<?php
session_start();
header('Content-Type: application/json');
if(!isset($_SESSION['user']) || $_SESSION['usertype']!='a'){
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit();
}
require_once '../connection.php';
$appoid = isset($_GET['appoid']) ? (int)$_GET['appoid'] : 0;
if($appoid<=0){ echo json_encode(['success'=>false,'error'=>'Invalid appointment id']); exit(); }
// Fetch all appointment_procedures joined with procedure names
$listRes = $database->query("SELECT ap.id, p.procedure_name, ap.agreed_price, ap.procedure_id FROM appointment_procedures ap INNER JOIN procedures p ON ap.procedure_id=p.procedure_id WHERE ap.appointment_id=$appoid ORDER BY ap.id ASC");
$procedures=[]; $discounts=[];
while($r=$listRes->fetch_assoc()){
    // Treat procedures named exactly as these as discounts
    if(in_array($r['procedure_name'], ['PWD Discount','Senior Citizen Discount'])){
        $discounts[] = $r;
    } else {
        $procedures[] = $r;
    }
}

// total including discounts
$totalRes = $database->query("SELECT COALESCE(SUM(agreed_price),0) AS total FROM appointment_procedures WHERE appointment_id=$appoid");
$totalRow = $totalRes->fetch_assoc();
$total_amount = $totalRow['total'];

echo json_encode(['success'=>true,'procedures'=>$procedures,'discounts'=>$discounts,'total_amount'=>$total_amount]);
