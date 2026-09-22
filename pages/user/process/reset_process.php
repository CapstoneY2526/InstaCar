<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

// Force the timezone to match local time (Philippines/PHT)
date_default_timezone_set('Asia/Manila');
$current_time = date("Y-m-d H:i:s");

if (isset($_POST['token'], $_POST['password'])) {

    $token = $_POST['token'] ?? '';

    // Validate token format (64 hex chars) before touching the DB
    if (!is_string($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
        $_SESSION['error'] = "Invalid reset link. Please request a new one.";
        header("Location: ../forgot.php");
        exit();
    }

    // Basic password length check
    if (strlen($_POST['password']) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters.";
        header("Location: ../reset.php?token=" . urlencode($token));
        exit();
    }

    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // 1. Verify the token is valid and not expired
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE reset_token = ? AND token_expiry > ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ss', $token, $current_time);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$user) {
        $_SESSION['error'] = "Link expired. Please request a new one.";
        header("Location: ../forgot.php");
        exit();
    }

    // 2. Update password — including reset_token in WHERE guards against double-use
    $stmt = mysqli_prepare($conn, "UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE id = ? AND reset_token = ?");
    mysqli_stmt_bind_param($stmt, 'sis', $password, $user['id'], $token);

    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        $_SESSION['success'] = "Password updated! You can now log in.";
        header("Location: ../../../login.php");
        exit();
    } else {
        mysqli_stmt_close($stmt);
        $_SESSION['error'] = "This reset link has already been used or expired. Please request a new one.";
        header("Location: ../forgot.php");
        exit();
    }
}

// No POST data — go back to login
header("Location: ../../../login.php");
exit();
?>