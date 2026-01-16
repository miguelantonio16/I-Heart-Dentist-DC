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

// default response
$response = ['status' => false, 'msg' => 'Unknown error occurred'];

// Accept any POST request and gracefully handle missing fields (client may omit event_name)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // helpful debug: capture posted keys when debugging enabled (commented out in prod)
    // error_log('save_event POST keys: ' . implode(',', array_keys($_POST)));
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

    // Use provided event_name or a safe default
    $event_name = isset($_POST['event_name']) && $_POST['event_name'] !== '' ? mysqli_real_escape_string($database, $_POST['event_name']) : 'My Appointment';
    // No procedure selected by patient; store 0 (or NULL) placeholder
    $procedure_id = 0;
    $date = mysqli_real_escape_string($database, $_POST['appointment_date']);
    $time = mysqli_real_escape_string($database, $_POST['appointment_time']);
    $docid = mysqli_real_escape_string($database, $_POST['docid']);

    // Require time selection
    if ($time === '' || $time === null) {
        ob_end_clean();
        echo json_encode(['status' => false, 'msg' => 'Please select a time before confirming.']);
        exit;
    }

    // --- NEW VALIDATION: Prevent booking in the past (date or time) ---
    $now = new DateTime('now', new DateTimeZone('Asia/Singapore'));
    $todayStr = $now->format('Y-m-d');
    $currentTimeStr = $now->format('H:i:s');

    if ($date < $todayStr) {
        ob_end_clean();
        echo json_encode(['status' => false, 'msg' => 'Cannot book on a past date.']);
        exit;
    }
    if ($date === $todayStr && $time <= $currentTimeStr) {
        ob_end_clean();
        echo json_encode(['status' => false, 'msg' => 'Cannot book a time that has already passed.']);
        exit;
    }

    // --- SLOT CONFLICT CHECK -------------------------------------------------
    // Prevent booking if another patient already has this dentist+date+time
    // Block any active/held status including pending_reservation to avoid race conditions.
    $conflict_sql = "SELECT appoid FROM appointment WHERE docid='$docid' AND appodate='$date' AND appointment_time='$time' AND status IN ('pending_reservation','booking','appointment') LIMIT 1";
    $conflict_res = $database->query($conflict_sql);
    if ($conflict_res && $conflict_res->num_rows > 0) {
        ob_end_clean();
        echo json_encode(['status' => false, 'msg' => 'Selected time slot is no longer available. Please choose another time.']);
        exit;
    }
    // -------------------------------------------------------------------------

    // Patient does not set procedure or price; total_amount remains 0 until admin assigns.
    $total_amount = 0;

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
                     VALUES ('".$database->real_escape_string($pid)."', '".$database->real_escape_string($docid)."', 0, '".$database->real_escape_string($date)."', '".$database->real_escape_string($time)."', '".$database->real_escape_string($event_name)."', $branch_sql_fragment, 'pending_reservation', 'unpaid', 0)";

    if($database->query($insert_query)) {
        $appoid = $database->insert_id;

        // PayMongo Session (appointment stays pending until payment success)
        $description = "Reservation Fee";
        // Build success/cancel URLs from actual filesystem paths to avoid path mismatches causing 404.
        $scheme = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
        $host = $_SERVER['HTTP_HOST'];
        $successFile = realpath(__DIR__ . '/../../patient/payment_success.php');
        $cancelFile  = realpath(__DIR__ . '/calendar.php');
        $docRoot = rtrim(realpath($_SERVER['DOCUMENT_ROOT']), DIRECTORY_SEPARATOR);
        $successRel = $successFile ? str_replace($docRoot, '', $successFile) : '/patient/payment_success.php';
        $cancelRel  = $cancelFile ? str_replace($docRoot, '', $cancelFile) : '/patient/calendar/calendar.php';
        // Normalize Windows backslashes to forward slashes for URL safety
        $successRel = str_replace('\\', '/', $successRel);
        $cancelRel = str_replace('\\', '/', $cancelRel);
        if ($successRel[0] !== '/') { $successRel = '/' . $successRel; }
        if ($cancelRel[0] !== '/') { $cancelRel = '/' . $cancelRel; }
        $success_url = $scheme . '://' . $host . $successRel . "?id=$appoid&type=reservation";
        $cancel_url  = $scheme . '://' . $host . $cancelRel . "?error=cancelled&appoid=$appoid";

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
    // Not a POST request
    $response = ['status' => false, 'msg' => 'Invalid Request Method'];
}

ob_end_clean();
echo json_encode($response);
exit;
?>
