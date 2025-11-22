<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = isset($_POST['price']) ? trim($_POST['price']) : '';
    
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
    // Validate price (optional)
    if ($price !== '') {
        $price = str_replace(',', '', $price);
        if (!is_numeric($price)) {
            header("location: settings.php?action=add_procedure&error=2");
            exit;
        }
        $price = number_format((float)$price, 2, '.', '');
    } else {
        $price = NULL;
    }
    
    // Insert new procedure
    if ($price === NULL) {
        $sql = "INSERT INTO procedures (procedure_name, description) VALUES ('$name', '$description')";
    } else {
        $sql = "INSERT INTO procedures (procedure_name, description, price) VALUES ('$name', '$description', '$price')";
    }
    if ($database->query($sql)) {
        header("location: settings.php?action=add_procedure&error=3");
    } else {
        header("location: settings.php?action=add_procedure&error=2");
    }
} else {
    header("location: settings.php");
}
?>