<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pid'])) {
    $pid = $_POST['pid'];
    
    // Check if file was uploaded without errors
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_pic']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $target_dir = "../Media/patient_profile/";
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $new_filename = 'patient_' . $pid . '_' . uniqid() . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                $image_path = 'Media/patient_profile/' . $new_filename;
                
                // Update database
                $stmt = $database->prepare("UPDATE patient SET profile_pic = ? WHERE pid = ?");
                $stmt->bind_param("si", $image_path, $pid);
                
                if ($stmt->execute()) {
                    // Update session if it's the current user
                    if (isset($_SESSION['user']) && $_SESSION['usertype'] == 'p') {
                        $_SESSION['profile_pic'] = $image_path;
                    }
                    header("Location: profile.php");
                    exit();
                } else {
                    header("Location: profile.php?error=upload");
                    exit();
                }
            } else {
                header("Location: profile.php?error=upload");
                exit();
            }
        } else {
            header("Location: profile.php?error=type");
            exit();
        }
    } else {
        header("Location: profile.php?error=upload");
        exit();
    }
} else {
    header("Location: profile.php");
    exit();
}
?>