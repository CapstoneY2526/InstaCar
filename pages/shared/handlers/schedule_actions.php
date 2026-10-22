<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

// --- AUTH CHECK ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'operator'])) {
    $_SESSION['error'] = "Access denied.";
    header("Location: ../../index.php");
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];

// --- ONLY POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request.";
    header("Location: ../my_car_schedules.php");
    exit();
}

// --- ACTION ROUTER ---
$action = $_POST['action'] ?? '';

// ============================================================
// ACTION: SAVE (create a new schedule block)
// ============================================================
if ($action === 'save_schedule') {

    $car_id     = (int)($_POST['car_id'] ?? 0);
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date   = trim($_POST['end_date'] ?? '');
    $reason     = trim($_POST['reason'] ?? 'Unavailable');
    $notes      = trim($_POST['notes'] ?? '');

    // Validate required fields
    if ($car_id <= 0 || $start_date === '' || $end_date === '') {
        $_SESSION['error'] = "Please fill in all required fields.";
        header("Location: ../my_car_schedules.php");
        exit();
    }

    // Validate reason against whitelist
    $allowed_reasons = ['Maintenance', 'Personal Use', 'Unavailable', 'Other'];
    if (!in_array($reason, $allowed_reasons, true)) {
        $reason = 'Unavailable';
    }

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $_SESSION['error'] = "Invalid date format.";
        header("Location: ../my_car_schedules.php");
        exit();
    }

    // End date must be on or after start
    if ($end_date < $start_date) {
        $_SESSION['error'] = "End date cannot be before the start date.";
        header("Location: ../my_car_schedules.php");
        exit();
    }

    // Cannot schedule in the past
    if ($start_date < date('Y-m-d')) {
        $_SESSION['error'] = "Start date cannot be in the past.";
        header("Location: ../my_car_schedules.php");
        exit();
    }

    // Authorization: does this user own this car?
    if ($user_role === 'admin') {
        $carCheck = mysqli_query($conn, "SELECT id FROM cars WHERE id = $car_id LIMIT 1");
    } else {
        $carCheckStmt = mysqli_prepare($conn, "SELECT id FROM cars WHERE id = ? AND user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($carCheckStmt, 'ii', $car_id, $current_user_id);
        mysqli_stmt_execute($carCheckStmt);
        $carCheck = mysqli_stmt_get_result($carCheckStmt);
    }

    if (!$carCheck || mysqli_num_rows($carCheck) === 0) {
        $_SESSION['error'] = "Car not found or you don't have permission to schedule it.";
        header("Location: ../my_car_schedules.php");
        exit();
    }

    // Conflict: any existing booking overlapping this range?
    $bookCheckStmt = mysqli_prepare($conn,
        "SELECT id FROM bookings
         WHERE car_id = ?
           AND status IN ('Pending', 'Approved', 'Active')
           AND DATE(start_date) <= ?
           AND DATE(end_date)   >= ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($bookCheckStmt, 'iss', $car_id, $end_date, $start_date);
    mysqli_stmt_execute($bookCheckStmt);
    $bookCheck = mysqli_stmt_get_result($bookCheckStmt);

    if ($bookCheck && mysqli_num_rows($bookCheck) > 0) {
        $_SESSION['error'] = "There's already a booking on those dates. Cancel or move it first.";
        header("Location: ../my_car_schedules.php");
        exit();
    }

    // Conflict: any existing schedule overlapping this range?
    $schedCheckStmt = mysqli_prepare($conn,
        "SELECT id FROM car_schedules
         WHERE car_id = ?
           AND start_date <= ?
           AND end_date   >= ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($schedCheckStmt, 'iss', $car_id, $end_date, $start_date);
    mysqli_stmt_execute($schedCheckStmt);
    $schedCheck = mysqli_stmt_get_result($schedCheckStmt);

    if ($schedCheck && mysqli_num_rows($schedCheck) > 0) {
        $_SESSION['error'] = "This car already has a schedule block overlapping those dates.";
        header("Location: ../my_car_schedules.php");
        exit();
    }

    // Insert
    $insertStmt = mysqli_prepare($conn,
        "INSERT INTO car_schedules (car_id, created_by, start_date, end_date, reason, notes)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($insertStmt, 'iissss',
        $car_id,
        $current_user_id,
        $start_date,
        $end_date,
        $reason,
        $notes
    );

    if (mysqli_stmt_execute($insertStmt)) {
        $_SESSION['success'] = "Schedule block added successfully.";
    } else {
        $_SESSION['error'] = "Failed to save schedule block: " . mysqli_error($conn);
    }

    header("Location: ../my_car_schedules.php");
    exit();
}

// ============================================================
// ACTION: DELETE (remove an existing schedule block)
// ============================================================
if ($action === 'delete_schedule') {

    $schedule_id = (int)($_POST['schedule_id'] ?? 0);

    if ($schedule_id <= 0) {
        $_SESSION['error'] = "Invalid schedule ID.";
        header("Location: ../my_car_schedules.php");
        exit();
    }

    // Admin can delete any block. Operator can only delete blocks on their own cars.
    if ($user_role === 'admin') {
        $stmt = mysqli_prepare($conn, "DELETE FROM car_schedules WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $schedule_id);
    } else {
        $stmt = mysqli_prepare($conn,
            "DELETE cs FROM car_schedules cs
             INNER JOIN cars c ON cs.car_id = c.id
             WHERE cs.id = ? AND c.user_id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $schedule_id, $current_user_id);
    }

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $_SESSION['success'] = "Schedule block removed.";
        } else {
            $_SESSION['error'] = "Schedule block not found or access denied.";
        }
    } else {
        $_SESSION['error'] = "Failed to delete block: " . mysqli_error($conn);
    }

    header("Location: ../my_car_schedules.php");
    exit();
}

// --- UNKNOWN ACTION ---
$_SESSION['error'] = "Unknown action.";
header("Location: ../my_car_schedules.php");
exit();