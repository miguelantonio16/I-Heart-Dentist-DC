<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['usertype'] != 'a') {
    header('Location: ../login.php');
    exit;
}
include('../connection.php');

// Fetch branches, doctors, patients
$branches_result = $database->query("SELECT * FROM branches ORDER BY name ASC");
$branches = [];
while ($br = $branches_result->fetch_assoc()) { $branches[] = $br; }
$doctors = $database->query("SELECT docid, docname, docemail, branch_id FROM doctor ORDER BY docname ASC");
$patients = $database->query("SELECT pid, pname, pemail, branch_id FROM patient ORDER BY pname ASC");

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Assign Branches</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="container">
        <h2>Assign Branch to Dentists</h2>
        <form method="POST" action="assign-branch-action.php">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Branch</th><th>Action</th></tr></thead>
                <tbody>
                <?php while ($d = $doctors->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['docname']); ?></td>
                        <td><?php echo htmlspecialchars($d['docemail']); ?></td>
                        <td>
                            <select name="doctor_branch[<?php echo $d['docid']; ?>]">
                                <option value="">-- None --</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?php echo $b['id']; ?>" <?php echo ($d['branch_id'] == $b['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <button type="submit" name="assign_doctors" class="login-btn btn-primary btn">Save Dentist Branches</button>
        </form>

        <h2>Assign Branch to Patients</h2>
        <form method="POST" action="assign-branch-action.php">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Branch</th></tr></thead>
                <tbody>
                <?php while ($p = $patients->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['pname']); ?></td>
                        <td><?php echo htmlspecialchars($p['pemail']); ?></td>
                        <td>
                            <select name="patient_branch[<?php echo $p['pid']; ?>]">
                                <option value="">-- None --</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?php echo $b['id']; ?>" <?php echo ($p['branch_id'] == $b['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <button type="submit" name="assign_patients" class="login-btn btn-primary btn">Save Patient Branches</button>
        </form>

        <p><a href="branches.php">Back to branches</a></p>
    </div>
</body>
</html>
