<?php

require_once __DIR__ . '/../config/db.php';

$pdo = getDBConnection();
$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        echo " [PASS] $msg\n";
        $passed++;
    } else {
        echo " [FAIL] $msg\n";
        $failed++;
    }
}
echo "======================================================\n";
echo "  RUNNING BOOK STORE FULL VERIFICATION TEST SUITE\n";
echo "======================================================\n\n";

echo "--- Group 1: Database Schema & Integrity ---\n";
$tables = ['users', 'author', 'genre', 'book', 'book_author', 'book_genre', 'addressbook', 'orders', 'order_item', 'payment', 'cart', 'cart_item', 'wishlist', 'review', 'promocode'];
$stmtTbl = $pdo->query("SHOW TABLES");
$dbTables = array_map('strtolower', $stmtTbl->fetchAll(PDO::FETCH_COLUMN));

$allTablesPresent = true;
foreach ($tables as $t) {
    if (!in_array($t, $dbTables)) {
        $allTablesPresent = false;
        break;
    }
}
assertCondition($allTablesPresent, "All 15 schema tables present in database");
