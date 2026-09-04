<?php

require_once __DIR__ . '/../config/db.php';
startSecureSession();

$pdo = getDBConnection();
$userId = $_SESSION['user']['userid'] ?? null;

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;
$action = $input['action'] ?? ($_GET['action'] ?? 'get');

if ($action === 'hydrate' || (!$userId && $action === 'get' && !empty($input['items']))) {
    $items = $input['items'] ?? [];
    if (!is_array($items) || empty($items)) {
        sendJsonResponse(['success' => true, 'items' => [], 'count' => 0]);
    }

    $bookIds = [];
    foreach ($items as $it) {
        $bid = is_array($it) ? (int)($it['bookid'] ?? 0) : (int)$it;
        if ($bid > 0) {
            $bookIds[] = $bid;
        }
    }

    if (empty($bookIds)) {
        sendJsonResponse(['success' => true, 'items' => [], 'count' => 0]);
    }

    $inQuery = implode(',', array_fill(0, count($bookIds), '?'));
    $stmt = $pdo->prepare("
        SELECT b.bookid, b.title, b.ISBN, b.price, b.stockQuantity, b.coverImageUrl,
               GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') as authors
        FROM Book b
        LEFT JOIN Book_Author ba ON b.bookid = ba.bookid
        LEFT JOIN Author a ON ba.authorid = a.authorid
        WHERE b.bookid IN ($inQuery)
        GROUP BY b.bookid
    ");
    $stmt->execute($bookIds);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['price'] = (float)$r['price'];
        $r['stockQuantity'] = (int)$r['stockQuantity'];
    }

    sendJsonResponse([
        'success' => true,
        'items'   => $rows,
        'count'   => count($rows)
    ]);
}

if (!$userId) {
    if ($action === 'get') {
        sendJsonResponse(['success' => true, 'items' => [], 'count' => 0, 'isGuest' => true]);
    }
    sendJsonResponse(['success' => false, 'message' => 'Please log in to manage your wishlist.'], 401);
}