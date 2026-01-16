<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['usertype']!='a') {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../connection.php';

header('Content-Type: application/json');

$appoid = isset($_POST['appoid']) ? (int)$_POST['appoid'] : 0;
$procedure_id = isset($_POST['procedure_id']) ? (int)$_POST['procedure_id'] : 0;
if ($appoid<=0 || $procedure_id<=0){
    echo json_encode(['success'=>false,'error'=>'Invalid input']);
    exit;
}

// Fetch procedure name
$procRes = $database->query("SELECT procedure_name FROM procedures WHERE procedure_id='$procedure_id' LIMIT 1");
if(!$procRes || $procRes->num_rows==0){
    echo json_encode(['success'=>false,'error'=>'Procedure not found']);
    exit;
}
$procRow = $procRes->fetch_assoc();
$name = $procRow['procedure_name'];

// Fixed price only for Consultation
$total_amount = 0;
if (strcasecmp($name,'Consultation')===0){
    $total_amount = 500; // fixed price
}
$amt_fmt = number_format($total_amount,2,'.','');

$upd = $database->query("UPDATE appointment SET procedure_id='$procedure_id', total_amount='$amt_fmt' WHERE appoid='$appoid'");
if($upd){
    echo json_encode(['success'=>true,'procedure_name'=>$name,'total_amount'=>$total_amount]);
} else {
    echo json_encode(['success'=>false,'error'=>'Database update failed']);
}
?>