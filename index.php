<?php
/**
 * Books Catalog & Details API Endpoint
 */

require_once __DIR__ . '/../config/db.php';
startSecureSession();

$pdo = getDBConnection();

$action = $_GET['action'] ?? 'list';

// ======================== GET GENRES LIST ========================
if ($action === 'genres') {
    $stmt = $pdo->query("SELECT genreid, genreName FROM Genre ORDER BY genreName ASC");
    $genres = $stmt->fetchAll();
    sendJsonResponse(['success' => true, 'genres' => $genres]);
}