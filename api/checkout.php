<?php

require_once __DIR__ . '/../config/db.php';
startSecureSession();

if (!isset($_SESSION['user']['userid'])) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Please log in or register to place your order.',
        'requireAuth' => true
    ], 401);
}

$userId = (int)$_SESSION['user']['userid'];
$pdo = getDBConnection();

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;
$action = $input['action'] ?? 'place_order';
