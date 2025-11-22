<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $field = $_POST['field'];
    $value = $_POST['value'];
    
    // Validate input
    if (empty($field) || empty($value)) {
        header("location: settings.php?error=3");
        exit;
    }
    
    // Special validation for URLs
    if (in_array($field, ['facebook_url', 'instagram_url'])) {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            header("location: settings.php?error=3");
            exit;
        }
    }
    
    // Special validation for email
    if ($field == 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        header("location: settings.php?error=3");
        exit;
    }
    
    // Special validation for phone
    if ($field == 'phone' && !preg_match('/^[0-9\s\-+]+$/', $value)) {
        header("location: settings.php?error=3");
        exit;
    }
    
    // Update the database
    $stmt = $database->prepare("UPDATE clinic_info SET $field = ? WHERE id = 1");
    $stmt->bind_param("s", $value);
    
    if ($stmt->execute()) {
        header("location: settings.php");
    } else {
        header("location: settings.php?error=3");
    }
    
    $stmt->close();
} else {
    header("location: settings.php");
}
?>