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
        <a href="../index.php" class="btn btn-secondary btn-sm" style="text-decoration:none;">Storefront</a>
        <button id="adminLogoutBtn" class="btn btn-secondary btn-sm">Sign Out</button>
      </div>
    </div>
  </header>

  <!-- Admin Main Content -->
  <main class="container admin-container">

    <!-- Notification Banner -->
    <div id="adminAlert" class="alert-box hidden"></div>

    <!-- ==================== OVERVIEW TAB ==================== -->
    <section id="tab-overview" class="admin-panel active">
      <!-- Panel Header -->
      <div class="panel-header">
        <div>
          <h1 class="panel-title">System Overview</h1>
          <p class="panel-subtitle">Real-time summary of catalog inventory and customer orders.</p>
        </div>
      </div>
      <!-- Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Total Books</div>
          <div class="stat-value" id="statTotalBooks">-</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total Units in Stock</div>
          <div class="stat-value" id="statTotalStock">-</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Orders Completed</div>
          <div class="stat-value" id="statTotalOrders">-</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total Sales Volume</div>
          <div class="stat-value" id="statTotalRevenue">$0.00</div>
        </div>
      </div>
    </section>

    <!-- ==================== INVENTORY TAB ==================== -->
    <section id="tab-inventory" class="admin-panel">
      <!-- Panel Header -->
      <div class="panel-header">
        <div>
          <h1 class="panel-title">Catalog Inventory</h1>
          <p class="panel-subtitle">Manage books, adjust pricing, and balance warehouse stock.</p>
        </div>
        <div>
          <button class="btn btn-primary" id="openAddBookModalBtn">+ Add New Book</button>
        </div>
      </div>

      <!-- Inventory Table -->
      <div class="table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Cover</th>
                <th>Title & ISBN</th>
                <th>Authors</th>
                <th>Genres</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="inventoryTableBody">
              <tr>
                <td colspan="7" class="text-center py-4">Loading catalog...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ==================== ORDERS AUDIT TAB ==================== -->
    <section id="tab-orders" class="admin-panel">
      <!-- Panel Header -->
      <div class="panel-header">
        <div>
          <h1 class="panel-title">Customer Orders Audit</h1>
          <p class="panel-subtitle">Historical records with permanently locked purchase unit prices.</p>
        </div>
      </div>

      <!-- Orders Table -->
      <div class="table-card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Order #</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Delivery Address</th>
                <th>Items & Locked Prices</th>
                <th>Subtotal</th>
                <th>Payment</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="ordersTableBody">
              <tr>
                <td colspan="8" class="text-center py-4">Loading orders...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ==================== AUTHORS & GENRES TAB ==================== -->
    <section id="tab-taxonomies" class="admin-panel">
      <!-- Panel Header -->
      <div class="panel-header">
        <div>
          <h1 class="panel-title">Taxonomies Management</h1>
          <p class="panel-subtitle">Manage authors and literary genre classifications.</p>
        </div>
      </div>

      <!-- Taxonomy Management -->
      <div class="two-col-grid">
        <!-- Authors Card -->
        <div class="card p-4">
          <h3 style="font-size:18px; font-weight:700; margin-bottom:16px;">Authors</h3>
          <form id="addAuthorForm" style="display:flex; flex-direction:column; gap:10px; margin-bottom:20px;">
            <input type="text" id="newAuthorName" placeholder="Author Full Name" class="form-control" required>
            <textarea id="newAuthorBio" placeholder="Short Biography" class="form-control" rows="2"></textarea>
            <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-start;">+ Add Author</button>
          </form>
          <div id="authorsList" style="max-height:300px; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">
            <!-- Injected via JS -->
          </div>
        </div>

        <!-- Genres Card -->
        <div class="card p-4">
          <h3 style="font-size:18px; font-weight:700; margin-bottom:16px;">Genres</h3>
          <form id="addGenreForm" style="display:flex; gap:10px; margin-bottom:20px;">
            <input type="text" id="newGenreName" placeholder="New Genre Name" class="form-control" required>
            <button type="submit" class="btn btn-primary btn-sm">+ Add Genre</button>
          </form>
          <div id="genresList" style="max-height:300px; overflow-y:auto; display:flex; flex-wrap:wrap; gap:8px;">
            <!-- Injected via JS -->
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- Add New Book -->
  <div id="addBookModal" class="modal-overlay hidden">
    <div class="modal-dialog" style="max-width:560px;">

      <!-- Modal Header -->
      <div class="modal-header">
        <h3 class="modal-title">Add New Book</h3>
        <button class="modal-close" id="closeAddBookModal">&times;</button>
      </div>

      <!-- Modal Body -->
      <form id="addBookForm" class="modal-body">
        <div class="form-group">
          <label class="form-label">Book Title *</label>
          <input type="text" name="title" class="form-control" required placeholder="e.g. Design Patterns">
        </div>
        <div class="form-group-row">
          <div class="form-group">
            <label class="form-label">ISBN *</label>
            <input type="text" name="ISBN" class="form-control" required placeholder="e.g. 978-0201633610">
          </div>
          <div class="form-group">
            <label class="form-label">Price ($) *</label>
            <input type="number" step="0.01" min="0.01" name="price" class="form-control" required placeholder="49.99">
          </div>
          <div class="form-group">
            <label class="form-label">Stock Quantity *</label>
            <input type="number" min="0" name="stockQuantity" class="form-control" required placeholder="10">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Cover Image URL</label>
          <input type="url" name="coverImageUrl" class="form-control" placeholder="https://images.unsplash.com/...">
        </div>
        <div class="form-group">
          <label class="form-label">Authors (Select all that apply)</label>
          <div id="bookModalAuthors" class="checkbox-select-box"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Genres (Select all that apply)</label>
          <div id="bookModalGenres" class="checkbox-select-box"></div>
        </div>
        <div class="modal-footer" style="padding:0; margin-top:20px;">
          <button type="button" class="btn btn-secondary" id="cancelAddBookBtn">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Book</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Book Modal -->
  <div id="editBookModal" class="modal-overlay hidden"></div>
</body>

</html>