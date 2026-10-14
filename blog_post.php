<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$preview = !empty($_GET['preview']);

// Check admin preview permission
$is_admin = false;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['user_id']) || !empty($_SESSION['admin_logged_in']) || !empty($_SESSION['role'])) {
    $is_admin = true;
}

$post = null;
if ($post_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($slug !== '') {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$post) {
    http_response_code(404);
    $page_title = 'Article Not Found - Silvee 925';
    require_once __DIR__ . '/store_header.php';
    echo '<div class="container py-5 text-center" style="min-height:50vh;display:flex;flex-direction:column;justify-content:center;align-items:center;">
        <h2 style="font-family:\'Playfair Display\',serif;color:#0D3B36;">Article Not Found</h2>
        <p class="text-muted">The jewellery article you are looking for is no longer available.</p>
        <a class="btn btn-dark rounded-pill px-4 mt-3" href="blog.php">Return to Blog</a>
    </div>';
    require_once __DIR__ . '/store_footer.php';
    exit;
}

// Access control: published posts are public; drafts require admin preview
if (($post['status'] ?? 'draft') !== 'published') {
    if (!$is_admin && !$preview) {
        http_response_code(403);
        $page_title = 'Draft Article - Silvee 925';
        require_once __DIR__ . '/store_header.php';
        echo '<div class="container py-5 text-center" style="min-height:50vh;display:flex;flex-direction:column;justify-content:center;align-items:center;">
            <h2 style="font-family:\'Playfair Display\',serif;color:#0D3B36;">Draft Article</h2>
            <p class="text-muted">This article is currently in draft mode and has not been published yet.</p>
            <a class="btn btn-dark rounded-pill px-4 mt-3" href="blog.php">Browse Published Articles</a>
        </div>';
        require_once __DIR__ . '/store_footer.php';
        exit;
    }
}

// Fetch related published posts (excluding current post)
$related_stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE status = 'published' AND id != :current_id ORDER BY published_at DESC, created_at DESC LIMIT 3");
$related_stmt->execute([':current_id' => $post['id']]);
$related_posts = $related_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check featured image existence & cache busting
$feature = trim(str_replace('\\', '/', $post['featured_image'] ?? ''));
$feature = ltrim($feature, '/');
$has_image = false;
$feature_src = '';
if (!empty($feature)) {
    $full_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $feature);
    if (@file_exists($full_path) && @is_file($full_path)) {
        $has_image = true;
        $feature_src = $feature . '?v=' . filemtime($full_path);
    }
}

$page_title = ($post['seo_title'] ?: $post['title']) . ' - Silvee 925 Journal';
$meta_description = $post['meta_description'] ?: ($post['short_description'] ?: substr(strip_tags($post['content']), 0, 160));
require_once __DIR__ . '/store_header.php';
?>

<link rel="stylesheet" href="assets/blog.css?v=<?= file_exists(__DIR__ . '/assets/blog.css') ? filemtime(__DIR__ . '/assets/blog.css') : '1.0' ?>">

<div class="blog-detail-shell">
    <div class="blog-detail-container">
        <!-- Full-Width Featured Hero Banner (Tanishq Style) -->
        <?php if ($has_image): ?>
            <figure class="blog-detail-hero">
                <img src="<?= e($feature_src) ?>" 
                     alt="<?= e($post['image_alt'] ?: $post['title']) ?>" 
                     loading="eager">
            </figure>
        <?php endif; ?>

        <header class="blog-detail-header">
            <h1 class="blog-detail-title"><?= e($post['title']) ?></h1>
        </header>

        <!-- Article Body -->
        <div class="blog-detail-body blog-style-<?= e($post['article_style'] ?: 'classic') ?>">
            <?= blog_output_content_safe($post['content']) ?>
        </div>

        <!-- Actions / Back to Blog -->
        <div class="blog-detail-actions">
            <a href="blog.php" class="btn btn-outline-dark rounded-pill px-4">
                <i class="bi bi-arrow-left me-1"></i> Back to All Articles
            </a>
        </div>
    </div><!-- /.blog-detail-container -->

    <!-- Related Posts Section matching Reference -->
    <?php if (!empty($related_posts)): ?>
        <section class="related-posts-section">
            <h2 class="related-posts-heading">Related Posts</h2>
            <div class="related-grid">
                <?php foreach ($related_posts as $rel): ?>
                    <?php
                    $rel_feature = trim(str_replace('\\', '/', $rel['featured_image'] ?? ''));
                    $rel_feature = ltrim($rel_feature, '/');
                    $rel_has_image = false;
                    $rel_img_src = '';
                    if (!empty($rel_feature)) {
                        $rf_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel_feature);
                        if (@file_exists($rf_path) && @is_file($rf_path)) {
                            $rel_has_image = true;
                            $rel_img_src = $rel_feature . '?v=' . filemtime($rf_path);
                        }
                    }
                    $rel_desc = trim((string)($rel['short_description'] ?? ''));
                    if ($rel_desc === '') {
                        $rel_desc = strip_tags((string)$rel['content']);
                    }
                    ?>
                    <article class="blog-card">
                        <div class="blog-card-media">
                            <?php if ($rel_has_image): ?>
                                <img src="<?= e($rel_img_src) ?>" alt="<?= e($rel['image_alt'] ?: $rel['title']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="blog-card-media-placeholder">925 SILVER</div>
                            <?php endif; ?>
                        </div>

                        <div class="blog-card-body">
                            <h3 class="blog-card-title">
                                <a href="blog_post.php?slug=<?= urlencode($rel['slug']) ?>"><?= e($rel['title']) ?></a>
                            </h3>

                            <p class="blog-card-desc"><?= e($rel_desc) ?></p>

                            <div class="blog-card-footer">
                                <a href="blog_post.php?slug=<?= urlencode($rel['slug']) ?>" class="blog-card-readmore">Read More <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/store_footer.php'; ?>
