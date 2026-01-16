<?php
// Unified database connection: reuse central credentials.
// This avoids hardcoded local XAMPP values breaking on cPanel.
require_once __DIR__ . '/../../connection.php';
// Maintain expected $con variable for legacy code using mysqli_*.
$con = $database;
?>