<?php
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

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["change_password"])) {
    $current_password = $_POST["current_password"];
    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];
    
    // Verify current password
    $sql = "SELECT * FROM doctor WHERE docemail='$useremail'";
    $result = $database->query($sql);
    $user = $result->fetch_assoc();
    
    if (password_verify($current_password, $user["docpassword"])) {
        if ($new_password === $confirm_password) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE doctor SET docpassword='$hashed_password' WHERE docemail='$useremail'";
            $database->query($update_sql);
            
            $_SESSION["password_change_success"] = "Password changed successfully!";
            header("Location: settings.php");
            exit();
        } else {
            $_SESSION["password_change_error"] = "New passwords do not match!";
        }
    } else {
        $_SESSION["password_change_error"] = "Current password is incorrect!";
    }
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_profile"])) {
    $userid = $_POST["user_id"];
    $name = $_POST["name"];
    $email = $_POST["email"];
    $tele = $_POST["Tele"];
    
    // Update query
    $update_query = "UPDATE doctor SET docname=?, docemail=?, doctel=? WHERE docid=?";
    $stmt = $database->prepare($update_query);
    $stmt->bind_param("sssi", $name, $email, $tele, $userid);
    $stmt->execute();
    
    // Update session email if changed
    if ($email != $useremail) {
        $_SESSION["user"] = $email;
    }
    
    $_SESSION["profile_update_success"] = "Profile updated successfully!";
    header("Location: settings.php");
    exit();
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
    <link rel="stylesheet" href="../css/settings.css">
    <link rel="stylesheet" href="../css/table.css">
    <title>Settings - IHeartDentistDC</title>
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">
    <style>
        .dashbord-tables {
            animation: transitionIn-Y-over 0.5s;
        }

        .filter-container {
            animation: transitionIn-X 0.5s;
        }

        .sub-table {
            animation: transitionIn-Y-bottom 0.5s;
        }

        .image-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            display: block;
        }
        
        /* Popup styles */
        .overlay {
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
        
        .popup {
            max-width: 600px;
            width: 90%;
            border-radius: 12px;
            background: white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            padding: 30px;
            overflow: hidden;
            animation: fadeIn 0.3s;
        }
        
        .popup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        
        .popup-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .close {
            font-size: 24px;
            color: #999;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .close:hover {
            color: #e74c3c;
        }
        
        .popup-content {
            padding: 10px 0;
        }
        
        .popup-form .label-td {
            padding: 8px 0;
        }
        
        .popup-form .form-label {
            font-weight: 500;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }
        
        .popup-form .input-text {
            width: 100%;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-size: 15px;
            transition: all 0.3s;
            margin-bottom: 10px;
        }
        
        .popup-form .input-text:focus {
            outline: none;
            border-color: #2ecc71;
            box-shadow: 0 0 5px rgba(46, 204, 113, 0.3);
        }
        
        .radio-group, .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin: 5px 0 15px;
        }
        
        .radio-option, .checkbox-option {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-row {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-primary {
            background-color: #2ecc71;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #27ae60;
        }
        
        .btn-outline {
            background-color: white;
            color: #555;
            border: 1px solid #ddd;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-outline:hover {
            background-color: #f5f5f5;
        }
        
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-section {
            margin-bottom: 20px;
        }
        
        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .clear-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 20px;
            color: #999;
            cursor: pointer;
            padding: 0 5px;
        }

        .clear-btn:hover {
            color: #555;
        }

        /* Right sidebar adjustments */
        .right-sidebar {
            width: 320px;
        }

        .stats-container {
            display: flex;
            flex-direction: column;
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

        /* File input styling */
        .file-input-wrapper {
            position: relative;
            margin-bottom: 20px;
        }

        .file-input-label {
            display: inline-block;
            background-color: #2ecc71;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .file-input-label:hover {
            background-color: #27ae60;
        }

        .file-input {
            display: none;
        }

        .file-preview {
            margin-top: 10px;
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            display: none;
        }
        
        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .action-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }
        
        .done-btn {
            background-color: #2ecc71;
            color: white;
        }
        
        .done-btn:hover {
            background-color: #27ae60;
        }
        
        .done-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .remove-btn {
            background-color: #e74c3c;
            color: white;
        }
        
        .remove-btn:hover {
            background-color: #c0392b;
        }
        
        .remove-btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        
        .cancel-btn {
            background-color: #f5f5f5;
            color: #555;
            border: 1px solid #ddd;
        }
        
        .cancel-btn:hover {
            background-color: #e0e0e0;
        }
    </style>
</head>

<body>
    <div class="nav-container">
        <div class="sidebar">
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
                <a href="settings.php" class="nav-item active">
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
                        <form action="" method="GET" style="display: flex; width: 100%; position: relative;">
                            <input type="search" name="search" id="searchInput" class="search-input" 
                                placeholder="Search settings"
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <?php if (isset($_GET['search']) && $_GET['search'] != ""): ?>
                                <button type="button" class="clear-btn" onclick="clearSearch()">×</button>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Display success/error messages -->
                    <?php if (isset($_SESSION["profile_update_success"])): ?>
                        <div class="alert alert-success">
                            <?php echo $_SESSION["profile_update_success"]; unset($_SESSION["profile_update_success"]); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION["password_change_success"])): ?>
                        <div class="alert alert-success">
                            <?php echo $_SESSION["password_change_success"]; unset($_SESSION["password_change_success"]); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION["password_change_error"])): ?>
                        <div class="alert alert-error">
                            <?php echo $_SESSION["password_change_error"]; unset($_SESSION["password_change_error"]); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION["profile_pic_success"])): ?>
                        <div class="alert alert-success">
                            <?php echo $_SESSION["profile_pic_success"]; unset($_SESSION["profile_pic_success"]); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION["profile_pic_error"])): ?>
                        <div class="alert alert-error">
                            <?php echo $_SESSION["profile_pic_error"]; unset($_SESSION["profile_pic_error"]); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Settings Cards -->
                    <div id="settings-container">
                        <!-- Personal Details Card -->
                        <a href="?action=edit_profile&id=<?php echo $userid ?>" class="settings-card">
                            <div class="settings-icon">
                                <img src="../Media/Icon/Blue/edit.png" alt="Personal Details">
                            </div>
                            <div class="settings-info">
                                <h3 class="settings-title">Personal Details</h3>
                                <p class="settings-description">Edit your personal details</p>
                            </div>
                            <div class="settings-arrow">
                                <span>›</span>
                            </div>
                        </a>
                        
                        <!-- Change Profile Picture Card -->
                        <a href="?action=change_profile_picture&id=<?php echo $userid ?>" class="settings-card">
                            <div class="settings-icon">
                                <img src="../Media/Icon/Blue/profile.png" alt="Profile Picture">
                            </div>
                            <div class="settings-info">
                                <h3 class="settings-title">Profile Picture</h3>
                                <p class="settings-description">Change or remove your profile picture</p>
                            </div>
                            <div class="settings-arrow">
                                <span>›</span>
                            </div>
                        </a>
                        
                        <!-- Password Card -->
                        <a href="?action=change_password&id=<?php echo $userid ?>" class="settings-card">
                            <div class="settings-icon">
                                <img src="../Media/Icon/Blue/lock.png" alt="Password">
                            </div>
                            <div class="settings-info">
                                <h3 class="settings-title">Password</h3>
                                <p class="settings-description">Change your password</p>
                            </div>
                            <div class="settings-arrow">
                                <span>›</span>
                            </div>
                        </a>
                        
                        <!-- Deactivate Account Card -->
                        <a href="?action=deactivate_account&id=<?php echo $userid ?>" class="settings-card">
                            <div class="settings-icon">
                                <img src="../Media/Icon/Blue/x.png" alt="Deactivate">
                            </div>
                            <div class="settings-info">
                                <h3 class="settings-title danger-text">Deactivate Account</h3>
                                <p class="settings-description">This will deactivate your account</p>
                            </div>
                            <div class="settings-arrow">
                                <span>›</span>
                            </div>
                        </a>
                    </div>
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
                                    appointment.appoid,
                                    procedures.procedure_name,
                                    appointment.appodate,
                                    appointment.appointment_time,
                                    patient.pname as patient_name
                                FROM appointment
                                INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
                                INNER JOIN patient ON appointment.pid = patient.pid
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
    
    <?php
    if ($_GET) {
        $id = $_GET["id"];
        $action = $_GET["action"];
        
        if ($action == 'deactivate_account') {
    $sqlmain = "select * from doctor where docid='$id'";
    $result = $database->query($sqlmain);
    $row = $result->fetch_assoc();
    $name = $row["docname"];
    
    echo '
    <div id="popup1" class="overlay">
        <div class="popup">
            <div class="popup-header">
                <h2 class="popup-title">Deactivate Account</h2>
                <a class="close" href="settings.php">×</a>
            </div>
            <div class="popup-content">
                <p>Are you sure you want to deactivate your account?</p>
                <p>This will mark your account as inactive and you won\'t be able to log in.</p>
                <p><strong>Note:</strong> This action is reversible by contacting the administrator.</p>
                
                <form action="delete-account.php" method="POST" style="margin-top:20px;">
                    <input type="hidden" name="docid" value="'.$id.'">
                    <div class="action-buttons-right">
                        <a href="settings.php" class="action-btn cancel-btn">Cancel</a>
                        <button type="submit" class="action-btn remove-btn" name="deactivate">Deactivate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>';
} elseif ($action == 'change_password') {
            echo '
            <div id="popup1" class="overlay">
                <div class="popup">
                    <div class="popup-header">
                        <h2 class="popup-title">Change Password</h2>
                        <a class="close" href="settings.php">×</a>
                    </div>
                    <div class="popup-content">
                        <form action="settings.php" method="POST" class="popup-form">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="label-td">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="input-text" placeholder="Enter current password" required>
                            </div>
                            
                            <div class="label-td">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" name="new_password" class="input-text" placeholder="Enter new password" required>
                            </div>
                            
                            <div class="label-td">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="input-text" placeholder="Confirm new password" required>
                            </div>
                            
                            <div class="action-buttons-right">
                                <a href="settings.php" class="action-btn cancel-btn">Cancel</a>
                                <button type="submit" class="action-btn done-btn">Change</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>';
        } elseif ($action == 'edit_profile') {
            // Fetching user details
            $sqlmain = "SELECT * FROM doctor WHERE docid='$id'";
            $result = $database->query($sqlmain);
            $row = $result->fetch_assoc();
            $name = $row["docname"];
            $email = $row["docemail"];
            $tele = $row['doctel'];

            echo '
            <div id="popup1" class="overlay">
                <div class="popup">
                    <div class="popup-header">
                        <h2 class="popup-title">Edit Personal Details</h2>
                        <a class="close" href="settings.php">×</a>
                    </div>
                    <div class="popup-content">
                        <form action="settings.php" method="POST" class="popup-form">
                            <input type="hidden" name="update_profile" value="1">
                            <input type="hidden" name="user_id" value="' . $id . '">
                            
                            <div class="label-td">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" name="email" class="input-text" placeholder="Email Address" value="' . htmlspecialchars($email) . '" required>
                            </div>
                            
                            <div class="label-td">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" name="name" class="input-text" placeholder="Name" value="' . htmlspecialchars($name) . '" required>
                            </div>
                            
                            <div class="label-td">
                                <label for="Tele" class="form-label">Telephone</label>
                                <input type="tel" name="Tele" class="input-text" placeholder="Telephone Number" value="' . htmlspecialchars($tele) . '" required>
                            </div>
                            
                            <div class="action-buttons-right">
                                <a href="settings.php" class="action-btn cancel-btn">Cancel</a>
                                <button type="submit" class="action-btn done-btn">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>';
        } elseif ($action == 'change_profile_picture') {
            // Fetch current photo
            $sqlmain = "SELECT photo FROM doctor WHERE docid='$id'";
            $result = $database->query($sqlmain);
            $row = $result->fetch_assoc();
            $current_photo = $row["photo"];
            $has_photo = !empty($current_photo) && file_exists("../admin/uploads/" . $current_photo);
            $photopath = $has_photo ? "../admin/uploads/" . $current_photo : "../Media/Icon/Blue/profile.png";

            echo '
            <div id="popup1" class="overlay">
                <div class="popup">
                    <div class="popup-header">
                        <h2 class="popup-title">Change Profile Picture</h2>
                        <a class="close" href="settings.php">×</a>
                    </div>
                    <div class="popup-content">
                        <form action="update-profile-pic.php" method="POST" class="popup-form" enctype="multipart/form-data">
                            <input type="hidden" name="upload_photo" value="1">
                            <input type="hidden" name="docid" value="' . $id . '">
                            
                            <div class="form-section">
                                <div class="label-td">
                                    <label for="profile_picture" class="form-label">Current Profile Picture</label>
                                    <img src="' . $photopath . '" alt="Profile Picture" class="image-preview">
                                </div>
                                
                                <div class="label-td">
                                    <label for="profile_picture" class="form-label">Upload New Picture</label>
                                    <div class="file-input-wrapper">
                                        <label for="profile_picture" class="file-input-label">Choose File</label>
                                        <input type="file" id="profile_picture" name="profile_picture" class="file-input" accept="image/*">
                                    </div>
                                    <img id="file-preview" class="file-preview" alt="Preview">
                                    <p style="font-size: 12px; color: #777;">Accepted formats: JPG, PNG, GIF. Max size: 5MB</p>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <button type="submit" class="action-btn done-btn" id="change-btn" disabled>Change</button>
                                <button type="submit" name="delete_photo" value="1" class="action-btn remove-btn" id="remove-btn" ' . ($has_photo ? '' : 'disabled') . '>Remove</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>';
        }
    }
    ?>
    <script>
        // Function to clear search and redirect
        function clearSearch() {
            window.location.href = 'settings.php';
        }

        // Search functionality
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('searchInput');
            const settingsCards = document.querySelectorAll('.settings-card');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    
                    settingsCards.forEach(card => {
                        const title = card.querySelector('.settings-title').textContent.toLowerCase();
                        const description = card.querySelector('.settings-description').textContent.toLowerCase();
                        
                        if (title.includes(searchTerm) || description.includes(searchTerm)) {
                            card.style.display = 'flex';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            }

            // File input preview and button state management
            const fileInput = document.getElementById('profile_picture');
            const filePreview = document.getElementById('file-preview');
            const changeBtn = document.getElementById('change-btn');
            const removeBtn = document.getElementById('remove-btn');
            
            if (fileInput && filePreview && changeBtn && removeBtn) {
                fileInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            filePreview.src = e.target.result;
                            filePreview.style.display = 'block';
                            changeBtn.disabled = false;
                        };
                        reader.readAsDataURL(file);
                    } else {
                        filePreview.style.display = 'none';
                        changeBtn.disabled = true;
                    }
                });
            }
        });
    </script>
</body>
</html>
