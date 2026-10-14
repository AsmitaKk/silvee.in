<?php
require_once "auth_check.php";
require_once "role_management.php";

// ✅ Import changes data, so require edit permission
require_permission($pdo, 'products', 'edit');

$page_title = "Import Products from Excel";
$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['file']['name'])) {
        $errors[] = "Please select a CSV file.";
    } else {
        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Upload error. Please try again.";
        } else {
            $tmpPath = $file['tmp_name'];
            $handle = fopen($tmpPath, 'r');

            if ($handle === false) {
                $errors[] = "Unable to open uploaded file.";
            } else {
                $rowNum = 0;
                $updated = 0;
                $skipped = 0;

                $pdo->beginTransaction();

                try {
                    while (($row = fgetcsv($handle)) !== false) {
                        $rowNum++;

                        // Skip header row
                        if ($rowNum === 1) {
                            continue;
                        }

                        // Expected columns:
                        // 0: Category
                        // 1: Sub-category
                        // 2: Product Name
                        // 3: SKU
                        // 4: Quantity
                        // 5: Min Stock Level
                        // 6: Metal Type
                        // 7: Purity
                        $sku      = trim($row[3] ?? '');
                        $qty      = $row[4] ?? '';
                        $minStock = $row[5] ?? '';

                        if ($sku === '') {
                            $skipped++;
                            continue;
                        }

                        $qty = is_numeric($qty) ? (int)$qty : null;
                        $minStock = is_numeric($minStock) ? (int)$minStock : null;

                        // Only update stock_qty and min_stock_level
                        $params = [];
                        $setParts = [];

                        if ($qty !== null) {
                            $setParts[] = "total_stock_qty = ?";
                            $params[] = $qty;
                        }
                        if ($minStock !== null) {
                            $setParts[] = "min_stock_level = ?";
                            $params[] = $minStock;
                        }

                        if (empty($setParts)) {
                            $skipped++;
                            continue;
                        }

                        $params[] = $sku;

                        $sql = "UPDATE products SET " . implode(", ", $setParts) . " WHERE sku = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);

                        if ($stmt->rowCount() > 0) {
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    }

                    $pdo->commit();
                    fclose($handle);

                    $messages[] = "Import completed. Updated {$updated} product(s). Skipped {$skipped} row(s).";

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    fclose($handle);
                    $errors[] = "Error during import: " . $e->getMessage();
                }
            }
        }
    }
}

require "templates/header.php";
?>

<div class="card shadow-sm border-0">
  <div class="card-header bg-white">
    <strong>Import Products from Excel/CSV</strong>
  </div>
  <div class="card-body">

    <?php foreach ($messages as $m): ?>
      <div class="alert alert-success"><?= e($m) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $eMsg): ?>
      <div class="alert alert-danger"><?= e($eMsg) ?></div>
    <?php endforeach; ?>

    <p class="text-muted small mb-3">
      1. First click <strong>"Download Excel"</strong> to get the latest product sheet.<br>
      2. Open it in Excel, update <strong>Quantity</strong> and <strong>Min Stock Level</strong> columns as needed.<br>
      3. Do <strong>not</strong> change the header row or the <strong>SKU</strong> column values.<br>
      4. Save as CSV and upload it here.
    </p>

    <form method="post" enctype="multipart/form-data">
      <div class="mb-3">
        <label class="form-label">CSV File</label>
        <input type="file" name="file" class="form-control" accept=".csv" required>
      </div>
      <button class="btn btn-primary">Upload &amp; Import</button>
      <a href="products.php" class="btn btn-secondary">Back to Products</a>
    </form>
  </div>
</div>

<?php require "templates/footer.php"; ?>
