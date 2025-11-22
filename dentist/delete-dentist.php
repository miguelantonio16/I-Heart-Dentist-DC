<?php
    session_start();

    if(isset($_SESSION["user"])){
        if(($_SESSION["user"])=="" or $_SESSION['usertype']!='d'){
            header("location: ../dentist/login.php");
            exit();
        }
    } else {
        header("location: ../dentist/login.php");
        exit();
    }

    if(isset($_GET['id'])){
        // Import database connection
        include("../connection.php");

        // Get the doctor ID from the GET request and sanitize it
        $id = (int)$_GET['id']; // Ensure $id is an integer to prevent SQL injection

        // Fetch the doctor's email using the ID
        $result001 = $database->query("SELECT * FROM doctor WHERE docid = $id");

        if($result001 && $result001->num_rows > 0) {
            $doctor = $result001->fetch_assoc();
            $email = $doctor['docemail'];

            // Delete the posts associated with the doctor
            $sql = $database->prepare("DELETE FROM posts WHERE docid = ?");
            $sql->bind_param("i", $id); // Bind the doctor ID
            $sql->execute();

            // Delete from the webuser table where email matches
            $sql = $database->prepare("DELETE FROM webuser WHERE email = ?");
            $sql->bind_param("s", $email); // Bind the email parameter
            $sql->execute();

            // Delete the doctor profile from the doctor table
            $sql = $database->prepare("DELETE FROM doctor WHERE docid = ?");
            $sql->bind_param("i", $id); // Bind the doctor ID
            $sql->execute();

            session_start();

	$_SESSION = array();

	if (isset($_COOKIE[session_name()])) {
		setcookie(session_name(), '', time()-86400, '/');
	}
            // After deletion, redirect to the login page
            
            session_destroy();

	// redirecting the user to the login page
	header('Location: login.php?action=logout');
            exit();
        } else {
            // Handle the case where the doctor record is not found
            echo "Doctor not found!";
            exit();
        }
    } else {
        // Handle the case where the ID is not passed in the URL
        echo "Invalid request!";
        exit();
    }
?>
