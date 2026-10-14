<?php
session_start();
$_SESSION = [];
session_destroy();
setcookie('remember_admin', '', time() - 3600, '/');
header("Location: login.php");
exit;
