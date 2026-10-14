<?php
require_once "auth_check.php";
require_once "role_management.php";
require_admin($pdo);

$role_key = $_GET['role'] ?? '';
$page_title = "Role Permissions";
require "templates/header.php";

// Flash messages
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">' . e($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . e($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
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
    echo '<div class="alert alert-warning">';
    echo '<h6>⚠️ Database Tables Missing</h6>';
    echo '<p>The role-based access control system needs to be set up first.</p>';
    echo '<p><a href="setup_role_system.php" class="btn btn-primary">Run Setup Now</a></p>';
    echo '</div>';
    require "templates/footer.php";
    exit;
}

// Get role info
$stmt = $pdo->prepare("SELECT role_key, role_name, description FROM roles WHERE role_key = ?");
$stmt->execute([$role_key]);
$role = $stmt->fetch();

if (!$role) {
    $_SESSION['error'] = "Role not found.";
    header("Location: masters.php");
    exit;
}

// Get modules for permission assignment
$stmt = $pdo->query("SELECT module_key, module_name FROM modules ORDER BY module_name");
$modules = $stmt->fetchAll();

// Get current permissions for this role
$stmt = $pdo->prepare("
    SELECT module_key, can_view, can_edit 
    FROM role_permissions 
    WHERE role_key = ?
");
$stmt->execute([$role_key]);
$current_perms = [];
foreach ($stmt->fetchAll() as $perm) {
    $current_perms[$perm['module_key']] = $perm;
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0">Permissions for <?= e($role['role_name']) ?></h5>
  <div class="text-secondary">
    <small><?= e($role['description'] ?? 'No description') ?></small>
  </div>
  <a href="masters.php" class="btn btn-sm btn-outline-secondary">Back to Masters</a>
</div>

<div class="card shadow-sm border-0">
  <div class="card-header bg-white">
    <h6 class="mb-0">
      <i class="bi bi-shield-lock me-2"></i>Module Permissions
    </h6>
  </div>
  <div class="card-body">
    <form method="POST" action="permissions_save.php" class="row g-3">
      <input type="hidden" name="role_key" value="<?= e($role_key) ?>">
      
      <div class="col-12">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Module</th>
                <th width="100">View Access</th>
                <th width="100">Edit Access</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($modules as $module): ?>
                <tr>
                  <td>
                    <strong><?= e($module['module_name']) ?></strong>
                    <div class="text-muted small"><?= e($module['module_key']) ?></div>
                  </td>
                  
                  <td>
                    <div class="form-check">
                      <input type="checkbox" name="view_<?= e($module['module_key']) ?>"
                             value="1"
                             <?= isset($current_perms[$module['module_key']]['can_view']) && $current_perms[$module['module_key']]['can_view'] ? 'checked' : '' ?>>
                      <label class="form-check-label">Can View</label>
                    </div>
                  </td>
                  
                  <td>
                    <div class="form-check">
                      <input type="checkbox" name="edit_<?= e($module['module_key']) ?>"
                             value="1"
                             <?= isset($current_perms[$module['module_key']]['can_edit']) && $current_perms[$module['module_key']]['can_edit'] ? 'checked' : '' ?>>
                      <label class="form-check-label">Can Edit</label>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="col-12 d-flex justify-content-end mt-3">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i>Save Permissions
        </button>
      </div>
    </form>
  </div>
</div>

<?php require "templates/footer.php"; ?>
