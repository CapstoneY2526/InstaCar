<?php

// Force PHP timezone to Philippine time (must be before any date/time calls)
date_default_timezone_set('Asia/Manila');

$env = parse_ini_file(__DIR__ . '/../.env');

$db_host = $env['DB_HOST'] ?? '127.0.0.1';
$db_user = $env['DB_USER'] ?? 'root';
$db_pass = $env['DB_PASS'] ?? '';
$db_name = $env['DB_NAME'] ?? '';

// Load Google Credentials dynamically from .env
define('GOOGLE_CLIENT_ID', $env['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $env['GOOGLE_CLIENT_SECRET'] ?? '');
define('GOOGLE_REDIRECT_URI', $env['GOOGLE_REDIRECT_URI'] ?? '');

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset("utf8mb4");

    // Force MySQL session timezone to PH time (UTC+8)
    // Affects: NOW(), CURRENT_TIMESTAMP, and any timestamp columns
    // Ensures settled_at is stored in PH time, matching PHP's time()
    $conn->query("SET time_zone = '+08:00'");

} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}