<?php
require 'database_connection.php';

$CAL_DEBUG = defined('APP_DEBUG') && APP_DEBUG;

function cal_safe_query($mysqli, $sql, $label) {
    $res = mysqli_query($mysqli, $sql);
    if ($res === false) {
        error_log('[CAL] Query failed (' . $label . '): ' . mysqli_error($mysqli) . ' SQL: ' . $sql);
    }
    return $res;
}

session_start();
if (!isset($_SESSION['user'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => false, 'msg' => 'Error: User not logged in.']);
    exit;
}

$useremail = mysqli_real_escape_string($con, $_SESSION['user']);
$patient_query = "SELECT pid, pname FROM patient WHERE pemail = '$useremail'";
$patient_result = cal_safe_query($con, $patient_query, 'fetch patient by email');
if (!$patient_result || mysqli_num_rows($patient_result) == 0) {
    header('Content-Type: application/json');
    echo json_encode(['status' => false, 'msg' => 'Error: Patient not found.']);
    exit;
}
$patient_row = mysqli_fetch_assoc($patient_result);
$patient_id = $patient_row['pid'];
$patient_name = $patient_row['pname'];

$dentist_id = isset($_GET['dentist_id']) ? mysqli_real_escape_string($con, $_GET['dentist_id']) : null;
$branch_id  = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? mysqli_real_escape_string($con, $_GET['branch_id']) : null;

if ($dentist_id) {
    $display_query = "SELECT a.appoid,a.appodate,a.appointment_time,a.event_name,a.docid,a.pid,a.status,p.procedure_name,d.docname AS dentist_name FROM appointment a LEFT JOIN procedures p ON a.procedure_id=p.procedure_id LEFT JOIN doctor d ON a.docid=d.docid WHERE a.docid='$dentist_id' AND a.status IN ('pending_reservation','booking','appointment','completed')";
    if ($branch_id) {
        $display_query .= " AND (a.branch_id='$branch_id' OR a.branch_id IS NULL)";
    }
} else {
    $display_query = "SELECT a.appoid,a.appodate,a.appointment_time,a.event_name,a.docid,a.pid,a.status,p.procedure_name,d.docname AS dentist_name FROM appointment a LEFT JOIN procedures p ON a.procedure_id=p.procedure_id LEFT JOIN doctor d ON a.docid=d.docid WHERE a.pid='$patient_id' AND a.status IN ('booking','appointment','completed')";
    if ($branch_id) {
        $display_query .= " AND (a.branch_id='$branch_id' OR a.branch_id IS NULL)";
    }
}

// Mark past appointments completed for this patient
cal_safe_query($con, "UPDATE appointment SET status='completed' WHERE appodate < CURDATE() AND status IN ('appointment','booking') AND pid='$patient_id'", 'mark past appointments completed');

$results = cal_safe_query($con, $display_query, 'main appointment fetch');
$data_arr = [];
if ($results && mysqli_num_rows($results) > 0) {
    while ($data_row = mysqli_fetch_assoc($results)) {
        $status = $data_row['status'];
        $bookingColor = '#F9C74F';
        $appointmentColor = '#90EE90';
        $completedColor = '#BBBBBB';
        $timeslotTakenColor = '#F9A15D';
        if ($status === 'completed') $event_color = $completedColor; elseif ($status === 'appointment') $event_color = $appointmentColor; else $event_color = $bookingColor;
        $is_self = ($data_row['pid'] == $patient_id);
        if ($is_self) {
            $rawEvent = $data_row['event_name'] ?? '';
            $normEvent = preg_replace('/^My\s+/i', '', $rawEvent);
            $normEvent = str_ireplace(["Patient's Choice", "Patients Choice", "Patient Choice"], 'Patient Preference', $normEvent);
            if (in_array($status, ['booking','pending_reservation'])) $statusLabel = 'Booking'; elseif ($status === 'appointment') $statusLabel = 'Appointment'; elseif ($status === 'completed') $statusLabel = 'Completed'; else $statusLabel = ucfirst($status);
            $title = $statusLabel . ': ' . $patient_name . ' with ' . $data_row['dentist_name'];
            $procedure_name = $data_row['procedure_name'];
            $patient_name_out = $patient_name;
        } else {
            $title = ($status === 'pending_reservation') ? 'Reserved Slot' : 'Booked Slot';
            $procedure_name = null;
            $patient_name_out = null;
            $appodate = $data_row['appodate'];
            $today = date('Y-m-d');
            if ($status === 'completed' && strtotime($appodate) < strtotime($today)) {
                $event_color = $completedColor; // past completed stays grey
            } else {
                $event_color = $timeslotTakenColor; // occupied slot color
            }
        }
        // Compute a 30-minute end time so events have visible duration in month/week views
        $start_dt = strtotime($data_row['appodate'].' '.$data_row['appointment_time']);
        $end_dt = $start_dt + (30 * 60); // +30 minutes
        $data_arr[] = [
            'appointment_id' => $data_row['appoid'],
            'title' => $title,
            'start' => date('Y-m-d H:i:s', $start_dt),
            'end'   => date('Y-m-d H:i:s', $end_dt),
            'status' => $status,
            'procedure_name' => $procedure_name,
            'patient_name' => $patient_name_out,
            'dentist_name' => $data_row['dentist_name'],
            'color' => $event_color,
            'is_self' => $is_self
        ];
    }
}

if ($dentist_id) {
    cal_safe_query($con, "CREATE TABLE IF NOT EXISTS doctor_non_working_days (id INT AUTO_INCREMENT PRIMARY KEY, docid INT NOT NULL, date DATE NOT NULL, description VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", 'ensure doctor_non_working_days');
    $stmt = $con->prepare("SELECT date, description, docid FROM doctor_non_working_days WHERE docid=?");
    $stmt->bind_param('i', $dentist_id);
    $stmt->execute();
    $nw_result = $stmt->get_result();
    while ($row = $nw_result->fetch_assoc()) {
        $data_arr[] = [
            'title' => $row['description'],
            'start' => $row['date'],
            'color' => '#F94144',
            'type' => 'non-working',
            'docid' => $row['docid']
        ];
    }
    $stmt->close();
}

$payload = [
    'status' => !empty($data_arr),
    'msg' => !empty($data_arr) ? 'Successfully fetched appointments!' : 'No appointments found.',
    'data' => $data_arr
];
if ($CAL_DEBUG) {
    $payload['debug'] = [
        'dentist_id' => $dentist_id,
        'branch_id' => $branch_id,
        'row_count' => count($data_arr)
    ];
}
header('Content-Type: application/json');
// Encode once and terminate script to prevent stray output corrupting JSON.
$json = json_encode($payload);
if ($json === false) {
    error_log('[CAL] json_encode failed: ' . json_last_error_msg());
    $json = '{"status":false,"msg":"JSON encoding failure"}';
}
echo $json;
exit; // critical: no closing PHP tag, halts further output