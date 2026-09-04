<?php

// Start the session and include necessary files
require_once __DIR__ . '/../config/db.php';
startSecureSession();

if (!isset($_SESSION['user']['userid']) || empty($_SESSION['user']['isAdmin'])) {
  http_response_code(403);
?>
  <!-- 403 Forbidden - Access Denied -->
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden - Access Denied</title>
    <link rel="stylesheet" href="../assets/css/style.css">
  </head>

  <body style="display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--bg-canvas);">
    <div style="max-width:440px; text-align:center; padding:40px; background:#fff; border-radius:12px; border:1px solid var(--border-color); box-shadow:0 4px 20px rgba(0,0,0,0.05);">
      <div style="font-size:48px; margin-bottom:16px;">🔒</div>
      <h2 style="font-size:24px; font-weight:700; margin-bottom:8px; color:var(--text-main);">403 Access Denied</h2>
      <p style="color:var(--text-muted); font-size:15px; margin-bottom:24px; line-height:1.5;">
        Administrator privileges are strictly required to view this control center.
      </p>
      <a href="../index.php" class="btn btn-primary" style="display:inline-block; text-decoration:none; padding:10px 24px;">Return to Storefront</a>
    </div>
  </body>

  </html>
<?php
  exit;
}

// If the user is an admin, retrieve their information
$adminUser = $_SESSION['user'];
?>

<!-- Admin Dashboard -->
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Control Center - BookStore</title>
  <!-- Preconnect to Google Fonts for performance optimization -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <!-- Preconnect to Google Fonts with crossorigin for better performance -->
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- Link to Google Fonts for Plus Jakarta Sans and Playfair Display fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
  <!-- Link to the main stylesheet for the admin dashboard -->
  <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="admin-body">
  <!-- Admin navigation -->
  <header class="admin-header">
    <div class="header-inner container">

      <!-- Brand Area -->
      <div class="brand-area">
        <a href="dashboard.php" class="brand-logo">
          <span class="brand-icon">📚</span>
          <span class="brand-name">BookStore<span class="brand-dot">.</span></span>
          <span class="admin-badge">Admin</span>
        </a>
      </div>

      <!-- Navigation Tabs -->
      <div class="admin-nav-tabs">
        <button class="admin-tab active" data-tab="overview">Overview</button>
        <button class="admin-tab" data-tab="inventory">Inventory</button>
        <button class="admin-tab" data-tab="orders">Orders Audit</button>
        <button class="admin-tab" data-tab="taxonomies">Authors & Genres</button>
      </div>

      <!-- User Menu -->
      <div class="admin-user-menu">
        <!-- User Profile -->
        <span class="admin-username">
          <?= htmlspecialchars($adminUser['firstName'] . ' ' . $adminUser['lastName']); ?>
        </span>
        <a href="../index.php" class="btn btn-secondary btn-sm">Storefront</a>
        <button id="adminLogoutBtn" class="btn btn-secondary btn-sm">Sign Out</button>
      </div>
    </div>
  </header>

  <!-- Admin Main Content -->
  <main class="container admin-container"></main>

  <!-- Add New Book -->
  <div id="addBookModal" class="modal-overlay hidden"></div>

  <!-- Edit Book Modal -->
  <div id="editBookModal" class="modal-overlay hidden"></div>
</body>

</html>