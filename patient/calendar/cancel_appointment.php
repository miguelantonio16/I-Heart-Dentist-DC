<?php
// Ensure we return JSON
header('Content-Type: application/json');

// Debugging - uncomment if needed
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../../connection.php';
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

// Initialize response array
$response = ['status' => false, 'msg' => 'Initialization failed'];

try {
    session_start();
    if (!isset($_SESSION['user'])) {
        throw new Exception('User not logged in');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['appoid'])) {
        throw new Exception('Invalid request method or missing parameters');
    }

    $appoid = (int)$_POST['appoid'];
    if ($appoid <= 0) {
        throw new Exception('Invalid appointment ID');
    }

    // Get appointment details with all related data
    $stmt = $database->prepare("
        SELECT a.*, d.docid, d.docname, d.docemail, 
               p.pid, p.pname, p.pemail,
               pr.procedure_id, pr.procedure_name
        FROM appointment a
        JOIN doctor d ON a.docid = d.docid
        JOIN patient p ON a.pid = p.pid
        JOIN procedures pr ON a.procedure_id = pr.procedure_id
        WHERE a.appoid = ?
    ");
    $stmt->bind_param("i", $appoid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Appointment not found');
    }

    $appointment = $result->fetch_assoc();
    $recordType = ($appointment['status'] === 'booking') ? 'booking' : 'appointment';
    $reason = "Cancelled by patient";
    $status = 'cancelled';

    // Start transaction
    $database->begin_transaction();

    // Archive the appointment - using SELECT INTO approach to avoid binding issues
        // Archive the appointment - include branch_id from appointment
        $archiveStmt = $database->prepare("
            INSERT INTO appointment_archive 
            (appoid, pid, docid, apponum, scheduleid, appodate, appointment_time, 
             procedure_id, event_name, status, cancel_reason, branch_id, archived_at)
            SELECT 
                appoid, pid, docid, apponum, scheduleid, appodate, appointment_time,
                procedure_id, event_name, ?, ?, branch_id, NOW()
            FROM appointment 
            WHERE appoid = ?
        ");
        $archiveStmt->bind_param("ssi", $status, $reason, $appoid);
    $archiveStmt->execute();

    // Delete from appointments
    $deleteStmt = $database->prepare("DELETE FROM appointment WHERE appoid = ?");
    $deleteStmt->bind_param("i", $appoid);
    $deleteStmt->execute();

    // Create notifications
    $notificationTitle = ucfirst($recordType) . " Cancelled";
    $notificationMessage = "Your $recordType for {$appointment['procedure_name']} on " . 
                         date('M j, Y', strtotime($appointment['appodate'])) . " at " .
                         date('g:i A', strtotime($appointment['appointment_time'])) . 
                         " has been cancelled. Reason: $reason";

    // For patient
    $notifStmt = $database->prepare("
        INSERT INTO notifications 
        (user_id, user_type, title, message, related_id, related_type) 
        VALUES (?, 'p', ?, ?, ?, ?)
    ");
    $notifStmt->bind_param(
        "issis",
        $appointment['pid'],
        $notificationTitle,
        $notificationMessage,
        $appoid,
        $recordType
    );
    $notifStmt->execute();

    // For dentist - need to prepare again or reset parameters
    $notifStmt2 = $database->prepare("
        INSERT INTO notifications 
        (user_id, user_type, title, message, related_id, related_type) 
        VALUES (?, 'd', ?, ?, ?, ?)
    ");
    $notifStmt2->bind_param(
        "issis",
        $appointment['docid'],
        $notificationTitle,
        $notificationMessage,
        $appoid,
        $recordType
    );
    $notifStmt2->execute();

    // Commit transaction
    $database->commit();

    // Send emails (try-catch to prevent email failure from affecting cancellation)
    $emailSuccess = false;
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $mailConfig['host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $mailConfig['username'] ?? '';
        $mail->Password = $mailConfig['password'] ?? '';
        $mail->SMTPSecure = $mailConfig['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $mailConfig['port'] ?? 587;
        
        // To dentist
        $mail->setFrom($mailConfig['from_email'] ?? 'no-reply@example.com', $mailConfig['from_name'] ?? 'IHeartDentistDC');
        $mail->addAddress($appointment['docemail'], $appointment['docname']);
        $mail->isHTML(true);
        $mail->Subject = "$recordType Cancellation Notification";
        $mail->Body = "<h3>$recordType Cancelled</h3>
            <p>Patient: {$appointment['pname']}</p>
            <p>Date: " . date('F j, Y', strtotime($appointment['appodate'])) . "</p>
            <p>Time: " . date('g:i A', strtotime($appointment['appointment_time'])) . "</p>
            <p>Procedure: {$appointment['procedure_name']}</p>";
        $mail->send();
        
        // To patient
        $mail->clearAddresses();
        $mail->addAddress($appointment['pemail'], $appointment['pname']);
        $mail->Subject = "Your $recordType Cancellation";
        $mail->Body = "<h3>Your $recordType Has Been Cancelled</h3>
            <p>Date: " . date('F j, Y', strtotime($appointment['appodate'])) . "</p>
            <p>Time: " . date('g:i A', strtotime($appointment['appointment_time'])) . "</p>
            <p>Procedure: {$appointment['procedure_name']}</p>";
        $mail->send();
        
        $emailSuccess = true;
    } catch (Exception $e) {
        $emailSuccess = false;
        // Log email error if needed
        error_log("Email error: " . $e->getMessage());
    }

    $response = [
        'status' => true,
        'msg' => "$recordType cancelled successfully",
        'email_sent' => $emailSuccess
    ];

} catch (Exception $e) {
    // Rollback on error
    if (isset($database) && $database instanceof mysqli && $database->thread_id) {
        $database->rollback();
    }
    $response = [
        'status' => false,
        'msg' => 'Error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ];
    error_log("Cancellation error: " . $e->getMessage());
}

// Ensure no output before this
echo json_encode($response);
exit;
?>
