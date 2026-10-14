<?php
require_once "auth_check.php";
require_once "role_management.php";

$user_role = get_user_role($pdo, $_SESSION['user_id']);

// Only admins can access
if ($user_role !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

$page_title = "Theme Settings";
require "templates/header.php";

// Fetch current theme settings
$stmt = $pdo->query("SELECT setting_key, setting_value, description FROM theme_settings ORDER BY setting_key");
$theme_settings = [];
while ($row = $stmt->fetch()) {
    $theme_settings[$row['setting_key']] = $row;
}

// Define theme color settings
$color_settings = [
    'banner_bg_color' => 'Banner Background Color',
    'banner_accent_color' => 'Banner Accent/Text Color (Gold)',
    'hero_gradient_start' => 'Hero Section Gradient Start',
    'hero_gradient_end' => 'Hero Section Gradient End',
    'primary_color' => 'Primary Button/Accent Color (Forest Green)',
    'secondary_color' => 'Secondary Text Color'
];

// Load Carousel Banners (max 3)
$hero_carousel_data = isset($theme_settings['hero_carousel_data']) ? json_decode($theme_settings['hero_carousel_data']['setting_value'], true) : [];
if (empty($hero_carousel_data)) {
    $hero_carousel_data = [
        [
            'title' => 'Wholesale Jewellery Collection',
            'subtitle' => 'PREMIUM 925 STERLING SILVER',
            'badge_text' => 'New',
            'button_text' => 'Explore Collection',
            'button_link' => '#catalog',
            'image_path' => 'assets/hero_jewellery.png'
        ],
        [
            'title' => 'Exquisite Handcrafted Rings',
            'subtitle' => '100% STERLING SILVER',
            'badge_text' => 'Hot',
            'button_text' => 'View Rings',
            'button_link' => 'shop.php?cat=1',
            'image_path' => ''
        ],
        [
            'title' => 'Silver Bracelets & Kadas',
            'subtitle' => 'PREMIUM QUALITY',
            'badge_text' => 'Trending',
            'button_text' => 'Explore Bracelets',
            'button_link' => 'shop.php?cat=5',
            'image_path' => ''
        ]
    ];
}

// Load Mobile Carousel Banners (max 3)
$hero_carousel_mobile_data = isset($theme_settings['hero_carousel_mobile_data']) ? json_decode($theme_settings['hero_carousel_mobile_data']['setting_value'], true) : [];
if (empty($hero_carousel_mobile_data)) {
    $hero_carousel_mobile_data = [
        [
            'title' => 'Wholesale Jewellery Collection',
            'subtitle' => 'PREMIUM 925 STERLING SILVER',
            'badge_text' => 'New',
            'button_text' => 'Explore Collection',
            'button_link' => '#catalog',
            'image_path' => ''
        ],
        [
            'title' => 'Exquisite Handcrafted Rings',
            'subtitle' => '100% STERLING SILVER',
            'badge_text' => 'Hot',
            'button_text' => 'View Rings',
            'button_link' => 'shop.php?cat=1',
            'image_path' => ''
        ],
        [
            'title' => 'Silver Bracelets & Kadas',
            'subtitle' => 'PREMIUM QUALITY',
            'badge_text' => 'Trending',
            'button_text' => 'Explore Bracelets',
            'button_link' => 'shop.php?cat=5',
            'image_path' => ''
        ]
    ];
}

// Load 4 Promotional Banners
$promo_banners_data = isset($theme_settings['promo_banners_data']) ? json_decode($theme_settings['promo_banners_data']['setting_value'], true) : [];
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

// Load Top Bar Texts
$topbar_text_1 = isset($theme_settings['topbar_text_1']) ? $theme_settings['topbar_text_1']['setting_value'] : "India's Trusted 925 Silver Wholesaler";
$topbar_text_2 = isset($theme_settings['topbar_text_2']) ? $theme_settings['topbar_text_2']['setting_value'] : "100% 925 Sterling Silver";
$topbar_text_3 = isset($theme_settings['topbar_text_3']) ? $theme_settings['topbar_text_3']['setting_value'] : "Premium Quality Guarantee";

// Load WhatsApp Number
$whatsapp_number_val = isset($theme_settings['whatsapp_number']) ? $theme_settings['whatsapp_number']['setting_value'] : '918208719194';

// Typography List
$fonts_list = [
    'Playfair Display' => 'Playfair Display (Elegant Serif)',
    'Cinzel' => 'Cinzel (Classical Roman Serif)',
    'Lora' => 'Lora (Contemporary Serif)',
    'Cormorant Garamond' => 'Cormorant Garamond (Premium Garamond Serif)',
    'Outfit' => 'Outfit (Modern Geometric Sans)',
    'Roboto' => 'Roboto (Standard Neo-Grotesque)',
    'Inter' => 'Inter (Modern UI Sans)',
    'Montserrat' => 'Montserrat (Geometric Sans)',
    'Poppins' => 'Poppins (Soft Geometric Sans)',
    'Open Sans' => 'Open Sans (Neutral Sans)',
    'Lato' => 'Lato (Warm Sans)'
];

$heading_font_val = isset($theme_settings['heading_font']) ? $theme_settings['heading_font']['setting_value'] : 'Playfair Display';
$paragraph_font_val = isset($theme_settings['paragraph_font']) ? $theme_settings['paragraph_font']['setting_value'] : 'Inter';
$header_font_val = isset($theme_settings['header_font']) ? $theme_settings['header_font']['setting_value'] : 'Playfair Display';
$footer_font_val = isset($theme_settings['footer_font']) ? $theme_settings['footer_font']['setting_value'] : 'Inter';

// Load Custom Titles
$category_title_val = isset($theme_settings['category_section_title']) ? $theme_settings['category_section_title']['setting_value'] : 'Shop by Category';
$new_arrivals_title_val = isset($theme_settings['new_arrivals_section_title']) ? $theme_settings['new_arrivals_section_title']['setting_value'] : 'New Arrivals';
$catalog_title_val = isset($theme_settings['catalog_section_title']) ? $theme_settings['catalog_section_title']['setting_value'] : 'Our Wholesale Catalog';
$new_arrivals_category_id_val = isset($theme_settings['new_arrivals_category_id']) ? $theme_settings['new_arrivals_category_id']['setting_value'] : '';
$promo_height_desktop_val = isset($theme_settings['promo_height_desktop']) ? $theme_settings['promo_height_desktop']['setting_value'] : '306';
$promo_width_desktop_val = isset($theme_settings['promo_width_desktop']) ? $theme_settings['promo_width_desktop']['setting_value'] : '364';
$logo_height_desktop_val = isset($theme_settings['logo_height_desktop']) ? $theme_settings['logo_height_desktop']['setting_value'] : '50';
$logo_height_mobile_val = isset($theme_settings['logo_height_mobile']) ? $theme_settings['logo_height_mobile']['setting_value'] : '38';
$category_font_val = isset($theme_settings['category_font']) ? $theme_settings['category_font']['setting_value'] : 'Playfair Display';
$subsection_font_val = isset($theme_settings['subsection_font']) ? $theme_settings['subsection_font']['setting_value'] : 'Playfair Display';
$section_heading_font_val = isset($theme_settings['section_heading_font']) ? $theme_settings['section_heading_font']['setting_value'] : 'Outfit';
$heading_font_size_val = isset($theme_settings['heading_font_size']) ? $theme_settings['heading_font_size']['setting_value'] : '28';
$heading_font_size_mobile_val = isset($theme_settings['heading_font_size_mobile']) ? $theme_settings['heading_font_size_mobile']['setting_value'] : '18';
$paragraph_font_size_val = isset($theme_settings['paragraph_font_size']) ? $theme_settings['paragraph_font_size']['setting_value'] : '16';
$paragraph_font_size_mobile_val = isset($theme_settings['paragraph_font_size_mobile']) ? $theme_settings['paragraph_font_size_mobile']['setting_value'] : '14';
$category_font_size_val = isset($theme_settings['category_font_size']) ? $theme_settings['category_font_size']['setting_value'] : '15';
$category_font_size_mobile_val = isset($theme_settings['category_font_size_mobile']) ? $theme_settings['category_font_size_mobile']['setting_value'] : '12';
$subsection_font_size_val = isset($theme_settings['subsection_font_size']) ? $theme_settings['subsection_font_size']['setting_value'] : '18';
$subsection_font_size_mobile_val = isset($theme_settings['subsection_font_size_mobile']) ? $theme_settings['subsection_font_size_mobile']['setting_value'] : '15';
$section_heading_font_size_val = isset($theme_settings['section_heading_font_size']) ? $theme_settings['section_heading_font_size']['setting_value'] : '18';
$section_heading_font_size_mobile_val = isset($theme_settings['section_heading_font_size_mobile']) ? $theme_settings['section_heading_font_size_mobile']['setting_value'] : '14';
$header_font_size_val = isset($theme_settings['header_font_size']) ? $theme_settings['header_font_size']['setting_value'] : '15';
$header_font_size_mobile_val = isset($theme_settings['header_font_size_mobile']) ? $theme_settings['header_font_size_mobile']['setting_value'] : '13';
$footer_font_size_val = isset($theme_settings['footer_font_size']) ? $theme_settings['footer_font_size']['setting_value'] : '14';
$footer_font_size_mobile_val = isset($theme_settings['footer_font_size_mobile']) ? $theme_settings['footer_font_size_mobile']['setting_value'] : '12';
$shop_cat_title_font_val = isset($theme_settings['shop_cat_title_font']) ? $theme_settings['shop_cat_title_font']['setting_value'] : 'Playfair Display';
$shop_cat_title_font_size_val = isset($theme_settings['shop_cat_title_font_size']) ? $theme_settings['shop_cat_title_font_size']['setting_value'] : '14';
$shop_cat_title_font_size_mobile_val = isset($theme_settings['shop_cat_title_font_size_mobile']) ? $theme_settings['shop_cat_title_font_size_mobile']['setting_value'] : '10';

$heading_font_style_val = isset($theme_settings['heading_font_style']) ? $theme_settings['heading_font_style']['setting_value'] : '';
$paragraph_font_style_val = isset($theme_settings['paragraph_font_style']) ? $theme_settings['paragraph_font_style']['setting_value'] : '';
$category_font_style_val = isset($theme_settings['category_font_style']) ? $theme_settings['category_font_style']['setting_value'] : '';
$subsection_font_style_val = isset($theme_settings['subsection_font_style']) ? $theme_settings['subsection_font_style']['setting_value'] : '';
$section_heading_font_style_val = isset($theme_settings['section_heading_font_style']) ? $theme_settings['section_heading_font_style']['setting_value'] : '';
$header_font_style_val = isset($theme_settings['header_font_style']) ? $theme_settings['header_font_style']['setting_value'] : '';
$footer_font_style_val = isset($theme_settings['footer_font_style']) ? $theme_settings['footer_font_style']['setting_value'] : '';

// Load Categories list from Database
$all_db_categories = $pdo->query("SELECT id, name, parent_id FROM categories ORDER BY parent_id, name")->fetchAll();
$category_names = [];
foreach ($all_db_categories as $c) {
    $category_names[$c['id']] = $c['name'];
}
$selected_categories = isset($theme_settings['homepage_selected_categories']) ? json_decode($theme_settings['homepage_selected_categories']['setting_value'], true) : [];
if (!is_array($selected_categories)) {
    $selected_categories = [];
}
$selected_categories_mobile = isset($theme_settings['homepage_selected_categories_mobile']) ? json_decode($theme_settings['homepage_selected_categories_mobile']['setting_value'], true) : [];
if (!is_array($selected_categories_mobile)) {
    $selected_categories_mobile = [];
}
$catalog_visibility = isset($theme_settings['catalog_categories_visibility']) ? json_decode($theme_settings['catalog_categories_visibility']['setting_value'], true) : [];
if (!is_array($catalog_visibility)) {
    $catalog_visibility = [];
}
?>

<style>
/* Sticky Toolbar Glassmorphism & Aesthetics */
.theme-settings-toolbar {
    position: sticky;
    z-index: 999;
    background: rgba(255, 255, 255, 0.85) !important;
    backdrop-filter: blur(10px);
    border-color: rgba(0, 0, 0, 0.08) !important;
    transition: top 0.3s ease-in-out;
    border-radius: 12px !important;
    border: 1px solid rgba(0, 0, 0, 0.08) !important;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.04), 0 1px 3px rgba(0, 0, 0, 0.02) !important;
}

/* Suggestion Dropdown Styling */
#searchSuggestions {
    border-radius: 10px !important;
    border: 1px solid rgba(0, 0, 0, 0.1) !important;
    margin-top: 6px !important;
    animation: fadeInSuggestions 0.15s ease-out;
    background: rgba(255, 255, 255, 0.98) !important;
    backdrop-filter: blur(5px);
}

@keyframes fadeInSuggestions {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}

#searchSuggestions .dropdown-item {
    transition: all 0.2s ease;
}

#searchSuggestions .dropdown-item:hover {
    background-color: rgba(30, 64, 175, 0.05) !important;
    padding-left: 1.25rem !important;
}

/* Glow Pulse Animation for scrolled-to element */
@keyframes settingPulse {
    0% {
        outline: 3px solid rgba(30, 64, 175, 0.6);
        box-shadow: 0 0 20px rgba(30, 64, 175, 0.3);
        background-color: rgba(30, 64, 175, 0.05);
    }
    50% {
        outline: 3px solid rgba(30, 64, 175, 0.8);
        box-shadow: 0 0 30px rgba(30, 64, 175, 0.5);
        background-color: rgba(30, 64, 175, 0.1);
    }
    100% {
        outline: 3px solid transparent;
        box-shadow: 0 0 0 transparent;
        background-color: transparent;
    }
}

.setting-highlight-flash {
    animation: settingPulse 2s cubic-bezier(0.25, 1, 0.5, 1) 1;
    border-radius: 8px;
    position: relative;
    z-index: 10;
}
</style>

<div class="container-fluid py-4">
    <!-- Sticky Search and Save Header Toolbar -->
    <div class="theme-settings-toolbar py-3 px-4 mb-4 border rounded shadow-sm d-flex flex-wrap align-items-center justify-content-between gap-3" style="top: 72px;">
        <!-- Search input section -->
        <div class="d-flex align-items-center flex-grow-1 position-relative" style="max-width: 500px;">
            <div class="input-group shadow-sm rounded">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" 
                       id="themeSettingSearch" 
                       class="form-control border-start-0" 
                       placeholder="Search settings (e.g. font, colors, WhatsApp...)" 
                       autocomplete="off"
                       aria-label="Search settings">
                <button class="btn btn-outline-secondary border-start-0 bg-white" type="button" id="clearSearchBtn" style="display: none; border-color: #dee2e6;">
                    <i class="bi bi-x-lg text-muted"></i>
                </button>
            </div>
            
            <!-- Floating Autocomplete Suggestions Dropdown -->
            <div class="dropdown-menu shadow-lg w-100 mt-1 border-0" id="searchSuggestions" style="display: none; position: absolute; top: 100%; left: 0; max-height: 300px; overflow-y: auto; z-index: 1000;">
                <!-- Suggestions will be generated dynamically -->
            </div>
        </div>
        
        <!-- Action Buttons Section -->
        <div class="d-flex align-items-center gap-2">
            <button type="submit" form="themeForm" class="btn btn-primary px-4 shadow-sm fw-bold">
                <i class="bi bi-check-circle-fill me-2"></i>Save All Settings
            </button>
            <a href="dashboard.php" class="btn btn-outline-secondary px-3" title="Back to Dashboard">
                <i class="bi bi-arrow-left"></i>
            </a>
        </div>
    </div>

    <!-- Quick Actions Panel -->
    <div class="card bg-light border-light-subtle shadow-sm mb-4">
        <div class="card-body py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h6 class="mb-0 fw-bold"><i class="bi bi-link-45deg text-primary me-2"></i>Quick Catalog Links:</h6>
                <small class="text-muted">Directly manage items shown on the index page.</small>
            </div>
            <div class="d-flex gap-2">
                <a href="categories.php" class="btn btn-sm btn-outline-dark"><i class="bi bi-grid me-1"></i> Add / Manage Categories</a>
                <a href="products.php" class="btn btn-sm btn-outline-dark"><i class="bi bi-gem me-1"></i> Add / Manage Products</a>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white py-3">
            <h4 class="mb-0"><i class="bi bi-palette-fill me-2"></i>Theme & Banner Settings</h4>
        </div>
        <div class="card-body p-4">
            <p class="text-muted">Customize the colors, fonts, banner sliders, category collections, and other store settings dynamically.</p>
            
            <form method="POST" action="theme_settings_save.php" id="themeForm" enctype="multipart/form-data">
                
                <!-- Section 1: Color Settings -->
                <div class="mb-5">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-paint-bucket text-primary me-2"></i>1. Brand & Theme Colors</h5>
                    <div class="row">
                        <?php foreach ($color_settings as $key => $label): ?>
                        <div class="col-md-4 mb-4">
                            <div class="form-group">
                                <label for="<?= $key ?>" class="form-label fw-semibold small text-secondary"><?= $label ?></label>
                                <div class="input-group">
                                    <input type="color" 
                                           class="form-control form-control-color" 
                                           id="<?= $key ?>" 
                                           name="<?= $key ?>" 
                                           value="<?= isset($theme_settings[$key]) ? $theme_settings[$key]['setting_value'] : '#000000' ?>"
                                           style="height: 45px; width: 60px; flex-grow: 0; cursor: pointer;">
                                    <input type="text" 
                                           class="form-control" 
                                           id="<?= $key ?>_text" 
                                           name="<?= $key ?>_text" 
                                           value="<?= isset($theme_settings[$key]) ? $theme_settings[$key]['setting_value'] : '#000000' ?>"
                                           placeholder="#000000">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Brand Logo Upload Sub-section -->
                    <div class="card border border-light-subtle shadow-none bg-light mb-4">
                        <div class="card-header bg-dark text-white fw-bold py-2 small d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-image me-1"></i> Brand Logo Image</span>
                            <span class="badge bg-secondary">Current Brand Logo</span>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3 text-center mb-3 mb-md-0">
                                    <div class="brand-logo-preview-container p-3 border rounded bg-white d-flex align-items-center justify-content-center" style="height: 120px; overflow: hidden; min-width: 150px;">
                                        <?php 
                                        $brand_logo_val = isset($theme_settings['brand_logo']) ? $theme_settings['brand_logo']['setting_value'] : '';
                                        if (!empty($brand_logo_val) && file_exists(__DIR__ . '/' . $brand_logo_val)): 
                                        ?>
                                            <img id="logoPreview" src="<?= htmlspecialchars($brand_logo_val) ?>" alt="Brand Logo Preview" class="img-fluid" style="max-height: 100px; object-fit: contain;">
                                        <?php else: ?>
                                            <div id="logoPreviewPlaceholder" class="text-center text-muted">
                                                <i class="bi bi-file-earmark-image fs-1 d-block mb-1"></i>
                                                <small class="small d-block">No Logo Uploaded</small>
                                            </div>
                                            <img id="logoPreview" src="" alt="Brand Logo Preview" class="img-fluid d-none" style="max-height: 100px; object-fit: contain;">
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <label for="brand_logo" class="form-label fw-semibold small text-secondary">Upload Brand Logo (PNG, JPG, WEBP, GIF)</label>
                                    <input type="file" class="form-control" id="brand_logo" name="brand_logo" accept="image/*">
                                    <div class="form-text text-muted small mt-1">Recommended size: horizontal layout, height 80px - 120px. Supports transparent PNGs.</div>
                                    
                                    <?php if (!empty($brand_logo_val)): ?>
                                        <div class="form-check form-switch mt-3">
                                            <input class="form-check-input" type="checkbox" id="remove_brand_logo" name="remove_brand_logo" value="1">
                                            <label class="form-check-label small fw-semibold text-danger" for="remove_brand_logo">
                                                <i class="bi bi-trash3 me-1"></i>Remove current logo and revert to text header
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row mt-3 pt-3 border-top">
                                        <div class="col-md-6 mb-2">
                                            <label for="logo_height_desktop" class="form-label fw-semibold small text-secondary">Logo Height on Desktop (px)</label>
                                            <input type="number" class="form-control form-control-sm" id="logo_height_desktop" name="logo_height_desktop" value="<?= htmlspecialchars($logo_height_desktop_val) ?>" min="20" max="150" placeholder="e.g. 50">
                                            <small class="text-muted">Recommended: 40px - 70px. Default is 50px.</small>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label for="logo_height_mobile" class="form-label fw-semibold small text-secondary">Logo Height on Mobile (px)</label>
                                            <input type="number" class="form-control form-control-sm" id="logo_height_mobile" name="logo_height_mobile" value="<?= htmlspecialchars($logo_height_mobile_val) ?>" min="15" max="80" placeholder="e.g. 38">
                                            <small class="text-muted">Recommended: 30px - 45px. Default is 38px.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Dynamic Fonts -->
                <div class="mb-5 bg-light p-3 rounded border border-light-subtle">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-fonts text-primary me-2"></i>2. Typography & Fonts</h5>
                    <p class="text-secondary small mb-4">Choose from the preset list or type any custom Google Font name (e.g. <em>Merriweather</em>, <em>Space Grotesk</em>, <em>Sacramento</em>). You can also adjust the base font sizes below.</p>
                    
                    <div class="row">
                        <!-- Heading Font -->
                        <div class="col-md-4 mb-4">
                            <div class="p-3 border rounded bg-white h-100 shadow-sm">
                                <label class="form-label fw-bold small text-dark"><i class="bi bi-fonts text-primary me-1"></i> Heading Font</label>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold small text-secondary">Font Family</label>
                                    <input list="google_fonts_list" name="heading_font" class="form-control form-control-sm" value="<?= htmlspecialchars($heading_font_val) ?>" placeholder="Type Google Font name...">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Base Size (px)</label>
                                        <input type="number" name="heading_font_size" class="form-control form-control-sm" value="<?= htmlspecialchars($heading_font_size_val) ?>" min="12" max="60" placeholder="e.g. 28">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Mobile Size (px)</label>
                                        <input type="number" name="heading_font_size_mobile" class="form-control form-control-sm" value="<?= htmlspecialchars($heading_font_size_mobile_val) ?>" min="8" max="40" placeholder="e.g. 18">
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label fw-semibold small text-secondary">Style Type</label>
                                    <select name="heading_font_style" class="form-select form-select-sm">
                                        <option value="">Default (None)</option>
                                        <option value="lowercase" <?= ($heading_font_style_val === 'lowercase') ? 'selected' : '' ?>>Lowercase</option>
                                        <option value="uppercase" <?= ($heading_font_style_val === 'uppercase') ? 'selected' : '' ?>>Uppercase</option>
                                        <option value="capitalize_first" <?= ($heading_font_style_val === 'capitalize_first') ? 'selected' : '' ?>>Capitalized (First Letter Only)</option>
                                        <option value="title_case" <?= ($heading_font_style_val === 'title_case') ? 'selected' : '' ?>>Title Case</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Paragraph/Body Font -->
                        <div class="col-md-4 mb-4">
                            <div class="p-3 border rounded bg-white h-100 shadow-sm">
                                <label class="form-label fw-bold small text-dark"><i class="bi bi-fonts text-primary me-1"></i> Paragraph/Body Font</label>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold small text-secondary">Font Family</label>
                                    <input list="google_fonts_list" name="paragraph_font" class="form-control form-control-sm" value="<?= htmlspecialchars($paragraph_font_val) ?>" placeholder="Type Google Font name...">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Base Size (px)</label>
                                        <input type="number" name="paragraph_font_size" class="form-control form-control-sm" value="<?= htmlspecialchars($paragraph_font_size_val) ?>" min="10" max="30" placeholder="e.g. 16">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Mobile Size (px)</label>
                                        <input type="number" name="paragraph_font_size_mobile" class="form-control form-control-sm" value="<?= htmlspecialchars($paragraph_font_size_mobile_val) ?>" min="8" max="30" placeholder="e.g. 14">
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label fw-semibold small text-secondary">Style Type</label>
                                    <select name="paragraph_font_style" class="form-select form-select-sm">
                                        <option value="">Default (None)</option>
                                        <option value="lowercase" <?= ($paragraph_font_style_val === 'lowercase') ? 'selected' : '' ?>>Lowercase</option>
                                        <option value="uppercase" <?= ($paragraph_font_style_val === 'uppercase') ? 'selected' : '' ?>>Uppercase</option>
                                        <option value="capitalize_first" <?= ($paragraph_font_style_val === 'capitalize_first') ? 'selected' : '' ?>>Capitalized (First Letter Only)</option>
                                        <option value="title_case" <?= ($paragraph_font_style_val === 'title_case') ? 'selected' : '' ?>>Title Case</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Category Name/Title Font -->
                        <div class="col-md-4 mb-4">
                            <div class="p-3 border rounded bg-white h-100 shadow-sm">
                                <label class="form-label fw-bold small text-dark"><i class="bi bi-fonts text-primary me-1"></i> Category Name/Title Font</label>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold small text-secondary">Font Family</label>
                                    <input list="google_fonts_list" name="category_font" class="form-control form-control-sm" value="<?= htmlspecialchars($category_font_val) ?>" placeholder="Type Google Font name...">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Base Size (px)</label>
                                        <input type="number" name="category_font_size" class="form-control form-control-sm" value="<?= htmlspecialchars($category_font_size_val) ?>" min="10" max="40" placeholder="e.g. 15">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Mobile Size (px)</label>
                                        <input type="number" name="category_font_size_mobile" class="form-control form-control-sm" value="<?= htmlspecialchars($category_font_size_mobile_val) ?>" min="8" max="30" placeholder="e.g. 12">
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label fw-semibold small text-secondary">Style Type</label>
                                    <select name="category_font_style" class="form-select form-select-sm">
                                        <option value="">Default (None)</option>
                                        <option value="lowercase" <?= ($category_font_style_val === 'lowercase') ? 'selected' : '' ?>>Lowercase</option>
                                        <option value="uppercase" <?= ($category_font_style_val === 'uppercase') ? 'selected' : '' ?>>Uppercase</option>
                                        <option value="capitalize_first" <?= ($category_font_style_val === 'capitalize_first') ? 'selected' : '' ?>>Capitalized (First Letter Only)</option>
                                        <option value="title_case" <?= ($category_font_style_val === 'title_case') ? 'selected' : '' ?>>Title Case</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Subsection Category Title Font -->
                        <div class="col-md-4 mb-4">
                            <div class="p-3 border rounded bg-white h-100 shadow-sm">
                                <label class="form-label fw-bold small text-dark"><i class="bi bi-fonts text-primary me-1"></i> Subsection Category Font</label>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold small text-secondary">Font Family</label>
                                    <input list="google_fonts_list" name="subsection_font" class="form-control form-control-sm" value="<?= htmlspecialchars($subsection_font_val) ?>" placeholder="Type Google Font name...">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Base Size (px)</label>
                                        <input type="number" name="subsection_font_size" class="form-control form-control-sm" value="<?= htmlspecialchars($subsection_font_size_val) ?>" min="10" max="40" placeholder="e.g. 18">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Mobile Size (px)</label>
                                        <input type="number" name="subsection_font_size_mobile" class="form-control form-control-sm" value="<?= htmlspecialchars($subsection_font_size_mobile_val) ?>" min="8" max="30" placeholder="e.g. 15">
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label fw-semibold small text-secondary">Style Type</label>
                                    <select name="subsection_font_style" class="form-select form-select-sm">
                                        <option value="">Default (None)</option>
                                        <option value="lowercase" <?= ($subsection_font_style_val === 'lowercase') ? 'selected' : '' ?>>Lowercase</option>
                                        <option value="uppercase" <?= ($subsection_font_style_val === 'uppercase') ? 'selected' : '' ?>>Uppercase</option>
                                        <option value="capitalize_first" <?= ($subsection_font_style_val === 'capitalize_first') ? 'selected' : '' ?>>Capitalized (First Letter Only)</option>
                                        <option value="title_case" <?= ($subsection_font_style_val === 'title_case') ? 'selected' : '' ?>>Title Case</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section Main Heading Font -->
                        <div class="col-md-4 mb-4">
                            <div class="p-3 border rounded bg-white h-100 shadow-sm">
                                <label class="form-label fw-bold small text-dark"><i class="bi bi-fonts text-primary me-1"></i> Section Main Heading Font</label>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold small text-secondary">Font Family</label>
                                    <input list="google_fonts_list" name="section_heading_font" class="form-control form-control-sm" value="<?= htmlspecialchars($section_heading_font_val) ?>" placeholder="Type Google Font name...">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Base Size (px)</label>
                                        <input type="number" name="section_heading_font_size" class="form-control form-control-sm" value="<?= htmlspecialchars($section_heading_font_size_val) ?>" min="10" max="40" placeholder="e.g. 18">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Mobile Size (px)</label>
                                        <input type="number" name="section_heading_font_size_mobile" class="form-control form-control-sm" value="<?= htmlspecialchars($section_heading_font_size_mobile_val) ?>" min="8" max="30" placeholder="e.g. 14">
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label fw-semibold small text-secondary">Style Type</label>
                                    <select name="section_heading_font_style" class="form-select form-select-sm">
                                        <option value="">Default (None)</option>
                                        <option value="lowercase" <?= ($section_heading_font_style_val === 'lowercase') ? 'selected' : '' ?>>Lowercase</option>
                                        <option value="uppercase" <?= ($section_heading_font_style_val === 'uppercase') ? 'selected' : '' ?>>Uppercase</option>
                                        <option value="capitalize_first" <?= ($section_heading_font_style_val === 'capitalize_first') ? 'selected' : '' ?>>Capitalized (First Letter Only)</option>
                                        <option value="title_case" <?= ($section_heading_font_style_val === 'title_case') ? 'selected' : '' ?>>Title Case</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Header/Navbar Font -->
                        <div class="col-md-4 mb-4">
                            <div class="p-3 border rounded bg-white h-100 shadow-sm">
                                <label class="form-label fw-bold small text-dark"><i class="bi bi-fonts text-primary me-1"></i> Header/Navbar Font</label>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold small text-secondary">Font Family</label>
                                    <input list="google_fonts_list" name="header_font" class="form-control form-control-sm" value="<?= htmlspecialchars($header_font_val) ?>" placeholder="Type Google Font name...">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Base Size (px)</label>
                                        <input type="number" name="header_font_size" class="form-control form-control-sm" value="<?= htmlspecialchars($header_font_size_val) ?>" min="10" max="30" placeholder="e.g. 15">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Mobile Size (px)</label>
                                        <input type="number" name="header_font_size_mobile" class="form-control form-control-sm" value="<?= htmlspecialchars($header_font_size_mobile_val) ?>" min="8" max="30" placeholder="e.g. 13">
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label fw-semibold small text-secondary">Style Type</label>
                                    <select name="header_font_style" class="form-select form-select-sm">
                                        <option value="">Default (None)</option>
                                        <option value="lowercase" <?= ($header_font_style_val === 'lowercase') ? 'selected' : '' ?>>Lowercase</option>
                                        <option value="uppercase" <?= ($header_font_style_val === 'uppercase') ? 'selected' : '' ?>>Uppercase</option>
                                        <option value="capitalize_first" <?= ($header_font_style_val === 'capitalize_first') ? 'selected' : '' ?>>Capitalized (First Letter Only)</option>
                                        <option value="title_case" <?= ($header_font_style_val === 'title_case') ? 'selected' : '' ?>>Title Case</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Footer Font -->
                        <div class="col-md-4 mb-4">
                            <div class="p-3 border rounded bg-white h-100 shadow-sm">
                                <label class="form-label fw-bold small text-dark"><i class="bi bi-fonts text-primary me-1"></i> Footer Font</label>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold small text-secondary">Font Family</label>
                                    <input list="google_fonts_list" name="footer_font" class="form-control form-control-sm" value="<?= htmlspecialchars($footer_font_val) ?>" placeholder="Type Google Font name...">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Base Size (px)</label>
                                        <input type="number" name="footer_font_size" class="form-control form-control-sm" value="<?= htmlspecialchars($footer_font_size_val) ?>" min="8" max="25" placeholder="e.g. 14">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Mobile Size (px)</label>
                                        <input type="number" name="footer_font_size_mobile" class="form-control form-control-sm" value="<?= htmlspecialchars($footer_font_size_mobile_val) ?>" min="6" max="25" placeholder="e.g. 12">
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label fw-semibold small text-secondary">Style Type</label>
                                    <select name="footer_font_style" class="form-select form-select-sm">
                                        <option value="">Default (None)</option>
                                        <option value="lowercase" <?= ($footer_font_style_val === 'lowercase') ? 'selected' : '' ?>>Lowercase</option>
                                        <option value="uppercase" <?= ($footer_font_style_val === 'uppercase') ? 'selected' : '' ?>>Uppercase</option>
                                        <option value="capitalize_first" <?= ($footer_font_style_val === 'capitalize_first') ? 'selected' : '' ?>>Capitalized (First Letter Only)</option>
                                        <option value="title_case" <?= ($footer_font_style_val === 'title_case') ? 'selected' : '' ?>>Title Case</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <!-- Shop By Category Title Font (Dedicated) -->
                    <div class="row mt-2">
                        <div class="col-12 mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge text-white px-2 py-1" style="background-color: #0D3B36; font-size: 0.7rem;">Shop By Category Section Only</span>
                                <small class="text-muted">The settings below apply <strong>only</strong> to the category card titles in the "Shop By Category" section on the homepage.</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="p-3 border border-2 rounded bg-white h-100 shadow-sm" style="border-color: #0D3B36 !important;">
                                <label class="form-label fw-bold small text-dark"><i class="bi bi-grid-fill text-success me-1"></i> Shop By Category — Card Title Font</label>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold small text-secondary">Font Family</label>
                                    <input list="google_fonts_list" name="shop_cat_title_font" class="form-control form-control-sm" value="<?= htmlspecialchars($shop_cat_title_font_val) ?>" placeholder="Type Google Font name...">
                                    <small class="text-muted">Font family for category name text on cards (e.g. Playfair Display).</small>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Desktop Size (px)</label>
                                        <input type="number" name="shop_cat_title_font_size" class="form-control form-control-sm" value="<?= htmlspecialchars($shop_cat_title_font_size_val) ?>" min="8" max="40" placeholder="e.g. 14">
                                        <small class="text-muted">Default: 14px</small>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-semibold small text-secondary">Mobile Size (px)</label>
                                        <input type="number" name="shop_cat_title_font_size_mobile" class="form-control form-control-sm" value="<?= htmlspecialchars($shop_cat_title_font_size_mobile_val) ?>" min="6" max="30" placeholder="e.g. 10">
                                        <small class="text-muted">Default: 10px</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <datalist id="google_fonts_list">
                        <?php foreach ($fonts_list as $f_key => $f_name): ?>
                            <option value="<?= htmlspecialchars($f_key) ?>"><?= htmlspecialchars($f_name) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <!-- Section 3: Store Information -->
                <div class="mb-5 bg-light p-3 rounded border border-light-subtle">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-info-circle text-primary me-2"></i>3. Global Store Info & Topbar Headers</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold small text-secondary">Global WhatsApp Number (with country code, no +)</label>
                            <input type="text" class="form-control" name="whatsapp_number" value="<?= htmlspecialchars($whatsapp_number_val) ?>" placeholder="e.g. 918208719194">
                            <small class="text-muted">Used for WhatsApp click-to-chat, inquiry clicks, and price quotes.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold small text-secondary">Topbar Bullet 1 Text</label>
                            <input type="text" class="form-control" name="topbar_text_1" value="<?= htmlspecialchars($topbar_text_1) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold small text-secondary">Topbar Bullet 2 Text</label>
                            <input type="text" class="form-control" name="topbar_text_2" value="<?= htmlspecialchars($topbar_text_2) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold small text-secondary">Topbar Bullet 3 Text</label>
                            <input type="text" class="form-control" name="topbar_text_3" value="<?= htmlspecialchars($topbar_text_3) ?>">
                        </div>
                    </div>
                </div>

                <!-- Section 4: Collection & Custom Titles -->
                <div class="mb-5">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-grid-3x3 text-primary me-2"></i>4. Collection Grid & Catalog Title Overrides</h5>
                    
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold small text-secondary">"Shop by Category" Grid Title</label>
                            <input type="text" class="form-control" name="category_section_title" value="<?= htmlspecialchars($category_title_val) ?>" placeholder="Shop by Category">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold small text-secondary">"New Arrivals" Slider Title</label>
                            <input type="text" class="form-control" name="new_arrivals_section_title" value="<?= htmlspecialchars($new_arrivals_title_val) ?>" placeholder="New Arrivals">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold small text-secondary">"Wholesale Catalog" Nested Grid Title</label>
                            <input type="text" class="form-control" name="catalog_section_title" value="<?= htmlspecialchars($catalog_title_val) ?>" placeholder="Our Wholesale Catalog">
                        </div>
                    </div>

                    <!-- Category Link for New Arrivals -->
                    <div class="row mb-4 bg-light p-3 rounded border border-light-subtle mx-0">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold small text-secondary">Linked Category for New Arrivals</label>
                            <select name="new_arrivals_category_id" class="form-select">
                                <option value="">Show Latest Designs (All Categories)</option>
                                <?php foreach($all_db_categories as $db_cat): ?>
                                    <?php 
                                    $displayName = $db_cat['name'];
                                    if (!empty($db_cat['parent_id']) && isset($category_names[$db_cat['parent_id']])) {
                                        $displayName = $category_names[$db_cat['parent_id']] . ' » ' . $displayName;
                                    }
                                    ?>
                                    <option value="<?= $db_cat['id'] ?>" <?= $new_arrivals_category_id_val == $db_cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($displayName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Choose a specific category to showcase in the "New Arrivals" slider on the homepage. Leave as "All Categories" to display the globally newest designs.</small>
                        </div>
                    </div>

                    <!-- Category Section Dimensions -->
                    <div class="row mb-4 bg-light p-3 rounded border border-light-subtle mx-0">
                        <div class="col-md-3 mb-2">
                            <label class="form-label fw-semibold small text-secondary">Category Card Height (Desktop - px)</label>
                            <input type="number" name="category_card_height" value="<?= htmlspecialchars($theme_settings['category_card_height']['setting_value'] ?? '220') ?>" class="form-control" min="150" max="500">
                            <small class="text-muted">Recommended: 200 - 300px. Default is 220px.</small>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label fw-semibold small text-secondary">Category Card Height (Mobile - px)</label>
                            <input type="number" name="category_card_height_mobile" value="<?= htmlspecialchars($theme_settings['category_card_height_mobile']['setting_value'] ?? '105') ?>" class="form-control" min="10" max="500">
                            <small class="text-muted">Adjustable range: 10 - 500px. Default is 105px.</small>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label fw-semibold small text-secondary">Category Title Font Size (Mobile - px)</label>
                            <input type="number" name="category_title_font_size_mobile" value="<?= htmlspecialchars($theme_settings['category_title_font_size_mobile']['setting_value'] ?? '10') ?>" class="form-control" min="6" max="30">
                            <small class="text-muted">Font size for category name text in the "Shop By Category" grid on mobile. Default is 10px.</small>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label fw-semibold small text-secondary">Category Section Width (Desktop)</label>
                            <?php $cat_width_val = $theme_settings['category_section_width']['setting_value'] ?? '100%'; ?>
                            <select name="category_section_width" class="form-select">
                                <option value="100%" <?= $cat_width_val === '100%' ? 'selected' : '' ?>>Full Width (100%)</option>
                                <option value="1400px" <?= $cat_width_val === '1400px' ? 'selected' : '' ?>>Boxed Large (1400px)</option>
                                <option value="1200px" <?= $cat_width_val === '1200px' ? 'selected' : '' ?>>Boxed Medium (1200px)</option>
                            </select>
                            <small class="text-muted">Centers the Category Grid on desktop.</small>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label fw-semibold small text-secondary">Categories per Row (Desktop)</label>
                            <?php $cat_row_val = $theme_settings['category_row_count']['setting_value'] ?? '5'; ?>
                            <select name="category_row_count" class="form-select">
                                <option value="3" <?= $cat_row_val === '3' ? 'selected' : '' ?>>3 Categories per Row</option>
                                <option value="4" <?= $cat_row_val === '4' ? 'selected' : '' ?>>4 Categories per Row</option>
                                <option value="5" <?= $cat_row_val === '5' ? 'selected' : '' ?>>5 Categories per Row</option>
                                <option value="6" <?= $cat_row_val === '6' ? 'selected' : '' ?>>6 Categories per Row</option>
                            </select>
                            <small class="text-muted">Number of columns in category grid.</small>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="form-label fw-semibold small text-secondary">Categories per Row (Mobile)</label>
                            <?php $cat_row_mobile_val = $theme_settings['category_row_count_mobile']['setting_value'] ?? '4'; ?>
                            <select name="category_row_count_mobile" class="form-select">
                                <option value="1" <?= $cat_row_mobile_val === '1' ? 'selected' : '' ?>>1 Category per Row</option>
                                <option value="2" <?= $cat_row_mobile_val === '2' ? 'selected' : '' ?>>2 Categories per Row</option>
                                <option value="3" <?= $cat_row_mobile_val === '3' ? 'selected' : '' ?>>3 Categories per Row</option>
                                <option value="4" <?= $cat_row_mobile_val === '4' ? 'selected' : '' ?>>4 Categories per Row</option>
                                <option value="5" <?= $cat_row_mobile_val === '5' ? 'selected' : '' ?>>5 Categories per Row</option>
                            </select>
                            <small class="text-muted">Number of columns in category grid on mobile.</small>
                        </div>
                    </div>

                    <!-- Category Selector Checkboxes -->
                    <div class="card border border-light-subtle shadow-none bg-light mb-4">
                        <div class="card-header bg-dark text-white fw-bold py-2 small">
                            Homepage "Shop by Category" filter selection
                        </div>
                        <div class="card-body">
                            <p class="text-secondary small mb-3">Check the boxes to manually select which Categories show on the homepage category grid. If none are checked, all categories will be shown.</p>
                            
                            <div class="row">
                                <?php foreach($all_db_categories as $db_cat): ?>
                                    <div class="col-md-4 col-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="homepage_selected_categories[]" value="<?= $db_cat['id'] ?>" id="cat_check_<?= $db_cat['id'] ?>" <?= in_array($db_cat['id'], $selected_categories) ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="cat_check_<?= $db_cat['id'] ?>">
                                                <?= htmlspecialchars($db_cat['name']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Category Selector Checkboxes (Mobile) -->
                    <div class="card border border-light-subtle shadow-none bg-light mb-4">
                        <div class="card-header bg-dark text-white fw-bold py-2 small">
                            Homepage "Shop by Category" filter selection (Mobile Only)
                        </div>
                        <div class="card-body">
                            <p class="text-secondary small mb-3">Check the boxes to manually select which Categories show on the mobile homepage category grid. If none are checked, all categories will be shown.</p>
                            
                            <div class="row">
                                <?php foreach($all_db_categories as $db_cat): ?>
                                    <div class="col-md-4 col-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="homepage_selected_categories_mobile[]" value="<?= $db_cat['id'] ?>" id="cat_check_mobile_<?= $db_cat['id'] ?>" <?= in_array($db_cat['id'], $selected_categories_mobile) ? 'checked' : '' ?>>
                                            <label class="form-check-label small" for="cat_check_mobile_<?= $db_cat['id'] ?>">
                                                <?= htmlspecialchars($db_cat['name']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- "Our Wholesale Catalog" Parent Categories Visibility -->
                    <div class="card border border-light-subtle shadow-none bg-light mb-4">
                        <div class="card-header bg-dark text-white fw-bold py-2 small">
                            "Our Wholesale Catalog" Parent Categories Visibility
                        </div>
                        <div class="card-body">
                            <p class="text-secondary small mb-3">Choose whether to Show or Hide each parent category in the "Our Wholesale Catalog" section on the storefront.</p>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered bg-white align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="px-2">Parent Category</th>
                                            <th style="width: 200px;" class="px-2">Visibility</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $parent_categories_only = array_filter($all_db_categories, function($c) {
                                            return empty($c['parent_id']);
                                        });
                                        foreach($parent_categories_only as $p_cat): 
                                            $current_vis = $catalog_visibility[$p_cat['id']] ?? 'show';
                                        ?>
                                            <tr>
                                                <td class="fw-semibold px-2"><?= htmlspecialchars($p_cat['name']) ?></td>
                                                <td class="px-2">
                                                    <select name="catalog_categories_visibility[<?= $p_cat['id'] ?>]" class="form-select form-select-sm">
                                                        <option value="show" <?= $current_vis === 'show' ? 'selected' : '' ?>>Show category</option>
                                                        <option value="hide" <?= $current_vis === 'hide' ? 'selected' : '' ?>>Hide category</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Hero Banner Carousel -->
                <div class="mb-5">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-images text-primary me-2"></i>5. Hero Banner Carousel Slides (Max 3)</h5>
                    
                    <!-- Hero Banner Dimensions Setup -->
                    <div class="row bg-light p-3 rounded border border-light-subtle mb-4 mx-0">
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-semibold small text-secondary">Hero Slider Height (Desktop - px)</label>
                            <input type="number" name="hero_height_desktop" value="<?= htmlspecialchars($theme_settings['hero_height_desktop']['setting_value'] ?? '750') ?>" class="form-control" min="300" max="1000">
                            <small class="text-muted">Recommended: 600 - 800px. Default is 750px.</small>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-semibold small text-secondary">Hero Slider Width (Desktop)</label>
                            <?php $hero_width_val = $theme_settings['hero_width_desktop']['setting_value'] ?? '100%'; ?>
                            <select name="hero_width_desktop" class="form-select">
                                <option value="100%" <?= $hero_width_val === '100%' ? 'selected' : '' ?>>Full Width (100%)</option>
                                <option value="1400px" <?= $hero_width_val === '1400px' ? 'selected' : '' ?>>Boxed Large (1400px)</option>
                                <option value="1200px" <?= $hero_width_val === '1200px' ? 'selected' : '' ?>>Boxed Medium (1200px)</option>
                            </select>
                            <small class="text-muted">Boxed widths add rounded corners and a shadow on desktop view.</small>
                        </div>
                    </div>
                    <p class="text-secondary small mb-4">Customize the text details and background images for the landing hero image carousel.</p>
                    
                    <div class="row">
                        <?php for($i = 0; $i < 3; $i++): ?>
                            <?php $slide = $hero_carousel_data[$i] ?? ['title'=>'', 'subtitle'=>'', 'badge_text'=>'', 'button_text'=>'', 'button_link'=>'', 'image_path'=>'']; ?>
                            <div class="col-lg-4 mb-4">
                                <div class="card border border-secondary border-opacity-25 h-100 shadow-sm">
                                    <div class="card-header bg-dark text-white fw-bold py-2">
                                        Slide <?= $i + 1 ?>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" name="hero_slide_visible_<?= $i ?>" value="1" id="hero_slide_visible_<?= $i ?>" <?= (!isset($slide['is_visible']) || $slide['is_visible'] == 1) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-semibold text-secondary" for="hero_slide_visible_<?= $i ?>">Show Slide on Storefront</label>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Slide Background Image</label>
                                            <?php if (!empty($slide['image_path'])): ?>
                                                <div class="mb-2 position-relative">
                                                    <img src="<?= htmlspecialchars($slide['image_path']) ?>" alt="Slide Image" class="img-fluid rounded border" style="max-height: 100px; width: 100%; object-fit: cover;">
                                                    <input type="hidden" name="hero_slide_existing_img_<?= $i ?>" value="<?= htmlspecialchars($slide['image_path']) ?>">
                                                </div>
                                            <?php endif; ?>
                                            <input type="file" name="hero_slide_img_<?= $i ?>" accept="image/*" class="form-control form-control-sm">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Badge Text</label>
                                            <input type="text" name="hero_slide_badge_<?= $i ?>" value="<?= htmlspecialchars($slide['badge_text']) ?>" class="form-control form-control-sm" placeholder="e.g. New / Hot">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Heading Title</label>
                                            <input type="text" name="hero_slide_title_<?= $i ?>" value="<?= htmlspecialchars($slide['title']) ?>" class="form-control form-control-sm" placeholder="Wholesale Jewellery Collection">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Subtitle Text</label>
                                            <input type="text" name="hero_slide_subtitle_<?= $i ?>" value="<?= htmlspecialchars($slide['subtitle']) ?>" class="form-control form-control-sm" placeholder="PREMIUM 925 STERLING SILVER">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Button Text</label>
                                            <input type="text" name="hero_slide_btn_text_<?= $i ?>" value="<?= htmlspecialchars($slide['button_text']) ?>" class="form-control form-control-sm" placeholder="Explore Collection">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Button Link</label>
                                            <input type="text" name="hero_slide_btn_link_<?= $i ?>" value="<?= htmlspecialchars($slide['button_link']) ?>" class="form-control form-control-sm" placeholder="#catalog / shop.php?cat=1">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Section 5b: Mobile Hero Banner Carousel -->
                <div class="mb-5">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-phone text-primary me-2"></i>5b. Mobile Hero Banner Carousel Slides (Max 3)</h5>
                    
                    <!-- Mobile Hero Banner Dimensions Setup -->
                    <div class="row bg-light p-3 rounded border border-light-subtle mb-4 mx-0">
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-semibold small text-secondary">Hero Slider Height (Mobile - px)</label>
                            <input type="number" name="hero_height_mobile" value="<?= htmlspecialchars($theme_settings['hero_height_mobile']['setting_value'] ?? '200') ?>" class="form-control" min="150" max="600">
                            <small class="text-muted">Recommended: 200 - 350px. Default is 200px.</small>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-semibold small text-secondary">Hero Slider Width (Mobile)</label>
                            <?php $hero_width_mobile_val = $theme_settings['hero_width_mobile']['setting_value'] ?? '100%'; ?>
                            <select name="hero_width_mobile" class="form-select">
                                <option value="100%" <?= $hero_width_mobile_val === '100%' ? 'selected' : '' ?>>Full Width (100%)</option>
                                <option value="1400px" <?= $hero_width_mobile_val === '1400px' ? 'selected' : '' ?>>Boxed Large (1400px)</option>
                                <option value="1200px" <?= $hero_width_mobile_val === '1200px' ? 'selected' : '' ?>>Boxed Medium (1200px)</option>
                            </select>
                            <small class="text-muted">Boxed widths add rounded corners and a shadow on mobile view.</small>
                        </div>
                    </div>
                    <p class="text-secondary small mb-4">Customize the text details and background images for the mobile view landing hero image carousel.</p>
                    
                    <div class="row">
                        <?php for($i = 0; $i < 3; $i++): ?>
                            <?php $slide_mobile = $hero_carousel_mobile_data[$i] ?? ['title'=>'', 'subtitle'=>'', 'badge_text'=>'', 'button_text'=>'', 'button_link'=>'', 'image_path'=>'']; ?>
                            <div class="col-lg-4 mb-4">
                                <div class="card border border-secondary border-opacity-25 h-100 shadow-sm">
                                    <div class="card-header bg-dark text-white fw-bold py-2">
                                        Mobile Slide <?= $i + 1 ?>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" name="hero_slide_mobile_visible_<?= $i ?>" value="1" id="hero_slide_mobile_visible_<?= $i ?>" <?= (!isset($slide_mobile['is_visible']) || $slide_mobile['is_visible'] == 1) ? 'checked' : '' ?>>
                                            <label class="form-check-label small fw-semibold text-secondary" for="hero_slide_mobile_visible_<?= $i ?>">Show Slide on Storefront (Mobile)</label>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Slide Background Image</label>
                                            <?php if (!empty($slide_mobile['image_path'])): ?>
                                                <div class="mb-2 position-relative">
                                                    <img src="<?= htmlspecialchars($slide_mobile['image_path']) ?>" alt="Mobile Slide Image" class="img-fluid rounded border" style="max-height: 100px; width: 100%; object-fit: cover;">
                                                    <input type="hidden" name="hero_slide_mobile_existing_img_<?= $i ?>" value="<?= htmlspecialchars($slide_mobile['image_path']) ?>">
                                                </div>
                                            <?php endif; ?>
                                            <input type="file" name="hero_slide_mobile_img_<?= $i ?>" accept="image/*" class="form-control form-control-sm">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Badge Text</label>
                                            <input type="text" name="hero_slide_mobile_badge_<?= $i ?>" value="<?= htmlspecialchars($slide_mobile['badge_text']) ?>" class="form-control form-control-sm" placeholder="e.g. New / Hot">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Heading Title</label>
                                            <input type="text" name="hero_slide_mobile_title_<?= $i ?>" value="<?= htmlspecialchars($slide_mobile['title']) ?>" class="form-control form-control-sm" placeholder="Wholesale Jewellery Collection">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Subtitle Text</label>
                                            <input type="text" name="hero_slide_mobile_subtitle_<?= $i ?>" value="<?= htmlspecialchars($slide_mobile['subtitle']) ?>" class="form-control form-control-sm" placeholder="PREMIUM 925 STERLING SILVER">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Button Text</label>
                                            <input type="text" name="hero_slide_mobile_btn_text_<?= $i ?>" value="<?= htmlspecialchars($slide_mobile['button_text']) ?>" class="form-control form-control-sm" placeholder="Explore Collection">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Button Link</label>
                                            <input type="text" name="hero_slide_mobile_btn_link_<?= $i ?>" value="<?= htmlspecialchars($slide_mobile['button_link']) ?>" class="form-control form-control-sm" placeholder="#catalog / shop.php?cat=1">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Section 6: Promotional Banners -->
                <div class="mb-5">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-grid-3x3-gap text-primary me-2"></i>6. Four Promotional Banners Customization</h5>
                    <p class="text-secondary small mb-4">Configure the 4 promotional banners featured above the footer section. Note: These are image-only banners with no overlay text.</p>
                    
                    <!-- Promo Banner Dimensions Setup -->
                    <div class="row bg-light p-3 rounded border border-light-subtle mb-4 mx-0">
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-semibold small text-secondary">Promo Banner Height (Desktop - px)</label>
                            <input type="number" name="promo_height_desktop" value="<?= htmlspecialchars($promo_height_desktop_val) ?>" class="form-control" min="100" max="600">
                            <small class="text-muted">Recommended: 200 - 400px. Default is 306px.</small>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label fw-semibold small text-secondary">Promo Banner Max Width (Desktop - px)</label>
                            <input type="number" name="promo_width_desktop" value="<?= htmlspecialchars($promo_width_desktop_val) ?>" class="form-control" min="100" max="600">
                            <small class="text-muted">Recommended: 250 - 450px. Default is 364px.</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <?php for($i = 0; $i < 4; $i++): ?>
                            <?php $banner = $promo_banners_data[$i] ?? ['button_link'=>'', 'image_path'=>'']; ?>
                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="card border border-secondary border-opacity-25 h-100 shadow-sm">
                                    <div class="card-header bg-dark text-white fw-bold py-2">
                                        Promo Banner <?= $i + 1 ?>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Banner Image (364 x 306 px)</label>
                                            <?php if (!empty($banner['image_path'])): ?>
                                                <div class="mb-2 position-relative">
                                                    <img src="<?= htmlspecialchars($banner['image_path']) ?>" alt="Promo Image" class="img-fluid rounded border" style="height: 120px; width: 100%; object-fit: contain; background: #eee;">
                                                    <input type="hidden" name="promo_banner_existing_img_<?= $i ?>" value="<?= htmlspecialchars($banner['image_path']) ?>">
                                                </div>
                                            <?php endif; ?>
                                            <input type="file" name="promo_banner_img_<?= $i ?>" accept="image/*" class="form-control form-control-sm">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold">Banner Click Link</label>
                                            <input type="text" name="promo_banner_btn_link_<?= $i ?>" value="<?= htmlspecialchars($banner['button_link'] ?? '') ?>" class="form-control form-control-sm" placeholder="#catalog or shop.php?cat=1">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Section 7: Visibility Controls -->
                <div class="mb-5 bg-light p-3 rounded border border-light-subtle">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-eye text-primary me-2"></i>7. Page Visibility Options</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <?php $bannerEnabled = isset($theme_settings['banner_enabled']) ? $theme_settings['banner_enabled']['setting_value'] : '1'; ?>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="banner_enabled" name="banner_enabled" value="1" <?= $bannerEnabled === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold text-secondary" for="banner_enabled">Show Hero Banner Carousel (Desktop)</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <?php $bannerMobileEnabled = isset($theme_settings['banner_mobile_enabled']) ? $theme_settings['banner_mobile_enabled']['setting_value'] : '1'; ?>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="banner_mobile_enabled" name="banner_mobile_enabled" value="1" <?= $bannerMobileEnabled === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold text-secondary" for="banner_mobile_enabled">Show Hero Banner Carousel (Mobile)</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <?php $showCategories = isset($theme_settings['show_categories']) ? $theme_settings['show_categories']['setting_value'] : '1'; ?>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="show_categories" name="show_categories" value="1" <?= $showCategories === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold text-secondary" for="show_categories">Show Category Grid on Homepage</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 8: Index Page Section Dimensions -->
                <div class="mb-5">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-layout-three-columns text-primary me-2"></i>8. Index Page Section Height &amp; Width Control</h5>
                    <p class="text-secondary small mb-4">Precisely control the <strong>height</strong> and <strong>width</strong> of every section on the index (storefront) page — separately for <span class="badge bg-primary">Desktop</span>, <span class="badge bg-success">Tablet</span>, and <span class="badge bg-warning text-dark">Mobile</span>. Width options: <em>Fit to Screen (100%)</em>, <em>720px</em>, <em>1770px</em>, or enter a custom px value.</p>

                    <?php
                    // Helper: width presets
                    $width_presets = ['100%' => 'Fit to Screen (100%)', '1770px' => '1770px', '720px' => '720px', 'custom' => 'Custom (enter px)'];

                    // All sections to control
                    $index_sections = [
                        'hero'        => ['label' => '🖼️ Hero Banner Carousel', 'icon' => 'bi-images',             'has_height' => true,  'color' => 'primary'],
                        'category'    => ['label' => '🗂️ Shop by Category Grid', 'icon' => 'bi-grid-3x3-gap',       'has_height' => true,  'color' => 'success'],
                        'new_arrivals'=> ['label' => '✨ New Arrivals Slider',   'icon' => 'bi-lightning-charge',   'has_height' => true,  'color' => 'warning'],
                        'promo'       => ['label' => '📢 Promotional Banners',   'icon' => 'bi-grid-3x3-gap-fill',  'has_height' => true,  'color' => 'danger'],
                        'why_us'      => ['label' => '⭐ Why Choose Us',         'icon' => 'bi-star',               'has_height' => true,  'color' => 'info'],
                        'catalog'     => ['label' => '📚 Wholesale Catalog',     'icon' => 'bi-book',               'has_height' => false, 'color' => 'secondary'],
                    ];

                    $devices = [
                        'desktop' => ['label' => 'Desktop', 'icon' => 'bi-display',        'badge' => 'bg-primary',              'breakpoint' => '≥ 992px'],
                        'tablet'  => ['label' => 'Tablet',  'icon' => 'bi-tablet',         'badge' => 'bg-success',              'breakpoint' => '768–991px'],
                        'mobile'  => ['label' => 'Mobile',  'icon' => 'bi-phone',          'badge' => 'bg-warning text-dark',    'breakpoint' => '< 768px'],
                    ];

                    foreach ($index_sections as $sec_key => $sec_info):
                    ?>
                    <div class="card border border-light-subtle shadow-sm mb-4">
                        <div class="card-header py-2 d-flex align-items-center gap-2" style="background: linear-gradient(135deg,#f8f9fa,#e9ecef);">
                            <i class="bi <?= $sec_info['icon'] ?> text-<?= $sec_info['color'] ?> fs-5"></i>
                            <span class="fw-bold text-dark"><?= $sec_info['label'] ?></span>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3">
                            <?php foreach ($devices as $dev_key => $dev_info):
                                $w_key = "section_{$sec_key}_width_{$dev_key}";
                                $h_key = "section_{$sec_key}_height_{$dev_key}";
                                $w_val = $theme_settings[$w_key]['setting_value'] ?? '100%';
                                $h_val = $theme_settings[$h_key]['setting_value'] ?? '';
                                // Detect if value is a custom px (not one of presets except 100%)
                                $is_custom_w = !in_array($w_val, ['100%', '1770px', '720px', '']) && $w_val !== '';
                                $select_w_val = $is_custom_w ? 'custom' : ($w_val ?: '100%');
                            ?>
                                <div class="col-md-4">
                                    <div class="p-3 border rounded h-100" style="border-left: 4px solid var(--bs-<?= $dev_info['badge'] === 'bg-warning text-dark' ? 'warning' : ($dev_info['badge'] === 'bg-success' ? 'success' : 'primary') ?>) !important;">
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <i class="bi <?= $dev_info['icon'] ?>"></i>
                                            <span class="badge <?= $dev_info['badge'] ?> small"><?= $dev_info['label'] ?></span>
                                            <span class="text-muted" style="font-size:0.7rem;"><?= $dev_info['breakpoint'] ?></span>
                                        </div>

                                        <!-- Width -->
                                        <div class="mb-2">
                                            <label class="form-label fw-semibold small text-secondary mb-1">Width</label>
                                            <select name="<?= $w_key ?>" class="form-select form-select-sm section-width-select" data-custom-target="<?= $w_key ?>_custom">
                                                <option value="100%" <?= $select_w_val === '100%' ? 'selected' : '' ?>>Fit to Screen (100%)</option>
                                                <option value="1770px" <?= $select_w_val === '1770px' ? 'selected' : '' ?>>1770px</option>
                                                <option value="720px" <?= $select_w_val === '720px' ? 'selected' : '' ?>>720px</option>
                                                <option value="custom" <?= $select_w_val === 'custom' ? 'selected' : '' ?>>Custom (px)</option>
                                            </select>
                                            <!-- Custom px input (shown only when Custom is selected) -->
                                            <div id="<?= $w_key ?>_custom_wrap" class="mt-1" style="<?= $select_w_val === 'custom' ? '' : 'display:none;' ?>">
                                                <input type="number" class="form-control form-control-sm" name="<?= $w_key ?>_custom" id="<?= $w_key ?>_custom" placeholder="e.g. 1400" min="300" max="3000" value="<?= $is_custom_w ? htmlspecialchars(rtrim($w_val,'px')) : '' ?>">
                                                <small class="text-muted">Enter width in px (e.g. 1400)</small>
                                            </div>
                                        </div>

                                        <?php if ($sec_info['has_height']): ?>
                                        <!-- Height -->
                                        <div class="mb-0">
                                            <label class="form-label fw-semibold small text-secondary mb-1">Height (px) <span class="text-muted fw-normal">(leave blank = auto)</span></label>
                                            <input type="number" class="form-control form-control-sm" name="<?= $h_key ?>" placeholder="auto" min="50" max="2000" value="<?= htmlspecialchars($h_val) ?>">
                                            <small class="text-muted">Pixels. Leave blank for auto height.</small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Section 9: Mobile Sidemenu Settings -->
                <div class="mb-5 bg-light p-3 rounded border border-light-subtle">
                    <h5 class="fw-bold text-dark border-bottom pb-2 mb-3" id="sidemenu-settings"><i class="bi bi-layout-sidebar-inset-reverse text-primary me-2"></i>9. Mobile Sidemenu Settings</h5>
                    <div class="row">
                        <!-- Sidemenu Background Color -->
                        <div class="col-md-3 mb-4">
                            <div class="form-group">
                                <label for="sidemenu_bg_color" class="form-label fw-semibold small text-secondary">Sidemenu Background Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="sidemenu_bg_color" name="sidemenu_bg_color" value="<?= isset($theme_settings['sidemenu_bg_color']) ? htmlspecialchars($theme_settings['sidemenu_bg_color']['setting_value']) : '#ffffff' ?>" style="height: 45px; width: 60px; flex-grow: 0; cursor: pointer;">
                                    <input type="text" class="form-control" id="sidemenu_bg_color_text" name="sidemenu_bg_color_text" value="<?= isset($theme_settings['sidemenu_bg_color']) ? htmlspecialchars($theme_settings['sidemenu_bg_color']['setting_value']) : '#ffffff' ?>" placeholder="#ffffff">
                                </div>
                            </div>
                        </div>

                        <!-- Sidemenu Font Color -->
                        <div class="col-md-3 mb-4">
                            <div class="form-group">
                                <label for="sidemenu_font_color" class="form-label fw-semibold small text-secondary">Sidemenu Font Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="sidemenu_font_color" name="sidemenu_font_color" value="<?= isset($theme_settings['sidemenu_font_color']) ? htmlspecialchars($theme_settings['sidemenu_font_color']['setting_value']) : '#000000' ?>" style="height: 45px; width: 60px; flex-grow: 0; cursor: pointer;">
                                    <input type="text" class="form-control" id="sidemenu_font_color_text" name="sidemenu_font_color_text" value="<?= isset($theme_settings['sidemenu_font_color']) ? htmlspecialchars($theme_settings['sidemenu_font_color']['setting_value']) : '#000000' ?>" placeholder="#000000">
                                </div>
                            </div>
                        </div>

                        <!-- Sidemenu Font -->
                        <div class="col-md-3 mb-4">
                            <div class="form-group">
                                <label for="sidemenu_font" class="form-label fw-semibold small text-secondary">Sidemenu Font Type</label>
                                <input list="google_fonts_list" name="sidemenu_font" id="sidemenu_font" class="form-control" value="<?= isset($theme_settings['sidemenu_font']) ? htmlspecialchars($theme_settings['sidemenu_font']['setting_value']) : 'Inter' ?>" placeholder="Type Google Font name...">
                            </div>
                        </div>

                        <!-- Menu Font Size -->
                        <div class="col-md-3 mb-4">
                            <div class="form-group">
                                <label for="sidemenu_font_size" class="form-label fw-semibold small text-secondary">Menu Font Size (px)</label>
                                <input type="number" name="sidemenu_font_size" id="sidemenu_font_size" class="form-control" value="<?= isset($theme_settings['sidemenu_font_size']) ? htmlspecialchars($theme_settings['sidemenu_font_size']['setting_value']) : '16' ?>" min="8" max="40" placeholder="e.g. 16">
                            </div>
                        </div>

                        <!-- Heading Font Size -->
                        <div class="col-md-3 mb-4">
                            <div class="form-group">
                                <label for="sidemenu_heading_font_size" class="form-label fw-semibold small text-secondary">Heading Font Size (px)</label>
                                <input type="number" name="sidemenu_heading_font_size" id="sidemenu_heading_font_size" class="form-control" value="<?= isset($theme_settings['sidemenu_heading_font_size']) ? htmlspecialchars($theme_settings['sidemenu_heading_font_size']['setting_value']) : '18' ?>" min="8" max="40" placeholder="e.g. 18">
                            </div>
                        </div>

                        <!-- Space Between Menu Items -->
                        <div class="col-md-3 mb-4">
                            <div class="form-group">
                                <label for="sidemenu_item_spacing" class="form-label fw-semibold small text-secondary">Menu Item Spacing (px)</label>
                                <input type="number" name="sidemenu_item_spacing" id="sidemenu_item_spacing" class="form-control" value="<?= isset($theme_settings['sidemenu_item_spacing']) ? htmlspecialchars($theme_settings['sidemenu_item_spacing']['setting_value']) : '2' ?>" min="0" max="30" placeholder="e.g. 2">
                            </div>
                        </div>

                        <!-- Heading Font Style -->
                        <div class="col-md-3 mb-4">
                            <div class="form-group">
                                <label for="sidemenu_heading_font_style" class="form-label fw-semibold small text-secondary">Heading Font Style</label>
                                <?php $shfs = isset($theme_settings['sidemenu_heading_font_style']) ? $theme_settings['sidemenu_heading_font_style']['setting_value'] : ''; ?>
                                <select name="sidemenu_heading_font_style" id="sidemenu_heading_font_style" class="form-select">
                                    <option value="">Default</option>
                                    <option value="lowercase" <?= ($shfs === 'lowercase') ? 'selected' : '' ?>>Lowercase</option>
                                    <option value="uppercase" <?= ($shfs === 'uppercase') ? 'selected' : '' ?>>Uppercase</option>
                                    <option value="capitalize_first" <?= ($shfs === 'capitalize_first') ? 'selected' : '' ?>>Capitalized (First Letter)</option>
                                    <option value="title_case" <?= ($shfs === 'title_case') ? 'selected' : '' ?>>Title Case</option>
                                </select>
                            </div>
                        </div>

                        <!-- Menu Item Font Style -->
                        <div class="col-md-3 mb-4">
                            <div class="form-group">
                                <label for="sidemenu_menu_font_style" class="form-label fw-semibold small text-secondary">Menu Font Style</label>
                                <?php $smfs = isset($theme_settings['sidemenu_menu_font_style']) ? $theme_settings['sidemenu_menu_font_style']['setting_value'] : ''; ?>
                                <select name="sidemenu_menu_font_style" id="sidemenu_menu_font_style" class="form-select">
                                    <option value="">Default</option>
                                    <option value="lowercase" <?= ($smfs === 'lowercase') ? 'selected' : '' ?>>Lowercase</option>
                                    <option value="uppercase" <?= ($smfs === 'uppercase') ? 'selected' : '' ?>>Uppercase</option>
                                    <option value="capitalize_first" <?= ($smfs === 'capitalize_first') ? 'selected' : '' ?>>Capitalized (First Letter)</option>
                                    <option value="title_case" <?= ($smfs === 'title_case') ? 'selected' : '' ?>>Title Case</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Sidemenu Banner Upload -->
                    <div class="card border border-light-subtle shadow-none bg-white mb-4">
                        <div class="card-header bg-dark text-white fw-bold py-2 small d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-image me-1"></i> Sidemenu Banner Image</span>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-3 text-center mb-3 mb-md-0">
                                    <div class="p-3 border rounded bg-light d-flex align-items-center justify-content-center" style="height: 120px; overflow: hidden; min-width: 150px;">
                                        <?php 
                                        $sidemenu_banner_val = isset($theme_settings['sidemenu_banner_img']) ? $theme_settings['sidemenu_banner_img']['setting_value'] : '';
                                        if (!empty($sidemenu_banner_val) && file_exists(__DIR__ . '/' . $sidemenu_banner_val)): 
                                        ?>
                                            <img src="<?= htmlspecialchars($sidemenu_banner_val) ?>" alt="Banner Preview" class="img-fluid" style="max-height: 100px; object-fit: contain;">
                                        <?php else: ?>
                                            <div class="text-center text-muted">
                                                <i class="bi bi-image fs-1 d-block mb-1"></i>
                                                <small class="small d-block">No Banner</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <label class="form-label fw-semibold small text-secondary">Upload Banner Image</label>
                                    <input type="file" class="form-control" name="sidemenu_banner_img" accept="image/*">
                                    <div class="form-text text-muted small mt-1">Image will be displayed at the bottom of the mobile sidemenu.</div>
                                    
                                    <?php if (!empty($sidemenu_banner_val)): ?>
                                        <div class="form-check form-switch mt-3">
                                            <input class="form-check-input" type="checkbox" id="remove_sidemenu_banner" name="remove_sidemenu_banner" value="1">
                                            <label class="form-check-label small fw-semibold text-danger" for="remove_sidemenu_banner">
                                                <i class="bi bi-trash3 me-1"></i>Remove current banner
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="row mt-3 pt-3 border-top">
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label fw-semibold small text-secondary">Banner Height</label>
                                            <input type="text" class="form-control form-control-sm" name="sidemenu_banner_height" value="<?= isset($theme_settings['sidemenu_banner_height']) ? htmlspecialchars($theme_settings['sidemenu_banner_height']['setting_value']) : 'auto' ?>" placeholder="e.g. 150px, auto">
                                            <small class="text-muted">CSS height (e.g., 150px or auto)</small>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <label class="form-label fw-semibold small text-secondary">Banner Width</label>
                                            <input type="text" class="form-control form-control-sm" name="sidemenu_banner_width" value="<?= isset($theme_settings['sidemenu_banner_width']) ? htmlspecialchars($theme_settings['sidemenu_banner_width']['setting_value']) : '100%' ?>" placeholder="e.g. 100%, 250px">
                                            <small class="text-muted">CSS width (e.g., 100%)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 border-top pt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-check-circle me-2"></i>Save All Settings
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Sync color input and text input
const colorSettings = <?= json_encode(array_keys($color_settings)) ?>;

colorSettings.forEach(key => {
    const colorInput = document.getElementById(key);
    const textInput = document.getElementById(key + '_text');
    
    if (colorInput && textInput) {
        // Sync color picker to text input
        colorInput.addEventListener('input', function() {
            textInput.value = this.value;
        });
        
        // Sync text input to color picker
        textInput.addEventListener('input', function() {
            if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                colorInput.value = this.value;
            }
        });
    }
});

// Section 8: Show/hide custom px input when "Custom" is selected
document.querySelectorAll('.section-width-select').forEach(function(sel) {
    function toggleCustom() {
        var wrapId = sel.getAttribute('data-custom-target') + '_wrap';
        var wrap = document.getElementById(wrapId);
        if (wrap) {
            wrap.style.display = sel.value === 'custom' ? '' : 'none';
        }
    }
    sel.addEventListener('change', toggleCustom);
    toggleCustom();
});

// Brand logo file upload preview
const logoInput = document.getElementById('brand_logo');
const logoPreview = document.getElementById('logoPreview');
const logoPreviewPlaceholder = document.getElementById('logoPreviewPlaceholder');
const removeLogoCheckbox = document.getElementById('remove_brand_logo');

if (logoInput) {
    logoInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                if (logoPreview) {
                    logoPreview.src = e.target.result;
                    logoPreview.classList.remove('d-none');
                }
                if (logoPreviewPlaceholder) {
                    logoPreviewPlaceholder.classList.add('d-none');
                }
                if (removeLogoCheckbox) {
                    removeLogoCheckbox.checked = false; // Uncheck remove if user uploads a new one
                }
            }
            reader.readAsDataURL(file);
        }
    });
}

if (removeLogoCheckbox) {
    removeLogoCheckbox.addEventListener('change', function() {
        if (this.checked) {
            // Show placeholder/revert look temporarily
            if (logoPreview) {
                logoPreview.classList.add('d-none');
            }
            if (logoPreviewPlaceholder) {
                logoPreviewPlaceholder.classList.remove('d-none');
            }
        } else {
            // Revert back if unchecked
            const file = logoInput ? logoInput.files[0] : null;
            if (file) {
                // Trigger change to re-read
                logoInput.dispatchEvent(new Event('change'));
            } else {
                <?php if (!empty($brand_logo_val)): ?>
                    if (logoPreview) {
                        logoPreview.src = "<?= htmlspecialchars($brand_logo_val) ?>";
                        logoPreview.classList.remove('d-none');
                    }
                    if (logoPreviewPlaceholder) {
                        logoPreviewPlaceholder.classList.add('d-none');
                    }
                <?php else: ?>
                    if (logoPreview) {
                        logoPreview.classList.add('d-none');
                    }
                    if (logoPreviewPlaceholder) {
                        logoPreviewPlaceholder.classList.remove('d-none');
                    }
                <?php endif; ?>
            }
        }
    });
}

// Form submission handling for multiple buttons
document.getElementById('themeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Select all submit buttons associated with this form
    const submitBtns = document.querySelectorAll('button[type="submit"][form="themeForm"], #themeForm button[type="submit"]');
    const originalBtnStates = [];
    
    submitBtns.forEach(btn => {
        originalBtnStates.push({ btn: btn, html: btn.innerHTML });
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        btn.disabled = true;
    });

    fetch('theme_settings_save.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Theme settings saved successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
            originalBtnStates.forEach(state => {
                state.btn.innerHTML = state.html;
                state.btn.disabled = false;
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving.');
        originalBtnStates.forEach(state => {
            state.btn.innerHTML = state.html;
            state.btn.disabled = false;
        });
    });
});

// Interactive Search and Sticky Position Logic
document.addEventListener('DOMContentLoaded', function() {
    const mainHeader = document.querySelector('main > header');
    const toolbar = document.querySelector('.theme-settings-toolbar');
    
    // Adjust sticky toolbar top offset dynamically based on main navigation header height
    function adjustStickyToolbarPosition() {
        if (mainHeader && toolbar) {
            const headerHeight = mainHeader.offsetHeight;
            toolbar.style.top = `${headerHeight}px`;
        }
    }
    adjustStickyToolbarPosition();
    window.addEventListener('resize', adjustStickyToolbarPosition);
    setTimeout(adjustStickyToolbarPosition, 100); // Fail-safe check
    
    // Search indexing setup
    const themeForm = document.getElementById('themeForm');
    const searchInput = document.getElementById('themeSettingSearch');
    const suggestionsBox = document.getElementById('searchSuggestions');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    
    let searchIndex = [];
    
    if (themeForm && searchInput) {
        // 1. Index h5 elements (sections)
        themeForm.querySelectorAll('h5').forEach((h5, idx) => {
            const text = h5.textContent.trim();
            const cleanText = text.replace(/^[0-9.]+\s*/, '').trim(); 
            
            let containerId = h5.id || `search-section-${idx}`;
            h5.id = containerId;
            
            searchIndex.push({
                type: 'section',
                title: cleanText,
                sectionName: 'Main Section',
                elementId: containerId,
                keywords: cleanText.toLowerCase()
            });
        });
        
        // 2. Index labels
        themeForm.querySelectorAll('label').forEach((label, idx) => {
            const text = label.textContent.trim();
            if (text.length < 3) return; // skip too short labels
            
            // Find parent section h5 to show section context
            let parentSection = 'Settings';
            let parentElement = label.parentElement;
            while (parentElement && parentElement !== themeForm) {
                const sectionHeader = parentElement.querySelector('h5');
                if (sectionHeader) {
                    parentSection = sectionHeader.textContent.trim().replace(/^[0-9.]+\s*/, '').trim();
                    break;
                }
                parentElement = parentElement.parentElement;
            }
            
            // Find appropriate container to highlight (closest form group or card)
            const container = label.closest('.card, .form-group, .mb-3, .mb-4, .mb-2, tr') || label;
            let containerId = container.id || `search-setting-${idx}`;
            container.id = containerId;
            
            searchIndex.push({
                type: 'setting',
                title: text,
                sectionName: parentSection,
                elementId: containerId,
                keywords: text.toLowerCase()
            });
        });
        
        // 3. Render matching suggestions
        function renderSuggestions(query) {
            if (!query) {
                suggestionsBox.style.display = 'none';
                clearSearchBtn.style.display = 'none';
                return;
            }
            
            clearSearchBtn.style.display = 'block';
            const cleanQuery = query.toLowerCase().trim();
            
            // Filter matches
            const matches = searchIndex.filter(item => item.keywords.includes(cleanQuery));
            
            if (matches.length === 0) {
                suggestionsBox.innerHTML = `
                <div class="dropdown-item text-muted py-2 text-center small">
                    <i class="bi bi-exclamation-circle me-1"></i>No matching settings found
                </div>`;
                suggestionsBox.style.display = 'block';
                return;
            }
            
            // Limit to max 8 results for clean UI
            const slicedMatches = matches.slice(0, 8);
            
            let html = '';
            slicedMatches.forEach(item => {
                const icon = item.type === 'section' ? 'bi-folder2-open text-primary' : 'bi-sliders text-secondary';
                
                // Highlight matching text in the title
                const index = item.title.toLowerCase().indexOf(cleanQuery);
                let displayTitle = item.title;
                if (index >= 0) {
                    const original = item.title.substring(index, index + query.length);
                    displayTitle = item.title.replace(new RegExp(original, 'i'), `<mark class="p-0 bg-warning bg-opacity-50 fw-bold">${original}</mark>`);
                }
                
                html += `
                <a href="javascript:void(0);" class="dropdown-item py-2 border-bottom border-light-subtle d-flex justify-content-between align-items-center suggestion-item" data-element-id="${item.elementId}">
                    <div>
                        <span class="fw-semibold text-dark d-block small"><i class="bi ${icon} me-2"></i>${displayTitle}</span>
                        <small class="text-muted text-uppercase" style="font-size: 0.7rem;">${item.sectionName}</small>
                    </div>
                    <i class="bi bi-chevron-right text-muted" style="font-size: 0.75rem;"></i>
                </a>`;
            });
            
            suggestionsBox.innerHTML = html;
            suggestionsBox.style.display = 'block';
            
            // Add click handlers to suggestions
            suggestionsBox.querySelectorAll('.suggestion-item').forEach(el => {
                el.addEventListener('click', function() {
                    const elementId = this.getAttribute('data-element-id');
                    navigateToSetting(elementId);
                    suggestionsBox.style.display = 'none';
                    searchInput.value = this.querySelector('.fw-semibold').textContent.trim();
                });
            });
        }
        
        // Navigation / scroll handler
        function navigateToSetting(elementId) {
            const target = document.getElementById(elementId);
            if (target) {
                // Scroll to view
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Clear any existing highlights
                document.querySelectorAll('.setting-highlight-flash').forEach(el => {
                    el.classList.remove('setting-highlight-flash');
                });
                
                // Add highlight flash class
                setTimeout(() => {
                    target.classList.add('setting-highlight-flash');
                }, 300);
                
                // Remove highlight class after animation finishes
                setTimeout(() => {
                    target.classList.remove('setting-highlight-flash');
                }, 2500);
            }
        }
        
        // Listeners for search actions
        searchInput.addEventListener('input', function() {
            renderSuggestions(this.value);
        });
        
        searchInput.addEventListener('focus', function() {
            if (this.value) {
                renderSuggestions(this.value);
            }
        });
        
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = this.value.toLowerCase().trim();
                if (query) {
                    const matches = searchIndex.filter(item => item.keywords.includes(query));
                    if (matches.length > 0) {
                        navigateToSetting(matches[0].elementId);
                        suggestionsBox.style.display = 'none';
                        this.value = matches[0].title;
                    }
                }
            }
        });
        
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            renderSuggestions('');
            searchInput.focus();
        });
        
        // Click outside closes the dropdown
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.style.display = 'none';
            }
        });
    }
});
</script>

<?php require "templates/footer.php"; ?>
