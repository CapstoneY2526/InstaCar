<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['available' => false, 'message' => 'Invalid request']);
    exit();
}

$car_id = mysqli_real_escape_string($conn, $data['car_id']);
$start_date = mysqli_real_escape_string($conn, $data['start_date']);
$end_date = mysqli_real_escape_string($conn, $data['end_date']);
$start_time = mysqli_real_escape_string($conn, $data['start_time']);
$end_time = mysqli_real_escape_string($conn, $data['end_time']);

// Check for overlapping bookings
$sql = "SELECT * FROM bookings 
        WHERE car_id = '$car_id' 
        AND status NOT IN ('Cancelled', 'Completed')
        AND (
            (start_date <= '$end_date' AND end_date >= '$start_date')
        )";

$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    echo json_encode([
        'available' => false, 
        'message' => 'This car is already booked for selected dates'
    ]);
} else {
    echo json_encode(['available' => true]);
}
?>