<?php
include("../connection.php"); // Make sure this path is correct

if (isset($_GET['docid'])) {
    $docid = $_GET['docid'];

    // Fetch active sessions for the selected dentist
    $query = "SELECT scheduleid, title, scheduledate, scheduletime 
              FROM schedule 
              WHERE docid = '$docid' AND status = 'active'";
    $result = $database->query($query);

    $sessions = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sessions[] = $row;
        }
    }

    // Return sessions as JSON
    echo json_encode($sessions);
}
?>
