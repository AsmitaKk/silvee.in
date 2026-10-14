<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/role_management.php';

require_admin($pdo);

$success_message = '';
$errors = [];

$redirect_page = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : 'blog_management.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $redirect_page");
    exit;
}

$post_id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : 0;
$action = $_POST['action'] ?? 'save_draft';

// Deleting only needs the post ID. Handle it before validating the edit form,
// since delete buttons in the posts table do not submit title/content fields.
if ($action === 'delete') {
    if ($post_id > 0) {
        $delete_stmt = $pdo->prepare('DELETE FROM blog_posts WHERE id = ?');
        $delete_stmt->execute([$post_id]);
        $_SESSION['blog_notice'] = 'Blog post deleted successfully.';
        header("Location: $redirect_page?deleted=1");
        exit;
    }

    header("Location: $redirect_page");
    exit;
}

$title = trim((string)($_POST['title'] ?? ''));
$slug = trim((string)($_POST['slug'] ?? ''));
$short_description = trim((string)($_POST['short_description'] ?? ''));
$category = trim((string)($_POST['category'] ?? 'General'));
$image_alt = trim((string)($_POST['image_alt'] ?? ''));
$seo_title = trim((string)($_POST['seo_title'] ?? ''));
$meta_description = trim((string)($_POST['meta_description'] ?? ''));
$article_style = trim((string)($_POST['article_style'] ?? 'classic'));
$article_style = in_array($article_style, ['classic', 'editorial', 'luxury', 'modern'], true) ? $article_style : 'classic';
$content = trim((string)($_POST['content'] ?? ''));
$content = blog_sanitize_html($content);

if ($title === '') {
    $errors[] = 'Please enter a blog title.';
}

if ($slug === '') {
    $slug = blog_slugify($title);
}
$slug = blog_slugify($slug);
if ($slug === '') {
    $errors[] = 'Please provide a valid URL slug.';
}

if ($category === '') {
    $category = 'General';
}

if ($image_alt === '') {
    $image_alt = $title;
}

$featured_image = '';
if (!empty($_FILES['featured_image']['name'])) {
    $blogs_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'blogs';
    @mkdir($blogs_dir, 0755, true);
    // Allow up to 10MB
    $upload = blog_file_upload($_FILES['featured_image'], $blogs_dir, 10485760);
    if (!$upload['ok']) {
        $errors[] = $upload['message'];
    } else {
        $normalized_dir = str_replace('\\', '/', __DIR__) . '/';
        $normalized_path = str_replace('\\', '/', $upload['path']);
        $featured_image = str_replace($normalized_dir, '', $normalized_path);
    }
}

$article_uploads = [];
if (!empty($_FILES['article_uploads']['name'])) {
    $blogs_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'blogs';
    @mkdir($blogs_dir, 0755, true);
    $total = count($_FILES['article_uploads']['name']);
    for ($i = 0; $i < $total; $i++) {
        if (empty($_FILES['article_uploads']['name'][$i])) {
            continue;
        }
        $file = [
            'name' => $_FILES['article_uploads']['name'][$i],
            'type' => $_FILES['article_uploads']['type'][$i],
            'tmp_name' => $_FILES['article_uploads']['tmp_name'][$i],
            'error' => $_FILES['article_uploads']['error'][$i],
            'size' => $_FILES['article_uploads']['size'][$i],
        ];
        $upload = blog_file_upload($file, $blogs_dir, 10485760);
        if (!$upload['ok']) {
            $errors[] = $upload['message'];
            continue;
        }
        $norm_dir = str_replace('\\', '/', __DIR__) . '/';
        $norm_path = str_replace('\\', '/', $upload['path']);
        $article_uploads[] = str_replace($norm_dir, '', $norm_path);
    }
}

if ($content === '') {
    $errors[] = 'Please add content to the article.';
}

if (empty($errors)) {
    $status = ($action === 'publish') ? 'published' : 'draft';
    if ($action === 'unpublish') {
        $status = 'draft';
    }
    $check_stmt = $pdo->prepare('SELECT id FROM blog_posts WHERE slug = ? AND id != ? LIMIT 1');
    $check_stmt->execute([$slug, $post_id]);
    if ($check_stmt->fetch()) {
        $base_slug = $slug;
        $suffix = 1;
        do {
            $slug = $base_slug . '-' . $suffix;
            $suffix++;
            $check_stmt->execute([$slug, $post_id]);
        } while ($check_stmt->fetch());
    }

    $published_at = null;
    if ($status === 'published' && ($action !== 'save_draft' && $action !== 'preview')) {
        $published_at = date('Y-m-d H:i:s');
    }

    if ($post_id > 0) {
        $existing = $pdo->prepare('SELECT featured_image, content FROM blog_posts WHERE id = ? LIMIT 1');
        $existing->execute([$post_id]);
        $existing_row = $existing->fetch(PDO::FETCH_ASSOC);

        if ($featured_image === '' && !empty($existing_row['featured_image'])) {
            $featured_image = $existing_row['featured_image'];
        }

        $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, slug = ?, short_description = ?, category = ?, featured_image = ?, image_alt = ?, content = ?, article_style = ?, status = ?, seo_title = ?, meta_description = ?, published_at = COALESCE(?, published_at), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([
            $title,
            $slug,
            $short_description,
            $category,
            $featured_image,
            $image_alt,
            $content,
            $article_style,
            $status,
            $seo_title ?: $title,
            $meta_description ?: substr(strip_tags($short_description ?: $content), 0, 160),
            $published_at,
            $post_id,
        ]);
        $saved_id = $post_id;
    } else {
        $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, short_description, category, featured_image, image_alt, content, article_style, status, seo_title, meta_description, published_at, author_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $title,
            $slug,
            $short_description,
            $category,
            $featured_image,
            $image_alt,
            $content,
            $article_style,
            $status,
            $seo_title ?: $title,
            $meta_description ?: substr(strip_tags($short_description ?: $content), 0, 160),
            $published_at,
            $_SESSION['user_id'] ?? null,
        ]);
        $saved_id = (int)$pdo->lastInsertId();
    }

    if (!empty($article_uploads)) {
        $content = blog_add_inline_images_to_content($content, $article_uploads);
        $update_content = $pdo->prepare('UPDATE blog_posts SET content = ? WHERE id = ?');
        $update_content->execute([$content, $saved_id]);
    }

    if ($action === 'preview') {
        header('Location: blog_post.php?preview=1&id=' . $saved_id);
        exit;
    }

    $status_label = ($status === 'published') ? 'published' : 'saved as draft';
    $_SESSION['blog_notice'] = 'Blog post ' . $status_label . ' successfully.';
    header("Location: $redirect_page?edit=" . $saved_id);
    exit;
}

$_SESSION['blog_error'] = implode(' ', $errors);
header("Location: $redirect_page" . ($post_id > 0 ? '?edit=' . $post_id : ''));
exit;
