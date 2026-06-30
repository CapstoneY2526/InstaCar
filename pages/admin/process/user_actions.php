<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once "../../../config/database.php";

// Security check - JS Redirect
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access.";
    ?>
    <script>
        window.stop();
        window.location.href = "../../index.php";
    </script>
    <?php
    exit();
}

// --- HANDLE ADD USER ---
if (isset($_POST['add_user'])) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone    = trim($_POST['phone']);
    $role     = $_POST['role'];

    // FIXED: Changed $fullname to $name to match the variable above
    $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $password, $phone, $role);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Account created successfully.";
        } else {
            $_SESSION['error'] = "Failed to create account: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Preparation failed: " . mysqli_error($conn);
    }

    ?>
    <script>window.location.href = "../users.php?role=<?= $role ?>";</script>
    <?php
    exit();
}

// --- HANDLE UPDATE USER ---
if (isset($_POST['update_user'])) {
    $id    = $_POST['id'];
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role  = $_POST['role'];
    
    // Note: This updates the password every time. 
    // Usually, you'd only update if the password field isn't empty.
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = mysqli_prepare($conn, "UPDATE users SET name = ?, email = ?, password = ?, phone = ?, role = ? WHERE id = ?");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssssi", $name, $email, $password, $phone, $role, $id);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Account updated successfully.";
        } else {
            $_SESSION['error'] = "Update failed: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }

    ?>
    <script>window.location.href = "../users.php?role=<?= $role ?>";</script>
    <?php
    exit();
}

// --- HANDLE DELETE USER ---
if (isset($_GET['delete'])) {
    $id   = $_GET['delete'];
    $role = $_GET['role'] ?? 'user';
    $current_admin_id = $_SESSION['user_id']; 

    // Prevent self-deletion
    if ($id == $current_admin_id) {
        $_SESSION['error'] = "You cannot delete your own account while logged in!";
    } else {
        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success'] = "User deleted successfully.";
            } else {
                $_SESSION['error'] = "Delete failed: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }

    ?>
    <script>window.location.href = "../users.php?role=<?= $role ?>";</script>
    <?php
    exit();
}