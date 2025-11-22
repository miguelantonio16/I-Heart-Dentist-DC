<?php
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Terms and Conditions - IHeartDentistDC</title>
      <style>
        .terms-wrapper {
            background: url('../Media/Background/background-login.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .terms-container {
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 600px;
            padding: 30px;
            animation: transitionIn-Y 0.5s;
            margin: 20px 0;
        }
        
        .terms-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .terms-header h1 {
            color: #2f396d;
            font-size: 0.3rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .terms-header p {
            color: #666;
            font-size: 0.3rem;
            font-weight: 400;
        }
        
        .terms-content {
            max-height: 60vh;
            overflow-y: auto;
            padding-right: 15px;
        }
        
        .terms-content section {
            margin-bottom: 25px;
        }
        
        .terms-content h2 {
            color: #2f396d;
            font-size: 0.3rem;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
        }
        
        .terms-content p {
            color: #333;
            line-height: 1.6;
            margin-bottom: 15px;
            font-size: 0.3rem;
            font-weight: 400;

        }
        
        .terms-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #666;
        }
        
        .terms-footer a {
            color: #2f396d;
            text-decoration: none;
            font-weight: bold;
        }
        
        .terms-footer a:hover {
            text-decoration: underline;
        }
        
        /* Scrollbar styling */
        .terms-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .terms-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .terms-content::-webkit-scrollbar-thumb {
            background: #2f396d;
            border-radius: 10px;
        }
        
        .terms-content::-webkit-scrollbar-thumb:hover {
            background: #1a237e;
        }
        
        @media (max-width: 768px) {
            .terms-container {
                padding: 20px;
                margin: 15px;
            }
            
            .terms-header h1 {
                font-size: 1.8rem;
            }
            
            .terms-content {
                max-height: 65vh;
            }
        }

         .back-button {
            display: block;
            margin: 25px auto 0;
            padding: 10px 25px;
            background-color: #2f396d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.3rem;
            transition: background-color 0.3s;
            text-align: center;
            text-decoration: none;
            width: fit-content;
        }
        
        .back-button:hover {
            background-color: #1a237e;
        }
    </style>
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

    <div class="terms-wrapper">
        <div class="terms-container">
            <div class="terms-header">
                <h1>Terms and Conditions</h1>
            </div>

            <div class="terms-content">
                <section>
                    <h2>1. Acceptance of Terms</h2>
                    <p>By accessing or using IHeartDentistDC, you agree to be bound by these Terms and Conditions. If you do not agree with any part of these terms, you must not use our services.</p>
                </section>

                <section>
                    <h2>2. Description of Service</h2>
                    <p>IHeartDentistDC provides dental appointment management services through our online platform. We connect patients with dental professionals at I Heart Dentist Dental Clinic.</p>
                </section>

                <section>
                    <h2>3. User Accounts</h2>
                    <p>To access certain features, you must create an account. You are responsible for maintaining the confidentiality of your account information and for all activities that occur under your account.</p>
                </section>

                <section>
                    <h2>4. Privacy Policy</h2>
                    <p>Your use of our services is also governed by our Privacy Policy, which explains how we collect, use, and protect your personal information.</p>
                </section>

                <section>
                    <h2>5. Appointments and Cancellations</h2>
                    <p>Appointments made through IHeartDentistDC are subject to the clinic's availability and cancellation policies. Please provide at least 24 hours notice for cancellations.</p>
                </section>

                <section>
                    <h2>6. Medical Information</h2>
                    <p>IHeartDentistDC is not a substitute for professional medical advice. Always consult with a qualified dental professional regarding any medical conditions or treatments.</p>
                </section>

                <section>
                    <h2>7. Limitation of Liability</h2>
                    <p>IHeartDentistDC shall not be liable for any direct, indirect, incidental, or consequential damages resulting from the use or inability to use our services.</p>
                </section>

                <section>
                    <h2>8. Changes to Terms</h2>
                    <p>We reserve the right to modify these terms at any time. Your continued use of the service after changes constitutes acceptance of the new terms.</p>
                </section>

                <section>
                    <h2>9. Governing Law</h2>
                    <p>These terms shall be governed by the laws of the Philippines. Any disputes shall be resolved in the courts of [Your City/Region].</p>
                </section>

                <div class="terms-footer">
                    <p>If you have any questions about these Terms and Conditions, please contact us at <a href="mailto:grycenmagahis@gmail.com">grycenmagahis@gmail.com</a>.</p>
                </div>
                
                <!-- Back Button -->
                <a href="create-account.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Create Account
                </a>
            </div>
        </div>
    </div>
</body>
</html>
