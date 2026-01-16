<?php
require 'database_connection.php';
require '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// load mail config
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appoid'])) {
    $appoid = intval($_POST['appoid']);
    $reason = $_POST['cancel_reason'];
    $other_reason = isset($_POST['other_reason']) ? $_POST['other_reason'] : '';
    $full_reason = ($reason == 'Other') ? "Other: " . $other_reason : $reason;

    // Fetch full appointment details with patient and doctor information
    $query = $con->prepare("
        SELECT a.*, p.pid, p.pname, p.pemail, d.docname, d.docemail, pr.procedure_name
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        JOIN doctor d ON a.docid = d.docid
        LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id
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

    // Fallback for missing procedure name
    $procedureText = !empty($appointment['procedure_name']) ? $appointment['procedure_name'] : 'To be assigned by the clinic';

    // Determine if this is a booking or appointment
    $isBooking = ($appointment['status'] === 'booking');
    $newStatus = $isBooking ? 'rejected' : 'cancelled';

    // Insert appointment into the archive table with reason
    $archiveQuery = $con->prepare("
    INSERT INTO appointment_archive 
    (appoid, pid, docid, appodate, appointment_time, 
    procedure_id, event_name, status, cancel_reason, archived_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    // Convert the time format before binding
    $time_parts = explode('-', $appointment['appointment_time']);
    $start_time = trim($time_parts[0]) . ':00'; // Convert "9:00" to "9:00:00"

    $archiveQuery->bind_param(
    "iiissssss",
    $appointment['appoid'],
    $appointment['pid'],
    $appointment['docid'],
    $appointment['appodate'],
    $start_time, // Use the converted time format
    $appointment['procedure_id'],
    $appointment['event_name'],
    $newStatus,
    $full_reason
    );

    if ($archiveQuery->execute()) {
        // Now delete the original appointment
        $deleteQuery = $con->prepare("DELETE FROM appointment WHERE appoid = ?");
        $deleteQuery->bind_param("i", $appoid);
        
        if ($deleteQuery->execute()) {
            // Create appropriate notification for patient
            $notificationTitle = $isBooking ? "Booking Rejected" : "Appointment Cancelled";
                        $notificationMessage = $isBooking 
                                ? "Your booking for " . $procedureText . " on " . 
                                    date('M j, Y', strtotime($appointment['appodate'])) . " at " . 
                                    date('g:i A', strtotime($appointment['appointment_time'])) . 
                                    " has been rejected. Reason: " . $full_reason
                                : "Your appointment for " . $procedureText . " on " . 
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

            // Send email notifications
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = $mailConfig['host'] ?? 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $mailConfig['username'] ?? '';
                $mail->Password = $mailConfig['password'] ?? '';
                $mail->SMTPSecure = $mailConfig['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $mailConfig['port'] ?? 587;
                
                // Recipients - send to patient
                $mail->setFrom($mailConfig['from_email'] ?? 'no-reply@example.com', $mailConfig['from_name'] ?? 'IHeartDentistDC');
                $mail->addAddress($appointment['pemail'], $appointment['pname']);
                if (!empty($appointment['docemail'])) $mail->addCC($appointment['docemail'], $appointment['docname']); // CC to dentist
                if (!empty($mailConfig['from_email'])) $mail->addCC($mailConfig['from_email']); // CC to admin
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = $notificationTitle;
                    $mail->Body = "
                    <h3>$notificationTitle</h3>
                    <p>Your " . ($isBooking ? 'booking' : 'appointment') . " has been $newStatus by the clinic.</p>
                    <p><strong>Patient Name:</strong> {$appointment['pname']}</p>
                    <p><strong>Dentist Name:</strong> {$appointment['docname']}</p>
                    <p><strong>Date:</strong> " . date('F j, Y', strtotime($appointment['appodate'])) . "</p>
                    <p><strong>Time:</strong> {$appointment['appointment_time']}</p>
                    <p><strong>Procedure:</strong> {$procedureText}</p>
                    <p><strong>Reason:</strong> $full_reason</p>
                    <p>Please contact the clinic if you wish to reschedule or for more information.</p>
                ";
                
                $mail->send();
                
                echo json_encode([
                    'status' => true,
                    'msg' => ucfirst($isBooking ? 'booking' : 'appointment') . " has been $newStatus successfully. Notification sent."
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'status' => true,
                    'msg' => ucfirst($isBooking ? 'booking' : 'appointment') . " has been $newStatus successfully, notification created, but email failed to send."
                ]);
            }
        } else {
            echo json_encode(['status' => false, 'msg' => 'Failed to delete the ' . ($isBooking ? 'booking' : 'appointment') . '.']);
        }
    } else {
        echo json_encode(['status' => false, 'msg' => 'Failed to archive the ' . ($isBooking ? 'booking' : 'appointment') . '.']);
    }
} else {
    echo json_encode(['status' => false, 'msg' => 'Invalid request.']);
}
?>
