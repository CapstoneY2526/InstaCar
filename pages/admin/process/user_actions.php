<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once "../../../config/database.php";

// ---- Branch handling (safe: defaults to NULL if not provided) ----
$branch_id_raw = $_POST['branch_id'] ?? '';
$branch_id = ($branch_id_raw === '' || $branch_id_raw === '0') ? null : intval($branch_id_raw);

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

    // branch_id: NULL for admin, whatever was posted for others
    $final_branch = ($role === 'admin') ? null : $branch_id;

    // Admin-created accounts are auto-verified (no email verification step).
    $is_verified = 1;

    $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, phone, role, branch_id, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt) {
        // "sssssii" = 5 strings + branch_id (int/null) + is_verified (int)
        mysqli_stmt_bind_param($stmt, "sssssii", $name, $email, $password, $phone, $role, $final_branch, $is_verified);
        
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
    <script>window.location.href = "../users.php?role=<?= htmlspecialchars($role) ?>";</script>
    <?php
    exit();
}

// --- HANDLE UPDATE USER ---
if (isset($_POST['update_user'])) {
    $id    = intval($_POST['id'] ?? 0);
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role  = $_POST['role'];

    // branch_id: NULL for admin, whatever was posted for others
    $final_branch = ($role === 'admin') ? null : $branch_id;

    // If a new password was provided, hash and update it.
    // If the field was left blank, keep the existing password untouched.
    $new_password = trim($_POST['password'] ?? '');

    if ($new_password !== '') {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "UPDATE users SET name = ?, email = ?, password = ?, phone = ?, role = ?, branch_id = ? WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssssii", $name, $email, $hashed, $phone, $role, $final_branch, $id);
        }
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE users SET name = ?, email = ?, phone = ?, role = ?, branch_id = ? WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssssii", $name, $email, $phone, $role, $final_branch, $id);
        }
    }

    if ($stmt) {
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Account updated successfully.";
        } else {
            $_SESSION['error'] = "Update failed: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error'] = "Preparation failed: " . mysqli_error($conn);
    }

    ?>
    <script>window.location.href = "../users.php?role=<?= htmlspecialchars($role) ?>";</script>
    <?php
    exit();
}

// --- HANDLE DELETE USER ---
if (isset($_GET['delete'])) {
    $id   = intval($_GET['delete']);
    $role = $_GET['role'] ?? 'user';
    $current_admin_id = (int)$_SESSION['user_id']; 

    // Prevent self-deletion
    if ($id === $current_admin_id) {
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
    <script>window.location.href = "../users.php?role=<?= htmlspecialchars(urlencode($role)) ?>";</script>
    <?php
    exit();
}