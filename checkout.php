<?php
require_once "config.php";
require_once "functions.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Handle empty cart redirect, unless success flag is shown (checked post-submission)
// We will check inside the logic if empty.

if(empty($_SESSION['cart']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: shop.php");
    exit;
}

$cart_items = [];
$total_amount = 0;
$can_checkout = true;

if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT d.*, p.name as product_name, p.selling_price 
        FROM product_designs d
        JOIN products p ON d.product_id = p.id
        WHERE d.id IN ($placeholders)
    ");
    $stmt->execute($ids);
    $results = $stmt->fetchAll();

    foreach($results as $r) {
        $qty = $_SESSION['cart'][$r['id']]['qty'];
        if($qty > $r['stock_qty']) {
            $can_checkout = false;
        }
        $r['cart_qty'] = $qty;
        $r['subtotal'] = $qty * $r['selling_price'];
        $total_amount += $r['subtotal'];
        $cart_items[] = $r;
    }
}

$c_profile = [];
if (isset($_SESSION['customer_id'])) {
    $stmt_prof = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt_prof->execute([$_SESSION['customer_id']]);
    $c_profile = $stmt_prof->fetch(PDO::FETCH_ASSOC) ?: [];
}

$def_name   = !empty($_POST['customer_name'])   ? $_POST['customer_name']   : ($c_profile['name']           ?? '');
$def_phone  = !empty($_POST['customer_phone'])  ? $_POST['customer_phone']  : ($c_profile['mobile_no']      ?? '');
$def_email  = !empty($_POST['customer_email'])  ? $_POST['customer_email']  : ($c_profile['email']          ?? '');
$def_street = !empty($_POST['address_street'])  ? $_POST['address_street']  : ($c_profile['address_street'] ?? '');
$def_city   = !empty($_POST['address_city'])    ? $_POST['address_city']    : ($c_profile['address_city']   ?? '');
$def_state  = !empty($_POST['address_state'])   ? $_POST['address_state']   : ($c_profile['address_state']  ?? '');
$def_pin    = !empty($_POST['address_pincode']) ? $_POST['address_pincode'] : ($c_profile['address_pincode']?? '');

if(!$can_checkout && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cart.php");
    exit;
}

$success = false;
$order_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(empty($cart_items)) {
        header("Location: shop.php");
        exit;
    }

    $name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    
    $street  = trim($_POST['address_street'] ?? '');
    $city    = trim($_POST['address_city'] ?? '');
    $state   = trim($_POST['address_state'] ?? '');
    $pincode = trim($_POST['address_pincode'] ?? '');
    $notes   = trim($_POST['order_notes'] ?? '');
    
    $address_parts = [];
    if($street)  $address_parts[] = $street;
    if($city)    $address_parts[] = $city;
    if($state)   $address_parts[] = $state;
    if($pincode) $address_parts[] = "PIN: " . $pincode;
    $address = implode(", ", $address_parts);
    
    if($name && $phone && $street && $city && $state && $pincode) {
        if ($can_checkout) {
            // Check if POS customer
            $is_pos_sale = false;
            $pos_salesperson = 'Web POS';
            if(isset($_SESSION['customer_id'])) {
                $c_stmt = $pdo->prepare("SELECT username FROM customers WHERE id = ?");
                $c_stmt->execute([$_SESSION['customer_id']]);
                $c_name = $c_stmt->fetchColumn();
                if($c_name && stripos($c_name, 'silvee') !== false) {
                    $is_pos_sale = true;
                    $pos_salesperson = $c_name;
                }
            }

            $pdo->beginTransaction();
            try {
                if ($is_pos_sale) {
                    // --- POS SALE LOGIC ---
                    $sale_date = date('Y-m-d');
                    $sale_note = "Processed via Web Store by: " . $pos_salesperson . ($notes ? " | Notes: " . $notes : "");
                    
                    // 1. Insert into sales
                    $stmt = $pdo->prepare("INSERT INTO sales (sale_date, buyer_name, city, note) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$sale_date, $name, $address, $sale_note]);
                    $sale_id = (int)$pdo->lastInsertId();

                    // 2. Insert into store_orders as Offline/Completed
                    $stmt_so = $pdo->prepare("INSERT INTO store_orders (customer_name, customer_phone, customer_email, shipping_address, order_notes, total_amount, status, order_type, salesperson_name) VALUES (?, ?, ?, ?, ?, ?, 'Completed', 'Offline', ?)");
                    $stmt_so->execute([$name, $phone, $email, $address, 'Physical POS Sale #'.$sale_id.' - ' . $sale_note, $total_amount, $pos_salesperson]);
                    $order_id = (int)$pdo->lastInsertId();

                    // 3. Process items and deduct stock
                    $stmt_si = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, design_id, quantity, total_weight, selling_price) VALUES (?,?,?,?,?,?)");
                    $stmt_soi = $pdo->prepare("INSERT INTO store_order_items (order_id, product_id, design_id, quantity, price_per_item) VALUES (?,?,?,?,?)");
                    $stmt_sm = $pdo->prepare("INSERT INTO stock_movements (product_id, design_id, movement_type, quantity, movement_date, vendor_name, total_weight, note) VALUES (?, ?, 'OUT', ?, ?, ?, ?, ?)");
                    $stmt_deduct_design = $pdo->prepare("UPDATE product_designs SET stock_qty = stock_qty - ? WHERE id = ?");
                    $stmt_deduct_prod = $pdo->prepare("UPDATE products SET total_stock_qty = total_stock_qty - ?, bulk_total_weight = bulk_total_weight - ? WHERE id = ?");

                    foreach($cart_items as $item) {
                        $qty = $item['cart_qty'];
                        // Calculate weight to deduct using derived unit weight
                        $stmt_w = $pdo->prepare("SELECT per_piece_weight FROM products WHERE id = ?");
                        $stmt_w->execute([$item['product_id']]);
                        $unit_weight = (float)$stmt_w->fetchColumn();
                        $weightToDeduct = $qty * $unit_weight;

                        $stmt_si->execute([$sale_id, $item['product_id'], $item['id'], $qty, $weightToDeduct, $item['selling_price']]);
                        $stmt_soi->execute([$order_id, $item['product_id'], $item['id'], $qty, $item['selling_price']]);
                        
                        $stmt_sm->execute([
                            $item['product_id'],
                            $item['id'],
                            $qty,
                            $sale_date,
                            $pos_salesperson,
                            $weightToDeduct,
                            "Web POS Sale #{$sale_id} to {$name}"
                        ]);
                        
                        $stmt_deduct_design->execute([$qty, $item['id']]);
                        $stmt_deduct_prod->execute([$qty, $weightToDeduct, $item['product_id']]);
                    }
                } else {
                    // --- NORMAL ONLINE PENDING LOGIC ---
                    $stmt = $pdo->prepare("INSERT INTO store_orders (customer_name, customer_phone, customer_email, shipping_address, order_notes, total_amount, status) VALUES (?,?,?,?,?,?,'Pending')");
                    $stmt->execute([$name, $phone, $email, $address, $notes, $total_amount]);
                    $order_id = $pdo->lastInsertId();
                    
                    $stmt_item = $pdo->prepare("INSERT INTO store_order_items (order_id, product_id, design_id, quantity, price_per_item) VALUES (?,?,?,?,?)");
                    foreach($cart_items as $item) {
                        $stmt_item->execute([$order_id, $item['product_id'], $item['id'], $item['cart_qty'], $item['selling_price']]);
                    }
                }
                
                $pdo->commit();
                $success = true;
                $_SESSION['cart'] = []; // Clear cart!
                
                // Clear Live Tracking DB
                if (isset($_SESSION['customer_id'])) {
                    $clear_cart = $pdo->prepare("DELETE FROM customer_carts WHERE customer_id = ?");
                    $clear_cart->execute([$_SESSION['customer_id']]);
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Order processing failed: " . $e->getMessage();
            }
        } else {
            $error = "Stock issue occurred. Please check your cart quantities.";
        }
    } else {
        $error = "Please fill in all required shipping fields.";
    }
}

require_once "store_header.php";
?>

<div class="container py-3 py-md-5 my-2 my-md-4">
    <?php if($success): ?>
    <div class="row justify-content-center text-center">
        <div class="col-md-8 col-lg-6 py-4 py-md-5 mt-2 mt-md-4">
            <i class="bi bi-cart-check-fill text-dark display-1 mb-4"></i>
            <h1 class="fw-bold mb-3" style="font-family: 'Playfair Display', serif; font-size: clamp(1.4rem, 2.5vw, 2.2rem);">Thank you for your order!</h1>
            <?php if(isset($is_pos_sale) && $is_pos_sale): ?>
            <p class="lead text-success mb-4 fw-medium"><i class="bi bi-check-circle me-1"></i> Offline POS Sale #<strong><?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></strong> successfully registered! Stock has been instantly deducted.</p>
            <?php else: ?>
            <p class="lead text-muted mb-4">Your order #<strong><?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?></strong> has been placed and is currently pending fulfillment.</p>
            <?php endif; ?>
            <div class="bg-light p-3 p-md-4 rounded-4 text-start shadow-sm mb-4 mb-md-5">
                <h6 class="fw-bold mb-3">Order Summary sent to store:</h6>
                <div class="d-flex justify-content-between small text-muted mb-2"><span>Deliver to:</span> <strong class="text-dark"><?= e($_POST['customer_name']) ?></strong></div>
                <div class="d-flex justify-content-between small text-muted mb-2"><span>Contact:</span> <strong class="text-dark"><?= e($_POST['customer_phone']) ?></strong></div>
                <div class="d-flex justify-content-between small text-muted mb-0"><span>Pricing Status:</span> <strong class="text-success"><i class="bi bi-whatsapp me-1"></i> To be discussed</strong></div>
            </div>
            <a href="shop.php" class="btn btn-dark rounded-pill px-4 px-md-5 py-2 py-md-3 fw-bold shadow-sm hover-lift" style="letter-spacing: 1px;">Continue Shopping</a>
        </div>
    </div>
    
    <?php else: ?>
    
    <h2 class="mb-3 mb-md-4 fw-bold" style="font-family: 'Playfair Display', serif; font-size: clamp(1.4rem, 2.5vw, 2rem);">Secure Checkout</h2>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-danger bg-danger-subtle border-0 rounded-4 mb-4 shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?></div>
    <?php endif; ?>
    
    <form method="POST" class="row g-4 g-lg-5">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-3 p-sm-4 p-md-5">
                    <h5 class="fw-bold mb-4">Shipping Information</h5>
                    <div class="row g-4">
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold text-uppercase" style="letter-spacing:1px;">Full Name *</label>
                            <input type="text" name="customer_name" class="form-control form-control-lg bg-light border-0 shadow-none px-4" required placeholder="Jane Doe" value="<?= e($def_name) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold text-uppercase" style="letter-spacing:1px;">Phone Number *</label>
                            <input type="text" name="customer_phone" class="form-control form-control-lg bg-light border-0 shadow-none px-4" required placeholder="+1 (234) 567-890" value="<?= e($def_phone) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold text-uppercase" style="letter-spacing:1px;">Email (Optional)</label>
                            <input type="email" name="customer_email" class="form-control form-control-lg bg-light border-0 shadow-none px-4" placeholder="jane@example.com" value="<?= e($def_email) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold text-uppercase" style="letter-spacing:1px;">Street Address *</label>
                            <input type="text" name="address_street" class="form-control form-control-lg bg-light border-0 shadow-none px-4" required placeholder="123 Luxury Ave, Apt 4B..." value="<?= e($def_street) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted fw-bold text-uppercase" style="letter-spacing:1px;">City *</label>
                            <input type="text" name="address_city" class="form-control form-control-lg bg-light border-0 shadow-none px-4" required placeholder="Mumbai" value="<?= e($def_city) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted fw-bold text-uppercase" style="letter-spacing:1px;">State *</label>
                            <input type="text" name="address_state" class="form-control form-control-lg bg-light border-0 shadow-none px-4" required placeholder="Maharashtra" value="<?= e($def_state) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted fw-bold text-uppercase" style="letter-spacing:1px;">Pincode *</label>
                            <input type="text" name="address_pincode" class="form-control form-control-lg bg-light border-0 shadow-none px-4" required placeholder="400001" value="<?= e($def_pin) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold text-uppercase" style="letter-spacing:1px;">Order Notes (Optional)</label>
                            <textarea name="order_notes" class="form-control bg-light border-0 shadow-none px-4 py-3" rows="2" placeholder="Special delivery instructions..."><?= e($_POST['order_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold mb-4">Payment Details</h5>
                    <div class="border rounded-4 p-4 bg-light text-muted d-flex align-items-center gap-3">
                        <i class="bi bi-shield-lock-fill fs-2 text-dark"></i>
                        <div>
                            <strong class="text-dark d-block">Cash on Delivery / Direct Invoice</strong>
                            <span class="small">Payment will be collected transparently upon dispatch confirmation. No card required now.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white sticky-top" style="top: 100px;">
                <h5 class="fw-bold border-bottom pb-3 mb-4" style="font-family: 'Playfair Display', serif;">Order Summary</h5>
                
                <div class="mb-4 d-flex flex-column gap-3 overflow-auto p-2" style="max-height: 400px; scrollbar-width: thin; overflow-x: hidden;">
                    <?php foreach($cart_items as $item): ?>
                    <div class="d-flex gap-3 align-items-center">
                        <div class="position-relative mt-2 me-2">
                            <?php if($item['image_path']): ?>
                                <img src="<?= e($item['image_path']) ?>" class="rounded bg-light object-fit-contain shadow-sm" style="width: 70px; height: 70px; border: 1px solid #eee;">
                            <?php else: ?>
                                <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted shadow-sm" style="width: 70px; height: 70px; border: 1px solid #eee;"><i class="bi bi-image"></i></div>
                            <?php endif; ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-circle bg-dark shadow" style="width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 0.70rem;">
                                <?= $item['cart_qty'] ?>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-semibold fs-6 text-dark text-truncate" style="max-width: 180px;"><?= e($item['design_name']) ?></h6>
                            <small class="text-muted d-block"><?= e($item['product_name']) ?></small>
                        </div>
                        <div class="text-muted fst-italic text-end ms-2 small">
                            TBD
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="d-flex justify-content-between mb-2 text-secondary px-1">
                    <span>Pricing</span>
                    <span>To be discussed</span>
                </div>
                <div class="d-flex justify-content-between mb-4 text-secondary px-1">
                    <span>Shipping</span>
                    <span class="text-success fw-medium">Free / TBD</span>
                </div>
                <div class="d-flex justify-content-between pt-4 border-top border-dark-subtle mb-5 px-1">
                    <span class="fs-5 fw-bold text-dark">Sales Quote</span>
                    <span class="fs-5 fw-bold text-success"><i class="bi bi-whatsapp me-1"></i> WhatsApp</span>
                </div>
                
                <button type="submit" class="btn btn-dark w-100 py-3 rounded-pill fw-bold text-uppercase shadow hover-lift" style="letter-spacing: 1.5px;">Place Secure Order</button>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<?php require_once "store_footer.php"; ?>
