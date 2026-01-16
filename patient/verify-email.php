<?php
session_start();
include("../connection.php");

header('Content-Type: text/html; charset=utf-8');

function fail_and_redirect($reason) {
    error_log('[VERIFY EMAIL] Failure: ' . $reason);
    header('Location: verification-failed.php');
    exit();
}

if (!isset($_GET['token'])) {
    fail_and_redirect('Missing token');
}

$token = $_GET['token'];
$token = trim($token);

// Basic validation: hex string of expected length (64 chars from bin2hex(random_bytes(32)))
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
    fail_and_redirect('Invalid token format');
}

// Prepared statement to avoid injection
$stmt = $database->prepare('SELECT pemail, pname, is_verified FROM patient WHERE verification_token = ? LIMIT 1');
if (!$stmt) {
    fail_and_redirect('Prepare failed: ' . $database->error);
}
$stmt->bind_param('s', $token);
if (!$stmt->execute()) {
    fail_and_redirect('Execute failed: ' . $stmt->error);
}
$res = $stmt->get_result();
if ($res->num_rows !== 1) {
    fail_and_redirect('Token not found');
}
$row = $res->fetch_assoc();
$email = $row['pemail'];

// If already verified, just redirect success
if (!empty($row['is_verified'])) {
    $_SESSION['user'] = $email;
    $_SESSION['usertype'] = 'p';
    $_SESSION['username'] = $row['pname'];
    header('Location: verification-success.php');
    exit();
}

$update = $database->prepare('UPDATE patient SET is_verified = 1, verification_token = NULL WHERE pemail = ? LIMIT 1');
if (!$update) {
    fail_and_redirect('Update prepare failed: ' . $database->error);
}
$update->bind_param('s', $email);
if (!$update->execute()) {
    fail_and_redirect('Update execute failed: ' . $update->error);
}

$_SESSION['user'] = $email;
$_SESSION['usertype'] = 'p';
$_SESSION['username'] = $row['pname'];

header('Location: verification-success.php');
exit();
?>
