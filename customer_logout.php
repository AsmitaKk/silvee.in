<?php
session_start();
// Keep the cart but destroy customer login state
unset($_SESSION['customer_id']);
unset($_SESSION['customer_username']);
unset($_SESSION['customer_name']);
setcookie('remember_customer', '', time() - 3600, "/");

header("Location: index.php");
exit;
