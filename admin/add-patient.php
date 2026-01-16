<?php
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" or $_SESSION['usertype'] != 'a') {
        header("location: ../login.php");
    }
} else {
    header("location: ../login.php");
}

include("../connection.php");
require_once __DIR__ . '/../inc/redirect_helper.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = isset($_POST['name']) ? $database->real_escape_string($_POST['name']) : '';
    $email = isset($_POST['email']) ? $database->real_escape_string($_POST['email']) : '';
    $tele = isset($_POST['Tele']) ? $database->real_escape_string($_POST['Tele']) : '';
    $paddress = isset($_POST['paddress']) ? $database->real_escape_string($_POST['paddress']) : '';
    $pdob = isset($_POST['pdob']) && $_POST['pdob'] !== '' ? $database->real_escape_string($_POST['pdob']) : null;
    $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? intval($_POST['branch_id']) : null;
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $cpassword = isset($_POST['cpassword']) ? $_POST['cpassword'] : '';

    // Password match
    if ($password != $cpassword) {
        redirect_with_context('patient.php', ['action' => 'add', 'error' => 2]);
        exit;
    }

    // Check existing webuser
    $check = $database->query("SELECT * FROM webuser WHERE email = '" . $database->real_escape_string($email) . "'");
    if ($check && $check->num_rows > 0) {
        redirect_with_context('patient.php', ['action' => 'add', 'error' => 1]);
        exit;
    }

    // handle photo
    $photo_new_name = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $photo = $_FILES['photo'];
        $photo_name = $photo['name'];
        $photo_tmp = $photo['tmp_name'];
        $photo_ext = pathinfo($photo_name, PATHINFO_EXTENSION);
        $photo_new_name = uniqid('patient_', true) . '.' . $photo_ext;
        $upload_dir = 'uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        if (!move_uploaded_file($photo_tmp, $upload_dir . $photo_new_name)) {
            redirect_with_context('patient.php', ['action' => 'add', 'error' => 3]);
            exit;
        }
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert patient and webuser in transaction
    $database->begin_transaction();
    try {
        // include paddress and mark admin-created patients as verified (is_verified = 1)
        if ($branch_id !== null) {
            if ($photo_new_name !== null) {
                $sql = "INSERT INTO patient (pname, pemail, ppassword, paddress, ptel, pdob, photo, status, is_verified, branch_id) VALUES ('" . $name . "', '" . $email . "', '" . $hashed_password . "', '" . $paddress . "', '" . $tele . "', '" . ($pdob ?? '') . "', '" . $photo_new_name . "', 'active', 1, $branch_id)";
            } else {
                $sql = "INSERT INTO patient (pname, pemail, ppassword, paddress, ptel, pdob, status, is_verified, branch_id) VALUES ('" . $name . "', '" . $email . "', '" . $hashed_password . "', '" . $paddress . "', '" . $tele . "', '" . ($pdob ?? '') . "', 'active', 1, $branch_id)";
            }
        } else {
            if ($photo_new_name !== null) {
                $sql = "INSERT INTO patient (pname, pemail, ppassword, paddress, ptel, pdob, photo, status, is_verified) VALUES ('" . $name . "', '" . $email . "', '" . $hashed_password . "', '" . $paddress . "', '" . $tele . "', '" . ($pdob ?? '') . "', '" . $photo_new_name . "', 'active', 1)";
            } else {
                $sql = "INSERT INTO patient (pname, pemail, ppassword, paddress, ptel, pdob, status, is_verified) VALUES ('" . $name . "', '" . $email . "', '" . $hashed_password . "', '" . $paddress . "', '" . $tele . "', '" . ($pdob ?? '') . "', 'active', 1)";
            }
        }

        $database->query($sql);
        $new_pid = $database->insert_id;

        // Insert mapping to patient_branches if requested
        if ($branch_id !== null) {
            $database->query("INSERT IGNORE INTO patient_branches (pid, branch_id) VALUES ('" . $database->real_escape_string($new_pid) . "', '" . $database->real_escape_string($branch_id) . "')");
        }

        // Insert into webuser
        $database->query("INSERT INTO webuser (email, usertype) VALUES ('" . $database->real_escape_string($email) . "', 'p')");

        $database->commit();
        redirect_with_context('patient.php', ['action' => 'add', 'error' => 4]);
        exit;
    } catch (Exception $e) {
        $database->rollback();
        redirect_with_context('patient.php', ['action' => 'add', 'error' => 3]);
        exit;
    }
} else {
    redirect_with_context('patient.php', ['action' => 'add', 'error' => 3]);
}
?>