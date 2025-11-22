<?php
session_start();
include("../connection.php");

// Check if user is logged in and is a dentist
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: login.php");
    exit();
}

// Check if the request is POST
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["deactivate"])) {
    $docid = $_POST["docid"];
    $useremail = $_SESSION["user"];
    
    // Verify the account belongs to the logged-in user
    $check = $database->query("SELECT * FROM doctor WHERE docid='$docid' AND docemail='$useremail'");
    if ($check->num_rows == 0) {
        $_SESSION["deactivate_error"] = "Unauthorized action!";
        header("location: settings.php");
        exit();
    }
    
    try {
        // Instead of deleting, we'll mark the account as inactive
        $update = $database->query("UPDATE doctor SET status='inactive' WHERE docid='$docid'");
        
        if ($update) {
            // Log out the user
            session_unset();
            session_destroy();
            
            // Redirect to login with success message
            header("location: login.php");
            exit();
        } else {
            throw new Exception("Database update failed");
        }
    } catch (Exception $e) {
        $_SESSION["deactivate_error"] = "Failed to deactivate account. Please try again.";
        header("location: settings.php");
        exit();
    }
} else {
    // If not a POST request, redirect back
    header("location: settings.php");
    exit();
}
?>