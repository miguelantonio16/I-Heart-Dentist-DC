<?php
// Start output buffering to prevent header issues
ob_start();

// Start session and set headers first
session_start();

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Set timezone and date
date_default_timezone_set('Asia/Kolkata');
$_SESSION["date"] = date('Y-m-d');

// Initialize variables
$error = "";
$email = "";

// Initialize login attempts if not set
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['last_login_attempt'])) {
    $_SESSION['last_login_attempt'] = time();
}

// Constants for login attempt limits
define('LOCKOUT_TIME', 60); // seconds
define('MAX_ATTEMPTS', 5);

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Import database connection
include("../connection.php");

// Process login if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please refresh the page.";
    } elseif ($_SESSION['login_attempts'] >= MAX_ATTEMPTS && (time() - $_SESSION['last_login_attempt']) < LOCKOUT_TIME) {
        $error = "Too many failed attempts. Please try again in a few seconds.";
    } else {
        $email = trim($_POST['useremail']);
        $password = $_POST['userpassword'];

        // Check if user exists
        $stmt = $database->prepare("SELECT * FROM webuser WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $usertype = $user['usertype'];
            
            // Handle different user types
            switch ($usertype) {
                case 'p': // Patient
                    $stmt2 = $database->prepare("SELECT * FROM patient WHERE pemail=? AND status='active'");
                    $stmt2->bind_param("s", $email);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();

                    if ($result2->num_rows === 1) {
                        $patient = $result2->fetch_assoc();

                        if (password_verify($password, $patient['ppassword'])) {
                            if ($patient['is_verified'] == 1) {
                                // Reset login attempts
                                $_SESSION['login_attempts'] = 0;

                                $_SESSION['user'] = $email;
                                $_SESSION['usertype'] = 'p';
                                $_SESSION['username'] = $patient['pname'];
                                
                                // Clear output buffer and redirect
                                ob_end_clean();
                                header('Location: dashboard.php');
                                exit();
                            } else {
                                $error = "Please verify your email before logging in.";
                            }
                        } else {
                            $error = "Wrong credentials: Invalid email or password.";
                        }
                    } else {
                        $error = "Wrong credentials: Invalid email or password.";
                    }
                    break;
                    
                case 'd': // Dentist
                    $stmt2 = $database->prepare("SELECT * FROM doctor WHERE docemail=? AND status='active'");
                    $stmt2->bind_param("s", $email);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();

                    if ($result2->num_rows === 1) {
                        $doctor = $result2->fetch_assoc();

                        if (password_verify($password, $doctor['docpassword'])) {
                            // Reset login attempts
                            $_SESSION['login_attempts'] = 0;

                            $_SESSION['user'] = $email;
                            $_SESSION['usertype'] = 'd';
                            $_SESSION['userid'] = $doctor['docid'];
                            
                            ob_end_clean();
                            header('Location: ../dentist/dashboard.php');
                            exit();
                        } else {
                            $error = "Wrong credentials: Invalid email or password.";
                        }
                    } else {
                        $error = "Wrong credentials: Invalid email or password.";
                    }
                    break;
                    
                case 'a': // Admin
                    $stmt2 = $database->prepare("SELECT * FROM admin WHERE aemail=?");
                    $stmt2->bind_param("s", $email);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();

                    if ($result2->num_rows === 1) {
                        $admin = $result2->fetch_assoc();

                        if (password_verify($password, $admin['apassword'])) {
                            // Reset login attempts
                            $_SESSION['login_attempts'] = 0;

                            $_SESSION['user'] = $email;
                            $_SESSION['usertype'] = 'a';
                            
                            ob_end_clean();
                            header('Location: ../admin/dashboard.php');
                            exit();
                        } else {
                            $error = "Wrong credentials: Invalid email or password.";
                        }
                    } else {
                        $error = "Wrong credentials: Invalid email or password.";
                    }
                    break;
                    
                default:
                    $error = "Invalid user type.";
                    break;
            }
        } else {
            $error = "We can't find any account with this email.";
        }

        // Increment login attempts
        $_SESSION['login_attempts']++;
        $_SESSION['last_login_attempt'] = time();
    }
}

// Redirect if already logged in
if (isset($_SESSION["user"]) && !empty($_SESSION["user"])) {
    switch ($_SESSION['usertype']) {
        case 'p':
            ob_end_clean();
            header("location: dashboard.php");
            exit();
        case 'd':
            ob_end_clean();
            header("location: ../dentist/dashboard.php");
            exit();
        case 'a':
            ob_end_clean();
            header("location: ../admin/dashboard.php");
            exit();
    }
}

// Clear output buffer before HTML
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="../css/IHeartDentistDC.css">
    <link rel="stylesheet" href="../css/loading.css">
    <script>
        function preventBackAfterLogout() {
            window.history.forward();
        }
        window.onload = preventBackAfterLogout;
        window.onpageshow = function(event) {
            if (event.persisted) window.location.reload();
        };
    </script>
    <title>Log in - IHeartDentistDC</title>
</head>
<body>

<nav>
    <ul class="sidebar">
        <li onclick=hideSidebar()><a href="#"><img src="../Media/Icon/Black/navbar.png" class="navbar-logo"></a></li>
        <li><a href="../IHeartDentistDC.php"><img src="../Media/Icon/logo.png" class="logo-name"></a></li>
        <li><a href="../IHeartDentistDC.php">Home</a></li>
        <li><a href="../IHeartDentistDC.php#services">Services</a></li>
        <li><a href="../IHeartDentistDC.php#contact">Contact</a></li>
        <li><a href="signup.php">Sign up</a></li>
        <li><a href="login.php">Login</a></li>
    </ul>
    <ul>
        <li><a href="../IHeartDentistDC.php"><img src="../Media/Icon/logo.png" class="logo-name"></a></li>
        <li class="hideOnMobile"><a href="../IHeartDentistDC.php">Home</a></li>
        <li class="hideOnMobile"><a href="../IHeartDentistDC.php#services">Services</a></li>
        <li class="hideOnMobile"><a href="../IHeartDentistDC.php#contact">Contact</a></li>
        <li class="hideOnMobile"><a href="signup.php" class="reg-btn">Sign up</a></li>
        <li class="hideOnMobile"><a href="login.php" class="log-btn">Login</a></li>
        <li class="menu-button" onclick=showSidebar()><a href="#"><img src="../Media/Icon/Black/navbar.png" class="navbar-logo"></a></li>
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

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <label for="useremail">Email</label>
            <input type="email" id="useremail" name="useremail" placeholder="Enter your email" required autocomplete="off" value="<?php echo htmlspecialchars($email); ?>">

            <label for="userpassword">Password</label>
            <input type="password" id="userpassword" name="userpassword" placeholder="Enter your password" required autocomplete="off">

            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <input type="submit" value="Log in" class="login-btn">

            <div class="login-actions">
                <div class="role-buttons">
                    <a href="../admin/login.php" class="role-btn admin">Login as Admin</a>
                    <a href="../dentist/login.php" class="role-btn dentist">Login as Dentist</a>
                </div>
            </div>

            <label class="bottom-text">Don't have an account&#63; <a href="signup.php" class="signup-link">Sign up</a></label>
            <label class="bottom-text"><a href="forgot-password.php" class="signup-link">Forgot password?</a></label>
            
        </form>
    </div>
</div>

</body>
</html>
