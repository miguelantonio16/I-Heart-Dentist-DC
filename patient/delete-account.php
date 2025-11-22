<?php
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'p') {
        header("location: login.php");
        exit();
    } else {
        $useremail = $_SESSION["user"];
    }
} else {
    header("location: login.php");
    exit();
}

// Import database connection
include("../connection.php");

// Get current user details
$userrow = $database->query("SELECT * FROM patient WHERE pemail='$useremail'");
$userfetch = $userrow->fetch_assoc();
$userid = $userfetch["pid"];
$username = $userfetch["pname"];

if (isset($_GET["id"])) {
    $id = $_GET["id"];
    
    // Verify the ID matches the logged-in user
    if ($id != $userid) {
        header("location: settings.php?error=unauthorized");
        exit();
    }
    
    // Update patient status to 'inactive'
    $sql = $database->query("UPDATE patient SET status='inactive' WHERE pid='$id'");
    
    if ($sql) {
        // Optionally update webuser table if needed
        // $database->query("UPDATE webuser SET status='inactive' WHERE email='$useremail'");
        
        // Destroy session and redirect to login
        session_destroy();
        header("location: login.php?account=deactivated");
        exit();
    } else {
        header("location: settings.php?error=deactivation_failed");
        exit();
    }
} else {
    header("location: settings.php");
    exit();
}
?>