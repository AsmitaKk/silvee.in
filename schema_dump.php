<?php
require 'config.php';
$stmt = $pdo->query("SHOW CREATE TABLE products");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

$stmt = $pdo->query("SHOW CREATE TABLE product_designs");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

$stmt = $pdo->query("SHOW CREATE TABLE b2b_invoice_items");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
