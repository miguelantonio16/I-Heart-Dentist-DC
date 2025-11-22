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
    
    $type = $_GET['type'] ?? 'appointments';
    $page = max(1, intval($_GET['page'] ?? 1));
    $rowsPerPage = intval($_GET['rows_per_page'] ?? 10);
    $statusFilter = $_GET['status'] ?? 'all';
    $sortOrder = in_array($_GET['sort'] ?? 'DESC', ['ASC', 'DESC']) ? $_GET['sort'] : 'DESC';

    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    // Query setup
    switch ($type) {
        case 'appointments':
            $baseQuery = "SELECT a.*, p.pname, d.docname, pr.procedure_name 
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
                    LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id";
            
            $where = [];
            if ($statusFilter !== 'all') {
                $where[] = "a.status = '" . $database->real_escape_string($statusFilter) . "'";
            }
            if (!empty($searchTerm)) {
                $searchTerm = $database->real_escape_string($searchTerm);
                $where[] = "(p.pname LIKE '%$searchTerm%' OR d.docname LIKE '%$searchTerm%' OR pr.procedure_name LIKE '%$searchTerm%')";
            }
            if (!empty($where)) {
                $baseQuery .= " WHERE " . implode(" AND ", $where);
            }
            
            // Similar modifications for countQuery
            $countQuery = "SELECT COUNT(*) FROM (
                SELECT a.appoid FROM appointment a
                LEFT JOIN patient p ON a.pid = p.pid
                LEFT JOIN doctor d ON a.docid = d.docid
                LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id
                WHERE a.status = 'completed'";
            
            if (!empty($searchTerm)) {
                $countQuery .= " AND (p.pname LIKE '%$searchTerm%' OR d.docname LIKE '%$searchTerm%' OR pr.procedure_name LIKE '%$searchTerm%')";
            }
            
            $countQuery .= " UNION ALL
                SELECT a.appoid FROM appointment_archive a
                LEFT JOIN patient p ON a.pid = p.pid
                LEFT JOIN doctor d ON a.docid = d.docid
                LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id
                WHERE a.status IN ('cancelled', 'rejected')";
            
            if (!empty($searchTerm)) {
                $countQuery .= " AND (p.pname LIKE '%$searchTerm%' OR d.docname LIKE '%$searchTerm%' OR pr.procedure_name LIKE '%$searchTerm%')";
            }
            
            $countQuery .= ") AS combined";
            
            $orderColumn = 'a.appodate';
            break;
        
        case 'dentists':
            $baseQuery = "SELECT * FROM doctor";
            $where = [];
            if ($statusFilter !== 'all') {
                $where[] = "status = '" . $database->real_escape_string($statusFilter) . "'";
            }
            if (!empty($searchTerm)) {
                $searchTerm = $database->real_escape_string($searchTerm);
                $where[] = "docname LIKE '%$searchTerm%'";
            }
            if (!empty($where)) {
                $baseQuery .= " WHERE " . implode(" AND ", $where);
            }
            
            $countQuery = "SELECT COUNT(*) FROM doctor";
            if (!empty($where)) {
                $countQuery .= " WHERE " . implode(" AND ", $where);
            }
            
            $orderColumn = 'docid';
            break;
        
        case 'patients':
            $baseQuery = "SELECT * FROM patient";
            $where = [];
            if ($statusFilter !== 'all') {
                $where[] = "status = '" . $database->real_escape_string($statusFilter) . "'";
            }
            if (!empty($searchTerm)) {
                $searchTerm = $database->real_escape_string($searchTerm);
                $where[] = "pname LIKE '%$searchTerm%'";
            }
            if (!empty($where)) {
                $baseQuery .= " WHERE " . implode(" AND ", $where);
            }
            
            $countQuery = "SELECT COUNT(*) FROM patient";
            if (!empty($where)) {
                $countQuery .= " WHERE " . implode(" AND ", $where);
            }
            
            $orderColumn = 'pid';
            break;
    }

    // Execute queries
    $total = $database->query($countQuery)->fetch_row()[0];
    $totalPages = ceil($total / $rowsPerPage);
    $offset = ($page - 1) * $rowsPerPage;
    $result = $database->query("$baseQuery ORDER BY $orderColumn $sortOrder LIMIT $rowsPerPage OFFSET $offset");

    // Build HTML
    $html = '<table class="table"><thead><tr><th class="checkbox-column"><input type="checkbox" class="select-all"></th>';
    switch ($type) {
        case 'appointments': $html .= '<th>Patient</th><th>Dentist</th><th>Procedure</th><th>Date & Time</th><th>Status</th>'; break;
        case 'dentists': $html .= '<th>Name</th><th>Email</th><th>Phone</th><th>Status</th>'; break;
        case 'patients': $html .= '<th>Name</th><th>Email</th><th>Address</th><th>Birthdate</th><th>Status</th>'; break;
    }
    $html .= '</tr></thead><tbody>';

    while ($row = $result->fetch_assoc()) {
        $html .= '<tr><td><input type="checkbox" class="row-checkbox" value="' 
               . ($type === 'appointments' ? $row['appoid'] : ($type === 'dentists' ? $row['docid'] : $row['pid'])) 
               . '"></td>';
        
        switch ($type) {
            case 'appointments':
                $html .= '<td>' . htmlspecialchars($row['pname'] ?? 'N/A') . '</td>'
                      . '<td>' . htmlspecialchars($row['docname'] ?? 'N/A') . '</td>'
                      . '<td>' . htmlspecialchars($row['procedure_name'] ?? 'N/A') . '</td>'
                      . '<td>' . htmlspecialchars(($row['appodate'] ?? '') . ' @ ' . substr($row['appointment_time'] ?? '', 0, 5)) . '</td>'
                      . '<td>' . ucfirst($row['status']) . '</td>';
                break;
            case 'dentists':
                $html .= '<td>' . htmlspecialchars($row['docname']) . '</td>'
                      . '<td>' . htmlspecialchars($row['docemail']) . '</td>'
                      . '<td>' . htmlspecialchars($row['doctel']) . '</td>'
                      . '<td>' . ucfirst($row['status']) . '</td>';
                break;
            case 'patients':
                $html .= '<td>' . htmlspecialchars($row['pname']) . '</td>'
                      . '<td>' . htmlspecialchars($row['pemail']) . '</td>'
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

$appointment_count = $database->query(
    "SELECT COUNT(*) FROM (
        SELECT appoid FROM appointment WHERE status = 'completed'
        UNION ALL
        SELECT appoid FROM appointment_archive WHERE status IN ('cancelled', 'rejected')
    ) AS combined"
)->fetch_row()[0];

$dentist_count = $database->query("SELECT COUNT(*) FROM doctor")->fetch_row()[0];
$patient_count = $database->query("SELECT COUNT(*) FROM patient")->fetch_row()[0];
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
    <div class="main-container">
        <div class="sidebar">
            <div class="sidebar-logo">
                <img src="../Media/Icon/logo.png" alt="IHeartDentistDC Logo">
            </div>

            <div class="user-profile">
                <div class="profile-image">
                    <img src="../Media/Icon/logo.png" alt="Profile" class="profile-img">
                </div>
                <h3 class="profile-name">I Heart Dentist Dental Clinic</h3>
                <p style="color: #777; margin: 0; font-size: 14px; text-align: center;">
                Secretary
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
                <a href="settings.php" class="nav-item">
                    <img src="../Media/Icon/Blue/settings.png" alt="Settings" class="nav-icon">
                    <span class="nav-label">Settings</span>
                </a>
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
                    <?php include('inc/sidebar-toggle.php'); ?>
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
$(document).ready(function() {
    let currentTab = 'appointments';
    
    // Load table data function
    function loadTableData(type, page = 1) {
        const rowsPerPage = $(`.rows-per-page[data-type="${type}"]`).val();
        const statusFilter = $(`.status-filter[data-type="${type}"]`).val();
        const sortOrder = $(`#${type}-content .sort-btn.active`).data('order') || 'DESC';
        const searchTerm = $('#searchInput').val();
        
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
                search: searchTerm
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

            console.log("Selected IDs:", selected); 
            console.log("Report Type:", type); 

            if (selected.length === 0) {
                alert('Please select items to generate PDF');
                return;
            }

            const form = $('<form>', { action: 'generate_pdf.php', method: 'POST' })
                .append($('<input>', { type: 'hidden', name: 'report_type', value: type }))
                .append($('<input>', { type: 'hidden', name: 'selected_ids', value: JSON.stringify(selected) }));

            console.log("Form Data:", form.serialize()); 

            $('body').append(form).submit();
            form.submit();
        });
});

    </script>
</body>
</html>
