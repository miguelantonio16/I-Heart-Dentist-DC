<?php
require '../../connection.php';
session_start();

// Branch restriction
$restrictedBranchId = isset($_SESSION['restricted_branch_id']) && $_SESSION['restricted_branch_id'] ? (int)$_SESSION['restricted_branch_id'] : 0;

// compute counts (respect branch restriction when set)
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

$today = date('Y-m-d');

// Upcoming appointments (aggregated procedures)
// Upcoming appointments (aggregated procedures)
$branchScope = '';
if ($restrictedBranchId > 0) {
    $branchScope = " AND (doctor.branch_id = $restrictedBranchId OR doctor.docid IN (SELECT docid FROM doctor_branches WHERE branch_id=$restrictedBranchId))";
}

$upcomingAppointments = $database->query("
    SELECT
        a.appoid,
        COALESCE(GROUP_CONCAT(DISTINCT p.procedure_name ORDER BY p.procedure_name SEPARATOR ', '), '') AS procedure_names,
        a.appodate,
        a.appointment_time,
        patient.pname as patient_name,
        doctor.docname as doctor_name,
        COALESCE(b.name, '') AS branch_name
    FROM appointment a
    LEFT JOIN appointment_procedures ap ON a.appoid = ap.appointment_id
    LEFT JOIN procedures p ON ap.procedure_id = p.procedure_id
    LEFT JOIN patient ON a.pid = patient.pid
    LEFT JOIN doctor ON a.docid = doctor.docid
    LEFT JOIN branches b ON doctor.branch_id = b.id
    WHERE
        a.status = 'appointment'
        AND a.appodate >= '$today'
    " . $branchScope . "
    GROUP BY a.appoid
    ORDER BY a.appodate ASC, a.appointment_time ASC
");

ob_start();
?>
<div class="right-sidebar">
    <div class="stats-section">
        <div class="stats-container">
            <a href="../dentist.php" class="stat-box-link">
                <div class="stat-box">
                    <div class="stat-content">
                        <h1 class="stat-number"><?php echo $doctorrow->num_rows; ?></h1>
                        <p class="stat-label">Dentists</p>
                    </div>
                    <div class="stat-icon">
                        <img src="../../Media/Icon/Blue/dentist.png" alt="Dentist Icon">
                    </div>
                </div>
            </a>

            <a href="../patient.php" class="stat-box-link">
                <div class="stat-box">
                    <div class="stat-content">
                        <h1 class="stat-number"><?php echo $patientrow->num_rows; ?></h1>
                        <p class="stat-label">Patients</p>
                    </div>
                    <div class="stat-icon">
                        <img src="../../Media/Icon/Blue/care.png" alt="Patient Icon">
                    </div>
                </div>
            </a>

            <a href="../booking.php" class="stat-box-link">
                <div class="stat-box">
                    <div class="stat-content">
                        <h1 class="stat-number"><?php echo $appointmentrow->num_rows; ?></h1>
                        <p class="stat-label">Bookings</p>
                    </div>
                    <div class="stat-icon">
                        <img src="../../Media/Icon/Blue/booking.png" alt="Booking Icon">
                        <?php if ($appointmentrow->num_rows > 0): ?>
                            <span class="notification-badge"><?php echo $appointmentrow->num_rows; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>

            <a href="../appointment.php" class="stat-box-link">
                <div class="stat-box">
                    <div class="stat-content">
                        <h1 class="stat-number"><?php echo $schedulerow->num_rows; ?></h1>
                        <p class="stat-label">Appointments</p>
                    </div>
                    <div class="stat-icon">
                        <img src="../../Media/Icon/Blue/appointment.png" alt="Appointment Icon">
                        <?php if ($schedulerow->num_rows > 0): ?>
                            <span class="notification-badge"><?php echo $schedulerow->num_rows; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="calendar-section">
        <div class="color-guide-container">
            <div class="calendar-header">
                <h3 class="color-guide-title">Color guide</h3>
            </div>
            <div class="color-legend">
                <div class="color-item">
                    <div class="color-circle" style="background-color: #F7BD01;"></div>
                    <div class="color-label">Booking</div>
                </div>
                <div class="color-item">
                    <div class="color-circle" style="background-color: #0e8923;"></div>
                    <div class="color-label">Appointment</div>
                </div>
                <div class="color-item">
                    <div class="color-circle" style="background-color: #F94144;"></div>
                    <div class="color-label">No Service</div>
                </div>
                <div class="color-item">
                    <div class="color-circle" style="background-color: #F9A15D;"></div>
                    <div class="color-label">Timeslot Taken</div>
                </div>
                <div class="color-item">
                    <div class="color-circle" style="background-color: #BBBBBB;"></div>
                    <div class="color-label">Completed</div>
                </div>
                <button class="legend-item no-service" id="addNonWorkingDay">Add No Service Day</button>

            </div>
        </div>
    </div>

    <div class="upcoming-appointments">
        <h3>Upcoming Appointments</h3>
        <div class="appointments-content">
            <?php
            if ($upcomingAppointments && $upcomingAppointments->num_rows > 0) {
                while ($appointment = $upcomingAppointments->fetch_assoc()) {
                    $pname = htmlspecialchars($appointment['patient_name'] ?? '');
                    $dname = htmlspecialchars($appointment['doctor_name'] ?? '');
                    $proc = htmlspecialchars($appointment['procedure_names'] ?? '');
                    $date_str = '';
                    $time_str = '';
                    if (!empty($appointment['appodate'])) {
                        $date_str = htmlspecialchars(date('F j, Y', strtotime($appointment['appodate'])));
                    }
                    if (!empty($appointment['appointment_time'])) {
                        $time_str = htmlspecialchars(date('g:i A', strtotime($appointment['appointment_time'])));
                    }

                    echo '<div class="appointment-item">';
                    echo '<h4 class="appointment-type">' . $pname . '</h4>';
                    echo '<p class="appointment-dentist">With Dr. ' . $dname . '</p>';
                    echo '<p class="appointment-date">' . $proc . '</p>';
                    $branch = htmlspecialchars($appointment['branch_name'] ?? '');
                    echo '<p class="appointment-date">' . $date_str . ($date_str && $time_str ? ' • ' : '') . $time_str . (($branch!=='') ? (' - ' . $branch) : '') . '</p>';
                    echo '</div>';
                }
            } else {
                echo '<div class="no-appointments"><p>No upcoming appointments scheduled</p></div>';
            }
            ?>
        </div>
    </div>
</div>
<?php
$html = ob_get_clean();
header('Content-Type: application/json');
echo json_encode(['status' => true, 'html' => $html]);
