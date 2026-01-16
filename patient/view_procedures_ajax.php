<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['usertype'] !== 'p') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once '../connection.php';

$useremail = $_SESSION['user'];
$userrow = $database->query("SELECT pid FROM patient WHERE pemail='$useremail' LIMIT 1");
if (!$userrow || $userrow->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Patient not found']);
    exit();
}
$userid = (int)$userrow->fetch_assoc()['pid'];

$appoid = isset($_GET['appoid']) ? (int)$_GET['appoid'] : 0;
if ($appoid <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid appointment.']);
    exit();
}

// Ensure appointment belongs to this patient and is completed
$appRes = $database->query("SELECT appoid, pid, status, payment_status, reservation_paid FROM appointment WHERE appoid=$appoid AND pid=$userid LIMIT 1");
if (!$appRes || $appRes->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Appointment not found.']);
    exit();
}
$appRow = $appRes->fetch_assoc();

if ($appRow['status'] !== 'completed' || $appRow['payment_status'] !== 'paid') {
    echo json_encode(['success' => false, 'error' => 'Procedures are visible only after payment is completed.']);
    exit();
}

// Load stacked procedures
$listRes = $database->query("SELECT ap.id, p.procedure_name, ap.agreed_price FROM appointment_procedures ap INNER JOIN procedures p ON ap.procedure_id=p.procedure_id WHERE ap.appointment_id=$appoid ORDER BY ap.id ASC");
$procedures = [];
$discounts = [];
$procedures_total = 0.0;
$discounts_total = 0.0; // positive value
if ($listRes) {
    while ($r = $listRes->fetch_assoc()) {
        $name = $r['procedure_name'];
        $price = (float)$r['agreed_price'];
        // Treat discount rows by name
        if (in_array($name, ['PWD Discount', 'Senior Citizen Discount'])) {
            $discounts[] = [
                'id' => (int)$r['id'],
                'procedure_name' => $name,
                'agreed_price' => $price,
            ];
            $discounts_total += abs($price);
        } else {
            $procedures[] = [
                'id' => (int)$r['id'],
                'procedure_name' => $name,
                'agreed_price' => $price,
            ];
            $procedures_total += $price;
        }
    }
}

// Determine if reservation applies
$reservationPaid = isset($appRow['reservation_paid']) ? (int)$appRow['reservation_paid'] : 0;
$statusVal = isset($appRow['status']) ? strtolower($appRow['status']) : '';
$payStatusVal = isset($appRow['payment_status']) ? strtolower($appRow['payment_status']) : '';
$hasReservation = ($reservationPaid === 1) || ($payStatusVal === 'partial') || ($statusVal === 'pending_reservation') || ($statusVal === 'booking');

$reservationFee = $hasReservation ? 250.00 : 0.00;

// Compute net after applying reservation and discounts (apply discount after reservation deduction per UI requirement)
$netBeforeReservation = $procedures_total; // sum of non-discount procedures
$netAfterReservation = $hasReservation ? max($netBeforeReservation - $reservationFee - $discounts_total, 0) : ($netBeforeReservation - $discounts_total);

echo json_encode([
    'success' => true,
    'procedures' => $procedures,
    'discounts' => $discounts,
    'procedures_total' => round($procedures_total,2),
    'discounts_total' => round($discounts_total,2),
    'total_amount' => round($procedures_total - $discounts_total,2),
    'reservation_fee' => $reservationFee,
    'net_after_reservation' => round($netAfterReservation,2),
    'has_reservation' => $hasReservation,
]);
