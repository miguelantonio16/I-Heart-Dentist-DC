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
    <title>Dentist - IHeartDentistDC</title>
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">

    <?php
    date_default_timezone_set('Asia/Singapore');
    session_start();

    if (isset($_SESSION["user"])) {
        if (($_SESSION["user"]) == "" or $_SESSION['usertype'] != 'a') {
            header("location: login.php");
        }
    } else {
        header("location: login.php");
    }

    include("../connection.php");

    // Load branches for selection, respecting any branch restriction set on session
    $restrictedBranchId = isset($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id'] ? (int)$_SESSION['restricted_branch_id'] : 0;
    if ($restrictedBranchId > 0) {
        $branches_result = $database->query("SELECT * FROM branches WHERE id = " . $restrictedBranchId . " ORDER BY name ASC");
    } else {
        $branches_result = $database->query("SELECT * FROM branches ORDER BY name ASC");
    }
    $branches = [];
    while ($b = $branches_result->fetch_assoc()) { $branches[] = $b; }

    // Ensure CSRF token exists for admin actions
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Get totals for right sidebar (respect branch restriction when set)
    if ($restrictedBranchId > 0) {
        $doctorrow = $database->query("SELECT * FROM doctor WHERE status='active' AND (branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId));");
        $patientrow = $database->query("SELECT * FROM patient WHERE status='active' AND branch_id = $restrictedBranchId;");
        $appointmentrow = $database->query("SELECT * FROM appointment WHERE status='booking' AND docid IN (SELECT docid FROM doctor WHERE branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId));");
        $schedulerow = $database->query("SELECT * FROM appointment WHERE status='appointment' AND docid IN (SELECT docid FROM doctor WHERE branch_id = $restrictedBranchId OR docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId));");
    } else {
        $doctorrow = $database->query("select * from doctor where status='active';");
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

    // Calculate the starting limit for SQL
    $start_from = ($page - 1) * $results_per_page;

    // Search functionality and status filter
    $search = "";
    $sort_param = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    $sort_order = ($sort_param === 'oldest') ? 'DESC' : 'ASC';

    // Status filter: all / active / inactive. Default to 'active' to match previous behavior.
    $allowed_status = ['all', 'active', 'inactive'];
    $status = isset($_GET['status']) ? $_GET['status'] : 'active';
    if (!in_array($status, $allowed_status)) { $status = 'all'; }

    // Build optional status condition
    $status_condition = '';
    if ($status === 'active') {
        $status_condition = "doctor.status='active'";
    } elseif ($status === 'inactive') {
        $status_condition = "doctor.status='inactive'";
    }

    // Branch filter: if session restricts branch, force to that branch; else allow optional selection
    $branch = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
    if ($restrictedBranchId > 0) {
        $branch = $restrictedBranchId; // override any provided branch when restricted
    }
    $branch_condition = '';
    if ($branch > 0) {
        // Supports both legacy doctor.branch_id and many-to-many doctor_branches mapping
        $branch_condition = "(doctor.docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$branch) OR doctor.branch_id = $branch)";
    }

    if (isset($_GET['search'])) {
        // escape search input to prevent SQL injection
        $search = $database->real_escape_string($_GET['search']);

        if ($status_condition !== '') {
            $where = "$status_condition AND (doctor.docname LIKE '%$search%' OR doctor.docemail LIKE '%$search%' OR doctor.doctel LIKE '%$search%')";
            if ($branch_condition !== '') $where .= " AND $branch_condition";
            $query = "SELECT doctor.*, b.name AS branch_name FROM doctor LEFT JOIN branches b ON doctor.branch_id = b.id WHERE $where ORDER BY doctor.docname $sort_order LIMIT $start_from, $results_per_page";
            $count_query = "SELECT COUNT(*) as total FROM doctor WHERE $where";
        } else {
            $where = "(doctor.docname LIKE '%$search%' OR doctor.docemail LIKE '%$search%' OR doctor.doctel LIKE '%$search%')";
            if ($branch_condition !== '') $where .= " AND $branch_condition";
            $query = "SELECT doctor.*, b.name AS branch_name FROM doctor LEFT JOIN branches b ON doctor.branch_id = b.id WHERE $where ORDER BY doctor.docname $sort_order LIMIT $start_from, $results_per_page";
            $count_query = "SELECT COUNT(*) as total FROM doctor WHERE $where";
        }
    } else {
        if ($status_condition !== '') {
            $where = $status_condition;
            if ($branch_condition !== '') $where .= " AND $branch_condition";
            $query = "SELECT doctor.*, b.name AS branch_name FROM doctor LEFT JOIN branches b ON doctor.branch_id = b.id WHERE $where ORDER BY doctor.docname $sort_order LIMIT $start_from, $results_per_page";
            $count_query = "SELECT COUNT(*) as total FROM doctor WHERE $where";
        } else {
            // no status filter here — show both active/inactive
            $where = '1=1';
            if ($branch_condition !== '') $where = $branch_condition;
            $query = "SELECT doctor.*, b.name AS branch_name FROM doctor LEFT JOIN branches b ON doctor.branch_id = b.id WHERE $where ORDER BY doctor.docname $sort_order LIMIT $start_from, $results_per_page";
            $count_query = "SELECT COUNT(*) as total FROM doctor WHERE $where";
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
    ?>
    
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
                box-sizing: border-box;
                padding: 10px; /* reduced spacing so popup has more room */
            }

            .popup {
                background-color: white;
                padding: 18px 22px; /* slightly larger for more breathing room */
                border-radius: 10px;
                width: auto;
                max-width: 740px; /* increase size a bit as requested */
                /* let the popup size to its content by default so no internal scroll */
                max-height: none;
                overflow-y: visible;
                box-shadow: 0 0 20px rgba(0,0,0,0.3);
                position: relative;
            }

            /* Limit inner content width so tables don't stretch the popup */
            .popup table, .popup .abc, .popup .content, .popup .add-doc-form-container {
                width: auto !important;
                max-width: 640px;
                margin: 0 auto;
                box-sizing: border-box;
            }

            /* On very short viewports enable internal scrolling to avoid overflow */
            @media (max-height: 600px) {
                .popup {
                    max-height: calc(100vh - 20px);
                    overflow-y: auto;
                }
            }

            /* Wrapper for AJAX-loaded assign-branch modal: adaptive to content */
            .assign-modal-wrapper {
                display: inline-block;       /* size to content */
                width: auto;
                min-width: 320px;            /* don't get too small */
                max-width: 90vw;             /* cap to viewport width */
                max-height: 85vh;           /* cap to viewport height */
                overflow: auto;             /* scroll only if content larger than caps */
                margin: 0 auto;
                padding: 12px 14px;
                box-sizing: border-box;
                vertical-align: middle;
                background: transparent;    /* content provides its own background */
            }

            /* Ensure buttons and general controls inside the assign modal look good */
            .assign-modal-wrapper .btn-primary.btn {
                padding: 8px 14px;
                min-width: 56px;
                border-radius: 6px;
                font-size: 14px;
            }

            /* Make the assign form layout adaptive: checkboxes flow horizontally */
            .assign-modal-wrapper form {
                display: flex;
                flex-wrap: wrap;
                gap: 8px 18px;
                align-items: center;
                margin: 0;
            }

            /* Make sure long content wraps nicely */
            .assign-modal-wrapper label, .assign-modal-wrapper .form-label {
                word-break: break-word;
            }

            /* Each checkbox wrapper should size to its content */
            .assign-modal-wrapper .content form > div {
                flex: 0 0 auto;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                margin: 0;
                padding: 4px 6px;
            }

            .assign-modal-wrapper .content form > div label {
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            /* Make the last div (action buttons) span full width and center its children */
            .assign-modal-wrapper .content form > div:last-child {
                width: 100%;
                display: flex !important;
                justify-content: center !important;
                gap: 12px !important;
                margin-top: 12px !important;
            }

            /* Make sure forms and inputs align nicely inside the popup */
            .popup .input-text, .popup .form-label {
                max-width: 100%;
                box-sizing: border-box;
            }

            /* Popup header/title sizing */
            .popup h2 {
                font-size: 22px;
                margin: 6px 0 10px 0;
                text-align: center;
            }

            /* Ensure popup is positioned and close button is absolutely placed in corner */
            .popup {
                position: relative !important;
            }

            .popup .close {
                position: absolute !important;
                top: 10px !important;
                right: 12px !important;
                font-size: 22px !important;
                line-height: 1 !important;
                padding: 6px !important;
            }

            /* Add right padding to header so centered title doesn't collide with close */
            .popup h2 {
                padding-right: 40px;
            }

            /* Buttons inside popup - ensure sufficient padding and prevent text overlap */
            .popup .btn-primary.btn {
                display: inline-block;
                padding: 10px 16px;
                min-width: 56px;
                border-radius: 6px;
                font-size: 14px;
                line-height: 1;
                vertical-align: middle;
            }

            /* Ensure button container has spacing and centers buttons */
            .popup .content .btn-row {
                display: flex;
                gap: 12px;
                justify-content: center;
                align-items: center;
                margin-top: 8px;
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
        /* Pill-style activate button with left circular white icon */
        .activate-btn {
            background-color: #8fc5f7; /* light blue */
            color: #ffffff;
            border-radius: 999px;
            padding: 6px 12px; /* match other buttons */
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 13px;
            border: none;
            cursor: pointer;
            box-shadow: none;
            vertical-align: middle;
            min-height: 34px; /* ensure same height as other action buttons */
            height: auto;
            line-height: 1;
        }

        .activate-btn:hover {
            filter: brightness(0.97);
        }

        .activate-icon {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #ffffff;
            color: #6aa3df; /* slightly darker blue for the plus */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 11px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.06);
            flex: 0 0 auto;
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
    </style>
</head>

<body>
    <!-- Mobile hamburger for sidebar toggle -->
    <button class="hamburger-admin" id="hamburgerAdmin" aria-label="Toggle sidebar" aria-controls="adminSidebar" aria-expanded="false">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <!-- sidebar toggle removed to keep sidebar static -->

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
                <a href="dentist.php" class="nav-item active">
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
                <!-- Legacy sidebar-toggle removed; logo now acts as toggle -->
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
                        <h3 class="announcements-title">Manage Dentists</h3>
                        <div class="announcement-filters">
                            <?php
                            $currentSort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
                            $searchParam = isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '';
                            $currentStatus = isset($_GET['status']) ? $_GET['status'] : 'active';
                            $statusParam = '&status=' . urlencode($currentStatus);
                            $currentBranch = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
                            $branchParam = $currentBranch ? '&branch=' . $currentBranch : '';
                            ?>
                            <a href="?sort=newest<?php echo $statusParam . $searchParam . $branchParam; ?>"
                                class="filter-btn newest-btn <?php echo ($currentSort === 'newest' || $currentSort === '') ? 'active' : 'inactive'; ?>">
                                A-Z
                            </a>

                            <a href="?sort=oldest<?php echo $statusParam . $searchParam . $branchParam; ?>"
                                class="filter-btn oldest-btn <?php echo $currentSort === 'oldest' ? 'active' : 'inactive'; ?>">
                                Z-A
                            </a>
                            
                            <!-- Status filter: All / Active / Inactive -->
                            <a href="?status=all<?php echo $searchParam ? $searchParam : ''; echo $branchParam; ?>" class="filter-btn filter-all-btn <?php echo ($currentStatus === 'all') ? 'active' : 'inactive'; ?>">All</a>
                            <a href="?status=active<?php echo $searchParam ? $searchParam : ''; echo $branchParam; ?>" class="filter-btn filter-active-btn <?php echo ($currentStatus === 'active') ? 'active' : 'inactive'; ?>">Active</a>
                            <a href="?status=inactive<?php echo $searchParam ? $searchParam : ''; echo $branchParam; ?>" class="filter-btn filter-inactive-btn <?php echo ($currentStatus === 'inactive') ? 'active' : 'inactive'; ?>">Inactive</a>

                            <!-- Branch dropdown filter: placed beside Inactive -->
                            <form method="GET" style="display:inline-block; margin-left:8px; vertical-align: middle;">
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($currentSort); ?>">
                                <input type="hidden" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($currentStatus); ?>">
                                <select name="branch" class="filter-branch-select" onchange="this.form.submit()">
                                    <option value="">All Branches</option>
                                    <?php foreach ($branches as $br_opt): ?>
                                        <option value="<?php echo $br_opt['id']; ?>" <?php echo ($currentBranch == $br_opt['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($br_opt['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>

                            <a href="?action=add&id=none&error=0" class="filter-btn add-btn" style="margin-left:12px;">
                                Add New Dentist
                            </a>
                        </div>
                    </div>

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive"><div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Profile</th>
                                        <th>Name</th>
                                        <th>Branch</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                // Check if photo exists, use the correct path to admin/uploads
                                                if (!empty($row['photo'])) {
                                                    $photo = "uploads/" . $row['photo'];
                                                } else {
                                                    $photo = "../Media/Icon/Blue/dentist.png";
                                                }
                                                ?>
                                                <img src="<?php echo $photo; ?>" alt="<?php echo $row['docname']; ?>"
                                                    class="profile-img-small" onerror="this.onerror=null;this.src='../Media/Icon/Blue/dentist.png';">
                                            </td>
                                            <td><div class="cell-text"><?php echo $row['docname']; ?></div></td>
                                            <td>
                                                <div class="cell-text">
                                                <?php
                                                    $branches_list = [];
                                                    $brres = $database->query("SELECT b.name FROM doctor_branches db JOIN branches b ON db.branch_id=b.id WHERE db.docid='" . (int)$row['docid'] . "' ORDER BY b.name ASC");
                                                    if ($brres && $brres->num_rows) {
                                                        while ($b = $brres->fetch_assoc()) {
                                                            $clean = trim($b['name']);
                                                            if (!in_array($clean, $branches_list)) $branches_list[] = $clean;
                                                        }
                                                    } else {
                                                        // fallback to legacy single branch column
                                                        if (isset($row['branch_name']) && $row['branch_name'] !== null) {
                                                            $branches_list[] = $row['branch_name'];
                                                        }
                                                    }

                                                    echo !empty($branches_list) ? htmlspecialchars(implode(', ', $branches_list)) : '-';
                                                ?>
                                                </div>
                                            </td>
                                            <td><div class="cell-text"><?php echo $row['docemail']; ?></div></td>
                                            <td><div class="cell-text"><?php echo $row['doctel']; ?></div></td>
                                            <td>
                                            <div class="action-buttons">
                                                <a href="?action=edit&id=<?php echo $row['docid']; ?>&error=0" class="action-btn edit-btn">Edit</a>
                                                <?php if ($row['status'] === 'active'): ?>
                                                    <a href="?action=drop&id=<?php echo $row['docid']; ?>&name=<?php echo urlencode($row['docname']); ?>" class="action-btn remove-btn">Deactivate</a>
                                                <?php else: ?>
                                                    <form method="post" action="activate-dentist.php" onsubmit="return confirm('Activate this dentist?');" style="display:inline-block;margin:0;padding:0;">
                                                        <input type="hidden" name="id" value="<?php echo $row['docid']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <button type="submit" class="action-btn activate-btn" aria-label="Activate">
                                                            <span class="activate-icon">+</span>
                                                            <span>Activate</span>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if (empty($restrictedBranchId) || $restrictedBranchId == 0): ?>
                                                    <a href="assign_branch.php?kind=doctor&id=<?php echo $row['docid']; ?>" class="action-btn assign-branch-link">Assign Branch</a>
                                                <?php endif; ?>
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
                            $currentStatus = isset($_GET['status']) ? $_GET['status'] : 'active';
                            $statusParam = '&status=' . urlencode($currentStatus);
                            $currentBranch = isset($_GET['branch']) ? (int)$_GET['branch'] : 0;
                            $branchParam = $currentBranch ? '&branch=' . $currentBranch : '';

                            // Previous link
                            if ($page > 1) {
                                echo '<a href="?page=' . ($page - 1) . $searchParam . $sortParam . $statusParam . $branchParam . '">&laquo; Previous</a>';
                            }

                            // Page links
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<a href="?page=' . $i . $searchParam . $sortParam . $statusParam . $branchParam . '"' . ($i == $page ? ' class="active"' : '') . '>' . $i . '</a>';
                            }

                            // Next link
                            if ($page < $total_pages) {
                                echo '<a href="?page=' . ($page + 1) . $searchParam . $sortParam . $statusParam . $branchParam . '">Next &raquo;</a>';
                            }
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="no-results">
                            <p>No dentist found. Please try a different search term.</p>
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

                    <div class="upcoming-appointments">
                        <h3>Upcoming Appointments</h3>
                        <div class="appointments-content">
                            <?php
                            $branchScope = '';
                            if (isset($restrictedBranchId) && $restrictedBranchId > 0) {
                                $branchScope = " AND (doctor.branch_id = $restrictedBranchId OR doctor.docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))";
                            }
                            $upcomingAppointments = $database->query("SELECT
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

                            if ($upcomingAppointments && $upcomingAppointments->num_rows > 0) {
                                while ($appointment = $upcomingAppointments->fetch_assoc()) {
                                    $pname = htmlspecialchars($appointment['patient_name'] ?? '');
                                    $dname = htmlspecialchars($appointment['doctor_name'] ?? '');
                                    $proc = htmlspecialchars($appointment['procedure_name'] ?? '');
                                    $date_str = '';
                                    $time_str = '';
                                    if (!empty($appointment['appodate'])) {
                                        $date_str = htmlspecialchars(date('F j, Y', strtotime($appointment['appodate'])));
                                    }
                                    if (!empty($appointment['appointment_time'])) {
                                        $time_str = htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time'])));
                                    }

                                    $branch = htmlspecialchars($appointment['branch_name'] ?? '');
                                    echo '<div class="appointment-item">' .
                                        '<h4 class="appointment-type">' . $pname . '</h4>' .
                                        '<p class="appointment-dentist">With Dr. ' . $dname . '</p>' .
                                        '<p class="appointment-date">' . $proc . '</p>' .
                                        '<p class="appointment-date">' . $date_str . ($date_str && $time_str ? ' • ' : '') . $time_str . (($branch!=='') ? (' - ' . $branch) : '') . '</p>' .
                                    '</div>';
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
                        <a class="close" href="dentist.php">&times;</a>
                        <div class="content" style="display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 8px 12px;">
                            <div style="text-align:center;">You want to delete this record<br>(' . substr($nameget, 0, 40) . ').</div>
                           <div style="display:flex; justify-content: center; gap: 12px; margin-top: 6px;">
                            <form method="post" action="delete-dentist.php" style="display:inline-block;margin:0;">
                                <input type="hidden" name="id" value="' . $id . '">
                                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">
                                <button type="submit" class="btn-primary btn" style="margin:0;padding:8px 14px;">Yes</button>
                            </form>
                        <a href="dentist.php" class="non-style-link"><button  class="btn-primary btn"  style="margin:0;padding:8px 14px;">No</button></a>
                        </div>
                        </div>
                    </center>
            </div>
            </div>
            ';
        } elseif ($action == 'view') {
            $sqlmain = "select * from doctor where docid='$id'";
            $result = $database->query($sqlmain);
            $row = $result->fetch_assoc();
            $name = $row["docname"];
            $email = $row["docemail"];
            $tele = $row['doctel'];
            $photo = $row["photo"];
            $current_branch = isset($row['branch_id']) ? $row['branch_id'] : null;
            // build branch options with selection
            $branch_options_edit = "";
            foreach ($branches as $br) {
                $selected = ($current_branch == $br['id']) ? " selected" : "";
                $branch_options_edit .= "<option value='" . $br['id'] . "'" . $selected . ">" . htmlspecialchars($br['name']) . "</option>";
            }
            echo '
            <div id="popup1" class="overlay">
                <div class="popup">
                    <center>
                        <h2>Dentist Details</h2>
                        <a class="close" href="dentist.php">&times;</a>
                        <div class="content">
                            <table width="100%" class="sub-table scrolldown add-doc-form-container" border="0">
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <img src="uploads/' . $photo . '" alt="Dentist Photo"
                                            style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 20px;">
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" style="width: 30%;">
                                        <label for="name" class="form-label">Name:</label>
                                    </td>
                                    <td>' . htmlspecialchars($name) . '</td>
                                </tr>
                                <tr>
                                    <td class="label-td">
                                        <label for="Email" class="form-label">Email:</label>
                                    </td>
                                    <td>' . htmlspecialchars($email) . '</td>
                                </tr>
                                <tr>
                                    <td class="label-td">
                                        <label for="Tele" class="form-label">Telephone:</label>
                                    </td>
                                    <td>' . htmlspecialchars($tele) . '</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding-top: 20px;">
                                        <a href="dentist.php"><input type="button" value="OK" class="login-btn btn-primary-soft btn"></a>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </center>
                </div>
            </div>';
        } elseif ($action == 'add') {
            $error_1 = $_GET["error"];
            $errorlist = array(
                '1' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Already have an account for this Email address.</label>',
                '2' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Password Confirmation Error! Reconfirm Password</label>',
                '3' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;"></label>',
                '4' => "",
                '0' => '',
            );
            if ($error_1 != '4') {
                // build branch options HTML and preselect restricted branch when set
                $branch_options = "";
                foreach ($branches as $br) {
                    $selected_attr = (isset($restrictedBranchId) && $restrictedBranchId > 0 && (int)$br['id'] === (int)$restrictedBranchId) ? ' selected' : '';
                    $branch_options .= "<option value='" . $br['id'] . "'" . $selected_attr . ">" . htmlspecialchars($br['name']) . "</option>";
                }

                echo '
                <div id="popup1" class="overlay">
                        <div class="popup">
                        <center>
                       
                            <a class="close" href="dentist.php">&times;</a>
                            <div style="display: flex;justify-content: center;">
                            <div class="abc">
                            <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                            <tr>
                                    <td class="label-td" colspan="2">' .
                $errorlist[$error_1]
                . '</td>
                                </tr>
                                <tr>
                                    <td>
                                        <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">Add New Dentist</p><br><br>
                                    </td>
                                </tr>
                               
                                <tr>
                                    <form action="add-new.php" method="POST" class="add-new-form" enctype="multipart/form-data">
                                    <td class="label-td" colspan="2">
                                        <label for="name" class="form-label">Name: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="text" name="name" class="input-text" placeholder="Dentist Name" required><br>
                                    </td>
                                   
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="Email" class="form-label">Email: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="email" name="email" class="input-text" placeholder="Email Address" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="Tele" class="form-label">Telephone: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="tel" name="Tele" class="input-text" placeholder="Telephone Number" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="branch_id" class="form-label">Branch: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <select name="branch_id" class="input-text">
                                            <option value="">-- Select Branch --</option>' . $branch_options . '
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="photo" class="form-label">Photo: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="file" name="photo" class="input-text" accept="image/*" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="password" class="form-label">Password: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="password" name="password" class="input-text" placeholder="Create Password" required><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <label for="cpassword" class="form-label">Confirm Password: </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="label-td" colspan="2">
                                        <input type="password" name="cpassword" class="input-text" placeholder="Confirm Password" required><br>
                                    </td>
                                </tr>
                               
                   
                                <tr>
                                    <td colspan="2">
                                        <input type="reset" value="Reset" class="login-btn btn-primary-soft btn">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                   
                                        <input type="submit" value="Add" class="login-btn btn-primary btn">
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
                                <h2>New Record Added Successfully!</h2>
                                <a class="close" href="dentist.php">&times;</a>
                                <div class="content" style="height: 0px;">
                                   
                                   
                                </div>
                                <div style="display: flex;justify-content: center;">
                               
                                <a href="dentist.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;OK&nbsp;&nbsp;</font></button></a>


                                </div>
                                <br><br>
                            </center>
                    </div>
                    </div>
        ';
            }
        } elseif ($action == 'edit') {
            $sqlmain = "select * from doctor where docid='$id'";
            $result = $database->query($sqlmain);
            $row = $result->fetch_assoc();
            $name = $row["docname"];
            $email = $row["docemail"];
            $tele = $row['doctel'];
            $photo = $row["photo"];


            $error_1 = $_GET["error"];
            $errorlist = array(
                '1' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Already have an account for this Email address.</label>',
                '2' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Password Confirmation Error! Reconfirm Password</label>',
                '3' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;"></label>',
                '4' => "",
                '0' => '',
            );


            if ($error_1 != '4') {
                echo '
                    <div id="popup1" class="overlay">
                            <div class="popup">
                            <center>
                           
                                <a class="close" href="dentist.php">&times;</a>
                                <div style="display: flex;justify-content: center;">
                                <div class="abc">
                                <table width="80%" class="sub-table scrolldown add-doc-form-container" border="0">
                                <tr>
                                        <td class="label-td" colspan="2">' .
                $errorlist[$error_1]
                . '</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <p style="padding: 0;margin: 0;text-align: left;font-size: 25px;font-weight: 500;">Edit Dentist Details.</p>
                                        Dentist ID : ' . $id . ' (Auto Generated)<br><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <form action="edit-doc.php" method="POST" class="add-new-form" enctype="multipart/form-data">
                                            <label for="Email" class="form-label">Email: </label>
                                            <input type="hidden" value="' . $id . '" name="id00">
                                            <input type="hidden" name="oldemail" value="' . $email . '" >
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                        <input type="email" name="email" class="input-text" placeholder="Email Address" value="' . $email . '" required><br>
                                        </td>
                                    </tr>
                                    <tr>
                                       
                                        <td class="label-td" colspan="2">
                                            <label for="name" class="form-label">Name: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <input type="text" name="name" class="input-text" placeholder="Dentist Name" value="' . $name . '" required><br>
                                        </td>
                                       
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="Tele" class="form-label">Telephone: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <input type="tel" name="Tele" class="input-text" placeholder="Telephone Number" value="' . $tele . '" required><br>
                                        </td>
                                    </tr>
                                    
                                   <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="photo" class="form-label">Photo: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <input type="file" name="photo" class="input-text" accept="image/*"><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <label for="password" class="form-label">Password: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <input type="password" name="password" class="input-text" placeholder="Create Password"><br>
                                        </td>
                                    </tr><tr>
                                        <td class="label-td" colspan="2">
                                            <label for="cpassword" class="form-label">Confirm Password: </label>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="label-td" colspan="2">
                                            <input type="password" name="cpassword" class="input-text" placeholder="Confirm Password"><br>
                                        </td>
                                    </tr>
                                   
                       
                                    <tr>
                                        <td colspan="2" style="display: flex; justify-content: center;">
                                            <input type="reset" value="Reset" class="new-action-btn edit-btn">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                            <input type="submit" value="Save" class="new-action-btn edit-btn">
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
                            <h2>Edit Successfully!</h2>
                            <a class="close" href="dentist.php">&times;</a>
                            <div class="content" style="height: 0px;">
                               
                               
                            </div>
                            <div style="display: flex;justify-content: center;">
                           
                            <a href="dentist.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;OK&nbsp;&nbsp;</font></button></a>


                            </div>
                            <br><br>
                        </center>
                </div>
                </div>
    ';
            }
        }
    }
    ?>

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
        });
    </script>
    <script>
        // Mobile sidebar toggle for Dentist page
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
    <script>
    // Assign Branch modal opener
    document.addEventListener('click', function(e){
        var link = e.target.closest && e.target.closest('.assign-branch-link');
        if (!link) return;
        e.preventDefault();
        var url = link.getAttribute('href');
        if (!url) return;
        if (url.indexOf('?') === -1) url += '?ajax=1'; else url += '&ajax=1';
        // create overlay
        var overlay = document.getElementById('assign-branch-overlay');
        if (overlay) overlay.remove();
        overlay = document.createElement('div');
        overlay.id = 'assign-branch-overlay';
        overlay.style.position = 'fixed'; overlay.style.left = 0; overlay.style.top = 0; overlay.style.right = 0; overlay.style.bottom = 0;
        overlay.style.background = 'rgba(0,0,0,0.5)'; overlay.style.zIndex = 9999; overlay.style.display = 'flex'; overlay.style.alignItems = 'center'; overlay.style.justifyContent = 'center';
        overlay.addEventListener('click', function(ev){ if (ev.target === overlay) overlay.remove(); });
        document.body.appendChild(overlay);
        // loader
        var loader = document.createElement('div'); loader.textContent = 'Loading...'; loader.style.color = '#fff'; overlay.appendChild(loader);
        fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){ return r.text(); }).then(function(html){
            overlay.innerHTML = '<div class="assign-modal-wrapper">' + html + '</div>';
            // prevent background scrolling while modal is open
            document.body.style.overflow = 'hidden';
            // attach submit handler to any form inside the modal
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
            // attach cancel button
            var cancel = overlay.querySelector('#assign-branch-cancel');
            if (cancel) cancel.addEventListener('click', function(){ document.body.style.overflow = ''; overlay.remove(); });

            // attach close (X) button handler for AJAX-inserted content
            var closeAnch = overlay.querySelector('.popup .close');
            if (closeAnch) closeAnch.addEventListener('click', function(e){ e.preventDefault(); document.body.style.overflow = ''; overlay.remove(); });
        }).catch(function(){ overlay.innerHTML = '<div style="color:#fff;padding:20px">Failed to load</div>'; });
    });
    </script>
</body>

</html>
