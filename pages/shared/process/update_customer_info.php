<?php
session_start();
// Go up 3 levels: process -> shared -> pages -> root
require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['booking_id'])) {
    header("Location: ../../index.php");
    exit();
}

$booking_id = (int)$_POST['booking_id'];
$user_id = (int)$_SESSION['user_id'];
$new_remark = trim($_POST['new_remark'] ?? '');

// 1. Insert New Remark into booking_remarks table
if (!empty($new_remark)) {
    $stmt = $conn->prepare("INSERT INTO booking_remarks (booking_id, created_by, remark) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $booking_id, $user_id, $new_remark);
    $stmt->execute();
    $stmt->close();
}

// 2. Upload Multiple Customer Photos to public/assets/images/customers/
if (isset($_FILES['customer_photos']) && !empty($_FILES['customer_photos']['name'][0])) {
    // Go up 3 levels to reach public/
    $target_dir = __DIR__ . '/../../../public/assets/images/customers/';
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_count = count($_FILES['customer_photos']['name']);
    
    for ($i = 0; $i < $file_count; $i++) {
        $file_name = $_FILES['customer_photos']['name'][$i];
        $file_tmp  = $_FILES['customer_photos']['tmp_name'][$i];
        $file_error = $_FILES['customer_photos']['error'][$i];

        if ($file_error === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

            if (in_array($ext, $allowed)) {
                $new_file_name = "customer_" . $booking_id . "_" . time() . "_" . uniqid() . "." . $ext;
                $target_file = $target_dir . $new_file_name;

                if (move_uploaded_file($file_tmp, $target_file)) {
                    $stmt = $conn->prepare("INSERT INTO booking_photos (booking_id, file_name) VALUES (?, ?)");
                    $stmt->bind_param("is", $booking_id, $new_file_name);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
}

header("Location: ../booking_details.php?id=" . $booking_id);
exit();