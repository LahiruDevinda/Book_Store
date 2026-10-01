<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

$user = $_SESSION['user'] ?? [];
$isShopkeeper = (($user['role'] ?? '') === 'shopkeeper') || !empty($user['isAdmin']);

if (!isset($user['userid']) || !$isShopkeeper) {
    http_response_code(403);
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>403 Forbidden - Access Denied</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body style="display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--bg-canvas);">
        <div style="max-width:440px; text-align:center; padding:40px; background:#fff; border-radius:12px; border:1px solid var(--border-color);">
            <div style="font-size:48px; margin-bottom:16px;">🔒</div>
            <h2 style="font-size:24px; font-weight:700; margin-bottom:8px;">403 Access Denied</h2>
            <p style="color:var(--text-muted); font-size:15px; margin-bottom:24px;">Shopkeeper privileges are required to view this dashboard.</p>
            <a href="../index.php" class="btn btn-primary" style="text-decoration:none; padding:10px 24px;">Return to Storefront</a>
        </div>
    </body>
    </html>
<?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopkeeper Dashboard - BookStore</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">
    <header class="admin-header">
        <div class="header-inner container">
            <div class="brand-area">
                <a href="dashboard.php" class="brand-logo">
                    <span class="brand-icon">📚</span>
                    <span class="brand-name">BookStore<span class="brand-dot">.</span></span>
                    <span class="admin-badge">Shopkeeper</span>
                </a>
            </div>
            <div class="admin-nav-tabs">
                <button class="admin-tab active" data-tab="orders">Orders Fulfillment</button>
                <button class="admin-tab" data-tab="inventory">Inventory & Change Requests</button>
            </div>
            <div class="admin-user-menu">
                <span class="admin-username"><?= htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></span>
                <a href="../index.php" class="btn btn-secondary btn-sm" style="text-decoration:none;">Storefront</a>
                <button id="shopLogoutBtn" class="btn btn-secondary btn-sm">Sign Out</button>
            </div>
        </div>
    </header>

    <main class="container admin-container">
        <div id="shopAlert" class="alert-box hidden"></div>

        <!-- Orders Fulfillment Tab -->
        <section id="tab-orders" class="admin-panel active">
            <div class="panel-header">
                <div>
                    <h1 class="panel-title">Order Fulfillment</h1>
                    <p class="panel-subtitle">Manage customer orders and update live delivery statuses.</p>
                </div>
            </div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Delivery Address</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Update Delivery</th>
                            </tr>
                        </thead>
                        <tbody id="shopOrdersTableBody">
                            <tr><td colspan="8" class="text-center py-4">Loading orders...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Inventory & Requests Tab -->
        <section id="tab-inventory" class="admin-panel">
            <div class="panel-header">
                <div>
                    <h1 class="panel-title">Catalog Inventory & Change Requests</h1>
                    <p class="panel-subtitle">View stock and submit price/stock update requests for admin approval.</p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="openRequestModal('add')">+ Request New Book</button>
                </div>
            </div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Cover</th>
                                <th>Title & ISBN</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="shopInventoryTableBody">
                            <tr><td colspan="5" class="text-center py-4">Loading inventory...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <!-- Request Modal -->
    <div id="requestModal" class="modal-overlay hidden">
        <div class="modal-dialog" style="max-width:480px;">
            <div class="modal-header">
                <h3 class="modal-title" id="requestModalTitle">Request Inventory Change</h3>
                <button class="modal-close" onclick="closeRequestModal()">&times;</button>
            </div>
            <form id="inventoryRequestForm" class="modal-body">
                <input type="hidden" id="reqBookId">
                <input type="hidden" id="reqActionType" value="add">
                <div class="form-group">
                    <label class="form-label">Book Title *</label>
                    <input type="text" id="reqTitle" class="form-control" required placeholder="Book Title">
                </div>
                <div class="form-group-row">
                    <div class="form-group">
                        <label class="form-label">ISBN *</label>
                        <input type="text" id="reqIsbn" class="form-control" required placeholder="978-...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price ($) *</label>
                        <input type="number" step="0.01" min="0.01" id="reqPrice" class="form-control" required placeholder="29.99">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock Quantity *</label>
                        <input type="number" min="0" id="reqStock" class="form-control" required placeholder="10">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Cover Image URL</label>
                    <input type="url" id="reqCover" class="form-control" placeholder="https://images.unsplash.com/...">
                </div>
                <div class="modal-footer" style="padding:0; margin-top:20px; display:flex; justify-content:flex-end; gap:8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeRequestModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Submit Request to Admin</button>
                </div>
            </form>
        </div>
    </div>

    <script src="shopkeeper.js"></script>
</body>
</html>