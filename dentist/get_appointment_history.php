<?php
session_start();
if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

include("../connection.php");

$docid = $_SESSION['userid'];

if (!isset($_GET['pid'])) {
    http_response_code(400);
    echo "Missing patient id";
    exit();
}

$pid = intval($_GET['pid']);

// Verify patient belongs to this dentist
$verify = $database->prepare("SELECT 1 FROM appointment WHERE pid = ? AND docid = ? LIMIT 1");
$verify->bind_param("ii", $pid, $docid);
$verify->execute();
$verifyRes = $verify->get_result();
if ($verifyRes->num_rows === 0) {
    http_response_code(403);
    echo "No appointment history for this patient.";
    exit();
}

// Fetch full appointment information for this dentist & patient (no payment amounts)
$sql = "SELECT 
            a.appodate,
            a.appointment_time,
            a.status,
            IF(
                COUNT(ap.procedure_id) > 0,
                GROUP_CONCAT(DISTINCT p2.procedure_name ORDER BY p2.procedure_name SEPARATOR ', '),
                p.procedure_name
            ) AS procedures
    FROM appointment a
    LEFT JOIN procedures p ON a.procedure_id = p.procedure_id
    LEFT JOIN appointment_procedures ap ON a.appoid = ap.appointment_id
    LEFT JOIN procedures p2 ON ap.procedure_id = p2.procedure_id
    WHERE a.pid = ? AND a.docid = ?
    GROUP BY a.appoid, a.appodate, a.appointment_time, a.status, p.procedure_name
    ORDER BY a.appodate DESC, a.appointment_time DESC";

$stmt = $database->prepare($sql);
$stmt->bind_param("ii", $pid, $docid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<p style="text-align:center; padding:20px;">No appointment history found for this patient.</p>';
    exit();
}

echo '<table class="table" style="width:100%; font-size:14px;">';
echo '<thead><tr>';
echo '<th>Date</th><th>Time</th><th>Procedure</th><th>Status</th>';
echo '</tr></thead><tbody>';

while ($row = $result->fetch_assoc()) {
    $date = htmlspecialchars($row['appodate']);
    $time = htmlspecialchars($row['appointment_time']);
    $procedure = htmlspecialchars($row['procedures'] ?? '');
    $status = htmlspecialchars($row['status']);

    echo '<tr>';
    echo '<td>' . $date . '</td>';
    echo '<td>' . $time . '</td>';
    echo '<td>' . $procedure . '</td>';
    echo '<td>' . $status . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
