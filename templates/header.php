<?php
if (!isset($page_title)) $page_title = "Inventory";
require_once __DIR__ . "/../config.php";

// Load role management functions
require_once __DIR__ . "/../role_management.php";

$current = basename($_SERVER['PHP_SELF']);

// Get current user role for navigation with error handling
$user_role = null;
$is_sales_employee = false;
$is_admin = false;

if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
    $user_role = get_user_role($pdo, $_SESSION['user_id']);
    $is_sales_employee = ($user_role === 'sales_employee');
    $is_admin = ($user_role === 'admin');
}

// Get active carts count
$active_carts_count = 0;
try {
    $stmt_carts = $pdo->query("SELECT COUNT(DISTINCT customer_id) FROM customer_carts");
    $active_carts_count = (int)$stmt_carts->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= e($page_title) ?> - Jewellery Inventory</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="d-flex" style="min-height: 100vh;">
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- SIDEBAR -->
  <nav class="sidebar d-flex flex-column p-3" id="sidebarMenu">
    <div class="d-flex align-items-center mb-4">
      <?php
      // Fetch custom brand logo if configured
      $admin_brand_logo = '';
      try {
          $stmt_logo = $pdo->query("SELECT setting_value FROM theme_settings WHERE setting_key = 'brand_logo'");
          if ($row_logo = $stmt_logo->fetch()) {
              if (!empty($row_logo['setting_value']) && file_exists(__DIR__ . '/../' . $row_logo['setting_value'])) {
                  $admin_brand_logo = $row_logo['setting_value'];
              }
          }
      } catch (Exception $e) {
          // Fail-safe
      }
      ?>
      <?php if (!empty($admin_brand_logo)): ?>
        <img src="<?= htmlspecialchars($admin_brand_logo) ?>" alt="Logo" class="logo-img me-2" style="max-height: 35px; width: auto; object-fit: contain;">
      <?php elseif (file_exists(__DIR__ . '/../assets/logo.png')): ?>
        <img src="assets/logo.png" alt="Logo" class="logo-img me-2">
      <?php else: ?>
        <div class="rounded-circle bg-light me-2" style="width:32px;height:32px;"></div>
      <?php endif; ?>
      <div>
        <div class="brand-title">Silvee 925
        </div>
        <small class="text-secondary">
          <?= $is_admin ? 'Inventory Admin' : 'Sales Terminal' ?>
        </small>
        <?php if ($is_admin): ?>
        <div class="badge bg-primary ms-2" style="font-size: 0.7rem;">Admin</div>
        <?php else: ?>
        <div class="badge bg-info ms-2" style="font-size: 0.7rem;">Sales</div>
        <?php endif; ?>
      </div>
    </div>

    <ul class="nav nav-pills flex-column mb-auto">
      <!-- Dashboard - Available to all -->
      <li class="nav-item">
        <a href="dashboard.php"
           class="nav-link text-white <?= $current === 'dashboard.php' ? 'active' : '' ?>">
          <i class="bi bi-house me-2"></i> Dashboard
        </a>
      </li>
      
      <!-- Store Orders -->
      <li class="nav-item mb-1">
        <a href="orders.php"
           class="nav-link text-white <?= $current === 'orders.php' ? 'active bg-primary' : 'fw-semibold' ?>">
          <i class="bi bi-globe me-2"></i> Web Store Orders
        </a>
      </li>
      <li class="nav-item border-bottom pb-2 mb-2">
        <a href="active_carts.php"
           class="nav-link text-white <?= $current === 'active_carts.php' ? 'active bg-success' : 'fw-semibold' ?> d-flex justify-content-between align-items-center">
          <div><i class="bi bi-cart4 me-2"></i> Live Shopping Carts</div>
          <?php if ($active_carts_count > 0): ?>
            <span class="badge bg-danger rounded-pill"><?= $active_carts_count ?></span>
          <?php endif; ?>
        </a>
      </li>
      
      <!-- Sales - Available to all -->
      <li class="nav-item">
        <a href="sales.php"
           class="nav-link text-white <?= $current === 'sales.php' ? 'active' : '' ?>">
          <i class="bi bi-currency-dollar me-2"></i> Sales
        </a>
      </li>
      <?php if ($is_admin): ?>
      <li class="nav-item">
        <a href="admin_customers.php"
           class="nav-link text-white <?= $current === 'admin_customers.php' ? 'active' : 'fw-semibold' ?>">
          <i class="bi bi-people-fill me-2"></i> Registered Customers
        </a>
      </li>
      <li class="nav-item border-bottom pb-2 mb-2">
        <a href="admin_invoices.php"
           class="nav-link text-white <?= $current === 'admin_invoices.php' ? 'active' : 'fw-semibold' ?>">
          <i class="bi bi-file-earmark-pdf-fill me-2"></i> B2B Invoices
        </a>
      </li>
      <?php endif; ?>
      
      <li class="nav-item mb-2 border-bottom pb-2">
        <a href="salesperson_report.php"
           class="nav-link text-white <?= $current === 'salesperson_report.php' ? 'active' : '' ?> fw-medium">
          <i class="bi bi-person-lines-fill me-2"></i> Staff Performance
        </a>
      </li>
      
      <?php if ($is_admin): ?>
      <li class="nav-item mb-3">
        <a class="nav-link text-white fw-bold rounded" style="background: rgba(255,255,255,0.05);" data-bs-toggle="collapse" href="#reportsMenu" role="button" aria-expanded="<?= in_array($current, ['report_sales.php', 'report_purchases.php', 'report_stock.php', 'report_customers.php']) ? 'true' : 'false' ?>">
          <i class="bi bi-file-earmark-bar-graph-fill me-2"></i> Reports Engine <i class="bi bi-chevron-down float-end mt-1 text-white" style="font-size:0.75rem;"></i>
        </a>
        <div class="collapse <?= in_array($current, ['report_sales.php', 'report_purchases.php', 'report_stock.php', 'report_customers.php']) ? 'show' : '' ?>" id="reportsMenu">
          <ul class="nav flex-column ms-2 mt-2 border-start ps-2" style="font-size:0.95rem;">
            <li class="nav-item mb-1">
              <a href="report_sales.php" class="nav-link text-white <?= $current === 'report_sales.php' ? 'fw-bold bg-primary' : 'fw-medium' ?> py-1" style="border-radius: 4px;">
                <i class="bi bi-graph-up me-2"></i> Sales Report
              </a>
            </li>
            <li class="nav-item mb-1">
              <a href="report_purchases.php" class="nav-link text-white <?= $current === 'report_purchases.php' ? 'fw-bold bg-primary' : 'fw-medium' ?> py-1" style="border-radius: 4px;">
                <i class="bi bi-cart-plus me-2"></i> Purchase Config
              </a>
            </li>
            <li class="nav-item mb-1">
              <a href="report_stock.php" class="nav-link text-white <?= $current === 'report_stock.php' ? 'fw-bold bg-primary' : 'fw-medium' ?> py-1" style="border-radius: 4px;">
                <i class="bi bi-boxes me-2"></i> Core Inventory
              </a>
            </li>
            <li class="nav-item">
              <a href="report_customers.php" class="nav-link text-white <?= $current === 'report_customers.php' ? 'fw-bold bg-primary' : 'fw-medium' ?> py-1" style="border-radius: 4px;">
                <i class="bi bi-person-badge-fill me-2"></i> Customer DB
              </a>
            </li>
          </ul>
        </div>
      </li>
      <?php endif; ?>
      
      <!-- Products - Admin only -->
      <?php if ($is_admin): ?>
      <li class="nav-item">
        <a href="products.php"
           class="nav-link text-white <?= $current === 'products.php' || $current === 'product_form.php' ? 'active' : '' ?>">
          <i class="bi bi-box-seam me-2"></i> Products
        </a>
      </li>
      
      <li class="nav-item">
        <a href="restock_report.php"
           class="nav-link text-white <?= $current === 'restock_report.php' ? 'active' : '' ?>">
          <i class="bi bi-exclamation-triangle-fill me-2"></i> Low Stock Report
        </a>
      </li>
      
      <!-- Categories - Admin only -->
      <li class="nav-item">
        <a href="categories.php"
           class="nav-link text-white <?= $current === 'categories.php' ? 'active' : '' ?>">
          <i class="bi bi-tags me-2"></i> Categories
        </a>
      </li>
      
      <!-- Stock Movements - Admin only -->
      <li class="nav-item">
        <a href="stock_movements.php"
           class="nav-link text-white <?= $current === 'stock_movements.php' ? 'active' : '' ?>">
          <i class="bi bi-arrow-left-right me-2"></i> Stock Movements
        </a>
      </li>
      
      <!-- Masters - Admin only -->
      <li class="nav-item">
        <a href="masters.php"
           class="nav-link text-white <?= $current === 'masters.php' ? 'active' : '' ?>">
          <i class="bi bi-sliders me-2"></i> Masters
        </a>
      </li>

      <li class="nav-item">
        <a href="blog_management.php?new=1"
           class="nav-link text-white <?= $current === 'blog_management.php' ? 'active' : 'fw-semibold' ?>">
          <i class="bi bi-journal-text me-2"></i> Blogs
        </a>
      </li>

      <!-- Theme Settings - Admin only -->
      <li class="nav-item">
        <a href="theme_settings.php"
           class="nav-link text-white <?= $current === 'theme_settings.php' ? 'active' : 'fw-semibold' ?>">
          <i class="bi bi-palette-fill me-2"></i> Theme Settings
        </a>
      </li>
      <?php endif; ?>
    </ul>

    <hr>
    <div class="dropdown">
      <span class="text-secondary small d-block mb-1">Logged in as</span>
      <div class="d-flex align-items-center justify-content-between">
        <span class="fw-semibold small">
          <?= isset($_SESSION['user_name']) ? e($_SESSION['user_name']) : 'User' ?>
        </span>
        <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
      </div>
    </div>
  </nav>

  <!-- MAIN CONTENT AREA -->
  <main class="flex-grow-1 d-flex flex-column">
    <header class="bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <button class="btn btn-outline-secondary btn-sm me-3 d-lg-none" type="button" id="mobileMenuBtn" aria-label="Toggle sidebar menu">
          <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="mb-0"><?= e($page_title) ?></h5>
      </div>
    </header>
    <div class="flex-grow-1 p-4">
