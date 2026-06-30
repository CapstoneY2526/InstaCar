<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

// 1. Force the timezone to match your local time (Phillippines/PHT)
date_default_timezone_set('Asia/Manila');
$current_time = date("Y-m-d H:i:s");

if(isset($_POST['token'], $_POST['password'])) {

    $token = mysqli_real_escape_string($conn, $_POST['token']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // 2. We use the PHP $current_time variable instead of SQL NOW() 
    // to ensure the comparison is accurate to your timezone.
    $query = "SELECT * FROM users WHERE reset_token='$token' AND token_expiry > '$current_time'";
    $result = mysqli_query($conn, $query);

    if(mysqli_num_rows($result) > 0){

        $update = mysqli_query($conn, "
            UPDATE users 
            SET password='$password', 
                reset_token=NULL, 
                token_expiry=NULL 
            WHERE reset_token='$token'
        ");

        if($update) {
            $_SESSION['success'] = "Password updated! You can now log in.";
            header("Location: ../../../login.php"); // Back to root login
            exit();
        } else {
            $_SESSION['error'] = "Database error. Please try again.";
            header("Location: ../reset.php?token=$token");
            exit();
        }

    } else {
        // This handles the "TwT" error by sending you back with a message
        $_SESSION['error'] = "Link expired. Please request a new one.";
        header("Location: ../forgot.php");
        exit();
    }
} else {
    header("Location: ../../../login.php");
    exit();
}
?>