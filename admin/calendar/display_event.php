<?php
require 'database_connection.php';

session_start();
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => false, 'msg' => 'Error: User not logged in.']);
    exit;
}

$useremail = $_SESSION['user'];
$admin_query = "SELECT * FROM admin WHERE aemail = '$useremail'";
$admin_result = mysqli_query($con, $admin_query);

if (mysqli_num_rows($admin_result) == 0) {
    echo json_encode(['status' => false, 'msg' => 'Error: User is not an admin.']);
    exit;
}

$selected_date = isset($_GET['selected_date']) ? $_GET['selected_date'] : null;
$dentist_id = isset($_GET['dentist_id']) ? $_GET['dentist_id'] : null;
$branch_id = isset($_GET['branch_id']) ? $_GET['branch_id'] : null;

// Mark past appointments as completed
mysqli_query($con, "UPDATE appointment SET status = 'completed' WHERE appodate < CURDATE() AND status IN ('appointment', 'booking')");

// Build query safely: append filters BEFORE GROUP BY
$display_query = "
    SELECT 
        a.appoid, a.appodate, a.appointment_time, a.event_name, a.pid, a.status,
        COALESCE(GROUP_CONCAT(DISTINCT pr.procedure_name ORDER BY pr.procedure_name SEPARATOR ', '), p.procedure_name) AS procedure_name,
        pt.pname AS patient_name, d.docname AS dentist_name,
        COALESCE(
            (
                SELECT GROUP_CONCAT(REPLACE(br.name, ' Branch', '') ORDER BY br.name SEPARATOR ', ')
                FROM doctor_branches db 
                JOIN branches br ON db.branch_id = br.id 
                WHERE db.docid = d.docid
            ),
            REPLACE(b.name, ' Branch', '')
        ) AS branch_display
    FROM appointment a
    LEFT JOIN procedures p ON a.procedure_id = p.procedure_id
    LEFT JOIN appointment_procedures ap2 ON a.appoid = ap2.appointment_id
    LEFT JOIN procedures pr ON ap2.procedure_id = pr.procedure_id
    LEFT JOIN patient pt ON a.pid = pt.pid
    LEFT JOIN doctor d ON a.docid = d.docid
    LEFT JOIN branches b ON d.branch_id = b.id
    WHERE a.status IN ('booking', 'appointment', 'completed')
";

if ($selected_date) $display_query .= " AND a.appodate = '" . mysqli_real_escape_string($con, $selected_date) . "'";
if ($dentist_id) $display_query .= " AND a.docid = '" . mysqli_real_escape_string($con, $dentist_id) . "'";
if ($branch_id) $display_query .= " AND d.branch_id = '" . mysqli_real_escape_string($con, $branch_id) . "'";

$display_query .= " GROUP BY a.appoid";

$results = mysqli_query($con, $display_query);
$data_arr = array();

// 1. FETCH APPOINTMENTS
if (mysqli_num_rows($results) > 0) {
    while ($data_row = mysqli_fetch_assoc($results)) {
        // Get booked times for this date
        $appointments_query = "SELECT appointment_time FROM appointment WHERE appodate = '{$data_row['appodate']}' AND status = 'booking'";        
        $appointments_result = mysqli_query($con, $appointments_query);
        $booked_times = [];
        while ($appointment_row = mysqli_fetch_assoc($appointments_result)) {
            $booked_times[] = $appointment_row['appointment_time']; 
        }

        // Determine Color
        $event_color = '#f5c447'; 
        if ($data_row['status'] == 'appointment') $event_color = '#0e8923';
        elseif ($data_row['status'] == 'booking') $event_color = '#f5c447';
        elseif ($data_row['status'] == 'completed') $event_color = 'grey';

        // Normalize event name for display: remove leading 'My', rename Patient's Choice, and add status prefix
        $rawEvent = $data_row['event_name'] ?? '';
        $normEvent = preg_replace('/^My\s+/i', '', $rawEvent);
        $normEvent = str_ireplace(["Patient's Choice", "Patients Choice", "Patient Choice"], 'Patient Preference', $normEvent);
        $statusLabel = ucfirst($data_row['status']);
        if (in_array($data_row['status'], ['booking','pending_reservation'])) $statusLabel = 'Booking';
        elseif ($data_row['status'] === 'appointment') $statusLabel = 'Appointment';
        elseif ($data_row['status'] === 'completed') $statusLabel = 'Completed';

        $titleLabel = $statusLabel . ': ' . $data_row['patient_name'];

        $data_arr[] = [
            'appointment_id' => $data_row['appoid'],
            'title' => $titleLabel,
            'start' => date("Y-m-d H:i:s", strtotime($data_row['appodate'] . ' ' . $data_row['appointment_time'])),
            'end' => date("Y-m-d H:i:s", strtotime($data_row['appodate'] . ' ' . $data_row['appointment_time'])),
            'status' => $data_row['status'],
            'procedure_name' => $data_row['procedure_name'],
            'patient_name' => $data_row['patient_name'],
                'dentist_name' => $data_row['dentist_name'],
                'branch_name' => !empty($data_row['branch_display']) ? $data_row['branch_display'] : null,
            'color' => $event_color,
            'booked_times' => $booked_times,
            'type' => 'appointment' // Important distinction
        ];
    }
}

// 2. FETCH NON-WORKING DAYS
// Fetch doctor-specific non-working days if a dentist is selected
if ($dentist_id) {
    // Ensure doctor_non_working_days table exists (safe-create)
    $create_sql = "CREATE TABLE IF NOT EXISTS doctor_non_working_days (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docid INT NOT NULL,
        date DATE NOT NULL,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($con, $create_sql);

    $nw_stmt = $con->prepare("SELECT date, description, docid FROM doctor_non_working_days WHERE docid = ?");
    $nw_stmt->bind_param("i", $dentist_id);
    $nw_stmt->execute();
    $nw_result = $nw_stmt->get_result();
    while ($row = $nw_result->fetch_assoc()) {
        $data_arr[] = [
            'title' => $row['description'],
            'start' => $row['date'],
            'color' => '#e23535',
            'type' => 'non-working', // Used by JS to identify deleting
            'allDay' => true,
            'docid' => $row['docid']
        ];
    }
    $nw_stmt->close();
} else {
    // If no dentist selected, include global non_working_days (legacy entries)
    $non_working_days_query = "SELECT * FROM non_working_days";
    $nw_result = mysqli_query($con, $non_working_days_query);
    if (mysqli_num_rows($nw_result) > 0) {
        while ($row = mysqli_fetch_assoc($nw_result)) {
            $data_arr[] = [
                'title' => $row['description'],
                'start' => $row['date'],
                'color' => '#e23535',
                'type' => 'non-working',
                'allDay' => true
            ];
        }
    }
}

// 3. RETURN RESPONSE
if (!empty($data_arr)) {
    echo json_encode(['status' => true, 'msg' => 'Successfully fetched events!', 'data' => $data_arr]);
} else {
    echo json_encode(['status' => false, 'msg' => 'No appointments found.']);
}
?>