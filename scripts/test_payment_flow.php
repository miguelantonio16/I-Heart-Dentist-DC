<?php
// Test script: create pending reservation, simulate payment success, verify branch_id
require_once __DIR__ . '/../connection.php';

echo "Starting test_payment_flow...\n";

// 1) Ensure there's a branch
$bres = $database->query("SELECT id FROM branches LIMIT 1");
if ($bres && $bres->num_rows > 0) {
    $branch = $bres->fetch_assoc();
    $branch_id = (int)$branch['id'];
    echo "Found branch id: $branch_id\n";
} else {
    $database->query("INSERT INTO branches (name, address) VALUES ('Test Branch', 'Test Address')");
    $branch_id = $database->insert_id;
    echo "Created branch id: $branch_id\n";
}

// 2) Ensure there's a patient
$email = 'test_patient@example.com';
$pr = $database->query("SELECT pid FROM patient WHERE pemail='".$database->real_escape_string($email)."' LIMIT 1");
if ($pr && $pr->num_rows>0) {
    $pid = (int)$pr->fetch_assoc()['pid'];
    echo "Found patient id: $pid\n";
    // ensure patient's branch_id is set to branch_id
    $database->query("UPDATE patient SET branch_id='".$database->real_escape_string($branch_id)."' WHERE pid='".$pid."'");
} else {
    $database->query("INSERT INTO patient (pname, pemail, ptel, status, branch_id) VALUES ('Test Patient', '".$database->real_escape_string($email)."', '0000000000', 'active', '".$database->real_escape_string($branch_id)."')");
    $pid = $database->insert_id;
    echo "Created patient id: $pid\n";
}

// 3) Insert pending appointment without branch_id
$date = date('Y-m-d');
$insert = "INSERT INTO appointment (pid, scheduleid, appodate, procedure_id, status, payment_status, total_amount, branch_id) VALUES ('".$pid."', 0, '$date', 0, 'pending_reservation', 'unpaid', 0, NULL)";
if ($database->query($insert)) {
    $appoid = $database->insert_id;
    echo "Inserted pending appointment id: $appoid\n";
} else {
    die("Failed to insert appointment: " . $database->error . "\n");
}

// 4) Simulate payment success logic (what payment_success.php would do)
$update = "UPDATE appointment SET status='booking', payment_status='partial', reservation_paid=1, payment_method='paymongo' WHERE appoid='".$appoid."'";
if ($database->query($update)) {
    echo "Updated appointment status to booking.\n";
} else {
    die("Failed to update appointment: " . $database->error . "\n");
}

// Now ensure branch_id is set: if appointment.branch_id is empty, set from patient.branch_id
$appRes = $database->query("SELECT branch_id, pid FROM appointment WHERE appoid='".$appoid."' LIMIT 1");
if ($appRes && $appRes->num_rows>0) {
    $ar = $appRes->fetch_assoc();
    if (empty($ar['branch_id'])) {
        $pb = $database->query("SELECT branch_id FROM patient WHERE pid='".$ar['pid']."' LIMIT 1");
        if ($pb && $pb->num_rows>0) {
            $setBranch = (int)$pb->fetch_assoc()['branch_id'];
            if (!empty($setBranch)) {
                $database->query("UPDATE appointment SET branch_id='".$database->real_escape_string($setBranch)."' WHERE appoid='".$appoid."'");
                echo "Set appointment.branch_id = $setBranch\n";
            }
        }
    } else {
        echo "Appointment already has branch_id: " . $ar['branch_id'] . "\n";
    }
}

// 5) Verify
$final = $database->query("SELECT appoid, branch_id FROM appointment WHERE appoid='".$appoid."' LIMIT 1");
$f = $final->fetch_assoc();
echo "Final appointment record: appoid={$f['appoid']}, branch_id={$f['branch_id']}\n";

echo "Test complete.\n";

?>