<?php
require_once "config.php";

$stmt = $pdo->query("DESCRIBE customers");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($columns);

