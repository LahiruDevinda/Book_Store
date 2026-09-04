<?php

// Start the session and include necessary files
require_once __DIR__ . '/../config/db.php';
startSecureSession();

if (!isset($_SESSION['user']['userid']) || empty($_SESSION['user']['isAdmin'])) {
  http_response_code(403);
}




