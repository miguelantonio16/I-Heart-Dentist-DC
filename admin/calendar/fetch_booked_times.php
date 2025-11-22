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
    $booked_times_query = "
        SELECT appointment_time FROM appointment 
        WHERE docid = '$dentist_id' 
        AND appodate = '$date'
    ";
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
