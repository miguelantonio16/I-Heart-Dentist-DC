<?php
session_start();
date_default_timezone_set('Asia/Singapore');
include("../connection.php");

if (isset($_GET['id']) && isset($_GET['type'])) {
    $appoid = $_GET['id'];
    $type = $_GET['type'];

    if ($type == 'reservation') {
        // 1. Update status to 'booking' (Confirmed)
        $sql = "UPDATE appointment SET 
                status='booking', 
                payment_status='partial', 
                reservation_paid=1,
                payment_method='paymongo'
                WHERE appoid='$appoid'";
        
        if ($database->query($sql)) {
            // Ensure branch_id is set on the appointment. Prefer session active branch, then patient's branch_id.
            $appRes = $database->query("SELECT branch_id, pid FROM appointment WHERE appoid='" . $database->real_escape_string($appoid) . "' LIMIT 1");
            if ($appRes && $appRes->num_rows > 0) {
                $appRow = $appRes->fetch_assoc();
                if (empty($appRow['branch_id'])) {
                    $setBranch = null;
                    if (!empty($_SESSION['active_branch_id'])) {
                        $setBranch = (int)$_SESSION['active_branch_id'];
                    } else {
                        $pb = $database->query("SELECT branch_id FROM patient WHERE pid='" . $database->real_escape_string($appRow['pid']) . "' LIMIT 1");
                        if ($pb && $pb->num_rows > 0) {
                            $setBranch = (int)$pb->fetch_assoc()['branch_id'];
                        }
                    }
                    if (!empty($setBranch)) {
                        $database->query("UPDATE appointment SET branch_id='" . $database->real_escape_string($setBranch) . "' WHERE appoid='" . $database->real_escape_string($appoid) . "'");
                    }
                }
            }
            
            // --- NOTIFICATION LOGIC START ---
            
            // Fetch details needed for the notification message
            // We need to join tables to get Patient Name, Dentist ID, and Procedure Name
            $detailsQuery = $database->query("
                SELECT 
                    a.appodate, 
                    a.appointment_time, 
                    p.pid, 
                    p.pname, 
                    d.docid, 
                    pr.procedure_name 
                FROM appointment a
                INNER JOIN patient p ON a.pid = p.pid
                INNER JOIN doctor d ON a.docid = d.docid
                INNER JOIN procedures pr ON a.procedure_id = pr.procedure_id
                WHERE a.appoid = '$appoid'
            ");

            if ($detailsQuery && $detailsQuery->num_rows > 0) {
                $row = $detailsQuery->fetch_assoc();
                
                $pid = $row['pid'];
                $docid = $row['docid'];
                $patientName = $row['pname'];
                $procedureName = $row['procedure_name'];
                $appDate = date('M j, Y', strtotime($row['appodate']));
                $appTime = date('g:i A', strtotime($row['appointment_time']));

                // 1. Create notification for PATIENT
                $notificationTitle = "Booking Confirmed";
                $notificationMessage = "Your appointment for " . $procedureName . " on " . $appDate . " at " . $appTime . " has been confirmed. Reservation fee paid.";
                
                $stmt = $database->prepare("INSERT INTO notifications (user_id, user_type, title, message, related_id, related_type, created_at) VALUES (?, 'p', ?, ?, ?, 'appointment', NOW())");
                $stmt->bind_param("issi", $pid, $notificationTitle, $notificationMessage, $appoid);
                $stmt->execute();
                $stmt->close();

                // 2. Create notification for DENTIST
                $dentistTitle = "New Appointment Booking";
                $dentistMessage = "New confirmed appointment booked by " . $patientName . " for " . $procedureName . " on " . $appDate . " at " . $appTime;

                $stmt2 = $database->prepare("INSERT INTO notifications (user_id, user_type, title, message, related_id, related_type, created_at) VALUES (?, 'd', ?, ?, ?, 'appointment', NOW())");
                $stmt2->bind_param("issi", $docid, $dentistTitle, $dentistMessage, $appoid);
                $stmt2->execute();
                $stmt2->close();
            }
            // --- NOTIFICATION LOGIC END ---

            echo "<script>
                alert('Reservation Fee Paid! Your booking is now confirmed.');
                window.location.href = 'my_booking.php';
            </script>";
        } else {
            echo "Error updating record: " . $database->error;
        }
    } 
    elseif ($type == 'balance') {
        // Handle Balance Payment (Complete)
        $sql = "UPDATE appointment SET 
                status='completed', 
                payment_status='paid', 
                payment_method='paymongo'
                WHERE appoid='$appoid'";
        
        if ($database->query($sql)) {
             echo "<script>
                alert('Full Payment Successful! Appointment Completed.');
                window.location.href = 'my_appointment.php';
            </script>";
        }
    }
} else {
    header("Location: dashboard.php");
}
?>