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
  <body>
    
  </body>
  </html>
  <?php
  exit;
}
