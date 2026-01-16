<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    // Check if procedure already exists
    $check = $database->query("SELECT * FROM procedures WHERE procedure_name = '$name'");
    if ($check->num_rows > 0) {
        header("location: settings.php?action=add_procedure&error=1");
        exit;
    }
    
    // Validate fields
    if (empty($name)) {
        header("location: settings.php?action=add_procedure&error=2");
        exit;
    }
    // Insert new procedure (price removed — prices are not fixed)
    $sql = "INSERT INTO procedures (procedure_name, description) VALUES ('$name', '$description')";
    if ($database->query($sql)) {
        header("location: settings.php?action=add_procedure&error=3");
    } else {
        header("location: settings.php?action=add_procedure&error=2");
    }
} else {
    header("location: settings.php");
}
?>