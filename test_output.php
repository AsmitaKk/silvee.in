<?php
require_once "config.php";
try {
    $stmt = $pdo->query("
        SELECT d.*, p.name as product_name, p.per_piece_weight as weight, p.category_id 
        FROM product_designs d 
        JOIN products p ON d.product_id = p.id 
        WHERE d.stock_qty > 0
        ORDER BY d.id DESC 
        LIMIT 8
    ");
    $new_arrivals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "SUCCESS: Found " . count($new_arrivals) . " designs!\n";
    foreach ($new_arrivals as $d) {
        echo "SKU: " . $d['sku'] . " | Design Name: " . $d['design_name'] . " | Product Name: " . $d['product_name'] . " | Weight: " . $d['weight'] . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
