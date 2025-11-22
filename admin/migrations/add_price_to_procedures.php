<?php
/**
 * Run this once from the browser (while logged in as admin) to add a `price` column
 * to `procedures` table if it doesn't already exist.
 */
session_start();
include("../../connection.php");

try {
    $res = $database->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'procedures' AND COLUMN_NAME = 'price'");
    if ($res->num_rows == 0) {
        $database->query("ALTER TABLE procedures ADD COLUMN price DECIMAL(10,2) NULL DEFAULT NULL");
        echo "Migration completed. 'price' column added to procedures table.";
    } else {
        echo "'price' column already exists on procedures table.";
    }
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage();
}

?>