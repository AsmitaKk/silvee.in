<?php
require_once "config.php";

// Create theme_settings table if it doesn't exist
$sql = "
CREATE TABLE IF NOT EXISTS theme_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
";

try {
    $pdo->exec($sql);
    
    // Check if we need to modify the column from VARCHAR(255) to TEXT
    $pdo->exec("ALTER TABLE theme_settings MODIFY COLUMN setting_value TEXT NOT NULL");
    
    // Insert defaults if not exist
    $defaults = [
        'banner_bg_color' => ['#fdfbfb', 'Banner background color'],
        'banner_accent_color' => ['#cda434', 'Banner accent/text color'],
        'hero_gradient_start' => ['#fdfbfb', 'Hero gradient start color'],
        'hero_gradient_end' => ['#ebedee', 'Hero gradient end color'],
        'primary_color' => ['#0c2d27', 'Primary button/theme color'], // Adjusted to forest green
        'secondary_color' => ['#6c757d', 'Secondary text color'],
        'hero_banner_image' => ['', 'Path to custom hero/banner image'],
        'banner_enabled' => ['1', 'Show hero banner (1) or hide (0)'],
        'show_categories' => ['1', 'Show category sections on homepage (1) or hide (0)'],
        'new_arrivals_category_id' => ['', 'Linked category ID for New Arrivals'],
        'promo_height_desktop' => ['306', 'Custom Height for Promo Banners in px'],
        'promo_width_desktop' => ['364', 'Custom Max Width for Promo Banners in px'],
        'brand_logo' => ['', 'Path to custom brand logo image'],
        'logo_height_desktop' => ['50', 'Custom Height for Brand Logo on Desktop in px'],
        'logo_height_mobile' => ['38', 'Custom Height for Brand Logo on Mobile in px'],
        'heading_font' => ['Playfair Display', 'Google font family for Headings'],
        'paragraph_font' => ['Inter', 'Google font family for Paragraphs/Body'],
        'header_font' => ['Playfair Display', 'Google font family for Header/Navbar'],
        'footer_font' => ['Inter', 'Google font family for Footer'],
        'category_font' => ['Playfair Display', 'Google font family for Category titles'],
        'subsection_font' => ['Playfair Display', 'Google font family for Subsection titles'],
        'section_heading_font' => ['Outfit', 'Google font family for Section headings'],
        'heading_font_size' => ['28', 'Base Heading font size in px'],
        'paragraph_font_size' => ['16', 'Base Body/Paragraph font size in px'],
        'category_font_size' => ['15', 'Category title font size in px'],
        'subsection_font_size' => ['18', 'Subsection title font size in px'],
        'section_heading_font_size' => ['18', 'Section heading font size in px'],
        'header_font_size' => ['15', 'Header/Navbar font size in px'],
        'footer_font_size' => ['14', 'Footer font size in px'],
        'heading_font_style' => ['', 'Style type for headings'],
        'paragraph_font_style' => ['', 'Style type for body/paragraphs'],
        'category_font_style' => ['', 'Style type for category titles'],
        'subsection_font_style' => ['', 'Style type for subsection titles'],
        'section_heading_font_style' => ['', 'Style type for section headings'],
        'header_font_style' => ['', 'Style type for header/navbar'],
        'footer_font_style' => ['', 'Style type for footer'],
        'catalog_categories_visibility' => ['{}', 'JSON map of parent category IDs to visibility status (show/hide) for Our Wholesale Catalog']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value, description) VALUES (:key, :val, :desc) ON DUPLICATE KEY UPDATE description = VALUES(description)");
    foreach ($defaults as $key => $info) {
        $stmt->execute([':key' => $key, ':val' => $info[0], ':desc' => $info[1]]);
    }

    // Add labour_charge and flat_rate to categories table if they don't exist
    try {
        $pdo->exec("ALTER TABLE categories ADD COLUMN labour_charge DECIMAL(10,2) DEFAULT 0.00");
    } catch (Exception $e) {
        // column might already exist, ignore
    }
    try {
        $pdo->exec("ALTER TABLE categories ADD COLUMN flat_rate DECIMAL(10,2) DEFAULT 0.00");
    } catch (Exception $e) {
        // column might already exist, ignore
    }
    
    echo "Theme settings table created and updated successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

