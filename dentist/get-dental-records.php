<?php
session_start();
include("../connection.php");

if(isset($_GET['pid']) && isset($_SESSION['userid'])) {
    $patient_id = intval($_GET['pid']);
    $docid = intval($_SESSION['userid']);
    
    // Verify dentist-patient relationship
    $verify = $database->query("SELECT * FROM appointment WHERE pid = $patient_id AND docid = $docid LIMIT 1");
    if($verify->num_rows == 0) {
        die("Unauthorized access");
    }
    
    // Get patient name for display
    $patient = $database->query("SELECT pname FROM patient WHERE pid = $patient_id")->fetch_assoc();
    $patient_name = $patient ? $patient['pname'] : 'Patient';
    
    echo "<h3>Dental Records for $patient_name</h3>";
    
    $records = $database->query("SELECT * FROM dental_records 
                               WHERE patient_id = $patient_id AND dentist_id = $docid
                               ORDER BY upload_date DESC");
    
    if($records->num_rows > 0) {
        while($record = $records->fetch_assoc()) {
            echo '<div class="record-item">';
            echo '<a href="'.$record['file_path'].'" target="_blank">';
            echo '<img src="'.$record['file_path'].'" class="record-thumbnail">';
            echo '</a>';
            echo '<p><strong>Uploaded:</strong> '.date('M d, Y h:i A', strtotime($record['upload_date'])).'</p>';
            if(!empty($record['notes'])) {
                echo '<p><strong>Notes:</strong> '.htmlspecialchars($record['notes']).'</p>';
            }
            echo '<a href="'.$record['file_path'].'" download class="download-btn">Download</a>';
            echo '</div>';
        }
    } else {
        echo '<p>No dental records found for this patient.</p>';
    }
} else {
    echo '<p>Invalid request.</p>';
}
?>