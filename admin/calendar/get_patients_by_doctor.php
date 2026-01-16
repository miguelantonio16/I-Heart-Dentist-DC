<?php
header('Content-Type: application/json');
require_once '../../connection.php';
session_start();

$docid = isset($_GET['docid']) ? (int)$_GET['docid'] : 0;
if ($docid <= 0) {
    echo json_encode(['status' => false, 'patients' => [], 'msg' => 'Invalid doctor']);
    exit;
}

// Detect if patient table has a branch_id column
$hasPatientBranch = false;
$colCheck = $database->query("SHOW COLUMNS FROM patient LIKE 'branch_id'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasPatientBranch = true;
}

$docidEsc = $database->real_escape_string($docid);

// Build subquery for doctor branches (includes legacy single branch and multi-branch mapping)
$doctorBranchesSub = "SELECT branch_id FROM doctor_branches WHERE docid='$docidEsc' UNION SELECT branch_id FROM doctor WHERE docid='$docidEsc'";

// Construct WHERE conditions depending on patient schema
$conditions = [];
$conditions[] = "pb.branch_id IN ($doctorBranchesSub)"; // patient_branches mapping always checked
if ($hasPatientBranch) {
    $conditions[] = "p.branch_id IN ($doctorBranchesSub)";
}
$whereClause = '(' . implode(' OR ', $conditions) . ')';

$query = "SELECT DISTINCT p.pid, p.pname
          FROM patient p
          LEFT JOIN patient_branches pb ON pb.pid = p.pid
          WHERE $whereClause AND p.status = 'active'
          ORDER BY p.pname ASC";

$res = $database->query($query);
$patients = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $patients[] = [
            'pid' => $row['pid'],
            'pname' => $row['pname']
        ];
    }
}

echo json_encode(['status' => true, 'patients' => $patients]);
