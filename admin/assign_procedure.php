<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['usertype']!='a') { header('Location: ../login.php'); exit(); }
include('../connection.php');

$appoid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($appoid <= 0) { die('Invalid appointment id'); }

// Determine where to go back after assigning (booking by default)
$redirectAfter = (isset($_GET['source']) && $_GET['source'] === 'appointment') ? 'appointment.php' : 'booking.php';

// Fetch appointment basic info
$apptRes = $database->query("SELECT a.appoid,a.procedure_id,a.total_amount,a.pid,a.docid,a.appodate,a.appointment_time, p.pname, d.docname FROM appointment a LEFT JOIN patient p ON a.pid=p.pid LEFT JOIN doctor d ON a.docid=d.docid WHERE a.appoid='$appoid' LIMIT 1");
if (!$apptRes || $apptRes->num_rows==0) { die('Appointment not found'); }
$appt = $apptRes->fetch_assoc();

$procRes = $database->query("SELECT procedure_id, procedure_name, price FROM procedures WHERE procedure_name NOT IN ('PWD Discount','Senior Citizen Discount') ORDER BY procedure_name ASC");
$procedures = [];
while ($procRes && $r=$procRes->fetch_assoc()) { $procedures[] = $r; }

$message='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $proc_id = isset($_POST['procedure_id']) ? (int)$_POST['procedure_id'] : 0;
    $raw_price = isset($_POST['agreed_price']) ? trim($_POST['agreed_price']) : '';
    $agreed = 0;
    if ($proc_id>0) {
        // default from procedure
        foreach ($procedures as $p) { if ($p['procedure_id']==$proc_id) { $agreed = (float)$p['price']; break; } }
    }
    if ($raw_price!=='') {
        $tmp = str_replace([',','₱',' '], '', $raw_price);
        if (is_numeric($tmp)) { $val = (float)$tmp; if ($val>0) $agreed = $val; }
    }
    if ($proc_id<=0) { $message='Please select a procedure.'; }
    else {
        $agreed_fmt = number_format($agreed,2,'.','');
        $upd = $database->query("UPDATE appointment SET procedure_id='$proc_id', total_amount='$agreed_fmt' WHERE appoid='$appoid'");
        if ($upd) {
            header("Location: {$redirectAfter}?assigned=1");
            exit();
        } else {
            $message='Database update failed: '.$database->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Assign Procedure</title>
<link rel="stylesheet" href="../css/main.css" />
<link rel="stylesheet" href="../css/admin.css" />
<style>
.container {max-width:640px;margin:40px auto;padding:24px;background:#fff;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.08);} 
h1{margin-top:0;font-size:22px;} .row{margin-bottom:16px;} label{font-weight:600;display:block;margin-bottom:6px;} input[type=text],input[type=number],select{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;} .btn{padding:10px 18px;border:none;border-radius:4px;cursor:pointer;font-weight:600;} .btn-primary{background:#1e88e5;color:#fff;} .btn-secondary{background:#eee;} .msg{margin-bottom:12px;color:#c00;font-weight:600;} .info-box{background:#f5faff;border:1px solid #d0e7ff;padding:12px;border-radius:6px;font-size:14px;}
</style>
</head>
<body>
<div class="container">
    <h1>Assign Procedure to Appointment #<?php echo htmlspecialchars($appt['appoid']); ?></h1>
    <div class="info-box">
        <strong>Patient:</strong> <?php echo htmlspecialchars($appt['pname']); ?><br>
        <strong>Dentist:</strong> <?php echo htmlspecialchars($appt['docname']); ?><br>
        <strong>Date:</strong> <?php echo htmlspecialchars($appt['appodate']); ?> @ <?php echo htmlspecialchars($appt['appointment_time']); ?><br>
        <strong>Current Status:</strong> <?php echo htmlspecialchars($appt['procedure_id'] ? 'Procedure Set' : 'Pending Evaluation'); ?>
    </div>
    <?php if($message): ?><div class="msg"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <form method="POST">
        <div class="row">
            <label for="procedure_id">Procedure</label>
            <select name="procedure_id" id="procedure_id" required>
                <option value="">Select Procedure</option>
                <?php foreach($procedures as $p): ?>
                    <option value="<?php echo (int)$p['procedure_id']; ?>" data-base="<?php echo htmlspecialchars($p['price']); ?>"><?php echo htmlspecialchars($p['procedure_name']); ?> (Base ₱<?php echo number_format((float)$p['price'],2); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row">
            <label for="agreed_price">Agreed Price (optional override)</label>
            <input type="text" name="agreed_price" id="agreed_price" placeholder="e.g. 1500" />
        </div>
        <div class="row" style="display:flex;gap:12px;">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="<?php echo htmlspecialchars($redirectAfter); ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<script>
const sel = document.getElementById('procedure_id');
const agreed = document.getElementById('agreed_price');
if (sel) {
  sel.addEventListener('change', ()=>{
    const opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.base) {
      if (!agreed.value) agreed.value = opt.dataset.base;
    }
  });
}
</script>
</body>
</html>
