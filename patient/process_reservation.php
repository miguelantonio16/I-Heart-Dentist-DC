<?php
session_start();
include("../connection.php");
include("../api/paymongo_config.php"); // Ensure you created this file from the previous step
require_once __DIR__ . '/../inc/redirect_helper.php';

if ($_POST) {
    $pid = $_SESSION['id']; // Patient ID
    $scheduleid = $_POST['scheduleid'];
    $date = $_POST['date'];

    // Patient flow now does not set procedure or price; defer to admin.
    $procedure_id = 0;
    $total_amount = 0;

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

    // Conflict check: prevent multiple patients reserving same schedule slot concurrently
    $conflict_sql = "SELECT appoid FROM appointment WHERE scheduleid='".$database->real_escape_string($scheduleid)."' AND status IN ('pending_reservation','booking','appointment') LIMIT 1";
    $conflict_res = $database->query($conflict_sql);
    if ($conflict_res && $conflict_res->num_rows > 0) {
        echo "This time slot is no longer available. Please choose another.";
        exit();
    }

        $sql = "INSERT INTO appointment (pid, scheduleid, appodate, procedure_id, branch_id, status, payment_status, total_amount) 
            VALUES ('".$database->real_escape_string($pid)."', '".$database->real_escape_string($scheduleid)."', '".$database->real_escape_string($date)."', 0, $branch_sql_fragment, 'pending_reservation', 'unpaid', 0)";
    
    if ($database->query($sql)) {
        $appoid = $database->insert_id; // Get the new ID

        // 3. Create PayMongo Session for P250
        $description = "Reservation Fee for Appointment #$appoid";
        
        // URLs where PayMongo will send the user back to
        // Build success URL using BASE_URL if defined, else derive from current host
        $base = defined('BASE_URL') ? rtrim(BASE_URL,'/') : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http') . '://' . $_SERVER['HTTP_HOST'] . '/IHeartDentistDC';
        $success_url = $base . "/patient/payment_success.php?id=$appoid&type=reservation";
        // Build a cancel URL that attempts to preserve context using get_redirect_url().
        $cancel_rel = get_redirect_url('booking.php', ['error' => 'cancelled'], true);
        // If get_redirect_url returned a full URL (referrer), use it; otherwise prefix with current host/path
        if (parse_url($cancel_rel, PHP_URL_SCHEME) !== null) {
            $cancel_url = $cancel_rel;
        } else {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            // Determine base path to the patient folder
            $basePath = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
            $cancel_url = $scheme . '://' . $host . $basePath . '/' . ltrim($cancel_rel, '/');
            // Normalize backslashes to forward slashes to ensure valid URL on Windows
            $cancel_url = str_replace('\\', '/', $cancel_url);
        }

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
