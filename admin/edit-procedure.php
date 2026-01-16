<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = null;
    if (isset($_POST['price'])) {
        $praw = trim($_POST['price']);
        $praw = str_replace([',','₱',' '], '', $praw);
        if ($praw === '') {
            $price = null;
        } elseif (is_numeric($praw)) {
            $price = number_format((float)$praw, 2, '.', '');
        } else {
            $price = null;
        }
    }
    
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
    // Update procedure. Only allow price update for the consultation/core procedure (id == 1)
    if ($price !== null && intval($id) === 1) {
        $sql = "UPDATE procedures SET procedure_name = '$name', description = '$description', price = '$price' WHERE procedure_id = '$id'";
    } else {
        $sql = "UPDATE procedures SET procedure_name = '$name', description = '$description' WHERE procedure_id = '$id'";
    }

    if ($database->query($sql)) {
        header("location: settings.php?action=edit_procedure&id=$id&error=3");
    } else {
        header("location: settings.php?action=edit_procedure&id=$id&error=2");
    }
} else {
    header("location: settings.php");
}
?>