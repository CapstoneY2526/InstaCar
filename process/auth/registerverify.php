<?php
session_start();

require_once __DIR__ . '/../../config/database.php';

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);

    // Fetch user details alongside verification status
    $stmt = $conn->prepare("SELECT id, name, email, role, is_verified FROM users WHERE verification_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if ($user['is_verified'] == 0) {
            // Update user status to verified and clear the token
            $update = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
            $update->bind_param("i", $user['id']);
            $update->execute();
        }

        // Set session variables to log the user in directly
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['success'] = "Email verified successfully! Welcome to your dashboard.";

        // Determine correct dashboard path based on role
        if ($user['role'] === "admin") {
            $location = "../../pages/admin/dashboard.php";
        } elseif ($user['role'] === "operator") {
            $location = "../../pages/operator/dashboard.php";
        } else {
            $location = "../../pages/user/dashboard.php";
        }

        header("Location: " . $location);
        exit();

    } else {
        $_SESSION['error'] = "Invalid or expired verification link.";
        header("Location: ../../login.php");
        exit();
    }
} else {
    $_SESSION['error'] = "No verification token provided.";
    header("Location: ../../login.php");
    exit();
}
?>