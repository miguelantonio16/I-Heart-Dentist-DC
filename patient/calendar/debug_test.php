<?php
// Enable error reporting to see what's wrong
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>🔍 Diagnostics Tool</h2>";

// 1. Check Root Path
$rootPath = dirname(dirname(__DIR__)); 
echo "<b>Root Path Detected:</b> " . $rootPath . "<br>";

// 2. Check Paths
$apiPath = $rootPath . '/api/paymongo_config.php';
$dbPath = $rootPath . '/connection.php';

echo "<b>Looking for API Config at:</b> " . $apiPath . " ... ";
if (file_exists($apiPath)) {
    echo "<span style='color:green'>✅ FOUND</span><br>";
} else {
    echo "<span style='color:red'>❌ NOT FOUND</span> (Check folder structure)<br>";
}

echo "<b>Looking for DB Connection at:</b> " . $dbPath . " ... ";
if (file_exists($dbPath)) {
    echo "<span style='color:green'>✅ FOUND</span><br>";
} else {
    echo "<span style='color:red'>❌ NOT FOUND</span><br>";
}

// 3. Test Database Connection
echo "<hr><h3>Testing Database...</h3>";
if (file_exists($dbPath)) {
    require_once $dbPath;
    if (isset($database) && $database->connect_error) {
        echo "<span style='color:red'>❌ Database Connection Failed: " . $database->connect_error . "</span><br>";
    } elseif (isset($database)) {
        echo "<span style='color:green'>✅ Database Connected Successfully!</span><br>";
    } else {
        echo "<span style='color:red'>❌ \$database variable not found in connection.php</span><br>";
    }
}

// 4. Test PayMongo Config
echo "<hr><h3>Testing PayMongo API...</h3>";
if (file_exists($apiPath)) {
    require_once $apiPath;
    if (function_exists('createPayMongoSession')) {
        echo "<span style='color:green'>✅ PayMongo Function Loaded!</span><br>";
        
        // Test cURL
        if (function_exists('curl_init')) {
            echo "<span style='color:green'>✅ cURL is enabled.</span><br>";
        } else {
            echo "<span style='color:red'>❌ cURL is DISABLED in PHP. Please enable it in php.ini.</span><br>";
        }
    } else {
        echo "<span style='color:red'>❌ Function 'createPayMongoSession' missing in paymongo_config.php</span><br>";
    }
}
?>