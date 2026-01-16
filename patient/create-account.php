<!DOCTYPE html>
<html lang="en">


<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../Media/Icon/logo.png" type="image/png">
    <link rel="stylesheet" href="../css/animations.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/signup.css">
    <link rel="stylesheet" href="../css/IHeartDentistDC.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>




    <title>Sign up - IHeartDentistDC</title>
    <style>
        .container {
            animation: transitionIn-X 0.5s;
        }
        .terms-container {
            margin-bottom: 10px;
            text-align: center;
        }

        .terms-text {
            color: #2f396d;
            font-size: 14px;
        }

        .terms-link {
            color: #2f396d;
            text-decoration: underline;
            font-weight: bold;
        }

        .terms-link:hover {
            color: #1a237e;
        }
    </style>


</head>


<body>
    <?php
    session_start();


    $_SESSION["user"] = "";
    $_SESSION["usertype"] = "";


    date_default_timezone_set('Asia/Kolkata');
    $date = date('Y-m-d');
    $_SESSION["date"] = $date;


    include("../connection.php");

    // Load branches for patient signup
    $branches_result = $database->query("SELECT * FROM branches ORDER BY name ASC");
    $signup_branches = [];
    while ($b = $branches_result->fetch_assoc()) { $signup_branches[] = $b; }


    // Include PHPMailer
    require '../vendor/autoload.php'; // Adjust the path if necessary
   
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;


    $error = "";


    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $result = $database->query("SELECT * FROM webuser");


        $fname = $_SESSION['personal']['fname'] ?? '';
        $lname = $_SESSION['personal']['lname'] ?? '';
        $name = $fname . " " . $lname;


        $house_no = $_SESSION['personal']['house_no'] ?? '';
        $street = $_SESSION['personal']['street'] ?? '';
        $barangay = $_SESSION['personal']['brgy'] ?? '';
        $city = $_SESSION['personal']['city'] ?? '';
        $province = $_SESSION['personal']['province'] ?? '';


        $address = $house_no . ', ' . $street . ', ' . $barangay . ', ' . $city . ', ' . $province;
        $address = $database->real_escape_string($address);


        $dob = $_SESSION['personal']['dob'] ?? '';
        $email = isset($_POST['newemail']) ? trim($_POST['newemail']) : '';
        $tele = isset($_POST['tele']) ? trim($_POST['tele']) : '';
        $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? intval($_POST['branch_id']) : null;
        $newpassword = $_POST['newpassword'];
        $cpassword = $_POST['cpassword'];


        if ($newpassword === $cpassword) {
            // Require branch selection on signup
            if ($branch_id === null) {
                $error = "Please select a preferred branch.";
            }
            $result = $database->query("SELECT * FROM webuser WHERE email='$email';");
            if ($result->num_rows == 1) {
                $error = "Account with this email already exists.";
            } else {
                // Generate a verification token
                $verification_token = bin2hex(random_bytes(32)); // 64-character token
                $hashed_password = password_hash($newpassword, PASSWORD_DEFAULT);


                // Insert user data into the database
            // Include branch_id if provided
                        if ($branch_id !== null) {
                            $query = "INSERT INTO patient (pemail, pname, ppassword, paddress, pdob, ptel, verification_token, is_verified, branch_id)
                                VALUES ('$email', '$name', '$hashed_password', '$address', '$dob', '$tele', '$verification_token', 0, $branch_id)";
                        } else {
                            $query = "INSERT INTO patient (pemail, pname, ppassword, paddress, pdob, ptel, verification_token, is_verified)
                                VALUES ('$email', '$name', '$hashed_password', '$address', '$dob', '$tele', '$verification_token', 0)";
                        }
                // Insert patient and capture ID for branch mapping
                if ($database->query($query)) {
                    $new_pid = $database->insert_id;
                    // Ensure branch mapping row exists (migration moved to mapping table usage elsewhere)
                    if ($branch_id !== null) {
                        $database->query("INSERT IGNORE INTO patient_branches (pid, branch_id) VALUES ('".$database->real_escape_string($new_pid)."', '".$database->real_escape_string($branch_id)."')");
                    }
                } else {
                    $error = "Failed to create patient account: " . $database->error;
                }

                // Create webuser row only if patient insert succeeded
                if (empty($error)) {
                    $database->query("INSERT INTO webuser VALUES ('$email', 'p')");
                }


                // Send verification email
                $mail = new PHPMailer(true);


                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com'; // Replace with your SMTP server
                    $mail->SMTPAuth = true;
                    $mail->Username = 'grycenmagahis@gmail.com'; // Replace with your email
                    $mail->Password = 'suxg svrk tfuo jvni'; // Replace with your email password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;


                    // Recipients
                    $mail->setFrom('grycenmagahis@gmail.com', 'I Heart Dentist Dental Clinic'); // Replace with your email
                    $mail->addAddress($email, $name); // Add a recipient
   
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Email Verification';
                    // Build verification link using configurable BASE_URL (falls back if undefined)
                    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL,'/') : 'http://iheartdentistdc.com';
                    $verification_link = $baseUrl . "/patient/verify-email.php?token=" . urlencode($verification_token);
                    $mail->Body = "Hi $name,<br><br>Please verify your email by clicking the link below:<br><br>
                                <a href='$verification_link'>Verify Email</a><br><br>Thank you!";


                    $mail->send();
                    $success = "A verification email has been sent to $email. Please check your inbox.";
                } catch (Exception $e) {
                    $error = "Failed to send verification email. Error: " . $mail->ErrorInfo;
                }


                // Redirect to a success page or display a message
                if (empty($error)) {
                    // Do NOT auto-login before email verification to prevent confusion / accidental access.
                    // Optionally store branch in a transient session if you plan to use it after verification resend flows.
                    $_SESSION['pending_verification_email'] = $email;
                    if ($branch_id !== null) {
                        $_SESSION['pending_branch_id'] = intval($branch_id);
                    }
                    header('Location: verification-sent.php');
                    exit();
                }
            }
        } else {
            $error = "Password confirmation error! Please re-enter your password.";
        }
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
        <script>
            // Adjust top spacing so fixed header does not overlap the create-account card
            // Use a small visible gap (8px) under the header while reserving header height on body
            function adjustTopSpacing() {
                const nav = document.querySelector('nav');
                if (!nav) return;
                const headerHeight = nav.offsetHeight || 64;
                // Reserve exact header height to prevent overlap
                document.body.style.paddingTop = headerHeight + 'px';
                // Keep the card close to the header with a small comfortable gap
                document.querySelectorAll('.create-container, .signup-container').forEach(el => {
                    el.style.marginTop = '8px';
                });
            }
            window.addEventListener('DOMContentLoaded', adjustTopSpacing);
            window.addEventListener('resize', adjustTopSpacing);
        </script>
    <div class="create-container">
        <div class="container">
            <table>
                <form action="" method="POST" onsubmit="return addCountryCode();">
                    <tr>
                        <td colspan="2">
                            <p class="header-text">Let's Get Started</p>
                            <p class="sub-text">Create a user account.</p>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-td" colspan="2">
                            <label for="newemail">Email: </label>
                            <span style="color: red">*</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-td" colspan="2">
                            <input type="email" name="newemail" placeholder="e.g., juandelacruz@gmail.com" required value="<?php echo isset(
                                $email
                            ) ? htmlspecialchars($email) : ''; ?>">
                        </td>
                    </tr>
                    <tr>
                        <td class="label-td" colspan="2">
                            <label for="country" id="country-label">Select Country <span
                                    style="color: red">*</span></label>
                            <select id="country" onchange="updateCountryCode()" class="searchable-select">
                                <option value="">-- Select Country --</option>
                                <option value="+7 840">Abkhazia</option>
                                <option value="+93">Afghanistan</option>
                                <option value="+355">Albania</option>
                                <option value="+213">Algeria</option>
                                <option value="+1 684">American Samoa</option>
                                <option value="+376">Andorra</option>
                                <option value="+244">Angola</option>
                                <option value="+1 264">Anguilla</option>
                                <option value="+1 268">Antigua and Barbuda</option>
                                <option value="+54">Argentina</option>
                                <option value="+374">Armenia</option>
                                <option value="+297">Aruba</option>
                                <option value="+61">Australia</option>
                                <option value="+43">Austria</option>
                                <option value="+994">Azerbaijan</option>
                                <option value="+1 242">Bahamas</option>
                                <option value="+973">Bahrain</option>
                                <option value="+880">Bangladesh</option>
                                <option value="+1 246">Barbados</option>
                                <option value="+375">Belarus</option>
                                <option value="+32">Belgium</option>
                                <option value="+501">Belize</option>
                                <option value="+229">Benin</option>
                                <option value="+1 441">Bermuda</option>
                                <option value="+975">Bhutan</option>
                                <option value="+591">Bolivia</option>
                                <option value="+387">Bosnia and Herzegovina</option>
                                <option value="+267">Botswana</option>
                                <option value="+55">Brazil</option>
                                <option value="+673">Brunei</option>
                                <option value="+359">Bulgaria</option>
                                <option value="+226">Burkina Faso</option>
                                <option value="+257">Burundi</option>
                                <option value="+855">Cambodia</option>
                                <option value="+237">Cameroon</option>
                                <option value="+1">Canada</option>
                                <option value="+238">Cape Verde</option>
                                <option value="+1 345">Cayman Islands</option>
                                <option value="+236">Central African Republic</option>
                                <option value="+235">Chad</option>
                                <option value="+56">Chile</option>
                                <option value="+86">China</option>
                                <option value="+57">Colombia</option>
                                <option value="+269">Comoros</option>
                                <option value="+243">Democratic Republic of the Congo</option>
                                <option value="+242">Republic of the Congo</option>
                                <option value="+682">Cook Islands</option>
                                <option value="+506">Costa Rica</option>
                                <option value="+225">Côte d'Ivoire</option>
                                <option value="+385">Croatia</option>
                                <option value="+53">Cuba</option>
                                <option value="+357">Cyprus</option>
                                <option value="+420">Czech Republic</option>
                                <option value="+45">Denmark</option>
                                <option value="+253">Djibouti</option>
                                <option value="+1 767">Dominica</option>
                                <option value="+1 809">Dominican Republic</option>
                                <option value="+593">Ecuador</option>
                                <option value="+20">Egypt</option>
                                <option value="+503">El Salvador</option>
                                <option value="+240">Equatorial Guinea</option>
                                <option value="+291">Eritrea</option>
                                <option value="+372">Estonia</option>
                                <option value="+268">Eswatini</option>
                                <option value="+251">Ethiopia</option>
                                <option value="+500">Falkland Islands</option>
                                <option value="+298">Faroe Islands</option>
                                <option value="+679">Fiji</option>
                                <option value="+358">Finland</option>
                                <option value="+33">France</option>
                                <option value="+594">French Guiana</option>
                                <option value="+689">French Polynesia</option>
                                <option value="+241">Gabon</option>
                                <option value="+220">Gambia</option>
                                <option value="+995">Georgia</option>
                                <option value="+49">Germany</option>
                                <option value="+233">Ghana</option>
                                <option value="+350">Gibraltar</option>
                                <option value="+30">Greece</option>
                                <option value="+299">Greenland</option>
                                <option value="+1 473">Grenada</option>
                                <option value="+590">Guadeloupe</option>
                                <option value="+1 671">Guam</option>
                                <option value="+502">Guatemala</option>
                                <option value="+224">Guinea</option>
                                <option value="+245">Guinea-Bissau</option>
                                <option value="+592">Guyana</option>
                                <option value="+509">Haiti</option>
                                <option value="+504">Honduras</option>
                                <option value="+852">Hong Kong</option>
                                <option value="+36">Hungary</option>
                                <option value="+354">Iceland</option>
                                <option value="+91">India</option>
                                <option value="+62">Indonesia</option>
                                <option value="+98">Iran</option>
                                <option value="+964">Iraq</option>
                                <option value="+353">Ireland</option>
                                <option value="+972">Israel</option>
                                <option value="+39">Italy</option>
                                <option value="+1 876">Jamaica</option>
                                <option value="+81">Japan</option>
                                <option value="+962">Jordan</option>
                                <option value="+7">Kazakhstan</option>
                                <option value="+254">Kenya</option>
                                <option value="+686">Kiribati</option>
                                <option value="+82">South Korea</option>
                                <option value="+965">Kuwait</option>
                                <option value="+996">Kyrgyzstan</option>
                                <option value="+856">Laos</option>
                                <option value="+371">Latvia</option>
                                <option value="+961">Lebanon</option>
                                <option value="+266">Lesotho</option>
                                <option value="+231">Liberia</option>
                                <option value="+218">Libya</option>
                                <option value="+423">Liechtenstein</option>
                                <option value="+370">Lithuania</option>
                                <option value="+352">Luxembourg</option>
                                <option value="+853">Macau</option>
                                <option value="+261">Madagascar</option>
                                <option value="+265">Malawi</option>
                                <option value="+60">Malaysia</option>
                                <option value="+960">Maldives</option>
                                <option value="+223">Mali</option>
                                <option value="+356">Malta</option>
                                <option value="+692">Marshall Islands</option>
                                <option value="+222">Mauritania</option>
                                <option value="+230">Mauritius</option>
                                <option value="+52">Mexico</option>
                                <option value="+373">Moldova</option>
                                <option value="+377">Monaco</option>
                                <option value="+976">Mongolia</option>
                                <option value="+382">Montenegro</option>
                                <option value="+212">Morocco</option>
                                <option value="+258">Mozambique</option>
                                <option value="+95">Myanmar</option>
                                <option value="+264">Namibia</option>
                                <option value="+674">Nauru</option>
                                <option value="+977">Nepal</option>
                                <option value="+31">Netherlands</option>
                                <option value="+64">New Zealand</option>
                                <option value="+505">Nicaragua</option>
                                <option value="+227">Niger</option>
                                <option value="+234">Nigeria</option>
                                <option value="+47">Norway</option>
                                <option value="+968">Oman</option>
                                <option value="+92">Pakistan</option>
                                <option value="+970">Palestine</option>
                                <option value="+507">Panama</option>
                                <option value="+595">Paraguay</option>
                                <option value="+51">Peru</option>
                                <option value="+63">Philippines</option>
                                <option value="+48">Poland</option>
                                <option value="+351">Portugal</option>
                                <option value="+974">Qatar</option>
                                <option value="+40">Romania</option>
                                <option value="+7">Russia</option>
                                <option value="+250">Rwanda</option>
                                <option value="+966">Saudi Arabia</option>
                                <option value="+221">Senegal</option>
                                <option value="+65">Singapore</option>
                                <option value="+27">South Africa</option>
                                <option value="+34">Spain</option>
                                <option value="+94">Sri Lanka</option>
                                <option value="+249">Sudan</option>
                                <option value="+597">Suriname</option>
                                <option value="+268">Swaziland</option>
                                <option value="+46">Sweden</option>
                                <option value="+41">Switzerland</option>
                                <option value="+255">Tanzania</option>
                                <option value="+66">Thailand</option>
                                <option value="+90">Turkey</option>
                                <option value="+380">Ukraine</option>
                                <option value="+44">United Kingdom</option>
                                <option value="+1">United States</option>
                                <option value="+263">Zimbabwe</option>
                            </select>
                        </td>
                    </tr>


                    <tr>
                        <td class="label-td" colspan="2">
                            <label for="tele" id="tele-label">Mobile Number <span style="color: red">*</span></label>
                            <div style="display: flex; align-items: center;">
                                <input type="text" id="countryCode" name="countryCode" value="<?php echo isset($countryCode) ? htmlspecialchars($countryCode) : ''; ?>" readonly
                                    style="width: 50px; text-align: center;">
                                <input type="tel" id="tele" name="tele" placeholder="e.g., 9123456789" required value="<?php echo isset($tele) ? htmlspecialchars($tele) : ''; ?>">
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td class="label-td" colspan="2">
                            <label for="branch_id">Preferred Branch <span style="color: red">*</span></label>
                            <select name="branch_id" class="input-text" required>
                                <option value="">-- Select Branch --</option>
                                <?php foreach ($signup_branches as $br): ?>
                                    <option value="<?php echo $br['id']; ?>" <?php echo (isset($branch_id) && intval($branch_id) === intval($br['id'])) ? 'selected' : ''; ?>><?php echo htmlspecialchars($br['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>


                    <script>
                        $(document).ready(function () {
                            // Initialize Select2 with search
                            $('.searchable-select').select2({
                                placeholder: "e.g., Philippines",
                                allowClear: false
                            });
                        });


                        // Updates the country code field based on the selected country.
                        function updateCountryCode() {
                            let countrySelect = document.getElementById("country");
                            let countryCodeInput = document.getElementById("countryCode");
                            countryCodeInput.value = countrySelect.value;
                        }


                        // Prepend the country code to the mobile number on form submission.
                        function addCountryCode() {
                            let countryCode = document.getElementById("countryCode").value;
                            let teleField = document.getElementById("tele");


                            // Only prepend if both values exist.
                            if (teleField.value && countryCode) {
                                teleField.value = countryCode + teleField.value;
                            }
                            return true; // Allow the form to submit.
                        }
                    </script>


                    <tr>
                        <td class="label-td" colspan="2">
                            <label for="newpassword">Create Password: <span style="color: red">*</span></label>


                        </td>
                    </tr>
                    <tr>
                        <td class="label-td" colspan="2">
                            <div class="password-container" style="position: relative;">
                                <input type="password" id="newpassword" name="newpassword" placeholder="Create Password"
                                    required
                                    pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$"
                                    title="Password must be at least 8 characters long, and include at least one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&).">
                                <div class="password-toggle-icon">
                                    <i class="fa-solid fa-eye-slash" id="toggleNewPassword"></i>
                                </div>
                            </div>
                            <div id="password-popup" class="password-popup">
                                <p id="length">❌ At least 8 characters long</p>
                                <p id="uppercase">❌ At least one uppercase letter</p>
                                <p id="lowercase">❌ At least one lowercase letter</p>
                                <p id="number">❌ At least one number</p>
                                <p id="special">❌ At least one special character (@$!%*?&)</p>
                            </div>
                        </td>
                    </tr>


                    <script>
                        let passwordInput = document.getElementById("newpassword");
                        let popup = document.getElementById("password-popup");


                        passwordInput.addEventListener("input", function () {
                            validatePassword();
                        });


                        passwordInput.addEventListener("focus", function () {
                            // Show popup only when the user starts typing
                            if (passwordInput.value.length > 0) {
                                popup.style.display = "block";
                            }
                        });


                        passwordInput.addEventListener("blur", function () {
                            // Hide popup when user leaves the input field
                            popup.style.display = "none";
                        });


                        function validatePassword() {
                            let password = passwordInput.value;
                            let length = document.getElementById("length");
                            let uppercase = document.getElementById("uppercase");
                            let lowercase = document.getElementById("lowercase");
                            let number = document.getElementById("number");
                            let special = document.getElementById("special");


                            let borderColor = "#2f396d";
                            let validCount = 0;


                            if (password.length === 0) {
                                passwordInput.style.borderColor = "#2f396d";
                                popup.style.display = "none";
                                return;
                            }


                            popup.style.display = "block";


                            if (password.length >= 8) {
                                length.innerHTML = "✅ At least 8 characters long";
                                length.classList.add("valid");
                                validCount++;
                            } else {
                                length.innerHTML = "❌ At least 8 characters long";
                                length.classList.remove("valid");
                            }


                            if (/[A-Z]/.test(password)) {
                                uppercase.innerHTML = "✅ At least one uppercase letter";
                                uppercase.classList.add("valid");
                                validCount++;
                            } else {
                                uppercase.innerHTML = "❌ At least one uppercase letter";
                                uppercase.classList.remove("valid");
                            }


                            if (/[a-z]/.test(password)) {
                                lowercase.innerHTML = "✅ At least one lowercase letter";
                                lowercase.classList.add("valid");
                                validCount++;
                            } else {
                                lowercase.innerHTML = "❌ At least one lowercase letter";
                                lowercase.classList.remove("valid");
                            }


                            if (/\d/.test(password)) {
                                number.innerHTML = "✅ At least one number";
                                number.classList.add("valid");
                                validCount++;
                            } else {
                                number.innerHTML = "❌ At least one number";
                                number.classList.remove("valid");
                            }


                            if (/[@$!%*?&]/.test(password)) {
                                special.innerHTML = "✅ At least one special character (@$!%*?&)";
                                special.classList.add("valid");
                                validCount++;
                            } else {
                                special.innerHTML = "❌ At least one special character (@$!%*?&)";
                                special.classList.remove("valid");
                            }


                            if (validCount === 5) {
                                borderColor = "green";
                            } else if (validCount >= 3) {
                                borderColor = "yellow";
                            } else {
                                borderColor = "red";
                            }


                            passwordInput.style.borderColor = borderColor;
                        }
                    </script>




                    <tr>
                        <td class="label-td" colspan="2">
                            <label for="cpassword">Confirm Password: <span style="color: red">*</span></label>
                        </td>
                    </tr>
                    <tr>
                        <td class="label-td" colspan="2">
                            <div class="password-container" style="position: relative;">
                                <input type="password" id="cpassword" name="cpassword" placeholder="Confirm Password"
                                    required onkeyup="validateConfirmPassword()">
                                <div class="password-toggle-icon">
                                    <i class="fa-solid fa-eye-slash" id="toggleConfirmPassword"></i>
                                </div>
                            </div>
                            <div id="password-mismatch" class="error-message">❌ Passwords do not match</div>
                        </td>
                    </tr>


                    <tr>
                        <td colspan="2">
                            <?php if (!empty($error)) { ?>
                                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                            <?php } ?>
                        </td>
                    </tr>


                    <script>
                        function validateConfirmPassword() {
                            let password = document.getElementById("newpassword").value;
                            let confirmPassword = document.getElementById("cpassword").value;
                            let confirmPasswordField = document.getElementById("cpassword");
                            let errorMessage = document.getElementById("password-mismatch");


                            if (confirmPassword.length > 0) {
                                if (password === confirmPassword) {
                                    confirmPasswordField.style.borderColor = "#39a149";
                                    errorMessage.style.display = "none";
                                } else {
                                    confirmPasswordField.style.borderColor = "#f93d3d";
                                    errorMessage.style.display = "block";
                                }
                            } else {
                                confirmPasswordField.style.borderColor = "#2f396d";
                                errorMessage.style.display = "none";
                            }
                        }
                    </script>


                    <script>
                        // Toggle New Password Visibility
                        document.getElementById("toggleNewPassword").addEventListener("click", function () {
                            let passwordInput = document.getElementById("newpassword");
                            let icon = this;
                            if (passwordInput.type === "password") {
                                passwordInput.type = "text";
                                icon.classList.remove("fa-eye-slash");
                                icon.classList.add("fa-eye");
                            } else {
                                passwordInput.type = "password";
                                icon.classList.remove("fa-eye");
                                icon.classList.add("fa-eye-slash");
                            }
                        });


                        // Toggle Confirm Password Visibility
                        document.getElementById("toggleConfirmPassword").addEventListener("click", function () {
                            let passwordInput = document.getElementById("cpassword");
                            let icon = this;
                            if (passwordInput.type === "password") {
                                passwordInput.type = "text";
                                icon.classList.remove("fa-eye-slash");
                                icon.classList.add("fa-eye");
                            } else {
                                passwordInput.type = "password";
                                icon.classList.remove("fa-eye");
                                icon.classList.add("fa-eye-slash");
                            }
                        });
                    </script>


                    <tr>
                        <td class="label-td" colspan="2">
                            
                            <div class="certify-container">
                                <input type="checkbox" name="certify" id="certify" class="small-checkbox" required>
                                <label class="certify" for="certify">
                                    I hereby certify that, to the best of my knowledge, the provided information is true
                                    and accurate.
                                </label>
                            </div>
                            <!-- <div class="terms-container">
                                <p class="terms-text">By creating an account, you agree to our <a href="terms-and-conditions.php" class="terms-link">Terms and Conditions</a>.</p>
                            </div> -->
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <input type="reset" value="Clear" class="clear-btn">
                        </td>
                        <td>
                            <input type="submit" value="Sign up" class="signup-btn">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <label for="" class="bottom-text">Already have an account? </label>
                            <a href="login.php" class="login-link">Login</a>
                            <br><br>
                        </td>
                    </tr>
                    </tr>
                </form>
            </table>
        </div>
    </div>
</body>


</html>



