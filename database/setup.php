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