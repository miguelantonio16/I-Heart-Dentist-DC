<?php
session_start();

// Check if user is logged in and is an admin
if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'a') {
        header("location: login.php");
        exit();
    }
} else {
    header("location: login.php");
    exit();
}

// Import database connection
include("../connection.php");

// Check if ID parameter is set
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Prepare and execute the delete query
    $stmt = $database->prepare("DELETE FROM services WHERE id = ?");
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();
    
    if ($result) {
        // Deletion successful - redirect with success status
        header("location: settings.php");
    } else {
        // Deletion failed - redirect with error status
        header("location: settings.php?action=drop&error=5");
    }
    
    $stmt->close();
} else {
    // No ID provided - redirect back to settings
    header("location: settings.php");
}

$database->close();
?>