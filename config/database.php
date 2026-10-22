<?php

date_default_timezone_set('Asia/Manila');

// Use environment variables first (Render), then local .env (XAMPP)
$env = [];
$envFile = __DIR__ . '/../.env';

if (is_file($envFile)) {
    $parsedEnv = parse_ini_file($envFile);
    if (is_array($parsedEnv)) {
        $env = $parsedEnv;
    }
}

$getConfig = function ($key, $default = '') use ($env) {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $env[$key] ?? $default;
};

$db_host = $getConfig('DB_HOST', '127.0.0.1');
$db_user = $getConfig('DB_USER', 'root');
$db_pass = $getConfig('DB_PASS', '');
$db_name = $getConfig('DB_NAME', '');

// Google OAuth credentials
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', $getConfig('GOOGLE_CLIENT_ID'));
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', $getConfig('GOOGLE_CLIENT_SECRET'));
}
if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', $getConfig('GOOGLE_REDIRECT_URI'));
}

if ($db_name === '') {
    die('Database name is missing. Check DB_NAME in your local .env or Render environment variables.');
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset('utf8mb4');
    $conn->query("SET time_zone = '+08:00'");

} catch (Throwable $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection failed. Check your database settings.');
}