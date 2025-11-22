<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['usertype'] != 'a') {
    header('Location: ../login.php');
    exit;
}
include('../connection.php');

// Handle POST create
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['branch_name'])) {
    $name = $database->real_escape_string($_POST['branch_name']);
    $address = $database->real_escape_string($_POST['branch_address'] ?? '');
    $stmt = $database->prepare("INSERT INTO branches (name, address) VALUES (?, ?)");
    $stmt->bind_param('ss', $name, $address);
    $stmt->execute();
    header('Location: branches.php');
    exit;
}

$branches = $database->query("SELECT * FROM branches ORDER BY name ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Branches - Admin</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <div class="container">
        <h2>Clinic Branches</h2>
        <form method="POST">
            <label>Branch name</label><br>
            <input type="text" name="branch_name" required><br>
            <label>Address</label><br>
            <textarea name="branch_address"></textarea><br>
            <button type="submit" class="login-btn btn-primary btn">Add Branch</button>
        </form>

        <h3>Existing Branches</h3>
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Name</th><th>Address</th></tr>
            </thead>
            <tbody>
                <?php while ($row = $branches->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['address']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <p><a href="assign-branches.php">Assign branches to dentists & patients</a></p>
    </div>
</body>
</html>
