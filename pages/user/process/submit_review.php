<?php
session_start();
require_once '../../../config/database.php'; 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $booking_id = $_POST['booking_id'];
    $rating = $_POST['rating'];
    $title = $_POST['review_title'];
    $text = $_POST['review_text'];

    // 1. Check if review exists
    $check_sql = "SELECT id FROM reviews WHERE booking_id = ? AND user_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // 2. It exists, so UPDATE
        $update_sql = "UPDATE reviews SET rating = ?, review_title = ?, review_text = ?, status = 'Pending', updated_at = NOW() WHERE booking_id = ? AND user_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("issii", $rating, $title, $text, $booking_id, $user_id);
    } else {
        // 3. It's new, so INSERT
        $insert_sql = "INSERT INTO reviews (user_id, booking_id, rating, review_title, review_text, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iiiss", $user_id, $booking_id, $rating, $title, $text);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
}