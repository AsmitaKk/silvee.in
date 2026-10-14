<?php
require 'config.php';
try {
    $pdo->exec("ALTER TABLE b2b_invoice_items MODIFY pkt_weight VARCHAR(500) DEFAULT NULL");
    echo "Success";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
