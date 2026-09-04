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

        $stmtCart = $pdo->prepare("SELECT cartid FROM Cart WHERE userid = ? LIMIT 1");
        $stmtCart->execute([$userId]);
        $cart = $stmtCart->fetch();

        if (!$cart) {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => 'Cart not found.'], 400);
        }
        $cartId = (int)$cart['cartid'];

        $stmtItems = $pdo->prepare("
            SELECT ci.bookid, ci.quantity, b.title, b.price, b.stockQuantity
            FROM Cart_Item ci
            JOIN Book b ON ci.bookid = b.bookid
            WHERE ci.cartid = ?
            FOR UPDATE
        ");
        $stmtItems->execute([$cartId]);
        $items = $stmtItems->fetchAll();

        if (empty($items)) {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => 'Your cart is empty.'], 400);
        }

        $subTotal = 0.00;
        foreach ($items as $item) {
            $qty = (int)$item['quantity'];
            $stock = (int)$item['stockQuantity'];
            if ($stock < $qty) {
                $pdo->rollBack();
                sendJsonResponse([
                    'success' => false,
                    'message' => "Insufficient stock for '{$item['title']}'."
                ], 400);
            }
            $subTotal += ((float)$item['price'] * $qty);
        }
        
        $promoId = null;
        $discountAmount = 0.00;

        if (!empty($promoCodeStr)) {
            $stmtPromo = $pdo->prepare("
                SELECT promoCodeld, type, price, isValid, exp_date
                FROM PromoCode
                WHERE code = ? AND userid = ?
                FOR UPDATE
            ");
            $stmtPromo->execute([$promoCodeStr, $userId]);
            $promo = $stmtPromo->fetch();

            if (!$promo || !(bool)$promo['isValid'] || $promo['exp_date'] < date('Y-m-d')) {
                $pdo->rollBack();
                sendJsonResponse(['success' => false, 'message' => 'Promo code is invalid or expired.'], 400);
            }

            $promoId = (int)$promo['promoCodeld'];
            $promoVal = (float)$promo['price'];

            if (strtolower($promo['type']) === 'percentage') {
                $discountAmount = round($subTotal * ($promoVal / 100), 2);
            } else {
                $discountAmount = min($subTotal, $promoVal);
            }

            $stmtInvalidate = $pdo->prepare("UPDATE PromoCode SET isValid = FALSE WHERE promoCodeld = ?");
            $stmtInvalidate->execute([$promoId]);
        }

        $finalTotal = max(0.00, round($subTotal - $discountAmount, 2));
        
        $stmtOrder = $pdo->prepare("
            INSERT INTO Orders (userid, addressid, promoCodeld, subTotal, orderStatus)
            VALUES (?, ?, ?, ?, 'Completed')
        ");
        $stmtOrder->execute([$userId, $addressId, $promoId, $finalTotal]);
        $orderId = (int)$pdo->lastInsertId();

        $stmtOrderItem = $pdo->prepare("
            INSERT INTO Order_Item (orderid, bookid, unitPrice, quantity)
            VALUES (?, ?, ?, ?)
        ");
        $stmtDeductStock = $pdo->prepare("
            UPDATE Book SET stockQuantity = stockQuantity - ? WHERE bookid = ?
        ");

        $lockedItems = [];
        foreach ($items as $item) {
            $bookId = (int)$item['bookid'];
            $qty = (int)$item['quantity'];
            $unitPrice = (float)$item['price'];

            $stmtOrderItem->execute([$orderId, $bookId, $unitPrice, $qty]);
            $stmtDeductStock->execute([$qty, $bookId]);

            $lockedItems[] = [
                'bookid'    => $bookId,
                'title'     => $item['title'],
                'unitPrice' => $unitPrice,
                'quantity'  => $qty,
                'itemTotal' => round($unitPrice * $qty, 2)
            ];
        }

        $paymentEnum = ($paymentMethod === 'CARD') ? 'card' : 'COD';
        $stmtPayment = $pdo->prepare("
            INSERT INTO Payment (orderid, method, status)
            VALUES (?, ?, 'Completed')
        ");
        $stmtPayment->execute([$orderId, $paymentEnum]);
        $paymentId = (int)$pdo->lastInsertId();

        $stmtClearCart = $pdo->prepare("DELETE FROM Cart_Item WHERE cartid = ?");
        $stmtClearCart->execute([$cartId]);

        $pdo->commit();

        sendJsonResponse([
            'success'        => true,
            'message'        => 'Order placed successfully!',
            'orderId'        => $orderId,
            'paymentId'      => $paymentId,
            'subTotal'       => $subTotal,
            'discountAmount' => $discountAmount,
            'finalTotal'     => $finalTotal,
            'items'          => $lockedItems,
            'paymentMethod'  => $paymentEnum,
            'orderStatus'    => 'Completed'
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Checkout error: " . $e->getMessage());
        sendJsonResponse(['success' => false, 'message' => 'Checkout failed: ' . $e->getMessage()], 500);
    }
}

sendJsonResponse(['success' => false, 'message' => 'Unknown action.'], 400);
