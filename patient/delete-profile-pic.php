<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pid'])) {
    $pid = $_POST['pid'];
    $default_pic = 'Media/Icon/Blue/profile.png'; // Adjusted to match the path format in your project

    // Fetch the current profile picture before updating
    $stmt = $database->prepare("SELECT profile_pic FROM patient WHERE pid = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $result = $stmt->get_result();
    $userfetch = $result->fetch_assoc();
    $old_pic = $userfetch['profile_pic'] ?? $default_pic;

    // Update the profile picture to the default image
    $update_stmt = $database->prepare("UPDATE patient SET profile_pic = ? WHERE pid = ?");
    $update_stmt->bind_param("si", $default_pic, $pid);

    if ($update_stmt->execute()) {
        // Delete the old profile picture file from the server if it exists and is not the default
        if ($old_pic !== $default_pic && file_exists("../$old_pic")) {
            unlink("../$old_pic");
        }

        // Update session if it's the current user
        if (isset($_SESSION['user']) && $_SESSION['usertype'] == 'p') {
            $_SESSION['profile_pic'] = $default_pic;
        }

        header("Location: profile.php");
        exit();
    } else {
        header("Location: profile.php?error=delete");
        exit();
    }
} else {
    header("Location: profile.php");
    exit();
}
?>