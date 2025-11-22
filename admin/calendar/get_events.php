<?php
include("../../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $events = [];
    $branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : null;

    // Base query joins doctor so we can filter by doctor's branch if requested
    $query = "SELECT a.*, d.branch_id, b.name AS branch_name FROM appointment a LEFT JOIN doctor d ON a.docid = d.docid LEFT JOIN branches b ON d.branch_id = b.id";
    if ($branch_id) {
        $query .= " WHERE d.branch_id = " . $branch_id;
    }

    $result = $database->query($query);
    while ($row = $result->fetch_assoc()) {
        $events[] = [
            'id' => $row['appoid'],
            'title' => $row['event_name'],
            'start' => $row['appodate'] . 'T' . $row['appointment_time'],
            'doctor' => $row['docid'],
            'procedure' => $row['procedure_id'],
            'patient' => $row['pid'],
            'branch_id' => $row['branch_id'],
            'branch_name' => $row['branch_name']
        ];
    }
    echo json_encode($events);
    exit();
}
?>
