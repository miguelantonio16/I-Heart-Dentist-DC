<?php
require 'connection.php';
ini_set('display_errors',1);
error_reporting(E_ALL);
$rs = $database->query("SELECT id, clinic_name, clinic_description, address, phone, email FROM clinic_info");
if (!$rs) {
    die('Query failed: ' . $database->error);
}
echo 'Rows: ' . $rs->num_rows . "<br>\n";
while ($r = $rs->fetch_assoc()) {
    echo '<pre>' . htmlspecialchars(print_r($r, true)) . '</pre>';
}
