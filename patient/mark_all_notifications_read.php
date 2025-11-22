<?php
session_start();
include("../connection.php");

if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'p') {
    header("location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the user's ID from the database since $_SESSION['userid'] is not set
    $useremail = $_SESSION["user"];
    $userrow = $database->query("select pid from patient where pemail='$useremail'");
    $userfetch = $userrow->fetch_assoc();
    $userId = $userfetch["pid"];
    
    // Mark all notifications as read for this user
    $stmt = $database->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND user_type = 'p' AND is_read = 0");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>