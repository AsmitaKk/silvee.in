<?php
require_once "auth_check.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: dashboard.php");
    exit;
}

// Fetch this category
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    header("Location: dashboard.php");
    exit;
}

// Fetch children (sub-categories)
$stmt = $pdo->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY name");
$stmt->execute([$id]);
$subCategories = $stmt->fetchAll();

// LOW STOCK per category (direct)
$lowProducts = $pdo->query("
    SELECT id, name, category_id, total_stock_qty, min_stock_level
    FROM products
    WHERE total_stock_qty <= min_stock_level
      AND min_stock_level > 0
      AND (is_hidden = 0 OR is_hidden IS NULL)
")->fetchAll();

$lowCountsByCategory = [];
foreach ($lowProducts as $p) {
    $cid = $p['category_id'];
    if (!$cid) continue;
    if (!isset($lowCountsByCategory[$cid])) {
        $lowCountsByCategory[$cid] = 0;
    }
    $lowCountsByCategory[$cid]++;
}

$page_title = $category['name'];
require "templates/header.php";
?>

<?php if (!empty($subCategories)): ?>
  <h5 class="mb-3">Sub-categories under "<?= e($category['name']) ?>"</h5>
  <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3 mb-4">
    <?php foreach ($subCategories as $sub): ?>
      <?php
        $lowCount = $lowCountsByCategory[$sub['id']] ?? 0;
      ?>
      <div class="col-6 col-md-3 col-lg-2">
        <a href="category.php?id=<?= $sub['id'] ?>" class="text-decoration-none text-dark">
          <div class="card h-100 shadow-sm border-0 position-relative">
            <?php if ($lowCount > 0): ?>
              <span class="category-low-badge position-absolute" style="top:8px; right:8px; background-color: #dc3545; color: white; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;">
                Low Stock
              </span>
            <?php endif; ?>

            <?php if (!empty($sub['image_path'])): ?>
              <img src="<?= e($sub['image_path']) ?>" class="card-img-top" style="height:130px;object-fit:cover;">
            <?php else: ?>
              <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center"
                   style="height:130px;">
                <span class="text-white fw-semibold"><?= e($sub['name']) ?></span>
              </div>
            <?php endif; ?>

            <div class="card-body text-center py-2 px-1">
              <h6 class="card-title mb-0 small text-truncate px-1"><?= e($sub['name']) ?></h6>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>

<?php else: ?>
  <?php
  // Fetch INDIVIDUAL DESIGNS in this category instead of Parent Batches
  $stmt = $pdo->prepare("
  SELECT 
    d.*, 
    p.name as product_name, 
    p.per_piece_weight
  FROM product_designs d
  JOIN products p ON d.product_id = p.id
  WHERE p.category_id = ? AND d.is_hidden = 0 AND (p.is_hidden = 0 OR p.is_hidden IS NULL)
  ORDER BY p.name, d.design_name
  ");
  $stmt->execute([$id]);
  $designs = $stmt->fetchAll();
  ?>

  <h5 class="mb-3">Design Variants inside "<?= e($category['name']) ?>"</h5>

  <div class="card shadow-sm border-0">
    <div class="card-body p-0">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 60px;" class="ps-3">Img</th>
            <th>Design Details</th>
            <th class="text-end pe-4">Stock & Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($designs as $d): ?>
            <tr>
              <td class="ps-3 align-middle">
                <?php if (!empty($d['image_path'])): 
                  $first_img = explode(',', $d['image_path'])[0];
                ?>
                  <img src="<?= e($first_img) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;cursor:pointer;" class="shadow-sm hover-lift" onclick="showImagePreview('<?= e(addslashes($d['image_path'])) ?>', '<?= e(addslashes($d['design_name'])) ?>')" title="Click to enlarge">
                <?php else: ?>
                  <div style="width:40px;height:40px;background:#eee;border-radius:6px;" class="d-flex align-items-center justify-content-center text-muted small border">No Img</div>
                <?php endif; ?>
              </td>
              <td class="align-middle py-3">
                <div class="small text-secondary fw-bold lh-sm mb-1"><?= e($d['product_name']) ?></div>
                <div class="fw-bold text-dark mb-1"><?= e($d['design_name']) ?></div>
                <div class="small text-muted"><span class="badge bg-light border text-secondary shadow-sm px-1 py-0" style="font-size:0.65rem;">SKU: <?= e($d['sku'] ?: 'N/A') ?></span></div>
              </td>
              <td class="text-end pe-4 align-middle py-3">
                <div class="fw-bold text-success mb-1" style="font-size: 0.95rem;"><?= (int)$d['stock_qty'] ?> pcs</div>
                <div class="small text-muted mb-2 fst-italic" style="font-size: 0.75rem;">~<?= number_format((float)$d['per_piece_weight'], 3) ?>g per</div>
                <a href="product_form.php?id=<?= $d['product_id'] ?>" class="btn btn-sm btn-outline-secondary shadow-sm py-1 px-2">
                  <i class="bi bi-gear-fill me-1"></i>Manage
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($designs)): ?>
            <tr>
              <td colspan="3" class="text-center py-5 text-muted">
                No designs found in this category. Head over to Products to add some.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-transparent border-0 shadow-none">
      <div class="modal-header border-0 pb-0 justify-content-end">
        <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1) grayscale(100%) brightness(200%);"></button>
      </div>
      <div class="modal-body text-center pb-4 pt-1">
        <img id="previewModalImage" src="" class="img-fluid rounded shadow-lg" style="max-height: 80vh; border: 3px solid white;" alt="Preview">
        <h5 id="previewModalTitle" class="mt-3 mb-0 fw-bold text-white text-shadow-sm"></h5>
      </div>
    </div>
  </div>
</div>

<script>
function showImagePreview(src, title) {
    const images = src.split(',');
    const body = document.querySelector('#imagePreviewModal .modal-body');
    let html = '';
    if (images.length > 1) {
        html = `
        <div id="modalCarousel" class="carousel slide" data-bs-ride="carousel">
          <div class="carousel-inner">
            ${images.map((img, i) => `
              <div class="carousel-item ${i === 0 ? 'active' : ''}">
                <img src="${img.trim()}" class="img-fluid rounded shadow-lg" style="max-height: 70vh; border: 3px solid white;" alt="Preview">
              </div>
            `).join('')}
          </div>
          <button class="carousel-control-prev" type="button" data-bs-target="#modalCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true" style="filter: drop-shadow(0px 0px 3px rgba(0,0,0,0.8));"></span>
            <span class="visually-hidden">Previous</span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#modalCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true" style="filter: drop-shadow(0px 0px 3px rgba(0,0,0,0.8));"></span>
            <span class="visually-hidden">Next</span>
          </button>
        </div>
        `;
    } else {
        html = `<img src="${images[0].trim()}" class="img-fluid rounded shadow-lg" style="max-height: 80vh; border: 3px solid white;" alt="Preview">`;
    }
    html += `<h5 id="previewModalTitle" class="mt-3 mb-0 fw-bold text-white text-shadow-sm">${title}</h5>`;
    body.innerHTML = html;
    var myModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    myModal.show();
}
</script>

<?php require "templates/footer.php"; ?>
