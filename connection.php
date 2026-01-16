<?php
// Config loading strategy:
// 1. If a local override file (config.local.php) exists AND we are on localhost, load it first.
// 2. Else load production config.php if present.
// 3. Fall back to environment variables or defaults.

$localHostnames = ['127.0.0.1', 'localhost'];
// In CLI, SERVER_NAME is often unset on production; don't default to 'localhost' to avoid loading local config.
$serverHost = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';

// Enable verbose error reporting automatically on localhost to surface 500 causes.
if ($serverHost !== '' && in_array($serverHost, $localHostnames, true)) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

$localConfig = __DIR__ . '/config.local.php';
$prodConfig  = __DIR__ . '/config.php';

if ($serverHost !== '' && in_array($serverHost, $localHostnames, true) && is_file($localConfig)) {
    require_once $localConfig;
} elseif (is_file($prodConfig)) {
    require_once $prodConfig;
}

// Determine DB parameters using precedence: defined constants -> env vars -> hard-coded defaults.
$db_host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: '127.0.0.1');
$db_user = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: 'root');
$db_pass = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: '');
$db_name = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: 'sdmc');
$db_port = null;
if (defined('DB_PORT')) {
    $db_port = DB_PORT;
} elseif (getenv('DB_PORT')) {
    $db_port = getenv('DB_PORT');
}

// Create mysqli connection. If a port is provided, pass it; otherwise attempt common fallback ports.
if ($db_port) {
    $database = @new mysqli($db_host, $db_user, $db_pass, $db_name, (int)$db_port);
} else {
    // Try default then a couple of alternates (useful if user changed MySQL port to avoid conflicts)
    $candidatePorts = [3306, 3307, 3308];
    $database = null;
    $lastError = '';
    foreach ($candidatePorts as $p) {
        $tmp = @new mysqli($db_host, $db_user, $db_pass, $db_name, $p);
        if (!$tmp->connect_error) {
            $database = $tmp;
            break;
        } else {
            $lastError = $tmp->connect_error;
        }
    }
    if ($database === null) {
        // Final attempt with implicit port (in case socket resolution differs)
        $database = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    }
}

if ($database->connect_error) {
    error_log('DB connection failed: ' . $database->connect_error);
    if (in_array($serverHost, $localHostnames, true)) {
        die('Database connection error: ' . $database->connect_error . '\nTried ports: ' . ($db_port ?: '3306,3307,3308') . '\nHost: ' . $db_host);
    }
    die('Database connection error.');
}
?>