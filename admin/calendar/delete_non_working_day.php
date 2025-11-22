<?php
require 'database_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = $_POST['date'];
    $docid = isset($_POST['docid']) && $_POST['docid'] !== '' ? intval($_POST['docid']) : null;

    if (!$date) {
        echo json_encode(['status' => false, 'msg' => 'Invalid date provided.']);
        exit;
    }

    // If docid provided, delete for that doctor only; otherwise delete global entry
    if ($docid) {
        // Ensure table exists
        $create_sql = "CREATE TABLE IF NOT EXISTS doctor_non_working_days (
            id INT AUTO_INCREMENT PRIMARY KEY,
            docid INT NOT NULL,
            date DATE NOT NULL,
            description VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        mysqli_query($con, $create_sql);

        $stmt = $con->prepare("DELETE FROM doctor_non_working_days WHERE date = ? AND docid = ?");
        $stmt->bind_param("si", $date, $docid);
    } else {
        $stmt = $con->prepare("DELETE FROM non_working_days WHERE date = ?");
        $stmt->bind_param("s", $date);
    }

    if ($stmt->execute()) {
        echo json_encode(['status' => true, 'msg' => 'No Service day removed successfully.']);
    } else {
        echo json_encode(['status' => false, 'msg' => 'Error removing date from database.']);
    }
}
?>