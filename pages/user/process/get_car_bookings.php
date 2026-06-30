<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['car_id'])) {
    echo json_encode(['success' => false, 'message' => 'Car ID required']);
    exit();
}

$car_id = mysqli_real_escape_string($conn, $_GET['car_id']);

// Get all confirmed bookings for this car (not cancelled/completed)
$sql = "SELECT start_date, end_date, pickup_time, return_time 
        FROM bookings 
        WHERE car_id = '$car_id' 
        AND status NOT IN ('Cancelled', 'Completed')
        AND start_date >= CURDATE()";

$result = mysqli_query($conn, $sql);

$bookedDates = [];

while ($row = mysqli_fetch_assoc($result)) {
    $start = new DateTime($row['start_date']);
    $end = new DateTime($row['end_date']);
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));
    
    foreach ($dateRange as $date) {
        $bookedDates[] = $date->format('Y-m-d');
    }
}

// Remove duplicates
$bookedDates = array_unique($bookedDates);
$bookedDates = array_values($bookedDates);

echo json_encode([
    'success' => true,
    'booked_dates' => $bookedDates
]);
?>