<?php
require 'database_connection.php';
require __DIR__ . '/../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mailConfig = [];
try {
    $mailConfig = require __DIR__ . '/../../inc/mail_config.php';
} catch (Exception $e) {
    error_log('Could not load mail configuration: ' . $e->getMessage());
}

$to = isset($_GET['to']) ? $_GET['to'] : null;
if (!$to) {
    echo json_encode(['status' => false, 'msg' => 'Provide ?to=you@example.com']);
    exit;
}

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
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    $mail->Subject = 'Test Email from IHeartDentistDC';
    $mail->Body = '<p>This is a test email from IHeartDentistDC at ' . date('c') . '</p>';
    $mail->send();
    echo json_encode(['status' => true, 'msg' => 'Test email sent to ' . $to]);
} catch (Exception $e) {
    $err = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
    error_log('Test mail error: ' . $err);
    echo json_encode(['status' => false, 'msg' => 'Failed to send test email', 'mail_error' => $err]);
}

