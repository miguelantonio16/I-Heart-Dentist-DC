<?php
require 'database_connection.php';
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'msg' => 'Invalid request method.']);
    exit;
}

$date = $_POST['date'] ?? null;
$docid = isset($_POST['docid']) && $_POST['docid'] !== '' ? intval($_POST['docid']) : null;

if (!$date) {
    echo json_encode(['status' => false, 'msg' => 'Invalid date provided.']);
    exit;
}

if (!$con) {
    error_log('[delete_non_working_day] DB connection missing');
    echo json_encode(['status' => false, 'msg' => 'Database connection failed.']);
    exit;
}

if ($docid) {
    $create_sql = "CREATE TABLE IF NOT EXISTS doctor_non_working_days (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docid INT NOT NULL,
        date DATE NOT NULL,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!mysqli_query($con, $create_sql)) {
        error_log('[delete_non_working_day] failed creating doctor_non_working_days: ' . mysqli_error($con));
    }

    $stmt = $con->prepare("DELETE FROM doctor_non_working_days WHERE date = ? AND docid = ?");
    if (!$stmt) {
        error_log('[delete_non_working_day] prepare failed (doctor): ' . $con->error);
        echo json_encode(['status' => false, 'msg' => 'Prepare failed.']);
        exit;
    }
    $stmt->bind_param("si", $date, $docid);
} else {
    $create_global_sql = "CREATE TABLE IF NOT EXISTS non_working_days (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!mysqli_query($con, $create_global_sql)) {
        error_log('[delete_non_working_day] failed creating non_working_days: ' . mysqli_error($con));
    }

    $stmt = $con->prepare("DELETE FROM non_working_days WHERE date = ?");
    if (!$stmt) {
        error_log('[delete_non_working_day] prepare failed (global): ' . $con->error);
        echo json_encode(['status' => false, 'msg' => 'Prepare failed.']);
        exit;
    }
    $stmt->bind_param("s", $date);
}

if ($stmt->execute()) {
    echo json_encode(['status' => true, 'msg' => 'No Service day removed successfully.']);
} else {
    error_log('[delete_non_working_day] execute failed: ' . ($stmt->error ?? mysqli_error($con)));
    echo json_encode(['status' => false, 'msg' => 'Error removing date from database.']);
}
?>