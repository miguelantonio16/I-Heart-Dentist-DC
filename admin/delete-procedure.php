<?php
session_start();
include("../connection.php");

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // First check if the procedure is being used in any appointments
    $check = $database->query("SELECT * FROM appointment WHERE procedure_id = '$id'");
    
    if ($check->num_rows > 0) {
        // Procedure is in use, can't delete
        header("location: settings.php?error=This procedure cannot be deleted as it is associated with existing appointments.");
    } else {
        // Delete the procedure
        $sql = "DELETE FROM procedures WHERE procedure_id = '$id'";
        if ($database->query($sql)) {
            header("location: settings.php?success=Procedure deleted successfully");
        } else {
            header("location: settings.php?error=Failed to delete procedure");
        }
    }
} else {
    header("location: settings.php");
}
?>