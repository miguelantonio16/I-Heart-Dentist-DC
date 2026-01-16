<?php
require_once 'connection.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (isset($database) && $database instanceof mysqli) {
    if ($database->connect_errno) {
        die('MySQL connect error: ' . $database->connect_error);
    }
    echo 'Connected to DB successfully.<br>';
    echo 'Host: ' . htmlspecialchars($database->host_info) . '<br>';
} else {
    die('No $database object available.');
}
