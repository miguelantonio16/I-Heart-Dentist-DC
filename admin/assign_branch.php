<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['usertype'] !== 'a') {
    header('Location: ../admin/login.php');
    exit;
}

require_once '../connection.php';

$csrf = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16));
$_SESSION['csrf_token'] = $csrf;

$branches = [];
$r = $database->query("SELECT * FROM branches ORDER BY name");
while ($row = $r->fetch_assoc()) { $branches[] = $row; }

$doctors = [];
$r = $database->query("SELECT docid, docname AS name FROM doctor ORDER BY docname");
while ($row = $r->fetch_assoc()) { $doctors[] = $row; }

$patients = [];
$r = $database->query("SELECT pid, pname AS name FROM patient ORDER BY pname");
while ($row = $r->fetch_assoc()) { $patients[] = $row; }

$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';
$focus = isset($_GET['focus']) ? $_GET['focus'] : '';

// Render only inner form when requested via AJAX (modal)
if ($isAjax) {
    $kind = isset($_GET['kind']) ? $_GET['kind'] : '';
    $itemId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($kind && $itemId) {
        // render a single-doctor or single-patient assign form
        if ($kind === 'doctor') {
            $res = $database->query("SELECT docid, docname FROM doctor WHERE docid='".$itemId."'");
            if (!$res || $res->num_rows == 0) { echo '<div class="popup"><div class="content">Doctor not found</div></div>'; exit; }
            $doc = $res->fetch_assoc();
            ?>
            <div class="popup" style="max-height:80vh;overflow:auto;">
                <center>
                    <h2>Assign Branches — <?php echo htmlspecialchars($doc['docname']); ?></h2>
                    <a class="close" href="#">&times;</a>
                    <div class="content">
                        <form method="post" action="assign_branch_action.php" id="assign-branch-form-ajax">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <?php foreach ($branches as $b):
                                $checked = '';
                                $q = $database->query("SELECT 1 FROM doctor_branches WHERE docid='".$database->real_escape_string($doc['docid'])."' AND branch_id='".$database->real_escape_string($b['id'])."'");
                                if ($q && $q->num_rows) $checked = 'checked';
                            ?>
                                <div style="padding:6px 0;text-align:left"><label><input type="checkbox" name="doctor_<?php echo $doc['docid']; ?>[]" value="<?php echo $b['id']; ?>" <?php echo $checked; ?>> <?php echo htmlspecialchars($b['name']); ?></label></div>
                            <?php endforeach; ?>
                            <div style="margin-top:12px;text-align:right">
                                <button type="submit" class="btn btn-primary" id="assign-branch-save">Save</button>
                                <button type="button" class="btn btn-secondary" id="assign-branch-cancel">Cancel</button>
                            </div>
                        </form>
                    </div>
                </center>
            </div>
            <script>
            (function(){
                var form = document.getElementById('assign-branch-form-ajax');
                var cancel = document.getElementById('assign-branch-cancel');
                if (cancel) cancel.addEventListener('click', function(){ var overlay = document.getElementById('assign-branch-overlay'); if (overlay) overlay.remove(); });
                var closeAnch = document.querySelector('.popup .close'); if (closeAnch) closeAnch.addEventListener('click', function(e){ e.preventDefault(); var overlay = document.getElementById('assign-branch-overlay'); if (overlay) overlay.remove(); });
                form.addEventListener('submit', function(e){
                    e.preventDefault(); var btn = document.getElementById('assign-branch-save'); if (btn) btn.disabled = true; var fd = new FormData(form);
                    fetch('assign_branch_action.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function(r){ return r.json(); }).then(function(json){ if (btn) btn.disabled = false; if (json && json.status) { var overlay = document.getElementById('assign-branch-overlay'); if (overlay) overlay.remove(); location.reload(); } else { alert(json.msg || 'Failed to save'); } }).catch(function(){ if (btn) btn.disabled = false; alert('Network error'); });
                });
            })();
            </script>
            <?php
            exit;
        } elseif ($kind === 'patient') {
            $res = $database->query("SELECT pid, pname FROM patient WHERE pid='".$itemId."'");
            if (!$res || $res->num_rows == 0) { echo '<div class="popup"><div class="content">Patient not found</div></div>'; exit; }
            $p = $res->fetch_assoc();
            ?>
            <div class="popup" style="max-height:80vh;overflow:auto;">
                <center>
                    <h2>Assign Branches — <?php echo htmlspecialchars($p['pname']); ?></h2>
                    <a class="close" href="#">&times;</a>
                    <div class="content">
                        <form method="post" action="assign_branch_action.php" id="assign-branch-form-ajax">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <?php foreach ($branches as $b):
                                $checked = '';
                                $q = $database->query("SELECT 1 FROM patient_branches WHERE pid='".$database->real_escape_string($p['pid'])."' AND branch_id='".$database->real_escape_string($b['id'])."'");
                                if ($q && $q->num_rows) $checked = 'checked';
                            ?>
                                <div style="padding:6px 0;text-align:left"><label><input type="checkbox" name="patient_<?php echo $p['pid']; ?>[]" value="<?php echo $b['id']; ?>" <?php echo $checked; ?>> <?php echo htmlspecialchars($b['name']); ?></label></div>
                            <?php endforeach; ?>
                            <div style="margin-top:12px;text-align:right">
                                <button type="submit" class="btn btn-primary" id="assign-branch-save">Save</button>
                                <button type="button" class="btn btn-secondary" id="assign-branch-cancel">Cancel</button>
                            </div>
                        </form>
                    </div>
                </center>
            </div>
            <script>
            (function(){
                var form = document.getElementById('assign-branch-form-ajax');
                var cancel = document.getElementById('assign-branch-cancel');
                if (cancel) cancel.addEventListener('click', function(){ var overlay = document.getElementById('assign-branch-overlay'); if (overlay) overlay.remove(); });
                var closeAnch = document.querySelector('.popup .close'); if (closeAnch) closeAnch.addEventListener('click', function(e){ e.preventDefault(); var overlay = document.getElementById('assign-branch-overlay'); if (overlay) overlay.remove(); });
                form.addEventListener('submit', function(e){
                    e.preventDefault(); var btn = document.getElementById('assign-branch-save'); if (btn) btn.disabled = true; var fd = new FormData(form);
                    fetch('assign_branch_action.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function(r){ return r.json(); }).then(function(json){ if (btn) btn.disabled = false; if (json && json.status) { var overlay = document.getElementById('assign-branch-overlay'); if (overlay) overlay.remove(); location.reload(); } else { alert(json.msg || 'Failed to save'); } }).catch(function(){ if (btn) btn.disabled = false; alert('Network error'); });
                });
            })();
            </script>
            <?php
            exit;
        }
    }

    // Fallback: render full lists (existing behavior)
    ?>
    <div class="popup" style="max-height:80vh;overflow:auto;">
        <center>
            <h2>Assign Branches to Doctors &amp; Patients</h2>
            <a class="close" href="#">&times;</a>
            <div class="content">
                <form method="post" action="assign_branch_action.php" id="assign-branch-form-ajax">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">

                    <h3>Doctors</h3>
                    <div style="overflow:auto;max-height:300px;padding-bottom:6px;border-bottom:1px solid #eee;margin-bottom:12px;">
            <?php foreach ($doctors as $doc): ?>
                <div id="doc-<?php echo $doc['docid']; ?>" style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #fafafa;">
                    <div style="flex:1;padding-right:12px;font-weight:600;text-align:center"><?php echo htmlspecialchars($doc['name']); ?></div>
                    <div style="flex:2;text-align:right">
                        <?php foreach ($branches as $b):
                            $checked = '';
                            $q = $database->query("SELECT 1 FROM doctor_branches WHERE docid='".$database->real_escape_string($doc['docid'])."' AND branch_id='".$database->real_escape_string($b['id'])."'");
                            if ($q && $q->num_rows) $checked = 'checked';
                        ?>
                            <label style="margin-left:8px"><input type="checkbox" name="doctor_<?php echo $doc['docid']; ?>[]" value="<?php echo $b['id']; ?>" <?php echo $checked; ?>> <?php echo htmlspecialchars($b['name']); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>

            <h3>Patients</h3>
            <div style="overflow:auto;max-height:300px;padding-bottom:6px;">
            <?php foreach ($patients as $p): ?>
                <div id="pat-<?php echo $p['pid']; ?>" style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #fafafa;">
                    <div style="flex:1;padding-right:12px;text-align:center"><?php echo htmlspecialchars($p['name']); ?></div>
                    <div style="flex:2;text-align:right">
                        <?php foreach ($branches as $b):
                            $checked = '';
                            $q = $database->query("SELECT 1 FROM patient_branches WHERE pid='".$database->real_escape_string($p['pid'])."' AND branch_id='".$database->real_escape_string($b['id'])."'");
                            if ($q && $q->num_rows) $checked = 'checked';
                        ?>
                            <label style="margin-left:8px"><input type="checkbox" name="patient_<?php echo $p['pid']; ?>[]" value="<?php echo $b['id']; ?>" <?php echo $checked; ?>> <?php echo htmlspecialchars($b['name']); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>

            <div style="margin-top:12px;text-align:right">
                <button type="submit" class="btn btn-primary" id="assign-branch-save">Save Assignments</button>
                <button type="button" class="btn btn-secondary" id="assign-branch-cancel">Cancel</button>
            </div>
        </form>
            </div>
        </center>
    </div>

    <script>
    (function(){
        var form = document.getElementById('assign-branch-form-ajax');
        var cancel = document.getElementById('assign-branch-cancel');
        if (cancel) cancel.addEventListener('click', function(){ var overlay = document.getElementById('assign-branch-overlay'); if (overlay) overlay.remove(); });
        var closeAnch = document.querySelector('.popup .close'); if (closeAnch) closeAnch.addEventListener('click', function(e){ e.preventDefault(); var overlay = document.getElementById('assign-branch-overlay'); if (overlay) overlay.remove(); });
        form.addEventListener('submit', function(e){ e.preventDefault(); var btn = document.getElementById('assign-branch-save'); if (btn) btn.disabled = true; var fd = new FormData(form); fetch('assign_branch_action.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function(r){ return r.json(); }).then(function(json){ if (btn) btn.disabled = false; if (json && json.status) { var overlay = document.getElementById('assign-branch-overlay'); if (overlay) overlay.remove(); location.reload(); } else { alert(json.msg || 'Failed to save assignments'); } }).catch(function(){ if (btn) btn.disabled = false; alert('Network error'); }); });
    })();
    </script>
    <?php
    exit;
}

// Non-AJAX full page fallback (keeps existing behavior)
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Assign Branches to Doctors & Patients</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 18px; border-bottom: 1px solid #eee; }
        .panel { background: #fff; padding: 20px; border-radius: 6px; }
        .actions { margin-top: 12px; }
    </style>
</head>
<body>
<div class="container panel">
    <h2>Assign Branches to Doctors & Patients</h2>

    <?php if (!empty($_GET['success'])): ?>
        <div class="alert success">Assignments saved.</div>
    <?php endif; ?>

    <h3>Doctors</h3>
    <form method="post" action="assign_branch_action.php" id="assign-branch-form">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
        <table class="table">
            <thead>
                <tr><th>NAME</th><th>ASSIGNED BRANCHES</th></tr>
            </thead>
            <tbody>
            <?php foreach ($doctors as $doc): ?>
                <tr id="doc-<?php echo $doc['docid']; ?>">
                    <td style="text-align:center"><?php echo htmlspecialchars($doc['name']); ?></td>
                    <td>
                        <?php foreach ($branches as $b):
                            $checked = '';
                            $q = $database->query("SELECT 1 FROM doctor_branches WHERE docid='".$database->real_escape_string($doc['docid'])."' AND branch_id='".$database->real_escape_string($b['id'])."'");
                            if ($q && $q->num_rows) $checked = 'checked';
                        ?>
                            <label style="margin-right:12px"><input type="checkbox" name="doctor_<?php echo $doc['docid']; ?>[]" value="<?php echo $b['id']; ?>" <?php echo $checked; ?>> <?php echo htmlspecialchars($b['name']); ?></label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Patients</h3>
        <table class="table">
            <thead>
                <tr><th>NAME</th><th>ASSIGNED BRANCHES</th></tr>
            </thead>
            <tbody>
            <?php foreach ($patients as $p): ?>
                <tr id="pat-<?php echo $p['pid']; ?>">
                    <td style="text-align:center"><?php echo htmlspecialchars($p['name']); ?></td>
                    <td>
                        <?php foreach ($branches as $b):
                            $checked = '';
                            $q = $database->query("SELECT 1 FROM patient_branches WHERE pid='".$database->real_escape_string($p['pid'])."' AND branch_id='".$database->real_escape_string($b['id'])."'");
                            if ($q && $q->num_rows) $checked = 'checked';
                        ?>
                            <label style="margin-right:12px"><input type="checkbox" name="patient_<?php echo $p['pid']; ?>[]" value="<?php echo $b['id']; ?>" <?php echo $checked; ?>> <?php echo htmlspecialchars($b['name']); ?></label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="actions">
            <button type="submit" class="btn btn-primary">Save Assignments</button>
            <a href="dashboard.php" class="btn">Cancel</a>
        </div>
    </form>
</div>

<?php if (!empty($focus)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            var el = document.getElementById('<?php echo $focus; ?>');
            if (el) {
                el.scrollIntoView({behavior:'smooth', block:'center'});
                el.style.transition = 'background-color 0.4s ease';
                el.style.backgroundColor = '#fffbcc';
                setTimeout(function(){ el.style.backgroundColor = ''; }, 3000);
            }
        });
    </script>
<?php endif; ?>
</body>
</html>
