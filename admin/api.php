<?php

// Start a secure session
require_once __DIR__ . '/../config/db.php';
startSecureSession();

$user = $_SESSION['user'] ?? [];
$isAdmin = !empty($user['isAdmin']);
$isShopkeeper = (($user['role'] ?? '') === 'shopkeeper');

// Strict Privileges Verification: Admin or Shopkeeper required
if (!isset($user['userid']) || (!$isAdmin && !$isShopkeeper)) {
    sendJsonResponse(['success' => false, 'message' => 'Forbidden: Administrator or Shopkeeper privileges required.'], 403);
}

$pdo = getDBConnection();
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;
$action = $input['action'] ?? ($_GET['action'] ?? '');

// ======================== DASHBOARD STATS ========================
if ($action === 'stats') {
    if (!$isAdmin) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }
    $totalBooks = (int)$pdo->query("SELECT COUNT(*) FROM Book")->fetchColumn();
    $totalStock = (int)$pdo->query("SELECT COALESCE(SUM(stockQuantity), 0) FROM Book")->fetchColumn();
    $totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM Orders")->fetchColumn();
    $totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(subTotal), 0) FROM Orders")->fetchColumn();
    $lowStock = (int)$pdo->query("SELECT COUNT(*) FROM Book WHERE stockQuantity < 10")->fetchColumn();

    sendJsonResponse([
        'success' => true,
        'stats'   => [
            'totalBooks'   => $totalBooks,
            'totalStock'   => $totalStock,
            'totalOrders'  => $totalOrders,
            'totalRevenue' => round($totalRevenue, 2),
            'lowStock'     => $lowStock
        ]
    ]);
}

// ======================== GET ALL BOOKS WITH BRIDGES ========================
if ($action === 'get_books') {
    // Allowed for both Admin and Shopkeeper
    if (!$isAdmin && !$isShopkeeper) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }
    $stmt = $pdo->query("
        SELECT b.bookid, b.title, b.ISBN, b.price, b.stockQuantity, b.coverImageUrl,
               GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') AS authors,
               GROUP_CONCAT(DISTINCT g.genreName SEPARATOR ', ') AS genres
        FROM Book b
        LEFT JOIN Book_Author ba ON b.bookid = ba.bookid
        LEFT JOIN Author a ON ba.authorid = a.authorid
        LEFT JOIN Book_Genre bg ON b.bookid = bg.bookid
        LEFT JOIN Genre g ON bg.genreid = g.genreid
        GROUP BY b.bookid
        ORDER BY b.bookid DESC
    ");
    $books = $stmt->fetchAll();

    foreach ($books as &$book) {
        $book['price'] = (float)$book['price'];
        $book['stockQuantity'] = (int)$book['stockQuantity'];
    }

    sendJsonResponse(['success' => true, 'books' => $books]);
}

// ======================== ADD NEW BOOK ========================
if ($action === 'add_book') {
    if (!$isAdmin) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }
    $title = trim($input['title'] ?? '');
    $isbn = trim($input['ISBN'] ?? '');
    $price = (float)($input['price'] ?? 0);
    $stock = (int)($input['stockQuantity'] ?? 0);
    $cover = trim($input['coverImageUrl'] ?? '');
    $authorIds = $input['authorIds'] ?? [];
    $genreIds = $input['genreIds'] ?? [];

    if (empty($title) || empty($isbn) || $price <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Title, ISBN, and positive price are required.'], 400);
    }

    // Check duplicate ISBN
    $chk = $pdo->prepare("SELECT bookid FROM Book WHERE ISBN = ?");
    $chk->execute([$isbn]);
    if ($chk->fetch()) {
        sendJsonResponse(['success' => false, 'message' => 'A book with this ISBN already exists.'], 409);
    }

    if (empty($cover)) {
        $cover = 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=600&auto=format&fit=crop&q=80';
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO Book (title, ISBN, price, stockQuantity, coverImageUrl) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $isbn, $price, $stock, $cover]);
        $bookId = (int)$pdo->lastInsertId();

        // Bridge Table: Book_Author
        if (!empty($authorIds) && is_array($authorIds)) {
            $stmtBA = $pdo->prepare("INSERT IGNORE INTO Book_Author (bookid, authorid) VALUES (?, ?)");
            foreach ($authorIds as $aid) {
                $aid = (int)$aid;
                if ($aid > 0) {
                    $stmtBA->execute([$bookId, $aid]);
                }
            }
        }

        // Bridge Table: Book_Genre
        if (!empty($genreIds) && is_array($genreIds)) {
            $stmtBG = $pdo->prepare("INSERT IGNORE INTO Book_Genre (bookid, genreid) VALUES (?, ?)");
            foreach ($genreIds as $gid) {
                $gid = (int)$gid;
                if ($gid > 0) {
                    $stmtBG->execute([$bookId, $gid]);
                }
            }
        }

        $pdo->commit();

        sendJsonResponse(['success' => true, 'message' => 'Book added successfully!', 'bookid' => $bookId]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendJsonResponse(['success' => false, 'message' => 'Failed to add book: ' . $e->getMessage()], 500);
    }
}

// ======================== UPDATE BOOK STOCK & PRICE ========================
if ($action === 'update_book') {
    if (!$isAdmin) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }
    $bookId = (int)($input['bookid'] ?? 0);
    $price = (float)($input['price'] ?? 0);
    $stock = (int)($input['stockQuantity'] ?? 0);

    if ($bookId <= 0 || $price <= 0 || $stock < 0) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid book details.'], 400);
    }

    $stmt = $pdo->prepare("UPDATE Book SET price = ?, stockQuantity = ? WHERE bookid = ?");
    $stmt->execute([$price, $stock, $bookId]);

    sendJsonResponse(['success' => true, 'message' => 'Book updated successfully.']);
}

// ======================== DELETE BOOK ========================
if ($action === 'delete_book') {
    if (!$isAdmin) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }
    $bookId = (int)($input['bookid'] ?? 0);
    if ($bookId <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid book ID.'], 400);
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM Book_Author WHERE bookid = ?")->execute([$bookId]);
        $pdo->prepare("DELETE FROM Book_Genre WHERE bookid = ?")->execute([$bookId]);
        $pdo->prepare("DELETE FROM Cart_Item WHERE bookid = ?")->execute([$bookId]);
        $pdo->prepare("DELETE FROM Wishlist WHERE bookid = ?")->execute([$bookId]);
        $pdo->prepare("DELETE FROM Review WHERE bookid = ?")->execute([$bookId]);
        $pdo->prepare("DELETE FROM Book WHERE bookid = ?")->execute([$bookId]);
        $pdo->commit();

        sendJsonResponse(['success' => true, 'message' => 'Book deleted successfully.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendJsonResponse(['success' => false, 'message' => 'Failed to delete book: ' . $e->getMessage()], 500);
    }
}

// ======================== GET / ADD AUTHORS ========================
if ($action === 'get_authors') {
    if (!$isAdmin) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }
    $stmt = $pdo->query("SELECT authorid, name, biography FROM Author ORDER BY name ASC");
    sendJsonResponse(['success' => true, 'authors' => $stmt->fetchAll()]);
}

if ($action === 'add_author') {
    if (!$isAdmin) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }
    $name = trim($input['name'] ?? '');
    $bio = trim($input['biography'] ?? '');

    if (empty($name)) {
        sendJsonResponse(['success' => false, 'message' => 'Author name is required.'], 400);
    }

    $stmt = $pdo->prepare("INSERT INTO Author (name, biography) VALUES (?, ?)");
    $stmt->execute([$name, $bio]);

    sendJsonResponse(['success' => true, 'message' => 'Author created!', 'authorid' => (int)$pdo->lastInsertId()]);
}

// ======================== GET / ADD GENRES ========================
if ($action === 'get_genres') {
    if (!$isAdmin) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }
    $stmt = $pdo->query("SELECT genreid, genreName FROM Genre ORDER BY genreName ASC");
    sendJsonResponse(['success' => true, 'genres' => $stmt->fetchAll()]);
}

if ($action === 'add_genre') {
    if (!$isAdmin) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }
    $name = trim($input['genreName'] ?? '');
    if (empty($name)) {
        sendJsonResponse(['success' => false, 'message' => 'Genre name is required.'], 400);
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO Genre (genreName) VALUES (?)");
    $stmt->execute([$name]);

    sendJsonResponse(['success' => true, 'message' => 'Genre saved!', 'genreid' => (int)$pdo->lastInsertId()]);
}

// ======================== AUDIT ORDERS WITH HISTORICAL UNIT PRICES ========================
if ($action === 'get_orders') {
    $stmt = $pdo->query("
        SELECT o.orderid, o.userid, o.subTotal, o.orderStatus, o.deliveryStatus, o.isDeliveredConfirmed, o.date AS orderDate,
               u.firstName, u.lastName, u.email,
               ab.no, ab.street, ab.zipCode,
               p.code AS promoCode,
               pay.method AS paymentMethod
        FROM Orders o
        JOIN Users u ON o.userid = u.userid
        LEFT JOIN AddressBook ab ON o.addressid = ab.addressid
        LEFT JOIN PromoCode p ON o.promoCodeld = p.promoCodeld
        LEFT JOIN Payment pay ON o.orderid = pay.orderid
        ORDER BY o.orderid DESC
    ");
    $orders = $stmt->fetchAll();

    foreach ($orders as &$ord) {
        $ord['subTotal'] = (float)$ord['subTotal'];

        // Fetch order items with locked unit prices
        $stmtItems = $pdo->prepare("
            SELECT oi.bookid, oi.unitPrice, oi.quantity, b.title
            FROM Order_Item oi
            LEFT JOIN Book b ON oi.bookid = b.bookid
            WHERE oi.orderid = ?
        ");
        $stmtItems->execute([$ord['orderid']]);
        $items = $stmtItems->fetchAll();

        foreach ($items as &$it) {
            $it['unitPrice'] = (float)$it['unitPrice'];
            $it['quantity'] = (int)$it['quantity'];
            $it['lineTotal'] = round($it['unitPrice'] * $it['quantity'], 2);
        }
        $ord['items'] = $items;
    }

    sendJsonResponse(['success' => true, 'orders' => $orders]);
}

// ======================== UPDATE DELIVERY STATUS ========================
if ($action === 'update_delivery_status') {
    $orderId = (int)($input['orderid'] ?? 0);
    $deliveryStatus = trim($input['deliveryStatus'] ?? '');

    if ($orderId <= 0 || empty($deliveryStatus)) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid order details or delivery status.'], 400);
    }

    $stmt = $pdo->prepare("UPDATE Orders SET deliveryStatus = ? WHERE orderid = ?");
    $stmt->execute([$deliveryStatus, $orderId]);

    sendJsonResponse(['success' => true, 'message' => 'Delivery status updated successfully.']);
}

// ======================== SHOPKEEPER: SUBMIT INVENTORY REQUEST ========================
if ($action === 'submit_inventory_request') {
    if (!$isShopkeeper && !$isAdmin) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }

    $bookId = !empty($input['bookid']) ? (int)$input['bookid'] : null;
    $actionType = $input['action_type'] ?? 'update'; // 'add' or 'update'
    $title = trim($input['title'] ?? '');
    $isbn = trim($input['ISBN'] ?? '');
    $price = (float)($input['price'] ?? 0);
    $stock = (int)($input['stockQuantity'] ?? 0);
    $cover = trim($input['coverImageUrl'] ?? '');

    if (empty($title) || empty($isbn) || $price <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Title, ISBN, and positive price are required.'], 400);
    }

    $stmt = $pdo->prepare("
        INSERT INTO InventoryRequest (userid, bookid, action_type, title, ISBN, price, stockQuantity, coverImageUrl, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
    ");
    $stmt->execute([$user['userid'], $bookId, $actionType, $title, $isbn, $price, $stock, $cover]);

    sendJsonResponse(['success' => true, 'message' => 'Inventory change request submitted to administrator for approval.']);
}

// ======================== ADMIN: GET INVENTORY REQUESTS ========================
if ($action === 'get_inventory_requests') {
    if (!$isAdmin) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }

    $stmt = $pdo->query("
        SELECT ir.*, u.firstName, u.lastName, u.email
        FROM InventoryRequest ir
        JOIN Users u ON ir.userid = u.userid
        WHERE ir.status = 'Pending'
        ORDER BY ir.requestid DESC
    ");
    sendJsonResponse(['success' => true, 'requests' => $stmt->fetchAll()]);
}

// ======================== ADMIN: RESOLVE INVENTORY REQUEST ========================
if ($action === 'resolve_inventory_request') {
    if (!$isAdmin) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }

    $requestId = (int)($input['requestid'] ?? 0);
    $decision = trim($input['decision'] ?? ''); // 'approve' or 'reject'

    if ($requestId <= 0 || !in_array($decision, ['approve', 'reject'])) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid request or decision.'], 400);
    }

    try {
        $pdo->beginTransaction();

        $stmtReq = $pdo->prepare("SELECT * FROM InventoryRequest WHERE requestid = ? AND status = 'Pending'");
        $stmtReq->execute([$requestId]);
        $req = $stmtReq->fetch();

        if (!$req) {
            $pdo->rollBack();
            sendJsonResponse(['success' => false, 'message' => 'Request not found or already processed.'], 404);
        }

        if ($decision === 'approve') {
            if ($req['action_type'] === 'add') {
                $stmtAdd = $pdo->prepare("INSERT INTO Book (title, ISBN, price, stockQuantity, coverImageUrl) VALUES (?, ?, ?, ?, ?)");
                $stmtAdd->execute([$req['title'], $req['ISBN'], $req['price'], $req['stockQuantity'], $req['coverImageUrl']]);
            } else {
                $stmtUpd = $pdo->prepare("UPDATE Book SET title = ?, ISBN = ?, price = ?, stockQuantity = ?, coverImageUrl = ? WHERE bookid = ?");
                $stmtUpd->execute([$req['title'], $req['ISBN'], $req['price'], $req['stockQuantity'], $req['coverImageUrl'], $req['bookid']]);
            }
            $status = 'Approved';
        } else {
            $status = 'Rejected';
        }

        $stmtUpdateReq = $pdo->prepare("UPDATE InventoryRequest SET status = ? WHERE requestid = ?");
        $stmtUpdateReq->execute([$status, $requestId]);

        $pdo->commit();
        sendJsonResponse(['success' => true, 'message' => "Inventory request has been {$status} successfully."]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendJsonResponse(['success' => false, 'message' => 'Failed to resolve request: ' . $e->getMessage()], 500);
    }
}

// ======================== INVALID ACTION HANDLER ========================
sendJsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);