<?php
// No session_start() needed here unless you are checking permissions
require_once __DIR__ . '/../../../config/database.php';

$events = [];

// Base Query
$sql = "SELECT b.id, b.start_date, b.end_date, b.status, 
               c.brand, c.model, u.name as customer_name 
        FROM bookings b
        LEFT JOIN cars c ON b.car_id = c.id
        LEFT JOIN users u ON b.user_id = u.id";

// Execute Procedural Query
$result = mysqli_query($conn, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Determine color based on status
        switch ($row['status']) {
            case 'Approved':  $color = '#10b981'; break; // Emerald
            case 'Pending':   $color = '#f59e0b'; break; // Amber
            case 'Completed': $color = '#6366f1'; break; // Indigo
            case 'Cancelled': $color = '#f43f5e'; break; // Rose
            default:          $color = '#94a3b8'; break; 
        }

        // Handle potentially missing data from LEFT JOINs
        $car_name = ($row['brand']) ? $row['brand'] : "Unknown Car";
        $cust_name = ($row['customer_name']) ? $row['customer_name'] : "Guest";

        $events[] = [
            'id'    => $row['id'],
            'title' => $car_name . " • " . $cust_name,
            'start' => $row['start_date'],
            // FullCalendar needs the end date to be +1 day for inclusive ranges
            'end'   => date('Y-m-d', strtotime($row['end_date'] . ' +1 day')),
            'backgroundColor' => $color . '22', // 15% opacity
            'borderColor' => $color,
            'textColor' => $color,
            'allDay' => true,
            'extendedProps' => [
                'status' => $row['status'],
                'car' => $car_name . ' ' . ($row['model'] ?? '')
            ]
        ];
    }
} else {
    // If query fails, return a JSON error instead of a 500 HTML error
    header('Content-Type: application/json', true, 500);
    echo json_encode(['error' => mysqli_error($conn)]);
    exit();
}

// Ensure clean JSON output
header('Content-Type: application/json');
echo json_encode($events);