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