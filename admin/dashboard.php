<?php

// Start the session and include necessary files
require_once __DIR__ . '/../config/db.php';
startSecureSession();

if (!isset($_SESSION['user']['userid']) || empty($_SESSION['user']['isAdmin'])) {
  http_response_code(403);
?>

  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden - Access Denied</title>
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
