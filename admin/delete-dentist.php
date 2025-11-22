<?php
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'a') {
        header("location: ../admin/login.php");
    }
} else {
    header("location: ../admin/login.php");
}

// Only accept POST for deactivation to prevent CSRF via GET.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Import database
    include("../connection.php");

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if ($id > 0 && !empty($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        // Set dentist's status to 'inactive'
        $sql = $database->query("UPDATE doctor SET status='inactive' WHERE docid=$id;");
    }

    // Redirect to dentist management page
    header("location: dentist.php");
    exit;
} else {
    // Do not allow GET deactivation anymore — redirect to management page
    header("location: dentist.php");
    exit;
}
?>
