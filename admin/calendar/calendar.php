<?php
date_default_timezone_set('Asia/Singapore');
session_start();

require '../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION["user"]) || ($_SESSION["user"] == "" || $_SESSION['usertype'] != 'a')) {
    header("location: ../login.php");
    exit();
}

include("../../connection.php");

$procedures = $database->query("SELECT * FROM procedures");
$procedure_options = '';
while ($procedure = $procedures->fetch_assoc()) {
    $procedure_options .= '<option value="' . $procedure['procedure_id'] . '">' . $procedure['procedure_name'] . '</option>';
}

$doctors = $database->query("SELECT docid, docname FROM doctor where status = 'active'");
$doctor_options = '';
while ($doctor = $doctors->fetch_assoc()) {
    $doctor_options .= '<option value="' . $doctor['docid'] . '">' . $doctor['docname'] . '</option>';
}

$branches = $database->query("SELECT id, name FROM branches ORDER BY name ASC");
$branch_options = '<option value="">All Branches</option>';
while ($branch = $branches->fetch_assoc()) {
    $branch_options .= '<option value="' . $branch['id'] . '">' . $branch['name'] . '</option>';
}

$patients = $database->query("SELECT pid, pname, branch_id FROM patient where status = 'active'");
$patient_name = '';
while ($patient = $patients->fetch_assoc()) {
    $branch_id_attr = isset($patient['branch_id']) && $patient['branch_id'] !== null ? ' data-branch="' . $patient['branch_id'] . '"' : '';
    $patient_name .= '<option value="' . $patient['pid'] . '"' . $branch_id_attr . '>' . htmlspecialchars($patient['pname']) . '</option>';
}

// Get totals for right sidebar
$doctorrow = $database->query("select * from doctor where status='active';");
$patientrow = $database->query("select * from patient where status='active';");
$appointmentrow = $database->query("select * from appointment where status='booking';");
$schedulerow = $database->query("select * from appointment where status='appointment';");

// Calendar variables
$today = date('Y-m-d');
$currentMonth = date('F');
$currentYear = date('Y');
$daysInMonth = date('t');
$firstDayOfMonth = date('N', strtotime("$currentYear-" . date('m') . "-01"));
$currentDay = date('j');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../../Media/white-icon/white-IHeartDentistDC_Logo.png" type="image/png">
    <!-- CSS for full calender -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.css" rel="stylesheet" />
    <!-- JS for jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <!-- JS for full calender -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.20.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.js"></script>
    <!-- bootstrap css and js -->
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <link rel="stylesheet" href="../../css/calendar.css">
    <link rel="stylesheet" href="../../css/animations.css">
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/dashboard.css">

    <title>Calendar - IHeartDentistDC</title>
    <link rel="icon" href="../../Media/Icon/logo.png" type="image/png">
    <style>
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
       
        .select-dentist-message {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 300px;
            background-color: #f9f9f9;
            border: 1px dashed #ccc;
            border-radius: 8px;
            margin: 20px 0;
        }

        .select-dentist-message p {
            font-size: 18px;
            color: #666;
            text-align: center;
            padding: 20px;
        }
        /* Ensure calendar has visible height */
        #calendar {
            min-height: 420px;
            margin-top: 8px;
        }
        
        /* Select2 dropdown styling */
        .select2-container--default .select2-selection--single {
            height: 38px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        
        .select2-container .select2-selection--single {
            box-sizing: border-box;
            cursor: pointer;
            display: block;
            height: 38px;
            user-select: none;
            -webkit-user-select: none;
        }
        
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #ced4da;
        }
        
        .select2-dropdown {
            border: 1px solid #ced4da;
        }

        .no-service{
            background-color:rgb(80, 87, 185); 
            width: 270px; 
            border: none;
            transition: background-color 0.5s ease;
        }
        .no-service:hover{
            cursor: pointer;
            background-color: rgb(118, 123, 206);
        }
    </style>
</head>

<body>

    <div class="nav-container">
        <div class="sidebar">
            <div class="sidebar-logo">
                <img src="../../Media/Icon/logo.png" alt="IHeartDentistDC Logo">
            </div>

            <div class="user-profile">
                <div class="profile-image">
                    <img src="../../Media/Icon/logo.png" alt="Profile" class="profile-img">
                </div>
                <h3 class="profile-name">I Heart Dentist Dental Clinic</h3>
                <p style="color: #777; margin: 0; font-size: 14px; text-align: center;">
                Secretary
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
                <a href="calendar.php" class="nav-item active">
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
                <a href="../reports/financial_reports.php" class="nav-item">
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
                <div class="main-section">
                    <div class="container">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="form-group" style="display:flex; gap:12px; align-items:center;">
                                    <div style="flex:1;">
                                        <label for="choose_branch">Branch</label>
                                        <div class="select-wrapper">
                                            <select class="form-control form-dentist select2-branch" id="choose_branch">
                                                <?php echo $branch_options; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div style="flex:2;">
                                        <label for="choose_dentist">Create an Appointment:</label>
                                        <div class="select-wrapper">
                                            <select class="form-control form-dentist select2-dentist" id="choose_dentist">
                                                <option value="">Select a Dentist</option>
                                                <?php echo $doctor_options; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div id="calendar"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Event creation modal -->
                    <div class="modal fade" id="event_entry_modal" tabindex="-1" role="dialog"
                        aria-labelledby="modalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-md" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="modalLabel">Create Appointment</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">x</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="eventForm">
                                        <div class="form-group">
                                            <label for="event_name">Event Name</label>
                                            <input type="text" name="event_name" id="event_name" class="form-control"
                                                placeholder="Enter your event name" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="procedure">Procedure</label>
                                            <select class="form-control" id="procedure" name="procedure" required>
                                                <option value="">Select a Procedure</option>
                                                <?php echo $procedure_options; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="patient_name">Patient Name</label>
                                            <select class="form-control select2-search" id="patient_name" name="patient_name" required>
                                                <option value="">Select a Patient</option>
                                                <?php echo $patient_name; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="appointment_date">Date</label>
                                            <input type="text" name="appointment_date" id="appointment_date"
                                                class="form-control" readonly required>
                                        </div>
                                        <div class="form-group">
                                            <label for="appointment_time">Time</label>
                                            <select class="form-control" id="appointment_time" name="appointment_time"
                                                required>
                                                <option value="09:00:00">9:00 AM</option>
                                                <option value="09:30:00">9:30 AM</option>
                                                <option value="10:00:00">10:00 AM</option>
                                                <option value="10:30:00">10:30 AM</option>
                                                <option value="11:00:00">11:00 AM</option>
                                                <option value="11:30:00">11:30 AM</option>
                                                <option value="13:00:00">1:00 PM</option>
                                                <option value="13:30:00">1:30 PM</option>
                                                <option value="14:00:00">2:00 PM</option>
                                                <option value="14:30:00">2:30 PM</option>
                                                <option value="16:00:00">4:00 PM</option>
                                                <option value="16:30:00">4:30 PM</option>
                                            </select>
                                        </div>
                                        <input type="hidden" name="docid" id="docid" value="">
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-primary">Confirm</button>
                                            <button type="button" class="btn btn-secondary"
                                                data-dismiss="modal">Close</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Appointment details modal -->
                    <div class="modal fade" id="appointmentModal" tabindex="-1" role="dialog"
                        aria-labelledby="appointmentModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content" style="width: 500px;">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="appointmentModalLabel">Event Details</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <p><strong>Procedure:</strong> <span id="modalProcedureName"></span></p>
                                    <p><strong>Patient:</strong> <span id="modalPatientName"></span></p>
                                    <p><strong>Dentist:</strong> <span id="modalDentistName"></span></p>
                                    <p><strong>Branch:</strong> <span id="modalBranchName"></span></p>
                                    <p><strong>Date:</strong> <span id="modalDate"></span></p>
                                    <p><strong>Time:</strong> <span id="modalTime"></span></p>
                                    <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-danger" id="cancel-appointment"
                                        data-toggle="modal" data-target="#cancelModal">Cancel/Reject</button>
                                    <button id="confirm-booking" class="btn btn-success" style="display: none;">Confirm
                                        Booking</button>
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cancellation confirmation modal -->
                    <div class="modal fade" id="cancelModal" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Confirm Cancellation</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="cancelForm">
                                        <input type="hidden" name="appoid" id="cancelAppoid">
                                        <p>Are you sure you want to cancel this appointment?</p>
                                        <div class="form-group">
                                            <label for="cancelReason">Reason for cancellation:</label>
                                            <select class="form-control" name="cancel_reason" id="cancelReason"
                                                required>
                                                <option value="">-- Select a reason --</option>
                                                <option value="Dentist Unavailable">Dentist Unavailable</option>
                                                <option value="Clinic Closed">Clinic Closed</option>
                                                <option value="Emergency Situation">Emergency Situation</option>
                                                <option value="Patient Request">Patient Request</option>
                                                <option value="Other">Other (please specify)</option>
                                            </select>
                                        </div>
                                        <div class="form-group" id="otherReasonGroup" style="display:none;">
                                            <label for="otherReason">Please specify:</label>
                                            <input type="text" class="form-control" name="other_reason"
                                                id="otherReason">
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" id="confirmCancel">Confirm</button>
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Non-working day modal -->
                    <div id="nonWorkingDayModal" class="modal fade" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Add Non-Working Day</h5>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <label>Select Date:</label>
                                    <input type="date" id="nonWorkingDate" class="form-control">
                                    <label>Description:</label>
                                    <input type="text" id="nonWorkingDesc" class="form-control">
                                </div>
                                <div class="modal-footer">
                                    <button id="saveNonWorkingDay" class="btn btn-primary">Save</button>
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Add right sidebar section -->
                <div class="right-sidebar">
                    <div class="stats-section">
                        <div class="stats-container">
                            <!-- First row -->
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

                            <!-- Second row -->
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
                        <!-- Color Guide -->
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
        // keep current selection globals so other functions can access them
        var currentDentistId = null;
        var currentBranchId = null;

        $(document).ready(function () {

            // Initialize Select2 for patient dropdown
            $('#patient_name').select2({
                placeholder: "Search for a patient...",
                allowClear: true,
                width: '100%',
                dropdownParent: $('#event_entry_modal')
            });

            // Reinitialize Select2 when modal is shown to fix styling issues
            $('#event_entry_modal').on('shown.bs.modal', function () {
                $('#patient_name').select2({
                    placeholder: "Search for a patient...",
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#event_entry_modal')
                });
            });

            // Don't initialize calendar by default
            // Instead, show a message prompting to select a dentist
            $('#calendar').html('<div class="select-dentist-message"><p>Please select a dentist to view available appointment slots.</p></div>');
           
            $('#choose_dentist').change(function () {
                currentDentistId = $(this).val();
                if (currentDentistId) {
                    $('#docid').val(currentDentistId);
                    // Clear the message and initialize calendar
                    $('#calendar').html('');
                    display_events(currentDentistId);
                } else {
                    // If "Select a Dentist" is chosen, show message again
                    $('#calendar').html('<div class="select-dentist-message"><p>Please select a dentist to view available appointment slots.</p></div>');
                }
            });

            // If a dentist is already selected on page load, initialize calendar automatically
            var preselectedDentist = $('#choose_dentist').val();
            var preselectedBranch = $('#choose_branch').val();
            if (preselectedBranch) currentBranchId = preselectedBranch;
            if (preselectedDentist) {
                currentDentistId = preselectedDentist;
                $('#docid').val(currentDentistId);
                $('#calendar').html('');
                // slight delay to ensure DOM and fullCalendar plugin are ready
                setTimeout(function(){ display_events(currentDentistId); }, 50);
            }

            // Branch filter change - fetch doctors for branch and repopulate dentist dropdown
            $('#choose_branch').change(function () {
                currentBranchId = $(this).val() || null;

                // Fetch doctors for the selected branch
                $.ajax({
                    url: 'get_doctors_by_branch.php',
                    data: { branch_id: currentBranchId },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status) {
                            var options = '<option value="">Select a Dentist</option>';
                            $.each(res.doctors, function (i, d) {
                                options += '<option value="' + d.docid + '">' + d.docname + '</option>';
                            });
                            $('#choose_dentist').html(options);
                            // Reset selected dentist and calendar
                            currentDentistId = null;
                            $('#docid').val('');
                            $('#calendar').html('<div class="select-dentist-message"><p>Please select a dentist to view available appointment slots.</p></div>');
                        } else {
                            alert('Failed to load doctors for the selected branch.');
                        }
                    },
                    error: function () {
                        alert('Error fetching doctors for branch.');
                    }
                });
            });

            // Form submission handler
            $('#eventForm').on('submit', function (e) {
                e.preventDefault();
                save_event();
            });

            // Cancel reason dropdown handler
            $('#cancelReason').change(function () {
                if ($(this).val() === 'Other') {
                    $('#otherReasonGroup').show();
                    $('#otherReason').prop('required', true);
                } else {
                    $('#otherReasonGroup').hide();
                    $('#otherReason').prop('required', false);
                }
            });

            // Confirm cancellation handler - FIXED
            $('#confirmCancel').click(function () {
                var formData = $('#cancelForm').serialize();

                if (!$('#cancelReason').val()) {
                    alert('Please select a cancellation reason');
                    return;
                }

                if ($('#cancelReason').val() === 'Other' && !$('#otherReason').val()) {
                    alert('Please specify the cancellation reason');
                    return;
                }

                $.ajax({
                    url: 'cancel_appointment.php', // Changed from cancel_appointment.php to save_event.php
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (response) {
                        if (response.status) {
                            alert(response.msg);
                            $('#cancelModal').modal('hide');
                            $('#appointmentModal').modal('hide');
                            if (currentDentistId) {
                                display_events(currentDentistId);
                            }
                        } else {
                            alert('Error: ' + response.msg);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Error:", error);
                        alert('Failed to process the request. Please check console for details.');
                    }
                });
            });

            // Non-working day handlers
            $("#addNonWorkingDay").click(function () {
                $("#nonWorkingDayModal").modal("show");
            });

            $("#saveNonWorkingDay").click(function () {
                var selectedDate = $("#nonWorkingDate").val();
                var description = $("#nonWorkingDesc").val();

                if (!selectedDate || !description) {
                    alert("Please enter a date and description.");
                    return;
                }
                if (!currentDentistId) {
                    alert('Please select a dentist before adding a No Service day.');
                    return;
                }

                $.ajax({
                    url: "save_non_working_day.php",
                    type: "POST",
                    dataType: "json",
                    data: { date: selectedDate, description: description, docid: currentDentistId },
                    success: function (res) {
                        if (res.status) {
                            alert("Non-Working Day added successfully!");
                            if (currentDentistId) {
                                display_events(currentDentistId);
                            }
                            $("#nonWorkingDayModal").modal("hide");
                        } else {
                            alert("Error: " + res.message);
                        }
                    },
                    error: function (xhr) {
                        alert("Failed to save Non-Working Day. Server Error.");
                        console.error("Server response:", xhr.responseText);
                    }
                });
            });
        });

        function display_events(dentistId) {
            var events = new Array();
            var bookedTimes = [];
            var nonWorkingDates = []; // collect per-dentist no-service dates (YYYY-MM-DD)

            function fetchBookedTimes(date) {
                $.ajax({
                    url: 'fetch_booked_times.php',
                    data: { dentist_id: dentistId, date: date, branch_id: currentBranchId },
                    dataType: 'json',
                    success: function (response) {
                        if (response.status) {
                            bookedTimes = response.booked_times;
                            updateTimeDropdown();
                        }
                    },
                    error: function (xhr, status) {
                        alert("Error fetching booked times.");
                    }
                });
            }

            function updateTimeDropdown() {
                var timeSlots = [
                    "09:00:00", "09:30:00",
                    "10:00:00", "10:30:00",
                    "11:00:00", "11:30:00",
                    "13:00:00", "13:30:00",
                    "14:00:00", "14:30:00",
                    "16:00:00", "16:30:00"
                ];

                $('#appointment_time').empty();
                $.each(timeSlots, function (index, time) {
                    var displayTime = moment(time, "HH:mm:ss").format("h:mm A");
                    var option = $("<option></option>").val(time).text(displayTime);
                    if (bookedTimes.indexOf(time) !== -1) {
                        option.attr("disabled", "disabled");
                        option.css("background-color", "#F46E34");
                    }
                    $('#appointment_time').append(option);
                });
            }

            $.ajax({
                url: 'display_event.php',
                data: { dentist_id: dentistId, branch_id: currentBranchId },
                dataType: 'json',
                success: function (response) {
                    var result = (response && response.data) ? response.data : [];
                    // If the server returned an error, log it for debugging
                    if (!response || response.status !== true) {
                        console.warn('display_event.php returned no events or an error:', response && response.msg ? response.msg : response);
                    }
                    $.each(result, function (i, item) {
                        // Collect non-working dates for dayRender/select blocking
                        if (item.type === 'non-working') {
                            try {
                                nonWorkingDates.push(moment(item.start).format('YYYY-MM-DD'));
                            } catch (e) {
                                nonWorkingDates.push(item.start);
                            }
                        }
                        var eventColor = (item.status === 'appointment') ? '#0e8923' :
                            (item.status === 'booking') ? '#F7BD01' :
                            (item.status === 'completed') ? '#BBBBBB' : '#F94144';
                        events.push({
    event_id: item.appointment_id,
    title: item.title + (item.branch_name ? ' — ' + item.branch_name : ''),
    start: item.start,
    end: item.end,
    color: item.color || eventColor, // Use color from PHP if available
    status: item.status,
    procedure_name: item.procedure_name,
    patient_name: item.patient_name,
    dentist_name: item.dentist_name,
    branch_name: item.branch_name || null,
    time: item.time || moment(item.start).format("h:mm A"),
    type: item.type, // <--- ADD THIS LINE (Crucial for delete logic)
    allDay: item.allDay || false
});
                    });

                    if ($('#calendar').fullCalendar) {
                        try { $('#calendar').fullCalendar('destroy'); } catch (e) { console.warn('Could not destroy calendar (maybe not initialized yet).', e); }
                    }

                    $('#calendar').fullCalendar({
                        defaultView: 'month',
                        timeZone: 'local',
                        editable: true,
                        selectable: true,
                        selectHelper: true,
                        selectAllow: function (selectInfo) {
                            // Allow selection for any day except past dates
                            if (moment(selectInfo.start).isBefore(moment(), 'day')) {
                                return false;
                            }
                            return true;
                        },
                        select: function (start, end) {
                            var selectedDate = moment(start).format('YYYY-MM-DD');

                            // If this date is a non-working day for the selected dentist, block booking
                            if (nonWorkingDates.indexOf(selectedDate) !== -1) {
                                alert("This date is marked as 'No Service' for the selected dentist. Booking is not allowed.");
                                $('#calendar').fullCalendar('unselect');
                                return;
                            }

                            $('#appointment_date').val(selectedDate);
                            $('#event_name').val("Dental Appointment");
                            fetchBookedTimes(selectedDate);
                            $('#event_entry_modal').modal('show');
                        },
                        events: events,
                        eventRender: function (event, element, view) {
    // 1. Style Past Events
    var eventDate = moment(event.start).format('YYYY-MM-DD');
    var todayDate = moment().format('YYYY-MM-DD');
    if (eventDate < todayDate) {
        element.css({
            'background-color': event.color,
            'color': '#303030'
        });
    }

    // 2. Handle Clicks
    element.on('click', function () {
        
        // === NEW: DELETE "NO SERVICE" DAY LOGIC ===
        if (event.type === 'non-working') {
            var dateToDelete = moment(event.start).format('YYYY-MM-DD');
            if (confirm("Do you want to remove this 'No Service' day (" + dateToDelete + ")?")) {
                $.ajax({
                    url: 'delete_non_working_day.php',
                    type: 'POST',
                    data: { date: dateToDelete, docid: (event.docid || currentDentistId) },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status) {
                            alert(res.msg);
                            $('#calendar').fullCalendar('removeEvents', event._id);
                        } else {
                            alert(res.msg);
                        }
                    },
                    error: function() {
                        alert("Error communicating with the server.");
                    }
                });
            }
            return; // Stop here so the appointment modal doesn't open
        }
        // ==========================================

    // Existing Appointment Modal Logic
    $('#cancelAppoid').val(event.event_id);
    $('#modalProcedureName').text(event.procedure_name || 'N/A');
    $('#modalPatientName').text(event.patient_name || 'N/A');
    $('#modalDentistName').text(event.dentist_name || 'N/A');
    $('#modalBranchName').text(event.branch_name || 'N/A');
    $('#modalDate').text(moment(event.start).format('MMMM D, YYYY'));
    $('#modalTime').text(event.time);

        var statusText = '';
        switch (event.status) {
            case 'appointment': statusText = 'Confirmed Appointment'; break;
            case 'booking': statusText = 'Booking'; break;
            case 'completed': statusText = 'Completed'; break;
            case 'rejected': statusText = 'Rejected'; break;
            case 'cancelled': statusText = 'Cancelled'; break;
            default: statusText = 'N/A';
        }
        $('#modalStatus').text(statusText);

        if (event.status === 'booking') {
            $('#confirm-booking').show();
            $('#cancel-appointment').show().text('Reject Booking');
        } else if (event.status === 'appointment') {
            $('#confirm-booking').hide();
            $('#cancel-appointment').show().text('Cancel Appointment');
        } else {
            $('#confirm-booking').hide();
            $('#cancel-appointment').hide();
        }

        $('#appointmentModal').modal('show');
    });

    element.css({
        'background-color': event.color,
        'color': '#303030'
    });
},
                        dayRender: function (date, cell) {
                                    var formattedDate = date.format('YYYY-MM-DD');
                                    if (nonWorkingDates.indexOf(formattedDate) > -1) {
                                        // Make whole day column look red-ish to indicate no service
                                        cell.css({ 'background-color': '#FFF2F2' });
                                    }
                                }
                    });
                },
                error: function (xhr, status) {
                    alert("Error fetching events.");
                }
            });

            // Confirm booking handler
            $('#confirm-booking').click(function () {
                var appoid = $('#cancelAppoid').val();
                if (!appoid) {
                    alert('No appointment selected.');
                    return;
                }

                var btn = $(this);
                btn.prop('disabled', true).text('Confirming...');

                $.ajax({
                    url: 'confirm_appointment.php',
                    type: 'POST',
                    data: { appoid: appoid },
                    dataType: 'json',
                    success: function (res) {
                        if (res && res.status) {
                            var msg = res.msg || 'Booking confirmed successfully.';
                            if (res.email_sent === false || res.mail_error) {
                                msg += '\n(Note: confirmation email failed to send.' + (res.mail_error ? ' Error: ' + res.mail_error : '') + ')';
                            }
                            alert(msg);
                            $('#appointmentModal').modal('hide');
                            if (currentDentistId) display_events(currentDentistId);
                        } else {
                            alert('Error: ' + (res.msg || 'Failed to confirm booking.'));
                        }
                    },
                    error: function (xhr) {
                        alert('Server error while confirming booking.');
                    },
                    complete: function () {
                        btn.prop('disabled', false).text('Confirm Booking');
                    }
                });
            });
        }

        function save_event() {
            var formData = $('#eventForm').serialize();

            // Validate required fields
            if (!$('#event_name').val() || !$('#procedure').val() || !$('#patient_name').val() ||
                !$('#appointment_date').val() || !$('#appointment_time').val() || !$('#docid').val()) {
                alert("Please fill in all required fields.");
                return false;
            }

            var submitButton = $('#eventForm').find('.btn-primary');
            submitButton.prop('disabled', true);
            submitButton.text('Submitting...');

            $.ajax({
                url: "save_event.php",
                type: "POST",
                dataType: 'json',
                data: formData,
                success: function (response) {
                    $('#event_entry_modal').modal('hide');
                    if (response.status === true) {
                        alert(response.msg);
                        if ($('#choose_dentist').val()) {
                            display_events($('#choose_dentist').val());
                        }
                    } else {
                        alert(response.msg);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Error:", error);
                    alert('Error saving event. Please try again.');
                },
                complete: function () {
                    submitButton.prop('disabled', false);
                    submitButton.text('Confirm');
                }
            });
        }
    </script>
    <script>
        // Initialize Select2 for improved dropdown UI
        $(document).ready(function(){
            try {
                $('#choose_branch').select2({
                    placeholder: 'All Branches',
                    allowClear: true,
                    width: '100%'
                });
            } catch(e) {}

            try {
                $('#choose_dentist').select2({
                    placeholder: 'Select a Dentist',
                    allowClear: true,
                    width: '100%'
                });
            } catch(e) {}

            try {
                $('.select2-search').select2({
                    placeholder: 'Select a Patient',
                    allowClear: true,
                    width: '100%'
                });
            } catch(e) {}

            // When a patient is selected, set the branch select to the patient's branch (if present)
            $('#patient_name').on('change', function(){
                var branch = $(this).find('option:selected').data('branch');
                if (branch !== undefined && branch !== null && branch !== '') {
                    // set the branch select value and trigger change so calendar handlers can react
                    $('#choose_branch').val(branch).trigger('change');
                }
            });
        });
    </script>
</body>
</html>
