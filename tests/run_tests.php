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

$bookIds = $pdo->query("SELECT bookid FROM Book LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
$bid1 = (int)$bookIds[0];
$bid2 = (int)$bookIds[1];

$stmtSyncCart = $pdo->prepare("INSERT IGNORE INTO Cart_Item (cartid, bookid, quantity) VALUES (?, ?, ?)");
$stmtSyncCart->execute([$testCartId, $bid1, 2]);
$stmtSyncCart->execute([$testCartId, $bid2, 1]);

$stmtSyncWish = $pdo->prepare("INSERT IGNORE INTO Wishlist (userid, bookid) VALUES (?, ?)");
$stmtSyncWish->execute([$testUserId, $bid1]);

$cartCount = (int)$pdo->query("SELECT COUNT(*) FROM Cart_Item WHERE cartid = $testCartId")->fetchColumn();
$wishCount = (int)$pdo->query("SELECT COUNT(*) FROM Wishlist WHERE userid = $testUserId")->fetchColumn();

assertCondition($cartCount === 2, "Cart_Item populated with merged items");
assertCondition($wishCount === 1, "Wishlist populated with merged item");

$stmtSyncCart->execute([$testCartId, $bid1, 2]);
$stmtSyncWish->execute([$testUserId, $bid1]);
$cartCountAfter = (int)$pdo->query("SELECT COUNT(*) FROM Cart_Item WHERE cartid = $testCartId")->fetchColumn();
assertCondition($cartCountAfter === 2, "INSERT IGNORE safely handled duplicate merge without error or duplicates");

echo "\n--- Group 4: Checkout Flow & Historical Price Locking ---\n";

$pdo->prepare("INSERT INTO AddressBook (userid, no, street, zipCode) VALUES (?, '99A', 'Test St', '12345')")
    ->execute([$testUserId]);
$testAddrId = (int)$pdo->lastInsertId();

$testPromoCode = 'TESTPROMO_' . time();
$pdo->prepare("INSERT INTO PromoCode (userid, code, type, price, isValid, exp_date) VALUES (?, ?, 'percentage', 10.00, 1, DATE_ADD(CURDATE(), INTERVAL 5 DAY))")
    ->execute([$testUserId, $testPromoCode]);
$testPromoId = (int)$pdo->lastInsertId();

$stmtChkPromo = $pdo->prepare("SELECT * FROM PromoCode WHERE code = ? AND userid = ? AND isValid = 1");
$stmtChkPromo->execute([$testPromoCode, $testUserId]);
$validPromo = $stmtChkPromo->fetch();
assertCondition(!empty($validPromo), "Active promo code found and verified");

$pdo->beginTransaction();
$cartItemsStmt = $pdo->prepare("SELECT ci.bookid, ci.quantity, b.price, b.stockQuantity FROM Cart_Item ci JOIN Book b ON ci.bookid = b.bookid WHERE ci.cartid = ?");
$cartItemsStmt->execute([$testCartId]);
$orderItems = $cartItemsStmt->fetchAll();

$subTotal = 0.00;
foreach ($orderItems as $it) {
    $subTotal += (float)$it['price'] * (int)$it['quantity'];
}
$discount = round($subTotal * 0.10, 2);
$finalTotal = round($subTotal - $discount, 2);

$stmtInsOrder = $pdo->prepare("INSERT INTO Orders (userid, addressid, promoCodeld, subTotal, orderStatus) VALUES (?, ?, ?, ?, 'Completed')");
$stmtInsOrder->execute([$testUserId, $testAddrId, $testPromoId, $finalTotal]);
$orderId = (int)$pdo->lastInsertId();

$stmtInsOrderItem = $pdo->prepare("INSERT INTO Order_Item (orderid, bookid, unitPrice, quantity) VALUES (?, ?, ?, ?)");
$stmtDeduct = $pdo->prepare("UPDATE Book SET stockQuantity = stockQuantity - ? WHERE bookid = ?");

$firstBookPrice = (float)$orderItems[0]['price'];
foreach ($orderItems as $it) {
    $stmtInsOrderItem->execute([$orderId, $it['bookid'], $it['price'], $it['quantity']]);
    $stmtDeduct->execute([$it['quantity'], $it['bookid']]);
}

$pdo->prepare("UPDATE PromoCode SET isValid = 0 WHERE promoCodeld = ?")->execute([$testPromoId]);
$pdo->prepare("DELETE FROM Cart_Item WHERE cartid = ?")->execute([$testCartId]);
$pdo->commit();

$savedOrder = $pdo->query("SELECT * FROM Orders WHERE orderid = $orderId")->fetch();
assertCondition(abs((float)$savedOrder['subTotal'] - (float)$finalTotal) < 0.01, "Order recorded with calculated discounted subtotal (\$$finalTotal)");

$savedItems = $pdo->query("SELECT * FROM Order_Item WHERE orderid = $orderId")->fetchAll();
assertCondition(count($savedItems) === 2, "Order_Item recorded 2 locked line items");
assertCondition((float)$savedItems[0]['unitPrice'] === $firstBookPrice, "Order_Item unitPrice locked in exactly at purchase time (\$$firstBookPrice)");

$promoState = $pdo->query("SELECT isValid FROM PromoCode WHERE promoCodeld = $testPromoId")->fetchColumn();
assertCondition((int)$promoState === 0, "Promo code invalidated (isValid = FALSE)");

$remainingCart = $pdo->query("SELECT COUNT(*) FROM Cart_Item WHERE cartid = $testCartId")->fetchColumn();
assertCondition((int)$remainingCart === 0, "User's Cart_Item cleared after order placement");

echo "\n--- Group 5: Social & Reviews ---\n";
$stmtReview = $pdo->prepare("INSERT INTO Review (userid, bookid, rate, review) VALUES (?, ?, 5, 'Exceptional book, pristine quality!')");
$stmtReview->execute([$testUserId, $bid1]);
$reviewId = (int)$pdo->lastInsertId();
assertCondition($reviewId > 0, "Review successfully created with 5-star rating");

$invalidRateThrew = false;
try {
    $pdo->prepare("INSERT INTO Review (userid, bookid, rate, review) VALUES (?, ?, 7, 'Invalid rating')")
        ->execute([$testUserId, $bid1]);
} catch (Exception $e) {
    $invalidRateThrew = true;
}
assertCondition($invalidRateThrew, "Rate CHECK constraint enforced (rate > 5 rejected by database)");
