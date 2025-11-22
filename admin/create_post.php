<?php
session_start();
include("../connection.php");

// Check if user is logged in and a dentist
if (!isset($_SESSION["user"]) || $_SESSION["usertype"] != 'a') {
    header("Location: ../admin/login.php");
    exit();
}

// For admin posts we store the admin email in session as 'user'
$aemail = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$post_title = isset($_POST['post_title']) ? trim($_POST['post_title']) : '';
$post_content = isset($_POST['post_content']) ? trim($_POST['post_content']) : '';

if (empty($aemail)) {
    // Not logged in properly as admin - redirect back to admin login
    header("Location: ../admin/login.php");
    exit();
}

// Insert into database (parameterized)
$sql = "INSERT INTO post_admin (aemail, title, content, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $database->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: " . $database->error);
    header("Location: dashboard.php?error=prepare_failed");
    exit();
}
$stmt->bind_param("sss", $aemail, $post_title, $post_content);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    header("Location: dashboard.php?error=execute_failed");
    exit();
}

header("Location: dashboard.php");
?>
