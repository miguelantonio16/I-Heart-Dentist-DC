<?php
session_start();
include("../connection.php");

// Only admins may delete posts
if (!isset($_SESSION["user"]) || $_SESSION["usertype"] != 'a') {
    header("Location: ../admin/login.php");
    exit();
}

$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$post_type = isset($_POST['post_type']) ? $_POST['post_type'] : 'admin';

if ($post_id <= 0) {
    header("Location: dashboard.php?error=invalid_post_id");
    exit();
}

// Determine table name safely
$table = $post_type === 'dentist' ? 'post_dentist' : 'post_admin';

// Find primary key column for the table (falls back to common names)
$pk = null;
$res = $database->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $database->real_escape_string($table) . "' AND COLUMN_KEY = 'PRI' LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $pk = $row['COLUMN_NAME'];
}

if (!$pk) {
    // Try common column names
    $candidates = ['id', 'post_id', 'postid', 'pid'];
    foreach ($candidates as $c) {
        $r = $database->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $database->real_escape_string($table) . "' AND COLUMN_NAME = '" . $database->real_escape_string($c) . "' LIMIT 1");
        if ($r && $r->num_rows > 0) {
            $pk = $c;
            break;
        }
    }
}

if (!$pk) {
    error_log("Could not determine primary key for table: " . $table);
    header("Location: dashboard.php?error=no_pk");
    exit();
}

// Delete using the detected primary key
$sql = "DELETE FROM `" . $table . "` WHERE `" . $pk . "` = ?";
$stmt = $database->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed (delete_post): " . $database->error . " SQL:" . $sql);
    header("Location: dashboard.php?error=prepare_failed");
    exit();
}
$stmt->bind_param("i", $post_id);
if (!$stmt->execute()) {
    error_log("Execute failed (delete_post): " . $stmt->error);
    header("Location: dashboard.php?error=execute_failed");
    exit();
}

header("Location: dashboard.php");
?>
