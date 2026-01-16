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
// Branch restriction (e.g., Bacoor-only admin)
$restrictedBranchId = isset($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id'] ? (int)$_SESSION['restricted_branch_id'] : 0;

// Ensure stacked procedures table exists (in case migration not executed)
try {
        $database->query("CREATE TABLE IF NOT EXISTS appointment_procedures (
            id INT AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT NOT NULL,
            procedure_id INT NOT NULL,
            agreed_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_appt_proc_appointment FOREIGN KEY (appointment_id) REFERENCES appointment(appoid) ON DELETE CASCADE,
            CONSTRAINT fk_appt_proc_procedure FOREIGN KEY (procedure_id) REFERENCES procedures(procedure_id) ON DELETE RESTRICT,
            INDEX idx_appt_proc_appointment (appointment_id),
            INDEX idx_appt_proc_procedure (procedure_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
        // If creation fails, suppress stacked procedures feature
}

// Get counts for dashboard (respect branch restriction)
if ($restrictedBranchId > 0) {
    $dentistrow = $database->query("SELECT * FROM doctor WHERE status='active' AND (branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId));");
    $patientrow = $database->query("SELECT * FROM patient WHERE status='active' AND branch_id = $restrictedBranchId;");
    $appointmentrow = $database->query("SELECT * FROM appointment WHERE status='booking' AND docid IN (SELECT docid FROM doctor WHERE branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId));");
    $schedulerow = $database->query("SELECT * FROM appointment WHERE status='appointment' AND docid IN (SELECT docid FROM doctor WHERE branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId));");
} else {
    $dentistrow = $database->query("select * from doctor where status='active';");
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

// Error message: no procedures assigned
if (isset($_GET['error']) && $_GET['error'] === 'no_procedures_assigned') {
    echo '
    <div class="success-message" style="position: fixed; top: 20px; right: 20px; background: #d32f2f; color: white; padding: 15px; border-radius: 5px; z-index: 10000; animation: fadeIn 0.5s, fadeOut 0.5s 3s;">
        Cannot complete the appointment. Please assign at least one procedure.
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
    <link rel="stylesheet" href="../css/responsive-admin.css">
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
        /* Manage Procedures button with icon (fallback chain: White then Blue) */
        .manage-proc-btn {
            background:#1e88e5;
            color:#fff;
            margin-top:4px;
            display:inline-block;
            background-image:url('../Media/Icon/White/edit.png'), url('../Media/Icon/Blue/edit.png');
            background-repeat:no-repeat;
            background-position:12px center;
            background-size:18px;
            padding:8px 16px 8px 40px;
            border:none;
            border-radius:4px;
            cursor:pointer;
            font-size:13px;
            font-weight:600;
        }
        .manage-proc-btn:hover { background-color:#1565c0; }

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
    <!-- Mobile hamburger toggle for sidebar -->
    <button class="hamburger-admin" id="hamburgerAdmin" aria-label="Toggle sidebar" aria-controls="adminSidebar" aria-expanded="false">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <script>
        // Mobile sidebar toggle for Appointment page
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
    appointment.payment_status,
    appointment.status,
    patient.pname, 
    patient.pemail,
    patient.ptel,
    patient.profile_pic,
    doctor.docname,
    b.name AS branch_name
FROM appointment 
INNER JOIN patient ON appointment.pid = patient.pid 
INNER JOIN doctor ON appointment.docid = doctor.docid 
LEFT JOIN procedures ON appointment.procedure_id = procedures.procedure_id 
LEFT JOIN branches b ON doctor.branch_id = b.id
WHERE (appointment.status = 'appointment' OR (appointment.status = 'completed' AND appointment.payment_status != 'paid'))";

                    // Branch restriction
                    if ($restrictedBranchId > 0) {
                        $sqlmain .= " AND (doctor.branch_id = $restrictedBranchId OR doctor.docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))";
                    }

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
                LEFT JOIN procedures ON appointment.procedure_id = procedures.procedure_id 
                LEFT JOIN branches b ON doctor.branch_id = b.id
                WHERE (appointment.status = 'appointment' OR (appointment.status = 'completed' AND appointment.payment_status != 'paid'))";

                    if ($restrictedBranchId > 0) {
                        $count_query .= " AND (doctor.branch_id = $restrictedBranchId OR doctor.docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))";
                    }

                    if (isset($_GET['search'])) {
                        $count_query .= " AND (patient.pname LIKE '%$search%' OR doctor.docname LIKE '%$search%' OR procedures.procedure_name LIKE '%$search%')";
                    }

                    $count_result = $database->query($count_query);
                    $count_row = $count_result->fetch_assoc();
                    $total_pages = ceil($count_row['total'] / $results_per_page);
                    ?>

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive"><div class="table-container">
                            <table class="table">
                                <thead>
    <tr>
        <th>Profile</th>
        <th>Patient Name</th>
        <th>Dentist</th>
        <th>Branch</th>
        <th>Procedure / Assignment</th>
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
                                                <div class="cell-text"><?php echo isset($row['branch_name']) ? htmlspecialchars($row['branch_name']) : '-'; ?></div>
                                            </td>
                                            <td>
                                                <div class="cell-text">
                                                    <?php
                                                    // Show full list of stacked procedures (no +X more summary) and provide a span for live JS updates.
                                                    $appIdLocal = (int)$row['appoid'];
                                                    $procListRes = $database->query("SELECT p.procedure_name FROM appointment_procedures ap INNER JOIN procedures p ON ap.procedure_id=p.procedure_id WHERE ap.appointment_id=$appIdLocal ORDER BY ap.id ASC");
                                                    $names = [];
                                                    if ($procListRes) {
                                                        while ($pRow = $procListRes->fetch_assoc()) {
                                                            $names[] = htmlspecialchars($pRow['procedure_name']);
                                                        }
                                                    }
                                                    $proceduresText = empty($names) ? '<span style=\"color:#ff9800; font-weight:600;\">Not Assigned</span>' : implode(', ', $names);
                                                    echo '<span id="appointmentProceduresList_' . $appIdLocal . '">' . $proceduresText . '</span>';
                                                    if ($row['status'] == 'appointment') {
                                                        echo '<br><button type="button" onclick="openAssignProcedureModal(' . $appIdLocal . ')" class="manage-proc-btn">Manage Procedures</button>';
                                                    }
                                                    ?>
                                                </div>
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
            echo '<span style="color:green; font-weight:bold;">Paid</span>';
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
        // Two-step flow: 1) mark appointment completed, 2) receive cash
        // Build return params to preserve pagination/search/sort context
        $returnParams = 'page=' . (isset($page) ? intval($page) : 1);
        if (isset($_GET['search']) && $_GET['search'] !== '') {
            $returnParams .= '&search=' . urlencode($_GET['search']);
        }
        $currentSortParam = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
        $returnParams .= '&sort=' . urlencode($currentSortParam);

        if ($row['status'] == 'appointment' && $payStatus != 'paid') : ?>
            <a href="complete_appointment.php?id=<?php echo $row['appoid']; ?>&step=complete&<?php echo $returnParams; ?>" 
               class="action-btn btn-complete"
               onclick="return confirm('Mark this appointment as completed?');">
               Complete
            </a>
        <?php elseif ($row['status'] == 'completed' && $payStatus != 'paid') : ?>
            <a href="complete_appointment.php?id=<?php echo $row['appoid']; ?>&step=receive_cash&<?php echo $returnParams; ?>" 
                    class="action-btn btn-complete"
                    onclick="return confirm('Confirm cash payment received and mark as paid?');">
                    Receive Cash
                </a>
        <?php endif; ?>

        <?php
        // Show "View Receipt" only for patient-booked reservations (i.e., has reservation fee context)
        $reservationPaid = isset($row['reservation_paid']) ? intval($row['reservation_paid']) : 0;
        $statusVal = isset($row['status']) ? strtolower($row['status']) : '';
        $payStatusVal = isset($row['payment_status']) ? strtolower($row['payment_status']) : '';
        $hasReservationFlag = ($reservationPaid === 1)
            || ($payStatusVal === 'partial')
            || ($statusVal === 'pending_reservation')
            || ($statusVal === 'booking');

        if ($hasReservationFlag) {
            echo '<a href="../patient/receipt.php?appoid=' . $row['appoid'] . '" target="_blank" class="action-btn view-btn">View Receipt</a>';
        }
        ?>

        <a href="?action=drop&id=<?php echo $row['appoid']; ?>&name=<?php echo urlencode($row['pname']); ?>&<?php echo $returnParams; ?>" class="action-btn remove-btn">Cancel</a>
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
                            $branchScope = '';
                            if (isset($restrictedBranchId) && $restrictedBranchId > 0) {
                                $branchScope = " AND (doctor.branch_id = $restrictedBranchId OR doctor.docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))";
                            }
                            $upcomingAppointments = $database->query("
                                SELECT
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

                            if ($upcomingAppointments->num_rows > 0) {
                                while ($appointment = $upcomingAppointments->fetch_assoc()) {
                                    // Use null-safe fallbacks to avoid passing null to htmlspecialchars()
                                    $pname = htmlspecialchars($appointment['patient_name'] ?? '');
                                    $dname = htmlspecialchars($appointment['doctor_name'] ?? '');
                                    $proc = htmlspecialchars($appointment['procedure_name'] ?? '');
                                    $branch = htmlspecialchars($appointment['branch_name'] ?? '');
                                    $apodate = '';
                                    $apotime = '';
                                    if (!empty($appointment['appodate'])) {
                                        $apodate = htmlspecialchars(date('F j, Y', strtotime($appointment['appodate'])));
                                    }
                                    if (!empty($appointment['appointment_time'])) {
                                        $apotime = htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time'])));
                                    }

                                    $html = '<div class="appointment-item">';
                                    $html .= '<h4 class="appointment-type">' . $pname . '</h4>';
                                    $html .= '<p class="appointment-dentist">With Dr. ' . $dname . '</p>';
                                    $html .= '<p class="appointment-date">' . $proc . '</p>';
                                    $suffix = ($branch !== '') ? (' - ' . $branch) : '';
                                    $html .= '<p class="appointment-date">' . $apodate . ' • ' . $apotime . $suffix . '</p>';
                                    $html .= '</div>';
                                    echo $html;
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
        if (isset($_GET['action']) && $_GET['action'] === 'drop' && isset($_GET['id']) && isset($_GET['name'])) {
            $id = $_GET["id"];
            $action = $_GET["action"];
            if ($action == 'drop') {
                $nameget = $_GET["name"];
                // Build return query string to preserve page/search/sort
                $parts = [];
                if (isset($_GET['page'])) { $parts[] = 'page=' . intval($_GET['page']); }
                if (isset($_GET['search']) && $_GET['search'] !== '') { $parts[] = 'search=' . urlencode($_GET['search']); }
                if (isset($_GET['sort']) && $_GET['sort'] !== '') { $parts[] = 'sort=' . urlencode($_GET['sort']); }
                $returnQuery = !empty($parts) ? implode('&', $parts) : '';

                echo '
                <div id="popup1" class="overlay">
                    <div class="popup" style="max-height: 400px;">
                        <center>
                            <h2>Cancel Appointment</h2>
                            <a class="close" href="appointment.php' . (!empty($returnQuery) ? '?' . $returnQuery : '') . '">&times;</a>
                            <div class="content">
                                <form id="cancelForm" action="delete-appointment.php" method="POST">
                                    <input type="hidden" name="appoid" value="'.$id.'">
                                    <input type="hidden" name="source" value="admin">
                                    '.(!empty($returnQuery) ? '<input type="hidden" name="return_query" value="'.htmlspecialchars($returnQuery).'">' : '').'
                                    
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
                                        <a href="appointment.php' . (!empty($returnQuery) ? '?' . $returnQuery : '') . '" class="btn btn-secondary" style="padding: 10px 20px;">Cancel</a>
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
                                body: formData,
                                credentials: "same-origin"
                            })
                            .then(response => response.text())
                            .then(text => {
                                // Try to parse JSON; if parsing fails, show the raw response for debugging
                                try {
                                    const data = JSON.parse(text);
                                    if (data.status) {
                                        alert(data.message);
                                        if (data.redirect) {
                                            window.location.href = data.redirect;
                                        } else {
                                            window.location.href = "appointment.php?cancel_success=1' . (!empty($returnQuery) ? '&' . $returnQuery : '') . '";
                                        }
                                    } else {
                                        alert("Error: " + (data.message || text));
                                    }
                                } catch (e) {
                                    console.error("Non-JSON response from server:", text);
                                    // Show server output to help debugging
                                    alert("Server response (not JSON):\n" + text);
                                }
                            })
                            .catch(error => {
                                console.error("Fetch error:", error);
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
                                appointment.total_amount,
                                appointment.status,
                                patient.pname, 
                                patient.pemail,
                                patient.ptel,
                                doctor.docname
                            FROM appointment 
                            INNER JOIN patient ON appointment.pid = patient.pid 
                            INNER JOIN doctor ON appointment.docid = doctor.docid 
                            LEFT JOIN procedures ON appointment.procedure_id = procedures.procedure_id 
                            WHERE appointment.appoid='$id'";
                $result = $database->query($sqlmain);
                $row = $result->fetch_assoc();
                $patient_name = $row["pname"];
                $dentist_name = $row["docname"];
                $procedure_name = $row["procedure_name"]; // legacy single
                $appodate = $row["appodate"];
                $appointment_time = $row["appointment_time"];
                $patient_email = $row["pemail"];
                $patient_tel = $row["ptel"];
                $status_view = $row['status'];

                // Load stacked procedures
                $stackRes = $database->query("SELECT p.procedure_name, ap.agreed_price FROM appointment_procedures ap INNER JOIN procedures p ON ap.procedure_id=p.procedure_id WHERE ap.appointment_id='$id' ORDER BY ap.id ASC");
                $stackRows = [];
                $stackTotal = 0;
                if ($stackRes) {
                    while ($s = $stackRes->fetch_assoc()) { $stackRows[] = $s; $stackTotal += (float)$s['agreed_price']; }
                }
                $hasStack = count($stackRows) > 0;
                    // Build return query string to preserve page/search/sort for the view popup
                    $parts = [];
                    if (isset($_GET['page'])) { $parts[] = 'page=' . intval($_GET['page']); }
                    if (isset($_GET['search']) && $_GET['search'] !== '') { $parts[] = 'search=' . urlencode($_GET['search']); }
                    if (isset($_GET['sort']) && $_GET['sort'] !== '') { $parts[] = 'sort=' . urlencode($_GET['sort']); }
                    $returnQuery = !empty($parts) ? implode('&', $parts) : '';

                    echo '
                <div id="popup1" class="overlay">
                    <div class="popup">
                        <center>
                            <h2>Appointment Details</h2>
                            <a class="close" href="appointment.php' . (!empty($returnQuery) ? '?' . $returnQuery : '') . '">&times;</a>
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
                                        <td class="label-td" valign="top">
                                            <label for="procedure" class="form-label">Procedures:</label>
                                        </td>
                                        <td>';
                                            if($hasStack){
                                                echo '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
                                                echo '<tr style="background:#f5f5f5;"><th style="text-align:left;padding:6px;border:1px solid #ddd;">Name</th><th style="text-align:right;padding:6px;border:1px solid #ddd;">Agreed Price</th></tr>';
                                                foreach($stackRows as $sr){
                                                    echo '<tr><td style="padding:6px;border:1px solid #ddd;">'.htmlspecialchars($sr['procedure_name']).'</td><td style="padding:6px;border:1px solid #ddd;text-align:right;">₱'.number_format((float)$sr['agreed_price'],2).'</td></tr>';
                                                }
                                                echo '<tr><td style="padding:6px;border:1px solid #ddd;font-weight:600;text-align:right;" colspan="2">Total: ₱'.number_format($stackTotal,2).'</td></tr>';
                                                echo '</table>';
                                            } else {
                                                echo '<em>No procedures assigned.</em>';
                                            }
                                        echo '</td>
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
                                        <td class="label-td">
                                            <label for="status" class="form-label">Status:</label>
                                        </td>
                                        <td>' . htmlspecialchars(ucfirst($status_view)) . '</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" style="padding-top: 20px;">
                                            <a href="appointment.php' . (!empty($returnQuery) ? '?' . $returnQuery : '') . '"><input type="button" value="OK" class="login-btn btn-primary-soft btn"></a>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </center>
                    </div>
                </div>';
            }
        }
        // View action removed
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
        <!-- Assign / Stack Procedures Modal -->
        <div id="assignProcedureModal" class="overlay" style="display:none;">
            <div class="popup" style="max-width:620px;padding:25px 30px;">
                <h2 style="text-align:center;margin-top:0;">Assign / Stack Procedures</h2>
                <a class="close" href="#" onclick="closeAssignProcedureModal();return false;" aria-label="Close">&times;</a>
                <div id="assignProcedureMessage" style="display:none;margin-bottom:12px;font-weight:600;color:#c00;"></div>
                <input type="hidden" id="assign_appoid" />

                <div style="border:1px solid #e0e0e0;padding:12px 15px;border-radius:6px;margin-bottom:18px;background:#fafafa;">
                    <strong>Current Procedures</strong>
                    <div id="currentProceduresList" style="margin-top:10px;font-size:14px;max-height:120px;overflow-y:auto;"></div>
                    <div id="currentProceduresTotal" style="margin-top:10px;font-weight:600;color:#1e88e5;">Total: ₱0.00</div>
                    <hr style="margin:12px 0;border:none;border-top:1px solid #eee;" />
                    <strong>Current Discounts</strong>
                    <div id="currentDiscountsList" style="margin-top:10px;font-size:14px;max-height:80px;overflow-y:auto;"></div>
                </div>

                <form id="addProcedureForm" onsubmit="submitAddProcedure(event)">
                    <label for="assign_procedure_id" style="font-weight:600;">Add Procedure</label>
                    <select name="procedure_id" id="assign_procedure_id" required style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:12px;" onchange="togglePriceInput()">
                        <option value="">Select Procedure</option>
                        <?php
                        $procResForModal = $database->query("SELECT procedure_id, procedure_name FROM procedures WHERE procedure_name NOT IN ('PWD Discount','Senior Citizen Discount') ORDER BY procedure_name ASC");
                        while ($procResForModal && $p = $procResForModal->fetch_assoc()) {
                            echo '<option value="' . (int)$p['procedure_id'] . '">' . htmlspecialchars($p['procedure_name']) . '</option>';
                        }
                        // Note: discount options moved to a separate dropdown below
                        ?>
                    </select>
                    <div id="priceInputGroup" style="display:none;margin-bottom:14px;">
                        <label for="procedure_price" style="font-weight:600;">Agreed Price (₱)</label>
                        <input type="number" min="0" step="0.01" id="procedure_price" name="procedure_price" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;" placeholder="Enter price" />
                    </div>
                    <div id="consultationNote" style="display:none;font-size:13px;color:#555;margin-bottom:14px;">Consultation price is fixed at <strong>₱500.00</strong>.</div>
                    <div id="discountNote" style="display:none;font-size:13px;color:#555;margin-bottom:14px;">A 20% discount will be applied to the current procedures total.</div>
                    <!-- Separate Discount controls -->
                    <label for="assign_discount_id" style="font-weight:600;margin-top:6px;display:block;">Add Discount</label>
                    <select id="assign_discount_id" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;margin-bottom:12px;" onchange="toggleDiscountNote()">
                        <option value="">Select Discount</option>
                        <option value="discount_pwd">PWD Discount (20%)</option>
                        <option value="discount_senior">Senior Citizen Discount (20%)</option>
                    </select>

                    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:8px;">
                        <button type="submit" class="action-btn" style="background:#1e88e5;color:#fff;min-width:140px;">Add Procedure</button>
                        <button type="button" id="assign_discount_btn" onclick="submitAddDiscount()" class="action-btn" style="background:#2e7d32;color:#fff;min-width:140px;">Add Discount</button>
                        <button type="button" onclick="closeAssignProcedureModal()" class="action-btn remove-btn" style="background:#6c757d;color:#fff;min-width:140px;">Done</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
        function formatPeso(val){return '₱'+Number(val).toFixed(2);}        
        function openAssignProcedureModal(appoid){
            document.getElementById('assign_appoid').value = appoid;
            document.getElementById('assignProcedureMessage').style.display='none';
            document.getElementById('assign_procedure_id').value='';
            togglePriceInput();
            loadAssignedProcedures(appoid);
            document.getElementById('assignProcedureModal').style.display='flex';
            document.body.style.overflow='hidden';
        }
        function closeAssignProcedureModal(){
            document.getElementById('assignProcedureModal').style.display='none';
            document.body.style.overflow='';
        }
        function togglePriceInput(){
            const select = document.getElementById('assign_procedure_id');
            const priceGroup = document.getElementById('priceInputGroup');
            const note = document.getElementById('consultationNote');
            const discountNote = document.getElementById('discountNote');
            const selectedText = select.options[select.selectedIndex]?.text || '';
            // Reset
            discountNote && (discountNote.style.display='none');
            note.style.display='none';
            if(selectedText.toLowerCase()==='consultation'){
                priceGroup.style.display='none';
                note.style.display='block';
            } else if(select.value){
                priceGroup.style.display='block';
                note.style.display='none';
            } else {
                priceGroup.style.display='none';
                note.style.display='none';
            }
        }

        function toggleDiscountNote(){
            const dsel = document.getElementById('assign_discount_id');
            const discountNote = document.getElementById('discountNote');
            if(dsel && dsel.value && String(dsel.value).startsWith('discount_')){
                discountNote.style.display='block';
            } else {
                discountNote.style.display='none';
            }
        }
        async function loadAssignedProcedures(appoid){
            const listBox = document.getElementById('currentProceduresList');
            listBox.innerHTML = '<em>Loading...</em>';
            try {
                const res = await fetch('list_appointment_procedures_ajax.php?appoid='+encodeURIComponent(appoid));
                const data = await res.json();
                if(!data.success){listBox.innerHTML='<em>Error loading procedures</em>';return;}

                // Render non-discount procedures
                const procList = data.procedures || [];
                if(procList.length===0){
                    listBox.innerHTML='<em>No procedures assigned yet.</em>';
                } else {
                    listBox.innerHTML = procList.map(p=>`<div style="display:flex;justify-content:space-between;align-items:center;border:1px solid #ddd;padding:6px 10px;border-radius:4px;margin-bottom:6px;">\n<span>${p.procedure_name} - <strong>${formatPeso(p.agreed_price)}</strong></span>\n<button type="button" onclick="deleteProcedure(${p.id})" style="background:#e53935;color:#fff;border:none;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:12px;">Delete</button></div>`).join('');
                }

                // Render discounts separately and compute totals
                const discountsListBox = document.getElementById('currentDiscountsList');
                const discounts = data.discounts || [];
                const discTotal = discounts.reduce((s,x)=>s + Number(x.agreed_price), 0); // negative or 0
                if(discounts.length===0){
                    discountsListBox.innerHTML = '<em>No discounts applied.</em>';
                } else {
                    discountsListBox.innerHTML = discounts.map(d=>`<div style="display:flex;justify-content:space-between;align-items:center;border:1px solid #f0f0f0;padding:6px 10px;border-radius:4px;margin-bottom:6px;">\n<span>${d.procedure_name} - <strong>${formatPeso(d.agreed_price)}</strong></span>\n<button type="button" onclick="deleteProcedure(${d.id})" style="background:#e53935;color:#fff;border:none;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:12px;">Delete</button></div>`).join('');
                }

                // Compute base total (sum of non-discount procedures)
                const baseTotal = (data.procedures || []).reduce((s,x)=>s + Number(x.agreed_price), 0);
                const discountAmount = Math.abs(discTotal); // positive value of discount
                const totalAfter = Number((baseTotal - discountAmount).toFixed(2));

                // Update totals display: before discount, discount amount, after discount
                const procTotalEl = document.getElementById('currentProceduresTotal');
                procTotalEl.innerHTML = `Total before discount: <strong>${formatPeso(baseTotal)}</strong><br>Discount: <strong style="color:#e53935;">${formatPeso(-discTotal)}</strong><br>Total after discount: <strong>${formatPeso(totalAfter)}</strong>`;

                // Disable discount controls if a discount already exists (no stacking allowed)
                try{
                    const dsel = document.getElementById('assign_discount_id');
                    const dbtn = document.getElementById('assign_discount_btn');
                    if(dsel) dsel.disabled = (discounts.length>0);
                    if(dbtn) dbtn.disabled = (discounts.length>0);
                }catch(e){}

                // Live update (only non-discount procedures)
                const procSpan = document.getElementById('appointmentProceduresList_'+appoid);
                if(procSpan){
                    if(procList.length===0){
                        procSpan.innerHTML = '<span style="color:#ff9800; font-weight:600;">Not Assigned</span>';
                    } else {
                        procSpan.textContent = procList.map(p=>p.procedure_name).join(', ');
                    }
                }
            } catch(e){
                listBox.innerHTML='<em>Network error.</em>';
            }
        }
        async function submitAddProcedure(e){
            e.preventDefault();
            const appoid = document.getElementById('assign_appoid').value;
            const procId = document.getElementById('assign_procedure_id').value;
            const selectedText = document.getElementById('assign_procedure_id').options[document.getElementById('assign_procedure_id').selectedIndex]?.text || '';
            let price = document.getElementById('procedure_price').value.trim();
            if(!procId){showAssignMessage('Please select a procedure.');return;}
            // If consultation, price is fixed on server.
            if(selectedText.toLowerCase()!=='consultation'){
                if(price===''){showAssignMessage('Please enter a price for the procedure.');return;}
                if(isNaN(price) || Number(price)<0){showAssignMessage('Invalid price value.');return;}
            } else {
                price=''; // handled on server
            }
            try {
                const body = `appoid=${encodeURIComponent(appoid)}&procedure_id=${encodeURIComponent(procId)}&agreed_price=${encodeURIComponent(price)}`;
                const res = await fetch('add_procedure_to_appointment_ajax.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
                const data = await res.json();
                if(data.success){
                    document.getElementById('assign_procedure_id').value='';
                    document.getElementById('procedure_price').value='';
                    togglePriceInput();
                    loadAssignedProcedures(appoid);
                } else {
                    showAssignMessage(data.error||'Failed to add procedure.');
                }
            } catch(err){
                showAssignMessage('Network error adding procedure.');
            }
        }
        async function deleteProcedure(id){
            const appoid = document.getElementById('assign_appoid').value;
            if(!confirm('Remove this procedure?')) return;
            try {
                const body = `id=${encodeURIComponent(id)}&appoid=${encodeURIComponent(appoid)}`;
                const res = await fetch('delete_appointment_procedure_ajax.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
                const data = await res.json();
                if(data.success){
                    loadAssignedProcedures(appoid);
                } else {
                    showAssignMessage(data.error||'Delete failed.');
                }
            } catch(err){
                showAssignMessage('Network error deleting procedure.');
            }
        }
        function showAssignMessage(msg){
            const box = document.getElementById('assignProcedureMessage');
            box.textContent=msg; box.style.display='block';
        }
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Delegated pointerdown to save scroll position for any action/link/button
                try {
                    document.addEventListener('pointerdown', function (ev) {
                        try {
                            const target = ev.target && ev.target.closest ? ev.target.closest('a, button, input, [data-action], [data-href]') : null;
                            if (!target) return;

                            const href = (target.getAttribute && (target.getAttribute('href') || target.getAttribute('data-href') || target.getAttribute('formaction'))) || '';
                            const onclick = (target.getAttribute && target.getAttribute('onclick')) || '';
                            const dataAction = (target.dataset && target.dataset.action) ? target.dataset.action : '';

                            const form = target.closest ? target.closest('form') : null;
                            const tag = (target.tagName || '').toLowerCase();
                            const isSubmitButton = (tag === 'button' && (String(target.type || '').toLowerCase() === 'submit')) || (tag === 'input' && String(target.type || '').toLowerCase() === 'submit');

                            const keywordMatch = /add_|add-|save|create|submit|delete|drop|edit|remove|action=/i.test(href + onclick + dataAction);

                            if (form || isSubmitButton || href.indexOf('action=') !== -1 || onclick.indexOf('action=') !== -1 || dataAction || keywordMatch) {
                                try { sessionStorage.setItem('settings_last_scroll', String(window.scrollY || document.documentElement.scrollTop || 0)); } catch (e) { }
                            }
                        } catch (e) { }
                    }, true);
                } catch (e) { }

                // Show popup if URL has any action parameter
                const urlParams = new URLSearchParams(window.location.search);
                const action = urlParams.get('action');

                if (action === 'view' || action === 'edit' || action === 'drop' || action === 'add') {
                    const popup = document.getElementById('popup1');
                    if (popup) {
                        popup.style.display = 'flex';
                        document.body.style.overflow = 'hidden';

                        // Restore previous scroll position if we saved one before navigation.
                        try {
                            const last = sessionStorage.getItem('settings_last_scroll');
                            if (last !== null) {
                                setTimeout(function () {
                                    window.scrollTo(0, parseInt(last, 10) || 0);
                                    sessionStorage.removeItem('settings_last_scroll');
                                }, 1);
                            }
                        } catch (e) { }
                    }
                }

                window.submitAddDiscount = async function(){
                    const appoid = document.getElementById('assign_appoid').value;
                    const dsel = document.getElementById('assign_discount_id');
                    if(!dsel || !dsel.value){ showAssignMessage('Please select a discount.'); return; }
                    const procId = dsel.value; // e.g. discount_pwd
                    try{
                        const body = `appoid=${encodeURIComponent(appoid)}&procedure_id=${encodeURIComponent(procId)}&agreed_price=`;
                        const res = await fetch('add_procedure_to_appointment_ajax.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
                        const data = await res.json();
                        if(data.success){
                            dsel.value=''; toggleDiscountNote(); loadAssignedProcedures(appoid);
                        } else {
                            showAssignMessage(data.error||'Failed to apply discount.');
                        }
                    } catch(e){ showAssignMessage('Network error applying discount.'); }
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
