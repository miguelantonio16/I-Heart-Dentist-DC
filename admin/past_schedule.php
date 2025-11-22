<?php
session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" || $_SESSION['usertype'] != 'a') {
        header("location: ../login.php");
    }
} else {
    header("location: ../login.php");
}

include("../connection.php");

// Automatically update session statuses for past dates
$today = date('Y-m-d');
$database->query("UPDATE schedule SET status = 'inactive' WHERE scheduledate < '$today'");

// Fetch only inactive (past) sessions, no join with doctor table
$sqlmain = "SELECT schedule.scheduleid, schedule.title, schedule.scheduledate, schedule.scheduletime, schedule.nop, schedule.status 
            FROM schedule 
            WHERE schedule.status = 'inactive'
            ORDER BY schedule.scheduledate DESC";

$result = $database->query($sqlmain);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../Media/white-icon/white-IHeartDentistDC_Logo.png" type="image/png">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
    <title>Past Schedules</title>
    <style>
        .popup {
            animation: transitionIn-Y-bottom 0.5s;
        }
        .sub-table {
            animation: transitionIn-Y-bottom 0.5s;
        }
    </style>
</head>
<body>
    <div class="nav-container">
        <div class="menu">
            <table class="menu-container" border="0">
            <tr>
                    <td style="padding:10px" colspan="2">
                        <table border="0" class="profile-container">
                            <tr>
                                <td>
                                    <img class="profile-pic" src="../Media/Icon/logo.png" alt="">
                                </td>
                                <td>
                                    <p class="profile-name">I Heart Dentist Dental Clinic</p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                <a href="logout.php" ><input type="button" value="Log out" class="logout-btn btn-primary-soft btn"></a>
                                <br><br>
                                    <?php
if (isset($_SESSION['temporary_admin']) && $_SESSION['temporary_admin']) {
    echo '<a href="switch_back_to_dentist.php"><input type="button" value="Go Back to Dentist View" class="btn-primary-soft btn"></a>';
}
?>    
                            </td>
                            </tr>
                    </table>
                    </td>
                
                </tr>
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-dashbord" >
                        <a href="dashboard.php" class="non-style-link-menu"><div><p class="menu-text">Dashboard</p></a></div></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="dentist.php" class="non-style-link-menu"><div><p class="menu-text">Dentists</p></a></div>
                    </td>
                </tr>
                
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-schedule menu-active menu-icon-schedule-active">
                        <a href="schedule.php" class="non-style-link-menu non-style-link-menu-active"><div><p class="menu-text">Sessions</p></div></a>
                    </td>
                </tr>
            
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appointment">
                        <a href="appointment.php" class="non-style-link-menu">
                            <div>
                                <p class="menu-text">Appointment</p>
                            </div>
                        </a>
                    </td>
                </tr>
               
                <tr class="menu-row" >
                    <td class="menu-btn menu-icon-patient">
                        <a href="patient.php" class="non-style-link-menu"><div><p class="menu-text">Patients</p></a></div>
                    </td>
                </tr>

                <tr class="menu-row">
                    <td class="menu-btn menu-icon-calendar">
                        <a href="calendar/calendar.php" class="non-style-link-menu">
                            <div>
                                <p class="menu-text">Calendar</p>
                            </div>
                        </a>
                    </td>
                </tr>

          
            </table>
        </div>
        <div class="dash-body">
            <table border="0" width="100%">
                <tr>
                    <td width="13%">
                        <a href="schedule.php">
                            <button class="login-btn btn-primary-soft btn btn-icon-back" style="margin-left: 20px;">Back</button>
                        </a>
                    </td>
                    <td>
                        <p style="font-size: 23px;">Past Schedules</p>
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="padding-top:10px;">
                        <center>
                            <div class="abc scroll">
                                <table class="sub-table" width="93%" border="0">
                                    <thead>
                                        <tr>
                                            <th>Session Title</th>
                                            <th>Scheduled Date & Time</th>
                                            <th>Max Bookings</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($result->num_rows > 0): ?>
                                            <?php while ($row = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo $row["title"]; ?></td>
                                                    <td><?php echo $row["scheduledate"] . " " . $row["scheduletime"]; ?></td>
                                                    <td><?php echo $row["nop"]; ?></td>
                                                    <td><?php echo ucfirst($row["status"]); ?></td>
                                                    <td>
                                                        <a href="?action=view&id=<?php echo $row["scheduleid"]; ?>" class="non-style-link">
                                                        </a>
                                                        <br><br>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" style="text-align: center;">No past schedules found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </center>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>

