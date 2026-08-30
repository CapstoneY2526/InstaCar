<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Include database configuration
require_once __DIR__ . '/config/database.php';

// 2. Locate and include PHPMailer / vendor autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/includes/vendor/autoload.php')) {
    require_once __DIR__ . '/includes/vendor/autoload.php';
}

// Helper function to render SweetAlert2 modal and redirect
function showSweetAlert($title, $text, $icon, $redirectUrl) {
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        <style>
            body { background-color: #0a0a0a; font-family: sans-serif; }
            .swal2-popup { background: #1a1a1a !important; color: #ffffff !important; border: 1px solid #2c2c2c !important; }
            .swal2-title { color: #ffffff !important; }
            .swal2-html-container { color: #aaaaaa !important; }
            .swal2-confirm { background-color: #ffcc00 !important; color: #000000 !important; font-weight: bold !important; border-radius: 30px !important; }
        </style>
    </head>
    <body>
        <script>
            Swal.fire({
                title: '{$title}',
                html: '{$text}',
                icon: '{$icon}',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = '{$redirectUrl}';
            });
        </script>
    </body>
    </html>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawName    = trim($_POST['name'] ?? '');
    $rawEmail   = trim($_POST['email'] ?? '');
    $rawSubject = trim($_POST['subject'] ?? '');
    $rawCustom  = trim($_POST['custom_subject'] ?? '');
    $rawMessage = trim($_POST['message'] ?? '');

    // Handle Custom Subject option
    if ($rawSubject === 'Other / Custom Subject' && !empty($rawCustom)) {
        $finalSubject = $rawCustom;
    } else {
        $finalSubject = $rawSubject;
    }

    if (!empty($rawName) && !empty($rawEmail) && !empty($finalSubject) && !empty($rawMessage)) {
        
        // 1. Save message directly into database using prepared statements
        $stmt = mysqli_prepare($conn, "INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $rawName, $rawEmail, $finalSubject, $rawMessage);
        $inserted = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // 2. Build HTML email body displaying user details clearly
        $emailBody = "
            <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #121212; color: #ffffff;'>
                <div style='max-width: 600px; margin: 0 auto; background: #1a1a1a; padding: 25px; border-radius: 12px; border: 1px solid #2c2c2c; border-top: 4px solid #ffcc00;'>
                    <h2 style='color: #ffcc00; margin-top: 0;'>New Website Inquiry</h2>
                    <hr style='border: 0; border-top: 1px solid #2c2c2c; margin: 15px 0;'>
                    <p><strong>Sender Name:</strong> " . htmlspecialchars($rawName) . "</p>
                    <p><strong>Sender Email:</strong> <a href='mailto:" . htmlspecialchars($rawEmail) . "' style='color: #ffcc00;'>" . htmlspecialchars($rawEmail) . "</a></p>
                    <p><strong>Subject:</strong> " . htmlspecialchars($finalSubject) . "</p>
                    <p><strong>Message:</strong></p>
                    <div style='background: #0a0a0a; padding: 15px; border-left: 3px solid #ffcc00; border-radius: 6px; margin-top: 10px; color: #dddddd;'>
                        " . nl2br(htmlspecialchars($rawMessage)) . "
                    </div>
                </div>
            </div>
        ";

        // Plain text alternative
        $plainTextBody = "New Website Inquiry\n\n" .
                         "Sender Name: " . $rawName . "\n" .
                         "Sender Email: " . $rawEmail . "\n" .
                         "Subject: " . $finalSubject . "\n\n" .
                         "Message:\n" . $rawMessage;

        // 3. Dispatch PHPMailer
        $mailError = null;
        try {
            $mail = new PHPMailer(true);

            $mail->SMTPDebug = 0;

            // SMTP Authentication credentials
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'ramesesjay@gmail.com';
            $mail->Password   = 'ldyg fiyu fwkc mjnv';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Bypass SSL restriction for local development environment
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                )
            );

            $mail->clearAddresses();
            $mail->clearReplyTos();

            // Sender is the System Courier (Shows customer's name in quotes)
            $mail->setFrom('ramesesjay@gmail.com', $rawName . ' (InstaCar Web Form)');

            // Destination: Admin Inbox
            $mail->addAddress('ramesesjay1@gmail.com', 'InstaCar Admin');

            // REPLY-TO HEADER: Directs replies to the visitor's submitted email
            $mail->addReplyTo($rawEmail, $rawName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "New Website Inquiry: " . $finalSubject;
            $mail->Body    = $emailBody;
            $mail->AltBody = $plainTextBody;

            $mail->send();
        } catch (Exception $e) {
            $mailError = $mail->ErrorInfo;
        }

        // Display results
        if ($inserted && !$mailError) {
            showSweetAlert(
                'Thank You!',
                'Your message has been saved and sent successfully.',
                'success',
                'index.php#contact'
            );
        } elseif ($inserted && $mailError) {
            showSweetAlert(
                'Saved with Warning',
                'Message saved to database, but email delivery failed:<br><br><code>' . htmlspecialchars($mailError) . '</code>',
                'warning',
                'index.php#contact'
            );
        }
    }
}

// Fallback error alert
showSweetAlert(
    'Error!',
    'Please fill out all required fields.',
    'error',
    'index.php#contact'
);