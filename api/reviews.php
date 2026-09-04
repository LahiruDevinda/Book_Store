<?php
/**
 * Book Reviews API Endpoint
 */

require_once __DIR__ . '/../config/db.php';
startSecureSession();

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $bookId = (int)($_GET['bookid'] ?? 0);
    if ($bookId <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Book ID is required.'], 400);
    }

    $stmt = $pdo->prepare("
        SELECT r.reviewld, r.rate, r.review AS description, r.userid, u.firstName, u.lastName
        FROM Review r
        JOIN Users u ON r.userid = u.userid
        WHERE r.bookid = ?
        ORDER BY r.reviewld DESC
    ");
    $stmt->execute([$bookId]);
    $reviews = $stmt->fetchAll();

    $avgStmt = $pdo->prepare("SELECT COALESCE(AVG(rate), 0) as avgRating, COUNT(*) as totalReviews FROM Review WHERE bookid = ?");
    $avgStmt->execute([$bookId]);
    $stats = $avgStmt->fetch();

    sendJsonResponse([
        'success'      => true,
        'reviews'      => $reviews,
        'avgRating'    => round((float)$stats['avgRating'], 1),
        'totalReviews' => (int)$stats['totalReviews']
    ]);
}

