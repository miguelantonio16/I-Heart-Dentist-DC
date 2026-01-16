<?php
// Enhanced PDF export: includes summary, revenue by branch/month/day, top procedures, optional detailed appointments
session_start();
if (!isset($_SESSION['user']) || $_SESSION['usertype'] != 'a') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
$connectionPath = __DIR__ . '/../../connection.php';
if (!file_exists($connectionPath)) die('Missing connection file: ' . $connectionPath);
include_once $connectionPath;

// Branch restriction: export only the restricted branch when set
$restrictedBranchId = 0;
$branchWhere = '';
$branchLabel = '';
// Allow explicit branch override via GET (reports page includes &branch_id when restricted)
if (isset($_GET['branch_id']) && is_numeric($_GET['branch_id']) && $_GET['branch_id']) {
    $restrictedBranchId = (int)$_GET['branch_id'];
} elseif (isset($_SESSION['restricted_branch_id']) && is_numeric($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id']) {
    $restrictedBranchId = (int)$_SESSION['restricted_branch_id'];
}
if ($restrictedBranchId > 0) {
    // Restrict strictly to appointments assigned to the given branch
    $branchWhere = " AND a.branch_id = $restrictedBranchId";
    // try to resolve branch name
    $br = $database->query('SELECT name FROM branches WHERE id=' . intval($restrictedBranchId) . ' LIMIT 1');
    if ($br && $br->num_rows) $branchLabel = $br->fetch_assoc()['name'];
}

$startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : null;
$endDate   = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : null;
$year      = isset($_GET['year']) && preg_match('/^\d{4}$/', $_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$includeDetails = isset($_GET['details']) && $_GET['details'] === '1';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_asc';

$dateWhere = '';
if ($startDate && $endDate) {
    $s = $database->real_escape_string($startDate); $e = $database->real_escape_string($endDate);
    $dateWhere = " AND DATE(a.appodate) BETWEEN '$s' AND '$e'";
} elseif ($startDate) {
    $s = $database->real_escape_string($startDate); $dateWhere = " AND DATE(a.appodate) >= '$s'";
} elseif ($endDate) {
    $e = $database->real_escape_string($endDate); $dateWhere = " AND DATE(a.appodate) <= '$e'";
}

// Totals
$totRes = $database->query("SELECT IFNULL(SUM(a.total_amount),0) AS tot FROM appointment a LEFT JOIN doctor d ON a.docid=d.docid WHERE a.payment_status='paid' $dateWhere $branchWhere");
$totalRevenuePaid = $totRes ? (float)$totRes->fetch_assoc()['tot'] : 0;
$paidCntRes = $database->query("SELECT COUNT(*) AS cnt FROM appointment a LEFT JOIN doctor d ON a.docid=d.docid WHERE a.payment_status='paid' $dateWhere $branchWhere");
$paidCount = $paidCntRes ? (int)$paidCntRes->fetch_assoc()['cnt'] : 0;
$allCntRes = $database->query("SELECT COUNT(*) AS cnt FROM appointment a LEFT JOIN doctor d ON a.docid=d.docid WHERE 1 $dateWhere $branchWhere");
$allCount = $allCntRes ? (int)$allCntRes->fetch_assoc()['cnt'] : 0;

// Revenue by branch
$branchSql = "SELECT COALESCE(b.name,'(Unassigned)') AS branch, IFNULL(SUM(a.total_amount),0) AS amt
              FROM appointment a
              LEFT JOIN doctor d ON a.docid=d.docid
              LEFT JOIN branches b ON b.id = COALESCE(a.branch_id, d.branch_id)
              WHERE a.payment_status='paid' $dateWhere $branchWhere
              GROUP BY branch ORDER BY amt DESC";
$branches = [];
if ($brRes = $database->query($branchSql)) while ($r=$brRes->fetch_assoc()) $branches[] = $r;

// Revenue by month (year)
$monthSql = "SELECT MONTH(a.appodate) AS m, IFNULL(SUM(a.total_amount),0) AS amt FROM appointment a LEFT JOIN doctor d ON a.docid=d.docid WHERE a.payment_status='paid' AND YEAR(a.appodate)=$year $branchWhere GROUP BY MONTH(a.appodate) ORDER BY m ASC";
$months = array_fill(1,12,['m'=>null,'amt'=>0]);
if ($moRes = $database->query($monthSql)) while ($r=$moRes->fetch_assoc()) $months[(int)$r['m']] = $r;

// Revenue by day
$dailySql = "SELECT DATE(a.appodate) AS d, IFNULL(SUM(a.total_amount),0) AS amt FROM appointment a LEFT JOIN doctor d ON a.docid=d.docid WHERE a.payment_status='paid' $dateWhere $branchWhere GROUP BY DATE(a.appodate) ORDER BY d ASC";
$days = [];
if ($dyRes = $database->query($dailySql)) while ($r=$dyRes->fetch_assoc()) $days[] = $r;

// Top procedures: include both primary procedure (appointment.procedure_id)
// and stacked procedures (appointment_procedures) so multi-procedure
// appointments are counted properly.
$top = [];
$apptDocFilter = '';
if (!empty($branchWhere)) {
    // branchWhere begins with ' AND a.branch_id = ...' so it's safe to reuse in aliased queries
    $apptDocFilter = $branchWhere;
}

// Build top procedures using per-procedure agreed_price when available
$topSql = "SELECT p.procedure_name, SUM(t.cnt) AS cnt, SUM(t.amount) AS amount FROM (\n" .
    "  SELECT ap.procedure_id AS pid, COUNT(*) AS cnt, IFNULL(SUM(ap.agreed_price),0) AS amount FROM appointment_procedures ap JOIN appointment a ON ap.appointment_id = a.appoid LEFT JOIN doctor d ON a.docid=d.docid WHERE a.payment_status='paid' " . $dateWhere . " " . $apptDocFilter . " GROUP BY ap.procedure_id\n" .
    "  UNION ALL\n" .
    "  SELECT a.procedure_id AS pid, COUNT(*) AS cnt, IFNULL(SUM(a.total_amount),0) AS amount FROM appointment a LEFT JOIN appointment_procedures ap ON a.appoid = ap.appointment_id LEFT JOIN doctor d ON a.docid=d.docid WHERE a.payment_status='paid' AND ap.id IS NULL " . $dateWhere . " " . $apptDocFilter . " GROUP BY a.procedure_id\n" .
") t JOIN procedures p ON p.procedure_id = t.pid GROUP BY p.procedure_id ORDER BY amount DESC";

if ($tpRes = $database->query($topSql)) while ($r=$tpRes->fetch_assoc()) $top[] = $r;

// Sorting for aggregated sections (branches, days, top) based on $sort
if ($sort === 'amount') {
    usort($branches, function($a,$b){ return $b['amt'] <=> $a['amt']; });
    usort($days, function($a,$b){ return $b['amt'] <=> $a['amt']; });
    usort($top, function($a,$b){ return $b['amount'] <=> $a['amount']; });
} elseif ($sort === 'branch') {
    usort($branches, function($a,$b){ return strcasecmp($a['branch'],$b['branch']); });
} elseif ($sort === 'date_desc') {
    usort($days, function($a,$b){ return strcmp($b['d'],$a['d']); });
} elseif ($sort === 'date_asc') {
    usort($days, function($a,$b){ return strcmp($a['d'],$b['d']); });
} elseif ($sort === 'procedure') {
    usort($top, function($a,$b){ return strcasecmp($a['procedure_name'],$b['procedure_name']); });
}

// Details (optional)
$orderBy = 'a.appodate ASC';
switch ($sort) {
    case 'date_desc': $orderBy='a.appodate DESC'; break;
    case 'amount': $orderBy='a.total_amount DESC'; break;
    case 'branch': $orderBy='branch ASC, a.appodate ASC'; break;
    case 'procedure': $orderBy='pr.procedure_name ASC, a.appodate ASC'; break;
    default: $orderBy='a.appodate ASC';
}
$details = [];
if ($includeDetails) {
    // Build a row-per-procedure listing by unioning primary procedure rows
    // with stacked procedures. This avoids comma-separated cells and allows
    // per-procedure sorting. We keep appointment-level amounts on each
    // procedure row since agreed_price may vary; detailed per-procedure
    // prices are not stored on appointment table for primary procedure,
    // but this keeps the exported layout simple.
    $baseDateBranch = "$dateWhere $branchWhere";

        $detailSql = "SELECT t.appoid, t.appodate, t.appointment_time, t.patient, t.dentist, p.procedure_name, t.total_amount, t.payment_status, t.branch"
                    . "\nFROM ("
                    . "\n  SELECT a.appoid, a.appodate, a.appointment_time, p.pname AS patient, d.docname AS dentist, a.procedure_id AS pid, a.total_amount, a.payment_status, COALESCE(b.name,'(Unassigned)') AS branch"
                    . "\n    FROM appointment a LEFT JOIN patient p ON a.pid=p.pid LEFT JOIN doctor d ON a.docid=d.docid LEFT JOIN branches b ON b.id = COALESCE(a.branch_id, d.branch_id)"
                    . "\n    WHERE 1 " . $baseDateBranch
                    . "\n  UNION ALL"
                    . "\n  SELECT a.appoid, a.appodate, a.appointment_time, p.pname AS patient, d.docname AS dentist, ap.procedure_id AS pid, a.total_amount, a.payment_status, COALESCE(b.name,'(Unassigned)') AS branch"
                    . "\n    FROM appointment_procedures ap JOIN appointment a ON ap.appointment_id = a.appoid LEFT JOIN patient p ON a.pid=p.pid LEFT JOIN doctor d ON a.docid=d.docid LEFT JOIN branches b ON b.id = COALESCE(a.branch_id, d.branch_id)"
                    . "\n    WHERE 1 " . $baseDateBranch
                    . "\n) t JOIN procedures p ON p.procedure_id = t.pid";

    // If restricted adminbacoor, sort by procedure then date for Bacoor-only view
    $adminSortCondition = '';
    if (isset($_SESSION['user']) && in_array($_SESSION['user'], ['adminbacoor@edoc.com','adminmakati@edoc.com']) && $restrictedBranchId > 0) {
        $adminSortCondition = ' ORDER BY p.procedure_name ASC, t.appodate ASC, t.appointment_time ASC';
    } else {
        $adminSortCondition = ' ORDER BY ' . $orderBy;
    }

    $detailSql .= $adminSortCondition;
    if ($dtRes=$database->query($detailSql)) while ($r=$dtRes->fetch_assoc()) $details[]=$r;
}

// TCPDF init
$tcpdfPath = __DIR__ . '/../../tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) die('Missing TCPDF: ' . $tcpdfPath);
require_once $tcpdfPath;
$pdf = new TCPDF();
$pdf->SetCreator('IHeartDentistDC');
$pdf->SetAuthor('I Heart Dentist Dental Clinic');
$pdf->SetTitle('Production Report');
$pdf->setPrintHeader(false); $pdf->setPrintFooter(false); $pdf->AddPage();

$generatedOn = date('M j, Y H:i');
$html = '<h1 style="color:#2b7a9b;">I Heart Dentist Dental Clinic</h1>';
$html .= '<h2>Production Report</h2>';
$html .= '<p><strong>Period:</strong> ' . ($startDate?htmlspecialchars($startDate):'--') . ' to ' . ($endDate?htmlspecialchars($endDate):'--') . '</p>';
$html .= '<p><strong>Selected Year (Monthly):</strong> ' . $year . '</p>';

// If restricted to a branch, show branch name in header
if (!empty($branchLabel)) {
    $html .= '<p><strong>Branch:</strong> ' . htmlspecialchars($branchLabel) . '</p>';
}

// Brief description of report contents to appear at the top of the PDF
$html .= '<p style="font-size:12px;color:#333;">Production summary for the selected period: totals, branch/month/day breakdowns, and top procedures.';
if ($includeDetails) {
    $html .= ' Includes detailed appointments.';
}
$html .= '</p>';

$html .= '<p style="font-size:11px;color:#666;">Generated: ' . $generatedOn . '</p>';

$html .= '<p><strong>Total Production (Paid):</strong> PHP ' . number_format($totalRevenuePaid,2) . '<br><strong>Paid Appointments:</strong> ' . $paidCount . '<br><strong>All Appointments:</strong> ' . $allCount . '</p>';

// Branch table (only show for unrestricted admins)
if ($restrictedBranchId == 0) {
    $html .= '<h3>Production by Branch</h3><table border="1" cellpadding="4"><thead><tr><th>Branch</th><th>Production (PHP)</th></tr></thead><tbody>'; 
    foreach ($branches as $b) { $html .= '<tr><td>'.htmlspecialchars($b['branch']).'</td><td style="text-align:right;">'.number_format($b['amt'],2).'</td></tr>'; } 
    $html .= '</tbody></table>';
}

// Month table

$html .= '<h3>Monthly Production (' . $year . ')</h3><table border="1" cellpadding="4"><thead><tr><th>Month</th><th>Production (PHP)</th></tr></thead><tbody>'; 
for ($m=1;$m<=12;$m++){ $amt = isset($months[$m])?(float)$months[$m]['amt']:0; $html .= '<tr><td>'.date('M', mktime(0,0,0,$m,1,$year)).'</td><td style="text-align:right;">'.number_format($amt,2).'</td></tr>'; } 
$html .= '</tbody></table>';

// Day table

$html .= '<h3>Daily Production</h3><table border="1" cellpadding="4"><thead><tr><th>Date</th><th>Production (PHP)</th></tr></thead><tbody>'; 
foreach ($days as $d){ $html .= '<tr><td>'.htmlspecialchars($d['d']).'</td><td style="text-align:right;">'.number_format($d['amt'],2).'</td></tr>'; } 
$html .= '</tbody></table>';

// Top procedures

$html .= '<h3>Top Procedures</h3><table border="1" cellpadding="4"><thead><tr><th>Procedure</th><th>Count</th><th>Production (PHP)</th></tr></thead><tbody>'; 
foreach ($top as $t){ $html .= '<tr><td>'.htmlspecialchars($t['procedure_name']).'</td><td>'.intval($t['cnt']).'</td><td style="text-align:right;">'.number_format($t['amount'],2).'</td></tr>'; } 
$html .= '</tbody></table>';

// Detailed appointments (optional)
if ($includeDetails) {
    $html .= '<h3>Detailed Appointments (Sorted: '.htmlspecialchars($sort).')</h3><table border="1" cellpadding="3" cellspacing="0"><thead><tr><th>ID</th><th>Date</th><th>Time</th><th>Patient</th><th>Dentist</th><th>Procedure</th><th>Amount (PHP)</th><th>Status</th><th>Branch</th></tr></thead><tbody>';
    foreach ($details as $d) {
        $procName = htmlspecialchars($d['procedure_name'] ?? ($d['primary_procedure'] ?? ''));
        $amt = isset($d['total_amount']) ? (float)$d['total_amount'] : 0.0;
        $html .= '<tr><td>'.$d['appoid'].'</td><td>'.htmlspecialchars($d['appodate']).'</td><td>'.htmlspecialchars($d['appointment_time']).'</td><td>'.htmlspecialchars($d['patient']).'</td><td>'.htmlspecialchars($d['dentist']).'</td><td>'. $procName .'</td><td style="text-align:right;">'.number_format($amt,2).'</td><td>'.htmlspecialchars($d['payment_status']).'</td><td>'.htmlspecialchars($d['branch']).'</td></tr>';
    }
    if (!$details) $html .= '<tr><td colspan="9">No appointments found for this period.</td></tr>';
    $html .= '</tbody></table>';
}

$pdf->writeHTML($html, true, false, true, false, '');
$branchSlug = '';
if (!empty($restrictedBranchId) && !empty($branchLabel)) {
    $branchSlug = '_' . preg_replace('/[^a-z0-9\-_]/', '', strtolower(str_replace(' ', '_', $branchLabel)));
}
$fn = 'production_report' . $branchSlug . '_' . date('Ymd_His') . '.pdf';
$pdf->Output($fn, 'I');
exit;

