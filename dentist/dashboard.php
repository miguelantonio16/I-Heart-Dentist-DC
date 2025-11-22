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
    <div class="main-container">
        <!-- sidebar -->
        <div class="sidebar">
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

                            // Base query with doctor photo included, alias primary keys to `pk` and docid to identify dentist posts
                            $query = "
                                SELECT post_dentist.`" . $pd_pk . "` AS pk, post_dentist.docid AS docid, post_dentist.title, post_dentist.content, post_dentist.created_at, doctor.docname, doctor.photo
                                FROM post_dentist
                                LEFT JOIN doctor ON post_dentist.docid = doctor.docid
                                UNION ALL
                                SELECT post_admin.`" . $pa_pk . "` AS pk, NULL AS docid, post_admin.title, post_admin.content, post_admin.created_at, NULL AS docname, NULL AS photo
                                FROM post_admin
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
                                    $photoPath = $post['docname'] ? "../admin/uploads/" . $post['photo'] : "../Media/Icon/SDMC Logo.png";
                                    $posterName = $post['docname'] ? $post['docname'] : 'I Heart Dentist Dental Clinic';
                                   
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
                                    appointment.appoid,
                                    patient.pname AS patient_name,
                                    appointment.appodate,
                                    appointment.appointment_time,
                                    procedures.procedure_name
                                FROM appointment
                                INNER JOIN patient ON appointment.pid = patient.pid
                                INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
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
</body>


</html>

