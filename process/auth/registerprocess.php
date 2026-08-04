<?php
session_start();
require_once "../../config/database.php";

// Load PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Update path based on your setup (Composer or manual PHPMailer download)
require_once "../../vendor/autoload.php"; 

if (isset($_POST['submit'])) {
    // 1. Clean Inputs
    $name = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 2. Validation
    if (empty($name) || empty($email) || empty($password)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: ../../register.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: ../../register.php");
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: ../../register.php");
        exit();
    }

    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters.";
        header("Location: ../../register.php");
        exit();
    }

    try {
        // 3. Check if email already exists
        $check_sql = "SELECT id FROM users WHERE email = '$email' LIMIT 1";
        $check_result = mysqli_query($conn, $check_sql);

        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error'] = "Email is already registered.";
            header("Location: ../../register.php");
            exit();
        }

        // 4. Hash Password & Generate Verification Token / OTP
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = "user"; // Default role
        
        // Generate secure token for URL verification + 6-digit OTP code
        $verification_token = bin2hex(random_bytes(32)); 
        $is_verified = 0; // Account disabled until email is verified

        // 5. Insert User into Database
        $insert_sql = "INSERT INTO users (name, email, password, role, verification_token, is_verified) 
                       VALUES ('$name', '$email', '$hashed_password', '$role', '$verification_token', '$is_verified')";

        if (mysqli_query($conn, $insert_sql)) {
            
            // 6. Send Verification Email via PHPMailer
            $mail = new PHPMailer(true);

            try {
                // --- SMTP Configuration ---
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';             // Replace with your SMTP server
                $mail->SMTPAuth   = true;
                $mail->Username   = 'ramesesjay@gmail.com';       // Your SMTP email address
                $mail->Password   = 'oaim ekfw evgn xknw';          // Your App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // --- Email Content ---
                $mail->setFrom('ramesesjay@gmail.com', 'InstaCar');
                $mail->addAddress($email, $name);

                // In registerprocess.php:
                $verify_url = "http://localhost/CAR-RENTAL/process/auth/registerverify.php?token=" . $verification_token;

                $mail->isHTML(true);
                $mail->Subject = 'Verify Your InstaCar Account';
                $mail->Body    = "
                                <div style='max-width:600px; margin:20px auto; font-family: \"Poppins\", sans-serif, Arial; background-color: #121212; border-radius:20px; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #333;'>
                                    
                                    <!-- Header Banner -->
                                    <div style='background: #ffcc00; padding:40px; text-align:center;'>
                                        <h1 style='margin:0; font-size:28px; color: #000000; text-transform: uppercase; letter-spacing: 2px; font-weight: 800;'>
                                            Verify Your Email
                                        </h1>
                                    </div>

                                    <!-- Content Body -->
                                    <div style='padding:40px; color:#ffffff; line-height:1.6;'>
                                        <p style='font-size: 18px;'>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                                        
                                        <p style='color: #bbb;'>Thanks for signing up with <strong>Insta<span style='color:#ffcc00;'>Car</span></strong>! Please confirm that <span style='color: #ffcc00; font-weight: bold;'>" . htmlspecialchars($email) . "</span> belongs to you by clicking the button below.</p>

                                        <!-- CTA Button -->
                                        <div style='text-align:center; margin:40px 0;'>
                                            <a href='" . $verify_url . "' target='_blank' style='display:inline-block; background:#ffcc00; color:#000000; padding:15px 35px; text-decoration:none; border-radius:10px; font-weight:800; text-transform: uppercase; letter-spacing: 1px;'>
                                                Confirm Email Address
                                            </a>
                                        </div>

                                        <!-- Fallback Link -->
                                        <p style='color: #666666; font-size: 12px; line-height: 1.5; margin: 0 0 20px 0; border-top: 1px solid #333; padding-top: 20px; text-align: center;'>
                                            If the button above doesn't work, copy and paste this link into your browser:<br>
                                            <a href='" . $verify_url . "' style='color: #ffcc00; text-decoration: underline; word-break: break-all;'>" . $verify_url . "</a>
                                        </p>

                                        <hr style='border:0; border-top:1px solid #333; margin:30px 0;'>
                                        
                                        <!-- Footer -->
                                        <div style='text-align:center;'>
                                            <p style='font-size:12px; color:#666; margin-bottom: 5px;'>If you didn't create an account, you can safely ignore this email.</p>
                                            <p style='font-size:10px; color:#444; text-transform: uppercase;'>&copy; " . date('Y') . " InstaCar Rental Service</p>
                                        </div>
                                    </div>
                                </div>";

                $mail->send();

                $_SESSION['success'] = "Registration successful! Please check your email inbox to verify your account.";
                header("Location: ../../login.php");
                exit();

            } catch (Exception $mail_error) {
                // Account created, but email dispatch failed
                $_SESSION['error'] = "Account created, but verification email failed to send. Error: {$mail->ErrorInfo}";
                header("Location: ../../register.php");
                exit();
            }

        } else {
            throw new Exception("Database insertion failed.");
        }

    } catch (Exception $e) {
        $_SESSION['error'] = "Registration error occurred.";
        header("Location: ../../register.php");
        exit();
    }
}
?>