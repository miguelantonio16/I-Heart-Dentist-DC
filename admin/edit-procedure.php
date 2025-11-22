<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = isset($_POST['price']) ? trim($_POST['price']) : '';
    
    // Check if procedure already exists (excluding current one)
    $check = $database->query("SELECT * FROM procedures WHERE procedure_name = '$name' AND procedure_id != '$id'");
    if ($check->num_rows > 0) {
        header("location: settings.php?action=edit_procedure&id=$id&error=1");
        exit;
    }
    
    // Validate fields
    if (empty($name)) {
        header("location: settings.php?action=edit_procedure&id=$id&error=2");
        exit;
    }
    // Validate price (optional)
    if ($price !== '') {
        $price = str_replace(',', '', $price);
        if (!is_numeric($price)) {
            header("location: settings.php?action=edit_procedure&id=$id&error=2");
            exit;
        }
        $price = number_format((float)$price, 2, '.', '');
        $price_sql = ", price = '$price'";
    } else {
        $price_sql = ", price = NULL";
    }
    
    // Update procedure
    $sql = "UPDATE procedures SET procedure_name = '$name', description = '$description' $price_sql WHERE procedure_id = '$id'";
    if ($database->query($sql)) {
        header("location: settings.php?action=edit_procedure&id=$id&error=3");
    } else {
        header("location: settings.php?action=edit_procedure&id=$id&error=2");
    }
} else {
    header("location: settings.php");
}
?>