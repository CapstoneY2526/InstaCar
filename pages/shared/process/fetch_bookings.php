<?php
require_once __DIR__ . '/../../../config/database.php';

error_reporting(0);
header('Content-Type: application/json');

$events = [];

// Get the user_id parameter for operator filtering
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// FullCalendar automatically appends ?start=YYYY-MM-DD&end=YYYY-MM-DD to this URL
// whenever the visible range changes. Previously this was ignored, so EVERY booking
// in the table (past, future, unrelated months) was fetched and sent to the browser
// on every single view change. Besides being wasteful, a large unfiltered payload
// makes it more likely for a busy day cell to hit dayMaxEvents and push a bar out of
// view. We now filter server-side to just the visible window (with a small buffer so
// a booking that starts before the visible range but returns inside it still shows).
$range_start = isset($_GET['start']) ? $_GET['start'] : null;
$range_end   = isset($_GET['end'])   ? $_GET['end']   : null;

$sql = "SELECT b.*, 
               c.brand, c.model, c.color as vehicle_color, 
               c.user_id as car_owner_id,
               u.name as customer_name 
        FROM bookings b
        LEFT JOIN cars c ON b.car_id = c.id
        LEFT JOIN users u ON b.user_id = u.id";

$params = [];
$types  = '';

$where_conditions = [];

// Operator filter: only show bookings for cars owned by this operator
if ($user_id > 0) {
    $where_conditions[] = "c.user_id = ?";
    $params[] = $user_id;
    $types .= 'i';
}

// Date range filter
if ($range_start && $range_end) {
    // Overlap test: booking must start before the visible window ends
    // AND end after the visible window starts.
    $where_conditions[] = "b.start_date < ? AND b.end_date >= ?";
    $params[] = $range_end;
    $params[] = $range_start;
    $types .= 'ss';
}

// Build the WHERE clause
if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY DATEDIFF(b.end_date, b.start_date) DESC, b.start_date ASC";
// Longest-spanning (multi-day) bookings first so a returning/continuing booking
// claims its day-grid slot before same-day, single-day bookings do — this keeps
// multi-day bars from being bumped out by dayMaxEvents on their final (return) day.

if ($params) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql);
}

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        switch ($row['status']) {
            case 'Approved':  $color = '#10b981'; break;
            case 'Pending':   $color = '#f59e0b'; break;
            case 'Completed': $color = '#6366f1'; break;
            case 'Cancelled': $color = '#f43f5e'; break;
            default:          $color = '#94a3b8'; break; 
        }

        $car_brand = !empty($row['brand']) ? $row['brand'] : "Unknown Car";
        $car_model = !empty($row['model']) ? $row['model'] : "Vehicle";
        $car_color = !empty($row['vehicle_color']) ? $row['vehicle_color'] : "N/A";
        $cust_name = !empty($row['customer_name']) ? $row['customer_name'] : "Guest";

        // Normalize to a plain Y-m-d date first (handles both DATE and DATETIME
        // columns safely) before appending the separate time column, so we never
        // end up concatenating a date string that already has a time portion.
        $start_date_only = date('Y-m-d', strtotime($row['start_date']));
        $end_date_only   = date('Y-m-d', strtotime($row['end_date']));

        $raw_start_str = $start_date_only;
        if (!empty($row['pickup_time'])) {
            $raw_start_str .= ' ' . $row['pickup_time'];
        }

        $raw_end_str = $end_date_only;
        if (!empty($row['return_time'])) {
            $raw_end_str .= ' ' . $row['return_time'];
        }

        $start_timestamp = strtotime($raw_start_str);
        $end_timestamp   = strtotime($raw_end_str);

        // Safety net: if the return timestamp ever comes out at or before the
        // pickup timestamp (bad/legacy data), fall back to end-of-day on the
        // stored end_date rather than silently producing an invalid range that
        // FullCalendar would refuse to render.
        if (!$end_timestamp || $end_timestamp <= $start_timestamp) {
            $end_timestamp = strtotime($end_date_only . ' 23:59:59');
        }

        // Date-only strings (YYYY-MM-DD) for clean visual grid spanning
        $fc_start_date = date('Y-m-d', $start_timestamp);
        // FullCalendar requires end date +1 day for inclusive multi-day visual rendering
        $fc_end_date   = date('Y-m-d', strtotime('+1 day', $end_timestamp));

        $events[] = [
            'id'              => $row['id'],
            'title'           => $car_brand . " " . $car_model . " • " . $cust_name,
            'start'           => $fc_start_date,
            'end'             => $fc_end_date,
            'allDay'          => true, // Forces full continuous bar across day grid
            'backgroundColor' => $color . '22',
            'borderColor'     => $color,
            'textColor'       => $color,
            'extendedProps'   => [
                'status'    => $row['status'],
                'brand'     => $car_brand,
                'model'     => $car_model,
                'color'     => $car_color,
                'raw_start' => date('Y-m-d H:i:s', $start_timestamp),
                'raw_end'   => date('Y-m-d H:i:s', $end_timestamp)
            ]
        ];
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => mysqli_error($conn)]);
    exit();
}

echo json_encode($events);