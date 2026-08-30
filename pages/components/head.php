<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/datatable_init.php';
require_once __DIR__ . '/sweetalert2.php';

$pageTitle = $pageTitle ?? 'Dashboard';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="InstaCar Rental Service - Fleet Management and Online Car Booking System">
    <meta name="keywords" content="car rental, car hire, vehicle management, instacar">
    <meta name="author" content="InstaCar Fleet Management">

    <!-- Favicon Icons -->
    <link rel="icon" type="image/x-icon" href="../../public/assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../../public/assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../public/assets/images/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../../public/assets/images/apple-touch-icon.png">

    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- UI Icon Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Frameworks & Core Scripts -->
    <link href="../../public/assets/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            background: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }

        .sidebar {
            min-height: 100vh;
            background: #212529;
        }

        .sidebar .nav-link {
            color: #ced4da;
            border-radius: 8px;
            margin-bottom: 6px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #0d6efd;
            color: #fff;
        }

        .main-content {
            min-height: 100vh;
            background: #f8f9fa;
        }

        .topbar {
            background: #fff;
            border-bottom: 1px solid #dee2e6;
        }

        .footer {
            background: #fff;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>

<body>