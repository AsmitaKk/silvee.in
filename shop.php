<?php
require_once "config.php";
require_once "functions.php";
require_once "store_header.php";

$cat_id = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch WhatsApp Number dynamically
$whatsapp_number = $themeSettings['whatsapp_number'] ?? "918208719194";

// Determine current view state
$is_root = ($cat_id === 0 && empty($search));
$sub_categories = [];
$designs = [];
$current_category = null;

// Fetch category IDs that contain the top 15 newest designs
$new_arrival_category_ids = [];
try {
    $stmt_new = $pdo->query("
        SELECT DISTINCT p.category_id 
        FROM product_designs d 
        JOIN products p ON d.product_id = p.id 
        WHERE d.is_hidden = 0
        ORDER BY d.id DESC 
        LIMIT 15
    ");
    while ($row_new = $stmt_new->fetch()) {
        if ($row_new['category_id']) {
            $new_arrival_category_ids[] = (int)$row_new['category_id'];
            
            // Also include parent category of this sub-category if it exists
            $stmt_parent = $pdo->prepare("SELECT parent_id FROM categories WHERE id = ?");
            $stmt_parent->execute([$row_new['category_id']]);
            $p_row = $stmt_parent->fetch();
            if ($p_row && $p_row['parent_id']) {
                $new_arrival_category_ids[] = (int)$p_row['parent_id'];
            }
        }
    }
    $new_arrival_category_ids = array_unique($new_arrival_category_ids);
} catch (Exception $e) {}

if (!empty($search)) {
    // Perform search across product_designs design_name and products name/sku
    $stmt = $pdo->prepare("
        SELECT d.*, p.name as product_name, p.sku as product_sku, c.name as subcategory_name
        FROM product_designs d
        JOIN products p ON d.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE d.stock_qty > 0 AND d.is_hidden = 0 AND (d.design_name LIKE :search OR p.name LIKE :search OR d.sku LIKE :search)
        ORDER BY p.name ASC, d.design_name ASC
    ");
    $stmt->execute([':search' => '%' . $search . '%']);
    $designs = $stmt->fetchAll();
} else if ($is_root) {
    // Fetch Top Level Categories for search
    $stmt = $pdo->query("
        SELECT c.id, c.name, c.image_path, c.parent_id,
               (SELECT COUNT(sub.id) FROM categories sub WHERE sub.parent_id = c.id AND (sub.is_hidden IS NULL OR sub.is_hidden = 0)) as subcat_count,
               (SELECT COUNT(d.id) 
                FROM product_designs d 
                JOIN products p ON d.product_id = p.id 
                WHERE p.category_id IN (SELECT id FROM categories WHERE parent_id = c.id) AND d.stock_qty > 0 AND d.is_hidden = 0) as design_count
        FROM categories c
        WHERE (c.parent_id IS NULL OR c.parent_id = 0) AND (c.is_hidden IS NULL OR c.is_hidden = 0)
        ORDER BY c.name ASC
    ");
    $sub_categories = $stmt->fetchAll();
} else {
    // Fetch current category info
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$cat_id]);
    $current_category = $stmt->fetch();
    
    // Fetch immediate child sub-categories (if any)
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.image_path, c.parent_id,
               (SELECT COUNT(sub.id) FROM categories sub WHERE sub.parent_id = c.id AND (sub.is_hidden IS NULL OR sub.is_hidden = 0)) as subcat_count,
               (SELECT COUNT(d.id) 
                FROM product_designs d 
                JOIN products p ON d.product_id = p.id 
                WHERE p.category_id = c.id AND d.stock_qty > 0 AND d.is_hidden = 0) as design_count
        FROM categories c
        WHERE c.parent_id = ? AND (c.is_hidden IS NULL OR c.is_hidden = 0)
        ORDER BY c.name ASC
    ");
    $stmt->execute([$cat_id]);
    $sub_categories = $stmt->fetchAll();
    
    // If no sub-categories, it's a leaf node. Fetch the actual product designs!
    if (empty($sub_categories)) {
        $stmt = $pdo->prepare("
            SELECT d.*, p.name as product_name, p.sku as product_sku, p.per_piece_weight as weight, c.name as subcategory_name
            FROM product_designs d
            JOIN products p ON d.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.category_id = ? AND d.stock_qty > 0 AND d.is_hidden = 0
            ORDER BY p.name ASC, d.design_name ASC
        ");
        $stmt->execute([$cat_id]);
        $designs = $stmt->fetchAll();
        
        // Fetch parent category for breadcrumbs
        $parent_category = null;
        if ($current_category && $current_category['parent_id']) {
            $stmt_p = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
            $stmt_p->execute([$current_category['parent_id']]);
            $parent_category = $stmt_p->fetch();
        }
        
        // Calculate cart totals
        $cart_total_qty = 0;
        $cart_total_weight = 0.000;
        if (!empty($_SESSION['cart'])) {
            $cart_ids = array_keys($_SESSION['cart']);
            $cart_placeholders = str_repeat('?,', count($cart_ids) - 1) . '?';
            $stmt_cart = $pdo->prepare("
                SELECT d.id, p.per_piece_weight
                FROM product_designs d
                JOIN products p ON d.product_id = p.id
                WHERE d.id IN ($cart_placeholders)
            ");
            $stmt_cart->execute($cart_ids);
            $cart_designs_w = $stmt_cart->fetchAll();
            
            foreach ($cart_designs_w as $cd) {
                $qty = $_SESSION['cart'][$cd['id']]['qty'];
                $cart_total_qty += $qty;
                $cart_total_weight += $qty * $cd['per_piece_weight'];
            }
        }
    }
}
?>

<style>
.square-category-card {
    background: #FFFFFF;
    border: 1px solid #FAF8F5;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex;
    flex-direction: column;
    height: 100%;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
}
.square-category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.06);
    border-color: var(--gold-accent);
}
.square-category-image-wrapper {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    background-color: #FAF8F5;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 1.25rem;
}
.square-category-image-wrapper img {
    max-width: 95%;
    max-height: 95%;
    object-fit: contain;
    mix-blend-mode: multiply;
    transition: transform 0.4s ease;
}
.square-category-card:hover .square-category-image-wrapper img {
    transform: scale(1.08);
}
.square-category-info {
    padding: 1rem;
    text-align: center;
    background: #FFFFFF;
    border-top: 1px solid #F6F3EB;
}
.square-category-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: #1A2522;
    margin-bottom: 0.25rem;
    font-family: var(--category-font), sans-serif;
}
.square-category-count {
    font-size: 0.75rem;
    color: #8A9692;
    font-weight: 500;
    font-family: var(--category-font), sans-serif;
}
/* Style search & filters */
#categorySearchInput::placeholder {
    color: #A0ABA7;
}
.filter-btn-group .btn {
    font-size: 0.78rem;
    font-weight: 600;
    transition: all 0.2s ease;
    border-color: #E2DDD3;
    color: #1A2522;
}
.filter-btn-group .btn:hover {
    background-color: var(--forest-green);
    border-color: var(--forest-green);
    color: #FFF;
}

</style>

<div class="container py-3 py-md-5 my-2 my-md-4 min-vh-100">

    <!-- Breadcrumbs -->
    <?php if (!$is_root): ?>
    <nav aria-label="breadcrumb" class="mb-3 mb-md-4">
      <ol class="breadcrumb bg-transparent p-0 m-0" style="font-size: 0.85rem; font-family: var(--paragraph-font), sans-serif; letter-spacing: 0.5px;">
        <li class="breadcrumb-item"><a href="index.php" class="text-muted text-decoration-none">Home</a></li>
        <?php if(!empty($search)): ?>
            <li class="breadcrumb-item active text-dark fw-bold" aria-current="page">Search Results</li>
        <?php elseif($current_category): ?>
            <?php if (isset($parent_category) && $parent_category): ?>
                <li class="breadcrumb-item"><a href="shop.php?cat=<?= $parent_category['id'] ?>" class="text-muted text-decoration-none"><?= e($parent_category['name']) ?></a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active text-dark fw-bold" aria-current="page"><?= e($current_category['name']) ?></li>
        <?php endif; ?>
      </ol>
    </nav>
    <?php endif; ?>

    <!-- Search Results View -->
    <?php if(!empty($search)): ?>
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-light">
            <h3 class="mb-0 fw-bold" style="font-family: var(--category-font), sans-serif; font-size: clamp(1.1rem, 2vw, 1.6rem);">
                Search Results for "<?= htmlspecialchars($search) ?>"
            </h3>
            <span class="badge bg-dark rounded-pill px-3 py-2 fw-medium"><?= count($designs) ?> Designs Found</span>
        </div>
        
        <?php if (empty($designs)): ?>
            <div class="text-center py-5 mt-4 border rounded-4 bg-white shadow-sm">
                <i class="bi bi-search display-1 text-muted opacity-25 mb-4 d-block"></i>
                <h3 class="fw-bold font-serif">No Designs Match Your Search</h3>
                <p class="text-muted">Try searching for other keywords (e.g. Ring, Kada, Earring).</p>
                <a href="shop.php" class="btn btn-forest rounded-pill px-4 mt-3">Browse All Collections</a>
            </div>
        <?php else: ?>
            <div class="row g-2 g-sm-3 g-md-4">
                <?php foreach($designs as $d): ?>
                    <?php include "templates/design_card.php"; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Sub-Categories Browse View -->
    <?php if(empty($search) && !empty($sub_categories)): ?>
        <?php if ($is_root): ?>
            <!-- Category Search Bar & Filters -->
            <div class="row align-items-center justify-content-between mb-4 mb-md-5 g-3">
                <div class="col-md-7 col-lg-8">
                    <!-- Category Filter Pills -->
                    <div class="d-flex justify-content-md-start justify-content-center gap-2 flex-wrap filter-btn-group">
                        <button class="btn btn-sm btn-outline-dark rounded-pill px-3 py-1 active-filter-btn" id="filter-all" onclick="filterCategories('all')">All</button>
                        <button class="btn btn-sm btn-outline-dark rounded-pill px-3 py-1" id="filter-ladies" onclick="filterCategories('ladies')">Ladies</button>
                        <button class="btn btn-sm btn-outline-dark rounded-pill px-3 py-1" id="filter-mens" onclick="filterCategories('mens')">Mens</button>
                        <button class="btn btn-sm btn-outline-dark rounded-pill px-3 py-1" id="filter-new" onclick="filterCategories('new')">New Arrivals</button>
                    </div>
                </div>
                
                <div class="col-md-5 col-lg-4">
                    <div class="input-group shadow-sm rounded-pill overflow-hidden border border-light-subtle bg-white p-1">
                        <span class="input-group-text bg-transparent border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="categorySearchInput" class="form-control border-0 bg-transparent shadow-none" placeholder="Search categories..." style="font-size: 0.95rem;">
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="row g-2 g-sm-3 g-md-4 justify-content-center" id="categoriesGrid">
            <?php foreach($sub_categories as $sc): ?>
                <?php 
                // Determine tags for this category
                $cNameLower = strtolower($sc['name']);
                $tags = [];
                
                // Ladies Keywords Match
                $isLadies = false;
                $ladies_keywords = ['ring', 'mangalsutra', 'earring', 'bali', 'pendant', 'stud', 'toe', 'set', 'charms'];
                foreach ($ladies_keywords as $kw) {
                    if (strpos($cNameLower, $kw) !== false) {
                        $isLadies = true;
                        break;
                    }
                }
                if ($isLadies) $tags[] = 'ladies';
                
                // Mens Keywords Match
                $isMens = false;
                $mens_keywords = ['kada', 'bracelet', 'ring', 'chain'];
                foreach ($mens_keywords as $kw) {
                    if (strpos($cNameLower, $kw) !== false) {
                        $isMens = true;
                        break;
                    }
                }
                if ($isMens) $tags[] = 'mens';
                
                // New Arrivals Check (Check if category contains any of the top 15 newest designs)
                $isNew = in_array($sc['id'], $new_arrival_category_ids);
                if ($isNew) $tags[] = 'new';
                
                $tags_str = implode(' ', $tags);
                $scImg = !empty($sc['image_path']) ? htmlspecialchars($sc['image_path']) : 'uploads/categories/1765362506_6194.png';
                ?>
                <div class="col-6 col-sm-6 col-md-4 col-lg-3 category-card-item" data-category-name="<?= htmlspecialchars($sc['name']) ?>" data-tags="<?= $tags_str ?>">
                    <a href="shop.php?cat=<?= $sc['id'] ?>" class="text-decoration-none d-block h-100">
                        <div class="square-category-card">
                            <div class="square-category-image-wrapper">
                                <img src="<?= $scImg ?>" alt="<?= htmlspecialchars($sc['name']) ?>" loading="lazy" decoding="async">
                            </div>
                            <div class="square-category-info">
                                <div class="square-category-title text-truncate"><?= htmlspecialchars($sc['name']) ?></div>
                                <div class="square-category-count">
                                    <?php if (!empty($sc['subcat_count']) && $sc['subcat_count'] > 0): ?>
                                        <?= (int)$sc['subcat_count'] ?> Sub-categories
                                    <?php else: ?>
                                        <?= (int)($sc['design_count'] ?? 0) ?> <?= ((int)($sc['design_count'] ?? 0) === 1) ? 'Design' : 'Designs' ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($is_root): ?>
            <script>
            function filterCategories(activeTag) {
                // Update active button styling
                const buttons = document.querySelectorAll('.filter-btn-group .btn');
                buttons.forEach(btn => {
                    btn.classList.remove('active-filter-btn');
                });
                
                const activeBtn = document.getElementById('filter-' + activeTag);
                if (activeBtn) {
                    activeBtn.classList.add('active-filter-btn');
                }
                
                // Filter items
                const searchVal = document.getElementById('categorySearchInput').value.toLowerCase().trim();
                const items = document.querySelectorAll('.category-card-item');
                
                items.forEach(item => {
                    const catName = item.getAttribute('data-category-name').toLowerCase();
                    const tags = item.getAttribute('data-tags').split(' ');
                    
                    const matchesSearch = catName.includes(searchVal);
                    const matchesTag = (activeTag === 'all') || tags.includes(activeTag);
                    
                    if (matchesSearch && matchesTag) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('categorySearchInput');
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        // Find currently active filter button
                        let activeTag = 'all';
                        const activeBtn = document.querySelector('.filter-btn-group .btn.active-filter-btn');
                        if (activeBtn) {
                            activeTag = activeBtn.id.replace('filter-', '');
                        }
                        filterCategories(activeTag);
                    });
                }
            });
            </script>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Designs List View -->
    <?php if(empty($search) && !empty($designs)): ?>
        
        <!-- Category Header Presentation Card -->
        <div class="row align-items-center bg-white p-4 rounded-4 shadow-sm border border-light-subtle mb-5">
            <div class="col-md-3 text-center mb-3 mb-md-0">
                <div class="bg-light rounded-4 p-2 border border-light-subtle d-flex align-items-center justify-content-center" style="height: 180px; width: 100%; overflow: hidden;">
                    <img src="<?= !empty($current_category['image_path']) ? htmlspecialchars($current_category['image_path']) : 'uploads/categories/1765362506_6194.png' ?>" 
                         alt="<?= htmlspecialchars($current_category['name']) ?>" 
                         class="img-fluid rounded-3" 
                         loading="lazy" 
                         decoding="async" 
                         style="max-height: 100%; object-fit: contain; mix-blend-mode: multiply;">
                </div>
            </div>
            <div class="col-md-9 text-start">
                <h1 class="fw-bold mb-1" style="font-family: var(--heading-font), serif; font-size: calc(1.5rem + 1vw); letter-spacing: 0.5px; color: #1e2221;">
                    <?= htmlspecialchars(strtoupper($current_category['name'])) ?>
                </h1>
                <h6 class="text-secondary fw-bold mb-3 font-sans" style="font-size: 0.85rem; letter-spacing: 1px;">
                    <?= count($designs) ?> COLLECTION
                </h6>
                
                <!-- Trust Badges Ribbon -->
                <div class="d-flex flex-wrap gap-3 mb-4" style="font-size: 0.75rem; font-weight: 500; color: #475569;">
                    <div class="d-flex align-items-center gap-1">
                        <i class="bi bi-gem text-warning"></i>
                        <span>100% 925 Sterling Silver</span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <i class="bi bi-award text-warning"></i>
                        <span>Premium Quality</span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <i class="bi bi-box-seam text-warning"></i>
                        <span>Ready Stock</span>
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <i class="bi bi-truck text-warning"></i>
                        <span>Pan India Delivery</span>
                    </div>
                </div>
                
            </div>
        </div>

        <!-- Controls Bar: Count, Sort, Grid/List -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom border-light flex-wrap gap-3">
            <h5 class="mb-0 fw-bold font-sans text-uppercase text-dark" style="letter-spacing: 0.5px;">
                <?= count($designs) ?> DESIGNS
            </h5>
            
            <div class="d-flex align-items-center gap-3">
                <!-- Sort dropdown -->
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-muted fw-medium font-sans">Sort By :</span>
                    <select class="form-select form-select-sm rounded-3 shadow-none border-dark-subtle" id="designSortSelect" style="width: 130px; font-size: 0.8rem; font-weight: 500;">
                        <option value="latest">Latest</option>
                        <option value="weight-asc">Weight: Low to High</option>
                        <option value="weight-desc">Weight: High to Low</option>
                        <option value="sku-asc">SKU Code</option>
                    </select>
                </div>
                
                <!-- Layout view selector -->
                <div class="btn-group btn-group-sm rounded-3 overflow-hidden border border-dark-subtle" role="group">
                    <button type="button" class="btn btn-light border-0 py-1 px-2 active" id="btn-grid-view" title="Grid View">
                        <i class="bi bi-grid-3x3-gap-fill text-dark"></i>
                    </button>
                    <button type="button" class="btn btn-light border-0 py-1 px-2" id="btn-list-view" title="List View">
                        <i class="bi bi-list text-muted"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Overhauled Designs Grid -->
        <div class="row g-2 g-md-3 designs-grid-container">
            <?php foreach($designs as $d): ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2 design-item-card-col" 
                     data-id="<?= $d['id'] ?>"
                     data-date="<?= $d['id'] ?>"
                     data-weight="<?= floatval($d['weight']) ?>"
                     data-sku="<?= htmlspecialchars(($d['product_sku'] ?? '') . '-' . ($d['sku'] ?: 'N/A')) ?>">
                    <div class="card border border-light-subtle shadow-sm rounded-4 h-100 overflow-hidden hover-lift position-relative design-card" style="background-color: #FCFAF7 !important; display: flex; flex-direction: column;">
                        <!-- SKU Badge (Top Left) -->
                        <div class="position-absolute top-0 start-0 m-2 z-2 card-sku-badge">
                            <span class="badge font-sans" style="background-color: #FDF3E7; color: #C67C27; font-size: 0.65rem; border-radius: 4px; padding: 4px 6px; font-weight: 700; border: 1px solid rgba(198,124,39,0.15);"><?= htmlspecialchars(($d['product_sku'] ?? '') . '-' . ($d['sku'] ?: 'N/A')) ?></span>
                        </div>
                        
                        <!-- Wishlist Icon (Top Right) -->
                        <div class="position-absolute top-0 end-0 m-2 z-2 card-fav-btn">
                            <button type="button" class="btn btn-link text-decoration-none p-0 shadow-none wishlist-heart-btn" data-design-id="<?= $d['id'] ?>" onclick="toggleWishlist(this, <?= $d['id'] ?>)">
                                <i class="bi bi-heart" style="font-size: 0.95rem; color: var(--gold-accent);"></i>
                            </button>
                        </div>
                        
                        <!-- Image Container -->
                        <div class="position-relative design-card-img-container" style="height: 150px; background-color: #FFFFFF !important; border-bottom: 1px solid #FAF8F5; flex-shrink: 0; width: 100%; cursor: pointer;" onclick="openProductDetailModal(<?= e(json_encode([
                            'id' => $d['id'],
                            'sku' => ($d['product_sku'] ?? '') . '-' . ($d['sku'] ?: 'N/A'),
                            'title' => $current_category['name'],
                            'weight' => number_format(floatval($d['weight']), 3) . ' gm (Gross Weight)',
                            'stock_qty' => (int)$d['stock_qty'],
                            'images' => $d['image_path'] ?: '',
                            'product_name' => $d['product_name'] ?: ''
                        ])) ?>)">
                            <?php if(!empty($d['image_path'])): 
                                $img_src = explode(',', $d['image_path'])[0];
                            ?>
                                <img src="<?= e($img_src) ?>" class="w-100 h-100 object-fit-contain p-2 rounded-top-4 transition-scale" style="mix-blend-mode: multiply;">
                            <?php else: ?>
                                <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image fs-2 opacity-25"></i></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Details Container -->
                        <div class="card-body p-2 p-md-3 text-start d-flex flex-column justify-content-between" style="background-color: #FCFAF7; flex-grow: 1;">
                            <div style="cursor: pointer;" onclick="openProductDetailModal(<?= e(json_encode([
                                'id' => $d['id'],
                                'sku' => ($d['product_sku'] ?? '') . '-' . ($d['sku'] ?: 'N/A'),
                                'title' => $current_category['name'],
                                'weight' => number_format(floatval($d['weight']), 3) . ' gm (Gross Weight)',
                                'stock_qty' => (int)$d['stock_qty'],
                                'images' => $d['image_path'] ?: '',
                                'product_name' => $d['product_name'] ?: ''
                            ])) ?>)">
                                <!-- Title -->
                                <h6 class="fw-bold text-dark mb-1 text-truncate font-sans card-title-text" style="font-size: 0.82rem;" title="<?= e($current_category['name']) ?>">
                                    <?= htmlspecialchars($current_category['name']) ?>
                                </h6>
                                
                                <!-- Weight -->
                                <div class="text-dark fw-medium font-sans mb-1 card-weight-text" style="font-size: 0.78rem;">
                                    <?= number_format(floatval($d['weight']), 3) ?> gm
                                </div>
                            </div>
                            
                            <!-- Quantity selector and Add Cart Button -->
                            <div class="mt-2 pt-2 border-top border-light-subtle card-actions-wrapper">
                                <form action="cart.php" method="POST" class="d-flex align-items-center justify-content-between gap-1 mb-0 ajax-cart-form w-100">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="design_id" value="<?= $d['id'] ?>">
                                    
                                    <!-- Qty Picker Pill -->
                                    <div class="input-group input-group-sm mb-0 shadow-sm rounded-pill border border-dark-subtle overflow-hidden bg-white" style="width: 75px; flex-shrink: 0; border-color: rgba(0,0,0,0.15) !important;">
                                        <button type="button" class="btn btn-link text-decoration-none border-0 p-0 text-dark fs-6 rounded-start-pill lh-1 d-flex align-items-center justify-content-center" style="background:#fff; width:22px; height:24px;" onclick="let inp = this.nextElementSibling; let val = parseInt(inp.value); if(val > 1) { inp.value = val - 1; }">&minus;</button>
                                        <input type="number" name="qty" value="1" min="1" max="<?= $d['stock_qty'] ?>" class="form-control text-center border-0 p-0 fw-bold bg-white shadow-none qty-input-field" style="font-size: 0.75rem; pointer-events: none; height:24px;" readonly>
                                        <button type="button" class="btn btn-link text-decoration-none border-0 p-0 text-dark fs-6 rounded-end-pill lh-1 d-flex align-items-center justify-content-center" style="background:#fff; width:22px; height:24px;" onclick="let inp = this.previousElementSibling; let val = parseInt(inp.value); let max = parseInt(inp.getAttribute('max')); if(val < max) { inp.value = val + 1; } else { alert('Maximum available quantity is ' + max); }">&plus;</button>
                                    </div>
                                    
                                    <!-- Add Button -->
                                    <button type="submit" class="btn p-0 d-flex align-items-center justify-content-center rounded-3 shadow-sm hover-lift add-to-bag-btn" style="background-color: #0c2d27; color: white; width: 30px; height: 26px; border: none;" title="Add to Bag">
                                        <i class="bi bi-cart-plus" style="font-size: 0.95rem;"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Bottom Trust Ribbon -->
        <div class="row g-2 justify-content-center py-4 my-5 bg-white border-top border-bottom rounded-4" style="background-color: #FCFAF7 !important; border-color: #EFECE6 !important; font-family: var(--paragraph-font), sans-serif;">
            <div class="col-4 col-md-2 text-center border-end border-light-subtle d-flex flex-column align-items-center justify-content-center">
                <i class="bi bi-gem fs-3 mb-1 text-warning"></i>
                <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.72rem;">100% 925</h6>
                <p class="mb-0 text-muted" style="font-size: 0.65rem;">Sterling Silver</p>
            </div>
            <div class="col-4 col-md-2 text-center border-end border-light-subtle d-flex flex-column align-items-center justify-content-center">
                <i class="bi bi-award fs-3 mb-1 text-warning"></i>
                <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.72rem;">Premium</h6>
                <p class="mb-0 text-muted" style="font-size: 0.65rem;">Quality</p>
            </div>
            <div class="col-4 col-md-2 text-center border-end border-light-subtle d-flex flex-column align-items-center justify-content-center">
                <i class="bi bi-box-seam fs-3 mb-1 text-warning"></i>
                <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.72rem;">Ready</h6>
                <p class="mb-0 text-muted" style="font-size: 0.65rem;">Stock</p>
            </div>
            <div class="col-4 col-md-2 text-center border-end border-light-subtle d-flex flex-column align-items-center justify-content-center">
                <i class="bi bi-shield-check fs-3 mb-1 text-warning"></i>
                <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.72rem;">Secure</h6>
                <p class="mb-0 text-muted" style="font-size: 0.65rem;">Packaging</p>
            </div>
            <div class="col-4 col-md-2 text-center border-end border-light-subtle d-flex flex-column align-items-center justify-content-center">
                <i class="bi bi-truck fs-3 mb-1 text-warning"></i>
                <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.72rem;">Pan India</h6>
                <p class="mb-0 text-muted" style="font-size: 0.65rem;">Delivery</p>
            </div>
            <div class="col-4 col-md-2 text-center d-flex flex-column align-items-center justify-content-center">
                <i class="bi bi-tags fs-3 mb-1 text-warning"></i>
                <h6 class="mb-0 fw-bold text-dark" style="font-size: 0.72rem;">Best Wholesale</h6>
                <p class="mb-0 text-muted" style="font-size: 0.65rem;">Prices</p>
            </div>
        </div>

        <!-- Sticky Bottom Checkout Bar -->
        <div id="stickyCheckoutBar" class="fixed-bottom py-2 py-md-3 px-3 px-md-4 bg-white border-top shadow-lg" style="z-index: 1040; background-color: #FCFAF7 !important; border-color: #EFECE6 !important; display: <?= $cart_total_qty > 0 ? 'block' : 'none' ?>; transition: transform 0.3s ease-in-out; transform: translateY(0);">
            <div class="container">
                <div class="row align-items-center g-2 g-md-3">
                    <!-- Cart Items info -->
                    <div class="col-12 col-md-6 d-flex align-items-center justify-content-between justify-content-md-start gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="position-relative bg-white rounded-circle shadow-sm d-flex align-items-center justify-content-center flex-shrink-0" style="width: 45px; height: 45px; border: 1px solid #FAF8F5;">
                                <i class="bi bi-cart3 fs-5 text-dark"></i>
                                <span id="checkout-cart-badge" class="position-absolute top-0 start-100 translate-middle badge rounded-circle bg-danger" style="font-size: 0.65rem; padding: 4px 6px;">
                                    <?= $cart_total_qty ?>
                                </span>
                            </div>
                            <div class="text-start lh-sm">
                                <h6 class="mb-0 fw-bold text-dark font-sans" id="checkout-item-count" style="font-size: 0.85rem;"><?= $cart_total_qty ?> ITEMS ADDED</h6>
                                <small class="text-secondary font-sans fw-semibold" id="checkout-total-weight" style="font-size: 0.75rem;">Total: <?= number_format($cart_total_weight, 3) ?> gm</small>
                            </div>
                        </div>
                        <!-- Small View Cart button visible only on mobile, placed next to cart info -->
                        <a href="cart.php" class="btn btn-outline-dark rounded-pill px-3 py-1 fw-bold font-sans d-md-none" style="font-size: 0.75rem;">VIEW CART</a>
                    </div>
                    
                    <!-- Buttons -->
                    <div class="col-12 col-md-6 d-flex align-items-center justify-content-end gap-2">
                        <a href="cart.php" class="btn btn-outline-dark rounded-pill px-3 py-2 fw-bold font-sans d-none d-md-inline-block" style="font-size: 0.8rem; border-color: #1e2221 !important; color: #1e2221 !important; background: transparent;">VIEW CART</a>
                        <a href="checkout.php" class="btn btn-dark rounded-pill px-4 py-2 fw-bold font-sans d-flex align-items-center justify-content-center w-100 w-md-auto gap-2" style="background-color: #0c2d27; border-color: #0c2d27; font-size: 0.85rem; color: white;">
                            PROCEED TO ENQUIRY <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Client-side Sorting, View-Toggle, Favorites scripts -->
        <script>

        document.addEventListener('DOMContentLoaded', function() {
            // Client-side Sorting
            const sortSelect = document.getElementById('designSortSelect');
            const cardsGrid = document.querySelector('.designs-grid-container');
            
            if (sortSelect && cardsGrid) {
                sortSelect.addEventListener('change', function() {
                    const sortBy = this.value;
                    const cards = Array.from(cardsGrid.querySelectorAll('.design-item-card-col'));
                    
                    cards.sort((a, b) => {
                        if (sortBy === 'latest') {
                            return parseInt(b.getAttribute('data-date')) - parseInt(a.getAttribute('data-date'));
                        } else if (sortBy === 'weight-asc') {
                            return parseFloat(a.getAttribute('data-weight')) - parseFloat(b.getAttribute('data-weight'));
                        } else if (sortBy === 'weight-desc') {
                            return parseFloat(b.getAttribute('data-weight')) - parseFloat(a.getAttribute('data-weight'));
                        } else if (sortBy === 'sku-asc') {
                            return a.getAttribute('data-sku').localeCompare(b.getAttribute('data-sku'));
                        }
                        return 0;
                    });
                    
                    cards.forEach(card => cardsGrid.appendChild(card));
                });
            }

            // Client-side Grid/List layout toggle
            const btnGrid = document.getElementById('btn-grid-view');
            const btnList = document.getElementById('btn-list-view');
            
            if (btnGrid && btnList && cardsGrid) {
                btnGrid.addEventListener('click', function() {
                    btnGrid.classList.add('active');
                    btnGrid.querySelector('i').className = 'bi bi-grid-3x3-gap-fill text-dark';
                    btnList.classList.remove('active');
                    btnList.querySelector('i').className = 'bi bi-list text-muted';
                    
                    cardsGrid.className = 'row g-2 g-md-3 designs-grid-container';
                    
                    const cols = cardsGrid.querySelectorAll('.design-item-card-col');
                    cols.forEach(col => {
                        col.className = 'col-6 col-sm-4 col-md-3 col-lg-2 design-item-card-col';
                        const card = col.querySelector('.design-card');
                        if (card) {
                            card.style.flexDirection = 'column';
                            card.classList.remove('flex-row', 'align-items-center');
                            const body = card.querySelector('.card-body');
                            if (body) {
                                body.classList.remove('flex-grow-1', 'ps-3');
                                body.style.padding = '';
                            }
                            const imgCont = card.querySelector('.design-card-img-container');
                            if (imgCont) {
                                imgCont.style.width = '100%';
                                imgCont.style.height = '150px';
                                imgCont.style.borderBottom = '1px solid #FAF8F5';
                                imgCont.style.borderRight = 'none';
                            }
                            const fav = card.querySelector('.card-fav-btn');
                            if (fav) fav.style.display = 'block';
                            const sku = card.querySelector('.card-sku-badge');
                            if (sku) sku.style.display = 'block';
                        }
                    });
                });
                
                btnList.addEventListener('click', function() {
                    btnList.classList.add('active');
                    btnList.querySelector('i').className = 'bi bi-list-task text-dark';
                    btnGrid.classList.remove('active');
                    btnGrid.querySelector('i').className = 'bi bi-grid-3x3-gap text-muted';
                    
                    cardsGrid.className = 'row g-3 designs-grid-container row-cols-1';
                    
                    const cols = cardsGrid.querySelectorAll('.design-item-card-col');
                    cols.forEach(col => {
                        col.className = 'col-12 design-item-card-col';
                        const card = col.querySelector('.design-card');
                        if (card) {
                            card.style.flexDirection = 'row';
                            card.classList.add('flex-row', 'align-items-center');
                            const body = card.querySelector('.card-body');
                            if (body) {
                                body.classList.add('flex-grow-1', 'ps-3');
                                body.style.padding = '1.5rem';
                            }
                            const imgCont = card.querySelector('.design-card-img-container');
                            if (imgCont) {
                                imgCont.style.width = '180px';
                                imgCont.style.height = '150px';
                                imgCont.style.borderBottom = 'none';
                                imgCont.style.borderRight = '1px solid #FAF8F5';
                            }
                            // Hide top badge & fav button in list layout for cleaner row structure
                            const fav = card.querySelector('.card-fav-btn');
                            if (fav) fav.style.display = 'none';
                            const sku = card.querySelector('.card-sku-badge');
                            if (sku) sku.style.display = 'none';
                        }
                    });
                });
            }
        });
        </script>
    <?php endif; ?>

    <!-- Leaf Node Empty State -->
    <?php if(empty($search) && empty($sub_categories) && empty($designs) && !$is_root): ?>
        <div class="text-center py-5 mt-5 border rounded-4 bg-white shadow-sm">
            <i class="bi bi-stars display-1 text-muted opacity-25 mb-4 d-block"></i>
            <h3 class="fw-bold" style="font-family: var(--category-font), sans-serif;">Collection Launching Soon</h3>
            <p class="text-muted">We are currently crafting new designs for this category. Stay tuned!</p>
            <a href="shop.php" class="btn btn-forest rounded-pill px-4 mt-3">Back to Catalog</a>
        </div>
    <?php endif; ?>

</div>


<?php require_once "store_footer.php"; ?>
