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
    <title>Dentist - IHeartDentistDC</title>
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">

    <?php
    date_default_timezone_set('Asia/Singapore');
    session_start();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

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

    // Determine patient's branch memberships from mapping table
    $user_branches = [];
    $ubres = $database->query("SELECT pb.branch_id, b.name FROM patient_branches pb JOIN branches b ON pb.branch_id = b.id WHERE pb.pid='" . $database->real_escape_string($userid) . "'");
    while ($b = $ubres->fetch_assoc()) {
        $user_branches[$b['branch_id']] = $b['name'];
    }

    $user_branch_id = null;
    $user_branch_name = null;
    $require_branch = false;

    if (count($user_branches) === 0) {
        // no branch assignments
        $require_branch = true;
    } elseif (count($user_branches) === 1) {
        // single branch - choose it
        $keys = array_keys($user_branches);
        $user_branch_id = $keys[0];
        $user_branch_name = $user_branches[$user_branch_id];
        $_SESSION['active_branch_id'] = $user_branch_id;
        $_SESSION['active_branch_name'] = $user_branch_name;
    } else {
        // multiple branches - respect session active branch if valid, otherwise default to first
        if (!empty($_SESSION['active_branch_id']) && array_key_exists($_SESSION['active_branch_id'], $user_branches)) {
            $user_branch_id = $_SESSION['active_branch_id'];
            $user_branch_name = $_SESSION['active_branch_name'] ?? $user_branches[$user_branch_id];
        } else {
            $keys = array_keys($user_branches);
            $user_branch_id = $keys[0];
            $user_branch_name = $user_branches[$user_branch_id];
            $_SESSION['active_branch_id'] = $user_branch_id;
            $_SESSION['active_branch_name'] = $user_branch_name;
        }
    }

    // Get notification count
    $unreadCount = $database->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = '$userid' AND user_type = 'p' AND is_read = 0");
    $unreadCount = $unreadCount->fetch_assoc()['count'];

    // Get notifications
    $notifications = $database->query("SELECT * FROM notifications WHERE user_id = '$userid' AND user_type = 'p' ORDER BY created_at DESC");


    // Get totals for right sidebar
    $doctorrow = $database->query("select * from doctor where status='active';");
    $appointmentrow = $database->query("select * from appointment where status='booking' AND pid='$userid';");
    $schedulerow = $database->query("select * from appointment where status='appointment' AND pid='$userid';");

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

    // This is the key part that needs fixing - check if 'sort' parameter exists and equals 'oldest'
    $sort_param = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    $sort_order = ($sort_param === 'oldest') ? 'DESC' : 'ASC';

    // restrict dentists to the same branch as the logged-in patient
    $branch_condition = '';
    $require_branch = false;
    if (!empty($user_branch_id)) {
        $user_branch_id_esc = $database->real_escape_string($user_branch_id);
        // filter doctors that are assigned to the same branch via mapping
        $branch_condition = "AND doctor.docid IN (SELECT docid FROM doctor_branches WHERE branch_id = '$user_branch_id_esc')";
    } else {
        // If patient has no branch assigned, require branch assignment before showing doctors
        $require_branch = true;
    }

    if (isset($_GET['search'])) {
        $search = $database->real_escape_string($_GET['search']);
        $query = "SELECT doctor.*, b.name AS branch_name FROM doctor LEFT JOIN branches b ON doctor.branch_id = b.id WHERE doctor.status='active' $branch_condition AND (doctor.docname LIKE '%$search%' OR doctor.docemail LIKE '%$search%' OR doctor.doctel LIKE '%$search%') ORDER BY doctor.docname $sort_order LIMIT $start_from, $results_per_page";
        $count_query = "SELECT COUNT(*) as total FROM doctor WHERE status='active' $branch_condition AND (docname LIKE '%$search%' OR docemail LIKE '%$search%' OR doctel LIKE '%$search%')";
    } else {
        $query = "SELECT doctor.*, b.name AS branch_name FROM doctor LEFT JOIN branches b ON doctor.branch_id = b.id WHERE doctor.status='active' $branch_condition ORDER BY doctor.docname $sort_order LIMIT $start_from, $results_per_page";
        $count_query = "SELECT COUNT(*) as total FROM doctor WHERE status='active' $branch_condition";
    }

    $result = $database->query($query);
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

    ?>
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
                <a href="dentist.php" class="nav-item active">
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
                        <h3 class="announcements-title">Available Dentists</h3>
                        <?php if (!empty($user_branch_name) && count($user_branches) <= 1): ?>
                            <div style="margin-top:6px;color:#666;font-size:14px">Showing: <strong><?php echo htmlspecialchars($user_branch_name); ?></strong></div>
                        <?php elseif (count($user_branches) > 1): ?>
                            <div style="margin-top:6px;color:#666;font-size:14px">Showing:
                                <form method="post" action="../set_active_branch.php" style="display:inline-block;margin-left:8px;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <select name="active_branch_id" onchange="this.form.submit()">
                                        <?php foreach ($user_branches as $bid => $bname): ?>
                                            <option value="<?php echo $bid; ?>" <?php echo ($bid == $user_branch_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($bname); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
                                </form>
                            </div>
                        <?php endif; ?>
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

                    <?php if ($require_branch): ?>
                        <div class="no-results">
                            <p>Your account is not assigned to a branch. Please contact the clinic administrator.</p>
                        </div>
                    <?php elseif ($result->num_rows > 0): ?>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Profile</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Branch</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                // Check if photo exists, use the correct path to admin/uploads
                                                if (!empty($row['photo'])) {
                                                    $photo = "../admin/uploads/" . $row['photo'];
                                                } else {
                                                    $photo = "../Media/Icon/Blue/dentist.png";
                                                }
                                                ?>
                                                <img src="<?php echo $photo; ?>" alt="<?php echo $row['docname']; ?>"
                                                    class="profile-img-small">
                                            </td>
                                            <td><div class="cell-text"><?php echo $row['docname']; ?></div></td>
                                            <td><div class="cell-text"><?php echo $row['docemail']; ?></div></td>
                                            <td><div class="cell-text"><?php echo $row['doctel']; ?></div></td>
                                            <td><div class="cell-text"><?php echo isset($row['branch_name']) && $row['branch_name'] !== null ? htmlspecialchars($row['branch_name']) : '-'; ?></div></td>
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
                            <p>No dentist found. Please try a different search term.</p>
                        </div>
                    <?php endif; ?>
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
                            $upcomingAppointments = $database->query("
                                SELECT
                                    appointment.appoid,
                                    procedures.procedure_name,
                                    appointment.appodate,
                                    appointment.appointment_time
                                FROM appointment
                                INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
                                WHERE
                                    appointment.pid = '$userid'
                                    AND appointment.status = 'appointment'
                                    AND appointment.appodate >= '$today'
                                ORDER BY appointment.appodate ASC
                                LIMIT 3;
                            ");

                            if ($upcomingAppointments->num_rows > 0) {
                                while ($appointment = $upcomingAppointments->fetch_assoc()) {
                                    echo '<div class="appointment-item">
                                        <h4 class="appointment-type">' . htmlspecialchars($appointment['procedure_name']) . '</h4>
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
                                    <a href="calendar/calendar.php" class="schedule-btn">Schedule an appointment</a>
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
            window.location.href = 'dentist.php';
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
            
            // Count remaining unread notifications
            const unreadCount = document.querySelectorAll('.notification-item.unread').length;
            updateNotificationCount(unreadCount);
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
            
            // Update count to zero
            updateNotificationCount(0);
        }
    });
}
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Handle click on sort buttons
            const sortButtons = document.querySelectorAll('.filter-btn');
            sortButtons.forEach(button => {
                button.addEventListener('click', function (e) {
                    // Only use preventDefault if these are <a> tags
                    if (this.tagName.toLowerCase() === 'a') {
                        e.preventDefault();
                    }

                    let sortType = '';
                    if (this.classList.contains('newest-btn')) {
                        sortType = 'newest';
                    } else if (this.classList.contains('oldest-btn')) {
                        sortType = 'oldest';
                    }

                    // Create URL with parameters
                    let url = new URL(window.location.href);
                    url.searchParams.set('sort', sortType);

                    // Keep search parameter if it exists
                    const searchInput = document.getElementById('searchInput');
                    if (searchInput && searchInput.value) {
                        url.searchParams.set('search', searchInput.value);
                    }

                    // Navigate to new URL
                    window.location.href = url.toString();
                });
            });

            // Debug - check current URL parameters
            console.log('Current URL parameters:', window.location.search);
        });
    </script>
</body>

</html>
