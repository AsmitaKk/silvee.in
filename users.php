<?php
require_once "auth_check.php";
require_once "role_management.php";
require_admin($pdo);

$page_title = "Users";
require "templates/header.php";

// flash messages
if (isset($_SESSION['error'])) { echo '<div class="alert alert-danger">'.e($_SESSION['error']).'</div>'; unset($_SESSION['error']); }
if (isset($_SESSION['success'])) { echo '<div class="alert alert-success">'.e($_SESSION['success']).'</div>'; unset($_SESSION['success']); }

// fetch users + role names
$sql = "
  SELECT u.id, u.name, u.email, u.role, u.created_at, u.is_active,
         r.role_name
  FROM users u
  LEFT JOIN roles r ON r.role_key = u.role
  ORDER BY u.created_at DESC
";
$users = $pdo->query($sql)->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-3">
  <h5 class="mb-0">Users</h5>
  <div class="d-flex gap-2">
    <a href="users_add.php" class="btn btn-sm btn-primary">
      <i class="bi bi-person-plus me-1"></i>Add User
    </a>
    <a href="masters.php" class="btn btn-sm btn-outline-secondary">Back to Masters</a>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body">
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
          <?php if (empty($users)): ?>
            <tr><td colspan="3" class="text-center text-muted py-4">No users found</td></tr>
          <?php else: ?>
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
                    <form method="POST" action="users_actions.php" style="display:inline" onsubmit="return confirm('Change active status for <?= e($u['name']) ?>?');">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button class="btn btn-sm btn-outline-warning shadow-sm py-1 px-2" title="Enable/Disable"><i class="bi bi-power"></i></button>
                    </form>
                    <form method="POST" action="users_actions.php" style="display:inline" onsubmit="return confirm('Delete <?= e($u['name']) ?>? This cannot be undone.');">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger shadow-sm py-1 px-2" title="Delete"><i class="bi bi-trash"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require "templates/footer.php"; ?>
