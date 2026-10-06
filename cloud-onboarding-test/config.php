<?php
/**
 * 4BS Cloud Migration Mailer - Configuration
 */

// ActiveCampaign API Configuration
define('AC_API_URL', 'https://4bs.api-us1.com');
define('AC_API_KEY', '511f18fa85937b5b40d929967f3d790fffd957e028f1f56a93f789d6a8fa80b2890934d8'); // Replace with actual API key

// Email Settings
define('FROM_EMAIL', 'cloud@4bs.com');
define('FROM_NAME', '4BS Cloud');
define('REPLY_TO_EMAIL', 'cloud@4bs.com');

// Rate Limiting
define('RATE_LIMIT', 10); // Emails per minute
define('RATE_LIMIT_DELAY', 6); // Seconds between emails (60/10 = 6)

// Application Settings
define('ADMIN_USER', 'spaties in een gebruikersnaam');
define('ADMIN_PASS', password_hash('UHmYtxjz2f&sX457dyPh!m%g^uHPDn7Z!', PASSWORD_DEFAULT)); // Change this!
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('LOG_DIR', __DIR__ . '/logs/');
define('DATA_DIR', __DIR__ . '/data/');
define('DB_PATH', DATA_DIR . 'mailer.sqlite');

// Supported Languages
define('SUPPORTED_LANGUAGES', ['nl', 'en', 'fr']);

// Create necessary directories
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();
