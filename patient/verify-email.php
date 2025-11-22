<?php
session_start();
include("../connection.php");


if (isset($_GET['token'])) {
    $token = $_GET['token'];


    // Check if the token exists in the database
    $result = $database->query("SELECT * FROM patient WHERE verification_token = '$token'");
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $email = $row['pemail'];


        // Mark the email as verified
        $database->query("UPDATE patient SET is_verified = 1, verification_token = NULL WHERE pemail = '$email'");


        // Redirect to a success page
        $_SESSION["user"] = $email;
        $_SESSION["usertype"] = "p";
        $_SESSION["username"] = $row['pname'];


        header('Location: verification-success.php'); // Create this page
        exit();
    } else {
        // Invalid token
        header('Location: verification-failed.php'); // Create this page
        exit();
    }
} else {
    // No token provided
    header('Location: verification-failed.php');
    exit();
}
?>
