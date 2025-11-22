<?php
session_start();
include("../connection.php");
include("../api/paymongo_config.php"); // Ensure you created this file from the previous step

if ($_POST) {
    $pid = $_SESSION['id']; // Patient ID
    $scheduleid = $_POST['scheduleid'];
    $date = $_POST['date'];
    
    // 1. Get Procedure Price (You need to ensure your form sends 'procedure_id')
    // If your form doesn't send procedure_id, we might need to fetch it or use a default
    $procedure_id = isset($_POST['procedure_id']) ? $_POST['procedure_id'] : 0; 
    
    // Default to 0 or fetch from DB if you have a procedure selected
    $total_amount = 0; 
    if($procedure_id != 0){
        $procQuery = $database->query("SELECT price FROM procedures WHERE procedure_id='$procedure_id'");
        if($procQuery->num_rows > 0){
             $total_amount = $procQuery->fetch_assoc()['price'];
        }
    }

    // 2. Insert Appointment with 'pending_reservation' status
    // This creates the record but marks it as unpaid/pending
    // determine branch id: prefer session active branch, fallback to patient.branch_id
    $branch_id = isset($_SESSION['active_branch_id']) ? (int)$_SESSION['active_branch_id'] : null;
    if (empty($branch_id)) {
        $bres = $database->query("SELECT branch_id FROM patient WHERE pid='" . $database->real_escape_string($pid) . "' LIMIT 1");
        if ($bres && $bres->num_rows>0) {
            $branch_id = (int)$bres->fetch_assoc()['branch_id'];
        }
    }

    $branch_sql_fragment = is_null($branch_id) ? 'NULL' : "'" . $database->real_escape_string($branch_id) . "'";

    $sql = "INSERT INTO appointment (pid, scheduleid, appodate, procedure_id, branch_id, status, payment_status, total_amount) 
            VALUES ('$pid', '$scheduleid', '$date', '$procedure_id', $branch_sql_fragment, 'pending_reservation', 'unpaid', '$total_amount')";
    
    if ($database->query($sql)) {
        $appoid = $database->insert_id; // Get the new ID

        // 3. Create PayMongo Session for P250
        $description = "Reservation Fee for Appointment #$appoid";
        
        // URLs where PayMongo will send the user back to
        $success_url = "http://localhost/IHeartDentistDC/patient/payment_success.php?id=$appoid&type=reservation";
        $cancel_url = "http://localhost/IHeartDentistDC/patient/booking.php?error=cancelled";

        $result = createPayMongoSession(250, $description, $success_url, $cancel_url);

        if (isset($result['data']['attributes']['checkout_url'])) {
            // 4. REDIRECT TO PAYMONGO
            header("Location: " . $result['data']['attributes']['checkout_url']);
            exit();
        } else {
            // Fallback if API fails
            echo "Payment Gateway Error. Please try again or contact support.";
            // Optional: Delete the pending appointment since payment failed
            $database->query("DELETE FROM appointment WHERE appoid='$appoid'");
        }
    } else {
        echo "Database Error: " . $database->error;
    }
}
?>
