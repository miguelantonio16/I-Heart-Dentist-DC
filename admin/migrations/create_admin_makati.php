<?php
/**
 * One-time script to create adminmakati@edoc.com with password 123, and ensure webuser entry exists.
 * Safe to re-run; it will upsert if records already exist.
 */

session_start();
require_once __DIR__ . '/../../connection.php';

header('Content-Type: text/plain');

try {
    // Detect minimal admin schema
    $hasAemail = false; $hasApassword = false;
    $cols = $database->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin'");
    if (!$cols) { throw new Exception('Admin table not found.'); }
    while ($c = $cols->fetch_assoc()) {
        if (strcasecmp($c['COLUMN_NAME'], 'aemail') === 0) $hasAemail = true;
        if (strcasecmp($c['COLUMN_NAME'], 'apassword') === 0) $hasApassword = true;
    }
    if (!$hasAemail || !$hasApassword) {
        throw new Exception('Admin table must have aemail and apassword columns.');
    }

    $email = 'adminmakati@edoc.com';
    $password = '123';

    // Upsert into admin
    $check = $database->prepare('SELECT aemail FROM admin WHERE aemail = ? LIMIT 1');
    $check->bind_param('s', $email);
    $check->execute();
    $res = $check->get_result();
    if ($res && $res->num_rows === 1) {
        $upd = $database->prepare('UPDATE admin SET apassword = ? WHERE aemail = ?');
        $upd->bind_param('ss', $password, $email);
        $upd->execute();
    } else {
        $ins = $database->prepare('INSERT INTO admin (aemail, apassword) VALUES (?, ?)');
        $ins->bind_param('ss', $email, $password);
        $ins->execute();
    }

    // Ensure webuser entry exists with type 'a'
    $wcheck = $database->prepare('SELECT email FROM webuser WHERE email = ? LIMIT 1');
    $wcheck->bind_param('s', $email);
    $wcheck->execute();
    $wres = $wcheck->get_result();
    if (!$wres || $wres->num_rows === 0) {
        $wins = $database->prepare('INSERT INTO webuser (email, usertype) VALUES (?, "a")');
        $wins->bind_param('s', $email);
        $wins->execute();
    }

    echo "Admin Makati account ensured. Email: $email, Password: $password\n";
    echo "Webuser entry ensured.\n";
    echo "Done.";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
