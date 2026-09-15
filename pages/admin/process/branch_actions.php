<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../../../config/database.php';

// Admin-only guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied.";
    ?>
    <script>window.stop(); window.location.href = "../../../index.php";</script>
    <?php
    exit();
}

// ---- Toggle active/inactive ----
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    mysqli_query($conn, "UPDATE branches SET is_active = NOT is_active WHERE id = $id");
    $_SESSION['success'] = "Branch status updated.";
    header("Location: ../branches.php");
    exit();
}

// ---- Add new branch ----
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $name    = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));

    if ($name === '') {
        $_SESSION['error'] = "Branch name is required.";
        header("Location: ../branches.php");
        exit();
    }

    $sql = "INSERT INTO branches (name, address, phone, is_active) 
            VALUES ('$name', '$address', '$phone', 1)";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Branch '$name' created successfully.";
    } else {
        $_SESSION['error'] = "Failed to create branch: " . mysqli_error($conn);
    }

    header("Location: ../branches.php");
    exit();
}

// ---- Edit existing branch ----
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id      = intval($_POST['id'] ?? 0);
    $name    = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $phone   = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));

    if ($id <= 0 || $name === '') {
        $_SESSION['error'] = "Invalid branch data.";
        header("Location: ../branches.php");
        exit();
    }

    $sql = "UPDATE branches SET name = '$name', address = '$address', phone = '$phone' WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Branch updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update branch: " . mysqli_error($conn);
    }

    header("Location: ../branches.php");
    exit();
}

header("Location: ../branches.php");
exit();
?>