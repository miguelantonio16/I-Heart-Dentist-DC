<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">
    <link rel="stylesheet" href="../css/IHeartDentistDC.css">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/signup.css">
    <link rel="stylesheet" href="../css/loading.css">

    <title>Sign up - IHeartDentistDC</title>

</head>

<body>
    <?php
    session_start();

    
    // Check if user is already logged in as admin
    if (isset($_SESSION["user"]) && $_SESSION['usertype'] == 'p') {
        header("location: dashboard.php");
        exit();
    }

    $_SESSION["user"] = "";
    $_SESSION["usertype"] = "";

    date_default_timezone_set('Asia/Kolkata');
    $date = date('Y-m-d');

    $_SESSION["date"] = $date;

    if ($_POST) {
        $_SESSION["personal"] = array(
            'fname' => strtoupper($_POST['fname']),
            'lname' => strtoupper($_POST['lname']),
            'house_no' => strtoupper($_POST['house_no']),
            'street' => strtoupper($_POST['street']),
            'brgy' => strtoupper($_POST['brgy']),
            'city' => strtoupper($_POST['city']),
            'province' => strtoupper($_POST['province']),
            'dob' => $_POST['dob']
        );

        print_r($_SESSION["personal"]);
        header("location: create-account.php");
    }

    ?>

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
    <div class="signup-container">
        <div class="container">
            <table border="0">
                <tr>
                    <td colspan="2">
                        <p class="header-text">Let's Get Started</p>
                        <p class="sub-text">Add your personal details to continue.</p>
                    </td>
                </tr>
                <form action="" method="POST">
                    <tr>
                        <td class="label-td" colspan="2">
                            <label for="name">Name</label>
                            <span style="color: red">*</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-td" colspan="2">
                            <div class="name-container">
                                <div class="input-group">
                                    <input type="text" name="fname" placeholder="First Name" required>
                                </div>
                                <div class="input-group">
                                    <input type="text" name="lname" placeholder="Last Name" required>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-td" colspan="2">
                            <label for="address">Address</label>
                            <span style="color: red">*</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-td" colspan="2">
                            <div class="address-container">
                                <div class="input-group">
                                    <input type="text" name="house_no" placeholder="House No." required>
                                </div>
                                <div class="input-group">
                                    <input type="text" name="street" placeholder="Street" required>
                                </div>
                                <div class="input-group">
                                    <input type="text" name="brgy" placeholder="Barangay" required>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-td" colspan="2">
                            <div class="city-province-container">
                                <div class="input-group">
                                    <input type="text" name="city" placeholder="City" required>
                                </div>
                                <div class="input-group">
                                    <input type="text" name="province" placeholder="Province" required>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-td" colspan="2">
                            <label for="dob">Date of Birth</label>
                            <span style="color: red">*</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-td" colspan="2">
                            <input type="date" name="dob" required>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-td" colspan="2">
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input type="reset" value="Clear" class="clear-btn">
                        </td>
                        <td>
                            <input type="submit" value="Next" class="login-btn">
                        </td>

                    </tr>

                    <tr>
                        <td colspan="2">
                            <br>
                            <label for="" class="bottom-text">Already have an account&#63; </label>
                            <a href="login.php" class="login-link">Login</a>
                            <br><br><br>
                        </td>
                    </tr>

                </form>
                </tr>
            </table>

        </div>
    </div>

</body>

</html>
