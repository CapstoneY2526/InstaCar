<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/booking_scope_helper.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['booking_id']) || !isset($_POST['type'])) {
    header("Location: ../../../index.php");
    exit();
}

$booking_id = (int)$_POST['booking_id'];
$type       = $_POST['type'];
$user_id    = (int)$_SESSION['user_id'];
$user_role  = $_SESSION['role'] ?? '';
$branch_id  = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : null;

// --- Verify the current user is allowed to modify this booking ---
if (!userCanAccessBooking($conn, $booking_id, $user_id, $user_role, $branch_id)) {
    header("Location: ../booking_details.php?id=" . $booking_id);
    exit();
}

// --- Proceed with the delete ---
if ($type === 'photo' && isset($_POST['photo_id'], $_POST['file_name'])) {
    $photo_id  = (int)$_POST['photo_id'];
    $file_name = basename($_POST['file_name']);

    // Delete physical file
    $file_path = __DIR__ . '/../../../public/assets/images/customers/' . $file_name;
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    $stmt = $conn->prepare("DELETE FROM booking_photos WHERE id = ? AND booking_id = ?");
    $stmt->bind_param("ii", $photo_id, $booking_id);
    $stmt->execute();
    $stmt->close();

} elseif ($type === 'remark' && isset($_POST['remark_id'])) {
    $remark_id = (int)$_POST['remark_id'];

    $stmt = $conn->prepare("DELETE FROM booking_remarks WHERE id = ? AND booking_id = ?");
    $stmt->bind_param("ii", $remark_id, $booking_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: ../booking_details.php?id=" . $booking_id);
exit();