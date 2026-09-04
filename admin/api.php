<?php

// Start a secure session
require_once __DIR__ . '/../config/db.php';
startSecureSession();

// Strict Admin Verification
if (!isset($_SESSION['user']['userid']) || empty($_SESSION['user']['isAdmin'])) {
    sendJsonResponse(['success' => false, 'message' => 'Forbidden: Administrator privileges required.'], 403);
}

$pdo = getDBConnection();
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;
$action = $input['action'] ?? ($_GET['action'] ?? '');

// ======================== DASHBOARD STATS ========================
if ($action === 'stats') {
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