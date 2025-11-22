<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../../Media/white-icon/white-IHeartDentistDC_Logo.png" type="image/png">
    <!-- *Note: You must have internet connection on your laptop or pc other wise below code is not working -->
    <!-- CSS for full calender -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.css" rel="stylesheet" />
    <!-- JS for jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <!-- JS for full calender -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.20.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.js"></script>
    <!-- bootstrap css and js -->
    <!--<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
-->
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>

    <link rel="stylesheet" href="../../css/calendar.css">
    <link rel="stylesheet" href="../../css/admin.css">
    <link rel="stylesheet" href="../../css/animations.css">
    <link rel="stylesheet" href="../../css/main.css">
    <link rel="stylesheet" href="../../css/dashboard.css">

    <title>Calendar - IHeartDentistDC</title>
    <link rel="icon" href="../../Media/Icon/logo.png" type="image/png">
    <style>
        .popup {
            animation: transitionIn-Y-bottom 0.5s;
        }

        .sub-table {
            animation: transitionIn-Y-bottom 0.5s;
        }

        #confirmationModal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            /* Overlay */
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            /* Ensure it sits above other content */
        }

        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 1000px;
            width: 100%;
            text-align: center;
            margin: 0 auto;
        }

        .modal-buttons {
            margin-top: 20px;
        }

        .btn-primary {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
            margin: 0 10px;
        }

        .btn-secondary {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 5px;
        }
        #calendar{
            height: 83.9%;
        }
    </style>
</head>

<body>
    <?php

    //learn from w3schools.com
    date_default_timezone_set('Asia/Singapore');
    session_start();

    if (isset($_SESSION["user"])) {
        if (($_SESSION["user"]) == "" or $_SESSION['usertype'] != 'd') {
            header("location: ../login.php");
        } else {
            $useremail = $_SESSION["user"];
        }

    } else {
        header("location: ../../dentist/login.php");
    }


    //import database
    include("../../connection.php");
    $userrow = $database->query("select * from doctor where docemail='$useremail'");
    $userfetch = $userrow->fetch_assoc();
    $userid = $userfetch["docid"];
    $username = $userfetch["docname"];

    // Get counts for dashboard
    $patientrow = $database->query("SELECT COUNT(DISTINCT pid) FROM appointment WHERE docid='$userid'");
    $appointmentrow = $database->query("SELECT COUNT(*) FROM appointment WHERE status='booking' AND docid='$userid'");
    $schedulerow = $database->query("SELECT COUNT(*) FROM appointment WHERE status='appointment' AND docid='$userid'");


    $today = date('Y-m-d');
    $currentMonth = date('F');
    $currentYear = date('Y');
    $daysInMonth = date('t');
    $firstDayOfMonth = date('N', strtotime("$currentYear-" . date('m') . "-01"));
    $currentDay = date('j');


    //echo $userid;
    //echo $username;
    
    ?>
    <div class="nav-container">
        <div class="sidebar">
            <div class="sidebar-logo">
                <img src="../../Media/Icon/logo.png" alt="IHeartDentistDC Logo">
            </div>

            <div class="user-profile">
                <div class="profile-image">
                    <?php
                    $userphoto = $userfetch["photo"];

                    if (!empty($userphoto) && file_exists("../../admin/uploads/" . $userphoto)) {
                        $photopath = "../../admin/uploads/" . $userphoto;
                    } else {
                        $photopath = "../../Media/Icon/Blue/profile.png";
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
                <a href="../dashboard.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/home.png" alt="Home" class="nav-icon">
                    <span class="nav-label">Dashboard</span>
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
                <a href="../patient.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/care.png" alt="Patient" class="nav-icon">
                    <span class="nav-label">Patient</span>
                </a>
                <a href="../dentist-records.php" class="nav-item">
                    <img src="../../Media/Icon/Blue/edit.png" alt="Records" class="nav-icon">
                    <span class="nav-label">Records</span>
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
                                <div id="calendar"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Start popup dialog box -->
                    <div class="modal fade" id="event_entry_modal" tabindex="-1" role="dialog" aria-labelledby="modalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-md" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">x</span>
                                    </button>
                                </div>

                                <div class="modal-body">
                                    <h5 class="modal-title" id="modalLabel">Bookings</h5>
                                    <table width="93%" class="sub-table scrolldown" border="0">
                                        <thead>
                                            <tr>
                                                <th class="table-headin">Patient Name</th>
                                                <th class="table-headin">Procedure</th>
                                                <th class="table-headin">Date & Time</th>
                                                <th class="table-headin">Events</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $sqlmain = "SELECT 
                                                appointment.appoid, 
                                                procedures.procedure_name, 
                                                patient.pname, 
                                                appointment.appodate, 
                                                appointment.appointment_time 
                                            FROM appointment
                                            INNER JOIN patient ON appointment.pid = patient.pid
                                            INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
                                            WHERE appointment.docid = '$userid' 
                                            AND appointment.status = 'booking'";


                                            $result = $database->query($sqlmain);
                                            if ($result->num_rows == 0) {
                                                echo '<tr>
                                                            <td colspan="4">
                                                                <center>
                                                                    <img src="../img/notfound.svg" width="25%">
                                                                    <p class="heading-main12" style="font-size:20px;color:rgb(49, 49, 49)">No pending bookings found!</p>
                                                                </center>
                                                            </td>
                                                        </tr>';
                                            } else {
                                                while ($row = $result->fetch_assoc()) {
                                                    $appoid = $row["appoid"];
                                                    $procedure_name = $row["procedure_name"];
                                                    $pname = $row["pname"];
                                                    $appodate = $row["appodate"];
                                                    $appointment_time = $row["appointment_time"];

                                                    echo '<tr id="row-' . $appoid . '">
                                                            <td>' . $pname . '</td>
                                                            <td>' . $procedure_name . '</td>
                                                            <td>' . $appodate . ' @ ' . $appointment_time . '</td>
                                                            <td>
                                                                <a href="#" onclick="updateBooking(' . $appoid . ', \'accept\')" class="btn-primary-soft btn">Accept</a>
                                                                <a href="#" onclick="updateBooking(' . $appoid . ', \'reject\')" class="btn-primary-soft btn">Reject</a>
                                                            </td>
                                                        </tr>';
                                                }
                                            }

                                            ?>
                                        </tbody>
                                    </table>
                                    <h5 class="modal-title" id="modalLabel">Appointments</h5>
                                    <table width="93%" class="sub-table scrolldown" border="0">
                                        <thead>
                                            <tr>
                                                <th class="table-headin">
                                                    Patient name
                                                </th>
                                                <th class="table-headin">

                                                    Procedure

                                                </th>

                                                <th class="table-headin">


                                                    Date

                                                </th>

                                                <th class="table-headin">

                                                    Time

                                                </th>

                                                <th class="table-headin">

                                                    Events
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>

                                            <?php
                                            $sqlmain = "SELECT appointment.appoid, procedures.procedure_name, patient.pname, appointment.appodate, appointment.appointment_time 
                                            FROM appointment
                                            INNER JOIN patient ON appointment.pid = patient.pid
                                            INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
                                            WHERE appointment.docid = '$userid' AND appointment.status = 'appointment'
                                            ORDER BY appointment.appodate, appointment.appointment_time";

                                            if (isset($_POST['filter'])) {  // Checks if the Filter button was clicked
                                                $filterDate = $_POST['appodate'];  // Gets the selected date from the form
                                            
                                                if (!empty($filterDate)) {  // Ensures the date is not empty
                                                    $sqlmain .= " AND appointment.appodate = '$filterDate'";  // Adds a condition to fetch only appointments on that date
                                                }
                                            }


                                            $result = $database->query($sqlmain);

                                            if ($result->num_rows == 0) {
                                                echo "<tr><td colspan='5'>No appointments found.</td></tr>";
                                            } else {
                                                while ($row = $result->fetch_assoc()) {
                                                    $appoid = $row["appoid"];
                                                    $procedure_name = $row["procedure_name"];
                                                    $pname = $row["pname"];
                                                    $appodate = $row["appodate"];
                                                    $appointment_time = $row["appointment_time"];

                                                    echo '<tr id="row-' . $appoid . '">
                                                        <td>' . $pname . '</td>
                                                        <td>' . $procedure_name . '</td>
                                                        <td>' . $appodate . '</td>
                                                        <td>' . $appointment_time . '</td>
                                                        <td>
                                                            <form method="POST" action="?action=drop&id=' . $appoid . '&name=' . $pname . '" style="display:inline;">
            <input type="hidden" name="cancel_id" value="' . $appoid . '">
            <button type="submit" class="btn-primary-soft btn button-icon btn-delete" style="padding-left: 40px; padding-top: 10px; padding-bottom: 10px; margin-top: 10px; margin-bottom: 10px;">
                <font class="tn-in-text">Cancel</font>
            </button>
        </form>

                                                        </td>
                                                    </tr>';
                                                }
                                            }
                                            ?>

                                        </tbody>

                                    </table>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary" onclick="save_event()">Save Event</button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div> 
                <div class="right-sidebar">
                    <div class="stats-section">
                        <div class="stats-container">
                            <!-- First row -->
                            <a href="../patient.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php echo $patientrow->fetch_row()[0] ?? 0; ?></h1>
                                        <p class="stat-label">My Patients</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../../Media/Icon/Blue/care.png" alt="Patients Icon">
                                    </div>
                                </div>
                            </a>


                            <!-- Second row -->
                            <a href="../booking.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php
                                        $bookingCount = $appointmentrow->fetch_row()[0] ?? 0;
                                        echo $bookingCount;
                                        ?></h1>
                                        <p class="stat-label">Bookings</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../../Media/Icon/Blue/booking.png" alt="Booking Icon">
                                        <?php if ($bookingCount > 0): ?>
                                            <span class="notification-badge"><?php echo $bookingCount; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>


                            <a href="../appointment.php" class="stat-box-link">
                                <div class="stat-box">
                                    <div class="stat-content">
                                        <h1 class="stat-number"><?php
                                        $appointmentCount = $schedulerow->fetch_row()[0] ?? 0;
                                        echo $appointmentCount;
                                        ?></h1>
                                        <p class="stat-label">Appointments</p>
                                    </div>
                                    <div class="stat-icon">
                                        <img src="../../Media/Icon/Blue/appointment.png" alt="Appointment Icon">
                                        <?php if ($appointmentCount > 0): ?>
                                            <span class="notification-badge"><?php echo $appointmentCount; ?></span>
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
                                    patient.pname AS patient_name,
                                    appointment.appodate,
                                    appointment.appointment_time,
                                    procedures.procedure_name
                                FROM appointment
                                INNER JOIN patient ON appointment.pid = patient.pid
                                INNER JOIN procedures ON appointment.procedure_id = procedures.procedure_id
                                WHERE
                                    appointment.docid = '$userid'
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

        <div id="confirmationModal" class="modal" style="display: none;">
            <div class="modal-content">
                <p id="modalMessage">Are you sure you want to proceed?</p>
                <div class="modal-buttons">
                    <button onclick="confirmAction()" class="btn-primary">Yes</button>
                    <button onclick="closeModal()" class="btn-secondary">No</button>
                </div>
            </div>
        </div>
        <!-- End popup dialog box -->
        <script>
            $(document).ready(function () {
                display_events();
            }); //end document.ready block

            function display_events() {
                var events = new Array();
                $.ajax({
                    url: 'display_event.php',
                    dataType: 'json',
                    success: function (response) {

                        var result = response.data;
                        $.each(result, function (i, item) {
                            var eventColor = '#0E8923';  // green for appointments

                            // Assign green color for "booking" status
                            if (result[i].status === 'booking') {
                                eventColor = '#F7BD01';  // yellow for booking requests
                            }

                            events.push({
                                event_id: result[i].event_id,
                                title: result[i].title,
                                start: result[i].start,
                                end: result[i].end,
                                color: eventColor,
                                url: result[i].url,
                                procedure_name: result[i].procedure_name || 'N/A',
                                patient_name: result[i].patient_name || 'N/A',
                                dentist_name: result[i].dentist_name || 'N/A',
                                status: result[i].status || 'N/A'
                            });

                        })
                        var calendar = $('#calendar').fullCalendar({
                            defaultView: 'month',
                            timeZone: 'local',
                            editable: true,
                            selectable: true,
                            selectHelper: true,
                            select: function (start, end) {
                                //alert(start);
                                //alert(end);
                                //	$('#event_start_date').val(moment(start).format('YYYY-MM-DD'));
                                //$('#event_end_date').val(moment(start).format('YYYY-MM-DD'));
                                //	$('#event_entry_modal').modal('show');
                            },
                            events: events,
                            eventRender: function (event, element) {
                                element.on('click', function () {
                                    console.log("Event clicked: ", event);
                                    $('#modalProcedureName').text(event.procedure_name || 'N/A');
                                    $('#modalPatientName').text(event.patient_name || 'N/A');
                                    $('#modalDentistName').text(event.dentist_name || 'N/A');
                                    $('#modalDate').text(event.start ? new Date(event.start).toLocaleDateString() : 'N/A');
                                    $('#modalTime').text(event.start ? new Date(event.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'N/A');
                                    $('#modalStatus').text(event.status === 'appointment' ? 'Appointment Confirmed' : 'Booking');

                                    // Ensure the modal is properly called
                                    $('#appointmentModal').modal('show');
                                });

                                element.css('background-color', event.color);
                            },
                            dayRender: function (date, cell) {
                                // No hard-coded weekday coloring here; non-working days handled from server-side data
                            }
                        }); //end fullCalendar block	
                    },//end success block
                    error: function (xhr, status) {
                        alert(response.msg);
                    }
                });//end ajax block	
            }

            function save_event() {
                var event_name = $("#event_name").val();
                var event_start_date = $("#event_start_date").val();
                var event_end_date = $("#event_end_date").val();
                if (event_name == "" || event_start_date == "" || event_end_date == "") {
                    alert("Please enter all required details.");
                    return false;
                }
                $.ajax({
                    url: "save_event.php",
                    type: "POST",
                    dataType: 'json',
                    data: { event_name: event_name, event_start_date: event_start_date, event_end_date: event_end_date },
                    success: function (response) {
                        $('#event_entry_modal').modal('hide');
                        if (response.status == true) {
                            alert(response.msg);
                            location.reload();
                        }
                        else {
                            alert(response.msg);
                        }
                    },
                    error: function (xhr, status) {
                        console.log('ajax error = ' + xhr.statusText);
                        alert(response.msg);
                    }
                });
                return false;
            }

            let currentAppoid = null;
            let currentAction = null;

            function updateBooking(appoid, action) {
                currentAppoid = appoid;
                currentAction = action;
                $('#event_entry_modal').modal('hide');
                document.getElementById("modalMessage").textContent = `Are you sure you want to ${action} this booking?`;
                document.getElementById("confirmationModal").style.display = "flex";
            }

            function confirmAction() {
                fetch(`booking.php?action=${currentAction}&id=${currentAppoid}`)
                    .then(response => {
                        if (response.ok) {
                            document.getElementById(`row-${currentAppoid}`).remove();
                            closeModal();
                        } else {
                            alert("Failed to update booking. Please try again.");
                            closeModal();
                        }
                    })
                    .catch(err => {
                        console.error("Error:", err);
                        closeModal();
                    });
            }

            function closeModal() {
                document.getElementById("confirmationModal").style.display = "none";
                currentAppoid = null;
                currentAction = null;
            }
        </script>
    </div>

    <div class="modal fade" id="appointmentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p><strong>Procedure:</strong> <span id="modalProcedureName"></span></p>
                    <p><strong>Patient:</strong> <span id="modalPatientName"></span></p>
                    <p><strong>Dentist:</strong> <span id="modalDentistName"></span></p>
                    <p><strong>Date:</strong> <span id="modalDate"></span></p>
                    <p><strong>Time:</strong> <span id="modalTime"></span></p>
                    <p><strong>Status:</strong> <span id="modalStatus"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

</body>

</html>

