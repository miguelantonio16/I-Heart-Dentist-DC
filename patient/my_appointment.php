<?php
// Start session at the very beginning of the file
session_start();
date_default_timezone_set('Asia/Singapore');

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" or $_SESSION['usertype'] != 'p') {
        header("location: login.php");
    } else {
        $useremail = $_SESSION["user"];
    }
} else {
    header("location: login.php");
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
$appointmentrow = $database->query("select * from appointment where status='booking' AND pid='$userid';");
$schedulerow = $database->query("select * from appointment where status='appointment' AND pid='$userid';");

// Branch filter removed per request (dropdown UI removed)

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
// REPLACE YOUR $sqlmain WITH THIS:
$sqlmain = "SELECT DISTINCT
            appointment.appoid, 
            appointment.procedure_id,
            doctor.docname, 
            appointment.appodate, 
            appointment.appointment_time,
            appointment.status,
            appointment.payment_status,
            appointment.reservation_paid,
            appointment.payment_method,
            appointment.total_amount, 
            doctor.photo,
            b.name AS branch_name
        FROM appointment
        INNER JOIN doctor ON appointment.docid = doctor.docid
        LEFT JOIN branches b ON appointment.branch_id = b.id
        LEFT JOIN procedures pmain ON appointment.procedure_id = pmain.procedure_id
        LEFT JOIN appointment_procedures ap ON appointment.appoid = ap.appointment_id
        LEFT JOIN procedures pr2 ON ap.procedure_id = pr2.procedure_id
        WHERE appointment.pid = '$userid' 
        AND appointment.status IN ('appointment', 'completed')";
if ($_POST) {
    if (!empty($_POST["appodate"])) {
        $appodate = $_POST["appodate"];
        $sqlmain .= " AND appointment.appodate='$appodate'";
    }
}

if (isset($_GET['search'])) {
    $search = $database->real_escape_string($_GET['search']);
    $sqlmain .= " AND (
        doctor.docname LIKE '%$search%'
        OR pmain.procedure_name LIKE '%$search%'
        OR pr2.procedure_name LIKE '%$search%'
    )";
}

$sqlmain .= " ORDER BY appointment.appodate DESC, appointment.appointment_time DESC LIMIT $start_from, $results_per_page";
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
        $statusMessage = "Appointment canceled successfully.";
        $messageClass = "success-message";
    } elseif ($_GET['status'] == 'cancel_error') {
        $statusMessage = "Failed to cancel the appointment. Please try again.";
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
    <title>My Appointment - IHeartDentistDC</title>
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
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
            display: inline-block;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-upcoming {
            background-color: #cce5ff;
            color: #004085;
        }
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
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
        .remove-btn {
            width: 122px;
        }

        /* Mobile fixes: prevent overlapping date/time and badges */
        @media (max-width: 992px) {
            .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .table th, .table td { white-space: normal; }
            /* Ensure Date & Time column has enough room and wraps */
            .table th:nth-child(5), .table td:nth-child(5) { min-width: 140px; }
            .table td:nth-child(5) .cell-text { display: block; }
            /* Keep status badge from crowding other columns */
            .table td:nth-child(6) .status-badge { display: inline-block; margin-top: 4px; }
            /* Avoid cramped action buttons */
            .table td:nth-child(7) { min-width: 160px; }
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
                <a href="my_booking.php" class="nav-item">
                    <img src="../Media/Icon/Blue/booking.png" alt="Bookings" class="nav-icon">
                    <span class="nav-label">My Booking</span>
                </a>
                <a href="my_appointment.php" class="nav-item active">
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
                        <h3 class="announcements-title">My Appointments (<?php echo $count_row['total']; ?>)</h3>
                        <div class="announcement-filters">
                            <form action="" method="POST" style="display: flex; align-items: center; gap: 8px;">
                                <input type="date" name="appodate" id="date" class="filter-date-input"
                                    value="<?php echo isset($_POST['appodate']) ? htmlspecialchars($_POST['appodate']) : ''; ?>">
                                <button type="submit" name="filter" class="filter-btn">
                                    Filter
                                </button>
                                <?php if (isset($_POST['appodate']) && $_POST['appodate'] != ""): ?>
                                    <a href="my_appointment.php" class="filter-clear-btn">×</a>
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
        <th>Procedure</th>
        <th>Date & Time</th>
        <th>Payment</th> <th>Actions</th>
    </tr>
</thead>
                                <tbody>
                                    <?php 
                                    while ($row = $result->fetch_assoc()): 
                                        $appodate = $row["appodate"];
                                        $status = $row["status"];
                                        
                                        // Determine status badge
                                        if ($status == 'completed') {
                                            // Show unpaid state distinctly if bill not yet settled
                                            if (isset($row['payment_status']) && strtolower($row['payment_status']) === 'paid') {
                                                $statusClass = 'status-completed';
                                                $statusText = 'Completed';
                                            } else {
                                                $statusClass = 'status-upcoming';
                                                $statusText = 'Awaiting Payment';
                                            }
                                        } elseif ($appodate < $today) {
                                            $statusClass = 'status-completed';
                                            $statusText = 'Completed';
                                        } else {
                                            $statusClass = 'status-upcoming';
                                            $statusText = 'Upcoming';
                                        }
                                    ?>
                                        <tr>
                                            <!-- Profile Column -->
                                            <td>
                                                <?php
                                                $photo = "../Media/Icon/Blue/dentist.png";
                                                if (!empty($row['photo'])) {
                                                    $profilePicRaw = trim($row['photo']);
                                                    $base = strtolower(basename($profilePicRaw));
                                                    $disallow = ['default.jpg','default.png','default.jpeg','logo.png','logo.jpg','logo.jpeg','sdmc logo.png','sdmc_logo.png'];
                                                    $candidateFs = realpath(__DIR__ . '/../admin/uploads/' . $profilePicRaw);
                                                    if ($profilePicRaw !== '' && !in_array($base, $disallow, true) && $candidateFs && file_exists($candidateFs)) {
                                                        $photo = "../admin/uploads/" . $profilePicRaw;
                                                    }
                                                }
                                                ?>
                                                <img src="<?php echo $photo; ?>" alt="<?php echo $row['docname']; ?>"
                                                    class="profile-img-small">
                                            </td>

                                            <!-- Dentist Name Column -->
                                            <td>
                                                <div class="cell-text"><?php echo $row['docname']; ?></div>
                                            </td>

                                            <!-- Branch Column -->
                                            <td>
                                                <div class="cell-text"><?php echo isset($row['branch_name']) ? htmlspecialchars($row['branch_name']) : '-'; ?></div>
                                            </td>

                                            <!-- Procedure Column -->
                                            <td>
                                                <div class="cell-text">
                                                    <?php
                                                    $appIdLocal = (int)$row['appoid'];
                                                    $procNames = [];
                                                    $procRes = $database->query("SELECT p.procedure_name FROM appointment_procedures ap INNER JOIN procedures p ON ap.procedure_id=p.procedure_id WHERE ap.appointment_id=$appIdLocal ORDER BY p.procedure_name ASC");
                                                    if ($procRes && $procRes->num_rows > 0) {
                                                        while ($pr = $procRes->fetch_assoc()) {
                                                            // Exclude discount rows from the procedure column
                                                            if (!in_array($pr['procedure_name'], ['PWD Discount', 'Senior Citizen Discount'])) {
                                                                $procNames[] = $pr['procedure_name'];
                                                            }
                                                        }
                                                    }

                                                    if ($row['status'] == 'completed' && !empty($procNames)) {
                                                        // Completed appointments: always show the procedures that were done.
                                                        echo htmlspecialchars(implode(', ', $procNames));
                                                    } else {
                                                        // Not yet completed: if any procedures are already attached, show assessment.
                                                        if (!empty($procNames) || ($row['procedure_id'] && $row['procedure_id'] != 0)) {
                                                            echo 'For Clinic Assessment';
                                                        } else {
                                                            echo 'Pending Evaluation';
                                                        }
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
                                            
                                            <!-- Status Column -->
                                            <td>
                                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                            </td>

<td>
    <?php
    // Hide agreed total and balance from patient until appointment is completed.
    $reservationFeePaid = 250; // fixed
    $total = ($row['total_amount'] > 0) ? $row['total_amount'] : 0;
    $balance = max($total - $reservationFeePaid, 0);

    // Determine if this appointment originated from a patient reservation
    $reservationPaidFlag = isset($row['reservation_paid']) ? (int)$row['reservation_paid'] : 0;
    $statusVal = isset($row['status']) ? strtolower($row['status']) : '';
    $payStatusVal = isset($row['payment_status']) ? strtolower($row['payment_status']) : '';
    $hasReservation = ($reservationPaidFlag === 1)
        || ($payStatusVal === 'partial')
        || ($statusVal === 'pending_reservation')
        || ($statusVal === 'booking');

    $isCancelable = ($row['status'] == 'appointment' && $row['appodate'] >= $today);

        if ($row['status'] == 'completed') {
        // Appointment finished: show minimal status with summary button.
        if ($row['payment_status'] == 'paid') {
            echo '<div style="font-size:13px; margin-bottom:5px;">';
            echo '<div style="margin-bottom:6px;">'
                . '<button type="button" class="btn-primary-soft" style="padding:5px 10px;font-size:12px;" onclick="openProcedureModal('.(int)$row['appoid'].')">View Summary</button>'
                . '</div>';
            // View Receipt for paid appointments (invoice). Always available when paid.
            echo '<div><a href="receipt.php?appoid='.(int)$row['appoid'].'" target="_blank" class="btn-primary-soft" style="padding:5px 10px;font-size:12px;text-decoration:none; display:inline-block;">View Receipt</a></div>';
            echo '</div>';
        } elseif ($row['payment_status'] == 'pending_cash' || ($row['payment_method']=='cash' && $row['payment_status']!='paid')) {
            // Show pending cash state even if payment_status failed to update but method flagged.
            echo '<div style="font-size:13px; margin-bottom:5px;">';
            echo 'Total: ₱' . number_format($total,2) . '<br>';
            if ($hasReservation) {
                echo 'Reservation Fee Paid: ₱' . number_format($reservationFeePaid,2) . '<br>';
            }
            $balanceDisplay = $hasReservation ? $balance : $total; // for admin-booked, full amount due
            echo '<strong style="color:#d32f2f;">Balance: ₱' . number_format($balanceDisplay,2) . '</strong><br>';
            echo '<span style="color:orange; font-weight:bold;">Cash Payment Pending Verification</span>';
            echo '</div>';
        } else {
            echo '<div style="font-size:13px; margin-bottom:5px;">';
            echo 'Total: ₱' . number_format($total,2) . '<br>';
            if ($hasReservation) {
                echo 'Reservation Fee Paid: ₱' . number_format($reservationFeePaid,2) . '<br>';
            }
            $balanceDisplay = $hasReservation ? $balance : $total;
            echo '<strong style="color:#d32f2f;">Balance: ₱' . number_format($balanceDisplay,2) . '</strong>';
            echo '</div>';
            if ($balance > 0) {
                // Pay Online
                $amountDue = $hasReservation ? $balance : $total;
                echo '<a href="pay_balance.php?id='.$row['appoid'].'&amount='.$amountDue.'" class="btn-primary-soft" style="padding:5px 10px; font-size:12px; text-decoration:none; margin-right:5px; display:inline-block; margin-bottom:5px;">Pay Online</a>';
                // Pay Cash
                echo '<form action="process_cash.php" method="POST" style="display:inline;" onsubmit="return confirm(\'Pay ₱'.number_format($balance,2).' via Cash at the clinic?\');">'
                    . '<input type="hidden" name="appoid" value="'.$row['appoid'].'">'
                    . '<button type="submit" class="btn-secondary" style="padding:5px 10px; font-size:12px; border:none; cursor:pointer; border-radius:4px;">Pay Cash</button>'
                    . '</form>';
                // Offer receipt only if reservation exists or appointment is fully paid
                if ($row['payment_status'] === 'paid' || $hasReservation) {
                    echo ' <a href="receipt.php?appoid='.(int)$row['appoid'].'" target="_blank" class="btn-primary-soft" style="padding:5px 10px;font-size:12px;margin-left:6px;text-decoration:none; display:inline-block; margin-bottom:5px;">View Receipt</a>';
                }
            }
        }
    } else {
                // Upcoming / confirmed appointment
                if ($hasReservation) {
                    echo '<div style="font-size:13px; margin-bottom:5px;">Reservation Fee Paid: ₱' . number_format($reservationFeePaid,2) . '</div>';
                    echo '<div style="margin-top:6px;"><a href="receipt.php?appoid='.(int)$row['appoid'].'" target="_blank" class="non-style-link" style="display:inline-block;background:#2f3670;color:#fff;padding:6px 10px;font-size:12px;border-radius:6px;text-decoration:none;margin-bottom:6px;">View Receipt</a></div>';
                }
                if ($isCancelable) {
                        echo '<div style="margin-top:6px;">'
                            . '<button type="button" class="cancel-btn" style="background:#e85d5d;color:#fff;padding:6px 10px;border:none;border-radius:6px;font-size:12px;cursor:pointer;" onclick="if(confirm(\'Are you sure you want to cancel this appointment?\')){ window.location.href=\'cancel_appointment.php?id=' . $row['appoid'] . '&source=patient\'; }">Cancel</button>'
                            . '</div>';
                }
    }
    ?>
</td>

                                            <!-- Actions Column -->
                                            <td>
                                                <!-- Actions for this appointment are handled in the Payment column -->
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
                            <p>No appointments found!</p>
                            <a class="non-style-link" href="calendar/calendar.php">
                                <button class="login-btn btn-primary-soft btn" style="margin-top: 20px;">Book a
                                    Dentist</button>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- View Procedures Modal -->
                <div id="procedureModal" class="overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);justify-content:center;align-items:center;z-index:9999;">
                    <div class="popup" style="background:#fff;padding:25px 30px;border-radius:10px;max-width:520px;width:90%;max-height:80vh;overflow-y:auto;position:relative;">
                        <h2 style="margin-top:0;text-align:center;">Procedures Summary</h2>
                        <a href="#" onclick="closeProcedureModal();return false;" style="position:absolute;right:15px;top:10px;font-size:22px;text-decoration:none;color:#333;">&times;</a>
                        <div id="procedureModalBody" style="margin-top:10px;font-size:14px;"></div>
                        <div style="text-align:center;margin-top:18px;">
                            <button type="button" class="btn-primary-soft" onclick="closeProcedureModal()">Close</button>
                        </div>
                    </div>
                </div>

                <!-- right sidebar section -->
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
                                
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Second row -->
                            <a href="my_booking.php" class="stat-box-link">
                                <div class="stat-box">

        <script>
        function openProcedureModal(appoid){
            const modal = document.getElementById('procedureModal');
            const body = document.getElementById('procedureModalBody');
            body.innerHTML = '<em>Loading procedures...</em>';
            modal.style.display='flex';
            document.body.style.overflow='hidden';
            fetch('view_procedures_ajax.php?appoid='+encodeURIComponent(appoid))
                .then(r=>r.json())
                .then(data=>{
                    if(!data.success){
                        body.innerHTML = '<span style="color:#c00;">'+(data.error||'Unable to load procedures.')+'</span>';
                        return;
                    }
                    const procedures = data.procedures || [];
                    const discounts = data.discounts || [];
                    const procTotal = typeof data.procedures_total !== 'undefined' ? Number(data.procedures_total) : procedures.reduce((s,p)=>s+Number(p.agreed_price),0);
                    const discTotal = typeof data.discounts_total !== 'undefined' ? Number(data.discounts_total) : discounts.reduce((s,d)=>s+Math.abs(Number(d.agreed_price)),0);
                    const hasReservation = !!data.has_reservation;
                    const reservation = typeof data.reservation_fee !== 'undefined' ? Number(data.reservation_fee) : (hasReservation ? 250 : 0);
                    const net = typeof data.net_after_reservation !== 'undefined' ? Number(data.net_after_reservation) : (hasReservation ? Math.max(procTotal - reservation - discTotal,0) : (procTotal - discTotal));

                    if(procedures.length===0 && discounts.length===0){
                        body.innerHTML = '<em>No procedures have been recorded for this appointment.</em>';
                        return;
                    }

                    let html = '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
                    html += '<tr style="background:#f5f5f5;"><th style="text-align:left;padding:6px;border:1px solid #ddd;">Procedure</th><th style="text-align:right;padding:6px;border:1px solid #ddd;">Price</th></tr>';
                    procedures.forEach(p=>{
                        html += '<tr><td style="padding:6px;border:1px solid #ddd;">'+p.procedure_name+'</td><td style="padding:6px;border:1px solid #ddd;text-align:right;">₱'+Number(p.agreed_price).toFixed(2)+'</td></tr>';
                    });

                    // Show totals: procedures subtotal, reservation, discounts (below reservation), then amount paid
                    html += '<tr><td style="padding:6px;border:1px solid #ddd;font-weight:600;text-align:right;" colspan="2">Total Procedures: ₱'+procTotal.toFixed(2)+'</td></tr>';
                    if (hasReservation && reservation>0) {
                        html += '<tr><td style="padding:6px;border:1px solid #ddd;text-align:right;" colspan="2">Less Reservation Fee Paid: ₱'+reservation.toFixed(2)+'</td></tr>';
                    }

                    if (discTotal > 0) {
                        // show first discount name if available
                        const dlabel = (discounts.length>0 && discounts[0].procedure_name) ? discounts[0].procedure_name : 'Discount';
                        html += '<tr><td style="padding:6px;border:1px solid #ddd;text-align:right;" colspan="2">'+dlabel+': - ₱'+Number(discTotal).toFixed(2)+'</td></tr>';
                    }

                    html += '<tr><td style="padding:6px;border:1px solid #ddd;font-weight:600;text-align:right;" colspan="2">Amount Paid at Clinic: ₱'+net.toFixed(2)+'</td></tr>';
                    html += '</table>';
                    body.innerHTML = html;
                })
                .catch(()=>{
                    body.innerHTML = '<span style="color:#c00;">Network error loading procedures.</span>';
                });
        }
        function closeProcedureModal(){
            document.getElementById('procedureModal').style.display='none';
            document.body.style.overflow='';
        }
        </script>
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

    <script>
        // Function to clear search and redirect
        function clearSearch() {
            window.location.href = 'my_appointment.php';
        }

        // Function to clear date filter
        function clearDateFilter() {
            window.location.href = 'my_appointment.php';
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
                    // Update badge count
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        const currentCount = parseInt(badge.textContent);
                        if (currentCount > 1) {
                            badge.textContent = currentCount - 1;
                        } else {
                            badge.remove();
                        }
                    }
                    // Update the stat number
                    const statNumber = document.querySelector('#notificationContainer .stat-number');
                    if (statNumber) {
                        statNumber.textContent = currentCount - 1;
                    }
                }
            });
        }
        
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
                    // Remove badge
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        badge.remove();
                    }
                    // Update the stat number
                    const statNumber = document.querySelector('#notificationContainer .stat-number');
                    if (statNumber) {
                        statNumber.textContent = '0';
                    }
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
