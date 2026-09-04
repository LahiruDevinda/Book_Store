<?php
/**
 * User Authentication Endpoint (Login, Register, Logout, Status)
 */

require_once __DIR__ . '/config/db.php';
startSecureSession();

$pdo = getDBConnection();
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;
$action = $input['action'] ?? ($_GET['action'] ?? '');

// ======================== SESSION STATUS ========================
if ($action === 'status') {
    if (isset($_SESSION['user']['userid'])) {
        $userId = (int)$_SESSION['user']['userid'];
        // Fetch cart count
        $stmtCart = $pdo->prepare("SELECT cartid FROM Cart WHERE userid = ? LIMIT 1");
        $stmtCart->execute([$userId]);
        $cart = $stmtCart->fetch();
        $cartCount = 0;
        if ($cart) {
            $stmtCount = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM Cart_Item WHERE cartid = ?");
            $stmtCount->execute([$cart['cartid']]);
            $cartCount = (int)$stmtCount->fetchColumn();
        }

        // Fetch wishlist count
        $stmtWish = $pdo->prepare("SELECT COUNT(*) FROM Wishlist WHERE userid = ?");
        $stmtWish->execute([$userId]);
        $wishlistCount = (int)$stmtWish->fetchColumn();

        sendJsonResponse([
            'success'       => true,
            'loggedIn'      => true,
            'user'          => $_SESSION['user'],
            'cartCount'     => $cartCount,
            'wishlistCount' => $wishlistCount
        ]);
    } else {
        sendJsonResponse([
            'success'  => true,
            'loggedIn' => false,
            'user'     => null
        ]);
    }
}

// ======================== USER LOGIN ========================
if ($action === 'login') {
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        sendJsonResponse(['success' => false, 'message' => 'Email and password are required.'], 400);
    }

    $stmt = $pdo->prepare("SELECT userid, firstName, lastName, email, password, isAdmin FROM Users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid email address or password.'], 401);
    }

    // Set secure session
    $_SESSION['user'] = [
        'userid'    => (int)$user['userid'],
        'firstName' => $user['firstName'],
        'lastName'  => $user['lastName'],
        'email'     => $user['email'],
        'isAdmin'   => (bool)$user['isAdmin']
    ];

    // Ensure user has a Cart record
    $pdo->prepare("INSERT IGNORE INTO Cart (userid) VALUES (?)")->execute([(int)$user['userid']]);

    sendJsonResponse([
        'success' => true,
        'message' => 'Welcome back, ' . htmlspecialchars($user['firstName']) . '!',
        'user'    => $_SESSION['user']
    ]);
}