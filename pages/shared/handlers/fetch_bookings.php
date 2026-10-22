<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

error_reporting(0);
header('Content-Type: application/json');

// Auth check — calendar is only for logged-in users.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$events = [];

$session_user_id = (int)$_SESSION['user_id'];
$session_role    = $_SESSION['role'] ?? '';

// ============================================================
// BRANCH SCOPE (mirrors staff_settlements.php convention)
// ============================================================
if ($session_role === 'staff') {
    $staff_branch_id = (int)($_SESSION['branch_id'] ?? 0);
    $_SESSION['view_branch'] = $staff_branch_id > 0 ? (string)$staff_branch_id : 'all';
}

$view_branch = $_SESSION['view_branch'] ?? 'all';

$branch_filter = 0;
if ($view_branch !== 'all') {
    $branch_filter = (int)$view_branch;
}

$operator_id = 0;
if ($session_role === 'operator') {
    $operator_id = $session_user_id;
}

$range_start = isset($_GET['start']) ? $_GET['start'] : null;
$range_end   = isset($_GET['end'])   ? $_GET['end']   : null;

// ============================================================
// PART 1 — LOAD BOOKINGS (one event per booking)
// ============================================================
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

if ($operator_id > 0) {
    $where_conditions[] = "c.user_id = ?";
    $params[] = $operator_id;
    $types .= 'i';
}

if ($branch_filter > 0) {
    $where_conditions[] = "b.branch_id = ?";
    $params[] = $branch_filter;
    $types .= 'i';
}

if ($range_start && $range_end) {
    $where_conditions[] = "b.start_date < ? AND b.end_date >= ?";
    $params[] = $range_end;
    $params[] = $range_start;
    $types .= 'ss';
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY DATEDIFF(b.end_date, b.start_date) DESC, b.start_date ASC";

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

        if (!$end_timestamp || $end_timestamp <= $start_timestamp) {
            $end_timestamp = strtotime($end_date_only . ' 23:59:59');
        }

        $fc_start_date = date('Y-m-d', $start_timestamp);
        $fc_end_date   = date('Y-m-d', strtotime('+1 day', $end_timestamp));

        $events[] = [
            'id'              => $row['id'],
            'title'           => $car_brand . " " . $car_model . " • " . $cust_name,
            'start'           => $fc_start_date,
            'end'             => $fc_end_date,
            'allDay'          => true,
            'backgroundColor' => $color . '22',
            'borderColor'     => $color,
            'textColor'       => $color,
            'extendedProps'   => [
                'type'        => 'booking',
                'status'      => $row['status'],
                'brand'       => $car_brand,
                'model'       => $car_model,
                'color'       => $car_color,
                'raw_start'   => date('Y-m-d H:i:s', $start_timestamp),
                'raw_end'     => date('Y-m-d H:i:s', $end_timestamp),
                'start_only'  => $start_date_only,
                'end_only'    => $end_date_only,
            ]
        ];
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => mysqli_error($conn)]);
    exit();
}

// ============================================================
// PART 2 — LOAD CAR SCHEDULES
// ============================================================
$schedSql = "SELECT cs.id, cs.car_id, cs.start_date, cs.end_date, cs.reason, cs.notes,
                    c.brand, c.model, c.color AS vehicle_color, c.user_id AS car_owner_id,
                    u.name AS created_by_name
             FROM car_schedules cs
             LEFT JOIN cars c  ON cs.car_id = c.id
             LEFT JOIN users u ON cs.created_by = u.id";

$schedParams = [];
$schedTypes  = '';
$schedWhere  = [];

if ($operator_id > 0) {
    $schedWhere[] = "c.user_id = ?";
    $schedParams[] = $operator_id;
    $schedTypes .= 'i';
}

if ($branch_filter > 0) {
    $schedWhere[] = "c.branch_id = ?";
    $schedParams[] = $branch_filter;
    $schedTypes .= 'i';
}

if ($range_start && $range_end) {
    $schedWhere[] = "cs.start_date < ? AND cs.end_date >= ?";
    $schedParams[] = $range_end;
    $schedParams[] = $range_start;
    $schedTypes .= 'ss';
}

if (!empty($schedWhere)) {
    $schedSql .= " WHERE " . implode(" AND ", $schedWhere);
}

$schedSql .= " ORDER BY cs.start_date ASC";

if ($schedParams) {
    $schedStmt = mysqli_prepare($conn, $schedSql);
    mysqli_stmt_bind_param($schedStmt, $schedTypes, ...$schedParams);
    mysqli_stmt_execute($schedStmt);
    $schedResult = mysqli_stmt_get_result($schedStmt);
} else {
    $schedResult = mysqli_query($conn, $schedSql);
}

if ($schedResult) {
    $reasonMap = [
        'Maintenance'  => ['icon' => '🚧', 'prefix' => 'Maintenance'],
        'Personal Use' => ['icon' => '👤', 'prefix' => 'Personal Use'],
        'Unavailable'  => ['icon' => '🚫', 'prefix' => 'Unavailable'],
        'Other'        => ['icon' => '❓', 'prefix' => 'Blocked'],
    ];

    while ($srow = mysqli_fetch_assoc($schedResult)) {
        $reason = $srow['reason'] ?? 'Unavailable';
        $meta = $reasonMap[$reason] ?? ['icon' => '🔒', 'prefix' => $reason];

        $car_brand = !empty($srow['brand']) ? $srow['brand'] : "Unknown Car";
        $car_model = !empty($srow['model']) ? $srow['model'] : "Vehicle";
        $car_color = !empty($srow['vehicle_color']) ? $srow['vehicle_color'] : "N/A";

        $s_start = date('Y-m-d', strtotime($srow['start_date']));
        $s_end   = date('Y-m-d', strtotime($srow['end_date']));
        $fc_sched_end = date('Y-m-d', strtotime($srow['end_date'] . ' +1 day'));

        $events[] = [
            'id'              => 'schedule-' . $srow['id'],
            'title'           => $meta['icon'] . ' ' . $meta['prefix'] . ' • ' . $car_brand . ' ' . $car_model,
            'start'           => $s_start,
            'end'             => $fc_sched_end,
            'allDay'          => true,
            'backgroundColor' => '#8b5cf622',
            'borderColor'     => '#8b5cf6',
            'textColor'       => '#8b5cf6',
            'extendedProps'   => [
                'type'         => 'schedule',
                'status'       => 'Schedule',
                'reason'       => $reason,
                'notes'        => $srow['notes'] ?? '',
                'brand'        => $car_brand,
                'model'        => $car_model,
                'color'        => $car_color,
                'created_by'   => $srow['created_by_name'] ?? '',
                'raw_start'    => $s_start . ' 00:00:00',
                'raw_end'      => $s_end . ' 23:59:59',
                'start_only'   => $s_start,
                'end_only'     => $s_end,
            ]
        ];
    }
}

echo json_encode($events);