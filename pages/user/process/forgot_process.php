<?php
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/send_email.php';

// Base URL helper — auto-detects localhost vs production
if (!function_exists('emailBaseUrl')) {
    function emailBaseUrl() {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme  = $isHttps ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base    = (strpos($host, 'localhost') !== false) ? '/car-rental' : '';
        return $scheme . '://' . $host . $base;
    }
}

if (isset($_POST['email'])) {

    $email = trim($_POST['email'] ?? '');

    // Validate email format first
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
        header("Location: ../forgot.php");
        exit();
    }

    // Look up user with a prepared statement
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user   = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($user) {
        // Generate a 64-char hex token
        $token  = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Update user with a prepared statement
        $stmt = mysqli_prepare($conn, "UPDATE users SET reset_token = ?, token_expiry = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ssi', $token, $expiry, $user['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Build the reset link dynamically
        $resetLink = emailBaseUrl() . "/pages/user/reset.php?token=$token";

        // Email body
        $body = "
            <div style='font-family: Arial, sans-serif; background-color: #121212; color: #ffffff; padding: 40px; border-radius: 15px;'>
                <h2 style='color: #ffcc00;'>InstaCar</h2>
                <p>Hello,</p>
                <p>We received a request to reset the password for your account. Click the button below to choose a new one:</p>
                <div style='margin: 30px 0;'>
                    <a href='$resetLink' style='background-color: #ffcc00; color: #000000; padding: 12px 25px; text-decoration: none; font-weight: bold; border-radius: 8px; display: inline-block;'>
                        RESET PASSWORD
                    </a>
                </div>
                <p style='font-size: 12px; color: #888888;'>If you did not request a password reset, please ignore this email. This link will expire in 1 hour.</p>
                <hr style='border: 0; border-top: 1px solid #333; margin: 20px 0;'>
                <p style='font-size: 10px; color: #555555;'>&copy; 2026 InstaCar Rental Service</p>
            </div>
        ";

        sendEmail($email, 'Customer', $body, 'Reset Your InstaCar Password');
    }

    // Always show the same message — prevents attackers from discovering
    // which emails are registered in the system.
    $_SESSION['success'] = "If an account with that email exists, a reset link has been sent. Please check your inbox.";
    header("Location: ../forgot.php");
    exit();
}

// No POST data — go back to the form
header("Location: ../forgot.php");
exit();