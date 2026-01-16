<?php
date_default_timezone_set('Asia/Singapore');
session_start();

if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

// Ensure we include the application's root connection.php
$connectionPath = __DIR__ . '/../../connection.php';
if (!file_exists($connectionPath)) {
    // Clearer error for easier debugging when file path is wrong
    die("Missing required file: $connectionPath\n");
}
include_once $connectionPath;

// Branch restriction (e.g., Bacoor-only admin)
$restrictedBranchId = isset($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id'] ? (int)$_SESSION['restricted_branch_id'] : 0;

// Date-range filtering
$startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : null;

// Build WHERE clauses for date filtering
$dateWhere = '';
if ($startDate && $endDate) {
    // inclusive
    $s = $database->real_escape_string($startDate);
    $e = $database->real_escape_string($endDate);
    $dateWhere = " AND DATE(appodate) BETWEEN '$s' AND '$e'";
} elseif ($startDate) {
    $s = $database->real_escape_string($startDate);
    $dateWhere = " AND DATE(appodate) >= '$s'";
} elseif ($endDate) {
    $e = $database->real_escape_string($endDate);
    $dateWhere = " AND DATE(appodate) <= '$e'";
}

// Basic summary queries (apply date filter where appropriate)
// Total revenue (paid appointments)
// Apply branch filter through doctor mapping when restricted
$branchWhereAppt = '';
if ($restrictedBranchId > 0) {
    // Restrict strictly to appointments that have appointment.branch_id set to the restricted branch.
    // This ensures branch-scoped admins only see appointments booked for their branch.
    $branchWhereAppt = " AND appointment.branch_id = $restrictedBranchId";
}

$totRevRes = $database->query("SELECT IFNULL(SUM(total_amount),0) as total_revenue FROM appointment WHERE payment_status = 'paid' " . $branchWhereAppt . $dateWhere);
$totRev = $totRevRes ? $totRevRes->fetch_assoc()['total_revenue'] : 0;

// Counts
$totalAppointmentsRes = $database->query("SELECT COUNT(*) as total_appointments FROM appointment WHERE 1 " . $branchWhereAppt . $dateWhere);
$totalAppointments = $totalAppointmentsRes ? $totalAppointmentsRes->fetch_assoc()['total_appointments'] : 0;

$paidCountRes = $database->query("SELECT COUNT(*) as paid_count FROM appointment WHERE payment_status='paid' " . $branchWhereAppt . $dateWhere);
$paidCount = $paidCountRes ? $paidCountRes->fetch_assoc()['paid_count'] : 0;

// Revenue by month for selected year (or current year)
$year = isset($_GET['year']) && is_numeric($_GET['year']) ? intval($_GET['year']) : date('Y');
$revByMonth = [];
$revMonthSql = "SELECT MONTH(appodate) as m, IFNULL(SUM(total_amount),0) as amount FROM appointment WHERE payment_status='paid' AND YEAR(appodate) = $year";
if ($branchWhereAppt) $revMonthSql .= $branchWhereAppt;
// apply date constraints in addition to year where provided
if ($dateWhere) $revMonthSql .= $dateWhere;
$revMonthSql .= " GROUP BY MONTH(appodate) ORDER BY MONTH(appodate)";
$revMonthStmt = $database->query($revMonthSql);
if ($revMonthStmt) {
    while ($r = $revMonthStmt->fetch_assoc()) {
        $revByMonth[intval($r['m'])] = $r['amount'];
    }
}

// Revenue by branch
$revByBranch = [];
$revBranchSql = "SELECT COALESCE(b.name, 'Unassigned') as name, IFNULL(SUM(a.total_amount),0) as amount, COALESCE(a.branch_id, d.branch_id) AS branch_key FROM appointment a LEFT JOIN doctor d ON a.docid = d.docid LEFT JOIN branches b ON b.id = COALESCE(a.branch_id, d.branch_id) WHERE a.payment_status='paid' ";
if ($restrictedBranchId > 0) {
    // Restrict branch aggregation to appointments with a.branch_id equal to the restricted branch
    $revBranchSql .= " AND a.branch_id = $restrictedBranchId";
}
if ($dateWhere) $revBranchSql .= $dateWhere;
$revBranchSql .= " GROUP BY branch_key ORDER BY amount DESC";
$revBranchStmt = $database->query($revBranchSql);
if ($revBranchStmt) {
    while ($r = $revBranchStmt->fetch_assoc()) {
        $branchName = $r['name'] ? $r['name'] : 'Unassigned';
        $revByBranch[$branchName] = $r['amount'];
    }
}

// Top procedures: include both primary procedure (appointment.procedure_id)
// and stacked procedures (appointment_procedures) so multi-procedure
// appointments are counted properly. No LIMIT so all procedures are returned.
$topProcedures = [];
$apptDocFilter = '';
if ($restrictedBranchId > 0) {
    // For queries that alias appointment as `a`, restrict to appointments with a.branch_id set to the restricted branch
    $apptDocFilter = " AND a.branch_id = $restrictedBranchId";
}

// Use per-procedure agreed_price from appointment_procedures when present,
// otherwise attribute the appointment.total_amount to the primary procedure
// for appointments that have no appointment_procedures rows.
$dateWhereA = str_replace('DATE(appodate)', 'DATE(a.appodate)', $dateWhere);
$topProcSql = "SELECT p.procedure_name, SUM(t.cnt) AS cnt, SUM(t.amount) AS amount FROM (\n" .
    "  SELECT ap.procedure_id AS pid, COUNT(*) AS cnt, IFNULL(SUM(ap.agreed_price),0) AS amount FROM appointment_procedures ap JOIN appointment a ON ap.appointment_id = a.appoid WHERE a.payment_status='paid' " . ($dateWhereA ? $dateWhereA : "") . " " . ($apptDocFilter ? $apptDocFilter : "") . " GROUP BY ap.procedure_id\n" .
    "  UNION ALL\n" .
    "  SELECT a.procedure_id AS pid, COUNT(*) AS cnt, IFNULL(SUM(a.total_amount),0) AS amount FROM appointment a LEFT JOIN appointment_procedures ap ON a.appoid = ap.appointment_id WHERE a.payment_status='paid' AND ap.id IS NULL " . ($dateWhereA ? $dateWhereA : "") . " " . ($apptDocFilter ? $apptDocFilter : "") . " GROUP BY a.procedure_id\n" .
") t JOIN procedures p ON p.procedure_id = t.pid GROUP BY p.procedure_id ORDER BY cnt DESC";

$topProcStmt = $database->query($topProcSql);
if ($topProcStmt) {
    while ($r = $topProcStmt->fetch_assoc()) {
        $topProcedures[] = $r;
    }
}

// Ensure top procedures are explicitly sorted by count (desc) in PHP
// so both the table and chart use the same ordering (defensive in case
// the DB ordering changes).
if (!empty($topProcedures)) {
    usort($topProcedures, function($a,$b){
        $ai = isset($a['cnt']) ? (int)$a['cnt'] : 0;
        $bi = isset($b['cnt']) ? (int)$b['cnt'] : 0;
        if ($bi === $ai) {
            // tie-breaker by amount desc
            $af = isset($a['amount']) ? (float)$a['amount'] : 0.0;
            $bf = isset($b['amount']) ? (float)$b['amount'] : 0.0;
            return $bf <=> $af;
        }
        return $bi <=> $ai;
    });
}

// Revenue by day (respects date range; if none supplied shows all paid days)
$revByDay = [];
$revDaySql = "SELECT DATE(appodate) as day, IFNULL(SUM(total_amount),0) as amount FROM appointment WHERE payment_status='paid'";
if ($branchWhereAppt) $revDaySql .= $branchWhereAppt;
if ($dateWhere) $revDaySql .= $dateWhere; // $dateWhere already starts with AND
$revDaySql .= " GROUP BY DATE(appodate) ORDER BY day ASC";
$revDayStmt = $database->query($revDaySql);
if ($revDayStmt) {
    while ($r = $revDayStmt->fetch_assoc()) {
        $revByDay[$r['day']] = $r['amount'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Financial Reports - IHeartDentistDC</title>
    <link rel="stylesheet" href="../../css/animations.css">
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/dashboard.css">
    <link rel="stylesheet" href="../../css/table.css">
    <link rel="stylesheet" href="../../css/responsive-admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Page-specific chart container rules to avoid oversized canvases */
        .chart-container {
            max-width: 900px;
            width: 100%;
            margin: 8px 0 20px 0;
            padding: 8px 0;
        }
        /* Ensure canvas scales responsively and doesn't explode in height */
        .chart-container canvas {
            width: 100% !important;
            height: auto !important;
            display: block;
            max-height: 480px;
        }
        /* Small tweak to keep the stats boxes from overflowing */
        .stat-box .stat-content h2 { font-size: 28px; }

        /* Filter form layout and controls */
        .reports-filter-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .reports-filter-form .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .reports-filter-form .filter-group label {
            font-size: 13px;
            color: #2b2b2b;
        }
        .reports-filter-form input[type="date"],
        .reports-filter-form input[type="number"] {
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid #d0d7dd;
            height: 36px;
            box-sizing: border-box;
            background: #fff;
        }
        .reports-filter-form .filter-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .post-btn {
            height: 36px;
            padding: 0 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center; /* center inline content horizontally */
            text-align: center; /* ensure text alignment */
            white-space: nowrap; /* prevent wrapping */
            border-radius: 6px;
            font-weight: 600;
            color: #fff;
            text-decoration: none;
            text-transform: none; /* keep the original casing */
            font-size: 13px;
            letter-spacing: 0.6px;
            min-width: 92px; /* ensure consistent button widths */
            box-sizing: border-box;
        }
        .post-btn.post-btn-apply { background: #84b6e4; border: none; }
        .post-btn.post-btn-csv { background: #5c9c34; }
        .post-btn.post-btn-pdf { background: #2b7a9b; }
        .post-btn.post-btn-clear { background: #f3f4f6; color: #222; border: 1px solid #d1d5db; }
        /* Summary tiles row */
        .reports-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            align-items: stretch;
            margin-bottom: 18px;
        }
        .reports-stats .stat-box {
            background: #eaf4fb;
            border-radius: 8px;
            padding: 18px 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .reports-stats .stat-content { text-align: left; width:100%; }
        .reports-stats .stat-content h4 { margin: 0 0 8px 0; font-size: 16px; color: #2b7a9b; }
        .reports-stats .stat-content h2 { margin: 0; font-size: 26px; color: #2b7a9b; }
        /* Enhanced layout additions */
        .reports-section-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(420px,1fr)); gap:22px; margin-bottom:26px; }
        @media (max-width:900px){ .reports-section-grid { grid-template-columns:1fr; } }
        .report-card { background:#fff; border:1px solid #e2e8ee; border-radius:10px; padding:18px 20px 16px; box-shadow:0 3px 10px rgba(0,0,0,0.05); }
        .report-card + .report-card { margin-top:14px; }
        .report-card h3.section-title { margin:0; font-size:18px; font-weight:600; color:#2b2b2b; display:flex; align-items:center; gap:8px; }
        .pill { display:inline-block; padding:2px 8px; border-radius:20px; background:#eaf4fb; font-size:11px; font-weight:600; color:#2b7a9b; }
        .collapse-btn { margin-left:auto; background:#84b6e4; border:none; color:#fff; font-size:11px; font-weight:600; padding:6px 10px; border-radius:5px; cursor:pointer; min-width:52px; text-align:center; }
        .collapse-btn[aria-expanded="false"] { background:#5a6a75; }
        .card-body.collapsed { display:none; }
        .sticky-header { position:sticky; top:0; background:#f4f8fb; z-index:2; }
        .search-inline { display:flex; gap:8px; align-items:center; margin-bottom:10px; }
        .search-inline input { flex:1; padding:6px 10px; border:1px solid #d0d7dd; border-radius:6px; font-size:13px; }
        .empty-msg { text-align:center; padding:25px 10px; color:#666; font-style:italic; }
        .chart-container.narrow { max-width:550px; width:100%; min-height:240px; }
        /* Column alignment & width adjustments */
        .report-card table th, .report-card table td { vertical-align:middle; }
        /* Right align numeric revenue cells */
        .report-card table tr > th:nth-child(2),
        .report-card table tr > td:nth-child(2) { text-align:right; }
        /* Procedure table numeric alignment */
        #procTable tr > th:nth-child(2), #procTable tr > td:nth-child(2),
        #procTable tr > th:nth-child(3), #procTable tr > td:nth-child(3) { text-align:right; }
        /* Branch table explicit widths */
        #card-branch table th:first-child, #card-branch table td:first-child { width:55%; text-align:left; }
        #card-branch table th:nth-child(2), #card-branch table td:nth-child(2) { width:45%; }
        /* Daily table widths */
        #card-daily table th:first-child, #card-daily table td:first-child { width:55%; text-align:left; }
        #card-daily table th:nth-child(2), #card-daily table td:nth-child(2) { width:45%; }
        .reports-stats .stat-box { transition:box-shadow .25s; }
        .reports-stats .stat-box:hover { box-shadow:0 6px 18px rgba(0,0,0,0.08); }
        /* Prevent layout jitter due to hover transforms and dynamic button width */
        .report-card, .reports-stats .stat-box { will-change: box-shadow; }
    </style>
</head>
<body>
    <button class="hamburger-admin" id="hamburgerAdmin" aria-label="Toggle sidebar" aria-controls="adminSidebar" aria-expanded="false">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="main-container">
        <div class="sidebar" id="adminSidebar">
            <div class="sidebar-logo">
                <img src="../../Media/Icon/logo.png" alt="IHeartDentistDC Logo">
            </div>
            <div class="user-profile">
                <div class="profile-image">
                    <img src="../../Media/Icon/logo.png" alt="Profile" class="profile-img">
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
                <a href="../dashboard.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/home.png" alt="Home" class="nav-icon">
                    <span class="nav-label">Dashboard</span>
                </a>
                <a href="../dentist.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/dentist.png" alt="Dentist" class="nav-icon">
                    <span class="nav-label">Dentist</span>
                </a>
                <a href="../patient.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/care.png" alt="Patient" class="nav-icon">
                    <span class="nav-label">Patient</span>
                </a>
                <a href="../records.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/edit.png" alt="Records" class="nav-icon">
                    <span class="nav-label">Patient Records</span>
                </a>
                <a href="../calendar/calendar.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/calendar.png" alt="Calendar" class="nav-icon">
                    <span class="nav-label">Calendar</span>
                </a>
                <a href="../booking.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/booking.png" alt="Booking" class="nav-icon">
                    <span class="nav-label">Booking</span>
                </a>
                <a href="../appointment.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/appointment.png" alt="Appointment" class="nav-icon">
                    <span class="nav-label">Appointment</span>
                </a>
                <a href="../history.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/folder.png" alt="Archive" class="nav-icon">
                    <span class="nav-label">Archive</span>
                </a>
                <a href="financial_reports.php" class="nav-item active">
                    <img src="../../Media/Icon/Blue/folder.png" alt="Reports" class="nav-icon">
                    <span class="nav-label">Reports</span>
                </a>
                <?php if (empty($_SESSION['restricted_branch_id'])): ?>
                <a href="../settings.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/settings.png" alt="Settings" class="nav-icon">
                    <span class="nav-label">Settings</span>
                </a>
                <?php endif; ?>
            </div>
            <div class="log-out">
                <a href="../logout.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/logout.png" alt="Log Out" class="nav-icon">
                    <span class="nav-label">Log Out</span>
                </a>
            </div>
        </div>

        <div class="content-area">
            <div class="content">
                <!-- Legacy sidebar-toggle removed; logo now acts as toggle -->
                <div class="main-section">
                    <?php
                        // If admin is restricted to a branch, resolve the branch name for display
                        $currentBranchName = '';
                        if ($restrictedBranchId > 0) {
                            $brow = $database->query("SELECT name FROM branches WHERE id = " . intval($restrictedBranchId) . " LIMIT 1");
                            if ($brow && $brow->num_rows > 0) {
                                $currentBranchName = $brow->fetch_assoc()['name'];
                            }
                        }

                        // Debug helper: append ?dbg=1 to the URL when logged in as a branch admin
                        // to display resolved restricted branch id and quick counts. Only for local debugging.
                        if (isset($_SESSION['user']) && in_array(strtolower($_SESSION['user']), ['adminmakati@edoc.com','adminbacoor@edoc.com']) && isset($_GET['dbg']) && $_GET['dbg'] == 1) {
                            echo '<div style="background:#fff3cd;padding:10px;border:1px solid #ffe8a1;margin:8px 0;border-radius:6px;">';
                            echo '<strong>Debug:</strong><br/>';
                            echo 'restricted_branch_id = ' . var_export($restrictedBranchId, true) . '<br/>';
                            // Sample checks
                            $c1 = $database->query("SELECT COUNT(*) as cnt FROM appointment WHERE payment_status='paid' AND branch_id = " . intval($restrictedBranchId) . " LIMIT 1");
                            $c1v = ($c1 && $c1->num_rows) ? $c1->fetch_assoc()['cnt'] : 'N/A';
                            echo 'paid appointments matching branch filter = ' . htmlspecialchars($c1v) . '<br/>';
                            // Show a short sample SQL used for top procedures
                            $sampleSql = "SELECT ap.procedure_id, COUNT(*) as cnt FROM appointment_procedures ap JOIN appointment a ON ap.appointment_id = a.appoid WHERE a.payment_status='paid' AND a.branch_id = " . intval($restrictedBranchId) . " GROUP BY ap.procedure_id LIMIT 3";
                            echo 'sample top-proc SQL (first 200 chars): ' . htmlspecialchars(substr($sampleSql,0,200)) . '<br/>';
                            echo '</div>';
                        }
                    ?>
                    <h2>Production Summary<?php echo $currentBranchName ? ' - ' . htmlspecialchars($currentBranchName) : ''; ?></h2>

                    <form id="filterForm" method="GET" class="reports-filter-form">
                        <div class="filter-group">
                            <label>Start date</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate ?? ''); ?>">
                        </div>
                        <div class="filter-group">
                            <label>End date</label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate ?? ''); ?>">
                        </div>
                        <div class="filter-group">
                            <label>Year (for month chart)</label>
                            <input type="number" name="year" min="2000" max="2100" value="<?php echo $year; ?>" style="width:110px;">
                        </div>
                        <div class="filter-actions">
                            <button type="button" id="clearFiltersBtn" class="post-btn post-btn-clear">Clear</button>
                            <button type="submit" class="post-btn post-btn-apply">Apply</button>
                            <a href="export_csv.php?start_date=<?php echo urlencode($startDate ?? ''); ?>&end_date=<?php echo urlencode($endDate ?? ''); ?>&year=<?php echo urlencode($year); ?><?php echo $restrictedBranchId ? '&branch_id=' . intval($restrictedBranchId) : ''; ?>" class="post-btn post-btn-csv">Export CSV</a>
                            <a href="export_pdf.php?start_date=<?php echo urlencode($startDate ?? ''); ?>&end_date=<?php echo urlencode($endDate ?? ''); ?>&year=<?php echo urlencode($year); ?><?php echo $restrictedBranchId ? '&branch_id=' . intval($restrictedBranchId) : ''; ?>" class="post-btn post-btn-pdf">Export PDF</a>
                        </div>
                    </form>

                    <?php $todayKey = date('Y-m-d'); $todayRev = isset($revByDay[$todayKey]) ? (float)$revByDay[$todayKey] : 0; ?>
                    <div class="reports-stats">
                        <div class="stat-box">
                            <div class="stat-content">
                                <h4>Today&#39;s Production</h4>
                                <h2 style="color:#2b7a9b;">PHP <?php echo number_format($todayRev,2); ?></h2>
                                <p style="margin-top:4px;font-size:13px;color:#555;">Date: <?php echo date('M j, Y'); ?></p>
                            </div>
                        </div>

                        <div class="stat-box">
                            <div class="stat-content">
                                <h4>Total Production</h4>
                                <h2 style="color:#2b7a9b;">PHP <?php echo number_format($totRev,2); ?></h2>
                            </div>
                        </div>

                        <div class="stat-box">
                            <div class="stat-content">
                                <h4>Total Appointments</h4>
                                <h2><?php echo number_format($totalAppointments); ?></h2>
                            </div>
                        </div>

                        <div class="stat-box">
                            <div class="stat-content">
                                <h4>Paid Appointments</h4>
                                <h2><?php echo number_format($paidCount); ?></h2>
                            </div>
                        </div>
                    </div>

                    <div class="reports-section-grid">
                        <div class="report-card" id="card-month">
                            <h3 class="section-title">Monthly Production (<?php echo $year; ?>) <span class="pill">Yearly</span>
                                <button class="collapse-btn" type="button" data-target="#body-month" aria-expanded="true">Hide</button></h3>
                            <div class="card-body" id="body-month">
                                <div class="chart-container">
                                    <canvas id="monthChart" class="responsive-chart" aria-label="Production by month chart" role="img"></canvas>
                                </div>
                            </div>
                        </div>
                        <?php if (empty($_SESSION['restricted_branch_id'])): ?>
                        <div class="report-card" id="card-branch">
                            <h3 class="section-title">Production by Branch <span class="pill">Location</span>
                                <button class="collapse-btn" type="button" data-target="#body-branch" aria-expanded="true">Hide</button></h3>
                            <div class="card-body" id="body-branch">
                                <table class="table table-striped">
                                    <thead class="sticky-header"><tr><th>Branch</th><th>Production (PHP)</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($revByBranch as $b=>$amt) {
                                            echo '<tr><td>'.htmlspecialchars($b).'</td><td>'.number_format($amt,2).'</td></tr>';
                                        } ?>
                                    </tbody>
                                </table>
                                <div class="chart-container narrow">
                                    <canvas id="branchChart" class="responsive-chart" aria-label="Production by branch chart" role="img"></canvas>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="report-card" id="card-daily">
                        <h3 class="section-title">Daily Production<?php if($startDate||$endDate){ echo ' (filtered)'; } ?> <span class="pill">Daily</span>
                            <button class="collapse-btn" type="button" data-target="#body-daily" aria-expanded="true">Hide</button></h3>
                        <div class="card-body" id="body-daily">
                            <div style="max-height:300px; overflow:auto;">
                                <table class="table table-striped">
                                    <thead class="sticky-header"><tr><th>Date</th><th>Production (PHP)</th></tr></thead>
                                    <tbody>
                                        <?php
                                        if(empty($revByDay)) {
                                            echo '<tr><td colspan="2" class="empty-msg">No paid production days found.</td></tr>';
                                        } else {
                                            foreach($revByDay as $d=>$amt){
                                                echo '<tr><td>'.htmlspecialchars(date('M j, Y', strtotime($d))).'</td><td>'.number_format($amt,2).'</td></tr>';
                                            }
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="chart-container">
                                <canvas id="dailyRevChart" class="responsive-chart" aria-label="Production by day chart" role="img"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="report-card" id="card-proc">
                        <h3 class="section-title">Top Procedures <span class="pill">Services</span>
                            <button class="collapse-btn" type="button" data-target="#body-proc" aria-expanded="true">Hide</button></h3>
                        <div class="card-body" id="body-proc">
                            <div class="search-inline">
                                <input type="text" id="procFilter" placeholder="Filter procedures..." aria-label="Filter procedures">
                            </div>
                            <table class="table table-striped" id="procTable">
                                <thead class="sticky-header"><tr><th>Procedure</th><th>Count</th><th>Production (PHP)</th></tr></thead>
                                <tbody>
                                    <?php foreach ($topProcedures as $p) {
                                        echo '<tr><td>'.htmlspecialchars($p['procedure_name']).'</td><td>'.intval($p['cnt']).'</td><td>'.number_format($p['amount'],2).'</td></tr>';
                                    } ?>
                                </tbody>
                            </table>
                            <div class="chart-container">
                                <canvas id="topProceduresChart" class="responsive-chart" aria-label="Top procedures production chart" role="img"></canvas>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <script>
        // Prepare data from PHP
        const monthLabels = [
            'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'
        ];
        const monthData = [
            <?php
            for ($m=1;$m<=12;$m++) {
                $amt = isset($revByMonth[$m]) ? floatval($revByMonth[$m]) : 0;
                echo $amt . ($m<12? ',':'');
            }
            ?>
        ];

        const IS_RESTRICTED = <?php echo ($restrictedBranchId > 0) ? 'true' : 'false'; ?>;
        const branchLabels = <?php echo ($restrictedBranchId > 0) ? '[]' : json_encode(array_keys($revByBranch)); ?>;
        const branchData = <?php echo ($restrictedBranchId > 0) ? '[]' : json_encode(array_values($revByBranch)); ?>;
        const dailyLabels = <?php echo json_encode(array_map(function($d){return date('M j', strtotime($d));}, array_keys($revByDay))); ?>;
        const dailyData = <?php echo json_encode(array_values($revByDay)); ?>;
        const topProcLabels = <?php echo json_encode(array_map(function($p){return $p['procedure_name'];}, $topProcedures)); ?>;
        const topProcRevenue = <?php echo json_encode(array_map(function($p){return (float)$p['amount'];}, $topProcedures)); ?>;
        const topProcCounts = <?php echo json_encode(array_map(function($p){return (int)$p['cnt'];}, $topProcedures)); ?>;

        // Month chart
        const ctx = document.getElementById('monthChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'Production (PHP)',
                        data: monthData,
                        backgroundColor: 'rgba(75, 179, 149, 0.6)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 3,
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        // Branch chart (pie) - stabilized to prevent layout jitter
        // Branch chart (pie) - only render when not restricted
        const bctx = document.getElementById('branchChart');
        if (!IS_RESTRICTED && bctx) {
            bctx.style.maxHeight = '240px';
            new Chart(bctx, {
                type: 'pie',
                data: {
                    labels: branchLabels,
                    datasets: [{ data: branchData, backgroundColor: ['#2b7a9b','#84b6e4','#f9a15d','#f94144','#c0c0c0'] }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    layout: { padding: 4 },
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        // Daily revenue chart (line)
        const drctx = document.getElementById('dailyRevChart');
        if (drctx) {
            new Chart(drctx, {
                type: 'line',
                data: {
                    labels: dailyLabels,
                    datasets: [{
                        label: 'Production (PHP)',
                        data: dailyData,
                        borderColor: '#2b7a9b',
                        backgroundColor: 'rgba(43,122,155,0.15)',
                        tension: 0.25,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 3,
                    scales: { y: { beginAtZero: true } },
                    plugins: { tooltip: { mode: 'index', intersect: false } }
                }
            });
        }

        // Top procedures revenue chart (horizontal bar if many labels)
        const tpctx = document.getElementById('topProceduresChart');
        if (tpctx) {
            new Chart(tpctx, {
                type: 'bar',
                data: {
                    labels: topProcLabels,
                    datasets: [{
                        label: 'Count',
                        data: topProcCounts,
                        backgroundColor: '#84b6e4'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2.4,
                    scales: { y: { beginAtZero: true } },
                    indexAxis: topProcLabels.length > 6 ? 'y' : 'x',
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var idx = context.dataIndex;
                                    var cnt = (typeof topProcCounts !== 'undefined' && typeof topProcCounts[idx] !== 'undefined') ? topProcCounts[idx] : context.formattedValue;
                                    return 'Count: ' + cnt;
                                },
                                afterLabel: function(context) {
                                    var idx = context.dataIndex;
                                    var rev = (typeof topProcRevenue[idx] !== 'undefined') ? Number(topProcRevenue[idx]).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) : '';
                                    return rev ? ('Production: PHP ' + rev) : '';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Collapse functionality
        document.querySelectorAll('.collapse-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.querySelector(btn.getAttribute('data-target'));
                const expanded = btn.getAttribute('aria-expanded') === 'true';
                if (expanded) {
                    target.classList.add('collapsed');
                    btn.setAttribute('aria-expanded','false');
                    btn.textContent = 'Show';
                } else {
                    target.classList.remove('collapsed');
                    btn.setAttribute('aria-expanded','true');
                    btn.textContent = 'Hide';
                }
            });
        });

        // Procedure table filter
        const procInput = document.getElementById('procFilter');
        if (procInput) {
            procInput.addEventListener('input', () => {
                const term = procInput.value.toLowerCase();
                document.querySelectorAll('#procTable tbody tr').forEach(row => {
                    const nameCell = row.children[0];
                    if (!nameCell) return;
                    const text = nameCell.textContent.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });
        }
    </script>
    <script>
        // Mobile sidebar toggle for Reports page
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
        // Clear filters button: remove query params and reload current page
        (function(){
            var btn = document.getElementById('clearFiltersBtn');
            if (!btn) return;
            btn.addEventListener('click', function(){
                // Navigate to same path without query string
                window.location.href = window.location.pathname;
            });
        })();
    </script>
</body>
</html>

