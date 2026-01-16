<?php
require 'database_connection.php';

// Start session to get the logged-in patient
session_start();
if (!isset($_SESSION['user'])) {
    $data = array(
        'status' => false,
        'msg' => 'Error: User not logged in.'
    );
    echo json_encode($data);
    exit;
}

if (isset($_GET['dentist_id']) && isset($_GET['date'])) {
    $dentist_id = $_GET['dentist_id'];
    $date = $_GET['date'];

    // Query to get booked times for the selected dentist and date
    // Only consider active/holding statuses so completed appointments don't block the slot
    $booked_times_query = "
        SELECT appointment_time FROM appointment 
        WHERE docid = '$dentist_id' 
        AND appodate = '$date'
        AND status IN ('pending_reservation','booking','appointment')
    ";

    // Respect optional branch filter if provided via GET
    if (isset($_GET['branch_id']) && $_GET['branch_id'] !== '') {
        $branch_id = $con->real_escape_string($_GET['branch_id']);
        $booked_times_query = "
            SELECT appointment_time FROM appointment 
            WHERE docid = '$dentist_id' 
            AND appodate = '$date'
            AND branch_id = '$branch_id'
            AND status IN ('pending_reservation','booking','appointment')
        ";
    }

    $result = mysqli_query($con, $booked_times_query);

    $booked_times = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $booked_times[] = $row['appointment_time'];
    }

    // Return booked times as JSON
    echo json_encode(array(
        'status' => true,
        'booked_times' => $booked_times
    ));
} else {
    echo json_encode(array(
        'status' => false,
        'msg' => 'Error: Missing dentist ID or date.'
    ));
}
?>
