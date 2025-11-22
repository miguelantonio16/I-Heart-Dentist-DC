<?php
date_default_timezone_set('Asia/Singapore');
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'p') {
        header("location: login.php");
    }
} else {
    header("location: ../login.php");
}

include("../connection.php");
$useremail = $_SESSION["user"];
// Fetch patient data along with branch name (if assigned)
$userrow = $database->query("SELECT p.*, b.name AS branch_name FROM patient p LEFT JOIN branches b ON p.branch_id = b.id WHERE p.pemail='$useremail'");
$userfetch = $userrow->fetch_assoc();
$userid = $userfetch["pid"];
$username = $userfetch["pname"];
// Normalize branch name variable for easier use later
$userBranchName = isset($userfetch['branch_name']) && $userfetch['branch_name'] !== null ? $userfetch['branch_name'] : '';


// Get notification count
$unreadCount = $database->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = '$userid' AND user_type = 'p' AND is_read = 0");
$unreadCount = $unreadCount->fetch_assoc()['count'];

// Get notifications
$notifications = $database->query("SELECT * FROM notifications WHERE user_id = '$userid' AND user_type = 'p' ORDER BY created_at DESC");


// Get medical history from both tables
$medicalHistory = $database->query("
    SELECT mh.*, ic.consent_date 
    FROM medical_history mh
    LEFT JOIN informed_consent ic ON mh.email = ic.email
    WHERE mh.email = '$useremail'
    ORDER BY ic.consent_date DESC
");

// Get dental records
$dentalRecords = $database->query("SELECT * FROM dental_records WHERE patient_id = $userid ORDER BY upload_date DESC");

// Get informed consents
$consents = $database->query("SELECT * FROM informed_consent WHERE email = '$useremail' ORDER BY consent_date DESC");
// Get the latest consent if it exists
$latestConsent = $database->query("SELECT * FROM informed_consent WHERE email = '$useremail' ORDER BY consent_date DESC LIMIT 1");
$hasConsent = $latestConsent->num_rows > 0;
$consentData = $hasConsent ? $latestConsent->fetch_assoc() : null;

// Get medical history data
$medicalData = $database->query("SELECT * FROM medical_history WHERE email = '$useremail'");
$hasMedicalHistory = $medicalData->num_rows > 0;
$medical = $hasMedicalHistory ? $medicalData->fetch_assoc() : null;

// Get data for right sidebar from dashboard.php
$patientrow = $database->query("select * from patient;");
$doctorrow = $database->query("select * from doctor where status='active';");
$appointmentrow = $database->query("select * from appointment where status='booking' AND pid='$userid';");
$schedulerow = $database->query("select * from appointment where status='appointment' AND pid='$userid';");

// For calendar
$today = date('Y-m-d');
$currentMonth = date('F');
$currentYear = date('Y');
$daysInMonth = date('t');
$firstDayOfMonth = date('N', strtotime("$currentYear-" . date('m') . "-01"));
$currentDay = date('j');

$upcomingAppointments = $database->query("
    SELECT
        appointment.appoid,
        procedures.procedure_name,
        appointment.appodate,
        appointment.appointment_time,
        doctor.docname as doctor_name,
        b.name AS branch_name
    FROM appointment
    INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
    INNER JOIN doctor ON appointment.docid = doctor.docid
    LEFT JOIN branches b ON doctor.branch_id = b.id
    WHERE
        appointment.pid = '$userid'
        AND appointment.status = 'appointment'
        AND appointment.appodate >= '$today'
    ORDER BY appointment.appodate ASC
    LIMIT 3;
");
$upcomingAppointments = $database->query("
    SELECT
        appointment.appoid,
        procedures.procedure_name,
        appointment.appodate,
        appointment.appointment_time,
        doctor.docname as doctor_name,
        b.name AS branch_name
    FROM appointment
    INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
    INNER JOIN doctor ON appointment.docid = doctor.docid
    LEFT JOIN branches b ON doctor.branch_id = b.id
    WHERE
        appointment.pid = '$userid'
        AND appointment.status = 'appointment'
        AND appointment.appodate >= '$today'
    ORDER BY appointment.appodate ASC
    LIMIT 3;
");

date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');
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
    <title>Profile - IHeartDentistDC</title>
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">
    <style>
        .main-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .menu {
            width: 100%;
        }

        .nav-container {
            flex: 0 0 20%;
            height: 100vh;
            overflow-y: auto;
            position: sticky;
            width: 100%;
            top: 0;
        }

        .dash-body {
            flex: 1;
            height: 100vh;
            overflow-y: auto;
            background: #f5f7fa;
            padding: 20px;
        }

        /* Profile Header */
        .profile-header {
            display: flex;
            align-items: center;
            background: url('../media/background/background-blue.png') no-repeat center center;
            background-size: cover; 
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            height: 14.5em;
            position: relative;
            z-index: 1; /* keep header behind tabs */
        }

        .profile-avatar {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 25px;
            border: 4px solid rgb(255, 255, 255);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .profile-info h2 {
            margin: 0 0 5px 0;
            font-size: 24px;
            font-weight: 600;
        }

        .profile-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 15px;
        }

        .edit-profile-btn {
            margin-left: auto;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .edit-profile-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .edit-profile-btn img {
            width: 16px;
            height: 16px;
        }

        /* Tabs Styling */
        .tabs-container {
            margin-bottom: 30px;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            z-index: 3; /* ensure tabs sit above header if any overlap */
        }

        .tabs {
            display: flex;
            background-color: #fff;
            border-radius: 8px 8px 0 0;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 4;
            pointer-events: auto;
        }

        .tab {
            padding: 15px 25px;
            cursor: pointer;
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 500;
            text-align: center;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
            flex: 1;
        }

        .tab:hover {
            background-color: #e9ecef;
        }

        .tab.active {
            background-color: #fff;
            color: #2b4c7e;
            border-bottom: 2px solid #2b4c7e;
        }

        /* Tab Content Styling */
        .tab-content {
            background: white;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            min-height: 400px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Content Cards */
        .content-card {
            margin-bottom: 25px;
            border-radius: 8px;
            background: #f8f9fa;
            padding: 20px;
            border-left: 4px solid #2b4c7e;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .card-header h3 {
            margin: 0;
            color: #2b4c7e;
            font-size: 18px;
            font-weight: 600;
        }

        /* Personal Info */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .info-value {
            color: #212529;
            background: white;
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            display: block;
            font-size: 15px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state img {
            width: 120px;
            margin-bottom: 20px;
            opacity: 0.7;
        }

        .empty-state p {
            color: #6c757d;
            font-size: 16px;
        }

        /* Dental Records Styles */
        .records-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .record-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .record-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .record-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }

        .record-info {
            padding: 15px;
        }

        .record-date {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .record-notes {
            margin-bottom: 15px;
            color: #212529;
            font-size: 14px;
        }

        .download-btn {
            display: inline-block;
            padding: 8px 16px;
            background: #2b4c7e;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: background 0.3s;
            text-align: center;
            font-weight: 500;
        }

        .download-btn:hover {
            background: #1a365d;
        }

        /* Forms Table */
        .forms-table {
            width: 100%;
            border-collapse: collapse;
        }

        .forms-table tr {
            border-bottom: 1px solid #e9ecef;
        }

        .forms-table tr:last-child {
            border-bottom: none;
        }

        .forms-table td {
            padding: 15px 10px;
        }

        .form-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            text-align: center;
        }

        .status-signed {
            background: #e6f7ee;
            color: #28a745;
        }

        /* Generalized Popup Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: #dae8f6; /* Light blue background */
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            text-align: center;
        }

        .modal-content h2 {
            margin: 0 0 20px;
            font-size: 15px;
            font-weight: 700;
            color: #333;
        }

        .modal-content .close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            text-decoration: none;
            color: #666;
            cursor: pointer;
        }

        .modal-content .close:hover {
            color: #333;
        }

        .modal-content .image-preview {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            display: block;
            border: 10px solid rgb(255, 255, 255);
        }

        .modal-content .file-input-container {
            margin: 20px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .modal-content input[type="file"] {
            display: none;
        }

        .modal-content .file-input-label {
            background: #787878; /* Light blue for "Choose File" */
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s;
        }

        .modal-content .file-input-label:hover {
            background:rgb(155, 155, 155);
        }

        .modal-content .file-name {
            font-size: 14px;
            color: #666;
        }

        .modal-content .modal-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .modal-content .modal-btn {
            padding: 7px 50px;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
        }

        .modal-content .modal-btn.remove {
            background: #ff5c5c; /* Red for "Remove" */
            color: white;
        }

        .modal-content .modal-btn.remove:hover {
            background: #fb8383;
        }

        .modal-content .modal-btn.update {
            background: #84b6e4; /* Light blue for "Update" */
            color: #333;
        }

        .modal-content .modal-btn.update:hover {
            background: #97c3ec;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-avatar {
                margin-right: 0;
                margin-bottom: 15px;
            }

            .edit-profile-btn {
                margin-left: 0;
                margin-top: 15px;
            }

            .tabs {
                flex-wrap: wrap;
            }

            .tab {
                padding: 12px 15px;
                font-size: 14px;
            }
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .records-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        @media (max-width: 576px) {
            .tab-content {
                padding: 20px 15px;
            }

            .modal-content {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="main-wrapper">
        <!-- Sidebar - Kept intact as requested -->
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
                <a href="profile.php" class="nav-item active">
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

        <!-- Content Area - Redesigned as requested -->
        <div class="content-area">
            <div class="content">
                <div class="main-section">
                    <table border="0" width="100%" style="border-spacing: 0;margin:0;padding:0;">
                        <tr>
                            <td colspan="4">
                                <!-- Profile Header with Avatar -->
                                <div class="profile-header">
                                    <?php
                                    // ensure helper available and get normalized path
                                    include_once __DIR__ . '/../inc/get_profile_pic.php';
                                    $profile_pic = get_profile_pic($userfetch);
                                    ?>
                                    <img src="../<?php echo $profile_pic; ?>" alt="Profile Photo"
                                        class="profile-avatar">
                                    <div class="profile-info">
                                        <h2><?php echo htmlspecialchars($username); ?></h2>
                                        <p><?php echo htmlspecialchars($useremail); ?></p>
                                    </div>
                                    <button class="edit-profile-btn" onclick="openPopup('profilePicPopup')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                            fill="currentColor" viewBox="0 0 16 16">
                                            <path
                                                d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z" />
                                        </svg>
                                        Edit Profile Picture
                                    </button>
                                </div>

                                <!-- Profile Picture Popup -->
                                <div id="profilePicPopup" class="modal-overlay">
                                    <div class="modal-content">
                                        <h2>Change Profile Picture</h2>
                                        <a href="#" class="close" onclick="closePopup('profilePicPopup')">×</a>
                                        <img src="../<?php echo $profile_pic; ?>" alt="Current Profile Picture" class="image-preview">
                                        <form id="profilePicForm" action="update-profile-pic.php" method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="pid" value="<?php echo $userid; ?>">
                                            <div class="file-input-container">
                                                <label for="profile_pic" class="file-input-label">Choose File</label>
                                                <input type="file" name="profile_pic" id="profile_pic" accept="image/*" onchange="updateFileName(this)">
                                                <span class="file-name">No File Chosen</span>
                                            </div>
                                            <div class="modal-actions">
                                                <button type="button" class="modal-btn remove" onclick="deleteProfilePic()">Remove</button>
                                                <button type="submit" class="modal-btn update">Update</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Tabs Navigation -->
                                <div class="tabs-container">
                                    <div class="tabs" role="tablist" aria-label="Profile tabs">
                                        <div class="tab active" role="tab" tabindex="0" data-tab="about">About</div>
                                        <!-- Medical Record tab removed -->
                                        <div class="tab" role="tab" tabindex="0" data-tab="dental">Dental Record</div>
                                        <div class="tab" role="tab" tabindex="0" data-tab="forms">Form</div>
                                        <div class="tab" role="tab" tabindex="0" data-tab="consent">Consent Form</div>
                                    </div>

                                    <!-- Tab Content -->
                                    <div class="tab-content">
                                        <!-- About Tab -->
                                        <div id="about" class="tab-pane active">
                                            <div class="content-card">
                                                <div class="card-header">
                                                    <h3>Personal Information</h3>
                                                </div>
                                                <div class="info-grid">
                                                    <div class="info-item">
                                                        <label class="info-label">Full Name</label>
                                                        <span
                                                            class="info-value"><?php echo htmlspecialchars($username); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <label class="info-label">Email</label>
                                                        <span
                                                            class="info-value"><?php echo htmlspecialchars($useremail); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <label class="info-label">Date of Birth</label>
                                                        <span
                                                            class="info-value"><?php echo htmlspecialchars($userfetch['pdob']); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <label class="info-label">Phone</label>
                                                        <span
                                                            class="info-value"><?php echo htmlspecialchars($userfetch['ptel']); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <label class="info-label">Address</label>
                                                        <span
                                                            class="info-value"><?php echo htmlspecialchars($userfetch['paddress']); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <label class="info-label">Branch</label>
                                                        <span class="info-value"><?php echo !empty($userBranchName) ? htmlspecialchars($userBranchName) : '-'; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Medical Record tab removed -->

                                        <!-- Dental Record Tab -->
                                        <div id="dental" class="tab-pane">
                                            <?php if ($dentalRecords->num_rows > 0): ?>
                                                <div class="records-grid">
                                                    <?php while ($record = $dentalRecords->fetch_assoc()): ?>
                                                        <div class="record-card">
                                                            <img src="<?php echo htmlspecialchars($record['file_path']); ?>"
                                                                alt="Dental Record" class="record-image">
                                                            <div class="record-info">
                                                                <div class="record-date">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14"
                                                                        height="14" fill="currentColor" viewBox="0 0 16 16"
                                                                        style="vertical-align: -2px; margin-right: 4px;">
                                                                        <path
                                                                            d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                    </svg>
                                                                    <?php echo date('M d, Y', strtotime($record['upload_date'])); ?>
                                                                </div>
                                                                <div class="record-notes">
                                                                    <?php echo !empty($record['notes']) ? htmlspecialchars($record['notes']) : 'No notes provided'; ?>
                                                                </div>
                                                                <a href="<?php echo htmlspecialchars($record['file_path']); ?>"
                                                                    download class="download-btn">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14"
                                                                        height="14" fill="currentColor" viewBox="0 0 16 16"
                                                                        style="vertical-align: -2px; margin-right: 4px;">
                                                                        <path
                                                                            d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z" />
                                                                        <path
                                                                            d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z" />
                                                                    </svg>
                                                                    Download
                                                                </a>
                                                            </div>
                                                        </div>
                                                    <?php endwhile; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="empty-state">
                                                    <img src="../img/notfound.svg" alt="No Records">
                                                    <p>No dental records found yet.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Forms Tab -->
                                        <div id="forms" class="tab-pane">
                                            <?php if ($consents->num_rows > 0): ?>
                                                <div class="content-card">
                                                    <div class="card-header">
                                                        <h3>Signed Forms</h3>
                                                    </div>
                                                    <table class="forms-table">
                                                        <?php while ($consent = $consents->fetch_assoc()): ?>
                                                            <tr>
                                                                <td>
                                                                    <strong>Consent Form</strong>
                                                                    <div
                                                                        style="font-size: 14px; color: #6c757d; margin-top: 3px;">
                                                                        Signed on:
                                                                        <?php echo date('M d, Y', strtotime($consent['consent_date'])); ?>
                                                                    </div>
                                                                </td>
                                                                <td style="text-align: right;">
                                                                    <span class="form-status status-signed">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="12"
                                                                            height="12" fill="currentColor" viewBox="0 0 16 16"
                                                                            style="vertical-align: -1px; margin-right: 3px;">
                                                                            <path
                                                                                d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z" />
                                                                        </svg>
                                                                        Signed
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endwhile; ?>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <div class="empty-state">
                                                    <img src="../img/notfound.svg" alt="No Forms">
                                                    <p>No consent forms signed yet.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Consent Form Tab -->
                                        <div id="consent" class="tab-pane">
                                            <?php if ($hasConsent): ?>
                                                <div class="content-card">
                                                    <div class="card-header">
                                                        <h3>Latest Consent Form</h3>
                                                        <span style="font-size: 14px; color: #6c757d;">
                                                            Signed on:
                                                            <?php echo date('M d, Y', strtotime($consentData['consent_date'])); ?>
                                                        </span>
                                                    </div>
                                                    <div class="info-grid">
                                                        <div class="info-item">
                                                            <label class="info-label">Dental Treatment</label>
                                                            <span
                                                                class="info-value"><?php echo htmlspecialchars($consentData['initial_treatment_to_be_done'] ?: 'Not specified'); ?></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <label class="info-label">Drugs/Medications</label>
                                                            <span
                                                                class="info-value"><?php echo htmlspecialchars($consentData['initial_drugs_medications'] ?: 'Not specified'); ?></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <label class="info-label">Treatment Changes</label>
                                                            <span
                                                                class="info-value"><?php echo htmlspecialchars($consentData['initial_changes_treatment_plan'] ?: 'Not specified'); ?></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <label class="info-label">Radiographs (X-rays)</label>
                                                            <span
                                                                class="info-value"><?php echo htmlspecialchars($consentData['initial_radiograph'] ?: 'Not specified'); ?></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <label class="info-label">Tooth Removal</label>
                                                            <span
                                                                class="info-value"><?php echo htmlspecialchars($consentData['initial_removal_teeth'] ?: 'Not specified'); ?></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <label class="info-label">Crowns/Bridges</label>
                                                            <span
                                                                class="info-value"><?php echo htmlspecialchars($consentData['initial_crowns_bridges'] ?: 'Not specified'); ?></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <label class="info-label">Endodontics</label>
                                                            <span
                                                                class="info-value"><?php echo htmlspecialchars($consentData['initial_endodontics'] ?: 'Not specified'); ?></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <label class="info-label">Periodontal Treatment</label>
                                                            <span
                                                                class="info-value"><?php echo htmlspecialchars($consentData['initial_periodontal_disease'] ?: 'Not specified'); ?></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <label class="info-label">Fillings</label>
                                                            <span
                                                                class="info-value"><?php echo htmlspecialchars($consentData['initial_fillings'] ?: 'Not specified'); ?></span>
                                                        </div>
                                                        <div class="info-item">
                                                            <label class="info-label">Dentures</label>
                                                            <span
                                                                class="info-value"><?php echo htmlspecialchars($consentData['initial_dentures'] ?: 'Not specified'); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="empty-state">
                                                    <img src="../img/notfound.svg" alt="No Consent Form">
                                                    <p>No consent form has been signed yet.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
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
                            $sql = "SELECT
                                appointment.appoid,
                                procedures.procedure_name,
                                appointment.appodate,
                                appointment.appointment_time,
                                doctor.docname as doctor_name,
                                b.name AS branch_name
                            FROM appointment
                            INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
                            INNER JOIN doctor ON appointment.docid = doctor.docid
                            LEFT JOIN branches b ON doctor.branch_id = b.id
                            WHERE
                                appointment.pid = '$userid'
                                AND appointment.status = 'appointment'
                                AND appointment.appodate >= '$today'
                            ORDER BY appointment.appodate ASC
                            LIMIT 3";

                            $upcomingAppointments = $database->query($sql);

                            if ($upcomingAppointments && $upcomingAppointments->num_rows > 0) {
                                while ($appointment = $upcomingAppointments->fetch_assoc()) {
                                    echo '<div class="appointment-item">';
                                    echo '<h4 class="appointment-type">' . htmlspecialchars($appointment['procedure_name']) . '</h4>';
                                    echo '<p class="appointment-dentist">With Dr. ' . htmlspecialchars($appointment['doctor_name']) . '</p>';
                                    echo '<p class="appointment-date">' . htmlspecialchars(date('F j, Y', strtotime($appointment['appodate']))) . ' • ' . htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time']))) . '</p>';
                                    echo '<p class="appointment-branch">' . htmlspecialchars($appointment['branch_name'] ?? '-') . '</p>';
                                    echo '</div>';
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

    <!-- JavaScript for tab and popup functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Tab functionality (event delegation + keyboard support)
            const tabsContainer = document.querySelector('.tabs');
            const tabPanes = document.querySelectorAll('.tab-pane');

            function activateTab(tabElement) {
                if (!tabElement) return;
                const allTabs = document.querySelectorAll('.tab');
                allTabs.forEach(t => t.classList.remove('active'));
                tabPanes.forEach(p => p.classList.remove('active'));

                tabElement.classList.add('active');
                const tabId = tabElement.getAttribute('data-tab');
                const pane = document.getElementById(tabId);
                if (pane) pane.classList.add('active');
            }

            if (tabsContainer) {
                tabsContainer.addEventListener('click', function (e) {
                    const tab = e.target.closest('.tab');
                    if (tab) {
                        activateTab(tab);
                    }
                });

                // keyboard support: Enter or Space activates focused tab
                tabsContainer.addEventListener('keydown', function (e) {
                    const key = e.key;
                    if (key === 'Enter' || key === ' ') {
                        const tab = document.activeElement;
                        if (tab && tab.classList.contains('tab')) {
                            e.preventDefault();
                            activateTab(tab);
                        }
                    }
                });
            }

            // Generalized Popup functionality
            window.openPopup = function (popupId) {
                const popup = document.getElementById(popupId);
                if (popup) {
                    popup.classList.add('active');
                }
            };

            window.closePopup = function (popupId) {
                const popup = document.getElementById(popupId);
                if (popup) {
                    popup.classList.remove('active');
                }
            };

            // Update file name display
            window.updateFileName = function (input) {
                const fileNameSpan = input.nextElementSibling;
                if (input.files.length > 0) {
                    fileNameSpan.textContent = input.files[0].name;
                } else {
                    fileNameSpan.textContent = 'No File Chosen';
                }
            };

            // Delete profile picture
            window.deleteProfilePic = function () {
                if (confirm('Are you sure you want to delete your profile picture?')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'delete-profile-pic.php';
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'pid';
                    input.value = '<?php echo $userid; ?>';
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            };

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
</body>

</html>
