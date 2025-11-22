<?php
session_start();
include('connection.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./'); exit;
}

if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}

$branch_id = isset($_POST['active_branch_id']) ? (int)$_POST['active_branch_id'] : 0;
$redirect = isset($_POST['redirect']) ? $_POST['redirect'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : './');

if (empty($_SESSION['user']) || empty($_SESSION['usertype'])) {
    header('Location: ./login.php'); exit;
}

$useremail = $_SESSION['user'];
$usertype = $_SESSION['usertype'];

$allowed = false;
if ($usertype === 'p') {
    $r = $database->query("SELECT 1 FROM patient_branches WHERE pid=(SELECT pid FROM patient WHERE pemail='".$database->real_escape_string($useremail)."') AND branch_id='".$branch_id."' LIMIT 1");
    if ($r && $r->num_rows>0) $allowed = true;
} elseif ($usertype === 'd') {
    $r = $database->query("SELECT 1 FROM doctor_branches WHERE docid=(SELECT docid FROM doctor WHERE docemail='".$database->real_escape_string($useremail)."') AND branch_id='".$branch_id."' LIMIT 1");
    if ($r && $r->num_rows>0) $allowed = true;
} else {
    // admins may switch to any branch
    $r = $database->query("SELECT 1 FROM branches WHERE id='".$branch_id."' LIMIT 1");
    if ($r && $r->num_rows>0) $allowed = true;
}

if ($allowed) {
    $_SESSION['active_branch_id'] = $branch_id;
    $bres = $database->query("SELECT name FROM branches WHERE id='".$branch_id."' LIMIT 1");
    $_SESSION['active_branch_name'] = ($bres && $bres->num_rows>0) ? $bres->fetch_assoc()['name'] : null;
}

header('Location: '.$redirect);
exit;

?>
