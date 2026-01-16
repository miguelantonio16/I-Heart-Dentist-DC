
<?php 
session_start();

date_default_timezone_set('Asia/Singapore');

// Authenticate admin user before any output is sent
if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || !isset($_SESSION['usertype']) || $_SESSION['usertype'] != 'a') {
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}

// Import database connection early for any DB work
include(__DIR__ . "/../connection.php");

// Ensure per-branch clinic info table exists (safe to run on every page load)
$createBranchInfoTable = "CREATE TABLE IF NOT EXISTS branch_info (
        branch_id INT PRIMARY KEY,
        clinic_name TEXT,
        clinic_description TEXT,
        address TEXT,
        phone VARCHAR(50),
        email VARCHAR(150),
        facebook_url VARCHAR(255),
        instagram_url VARCHAR(255),
        map_embed_url TEXT,
        CONSTRAINT fk_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$database->query($createBranchInfoTable);

// Handle saving per-branch clinic info (upsert)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_branch_info'])) {
    // Use hidden branch_id from the form (the popup is for a specific branch)
    $bid = (int)($_POST['branch_id'] ?? 0);
    $clinic_name = $database->real_escape_string($_POST['clinic_name'] ?? '');
    $clinic_description = $database->real_escape_string($_POST['clinic_description'] ?? '');
    $address = $database->real_escape_string($_POST['address'] ?? '');
    $phone = $database->real_escape_string($_POST['phone'] ?? '');
    $email = $database->real_escape_string($_POST['email'] ?? '');
    $map_embed = $database->real_escape_string($_POST['map_embed_url'] ?? '');

    if ($bid > 0) {
        $sql = "INSERT INTO branch_info (branch_id, clinic_name, clinic_description, address, phone, email, map_embed_url)
            VALUES ($bid, '$clinic_name', '$clinic_description', '$address', '$phone', '$email', '$map_embed')
            ON DUPLICATE KEY UPDATE
            clinic_name=VALUES(clinic_name), clinic_description=VALUES(clinic_description), address=VALUES(address), phone=VALUES(phone), email=VALUES(email), map_embed_url=VALUES(map_embed_url)";

        // Log SQL and POST payload for debugging if admin save is failing for a branch
        error_log("[settings.php] Saving branch_info: branch_id={$bid} sql=" . $sql);
        error_log("[settings.php] POST payload: " . print_r($_POST, true));

        $res = $database->query($sql);
        if ($res === false) {
            error_log("[settings.php] DB error on branch_info save: " . $database->error);
            $_SESSION['flash_error'] = 'Failed to save branch info: ' . $database->error;
        } else {
            error_log("[settings.php] branch_info save affected_rows=" . $database->affected_rows);
        }
    } else {
        $_SESSION['flash_error'] = 'Invalid branch selected.';
    }

    // Redirect cleanly back to settings to avoid resubmission
    header('Location: settings.php');
    exit();
}

// Get totals for right sidebar
$doctorrow = $database->query("select * from doctor where status='active';");
$patientrow = $database->query("select * from patient where status='active';");
$appointmentrow = $database->query("select * from appointment where status='booking';");
$schedulerow = $database->query("select * from appointment where status='appointment';");

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
    <link rel="stylesheet" href="../css/responsive-admin.css">
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
            max-width: 580px;
            max-height: 80vh;
            box-sizing: border-box;
            overflow-y: auto;
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
            width: 100%;
            max-width: 520px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
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
    <button class="hamburger-admin" id="hamburgerAdmin" aria-label="Toggle sidebar" aria-controls="adminSidebar" aria-expanded="false">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <script>
        // Mobile sidebar toggle for Settings page
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
    
    <div class="main-container">
        <?php
        // Show any flash messages (success / error)
        if (isset($_SESSION['flash_success'])) {
            echo '<div style="margin:12px; padding:12px; background:#e6ffef; border:1px solid #b2f2c4; color:#006644; border-radius:6px;">' . htmlspecialchars($_SESSION['flash_success']) . '</div>';
            unset($_SESSION['flash_success']);
        }
        if (isset($_SESSION['flash_error'])) {
            echo '<div style="margin:12px; padding:12px; background:#fff1f0; border:1px solid #ffb3b3; color:#8a1f1f; border-radius:6px;">' . htmlspecialchars($_SESSION['flash_error']) . '</div>';
            unset($_SESSION['flash_error']);
        }
        ?>
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
                <!-- Legacy sidebar-toggle removed; logo now acts as toggle -->
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

                    // Do not allow removing the core 'Consultation' procedure (id 1 or name 'Consultation')
                    $isCoreConsultation = (intval($id) === 1) || (strtolower(trim($name)) === 'consultation');
                    $removeLink = $isCoreConsultation ? '' : '<a href="?action=drop_procedure&id='.$id.'&name='.$name.'" class="action-btn remove-btn">Remove</a>';

                    echo '<tr>
                        <td>'.$id.'</td>
                        <td><div class="cell-text">'.substr($name, 0, 30).'</div></td>
                        <td><div class="cell-text">'.substr($desc, 0, 50).'...</div></td>
                        <td>
                            <div class="action-buttons">
                                <a href="?action=edit_procedure&id='.$id.'&error=0" class="action-btn edit-btn">Edit</a>' . $removeLink . '
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
                                                // include branch_id param and data-bid to satisfy JS fallback which checks for branch_id
                                                . '<a href="?action=edit_branch&id='.$bid.'&branch_id='.$bid.'" class="action-btn edit-btn" data-bid="'.$bid.'">Edit</a>'
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
                                    // Show per-branch clinic information with edit buttons
                                    // Explicitly select branch fields and only the needed branch_info columns
                                    // Avoid using bi.* because it includes a `branch_id` column which can overwrite the aliased b.id (NULL when no row exists).
                                    $branch_list_sql = "SELECT b.id AS branch_id, b.name AS branch_name, b.address AS branch_address, 
                                                        bi.clinic_name AS clinic_name, bi.clinic_description AS clinic_description, bi.address AS bi_address, bi.phone AS phone, bi.email AS email, bi.facebook_url AS facebook_url, bi.instagram_url AS instagram_url, bi.map_embed_url AS map_embed_url
                                                        FROM branches b
                                                        LEFT JOIN branch_info bi ON bi.branch_id = b.id
                                                        ORDER BY b.id ASC";
                                    $branch_list = $database->query($branch_list_sql);
                                    if (!$branch_list || $branch_list->num_rows == 0) {
                                        echo '<tr><td colspan="3"><center>No branches found</center></td></tr>';
                                    } else {
                                        while ($br = $branch_list->fetch_assoc()) {
                                            $bid = (int)$br['branch_id'];
                                            $bname = htmlspecialchars($br['branch_name']);
                                            $baddr = htmlspecialchars($br['branch_address']);
                                            $cname = isset($br['clinic_name']) ? htmlspecialchars($br['clinic_name']) : '';
                                            $cphone = isset($br['phone']) ? htmlspecialchars($br['phone']) : '';
                                            $cemail = isset($br['email']) ? htmlspecialchars($br['email']) : '';
                                            $cdesc = isset($br['clinic_description']) ? htmlspecialchars($br['clinic_description']) : '';
                                            echo '<tr>';
                                            echo '<td><div class="cell-text">'. $bname .'</div></td>';
                                            echo '<td><div class="cell-text">'.(strlen($cname) ? substr($cname,0,50) : '<em>Not set</em>').'</div><div class="cell-text" style="font-size:12px;color:#666;">'.(strlen($cdesc) ? substr($cdesc,0,80) : '').'</div></td>';
                                            // Use explicit settings.php URL and include data-bid for JS fallback
                                            // Use a small GET form to reliably send branch_id when Edit is clicked
                                            echo '<td><div class="action-buttons">'
                                                . '<form method="GET" action="settings.php" style="display:inline;margin:0;">'
                                                . '<input type="hidden" name="action" value="edit_branch_info">'
                                                . '<input type="hidden" name="branch_id" value="' . $bid . '">'
                                                . '<button type="submit" class="action-btn edit-btn" data-bid="' . $bid . '" onclick="event.preventDefault(); window.location.href=\'settings.php?action=edit_branch_info&branch_id=' . $bid . '\';">Edit</button>'
                                                . '</form>'
                                                . '</div></td>';
                                            echo '</tr>';
                                        }
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
                                    AND appointment.appodate >= '$today'
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
        $id = isset($_GET["id"]) ? $_GET["id"] : null;
        $action = isset($_GET["action"]) ? $_GET["action"] : null;
        
        // If the drop_procedure flow was redirected with inactive=1, show a stronger
        // confirmation that allows permanently deleting the inactive procedure.
        if ($action === 'drop_procedure' && isset($_GET['inactive']) && $_GET['inactive'] == '1') {
            $nameget = isset($_GET['name']) ? $_GET['name'] : '';
            echo '
            <div id="popup1" class="overlay">
                    <div class="popup">
                    <center>
                        <h2>Permanently delete?</h2>
                        <a class="close" href="settings.php">&times;</a>
                        <div class="content" style="height: 0px; text-align:center;">
                            <p style="font-size:16px; margin:12px 0 0 0;">Are you sure you want to delete?</p>
                        </div>
                        <div style="display: flex;justify-content: center;">
                        <a href="delete-procedure.php?id=' . urlencode($id) . '&force=1" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"<font class="tn-in-text">&nbsp;Permanently Delete&nbsp;</font></button></a>&nbsp;&nbsp;&nbsp;
                        <a href="settings.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;Cancel&nbsp;&nbsp;</font></button></a>
                        </div>
                    </center>
            </div>
            </div>
            ';
            exit();
        }
        if ($action == 'drop') {
            $nameget = isset($_GET["name"]) ? $_GET["name"] : '';
                // Show a moderate-width, auto-height popup so long names are visible without too much empty space
                echo '
                <div id="popup1" class="overlay">
                    <div class="popup" style="max-width:620px; width:70%;">
                    <center>
                        <h2>Are you sure?</h2>
                        <a class="close" href="settings.php">&times;</a>
                        <div class="content" style="max-width:560px; padding:16px; text-align:center;">
                            <p style="text-align:center; margin:0 auto; display:inline-block;">You want to delete this service<br>(' . htmlspecialchars($nameget) . ').</p>
                        </div>
                        <div style="display: flex;justify-content: center; gap:12px;">
                        <a href="delete-service.php?id=' . $id . '" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;">Yes</button></a>
                        <a href="settings.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;">No</button></a>
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
            $price = isset($row['price']) ? $row['price'] : '';

            $error_1 = $_GET["error"];
            $errorlist = array(
                '1' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Procedure with this name already exists.</label>',
                '2' => '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Please fill all fields.</label>',
                '3' => "",
                '0' => '',
            );

            if ($error_1 != '3') {
                // Determine if this is the core Consultation procedure (allow price edit only for it)
                $isCoreConsultation = (intval($id) === 1) || (strtolower(trim($name)) === "consultation");

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
                                    </div>';

                // If this is the Consultation procedure, show a price input so admin can edit its fixed price
                if ($isCoreConsultation) {
                    $priceVal = ($price === '' || $price === null) ? '' : number_format((float)$price, 2, '.', '');
                    echo '<div class="form-group"><label for="price">Price (₱)</label><input type="number" name="price" class="input-text" step="0.01" min="0" placeholder="e.g. 500.00" value="' . $priceVal . '"></div>';
                }

                echo '
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
            $nameget = isset($_GET["name"]) ? $_GET["name"] : '';
            $cnt1 = isset($_GET['cnt1']) ? intval($_GET['cnt1']) : 0;
            $cnt2 = isset($_GET['cnt2']) ? intval($_GET['cnt2']) : 0;
            $hasRefs = ($cnt1 + $cnt2) > 0;
            if ($hasRefs) {
                // Stronger confirmation with POST force delete; use a moderate popup width to avoid empty space
                echo '
                <div id="popup1" class="overlay">
                    <div class="popup" style="max-width:620px; width:70%;">
                        <center>
                            <h2>Are you sure?</h2>
                            <a class="close" href="settings.php">&times;</a>
                            <div class="content" style="max-width:560px; padding:16px; text-align:center;">
                                <p style="font-size:16px; margin:12px 0 0 0; text-align:center; display:inline-block;">Are you sure you want to delete?</p>
                            </div>
                            <div style="display:flex;justify-content:center;gap:16px;">
                                <form action="delete-procedure.php?id=' . $id . '&force=1" method="POST" style="display:inline;">
                                    <button type="submit" class="btn-primary btn" style="margin:10px;padding:10px;">Yes, Delete</button>
                                </form>
                                <a href="settings.php" class="non-style-link"><button class="btn-primary btn" style="margin:10px;padding:10px;">No</button></a>
                            </div>
                        </center>
                </div>
                </div>
                ';
            } else {
                // Simple confirmation when no references exist; use a moderate popup width for readability
                echo '
                <div id="popup1" class="overlay">
                    <div class="popup" style="max-width:620px; width:70%;">
                        <center>
                            <h2>Are you sure?</h2>
                            <a class="close" href="settings.php">&times;</a>
                            <div class="content" style="max-width:560px; padding:16px; text-align:center;">
                                <p style="font-size:16px; margin:12px 0 0 0; text-align:center; display:inline-block;">Are you sure you want to delete?</p>
                            </div>
                            <div style="display: flex;justify-content: center; gap:12px;">
                                <a href="delete-procedure.php?id=' . $id . '" class="non-style-link"><button class="btn-primary btn" style="margin:10px;padding:10px;">Yes</button></a>
                                <a href="settings.php" class="non-style-link"><button class="btn-primary btn" style="margin:10px;padding:10px;">No</button></a>
                            </div>
                        </center>
                </div>
                </div>
                ';
            }
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
            $branch = $database->query("SELECT * FROM branches WHERE id='$bid'");
            if ($branch && $branch->num_rows > 0) {
                $branch = $branch->fetch_assoc();
                $bname = isset($branch['name']) ? htmlspecialchars($branch['name']) : 'Branch';
                $baddr = isset($branch['address']) ? htmlspecialchars($branch['address']) : '';
            } else {
                $branch = null;
                $bname = 'Branch';
                $baddr = '';
            }
            echo '<div id="popup1" class="overlay"><div class="popup"><center><a class="close" href="settings.php">&times;</a><div style="display:flex;justify-content:center;"><div class="abc"><table width="80%" class="sub-table" border="0"><tr><td colspan="2"><p style="font-size:22px">Edit Branch</p></td></tr><tr><form action="edit-branch.php" method="POST"><input type="hidden" name="id" value="'.$bid.'"><td colspan="2"><label for="name">Branch Name:</label><br><input type="text" name="name" class="input-text" value="'.$bname.'" required></td></tr><tr><td colspan="2"><label for="address">Address:</label><br><textarea name="address" class="input-text" rows="3">'.$baddr.'</textarea></td></tr><tr><td colspan="2"><input type="reset" value="Reset" class="new-action-btn"> <input type="submit" value="Save Changes" class="new-action-btn"></td></tr></form></table></div></div></center></div></div>';
        } elseif ($action == 'drop_branch') {
            $nameget = isset($_GET['name']) ? urldecode($_GET['name']) : '';
            $bid = intval($_GET['id']);
            echo '<div id="popup1" class="overlay"><div class="popup"><center><h2>Are you sure?</h2><a class="close" href="settings.php">&times;</a><div class="content" style="height: 0px;">You want to delete this branch<br>(' . substr($nameget, 0, 40) . ').</div><div style="display: flex;justify-content: center;"><a href="delete-branch.php?id=' . $bid . '" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"<font class="tn-in-text">&nbsp;Yes&nbsp;</font></button></a>&nbsp;&nbsp;&nbsp;<a href="settings.php" class="non-style-link"><button  class="btn-primary btn"  style="display: flex;justify-content: center;align-items: center;margin:10px;padding:10px;"><font class="tn-in-text">&nbsp;&nbsp;No&nbsp;&nbsp;</font></button></a></div></center></div></div>';
        }
        elseif ($action == 'edit_branch_info') {
            // Record incoming request for debugging: why branch_id may be 0
            error_log("[settings.php] Entering edit_branch_info handler. REQUEST_URI=" . ($_SERVER['REQUEST_URI'] ?? '') . " | QUERY_STRING=" . ($_SERVER['QUERY_STRING'] ?? '') . " | GET=" . print_r($_GET, true));
            $bid = intval($_GET['branch_id'] ?? 0);
            error_log("[settings.php] Resolved bid=" . $bid . " | REMOTE_ADDR=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            // Fetch branch and existing branch_info if any
            $branch_rs = $database->query("SELECT * FROM branches WHERE id='$bid'");
            if ($branch_rs && $branch_rs->num_rows > 0) {
                $branch = $branch_rs->fetch_assoc();
                $bname = isset($branch['name']) ? htmlspecialchars($branch['name']) : 'Branch';
                $baddr = isset($branch['address']) ? htmlspecialchars($branch['address']) : '';
            } else {
                $branch = null;
                $bname = 'Branch';
                $baddr = '';
            }
            $binfo_rs = $database->query("SELECT * FROM branch_info WHERE branch_id='$bid' LIMIT 1");
            $binfo = $binfo_rs && $binfo_rs->num_rows ? $binfo_rs->fetch_assoc() : [];

            $clinic_name = isset($binfo['clinic_name']) ? htmlspecialchars($binfo['clinic_name']) : '';
            $clinic_description = isset($binfo['clinic_description']) ? htmlspecialchars($binfo['clinic_description']) : '';
            $address = isset($binfo['address']) ? htmlspecialchars($binfo['address']) : $baddr;
            $phone = isset($binfo['phone']) ? htmlspecialchars($binfo['phone']) : '';
            $email = isset($binfo['email']) ? htmlspecialchars($binfo['email']) : '';
            $map_embed = isset($binfo['map_embed_url']) ? htmlspecialchars($binfo['map_embed_url']) : '';

            echo '<div id="popup1" class="overlay"><div class="popup edit-clinic-popup"><center>';
            echo '<h2>Edit Clinic Info — ' . $bname . '</h2>';
            echo '<a class="close" href="settings.php">&times;</a>';
            echo '<form method="POST" action="settings.php">';
            echo '<input type="hidden" name="save_branch_info" value="1">';
            echo '<input type="hidden" name="branch_id" value="' . $bid . '">';
            echo '<div style="text-align:left; margin-top:10px;">';
            echo '<label>Clinic Name</label><br><input type="text" name="clinic_name" value="' . $clinic_name . '" required><br><br>';
            echo '<label>Clinic Description</label><br><textarea name="clinic_description">' . $clinic_description . '</textarea><br><br>';
            echo '<label>Address</label><br><input type="text" name="address" value="' . $address . '"><br><br>';
            echo '<label>Phone</label><br><input type="tel" name="phone" value="' . $phone . '"><br><br>';
            echo '<label>Email</label><br><input type="email" name="email" value="' . $email . '"><br><br>';
            echo '<label>Map Embed URL</label><br><input type="text" name="map_embed_url" value="' . $map_embed . '"><br><br>';
            echo '</div>';
            echo '<div style="display:flex; gap:10px; justify-content:center; margin-top:10px;">';
            echo '<a class="non-style-link" href="settings.php"><button type="button" class="new-action-btn">Cancel</button></a>';
            echo '<button type="submit" class="new-action-btn">Save Changes</button>';
            echo '</div>';
            echo '</form>';
            echo '</center></div></div>';
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
            // Use delegated pointerdown to capture clicks on any current or future
            // link/button that navigates to a settings action so we can save scroll
            // before the navigation happens. This avoids binding to every element.
            try {
                document.addEventListener('pointerdown', function (ev) {
                    try {
                        const target = ev.target && ev.target.closest ? ev.target.closest('a, button, input, [data-action], [data-href]') : null;
                        if (!target) return;

                        const href = (target.getAttribute && (target.getAttribute('href') || target.getAttribute('data-href') || target.getAttribute('formaction'))) || '';
                        const onclick = (target.getAttribute && target.getAttribute('onclick')) || '';
                        const dataAction = (target.dataset && target.dataset.action) ? target.dataset.action : '';

                        // If the click is on/inside a form (submit/save), or an explicit action link,
                        // or matches common add/save/edit/delete keywords, save the scroll position.
                        const form = target.closest ? target.closest('form') : null;
                        const tag = (target.tagName || '').toLowerCase();
                        const isSubmitButton = (tag === 'button' && (String(target.type || '').toLowerCase() === 'submit')) || (tag === 'input' && String(target.type || '').toLowerCase() === 'submit');

                        const keywordMatch = /add_|add-|save|create|submit|delete|drop|edit|remove|action=/i.test(href + onclick + dataAction);

                        if (form || isSubmitButton || href.indexOf('action=') !== -1 || onclick.indexOf('action=') !== -1 || dataAction || keywordMatch) {
                            try {
                                sessionStorage.setItem('settings_last_scroll', String(window.scrollY || document.documentElement.scrollTop || 0));
                            } catch (e) { }
                        }
                    } catch (e) { /* ignore */ }
                }, true);
            } catch (e) { }
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');

            if (action === 'view' || action === 'edit' || action === 'drop' || action === 'add' || action === 'edit_clinic' || action === 'edit_procedure' || action === 'drop_procedure' || action === 'add_procedure' || action === 'add_branch' || action === 'edit_branch' || action === 'drop_branch' || action === 'edit_branch_info') {
                const popup = document.getElementById('popup1');
                if (popup) {
                    popup.style.display = 'flex';
                    document.body.style.overflow = 'hidden';

                    // Restore previous scroll position if we saved one before navigation.
                    try {
                        const last = sessionStorage.getItem('settings_last_scroll');
                        if (last !== null) {
                            setTimeout(function () {
                                window.scrollTo(0, parseInt(last, 10) || 0);
                                sessionStorage.removeItem('settings_last_scroll');
                            }, 1);
                        }
                    } catch (e) { }

                    // Auto-focus the first form control in the popup for convenience
                    try {
                        const firstControl = popup.querySelector('input[name="clinic_name"], input[type="text"], textarea');
                        if (firstControl) {
                            firstControl.focus();
                        }
                    } catch (e) {
                        // ignore focus errors
                    }
                }
            }

            // Ensure edit links navigate with the correct branch id (fallback for any malformed hrefs)
            // Only apply the branch-id fallback to branch edit links (do not block other edit buttons)
            document.querySelectorAll('a.action-btn.edit-btn').forEach(function(el){
                el.addEventListener('click', function(e){
                    try {
                        const href = this.getAttribute('href') || '';
                        const dataBid = this.getAttribute('data-bid') || '';
                        // Only run fallback when the link targets branch edit (explicit action or branch_id param)
                        const isBranchEditAction = /action=edit_branch_info|action=edit_branch/.test(href) || /[?&]branch_id=\d+/.test(href);
                        if (!isBranchEditAction) {
                            return; // don't interfere with other edit links (procedures, services, etc.)
                        }

                        // If href indicates branch_id=0 or missing, force navigation using data-bid
                        const hasBranchParam = /[?&]branch_id=\d+/.test(href);
                        const branchIsZero = /[?&]branch_id=0(?!\d)/.test(href);
                        if (!hasBranchParam || branchIsZero) {
                            e.preventDefault();
                            if (dataBid && parseInt(dataBid) > 0) {
                                window.location.href = 'settings.php?action=edit_branch_info&branch_id=' + encodeURIComponent(dataBid);
                            }
                        }
                    } catch (err) {
                        // Ignore and allow default navigation
                    }
                });
            });

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
                        url.searchParams.delete('branch_id');
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
                        url.searchParams.delete('branch_id');
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
