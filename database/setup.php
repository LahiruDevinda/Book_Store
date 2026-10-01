<?php
/**
 * Automated Database Setup & Seeding Script
 */
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getDBConnection();

    // 1. Read and execute schema setup
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    if ($schemaSql) {
        $statements = array_filter(array_map('trim', explode(';', $schemaSql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }
        echo "[OK] Database schema executed successfully.\n";
    }

    // 2. Ensure Admin User
    $adminEmail = 'admin@bookstore.com';
    $stmtCheck = $pdo->prepare("SELECT userid FROM Users WHERE email = ? LIMIT 1");
    $stmtCheck->execute([$adminEmail]);
    if (!$stmtCheck->fetch()) {
        $adminPass = password_hash('Admin@1234', PASSWORD_BCRYPT);
        $stmtAdmin = $pdo->prepare("INSERT INTO Users (firstName, lastName, email, password, isAdmin, role) VALUES (?, ?, ?, ?, 1, 'admin')");
        $stmtAdmin->execute(['System', 'Admin', $adminEmail, $adminPass]);
        $adminId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT IGNORE INTO Cart (userid) VALUES (?)")->execute([$adminId]);
        echo "[OK] Default Admin account created (admin@bookstore.com / Admin@1234).\n";
    }

    // 3. Ensure Shopkeeper User
    $shopEmail = 'shopkeeper@bookstore.com';
    $stmtCheck->execute([$shopEmail]);
    if (!$stmtCheck->fetch()) {
        $shopPass = password_hash('Shop@1234', PASSWORD_BCRYPT);
        $stmtShop = $pdo->prepare("INSERT INTO Users (firstName, lastName, email, password, isAdmin, role) VALUES (?, ?, ?, ?, 0, 'shopkeeper')");
        $stmtShop->execute(['Store', 'Keeper', $shopEmail, $shopPass]);
        $shopId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT IGNORE INTO Cart (userid) VALUES (?)")->execute([$shopId]);
        echo "[OK] Default Shopkeeper account created (shopkeeper@bookstore.com / Shop@1234).\n";
    }

    echo "\n[SUCCESS] Setup completed successfully!";

} catch (Exception $e) {
    echo "[ERROR] Setup failed: " . $e->getMessage();
}