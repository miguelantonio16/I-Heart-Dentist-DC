<?php
session_start();
date_default_timezone_set('Asia/Singapore');

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$usertype = isset($_SESSION['usertype']) ? $_SESSION['usertype'] : '';
if ($usertype !== 'p' && $usertype !== 'a') {
    header('Location: login.php');
    exit;
}

include_once __DIR__ . '/../connection.php';

// Get appoid from query early so admin lookup can use it
$appoid = isset($_GET['appoid']) ? intval($_GET['appoid']) : 0;
if ($appoid <= 0) {
    echo "Invalid appointment id.";
    exit;
}

$userid = null;
$patient_name = null;

if ($usertype === 'p') {
    // Patient viewing their own receipt
    $useremail = $_SESSION['user'];
    $u = $database->query("SELECT pid, pname FROM patient WHERE pemail='" . $database->real_escape_string($useremail) . "' LIMIT 1");
    if ($u && $u->num_rows > 0) {
        $uf = $u->fetch_assoc();
        $userid = $uf['pid'];
        $patient_name = $uf['pname'];
    } else {
        echo "Invalid user";
        exit;
    }

    // Only allow patients to view receipts for their own appointments
    $sql = "SELECT a.*, d.docname, d.docid, b.name AS branch_name
            FROM appointment a
            LEFT JOIN doctor d ON a.docid = d.docid
            LEFT JOIN branches b ON a.branch_id = b.id
            WHERE a.appoid = '" . $database->real_escape_string($appoid) . "' AND a.pid = '" . $database->real_escape_string($userid) . "' LIMIT 1";

} else {
    // Admin users: allow viewing any appointment receipt
    $sql = "SELECT a.*, d.docname, d.docid, b.name AS branch_name, p.pname AS patient_name
            FROM appointment a
            LEFT JOIN doctor d ON a.docid = d.docid
            LEFT JOIN branches b ON a.branch_id = b.id
            LEFT JOIN patient p ON a.pid = p.pid
            WHERE a.appoid = '" . $database->real_escape_string($appoid) . "' LIMIT 1";
}

$res = $database->query($sql);
if (!$res || $res->num_rows == 0) {
    echo "Appointment not found or you don't have permission to view this receipt.";
    exit;
}
$ap = $res->fetch_assoc();

// If admin, get the patient name from the appointment join
if ($usertype === 'a') {
    $patient_name = $ap['patient_name'] ?? 'Unknown';
    $userid = $ap['pid'] ?? null;
}

// Details for receipt
$clinic_name = 'IHeartDentistDC Clinic';
$clinic_logo = '../Media/Icon/logo.png';
$receipt_no = 'RCPT-' . str_pad($appoid, 6, '0', STR_PAD_LEFT);
$issued_at = date('F j, Y, g:i A');
$appointment_status = isset($ap['status']) ? strtolower($ap['status']) : '';
$payment_status = isset($ap['payment_status']) ? strtolower($ap['payment_status']) : '';
$reservation_paid = isset($ap['reservation_paid']) ? intval($ap['reservation_paid']) : 0;
$isCompleted = ($appointment_status === 'completed');
$isFullyPaid = ($payment_status === 'paid');
$patient_fullname = htmlspecialchars($patient_name);
$dentist = htmlspecialchars($ap['docname'] ?? '-');
$branch = htmlspecialchars($ap['branch_name'] ?? '-');
$appointment_date = (!empty($ap['appodate'])) ? date('F j, Y', strtotime($ap['appodate'])) : '-';
$appointment_time = (!empty($ap['appointment_time'])) ? date('g:i A', strtotime($ap['appointment_time'])) : '-';

// Determine if this appointment has a reservation fee (patient-booked) or not (admin-booked)
// Heuristics: reservation applies when reservation_paid=1 or payment_status='partial' or status in ['pending_reservation','booking']
$hasReservation = ($reservation_paid === 1)
    || ($payment_status === 'partial')
    || ($appointment_status === 'pending_reservation')
    || ($appointment_status === 'booking');

// Financials baseline (used only when showing reservation section)
$subtotal = 250.00;
$tax_rate = 0.0;
$tax = 0.00;
$total = round($subtotal + $tax, 2);

// Fetch procedures attached to this appointment (if any) and their prices
// Fetch appointment procedures and separate discounts from normal items
$procedure_items = [];
$discount_items = [];
$procedures_total = 0.00; // sum of non-discount items
$discounts_total = 0.00; // sum of discount rows (negative values)

// Prefer stacked procedures with agreed prices; fall back to archive if procedure definitions were deleted
$procRes = $database->query("SELECT p.procedure_name, COALESCE(ap.agreed_price, COALESCE(p.price,0)) AS price
                             FROM appointment_procedures ap
                             JOIN procedures p ON ap.procedure_id = p.procedure_id
                             WHERE ap.appointment_id='" . $database->real_escape_string($appoid) . "'");
if ($procRes && $procRes->num_rows > 0) {
    while ($pr = $procRes->fetch_assoc()) {
        $name = $pr['procedure_name'];
        $price = floatval($pr['price']);
        // Treat these procedure names as discounts
        if (in_array($name, ['PWD Discount', 'Senior Citizen Discount'])) {
            $discount_items[] = ['name' => $name, 'price' => $price];
            $discounts_total += $price; // likely negative
        } else {
            $procedure_items[] = ['name' => $name, 'price' => $price];
            $procedures_total += $price;
        }
    }
} else {
    // Try archive snapshot (archive rows may include discounts too)
    $archRes = $database->query("SELECT procedure_name, agreed_price FROM appointment_procedures_archive WHERE appointment_id='" . $database->real_escape_string($appoid) . "' ORDER BY id ASC");
    if ($archRes && $archRes->num_rows > 0) {
        while ($ar = $archRes->fetch_assoc()) {
            $name = $ar['procedure_name'];
            $price = floatval($ar['agreed_price']);
            if (in_array($name, ['PWD Discount', 'Senior Citizen Discount'])) {
                $discount_items[] = ['name' => $name, 'price' => $price];
                $discounts_total += $price;
            } else {
                $procedure_items[] = ['name' => $name, 'price' => $price];
                $procedures_total += $price;
            }
        }
    }
}

// If appointment has a single procedure_id stored on appointment table, include it when no appointment_procedures rows exist
if (empty($procedure_items) && !empty($ap['procedure_id']) && $ap['procedure_id'] != 0) {
    $single = $database->query("SELECT procedure_name, COALESCE(price,0) AS price FROM procedures WHERE procedure_id='" . $database->real_escape_string($ap['procedure_id']) . "' LIMIT 1");
    if ($single && $single->num_rows > 0) {
        $s = $single->fetch_assoc();
        $procedure_items[] = ['name' => $s['procedure_name'], 'price' => floatval($s['price'])];
        $procedures_total += floatval($s['price']);
    }
}

// Recompute totals
$reservation_fee = 250.00;
$base_services_total = $procedures_total; // sum of non-discount procedure prices
$discounts_total = isset($discounts_total) ? $discounts_total : 0.00; // negative or 0
$net_services_total = round($base_services_total + $discounts_total, 2); // after applying discounts
$gross_total = $reservation_fee + $net_services_total;

// If no reservation (admin-booked), do not deduct reservation fee from total bill
if ($hasReservation) {
    $amount_due = round(max($net_services_total - $reservation_fee, 0), 2);
} else {
    $amount_due = round($net_services_total, 2);
}
// 'Total' shown later corresponds to amount due
$total = $amount_due;

function fmt($n) {
    return '₱' . number_format($n, 2);
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Receipt - <?php echo htmlspecialchars($clinic_name); ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #222; padding: 24px; }
        .receipt { max-width: 800px; margin: 0 auto; border: 1px solid #e6e6e6; padding: 28px; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
        .header { display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; }
        .logo img { height:72px; }
        .clinic { text-align:center; flex:1; }
        .clinic h2 { margin:0; font-size:20px; letter-spacing:0.3px; }
        .clinic p { margin:2px 0 0 0; color:#666; font-size:13px; }
        .meta { text-align:right; font-size:13px; color:#555; }
        h1.title { text-align:center; margin-top: 8px; margin-bottom: 16px; font-size:18px; }
        .info { display:flex; justify-content:space-between; margin-bottom:18px; }
        .info .left, .info .right { width:48%; font-size:14px; }
        table.items { width:100%; border-collapse:collapse; margin-top:12px; }
        table.items th, table.items td { padding:10px 12px; border-bottom:1px solid #eee; text-align:left; }
        table.items th { background:#fafafa; font-weight:700; font-size:13px; }
        .totals { margin-top:12px; float:right; width:320px; }
        .totals table { width:100%; }
        .totals td { padding:8px 6px; }
        .totals .label { color:#555; }
        .totals .value { text-align:right; font-weight:600; }
        .grand-total { font-size:18px; font-weight:800; border-top:2px solid #222; padding-top:8px; }
        .print-btn { display:inline-block; margin-top:18px; padding:10px 14px; background:#2f3670; color:#fff; border-radius:6px; text-decoration:none; }
        .small { font-size:12px; color:#777; }
        @media print {
            .print-btn { display:none; }
            body { padding:0; }
            .receipt { box-shadow:none; border:none; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="logo">
                <img src="<?php echo $clinic_logo; ?>" alt="<?php echo htmlspecialchars($clinic_name); ?> Logo">
            </div>
            <div class="clinic">
                <h2><?php echo htmlspecialchars($clinic_name); ?></h2>
                <p>Official Receipt</p>
            </div>
            <div class="meta">
                <div><strong><?php echo $receipt_no; ?></strong></div>
                <div class="small">Issued: <?php echo $issued_at; ?></div>
            </div>
        </div>

        <?php $hasProcedures = !empty($procedure_items); ?>

        <?php if ($hasReservation && !$isFullyPaid): ?>
            <h1 class="title">Reservation Fee Receipt</h1>

            <div class="info">
                <div class="left">
                    <div><strong>Patient:</strong> <?php echo $patient_fullname; ?></div>
                    <div><strong>Dentist:</strong> <?php echo $dentist; ?></div>
                    <div><strong>Branch:</strong> <?php echo $branch; ?></div>
                </div>
                <div class="right">
                    <div><strong>Appointment Date:</strong> <?php echo $appointment_date; ?></div>
                    <div><strong>Appointment Time:</strong> <?php echo $appointment_time; ?></div>
                    <div><strong>Booking ID:</strong> <?php echo $appoid; ?></div>
                </div>
            </div>

            <table class="items" aria-describedby="line-items">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="width:120px;text-align:right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Reservation Fee (1 x)</td>
                        <td style="text-align:right"><?php echo fmt($reservation_fee); ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="totals">
                <table>
                    <tr class="grand-total">
                        <td class="label"><strong>Total</strong></td>
                        <td class="value"><strong><?php echo fmt($reservation_fee); ?></strong></td>
                    </tr>
                </table>
            </div>

        <?php else: ?>
            <h1 class="title">Procedure Invoice</h1>

            <div class="info">
                <div class="left">
                    <div><strong>Patient:</strong> <?php echo $patient_fullname; ?></div>
                    <div><strong>Dentist:</strong> <?php echo $dentist; ?></div>
                    <div><strong>Branch:</strong> <?php echo $branch; ?></div>
                </div>
                <div class="right">
                    <div><strong>Appointment Date:</strong> <?php echo $appointment_date; ?></div>
                    <div><strong>Appointment Time:</strong> <?php echo $appointment_time; ?></div>
                    <div><strong>Booking ID:</strong> <?php echo $appoid; ?></div>
                </div>
            </div>

            <table class="items" aria-describedby="line-items">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="width:120px;text-align:right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($procedure_items as $it): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($it['name']); ?></td>
                            <td style="text-align:right"><?php echo fmt($it['price']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals">
                <table>
                    <tr>
                        <td class="label">Procedures Subtotal</td>
                        <td class="value"><?php echo fmt($base_services_total); ?></td>
                    </tr>
                    <?php if (!empty($discount_items)): ?>
                    <?php $dlabel = htmlspecialchars($discount_items[0]['name']); $damount = $discounts_total; ?>
                    <tr>
                        <td class="label"><?php echo $dlabel; ?></td>
                        <td class="value">- <?php echo fmt(abs($damount)); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($hasReservation): ?>
                    <tr>
                        <td class="label">Reservation Fee Paid</td>
                        <td class="value">- <?php echo fmt($reservation_fee); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="grand-total">
                        <td class="label"><strong>Amount Due</strong></td>
                        <td class="value"><strong><?php echo fmt($amount_due); ?></strong></td>
                    </tr>
                </table>
            </div>

        <?php endif; ?>

        <div style="clear:both"></div>

        <?php if ($hasReservation): ?>
        <p class="small" style="margin-top:18px;">This receipt acknowledges payment (or reservation record) for the reservation fee. Reservation fees are non-refundable unless otherwise stated by the clinic.</p>
        <?php endif; ?>

        <a href="#" class="print-btn" onclick="window.print();return false;">Print / Save as PDF</a>
        <a href="my_booking.php" style="margin-left:12px; color:#2f3670; text-decoration:none;">Close</a>
    </div>
</body>
</html>
