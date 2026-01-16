<?php
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'a') {
        header("location: ../login.php");
        exit;
    }
} else {
    header("location: ../login.php");
    exit;
}

// Accept POST or GET for backwards compatibility. Prefer POST from UI.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include("../connection.php");
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if ($id > 0 && !empty($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        $database->query("UPDATE doctor SET status='active' WHERE docid=$id");
    } else {
        // Invalid token or input — ignore or log
    }
} elseif ($_GET) {
    // Backwards-compatible GET activation (no CSRF). Prefer POST.
    include("../connection.php");
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id > 0) {
        $database->query("UPDATE doctor SET status='active' WHERE docid=$id");
    }
}

require_once __DIR__ . '/../inc/redirect_helper.php';
redirect_with_context('dentist.php');
exit;
?>