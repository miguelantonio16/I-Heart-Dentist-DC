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
if (!isset($_SESSION["user"]) || ($_SESSION["user"] == "" || $_SESSION['usertype'] != 'a')) {
    echo json_encode(['status' => false, 'msg' => 'Unauthorized access.']);
    exit;
}


function sendConfirmationEmail($patientEmail, $patientName, $appointmentDate, $appointmentTime, $dentistName, $procedureName) {
    // ensure we use the centralized mail configuration
    global $mailConfig;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $mailConfig['host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $mailConfig['username'] ?? '';
        $mail->Password = $mailConfig['password'] ?? '';
        $mail->SMTPSecure = $mailConfig['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $mailConfig['port'] ?? 587;

        // allow local testing when TLS issues occur
        $mail->SMTPOptions = $mailConfig['smtp_options'] ?? [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // Enable detailed SMTP debug output to PHP error log (temporary for debugging)
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log('PHPMailer debug: ' . trim($str));
        };

        $mail->setFrom($mailConfig['from_email'] ?? 'no-reply@example.com', $mailConfig['from_name'] ?? 'IHeartDentistDC');
        $mail->addAddress($patientEmail, $patientName);

        $mail->isHTML(true);
        $mail->Subject = 'Appointment Confirmed';
        $mail->Body = "Dear $patientName,<br><br>
                    Your appointment has been confirmed with <strong>$dentistName</strong> for:<br><br>
                    <strong>Procedure:</strong> $procedureName<br>
                    <strong>Date:</strong> $appointmentDate<br>
                    <strong>Time:</strong> $appointmentTime<br><br>
                    Please arrive 10 minutes before your scheduled time and bring any necessary documents.<br><br>
                    You can view your appointment details in your IHeartDentistDC account.<br><br>
                    Thank you for choosing I Heart Dentist Dental Clinic.<br><br>
                    Sincerely,<br>
                    I Heart Dentist Dental Clinic";

        $mail->send();
        return ['ok' => true];
    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        error_log("Failed to send confirmation email: " . $err);
        return ['ok' => false, 'error' => $err];
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appoid'])) {
    $appoid = intval($_POST['appoid']);


    // Get booking details with patient and dentist info
    $query = $con->prepare("
        SELECT a.*, p.pname, p.pemail, d.docname, pr.procedure_name
        FROM appointment a
        JOIN patient p ON a.pid = p.pid
        JOIN doctor d ON a.docid = d.docid
        JOIN procedures pr ON a.procedure_id = pr.procedure_id
        WHERE a.appoid = ? AND a.status = 'booking'
    ");
    $query->bind_param("i", $appoid);
    $query->execute();
    $result = $query->get_result();
    $booking = $result->fetch_assoc();


    if (!$booking) {
        echo json_encode(['status' => false, 'msg' => 'Booking not found or already processed.']);
        exit;
    }


    // Update status to 'appointment'
    $updateQuery = $con->prepare("UPDATE appointment SET status = 'appointment' WHERE appoid = ?");
    $updateQuery->bind_param("i", $appoid);
   
    if ($updateQuery->execute()) {
        // Send confirmation email
        $appodate = $booking['appodate'] !== null ? date('Y-m-d', strtotime($booking['appodate'])) : null;
        $emailResult = sendConfirmationEmail(
            $booking['pemail'],
            $booking['pname'],
            $appodate,
            $booking['appointment_time'],
            $booking['docname'],
            $booking['procedure_name']
        );

        $response = [
            'status' => true,
            'msg' => "Booking confirmed successfully.",
            'email_sent' => ($emailResult['ok'] ?? false)
        ];

        if (!($emailResult['ok'] ?? false)) {
            $response['msg'] .= " (Failed to send confirmation email)";
            $response['mail_error'] = $emailResult['error'] ?? 'Unknown error';
        }

        echo json_encode($response);
    } else {
        echo json_encode(['status' => false, 'msg' => 'Failed to confirm booking.']);
    }
} else {
    echo json_encode(['status' => false, 'msg' => 'Invalid request.']);
}
?>

