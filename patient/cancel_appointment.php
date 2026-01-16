<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
session_start();
include("../connection.php");
require_once __DIR__ . '/../inc/redirect_helper.php';

// Check if user is logged in
if (!isset($_SESSION["user"])) {
    header("location: login.php");
    exit;
}

// Handle both GET (from my_booking.php) and POST requests (from form)
if($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    // Coming from my_booking.php with GET parameters
    $appoid = $_GET['id'];
    $source = isset($_GET['source']) ? $_GET['source'] : 'patient';
    $reason = "Cancelled by patient";
    $full_reason = $reason;
    
    // Get appointment details before archiving
    $appointmentQuery = $database->query("
        SELECT a.*, d.docemail, d.docname, p.pemail, p.pname, pr.procedure_name
        FROM appointment a
        JOIN doctor d ON a.docid = d.docid
        JOIN patient p ON a.pid = p.pid
        LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id
        WHERE a.appoid = '$appoid'
    ");
    
    if (!$appointmentQuery || $appointmentQuery->num_rows == 0) {
        // Appointment not found
        redirect_with_context('my_booking.php', ['status' => 'cancel_error']);
        exit();
    }
    
    $appointment = $appointmentQuery->fetch_assoc();
    
    // Determine status based on source
    $status = ($source == 'patient') ? 'cancelled' : 'rejected';
    
    // Determine if appointment_archive has branch_id column (schema may vary)
    $hasBranchId = false;
    $colCheck = $database->query("SHOW COLUMNS FROM appointment_archive LIKE 'branch_id'");
    if($colCheck && $colCheck->num_rows > 0){
        $hasBranchId = true;
    }
    // Build dynamic archive query
    if($hasBranchId){
        $archive_query = "INSERT INTO appointment_archive 
                (archive_id, appoid, pid, docid, apponum, scheduleid, appodate, appointment_time, 
                 procedure_id, event_name, status, cancel_reason, branch_id, archived_at)
                SELECT NULL, a.appoid, a.pid, a.docid, a.apponum, a.scheduleid, a.appodate, a.appointment_time, 
                    a.procedure_id, a.event_name, ?, ?, COALESCE(d.branch_id,(SELECT branch_id FROM doctor_branches db WHERE db.docid=a.docid LIMIT 1)), NOW()
                FROM appointment a
                LEFT JOIN doctor d ON a.docid = d.docid
                WHERE a.appoid = ?";
        $stmt = $database->prepare($archive_query);
        if(!$stmt){
            error_log('Archive prepare failed (GET with branch_id): '.$database->error);
            redirect_with_context('my_booking.php', ['status' => 'cancel_error']);
            exit();
        }
        $stmt->bind_param("ssi", $status, $full_reason, $appoid);
        $stmt->execute();
    }else{
        $archive_query = "INSERT INTO appointment_archive 
                (archive_id, appoid, pid, docid, apponum, scheduleid, appodate, appointment_time, 
                 procedure_id, event_name, status, cancel_reason, archived_at)
                SELECT NULL, a.appoid, a.pid, a.docid, a.apponum, a.scheduleid, a.appodate, a.appointment_time, 
                    a.procedure_id, a.event_name, ?, ?, NOW()
                FROM appointment a
                WHERE a.appoid = ?";
        $stmt = $database->prepare($archive_query);
        if(!$stmt){
            error_log('Archive prepare failed (GET no branch_id): '.$database->error);
            redirect_with_context('my_booking.php', ['status' => 'cancel_error']);
            exit();
        }
        $stmt->bind_param("ssi", $status, $full_reason, $appoid);
        $stmt->execute();
    }
    
    // Then delete from appointments
    $delete_query = "DELETE FROM appointment WHERE appoid = ?";
    $stmt = $database->prepare($delete_query);
    $stmt->bind_param("i", $appoid);
    $result = $stmt->execute();
    
    if($result) {
        // Determine if this is a booking or an appointment based on the status field
        $recordType = (isset($appointment['status']) && $appointment['status'] == 'booking') ? 'booking' : 'appointment';
        
        // Create notification for patient
        $notificationTitle = ucfirst($recordType) . " " . ucfirst($status);
        $procedureText = !empty($appointment['procedure_name']) ? $appointment['procedure_name'] : 'For Clinic Assessment';
        $notificationMessage = "Your " . $recordType . " for " . $procedureText . " on " . 
                      date('M j, Y', strtotime($appointment['appodate'])) . " at " . 
                      date('g:i A', strtotime($appointment['appointment_time'])) . " has been $status.";
        if (!empty($full_reason)) {
            $notificationMessage .= " Reason: " . $full_reason;
        }
        
        $notificationStmt = $database->prepare("INSERT INTO notifications 
                                              (user_id, user_type, title, message, related_id, related_type) 
                                              VALUES (?, 'p', ?, ?, ?, ?)");
        $notificationStmt->bind_param("issis", $appointment['pid'], $notificationTitle, $notificationMessage, $appoid, $recordType);
        $notificationStmt->execute();
        $notificationStmt->close();
        
        // Create notification for dentist
        $dentistNotificationTitle = ucfirst($recordType) . " " . ucfirst($status);
        $dentistProcedureText = !empty($appointment['procedure_name']) ? $appointment['procedure_name'] : 'For Clinic Assessment';
        $dentistNotificationMessage = ucfirst($recordType) . " with " . $appointment['pname'] . " for " . 
                         $dentistProcedureText . " on " . 
                         date('M j, Y', strtotime($appointment['appodate'])) . " at " . 
                         date('g:i A', strtotime($appointment['appointment_time'])) . " has been $status.";
        if (!empty($full_reason)) {
            $dentistNotificationMessage .= " Reason: " . $full_reason;
        }
        
        $dentistNotificationStmt = $database->prepare("INSERT INTO notifications 
                                                     (user_id, user_type, title, message, related_id, related_type) 
                                                     VALUES (?, 'd', ?, ?, ?, ?)");
        $dentistNotificationStmt->bind_param("issis", $appointment['docid'], $dentistNotificationTitle, $dentistNotificationMessage, $appoid, $recordType);
        $dentistNotificationStmt->execute();
        $dentistNotificationStmt->close();
        
        // Send email notifications
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
            
            // Recipients - send to dentist
            $mail->setFrom('grycenmagahis@gmail.com', 'I Heart Dentist Dental Clinic');
            $mail->addAddress($appointment['docemail'], $appointment['docname']);
            $mail->addCC('grycenmagahis@gmail.com'); // CC to admin
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = ucfirst($recordType) . ' ' . ucfirst($status) . ' Notification';
            $mail->Body = "
                <h3>" . ucfirst($recordType) . " " . ucfirst($status) . " Notification</h3>
                <p>A " . $recordType . " has been $status by the patient.</p>
                <p><strong>Patient Name:</strong> {$appointment['pname']}</p>
                <p><strong>". ucfirst($recordType) ." Date:</strong> " . date('F j, Y', strtotime($appointment['appodate'])) . "</p>
                <p><strong>". ucfirst($recordType) ." Time:</strong> " . date('g:i A', strtotime($appointment['appointment_time'])) . "</p>
                <p><strong>Procedure:</strong> {$procedureText}</p>
                <p><strong>Reason:</strong> $full_reason</p>
                <p>Please check your schedule in I Heart Dentist Dental Clinic for updates.</p>
            ";
            
            $mail->send();
            
            // Send confirmation to patient
            $mail->clearAddresses();
            $mail->addAddress($appointment['pemail'], $appointment['pname']);
            $mail->Subject = 'Your ' . ucfirst($recordType) . ' ' . ucfirst($status) . ' Confirmation';
            $mail->Body = "
                <h3>" . ucfirst($recordType) . " " . ucfirst($status) . " Confirmation</h3>
                <p>Your " . $recordType . " has been successfully $status.</p>
                <p><strong>". ucfirst($recordType) ." Date:</strong> " . date('F j, Y', strtotime($appointment['appodate'])) . "</p>
                <p><strong>". ucfirst($recordType) ." Time:</strong> " . date('g:i A', strtotime($appointment['appointment_time'])) . "</p>
                <p><strong>Procedure:</strong> {$procedureText}</p>
                <p><strong>Reason:</strong> $full_reason</p>
                <p>If this was a mistake or you'd like to reschedule, please contact our office.</p>
            ";
            
            $mail->send();
            
            // Redirect with success message (preserve context when possible)
            redirect_with_context('my_booking.php', ['status' => 'cancel_success']);
            exit();
        } catch (Exception $e) {
            // Email failed but appointment was still processed
            redirect_with_context('my_booking.php', ['status' => 'cancel_success_no_email']);
            exit();
        }
    } else {
        redirect_with_context('my_booking.php', ['status' => 'cancel_error']);
        exit();
    }
} 
else if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Original POST processing code remains unchanged
    $appoid = $_POST['id'];
    $source = $_POST['source'];
    $reason = $_POST['cancel_reason'];
    $other_reason = isset($_POST['other_reason']) ? $_POST['other_reason'] : '';
    $full_reason = ($reason == 'Other') ? "Other: " . $other_reason : $reason;
    
    // First get appointment details before archiving
    $appointmentQuery = $database->query("
        SELECT a.*, d.docemail, d.docname, p.pemail, p.pname, pr.procedure_name
        FROM appointment a
        JOIN doctor d ON a.docid = d.docid
        JOIN patient p ON a.pid = p.pid
        LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id
        WHERE a.appoid = '$appoid'
    ");
    if (!$appointmentQuery || $appointmentQuery->num_rows == 0) {
        // Appointment not found or missing (prevent null offsets)
        redirect_with_context('my_appointment.php', ['status' => 'cancel_error']);
        exit();
    }
    $appointment = $appointmentQuery->fetch_assoc();
    
    // Determine status based on source
    $status = ($source == 'patient') ? 'cancelled' : 'rejected';
    
    // Archive with dynamic branch_id presence check
    $hasBranchId = false;
    $colCheck = $database->query("SHOW COLUMNS FROM appointment_archive LIKE 'branch_id'");
    if($colCheck && $colCheck->num_rows > 0){
        $hasBranchId = true;
    }
    if($hasBranchId){
        $archive_query = "INSERT INTO appointment_archive 
                (archive_id, appoid, pid, docid, apponum, scheduleid, appodate, appointment_time, 
                 procedure_id, event_name, status, cancel_reason, branch_id, archived_at)
                SELECT NULL, a.appoid, a.pid, a.docid, a.apponum, a.scheduleid, a.appodate, a.appointment_time, 
                    a.procedure_id, a.event_name, ?, ?, COALESCE(d.branch_id,(SELECT branch_id FROM doctor_branches db WHERE db.docid=a.docid LIMIT 1)), NOW()
                FROM appointment a
                LEFT JOIN doctor d ON a.docid = d.docid
                WHERE a.appoid = ?";
        $stmt = $database->prepare($archive_query);
        if(!$stmt){
            error_log('Archive prepare failed (POST with branch_id): '.$database->error);
            redirect_with_context('my_appointment.php', ['status' => 'cancel_error']);
            exit();
        }
        $stmt->bind_param("ssi", $status, $full_reason, $appoid);
        $stmt->execute();
    }else{
        $archive_query = "INSERT INTO appointment_archive 
                (archive_id, appoid, pid, docid, apponum, scheduleid, appodate, appointment_time, 
                 procedure_id, event_name, status, cancel_reason, archived_at)
                SELECT NULL, appoid, pid, docid, apponum, scheduleid, appodate, appointment_time, 
                    procedure_id, event_name, ?, ?, NOW()
                FROM appointment 
                WHERE appoid = ?";
        $stmt = $database->prepare($archive_query);
        if(!$stmt){
            error_log('Archive prepare failed (POST no branch_id): '.$database->error);
            redirect_with_context('my_appointment.php', ['status' => 'cancel_error']);
            exit();
        }
        $stmt->bind_param("ssi", $status, $full_reason, $appoid);
        $stmt->execute();
    }
    
    // Then delete from appointments
    $delete_query = "DELETE FROM appointment WHERE appoid = ?";
    $stmt = $database->prepare($delete_query);
    $stmt->bind_param("i", $appoid);
    $result = $stmt->execute();
    
    if($result) {
        // Determine if this is a booking or an appointment based on the status field
        $recordType = (isset($appointment['status']) && $appointment['status'] == 'booking') ? 'booking' : 'appointment';
        
        // Create notification for patient
        $notificationTitle = ucfirst($recordType) . " " . ucfirst($status);
        $procedureText = !empty($appointment['procedure_name']) ? $appointment['procedure_name'] : 'For Clinic Assessment';
        $notificationMessage = "Your " . $recordType . " for " . $procedureText . " on " . 
                      date('M j, Y', strtotime($appointment['appodate'])) . " at " . 
                      date('g:i A', strtotime($appointment['appointment_time'])) . " has been $status.";
        if (!empty($full_reason)) {
            $notificationMessage .= " Reason: " . $full_reason;
        }
        
        $notificationStmt = $database->prepare("INSERT INTO notifications 
                                              (user_id, user_type, title, message, related_id, related_type) 
                                              VALUES (?, 'p', ?, ?, ?, ?)");
        $notificationStmt->bind_param("issis", $appointment['pid'], $notificationTitle, $notificationMessage, $appoid, $recordType);
        $notificationStmt->execute();
        $notificationStmt->close();
        
        // Create notification for dentist
        $dentistNotificationTitle = ucfirst($recordType) . " " . ucfirst($status);
        $dentistProcedureText = !empty($appointment['procedure_name']) ? $appointment['procedure_name'] : 'For Clinic Assessment';
        $dentistNotificationMessage = ucfirst($recordType) . " with " . $appointment['pname'] . " for " . 
                         $dentistProcedureText . " on " . 
                         date('M j, Y', strtotime($appointment['appodate'])) . " at " . 
                         date('g:i A', strtotime($appointment['appointment_time'])) . " has been $status.";
        if (!empty($full_reason)) {
            $dentistNotificationMessage .= " Reason: " . $full_reason;
        }
        
        $dentistNotificationStmt = $database->prepare("INSERT INTO notifications 
                                                     (user_id, user_type, title, message, related_id, related_type) 
                                                     VALUES (?, 'd', ?, ?, ?, ?)");
        $dentistNotificationStmt->bind_param("issis", $appointment['docid'], $dentistNotificationTitle, $dentistNotificationMessage, $appoid, $recordType);
        $dentistNotificationStmt->execute();
        $dentistNotificationStmt->close();
        
        // Send email notifications
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
            
            // Recipients - send to dentist
            $mail->setFrom('grycenmagahis@gmail.com', 'I Heart Dentist Dental Clinic');
            $mail->addAddress($appointment['docemail'], $appointment['docname']);
            $mail->addCC('grycenmagahis@gmail.com'); // CC to admin
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = ucfirst($recordType) . ' ' . ucfirst($status) . ' Notification';
            $mail->Body = "
                <h3>" . ucfirst($recordType) . " " . ucfirst($status) . " Notification</h3>
                <p>A " . $recordType . " has been $status by the patient.</p>
                <p><strong>Patient Name:</strong> {$appointment['pname']}</p>
                <p><strong>". ucfirst($recordType) ." Date:</strong> " . date('F j, Y', strtotime($appointment['appodate'])) . "</p>
                <p><strong>". ucfirst($recordType) ." Time:</strong> " . date('g:i A', strtotime($appointment['appointment_time'])) . "</p>
                <p><strong>Procedure:</strong> {$procedureText}</p>
                <p><strong>Reason:</strong> $full_reason</p>
                <p>Please check your schedule in I Heart Dentist Dental Clinic for updates.</p>
            ";
            
            $mail->send();
            
            // Send confirmation to patient
            $mail->clearAddresses();
            $mail->addAddress($appointment['pemail'], $appointment['pname']);
            $mail->Subject = 'Your ' . ucfirst($recordType) . ' ' . ucfirst($status) . ' Confirmation';
            $mail->Body = "
                <h3>" . ucfirst($recordType) . " " . ucfirst($status) . " Confirmation</h3>
                <p>Your " . $recordType . " has been successfully $status.</p>
                <p><strong>". ucfirst($recordType) ." Date:</strong> " . date('F j, Y', strtotime($appointment['appodate'])) . "</p>
                <p><strong>". ucfirst($recordType) ." Time:</strong> " . date('g:i A', strtotime($appointment['appointment_time'])) . "</p>
                <p><strong>Procedure:</strong> {$procedureText}</p>
                <p><strong>Reason:</strong> $full_reason</p>
                <p>If this was a mistake or you'd like to reschedule, please contact our office.</p>
            ";
            
            $mail->send();
            
            // Redirect with success message (preserve context when possible)
            if($source == 'patient') {
                redirect_with_context('my_appointment.php', ['status' => 'cancel_success']);
            } else {
                redirect_with_context('my_appointment.php', ['status' => 'reject_success']);
            }
            exit();
        } catch (Exception $e) {
            // Email failed but appointment was still processed
            if($source == 'patient') {
                redirect_with_context('my_appointment.php', ['status' => 'cancel_success_no_email']);
            } else {
                redirect_with_context('my_appointment.php', ['status' => 'reject_success_no_email']);
            }
            exit();
        }
    } else {
        if($source == 'patient') {
            redirect_with_context('my_appointment.php', ['status' => 'cancel_error']);
        } else {
            redirect_with_context('my_appointment.php', ['status' => 'reject_error']);
        }
        exit();
    }
} else {
    redirect_with_context('dashboard.php');
    exit();
}
?>