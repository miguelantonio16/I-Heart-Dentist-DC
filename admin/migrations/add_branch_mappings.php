<?php
/**
 * Migration to add doctor_branches and patient_branches mapping tables
 * and to ensure `branch_id` exists on `appointment`. Safe to re-run.
 */
session_start();
include("../../connection.php");

try {
    // doctor_branches
    $sql = "CREATE TABLE IF NOT EXISTS doctor_branches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docid INT NOT NULL,
        branch_id INT NOT NULL,
        UNIQUE KEY ux_doc_branch (docid, branch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $database->query($sql);

    // patient_branches
    $sql2 = "CREATE TABLE IF NOT EXISTS patient_branches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pid INT NOT NULL,
        branch_id INT NOT NULL,
        UNIQUE KEY ux_patient_branch (pid, branch_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $database->query($sql2);

    // Ensure appointment has branch_id
    $res = $database->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointment' AND COLUMN_NAME = 'branch_id'");
    if ($res->num_rows == 0) {
        $database->query("ALTER TABLE appointment ADD COLUMN branch_id INT NULL");
    }

    // Migrate existing single branch values (doctor.branch_id -> doctor_branches)
    $r = $database->query("SELECT docid, branch_id FROM doctor WHERE branch_id IS NOT NULL AND branch_id != ''");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $docid = (int)$row['docid'];
            $branch = (int)$row['branch_id'];
            if ($branch>0) {
                $database->query("INSERT IGNORE INTO doctor_branches (docid, branch_id) VALUES ('$docid', '$branch')");
            }
        }
    }

    // Migrate existing patient.branch_id -> patient_branches
    $r2 = $database->query("SELECT pid, branch_id FROM patient WHERE branch_id IS NOT NULL AND branch_id != ''");
    if ($r2) {
        while ($row = $r2->fetch_assoc()) {
            $pid = (int)$row['pid'];
            $branch = (int)$row['branch_id'];
            if ($branch>0) {
                $database->query("INSERT IGNORE INTO patient_branches (pid, branch_id) VALUES ('$pid', '$branch')");
            }
        }
    }

    echo "Migration completed: mapping tables created and appointment.branch_id ensured. Existing single-branch values migrated to mapping tables.";

} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage();
}

?>
