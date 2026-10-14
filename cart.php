<?php
require_once "config.php";
require_once "functions.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if(!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$isAjax = !empty($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if (!isset($_SESSION['customer_id'])) {
    if ($isAjax) {
        if(ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please sign in or register to add items to your bag.']);
        exit;
    }
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $did = (int)($_POST['design_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);
    
    if ($action === 'add' && $did > 0 && $qty > 0) {
        if(isset($_SESSION['cart'][$did])) {
            $_SESSION['cart'][$did]['qty'] += $qty;
        } else {
            $_SESSION['cart'][$did] = ['id' => $did, 'qty' => $qty];
        }
        sync_customer_cart($pdo);
        
        if ($isAjax) {
            $cart_count = array_sum(array_column($_SESSION['cart'], 'qty'));
            
            // Calculate total weight of cart
            $cart_total_weight = 0.000;
            if (!empty($_SESSION['cart'])) {
                $cart_ids = array_keys($_SESSION['cart']);
                $cart_placeholders = str_repeat('?,', count($cart_ids) - 1) . '?';
                $stmt_w = $pdo->prepare("
                    SELECT d.id, p.per_piece_weight 
                    FROM product_designs d 
                    JOIN products p ON d.product_id = p.id 
                    WHERE d.id IN ($cart_placeholders)
                ");
                $stmt_w->execute($cart_ids);
                while ($w_row = $stmt_w->fetch()) {
                    $cart_total_weight += $_SESSION['cart'][$w_row['id']]['qty'] * $w_row['per_piece_weight'];
                }
            }
            
            if(ob_get_length()) ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Item added to cart', 
                'cart_count' => $cart_count,
                'cart_total_weight' => number_format($cart_total_weight, 3)
            ]);
            exit;
        }
        header("Location: cart.php");
        exit;
    }
    
    if ($action === 'remove' && $did > 0) {
        unset($_SESSION['cart'][$did]);
        sync_customer_cart($pdo);
        if ($isAjax) {
            if(ob_get_length()) ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Item removed']);
            exit;
        }
        header("Location: cart.php");
        exit;
    }
    
    if ($action === 'update' && $did > 0 && $qty > 0) {
        if(isset($_SESSION['cart'][$did])) {
            $_SESSION['cart'][$did]['qty'] = $qty;
        }
        sync_customer_cart($pdo);
        if ($isAjax) {
            if(ob_get_length()) ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Cart updated']);
            exit;
        }
        header("Location: cart.php");
        exit;
    }
}

// Failsafe for AJAX requests: if we reach here and it was AJAX, always return JSON error instead of HTML
if ($isAjax) {
    if(ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid parameters or action.']);
    exit;
}

// Fetch detailed cart info
$cart_items = [];
$total_amount = 0;

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
        $subtotal = $qty * $r['selling_price'];
        $total_amount += $subtotal;
        
        $r['cart_qty'] = $qty;
        $r['subtotal'] = $subtotal;
        $cart_items[] = $r;
    }
}

require_once "store_header.php";
?>

<div class="container py-3 py-md-5 my-2 my-md-4">
    <h2 class="mb-3 mb-md-4 fw-bold" style="font-family: 'Playfair Display', serif; font-size: clamp(1.4rem, 2.5vw, 2rem);">Your Shopping Bag</h2>
    
    <?php if(empty($cart_items)): ?>
    <div class="text-center py-5 shadow-sm rounded-4 bg-white border">
        <i class="bi bi-handbag mb-3 text-muted" style="font-size: 3.5rem;"></i>
        <h4>Your bag is currently empty.</h4>
        <a href="shop.php" class="btn btn-dark mt-3 px-4 py-2 rounded-pill hover-lift shadow-sm">Continue Shopping</a>
    </div>
    <?php else: ?>
    <div class="row g-4 g-lg-5">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body p-0">
                    <!-- Desktop Header -->
                    <div class="d-none d-md-flex bg-light text-muted small text-uppercase px-4 py-3 border-bottom" style="letter-spacing: 1px;">
                        <div style="flex: 2;" class="fw-medium">Item Details</div>
                        <div style="flex: 1;" class="text-center fw-medium">Quantity</div>
                        <div style="width: 100px;" class="text-end fw-medium">Item Total</div>
                        <div style="width: 40px;"></div>
                    </div>
                    
                    <?php foreach($cart_items as $item): ?>
                    <div class="d-flex flex-column flex-md-row align-items-md-center px-4 py-4 border-bottom position-relative">
                        <!-- Mobile Delete Button (Top Right) -->
                        <div class="position-absolute top-0 end-0 p-3 pt-3 d-md-none">
                            <form action="cart.php" method="POST">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="design_id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-link text-danger p-0 shadow-none"><i class="bi bi-x-circle fs-4"></i></button>
                            </form>
                        </div>
                        
                        <!-- Item Info -->
                        <div class="d-flex justify-content-start align-items-center gap-3 mb-3 mb-md-0" style="flex: 2;">
                            <?php if($item['image_path']): ?>
                                <img src="<?= e($item['image_path']) ?>" class="rounded-3 shadow-sm border" style="width: 80px; height: 80px; object-fit: contain;">
                            <?php else: ?>
                                <div class="bg-light border shadow-sm rounded-3 d-flex align-items-center justify-content-center text-muted" style="width: 80px; height: 80px;"><i class="bi bi-image"></i></div>
                            <?php endif; ?>
                            <div class="pe-4 pe-md-0">
                                <h6 class="mb-1 fw-semibold text-dark"><?= e($item['design_name']) ?></h6>
                                <div class="text-muted small" style="font-size: 0.80rem;"><?= e($item['product_name']) ?></div>
                                <div class="text-dark mt-1 fw-medium" style="font-size: 0.85rem;"><i class="bi bi-whatsapp text-success me-1"></i> Price via WhatsApp</div>
                            </div>
                        </div>
                        
                        <!-- Quantity -->
                        <div class="d-flex justify-content-between align-items-center justify-content-md-center mb-2 mb-md-0" style="flex: 1;">
                            <span class="d-md-none small fw-bold text-muted text-uppercase">Quantity: </span>
                            <div>
                                <form action="cart.php" method="POST" class="d-inline-flex align-items-center border rounded-pill px-2 py-1 bg-white">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="design_id" value="<?= $item['id'] ?>">
                                    <button type="button" class="btn btn-link text-dark text-decoration-none p-0 px-2 fw-bold" onclick="let inp = this.nextElementSibling; let val = parseInt(inp.value); if(val > 1) { inp.value = val - 1; this.form.submit(); }">&minus;</button>
                                    <input type="number" name="qty" value="<?= $item['cart_qty'] ?>" min="1" max="<?= $item['stock_qty'] ?>" class="form-control form-control-sm border-0 shadow-none text-center bg-transparent fw-medium" style="width: 45px; pointer-events: none;" readonly>
                                    <button type="button" class="btn btn-link text-dark text-decoration-none p-0 px-2 fw-bold" onclick="let inp = this.previousElementSibling; let val = parseInt(inp.value); let max = parseInt(inp.getAttribute('max')); if(val < max) { inp.value = val + 1; this.form.submit(); } else { alert('Maximum available quantity is ' + max); }">&plus;</button>
                                </form>
                                <?php if($item['cart_qty'] > $item['stock_qty']): ?>
                                    <div class="text-danger small mt-1 fw-medium text-center"><i class="bi bi-exclamation-circle"></i> Max: <?= $item['stock_qty'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Item Total -->
                        <div class="d-flex justify-content-between align-items-center justify-content-md-end" style="width: 100px;">
                            <span class="d-md-none small fw-bold text-muted text-uppercase">Total: </span>
                            <span class="text-muted fst-italic small">TBD</span>
                        </div>
                        
                        <!-- Desktop Delete -->
                        <div class="d-none d-md-block text-end" style="width: 40px;">
                            <form action="cart.php" method="POST">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="design_id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-link text-danger p-0 hover-lift"><i class="bi bi-backspace fs-5"></i></button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="mt-4 pt-4 border-top">
                <a href="shop.php" class="text-decoration-none text-dark fw-medium btn btn-light rounded-pill px-4 shadow-sm"><i class="bi bi-arrow-left me-2"></i>Continue Shopping</a>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card border-0 shadow rounded-4 bg-dark text-white p-4 sticky-top" style="top: 100px;">
                <h4 class="fw-bold border-bottom border-light pb-3 mb-4" style="font-family: 'Playfair Display', serif;">Inquiry Status</h4>
                <div class="d-flex justify-content-between mb-3 text-white-50">
                    <span>Pricing</span>
                    <span>To be discussed</span>
                </div>
                <div class="d-flex justify-content-between mb-3 text-white-50">
                    <span>Shipping & Handling</span>
                    <span>Calculated at checkout</span>
                </div>
                <div class="d-flex justify-content-between mb-4 border-top border-secondary pt-4 mt-4">
                    <span class="fs-5 fw-medium text-white">Final Quote</span>
                    <span class="fs-6 fw-bold text-success"><i class="bi bi-whatsapp me-1"></i> Via WhatsApp</span>
                </div>
                
                <?php 
                $can_checkout = true;
                foreach($cart_items as $item) {
                    if($item['cart_qty'] > $item['stock_qty']) $can_checkout = false;
                }
                ?>
                
                <?php if($can_checkout): ?>
                <a href="checkout.php" class="btn btn-light w-100 py-3 rounded-pill fw-bold text-uppercase hover-lift shadow" style="letter-spacing: 1px;">Checkout Securely</a>
                <?php else: ?>
                <button class="btn btn-secondary w-100 py-3 rounded-pill fw-bold" disabled>Fix QTY to Checkout</button>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                   <div class="text-white-50 small mb-2"><i class="bi bi-shield-lock-fill me-1"></i> SSL Encrypted Checkout</div>
                   <div class="d-flex justify-content-center gap-2 fs-4 text-white-50 opacity-75">
                        <i class="bi bi-credit-card"></i>
                        <i class="bi bi-paypal"></i>
                        <i class="bi bi-apple"></i>
                   </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once "store_footer.php"; ?>
