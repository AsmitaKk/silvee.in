<?php
require_once "config.php";

/**
 * Get user role from DB (single source of truth)
 */
function get_user_role(PDO $pdo, $user_id): ?string {
    $user_id = (int)$user_id;
    if ($user_id <= 0) return null;

    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $role = $stmt->fetchColumn();
        return $role ? (string)$role : null;
    } catch (Throwable $e) {
        error_log("Error getting user role: " . $e->getMessage());
        return null;
    }
}

function current_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function current_user_role(PDO $pdo): ?string {
    return get_user_role($pdo, current_user_id());
}

function is_admin(PDO $pdo): bool {
    return current_user_role($pdo) === 'admin';
}

function is_sales_employee(PDO $pdo): bool {
    return current_user_role($pdo) === 'sales_employee';
}

function require_admin(PDO $pdo): void {
    if (!is_admin($pdo)) {
        $_SESSION['error'] = 'Access denied. Admin privileges required.';
        header("Location: dashboard.php");
        exit;
    }
}

function require_sales_access(PDO $pdo): void {
    $role = current_user_role($pdo);
    if ($role !== 'admin' && $role !== 'sales_employee') {
        $_SESSION['error'] = 'Access denied. Valid user role required.';
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Permission system using role_permissions table
 */
function has_permission(PDO $pdo, string $module_key, string $perm = 'view'): bool
{
    $role = current_user_role($pdo);
    if (!$role) return false;

    // Admin shortcut
    if ($role === 'admin') return true;

    $col = ($perm === 'edit') ? 'can_edit' : 'can_view';

    try {
        $stmt = $pdo->prepare("
            SELECT {$col}
            FROM role_permissions
            WHERE role_key = ? AND module_key = ?
            LIMIT 1
        ");
        $stmt->execute([$role, $module_key]);
        return (int)$stmt->fetchColumn() === 1;
    } catch (Throwable $e) {
        // If table doesn't exist yet, deny safely
        error_log("Permission check failed: " . $e->getMessage());
        return false;
    }
}

function require_permission(PDO $pdo, string $module_key, string $perm = 'view'): void
{
    if (!has_permission($pdo, $module_key, $perm)) {
        $_SESSION['error'] = "Access denied.";
        header("Location: dashboard.php");
        exit;
    }
}
