<?php
require_once "auth_check.php";
require_once "role_management.php";

require_admin($pdo);

$page_title = "Add User";
require "templates/header.php";

// flash messages
if (isset($_SESSION['error'])) {
  echo '<div class="alert alert-danger">' . e($_SESSION['error']) . '</div>';
  unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
  echo '<div class="alert alert-success">' . e($_SESSION['success']) . '</div>';
  unset($_SESSION['success']);
}

// load roles
$stmt = $pdo->query("SELECT role_key, role_name FROM roles ORDER BY role_name");
$roles = $stmt->fetchAll();
?>

<div class="card shadow-sm border-0">
  <div class="card-header bg-white">
    <h6 class="mb-0"><i class="bi bi-person-plus me-2"></i>Add User</h6>
  </div>

  <div class="card-body">
    <form method="POST" action="users_save.php" class="row g-3">

      <div class="col-md-6">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>

      <div class="col-md-6">
        <label class="form-label">Role</label>
        <select name="role" class="form-select" required>
          <option value="">Select role</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= e($r['role_key']) ?>">
              <?= e($r['role_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 d-flex justify-content-end">
        <button class="btn btn-primary">
          <i class="bi bi-save me-1"></i>Create User
        </button>
      </div>

    </form>
  </div>
</div>

<?php require "templates/footer.php"; ?>
