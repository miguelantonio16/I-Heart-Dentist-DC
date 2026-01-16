<?php
// 1. Clean output
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

// 2. SMART PATH FINDER (Same as calendar fix)
$possible_paths = [
    __DIR__ . '/../api/paymongo_config.php',
    dirname(__DIR__) . '/api/paymongo_config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/IHeartDentistDC/api/paymongo_config.php',
    'C:/xampp/htdocs/IHeartDentistDC/api/paymongo_config.php'
];

$configFile = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $configFile = $path;
        break;
    }
}

if (!$configFile) {
    die("Error: Configuration file not found.");
}

require_once($configFile);

// 3. Process Payment
if (isset($_GET['id']) && isset($_GET['amount'])) {
    $appoid = $_GET['id'];
    $amount = (float)$_GET['amount']; // Ensure it's a number
    $source = isset($_GET['source']) ? strtolower(trim($_GET['source'])) : 'balance'; // 'reservation' or 'balance'

    // SAFETY: Ensure amount is at least 100 PHP (PayMongo minimum)
    if ($amount < 100) {
        die("Error: Invalid amount (₱$amount). The balance might be zero or already paid.");
    }

    // Describe payment based on source
    $description = ($source === 'reservation')
        ? "Reservation Fee Payment for Appointment ID #$appoid"
        : "Balance Payment for Appointment ID #$appoid";
    // Build success/cancel URLs from actual filesystem path to avoid hard-coded folder assumptions causing 404.
    $scheme = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = $_SERVER['HTTP_HOST'];
    $successFile = realpath(__DIR__ . '/payment_success.php');
    $myAppointmentFile = realpath(__DIR__ . '/my_appointment.php');
    $docRoot = rtrim(realpath($_SERVER['DOCUMENT_ROOT']), DIRECTORY_SEPARATOR);
    $successRel = $successFile ? str_replace($docRoot, '', $successFile) : '/patient/payment_success.php';
    $myApptRel = $myAppointmentFile ? str_replace($docRoot, '', $myAppointmentFile) : '/patient/my_appointment.php';
    // Normalize backslashes to forward slashes (Windows paths)
    $successRel = str_replace('\\', '/', $successRel);
    $myApptRel = str_replace('\\', '/', $myApptRel);
    // Ensure leading slash
    if ($successRel[0] !== '/') { $successRel = '/' . $successRel; }
    if ($myApptRel[0] !== '/') { $myApptRel = '/' . $myApptRel; }
    // Build success/cancel URLs based on payment source
    if ($source === 'reservation') {
        // Send back to payment_success with type=reservation; cancel to My Booking and include appoid
        $success_url = $scheme . '://' . $host . $successRel . "?id=$appoid&type=reservation";
        // Compute My Booking path
        $myBookingFile = realpath(__DIR__ . '/my_booking.php');
        $myBookingRel = $myBookingFile ? str_replace($docRoot, '', $myBookingFile) : '/patient/my_booking.php';
        $myBookingRel = str_replace('\\', '/', $myBookingRel);
        if ($myBookingRel[0] !== '/') { $myBookingRel = '/' . $myBookingRel; }
        $cancel_url  = $scheme . '://' . $host . $myBookingRel . "?error=cancelled&appoid=$appoid";
    } else {
        $success_url = $scheme . '://' . $host . $successRel . "?id=$appoid&type=balance";
        $cancel_url  = $scheme . '://' . $host . $myApptRel . "?error=cancelled";
    }

    if (function_exists('createPayMongoSession')) {
        $result = createPayMongoSession($amount, $description, $success_url, $cancel_url);

        if (isset($result['data']['attributes']['checkout_url'])) {
            header("Location: " . $result['data']['attributes']['checkout_url']);
            exit();
        } else {
            $err = isset($result['errors'][0]['detail']) ? $result['errors'][0]['detail'] : 'Unknown API Error';
            echo "<h3>Payment Gateway Error</h3><p>$err</p>";
            $fallback = ($source === 'reservation') ? 'my_booking.php' : 'my_appointment.php';
            echo "<a href='$fallback'>Go Back</a>";
        }
    } else {
        die("Error: Payment function missing.");
    }
    } else {
    require_once __DIR__ . '/../inc/redirect_helper.php';
    redirect_with_context('my_appointment.php');
}
?>
