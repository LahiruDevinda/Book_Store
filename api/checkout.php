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

if ($action === 'validate_promo') {
    $code = trim($input['code'] ?? '');
    if (empty($code)) {
        sendJsonResponse(['success' => false, 'message' => 'Promo code cannot be empty.'], 400);
    }

    $stmt = $pdo->prepare("SELECT promoCodeld, userid, code, type, price, isValid, exp_date FROM PromoCode WHERE code = ? AND userid = ? LIMIT 1");
    $stmt->execute([$code, $userId]);
    $promo = $stmt->fetch();

    if (!$promo) {
        sendJsonResponse(['success' => false, 'message' => 'Promo code is invalid.'], 404);
    }

    if (!(bool)$promo['isValid']) {
        sendJsonResponse(['success' => false, 'message' => 'This promo code has already been used.'], 400);
    }

    $today = date('Y-m-d');
    if ($promo['exp_date'] < $today) {
        sendJsonResponse(['success' => false, 'message' => 'This promo code has expired.'], 400);
    }

    sendJsonResponse([
        'success' => true,
        'message' => 'Promo code applied!',
        'promo' => [
            'promoCodeld' => (int)$promo['promoCodeld'],
            'code'        => $promo['code'],
            'type'        => $promo['type'],
            'price'       => (float)$promo['price'],
            'exp_date'    => $promo['exp_date']
        ]
    ]);
}