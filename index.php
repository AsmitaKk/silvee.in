<?php
require_once "config.php";
require_once "functions.php";

$themeSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM theme_settings");
    while ($row = $stmt->fetch()) {
        $themeSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
}

$primaryColor = $themeSettings['primary_color'] ?? '#0D3B36';
$accentColor = $themeSettings['banner_accent_color'] ?? '#C8A96B';
$whatsappNumber = $themeSettings['whatsapp_number'] ?? '918208719194';

$categorySectionTitle = $themeSettings['category_section_title'] ?? 'Shop by Category';
$newArrivalsSectionTitle = $themeSettings['new_arrivals_section_title'] ?? 'New Arrivals';
$catalogSectionTitle = $themeSettings['catalog_section_title'] ?? 'Our Wholesale Catalog';
$categoryRowCount = $themeSettings['category_row_count'] ?? '5';
$categoryRowCountMobile = $themeSettings['category_row_count_mobile'] ?? '4';
$catalog_visibility = isset($themeSettings['catalog_categories_visibility']) ? json_decode($themeSettings['catalog_categories_visibility'], true) : [];
if (!is_array($catalog_visibility)) {
    $catalog_visibility = [];
}

$categories_list_desktop = [];
$categories_list_mobile = [];

try {
    $selected_cat_ids = isset($themeSettings['homepage_selected_categories']) ? json_decode($themeSettings['homepage_selected_categories'], true) : [];
    if (is_array($selected_cat_ids) && !empty($selected_cat_ids)) {
        $placeholders = implode(',', array_map('intval', $selected_cat_ids));
        $categories_list_desktop = $pdo->query("
            SELECT c.id, c.name, c.image_path, c.parent_id,
                   (SELECT COUNT(sub.id) FROM categories sub WHERE sub.parent_id = c.id AND (sub.is_hidden IS NULL OR sub.is_hidden = 0)) as subcat_count,
                   COALESCE(dc.count, 0) as design_count
            FROM categories c
            LEFT JOIN (
                SELECT 
                    CASE 
                        WHEN pcat.parent_id IS NOT NULL AND pcat.parent_id != 0 THEN pcat.parent_id 
                        ELSE pcat.id 
                    END as root_cat_id,
                    COUNT(d.id) as count
                FROM product_designs d
                JOIN products p ON d.product_id = p.id
                JOIN categories pcat ON p.category_id = pcat.id
                WHERE d.stock_qty > 0 AND d.is_hidden = 0 AND (p.is_hidden = 0 OR p.is_hidden IS NULL)
                GROUP BY root_cat_id
            ) dc ON dc.root_cat_id = c.id
            WHERE c.id IN ($placeholders) AND (c.parent_id IS NULL OR c.parent_id = 0) AND (c.is_hidden IS NULL OR c.is_hidden = 0)
            ORDER BY FIELD(c.id, $placeholders)
        ")->fetchAll();
    } else {
        $categories_list_desktop = $pdo->query("
            SELECT c.id, c.name, c.image_path, c.parent_id,
                   (SELECT COUNT(sub.id) FROM categories sub WHERE sub.parent_id = c.id AND (sub.is_hidden IS NULL OR sub.is_hidden = 0)) as subcat_count,
                   COALESCE(dc.count, 0) as design_count
            FROM categories c
            LEFT JOIN (
                SELECT 
                    CASE 
                        WHEN pcat.parent_id IS NOT NULL AND pcat.parent_id != 0 THEN pcat.parent_id 
                        ELSE pcat.id 
                    END as root_cat_id,
                    COUNT(d.id) as count
                FROM product_designs d
                JOIN products p ON d.product_id = p.id
                JOIN categories pcat ON p.category_id = pcat.id
                WHERE d.stock_qty > 0 AND d.is_hidden = 0 AND (p.is_hidden = 0 OR p.is_hidden IS NULL)
                GROUP BY root_cat_id
            ) dc ON dc.root_cat_id = c.id
            WHERE (c.parent_id IS NULL OR c.parent_id = 0) AND (c.is_hidden IS NULL OR c.is_hidden = 0)
            ORDER BY design_count DESC, c.name ASC
        ")->fetchAll();
    }
} catch (Exception $e) {
    $categories_list_desktop = [];
}

try {
    $selected_cat_ids_mobile = isset($themeSettings['homepage_selected_categories_mobile']) ? json_decode($themeSettings['homepage_selected_categories_mobile'], true) : [];
    if (is_array($selected_cat_ids_mobile) && !empty($selected_cat_ids_mobile)) {
        $placeholders_mobile = implode(',', array_map('intval', $selected_cat_ids_mobile));
        $categories_list_mobile = $pdo->query("
            SELECT c.id, c.name, c.image_path, c.parent_id,
                   (SELECT COUNT(sub.id) FROM categories sub WHERE sub.parent_id = c.id AND (sub.is_hidden IS NULL OR sub.is_hidden = 0)) as subcat_count,
                   COALESCE(dc.count, 0) as design_count
            FROM categories c
            LEFT JOIN (
                SELECT 
                    CASE 
                        WHEN pcat.parent_id IS NOT NULL AND pcat.parent_id != 0 THEN pcat.parent_id 
                        ELSE pcat.id 
                    END as root_cat_id,
                    COUNT(d.id) as count
                FROM product_designs d
                JOIN products p ON d.product_id = p.id
                JOIN categories pcat ON p.category_id = pcat.id
                WHERE d.stock_qty > 0 AND d.is_hidden = 0 AND (p.is_hidden = 0 OR p.is_hidden IS NULL)
                GROUP BY root_cat_id
            ) dc ON dc.root_cat_id = c.id
            WHERE c.id IN ($placeholders_mobile) AND (c.parent_id IS NULL OR c.parent_id = 0) AND (c.is_hidden IS NULL OR c.is_hidden = 0)
            ORDER BY FIELD(c.id, $placeholders_mobile)
        ")->fetchAll();
    } else {
        $categories_list_mobile = $categories_list_desktop;
    }
} catch (Exception $e) {
    $categories_list_mobile = $categories_list_desktop;
}

if (empty($categories_list_desktop)) {
    try {
        $categories_list_desktop = $pdo->query("SELECT c.id, c.name, c.image_path, c.parent_id, (SELECT COUNT(sub.id) FROM categories sub WHERE sub.parent_id = c.id AND (sub.is_hidden IS NULL OR sub.is_hidden = 0)) as subcat_count, 0 as design_count FROM categories c WHERE (c.parent_id IS NULL OR c.parent_id = 0) AND (c.is_hidden IS NULL OR c.is_hidden = 0) ORDER BY c.name")->fetchAll();
    } catch (Exception $e) {}
}
if (empty($categories_list_mobile)) {
    $categories_list_mobile = $categories_list_desktop;
}

$new_arrivals = [];
$new_arrivals_cat_id = $themeSettings['new_arrivals_category_id'] ?? '';
try {
    if (!empty($new_arrivals_cat_id)) {
        $cat_ids = [intval($new_arrivals_cat_id)];
        $sub_stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ? AND (is_hidden IS NULL OR is_hidden = 0)");
        $sub_stmt->execute([$new_arrivals_cat_id]);
        while ($sub_row = $sub_stmt->fetch()) {
            $cat_ids[] = intval($sub_row['id']);
        }
        $placeholders = implode(',', $cat_ids);
        
        $new_arrivals = $pdo->query("
            SELECT d.*, p.name as product_name, p.sku as product_sku, p.per_piece_weight as weight, p.category_id, cat.name as subcategory_name 
            FROM product_designs d 
            JOIN products p ON d.product_id = p.id 
            LEFT JOIN categories cat ON p.category_id = cat.id
            WHERE d.stock_qty > 0 AND d.is_hidden = 0 AND (p.is_hidden = 0 OR p.is_hidden IS NULL) AND p.category_id IN ($placeholders)
            ORDER BY d.id DESC 
            LIMIT 8
        ")->fetchAll();
    } else {
        $new_arrivals = $pdo->query("
            SELECT d.*, p.name as product_name, p.sku as product_sku, p.per_piece_weight as weight, p.category_id, cat.name as subcategory_name 
            FROM product_designs d 
            JOIN products p ON d.product_id = p.id 
            LEFT JOIN categories cat ON p.category_id = cat.id
            WHERE d.stock_qty > 0 AND d.is_hidden = 0 AND (p.is_hidden = 0 OR p.is_hidden IS NULL)
            ORDER BY d.id DESC 
            LIMIT 8
        ")->fetchAll();
    }
} catch (Exception $e) {
}

$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM categories ORDER BY parent_id, name")->fetchAll();
} catch (Exception $e) {}

$parents = [];
$children = [];
foreach($categories as $c) {
    if (!empty($c['is_hidden']) && (int)$c['is_hidden'] === 1) {
        continue;
    }
    if(!$c['parent_id']) $parents[] = $c;
    else $children[$c['parent_id']][] = $c;
}

$categoryDesignAgg = [];
$total_designs = 0;
try {
    $agg = $pdo->query("
        SELECT 
            p.category_id, 
            COUNT(d.id) as design_count, 
            MAX(d.image_path) as fallback_image 
        FROM product_designs d 
        JOIN products p ON d.product_id = p.id 
        WHERE d.stock_qty > 0 AND d.is_hidden = 0 AND (p.is_hidden = 0 OR p.is_hidden IS NULL)
        GROUP BY p.category_id
    ")->fetchAll();
    foreach($agg as $row) {
        $categoryDesignAgg[$row['category_id']] = [
            'count' => (int)$row['design_count'],
            'image' => $row['fallback_image']
        ];
        $total_designs += (int)$row['design_count'];
    }
} catch (Exception $e) {}

$showCategories = ($themeSettings['show_categories'] ?? '1') === '1';

$hero_carousel_data = isset($themeSettings['hero_carousel_data']) ? json_decode($themeSettings['hero_carousel_data'], true) : [];
if (empty($hero_carousel_data)) {
    $hero_carousel_data = [
        [
            'title' => 'Wholesale Jewellery Collection',
            'subtitle' => 'PREMIUM 925 STERLING SILVER',
            'badge_text' => 'New',
            'button_text' => 'Explore Collection',
            'button_link' => '#catalog',
            'image_path' => 'assets/hero_jewellery.png',
            'is_visible' => 1
        ]
    ];
}

$active_slides = [];
foreach ($hero_carousel_data as $slide) {
    if (!isset($slide['is_visible']) || $slide['is_visible'] == 1) {
        $active_slides[] = $slide;
    }
}
$hero_carousel_data = $active_slides;
$bannerEnabled = (($themeSettings['banner_enabled'] ?? '1') === '1') && !empty($hero_carousel_data);

$hero_carousel_mobile_data = isset($themeSettings['hero_carousel_mobile_data']) ? json_decode($themeSettings['hero_carousel_mobile_data'], true) : [];
if (empty($hero_carousel_mobile_data)) {
    $hero_carousel_mobile_data = [
        [
            'title' => 'Wholesale Jewellery Collection',
            'subtitle' => 'PREMIUM 925 STERLING SILVER',
            'badge_text' => 'New',
            'button_text' => 'Explore Collection',
            'button_link' => '#catalog',
            'image_path' => 'assets/hero_jewellery.png',
            'is_visible' => 1
        ]
    ];
}

$active_mobile_slides = [];
foreach ($hero_carousel_mobile_data as $slide) {
    if (!isset($slide['is_visible']) || $slide['is_visible'] == 1) {
        $active_mobile_slides[] = $slide;
    }
}
$hero_carousel_mobile_data = $active_mobile_slides;
$bannerMobileEnabled = (($themeSettings['banner_mobile_enabled'] ?? '1') === '1') && !empty($hero_carousel_mobile_data);

$promo_banners_data = isset($themeSettings['promo_banners_data']) ? json_decode($themeSettings['promo_banners_data'], true) : [];
if (empty($promo_banners_data)) {
    $promo_banners_data = [
        [
            'title' => 'ADHIK MAAS SPECIAL',
            'subtitle' => 'Divine Designs Pure Blessings',
            'button_text' => 'Shop Now',
            'button_link' => '#catalog',
            'image_path' => ''
        ],
        [
            'title' => 'RAKHI COLLECTION',
            'subtitle' => 'Celebrate the bond with pure silver',
            'button_text' => 'Shop Now',
            'button_link' => '#catalog',
            'image_path' => ''
        ],
        [
            'title' => 'PREMIUM COLLECTION',
            'subtitle' => 'Handpicked Luxury Silver Jewellery',
            'button_text' => 'Explore Now',
            'button_link' => '#catalog',
            'image_path' => ''
        ],
        [
            'title' => 'NEW ARRIVALS',
            'subtitle' => 'Fresh Designs Every Week',
            'button_text' => 'Explore Now',
            'button_link' => '#catalog',
            'image_path' => ''
        ]
    ];
}

require_once "store_header.php"; 
?>

<style>
:root {
    --forest-green: <?= htmlspecialchars($primaryColor) ?>;
    --gold-accent: <?= htmlspecialchars($accentColor) ?>;
    --category-card-height: <?= htmlspecialchars($themeSettings['category_card_height'] ?? '220') ?>px;
    --category-card-height-mobile: <?= htmlspecialchars($themeSettings['category_card_height_mobile'] ?? '105') ?>px;
    --category-title-font-size-mobile: <?= htmlspecialchars($themeSettings['category_title_font_size_mobile'] ?? '10') ?>px;
    --shop-cat-title-font: '<?= htmlspecialchars($themeSettings['shop_cat_title_font'] ?? 'Playfair Display') ?>', sans-serif;
    --shop-cat-title-font-size: <?= htmlspecialchars($themeSettings['shop_cat_title_font_size'] ?? '11') ?>px;
    --shop-cat-title-font-size-mobile: <?= htmlspecialchars($themeSettings['shop_cat_title_font_size_mobile'] ?? '9') ?>px;

<?php
function getSec($ts, $key, $default = '') {
    return $ts[$key] ?? $default;
}

$sections_with_height = ['hero', 'category', 'new_arrivals', 'promo', 'why_us'];
$all_sections          = ['hero', 'category', 'new_arrivals', 'promo', 'why_us', 'catalog'];
$devs = ['desktop', 'tablet', 'mobile'];

foreach ($all_sections as $sec) {
    foreach ($devs as $dev) {
        $w = getSec($themeSettings, "section_{$sec}_width_{$dev}", '100%');
        if ($sec === 'hero' && $w === '100%') {
            $w = getSec($themeSettings, "hero_width_{$dev}", '100%');
        }
        echo "    --sec-{$sec}-width-{$dev}: " . htmlspecialchars($w) . ";\n";
        if (in_array($sec, $sections_with_height)) {
            $h = getSec($themeSettings, "section_{$sec}_height_{$dev}", '');
            if ($sec === 'hero' && empty($h)) {
                $fallbackHeight = ($dev === 'desktop') ? '750' : '200';
                $h = getSec($themeSettings, "hero_height_{$dev}", $fallbackHeight);
            }
            $hcss = (!empty($h) && $h !== 'auto') ? htmlspecialchars($h) . 'px' : 'auto';
            echo "    --sec-{$sec}-height-{$dev}: {$hcss};\n";
        }
    }
}
?>
}

@media (min-width: 992px) {
    .hero-carousel-item-full {
        height: clamp(300px, 38vw, var(--sec-hero-height-desktop, 750px)) !important;
        max-width: 100% !important;
        margin-left: auto !important;
        margin-right: auto !important;
    }
    section.idx-hero    { max-width: var(--sec-hero-width-desktop) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-category { max-width: var(--sec-category-width-desktop) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-new-arrivals { max-width: var(--sec-new_arrivals-width-desktop) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-promo   { max-width: var(--sec-promo-width-desktop) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-why-us  { max-width: var(--sec-why_us-width-desktop) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-catalog { max-width: var(--sec-catalog-width-desktop) !important; margin-left:auto !important; margin-right:auto !important; }
}

@media (min-width: 768px) and (max-width: 991.98px) {
    .hero-carousel-item-full {
        height: clamp(220px, 35vw, 380px) !important;
    }
    section.idx-hero    { max-width: var(--sec-hero-width-tablet) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-category { max-width: var(--sec-category-width-tablet) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-new-arrivals { max-width: var(--sec-new_arrivals-width-tablet) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-promo   { max-width: var(--sec-promo-width-tablet) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-why-us  { max-width: var(--sec-why_us-width-tablet) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-catalog { max-width: var(--sec-catalog-width-tablet) !important; margin-left:auto !important; margin-right:auto !important; }
}

</style>

<?php 
$hero_desktop_w = $themeSettings['section_hero_width_desktop']['setting_value'] ?? $themeSettings['section_hero_width_desktop'] ?? '100%';
if ($hero_desktop_w !== '100%'): 
?>
<style>
@media (min-width: 992px) {
    .hero-carousel-item-full {
        border-radius: 16px !important;
        margin-top: 1.5rem !important;
        margin-bottom: 1.5rem !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12) !important;
    }
}
</style>
<?php endif; ?>

<style>
@media (max-width: 991.98px) {
    .hero-carousel-item-full {
        height: clamp(220px, 35vw, 380px) !important;
        max-width: 100% !important;
        margin-left: auto !important;
        margin-right: auto !important;
        background-size: cover !important;
        background-position: center !important;
    }
}

@media (max-width: 767.98px) {
    .hero-carousel-item-full {
        height: clamp(350px, 130vw, 650px) !important;
    }
}

@media (max-width: 767.98px) {
    section.idx-hero    { max-width: var(--sec-hero-width-mobile) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-category { max-width: var(--sec-category-width-mobile) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-new-arrivals { max-width: var(--sec-new_arrivals-width-mobile) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-promo   { max-width: var(--sec-promo-width-mobile) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-why-us  { max-width: var(--sec-why_us-width-mobile) !important; margin-left:auto !important; margin-right:auto !important; }
    section.idx-catalog { max-width: var(--sec-catalog-width-mobile) !important; margin-left:auto !important; margin-right:auto !important; }
}

.scroll-container::-webkit-scrollbar {
    display: none;
}
.scroll-container {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.category-section-container {
    max-width: 100% !important;
    margin-left: auto !important;
    margin-right: auto !important;
    padding-left: clamp(0.75rem, 2vw, 2rem) !important;
    padding-right: clamp(0.75rem, 2vw, 2rem) !important;
}

/* ---- Desktop category card sizing (min-width 992px) ---- */
@media (min-width: 992px) {
    .category-circle-card {
        height: clamp(140px, 18vw, var(--category-card-height, 220px)) !important;
        border-radius: 16px !important;
        background-color: #FCFAF7 !important;
        border: 1px solid #F0ECE3 !important;
        padding: clamp(0.4rem, 0.8vw, 0.6rem) !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02) !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        align-items: center !important;
        overflow: hidden !important;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
    }

    .category-circle-card h6 {
        overflow: hidden !important;
        display: -webkit-box !important;
        -webkit-line-clamp: 2 !important;
        -webkit-box-orient: vertical !important;
        text-overflow: ellipsis !important;
        line-height: 1.25 !important;
        max-height: 2.5em !important;
        width: 100% !important;
        text-align: center !important;
        word-break: break-word !important;
        margin-bottom: 0.15rem !important;
    }

    .category-circle-card:hover {
        transform: translateY(-4px) !important;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05) !important;
        background-color: #FFFFFF !important;
        border-color: var(--gold-accent) !important;
    }

    .category-img-wrapper {
        width: 100%;
        height: 55%;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        margin-bottom: 0.25rem;
    }

    .category-circle-card .category-img-wrapper img {
        max-width: 85% !important;
        max-height: 85% !important;
        width: auto !important;
        height: auto !important;
        object-fit: contain !important;
        transition: transform 0.4s ease;
        mix-blend-mode: multiply !important;
        transform: scale(1.05) !important;
    }

    .category-circle-card:hover .category-img-wrapper img {
        transform: scale(1.12) !important;
    }
}

/* ---- Mobile category card sizing (max-width 991.98px) ---- */
@media (max-width: 991.98px) {
    .category-circle-card {
        aspect-ratio: auto !important;
        height: var(--category-card-height-mobile) !important;
        padding: 0.45rem 0.25rem 0.3rem 0.25rem !important;
        border-radius: 12px !important;
        background-color: #FCFAF7 !important;
        border: 1px solid #F0ECE3 !important;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02) !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: space-between !important;
        align-items: center !important;
        overflow: hidden !important;
    }
    .category-img-wrapper {
        width: 100% !important;
        height: 48% !important;
        margin-bottom: 0.1rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
    }
    .category-circle-card .category-img-wrapper img {
        max-width: 90% !important;
        max-height: 90% !important;
        width: auto !important;
        height: auto !important;
        object-fit: contain !important;
        mix-blend-mode: multiply !important;
        transform: none !important;
    }
    .category-circle-card h6 {
        overflow: hidden !important;
        display: -webkit-box !important;
        -webkit-line-clamp: 2 !important;
        -webkit-box-orient: vertical !important;
        text-overflow: ellipsis !important;
        line-height: 1.2 !important;
        max-height: 2.4em !important;
        margin-bottom: 0px !important;
        text-align: center !important;
        word-break: break-word !important;
        width: 100% !important;
        padding: 0 2px !important;
        font-size: 0.65rem !important;
    }
    .category-circle-card span {
        font-size: 0.52rem !important;
        margin-top: 0.08rem !important;
        color: var(--text-muted) !important;
        font-weight: 500 !important;
        text-align: center !important;
        flex-shrink: 0 !important;
    }
}


/* Transparent background and blend effect for product images in New Arrivals section only */
#new-arrivals .card,
#new-arrivals .design-card-img-container {
    background-color: transparent !important;
    background: transparent !important;
}
#new-arrivals .design-card-img-container img {
    mix-blend-mode: multiply !important;
    background-color: transparent !important;
    background: transparent !important;
}
</style>


<!-- Hero Section with Carousel -->
<?php if ($bannerEnabled || $bannerMobileEnabled): ?>
<section class="idx-hero position-relative overflow-hidden mb-4">
    <!-- Desktop Hero Carousel (Visible on Desktop only) -->
    <?php if ($bannerEnabled): ?>
    <div id="heroCarouselDesktop" class="carousel slide carousel-fade d-none d-md-block" data-bs-ride="carousel" data-bs-interval="4000">
        <div class="carousel-indicators mb-3">
            <?php foreach ($hero_carousel_data as $idx => $slide): ?>
                <button type="button" data-bs-target="#heroCarouselDesktop" data-bs-slide-to="<?= $idx ?>" class="<?= $idx === 0 ? 'active' : '' ?>" aria-current="<?= $idx === 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $idx + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
        <div class="carousel-inner">
            <?php foreach ($hero_carousel_data as $idx => $slide): ?>
                <?php 
                $slide_img = !empty($slide['image_path']) ? htmlspecialchars($slide['image_path']) : 'assets/hero_jewellery.png'; 
                ?>
                <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
                    <div class="hero-carousel-item-full" style="background-image: url('<?= $slide_img ?>');">
                        <?php if (!empty($slide['title']) || !empty($slide['subtitle'])): ?>
                            <div class="container h-100 d-flex align-items-center">
                                <div class="row w-100">
                                    <div class="col-11 col-sm-10 col-md-8 col-lg-6 col-xl-5 text-start ps-3 ps-md-4 ps-lg-5 py-3 py-md-4 py-lg-5 rounded-4 shadow" style="background: rgba(var(--forest-green-rgb, 6, 37, 33), 0.78); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.1); color: #fff; max-width: 500px; margin-left: clamp(5px, 2vw, 15px);">
                                        <?php if (!empty($slide['badge_text'])): ?>
                                            <div class="d-none d-md-inline-flex align-items-center gap-2 px-3 py-1 bg-white rounded-pill shadow-sm mb-3 text-dark">
                                                <span class="badge rounded-pill text-white" style="background-color: var(--gold-accent);"><?= htmlspecialchars($slide['badge_text']) ?></span>
                                                <span class="small fw-medium">Wholesale Exclusive</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <h5 class="text-uppercase mb-1 mb-md-2" style="font-size: clamp(0.68rem, 0.9vw, 0.75rem); font-weight:600; color: var(--gold-accent); letter-spacing: 2px;"><?= htmlspecialchars($slide['subtitle']) ?></h5>
                                        <h1 class="fw-bold mb-3 font-serif text-white" style="line-height: 1.15; font-size: clamp(1.1rem, 2.2vw, 1.85rem); margin-bottom: 1rem;">
                                            <?= htmlspecialchars($slide['title']) ?>
                                        </h1>
                                        
                                        <?php if (!empty($slide['button_text'])): ?>
                                            <div class="d-flex justify-content-start mt-2 mt-md-3">
                                                <a href="<?= htmlspecialchars($slide['button_link'] ?: '#catalog') ?>" class="btn rounded-pill px-3 py-2 px-md-4 py-md-2 fw-semibold text-white d-inline-flex align-items-center gap-1" style="background-color: var(--gold-accent); border: 1px solid var(--gold-accent); font-size: clamp(0.72rem, 0.9vw, 0.8rem); transition: all 0.3s ease;">
                                                    <?= htmlspecialchars($slide['button_text']) ?> <i class="bi bi-chevron-right ms-1" style="font-size: 0.7rem;"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Full banner image is clickable if no text is present -->
                            <a href="<?= htmlspecialchars($slide['button_link'] ?: '#catalog') ?>" class="position-absolute top-0 start-0 w-100 h-100 z-1" aria-label="Explore Banner Link"></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev d-none d-md-flex" type="button" data-bs-target="#heroCarouselDesktop" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next d-none d-md-flex" type="button" data-bs-target="#heroCarouselDesktop" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- Mobile Hero Carousel (Visible on Mobile only) -->
    <?php if ($bannerMobileEnabled): ?>
    <div id="heroCarouselMobile" class="carousel slide carousel-fade d-block d-md-none" data-bs-ride="carousel" data-bs-interval="4000">
        <div class="carousel-indicators mb-2">
            <?php foreach ($hero_carousel_mobile_data as $idx => $slide): ?>
                <button type="button" data-bs-target="#heroCarouselMobile" data-bs-slide-to="<?= $idx ?>" class="<?= $idx === 0 ? 'active' : '' ?>" aria-current="<?= $idx === 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $idx + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
        <div class="carousel-inner">
            <?php foreach ($hero_carousel_mobile_data as $idx => $slide): ?>
                <?php 
                $slide_img = !empty($slide['image_path']) ? htmlspecialchars($slide['image_path']) : 'assets/hero_jewellery.png'; 
                ?>
                <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
                    <div class="hero-carousel-item-full" style="background-image: url('<?= $slide_img ?>');">
                        <?php if (!empty($slide['title']) || !empty($slide['subtitle'])): ?>
                            <div class="container h-100 d-flex align-items-center">
                                <div class="row w-100">
                                    <div class="col-11 text-start ps-3 py-3 rounded-3 shadow" style="background: rgba(var(--forest-green-rgb, 6, 37, 33), 0.72); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.1); color: #fff; margin-left: 10px;">
                                        <?php if (!empty($slide['badge_text'])): ?>
                                            <span class="badge rounded-pill text-white mb-2" style="background-color: var(--gold-accent); font-size: 0.65rem; padding: 3px 8px;"><?= htmlspecialchars($slide['badge_text']) ?></span>
                                        <?php endif; ?>
                                        
                                        <h5 class="text-uppercase mb-1" style="font-size: 0.65rem; font-weight:600; color: var(--gold-accent); letter-spacing: 1px;"><?= htmlspecialchars($slide['subtitle']) ?></h5>
                                        <h1 class="fw-bold mb-2 font-serif text-white" style="line-height: 1.2; font-size: 1.05rem;">
                                            <?= htmlspecialchars($slide['title']) ?>
                                        </h1>
                                        
                                        <?php if (!empty($slide['button_text'])): ?>
                                            <div class="d-flex justify-content-start mt-2">
                                                <a href="<?= htmlspecialchars($slide['button_link'] ?: '#catalog') ?>" class="btn rounded-pill px-3 py-1 fw-semibold text-white d-inline-flex align-items-center gap-1" style="background-color: var(--gold-accent); border: 1px solid var(--gold-accent); font-size: 0.7rem; transition: all 0.3s ease;">
                                                    <?= htmlspecialchars($slide['button_text']) ?> <i class="bi bi-chevron-right ms-1" style="font-size: 0.6rem;"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Full banner image is clickable if no text is present -->
                            <a href="<?= htmlspecialchars($slide['button_link'] ?: '#catalog') ?>" class="position-absolute z-1 w-100 h-100 top-0 start-0" aria-label="Explore Banner Link"></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- Mobile Quick Navigation links (New Arrivals, Wishlist, Catalogue, Login) -->
<div class="d-block d-md-none bg-white py-3 border-bottom mb-4">
    <div class="container d-flex align-items-center justify-content-between">
        <!-- New Arrivals -->
        <a href="#new-arrivals" class="text-decoration-none text-center d-flex flex-column align-items-center text-dark flex-grow-1">
            <div class="d-flex align-items-center justify-content-center rounded-circle shadow-sm" style="width: 44px; height: 44px; background-color: #FAF8F5; border: 1px solid #E5E2DC;">
                <i class="bi bi-lightning-charge text-dark" style="font-size: 1.2rem;"></i>
            </div>
            <span class="text-dark fw-medium mt-1" style="font-size: 0.65rem; font-family: var(--paragraph-font), sans-serif;">New Arrivals</span>
        </a>
        
        <!-- Wishlist -->
        <a href="wishlist.php" class="text-decoration-none text-center d-flex flex-column align-items-center text-dark flex-grow-1 position-relative">
            <div class="d-flex align-items-center justify-content-center rounded-circle shadow-sm" style="width: 44px; height: 44px; background-color: #FAF8F5; border: 1px solid #E5E2DC;">
                <i class="bi bi-heart text-dark" style="font-size: 1.2rem;"></i>
            </div>
            <span id="wishlist-badge-mobile-quick" class="badge rounded-circle position-absolute wishlist-badge d-none" style="font-size: 0.52rem; padding: 2px 5px; background-color: var(--gold-accent); color: #FFFFFF; border: 1.5px solid #fff; top: -2px; right: calc(50% - 22px);">0</span>
            <span class="text-dark fw-medium mt-1" style="font-size: 0.65rem; font-family: var(--paragraph-font), sans-serif;">Wishlist</span>
        </a>
        
        <!-- Catalogue -->
        <a href="#catalog" class="text-decoration-none text-center d-flex flex-column align-items-center text-dark flex-grow-1">
            <div class="d-flex align-items-center justify-content-center rounded-circle shadow-sm" style="width: 44px; height: 44px; background-color: #FAF8F5; border: 1px solid #E5E2DC;">
                <i class="bi bi-grid" style="font-size: 1.2rem;"></i>
            </div>
            <span class="text-dark fw-medium mt-1" style="font-size: 0.65rem; font-family: var(--paragraph-font), sans-serif;">Catalogue</span>
        </a>
        
        <!-- Login -->
        <a href="login.php" class="text-decoration-none text-center d-flex flex-column align-items-center text-dark flex-grow-1">
            <div class="d-flex align-items-center justify-content-center rounded-circle shadow-sm" style="width: 44px; height: 44px; background-color: #FAF8F5; border: 1px solid #E5E2DC;">
                <i class="bi bi-person" style="font-size: 1.2rem;"></i>
            </div>
            <span class="text-dark fw-medium mt-1" style="font-size: 0.65rem; font-family: var(--paragraph-font), sans-serif;">Login</span>
        </a>
    </div>
</div>

<!-- Category Grid Section ("SHOP BY CATEGORY") -->
<?php if ($showCategories && (!empty($categories_list_desktop) || !empty($categories_list_mobile))): ?>
<section class="idx-category py-4 bg-white border-bottom border-light">
    <div class="container category-section-container">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <!-- Dynamic Category Grid Title -->
                <h5 class="fw-bold mb-1 text-dark section-heading" style="font-family: var(--section-heading-font), sans-serif; font-size: var(--section-heading-font-size); text-transform: uppercase; letter-spacing: 0.5px;"><?= htmlspecialchars($categorySectionTitle) ?></h5>
            </div>
            <a href="shop.php" class="text-decoration-none small fw-semibold text-muted" style="font-size:0.78rem;">View All Categories <i class="bi bi-arrow-right" style="font-size:0.7rem;"></i></a>
        </div>
        
        <!-- Desktop Grid: d-none d-md-flex -->
        <div class="row d-none d-md-flex row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-<?= htmlspecialchars($categoryRowCount) ?> g-2 g-md-3">
            <?php 
            $catCount = 0;
            foreach ($categories_list_desktop as $cat): 
                $catCount++;
                if ($catCount > 9) break; // Limit to 9 categories plus 1 view all card
                $catImg = !empty($cat['image_path']) ? htmlspecialchars($cat['image_path']) : 'uploads/categories/1765362506_6194.png';
            ?>
                <div class="col">
                    <a href="shop.php?cat=<?= $cat['id'] ?>" class="category-grid-item">
                        <div class="category-circle-card py-2 px-1">
                            <div class="category-img-wrapper mb-2">
                                <img src="<?= $catImg ?>" alt="<?= htmlspecialchars($cat['name']) ?>" loading="lazy" decoding="async" width="80" height="80">
                            </div>
                            <h6 class="fw-bold text-dark text-center w-100 mb-1" style="font-size: var(--shop-cat-title-font-size); font-family: var(--shop-cat-title-font); overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.25; word-break: break-word;"><?= htmlspecialchars($cat['name']) ?></h6>
                             <span class="text-muted text-center w-100 d-none d-md-block" style="font-size: 0.72rem; font-weight: 500; font-family: var(--category-font), sans-serif;"><?= (int)($cat['subcat_count'] ?? 0) ?> <?= ((int)($cat['subcat_count'] ?? 0) === 1) ? 'Subcategory' : 'Subcategories' ?></span>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
            
            <!-- View All Categories Card (Desktop only) -->
            <div class="col">
                <a href="shop.php" class="category-grid-item">
                    <div class="category-circle-card py-2 px-1">
                        <div class="category-img-wrapper mb-2">
                            <div class="d-flex align-items-center justify-content-center text-dark" style="font-size: 2.2rem; height: 120px; width: 100%;">
                                <i class="bi bi-grid" style="color: var(--text-dark);"></i>
                            </div>
                        </div>
                        <h6 class="fw-bold text-dark text-center w-100 mb-1" style="font-size: var(--shop-cat-title-font-size); font-family: var(--shop-cat-title-font); overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.25; word-break: break-word;">View All Categories</h6>
                        <div class="d-flex align-items-center justify-content-center rounded-circle shadow-sm" style="width: 24px; height: 24px; background-color: var(--forest-green); color: white; margin: 0 auto;">
                            <i class="bi bi-arrow-right-short fs-6"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Mobile Grid: d-flex d-md-none -->
        <div class="row d-flex d-md-none row-cols-2 row-cols-sm-3 row-cols-<?= htmlspecialchars($categoryRowCountMobile) ?> g-2">
            <?php 
            $catCount = 0;
            foreach ($categories_list_mobile as $cat): 
                $catCount++;
                if ($catCount > 5) break; // Limit to 5 items on mobile
                $catImg = !empty($cat['image_path']) ? htmlspecialchars($cat['image_path']) : 'uploads/categories/1765362506_6194.png';
            ?>
                <div class="col">
                    <a href="shop.php?cat=<?= $cat['id'] ?>" class="category-grid-item">
                        <div class="category-circle-card py-2 px-1">
                            <div class="category-img-wrapper mb-1">
                                <img src="<?= $catImg ?>" alt="<?= htmlspecialchars($cat['name']) ?>" loading="lazy" decoding="async" width="80" height="80">
                            </div>
                            <h6 class="fw-bold text-dark text-center w-100 mb-0 px-1" style="font-size: var(--shop-cat-title-font-size-mobile); font-family: var(--shop-cat-title-font); overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.2; word-break: break-word;"><?= htmlspecialchars($cat['name']) ?></h6>
                            <span class="text-muted text-center w-100 mt-1" style="font-size: 0.55rem; font-weight: 500; font-family: var(--category-font), sans-serif;"><?= (int)($cat['subcat_count'] ?? 0) ?> <?= ((int)($cat['subcat_count'] ?? 0) === 1) ? 'Subcategory' : 'Subcategories' ?></span>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
            
            <!-- View All Categories Card (Mobile only) -->
            <div class="col">
                <a href="shop.php" class="category-grid-item">
                    <div class="category-circle-card py-2 px-1">
                        <div class="category-img-wrapper mb-1">
                            <div class="d-flex align-items-center justify-content-center text-dark" style="font-size: 1.5rem; height: 100%; width: 100%;">
                                <i class="bi bi-grid" style="color: var(--text-dark);"></i>
                            </div>
                        </div>
                        <h6 class="fw-bold text-dark text-center w-100 mb-0 px-1" style="font-size: var(--shop-cat-title-font-size-mobile); font-family: var(--shop-cat-title-font); overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; line-height: 1.2; word-break: break-word;">View All Categories</h6>
                        <div class="d-flex align-items-center justify-content-center rounded-circle mt-1" style="width: 20px; height: 20px; background-color: var(--forest-green); color: white; margin: 0 auto;">
                            <i class="bi bi-arrow-right-short" style="font-size: 0.85rem; line-height: 1;"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>
     </div>
</section>
<?php endif; ?>

<!-- Mobile Trust Badges Ribbon (Shown only on mobile) -->
<div class="d-flex d-md-none overflow-x-auto gap-3 py-2 px-2 border-top border-bottom bg-white justify-content-between align-items-center mb-3 scroll-container" style="scrollbar-width: none; -ms-overflow-style: none;">
    <div class="d-flex align-items-center gap-1 flex-shrink-0" style="font-size: 0.62rem; color: #1e2221;">
        <i class="bi bi-lightning-charge-fill text-warning"></i>
        <span class="fw-semibold">Ready Stock Quick Dispatch</span>
    </div>
    <div class="d-flex align-items-center gap-1 flex-shrink-0" style="font-size: 0.62rem; color: #1e2221;">
        <i class="bi bi-truck text-warning"></i>
        <span class="fw-semibold">Pan India Delivery</span>
    </div>
    <div class="d-flex align-items-center gap-1 flex-shrink-0" style="font-size: 0.62rem; color: #1e2221;">
        <i class="bi bi-currency-rupee text-warning"></i>
        <span class="fw-semibold">Best Wholesale Prices</span>
    </div>
    <div class="d-flex align-items-center gap-1 flex-shrink-0" style="font-size: 0.62rem; color: #1e2221;">
        <i class="bi bi-patch-check-fill text-warning"></i>
        <span class="fw-semibold">100% 925 Sterling Silver</span>
    </div>
</div>

<!-- Desktop Value Ribbon (Shown only on desktop) -->
<section class="d-none d-md-block my-4">
    <div class="container category-section-container">
        <div class="py-4 px-3 px-lg-4 shadow-sm text-white d-flex align-items-center justify-content-between" style="background-color: var(--forest-green); border-radius: 24px; border: 1px solid var(--light-border);">
            <div class="row w-100 align-items-center text-start g-3 g-lg-0">
                <!-- Item 1 -->
                <div class="col-6 col-lg-3 d-flex align-items-center justify-content-center border-end-lg border-white border-opacity-10 py-2">
                    <div class="d-flex align-items-center gap-2 gap-xl-3">
                        <i class="bi bi-gem fs-2" style="color: var(--gold-accent); line-height: 1;"></i>
                        <div>
                            <h6 class="mb-0 fw-bold text-white font-sans" style="font-size: clamp(0.95rem, 1.1vw, 1.15rem); letter-spacing: 0.5px;">250+</h6>
                            <p class="mb-0 text-white-50 font-sans" style="font-size: 0.75rem; font-weight: 500;">Unique Designs</p>
                        </div>
                    </div>
                </div>
                <!-- Item 2 -->
                <div class="col-6 col-lg-3 d-flex align-items-center justify-content-center border-end-lg border-white border-opacity-10 py-2">
                    <div class="d-flex align-items-center gap-2 gap-xl-3">
                        <i class="bi bi-patch-check fs-2" style="color: var(--gold-accent); line-height: 1;"></i>
                        <div>
                            <h6 class="mb-0 fw-bold text-white font-sans" style="font-size: clamp(0.95rem, 1.1vw, 1.15rem); letter-spacing: 0.5px;">100%</h6>
                            <p class="mb-0 text-white-50 font-sans" style="font-size: 0.75rem; font-weight: 500;">925 Sterling Silver</p>
                        </div>
                    </div>
                </div>
                <!-- Item 3 -->
                <div class="col-6 col-lg-3 d-flex align-items-center justify-content-center border-end-lg border-white border-opacity-10 py-2">
                    <div class="d-flex align-items-center gap-2 gap-xl-3">
                        <i class="bi bi-lightning-charge fs-2" style="color: var(--gold-accent); line-height: 1;"></i>
                        <div>
                            <h6 class="mb-0 fw-bold text-white font-sans" style="font-size: clamp(0.95rem, 1.1vw, 1.15rem); letter-spacing: 0.5px;">Ready Stock</h6>
                            <p class="mb-0 text-white-50 font-sans" style="font-size: 0.75rem; font-weight: 500;">Quick Dispatch</p>
                        </div>
                    </div>
                </div>
                <!-- Item 4 -->
                <div class="col-6 col-lg-3 d-flex align-items-center justify-content-center py-2">
                    <div class="d-flex align-items-center gap-2 gap-xl-3">
                        <i class="bi bi-people fs-2" style="color: var(--gold-accent); line-height: 1;"></i>
                        <div>
                            <h6 class="mb-0 fw-bold text-white font-sans" style="font-size: clamp(0.95rem, 1.1vw, 1.15rem); letter-spacing: 0.5px;">Trusted by</h6>
                            <p class="mb-0 text-white-50 font-sans" style="font-size: 0.75rem; font-weight: 500;">5000+ Retailers</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- New Arrivals Section -->
<?php if (!empty($new_arrivals)): ?>
<section id="new-arrivals" class="idx-new-arrivals py-4 bg-white">
    <div class="container category-section-container">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <!-- Dynamic New Arrivals Title -->
                <h5 class="fw-bold mb-1 text-dark section-heading" style="font-family: var(--section-heading-font), sans-serif; font-size: var(--section-heading-font-size); text-transform: uppercase; letter-spacing: 0.5px;"><?= htmlspecialchars($newArrivalsSectionTitle) ?></h5>
            </div>
            <a href="<?= !empty($new_arrivals_cat_id) ? 'shop.php?cat=' . intval($new_arrivals_cat_id) : 'shop.php' ?>" class="text-decoration-none small fw-semibold text-muted" style="font-size:0.78rem;">View All New Arrivals <i class="bi bi-arrow-right" style="font-size:0.7rem;"></i></a>
        </div>
        
        <!-- Horizontal Scrollable Container (exactly 5 cards fit desktop view width) -->
        <div class="d-flex overflow-x-auto gap-3 pb-3 scroll-container" id="new-arrivals-scroll-container" style="scrollbar-width: none; -ms-overflow-style: none; scroll-snap-type: x mandatory; -webkit-scroll-snap-type: mandatory; -webkit-overflow-scrolling: touch;">
            <?php foreach ($new_arrivals as $d): ?>
                <?php 
                $displayName = !empty($d['sku']) ? ($d['product_sku'] ?? '') . '-' . $d['sku'] . '_ ' . $d['design_name'] : $d['design_name'];
                $weightText = floatval($d['weight']) . 'gm';
                if (stripos($d['product_name'], 'Toe Ring') !== false) {
                    $weightText = 'Toe Ring ' . $weightText;
                }
                ?>
                <div style="min-width: 220px; width: calc(20% - 12px); flex-shrink: 0; scroll-snap-align: start;">
                    <div class="card border border-light-subtle shadow-sm rounded-4 h-100 overflow-hidden hover-lift position-relative" style="background-color: #FCFAF7 !important; cursor: pointer;" onclick="openProductDetailModal(<?= e(json_encode([
                        'id' => $d['id'] ?? 0,
                        'sku' => ($d['product_sku'] ?? '') . '-' . ($d['sku'] ?: 'N/A'),
                        'title' => !empty($d['product_name']) ? $d['product_name'] : 'New Arrival',
                        'weight' => number_format(floatval($d['weight'] ?? 0), 3) . ' gm (Gross Weight)',
                        'stock_qty' => (int)($d['stock_qty'] ?? 1),
                        'images' => $d['image_path'] ?: '',
                        'product_name' => $d['product_name'] ?: ''
                    ])) ?>)">
                        <!-- NEW Badge -->
                        <div class="position-absolute top-0 start-0 m-3 z-2">
                            <span class="badge" style="background-color: var(--rose-gold); color: #FFFFFF; font-size: 0.65rem; border-radius: 4px; padding: 4px 8px; font-weight: 600; letter-spacing: 0.5px;">NEW</span>
                        </div>
                        
                        <!-- Image Container -->
                        <div class="position-relative design-card-img-container" style="height: 180px; background-color: #FCFAF7 !important;">
                            <?php if(!empty($d['image_path'])): ?>
                                <img src="<?= e($d['image_path']) ?>" alt="<?= htmlspecialchars($d['design_name']) ?>" loading="lazy" decoding="async" width="200" height="180" class="w-100 h-100 object-fit-contain p-2 rounded-4 transition-scale">
                            <?php else: ?>
                                <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image fs-1 opacity-25" aria-hidden="true"></i></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Card Details -->
                        <div class="card-body p-3 text-start d-flex flex-column justify-content-between">
                            <div>
                                <h6 class="fw-bold text-dark mb-0 text-truncate font-sans" style="font-size: 0.88rem;" title="<?= e($d['design_name']) ?>">
                                    <?= htmlspecialchars($d['design_name']) ?>
                                </h6>
                                <small class="text-uppercase text-muted fw-semibold d-block text-truncate" style="letter-spacing: 0.5px; font-size: 0.65rem; margin-top: 2px;"><?= e($d['subcategory_name'] ?? $d['product_name']) ?></small>
                            </div>
                            
                            <!-- Split row containing weight and wishlist heart icon -->
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="text-secondary font-sans" style="font-size: 0.78rem; font-weight: 500;">
                                    <?= htmlspecialchars($weightText) ?>
                                </span>
                                <button type="button" class="btn btn-link text-decoration-none text-muted p-0 shadow-none wishlist-heart-btn" data-design-id="<?= $d['id'] ?>" title="Add to Wishlist" onclick="event.stopPropagation(); toggleWishlist(this, <?= $d['id'] ?>);">
                                    <i class="bi bi-heart" style="font-size: 1rem; color: var(--gold-accent);"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Scroll Pagination Dots -->
        <div class="d-flex justify-content-center gap-2 mt-3" id="new-arrivals-dots">
            <?php for ($idx = 0; $idx < 5; $idx++): ?>
                <span class="scroll-dot" style="width: 8px; height: 8px; border-radius: 50%; background-color: <?= $idx === 0 ? '#1B1B1B' : '#E5E2DC' ?>; display: inline-block; transition: all 0.3s ease; cursor: pointer; transform: <?= $idx === 0 ? 'scale(1.2)' : 'scale(1)' ?>;" data-dot-index="<?= $idx ?>"></span>
            <?php endfor; ?>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('new-arrivals-scroll-container');
    const dots = document.querySelectorAll('#new-arrivals-dots .scroll-dot');
    if (container && dots.length > 0) {
        container.addEventListener('scroll', function() {
            const maxScrollLeft = container.scrollWidth - container.clientWidth;
            if (maxScrollLeft <= 0) return;
            const percentage = container.scrollLeft / maxScrollLeft;
            const activeIndex = Math.min(4, Math.max(0, Math.round(percentage * 4)));
            dots.forEach((dot, idx) => {
                if (idx === activeIndex) {
                    dot.style.backgroundColor = '#1B1B1B';
                    dot.style.transform = 'scale(1.2)';
                } else {
                    dot.style.backgroundColor = '#E5E2DC';
                    dot.style.transform = 'scale(1)';
                }
            });
        });
        dots.forEach((dot, idx) => {
            dot.addEventListener('click', function() {
                const maxScrollLeft = container.scrollWidth - container.clientWidth;
                const targetScrollLeft = (idx / 4) * maxScrollLeft;
                container.scrollTo({
                    left: targetScrollLeft,
                    behavior: 'smooth'
                });
            });
        });
    }
});
</script>
<?php endif; ?>

<!-- 4 Promotional Banners Section -->
<section class="idx-promo py-4 bg-light border-top border-bottom border-light-subtle">
    <div class="container category-section-container">
        <div class="row justify-content-center">
            <?php for($i = 0; $i < 4; $i++): ?>
                <?php 
                $banner = $promo_banners_data[$i] ?? ['button_link'=>'', 'image_path'=>'']; 
                ?>
                <div class="col-lg-3 col-md-6 mb-3 d-flex justify-content-center">
                    <a href="<?= htmlspecialchars($banner['button_link'] ?: '#catalog') ?>" class="d-block w-100 hover-lift" style="height: 100%; min-height: 250px; border-radius: 18px; overflow: hidden; box-shadow: var(--card-shadow); border: 1px solid var(--light-border);">
                        <?php if (!empty($banner['image_path'])): ?>
                            <img src="<?= htmlspecialchars($banner['image_path']) ?>" alt="Promo Banner <?= $i + 1 ?>" loading="lazy" decoding="async" width="364" height="306" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                        <?php else: ?>
                            <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-white" style="background: linear-gradient(135deg, var(--forest-green) 0%, #0c2d27 100%);">
                                <i class="bi bi-image fs-1 opacity-50 mb-2"></i>
                                <span class="small fw-semibold">Promo Banner <?= $i + 1 ?></span>
                                <span class="text-white-50" style="font-size: 0.65rem;">364 x 306 px</span>
                            </div>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<section id="why-choose-us" class="idx-why-us py-4 bg-white">
    <div class="container category-section-container">
        <div class="d-none d-md-flex justify-content-between align-items-end mb-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark text-uppercase section-heading" style="font-family: var(--section-heading-font), sans-serif; font-size: var(--section-heading-font-size); letter-spacing: 0.5px;">Why Choose Silvee925?</h5>
            </div>
        </div>
        <div class="d-flex d-md-none justify-content-between align-items-end mb-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark text-uppercase section-heading" style="font-family: var(--section-heading-font), sans-serif; font-size: 0.95rem; letter-spacing: 0.5px;">Features</h5>
            </div>
        </div>
        
        <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-5 g-3 justify-content-center d-none d-md-flex">
            <div class="col">
                <div class="choose-box">
                    <div class="choose-box-icon" style="width: 40px; height: 40px; font-size:1.2rem;">
                        <i class="bi bi-gem"></i>
                    </div>
                    <h6 style="font-size:0.78rem;">Premium Quality</h6>
                    <p style="font-size:0.65rem;">Finest sterling silver</p>
                </div>
            </div>
            <div class="col">
                <div class="choose-box">
                    <div class="choose-box-icon" style="width: 40px; height: 40px; font-size:1.2rem;">
                        <i class="bi bi-award"></i>
                    </div>
                    <h6 style="font-size:0.78rem;">Latest & Exclusive</h6>
                    <p style="font-size:0.65rem;">Ahead of trends</p>
                </div>
            </div>
            <div class="col">
                <div class="choose-box">
                    <div class="choose-box-icon" style="width: 40px; height: 40px; font-size:1.2rem;">
                        <i class="bi bi-currency-rupee"></i>
                    </div>
                    <h6 style="font-size:0.78rem;">Best Wholesale Prices</h6>
                    <p style="font-size:0.65rem;">Unmatched value</p>
                </div>
            </div>
            <div class="col">
                <div class="choose-box">
                    <div class="choose-box-icon" style="width: 40px; height: 40px; font-size:1.2rem;">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h6 style="font-size:0.78rem;">Safe Packaging</h6>
                    <p style="font-size:0.65rem;">Zero damage delivery</p>
                </div>
            </div>
            <div class="col">
                <div class="choose-box">
                    <div class="choose-box-icon" style="width: 40px; height: 40px; font-size:1.2rem;">
                        <i class="bi bi-truck"></i>
                    </div>
                    <h6 style="font-size:0.78rem;">Pan India Delivery</h6>
                    <p style="font-size:0.65rem;">Fast, tracked shipping</p>
                </div>
            </div>
        </div>

        <div class="row row-cols-4 g-2 justify-content-center d-flex d-md-none">
            <div class="col">
                <div class="choose-box p-1 border rounded-3 bg-white h-100" style="border-color: #E5E2DC !important;">
                    <div class="choose-box-icon mx-auto my-1" style="width: 32px; height: 32px; font-size: 0.95rem; background-color: #FAF5F0 !important;">
                        <i class="bi bi-gem"></i>
                    </div>
                    <h6 class="mb-0 fw-bold" style="font-size:0.55rem; line-height: 1.15; color:#1b1b1b;">250+<br>Designs</h6>
                </div>
            </div>
            <div class="col">
                <div class="choose-box p-1 border rounded-3 bg-white h-100" style="border-color: #E5E2DC !important;">
                    <div class="choose-box-icon mx-auto my-1" style="width: 32px; height: 32px; font-size: 0.95rem; background-color: #FAF5F0 !important;">
                        <i class="bi bi-patch-check"></i>
                    </div>
                    <h6 class="mb-0 fw-bold" style="font-size:0.55rem; line-height: 1.15; color:#1b1b1b;">925<br>Silver</h6>
                </div>
            </div>
            <div class="col">
                <div class="choose-box p-1 border rounded-3 bg-white h-100" style="border-color: #E5E2DC !important;">
                    <div class="choose-box-icon mx-auto my-1" style="width: 32px; height: 32px; font-size: 0.95rem; background-color: #FAF5F0 !important;">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                    <h6 class="mb-0 fw-bold" style="font-size:0.55rem; line-height: 1.15; color:#1b1b1b;">Ready<br>Stock</h6>
                </div>
            </div>
            <div class="col">
                <div class="choose-box p-1 border rounded-3 bg-white h-100" style="border-color: #E5E2DC !important;">
                    <div class="choose-box-icon mx-auto my-1" style="width: 32px; height: 32px; font-size: 0.95rem; background-color: #FAF5F0 !important;">
                        <i class="bi bi-truck"></i>
                    </div>
                    <h6 class="mb-0 fw-bold" style="font-size:0.55rem; line-height: 1.15; color:#1b1b1b;">Pan India<br>Delivery</h6>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($showCategories): ?>
<section id="catalog" class="idx-catalog py-4 bg-light border-top">
    <div class="container category-section-container">
        
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark section-heading" style="font-family: var(--section-heading-font), sans-serif; font-size: var(--section-heading-font-size); text-transform: uppercase; letter-spacing: 0.5px;"><?= htmlspecialchars($catalogSectionTitle) ?></h5>
            </div>
        </div>

        <?php if(empty($parents) && $total_designs === 0): ?>
            <div class="text-center py-4 border rounded-4 bg-white shadow-sm">
                <i class="bi bi-box-seam display-2 text-muted opacity-25 mb-3 d-block"></i>
                <h5 class="fw-bold font-serif">Store Catalog is Empty</h5>
                <p class="text-muted small">No collections or designs are currently active.</p>
            </div>
        <?php endif; ?>

        <?php foreach($parents as $pCat): ?>
            <?php 
            $subCats = $children[$pCat['id']] ?? []; 
            
            $vis = $catalog_visibility[$pCat['id']] ?? 'show';
            if ($vis === 'hide') continue;
            
            if (empty($subCats)) continue;
            ?>
            
            <div class="mb-5 pb-3 border-bottom border-light">
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <div>
                        <h5 class="fw-bold mb-1 text-dark subsection-title" style="font-family: var(--subsection-font), sans-serif; font-size: var(--subsection-font-size); text-transform: uppercase; letter-spacing: 0.5px;">
                            <?= e($pCat['name']) ?>
                        </h5>
                    </div>
                    <a href="shop.php?cat=<?= $pCat['id'] ?>" class="text-decoration-none small fw-semibold text-muted" style="font-size:0.78rem;">View All <i class="bi bi-arrow-right" style="font-size:0.7rem;"></i></a>
                </div>
                
                <?php if(!empty($subCats)): ?>
                    <div class="row row-cols-2 row-cols-md-4 g-2 g-md-3 mb-3">
                        <?php 
                        usort($subCats, function($a, $b) {
                            return (int)$b['id'] - (int)$a['id'];
                        });
                        
                        $subCount = 0;
                        foreach($subCats as $subCat): 
                            $subAgg = $categoryDesignAgg[$subCat['id']] ?? ['count' => 0, 'image' => ''];
                            
                            $subCount++;
                            if($subCount > 4) break;
                            
                            $subImg = !empty($subCat['image_path']) ? htmlspecialchars($subCat['image_path']) : '';
                            if (empty($subImg) && !empty($subAgg['image'])) {
                                $subImg = htmlspecialchars($subAgg['image']);
                            }
                            if (empty($subImg)) {
                                $subImg = 'uploads/categories/1765362506_6194.png';
                            }
                        ?>
                            <div class="col">
                                <a href="shop.php?cat=<?= $subCat['id'] ?>" class="text-decoration-none">
                                    <div class="catalog-subcat-card">
                                        <div class="catalog-subcat-card-img-wrapper">
                                            <img src="<?= $subImg ?>" alt="<?= htmlspecialchars($subCat['name']) ?>" loading="lazy" decoding="async" width="250" height="250">
                                        </div>
                                        <div class="catalog-subcat-card-body">
                                            <h6 class="catalog-subcat-card-title"><?= htmlspecialchars($subCat['name']) ?></h6>
                                            <span class="catalog-subcat-card-subtext"><?= $subAgg['count'] ?> <?= $subAgg['count'] == 1 ? 'Design' : 'Designs' ?></span>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>


            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<div class="modal fade" id="enquiryModal" tabindex="-1" aria-labelledby="enquiryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background-color: #FCFAF7;">
            <div class="modal-header border-0 bg-light pb-0 d-flex flex-column align-items-center pt-4">
                <h4 class="modal-title fw-bold" id="enquiryModalLabel" style="font-family: 'Playfair Display', serif; color: var(--forest-green);">Quick Enquiry</h4>
                <span class="text-uppercase text-secondary font-weight-bold" style="font-size: 0.62rem; letter-spacing: 2px;">925 Sterling Silver Wholesaler</span>
            </div>
            <div class="modal-body p-4 pt-3">
                <form id="enquiryForm">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Your Shop Name</label>
                        <input type="text" class="form-control" name="enquiry_shop_name" id="enquiry_shop_name" required placeholder="e.g. Silvee Retailers" value="<?= isset($_SESSION['customer_username']) ? htmlspecialchars($_SESSION['customer_username']) : '' ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Your Mobile Number</label>
                        <input type="text" class="form-control" name="enquiry_mobile" id="enquiry_mobile" required placeholder="10-digit mobile number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Inquiry Details / Message</label>
                        <textarea class="form-control" name="enquiry_message" id="enquiry_message" rows="3" required placeholder="What products are you looking for? (e.g. Kadas, Studs, Toe Rings)"></textarea>
                    </div>
                    <button type="submit" class="btn btn-forest w-100 fw-medium shadow-sm rounded-pill py-2">
                        <i class="bi bi-whatsapp me-2"></i>Send via WhatsApp
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const enquiryForm = document.getElementById('enquiryForm');
    if (enquiryForm) {
        enquiryForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const shopName = document.getElementById('enquiry_shop_name').value;
            const mobile = document.getElementById('enquiry_mobile').value;
            const message = document.getElementById('enquiry_message').value;
            
            const text = `Hello Silvee925!%0A%0AI want to make a quick wholesale enquiry.%0A%0A*Shop Name:* ${encodeURIComponent(shopName)}%0A*Mobile:* ${encodeURIComponent(mobile)}%0A*Enquiry:* ${encodeURIComponent(message)}`;
            const waUrl = `https://wa.me/<?= htmlspecialchars($whatsappNumber) ?>?text=${text}`;
            
            window.open(waUrl, '_blank');
            
            const modalEl = document.getElementById('enquiryModal');
            const modalObj = bootstrap.Modal.getInstance(modalEl);
            if (modalObj) {
                modalObj.hide();
            }
        });
    }
});
</script>

<?php require_once "store_footer.php"; ?>
