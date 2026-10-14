<?php
require_once "auth_check.php";

$page_title = "Add Stock (IN) & History";

// Fetch designs for dropdown
$designs = $pdo->query("
    SELECT 
        d.id as design_id,
        d.product_id,
        d.design_name,
        d.sku as design_sku,
        d.stock_qty as design_stock,
        p.name as product_name,
        p.sku as product_sku
    FROM product_designs d
    JOIN products p ON d.product_id = p.id
    ORDER BY p.name, d.design_name
")->fetchAll();

$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $design_id     = (int)($_POST['design_id'] ?? 0);
    $quantity      = (int)($_POST['quantity'] ?? 0);
    $movement_date = $_POST['movement_date'] ?? '';
    $vendor_name   = trim($_POST['vendor_name'] ?? '');
    $total_weight  = trim($_POST['total_weight'] ?? '');

    if ($design_id <= 0) $errors[] = "Product Design is required.";
    if ($quantity <= 0) $errors[] = "Quantity must be greater than zero.";
    if ($movement_date === '') $movement_date = date('Y-m-d');

    $total_weight_val = 0.000;
    if ($total_weight !== '' && is_numeric($total_weight)) {
        $total_weight_val = (float)$total_weight;
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT product_id, stock_qty FROM product_designs WHERE id = ?");
            $stmt->execute([$design_id]);
            $designData = $stmt->fetch();

            if (!$designData) {
                throw new Exception("Design not found.");
            }

            $product_id = (int)$designData['product_id'];

            // Insert stock movement
            $stmt = $pdo->prepare("
                INSERT INTO stock_movements 
                    (product_id, design_id, movement_type, quantity, movement_date, vendor_name, total_weight, note)
                VALUES (?, ?, 'IN', ?, ?, ?, ?, '')
            ");
            $stmt->execute([
                $product_id,
                $design_id,
                $quantity,
                $movement_date,
                $vendor_name,
                $total_weight_val > 0 ? $total_weight_val : null
            ]);

            // Update design stock
            $pdo->prepare("UPDATE product_designs SET stock_qty = stock_qty + ? WHERE id = ?")
                ->execute([$quantity, $design_id]);

            // Update parent group stock & weight
            if ($total_weight_val > 0) {
                $pdo->prepare("UPDATE products SET total_stock_qty = total_stock_qty + ?, bulk_total_weight = bulk_total_weight + ? WHERE id = ?")
                    ->execute([$quantity, $total_weight_val, $product_id]);
            } else {
                $pdo->prepare("UPDATE products SET total_stock_qty = total_stock_qty + ? WHERE id = ?")
                    ->execute([$quantity, $product_id]);
            }

            // Recalculate average piece weight broadly
            $pdo->exec("UPDATE products SET per_piece_weight = CASE WHEN total_stock_qty > 0 THEN bulk_total_weight / total_stock_qty ELSE 0 END WHERE id = $product_id");

            $pdo->commit();
            $messages[] = "Stock added successfully to design variant!";
            $_POST = [];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error saving stock movement: " . $e->getMessage();
        }
    }
}

// Fetch recent IN movements
$tab = $_GET['tab'] ?? 'IN';
if (!in_array($tab, ['IN', 'OUT'])) $tab = 'IN';

$stmt = $pdo->prepare("
  SELECT sm.*, p.name AS product_name, p.sku AS product_sku, d.design_name, d.sku AS design_sku
  FROM stock_movements sm
  JOIN products p ON p.id = sm.product_id
  LEFT JOIN product_designs d ON d.id = sm.design_id
  WHERE sm.movement_type = ?
  ORDER BY COALESCE(sm.movement_date, sm.created_at) DESC, sm.id DESC
  LIMIT 50
");
$stmt->execute([$tab]);
$movements = $stmt->fetchAll();

require "templates/header.php";
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
  <h6 class="mb-0">Add Stock (IN) & History</h6>
  <a href="stock_movement_report.php" class="btn btn-outline-secondary btn-sm disabled">
    Download Report
  </a>
</div>

<?php foreach ($messages as $m): ?>
  <div class="alert alert-success"><?= e($m) ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger"><?= e($e) ?></div>
<?php endforeach; ?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white"><strong>Receive Stock</strong></div>
      <div class="card-body">
        <form method="post">
          <div class="mb-3">
            <label class="form-label">Date</label>
            <input type="date" name="movement_date" class="form-control"
                   value="<?= e($_POST['movement_date'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Product Design</label>
            <select name="design_id" class="form-select" required>
              <option value="">-- Select Design Variant --</option>
              <?php foreach ($designs as $d): ?>
                <option value="<?= $d['design_id'] ?>" <?= (isset($_POST['design_id']) && (int)$_POST['design_id'] === (int)$d['design_id']) ? 'selected' : '' ?>>
                  <?= e($d['product_name']) ?> — <?= e($d['design_name']) ?> (Stock: <?= (int)$d['design_stock'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Quantity Received (pieces)</label>
            <input type="number" name="quantity" class="form-control" min="1" required
                   value="<?= e($_POST['quantity'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Vendor Name</label>
            <input type="text" name="vendor_name" class="form-control"
                   value="<?= e($_POST['vendor_name'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Total Weight for this Batch (gm)</label>
            <input type="number" step="0.001" name="total_weight" class="form-control"
                   value="<?= e($_POST['total_weight'] ?? '') ?>">
            <div class="form-text">If provided, this weight will be added to the parent group's Bulk Weight and the average piece weight will automatically recalculate.</div>
          </div>
          <button class="btn btn-primary">Process Stock IN</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>Movement History</strong>
        <div class="btn-group btn-group-sm">
            <a href="?tab=IN" class="btn <?= $tab === 'IN' ? 'btn-primary' : 'btn-outline-primary' ?>">Stock IN</a>
            <a href="?tab=OUT" class="btn <?= $tab === 'OUT' ? 'btn-primary' : 'btn-outline-primary' ?>">Stock OUT</a>
        </div>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 100px;" class="d-none d-md-table-cell ps-3">Date</th>
              <th class="ps-3 ps-md-0">Movement Details</th>
              <th class="text-end pe-4">Qty</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($movements as $m): ?>
              <tr>
                <td class="d-none d-md-table-cell align-middle text-secondary small ps-3">
                  <?= date('M j, Y', strtotime($m['movement_date'] ?: $m['created_at'])) ?>
                </td>
                <td class="align-middle ps-3 ps-md-0 py-3">
                  <div class="fw-bold text-dark text-wrap mb-1" style="max-width: 280px;"><?= e($m['product_name']) ?></div>
                  <div class="text-secondary small mb-2"><i class="bi bi-arrow-return-right me-1"></i> <?= e($m['design_name'] ?: 'General') ?></div>
                  
                  <div class="d-flex flex-wrap gap-1 align-items-center mb-1">
                      <span class="badge bg-light border text-muted px-2 py-1 shadow-sm d-md-none my-1"><i class="bi bi-calendar3"></i> <?= date('M j', strtotime($m['movement_date'] ?: $m['created_at'])) ?></span>
                      <span class="badge <?= $tab === 'IN' ? 'bg-primary-subtle border-primary-subtle text-primary' : 'bg-info-subtle border-info-subtle text-info' ?> border px-2 py-1 shadow-sm"><i class="bi bi-person-badge"></i> <?= e($m['vendor_name'] ?: ($tab === 'IN' ? 'Unknown Vendor' : 'Retail Sales')) ?></span>
                  </div>
                  <?php if($m['note']): ?>
                      <div class="small text-muted mt-2 fst-italic text-wrap border-start border-2 ps-2" style="max-width: 280px; font-size: 0.75rem;">"<?= e($m['note']) ?>"</div>
                  <?php endif; ?>
                </td>
                <td class="align-middle fw-bold fs-5 text-end pe-4 <?= $tab === 'IN' ? 'text-success' : 'text-danger' ?>">
                  <?= $tab === 'IN' ? '+' : '-' ?><?= (int)$m['quantity'] ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($movements)): ?>
              <tr><td colspan="3" class="text-center py-5 text-muted">No stock movements recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require "templates/footer.php"; ?>
