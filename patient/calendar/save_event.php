<?php
// 1. Clean output buffer
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

session_start();

// 2. Define correct paths
$connectionFile = __DIR__ . '/../../connection.php';
$configFile = __DIR__ . '/../../api/paymongo_config.php';

// 3. Check if files exist
if (!file_exists($configFile)) {
    ob_end_clean();
    echo json_encode(['status' => false, 'msg' => 'Error: api/paymongo_config.php not found.']);
    exit;
}

require_once($connectionFile);
require_once($configFile);

$response = ['status' => false, 'msg' => 'Unknown error occurred'];

if(isset($_POST['event_name'])) {
    // --- FIX 1: GET PATIENT ID CORRECTLY ---
    if (isset($_SESSION['user'])) {
        $useremail = $_SESSION['user'];
        $patientQuery = $database->query("SELECT pid FROM patient WHERE pemail='$useremail'");
        if ($patientQuery && $patientQuery->num_rows > 0) {
            $patientRow = $patientQuery->fetch_assoc();
            $pid = $patientRow['pid'];
        } else {
             ob_end_clean();
             echo json_encode(['status' => false, 'msg' => 'Error: Patient not found.']);
             exit;
        }
    } else {
        ob_end_clean();
        echo json_encode(['status' => false, 'msg' => 'Error: Session expired. Please login again.']);
        exit;
    }
    // ---------------------------------------

    $event_name = mysqli_real_escape_string($database, $_POST['event_name']);
    $procedure_id = mysqli_real_escape_string($database, $_POST['procedure']);
    $date = mysqli_real_escape_string($database, $_POST['appointment_date']);
    $time = mysqli_real_escape_string($database, $_POST['appointment_time']);
    $docid = mysqli_real_escape_string($database, $_POST['docid']);

    // --- FIX 2: HANDLING PRICE ---
    $total_amount = 0;
    $procedure_name = "Dental Service";
    
    // We select 'price' now (make sure you ran the SQL Step 1)
    $procQuery = $database->query("SELECT price, procedure_name FROM procedures WHERE procedure_id='$procedure_id'");
    
    if($procQuery && $procQuery->num_rows > 0){
        $row = $procQuery->fetch_assoc();
        // If price is NULL or missing, default to 0
        $total_amount = isset($row['price']) ? $row['price'] : 0;
        $procedure_name = $row['procedure_name'];
    }

    // Insert Appointment
    // determine branch: prefer session active branch, fallback to patient's first mapping or patient.branch_id
    $branch_id = null;
    if (!empty($_SESSION['active_branch_id'])) {
        $branch_id = (int)$_SESSION['active_branch_id'];
    } else {
        // try patient_branches
        $pb = $database->query("SELECT branch_id FROM patient_branches WHERE pid='" . $database->real_escape_string($pid) . "' LIMIT 1");
        if ($pb && $pb->num_rows>0) {
            $branch_id = (int)$pb->fetch_assoc()['branch_id'];
        } else {
            // fallback to patient.branch_id
            $bres = $database->query("SELECT branch_id FROM patient WHERE pid='" . $database->real_escape_string($pid) . "' LIMIT 1");
            if ($bres && $bres->num_rows>0) $branch_id = (int)$bres->fetch_assoc()['branch_id'];
        }
    }

    $branch_sql_fragment = is_null($branch_id) ? 'NULL' : "'" . $database->real_escape_string($branch_id) . "'";

    $insert_query = "INSERT INTO appointment (pid, docid, procedure_id, appodate, appointment_time, event_name, branch_id, status, payment_status, total_amount) 
                     VALUES ('$pid', '$docid', '$procedure_id', '$date', '$time', '$event_name', $branch_sql_fragment, 'pending_reservation', 'unpaid', '$total_amount')";

    if($database->query($insert_query)) {
        $appoid = $database->insert_id;

        // PayMongo Session
        $description = "Reservation: $procedure_name";
        $success_url = "http://localhost/IHeartDentistDC/patient/payment_success.php?id=$appoid&type=reservation";
        $cancel_url = "http://localhost/IHeartDentistDC/patient/calendar/calendar.php?error=cancelled";

        if (function_exists('createPayMongoSession')) {
            $result = createPayMongoSession(250, $description, $success_url, $cancel_url);

            if (isset($result['data']['attributes']['checkout_url'])) {
                $response = [
                    'status' => true, 
                    'msg' => 'Redirecting...', 
                    'payment_url' => $result['data']['attributes']['checkout_url']
                ];
            } else {
                // API Error
                $database->query("DELETE FROM appointment WHERE appoid='$appoid'");
                $apiError = isset($result['errors'][0]['detail']) ? $result['errors'][0]['detail'] : 'Unknown API Error';
                $response = ['status' => false, 'msg' => 'Payment Error: ' . $apiError];
            }
        } else {
             $database->query("DELETE FROM appointment WHERE appoid='$appoid'");
             $response = ['status' => false, 'msg' => 'Error: createPayMongoSession function missing.'];
        }
    } else {
        $response = ['status' => false, 'msg' => 'Database Error: ' . $database->error];
    }
} else {
    $response = ['status' => false, 'msg' => 'Invalid Request Data'];
}

ob_end_clean();
echo json_encode($response);
exit;
?>
