<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    
    // Check if service with same name exists
    $check_sql = "SELECT * FROM services WHERE procedure_name = '$name'";
    $check_result = $database->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        header("location: settings.php?action=add&id=none&error=1");
        exit;
    }
    
    // Handle image upload
    $target_dir = "../Media/services/";
    $target_file = $target_dir . basename($_FILES["image"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $imageFileType;
    $destination = $target_dir . $new_filename;
    
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $destination)) {
        $image_path = "Media/services/" . $new_filename;
        
        $sql = "INSERT INTO services (procedure_name, description, image_path) VALUES ('$name', '$description', '$image_path')";
        
        if ($database->query($sql)) {  // Fixed this line - added missing parenthesis
            header("location: settings.php?action=add&id=none&error=4");
        } else {
            header("location: settings.php?action=add&id=none&error=3");
        }
    } else {
        header("location: settings.php?action=add&id=none&error=2");
    }
}
?>