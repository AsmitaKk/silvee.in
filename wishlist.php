<?php
/**
 * Wishlist Page — Shows all items the customer has saved to their wishlist
 */
require_once "config.php";
require_once "functions.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to home if not logged in as customer
if (!isset($_SESSION['customer_id'])) {
    header("Location: index.php");
    exit;
}

$customer_id = (int) $_SESSION['customer_id'];

// Handle remove action (non-AJAX fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_wishlist') {
    $design_id = (int) ($_POST['design_id'] ?? 0);
    if ($design_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM customer_wishlists WHERE customer_id = ? AND design_id = ?");
        $stmt->execute([$customer_id, $design_id]);
    }
    header("Location: wishlist.php");
    exit;
}

// Fetch wishlist items with full design + product info
$wishlist_items = [];
try {
    $stmt = $pdo->prepare("
        SELECT d.id, d.design_name, d.sku, d.image_path, d.stock_qty,
               p.name AS product_name, p.per_piece_weight AS weight,
               w.created_at AS wishlisted_at,
               COALESCE(
                   (SELECT c2.name FROM categories c2 WHERE c2.id = p.category_id),
                   ''
               ) AS subcategory_name
        FROM customer_wishlists w
        JOIN product_designs d ON w.design_id = d.id
        JOIN products p ON d.product_id = p.id
        WHERE w.customer_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$customer_id]);
    $wishlist_items = $stmt->fetchAll();
} catch (Exception $e) {
    // Silently handle
}

$page_title_store = "My Wishlist";
require_once "store_header.php";
?>

<div class="container py-3 py-md-5 my-2 my-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3 mb-md-4">
        <h2 class="fw-bold mb-0" style="font-family: 'Playfair Display', serif; font-size: clamp(1.4rem, 2.5vw, 2rem);">
            <i class="bi bi-heart me-2" style="color: var(--gold-accent);"></i>My Wishlist
        </h2>
        <span class="badge bg-dark rounded-pill px-3 py-2 fw-medium"><?= count($wishlist_items) ?> Items</span>
    </div>

    <?php if (empty($wishlist_items)): ?>
    <div class="text-center py-5 shadow-sm rounded-4 bg-white border">
        <i class="bi bi-heart mb-3 text-muted" style="font-size: 3.5rem;"></i>
        <h4 class="fw-bold">Your wishlist is empty</h4>
        <p class="text-muted mb-4">Browse our collections and save your favourite designs!</p>
        <a href="shop.php" class="btn btn-dark mt-2 px-4 py-2 rounded-pill hover-lift shadow-sm">Browse Collections</a>
    </div>
    <?php else: ?>

    <div class="row g-2 g-sm-3 g-md-4">
        <?php foreach ($wishlist_items as $item):
            $img_src = '';
            if (!empty($item['image_path'])) {
                $img_src = explode(',', $item['image_path'])[0];
            }
            $weightText = number_format(floatval($item['weight']), 2) . ' gm';
        ?>
        <div class="col-6 col-sm-6 col-md-4 col-lg-3" id="wishlist-item-<?= $item['id'] ?>">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden hover-lift bg-white">
                <!-- Image -->
                <div class="position-relative bg-light border-bottom design-card-img-container">
                    <?php if (!empty($img_src)): ?>
                        <img src="<?= e($img_src) ?>" alt="<?= e($item['design_name']) ?>" loading="lazy" decoding="async" class="w-100 h-100 object-fit-contain p-2 rounded-4">
                    <?php else: ?>
                        <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted" style="height: 200px;">
                            <i class="bi bi-image fs-1 opacity-25"></i>
                        </div>
                    <?php endif; ?>

                    <!-- Stock Badge -->
                    <div class="position-absolute top-0 end-0 m-2 z-2">
                        <?php if ($item['stock_qty'] > 0): ?>
                            <span class="badge bg-white text-dark shadow-sm border px-2 py-1" style="font-size: 0.68rem;"><i class="bi bi-box-seam text-success me-1"></i>In Stock</span>
                        <?php else: ?>
                            <span class="badge bg-danger text-white shadow-sm border border-danger px-2 py-1" style="font-size: 0.68rem;">Sold Out</span>
                        <?php endif; ?>
                    </div>

                    <!-- Remove from Wishlist (Top Left) -->
                    <div class="position-absolute top-0 start-0 m-2 z-2">
                        <button type="button" class="btn btn-link p-0 shadow-none" title="Remove from Wishlist"
                                onclick="removeFromWishlistPage(<?= $item['id'] ?>)" style="line-height: 1;">
                            <i class="bi bi-heart-fill" style="font-size: 1.1rem; color: #dc2626;"></i>
                        </button>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="card-body p-2 p-md-3 text-center d-flex flex-column">
                    <h6 class="fw-bold text-dark mb-1 text-truncate" style="font-family: var(--category-font), sans-serif; font-size: clamp(0.82rem, 1vw, 0.95rem);" title="<?= e($item['design_name']) ?>">
                        <?= e($item['design_name']) ?>
                    </h6>
                    <small class="text-uppercase text-muted fw-bold mb-1 d-block text-truncate" style="letter-spacing: 0.5px; font-size: 0.62rem;">
                        <?= e($item['subcategory_name'] ?: $item['product_name']) ?>
                    </small>
                    <div class="text-secondary small mb-2" style="font-size: 0.72rem;">
                        SKU: <?= e($item['sku'] ?: 'N/A') ?> · <?= $weightText ?>
                    </div>

                    <div class="mt-auto pt-1 d-flex flex-column gap-2">
                        <?php if ($item['stock_qty'] > 0): ?>
                        <!-- Add to Cart -->
                        <form action="cart.php" method="POST" class="ajax-cart-form w-100">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="design_id" value="<?= $item['id'] ?>">
                            <input type="hidden" name="qty" value="1">
                            <button type="submit" class="btn btn-dark btn-sm rounded-pill fw-bold w-100 shadow-sm hover-lift py-1">
                                <i class="bi bi-cart-plus me-1"></i> Add to Cart
                            </button>
                        </form>
                        <?php else: ?>
                        <button class="btn btn-secondary btn-sm w-100 rounded-pill fw-bold opacity-50 py-1" disabled>Sold Out</button>
                        <?php endif; ?>

                        <!-- Remove -->
                        <button type="button" class="btn btn-outline-danger btn-sm w-100 rounded-pill fw-bold py-1" style="font-size: 0.78rem;"
                                onclick="removeFromWishlistPage(<?= $item['id'] ?>)">
                            <i class="bi bi-x-circle me-1"></i> Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-4 pt-3 border-top">
        <a href="shop.php" class="text-decoration-none text-dark fw-medium btn btn-light rounded-pill px-4 shadow-sm">
            <i class="bi bi-arrow-left me-2"></i>Continue Shopping
        </a>
    </div>

    <?php endif; ?>
</div>

<script>
function removeFromWishlistPage(designId) {
    fetch('wishlist_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=remove&design_id=' + designId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Animate and remove card
            const card = document.getElementById('wishlist-item-' + designId);
            if (card) {
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    card.remove();
                    // Update count badge
                    const countBadge = document.querySelector('.badge.bg-dark');
                    if (countBadge) countBadge.textContent = data.wishlist_count + ' Items';
                    // If no items left, reload to show empty state
                    if (data.wishlist_count === 0) location.reload();
                }, 300);
            }
            // Update nav badges
            updateWishlistBadges(data.wishlist_count);
        }
    })
    .catch(() => {});
}

function updateWishlistBadges(count) {
    const badges = document.querySelectorAll('.wishlist-badge');
    badges.forEach(badge => {
        badge.textContent = count;
        if (count > 0) badge.classList.remove('d-none');
        else badge.classList.add('d-none');
    });
}
</script>

<?php require_once "store_footer.php"; ?>
