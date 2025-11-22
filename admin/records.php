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

// Get totals for right sidebar
$doctorrow = $database->query("select * from doctor where status='active';");
$patientrow = $database->query("select * from patient where status='active';");
$appointmentrow = $database->query("select * from appointment where status='booking';");
$schedulerow = $database->query("select * from appointment where status='appointment';");

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
            header("Location: records.php?error=patient_not_found");
            exit();
        }

        // Fetch medical history
        $medical_sql = "SELECT * FROM medical_history WHERE email = ?";
        $stmt = $database->prepare($medical_sql);
        $stmt->bind_param("s", $patient['pemail']);
        $stmt->execute();
        $medical_result = $stmt->get_result();
        $medical_history = $medical_result->fetch_assoc();

        // Fetch informed consent
        $consent_sql = "SELECT * FROM informed_consent WHERE email = ? ORDER BY consent_date DESC LIMIT 1";
        $stmt = $database->prepare($consent_sql);
        $stmt->bind_param("s", $patient['pemail']);
        $stmt->execute();
        $consent_result = $stmt->get_result();
        $informed_consent = $consent_result->fetch_assoc();
    }
}

if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $query = "SELECT * FROM patient WHERE status='active' AND (pname LIKE '%$search%' OR pemail LIKE '%$search%' OR ptel LIKE '%$search%') ORDER BY pname $sort_order LIMIT $start_from, $results_per_page";
    $count_query = "SELECT COUNT(*) as total FROM patient WHERE status='active' AND (pname LIKE '%$search%' OR pemail LIKE '%$search%' OR ptel LIKE '%$search%')";
} else {
    $query = "SELECT * FROM patient WHERE status='active' ORDER BY pname $sort_order LIMIT $start_from, $results_per_page";
    $count_query = "SELECT COUNT(*) as total FROM patient WHERE status='active'";
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

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Profile</th>
                                        <th>Name</th>
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
                                                // Check if profile picture exists
                                                if (!empty($row['profile_pic'])) {
                                                    $photo = "../" . $row['profile_pic'];  // Adding ../ to the location of the photo
                                                } else {
                                                    $photo = "../Media/Icon/Blue/care.png"; // Default patient icon
                                                }
                                                ?>
                                                <img src="<?php echo $photo; ?>" alt="<?php echo $row['pname']; ?>"
                                                    class="profile-img-small">
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo $row['pname']; ?></div>
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
                        </div>

                        <!-- Pagination -->
                        <div class="pagination">
                            <?php
                            $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
                            $sortParam = '&sort=' . $currentSort;
                            $searchParam = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';

                            // Previous link
                            if ($page > 1) {
                                echo '<a href="?page=' . ($page - 1) . $searchParam . $sortParam . '">&laquo; Previous</a>';
                            }

                            // Page links
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<a href="?page=' . $i . $searchParam . $sortParam . '"' . ($i == $page ? ' class="active"' : '') . '>' . $i . '</a>';
                            }

                            // Next link
                            if ($page < $total_pages) {
                                echo '<a href="?page=' . ($page + 1) . $searchParam . $sortParam . '">Next &raquo;</a>';
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
                                        <h3>Medical History</h3>
                                        <?php if ($medical_history): ?>
                                            <div class="record-row">
                                                <span class="record-label">In Good Health:</span>
                                                <span><?php echo $medical_history['good_health']; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Under Medical Treatment:</span>
                                                <span><?php echo $medical_history['under_treatment']; ?></span>
                                                <?php if ($medical_history['under_treatment'] == 'Yes'): ?>
                                                    <div style="margin-left: 250px;">
                                                        <?php echo $medical_history['condition_treated']; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Serious Illness/Surgery:</span>
                                                <span><?php echo $medical_history['serious_illness']; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Hospitalized:</span>
                                                <span><?php echo $medical_history['hospitalized']; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Taking Medication:</span>
                                                <span><?php echo $medical_history['medication']; ?></span>
                                                <?php if ($medical_history['medication'] == 'Yes'): ?>
                                                    <div style="margin-left: 250px;">
                                                        <?php echo $medical_history['medication_specify']; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Tobacco Use:</span>
                                                <span><?php echo $medical_history['tobacco']; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Recreational Drug Use:</span>
                                                <span><?php echo $medical_history['drugs']; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Allergies:</span>
                                                <span><?php echo $medical_history['allergies'] ? $medical_history['allergies'] : 'None reported'; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Blood Pressure:</span>
                                                <span><?php echo $medical_history['blood_pressure'] ? $medical_history['blood_pressure'] : 'Not recorded'; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Bleeding Time:</span>
                                                <span><?php echo $medical_history['bleeding_time'] ? $medical_history['bleeding_time'] : 'Not recorded'; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Other Health Conditions:</span>
                                                <span><?php echo $medical_history['health_conditions'] ? $medical_history['health_conditions'] : 'None reported'; ?></span>
                                            </div>
                                        <?php else: ?>
                                            <p>No medical history recorded for this patient.</p>
                                        <?php endif; ?>
                                    </div>

                                    <div class="record-section">
                                        <h3>Informed Consent</h3>
                                        <?php if ($informed_consent): ?>
                                            <div class="record-row">
                                                <span class="record-label">Consent Date:</span>
                                                <span><?php echo $informed_consent['consent_date']; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Treatment to be Done:</span>
                                                <span><?php echo $informed_consent['initial_treatment_to_be_done'] == 'y' ? 'Agreed' : 'Not agreed'; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Drugs/Medications:</span>
                                                <span><?php echo $informed_consent['initial_drugs_medications'] == 'y' ? 'Agreed' : 'Not agreed'; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Changes to Treatment Plan:</span>
                                                <span><?php echo $informed_consent['initial_changes_treatment_plan'] == 'y' ? 'Agreed' : 'Not agreed'; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Radiographs (X-rays):</span>
                                                <span><?php echo $informed_consent['initial_radiograph'] == 'y' ? 'Agreed' : 'Not agreed'; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Removal of Teeth:</span>
                                                <span><?php echo $informed_consent['initial_removal_teeth'] == 'y' ? 'Agreed' : 'Not agreed'; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Crowns/Bridges:</span>
                                                <span><?php echo $informed_consent['initial_crowns_bridges'] == 'y' ? 'Agreed' : 'Not agreed'; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Endodontics (Root Canal):</span>
                                                <span><?php echo $informed_consent['initial_endodontics'] == 'y' ? 'Agreed' : 'Not agreed'; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Periodontal Disease Treatment:</span>
                                                <span><?php echo $informed_consent['initial_periodontal_disease'] == 'y' ? 'Agreed' : 'Not agreed'; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Fillings:</span>
                                                <span><?php echo $informed_consent['initial_fillings'] == 'y' ? 'Agreed' : 'Not agreed'; ?></span>
                                            </div>
                                            <div class="record-row">
                                                <span class="record-label">Dentures:</span>
                                                <span><?php echo $informed_consent['initial_dentures'] == 'y' ? 'Agreed' : 'Not agreed'; ?></span>
                                            </div>
                                            <?php if ($informed_consent['id_signature_path']): ?>
                                                <div class="record-row">
                                                    <span class="record-label">Signature:</span>
                                                    <img src="<?php echo $informed_consent['id_signature_path']; ?>"
                                                        alt="Patient Signature" class="signature-image">
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p>No informed consent recorded for this patient.</p>
                                        <?php endif; ?>
                                    </div>
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
</body>

</html>
