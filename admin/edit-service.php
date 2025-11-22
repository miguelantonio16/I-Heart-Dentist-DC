<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    
    // Check if another service with same name exists
    $check_sql = "SELECT * FROM services WHERE procedure_name = '$name' AND id != '$id'";
    $check_result = $database->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        header("location: settings.php?action=edit&id=$id&error=1");
        exit;
    }
    
    // Get current image path
    $current_sql = "SELECT image_path FROM services WHERE id = '$id'";
    $current_result = $database->query($current_sql);
    $current_row = $current_result->fetch_assoc();
    $image_path = $current_row['image_path'];
    
    // Handle new image upload if provided
    if (!empty($_FILES["image"]["name"])) {
        $target_dir = "../Media/services/";
        $target_file = $target_dir . basename($_FILES["image"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $imageFileType;
        $destination = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $destination)) {
            // Delete old image
            if (file_exists("../" . $image_path)) {
                unlink("../" . $image_path);
            }
            $image_path = "Media/services/" . $new_filename;
        } else {
            header("location: settings.php?action=edit&id=$id&error=2");
            exit;
        }
    }
    
    $sql = "UPDATE services SET procedure_name = '$name', description = '$description', image_path = '$image_path' WHERE id = '$id'";
    
    if ($database->query($sql)) {
        header("location: settings.php?action=edit&id=$id&error=4");
    } else {
        header("location: settings.php?action=edit&id=$id&error=3");
    }
}
?>