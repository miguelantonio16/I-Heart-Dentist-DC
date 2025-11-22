<?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: login.php");
    exit();
}

include("../connection.php");

$useremail = $_SESSION["user"];
$userrow = $database->query("SELECT * FROM doctor WHERE docemail='$useremail'");
$userfetch = $userrow->fetch_assoc();
$userid = $userfetch["docid"];

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["delete_photo"])) {
        // Handle photo deletion - set to default dentist.png
        $default_photo = "dentist.png"; // This should be in your uploads folder
        
        // Update database
        $update_sql = "UPDATE doctor SET photo='$default_photo' WHERE docid='$userid'";
        if ($database->query($update_sql)) {
            $_SESSION["profile_pic_success"] = "Profile picture removed successfully!";
        } else {
            $_SESSION["profile_pic_error"] = "Error removing profile picture!";
        }
        
    } elseif (isset($_FILES["profile_picture"])) {
        // Handle file upload (existing code)
        $target_dir = "../admin/uploads/";
        $target_file = $target_dir . basename($_FILES["profile_picture"]["name"]);
        $uploadOk = 1;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Check if image file is a actual image or fake image
        $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
        if ($check !== false) {
            $uploadOk = 1;
        } else {
            $_SESSION["profile_pic_error"] = "File is not an image.";
            $uploadOk = 0;
        }
        
        // Check file size (5MB max)
        if ($_FILES["profile_picture"]["size"] > 5000000) {
            $_SESSION["profile_pic_error"] = "Sorry, your file is too large (max 5MB).";
            $uploadOk = 0;
        }
        
        // Allow certain file formats
        if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
            $_SESSION["profile_pic_error"] = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
            $uploadOk = 0;
        }
        
        // Check if $uploadOk is set to 0 by an error
        if ($uploadOk == 0) {
            $_SESSION["profile_pic_error"] = $_SESSION["profile_pic_error"] ?? "Sorry, your file was not uploaded.";
        } else {
            // Generate unique filename
            $new_filename = "doc_" . $userid . "_" . time() . "." . $imageFileType;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                // Update database with new filename
                $update_sql = "UPDATE doctor SET photo='$new_filename' WHERE docid='$userid'";
                if ($database->query($update_sql)) {
                    $_SESSION["profile_pic_success"] = "Profile picture updated successfully!";
                    
                    // Delete old photo if it's not the default one
                    $old_photo = $userfetch["photo"];
                    if ($old_photo != "dentist.png" && file_exists($target_dir . $old_photo)) {
                        unlink($target_dir . $old_photo);
                    }
                } else {
                    $_SESSION["profile_pic_error"] = "Error updating database record.";
                }
            } else {
                $_SESSION["profile_pic_error"] = "Sorry, there was an error uploading your file.";
            }
        }
    }
}

header("Location: settings.php");
exit();
?>