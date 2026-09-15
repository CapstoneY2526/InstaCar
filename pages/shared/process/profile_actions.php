<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/content_helper.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$user_id = (int)$_SESSION['user_id'];

// --- UPDATE PERSONAL INFO ---
if (isset($_POST['update_info'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));

    // Short style: executing query directly in the variable
    $update = mysqli_query($conn, "UPDATE users SET name = '$name', email = '$email' WHERE id = $user_id");

    if ($update) {
        $_SESSION['name'] = $name; // Update session name for header display
        $_SESSION['success'] = "Profile information updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update profile.";
    }
    ?>
    <script>window.location.href = "<?php echo $_SERVER['HTTP_REFERER']; ?>";</script>
    <?php
    exit();
}

// --- UPDATE PASSWORD ---
if (isset($_POST['update_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // 1. Verify New Passwords Match
    if ($new_pass !== $confirm_pass) {
        $_SESSION['error'] = "New passwords do not match.";
        ?>
        <script>window.location.href = "<?php echo $_SERVER['HTTP_REFERER']; ?>";</script>
        <?php
        exit();
    }

    // 2. Verify Current Password (Short style fetch)
    $user_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM users WHERE id = $user_id"));

    if ($user_data && password_verify($current_pass, $user_data['password'])) {
        // 3. Hash and Save New Password
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
        
        // Short style: executing update directly
        $update_pw = mysqli_query($conn, "UPDATE users SET password = '$hashed_pass' WHERE id = $user_id");
        
        if ($update_pw) {
            $_SESSION['success'] = "Password changed successfully!";
        } else {
            $_SESSION['error'] = "Failed to update password.";
        }
    } else {
        $_SESSION['error'] = "Current password is incorrect.";
    }

    ?>
    <script>window.location.href = "<?php echo $_SERVER['HTTP_REFERER']; ?>";</script>
    <?php
    exit();
}

// --- UPLOAD PAYMENT QR CODE ACTION ---
if (isset($_POST['upload_qr_code'])) {
    if (!isset($_FILES['qr_code_image']) || $_FILES['qr_code_image']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "File upload failed. Please try again.";
        ?>
        <script>window.location.href = "<?php echo $_SERVER['HTTP_REFERER']; ?>";</script>
        <?php
        exit();
    }

    $fileTmpPath = $_FILES['qr_code_image']['tmp_name'];
    
    // Get actual mime type from the file content for better security
    $fileInfo = @getimagesize($fileTmpPath);
    $mimeType = $fileInfo ? $fileInfo['mime'] : '';

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $allowedTypes)) {
        $_SESSION['error'] = "Invalid image type! Please upload a PNG, JPG, or WEBP image.";
        ?>
        <script>window.location.href = "<?php echo $_SERVER['HTTP_REFERER']; ?>";</script>
        <?php
        exit();
    }

    $targetDir = __DIR__ . "/../../../public/assets/images/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Target destination file
    $targetFilePath = $targetDir . "qr_code_payment.png";

    // Standard move and overwrite
    if (move_uploaded_file($fileTmpPath, $targetFilePath)) {
        // Touch file to guarantee the modification time changes for cache-busting
        touch($targetFilePath);
        $_SESSION['success'] = "Payment QR code updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to save QR code image file to server.";
    }

    ?>
    <script>window.location.href = "<?php echo $_SERVER['HTTP_REFERER']; ?>";</script>
    <?php
    exit();
}

// --- SAVE RENTAL AGREEMENT CONTENT (ADMIN / OPERATOR ONLY) ---
if (isset($_POST['save_rental_agreement'])) {

    // Re-check role server-side (never trust the form)
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'operator'])) {
        $_SESSION['error'] = "You do not have permission to edit the agreement.";
        ?>
        <script>window.location.href = "<?php echo $_SERVER['HTTP_REFERER']; ?>";</script>
        <?php
        exit();
    }

    $new_content = $_POST['rental_agreement_html'] ?? '';

    // Basic sanitization — strip <script>, on* handlers, javascript: URLs
    $new_content = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $new_content);
    $new_content = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $new_content);
    $new_content = preg_replace("/\son\w+\s*=\s*'[^']*'/i", '', $new_content);
    $new_content = preg_replace('/javascript:/i', '', $new_content);

    if (update_site_content($conn, 'rental_agreement_html', $new_content, $user_id)) {
        $_SESSION['success'] = "Rental agreement updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to save rental agreement. Please try again.";
    }

    ?>
    <script>window.location.href = "<?php echo $_SERVER['HTTP_REFERER']; ?>";</script>
    <?php
    exit();
}
?>