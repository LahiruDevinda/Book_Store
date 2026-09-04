<?php

require_once __DIR__ . '/../config/db.php';
startSecureSession();

if (!isset($_SESSION['user']['userid'])) {
    sendJsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

$userId = (int)$_SESSION['user']['userid'];
$pdo = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT addressid, no, street, zipCode FROM AddressBook WHERE userid = ? ORDER BY addressid DESC");
    $stmt->execute([$userId]);
    $addresses = $stmt->fetchAll();
    sendJsonResponse(['success' => true, 'addresses' => $addresses]);
}

if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?: $_POST;

    $no = trim($input['no'] ?? '');
    $street = trim($input['street'] ?? '');
    $zipCode = trim($input['zipCode'] ?? '');

    if (empty($no) || empty($street) || empty($zipCode)) {
        sendJsonResponse(['success' => false, 'message' => 'Please provide unit number, street address, and postal code.'], 400);
    }

    $stmt = $pdo->prepare("INSERT INTO AddressBook (userid, no, street, zipCode) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $no, $street, $zipCode]);
    $addressId = (int)$pdo->lastInsertId();

    sendJsonResponse([
        'success'   => true,
        'message'   => 'Address saved successfully.',
        'addressid' => $addressId,
        'address'   => [
            'addressid' => $addressId,
            'no'        => $no,
            'street'    => $street,
            'zipCode'   => $zipCode
        ]
    ]);
}

sendJsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);