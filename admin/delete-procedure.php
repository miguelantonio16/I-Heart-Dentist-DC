<?php
session_start();
include("../connection.php");

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Ensure id is integer
    $id = intval($id);

    // Check references in appointment (legacy single-procedure field)
    $check1 = $database->query("SELECT COUNT(*) AS cnt FROM appointment WHERE procedure_id = $id");
    $cnt1 = 0;
    if ($check1 && $r = $check1->fetch_assoc()) $cnt1 = intval($r['cnt']);

    // Check references in appointment_procedures (multiple procedures per appointment)
    $check2 = $database->query("SELECT COUNT(*) AS cnt FROM appointment_procedures WHERE procedure_id = $id");
    $cnt2 = 0;
    if ($check2 && $r2 = $check2->fetch_assoc()) $cnt2 = intval($r2['cnt']);

    $totalRefs = $cnt1 + $cnt2;

    // If references exist and the user has not confirmed force-delete, redirect to settings page
    $force = isset($_GET['force']) ? intval($_GET['force']) : 0;
    if ($totalRefs > 0 && !($force === 1 && $_SERVER['REQUEST_METHOD'] === 'POST')) {
        // Redirect back to settings with counts so the UI can show a modal confirmation
        $nameRes = $database->query("SELECT procedure_name FROM procedures WHERE procedure_id = $id LIMIT 1");
        $pname = '';
        if ($nameRes && $nameRes->num_rows) $pname = $nameRes->fetch_assoc()['procedure_name'];
        $params = http_build_query(['action' => 'drop_procedure', 'id' => $id, 'cnt1' => $cnt1, 'cnt2' => $cnt2, 'name' => $pname]);
        header('Location: settings.php?' . $params);
        exit();
    }

    // If we reach here, either there were no references or user confirmed force-delete via POST
    // Perform destructive cleanup inside a transaction. Archive linked procedures first.
    $database->begin_transaction();
    try {
        // Ensure archive table exists for historical display
        $createArchiveSql = "CREATE TABLE IF NOT EXISTS appointment_procedures_archive (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL,
            procedure_id INT NULL,
            procedure_name VARCHAR(255) NOT NULL,
            agreed_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_appt_proc_arch_appointment (appointment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        if ($database->query($createArchiveSql) === false) {
            throw new Exception('Failed to ensure archive table: ' . $database->error);
        }

        if ($cnt2 > 0) {
            // Archive appointment_procedures rows referencing this procedure
            $archiveSql = "INSERT INTO appointment_procedures_archive (appointment_id, procedure_id, procedure_name, agreed_price)
                           SELECT ap.appointment_id, ap.procedure_id, p.procedure_name, COALESCE(ap.agreed_price, COALESCE(p.price,0))
                           FROM appointment_procedures ap
                           LEFT JOIN procedures p ON ap.procedure_id = p.procedure_id
                           WHERE ap.procedure_id = $id";
            if ($database->query($archiveSql) === false) {
                throw new Exception('Failed to archive appointment procedures: ' . $database->error);
            }

            // Remove appointment_procedures rows referencing this procedure
            $del_ap = $database->query("DELETE FROM appointment_procedures WHERE procedure_id = $id");
            if ($del_ap === false) throw new Exception('Failed to delete appointment_procedures: ' . $database->error);
        }

        if ($cnt1 > 0) {
            // Clear legacy single-procedure references
            $upd = $database->query("UPDATE appointment SET procedure_id = 0 WHERE procedure_id = $id");
            if ($upd === false) throw new Exception('Failed to update appointment.procedure_id: ' . $database->error);
        }

        // Delete the procedure
        $del_proc = $database->query("DELETE FROM procedures WHERE procedure_id = $id");
        if ($del_proc === false) throw new Exception('Failed to delete procedure: ' . $database->error);

        $database->commit();
        $msg = 'Procedure deleted. Archived ' . $cnt2 . ' appointment procedures and cleared ' . $cnt1 . ' appointment references.';
        header('Location: settings.php?success=' . urlencode($msg));
        exit();
    } catch (Exception $e) {
        $database->rollback();
        header('Location: settings.php?error=' . urlencode('Delete failed: ' . $e->getMessage()));
        exit();
    }
} else {
    header("location: settings.php");
}
?>