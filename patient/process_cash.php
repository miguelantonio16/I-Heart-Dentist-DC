<?php
session_start();
include("../connection.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['appoid'])) {
    $appoid = $_POST['appoid'];
    
    // Update status to 'pending_cash' so Admin sees it
    $stmt = $database->prepare("UPDATE appointment SET payment_status = 'pending_cash', payment_method = 'cash' WHERE appoid = ?");
    $stmt->bind_param("i", $appoid);
    
    if ($stmt->execute()) {
        // Determine redirect URL (prefer explicit params, otherwise referrer)
        require_once __DIR__ . '/../inc/redirect_helper.php';
        $returnParams = [];
        if (isset($_POST['page'])) { $returnParams['page'] = (int)$_POST['page']; }
        if (isset($_POST['search']) && $_POST['search'] !== '') { $returnParams['search'] = $_POST['search']; }
        if (isset($_POST['sort']) && $_POST['sort'] !== '') { $returnParams['sort'] = $_POST['sort']; }
        $redirectUrl = get_redirect_url('my_appointment.php', $returnParams, true);

        echo "<script>
            alert('Cash payment selected. Please pay at the clinic counter to complete your appointment.');
            window.location.href = '" . addslashes($redirectUrl) . "';
        </script>";
    } else {
        echo "Error updating record: " . $database->error;
    }
} else {
    require_once __DIR__ . '/../inc/redirect_helper.php';
    redirect_with_context('my_appointment.php');
}
?>