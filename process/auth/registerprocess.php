<?php
session_start();
require_once "../../config/database.php";

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

        // 4. Hash Password & Insert User
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = "user"; // Default role for new registrations

        $insert_sql = "INSERT INTO users (name, email, password, role) VALUES ('$name', '$email', '$hashed_password', '$role')";

        if (mysqli_query($conn, $insert_sql)) {
            $_SESSION['success'] = "Registration successful! You can now log in.";
            ?>
            <script>window.location.href = "../../login.php";</script>
            <?php
            exit();
        } else {
            throw new Exception("Database insertion failed.");
        }

    } catch (Exception $e) {
        $_SESSION['error'] = "Registration error occurred.";
        ?>
        <script>window.location.href = "../../register.php";</script>
        <?php
        exit();
    }
}
?>