<?php
session_start();
date_default_timezone_set('Asia/Singapore');
include("../connection.php");

function log_fail($msg) {
    error_log('[PAYMENT_SUCCESS] ' . $msg);
    echo "<script>alert('Payment processing error. Please contact support.');window.location.href='dashboard.php';</script>";
    exit();
}

if (isset($_GET['id']) && isset($_GET['type'])) {
    $appoid_raw = $_GET['id'];
    $type = $_GET['type'];
    if (!ctype_digit($appoid_raw)) { log_fail('Invalid appointment id: ' . $appoid_raw); }
    $appoid = (int)$appoid_raw;

    // Idempotence: Fetch current state to see if already processed
    $stateStmt = $database->prepare('SELECT status, payment_status, reservation_paid FROM appointment WHERE appoid=? LIMIT 1');
    if (!$stateStmt) { log_fail('Prepare state select failed: ' . $database->error); }
    $stateStmt->bind_param('i',$appoid);
    if (!$stateStmt->execute()) { log_fail('Execute state select failed: ' . $stateStmt->error); }
    $stateRes = $stateStmt->get_result();
    if ($stateRes->num_rows !== 1) { log_fail('Appointment not found for id ' . $appoid); }
    $current = $stateRes->fetch_assoc();
    $curStatus = $current['status'];
    $curPayStatus = $current['payment_status'];
    $curReservationPaid = (int)$current['reservation_paid'];

    if ($type === 'reservation' && $curStatus === 'booking' && $curReservationPaid === 1) {
        echo "<script>alert('Reservation payment already processed.');window.location.href='my_booking.php';</script>"; exit();
    }
    if ($type === 'balance' && $curStatus === 'completed' && $curPayStatus === 'paid') {
        echo "<script>alert('Balance payment already processed.');window.location.href='my_appointment.php';</script>"; exit();
    }

    if ($type == 'reservation') {
        // Update appointment to booking (partial payment)
        $stmtUpd = $database->prepare("UPDATE appointment SET status='booking', payment_status='partial', reservation_paid=1, payment_method='paymongo' WHERE appoid=? LIMIT 1");
        if (!$stmtUpd) { log_fail('Prepare reservation update failed: ' . $database->error); }
        $stmtUpd->bind_param('i',$appoid);
        if ($stmtUpd->execute()) {
            // Ensure branch_id is set using session or patient record
            $appResStmt = $database->prepare('SELECT branch_id, pid FROM appointment WHERE appoid=? LIMIT 1');
            if ($appResStmt) {
                $appResStmt->bind_param('i',$appoid);
                $appResStmt->execute();
                $appRes = $appResStmt->get_result();
                if ($appRes && $appRes->num_rows > 0) {
                    $appRow = $appRes->fetch_assoc();
                    if (empty($appRow['branch_id'])) {
                        $setBranch = null;
                        if (!empty($_SESSION['active_branch_id'])) {
                            $setBranch = (int)$_SESSION['active_branch_id'];
                        } else {
                            $pbStmt = $database->prepare('SELECT branch_id FROM patient WHERE pid=? LIMIT 1');
                            if ($pbStmt) {
                                $pbStmt->bind_param('i',$appRow['pid']);
                                $pbStmt->execute();
                                $pb = $pbStmt->get_result();
                                if ($pb && $pb->num_rows > 0) {
                                    $setBranch = (int)$pb->fetch_assoc()['branch_id'];
                                }
                            }
                        }
                        if (!empty($setBranch)) {
                            $branchUpdStmt = $database->prepare('UPDATE appointment SET branch_id=? WHERE appoid=? LIMIT 1');
                            if ($branchUpdStmt) { $branchUpdStmt->bind_param('ii',$setBranch,$appoid); $branchUpdStmt->execute(); }
                        }
                    }
                }
            }

            // --- NOTIFICATION LOGIC START ---
            
            // Fetch details for notifications (patient + dentist)
            $detailsStmt = $database->prepare("SELECT a.appodate, a.appointment_time, p.pid, p.pname, d.docid, pr.procedure_name FROM appointment a INNER JOIN patient p ON a.pid=p.pid INNER JOIN doctor d ON a.docid=d.docid INNER JOIN procedures pr ON a.procedure_id=pr.procedure_id WHERE a.appoid=? LIMIT 1");
            if ($detailsStmt) {
                $detailsStmt->bind_param('i',$appoid);
                $detailsStmt->execute();
                $detailsQuery = $detailsStmt->get_result();
            } else { $detailsQuery = false; }

            if ($detailsQuery && $detailsQuery->num_rows > 0) {
                $row = $detailsQuery->fetch_assoc();
                
                $pid = $row['pid'];
                $docid = $row['docid'];
                $patientName = $row['pname'];
                $procedureName = $row['procedure_name'];
                $appDate = date('M j, Y', strtotime($row['appodate']));
                $appTime = date('g:i A', strtotime($row['appointment_time']));

                // 1. Create notification for PATIENT
                $notificationTitle = "Booking Confirmed";
                $notificationMessage = "Your appointment for " . $procedureName . " on " . $appDate . " at " . $appTime . " has been confirmed. Reservation fee paid.";
                
                $stmt = $database->prepare("INSERT INTO notifications (user_id, user_type, title, message, related_id, related_type, created_at) VALUES (?, 'p', ?, ?, ?, 'appointment', NOW())");
                if ($stmt) {
                    $stmt->bind_param("issi", $pid, $notificationTitle, $notificationMessage, $appoid);
                    $stmt->execute();
                    $stmt->close();
                } else { log_fail('Prepare patient notification failed: ' . $database->error); }

                // 2. Create notification for DENTIST
                $dentistTitle = "New Appointment Booking";
                $dentistMessage = "New confirmed appointment booked by " . $patientName . " for " . $procedureName . " on " . $appDate . " at " . $appTime;

                $stmt2 = $database->prepare("INSERT INTO notifications (user_id, user_type, title, message, related_id, related_type, created_at) VALUES (?, 'd', ?, ?, ?, 'appointment', NOW())");
                if ($stmt2) {
                    $stmt2->bind_param("issi", $docid, $dentistTitle, $dentistMessage, $appoid);
                    $stmt2->execute();
                    $stmt2->close();
                } else { log_fail('Prepare dentist notification failed: ' . $database->error); }
            }
            // --- NOTIFICATION LOGIC END ---

            echo "<script>
                alert('Reservation Fee Paid! Your booking is now confirmed.');
                window.location.href = 'my_booking.php';
            </script>";
        } else {
            echo "Error updating record: " . $database->error;
        }
    } 
    elseif ($type == 'balance') {
        $stmtBal = $database->prepare("UPDATE appointment SET status='completed', payment_status='paid', payment_method='paymongo' WHERE appoid=? LIMIT 1");
        if (!$stmtBal) { log_fail('Prepare balance update failed: ' . $database->error); }
        $stmtBal->bind_param('i',$appoid);
        if ($stmtBal->execute()) {
            echo "<script>alert('Full Payment Successful! Appointment Completed.');window.location.href='my_appointment.php';</script>";
        } else { log_fail('Execute balance update failed: ' . $stmtBal->error); }
    }
} else {
    require_once __DIR__ . '/../inc/redirect_helper.php';
    redirect_with_context('dashboard.php');
}
?>