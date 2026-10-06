<?php
/**
 * Debug Test File - Check PHP Configuration
 * REQUIRES AUTHENTICATION
 */

// Start session and check auth
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (!isAuthenticated()) {
    http_response_code(401);
    die('Unauthorized. Please <a href="index.php">login</a> first.');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP Configuration Test</h1>";

// Check PHP version
echo "<h2>1. PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo (version_compare(phpversion(), '8.0.0', '>=')) ? "✓ PHP 8.0+ OK" : "✗ PHP version too old";

// Check required extensions
echo "<h2>2. Required Extensions</h2>";
$extensions = ['curl', 'json', 'session'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo $ext . ": " . ($loaded ? "✓ Loaded" : "✗ Missing") . "<br>";
}

// Check file permissions
echo "<h2>3. Directory Permissions</h2>";
$dirs = ['uploads', 'logs'];
foreach ($dirs as $dir) {
    $writable = is_writable(__DIR__ . '/' . $dir);
    echo $dir . "/: " . ($writable ? "✓ Writable" : "✗ Not writable") . "<br>";
}

// Test session
echo "<h2>4. Session Test</h2>";
try {
    session_start();
    $_SESSION['test'] = 'working';
    echo "✓ Session working<br>";
} catch (Exception $e) {
    echo "✗ Session error: " . $e->getMessage() . "<br>";
}

// Check config file
echo "<h2>5. Config File</h2>";
if (file_exists(__DIR__ . '/config.php')) {
    echo "✓ config.php exists<br>";
    require_once __DIR__ . '/config.php';
    echo "API URL: " . AC_API_URL . "<br>";
    echo "API Key: " . (AC_API_KEY === 'YOUR_API_KEY_HERE' ? '⚠ Not configured yet' : '✓ Configured') . "<br>";
} else {
    echo "✗ config.php not found<br>";
}

// Test cURL
echo "<h2>6. cURL Test</h2>";
if (function_exists('curl_init')) {
    $ch = curl_init('https://www.google.com');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($result !== false) {
        echo "✓ cURL working<br>";
    } else {
        echo "✗ cURL error: " . $error . "<br>";
    }
} else {
    echo "✗ cURL not available<br>";
}

echo "<h2>7. Test Complete</h2>";
echo "<p>If all checks pass, the application should work. If you see errors above, fix those first.</p>";
echo "<p><a href='index.php'>Go to Main Application</a></p>";
