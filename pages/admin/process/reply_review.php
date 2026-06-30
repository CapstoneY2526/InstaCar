<?php
session_start();
// Inside admin/process/reply_review.php
require_once '../../../config/database.php';

header('Content-Type: application/json');
// Disable error display so they don't break the JSON format
error_reporting(0);
ini_set('display_errors', 0);

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Unauthorized access');
    }

    $review_id = intval($_POST['review_id'] ?? 0);
    $reply = trim($_POST['admin_reply'] ?? '');

    if ($review_id <= 0) {
        throw new Exception('Invalid review ID');
    }

    if ($reply === '') {
        throw new Exception('Reply cannot be empty');
    }

    $stmt = $conn->prepare("UPDATE reviews SET admin_reply = ?, status = 'Replied', updated_at = NOW() WHERE id = ?");

    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("si", $reply, $review_id);
    $stmt->execute();

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    http_response_code(400); // Set a proper error code
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
exit; // Ensure nothing else is printed