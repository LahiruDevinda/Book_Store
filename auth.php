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