<?php
/**
 * Run this once from the browser (while logged in as admin) to create the branches table
 * and add branch_id columns to doctor and patient tables. Safe to re-run (uses IF NOT EXISTS).
 */
session_start();
include("../../connection.php");

try {
    // Create branches table
    $sql = "CREATE TABLE IF NOT EXISTS branches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(191) NOT NULL,
        address TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $database->query($sql);

    // Add branch_id to doctor if not exists (works across MySQL versions)
    $res = $database->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'doctor' AND COLUMN_NAME = 'branch_id'");
    if ($res->num_rows == 0) {
        $database->query("ALTER TABLE doctor ADD COLUMN branch_id INT NULL");
    }

    // Add branch_id to patient if not exists
    $res2 = $database->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patient' AND COLUMN_NAME = 'branch_id'");
    if ($res2->num_rows == 0) {
        $database->query("ALTER TABLE patient ADD COLUMN branch_id INT NULL");
    }

    // Insert default branches if they don't exist
    $stmt = $database->prepare("SELECT id FROM branches WHERE name = ? LIMIT 1");
    $defaults = [
        ['Bacoor', 'Bacoor branch'],
        ['Makati Branch', 'Makati branch']
    ];
    foreach ($defaults as $b) {
        $name = $b[0];
        $addr = $b[1];
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows == 0) {
            $ins = $database->prepare("INSERT INTO branches (name, address) VALUES (?, ?)");
            $ins->bind_param('ss', $name, $addr);
            $ins->execute();
        }
    }

    echo "Migration completed. Branches table created/verified and branch_id columns added to doctor & patient.";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage();
}

?>
