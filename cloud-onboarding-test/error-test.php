<?php
/**
 * Simple Error Test
 * REQUIRES AUTHENTICATION
 */

// Start session and check auth
session_start();

// Try to load config and functions for auth check
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/functions.php';
    
    if (!isAuthenticated()) {
        http_response_code(401);
        die('Unauthorized. Please <a href="index.php">login</a> first.');
    }
}

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<!DOCTYPE html><html><head><title>Error Test</title></head><body>";
echo "<h1>Testing Basic PHP</h1>";
echo "<p>If you see this, PHP is working.</p>";

// Test 1: Check if files exist
echo "<h2>File Check</h2>";
$files = ['config.php', 'functions.php', 'index.php', 'send.php'];
foreach ($files as $file) {
    echo "$file: " . (file_exists(__DIR__ . '/' . $file) ? '✓ Exists' : '✗ Missing') . "<br>";
}

// Test 2: Try to include config
echo "<h2>Config Include Test</h2>";
try {
    require_once __DIR__ . '/config.php';
    echo "✓ Config loaded successfully<br>";
    echo "Upload dir: " . UPLOAD_DIR . "<br>";
    echo "Log dir: " . LOG_DIR . "<br>";
} catch (Throwable $e) {
    echo "✗ Config error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}

// Test 3: Try to include functions
echo "<h2>Functions Include Test</h2>";
try {
    require_once __DIR__ . '/functions.php';
    echo "✓ Functions loaded successfully<br>";
} catch (Throwable $e) {
    echo "✗ Functions error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}

echo "<hr>";
echo "<p><a href='test.php'>Full Test</a> | <a href='index.php'>Main App</a></p>";
echo "</body></html>";
