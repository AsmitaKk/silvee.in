<?php
require_once "auth_check.php";
require_once "config.php";

$page_title = "Database Cleanup";
require "templates/header.php";

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purge_data'])) {
    $pdo->beginTransaction();
    try {
        // 1. Delete designs belonging to hidden products
        $pdo->exec("DELETE FROM product_designs WHERE product_id IN (SELECT id FROM products WHERE is_hidden = 1)");
        
        // 2. Delete designs belonging to products that belong to hidden categories
        $pdo->exec("DELETE FROM product_designs WHERE product_id IN (SELECT id FROM products WHERE category_id IN (SELECT id FROM categories WHERE is_hidden = 1))");

        // 3. Delete hidden products
        $pdo->exec("DELETE FROM products WHERE is_hidden = 1");
        
        // 4. Delete products belonging to hidden categories
        $pdo->exec("DELETE FROM products WHERE category_id IN (SELECT id FROM categories WHERE is_hidden = 1)");
        
        // 5. Delete sub-categories where parent is hidden
        $pdo->exec("DELETE FROM categories WHERE parent_id IN (SELECT id FROM (SELECT id FROM categories WHERE is_hidden = 1) as t)");
        
        // 6. Delete hidden categories
        $pdo->exec("DELETE FROM categories WHERE is_hidden = 1");

        // 7. Detect and Purge ORPHANED sub-categories (where the parent was hard-deleted previously)
        $stmt = $pdo->query("SELECT c1.id FROM categories c1 LEFT JOIN categories c2 ON c1.parent_id = c2.id WHERE c1.parent_id IS NOT NULL AND c1.parent_id != 0 AND c2.id IS NULL");
        $orphaned_cat_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($orphaned_cat_ids)) {
            $orphaned_cat_placeholders = implode(',', array_fill(0, count($orphaned_cat_ids), '?'));
            // Delete their designs
            $stmt = $pdo->prepare("DELETE FROM product_designs WHERE product_id IN (SELECT id FROM products WHERE category_id IN ($orphaned_cat_placeholders))");
            $stmt->execute($orphaned_cat_ids);
            // Delete their products
            $stmt = $pdo->prepare("DELETE FROM products WHERE category_id IN ($orphaned_cat_placeholders)");
            $stmt->execute($orphaned_cat_ids);
            // Delete the orphaned categories themselves
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id IN ($orphaned_cat_placeholders)");
            $stmt->execute($orphaned_cat_ids);
        }

        // 8. Detect and Purge ORPHANED products (where their category was hard-deleted previously)
        $stmt = $pdo->query("SELECT p.id FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.category_id IS NOT NULL AND p.category_id != 0 AND c.id IS NULL");
        $orphaned_prod_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($orphaned_prod_ids)) {
            $orphaned_prod_placeholders = implode(',', array_fill(0, count($orphaned_prod_ids), '?'));
            // Delete their designs
            $stmt = $pdo->prepare("DELETE FROM product_designs WHERE product_id IN ($orphaned_prod_placeholders)");
            $stmt->execute($orphaned_prod_ids);
            // Delete the orphaned products
            $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($orphaned_prod_placeholders)");
            $stmt->execute($orphaned_prod_ids);
        }
        
        $pdo->commit();
        $message = "Cleanup Successful! All soft-deleted AND orphaned categories, products, and their designs have been permanently erased from the database.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error during cleanup: " . $e->getMessage();
    }
}

// Count hidden records
$hidden_categories = (int)$pdo->query("SELECT COUNT(*) FROM categories WHERE is_hidden = 1")->fetchColumn();
$hidden_products = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE is_hidden = 1")->fetchColumn();

// Count orphaned records (hard deleted parents)
$orphaned_categories = (int)$pdo->query("SELECT COUNT(*) FROM categories c1 LEFT JOIN categories c2 ON c1.parent_id = c2.id WHERE c1.parent_id IS NOT NULL AND c1.parent_id != 0 AND c2.id IS NULL")->fetchColumn();
$orphaned_products = (int)$pdo->query("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.category_id IS NOT NULL AND p.category_id != 0 AND c.id IS NULL")->fetchColumn();

$total_to_delete = $hidden_categories + $hidden_products + $orphaned_categories + $orphaned_products;

?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-danger"><i class="bi bi-trash-fill me-2"></i> Purge Deleted Data</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <p class="mb-4">This tool will permanently erase all categories and products that you previously "deleted" from the system. This action cannot be undone and will free up any duplicate Category Codes or Product Names that were blocked.</p>
                
                <ul class="list-group mb-4">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Deleted Categories (Hidden)
                        <span class="badge bg-secondary rounded-pill"><?= $hidden_categories ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Deleted Products (Hidden)
                        <span class="badge bg-secondary rounded-pill"><?= $hidden_products ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Orphaned Sub-Categories (Invisible)
                        <span class="badge bg-warning text-dark rounded-pill"><?= $orphaned_categories ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Orphaned Products (Invisible)
                        <span class="badge bg-warning text-dark rounded-pill"><?= $orphaned_products ?></span>
                    </li>
                </ul>

                <?php if ($total_to_delete > 0): ?>
                <form method="POST" onsubmit="return confirm('Are you absolutely sure? This will permanently erase the hidden and orphaned records.');">
                    <button type="submit" name="purge_data" class="btn btn-danger w-100 fw-bold shadow-sm">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Permanently Delete Hidden & Orphaned Data
                    </button>
                </form>
                <?php else: ?>
                    <div class="alert alert-info mb-0">There is currently no deleted or orphaned data to purge. Your database is completely clean!</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require "templates/footer.php"; ?>
