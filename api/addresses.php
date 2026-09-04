<?php

require_once __DIR__ . '/../config/db.php';
startSecureSession();

if (!isset($_SESSION['user']['userid'])) {
    sendJsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int)$_SESSION['user']['userid'];
$pdo = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];