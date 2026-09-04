require_once __DIR__ . '/../config/db.php';
startSecureSession();

$pdo = getDBConnection();
$userId = $_SESSION['user']['userid'] ?? null;

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;
$action = $input['action'] ?? ($_GET['action'] ?? 'get');

function getUserCartId(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT cartid FROM Cart WHERE userid = ? LIMIT 1");
    $stmt->execute([$userId]);
    $cart = $stmt->fetch();
    if ($cart) {
        return (int)$cart['cartid'];
    }
    $stmtIns = $pdo->prepare("INSERT INTO Cart (userid) VALUES (?)");
    $stmtIns->execute([$userId]);
    return (int)$pdo->lastInsertId();
}

if ($action === 'hydrate' || (!$userId && $action === 'get' && !empty($input['items']))) {
    $items = $input['items'] ?? [];
    if (!is_array($items) || empty($items)) {
        sendJsonResponse([
            'success'  => true,
            'items'    => [],
            'subTotal' => 0.00,
            'count'    => 0
        ]);
    }

    $bookIds = [];
    $qtyMap = [];
    foreach ($items as $it) {
        $bid = (int)($it['bookid'] ?? 0);
        $qty = max(1, (int)($it['quantity'] ?? 1));
        if ($bid > 0) {
            $bookIds[] = $bid;
            $qtyMap[$bid] = $qty;
        }
    }

    if (empty($bookIds)) {
        sendJsonResponse(['success' => true, 'items' => [], 'subTotal' => 0.00, 'count' => 0]);
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

    $cartItems = [];
    $subTotal = 0.00;
    $totalCount = 0;

    foreach ($rows as $row) {
        $bid = (int)$row['bookid'];
        $qty = $qtyMap[$bid] ?? 1;
        $price = (float)$row['price'];
        $itemTotal = round($price * $qty, 2);
        $subTotal += $itemTotal;
        $totalCount += $qty;

        $cartItems[] = [
            'bookid'        => $bid,
            'title'         => $row['title'],
            'ISBN'          => $row['ISBN'],
            'price'         => $price,
            'stockQuantity' => (int)$row['stockQuantity'],
            'coverImageUrl' => $row['coverImageUrl'],
            'authors'       => $row['authors'],
            'quantity'      => $qty,
            'itemTotal'     => $itemTotal
        ];
    }

    sendJsonResponse([
        'success'  => true,
        'items'    => $cartItems,
        'subTotal' => round($subTotal, 2),
        'count'    => $totalCount
    ]);
}