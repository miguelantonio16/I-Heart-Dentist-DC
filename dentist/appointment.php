<?php
date_default_timezone_set('Asia/Singapore');
session_start();

if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: login.php");
    exit();
}

include("../connection.php");
date_default_timezone_set('Asia/Singapore');

$useremail = $_SESSION["user"];
$userrow = $database->query("SELECT * FROM doctor WHERE docemail='$useremail'");
$userfetch = $userrow->fetch_assoc();
$userid = $userfetch["docid"];
$username = $userfetch["docname"];

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
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort_param = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$sort_order = ($sort_param === 'oldest') ? 'DESC' : 'ASC';


$today = date('Y-m-d');
$currentMonth = date('F');
$currentYear = date('Y');
$daysInMonth = date('t');
$firstDayOfMonth = date('N', strtotime("$currentYear-" . date('m') . "-01"));
$currentDay = date('j');

// Get counts for sidebar
$patientrow = $database->query("SELECT COUNT(DISTINCT pid) FROM appointment WHERE docid='$userid'");
$appointmentrow = $database->query("SELECT COUNT(*) FROM appointment WHERE status='booking' AND docid='$userid'");
$schedulerow = $database->query("SELECT COUNT(*) FROM appointment WHERE status='appointment' AND docid='$userid'");
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
    <title>My Appointments - IHeartDentistDC</title>
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
            width: 400px;
            max-width: 90%;
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

        .cancel-reason {
            width: 100%;
            margin: 15px 0;
        }

        .cancel-reason select, .cancel-reason textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 5px;
        }

        .cancel-reason textarea {
            height: 80px;
            resize: vertical;
            display: none;
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

        .stat-box {
            height: 100%;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #f44336;
            color: white;
            border-radius: 50%;
            padding: 3px 6px;
            font-size: 12px;
        }

        .stat-icon {
            position: relative;
        }
        .btn-complete {
    background-color: #28a745;
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 14px;
    margin-right: 5px;
    display: inline-flex;
    align-items: center;
}
.btn-complete:hover {
    background-color: #218838;
}
    </style>
</head>

<body>
    <button class="hamburger-admin show-mobile" id="sidebarToggle" aria-label="Toggle navigation" aria-controls="adminSidebar" aria-expanded="false">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="main-container">
        <div class="sidebar" id="adminSidebar">
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
                <a href="appointment.php" class="nav-item active">
                    <img src="../Media/Icon/Blue/appointment.png" alt="Appointment" class="nav-icon">
                    <span class="nav-label">Appointment</span>
                </a>
                <a href="patient.php" class="nav-item">
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
                                placeholder="Search by patient name, dentist name or procedure"
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <?php if (isset($_GET['search']) && $_GET['search'] != ""): ?>
                                <button type="button" class="clear-btn" onclick="clearSearch()">×</button>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- header -->
                    <div class="announcements-header">
                        <h3 class="announcements-title">Manage Appointments</h3>
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

                    <?php
                                        $sqlmain = "SELECT 
                                                                                    appointment.appoid, 
                                                                                    procedures.procedure_name, 
                                                                                    patient.pname, 
                                                                                    patient.pemail,
                                                                                    patient.profile_pic,
                                                                                    appointment.appodate, 
                                                                                    appointment.appointment_time,
                                                                                    b.name AS branch_name
                                                                            FROM appointment
                                                                            INNER JOIN patient ON appointment.pid = patient.pid
                                                                            LEFT JOIN procedures ON appointment.procedure_id = procedures.procedure_id
                                                                            LEFT JOIN branches b ON patient.branch_id = b.id
                                                                            WHERE appointment.docid = '$userid' 
                                                                                AND appointment.status = 'appointment'";

                                        // Add search filter (patient name or procedure)
                                        if (!empty($search)) {
                                            $s = $database->real_escape_string($search);
                                            $sqlmain .= " AND (patient.pname LIKE '%$s%' OR procedures.procedure_name LIKE '%$s%')";
                                        }

                    if (isset($_POST['filter'])) {
                        $filterDate = $_POST['appodate'];
                        if (!empty($filterDate)) {
                            $sqlmain .= " AND appointment.appodate = '$filterDate'";
                        }
                    }

                    // Add sorting
                    $sqlmain .= " ORDER BY ";
                    if ($sort_param === 'oldest') {
                        $sqlmain .= "appointment.appodate DESC, appointment.appointment_time DESC";
                    } else {
                        $sqlmain .= "appointment.appodate ASC, appointment.appointment_time ASC";
                    }

                    // Add pagination
                    $sqlmain .= " LIMIT $start_from, $results_per_page";

                    $result = $database->query($sqlmain);

                    // Count query for pagination (only this doctor's appointments)
                    $count_query = "SELECT COUNT(*) as total 
               FROM appointment 
               INNER JOIN patient ON appointment.pid = patient.pid 
               LEFT JOIN procedures ON appointment.procedure_id = procedures.procedure_id 
               LEFT JOIN branches b ON patient.branch_id = b.id
               WHERE appointment.status = 'appointment' AND appointment.docid = '$userid'";

                    if (!empty($search)) {
                        $s2 = $database->real_escape_string($search);
                        $count_query .= " AND (patient.pname LIKE '%$s2%' OR procedures.procedure_name LIKE '%$s2%')";
                    }

                    $count_result = $database->query($count_query);
                    $count_row = $count_result->fetch_assoc();
                    $total_pages = ceil($count_row['total'] / $results_per_page);
                    ?>

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Profile</th>
                                        <th>Patient Name</th>
                                        <th>Branch</th>
                                        <th>Procedure</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr id="row-<?php echo $row['appoid']; ?>">
                                            <td>
                                                <?php
                                                $profilePicRaw = isset($row['profile_pic']) ? trim($row['profile_pic']) : '';
                                                if ($profilePicRaw === '' || $profilePicRaw === 'default.jpg' || $profilePicRaw === 'default.png') {
                                                    $photo = "../Media/Icon/Blue/profile.png";
                                                } else {
                                                    $cleanPath = ltrim($profilePicRaw, '/');
                                                    $photo = "../" . $cleanPath;
                                                }
                                                ?>
                                                <img src="<?php echo $photo; ?>" alt="<?php echo $row['pname']; ?>"
                                                    class="profile-img-small">
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo $row['pname']; ?></div>
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo isset($row['branch_name']) ? htmlspecialchars($row['branch_name']) : '-'; ?></div>
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo !empty($row['procedure_name']) ? htmlspecialchars($row['procedure_name']) : 'For Clinic Assessment'; ?></div>
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo $row['appodate']; ?></div>
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo $row['appointment_time']; ?></div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
    <a href="#" onclick="showCancelModal(<?php echo $row['appoid']; ?>, '<?php echo $row['pname']; ?>')" class="action-btn remove-btn">Cancel</a>
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
                            <p>No appointments found. Please try a different filter.</p>
                        </div>
                    <?php endif; ?>
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
                                    a.appoid,
                                    p.pname AS patient_name,
                                    b.name AS branch_name,
                                    a.appodate,
                                    a.appointment_time,
                                    COALESCE(
                                        CONCAT_WS(', ',
                                            NULLIF(pr.procedure_name, ''),
                                            NULLIF(GROUP_CONCAT(DISTINCT pr2.procedure_name ORDER BY pr2.procedure_name SEPARATOR ', '), '')
                                        ),
                                        pr.procedure_name
                                    ) AS procedures
                                FROM appointment a
                                INNER JOIN patient p ON a.pid = p.pid
                                LEFT JOIN branches b ON b.id = a.branch_id
                                LEFT JOIN procedures pr ON a.procedure_id = pr.procedure_id
                                LEFT JOIN appointment_procedures ap ON a.appoid = ap.appointment_id
                                LEFT JOIN procedures pr2 ON ap.procedure_id = pr2.procedure_id
                                WHERE
                                    a.docid = '$userid'
                                    AND a.status IN ('appointment', 'booking')
                                    AND a.appodate >= '$today'
                                GROUP BY a.appoid, p.pname, b.name, a.appodate, a.appointment_time, pr.procedure_name
                                ORDER BY a.appodate ASC, a.appointment_time ASC
                                LIMIT 3;
                            ");

                            if ($upcomingAppointments->num_rows > 0) {
                                while ($appointment = $upcomingAppointments->fetch_assoc()) {
                                    $proc = htmlspecialchars($appointment['procedures'] ?? '');
                                    $patient = htmlspecialchars($appointment['patient_name'] ?? '');
                                    $branch = htmlspecialchars($appointment['branch_name'] ?? '');
                                    $dateLine = htmlspecialchars(date('F j, Y', strtotime($appointment['appodate']))) . ' • ' . htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time'])));
                                    $suffix = $branch ? (' - ' . $branch) : '';
                                    echo '<div class="appointment-item">'
                                        . '<h4 class="appointment-type">' . ($proc !== '' ? $proc : 'Appointment') . '</h4>'
                                        . '<p class="appointment-date">With ' . $patient . '</p>'
                                        . '<p class="appointment-date">' . $dateLine . $suffix . '</p>'
                                    . '</div>';
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

    <div id="cancelModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h2>Cancel Appointment</h2>
            <p>You are about to cancel an appointment for <span id="patientName"></span></p>
            
            <div class="cancel-reason">
                <label for="cancelReason">Reason for cancellation:</label>
                <select id="cancelReason" class="form-control">
                    <option value="">-- Select a reason --</option>
                    <option value="Dentist Unavailable">Dentist Unavailable</option>
                    <option value="Emergency Situation">Emergency Situation</option>
                    <option value="Patient Request">Patient Request</option>
                    <option value="Clinic Closed">Clinic Closed</option>
                    <option value="Other">Other (please specify)</option>
                </select>
                <textarea id="otherReason" placeholder="Please specify the reason..." class="form-control"></textarea>
            </div>
            
            <div class="modal-buttons">
                <button id="confirmCancelBtn" class="btn-primary">Confirm</button>
                <button id="cancelCancelBtn" class="btn-secondary">Close</button>
            </div>
        </div>
    </div>

    <script>
        let currentAppointmentId = null;
        
        function showCancelModal(appoid, patientName) {
            currentAppointmentId = appoid;
            document.getElementById('patientName').textContent = patientName;
            document.getElementById('cancelModal').style.display = 'flex';
            
            document.getElementById('cancelReason').value = '';
            document.getElementById('otherReason').value = '';
            document.getElementById('otherReason').style.display = 'none';
        }
        
        document.getElementById('cancelReason').addEventListener('change', function() {
            const otherReason = document.getElementById('otherReason');
            if (this.value === 'Other') {
                otherReason.style.display = 'block';
                otherReason.required = true;
            } else {
                otherReason.style.display = 'none';
                otherReason.required = false;
            }
        });
        
        document.getElementById('cancelCancelBtn').addEventListener('click', function() {
            document.getElementById('cancelModal').style.display = 'none';
        });
        
        document.getElementById('confirmCancelBtn').addEventListener('click', function() {
            const reason = document.getElementById('cancelReason').value;
            const otherReason = document.getElementById('otherReason').value;
            
            if (!reason) {
                alert('Please select a cancellation reason');
                return;
            }
            
            if (reason === 'Other' && !otherReason) {
                alert('Please specify the cancellation reason');
                return;
            }
            
            const fullReason = reason === 'Other' ? otherReason : reason;
            
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            
            fetch('delete-appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${currentAppointmentId}&reason=${encodeURIComponent(fullReason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    alert(data.msg);
                    document.getElementById(`row-${currentAppointmentId}`).remove();
                } else {
                    alert(data.msg || 'Error cancelling appointment');
                }
                document.getElementById('cancelModal').style.display = 'none';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to cancel appointment. Please try again.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Confirm';
            });
        });
    </script>
    <script>
    // Mobile sidebar toggle with overlay and accessibility
    document.addEventListener('DOMContentLoaded', function() {
        var toggleBtn = document.getElementById('sidebarToggle');
        var sidebar = document.getElementById('adminSidebar');
        var overlay = document.getElementById('sidebarOverlay');

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('visible');
            toggleBtn.setAttribute('aria-expanded', 'true');
        }
        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('visible');
            toggleBtn.setAttribute('aria-expanded', 'false');
        }

        if (toggleBtn && sidebar && overlay) {
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (sidebar.classList.contains('open')) { closeSidebar(); } else { openSidebar(); }
            });
            overlay.addEventListener('click', closeSidebar);
            document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeSidebar(); });
        }
    });
    </script>
</body>
</html>
