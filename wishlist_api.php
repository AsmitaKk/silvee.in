<?php
/**
 * Wishlist API — AJAX endpoint for add/remove/list wishlist items
 */
require_once "config.php";
require_once "functions.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Must be logged in as a customer
if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please sign in or register to use the wishlist.', 'wishlist_count' => 0]);
    exit;
}

$customer_id = (int) $_SESSION['customer_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- ADD to wishlist ---
if ($action === 'add') {
    $design_id = (int) ($_POST['design_id'] ?? 0);
    if ($design_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid design ID.']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO customer_wishlists (customer_id, design_id) VALUES (?, ?)");
        $stmt->execute([$customer_id, $design_id]);

        $count = $pdo->prepare("SELECT COUNT(*) FROM customer_wishlists WHERE customer_id = ?");
        $count->execute([$customer_id]);
        $wishlist_count = (int) $count->fetchColumn();

        echo json_encode(['success' => true, 'message' => 'Added to Wishlist!', 'wishlist_count' => $wishlist_count, 'action' => 'added']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Could not add to wishlist.']);
    }
    exit;
}

// --- REMOVE from wishlist ---
if ($action === 'remove') {
    $design_id = (int) ($_POST['design_id'] ?? 0);
    if ($design_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid design ID.']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM customer_wishlists WHERE customer_id = ? AND design_id = ?");
        $stmt->execute([$customer_id, $design_id]);

        $count = $pdo->prepare("SELECT COUNT(*) FROM customer_wishlists WHERE customer_id = ?");
        $count->execute([$customer_id]);
        $wishlist_count = (int) $count->fetchColumn();

        echo json_encode(['success' => true, 'message' => 'Removed from Wishlist', 'wishlist_count' => $wishlist_count, 'action' => 'removed']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Could not remove from wishlist.']);
    }
    exit;
}

// --- LIST wishlist IDs (GET request, used to pre-fill hearts) ---
if ($action === 'list') {
    try {
        $stmt = $pdo->prepare("SELECT design_id FROM customer_wishlists WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $count = count($ids);
        echo json_encode(['success' => true, 'wishlist_ids' => $ids, 'wishlist_count' => $count]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'wishlist_ids' => [], 'wishlist_count' => 0]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
