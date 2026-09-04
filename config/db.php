<?php

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'bookstore_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'HBdeLA@2004');

/**
 * Returns a configured PDO instance or throws a PDOException.
 * Supports auto-fallback between user password and empty password for XAMPP / MySQL80.
 * 
 * @param bool $selectDb Whether to include the dbname in DSN (false for setup scripts)
 * @return PDO
 */

function getDBConnection(bool $selectDb = true): PDO {
    static $pdoInstance = null;

    if ($selectDb && $pdoInstance !== null) {
        return $pdoInstance;
    }

    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
    if ($selectDb) {
        $dsn .= ";dbname=" . DB_NAME;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    $passwords = [DB_PASS];
    if (DB_PASS !== '') {
        $passwords[] = '';
    }

    $lastException = null;
    foreach ($passwords as $pwd) {
        try {
            $pdo = new PDO($dsn, DB_USER, $pwd, $options);
            if ($selectDb) {
                $pdoInstance = $pdo;
            }
            return $pdo;
        } catch (PDOException $e) {
            $lastException = $e;
            if ($e->getCode() == 1045 || strpos($e->getMessage(), '1045') !== false) {
                continue;
            }
            throw $e;
        }
    }

    error_log("Database Connection Error: " . ($lastException ? $lastException->getMessage() : 'Access denied'));
    throw new PDOException("Database connection failed: " . ($lastException ? $lastException->getMessage() : 'Access denied'), 1045);
}