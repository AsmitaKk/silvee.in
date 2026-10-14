<?php
require 'config.php';
$stmt = $pdo->query("SHOW CREATE TABLE categories");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
