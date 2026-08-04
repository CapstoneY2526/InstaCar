<?php
session_start();
require_once '../../../config/database.php'; 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'User session expired or not logged in.']);
        exit;
    }

    $user_id    = (int)$_SESSION['user_id'];
    $booking_id = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $rating     = isset($_POST['rating']) ? (int)$_POST['rating'] : 5;
    $title      = trim($_POST['review_title'] ?? '');
    $text       = trim($_POST['review_text'] ?? '');

    if (!$booking_id || empty($title) || empty($text)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // 1. Check if review exists
    $check_sql = "SELECT id FROM reviews WHERE booking_id = ? AND user_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // 2. UPDATE existing review
        // Param types: rating (i), title (s), text (s), booking_id (i), user_id (i) -> "issii"
        $update_sql = "UPDATE reviews SET rating = ?, review_title = ?, review_text = ?, status = 'Pending', updated_at = NOW() WHERE booking_id = ? AND user_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("issii", $rating, $title, $text, $booking_id, $user_id);
    } else {
        // 3. INSERT new review
        // Param types: user_id (i), booking_id (i), rating (i), title (s), text (s) -> "iiiss"
        $insert_sql = "INSERT INTO reviews (user_id, booking_id, rating, review_title, review_text, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iiiss", $user_id, $booking_id, $rating, $title, $text);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
}