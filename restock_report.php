<?php
require_once "auth_check.php";
require_once "role_management.php";

require_permission($pdo, 'products', 'view');

$page_title = "Low Stock / Restock Report";

// Fetch parent batches that are below or equal to their min_stock_level
$stmt = $pdo->query("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.total_stock_qty <= p.min_stock_level 
      AND p.min_stock_level > 0
    ORDER BY p.name ASC
");
$lowProducts = $stmt->fetchAll();

// Fetch designs for ALL low-stock products to avoid multiple queries
$designsByProd = [];
if (!empty($lowProducts)) {
    $ids = array_column($lowProducts, 'id');
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmtD = $pdo->prepare("SELECT * FROM product_designs WHERE product_id IN ($placeholders) ORDER BY product_id, design_name");
    $stmtD->execute($ids);
    $allDesigns = $stmtD->fetchAll();
    foreach ($allDesigns as $d) {
        $designsByProd[$d['product_id']][] = $d;
    }
}

require "templates/header.php";
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 d-print-none">
  <div>
      <h5 class="mb-0 text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> Low Stock / Restock Report</h5>
      <span class="text-secondary small">Batches that have dropped below their designated minimum stock limit.</span>
  </div>
  <button onclick="window.print()" class="btn btn-primary shadow-sm px-4">
    <i class="bi bi-printer me-2"></i> Print Report
  </button>
</div>

<!-- Print-only header -->
<div class="d-none d-print-block mb-4 border-bottom pb-3 text-center">
    <h3>Silvee Inventory - Restock Action Report</h3>
    <p class="mb-0 text-secondary">Generated on: <?= date('F j, Y, g:i a') ?></p>
</div>

<?php if (empty($lowProducts)): ?>
<div class="card shadow-sm border-0 bg-light p-5 text-center mt-4">
    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
    <h4 class="mt-3">Inventory is Healthy!</h4>
    <p class="text-secondary">No product batches are currently below their minimum stock thresholds.</p>
</div>
<?php else: ?>
    <?php foreach ($lowProducts as $p): ?>
        <?php 
            $pid = (int)$p['id'];
            $pDesigns = $designsByProd[$pid] ?? [];
            $shortage = $p['min_stock_level'] - $p['total_stock_qty'];
        ?>
        <div class="card shadow-sm border-0 mb-4 border-start border-danger border-4">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 text-danger fw-bold"><?= e($p['name']) ?></h5>
                        <div class="small mt-1 text-secondary">
                            <strong>SKU:</strong> <?= e($p['sku'] ?: 'N/A') ?> 
                            <span class="mx-2">|</span> 
                            <strong>Category:</strong> <?= e($p['category_name'] ?: 'Uncategorized') ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-5 fw-bold text-danger"><?= (int)$p['total_stock_qty'] ?> / <?= (int)$p['min_stock_level'] ?> in stock</div>
                        <span class="badge bg-danger mt-1">Shortage: <?= $shortage ?> pcs</span>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;" class="text-center d-print-none">Img</th>
                            <th>Variant Info</th>
                            <th class="text-end pe-4">Current Qty</th>
                            <th class="d-none d-print-table-cell" style="width: 150px;">Order Qty Form ________</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pDesigns as $d): ?>
                        <tr>
                            <td class="text-center align-middle d-print-none">
                                <?php if (!empty($d['image_path'])): 
                                    $img_src = explode(',', $d['image_path'])[0];
                                ?>
                                    <img src="<?= e($img_src) ?>" style="width:30px;height:30px;object-fit:cover;border-radius:4px;" class="shadow-sm">
                                <?php else: ?>
                                    <div style="width:30px;height:30px;background:#eee;border-radius:4px;font-size: 0.6rem;" class="mx-auto d-flex align-items-center justify-content-center text-muted border">-</div>
                                <?php endif; ?>
                            </td>
                            <td class="align-middle">
                                <div class="fw-medium text-wrap mb-1" style="max-width: 180px;"><?= e($d['design_name']) ?></div>
                                <div class="small text-muted"><span class="badge bg-light border text-secondary px-1 py-0 shadow-sm" style="font-size: 0.65rem;">SKU: <?= e($d['sku'] ?: '-') ?></span></div>
                            </td>
                            <td class="align-middle text-end pe-4 fw-bold fs-6 <?= $d['stock_qty'] == 0 ? 'text-danger' : 'text-dark' ?>">
                                <?= (int)$d['stock_qty'] ?>
                            </td>
                            <td class="d-none d-print-table-cell"></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($pDesigns)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No design variants mapped. General batch stock is low.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light text-end py-2 d-print-none">
                <a href="product_form.php?id=<?= $pid ?>" class="btn btn-sm btn-outline-secondary">Edit Batch Alert Level</a>
                <a href="stock_movements.php" class="btn btn-sm btn-primary ms-2"><i class="bi bi-box-arrow-in-down-right"></i> Add Resupplied Stock</a>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<style>
/* Print Styles */
@media print {
    body * { visibility: hidden; }
    .sidebar, .navbar, .btn, .d-print-none { display: none !important; }
    main { padding: 0 !important; margin: 0 !important; }
    .card { border: none !important; box-shadow: none !important; margin-bottom: 2rem !important; page-break-inside: avoid; }
    .card-header { border-bottom: 2px solid #ccc !important; padding-left: 0 !important; }
    .card-body { padding: 0 !important; }
    .card-footer { display: none !important; }
    table { width: 100% !important; margin-bottom: 0 !important; }
    th, td { border-bottom: 1px solid #ddd !important; padding: 0.5rem 0 !important; }
    th { color: #555 !important; }
    
    #restock-report-area, #restock-report-area * {
        visibility: visible;
    }
    #restock-report-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    
    /* Reveal print-only elements */
    .d-none.d-print-block { display: block !important; visibility: visible; }
    .d-none.d-print-table-cell { display: table-cell !important; visibility: visible; border-bottom: 1px solid #333 !important; }
}
</style>

<!-- Wrap everything inside a container for clean printing visibility -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Wrap page content to ensure cleaner printing
    const mainWrap = document.querySelector('main');
    if(mainWrap) {
        mainWrap.id = 'restock-report-area';
    }
});
</script>

<?php require "templates/footer.php"; ?>
