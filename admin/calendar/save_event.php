<?php
require 'database_connection.php';
require '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load mail config
$mailConfig = [];
try {
    $mailConfig = require __DIR__ . '/../../inc/mail_config.php';
} catch (Exception $e) {
    error_log('Could not load mail configuration: ' . $e->getMessage());
}

session_start();
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => false, 'msg' => 'User not logged in.']);
    exit;
}

// Handle new event creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_name'])) {
    // Sanitize and validate input data
    $event_name = trim($_POST['event_name']);
    $procedure = intval($_POST['procedure']);
    $patient_name = intval($_POST['patient_name']);
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $docid = intval($_POST['docid']);

    // Validate inputs
    if (empty($event_name) || $procedure <= 0 || $patient_name <= 0 || 
        empty($appointment_date) || empty($appointment_time) || $docid <= 0) {
        echo json_encode(['status' => false, 'msg' => 'All fields are required.']);
        exit;
    }

    // Check if the time slot is available
    $checkQuery = $con->prepare("SELECT appoid FROM appointment 
                                WHERE docid = ? AND appodate = ? AND appointment_time = ?
                                AND status IN ('appointment', 'booking')");
    $checkQuery->bind_param("iss", $docid, $appointment_date, $appointment_time);
    $checkQuery->execute();
    $checkResult = $checkQuery->get_result();

    if ($checkResult->num_rows > 0) {
        echo json_encode(['status' => false, 'msg' => 'This time slot is already booked.']);
        exit;
    }

    // Determine branch_id for the appointment. Prefer provided POST branch_id, then session active branch,
    // then doctor's first mapping, then doctor's branch_id column as fallback.
    $branch_id = null;
    if (!empty($_POST['branch_id'])) {
        $branch_id = (int)$_POST['branch_id'];
    } elseif (!empty($_SESSION['active_branch_id'])) {
        $branch_id = (int)$_SESSION['active_branch_id'];
    } else {
        // try doctor_branches
        $dbr = $con->prepare("SELECT branch_id FROM doctor_branches WHERE docid = ? LIMIT 1");
        $dbr->bind_param("i", $docid);
        $dbr->execute();
        $dbrres = $dbr->get_result();
        if ($dbrres && $dbrres->num_rows>0) {
            $branch_id = (int)$dbrres->fetch_assoc()['branch_id'];
        } else {
            // fallback to doctor.branch_id
            $dres = $con->prepare("SELECT branch_id FROM doctor WHERE docid=? LIMIT 1");
            $dres->bind_param("i", $docid);
            $dres->execute();
            $drow = $dres->get_result();
            if ($drow && $drow->num_rows>0) $branch_id = (int)$drow->fetch_assoc()['branch_id'];
        }
    }

    // Insert new appointment including branch_id
    $query = $con->prepare("INSERT INTO appointment 
                           (pid, docid, appodate, appointment_time, procedure_id, event_name, branch_id, status) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'appointment')");
    $query->bind_param("iisssis", $patient_name, $docid, $appointment_date, $appointment_time, $procedure, $event_name, $branch_id);

    if ($query->execute()) {
        $appoid = $con->insert_id;
        
        // Fetch details for notification and email
    $detailsQuery = $con->prepare("
        SELECT p.pid, p.pname, p.pemail, d.docname, d.docemail, d.branch_id, b.name AS branch_name, pr.procedure_name
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        JOIN doctor d ON a.docid = d.docid
        LEFT JOIN branches b ON d.branch_id = b.id
        JOIN procedures pr ON a.procedure_id = pr.procedure_id
        WHERE a.appoid = ?
    ");
        $detailsQuery->bind_param("i", $appoid);
        $detailsQuery->execute();
        $result = $detailsQuery->get_result();
        $appointment = $result->fetch_assoc();

        if (!$appointment) {
            echo json_encode([
                'status' => true,
                'msg' => 'Appointment created but could not fetch details for notification.',
                'appointment' => [
                    'appointment_id' => $appoid,
                    'title' => $event_name,
                    'start' => date("Y-m-d H:i:s", strtotime($appointment_date . ' ' . $appointment_time)),
                    'end' => date("Y-m-d H:i:s", strtotime($appointment_date . ' ' . $appointment_time)),
                    'status' => 'appointment'
                ]
            ]);
            exit;
        }

        // Format the date for display
        $formattedDate = date('F j, Y', strtotime($appointment_date));
        
        // Create notification for patient
        $notificationTitle = "New Appointment Confirmed";
        $notificationMessage = "Your appointment for " . $appointment['procedure_name'] . " with Dr. " . 
                             $appointment['docname'] . " on " . $formattedDate . " at " . 
                             $appointment_time . " has been confirmed.";
        
        $notificationQuery = $con->prepare("
            INSERT INTO notifications (user_id, user_type, title, message, related_id, related_type, created_at, is_read)
            VALUES (?, 'p', ?, ?, ?, 'appointment', NOW(), 0)
        ");
        $notificationQuery->bind_param("issi", 
            $appointment['pid'], 
            $notificationTitle, 
            $notificationMessage, 
            $appoid
        );
        $notificationQuery->execute();

        // Send confirmation email using centralized config
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $mailConfig['host'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $mailConfig['username'] ?? '';
            $mail->Password = $mailConfig['password'] ?? '';
            $mail->SMTPSecure = $mailConfig['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $mailConfig['port'] ?? 587;

            $mail->setFrom($mailConfig['from_email'] ?? 'no-reply@example.com', $mailConfig['from_name'] ?? 'IHeartDentistDC');
            if (!empty($appointment['pemail'])) $mail->addAddress($appointment['pemail'], $appointment['pname']);
            if (!empty($appointment['docemail'])) $mail->addCC($appointment['docemail'], $appointment['docname']);
            if (!empty($mailConfig['from_email'])) $mail->addCC($mailConfig['from_email']);

            $mail->isHTML(true);
            // Relax SSL checks for local dev (remove in production)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            $mail->Subject = 'New Appointment Confirmation';
            $mail->Body = "
                <h3>New Appointment Confirmation</h3>
                <p>Your appointment has been successfully scheduled with I Heart Dentist Dental Clinic.</p>
                
                <h4>Appointment Details:</h4>
                <p><strong>Patient Name:</strong> {$appointment['pname']}</p>
                <p><strong>Dentist:</strong> Dr. {$appointment['docname']}</p>
                <p><strong>Date:</strong> $formattedDate</p>
                <p><strong>Time:</strong> $appointment_time</p>
                <p><strong>Procedure:</strong> {$appointment['procedure_name']}</p>
                
                <p>Please arrive 10 minutes before your scheduled time.</p>
                <p>If you need to reschedule or cancel, please contact us at least 24 hours in advance.</p>
                
                <p>Thank you for choosing I Heart Dentist Dental Clinic!</p>
            ";

            $mail->send();

            echo json_encode([
                'status' => true,
                'msg' => 'Appointment created successfully. Notification and confirmation sent to patient.',
                'appointment' => [
                    'appointment_id' => $appoid,
                    'title' => $event_name . ' with ' . $appointment['pname'] . (isset($appointment['branch_name']) ? ' — ' . $appointment['branch_name'] : ''),
                    'start' => date("Y-m-d H:i:s", strtotime($appointment_date . ' ' . $appointment_time)),
                    'end' => date("Y-m-d H:i:s", strtotime($appointment_date . ' ' . $appointment_time)),
                    'status' => 'appointment',
                    'procedure_name' => $appointment['procedure_name'],
                    'patient_name' => $appointment['pname'],
                    'dentist_name' => $appointment['docname'],
                    'branch_id' => isset($appointment['branch_id']) ? $appointment['branch_id'] : null,
                    'branch_name' => isset($appointment['branch_name']) ? $appointment['branch_name'] : null
                ]
            ]);
        } catch (Exception $e) {
            error_log("Mailer Error: " . $e->getMessage());
            $mailError = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
            echo json_encode([
                'status' => true,
                'msg' => 'Appointment created successfully. Notification created but email failed to send.',
                'mail_error' => $mailError,
                'appointment' => [
                    'appointment_id' => $appoid,
                    'title' => $event_name . ' with ' . $appointment['pname'] . (isset($appointment['branch_name']) ? ' — ' . $appointment['branch_name'] : ''),
                    'start' => date("Y-m-d H:i:s", strtotime($appointment_date . ' ' . $appointment_time)),
                    'end' => date("Y-m-d H:i:s", strtotime($appointment_date . ' ' . $appointment_time)),
                    'status' => 'appointment',
                    'procedure_name' => $appointment['procedure_name'],
                    'patient_name' => $appointment['pname'],
                    'dentist_name' => $appointment['docname'],
                    'branch_id' => isset($appointment['branch_id']) ? $appointment['branch_id'] : null,
                    'branch_name' => isset($appointment['branch_name']) ? $appointment['branch_name'] : null
                ]
            ]);
        }
    } else {
        echo json_encode([
            'status' => false, 
            'msg' => 'Failed to create appointment. Database error: ' . $con->error
        ]);
    }
    exit;
}

// Handle appointment cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appoid'])) {
    $appoid = intval($_POST['appoid']);
    $reason = trim($_POST['cancel_reason']);
    $other_reason = isset($_POST['other_reason']) ? trim($_POST['other_reason']) : '';
    $full_reason = ($reason == 'Other') ? "Other: " . $other_reason : $reason;

    if (empty($reason)) {
        echo json_encode(['status' => false, 'msg' => 'Cancellation reason is required.']);
        exit;
    }

    // Fetch full appointment details
    $query = $con->prepare("
        SELECT a.*, p.pid, p.pname, p.pemail, d.docname, d.docemail, pr.procedure_name
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        JOIN doctor d ON a.docid = d.docid
        JOIN procedures pr ON a.procedure_id = pr.procedure_id
        WHERE a.appoid = ?
    ");
    $query->bind_param("i", $appoid);
    $query->execute();
    $result = $query->get_result();
    $appointment = $result->fetch_assoc();

    if (!$appointment) {
        echo json_encode(['status' => false, 'msg' => 'Appointment not found.']);
        exit;
    }

    $newStatus = ($appointment['status'] === 'booking') ? 'rejected' : 'cancelled';

    // Archive the appointment
    $archiveQuery = $con->prepare("
        INSERT INTO appointment_archive 
        (appoid, pid, docid, appodate, appointment_time, 
         procedure_id, event_name, status, cancel_reason, archived_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    // Convert time format if needed
    $time_parts = explode('-', $appointment['appointment_time']);
    $start_time = trim($time_parts[0]) . ':00';
    if (strlen($start_time) === 7) {
        $start_time = '0' . $start_time;
    }

    $archiveQuery->bind_param(
        "iissssss",
        $appointment['appoid'],
        $appointment['pid'],
        $appointment['docid'],
        $appointment['appodate'],
        $start_time,
        $appointment['procedure_id'],
        $appointment['event_name'],
        $newStatus,
        $full_reason
    );

    if ($archiveQuery->execute()) {
        // Delete the original appointment
        $deleteQuery = $con->prepare("DELETE FROM appointment WHERE appoid = ?");
        $deleteQuery->bind_param("i", $appoid);
        
        if ($deleteQuery->execute()) {
            // Create notification for patient
            $notificationTitle = ($newStatus === 'rejected') ? "Booking Rejected" : "Appointment Cancelled";
            $notificationMessage = ($newStatus === 'rejected') 
                ? "Your booking for " . $appointment['procedure_name'] . " on " . 
                  date('M j, Y', strtotime($appointment['appodate'])) . " at " . 
                  date('g:i A', strtotime($appointment['appointment_time'])) . 
                  " has been rejected. Reason: " . $full_reason
                : "Your appointment for " . $appointment['procedure_name'] . " on " . 
                  date('M j, Y', strtotime($appointment['appodate'])) . " at " . 
                  date('g:i A', strtotime($appointment['appointment_time'])) . 
                  " has been cancelled. Reason: " . $full_reason;
            
            $notificationQuery = $con->prepare("
                INSERT INTO notifications (user_id, user_type, title, message, related_id, related_type, created_at, is_read)
                VALUES (?, 'p', ?, ?, ?, 'appointment', NOW(), 0)
            ");
            $notificationQuery->bind_param("issi", 
                $appointment['pid'], 
                $notificationTitle, 
                $notificationMessage, 
                $appoid
            );
            $notificationQuery->execute();

            // Send cancellation email using centralized mail config
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = $mailConfig['host'] ?? 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $mailConfig['username'] ?? '';
                $mail->Password = $mailConfig['password'] ?? '';
                $mail->SMTPSecure = $mailConfig['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $mailConfig['port'] ?? 587;

                $mail->setFrom($mailConfig['from_email'] ?? 'no-reply@example.com', $mailConfig['from_name'] ?? 'IHeartDentistDC');
                if (!empty($appointment['pemail'])) $mail->addAddress($appointment['pemail'], $appointment['pname']);
                if (!empty($appointment['docemail'])) $mail->addCC($appointment['docemail'], $appointment['docname']);
                if (!empty($mailConfig['from_email'])) $mail->addCC($mailConfig['from_email']);

                $mail->isHTML(true);
                // Relax SSL checks for local dev (remove in production)
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
                $mail->Subject = 'Appointment ' . ucfirst($newStatus) . ' Notification';
                // Use simpler cancellation wording like the screenshot
                if ($newStatus === 'rejected') {
                    $intro = 'Your booking has been rejected by the clinic.';
                } else {
                    $intro = 'Your appointment has been cancelled by the clinic.';
                }

                $mail->Body = "
                    <h3>Appointment " . ucfirst($newStatus) . "</h3>
                    <p>$intro</p>
                    
                    <h4>Original Appointment Details:</h4>
                    <p><strong>Patient Name:</strong> {$appointment['pname']}</p>
                    <p><strong>Dentist Name:</strong> {$appointment['docname']}</p>
                    <p><strong>Date:</strong> " . date('F j, Y', strtotime($appointment['appodate'])) . "</p>
                    <p><strong>Time:</strong> {$appointment['appointment_time']}</p>
                    <p><strong>Procedure:</strong> {$appointment['procedure_name']}</p>
                    
                    <h4>Reason:</h4>
                    <p>$full_reason</p>
                    
                    <p>Please contact the clinic if you wish to reschedule or for more information.</p>
                ";

                $mail->send();
                echo json_encode([
                    'status' => true,
                    'msg' => "Appointment has been $newStatus successfully. Notification sent to patient."
                ]);
            } catch (Exception $e) {
                error_log("Mailer Error: " . $e->getMessage());
                $mailError = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
                echo json_encode([
                    'status' => true,
                    'msg' => "Appointment has been $newStatus successfully. Notification created but email failed to send.",
                    'mail_error' => $mailError
                ]);
            }
        } else {
            echo json_encode(['status' => false, 'msg' => 'Failed to delete the appointment.']);
        }
    } else {
        echo json_encode(['status' => false, 'msg' => 'Failed to archive the appointment.']);
    }
    exit;
}

// If neither creation nor cancellation request
echo json_encode(['status' => false, 'msg' => 'Invalid request.']);
?>
