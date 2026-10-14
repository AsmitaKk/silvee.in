<?php
$page_title = "Edit Order #" . (int)($_GET['id'] ?? 0);
require_once "config.php";
require_once "functions.php";
require_once "templates/header.php";

if (!$is_admin) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Access Denied. Admins only.</div></div>";
    require_once "templates/footer.php";
    exit;
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    die("<div class='container py-5'><div class='alert alert-danger'>Invalid Order ID.</div></div>");
}

$stmt = $pdo->prepare("SELECT * FROM store_orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order || $order['status'] !== 'Pending') {
     die("<div class='container py-5'><div class='alert alert-danger'>Order not found or it is no longer in Pending status. Only Pending online orders can be customized.</div></div>");
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // 1. Update Existing Item Quantities
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach($_POST['items'] as $item_id => $qty) {
                $qty = (int)$qty;
                if ($qty > 0) {
                    $pdo->prepare("UPDATE store_order_items SET quantity = ? WHERE id = ? AND order_id = ?")->execute([$qty, $item_id, $order_id]);
                } else {
                    $pdo->prepare("DELETE FROM store_order_items WHERE id = ? AND order_id = ?")->execute([$item_id, $order_id]);
                }
            }
        }
        
        // 2. Add New Item
        if (!empty($_POST['new_design_id']) && !empty($_POST['new_qty'])) {
            $did = (int)$_POST['new_design_id'];
            $qty = (int)$_POST['new_qty'];
            
            // Get product mapping
            $stmt_d = $pdo->prepare("SELECT d.product_id, p.selling_price FROM product_designs d JOIN products p ON d.product_id = p.id WHERE d.id = ?");
            $stmt_d->execute([$did]);
            $d_data = $stmt_d->fetch();
            
            if ($d_data && $qty > 0) {
                // Check if this design is already in the order to avoid duplicate rows
                $stmt_check = $pdo->prepare("SELECT id FROM store_order_items WHERE order_id = ? AND design_id = ?");
                $stmt_check->execute([$order_id, $did]);
                $existing_item = $stmt_check->fetchColumn();
                
                if ($existing_item) {
                     $pdo->prepare("UPDATE store_order_items SET quantity = quantity + ? WHERE id = ?")->execute([$qty, $existing_item]);
                } else {
                     $pdo->prepare("INSERT INTO store_order_items (order_id, product_id, design_id, quantity, price_per_item) VALUES (?, ?, ?, ?, ?)")->execute([$order_id, $d_data['product_id'], $did, $qty, $d_data['selling_price']]);
                }
            }
        }
        
        // 3. Recalculate total amount for consistency (mostly UI reference since actual B2B is based on weights)
        $stmt_calc = $pdo->prepare("SELECT SUM(quantity * price_per_item) FROM store_order_items WHERE order_id = ?");
        $stmt_calc->execute([$order_id]);
        $new_total = (float)$stmt_calc->fetchColumn();
        
        $pdo->prepare("UPDATE store_orders SET total_amount = ? WHERE id = ?")->execute([$new_total, $order_id]);
        
        $pdo->commit();
        $success_msg = "Order #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . " successfully adjusted!";
        
        // Reload order state
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Error updating order: " . $e->getMessage();
    }
}

// Fetch Items
$stmt_items = $pdo->prepare("
    SELECT oi.*, d.sku, d.design_name, d.stock_qty as current_stock, p.name as product_name 
    FROM store_order_items oi 
    JOIN product_designs d ON oi.design_id = d.id 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt_items->execute([$order_id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$all_designs = $pdo->query("SELECT d.id, d.sku, d.design_name, p.name as product_name, d.stock_qty FROM product_designs d JOIN products p ON d.product_id = p.id ORDER BY p.name ASC, d.sku ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-md-8 mx-auto">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0" style="font-family: 'Playfair Display', serif;">Customize Order #<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></h3>
            <a href="orders.php" class="btn btn-outline-dark rounded-pill shadow-sm"><i class="bi bi-arrow-left me-2"></i>Back to Orders</a>
        </div>
        
        <?php if($success_msg): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4"><i class="bi bi-check-circle-fill me-2"></i><?= e($success_msg) ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error_msg) ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
                <h5 class="fw-bold mb-0">Order Line Items</h5>
                <small class="text-muted">Set quantity to 0 to entirely remove a design from the order.</small>
            </div>
            
            <form method="POST">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4 py-3 border-0">Product & Design</th>
                                    <th class="py-3 border-0">SKU</th>
                                    <th class="py-3 border-0 text-center">In Stock</th>
                                    <th class="pe-4 py-3 border-0 text-center" style="width: 150px;">Req. Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($items)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">This order currently has no items.</td></tr>
                                <?php endif; ?>
                                <?php foreach($items as $item): ?>
                                <tr>
                                    <td class="ps-4 fw-medium text-dark">
                                        <?= e($item['product_name']) ?> <span class="text-secondary small ms-1">(<?= e($item['design_name']) ?>)</span>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= e($item['sku']) ?></span></td>
                                    <td class="text-center">
                                        <span class="badge <?= $item['current_stock'] > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                            <?= $item['current_stock'] ?>
                                        </span>
                                    </td>
                                    <td class="pe-4 text-center">
                                        <input type="number" name="items[<?= $item['id'] ?>]" class="form-control form-control-sm text-center fw-bold shadow-sm" min="0" value="<?= $item['quantity'] ?>">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="card-footer bg-light border-top-0 d-flex justify-content-end p-3 px-4">
                    <button type="submit" class="btn btn-dark rounded-pill fw-bold px-4 hover-lift shadow-sm">Save Quantities</button>
                </div>
            </form>
        </div>
        
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
             <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                <h5 class="fw-bold mb-0"><i class="bi bi-plus-circle-fill text-success me-2"></i>Add New Product</h5>
            </div>
             <div class="card-body p-4">
                <form method="POST" class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label small text-muted fw-bold">Search & Select Design</label>
                        <select name="new_design_id" class="form-select border-0 bg-light shadow-sm">
                            <option value="">-- Select Design to Add --</option>
                            <?php foreach($all_designs as $ad): ?>
                                <?php $disp = "{$ad['product_name']} - {$ad['design_name']} (SKU: {$ad['sku']}) [Stock: {$ad['stock_qty']}]"; ?>
                                <option value="<?= $ad['id'] ?>"><?= e($disp) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted fw-bold">Qty</label>
                        <input type="number" name="new_qty" class="form-control border-0 bg-light shadow-sm text-center" min="1" value="1">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm">Add Item</button>
                    </div>
                </form>
             </div>
        </div>

    </div>
</div>

<?php require_once "templates/footer.php"; ?>
