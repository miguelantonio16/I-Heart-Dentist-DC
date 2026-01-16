<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user"])) {
    header("location: login.php");
    exit();
}

include("../connection.php");

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    // Branch restriction
    $restrictedBranchId = isset($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id'] ? (int)$_SESSION['restricted_branch_id'] : 0;
    // Detect whether appointment_archive stores branch_id (schema can vary)
    $archiveHasBranch = false;
    try {
        $colCheck = $database->query("SHOW COLUMNS FROM appointment_archive LIKE 'branch_id'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $archiveHasBranch = true;
        }
    } catch (Throwable $e) {
        $archiveHasBranch = false;
    }
    
    $type = $_GET['type'] ?? 'appointments';
    $page = max(1, intval($_GET['page'] ?? 1));
    $rowsPerPage = intval($_GET['rows_per_page'] ?? 10);
    $statusFilter = $_GET['status'] ?? 'all';
    $sortOrder = in_array($_GET['sort'] ?? 'DESC', ['ASC', 'DESC']) ? $_GET['sort'] : 'DESC';

    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $procedureFilter = isset($_GET['procedure_id']) ? intval($_GET['procedure_id']) : 0;
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    // Query setup
    switch ($type) {
        case 'appointments':
            // Include stacked procedures via appointment_procedures and aggregate names
            // Select branch_id from appointment/appointment_archive when available so we can filter by booked branch
            $baseQuery = "SELECT 
                    a.appoid, a.pid, a.docid, a.appodate, a.appointment_time, a.status, a.procedure_id, a.branch_id,
                    p.pname, d.docname,
                    COALESCE(b.name,'(Unassigned)') AS branch,
                    pr.procedure_name AS primary_procedure,
                    GROUP_CONCAT(DISTINCT p2.procedure_name ORDER BY p2.procedure_name SEPARATOR ', ') AS procedures_list
                FROM (
                    SELECT appoid, pid, docid, appodate, appointment_time, status, procedure_id, branch_id
                    FROM appointment 
                    WHERE status = 'completed'
                    UNION ALL
                    SELECT appoid, pid, docid, appodate, appointment_time, status, procedure_id, " . ($archiveHasBranch ? 'branch_id' : 'NULL') . " AS branch_id
                    FROM appointment_archive 
                    WHERE status IN ('cancelled', 'rejected')
                ) a
                LEFT JOIN patient p ON a.pid = p.pid
                LEFT JOIN doctor d ON a.docid = d.docid
                LEFT JOIN branches b ON (COALESCE(a.branch_id, d.branch_id) = b.id)
                LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id
                LEFT JOIN appointment_procedures ap ON a.appoid = ap.appointment_id
                LEFT JOIN procedures p2 ON ap.procedure_id = p2.procedure_id";

            $where = [];
            if ($restrictedBranchId > 0) {
                // Only include appointments whose effective booked branch equals the restricted branch.
                // Effective branch = appointment.branch_id if set, otherwise doctor's branch_id.
                $where[] = "COALESCE(a.branch_id, d.branch_id) = $restrictedBranchId";
            }
            if ($statusFilter !== 'all') {
                $where[] = "a.status = '" . $database->real_escape_string($statusFilter) . "'";
            }
            if (!empty($searchTerm)) {
                $st = $database->real_escape_string($searchTerm);
                $where[] = "(p.pname LIKE '%$st%' OR d.docname LIKE '%$st%' OR pr.procedure_name LIKE '%$st%')";
            }
            // Procedure filter: match primary or any stacked procedures
            if (!empty($procedureFilter) && is_numeric($procedureFilter)) {
                $procId = intval($procedureFilter);
                $where[] = "(a.procedure_id = $procId OR ap.procedure_id = $procId)";
            }
            // Date range filter (appodate is stored as YYYY-MM-DD)
            if (!empty($dateFrom)) {
                $df = $database->real_escape_string($dateFrom);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) {
                    $where[] = "a.appodate >= '$df'";
                }
            }
            if (!empty($dateTo)) {
                $dt = $database->real_escape_string($dateTo);
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
                    $where[] = "a.appodate <= '$dt'";
                }
            }

            if (!empty($where)) {
                $baseQuery .= " WHERE " . implode(" AND ", $where);
            }
            // Group by appointment to enable aggregation of stacked procedures
            $baseQuery .= " GROUP BY a.appoid";

            // Count appointments (distinct appoid) with same filters
            $countQuery = "SELECT COUNT(*) as total FROM (" . str_replace(
                ["SELECT \n                    a.appoid, a.pid, a.docid, a.appodate, a.appointment_time, a.status, a.procedure_id,\n                    p.pname, d.docname,\n                    COALESCE(b.name,'(Unassigned)') AS branch,\n                    pr.procedure_name AS primary_procedure,\n                    GROUP_CONCAT(DISTINCT p2.procedure_name ORDER BY p2.procedure_name SEPARATOR ', ') AS procedures_list"],
                ["SELECT a.appoid"],
                $baseQuery
            ) . ") AS combined";

            // Build order clause to include time so sorting within the same date is deterministic
            $orderColumn = 'a.appodate';
            $orderClause = "a.appodate $sortOrder, a.appointment_time $sortOrder";
            break;
        
        case 'dentists':
            // Build branch list by combining doctor's primary branch and any mapped branches
            $baseQuery = "SELECT d.*, COALESCE(bl.branch_list, '(Unassigned)') AS branch FROM doctor d LEFT JOIN (
                SELECT mb.docid, GROUP_CONCAT(DISTINCT b.name ORDER BY b.name SEPARATOR ', ') AS branch_list
                FROM (
                    SELECT docid, branch_id FROM doctor WHERE branch_id IS NOT NULL
                    UNION ALL
                    SELECT docid, branch_id FROM doctor_branches
                ) mb
                JOIN branches b ON mb.branch_id = b.id
                GROUP BY mb.docid
            ) bl ON bl.docid = d.docid";
            $where = [];
            if ($restrictedBranchId > 0) {
                $where[] = "(d.branch_id = $restrictedBranchId OR d.docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))";
            }
            if ($statusFilter !== 'all') {
                $where[] = "d.status = '" . $database->real_escape_string($statusFilter) . "'";
            }
            if (!empty($searchTerm)) {
                $searchTerm = $database->real_escape_string($searchTerm);
                $where[] = "d.docname LIKE '%$searchTerm%'";
            }
            if (!empty($where)) {
                $baseQuery .= " WHERE " . implode(" AND ", $where);
            }

            $countQuery = "SELECT COUNT(*) FROM doctor d";
            if (!empty($where)) {
                $countQuery .= " WHERE " . implode(" AND ", $where);
            }

            $orderColumn = 'd.docid';
            break;
        
        case 'patients':
            // Build branch list for patients combining primary branch and patient_branches mapping
            $baseQuery = "SELECT p.*, COALESCE(bl.branch_list, '(Unassigned)') AS branch FROM patient p LEFT JOIN (
                SELECT mb.pid, GROUP_CONCAT(DISTINCT b.name ORDER BY b.name SEPARATOR ', ') AS branch_list
                FROM (
                    SELECT pid, branch_id FROM patient WHERE branch_id IS NOT NULL
                    UNION ALL
                    SELECT pid, branch_id FROM patient_branches
                ) mb
                JOIN branches b ON mb.branch_id = b.id
                GROUP BY mb.pid
            ) bl ON bl.pid = p.pid";
            $where = [];
            if ($restrictedBranchId > 0) {
                $where[] = "(p.branch_id = $restrictedBranchId OR EXISTS(SELECT 1 FROM patient_branches pb WHERE pb.pid = p.pid AND pb.branch_id = $restrictedBranchId))";
            }
            if ($statusFilter !== 'all') {
                $where[] = "p.status = '" . $database->real_escape_string($statusFilter) . "'";
            }
            if (!empty($searchTerm)) {
                $searchTerm = $database->real_escape_string($searchTerm);
                $where[] = "p.pname LIKE '%$searchTerm%'";
            }
            if (!empty($where)) {
                $baseQuery .= " WHERE " . implode(" AND ", $where);
            }

            $countQuery = "SELECT COUNT(*) FROM patient p";
            if (!empty($where)) {
                $countQuery .= " WHERE " . implode(" AND ", $where);
            }

            $orderColumn = 'p.pid';
            break;
    }

    // Ensure we have an ORDER clause for all types (appointments sets $orderClause earlier)
    if (!isset($orderClause) || trim($orderClause) === '') {
        // Fallback to ordering by the determined order column and provided sort order
        $orderClause = $orderColumn . ' ' . $sortOrder;
    }

    // Execute queries
    $total = $database->query($countQuery)->fetch_row()[0];
    $totalPages = ceil($total / $rowsPerPage);
    $offset = ($page - 1) * $rowsPerPage;
    $result = $database->query("$baseQuery ORDER BY $orderClause LIMIT $rowsPerPage OFFSET $offset");

    // Build HTML
    $html = '<table class="table"><thead><tr><th class="checkbox-column"><input type="checkbox" class="select-all"></th>';
    switch ($type) {
        case 'appointments': $html .= '<th>Patient</th><th>Dentist</th><th>Procedure</th><th>Branch</th><th>Date & Time</th><th>Status</th>'; break;
        case 'dentists': $html .= '<th>Name</th><th>Email</th><th>Branch</th><th>Phone</th><th>Status</th>'; break;
        case 'patients': $html .= '<th>Name</th><th>Email</th><th>Branch</th><th>Address</th><th>Birthdate</th><th>Status</th>'; break;
    }
    $html .= '</tr></thead><tbody>';

    while ($row = $result->fetch_assoc()) {
        $html .= '<tr><td><input type="checkbox" class="row-checkbox" value="' 
               . ($type === 'appointments' ? $row['appoid'] : ($type === 'dentists' ? $row['docid'] : $row['pid'])) 
               . '"></td>';
        
        switch ($type) {
            case 'appointments':
                // Prefer stacked procedures list; fallback to primary procedure name
                $procDisplay = trim($row['procedures_list'] ?? '') !== '' ? $row['procedures_list'] : ($row['primary_procedure'] ?? 'N/A');
                $branchDisplay = isset($row['branch']) && $row['branch'] !== null ? $row['branch'] : '(Unassigned)';
                $html .= '<td>' . htmlspecialchars($row['pname'] ?? 'N/A') . '</td>'
                    . '<td>' . htmlspecialchars($row['docname'] ?? 'N/A') . '</td>'
                    . '<td>' . htmlspecialchars($procDisplay) . '</td>'
                    . '<td>' . htmlspecialchars($branchDisplay) . '</td>'
                    . '<td>' . htmlspecialchars(($row['appodate'] ?? '') . ' @ ' . substr($row['appointment_time'] ?? '', 0, 5)) . '</td>'
                    . '<td data-status="' . htmlspecialchars($row['status']) . '">' . ucfirst($row['status']) . '</td>';
                break;
            case 'dentists':
                $branchDisplay = isset($row['branch']) && $row['branch'] !== null ? $row['branch'] : '(Unassigned)';
                $html .= '<td>' . htmlspecialchars($row['docname']) . '</td>'
                    . '<td>' . htmlspecialchars($row['docemail']) . '</td>'
                    . '<td>' . htmlspecialchars($branchDisplay) . '</td>'
                    . '<td>' . htmlspecialchars($row['doctel']) . '</td>'
                    . '<td>' . ucfirst($row['status']) . '</td>';
                break;
            case 'patients':
                $branchDisplay = isset($row['branch']) && $row['branch'] !== null ? $row['branch'] : '(Unassigned)';
                $html .= '<td>' . htmlspecialchars($row['pname']) . '</td>'
                    . '<td>' . htmlspecialchars($row['pemail']) . '</td>'
                    . '<td>' . htmlspecialchars($branchDisplay) . '</td>'
                    . '<td>' . htmlspecialchars(substr($row['paddress'] ?? '', 0, 30)) . '</td>'
                    . '<td>' . htmlspecialchars($row['pdob'] ?? 'N/A') . '</td>' 
                    . '<td>' . ucfirst($row['status']) . '</td>';
                break;
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    // Pagination HTML
    $pagination = '<div class="pagination"><span class="pagination-label">' . 
    (($page - 1) * $rowsPerPage + 1) . ' - ' . min($page * $rowsPerPage, $total) . 
    ' of ' . $total . '</span><div class="pagination-buttons">' .
    '<button class="pagination-button prev" ' . ($page <= 1 ? 'disabled' : '') . 
    ' data-page="' . ($page - 1) . '"></button>' .
    '<button class="pagination-button next" ' . ($page >= $totalPages ? 'disabled' : '') . 
    ' data-page="' . ($page + 1) . '"></button>' .
    '</div></div>';

    echo json_encode(['html' => $html, 'pagination' => $pagination]);
    exit();
}

// Restrict counts when admin is limited to a branch
$restrictedBranchId = isset($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id'] ? (int)$_SESSION['restricted_branch_id'] : 0;
// Detect whether appointment_archive stores branch_id (schema can vary)
$archiveHasBranch = false;
try {
    $colCheck = $database->query("SHOW COLUMNS FROM appointment_archive LIKE 'branch_id'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $archiveHasBranch = true;
    }
} catch (Throwable $e) {
    $archiveHasBranch = false;
}

if ($restrictedBranchId > 0) {
    if ($archiveHasBranch) {
        $appointment_count = $database->query(
            "SELECT COUNT(*) FROM (
                SELECT a.appoid FROM appointment a JOIN doctor d ON a.docid = d.docid WHERE a.status = 'completed' AND COALESCE(a.branch_id, d.branch_id) = $restrictedBranchId
                UNION ALL
                SELECT a.appoid FROM appointment_archive a JOIN doctor d ON a.docid = d.docid WHERE a.status IN ('cancelled', 'rejected') AND COALESCE(a.branch_id, d.branch_id) = $restrictedBranchId
            ) AS combined"
        )->fetch_row()[0];
    } else {
        // appointment_archive doesn't have branch info; fall back to counting only active appointment table rows where effective branch equals restricted
        $appointment_count = $database->query(
            "SELECT COUNT(*) FROM (
                SELECT a.appoid FROM appointment a JOIN doctor d ON a.docid = d.docid WHERE a.status = 'completed' AND COALESCE(a.branch_id, d.branch_id) = $restrictedBranchId
            ) AS combined"
        )->fetch_row()[0];
    }

    $dentist_count = $database->query("SELECT COUNT(*) FROM doctor WHERE (branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))")->fetch_row()[0];
    $patient_count = $database->query("SELECT COUNT(*) FROM patient WHERE branch_id = $restrictedBranchId")->fetch_row()[0];
} else {
    $appointment_count = $database->query(
        "SELECT COUNT(*) FROM (
            SELECT appoid FROM appointment WHERE status = 'completed'
            UNION ALL
            SELECT appoid FROM appointment_archive WHERE status IN ('cancelled', 'rejected')
        ) AS combined"
    )->fetch_row()[0];

    $dentist_count = $database->query("SELECT COUNT(*) FROM doctor")->fetch_row()[0];
    $patient_count = $database->query("SELECT COUNT(*) FROM patient")->fetch_row()[0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/responsive-admin.css">
    <title>Archive - IHeartDentistDC</title>
    <style>
        .main-container {
            display: flex;
            min-height: 100vh;
        }
        
        .content-area {
            flex: 1;
            padding: 20px;
            background-color: #f8f9fa;
        }
        
        .main-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
        }
        
        .clear-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #999;
        }
        
        /* Tab styles */
        .tab-container {
            margin: 20px 0;
        }
        
        .tab-button {
            padding: 10px 20px;
            background: #f0f0f0;
            border: none;
            cursor: pointer;
            margin-right: 5px;
            border-radius: 5px;
            transition: all 0.2s ease;
            font-weight: 600;
            color: #303030;
        }
        
        .tab-button:hover {
            background: #e0e0e0;
        }
        
        .tab-button.active {
            background: #6491bb;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Table styles */
        .table-container {
            max-height: 600px; /* Or whatever height you prefer */
            overflow-y: auto;
            position: relative;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .table thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #6491bb;
        }
        
        /* Ensure table takes full width */
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        
        .table th {
            background-color: #84b6e4;
            color: white;
            position: sticky;
            top: 0;
        }
        
        .table tr:hover td {
            background-color:#dae8f6;
        }
        
        /* Checkbox column */
        .checkbox-column {
            width: 30px;
        }
        
        /* Status badges */
        .table td:last-child[data-status="completed"],
        .table td:last-child[data-status="active"] {
            color: #28a745;
        }
        
        .table td:last-child[data-status="cancelled"],
        .table td:last-child[data-status="rejected"],
        .table td:last-child[data-status="inactive"] {
            color: #dc3545;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        
        .pagination-label {
            padding-right: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .pagination-buttons {
            display: flex;
            gap: 8px;
        }
        
        .pagination-button {
            background: none;
            border: none;
            color: #84b6e4;
            font-size: 25px;
            font-weight: 500;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        
        .pagination-button:hover:not(:disabled) {
            background-color: #f0f0f0;
        }
        
        .pagination-button:disabled {
            color: #ccc;
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .pagination-button.prev::before {
            content: "←";
            margin-right: 5px;
        }
        
        .pagination-button.next::after {
            content: "→";
            margin-left: 5px;
        }
        
        /* Table controls */
        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .table-controls-left {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .table-controls-right {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: auto;
        }
        
        .status-filter, .rows-per-page {
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #ced4da;
            background-color: white;
        }
        
        .generate-pdf {
            background-color: #84b6e4;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .generate-pdf:hover {
            background-color:rgb(154, 196, 237);
        }
        
        /* Loading animation */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-message {
            padding: 20px;
            text-align: center;
            color: #666;
        }
        
        .loading-message::before {
            content: "";
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top-color: #007bff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        /* Sort buttons */
        .sort-btn {
            background: none;
            border: none;
            color: #666;
            font-size: 14px;
            cursor: pointer;
            padding: 6px 10px;
            transition: all 0.2s ease;
            position: relative;
        }
        
        .sort-btn:after {
            content: "";
            display: inline-block;
            width: 0;
            height: 0;
            margin-left: 5px;
            vertical-align: middle;
        }
        
        .sort-btn[data-order="DESC"]:after {
            border-top: 4px solid;
            border-right: 4px solid transparent;
            border-left: 4px solid transparent;
        }
        
        .sort-btn[data-order="ASC"]:after {
            border-bottom: 4px solid;
            border-right: 4px solid transparent;
            border-left: 4px solid transparent;
        }
        
        .sort-btn.active {
            color: #6491bb;
            font-weight: 600;
        }
        
        .sort-btn.active:after {
            border-top-color: #6491bb;
            border-bottom-color: #6491bb;
        }
        
        /* Header styles */
        .announcements-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .table-controls {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .table-controls-right {
                margin-left: 0;
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <button class="hamburger-admin" id="hamburgerAdmin" aria-label="Toggle sidebar" aria-controls="adminSidebar" aria-expanded="false">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="main-container">
        <div class="sidebar" id="adminSidebar">
            <div class="sidebar-logo">
                <img src="../Media/Icon/logo.png" alt="IHeartDentistDC Logo">
            </div>

            <div class="user-profile">
                <div class="profile-image">
                    <img src="../Media/Icon/logo.png" alt="Profile" class="profile-img">
                </div>
                <h3 class="profile-name">I Heart Dentist Dental Clinic</h3>
                <p style="color: #777; margin: 0; font-size: 14px; text-align: center;">
                <?php
                    $roleLabel = 'Secretary';
                    if (isset($_SESSION['user'])) {
                        $curr = strtolower($_SESSION['user']);
                        // Super Admin label for the primary admin account
                        if ($curr === 'admin@edoc.com') {
                            $roleLabel = 'Super Admin';
                        } else if (isset($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id']) {
                            $branchLabels = [
                                'adminbacoor@edoc.com' => 'Bacoor',
                                'adminmakati@edoc.com' => 'Makati'
                            ];
                            if (isset($branchLabels[$curr])) {
                                $roleLabel = 'Secretary - ' . $branchLabels[$curr];
                            }
                        }
                    }
                    echo $roleLabel;
                ?>
                </p>
            </div>

            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <img src="../Media/Icon/Blue/home.png" alt="Home" class="nav-icon">
                    <span class="nav-label">Dashboard</span>
                </a>
                <a href="dentist.php" class="nav-item">
                    <img src="../Media/Icon/Blue/dentist.png" alt="Dentist" class="nav-icon">
                    <span class="nav-label">Dentist</span>
                </a>
                <a href="patient.php" class="nav-item">
                    <img src="../Media/Icon/Blue/care.png" alt="Patient" class="nav-icon">
                    <span class="nav-label">Patient</span>
                </a>
                <a href="records.php" class="nav-item">
                    <img src="../Media/Icon/Blue/edit.png" alt="Records" class="nav-icon">
                    <span class="nav-label">Patient Records</span>
                </a>
                <a href="calendar/calendar.php" class="nav-item">
                    <img src="../Media/Icon/Blue/calendar.png" alt="Calendar" class="nav-icon">
                    <span class="nav-label">Calendar</span>
                </a>
                <a href="booking.php" class="nav-item">
                    <img src="../Media/Icon/Blue/booking.png" alt="Booking" class="nav-icon">
                    <span class="nav-label">Booking</span>
                </a>
                <a href="appointment.php" class="nav-item">
                    <img src="../Media/Icon/Blue/appointment.png" alt="Appointment" class="nav-icon">
                    <span class="nav-label">Appointment</span>
                </a>
                <a href="history.php" class="nav-item active">
                    <img src="../Media/Icon/Blue/folder.png" alt="Archive" class="nav-icon">
                    <span class="nav-label">Archive</span>
                </a>
                <a href="reports/financial_reports.php" class="nav-item">
                    <img src="../Media/Icon/Blue/folder.png" alt="Reports" class="nav-icon">
                    <span class="nav-label">Reports</span>
                </a>
                <?php if (empty($_SESSION['restricted_branch_id'])): ?>
                <a href="settings.php" class="nav-item">
                    <img src="../Media/Icon/Blue/settings.png" alt="Settings" class="nav-icon">
                    <span class="nav-label">Settings</span>
                </a>
                <?php endif; ?>
            </div>

            <div class="log-out">
                <a href="logout.php" class="nav-item">
                    <img src="../Media/Icon/Blue/logout.png" alt="Log Out" class="nav-icon">
                    <span class="nav-label">Log Out</span>
                </a>
            </div>
        </div>

        <div class="content-area">
            <div class="container">
                <!-- Search bar -->
                <div class="search-container">
                    <div style="display: flex; width: 100%; position: relative;">
                        <input type="search" id="searchInput" class="search-input"
                            placeholder="Search by patient name, dentist name or procedure">
                        <button type="button" class="clear-btn">×</button>
                    </div>
                </div>

                <!-- Tab container -->
                <div class="tab-container">
                    <h3 class="announcements-title">Archive</h3>
                    <button class="tab-button active" data-type="appointments">Appointments (<?= $appointment_count ?>)</button>
                    <button class="tab-button" data-type="dentists">Dentists (<?= $dentist_count ?>)</button>
                    <button class="tab-button" data-type="patients">Patients (<?= $patient_count ?>)</button>
                    <!-- Legacy sidebar-toggle removed; logo now acts as toggle -->
                </div>

                <!-- Appointments Tab -->
                <div id="appointments-content" class="tab-content active">
                    <div class="table-controls">
                        <div class="table-controls-left">
                            <label><input type="checkbox" class="select-all" data-type="appointments"> Select All</label>
                            <select class="rows-per-page" data-type="appointments">
                                <option value="10">10 rows</option>
                                <option value="20">20 rows</option>
                                <option value="50">50 rows</option>
                                <option value="100">100 rows</option>
                            </select>
                            <div class="sort-controls">
                                <button class="sort-btn active" data-type="appointments" data-order="DESC">Newest</button>
                                <button class="sort-btn" data-type="appointments" data-order="ASC">Oldest</button>
                            </div>
                            <select class="status-filter" data-type="appointments">
                                <option value="all">All</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="rejected">Rejected</option>
                            </select>
                            <?php
                            // Procedure filter dropdown
                            $procRes = $database->query("SELECT procedure_id, procedure_name FROM procedures ORDER BY procedure_name ASC");
                            ?>
                            <select id="procedureFilter" class="rows-per-page" data-type="appointments" style="min-width:180px;">
                                <option value="">All Procedures</option>
                                <?php while ($procRes && $prow = $procRes->fetch_assoc()): ?>
                                    <option value="<?= (int)$prow['procedure_id'] ?>"><?= htmlspecialchars($prow['procedure_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <label style="display:flex;gap:6px;align-items:center;">
                                <span style="font-size:13px;color:#555;">From</span>
                                <input type="date" id="dateFrom" class="form-input" style="padding:6px;border-radius:4px;"> 
                            </label>
                            <label style="display:flex;gap:6px;align-items:center;">
                                <span style="font-size:13px;color:#555;">To</span>
                                <input type="date" id="dateTo" class="form-input" style="padding:6px;border-radius:4px;">
                            </label>
                            <button class="generate-pdf btn-primary" data-type="appointments">Generate PDF</button>
                        </div>
                        
                        <div class="table-controls-right">
                            <div class="pagination" id="appointments-pagination"></div>
                        </div>
                    </div>
                    
                    <div class="table-container" id="appointments-table"><div class="loading-message"></div></div>
                </div>

                <!-- Dentists Tab -->
                <div id="dentists-content" class="tab-content">
                    <div class="table-controls">
                        <div class="table-controls-left">
                            <label><input type="checkbox" class="select-all" data-type="dentists"> Select All</label>
                            <select class="rows-per-page" data-type="dentists">
                                <option value="10">10 rows</option>
                                <option value="20">20 rows</option>
                                <option value="50">50 rows</option>
                                <option value="100">100 rows</option>
                            </select>
                            <div class="sort-controls">
                                <button class="sort-btn active" data-type="dentists" data-order="DESC">Newest</button>
                                <button class="sort-btn" data-type="dentists" data-order="ASC">Oldest</button>
                            </div>
                            <select class="status-filter" data-type="dentists">
                                <option value="all">All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <button class="generate-pdf btn-primary" data-type="dentists">Generate PDF</button>
                        </div>
                        
                        <div class="table-controls-right">
                            <div class="pagination" id="dentists-pagination"></div>
                        </div>
                    </div>
                    
                    <div class="table-container" id="dentists-table"><div class="loading-message">Loading dentists...</div></div>
                </div>

                <!-- Patients Tab -->
                <div id="patients-content" class="tab-content">
                    <div class="table-controls">
                        <div class="table-controls-left">
                            <label><input type="checkbox" class="select-all" data-type="patients"> Select All</label>
                            <select class="rows-per-page" data-type="patients">
                                <option value="10">10 rows</option>
                                <option value="20">20 rows</option>
                                <option value="50">50 rows</option>
                                <option value="100">100 rows</option>
                            </select>
                            <div class="sort-controls">
                                <button class="sort-btn active" data-type="patients" data-order="DESC">Newest</button>
                                <button class="sort-btn" data-type="patients" data-order="ASC">Oldest</button>
                            </div>
                            <select class="status-filter" data-type="patients">
                                <option value="all">All</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <button class="generate-pdf btn-primary" data-type="patients">Generate PDF</button>
                        </div>
                        
                        <div class="table-controls-right">
                            <div class="pagination" id="patients-pagination"></div>
                        </div>
                    </div>
                    
                    <div class="table-container" id="patients-table"><div class="loading-message">Loading patients...</div></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
// Expose restricted branch id (if any) to PDF generator
var RESTRICTED_BRANCH_ID = <?php echo $restrictedBranchId > 0 ? (int)$restrictedBranchId : '0'; ?>;
document.addEventListener('DOMContentLoaded', function () {
    const hamburger = document.getElementById('hamburgerAdmin');
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (hamburger && sidebar && overlay) {
        const closeSidebar = () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('visible');
            hamburger.setAttribute('aria-expanded', 'false');
        };

        const openSidebar = () => {
            sidebar.classList.add('open');
            overlay.classList.add('visible');
            hamburger.setAttribute('aria-expanded', 'true');
        };

        hamburger.addEventListener('click', function () {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        overlay.addEventListener('click', function () {
            closeSidebar();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeSidebar();
        });
    }
});
$(document).ready(function() {
    let currentTab = 'appointments';
    
    // Load table data function
    function loadTableData(type, page = 1) {
        const rowsPerPage = $(`.rows-per-page[data-type="${type}"]`).val();
        const statusFilter = $(`.status-filter[data-type="${type}"]`).val();
        const sortOrder = $(`#${type}-content .sort-btn.active`).data('order') || 'DESC';
        const searchTerm = $('#searchInput').val();
        const procedureId = $('#procedureFilter').val() || '';
        const dateFrom = $('#dateFrom').val() || '';
        const dateTo = $('#dateTo').val() || '';
        
        $.ajax({
            url: window.location.pathname,
            method: 'GET',
            dataType: 'json',
            data: { 
                ajax: 1,
                type: type,
                page: page,
                rows_per_page: rowsPerPage,
                status: statusFilter,
                sort: sortOrder,
                search: searchTerm,
                procedure_id: procedureId,
                date_from: dateFrom,
                date_to: dateTo
            },
            beforeSend: function() {
                $(`#${type}-table`).html('<div class="loading-message">Loading...</div>');
            },
            success: function(response) {
                if (response && response.html && response.pagination) {
                    $(`#${type}-table`).html(response.html);
                    $(`#${type}-pagination`).html(response.pagination);
                    
                    // Rebind pagination buttons
                    $(`#${type}-pagination button`).off('click').on('click', function() {
                        if (!$(this).is(':disabled')) {
                            loadTableData(type, $(this).data('page'));
                        }
                    });
                    
                    // Rebind select all checkbox
                    bindSelectAll(type);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error);
                $(`#${type}-table`).html('<div class="error-message">Error loading data. Please try again.</div>');
            }
        });
    }
    
    // Bind select all functionality
    function bindSelectAll(type) {
        $(`#${type}-content .select-all`).off('change').on('change', function() {
            const isChecked = $(this).is(':checked');
            $(`#${type}-table .row-checkbox`).prop('checked', isChecked);
        });
        
        // Bind individual row checkboxes to update select-all status
        $(`#${type}-table`).on('change', '.row-checkbox', function() {
            const allChecked = $(`#${type}-table .row-checkbox`).length === 
                              $(`#${type}-table .row-checkbox:checked`).length;
            $(`#${type}-content .select-all`).prop('checked', allChecked);
        });
    }
    
    // Generate PDF function
    function generatePDF(type) {
        const selectedIds = [];
        $(`#${type}-table .row-checkbox:checked`).each(function() {
            selectedIds.push($(this).val());
        });
        
        if (selectedIds.length === 0) {
            alert('Please select at least one item to generate PDF');
            return;
        }
        
        // Here you would typically make an AJAX call to your PDF generation endpoint
        // For now, we'll just show a confirmation
        console.log('Generating PDF for', type, 'with IDs:', selectedIds);
        alert(`Preparing to generate PDF for ${selectedIds.length} selected ${type}`);
        
        // Example of how you might implement the actual PDF generation:
        /*
        $.ajax({
            url: 'generate_pdf.php',
            method: 'POST',
            data: {
                type: type,
                ids: selectedIds
            },
            success: function(response) {
                // Handle the PDF download
                window.open(response.pdf_url, '_blank');
            },
            error: function() {
                alert('Error generating PDF');
            }
        });
        */
    }
    
    // Initial load
    loadTableData(currentTab);
    
    // Tab switching
    $('.tab-button').on('click', function() {
        const type = $(this).data('type');
        currentTab = type;
        
        // Update UI
        $('.tab-button').removeClass('active');
        $(this).addClass('active');
        $('.tab-content').removeClass('active');
        $(`#${type}-content`).addClass('active');
        
        // Update placeholder based on tab
        updateSearchPlaceholder(type);
        
        // Load data for this tab
        loadTableData(type);
    });
    
    // Update search placeholder based on tab
    function updateSearchPlaceholder(type) {
        let placeholder = '';
        switch(type) {
            case 'appointments':
                placeholder = 'Search by patient name, dentist name or procedure';
                break;
            case 'dentists':
                placeholder = 'Search by dentist name';
                break;
            case 'patients':
                placeholder = 'Search by patient name';
                break;
        }
        $('#searchInput').attr('placeholder', placeholder);
    }
    
    // Search functionality
    $('#searchInput').on('keyup', function(e) {
        if (e.key === 'Enter') {
            loadTableData(currentTab);
        }
    });
    
    // Clear search
    function clearSearch() {
        $('#searchInput').val('');
        loadTableData(currentTab);
    }
    
    $(document).on('click', '.clear-btn', clearSearch);
    
    // Rows per page change
    $('.rows-per-page').on('change', function() {
        const type = $(this).data('type');
        loadTableData(type);
    });
    
    // Status filter change
    $('.status-filter').on('change', function() {
        const type = $(this).data('type');
        loadTableData(type);
    });
    
    // Sort buttons
    $(document).on('click', '.sort-btn', function() {
        const type = $(this).data('type');
        const sortOrder = $(this).data('order');
        
        // Remove active class from all sort buttons in this tab
        $(`#${type}-content .sort-btn`).removeClass('active');
        
        // Add active class to clicked button
        $(this).addClass('active');
        
        // Reload data with new sort order
        loadTableData(type);
    });
    $(document).on('click', '.select-all', function() {
            const type = $(this).data('type');
            const checked = $(this).prop('checked');
            $(`#${type}-table .row-checkbox`).prop('checked', checked);
        });

        $(document).on('click', '.generate-pdf', function () {
            const type = $(this).data('type');
            const selected = $(`#${type}-table .row-checkbox:checked`).map((i, el) => el.value).get();
            const procedureId = $('#procedureFilter').val() || '';
            const dateFrom = $('#dateFrom').val() || '';
            const dateTo = $('#dateTo').val() || '';

            console.log("Selected IDs:", selected);
            console.log("Report Type:", type);

            // If nothing selected, ask user whether to generate for all filtered results
            if (selected.length === 0) {
                if (!confirm('No rows selected. Generate PDF for all appointments matching the current filters?')) {
                    return;
                }
            }

            const sortVal = $(`#${type}-content .sort-btn.active`).data('order') || '';
            const form = $('<form>', { action: 'generate_pdf.php', method: 'POST' })
                .append($('<input>', { type: 'hidden', name: 'report_type', value: type }))
                .append($('<input>', { type: 'hidden', name: 'selected_ids', value: JSON.stringify(selected) }))
                .append($('<input>', { type: 'hidden', name: 'procedure_id', value: procedureId }))
                .append($('<input>', { type: 'hidden', name: 'date_from', value: dateFrom }))
                .append($('<input>', { type: 'hidden', name: 'date_to', value: dateTo }))
                .append($('<input>', { type: 'hidden', name: 'search', value: $('#searchInput').val() }))
                .append($('<input>', { type: 'hidden', name: 'status', value: $('.status-filter[data-type="appointments"]').val() }))
                .append($('<input>', { type: 'hidden', name: 'sort', value: sortVal }))
                .append($('<input>', { type: 'hidden', name: 'branch_id', value: RESTRICTED_BRANCH_ID }));

            console.log("Form Data:", form.serialize());

            $('body').append(form);
            form.submit();
        });
});

    </script>
</body>
</html>
