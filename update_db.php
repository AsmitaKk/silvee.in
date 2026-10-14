<?php
require_once "config.php";

try {
    $pdo->exec("ALTER TABLE b2b_invoices ADD COLUMN parcel_charges decimal(10,2) NOT NULL DEFAULT 0.00 AFTER subtotal");
    echo "Added parcel_charges column to b2b_invoices successfully.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column parcel_charges already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
