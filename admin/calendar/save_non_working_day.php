<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'database_connection.php';

session_start();
header('Content-Type: application/json');

// Basic admin session check (same guard as display_event.php)
if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => false, 'message' => 'Not authenticated']);
    exit;
}

// Only accept POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["status" => false, "message" => "Invalid request method"]);
    exit;
}

$date = $_POST["date"] ?? "";
$description = trim($_POST["description"] ?? "");
$docid = isset($_POST['docid']) && $_POST['docid'] !== '' ? intval($_POST['docid']) : null;

// Validate inputs
if (empty($date) || empty($description)) {
    echo json_encode(["status" => false, "message" => "Missing date or description"]);
    exit;
}

$dobj = DateTime::createFromFormat('Y-m-d', $date);
if (!$dobj || $dobj->format('Y-m-d') !== $date) {
    echo json_encode(["status" => false, "message" => "Invalid date format (expected YYYY-MM-DD)"]);
    exit;
}

if (!$con) {
    error_log('[save_non_working_day] DB connection missing');
    echo json_encode(["status" => false, "message" => "Database connection failed"]);
    exit;
}

// Ensure doctor_non_working_days table exists (safe-create)
$create_sql = "CREATE TABLE IF NOT EXISTS doctor_non_working_days (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docid INT NOT NULL,
    date DATE NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if (!mysqli_query($con, $create_sql)) {
    error_log('[save_non_working_day] failed creating doctor_non_working_days: ' . mysqli_error($con));
}

try {
    if ($docid) {
        $sql = "INSERT INTO doctor_non_working_days (docid, date, description) VALUES (?, ?, ?)";
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            $err = $con->error;
            error_log('[save_non_working_day] prepare failed (doctor): ' . $err);
            echo json_encode(["status" => false, "message" => "Database prepare failed: $err"]);
            exit;
        }
        $stmt->bind_param("iss", $docid, $date, $description);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            error_log('[save_non_working_day] execute failed (doctor): ' . $err);
            echo json_encode(["status" => false, "message" => "Database insert failed: $err"]);
            $stmt->close();
            exit;
        }
        $stmt->close();
        echo json_encode(["status" => true, "message" => "Saved for the selected dentist"]);
        exit;
    }

    // Fallback: save a global non-working day as before
    $create_global_sql = "CREATE TABLE IF NOT EXISTS non_working_days (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if (!mysqli_query($con, $create_global_sql)) {
        error_log('[save_non_working_day] failed creating non_working_days: ' . mysqli_error($con));
    }

    $sql = "INSERT INTO non_working_days (date, description) VALUES (?, ?)";
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        $err = $con->error;
        error_log('[save_non_working_day] prepare failed (global): ' . $err);
        echo json_encode(["status" => false, "message" => "Database prepare failed: $err"]);
        exit;
    }
    $stmt->bind_param("ss", $date, $description);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        error_log('[save_non_working_day] execute failed (global): ' . $err);
        echo json_encode(["status" => false, "message" => "Database insert failed: $err"]);
        $stmt->close();
        exit;
    }
    $stmt->close();
    echo json_encode(["status" => true, "message" => "Saved globally"]);
    exit;
} catch (Exception $e) {
    error_log('[save_non_working_day] exception: ' . $e->getMessage());
    echo json_encode(["status" => false, "message" => "Server error"]);
    exit;
}
?>
