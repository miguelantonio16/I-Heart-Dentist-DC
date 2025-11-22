<?php
session_start();
include("../connection.php");

// Safer branch deletion flow:
// - If the branch has linked doctors/patients/appointments, show a confirmation page
//   listing samples of those linked records and provide Cancel and Force Delete options.
// - If `force=1` is present, perform the deletion regardless.

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id > 0) {
        // Query samples and counts (limit samples to avoid huge pages)
        $resDoctors = $database->query("SELECT docid, docname FROM doctor WHERE branch_id = $id LIMIT 50");
        $resPatients = $database->query("SELECT pid, pname FROM patient WHERE branch_id = $id LIMIT 50");
        $resAppts = $database->query("SELECT appoid, appodate, appointment_time FROM appointment WHERE branch_id = $id LIMIT 50");

        $countDoctors = intval($database->query("SELECT COUNT(*) AS cnt FROM doctor WHERE branch_id = $id")->fetch_assoc()['cnt']);
        $countPatients = intval($database->query("SELECT COUNT(*) AS cnt FROM patient WHERE branch_id = $id")->fetch_assoc()['cnt']);
        $countAppts = intval($database->query("SELECT COUNT(*) AS cnt FROM appointment WHERE branch_id = $id")->fetch_assoc()['cnt']);

        $totalRefs = $countDoctors + $countPatients + $countAppts;

        $force = isset($_GET['force']) && $_GET['force'] == '1';

        if ($totalRefs > 0 && !$force) {
            // Render confirmation page with lists
            ?>
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width,initial-scale=1">
                <title>Confirm Branch Deletion</title>
                <link rel="stylesheet" href="../css/main.css">
                <style>
                    body { font-family: Arial, Helvetica, sans-serif; background:#f6f7fb; padding:20px; }
                    .card { max-width:900px; margin:0 auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,0.06); }
                    .danger { background:#fff0f0; border:1px solid #ffd6d6; color:#8a1f1f; padding:12px; border-radius:6px; }
                    h2 { margin-top:0; }
                    .list { margin-top:14px; }
                    .list ul { margin:6px 0 0 18px; }
                    .actions { margin-top:18px; }
                    .btn { display:inline-block; padding:10px 14px; border-radius:6px; text-decoration:none; margin-right:8px; }
                    .btn-cancel { background:#eee; color:#333; }
                    .btn-force { background:#c93; color:#fff; background:#d9534f; }
                </style>
            </head>
            <body>
                <div class="card">
                    <h2>Branch Deletion — Linked Records Found</h2>
                    <div class="danger">This branch is referenced by <strong><?php echo $totalRefs; ?></strong> record(s):
                        <?php echo " (Dentists: $countDoctors, Patients: $countPatients, Appointments: $countAppts)"; ?>
                    </div>

                    <div class="list">
                        <h3>Sample Dentists (<?php echo $countDoctors; ?>)</h3>
                        <?php if ($resDoctors && $resDoctors->num_rows > 0): ?>
                            <ul>
                                <?php while ($d = $resDoctors->fetch_assoc()): ?>
                                    <li>Dr. <?php echo htmlspecialchars($d['docname']); ?> (ID: <?php echo $d['docid']; ?>)</li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p><em>No dentists in sample.</em></p>
                        <?php endif; ?>
                    </div>

                    <div class="list">
                        <h3>Sample Patients (<?php echo $countPatients; ?>)</h3>
                        <?php if ($resPatients && $resPatients->num_rows > 0): ?>
                            <ul>
                                <?php while ($p = $resPatients->fetch_assoc()): ?>
                                    <li><?php echo htmlspecialchars($p['pname']); ?> (ID: <?php echo $p['pid']; ?>)</li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p><em>No patients in sample.</em></p>
                        <?php endif; ?>
                    </div>

                    <div class="list">
                        <h3>Sample Appointments (<?php echo $countAppts; ?>)</h3>
                        <?php if ($resAppts && $resAppts->num_rows > 0): ?>
                            <ul>
                                <?php while ($a = $resAppts->fetch_assoc()): ?>
                                    <li>Appointment #<?php echo $a['appoid']; ?> — <?php echo htmlspecialchars($a['appodate']); ?> <?php echo htmlspecialchars($a['appointment_time']); ?></li>
                                <?php endwhile; ?>
                            </ul>
                        <?php else: ?>
                            <p><em>No appointments in sample.</em></p>
                        <?php endif; ?>
                    </div>

                    <div class="actions">
                        <a class="btn btn-cancel" href="settings.php">Cancel</a>
                        <a class="btn btn-force" href="delete-branch.php?id=<?php echo $id; ?>&force=1" onclick="return confirm('Force delete will remove the branch row but will NOT reassign or remove linked records. Proceed?')">Force Delete</a>
                        <a class="btn" href="dentist.php?branch_id=<?php echo $id; ?>">View Dentists</a>
                        <a class="btn" href="patient.php?branch_id=<?php echo $id; ?>">View Patients</a>
                        <a class="btn" href="booking.php?branch_id=<?php echo $id; ?>">View Bookings</a>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }

        // No references or force requested: delete
        $stmt = $database->prepare("DELETE FROM branches WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }
}

header('Location: settings.php');
?>