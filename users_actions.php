<?php
require_once "auth_check.php";
require_once "role_management.php";
require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: users.php");
  exit;
}

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
  $_SESSION['error'] = "Invalid user.";
  header("Location: users.php");
  exit;
}

// Load user
$stmt = $pdo->prepare("SELECT id, name, email, role, is_active FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
  $_SESSION['error'] = "User not found.";
  header("Location: users.php");
  exit;
}

// Optional: protect admin from deletion/disable (by role_key)
$isCoreAdmin = ($user['role'] === 'admin');

if ($action === 'toggle_active') {
  if ($isCoreAdmin) {
    $_SESSION['error'] = "Admin user cannot be disabled.";
    header("Location: users.php");
    exit;
  }

  $new = ((int)$user['is_active'] === 1) ? 0 : 1;
  $stmt = $pdo->prepare("UPDATE users SET is_active=? WHERE id=?");
  $stmt->execute([$new, $id]);

  $_SESSION['success'] = $new ? "User activated." : "User disabled.";
  header("Location: users.php");
  exit;
}

if ($action === 'delete_user') {
  if ($isCoreAdmin) {
    $_SESSION['error'] = "Admin user cannot be deleted.";
    header("Location: users.php");
    exit;
  }

  // ✅ Safe delete rule: block deletion if user is referenced in sales/stock tables (best-effort checks)
  // If your tables differ, change these checks.
  $blockingTables = [
    // ['table' => 'sales', 'column' => 'created_by'],
    // ['table' => 'stock_movements', 'column' => 'created_by'],
  ];

  foreach ($blockingTables as $bt) {
    $sql = "SELECT COUNT(*) FROM {$bt['table']} WHERE {$bt['column']} = ?";
    $st = $pdo->prepare($sql);
    $st->execute([$id]);
    if ((int)$st->fetchColumn() > 0) {
      $_SESSION['error'] = "Cannot delete. User is referenced in {$bt['table']}. Disable instead.";
      header("Location: users.php");
      exit;
    }
  }

  // ✅ Delete
  $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
  $stmt->execute([$id]);

  $_SESSION['success'] = "User deleted successfully.";
  header("Location: users.php");
  exit;
}

header("Location: users.php");
exit;
