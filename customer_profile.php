<?php
require_once "config.php";
require_once "functions.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['customer_id'])) {
    header("Location: index.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];

// Fetch customer data
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    // Failsafe in case session persists after deletion
    unset($_SESSION['customer_id'], $_SESSION['customer_username']);
    header("Location: index.php");
    exit;
}

// Handle Profile Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $shop_name       = trim($_POST['shop_name'] ?? '');
    $name            = trim($_POST['name'] ?? '');
    $address_street  = trim($_POST['address_street'] ?? '');
    $address_city    = trim($_POST['address_city'] ?? '');
    $address_state   = trim($_POST['address_state'] ?? '');
    $address_pincode = trim($_POST['address_pincode'] ?? '');
    $gst_no          = trim($_POST['gst_no'] ?? '');
    
    if($shop_name && $name && $address_street && $address_city && $address_state && $address_pincode) {
        $upd = $pdo->prepare("UPDATE customers SET shop_name=?, name=?, address_street=?, address_city=?, address_state=?, address_pincode=?, gst_no=? WHERE id=?");
        $upd->execute([$shop_name, $name, $address_street, $address_city, $address_state, $address_pincode, $gst_no ?: null, $customer_id]);
        $success_msg = "Profile updated successfully.";
        // Refresh customer data
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $error_msg = "Please fill in all required fields.";
    }
}

// Fetch order history utilizing mobile_no mapping
$stmt_orders = $pdo->prepare("
    SELECT o.id, o.created_at, o.status, o.total_amount, o.shipping_address, i.id as invoice_id
    FROM store_orders o
    LEFT JOIN b2b_invoices i ON o.id = i.order_id
    WHERE o.customer_phone = ?
    ORDER BY o.created_at DESC
");
$stmt_orders->execute([$customer['mobile_no']]);
$orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

require_once "store_header.php";
?>

<div class="container py-3 py-md-5 my-2 my-md-4">
    <div class="row mb-4 mb-md-5">
        <div class="col-12 text-center">
            <h2 class="fw-bold" style="font-family: 'Playfair Display', serif; font-size: clamp(1.5rem, 3vw, 2.5rem);">Welcome Back, <?= e($customer['name']) ?></h2>
            <p class="text-muted lead fs-6">Manage your account and view your order history below.</p>
            
            <?php if(isset($success_msg)): ?>
                <div class="alert alert-success d-inline-block rounded-pill shadow-sm"><i class="bi bi-check-circle me-1"></i> <?= e($success_msg) ?></div>
            <?php elseif(isset($error_msg)): ?>
                <div class="alert alert-danger d-inline-block rounded-pill shadow-sm"><i class="bi bi-exclamation-circle me-1"></i> <?= e($error_msg) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4 g-lg-5">
        
        <!-- Left Column: Account Details -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white sticky-top" style="top: 120px;">
                <div class="card-body p-4 p-md-5 text-center">
                    <div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-4 border shadow-sm" style="width: 100px; height: 100px;">
                        <i class="bi bi-person fs-1 text-secondary"></i>
                    </div>
                    <h4 class="fw-bold mb-1"><?= e($customer['username']) ?></h4>
                    <p class="text-muted small mb-4">Member since <?= date('M Y', strtotime($customer['created_at'])) ?></p>
                    
                    <hr class="text-secondary opacity-25 mb-4">
                    
                    <div class="text-start">
                        <div class="mb-3">
                            <span class="d-block small text-muted text-uppercase fw-bold" style="letter-spacing: 1px;">Shop Details</span>
                            <span class="fs-6 fw-medium text-dark d-block"><?= e($customer['shop_name']) ?></span>
                            <span class="text-muted small">Rep: <?= e($customer['name']) ?></span>
                        </div>
                        <div class="mb-3">
                            <span class="d-block small text-muted text-uppercase fw-bold" style="letter-spacing: 1px;">Registered Phone</span>
                            <span class="fs-6 fw-medium text-dark"><i class="bi bi-telephone-fill text-success me-2"></i> <?= e($customer['mobile_no']) ?></span>
                        </div>
                        <div class="mb-4">
                            <span class="d-block small text-muted text-uppercase fw-bold" style="letter-spacing: 1px;">Shipping Address</span>
                            <p class="fs-6 fw-medium text-dark mb-0">
                                <?= e($customer['address_street']) ?><br>
                                <?= e($customer['address_city']) ?>, <?= e($customer['address_state']) ?><br>
                                PIN: <?= e($customer['address_pincode']) ?>
                            </p>
                            <?php if($customer['gst_no']): ?>
                            <div class="mt-2 text-muted small"><i class="bi bi-receipt"></i> GST: <?= e($customer['gst_no']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <span class="d-block small text-muted text-uppercase fw-bold" style="letter-spacing: 1px;">Account Status</span>
                            <span class="badge bg-success-subtle text-success fs-6 rounded-pill px-3 py-2 fw-medium"><i class="bi bi-check-circle-fill me-1"></i> Active Premium</span>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <button class="btn btn-outline-dark rounded-pill px-4 btn-sm fw-medium shadow-sm hover-lift" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="bi bi-pencil-square me-1"></i> Edit Profile Address
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Profile Modal -->
            <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" class="modal-content rounded-4 shadow-lg border-0">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="modal-header bg-light border-0">
                            <h5 class="modal-title fw-bold">Update Profile</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4 pt-4">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-muted">Shop Name *</label>
                                    <input type="text" class="form-control" name="shop_name" value="<?= e($customer['shop_name']) ?>" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-muted">Full Name *</label>
                                    <input type="text" class="form-control" name="name" value="<?= e($customer['name']) ?>" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-muted">Street Address *</label>
                                    <input type="text" class="form-control" name="address_street" value="<?= e($customer['address_street']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">City *</label>
                                    <input type="text" class="form-control" name="address_city" value="<?= e($customer['address_city']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">State *</label>
                                    <input type="text" class="form-control" name="address_state" value="<?= e($customer['address_state']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">Pincode *</label>
                                    <input type="text" class="form-control" name="address_pincode" value="<?= e($customer['address_pincode']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted">GST No (Optional)</label>
                                    <input type="text" class="form-control" name="gst_no" value="<?= e($customer['gst_no']) ?>">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-0">
                            <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-dark rounded-pill px-4 shadow-sm hover-lift">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Order Ledger -->
        <div class="col-lg-8">
            <h4 class="fw-bold mb-4 border-bottom pb-3" style="font-family: 'Playfair Display', serif;">Order History</h4>
            
            <?php if (empty($orders)): ?>
                <div class="p-5 text-center bg-light rounded-4 border-0">
                    <i class="bi bi-bag-x display-4 text-muted mb-3"></i>
                    <h5 class="fw-bold">No orders found.</h5>
                    <p class="text-muted">You haven't placed any orders with us yet.</p>
                    <a href="shop.php" class="btn btn-dark rounded-pill px-4 hover-lift">Browse Catalog</a>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-4">
                    <?php foreach ($orders as $order): 
                        
                        // Parse status colored badges safely
                        $status_badge = '<span class="badge bg-secondary">Unknown</span>';
                        if ($order['status'] === 'Pending') {
                            $status_badge = '<span class="badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-hourglass-split me-1"></i> Pending</span>';
                        } elseif ($order['status'] === 'Completed' || $order['status'] === 'Fulfilled') {
                            $status_badge = '<span class="badge bg-success px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-check-lg me-1"></i> Completed</span>';
                        } elseif ($order['status'] === 'Cancelled') {
                            $status_badge = '<span class="badge bg-danger px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-x-circle me-1"></i> Cancelled</span>';
                        } elseif ($order['status'] === 'Returned') {
                            $status_badge = '<span class="badge bg-secondary px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-arrow-return-left me-1"></i> Returned</span>';
                        } elseif ($order['status'] === 'Partially Returned') {
                            $status_badge = '<span class="badge bg-info text-dark px-3 py-2 rounded-pill shadow-sm"><i class="bi bi-arrow-return-right me-1"></i> Partial Return</span>';
                        }
                    ?>
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden position-relative">
                            <!-- Left Border Accent -->
                            <div class="position-absolute start-0 top-0 bottom-0 bg-dark opacity-75" style="width: 5px;"></div>
                            
                            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4 px-md-5 d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="d-block small text-muted fw-bold text-uppercase" style="letter-spacing:1px;">Date Placed</span>
                                    <h6 class="mb-0 fw-bold"><?= date('F j, Y, g:i a', strtotime($order['created_at'])) ?></h6>
                                </div>
                                <div class="text-end">
                                    <span class="d-block small text-muted fw-bold text-uppercase" style="letter-spacing:1px;">Order No.</span>
                                    <h6 class="mb-0 fw-bold text-primary">#<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></h6>
                                </div>
                            </div>
                            
                            <div class="card-body p-4 p-md-5 pt-3">
                                <div class="row align-items-center mt-3">
                                    <div class="col-md-5 mb-3 mb-md-0 text-center text-md-start">
                                        <div class="d-flex align-items-center justify-content-center justify-content-md-start gap-2 mb-1">
                                            <i class="bi bi-geo-alt-fill text-muted"></i>
                                            <span class="small text-muted fw-bold text-uppercase">Shipping Address</span>
                                        </div>
                                        <p class="mb-0 fw-medium small text-dark mx-auto mx-md-0" style="max-width: 250px;">
                                            <?= nl2br(e($order['shipping_address'])) ?>
                                        </p>
                                    </div>
                                    <div class="col-md-3 mb-3 mb-md-0 text-center py-3 py-md-0" style="border-top: 1px dashed #eee; border-bottom: 1px dashed #eee;">
                                        <span class="small text-muted fw-bold text-uppercase d-block mb-1">Total</span>
                                        <h5 class="mb-0 fw-bold text-dark">
                                            <?= ($order['total_amount'] > 0) ? '₹' . number_format($order['total_amount'], 2) : '<span class="fs-6 text-muted">To be discussed</span>' ?>
                                        </h5>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <span class="small text-muted fw-bold text-uppercase d-block mb-2">Status</span>
                                        <?= $status_badge ?>
                                    </div>
                                </div>
                                
                                <div class="mt-4 pt-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                                    <a class="btn btn-outline-dark btn-sm rounded-pill fw-medium px-4 w-100 w-md-auto hover-lift" data-bs-toggle="collapse" href="#orderDetails<?= $order['id'] ?>" role="button">
                                        View Item Breakdown <i class="bi bi-chevron-down ms-1"></i>
                                    </a>
                                    <?php if ($order['invoice_id']): ?>
                                    <a href="invoice_print.php?order_id=<?= $order['id'] ?>" target="_blank" class="btn btn-dark btn-sm rounded-pill fw-bold px-4 w-100 w-md-auto shadow-sm hover-lift">
                                        <i class="bi bi-file-earmark-pdf me-1"></i> Download Invoice
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Expandable Item Breakdown -->
                            <div class="collapse bg-light border-top" id="orderDetails<?= $order['id'] ?>">
                                <div class="card-body p-4 p-md-5">
                                    <h6 class="fw-bold mb-3 small text-uppercase text-muted" style="letter-spacing:1px;">Cart Contents</h6>
                                    
                                    <?php
                                        // Fetch order items efficiently right here
                                        $stmt_items = $pdo->prepare("
                                            SELECT oi.*, p.name as product_name, d.design_name as design_name, d.image_path 'design_image'
                                            FROM store_order_items oi
                                            JOIN products p ON oi.product_id = p.id
                                            LEFT JOIN product_designs d ON oi.design_id = d.id
                                            WHERE oi.order_id = ?
                                        ");
                                        $stmt_items->execute([$order['id']]);
                                        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    
                                    <ul class="list-group list-group-flush rounded-3 border">
                                        <?php foreach ($items as $item): ?>
                                            <li class="list-group-item bg-white p-3 d-flex justify-content-between align-items-center gap-3">
                                                <div class="d-flex align-items-center gap-3">
                                                    <?php if($item['design_image']): ?>
                                                        <img src="<?= e($item['design_image']) ?>" class="rounded shadow-sm object-fit-cover" style="width: 50px; height: 50px;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted border" style="width: 50px; height: 50px;"><i class="bi bi-image"></i></div>
                                                    <?php endif; ?>
                                                    
                                                    <div>
                                                        <h6 class="mb-0 fw-bold"><?= e($item['design_name'] ?? 'Unknown Design') ?></h6>
                                                        <small class="text-muted d-block"><?= e($item['product_name']) ?></small>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-dark rounded-pill px-2 py-1">Qty: <?= $item['quantity'] ?></span>
                                                    <div class="small fw-medium mt-1">
                                                        <?= ($item['price_per_item'] > 0) ? '₹' . number_format($item['price_per_item'], 2) : 'TBD' ?>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once "store_footer.php"; ?>
