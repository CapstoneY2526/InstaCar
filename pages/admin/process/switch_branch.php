<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['error'] = "Unauthorized.";
    header("Location: ../../../index.php");
    exit();
}

$b = $_GET['b'] ?? 'all';

if ($b === 'all' || $b === '') {
    $_SESSION['view_branch'] = 'all';
    $_SESSION['success'] = "Now viewing all branches.";
} else {
    $bid = (int)$b;
    if ($bid > 0) {
        $_SESSION['view_branch'] = $bid;
        $_SESSION['success'] = "Branch view switched.";
    } else {
        $_SESSION['view_branch'] = 'all';
    }
}

$return = $_SERVER['HTTP_REFERER'] ?? '../../../pages/admin/dashboard.php';
header("Location: " . $return);
exit();
?>