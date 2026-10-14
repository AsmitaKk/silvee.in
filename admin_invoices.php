<?php
$page_title = "B2B Invoices";
require_once "config.php";
require_once "functions.php";
require_once "templates/header.php";

if (!$is_admin) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Access Denied. Admins only.</div></div>";
    require_once "templates/footer.php";
    exit;
}

$start_date = $_GET['start_date'] ?? date('Y-m-01', strtotime('-6 months'));
$end_date = $_GET['end_date'] ?? date('Y-m-t');

$stmt = $pdo->prepare("
    SELECT i.*, 
           o.customer_name as order_customer_name,
           c.shop_name, c.name as customer_name 
    FROM b2b_invoices i
    LEFT JOIN store_orders o ON i.order_id = o.id
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE DATE(i.invoice_date) BETWEEN ? AND ?
    ORDER BY i.created_at DESC
");
$stmt->execute([$start_date, $end_date]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white border-bottom-0 pt-4 pb-3 px-4 d-flex flex-wrap flex-md-row justify-content-between align-items-md-center gap-3">
        <h5 class="fw-bold mb-0"><i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i> Generated B2B Invoices</h5>
        <!-- Desktop Layout Form -->
        <form method="GET" class="m-0 w-100 d-none d-md-block" style="max-width: 100%;">
            <div class="input-group shadow-sm justify-content-end">
                <span class="input-group-text bg-light text-secondary border-end-0 fw-medium small"><i class="bi bi-calendar-range me-1"></i></span>
                <input type="date" name="start_date" class="form-control form-control-sm border-start-0 flex-grow-0" value="<?= e($start_date) ?>" style="width: 130px;">
                <span class="input-group-text bg-light text-secondary border-start-0 border-end-0 px-2 fw-medium small">to</span>
                <input type="date" name="end_date" class="form-control form-control-sm border-start-0 flex-grow-0" value="<?= e($end_date) ?>" style="width: 130px;">
                <button type="submit" class="btn btn-sm btn-primary px-3"><i class="bi bi-funnel-fill"></i></button>
            </div>
        </form>
        
        <!-- Mobile Layout Form -->
        <form method="GET" class="m-0 w-100 d-block d-md-none" style="max-width: 100%;">
            <div class="d-flex flex-column gap-2 w-100">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1" style="font-size: 0.70rem;">Start Date</label>
                        <input type="date" name="start_date" class="form-control form-control-sm shadow-sm" value="<?= e($start_date) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1" style="font-size: 0.70rem;">End Date</label>
                        <input type="date" name="end_date" class="form-control form-control-sm shadow-sm" value="<?= e($end_date) ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-sm btn-primary w-100 shadow-sm mt-1"><i class="bi bi-funnel-fill me-1"></i> Filter Invoices</button>
            </div>
        </form>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted small text-uppercase" style="letter-spacing: 1px;">
                    <tr>
                        <th class="py-3 px-3 rounded-start border-0 fw-medium">Invoice Details</th>
                        <th class="py-3 px-3 border-0 fw-medium">Customer & Store</th>
                        <th class="py-3 px-3 rounded-end border-0 fw-medium text-end">Total & Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($invoices)): ?>
                    <tr><td colspan="3" class="text-center py-5 text-muted"><i class="bi bi-inbox display-4 d-block mb-3 opacity-25"></i>No records found for the selected dates.</td></tr>
                    <?php else: ?>
                        <?php foreach($invoices as $inv): ?>
                        <tr class="border-bottom">
                            <td class="px-3 align-middle py-3">
                                <span class="d-block fw-bold text-dark mb-1"><?= e($inv['invoice_number']) ?></span>
                                <span class="badge bg-light text-secondary border fw-normal mb-1"><i class="bi bi-calendar3 me-1"></i><?= date('M j, Y', strtotime($inv['invoice_date'])) ?></span>
                                <div class="mt-1"><a href="orders.php" class="text-decoration-none small text-primary"><i class="bi bi-link-45deg"></i> Order #<?= str_pad($inv['order_id'], 6, '0', STR_PAD_LEFT) ?></a></div>
                            </td>
                            <td class="px-3 align-middle py-3">
                                <?php 
                                $display_shop = !empty($inv['shop_name']) ? $inv['shop_name'] : (!empty($inv['customer_name']) ? $inv['customer_name'] : $inv['order_customer_name']);
                                $display_person = !empty($inv['customer_name']) ? $inv['customer_name'] : $inv['order_customer_name'];
                                ?>
                                <span class="d-block fw-bold text-dark text-wrap lh-sm mb-1" style="max-width: 200px;"><?= e($display_shop) ?></span>
                                <small class="text-muted"><i class="bi bi-person me-1"></i><?= e($display_person) ?></small>
                            </td>
                            <td class="px-3 text-end align-middle py-3 pe-4">
                                <div class="fw-bold text-success fs-5 mb-2">
                                    ₹<?= number_format($inv['grand_total'], 2) ?>
                                </div>
                                <a href="invoice_print.php?order_id=<?= $inv['order_id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill px-3 shadow-sm hover-lift text-nowrap">
                                    <i class="bi bi-printer"></i><span class="d-none d-md-inline ms-1">Print / PDF</span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once "templates/footer.php"; ?>
