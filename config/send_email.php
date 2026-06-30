<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Use __DIR__ to ensure the path is always relative to this file's location
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Sends an email using PHPMailer
 * @param string $toEmail      Recipient email address
 * @param string $customerName Recipient name
 * @param string $body         HTML content of the email
 * @param string $subject      Email subject line
 * @return bool                True on success, false on failure
 */
function sendEmail($toEmail, $customerName, $body, $subject = 'Booking Update') {

    $mail = new PHPMailer(true);

    try {
        // --- Server Settings ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ramesesjay@gmail.com'; // Your Gmail address
        $mail->Password   = 'lydx czlf zuxx ghab';    // Your Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = 587;

        // --- Recipients ---
        $mail->setFrom('ramesesjay@gmail.com', 'InstaCar.');
        $mail->addAddress($toEmail, $customerName);

        // --- Content ---
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        // Plain text version for non-HTML mail clients
        $mail->AltBody = strip_tags(str_replace(['<br>', '<p>', '</h2>', '</h3>'], ["\n", "\n", "\n\n", "\n\n"], $body));

        $mail->send();
        return true;

    } catch (Exception $e) {
        // You can log the error if needed: error_log("Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}