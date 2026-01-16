<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/loading.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/responsive-admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <title>Dashboard - IHeartDentistDC</title>
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">
    <script>
        // Prevent going back to this page after logout
        function preventBackAfterLogout() {
            window.history.forward();
        }
        
        // Execute when page loads
        window.onload = function() {
            preventBackAfterLogout();
        }
        
        // Execute when back/forward buttons are pressed
        window.onpageshow = function(event) {
            if (event.persisted) {
                // Page was loaded from cache (back button)
                window.location.reload();
            }
        };
    </script>
</head>
<style>
/* Optional: Style the disabled button to look different */
.post-btn:disabled {
    background-color: #cccccc;
    cursor: not-allowed;
    opacity: 0.7;
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
.poster-photo {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    background-color: white;
    border: solid 3px #84b6e4;
}
.clinic-logo {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
    background-color: white;
    border: solid 3px rgb(142, 193, 255);
}
</style>
<?php
date_default_timezone_set('Asia/Singapore');
session_start();

// Add these headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'a') {
        header("location: login.php");
    } else {
        $useremail = $_SESSION["user"];
    }
} else {
    header("location: login.php");
}


//import database
include("../connection.php");
$userrow = $database->query("select * from admin where aemail='$useremail'");
$userfetch = $userrow->fetch_assoc();
$username = $userfetch["aemail"];


// Branch restriction (e.g., Bacoor-only admin)
$restrictedBranchId = isset($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id'] ? (int)$_SESSION['restricted_branch_id'] : 0;

// Get counts for dashboard, respecting branch restriction
if ($restrictedBranchId > 0) {
    $dentistrow = $database->query("SELECT COUNT(*) AS c FROM doctor WHERE status='active' AND (branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))");
    $patientrow = $database->query("SELECT COUNT(*) AS c FROM patient WHERE status='active' AND branch_id = $restrictedBranchId");
    $appointmentrow = $database->query("SELECT COUNT(*) AS c FROM appointment WHERE status='booking' AND docid IN (SELECT docid FROM doctor WHERE branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))");
    $schedulerow = $database->query("SELECT COUNT(*) AS c FROM appointment WHERE status='appointment' AND docid IN (SELECT docid FROM doctor WHERE branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))");
} else {
    $dentistrow = $database->query("SELECT COUNT(*) AS c FROM doctor WHERE status='active'");
    $patientrow = $database->query("SELECT COUNT(*) AS c FROM patient WHERE status='active'");
    $appointmentrow = $database->query("SELECT COUNT(*) AS c FROM appointment WHERE status='booking'");
    $schedulerow = $database->query("SELECT COUNT(*) AS c FROM appointment WHERE status='appointment'");
}


$today = date('Y-m-d');
$currentMonth = date('F');
$currentYear = date('Y');
$daysInMonth = date('t');
$firstDayOfMonth = date('N', strtotime("$currentYear-" . date('m') . "-01"));
$currentDay = date('j');


// sort order for announcements
$sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'oldest' ? 'ASC' : 'DESC';
?>


<body>
    <!-- Mobile hamburger for sidebar toggle -->
    <button class="hamburger-admin" id="hamburgerAdmin" aria-label="Toggle sidebar" aria-controls="adminSidebar" aria-expanded="false">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- sidebar toggle removed to keep sidebar static -->
    <div class="main-container">
        <!-- sidebar -->
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
                                    // Special label for branch-restricted admin accounts
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
                <a href="dashboard.php" class="nav-item active">
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
                <!-- Superadmin link removed -->
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
            <!-- main content -->
            <div class="content">
                <!-- Legacy sidebar-toggle removed; logo now acts as toggle -->
                <div class="main-section">
                    <!-- search bar -->
                    <div class="search-container">
                        <form id="announcementSearchForm" style="display: flex; width: 100%;">
                            <input type="search" name="search" id="announcementSearch" class="search-input"
                                placeholder="Search announcements"
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <button type="button" class="clear-btn">×</button>
                        </form>
                    </div>
                    <!-- announcements header -->
                    <div class="announcements-header">
                        <h3 class="announcements-title">Announcements</h3>
                        <div class="announcement-filters">
                            <?php
                            $currentSort = isset($_GET['sort']) && $_GET['sort'] === 'oldest' ? 'oldest' : 'newest';
                            ?>
                            <a href="?sort=newest"
                                class="filter-btn newest-btn <?php echo $currentSort === 'newest' ? 'active' : 'inactive'; ?>">
                                Newest
                            </a>
                            <a href="?sort=oldest"
                                class="filter-btn oldest-btn <?php echo $currentSort === 'oldest' ? 'active' : 'inactive'; ?>">
                                Oldest
                            </a>
                        </div>
                    </div>


                    <!-- Post Announcement Button -->
                    <div class="announcement-post-button">
                        <div class="user-avatar">
                            <img src="../Media/Icon/logo.png" alt="Profile" class="poster-photo">
                        </div>
                        <button id="postAnnouncementBtn" class="post-announcement-input">
                            Post announcements...
                        </button>
                    </div>


                    <!-- Hidden Post Announcement Form Modal -->
                    <div id="announcementModal" class="announcement-modal">
                        <div class="modal-content">
                            <span class="close-modal">&times;</span>
                            <div class="modal-user">
                                <div class="modal-user-avatar">
                                    <img src="../Media/Icon/logo.png" alt="Profile" class="poster-photo">
                                </div>
                                <div class="modal-user-name">
                                    I Heart Dentist Dental Clinic
                                </div>
                            </div>
                            <form action="create_post.php" method="POST">
                                <div class="form-group">
                                    <label for="post_title">Title</label>
                                    <input type="text" id="post_title" name="post_title" required>
                                </div>
                                <div class="form-group">
                                    <label for="post_content">Content</label>
                                    <textarea id="post_content" name="post_content" rows="5" required></textarea>
                                </div>
                                <button type="submit" class="post-btn" id="submitPostBtn" disabled>POST</button>
                            </form>
                        </div>
                    </div>
                   
                    <!-- announcements container -->
                    <div class="announcements">
                        <div class="announcements-content" id="announcementsContent">
                            <?php
                            // Get search term if submitted
                            $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
                            $sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'oldest' ? 'ASC' : 'DESC';


                            // Determine primary keys for post tables and build a normalized query (pk, docid, title, content, created_at, docname, photo)
                            function get_table_pk($db, $table) {
                                $pk = null;
                                $res = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $db->real_escape_string($table) . "' AND COLUMN_KEY = 'PRI' LIMIT 1");
                                if ($res && $res->num_rows > 0) {
                                    $r = $res->fetch_assoc();
                                    $pk = $r['COLUMN_NAME'];
                                }
                                if (!$pk) {
                                    $candidates = ['id', 'post_id', 'postid', 'pid'];
                                    foreach ($candidates as $c) {
                                        $r = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $db->real_escape_string($table) . "' AND COLUMN_NAME = '" . $db->real_escape_string($c) . "' LIMIT 1");
                                        if ($r && $r->num_rows > 0) {
                                            $pk = $c;
                                            break;
                                        }
                                    }
                                }
                                return $pk;
                            }

                            $pd_pk = get_table_pk($database, 'post_dentist');
                            $pa_pk = get_table_pk($database, 'post_admin');
                            if (!$pd_pk) $pd_pk = 'id';
                            if (!$pa_pk) $pa_pk = 'id';

                            // Base query with doctor photo included, alias primary keys to `pk` and docid to identify dentist posts
                            $query = "
                                SELECT post_dentist.`" . $pd_pk . "` AS pk, post_dentist.docid AS docid, post_dentist.title, post_dentist.content, post_dentist.created_at, doctor.docname, doctor.photo, NULL AS aemail
                                FROM post_dentist
                                LEFT JOIN doctor ON post_dentist.docid = doctor.docid";

                            // Restrict dentist posts to branch if needed
                            if ($restrictedBranchId > 0) {
                                $query .= " WHERE (doctor.branch_id = $restrictedBranchId OR doctor.docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))";
                            }

                            // Determine which admin posts should be visible to this viewer
                            $postAdminWhere = '';
                            $currentAdminEmail = isset($_SESSION['user']) ? strtolower($_SESSION['user']) : '';
                            // If viewing as a restricted-branch admin, only show central admin posts and posts from the same branch admin
                            if (!empty($restrictedBranchId)) {
                                $allowed = ['admin@edoc.com'];
                                if ($currentAdminEmail === 'adminbacoor@edoc.com') $allowed[] = 'adminbacoor@edoc.com';
                                if ($currentAdminEmail === 'adminmakati@edoc.com') $allowed[] = 'adminmakati@edoc.com';
                                // Build WHERE to include only allowed admin emails
                                $escaped = array_map(function($e) use ($database){ return $database->real_escape_string($e); }, $allowed);
                                $postAdminWhere = " WHERE aemail IN ('" . implode("','", $escaped) . "')";
                            } else {
                                // If not restricted and current admin is not superadmin, hide branch-only admin posts
                                if (strcasecmp($currentAdminEmail, 'admin@edoc.com') !== 0) {
                                    $postAdminWhere = " WHERE aemail NOT IN ('adminbacoor@edoc.com','adminmakati@edoc.com')";
                                }
                            }
                            $query .= "
                                UNION ALL
                                SELECT post_admin.`" . $pa_pk . "` AS pk, NULL AS docid, post_admin.title, post_admin.content, post_admin.created_at, NULL AS docname, NULL AS photo, post_admin.aemail AS aemail
                                FROM post_admin" . $postAdminWhere . "
                            ";


                            // Add search condition if term exists
                            if (!empty($searchTerm)) {
                                $searchTerm = $database->real_escape_string($searchTerm);
                                $query = "
                                    SELECT * FROM (
                                        $query
                                    ) AS combined_posts
                                    WHERE title LIKE '%$searchTerm%' OR content LIKE '%$searchTerm%'
                                ";
                            }


                            // Add sorting and limit
                            $query .= " ORDER BY created_at $sortOrder LIMIT 6";


                            $result = $database->query($query);


                            if ($result->num_rows > 0) {
                                // Loop through the posts and display them
                                while ($post = $result->fetch_assoc()) {
                                    // Determine photo path
                                    $photoPath = $post['docname'] ? "../admin/uploads/" . $post['photo'] : "../Media/Icon/logo.png";
                                    // If this is an admin post, check author email to present branch-specific label
                                    if (empty($post['docname'])) {
                                        $authorEmail = isset($post['aemail']) ? $post['aemail'] : '';
                                        $branchAdminLabels = [
                                            'adminbacoor@edoc.com' => 'Bacoor',
                                            'adminmakati@edoc.com' => 'Makati'
                                        ];
                                        if (isset($branchAdminLabels[$authorEmail])) {
                                            $posterName = 'I Heart Dentist Dental Clinic - ' . $branchAdminLabels[$authorEmail];
                                        } else {
                                            $posterName = 'I Heart Dentist Dental Clinic';
                                        }
                                    } else {
                                        $posterName = $post['docname'];
                                    }
                                   
                                    $content = htmlspecialchars($post['content']);
                                    $isLong = strlen($content) > 400;
                                    $shortContent = $isLong ? substr($content, 0, 255) . '...' : $content;


                                    echo '<div class="announcement-item">';
                                    echo '<div class="announcement-header">';
                                    echo '<div class="clinic-logo"><img src="' . $photoPath . '" alt="Profile" class="poster-photo"></div>';
                                    echo '<div class="clinic-info">';
                                    echo '<h4 class="clinic-name">' . htmlspecialchars($post['title']) . '</h4>';
                                    echo '<p class="clinic-date">Posted by: ' . htmlspecialchars($posterName) . ' on ' . date('M d, Y', strtotime($post['created_at'])) . '</p>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '<div class="announcement-content">' . nl2br($shortContent) . '</div>';
                                    echo '<div class="full-content">' . nl2br($content) . '</div>';


                                    if ($isLong) {
                                        echo '<div class="announcement-footer">';
                                        echo '<button class="see-more-btn" onclick="toggleExpand(this)">See more...</button>';
                                        echo '</div>';
                                    }

                                    // Add delete button/form for admins (can delete admin or dentist posts)
                                    $postType = isset($post['docid']) ? 'dentist' : 'admin';
                                    $postId = $post['pk'] ?? null;
                                    if ($postId) {
                                        // By default allow deletion
                                        $canDelete = true;
                                        // If this is an admin post authored by admin@edoc.com,
                                        // do not allow adminbacoor to delete it
                                            if ($postType === 'admin') {
                                            $authorEmail = isset($post['aemail']) ? $post['aemail'] : '';
                                            // Prevent branch-restricted admins from deleting central admin's posts
                                            if (isset($_SESSION['user']) && in_array($_SESSION['user'], ['adminbacoor@edoc.com','adminmakati@edoc.com']) && strcasecmp($authorEmail, 'admin@edoc.com') === 0) {
                                                $canDelete = false;
                                            }
                                        }
                                        if ($canDelete) {
                                            echo '<div class="announcement-actions">';
                                            echo '<form method="POST" action="delete_post.php" onsubmit="return confirm(\'Are you sure you want to delete this announcement?\');">';
                                            echo '<input type="hidden" name="post_id" value="' . intval($postId) . '">';
                                            echo '<input type="hidden" name="post_type" value="' . htmlspecialchars($postType) . '">';
                                            echo '<button type="submit" class="delete-post-btn">Delete</button>';
                                            echo '</form>';
                                            echo '</div>';
                                        }
                                    }


                                    echo '</div>';
                                }
                            } else {
                                echo '<p>No announcements found' . (!empty($searchTerm) ? ' matching "' . htmlspecialchars($searchTerm) . '"' : '') . '.</p>';
                            }
                            ?>
                        </div>
                    </div>


                </div>


                <div class="right-sidebar">
                    <div class="stats-section">
                        <div class="stats-container">
                            <!-- First row -->
                            <a href="dentist.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $dentistrow->fetch_row()[0] ?? 0; ?></h1>
                                        <p class="stat-label">Dentists</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/dentist.png" alt="Dentists Icon">
                                    </div>
                                </div>
                            </a>


                            <!-- Second row -->
                            <a href="patient.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $patientrow->fetch_row()[0] ?? 0; ?></h1>
                                        <p class="stat-label">Patients</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/care.png" alt="Patients Icon">
                                    </div>
                                </div>
                            </a>


                            <!-- Third row -->
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


                            <!-- Fourth row -->
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
                        <div class="calendar-container">
                            <div class="calendar-header">
                                <h3 class="calendar-month">
                                    <?php echo strtoupper(date('F', strtotime('this month'))); ?>
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
                            if ($restrictedBranchId > 0) {
                                $branchScope = " AND (doctor.branch_id = $restrictedBranchId OR doctor.docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))";
                            }
                            $upcomingAppointments = $database->query("
                                SELECT
                                    appointment.appoid,
                                    patient.pname AS patient_name,
                                    doctor.docname AS dentist_name,
                                    appointment.appodate,
                                    appointment.appointment_time,
                                    procedures.procedure_name,
                                    COALESCE(b.name, '') AS branch_name
                                FROM appointment
                                LEFT JOIN patient ON appointment.pid = patient.pid
                                LEFT JOIN doctor ON appointment.docid = doctor.docid
                                LEFT JOIN procedures ON appointment.procedure_id = procedures.procedure_id
                                LEFT JOIN branches b ON doctor.branch_id = b.id
                                WHERE
                                    appointment.status = 'appointment'
                                    AND appointment.appodate >= '$today'
                                    $branchScope
                                ORDER BY appointment.appodate ASC, appointment.appointment_time ASC
                            ");


                            if ($upcomingAppointments && $upcomingAppointments->num_rows > 0) {
                                while ($appointment = $upcomingAppointments->fetch_assoc()) {
                                    $pname = htmlspecialchars($appointment['patient_name'] ?? '');
                                    $dname = htmlspecialchars($appointment['dentist_name'] ?? '');
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

                                    $html = '<div class="appointment-item">';
                                    $html .= '<h4 class="appointment-type">' . $pname . '</h4>';
                                    $html .= '<p class="appointment-dentist">With Dr. ' . $dname . '</p>';
                                    $html .= '<p class="appointment-date">' . $proc . '</p>';
                                    $suffix = ($branch !== '') ? (' - ' . $branch) : '';
                                    $html .= '<p class="appointment-date">' . $date_str . ($date_str && $time_str ? ' • ' : '') . $time_str . $suffix . '</p>';
                                    $html .= '</div>';
                                    echo $html;
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


    <script>
        // Script for clear button in search
        document.querySelector('.clear-btn').addEventListener('click', function () {
            document.querySelector('input[name="search"]').value = '';
        });
    </script>

    <script>
        // Mobile sidebar toggle
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

                // Close on ESC
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') closeSidebar();
                });
            }
        });
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('announcementSearch');
            const searchForm = document.getElementById('announcementSearchForm');
            const clearBtn = document.querySelector('.clear-btn');


            // Clear search functionality
            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                searchForm.submit();
            });
        });
    </script>


    <script>
        // Script for announcement modal and post validation
        document.addEventListener('DOMContentLoaded', function () {
            // Modal elements
            const modal = document.getElementById('announcementModal');
            const btn = document.getElementById('postAnnouncementBtn');
            const closeBtn = document.querySelector('.close-modal');


            // Form validation elements
            const titleInput = document.getElementById('post_title');
            const contentInput = document.getElementById('post_content');
            const submitBtn = document.getElementById('submitPostBtn');


            // Open modal when button is clicked
            btn.addEventListener('click', function () {
                modal.style.display = 'block';
                // Clear form and disable button when opening modal
                titleInput.value = '';
                contentInput.value = '';
                submitBtn.disabled = true;
            });


            // Close modal when X is clicked
            closeBtn.addEventListener('click', function () {
                modal.style.display = 'none';
            });


            // Close modal when clicking outside the modal content
            window.addEventListener('click', function (event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });


            // Function to validate form and enable/disable submit button
            function validateForm() {
                const titleValid = titleInput.value.trim() !== '';
                const contentValid = contentInput.value.trim() !== '';
                submitBtn.disabled = !(titleValid && contentValid);
            }


            // Add input event listeners for validation
            if (titleInput && contentInput && submitBtn) {
                titleInput.addEventListener('input', validateForm);
                contentInput.addEventListener('input', validateForm);
            }
        });
    </script>
    <script>
        function toggleExpand(button) {
            const announcementItem = button.closest('.announcement-item');
            const content = announcementItem.querySelector('.announcement-content');
            const fullContent = announcementItem.querySelector('.full-content');


            if (content.style.display === 'none') {
                // Collapse
                content.style.display = 'block';
                fullContent.style.display = 'none';
                button.textContent = 'See more...';
            } else {
                // Expand
                content.style.display = 'none';
                fullContent.style.display = 'block';
                button.textContent = 'See less';
            }
        }
    </script>
<!-- Sidebar toggle logic moved to partial -->
</body>


</html>

