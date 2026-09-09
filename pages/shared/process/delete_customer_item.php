<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['booking_id']) || !isset($_POST['type'])) {
    header("Location: ../../../index.php");
    exit();
}

$booking_id = (int)$_POST['booking_id'];
$type = $_POST['type'];

if ($type === 'photo' && isset($_POST['photo_id'], $_POST['file_name'])) {
    $photo_id = (int)$_POST['photo_id'];
    $file_name = basename($_POST['file_name']);

    // Delete physical file
    $file_path = __DIR__ . '/../../../public/assets/images/customers/' . $file_name;
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // Delete photo record
    $stmt = $conn->prepare("DELETE FROM booking_photos WHERE id = ? AND booking_id = ?");
    $stmt->bind_param("ii", $photo_id, $booking_id);
    $stmt->execute();
    $stmt->close();

} elseif ($type === 'remark' && isset($_POST['remark_id'])) {
    $remark_id = (int)$_POST['remark_id'];

    // Delete remark record
    $stmt = $conn->prepare("DELETE FROM booking_remarks WHERE id = ? AND booking_id = ?");
    $stmt->bind_param("ii", $remark_id, $booking_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: ../booking_details.php?id=" . $booking_id);
exit();