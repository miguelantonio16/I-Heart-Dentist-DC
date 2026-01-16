<?php
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to dashboard if already logged in
if (isset($_SESSION["user"]) && $_SESSION["usertype"] === 'd') {
    require_once __DIR__ . '/../inc/redirect_helper.php';
    redirect_with_context('dashboard.php');
    exit();
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');
$_SESSION["date"] = date('Y-m-d');

// Include DB connection
include("../connection.php");

// Handle login logic
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $database->real_escape_string($_POST['useremail']);
    $password = $_POST['userpassword'];

    $result = $database->query("SELECT * FROM webuser WHERE email='$email'");
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ($user['usertype'] === 'd') {
            $checker = $database->query("SELECT * FROM doctor WHERE docemail='$email' AND status='active'");
            if ($checker->num_rows === 1) {
                $doctor = $checker->fetch_assoc();

                if (password_verify($password, $doctor['docpassword'])) {
                    $_SESSION['user'] = $email;
                    $_SESSION['usertype'] = 'd';
                    $_SESSION['userid'] = $doctor['docid'];
                    header("Location: dashboard.php");
                    exit();
                }
            }
            $error = "Invalid credentials. Please try again.";
        } else {
            $error = "Access denied. Dentists only.";
        }
    } else {
        $error = "No account found for this email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="../css/IHeartDentistDC.css">
    <link rel="stylesheet" href="../css/loading.css">
    <title>Log in - IHeartDentistDC (Dentist)</title>
    <script>
        function preventBackAfterLogout() {
            window.history.forward();
        }
        window.onload = preventBackAfterLogout;
        window.onpageshow = function (event) {
            if (event.persisted) window.location.reload();
        };
        function showSidebar() {
            document.querySelector('.sidebar').style.display = 'flex';
        }
        function hideSidebar() {
            document.querySelector('.sidebar').style.display = 'none';
        }
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

    <div class="login-container">
        <div class="inside-container">
            <span class="login-logo"><img src="../Media/Icon/Blue/dentist.png" alt="Dentist Icon"></span>
            <span class="login-header">Log in</span>
            <span class="login-header-admin">I Heart Dentist Dental Clinic</span>
            <form method="POST">
                <label for="useremail">Email</label>
                <input type="email" id="useremail" name="useremail" placeholder="Enter your email" required>

                <label for="userpassword">Password</label>
                <input type="password" id="userpassword" name="userpassword" placeholder="Enter your password" required>

                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <input type="submit" value="Log in" class="login-btn">

                <div class="login-actions">
                    <div class="role-buttons">
                        <a href="../admin/login.php" class="role-btn admin">Login as Admin</a>
                        <a href="../patient/login.php" class="role-btn patient">Login as Patient</a>
                    </div>
                </div>

                <label class="bottom-text">Forgot password? <a href="forgot-password.php" class="signup-link">Reset here</a></label>
            </form>
        </div>
    </div>
</body>
</html>

