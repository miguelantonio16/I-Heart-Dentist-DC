<?php
include("../../connection.php");

$docid = isset($_GET['docid']) ? $_GET['docid'] : ''; // Get selected docid

$query = "SELECT * FROM appointment WHERE docid = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $docid);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = [
        'id' => $row['appoid'],
        'title' => $row['event_name'],
        'start' => $row['appodate'] . 'T' . $row['appointment_time'],
        'color' => ($row['status'] == 'appointment') ? 'green' : 'red'
    ];
}

echo json_encode($events);
?>
