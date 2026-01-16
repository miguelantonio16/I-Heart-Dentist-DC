<?php
session_start();
header('Content-Type: application/json');

include_once __DIR__ . '/../connection.php';

if (!isset($_SESSION['user']) || ($_SESSION['usertype'] ?? '') !== 'p') {
    echo json_encode(['status' => false, 'msg' => 'Unauthorized']);
    exit;
}

$useremail = $_SESSION['user'];
$res = $database->query("SELECT pid FROM patient WHERE pemail='" . $database->real_escape_string($useremail) . "' LIMIT 1");
if (!$res || $res->num_rows === 0) {
    echo json_encode(['status' => false, 'msg' => 'Patient not found']);
    exit;
}
$pid = (int)$res->fetch_assoc()['pid'];

$appoid = isset($_POST['appoid']) ? (int)$_POST['appoid'] : 0;
if ($appoid <= 0) {
    echo json_encode(['status' => false, 'msg' => 'Invalid appointment id']);
    exit;
}

// Only delete if still pending and owned by patient
$del = $database->query("DELETE FROM appointment WHERE appoid='" . $database->real_escape_string($appoid) . "' AND pid='" . $database->real_escape_string($pid) . "' AND status='pending_reservation'");
if ($del) {
    echo json_encode(['status' => true]);
} else {
    echo json_encode(['status' => false, 'msg' => 'Failed to cancel pending reservation']);
}
