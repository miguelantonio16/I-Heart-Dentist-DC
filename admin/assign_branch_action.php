<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['usertype'] !== 'a') { header('Location: login.php'); exit; }
include('../connection.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: assign_branch.php'); exit; }

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status'=>false,'msg'=>'Invalid CSRF token']);
        exit;
    }
    die('Invalid CSRF token');
}

// Process doctor_branch assignments
// Normalize legacy form keys like doctor_<id>[] and patient_<id>[] into arrays
$doctor_assignments = [];
$patient_assignments = [];
if (!empty($_POST['doctor_branch']) && is_array($_POST['doctor_branch'])) {
    $doctor_assignments = $_POST['doctor_branch'];
}
if (!empty($_POST['patient_branch']) && is_array($_POST['patient_branch'])) {
    $patient_assignments = $_POST['patient_branch'];
}
foreach ($_POST as $k => $v) {
    if (preg_match('/^doctor_(\d+)$/', $k, $m)) {
        $doctor_assignments[(int)$m[1]] = is_array($v) ? $v : [$v];
    }
    if (preg_match('/^patient_(\d+)$/', $k, $m)) {
        $patient_assignments[(int)$m[1]] = is_array($v) ? $v : [$v];
    }
}

// Process doctor assignments
if (!empty($doctor_assignments) && is_array($doctor_assignments)) {
    foreach ($doctor_assignments as $docid => $branches) {
        $docid = (int)$docid;
        $database->query("DELETE FROM doctor_branches WHERE docid='".$docid."'");
        if (is_array($branches) && count($branches) > 0) {
            foreach ($branches as $b) {
                $b = (int)$b;
                if ($b>0) $database->query("INSERT IGNORE INTO doctor_branches (docid, branch_id) VALUES ('$docid', '$b')");
            }
            $first = (int)$branches[0];
            $database->query("UPDATE doctor SET branch_id='".$first."' WHERE docid='".$docid."'");
        } else {
            $database->query("UPDATE doctor SET branch_id=NULL WHERE docid='".$docid."'");
        }
    }
}

// Process patient assignments (supports legacy form names)
if (!empty($patient_assignments) && is_array($patient_assignments)) {
    foreach ($patient_assignments as $pid => $branches) {
        $pid = (int)$pid;
        $database->query("DELETE FROM patient_branches WHERE pid='".$pid."'");
        if (is_array($branches) && count($branches) > 0) {
            foreach ($branches as $b) {
                $b = (int)$b;
                if ($b>0) $database->query("INSERT IGNORE INTO patient_branches (pid, branch_id) VALUES ('$pid', '$b')");
            }
            $first = (int)$branches[0];
            $database->query("UPDATE patient SET branch_id='".$first."' WHERE pid='".$pid."'");
        } else {
            $database->query("UPDATE patient SET branch_id=NULL WHERE pid='".$pid."'");
        }
    }
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['status'=>true,'msg'=>'Assignments saved']);
    exit;
} else {
    header('Location: assign_branch.php?success=1');
    exit;
}

?>
