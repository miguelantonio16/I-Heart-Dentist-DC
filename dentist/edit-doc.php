<?php
// Import database
include("../connection.php");

if ($_POST) {
    // Fetch POST data
    $name = $_POST['name'];
    $oldemail = $_POST["oldemail"];
    $email = $_POST['email'];
    $tele = $_POST['Tele'];
    $password = $_POST['password'];
    $cpassword = $_POST['cpassword'];
    $id = $_POST['id00'];
    $error = '3'; // Default error code

    // Check if passwords match
    if ($password == $cpassword) {
        // Check if email already exists for another doctor
        $result = $database->query("SELECT doctor.docid FROM doctor INNER JOIN webuser ON doctor.docemail = webuser.email WHERE webuser.email = '$email';");
        $id2 = ($result->num_rows == 1) ? $result->fetch_assoc()["docid"] : $id;

        if ($id2 != $id) {
            $error = '1'; // Email already in use by another doctor
        } else {
            // Hash the password before updating
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // File upload handling
            $photoFileName = ""; // Default empty filename
            if (!empty($_FILES["photo"]["name"])) {
                $targetDir = "../admin/uploads/";
                $photoFileName = basename($_FILES["photo"]["name"]);
                $targetFilePath = $targetDir . $photoFileName;
                $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

                // Allow only image files
                $allowedTypes = ["jpg", "jpeg", "png", "gif"];
                if (in_array($fileType, $allowedTypes)) {
                    if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFilePath)) {
                        // Successfully uploaded
                    } else {
                        $error = '5'; // File upload failed
                    }
                } else {
                    $error = '6'; // Invalid file type
                }
            }

            // Update doctor info with profile photo if uploaded
            if ($photoFileName) {
                $sql1 = "UPDATE doctor SET docemail = '$email', docname = '$name', docpassword = '$hashedPassword', doctel = '$tele', photo = '$photoFileName' WHERE docid = $id;";
            } else {
                $sql1 = "UPDATE doctor SET docemail = '$email', docname = '$name', docpassword = '$hashedPassword', doctel = '$tele' WHERE docid = $id;";
            }
            $database->query($sql1);

            // Update webuser email (using the old email)
            $sql2 = "UPDATE webuser SET email = '$email' WHERE email = '$oldemail';";
            $database->query($sql2);

            // Set success error code
            $error = '4';
        }
    } else {
        $error = '2'; // Passwords do not match
    }
} else {
    $error = '3'; // No POST data received
}

// Redirect with error/success message
header("location: settings.php?action=edit&error=".$error."&id=".$id);
exit();
?>
