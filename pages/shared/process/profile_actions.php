<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

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
?>