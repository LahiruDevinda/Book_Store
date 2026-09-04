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