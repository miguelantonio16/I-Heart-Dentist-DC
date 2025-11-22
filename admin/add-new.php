<?php
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" or $_SESSION['usertype'] != 'a') {
        header("location: ../login.php");
    }
} else {
    header("location: ../login.php");
}

// Import database
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $tele = $_POST['Tele'];
    $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? intval($_POST['branch_id']) : null;
    $password = $_POST['password'];
    $cpassword = $_POST['cpassword'];
    
    // Check if passwords match
    if ($password != $cpassword) {
        header("location: dentist.php?action=add&error=2");
        exit;
    }
    
    // Check if email already exists
    $sql = "SELECT * FROM webuser WHERE email = '$email'";
    $result = $database->query($sql);
    if ($result->num_rows > 0) {
        header("location: dentist.php?action=add&error=1");
        exit;
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Set default photo path
    $photo_new_name = "default.jpg";
    
    // Handle image upload if present
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $photo = $_FILES['photo'];
        $photo_name = $photo['name'];
        $photo_tmp = $photo['tmp_name'];
        $photo_size = $photo['size'];
        
        // Generate a unique filename
        $photo_ext = pathinfo($photo_name, PATHINFO_EXTENSION);
        $photo_new_name = uniqid('dentist_', true) . '.' . $photo_ext;
        
        // Define the upload directory (in the same directory)
        $upload_dir = 'uploads/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Move the uploaded file to the directory
        if (!move_uploaded_file($photo_tmp, $upload_dir . $photo_new_name)) {
            header("location: dentist.php?action=add&error=3");
            exit;
        }
    }
    
    // Begin transaction to ensure both queries succeed or fail together
    $database->begin_transaction();
    
    try {
        // Insert dentist data
    // Insert doctor, include branch_id if provided
    if ($branch_id !== null) {
        $sql1 = "INSERT INTO doctor (docname, docemail, docpassword, doctel, photo, branch_id) 
        VALUES ('$name', '$email', '$hashed_password', '$tele', '$photo_new_name', $branch_id)";
    } else {
        $sql1 = "INSERT INTO doctor (docname, docemail, docpassword, doctel, photo) 
        VALUES ('$name', '$email', '$hashed_password', '$tele', '$photo_new_name')";
    }
        $database->query($sql1);
        
        // Insert web user data
        $sql2 = "INSERT INTO webuser (email, usertype) VALUES ('$email', 'd')";
        $database->query($sql2);
        
        // Commit transaction
        $database->commit();
        header("location: dentist.php?action=add&error=4");
    } catch (Exception $e) {
        // Rollback transaction on error
        $database->rollback();
        header("location: dentist.php?action=add&error=3");
    }
} else {
    header("location: dentist.php?action=add&error=3");
}
?>