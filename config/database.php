<?php

$env = parse_ini_file(__DIR__ . '/../.env');

$db_host = $env['DB_HOST'] ?? '127.0.0.1';
$db_user = $env['DB_USER'] ?? 'root';
$db_pass = $env['DB_PASS'] ?? '';
$db_name = $env['DB_NAME'] ?? '';

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}