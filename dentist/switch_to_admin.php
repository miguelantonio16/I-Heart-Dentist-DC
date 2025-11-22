<?php
session_start();
if (isset($_SESSION['usertype']) && $_SESSION['usertype'] == 'd') {
    $_SESSION['temporary_admin'] = true; // Set temporary flag
    $_SESSION['usertype'] = 'a';        // Change usertype to admin
    header("Location: ../admin/dashboard.php"); // Redirect to admin dashboard
    exit();
}
header("Location: login.php");
