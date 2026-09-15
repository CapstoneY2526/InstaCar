<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/booking_scope_helper.php';

// --- Guard: POST + logged in ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header("Location: ../../../index.php");
    exit();
}

$user_id    = (int)$_SESSION['user_id'];
$user_role  = $_SESSION['role'] ?? '';
$branch_id  = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : null;
$booking_id = (int)($_POST['booking_id'] ?? 0);
$new_status = trim($_POST['status'] ?? '');

// --- Whitelist status ---
$valid_statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
if (!in_array($new_status, $valid_statuses, true)) {
    $_SESSION['error'] = "Invalid status value.";
    header("Location: ../booking_details.php?id=" . $booking_id);
    exit();
}

if ($booking_id <= 0) {
    $_SESSION['error'] = "Invalid booking.";
    header("Location: ../booking_details.php");
    exit();
}

// --- Role must be admin / staff / operator ---
if (!in_array($user_role, ['admin', 'staff', 'operator'], true)) {
    header("Location: ../../../index.php");
    exit();
}

// --- Scope: verify the user can modify this booking ---
if (!userCanAccessBooking($conn, $booking_id, $user_id, $user_role, $branch_id)) {
    $_SESSION['error'] = "Booking not found or access denied.";
    header("Location: ../booking_details.php?id=" . $booking_id);
    exit();
}

// --- Fetch car_id for the status sync step ---
$car_stmt = $conn->prepare("SELECT car_id FROM bookings WHERE id = ?");
$car_stmt->bind_param("i", $booking_id);
$car_stmt->execute();
$car_row = $car_stmt->get_result()->fetch_assoc();
$car_stmt->close();

if (!$car_row) {
    $_SESSION['error'] = "Booking not found.";
    header("Location: ../booking_details.php");
    exit();
}

$car_id = (int)$car_row['car_id'];

// --- Update booking + audit trail ---
$stmt = $conn->prepare("
    UPDATE bookings
    SET status = ?,
        last_action_by = ?,
        last_action_at = NOW(),
        last_action_type = ?
    WHERE id = ?
");
$stmt->bind_param("sisi", $new_status, $user_id, $new_status, $booking_id);
$stmt->execute();
$stmt->close();

// --- Sync car status ---
// Completed / Cancelled → car is Available again
// Confirmed / Pending → car is held for this booking
if (in_array($new_status, ['Completed', 'Cancelled'], true)) {
    $upd = $conn->prepare("UPDATE cars SET status = 'Available' WHERE id = ?");
    $upd->bind_param("i", $car_id);
    $upd->execute();
    $upd->close();
} elseif (in_array($new_status, ['Confirmed'], true)) {
    $upd = $conn->prepare("UPDATE cars SET status = 'Active' WHERE id = ?");
    $upd->bind_param("i", $car_id);
    $upd->execute();
    $upd->close();
}

$_SESSION['success'] = "Booking status updated to " . htmlspecialchars($new_status) . ".";
header("Location: ../booking_details.php?id=" . $booking_id);
exit();