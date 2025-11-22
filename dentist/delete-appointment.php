<?php
session_start();
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in as dentist
if(!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: ../dentist/login.php");
    exit();
}

if(($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') && isset($_REQUEST["id"])) {
    include("../connection.php");
    
    $appoid = $_REQUEST["id"];
    $reason = isset($_REQUEST["reason"]) ? $_REQUEST["reason"] : "Cancelled by dentist";
    $useremail = $_SESSION["user"];
    
    // Get dentist info
    $dentistRow = $database->query("SELECT docid, docname FROM doctor WHERE docemail='$useremail'");
    $dentist = $dentistRow->fetch_assoc();
    $userid = $dentist["docid"];
    $dentistName = $dentist["docname"];
    
    // Get appointment details for email notification
    $query = $database->prepare("
        SELECT a.*, p.pid, p.pname, p.pemail, pr.procedure_name 
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        JOIN procedures pr ON a.procedure_id = pr.procedure_id
        WHERE a.appoid = ? AND a.docid = ? AND a.status = 'appointment'
    ");
    
    $query->bind_param("ii", $appoid, $userid);
    $query->execute();
    $result = $query->get_result();
    $appointment = $result->fetch_assoc();

    if(!$appointment) {
        echo json_encode(['status' => false, 'msg' => "Appointment not found or you don't have permission to cancel it"]);
        exit();
    }

    // Archive the appointment first
    $archiveQuery = $database->prepare("
        INSERT INTO appointment_archive 
        (appoid, pid, docid, appodate, appointment_time, 
         procedure_id, event_name, status, cancel_reason, archived_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'cancelled', ?, NOW())
    ");
    
    // Convert time format if needed
    $time_parts = explode('-', $appointment['appointment_time']);
    $start_time = trim($time_parts[0]) . ':00';
    if(strlen($start_time) === 7) {
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
        $reason
    );

    if($archiveQuery->execute()) {
        // Now delete from active appointments
        $deleteQuery = $database->prepare("DELETE FROM appointment WHERE appoid = ?");
        $deleteQuery->bind_param("i", $appoid);
        
        if($deleteQuery->execute()) {
            // Create notification for patient
            $notificationTitle = "Appointment Cancelled";
            $notificationMessage = "Your appointment for " . $appointment['procedure_name'] . " on " . 
                                 date('M j, Y', strtotime($appointment['appodate'])) . " at " . 
                                 date('g:i A', strtotime($appointment['appointment_time'])) . 
                                 " has been cancelled by Dr. " . $dentistName . ". Reason: " . $reason;
            
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
            
            // Send notification email
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
                
                // Recipients
                $mail->setFrom('grycenmagahis@gmail.com', 'I Heart Dentist Dental Clinic');
                $mail->addAddress($appointment['pemail'], $appointment['pname']);
                $mail->addCC($useremail, $dentistName);
                $mail->addCC('grycenmagahis@gmail.com'); // CC to admin
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Appointment Cancellation Notification';
                $mail->Body = "
                    <h3>Appointment Cancellation Notice</h3>
                    <p>We regret to inform you that your appointment has been cancelled by your dentist.</p>
                    
                    <h4>Cancelled Appointment Details:</h4>
                    <p><strong>Patient:</strong> {$appointment['pname']}</p>
                    <p><strong>Dentist:</strong> Dr. {$dentistName}</p>
                    <p><strong>Date:</strong> " . date('F j, Y', strtotime($appointment['appodate'])) . "</p>
                    <p><strong>Time:</strong> {$appointment['appointment_time']}</p>
                    <p><strong>Procedure:</strong> {$appointment['procedure_name']}</p>
                    
                    <h4>Cancellation Details:</h4>
                    <p><strong>Reason:</strong> {$reason}</p>
                    
                    <p>We apologize for any inconvenience this may cause. Please contact the clinic to reschedule.</p>
                    <p>Thank you for your understanding.</p>
                ";
                
                $mail->send();
                echo json_encode(['status' => true, 'msg' => "Appointment cancelled successfully. Notification sent."]);
            } catch (Exception $e) {
                echo json_encode(['status' => true, 'msg' => "Appointment cancelled but failed to send notification email."]);
                error_log("Mailer Error: " . $e->getMessage());
            }
        } else {
            echo json_encode(['status' => false, 'msg' => "Failed to delete the appointment."]);
        }
    } else {
        echo json_encode(['status' => false, 'msg' => "Failed to archive the appointment."]);
    }
} else {
    echo json_encode(['status' => false, 'msg' => "Invalid request."]);
}
?>