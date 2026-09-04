<?php

require_once __DIR__ . '/../config/db.php';

echo "=== Initializing Book Store Database Setup ===\n";

try {
    $pdoRoot = getDBConnection(false);
    $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "[OK] Database '" . DB_NAME . "' ensured.\n";

    $pdo = getDBConnection(true);
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schemaSql);
    echo "[OK] All schema tables ensured.\n";

    $adminEmail = 'admin@bookstore.com';
    $userEmail = 'john@example.com';

    $stmtCheck = $pdo->prepare("SELECT userid FROM Users WHERE email = ?");
    $stmtCheck->execute([$adminEmail]);
    $admin = $stmtCheck->fetch();

    if (!$admin) {
        $adminHash = password_hash('Admin@1234', PASSWORD_BCRYPT);
        $stmtAdmin = $pdo->prepare("INSERT INTO Users (firstName, lastName, email, password, isAdmin) VALUES (?, ?, ?, ?, 1)");
        $stmtAdmin->execute(['Admin', 'Master', $adminEmail, $adminHash]);
        $adminId = $pdo->lastInsertId();
        $pdo->prepare("INSERT IGNORE INTO Cart (userid) VALUES (?)")->execute([$adminId]);
        echo "[OK] Admin user ensured.\n";
    }

    $stmtCheck->execute([$userEmail]);
    $user = $stmtCheck->fetch();
    if (!$user) {
        $userHash = password_hash('User@1234', PASSWORD_BCRYPT);
        $stmtUser = $pdo->prepare("INSERT INTO Users (firstName, lastName, email, password, isAdmin) VALUES (?, ?, ?, ?, 0)");
        $stmtUser->execute(['John', 'Doe', $userEmail, $userHash]);
        $userId = $pdo->lastInsertId();
        $pdo->prepare("INSERT IGNORE INTO Cart (userid) VALUES (?)")->execute([$userId]);

        $pdo->prepare("INSERT INTO AddressBook (userid, no, street, zipCode) VALUES (?, ?, ?, ?)")
            ->execute([$userId, '104B', 'Silicon Valley Blvd, Suite 200', '94025']);

        $nextMonth = date('Y-m-d', strtotime('+30 days'));
        $lastMonth = date('Y-m-d', strtotime('-10 days'));

        $pdo->prepare("INSERT INTO PromoCode (userid, code, type, price, isValid, exp_date) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId, 'WELCOME10', 'percentage', 10.00, 1, $nextMonth]);
        $pdo->prepare("INSERT INTO PromoCode (userid, code, type, price, isValid, exp_date) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId, 'SAVE20', 'fixed', 20.00, 1, $nextMonth]);
        $pdo->prepare("INSERT INTO PromoCode (userid, code, type, price, isValid, exp_date) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$userId, 'EXPIRED5', 'fixed', 5.00, 1, $lastMonth]);
        echo "[OK] Customer user and promos ensured.\n";
    } else {
        $userId = $user['userid'];
    }