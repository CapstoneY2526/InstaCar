<?php
session_start();

// Go up two levels to reach CAR-RENTAL/config/database.php
require_once __DIR__ . '/../../config/database.php';

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);

    // Check for user with matching verification token
    $stmt = $conn->prepare("SELECT id, is_verified FROM users WHERE verification_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($user['is_verified'] == 1) {
            $_SESSION['info'] = "Your account is already verified! Please sign in.";
        } else {
            // Mark account as verified and clear token
            $update = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
            $update->bind_param("i", $user['id']);
            $update->execute();

            $_SESSION['success'] = "Email verified successfully! You can now log in.";
        }
    } else {
        $_SESSION['error'] = "Invalid or expired verification link.";
    }
} else {
    $_SESSION['error'] = "No verification token provided.";
}

// Redirect back to login page in project root
header("Location: ../../login.php");
exit();