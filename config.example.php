<?php
/**
 * Production configuration template.
 * Copy this file to config.php and fill in real credentials.
 * Never commit config.php with real secrets to version control.
 */

// Database credentials (use your cPanel prefix, e.g., prefix_dbname)
define('DB_HOST', 'localhost');
define('DB_USER', 'cpanelprefix_dbuser');
define('DB_PASS', 'REPLACE_WITH_STRONG_PASSWORD');
define('DB_NAME', 'cpanelprefix_dbname');
// Optional: if your host requires a specific port (usually not on shared hosting)
// define('DB_PORT', 3306);

// SMTP settings example (uncomment and adjust if centralizing email config)
// define('SMTP_HOST', 'smtp.z.com');
// define('SMTP_PORT', 587);
// define('SMTP_USER', 'no-reply@yourdomain.com');
// define('SMTP_PASS', 'REPLACE_SMTP_PASSWORD');
// define('SMTP_SECURE', 'tls'); // or 'ssl'

/*
Usage in connection.php will prefer these constants if config.php exists.
*/
