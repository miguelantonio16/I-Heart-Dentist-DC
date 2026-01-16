<?php
session_start();
header('Content-Type: application/json');
if(!isset($_SESSION['user']) || $_SESSION['usertype']!='a'){
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit();
}
require_once '../connection.php';

 $appoid = isset($_POST['appoid']) ? (int)$_POST['appoid'] : 0;
 $procedure_id_raw = isset($_POST['procedure_id']) ? $_POST['procedure_id'] : '';
 $input_price = isset($_POST['agreed_price']) ? trim($_POST['agreed_price']) : '';

 // Detect discount options (sent as special string values from the client)
 $is_discount = false;
 $discount_type = '';
 if (is_string($procedure_id_raw) && strpos($procedure_id_raw, 'discount_') === 0) {
     $is_discount = true;
     $discount_type = $procedure_id_raw; // e.g. discount_pwd or discount_senior
     $procedure_id = 0;
 } else {
     $procedure_id = (int)$procedure_id_raw;
 }

if($appoid<=0){
    echo json_encode(['success'=>false,'error'=>'Invalid appointment.']);
    exit();
}
if(!$is_discount && $procedure_id<=0){
    echo json_encode(['success'=>false,'error'=>'Invalid procedure.']);
    exit();
}

// Verify appointment exists
$apptRes = $database->query("SELECT appoid, procedure_id, total_amount FROM appointment WHERE appoid=$appoid LIMIT 1");
if(!$apptRes || $apptRes->num_rows==0){
    echo json_encode(['success'=>false,'error'=>'Appointment not found.']);
    exit();
}
$apptRow = $apptRes->fetch_assoc();

if($is_discount){
    // Handle discount: create/get a procedure row for this discount type and insert a negative agreed_price equal to 20% of base procedures total.
    $discountName = ($discount_type==='discount_pwd') ? 'PWD Discount' : 'Senior Citizen Discount';

    // Ensure discount procedure exists in procedures table
    $escName = $database->real_escape_string($discountName);
    $procRes = $database->query("SELECT procedure_id FROM procedures WHERE procedure_name='$escName' LIMIT 1");
    if($procRes && $procRes->num_rows>0){
        $procRow = $procRes->fetch_assoc();
        $discProcId = (int)$procRow['procedure_id'];
    } else {
        // Insert a neutral procedure row for the discount
        $database->query("INSERT INTO procedures (procedure_name, price) VALUES ('$escName', 0)");
        $discProcId = (int)$database->insert_id;
    }

    // Check if any discount already applied to this appointment (prevent stacking different discounts)
    $existingDiscRes = $database->query("SELECT COUNT(*) AS c FROM appointment_procedures ap INNER JOIN procedures p ON ap.procedure_id=p.procedure_id WHERE ap.appointment_id=$appoid AND p.procedure_name IN ('PWD Discount','Senior Citizen Discount')");
    if($existingDiscRes){
        $existingRow = $existingDiscRes->fetch_assoc();
        if((int)$existingRow['c']>0){
            echo json_encode(['success'=>false,'error'=>'A discount has already been applied to this appointment. Discounts cannot be stacked.']);
            exit();
        }
    }

    // Prevent duplicate same discount on the same appointment
    $check = $database->query("SELECT id FROM appointment_procedures WHERE appointment_id=$appoid AND procedure_id=$discProcId LIMIT 1");
    if($check && $check->num_rows>0){
        echo json_encode(['success'=>false,'error'=>'This discount has already been applied to the appointment.']);
        exit();
    }

    // Find IDs of both discount procedure rows (so we can exclude them when computing base total)
    $excludeIds = [];
    $allDiscRes = $database->query("SELECT procedure_id, procedure_name FROM procedures WHERE procedure_name IN ('PWD Discount','Senior Citizen Discount')");
    while($r=$allDiscRes->fetch_assoc()){
        $excludeIds[] = (int)$r['procedure_id'];
    }
    $excludeList = count($excludeIds)>0 ? implode(',', $excludeIds) : '0';

    // Compute base total excluding discount entries
    $sql = "SELECT COALESCE(SUM(agreed_price),0) AS base_total FROM appointment_procedures WHERE appointment_id=$appoid";
    if(count($excludeIds)>0){ $sql .= " AND procedure_id NOT IN ($excludeList)"; }
    $baseRes = $database->query($sql);
    $baseRow = $baseRes->fetch_assoc();
    $baseTotal = (float)$baseRow['base_total'];
    if($baseTotal <= 0){
        echo json_encode(['success'=>false,'error'=>'No procedures to apply discount to.']);
        exit();
    }

    $discountAmount = round($baseTotal * 0.20, 2);
    $agreed_price = -1.0 * $discountAmount; // negative to reduce total

    // Insert discount as an appointment_procedures row
    $stmt = $database->prepare("INSERT INTO appointment_procedures (appointment_id, procedure_id, agreed_price) VALUES (?,?,?)");
    $stmt->bind_param('iid', $appoid, $discProcId, $agreed_price);
    if(!$stmt->execute()){
        echo json_encode(['success'=>false,'error'=>'Failed to apply discount.']);
        exit();
    }
    $stmt->close();

    // Recalculate total and return (we skip the normal procedure flow below)
    $totalRes = $database->query("SELECT COALESCE(SUM(agreed_price),0) AS total FROM appointment_procedures WHERE appointment_id=$appoid");
    $totalRow = $totalRes->fetch_assoc();
    $total_amount = $totalRow['total'];
    $database->query("UPDATE appointment SET total_amount=$total_amount WHERE appoid=$appoid");

    $listRes = $database->query("SELECT ap.id, p.procedure_name, ap.agreed_price FROM appointment_procedures ap INNER JOIN procedures p ON ap.procedure_id=p.procedure_id WHERE ap.appointment_id=$appoid ORDER BY ap.id ASC");
    $items=[];while($r=$listRes->fetch_assoc()){ $items[]=$r; }
    echo json_encode(['success'=>true,'procedures'=>$items,'total_amount'=>$total_amount]);
    exit();

} else {
    // Normal procedure flow continues below
    // Fetch procedure
    $procRes = $database->query("SELECT procedure_id, procedure_name FROM procedures WHERE procedure_id=$procedure_id LIMIT 1");
    if(!$procRes || $procRes->num_rows==0){
        echo json_encode(['success'=>false,'error'=>'Procedure not found.']);
        exit();
    }
    $procRow = $procRes->fetch_assoc();
    $procedure_name = $procRow['procedure_name'];

    // Determine agreed price
    if(strcasecmp($procedure_name,'Consultation')===0){
        $agreed_price = 500.00; // fixed
    } else {
        if($input_price===''){ 
            echo json_encode(['success'=>false,'error'=>'Enter a price for the procedure.']);
            exit();
        }
        if(!is_numeric($input_price) || $input_price < 0){
            echo json_encode(['success'=>false,'error'=>'Invalid price value.']);
            exit();
        }
        $agreed_price = (float)$input_price;
    }

// Prevent duplicate identical procedure entries optionally (allow stacking same? keep allowed)
// Insert mapping for normal procedure
$stmt = $database->prepare("INSERT INTO appointment_procedures (appointment_id, procedure_id, agreed_price) VALUES (?,?,?)");
$stmt->bind_param('iid',$appoid,$procedure_id,$agreed_price);
if(!$stmt->execute()){
    echo json_encode(['success'=>false,'error'=>'Failed to add procedure.']);
    exit();
}
$stmt->close();

// After adding a normal procedure, update any existing discount rows so discount amount remains 20% of base total
// Find discount procedure IDs
$discountNames = [ 'PWD Discount', 'Senior Citizen Discount' ];
$discIds = [];
$dnEsc = implode("','", array_map(function($n) use($database){ return $database->real_escape_string($n); }, $discountNames));
$allDiscRes = $database->query("SELECT procedure_id, procedure_name FROM procedures WHERE procedure_name IN ('$dnEsc')");
while($r=$allDiscRes->fetch_assoc()){
    $discIds[(int)$r['procedure_id']] = $r['procedure_name'];
}

if(count($discIds)>0){
    $excludeList = implode(',', array_keys($discIds));
    // compute base total excluding any discount rows
    $baseSql = "SELECT COALESCE(SUM(agreed_price),0) AS base_total FROM appointment_procedures WHERE appointment_id=$appoid";
    if($excludeList){ $baseSql .= " AND procedure_id NOT IN ($excludeList)"; }
    $baseRes = $database->query($baseSql);
    $baseRow = $baseRes->fetch_assoc();
    $baseTotal = (float)$baseRow['base_total'];

    // For each discount procedure present on appointment, update its agreed_price to -20% of baseTotal
    $discountAmount = round($baseTotal * 0.20, 2);
    if($discountAmount>0){
        foreach(array_keys($discIds) as $dprocId){
            // update appointment_procedures rows for this appointment & discount proc id if present
            $upd = $database->query("UPDATE appointment_procedures SET agreed_price=".(-1.0*$discountAmount)." WHERE appointment_id=$appoid AND procedure_id=$dprocId");
        }
    }
}

// Recalculate total (including discounts)
$totalRes = $database->query("SELECT COALESCE(SUM(agreed_price),0) AS total FROM appointment_procedures WHERE appointment_id=$appoid");
$totalRow = $totalRes->fetch_assoc();
$total_amount = $totalRow['total'];

// Legacy procedure_id set if first procedure
$countRes = $database->query("SELECT COUNT(*) AS c FROM appointment_procedures WHERE appointment_id=$appoid");
$countRow = $countRes->fetch_assoc();
if($countRow['c']==1){
    // Set legacy procedure_id to first
    $database->query("UPDATE appointment SET procedure_id=$procedure_id WHERE appoid=$appoid");
}
// Update total
$database->query("UPDATE appointment SET total_amount=$total_amount WHERE appoid=$appoid");

// Fetch list
$listRes = $database->query("SELECT ap.id, p.procedure_name, ap.agreed_price FROM appointment_procedures ap INNER JOIN procedures p ON ap.procedure_id=p.procedure_id WHERE ap.appointment_id=$appoid ORDER BY ap.id ASC");
$items=[];while($r=$listRes->fetch_assoc()){ $items[]=$r; }

echo json_encode(['success'=>true,'procedures'=>$items,'total_amount'=>$total_amount]);
}
