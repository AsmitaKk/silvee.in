<?php
require_once "auth_check.php";

// Check if is_hidden column exists in categories table, if not add it
try {
    $pdo->query("SELECT is_hidden FROM categories LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE categories ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0");
}

// AJAX products fetch for subcategory (must run before headers are sent)
if (isset($_GET['action']) && $_GET['action'] === 'get_subcategory_products') {
    header('Content-Type: application/json');
    $sub_id = (int)($_GET['subcategory_id'] ?? 0);
    $products = [];
    if ($sub_id > 0) {
        $stmt = $pdo->prepare("SELECT sku, name, total_stock_qty, per_piece_weight FROM products WHERE category_id = ? AND (is_hidden = 0 OR is_hidden IS NULL) ORDER BY name");
        $stmt->execute([$sub_id]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode($products);
    exit;
}

$page_title = "Categories";
require "templates/header.php";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $delete_id = (int)($_POST['id'] ?? 0);
        if ($delete_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$delete_id]);
            echo '<div class="alert alert-success">Category removed successfully.</div>';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'toggle_hide') {
        $hide_id = (int)($_POST['id'] ?? 0);
        if ($hide_id > 0) {
            $stmt = $pdo->prepare("SELECT is_hidden FROM categories WHERE id = ?");
            $stmt->execute([$hide_id]);
            $current_val = (int)$stmt->fetchColumn();
            
            $new_val = ($current_val === 1) ? 0 : 1;
            
            $stmt = $pdo->prepare("UPDATE categories SET is_hidden = ? WHERE id = ?");
            $stmt->execute([$new_val, $hide_id]);
            
            $status_msg = ($new_val === 1) ? "Category hidden successfully from customer view." : "Category made visible successfully.";
            echo '<div class="alert alert-success">' . $status_msg . '</div>';
        }
    } else {
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $name = $parent_id ? trim($_POST['name_sub'] ?? '') : trim($_POST['name_top'] ?? '');
        $code = $parent_id ? trim($_POST['code_sub'] ?? '') : trim($_POST['code_top'] ?? '');
        $image_path = null;

        if ($name === '') {
            echo '<div class="alert alert-danger">Name is required.</div>';
        } elseif ($code === '') {
            echo '<div class="alert alert-danger">Category code is required.</div>';
        } else {
            // Check if name already exists (case-insensitive)
            $stmt_name = $pdo->prepare("SELECT id, is_hidden, name FROM categories WHERE LOWER(name) = LOWER(?) ORDER BY is_hidden ASC LIMIT 1");
            $stmt_name->execute([$name]);
            $existing_name_cat = $stmt_name->fetch(PDO::FETCH_ASSOC);

            // Check if code already exists (case-insensitive)
            $stmt_code = $pdo->prepare("SELECT id, is_hidden, name FROM categories WHERE LOWER(code) = LOWER(?) ORDER BY is_hidden ASC LIMIT 1");
            $stmt_code->execute([$code]);
            $existing_code_cat = $stmt_code->fetch(PDO::FETCH_ASSOC);

            $restore_id = null;
            $conflict = false;
            $conflict_msg = "";

            if ($existing_name_cat) {
                if ($existing_name_cat['is_hidden'] == 0) {
                    $conflict = true;
                    $conflict_msg = "This category name is already available.";
                } else {
                    $restore_id = $existing_name_cat['id'];
                }
            }

            if (!$conflict && $existing_code_cat && $existing_code_cat['id'] !== $restore_id) {
                if ($existing_code_cat['is_hidden'] == 0) {
                    $conflict = true;
                    $conflict_msg = "This category code is already used for the '" . htmlspecialchars($existing_code_cat['name'], ENT_QUOTES, 'UTF-8') . "'.";
                } else {
                    if (!$restore_id) {
                        $restore_id = $existing_code_cat['id'];
                    } else {
                        $conflict = true;
                        $conflict_msg = "The entered Name and Code belong to two different deleted categories. Please change one of them.";
                    }
                }
            }

            if ($conflict) {
                echo '<div class="alert alert-danger">' . $conflict_msg . '</div>';
            } else {
                // Handle image upload if provided
                $file_field = $parent_id ? 'image_sub' : 'image_top';
                if (!empty($_FILES[$file_field]['name'])) {
                    $file = $_FILES[$file_field];
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        if (!is_dir('uploads/categories')) {
                            mkdir('uploads/categories', 0777, true);
                        }
                        $newName = 'uploads/categories/' . time() . '_' . rand(1000,9999) . '.' . $ext;
                        if (move_uploaded_file($file['tmp_name'], $newName)) {
                            $image_path = $newName;
                        } else {
                            echo '<div class="alert alert-warning">Image upload failed, category will be created without image.</div>';
                        }
                    } else {
                        echo '<div class="alert alert-warning">Image upload error.</div>';
                    }
                }

                if ($restore_id) {
                    $stmt = $pdo->prepare("UPDATE categories SET name=?, code=?, parent_id=?, is_hidden=0" . ($image_path ? ", image_path=?" : "") . " WHERE id=?");
                    if ($image_path) {
                        $stmt->execute([$name, $code, $parent_id, $image_path, $restore_id]);
                    } else {
                        $stmt->execute([$name, $code, $parent_id, $restore_id]);
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, code, parent_id, image_path, labour_charge, flat_rate) VALUES (?, ?, ?, ?, 0.00, 0.00)");
                    $stmt->execute([$name, $code, $parent_id, $image_path]);
                }

                echo '<div class="alert alert-success">Category added.</div>';
            }
        }
    }

    // Always reload categories from database on any POST request to show fresh state
    $allCategories = $pdo->query("
        SELECT c.*, 
               (SELECT COUNT(d.id) FROM product_designs d JOIN products p ON d.product_id = p.id WHERE p.category_id = c.id AND d.is_hidden = 0 AND (p.is_hidden = 0 OR p.is_hidden IS NULL)) as design_count
        FROM categories c
        ORDER BY c.name
    ")->fetchAll();
    
    // Re-group subcategories
    $subCategoriesGrouped = [];
    foreach ($allCategories as $c) {
        if (!empty($c['parent_id'])) {
            $subCategoriesGrouped[$c['parent_id']][] = [
                'id' => $c['id'],
                'name' => $c['name'],
                'code' => $c['code'],
                'image_path' => $c['image_path'],
                'is_hidden' => (int)($c['is_hidden'] ?? 0),
                'design_count' => (int)($c['design_count'] ?? 0)
            ];
        }
    }
    
    // Re-fetch top-level
    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE (name LIKE ? OR code LIKE ?) AND (parent_id IS NULL OR parent_id = 0 OR parent_id = '') ORDER BY name");
        $stmt->execute(["%$search%", "%$search%"]);
        $categories = $stmt->fetchAll();
    } else {
        $categories = [];
        foreach ($allCategories as $c) {
            if (empty($c['parent_id'])) {
                $categories[] = $c;
            }
        }
    }
}

// Fetch all categories for the dropdown menu and grouping, including design counts
$allCategories = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(d.id) FROM product_designs d JOIN products p ON d.product_id = p.id WHERE p.category_id = c.id AND d.is_hidden = 0 AND (p.is_hidden = 0 OR p.is_hidden IS NULL)) as design_count
    FROM categories c
    ORDER BY c.name
")->fetchAll();

// Group subcategories by parent_id
$subCategoriesGrouped = [];
foreach ($allCategories as $cat) {
    if (!empty($cat['parent_id'])) {
        $subCategoriesGrouped[$cat['parent_id']][] = [
            'id' => $cat['id'],
            'name' => $cat['name'],
            'code' => $cat['code'],
            'image_path' => $cat['image_path'],
            'is_hidden' => (int)($cat['is_hidden'] ?? 0),
            'design_count' => (int)($cat['design_count'] ?? 0)
        ];
    }
}

// Handle search query
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE (name LIKE ? OR code LIKE ?) AND (parent_id IS NULL OR parent_id = 0 OR parent_id = '') ORDER BY name");
    $stmt->execute(["%$search%", "%$search%"]);
    $categories = $stmt->fetchAll();
} else {
    $categories = [];
    foreach ($allCategories as $cat) {
        if (empty($cat['parent_id'])) {
            $categories[] = $cat;
        }
    }
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
  <div class="d-flex align-items-center gap-2">
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addCategoryWizardModal">
      <i class="bi bi-plus-circle me-1"></i>Add Category
    </button>
  </div>
  <a href="masters.php" class="btn btn-sm btn-outline-secondary">Back to Masters</a>
</div>

<div class="row">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
        <strong class="align-middle">All Categories</strong>
        <form method="GET" action="" class="d-flex gap-2" style="max-width: 300px;">
          <div class="input-group input-group-sm">
            <input type="text" name="search" class="form-control" placeholder="Search name or code..." value="<?= e($search) ?>">
            <button class="btn btn-outline-secondary" type="submit">
              <i class="bi bi-search"></i>
            </button>
            <?php if ($search !== ''): ?>
              <a href="categories.php" class="btn btn-outline-danger" title="Clear Search">
                <i class="bi bi-x-lg"></i>
              </a>
            <?php endif; ?>
          </div>
        </form>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th style="width: 50px;" class="ps-3">Img</th>
              <th>Category Details</th>
              <th class="text-end pe-4">Actions</th>
            </tr>
          </thead>

          <tbody>
            <?php foreach ($categories as $cat): ?>
              <?php
              $subcats = $subCategoriesGrouped[$cat['id']] ?? [];
              $subCount = count($subcats);
              $subcats_json = json_encode($subcats);
              ?>
              <tr class="category-row" style="cursor: pointer;"
                  data-category-name="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>"
                  data-subcategories="<?= htmlspecialchars($subcats_json, ENT_QUOTES, 'UTF-8') ?>">
                <td class="ps-3 align-middle">
                  <?php if (!empty($cat['image_path'])): ?>
                    <img src="<?= e($cat['image_path']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid #ddd;" class="shadow-sm">
                  <?php else: ?>
                    <div style="width:40px;height:40px;background:#eee;border-radius:6px;" class="d-flex align-items-center justify-content-center text-muted small border">None</div>
                  <?php endif; ?>
                </td>
                <td class="align-middle">
                  <div class="fw-bold text-dark mb-1">
                    <a href="#" class="view-subcategories text-decoration-none text-dark" style="cursor: pointer;"
                       data-category-name="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>"
                       data-subcategories="<?= htmlspecialchars($subcats_json, ENT_QUOTES, 'UTF-8') ?>"
                       onmouseover="this.style.color='var(--bs-primary)';" onmouseout="this.style.color='';"
                    >
                      <?= e($cat['name']) ?>
                    </a>
                    <?php if (!empty($cat['code'])): ?>
                      <span class="badge bg-secondary-subtle text-secondary border px-2 ms-1" style="font-size: 0.7rem;"><?= e($cat['code']) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="small text-muted mt-1">
                      <a href="#" class="view-subcategories badge bg-info-subtle border text-info px-2 py-1 shadow-sm text-decoration-none" 
                         data-category-name="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>"
                         data-subcategories="<?= htmlspecialchars($subcats_json, ENT_QUOTES, 'UTF-8') ?>">
                          <i class="bi bi-diagram-3 me-1"></i> <?= $subCount ?> Sub-categories
                      </a>
                      <span class="d-none d-md-inline ms-2 fst-italic" style="font-size: 0.70rem;">Created: <?= date('M j, Y', strtotime($cat['created_at'])) ?></span>
                  </div>
                </td>
                <td class="text-end pe-4 align-middle">
                  <div class="d-flex justify-content-end gap-1">
                    <a href="category_edit.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-secondary shadow-sm">
                      Edit
                    </a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove the category \'<?= e(addslashes($cat['name'])) ?>\'? This will also remove its parent reference on any sub-categories/products.');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm">
                        Remove
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?>
              <tr><td colspan="3" class="text-center py-4 text-muted">No categories yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const input = document.getElementById('categoryImageInput');
  const warning = document.getElementById('categoryImageWarning');
  if (!input || !warning) return;

  input.addEventListener('change', function () {
    warning.style.display = 'none';
    warning.textContent = '';

    const file = this.files && this.files[0];
    if (!file) return;

    const messages = [];

    // 1) File size check (400 KB)
    const sizeKB = file.size / 1024;
    if (sizeKB > 400) {
      messages.push(
        `Large image (${sizeKB.toFixed(0)} KB). For faster loading, try keeping it under 400 KB.`
      );
    }

    // 2) Aspect ratio check (roughly square)
    const reader = new FileReader();
    reader.onload = function (e) {
      const img = new Image();
      img.onload = function () {
        const w = img.width;
        const h = img.height;
        if (w > 0 && h > 0) {
          const ratio = w / h;
          if (ratio < 0.9 || ratio > 1.1) {
            messages.push(
              `Image is not square (${w}×${h}). Square images (e.g. 600×600px) look best for categories.`
            );
          }
        }
        if (messages.length > 0) {
          warning.textContent = messages.join(' ');
          warning.style.display = 'block';
        }
      };
      img.src = e.target.result;
    };
    reader.readAsDataURL(file);
  });
})();
</script>

<!-- Sub-categories Modal -->
<div class="modal fade" id="subcategoriesModal" tabindex="-1" aria-labelledby="subcategoriesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-light border-bottom-0 py-3 px-4 rounded-top-4">
        <h5 class="modal-title fw-bold" id="subcategoriesModalLabel">
          Sub-categories of <span id="parentCategoryName" class="text-primary"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0" id="subcategoriesTable">
            <thead class="table-light">
              <tr>
                <th style="width: 50px;" class="ps-3">Img</th>
                <th>Sub-category Details</th>
                <th>Sub-Category Code</th>
                <th>No. of Designs</th>
                <th class="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody id="subcategoriesTableBody">
              <!-- Dynamically populated via JS -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const subcategoriesModalEl = document.getElementById('subcategoriesModal');
    if (subcategoriesModalEl) {
        const modalTitleName = document.getElementById('parentCategoryName');
        const tableBody = document.getElementById('subcategoriesTableBody');
        const modalObj = new bootstrap.Modal(subcategoriesModalEl);

        document.body.addEventListener('click', function(e) {
            const row = e.target.closest('.category-row');
            const btn = e.target.closest('.view-subcategories');
            
            if (btn || (row && !e.target.closest('a') && !e.target.closest('button') && !e.target.closest('form'))) {
                const triggerEl = btn || row;
                e.preventDefault();
                const parentName = triggerEl.getAttribute('data-category-name');
                const subcategoriesData = JSON.parse(triggerEl.getAttribute('data-subcategories') || '[]');
                
                modalTitleName.textContent = parentName;
                tableBody.innerHTML = '';

                if (subcategoriesData.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No sub-categories in this category.</td></tr>';
                } else {
                    subcategoriesData.forEach(function(sub) {
                        const imgHtml = sub.image_path 
                            ? `<img src="${sub.image_path}" style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid #ddd;" class="shadow-sm">`
                            : `<div style="width:40px;height:40px;background:#eee;border-radius:6px;" class="d-flex align-items-center justify-content-center text-muted small border">None</div>`;

                        const tr = document.createElement('tr');
                        tr.className = 'subcategory-row';
                        tr.style.cursor = 'pointer';
                        tr.setAttribute('data-subcategory-id', sub.id);
                        tr.setAttribute('data-subcategory-name', sub.name);
                        
                        if (sub.is_hidden === 1) {
                            tr.style.opacity = '0.65';
                        }

                        tr.innerHTML = `
                            <td class="ps-3 align-middle">${imgHtml}</td>
                            <td class="align-middle fw-bold text-dark">${sub.name}</td>
                            <td class="align-middle"><span class="badge bg-light border text-secondary px-2">${sub.code || '-'}</span></td>
                            <td class="align-middle fw-bold text-primary">${sub.design_count} Designs</td>
                            <td class="text-end pe-4 align-middle">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="category_edit.php?id=${sub.id}" class="btn btn-sm btn-outline-secondary shadow-sm">
                                        Edit
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_hide">
                                        <input type="hidden" name="id" value="${sub.id}">
                                        <button type="submit" class="btn btn-sm btn-outline-warning shadow-sm" title="${sub.is_hidden === 1 ? 'Show on index' : 'Hide from index'}">
                                            ${sub.is_hidden === 1 ? '<i class="bi bi-eye"></i> Show' : '<i class="bi bi-eye-slash"></i> Hide'}
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove the sub-category \\'${sub.name.replace(/'/g, "\\'")}\\'?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="${sub.id}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </td>
                        `;
                        tableBody.appendChild(tr);
                    });
                }
                modalObj.show();
            }
        });
    }
});
</script>

<!-- Add Category Wizard Modal -->
<div class="modal fade" id="addCategoryWizardModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="addCategoryWizardModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-light border-bottom-0 py-3 px-4 rounded-top-4">
        <h5 class="modal-title fw-bold" id="addCategoryWizardModalLabel">Add Category / Sub-category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="resetWizard()"></button>
      </div>
      
      <!-- Wizard Step 1: Choose Type -->
      <div id="wizardStep1" class="modal-body p-4">
        <p class="text-muted small mb-4">Choose the type of category you want to add:</p>
        <div class="form-check p-3 mb-3 border rounded-3" style="cursor:pointer;" onclick="document.getElementById('typeTopLevel').checked = true;">
          <input class="form-check-input ms-0 me-2" type="radio" name="category_type" id="typeTopLevel" value="top_level" checked>
          <label class="form-check-label fw-bold text-dark" for="typeTopLevel" style="cursor:pointer;">
            Top-level Category
          </label>
          <div class="form-text ms-4 small text-muted">Create a new primary category with no parent.</div>
        </div>
        <div class="form-check p-3 mb-4 border rounded-3" style="cursor:pointer;" onclick="document.getElementById('typeSubcategory').checked = true;">
          <input class="form-check-input ms-0 me-2" type="radio" name="category_type" id="typeSubcategory" value="subcategory">
          <label class="form-check-label fw-bold text-dark" for="typeSubcategory" style="cursor:pointer;">
            Sub-category
          </label>
          <div class="form-text ms-4 small text-muted">Create a sub-category under an existing top-level category.</div>
        </div>
        <div class="text-end">
          <button type="button" class="btn btn-primary px-4 rounded-pill" onclick="goToStep2()">Next <i class="bi bi-arrow-right ms-1"></i></button>
        </div>
      </div>

      <!-- Wizard Step 2: Form submission wrapper -->
      <form id="wizardForm" method="post" enctype="multipart/form-data" action="categories.php">
        
        <!-- Step 2: Top-level Form -->
        <div id="wizardStep2TopLevel" class="modal-body p-4" style="display: none;">
          <h6 class="fw-bold text-dark mb-3"><i class="bi bi-folder-plus text-primary me-2"></i>New Top-level Category</h6>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-muted">Name</label>
            <input type="text" name="name_top" class="form-control" placeholder="e.g. Gold Jewellery" id="topLevelName" list="existingCategoryNames">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-muted">Category Code</label>
            <input type="text" name="code_top" class="form-control" placeholder="e.g. GOLD" id="topLevelCode" list="existingCategoryCodes">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-muted">Image (optional)</label>
            <input type="file" name="image_top" id="categoryImageInputTop" class="form-control" accept="image/*">
            <div id="categoryImageWarningTop" class="form-text text-danger" style="display:none;"></div>
            <div class="form-text small text-muted mt-1">Use a clean square image under 400KB.</div>
          </div>
          <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" onclick="goBackToStep1()"><i class="bi bi-arrow-left me-1"></i> Back</button>
            <button type="submit" class="btn btn-primary px-4 rounded-pill">Save</button>
          </div>
        </div>

        <!-- Step 2: Sub-category Form -->
        <div id="wizardStep2Subcategory" class="modal-body p-4" style="display: none;">
          <h6 class="fw-bold text-dark mb-3"><i class="bi bi-diagram-3 text-primary me-2"></i>New Sub-category</h6>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-muted">Parent Category</label>
            <select name="parent_id" class="form-select" id="subCategoryParent">
              <option value="" disabled selected>-- Select Parent Category --</option>
              <?php foreach ($allCategories as $cat): ?>
                <?php if (empty($cat['parent_id'])): ?>
                  <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-muted">Name</label>
            <input type="text" name="name_sub" class="form-control" placeholder="e.g. Rings" id="subCategoryName" list="existingCategoryNames">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-muted">Category Code</label>
            <input type="text" name="code_sub" class="form-control" placeholder="e.g. RING" id="subCategoryCode" list="existingCategoryCodes">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold text-muted">Image (optional)</label>
            <input type="file" name="image_sub" id="categoryImageInputSub" class="form-control" accept="image/*">
            <div id="categoryImageWarningSub" class="form-text text-danger" style="display:none;"></div>
            <div class="form-text small text-muted mt-1">Use a clean square image under 400KB.</div>
          </div>
          <div class="d-flex justify-content-between mt-4">
            <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" onclick="goBackToStep1()"><i class="bi bi-arrow-left me-1"></i> Back</button>
            <button type="submit" class="btn btn-primary px-4 rounded-pill">Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function goToStep2() {
    const isTopLevel = document.getElementById('typeTopLevel').checked;
    const modalTitle = document.getElementById('addCategoryWizardModalLabel');
    document.getElementById('wizardStep1').style.display = 'none';
    
    if (isTopLevel) {
        modalTitle.textContent = "Add Main Category";
        document.getElementById('wizardStep2TopLevel').style.display = 'block';
        document.getElementById('topLevelName').required = true;
        document.getElementById('topLevelCode').required = true;
        document.getElementById('subCategoryParent').required = false;
        document.getElementById('subCategoryName').required = false;
        document.getElementById('subCategoryCode').required = false;
    } else {
        modalTitle.textContent = "Add Sub-Category";
        document.getElementById('wizardStep2Subcategory').style.display = 'block';
        document.getElementById('topLevelName').required = false;
        document.getElementById('topLevelCode').required = false;
        document.getElementById('subCategoryParent').required = true;
        document.getElementById('subCategoryName').required = true;
        document.getElementById('subCategoryCode').required = true;
    }
}

function goBackToStep1() {
    document.getElementById('addCategoryWizardModalLabel').textContent = "Add Category / Sub-category";
    document.getElementById('wizardStep2TopLevel').style.display = 'none';
    document.getElementById('wizardStep2Subcategory').style.display = 'none';
    document.getElementById('wizardStep1').style.display = 'block';
}

function resetWizard() {
    goBackToStep1();
    document.getElementById('wizardForm').reset();
}

// Add client-side validation warning listeners for new image inputs in wizard
(function() {
    const inputs = [
        { inputId: 'categoryImageInputTop', warningId: 'categoryImageWarningTop' },
        { inputId: 'categoryImageInputSub', warningId: 'categoryImageWarningSub' }
    ];

    inputs.forEach(item => {
        const input = document.getElementById(item.inputId);
        const warning = document.getElementById(item.warningId);
        if (!input || !warning) return;

        input.addEventListener('change', function() {
            warning.style.display = 'none';
            warning.textContent = '';
            const file = this.files && this.files[0];
            if (!file) return;

            const messages = [];
            const sizeKB = file.size / 1024;
            if (sizeKB > 400) {
                messages.push(`Large image (${sizeKB.toFixed(0)} KB). Keep it under 400 KB.`);
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const w = img.width;
                    const h = img.height;
                    if (w > 0 && h > 0) {
                        const ratio = w / h;
                        if (ratio < 0.9 || ratio > 1.1) {
                            messages.push(`Image is not square (${w}×${h}). Square images look best.`);
                        }
                    }
                    if (messages.length > 0) {
                        warning.textContent = messages.join(' ');
                        warning.style.display = 'block';
                    }
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    });
})();

// Disable dropdown on click, only show when typing
(function() {
    const fields = [
        { id: 'topLevelName', datalistId: 'existingCategoryNames' },
        { id: 'topLevelCode', datalistId: 'existingCategoryCodes' },
        { id: 'subCategoryName', datalistId: 'existingCategoryNames' },
        { id: 'subCategoryCode', datalistId: 'existingCategoryCodes' }
    ];

    fields.forEach(field => {
        const input = document.getElementById(field.id);
        if (!input) return;

        // Remove datalist reference initially to prevent list opening on click/focus
        input.removeAttribute('list');

        input.addEventListener('input', function() {
            if (this.value.trim().length > 0) {
                this.setAttribute('list', field.datalistId);
            } else {
                this.removeAttribute('list');
            }
        });

        // Remove list reference on blur
        input.addEventListener('blur', function() {
            // Keep a tiny timeout to allow click on datalist item to execute first
            setTimeout(() => {
                this.removeAttribute('list');
            }, 200);
        });
    });
})();

// Listen for subcategory row clicks to show products
document.body.addEventListener('click', function(e) {
    const subRow = e.target.closest('.subcategory-row');
    // Ignore click if it happened inside edit or remove actions
    if (subRow && !e.target.closest('a') && !e.target.closest('button') && !e.target.closest('form')) {
        const subId = subRow.getAttribute('data-subcategory-id');
        const subName = subRow.getAttribute('data-subcategory-name');
        showSubcategoryProducts(subId, subName);
    }
});

function showSubcategoryProducts(subId, subName) {
    const productsModalEl = document.getElementById('subcategoryProductsModal');
    const modalTitleName = document.getElementById('productsSubcategoryName');
    const tableBody = document.getElementById('productsTableBody');
    if (!productsModalEl) return;
    
    const modalObj = new bootstrap.Modal(productsModalEl);
    modalTitleName.textContent = subName;
    tableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading products...</td></tr>';
    modalObj.show();
    
    fetch(`categories.php?action=get_subcategory_products&subcategory_id=${subId}`)
        .then(res => res.json())
        .then(products => {
            tableBody.innerHTML = '';
            if (products.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No products in this sub-category.</td></tr>';
            } else {
                products.forEach(p => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="ps-3 align-middle">${p.name}</td>
                        <td class="align-middle"><span class="badge bg-light border text-secondary px-2">${p.sku}</span></td>
                        <td class="align-middle">${parseFloat(p.per_piece_weight).toFixed(3)} g</td>
                        <td class="align-middle fw-bold text-end pe-4">${parseInt(p.total_stock_qty)} Pcs</td>
                    `;
                    tableBody.appendChild(row);
                });
            }
        })
        .catch(err => {
            tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">Failed to load products.</td></tr>';
        });
}

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('add_main') || urlParams.has('add_sub')) {
        const modal = new bootstrap.Modal(document.getElementById('addCategoryWizardModal'));
        
        if (urlParams.has('add_main')) {
            document.getElementById('typeTopLevel').checked = true;
        } else {
            document.getElementById('typeSubcategory').checked = true;
        }
        
        modal.show();
        
        // Wait for modal to render, then go to Step 2
        setTimeout(() => {
            goToStep2();
            if (urlParams.has('add_sub')) {
                const parentId = urlParams.get('parent_id');
                if (parentId) {
                    const parentSelect = document.getElementById('subCategoryParent');
                    if (parentSelect) parentSelect.value = parentId;
                }
            }
        }, 300);
    }
});
</script>

<!-- Datalists for autocomplete suggestions -->
<datalist id="existingCategoryNames">
  <?php foreach ($allCategories as $cat): ?>
    <option value="<?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>"></option>
  <?php endforeach; ?>
</datalist>

<datalist id="existingCategoryCodes">
  <?php foreach ($allCategories as $cat): ?>
    <?php if (!empty($cat['code'])): ?>
      <option value="<?= htmlspecialchars($cat['code'], ENT_QUOTES, 'UTF-8') ?>"></option>
    <?php endif; ?>
  <?php endforeach; ?>
</datalist>

<!-- Sub-category Products Modal -->
<div class="modal fade" id="subcategoryProductsModal" tabindex="-1" aria-labelledby="subcategoryProductsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-light border-bottom-0 py-3 px-4 rounded-top-4">
        <h5 class="modal-title fw-bold" id="subcategoryProductsModalLabel">
          Products in <span id="productsSubcategoryName" class="text-primary"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0" id="subcategoryProductsTable">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Product Group Name</th>
                <th>SKU / Code</th>
                <th>Avg Weight</th>
                <th class="text-end pe-4">Stock Qty</th>
              </tr>
            </thead>
            <tbody id="productsTableBody">
              <!-- Dynamically populated via JS -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require "templates/footer.php"; ?>


