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

$guestCart = $data['guest_cart'] ?? [];
$guestWishlist = $data['guest_wishlist'] ?? [];

try {

    $pdo->beginTransaction();

    $stmtCart = $pdo->prepare("SELECT cartid FROM Cart WHERE userid = ? LIMIT 1");
    $stmtCart->execute([$userId]);
    $cart = $stmtCart->fetch();

    if (!$cart) {
        $pdo->prepare("INSERT INTO Cart (userid) VALUES (?)")->execute([$userId]);
        $cartId = (int)$pdo->lastInsertId();
    } else {
        $cartId = (int)$cart['cartid'];
    }

    $mergedCartCount = 0;
    $mergedWishlistCount = 0;

    $stmtWishlist = $pdo->prepare("INSERT IGNORE INTO Wishlist (userid, bookid) VALUES (?, ?)");
    if (is_array($guestWishlist)) {
        foreach ($guestWishlist as $item) {
            $bookId = is_array($item) ? (int)($item['bookid'] ?? 0) : (int)$item;
            if ($bookId > 0) {
                $chkBook = $pdo->prepare("SELECT bookid FROM Book WHERE bookid = ?");
                $chkBook->execute([$bookId]);
                if ($chkBook->fetch()) {
                    $stmtWishlist->execute([$userId, $bookId]);
                    if ($stmtWishlist->rowCount() > 0) {
                        $mergedWishlistCount++;
                    }
                }
            }
        }
    }

    $stmtCartItem = $pdo->prepare("INSERT IGNORE INTO Cart_Item (cartid, bookid, quantity) VALUES (?, ?, ?)");
    if (is_array($guestCart)) {
        foreach ($guestCart as $item) {
            $bookId = (int)($item['bookid'] ?? 0);
            $quantity = max(1, (int)($item['quantity'] ?? 1));

            if ($bookId > 0) {
                $chkBook = $pdo->prepare("SELECT bookid FROM Book WHERE bookid = ?");
                $chkBook->execute([$bookId]);
                if ($chkBook->fetch()) {
                    $stmtCartItem->execute([$cartId, $bookId, $quantity]);
                    if ($stmtCartItem->rowCount() > 0) {
                        $mergedCartCount++;
                    }
                }
            }
        }
    }

    $pdo->commit();

    $cartCountStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM Cart_Item WHERE cartid = ?");
    $cartCountStmt->execute([$cartId]);
    $totalCartItems = (int)$cartCountStmt->fetchColumn();

    $wishlistCountStmt = $pdo->prepare("SELECT COUNT(*) FROM Wishlist WHERE userid = ?");
    $wishlistCountStmt->execute([$userId]);
    $totalWishlistItems = (int)$wishlistCountStmt->fetchColumn();


} catch (Exception $e) {

}