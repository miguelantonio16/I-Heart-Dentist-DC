<?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: login.php");
    exit();
}

include("../connection.php");

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Update status to completed
    $sql = "UPDATE appointment SET status='completed' WHERE appoid='$id'";
    
    if ($database->query($sql)) {
        header("location: appointment.php?action=completed");
    } else {
        echo "Error updating record: " . $database->error;
    }
} else {
    header("location: appointment.php");
}
?>