<?php
require_once "auth_check.php";
require_once "role_management.php";

require_permission($pdo, 'products', 'edit');

$page_title = "Product Group Form";
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$designs = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if ($product) {
        $stmt = $pdo->prepare("SELECT * FROM product_designs WHERE product_id = ? ORDER BY id");
        $stmt->execute([$id]);
        $designs = $stmt->fetchAll();
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parent_sku = trim($_POST['sku'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $bulk_weight = (float)($_POST['bulk_total_weight'] ?? 0);
    $min_stock_level = (int)($_POST['min_stock_level'] ?? 0);
    
    $metal_type = trim($_POST['metal_type'] ?? '');
    $purity = trim($_POST['purity'] ?? '');
    $cost_price = (float)($_POST['cost_price'] ?? 0);
    $selling_price = (float)($_POST['selling_price'] ?? 0);

    if ($name === '') {
        $errors[] = 'Product Group Name is required';
    }

    $d_ids = $_POST['design_id'] ?? [];
    $d_skus = $_POST['design_sku'] ?? [];
    $d_names = $_POST['design_name'] ?? [];
    $d_qtys = $_POST['design_qty'] ?? [];
    $bulk_idxs = $_POST['bulk_image_index'] ?? [];
    $row_keys = $_POST['row_key'] ?? [];
    $d_hiddens = $_POST['design_hidden'] ?? [];
    
    $valid_designs = [];
    $total_qty = 0;
    
    for ($i = 0; $i < count($d_names); $i++) {
        $d_name = trim($d_names[$i]);
        if ($d_name === '') continue; 
        
        $d_qty = (int)($d_qtys[$i] ?? 0);
        $total_qty += $d_qty;
        
        $valid_designs[] = [
            'index' => $i,
            'id' => (int)($d_ids[$i] ?? 0),
            'bulk_idx' => isset($bulk_idxs[$i]) ? (int)$bulk_idxs[$i] : -1,
            'sku' => trim($d_skus[$i] ?? ''),
            'name' => $d_name,
            'qty' => $d_qty,
            'row_key' => $row_keys[$i] ?? $i,
            'is_hidden' => isset($d_hiddens[$i]) ? (int)$d_hiddens[$i] : 0
        ];
    }
    
    if (empty($valid_designs)) {
        $errors[] = "You must add at least one design to this product group.";
    }

    if (empty($errors)) {
        $per_piece_weight = $total_qty > 0 ? ($bulk_weight / $total_qty) : 0;
        
        $pdo->beginTransaction();
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE products SET sku=?, name=?, category_id=?, metal_type=?, purity=?, bulk_total_weight=?, per_piece_weight=?, cost_price=?, selling_price=?, total_stock_qty=?, min_stock_level=?, is_hidden=0 WHERE id=?");
                $stmt->execute([$parent_sku, $name, $category_id, $metal_type, $purity, $bulk_weight, $per_piece_weight, $cost_price, $selling_price, $total_qty, $min_stock_level, $id]);
                $product_id = $id;
            } else {
                $stmt = $pdo->prepare("INSERT INTO products (sku, name, category_id, metal_type, purity, bulk_total_weight, per_piece_weight, cost_price, selling_price, total_stock_qty, min_stock_level) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$parent_sku, $name, $category_id, $metal_type, $purity, $bulk_weight, $per_piece_weight, $cost_price, $selling_price, $total_qty, $min_stock_level]);
                $product_id = (int)$pdo->lastInsertId();
            }

            $processed_design_ids = [];
            
            foreach ($valid_designs as $vd) {
                $idx = $vd['index'];
                $bulk_idx = $vd['bulk_idx'];
                $d_id = $vd['id'];
                $row_key = $vd['row_key'];
                
                $uploaded_images = [];
                // Retrieve remaining existing images
                if (isset($_POST["existing_images_{$row_key}"])) {
                    $uploaded_images = $_POST["existing_images_{$row_key}"];
                }
                
                // Process Bulk Upload Image OR Individual Upload Image(s)
                if ($bulk_idx >= 0 && isset($_FILES['bulk_images']['tmp_name'][$bulk_idx]) && $_FILES['bulk_images']['error'][$bulk_idx] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['bulk_images']['name'][$bulk_idx], PATHINFO_EXTENSION));
                    if (!is_dir('uploads/products')) mkdir('uploads/products', 0777, true);
                    $newName = 'uploads/products/' . time() . '_' . rand(1000,9999) . '_bulk_' . $bulk_idx . '.' . $ext;
                    if (move_uploaded_file($_FILES['bulk_images']['tmp_name'][$bulk_idx], $newName)) {
                        $uploaded_images[] = $newName;
                    }
                } elseif (isset($_FILES["design_images_{$row_key}"])) {
                    $files = $_FILES["design_images_{$row_key}"];
                    if (is_array($files['name'])) {
                        for ($f = 0; $f < count($files['name']); $f++) {
                            if (isset($files['error'][$f]) && $files['error'][$f] === UPLOAD_ERR_OK) {
                                $ext = strtolower(pathinfo($files['name'][$f], PATHINFO_EXTENSION));
                                if (!is_dir('uploads/products')) mkdir('uploads/products', 0777, true);
                                $newName = 'uploads/products/' . time() . '_' . rand(1000,9999) . '_single_' . $row_key . '_' . $f . '.' . $ext;
                                if (move_uploaded_file($files['tmp_name'][$f], $newName)) {
                                    $uploaded_images[] = $newName;
                                }
                            }
                        }
                    } else {
                        if ($files['error'] === UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($files['name'], PATHINFO_EXTENSION));
                            if (!is_dir('uploads/products')) mkdir('uploads/products', 0777, true);
                            $newName = 'uploads/products/' . time() . '_' . rand(1000,9999) . '_single_' . $row_key . '.' . $ext;
                            if (move_uploaded_file($files['tmp_name'], $newName)) {
                                $uploaded_images[] = $newName;
                            }
                        }
                    }
                }

                $image_path = !empty($uploaded_images) ? implode(',', $uploaded_images) : null;

                if ($d_id > 0) {
                    $stmt = $pdo->prepare("UPDATE product_designs SET sku=?, design_name=?, stock_qty=?, image_path=?, is_hidden=? WHERE id=?");
                    $stmt->execute([$vd['sku'], $vd['name'], $vd['qty'], $image_path, $vd['is_hidden'], $d_id]);
                    $processed_design_ids[] = $d_id;
                } else {
                    $stmt = $pdo->prepare("INSERT INTO product_designs (product_id, sku, design_name, stock_qty, image_path, is_hidden) VALUES (?,?,?,?,?,?)");
                    $stmt->execute([$product_id, $vd['sku'], $vd['name'], $vd['qty'], $image_path, $vd['is_hidden']]);
                    $new_d_id = (int)$pdo->lastInsertId();
                    $processed_design_ids[] = $new_d_id;
                    
                    if ($vd['qty'] > 0) {
                        $pdo->prepare("INSERT INTO stock_movements (product_id, design_id, movement_type, quantity, movement_date, vendor_name, note) VALUES (?, ?, 'IN', ?, ?, 'Opening Stock', '')")
                            ->execute([$product_id, $new_d_id, $vd['qty'], date('Y-m-d')]);
                    }
                }
            }
            
            if ($id && !empty($processed_design_ids)) {
                $placeholders = str_repeat('?,', count($processed_design_ids) - 1) . '?';
                $delStmt = $pdo->prepare("DELETE FROM product_designs WHERE product_id = ? AND id NOT IN ($placeholders)");
                $params = array_merge([$id], $processed_design_ids);
                $delStmt->execute($params);
            }
            
            $pdo->commit();
            header("Location: products.php");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

require "templates/header.php";
?>

<style>
/* View Mode Styles */
.btn-view-toggle {
    background-color: #ffffff;
    color: #000000;
    border: 1px solid #000000;
}
.btn-view-toggle:hover,
.btn-check:checked + .btn-view-toggle {
    background-color: #000000;
    color: #ffffff;
    border-color: #000000;
}

#designsContainer {
    transition: all 0.3s ease;
}

#designsContainer.view-collage {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

#designsContainer.view-collage .design-row {
    width: calc(25% - 15px);
    min-width: 220px;
    flex-direction: column;
    align-items: stretch !important;
    position: relative;
    padding: 15px !important;
    padding-top: 40px !important;
    border-color: #ddd !important;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08) !important;
    background: #fff !important;
}

#designsContainer.view-collage .design-row > div[class*="col-"] {
    width: 100% !important;
    max-width: 100% !important;
    flex: none !important;
    margin-bottom: 10px;
}

/* Hide SKU and Image Upload fields in Collage view to make it a neat card */
#designsContainer.view-collage .design-row > div:nth-of-type(1),
#designsContainer.view-collage .design-row > div:nth-of-type(4) {
    display: none !important;
}

#designsContainer.view-collage .image-preview-area {
    order: -1;
    width: 100% !important;
    height: auto !important;
    aspect-ratio: 1 / 1 !important;
    background: #f8f9fa;
    border: none;
    border-radius: 5px;
    margin-bottom: 15px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 0 !important;
    position: relative;
}

#designsContainer.view-collage .thumb-container {
    flex: 1;
    height: 100%;
    margin: 0 !important;
    padding: 2px;
    display: flex;
    justify-content: center;
    align-items: center;
}

#designsContainer.view-collage .image-preview-area img {
    max-height: 100% !important;
    max-width: 100% !important;
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
    cursor: pointer;
    transition: transform 0.2s;
}

#designsContainer.view-collage .image-preview-area img:hover {
    transform: scale(1.05);
}

#designsContainer.view-collage .remove-design-wrap {
    position: absolute;
    top: 10px;
    right: 10px;
    width: auto !important;
    margin: 0;
    order: -2;
    z-index: 2;
}

#designsContainer.view-collage label {
    margin-bottom: 2px;
    font-size: 0.85rem;
    color: #6c757d;
}

@media (max-width: 1200px) {
    #designsContainer.view-collage .design-row { width: calc(33.333% - 15px); }
}
@media (max-width: 992px) {
    #designsContainer.view-collage .design-row { width: calc(50% - 15px); }
}
@media (max-width: 576px) {
    #designsContainer.view-collage .design-row { width: 100%; }
}

/* Image cursor for List view as well */
#designsContainer .image-preview-area img {
    cursor: pointer;
}
</style>

<div class="card shadow-sm border-0 mb-4">
  <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
    <strong class="fs-5 mb-0"><?= $id ? 'Edit Product Group (Batch)' : 'Add Product Group (Batch)' ?></strong>
    <div class="d-flex gap-2">
        <a href="products.php" class="btn btn-light border px-4">Cancel</a>
        <button form="productForm" class="btn btn-primary px-4 fw-semibold text-white">Save Product & Designs</button>
    </div>
  </div>
  <div class="card-body">
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
           <div><i class="bi bi-exclamation-triangle-fill"></i> <?= e($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" id="productForm" enctype="multipart/form-data">
        <h6 class="text-primary border-bottom pb-2">1. Main Group Details</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Batch / Parent SKU</label>
                <input type="text" name="sku" class="form-control" value="<?= e($product['sku'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Group Name (Main Category) *</label>
                <select name="name" id="mainCategorySelect" class="form-select" required>
                    <option value="">-- Select Main Category --</option>
                    <?php 
                    $main_categories = [];
                    $sub_by_parent = [];
                    $found_main = false;
                    foreach ($categories as $c) {
                        if (empty($c['parent_id'])) {
                            $main_categories[] = $c;
                            if (isset($product['name']) && $product['name'] === $c['name']) {
                                $found_main = true;
                            }
                        } else {
                            $sub_by_parent[$c['parent_id']][] = $c;
                        }
                    }
                    if (isset($product['name']) && $product['name'] !== '' && !$found_main) {
                        echo '<option value="' . e($product['name']) . '" data-id="" selected>' . e($product['name']) . '</option>';
                    }
                    foreach ($main_categories as $mc): ?>
                        <option value="<?= e($mc['name']) ?>" data-id="<?= $mc['id'] ?>" <?= (isset($product['name']) && $product['name'] === $mc['name']) ? 'selected' : '' ?>><?= e($mc['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="__ADD_NEW__" class="text-primary fw-bold">+ Add New Main Category</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Sub-Category</label>
                <select name="category_id" id="subCategorySelect" class="form-select">
                    <option value="" data-code="" data-name="">-- None --</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Bulk Total Weight (gm)</label>
                <input type="number" step="0.001" name="bulk_total_weight" class="form-control" value="<?= e($product['bulk_total_weight'] ?? '') ?>">
                <div class="form-text">Total mass. Will be divided evenly by total quantity.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Metal Type</label>
                <input type="text" name="metal_type" class="form-control" value="<?= e($product['metal_type'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Purity</label>
                <input type="text" name="purity" class="form-control" value="<?= e($product['purity'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Min Stock Level Alert</label>
                <input type="number" name="min_stock_level" class="form-control" value="<?= e($product['min_stock_level'] ?? 0) ?>">
            </div>
        </div>

        <h6 class="text-primary border-bottom pb-2 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <span>2. Design Variations</span>
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check" name="view_mode" id="view_list" value="list" checked onchange="toggleViewMode('list')">
                    <label class="btn btn-view-toggle" for="view_list"><i class="bi bi-list-ul"></i> List</label>

                    <input type="radio" class="btn-check" name="view_mode" id="view_collage" value="collage" onchange="toggleViewMode('collage')">
                    <label class="btn btn-view-toggle" for="view_collage"><i class="bi bi-grid"></i> Collage</label>
                </div>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap justify-content-end">
                <div class="input-group input-group-sm" style="max-width: 180px;">
                    <input type="number" id="bulkQtyInput" class="form-control" placeholder="Qty">
                    <button type="button" class="btn btn-outline-secondary fw-semibold" onclick="applyBulkQty()" title="Apply this qty to all designs">Apply Qty</button>
                </div>
                <!-- Hidden Bulk Upload Input -->
                <input type="file" id="bulkImageUpload" name="bulk_images[]" multiple accept="image/*" style="display: none;" onchange="handleBulkImages(this)">
                <button type="button" class="btn btn-sm btn-outline-info fw-semibold" onclick="document.getElementById('bulkImageUpload').click()">
                    <i class="bi bi-images"></i> Bulk Add Images
                </button>
                <button type="button" class="btn btn-sm btn-primary" id="addDesignBtn">+ Add Empty Design</button>
            </div>
        </h6>
        
        <div id="designsContainer" class="mb-4 view-list">
            <?php 
            if (empty($designs)) {
                $designs = [['id'=>0, 'sku'=>'', 'design_name'=>'', 'stock_qty'=>0, 'image_path'=>'', 'is_hidden'=>0]];
            }
            foreach ($designs as $idx => $d): 
            ?>
            <div class="row g-2 align-items-end border p-2 mb-2 bg-light rounded design-row" data-row-idx="<?= $idx ?>" style="<?= !empty($d['is_hidden']) ? 'opacity: 0.6;' : '' ?>">
                <input type="hidden" name="design_id[]" value="<?= $d['id'] ?>">
                <input type="hidden" name="bulk_image_index[]" value="-1">
                <input type="hidden" name="row_key[]" value="<?= $idx ?>">
                <input type="hidden" name="design_hidden[]" class="design-hidden-input" value="<?= !empty($d['is_hidden']) ? 1 : 0 ?>">
                <div class="col-md-2">
                    <label class="form-label small text-secondary">Design SKU</label>
                    <input type="text" name="design_sku[]" class="form-control form-control-sm design-sku-input" value="<?= e($d['sku']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Design Name *</label>
                    <input type="text" name="design_name[]" class="form-control form-control-sm design-name-input" required value="<?= e($d['design_name']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-primary">Initial Qty</label>
                    <input type="number" name="design_qty[]" class="form-control form-control-sm border-primary" value="<?= e($d['stock_qty']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary">Image Upload (Multiple)</label>
                    <input type="file" name="design_images_<?= $idx ?>[]" class="form-control form-control-sm" accept="image/*" multiple>
                </div>
                <div class="col-md-1 text-center d-flex flex-wrap gap-1 align-items-center justify-content-center image-preview-area">
                    <?php if ($d['image_path']): ?>
                        <?php foreach (explode(',', $d['image_path']) as $img_path): if(trim($img_path) === '') continue; ?>
                            <div class="position-relative d-inline-block thumb-container" style="margin-right: 2px;">
                                <img src="<?= e($img_path) ?>" style="height:40px; border-radius:4px;">
                                <button type="button" class="btn btn-danger btn-xs position-absolute top-0 end-0 p-0 d-flex align-items-center justify-content-center delete-thumb" style="width:14px; height:14px; font-size:10px; line-height:1; border-radius:50%; transform: translate(50%, -50%);" onclick="removeThumbnail(this)">x</button>
                                <input type="hidden" name="existing_images_<?= $idx ?>[]" value="<?= e($img_path) ?>">
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="height:40px; width:40px; border-radius:4px; background:#ddd;" class="d-flex align-items-center justify-content-center text-muted small">No Img</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-1 text-end pb-1 remove-design-wrap d-flex align-items-center justify-content-end gap-2">
                    <button type="button" class="btn btn-sm <?= !empty($d['is_hidden']) ? 'btn-warning' : 'btn-outline-secondary' ?> toggle-hide-design" title="<?= !empty($d['is_hidden']) ? 'Unhide Design' : 'Hide Design' ?>">
                        <i class="bi <?= !empty($d['is_hidden']) ? 'bi-eye-slash-fill' : 'bi-eye' ?>"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-design">X</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

      <div class="col-12 mt-4 pt-3 border-top">
        <div class="d-flex justify-content-end gap-2">
            <a href="products.php" class="btn btn-light border px-4">Cancel</a>
            <button class="btn btn-primary px-4 fw-semibold text-white">Save Product & Designs</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
let designIdx = 1000;

const subCategoriesByParent = <?= json_encode($sub_by_parent) ?>;
const currentSubCategoryId = <?= isset($product['category_id']) ? (int)$product['category_id'] : 'null' ?>;

document.getElementById('addDesignBtn').addEventListener('click', function() {
    addEmptyRow();
});

function getSelectedCategory() {
    const select = document.getElementById('subCategorySelect');
    if (select && select.selectedIndex > 0) {
        const option = select.options[select.selectedIndex];
        return {
            code: option.getAttribute('data-code') || '',
            name: option.getAttribute('data-name') || ''
        };
    }
    return { code: '', name: '' };
}

function addEmptyRow() {
    designIdx++;
    const cat = getSelectedCategory();
    const tpl = `
    <div class="row g-2 align-items-end border p-2 mb-2 bg-light rounded design-row" data-row-idx="${designIdx}">
        <input type="hidden" name="design_id[]" value="0">
        <input type="hidden" name="bulk_image_index[]" value="-1">
        <input type="hidden" name="row_key[]" value="${designIdx}">
        <input type="hidden" name="design_hidden[]" class="design-hidden-input" value="0">
        <div class="col-md-2">
            <label class="form-label small text-secondary">Design SKU</label>
            <input type="text" name="design_sku[]" class="form-control form-control-sm design-sku-input" value="">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-semibold">Design Name *</label>
            <input type="text" name="design_name[]" class="form-control form-control-sm design-name-input" required value="${cat.name}">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold text-primary">Initial Qty</label>
            <input type="number" name="design_qty[]" class="form-control form-control-sm border-primary" value="0">
        </div>
        <div class="col-md-3">
            <label class="form-label small text-secondary">Image Upload (Multiple)</label>
            <input type="file" name="design_images_${designIdx}[]" class="form-control form-control-sm" accept="image/*" multiple>
        </div>
        <div class="col-md-1 text-center image-preview-area">
            <div style="height:40px; border-radius:4px; background:#ddd;" class="d-flex align-items-center justify-content-center text-muted small">Empty</div>
        </div>
        <div class="col-md-1 text-end pb-1 remove-design-wrap d-flex align-items-center justify-content-end gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary toggle-hide-design" title="Hide Design">
                <i class="bi bi-eye"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger remove-design">X</button>
        </div>
    </div>`;
    document.getElementById('designsContainer').insertAdjacentHTML('beforeend', tpl);
    renumberDesignSKUs();
}

function handleBulkImages(input) {
    if (!input.files || input.files.length === 0) return;
    
    const container = document.getElementById('designsContainer');
    const existingRows = container.querySelectorAll('.design-row');
    const cat = getSelectedCategory();
    
    // Clear out the default empty row if it hasn't been touched yet
    if (existingRows.length === 1) {
        let nameField = existingRows[0].querySelector('input[name="design_name[]"]').value;
        let qtyField = existingRows[0].querySelector('input[name="design_qty[]"]').value;
        if (nameField === '' && qtyField === '0') {
            existingRows[0].remove();
        }
    }
    
    Array.from(input.files).forEach((file, index) => {
        designIdx++;
        const thumbUrl = URL.createObjectURL(file);
        const defaultName = cat.name ? cat.name : file.name.replace(/\.[^/.]+$/, ""); // Strip file extension
        
        const tpl = `
        <div class="row g-2 align-items-end border p-2 mb-2 rounded design-row shadow-sm border-info" style="background-color: #f8ffff;" data-row-idx="${designIdx}">
            <input type="hidden" name="design_id[]" value="0">
            <input type="hidden" name="bulk_image_index[]" value="${index}">
            <input type="hidden" name="row_key[]" value="${designIdx}">
            <input type="hidden" name="design_hidden[]" class="design-hidden-input" value="0">
            <div class="col-md-2">
                <label class="form-label small text-secondary">Design SKU</label>
                <input type="text" name="design_sku[]" class="form-control form-control-sm design-sku-input" value="">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Design Name *</label>
                <input type="text" name="design_name[]" class="form-control form-control-sm design-name-input" required value="${defaultName}">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-primary">Initial Qty</label>
                <input type="number" name="design_qty[]" class="form-control form-control-sm border-primary" value="0">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-success fw-bold"><i class="bi bi-check-circle"></i> Bulk Image Scheduled</label>
                <input type="file" name="design_images_${designIdx}[]" style="display:none;">
                <div class="small text-muted text-truncate" title="${file.name}">${file.name}</div>
            </div>
            <div class="col-md-1 text-center image-preview-area">
                <img src="${thumbUrl}" style="height:40px; border-radius:4px; object-fit:cover; border: 1px solid #ddd;">
            </div>
            <div class="col-md-1 text-end pb-1 remove-design-wrap d-flex align-items-center justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary toggle-hide-design" title="Hide Design">
                    <i class="bi bi-eye"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger remove-design">X</button>
            </div>
        </div>`;
        container.insertAdjacentHTML('beforeend', tpl);
    });
    renumberDesignSKUs();
}

function removeThumbnail(btn) {
    const thumbContainer = btn.closest('.thumb-container');
    const previewArea = thumbContainer.closest('.image-preview-area');
    thumbContainer.remove();
    if (previewArea.querySelectorAll('.thumb-container').length === 0) {
        previewArea.innerHTML = '<div style="height:40px; width:40px; border-radius:4px; background:#ddd;" class="d-flex align-items-center justify-content-center text-muted small">No Img</div>';
    }
}

document.getElementById('designsContainer').addEventListener('click', function(e) {
    const toggleBtn = e.target.closest('.toggle-hide-design');
    if (toggleBtn) {
        const row = toggleBtn.closest('.design-row');
        const hiddenInput = row.querySelector('.design-hidden-input');
        const isHidden = parseInt(hiddenInput.value) === 1;
        
        if (isHidden) {
            hiddenInput.value = '0';
            toggleBtn.classList.remove('btn-warning');
            toggleBtn.classList.add('btn-outline-secondary');
            toggleBtn.innerHTML = '<i class="bi bi-eye"></i>';
            toggleBtn.setAttribute('title', 'Hide Design');
            row.style.opacity = '1';
        } else {
            hiddenInput.value = '1';
            toggleBtn.classList.remove('btn-outline-secondary');
            toggleBtn.classList.add('btn-warning');
            toggleBtn.innerHTML = '<i class="bi bi-eye-slash-fill"></i>';
            toggleBtn.setAttribute('title', 'Unhide Design');
            row.style.opacity = '0.6';
        }
        return;
    }

    if (e.target.classList.contains('remove-design')) {
        e.target.closest('.design-row').remove();
        renumberDesignSKUs();
    }
});

function renumberDesignSKUs() {
    const skuInputs = document.querySelectorAll('.design-sku-input');
    skuInputs.forEach((input, index) => {
        input.value = index + 1;
    });
}

document.getElementById('mainCategorySelect').addEventListener('change', function() {
    if (this.value === '__ADD_NEW__') {
        window.location.href = 'categories.php?add_main=1';
        return;
    }
    
    const selectedOption = this.options[this.selectedIndex];
    const mainId = selectedOption.getAttribute('data-id');
    const subSelect = document.getElementById('subCategorySelect');
    
    subSelect.innerHTML = '<option value="" data-code="" data-name="">-- None --</option>';
    
    if (mainId && subCategoriesByParent[mainId]) {
        subCategoriesByParent[mainId].forEach(sub => {
            const option = document.createElement('option');
            option.value = sub.id;
            option.setAttribute('data-code', sub.code || '');
            option.setAttribute('data-name', sub.name || '');
            option.textContent = sub.name;
            if (currentSubCategoryId && parseInt(sub.id) === currentSubCategoryId) {
                option.selected = true;
            }
            subSelect.appendChild(option);
        });
    }
    
    if (mainId) {
        const addOption = document.createElement('option');
        addOption.value = '__ADD_NEW__';
        addOption.className = 'text-primary fw-bold';
        addOption.textContent = '+ Add New Subcategory';
        subSelect.appendChild(addOption);
    }
});

document.getElementById('subCategorySelect').addEventListener('change', function() {
    if (this.value === '__ADD_NEW__') {
        const mainSelect = document.getElementById('mainCategorySelect');
        const mainId = mainSelect.options[mainSelect.selectedIndex].getAttribute('data-id');
        window.location.href = 'categories.php?add_sub=1&parent_id=' + mainId;
        return;
    }
    
    const cat = getSelectedCategory();
    if (cat.name || cat.code) {
        // Apply Parent SKU
        const parentSkuInput = document.querySelector('input[name="sku"]');
        if (parentSkuInput) {
            parentSkuInput.value = cat.code;
        }
        
        const nameInputs = document.querySelectorAll('.design-name-input');
        nameInputs.forEach(input => {
            input.value = cat.name;
        });
    }
});

// Trigger change on load if editing
document.addEventListener('DOMContentLoaded', function() {
    const mainSelect = document.getElementById('mainCategorySelect');
    if (mainSelect.value) {
        mainSelect.dispatchEvent(new Event('change'));
    }
});

function toggleViewMode(mode) {
    const container = document.getElementById('designsContainer');
    if (mode === 'collage') {
        container.classList.remove('view-list');
        container.classList.add('view-collage');
    } else {
        container.classList.remove('view-collage');
        container.classList.add('view-list');
    }
}

document.addEventListener('click', function(e) {
    if (e.target.tagName === 'IMG' && e.target.closest('.image-preview-area') && !e.target.closest('.delete-thumb')) {
        const src = e.target.src;
        document.getElementById('previewModalImage').src = src;
        const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
        modal.show();
    }
});

function applyBulkQty() {
    const qty = document.getElementById('bulkQtyInput').value;
    if (qty === '') return;
    const qtyInputs = document.querySelectorAll('input[name="design_qty[]"]');
    qtyInputs.forEach(input => {
        input.value = qty;
    });
}
</script>

<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center pt-0 pb-4">
        <img id="previewModalImage" src="" alt="Preview" style="max-width: 100%; max-height: 80vh; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
      </div>
    </div>
  </div>
</div>

<?php require "templates/footer.php"; ?>
