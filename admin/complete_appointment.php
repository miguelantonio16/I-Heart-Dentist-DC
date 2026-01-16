<?php
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" or $_SESSION['usertype'] != 'a') {
        header("location: ../login.php");
        exit();
    }
} else {
    header("location: ../login.php");
    exit();
}

include("../connection.php");
require_once __DIR__ . '/../inc/redirect_helper.php';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $step = isset($_GET['step']) ? $_GET['step'] : 'complete';

    // Always recalc total from stacked procedures before any status/payment change
    $totalRes = $database->query("SELECT COALESCE(SUM(agreed_price),0) AS total FROM appointment_procedures WHERE appointment_id=$id");
    $totalRow = $totalRes ? $totalRes->fetch_assoc() : ['total'=>0];
    $stackTotal = (float)$totalRow['total'];
    $database->query("UPDATE appointment SET total_amount=$stackTotal WHERE appoid=$id");

    if ($step === 'receive_cash') {
        // Confirm cash received
        $sql = "UPDATE appointment SET payment_status='paid', payment_method='cash' WHERE appoid='$id'";
    } else {
        // Clinical completion: require at least one assigned procedure
        $procCountRes = $database->query("SELECT COUNT(*) AS cnt FROM appointment_procedures WHERE appointment_id=$id");
        $procCount = ($procCountRes && ($r=$procCountRes->fetch_assoc())) ? (int)$r['cnt'] : 0;
        // Also consider legacy single procedure_id on appointment
        $apptRowRes = $database->query("SELECT COALESCE(procedure_id,0) AS procedure_id FROM appointment WHERE appoid=$id LIMIT 1");
        $apptRow = $apptRowRes ? $apptRowRes->fetch_assoc() : ['procedure_id'=>0];
        $hasLegacyProc = ((int)$apptRow['procedure_id'] !== 0);

        if ($procCount === 0 && !$hasLegacyProc) {
            // Block completion and redirect with descriptive error
            $returnPageParams = [];
            if (isset($_GET['page'])) { $returnPageParams['page'] = (int)$_GET['page']; }
            if (isset($_GET['search']) && $_GET['search'] !== '') { $returnPageParams['search'] = $_GET['search']; }
            if (isset($_GET['sort']) && $_GET['sort'] !== '') { $returnPageParams['sort'] = $_GET['sort']; }
            $params = array_merge(['error' => 'no_procedures_assigned'], $returnPageParams);
            redirect_with_context('appointment.php', $params, true);
            exit();
        }

        // Proceed to mark completed if procedures exist
        $sql = "UPDATE appointment SET status='completed' WHERE appoid='$id'";
    }

    // Preserve context (page/search/sort) when redirecting back
    $returnPageParams = [];
    if (isset($_GET['page'])) {
        $returnPageParams['page'] = (int)$_GET['page'];
    }
    if (isset($_GET['search']) && $_GET['search'] !== '') {
        $returnPageParams['search'] = $_GET['search'];
    }
    if (isset($_GET['sort']) && $_GET['sort'] !== '') {
        $returnPageParams['sort'] = $_GET['sort'];
    }

    if ($database->query($sql)) {
        // Build redirect URL using helper (preserves explicit params and safely falls back to referrer)
        $params = array_merge(['action' => 'completed'], $returnPageParams);
        redirect_with_context('appointment.php', $params, true);
    } else {
        $params = array_merge(['error' => 'completion_failed'], $returnPageParams);
        redirect_with_context('appointment.php', $params, true);
    }
}
?>