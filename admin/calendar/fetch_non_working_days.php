<?php
include 'database_connection.php';

$sql = "SELECT * FROM non_working_days";
$result = $con->query($sql);
$nonWorkingDays = [];

while ($row = $result->fetch_assoc()) {
    $nonWorkingDays[] = [
        "date" => $row["date"],
        "description" => $row["description"],
        "type" => "non-working" // Add this type field
    ];
    
}

echo json_encode(["status" => true, "non_working_days" => $nonWorkingDays]);
?>
