<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

const BLOGS_PER_LOAD = 6;

function render_blog_card(array $post): string
{
    $feature = trim(str_replace('\\', '/', $post['featured_image'] ?? ''));
    $feature = ltrim($feature, '/');
    $excerpt = trim((string)($post['short_description'] ?? ''));
    if ($excerpt === '') {
        $excerpt = strip_tags((string)$post['content']);
    }

    $has_image = false;
    $feature_src = '';
    if ($feature !== '') {
        $full_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $feature);
        if (@file_exists($full_path) && @is_file($full_path)) {
            $has_image = true;
            $feature_src = $feature . '?v=' . filemtime($full_path);
        }
    }

    ob_start();
    ?>
    <article class="blog-card">
        <div class="blog-card-media">
            <?php if ($has_image): ?>
                <img src="<?= e($feature_src) ?>"
                     alt="<?= e($post['image_alt'] ?: $post['title']) ?>"
                     loading="lazy">
            <?php else: ?>
                <div class="blog-card-media-placeholder">925 SILVER</div>
            <?php endif; ?>
        </div>

        <div class="blog-card-body">
            <h2 class="blog-card-title">
                <a href="blog_post.php?slug=<?= urlencode($post['slug']) ?>"><?= e($post['title']) ?></a>
            </h2>

            <p class="blog-card-desc"><?= e($excerpt) ?></p>

            <div class="blog-card-footer">
                <a href="blog_post.php?slug=<?= urlencode($post['slug']) ?>" class="blog-card-readmore">Read More <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </article>
    <?php
    return (string)ob_get_clean();
}

$where = " WHERE status = 'published' ";
$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts $where");
$total_stmt->execute();
$total_posts = (int)$total_stmt->fetchColumn();

$offset = max(0, (int)($_GET['offset'] ?? 0));
$list_stmt = $pdo->prepare(
    "SELECT * FROM blog_posts $where
     ORDER BY COALESCE(published_at, created_at) DESC, id DESC
     LIMIT :limit OFFSET :offset"
);
$list_stmt->bindValue(':limit', BLOGS_PER_LOAD, PDO::PARAM_INT);
$list_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$list_stmt->execute();
$posts = $list_stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['load_more']) && $_GET['load_more'] === '1') {
    $loaded_count = min($total_posts, $offset + count($posts));
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode([
        'html' => implode('', array_map('render_blog_card', $posts)),
        'loaded' => $loaded_count,
        'total' => $total_posts,
        'has_more' => $loaded_count < $total_posts,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$initial_count = count($posts);
$page_title = 'Latest Silver Jewellery Blogs - Silvee 925';
require_once __DIR__ . '/store_header.php';
?>

<link rel="stylesheet" href="assets/blog.css?v=<?= file_exists(__DIR__ . '/assets/blog.css') ? filemtime(__DIR__ . '/assets/blog.css') : '1.0' ?>">

<div class="blog-page-container">
<div class="blog-inner">

    <header class="blog-header">
        <h1>Latest Silver Jewellery Blogs</h1>
        <p>Explore silver jewellery designs, styling ideas, care tips, and wholesale buying guides.</p>
    </header>

    <?php if (!empty($posts)): ?>
        <div class="blog-grid" id="all-blogs">
            <?php foreach ($posts as $post): ?>
                <?= render_blog_card($post) ?>
            <?php endforeach; ?>
        </div>

        <div class="blog-load-more-wrap" id="blog-load-more-controls">
            <?php if ($initial_count < $total_posts): ?>
                <button type="button" class="blog-view-all-button" id="view-all-blogs" aria-controls="all-blogs">
                    View All Blogs
                </button>
            <?php endif; ?>
            <p class="blog-count" id="blog-count" aria-live="polite">Showing <?= $initial_count ?> of <?= $total_posts ?> blogs</p>
            <p class="blog-all-viewed<?= $initial_count < $total_posts ? ' is-hidden' : '' ?>" id="blog-all-viewed">You have viewed all blogs.</p>
        </div>
    <?php else: ?>
        <div style="text-align:center; padding:5rem 1rem; color:#6b7280;">
            <p style="font-size:1.05rem;">No articles found.</p>
        </div>
    <?php endif; ?>

</div><!-- /.blog-inner -->
</div><!-- /.blog-page-container -->

<?php if ($initial_count < $total_posts): ?>
<script>
(function () {
    const grid = document.getElementById('all-blogs');
    const button = document.getElementById('view-all-blogs');
    const counter = document.getElementById('blog-count');
    const completeMessage = document.getElementById('blog-all-viewed');
    let loaded = <?= $initial_count ?>;
    let isLoading = false;

    button.addEventListener('click', async function () {
        if (isLoading) return;

        isLoading = true;
        button.disabled = true;
        button.textContent = 'Loading...';

        try {
            const url = new URL(window.location.href);
            url.search = '';
            url.searchParams.set('load_more', '1');
            url.searchParams.set('offset', String(loaded));

            const response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });
            if (!response.ok) throw new Error('Unable to load blogs');

            const data = await response.json();
            grid.insertAdjacentHTML('beforeend', data.html);
            loaded = data.loaded;
            counter.textContent = `Showing ${data.loaded} of ${data.total} blogs`;

            if (!data.has_more) {
                button.remove();
                completeMessage.classList.remove('is-hidden');
                return;
            }

            button.disabled = false;
            button.textContent = 'View All Blogs';
        } catch (error) {
            button.disabled = false;
            button.textContent = 'View All Blogs';
            counter.textContent = 'Unable to load more blogs. Please try again.';
        } finally {
            isLoading = false;
        }
    });
}());
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/store_footer.php'; ?>
