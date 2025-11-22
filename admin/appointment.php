<?php
date_default_timezone_set('Asia/Singapore');
session_start();
$today = date('Y-m-d');

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" or $_SESSION['usertype'] != 'a') {
        header("location: ../login.php");
        exit();
    }
} else {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

// Get counts for dashboard
$dentistrow = $database->query("select * from doctor where status='active';");
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

$start_from = ($page - 1) * $results_per_page;

// Search functionality
$search = "";
$sort_param = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$sort_order = ($sort_param === 'oldest') ? 'DESC' : 'ASC';

// Calendar variables
$today = date('Y-m-d');
$currentMonth = date('F');
$currentYear = date('Y');
$daysInMonth = date('t');
$firstDayOfMonth = date('N', strtotime("$currentYear-" . date('m') . "-01"));
$currentDay = date('j');

// Success message
if (isset($_GET['cancel_success'])) {
    echo '
    <div class="success-message" style="position: fixed; top: 20px; right: 20px; background: #4CAF50; color: white; padding: 15px; border-radius: 5px; z-index: 10000; animation: fadeIn 0.5s, fadeOut 0.5s 2.5s;">
        Appointment cancelled successfully!
    </div>';
}
// Success message for completion
if (isset($_GET['action']) && $_GET['action'] == 'completed') {
    echo '
    <div class="success-message" style="position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 15px; border-radius: 5px; z-index: 10000; animation: fadeIn 0.5s, fadeOut 0.5s 2.5s;">
        Appointment marked as Completed!
    </div>';
}


// Action Buttons

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
    <title>Appointments - IHeartDentistDC</title>
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .form-select, .form-input {
            width: 100%;
            padding: 8px 12px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-complete {
    background-color: #28a745; /* Green */
    color: white;
    background-image: url('../Media/Icon/White/check.png'); /* Assuming you have a check icon, or remove this line */
    background-repeat: no-repeat;
    background-position: 10px center;
    background-size: 16px;
    padding-left: 30px;
}
.btn-complete:hover {
    background-color: #218838;
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
                <a href="appointment.php" class="nav-item active">
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

                            <a href="history.php" class="filter-btn add-btn">
                                Past Appointments
                            </a>
                        </div>
                    </div>

                    <?php
                    $sqlmain = "SELECT 
    appointment.appoid, 
    appointment.pid, 
    appointment.appodate, 
    appointment.procedure_id, 
    procedures.procedure_name, 
    appointment.appointment_time, 
    appointment.docid, 
    appointment.payment_status,  /* ADDED */
    appointment.status,          /* ADDED */
    patient.pname, 
    patient.pemail,
    patient.ptel,
    patient.profile_pic,
    doctor.docname 
FROM appointment 
INNER JOIN patient ON appointment.pid = patient.pid 
INNER JOIN doctor ON appointment.docid = doctor.docid 
INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id 
WHERE appointment.status = 'appointment'";

                    // Add search condition if search term exists
                    if (isset($_GET['search'])) {
                        $search = $_GET['search'];
                        $sqlmain .= " AND (patient.pname LIKE '%$search%' OR doctor.docname LIKE '%$search%' OR procedures.procedure_name LIKE '%$search%')";
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

                    // Count query for pagination
                    $count_query = "SELECT COUNT(*) as total 
               FROM appointment 
               INNER JOIN patient ON appointment.pid = patient.pid 
               INNER JOIN doctor ON appointment.docid = doctor.docid 
               INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id 
               WHERE appointment.status = 'appointment'";

                    if (isset($_GET['search'])) {
                        $count_query .= " AND (patient.pname LIKE '%$search%' OR doctor.docname LIKE '%$search%' OR procedures.procedure_name LIKE '%$search%')";
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
        <th>Dentist</th>
        <th>Procedure</th>
        <th>Appointment Date</th>
        <th>Appointment Time</th>
        <th>Payment Status</th> <th>Actions</th>
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
                                                <div class="cell-text"><?php echo $row['docname']; ?></div>
                                            </td>
                                            <td>
                                                <div class="cell-text"><?php echo $row['procedure_name']; ?></div>
                                            </td>
                                            
                                            <td>
    <div class="cell-text">
        <?php echo date('M j, Y', strtotime($row['appodate'])); ?></div>
</td>

<td>
    <div class="cell-text">
        <?php echo date('g:i A', strtotime($row['appointment_time'])); ?>
    </div>
</td>

<td>
    <div class="cell-text">
        <?php
        $payStatus = isset($row['payment_status']) ? $row['payment_status'] : 'unpaid';
        
        if ($payStatus == 'paid') {
            echo '<span style="color:green; font-weight:bold;">Paid Online</span>';
        } elseif ($payStatus == 'pending_cash') {
            echo '<span style="color:orange; font-weight:bold;">Cash (Pending)</span>';
        } else {
            echo '<span style="color:red;">Unpaid</span>';
        }
        ?>
    </div>
</td>

<td>
    <div class="action-buttons">
        <?php 
        // If cash is pending OR it's a standard appointment that hasn't been paid yet
        if ($payStatus == 'pending_cash' || ($row['status'] == 'appointment' && $payStatus != 'paid')): 
        ?>
            <a href="complete_appointment.php?id=<?php echo $row['appoid']; ?>&method=cash" 
               class="action-btn btn-complete"
               onclick="return confirm('Confirm cash payment received and complete appointment?');">
               Receive Cash & Complete
            </a>
        <?php endif; ?>

        <a href="?action=view&id=<?php echo $row['appoid']; ?>" class="action-btn view-btn">View</a>

        <a href="?action=drop&id=<?php echo $row['appoid']; ?>&name=<?php echo urlencode($row['pname']); ?>" class="action-btn remove-btn">Cancel</a>
    </div>
</td>
</tr>
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
                            <p>No appointments found. Please try a different search term.</p>
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
                                        <h1 class="stat-number"><?php echo $dentistrow->num_rows; ?></h1>
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
        <?php
        if ($_GET) {
            $id = $_GET["id"];
            $action = $_GET["action"];
            if ($action == 'drop') {
                $nameget = $_GET["name"];
                echo '
                <div id="popup1" class="overlay">
                    <div class="popup" style="max-height: 400px;">
                        <center>
                            <h2>Cancel Appointment</h2>
                            <a class="close" href="appointment.php">&times;</a>
                            <div class="content">
                                <form id="cancelForm" action="delete-appointment.php" method="POST">
                                    <input type="hidden" name="appoid" value="'.$id.'">
                                    <input type="hidden" name="source" value="admin">
                                    
                                    <p>You are about to cancel the appointment for <strong>'.substr($nameget, 0, 40).'</strong>.</p>
                                    
                                    <div style="text-align: left; margin: 20px 0;">
                                        <label for="cancel_reason" style="display: block; margin-bottom: 8px; font-weight: bold;">Reason for cancellation:</label>
                                        <select name="cancel_reason" id="cancel_reason" class="form-select" required>
                                            <option value="">-- Select a reason --</option>
                                            <option value="Patient request">Patient request</option>
                                            <option value="Dentist unavailable">Dentist unavailable</option>
                                            <option value="Clinic emergency">Clinic emergency</option>
                                            <option value="Rescheduled">Rescheduled</option>
                                            <option value="Other">Other (please specify)</option>
                                        </select>
                                        
                                        <div id="otherReasonContainer" style="margin-top: 10px; display: none;">
                                            <label for="other_reason" style="display: block; margin-bottom: 8px;">Please specify:</label>
                                            <input type="text" name="other_reason" id="other_reason" class="form-input" style="width: 100%;">
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: center; gap: 15px; margin-top: 20px;">
                                        <button type="submit" class="btn-primary btn" style="padding: 10px 20px;">Confirm Cancellation</button>
                                        <a href="appointment.php" class="btn btn-secondary" style="padding: 10px 20px;">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </center>
                    </div>
                </div>
                
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        const reasonSelect = document.getElementById("cancel_reason");
                        const otherReasonContainer = document.getElementById("otherReasonContainer");
                        
                        reasonSelect.addEventListener("change", function() {
                            if (this.value === "Other") {
                                otherReasonContainer.style.display = "block";
                            } else {
                                otherReasonContainer.style.display = "none";
                            }
                        });
                        
                        // Handle form submission
                        document.getElementById("cancelForm").addEventListener("submit", function(e) {
                            e.preventDefault();
                            
                            const formData = new FormData(this);
                            
                            fetch(this.action, {
                                method: "POST",
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status) {
                                    // Show success message and reload
                                    alert(data.message);
                                    window.location.href = "appointment.php?cancel_success=1";
                                } else {
                                    alert("Error: " + data.message);
                                }
                            })
                            .catch(error => {
                                console.error("Error:", error);
                                alert("An error occurred. Please try again.");
                            });
                        });
                    });
                </script>
                ';
            } elseif ($action == 'view') {
                $sqlmain = "SELECT 
                                appointment.appoid, 
                                appointment.pid, 
                                appointment.appodate, 
                                appointment.procedure_id, 
                                procedures.procedure_name, 
                                appointment.appointment_time, 
                                appointment.docid, 
                                patient.pname, 
                                patient.pemail,
                                patient.ptel,
                                doctor.docname
                            FROM appointment 
                            INNER JOIN patient ON appointment.pid = patient.pid 
                            INNER JOIN doctor ON appointment.docid = doctor.docid 
                            INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id 
                            WHERE appointment.appoid='$id'";
                $result = $database->query($sqlmain);
                $row = $result->fetch_assoc();
                $patient_name = $row["pname"];
                $dentist_name = $row["docname"];
                $procedure_name = $row["procedure_name"];
                $appodate = $row["appodate"];
                $appointment_time = $row["appointment_time"];
                $patient_email = $row["pemail"];
                $patient_tel = $row["ptel"];

                echo '
                <div id="popup1" class="overlay">
                    <div class="popup">
                        <center>
                            <h2>Appointment Details</h2>
                            <a class="close" href="appointment.php">&times;</a>
                            <div class="content">
                                <table width="100%" class="sub-table scrolldown add-doc-form-container" border="0">
                                    <tr>
                                        <td class="label-td" style="width: 30%;">
                                            <label for="name" class="form-label">Patient Name:</label>
                                        </td>
                                        <td>' . htmlspecialchars($patient_name) . '</td>
                                    </tr>
                                    <tr>
                                        <td class="label-td">
                                            <label for="Email" class="form-label">Patient Email:</label>
                                        </td>
                                        <td>' . htmlspecialchars($patient_email) . '</td>
                                    </tr>
                                    <tr>
                                        <td class="label-td">
                                            <label for="Tele" class="form-label">Patient Phone:</label>
                                        </td>
                                        <td>' . htmlspecialchars($patient_tel) . '</td>
                                    </tr>
                                    <tr>
                                        <td class="label-td">
                                            <label for="dentist" class="form-label">Dentist:</label>
                                        </td>
                                        <td>' . htmlspecialchars($dentist_name) . '</td>
                                    </tr>
                                    <tr>
                                        <td class="label-td">
                                            <label for="procedure" class="form-label">Procedure:</label>
                                        </td>
                                        <td>' . htmlspecialchars($procedure_name) . '</td>
                                    </tr>
                                    <tr>
                                        <td class="label-td">
                                            <label for="date" class="form-label">Appointment Date:</label>
                                        </td>
                                        <td>' . htmlspecialchars(date('F j, Y', strtotime($appodate))) . '</td>
                                    </tr>
                                    <tr>
                                        <td class="label-td">
                                            <label for="time" class="form-label">Appointment Time:</label>
                                        </td>
                                        <td>' . htmlspecialchars(date('g:i A', strtotime($appointment_time))) . '</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="padding-top: 20px;">
                                            <a href="appointment.php"><input type="button" value="OK" class="login-btn btn-primary-soft btn"></a>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </center>
                    </div>
                </div>';
            }
        }
        ?>

        <script>
            // Function to clear search and redirect
            function clearSearch() {
                window.location.href = 'appointment.php';
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
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Show popup if URL has any action parameter
                const urlParams = new URLSearchParams(window.location.search);
                const action = urlParams.get('action');

                if (action === 'view' || action === 'edit' || action === 'drop' || action === 'add') {
                    const popup = document.getElementById('popup1');
                    if (popup) {
                        popup.style.display = 'flex';
                        document.body.style.overflow = 'hidden';
                    }
                }

                // Close button functionality
                const closeButtons = document.querySelectorAll('.close');
                closeButtons.forEach(button => {
                    button.addEventListener('click', function (e) {
                        e.preventDefault();
                        const overlay = this.closest('.overlay');
                        if (overlay) {
                            overlay.style.display = 'none';
                            document.body.style.overflow = '';
                            // Remove the parameters from URL without reloading
                            const url = new URL(window.location);
                            url.searchParams.delete('action');
                            url.searchParams.delete('id');
                            url.searchParams.delete('name');
                            url.searchParams.delete('error');
                            history.pushState(null, '', url);
                        }
                    });
                });

                // Close popup when clicking outside of it
                const overlays = document.querySelectorAll('.overlay');
                overlays.forEach(overlay => {
                    overlay.addEventListener('click', function (e) {
                        if (e.target === this) {
                            this.style.display = 'none';
                            document.body.style.overflow = '';
                            // Remove the parameters from URL without reloading
                            const url = new URL(window.location);
                            url.searchParams.delete('action');
                            url.searchParams.delete('id');
                            url.searchParams.delete('name');
                            url.searchParams.delete('error');
                            history.pushState(null, '', url);
                        }
                    });
                });
            });
        </script>
    </div>
</body>
</html>
