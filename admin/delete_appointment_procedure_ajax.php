<?php
session_start();
header('Content-Type: application/json');
if(!isset($_SESSION['user']) || $_SESSION['usertype']!='a'){
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit();
}
require_once '../connection.php';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$appoid = isset($_POST['appoid']) ? (int)$_POST['appoid'] : 0;
if($id<=0 || $appoid<=0){
    echo json_encode(['success'=>false,'error'=>'Invalid parameters.']);
    exit();
}

// Delete row
$delStmt = $database->prepare("DELETE FROM appointment_procedures WHERE id=? AND appointment_id=? LIMIT 1");
$delStmt->bind_param('ii',$id,$appoid);
if(!$delStmt->execute()){
    echo json_encode(['success'=>false,'error'=>'Delete failed.']);
    exit();
}
$delStmt->close();

// Recalculate total and legacy procedure_id
$totalRes = $database->query("SELECT COALESCE(SUM(agreed_price),0) AS total FROM appointment_procedures WHERE appointment_id=$appoid");
$totalRow = $totalRes->fetch_assoc();
$total_amount = $totalRow['total'];

// After deletion, update any existing discount rows so discount amount remains 20% of base total
$discountNames = [ 'PWD Discount', 'Senior Citizen Discount' ];
$discIds = [];
$dnEsc = implode("','", array_map(function($n) use($database){ return $database->real_escape_string($n); }, $discountNames));
$allDiscRes = $database->query("SELECT procedure_id, procedure_name FROM procedures WHERE procedure_name IN ('$dnEsc')");
while($r=$allDiscRes->fetch_assoc()){
    $discIds[(int)$r['procedure_id']] = $r['procedure_name'];
}
if(count($discIds)>0){
    $excludeList = implode(',', array_keys($discIds));
    $baseSql = "SELECT COALESCE(SUM(agreed_price),0) AS base_total FROM appointment_procedures WHERE appointment_id=$appoid";
    if($excludeList){ $baseSql .= " AND procedure_id NOT IN ($excludeList)"; }
    $baseRes = $database->query($baseSql);
    $baseRow = $baseRes->fetch_assoc();
    $baseTotal = (float)$baseRow['base_total'];
    $discountAmount = round($baseTotal * 0.20, 2);
    if($discountAmount>0){
        foreach(array_keys($discIds) as $dprocId){
            $database->query("UPDATE appointment_procedures SET agreed_price=".(-1.0*$discountAmount)." WHERE appointment_id=$appoid AND procedure_id=$dprocId");
        }
    } else {
        // if baseTotal is zero, remove any discount rows (no procedures to discount)
        foreach(array_keys($discIds) as $dprocId){
            $database->query("DELETE FROM appointment_procedures WHERE appointment_id=$appoid AND procedure_id=$dprocId");
        }
    }
    // recalc total after discount updates/removals
    $totalRes = $database->query("SELECT COALESCE(SUM(agreed_price),0) AS total FROM appointment_procedures WHERE appointment_id=$appoid");
    $totalRow = $totalRes->fetch_assoc();
    $total_amount = $totalRow['total'];
}

// Determine new legacy procedure_id
$firstRes = $database->query("SELECT procedure_id FROM appointment_procedures WHERE appointment_id=$appoid ORDER BY id ASC LIMIT 1");
if($firstRes->num_rows>0){
    $firstRow = $firstRes->fetch_assoc();
    $legacy_procedure_id = (int)$firstRow['procedure_id'];
} else {
    $legacy_procedure_id = 0; // none
}
$database->query("UPDATE appointment SET procedure_id=$legacy_procedure_id, total_amount=$total_amount WHERE appoid=$appoid");

// Fetch list
$listRes = $database->query("SELECT ap.id, p.procedure_name, ap.agreed_price FROM appointment_procedures ap INNER JOIN procedures p ON ap.procedure_id=p.procedure_id WHERE ap.appointment_id=$appoid ORDER BY ap.id ASC");
$items=[];while($r=$listRes->fetch_assoc()){ $items[]=$r; }

echo json_encode(['success'=>true,'procedures'=>$items,'total_amount'=>$total_amount]);
