<?php
require_once('tcpdf/tcpdf.php'); // Ensure TCPDF is included
session_start();

if (!isset($_SESSION["user"])) {
    die("Unauthorized access!");
}

include("../connection.php");

// Get report type and selected IDs
$report_type = $_POST['report_type'] ?? '';
$selected_ids = json_decode($_POST['selected_ids'] ?? '[]', true);

// Validate inputs
if (empty($report_type)) {
    die("Invalid report type!");
}

if (empty($selected_ids)) {
    die("No items selected!");
}

// Prepare data based on report type
switch ($report_type) {
    case 'appointments':
        $query = "SELECT a.*, p.pname, d.docname, pr.procedure_name 
                 FROM (
                     SELECT appoid, pid, docid, appodate, appointment_time, status, procedure_id 
                     FROM appointment 
                     WHERE status = 'completed'
                     UNION ALL
                     SELECT appoid, pid, docid, appodate, appointment_time, status, procedure_id 
                     FROM appointment_archive 
                     WHERE status IN ('cancelled', 'rejected')
                 ) a
                 LEFT JOIN patient p ON a.pid = p.pid
                 LEFT JOIN doctor d ON a.docid = d.docid
                 LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id
                 WHERE a.appoid IN (" . implode(',', array_map('intval', $selected_ids)) . ")
                 ORDER BY a.appodate DESC";
        $headers = ['Patient', 'Dentist', 'Date & Time', 'Procedure', 'Status']; // Added 'Procedure'
        $columns = ['pname', 'docname', 'appodate', 'procedure_name', 'status']; // Added 'procedure_name'
        $title = "Appointment Archive Report";
        break;

    case 'dentists':
        $query = "SELECT * FROM doctor 
                 WHERE docid IN (" . implode(',', array_map('intval', $selected_ids)) . ")
                 ORDER BY docid DESC";
        $headers = ['Name', 'Email', 'Phone', 'Status'];
        $columns = ['docname', 'docemail', 'doctel', 'status'];
        $title = "Dentists Report";
        break;

    case 'patients':
        $query = "SELECT * FROM patient 
                 WHERE pid IN (" . implode(',', array_map('intval', $selected_ids)) . ")
                 ORDER BY pid DESC";
        $headers = ['Name', 'Email', 'Address', 'Status'];
        $columns = ['pname', 'pemail', 'paddress', 'status'];
        $title = "Patients Report";
        break;

    default:
        die("Invalid report type!");
}

// Fetch data
$result = $database->query($query);
if (!$result) {
    die("Error fetching data: " . $database->error);
}

// Create PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('IHeartDentistDC');
$pdf->SetTitle($title);
$pdf->SetHeaderData('', 0, $title, date('Y-m-d H:i:s'));
$pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
$pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->SetFont('helvetica', '', 10);

// Add a page
$pdf->AddPage();

// Create the table content
$html = '<h1>' . $title . '</h1>';
$html .= '<table border="1" cellpadding="5">';
$html .= '<thead><tr>';

// Add headers
foreach ($headers as $header) {
    $html .= '<th><strong>' . htmlspecialchars($header) . '</strong></th>';
}
$html .= '</tr></thead><tbody>';

// Add rows
while ($row = $result->fetch_assoc()) {
    $html .= '<tr>';
    foreach ($columns as $column) {
        $value = $row[$column] ?? 'N/A';
        // Format date and time for appointments
        if ($column === 'appodate' && isset($row['appointment_time'])) {
            $value = htmlspecialchars($row['appodate'] . ' @ ' . substr($row['appointment_time'], 0, 5));
        } else {
            $value = htmlspecialchars($value);
        }
        $html .= '<td>' . $value . '</td>';
    }
    $html .= '</tr>';
}
$html .= '</tbody></table>';

// Write HTML content to PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Output PDF
$pdf->Output('report.pdf', 'D'); // Force download
?>
