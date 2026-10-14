<?php
require_once "auth_check.php";
require_once "role_management.php";

$page_title = "Staff Performance Report";
$role = current_user_role($pdo);

// Default to current month
$start_date = $_GET['start_date'] ?? date('Y-m-01', strtotime('-6 months'));
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Aggregate Query
$stmt = $pdo->prepare("
    SELECT 
        vendor_name AS salesperson,
        COUNT(DISTINCT note) AS total_transactions,
        SUM(quantity) AS total_items_sold,
        SUM(total_weight) AS total_weight_sold
    FROM stock_movements
    WHERE movement_type = 'OUT' 
      AND vendor_name IS NOT NULL AND vendor_name != ''
      AND DATE(movement_date) BETWEEN ? AND ?
    GROUP BY vendor_name
    ORDER BY total_items_sold DESC
");
$stmt->execute([$start_date, $end_date]);
$performance = $stmt->fetchAll();

require "templates/header.php";
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h5 class="mb-0 text-dark"><i class="bi bi-person-lines-fill me-2"></i> Staff Performance</h5>
        <span class="text-secondary small">Track sales team metrics and outbound stock movement aggregated by employee.</span>
    </div>
</div>

<div class="card shadow-sm border-0 bg-white rounded-3 mb-4">
    <div class="card-body p-3 bg-light rounded-top border-bottom">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted text-uppercase">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= e($start_date) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted text-uppercase">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= e($end_date) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="bi bi-funnel"></i> Filter</button>
            </div>
        </form>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Sales Person</th>
                        <th>Performance Pulse</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $details_stmt = $pdo->prepare("
                        SELECT sm.*, p.name AS product_name, d.design_name, d.sku AS design_sku
                        FROM stock_movements sm
                        JOIN products p ON sm.product_id = p.id
                        LEFT JOIN product_designs d ON sm.design_id = d.id
                        WHERE sm.movement_type = 'OUT' 
                          AND sm.vendor_name = ?
                          AND DATE(sm.movement_date) BETWEEN ? AND ?
                        ORDER BY sm.movement_date DESC, sm.id DESC
                    ");
                    
                    foreach($performance as $idx => $p): 
                        $sp_name = $p['salesperson'];
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold text-dark align-middle">
                            <i class="bi bi-person-badge text-primary me-2"></i> <?= e($sp_name) ?>
                        </td>
                        <td class="align-middle py-3">
                            <div class="d-flex flex-wrap gap-2 my-1">
                                <span class="badge bg-light text-dark border shadow-sm"><i class="bi bi-receipt me-1"></i><?= (int)$p['total_transactions'] ?> Trans</span>
                                <span class="badge bg-success-subtle text-success border border-success-subtle shadow-sm"><i class="bi bi-box me-1"></i><?= (int)$p['total_items_sold'] ?> Pcs</span>
                                <?php if($p['total_weight_sold']): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle shadow-sm"><i class="bi bi-speedometer2 me-1"></i><?= number_format($p['total_weight_sold'], 3) ?> g</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-end pe-4 align-middle">
                            <button class="btn btn-sm btn-light border shadow-sm fw-medium text-nowrap" type="button" data-bs-toggle="collapse" data-bs-target="#spDet<?= $idx ?>">
                                <span class="d-none d-md-inline">Log </span><i class="bi bi-chevron-down ms-md-1 text-muted"></i>
                            </button>
                        </td>
                    </tr>
                    
                    <tr>
                        <td colspan="3" class="p-0 border-0">
                            <div class="collapse" id="spDet<?= $idx ?>">
                                <div class="p-4" style="background-color: #fcfcfc; border-bottom: 2px solid #eee;">
                                    <h6 class="fw-bold text-muted small text-uppercase mb-3" style="letter-spacing: 1px;">Itemized Outbound Log for <?= e($sp_name) ?></h6>
                                    <div class="bg-white rounded border shadow-sm p-0 overflow-hidden">
                                        <table class="table table-sm mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-3 py-2 text-muted fw-medium">Transaction Info</th>
                                                    <th class="py-2 text-muted fw-medium">Item Details</th>
                                                    <th class="text-end pe-3 py-2 text-muted fw-medium">Volume & Wt</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $details_stmt->execute([$sp_name, $start_date, $end_date]);
                                                $logs = $details_stmt->fetchAll();
                                                foreach($logs as $log):
                                                ?>
                                                <tr>
                                                    <td class="ps-3 py-3 align-middle">
                                                        <div class="small text-secondary fw-bold mb-1"><i class="bi bi-calendar3 me-1"></i><?= date('M j, Y', strtotime($log['movement_date'])) ?></div>
                                                        <div class="small text-muted fst-italic text-wrap lh-sm border-start border-2 ps-2 mt-2" style="max-width: 150px; font-size: 0.70rem;"><?= e($log['note']) ?></div>
                                                    </td>
                                                    <td class="py-3 align-middle">
                                                        <div class="fw-medium text-dark text-wrap lh-sm mb-1" style="font-size: 0.85rem; max-width: 180px;"><?= e($log['product_name']) ?></div>
                                                        <div class="small text-muted"><i class="bi bi-arrow-return-right me-1"></i><?= e($log['design_name'] ?: 'General') ?></div>
                                                    </td>
                                                    <td class="text-end pe-3 py-3 align-middle">
                                                        <div class="fw-bold text-success fs-6 mb-1">-<?= (int)$log['quantity'] ?> pcs</div>
                                                        <?php if($log['total_weight']): ?>
                                                            <div class="badge bg-light text-secondary border px-2 py-1 shadow-sm mt-1"><?= number_format($log['total_weight'], 3) ?>g</div>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php if(empty($logs)): ?>
                                                <tr><td colspan="3" class="text-center py-4 text-muted">No raw logs found.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($performance)): ?>
                    <tr>
                        <td colspan="3" class="text-center py-5">
                            <i class="bi bi-calendar-x display-4 text-muted opacity-25 d-block mb-3"></i>
                            <h5 class="text-secondary fw-medium">No sales records found for this period.</h5>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require "templates/footer.php"; ?>
