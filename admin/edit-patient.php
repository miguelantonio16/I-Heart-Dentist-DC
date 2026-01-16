<?php
date_default_timezone_set('Asia/Singapore');
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'a') {
        header("location: login.php");
        exit();
    }
} else {
    header("location: login.php");
    exit();
}

include("../connection.php");

// Helper to send JSON and exit
function send_json($arr) {
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit();
}

// Handle POST (update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $pname = isset($_POST['pname']) ? trim($_POST['pname']) : '';
    $pemail = isset($_POST['pemail']) ? trim($_POST['pemail']) : '';
    $ptel = isset($_POST['ptel']) ? trim($_POST['ptel']) : '';
       $pdob = isset($_POST['pdob']) ? trim($_POST['pdob']) : '';
    $paddress = isset($_POST['paddress']) ? trim($_POST['paddress']) : '';

    if ($id <= 0) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) send_json(['status' => false, 'msg' => 'Invalid ID']);
        header('Location: patient.php'); exit();
    }

    if ($pname === '') {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) send_json(['status' => false, 'msg' => 'Name is required']);
        header('Location: patient.php'); exit();
    }

    // Basic email validation
    if ($pemail !== '' && !filter_var($pemail, FILTER_VALIDATE_EMAIL)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) send_json(['status' => false, 'msg' => 'Invalid email']);
        header('Location: patient.php'); exit();
    }

    $sql = "UPDATE patient SET pname = ?, pemail = ?, ptel = ?, pdob = ?, paddress = ? WHERE pid = ?";
    $stmt = $database->prepare($sql);
    if (!$stmt) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) send_json(['status' => false, 'msg' => 'Prepare failed: ' . $database->error]);
        header('Location: patient.php'); exit();
    }

    $stmt->bind_param('sssssi', $pname, $pemail, $ptel, $pdob, $paddress, $id);
    $exec = $stmt->execute();

    if ($exec) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) send_json(['status' => true, 'msg' => 'Patient updated']);
            header('Location: patient.php');
        exit();
    } else {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) send_json(['status' => false, 'msg' => 'Update failed: ' . $stmt->error]);
        header('Location: patient.php');
        exit();
    }
}

// Handle GET - render form
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo '<div style="padding:18px; background:#fff; border-radius:8px; max-width:520px; margin:18px auto;">Invalid patient ID</div>';
    exit();
}

$sql = "SELECT * FROM patient WHERE pid = ? LIMIT 1";
$stmt = $database->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows == 0) {
    echo '<div style="padding:18px; background:#fff; border-radius:8px; max-width:520px; margin:18px auto;">Patient not found</div>';
    exit();
}
$row = $res->fetch_assoc();

// Render simple form that the overlay JS will inject
$pname = htmlspecialchars($row['pname']);
$pemail = htmlspecialchars($row['pemail']);
$ptel = htmlspecialchars($row['ptel']);
$pdob = isset($row['pdob']) ? htmlspecialchars($row['pdob']) : '';
$paddress = isset($row['paddress']) ? htmlspecialchars($row['paddress']) : '';

// Build HTML form
$html = <<<HTML
<div class="modal-card">
    <a href="#" class="close">&times;</a>
    <div class="modal-header">
        <h2>Edit Patient</h2>
        <p class="patient-id">Patient ID: P-{$id}</p>
    </div>
    <div class="patient-card">
        <form method="POST" action="edit-patient.php" id="edit-patient-form">
            <input type="hidden" name="id" value="{$id}">
            <div style="display:flex;gap:12px;flex-direction:column;">
                <label style="font-weight:700;">Name</label>
                <input type="text" name="pname" value="{$pname}" required class="input-text">

                <label style="font-weight:700;">Email</label>
                <input type="email" name="pemail" value="{$pemail}" class="input-text">

                <label style="font-weight:700;">Phone</label>
                <input type="text" name="ptel" value="{$ptel}" class="input-text">

                <label style="font-weight:700;">Date of Birth</label>
                <input type="date" name="pdob" value="{$pdob}" class="input-text">

                <label style="font-weight:700;">Address</label>
                <textarea name="paddress" class="input-text" rows="3">{$paddress}</textarea>

                <div style="display:flex;gap:8px;margin-top:12px;">
                    <button type="submit" class="action-btn add-btn">Save</button>
                    <a href="#" class="close-btn">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>
HTML;

// Output HTML
echo $html;
exit();
