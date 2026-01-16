<?php
require 'database_connection.php';

session_start();
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => false, 'msg' => 'Error: User not logged in.']);
    exit;
}

if (!isset($_GET['dentist_id']) || !isset($_GET['date'])) {
    echo json_encode(['status' => false, 'msg' => 'Error: Missing dentist ID or date.']);
    exit;
}

$dentist_id = mysqli_real_escape_string($con, $_GET['dentist_id']);
$date = mysqli_real_escape_string($con, $_GET['date']);
// optional branch filter
$branch_id = null;
if (isset($_GET['branch_id']) && $_GET['branch_id'] !== '') {
    $branch_id = mysqli_real_escape_string($con, $_GET['branch_id']);
}

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $date)) {
    echo json_encode(['status' => false, 'msg' => 'Error: Invalid date format. Use YYYY-MM-DD.']);
    exit;
}

$booked_times_query = "
    SELECT appointment_time, COUNT(*) as count 
    FROM appointment 
    WHERE docid = '$dentist_id' 
    AND appodate = '$date'
    AND status IN ('pending_reservation','booking','appointment')
    GROUP BY appointment_time
";

// If branch_id provided, add it to the WHERE clause
if (!empty($branch_id)) {
    $booked_times_query = "
    SELECT appointment_time, COUNT(*) as count 
    FROM appointment 
    WHERE docid = '$dentist_id' 
    AND appodate = '$date'
    AND branch_id = '$branch_id'
    AND status IN ('pending_reservation','booking','appointment')
    GROUP BY appointment_time
    ";
}

$result = mysqli_query($con, $booked_times_query);
$booked_times = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $booked_times[$row['appointment_time']] = (int)$row['count'];
    }
}

echo json_encode([
    'status' => true,
    'booked_times' => $booked_times
]);
?>