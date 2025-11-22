<?php
session_start();

// Check authorization
if (!isset($_SESSION["user"]) || $_SESSION["user"] == "" || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit;
}

include("../connection.php");

// Create uploads directory with full permissions if it doesn't exist
$upload_dir = __DIR__ . '/uploads/';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        error_log("Failed to create upload directory: " . $upload_dir);
    } else {
        chmod($upload_dir, 0777); // Ensure directory is writable
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id00'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $tele = $_POST['Tele'];
    $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? intval($_POST['branch_id']) : null;
    $password = $_POST['password'] ?? '';
    $cpassword = $_POST['cpassword'] ?? '';
    $oldemail = $_POST['oldemail'];

    // Check if passwords match and are not empty
    if (!empty($password) && $password != $cpassword) {
        header("location: dentist.php?action=edit&id=$id&error=2");
        exit;
    }

    // Check if email is already used by another doctor
    $result = $database->query("SELECT docid FROM doctor WHERE docemail = '$email' AND docid != '$id'");
    if ($result->num_rows > 0) {
        header("location: dentist.php?action=edit&id=$id&error=1");
        exit;
    }

    // Handle photo upload
    $photoUpdate = "";
    
    // Debug file upload
    if (isset($_FILES['photo'])) {
        error_log("File upload info: " . print_r($_FILES['photo'], true));
    } else {
        error_log("No file uploaded");
    }
    
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo = $_FILES['photo'];
        $photo_ext = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        // Validate file extension
        if (!in_array($photo_ext, $allowed_extensions)) {
            header("location: dentist.php?action=edit&id=$id&error=5"); // Invalid file type
            exit;
        }
        
        $photo_name = uniqid('dentist_', true) . '.' . $photo_ext;
        $photo_path = $upload_dir . $photo_name;
        
        error_log("Attempting to move uploaded file to: " . $photo_path);
        
        if (move_uploaded_file($photo['tmp_name'], $photo_path)) {
            error_log("File moved successfully to: " . $photo_path);
            
            // Delete old photo if exists and is not the default photo
            $oldPhoto = $database->query("SELECT photo FROM doctor WHERE docid = '$id'")->fetch_assoc()['photo'];
            if ($oldPhoto && $oldPhoto != 'default.jpg' && file_exists($upload_dir . $oldPhoto)) {
                unlink($upload_dir . $oldPhoto);
            }
            $photoUpdate = ", photo = '$photo_name'";
        } else {
            error_log("Failed to move uploaded file. Error: " . error_get_last()['message']);
        }
    }

    // Start transaction
    $database->begin_transaction();
    
    try {
        // Only update password if provided
        $passwordUpdate = "";
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $passwordUpdate = ", docpassword = '$hashedPassword'";
        }
        // Update doctor data (include branch if present)
        $branchUpdate = '';
        if ($branch_id !== null) {
            $branchUpdate = ", branch_id = $branch_id";
        } else {
            $branchUpdate = ", branch_id = NULL";
        }

        $sql = "UPDATE doctor 
                SET docname = '$name', 
                    docemail = '$email', 
                    doctel = '$tele' 
                    $passwordUpdate
                    $photoUpdate
                    $branchUpdate
                WHERE docid = '$id'";
        
        $database->query($sql);
        
        // Update webuser email if email changed
        if ($email != $oldemail) {
            $database->query("UPDATE webuser SET email = '$email' WHERE email = '$oldemail'");
        }
        
        $database->commit();
        header("location: dentist.php?action=edit&id=$id&error=4");
    } catch (Exception $e) {
        $database->rollback();
        error_log("Database error: " . $e->getMessage());
        header("location: dentist.php?action=edit&id=$id&error=3");
    }
}
?>