<?php 
session_start();
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
    <title>Settings - IHeartDentistDC</title>
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">

    <style>
        .popup {
            animation: transitionIn-Y-bottom 0.5s;
            max-width: 600px;
            width: 80%;
        }

        .sub-table {
            animation: transitionIn-Y-bottom 0.5s;
        }
        
        .edit-clinic-popup {
            padding: 20px;
            width: 100%;
            max-width: 500px;
            height: 300px;
            justify-items: center;
            align-items: center;
        }
        
        .edit-clinic-popup textarea {
            width: 100%;
            min-height: 120px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }
        
        .edit-clinic-popup input[type="text"],
        .edit-clinic-popup input[type="url"],
        .edit-clinic-popup input[type="email"],
        .edit-clinic-popup input[type="tel"] {
            width: 300px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
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
            width: auto;
            max-width: 400px;
            box-sizing: border-box;
        }
        .profile-img-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
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
        
        /* Procedures popup styles */
        .procedures-popup {
            padding: 20px;
            width: 100%;
            max-width: 600px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
        }
        
        .procedures-popup h2 {
            margin-top: 0;
            color: #2a5885;
        }
        
        .procedures-popup .input-text {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .procedures-popup textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }
        
        .success-popup {
            text-align: center;
            padding: 30px;
        }
        
        .success-popup h2 {
            color: #4CAF50;
        }
    </style>
</head>

<body>
    <?php
    date_default_timezone_set('Asia/Singapore');
    

    if (isset($_SESSION["user"])) {
        if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'a') {
            header("location: login.php");
        }
    } else {
        header("location: login.php");
    }

    // Import database connection
    include("../connection.php");

    // Get totals for right sidebar
    $doctorrow = $database->query("select * from doctor where status='active';");
    $patientrow = $database->query("select * from patient where status='active';");
    $appointmentrow = $database->query("select * from appointment where status='booking';");
    $schedulerow = $database->query("select * from appointment where status='appointment';");
    ?>
    
    <div class="main-container">
        <div class="sidebar">
            <div class="sidebar-logo">
                <img src="../Media/Icon/logo.png" alt="IHeartDentistDC Logo">
            </div>

            <div class="user-profile">
                <div class="profile-image">
                    <img src="../Media/Icon/logo.png" alt="Profile" class="profile-img">
                </div>
                <h3 class="profile-name">I Heart Dentist Dental Clinic</h3>
                <p style="color: #777; margin: 0; font-size: 14px; text-align: center;">
                Secretary
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
                <?php include __DIR__ . '/inc/sidebar-toggle.php'; ?>
                <div class="main-section">
                    <!-- search bar -->
                    <div class="search-container">
                        <form action="" method="POST" style="display: flex; width: 100%;">
                            <input type="search" name="search" id="searchInput" class="search-input"
                                placeholder="Search service name" list="services"
                                value="<?php echo isset($_POST['search']) ? htmlspecialchars($_POST['search']) : ''; ?>">
                            <?php if (isset($_POST['search']) && $_POST['search'] != ""): ?>
                                <button type="button" class="clear-btn" onclick="clearSearch()">×</button>
                            <?php endif; ?>
                            <datalist id="services">
                                <?php
                                $list11 = $database->query("SELECT procedure_name FROM services");
                                for ($y = 0; $y < $list11->num_rows; $y++) {
                                    $row00 = $list11->fetch_assoc();
                                    $d = $row00["procedure_name"];
                                    echo "<option value='$d'><br/>";
                                }
                                ?>
                            </datalist>
                        </form>
                    </div>

                    <!-- header -->
                    <div class="announcements-header">
                        <h3 class="announcements-title">Settings</h3>
                    </div>
                    <?php
                    // Show branch deletion error if redirected from delete-branch.php
                    if (isset($_GET['branch_error']) && $_GET['branch_error'] == '1') {
                        echo '<div style="margin:10px 0; padding:12px; background:#ffdede; border:1px solid #ffb3b3; color:#8a1f1f; border-radius:6px;">Cannot delete branch: it is referenced by one or more dentists, patients, or appointments. Reassign or remove those references first.</div>';
                    }
                    ?>

                    

                    <!-- Procedures Tab -->
                    <div id="procedures-tab" class="tab-content active">
                    </div>

                    <!-- Procedures Section -->
                    <!-- Procedures Section -->
<div class="table-container">
    <div class="announcements-header">
        <p class="heading-main12" style="margin-left: 15px;font-size:18px;color:rgb(49, 49, 49)">All Procedures (<?php 
        $sqlcount = "SELECT COUNT(*) FROM procedures";
        $result = $database->query($sqlcount);
        $row = $result->fetch_row();
        echo $row[0]; 
        ?>)</p>
        <div class="announcement-filters">
            <a href="?action=add_procedure&id=none&error=0" class="non-style-link">
                <button class="filter-btn add-btn"
                    style="display: flex;justify-content: center;align-items: center;margin-left:75px; width: 200px;">
                    Add New Procedure
                </button>
            </a>
        </div>
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Procedure Name</th>
                <th>Description</th>
                <th>Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($_POST) {
                $keyword = $_POST["search"];
                $sqlmain = "SELECT * FROM procedures WHERE procedure_name='$keyword' OR procedure_name LIKE '$keyword%' OR procedure_name LIKE '%$keyword' OR procedure_name LIKE '%$keyword%'";
            } else {
                $sqlmain = "SELECT * FROM procedures ORDER BY procedure_id ASC";
            }
            
            $result = $database->query($sqlmain);
            if ($result->num_rows == 0) {
                echo '<tr><td colspan="4"><center>No procedures found</center></td></tr>';
            } else {
                while ($row = $result->fetch_assoc()) {
                    $id = $row["procedure_id"];
                    $name = $row["procedure_name"];
                    $desc = $row["description"];
                    $price = isset($row['price']) ? $row['price'] : null;
                    
                    echo '<tr>
                        <td>'.$id.'</td>
                        <td><div class="cell-text">'.substr($name, 0, 30).'</div></td>
                        <td><div class="cell-text">'.substr($desc, 0, 50).'...</div></td>
                        <td>'.($price !== null ? number_format((float)$price,2) : '-').'</td>
                        <td>
                            <div class="action-buttons">
                                <a href="?action=edit_procedure&id='.$id.'&error=0" class="action-btn edit-btn">Edit</a>
                                <a href="?action=drop_procedure&id='.$id.'&name='.$name.'" class="action-btn remove-btn">Remove</a>
                            </div>
                        </td>
                    </tr>';
                }
            }
            ?>
        </tbody>
    </table>
</div>

                    <!-- Services Section -->
                    <div class="table-container" style="margin-top: 30px;">
                        <div class="announcements-header">
                            <p class="heading-main12" style="margin-left: 15px;font-size:18px;color:rgb(49, 49, 49)">All Services (<?php 
                            $sqlcount = "SELECT COUNT(*) FROM services";
                            $result = $database->query($sqlcount);
                            $row = $result->fetch_row();
                            echo $row[0]; 
                            ?>)</p>
                            <div class="announcement-filters">
                                <a href="?action=add&id=none&error=0" class="non-style-link">
                                    <button class="filter-btn add-btn" style="display: flex;justify-content: center;align-items: center; width: 180px;">Add New Service</button>
                                </a>
                            </div>
                        </div>

                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Service Name</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($_POST) {
                                    $keyword = $_POST["search"];
                                    $sqlmain = "SELECT * FROM services WHERE procedure_name='$keyword' OR procedure_name LIKE '$keyword%' OR procedure_name LIKE '%$keyword' OR procedure_name LIKE '%$keyword%'";
                                } else {
                                    $sqlmain = "SELECT * FROM services";
                                }
                                
                                $result = $database->query($sqlmain);
                                if ($result->num_rows == 0) {
                                    echo '<tr><td colspan="4"><center>No services found</center></td></tr>';
                                } else {
                                    while ($row = $result->fetch_assoc()) {
                                        $id = $row["id"];
                                        $name = $row["procedure_name"];
                                        $desc = $row["description"];
                                        $image = $row["image_path"];
                                        
                                        echo '<tr>
                                            <td>
                                                <img src="../'.$image.'" alt="Service Image" class="profile-img-small">
                                            </td>
                                            <td><div class="cell-text">'.substr($name, 0, 30).'</div></td>
                                            <td><div class="cell-text">'.substr($desc, 0, 50).'...</div></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="?action=edit&id='.$id.'&error=0" class="action-btn edit-btn">Edit</a>
                                                    <a href="?action=drop&id='.$id.'&name='.$name.'" class="action-btn remove-btn">Remove</a>
                                                </div>
                                            </td>
                                        </tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Clinic Information Tab -->
                    <!-- Branches Section -->
                    <div id="branches-tab" class="tab-content">
                        <div class="table-container" style="margin-top: 30px;">
                            <div class="announcements-header">
                                <p class="heading-main12" style="margin-left: 15px;font-size:18px;color:rgb(49, 49, 49)">Branches</p>
                                <div class="announcement-filters">
                                    <a href="?action=add_branch" class="non-style-link">
                                        <button class="filter-btn add-btn" style="display: flex;justify-content: center;align-items: center;margin-left:75px; width: 200px;">Add Branch</button>
                                    </a>
                                </div>
                            </div>

                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Address</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $branches = $database->query("SELECT * FROM branches ORDER BY id ASC");
                                    if ($branches->num_rows == 0) {
                                        echo '<tr><td colspan="4"><center>No branches found</center></td></tr>';
                                    } else {
                                        while ($b = $branches->fetch_assoc()) {
                                            $bid = $b['id'];
                                            $bname = htmlspecialchars($b['name']);
                                            $baddr = htmlspecialchars($b['address']);
                                            echo '<tr>';
                                            echo '<td>'.$bid.'</td>';
                                            echo '<td><div class="cell-text">'.$bname.'</div></td>';
                                            echo '<td><div class="cell-text">'.(strlen($baddr) > 80 ? substr($baddr,0,80).'...' : $baddr).'</div></td>';
                                            echo '<td><div class="action-buttons">'
                                                . '<a href="?action=edit_branch&id='.$bid.'" class="action-btn edit-btn">Edit</a>'
                                                . '<a href="?action=drop_branch&id='.$bid.'&name='.urlencode($bname).'" class="action-btn remove-btn">Remove</a>'
                                                . '</div></td>';
                                            echo '</tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div id="clinic-tab" class="tab-content">
                        <div class="table-container">
                            <p class="heading-main12" style="margin-left: 15px;font-size:20px;color:rgb(49, 49, 49)">Clinic Information</p>
                            
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Current Value</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $clinic_info = $database->query("SELECT * FROM clinic_info WHERE id=1")->fetch_assoc();
                                    
                                    $fields = [
                                        'clinic_name' => 'Clinic Name',
                                        'clinic_description' => 'Clinic Description',
                                        'address' => 'Address',
                                        'phone' => 'Phone Number',
                                        'email' => 'Email Address',
                                        'facebook_url' => 'Facebook Link',
                                        'instagram_url' => 'Instagram Link'
                                    ];
                                    
                                    foreach ($fields as $field => $label) {
                                        echo '<tr>
                                                <td><div class="cell-text">'.$label.'</div></td>
                                                <td><div class="cell-text">'.substr($clinic_info[$field], 0, 50).(strlen($clinic_info[$field]) > 50 ? '...' : '').'</div></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="?action=edit_clinic&field='.$field.'" class="action-btn edit-btn">Edit</a>
                                                    </div>
                                                </td>
                                            </tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Add right sidebar section -->
                <div class="right-sidebar">
                    <div class="stats-section">
                        <div class="stats-container">
                            <!-- First row -->
                            <a href="dentist.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $doctorrow->num_rows; ?></h1>
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
                        <?php
                        // Calendar variables
                        $today = date('Y-m-d');
                        $currentMonth = date('F');
                        $currentYear = date('Y');
                        $daysInMonth = date('t');
                        $firstDayOfMonth = date('N', strtotime("$currentYear-" . date('m') . "-01"));
                        $currentDay = date('j');
                        ?>
                        <div class="calendar-container">
                            <div class="calendar-header">
                                <h3 class="calendar-month">
                                    <?php echo strtoupper($currentMonth); ?>
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
                                    patient.pname as patient_name,
                                    doctor.docname as doctor_name
                                FROM appointment
                                INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
                                INNER JOIN patient ON appointment.pid = patient.pid
                                INNER JOIN doctor ON appointment.docid = doctor.docid
                                WHERE
                                    appointment.status = 'appointment'
                                    AND appointment.appodate >= '$today'
                                ORDER BY appointment.appodate ASC
                                LIMIT 3;
                            ");

                            if ($upcomingAppointments->num_rows > 0) {
                                while ($appointment = $upcomingAppointments->fetch_assoc()) {
                                    echo '<div class="appointment-item">
                                        <h4 class="appointment-type">' . htmlspecialchars($appointment['patient_name']) . '</h4>
                                        <p class="appointment-dentist">With Dr. ' . htmlspecialchars($appointment['doctor_name']) . '</p>
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
        
        if ($action == 'drop') {
            $nameget = $_GET["name"];
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                        <h2>Are you sure?</h2>
                        <a class="close" href="settings.php">&times;</a>
                        <div class="content" style="height: 0px;">
                            You want to delete this service<br>(' . substr($nameget, 0, 40) . ').
                        </div>
                        <div style="display: flex;justify-content: center;">
                        <a href="delete-service.php?id=' . $id . '" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"<font class="tn-in-text">&nbsp;Yes&nbsp;</font></button></a>&nbsp;&nbsp;&nbsp;
                        <a href="settings.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;No&nbsp;&nbsp;</font></button></a>
                        </div>
                    </center>
            </div>
            </div>
            ';
        } elseif ($action == 'add') {
            $error_1 = $_GET["error"];
            $errorlist = array(
                '1' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Service with this name already exists.</label>',
                '2' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Image upload error.</label>',
                '3' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Please fill all fields.</label>',
                '4' => "",
                '0' => '',
            );

            if ($error_1 != '4') {
                echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                        <a class="close" href="settings.php">&times;</a> 
                        <div style="display: flex;justify-content: center;">
                        <div class="abc">
                        <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                        <tr>
                                <td class="label-td" colspan="2">' . $errorlist[$error_1] . '</td>
                            </tr>
                            <tr>
                                <td>
                                    <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">Add New Service</p><br><br>
                                </td>
                            </tr>
                            
                            <tr>
                                <form action="add-service.php" method="POST" class="add-new-form" enctype="multipart/form-data">
                                <td class="label-td" colspan="2">
                                    <label for="name" class="form-label">Service Name: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <input type="text" name="name" class="input-text" placeholder="Service Name" required><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="description" class="form-label">Description: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <textarea name="description" class="input-text" placeholder="Service Description" rows="4" required></textarea><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <label for="image" class="form-label">Service Image: </label>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">
                                    <input type="file" name="image" class="input-text" accept="image/*" required><br>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <input type="reset" value="Reset" class="login-btn btn-primary-soft btn" >&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    <input type="submit" value="Add Service" class="login-btn btn-primary btn">
                                </td>
                            </tr>
                            </form>
                            </tr>
                        </table>
                        </div>
                        </div>
                    </center>
                    <br><br>
            </div>
            </div>
            ';
            } else {
                echo '
                <div id="popup1" class="overlay">
                        <div class="popup">
                        <center>
                        <br><br><br><br>
                            <h2>Service Added Successfully!</h2>
                            <a class="close" href="settings.php">&times;</a>
                            <div class="content" style="height: 0px;">
                            </div>
                            <div style="display: flex;justify-content: center;">
                            <a href="settings.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;OK&nbsp;&nbsp;</font></button></a>
                            </div>
                            <br><br>
                        </center>
                </div>
                </div>
                ';
            }
        } elseif ($action == 'edit') {
            $sqlmain = "SELECT * FROM services WHERE id='$id'";
            $result = $database->query($sqlmain);
            $row = $result->fetch_assoc();
            $name = $row["procedure_name"];
            $desc = $row["description"];
            $image = $row["image_path"];

            $error_1 = $_GET["error"];
            $errorlist = array(
                '1' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Service with this name already exists.</label>',
                '2' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Image upload error.</label>',
                '3' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Please fill all fields.</label>',
                '4' => "",
                '0' => '',
            );

            if ($error_1 != '4') {
                echo '
                <div id="popup1" class="overlay">
                        <div class="popup">
                        <center>
                            <a class="close" href="settings.php">&times;</a> 
                            <div style="display: flex;justify-content: center;">
                            <div class="abc">
                            <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                            <tr>
                                    <td class="label-td" colspan="2">' . $errorlist[$error_1] . '</td>
                                </tr>
                                <tr>
                                    <td>
                                        <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">Edit Service Details</p>
                                        Service ID: ' . $id . '<br><br>
                                    </td>
                                </tr>
                                <tr>
                                    <form action="edit-service.php" method="POST" class="add-new-form" enctype="multipart/form-data">
                                    <input type="hidden" name="id" value="' . $id . '">
                                    <td class="label-td" colspan="2">
                                        <label for="name" class="form-label">Service Name: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="name" class="input-text" placeholder="Service Name" value="' . $name . '" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="description" class="form-label">Description: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <textarea name="description" class="input-text" placeholder="Service Description" rows="4" required>' . $desc . '</textarea><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="image" class="form-label">Current Image: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <img src="../' . $image . '" style="width: 100px; height: auto; margin-bottom: 10px;"><br>
                                        <label for="image" class="form-label">Change Image (leave blank to keep current): </label>
                                        <input type="file" name="image" class="input-text" accept="image/*"><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="display:flex;">
                                        <input type="reset" value="Reset" class="new-action-btn">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                        <input type="submit" value="Save Changes" class="new-action-btn" style="width: 300px;">
                                    </td>
                                </tr>
                                </form>
                                </tr>
                            </table>
                            </div>
                            </div>
                        </center>
                        <br><br>
                </div>
                </div>
                ';
            } else {
                echo '
                <div id="popup1" class="overlay">
                        <div class="popup">
                        <center>
                        <br><br><br><br>
                            <h2>Changes Saved Successfully!</h2>
                            <a class="close" href="settings.php">&times;</a>
                            <div class="content" style="height: 0px;">
                            </div>
                            <div style="display: flex;justify-content: center;">
                            <a href="settings.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;OK&nbsp;&nbsp;</font></button></a>
                            </div>
                            <br><br>
                        </center>
                </div>
                </div>
                ';
            }
        } elseif ($action == 'edit_clinic') {
            $field = $_GET['field'];
            $clinic_info = $database->query("SELECT * FROM clinic_info WHERE id=1")->fetch_assoc();
            $current_value = $clinic_info[$field];
            
            $field_labels = [
                'clinic_name' => 'Clinic Name',
                'clinic_description' => 'Clinic Description',
                'address' => 'Address',
                'phone' => 'Phone Number',
                'email' => 'Email Address',
                'facebook_url' => 'Facebook Link',
                'instagram_url' => 'Instagram Link'
            ];
            
            $input_type = 'text';
            if (in_array($field, ['facebook_url', 'instagram_url'])) $input_type = 'url';
            if ($field == 'email') $input_type = 'email';
            if ($field == 'phone') $input_type = 'tel';
            if ($field == 'clinic_description') $input_type = 'textarea';
            
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup edit-clinic-popup">
                    <center>
                        <h2>Edit '.$field_labels[$field].'</h2>
                        <a class="close" href="settings.php">&times;</a>
                        <div class="content">
                            <form action="update-clinic-info.php" method="POST">
                                <input type="hidden" name="field" value="'.$field.'">';
                            
            if ($input_type == 'textarea') {
                echo '<textarea name="value" class="input-text" required>'.htmlspecialchars($current_value).'</textarea>';
            } else {
                echo '<input type="'.$input_type.'" name="value" class="input-text" value="'.htmlspecialchars($current_value).'" required>';
            }
            
            echo '              <br><br>
                                <input type="submit" value="Save Changes" class="login-btn btn-primary btn">
                            </form>
                        </div>
                    </center>
            </div>
            </div>
            ';
        } elseif ($action == 'add_procedure') {
            $error_1 = $_GET["error"];
            $errorlist = array(
                '1' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Procedure with this name already exists.</label>',
                '2' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Please fill all fields.</label>',
                '3' => "",
                '0' => '',
            );

            if ($error_1 != '3') {
                echo '
                <div id="popup1" class="overlay">
                        <div class="popup procedures-popup">
                        <center>
                            <h2>Add New Procedure</h2>
                            <a class="close" href="settings.php">&times;</a>
                            <div class="content" style="height: 400px; width: 300px;">
                                ' . $errorlist[$error_1] . '
                                <form action="add-procedure.php" method="POST">
                                    <div class="form-group">
                                        <label for="name">Procedure Name:</label>
                                        <input type="text" name="name" class="input-text" placeholder="Procedure Name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="description">Description:</label>
                                        <textarea name="description" class="input-text" placeholder="Procedure Description" rows="4" required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="price">Price (PHP):</label>
                                        <input type="text" name="price" class="input-text" placeholder="0.00">
                                    </div>
                                    <div class="form-actions" style="display:flex;">
                                        <input type="reset" value="Reset" class="new-action-btn">
                                        <input type="submit" value="Add Procedure" class="new-action-btn" style="width: 140px;">
                                    </div>
                                </form>
                            </div>
                        </center>
                </div>
                </div>
                ';
            } else {
                echo '
                <div id="popup1" class="overlay">
                        <div class="popup success-popup">
                        <center>
                        <br><br><br><br>
                            <h2>Procedure Added Successfully!</h2>
                            <a class="close" href="settings.php">&times;</a>
                            <div class="content" style="height: 0px; width: 300px;">
                            </div>
                            <div style="display: flex;justify-content: center;">
                            <a href="settings.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;OK&nbsp;&nbsp;</font></button></a>
                            </div>
                            <br><br>
                        </center>
                </div>
                </div>
                ';
            }
        } elseif ($action == 'edit_procedure') {
            $sqlmain = "SELECT * FROM procedures WHERE procedure_id='$id'";
            $result = $database->query($sqlmain);
            $row = $result->fetch_assoc();
            $name = $row["procedure_name"];
            $desc = $row["description"];

            $error_1 = $_GET["error"];
            $errorlist = array(
                '1' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Procedure with this name already exists.</label>',
                '2' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Please fill all fields.</label>',
                '3' => "",
                '0' => '',
            );

            if ($error_1 != '3') {
                echo '
                <div id="popup1" class="overlay">
                        <div class="popup procedures-popup">
                        <center>
                            <h2>Edit Procedure</h2>
                            <a class="close" href="settings.php">&times;</a>
                            <div class="content" style="height: 400px; width: 300px;">
                                ' . $errorlist[$error_1] . '
                                <form action="edit-procedure.php" method="POST">
                                    <input type="hidden" name="id" value="' . $id . '">
                                    <div class="form-group">
                                        <label for="name">Procedure Name:</label>
                                        <input type="text" name="name" class="input-text" placeholder="Procedure Name" value="' . $name . '" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="description">Description:</label>
                                        <textarea name="description" class="input-text" placeholder="Procedure Description" rows="4" required>' . $desc . '</textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="price">Price (PHP):</label>
                                        <input type="text" name="price" class="input-text" placeholder="0.00" value="' . (isset($row['price']) ? number_format((float)$row['price'],2) : '') . '">
                                    </div>
                                    <div class="form-actions">
                                        <input type="reset" value="Reset" class="login-btn btn-primary-soft btn">
                                        <input type="submit" value="Save Changes" class="login-btn btn-primary btn">
                                    </div>
                                </form>
                            </div>
                        </center>
                </div>
                </div>
                ';
            } else {
                echo '
                <div id="popup1" class="overlay">
                        <div class="popup success-popup">
                        <center>
                        <br><br><br><br>
                            <h2>Changes Saved Successfully!</h2>
                            <a class="close" href="settings.php">&times;</a>
                            <div class="content" style="height: 0px; width: 300px;">
                            </div>
                            <div style="display: flex;justify-content: center;">
                            <a href="settings.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;OK&nbsp;&nbsp;</font></button></a>
                            </div>
                            <br><br>
                        </center>
                </div>
                </div>
                ';
            }
        } elseif ($action == 'drop_procedure') {
            $nameget = $_GET["name"];
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                        <h2>Are you sure?</h2>
                        <a class="close" href="settings.php">&times;</a>
                        <div class="content" style="height: 0px;">
                            You want to delete this procedure<br>(' . substr($nameget, 0, 40) . ').
                        </div>
                        <div style="display: flex;justify-content: center;">
                        <a href="delete-procedure.php?id=' . $id . '" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"<font class="tn-in-text">&nbsp;Yes&nbsp;</font></button></a>&nbsp;&nbsp;&nbsp;
                        <a href="settings.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;No&nbsp;&nbsp;</font></button></a>
                        </div>
                    </center>
            </div>
            </div>
            ';
        } elseif ($action == 'add_branch') {
            $error = isset($_GET['error']) ? $_GET['error'] : 0;
            $errorlist = array(
                '1' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Branch with this name already exists.</label>',
                '2' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Please fill all fields.</label>',
                '0' => ''
            );

            echo '<div id="popup1" class="overlay"><div class="popup"><center><a class="close" href="settings.php">&times;</a><div style="display:flex;justify-content:center;"><div class="abc"><table width="80%" class="sub-table" border="0"><tr><td colspan="2">'. $errorlist[$error] .'</td></tr><tr><td colspan="2"><p style="font-size:22px">Add New Branch</p></td></tr><tr><form action="add-branch.php" method="POST"><td colspan="2"><label for="name">Branch Name:</label><br><input type="text" name="name" class="input-text" required></td></tr><tr><td colspan="2"><label for="address">Address:</label><br><textarea name="address" class="input-text" rows="3"></textarea></td></tr><tr><td colspan="2"><input type="reset" value="Reset" class="new-action-btn"> <input type="submit" value="Add Branch" class="new-action-btn"></td></tr></form></table></div></div></center></div></div>';
        } elseif ($action == 'edit_branch') {
            $bid = intval($_GET['id']);
            $branch = $database->query("SELECT * FROM branches WHERE id='$bid'")->fetch_assoc();
            $bname = htmlspecialchars($branch['name']);
            $baddr = htmlspecialchars($branch['address']);
            echo '<div id="popup1" class="overlay"><div class="popup"><center><a class="close" href="settings.php">&times;</a><div style="display:flex;justify-content:center;"><div class="abc"><table width="80%" class="sub-table" border="0"><tr><td colspan="2"><p style="font-size:22px">Edit Branch</p></td></tr><tr><form action="edit-branch.php" method="POST"><input type="hidden" name="id" value="'.$bid.'"><td colspan="2"><label for="name">Branch Name:</label><br><input type="text" name="name" class="input-text" value="'.$bname.'" required></td></tr><tr><td colspan="2"><label for="address">Address:</label><br><textarea name="address" class="input-text" rows="3">'.$baddr.'</textarea></td></tr><tr><td colspan="2"><input type="reset" value="Reset" class="new-action-btn"> <input type="submit" value="Save Changes" class="new-action-btn"></td></tr></form></table></div></div></center></div></div>';
        } elseif ($action == 'drop_branch') {
            $nameget = isset($_GET['name']) ? urldecode($_GET['name']) : '';
            $bid = intval($_GET['id']);
            echo '<div id="popup1" class="overlay"><div class="popup"><center><h2>Are you sure?</h2><a class="close" href="settings.php">&times;</a><div class="content" style="height: 0px;">You want to delete this branch<br>(' . substr($nameget, 0, 40) . ').</div><div style="display: flex;justify-content: center;"><a href="delete-branch.php?id=' . $bid . '" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"<font class="tn-in-text">&nbsp;Yes&nbsp;</font></button></a>&nbsp;&nbsp;&nbsp;<a href="settings.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;No&nbsp;&nbsp;</font></button></a></div></center></div></div>';
        }
    }
    ?>

    <script>
        // Function to clear search and redirect
        function clearSearch() {
            window.location.href = 'settings.php';
        }

        // Tab functionality
        function openTab(tabId) {
            // Hide all tab contents
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Remove active class from all tabs
            const tabs = document.getElementsByClassName('tab');
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Show the selected tab content and mark tab as active
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
            
            // Update URL without reloading
            const stateObj = { tab: tabId };
            const title = 'Settings - ' + tabId.replace('-tab', '');
            const url = 'settings.php?tab=' + tabId.replace('-tab', '');
            history.pushState(stateObj, title, url);
        }
        
        // Check for tab parameter on page load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam) {
                const tabId = tabParam + '-tab';
                const tabElement = document.getElementById(tabId);
                
                if (tabElement) {
                    // Hide all tabs first
                    const tabContents = document.getElementsByClassName('tab-content');
                    for (let i = 0; i < tabContents.length; i++) {
                        tabContents[i].classList.remove('active');
                    }
                    
                    // Remove active class from all tab buttons
                    const tabs = document.getElementsByClassName('tab');
                    for (let i = 0; i < tabs.length; i++) {
                        tabs[i].classList.remove('active');
                    }
                    
                    // Activate the requested tab
                    tabElement.classList.add('active');
                    
                    // Find and activate the corresponding tab button
                    const tabButtons = document.getElementsByClassName('tab');
                    for (let i = 0; i < tabButtons.length; i++) {
                        if (tabButtons[i].getAttribute('onclick').includes(tabId)) {
                            tabButtons[i].classList.add('active');
                            break;
                        }
                    }
                }
            }
            
            // Search input event listener
            const searchInput = document.getElementById('searchInput');
            const clearBtn = document.querySelector('.clear-btn');

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    clearSearch();
                });
            }
        });

        // Show popup if URL has any action parameter
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');

            if (action === 'view' || action === 'edit' || action === 'drop' || action === 'add' || action === 'edit_clinic' || action === 'edit_procedure' || action === 'drop_procedure' || action === 'add_procedure' || action === 'add_branch' || action === 'edit_branch' || action === 'drop_branch') {
                const popup = document.getElementById('popup1');
                if (popup) {
                    popup.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
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
                        url.searchParams.delete('field');
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
                        url.searchParams.delete('field');
                        history.pushState(null, '', url);
                    }
                });
            });
        });
    </script>
    
</body>
</html>
