<?php
require 'config.php';

// Get all products
$stmt = $pdo->query("SELECT id FROM products");
$products = $stmt->fetchAll(PDO::FETCH_COLUMN);

$fixed_count = 0;

foreach ($products as $pid) {
    // Get total stock from designs
    $stmt1 = $pdo->prepare("SELECT SUM(stock_qty) FROM product_designs WHERE product_id = ?");
    $stmt1->execute([$pid]);
    $design_stock = (int)$stmt1->fetchColumn();

    // Get net stock from movements
    $stmt2 = $pdo->prepare("SELECT SUM(CASE WHEN movement_type='IN' THEN quantity ELSE -quantity END) FROM stock_movements WHERE product_id = ?");
    $stmt2->execute([$pid]);
    $mov_stock = (int)$stmt2->fetchColumn();

    $diff = $design_stock - $mov_stock;

    if ($diff != 0) {
        // Needs adjustment
        $type = $diff > 0 ? 'IN' : 'OUT';
        $adj_qty = abs($diff);
        
        // Find the first design id to attach this movement to (doesn't matter much for bulk report)
        $stmt3 = $pdo->prepare("SELECT id FROM product_designs WHERE product_id = ? LIMIT 1");
        $stmt3->execute([$pid]);
        $did = $stmt3->fetchColumn();

        // Insert corrective movement way back in time so it acts as "Opening Stock"
        $insert = $pdo->prepare("INSERT INTO stock_movements (product_id, design_id, movement_type, quantity, movement_date, vendor_name, note) VALUES (?, ?, ?, ?, '2020-01-01', 'System Adjustment', 'Auto-sync discrepancy')");
        $insert->execute([$pid, $did, $type, $adj_qty]);
        
        $fixed_count++;
    }
}

echo "Fixed $fixed_count products.";
