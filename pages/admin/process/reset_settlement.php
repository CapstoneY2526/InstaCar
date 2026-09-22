<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

// Auth Check — allow admin and staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    die("Unauthorized access");
}

// ── Return path (relative from this file's location: pages/admin/process/) ──
$return_page = ($_SESSION['role'] === 'staff')
    ? '../../staff/staff_settlements.php'
    : '../settlements.php';

// Which booking to reset?
$booking_id = (int)($_GET['booking_id'] ?? 0);

if ($booking_id <= 0) {
    $_SESSION['error'] = "Invalid booking ID.";
    header("Location: " . $return_page);
    exit();
}

// ── Branch security ──
if ($_SESSION['role'] === 'staff') {
    $staff_branch = (int)($_SESSION['branch_id'] ?? 0);

    $check = $conn->prepare("
        SELECT b.id
        FROM bookings b
        INNER JOIN booking_payments p ON p.booking_id = b.id
        WHERE b.id = ? AND b.branch_id = ?
        LIMIT 1
    ");

    if ($check) {
        $check->bind_param('ii', $booking_id, $staff_branch);
        $check->execute();
        $authorized = (bool)$check->get_result()->fetch_assoc();
        $check->close();

        if (!$authorized) {
            $_SESSION['error'] = "You can only reset settlements from your own branch.";
            header("Location: " . $return_page);
            exit();
        }
    } else {
        $_SESSION['error'] = "Authorization check failed.";
        header("Location: " . $return_page);
        exit();
    }
}

// ── Delete the settlement record ──
$delStmt = $conn->prepare("DELETE FROM booking_payments WHERE booking_id = ?");
if ($delStmt) {
    $delStmt->bind_param('i', $booking_id);
    $delStmt->execute();
    $deleted = $delStmt->affected_rows;
    $delStmt->close();

    if ($deleted > 0) {
        $_SESSION['success'] = "✅ Settlement reset. The booking is now back in Pending — please re-enter the fees.";
    } else {
        $_SESSION['error'] = "No settlement found for that booking.";
    }
} else {
    $_SESSION['error'] = "Failed to prepare reset statement.";
}

header("Location: " . $return_page);
exit();
?>