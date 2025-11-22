<?php

//learn from w3schools.com

session_start();

if (isset($_SESSION["user"])) {
    if (($_SESSION["user"]) == "" or $_SESSION['usertype'] != 'p') {
        header("location: login.php");
    } else {
        $useremail = $_SESSION["user"];
    }

} else {
    header("location: login.php");
}


//import database
include("../connection.php");
$userrow = $database->query("select * from patient where pemail='$useremail'");
$userfetch = $userrow->fetch_assoc();
$userid = $userfetch["pid"];
$username = $userfetch["pname"];


if ($_POST) {
    if (isset($_POST["booknow"])) {
        $apponum = $_POST["apponum"];
        $scheduleid = $_POST["scheduleid"];
        $date = $_POST["date"];
        $scheduleid = $_POST["scheduleid"];
                // determine branch id from session or patient default
                $branch_id = isset($_SESSION['active_branch_id']) ? (int)$_SESSION['active_branch_id'] : null;
                if (empty($branch_id)) {
                        $bres = $database->query("SELECT branch_id FROM patient WHERE pid='" . $database->real_escape_string($userid) . "' LIMIT 1");
                        if ($bres && $bres->num_rows>0) $branch_id = (int)$bres->fetch_assoc()['branch_id'];
                }
                $branch_sql = is_null($branch_id) ? 'NULL' : "'" . $database->real_escape_string($branch_id) . "'";

                $sql2 = "INSERT INTO appointment (scheduleid, pid, appodate, apponum, branch_id, status) 
                    VALUES ('$scheduleid', '$userid', '$date', '$apponum', $branch_sql, 'booking')";
        $result = $database->query($sql2);
        //echo $apponom;
        header("location: my_booking.php?action=booking-added&id=" . $apponum . "&titleget=none");

    }
}
?>