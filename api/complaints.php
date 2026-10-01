<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

if (!isset($_SESSION['user']['userid'])) {
    sendJsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int)$_SESSION['user']['userid'];
$isAdmin = !empty($_SESSION['user']['isAdmin']);
$pdo = getDBConnection();

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;
$action = $input['action'] ?? ($_GET['action'] ?? 'get');

// Get complaints for user or all for admin
if ($action === 'get') {
    if ($isAdmin) {
        $stmt = $pdo->query("
            c.complaintid, c.orderid, c.message, c.reply, c.status, c.date,
            u.firstName, u.lastName, u.email
            FROM Complaint c
            JOIN Users u ON c.userid = u.userid
            ORDER BY c.complaintid DESC
        ");
        sendJsonResponse(['success' => true, 'complaints' => $stmt->fetchAll()]);
    } else {
        $stmt = $pdo->prepare("
            SELECT complaintid, orderid, message, reply, status, date
            FROM Complaint
            WHERE userid = ?
            ORDER BY complaintid DESC
        ");
        $stmt->execute([$userId]);
        sendJsonResponse(['success' => true, 'complaints' => $stmt->fetchAll()]);
    }
}

// User submits complaint
if ($action === 'add') {
    $orderId = (int)($input['orderid'] ?? 0);
    $message = trim($input['message'] ?? '');

    if ($orderId <= 0 || empty($message)) {
        sendJsonResponse(['success' => false, 'message' => 'Order ID and complaint message are required.'], 400);
    }

    $stmt = $pdo->prepare("INSERT INTO Complaint (orderid, userid, message) VALUES (?, ?, ?)");
    $stmt->execute([$orderId, $userId, $message]);

    sendJsonResponse(['success' => true, 'message' => 'Complaint submitted successfully. Our team will review it shortly.']);
}

// Admin/Worker replies to complaint
if ($action === 'reply') {
    if (!$isAdmin) {
        sendJsonResponse(['success' => false, 'message' => 'Forbidden.'], 403);
    }

    $complaintId = (int)($input['complaintid'] ?? 0);
    $reply = trim($input['reply'] ?? '');

    if ($complaintId <= 0 || empty($reply)) {
        sendJsonResponse(['success' => false, 'message' => 'Complaint ID and reply message are required.'], 400);
    }

    $stmt = $pdo->prepare("UPDATE Complaint SET reply = ?, status = 'Resolved' WHERE complaintid = ?");
    $stmt->execute([$reply, $complaintId]);

    sendJsonResponse(['success' => true, 'message' => 'Reply sent successfully.']);
}

sendJsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);