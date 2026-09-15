<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['car_id'])) {
    echo json_encode(['success' => false, 'message' => 'Car ID required']);
    exit();
}

$car_id = mysqli_real_escape_string($conn, $_GET['car_id']);

// ============================================================
// 1. Booked dates from existing bookings (not cancelled/completed)
// ============================================================
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

// ============================================================
// 2. Blocked dates from car_schedules (maintenance, personal use, etc.)
// ============================================================
$schedSql = "SELECT start_date, end_date 
             FROM car_schedules 
             WHERE car_id = '$car_id' 
             AND end_date >= CURDATE()";

$schedResult = mysqli_query($conn, $schedSql);

if ($schedResult) {
    while ($srow = mysqli_fetch_assoc($schedResult)) {
        $sStart = new DateTime($srow['start_date']);
        $sEnd = new DateTime($srow['end_date']);
        $sInterval = new DateInterval('P1D');
        $sRange = new DatePeriod($sStart, $sInterval, $sEnd->modify('+1 day'));

        foreach ($sRange as $sDate) {
            $bookedDates[] = $sDate->format('Y-m-d');
        }
    }
}

// Remove duplicates and reindex
$bookedDates = array_unique($bookedDates);
$bookedDates = array_values($bookedDates);

echo json_encode([
    'success' => true,
    'booked_dates' => $bookedDates
]);
?>