<?php
require_once "auth_check.php";
require_once "role_management.php";

require_admin($pdo);

$page_title = "Masters";
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

// Load roles from DB
$roles = [];
try {
  $stmt = $pdo->query("SELECT id, role_key, role_name, description, created_at FROM roles ORDER BY created_at DESC");
  $roles = $stmt->fetchAll();
} catch (Throwable $ex) {
  $roles = [];
}

// Load users from DB (with role name if available + is_active if column exists)
$users = [];
try {
  $sqlUsers = "
    SELECT u.id, u.name, u.email, u.role, u.created_at,
           " . (column_exists($pdo, 'users', 'is_active') ? "u.is_active" : "1 AS is_active") . ",
           r.role_name
    FROM users u
    LEFT JOIN roles r ON r.role_key = u.role
    ORDER BY u.created_at DESC
  ";
  $stmtU = $pdo->query($sqlUsers);
  $users = $stmtU->fetchAll();
} catch (Throwable $ex) {
  $users = [];
}

/**
 * Helper: check if column exists (safe in MySQL)
 */
function column_exists(PDO $pdo, string $table, string $column): bool {
  try {
    $st = $pdo->prepare("
      SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
  } catch (Throwable $e) {
    return false;
  }
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
  <div>
    <h5 class="mb-0">Masters Configuration</h5>
    <div class="text-secondary small">Manage system masters and configurations</div>
  </div>

  <div class="d-flex gap-2">
    <a href="users.php" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-people me-1"></i>User List
    </a>
  </div>
</div>

<div class="row g-4">
  <!-- Roles Management -->
  <div class="col-md-6">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-header bg-white">
        <h6 class="mb-0">
          <i class="bi bi-people-fill me-2"></i>User Roles
        </h6>
      </div>

      <div class="card-body">
        <!-- Add Role Form -->
        <div class="mb-4">
          <h6 class="text-primary mb-2">Add New Role</h6>

          <form method="POST" action="roles_action.php" class="row g-2">
            <input type="hidden" name="action" value="add_role">

            <div class="col-12">
              <label class="form-label small mb-1">Role Key (unique)</label>
              <input type="text" name="role_key" class="form-control form-control-sm"
                     placeholder="e.g. sales_employee" required>
              <div class="form-text">Lowercase only. Use underscore instead of spaces.</div>
            </div>

            <div class="col-12">
              <label class="form-label small mb-1">Role Name</label>
              <input type="text" name="role_name" class="form-control form-control-sm"
                     placeholder="e.g. Sales Employee" required>
            </div>

            <div class="col-12">
              <label class="form-label small mb-1">Description (optional)</label>
              <input type="text" name="description" class="form-control form-control-sm"
                     placeholder="What can this role do?">
            </div>

            <div class="col-12 d-flex justify-content-end">
              <button class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Add Role
              </button>
            </div>
          </form>
        </div>

        <!-- Roles List -->
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center">
            <h6 class="text-primary mb-0">Roles in System</h6>
            <span class="text-muted small">Total: <?= count($roles) ?></span>
          </div>

          <?php if (empty($roles)): ?>
            <div class="text-muted small mt-2">
              No roles found (or roles table not created yet). Run the SQL to create <code>roles</code>.
            </div>
          <?php else: ?>
            <div class="table-responsive mt-2">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th class="ps-3">Role Details</th>
                    <th class="text-end pe-3">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($roles as $r): ?>
                    <tr>
                      <td class="align-middle ps-3 py-3">
                        <div class="fw-bold text-dark mb-1"><?= e($r['role_name']) ?> <span class="badge bg-light text-secondary border fw-normal py-0 px-1 ms-1"><?= e($r['role_key']) ?></span></div>
                        <?php if(!empty($r['description'])): ?>
                          <div class="text-muted small lh-sm text-wrap mb-1" style="max-width:250px;"><?= e($r['description']) ?></div>
                        <?php endif; ?>
                        <div class="small text-muted fst-italic mt-1" style="font-size: 0.70rem;">Created: <?= !empty($r['created_at']) ? e(date('M j, Y', strtotime($r['created_at']))) : '-' ?></div>
                      </td>
                      <td class="text-end align-middle pe-3">
                        <?php if ($r['role_key'] === 'admin'): ?>
                          <span class="text-muted small border px-2 py-1 rounded-pill bg-light">Protected</span>
                        <?php else: ?>
                          <div class="d-flex justify-content-end gap-1 flex-md-nowrap">
                            <form method="POST" action="roles_action.php" onsubmit="return confirm('Delete role <?= e($r['role_name']) ?>?');">
                              <input type="hidden" name="action" value="delete_role">
                              <input type="hidden" name="role_key" value="<?= e($r['role_key']) ?>">
                              <button class="btn btn-sm btn-outline-danger shadow-sm py-1 px-2"><i class="bi bi-trash"></i></button>
                            </form>
                            <a href="permissions.php?role=<?= urlencode($r['role_key']) ?>" class="btn btn-sm btn-outline-secondary shadow-sm py-1 px-2"><i class="bi bi-shield-lock"></i></a>
                          </div>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <div class="text-secondary small">
          <i class="bi bi-info-circle me-1"></i>
          Configure permissions per role using the shield icon.
        </div>
      </div>
    </div>
  </div>

  <!-- Users Management -->
  <div class="col-md-6">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-header bg-white">
        <h6 class="mb-0">
          <i class="bi bi-person-lines-fill me-2"></i>Users
        </h6>
      </div>

      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="text-primary mb-0">Current Users</h6>

          <div class="d-flex gap-2">
            <a href="users_add.php" class="btn btn-sm btn-primary">
              <i class="bi bi-person-plus me-1"></i>Add User
            </a>
            <a href="users.php" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-people me-1"></i>All Users
            </a>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th class="ps-3 bg-light">User Profile</th>
                <th class="bg-light">Role & Status</th>
                <th class="text-end pe-3 bg-light">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($users)): ?>
                <?php foreach ($users as $u): ?>
                  <tr>
                    <td class="align-middle ps-3 py-3">
                      <div class="fw-bold text-dark mb-1"><?= e($u['name']) ?></div>
                      <div class="small text-muted text-break"><a href="mailto:<?= e($u['email']) ?>" class="text-decoration-none text-secondary"><i class="bi bi-envelope me-1"></i><?= e($u['email']) ?></a></div>
                      <div class="small text-muted fst-italic mt-1" style="font-size: 0.70rem;">Joined: <?= !empty($u['created_at']) ? e(date('M j, Y', strtotime($u['created_at']))) : '-' ?></div>
                    </td>
                    <td class="align-middle py-3">
                      <div class="mb-2"><span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 shadow-sm"><?= e($u['role_name'] ?: $u['role']) ?></span></div>
                      <div>
                        <?php if ((int)$u['is_active'] === 1): ?>
                          <span class="badge bg-success-subtle text-success border border-success-subtle px-2 shadow-sm"><i class="bi bi-check-circle me-1"></i>Active</span>
                        <?php else: ?>
                          <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 shadow-sm"><i class="bi bi-dash-circle me-1"></i>Disabled</span>
                        <?php endif; ?>
                      </div>
                    </td>
                    <td class="text-end align-middle pe-3">
                      <div class="d-flex justify-content-end gap-1 flex-md-nowrap mt-1">
                        <a href="users_edit.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-primary shadow-sm py-1 px-2" title="Edit"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="users_actions.php" onsubmit="return confirm('Change active status for <?= e($u['name']) ?>?');">
                          <input type="hidden" name="action" value="toggle_active">
                          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                          <button class="btn btn-sm btn-outline-warning shadow-sm py-1 px-2" title="Enable/Disable"><i class="bi bi-power"></i></button>
                        </form>
                        <form method="POST" action="users_actions.php" onsubmit="return confirm('Delete <?= e($u['name']) ?>?');">
                          <input type="hidden" name="action" value="delete_user">
                          <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                          <button class="btn btn-sm btn-outline-danger shadow-sm py-1 px-2" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="3" class="text-center text-muted py-4">No users found</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="text-secondary small mt-2">
          <i class="bi bi-info-circle me-1"></i>
          Use the power icon to disable/activate users. Use trash to delete.
        </div>
      </div>
    </div>
  </div>
</div>

<?php require "templates/footer.php"; ?>
