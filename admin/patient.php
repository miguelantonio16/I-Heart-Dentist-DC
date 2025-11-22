<?php
date_default_timezone_set('Asia/Singapore');
session_start();

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

// Load branches for filters
$branchesResult = $database->query("SELECT id, name FROM branches ORDER BY name ASC");


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
// Branch filter
$selected_branch = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
// Show inactive toggle
$show_inactive = isset($_GET['show']) && $_GET['show'] === 'inactive';

$search = "";
$sort_param = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$sort_order = ($sort_param === 'oldest') ? 'DESC' : 'ASC';

if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $query = "SELECT patient.*, b.name AS branch_name FROM patient LEFT JOIN branches b ON patient.branch_id = b.id WHERE patient.status='active' AND (patient.pname LIKE '%$search%' OR patient.pemail LIKE '%$search%' OR patient.ptel LIKE '%$search%')";
    if ($selected_branch > 0) {
        $query .= " AND patient.branch_id = $selected_branch";
    }
    $query .= " ORDER BY patient.pname $sort_order LIMIT $start_from, $results_per_page";

    $count_query = "SELECT COUNT(*) as total FROM patient WHERE status='active' AND (pname LIKE '%$search%' OR pemail LIKE '%$search%' OR ptel LIKE '%$search%')";
    if ($selected_branch > 0) {
        $count_query .= " AND branch_id = $selected_branch";
    }
} else {
    $status_filter = $show_inactive ? "inactive" : "active";
    $query = "SELECT patient.*, b.name AS branch_name FROM patient LEFT JOIN branches b ON patient.branch_id = b.id WHERE patient.status='$status_filter'";
    if ($selected_branch > 0) {
        $query .= " AND patient.branch_id = $selected_branch";
    }
    $query .= " ORDER BY patient.pname $sort_order LIMIT $start_from, $results_per_page";

    $count_query = "SELECT COUNT(*) as total FROM patient WHERE status='$status_filter'";
    if ($selected_branch > 0) {
        $count_query .= " AND branch_id = $selected_branch";
    }
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

if (isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['id'])) {
    $id = $_GET['id'];

    // Fetch patient details from the database
    $sqlmain = "SELECT * FROM patient WHERE pid = ?";
    $stmt = $database->prepare($sqlmain);
    $stmt->bind_param("i", $id); // 'i' means the parameter is an integer
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $name = $row["pname"];
        $email = $row["pemail"];
        $dob = $row["pdob"];
        $tel = $row["ptel"];
        $address = $row["paddress"]; // Assuming address exists in the database
        include_once __DIR__ . '/../inc/get_profile_pic.php';
        $profile_pic = get_profile_pic($row); // Get normalized profile picture path (no leading ../)

        // Render the popup
        echo '
        <div id="popup1" class="overlay">
            <div class="popup1"">
                <center>
                    <a class="close" href="patient.php">&times;</a>
                    <div style="display: flex;justify-content: center;">
                        <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                            <tr>
                                <td class="label-td" colspan="2">
                                    <img src="../' . $profile_pic . '" alt="Patient Photo"
                                        style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 20px;">
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">View Details</p><br><br>
                                </td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2"><label for="name" class="form-label">Patient ID: </label></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">P-' . $id . '<br><br></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2"><label for="name" class="form-label">Name: </label></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">' . $name . '<br><br></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2"><label for="Email" class="form-label">Email: </label></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">' . $email . '<br><br></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2"><label for="Tele" class="form-label">Telephone: </label></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">' . $tel . '<br><br></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2"><label for="spec" class="form-label">Address: </label></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">' . $address . '<br><br></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2"><label for="name" class="form-label">Date of Birth: </label></td>
                            </tr>
                            <tr>
                                <td class="label-td" colspan="2">' . $dob . '<br><br></td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="patient.php"><input type="button" value="OK" class="login-btn btn-primary-soft btn"></a>
                                </td>
                            </tr>
                        </table>
                    </div>
                </center>
            </div>
        </div>';
    } else {
        echo "<script>alert('Patient not found!');</script>";
        header("Location: patient.php");
        exit();
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Update status to 'inactive' instead of deleting (recommended for data integrity)
    $sql = "UPDATE patient SET status = 'inactive' WHERE pid = ?";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();
    
    if ($result) {
        // Redirect with success message
        header("Location: patient.php?status=deactivate_success");
        exit();
    } else {
        // Redirect with error message
        header("Location: patient.php?status=deactivate_error");
        exit();
    }
}

// Activate patient if requested
if (isset($_GET['action']) && $_GET['action'] == 'activate' && isset($_GET['id'])) {
    $id = $_GET['id'];

    $sql = "UPDATE patient SET status = 'active' WHERE pid = ?";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();

    if ($result) {
        header("Location: patient.php?status=activate_success");
        exit();
    } else {
        header("Location: patient.php?status=activate_error");
        exit();
    }
}

// Permanently delete patient (only available when viewing inactive patients)
if (isset($_GET['action']) && $_GET['action'] == 'destroy' && isset($_GET['id'])) {
    $id = $_GET['id'];

    // Delete related webuser row if present
    $delWebuser = $database->prepare("DELETE FROM webuser WHERE email = (SELECT pemail FROM patient WHERE pid = ?)");
    $delWebuser->bind_param("i", $id);
    $delWebuser->execute();

    // Delete the patient record
    $sql = "DELETE FROM patient WHERE pid = ?";
    $stmt = $database->prepare($sql);
    $stmt->bind_param("i", $id);
    $result = $stmt->execute();

    if ($result) {
        header("Location: patient.php?status=delete_success");
        exit();
    } else {
        header("Location: patient.php?status=delete_error");
        exit();
    }
}

// Initialize status message variables
$statusMessage = '';
$messageClass = '';

if (isset($_GET['status'])) {
    if ($_GET['status'] == 'deactivate_success') {
        $statusMessage = "Patient deactivated successfully.";
        $messageClass = "success-message";
    } elseif ($_GET['status'] == 'deactivate_error') {
        $statusMessage = "Failed to deactivate patient.";
        $messageClass = "error-message";
        } elseif ($_GET['status'] == 'activate_success') {
            $statusMessage = "Patient activated successfully.";
            $messageClass = "success-message";
        } elseif ($_GET['status'] == 'activate_error') {
            $statusMessage = "Failed to activate patient.";
            $messageClass = "error-message";
        } elseif ($_GET['status'] == 'delete_success') {
            $statusMessage = "Patient deleted permanently.";
            $messageClass = "success-message";
        } elseif ($_GET['status'] == 'delete_error') {
            $statusMessage = "Failed to delete patient.";
            $messageClass = "error-message";
    }
    
    if (!empty($statusMessage)) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                alert('$statusMessage');
                window.location.href = 'patient.php'; // Remove query params
            });
        </script>";
    }
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
    <link rel="stylesheet" href="../css/table.css">
    <title>Patient - IHeartDentistDC</title>
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">

    <style>
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
            z-index: 999;
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
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        .view-btn {
            width: 120px;
        }
        .popup1 {
            background-color: white;
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
                <a href="patient.php" class="nav-item active">
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
                <?php include('inc/sidebar-toggle.php'); ?>
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
                        <h3 class="announcements-title">Manage Patients</h3>
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
                            
                            <!-- Branch filter -->
                            <form method="GET" style="display:inline-block; margin-left:12px;">
                                <input type="hidden" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($currentSort); ?>">
                                <select name="branch_id" onchange="this.form.submit()" class="input-text" style="padding:6px; margin-left:8px;">
                                    <option value="">All Branches</option>
                                    <?php if ($branchesResult && $branchesResult->num_rows > 0): ?>
                                        <?php while ($b = $branchesResult->fetch_assoc()): ?>
                                            <option value="<?php echo $b['id']; ?>" <?php echo ($selected_branch == $b['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </form>
                        </div>
                    </div>

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-container">
                            <div style="margin-bottom:12px; display:flex; gap:8px; align-items:center;">
                                <?php if ($show_inactive): ?>
                                    <a href="patient.php" class="btn btn-small">Show Active Patients</a>
                                <?php else: ?>
                                    <a href="patient.php?show=inactive" class="btn btn-small">Show Inactive Patients</a>
                                <?php endif; ?>
                            </div>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Profile</th>
                                        <th>Name</th>
                                        <th>Branch</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Date of Birth</th>
                                        <th>Status</th>
                                        <th>Actions</th>
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
                                            <td><div class="cell-text"><?php echo $row['pname']; ?></div></td>
                                            <td><div class="cell-text"><?php echo isset($row['branch_name']) ? htmlspecialchars($row['branch_name']) : '-'; ?></div></td>
                                            <td><div class="cell-text"><?php echo $row['pemail']; ?></div></td>
                                            <td><div class="cell-text"><?php echo $row['ptel']; ?></div></td>
                                            <td><div class="cell-text"><?php echo $row['pdob']; ?></div></td>
                                            <td><div class="cell-text"><?php echo ucfirst($row['status']); ?></div></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="?action=view&id=<?php echo $row['pid']; ?>" class="action-btn view-btn">View</a>
                                                                     <?php if ($show_inactive): ?>
                                                                          <a href="?action=activate&id=<?php echo $row['pid']; ?>" 
                                                                              class="action-btn add-btn" 
                                                                              onclick="return confirm('Are you sure you want to activate this patient?')">Activate</a>
                                                                          <a href="?action=destroy&id=<?php echo $row['pid']; ?>" 
                                                                              class="action-btn remove-btn" 
                                                                              onclick="return confirm('This will permanently delete the patient and cannot be undone. Continue?')">Delete</a>
                                                                     <?php else: ?>
                                                                          <a href="?action=delete&id=<?php echo $row['pid']; ?>&name=<?php echo urlencode($row['pname']); ?>" 
                                                                              class="action-btn remove-btn" 
                                                                              onclick="return confirm('Are you sure you want to deactivate this patient?')">Deactivate</a>
                                                                     <?php endif; ?>
                                                                    <a href="assign_branch.php?kind=patient&id=<?php echo $row['pid']; ?>" class="action-btn assign-branch-link">Assign Branch</a>
                                                </div>
                                            </td>
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
                            <p>No patient found. Please try a different search term.</p>
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

            <script>
            // Assign Branch modal opener (shared behavior)
            document.addEventListener('click', function(e){
                var link = e.target.closest && e.target.closest('.assign-branch-link');
                if (!link) return;
                e.preventDefault();
                var url = link.getAttribute('href');
                if (!url) return;
                if (url.indexOf('?') === -1) url += '?ajax=1'; else url += '&ajax=1';
                var overlay = document.getElementById('assign-branch-overlay');
                if (overlay) overlay.remove();
                overlay = document.createElement('div');
                overlay.id = 'assign-branch-overlay';
                overlay.style.position = 'fixed'; overlay.style.left = 0; overlay.style.top = 0; overlay.style.right = 0; overlay.style.bottom = 0;
                overlay.style.background = 'rgba(0,0,0,0.5)'; overlay.style.zIndex = 9999; overlay.style.display = 'flex'; overlay.style.alignItems = 'center'; overlay.style.justifyContent = 'center';
                overlay.addEventListener('click', function(ev){ if (ev.target === overlay) overlay.remove(); });
                document.body.appendChild(overlay);
                var loader = document.createElement('div'); loader.textContent = 'Loading...'; loader.style.color = '#fff'; overlay.appendChild(loader);
                fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){ return r.text(); }).then(function(html){
                    overlay.innerHTML = '<div style="max-width:1000px;width:95%;">' + html + '</div>';
                    var form = overlay.querySelector('form');
                    if (form) {
                        form.addEventListener('submit', function(ev){
                            ev.preventDefault();
                            var btn = form.querySelector('button[type=submit]'); if (btn) btn.disabled = true;
                            var fd = new FormData(form);
                            fetch(form.getAttribute('action') || 'assign_branch_action.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(function(r){ return r.json(); })
                            .then(function(json){ if (btn) btn.disabled = false; if (json && json.status) { overlay.remove(); location.reload(); } else { alert(json.msg || 'Failed to save'); } })
                            .catch(function(){ if (btn) btn.disabled = false; alert('Network error'); });
                        });
                    }
                    var cancel = overlay.querySelector('#assign-branch-cancel'); if (cancel) cancel.addEventListener('click', function(){ overlay.remove(); });
                }).catch(function(){ overlay.innerHTML = '<div style="color:#fff;padding:20px">Failed to load</div>'; });
            });
            </script>

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

    <script>
        function clearSearch() {
            window.location.href = 'patient.php';
        }

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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Show popup if URL has any action parameter
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');

            if (action === 'view' || action === 'edit' || action === 'drop' || action === 'add') {
                const popup = document.getElementById('popup1');
                if (popup) {
                    popup.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
            }

            const closeButtons = document.querySelectorAll('.close');
            closeButtons.forEach(button => {
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    const overlay = this.closest('.overlay');
                    if (overlay) {
                        overlay.style.display = 'none';
                        document.body.style.overflow = '';

                        const url = new URL(window.location);
                        url.searchParams.delete('action');
                        url.searchParams.delete('id');
                        url.searchParams.delete('name');
                        url.searchParams.delete('error');
                        window.location.href = url.toString(); // This will reload the page
                    }
                });
            });

            const overlays = document.querySelectorAll('.overlay');
            overlays.forEach(overlay => {
                overlay.addEventListener('click', function (e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                        document.body.style.overflow = '';
                        // Remove the parameters from URL and reload
                        const url = new URL(window.location);
                        url.searchParams.delete('action');
                        url.searchParams.delete('id');
                        url.searchParams.delete('name');
                        url.searchParams.delete('error');
                        window.location.href = url.toString(); // This will reload the page
                    }
                });
            });
        });
    </script>
</body>

</html>
