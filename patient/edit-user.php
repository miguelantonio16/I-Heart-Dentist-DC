<?php
// Import the database connection
include("../connection.php");

// Check if the form is submitted via POST
if ($_POST) {
    // Fetch existing records and POST data
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $pname = $fname . ' ' . $lname; // Combine into full name
    $oldemail = $_POST["oldemail"];
    $email = $_POST['email'];
    $tele = $_POST['Tele'];
    $password = $_POST['password'];
    $cpassword = $_POST['cpassword'];
    $id = $_POST['id00'];

    // Check if passwords match
    if ($password == $cpassword) {
        $error = '3';
        
        // Check if email already exists for another user
        $result = $database->query("SELECT patient.pid FROM patient INNER JOIN webuser ON patient.pemail = webuser.email WHERE webuser.email = '$email';");
        if ($result->num_rows == 1) {
            $id2 = $result->fetch_assoc()["pid"];
        } else {
            $id2 = $id;
        }

        // Check if the ID matches or the email is used by another user
        if ($id2 != $id) {
            $error = '1';
        } else {
            // Hash the password before updating
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Update patient information (including fname and lname)
            $sql1 = "UPDATE patient SET pemail = '$email', pname = '$pname', fname = '$fname', lname = '$lname', ppassword = '$hashedPassword', ptel = '$tele' WHERE pid = $id;";
            $database->query($sql1);

            // Update webuser email (using the old email)
            $sql2 = "UPDATE webuser SET email = '$email' WHERE email = '$oldemail';";
            $database->query($sql2);

            // Now update the medical history based on the patient ID
            $good_health = $_POST['good_health'];
            $under_treatment = $_POST['under_treatment'];
            $condition_treated = $_POST['condition_treated'];
            $serious_illness = $_POST['serious_illness'];
            $hospitalized = $_POST['hospitalized'];
            $medication = $_POST['medication'];
            $medication_specify = $_POST['medication_specify'];
            $tobacco = $_POST['tobacco'];
            $drugs = $_POST['drugs'];
            $allergies = isset($_POST['allergies']) ? implode(',', $_POST['allergies']) : '';  // Store allergies as a comma-separated string
            $blood_pressure = $_POST['blood_pressure'];
            $bleeding_time = $_POST['bleeding_time'];
            $health_conditions = $_POST['health_conditions'];

            // Update the medical history in the database
            $sql3 = "UPDATE medical_history SET 
                    good_health = ?, 
                    under_treatment = ?, 
                    condition_treated = ?, 
                    serious_illness = ?, 
                    hospitalized = ?, 
                    medication = ?, 
                    medication_specify = ?, 
                    tobacco = ?, 
                    drugs = ?, 
                    allergies = ?, 
                    blood_pressure = ?, 
                    bleeding_time = ?, 
                    health_conditions = ? 
                    WHERE email = ?";

            // Prepare the query to prevent SQL injection
            $stmt = $database->prepare($sql3);
            $stmt->bind_param(
                "ssssssssssssss", 
                $good_health, $under_treatment, $condition_treated, $serious_illness, 
                $hospitalized, $medication, $medication_specify, $tobacco, 
                $drugs, $allergies, $blood_pressure, $bleeding_time, $health_conditions, $email
            );

            // Execute the query for medical history update
            if ($stmt->execute()) {
                // On successful update, set success error code
                $error = '4';  // Success
            } else {
                // On failure, set error code for medical history update
                $error = '5';  // Failed to update medical history
            }
        }
    } else {
        $error = '2'; // Passwords do not match
    }
} else {
    // If no POST data, set a default error
    $error = '3';
}

// Redirect to settings page with the error code
header("Location: settings.php?action=edit&error=".$error."&id=".$id);
exit();
?>
