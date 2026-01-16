<?php
// Start the session and set timezone before any output
session_start();
date_default_timezone_set('Asia/Singapore');

// Check if user is logged in and has correct user type
if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" or $_SESSION['usertype'] != 'p') {
        header("location: login.php");
        exit; // Add exit after redirect
    } else {
        $useremail = $_SESSION["user"];
    }
} else {
    header("location: login.php");
    exit; // Add exit after redirect
}

include("../connection.php");
$userrow = $database->query("select * from patient where pemail='$useremail'");
$userfetch = $userrow->fetch_assoc();
$userid = $userfetch["pid"];
$username = $userfetch["pname"];

// Get notification data
$unreadCount = $database->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = '$userid' AND user_type = 'p' AND is_read = 0");
$unreadCount = $unreadCount->fetch_assoc()['count'];

$notifications = $database->query("SELECT * FROM notifications WHERE user_id = '$userid' AND user_type = 'p' ORDER BY created_at DESC");

// Get totals for right sidebar
$doctorrow = $database->query("select * from doctor where status='active';");
// Only count confirmed bookings for patient-facing "My Bookings" summary.
$appointmentrow = $database->query("select * from appointment where status = 'booking' AND pid='$userid';");
$schedulerow = $database->query("select * from appointment where status='appointment' AND pid='$userid';");

// Branch filter removed for consistency with My Appointments

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

// Main query with current database structure
$sqlmain = "SELECT 
            appointment.appoid, 
            procedures.procedure_name, 
            doctor.docname, 
            appointment.appodate, 
            appointment.appointment_time,
            appointment.status,
            doctor.photo,
            b.name AS branch_name
        FROM appointment
        INNER JOIN doctor ON appointment.docid = doctor.docid
        LEFT JOIN procedures ON appointment.procedure_id = procedures.procedure_id
        LEFT JOIN branches b ON appointment.branch_id = b.id
        WHERE appointment.pid = '$userid' AND appointment.status = 'booking'";

if ($_POST) {
    if (!empty($_POST["appodate"])) {
        $appodate = $_POST["appodate"];
        $sqlmain .= " AND appointment.appodate='$appodate'";
    }
}

if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $sqlmain .= " AND (doctor.docname LIKE '%$search%' OR procedures.procedure_name LIKE '%$search%')";
}

// Sort by upcoming date/time (soonest first)
$sqlmain .= " ORDER BY appointment.appodate ASC, appointment.appointment_time ASC LIMIT $start_from, $results_per_page";
$result = $database->query($sqlmain);

// Count query for pagination
$count_query = str_replace("LIMIT $start_from, $results_per_page", "", $sqlmain);
$count_query = "SELECT COUNT(*) as total FROM (" . $count_query . ") as count_table";
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

// Status message handling
$statusMessage = '';
$messageClass = '';

if (isset($_GET['status'])) {
    if ($_GET['status'] == 'cancel_success') {
        $statusMessage = "Booking canceled successfully.";
        $messageClass = "success-message";
    } elseif ($_GET['status'] == 'cancel_error') {
        $statusMessage = "Failed to cancel the booking. Please try again.";
        $messageClass = "error-message";
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
    <link rel="stylesheet" href="../css/overrides.css">
    <link rel="stylesheet" href="../css/responsive-admin.css">
    <title>My Booking - IHeartDentistDC</title>
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">
    <style>
        .filter-date-input {
            padding: 6px 12px;
            border: 1px solid #303030;
            border-radius: 18px;
            font-size: 14px;
        }

        .filter-btn {
            padding: 8px 16px;
            background-color: #84b6e4;
            color: white;
            border: none;
            border-radius: 18px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .filter-btn:hover {
            background-color: #98c0e4;
        }

        .filter-clear-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background-color: #f5f5f5;
            color: #777;
            border-radius: 50%;
            text-decoration: none;
            font-size: 16px;
            transition: all 0.3s;
        }

        .filter-clear-btn:hover {
            background-color: #e0e0e0;
            color: #333;
        }
        
        .success-message {
            color: green;
            font-weight: bold;
            margin: 10px 0;
            text-align: center;
        }
        
        .error-message {
            color: red;
            font-weight: bold;
            margin: 10px 0;
            text-align: center;
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
                    include_once __DIR__ . '/../inc/get_profile_pic.php';
                    $profile_pic = get_profile_pic($userfetch);
                    ?>
                    <img src="../<?php echo $profile_pic; ?>" alt="Profile" class="profile-img">
                </div>
                <h3 class="profile-name"><?php echo substr($username, 0, 25) ?></h3>
                <p style="color: #777; margin: 0; font-size: 14px; text-align: center;">
                    <?php echo substr($useremail, 0, 30) ?>
                </p>
            </div>

            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <img src="../Media/Icon/Blue/home.png" alt="Home" class="nav-icon">
                    <span class="nav-label">Dashboard</span>
                </a>
                <a href="profile.php" class="nav-item">
                    <img src="../Media/Icon/Blue/profile.png" alt="Profile" class="nav-icon">
                    <span class="nav-label">Profile</span>
                </a>
                <a href="dentist.php" class="nav-item">
                    <img src="../Media/Icon/Blue/dentist.png" alt="Dentist" class="nav-icon">
                    <span class="nav-label">Dentist</span>
                </a>
                <a href="calendar/calendar.php" class="nav-item">
                    <img src="../Media/Icon/Blue/calendar.png" alt="Calendar" class="nav-icon">
                    <span class="nav-label">Calendar</span>
                </a>
                <a href="my_booking.php" class="nav-item active">
                    <img src="../Media/Icon/Blue/booking.png" alt="Bookings" class="nav-icon">
                    <span class="nav-label">My Booking</span>
                </a>
                <a href="my_appointment.php" class="nav-item">
                    <img src="../Media/Icon/Blue/appointment.png" alt="Appointments" class="nav-icon">
                    <span class="nav-label">My Appointment</span>
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
                    <!-- Status Message -->
                    <?php if (!empty($statusMessage)): ?>
                        <div class="<?php echo $messageClass; ?>"><?php echo $statusMessage; ?></div>
                    <?php endif; ?>
                    
                    <!-- search bar -->
                    <div class="search-container">
                        <form action="" method="GET" style="display: flex; width: 100%;">
                            <input type="search" name="search" id="searchInput" class="search-input"
                                placeholder="Search by dentist or procedure name"
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <?php if (isset($_GET['search']) && $_GET['search'] != ""): ?>
                                <button type="button" class="clear-btn" onclick="clearSearch()">×</button>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- header section -->
                    <div class="announcements-header">
                        <h3 class="announcements-title">My Bookings (<?php echo $count_row['total']; ?>)</h3>
                        <div class="announcement-filters">
                            <form action="" method="POST" style="display: flex; align-items: center; gap: 8px;">
                                <input type="date" name="appodate" id="date" class="filter-date-input"
                                    value="<?php echo isset($_POST['appodate']) ? htmlspecialchars($_POST['appodate']) : ''; ?>">
                                <button type="submit" name="filter" class="filter-btn">
                                    Filter
                                </button>
                                <?php if (isset($_POST['appodate']) && $_POST['appodate'] != ""): ?>
                                    <a href="my_booking.php" class="filter-clear-btn">×</a>
                                <?php endif; ?>
                            </form>
                            
                            <!-- Branch filter dropdown removed -->
                        </div>
                    </div>

                    <?php if ($result && $result->num_rows > 0): ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Profile</th>
                                        <th>Dentist</th>
                                        <th>Branch</th>
                                            <th>Status</th>
                                        <th>Date & Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <!-- Profile Column -->
                                            <td>
                                                <?php
                                                if (!empty($row['photo'])) {
                                                    $photo = "../admin/uploads/" . $row['photo'];
                                                } else {
                                                    $photo = "../Media/Icon/Blue/dentist.png";
                                                }
                                                ?>
                                                <img src="<?php echo $photo; ?>" alt="<?php echo $row['docname']; ?>"
                                                    class="profile-img-small">
                                            </td>

                                            <!-- Dentist Name Column -->
                                            <td>
                                                <div class="cell-text"><?php echo $row['docname']; ?></div>
                                            </td>

                                            <!-- Branch Column (fixed position to match header) -->
                                            <td>
                                                <div class="cell-text"><?php echo isset($row['branch_name']) ? htmlspecialchars($row['branch_name']) : '-'; ?></div>
                                            </td>

                                            <!-- Status / Procedure Placeholder -->
                                            <td>
                                                <div class="cell-text">
                                                <?php
                                                  if ($row['status'] === 'pending_reservation') {
                                                      echo 'Pending Payment';
                                                  } else {
                                                      echo ($row['procedure_name']) ? 'Pending Evaluation' : 'Pending Evaluation';
                                                  }
                                                ?>
                                                </div>
                                            </td>

                                            <!-- Date & Time Column -->
                                            <td>
                                                <div class="cell-text">
                                                    <?php echo date('F j, Y', strtotime($row['appodate'])); ?></div>
                                                <div class="cell-text" style="color: #666; font-size: 0.9em;">
                                                    <?php echo date('g:i A', strtotime($row['appointment_time'])); ?>
                                                </div>
                                            </td>

                                            <!-- Actions Column -->
                                            <td>
                                                <div style="display:flex;gap:8px;align-items:center;justify-content:center;">
                                                    <a href="?action=drop&id=<?php echo $row['appoid']; ?>&doc=<?php echo urlencode($row['docname']); ?>" class="non-style-link">
                                                        <button class="action-btn remove-btn">Cancel</button>
                                                    </a>

                                                    <!-- View Receipt button opens a printable receipt in a new tab -->
                                                    <a href="receipt.php?appoid=<?php echo $row['appoid']; ?>" target="_blank" class="non-style-link">
                                                        <button class="action-btn view-receipt-btn">View Receipt</button>
                                                    </a>
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
                            $searchParam = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';

                            // Previous link
                            if ($page > 1) {
                                echo '<a href="?page=' . ($page - 1) . $searchParam . '">&laquo; Previous</a>';
                            }

                            // Page links
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<a href="?page=' . $i . $searchParam . '"' . ($i == $page ? ' class="active"' : '') . '>' . $i . '</a>';
                            }

                            // Next link
                            if ($page < $total_pages) {
                                echo '<a href="?page=' . ($page + 1) . $searchParam . '">Next &raquo;</a>';
                            }
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <p>No bookings found!</p>
                            <a class="non-style-link" href="calendar/calendar.php">
                                <button class="login-btn btn-primary-soft btn" style="margin-top: 20px;">Book a
                                    Dentist</button>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Add right sidebar section -->
                <div class="right-sidebar">
                    <div class="stats-section">
                        <div class="stats-container">
                            <!-- Notification Box -->
                            <div class="stat-box notification-container" id="notificationContainer">
                                <div class="stat-content">
                                    <h1 class="stat-number"><?php echo $unreadCount; ?></h1>
                                    <p class="stat-label">Notifications</p>
                                </div>
                                <div class="stat-icon">
                                    <img src="../Media/Icon/Blue/folder.png" alt="Notifications Icon">
                                    <?php if ($unreadCount > 0): ?>
                                        <span class="notification-badge"><?php echo $unreadCount; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="notification-dropdown" id="notificationDropdown">
                                    <div class="notification-header">
                                        <span>Notifications</span>
                                        <span class="mark-all-read" onclick="markAllAsRead()">Mark all as read</span>
                                    </div>
                                    
                                    <?php if ($notifications && $notifications->num_rows > 0): ?>
                                        <?php while ($notification = $notifications->fetch_assoc()): ?>
                                            <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" 
                                                 onclick="markAsRead(<?php echo $notification['id']; ?>, this)">
                                                <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                                <div><?php echo htmlspecialchars($notification['message']); ?></div>
                                                <div class="notification-time">
                                                    <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="no-notifications">No notifications</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Second row -->
                            <a href="my_booking.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $appointmentrow->num_rows ?></h1>
                                        <p class="stat-label">My Bookings</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/booking.png" alt="Booking Icon">
                                    </div>
                                </div>
                            </a>

                            <a href="my_appointment.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php
                                        $appointmentCount = $schedulerow->num_rows;
                                        echo $appointmentCount;
                                        ?></h1>
                                        <p class="stat-label">My Appointments</p>
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
                            $sql = "SELECT
                                        a.appoid,
                                        COALESCE(GROUP_CONCAT(DISTINCT p.procedure_name ORDER BY p.procedure_name SEPARATOR ', '), '') AS procedure_names,
                                        a.appodate,
                                        a.appointment_time,
                                        d.docname as doctor_name,
                                        b.name AS branch_name
                                    FROM appointment a
                                    LEFT JOIN appointment_procedures ap ON a.appoid = ap.appointment_id
                                    LEFT JOIN procedures p ON ap.procedure_id = p.procedure_id
                                    LEFT JOIN doctor d ON a.docid = d.docid
                                    LEFT JOIN branches b ON a.branch_id = b.id
                                    WHERE
                                        a.pid = '$userid'
                                        AND a.status = 'appointment'
                                        AND a.appodate >= '$today'
                                    GROUP BY a.appoid
                                    ORDER BY a.appodate ASC, a.appointment_time ASC
                                    LIMIT 3";

                            $upcomingAppointments = $database->query($sql);

                            if ($upcomingAppointments && $upcomingAppointments->num_rows > 0) {
                                while ($appointment = $upcomingAppointments->fetch_assoc()) {
                                    $proc = htmlspecialchars($appointment['procedure_names'] ?? 'No procedure assigned');
                                    $dname = htmlspecialchars($appointment['doctor_name'] ?? '');
                                    $date_str = '';
                                    $time_str = '';
                                    if (!empty($appointment['appodate'])) {
                                        $date_str = htmlspecialchars(date('F j, Y', strtotime($appointment['appodate'])));
                                    }
                                    if (!empty($appointment['appointment_time'])) {
                                        $time_str = htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time'])));
                                    }
                                    $branch = htmlspecialchars($appointment['branch_name'] ?? '-');

                                    echo '<div class="appointment-item">';
                                    echo '<h4 class="appointment-type">' . $proc . '</h4>';
                                    echo '<p class="appointment-dentist">With Dr. ' . $dname . '</p>';
                                    $datetime = $date_str . ($date_str && $time_str ? ' • ' : '') . $time_str;
                                    if ($branch && $branch !== '-') {
                                        $datetime .= ' - ' . $branch;
                                    }
                                    echo '<p class="appointment-date">' . $datetime . '</p>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="no-appointments">                                    <p>No upcoming appointments scheduled</p>                                    <a href="calendar/calendar.php" class="schedule-btn">Schedule an appointment</a>                                </div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    if (isset($_GET['action'])) {
        $id = $_GET["id"];
        $action = $_GET["action"];

        if ($action == 'drop') {
            $docname = $_GET["doc"];

            // Get appointment details for notification
            $appointmentQuery = $database->query("SELECT a.*, p.pname, p.pemail 
                                                FROM appointment a
                                                JOIN patient p ON a.pid = p.pid
                                                WHERE a.appoid = '$id'");
            $appointment = $appointmentQuery->fetch_assoc();

            echo '
            <div id="popup1" class="overlay">
                <div class="popup" style="max-width:520px;padding:25px 30px;">
                    <h2 style="text-align:center;margin-top:0;">Confirm Cancellation</h2>
                    <a class="close" href="my_booking.php" aria-label="Close">&times;</a>
                    <div class="content" style="width:100%;margin:0 0 15px 0;">
                        <p style="margin:0 0 12px 0;font-size:15px;">Are you sure you want to cancel this booking?</p>
                        <p style="color:#c0392b;font-size:13px;font-weight:600;margin:0;line-height:1.4;">
                            Note: Your reservation fee is <u>non‑refundable</u>. Cancelling will forfeit this fee and cannot be reversed.
                        </p>
                    </div>
                    <div style="display:flex;justify-content:center;gap:18px;flex-wrap:wrap;">
                        <a href="cancel_appointment.php?id=' . $id . '&source=patient" class="non-style-link">
                            <button class="btn-primary btn" style="display:flex;justify-content:center;align-items:center;margin:10px;padding:10px 22px;min-width:160px;">
                                <span class="tn-in-text">Yes, Cancel</span>
                            </button>
                        </a>
                        <a href="my_booking.php" class="non-style-link">
                            <button class="btn-primary btn" style="display:flex;justify-content:center;align-items:center;margin:10px;padding:10px 22px;min-width:160px;">
                                <span class="tn-in-text">No, Go Back</span>
                            </button>
                        </a>
                    </div>
                </div>
            </div>
            ';
        }
    }
    ?>

    <script>
        // Function to clear search and redirect
        function clearSearch() {
            window.location.href = 'my_booking.php';
        }

        // Function to clear date filter
        function clearDateFilter() {
            window.location.href = 'my_booking.php';
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
            
            // Notification dropdown toggle
            const notificationContainer = document.getElementById('notificationContainer');
            const notificationDropdown = document.getElementById('notificationDropdown');
            
            notificationContainer.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function() {
                notificationDropdown.classList.remove('show');
            });
        });

        // Update this function to prevent negative counts
function updateNotificationCount(newCount) {
    // Ensure count never goes below 0
    newCount = Math.max(0, newCount);
    
    // Update the stat number
    const statNumber = document.querySelector('#notificationContainer .stat-number');
    if (statNumber) {
        statNumber.textContent = newCount;
    }
    
    // Update or remove the badge
    const badge = document.querySelector('.notification-badge');
    if (newCount > 0) {
        if (badge) {
            badge.textContent = newCount;
        } else {
            // Create new badge if it doesn't exist
            const notificationIcon = document.querySelector('#notificationContainer .stat-icon');
            const newBadge = document.createElement('span');
            newBadge.className = 'notification-badge';
            newBadge.textContent = newCount;
            notificationIcon.appendChild(newBadge);
        }
    } else {
        if (badge) {
            badge.remove();
        }
    }
}

// Update markAsRead to use server-side count
function markAsRead(notificationId, element) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            element.classList.remove('unread');
            
            // Use the count returned from server instead of DOM counting
            updateNotificationCount(data.unread_count || 0);
        }
    });
}

// Update markAllAsRead to use server-side count
function markAllAsRead() {
    fetch('mark_all_notifications_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove unread class from all notifications
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            
            // Use the count returned from server
            updateNotificationCount(data.unread_count || 0);
        }
    });
}
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
