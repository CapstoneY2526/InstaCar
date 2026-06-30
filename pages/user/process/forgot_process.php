<?php
session_start();
date_default_timezone_set('Asia/Manila');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Corrected paths based on your image_cc5342.png
require_once __DIR__ . '/../../../config/database.php';
require __DIR__ . '/../../../vendor/autoload.php';

if(isset($_POST['email'])) {

    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Check if user exists
    $result = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
    
    if(mysqli_num_rows($result) > 0) {

        $token = bin2hex(random_bytes(50));
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Make sure your database table has these columns: reset_token and token_expiry
        mysqli_query($conn, "
            UPDATE users 
            SET reset_token='$token', token_expiry='$expiry' 
            WHERE email='$email'
        ");

        // 2. Corrected Reset Link Path
        // This points to reset.php located in pages/user/
        $resetLink = "http://localhost/CAR-RENTAL/pages/user/reset.php?token=$token";

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'ramesesjay@gmail.com'; // Your Gmail
            $mail->Password   = 'lydx czlf zuxx ghab';    // Your 16-digit Google App Password
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('ramesesjay@gmail.com', 'InstaCar Support');
            $mail->addAddress($email);

            // --- EMAIL CONTENT ---
            $mail->isHTML(true);
            $mail->Subject = 'Reset Your InstaCar Password';

            // We use inline CSS because most email apps (like Gmail/Outlook) block external stylesheets
            $mail->Body = "
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

            $mail->send();

            $_SESSION['success'] = "Success! Please check your email for the reset link.";
            header("Location: ../forgot.php");
            exit();

        } catch (Exception $e) {
            $_SESSION['error'] = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            header("Location: ../forgot.php");
            exit();
        }

    } else {
        $_SESSION['error'] = "We couldn't find an account with that email address.";
        header("Location: ../forgot.php");
        exit();
    }
}