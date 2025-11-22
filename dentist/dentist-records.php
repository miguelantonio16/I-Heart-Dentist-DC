<?php
date_default_timezone_set('Asia/Singapore');
session_start();

if (!isset($_SESSION["user"])) {
    header("location: login.php");
    exit();
}

if ($_SESSION['usertype'] != 'd') {
    header("location: login.php");
    exit();
}

include("../connection.php");
date_default_timezone_set('Asia/Singapore');

$useremail = $_SESSION["user"];
$docid = $_SESSION['userid']; // Get dentist ID from session

// Fetch dentist information for the profile section
$userrow = $database->query("SELECT * FROM doctor WHERE docemail='$useremail'");
$userfetch = $userrow->fetch_assoc();
$username = $userfetch["docname"];

// Get counts for sidebar
$patient_count = $database->query("SELECT COUNT(DISTINCT pid) FROM appointment WHERE docid='$docid'")->fetch_row()[0];
$booking_count = $database->query("SELECT COUNT(*) FROM appointment WHERE status='booking' AND docid='$docid'")->fetch_row()[0];
$appointment_count = $database->query("SELECT COUNT(*) FROM appointment WHERE status='appointment' AND docid='$docid'")->fetch_row()[0];

// Calendar variables
$today = date('Y-m-d');
$currentMonth = date('F');
$currentYear = date('Y');
$daysInMonth = date('t');
$firstDayOfMonth = date('N', strtotime("$currentYear-" . date('m') . "-01"));
$currentDay = date('j');

// Handle patient record view
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'view' && isset($_GET['id'])) {
        $patient_id = $_GET['id'];
        
        // Verify this patient has appointments with the current dentist
        $verify_sql = "SELECT * FROM appointment WHERE pid = ? AND docid = ?";
        $stmt = $database->prepare($verify_sql);
        $stmt->bind_param("ii", $patient_id, $docid);
        $stmt->execute();
        $verify_result = $stmt->get_result();
        
        if ($verify_result->num_rows == 0) {
            header("Location: dentist-records.php?error=unauthorized_access");
            exit();
        }
        
        // Fetch patient basic info
        $patient_sql = "SELECT * FROM patient WHERE pid = ?";
        $stmt = $database->prepare($patient_sql);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $patient_result = $stmt->get_result();
        $patient = $patient_result->fetch_assoc();
        
        if (!$patient) {
            header("Location: dentist-records.php?error=patient_not_found");
            exit();
        }
        
        // Fetch medical history
        $medical_sql = "SELECT * FROM medical_history WHERE email = ?";
        $stmt = $database->prepare($medical_sql);
        $stmt->bind_param("s", $patient['pemail']);
        $stmt->execute();
        $medical_result = $stmt->get_result();
        $medical_history = $medical_result->fetch_assoc();
        
        // Fetch informed consent
        $consent_sql = "SELECT * FROM informed_consent WHERE email = ? ORDER BY consent_date DESC LIMIT 1";
        $stmt = $database->prepare($consent_sql);
        $stmt->bind_param("s", $patient['pemail']);
        $stmt->execute();
        $consent_result = $stmt->get_result();
        $informed_consent = $consent_result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/table.css">
    <title>Patient Records - IHeartDentistDC</title>
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">
    <style>
        /* Enhanced Modal Styles */
        .modal-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 10px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            padding: 30px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.3);
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 28px;
            font-weight: bold;
            color: #555;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .modal-close:hover {
            color: #000;
        }
        
        .modal-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #2c3e50;
        }
        
        .modal-body {
            padding: 20px 0;
        }
        
        /* Enhanced Record Styles */
        .record-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .record-section h3 {
            color: #2c3e50;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.4em;
        }
        
        .record-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .record-label {
            font-weight: 600;
            color: #555;
            align-self: start;
        }
        
        .record-value {
            color: #333;
        }
        
        .signature-container {
            margin-top: 20px;
            padding: 15px;
            background: white;
            border-radius: 5px;
            border: 1px solid #ddd;
            max-width: 400px;
        }
        
        .signature-image {
            max-width: 100%;
            max-height: 150px;
            display: block;
            margin-top: 10px;
        }
        
        /* Dental Records Grid */
        .dental-records-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .dental-record-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .dental-record-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .record-image-container {
            height: 200px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
        }
        
        .record-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            cursor: pointer;
        }
        
        .record-details {
            padding: 15px;
        }
        
        .record-date {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 10px;
        }
        
        .record-notes {
            font-size: 0.95em;
            color: #333;
            margin-bottom: 15px;
            word-break: break-word;
        }
        
        .record-actions {
            display: flex;
            justify-content: space-between;
        }
        
        .download-btn {
            display: inline-block;
            padding: 6px 12px;
            background: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            transition: background 0.2s;
        }
        
        .download-btn:hover {
            background: #0b7dda;
        }
        
        .delete-btn {
            display: inline-block;
            padding: 6px 12px;
            background: #f44336;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            transition: background 0.2s;
        }
        
        .delete-btn:hover {
            background: #d32f2f;
        }
        
        /* Form Styles */
        .form-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input[type="file"],
        .form-group textarea,
        .form-group input[type="text"],
        .form-group input[type="date"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .submit-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            transition: background 0.2s;
        }
        
        .submit-btn:hover {
            background-color: #45a049;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9em;
            text-align: center;
            transition: all 0.2s;
        }
        
        .vieww-btn {
            background-color: #4CAF50;
            color: white;
        }
        
        .vieww-btn:hover {
            background-color: #45a049;
        }
        
        .editt-btn {
            background-color: #2196F3;
            color: white;
        }
        
        .editt-btn:hover {
            background-color: #0b7dda;
        }
        
        .addd-btn {
            background-color: #ff9800;
            color: white;
        }
        
        .addd-btn:hover {
            background-color: #e68a00;
        }

        .action-btn {
            width: 170px;
            border-radius: 50px;
            padding-left: 40px;
            height: 30px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .record-grid {
                grid-template-columns: 1fr;
            }
            
            .record-label {
                margin-bottom: 5px;
            }
            
            .dental-records-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                padding: 20px;
            }
        }
        
        /* Right sidebar styles */
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
        
        .no-results {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        
        .no-results img {
            width: 25%;
            margin-bottom: 20px;
        }
        
        .profile-img-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        /* Image preview modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            overflow: auto;
        }
        
        .image-modal-content {
            display: block;
            margin: auto;
            max-width: 90%;
            max-height: 90%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .close-image {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
        }
        
        .close-image:hover {
            color: #bbb;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
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
                <a href="dentist-records.php" class="nav-item active">
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
            <div class="content">
                <div class="main-section">
                    <?php if (isset($_GET['action']) && $_GET['action'] == 'view' && isset($patient)): ?>
                        <!-- Patient Record View -->
                        <div class="announcements-header">
                            <h3 class="announcements-title">Patient Record: <?php echo $patient['pname']; ?></h3>
                            <div class="announcement-filters">
                                <a href="dentist-records.php" class="filter-btn active">Back to Records</a>
                            </div>
                        </div>
                        
                        <div class="record-section">
                            <h3>Patient Information</h3>
                            <div class="record-grid">
                                <div class="record-label">Patient ID:</div>
                                <div class="record-value">P-<?php echo $patient['pid']; ?></div>
                                
                                <div class="record-label">Name:</div>
                                <div class="record-value"><?php echo $patient['pname']; ?></div>
                                
                                <div class="record-label">Email:</div>
                                <div class="record-value"><?php echo $patient['pemail']; ?></div>
                                
                                <div class="record-label">Phone:</div>
                                <div class="record-value"><?php echo $patient['ptel']; ?></div>
                                
                                <div class="record-label">Date of Birth:</div>
                                <div class="record-value"><?php echo $patient['pdob']; ?></div>
                                
                                <div class="record-label">Address:</div>
                                <div class="record-value"><?php echo $patient['paddress']; ?></div>
                            </div>
                        </div>
                        
                        <div class="record-section">
                            <h3>Medical History</h3>
                            <?php if ($medical_history): ?>
                                <div class="record-grid">
                                    <div class="record-label">In Good Health:</div>
                                    <div class="record-value"><?php echo $medical_history['good_health']; ?></div>
                                    
                                    <div class="record-label">Under Medical Treatment:</div>
                                    <div class="record-value"><?php echo $medical_history['under_treatment']; ?></div>
                                    
                                    <?php if ($medical_history['under_treatment'] == 'Yes'): ?>
                                        <div class="record-label">Condition Treated:</div>
                                        <div class="record-value"><?php echo $medical_history['condition_treated']; ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="record-label">Serious Illness/Surgery:</div>
                                    <div class="record-value"><?php echo $medical_history['serious_illness']; ?></div>
                                    
                                    <div class="record-label">Hospitalized:</div>
                                    <div class="record-value"><?php echo $medical_history['hospitalized']; ?></div>
                                    
                                    <div class="record-label">Taking Medication:</div>
                                    <div class="record-value"><?php echo $medical_history['medication']; ?></div>
                                    
                                    <?php if ($medical_history['medication'] == 'Yes'): ?>
                                        <div class="record-label">Medication Details:</div>
                                        <div class="record-value"><?php echo $medical_history['medication_specify']; ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="record-label">Tobacco Use:</div>
                                    <div class="record-value"><?php echo $medical_history['tobacco']; ?></div>
                                    
                                    <div class="record-label">Recreational Drug Use:</div>
                                    <div class="record-value"><?php echo $medical_history['drugs']; ?></div>
                                    
                                    <div class="record-label">Allergies:</div>
                                    <div class="record-value"><?php echo $medical_history['allergies'] ? $medical_history['allergies'] : 'None reported'; ?></div>
                                    
                                    <div class="record-label">Blood Pressure:</div>
                                    <div class="record-value"><?php echo $medical_history['blood_pressure'] ? $medical_history['blood_pressure'] : 'Not recorded'; ?></div>
                                    
                                    <div class="record-label">Bleeding Time:</div>
                                    <div class="record-value"><?php echo $medical_history['bleeding_time'] ? $medical_history['bleeding_time'] : 'Not recorded'; ?></div>
                                    
                                    <div class="record-label">Other Health Conditions:</div>
                                    <div class="record-value"><?php echo $medical_history['health_conditions'] ? $medical_history['health_conditions'] : 'None reported'; ?></div>
                                </div>
                            <?php else: ?>
                                <p>No medical history recorded for this patient.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="record-section">
                            <h3>Informed Consent</h3>
                            <?php if ($informed_consent): ?>
                                <div class="record-grid">
                                    <div class="record-label">Consent Date:</div>
                                    <div class="record-value"><?php echo $informed_consent['consent_date']; ?></div>
                                    
                                    <div class="record-label">Treatment to be Done:</div>
                                    <div class="record-value"><?php echo $informed_consent['initial_treatment_to_be_done'] == 'y' ? 'Agreed' : 'Not agreed'; ?></div>
                                    
                                    <div class="record-label">Drugs/Medications:</div>
                                    <div class="record-value"><?php echo $informed_consent['initial_drugs_medications'] == 'y' ? 'Agreed' : 'Not agreed'; ?></div>
                                    
                                    <div class="record-label">Changes to Treatment Plan:</div>
                                    <div class="record-value"><?php echo $informed_consent['initial_changes_treatment_plan'] == 'y' ? 'Agreed' : 'Not agreed'; ?></div>
                                    
                                    <div class="record-label">Radiographs (X-rays):</div>
                                    <div class="record-value"><?php echo $informed_consent['initial_radiograph'] == 'y' ? 'Agreed' : 'Not agreed'; ?></div>
                                    
                                    <div class="record-label">Removal of Teeth:</div>
                                    <div class="record-value"><?php echo $informed_consent['initial_removal_teeth'] == 'y' ? 'Agreed' : 'Not agreed'; ?></div>
                                    
                                    <div class="record-label">Crowns/Bridges:</div>
                                    <div class="record-value"><?php echo $informed_consent['initial_crowns_bridges'] == 'y' ? 'Agreed' : 'Not agreed'; ?></div>
                                    
                                    <div class="record-label">Endodontics (Root Canal):</div>
                                    <div class="record-value"><?php echo $informed_consent['initial_endodontics'] == 'y' ? 'Agreed' : 'Not agreed'; ?></div>
                                    
                                    <div class="record-label">Periodontal Disease Treatment:</div>
                                    <div class="record-value"><?php echo $informed_consent['initial_periodontal_disease'] == 'y' ? 'Agreed' : 'Not agreed'; ?></div>
                                    
                                    <div class="record-label">Fillings:</div>
                                    <div class="record-value"><?php echo $informed_consent['initial_fillings'] == 'y' ? 'Agreed' : 'Not agreed'; ?></div>
                                    
                                    <div class="record-label">Dentures:</div>
                                    <div class="record-value"><?php echo $informed_consent['initial_dentures'] == 'y' ? 'Agreed' : 'Not agreed'; ?></div>
                                </div>
                                
                                <?php if ($informed_consent['id_signature_path']): ?>
                                    <div class="signature-container">
                                        <div class="record-label">Patient Signature:</div>
                                        <img src="<?php echo $informed_consent['id_signature_path']; ?>" alt="Patient Signature" class="signature-image">
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p>No informed consent recorded for this patient.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="record-section">
                            <h3>Dental Records</h3>
                            <div class="action-buttons">
                                <a href="#" onclick="openDentalRecords(<?php echo $patient['pid']; ?>)" class="action-btn edit-btn">View Dental Records</a>
                                <a href="#" onclick="openAddDentalRecord(<?php echo $patient['pid']; ?>)" class="action-btn add-btn">Add Dental Record</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Patient List View -->
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

                        <div class="announcements-header">
                            <h3 class="announcements-title">Patient Records</h3>
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

                        <?php
                        if ($_GET) {
                            $keyword = $_GET["search"] ?? '';
                            $sqlmain = "SELECT DISTINCT p.* FROM patient p 
                                JOIN appointment a ON p.pid = a.pid 
                                WHERE (p.pemail='$keyword' OR p.pname='$keyword' OR p.pname LIKE '$keyword%' OR p.pname LIKE '%$keyword' OR p.pname LIKE '%$keyword%') 
                                AND a.docid = $docid AND p.status = 'active' 
                                ORDER BY p.pname " . ($currentSort === 'oldest' ? 'DESC' : 'ASC');
                        } else {
                            $sqlmain = "SELECT DISTINCT p.* FROM patient p 
                                JOIN appointment a ON p.pid = a.pid 
                                WHERE a.docid = $docid AND p.status = 'active' 
                                ORDER BY p.pname " . ($currentSort === 'oldest' ? 'DESC' : 'ASC');
                        }
                        
                        $result = $database->query($sqlmain);
                        ?>

                        <?php if ($result->num_rows > 0): ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Profile</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Contact</th>
                                            <th>Date of Birth</th>
                                            <th>Last Appointment</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): 
                                            $pid = $row["pid"];
                                            $name = $row["pname"];
                                            $email = $row["pemail"];
                                            $dob = $row["pdob"];
                                            $tel = $row["ptel"];
                                            
                                            // Get last appointment date
                                            $appt_sql = "SELECT appodate FROM appointment 
                                                WHERE pid = $pid AND docid = $docid 
                                                ORDER BY appodate DESC LIMIT 1";
                                            $appt_result = $database->query($appt_sql);
                                            $last_appt = $appt_result->fetch_assoc();
                                            $last_appt_date = $last_appt ? $last_appt['appodate'] : 'N/A';
                                        ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    $profile_pic = !empty($row["profile_pic"]) ? "../" . $row["profile_pic"] : "../Media/Icon/Blue/profile.png";
                                                    ?>
                                                    <img src="<?php echo $profile_pic; ?>" alt="<?php echo $name; ?>" class="profile-img-small">
                                                </td>
                                                <td><div class="cell-text"><?php echo $name; ?></div></td>
                                                <td><div class="cell-text"><?php echo $email; ?></div></td>
                                                <td><div class="cell-text"><?php echo $tel; ?></div></td>
                                                <td><div class="cell-text"><?php echo $dob; ?></div></td>
                                                <td><div class="cell-text"><?php echo $last_appt_date; ?></div></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="dentist-records.php?action=view&id=<?php echo $pid; ?>" class="action-btn view-btn">View Records</a>
                                                        <a href="#" onclick="openDentalRecords(<?php echo $pid; ?>)" class="action-btn edit-btn">Dental Records</a>
                                                        <a href="#" onclick="openAddDentalRecord(<?php echo $pid; ?>)" class="action-btn add-btn">Add Record</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="no-results">
                                <img src="../img/notfound.svg" width="25%">
                                <p>No patient records found!</p>
                                <p>You will see patients here after you have appointments with them.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Right sidebar section -->
                <div class="right-sidebar">
                    <div class="stats-section">
                        <div class="stats-container">
                            <!-- First row -->
                            <a href="patient.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $patient_count; ?></h1>
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
                                        <h1 class="stat-number"><?php echo $booking_count; ?></h1>
                                        <p class="stat-label">Bookings</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/booking.png" alt="Booking Icon">
                                        <?php if ($booking_count > 0): ?>
                                            <span class="notification-badge"><?php echo $booking_count; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>

                            <a href="appointment.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $appointment_count; ?></h1>
                                        <p class="stat-label">Appointments</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../Media/Icon/Blue/appointment.png" alt="Appointment Icon">
                                        <?php if ($appointment_count > 0): ?>
                                            <span class="notification-badge"><?php echo $appointment_count; ?></span>
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
                                $nextMonthDays = 42 - ($previousMonthDays + $daysInMonth);
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
                                    appointment.docid = '$docid'
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
    
    <!-- Dental Records Popup -->
    <div id="dentalRecordsPopup" class="modal-container">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <div class="modal-header">
                <h2 id="dentalRecordsTitle">Dental Records</h2>
            </div>
            <div class="modal-body" id="dentalRecordsContent">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Add Dental Record Popup -->
    <div id="addDentalRecordPopup" class="modal-container">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <div class="modal-header">
                <h2>Upload Dental Record</h2>
            </div>
            <div class="modal-body">
                <form action="upload-dental-record.php" method="POST" enctype="multipart/form-data" class="form-container">
                    <input type="hidden" name="patient_id" id="uploadPatientId">
                    <div class="form-group">
                        <label for="recordDate">Date:</label>
                        <input type="date" name="record_date" id="recordDate" required>
                    </div>
                    <div class="form-group">
                        <label for="dentalRecord">Select Image:</label>
                        <input type="file" name="dental_record" id="dentalRecord" accept="image/*" required>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <textarea name="notes" id="notes" rows="3"></textarea>
                    </div>
                    <button type="submit" class="submit-btn">Upload Record</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div id="imageModal" class="image-modal">
        <span class="close-image">&times;</span>
        <img class="image-modal-content" id="modalImage">
    </div>

    <script>
    function openDentalRecords(pid) {
        document.getElementById('dentalRecordsContent').innerHTML = '<p style="text-align: center; padding: 20px;">Loading records...</p>';
        document.getElementById('dentalRecordsPopup').style.display = 'flex';
        
        // Set current date as default for the record date
        document.getElementById('recordDate').valueAsDate = new Date();
        
        // Load dental records via AJAX
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'get-dental-records.php?pid=' + pid, true);
        xhr.onload = function() {
            if (this.status == 200) {
                document.getElementById('dentalRecordsContent').innerHTML = this.responseText;
                document.getElementById('dentalRecordsTitle').textContent = 'Dental Records for ' + document.querySelector('.announcements-title').textContent.replace('Patient Record: ', '');
                
                // Attach click handlers to all record images
                document.querySelectorAll('.record-image').forEach(img => {
                    img.addEventListener('click', function() {
                        openImageModal(this.src);
                    });
                });
            } else {
                document.getElementById('dentalRecordsContent').innerHTML = '<p style="text-align: center; padding: 20px; color: #f44336;">Error loading records. Please try again.</p>';
            }
        };
        xhr.onerror = function() {
            document.getElementById('dentalRecordsContent').innerHTML = '<p style="text-align: center; padding: 20px; color: #f44336;">Network error. Please check your connection.</p>';
        };
        xhr.send();
    }

    function openAddDentalRecord(pid) {
        document.getElementById('uploadPatientId').value = pid;
        document.getElementById('addDentalRecordPopup').style.display = 'flex';
    }

    function clearSearch() {
        window.location.href = 'dentist-records.php';
    }

    function openImageModal(imageSrc) {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        modal.style.display = 'block';
        modalImg.src = imageSrc;
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-container')) {
            event.target.style.display = 'none';
        }
        
        if (event.target.classList.contains('image-modal')) {
            event.target.style.display = 'none';
        }
    });

    // Close modals when clicking the close button
    document.querySelectorAll('.modal-close, .close-image').forEach(closeBtn => {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            this.closest('.modal-container, .image-modal').style.display = 'none';
        });
    });

    // Search input event listener
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('searchInput');
        const clearBtn = document.querySelector('.clear-btn');

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                clearSearch();
            });
        }
        
        // Set current date as default for new records
        document.getElementById('recordDate').valueAsDate = new Date();
    });
    </script>
</body>
</html>
