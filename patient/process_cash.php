<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['appoid'])) {
    $appoid = $_POST['appoid'];
    
    // Update status to 'pending_cash' so Admin sees it
    $stmt = $database->prepare("UPDATE appointment SET payment_status = 'pending_cash', payment_method = 'cash' WHERE appoid = ?");
    $stmt->bind_param("i", $appoid);
    
    if ($stmt->execute()) {
        echo "<script>
            alert('Cash payment selected. Please pay at the clinic counter to complete your appointment.');
            window.location.href = 'my_appointment.php';
        </script>";
    } else {
        echo "Error updating record: " . $database->error;
    }
} else {
    header("Location: my_appointment.php");
}
?>