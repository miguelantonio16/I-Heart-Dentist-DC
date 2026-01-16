<?php ob_start(); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../../Media/white-icon/white-IHeartDentistDC_Logo.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.20.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.js"></script>
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <script>
        // Initialize Select2 on page load for branch and dentist selects
        $(document).ready(function(){
            try {
                $('#choose_branch').select2({
                    placeholder: 'All Branches',
                    allowClear: true,
                    width: '100%'
                });
            } catch(e) {}

            try {
                $('#choose_dentist').select2({
                    placeholder: 'Select a Dentist',
                    allowClear: true,
                    width: '100%'
                });
            } catch(e) {}
        });
    </script>
    <link rel="stylesheet" href="../../css/calendar.css">
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/animations.css">
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/dashboard.css">
    <link rel="stylesheet" href="../../css/overrides.css">
    <link rel="stylesheet" href="../../css/responsive-admin.css">
    <!-- Select2 for improved selects -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <title>Calendar - IHeartDentistDC</title>
    <link rel="icon" href="../../Media/Icon/logo.png" type="image/png">
    <style>
        .select-dentist-message {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 300px;
            background-color: #f9f9f9;
            border: 1px dashed #ccc;
            border-radius: 8px;
            margin: 20px 0;
        }

        .select-dentist-message p {
            font-size: 18px;
            color: #666;
            text-align: center;
            padding: 20px;
        }

        #calendar{
            height: auto !important; /* allow FullCalendar to size rows */
        }

        /* Confirmation modal tweaks */
        #confirmReservationModal .modal-dialog {
            max-width: 360px;
            margin: 1.75rem auto;
        }
        #confirmReservationModal .modal-content {
            border-radius: 8px;
            padding: 0;
            overflow: hidden;
        }
        #confirmReservationModal .modal-header {
            border-bottom: none;
            padding: 18px 20px 6px 20px;
        }
        #confirmReservationModal .modal-title {
            font-weight: 700;
            font-size: 18px;
            line-height: 1.1;
        }
        #confirmReservationModal .modal-body {
            padding: 8px 20px 6px 20px;
            color: #222;
        }
        #confirmReservationModal .confirm-row { margin: 12px 0; }
        #confirmReservationModal .confirm-label { display:block; font-weight:700; margin-bottom:6px; }
        #confirmReservationModal .confirm-value { font-size:16px; font-weight:600; color:#1b2437; }
        #confirmReservationModal .confirm-note { color:#7a8593; font-size:13px; margin-top:6px; }
        #confirmReservationModal .modal-footer { border-top:none; padding:12px 16px 18px 16px; }
        #confirmReservationModal .btn { border-radius:6px; padding:8px 14px; }
        #confirmReservationModal .btn-secondary { background:#6c757d; border-color:#6c757d; color:#fff; }
        #confirmReservationModal .btn-primary { background:#2f3670; border-color:#2f3670; }

    </style>
</head>

<body class="patient-calendar">
    <button class="hamburger-admin show-mobile" id="sidebarToggle" aria-label="Toggle navigation" aria-controls="adminSidebar" aria-expanded="false">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?php
    date_default_timezone_set('Asia/Singapore');
    session_start();

    if (isset($_SESSION["user"])) {
        if (($_SESSION["user"]) == "" or $_SESSION['usertype'] != 'p') {
            header("location: ../login.php");
        } else {
            $useremail = $_SESSION["user"];
        }
    } else {
        header("location: ../login.php");
    }

    include("../../connection.php");
    $userrow = $database->query("select * from patient where pemail='$useremail'");
    $userfetch = $userrow->fetch_assoc();
    $userid = $userfetch["pid"];
    $username = $userfetch["pname"];

    // Get notification count
    $unreadCount = $database->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = '$userid' AND user_type = 'p' AND is_read = 0");
    $unreadCount = $unreadCount->fetch_assoc()['count'];

    // Get notifications
    $notifications = $database->query("SELECT * FROM notifications WHERE user_id = '$userid' AND user_type = 'p' ORDER BY created_at DESC");

    // Handle payment cancellation cleanup: remove pending reservation that wasn't paid
    if (isset($_GET['error']) && $_GET['error'] === 'cancelled' && isset($_GET['appoid'])) {
        $appoid = intval($_GET['appoid']);
        // Only delete if still pending_reservation & belongs to this patient
        $database->query("DELETE FROM appointment WHERE appoid='$appoid' AND pid='$userid' AND status='pending_reservation'");
    }

    $procedures = $database->query("SELECT * FROM procedures");
    $procedure_options = '';
    while ($procedure = $procedures->fetch_assoc()) {
           $price_attr = isset($procedure['price']) ? $procedure['price'] : 0;
           $desc_attr = isset($procedure['description']) ? htmlspecialchars($procedure['description']) : '';
           $procedure_options .= '<option value="' . $procedure['procedure_id'] . '" data-price="' . $price_attr . '" data-description="' . $desc_attr . '">' . htmlspecialchars($procedure['procedure_name']) . '</option>';
    }
        // Reservation fee (must match server-side amount in save_event.php)
        $reservation_fee = 250;

    $doctorrow = $database->query("select * from doctor where status='active';");
    $appointmentrow = $database->query("select * from appointment where status='booking' AND pid='$userid';");
    $schedulerow = $database->query("select * from appointment where status='appointment' AND pid='$userid';");

    $results_per_page = 10;
    if (isset($_GET['page'])) {
        $page = $_GET['page'];
    } else {
        $page = 1;
    }
    $start_from = ($page - 1) * $results_per_page;
    // Ensure branch selection is available before building queries
    $selected_branch = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : (isset($userfetch['branch_id']) ? intval($userfetch['branch_id']) : 0);
    $search = "";
    $sort_param = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    $sort_order = ($sort_param === 'oldest') ? 'DESC' : 'ASC';

    if (isset($_GET['search'])) {
        $search = $_GET['search'];
        $query = "SELECT * FROM doctor WHERE status='active'" .
                 ($selected_branch > 0 ? " AND (branch_id = $selected_branch OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id = $selected_branch))" : "") .
                 " AND (docname LIKE '%$search%' OR docemail LIKE '%$search%' OR doctel LIKE '%$search%') ORDER BY docname $sort_order LIMIT $start_from, $results_per_page";
        $count_query = "SELECT COUNT(DISTINCT docid) as total FROM doctor WHERE status='active'" .
                       ($selected_branch > 0 ? " AND (branch_id = $selected_branch OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id = $selected_branch))" : "") .
                       " AND (docname LIKE '%$search%' OR docemail LIKE '%$search%' OR doctel LIKE '%$search%')";
    } else {
        $query = "SELECT * FROM doctor WHERE status='active'" .
                 ($selected_branch > 0 ? " AND (branch_id = $selected_branch OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id = $selected_branch))" : "") .
                 " ORDER BY docname $sort_order LIMIT $start_from, $results_per_page";
        $count_query = "SELECT COUNT(DISTINCT docid) as total FROM doctor WHERE status='active'" .
                       ($selected_branch > 0 ? " AND (branch_id = $selected_branch OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id = $selected_branch))" : "");
    }

    $result = $database->query($query);
    $count_result = $database->query($count_query);
    $count_row = $count_result->fetch_assoc();
    $total_pages = ceil($count_row['total'] / $results_per_page);

    $today = date('Y-m-d');
    $currentMonth = date('F');
    $currentYear = date('Y');
    $daysInMonth = date('t');
    $firstDayOfMonth = date('N', strtotime("$currentYear-" . date('m') . "-01"));
    $currentDay = date('j');

    // Branch filter (optional). Default to the logged-in patient's branch if available
    // (already initialized above to avoid undefined variable warnings)
    $branches_result = $database->query("SELECT id, name FROM branches ORDER BY name ASC");
    $branch_options = '<option value="">All Branches</option>';
    while ($b = $branches_result->fetch_assoc()) {
        $sel = ($selected_branch === (int)$b['id']) ? ' selected' : '';
        $branch_options .= '<option value="' . $b['id'] . '"' . $sel . '>' . htmlspecialchars($b['name']) . '</option>';
    }

    // Load doctors for dropdown, filtered by branch if selected
    if ($selected_branch > 0) {
        $doctors = $database->query("SELECT DISTINCT docid, docname FROM doctor WHERE status = 'active' AND (branch_id = $selected_branch OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id = $selected_branch)) ORDER BY docname ASC");
    } else {
        $doctors = $database->query("SELECT docid, docname FROM doctor WHERE status = 'active' ORDER BY docname ASC");
    }
    $doctor_options = '';
    while ($doctor = $doctors->fetch_assoc()) {
        $doctor_options .= '<option value="' . $doctor['docid'] . '">' . htmlspecialchars($doctor['docname']) . '</option>';
    }
    ?>
    <div class="nav-container">
        <div class="sidebar" id="adminSidebar">
            <div class="sidebar-logo">
                <img src="../../Media/Icon/logo.png" alt="IHeartDentistDC Logo">
            </div>

            <div class="user-profile">
                <div class="profile-image">
                    <?php
                    include_once __DIR__ . '/../../inc/get_profile_pic.php';
                    $profile_pic = get_profile_pic($userfetch);
                    ?>
                    <img src="../../<?php echo $profile_pic; ?>" alt="Profile" class="profile-img">
                </div>
                <h3 class="profile-name"><?php echo substr($username, 0, 25) ?></h3>
                <p style="color: #777; margin: 0; font-size: 14px; text-align: center;">
                    <?php echo substr($useremail, 0, 30) ?>
                </p>
            </div>

            <div class="nav-menu">
                <a href="../dashboard.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/home.png" alt="Home" class="nav-icon">
                    <span class="nav-label">Dashboard</span>
                </a>
                <a href="../profile.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/profile.png" alt="Profile" class="nav-icon">
                    <span class="nav-label">Profile</span>
                </a>
                <a href="../dentist.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/dentist.png" alt="Dentist" class="nav-icon">
                    <span class="nav-label">Dentist</span>
                </a>
                <a href="calendar.php" class="nav-item active">
                    <img src="../../Media/Icon/Blue/calendar.png" alt="Calendar" class="nav-icon">
                    <span class="nav-label">Calendar</span>
                </a>
                <a href="../my_booking.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/booking.png" alt="Bookings" class="nav-icon">
                    <span class="nav-label">My Booking</span>
                </a>
                <a href="../my_appointment.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/appointment.png" alt="Appointments" class="nav-icon">
                    <span class="nav-label">My Appointment</span>
                </a>
                <a href="../settings.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/settings.png" alt="Settings" class="nav-icon">
                    <span class="nav-label">Settings</span>
                </a>
            </div>

            <div class="log-out">
                <a href="../logout.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/logout.png" alt="Log Out" class="nav-icon">
                    <span class="nav-label">Log Out</span>
                </a>
            </div>
        </div>
        <div class="content-area">
            <div class="content">
                <div class="main-section">
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="form-group" style="display:flex;gap:12px;align-items:center;">
                                    <div style="flex: 1;">
                                        <label for="choose_branch">Branch</label>
                                        <div class="select-wrapper">
                                            <select class="form-control select2-branch" id="choose_branch">
                                                <?php echo $branch_options; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="flex: 2;">
                                        <label for="choose_dentist">Create an Appointment:</label>
                                        <div class="select-wrapper">
                                            <select class="form-control select2-dentist" id="choose_dentist">
                                                <option value="">Select a Dentist</option>
                                                <?php echo $doctor_options; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div id="calendar"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal fade" id="event_entry_modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-md" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalLabel">Create Appointment</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">x</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form action="save_event.php" method="POST">
                                        <div class="form-group">
                                            <!-- Hidden event_name field: will be auto-populated from selected procedure -->
                                            <input type="hidden" name="event_name" id="event_name" value="My Appointment">
                                        </div>
                                        <div class="form-row" style="display:flex;gap:12px;">
                                            <div class="form-group" style="flex:1;">
                                                <label>Reservation Fee</label>
                                                <div class="form-control" id="reservationFee">-</div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="patient_name">Patient Name</label>
                                            <input type="text" name="patient_name" id="patient_name" class="form-control" value="<?php echo $username; ?>" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label for="appointment_date">Date</label>
                                            <input type="text" name="appointment_date" id="appointment_date" class="form-control" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label for="appointment_time">Time</label>
                                                <select class="form-control" id="appointment_time" name="appointment_time" required>
                                                <option value="09:00:00"> 9:00 AM - 9:30 AM</option>
                                                <option value="09:30:00"> 9:30 AM - 10:00 AM</option>
                                                <option value="10:00:00"> 10:00 AM - 10:30 AM</option>
                                                <option value="10:30:00"> 10:30 AM - 11:00 AM</option>
                                                <option value="11:00:00"> 11:00 AM - 11:30 AM</option>
                                                <option value="11:30:00"> 11:30 AM - 12:00 PM</option>
                                                <option value="13:00:00"> 1:00 PM - 1:30 PM</option>
                                                <option value="13:30:00"> 1:30 PM - 2:00 PM</option>
                                                <option value="14:00:00"> 2:00 PM - 2:30 PM</option>
                                                <option value="14:30:00"> 2:30 PM - 3:00 PM</option>
                                                <option value="16:00:00"> 4:00 PM - 4:30 PM</option>
                                                <option value="16:30:00"> 4:30 PM - 5:00 PM</option>
                                            </select>
                                        </div>
                                        <input type="hidden" name="docid" id="docid" value="">
                                        <div class="modal-footer">
    <button type="button" class="btn btn-primary" id="openConfirmBtn">Confirm</button>
</div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                    <!-- Confirmation Modal (before redirecting to payment) -->
                    <div class="modal fade" id="confirmReservationModal" tabindex="-1" role="dialog" aria-labelledby="confirmReservationLabel" aria-hidden="true">
                        <div class="modal-dialog modal-sm" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="confirmReservationLabel">Confirm Appointment</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="confirm-row">
                                        <span class="confirm-label">Reservation Fee (pay now):</span>
                                        <span class="confirm-value" id="confirmReservationAmount">-</span>
                                    </div>
                                    <div class="confirm-row confirm-note">Procedure will be determined during clinic visit.</div>
                                    <div class="confirm-row confirm-note">Note: The reservation fee will be deducted from your total bill.</div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="proceedPaymentBtn">Proceed to Payment</button>
                                </div>
                            </div>
                        </div>
                    </div>
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
                                    <img src="../../Media/Icon/Blue/folder.png" alt="Notifications Icon">
                                    <?php if ($unreadCount > 0): ?>
                                        <span class="notification-badge"><?php echo $unreadCount; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="notification-dropdown" id="notificationDropdown">
                                    <div class="notification-header">
                                        <span>Notifications</span>
                                        <span class="mark-all-read" onclick="markAllAsRead()">Mark all as read</span>
                                    </div>
                                    
                                    <?php if ($notifications->num_rows > 0): ?>
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
                            <a href="../my_booking.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $appointmentrow->num_rows ?></h1>
                                        <p class="stat-label">My Bookings</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../../Media/Icon/Blue/booking.png" alt="Booking Icon">
                                    </div>
                                </div>
                            </a>

                            <a href="../my_appointment.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php
                                        $appointmentCount = $schedulerow->num_rows;
                                        echo $appointmentCount;
                                        ?></h1>
                                        <p class="stat-label">My Appointments</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../../Media/Icon/Blue/appointment.png" alt="Appointment Icon">
                                        <?php if ($appointmentCount > 0): ?>
                                            <span class="notification-badge"><?php echo $appointmentCount; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div class="calendar-section">
                        <!-- Color Guide -->
                        <div class="color-guide-container">
                            <div class="calendar-header">
                                <h3 class="color-guide-title">Color guide</h3>
                            </div>
                            <div class="color-legend">
                                <div class="color-item">
                                    <div class="color-circle" style="background-color: #F7BD01;"></div>
                                    <div class="color-label">Booking</div>
                                </div>
                                <div class="color-item">
                                    <div class="color-circle" style="background-color: #0e8923;"></div>
                                    <div class="color-label">Appointment</div>
                                </div>
                                <div class="color-item">
                                    <div class="color-circle" style="background-color: #F94144;"></div>
                                    <div class="color-label">No Service</div>
                                </div>
                                <div class="color-item">
                                    <div class="color-circle" style="background-color: #F9A15D;"></div>
                                    <div class="color-label">Timeslot Taken</div>
                                </div>
                                <div class="color-item">
                                    <div class="color-circle" style="background-color: #BBBBBB;"></div>
                                    <div class="color-label">Completed</div>
                                </div>
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
                                    LEFT JOIN branches b ON d.branch_id = b.id
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
                                echo '<div class="no-appointments">                                    <p>No upcoming appointments scheduled</p>                                    <a href="calendar.php" class="schedule-btn">Schedule an appointment</a>                                </div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                $(document).ready(function () {
                    // Don't initialize calendar by default
                    // Instead, show a message prompting to select a dentist
                    $('#calendar').html('<div class="select-dentist-message"><p>Please select a dentist to view available appointment slots.</p></div>');
                    
                    // Event listener for selecting a dentist
                    $('#choose_branch').change(function () {
                        // reload the page to apply branch filter and refresh dentist list
                        var branchId = $(this).val();
                        var url = new URL(window.location.href);
                        if (branchId) url.searchParams.set('branch_id', branchId); else url.searchParams.delete('branch_id');
                        // remove paging/search params if present to keep behavior simple
                        url.searchParams.delete('page');
                        window.location.href = url.toString();
                    });

                    $('#choose_dentist').change(function () {
                        var dentistId = $(this).val();
                        var branchId = $('#choose_branch').val();
                        if (dentistId) {
                            $('#docid').val(dentistId);
                            // Clear the message and initialize calendar
                            $('#calendar').html('');
                            display_events(dentistId, branchId);
                        } else {
                            // If "Select a Dentist" is chosen, show message again
                            $('#calendar').html('<div class="select-dentist-message"><p>Please select a dentist to view available appointment slots.</p></div>');
                        }
                    });

                    // Note: specific handlers (openConfirmBtn / proceedPaymentBtn) handle confirmation and payment.
                });

                function display_events(dentistId, branchId) {
    var events = new Array();
    var nonWorkingDates = []; // 1. Create array to store leave dates

    // ... existing fetchBookedTimes function ...
    function fetchBookedTimes(date) {
        var data = { dentist_id: dentistId, date: date };
        if (typeof branchId !== 'undefined' && branchId !== null && branchId !== '') data.branch_id = branchId;
        $.ajax({
            url: 'fetch_booked_times.php',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.status) {
                    var bookedTimes = response.booked_times;
                    updateTimeDropdown(bookedTimes);
                }
            },
            error: function (xhr, status) {
                alert("Error fetching booked times.");
            }
        });
    }

    // ... existing updateTimeDropdown function ...
    function updateTimeDropdown(bookedTimes) {
        // ... existing code inside updateTimeDropdown ...
        var timeSlots = [
            { time: "09:00:00", label: "9:00 AM  -  9:30 AM" },
            { time: "09:30:00", label: "9:30 AM  - 10:00 AM" },
            { time: "10:00:00", label: "10:00 AM  - 10:30 AM" },
            { time: "10:30:00", label: "10:30 AM  - 11:00 AM" },
            { time: "11:00:00", label: "11:00 AM  - 11:30 AM" },
            { time: "11:30:00", label: "11:30 AM  - 12:00 PM" },
            { time: "13:00:00", label: "1:00 PM  -  1:30 PM" },
            { time: "13:30:00", label: "1:30 PM  -  2:00 PM" },
            { time: "14:00:00", label: "2:00 PM  -  2:30 PM" },
            { time: "14:30:00", label: "2:30 PM  -  3:00 PM" },
            { time: "16:00:00", label: "4:00 PM  -  4:30 PM" },
            { time: "16:30:00", label: "4:30 PM  -  5:00 PM" }
        ];

        $('#appointment_time').empty();
        // Placeholder option forces explicit user selection
        $('#appointment_time').append('<option value="" disabled selected>Select Time</option>');

        var selectedDate = $('#appointment_date').val();
        var todayDate = moment().format('YYYY-MM-DD');
        var nowTime = moment().format('HH:mm:ss');

        $.each(timeSlots, function (index, slot) {
            var option = $("<option></option>").val(slot.time).text(slot.label);

            // Disable slot if at least one reservation/booking/appointment exists (single-capacity)
            if (bookedTimes[slot.time] && bookedTimes[slot.time] >= 1) {
                option.attr("disabled", "disabled");
                option.css("background-color", "#F46E34");
            }

            // Disable past time slots for today
            if (selectedDate === todayDate && slot.time <= nowTime) {
                option.attr("disabled", "disabled");
                option.css("background-color", "#d9d9d9");
                option.css("color", "#777");
            }

            $('#appointment_time').append(option);
        });
    }

    $.ajax({
        url: 'display_event.php',
        data: (function(){ var d = { dentist_id: dentistId }; if (typeof branchId !== 'undefined' && branchId !== null && branchId !== '') d.branch_id = branchId; return d; })(),
        dataType: 'json',
        success: function(response) {
            var result = response.data;
            $.each(result, function(i, item) {
                
                // 2. CAPTURE NON-WORKING DATES
                if (item.type === 'non-working') {
                    // Format date to YYYY-MM-DD to match calendar format
                    nonWorkingDates.push(moment(item.start).format('YYYY-MM-DD'));
                }

                // FORCE COLOR LOGIC
                var eventColor;
                if (item.status === 'appointment') {
                    eventColor = '#0e8923'; // Green for appointments
                } else if (item.type === 'non-working') {
                    eventColor = '#F94144'; // RED for No Service
                } else {
                    eventColor = item.color; // Fallback
                }

                // Determine final color respecting privacy rules: other patients' occupied slots use Timeslot Taken color (#F9A15D)
                var finalColor;
                var timeslotTakenColor = '#F9A15D';
                var completedColor = '#BBBBBB';
                if (!item.is_self && item.type !== 'non-working') {
                    if (item.status === 'completed') {
                        finalColor = completedColor; // keep grey for past completed
                    } else {
                        finalColor = timeslotTakenColor; // unified occupied slot color
                    }
                } else {
                    if (item.type === 'non-working') {
                        finalColor = '#F94144';
                    } else if (item.status === 'appointment') {
                        finalColor = '#0e8923'; // user's confirmed appointment
                    } else if (item.status === 'completed') {
                        finalColor = completedColor;
                    } else {
                        // booking / pending_reservation for self uses provided fallback (yellow)
                        finalColor = item.color || '#F9C74F';
                    }
                }
                // Skip rendering completed events from other patients entirely
                if (!item.is_self && item.status === 'completed') {
                    return; // do not add to events array
                }
                var eventTitle = result[i].title;
                events.push({
                    event_id: result[i].appointment_id,
                    title: eventTitle,
                    start: result[i].start,
                    end: result[i].end,
                    color: finalColor,
                    url: null,
                    status: result[i].status,
                    procedure_name: item.procedure_name,
                    patient_name: item.patient_name,
                    dentist_name: item.dentist_name,
                    type: item.type,
                    is_self: item.is_self === true
                });
            });

            // Confirm booking handler (patient/doctor view)
            $('#confirm-booking').click(function () {
                var appoid = $('#cancelAppoid').val();
                if (!appoid) {
                    alert('No appointment selected.');
                    return;
                }

                var btn = $(this);
                btn.prop('disabled', true).text('Confirming...');

                $.ajax({
                    url: '../../admin/calendar/confirm_appointment.php',
                    type: 'POST',
                    data: { appoid: appoid },
                    dataType: 'json',
                    success: function (res) {
                        if (res && res.status) {
                            var msg = res.msg || 'Booking confirmed successfully.';
                            if (res.email_sent === false || res.mail_error) {
                                msg += '\n(Note: confirmation email failed to send.' + (res.mail_error ? ' Error: ' + res.mail_error : '') + ')';
                            }
                            alert(msg);
                            $('#appointmentModal').modal('hide');
                            location.reload();
                        } else {
                            alert('Error: ' + (res.msg || 'Failed to confirm booking.'));
                        }
                    },
                    error: function () {
                        alert('Server error while confirming booking.');
                    },
                    complete: function () {
                        btn.prop('disabled', false).text('Confirm Booking');
                    }
                });
            });
            if ($('#calendar').fullCalendar('getView')) {
                $('#calendar').fullCalendar('destroy');
            }

            // Configure FullCalendar; compact rows on desktop, roomy on mobile
            var isMobile = window.matchMedia && window.matchMedia('(max-width: 768px)').matches;
            $('#calendar').fullCalendar({
                defaultView: 'month',
                timeZone: 'local',
                fixedWeekCount: false,
                height: 'auto',
                contentHeight: 'auto',
                aspectRatio: isMobile ? 1.05 : 1.6,
                editable: false, // prevent drag/drop & resize
                eventStartEditable: false,
                eventDurationEditable: false,
                droppable: false,
                selectable: true,
                selectHelper: true,
                select: function(start, end) {
                    var selectedDate = moment(start).format('YYYY-MM-DD');

                    // CHECK FOR NO SERVICE DAY (Prevents Clicking Empty Space)
                    var clientEvents = $('#calendar').fullCalendar('clientEvents');
                    var isNoService = false;

                    $.each(clientEvents, function(index, event) {
                        if (event.type === 'non-working' && moment(event.start).format('YYYY-MM-DD') === selectedDate) {
                            isNoService = true;
                            return false;
                        }
                    });

                    if (isNoService) {
                        alert("This date is marked as 'No Service'. Please choose another date.");
                        $('#calendar').fullCalendar('unselect');
                        return;
                    }

                    $('#appointment_date').val(selectedDate);
                    // Initialize hidden event_name with default; will update when procedure chosen
                    $('#event_name').val('My Appointment');
                    var today = moment().format('YYYY-MM-DD');
                    var maxAllowedDate = moment().add(2, 'months').format('YYYY-MM-DD');
                    var dayOfWeek = moment(start).day();

                    if (selectedDate < today) {
                        alert("You cannot book appointments for past dates!");
                        return;
                    }

                    if (selectedDate > maxAllowedDate) {
                        alert("You can only book appointments within 2 months from today!");
                        return;
                    }

                    // Removed hard-coded 'no service on Thursdays' restriction.

                    fetchBookedTimes(selectedDate);
                    $('#event_entry_modal').modal('show');
                },
                events: events,
                eventRender: function(event, element, view) {
                    // Make other patients' events unclickable for privacy
                    if (!event.is_self && event.type !== 'non-working') {
                        element.css('cursor', 'not-allowed');
                        // Do not bind click handler
                    } else {
                    element.on('click', function() {
                        if (event.type === 'non-working') {
                            return; // Do nothing
                        }
                        // ... existing modal logic ...
                        $('#modalProcedureName').text(event.procedure_name || 'N/A');
                        $('#modalPatientName').text(event.patient_name || 'N/A');
                        $('#modalDentistName').text(event.dentist_name || 'N/A');
                        $('#modalDate').text(event.start ? new Date(event.start).toLocaleDateString() : 'N/A');
                        var startTime = moment(event.start).format('h:mm A');
                        var endTime = moment(event.end).format('h:mm A');
                        $('#modalTime').text(startTime);
                        $('#modalStatus').text(
                            event.status === 'appointment' ?
                            'Appointment Confirmed' :
                            event.status === 'completed' ?
                            'Completed' :
                            'Booking'
                        );

                        $('#appointmentModal').on('show.bs.modal', function() {
                            $('#cancel-appointment').hide();
                            if (event.status === 'booking') {
                                $('#confirm-booking').show();
                                $('#cancel-appointment').show();
                                // Hide pay now button for confirmed bookings
                                $('#payNowBtn').hide();
                            } else if (event.status === 'appointment') {
                                $('#confirm-booking').hide();
                                $('#cancel-appointment').show();
                                $('#payNowBtn').hide();
                            } else if (event.status === 'completed') {
                                $('#confirm-booking').hide();
                                $('#cancel-appointment').hide();
                                $('#payNowBtn').hide();
                            } else if (event.status === 'pending_reservation') {
                                // Unpaid reservation: allow cancel + pay now
                                $('#confirm-booking').hide();
                                $('#cancel-appointment').show();
                                $('#payNowBtn').show();
                            }
                        });
                        
                         $('#cancel-appointment').off('click').on('click', function() {
                                    var confirmMessage = "Are you sure you want to cancel this " +
                                        (event.status === 'pending_reservation' ? 'pending reservation' : (event.status === 'booking' ? 'booking' : 'appointment')) + "?";

                                        if (confirm(confirmMessage)) {
                                        $.ajax({
                                            url: (event.status === 'pending_reservation' ? '../cancel_pending_reservation.php' : 'cancel_appointment.php'),
                                            type: 'POST',
                                            data: { appoid: event.event_id },
                                            dataType: 'json',
                                            success: function (response) {
                                                if (response && response.status) {
                                                    var okMsg = (event.status === 'pending_reservation')
                                                        ? "Pending reservation cancelled successfully."
                                                        : (event.status === 'booking' ? "Booking cancelled successfully." : "Appointment cancelled successfully.");
                                                    alert(okMsg);
                                                    $('#appointmentModal').modal('hide');
                                                    location.reload();
                                                } else {
                                                    alert("Error: " + (response.msg || 'Unknown error occurred'));
                                                }
                                            },
                                            error: function(xhr, status, error) {
                                                try {
                                                    var response = JSON.parse(xhr.responseText);
                                                    alert("Error: " + (response.msg || error));
                                                } catch (e) {
                                                    alert("Error: " + error);
                                                }
                                            }
                                        });
                                    }
                                });


                        // Configure Pay Now button for pending reservations (only for owner)
                        if (event.status === 'pending_reservation' && event.is_self) {
                            $('#payNowBtn').show();
                            $('#payNowBtn').off('click').on('click', function() {
                                var amount = <?php echo json_encode($reservation_fee); ?>;
                                // Use parent folder path since this file is in /patient/calendar/
                                window.location.href = '../pay_balance.php?id=' + encodeURIComponent(event.event_id) + '&amount=' + encodeURIComponent(amount) + '&source=reservation';
                            });
                        } else {
                            $('#payNowBtn').hide();
                        }

                        $('#appointmentModal').modal('show');
                    });
                    }

                    element.css('background-color', event.color);
                    element.css('border-color', event.color);
                },
                dayRender: function(date, cell) {
                    var formattedDate = date.format('YYYY-MM-DD');

                    // 3. APPLY RED BACKGROUND TO LEAVE DATES
                    // We use indexOf because standard arrays in old JS might not support .includes, 
                    // but modern browsers do. Using $.inArray or indexOf is safer.
                    if (nonWorkingDates.indexOf(formattedDate) > -1) {
                        cell.css("background-color", "#FFF2F2"); // Match Thursday color
                    }

                    // No hard-coded weekday coloring here; non-working dates are handled above.

                    // Existing Past/Future Check
                    var today = moment().startOf('day');
                    var maxAllowedDate = moment().add(2, 'months').startOf('day');
                    if (date < today || date > maxAllowedDate) {
                        cell.css("background-color", "#fff2f2");
                        cell.css("pointer-events", "none");
                    }
                }
            });
        },
        error: function(xhr, status, error) {
            var msg = "Error fetching events. Status: " + xhr.status;
            if (error) msg += " (" + error + ")";
            if (xhr.responseText) {
                msg += "\nResponse: " + xhr.responseText.substring(0,300);
            }
            alert(msg);
            console.error('Calendar events fetch failed:', xhr.status, status, error, xhr.responseText);
        }
    });

                }

                function save_event() {
    // Auto-fill event_name from selected procedure text (or generic fallback)
    var procText = $('#procedure option:selected').text();
    if (procText && procText.trim() !== '' && procText.toLowerCase() !== 'select procedure') {
        $('#event_name').val(procText.trim());
    } else {
        $('#event_name').val('My Appointment');
    }
    var event_name = $("#event_name").val();
    // Procedure selection removed from patient flow
    var procedure = null;
    var patient_name = $("#patient_name").val();
    var appointment_date = $("#appointment_date").val();
    var appointment_time = $("#appointment_time").val();
    var docid = $('#docid').val();

    // Validate only date, time, and doctor now
    if (!appointment_date || !appointment_time || !docid) {
        alert("Please select a date, time slot, and dentist.");
        return false;
    }

    // Target only the payment progression button, not all .btn-primary buttons
    var submitButton = $('#proceedPaymentBtn');
    submitButton.prop('disabled', true);
    submitButton.text('Processing Payment...');

    $.ajax({
        url: "save_event.php", // This now points to our new PayMongo logic
        type: "POST",
        dataType: 'json',
        data: {
            event_name: event_name,
            procedure: procedure,
            patient_name: patient_name,
            appointment_date: appointment_date,
            appointment_time: appointment_time,
            docid: docid,
            custom_price: $('#customPrice').val()
        },
        success: function (response) {
            if (response.status === true) {
                // IF SUCCESSFUL, REDIRECT TO PAYMONGO
                if (response.payment_url) {
                    window.location.href = response.payment_url;
                } else {
                    alert("Booking saved but no payment URL returned.");
                    location.reload();
                }
            } else {
                alert(response.msg);
                submitButton.prop('disabled', false);
                submitButton.text('Confirm');
            }
        },
        error: function (xhr, status, error) {
            console.log(xhr.responseText);
            alert('Error connecting to server. Check console for details.');
            submitButton.prop('disabled', false);
            submitButton.text('Confirm');
        }
    });
}

                // (Procedure selection removed) Helper retained only for money formatting.

                function formatMoney(num) {
                    if (isNaN(num)) return '-';
                    return '₱' + Number(num).toFixed(2);
                }

                // Show confirmation modal populated with reservation fee only
                function showConfirmModal() {
                    var timeVal = $('#appointment_time').val();
                    if (!timeVal || timeVal === '') {
                        alert('Please select a time slot before confirming.');
                        return;
                    }
                    var reservationFee = <?php echo json_encode($reservation_fee); ?>;
                    document.getElementById('confirmReservationAmount').innerText = formatMoney(parseFloat(reservationFee));
                    $('#confirmReservationModal').modal('show');
                }

                // wire up buttons when modal is ready
                $(document).ready(function () {
                    // Initialize reservation fee display (procedure UI removed)
                    var initialReservationFee = <?php echo json_encode($reservation_fee); ?>;
                    $('#reservationFee').text(formatMoney(parseFloat(initialReservationFee)));

                    $('#openConfirmBtn').on('click', function (e) {
                        e.preventDefault();
                        var timeVal = $('#appointment_time').val();
                        if (!timeVal || timeVal === '') {
                            alert('Please select a time slot before confirming.');
                            return;
                        }
                        showConfirmModal();
                    });

                    $('#proceedPaymentBtn').on('click', function () {
                        // Final guard: ensure time selected
                        var timeVal = $('#appointment_time').val();
                        if (!timeVal || timeVal === '') {
                            alert('Please select a time slot before proceeding to payment.');
                            return;
                        }
                        // Close confirmation modal and submit
                        $('#confirmReservationModal').modal('hide');
                        // submit via existing save_event which calls save_event() AJAX
                        save_event();
                    });
                });

                // Add this function to update notification count display
                function updateNotificationCount(newCount) {
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

                function markAsRead(notificationId, element) {
                    $.ajax({
                        url: '../mark_notification_read.php',
                        method: 'POST',
                        data: { id: notificationId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                element.classList.remove('unread');
                                
                                // Count remaining unread notifications
                                const unreadCount = document.querySelectorAll('.notification-item.unread').length;
                                updateNotificationCount(unreadCount);
                            }
                        },
                        error: function(xhr) {
                            console.error("Error marking notification as read:", xhr.responseText);
                        }
                    });
                }

                function markAllAsRead() {
                    $.ajax({
                        url: '../mark_all_notifications_read.php',
                        method: 'POST',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Remove unread class from all notifications
                                document.querySelectorAll('.notification-item.unread').forEach(item => {
                                    item.classList.remove('unread');
                                });
                                
                                // Update count to zero
                                updateNotificationCount(0);
                            }
                        },
                        error: function(xhr) {
                            console.error("Error marking all notifications as read:", xhr.responseText);
                        }
                    });
                }

                document.addEventListener('DOMContentLoaded', function () {
                    var firstOption = document.querySelector('#procedure option');
                    if (firstOption) {
                        showProcedureDescription(document.getElementById('procedure'));
                    }
                    $('[data-toggle="tooltip"]').tooltip();

                    // Notification dropdown toggle
                    const notificationContainer = document.getElementById('notificationContainer');
                    const notificationDropdown = document.getElementById('notificationDropdown');

                    if (notificationContainer && notificationDropdown) {
                        notificationContainer.addEventListener('click', function(e) {
                            e.stopPropagation();
                            notificationDropdown.classList.toggle('show');
                        });
                        
                        // Close dropdown when
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
        notificationDropdown.classList.remove('show');
    });
}

function markAsRead(notificationId, element) {
    $.ajax({
        url: 'mark_notification_read.php',
        method: 'POST',
        data: { id: notificationId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
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
                    statNumber.textContent = parseInt(statNumber.textContent) - 1;
                }
            }
        },
        error: function(xhr) {
            console.error("Error marking notification as read:", xhr.responseText);
        }
    });
}
function markAllAsRead() {
    $.ajax({
        url: 'mark_all_notifications_read.php',
        method: 'POST',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
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
        },
        error: function(xhr) {
            console.error("Error marking all notifications as read:", xhr.responseText);
        }
    });
}
                });
            </script>
        </div>
        <div class="modal fade" id="appointmentModal" tabindex="-1" role="dialog" aria-labelledby="appointmentModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="appointmentModalLabel">Appointment Details</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Patient:</strong> <span id="modalPatientName"></span></p>
                        <p><strong>Dentist:</strong> <span id="modalDentistName"></span></p>
                        <p><strong>Date:</strong> <span id="modalDate"></span></p>
                        <p><strong>Time:</strong> <span id="modalTime"></span></p>
                        <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                    </div>
                    <div class="modal-footer">
                        <!-- Pay Now button for pending reservations -->
                        <button type="button" class="btn btn-primary" id="payNowBtn" style="display:none;">Pay Now</button>
                        <button type="button" class="btn btn-danger" id="cancel-appointment" style="margin-left:8px;">Cancel</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" style="margin-left:8px;">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
