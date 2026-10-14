<?php
require_once "config.php";
require_once "functions.php";
require_login(); // Ensure staff/admin

// Query active carts joined with customer and design details
$stmt = $pdo->query("
    SELECT 
        c.id as customer_id, 
        c.username, 
        c.mobile_no,
        cc.quantity, 
        cc.updated_at,
        p.name as product_name,
        d.id as design_id,
        d.design_name as design_name,
        d.image_path 'design_image',
        d.stock_qty
    FROM customer_carts cc
    JOIN customers c ON cc.customer_id = c.id
    JOIN products p ON cc.product_id = p.id
    JOIN product_designs d ON cc.design_id = d.id
    ORDER BY c.id DESC, cc.updated_at DESC
");

$fetched_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$carts = [];
foreach ($fetched_data as $row) {
    if (!isset($carts[$row['customer_id']])) {
        $carts[$row['customer_id']] = [
            'username' => $row['username'],
            'mobile_no' => $row['mobile_no'],
            'last_active' => $row['updated_at'],
            'items' => []
        ];
    }
    // Track most recent activity globally for this user
    if (strtotime($row['updated_at']) > strtotime($carts[$row['customer_id']]['last_active'])) {
        $carts[$row['customer_id']]['last_active'] = $row['updated_at'];
    }
    
    $carts[$row['customer_id']]['items'][] = [
        'design_id' => $row['design_id'],
        'product_name' => $row['product_name'],
        'design_name' => $row['design_name'],
        'image' => $row['design_image'],
        'quantity' => $row['quantity'],
        'stock_qty' => $row['stock_qty']
    ];
}

require_once "templates/header.php";
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <h2 class="mb-0 fw-bold"><i class="bi bi-cart4 text-primary me-2"></i> Live Shopping Carts</h2>
    <div class="text-muted small">
        <i class="bi bi-broadcast me-1 text-success"></i> Automatically tracking unsaved checkouts
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
    <?php if(empty($carts)): ?>
        <div class="card-body p-5 text-center text-muted">
            <i class="bi bi-basket display-4 text-secondary opacity-50 mb-3 block"></i>
            <h5 class="fw-bold">No active shopping carts</h5>
            <p class="mb-0">There are currently no customers with active items in their bag.</p>
        </div>
    <?php else: ?>
        <div class="accordion accordion-flush" id="cartsAccordion">
            <?php foreach($carts as $cid => $cart): ?>
            <div class="accordion-item border-bottom">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed px-4 py-3 bg-light hover-lift" type="button" data-bs-toggle="collapse" data-bs-target="#cart-<?= $cid ?>">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center w-100 pe-3 gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <span class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width: 45px; height: 45px; font-size: 1.2rem; flex-shrink: 0;">
                                    <?= strtoupper(substr($cart['username'], 0, 1)) ?>
                                </span>
                                <div class="text-start">
                                    <h6 class="mb-0 fw-bold text-dark"><?= e($cart['username']) ?></h6>
                                    <small class="text-muted"><i class="bi bi-telephone-fill me-1"></i> <?= e($cart['mobile_no']) ?></small>
                                </div>
                            </div>
                            <div class="text-start text-md-end border-top border-md-top-0 pt-2 pt-md-0 mt-2 mt-md-0 border-md-start ps-md-4 w-100 w-md-auto">
                                <span class="badge bg-dark rounded-pill px-3 py-2 mb-1 shadow-sm"><i class="bi bi-bag me-1"></i> <?= count($cart['items']) ?> Unique Items</span>
                                <div class="small fw-medium text-secondary mt-1"><i class="bi bi-clock-history me-1"></i> Active <?= date('M j, g:i A', strtotime($cart['last_active'])) ?></div>
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="cart-<?= $cid ?>" class="accordion-collapse collapse" data-bs-parent="#cartsAccordion">
                    <div class="accordion-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                            <thead class="bg-white">
                                <tr>
                                    <th class="ps-4 fw-bold text-muted small py-3 border-bottom-0 w-50">Item Details</th>
                                    <th class="text-center fw-bold text-muted small py-3 border-bottom-0">Stock Remaining</th>
                                    <th class="text-end pe-4 fw-bold text-muted small py-3 border-bottom-0">Cart Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cart['items'] as $item): ?>
                                <tr>
                                    <td class="ps-4 py-3 border-light">
                                        <div class="d-flex align-items-center gap-3">
                                            <?php if($item['image']): ?>
                                                <img src="<?= e($item['image']) ?>" class="rounded shadow-sm object-fit-cover border" style="width: 55px; height: 55px;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center text-muted border shadow-sm" style="width: 55px; height: 55px;"><i class="bi bi-image"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-0 fw-bold border-bottom border-dark-subtle pb-1 d-inline-block"><?= e($item['design_name'] ?? 'Unknown Design') ?></h6>
                                                <small class="text-muted d-block mt-1"><?= e($item['product_name']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center py-3 border-light">
                                        <?php if($item['stock_qty'] <= 0): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1"><i class="bi bi-x-circle me-1"></i> Out of Stock</span>
                                        <?php elseif($item['stock_qty'] < $item['quantity']): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-1"><i class="bi bi-exclamation-triangle me-1"></i> Short (<?= $item['stock_qty'] ?> left)</span>
                                        <?php else: ?>
                                            <span class="text-success small fw-bold"><?= $item['stock_qty'] ?> Available</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4 py-3 border-light">
                                        <div class="d-inline-flex bg-light border rounded px-3 py-1 fw-bold fs-6">
                                            x<?= $item['quantity'] ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once "templates/footer.php"; ?>
