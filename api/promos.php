<?php

require_once __DIR__ . '/../config/db.php';
startSecureSession();

if (!isset($_SESSION['user']['userid'])) {
    sendJsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int)$_SESSION['user']['userid'];
$pdo = getDBConnection();

$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT promoCodeld, code, type, price, exp_date
    FROM PromoCode
    WHERE userid = ? AND isValid = 1 AND exp_date >= ?
    ORDER BY exp_date ASC
");
$stmt->execute([$userId, $today]);
$promos = $stmt->fetchAll();

foreach ($promos as &$p) {
    $p['price'] = (float)$p['price'];
}

sendJsonResponse(['success' => true, 'promos' => $promos]);