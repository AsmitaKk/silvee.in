<?php
require 'config.php';
$hash = password_hash('123456', PASSWORD_DEFAULT);
$pdo->prepare("UPDATE customers SET password_hash = ? WHERE username = 'silveepos'")->execute([$hash]);
$pdo->prepare("UPDATE users SET password_hash = ? WHERE name = 'silveepos'")->execute([$hash]);

echo "Updated customers and users table with hash: " . $hash . "\n";
