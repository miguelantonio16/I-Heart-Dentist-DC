<?php
session_start();
if (isset($_SESSION['temporary_admin']) && $_SESSION['temporary_admin']) {
    unset($_SESSION['temporary_admin']); // Remove temporary flag
    $_SESSION['usertype'] = 'd';         // Change usertype back to dentist
    header("Location: ../dentist/dashboard.php"); // Redirect to dentist dashboard
    exit();
}
header("Location: login.php");
