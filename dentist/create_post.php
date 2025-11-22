<?php
session_start();
include("../connection.php");

// Check if user is logged in and a dentist
if (!isset($_SESSION["user"]) || $_SESSION["usertype"] != 'd') {
    header("Location: ../dentist/login.php");
    exit();
}

// Dentist posts should use the numeric dentist id in session 'userid'
$docid = isset($_SESSION['userid']) ? intval($_SESSION['userid']) : null;
$post_title = isset($_POST['post_title']) ? trim($_POST['post_title']) : '';
$post_content = isset($_POST['post_content']) ? trim($_POST['post_content']) : '';

if (empty($docid)) {
    // Not logged in properly as dentist
    header("Location: login.php");
    exit();
}

// Insert into database (parameterized)
$sql = "INSERT INTO post_dentist (docid, title, content, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $database->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed (post_dentist): " . $database->error);
    header("Location: dashboard.php?error=prepare_failed");
    exit();
}
$stmt->bind_param("iss", $docid, $post_title, $post_content);
if (!$stmt->execute()) {
    error_log("Execute failed (post_dentist): " . $stmt->error);
    header("Location: dashboard.php?error=execute_failed");
    exit();
}

header("Location: dashboard.php");
?>
