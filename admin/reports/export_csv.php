<?php
// Enhanced financial export CSV including summary + aggregated sections + detailed appointments
session_start();
if (!isset($_SESSION['user']) || $_SESSION['usertype'] != 'a') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$connectionPath = __DIR__ . '/../../connection.php';
if (!file_exists($connectionPath)) die('Missing connection file: ' . $connectionPath);
include_once $connectionPath;

// Branch restriction: if admin is restricted, only export that branch
$restrictedBranchId = 0;
// Allow explicit branch override via GET for exports (page passes &branch_id when restricted)
if (isset($_GET['branch_id']) && is_numeric($_GET['branch_id']) && $_GET['branch_id']) {
    $restrictedBranchId = (int)$_GET['branch_id'];
} elseif (isset($_SESSION['restricted_branch_id']) && is_numeric($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id']) {
    $restrictedBranchId = (int)$_SESSION['restricted_branch_id'];
}
$branchWhere = '';
if ($restrictedBranchId > 0) {
    // Restrict strictly to appointments assigned to the given branch
    $branchWhere = " AND a.branch_id = " . intval($restrictedBranchId);
}
// Resolve branch name if restricted (for header)
$branchName = '';
if ($restrictedBranchId > 0) {
    $br = $database->query('SELECT name FROM branches WHERE id=' . $restrictedBranchId . ' LIMIT 1');
    if ($br && $br->num_rows) $branchName = $br->fetch_assoc()['name'];
}

$startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : null;
$endDate   = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : null;
$year      = isset($_GET['year']) && preg_match('/^\d{4}$/', $_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$sort      = isset($_GET['sort']) ? $_GET['sort'] : 'date_asc'; // date_asc | date_desc | amount | branch | procedure

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

// Aggregated revenue by branch (paid only)
$branchSql = "SELECT COALESCE(b.name,'(Unassigned)') AS branch, IFNULL(SUM(a.total_amount),0) AS amt
             FROM appointment a
             LEFT JOIN doctor d ON a.docid = d.docid
             LEFT JOIN branches b ON b.id = COALESCE(a.branch_id, d.branch_id)
             WHERE a.payment_status='paid' $dateWhere $branchWhere
             GROUP BY branch ORDER BY amt DESC";
$branchData = [];
if ($brRes = $database->query($branchSql)) {
    while ($r = $brRes->fetch_assoc()) $branchData[] = $r;
}

// Aggregated revenue by day (paid only)
$dailySql = "SELECT DATE(a.appodate) AS d, IFNULL(SUM(a.total_amount),0) AS amt
            FROM appointment a
            LEFT JOIN doctor d ON a.docid = d.docid
            WHERE a.payment_status='paid' $dateWhere $branchWhere
            GROUP BY DATE(a.appodate) ORDER BY d ASC";
$dailyData = [];
if ($dyRes = $database->query($dailySql)) {
    while ($r = $dyRes->fetch_assoc()) $dailyData[] = $r;
}

// Aggregated revenue by month for selected year (paid only)
$monthSql = "SELECT MONTH(a.appodate) AS m, IFNULL(SUM(a.total_amount),0) AS amt
            FROM appointment a
            LEFT JOIN doctor d ON a.docid = d.docid
            WHERE a.payment_status='paid' AND YEAR(a.appodate)=$year $branchWhere
            GROUP BY MONTH(a.appodate) ORDER BY m ASC";
$monthData = array_fill(1,12,['m'=>null,'amt'=>0]);
if ($moRes = $database->query($monthSql)) {
    while ($r = $moRes->fetch_assoc()) $monthData[(int)$r['m']] = $r;
}

// Top procedures (paid only) - include primary + stacked procedures
$topProcedures = [];
$apptDocFilter = '';
if (!empty($branchWhere)) $apptDocFilter = $branchWhere; // contains leading AND

$topProcSql = "SELECT p.procedure_name, SUM(t.cnt) AS cnt, SUM(t.amount) AS amount FROM (\n" .
    "  SELECT ap.procedure_id AS pid, COUNT(*) AS cnt, IFNULL(SUM(ap.agreed_price),0) AS amount FROM appointment_procedures ap JOIN appointment a ON ap.appointment_id = a.appoid LEFT JOIN doctor d ON a.docid=d.docid WHERE a.payment_status='paid' " . $dateWhere . " " . $apptDocFilter . " GROUP BY ap.procedure_id\n" .
    "  UNION ALL\n" .
    "  SELECT a.procedure_id AS pid, COUNT(*) AS cnt, IFNULL(SUM(a.total_amount),0) AS amount FROM appointment a LEFT JOIN appointment_procedures ap ON a.appoid = ap.appointment_id LEFT JOIN doctor d ON a.docid=d.docid WHERE a.payment_status='paid' AND ap.id IS NULL " . $dateWhere . " " . $apptDocFilter . " GROUP BY a.procedure_id\n" .
") t JOIN procedures p ON p.procedure_id = t.pid GROUP BY p.procedure_id ORDER BY amount DESC";

if ($tpRes = $database->query($topProcSql)) {
    while ($r = $tpRes->fetch_assoc()) $topProcedures[] = $r;
}

// Totals
$totPaidRes = $database->query("SELECT IFNULL(SUM(a.total_amount),0) AS tot FROM appointment a LEFT JOIN doctor d ON a.docid=d.docid WHERE a.payment_status='paid' $dateWhere $branchWhere");
$totalRevenuePaid = $totPaidRes ? (float)$totPaidRes->fetch_assoc()['tot'] : 0;
$cntPaidRes = $database->query("SELECT COUNT(*) AS cnt FROM appointment a LEFT JOIN doctor d ON a.docid=d.docid WHERE a.payment_status='paid' $dateWhere $branchWhere");
$paidCount = $cntPaidRes ? (int)$cntPaidRes->fetch_assoc()['cnt'] : 0;
$cntAllRes = $database->query("SELECT COUNT(*) AS cnt FROM appointment a LEFT JOIN doctor d ON a.docid=d.docid WHERE 1 $dateWhere $branchWhere");
$allCount = $cntAllRes ? (int)$cntAllRes->fetch_assoc()['cnt'] : 0;

    if (!empty($branchWhere)) {
         // branchWhere begins with ' AND a.branch_id = ...' so it's safe to reuse for aliased queries
         $apptDocFilter = $branchWhere;
    }
// Detailed appointment ordering
$orderBy = 'a.appodate ASC';
switch ($sort) {
    case 'date_desc': $orderBy = 'a.appodate DESC'; break;
    case 'amount': $orderBy = 'a.total_amount DESC'; break;
    case 'branch': $orderBy = 'branch ASC, a.appodate ASC'; break;
    case 'procedure': $orderBy = 'pr.procedure_name ASC, a.appodate ASC'; break;
    default: $orderBy = 'a.appodate ASC';
}

// Apply sort to aggregated arrays (branchData, dailyData, topProcedures) for consistency
if ($sort === 'amount') {
    usort($branchData, function($a,$b){ return $b['amt'] <=> $a['amt']; });
    usort($dailyData, function($a,$b){ return $b['amt'] <=> $a['amt']; });
    usort($topProcedures, function($a,$b){ return $b['amount'] <=> $a['amount']; });
} elseif ($sort === 'branch') {
    usort($branchData, function($a,$b){ return strcasecmp($a['branch'],$b['branch']); });
} elseif ($sort === 'date_desc') {
    usort($dailyData, function($a,$b){ return strcmp($b['d'],$a['d']); });
} elseif ($sort === 'date_asc') {
    usort($dailyData, function($a,$b){ return strcmp($a['d'],$b['d']); });
} elseif ($sort === 'procedure') {
    usort($topProcedures, function($a,$b){ return strcasecmp($a['procedure_name'],$b['procedure_name']); });
}

// Query detailed appointments: produce one row per procedure by unioning
// appointments without stacked procedures (primary-only) with stacked
// procedure rows. Each row includes a per-procedure amount: for stacked
// rows prefer ap.agreed_price, otherwise fallback to appointment.total_amount.
$baseDateBranch = "$dateWhere $branchWhere";

$detailSql = "SELECT t.appoid, t.appodate, t.appointment_time, t.patient, t.dentist, p.procedure_name, t.proc_amount, t.payment_status, t.branch\n"
    . "FROM (\n"
    . "  SELECT a.appoid, a.appodate, a.appointment_time, pt.pname AS patient, d.docname AS dentist, a.procedure_id AS pid, a.total_amount AS proc_amount, a.payment_status, COALESCE(b.name,'(Unassigned)') AS branch\n"
    . "    FROM appointment a LEFT JOIN appointment_procedures ap ON a.appoid = ap.appointment_id LEFT JOIN patient pt ON a.pid = pt.pid LEFT JOIN doctor d ON a.docid = d.docid LEFT JOIN branches b ON b.id = COALESCE(a.branch_id, d.branch_id)\n"
    . "    WHERE ap.id IS NULL " . $baseDateBranch . "\n"
    . "  UNION ALL\n"
    . "  SELECT a.appoid, a.appodate, a.appointment_time, pt.pname AS patient, d.docname AS dentist, ap.procedure_id AS pid, IFNULL(ap.agreed_price, a.total_amount) AS proc_amount, a.payment_status, COALESCE(b.name,'(Unassigned)') AS branch\n"
    . "    FROM appointment_procedures ap JOIN appointment a ON ap.appointment_id = a.appoid LEFT JOIN patient pt ON a.pid = pt.pid LEFT JOIN doctor d ON a.docid = d.docid LEFT JOIN branches b ON b.id = COALESCE(a.branch_id, d.branch_id)\n"
    . "    WHERE 1 " . $baseDateBranch . "\n"
    . ") t JOIN procedures p ON p.procedure_id = t.pid";

// Determine ORDER BY for the unioned detail query (map to t.* / p.procedure_name)
$detailOrder = 't.appodate ASC';
switch ($sort) {
    case 'date_desc': $detailOrder = 't.appodate DESC'; break;
    case 'amount': $detailOrder = 't.proc_amount DESC'; break;
    case 'branch': $detailOrder = "t.branch ASC, t.appodate ASC"; break;
    case 'procedure': $detailOrder = "p.procedure_name ASC, t.appodate ASC"; break;
    default: $detailOrder = 't.appodate ASC';
}

// Sorting: default to date order for CSV exports (even for restricted admins)
// so detailed rows are ordered chronologically. Use mapped $detailOrder
// where a different sort was explicitly requested.
if (isset($_GET['sort']) && $_GET['sort'] === 'procedure') {
    // if caller explicitly requested procedure sort, apply it
    $detailSql .= ' ORDER BY p.procedure_name ASC, t.appodate ASC, t.appointment_time ASC';
} else {
    // default to date-based ordering
    $detailSql .= ' ORDER BY t.appodate ASC, t.appointment_time ASC';
}

$detailsRes = $database->query($detailSql);

header('Content-Type: text/csv; charset=utf-8');
// build filename (include branch slug when restricted)
$branchSlug = '';
if (!empty($restrictedBranchId) && !empty($branchName)) {
    $branchSlug = '_' . preg_replace('/[^a-z0-9\-_]/', '', strtolower(str_replace(' ', '_', $branchName)));
} elseif (!empty($restrictedBranchId)) {
    // fallback to an id-based slug when branch name couldn't be resolved
    $branchSlug = '_branch_' . $restrictedBranchId;
}
$fn = 'production_report' . $branchSlug . '_' . date('Ymd_His') . '.csv';
header('Content-Disposition: attachment; filename=' . $fn);
$out = fopen('php://output','w');

// Summary section
fputcsv($out, ['SUMMARY']);
fputcsv($out, ['Period Start', $startDate ?: '--']);
fputcsv($out, ['Period End', $endDate ?: '--']);
fputcsv($out, ['Selected Year (Monthly)', $year]);
if ($restrictedBranchId > 0) {
    fputcsv($out, ['Branch', $branchName ?: ('#' . $restrictedBranchId)]);
}
fputcsv($out, ['Total Production (Paid)', number_format($totalRevenuePaid,2,'.','')]);
fputcsv($out, ['Paid Appointments', $paidCount]);
fputcsv($out, ['All Appointments', $allCount]);
fputcsv($out, []);
// Production by Branch (only for unrestricted admins)
if ($restrictedBranchId == 0) {
    fputcsv($out, ['PRODUCTION BY BRANCH']);
    fputcsv($out, ['Branch','Production (PHP)']);
    foreach ($branchData as $b) {
        fputcsv($out, [$b['branch'], number_format($b['amt'],2,'.','')]);
    }
    fputcsv($out, []);
}

// Monthly Production
fputcsv($out, ['MONTHLY PRODUCTION','Year '.$year]);
fputcsv($out, ['Month','Production (PHP)']);
for ($m=1;$m<=12;$m++) {
    $amt = isset($monthData[$m]) ? (float)$monthData[$m]['amt'] : 0;
    fputcsv($out, [date('M', mktime(0,0,0,$m,1,$year)), number_format($amt,2,'.','')]);
}
fputcsv($out, []);

// Daily Production
fputcsv($out, ['DAILY PRODUCTION']);
fputcsv($out, ['Date','Production (PHP)']);
foreach ($dailyData as $d) {
    fputcsv($out, [$d['d'], number_format($d['amt'],2,'.','')]);
}
fputcsv($out, []);

// Top Procedures
fputcsv($out, ['TOP PROCEDURES']);
fputcsv($out, ['Procedure','Count','Production (PHP)']);
foreach ($topProcedures as $p) {
    fputcsv($out, [$p['procedure_name'], $p['cnt'], number_format($p['amount'],2,'.','')]);
}
fputcsv($out, []);

// Detailed Appointments
fputcsv($out, ['DETAILED APPOINTMENTS','Sorted: '.$sort]);
fputcsv($out, ['App ID','Date','Time','Patient','Dentist','Procedure','Procedure Amount (PHP)','Payment Status','Branch']);
if ($detailsRes && $detailsRes->num_rows>0) {
    while ($r = $detailsRes->fetch_assoc()) {
        $procName = $r['procedure_name'] ?? '';
        $procAmt = isset($r['proc_amount']) ? (float)$r['proc_amount'] : 0.0;
        fputcsv($out, [
            $r['appoid'],
            $r['appodate'],
            $r['appointment_time'],
            $r['patient'],
            $r['dentist'],
            $procName,
            number_format($procAmt,2,'.',''),
            $r['payment_status'],
            $r['branch']
        ]);
    }
}

fclose($out);
exit;
