<?php
// export_csv.php - exports financial rows and summary as CSV based on date filter
session_start();
if (!isset($_SESSION['user']) || $_SESSION['usertype'] != 'a') {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$connectionPath = __DIR__ . '/../../connection.php';
if (!file_exists($connectionPath)) die("Missing connection file: $connectionPath");
include_once $connectionPath;

$startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : null;

$dateWhere = '';
if ($startDate && $endDate) {
    $s = $database->real_escape_string($startDate);
    $e = $database->real_escape_string($endDate);
    $dateWhere = " AND DATE(a.appodate) BETWEEN '$s' AND '$e'";
} elseif ($startDate) {
    $s = $database->real_escape_string($startDate);
    $dateWhere = " AND DATE(a.appodate) >= '$s'";
} elseif ($endDate) {
    $e = $database->real_escape_string($endDate);
    $dateWhere = " AND DATE(a.appodate) <= '$e'";
}

// Query appointments with payment info
$sql = "SELECT a.appoid, a.appodate, a.appointment_time, p.pname as patient, d.docname as dentist, pr.procedure_name, a.total_amount, a.payment_status, b.name as branch
FROM appointment a
LEFT JOIN patient p ON a.pid = p.pid
LEFT JOIN doctor d ON a.docid = d.docid
LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id
LEFT JOIN branches b ON d.branch_id = b.id
WHERE 1 " . $dateWhere . " ORDER BY a.appodate ASC";

$res = $database->query($sql);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=financial_report_' . date('Ymd_His') . '.csv');
$out = fopen('php://output','w');
// Header
fputcsv($out, ['App ID','Date','Time','Patient','Dentist','Procedure','Amount','Payment Status','Branch']);
if ($res && $res->num_rows>0) {
    while ($r=$res->fetch_assoc()) {
        fputcsv($out, [
            $r['appoid'],
            $r['appodate'],
            $r['appointment_time'],
            $r['patient'],
            $r['dentist'],
            $r['procedure_name'],
            number_format($r['total_amount'],2,'.',''),
            $r['payment_status'],
            $r['branch']
        ]);
    }
}
fclose($out);
exit;
