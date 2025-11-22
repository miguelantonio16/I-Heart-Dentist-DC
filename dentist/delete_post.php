<?php
session_start();
include("../connection.php");

// Check if user is logged in and a dentist
if (!isset($_SESSION["user"]) || $_SESSION["usertype"] != 'd') {
    header("Location: ../dentist/login.php");
    exit();
}

$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
$docid = isset($_SESSION['userid']) ? intval($_SESSION['userid']) : 0;

if ($post_id <= 0 || $docid <= 0) {
    header("Location: dashboard.php?error=invalid_request");
    exit();
}

// Determine primary key column for post_dentist
$pk = null;
$res = $database->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'post_dentist' AND COLUMN_KEY = 'PRI' LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $pk = $row['COLUMN_NAME'];
}
if (!$pk) {
    $candidates = ['id', 'post_id', 'postid', 'pid'];
    foreach ($candidates as $c) {
        $r = $database->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'post_dentist' AND COLUMN_NAME = '" . $database->real_escape_string($c) . "' LIMIT 1");
        if ($r && $r->num_rows > 0) {
            $pk = $c;
            break;
        }
    }
}

if (!$pk) {
    error_log("Could not determine primary key for table: post_dentist");
    header("Location: dashboard.php?error=no_pk");
    exit();
}

// Delete post (only if it belongs to this dentist)
$sql = "DELETE FROM `post_dentist` WHERE `" . $pk . "` = ? AND docid = ?";
$stmt = $database->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed (dentist delete_post): " . $database->error . " SQL:" . $sql);
    header("Location: dashboard.php?error=prepare_failed");
    exit();
}
$stmt->bind_param("ii", $post_id, $docid);
if (!$stmt->execute()) {
    error_log("Execute failed (dentist delete_post): " . $stmt->error);
    header("Location: dashboard.php?error=execute_failed");
    exit();
}

header("Location: dashboard.php");
?>
