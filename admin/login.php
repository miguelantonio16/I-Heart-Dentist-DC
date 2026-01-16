<?php
// Start the session securely
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Redirect already logged-in admin
if (isset($_SESSION["user"]) && $_SESSION['usertype'] === 'a') {
    require_once __DIR__ . '/../inc/redirect_helper.php';
    redirect_with_context('dashboard.php');
    exit();
}

// Clear session
$_SESSION["user"] = "";
$_SESSION["usertype"] = "";

// Set timezone
date_default_timezone_set('Asia/Singapore');
$_SESSION["date"] = date('Y-m-d');

// DB connection
include("../connection.php");

$error = "";

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['useremail']);
    $password = $_POST['userpassword'];

    // Sanitize email input
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email exists in webuser
        $stmt = $database->prepare("SELECT usertype FROM webuser WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $utype = $result->fetch_assoc()['usertype'];

            if ($utype === 'a') {
                // Check if admin exists
                $stmt = $database->prepare("SELECT * FROM admin WHERE aemail = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $admin = $result->fetch_assoc();

                    // Verify password
                    if ($password == $admin['apassword']) {
                        $_SESSION['user'] = $email;
                        $_SESSION['usertype'] = 'a';
                        // Set superadmin flag if present in the admin record
                        $_SESSION['is_superadmin'] = (isset($admin['is_super']) && $admin['is_super'] == 1) ? 1 : 0;

                        // Branch restriction mapping for special branch-scoped admin accounts
                        $branchAdminMap = [
                            'adminbacoor@edoc.com' => 'Bacoor',
                            'adminmakati@edoc.com' => 'Makati'
                        ];

                        $lowerEmail = strtolower($email);
                        if (isset($branchAdminMap[$lowerEmail])) {
                            $branchName = $branchAdminMap[$lowerEmail];
                            try {
                                $branchId = null;
                                $stmtB = $database->prepare("SELECT id FROM branches WHERE name = ? LIMIT 1");
                                $stmtB->bind_param('s', $branchName);
                                $stmtB->execute();
                                $resB = $stmtB->get_result();
                                if ($resB && $resB->num_rows === 1) {
                                    $branchId = (int)($resB->fetch_assoc()['id']);
                                } else {
                                    $likeName = '%' . $branchName . '%';
                                    $stmtB2 = $database->prepare("SELECT id FROM branches WHERE name LIKE ? LIMIT 1");
                                    $stmtB2->bind_param('s', $likeName);
                                    $stmtB2->execute();
                                    $resB2 = $stmtB2->get_result();
                                    if ($resB2 && $resB2->num_rows === 1) {
                                        $branchId = (int)($resB2->fetch_assoc()['id']);
                                    }
                                }
                                $_SESSION['restricted_branch_id'] = $branchId ? $branchId : null;
                            } catch (Throwable $e) {
                                $_SESSION['restricted_branch_id'] = null;
                            }
                        } else {
                            // Clear any previous restrictions for other admins
                            $_SESSION['restricted_branch_id'] = null;
                        }

                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = "Wrong credentials: Invalid email or password.";
                    }
                } else {
                    $error = "No admin account found for this email.";
                }
            } else {
                $error = "Access denied: Admins only.";
            }
        } else {
            $error = "No account found for this email.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Prevent caching -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="../css/IHeartDentistDC.css">
    <link rel="stylesheet" href="../css/loading.css">
    <title>Log in - IHeartDentistDC (Admin)</title>
    <script>
        // Prevent browser back after logout
        function preventBackAfterLogout() {
            window.history.forward();
        }
        window.onload = preventBackAfterLogout;
        window.onpageshow = function(event) {
            if (event.persisted) window.location.reload();
        };
    </script>
</head>
<body>
    <nav>
        <ul class="sidebar">
            <li onclick="hideSidebar()"><a href="#"><img src="../Media/Icon/Black/navbar.png" class="navbar-logo" alt="Navigation Bar"></a></li>
            <li><a href="../IHeartDentistDC.php"><img src="../Media/Icon/logo.png" class="logo-name" alt="IHeartDentistDC"></a></li>
            <li><a href="../IHeartDentistDC.php">Home</a></li>
            <li><a href="../IHeartDentistDC.php#services">Services</a></li>
            <li><a href="../IHeartDentistDC.php#contact">Contact</a></li>
            <li><a href="signup.php">Sign up</a></li>
            <li><a href="login.php">Login</a></li>
        </ul>
        <ul>
            <li><a href="../IHeartDentistDC.php"><img src="../Media/Icon/logo.png" class="logo-name" alt="IHeartDentistDC"></a></li>
            <li class="hideOnMobile"><a href="../IHeartDentistDC.php">Home</a></li>
            <li class="hideOnMobile"><a href="../IHeartDentistDC.php#services">Services</a></li>
            <li class="hideOnMobile"><a href="../IHeartDentistDC.php#contact">Contact</a></li>
            <li class="hideOnMobile"><a href="signup.php" class="reg-btn">Sign up</a></li>
            <li class="hideOnMobile"><a href="login.php" class="log-btn">Login</a></li>
            <li class="menu-button" onclick="showSidebar()"><a href="#"><img src="../Media/Icon/Black/navbar.png" class="navbar-logo" alt="Navigation Bar"></a></li>
        </ul>
    </nav>
    <script>
        function showSidebar() {
            document.querySelector('.sidebar').style.display = 'flex';
        }
        function hideSidebar() {
            document.querySelector('.sidebar').style.display = 'none';
        }
    </script>
    <div class="login-container">
        <div class="inside-container">
            <span class="login-logo"><img src="../Media/Icon/logo.png"></span>
            <span class="login-header">Log in</span>
            <span class="login-header-admin">I Heart Dentist Dental Clinic</span>
            <form action="" method="POST" novalidate>
                <label for="useremail">Email</label>
                <input type="email" id="useremail" name="useremail" placeholder="Enter your email" required>
                <label for="userpassword">Password</label>
                <input type="password" id="userpassword" name="userpassword" placeholder="Enter your password" required>
                <div class="error-message" style="<?php echo empty($error) ? 'display:none;' : ''; ?>">
                    <?php 
                    if (!empty($error)) {
                        echo '<p>' . htmlspecialchars($error) . '</p>'; 
                    }
                    ?>
                </div>
                <input type="submit" value="Log in" class="login-btn">
                <div class="login-actions">
                    <div class="role-buttons">
                        <a href="../dentist/login.php" class="role-btn dentist">Login as Dentist</a>
                        <a href="../patient/login.php" class="role-btn patient">Login as Patient</a>
                    </div>
                </div>
                <label class="bottom-text">Forgot password? <a href="forgot-password.php" class="signup-link">Reset here</a></label>
            </form>
        </div>
    </div>
</body>
</html>

