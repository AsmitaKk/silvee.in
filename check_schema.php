<?php
require_once "config.php";

echo "<h2>Database Schema Check</h2>";

// Check products table columns
echo "<h3>Products Table Columns:</h3>";
try {
    $result = $pdo->query("SHOW COLUMNS FROM products");
    echo "<pre>";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check product_designs table columns
echo "<h3>Product_Designs Table Columns:</h3>";
try {
    $result = $pdo->query("SHOW COLUMNS FROM product_designs");
    echo "<pre>";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Check categories table columns
echo "<h3>Categories Table Columns:</h3>";
try {
    $result = $pdo->query("SHOW COLUMNS FROM categories");
    echo "<pre>";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "</pre>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Try to run the problematic query
echo "<h3>Test Query (Line 59 equivalent):</h3>";
try {
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
    echo "Query executed successfully!<br>";
    $rows = $stmt->fetchAll();
    echo "Results: " . count($rows) . " rows returned";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
