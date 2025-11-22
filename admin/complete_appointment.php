<?php
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" or $_SESSION['usertype'] != 'a') {
        header("location: ../login.php");
        exit();
    }
} else {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $method = isset($_GET['method']) ? $_GET['method'] : '';

    $sql = "";
    if($method == 'cash'){
        // Admin confirmed receiving cash
        $sql = "UPDATE appointment SET status='completed', payment_status='paid', payment_method='cash' WHERE appoid='$id'";
    } else {
        // Standard completion
        $sql = "UPDATE appointment SET status='completed' WHERE appoid='$id'";
    }
    
    if ($database->query($sql)) {
        header("location: appointment.php?action=completed");
    }
}
?>