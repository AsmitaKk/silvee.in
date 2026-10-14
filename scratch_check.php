<?php
require_once "config.php";
try {
    $pdo->exec("ALTER TABLE product_designs MODIFY COLUMN image_path TEXT");
    echo "SUCCESS: altered column type to TEXT!\n";
} catch(Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
