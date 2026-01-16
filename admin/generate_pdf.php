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

// Also accept filter & context params (optional)
$procedure_id = isset($_POST['procedure_id']) ? intval($_POST['procedure_id']) : 0;
$date_from = isset($_POST['date_from']) ? trim($_POST['date_from']) : '';
$date_to = isset($_POST['date_to']) ? trim($_POST['date_to']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : '';
$search = isset($_POST['search']) ? trim($_POST['search']) : '';
$sort = isset($_POST['sort']) ? trim($_POST['sort']) : '';
// Branch scoping: prefer explicit POST branch_id; fallback to restricted session
$branch_id = 0;
if (isset($_POST['branch_id']) && is_numeric($_POST['branch_id'])) {
    $branch_id = intval($_POST['branch_id']);
}
if ($branch_id === 0 && isset($_SESSION['restricted_branch_id']) && is_numeric($_SESSION['restricted_branch_id'])) {
    $branch_id = intval($_SESSION['restricted_branch_id']);
}

// Validate inputs
if (empty($report_type)) {
    die("Invalid report type!");
}

// NOTE: allow empty selected_ids when generating for filtered results

// Prepare data based on report type
switch ($report_type) {
    case 'appointments':
        // If selected_ids provided, use them; otherwise allow filters (procedure_id, date_from, date_to, status, search)
        if (!empty($selected_ids)) {
            $query = "SELECT a.*, p.pname, d.docname, COALESCE(b.name,'(Unassigned)') AS branch, pr.procedure_name 
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
                 LEFT JOIN branches b ON d.branch_id = b.id
                 LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id";
            $where = [];
            $where[] = "a.appoid IN (" . implode(',', array_map('intval', $selected_ids)) . ")";
            if ($branch_id > 0) {
                $where[] = "d.branch_id = " . $branch_id;
            }
            if (!empty($where)) {
                $query .= " WHERE " . implode(" AND ", $where);
            }
            $query .= " ORDER BY a.appodate DESC";
        } else {
            // Build filtered query
            $procedure_id = isset($_POST['procedure_id']) ? intval($_POST['procedure_id']) : 0;
            $date_from = isset($_POST['date_from']) ? trim($_POST['date_from']) : '';
            $date_to = isset($_POST['date_to']) ? trim($_POST['date_to']) : '';
            $status = isset($_POST['status']) ? trim($_POST['status']) : '';
            $search = isset($_POST['search']) ? trim($_POST['search']) : '';

              $baseQuery = "SELECT a.*, p.pname, d.docname, COALESCE(b.name,'(Unassigned)') AS branch, pr.procedure_name 
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
                  LEFT JOIN branches b ON d.branch_id = b.id
                 LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id";

            $where = [];
            if (!empty($status) && $status !== 'all') {
                $where[] = "a.status = '" . $database->real_escape_string($status) . "'";
            }
            if (!empty($search)) {
                $st = $database->real_escape_string($search);
                $where[] = "(p.pname LIKE '%$st%' OR d.docname LIKE '%$st%' OR pr.procedure_name LIKE '%$st%')";
            }
            if (!empty($procedure_id)) {
                $procId = intval($procedure_id);
                // Match only the appointment's primary procedure_id (exclude stacked procedures)
                $where[] = "a.procedure_id = $procId";
            }
            if (!empty($date_from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
                $df = $database->real_escape_string($date_from);
                $where[] = "a.appodate >= '$df'";
            }
            if (!empty($date_to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
                $dt = $database->real_escape_string($date_to);
                $where[] = "a.appodate <= '$dt'";
            }
            if ($branch_id > 0) {
                $where[] = "d.branch_id = " . $branch_id;
            }

            if (!empty($where)) {
                $baseQuery .= " WHERE " . implode(" AND ", $where);
            }

            $query = $baseQuery . " ORDER BY a.appodate DESC, a.appointment_time DESC";
        }
        $headers = ['Patient', 'Dentist', 'Branch', 'Date & Time', 'Procedure', 'Status'];
        $columns = ['pname', 'docname', 'branch', 'appodate', 'procedure_name', 'status'];
        $title = "Appointment Archive Report";
        break;

    case 'dentists':
        $query = "SELECT d.*, COALESCE(b.name,'(Unassigned)') AS branch FROM doctor d LEFT JOIN branches b ON d.branch_id = b.id";
        $where = [];
        if (!empty($selected_ids)) {
            $where[] = "d.docid IN (" . implode(',', array_map('intval', $selected_ids)) . ")";
        }
        if ($branch_id > 0) {
            $where[] = "d.branch_id = " . $branch_id;
        }
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        $query .= " ORDER BY d.docid DESC";
        $headers = ['Name', 'Email', 'Branch', 'Phone', 'Status'];
        $columns = ['docname', 'docemail', 'branch', 'doctel', 'status'];
        $title = "Dentists Report";
        break;

    case 'patients':
        $query = "SELECT p.*, COALESCE(b.name,'(Unassigned)') AS branch FROM patient p LEFT JOIN branches b ON p.branch_id = b.id";
        $where = [];
        if (!empty($selected_ids)) {
            $where[] = "p.pid IN (" . implode(',', array_map('intval', $selected_ids)) . ")";
        }
        if ($branch_id > 0) {
            $where[] = "p.branch_id = " . $branch_id;
        }
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        $query .= " ORDER BY p.pid DESC";
        $headers = ['Name', 'Email', 'Branch', 'Address', 'Status'];
        $columns = ['pname', 'pemail', 'branch', 'paddress', 'status'];
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

// Build a short description of the report (sort, procedure, timeframe, status/search)
$descParts = [];
// Sort description
if (!empty($sort)) {
    $descParts[] = 'Sort: ' . (strtoupper($sort) === 'DESC' ? 'Newest' : 'Oldest');
}
// Procedure name
$procLabel = 'All Procedures';
if (!empty($procedure_id)) {
    $procRes = $database->query('SELECT procedure_name FROM procedures WHERE procedure_id=' . intval($procedure_id) . ' LIMIT 1');
    if ($procRes && $prow = $procRes->fetch_assoc()) {
        $procLabel = $prow['procedure_name'];
    }
}
$descParts[] = 'Procedure: ' . $procLabel;
// Time frame
if (!empty($date_from) && !empty($date_to)) {
    // format dates
    $formattedFrom = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) ? date('F j, Y', strtotime($date_from)) : $date_from);
    $formattedTo = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to) ? date('F j, Y', strtotime($date_to)) : $date_to);
    $descParts[] = 'Period: ' . $formattedFrom . ' — ' . $formattedTo;
} elseif (!empty($date_from)) {
    $formattedFrom = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) ? date('F j, Y', strtotime($date_from)) : $date_from);
    $descParts[] = 'From: ' . $formattedFrom;
} elseif (!empty($date_to)) {
    $formattedTo = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to) ? date('F j, Y', strtotime($date_to)) : $date_to);
    $descParts[] = 'Up to: ' . $formattedTo;
} else {
    $descParts[] = 'Period: All time';
}
// Status
if (!empty($status) && $status !== 'all') {
    $descParts[] = 'Status: ' . ucfirst($status);
}
// Search term
if (!empty($search)) {
    $descParts[] = 'Search: "' . $database->real_escape_string($search) . '"';
}
// Selection summary
if (!empty($selected_ids)) {
    $descParts[] = 'Selection: ' . count($selected_ids) . ' selected';
} else {
    $descParts[] = 'Selection: Filtered results';
    // Add branch descriptor to description parts
    if ($branch_id > 0) {
        $branchDesc = 'Branch: #' . $branch_id;
        $brRes2 = $database->query('SELECT name FROM branches WHERE id=' . $branch_id . ' LIMIT 1');
        if ($brRes2 && $brRow2 = $brRes2->fetch_assoc()) {
            $branchDesc = 'Branch: ' . $brRow2['name'];
        }
        array_unshift($descParts, $branchDesc);
    }
}

// Create PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('IHeartDentistDC');
$pdf->SetTitle($title);
// Build header string with optional branch label
$headerRight = date('Y-m-d H:i:s');
if ($branch_id > 0) {
    $branchLabel = 'Branch: #' . $branch_id;
    $brRes = $database->query('SELECT name FROM branches WHERE id=' . $branch_id . ' LIMIT 1');
    if ($brRes && $brRow = $brRes->fetch_assoc()) {
        $branchLabel = 'Branch: ' . $brRow['name'];
    }
    $headerRight = $branchLabel . ' | ' . $headerRight;
}
$pdf->SetHeaderData('', 0, $title, $headerRight);
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
$html .= '<p style="font-size:12px;color:#555;margin-bottom:12px;">' . htmlspecialchars(implode(' | ', $descParts)) . '</p>';
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
