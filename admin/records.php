<?php
date_default_timezone_set('Asia/Singapore');
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'a') {
        header("location: login.php");
    }
} else {
    header("location: login.php");
}

include("../connection.php");
require_once __DIR__ . '/../inc/redirect_helper.php';

// Branch restriction (e.g., Bacoor-only admin)
$restrictedBranchId = isset($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id'] ? (int)$_SESSION['restricted_branch_id'] : 0;

// Get totals for right sidebar (respect branch restriction)
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

// Load branches for filter dropdown; restrict if needed
if ($restrictedBranchId > 0) {
    $branchesResult = $database->query("SELECT id, name FROM branches WHERE id = $restrictedBranchId ORDER BY name ASC");
} else {
    $branchesResult = $database->query("SELECT id, name FROM branches ORDER BY name ASC");
}

// Branch filter param (branch_id to match patient.php)
$selected_branch = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
if ($restrictedBranchId > 0) { $selected_branch = $restrictedBranchId; }

// Handle patient record view
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'view' && isset($_GET['id'])) {
        $patient_id = $_GET['id'];

        // Fetch patient basic info
        $patient_sql = "SELECT * FROM patient WHERE pid = ?";
        $stmt = $database->prepare($patient_sql);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $patient_result = $stmt->get_result();
        $patient = $patient_result->fetch_assoc();

        if (!$patient) {
            redirect_with_context('records.php', ['error' => 'patient_not_found']);
            exit();
        }

        // Fetch medical history
        $medical_sql = "SELECT * FROM medical_history WHERE email = ?";
        $stmt = $database->prepare($medical_sql);
        $stmt->bind_param("s", $patient['pemail']);
        $stmt->execute();
        $medical_result = $stmt->get_result();
        $medical_history = $medical_result->fetch_assoc();

        // Informed consent removed from system; not fetched
    }
}

if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $query = "SELECT * FROM patient WHERE status='active' AND (pname LIKE '%$search%' OR pemail LIKE '%$search%' OR ptel LIKE '%$search%')";
    // apply branch filter when present
    if ($selected_branch > 0) {
        $query .= " AND (patient.branch_id = $selected_branch OR EXISTS(SELECT 1 FROM patient_branches pb WHERE pb.pid = patient.pid AND pb.branch_id = $selected_branch) OR EXISTS(SELECT 1 FROM appointment ap WHERE ap.pid = patient.pid AND ap.branch_id = $selected_branch))";
    }
    $query .= " ORDER BY pname $sort_order LIMIT $start_from, $results_per_page";

    $count_query = "SELECT COUNT(*) as total FROM patient WHERE status='active' AND (pname LIKE '%$search%' OR pemail LIKE '%$search%' OR ptel LIKE '%$search%')";
    if ($selected_branch > 0) {
        $count_query .= " AND (branch_id = $selected_branch OR EXISTS(SELECT 1 FROM patient_branches pb WHERE pb.pid = patient.pid AND pb.branch_id = $selected_branch) OR EXISTS(SELECT 1 FROM appointment ap WHERE ap.pid = patient.pid AND ap.branch_id = $selected_branch))";
    }
} else {
    $query = "SELECT * FROM patient WHERE status='active'";
    if ($selected_branch > 0) {
        $query .= " AND (patient.branch_id = $selected_branch OR EXISTS(SELECT 1 FROM patient_branches pb WHERE pb.pid = patient.pid AND pb.branch_id = $selected_branch) OR EXISTS(SELECT 1 FROM appointment ap WHERE ap.pid = patient.pid AND ap.branch_id = $selected_branch))";
    }
    $query .= " ORDER BY pname $sort_order LIMIT $start_from, $results_per_page";

    $count_query = "SELECT COUNT(*) as total FROM patient WHERE status='active'";
    if ($selected_branch > 0) {
        $count_query .= " AND (branch_id = $selected_branch OR EXISTS(SELECT 1 FROM patient_branches pb WHERE pb.pid = patient.pid AND pb.branch_id = $selected_branch) OR EXISTS(SELECT 1 FROM appointment ap WHERE ap.pid = patient.pid AND ap.branch_id = $selected_branch))";
    }
}

$result = $database->query($query);
$count_result = $database->query($count_query);
$count_row = $count_result->fetch_assoc();
$total_pages = ceil($count_row['total'] / $results_per_page);

// Calendar variables
$today = date('Y-m-d');
$currentMonth = date('F');
$currentYear = date('Y');
$daysInMonth = date('t');
$firstDayOfMonth = date('N', strtotime("$currentYear-" . date('m') . "-01"));
$currentDay = date('j');
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
    <title>Patient Records - IHeartDentistDC</title>
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
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
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

        .btn-edit {
            background-image: url('../Media/Icon/Blue/edit.png');
            background-repeat: no-repeat;
            background-position: left center;
            padding-left: 30px;
        }

        .btn-view {
            background-image: url('../Media/Icon/Blue/eye.png');
            background-repeat: no-repeat;
            background-position: left center;
            padding-left: 30px;
        }

        .btn-delete {
            background-image: url('../Media/Icon/Blue/delete.png');
            background-repeat: no-repeat;
            background-position: left center;
            padding-left: 30px;
        }

        /* Receipt button style used in patient view */
        .action-btn.view-receipt-btn { display:inline-block; background:#2f3670; color:#fff; padding:6px 10px; border-radius:6px; text-decoration:none; }

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

        /* Branch select styling to match compact pill-like UI */
        .branch-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding: 8px 12px;
            border-radius: 10px;
            border: 1px solid #e6e9ef;
            background: #ffffff;
            color: #4b5563;
            font-size: 14px;
            line-height: 20px;
            min-width: 140px;
            box-shadow: none;
            cursor: pointer;
        }

        /* Add a subtle down-caret using a background SVG */
        .branch-select {
            background-image: linear-gradient(45deg, transparent 50%, #9ca3af 50%), linear-gradient(135deg, #9ca3af 50%, transparent 50%), linear-gradient(to right, #fff, #fff);
            background-position: calc(100% - 18px) calc(1em + 2px), calc(100% - 13px) calc(1em + 2px), 0 0;
            background-size: 6px 6px, 6px 6px, 100% 100%;
            background-repeat: no-repeat;
            padding-right: 36px;
        }

        /* Slight hover/focus states */
        .branch-select:focus {
            border-color: #cbe4ff;
            box-shadow: 0 0 0 3px rgba(66,153,225,0.12);
            outline: none;
        }

        /* Records specific styles */
        .record-section {
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .record-section h3 {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .record-row {
            display: flex;
            margin-bottom: 15px;
        }

        .record-label {
            font-weight: bold;
            width: 250px;
        }

        .signature-image {
            max-width: 300px;
            max-height: 150px;
            border: 1px solid #ddd;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <!-- Mobile hamburger for sidebar toggle -->
    <button class="hamburger-admin" id="hamburgerAdmin" aria-label="Toggle sidebar" aria-controls="adminSidebar" aria-expanded="false">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- sidebar toggle removed to keep sidebar static -->
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
                <a href="records.php" class="nav-item active">
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
                        <h3 class="announcements-title">Patient Records</h3>
                        <div class="announcement-filters">
                            <?php
                            $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
                            $searchParam = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';
                            $branchParam = $selected_branch ? '&branch_id=' . $selected_branch : '';
                            ?>
                            <a href="?sort=newest<?php echo $searchParam; ?>"
                                class="filter-btn newest-btn <?php echo ($currentSort === 'newest' || $currentSort === '') ? 'active' : 'inactive'; ?>">
                                A-Z
                            </a>

                            <a href="?sort=oldest<?php echo $searchParam . $branchParam; ?>"
                                class="filter-btn oldest-btn <?php echo $currentSort === 'oldest' ? 'active' : 'inactive'; ?>">
                                Z-A
                            </a>
                            
                            <!-- Branch filter -->
                            <form method="GET" style="display:inline-block; margin-left:12px;">
                                <input type="hidden" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($currentSort); ?>">
                                <select name="branch_id" onchange="this.form.submit()" class="branch-select" style="margin-left:8px;">
                                    <option value="">All Branches</option>
                                    <?php if ($branchesResult && $branchesResult->num_rows > 0): ?>
                                        <?php while ($b = $branchesResult->fetch_assoc()): ?>
                                            <option value="<?php echo $b['id']; ?>" <?php echo ($selected_branch == $b['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </form>
                        </div>
                    </div>

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive"><div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Profile</th>
                                        <th>Name</th>
                                        <th>Branch</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Date of Birth</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                include_once __DIR__ . '/../inc/get_profile_pic.php';
                                                $profile_pic = get_profile_pic($row); // returns path without leading ../
                                                $photo = "../" . $profile_pic;
                                                ?>
                                                <img src="<?php echo $photo; ?>" alt="<?php echo htmlspecialchars($row['pname']); ?>"
                                                    class="profile-img-small" onerror="this.onerror=null;this.src='../Media/Icon/Blue/profile.png';">
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo $row['pname']; ?></div>
                                            </td>
                                            <td>
                                                <div class="cell-text">
                                                    <?php
                                                    // Build multi-branch list
                                                    $branches_list = [];
                                                    $pbres = $database->query("SELECT b.name FROM patient_branches pb JOIN branches b ON pb.branch_id=b.id WHERE pb.pid='" . (int)$row['pid'] . "' ORDER BY b.name ASC");
                                                    if ($pbres && $pbres->num_rows > 0) {
                                                        while ($br = $pbres->fetch_assoc()) {
                                                            $clean = preg_replace('/\\s*Branch$/i','', $br['name']);
                                                            if (!in_array($clean, $branches_list)) $branches_list[] = $clean;
                                                        }
                                                    } elseif (!empty($row['branch_id'])) {
                                                        // Fallback to legacy single branch
                                                        $legacy = $database->query("SELECT name FROM branches WHERE id='" . (int)$row['branch_id'] . "' LIMIT 1");
                                                        if ($legacy && $legacy->num_rows > 0) {
                                                            $clean = preg_replace('/\\s*Branch$/i','', $legacy->fetch_assoc()['name']);
                                                            $branches_list[] = $clean;
                                                        }
                                                    }
                                                    echo !empty($branches_list) ? htmlspecialchars(implode(', ', $branches_list)) : '-';
                                                    ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo $row['pemail']; ?></div>
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo $row['ptel']; ?></div>
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo $row['pdob']; ?></div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="records.php?action=view&id=<?php echo $row['pid']; ?>"
                                                        class="action-btn view-btn">View Records</a>
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
                            $branchParam = $selected_branch ? '&branch_id=' . $selected_branch : '';

                            // Previous link
                            if ($page > 1) {
                                echo '<a href="?page=' . ($page - 1) . $searchParam . $sortParam . $branchParam . '">&laquo; Previous</a>';
                            }

                            // Page links
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<a href="?page=' . $i . $searchParam . $sortParam . $branchParam . '"' . ($i == $page ? ' class="active"' : '') . '>' . $i . '</a>';
                            }

                            // Next link
                            if ($page < $total_pages) {
                                echo '<a href="?page=' . ($page + 1) . $searchParam . $sortParam . $branchParam . '">Next &raquo;</a>';
                            }
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <p>No patient found. Please try a different search term.</p>
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
                                    $date_str = '';
                                    $time_str = '';
                                    if (!empty($appointment['appodate'])) {
                                        $date_str = htmlspecialchars(date('F j, Y', strtotime($appointment['appodate'])));
                                    }
                                    if (!empty($appointment['appointment_time'])) {
                                        $time_str = htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time'])));
                                    }

                                    $branch = htmlspecialchars($appointment['branch_name'] ?? '');
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

    <?php if (isset($_GET['action']) && $_GET['action'] == 'view' && isset($patient)): ?>
        <!-- Patient Record View Popup -->
        <div id="popup1" class="overlay" style="display: flex;">
            <div class="popup">
                <center>
                    <a class="close" href="records.php">&times;</a>
                    <div style="display: flex;justify-content: center;">
                        <table width="90%" class="sub-table scrolldown add-doc-form-container" border="0">
                            <tr>
                                <td>
                                    <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">
                                        Patient Records: <?php echo $patient['pname']; ?></p>
                                    <p
                                        style="font-size: 14px;color: rgb(119, 119, 119);padding: 0;margin: 0;text-align: left;">
                                        Patient ID: P-<?php echo $patient['pid']; ?></p>
                                    <br>
                                </td>
                            </tr>

                            <tr>
                                <td colspan="2">
                                    <div class="record-section">
                                        <h3>Patient Information</h3>
                                        <div class="record-row">
                                            <span class="record-label">Name:</span>
                                            <span><?php echo $patient['pname']; ?></span>
                                        </div>
                                        <div class="record-row">
                                            <span class="record-label">Email:</span>
                                            <span><?php echo $patient['pemail']; ?></span>
                                        </div>
                                        <div class="record-row">
                                            <span class="record-label">Phone:</span>
                                            <span><?php echo $patient['ptel']; ?></span>
                                        </div>
                                        <div class="record-row">
                                            <span class="record-label">Date of Birth:</span>
                                            <span><?php echo $patient['pdob']; ?></span>
                                        </div>
                                        <div class="record-row">
                                            <span class="record-label">Address:</span>
                                            <span><?php echo $patient['paddress']; ?></span>
                                        </div>
                                    </div>

                                    <div class="record-section">
                                        <h3>Past Appointments</h3>
                                        <?php
                                            // Pagination parameters for past appointments
                                            $past_page = isset($_GET['past_page']) ? max(1, intval($_GET['past_page'])) : 1;
                                            $past_page_size = 5; // items per page
                                            $pid_safe = intval($patient['pid']);
                                            // Total count of completed appointments
                                            $past_count_res = $database->query("SELECT COUNT(*) AS cnt FROM appointment WHERE pid = $pid_safe AND status='completed'");
                                            $past_total = ($past_count_res && $past_count_res->num_rows) ? intval($past_count_res->fetch_assoc()['cnt']) : 0;
                                            $past_total_pages = $past_total > 0 ? ceil($past_total / $past_page_size) : 1;
                                            if ($past_page > $past_total_pages) { $past_page = $past_total_pages; }
                                            $past_offset = ($past_page - 1) * $past_page_size;
                                            // Page query
                                            // Exclude discount procedure names from the displayed procedure list
                                            $past_q_sql = "SELECT a.appoid, a.appodate, a.appointment_time, a.status,
                                                                        COALESCE(
                                                                            NULLIF(GROUP_CONCAT(DISTINCT CASE WHEN ap_proc.procedure_name NOT IN ('PWD Discount','Senior Citizen Discount') THEN ap_proc.procedure_name END ORDER BY ap_proc.procedure_name SEPARATOR ', '), ''),
                                                                            NULLIF((SELECT GROUP_CONCAT(DISTINCT CASE WHEN apa.procedure_name NOT IN ('PWD Discount','Senior Citizen Discount') THEN apa.procedure_name END ORDER BY apa.procedure_name SEPARATOR ', ')
                                                                                    FROM appointment_procedures_archive apa
                                                                                    WHERE apa.appointment_id = a.appoid), ''),
                                                                            (SELECT procedure_name FROM procedures WHERE procedure_id = a.procedure_id AND procedure_name NOT IN ('PWD Discount','Senior Citizen Discount') LIMIT 1)
                                                                        ) AS procedures,
                                                                        d.docname
                                                                        FROM appointment a
                                                                        LEFT JOIN appointment_procedures ap ON a.appoid = ap.appointment_id
                                                                        LEFT JOIN procedures ap_proc ON ap.procedure_id = ap_proc.procedure_id
                                                                        LEFT JOIN doctor d ON a.docid = d.docid
                                                                        WHERE a.pid = $pid_safe AND a.status = 'completed'
                                                                        GROUP BY a.appoid
                                                                        ORDER BY a.appodate DESC, a.appointment_time DESC
                                                                        LIMIT $past_page_size OFFSET $past_offset";
                                            $past_q = $database->query($past_q_sql);
                                        ?>
                                        <?php if ($past_q && $past_q->num_rows > 0): ?>
                                            <?php $total = $past_q->num_rows; $i = 0; while ($appt = $past_q->fetch_assoc()): $i++; ?>
                                                <div class="record-row">
                                                    <span class="record-label">Date:</span>
                                                    <span><?php echo htmlspecialchars(date('F j, Y', strtotime($appt['appodate']))); ?></span>
                                                </div>
                                                <div class="record-row">
                                                    <span class="record-label">Time:</span>
                                                    <span><?php echo htmlspecialchars(date('g:i A', strtotime($appt['appointment_time']))); ?></span>
                                                </div>
                                                <div class="record-row">
                                                    <span class="record-label">Procedure:</span>
                                                    <span><?php echo htmlspecialchars($appt['procedures'] ?: 'N/A'); ?></span>
                                                </div>
                                                <div class="record-row">
                                                    <span class="record-label">Dentist:</span>
                                                    <span><?php echo htmlspecialchars($appt['docname'] ?: 'N/A'); ?></span>
                                                </div>
                                                <div class="record-row" style="margin-bottom:12px;">
                                                    <span class="record-label">Status:</span>
                                                    <span><?php echo htmlspecialchars(ucfirst($appt['status'])); ?></span>
                                                </div>
                                                <div style="margin-bottom:10px;">
                                                    <?php $appoid = (int)$appt['appoid']; ?>
                                                    <a href="../patient/receipt.php?appoid=<?php echo $appoid; ?>" target="_blank" class="action-btn view-receipt-btn">View Receipt</a>
                                                </div>
                                                <?php if ($i < $total): ?>
                                                    <div class="past-appt-separator"></div>
                                                <?php endif; ?>
                                            <?php endwhile; ?>
                                            <div class="past-appt-pagination">
                                                <?php if ($past_page > 1): ?>
                                                    <a class="page-link" href="records.php?action=view&id=<?php echo $patient['pid']; ?>&past_page=<?php echo $past_page - 1; ?>">&laquo; Prev</a>
                                                <?php endif; ?>
                                                <span class="page-status">Page <?php echo $past_page; ?> of <?php echo $past_total_pages; ?></span>
                                                <?php if ($past_page < $past_total_pages): ?>
                                                    <a class="page-link" href="records.php?action=view&id=<?php echo $patient['pid']; ?>&past_page=<?php echo $past_page + 1; ?>">Next &raquo;</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <p>No past completed appointments found.</p>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Informed Consent section removed -->
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="records.php"><input type="button" value="Close"
                                            class="login-btn btn-primary-soft btn" style="width: 100%;"></a>
                                </td>
                            </tr>
                        </table>
                    </div>
                </center>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Function to clear search and redirect
        function clearSearch() {
            window.location.href = 'records.php';
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

            // Close popup when clicking outside of it
            const overlay = document.querySelector('.overlay');
            if (overlay) {
                overlay.addEventListener('click', function (e) {
                    if (e.target === this) {
                        window.location.href = 'records.php';
                    }
                });
            }
        });
    </script>
    <script>
        // Mobile sidebar toggle for Records page
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
