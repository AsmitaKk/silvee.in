<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch Theme Settings
if (!isset($themeSettings) || empty($themeSettings)) {
    $themeSettings = [];
    require_once __DIR__ . "/config.php";
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM theme_settings");
        while ($row = $stmt->fetch()) {
            $themeSettings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        // Fail-safe defaults
    }
}
$brand_logo = $themeSettings['brand_logo'] ?? '';
$primary_color = $themeSettings['primary_color'] ?? '#0D3B36';
$accent_color = $themeSettings['banner_accent_color'] ?? '#C8A96B';
$logo_height_desktop = !empty($themeSettings['logo_height_desktop']) ? intval($themeSettings['logo_height_desktop']) : 50;
$logo_height_mobile = !empty($themeSettings['logo_height_mobile']) ? intval($themeSettings['logo_height_mobile']) : 38;

// Fetch Main Categories for Mobile Menu
$main_categories_menu = [];
try {
    $main_categories_menu = $pdo->query("SELECT id, name FROM categories WHERE (parent_id IS NULL OR parent_id = 0) AND (is_hidden IS NULL OR is_hidden = 0) ORDER BY name")->fetchAll();
} catch (Exception $e) {}

// Check if cart has items
$cart_count = 0;
if(isset($_SESSION['cart'])) {
    foreach($_SESSION['cart'] as $item) {
        $cart_count += (int)$item['qty'];
    }
}

$is_customer_logged_in = isset($_SESSION['customer_id']);

// Default top bar texts
$topbar1 = $themeSettings['topbar_text_1'] ?? "India's Trusted 925 Silver Wholesaler";
$topbar2 = $themeSettings['topbar_text_2'] ?? "100% 925 Sterling Silver";
$topbar3 = $themeSettings['topbar_text_3'] ?? "Premium Quality Guarantee";
$wa_number = $themeSettings['whatsapp_number'] ?? '917030033819';
$wa_text = "Hello! I want to inquire about your sterling silver jewellery.";

if (isset($_SESSION['is_new_registration']) && $_SESSION['is_new_registration'] && !empty($_SESSION['visiting_card_url'])) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $current_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $card_url = $protocol . $host . rtrim($current_dir, '/') . '/' . ltrim($_SESSION['visiting_card_url'], '/');
    $wa_text .= "\n\nHere is my visiting card: " . $card_url;
}

$wa_link = "https://wa.me/{$wa_number}?text=" . rawurlencode($wa_text);

// Fetch Dynamic Font choices
$heading_font = $themeSettings['heading_font'] ?? 'Playfair Display';
$paragraph_font = $themeSettings['paragraph_font'] ?? 'Inter';
$header_font = $themeSettings['header_font'] ?? 'Playfair Display';
$footer_font = $themeSettings['footer_font'] ?? 'Inter';
$category_font = $themeSettings['category_font'] ?? 'Playfair Display';
$subsection_font = $themeSettings['subsection_font'] ?? 'Playfair Display';
$section_heading_font = $themeSettings['section_heading_font'] ?? 'Outfit';
$shop_cat_title_font = $themeSettings['shop_cat_title_font'] ?? 'Playfair Display';

$heading_font_size = $themeSettings['heading_font_size'] ?? '28';
$heading_font_size_mobile = $themeSettings['heading_font_size_mobile'] ?? '18';
$paragraph_font_size = $themeSettings['paragraph_font_size'] ?? '16';
$paragraph_font_size_mobile = $themeSettings['paragraph_font_size_mobile'] ?? '14';
$category_font_size = $themeSettings['category_font_size'] ?? '15';
$category_font_size_mobile = $themeSettings['category_font_size_mobile'] ?? '12';
$subsection_font_size = $themeSettings['subsection_font_size'] ?? '18';
$subsection_font_size_mobile = $themeSettings['subsection_font_size_mobile'] ?? '15';
$section_heading_font_size = $themeSettings['section_heading_font_size'] ?? '18';
$section_heading_font_size_mobile = $themeSettings['section_heading_font_size_mobile'] ?? '14';
$header_font_size = $themeSettings['header_font_size'] ?? '15';
$header_font_size_mobile = $themeSettings['header_font_size_mobile'] ?? '13';
$footer_font_size = $themeSettings['footer_font_size'] ?? '14';
$footer_font_size_mobile = $themeSettings['footer_font_size_mobile'] ?? '12';

$heading_font_style = $themeSettings['heading_font_style'] ?? '';
$paragraph_font_style = $themeSettings['paragraph_font_style'] ?? '';
$category_font_style = $themeSettings['category_font_style'] ?? '';
$subsection_font_style = $themeSettings['subsection_font_style'] ?? '';
$section_heading_font_style = $themeSettings['section_heading_font_style'] ?? '';
$header_font_style = $themeSettings['header_font_style'] ?? '';
$footer_font_style = $themeSettings['footer_font_style'] ?? '';

// Sidemenu Settings
$sidemenu_font = $themeSettings['sidemenu_font'] ?? 'Inter';
$sidemenu_bg_color = $themeSettings['sidemenu_bg_color'] ?? '#FCFAF5';
$sidemenu_font_color = $themeSettings['sidemenu_font_color'] ?? '#1A1A1A';
$sidemenu_font_size = $themeSettings['sidemenu_font_size'] ?? '15';
$sidemenu_heading_font_size = $themeSettings['sidemenu_heading_font_size'] ?? '12';
$sidemenu_item_spacing = $themeSettings['sidemenu_item_spacing'] ?? '2';
$sidemenu_heading_font_style = $themeSettings['sidemenu_heading_font_style'] ?? '';
$sidemenu_menu_font_style = $themeSettings['sidemenu_menu_font_style'] ?? '';
$sidemenu_banner_img = $themeSettings['sidemenu_banner_img'] ?? '';
$sidemenu_banner_height = $themeSettings['sidemenu_banner_height'] ?? 'auto';
$sidemenu_banner_width = $themeSettings['sidemenu_banner_width'] ?? '100%';

// Build Google Fonts import query (Optimized weights for performance)
$unique_fonts = array_unique([$heading_font, $paragraph_font, $header_font, $footer_font, $category_font, $subsection_font, $section_heading_font, $shop_cat_title_font, $sidemenu_font]);
$font_import_urls = [];
foreach ($unique_fonts as $f) {
    $font_import_urls[] = "family=" . str_replace(' ', '+', $f) . ":wght@400;600;700";
}
$fonts_query = implode('&', $font_import_urls);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>Silvee 925 - Premium 925 Sterling Silver Wholesale</title>
    <meta name="description" content="Discover Silvee 925's premium wholesale collection of 925 sterling silver jewellery. Direct manufacturer pricing on rings, earrings, necklaces, and accessories.">
    <meta name="keywords" content="925 silver wholesale, sterling silver jewellery wholesaler, b2b jewellery manufacturer">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
    <meta property="og:title" content="<?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>Silvee 925">
    <meta property="og:description" content="Premium 925 sterling silver jewellery wholesaler. Direct factory rates, ready stock, and fast shipping.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ?>">
    <?php if (!empty($brand_logo)): ?>
        <meta property="og:image" content="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/' . htmlspecialchars($brand_logo) ?>">
    <?php endif; ?>
    <!-- Preconnect CDN for High Speed -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" media="print" onload="this.media='all'">
    
    <!-- Dynamic Google Fonts Sourcing -->
    <link href="https://fonts.googleapis.com/css2?<?= $fonts_query ?>&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <?php
    // Preload first hero image for LCP optimization
    if (isset($hero_carousel_data[0]['image_path']) && !empty($hero_carousel_data[0]['image_path'])) {
        $first_hero_img = htmlspecialchars($hero_carousel_data[0]['image_path']);
        echo '    <link rel="preload" as="image" href="' . $first_hero_img . '" fetchpriority="high">' . "\n";
    }
    if (isset($hero_carousel_mobile_data[0]['image_path']) && !empty($hero_carousel_mobile_data[0]['image_path'])) {
        $first_hero_mobile_img = htmlspecialchars($hero_carousel_mobile_data[0]['image_path']);
        echo '    <link rel="preload" as="image" href="' . $first_hero_mobile_img . '" fetchpriority="high" media="(max-width: 767.98px)">' . "\n";
    }
    ?>

    <!-- Custom Store CSS -->
    <!-- <link rel="stylesheet" href="/assets/store.css"> -->
    <link rel="stylesheet" href="assets/store.css?v=<?= file_exists(__DIR__ . '/assets/store.css') ? filemtime(__DIR__ . '/assets/store.css') : '1.0' ?>">
    <!-- Apply Dynamic Fonts and Colors CSS Variables -->
    <style>
    :root {
        --forest-green: <?= htmlspecialchars($primary_color) ?>;
        --gold-accent: <?= htmlspecialchars($accent_color) ?>;
        --heading-font: '<?= htmlspecialchars($heading_font) ?>', serif;
        --paragraph-font: '<?= htmlspecialchars($paragraph_font) ?>', sans-serif;
        --header-font: '<?= htmlspecialchars($header_font) ?>', sans-serif;
        --footer-font: '<?= htmlspecialchars($footer_font) ?>', sans-serif;
        --category-font: '<?= htmlspecialchars($category_font) ?>', sans-serif;
        --subsection-font: '<?= htmlspecialchars($subsection_font) ?>', sans-serif;
        --section-heading-font: '<?= htmlspecialchars($section_heading_font) ?>', sans-serif;
        
        --heading-font-size: <?= htmlspecialchars($heading_font_size) ?>px;
        --paragraph-font-size: <?= htmlspecialchars($paragraph_font_size) ?>px;
        --header-font-size: <?= htmlspecialchars($header_font_size) ?>px;
        --footer-font-size: <?= htmlspecialchars($footer_font_size) ?>px;
        --category-font-size: <?= htmlspecialchars($category_font_size) ?>px;
        --subsection-font-size: <?= htmlspecialchars($subsection_font_size) ?>px;
        --section-heading-font-size: <?= htmlspecialchars($section_heading_font_size) ?>px;
        
        --sidemenu-font: '<?= htmlspecialchars($sidemenu_font) ?>', sans-serif;
        --sidemenu-bg-color: <?= htmlspecialchars($sidemenu_bg_color) ?>;
        --sidemenu-font-color: <?= htmlspecialchars($sidemenu_font_color) ?>;
        --sidemenu-font-size: <?= htmlspecialchars($sidemenu_font_size) ?>px;
        --sidemenu-heading-font-size: <?= htmlspecialchars($sidemenu_heading_font_size) ?>px;
        --sidemenu-item-spacing: <?= htmlspecialchars($sidemenu_item_spacing) ?>px;
    }

    @media (max-width: 991.98px) {
        :root {
            --heading-font-size: <?= htmlspecialchars($heading_font_size_mobile) ?>px;
            --paragraph-font-size: <?= htmlspecialchars($paragraph_font_size_mobile) ?>px;
            --header-font-size: <?= htmlspecialchars($header_font_size_mobile) ?>px;
            --footer-font-size: <?= htmlspecialchars($footer_font_size_mobile) ?>px;
            --category-font-size: <?= htmlspecialchars($category_font_size_mobile) ?>px;
            --subsection-font-size: <?= htmlspecialchars($subsection_font_size_mobile) ?>px;
            --section-heading-font-size: <?= htmlspecialchars($section_heading_font_size_mobile) ?>px;
        }
    }
    h1, h2, h3, h4, h5, h6, .font-serif, .hero-slide-heading {
        font-family: var(--heading-font) !important;
    }
    h1 { font-size: var(--heading-font-size) !important; }
    h2 { font-size: calc(var(--heading-font-size) * 0.85) !important; }
    h3 { font-size: calc(var(--heading-font-size) * 0.75) !important; }
    h4 { font-size: calc(var(--heading-font-size) * 0.65) !important; }
    h5 { font-size: calc(var(--heading-font-size) * 0.6) !important; }
    h6 { font-size: calc(var(--heading-font-size) * 0.55) !important; }

    body, p, .font-sans, .badge, small {
        font-family: var(--paragraph-font) !important;
    }
    body, p, .font-sans {
        font-size: var(--paragraph-font-size) !important;
    }

    .navbar, .navbar *, .mobile-header, .mobile-header *, .nav-link, .nav-icon-link {
        font-family: var(--header-font) !important;
    }
    .navbar, .nav-link, .nav-icon-link, .dropdown-item, .mobile-header {
        font-size: var(--header-font-size) !important;
    }

    footer, footer *, .premium-footer, .premium-footer * {
        font-family: var(--footer-font) !important;
    }
    footer, .premium-footer, .premium-footer p, .premium-footer a {
        font-size: var(--footer-font-size) !important;
    }

    .category-title, .category-circle-card h6, .category-circle-card span, .category-grid-item h6, .category-name, .square-category-title, .square-category-count, .subcategory-title, .design-card-title, h5.design-card-title {
        font-family: var(--category-font) !important;
    }
    .category-title, .category-circle-card h6, .category-grid-item h6, .category-name, .square-category-title, .subcategory-title, .design-card-title, h5.design-card-title {
        font-size: var(--category-font-size) !important;
    }
    .category-circle-card span, .square-category-count {
        font-size: calc(var(--category-font-size) * 0.75) !important;
    }
    .subsection-title {
        font-family: var(--subsection-font) !important;
        font-size: var(--subsection-font-size) !important;
    }
    .section-heading {
        font-family: var(--section-heading-font) !important;
        font-size: var(--section-heading-font-size) !important;
    }
    .catalog-subcat-card {
        border: 1px solid var(--gold-accent) !important;
        border-radius: 20px !important;
        overflow: hidden !important;
        background-color: #ffffff !important;
        transition: all 0.3s ease !important;
        display: flex !important;
        flex-direction: column !important;
        height: 100% !important;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02) !important;
    }
    .catalog-subcat-card:hover {
        transform: translateY(-5px) !important;
        box-shadow: 0 8px 25px rgba(12, 45, 39, 0.08) !important;
    }
    .catalog-subcat-card-img-wrapper {
        background-color: #FAF9F6 !important;
        padding: clamp(0.6rem, 1.2vw, 1.25rem) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        height: clamp(140px, 18vw, 220px) !important;
    }
    .catalog-subcat-card-img-wrapper img {
        max-width: 100% !important;
        max-height: 100% !important;
        object-fit: contain !important;
        transition: transform 0.5s ease !important;
        mix-blend-mode: multiply !important;
    }
    .catalog-subcat-card:hover .catalog-subcat-card-img-wrapper img {
        transform: scale(1.05) !important;
    }
    .catalog-subcat-card-body {
        padding: 1rem 0.8rem !important;
        text-align: center !important;
        background-color: #ffffff !important;
        border-top: 1px solid #FAF9F6 !important;
        display: flex !important;
        flex-direction: column !important;
        justify-content: center !important;
        flex-grow: 1 !important;
    }
    .catalog-subcat-card-title {
        font-family: var(--category-font) !important;
        font-size: var(--category-font-size) !important;
        font-weight: 700 !important;
        color: var(--primary-color, #0c2d27) !important;
        margin-bottom: 0.25rem !important;
        text-transform: none !important;
    }
    .catalog-subcat-card-subtext {
        font-family: var(--category-font) !important;
        font-size: calc(var(--category-font-size) * 0.8) !important;
        color: #888888 !important;
        font-weight: 500 !important;
    }

    <?php
    if (!function_exists('get_font_style_css')) {
        function get_font_style_css($selectors, $style_type) {
            if (empty($style_type)) return '';
            $css = '';
            if ($style_type === 'lowercase') {
                $css .= "$selectors { text-transform: lowercase !important; }\n";
            } elseif ($style_type === 'uppercase') {
                $css .= "$selectors { text-transform: uppercase !important; }\n";
            } elseif ($style_type === 'title_case') {
                $css .= "$selectors { text-transform: capitalize !important; }\n";
            } elseif ($style_type === 'capitalize_first') {
                $css .= "$selectors { text-transform: lowercase !important; }\n";
                $sel_arr = explode(',', $selectors);
                $first_letter_sels = [];
                foreach ($sel_arr as $s) {
                    $s_trimmed = trim($s);
                    if (!empty($s_trimmed)) {
                        $first_letter_sels[] = $s_trimmed . "::first-letter";
                    }
                }
                if (!empty($first_letter_sels)) {
                    $fl_selectors = implode(', ', $first_letter_sels);
                    $css .= "$fl_selectors { text-transform: uppercase !important; }\n";
                }
            }
            return $css;
        }
    }
    ?>

    <?= get_font_style_css('h1, h2, h3, h4, h5, h6, .font-serif, .hero-slide-heading', $heading_font_style) ?>
    <?= get_font_style_css('body, p, .font-sans, .badge, small', $paragraph_font_style) ?>
    <?= get_font_style_css('.category-title, .category-circle-card h6, .category-circle-card span, .category-grid-item h6, .category-name, .square-category-title, .square-category-count, .subcategory-title, .design-card-title, h5.design-card-title, .catalog-subcat-card-title', $category_font_style) ?>
    <?= get_font_style_css('.subsection-title', $subsection_font_style) ?>
    <?= get_font_style_css('.section-heading', $section_heading_font_style) ?>
    <?= get_font_style_css('.navbar, .navbar *, .mobile-header, .mobile-header *, .nav-link, .nav-icon-link', $header_font_style) ?>
    <?= get_font_style_css('footer, footer *, .premium-footer, .premium-footer *', $footer_font_style) ?>
    <?= get_font_style_css('.sidebar-divider-text, .sidebar-help-title', $sidemenu_heading_font_style) ?>
    <?= get_font_style_css('.sidebar-menu-item, .sidebar-bottom-card, .sidebar-user-name', $sidemenu_menu_font_style) ?>
    
    /* Premium Desktop Header Styling (matching screenshot) */
    .glass-nav {
        background-color: #ffffff !important;
        border-bottom: 1px solid #E5E2DC !important;
        box-shadow: none !important;
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
    }
    .desktop-header-search-wrapper {
        position: relative;
        width: 240px;
    }
    .desktop-header-search-input {
        background-color: #ffffff !important;
        border: 1px solid #E5E2DC !important;
        border-radius: 50px !important;
        padding: 0.45rem 1rem 0.45rem 2.25rem !important;
        font-size: 0.85rem !important;
        font-family: 'Outfit', sans-serif !important;
        color: var(--text-dark) !important;
        transition: all 0.3s ease;
        height: 38px !important;
    }
    .desktop-header-search-input::placeholder {
        color: #A0A5A3 !important;
        opacity: 1;
    }
    .desktop-header-search-input:focus {
        border-color: var(--gold-accent) !important;
        box-shadow: 0 0 0 3px rgba(196, 160, 84, 0.12) !important;
    }
    .desktop-header-search-icon {
        position: absolute;
        left: 0.85rem;
        top: 50%;
        transform: translateY(-50%);
        color: #7D8582;
        font-size: 0.9rem;
        pointer-events: none;
    }
    .desktop-nav-link {
        text-decoration: none !important;
        color: #55605C !important;
        font-weight: 500 !important;
        font-size: 0.88rem !important;
        transition: all 0.2s ease;
        font-family: 'Outfit', sans-serif !important;
        padding: 0.25rem 0.5rem !important;
    }
    .desktop-nav-link:hover, .desktop-nav-link.active {
        color: var(--forest-green) !important;
        font-weight: 700 !important;
    }
    .desktop-nav-circle-btn {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none !important;
        transition: all 0.25s ease;
        background-color: transparent;
        font-size: 1.1rem;
        position: relative;
    }
    .desktop-nav-circle-btn.wa-circle {
        border: 1px solid #25D366 !important;
        color: #25D366 !important;
    }
    .desktop-nav-circle-btn.wa-circle:hover {
        background-color: #25D366 !important;
        color: #ffffff !important;
    }
    .desktop-nav-circle-btn.heart-circle {
        border: 1px solid #E5E2DC !important;
        color: #1B1B1B !important;
    }
    .desktop-nav-circle-btn.heart-circle:hover {
        border-color: #1B1B1B;
        background-color: #F8F5F0 !important;
    }
    .desktop-nav-circle-btn.bag-circle {
        border: 1px solid var(--gold-accent) !important;
        color: var(--gold-accent) !important;
    }
    .desktop-nav-circle-btn.bag-circle:hover {
        background-color: var(--gold-accent) !important;
        color: #ffffff !important;
    }
    .desktop-nav-circle-btn.profile-circle {
        border: 1px solid var(--gold-accent) !important;
        color: var(--gold-accent) !important;
    }
    .desktop-nav-circle-btn.profile-circle:hover {
        background-color: var(--gold-accent) !important;
        color: #ffffff !important;
    }
    .desktop-nav-circle-btn .nav-icon-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background-color: var(--forest-green);
        color: #FFFFFF;
        font-size: 0.6rem;
        font-weight: 700;
        padding: 1px 4px;
        border-radius: 50px;
        border: 1.5px solid #ffffff;
        min-width: 16px;
        text-align: center;
    }
    .no-caret::after {
        display: none !important;
    }
    
    /* Sidemenu CSS Options */
    #mobileOffcanvasNav {
        background-color: var(--sidemenu-bg-color) !important;
    }
    #mobileOffcanvasNav .offcanvas-title, 
    #mobileOffcanvasNav .nav-link,
    #mobileOffcanvasNav .btn-close {
        color: var(--sidemenu-font-color) !important;
        font-family: var(--sidemenu-font) !important;
    }
    #mobileOffcanvasNav .nav-link {
        font-size: var(--sidemenu-font-size) !important;
    }
    
    /* Hide scrollbar for offcanvas menu on the left side */
    #mobileOffcanvasNav .offcanvas-body::-webkit-scrollbar {
        display: none;
    }
    #mobileOffcanvasNav .offcanvas-body {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    </style>
<style>
/* Premium Product Detail Modal Styles */
#productModalCarousel {
    position: relative;
    width: 100%;
    height: 240px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background-color: #FFFFFF;
}
#productModalCarousel .carousel-item {
    height: 100%;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
#productModalCarousel .carousel-item img {
    max-height: 220px;
    width: auto;
    max-width: 100%;
    object-fit: contain;
    padding: 0.5rem;
}
#modalCarouselIndicators [data-bs-target] {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #000;
    opacity: 0.3;
    border: none;
}
#modalCarouselIndicators .active {
    opacity: 1;
}
.btn-xs {
    padding: 0.1rem 0.25rem;
    font-size: 0.75rem;
}
</style>
<script>
window.isCustomerLoggedIn = <?= $is_customer_logged_in ? 'true' : 'false' ?>;
document.addEventListener("DOMContentLoaded", function() {
    const waLinks = document.querySelectorAll('a[href*="wa.me"]');
    waLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Send ajax to clear the new registration flag so it only sends the visiting card the first time
            fetch('customer_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear_new_registration_flag'
            }).catch(e => console.error(e));
        });
    });
});
</script>
</head>
<body>
    
    <!-- DESKTOP TOPBAR (Hidden on mobile) -->
    <div class="topbar py-2 d-none d-xl-block">
        <div class="container-fluid px-4 px-xl-5 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-1">
                <i class="bi bi-shield-check text-warning"></i>
                <span><?= htmlspecialchars($topbar1) ?></span>
            </div>
            <div class="d-flex align-items-center gap-1">
                <i class="bi bi-patch-check-fill text-warning"></i>
                <span><?= htmlspecialchars($topbar2) ?></span>
            </div>
            <div class="d-flex align-items-center gap-1">
                <i class="bi bi-award text-warning"></i>
                <span><?= htmlspecialchars($topbar3) ?></span>
            </div>
        </div>
    </div>

    <!-- DESKTOP NAVBAR (Hidden on mobile) -->
    <nav class="navbar navbar-expand-xl glass-nav sticky-top py-2 d-none d-xl-block">
        <div class="container-fluid px-3 px-xl-4 px-xxl-5 d-flex align-items-center justify-content-between flex-nowrap">
            <!-- Left: Logo & Search -->
            <div class="d-flex align-items-center gap-2 gap-xl-4 flex-shrink-1">
                <a class="navbar-brand d-flex flex-column align-items-start m-0 p-0 flex-shrink-0" href="index.php">
                    <?php if (!empty($brand_logo) && file_exists(__DIR__ . '/' . $brand_logo)): ?>
                        <img src="<?= htmlspecialchars($brand_logo) ?>" alt="Brand Logo" height="<?= htmlspecialchars($logo_height_desktop) ?>" style="max-height: <?= htmlspecialchars($logo_height_desktop) ?>px; width: auto; object-fit: contain;">
                    <?php else: ?>
                        <span class="fw-bold text-uppercase" style="font-family: 'Outfit', sans-serif; font-size: 1.35rem; font-weight: 800; letter-spacing: 0.5px; color: var(--forest-green); line-height: 1.1;">SILVEE925</span>
                        <span class="text-uppercase text-muted" style="font-family: 'Outfit', sans-serif; font-size: 0.52rem; font-weight: 600; letter-spacing: 1.5px; color: var(--forest-green) !important; margin-top: 1px;">WHOLESALE 925 SILVER</span>
                    <?php endif; ?>
                </a>

                <!-- Search Bar -->
                <form action="shop.php" method="GET" class="m-0 flex-shrink-1">
                    <div class="desktop-header-search-wrapper">
                        <i class="bi bi-search desktop-header-search-icon" aria-hidden="true"></i>
                        <input type="text" name="search" aria-label="Search collections" class="form-control desktop-header-search-input" placeholder="Search collections..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                    </div>
                </form>
            </div>

            <!-- Center: Navigation Links -->
            <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
            <div class="d-flex align-items-center gap-2 gap-xl-3 gap-xxl-4 flex-shrink-0 mx-2">
                <a href="index.php" class="desktop-nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>">Home</a>
                <a href="shop.php" class="desktop-nav-link">Collections</a>
                <a href="index.php#new-arrivals" class="desktop-nav-link">Special Collections</a>
                <a href="about.php" class="desktop-nav-link <?= ($current_page == 'about.php') ? 'active' : '' ?>">About Us</a>
                <a href="contact.php" class="desktop-nav-link <?= ($current_page == 'contact.php') ? 'active' : '' ?>">Contact</a>
                <a href="blog.php" class="desktop-nav-link">Blog</a>
            </div>

            <!-- Right: Action Icons (WhatsApp, Wishlist, Bag, Account) -->
            <div class="d-flex align-items-center gap-2 gap-xl-3 flex-shrink-0">
                <!-- WhatsApp -->
                <a href="<?= $wa_link ?>" target="_blank" rel="noopener noreferrer" class="desktop-nav-circle-btn wa-circle" title="WhatsApp Enquiry" aria-label="WhatsApp Enquiry">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i>
                </a>

                <!-- Wishlist (Heart icon) -->
                <a href="wishlist.php" class="desktop-nav-circle-btn heart-circle position-relative" title="Wishlist" aria-label="Wishlist">
                    <i class="bi bi-heart" aria-hidden="true"></i>
                    <span id="wishlist-badge-nav" class="nav-icon-badge wishlist-badge d-none">0</span>
                </a>

                <!-- Bag (Cart) -->
                <a href="cart.php" class="desktop-nav-circle-btn bag-circle" title="Shopping Bag" aria-label="Shopping Bag">
                    <i class="bi bi-bag" aria-hidden="true"></i>
                    <span id="cart-badge-nav" class="nav-icon-badge <?= $cart_count > 0 ? '' : 'd-none' ?>"><?= $cart_count ?></span>
                </a>

                <!-- Account / Profile Dropdown -->
                <?php if($is_customer_logged_in): ?>
                    <div class="dropdown">
                        <a href="#" class="desktop-nav-circle-btn profile-circle dropdown-toggle no-caret" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account profile menu">
                            <i class="bi bi-person-fill" aria-hidden="true"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 rounded-3" style="z-index: 1050;">
                            <li><a class="dropdown-item py-2 fw-medium text-dark" href="customer_profile.php"><i class="bi bi-person-badge me-2 text-secondary align-middle" aria-hidden="true"></i> Profile (<?= htmlspecialchars($_SESSION['customer_username'] ?? 'Account') ?>)</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 fw-medium text-danger" href="customer_logout.php"><i class="bi bi-box-arrow-right me-2 align-middle"></i> Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="desktop-nav-circle-btn profile-circle" title="Login / Register">
                        <i class="bi bi-person-fill"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- MOBILE SPECIFIC HEADER (Shown on mobile only, matching screenshot) -->
    <div class="mobile-header d-xl-none bg-white sticky-top shadow-sm" style="z-index: 1020;">
        <!-- Row 1: Hamburg Menu - Logo - Cart Icon (Always Fixed/Sticky at top of mobile header) -->
        <div class="mobile-header-top bg-white border-bottom py-2 px-3">
            <div class="d-flex align-items-center justify-content-between py-1">
                <button class="border-0 bg-transparent p-0 text-dark" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileOffcanvasNav" aria-controls="mobileOffcanvasNav" aria-label="Toggle navigation">
                    <i class="bi bi-list fs-2"></i>
                </button>
                
                <a class="d-flex flex-column align-items-center text-decoration-none text-center" href="index.php">
                    <?php if (!empty($brand_logo) && file_exists(__DIR__ . '/' . $brand_logo)): ?>
                        <img src="<?= htmlspecialchars($brand_logo) ?>" alt="Brand Logo" height="<?= htmlspecialchars($logo_height_mobile) ?>" style="max-height: <?= htmlspecialchars($logo_height_mobile) ?>px; width: auto; object-fit: contain;">
                    <?php else: ?>
                        <span class="fs-4 fw-bold text-uppercase tracking-wider m-0 p-0" style="font-family: 'Playfair Display', serif; font-weight: 800; color: var(--forest-green); line-height: 1;">SILVEE <span style="color: var(--gold-accent);">925</span></span>
                        <span class="brand-sub text-muted" style="font-size: 0.55rem; letter-spacing: 0.5px; font-weight: 600;">925 STERLING SILVER WHOLESALE</span>
                    <?php endif; ?>
                </a>
                
                <a href="cart.php" class="text-decoration-none text-dark position-relative p-1" aria-label="View shopping cart">
                    <i class="bi bi-cart3 fs-2" aria-hidden="true"></i>
                    <span id="cart-badge-mobile" class="badge rounded-circle position-absolute top-0 start-100 translate-middle <?= $cart_count > 0 ? '' : 'd-none' ?>" style="font-size: 0.55rem; padding: 3px 6px; background-color: var(--forest-green); color: #FFFFFF; border: 1.5px solid #fff;"><?= $cart_count ?></span>
                </a>
            </div>
        </div>
        
        <!-- Row 2: Search Input (Hides on scroll down, shows on scroll up) -->
        <div class="mobile-search-bar bg-white border-bottom py-2 px-3">
            <form action="shop.php" method="GET" class="w-100 m-0">
                <div class="position-relative">
                    <input type="text" name="search" aria-label="Search products" class="form-control rounded-pill border-light-subtle bg-light text-dark shadow-none" placeholder="Search by product, code, weight..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" style="font-size: 0.85rem; padding: 0.55rem 3rem 0.55rem 1.25rem; border: 1px solid #E5E2DC;">
                    <button type="submit" class="bg-transparent border-0 position-absolute end-0 top-50 translate-middle-y me-3 p-0" style="outline: none;" aria-label="Submit search">
                        <i class="bi bi-search text-dark fs-5" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mobile Scroll Behavior CSS & JS -->
    <style>
    .mobile-header {
        position: sticky;
        top: 0;
        z-index: 1020;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        background-color: #ffffff;
    }
    .mobile-header-top {
        position: relative;
        z-index: 1022;
        background-color: #ffffff;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .mobile-search-bar {
        position: relative;
        z-index: 1021;
        background-color: #ffffff;
        transition: opacity 0.2s ease, visibility 0.3s ease;
        opacity: 1;
        visibility: visible;
    }
    /* When search is hidden */
    .mobile-header.search-hidden {
        transform: translateY(calc(-1 * var(--search-bar-height, 54px)));
    }
    .mobile-header.search-hidden .mobile-header-top {
        transform: translateY(var(--search-bar-height, 54px));
    }
    .mobile-header.search-hidden .mobile-search-bar {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }
    </style>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        let lastScrollTop = 0;
        const mobileHeader = document.querySelector('.mobile-header');
        const searchBar = document.querySelector('.mobile-search-bar');
        const storeNav = document.getElementById('mobileOffcanvasNav');
        
        if (mobileHeader && searchBar) {
            // Function to update the search bar height dynamically
            function updateSearchBarHeight() {
                const height = searchBar.offsetHeight;
                if (height > 0) {
                    document.documentElement.style.setProperty('--search-bar-height', height + 'px');
                }
            }
            
            // Measure height initially and on window resize/load
            updateSearchBarHeight();
            window.addEventListener('resize', updateSearchBarHeight);
            window.addEventListener('load', updateSearchBarHeight);
            
            // Listen for collapse show event to reset search bar visibility
            if (storeNav) {
                storeNav.addEventListener('show.bs.offcanvas', function() {
                    mobileHeader.classList.remove('search-hidden');
                });
            }
            
            window.addEventListener('scroll', function() {
                // If mobile menu is open, do not toggle search bar
                if (storeNav && storeNav.classList.contains('show')) {
                    return;
                }
                
                let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                
                if (scrollTop > lastScrollTop && scrollTop > 80) {
                    // Scrolling down and past threshold - hide search bar
                    mobileHeader.classList.add('search-hidden');
                } else if (scrollTop < lastScrollTop - 5 || scrollTop <= 15) {
                    // Scrolling up by more than 5px or near top - show search bar
                    mobileHeader.classList.remove('search-hidden');
                }
                
                lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
            }, { passive: true });
        }
    });
    </script>

        <!-- Mobile Navigation Menu Offcanvas -->
        <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileOffcanvasNav" aria-labelledby="mobileOffcanvasNavLabel" style="max-width: 85vw; width: 320px; background-color: var(--sidemenu-bg-color); border-right: none;">
            <!-- Custom CSS for new sidebar -->
            <style>
            .sidebar-logo-container { text-align: center; position: relative; padding: 2rem 1rem 1.5rem; }
            .sidebar-close-btn { position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 1.5rem; color: #9C8353; padding: 0; line-height: 1; z-index: 10; }
            .sidebar-user-section { display: flex; align-items: center; padding: 0 1.5rem 1.5rem; }
            .sidebar-user-avatar { width: 50px; height: 50px; background-color: #EFE8DA; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 12px; color: #D3C4A5; font-size: 1.8rem; flex-shrink: 0; }
            .sidebar-user-info { flex-grow: 1; overflow: hidden; }
            .sidebar-user-name { font-family: var(--sidemenu-font); font-weight: 700; color: var(--sidemenu-font-color); margin: 0; font-size: calc(var(--sidemenu-font-size) * 1.15); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .sidebar-user-sub { font-family: var(--sidemenu-font); color: #777; margin: 0; font-size: 0.85rem; }
            .sidebar-premium-btn { background-color: #1A1A1A; color: #E2C284; border: none; border-radius: 20px; padding: 5px 12px; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 5px; text-decoration: none; flex-shrink: 0; }
            .sidebar-divider-container { display: flex; align-items: center; margin: 15px 1.5rem 10px; }
            .sidebar-divider-line { display: none; }
            .sidebar-divider-text { padding: 0; color: #B4965C; font-family: var(--sidemenu-font); font-size: var(--sidemenu-heading-font-size); font-weight: 700; letter-spacing: 1px; text-align: left; }
            .sidebar-menu-list { list-style: none; padding: 0 1rem; margin: 0; }
            .sidebar-menu-item { display: flex; align-items: center; padding: 12px 15px; border-radius: 8px; text-decoration: none; color: var(--sidemenu-font-color); font-family: var(--sidemenu-font); font-size: var(--sidemenu-font-size); font-weight: 500; margin-bottom: var(--sidemenu-item-spacing); transition: background-color 0.2s; }
            .sidebar-menu-item:hover, .sidebar-menu-item.active { background-color: #F4EFE3; color: var(--sidemenu-font-color); }
            .sidebar-menu-item.active { border-left: 3px solid #B4965C; padding-left: 12px; }
            .sidebar-menu-icon { width: 24px; color: var(--sidemenu-font-color); margin-right: 12px; font-size: 1.1rem; text-align: center; }
            .sidebar-menu-text { flex-grow: 1; }
            .sidebar-menu-chevron { color: #B4965C; font-size: 0.9rem; }
            .sidebar-badge { font-size: 0.6rem; font-weight: 800; padding: 3px 6px; border-radius: 4px; margin-right: 8px; letter-spacing: 0.5px; }
            .badge-new { background-color: #F8E5C4; color: #B48232; }
            .badge-hot { background-color: #FFD4D4; color: #D32F2F; }
            .badge-updated { background-color: #D4F0DF; color: #2E7D32; }
            .sidebar-bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 1rem 1.5rem; }
            .sidebar-bottom-card { background-color: #F4EFE3; border-radius: 8px; padding: 12px; text-decoration: none; color: var(--sidemenu-font-color); display: flex; align-items: center; gap: 10px; font-family: var(--sidemenu-font); transition: background-color 0.2s; }
            .sidebar-bottom-card:hover { background-color: #EAE3D3; }
            .sidebar-bottom-card-icon { color: #B4965C; font-size: 1.4rem; }
            .sidebar-bottom-card-text { font-size: 0.75rem; font-weight: 600; line-height: 1.3; flex-grow: 1; color: var(--sidemenu-font-color); }
            .sidebar-bottom-card-chevron { color: #B4965C; font-size: 0.8rem; }
            .sidebar-logout-btn { background-color: #2A2A2A; color: #E6DBC6; border-radius: 8px; padding: 12px; margin: 0 1.5rem 2rem; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; font-family: var(--sidemenu-font); font-weight: 500; font-size: var(--sidemenu-font-size); }
            .sidebar-logout-btn:hover { color: #fff; background-color: #1a1a1a; }
            .sidebar-help-section { background-color: #1A1A1A; padding: 1.5rem; margin-top: auto; }
            .sidebar-help-title { color: #E2C284; font-family: var(--sidemenu-font); font-weight: 700; margin-bottom: 5px; font-size: calc(var(--sidemenu-heading-font-size) * 1.5); }
            .sidebar-help-text { color: #E6DBC6; font-family: var(--sidemenu-font); font-size: 0.8rem; margin-bottom: 15px; }
            .sidebar-help-btn { background-color: #E2C284; color: #1A1A1A; display: block; text-align: center; padding: 12px; border-radius: 8px; text-decoration: none; font-family: var(--sidemenu-font); font-weight: 700; font-size: 0.95rem; transition: background-color 0.2s; }
            .sidebar-help-btn:hover { background-color: #d1b173; color: #1A1A1A; }
            
            /* Hide the default offcanvas scrollbar but allow scrolling */
            #mobileOffcanvasNav .offcanvas-body::-webkit-scrollbar { display: none; }
            #mobileOffcanvasNav .offcanvas-body { -ms-overflow-style: none; scrollbar-width: none; padding: 0; }
            .sidebar-divider-container[aria-expanded="true"] .collapse-icon { transform: rotate(180deg); }
            .collapse-icon { transition: transform 0.3s ease; font-size: 0.8rem; margin-left: 5px; }
            </style>

            <div class="offcanvas-body d-flex flex-column">
                <!-- Header Logo Area -->
                <div class="sidebar-logo-container">
                    <button type="button" class="sidebar-close-btn" data-bs-dismiss="offcanvas" aria-label="Close"><i class="bi bi-x-lg"></i></button>
                    <?php if (!empty($brand_logo) && file_exists(__DIR__ . '/' . $brand_logo)): ?>
                        <img src="<?= htmlspecialchars($brand_logo) ?>" alt="Brand Logo" style="max-height: 45px; width: auto; object-fit: contain; margin-bottom: 8px;">
                    <?php else: ?>
                        <div style="font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 600; color: #B4965C; letter-spacing: 2px; margin-bottom: 2px;">SILVEE</div>
                    <?php endif; ?>
                    <div style="font-family: 'Outfit', sans-serif; font-size: 0.55rem; font-weight: 600; color: #666; letter-spacing: 1px;">925 STERLING SILVER JEWELLERY</div>
                </div>

                <!-- User Profile Area -->
                <div class="sidebar-user-section">
                    <div class="sidebar-user-avatar">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div class="sidebar-user-info">
                        <?php if($is_customer_logged_in): ?>
                            <h4 class="sidebar-user-name">Hi, <?= htmlspecialchars($_SESSION['customer_username'] ?? 'User') ?></h4>
                            <p class="sidebar-user-sub">Welcome back!</p>
                        <?php else: ?>
                            <h4 class="sidebar-user-name">Welcome</h4>
                            <p class="sidebar-user-sub"><a href="login.php" class="text-decoration-none" style="color:#B4965C;">Login / Register</a></p>
                        <?php endif; ?>
                    </div>
                    <?php if($is_customer_logged_in): ?>
                    <a href="#" class="sidebar-premium-btn">
                        <i class="bi bi-brightness-high-fill" style="color: #E2C284;"></i> Premium
                    </a>
                    <?php endif; ?>
                </div>

                <!-- SHOP SECTION -->
                <div class="sidebar-divider-container" data-bs-toggle="collapse" data-bs-target="#collapseShop" aria-expanded="true" aria-controls="collapseShop" style="cursor: pointer;">
                    <div class="sidebar-divider-text">SHOP</div>
                </div>
                <div class="collapse show" id="collapseShop">
                <ul class="sidebar-menu-list">
                    <li><a href="index.php" class="sidebar-menu-item active">
                        <i class="bi bi-house sidebar-menu-icon" style="color: #B4965C;"></i>
                        <span class="sidebar-menu-text">Home</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <li><a href="index.php#new-arrivals" class="sidebar-menu-item">
                        <i class="bi bi-stars sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">New Arrivals</span>
                        <span class="sidebar-badge badge-new">NEW</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <li><a href="shop.php?sort=best_sellers" class="sidebar-menu-item">
                        <i class="bi bi-fire sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">Best Sellers</span>
                        <span class="sidebar-badge badge-hot">HOT</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <li><a href="shop.php" class="sidebar-menu-item">
                        <i class="bi bi-book sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">View Catalog</span>
                        <span class="sidebar-badge badge-updated">UPDATED</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <li><a href="shop.php?sort=latest" class="sidebar-menu-item">
                        <i class="bi bi-magic sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">Latest Designs</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                </ul>
                </div>

                <!-- SHOP BY CATEGORY -->
                <div class="sidebar-divider-container" data-bs-toggle="collapse" data-bs-target="#collapseCategory" aria-expanded="false" aria-controls="collapseCategory" style="cursor: pointer; margin-top: 20px;">
                    <div class="sidebar-divider-text">SHOP BY CATEGORY</div>
                </div>
                <div class="collapse" id="collapseCategory">
                <ul class="sidebar-menu-list">
                    <?php 
                    if(empty($main_categories_menu)) {
                        $main_categories_menu = [
                            ['id' => 'rings', 'name' => 'Rings'],
                            ['id' => 'earrings', 'name' => 'Earrings'],
                            ['id' => 'necklaces', 'name' => 'Necklaces'],
                            ['id' => 'bracelets', 'name' => 'Bracelets'],
                            ['id' => 'anklets', 'name' => 'Anklets'],
                            ['id' => 'kids', 'name' => 'Kids Collection'],
                            ['id' => 'bridal', 'name' => 'Bridal Collection'],
                            ['id' => 'gift', 'name' => 'Gift Collection']
                        ];
                    }
                    ?>
                    <?php foreach($main_categories_menu as $mc): ?>
                    <li><a href="shop.php?cat=<?= $mc['id'] ?>" class="sidebar-menu-item">
                        <?php 
                        $cat_name_lower = strtolower($mc['name']);
                        $icon = 'bi-circle';
                        if(strpos($cat_name_lower, 'ring') !== false) $icon = 'bi-record-circle';
                        elseif(strpos($cat_name_lower, 'earring') !== false) $icon = 'bi-gem';
                        elseif(strpos($cat_name_lower, 'necklace') !== false) $icon = 'bi-vinyl';
                        elseif(strpos($cat_name_lower, 'bracelet') !== false) $icon = 'bi-watch';
                        elseif(strpos($cat_name_lower, 'anklet') !== false) $icon = 'bi-vinyl-fill';
                        elseif(strpos($cat_name_lower, 'kid') !== false) $icon = 'bi-emoji-smile';
                        elseif(strpos($cat_name_lower, 'bridal') !== false) $icon = 'bi-brightness-high';
                        elseif(strpos($cat_name_lower, 'gift') !== false) $icon = 'bi-gift';
                        ?>
                        <i class="bi <?= $icon ?> sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text"><?= htmlspecialchars($mc['name']) ?></span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <?php endforeach; ?>
                </ul>
                </div>

                <!-- MY ACCOUNT SECTION -->
                <div class="sidebar-divider-container" data-bs-toggle="collapse" data-bs-target="#collapseAccount" aria-expanded="false" aria-controls="collapseAccount" style="cursor: pointer; margin-top: 20px;">
                    <div class="sidebar-divider-text">MY ACCOUNT</div>
                </div>
                <div class="collapse" id="collapseAccount">
                <ul class="sidebar-menu-list">
                    <li><a href="customer_profile.php" class="sidebar-menu-item">
                        <i class="bi bi-box sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">My Orders</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <li><a href="wishlist.php" class="sidebar-menu-item">
                        <i class="bi bi-heart sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">Wishlist</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <li><a href="customer_profile.php#addresses" class="sidebar-menu-item">
                        <i class="bi bi-geo-alt sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">Saved Addresses</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <li><a href="customer_profile.php" class="sidebar-menu-item">
                        <i class="bi bi-person sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">My Profile</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                </ul>
                </div>

                <!-- CUSTOMER SUPPORT SECTION -->
                <div class="sidebar-divider-container" data-bs-toggle="collapse" data-bs-target="#collapseSupport" aria-expanded="false" aria-controls="collapseSupport" style="cursor: pointer; margin-top: 20px;">
                    <div class="sidebar-divider-text">CUSTOMER SUPPORT</div>
                </div>
                <div class="collapse" id="collapseSupport">
                <ul class="sidebar-menu-list">
                    <li><a href="<?= htmlspecialchars($wa_link) ?>" target="_blank" class="sidebar-menu-item">
                        <i class="bi bi-whatsapp sidebar-menu-icon" style="color: #25D366;"></i>
                        <span class="sidebar-menu-text">WhatsApp Support</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <li><a href="contact.php" class="sidebar-menu-item">
                        <i class="bi bi-telephone sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">Contact Us</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <li><a href="blog.php" class="sidebar-menu-item">
                        <i class="bi bi-journal-text sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">Blog</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <li><a href="about.php" class="sidebar-menu-item">
                        <i class="bi bi-buildings sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">About Us</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <li><a href="#" class="sidebar-menu-item">
                        <i class="bi bi-question-circle sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">FAQs</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <li><a href="#" class="sidebar-menu-item">
                        <i class="bi bi-truck sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">Shipping Policy</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                    <li><a href="#" class="sidebar-menu-item">
                        <i class="bi bi-arrow-return-left sidebar-menu-icon"></i>
                        <span class="sidebar-menu-text">Return Policy</span>
                        <i class="bi bi-chevron-right sidebar-menu-chevron"></i>
                    </a></li>
                </ul>
                </div>

                <!-- Bottom Grid (Premium / Refer) -->
                <div class="sidebar-bottom-grid mt-2">
                    <a href="#" class="sidebar-bottom-card">
                        <i class="bi bi-gem sidebar-bottom-card-icon"></i>
                        <span class="sidebar-bottom-card-text">Become a<br>Premium Partner</span>
                        <i class="bi bi-chevron-right sidebar-bottom-card-chevron"></i>
                    </a>
                    <a href="#" class="sidebar-bottom-card">
                        <i class="bi bi-gift sidebar-bottom-card-icon"></i>
                        <span class="sidebar-bottom-card-text">Refer & Earn<br>Rewards</span>
                        <i class="bi bi-chevron-right sidebar-bottom-card-chevron"></i>
                    </a>
                </div>

                <!-- Logout Button -->
                <?php if($is_customer_logged_in): ?>
                    <a href="customer_logout.php" class="sidebar-logout-btn">
                        <i class="bi bi-box-arrow-right" style="color: #E6DBC6;"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="login.php" class="sidebar-logout-btn">
                        <i class="bi bi-box-arrow-in-right" style="color: #E6DBC6;"></i> Login
                    </a>
                <?php endif; ?>
                
                <!-- Bottom Need Help Section -->
                <div class="sidebar-help-section mt-auto">
                    <h6 class="sidebar-help-title">Need Help?</h6>
                    <p class="sidebar-help-text">Chat with our Jewellery Expert</p>
                    <a href="<?= htmlspecialchars($wa_link) ?>" target="_blank" class="sidebar-help-btn">
                        <i class="bi bi-whatsapp"></i> WhatsApp Support
                    </a>
                </div>
            </div>
        </div>

    <?php if(!$is_customer_logged_in): ?>
    <!-- Authentication Block Overlay Removed so page is viewable -->
    
    <!-- Auth Modal -->
    <div class="modal fade" id="authModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background-color: #FCFAF7;">
                <div class="modal-header border-0 bg-light pb-0 d-flex flex-column align-items-center pt-4">
                    <?php if (!empty($brand_logo) && file_exists(__DIR__ . '/' . $brand_logo)): ?>
                        <img src="<?= htmlspecialchars($brand_logo) ?>" alt="Brand Logo" style="max-height: <?= htmlspecialchars(round($logo_height_desktop * 1.1)) ?>px; width: auto; object-fit: contain;" class="mb-2">
                    <?php else: ?>
                        <h4 class="modal-title fw-bold" style="font-family: 'Playfair Display', serif; letter-spacing: 1px; color: var(--forest-green);">SILVEE <span style="color: var(--gold-accent);">925</span></h4>
                        <span class="text-uppercase text-secondary font-weight-bold" style="font-size: 0.62rem; letter-spacing: 2px;">925 Sterling Silver Wholesale</span>
                    <?php endif; ?>
                </div>
                <div class="modal-body p-4 pt-3">
                    <p class="text-center text-muted small mb-4">Please sign in or create an account to view our premium catalog.</p>
                    
                    <ul class="nav nav-pills nav-justified mb-4 rounded bg-light p-1" id="authTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-medium rounded-3" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-pane" type="button" role="tab">Login</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-medium rounded-3" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-pane" type="button" role="tab">Register Shop</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="authTabContent">
                        <!-- Login Form -->
                        <div class="tab-pane fade show active" id="login-pane" role="tabpanel">
                            <form id="loginForm">
                                <input type="hidden" name="action" value="login">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold text-muted">Username or Mobile Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-person text-muted"></i></span>
                                        <input type="text" class="form-control border-start-0 ps-0" name="identifier" required placeholder="Enter username or mobile">
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label small fw-semibold text-muted">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-lock text-muted"></i></span>
                                        <input type="password" class="form-control border-start-0 ps-0" name="password" id="modalLoginPassword" required placeholder="••••••••">
                                        <button class="btn btn-outline-secondary border-start-0 bg-white" type="button" onclick="let inp = document.getElementById('modalLoginPassword'); if(inp.type === 'password') { inp.type = 'text'; this.innerHTML = '<i class=\'bi bi-eye-slash\'></i>'; } else { inp.type = 'password'; this.innerHTML = '<i class=\'bi bi-eye\'></i>'; }">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-forest w-100 fw-medium shadow-sm rounded-pill py-2" id="loginBtn">Sign In</button>
                                <div id="loginAlert" class="mt-3 small text-center text-danger" style="display:none;"></div>
                            </form>
                        </div>
                        
                        <!-- Register Form -->
                        <div class="tab-pane fade" id="register-pane" role="tabpanel">
                            <form id="registerForm" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="register">
                                <input type="hidden" name="verified_card_file" id="verified_card_file" value="">
                                
                                <div class="mb-3" id="visitingCardSection">
                                    <label class="form-label small fw-semibold text-muted">Visiting Card Photo or PDF (Compulsory)</label>
                                    
                                    <div class="d-flex gap-2 mb-2">
                                        <button type="button" class="btn btn-outline-secondary w-50 py-2" onclick="document.getElementById('visiting_card_input').click();">
                                            <i class="bi bi-image me-1"></i> Gallery
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary w-50 py-2" onclick="document.getElementById('visiting_card_cam_input').click();">
                                            <i class="bi bi-camera me-1"></i> Camera
                                        </button>
                                    </div>
                                    
                                    <input type="file" class="d-none" id="visiting_card_input" accept="image/*,application/pdf" onchange="handleVisitingCardSelect(this)">
                                    <input type="file" class="d-none" id="visiting_card_cam_input" accept="image/*" capture="environment" onchange="handleVisitingCardSelect(this)">
                                    
                                    <div id="selectedCardName" class="small text-success fw-bold mb-1"></div>
                                    <small class="text-muted" style="font-size: 0.7rem;">Please select a card photo/PDF or capture a clear photo of your business visiting card.</small>
                                    
                                    <div id="verifyAlert" class="mt-2 small text-center" style="display:none;"></div>
                                </div>
                                <script>
                                window.selectedVisitingCardFile = null;
                                function handleVisitingCardSelect(input) {
                                    if(input.files && input.files.length > 0) {
                                        window.selectedVisitingCardFile = input.files[0];
                                        document.getElementById('selectedCardName').innerText = "Selected: " + window.selectedVisitingCardFile.name;
                                        // Reset the other input so no conflict
                                        if(input.id === 'visiting_card_input') {
                                            document.getElementById('visiting_card_cam_input').value = '';
                                        } else {
                                            document.getElementById('visiting_card_input').value = '';
                                        }
                                        
                                        // Trigger direct background upload
                                        uploadVisitingCardDirectly(window.selectedVisitingCardFile);
                                    }
                                }

                                function uploadVisitingCardDirectly(file) {
                                    const verifyAlert = document.getElementById('verifyAlert');
                                    const regDetails = document.getElementById('registrationDetails');
                                    const verifiedCardInput = document.getElementById('verified_card_file');

                                    verifyAlert.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Uploading card...';
                                    verifyAlert.style.color = '#0d6efd';
                                    verifyAlert.style.display = 'block';

                                    let fd = new FormData();
                                    fd.append('action', 'upload_card');
                                    fd.append('visiting_card', file);

                                    fetch('customer_ajax.php', {
                                        method: 'POST',
                                        body: fd
                                    }).then(r => r.json()).then(data => {
                                        if(data.success) {
                                            verifyAlert.innerText = 'Card uploaded successfully!';
                                            verifyAlert.style.color = 'green';
                                            verifyAlert.style.display = 'block';
                                            verifiedCardInput.value = data.temp_file;
                                            regDetails.style.display = 'block';
                                        } else {
                                            verifyAlert.innerText = data.message;
                                            verifyAlert.style.color = 'red';
                                            verifyAlert.style.display = 'block';
                                            regDetails.style.display = 'none';
                                        }
                                    }).catch(e => {
                                        verifyAlert.innerText = 'Server error during upload.';
                                        verifyAlert.style.color = 'red';
                                        verifyAlert.style.display = 'block';
                                        regDetails.style.display = 'none';
                                    });
                                }
                                </script>
                                
                                <div id="registrationDetails" style="display: none;">
                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold text-muted">Shop Name</label>
                                            <input type="text" class="form-control" name="shop_name" required placeholder="Your Shop Name">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold text-muted">Full Name</label>
                                            <input type="text" class="form-control" name="name" required placeholder="Your Full Name">
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold text-muted">Username</label>
                                            <input type="text" class="form-control" name="username" required placeholder="Username" pattern="^[a-zA-Z0-9_\-\.]+$" minlength="3" maxlength="50" title="Only letters, numbers, underscores, dashes, and periods.">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-semibold text-muted">Mobile Number</label>
                                            <input type="text" class="form-control" name="mobile_no" required placeholder="10-digit mobile" pattern="^[0-9]{10}$" title="Exactly 10 numeric digits.">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 border-top pt-3">
                                        <h6 class="small fw-bold text-dark mb-2">Shop Address Details</h6>
                                        <div class="mb-2">
                                            <input type="text" class="form-control form-control-sm" name="address_street" required placeholder="Street Address">
                                        </div>
                                        <div class="row g-2 mb-2">
                                            <div class="col-6">
                                                <input type="text" class="form-control form-control-sm" name="address_city" required placeholder="City">
                                            </div>
                                            <div class="col-6">
                                                <input type="text" class="form-control form-control-sm" name="address_state" required placeholder="State">
                                            </div>
                                        </div>
                                        <div class="row g-2 mb-2">
                                            <div class="col-6">
                                                <input type="text" class="form-control form-control-sm" name="address_pincode" required placeholder="Pincode">
                                            </div>
                                            <div class="col-6">
                                                <input type="text" class="form-control form-control-sm" name="gst_no" placeholder="GST No (Optional)">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3 border-top pt-3">
                                        <div class="row g-2">
                                            <div class="col-12">
                                                <label class="form-label small fw-semibold text-muted">Password</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="password" id="modalRegPassword" required placeholder="Create password (min 6 chars)" minlength="6">
                                                    <button class="btn btn-outline-secondary bg-white" type="button" onclick="let inp = document.getElementById('modalRegPassword'); if(inp.type === 'password') { inp.type = 'text'; this.innerHTML = '<i class=\'bi bi-eye-slash\'></i>'; } else { inp.type = 'password'; this.innerHTML = '<i class=\'bi bi-eye\'></i>'; }">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-12 mt-2">
                                                <label class="form-label small fw-semibold text-muted">Confirm Password</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="confirm_password" id="modalRegConfirmPassword" required placeholder="Re-enter password">
                                                    <button class="btn btn-outline-secondary bg-white" type="button" onclick="let inp = document.getElementById('modalRegConfirmPassword'); if(inp.type === 'password') { inp.type = 'text'; this.innerHTML = '<i class=\'bi bi-eye-slash\'></i>'; } else { inp.type = 'password'; this.innerHTML = '<i class=\'bi bi-eye\'></i>'; }">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-forest w-100 fw-medium shadow-sm rounded-pill py-2" id="regBtn" onclick="if(document.getElementById('modalRegPassword').value !== document.getElementById('modalRegConfirmPassword').value) { document.getElementById('regAlert').innerHTML = 'Passwords do not match.'; document.getElementById('regAlert').style.display='block'; return false; }">Create Account</button>
                                    <div id="regAlert" class="mt-3 small text-center text-danger" style="display:none;"></div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Auto-close mobile offcanvas when a link is clicked
        const mobileOffcanvas = document.getElementById('mobileOffcanvasNav');
        if (mobileOffcanvas) {
            const navLinks = mobileOffcanvas.querySelectorAll('.nav-link');
            navLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    if (this.hasAttribute('data-bs-toggle') && this.getAttribute('data-bs-toggle') === 'collapse') {
                        return; // Let Bootstrap handle the collapse dropdown
                    }
                    if (mobileOffcanvas.classList.contains('show')) {
                        const offcanvasInstance = bootstrap.Offcanvas.getInstance(mobileOffcanvas);
                        if (offcanvasInstance) {
                            offcanvasInstance.hide();
                        }
                    }
                });
            });
        }
    });
    </script>
