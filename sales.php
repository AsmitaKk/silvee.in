<?php
require_once "auth_check.php";

$page_title = "Sales (Point of Sale)";

$messages = [];
$errors = [];

// Fetch designs for dropdown with their parent product info
$designs = $pdo->query("
    SELECT 
        d.id as design_id,
        d.product_id,
        d.design_name,
        d.sku as design_sku,
        d.stock_qty as design_stock,
        p.name as product_name,
        p.sku as product_sku,
        p.per_piece_weight,
        p.total_stock_qty as parent_stock
    FROM product_designs d
    JOIN products p ON d.product_id = p.id
    ORDER BY p.name, d.design_name
")->fetchAll();

$designs_json = json_encode($designs);

// Fetch active users for salesperson dropdown
$users = $pdo->query("SELECT name FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sale_date   = $_POST['sale_date'] ?? '';
    $buyer_name  = trim($_POST['buyer_name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $sales_person = trim($_POST['sales_person'] ?? 'Admin');

    $posted_design_ids = $_POST['design_id'] ?? [];
    $quantities        = $_POST['quantity'] ?? [];
    $total_weights     = $_POST['total_weight'] ?? [];

    if ($sale_date === '') {
        $errors[] = "Sale date is required.";
    }

    $items = [];
    $negativeWeightProducts = []; // Used for stock checks

    if (is_array($posted_design_ids)) {
        for ($i = 0; $i < count($posted_design_ids); $i++) {
            $did   = (int)$posted_design_ids[$i];
            $qty   = (int)($quantities[$i] ?? 0);
            $twt   = $total_weights[$i] ?? '';

            $twtVal = null;
            if ($twt !== '' && is_numeric($twt)) {
                $twtVal = (float)$twt;
            }

            if ($did > 0 && $qty > 0) {
                if ($twtVal !== null && $twtVal < 0) {
                    $errors[] = "Weight cannot be negative.";
                    continue;
                }
                
                // Get current stock for this design and parent
                $stmt = $pdo->prepare("
                    SELECT d.stock_qty as design_stock, p.total_stock_qty, p.id as product_id, p.name, d.design_name 
                    FROM product_designs d 
                    JOIN products p ON d.product_id = p.id 
                    WHERE d.id = ?
                ");
                $stmt->execute([$did]);
                $row = $stmt->fetch();
                
                if ($row) {
                    $newDesignStock = (int)$row['design_stock'] - $qty;
                    if ($newDesignStock < 0) {
                        $negativeWeightProducts[] = [
                            'name' => $row['name'] . ' - ' . $row['design_name'],
                            'current_stock' => $row['design_stock'],
                            'requested_qty' => $qty,
                            'shortage' => abs($newDesignStock)
                        ];
                    }
                    
                    $items[] = [
                        'design_id'    => $did,
                        'product_id'   => $row['product_id'],
                        'quantity'     => $qty,
                        'total_weight' => $twtVal,
                    ];
                }
            }
        }
    }

    if (empty($items)) {
        $errors[] = "Please add at least one design with sold quantity.";
    }

    if (!empty($negativeWeightProducts)) {
        $errorMsg = "Cannot process sale due to insufficient stock:\n";
        foreach ($negativeWeightProducts as $prod) {
            $errorMsg .= "- {$prod['name']}: Available {$prod['current_stock']}, Requested {$prod['requested_qty']} (Shortage: {$prod['shortage']})\n";
        }
        $errors[] = $errorMsg;
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert sale header
            $sale_note = "Processed by: " . $sales_person;
            $stmt = $pdo->prepare("INSERT INTO sales (sale_date, buyer_name, city, note) VALUES (?, ?, ?, ?)");
            $stmt->execute([$sale_date, $buyer_name, $city, $sale_note]);
            $sale_id = (int)$pdo->lastInsertId();

            // Mirror into store_orders
            $stmt_so = $pdo->prepare("INSERT INTO store_orders (customer_name, customer_phone, shipping_address, order_notes, total_amount, status, order_type, salesperson_name) VALUES (?, 'N/A', ?, ?, 0, 'Completed', 'Offline', ?)");
            $stmt_so->execute([$buyer_name ?: 'Walk-in Customer', $city ?: 'In-Store', 'Physical POS Sale #'.$sale_id, $sales_person]);
            $store_order_id = (int)$pdo->lastInsertId();
            $total_store_amount = 0;

            foreach ($items as $item) {
                $design_id    = $item['design_id'];
                $product_id   = $item['product_id'];
                $qty          = $item['quantity'];
                $totalWeight  = $item['total_weight'];

                // Get selling price
                $stmt = $pdo->prepare("SELECT selling_price FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $sellingPrice = (float)$stmt->fetchColumn();

                // Insert sale item
                $stmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, design_id, quantity, total_weight, selling_price) VALUES (?,?,?,?,?,?)");
                $stmt->execute([$sale_id, $product_id, $design_id, $qty, $totalWeight, $sellingPrice]);

                // Mirror into store_order_items
                $stmt_soi = $pdo->prepare("INSERT INTO store_order_items (order_id, product_id, design_id, quantity, price_per_item) VALUES (?,?,?,?,?)");
                $stmt_soi->execute([$store_order_id, $product_id, $design_id, $qty, $sellingPrice]);
                $total_store_amount += ($qty * $sellingPrice);

                // Insert OUT movement
                $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, design_id, movement_type, quantity, movement_date, vendor_name, total_weight, note) VALUES (?, ?, 'OUT', ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $product_id,
                    $design_id,
                    $qty,
                    $sale_date,
                    $sales_person,
                    $totalWeight,
                    "Sale #{$sale_id} to {$buyer_name}"
                ]);

                // Update design stock
                $pdo->prepare("UPDATE product_designs SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$qty, $design_id]);
                
                // Update parent product stock and weight
                $weightToDeduct = $totalWeight > 0 ? (float)$totalWeight : 0.0;
                $pdo->prepare("UPDATE products SET total_stock_qty = total_stock_qty - ?, bulk_total_weight = bulk_total_weight - ? WHERE id = ?")->execute([$qty, $weightToDeduct, $product_id]);
            }

            // Update mirrored total
            $pdo->prepare("UPDATE store_orders SET total_amount = ? WHERE id = ?")->execute([$total_store_amount, $store_order_id]);

            $pdo->commit();
            $messages[] = "Sale saved successfully. Design and Group stock updated.";
            $_POST = [];
            
            header("Location: sales.php");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error saving sale: " . $e->getMessage();
        }
    }
}

// Fetch recent sales summary
$stmt = $pdo->query("
  SELECT s.id, s.sale_date, s.buyer_name, s.note, s.created_at, 
         COALESCE(SUM(si.quantity), 0) AS total_items,
         COALESCE(SUM(si.total_weight), 0) AS total_weight
  FROM sales s
  LEFT JOIN sale_items si ON si.sale_id = s.id
  GROUP BY s.id
  ORDER BY s.sale_date DESC, s.id DESC
  LIMIT 20
");
$recentSales = $stmt->fetchAll();

require "templates/header.php";
?>

<?php foreach ($messages as $m): ?>
  <div class="alert alert-success"><?= e($m) ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger"><?= e($e) ?></div>
<?php endforeach; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
  <h6 class="mb-0">Process Sales</h6>
  <div class="btn-group shadow-sm">
      <a href="sales_report.php" class="btn btn-outline-secondary btn-sm">Monthly Sales</a>
      <a href="salesperson_report.php" class="btn btn-outline-dark btn-sm"><i class="bi bi-person-lines-fill me-1"></i> Staff Performance</a>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white"><strong>New Sale Entry</strong></div>
      <div class="card-body">
        <form method="post">
          <div class="row mb-3">
            <div class="col-md-3">
                <label class="form-label">Sale Date</label>
                <input type="date" name="sale_date" class="form-control" required value="<?= e($_POST['sale_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Buyer Name</label>
                <input type="text" name="buyer_name" class="form-control" value="<?= e($_POST['buyer_name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">City</label>
                <input type="text" name="city" class="form-control" value="<?= e($_POST['city'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Sales Person</label>
                <input type="text" name="sales_person" list="salesperson_list" class="form-control border-primary" value="<?= e($_POST['sales_person'] ?? $_SESSION['user_name'] ?? 'Admin') ?>" required>
                <datalist id="salesperson_list">
                    <?php foreach($users as $uname): ?>
                        <option value="<?= e($uname) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
          </div>

          <div class="mb-2"><label class="form-label">Product Designs Sold</label></div>
          <table class="table table-sm align-middle border-0" id="sale-items-table">
            <thead class="d-none d-md-table-header-group">
              <tr>
                <th style="width:45%; border-bottom: none;">Product > Design</th>
                <th style="width:20%; border-bottom: none;">Sold Qty</th>
                <th style="width:20%; border-bottom: none;">Weight (gm)</th>
                <th style="width:15%; border-bottom: none;"></th>
              </tr>
            </thead>
            <tbody class="border-top-0">
              <tr class="d-flex flex-column flex-md-row mb-3 pb-3 border-bottom border-md-bottom-0 pb-md-0 mb-md-0 w-100 align-items-md-center">
                <td class="flex-grow-1 border-0 px-0 px-md-2 mb-2 mb-md-0" style="min-width: 40%;">
                  <div class="d-md-none small fw-bold text-secondary mb-1">Product > Design Variant</div>
                  <select name="design_id[]" class="form-select shadow-sm border-primary" onchange="updateStockInfo(this)" required>
                    <option value="">-- Select Design --</option>
                    <?php foreach ($designs as $d): ?>
                      <option value="<?= $d['design_id'] ?>" 
                              data-stock="<?= $d['design_stock'] ?>"
                              data-weight="<?= $d['per_piece_weight'] ?>">
                        <?= e($d['product_name']) ?> — <?= e($d['design_name']) ?> (Available: <?= $d['design_stock'] ?>pcs)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td class="border-0 px-0 px-md-2 mb-2 mb-md-0" style="width: 100%; max-width: 200px;">
                  <div class="d-md-none small fw-bold text-secondary mb-1">Sold Qty (pcs)</div>
                  <input type="number" name="quantity[]" class="form-control shadow-sm" min="1" onchange="checkStock(this); autoCalcWeight(this);" required>
                </td>
                <td class="border-0 px-0 px-md-2 mb-2 mb-md-0" style="width: 100%; max-width: 200px;">
                  <div class="d-md-none small fw-bold text-secondary mb-1">Weight (gm)</div>
                  <input type="number" step="0.001" min="0" name="total_weight[]" class="form-control shadow-sm text-secondary" placeholder="Calculated Auto">
                </td>
                <td class="border-0 px-0 px-md-2 mt-2 mt-md-0 flex-shrink-0 align-self-end align-self-md-center">
                  <button type="button" class="btn btn-outline-danger shadow-sm w-100" onclick="removeRow(this)">
                    <span class="d-md-none"><i class="bi bi-trash me-1"></i>Remove</span>
                    <span class="d-none d-md-inline px-2">×</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="addRow()">+ Add another design</button>
          <div><button class="btn btn-primary">Process Sale</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white"><strong>Recent Sales</strong></div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3 bg-light">Sale Details</th>
              <th class="ps-3 bg-light">Buyer & Items</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentSales as $s): ?>
              <tr>
                <td class="ps-3 align-middle py-3">
                  <span class="d-inline-block fw-bold text-dark mb-1">#<?= (int)$s['id'] ?></span>
                  <span class="d-inline-block badge bg-light text-secondary border fw-normal mb-1 ms-2"><i class="bi bi-calendar3 me-1"></i><?= date('d M', strtotime($s['sale_date'])) ?></span>
                  <div class="small text-primary opacity-75 mt-1 text-wrap lh-sm" style="max-width:140px; font-size: 0.70rem;"><?= e($s['note'] ?: '') ?></div>
                </td>
                <td class="ps-3 align-middle py-3">
                  <div class="fw-bold text-dark text-wrap mb-1 lh-sm" style="max-width:150px;"><?= e($s['buyer_name'] ?: 'Retail Walk-in') ?></div>
                  <div class="small text-muted"><i class="bi bi-box me-1"></i><?= (int)$s['total_items'] ?> pcs sold</div>
                  <?php if($s['total_weight'] > 0): ?>
                      <div class="small text-muted mt-1 fst-italic" style="font-size: 0.70rem;"><i class="bi bi-speedometer2 me-1"></i>Avg ~<?= number_format((float)$s['total_weight'], 2) ?>g</div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($recentSales)): ?>
              <tr><td colspan="2" class="text-center py-4 text-muted">No sales recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
const designsData = <?= $designs_json ?>;

function addRow() {
  const tableBody = document.querySelector('#sale-items-table tbody');
  const firstRow = tableBody.querySelector('tr');
  const newRow = firstRow.cloneNode(true);
  newRow.querySelectorAll('select, input').forEach(el => {
    if (el.tagName === 'SELECT') el.selectedIndex = 0;
    if (el.tagName === 'INPUT') el.value = '';
  });
  tableBody.appendChild(newRow);
}

function removeRow(button) {
  const tableBody = document.querySelector('#sale-items-table tbody');
  if (tableBody.rows.length === 1) return;
  button.closest('tr').remove();
}

function updateStockInfo(select) {
  const selectedOption = select.options[select.selectedIndex];
  const row = select.closest('tr');
  const qtyInput = row.querySelector('input[name="quantity[]"]');
  const weightInput = row.querySelector('input[name="total_weight[]"]');
  
  const design = designsData.find(d => d.design_id == selectedOption.value);
  if (design && qtyInput) {
    qtyInput.setAttribute('data-available-stock', design.design_stock);
    qtyInput.setAttribute('data-unit-weight', design.per_piece_weight);
  }
}

function checkStock(input) {
  const availableStock = parseInt(input.getAttribute('data-available-stock')) || 0;
  const requestedQty = parseInt(input.value) || 0;
  
  if (requestedQty > availableStock) {
    alert(`Only ${availableStock} pieces available for this design.`);
    input.value = availableStock;
  }
}

function autoCalcWeight(input) {
  const unitWeight = parseFloat(input.getAttribute('data-unit-weight')) || 0;
  const qty = parseInt(input.value) || 0;
  const row = input.closest('tr');
  const weightInput = row.querySelector('input[name="total_weight[]"]');
  
  if (unitWeight > 0 && qty > 0 && !weightInput.value) {
    weightInput.value = (unitWeight * qty).toFixed(3);
  }
}
</script>

<?php require "templates/footer.php"; ?>
