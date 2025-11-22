<?php
// Start session and verify at the VERY TOP (no whitespace before this)
session_start();

// Verify user is logged in and is admin
if (!isset($_SESSION["user"])) {
    header("location: ../login.php");
    exit();
}

if ($_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

// Include connection file with error handling
require_once __DIR__ . "/../connection.php";  // Use absolute path

// Verify connection was established
if (!isset($database) || !($database instanceof mysqli)) {
    die("Database connection failed. Please check connection.php");
}

// Verify the connection is active
if ($database->connect_error) {
    die("Database connection error: " . $database->connect_error);
}

date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');

// Test query to verify connection works
$test = $database->query("SELECT 1");
if (!$test) {
    die("Database test query failed: " . $database->error);
}

// Get totals for right sidebar - with error handling
$doctorrow = $database->query("SELECT * FROM doctor WHERE status='active'") or die("Doctor query failed: " . $database->error);
$patientrow = $database->query("SELECT * FROM patient WHERE status='active'") or die("Patient query failed: " . $database->error);
$appointmentrow = $database->query("SELECT * FROM appointment WHERE status='appointment'") or die("Appointment query failed: " . $database->error);
$bookingrow = $database->query("SELECT * FROM appointment WHERE status='booking'") or die("Booking query failed: " . $database->error);

// Pagination
$results_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start_from = ($page - 1) * $results_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? $database->real_escape_string($_GET['search']) : '';
$sort_param = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$sort_order = ($sort_param === 'oldest') ? 'DESC' : 'ASC';
$filterDate = isset($_POST['filter']) && isset($_POST['appodate']) ? $database->real_escape_string($_POST['appodate']) : '';

// Base query
$sqlmain = "SELECT 
        appointment.appoid, 
        procedures.procedure_name, 
        patient.pname, 
        appointment.appodate, 
        appointment.appointment_time,
        doctor.docname as dentist_name
    FROM appointment
    INNER JOIN patient ON appointment.pid = patient.pid
    INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
    INNER JOIN doctor ON appointment.docid = doctor.docid
    WHERE appointment.status = 'booking'";

// Apply filters
if (!empty($filterDate)) {
    $sqlmain .= " AND appointment.appodate = '$filterDate'";
}

if (!empty($search)) {
    $sqlmain .= " AND (patient.pname LIKE '%$search%' OR procedures.procedure_name LIKE '%$search%' OR doctor.docname LIKE '%$search%')";
}

// Add sorting
$sqlmain .= " ORDER BY appointment.appodate $sort_order, appointment.appointment_time $sort_order";

// Main data query
$sql_pagination = $sqlmain . " LIMIT $start_from, $results_per_page";
$result = $database->query($sql_pagination) or die("Main query failed: " . $database->error);

// Count query - fixed version
$count_query = "SELECT COUNT(*) as total FROM appointment
    INNER JOIN patient ON appointment.pid = patient.pid
    INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
    INNER JOIN doctor ON appointment.docid = doctor.docid
    WHERE appointment.status = 'booking'";

if (!empty($filterDate)) {
    $count_query .= " AND appointment.appodate = '$filterDate'";
}

if (!empty($search)) {
    $count_query .= " AND (patient.pname LIKE '%$search%' OR procedures.procedure_name LIKE '%$search%' OR doctor.docname LIKE '%$search%')";
}

$count_result = $database->query($count_query) or die("Count query failed: " . $database->error);
$count_row = $count_result->fetch_assoc();
$total_pages = ceil($count_row['total'] / $results_per_page);

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action == 'accept') {
        $database->query("UPDATE appointment SET status='appointment' WHERE appoid='$id'") or die("Update failed: " . $database->error);
        header("Location: booking.php");
        exit();
    } elseif ($action == 'reject') {
        $database->query("DELETE FROM appointment WHERE appoid='$id'") or die("Delete failed: " . $database->error);
        header("Location: booking.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Your head content remains the same -->
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/table.css">
    <title>Booking - IHeartDentistDC</title>
    <link rel="icon" href="../Media/white-icon/white-IHeartDentistDC_Logo.png" type="image/png">
    <style>
        .popup {
            animation: transitionIn-Y-bottom 0.5s;
        }

        .sub-table {
            animation: transitionIn-Y-bottom 0.5s;
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .popup {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
            position: relative;
        }

        .close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            color: #333;
            text-decoration: none;
            cursor: pointer;
            z-index: 10000;
        }
       
        .stats-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        .stat-box {
            height: 100%;
        }
        .right-sidebar {
            width: 400px;
        }
        
        /* Modal styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            width: 300px;
            text-align: center;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
        }

        .modal-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .btn-primary:hover {
            background-color: #45a049;
        }

        .btn-secondary {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .btn-secondary:hover {
            background-color: #da190b;
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .accept-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
        }
        
        .accept-btn:hover {
            background-color: #45a049;
        }
        
        .reject-btn {
            background-color: #f44336;
            color: white;
            border: none;
        }
        
        .reject-btn:hover {
            background-color: #da190b;
        }
        
        /* Search and filter styles */
        .search-container {
            margin-bottom: 20px;
        }
        
        .search-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .filter-container {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .filter-container-items {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .btn-filter {
            background-color: #2a7be4;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-filter:hover {
            background-color: #1a6bd4;
        }
        
        /* Table styles */
        .table-container {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background-color: #f5f5f5;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .table tr:hover {
            background-color: #f9f9f9;
        }
        
        .cell-text {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }
        
        /* No results style */
        .no-results {
            text-align: center;
            padding: 40px;
            color: #777;
        }
        
        /* Calendar section styles */
        .calendar-section {
            margin-top: 20px;
        }
        
        .calendar-container {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .calendar-month {
            margin: 0;
            font-size: 16px;
            color: #333;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
        }
        
        .calendar-day {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            color: #777;
            padding: 5px;
        }
        
        .calendar-date {
            text-align: center;
            padding: 8px 5px;
            border-radius: 5px;
            font-size: 12px;
        }
        
        .calendar-date.today {
            background-color: #2a7be4;
            color: white;
        }
        
        .calendar-date.other-month {
            color: #ccc;
        }
        
        /* Upcoming appointments */
        .upcoming-appointments {
            margin-top: 20px;
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .upcoming-appointments h3 {
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 16px;
            color: #333;
        }
        
        .appointment-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .appointment-item:last-child {
            border-bottom: none;
        }
        
        .appointment-type {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #333;
        }
        
        .appointment-dentist {
            margin: 0 0 5px 0;
            font-size: 12px;
            color: #777;
        }
        
        .appointment-date {
            margin: 0;
            font-size: 12px;
            color: #777;
        }
        
        .no-appointments {
            text-align: center;
            padding: 20px;
            color: #777;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Your sidebar and content area remain the same -->
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
                    Administrator
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
                <a href="booking.php" class="nav-item active">
                    <img src="../Media/Icon/Blue/booking.png" alt="Booking" class="nav-icon">
                    <span class="nav-label">Booking</span>
                </a>
                <a href="appointment.php" class="nav-item">
                    <img src="../Media/Icon/Blue/appointment.png" alt="Appointment" class="nav-icon">
                    <span class="nav-label">Appointment</span>
                </a>
                <a href="history.php" class="nav-item">
                    <img src="../Media/Icon/Blue/folder.png" alt="Archive" class="nav-icon">
                    <span class="nav-label">Archive</span>
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
            <div class="content">
                <?php include('inc/sidebar-toggle.php'); ?>
                <div class="main-section">
                    <!-- search bar -->
                    <div class="search-container">
                        <form action="" method="GET" style="display: flex; width: 100%;">
                            <input type="search" name="search" id="searchInput" class="search-input"
                                placeholder="Search by patient name, procedure or dentist"
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <?php if (isset($_GET['search']) && $_GET['search'] != ""): ?>
                                <button type="button" class="clear-btn" onclick="clearSearch()">×</button>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- header -->
                    <div class="announcements-header">
                        <h3 class="announcements-title">Manage Bookings</h3>
                        <div class="announcement-filters">
                            <?php
                            $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
                            $searchParam = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';
                            ?>
                            <a href="?sort=newest<?php echo $searchParam; ?>"
                                class="filter-btn newest-btn <?php echo ($currentSort === 'newest' || $currentSort === '') ? 'active' : 'inactive'; ?>">
                                Newest
                            </a>

                            <a href="?sort=oldest<?php echo $searchParam; ?>"
                                class="filter-btn oldest-btn <?php echo $currentSort === 'oldest' ? 'active' : 'inactive'; ?>">
                                Oldest
                            </a>
                        </div>
                    </div>

                    <!-- Date filter form -->
                    <div class="filter-container">
                        <form action="" method="post" style="display: flex; gap: 10px; align-items: center;">
                            <div style="flex-grow: 1;">
                                <input type="date" name="appodate" id="date" class="input-text filter-container-items" 
                                    style="margin: 0; width: 100%;" value="<?php echo isset($_POST['appodate']) ? $_POST['appodate'] : ''; ?>">
                            </div>
                            <div>
                                <input type="submit" name="filter" value="Filter" class="btn-primary-soft btn button-icon btn-filter">
                            </div>
                            <?php if (isset($_POST['filter'])): ?>
                                <div>
                                    <a href="booking.php" class="btn-secondary" style="padding: 10px 15px; display: inline-block;">Clear</a>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Patient Name</th>
                                        <th>Procedure</th>
                                        <th>Dentist</th>
                                        <th>Date & Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><div class="cell-text"><?php echo $row['pname']; ?></div></td>
                                            <td><div class="cell-text"><?php echo $row['procedure_name']; ?></div></td>
                                            <td><div class="cell-text"><?php echo $row['dentist_name']; ?></div></td>
                                            <td><div class="cell-text"><?php echo $row['appodate'] . ' @ ' . $row['appointment_time']; ?></div></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="#" onclick="updateBooking(<?php echo $row['appoid']; ?>, 'accept')" class="action-btn accept-btn">Accept</a>
                                                    <a href="#" onclick="updateBooking(<?php echo $row['appoid']; ?>, 'reject')" class="action-btn reject-btn">Reject</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="pagination">
                            <?php
                            $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
                            $sortParam = '&sort=' . $currentSort;
                            $searchParam = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';
                            $filterParam = isset($_POST['filter']) ? '&filter=1&appodate=' . urlencode($_POST['appodate']) : '';

                            // Previous link
                            if ($page > 1) {
                                echo '<a href="?page=' . ($page - 1) . $searchParam . $sortParam . $filterParam . '">&laquo; Previous</a>';
                            }

                            // Page links
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<a href="?page=' . $i . $searchParam . $sortParam . $filterParam . '"' . ($i == $page ? ' class="active"' : '') . '>' . $i . '</a>';
                            }

                            // Next link
                            if ($page < $total_pages) {
                                echo '<a href="?page=' . ($page + 1) . $searchParam . $sortParam . $filterParam . '">Next &raquo;</a>';
                            }
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <p>No bookings found. Please try a different search or filter.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Patient Name</th>
                                        <th>Procedure</th>
                                        <th>Dentist</th>
                                        <th>Date & Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><div class="cell-text"><?php echo htmlspecialchars($row['pname']); ?></div></td>
                                            <td><div class="cell-text"><?php echo htmlspecialchars($row['procedure_name']); ?></div></td>
                                            <td><div class="cell-text"><?php echo htmlspecialchars($row['dentist_name']); ?></div></td>
                                            <td><div class="cell-text"><?php echo htmlspecialchars($row['appodate'] . ' @ ' . $row['appointment_time']); ?></div></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="#" onclick="updateBooking(<?php echo (int)$row['appoid']; ?>, 'accept')" class="action-btn accept-btn">Accept</a>
                                                    <a href="#" onclick="updateBooking(<?php echo (int)$row['appoid']; ?>, 'reject')" class="action-btn reject-btn">Reject</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="pagination">
                            <?php
                            $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
                            $searchParam = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';
                            $filterParam = isset($_POST['filter']) ? '&filter=1&appodate=' . urlencode($_POST['appodate']) : '';

                            // Previous link
                            if ($page > 1) {
                                echo '<a href="?page=' . ($page - 1) . $searchParam . '&sort=' . $currentSort . $filterParam . '">&laquo; Previous</a>';
                            }

                            // Page links
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<a href="?page=' . $i . $searchParam . '&sort=' . $currentSort . $filterParam . '"' . ($i == $page ? ' class="active"' : '') . '>' . $i . '</a>';
                            }

                            // Next link
                            if ($page < $total_pages) {
                                echo '<a href="?page=' . ($page + 1) . $searchParam . '&sort=' . $currentSort . $filterParam . '">Next &raquo;</a>';
                            }
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <p>No bookings found. Please try a different search or filter.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="right-sidebar">
                    <div class="stats-section">
                        <div class="stats-container">
                            <!-- First row -->
                            <a href="dentist.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $doctorrow->num_rows; ?></h1>
                                        <p class="stat-label">Dentists</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/dentist.png" alt="Dentist Icon">
                                    </div>
                                </div>
                            </a>

                            <!-- Second row -->
                            <a href="patient.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $patientrow->num_rows; ?></h1>
                                        <p class="stat-label">Patients</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/care.png" alt="Patient Icon">
                                    </div>
                                </div>
                            </a>

                            <a href="booking.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $bookingrow->num_rows; ?></h1>
                                        <p class="stat-label">Bookings</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/booking.png" alt="Booking Icon">
                                        <?php if ($bookingrow->num_rows > 0): ?>
                                            <span class="notification-badge"><?php echo $bookingrow->num_rows; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>

                            <a href="appointment.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $appointmentrow->num_rows; ?></h1>
                                        <p class="stat-label">Appointments</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/appointment.png" alt="Appointment Icon">
                                        <?php if ($appointmentrow->num_rows > 0): ?>
                                            <span class="notification-badge"><?php echo $appointmentrow->num_rows; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div class="calendar-section">
                        <!-- Dynamic Calendar -->
                        <div class="calendar-container">
                            <div class="calendar-header">
                                <h3 class="calendar-month">
                                    <?php
                                    // Get current month name dynamically
                                    echo strtoupper(date('F', strtotime('this month')));
                                    ?>
                                </h3>
                            </div>
                            <div class="calendar-grid">
                                <div class="calendar-day">S</div>
                                <div class="calendar-day">M</div>
                                <div class="calendar-day">T</div>
                                <div class="calendar-day">W</div>
                                <div class="calendar-day">T</div>
                                <div class="calendar-day">F</div>
                                <div class="calendar-day">S</div>

                                <?php
                                // Calculate the first day of the month and number of days
                                $firstDayOfMonth = date('N', strtotime("first day of this month"));
                                $daysInMonth = date('t');
                                $currentDay = date('j');

                                // Calculate the previous month's spillover days
                                $previousMonthDays = $firstDayOfMonth - 1;
                                $previousMonthLastDay = date('t', strtotime('last month'));
                                $startDay = $previousMonthLastDay - $previousMonthDays + 1;

                                // Display previous month's spillover days
                                for ($i = 0; $i < $previousMonthDays; $i++) {
                                    echo '<div class="calendar-date other-month">' . $startDay . '</div>';
                                    $startDay++;
                                }

                                // Display current month's days
                                for ($day = 1; $day <= $daysInMonth; $day++) {
                                    $class = ($day == $currentDay) ? 'calendar-date today' : 'calendar-date';
                                    echo '<div class="' . $class . '">' . $day . '</div>';
                                }

                                // Calculate and display next month's spillover days
                                $nextMonthDays = 42 - ($previousMonthDays + $daysInMonth); // 42 = 6 rows * 7 days
                                for ($i = 1; $i <= $nextMonthDays; $i++) {
                                    echo '<div class="calendar-date other-month">' . $i . '</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="upcoming-appointments">
                        <h3>Upcoming Appointments</h3>
                        <div class="appointments-content">
                            <?php
                            $upcomingAppointments = $database->query("
                                SELECT
                                    appointment.appoid,
                                    procedures.procedure_name,
                                    appointment.appodate,
                                    appointment.appointment_time,
                                    patient.pname as patient_name,
                                    doctor.docname as doctor_name
                                FROM appointment
                                INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
                                INNER JOIN patient ON appointment.pid = patient.pid
                                INNER JOIN doctor ON appointment.docid = doctor.docid
                                WHERE
                                    appointment.status = 'appointment'
                                    AND appointment.appodate >= '$today'
                                ORDER BY appointment.appodate ASC
                                LIMIT 3;
                            ");

                            if ($upcomingAppointments->num_rows > 0) {
                                while ($appointment = $upcomingAppointments->fetch_assoc()) {
                                    echo '<div class="appointment-item">
                                        <h4 class="appointment-type">' . htmlspecialchars($appointment['patient_name']) . '</h4>
                                        <p class="appointment-dentist">With Dr. ' . htmlspecialchars($appointment['doctor_name']) . '</p>
                                        <p class="appointment-date">' . htmlspecialchars($appointment['procedure_name']) . '</p>
                                        <p class="appointment-date">' .
                                            htmlspecialchars(date('F j, Y', strtotime($appointment['appodate']))) .
                                            ' • ' .
                                            htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time']))) .
                                        '</p>
                                    </div>';
                                }
                            } else {
                                echo '<div class="no-appointments">
                                    <p>No upcoming appointments scheduled</p>
                                </div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal and JavaScript remain the same -->
    <div id="confirmationModal" class="modal" style="display: none;">
        <div class="modal-content">
            <p id="modalMessage">Are you sure you want to proceed?</p>
            <div class="modal-buttons">
                <button onclick="confirmAction()" class="btn-primary">Yes</button>
                <button onclick="closeModal()" class="btn-secondary">No</button>
            </div>
        </div>
    </div>

    <script>
        // Function to clear search and redirect
        function clearSearch() {
            window.location.href = 'booking.php';
        }

        // Search input event listener
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            const clearBtn = document.querySelector('.clear-btn');

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    clearSearch();
                });
            }
        });
        
        let currentAppoid = null;
        let currentAction = null;

        function updateBooking(appoid, action) {
            currentAppoid = appoid;
            currentAction = action;
            document.getElementById("modalMessage").textContent = `Are you sure you want to ${action} this booking?`;
            document.getElementById("confirmationModal").style.display = "flex";
        }

        function confirmAction() {
            fetch(`booking.php?action=${currentAction}&id=${currentAppoid}`)
                .then(response => {
                    if (response.ok) {
                        window.location.reload();
                    } else {
                        alert("Failed to update booking. Please try again.");
                        closeModal();
                    }
                })
                .catch(err => {
                    console.error("Error:", err);
                    closeModal();
                });
        }

        function closeModal() {
            document.getElementById("confirmationModal").style.display = "none";
            currentAppoid = null;
            currentAction = null;
        }
    </script>
</body>
</html>
