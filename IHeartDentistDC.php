<?php
// Start session
session_start();

// Enable verbose errors on localhost (defensive in case connection.php not yet loaded or error_reporting suppressed).
if (in_array($_SERVER['SERVER_NAME'] ?? 'localhost', ['127.0.0.1', 'localhost'], true)) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Check if any user is logged in and redirect them
if (isset($_SESSION["user"]) && isset($_SESSION["usertype"])) {
    switch ($_SESSION["usertype"]) {
        case 'a': // Admin
            header("Location: admin/dashboard.php");
            exit();
        case 'd': // Dentist
            header("Location: dentist/dashboard.php");
            exit();
        case 'p': // Patient
            header("Location: patient/dashboard.php");
            exit();
    }
}
require_once 'connection.php';

// Safely load clinic info (avoid fatal on missing table / query failure)
$clinic_info = [
    'clinic_name' => 'IHeartDentistDC',
    'clinic_description' => '',
    'address' => '',
    'phone' => '',
    'email' => '',
    'facebook_url' => '#',
    'instagram_url' => '#'
];
$clinic_info_rs = $database->query("SELECT * FROM clinic_info WHERE id=1");
if ($clinic_info_rs && $clinic_info_rs->num_rows === 1) {
    $row = $clinic_info_rs->fetch_assoc();
    $clinic_info = array_merge($clinic_info, $row);
} else {
    // Fallback: first available row if id=1 missing
    $fallback_rs = $database->query("SELECT * FROM clinic_info ORDER BY id ASC LIMIT 1");
    if ($fallback_rs && $fallback_rs->num_rows === 1) {
        $row = $fallback_rs->fetch_assoc();
        $clinic_info = array_merge($clinic_info, $row);
    } else {
        error_log('Clinic info query failed or empty: ' . $database->error);
    }
}






?>


<!DOCTYPE html>


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IHeartDentistDC</title>
    <link rel="icon" href="Media/Icon/logo.png" type="image/png">
    <link rel="stylesheet" href="css/IHeartDentistDC.css">
    <link rel="stylesheet" href="css/loading.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Alfa+Slab+One&family=Architects+Daughter&family=Archivo+Black&family=IBM+Plex+Mono:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;1,100;1,200;1,300;1,400;1,500;1,600;1,700&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Mulish:ital,wght@0,200..1000;1,200..1000&family=Oswald:wght@200..700&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/owl.carousel@2.3.4/dist/assets/owl.carousel.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/owl.carousel@2.3.4/dist/assets/owl.theme.default.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/owl.carousel@2.3.4/dist/owl.carousel.min.js"></script>


</head>


<body>
    <header>
        <nav>
            <ul class="sidebar">
                <li onclick=hideSidebar()><a href="#"><img src="Media/Icon/Black/navbar.png" class="navbar-logo"
                            alt="Navigation Bar"></a></li>
                <!-- logo removed from sidebar to avoid duplication -->
                <li><a href="#">Home</a></li>
                <!--<li><a href="#">About</a></li>-->
                <li><a href="#services">Services</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="patient/signup.php">Sign up</a></li>
                <li><a href="patient/login.php">Login</a></li>
            </ul>
            <ul>
                <li><a href="#"><img src="Media/Icon/logo.png" class="logo-name" alt="IHeartDentistDC"></a>
                </li>
                <li class="hideOnMobile"><a href="#">Home</a></li>
                <!--<li class="hideOnMobile"><a href="#">About</a></li> -->
                <li class="hideOnMobile"><a href="#services">Services</a></li>
                <li class="hideOnMobile"><a href="#contact">Contact</a></li>
                <li class="hideOnMobile"><a href="patient/signup.php" class="reg-btn">Sign up</a></li>
                <li class="hideOnMobile"><a href="patient/login.php" class="log-btn">Login</a></li>
                <li class="menu-button" onclick=showSidebar()><a href="#"><img src="Media/Icon/Black/navbar.png"
                            class="navbar-logo" alt="Navigation Bar"></a></li>
            </ul>
        </nav>


    </header>
    <main>
        <section id="home">
            <div class="container-fluid h-100">
                <div class="row no-gutters h-100 align-items-center">
                    <!-- Left Content Column -->
                    <div class="col-md-6">
                        <div class="leftside">
                            <div>
                                <h1 class="tagline1">SHINE <span class="highlight">BRIGHT</span></h1>
                                <h1 class="tagline2">TODAY! </h1>
                                <p class="description">Love your smile again. Our dedicated team in Bacoor and Makati combines high-tech treatments with a gentle touch to boost your confidence. Whether you need a simple cleaning or a complete smile makeover, we provide the personalized care you need to shine bright.</p>
                            </div>
                            <div class="register-login">
                                <a href="patient/signup.php" class="cta-getstarted">Get started</a>
                            </div>
                        </div>
                    </div>


                    <!-- Right Image Column -->
                    <div class="col-md-6 h-100">
                        <div class="rightside h-100 d-flex align-items-center justify-content-end">
                            <div class="device-container">
                                <!-- Device frame -->
                                <div class="device-frame">
                                    <!-- Browser top bar -->
                                    <div class="browser-bar">
                                        <div class="browser-buttons">
                                            <span class="browser-button red"></span>
                                            <span class="browser-button yellow"></span>
                                            <span class="browser-button green"></span>
                                        </div>
                                        <div class="browser-address">
                                            <div class="url-bar">IHeartDentistDC.com</div>
                                        </div>
                                    </div>
                                    <!-- Website content -->
                                    <div class="website-content">
                                        <div class="website-logo">
                                            <img src="Media/Icon/logo.png" alt="IHeartDentistDC Logo">
                                        </div>
                                        <div class="website-ui">
                                            <div class="ui-element header-bar"></div>
                                            <div class="ui-element calendar-box"></div>
                                            <div class="ui-element appointment-list">
                                                <div class="appointment-item"></div>
                                                <div class="appointment-item"></div>
                                                <div class="appointment-item"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Notification elements -->
                                <div class="notification notification-1">
                                    <div class="notification-icon">✓</div>
                                    <div class="notification-content">
                                        <p class="notification-title">Appointment Confirmed</p>
                                        <p class="notification-time">Today, 2:30 PM</p>
                                    </div>
                                </div>
                                <div class="notification notification-2">
                                    <div class="notification-icon">🔔</div>
                                    <div class="notification-content">
                                        <p class="notification-title">Reminder</p>
                                        <p class="notification-time">Consultation Tomorrow</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <br><br>
        </section>
        <!--
        <section id="about">
            <span>
                <p class="nextpage"></p>
            </span>
            <div>
                <h2 class="title">ABOUT SDMC</h2>
            </div>
            <div>
               
            </div>
        </section>
-->


        <?php
        require_once 'connection.php';


        function getServices($database)
        {
            $services = array();
            $query = "SELECT * FROM services";
            $result = $database->query($query);


            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $services[] = $row;
                }
            }


            return $services;
        }


        $services = getServices($database);
        ?>


        <section id="services">
            <div>
                <br><br>
                <h2 class="title">SERVICES OFFERED</h2>
            </div>
            <img src="Media/Icon/Blue/tooth.png" alt="" class="floating-tooth tooth-left" style="top: 30%;">
            <img src="Media/Icon/Blue/tooth.png" alt="" class="floating-tooth tooth-right" style="top: 60%;">
            <img src="Media/Icon/Blue/tooth.png" alt="" class="floating-tooth tooth-top">
            <img src="Media/Icon/Blue/tooth.png" alt="" class="floating-tooth tooth-bottom">
            <div class="carousel-container">
                <button class="carousel-button prev" aria-label="Previous slide">&#10094;</button>
                <div class="carousel">
                    <?php foreach ($services as $service): ?>
                        <div class="card">
                            <div class="card-content">
                                <img src="<?= htmlspecialchars($service['image_path']) ?>"
                                    alt="<?= htmlspecialchars($service['procedure_name']) ?>">
                                <h3><?= htmlspecialchars($service['procedure_name']) ?></h3>
                                <p class="description"><?= htmlspecialchars($service['description']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="carousel-button next" aria-label="Next slide">&#10095;</button>
            </div>
        </section>




        <?php
        // Load branches to display multiple locations (e.g., Bacoor, Makati)
        // Join with `branch_info` so per-branch clinic info (name/description/phone/email/map) is used when available.
        $branches_rs = $database->query(
            "SELECT b.id, b.name, b.address, bi.address AS bi_address, bi.clinic_name AS bi_clinic_name, bi.clinic_description AS bi_clinic_description, bi.phone AS bi_phone, bi.email AS bi_email, bi.facebook_url AS bi_facebook_url, bi.instagram_url AS bi_instagram_url, bi.map_embed_url AS bi_map_embed_url FROM branches b LEFT JOIN branch_info bi ON bi.branch_id = b.id ORDER BY b.id ASC"
        );
        ?>
        <section id="contact">
            <div>
                <h2 class="title">CONTACT US</h2>
            </div>
            <?php if ($branches_rs && $branches_rs->num_rows > 0): ?>
                <div class="branches-grid">
                <?php while ($branch = $branches_rs->fetch_assoc()):
                    $branchName = isset($branch['name']) ? $branch['name'] : 'Branch';
                    // Prefer per-branch address from `branch_info`, then branch table address, otherwise fallback.
                    if (!empty($branch['bi_address'])) {
                        $branchAddress = $branch['bi_address'];
                    } elseif (isset($branch['address']) && trim($branch['address']) !== '') {
                        $branchAddress = $branch['address'];
                    } else {
                        $branchAddress = $branchName . ', Philippines';
                    }

                    // Prefer per-branch values from `branch_info` (alias prefixed with bi_). Fall back to global `clinic_info`.
                    $displayClinicName = !empty($branch['bi_clinic_name']) ? $branch['bi_clinic_name'] : $clinic_info['clinic_name'];
                    // Only show per-branch clinic description when explicitly set. Do not fall back to global clinic_info to avoid showing a default placeholder.
                    $displayClinicDescription = !empty($branch['bi_clinic_description']) ? $branch['bi_clinic_description'] : '';
                    $displayPhone = !empty($branch['bi_phone']) ? $branch['bi_phone'] : $clinic_info['phone'];
                    $displayEmail = !empty($branch['bi_email']) ? $branch['bi_email'] : $clinic_info['email'];
                    $displayFacebook = !empty($branch['bi_facebook_url']) ? $branch['bi_facebook_url'] : $clinic_info['facebook_url'];
                    $displayInstagram = !empty($branch['bi_instagram_url']) ? $branch['bi_instagram_url'] : $clinic_info['instagram_url'];

                    // Determine map HTML: allow admin to provide either a full iframe or an embed URL. Otherwise build map by address.
                    if (!empty($branch['bi_map_embed_url'])) {
                        $raw = $branch['bi_map_embed_url'];
                        if (stripos($raw, '<iframe') !== false) {
                            $mapHtml = $raw; // assume admin-provided iframe markup
                        } else {
                            // treat as a URL to embed
                            $mapHtml = '<iframe src="' . htmlspecialchars($raw) . '" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
                        }
                    } else {
                        $mapSrc = 'https://www.google.com/maps?q=' . urlencode($branchAddress) . '&output=embed';
                        $mapHtml = '<iframe src="' . htmlspecialchars($mapSrc) . '" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
                    }

                ?>
                <div class="ffbox" style="margin-bottom: 24px;">
                    <div class="map-div">
                        <?php echo $mapHtml; ?>
                    </div>
                    <div class="ffbox1">
                        <h1 class="contact-title"><?= htmlspecialchars($displayClinicName) ?> — <?= htmlspecialchars($branchName) ?></h1>
                        <?php if (!empty($displayClinicDescription)): ?>
                            <p class="clinic-services"><?= htmlspecialchars($displayClinicDescription) ?></p>
                        <?php endif; ?>
                        <div class="contact-info">
                            <p><img src="Media/Icon/Blue/address.png" alt="Location" class="contact-icon"> <?= htmlspecialchars($branchAddress) ?></p>
                            <p><img src="Media/Icon/Blue/phone.png" alt="Phone" class="contact-icon"> <?= htmlspecialchars($displayPhone) ?></p>
                            <p><img src="Media/Icon/Blue/mail.png" alt="Email" class="contact-icon"> <?= htmlspecialchars($displayEmail) ?></p>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                </div>
            <?php else: ?>
                <!-- Fallback: single location (legacy) -->
                <div class="ffbox">
                    <div class="map-div">
                        <iframe src="https://www.google.com/maps?q=<?= urlencode($clinic_info['address']) ?>&output=embed" width="370" height="95%" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    <div class="ffbox1">
                        <h1 class="contact-title"><?= htmlspecialchars($clinic_info['clinic_name']) ?></h1>
                        <?php if (!empty($clinic_info['clinic_description'])): ?>
                            <p class="clinic-services"><?= htmlspecialchars($clinic_info['clinic_description']) ?></p>
                        <?php endif; ?>
                        <div class="contact-info">
                            <p><img src="Media/Icon/Blue/address.png" alt="Location" class="contact-icon"> <?= htmlspecialchars($clinic_info['address']) ?></p>
                            <p><img src="Media/Icon/Blue/phone.png" alt="Phone" class="contact-icon"> <?= htmlspecialchars($clinic_info['phone']) ?></p>
                            <p><img src="Media/Icon/Blue/mail.png" alt="Email" class="contact-icon"> <?= htmlspecialchars($clinic_info['email']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-row">
                <div class="footer-column">
                 
                </div>
                <div class="footer-column">
                    <ul class="social-icons">
                        <li>
                            <a class="facebook" href="<?= htmlspecialchars($clinic_info['facebook_url']) ?>"
                                target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-facebook"></i>
                            </a>
                        </li>
                        <li>
                            <a class="instagram" href="<?= htmlspecialchars($clinic_info['instagram_url']) ?>"
                                target="_blank" rel="noopener noreferrer">
                                <i class="fab fa-instagram"></i>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="footer-column">
                    
                </div>
            </div>
            <!-- Add this HTML code to the footer section before the closing </footer> tag -->
            <div class="help-button-container">
                <button id="helpButton" class="help-button" aria-label="Contact Developer Support">
                    ?
                </button>
            </div>
        </div>
    </footer>
    <!-- Add this HTML for the popup modal after the footer -->
    <div id="helpModal" class="help-modal">
        <div class="help-modal-content">
            <span class="close-modal">&times;</span>
            <div class="help-modal-header">
                <img src="Media/Icon/logo.png" alt="IHeartDentistDC Logo" class="modal-logo">
                <h3>Talk to Developer Support</h3>
            </div>
            <div class="help-modal-body">
            <div class="contact-info-modal">
                <h4>Contact Us</h4>
                <p><img src="Media/Icon/Blue/mail.png" alt="Email" class="contact-modal-icon"> IHeartDentistDC@gmail.com</p>
                <p><img src="Media/Icon/Blue/phone.png" alt="Phone" class="contact-modal-icon"> +63 994 803 5127</p>
            </div>
                <form id="bugReportForm" class="bug-report-form" onsubmit="redirectToGmail(event)">
                    <h4>Report an Issue</h4>
                    <div class="form-group">
                        <label for="reporterName">Your Name</label>
                        <input type="text" id="reporterName" name="reporterName" required>
                    </div>
                    <div class="form-group">
                        <label for="reporterEmail">Email</label>
                        <input type="email" id="reporterEmail" name="reporterEmail" required>
                    </div>
                    <div class="form-group">
                        <label for="issueDescription">Describe the Issue</label>
                        <textarea id="issueDescription" name="issueDescription" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="submit-report">Submit Report</button>
                </form>
            </div>
        </div>
    </div>


    <!--Navbar-->
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


    <!--Services-->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const carousel = document.querySelector('.carousel');
            const prevButton = document.querySelector('.carousel-button.prev');
            const nextButton = document.querySelector('.carousel-button.next');

            if (!carousel) return; // nothing to do

            // Scroll by a page (visible width) to avoid overshooting to the end
            function scrollByPage(direction) {
                const page = carousel.clientWidth || window.innerWidth;
                carousel.scrollBy({ left: direction * page, behavior: 'smooth' });
            }

            if (prevButton) {
                prevButton.addEventListener('click', function () {
                    scrollByPage(-1);
                });
            }

            if (nextButton) {
                nextButton.addEventListener('click', function () {
                    scrollByPage(1);
                });
            }

            // Hide buttons when at extremes
            function updateButtonVisibility() {
                if (prevButton) prevButton.style.display = carousel.scrollLeft <= 10 ? 'none' : 'flex';
                if (nextButton) nextButton.style.display = carousel.scrollLeft >= carousel.scrollWidth - carousel.clientWidth - 10 ? 'none' : 'flex';
            }

            carousel.addEventListener('scroll', updateButtonVisibility);
            updateButtonVisibility(); // Initial check

            // Handle window resize
            window.addEventListener('resize', function () {
                updateButtonVisibility();
            });

            // Drag/Swipe support (desktop + touch)
            let isDragging = false;
            let startX = 0, scrollLeft = 0;

            carousel.addEventListener('mousedown', (e) => {
                isDragging = true;
                startX = e.pageX - carousel.offsetLeft;
                scrollLeft = carousel.scrollLeft;
                carousel.style.cursor = 'grabbing';
            });

            carousel.addEventListener('mouseleave', () => {
                isDragging = false;
                carousel.style.cursor = 'grab';
            });

            carousel.addEventListener('mouseup', () => {
                isDragging = false;
                carousel.style.cursor = 'grab';
            });

            carousel.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                e.preventDefault();
                const x = e.pageX - carousel.offsetLeft;
                const walk = (x - startX) * 1.5; // Adjust scroll speed
                carousel.scrollLeft = scrollLeft - walk;
            });

            // Touch support
            carousel.addEventListener('touchstart', (e) => {
                isDragging = true;
                startX = e.touches[0].pageX - carousel.offsetLeft;
                scrollLeft = carousel.scrollLeft;
            });
            carousel.addEventListener('touchend', () => {
                isDragging = false;
            });
            carousel.addEventListener('touchmove', (e) => {
                if (!isDragging) return;
                const x = e.touches[0].pageX - carousel.offsetLeft;
                const walk = (x - startX) * 1.5;
                carousel.scrollLeft = scrollLeft - walk;
            });
        });
    </script>


    <!-- Add this JavaScript code before the closing </body> tag -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const helpButton = document.getElementById('helpButton');
            const helpModal = document.getElementById('helpModal');
            const closeModal = document.querySelector('.close-modal');
           
            // Open modal when help button is clicked
            helpButton.addEventListener('click', function() {
                helpModal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                document.body.style.position = 'fixed';
                document.body.style.width = '100%';
            });
           
            // Close modal when X is clicked
            closeModal.addEventListener('click', function() {
                helpModal.style.display = 'none';
                document.body.style.overflow = 'auto';
                document.body.style.position = 'static';
            });
           
            // Close modal when clicking outside the modal content
            window.addEventListener('click', function(event) {
                if (event.target === helpModal) {
                    helpModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                    document.body.style.position = 'static';
                }
            });
        });
    </script>
    <script>
        function redirectToGmail(event) {
    event.preventDefault();
   
    // Get form values
    const name = document.getElementById('reporterName').value;
    const email = document.getElementById('reporterEmail').value;
    const issue = document.getElementById('issueDescription').value;
   
    // Validate inputs
    if (!name || !email || !issue) {
        alert('Please fill all required fields.');
        return;
    }
   
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('Please enter a valid email address.');
        return;
    }
   
    // Create mailto link
    const subject = `Bug Report from ${name}`;
    const body = `${issue}`;
   
    const mailtoLink = `https://mail.google.com/mail/?view=cm&fs=1&to=IHeartDentistDC@gmail.com&su=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
   
    // Open Gmail compose window
    window.open(mailtoLink, '_blank');
   
    // Show confirmation and reset form
    alert('Thank you! Please send your report through the Gmail window that opened.');
    document.getElementById('bugReportForm').reset();
   
    // Close modal
    document.getElementById('helpModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.body.style.position = 'static';
}
    </script>
</body>


</html>
