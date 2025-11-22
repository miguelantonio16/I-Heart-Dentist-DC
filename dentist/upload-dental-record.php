<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['userid'])) {
    $dentist_id = intval($_SESSION['userid']);
    $patient_id = intval($_POST['patient_id']);
    $notes = $database->escape_string($_POST['notes']);

    // Verify dentist-patient relationship
    $verify = $database->query("SELECT * FROM appointment WHERE pid = $patient_id AND docid = $dentist_id LIMIT 1");
    if($verify->num_rows == 0) {
        die("Unauthorized access");
    }

    // File upload handling
    $target_dir = "../uploads/dental_records/";
    if(!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $filename = uniqid() . '_' . basename($_FILES["dental_record"]["name"]);
    $target_file = $target_dir . $filename;
    
    // Validate image
    $check = getimagesize($_FILES["dental_record"]["tmp_name"]);
    if($check === false) {
        die("File is not an image");
    }

    // Check file size (limit to 5MB)
    if ($_FILES["dental_record"]["size"] > 5000000) {
        die("Sorry, your file is too large. Maximum size is 5MB.");
    }

    // Allow certain file formats
    $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        die("Sorry, only JPG, JPEG, PNG & GIF files are allowed.");
    }

    if (move_uploaded_file($_FILES["dental_record"]["tmp_name"], $target_file)) {
        $database->query("INSERT INTO dental_records (patient_id, dentist_id, file_path, notes) 
                         VALUES ($patient_id, $dentist_id, '$target_file', '$notes')");
        header("Location: dentist-records.php?status=upload_success");
    } else {
        header("Location: dentist-records.php?status=upload_error");
    }
} else {
    header("Location: dentist-records.php");
}
?>