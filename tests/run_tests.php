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

function checkCompositePk(PDO $pdo, string $table, array $expectedCols): bool {
    $stmt = $pdo->query("SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN, 4);
    return count($cols) === count($expectedCols) && empty(array_diff($expectedCols, $cols));
}

assertCondition(checkCompositePk($pdo, 'Book_Author', ['bookid', 'authorid']), "Book_Author composite PK (bookid, authorid)");
assertCondition(checkCompositePk($pdo, 'Wishlist', ['userid', 'bookid']), "Wishlist composite PK (userid, bookid)");
assertCondition(checkCompositePk($pdo, 'Cart_Item', ['cartid', 'bookid']), "Cart_Item composite PK (cartid, bookid)");
assertCondition(checkCompositePk($pdo, 'Order_Item', ['orderid', 'bookid']), "Order_Item composite PK (orderid, bookid)");

echo "\n--- Group 2: Authentication & Security ---\n";
$stmtCust = $pdo->prepare("SELECT * FROM Users WHERE email = ?");
$stmtCust->execute(['john@example.com']);
$john = $stmtCust->fetch();

assertCondition($john && password_verify('User@1234', $john['password']), "Customer password hashed with bcrypt and verifiable");
assertCondition($john && !(bool)$john['isAdmin'], "Customer isAdmin flag is FALSE");

$stmtAdm = $pdo->prepare("SELECT * FROM Users WHERE email = ?");
$stmtAdm->execute(['admin@bookstore.com']);
$admin = $stmtAdm->fetch();

assertCondition($admin && password_verify('Admin@1234', $admin['password']), "Admin password hashed with bcrypt and verifiable");
assertCondition($admin && (bool)$admin['isAdmin'], "Admin isAdmin flag is TRUE");

echo "\n--- Group 3: LocalStorage Synchronization (The Merge) ---\n";

$testUserEmail = 'merge_test_' . time() . '@test.com';
$hash = password_hash('Pass@123', PASSWORD_BCRYPT);
$pdo->prepare("INSERT INTO Users (firstName, lastName, email, password, isAdmin) VALUES ('Merge', 'Test', ?, ?, 0)")
    ->execute([$testUserEmail, $hash]);
$testUserId = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO Cart (userid) VALUES (?)")->execute([$testUserId]);
$testCartId = (int)$pdo->lastInsertId();

