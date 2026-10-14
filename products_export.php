<?php
require_once "auth_check.php";
require_once "role_management.php";

// ✅ Export is read-only, so view permission is enough
require_permission($pdo, 'products', 'view');

// Tell browser this is a CSV file download
$filename = "products_inventory_" . date('Ymd_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, [
    'Category',
    'Sub-category',
    'Product Name',
    'SKU',
    'Quantity',
    'Min Stock Level',
    'Metal Type',
    'Purity'
]);

// Fetch products with category info
$stmt = $pdo->query("
    SELECT p.*
    FROM products p
    ORDER BY p.name
");
$products = $stmt->fetchAll();

foreach ($products as $p) {
    $path = get_category_path($pdo, $p['category_id']); // e.g. "Rings > Gold Rings"
    $parts = ($path !== '-' && $path !== '') ? explode(' > ', $path) : [];
    $category    = $parts[0] ?? '';
    $subcategory = $parts[1] ?? '';

    fputcsv($output, [
        $category,
        $subcategory,
        $p['name'],
        $p['sku'],
        $p['total_stock_qty'],
        $p['min_stock_level'],
        $p['metal_type'],
        $p['purity']
    ]);
}

fclose($output);
exit;
