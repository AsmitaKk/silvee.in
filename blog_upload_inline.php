<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/role_management.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) && empty($_SESSION['admin_logged_in']) && empty($_SESSION['role'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No image uploaded']);
    exit;
}

$blogs_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'blogs';
@mkdir($blogs_dir, 0755, true);

$upload = blog_file_upload($_FILES['image'], $blogs_dir, 10485760); // 10MB max

if (!$upload['ok']) {
    echo json_encode(['success' => false, 'message' => $upload['message']]);
    exit;
}

$normalized_dir = str_replace('\\', '/', __DIR__) . '/';
$normalized_path = str_replace('\\', '/', $upload['path']);
$relative_path = str_replace($normalized_dir, '', $normalized_path);

echo json_encode([
    'success' => true,
    'url' => $relative_path
]);
