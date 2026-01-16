<?php
date_default_timezone_set('Asia/Singapore');
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'a') {
        header("location: login.php");
    }
} else {
    header("location: login.php");
}

// Import database connection
include("../connection.php");
require_once __DIR__ . '/../inc/redirect_helper.php';

// Branch restriction (e.g., Bacoor-only admin)
$restrictedBranchId = isset($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id'] ? (int)$_SESSION['restricted_branch_id'] : 0;

// Get totals for right sidebar (respect branch restriction)
if ($restrictedBranchId > 0) {
    $doctorrow = $database->query("SELECT * FROM doctor WHERE status='active' AND (branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId));");
    $patientrow = $database->query("SELECT * FROM patient WHERE status='active' AND branch_id = $restrictedBranchId;");
    $appointmentrow = $database->query("SELECT * FROM appointment WHERE status='booking' AND docid IN (SELECT docid FROM doctor WHERE branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId));");
    $schedulerow = $database->query("SELECT * FROM appointment WHERE status='appointment' AND docid IN (SELECT docid FROM doctor WHERE branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId));");
} else {
    $doctorrow = $database->query("select * from doctor where status='active';");
    $patientrow = $database->query("select * from patient where status='active';");
    $appointmentrow = $database->query("select * from appointment where status='booking';");
    $schedulerow = $database->query("select * from appointment where status='appointment';");
}

// Load branches for filters
// Limit branches dropdown to restricted branch when applicable
if ($restrictedBranchId > 0) {
    $branchesResult = $database->query("SELECT id, name FROM branches WHERE id = $restrictedBranchId ORDER BY name ASC");
} else {
    $branchesResult = $database->query("SELECT id, name FROM branches ORDER BY name ASC");
}


// Pagination
$results_per_page = 10;

// Determine which page we're on
if (isset($_GET['page'])) {
    $page = $_GET['page'];
} else {
    $page = 1;
}

// Calculate the starting limit for SQL
$start_from = ($page - 1) * $results_per_page;

// Search functionality
// Branch filter
$selected_branch = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
if ($restrictedBranchId > 0) { $selected_branch = $restrictedBranchId; }
// Status filter: all / active / inactive (default to 'active' to keep previous behavior)
$currentStatus = isset($_GET['status']) ? $_GET['status'] : 'active';

$search = "";
$sort_param = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$sort_order = ($sort_param === 'oldest') ? 'DESC' : 'ASC';

    if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $query = "SELECT patient.*, (
                SELECT COALESCE(GROUP_CONCAT(DISTINCT b2.name ORDER BY b2.name SEPARATOR ', '), '')
                FROM branches b2
                WHERE b2.id IN (
                    SELECT branch_id FROM patient_branches WHERE pid = patient.pid
                    UNION
                    SELECT branch_id FROM appointment WHERE pid = patient.pid
                    UNION
                    SELECT branch_id FROM patient WHERE pid = patient.pid
                )
            ) AS branch_names
            FROM patient
            WHERE (patient.pname LIKE '%$search%' OR patient.pemail LIKE '%$search%' OR patient.ptel LIKE '%$search%')";
    if ($currentStatus !== 'all') {
        $query .= " AND patient.status='$currentStatus'";
    }
    if ($selected_branch > 0) {
        // Check branch in patient table or patient_branches or any appointment for that patient
        $query .= " AND (
            patient.branch_id = $selected_branch OR
            EXISTS(SELECT 1 FROM patient_branches pb WHERE pb.pid = patient.pid AND pb.branch_id = $selected_branch) OR
            EXISTS(SELECT 1 FROM appointment ap WHERE ap.pid = patient.pid AND ap.branch_id = $selected_branch)
        )";
    }
    $query .= " ORDER BY patient.pname $sort_order LIMIT $start_from, $results_per_page";

    $count_query = "SELECT COUNT(*) as total FROM patient WHERE (pname LIKE '%$search%' OR pemail LIKE '%$search%' OR ptel LIKE '%$search%')";
    if ($currentStatus !== 'all') {
        $count_query .= " AND status = '$currentStatus'";
    }
    if ($selected_branch > 0) {
        $count_query .= " AND branch_id = $selected_branch";
    }
} else {
    // No search; filter by status unless 'all' is requested
    if ($currentStatus === 'all') {
            $query = "SELECT patient.*, (
                        SELECT COALESCE(GROUP_CONCAT(DISTINCT b2.name ORDER BY b2.name SEPARATOR ', '), '')
                        FROM (
                            SELECT branch_id FROM patient_branches WHERE pid = patient.pid
                            UNION
                            SELECT branch_id FROM appointment WHERE pid = patient.pid
                            UNION
                            SELECT branch_id FROM patient WHERE pid = patient.pid
                        ) AS src
                        JOIN branches b2 ON b2.id = src.branch_id
                    ) AS branch_names
                    FROM patient";
        if ($selected_branch > 0) {
            $query .= " WHERE patient.branch_id = $selected_branch";
        }
    } else {
        $query = "SELECT patient.*, (
                    SELECT COALESCE(GROUP_CONCAT(DISTINCT b2.name ORDER BY b2.name SEPARATOR ', '), '')
                    FROM branches b2
                    WHERE b2.id IN (
                        SELECT branch_id FROM patient_branches WHERE pid = patient.pid
                        UNION
                        SELECT branch_id FROM appointment WHERE pid = patient.pid
                        UNION
                        SELECT branch_id FROM patient WHERE pid = patient.pid
                    )
                ) AS branch_names
                FROM patient WHERE patient.status='$currentStatus'";
        if ($selected_branch > 0) {
            $query .= " AND patient.branch_id = $selected_branch";
        }
    }
    $query .= " ORDER BY patient.pname $sort_order LIMIT $start_from, $results_per_page";

    $count_query = "SELECT COUNT(*) as total FROM patient";
    if ($currentStatus === 'all') {
        if ($selected_branch > 0) {
            $count_query .= " WHERE branch_id = $selected_branch";
        }
    } else {
        $count_query .= " WHERE status = '$currentStatus'";
        if ($selected_branch > 0) {
                $count_query .= " AND (branch_id = $selected_branch OR EXISTS(SELECT 1 FROM patient_branches pb WHERE pb.pid = patient.pid AND pb.branch_id = $selected_branch) OR EXISTS(SELECT 1 FROM appointment ap WHERE ap.pid = patient.pid AND ap.branch_id = $selected_branch))";
            }
    }
}

$result = $database->query($query);
$count_result = $database->query($count_query);
$count_row = $count_result->fetch_assoc();
$total_pages = ceil($count_row['total'] / $results_per_page);

// Calendar variables
$today = date('Y-m-d');
$currentMonth = date('F');
$currentYear = date('Y');
$daysInMonth = date('t');
$firstDayOfMonth = date('N', strtotime("$currentYear-" . date('m') . "-01"));
$currentDay = date('j');

if (isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['id'])) {
    $id = $_GET['id'];

    // Fetch patient details from the database
    // Enforce branch restriction for viewing patient details
    $sqlmain = "SELECT * FROM patient WHERE pid = ?";
    $stmt = $database->prepare($sqlmain);
    $stmt->bind_param("i", $id); // 'i' means the parameter is an integer
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // If restricted, ensure this patient belongs to restricted branch via direct column or mappings
        if ($restrictedBranchId > 0) {
            $p = $result->fetch_assoc();
            $belongs = false;
            if (!empty($p['branch_id']) && (int)$p['branch_id'] === $restrictedBranchId) { $belongs = true; }
            if (!$belongs) {
                $chk1 = $database->query("SELECT 1 FROM patient_branches WHERE pid = " . (int)$p['pid'] . " AND branch_id = $restrictedBranchId LIMIT 1");
                $belongs = $chk1 && $chk1->num_rows === 1;
            }
            if (!$belongs) {
                $chk2 = $database->query("SELECT 1 FROM appointment WHERE pid = " . (int)$p['pid'] . " AND branch_id = $restrictedBranchId LIMIT 1");
                $belongs = $chk2 && $chk2->num_rows === 1;
            }
            if (!$belongs) {
                redirect_with_context('patient.php');
                exit();
            }
            // Reset result pointer after pre-fetch
            $result->data_seek(0);
        }
        $row = $result->fetch_assoc();
        $name = $row["pname"];
        $email = $row["pemail"];
        $dob = $row["pdob"];
        $tel = $row["ptel"];
        $address = $row["paddress"]; // Assuming address exists in the database
        include_once __DIR__ . '/../inc/get_profile_pic.php';
        $profile_pic = get_profile_pic($row); // Get normalized profile picture path (no leading ../)

        // Fetch past appointments for this patient (up to today) with pagination
        $appointments_html = '';
        $ap_page = isset($_GET['ap_page']) ? max(1, intval($_GET['ap_page'])) : 1;
        $ap_per_page = 5;
        $ap_offset = ($ap_page - 1) * $ap_per_page;

        // total count
        $count_sql = "SELECT COUNT(*) AS total FROM appointment WHERE pid = ? AND appodate <= ?";
        $count_stmt = $database->prepare($count_sql);
        $total_appts = 0;
        if ($count_stmt) {
            $count_stmt->bind_param('is', $id, $today);
            $count_stmt->execute();
            $cres = $count_stmt->get_result();
            if ($cres && $crow = $cres->fetch_assoc()) {
                $total_appts = intval($crow['total']);
            }
        }

        $total_pages_appt = max(1, ceil($total_appts / $ap_per_page));

        $appt_sql = "SELECT a.appoid, a.appodate, a.appointment_time, a.status, procedures.procedure_name, doctor.docname
                     FROM appointment a
                     LEFT JOIN procedures ON a.procedure_id = procedures.procedure_id
                     LEFT JOIN doctor ON a.docid = doctor.docid
                     WHERE a.pid = ? AND a.appodate <= ?
                     ORDER BY a.appodate DESC, a.appointment_time DESC
                     LIMIT ? OFFSET ?";
        $appt_stmt = $database->prepare($appt_sql);
        if ($appt_stmt) {
            $appt_stmt->bind_param('isii', $id, $today, $ap_per_page, $ap_offset);
            $appt_stmt->execute();
            $appt_result = $appt_stmt->get_result();
            if ($appt_result && $appt_result->num_rows > 0) {
                // Build record-style rows to match provided UI
                while ($ap = $appt_result->fetch_assoc()) {
                    $a_date = !empty($ap['appodate']) ? htmlspecialchars(date('F j, Y', strtotime($ap['appodate']))) : '-';
                    $a_time = !empty($ap['appointment_time']) ? htmlspecialchars(date('g:i A', strtotime($ap['appointment_time']))) : '-';
                    $proc = htmlspecialchars($ap['procedure_name'] ?? '-');
                    $dent = htmlspecialchars($ap['docname'] ?? '-');
                    $status = htmlspecialchars(ucfirst($ap['status'] ?? '-'));
                    $appoid = (int)$ap['appoid'];

                    $appointments_html .= '<div class="record-row"><span class="record-label">Date:</span><span>' . $a_date . '</span></div>';
                    $appointments_html .= '<div class="record-row"><span class="record-label">Time:</span><span>' . $a_time . '</span></div>';
                    $appointments_html .= '<div class="record-row"><span class="record-label">Procedure:</span><span>' . $proc . '</span></div>';
                    $appointments_html .= '<div class="record-row"><span class="record-label">Dentist:</span><span>' . $dent . '</span></div>';
                    $appointments_html .= '<div class="record-row" style="margin-bottom:12px;"><span class="record-label">Status:</span><span>' . $status . '</span></div>';
                    // Show "View Receipt" only for patient-booked reservations (has reservation context)
                    $reservationPaid = isset($ap['reservation_paid']) ? intval($ap['reservation_paid']) : 0;
                    $statusVal = isset($ap['status']) ? strtolower($ap['status']) : '';
                    $payStatusVal = isset($ap['payment_status']) ? strtolower($ap['payment_status']) : '';
                    $hasReservationFlag = ($reservationPaid === 1)
                        || ($payStatusVal === 'partial')
                        || ($statusVal === 'pending_reservation')
                        || ($statusVal === 'booking');
                    if ($hasReservationFlag) {
                        $appointments_html .= '<div style="margin-bottom:10px;"><a href="../patient/receipt.php?appoid=' . $appoid . '" target="_blank" class="action-btn view-receipt-btn">View Receipt</a></div>';
                    }
                    $appointments_html .= '<div class="past-appt-separator"></div>';
                }

                // pagination links (numbered)
                $appointments_html .= '<div class="past-appt-pagination" style="margin-top:12px; text-align:center;">';
                if ($total_pages_appt > 1) {
                    for ($p = 1; $p <= $total_pages_appt; $p++) {
                        $link = htmlspecialchars('?action=view&id=' . $id . '&ajax=1&ap_page=' . $p);
                        $appointments_html .= '<a href="' . $link . '" class="page-link appt-page-link' . ($p == $ap_page ? ' active' : '') . '" style="margin:0 6px; text-decoration:none;">' . $p . '</a>';
                    }
                }
                $appointments_html .= '</div>';
            } else {
                $appointments_html .= '<div class="no-appointments">No past appointments.</div>';
            }
        }

        // Build modal content using the requested UI structure
        $modal_content = <<<HTML
            <div class="popup">
                <center>
                    <a class="close" href="patient.php">&times;</a>
                    <div style="display: flex;justify-content: center;">
                        <table width="100%" class="sub-table scrolldown add-doc-form-container" border="0">
                            <tbody>
                            <tr>
                                <td>
                                    <p class="record-title">Patient Records: {$name}</p>
                                    <p class="record-subtitle">Patient ID: P-{$id}</p>
                                    <br>
                                </td>
                            </tr>

                            <tr>
                                <td colspan="2">
                                    <div class="record-section">
                                        <h3>Patient Information</h3>
                                        <div class="record-row">
                                            <span class="record-label">Name:</span>
                                            <span>{$name}</span>
                                        </div>
                                        <div class="record-row">
                                            <span class="record-label">Email:</span>
                                            <span>{$email}</span>
                                        </div>
                                        <div class="record-row">
                                            <span class="record-label">Phone:</span>
                                            <span>{$tel}</span>
                                        </div>
                                        <div class="record-row">
                                            <span class="record-label">Date of Birth:</span>
                                            <span>{$dob}</span>
                                        </div>
                                        <div class="record-row">
                                            <span class="record-label">Address:</span>
                                            <span>{$address}</span>
                                        </div>
                                    </div>

                                    <div class="record-section">
                                        <h3>Past Appointments</h3>
                                        {$appointments_html}
                                        <div class="past-appt-pagination">
                                            <span class="page-status">Page {$ap_page} of {$total_pages_appt}</span>
                                        </div>
                                    </div>

                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="patient.php"><input type="button" value="Close" class="login-btn btn-primary-soft btn" style="width: 100%;"></a>
                                </td>
                            </tr>
                        </tbody></table>
                    </div>
                </center>
            </div>
        HTML;

        // If AJAX requested, return only the inner modal card so the client JS can wrap it in an overlay
        if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
            echo $modal_content;
            exit();
        }

        // Non-AJAX: render full overlay + modal
        echo '<div id="popup1" class="overlay">' . $modal_content . '</div>'; 
    } else {
        echo "<script>alert('Patient not found!');</script>";
        redirect_with_context('patient.php');
        exit();
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Update status to 'inactive' instead of deleting (recommended for data integrity)
    $sql = "UPDATE patient SET status = 'inactive' WHERE pid = ?";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();
    
    if ($result) {
        // Redirect with success message
        redirect_with_context('patient.php', ['status' => 'deactivate_success']);
        exit();
    } else {
        // Redirect with error message
        redirect_with_context('patient.php', ['status' => 'deactivate_error']);
        exit();
    }
}

// Activate patient if requested
if (isset($_GET['action']) && $_GET['action'] == 'activate' && isset($_GET['id'])) {
    $id = $_GET['id'];

    $sql = "UPDATE patient SET status = 'active' WHERE pid = ?";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();

    if ($result) {
        redirect_with_context('patient.php', ['status' => 'activate_success']);
        exit();
    } else {
        redirect_with_context('patient.php', ['status' => 'activate_error']);
        exit();
    }
}

// Permanently delete patient (only available when viewing inactive patients)
if (isset($_GET['action']) && $_GET['action'] == 'destroy' && isset($_GET['id'])) {
    $id = $_GET['id'];

    // Ensure id is integer
    $id = intval($id);

    // Use transaction to safely remove dependent rows first to satisfy foreign key constraints
    $database->begin_transaction();
    try {
        // Delete notifications for this patient (if table exists)
        $delNotifications = $database->prepare("DELETE FROM notifications WHERE user_id = ? AND user_type = 'p'");
        if ($delNotifications) {
            $delNotifications->bind_param("i", $id);
            $delNotifications->execute();
        }

        // Delete dental records associated with this patient (if table exists)
        $delDental = $database->prepare("DELETE FROM dental_records WHERE patient_id = ?");
        if ($delDental) {
            $delDental->bind_param("i", $id);
            $delDental->execute();
        }

        // Delete appointments referencing this patient (must be removed before deleting patient)
        $delAppointments = $database->prepare("DELETE FROM appointment WHERE pid = ?");
        if ($delAppointments) {
            $delAppointments->bind_param("i", $id);
            $delAppointments->execute();
        }

        // Delete related webuser row if present (uses subquery to find email)
        $delWebuser = $database->prepare("DELETE FROM webuser WHERE email = (SELECT pemail FROM patient WHERE pid = ?)");
        if ($delWebuser) {
            $delWebuser->bind_param("i", $id);
            $delWebuser->execute();
        }

        // Finally delete the patient record
        $delPatient = $database->prepare("DELETE FROM patient WHERE pid = ?");
        if (!$delPatient) {
            throw new Exception('Failed to prepare patient delete statement: ' . $database->error);
        }
        $delPatient->bind_param("i", $id);
        $res = $delPatient->execute();

        if ($res) {
            $database->commit();
            redirect_with_context('patient.php', ['status' => 'delete_success']);
            exit();
        } else {
            throw new Exception('Failed to delete patient: ' . $delPatient->error);
        }
    } catch (Exception $e) {
        // Roll back any changes and report error
        $database->rollback();
        error_log('Patient destroy error: ' . $e->getMessage());
        redirect_with_context('patient.php', ['status' => 'delete_error']);
        exit();
    }
}

// Initialize status message variables
$statusMessage = '';
$messageClass = '';

if (isset($_GET['status'])) {
    if ($_GET['status'] == 'deactivate_success') {
        $statusMessage = "Patient deactivated successfully.";
        $messageClass = "success-message";
    } elseif ($_GET['status'] == 'deactivate_error') {
        $statusMessage = "Failed to deactivate patient.";
        $messageClass = "error-message";
        } elseif ($_GET['status'] == 'activate_success') {
            $statusMessage = "Patient activated successfully.";
            $messageClass = "success-message";
        } elseif ($_GET['status'] == 'activate_error') {
            $statusMessage = "Failed to activate patient.";
            $messageClass = "error-message";
        } elseif ($_GET['status'] == 'delete_success') {
            $statusMessage = "Patient deleted permanently.";
            $messageClass = "success-message";
        } elseif ($_GET['status'] == 'delete_error') {
            $statusMessage = "Failed to delete patient.";
            $messageClass = "error-message";
    }
    
    if (!empty($statusMessage)) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                alert('$statusMessage');
                window.location.href = 'patient.php'; // Remove query params
            });
        </script>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/table.css">
    <link rel="stylesheet" href="../css/responsive-admin.css">
    <title>Patient - IHeartDentistDC</title>
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">

    <style>
        /* Wrapper for AJAX-loaded assign-branch modal: adaptive to content (copied from dentist.php) */
        .assign-modal-wrapper {
            display: inline-block;
            width: auto;
            min-width: 320px;
            max-width: 90vw;
            max-height: 85vh;
            overflow: auto;
            margin: 0 auto;
            padding: 12px 14px;
            box-sizing: border-box;
            vertical-align: middle;
            background: transparent;
        }

        .assign-modal-wrapper .btn-primary.btn {
            padding: 8px 14px;
            min-width: 56px;
            border-radius: 6px;
            font-size: 14px;
        }

        .assign-modal-wrapper form {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 18px;
            align-items: center;
            margin: 0;
        }

        .assign-modal-wrapper label, .assign-modal-wrapper .form-label {
            word-break: break-word;
        }

        .assign-modal-wrapper .content form > div {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0;
            padding: 4px 6px;
        }

        .assign-modal-wrapper .content form > div label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .assign-modal-wrapper .content form > div:last-child {
            width: 100%;
            display: flex !important;
            justify-content: center !important;
            gap: 12px !important;
            margin-top: 12px !important;
        }

        /* Ensure forms and inputs align nicely inside the popup */
        .popup .input-text, .popup .form-label {
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Branch select styling to match compact pill-like UI */
        .branch-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding: 8px 12px;
            border-radius: 10px;
            border: 1px solid #e6e9ef;
            background: #ffffff;
            color: #4b5563;
            font-size: 14px;
            line-height: 20px;
            min-width: 140px;
            box-shadow: none;
            cursor: pointer;
        }

        /* Add a subtle down-caret using a background SVG */
        .branch-select {
            background-image: linear-gradient(45deg, transparent 50%, #9ca3af 50%), linear-gradient(135deg, #9ca3af 50%, transparent 50%), linear-gradient(to right, #fff, #fff);
            background-position: calc(100% - 18px) calc(1em + 2px), calc(100% - 13px) calc(1em + 2px), 0 0;
            background-size: 6px 6px, 6px 6px, 100% 100%;
            background-repeat: no-repeat;
            padding-right: 36px;
        }

        /* Slight hover/focus states */
        .branch-select:focus {
            border-color: #cbe4ff;
            box-shadow: 0 0 0 3px rgba(66,153,225,0.12);
            outline: none;
        }
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            /* center overlay content vertically */
            align-items: center;
            overflow: auto;
            padding: 20px 0; /* provide breathing room at top/bottom */
            z-index: 999;
        }

        /* Ensure non-AJAX popup (`#popup1`) matches the AJAX wrapper width and centering */
        .overlay > .popup {
            max-width: 520px;
            width: 520px;
            margin: 0 auto;
            max-height: calc(100vh - 80px);
            overflow: auto;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 20px 50px rgba(2,6,23,0.45);
            padding: 22px 20px;
            position: relative;
            display: block;
        }
        .overlay > .popup .sub-table { box-shadow: 0 6px 18px rgba(13,38,59,0.04); border-radius:8px; padding:18px; border:1px solid rgba(15,23,42,0.04); }

        .close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            color: #333;
            text-decoration: none;
            cursor: pointer;
            z-index: 10000;
        }
       
        .btn-edit {
            background-image: url('../Media/Icon/Blue/edit.png');
            background-repeat: no-repeat;
            background-position: left center;
            padding-left: 30px;
        }
       
        .btn-view {
            background-image: url('../Media/Icon/Blue/eye.png');
            background-repeat: no-repeat;
            background-position: left center;
            padding-left: 30px;
        }
       
        .btn-delete {
            background-image: url('../Media/Icon/Blue/delete.png');
            background-repeat: no-repeat;
            background-position: left center;
            padding-left: 30px;
        }
    
        .stats-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        .stat-box {
            height: 100%;
        }
        .right-sidebar {
            width: 400px;
        }
        .profile-img-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        .view-btn {
            width: 120px;
        }
        .popup1 {
            background-color: white;
        }
        /* Modal styles for View Records popup */
        .modal-card {
            background: #fff;
            border-radius: 10px;
            max-width: 900px;
            width: 90%;
            padding: 28px 32px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            position: relative;
            /* constrain height and enable internal scrolling */
            max-height: calc(100vh - 120px);
            overflow: auto;
        }

        .modal-header h2 {
            margin: 0 0 6px 0;
            font-size: 24px;
            font-weight: 600;
        }

        .patient-id {
            margin: 0 0 18px 0;
            color: #666;
            font-size: 13px;
        }

        .patient-card {
            background: #fff;
            border-radius: 8px;
            padding: 18px 20px;
            box-shadow: 0 6px 18px rgba(13,38,59,0.06);
            border: 1px solid rgba(0,0,0,0.03);
        }

        .patient-info-title {
            font-weight: 700;
            margin-bottom: 12px;
            font-size: 16px;
        }

        .patient-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            row-gap: 10px;
            column-gap: 20px;
            align-items: center;
        }

        .patient-info .label { color:#333; font-weight:600; }
        .patient-info .value { text-align: left; color:#222; }

        .modal-card .close { position: absolute; top: 12px; right: 14px; font-size: 20px; color:#333; text-decoration: none; }
        /* Appointments list styles */
        .appointments-list { margin-top: 18px; background:#fff; border-radius:8px; padding:16px; box-shadow:0 6px 18px rgba(13,38,59,0.04); border:1px solid rgba(0,0,0,0.03); }
        /* Scope appointment-item styles to the View Records modal only to avoid overriding right sidebar cards */
        .view-modal-wrapper .appointment-item { border-bottom:1px solid rgba(0,0,0,0.06); padding:12px 0; }
        .view-modal-wrapper .appointment-item:last-child { border-bottom:none; }
        .appointment-row { display:flex; justify-content:space-between; padding:4px 0; }
        .appointment-row .label { font-weight:700; color:#333; }
        .appointment-row .value { color:#222; }
        /* Record-row styles (match the provided UI) */
        .record-section h3 { margin: 0 0 10px 0; font-size:18px; }
        .record-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom: none; }
        .record-label { font-weight:700; color:#333; }
        .past-appt-separator { height:1px; background:#eee; margin:12px 0; }
        .past-appt-pagination .page-status { display:block; text-align:center; color:#777; margin-top:8px; }
        /* Animated overlay and modal wrapper transitions */
        .animated-overlay { opacity: 0; transition: opacity 240ms ease; }
        .animated-overlay:not(.fade-out) { opacity: 1; }
        .animated-overlay.fade-out { opacity: 0; }

        .view-modal-wrapper { transform: translateX(18px) scale(0.99); opacity: 0; transition: transform 260ms cubic-bezier(.2,.9,.3,1), opacity 260ms ease; }
        .view-modal-wrapper.open { transform: translateX(0) scale(1); opacity: 1; }
        .view-modal-wrapper.closing { transform: translateX(18px) scale(0.99); opacity: 0; }
        /* Constrain modal width and scrolling to match design */
        .view-modal-wrapper {
            max-width: 520px;
            width: 520px;
            height: calc(100vh - 80px);
            overflow: auto;
            background: #ffffff; /* outer white panel */
            border-radius: 10px;
            box-shadow: 0 20px 50px rgba(2,6,23,0.45);
            position: relative;
            padding: 12px 14px;
        }
        /* Inner white content card */
        .view-modal-wrapper .sub-table {
            background: #fff;
            border-radius: 8px;
            padding: 14px 14px 16px 14px;
            box-shadow: 0 6px 18px rgba(13,38,59,0.04);
            border: 1px solid rgba(15,23,42,0.04);
            width: 100%;
        }
        .view-modal-wrapper .popup { padding: 0; box-sizing: border-box; width:100%; }
        .view-modal-wrapper { display:flex; align-items:center; justify-content:center; }
        /* Close button inside wrapper */
        .view-modal-wrapper .close { position: absolute; top: 12px; right: 18px; color:#111827; font-size:18px; z-index: 20; background:transparent; border-radius:50%; width:28px; height:28px; line-height:28px; text-align:center; }
        /* Make inner table and rows stretch to fill modal */
        .view-modal-wrapper .sub-table { width: 100% !important; border-collapse: collapse; }
        .view-modal-wrapper .sub-table td { padding: 6px 10px; vertical-align: top; }
        .view-modal-wrapper .sub-table tbody { width:100%; }
        .view-modal-wrapper .record-section { padding: 12px; margin: 12px 0; background: #ffffff; border-radius:8px; box-shadow: 0 6px 18px rgba(13,38,59,0.04); border:1px solid #f4f4f6; }
        .view-modal-wrapper .record-section h3 { margin: 0 0 12px 0; font-size:18px; padding-bottom:6px; border-bottom:1px solid #f3f4f6; }
        /* Force record rows to use label/value layout filling full width */
        .view-modal-wrapper .record-row { display:flex; gap:12px; align-items:flex-start; padding:6px 0; }
        .view-modal-wrapper .record-row .record-label { flex: 0 0 36%; max-width: 36%; text-align:left; color:#111827; font-weight:700; }
        .view-modal-wrapper .record-row span:not(.record-label) { flex: 1 1 auto; text-align:left; color:#374151; }
        /* Make action buttons align left but occupy minimal width */
        .view-modal-wrapper .action-btn.view-receipt-btn { display:inline-block; margin-top:6px; }
        /* Separator should span full width */
        .view-modal-wrapper .past-appt-separator { width:100%; height:1px; background:#eee; margin:12px 0; }
        /* Page status center */
        .view-modal-wrapper .past-appt-pagination { text-align:center; margin-top:8px; }
        /* Title styles */
        .view-modal-wrapper .record-title { font-size:22px; font-weight:700; margin:0 0 6px 0; color:#0f172a; text-transform:uppercase; letter-spacing:0.2px; }
        .view-modal-wrapper .record-subtitle { font-size:13px; color:#6b7280; margin:0 0 16px 0; }

        /* responsive */
        @media (max-width: 640px) {
            .view-modal-wrapper { width: 92%; max-width: 92%; padding: 14px; }
            .view-modal-wrapper .sub-table { padding: 12px; }
            .view-modal-wrapper .record-label { flex-basis: 40%; }
            .view-modal-wrapper .record-title { font-size:18px; }
        }
        /* Title styles */
        .view-modal-wrapper .record-title { font-size:20px; font-weight:700; margin:0 0 6px 0; color:#111827; }
        .view-modal-wrapper .record-subtitle { font-size:13px; color:#6b7280; margin:0 0 16px 0; }
        .page-indicator { text-align:center; font-size:12px; color:#777; margin-top:8px; }
        .action-btn.view-receipt-btn { display:inline-block; background:#2f3670; color:#fff; padding:6px 10px; border-radius:6px; text-decoration:none; }
        .close-btn { display:block; width:100%; text-align:center; background:#e6eef7; color:#2f3670; padding:10px 0; border-radius:6px; text-decoration:none; }
        /* Make cancel buttons inside the edit modal smaller and inline */
        .modal-card .close-btn {
            display: inline-block;
            width: auto;
            padding: 8px 14px;
            min-width: 100px;
            text-align: center;
            border-radius: 6px;
            background: #e6eef7;
            color: #2f3670;
            text-decoration: none;
            margin-left: 6px;
        }
        /* View Records action button (table) - match action-btn style and assign-branch color */
        .action-btn.view-records-link {
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #84b6e4;
            border: none;
            border-radius: 50px;
            padding: 10px 15px 10px 35px;
            min-width: 120px;
            color: white;
            cursor: pointer;
            font-size: 13px;
            gap: 5px;
            position: relative;
            transition: background-color 0.3s;
            margin: 2px;
        }

        .action-btn.view-records-link::before{
            content: "";
            position: absolute;
            left: 12px;
            width: 16px;
            height: 16px;
            background-image: url('../Media/Icon/White/eye.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        /* Edit button styling with icon */
        .action-btn.edit-patient-link {
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #84b6e4;
            border: none;
            border-radius: 50px;
            padding: 10px 15px 10px 35px;
            min-width: 100px;
            color: white;
            cursor: pointer;
            font-size: 13px;
            gap: 5px;
            position: relative;
            transition: background-color 0.3s;
            margin: 2px;
        }

        .action-btn.edit-patient-link::before{
            content: "";
            position: absolute;
            left: 12px;
            width: 16px;
            height: 16px;
            background-image: url('../Media/Icon/White/edit.png');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        .action-btn.edit-patient-link:hover { background-color: #6aaedc; }
    </style>
</head>

<body>
    <!-- Mobile hamburger for sidebar toggle -->
    <button class="hamburger-admin" id="hamburgerAdmin" aria-label="Toggle sidebar" aria-controls="adminSidebar" aria-expanded="false">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- sidebar toggle removed to keep sidebar static -->
    <div class="main-container">
        <div class="sidebar" id="adminSidebar">
            <div class="sidebar-logo">
                <img src="../Media/Icon/logo.png" alt="IHeartDentistDC Logo">
            </div>

            <div class="user-profile">
                <div class="profile-image">
                    <img src="../Media/Icon/logo.png" alt="Profile" class="profile-img">
                </div>
                <h3 class="profile-name">I Heart Dentist Dental Clinic</h3>
                <p style="color: #777; margin: 0; font-size: 14px; text-align: center;">
                <?php
                    $roleLabel = 'Secretary';
                    if (isset($_SESSION['user'])) {
                        $curr = strtolower($_SESSION['user']);
                        if ($curr === 'admin@edoc.com') {
                            $roleLabel = 'Super Admin';
                        } elseif (isset($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id']) {
                            $branchLabels = [
                                'adminbacoor@edoc.com' => 'Bacoor',
                                'adminmakati@edoc.com' => 'Makati'
                            ];
                            if (isset($branchLabels[$curr])) {
                                $roleLabel = 'Secretary - ' . $branchLabels[$curr];
                            }
                        }
                    }
                    echo $roleLabel;
                ?>
                </p>
            </div>

            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <img src="../Media/Icon/Blue/home.png" alt="Home" class="nav-icon">
                    <span class="nav-label">Dashboard</span>
                </a>
                <a href="dentist.php" class="nav-item">
                    <img src="../Media/Icon/Blue/dentist.png" alt="Dentist" class="nav-icon">
                    <span class="nav-label">Dentist</span>
                </a>
                <a href="patient.php" class="nav-item active">
                    <img src="../Media/Icon/Blue/care.png" alt="Patient" class="nav-icon">
                    <span class="nav-label">Patient</span>
                </a>
                <a href="records.php" class="nav-item">
                    <img src="../Media/Icon/Blue/edit.png" alt="Records" class="nav-icon">
                    <span class="nav-label">Patient Records</span>
                </a>
                <a href="calendar/calendar.php" class="nav-item">
                    <img src="../Media/Icon/Blue/calendar.png" alt="Calendar" class="nav-icon">
                    <span class="nav-label">Calendar</span>
                </a>
                <a href="booking.php" class="nav-item">
                    <img src="../Media/Icon/Blue/booking.png" alt="Booking" class="nav-icon">
                    <span class="nav-label">Booking</span>
                </a>
                <a href="appointment.php" class="nav-item">
                    <img src="../Media/Icon/Blue/appointment.png" alt="Appointment" class="nav-icon">
                    <span class="nav-label">Appointment</span>
                </a>
                <a href="history.php" class="nav-item">
                    <img src="../Media/Icon/Blue/folder.png" alt="Archive" class="nav-icon">
                    <span class="nav-label">Archive</span>
                </a>
                <a href="reports/financial_reports.php" class="nav-item">
                    <img src="../Media/Icon/Blue/folder.png" alt="Reports" class="nav-icon">
                    <span class="nav-label">Reports</span>
                </a>
                <?php if (empty($_SESSION['restricted_branch_id'])): ?>
                <a href="settings.php" class="nav-item">
                    <img src="../Media/Icon/Blue/settings.png" alt="Settings" class="nav-icon">
                    <span class="nav-label">Settings</span>
                </a>
                <?php endif; ?>
            </div>

            <div class="log-out">
                <a href="logout.php" class="nav-item">
                    <img src="../Media/Icon/Blue/logout.png" alt="Log Out" class="nav-icon">
                    <span class="nav-label">Log Out</span>
                </a>
            </div>
        </div>

        <div class="content-area">
            <div class="content">
                <!-- Legacy sidebar-toggle removed; logo now acts as toggle -->
                <div class="main-section">
                    <!-- search bar -->
                    <div class="search-container">
                        <form action="" method="GET" style="display: flex; width: 100%;">
                            <input type="search" name="search" id="searchInput" class="search-input"
                                placeholder="Search by name, email or phone number"
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <?php if (isset($_GET['search']) && $_GET['search'] != ""): ?>
                                <button type="button" class="clear-btn" onclick="clearSearch()">×</button>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- header -->
                    <div class="announcements-header">
                        <h3 class="announcements-title">Manage Patients</h3>
                        <div class="announcement-filters">
                            <?php
                            $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
                            $searchParam = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';
                            $currentStatus = isset($_GET['status']) ? $_GET['status'] : 'active';
                            $statusParam = '&status=' . urlencode($currentStatus);
                            ?>
                            <a href="?sort=newest<?php echo $statusParam . $searchParam; ?>"
                                class="filter-btn newest-btn <?php echo ($currentSort === 'newest' || $currentSort === '') ? 'active' : 'inactive'; ?>">
                                A-Z
                            </a>

                            <a href="?sort=oldest<?php echo $statusParam . $searchParam; ?>"
                                class="filter-btn oldest-btn <?php echo $currentSort === 'oldest' ? 'active' : 'inactive'; ?>">
                                Z-A
                            </a>
                            
                            <!-- Status filter: All / Active / Inactive -->
                            <a href="?status=all<?php echo $searchParam ? $searchParam : ''; ?>" class="filter-btn filter-all-btn <?php echo ($currentStatus === 'all') ? 'active' : 'inactive'; ?>">All</a>
                            <a href="?status=active<?php echo $searchParam ? $searchParam : ''; ?>" class="filter-btn filter-active-btn <?php echo ($currentStatus === 'active') ? 'active' : 'inactive'; ?>">Active</a>
                            <a href="?status=inactive<?php echo $searchParam ? $searchParam : ''; ?>" class="filter-btn filter-inactive-btn <?php echo ($currentStatus === 'inactive') ? 'active' : 'inactive'; ?>">Inactive</a>

                            <!-- Branch filter -->
                            <form method="GET" style="display:inline-block; margin-left:12px;">
                                <input type="hidden" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($currentSort); ?>">
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($currentStatus); ?>">
                                <select name="branch_id" onchange="this.form.submit()" class="branch-select" style="margin-left:8px;">
                                    <option value="">All Branches</option>
                                    <?php if ($branchesResult && $branchesResult->num_rows > 0): ?>
                                        <?php while ($b = $branchesResult->fetch_assoc()): ?>
                                            <option value="<?php echo $b['id']; ?>" <?php echo ($selected_branch == $b['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </form>
                            <a href="?action=add&id=none&error=0" class="filter-btn add-btn" style="margin-left:10px;">
                                Add New Patient
                            </a>
                        </div>
                    </div>

                            <!-- small toggle removed: header status buttons control view; default is 'active' -->

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive"><div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Profile</th>
                                        <th>Name</th>
                                        <th>Branch</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Date of Birth</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                include_once __DIR__ . '/../inc/get_profile_pic.php';
                                                $profile_pic = get_profile_pic($row);
                                                $photo = "../" . $profile_pic;
                                                ?>
                                                <img src="<?php echo $photo; ?>" alt="<?php echo htmlspecialchars($row['pname']); ?>"
                                                    class="profile-img-small" onerror="this.onerror=null;this.src='../Media/Icon/Blue/profile.png';">
                                            </td>
                                            <td><div class="cell-text"><?php echo $row['pname']; ?></div></td>
                                            <td>
                                                <div class="cell-text">
                                                <?php
                                                    $branches_list = [];
                                                    $brres = $database->query("SELECT b.name FROM patient_branches pb JOIN branches b ON pb.branch_id=b.id WHERE pb.pid='" . (int)$row['pid'] . "' ORDER BY b.name ASC");
                                                    if ($brres && $brres->num_rows) {
                                                        while ($b = $brres->fetch_assoc()) {
                                                            $clean = trim($b['name']);
                                                            if (!in_array($clean, $branches_list)) $branches_list[] = $clean;
                                                        }
                                                    } else {
                                                        // fallback to legacy single branch column
                                                        if (isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== '') {
                                                            $bres = $database->query("SELECT name FROM branches WHERE id='" . (int)$row['branch_id'] . "' LIMIT 1");
                                                            if ($bres && $bres->num_rows) {
                                                                $brow = $bres->fetch_assoc();
                                                                $branches_list[] = trim($brow['name']);
                                                            }
                                                        }
                                                    }

                                                    echo !empty($branches_list) ? htmlspecialchars(implode(', ', $branches_list)) : '-';
                                                ?>
                                                </div>
                                            </td>
                                            <td><div class="cell-text"><?php echo $row['pemail']; ?></div></td>
                                            <td><div class="cell-text"><?php echo $row['ptel']; ?></div></td>
                                            <td><div class="cell-text"><?php echo $row['pdob']; ?></div></td>
                                            <td><div class="cell-text"><?php echo ucfirst($row['status']); ?></div></td>
                                            <td>
                                                <div class="action-buttons">
                                                                    <a href="edit-patient.php?id=<?php echo $row['pid']; ?>" class="action-btn edit-patient-link">Edit</a>
                                                                     <?php if ($currentStatus === 'inactive'): ?>
                                                                          <a href="?action=activate&id=<?php echo $row['pid']; ?>" 
                                                                              class="action-btn add-btn" 
                                                                              onclick="return confirm('Are you sure you want to activate this patient?')">Activate</a>
                                                                          <?php if (empty($restrictedBranchId) || $restrictedBranchId == 0): ?>
                                                                              <a href="?action=destroy&id=<?php echo $row['pid']; ?>" 
                                                                                  class="action-btn remove-btn" 
                                                                                  onclick="return confirm('This will permanently delete the patient and cannot be undone. Continue?')">Delete</a>
                                                                          <?php endif; ?>
                                                                     <?php else: ?>
                                                                          <a href="?action=delete&id=<?php echo $row['pid']; ?>&name=<?php echo urlencode($row['pname']); ?>" 
                                                                              class="action-btn remove-btn" 
                                                                              onclick="return confirm('Are you sure you want to deactivate this patient?')">Deactivate</a>
                                                                     <?php endif; ?>
                                                                    <?php if (empty($restrictedBranchId) || $restrictedBranchId == 0): ?>
                                                                        <a href="assign_branch.php?kind=patient&id=<?php echo $row['pid']; ?>" class="action-btn assign-branch-link">Assign Branch</a>
                                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div></div>

                        <!-- Pagination -->
                        <div class="pagination">
                            <?php
                            $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
                            $sortParam = '&sort=' . $currentSort;
                            $searchParam = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';
                            $currentStatus = isset($_GET['status']) ? $_GET['status'] : 'active';
                            $statusParam = '&status=' . $currentStatus;

                            // Previous link
                            if ($page > 1) {
                                echo '<a href="?page=' . ($page - 1) . $searchParam . $sortParam . $statusParam . '">&laquo; Previous</a>';
                            }

                            // Page links
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<a href="?page=' . $i . $searchParam . $sortParam . $statusParam . '"' . ($i == $page ? ' class="active"' : '') . '>' . $i . '</a>';
                            }

                            // Next link
                            if ($page < $total_pages) {
                                echo '<a href="?page=' . ($page + 1) . $searchParam . $sortParam . $statusParam . '">Next &raquo;</a>';
                            }
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <p>No patient found. Please try a different search term.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Add right sidebar section -->
                <div class="right-sidebar">
                    <div class="stats-section">
                        <div class="stats-container">
                            <!-- First row -->
                            <a href="dentist.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $doctorrow->num_rows; ?></h1>
                                        <p class="stat-label">Dentists</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/dentist.png" alt="Dentist Icon">
                                    </div>
                                </div>
                            </a>

                            <!-- Second row -->
                            <a href="patient.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $patientrow->num_rows; ?></h1>
                                        <p class="stat-label">Patients</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/care.png" alt="Patient Icon">
                                    </div>
                                </div>
                            </a>

                            <a href="booking.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $appointmentrow->num_rows; ?></h1>
                                        <p class="stat-label">Bookings</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/booking.png" alt="Booking Icon">
                                        <?php if ($appointmentrow->num_rows > 0): ?>
                                            <span class="notification-badge"><?php echo $appointmentrow->num_rows; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>

                            <a href="appointment.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $schedulerow->num_rows; ?></h1>
                                        <p class="stat-label">Appointments</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/appointment.png" alt="Appointment Icon">
                                        <?php if ($schedulerow->num_rows > 0): ?>
                                            <span class="notification-badge"><?php echo $schedulerow->num_rows; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div class="calendar-section">
                        <!-- Dynamic Calendar -->
                        <div class="calendar-container">
                            <div class="calendar-header">
                                <h3 class="calendar-month">
                                    <?php
                                    // Get current month name dynamically
                                    echo strtoupper(date('F', strtotime('this month')));
                                    ?>
                                </h3>
                            </div>
                            <div class="calendar-grid">
                                <div class="calendar-day">S</div>
                                <div class="calendar-day">M</div>
                                <div class="calendar-day">T</div>
                                <div class="calendar-day">W</div>
                                <div class="calendar-day">T</div>
                                <div class="calendar-day">F</div>
                                <div class="calendar-day">S</div>

                                <?php
                                // Calculate the previous month's spillover days
                                $previousMonthDays = $firstDayOfMonth - 1;
                                $previousMonthLastDay = date('t', strtotime('last month'));
                                $startDay = $previousMonthLastDay - $previousMonthDays + 1;

                                // Display previous month's spillover days
                                for ($i = 0; $i < $previousMonthDays; $i++) {
                                    echo '<div class="calendar-date other-month">' . $startDay . '</div>';
                                    $startDay++;
                                }

                                // Display current month's days
                                for ($day = 1; $day <= $daysInMonth; $day++) {
                                    $class = ($day == $currentDay) ? 'calendar-date today' : 'calendar-date';
                                    echo '<div class="' . $class . '">' . $day . '</div>';
                                }

                                // Calculate and display next month's spillover days
                                $nextMonthDays = 42 - ($previousMonthDays + $daysInMonth); // 42 = 6 rows * 7 days
                                for ($i = 1; $i <= $nextMonthDays; $i++) {
                                    echo '<div class="calendar-date other-month">' . $i . '</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

            <script>
            // Assign Branch modal opener (shared behavior)
            document.addEventListener('click', function(e){
                var link = e.target.closest && e.target.closest('.assign-branch-link');
                if (!link) return;
                e.preventDefault();
                var url = link.getAttribute('href');
                if (!url) return;
                if (url.indexOf('?') === -1) url += '?ajax=1'; else url += '&ajax=1';
                var overlay = document.getElementById('assign-branch-overlay');
                if (overlay) overlay.remove();
                overlay = document.createElement('div');
                overlay.id = 'assign-branch-overlay';
                overlay.style.position = 'fixed'; overlay.style.left = 0; overlay.style.top = 0; overlay.style.right = 0; overlay.style.bottom = 0;
                overlay.style.background = 'rgba(0,0,0,0.5)'; overlay.style.zIndex = 9999; overlay.style.display = 'flex'; overlay.style.alignItems = 'center'; overlay.style.justifyContent = 'center';
                overlay.addEventListener('click', function(ev){ if (ev.target === overlay) { document.body.style.overflow = ''; overlay.remove(); } });
                document.body.appendChild(overlay);
                var loader = document.createElement('div'); loader.textContent = 'Loading...'; loader.style.color = '#fff'; overlay.appendChild(loader);
                fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){ return r.text(); }).then(function(html){
                    overlay.innerHTML = '<div class="assign-modal-wrapper">' + html + '</div>';
                    // prevent background scrolling while modal is open
                    document.body.style.overflow = 'hidden';
                    // attach submit handler to any form inside the modal
                    var form = overlay.querySelector('form');
                    if (form) {
                        form.addEventListener('submit', function(ev){
                            ev.preventDefault();
                            var btn = form.querySelector('button[type=submit]'); if (btn) btn.disabled = true;
                            var fd = new FormData(form);
                            fetch(form.getAttribute('action') || 'assign_branch_action.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(function(r){ return r.json(); })
                            .then(function(json){ if (btn) btn.disabled = false; if (json && json.status) { overlay.remove(); location.reload(); } else { alert(json.msg || 'Failed to save'); } })
                            .catch(function(){ if (btn) btn.disabled = false; alert('Network error'); });
                        });
                    }
                    // attach cancel button
                    var cancel = overlay.querySelector('#assign-branch-cancel');
                    if (cancel) cancel.addEventListener('click', function(){ document.body.style.overflow = ''; overlay.remove(); });

                    // attach close (X) button handler for AJAX-inserted content
                    var closeAnch = overlay.querySelector('.popup .close');
                    if (closeAnch) closeAnch.addEventListener('click', function(e){ e.preventDefault(); document.body.style.overflow = ''; overlay.remove(); });
                }).catch(function(){ overlay.innerHTML = '<div style="color:#fff;padding:20px">Failed to load</div>'; });
            });
            </script>

            <script>
            // Edit Patient modal opener (AJAX)
            document.addEventListener('click', function(e){
                var link = e.target.closest && e.target.closest('.edit-patient-link');
                if (!link) return;
                e.preventDefault();
                var url = link.getAttribute('href');
                if (!url) return;
                if (url.indexOf('?') === -1) url += '?ajax=1'; else url += '&ajax=1';
                var overlay = document.getElementById('edit-patient-overlay');
                if (overlay) overlay.remove();
                overlay = document.createElement('div');
                overlay.id = 'edit-patient-overlay';
                overlay.style.position = 'fixed'; overlay.style.left = 0; overlay.style.top = 0; overlay.style.right = 0; overlay.style.bottom = 0;
                overlay.style.background = 'rgba(0,0,0,0.5)'; overlay.style.zIndex = 9999; overlay.style.display = 'flex'; overlay.style.alignItems = 'center'; overlay.style.justifyContent = 'center';
                overlay.addEventListener('click', function(ev){ if (ev.target === overlay) overlay.remove(); });
                document.body.appendChild(overlay);
                document.body.style.overflow = 'hidden';
                var loader = document.createElement('div'); loader.textContent = 'Loading...'; loader.style.color = '#fff'; overlay.appendChild(loader);

                fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){ return r.text(); }).then(function(html){
                    overlay.innerHTML = '<div style="max-width:620px;width:70%;">' + html + '</div>';

                    // wire form submission inside overlay
                    var form = overlay.querySelector('form');
                    if (form) {
                        form.addEventListener('submit', function(ev){
                            ev.preventDefault();
                            var btn = form.querySelector('button[type=submit]'); if (btn) btn.disabled = true;
                            var fd = new FormData(form);
                            fetch(form.getAttribute('action') || url.split('?')[0], { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(function(r){ return r.json(); })
                            .then(function(json){ if (btn) btn.disabled = false; if (json && json.status) { overlay.remove(); location.reload(); } else { alert(json.msg || 'Failed to save'); } })
                            .catch(function(){ if (btn) btn.disabled = false; alert('Network error'); });
                        });
                    }

                    // Wire all close elements (X and Cancel) to close the overlay, prevent default navigation, and restore scrolling
                    var closeEls = overlay.querySelectorAll('.close, .close-btn');
                    closeEls.forEach(function(el){
                        el.addEventListener('click', function(ev){
                            if (ev && ev.preventDefault) ev.preventDefault();
                            document.body.style.overflow = '';
                            if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
                        });
                    });
                }).catch(function(){ overlay.innerHTML = '<div style="color:#fff;padding:20px">Failed to load</div>'; document.body.style.overflow = ''; });
            });
            </script>

            <script>
            // View Records modal opener (AJAX) with open/close animations
            document.addEventListener('click', function(e){
                var link = e.target.closest && e.target.closest('.view-records-link');
                if (!link) return;
                e.preventDefault();
                var url = link.getAttribute('href');
                if (!url) return;
                if (url.indexOf('?') === -1) url += '?ajax=1'; else url += '&ajax=1';
                var overlay = document.getElementById('view-patient-overlay');
                if (overlay) overlay.remove();
                overlay = document.createElement('div');
                overlay.id = 'view-patient-overlay';
                overlay.className = 'animated-overlay';
                overlay.style.position = 'fixed'; overlay.style.left = 0; overlay.style.top = 0; overlay.style.right = 0; overlay.style.bottom = 0;
                overlay.style.background = 'rgba(0,0,0,0.5)'; overlay.style.zIndex = 9999; overlay.style.display = 'flex'; overlay.style.alignItems = 'center'; overlay.style.justifyContent = 'center';

                function finishRemove(nOverlay){
                    if (!nOverlay) nOverlay = document.getElementById('view-patient-overlay');
                    if (nOverlay && nOverlay.parentNode) nOverlay.parentNode.removeChild(nOverlay);
                    document.body.style.overflow = '';
                }

                function removeOverlayAnimated(nOverlay){
                    if (!nOverlay) nOverlay = document.getElementById('view-patient-overlay');
                    if (!nOverlay) return finishRemove(nOverlay);
                    var wrapper = nOverlay.querySelector('.view-modal-wrapper');
                    if (wrapper) {
                        // start closing animation
                        wrapper.classList.remove('open');
                        wrapper.classList.add('closing');
                        // fade overlay background
                        nOverlay.classList.add('fade-out');
                        // wait for transition end (fallback to 400ms)
                        var called = false;
                        wrapper.addEventListener('transitionend', function te(){ if (called) return; called = true; wrapper.removeEventListener('transitionend', te); finishRemove(nOverlay); });
                        setTimeout(function(){ if (!called) finishRemove(nOverlay); }, 450);
                    } else {
                        finishRemove(nOverlay);
                    }
                }

                overlay.addEventListener('click', function(ev){ if (ev.target === overlay) removeOverlayAnimated(overlay); });
                document.body.appendChild(overlay);
                document.body.style.overflow = 'hidden';
                var loader = document.createElement('div'); loader.textContent = 'Loading...'; loader.style.color = '#fff'; overlay.appendChild(loader);

                fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){ return r.text(); }).then(function(html){
                    // create a wrapper for the modal so we can animate it
                    overlay.innerHTML = '';
                    var wrapper = document.createElement('div');
                    wrapper.className = 'view-modal-wrapper';
                    wrapper.style.maxWidth = '620px';
                    wrapper.style.width = '70%';
                    wrapper.innerHTML = html;
                    overlay.appendChild(wrapper);

                    // allow CSS transitions to kick in
                    requestAnimationFrame(function(){ wrapper.classList.add('open'); });

                    // close handlers
                    var close = overlay.querySelector('.close'); if (close) close.addEventListener('click', function(ev){ ev.preventDefault(); removeOverlayAnimated(overlay); });
                    var closeBtns = overlay.querySelectorAll('.close-btn'); closeBtns.forEach(function(b){ b.addEventListener('click', function(ev){ ev.preventDefault(); removeOverlayAnimated(overlay); }); });

                    // wire appointment pagination links inside overlay to fetch pages via AJAX and animate content swap
                    function wirePagination(container){
                        var pageLinks = container.querySelectorAll('.appt-page-link');
                        pageLinks.forEach(function(pl){
                            pl.addEventListener('click', function(ev){
                                ev.preventDefault();
                                var href = this.getAttribute('href');
                                if (!href) return;
                                // animate content out
                                container.classList.remove('open');
                                setTimeout(function(){
                                    fetch(href, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){ return r.text(); }).then(function(newHtml){
                                        container.innerHTML = newHtml;
                                        // re-open with animation
                                        requestAnimationFrame(function(){ container.classList.add('open'); });
                                        // re-wire handlers for new content
                                        var newClose = container.querySelector('.close'); if (newClose) newClose.addEventListener('click', function(ev){ ev.preventDefault(); removeOverlayAnimated(overlay); });
                                        var newCloseBtns = container.querySelectorAll('.close-btn'); newCloseBtns.forEach(function(b){ b.addEventListener('click', function(ev){ ev.preventDefault(); removeOverlayAnimated(overlay); }); });
                                        // recurse
                                        wirePagination(container);
                                    }).catch(function(){ /* ignore errors for now */ });
                                }, 220);
                            });
                        });
                    }

                    wirePagination(wrapper);
                }).catch(function(){ overlay.innerHTML = '<div style="color:#fff;padding:20px">Failed to load</div>'; document.body.style.overflow = ''; });
            });
            </script>

                    <div class="upcoming-appointments">
                        <h3>Upcoming Appointments</h3>
                        <div class="appointments-content">
                            <?php
                            $branchScope = '';
                            if (isset($restrictedBranchId) && $restrictedBranchId > 0) {
                                $branchScope = " AND (doctor.branch_id = $restrictedBranchId OR doctor.docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))";
                            }
                            $upcomingAppointments = $database->query("SELECT
                                    appointment.appoid,
                                    procedures.procedure_name,
                                    appointment.appodate,
                                    appointment.appointment_time,
                                    patient.pname as patient_name,
                                    doctor.docname as doctor_name,
                                    COALESCE(b.name, '') AS branch_name
                                FROM appointment
                                LEFT JOIN procedures ON appointment.procedure_id = procedures.procedure_id
                                LEFT JOIN patient ON appointment.pid = patient.pid
                                LEFT JOIN doctor ON appointment.docid = doctor.docid
                                LEFT JOIN branches b ON doctor.branch_id = b.id
                                WHERE
                                    appointment.status = 'appointment'
                                    AND appointment.appodate >= '$today'" . $branchScope . "
                                ORDER BY appointment.appodate ASC, appointment.appointment_time ASC
                            ");

                            if ($upcomingAppointments && $upcomingAppointments->num_rows > 0) {
                                while ($appointment = $upcomingAppointments->fetch_assoc()) {
                                    $pname = htmlspecialchars($appointment['patient_name'] ?? '');
                                    $dname = htmlspecialchars($appointment['doctor_name'] ?? '');
                                    $proc = htmlspecialchars($appointment['procedure_name'] ?? '');
                                    $branch = htmlspecialchars($appointment['branch_name'] ?? '');
                                    $date_str = '';
                                    $time_str = '';
                                    if (!empty($appointment['appodate'])) {
                                        $date_str = htmlspecialchars(date('F j, Y', strtotime($appointment['appodate'])));
                                    }
                                    if (!empty($appointment['appointment_time'])) {
                                        $time_str = htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time'])));
                                    }

                                    echo '<div class="appointment-item">' .
                                        '<h4 class="appointment-type">' . $pname . '</h4>' .
                                        '<p class="appointment-dentist">With Dr. ' . $dname . '</p>' .
                                        '<p class="appointment-date">' . $proc . '</p>' .
                                        '<p class="appointment-date">' . $date_str . ($date_str && $time_str ? ' • ' : '') . $time_str . (($branch!=='') ? (' - ' . $branch) : '') . '</p>' .
                                    '</div>';
                                }
                            } else {
                                echo '<div class="no-appointments"><p>No upcoming appointments scheduled</p></div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function clearSearch() {
            window.location.href = 'patient.php';
        }

        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            const clearBtn = document.querySelector('.clear-btn');

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    clearSearch();
                });
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Show popup if URL has any action parameter
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');

            if (action === 'view' || action === 'edit' || action === 'drop' || action === 'add') {
                const popup = document.getElementById('popup1');
                if (popup) {
                    popup.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
            }

            const closeButtons = document.querySelectorAll('.close');
            closeButtons.forEach(button => {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    const overlay = this.closest('.overlay');
                    if (overlay) {
                        overlay.style.display = 'none';
                        document.body.style.overflow = '';

                        const url = new URL(window.location);
                        url.searchParams.delete('action');
                        url.searchParams.delete('id');
                        url.searchParams.delete('name');
                        url.searchParams.delete('error');
                        window.location.href = url.toString(); // This will reload the page
                    }
                });
            });

            const overlays = document.querySelectorAll('.overlay');
            overlays.forEach(overlay => {
                overlay.addEventListener('click', function (e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                        document.body.style.overflow = '';
                        // Remove the parameters from URL and reload
                        const url = new URL(window.location);
                        url.searchParams.delete('action');
                        url.searchParams.delete('id');
                        url.searchParams.delete('name');
                        url.searchParams.delete('error');
                        window.location.href = url.toString(); // This will reload the page
                    }
                });
            });
        });
    </script>
    <?php
    // Render Add New Patient modal when requested (mirrors dentist add modal)
    if (isset($_GET['action']) && $_GET['action'] == 'add') {
        $error_1 = isset($_GET['error']) ? $_GET['error'] : '0';
        $errorlist = array(
            '1' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Already have an account for this Email address.</label>',
            '2' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Password Confirmation Error! Reconfirm Password</label>',
            '3' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;"></label>',
            '4' => "",
            '0' => '',
        );

        if ($error_1 != '4') {
            // build branch options; respect branch restriction so restricted admins
            // (e.g., adminbacoor) only see their branch in the select.
            $branch_options = "";
            if ($restrictedBranchId > 0) {
                $brres2 = $database->query("SELECT id, name FROM branches WHERE id = $restrictedBranchId ORDER BY name ASC");
            } else {
                $brres2 = $database->query("SELECT id, name FROM branches ORDER BY name ASC");
            }
            if ($brres2 && $brres2->num_rows > 0) {
                while ($br = $brres2->fetch_assoc()) {
                    $selected_attr = ($restrictedBranchId > 0 && (int)$br['id'] === $restrictedBranchId) ? ' selected' : '';
                    $branch_options .= "<option value='" . $br['id'] . "'" . $selected_attr . ">" . htmlspecialchars($br['name']) . "</option>";
                }
            }

            $modal = <<<HTML
                <div id="popup1" class="overlay">
                        <div class="popup">
                        <center>
                            <a class="close" href="patient.php">&times;</a>
                            <div style="display: flex;justify-content: center;">
                            <div class="abc">
                            <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                            <tr>
                                    <td class="label-td" colspan="2">{$errorlist[$error_1]}</td>
                                </tr>
                                <tr>
                                    <td>
                                        <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">Add New Patient</p><br><br>
                                    </td>
                                </tr>
                                <tr>
                                    <form action="add-patient.php" method="POST" class="add-new-form" enctype="multipart/form-data">
                                    <td class="label-td" colspan="2">
                                        <label for="name" class="form-label">Name: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="name" class="input-text" placeholder="Patient Name" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="Email" class="form-label">Email: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="email" name="email" class="input-text" placeholder="Email Address" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="Tele" class="form-label">Telephone: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="tel" name="Tele" class="input-text" placeholder="Telephone Number" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="paddress" class="form-label">Address: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="paddress" class="input-text" placeholder="Street, City, Province"><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="pdob" class="form-label">Date of Birth: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="date" name="pdob" class="input-text" placeholder="YYYY-MM-DD"><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="branch_id" class="form-label">Branch: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <select name="branch_id" class="input-text">
                                            <option value="">-- Select Branch --</option>
                                            {$branch_options}
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="photo" class="form-label">Photo: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="file" name="photo" class="input-text" accept="image/*"><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="password" class="form-label">Password: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="password" name="password" class="input-text" placeholder="Create Password" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="cpassword" class="form-label">Confirm Password: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="password" name="cpassword" class="input-text" placeholder="Confirm Password" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">
                                        <input type="reset" value="Reset" class="login-btn btn-primary-soft btn">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                        <input type="submit" value="Add" class="login-btn btn-primary btn">
                                    </td>
                                </tr>
                                </form>
                            </table>
                            </div>
                            </div>
                        </center>
                </div>
                </div>
            HTML;

            echo $modal;
        } else {
            // success overlay
            $success = <<<HTML
                <div id="popup1" class="overlay">
                        <div class="popup">
                        <center>
                            <h2>New Patient Added Successfully!</h2>
                            <a class="close" href="patient.php">&times;</a>
                            <div class="content" style="height: 0px;">
                            </div>
                            <div style="display: flex;justify-content: center;">
                            <a href="patient.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;OK&nbsp;&nbsp;</font></button></a>
                            </div>
                        </center>
                </div>
                </div>
            HTML;

            echo $success;
        }
    }
    ?>
    <script>
        // Mobile sidebar toggle for Patient page
        document.addEventListener('DOMContentLoaded', function () {
            const hamburger = document.getElementById('hamburgerAdmin');
            const sidebar = document.getElementById('adminSidebar');
            const overlay = document.getElementById('sidebarOverlay');

            if (hamburger && sidebar && overlay) {
                const closeSidebar = () => {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('visible');
                    hamburger.setAttribute('aria-expanded', 'false');
                };

                const openSidebar = () => {
                    sidebar.classList.add('open');
                    overlay.classList.add('visible');
                    hamburger.setAttribute('aria-expanded', 'true');
                };

                hamburger.addEventListener('click', function () {
                    if (sidebar.classList.contains('open')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });

                overlay.addEventListener('click', function () {
                    closeSidebar();
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') closeSidebar();
                });
            }
        });
    </script>
</body>

</html>
