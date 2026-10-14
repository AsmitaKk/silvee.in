<?php
require_once "auth_check.php";
require_once "role_management.php";

// Check if is_hidden column exists in products table, if not add it
try {
    $pdo->query("SELECT is_hidden FROM products LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE products ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0");
}

require_permission($pdo, 'products', 'view');

$canEdit = has_permission($pdo, 'products', 'edit');

// Handle Delete (Hide) Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && $canEdit) {
    $delete_id = (int)($_POST['id'] ?? 0);
    if ($delete_id > 0) {
        $stmt = $pdo->prepare("UPDATE products SET is_hidden = 1 WHERE id = ?");
        $stmt->execute([$delete_id]);
        $_SESSION['success'] = "Product batch removed successfully.";
        header("Location: products.php");
        exit;
    }
}

$page_title = "Product Groups & Designs";
require "templates/header.php";

// Search & Sort Parameters
$search = trim($_GET['search'] ?? '');
$sort = trim($_GET['sort'] ?? 'name_asc');

$where_clauses = ["(p.is_hidden = 0 OR p.is_hidden IS NULL)"];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(p.name LIKE ? OR c.name LIKE ? OR c.code LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = implode(' AND ', $where_clauses);

$order_sql = "ORDER BY p.name ASC";
if ($sort === 'name_desc') {
    $order_sql = "ORDER BY p.name DESC";
} elseif ($sort === 'newest') {
    $order_sql = "ORDER BY p.id DESC";
}

$stmt = $pdo->prepare("
  SELECT 
    p.*,
    c.name as category_name
  FROM products p
  LEFT JOIN categories c ON p.category_id = c.id
  WHERE $where_sql
  $order_sql
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch designs
$stmtD = $pdo->query("SELECT * FROM product_designs ORDER BY product_id, id");
$allDesigns = $stmtD->fetchAll();
$designsByProd = [];
foreach ($allDesigns as $d) {
    if(!isset($designsByProd[$d['product_id']])) $designsByProd[$d['product_id']] = [];
    $designsByProd[$d['product_id']][] = $d;
}
?>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <?= e($_SESSION['success']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <?= e($_SESSION['error']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center flex-wrap mb-4 gap-3">
  <h5 class="mb-0 fw-bold text-dark text-nowrap">Product Groups (Batches) & Designs</h5>
  
  <div class="d-flex flex-column flex-md-row gap-2 flex-grow-1 justify-content-md-center align-items-center" style="max-width: 650px;">
      <form method="GET" action="products.php" class="d-flex flex-column flex-sm-row w-100 gap-2 shadow-sm rounded">
          <div class="input-group">
              <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
              <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search batch, category..." value="<?= e($search) ?>">
          </div>
          <select name="sort" class="form-select w-auto" onchange="this.form.submit()">
              <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Alphabetically (A-Z)</option>
              <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Alphabetically (Z-A)</option>
              <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>New added first</option>
          </select>
          <?php if ($search !== '' || $sort !== 'name_asc'): ?>
              <a href="products.php" class="btn btn-outline-secondary px-3" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
          <?php endif; ?>
      </form>
  </div>
  
  <div class="d-flex gap-2 ms-lg-auto flex-shrink-0">
    <?php if ($canEdit): ?>
      <a href="product_form.php" class="btn btn-primary shadow-sm text-nowrap"><i class="bi bi-plus-circle me-1"></i> Add Product Batch</a>
    <?php endif; ?>
  </div>
</div>

<div class="card shadow-sm border-0 rounded-4 overflow-hidden">
  <div class="card-body p-0">
    <div class="accordion accordion-flush" id="productsAccordion">
        <?php foreach ($products as $p): ?>
            <?php 
                $pid = (int)$p['id'];
                $pDesigns = $designsByProd[$pid] ?? [];
                
                // Current stock sum
                $current_stock = 0;
                foreach($pDesigns as $pd) $current_stock += (int)$pd['stock_qty'];
            ?>
            <div class="border-0 border-bottom">
                <div class="d-flex flex-column flex-md-row bg-white" id="heading<?= $pid ?>">
                    <a href="product_form.php?id=<?= $pid ?>" class="text-decoration-none py-3 flex-grow-1 text-start px-3" style="color: inherit; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f8f9fa';" onmouseout="this.style.backgroundColor='transparent';">
                        <div class="d-flex flex-column flex-md-row w-100 justify-content-between pe-3 align-items-start align-items-md-center gap-2">
                            <div>
                                <strong class="fs-6 text-dark"><?= e($p['name']) ?></strong> 
                                <span class="text-secondary fs-6 ms-md-2">(<?= count($pDesigns) ?> Designs)</span>
                                <div class="mt-2 d-flex gap-1 flex-wrap">
                                    <span class="badge bg-light border text-dark fw-medium"><?= e($p['category_name'] ?: 'No Category') ?></span>
                                    <span class="badge bg-light border text-dark fw-medium">Current Wt: <?= number_format($current_stock * (float)$p['per_piece_weight'], 3) ?>g</span>
                                    <span class="badge bg-light border text-dark fw-medium">Avg: <?= number_format((float)$p['per_piece_weight'], 3) ?>g</span>
                                </div>
                            </div>
                            <div class="text-start text-md-end pe-md-2 mt-2 mt-md-0">
                                <strong class="fs-5 d-block text-primary m-0"><?= $current_stock ?> Pcs</strong>
                            </div>
                        </div>
                    </a>
                    <?php if ($canEdit): ?>
                        <div class="p-2 p-md-3 d-flex align-items-center justify-content-start justify-content-md-center border-start-md bg-white border-top border-top-md-0 gap-2" style="min-width: 100px;">
                            <a href="product_form.php?id=<?= $pid ?>" class="btn btn-sm btn-outline-primary shadow-sm" title="Edit Product Batch"><i class="bi bi-pencil-square"></i></a>
                            <form method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to delete this product batch and all its designs?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $pid ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm" title="Delete Product Batch"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($products)): ?>
          <div class="p-5 text-center text-muted bg-white">
             <i class="bi bi-box-seam fs-1 text-light"></i><br>
             No product batches found.<br>Click "Add Product Batch" to start tracking inventory.
          </div>
        <?php endif; ?>
    </div>
  </div>
</div>

<?php require "templates/footer.php"; ?>
