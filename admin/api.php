<?php

// Start a secure session
require_once __DIR__ . '/../config/db.php';
startSecureSession();

// Strict Admin Verification
if (!isset($_SESSION['user']['userid']) || empty($_SESSION['user']['isAdmin'])) {
    sendJsonResponse(['success' => false, 'message' => 'Forbidden: Administrator privileges required.'], 403);
}