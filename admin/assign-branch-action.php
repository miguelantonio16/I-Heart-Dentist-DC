<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['usertype'] != 'a') {
    header('Location: ../login.php');
    exit;
}
include('../connection.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['assign_doctors']) && isset($_POST['doctor_branch'])) {
        foreach ($_POST['doctor_branch'] as $docid => $branch_id) {
            $docid = intval($docid);
            $branch = ($branch_id === "") ? 'NULL' : intval($branch_id);
            $sql = "UPDATE doctor SET branch_id = " . ($branch === 'NULL' ? 'NULL' : $branch) . " WHERE docid = $docid";
            $database->query($sql);
        }
    }

    if (isset($_POST['assign_patients']) && isset($_POST['patient_branch'])) {
        foreach ($_POST['patient_branch'] as $pid => $branch_id) {
            $pid = intval($pid);
            $branch = ($branch_id === "") ? 'NULL' : intval($branch_id);
            $sql = "UPDATE patient SET branch_id = " . ($branch === 'NULL' ? 'NULL' : $branch) . " WHERE pid = $pid";
            $database->query($sql);
        }
    }

    require_once __DIR__ . '/../inc/redirect_helper.php';
    redirect_with_context('assign-branches.php');
    exit;
}

require_once __DIR__ . '/../inc/redirect_helper.php';
redirect_with_context('assign-branches.php');
exit;
?>
