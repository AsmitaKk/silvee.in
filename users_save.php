<?php
require_once "auth_check.php";
require_once "role_management.php";

require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: masters.php");
  exit;
}

$id = (int)($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? '');

if ($name === '' || $email === '' || $role === '') {
  $_SESSION['error'] = "Name, email, and role are required.";
  header("Location: " . ($id ? "users_edit.php?id=$id" : "users_add.php"));
  exit;
}

try {
  if ($id > 0) {
    // UPDATE
    if ($password !== '') {
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, password_hash=?, role=? WHERE id=?");
      $stmt->execute([$name, $email, $hash, $role, $id]);
    } else {
      $stmt = $pdo->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
      $stmt->execute([$name, $email, $role, $id]);
    }
    $_SESSION['success'] = "User updated successfully.";
  } else {
    // INSERT
    if ($password === '') {
      $_SESSION['error'] = "Password is required for new users.";
      header("Location: users_add.php");
      exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
      "INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$name, $email, $hash, $role]);
    $_SESSION['success'] = "User created successfully.";
  }
} catch (PDOException $e) {
  $_SESSION['error'] = "Database error: " . $e->getMessage();
}

header("Location: masters.php");
exit;
