<?php
require_once "config.php";
require_once "auth_check.php";
require_once "role_management.php";

$user_role = get_user_role($pdo, $_SESSION['user_id']);

header('Content-Type: application/json');

// Only admins can update
if ($user_role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$color_settings = [
    'banner_bg_color',
    'banner_accent_color',
    'hero_gradient_start',
    'hero_gradient_end',
    'primary_color',
    'secondary_color',
    'sidemenu_bg_color',
    'sidemenu_font_color'
];

$success = true;
$errors = [];

try {
    $pdo->beginTransaction();
    
    // Save Color Settings
    foreach ($color_settings as $key) {
        $val = isset($_POST[$key]) ? trim($_POST[$key]) : '';
        if (empty($val) && isset($_POST[$key . '_text'])) {
            $val = trim($_POST[$key . '_text']);
        }
        
        if (!empty($val)) {
            // Validate hex color
            if (!preg_match('/^#[0-9A-F]{6}$/i', $val)) {
                $errors[] = "$key must be a valid hex color (format: #FFFFFF)";
                continue;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO theme_settings (setting_key, setting_value, description) 
                VALUES (:key, :value, :desc)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([
                ':key' => $key,
                ':value' => $val,
                ':desc' => 'Theme color setting'
            ]);
        }
    }

    // Save Visibility Options
    $banner_enabled = isset($_POST['banner_enabled']) && $_POST['banner_enabled'] == '1' ? '1' : '0';
    $stmt = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES ('banner_enabled', :val, 'Banner visibility') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([':val' => $banner_enabled]);

    $banner_mobile_enabled = isset($_POST['banner_mobile_enabled']) && $_POST['banner_mobile_enabled'] == '1' ? '1' : '0';
    $stmt = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES ('banner_mobile_enabled', :val, 'Mobile Banner visibility') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([':val' => $banner_mobile_enabled]);

    $show_categories = isset($_POST['show_categories']) && $_POST['show_categories'] == '1' ? '1' : '0';
    $stmt = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES ('show_categories', :val, 'Show categories on homepage') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([':val' => $show_categories]);

    // Save Global Store Info, Topbar, Fonts, and Custom Titles
    $generic_text_settings = [
        'whatsapp_number' => 'Global WhatsApp Number',
        'topbar_text_1' => 'Topbar text highlight 1',
        'topbar_text_2' => 'Topbar text highlight 2',
        'topbar_text_3' => 'Topbar text highlight 3',
        'heading_font' => 'Google font family for Headings',
        'paragraph_font' => 'Google font family for Paragraphs/Body',
        'header_font' => 'Google font family for Header/Navbar',
        'footer_font' => 'Google font family for Footer',
        'category_section_title' => 'Custom title for Shop by Category grid',
        'new_arrivals_section_title' => 'Custom title for New Arrivals slider',
        'catalog_section_title' => 'Custom title for Wholesale Catalog nested grid',
        'hero_height_desktop' => 'Custom Height for Desktop Hero Slider in px',
        'hero_width_desktop' => 'Custom Width for Desktop Hero Slider (100%, 1400px, 1200px)',
        'hero_height_mobile' => 'Custom Height for Mobile Hero Slider in px',
        'hero_width_mobile' => 'Custom Width for Mobile Hero Slider (100%, 1400px, 1200px)',
        'category_section_width' => 'Custom Width for Category Section (100%, 1400px, 1200px)',
        'category_card_height' => 'Custom Height for Category Cards in px',
        'category_card_height_mobile' => 'Custom Height for Category Cards on mobile in px',
        'category_row_count' => 'Number of categories in a row on desktop (3, 4, 5, 6)',
        'category_row_count_mobile' => 'Number of categories in a row on mobile (1, 2, 3, 4, 5)',
        'new_arrivals_category_id' => 'Linked category ID for New Arrivals',
        'promo_height_desktop' => 'Custom Height for Promo Banners in px',
        'promo_width_desktop' => 'Custom Max Width for Promo Banners in px',
        'logo_height_desktop' => 'Custom Height for Brand Logo on Desktop in px',
        'logo_height_mobile' => 'Custom Height for Brand Logo on Mobile in px',
        'category_font' => 'Google font family for Category names/titles',
        'subsection_font' => 'Google font family for Subsection titles',
        'section_heading_font' => 'Google font family for Section headings',
        'heading_font_size' => 'Base Heading font size in px',
        'heading_font_size_mobile' => 'Heading font size on mobile in px',
        'paragraph_font_size' => 'Base Body/Paragraph font size in px',
        'paragraph_font_size_mobile' => 'Body/Paragraph font size on mobile in px',
        'category_font_size' => 'Category title font size in px',
        'category_font_size_mobile' => 'Category title font size on mobile in px',
        'subsection_font_size' => 'Subsection title font size in px',
        'subsection_font_size_mobile' => 'Subsection title font size on mobile in px',
        'section_heading_font_size' => 'Section heading font size in px',
        'section_heading_font_size_mobile' => 'Section heading font size on mobile in px',
        'header_font_size' => 'Header/Navbar font size in px',
        'header_font_size_mobile' => 'Header/Navbar font size on mobile in px',
        'footer_font_size' => 'Footer font size in px',
        'footer_font_size_mobile' => 'Footer font size on mobile in px',
        'heading_font_style' => 'Style type for headings',
        'paragraph_font_style' => 'Style type for body/paragraphs',
        'category_font_style' => 'Style type for category titles',
        'subsection_font_style' => 'Style type for subsection titles',
        'section_heading_font_style' => 'Style type for section headings',
        'header_font_style' => 'Style type for header/navbar',
        'footer_font_style' => 'Style type for footer',
        'category_title_font_size_mobile' => 'Font size for category card title in Shop By Category section on mobile in px',
        'shop_cat_title_font' => 'Google font family for Shop By Category section card titles',
        'shop_cat_title_font_size' => 'Font size for Shop By Category card titles on desktop in px',
        'shop_cat_title_font_size_mobile' => 'Font size for Shop By Category card titles on mobile in px',
        'sidemenu_font' => 'Google font family for Mobile Sidemenu',
        'sidemenu_font_size' => 'Sidemenu font size in px',
        'sidemenu_heading_font_size' => 'Sidemenu heading font size in px',
        'sidemenu_item_spacing' => 'Sidemenu menu item spacing in px',
        'sidemenu_heading_font_style' => 'Style type for sidemenu headings',
        'sidemenu_menu_font_style' => 'Style type for sidemenu menu items',
        'sidemenu_banner_height' => 'Height of sidemenu banner (e.g., 150px, auto)',
        'sidemenu_banner_width' => 'Width of sidemenu banner (e.g., 100%, 200px)'
    ];

    foreach ($generic_text_settings as $key => $desc) {
        $val = isset($_POST[$key]) ? trim($_POST[$key]) : '';
        $stmt = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES (:key, :val, :desc) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([':key' => $key, ':val' => $val, ':desc' => $desc]);
    }

    // Save Index Page Section Dimensions (Section 8)
    $index_section_keys = ['hero', 'category', 'new_arrivals', 'promo', 'why_us', 'catalog'];
    $device_keys = ['desktop', 'tablet', 'mobile'];
    foreach ($index_section_keys as $sec) {
        foreach ($device_keys as $dev) {
            // Width
            $w_key = "section_{$sec}_width_{$dev}";
            $w_val = isset($_POST[$w_key]) ? trim($_POST[$w_key]) : '100%';
            if ($w_val === 'custom') {
                // Use the custom px value
                $custom_px = isset($_POST["{$w_key}_custom"]) ? intval($_POST["{$w_key}_custom"]) : 0;
                $w_val = ($custom_px > 0) ? $custom_px . 'px' : '100%';
            }
            $stmt = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES (:key, :val, :desc) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([':key' => $w_key, ':val' => $w_val, ':desc' => "Width for {$sec} section on {$dev}"]);

            // Height (only for sections that have it; catalog has no height control but we save anyway for safety)
            $h_key = "section_{$sec}_height_{$dev}";
            $h_val = isset($_POST[$h_key]) ? trim($_POST[$h_key]) : '';
            $stmt = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES (:key, :val, :desc) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            $stmt->execute([':key' => $h_key, ':val' => $h_val, ':desc' => "Height for {$sec} section on {$dev} in px"]);
        }
    }

    // Save Selected Homepage Categories Checklist
    $homepage_selected_categories = isset($_POST['homepage_selected_categories']) ? $_POST['homepage_selected_categories'] : [];
    if (!is_array($homepage_selected_categories)) {
        $homepage_selected_categories = [];
    }
    $categories_json = json_encode($homepage_selected_categories);
    $stmt = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES ('homepage_selected_categories', :val, 'JSON list of selected category IDs for homepage') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([':val' => $categories_json]);

    // Save Selected Homepage Categories Checklist (Mobile)
    $homepage_selected_categories_mobile = isset($_POST['homepage_selected_categories_mobile']) ? $_POST['homepage_selected_categories_mobile'] : [];
    if (!is_array($homepage_selected_categories_mobile)) {
        $homepage_selected_categories_mobile = [];
    }
    $categories_mobile_json = json_encode($homepage_selected_categories_mobile);
    $stmt = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES ('homepage_selected_categories_mobile', :val, 'JSON list of selected category IDs for mobile homepage') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([':val' => $categories_mobile_json]);

    // Save Catalog Categories Visibility
    $catalog_categories_visibility = isset($_POST['catalog_categories_visibility']) ? $_POST['catalog_categories_visibility'] : [];
    if (!is_array($catalog_categories_visibility)) {
        $catalog_categories_visibility = [];
    }
    $visibility_json = json_encode($catalog_categories_visibility);
    $stmt = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES ('catalog_categories_visibility', :val, 'JSON map of parent category IDs to visibility status (show/hide) for Our Wholesale Catalog') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([':val' => $visibility_json]);

    // Ensure uploads directories exist
    $uploadDir = __DIR__ . '/uploads/themes';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Save Hero Carousel Banners (Max 3)
    $carousel_data = [];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    
    for ($i = 0; $i < 3; $i++) {
        $image_path = isset($_POST["hero_slide_existing_img_$i"]) ? $_POST["hero_slide_existing_img_$i"] : '';
        
        // Handle file upload
        if (isset($_FILES["hero_slide_img_$i"]) && $_FILES["hero_slide_img_$i"]['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES["hero_slide_img_$i"]['tmp_name'];
            $name = basename($_FILES["hero_slide_img_$i"]['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed_extensions)) {
                $newName = 'slide_' . $i . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . '/' . $newName;
                if (move_uploaded_file($tmp, $dest)) {
                    $image_path = 'uploads/themes/' . $newName;
                } else {
                    $errors[] = "Failed to upload image for Hero Slide " . ($i + 1);
                }
            } else {
                $errors[] = "Invalid image type for Hero Slide " . ($i + 1) . ". Allowed: " . implode(', ', $allowed_extensions);
            }
        }
        
        $carousel_data[] = [
            'badge_text' => isset($_POST["hero_slide_badge_$i"]) ? trim($_POST["hero_slide_badge_$i"]) : '',
            'title' => isset($_POST["hero_slide_title_$i"]) ? trim($_POST["hero_slide_title_$i"]) : '',
            'subtitle' => isset($_POST["hero_slide_subtitle_$i"]) ? trim($_POST["hero_slide_subtitle_$i"]) : '',
            'button_text' => isset($_POST["hero_slide_btn_text_$i"]) ? trim($_POST["hero_slide_btn_text_$i"]) : '',
            'button_link' => isset($_POST["hero_slide_btn_link_$i"]) ? trim($_POST["hero_slide_btn_link_$i"]) : '',
            'image_path' => $image_path,
            'is_visible' => isset($_POST["hero_slide_visible_$i"]) && $_POST["hero_slide_visible_$i"] == '1' ? 1 : 0
        ];
    }
    
    $carousel_json = json_encode($carousel_data);
    $stmt = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES ('hero_carousel_data', :val, 'JSON configuration of hero carousel slides') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([':val' => $carousel_json]);

    // Save Hero Carousel Mobile Banners (Max 3)
    $carousel_mobile_data = [];
    for ($i = 0; $i < 3; $i++) {
        $image_path = isset($_POST["hero_slide_mobile_existing_img_$i"]) ? $_POST["hero_slide_mobile_existing_img_$i"] : '';
        
        // Handle file upload
        if (isset($_FILES["hero_slide_mobile_img_$i"]) && $_FILES["hero_slide_mobile_img_$i"]['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES["hero_slide_mobile_img_$i"]['tmp_name'];
            $name = basename($_FILES["hero_slide_mobile_img_$i"]['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed_extensions)) {
                $newName = 'slide_mobile_' . $i . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . '/' . $newName;
                if (move_uploaded_file($tmp, $dest)) {
                    $image_path = 'uploads/themes/' . $newName;
                } else {
                    $errors[] = "Failed to upload image for Mobile Hero Slide " . ($i + 1);
                }
            } else {
                $errors[] = "Invalid image type for Mobile Hero Slide " . ($i + 1) . ". Allowed: " . implode(', ', $allowed_extensions);
            }
        }
        
        $carousel_mobile_data[] = [
            'badge_text' => isset($_POST["hero_slide_mobile_badge_$i"]) ? trim($_POST["hero_slide_mobile_badge_$i"]) : '',
            'title' => isset($_POST["hero_slide_mobile_title_$i"]) ? trim($_POST["hero_slide_mobile_title_$i"]) : '',
            'subtitle' => isset($_POST["hero_slide_mobile_subtitle_$i"]) ? trim($_POST["hero_slide_mobile_subtitle_$i"]) : '',
            'button_text' => isset($_POST["hero_slide_mobile_btn_text_$i"]) ? trim($_POST["hero_slide_mobile_btn_text_$i"]) : '',
            'button_link' => isset($_POST["hero_slide_mobile_btn_link_$i"]) ? trim($_POST["hero_slide_mobile_btn_link_$i"]) : '',
            'image_path' => $image_path,
            'is_visible' => isset($_POST["hero_slide_mobile_visible_$i"]) && $_POST["hero_slide_mobile_visible_$i"] == '1' ? 1 : 0
        ];
    }
    
    $carousel_mobile_json = json_encode($carousel_mobile_data);
    $stmt = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES ('hero_carousel_mobile_data', :val, 'JSON configuration of mobile hero carousel slides') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([':val' => $carousel_mobile_json]);

    // Save Promotional Banners (Max 4)
    $promo_data = [];
    for ($i = 0; $i < 4; $i++) {
        $image_path = isset($_POST["promo_banner_existing_img_$i"]) ? $_POST["promo_banner_existing_img_$i"] : '';
        
        // Handle file upload
        if (isset($_FILES["promo_banner_img_$i"]) && $_FILES["promo_banner_img_$i"]['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES["promo_banner_img_$i"]['tmp_name'];
            $name = basename($_FILES["promo_banner_img_$i"]['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed_extensions)) {
                $newName = 'promo_' . $i . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . '/' . $newName;
                if (move_uploaded_file($tmp, $dest)) {
                    $image_path = 'uploads/themes/' . $newName;
                } else {
                    $errors[] = "Failed to upload image for Promo Banner " . ($i + 1);
                }
            } else {
                $errors[] = "Invalid image type for Promo Banner " . ($i + 1) . ". Allowed: " . implode(', ', $allowed_extensions);
            }
        }
        
        $promo_data[] = [
            'title' => isset($_POST["promo_banner_title_$i"]) ? trim($_POST["promo_banner_title_$i"]) : '',
            'subtitle' => isset($_POST["promo_banner_subtitle_$i"]) ? trim($_POST["promo_banner_subtitle_$i"]) : '',
            'button_text' => isset($_POST["promo_banner_btn_text_$i"]) ? trim($_POST["promo_banner_btn_text_$i"]) : '',
            'button_link' => isset($_POST["promo_banner_btn_link_$i"]) ? trim($_POST["promo_banner_btn_link_$i"]) : '',
            'image_path' => $image_path
        ];
    }
    
    $promo_json = json_encode($promo_data);
    $stmt = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES ('promo_banners_data', :val, 'JSON configuration of the 4 promotional banners') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([':val' => $promo_json]);

    // Save Brand Logo (if uploaded or requested to remove)
    if (isset($_POST['remove_brand_logo']) && $_POST['remove_brand_logo'] == '1') {
        // Fetch existing logo path to delete file
        $stmt_logo = $pdo->query("SELECT setting_value FROM theme_settings WHERE setting_key = 'brand_logo'");
        if ($row_logo = $stmt_logo->fetch()) {
            $old_path = $row_logo['setting_value'];
            if (!empty($old_path) && file_exists(__DIR__ . '/' . $old_path)) {
                @unlink(__DIR__ . '/' . $old_path);
            }
        }
        $stmt_update = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES ('brand_logo', '', 'Path to custom brand logo image') ON DUPLICATE KEY UPDATE setting_value = ''");
        $stmt_update->execute();
    } elseif (isset($_FILES['brand_logo']) && $_FILES['brand_logo']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['brand_logo']['tmp_name'];
        $name = basename($_FILES['brand_logo']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed_extensions)) {
            $newName = 'brand_logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $uploadDir . '/' . $newName;
            if (move_uploaded_file($tmp, $dest)) {
                // Delete old file
                $stmt_logo = $pdo->query("SELECT setting_value FROM theme_settings WHERE setting_key = 'brand_logo'");
                if ($row_logo = $stmt_logo->fetch()) {
                    $old_path = $row_logo['setting_value'];
                    if (!empty($old_path) && file_exists(__DIR__ . '/' . $old_path)) {
                        @unlink(__DIR__ . '/' . $old_path);
                    }
                }
                
                $brand_logo_path = 'uploads/themes/' . $newName;
                $stmt_update = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES ('brand_logo', :val, 'Path to custom brand logo image') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt_update->execute([':val' => $brand_logo_path]);
            } else {
                $errors[] = "Failed to save uploaded brand logo image.";
            }
        } else {
            $errors[] = "Invalid image type for brand logo. Allowed: " . implode(', ', $allowed_extensions);
        }
    }

    // Save Sidemenu Banner (if uploaded or requested to remove)
    if (isset($_POST['remove_sidemenu_banner']) && $_POST['remove_sidemenu_banner'] == '1') {
        // Fetch existing banner path to delete file
        $stmt_logo = $pdo->query("SELECT setting_value FROM theme_settings WHERE setting_key = 'sidemenu_banner_img'");
        if ($row_logo = $stmt_logo->fetch()) {
            $old_path = $row_logo['setting_value'];
            if (!empty($old_path) && file_exists(__DIR__ . '/' . $old_path)) {
                @unlink(__DIR__ . '/' . $old_path);
            }
        }
        $stmt_update = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES ('sidemenu_banner_img', '', 'Path to sidemenu banner image') ON DUPLICATE KEY UPDATE setting_value = ''");
        $stmt_update->execute();
    } elseif (isset($_FILES['sidemenu_banner_img']) && $_FILES['sidemenu_banner_img']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['sidemenu_banner_img']['tmp_name'];
        $name = basename($_FILES['sidemenu_banner_img']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed_extensions)) {
            $newName = 'sidemenu_banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $uploadDir . '/' . $newName;
            if (move_uploaded_file($tmp, $dest)) {
                // Delete old file
                $stmt_logo = $pdo->query("SELECT setting_value FROM theme_settings WHERE setting_key = 'sidemenu_banner_img'");
                if ($row_logo = $stmt_logo->fetch()) {
                    $old_path = $row_logo['setting_value'];
                    if (!empty($old_path) && file_exists(__DIR__ . '/' . $old_path)) {
                        @unlink(__DIR__ . '/' . $old_path);
                    }
                }
                
                $banner_logo_path = 'uploads/themes/' . $newName;
                $stmt_update = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES ('sidemenu_banner_img', :val, 'Path to sidemenu banner image') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt_update->execute([':val' => $banner_logo_path]);
            } else {
                $errors[] = "Failed to save uploaded sidemenu banner image.";
            }
        } else {
            $errors[] = "Invalid image type for sidemenu banner. Allowed: " . implode(', ', $allowed_extensions);
        }
    }
    
    if (count($errors) > 0) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => implode(', ', $errors)
        ]);
    } else {
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Theme settings saved successfully'
        ]);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
