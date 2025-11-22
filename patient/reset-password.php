<?php
session_start();
include("../connection.php");

$error = "";
$token = $_GET['token'] ?? '';

// Check if token is valid
if (!empty($token)) {
    $current_time = date("Y-m-d H:i:s");
    $result = $database->query("SELECT * FROM patient WHERE reset_token='$token' AND reset_token_expires > '$current_time'");
    
    if ($result->num_rows != 1) {
        $error = "Invalid or expired token. Please request a new password reset link.";
        $token = ""; // Clear token if invalid
    }
} else {
    $error = "No token provided. Please use the link from your email.";
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($token)) {
    $new_password = $_POST['newpassword'];
    $confirm_password = $_POST['cpassword'];
    
    if ($new_password === $confirm_password) {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Get the email associated with this token
        $patient = $result->fetch_assoc();
        $email = $patient['pemail'];
        
        // Update password and clear reset token
        $database->query("UPDATE patient SET ppassword='$hashed_password', reset_token=NULL, reset_token_expires=NULL WHERE pemail='$email'");
        
        header('Location: password-reset-success.php');
        exit();
    } else {
        $error = "Passwords do not match!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/login.css">
    <link rel="stylesheet" href="../css/IHeartDentistDC.css">
    <link rel="stylesheet" href="../css/loading.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <title>Reset Password - IHeartDentistDC</title>
</head>

<body>

    <nav>
        <ul class="sidebar">
            <li onclick=hideSidebar()><a href="#"><img src="../Media/Icon/Black/navbar.png" class="navbar-logo" alt="Navigation Bar"></a></li>
            <li><a href="../IHeartDentistDC.php"><img src="../Media/Icon/logo.png" class="logo-name" alt="IHeartDentistDC"></a></li>
            <li><a href="../IHeartDentistDC.php">Home</a></li>
            <!--<li><a href="#">About</a></li>-->
            <li><a href="../IHeartDentistDC.php#services">Services</a></li>
            <li><a href="../IHeartDentistDC.php#contact">Contact</a></li>
            <li><a href="signup.php">Sign up</a></li>
            <li><a href="login.php">Login</a></li>
        </ul>
        <ul>
            <li><a href="../IHeartDentistDC.php"><img src="../Media/Icon/logo.png" class="logo-name" alt="IHeartDentistDC"></a>
            </li>
            <li class="hideOnMobile"><a href="../IHeartDentistDC.php">Home</a></li>
            <!--<li class="hideOnMobile"><a href="#">About</a></li> -->
            <li class="hideOnMobile"><a href="../IHeartDentistDC.php#services">Services</a></li>
            <li class="hideOnMobile"><a href="../IHeartDentistDC.php#contact">Contact</a></li>
            <li class="hideOnMobile"><a href="signup.php" class="reg-btn">Sign up</a></li>
            <li class="hideOnMobile"><a href="login.php" class="log-btn">Login</a></li>
            <li class="menu-button" onclick=showSidebar()><a href="#"><img src="../Media/Icon/Black/navbar.png"
                        class="navbar-logo" alt="Navigation Bar"></a></li>
        </ul>
    </nav>
    <script>
            function showSidebar() {
                const sidebar = document.querySelector('.sidebar')
                sidebar.style.display = 'flex'
            }
            function hideSidebar() {
                const sidebar = document.querySelector('.sidebar')
                sidebar.style.display = 'none'
            }
        </script>

    <div class="login-container">
        <div class="inside-container">
            <span class="login-logo"><img src="../Media/Icon/Blue/care.png"></span>
            <span class="login-header">Set New Password</span>
            <span class="login-header-admin">I Heart Dentist Dental Clinic</span>
            
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($token)): ?>
                <form action="" method="POST">
                    <label for="newpassword">New Password</label>
                    <div class="password-container" style="position: relative;">
                        <input type="password" id="newpassword" name="newpassword" placeholder="Enter new password" required
                            pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$"
                            title="Password must be at least 8 characters long, and include at least one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&).">
                        <div class="password-toggle-icon">
                            <i class="fa-solid fa-eye-slash" id="toggleNewPassword"></i>
                        </div>
                    </div>
                    
                    <label for="cpassword">Confirm Password</label>
                    <div class="password-container" style="position: relative;">
                        <input type="password" id="cpassword" name="cpassword" placeholder="Confirm new password" required>
                        <div class="password-toggle-icon">
                            <i class="fa-solid fa-eye-slash" id="toggleConfirmPassword"></i>
                        </div>
                    </div>
                    
                    <div id="password-mismatch" class="error-message" style="display: none;">❌ Passwords do not match</div>
                    
                    <input type="submit" value="Reset Password" class="login-btn">
                </form>
            <?php else: ?>
                <div class="info-message">
                    <p>Please check your email for a valid password reset link or <a href="forgot-password.php">request a new one</a>.</p>
                </div>
            <?php endif; ?>
            
            <label for="" class="bottom-text">Remember your password? <a href="login.php" class="signup-link">Log in</a></label>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        document.getElementById("toggleNewPassword").addEventListener("click", function() {
            const passwordInput = document.getElementById("newpassword");
            const icon = this;
            togglePasswordVisibility(passwordInput, icon);
        });
        
        document.getElementById("toggleConfirmPassword").addEventListener("click", function() {
            const passwordInput = document.getElementById("cpassword");
            const icon = this;
            togglePasswordVisibility(passwordInput, icon);
        });
        
        function togglePasswordVisibility(input, icon) {
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            }
        }
        
        // Password match validation
        document.getElementById("cpassword").addEventListener("input", function() {
            const newPassword = document.getElementById("newpassword").value;
            const confirmPassword = this.value;
            const mismatchMessage = document.getElementById("password-mismatch");
            
            if (confirmPassword.length > 0 && newPassword !== confirmPassword) {
                mismatchMessage.style.display = "block";
            } else {
                mismatchMessage.style.display = "none";
            }
        });
    </script>
</body>

</html>
