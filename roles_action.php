<?php
require_once "auth_check.php";
require_once "role_management.php";

require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: masters.php");
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add_role') {
    $role_key = strtolower(trim($_POST['role_key'] ?? ''));
    $role_name = trim($_POST['role_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($role_key === '' || $role_name === '') {
        $_SESSION['error'] = "Role key and role name are required.";
        header("Location: masters.php");
        exit;
    }

    if (!preg_match('/^[a-z0-9_]+$/', $role_key)) {
        $_SESSION['error'] = "Role key must be lowercase and contain only letters, numbers, and underscore.";
        header("Location: masters.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO roles (role_key, role_name, description) VALUES (?, ?, ?)"
        );
        $stmt->execute([$role_key, $role_name, $description ?: null]);

        $_SESSION['success'] = "Role added successfully.";
    } catch (PDOException $e) {
        if ((int)$e->errorInfo[1] === 1062) {
            $_SESSION['error'] = "Role key already exists.";
        } else {
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    }

    header("Location: masters.php");
    exit;
}

if ($action === 'delete_role') {
    $role_key = $_POST['role_key'] ?? '';
    
    if ($role_key === 'admin') {
        $_SESSION['error'] = "Admin role cannot be deleted.";
        header("Location: masters.php");
        exit;
    }

    // Check if role is assigned to any users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
    $stmt->execute([$role_key]);
    $userCount = (int)$stmt->fetchColumn();

    if ($userCount > 0) {
        $_SESSION['error'] = "Cannot delete role. It is assigned to {$userCount} user(s).";
        header("Location: masters.php");
        exit;
    }

    try {
        $pdo->beginTransaction();
        
        // Delete role permissions first
        $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_key = ?");
        $stmt->execute([$role_key]);

        // Delete role
        $stmt = $pdo->prepare("DELETE FROM roles WHERE role_key = ?");
        $stmt->execute([$role_key]);

        $pdo->commit();
        $_SESSION['success'] = "Role deleted successfully.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error deleting role: " . $e->getMessage();
    }

    header("Location: masters.php");
    exit;
}

header("Location: masters.php");
exit;
?>
