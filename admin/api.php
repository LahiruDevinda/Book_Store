<?php

// Start a secure session
require_once __DIR__ . '/../config/db.php';
startSecureSession();

// Strict Admin Verification
if (!isset($_SESSION['user']['userid']) || empty($_SESSION['user']['isAdmin'])) {
    sendJsonResponse(['success' => false, 'message' => 'Forbidden: Administrator privileges required.'], 403);
}

$pdo = getDBConnection();
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;
$action = $input['action'] ?? ($_GET['action'] ?? '');