<?php

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

// Read configuration from Render environment variables
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: '';

// Google OAuth credentials
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
}

if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
}

if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: '');
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = new mysqli(
        $db_host,
        $db_user,
        $db_pass,
        $db_name
    );

    $conn->set_charset('utf8mb4');

    // Set MySQL session timezone to Philippine time (UTC+8)
    $conn->query("SET time_zone = '+08:00'");

} catch (Throwable $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Please try again later.');
}