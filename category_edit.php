<?php
require_once "auth_check.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: categories.php");
    exit;
}

// Fetch category
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    header("Location: categories.php");
    exit;
}

// Fetch all categories for parent dropdown
$allCategories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$page_title = "Edit Category";
$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $image_path = $category['image_path'];

    if ($name === '') {
        $errors[] = "Name is required.";
    } else {
        // Check for duplicate name (case-insensitive)
        $stmt_name = $pdo->prepare("SELECT * FROM categories WHERE LOWER(name) = LOWER(?) AND id != ?");
        $stmt_name->execute([$name, $category['id']]);
        $existing_name = $stmt_name->fetch(PDO::FETCH_ASSOC);

        // Check for duplicate code (case-insensitive)
        $existing_cat_with_code = null;
        if ($code !== '') {
            $stmt_code = $pdo->prepare("SELECT * FROM categories WHERE LOWER(code) = LOWER(?) AND id != ?");
            $stmt_code->execute([$code, $category['id']]);
            $existing_cat_with_code = $stmt_code->fetch(PDO::FETCH_ASSOC);
        } else {
            $code = null;
        }

        if ($existing_name) {
            $errors[] = "This category is already available";
        } elseif ($existing_cat_with_code) {
            $errors[] = "This category code is already used for the '" . htmlspecialchars($existing_cat_with_code['name'], ENT_QUOTES, 'UTF-8') . "'";
        }
    }

    // Prevent self as parent
    if ($parent_id === $category['id']) {
        $errors[] = "A category cannot be its own parent.";
    }

    // Handle image upload (optional)
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!is_dir('uploads/categories')) {
                mkdir('uploads/categories', 0777, true);
            }
            $newName = 'uploads/categories/' . time() . '_' . rand(1000,9999) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $newName)) {
                $image_path = $newName;
            } else {
                $errors[] = 'Failed to upload new image.';
            }
        } else {
            $errors[] = 'Image upload error.';
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, code = ?, parent_id = ?, image_path = ?, labour_charge = 0.00, flat_rate = 0.00 WHERE id = ?");
        $stmt->execute([$name, $code, $parent_id, $image_path, $category['id']]);

        $messages[] = "Category updated successfully.";

        // Refresh category data
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();
    }
}

require "templates/header.php";
?>

<?php foreach ($messages as $m): ?>
  <div class="alert alert-success"><?= e($m) ?></div>
<?php endforeach; ?>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger"><?= e($e) ?></div>
<?php endforeach; ?>

<div class="card shadow-sm border-0">
  <div class="card-header bg-white">
    <strong>Edit Category</strong>
  </div>
  <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <div class="mb-3">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control"
               value="<?= e($category['name']) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Category Code (optional)</label>
        <input type="text" name="code" class="form-control"
               value="<?= e($category['code'] ?? '') ?>">
      </div>



      <div class="mb-3">
        <label class="form-label">Parent Category (optional)</label>
        <select name="parent_id" class="form-select">
          <option value="">-- Top Level --</option>
          <?php foreach ($allCategories as $cat): ?>
            <?php if ($cat['id'] == $category['id']) continue; // skip self ?>
            <option value="<?= $cat['id'] ?>"
              <?= ($category['parent_id'] == $cat['id']) ? 'selected' : '' ?>>
              <?= e($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Image (optional)</label>
        <input type="file" name="image" class="form-control" accept="image/*">
        <?php if (!empty($category['image_path'])): ?>
          <div class="mt-2">
            <img src="<?= e($category['image_path']) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
          </div>
        <?php endif; ?>
        <div class="form-text">
          Leave blank to keep existing image.
        </div>
      </div>

      <button class="btn btn-primary">Save Changes</button>
      <a href="categories.php" class="btn btn-secondary">Back to Categories</a>
    </form>
  </div>
</div>

<?php require "templates/footer.php"; ?>
