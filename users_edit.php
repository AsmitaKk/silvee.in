<?php
require_once "auth_check.php";
require_once "role_management.php";

require_admin($pdo);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  header("Location: masters.php");
  exit;
}

// load user
$stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
  $_SESSION['error'] = "User not found.";
  header("Location: masters.php");
  exit;
}

// load roles
$stmt = $pdo->query("SELECT role_key, role_name FROM roles ORDER BY role_name");
$roles = $stmt->fetchAll();

$page_title = "Edit User";
require "templates/header.php";

// flash messages
if (isset($_SESSION['error'])) {
  echo '<div class="alert alert-danger">' . e($_SESSION['error']) . '</div>';
  unset($_SESSION['error']);
}
?>

<div class="card shadow-sm border-0">
  <div class="card-header bg-white">
    <h6 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit User</h6>
  </div>

  <div class="card-body">
    <form method="POST" action="users_save.php" class="row g-3">
      <input type="hidden" name="id" value="<?= e($user['id']) ?>">

      <div class="col-md-6">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control"
               value="<?= e($user['name']) ?>" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control"
               value="<?= e($user['email']) ?>" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">New Password (optional)</label>
        <input type="password" name="password" class="form-control">
      </div>

      <div class="col-md-6">
        <label class="form-label">Role</label>
        <select name="role" class="form-select" required>
          <?php foreach ($roles as $r): ?>
            <option value="<?= e($r['role_key']) ?>"
              <?= ($user['role'] === $r['role_key']) ? 'selected' : '' ?>>
              <?= e($r['role_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-primary">
          <i class="bi bi-save me-1"></i>Update User
        </button>
      </div>

    </form>
  </div>
</div>

<?php require "templates/footer.php"; ?>
