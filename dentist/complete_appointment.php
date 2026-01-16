<?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'd') {
    header("location: login.php");
    exit();
}

include("../connection.php");

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Update status to completed
    $sql = "UPDATE appointment SET status='completed' WHERE appoid='$id'";
    
    if ($database->query($sql)) {
        require_once __DIR__ . '/../inc/redirect_helper.php';
        // Preserve page/search/sort if provided, otherwise fall back to referrer
        $returnParams = [];
        if (isset($_GET['page'])) { $returnParams['page'] = (int)$_GET['page']; }
        if (isset($_GET['search']) && $_GET['search'] !== '') { $returnParams['search'] = $_GET['search']; }
        if (isset($_GET['sort']) && $_GET['sort'] !== '') { $returnParams['sort'] = $_GET['sort']; }
        if (!empty($returnParams)) {
            redirect_with_context('appointment.php', array_merge(['action'=>'completed'], $returnParams));
        } else {
            redirect_with_context('appointment.php', [], true);
        }
    } else {
        echo "Error updating record: " . $database->error;
    }
} else {
    require_once __DIR__ . '/../inc/redirect_helper.php';
    redirect_with_context('appointment.php');
}
?>