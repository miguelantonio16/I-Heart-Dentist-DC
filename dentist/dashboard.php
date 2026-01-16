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
        // Prevent going back to dashboard after logout
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
    .post-btn:disabled {
        background-color: #cccccc;
        cursor: not-allowed;
        opacity: 0.7;
    }
    .poster-photo {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: solid 3px rgb(142, 193, 255);
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

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'd') {
        header("location: login.php");
    } else {
        $useremail = $_SESSION["user"];
    }
} else {
    header("location: login.php");
}


//import database
include("../connection.php");
$userrow = $database->query("select * from doctor where docemail='$useremail'");
$userfetch = $userrow->fetch_assoc();
$userid = $userfetch["docid"];
$username = $userfetch["docname"];


// Get counts for dashboard
$patientrow = $database->query("SELECT COUNT(DISTINCT pid) FROM appointment WHERE docid='$userid'");
$appointmentrow = $database->query("SELECT COUNT(*) FROM appointment WHERE status='booking' AND docid='$userid'");
$schedulerow = $database->query("SELECT COUNT(*) FROM appointment WHERE status='appointment' AND docid='$userid'");


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
    <button class="hamburger-admin show-mobile" id="sidebarToggle" aria-label="Toggle navigation" aria-controls="adminSidebar" aria-expanded="false">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="main-container">
        <!-- sidebar -->
        <div class="sidebar" id="adminSidebar">
            <div class="sidebar-logo">
                <img src="../Media/Icon/logo.png" alt="IHeartDentistDC Logo">
            </div>


            <div class="user-profile">
                <div class="profile-image">
                    <?php
                    $userphoto = $userfetch["photo"];
                    $profile_pic = (!empty($userphoto) && file_exists("../admin/uploads/" . $userphoto)) ? "../admin/uploads/" . $userphoto : '../Media/Icon/Blue/profile.png';
                    ?>
                    <img src="<?php echo $profile_pic; ?>" alt="Profile" class="profile-img">
                </div>
                <h3 class="profile-name"><?php echo substr($username, 0, 25) ?></h3>
                <p style="color: #777; margin: 0; font-size: 14px; text-align: center;">
                    <?php echo substr($useremail, 0, 30) ?>
                </p>
            </div>


            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item active">
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
            <!-- main content -->
            <div class="content">
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
                            <img src="<?php echo $profile_pic; ?>" alt="Profile" class="poster-photo">
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
                                    <img src="<?php echo $profile_pic; ?>" alt="Profile" class="poster-photo">
                                </div>
                                <div class="modal-user-name">
                                    <?php echo $username; ?>
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

                            // Determine dentist's branches (support multi-branch dentists)
                            $dentistBranchIds = [];
                            if (isset($userfetch['branch_id']) && $userfetch['branch_id']) {
                                $dentistBranchIds[] = (int)$userfetch['branch_id'];
                            }
                            // Also check doctor_branches table for any additional branch assignments
                            $stmtDb = $database->prepare("SELECT branch_id FROM doctor_branches WHERE docid = ?");
                            if ($stmtDb) {
                                $stmtDb->bind_param('i', $userid);
                                $stmtDb->execute();
                                $resDb = $stmtDb->get_result();
                                if ($resDb) {
                                    while ($r = $resDb->fetch_assoc()) {
                                        $bid = (int)$r['branch_id'];
                                        if ($bid && !in_array($bid, $dentistBranchIds, true)) {
                                            $dentistBranchIds[] = $bid;
                                        }
                                    }
                                }
                                $stmtDb->close();
                            }

                            // Build allowed admin emails: always include global admin@edoc.com
                            $allowedAdminEmails = ['admin@edoc.com'];
                            // Map known branch admins to branch names
                            $branchAdminMap = [
                                'adminbacoor@edoc.com' => 'Bacoor',
                                'adminmakati@edoc.com' => 'Makati'
                            ];
                            foreach ($branchAdminMap as $adminEmail => $branchName) {
                                // Find branch id for this branch name
                                $branchId = null;
                                $stmtBr = $database->prepare("SELECT id FROM branches WHERE name = ? LIMIT 1");
                                if ($stmtBr) {
                                    $stmtBr->bind_param('s', $branchName);
                                    $stmtBr->execute();
                                    $resBr = $stmtBr->get_result();
                                    if ($resBr && $resBr->num_rows === 1) {
                                        $branchId = (int)$resBr->fetch_assoc()['id'];
                                    } else {
                                        $like = '%' . $branchName . '%';
                                        $stmtBr2 = $database->prepare("SELECT id FROM branches WHERE name LIKE ? LIMIT 1");
                                        if ($stmtBr2) {
                                            $stmtBr2->bind_param('s', $like);
                                            $stmtBr2->execute();
                                            $resBr2 = $stmtBr2->get_result();
                                            if ($resBr2 && $resBr2->num_rows === 1) {
                                                $branchId = (int)$resBr2->fetch_assoc()['id'];
                                            }
                                            $stmtBr2->close();
                                        }
                                    }
                                    $stmtBr->close();
                                }
                                // If this admin corresponds to any of the dentist's branches, include the admin email
                                if ($branchId && in_array($branchId, $dentistBranchIds, true)) {
                                    $allowedAdminEmails[] = $adminEmail;
                                }
                            }

                            // Build admin filter SQL (safe-escaped list)
                            $escapedAdmins = array_map(function($e) use ($database) { return $database->real_escape_string($e); }, $allowedAdminEmails);
                            $adminListSql = "('" . implode("','", $escapedAdmins) . "')";

                            // Restrict dentist posts to only those authored by the current dentist
                            $dentistWhere = "WHERE post_dentist.docid = " . (int)$userid;

                            $query = "
                                SELECT post_dentist.`" . $pd_pk . "` AS pk, post_dentist.docid AS docid, post_dentist.title, post_dentist.content, post_dentist.created_at, doctor.docname, doctor.photo, NULL AS aemail
                                FROM post_dentist
                                LEFT JOIN doctor ON post_dentist.docid = doctor.docid
                                " . $dentistWhere . "
                                UNION ALL
                                SELECT post_admin.`" . $pa_pk . "` AS pk, NULL AS docid, post_admin.title, post_admin.content, post_admin.created_at, NULL AS docname, NULL AS photo, post_admin.aemail AS aemail
                                FROM post_admin
                                WHERE post_admin.aemail IN " . $adminListSql . "
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
                                    // Determine robust photo path for poster avatar
                                    // If this is an admin post, map adminbacoor to Bacoor label
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
                                    $photoRaw = isset($post['photo']) ? trim($post['photo']) : '';
                                    if (!empty($post['docname'])) {
                                        if ($photoRaw !== '' && file_exists("../admin/uploads/" . $photoRaw)) {
                                            $photoPath = "../admin/uploads/" . $photoRaw;
                                        } else {
                                            $photoPath = "../Media/Icon/Blue/dentist.png";
                                        }
                                    } else {
                                        // Admin/clinic post uses clinic logo
                                        $photoPath = "../Media/Icon/logo.png";
                                    }
                                   
                                    $content = htmlspecialchars($post['content']);
                                    $isLong = strlen($content) > 400;
                                    $shortContent = $isLong ? substr($content, 0, 255) . '...' : $content;


                                    echo '<div class="announcement-item">';
                                    echo '<div class="announcement-header">';
                                    echo '<div class="clinic-logo"><img src="' . $photoPath . '" alt="Profile" class="poster-photo" onerror="this.onerror=null;this.src=\'../Media/Icon/Blue/profile.png\';"></div>';
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

                                        // If this is a dentist post authored by this dentist, show delete button
                                        if (isset($post['docid']) && isset($_SESSION['userid']) && intval($post['docid']) === intval($_SESSION['userid'])) {
                                            $pid = intval($post['pk'] ?? 0);
                                            if ($pid > 0) {
                                                echo '<div class="announcement-actions">';
                                                echo '<form method="POST" action="delete_post.php" onsubmit="return confirm(\'Are you sure you want to delete this announcement?\');">';
                                                echo '<input type="hidden" name="post_id" value="' . $pid . '">';
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


    <script>
        // Script for clear button in search
        document.querySelector('.clear-btn').addEventListener('click', function () {
            document.querySelector('input[name="search"]').value = '';
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

