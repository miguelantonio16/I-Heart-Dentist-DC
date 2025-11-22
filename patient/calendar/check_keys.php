<?php
// patient/calendar/check_keys.php

echo "<h2>🔑 Key Detective</h2>";

// 1. Try to load the config using the same logic as your app
$rootPath = dirname(dirname(__DIR__)); // C:\xampp\htdocs\IHeartDentistDC
$configFile = $rootPath . '/api/paymongo_config.php';

echo "<b>Looking for file at:</b> " . $configFile . "<br>";

if (file_exists($configFile)) {
    echo "<span style='color:green'>✅ File Found!</span><br>";
    include($configFile);

    echo "<hr>";
    echo "<b>Key currently loaded in PHP:</b> ";

    if (defined('PAYMONGO_SECRET_KEY')) {
        $key = PAYMONGO_SECRET_KEY;
        // Show only the first 7 chars for security/verification
        $prefix = substr($key, 0, 7); 

        if ($prefix === 'sk_test') {
            echo "<h3 style='color:green'>✅ USING TEST KEY ($prefix...)</h3>";
            echo "Great! Your code is loading the correct keys.";
        } elseif ($prefix === 'sk_live') {
            echo "<h3 style='color:red'>❌ USING LIVE KEY ($prefix...)</h3>";
            echo "<b>FIX:</b> You edited the wrong file! Open the file at the path shown above and change the keys there.";
        } else {
            echo "<span style='color:orange'>❓ Unknown Key format: $key</span>";
        }
    } else {
        echo "<span style='color:red'>❌ Constant PAYMONGO_SECRET_KEY is NOT defined.</span>";
    }
} else {
    echo "<h3 style='color:red'>❌ File NOT Found.</h3>";
    echo "Make sure you saved 'paymongo_config.php' inside the <b>api</b> folder.";
}
?>
