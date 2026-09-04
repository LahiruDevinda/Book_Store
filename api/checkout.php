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

if ($action === 'place_order') {
    $addressId = !empty($input['addressid']) ? (int)$input['addressid'] : null;
    $newAddress = $input['newAddress'] ?? null;
    $promoCodeStr = trim($input['promoCode'] ?? '');
    $paymentMethod = strtoupper(trim($input['paymentMethod'] ?? 'COD'));

    if (!in_array($paymentMethod, ['COD', 'CARD'])) {
        $paymentMethod = 'COD';
    }

    try {
        $pdo->beginTransaction();

         if ($addressId) {
            $stmtAddr = $pdo->prepare("SELECT addressid FROM AddressBook WHERE addressid = ? AND userid = ?");
            $stmtAddr->execute([$addressId, $userId]);
            if (!$stmtAddr->fetch()) {
                $pdo->rollBack();
                sendJsonResponse(['success' => false, 'message' => 'Delivery address not found.'], 400);
            }
        } elseif ($newAddress && is_array($newAddress)) {
            $no = trim($newAddress['no'] ?? '');
            $street = trim($newAddress['street'] ?? '');
            $zipCode = trim($newAddress['zipCode'] ?? '');

            if (empty($no) || empty($street) || empty($zipCode)) {
                $pdo->rollBack();
                sendJsonResponse(['success' => false, 'message' => 'Please provide complete address details.'], 400);
            }

            $stmtInsAddr = $pdo->prepare("INSERT INTO AddressBook (userid, no, street, zipCode) VALUES (?, ?, ?, ?)");
            $stmtInsAddr->execute([$userId, $no, $street, $zipCode]);
            $addressId = (int)$pdo->lastInsertId();
        } else {
            $stmtDefaultAddr = $pdo->prepare("SELECT addressid FROM AddressBook WHERE userid = ? ORDER BY addressid DESC LIMIT 1");
            $stmtDefaultAddr->execute([$userId]);
            $defaultAddr = $stmtDefaultAddr->fetch();
            if ($defaultAddr) {
                $addressId = (int)$defaultAddr['addressid'];
            } else {
                $pdo->rollBack();
                sendJsonResponse(['success' => false, 'message' => 'Please enter a delivery address.'], 400);
            }
        }

        
    } catch (Exception $e) {}
}    