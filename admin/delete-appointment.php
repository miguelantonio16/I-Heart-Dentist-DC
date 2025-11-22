<?php
session_start();
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if the user is logged in and has admin privileges
if (!isset($_SESSION["user"]) || ($_SESSION["user"] == "" || $_SESSION['usertype'] != 'a')) {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $appoid = $_POST['appoid'];
    $source = $_POST['source'];
    $reason = $_POST['cancel_reason'];
    $other_reason = isset($_POST['other_reason']) ? $_POST['other_reason'] : '';
    $full_reason = ($reason == 'Other') ? "Other: " . $other_reason : $reason;
    
    // First get appointment details before archiving (including patient ID)
    $appointmentQuery = $database->query("
        SELECT a.*, p.pid, p.pemail, p.pname, pr.procedure_name
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        JOIN procedures pr ON a.procedure_id = pr.procedure_id
        WHERE a.appoid = '$appoid'
    ");
    
    if (!$appointmentQuery || $appointmentQuery->num_rows == 0) {
        echo json_encode(['status' => false, 'message' => 'Appointment not found.']);
        exit();
    }
    
    $appointment = $appointmentQuery->fetch_assoc();
    
    // Archive the appointment
        $archive_query = "INSERT INTO appointment_archive 
                (archive_id, appoid, pid, docid, apponum, scheduleid, appodate, appointment_time, 
                 procedure_id, event_name, status, cancel_reason, branch_id, archived_at)
                SELECT NULL, appoid, pid, docid, apponum, scheduleid, appodate, appointment_time, 
                    procedure_id, event_name, 'cancelled', ?, branch_id, NOW()
                FROM appointment 
                WHERE appoid = ?";
    
        $stmt = $database->prepare($archive_query);
        $stmt->bind_param("si", $full_reason, $appoid);
        $archive_result = $stmt->execute();
    
    if (!$archive_result) {
        echo json_encode(['status' => false, 'message' => 'Failed to archive appointment.']);
        exit();
    }
    
    // Delete from appointments
    $delete_query = "DELETE FROM appointment WHERE appoid = ?";
    $stmt = $database->prepare($delete_query);
    $stmt->bind_param("i", $appoid);
    $delete_result = $stmt->execute();
    
    if ($delete_result) {
        // Create notification for patient only
        $notificationTitle = "Appointment Cancelled";
        $notificationMessage = "Your appointment for " . $appointment['procedure_name'] . " on " . 
                             date('M j, Y', strtotime($appointment['appodate'])) . " at " . 
                             date('g:i A', strtotime($appointment['appointment_time'])) . 
                             " has been cancelled. Reason: " . $full_reason;
        
        $notificationQuery = $database->prepare("
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
        
        // Send email notification to patient
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'grycenmagahis@gmail.com';
            $mail->Password = 'suxg svrk tfuo jvni';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Recipient - patient only
            $mail->setFrom('grycenmagahis@gmail.com', 'I Heart Dentist Dental Clinic');
            $mail->addAddress($appointment['pemail'], $appointment['pname']);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Appointment Cancellation Notification';
            $mail->Body = "
                <h3>Appointment Cancellation Notification</h3>
                <p>Your appointment has been cancelled by the clinic administration.</p>
                <p><strong>Procedure:</strong> {$appointment['procedure_name']}</p>
                <p><strong>Date:</strong> " . date('F j, Y', strtotime($appointment['appodate'])) . "</p>
                <p><strong>Time:</strong> " . date('g:i A', strtotime($appointment['appointment_time'])) . "</p>
                <p><strong>Reason:</strong> $full_reason</p>
                <p>Please contact the clinic if you wish to reschedule.</p>
            ";
            
            $mail->send();
            
            echo json_encode([
                'status' => true,
                'message' => 'Appointment cancelled successfully. Notification sent to patient.'
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => true,
                'message' => 'Appointment cancelled successfully, notification created, but email failed to send.'
            ]);
        }
    } else {
        echo json_encode([
            'status' => false,
            'message' => 'Failed to cancel appointment. Please try again.'
        ]);
    }
} else {
    echo json_encode([
        'status' => false,
        'message' => 'Invalid request method.'
    ]);
}
?>