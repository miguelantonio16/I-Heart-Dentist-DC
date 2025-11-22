<?php
// export_pdf.php - generate a printable summary PDF using TCPDF
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
    $dateWhere = " AND DATE(appodate) BETWEEN '$s' AND '$e'";
} elseif ($startDate) {
    $s = $database->real_escape_string($startDate);
    $dateWhere = " AND DATE(appodate) >= '$s'";
} elseif ($endDate) {
    $e = $database->real_escape_string($endDate);
    $dateWhere = " AND DATE(appodate) <= '$e'";
}

// compute totals
$totRes = $database->query("SELECT IFNULL(SUM(total_amount),0) as tot FROM appointment WHERE payment_status='paid' " . $dateWhere);
$tot = $totRes ? $totRes->fetch_assoc()['tot'] : 0;

$countRes = $database->query("SELECT COUNT(*) as cnt FROM appointment WHERE 1 " . $dateWhere);
$cnt = $countRes ? $countRes->fetch_assoc()['cnt'] : 0;

// fetch top procedures
$topRes = $database->query("SELECT p.procedure_name, COUNT(*) as cnt, IFNULL(SUM(a.total_amount),0) as amount FROM appointment a JOIN procedures p ON a.procedure_id = p.procedure_id WHERE a.payment_status='paid' " . $dateWhere . " GROUP BY a.procedure_id ORDER BY cnt DESC LIMIT 8");
$top = [];
if ($topRes) while ($r=$topRes->fetch_assoc()) $top[] = $r;

// Use TCPDF
$tcpdfPath = __DIR__ . '/../../tcpdf/tcpdf.php';
if (!file_exists($tcpdfPath)) die("Missing TCPDF: $tcpdfPath");
require_once $tcpdfPath;

$pdf = new TCPDF();
$pdf->SetCreator('IHeartDentistDC');
$pdf->SetAuthor('I Heart Dentist Dental Clinic');
$pdf->SetTitle('Financial Summary');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->AddPage();

$html = '<h1 style="color:#2b7a9b;">I Heart Dentist Dental Clinic</h1>';
$html .= '<h2>Financial Summary</h2>';
$html .= '<p><strong>Period:</strong> ' . ($startDate?htmlspecialchars($startDate):'--') . ' to ' . ($endDate?htmlspecialchars($endDate):'--') . '</p>';
$html .= '<p><strong>Total Revenue (paid):</strong> PHP ' . number_format($tot,2) . '</p>';
$html .= '<p><strong>Total Appointments:</strong> ' . intval($cnt) . '</p>';
$html .= '<h3>Top Procedures</h3>';
$html .= '<table border="1" cellpadding="4"><thead><tr><th>Procedure</th><th>Count</th><th>Revenue</th></tr></thead><tbody>';
foreach ($top as $t) {
    $html .= '<tr><td>'.htmlspecialchars($t['procedure_name']).'</td><td>'.intval($t['cnt']).'</td><td>PHP '.number_format($t['amount'],2).'</td></tr>';
}
$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('financial_summary_' . date('Ymd_His') . '.pdf', 'I');
exit;

