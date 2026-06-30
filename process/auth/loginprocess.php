<?php
session_start();
require_once "../../config/database.php";

if (isset($_POST['submit'])) {
    // 1. Clean the input to prevent SQL Injection
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    // 2. Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        ?>
        <script>window.location.href = "../../index.php";</script>
        <?php
        exit();
    }

    try {
        // 3. Short Query & Fetch in one line
        $sql = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
        $user = mysqli_fetch_assoc(mysqli_query($conn, $sql));

        // 4. If user exists, check the password
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['success'] = "Welcome back, " . $user['name'] . "!";

            // Determine the path based on role
            if ($user['role'] === "admin") {
                $location = "../../pages/admin/dashboard.php";
            } elseif ($user['role'] === "operator") {
                $location = "../../pages/operator/dashboard.php";
            } else {
                $location = "../../pages/user/dashboard.php";
            }
            
            // 5. JavaScript Redirect for Success
            ?>
            <script>window.location.href = '<?php echo $location; ?>';</script>
            <?php
            exit();

        } else {
            // Error if password or email is wrong
            $_SESSION['error'] = "Invalid email or password.";
            ?>
            <script>window.location.href = "../../login.php";</script>
            <?php
            exit();
        }

    } catch (Exception $e) {
        $_SESSION['error'] = "Login error occurred.";
        ?>
        <script>window.location.href = "../../login.php";</script>
        <?php
        exit();
    }
}
?>