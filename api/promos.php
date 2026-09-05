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

if ($action === 'assign_promo') {
    if (empty($_SESSION['user']['isAdmin'])) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden: Administrator privileges required.'], 403);
    }

    $targetUserId = (int)($input['userid'] ?? 0);
    $code = trim($input['code'] ?? '');
    $type = trim($input['type'] ?? 'percentage'); // 'percentage' or 'fixed'
    $price = (float)($input['price'] ?? 0);
    $expDate = trim($input['exp_date'] ?? '');

    if ($targetUserId <= 0 || empty($code) || $price <= 0 || empty($expDate)) {
        sendJsonResponse(['success' => false, 'message' => 'All fields (User, Code, Type, Value, Expiry) are required.'], 400);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO PromoCode (userid, code, type, price, isValid, exp_date)
            VALUES (?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([$targetUserId, strtoupper($code), $type, $price, $expDate]);
        sendJsonResponse(['success' => true, 'message' => 'Promo code successfully assigned to user!']);
    } catch (Exception $e) {
        sendJsonResponse(['success' => false, 'message' => 'Failed to assign promo code (Code might already exist): ' . $e->getMessage()], 500);
    }
}

if ($action === 'get_users' && !empty($_SESSION['user']['isAdmin'])) {
    $stmt = $pdo->query("SELECT userid, firstName, lastName, email FROM Users ORDER BY firstName ASC");
    sendJsonResponse(['success' => true, 'users' => $stmt->fetchAll()]);
}