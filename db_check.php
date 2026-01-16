<?php
// Temporary DB connectivity check. Delete after verification.
require __DIR__ . '/connection.php';
header('Content-Type: text/plain');

$ok = $database->ping();
if (!$ok) {
    // If ping fails, attempt a simple query to surface error logging.
    @mysqli_query($database, 'SELECT 1');
}

echo "DB_PING=" . ($ok ? 'OK' : 'FAIL') . "\n";
// Show which host & database were used (safe minimal info)
$info = [
    'host' => isset($db_host) ? $db_host : 'n/a',
    'db'   => isset($db_name) ? $db_name : 'n/a',
];
foreach ($info as $k => $v) {
    echo strtoupper($k) . "=" . $v . "\n";
}

echo "Remove this file after confirming connectivity.\n";
