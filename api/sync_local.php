<?php

require_once __DIR__ . '/../config/db.php';
startSecureSession();

if (!isset($_SESSION['user']['userid'])) {
    sendJsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int)$_SESSION['user']['userid'];
$pdo = getDBConnection();

$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true);

if (!is_array($data)) {
    $data = ['guest_cart' => [], 'guest_wishlist' => []];
}