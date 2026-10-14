<?php
require_once "auth_check.php";
require_once "role_management.php";
require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: masters.php");
    exit;
}

$role_key = $_POST['role_key'] ?? '';
if ($role_key === '') {
    $_SESSION['error'] = "Invalid role.";
    header("Location: masters.php");
    exit;
}

// Check if required tables exist
$tables_exist = true;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('roles', 'modules', 'role_permissions')");
    $tableCount = $stmt->fetchColumn();
    if ($tableCount < 3) {
        $tables_exist = false;
    }
} catch (Exception $e) {
    $tables_exist = false;
}

if (!$tables_exist) {
    $_SESSION['error'] = "Database tables not found. Please run setup_role_system.php first.";
    header("Location: masters.php");
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Delete existing permissions for this role
    $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE role_key = ?");
    $stmt->execute([$role_key]);
    
    // Get all modules
    $stmt = $pdo->query("SELECT module_key FROM modules");
    $modules = $stmt->fetchAll();
    
    // Insert new permissions
    foreach ($modules as $module) {
        $module_key = $module['module_key'];
        
        $can_view = isset($_POST["view_{$module_key}"]) ? 1 : 0;
        $can_edit = isset($_POST["edit_{$module_key}"]) ? 1 : 0;
        
        // Only insert if at least view permission is granted
        if ($can_view || $can_edit) {
            $stmt = $pdo->prepare("
                INSERT INTO role_permissions (role_key, module_key, can_view, can_edit)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$role_key, $module_key, $can_view, $can_edit]);
        }
    }
    
    $pdo->commit();
    $_SESSION['success'] = "Permissions updated successfully.";
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error saving permissions: " . $e->getMessage();
}

header("Location: permissions.php?role=" . urlencode($role_key));
exit;
?>
