<?php
// Production configuration (Z.com cPanel)

// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'sbbfnklu_admin');          // e.g. mycpanel_iheartusr
define('DB_PASS', 'Admin@2025');    // e.g. Generated 16–20 char password
define('DB_NAME', 'sbbfnklu_iheartdentistdc');          // e.g. mycpanel_iheartdentist
// define('DB_PORT', 3306); // Uncomment only if host supplies a non-default port

// Base URL (adjust if in subdirectory)
define('BASE_URL', 'http://iheartdentistdc.com/');

// Application debug (always false in production)
define('APP_DEBUG', false);

// Timezone
date_default_timezone_set('Asia/Manila'); // Pick your local timezone

// Optional SMTP (uncomment if you send email directly)
// define('SMTP_HOST', 'smtp.z.com');
// define('SMTP_PORT', 587);
// define('SMTP_USER', 'no-reply@yourdomain.com');
// define('SMTP_PASS', 'STRONG_SMTP_PASSWORD');
// define('SMTP_SECURE', 'tls');

// --- Optional security tweaks ---
// If you want to harden session handling centrally, you could place:
// ini_set('session.cookie_httponly', 1);
// ini_set('session.cookie_secure', 1); // Only if HTTPS enforced
// ini_set('session.use_strict_mode', 1);
