<?php
require_once "auth_check.php";
require_once "role_management.php";

$page_title = "Unified Orders & Sales";
$role = current_user_role($pdo);

$action = $_POST['action'] ?? '';
$order_id = (int)($_POST['order_id'] ?? 0);
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order_id > 0) {
    if ($action === 'fulfill') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT status, order_type FROM store_orders WHERE id = ? FOR UPDATE");
            $stmt->execute([$order_id]);
            $order_info = $stmt->fetch();
            
            if ($order_info['status'] === 'Pending' && $order_info['order_type'] === 'Online') {
                $items = $pdo->prepare("SELECT oi.*, p.category_id, c.name as category_name FROM store_order_items oi JOIN products p ON oi.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE oi.order_id = ?");
                $items->execute([$order_id]);
                $order_items = $items->fetchAll();
                
                $can_fulfill = true;
                foreach($order_items as $item) {
                    $chk = $pdo->prepare("SELECT stock_qty FROM product_designs WHERE id = ?");
                    $chk->execute([$item['design_id']]);
                    $current_stock = (int)$chk->fetchColumn();
                    if($current_stock < $item['quantity']) {
                        $can_fulfill = false;
                        $error_msg = "Cannot fulfill order #$order_id. Insufficient stock (requested ".$item['quantity'].", available $current_stock) for Design ID ".$item['design_id'].".";
                        break;
                    }
                }
                
                if($can_fulfill) {
                    $salesperson_name = trim($_POST['salesperson_name'] ?? 'Admin');
                    $custom_weights = $_POST['custom_weight'] ?? []; // Map of category_id => total_weight_for_category
                    
                    // Pre-calculate total quantities per category
                    $cat_totals = [];
                    foreach($order_items as $item) {
                        $cid = $item['category_id'] ?: 'Uncategorized';
                        if(!isset($cat_totals[$cid])) $cat_totals[$cid] = 0;
                        $cat_totals[$cid] += $item['quantity'];
                    }
                    
                    foreach($order_items as $item) {
                        $qty = (int)$item['quantity'];
                        $did = (int)$item['design_id'];
                        $pid = (int)$item['product_id'];
                        $cid = $item['category_id'] ?: 'Uncategorized';
                        
                        $cat_weight = isset($custom_weights[$cid]) ? (float)$custom_weights[$cid] : 0.0;
                        $cat_qty = $cat_totals[$cid] > 0 ? $cat_totals[$cid] : 1;
                        $deduct_weight = ($qty / $cat_qty) * $cat_weight;
                        
                        $pdo->prepare("UPDATE product_designs SET stock_qty = stock_qty - ? WHERE id = ?")
                            ->execute([$qty, $did]);
                            
                        $pdo->prepare("UPDATE products SET total_stock_qty = total_stock_qty - ?, bulk_total_weight = bulk_total_weight - ? WHERE id = ?")
                            ->execute([$qty, $deduct_weight, $pid]);
                            
                        $pdo->prepare("UPDATE store_order_items SET total_weight = ? WHERE id = ?")
                            ->execute([$deduct_weight, $item['id']]);
                            
                        $note = "Auto-deducted for Web Store Order #$order_id";
                        $pdo->prepare("INSERT INTO stock_movements (product_id, design_id, movement_type, quantity, total_weight, movement_date, vendor_name, note) VALUES (?, ?, 'OUT', ?, ?, CURDATE(), ?, ?)")
                            ->execute([$pid, $did, $qty, $deduct_weight, $salesperson_name, $note]);
                    }
                    
                    // Invoice Generation
                    $invoice_cats = [];
                    foreach($order_items as $item) {
                        $cid = $item['category_id'] ?: 'Uncategorized';
                        $cat_name = $item['category_name'];
                        if (!empty($item['category_code'])) {
                            $cat_name .= ' - ' . $item['category_code'];
                        } elseif (empty($cat_name)) {
                            $cat_name = 'Uncategorized';
                        }
                        if(!isset($invoice_cats[$cid])) { $invoice_cats[$cid] = ['name' => $cat_name, 'qty' => 0]; }
                        $invoice_cats[$cid]['qty'] += $item['quantity'];
                    }

                    $inv_num = "INV-" . date('Ymd') . "-" . rand(1000, 9999);
                    $subtotal = 0;
                    $gross_weights = $_POST['gross_weight'] ?? [];
                    $pkt_weights = $_POST['pkt_weight'] ?? [];
                    $pkt_qtys = $_POST['pkt_qty'] ?? [];
                    
                    foreach($invoice_cats as $cid => &$data) {
                        $w = isset($custom_weights[$cid]) ? (float)$custom_weights[$cid] : 0;
                        $r = isset($_POST['rate'][$cid]) ? (float)$_POST['rate'][$cid] : 0;
                        $gw = isset($gross_weights[$cid]) ? (float)$gross_weights[$cid] : 0;
                        $pw = isset($pkt_weights[$cid]) ? $pkt_weights[$cid] : '';
                        $pq = isset($pkt_qtys[$cid]) ? (int)$pkt_qtys[$cid] : 0;
                        
                        $amt = round($w * $r, 2);
                        $data['weight'] = $w;
                        $data['rate'] = $r;
                        $data['amount'] = $amt;
                        $data['gross_weight'] = $gw;
                        $data['pkt_weight'] = $pw;
                        $data['pkt_qty'] = $pq;
                        $subtotal += $amt;
                    }
                    
                    $gst_percent = isset($_POST['gst_percent']) ? (float)$_POST['gst_percent'] : 0;
                    $parcel_charges = isset($_POST['parcel_charges']) ? (float)$_POST['parcel_charges'] : 0;
                    $final_amount = isset($_POST['final_amount']) ? (float)$_POST['final_amount'] : round($subtotal + $parcel_charges + ($subtotal * ($gst_percent/100)));
                    
                    $cust_stmt = $pdo->prepare("SELECT customer_phone FROM store_orders WHERE id = ?");
                    $cust_stmt->execute([$order_id]);
                    $customer_phone = $cust_stmt->fetchColumn();
                        
                    $customer_id = null;
                    if ($customer_phone) {
                        $c_stmt = $pdo->prepare("SELECT id FROM customers WHERE mobile_no = ?");
                        $c_stmt->execute([$customer_phone]);
                        $customer_id = $c_stmt->fetchColumn() ?: null;
                    }
                    
                    $stmt_inv = $pdo->prepare("INSERT INTO b2b_invoices (order_id, customer_id, invoice_number, invoice_date, subtotal, parcel_charges, gst_percent, grand_total) VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?)");
                    $stmt_inv->execute([$order_id, $customer_id, $inv_num, $subtotal, $parcel_charges, $gst_percent, $final_amount]);
                    $invoice_id = $pdo->lastInsertId();
                    
                    foreach($invoice_cats as $data) {
                        if($data['qty'] > 0) {
                            $pdo->prepare("INSERT INTO b2b_invoice_items (invoice_id, category_name, qty, pkt_qty, pkt_weight, gross_weight, weight, rate, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([$invoice_id, $data['name'], $data['qty'], $data['pkt_qty'], $data['pkt_weight'], $data['gross_weight'], $data['weight'], $data['rate'], $data['amount']]);
                        }
                    }
                    
                    $pdo->prepare("UPDATE store_orders SET status = 'Completed', total_amount = ? WHERE id = ?")->execute([$final_amount, $order_id]);
                    $success_msg = "Order #$order_id has been successfully fulfilled. B2B Invoice $inv_num generated.";
                }
            } else {
                $error_msg = "Order is already processed or is an offline order.";
            }
            if ($can_fulfill) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_msg = "Critical error processing order: " . $e->getMessage();
        }
    }
    
    if ($action === 'cancel') {
        $pdo->prepare("UPDATE store_orders SET status = 'Cancelled' WHERE id = ? AND order_type = 'Online'")->execute([$order_id]);
        $success_msg = "Order #$order_id has been cancelled. No stock was modified.";
    }
    
    if ($action === 'process_return') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT status FROM store_orders WHERE id = ? FOR UPDATE");
            $stmt->execute([$order_id]);
            $status = $stmt->fetchColumn();

            if (in_array($status, ['Completed', 'Partially Returned'])) {
                $return_qtys = $_POST['return_qty'] ?? []; 
                $salesperson_name = trim($_POST['salesperson_name'] ?? $_SESSION['user_name'] ?? 'Admin');
                $items = $pdo->prepare("SELECT oi.*, p.per_piece_weight, c.name as category_name FROM store_order_items oi JOIN products p ON oi.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE oi.order_id = ? FOR UPDATE");
                $items->execute([$order_id]);
                $order_items = $items->fetchAll();
                
                $itemsById = [];
                foreach($order_items as $itm) { $itemsById[$itm['id']] = $itm; }

                $anyReturns = false;
                $returned_cat_totals = [];

                foreach($return_qtys as $item_id => $rqty) {
                    $rqty = (int)$rqty;
                    if ($rqty <= 0 || !isset($itemsById[$item_id])) continue;
                    
                    $item = $itemsById[$item_id];
                    $max_ret = $item['quantity'] - $item['returned_qty'];
                    if ($rqty > $max_ret) {
                        throw new Exception("Cannot return more than purchased for item.");
                    }
                    
                    $anyReturns = true;
                    $base_weight = (float)(empty($item['total_weight']) ? ($item['quantity'] * $item['per_piece_weight']) : $item['total_weight']);
                    $weightToReturn = ($rqty / $item['quantity']) * $base_weight;

                    $pdo->prepare("UPDATE product_designs SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$rqty, $item['design_id']]);
                    $pdo->prepare("UPDATE products SET total_stock_qty = total_stock_qty + ?, bulk_total_weight = bulk_total_weight + ? WHERE id = ?")->execute([$rqty, $weightToReturn, $item['product_id']]);

                    $note = "Return Inwards for Order #" . $order_id;
                    $pdo->prepare("INSERT INTO stock_movements (product_id, design_id, movement_type, quantity, total_weight, movement_date, vendor_name, note) VALUES (?, ?, 'IN', ?, ?, CURDATE(), ?, ?)")
                        ->execute([$item['product_id'], $item['design_id'], $rqty, $weightToReturn, $salesperson_name, $note]);
                        
                    $pdo->prepare("UPDATE store_order_items SET returned_qty = returned_qty + ? WHERE id = ?")->execute([$rqty, $item_id]);
                        
                    $cid_name = $item['category_name'] ?: 'Uncategorized';
                    if (!isset($returned_cat_totals[$cid_name])) {
                        $returned_cat_totals[$cid_name] = ['qty' => 0, 'weight' => 0];
                    }
                    $returned_cat_totals[$cid_name]['qty'] += $rqty;
                    $returned_cat_totals[$cid_name]['weight'] += $weightToReturn;
                }
                
                if ($anyReturns) {
                    $items_after = $pdo->prepare("SELECT SUM(quantity) as tq, SUM(returned_qty) as trq FROM store_order_items WHERE order_id = ?");
                    $items_after->execute([$order_id]);
                    $chk = $items_after->fetch();
                    
                    $new_status = ($chk['tq'] > 0 && $chk['tq'] == $chk['trq']) ? 'Returned' : 'Partially Returned';
                    $pdo->prepare("UPDATE store_orders SET status = ? WHERE id = ?")->execute([$new_status, $order_id]);
                    
                    // Recalculate Invoice
                    $inv_stmt = $pdo->prepare("SELECT id, gst_percent FROM b2b_invoices WHERE order_id = ? FOR UPDATE");
                    $inv_stmt->execute([$order_id]);
                    $invoice = $inv_stmt->fetch();
                    if ($invoice) {
                        $invoice_id = $invoice['id'];
                        $subtotal = 0;
                        
                        $inv_items_stmt = $pdo->prepare("SELECT id, category_name, qty, weight, rate, amount FROM b2b_invoice_items WHERE invoice_id = ? FOR UPDATE");
                        $inv_items_stmt->execute([$invoice_id]);
                        $inv_items = $inv_items_stmt->fetchAll();
                        
                        foreach($inv_items as $ii) {
                            $cname = $ii['category_name'];
                            if (isset($returned_cat_totals[$cname])) {
                                $new_qty = $ii['qty'] - $returned_cat_totals[$cname]['qty'];
                                $new_weight = $ii['weight'] - $returned_cat_totals[$cname]['weight'];
                                
                                if ($new_qty < 0) $new_qty = 0;
                                if ($new_weight < 0) $new_weight = 0;
                                
                                $new_amount = round($new_weight * $ii['rate'], 2);
                                $subtotal += $new_amount;
                                
                                $pdo->prepare("UPDATE b2b_invoice_items SET qty = ?, weight = ?, amount = ? WHERE id = ?")->execute([$new_qty, $new_weight, $new_amount, $ii['id']]);
                            } else {
                                $subtotal += $ii['amount'];
                            }
                        }
                        
                        $gst_percent = (float)$invoice['gst_percent'];
                        $final_amount = $subtotal + ($subtotal * ($gst_percent/100));
                        
                        $pdo->prepare("UPDATE b2b_invoices SET subtotal = ?, grand_total = ? WHERE id = ?")->execute([$subtotal, $final_amount, $invoice_id]);
                        $pdo->prepare("UPDATE store_orders SET total_amount = ? WHERE id = ?")->execute([$final_amount, $order_id]);
                    }

                    $success_msg = "Order #$order_id returns processed! Status: $new_status. Invoice regenerated.";
                    $pdo->commit();
                } else {
                    $error_msg = "No valid quantities specified for return.";
                    $pdo->rollBack();
                }
            } else {
                $error_msg = "Only completed orders can be returned.";
                $pdo->rollBack();
            }
        } catch(Exception $e) {
            $pdo->rollBack();
            $error_msg = "Return failed: " . $e->getMessage();
        }
    }
}

$tab = $_GET['tab'] ?? 'Online';
if(!in_array($tab, ['Online', 'Offline'])) $tab = 'Online';

$status_filter = $_GET['status'] ?? '';

$sql = "SELECT * FROM store_orders WHERE order_type = ? ";
$params = [$tab];

if($tab === 'Online' && in_array($status_filter, ['Pending', 'Completed', 'Cancelled', 'Returned'])) {
    if ($status_filter === 'Returned') {
        $sql .= "AND status IN ('Returned', 'Partially Returned') ";
    } else {
        $sql .= "AND status = ? ";
        $params[] = $status_filter;
    }
}
$sql .= "ORDER BY created_at DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

require "templates/header.php";
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
      <h5 class="mb-0 text-dark"><i class="bi bi-box-seam me-2"></i> Unified Orders Hub</h5>
      <span class="text-secondary small">Manage and track both E-Commerce orders and POS in-store sales.</span>
  </div>
</div>

<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link fw-bold <?= $tab === 'Online' ? 'active text-primary' : 'text-muted' ?>" href="orders.php?tab=Online"><i class="bi bi-globe me-1"></i> Web/Online Orders</a>
  </li>
  <li class="nav-item">
    <a class="nav-link fw-bold <?= $tab === 'Offline' ? 'active text-primary' : 'text-muted' ?>" href="orders.php?tab=Offline"><i class="bi bi-shop me-1"></i> POS/Offline Sales</a>
  </li>
</ul>

<?php if($tab === 'Online'): ?>
<div class="mb-4">
  <div class="btn-group shadow-sm">
      <a href="orders.php?tab=Online" class="btn btn-outline-dark <?= $status_filter === '' ? 'active' : '' ?>">All Online</a>
      <a href="orders.php?tab=Online&status=Pending" class="btn btn-outline-warning text-dark <?= $status_filter === 'Pending' ? 'active' : '' ?>">Pending</a>
      <a href="orders.php?tab=Online&status=Completed" class="btn btn-outline-success <?= $status_filter === 'Completed' ? 'active' : '' ?>">Completed</a>
      <a href="orders.php?tab=Online&status=Returned" class="btn btn-outline-info text-dark <?= $status_filter === 'Returned' ? 'active' : '' ?>">Returns</a>
  </div>
</div>
<?php endif; ?>

<?php if($success_msg): ?>
  <div class="alert alert-success border-0 shadow-sm"><i class="bi bi-check-circle-fill me-2"></i> <?= e($success_msg) ?></div>
<?php endif; ?>
<?php if($error_msg): ?>
  <div class="alert alert-danger border-0 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error_msg) ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0 bg-white rounded-3">
  <div class="card-body p-0">
    <div class="table-responsive">
      <?php $modals_html = ''; ?>
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-4">Order Details</th>
            <th>Customer</th>
            <th>Value & Status</th>
            <th class="text-end pe-4">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td class="ps-4 align-middle">
                <div class="fw-bold <?= $o['order_type'] === 'Online' ? 'text-primary' : 'text-success' ?>">
                    #<?= str_pad($o['id'], 6, '0', STR_PAD_LEFT) ?>
                    <?php if($o['order_type'] === 'Offline'): ?>
                    <span class="badge bg-light text-dark border ms-2">POS</span>
                    <?php endif; ?>
                </div>
                <div class="text-secondary small mt-1"><?= date('M j, d - g:i A', strtotime($o['created_at'])) ?></div>
            </td>
            <td class="align-middle">
                <div class="fw-medium text-wrap" style="max-width: 120px;"><?= e($o['customer_name']) ?></div>
                <div class="small text-muted d-none d-md-block"><i class="bi bi-geo-alt-fill opacity-50 me-1"></i> <?= e($o['shipping_address'] ?: 'In-Store') ?></div>
            </td>
            <td class="align-middle">
                <div class="fw-bold text-dark mb-1">₹<?= number_format((float)$o['total_amount'], 2) ?></div>
                <?php
                if($o['status'] === 'Pending') {
                    echo '<span class="badge bg-warning text-dark px-2 py-1 shadow-sm"><i class="bi bi-hourglass-split"></i><span class="d-none d-sm-inline ms-1">Pending</span></span>';
                } elseif($o['status'] === 'Completed') {
                    echo '<span class="badge bg-success px-2 py-1 shadow-sm"><i class="bi bi-check2-circle"></i><span class="d-none d-sm-inline ms-1">Completed</span></span>';
                } elseif($o['status'] === 'Cancelled') {
                    echo '<span class="badge bg-danger px-2 py-1 shadow-sm"><i class="bi bi-x-circle"></i><span class="d-none d-sm-inline ms-1">Cancelled</span></span>';
                } elseif($o['status'] === 'Returned') {
                    echo '<span class="badge bg-secondary px-2 py-1 shadow-sm"><i class="bi bi-arrow-return-left"></i><span class="d-none d-sm-inline ms-1">Returned</span></span>';
                } elseif($o['status'] === 'Partially Returned') {
                    echo '<span class="badge bg-info text-dark px-2 py-1 shadow-sm"><i class="bi bi-arrow-return-right"></i><span class="d-none d-sm-inline ms-1">Partial Return</span></span>';
                }
                ?>
            </td>
            <td class="text-end pe-4 align-middle">
                <button class="btn btn-sm btn-light border shadow-sm fw-medium text-nowrap" type="button" data-bs-toggle="collapse" data-bs-target="#orderDet<?= $o['id'] ?>">
                    <span class="d-none d-md-inline">Review</span> <i class="bi bi-chevron-down ms-1 text-muted"></i>
                </button>
            </td>
          </tr>
          
          <!-- Collapsible Order Details -->
          <tr>
            <td colspan="4" class="p-0 border-0">
                <div class="collapse" id="orderDet<?= $o['id'] ?>">
                    <div class="p-4" style="background-color: #fcfcfc; border-bottom: 2px solid #eee;">
                        <div class="row g-4">
                            <div class="col-md-5">
                                <h6 class="fw-bold text-muted small text-uppercase" style="letter-spacing: 1px;">Logistics / Delivery</h6>
                                <div class="bg-white p-3 rounded-3 shadow-sm border border-light">
                                    <div class="mb-1 fw-medium"><?= e($o['customer_name']) ?></div>
                                    <p class="mb-0 text-secondary small"><?= nl2br(e($o['shipping_address'])) ?></p>
                                    <?php if($o['customer_phone'] && $o['customer_phone'] !== 'N/A'): ?>
                                    <div class="mt-2 text-primary small"><i class="bi bi-telephone"></i> <?= e($o['customer_phone']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if($o['order_notes']): ?>
                                <h6 class="fw-bold text-muted small text-uppercase mt-4" style="letter-spacing: 1px;">Internal Notes</h6>
                                <p class="small text-secondary fst-italic bg-light p-2 rounded border shadow-sm"><?= nl2br(e($o['order_notes'])) ?></p>
                                <?php endif; ?>
                                
                                <?php 
                                if ($o['status'] === 'Completed' || $o['order_type'] === 'Offline') {
                                    $salesperson = null;
                                    
                                    if ($o['order_type'] === 'Online') {
                                        $sp_stmt = $pdo->prepare("SELECT vendor_name FROM stock_movements WHERE note = ? LIMIT 1");
                                        $sp_stmt->execute(["Auto-deducted for Web Store Order #" . $o['id']]);
                                        $salesperson = $sp_stmt->fetchColumn();
                                    } else {
                                        if (preg_match('/Processed by:\s*(.+)$/i', $o['order_notes'], $m)) {
                                            $salesperson = trim($m[1]);
                                        } else {
                                            $salesperson = 'Admin (Legacy)';
                                        }
                                    }
                                    
                                    if ($salesperson) {
                                ?>
                                <div class="mt-4">
                                    <h6 class="fw-bold text-muted small text-uppercase" style="letter-spacing: 1px;">Processed By</h6>
                                    <div class="d-inline-flex align-items-center gap-2 bg-light border rounded px-3 py-2 shadow-sm">
                                        <i class="bi bi-person-badge text-primary" style="font-size: 1.2rem;"></i>
                                        <span class="fw-medium text-dark"><?= e($salesperson) ?></span>
                                    </div>
                                </div>
                                <?php 
                                    }
                                } 
                                ?>
                            </div>
                            
                            <div class="col-md-7">
                                <h6 class="fw-bold text-muted small text-uppercase" style="letter-spacing: 1px;">Transaction Items</h6>
                                <div class="bg-white rounded-3 shadow-sm border border-light p-0 overflow-hidden">
                                    <table class="table table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3 border-0 py-2 text-muted fw-medium">Item Details</th>
                                                <th class="text-center border-0 py-2 text-muted fw-medium">Qty</th>
                                                <th class="text-end pe-3 border-0 py-2 text-muted fw-medium">Pricing Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $items = $pdo->prepare("
                                            SELECT oi.*, d.design_name, d.sku as design_sku, d.image_path, d.stock_qty as current_stock, p.name as product_name, p.sku as product_sku, p.category_id, c.name as category_name, c.code as category_code
                                            FROM store_order_items oi
                                            JOIN product_designs d ON oi.design_id = d.id
                                            JOIN products p ON oi.product_id = p.id
                                            LEFT JOIN categories c ON p.category_id = c.id
                                            WHERE oi.order_id = ?
                                        ");
                                        $items->execute([$o['id']]);
                                        $order_items_cache = $items->fetchAll();
                                        
                                        $grouped_products = [];
                                        foreach($order_items_cache as $item) {
                                            $pid = $item['product_id'];
                                            if (!isset($grouped_products[$pid])) {
                                                $grouped_products[$pid] = [
                                                    'product_id' => $pid,
                                                    'product_name' => $item['product_name'],
                                                    'product_sku' => $item['product_sku'] ?: 'N/A',
                                                    'total_qty' => 0,
                                                    'returned_qty' => 0,
                                                    'image_path' => $item['image_path'],
                                                    'category_name' => $item['category_name'],
                                                    'designs' => []
                                                ];
                                            }
                                            $grouped_products[$pid]['total_qty'] += $item['quantity'];
                                            $grouped_products[$pid]['returned_qty'] += $item['returned_qty'];
                                            $grouped_products[$pid]['designs'][] = [
                                                'design_name' => $item['design_name'],
                                                'design_sku' => $item['design_sku'],
                                                'quantity' => $item['quantity'],
                                                'returned_qty' => $item['returned_qty'],
                                                'image_path' => $item['image_path']
                                            ];
                                        }

                                        foreach($grouped_products as $gp):
                                            $collapse_id = "collapseDesigns_" . $o['id'] . "_" . $gp['product_id'];
                                        ?>
                                        <tr style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>" aria-expanded="false" class="hover-bg-light">
                                            <td class="ps-3 border-0 py-3">
                                                <div class="d-flex align-items-center gap-3">
                                                    <?php if($gp['image_path']): 
                                                        $first_img = explode(',', $gp['image_path'])[0];
                                                    ?>
                                                        <a href="#" onclick="event.stopPropagation(); showImageModal('<?= e($gp['image_path']) ?>'); return false;">
                                                            <img src="<?= e($first_img) ?>" class="rounded bg-light object-fit-cover shadow-sm" style="width: 50px; height: 50px; border: 1px solid #eee;">
                                                        </a>
                                                    <?php else: ?>
                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted shadow-sm" style="width: 50px; height: 50px; border: 1px solid #eee;"><i class="bi bi-image"></i></div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?= e($gp['product_name']) ?> <i class="bi bi-chevron-down text-muted ms-1" style="font-size: 0.8rem;"></i></div>
                                                        <div class="small text-muted"><span class="px-1 bg-light border rounded" style="font-size: 0.70rem;">SKU: <?= e($gp['product_sku']) ?></span></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center align-middle border-0 py-3 fw-bold fs-6">
                                                x<?= $gp['total_qty'] ?>
                                                <?php if($gp['returned_qty'] > 0): ?>
                                                    <br><span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill fw-medium px-2 py-1" style="font-size: 0.70rem;"><i class="bi bi-arrow-return-left me-1"></i> Ret: <?= $gp['returned_qty'] ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-3 align-middle border-0 py-3 small fst-italic text-secondary">Pricing TBD</td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="p-0 border-0">
                                                <div class="collapse bg-light border-bottom" id="<?= $collapse_id ?>">
                                                    <div class="p-3">
                                                        <h6 class="small fw-bold text-muted mb-3 text-uppercase">Selected Designs for <?= e($gp['product_name']) ?></h6>
                                                        <div class="d-flex flex-wrap gap-3">
                                                            <?php foreach($gp['designs'] as $ds): 
                                                                $d_img = $ds['image_path'] ? explode(',', $ds['image_path'])[0] : '';
                                                            ?>
                                                            <div class="d-flex align-items-center gap-2 bg-white border rounded p-2 shadow-sm" style="min-width: 200px;">
                                                                <?php if($d_img): ?>
                                                                    <a href="#" onclick="showImageModal('<?= e($ds['image_path']) ?>'); return false;">
                                                                        <img src="<?= e($d_img) ?>" class="rounded object-fit-cover shadow-sm" style="width: 45px; height: 45px; cursor: zoom-in;" title="Click to view full image">
                                                                    </a>
                                                                <?php else: ?>
                                                                    <div class="bg-secondary bg-opacity-10 rounded d-flex align-items-center justify-content-center text-muted" style="width: 45px; height: 45px;"><i class="bi bi-image"></i></div>
                                                                <?php endif; ?>
                                                                <div class="lh-sm">
                                                                    <div class="fw-bold text-dark" style="font-size: 0.85rem;"><?= e($ds['design_name']) ?></div>
                                                                    <div class="text-muted" style="font-size: 0.75rem;">SKU: <?= e($ds['design_sku']) ?></div>
                                                                    <div class="fw-bold text-primary mt-1" style="font-size: 0.8rem;">Qty: <?= $ds['quantity'] ?></div>
                                                                </div>
                                                            </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="border-top">
                                            <tr>
                                                <td colspan="2" class="ps-3 py-2 fw-medium text-muted">Final Order Value</td>
                                                <td class="text-end pe-3 py-2 fw-medium text-success fs-6"><i class="bi bi-whatsapp"></i> WhatsApp Quote</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <?php if($o['status'] === 'Pending' && $o['order_type'] === 'Online'): ?>
                        <div class="d-flex justify-content-end gap-3 mt-4 pt-3 border-top">
                            <form method="POST" onsubmit="return confirm('Cancel this order? This will reject the order and no stock will be deducted.');">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger shadow-sm rounded-pill px-4 hover-lift">Cancel Order</button>
                            </form>
                            <a href="admin_edit_order.php?id=<?= $o['id'] ?>" class="btn btn-outline-primary shadow-sm fw-bold px-4 py-2 rounded-pill hover-lift tracking-wide">
                                <i class="bi bi-pencil-square me-2"></i> Edit Order
                            </a>
                            
                            <button type="button" class="btn btn-success shadow-sm fw-bold px-4 py-2 rounded-pill hover-lift tracking-wide" data-bs-toggle="modal" data-bs-target="#fulfillModal<?= $o['id'] ?>">
                                <i class="bi bi-box-seam me-2"></i> Approve & Deduct Stock
                            </button>
                        </div>
                        
                        <!-- Fulfillment Modal (Custom Weights) -->
                        <?php ob_start(); ?>
                        <div class="modal fade" id="fulfillModal<?= $o['id'] ?>" tabindex="-1">
                          <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                              <form method="POST">
                                <input type="hidden" name="action" value="fulfill">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                
                                <div class="modal-header border-bottom-0 pb-0">
                                  <h5 class="modal-title fw-bold">Fulfill Order #<?= str_pad($o['id'], 6, '0', STR_PAD_LEFT) ?></h5>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body pb-4">
                                    <div class="alert alert-info border-0 shadow-sm small mb-4">
                                        <i class="bi bi-info-circle-fill me-2"></i> Please specify the exact physical <strong>total weight</strong> of the products being shipped so we can accurately deduct it from the bulk stock.
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <label class="form-label fw-bold small text-uppercase text-muted">Sales Person Name <span class="text-danger">*</span></label>
                                            <input type="text" name="salesperson_name" class="form-control" required placeholder="Who is processing this sale?">
                                        </div>
                                    </div>
                                    
                                    <h6 class="fw-bold fs-6 border-bottom pb-2 mb-3">Sub-Category Weight Verification</h6>
                                    <?php 
                                    $grouped_items = [];
                                    $insufficient_stock_items = [];
                                    
                                    foreach($order_items_cache as $item) {
                                        if ($item['quantity'] > $item['current_stock']) {
                                            $insufficient_stock_items[] = "{$item['design_sku']} (Req: {$item['quantity']}, Stock: {$item['current_stock']})";
                                        }
                                        $cid = $item['category_id'] ?: 'Uncategorized';
                                        $group_name = $item['category_name'];
                                        if (!empty($item['category_code'])) {
                                            $group_name .= ' - ' . $item['category_code'];
                                        } elseif (empty($group_name)) {
                                            $group_name = 'Uncategorized';
                                        }
                                        if (!isset($grouped_items[$cid])) {
                                            $grouped_items[$cid] = [
                                                'category_name' => $group_name,
                                                'total_qty' => 0,
                                                'items' => []
                                            ];
                                        }
                                        $grouped_items[$cid]['total_qty'] += $item['quantity'];
                                        $grouped_items[$cid]['items'][] = $item;
                                    }
                                    ?>
                                    
                                    <?php if(!empty($insufficient_stock_items)): ?>
                                    <div class="alert alert-danger border-0 shadow-sm small mb-4">
                                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5 align-middle"></i> 
                                        <strong>Critcal Warning:</strong> Insufficient physical stock remaining to fulfill this order! Please cancel it or update inventory.
                                        <ul class="mb-0 mt-2">
                                            <?php foreach($insufficient_stock_items as $err): ?>
                                            <li><?= e($err) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                    <div class="invoice-calculator">
                                        <?php foreach($grouped_items as $cid => $group): ?>
                                        <div class="card mb-3 border bg-light shadow-sm invoice-row-group">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="bg-primary bg-opacity-10 rounded d-flex align-items-center justify-content-center text-primary shadow-sm" style="width: 35px; height: 35px;"><i class="bi bi-tags-fill"></i></div>
                                                        <div>
                                                           <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?= e($group['category_name']) ?></div>
                                                           <div class="small text-muted"><?= count($group['items']) ?> variant(s) &middot; <span class="fw-bold text-dark"><?= $group['total_qty'] ?> pcs total</span></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-flex flex-wrap gap-2 mb-3 bg-white p-2 rounded border border-secondary border-opacity-25 shadow-sm">
                                                <?php foreach($group['items'] as $gItem): ?>
                                                   <div class="d-flex align-items-center gap-2 border bg-light py-1 px-2 rounded" style="font-size: 0.75rem;">
                                                      <?php if($gItem['image_path']): 
                                                          $first_img = explode(',', $gItem['image_path'])[0];
                                                      ?>
                                                         <img src="<?= e($first_img) ?>" class="rounded shadow-sm" style="width: 45px; height: 45px; object-fit: cover; cursor: pointer;" onclick="showImageModal('<?= e($gItem['image_path']) ?>')" title="Click to View">
                                                      <?php else: ?>
                                                         <div class="bg-secondary bg-opacity-25 rounded d-flex align-items-center justify-content-center text-muted" style="width: 45px; height: 45px;"><i class="bi bi-image"></i></div>
                                                      <?php endif; ?>
                                                      <div class="lh-sm">
                                                         <strong class="text-dark"><?= e($gItem['design_name']) ?></strong><br>
                                                         <span class="text-muted">Req: <?= $gItem['quantity'] ?> pcs</span>
                                                      </div>
                                                   </div>
                                                <?php endforeach; ?>
                                                </div>
                                                
                                                <div class="row g-2 mb-2">
                                                    <!-- Gross Weight -->
                                                    <div class="col-12 col-md-3">
                                                        <label class="form-label small text-muted mb-1 fw-medium">Gross Wt (g)</label>
                                                        <input type="number" step="0.001" min="0" name="gross_weight[<?= e($cid) ?>]" class="form-control form-control-sm border-dark-subtle shadow-sm calc-gross-weight rounded-3" placeholder="0.000" oninput="calculateNetWeight(this)">
                                                    </div>
                                                    
                                                    <!-- Packets Wrapper -->
                                                    <div class="col-12 col-md-9">
                                                        <div class="packet-container" data-cid="<?= e($cid) ?>">
                                                            <!-- Packet 1 -->
                                                            <div class="row g-2 packet-row" id="pkt_row_<?= e($cid) ?>_1">
                                                                <div class="col-6 col-md-4">
                                                                    <label class="form-label small text-muted mb-1 fw-medium">Pkt 1 Wt (g)</label>
                                                                    <input type="number" step="0.001" min="0" class="form-control form-control-sm border-dark-subtle shadow-sm calc-pkt-weight rounded-3" placeholder="0.000" oninput="calculateNetWeight(this); showNextPacket(this, 2)">
                                                                </div>
                                                                <div class="col-6 col-md-4">
                                                                    <label class="form-label small text-muted mb-1 fw-medium">Pkt 1 Qty</label>
                                                                    <input type="number" step="1" min="0" class="form-control form-control-sm border-dark-subtle shadow-sm calc-pkt-qty rounded-3" placeholder="0" oninput="calculateNetWeight(this); showNextPacket(this, 2)">
                                                                    <div class="invalid-feedback" style="font-size: 0.65rem; margin-top: 2px;">Exceeds total!</div>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Packet 2 to 5 -->
                                                            <?php for($i=2; $i<=5; $i++): ?>
                                                            <div class="row g-2 packet-row d-none mt-1" id="pkt_row_<?= e($cid) ?>_<?= $i ?>">
                                                                <div class="col-6 col-md-4">
                                                                    <label class="form-label small text-muted mb-1 fw-medium">Pkt <?= $i ?> Wt (g)</label>
                                                                    <input type="number" step="0.001" min="0" class="form-control form-control-sm border-dark-subtle shadow-sm calc-pkt-weight rounded-3" placeholder="0.000" oninput="calculateNetWeight(this); showNextPacket(this, <?= $i+1 ?>)">
                                                                </div>
                                                                <div class="col-6 col-md-4">
                                                                    <label class="form-label small text-muted mb-1 fw-medium">Pkt <?= $i ?> Qty</label>
                                                                    <input type="number" step="1" min="0" class="form-control form-control-sm border-dark-subtle shadow-sm calc-pkt-qty rounded-3" placeholder="0" oninput="calculateNetWeight(this); showNextPacket(this, <?= $i+1 ?>)">
                                                                    <div class="invalid-feedback" style="font-size: 0.65rem; margin-top: 2px;">Exceeds total!</div>
                                                                </div>
                                                            </div>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row g-2 mt-2 bg-white p-2 rounded border border-primary border-opacity-25 shadow-sm">
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label small text-primary mb-1 fw-bold">Total Pkt Qty</label>
                                                        <input type="number" data-max-qty="<?= $group['total_qty'] ?>" class="form-control form-control-sm bg-light fw-bold text-dark border-primary shadow-sm calc-total-qty rounded-3" readonly value="0">
                                                        <input type="hidden" name="pkt_weight[<?= e($cid) ?>]" class="calc-total-pkt-weight" value="0">
                                                        <input type="hidden" name="pkt_qty[<?= e($cid) ?>]" class="calc-hidden-pkt-qty" value="0">
                                                        <div class="invalid-feedback" style="font-size: 0.70rem;">Exceeds <?= $group['total_qty'] ?>!</div>
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label small text-success mb-1 fw-bold">Net Wt (g) <span class="text-danger">*</span></label>
                                                        <input type="number" step="0.001" min="0" name="custom_weight[<?= e($cid) ?>]" class="form-control form-control-sm border-success shadow-sm row-weight fw-bold text-success rounded-3" required placeholder="0.000" oninput="calculateInvoice(this)">
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label small text-muted mb-1 fw-medium">Rate (₹/g) <span class="text-danger">*</span></label>
                                                        <input type="number" step="0.01" min="0" name="rate[<?= e($cid) ?>]" class="form-control form-control-sm border-dark-subtle shadow-sm row-rate rounded-3" required placeholder="0.00" oninput="calculateInvoice(this)">
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label small text-muted mb-1 fw-medium">Amount (₹)</label>
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text bg-light border-0 fw-bold text-muted d-md-none">Total: ₹</span>
                                                            <input type="text" class="form-control form-control-sm border-0 bg-transparent fw-bold row-amount text-md-end fs-6" readonly value="0.00">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                        <div class="card border-primary shadow-sm mt-4">
                                            <div class="card-body p-3 bg-light">
                                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-2">
                                                    <span class="fw-medium text-muted">Subtotal Amount</span>
                                                    <span class="fw-bold text-dark fs-5 mt-1 mt-sm-0">₹<span class="invoice-subtotal">0.00</span></span>
                                                </div>
                                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3">
                                                    <span class="fw-medium text-muted mb-2 mb-sm-0 w-100">Parcel Charges (₹)</span>
                                                    <div class="input-group input-group-sm w-100" style="max-width: 150px;">
                                                        <input type="number" step="0.01" min="0" name="parcel_charges" class="form-control text-end shadow-sm invoice-parcel border-dark-subtle" placeholder="0.00" oninput="calculateInvoice(this)">
                                                        <span class="input-group-text bg-white border-dark-subtle">₹</span>
                                                    </div>
                                                </div>
                                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center mb-3">
                                                    <span class="fw-medium text-muted mb-2 mb-sm-0 w-100">GST % (Optional)</span>
                                                    <div class="input-group input-group-sm w-100" style="max-width: 150px;">
                                                        <input type="number" step="0.01" min="0" name="gst_percent" class="form-control text-end shadow-sm invoice-gst border-dark-subtle" placeholder="e.g. 3" oninput="calculateInvoice(this)">
                                                        <span class="input-group-text bg-white border-dark-subtle">%</span>
                                                    </div>
                                                </div>
                                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center pt-3 border-top border-primary">
                                                    <span class="fw-bold text-primary fs-5 mb-2 mb-sm-0">GRAND TOTAL</span>
                                                    <div class="input-group w-100" style="max-width: 200px;">
                                                        <span class="input-group-text bg-white border-primary fw-bold text-primary">₹</span>
                                                        <input type="number" step="0.01" min="0" name="final_amount" class="form-control text-end fw-bold shadow-sm invoice-grandtotal bg-white border-primary fs-5" readonly value="0.00" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer border-top-0 pt-0 d-flex flex-column flex-md-row gap-2 w-100 mt-2 px-4 pb-4">
                                  <button type="submit" class="btn btn-success rounded-pill fw-bold px-4 shadow-sm flex-fill order-1 order-md-3 mt-2 mt-md-0 w-100 w-md-auto" onclick="return confirm('Confirm weights? This will permanently deduct from inventory.');" <?= !empty($insufficient_stock_items) ? 'disabled' : '' ?>>Confirm & Deduct Stock</button>
                                  <button type="submit" formaction="invoice_draft.php" formtarget="_blank" title="Preview PDF without stock deduction" class="btn btn-outline-dark shadow-sm rounded-pill px-4 flex-fill order-2 order-md-2 mt-2 mt-md-0 w-100 w-md-auto" <?= !empty($insufficient_stock_items) ? 'disabled' : '' ?>><i class="bi bi-file-earmark-pdf me-1"></i> Download B2B</button>
                                  <button type="button" class="btn btn-light rounded-pill flex-fill order-3 order-md-1 mt-2 mt-md-0 w-100 w-md-auto" data-bs-dismiss="modal">Cancel</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>
                        <?php $modals_html .= ob_get_clean(); ?>
                        <!-- End Modal -->
                        
                        <?php elseif(in_array($o['status'], ['Completed', 'Partially Returned', 'Returned'])): ?>
                        <div class="d-flex justify-content-end gap-3 mt-4 pt-3 border-top">
                            <?php if ($o['order_type'] === 'Online'):
                                $chk_inv = $pdo->prepare("SELECT id FROM b2b_invoices WHERE order_id = ?");
                                $chk_inv->execute([$o['id']]);
                                if($chk_inv->fetch()):
                            ?>
                            <a href="invoice_print.php?order_id=<?= $o['id'] ?>" target="_blank" class="btn btn-dark shadow-sm rounded-pill px-4 hover-lift">
                                <i class="bi bi-file-earmark-pdf me-2"></i> Download B2B Invoice
                            </a>
                            <?php 
                                endif; 
                            endif; 
                            ?>
                            
                            <?php if($o['status'] !== 'Returned'): ?>
                            <button type="button" class="btn btn-outline-danger shadow-sm rounded-pill px-4 hover-lift" data-bs-toggle="modal" data-bs-target="#returnModal<?= $o['id'] ?>">
                                <i class="bi bi-arrow-return-left me-1"></i> Process Return
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php 
                        ob_start(); 
                        $ri = $pdo->prepare("SELECT oi.*, p.name as product_name, d.design_name as design_name, d.image_path as design_image FROM store_order_items oi JOIN products p ON oi.product_id=p.id LEFT JOIN product_designs d ON oi.design_id=d.id WHERE oi.order_id = ?");
                        $ri->execute([$o['id']]);
                        $ret_items = $ri->fetchAll();
                        ?>
                        <div class="modal fade" id="returnModal<?= $o['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <form method="POST" class="modal-content rounded-4 shadow-lg border-0 bg-light">
                                    <input type="hidden" name="action" value="process_return">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <div class="modal-header bg-white border-0 py-3 px-4">
                                        <div>
                                            <h5 class="modal-title fw-bold text-dark mb-0">Partial/Full Return Process</h5>
                                            <small class="text-muted text-uppercase" style="letter-spacing:1px;">Order #<?= $o['id'] ?></small>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4 bg-light text-start">
                                        <div class="alert alert-warning border border-warning-subtle rounded-3 mb-4 d-flex align-items-center shadow-sm">
                                            <i class="bi bi-info-circle-fill fs-5 me-3 text-warning"></i>
                                            <div>
                                                <strong>Select items to return:</strong> Specify the exact quantity. It will proportionally calculate weights and restock inventory immediately.
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex flex-column gap-3">
                                            <?php foreach ($ret_items as $ritem): 
                                                $max_ret = $ritem['quantity'] - $ritem['returned_qty'];
                                                if ($max_ret > 0):
                                            ?>
                                            <div class="card border border-dark-subtle border-opacity-10 shadow-sm overflow-hidden mb-1">
                                              <div class="card-body p-3 bg-white d-flex justify-content-between align-items-center">
                                                <div class="d-flex align-items-center gap-3">
                                                  <?php if ($ritem['design_image']): ?>
                                                    <img src="<?= e($ritem['design_image']) ?>" class="rounded bg-light shadow-sm" style="width: 50px; height: 50px; object-fit: cover;">
                                                  <?php else: ?>
                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted border border-secondary-subtle" style="width: 50px; height: 50px; font-size: 1.2rem;"><i class="bi bi-gem"></i></div>
                                                  <?php endif; ?>
                                                  <div>
                                                    <h6 class="mb-0 fw-bold text-dark"><?= e($ritem['design_name'] ?? 'Unknown Design') ?></h6>
                                                    <small class="text-secondary d-block"><?= e($ritem['product_name']) ?></small>
                                                  </div>
                                                </div>
                                                
                                                <div class="d-flex flex-column align-items-end text-end" style="min-width:180px;">
                                                    <span class="badge bg-secondary-subtle text-dark border border-secondary-subtle rounded-pill px-3 py-1 mb-2 d-inline-block">Max returnable: <?= $max_ret ?></span>
                                                    <div class="input-group input-group-sm w-100 shadow-sm" style="max-width: 130px;">
                                                        <span class="input-group-text bg-light text-muted border-dark-subtle">Qty</span>
                                                        <input type="number" name="return_qty[<?= $ritem['id'] ?>]" min="0" max="<?= $max_ret ?>" value="0" class="form-control text-center border-dark-subtle fw-bold">
                                                    </div>
                                                </div>
                                              </div>
                                            </div>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                    </div>
                                    <div class="modal-footer bg-white border-0 p-4">
                                        <div class="w-100 d-flex flex-column flex-md-row gap-2 justify-content-between">
                                            <div class="col-md-5 mb-2 mb-md-0">
                                                <div class="input-group shadow-sm">
                                                    <span class="input-group-text bg-light text-muted border-dark-subtle"><i class="bi bi-person"></i></span>
                                                    <input class="form-control bg-white border-dark-subtle text-dark" name="salesperson_name" list="spListModal<?= $o['id'] ?>" placeholder="Staff Name" value="<?= e($_SESSION['user_name'] ?? '') ?>" required>
                                                </div>
                                                <datalist id="spListModal<?= $o['id'] ?>">
                                                    <?php foreach ($salespeople as $sp): ?>
                                                        <option value="<?= e($sp['name']) ?>"></option>
                                                    <?php endforeach; ?>
                                                </datalist>
                                            </div>
                                            
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-light rounded-pill px-4 shadow-sm border border-secondary-subtle" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-warning rounded-pill px-4 shadow-sm hover-lift fw-bold" onclick="return confirm('Confirm processing this exact return quantity?');">
                                                    <i class="bi bi-arrow-return-left me-1"></i> Confirm Return
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php $modals_html .= ob_get_clean(); ?>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($orders)): ?>
          <tr>
            <td colspan="4" class="text-center py-5">
                <i class="bi bi-inbox display-4 text-muted opacity-25 d-block mb-3"></i>
                <h5 class="text-secondary fw-medium">No results found in this tab.</h5>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?= $modals_html ?? '' ?>

<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" style="z-index: 9999;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-transparent border-0">
      <div class="modal-body text-center position-relative p-0">
        <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-2 shadow" data-bs-dismiss="modal" style="z-index: 10; cursor: pointer; background-color: rgba(255,255,255,0.8); border-radius: 50%; opacity: 1; padding: 10px;"></button>
        <img id="previewImageSrc" src="" class="img-fluid rounded shadow-lg" style="max-height: 80vh; max-width: 100%;">
      </div>
    </div>
  </div>
</div>
<script>
function showImageModal(src) {
    if(!src) return;
    const images = src.split(',');
    const container = document.querySelector('#imagePreviewModal .modal-body');
    let html = `<button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-2 shadow" data-bs-dismiss="modal" style="z-index: 10; cursor: pointer; background-color: rgba(255,255,255,0.8); border-radius: 50%; opacity: 1; padding: 10px;"></button>`;
    if (images.length > 1) {
        html += `
        <div id="orderModalCarousel" class="carousel slide" data-bs-ride="carousel">
          <div class="carousel-inner">
            ${images.map((img, i) => `
              <div class="carousel-item ${i === 0 ? 'active' : ''}">
                <img src="${img.trim()}" class="img-fluid rounded shadow-lg" style="max-height: 80vh; max-width: 100%;">
              </div>
            `).join('')}
          </div>
          <button class="carousel-control-prev" type="button" data-bs-target="#orderModalCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true" style="filter: drop-shadow(0px 0px 3px rgba(0,0,0,0.8));"></span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#orderModalCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true" style="filter: drop-shadow(0px 0px 3px rgba(0,0,0,0.8));"></span>
          </button>
        </div>
        `;
    } else {
        html += `<img src="${images[0].trim()}" class="img-fluid rounded shadow-lg" style="max-height: 80vh; max-width: 100%;">`;
    }
    container.innerHTML = html;
    var myModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    myModal.show();
}

function calculateInvoice(element) {
    const container = element.closest('.invoice-calculator');
    let subtotal = 0;
    
    // Calculate row amounts and subtotal
    container.querySelectorAll('.invoice-row-group').forEach(row => {
        const weight = parseFloat(row.querySelector('.row-weight').value) || 0;
        const rate = parseFloat(row.querySelector('.row-rate').value) || 0;
        const amount = weight * rate;
        
        row.querySelector('.row-amount').value = amount.toFixed(2);
        subtotal += amount;
    });
    
    container.querySelector('.invoice-subtotal').textContent = subtotal.toFixed(2);
    
    // Calculate Grand Total with GST and Parcel Charges
    const gstPercent = parseFloat(container.querySelector('.invoice-gst').value) || 0;
    const parcelCharges = parseFloat(container.querySelector('.invoice-parcel') ? container.querySelector('.invoice-parcel').value : 0) || 0;
    const gstAmount = subtotal * (gstPercent / 100);
    const grandTotalExact = subtotal + parcelCharges + gstAmount;
    const grandTotal = Math.round(grandTotalExact);
    
    container.querySelector('.invoice-grandtotal').value = grandTotal.toFixed(2);
}

function showNextPacket(el, nextId) {
    if(nextId > 5) return;
    
    const container = el.closest('.packet-container');
    const calculatorDiv = container.closest('.invoice-row-group');
    const totalQtyInput = calculatorDiv.querySelector('.calc-total-qty');
    const maxQty = parseInt(totalQtyInput.getAttribute('data-max-qty')) || 0;
    
    let currentTotal = 0;
    container.querySelectorAll('.calc-pkt-qty').forEach(input => {
        currentTotal += parseInt(input.value) || 0;
    });

    const val = parseFloat(el.value) || 0;
    if(val > 0 && currentTotal < maxQty) {
        const cid = container.getAttribute('data-cid');
        const nextRow = container.querySelector('#pkt_row_' + cid + '_' + nextId);
        if(nextRow) nextRow.classList.remove('d-none');
    }
}

function calculateNetWeight(el) {
    const calculatorDiv = el.closest('.invoice-row-group');
    
    const grossWeightInput = calculatorDiv.querySelector('.calc-gross-weight');
    const totalQtyInput = calculatorDiv.querySelector('.calc-total-qty');
    const netWeightInput = calculatorDiv.querySelector('.row-weight');
    
    // Clear all previous errors on packet inputs
    calculatorDiv.querySelectorAll('.calc-pkt-qty').forEach(input => {
        input.classList.remove('is-invalid');
    });
    
    let grossWeight = parseFloat(grossWeightInput.value) || 0;
    let totalPktQty = 0;
    let totalPktWeight = 0;
    
    let pktDetails = [];
    
    let pktIndex = 1;
    const packetRows = calculatorDiv.querySelectorAll('.packet-row');
    packetRows.forEach(row => {
        let pktWt = parseFloat(row.querySelector('.calc-pkt-weight').value) || 0;
        let pktQty = parseInt(row.querySelector('.calc-pkt-qty').value) || 0;
        if (pktWt > 0 || pktQty > 0) {
            totalPktQty += pktQty;
            totalPktWeight += (pktWt * pktQty);
        }
        if (pktWt > 0 && pktQty > 0) {
            let total = (pktWt * pktQty).toFixed(3);
            pktDetails.push('PKT ' + pktIndex + ' (' + pktQty + 'x' + pktWt.toFixed(3) + '=' + total + ')');
            pktIndex++;
        }
    });
    
    totalQtyInput.value = totalPktQty;
    
    const maxQty = parseInt(totalQtyInput.getAttribute('data-max-qty')) || 0;
    if (totalPktQty > maxQty) {
        totalQtyInput.classList.add('is-invalid');
        totalQtyInput.classList.remove('border-primary', 'text-dark');
        netWeightInput.setCustomValidity("Total packet quantity cannot exceed the total quantity of selected designs (" + maxQty + ").");
        
        if (el && el.classList.contains('calc-pkt-qty')) {
            el.classList.add('is-invalid');
        }
    } else {
        totalQtyInput.classList.remove('is-invalid');
        totalQtyInput.classList.add('border-primary', 'text-dark');
        netWeightInput.setCustomValidity("");
    }
    
    let totalPktWeightInput = calculatorDiv.querySelector('.calc-total-pkt-weight');
    let hiddenPktQtyInput = calculatorDiv.querySelector('.calc-hidden-pkt-qty');
    if(totalPktWeightInput) totalPktWeightInput.value = pktDetails.join('\n');
    if(hiddenPktQtyInput) hiddenPktQtyInput.value = totalPktQty;
    
    if (grossWeightInput.value !== '') {
        let netWeight = grossWeight - totalPktWeight;
        if(netWeight < 0) netWeight = 0;
        netWeightInput.value = netWeight.toFixed(3);
    }
    
    calculateInvoice(netWeightInput);
}
</script>

<?php require "templates/footer.php"; ?>
