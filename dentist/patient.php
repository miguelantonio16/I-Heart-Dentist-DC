<?php
session_start();

if (!isset($_SESSION["user"])) {
    header("location: login.php");
    exit();
}

if ($_SESSION['usertype'] != 'd') {
    header("location: login.php");
    exit();
}

// Import database connection
include("../connection.php");
date_default_timezone_set('Asia/Singapore');

$useremail = $_SESSION["user"];
$userrow = $database->query("SELECT * FROM doctor WHERE docemail='$useremail'");
$userfetch = $userrow->fetch_assoc();
$userid = $userfetch["docid"];
$username = $userfetch["docname"];

$patientrow = $database->query("SELECT COUNT(DISTINCT pid) FROM appointment WHERE docid='$userid'");
$appointmentrow = $database->query("SELECT COUNT(*) FROM appointment WHERE status='booking' AND docid='$userid'");
$schedulerow = $database->query("SELECT COUNT(*) FROM appointment WHERE status='appointment' AND docid='$userid'");

$today = date('Y-m-d');
$currentMonth = date('F');
$currentYear = date('Y');
$daysInMonth = date('t');
$firstDayOfMonth = date('N', strtotime("$currentYear-" . date('m') . "-01"));
$currentDay = date('j');

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

if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $sqlmain = "SELECT DISTINCT patient.*, b.name AS branch_name FROM appointment INNER JOIN patient ON appointment.pid = patient.pid LEFT JOIN branches b ON patient.branch_id = b.id 
               WHERE appointment.docid = '$userid' AND patient.status='active' 
               AND (patient.pname LIKE '%$search%' OR patient.pemail LIKE '%$search%' OR patient.ptel LIKE '%$search%') 
               ORDER BY patient.pname $sort_order LIMIT $start_from, $results_per_page";

    $sqlmain_inactive = "SELECT DISTINCT patient.*, b.name AS branch_name FROM appointment INNER JOIN patient ON appointment.pid = patient.pid LEFT JOIN branches b ON patient.branch_id = b.id 
                        WHERE appointment.docid = '$userid' AND patient.status='inactive' 
                        AND (patient.pname LIKE '%$search%' OR patient.pemail LIKE '%$search%' OR patient.ptel LIKE '%$search%')";

    $count_query = "SELECT COUNT(DISTINCT patient.pid) as total FROM appointment INNER JOIN patient ON appointment.pid = patient.pid 
                   WHERE appointment.docid = '$userid' AND patient.status='active' 
                   AND (patient.pname LIKE '%$search%' OR patient.pemail LIKE '%$search%' OR patient.ptel LIKE '%$search%')";
} else {
    $sqlmain = "SELECT DISTINCT patient.*, b.name AS branch_name FROM appointment INNER JOIN patient ON appointment.pid = patient.pid LEFT JOIN branches b ON patient.branch_id = b.id 
               WHERE appointment.docid = '$userid' AND patient.status='active' 
               ORDER BY patient.pname $sort_order LIMIT $start_from, $results_per_page";

    $sqlmain_inactive = "SELECT DISTINCT patient.*, b.name AS branch_name FROM appointment INNER JOIN patient ON appointment.pid = patient.pid LEFT JOIN branches b ON patient.branch_id = b.id 
                        WHERE appointment.docid = '$userid' AND patient.status='inactive'";

    $count_query = "SELECT COUNT(DISTINCT patient.pid) as total FROM appointment INNER JOIN patient ON appointment.pid = patient.pid 
                   WHERE appointment.docid = '$userid' AND patient.status='active'";
}

$result_active = $database->query($sqlmain);
$active_count = $result_active->num_rows;

$result_inactive = $database->query($sqlmain_inactive);
$inactive_count = $result_inactive->num_rows;

$count_result = $database->query($count_query);
$count_row = $count_result->fetch_assoc();
$total_pages = ceil($count_row['total'] / $results_per_page);

if (isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['id'])) {
    $id = $_GET['id'];

    // Fetch patient details from the database
    $sqlmain = "SELECT * FROM patient WHERE pid = ?";
    $stmt = $database->prepare($sqlmain);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $name = $row["pname"];
        $email = $row["pemail"];
        $dob = $row["pdob"];
        $tel = $row["ptel"];
        $address = $row["paddress"];
        $status = $row["status"];
        $profile_pic = !empty($row["profile_pic"]) ? "../" . $row["profile_pic"] : "../Media/Icon/Blue/profile.png";

        $sqlHistory = "SELECT * FROM medical_history WHERE email='$email'";
        $resultHistory = $database->query($sqlHistory);

        echo '
        <div id="patientModal" class="modal-container">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <div class="modal-body">
                    <table width="80%" class="patient-details-table" border="0">
                        <tr>
                            <td class="label-td" colspan="2">
                                <img src="' . $profile_pic . '" alt="Patient Photo"
                                    style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 20px;">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">View Details</p><br><br>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2"><label for="name" class="form-label">Patient ID: </label></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">P-' . $id . '<br><br></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2"><label for="name" class="form-label">Name: </label></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">' . $name . '<br><br></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2"><label for="Email" class="form-label">Email: </label></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">' . $email . '<br><br></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2"><label for="Tele" class="form-label">Telephone: </label></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">' . $tel . '<br><br></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2"><label for="spec" class="form-label">Address: </label></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">' . $address . '<br><br></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2"><label for="name" class="form-label">Date of Birth: </label></td>
                        </tr>
                        <tr>
                            <td class="label-td" colspan="2">' . $dob . '<br><br></td>
                        </tr>';

        if ($resultHistory->num_rows > 0) {
            $rowHistory = $resultHistory->fetch_assoc();
            echo '
                        <tr>
                            <td colspan="2" style="padding-top: 20px; text-align: center;">
                                <h3>Medical History</h3>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;"><strong>Good Health:</strong></td>
                            <td style="padding: 10px;">' . htmlspecialchars($rowHistory["good_health"] ?? "No") . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;"><strong>Under Treatment:</strong></td>
                            <td style="padding: 10px;">' . htmlspecialchars($rowHistory["under_treatment"] ?? "No") . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;"><strong>Had a serious surgical operation:</strong></td>
                            <td style="padding: 10px;">' . htmlspecialchars($rowHistory["condition_treated"] ?: "No") . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;"><strong>Had a serious illness:</strong></td>
                            <td style="padding: 10px;">' . htmlspecialchars($rowHistory["serious_illness"] ?? "No") . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;"><strong>Hospitalized:</strong></td>
                            <td style="padding: 10px;">' . htmlspecialchars($rowHistory["hospitalized"] ?? "No") . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;"><strong>Taking any prescription/non-prescription medication:</strong></td>
                            <td style="padding: 10px;">' . htmlspecialchars($rowHistory["medication"] ?? "No") . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;"><strong>Medication Specify:</strong></td>
                            <td style="padding: 10px;">' . htmlspecialchars($rowHistory["medication_specify"] ?: "-") . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;"><strong>Use Tobacco:</strong></td>
                            <td style="padding: 10px;">' . htmlspecialchars($rowHistory["tobacco"] ?? "No") . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;"><strong>Use Alcohol or Dangerous Drugs:</strong></td>
                            <td style="padding: 10px;">' . htmlspecialchars($rowHistory["drugs"] ?? "No") . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;"><strong>Have Allergies:</strong></td>
                            <td style="padding: 10px;">' . htmlspecialchars($rowHistory["allergies"] ?: "No") . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;"><strong>Blood Pressure:</strong></td>
                            <td style="padding: 10px;">' . htmlspecialchars($rowHistory["blood_pressure"] ?: "-") . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;"><strong>Bleeding Time:</strong></td>
                            <td style="padding: 10px;">' . htmlspecialchars($rowHistory["bleeding_time"] ?: "-") . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;"><strong>Health Conditions:</strong></td>
                            <td style="padding: 10px;">' . htmlspecialchars($rowHistory["health_conditions"] ?: "None") . '</td>
                        </tr>';
        } else {
            echo '
                        <tr>
                            <td colspan="2" style="padding: 20px; text-align: center;">
                                <p>No medical history found for this patient.</p>
                            </td>
                        </tr>';
        }

        echo '
                        <tr>
                            <td colspan="2">
                                <a href="patient.php"><input type="button" value="OK" class="login-btn btn-primary-soft btn"></a>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>';
    } else {
        echo "<script>alert('Patient not found!');</script>";
        header("Location: patient.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/table.css">
    <title>Patient - IHeartDentistDC</title>
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">

    <style>
        /* New Modal Styles */
        .modal-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 10px;
            width: 80%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 28px;
            font-weight: bold;
            color: #333;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .modal-close:hover {
            color: #000;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .patient-details-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .patient-details-table tr td {
            padding: 8px 0;
        }
        
        .label-td {
            font-weight: 600;
            color: #555;
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
            width: 320px;
        }

        .stats-container {
            display: flex;
            flex-direction: column;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .profile-img-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .inactive-table {
            opacity: 0.8;
        }

        .table-section {
            margin-bottom: 40px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .table-title {
            font-size: 18px;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
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
                    <?php
                    $userphoto = $userfetch["photo"];
                    if (!empty($userphoto) && file_exists("../admin/uploads/" . $userphoto)) {
                        $photopath = "../admin/uploads/" . $userphoto;
                    } else {
                        $photopath = "../Media/Icon/Blue/profile.png";
                    }
                    ?>
                    <img src="<?php echo $photopath; ?>" alt="Profile" class="profile-img">
                </div>
                <h3 class="profile-name"><?php echo substr($username, 0, 25); ?></h3>
                <p style="color: #777; margin: 0; font-size: 14px; text-align: center;">
                    <?php echo substr($useremail, 0, 30); ?>
                </p>
            </div>

            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <img src="../Media/Icon/Blue/home.png" alt="Home" class="nav-icon">
                    <span class="nav-label">Dashboard</span>
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
                <a href="patient.php" class="nav-item active">
                    <img src="../Media/Icon/Blue/care.png" alt="Patient" class="nav-icon">
                    <span class="nav-label">Patient</span>
                </a>
                <a href="dentist-records.php" class="nav-item">
                    <img src="../Media/Icon/Blue/edit.png" alt="Records" class="nav-icon">
                    <span class="nav-label">Records</span>
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
                        <h3 class="announcements-title">Manage Patients</h3>
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

                    <!-- Active Patients Table -->
                    <div class="table-section">
                        <h3 class="table-title">Active Patients (<?php echo $active_count; ?>)</h3>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Profile</th>
                                        <th>Name</th>
                                        <th>Branch</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Date of Birth</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result_active->num_rows == 0) {
                                        echo '<tr>
                                            <td colspan="7">
                                            <br><br><br><br>
                                            <center>
                                            <img src="../img/notfound.svg" width="25%">
                                           
                                            <br>
                                            <p class="heading-main12" style="margin-left: 45px;font-size:20px;color:rgb(49, 49, 49)">No active patients found!</p>
                                            </center>
                                            <br><br><br><br>
                                            </td>
                                            </tr>';
                                    } else {
                                        while ($row = $result_active->fetch_assoc()) {
                                            $pid = $row["pid"];
                                            $name = $row["pname"];
                                            $email = $row["pemail"];
                                            $dob = $row["pdob"];
                                            $tel = $row["ptel"];
                                            $status = $row["status"];
                                            $profile_pic = !empty($row["profile_pic"]) ? "../" . $row["profile_pic"] : "../Media/Icon/Blue/profile.png";

                                            echo '<tr>
                                                <td>
                                                    <img src="' . $profile_pic . '" alt="' . $name . '" class="profile-img-small">
                                                </td>
                                                <td><div class="cell-text">' . $name . '</div></td>
                                                <td><div class="cell-text">' . (isset($row['branch_name']) ? htmlspecialchars($row['branch_name']) : '-') . '</div></td>
                                                <td><div class="cell-text">' . $email . '</div></td>
                                                <td><div class="cell-text">' . $tel . '</div></td>
                                                <td><div class="cell-text">' . $dob . '</div></td>
                                                <td><span class="status-badge status-active">Active</span></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="?action=view&id=' . $pid . '" class="action-btn view-btn">View</a>
                                                    </div>
                                                </td>
                                            </tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
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
                </div>

                <!-- Right sidebar section -->
                <div class="right-sidebar">
                    <div class="stats-section">
                        <div class="stats-container">
                            <!-- First row -->
                            <a href="patient.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $patientrow->fetch_row()[0] ?? 0; ?></h1>
                                        <p class="stat-label">My Patients</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/care.png" alt="Patients Icon">
                                    </div>
                                </div>
                            </a>

                            <!-- Second row -->
                            <a href="booking.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php
                                        $bookingCount = $appointmentrow->fetch_row()[0] ?? 0;
                                        echo $bookingCount;
                                        ?></h1>
                                        <p class="stat-label">Bookings</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/booking.png" alt="Booking Icon">
                                        <?php if ($bookingCount > 0): ?>
                                            <span class="notification-badge"><?php echo $bookingCount; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>

                            <a href="appointment.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php
                                        $appointmentCount = $schedulerow->fetch_row()[0] ?? 0;
                                        echo $appointmentCount;
                                        ?></h1>
                                        <p class="stat-label">Appointments</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/appointment.png" alt="Appointment Icon">
                                        <?php if ($appointmentCount > 0): ?>
                                            <span class="notification-badge"><?php echo $appointmentCount; ?></span>
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
                                    patient.pname as patient_name
                                FROM appointment
                                INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
                                INNER JOIN patient ON appointment.pid = patient.pid
                                WHERE
                                    appointment.docid = '$userid'
                                    AND appointment.status = 'appointment'
                                    AND appointment.appodate >= '$today'
                                ORDER BY appointment.appodate ASC
                                LIMIT 3;
                            ");

                            if ($upcomingAppointments->num_rows > 0) {
                                while ($appointment = $upcomingAppointments->fetch_assoc()) {
                                    echo '<div class="appointment-item">
                                        <h4 class="appointment-type">' . htmlspecialchars($appointment['patient_name']) . '</h4>
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

    <script>
        // Function to clear search and redirect
        function clearSearch() {
            window.location.href = 'patient.php';
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

        // Show modal if URL has view action parameter
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');

            if (action === 'view') {
                const modal = document.getElementById('patientModal');
                if (modal) {
                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
            }

            // Close modal when clicking close button
            const closeButton = document.querySelector('.modal-close');
            if (closeButton) {
                closeButton.addEventListener('click', function () {
                    const modal = this.closest('.modal-container');
                    if (modal) {
                        modal.style.display = 'none';
                        document.body.style.overflow = '';
                        // Remove the parameters from URL without reloading
                        const url = new URL(window.location);
                        url.searchParams.delete('action');
                        url.searchParams.delete('id');
                        window.history.replaceState({}, '', url.toString());
                    }
                });
            }

            // Close modal when clicking outside of it
            const modal = document.querySelector('.modal-container');
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                        document.body.style.overflow = '';
                        // Remove the parameters from URL without reloading
                        const url = new URL(window.location);
                        url.searchParams.delete('action');
                        url.searchParams.delete('id');
                        window.history.replaceState({}, '', url.toString());
                    }
                });
            }
        });
    </script>
</body>

</html>
