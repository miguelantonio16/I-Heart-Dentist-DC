<?php
// send_reminders.php
// Run this script via scheduled task every hour (or daily) to send appointment reminders.

require __DIR__ . '/database_connection.php';
require __DIR__ . '/../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mailConfig = [];
try {
    $mailConfig = require __DIR__ . '/../../inc/mail_config.php';
} catch (Exception $e) {
    error_log('Could not load mail configuration: ' . $e->getMessage());
}

// Use server timezone consistent with app
date_default_timezone_set('Asia/Singapore');

// Create reminder log table if not exists to avoid duplicate sends
$createTableSql = "CREATE TABLE IF NOT EXISTS appointment_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appoid INT NOT NULL,
    reminder_type VARCHAR(32) NOT NULL,
    sent_at DATETIME NOT NULL,
    UNIQUE KEY unique_reminder (appoid, reminder_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$con->query($createTableSql);

$now_ts = time();
$now_day = date('Y-m-d', $now_ts);

// Fetch upcoming appointments in the next 26 hours (cover both 24h and 2h windows)
$stmt = $con->prepare("SELECT a.appoid, a.pid, a.docid, a.appodate, a.appointment_time, p.pname, p.pemail, d.docname, d.docemail, pr.procedure_name
    FROM appointment a
    JOIN patient p ON a.pid = p.pid
    JOIN doctor d ON a.docid = d.docid
    LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id
    WHERE a.status = 'appointment' AND CONCAT(a.appodate, ' ', a.appointment_time) BETWEEN ? AND ?");
$startWindow = date('Y-m-d H:i:s', $now_ts);
$endWindow = date('Y-m-d H:i:s', $now_ts + 60*60*26); // next 26 hours
$stmt->bind_param('ss', $startWindow, $endWindow);
$stmt->execute();
$res = $stmt->get_result();

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $appt_datetime_str = $row['appodate'] . ' ' . $row['appointment_time'];
        $appt_ts = strtotime($appt_datetime_str);
        if (!$appt_ts) continue;

        $diff_seconds = $appt_ts - $now_ts;

        // Determine reminder types to send
        $toSend = [];

        // 24-hour reminder: send when appointment is ~24 hours away (within +/-30 minutes)
        if ($diff_seconds >= (23.5*3600) && $diff_seconds <= (24.5*3600)) {
            $toSend[] = '24h';
        }

        // 2-hour reminder: send when appointment is ~2 hours away (within +/-10 minutes)
        if ($diff_seconds >= (1.833*3600) && $diff_seconds <= (2.166*3600)) {
            $toSend[] = '2h';
        }

        // Same-day sweep: send a reminder once for appointments later today
        // Useful when running manually or when windows were missed.
        $appt_day = date('Y-m-d', $appt_ts);
        if ($appt_day === $now_day && $diff_seconds > 0) {
            $toSend[] = 'day';
        }

        if (empty($toSend)) continue;

        foreach ($toSend as $rtype) {
            // Check if we've already sent this reminder for this appointment
            $chk = $con->prepare("SELECT 1 FROM appointment_reminders WHERE appoid = ? AND reminder_type = ? LIMIT 1");
            $chk->bind_param('is', $row['appoid'], $rtype);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) { $chk->close(); continue; }
            $chk->close();

            // Prepare message variant
            $appt_dt = date('F j, Y', $appt_ts);
            $appt_time = date('g:i A', $appt_ts);
            $title = (
                $rtype === '24h' ? 'Appointment Reminder — 24 hours' : (
                $rtype === '2h' ? 'Appointment Reminder — 2 hours' : 'Appointment Reminder — Today')
            );
            $procName = !empty($row['procedure_name']) ? $row['procedure_name'] : 'your appointment';
            $message = "Reminder: {$procName} with Dr. {$row['docname']} is on {$appt_dt} at {$appt_time}. (This is your {$title} reminder)";

            // Insert notification for the patient
            $ins = $con->prepare("INSERT INTO notifications (user_id, user_type, title, message, related_id, related_type, created_at, is_read)
                VALUES (?, 'p', ?, ?, ?, 'appointment', NOW(), 0)");
            $ins->bind_param('issi', $row['pid'], $title, $message, $row['appoid']);
            $ins->execute();

            // Send email reminder using centralized mail config
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
                if (!empty($row['pemail'])) $mail->addAddress($row['pemail'], $row['pname']);
                if (!empty($row['docemail'])) $mail->addCC($row['docemail'], $row['docname']);

                $mail->isHTML(true);
                $mail->Subject = $title;
                $mail->Body = "\n                    <h3>{$title}</h3>\n                    <p>Dear {$row['pname']},</p>\n                    <p>This is a reminder for your upcoming appointment:</p>\n                    <ul>\n                        " . (!empty($row['procedure_name']) ? "<li><strong>Procedure:</strong> {$row['procedure_name']}</li>" : "") . "\n                        <li><strong>Dentist:</strong> Dr. {$row['docname']}</li>\n                        <li><strong>Date:</strong> {$appt_dt}</li>\n                        <li><strong>Time:</strong> {$appt_time}</li>\n                    </ul>\n                    <p>Please arrive 10 minutes early. Reply to this email or call us to reschedule.</p>\n                    <p>Thank you,<br/>I Heart Dentist Dental Clinic</p>\n                ";

                $mail->send();

                // Log sent reminder to avoid duplicates
                $log = $con->prepare("INSERT INTO appointment_reminders (appoid, reminder_type, sent_at) VALUES (?, ?, NOW())");
                $log->bind_param('is', $row['appoid'], $rtype);
                $log->execute();

                error_log('Reminder (' . $rtype . ') sent for appointment ' . $row['appoid']);
            } catch (Exception $e) {
                error_log('Reminder (' . $rtype . ') email failed for appt ' . $row['appoid'] . ': ' . $e->getMessage());
            }
        }
    }
} else {
    error_log('No upcoming appointments to remind.');
}

echo "Done\n";

