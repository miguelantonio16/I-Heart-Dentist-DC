<?php
// PHPMailer namespace declarations must be at the top
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// Check if user is already logged in as admin

$_SESSION["user"] = "";
$_SESSION["usertype"] = "";

// Set the new timezone
date_default_timezone_set('Asia/Kolkata');
$date = date('Y-m-d');
$_SESSION["date"] = $date;

// Import database connection
include("../connection.php");

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['useremail'];

    // Check if the email exists in the webuser table
    $result = $database->query("SELECT * FROM webuser WHERE email='$email' AND usertype='p'");

    if ($result->num_rows == 1) {
        // Check if the email exists in the patient table
        $checker = $database->query("SELECT * FROM patient WHERE pemail='$email'");

        if ($checker->num_rows == 1) {
            $patient = $checker->fetch_assoc();

            // Generate a unique token
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", time() + 3600); // Token expires in 1 hour

            // Update the patient record with the token
            $database->query("UPDATE patient SET reset_token='$token', reset_token_expires='$expires' WHERE pemail='$email'");

            // Include PHPMailer
            require '../vendor/autoload.php';

            // Send email with reset link
            $mail = new PHPMailer(true);

            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'grycenmagahis@gmail.com';
                $mail->Password = 'suxg svrk tfuo jvni';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Recipients
                $mail->setFrom('grycenmagahis@gmail.com', 'I Heart Dentist Dental Clinic');
                $mail->addAddress($email, $patient['pname']);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                // Build reset link using configured BASE_URL
                $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
                $reset_link = $base . '/patient/reset-password.php?token=' . urlencode($token);
                $mail->Body = "Hi {$patient['pname']},<br><br>"
                    . "We received a request to reset your password. Click the link below to reset it:<br><br>"
                    . "<a href='$reset_link'>Reset Password</a><br><br>"
                    . "This link will expire in 1 hour. If you didn't request this, please ignore this email.<br><br>"
                    . "Thank you!";

                $mail->send();
                header('Location: password-reset-sent.php');
                exit();
            } catch (Exception $e) {
                $error = "Failed to send reset email. Error: " . $mail->ErrorInfo;
            }
        } else {
            $error = "We can't find any patient account with this email.";
        }
    } else {
        $error = "We can't find any account with this email.";
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

    <title>Forgot Password - IHeartDentistDC</title>
</head>

<body>

    <nav>
        <ul class="sidebar">
            <li onclick=hideSidebar()><a href="#"><img src="../Media/Icon/Black/navbar.png" class="navbar-logo" alt="Navigation Bar"></a></li>
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
            <li class="menu-button" onclick=showSidebar()><a href="#"><img src="../Media/Icon/Black/navbar.png" class="navbar-logo" alt="Navigation Bar"></a></li>
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
            <span class="login-header">Reset Password</span>
            <span class="login-header-admin">I Heart Dentist Dental Clinic</span>
            <form action="" method="POST">
                <label for="email">Email</label>
                <input type="email" id="useremail" name="useremail" placeholder="Enter your email" required>
                <div class="error-message" style="<?php echo empty($error) ? 'display:none;' : ''; ?>">
                    <?php
                    if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($error)) {
                        echo '<p style="error-message">' . htmlspecialchars($error) . '</p>';
                    }
                    ?>
                </div>
                <input type="submit" value="Send Reset Link" class="login-btn">
                <label for="" class="bottom-text">Remember your password? <a href="login.php" class="signup-link">Log in</a></label>
            </form>
        </div>
    </div>

</body>

</html>

