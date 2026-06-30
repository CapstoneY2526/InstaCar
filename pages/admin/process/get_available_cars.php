<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

$start_date = $_GET['start_date'] ?? '';
$start_time = $_GET['start_time'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$end_time = $_GET['end_time'] ?? '';
$type = $_GET['type'] ?? '';

// Validate inputs
if (!$start_date || !$start_time || !$end_date || !$end_time) {
    echo json_encode([]);
    exit();
}

// Create DateTime objects
$start = strtotime("$start_date $start_time");
$end = strtotime("$end_date $end_time");

// ❌ invalid
if ($end <= $start) {
    echo json_encode([]);
    exit();
}

// ✅ FIX: Use 9.99 para sa tolerance
$diff_hours = ($end - $start) / 3600;

if ($diff_hours < 9.99) {
    echo json_encode([]);
    exit();
}

// Escape AFTER validation
$start_date = mysqli_real_escape_string($conn, $start_date);
$start_time = mysqli_real_escape_string($conn, $start_time);
$end_date = mysqli_real_escape_string($conn, $end_date);
$end_time = mysqli_real_escape_string($conn, $end_time);
$type = mysqli_real_escape_string($conn, $type);

// ✅ FIXED: Include BOTH 'Available' AND 'Active' status
$query = "SELECT c.* 
          FROM cars c 
          WHERE c.status IN ('Available', 'Active')";

if (!empty($type)) {
    $query .= " AND c.brand = '$type'";
}

$query .= " ORDER BY c.brand ASC, c.model ASC";

$res = mysqli_query($conn, $query);

if (!$res) {
    echo json_encode(['error' => mysqli_error($conn)]);
    exit();
}

$cars = [];

while ($row = mysqli_fetch_assoc($res)) {
    $cars[] = $row;
}

// Debug log
error_log("Found " . count($cars) . " cars with status Available or Active");

echo json_encode($cars);
?>