<?php
require 'database_connection.php';

// Start session to get the logged-in dentist
session_start();
if (!isset($_SESSION['user'])) {
    $data = array(
        'status' => false,
        'msg' => 'Error: User not logged in.'
    );
    echo json_encode($data);
    exit;
}

// Get the logged-in dentist's ID (from session)
$useremail = $_SESSION['user'];
$dentist_query = "SELECT docid, docname FROM doctor WHERE docemail = '$useremail'";
$dentist_result = mysqli_query($con, $dentist_query);
$dentist_row = mysqli_fetch_assoc($dentist_result);
$dentist_id = $dentist_row['docid'];
$dentist_name = $dentist_row['docname'];  // Get dentist name

// Capture the selected date from the AJAX request (if available)
$selected_date = isset($_GET['selected_date']) ? $_GET['selected_date'] : null;

// Build the query to get all appointments assigned to this dentist
$display_query = "
    SELECT 
        a.appoid, 
        a.appodate, 
        a.appointment_time, 
        a.event_name, 
        a.pid, 
        a.status,
        p.procedure_name, 
        pt.pname AS patient_name
    FROM appointment a
    LEFT JOIN procedures p ON a.procedure_id = p.procedure_id
    LEFT JOIN patient pt ON a.pid = pt.pid
    WHERE a.docid = '$dentist_id' 
    AND a.status IN ('booking', 'appointment')
";

// If a selected date is provided, add a condition to filter by the appodate
if ($selected_date) {
    $display_query .= " AND a.appodate = '$selected_date'";  // Filter by selected date
}

$results = mysqli_query($con, $display_query);   
$count = mysqli_num_rows($results);  

if ($count > 0) {
    $data_arr = array();
    $i = 0; // Start index from 0
    while ($data_row = mysqli_fetch_array($results, MYSQLI_ASSOC)) {
        
        // Get all booked times for the specific dentist and date
        $appointments_query = "
            SELECT appointment_time 
            FROM appointment 
            WHERE docid = '{$dentist_id}' 
            AND appodate = '{$data_row['appodate']}' 
            AND status = 'booking'
        ";        
        $appointments_result = mysqli_query($con, $appointments_query);
        
        $booked_times = [];
        while ($appointment_row = mysqli_fetch_assoc($appointments_result)) {
            $booked_times[] = $appointment_row['appointment_time']; // Collect booked times
        }

        // Add event data to response
        $data_arr[$i]['appointment_id'] = $data_row['appoid'];
        $data_arr[$i]['title'] = $data_row['event_name'] . " with " . $data_row['patient_name']; // Display patient name
        $data_arr[$i]['start'] = date("Y-m-d H:i:s", strtotime($data_row['appodate'] . ' ' . $data_row['appointment_time']));
        $data_arr[$i]['end'] = date("Y-m-d H:i:s", strtotime($data_row['appodate'] . ' ' . $data_row['appointment_time']));
        $data_arr[$i]['status'] = $data_row['status']; // Add the status to the event data

        // Additional fields
        $data_arr[$i]['procedure_name'] = $data_row['procedure_name'];  // Add procedure name
        $data_arr[$i]['patient_name'] = $data_row['patient_name'];      // Add patient name
        $data_arr[$i]['dentist_name'] = $dentist_name;                 // Add dentist name

        // Set the color based on the status
        if ($data_row['status'] == 'appointment') {
            $data_arr[$i]['color'] = 'blue'; // Color for "appointment"
        } else {
            $data_arr[$i]['color'] = '#' . substr(uniqid(), -6); // Random color
        }

        $data_arr[$i]['booked_times'] = $booked_times;  // Include booked times for this dentist and date
        
        $i++;
    }

    $data = array(
        'status' => true,
        'msg' => 'Successfully fetched appointments!',
        'data' => $data_arr
    );
} else {
    $data = array(
        'status' => false,
        'msg' => 'Error: No appointments found.'                
    );
}

echo json_encode($data);
?>
