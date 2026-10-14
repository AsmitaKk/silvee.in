<?php
require 'config.php';
$stmt = $pdo->query("SELECT id, name, code, parent_id FROM categories");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
