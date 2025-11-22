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
$totRevRes = $database->query("SELECT IFNULL(SUM(total_amount),0) as total_revenue FROM appointment WHERE payment_status = 'paid' " . $dateWhere);
$totRev = $totRevRes ? $totRevRes->fetch_assoc()['total_revenue'] : 0;

// Counts
$totalAppointmentsRes = $database->query("SELECT COUNT(*) as total_appointments FROM appointment WHERE 1 " . $dateWhere);
$totalAppointments = $totalAppointmentsRes ? $totalAppointmentsRes->fetch_assoc()['total_appointments'] : 0;

$paidCountRes = $database->query("SELECT COUNT(*) as paid_count FROM appointment WHERE payment_status='paid' " . $dateWhere);
$paidCount = $paidCountRes ? $paidCountRes->fetch_assoc()['paid_count'] : 0;

// Revenue by month for selected year (or current year)
$year = isset($_GET['year']) && is_numeric($_GET['year']) ? intval($_GET['year']) : date('Y');
$revByMonth = [];
$revMonthSql = "SELECT MONTH(appodate) as m, IFNULL(SUM(total_amount),0) as amount FROM appointment WHERE payment_status='paid' AND YEAR(appodate) = $year";
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
$revBranchSql = "SELECT b.name, IFNULL(SUM(a.total_amount),0) as amount FROM appointment a LEFT JOIN doctor d ON a.docid = d.docid LEFT JOIN branches b ON d.branch_id = b.id WHERE a.payment_status='paid' ";
if ($dateWhere) $revBranchSql .= $dateWhere;
$revBranchSql .= " GROUP BY b.id ORDER BY amount DESC";
$revBranchStmt = $database->query($revBranchSql);
if ($revBranchStmt) {
    while ($r = $revBranchStmt->fetch_assoc()) {
        $branchName = $r['name'] ? $r['name'] : 'Unassigned';
        $revByBranch[$branchName] = $r['amount'];
    }
}

// Top procedures
$topProcedures = [];
$topProcSql = "SELECT p.procedure_name, COUNT(*) as cnt, IFNULL(SUM(a.total_amount),0) as amount FROM appointment a JOIN procedures p ON a.procedure_id = p.procedure_id WHERE a.payment_status='paid' ";
if ($dateWhere) $topProcSql .= $dateWhere;
$topProcSql .= " GROUP BY a.procedure_id ORDER BY cnt DESC LIMIT 8";
$topProcStmt = $database->query($topProcSql);
if ($topProcStmt) {
    while ($r = $topProcStmt->fetch_assoc()) {
        $topProcedures[] = $r;
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
    </style>
</head>
<body>
    <div class="main-container">
        <div class="sidebar">
            <div class="sidebar-logo">
                <img src="../../Media/Icon/logo.png" alt="IHeartDentistDC Logo">
            </div>
            <div class="user-profile">
                <div class="profile-image">
                    <img src="../../Media/Icon/logo.png" alt="Profile" class="profile-img">
                </div>
                <h3 class="profile-name">I Heart Dentist Dental Clinic</h3>
                <p style="color: #777; margin: 0; font-size: 14px; text-align: center;">Secretary</p>
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
                <a href="../settings.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/settings.png" alt="Settings" class="nav-icon">
                    <span class="nav-label">Settings</span>
                </a>
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
                <?php include(__DIR__ . '/../inc/sidebar-toggle.php'); ?>
                <div class="main-section">
                    <h2>Financial Summary</h2>

                    <form id="filterForm" method="GET" style="display:flex; gap:12px; align-items:flex-end; margin-bottom:16px;">
                        <div>
                            <label>Start date</label><br>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate ?? ''); ?>">
                        </div>
                        <div>
                            <label>End date</label><br>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate ?? ''); ?>">
                        </div>
                        <div>
                            <label>Year (for month chart)</label><br>
                            <input type="number" name="year" min="2000" max="2100" value="<?php echo $year; ?>" style="width:110px;">
                        </div>
                        <div>
                            <button type="submit" class="post-btn">Apply</button>
                        </div>
                        <div>
                            <a href="export_csv.php?start_date=<?php echo urlencode($startDate ?? ''); ?>&end_date=<?php echo urlencode($endDate ?? ''); ?>" class="post-btn" style="background:#5c9c34;">Export CSV</a>
                        </div>
                        <div>
                            <a href="export_pdf.php?start_date=<?php echo urlencode($startDate ?? ''); ?>&end_date=<?php echo urlencode($endDate ?? ''); ?>" class="post-btn" style="background:#2b7a9b;">Export PDF</a>
                        </div>
                    </form>

                    <div class="stats-container" style="grid-template-columns: repeat(3, 1fr);">
                        <div class="stat-box">
                            <div class="stat-content">
                                <h4>Total Revenue (paid)</h4>
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

                    <h3>Revenue by Month (<?php echo $year; ?>)</h3>
                    <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px;">
                        <div class="chart-container">
                            <canvas id="monthChart" class="responsive-chart" aria-label="Revenue by month chart" role="img"></canvas>
                        </div>
                    </div>

                    <h3>Revenue by Branch</h3>
                    <div style="margin-bottom:16px;">
                        <table class="table table-striped">
                            <thead>
                                <tr><th>Branch</th><th>Revenue (PHP)</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($revByBranch as $b=>$amt) {
                                    echo '<tr><td>'.htmlspecialchars($b).'</td><td>'.number_format($amt,2).'</td></tr>';
                                } ?>
                            </tbody>
                        </table>
                    </div>

                    <h3>Revenue by Branch (Chart)</h3>
                    <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px;">
                        <div class="chart-container">
                            <canvas id="branchChart" class="responsive-chart" aria-label="Revenue by branch chart" role="img"></canvas>
                        </div>
                    </div>

                    <h3>Top Procedures (by count)</h3>
                    <div>
                        <table class="table table-striped">
                            <thead><tr><th>Procedure</th><th>Count</th><th>Revenue (PHP)</th></tr></thead>
                            <tbody>
                                <?php foreach ($topProcedures as $p) {
                                    echo '<tr><td>'.htmlspecialchars($p['procedure_name']).'</td><td>'.intval($p['cnt']).'</td><td>'.number_format($p['amount'],2).'</td></tr>';
                                } ?>
                            </tbody>
                        </table>
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

        const branchLabels = <?php echo json_encode(array_keys($revByBranch)); ?>;
        const branchData = <?php echo json_encode(array_values($revByBranch)); ?>;

        // Month chart
        const ctx = document.getElementById('monthChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthLabels,
                    datasets: [{
                        label: 'Revenue (PHP)',
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

        // Branch chart (pie) - constrained aspect ratio
        const bctx = document.getElementById('branchChart');
        if (bctx) {
            new Chart(bctx, {
                type: 'pie',
                data: {
                    labels: branchLabels,
                    datasets: [{ data: branchData, backgroundColor: ['#2b7a9b','#84b6e4','#f9a15d','#f94144','#c0c0c0'] }]
                },
                options: { responsive: true, maintainAspectRatio: true, aspectRatio: 1.2 }
            });
        }
    </script>
</body>
</html>

