<?php
// Shared mail helper for sending appointment confirmation emails
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mailConfig = [];
try {
    $mailConfig = require __DIR__ . '/mail_config.php';
} catch (Exception $e) {
    error_log('Could not load mail configuration: ' . $e->getMessage());
}

function sendConfirmationEmail($patientEmail, $patientName, $appointmentDate, $appointmentTime, $dentistName, $procedureName) {
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

        $mail->SMTPOptions = $mailConfig['smtp_options'] ?? [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom($mailConfig['from_email'] ?? 'no-reply@example.com', $mailConfig['from_name'] ?? 'IHeartDentistDC');
        if (!empty($patientEmail)) {
            $mail->addAddress($patientEmail, $patientName);
        }

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

function sendCancellationEmail($patientEmail, $patientName, $appointmentDate, $appointmentTime, $dentistName, $procedureName, $cancelledBy = '') {
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

        $mail->SMTPOptions = $mailConfig['smtp_options'] ?? [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom($mailConfig['from_email'] ?? 'no-reply@example.com', $mailConfig['from_name'] ?? 'IHeartDentistDC');
        if (!empty($patientEmail)) {
            $mail->addAddress($patientEmail, $patientName);
        }

        $mail->isHTML(true);
        $mail->Subject = 'Appointment Cancelled';
        $byText = $cancelledBy ? " by " . htmlspecialchars($cancelledBy) : '';
        $mail->Body = "Dear $patientName,<br><br>
                    We regret to inform you that your appointment with <strong>$dentistName</strong> for <strong>$procedureName</strong> on <strong>$appointmentDate</strong> at <strong>$appointmentTime</strong> has been cancelled$byText.<br><br>
                    If you need to reschedule or have questions, please contact the clinic.<br><br>
                    Sincerely,<br>
                    I Heart Dentist Dental Clinic";

        $mail->send();
        return ['ok' => true];
    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        error_log("Failed to send cancellation email: " . $err);
        return ['ok' => false, 'error' => $err];
    }
}

?>

