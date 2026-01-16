<?php
// Start session and verify at the VERY TOP (no whitespace before this)
date_default_timezone_set('Asia/Singapore');
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
require_once __DIR__ . '/../inc/redirect_helper.php';

// Verify connection was established
if (!isset($database) || !($database instanceof mysqli)) {
    die("Database connection failed. Please check connection.php");
}

// Verify the connection is active
if ($database->connect_error) {
    die("Database connection error: " . $database->connect_error);
}

$today = date('Y-m-d');

// Test query to verify connection works
$test = $database->query("SELECT 1");
if (!$test) {
    die("Database test query failed: " . $database->error);
}

// Get totals for right sidebar
// Apply branch scoping for counts if admin is restricted to a branch
$restrictedBranchId = isset($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id'] ? (int)$_SESSION['restricted_branch_id'] : 0;
if ($restrictedBranchId > 0) {
    $doctorrow = $database->query("SELECT * FROM doctor WHERE status='active' AND (branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId));");
    $patientrow = $database->query("SELECT * FROM patient WHERE status='active' AND branch_id = $restrictedBranchId;");
    $appointmentrow = $database->query("SELECT * FROM appointment WHERE status='booking' AND docid IN (SELECT docid FROM doctor WHERE branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId));");
    $schedulerow = $database->query("SELECT * FROM appointment WHERE status='appointment' AND docid IN (SELECT docid FROM doctor WHERE branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId));");
} else {
    $doctorrow = $database->query("select * from doctor where status='active';");
    $patientrow = $database->query("select * from patient where status='active';");
    $appointmentrow = $database->query("select * from appointment where status='booking';");
    $schedulerow = $database->query("select * from appointment where status='appointment';");
}

// Load branches for filter
// Load branches for filter; when restricted, only show the allowed branch
if ($restrictedBranchId > 0) {
    $branchesResult = $database->query("SELECT id, name FROM branches WHERE id = $restrictedBranchId ORDER BY name ASC");
} else {
    $branchesResult = $database->query("SELECT id, name FROM branches ORDER BY name ASC");
}

// Calendar variables
$today = date('Y-m-d');
$currentMonth = date('F');
$currentYear = date('Y');
$daysInMonth = date('t');
$firstDayOfMonth = date('N', strtotime("$currentYear-" . date('m') . "-01"));
$currentDay = date('j');

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET["id"];
    $action = $_GET["action"];

    // First get appointment details
        $bookingQuery = $database->query("
            SELECT a.*, p.pid, p.pname, p.pemail, pr.procedure_name, d.docname
            FROM appointment a
            JOIN patient p ON a.pid = p.pid
            LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id
            JOIN doctor d ON a.docid = d.docid
            WHERE a.appoid = '$id'
        ");
    
    if ($bookingQuery && $bookingQuery->num_rows > 0) {
        $booking = $bookingQuery->fetch_assoc();
        
        if ($action == 'accept') {
            $database->query("UPDATE appointment SET status='appointment' WHERE appoid='$id'");

            // Attempt to send confirmation email to patient (best-effort)
            try {
                require_once __DIR__ . '/../inc/mail_helpers.php';
                $appodate = $booking['appodate'] !== null ? date('Y-m-d', strtotime($booking['appodate'])) : null;
                $emailResult = sendConfirmationEmail(
                    $booking['pemail'] ?? '',
                    $booking['pname'] ?? '',
                    $appodate,
                    $booking['appointment_time'] ?? '',
                    $booking['docname'] ?? '',
                    $booking['procedure_name'] ?? ''
                );
                if (!($emailResult['ok'] ?? false)) {
                    error_log('Booking accept: failed to send confirmation email for appoid=' . $id . ' error=' . ($emailResult['error'] ?? ''));
                }
            } catch (Exception $e) {
                error_log('Booking accept: mail helper error: ' . $e->getMessage());
            }
            
            // Create notification for patient
            $notificationTitle = "Booking Accepted";
            $procLabel = !empty($booking['procedure_name']) ? $booking['procedure_name'] : 'Procedure (to be evaluated)';
            $notificationMessage = "Your booking for " . $procLabel . " on " . 
                                 date('M j, Y', strtotime($booking['appodate'])) . " at " . 
                                 date('g:i A', strtotime($booking['appointment_time'])) . 
                                 " has been accepted by the clinic.";
            
            $notificationQuery = $database->prepare("
                INSERT INTO notifications (user_id, user_type, title, message, related_id, related_type, created_at, is_read)
                VALUES (?, 'p', ?, ?, ?, 'appointment', NOW(), 0)
            ");
            $notificationQuery->bind_param("issi", 
                $booking['pid'], 
                $notificationTitle, 
                $notificationMessage, 
                $id
            );
            $notificationQuery->execute();
            
            redirect_with_context('booking.php');
            exit();
            
        } elseif ($action == 'reject') {
            // Archive appointment before deleting
            $status = 'rejected';
            $rejectedBy = 'clinic';
        
                $archiveQuery = $database->prepare("\n                    INSERT INTO appointment_archive (appoid, pid, docid, appodate, appointment_time, procedure_id, event_name, status, cancel_reason, archived_at)\n                    SELECT appoid, pid, docid, appodate, appointment_time, procedure_id, event_name, ?, ?, NOW()\n                    FROM appointment\n                    WHERE appoid = ?\n                ");
            $archiveQuery->bind_param("ssi", $status, $rejectedBy, $id);
            $archiveQuery->execute();

            // Attempt to send cancellation email to patient before deleting
            try {
                require_once __DIR__ . '/../inc/mail_helpers.php';
                $appodate = $booking['appodate'] !== null ? date('Y-m-d', strtotime($booking['appodate'])) : null;
                $emailResult = sendCancellationEmail(
                    $booking['pemail'] ?? '',
                    $booking['pname'] ?? '',
                    $appodate,
                    $booking['appointment_time'] ?? '',
                    $booking['docname'] ?? '',
                    $booking['procedure_name'] ?? '',
                    'clinic'
                );
                if (!($emailResult['ok'] ?? false)) {
                    error_log('Admin reject: failed to send cancellation email for appoid=' . $id . ' error=' . ($emailResult['error'] ?? ''));
                }
            } catch (Exception $e) {
                error_log('Admin reject: mail helper error: ' . $e->getMessage());
            }

            // Then delete from appointment table
            $database->query("DELETE FROM appointment WHERE appoid='$id'");
        
            // Create notification for patient
            $notificationTitle = "Booking Rejected";
            $notificationMessage = "Your booking for " . $booking['procedure_name'] . " on " . 
                                 date('M j, Y', strtotime($booking['appodate'])) . " at " . 
                                 date('g:i A', strtotime($booking['appointment_time'])) . 
                                 " has been rejected by the clinic.";
        
            $notificationQuery = $database->prepare("
                INSERT INTO notifications (user_id, user_type, title, message, related_id, related_type, created_at, is_read)
                VALUES (?, 'p', ?, ?, ?, 'appointment', NOW(), 0)
            ");
            $notificationQuery->bind_param("issi", 
                $booking['pid'], 
                $notificationTitle, 
                $notificationMessage, 
                $id
            );
            $notificationQuery->execute();
        
            redirect_with_context('booking.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/table.css">
    <link rel="stylesheet" href="../css/responsive-admin.css">
    <title>Booking - IHeartDentistDC</title>
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">
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

        .profile-img-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
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

    </style>
</head>

<body>
    <!-- Mobile hamburger for sidebar toggle -->
    <button class="hamburger-admin" id="hamburgerAdmin" aria-label="Toggle sidebar" aria-controls="adminSidebar" aria-expanded="false">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- sidebar toggle removed to keep sidebar static -->
    <?php
    // Pagination
    $results_per_page = 10;

    // Determine which page we're on
    if (isset($_GET['page'])) {
        $page = $_GET['page'];
    } else {
        $page = 1;
    }

    // Calculate the starting limit for SQL
    $start_from = ($page - 1) * $results_per_page;

    // Search functionality
    $search = "";
    $sort_param = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    $sort_order = ($sort_param === 'oldest') ? 'DESC' : 'ASC';

    // Branch filter from GET, but enforce restriction if present
    $selected_branch = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
    if ($restrictedBranchId > 0) {
        $selected_branch = $restrictedBranchId;
    }

    // Base query
    $sqlmain = "SELECT 
            appointment.appoid, 
            patient.pname, 
            patient.pemail,
            patient.profile_pic,
            appointment.appodate, 
            appointment.appointment_time,
            doctor.docname as dentist_name,
            b.name AS branch_name
        FROM appointment
        INNER JOIN patient ON appointment.pid = patient.pid
        INNER JOIN doctor ON appointment.docid = doctor.docid
        LEFT JOIN branches b ON doctor.branch_id = b.id
        WHERE appointment.status = 'booking'";

    // Check if filter is applied
    if (isset($_POST['filter'])) {
        $filterDate = $_POST['appodate'];

        if (!empty($filterDate)) {
            $sqlmain .= " AND appointment.appodate = '$filterDate'";
        }
    }

    // Branch filter via GET
    if ($selected_branch > 0) {
        $sqlmain .= " AND (doctor.branch_id = $selected_branch OR doctor.docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$selected_branch))";
    }

    // Add search if exists
    if (isset($_GET['search']) && $_GET['search'] != "") {
        $search = $_GET['search'];
        $sqlmain .= " AND (patient.pname LIKE '%$search%' OR doctor.docname LIKE '%$search%')";
    }

    // Add sorting
    $sqlmain .= " ORDER BY appointment.appodate $sort_order, appointment.appointment_time $sort_order";

    // Add pagination
    $sql_pagination = $sqlmain . " LIMIT $start_from, $results_per_page";

    $result = $database->query($sql_pagination);
    // Count total records (adjust selected fields string to include branch_name)
    $count_replace = "appointment.appoid, patient.pname, patient.pemail, patient.profile_pic, appointment.appodate, appointment.appointment_time, doctor.docname as dentist_name, b.name AS branch_name";
    $count_result = $database->query(str_replace($count_replace, "COUNT(*) as total", $sqlmain));

    if (!$count_result) {
        die("Count query failed: " . $database->error);
    }

    $count_row = $count_result->fetch_assoc();
    $total_records = isset($count_row['total']) ? $count_row['total'] : 0;
    $total_pages = ceil($total_records / $results_per_page);
    ?>
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
                            if ($curr === 'admin@edoc.com') {
                                $roleLabel = 'Super Admin';
                            } elseif (isset($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id']) {
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
            <div class="content">
                <!-- Legacy sidebar-toggle removed; logo now acts as toggle -->
                <div class="main-section">
                    <!-- search bar -->
                    <div class="search-container">
                        <form action="" method="GET" style="display: flex; width: 100%;">
                            <input type="search" name="search" id="searchInput" class="search-input"
                                placeholder="Search by name, email or phone number"
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
                                A-Z
                            </a>

                            <a href="?sort=oldest<?php echo $searchParam; ?>"
                                class="filter-btn oldest-btn <?php echo $currentSort === 'oldest' ? 'active' : 'inactive'; ?>">
                                Z-A
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
                        
                        <!-- Branch filter (GET) -->
                        <form action="" method="GET" style="margin-top:8px; display:flex; gap:8px; align-items:center;">
                            <div>
                                <select name="branch_id" class="input-text" onchange="this.form.submit()">
                                    <option value="">All Branches</option>
                                    <?php if ($branchesResult && $branchesResult->num_rows > 0): ?>
                                        <?php
                                        // rewind if previously iterated
                                        $branchesResult->data_seek(0);
                                        while ($b = $branchesResult->fetch_assoc()): ?>
                                            <option value="<?php echo $b['id']; ?>" <?php echo ($selected_branch == $b['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div>
                                <a href="booking.php" class="btn-secondary" style="padding: 8px 12px; display: inline-block;">Clear</a>
                            </div>
                        </form>
                    </div>

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive"><div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Profile</th>
                                        <th>Patient Name</th>
                                        <th>Dentist</th>
                                        <th>Branch</th>
                                        <th>Date & Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                include_once __DIR__ . '/../inc/get_profile_pic.php';
                                                $profile_pic = get_profile_pic($row);
                                                $photo = "../" . $profile_pic;
                                                ?>
                                                <img src="<?php echo $photo; ?>" alt="<?php echo htmlspecialchars($row['pname']); ?>"
                                                    class="profile-img-small">
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo $row['pname']; ?></div>
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo $row['dentist_name']; ?></div>
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo isset($row['branch_name']) ? htmlspecialchars($row['branch_name']) : '-'; ?></div>
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo $row['appodate'] . ' @ ' . $row['appointment_time']; ?></div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="#" onclick="updateBooking(<?php echo $row['appoid']; ?>, 'accept')" class="action-btn done-btn">Accept</a>
                                                    <a href="#" onclick="updateBooking(<?php echo $row['appoid']; ?>, 'reject')" class="action-btn remove-btn">Reject</a>
                                                    <a href="../patient/receipt.php?appoid=<?php echo $row['appoid']; ?>" target="_blank" class="action-btn view-btn">View Receipt</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div></div>

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
                </div>

                <!-- Add right sidebar section -->
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
                                        <h1 class="stat-number"><?php echo $appointmentrow->num_rows; ?></h1>
                                        <p class="stat-label">Bookings</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/booking.png" alt="Booking Icon">
                                        <?php if ($appointmentrow->num_rows > 0): ?>
                                            <span class="notification-badge"><?php echo $appointmentrow->num_rows; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>

                            <a href="appointment.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $schedulerow->num_rows; ?></h1>
                                        <p class="stat-label">Appointments</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/appointment.png" alt="Appointment Icon">
                                        <?php if ($schedulerow->num_rows > 0): ?>
                                            <span class="notification-badge"><?php echo $schedulerow->num_rows; ?></span>
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
                            $branchScope = '';
                            if (isset($restrictedBranchId) && $restrictedBranchId > 0) {
                                $branchScope = " AND (doctor.branch_id = $restrictedBranchId OR doctor.docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))";
                            }
                            $upcomingAppointments = $database->query("SELECT
                                    appointment.appoid,
                                    procedures.procedure_name,
                                    appointment.appodate,
                                    appointment.appointment_time,
                                    patient.pname as patient_name,
                                    doctor.docname as doctor_name,
                                    COALESCE(b.name, '') AS branch_name
                                FROM appointment
                                LEFT JOIN procedures ON appointment.procedure_id = procedures.procedure_id
                                LEFT JOIN patient ON appointment.pid = patient.pid
                                LEFT JOIN doctor ON appointment.docid = doctor.docid
                                LEFT JOIN branches b ON doctor.branch_id = b.id
                                WHERE
                                    appointment.status = 'appointment'
                                    AND appointment.appodate >= '$today'" . $branchScope . "
                                ORDER BY appointment.appodate ASC, appointment.appointment_time ASC
                            ");

                            if ($upcomingAppointments && $upcomingAppointments->num_rows > 0) {
                                while ($appointment = $upcomingAppointments->fetch_assoc()) {
                                    $pname = htmlspecialchars($appointment['patient_name'] ?? '');
                                    $dname = htmlspecialchars($appointment['doctor_name'] ?? '');
                                    $proc = htmlspecialchars($appointment['procedure_name'] ?? '');
                                    $branch = htmlspecialchars($appointment['branch_name'] ?? '');
                                    $date_str = '';
                                    $time_str = '';
                                    if (!empty($appointment['appodate'])) {
                                        $date_str = htmlspecialchars(date('F j, Y', strtotime($appointment['appodate'])));
                                    }
                                    if (!empty($appointment['appointment_time'])) {
                                        $time_str = htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time'])));
                                    }

                                    echo '<div class="appointment-item">' .
                                        '<h4 class="appointment-type">' . $pname . '</h4>' .
                                        '<p class="appointment-dentist">With Dr. ' . $dname . '</p>' .
                                        '<p class="appointment-date">' . $proc . '</p>' .
                                        '<p class="appointment-date">' . $date_str . ($date_str && $time_str ? ' • ' : '') . $time_str . (($branch!=='') ? (' - ' . $branch) : '') . '</p>' .
                                    '</div>';
                                }
                            } else {
                                echo '<div class="no-appointments"><p>No upcoming appointments scheduled</p></div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
            // Navigate so server-side redirect/processing happens normally
            window.location.href = `booking.php?action=${currentAction}&id=${currentAppoid}`;
        }

        function closeModal() {
            document.getElementById("confirmationModal").style.display = "none";
            currentAppoid = null;
            currentAction = null;
        }
    </script>
    <script>
        // Mobile sidebar toggle for Booking page
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
    </script>
</body>

</html>
