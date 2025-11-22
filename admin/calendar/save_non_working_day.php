<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'database_connection.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date = $_POST["date"] ?? "";
    $description = $_POST["description"] ?? "";

    if (empty($date) || empty($description)) {
        echo json_encode(["status" => false, "message" => "Missing date or description"]);
        exit;
    }

    // Check if connection exists
    if (!$con) {
        echo json_encode(["status" => false, "message" => "Database connection failed"]);
        exit;
    }

    // If a dentist (docid) is provided, store the no-service day for that dentist only.
    $docid = isset($_POST['docid']) && $_POST['docid'] !== '' ? intval($_POST['docid']) : null;

    // Ensure doctor_non_working_days table exists (safe-create)
    $create_sql = "CREATE TABLE IF NOT EXISTS doctor_non_working_days (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docid INT NOT NULL,
        date DATE NOT NULL,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($con, $create_sql);

    if ($docid) {
        $sql = "INSERT INTO doctor_non_working_days (docid, date, description) VALUES (?, ?, ?)";
        $stmt = $con->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iss", $docid, $date, $description);
            if ($stmt->execute()) {
                echo json_encode(["status" => true, "message" => "Saved for the selected dentist"]);
            } else {
                echo json_encode(["status" => false, "message" => "Database insert failed: " . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(["status" => false, "message" => "SQL prepare failed: " . $con->error]);
        }
    } else {
        // Fallback: save a global non-working day as before
        $sql = "INSERT INTO non_working_days (date, description) VALUES (?, ?)";
        $stmt = $con->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ss", $date, $description);
            if ($stmt->execute()) {
                echo json_encode(["status" => true, "message" => "Saved globally"]);
            } else {
                echo json_encode(["status" => false, "message" => "Database insert failed: " . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(["status" => false, "message" => "SQL prepare failed: " . $con->error]);
        }
    }
} else {
    echo json_encode(["status" => false, "message" => "Invalid request"]);
}
?>
