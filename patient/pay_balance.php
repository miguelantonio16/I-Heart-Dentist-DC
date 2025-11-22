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

    // SAFETY: Ensure amount is at least 100 PHP (PayMongo minimum)
    if ($amount < 100) {
        die("Error: Invalid amount (₱$amount). The balance might be zero or already paid.");
    }

    $description = "Balance Payment for Appointment ID #$appoid";
    $success_url = "http://localhost/IHeartDentistDC/patient/payment_success.php?id=$appoid&type=balance";
    $cancel_url = "http://localhost/IHeartDentistDC/patient/my_appointment.php?error=cancelled";

    if (function_exists('createPayMongoSession')) {
        $result = createPayMongoSession($amount, $description, $success_url, $cancel_url);

        if (isset($result['data']['attributes']['checkout_url'])) {
            header("Location: " . $result['data']['attributes']['checkout_url']);
            exit();
        } else {
            $err = isset($result['errors'][0]['detail']) ? $result['errors'][0]['detail'] : 'Unknown API Error';
            echo "<h3>Payment Gateway Error</h3><p>$err</p>";
            echo "<a href='my_appointment.php'>Go Back</a>";
        }
    } else {
        die("Error: Payment function missing.");
    }
} else {
    header("Location: my_appointment.php");
}
?>
