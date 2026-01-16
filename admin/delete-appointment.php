<?php
session_start();
// Ensure API-style JSON responses for fetch callers
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');
ob_start();

require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if the user is logged in and has admin or dentist privileges
if (!isset($_SESSION["user"]) || $_SESSION["user"] == "") {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['status' => false, 'message' => 'Unauthorized. Please log in.']);
        exit();
    }
    header("location: ../login.php");
    exit();
}

// Allow only admins or dentists to call this endpoint
if (!in_array($_SESSION['usertype'], ['a', 'd'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo json_encode(['status' => false, 'message' => 'Forbidden.']);
        exit();
    }
    header("location: ../login.php");
    exit();
}

include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Accept multiple possible parameter names (dentist JS sends `id` and `reason`)
    $appoid = isset($_POST['appoid']) ? $_POST['appoid'] : (isset($_POST['id']) ? $_POST['id'] : null);
    $source = isset($_POST['source']) ? $_POST['source'] : '';
    $reason = isset($_POST['cancel_reason']) ? $_POST['cancel_reason'] : (isset($_POST['reason']) ? $_POST['reason'] : '');
    $other_reason = isset($_POST['other_reason']) ? $_POST['other_reason'] : '';
    $full_reason = ($reason == 'Other') ? "Other: " . $other_reason : $reason;
    
    // First get appointment details before archiving (including patient ID)
    $appointmentQuery = $database->query("
        SELECT a.*, p.pid, p.pemail, p.pname, pr.procedure_name
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id
        WHERE a.appoid = '$appoid'
    ");
    
    if (!$appointmentQuery || $appointmentQuery->num_rows == 0) {
        echo json_encode(['status' => false, 'message' => 'Appointment not found.', 'msg' => 'Appointment not found.']);
        exit();
    }
    
    $appointment = $appointmentQuery->fetch_assoc();

        // If the caller is a dentist, ensure they own this appointment
        if (isset($_SESSION['usertype']) && $_SESSION['usertype'] === 'd') {
            $useremail = $_SESSION['user'];
            $docRes = $database->query("SELECT docid FROM doctor WHERE docemail = '" . $database->real_escape_string($useremail) . "' LIMIT 1");
            $docRow = $docRes ? $docRes->fetch_assoc() : null;
            if (!$docRow || $docRow['docid'] != $appointment['docid']) {
                echo json_encode(['status' => false, 'message' => "Appointment not found or you don't have permission to cancel it", 'msg' => "Appointment not found or you don't have permission to cancel it"]);
                exit();
            }
        }
    
    // Archive the appointment
        // Some installations have different schemas for `appointment_archive`.
        // Detect whether `branch_id` exists on the archive table and build the appropriate query.
        $hasBranchInArchive = false;
        $colCheck = $database->query("SHOW COLUMNS FROM appointment_archive LIKE 'branch_id'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $hasBranchInArchive = true;
        }

        if ($hasBranchInArchive) {
            // appointment_archive expects a branch_id column — supply NULL if appointment table doesn't have it
            $archive_query = "INSERT INTO appointment_archive 
                (archive_id, appoid, pid, docid, apponum, scheduleid, appodate, appointment_time, 
                 procedure_id, event_name, status, cancel_reason, branch_id, archived_at)
                SELECT NULL, appoid, pid, docid, apponum, scheduleid, appodate, appointment_time, 
                    procedure_id, event_name, 'cancelled', ?, NULL, NOW()
                FROM appointment 
                WHERE appoid = ?";
            $stmt = $database->prepare($archive_query);
            $stmt->bind_param("si", $full_reason, $appoid);
        } else {
            // appointment_archive does not have branch_id — omit it from the INSERT
            $archive_query = "INSERT INTO appointment_archive 
                (archive_id, appoid, pid, docid, apponum, scheduleid, appodate, appointment_time, 
                 procedure_id, event_name, status, cancel_reason, archived_at)
                SELECT NULL, appoid, pid, docid, apponum, scheduleid, appodate, appointment_time, 
                    procedure_id, event_name, 'cancelled', ?, NOW()
                FROM appointment 
                WHERE appoid = ?";
            $stmt = $database->prepare($archive_query);
            $stmt->bind_param("si", $full_reason, $appoid);
        }
        $archive_result = $stmt->execute();
    
    if (!$archive_result) {
        echo json_encode(['status' => false, 'message' => 'Failed to archive appointment.', 'msg' => 'Failed to archive appointment.']);
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
            
            // Determine redirect: prefer provided return_query, otherwise use HTTP_REFERER if it's appointment.php
            $redirect = '';
            if (isset($_POST['return_query']) && $_POST['return_query'] !== '') {
                $redirect = 'appointment.php?cancel_success=1&' . $_POST['return_query'];
            } elseif (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'appointment.php') !== false) {
                $redirect = $_SERVER['HTTP_REFERER'];
            } else {
                $redirect = 'appointment.php?cancel_success=1';
            }

            echo json_encode([
                'status' => true,
                'message' => 'Appointment cancelled successfully. Notification sent to patient.',
                'redirect' => $redirect
            ]);
        } catch (Exception $e) {
            $redirect = '';
            if (isset($_POST['return_query']) && $_POST['return_query'] !== '') {
                $redirect = 'appointment.php?cancel_success=1&' . $_POST['return_query'];
            } elseif (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'appointment.php') !== false) {
                $redirect = $_SERVER['HTTP_REFERER'];
            } else {
                $redirect = 'appointment.php?cancel_success=1';
            }

            echo json_encode([
                'status' => true,
                'message' => 'Appointment cancelled successfully, notification created, but email failed to send.',
                'redirect' => $redirect
            ]);
        }
    } else {
        echo json_encode([
            'status' => false,
            'message' => 'Failed to cancel appointment. Please try again.',
            'msg' => 'Failed to cancel appointment. Please try again.'
        ]);
    }
} else {
    echo json_encode([
        'status' => false,
        'message' => 'Invalid request method.',
        'msg' => 'Invalid request method.'
    ]);
}
?>