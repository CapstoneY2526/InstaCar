<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied.";
    ?>
    <script>window.stop(); window.location.href = "../../index.php";</script>
    <?php
    exit();
}

if (isset($_FILES['proof_of_billing'])) {
    $fileType = $_FILES['proof_of_billing']['type'];
    $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($fileType, $allowed)) {
        die("Error: Only PDFs and Images are allowed.");
    }
}

$pageTitle = 'Manual Booking';
$filter = $_GET['filter'] ?? 'All';

// Fetch Registered Customers
$customers = [];
$userRes = mysqli_query($conn, "SELECT id, name FROM users WHERE role = 'user' ORDER BY name ASC");
if ($userRes) {
    while ($row = mysqli_fetch_assoc($userRes)) {
        $customers[] = $row;
    }
}

// Get stats for manual bookings
$statsQuery = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
FROM bookings 
WHERE booking_type = 'manual'";

$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Fetch Bookings with Filter
$bookings = [];
$query = "SELECT b.*, u.name as member_name, c.brand, c.model, c.plate_number 
          FROM bookings b 
          LEFT JOIN users u ON b.user_id = u.id 
          JOIN cars c ON b.car_id = c.id 
          WHERE b.booking_type = 'manual'";

if ($filter !== 'All') {
    $safe_filter = mysqli_real_escape_string($conn, $filter);
    $query .= " AND b.status = '$safe_filter'";
}
$query .= " ORDER BY b.id DESC";

$res = mysqli_query($conn, $query);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $bookings[] = $row;
    }
}

// Fetch Cars with active reservation timelines
$available_cars = [];
$carQuery = "
    SELECT c.*, 
           GROUP_CONCAT(CONCAT(b.start_date, ' ', b.pickup_time, '|', b.end_date, ' ', b.return_time)) AS busy_slots
    FROM cars c
    LEFT JOIN bookings b ON c.id = b.car_id AND b.status NOT IN ('Cancelled', 'Completed')
    GROUP BY c.id 
    ORDER BY c.brand ASC, c.model ASC
";
$carRes = mysqli_query($conn, $carQuery);
if ($carRes) {
    while ($row = mysqli_fetch_assoc($carRes)) {
        $available_cars[] = $row;
    }
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    /* ========================================================
       CORE VARIABLES & GLOBAL RESET
       ======================================================== */
    :root {
        --brand-yellow: #ffcc00;
        --brand-yellow-hover: #e6b800;
        --brand-yellow-soft: rgba(255, 204, 0, 0.12);
        --brand-black: #0a0a0a;
        --brand-dark: #0f172a;
        --brand-ink: #1e293b;
        --brand-muted: #64748b;
        --brand-border: #e2e8f0;
        --brand-bg: #f8fafc;
        --brand-card-bg-dark: #141414;
        --brand-row-bg-dark: #1a1a1a;
        --brand-border-dark: #27272a;
        --card-radius: 16px;
    }

    * { box-sizing: border-box; }
    
    /* Prevent double scrollbars at root level */
    html, body {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
    }

    body { 
        background-color: var(--brand-bg); 
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: var(--brand-ink);
        transition: background-color 0.25s ease, color 0.25s ease;
    }

    .main-content { 
        min-width: 0;
        min-height: 100vh; 
        width: 100%;
        overflow-x: hidden;
    }

    /* Header & Action Bar Accent */
    .header-title-wrapper {
        padding-bottom: 1.25rem;
        border-bottom: 2px solid var(--brand-yellow-soft);
    }

    .btn-brand,
    .btn-primary {
        background-color: var(--brand-yellow) !important;
        color: #000000 !important;
        font-weight: 700 !important;
        border: none !important;
        border-radius: 12px;
        padding: 0.6rem 1.25rem;
        box-shadow: 0 2px 4px rgba(255, 204, 0, 0.2) !important;
        transition: all 0.2s ease !important;
    }

    .btn-brand:hover,
    .btn-primary:hover,
    .btn-primary:focus {
        background-color: var(--brand-yellow-hover) !important;
        color: #000000 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.35) !important;
    }

    .text-brand-yellow { color: #b38a00 !important; }
    .bg-brand-yellow { background: var(--brand-yellow-soft) !important; }

    /* Unified Stat Cards */
    .stat-card {
        background: #ffffff;
        border-radius: var(--card-radius);
        padding: 1.25rem;
        transition: all 0.25s ease;
        border: 1px solid var(--brand-border);
        height: 100%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }

    .stat-card:hover {
        transform: translateY(-2px);
        border-color: var(--brand-yellow);
        box-shadow: 0 12px 20px -8px rgba(255, 204, 0, 0.25);
    }

    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .stat-value { 
        font-size: 1.5rem;
        font-weight: 800;
        line-height: 1.2;
        color: var(--brand-dark);
    }

    .stat-label {
        font-size: 0.725rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--brand-muted);
        font-weight: 700;
        margin-top: 2px;
    }

    /* Navigation Status Pills */
    .nav-status-pills {
        background: #ffffff;
        padding: 6px;
        border-radius: var(--card-radius);
        border: 1px solid var(--brand-border);
        gap: 4px;
        display: flex;
        flex-wrap: wrap;
    }

    .nav-status-pills .nav-link {
        color: var(--brand-muted);
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.5rem 1.25rem;
        border-radius: 10px;
        transition: all 0.2s ease;
    }

    .nav-status-pills .nav-link.active {
        color: #000000;
        background: var(--brand-yellow);
        font-weight: 700;
    }

    /* Control Bar & Search Mechanics */
    .custom-control-bar {
        border: 1px solid var(--brand-border) !important;
        border-radius: var(--card-radius) !important;
        background: #ffffff !important;
    }

    .search-input-wrapper { 
        position: relative; 
        width: 100%; 
        max-width: 320px; 
    }

    .search-input-wrapper i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--brand-muted);
        font-size: 14px;
        pointer-events: none;
    }

    .search-input-wrapper input {
        width: 100% !important;
        border: 1px solid var(--brand-border) !important;
        border-radius: 10px !important;
        padding: 8px 14px 8px 38px !important;
        font-size: 0.85rem !important;
        height: 40px !important;
        color: var(--brand-ink) !important;
        background-color: #ffffff;
        transition: all 0.2s ease-in-out;
    }

    .search-input-wrapper input:focus {
        border-color: var(--brand-yellow) !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.2) !important;
    }

    /* Dark Limit Select Dropdown Mechanics */
    .entry-limiter-select {
        height: 40px;
        padding: 6px 32px 6px 12px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #ffffff !important;
        background-color: #0d0d0d !important;
        border: 1px solid var(--brand-border-dark);
        border-radius: 10px;
        outline: none;
        appearance: none;
        -webkit-appearance: none;
        background: #0d0d0d url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23cbd5e1' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") no-repeat right 12px center/12px 12px !important;
    }

    .entry-limiter-select option {
        background-color: #0d0d0d !important;
        color: #ffffff !important;
    }

    /* Duration Quick Toggle Buttons */
    .duration-buttons {
        display: flex;
        gap: 8px;
    }

    .duration-btn {
        flex: 1;
        padding: 8px 12px;
        font-size: 0.8rem;
        font-weight: 600;
        border-radius: 10px;
        border: 1px solid var(--brand-border);
        background: #ffffff;
        color: var(--brand-ink);
        transition: all 0.2s;
        cursor: pointer;
    }

    .duration-btn.active {
        background: var(--brand-yellow);
        color: #000000;
        border-color: var(--brand-yellow);
        font-weight: 700;
    }

    /* Table & Status Formatting */
    .table-card {
        border-radius: var(--card-radius);
        border: 1px solid var(--brand-border);
        overflow: hidden;
        background: #ffffff;
    }

    .desktop-table-wrapper {
        width: 100%;
    }

    .desktop-table-wrapper .table-responsive,
    .table-responsive {
        overflow-x: auto !important;
        overflow-y: visible !important;
        -webkit-overflow-scrolling: touch;
    }

    .table {
        width: 100% !important;
        margin: 0 !important;
        vertical-align: middle;
        background-color: #ffffff;
        border-collapse: separate;
        border-spacing: 0;
    }

    .table thead th {
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: 0.7rem;
        background-color: #f8fafc;
        color: var(--brand-muted);
        padding: 0.9rem 1rem;
        border: none;
        border-bottom: 2px solid var(--brand-yellow-soft);
    }

    .table tbody tr {
        transition: background-color 0.15s ease;
    }

    .table tbody td {
        vertical-align: middle;
        white-space: normal !important;
        word-break: break-word;
        background-color: #ffffff;
        padding: 0.85rem 1rem;
        border: none;
        border-bottom: 1px solid var(--brand-border);
    }

    .table tbody tr:last-child td {
        border-bottom: none;
    }

    .table-hover > tbody > tr:hover > * {
        background-color: var(--brand-yellow-soft) !important;
    }

    .table td .dropdown-menu {
        z-index: 1050 !important;
    }

    /* Customer identity avatar */
    .customer-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--brand-yellow-soft);
        color: #8a6a00;
        font-weight: 700;
        font-size: 0.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    body.dark-mode .customer-avatar {
        background: rgba(255, 204, 0, 0.15);
        color: var(--brand-yellow);
    }

    .status-badge {
        padding: 5px 12px 5px 10px;
        border-radius: 20px;
        font-size: 0.725rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }

    .status-badge::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
        flex-shrink: 0;
    }

    .status-confirmed { background: #e0f2fe; color: #0369a1; }
    .status-completed { background: #dcfce7; color: #15803d; }
    .status-cancelled { background: #fee2e2; color: #b91c1c; }

    /* Mobile Booking Cards Container */
    .mobile-cards-wrapper {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        width: 100%;
        position: relative;
    }

    .mobile-booking-card {
        background: #ffffff;
        border-radius: var(--card-radius);
        border: 1px solid var(--brand-border);
        padding: 1.25rem;
        margin-bottom: 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        width: 100%;
        position: relative; /* Context parent for absolute dropdown placement */
        overflow: visible !important; /* Prevents nested dropdown clipping */
    }

    /* Fix Dropdown Clippings & Off-screen Overflows on Mobile Cards */
    .mobile-booking-card .dropdown {
        position: relative;
    }

    .mobile-booking-card .dropdown-menu {
        right: 0 !important;
        left: auto !important;
        max-width: calc(100vw - 2rem) !important;
        margin-top: 0.25rem;
        z-index: 1050 !important;
    }

    /* Modal Layering & Layout */
    .modal {
        z-index: 1060 !important;
    }

    .modal-backdrop {
        z-index: 1055 !important;
    }

    .modal-content {
        border-radius: var(--card-radius) !important;
        border: 1px solid var(--brand-border);
        background-color: #ffffff;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .modal-header {
        border-bottom: 1px solid #f1f5f9;
        padding: 1.25rem 1.5rem;
        background-color: #ffffff;
    }

    .modal-footer {
        border-top: 1px solid #f1f5f9;
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        background-color: #ffffff;
    }

    .modal-body {
        background-color: #ffffff;
    }

    .form-label {
        font-weight: 600;
        font-size: 0.8125rem;
        color: #334155;
        margin-bottom: 0.375rem;
        letter-spacing: 0.2px;
    }

    .form-control,
    .form-select {
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        color: #0f172a;
        background-color: #ffffff;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.25) !important;
        outline: none;
    }

    /* Dropzone Base */
    #dropzoneContainer,
    [id^="editDropzoneContainer"] {
        background-color: #fafafa;
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        padding: 1.5rem;
        text-align: center;
        transition: all 0.2s ease-in-out;
    }

    #dropzoneContainer:hover,
    [id^="editDropzoneContainer"]:hover {
        border-color: var(--brand-yellow);
        background-color: var(--brand-yellow-soft);
    }

    /* Native File Input Styling */
    input[type="file"] {
        font-size: 0.825rem;
    }

    /* Dynamic Pricing Box Utilities (Light & Dark Ready) */
    .pricing-summary-box,
    .price-breakdown-card {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
    }

    .price-info-alert,
    .alert-info-custom,
    .modal-body .alert-info {
        background-color: #e0f2fe !important;
        border: 1px solid #bae6fd !important;
        color: #0369a1 !important;
        border-radius: 12px !important;
        padding: 0.875rem 1.125rem !important;
        font-size: 0.875rem !important;
        line-height: 1.4 !important;
        width: 100% !important;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        transition: all 0.25s ease;
    }

    .price-info-alert strong,
    .alert-info-custom strong,
    .modal-body .alert-info strong {
        color: #0c4a6e !important;
        font-weight: 700;
    }

    .price-info-alert .subtext,
    .alert-info-custom .subtext,
    .modal-body .alert-info p,
    .modal-body .alert-info span {
        color: #0284c7 !important;
        font-size: 0.8rem;
    }

    /* Active Selector Pill Component */
    .user-status-card,
    .status-dropdown-btn {
        display: inline-flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.625rem;
        background-color: #ffffff;
        border: 1px solid var(--brand-border);
        border-radius: 14px;
        padding: 0.4rem 0.75rem 0.4rem 0.4rem;
        max-width: 100%;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .user-status-card:hover,
    .status-dropdown-btn:hover {
        border-color: var(--brand-yellow);
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .user-status-card .avatar-icon,
    .status-dropdown-btn .avatar-icon {
        width: 34px;
        height: 34px;
        background-color: var(--brand-yellow);
        color: #000000;
        font-weight: 800;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.9rem;
    }

    .user-status-card .status-text,
    .status-dropdown-btn .status-text {
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.5px;
        color: #b38a00;
        text-transform: uppercase;
    }

    /* ========================================================
       DEVICE RESPONSIVENESS & BREAKPOINT FIXES
       ======================================================== */

    /* Desktop View (Laptops & Large Displays: 992px+) */
    @media (min-width: 992px) {
        .mobile-cards-wrapper {
            display: none !important;
        }

        .desktop-table-wrapper,
        .desktop-table-card {
            display: block !important;
        }

        .user-status-card,
        .status-dropdown-btn {
            min-width: 140px;
        }
    }

    /* Tablet View (Mid-range Devices: 576px to 991.98px) */
    @media (min-width: 576px) and (max-width: 991.98px) {
        .mobile-cards-wrapper {
            display: flex !important;
        }

        .desktop-table-wrapper,
        .desktop-table-card {
            display: none !important;
        }

        .price-info-alert,
        .alert-info-custom {
            padding: 0.875rem 1rem !important;
        }

        .user-status-card,
        .status-dropdown-btn {
            min-width: 130px;
        }

        .custom-control-bar .card-body {
            gap: 1rem;
        }

        .search-input-wrapper {
            max-width: 240px;
        }
    }

    /* Mobile Phone View (< 575.98px) */
    @media (max-width: 575.98px) {
        .desktop-table-wrapper,
        .desktop-table-card {
            display: none !important;
        }

        .mobile-cards-wrapper {
            display: flex !important;
            width: 100% !important;
        }

        .main-content { 
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
            width: 100% !important;
            overflow-x: hidden !important;
        }

        .main-content > .p-4 {
            padding: 1rem 0.5rem !important;
        }

        /* Mobile Header and Floating Controls */
        header, .navbar, .header-title-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
            justify-content: space-between;
        }

        .header-title-wrapper .btn-brand {
            width: 100%;
            justify-content: center;
        }

        .custom-control-bar .card-body {
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 12px !important;
            padding: 0.875rem !important;
        }

        .custom-control-bar .card-body > div:first-child {
            flex: 0 0 auto !important;
        }

        .search-input-wrapper {
            flex: 1 1 auto !important;
            max-width: 100% !important;
            width: auto !important;
        }

        .nav-status-pills {
            width: 100%;
            display: flex !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            justify-content: flex-start !important;
            -webkit-overflow-scrolling: touch;
            padding: 4px !important;
        }

        .nav-status-pills .nav-item {
            flex: 0 0 auto !important;
            text-align: center;
        }

        .nav-status-pills .nav-link {
            padding: 0.4rem 0.75rem !important;
            font-size: 0.75rem;
            text-align: center;
            white-space: nowrap !important;
        }

        /* Prevent Text Squishing & Prevent Horizontal Scroll Overflows */
        .mobile-booking-card {
            padding: 1rem !important;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .mobile-booking-card * {
            max-width: 100%;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .mobile-booking-card .status-badge {
            align-self: flex-start;
        }

        /* Action Buttons Grid on Mobile */
        .mobile-booking-card .action-buttons-group,
        .mobile-booking-card .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            width: 100%;
            margin-top: 0.5rem;
        }

        .mobile-booking-card .action-buttons-group .btn,
        .mobile-booking-card .btn-group .btn {
            flex: 1 1 auto;
            text-align: center;
            padding: 0.4rem 0.6rem;
            font-size: 0.775rem;
        }

        .modal-dialog {
            margin: 0.5rem;
            max-width: calc(100% - 1rem) !important;
        }

        .modal-header, .modal-footer {
            padding: 1rem !important;
        }

        .modal-header .modal-icon-badge {
            width: 38px;
            height: 38px;
            font-size: 1rem;
        }

        .modal-title-text { font-size: 1rem; }

        .modal-body {
            padding: 1rem !important;
        }

        .price-info-alert,
        .alert-info-custom,
        .modal-body .alert-info {
            padding: 0.75rem !important;
            font-size: 0.8125rem !important;
        }

        .user-status-card,
        .status-dropdown-btn {
            width: 100%;
            padding: 0.5rem 0.75rem 0.5rem 0.5rem;
        }

        /* Custom Date Badge Fixes */
        .schedule-badge, .schedule-pill {
            white-space: normal !important;
            line-height: 1.3;
            padding: 0.5rem;
            font-size: 0.8rem;
        }
    }

    /* ========================================================
       COMPLETE & CONSOLIDATED DARK MODE OVERRIDES
       ======================================================== */
    body.dark-mode {
        background-color: var(--brand-black) !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .main-content {
        background-color: var(--brand-black) !important;
    }

    body.dark-mode header,
    body.dark-mode nav,
    body.dark-mode .navbar {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode footer,
    body.dark-mode .footer {
        background-color: var(--brand-black) !important;
        border-color: var(--brand-border-dark) !important;
        color: #cbd5e1 !important;
    }

    /* Fix Light Containers in Dark Mode */
    body.dark-mode .bg-light,
    body.dark-mode .pricing-summary-box,
    body.dark-mode .price-breakdown-card,
    body.dark-mode #dateTimePreview {
        background-color: #1a1a1a !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .card,
    body.dark-mode .stat-card,
    body.dark-mode .modal-content,
    body.dark-mode .table-card,
    body.dark-mode .mobile-booking-card,
    body.dark-mode .dropdown-menu {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .dropdown-item {
        color: #e2e8f0 !important;
    }

    body.dark-mode .dropdown-item:hover {
        background-color: #27272a !important;
        color: #ffffff !important;
    }

    body.dark-mode .stat-card:hover {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 10px 24px rgba(255, 204, 0, 0.12) !important;
    }

    body.dark-mode .stat-value,
    body.dark-mode .fw-bold,
    body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, 
    body.dark-mode h4, body.dark-mode h5, body.dark-mode h6,
    body.dark-mode .text-dark,
    body.dark-mode .modal-title {
        color: #ffffff !important;
    }

    body.dark-mode .stat-label,
    body.dark-mode .text-muted,
    body.dark-mode .form-label {
        color: #cbd5e1 !important;
    }

    /* Navigation Pills Dark Override */
    body.dark-mode .nav-status-pills {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
    }

    body.dark-mode .nav-status-pills .nav-link {
        color: #cbd5e1;
    }

    body.dark-mode .nav-status-pills .nav-link.active {
        background-color: var(--brand-yellow) !important;
        color: #000000 !important;
    }

    /* Quick Duration Dark Override */
    body.dark-mode .duration-btn {
        background-color: #1a1a1a;
        border-color: var(--brand-border-dark);
        color: #f1f5f9;
    }

    body.dark-mode .duration-btn.active {
        background-color: var(--brand-yellow) !important;
        color: #000000 !important;
        border-color: var(--brand-yellow) !important;
    }

    /* Control Bar Dark Override */
    body.dark-mode .custom-control-bar {
        background: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
    }

    body.dark-mode .entry-limiter-select {
        background-color: #0d0d0d !important;
        color: #f1f5f9 !important;
        border-color: var(--brand-border-dark) !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23cbd5e1' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    }

    body.dark-mode .search-input-wrapper input {
        background-color: #0d0d0d !important;
        color: #ffffff !important;
        border-color: var(--brand-border-dark) !important;
    }

    body.dark-mode .search-input-wrapper input::placeholder {
        color: #64748b;
    }

    /* Table & Action Buttons Dark Mode */
    body.dark-mode .table {
        color: #f1f5f9 !important;
        background-color: var(--brand-card-bg-dark) !important;
    }

    body.dark-mode .table thead th {
        background-color: #1a1600 !important;
        color: var(--brand-yellow) !important;
        border-bottom: 2px solid var(--brand-border-dark) !important;
    }

    body.dark-mode .table tbody tr {
        background-color: var(--brand-row-bg-dark) !important;
    }

    body.dark-mode .table td {
        background-color: var(--brand-row-bg-dark) !important;
        border-bottom: 1px solid var(--brand-border-dark) !important;
        color: #e2e8f0 !important;
    }

    body.dark-mode .table-hover > tbody > tr:hover > * {
        background-color: #262626 !important;
        color: #ffffff !important;
    }

    /* Action Buttons & Dropdowns Fixes */
    body.dark-mode .btn-light,
    body.dark-mode .btn-white,
    body.dark-mode .btn-outline-secondary,
    body.dark-mode .dropdown-toggle {
        background-color: #1f1f1f !important;
        color: #f1f5f9 !important;
        border-color: var(--brand-border-dark) !important;
    }

    body.dark-mode .btn-light:hover,
    body.dark-mode .btn-white:hover,
    body.dark-mode .btn-outline-secondary:hover,
    body.dark-mode .dropdown-toggle:hover,
    body.dark-mode .dropdown-toggle:focus {
        background-color: #2a2a2a !important;
        color: #ffffff !important;
        border-color: var(--brand-yellow) !important;
    }

    /* Native File Input Buttons Dark Mode Fix */
    body.dark-mode input[type="file"] {
        background-color: #171717 !important;
        color: #f8fafc !important;
        border-color: var(--brand-border-dark) !important;
    }

    body.dark-mode input[type="file"]::file-selector-button {
        background-color: #27272a !important;
        color: #f1f5f9 !important;
        border: 1px solid var(--brand-border-dark) !important;
        border-radius: 6px;
        padding: 0.25rem 0.75rem;
        margin-right: 0.75rem;
        transition: all 0.2s ease;
    }

    body.dark-mode input[type="file"]::file-selector-button:hover {
        background-color: var(--brand-yellow) !important;
        color: #000000 !important;
        border-color: var(--brand-yellow) !important;
        cursor: pointer;
    }

    /* Modal Layout & Elements Dark Fix */
    body.dark-mode .modal-header,
    body.dark-mode .modal-footer,
    body.dark-mode .modal-body {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
    }

    body.dark-mode .modal-footer .btn-secondary {
        background-color: #27272a !important;
        border: 1px solid var(--brand-border-dark) !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .modal-footer .btn-secondary:hover {
        background-color: #3f3f46 !important;
        color: #ffffff !important;
    }

    body.dark-mode .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    body.dark-mode .form-control,
    body.dark-mode .form-select {
        background-color: #171717 !important;
        border-color: var(--brand-border-dark) !important;
        color: #f8fafc !important;
    }

    body.dark-mode .form-control::placeholder {
        color: #64748b !important;
    }

    body.dark-mode .form-control:focus,
    body.dark-mode .form-select:focus {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.2) !important;
    }

    body.dark-mode #dropzoneContainer,
    body.dark-mode [id^="editDropzoneContainer"] {
        background-color: #121212 !important;
        border-color: var(--brand-border-dark) !important;
    }

    body.dark-mode #dropzoneContainer:hover,
    body.dark-mode [id^="editDropzoneContainer"]:hover {
        border-color: var(--brand-yellow) !important;
        background-color: rgba(255, 204, 0, 0.05) !important;
    }

    body.dark-mode .text-brand-yellow {
        color: var(--brand-yellow) !important;
    }

    body.dark-mode .bg-brand-yellow,
    body.dark-mode .stat-icon.bg-brand-yellow {
        background: rgba(255, 204, 0, 0.15) !important;
        color: var(--brand-yellow) !important;
    }

    /* Dark Mode Alert & Profile Pill Overrides */
    body.dark-mode .price-info-alert,
    body.dark-mode .alert-info-custom,
    body.dark-mode .modal-body .alert-info {
        background-color: #082f49 !important;
        border-color: #0e7490 !important;
        color: #e0f2fe !important;
    }

    body.dark-mode .price-info-alert strong,
    body.dark-mode .alert-info-custom strong,
    body.dark-mode .modal-body .alert-info strong {
        color: #38bdf8 !important;
    }

    body.dark-mode .price-info-alert .subtext,
    body.dark-mode .alert-info-custom .subtext,
    body.dark-mode .modal-body .alert-info p,
    body.dark-mode .modal-body .alert-info span {
        color: #bae6fd !important;
    }

    body.dark-mode .user-status-card,
    body.dark-mode .status-dropdown-btn {
        background-color: #000000 !important;
        border-color: var(--brand-border-dark) !important;
    }

    body.dark-mode .user-status-card .status-text,
    body.dark-mode .status-dropdown-btn .status-text {
        color: var(--brand-yellow) !important;
    }

    /* ========================================================
   CUSTOM SLEEK SCROLLBAR STYLING (GLOBAL & CONTAINERS)
   ======================================================== */
/* Firefox Scrollbar Support */
* {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

body.dark-mode * {
    scrollbar-color: #3f3f46 transparent;
}

/* WebKit Scrollbars (Chrome, Edge, Safari) */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 8px;
}

::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 8px;
    border: 2px solid transparent;
    background-clip: padding-box;
    transition: background-color 0.2s ease;
}

::-webkit-scrollbar-thumb:hover {
    background-color: var(--brand-yellow);
}

::-webkit-scrollbar-corner {
    background: transparent;
}

/* Dark Mode Scrollbar Overrides */
body.dark-mode ::-webkit-scrollbar-thumb {
    background-color: #3f3f46;
}

body.dark-mode ::-webkit-scrollbar-thumb:hover {
    background-color: var(--brand-yellow);
}

/* Table Responsive Container Scrollbar Polish */
.table-responsive::-webkit-scrollbar {
    height: 6px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
}

body.dark-mode .table-responsive::-webkit-scrollbar-thumb {
    background-color: #3f3f46;
}

body.dark-mode .table-responsive::-webkit-scrollbar-thumb:hover {
    background-color: var(--brand-yellow);
}

/* Custom Searchable Dropdown Styles */
.custom-dropdown-wrapper {
    position: relative;
    width: 100%;
}

.custom-dropdown-btn {
    width: 100%;
    background-color: #121214 !important;
    color: #ffffff !important;
    border: 1px solid #333338 !important;
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    text-align: left;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.custom-dropdown-wrapper.active .custom-dropdown-btn {
    border-color: #ffcc00 !important;
    box-shadow: 0 0 0 2px rgba(255, 204, 0, 0.4) !important;
}

.custom-dropdown-menu {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background-color: #141416 !important;
    border: 1px solid #27272a !important;
    border-radius: 12px;
    padding: 8px;
    z-index: 1070;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
    max-height: 320px;
    overflow-y: auto;
}

.custom-dropdown-wrapper.active .custom-dropdown-menu {
    display: block;
}

.custom-dropdown-search {
    width: 100%;
    background-color: #1c1c1f !important;
    border: 1px solid #2d2d32 !important;
    border-radius: 8px;
    color: #ffffff !important;
    padding: 8px 12px;
    font-size: 0.85rem;
    margin-bottom: 8px;
    outline: none;
}

.custom-dropdown-search::placeholder {
    color: #64748b;
}

.custom-dropdown-search:focus {
    border-color: #3b3b44 !important;
}

.custom-dropdown-options {
    list-style: none;
    padding: 0;
    margin: 0;
}

.custom-dropdown-item {
    padding: 8px 12px;
    font-size: 0.875rem;
    font-weight: 700;
    color: #ffffff;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.15s ease;
}

.custom-dropdown-item:hover {
    background-color: #242428;
}

.custom-dropdown-divider {
    height: 1px;
    background-color: #27272a;
    margin: 6px 0;
}

.car-type-dropdown-container {
    position: relative;
    width: 100%;
}

.car-type-dropdown-btn {
    width: 100%;
    background-color: #121214;
    color: #ffffff;
    border: 1px solid #333338;
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    text-align: left;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.car-type-menu {
    background-color: #18181b !important;
    border: 1px solid #27272a !important;
    border-radius: 8px;
    max-height: 280px;
    overflow-y: auto;
}

.car-type-menu .dropdown-item {
    color: #ffffff;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
}

.car-type-menu .dropdown-item:hover,
.car-type-menu .dropdown-item.active {
    background-color: #facc15 !important; /* Yellow highlight from image */
    color: #000000 !important;
}

.car-type-search-input {
    background-color: #09090b !important;
    border: 1px solid #27272a !important;
    color: #ffffff !important;
}

/* ============================================================
   MANUAL BOOKING MODAL — REDESIGN
   Theme-aware (light/dark), glassmorphism, micro-animations
   ============================================================ */

/* ---- Utility ---- */
.fs-xs { font-size: 0.68rem; }
.tracking-wider { letter-spacing: 0.08em; }

/* ---- Modal shell + entrance animation ---- */
#manualBookingModal .modal-dialog {
    transition: transform 0.35s cubic-bezier(.34,1.56,.64,1), opacity 0.25s ease;
}
#manualBookingModal.fade .modal-dialog {
    transform: scale(0.94) translateY(24px);
    opacity: 0;
}
#manualBookingModal.show .modal-dialog {
    transform: scale(1) translateY(0);
    opacity: 1;
}

.glass-card {
    background: rgba(255, 255, 255, 0.94);
    backdrop-filter: blur(20px) saturate(160%);
    -webkit-backdrop-filter: blur(20px) saturate(160%);
    border: 1px solid rgba(226, 232, 240, 0.8) !important;
}

body.dark-mode .glass-card {
    background: rgba(18, 18, 18, 0.92) !important;
    border: 1px solid rgba(255, 255, 255, 0.07) !important;
}

/* ---- Header ---- */
.modal-icon-badge {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #000000;
    background: linear-gradient(135deg, var(--brand-yellow), #ffe066);
    box-shadow: 0 6px 16px rgba(255, 204, 0, 0.35);
    flex-shrink: 0;
    animation: badgePop 0.5s cubic-bezier(.34,1.56,.64,1);
}

@keyframes badgePop {
    from { transform: scale(0.6) rotate(-10deg); opacity: 0; }
    to { transform: scale(1) rotate(0); opacity: 1; }
}

.modal-title-text {
    color: var(--brand-dark);
    letter-spacing: -0.2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
body.dark-mode .modal-title-text { color: #ffffff; }

/* Keep the title block from crowding the close button on narrow screens */
.modal-header { flex-wrap: nowrap; gap: 0.75rem; }
.modal-header > .d-flex.align-items-center.gap-3 {
    min-width: 0;
    flex: 1 1 auto;
}
.modal-header > .d-flex.align-items-center.gap-3 > div {
    min-width: 0;
}
.modal-header p.text-muted {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.btn-close-custom {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid var(--brand-border);
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--brand-muted);
    font-size: 0.8rem;
    transition: all 0.2s ease;
    flex-shrink: 0;
    margin-left: 0.75rem;
}
.btn-close-custom:hover {
    background: #fee2e2;
    color: #b91c1c;
    border-color: #fecaca;
    transform: rotate(90deg);
}
body.dark-mode .btn-close-custom {
    background: #171717;
    border-color: var(--brand-border-dark);
    color: #cbd5e1;
}
body.dark-mode .btn-close-custom:hover {
    background: #3f1212;
    color: #f87171;
    border-color: #7f1d1d;
}

/* ---- Icon input group ---- */
.input-group-custom { position: relative; }
.input-group-custom .input-icon {
    position: absolute;
    left: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
    background: var(--brand-yellow-soft);
    color: #8a6a00;
    font-size: 0.8rem;
    pointer-events: none;
    z-index: 2;
    transition: background-color 0.2s ease, color 0.2s ease;
}
.input-group-custom .custom-select,
.input-group-custom .custom-input { padding-left: 2.75rem !important; }
.input-group-custom.compact .input-icon { width: 20px; height: 20px; left: 7px; }
.input-group-custom.compact .custom-input { padding-left: 2.35rem !important; height: 38px; }
.input-group-custom:focus-within .input-icon { background: var(--brand-yellow); color: #000000; }
body.dark-mode .input-group-custom .input-icon { background: rgba(255, 204, 0, 0.12); color: var(--brand-yellow); }
body.dark-mode .input-group-custom:focus-within .input-icon { background: var(--brand-yellow); color: #000000; }

.custom-select,
.custom-input {
    width: 100%;
    border-radius: 10px !important;
    border: 1.5px solid var(--brand-border);
    background-color: #ffffff;
    font-size: 0.875rem;
    font-weight: 500;
    padding: 0.6rem 0.75rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.15s ease, background-color 0.2s ease;
}
.custom-select:hover,
.custom-input:hover {
    border-color: #cbd5e1;
}
select.custom-select {
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.85rem center;
    background-size: 12px 12px;
    padding-right: 2.2rem !important;
    cursor: pointer;
}
.custom-select:focus,
.custom-input:focus {
    border-color: var(--brand-yellow) !important;
    box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.22) !important;
    outline: none;
}
body.dark-mode .custom-select,
body.dark-mode .custom-input {
    background-color: #1c1c1f;
    border-color: #34343a;
    color: #f8fafc;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.35);
}
body.dark-mode .custom-select:hover,
body.dark-mode .custom-input:hover {
    border-color: #52525b;
}
body.dark-mode select.custom-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23cbd5e1' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
}
body.dark-mode .custom-select:focus,
body.dark-mode .custom-input:focus {
    box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.18) !important;
}

/* ---- Customer type fade-switch ---- */
.fade-switch { animation: switchIn 0.35s ease; }
@keyframes switchIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ---- File upload cards ---- */
.file-upload-card { position: relative; }
.file-input-hidden { position: absolute; inset: 0; opacity: 0; cursor: pointer; z-index: 2; margin: 0; }
.file-upload-label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    min-height: 96px;
    border: 2px dashed var(--brand-border);
    border-radius: 14px;
    padding: 1rem 0.75rem;
    text-align: center;
    color: var(--brand-muted);
    background: #fafafa;
    transition: all 0.25s ease;
    cursor: pointer;
}
.file-upload-label i { color: var(--brand-muted); transition: transform 0.25s ease, color 0.25s ease; }
.file-upload-card:hover .file-upload-label,
.file-upload-card:focus-within .file-upload-label {
    border-color: var(--brand-yellow);
    background: var(--brand-yellow-soft);
    transform: translateY(-2px);
}
.file-upload-card:hover .file-upload-label i { color: #b38a00; transform: translateY(-3px) scale(1.08); }
.file-upload-card.has-file .file-upload-label {
    border-style: solid;
    border-color: #22c55e;
    background: rgba(34, 197, 94, 0.08);
    animation: fileAccept 0.4s ease;
}
.file-upload-card.has-file .file-upload-label i { color: #16a34a; }
@keyframes fileAccept {
    0% { transform: scale(0.97); }
    50% { transform: scale(1.015); }
    100% { transform: scale(1); }
}
.file-title { font-weight: 700; font-size: 0.8rem; color: var(--brand-ink); }
.file-subtitle { font-size: 0.7rem; color: var(--brand-muted); }
body.dark-mode .file-upload-label { background: #141414; border-color: var(--brand-border-dark); color: #94a3b8; }
body.dark-mode .file-title { color: #f1f5f9; }
body.dark-mode .file-upload-card:hover .file-upload-label { background: rgba(255, 204, 0, 0.06); }
body.dark-mode .file-upload-card.has-file .file-upload-label { background: rgba(34, 197, 94, 0.1); border-color: #22c55e; }

/* ---- Duration segmented control ---- */
.duration-segmented-control {
    display: flex;
    gap: 6px;
    background: #f1f5f9;
    padding: 5px;
    border-radius: 12px;
    border: 1px solid var(--brand-border);
}
.segment-btn {
    flex: 1;
    border: none;
    background: transparent;
    padding: 0.55rem 0.5rem;
    border-radius: 9px;
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--brand-muted);
    cursor: pointer;
    transition: all 0.25s cubic-bezier(.4,0,.2,1);
}
.segment-btn:hover { color: var(--brand-ink); }
.segment-btn.active {
    background: var(--brand-yellow);
    color: #000000;
    box-shadow: 0 3px 10px rgba(255, 204, 0, 0.35);
    transform: translateY(-1px);
}
body.dark-mode .duration-segmented-control { background: #141414; border-color: var(--brand-border-dark); }
body.dark-mode .segment-btn { color: #94a3b8; }
body.dark-mode .segment-btn:hover { color: #f1f5f9; }
body.dark-mode .segment-btn.active { color: #000000; }

/* ---- DateTime preview banner ---- */
.preview-banner {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: var(--brand-yellow-soft);
    border: 1px solid rgba(255, 204, 0, 0.35);
    border-radius: 14px;
    padding: 0.85rem 1rem;
    animation: bannerSlide 0.3s ease;
}
@keyframes bannerSlide {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}
.preview-item { display: flex; align-items: center; gap: 0.6rem; flex: 1; min-width: 0; }
.preview-item i { font-size: 1.1rem; }
.preview-item .label {
    display: block;
    font-size: 0.65rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--brand-muted);
    font-weight: 700;
}
.preview-item strong {
    font-size: 0.8rem;
    color: var(--brand-ink);
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.preview-divider { width: 1px; align-self: stretch; background: rgba(255, 204, 0, 0.4); }
body.dark-mode .preview-banner { background: rgba(255, 204, 0, 0.07); border-color: rgba(255, 204, 0, 0.25); }
body.dark-mode .preview-item strong { color: #f1f5f9; }

/* ---- Searchable dropdown menus (car type, member select, etc.) ---- */
.glass-dropdown {
    border: 1px solid var(--brand-border) !important;
    background-color: #ffffff !important;
    padding: 0.6rem;
    transform-origin: top center;
}
body.dark-mode .glass-dropdown {
    background-color: #141414 !important;
    border-color: var(--brand-border-dark) !important;
}
.custom-dropdown-item {
    color: var(--brand-ink);
    font-weight: 600;
    border-radius: 8px;
    padding: 0.5rem 0.7rem;
    font-size: 0.85rem;
    transition: background-color 0.15s ease, color 0.15s ease, padding-left 0.15s ease;
    cursor: pointer;
}
.custom-dropdown-item:hover,
.custom-dropdown-item:focus {
    background-color: var(--brand-yellow-soft);
    color: #8a6a00;
    padding-left: 0.9rem;
}
body.dark-mode .custom-dropdown-item { color: #e2e8f0; }
body.dark-mode .custom-dropdown-item:hover,
body.dark-mode .custom-dropdown-item:focus {
    background-color: rgba(255, 204, 0, 0.1);
    color: var(--brand-yellow);
}
#carTypeDropdownBtn { transition: border-color 0.2s ease, box-shadow 0.2s ease; }
#carTypeDropdownBtn[aria-expanded="true"] {
    border-color: var(--brand-yellow) !important;
    box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.2) !important;
}
#carTypeDropdownBtn .bi-chevron-down { transition: transform 0.25s ease; }
#carTypeDropdownBtn[aria-expanded="true"] .bi-chevron-down { transform: rotate(180deg); }
.custom-scroll { scrollbar-width: thin; }
.max-h-48 { max-height: 12rem; }

/* ---- Site-wide dropdown open animation ---- */
.dropdown-menu.show {
    animation: dropdownPop 0.2s cubic-bezier(.34,1.56,.64,1);
}
@keyframes dropdownPop {
    from { opacity: 0; transform: scale(0.96) translateY(-6px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

/* ---- Pricing summary card ---- */
.pricing-card {
    background: linear-gradient(160deg, #fffdf5, #ffffff);
    border: 1px solid rgba(255, 204, 0, 0.35);
    border-radius: 16px;
    padding: 1.1rem 1.25rem;
}
.pricing-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; color: var(--brand-muted); padding: 0.25rem 0; }
.pricing-row strong { color: var(--brand-ink); font-weight: 700; transition: color 0.2s ease; }

/* Color-code each row so the sign/meaning of each amount is obvious at a glance */
.pricing-row.text-danger,
.pricing-row.text-danger strong { color: #dc2626 !important; }
.pricing-row.text-success,
.pricing-row.text-success strong { color: #16a34a !important; }

.pricing-divider { height: 1px; background: rgba(0, 0, 0, 0.06); margin: 0.6rem 0; }
.pricing-total-row { display: flex; align-items: flex-end; justify-content: space-between; }
.total-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 800; color: var(--brand-muted); }
.duration-badge {
    display: inline-block;
    margin-top: 2px;
    font-size: 0.7rem;
    font-weight: 700;
    background: var(--brand-yellow-soft);
    color: #8a6a00;
    padding: 2px 8px;
    border-radius: 20px;
}
.total-amount { color: #b38a00; font-weight: 800; transition: transform 0.2s ease; display: inline-block; }
.total-amount.pulse { animation: totalPulse 0.4s ease; }
@keyframes totalPulse {
    0% { transform: scale(1); }
    40% { transform: scale(1.08); color: #b38a00; }
    100% { transform: scale(1); }
}
body.dark-mode .pricing-card { background: linear-gradient(160deg, #1a1600, #141414); border-color: rgba(255, 204, 0, 0.2); }
body.dark-mode .pricing-row { color: #94a3b8; }
body.dark-mode .pricing-row strong { color: #f1f5f9; }
body.dark-mode .pricing-row.text-danger,
body.dark-mode .pricing-row.text-danger strong { color: #f87171 !important; }
body.dark-mode .pricing-row.text-success,
body.dark-mode .pricing-row.text-success strong { color: #4ade80 !important; }
body.dark-mode .pricing-divider { background: rgba(255, 255, 255, 0.08); }
body.dark-mode .total-label { color: #94a3b8; }
body.dark-mode .total-amount { color: var(--brand-yellow); }
body.dark-mode .duration-badge { background: rgba(255, 204, 0, 0.15); color: var(--brand-yellow); }

/* ---- Footer buttons ---- */
.btn-cancel-custom {
    padding: 0.6rem 1.4rem;
    border-radius: 12px;
    border: 1px solid var(--brand-border);
    background: #ffffff;
    color: var(--brand-ink);
    font-weight: 600;
    font-size: 0.875rem;
    transition: all 0.2s ease;
}
.btn-cancel-custom:hover { background: #f1f5f9; border-color: #cbd5e1; }
body.dark-mode .btn-cancel-custom { background: #171717; border-color: var(--brand-border-dark); color: #e2e8f0; }
body.dark-mode .btn-cancel-custom:hover { background: #27272a; }

.btn-brand-submit {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.6rem 1.5rem;
    border-radius: 12px;
    border: none;
    background: var(--brand-yellow);
    color: #000000;
    font-weight: 700;
    font-size: 0.875rem;
    box-shadow: 0 4px 12px rgba(255, 204, 0, 0.3);
    transition: all 0.2s ease;
}
.btn-brand-submit i { transition: transform 0.2s ease; }
.btn-brand-submit:hover {
    background: var(--brand-yellow-hover);
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(255, 204, 0, 0.4);
}
.btn-brand-submit:hover i { transform: translateX(3px); }
.btn-brand-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

/* ---- Accessibility: respect reduced motion ---- */
@media (prefers-reduced-motion: reduce) {
    #manualBookingModal .modal-dialog,
    .modal-icon-badge,
    .fade-switch,
    .preview-banner,
    .dropdown-menu.show,
    .total-amount.pulse,
    .file-upload-card.has-file .file-upload-label,
    .btn-close-custom,
    .segment-btn {
        animation: none !important;
        transition: none !important;
    }
}

</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 col-md-auto p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-4">
                
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4 header-title-wrapper">
                    <div>
                        <h3 class="fw-bold mb-0">Manual <span class="text-brand-yellow">Bookings</span></h3>
                        <p class="text-muted small mb-0">Manage walk-in reservations and offline bookings seamlessly.</p>
                    </div>
                    <button type="button" class="btn btn-brand shadow-sm d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#manualBookingModal">
                        <i class="bi bi-plus-circle-fill me-2"></i>New Walk-in Booking
                    </button>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-brand-yellow text-dark">
                                    <i class="bi bi-journal-bookmark-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($stats['total_bookings'] ?? 0) ?></div>
                                    <div class="stat-label">Total Walk-ins</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-calendar-check-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($stats['confirmed'] ?? 0) ?></div>
                                    <div class="stat-label">Confirmed</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($stats['completed'] ?? 0) ?></div>
                                    <div class="stat-label">Completed</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                                    <i class="bi bi-x-circle-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($stats['cancelled'] ?? 0) ?></div>
                                    <div class="stat-label">Cancelled</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex mb-3">
                    <ul class="nav nav-status-pills shadow-sm">
                        <li class="nav-item"><a class="nav-link <?= $filter == 'All' ? 'active' : '' ?>" href="?filter=All">All Bookings</a></li>
                        <li class="nav-item"><a class="nav-link <?= $filter == 'Confirmed' ? 'active' : '' ?>" href="?filter=Confirmed">Confirmed</a></li>
                        <li class="nav-item"><a class="nav-link <?= $filter == 'Completed' ? 'active' : '' ?>" href="?filter=Completed">Completed</a></li>
                        <li class="nav-item"><a class="nav-link <?= $filter == 'Cancelled' ? 'active' : '' ?>" href="?filter=Cancelled">Cancelled</a></li>
                    </ul>
                </div>

                <div class="card custom-control-bar shadow-sm mb-4">
                    <div class="card-body p-3 d-flex flex-row justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-2 small text-muted font-weight-bold">
                            <span>Show</span>
                            <select id="manualEntryLimitSelect" class="entry-limiter-select">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                            <span>entries</span>
                        </div>
                        
                        <div class="search-input-wrapper">
                            <i class="bi bi-search"></i>
                            <input type="text" id="unifiedManualSearch" placeholder="Search reservations...">
                        </div>
                    </div>
                </div>

                <!-- Create Booking Modal -->
                <div class="modal fade modal-animated" id="manualBookingModal" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content glass-card border-0 shadow-lg">
                            
                            <!-- Modal Header -->
                            <div class="modal-header border-0 p-4 pb-2 align-items-center">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="modal-icon-badge">
                                        <i class="bi bi-person-plus-fill"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold mb-0 modal-title-text">Create Walk-in Booking</h5>
                                        <p class="text-muted small mb-0">Register a new reservation or walk-in client</p>
                                    </div>
                                </div>
                                <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>

                            <!-- Modal Body -->
                            <div class="modal-body p-4 pt-3">
                                <form action="process/booking_actions.php" method="POST" enctype="multipart/form-data" id="manualBookingForm">
                                    <input type="hidden" name="add_manual_booking" value="1">

                                    <div class="row g-3">
                                        <!-- Customer Type Selection -->
                                        <div class="col-12">
                                            <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Customer Type</label>
                                            <div class="input-group-custom">
                                                <span class="input-icon"><i class="bi bi-people"></i></span>
                                                <select id="userType" name="customer_type" class="form-select custom-select" onchange="toggleCustomerType()" required>
                                                    <option value="registered">Registered Member</option>
                                                    <option value="guest">Guest / Walk-in</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Registered Member Input (searchable) -->
                                        <div id="registeredInput" class="col-12 fade-switch active">
                                            <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Select Member</label>
                                            <div class="dropdown">
                                                <button class="form-select custom-select text-start d-flex justify-content-between align-items-center w-100"
                                                        type="button"
                                                        id="memberDropdownBtn"
                                                        data-bs-toggle="dropdown"
                                                        data-bs-auto-close="outside"
                                                        aria-expanded="false">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <i class="bi bi-person-badge text-muted"></i>
                                                        <span id="selectedMemberLabel" class="text-muted">-- Select Member --</span>
                                                    </span>
                                                    <i class="bi bi-chevron-down opacity-50"></i>
                                                </button>

                                                <div class="dropdown-menu w-100 p-2 glass-dropdown shadow-lg rounded-4" aria-labelledby="memberDropdownBtn">
                                                    <div class="p-1 mb-2">
                                                        <div class="input-group-custom compact">
                                                            <span class="input-icon"><i class="bi bi-search"></i></span>
                                                            <input type="text" class="form-control custom-input py-1" id="memberSearchInput" placeholder="Search member name...">
                                                        </div>
                                                    </div>
                                                    <div id="memberOptionsList" class="custom-scroll max-h-48 overflow-y-auto">
                                                        <a class="dropdown-item custom-dropdown-item" data-value="">-- Select Member --</a>
                                                        <?php foreach ($customers as $cus): ?>
                                                            <a class="dropdown-item custom-dropdown-item" data-value="<?= $cus['id'] ?>"><?= htmlspecialchars($cus['name']) ?></a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div id="memberNoResults" class="text-muted small text-center py-2 d-none">No members found.</div>
                                                </div>
                                            </div>
                                            <input type="hidden" name="user_id" id="userIdSelect" value="" required>
                                        </div>

                                        <!-- Guest Inputs -->
                                        <div id="guestInput" class="col-12 d-none fade-switch">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Guest Full Name</label>
                                                    <div class="input-group-custom">
                                                        <span class="input-icon"><i class="bi bi-person"></i></span>
                                                        <input type="text" name="guest_name" id="guestNameInput" class="form-control custom-input" placeholder="Enter Full Name">
                                                    </div>
                                                </div>
                                                <div class="col-12 col-sm-6">
                                                    <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Email</label>
                                                    <div class="input-group-custom">
                                                        <span class="input-icon"><i class="bi bi-envelope"></i></span>
                                                        <input type="email" name="guest_email" id="guestEmailInput" class="form-control custom-input" placeholder="email@gmail.com">
                                                    </div>
                                                </div>
                                                <div class="col-12 col-sm-6">
                                                    <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Phone</label>
                                                    <div class="input-group-custom">
                                                        <span class="input-icon"><i class="bi bi-telephone"></i></span>
                                                        <input type="text" name="guest_phone" id="guestPhoneInput" class="form-control custom-input" placeholder="09xxxxxxxxx">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Documents Upload -->
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label text-uppercase fs-xs fw-bold tracking-wider text-danger">Primary ID *</label>
                                            <div class="file-upload-card">
                                                <input type="file" name="primary_id" id="primaryIdInput" class="file-input-hidden" accept="image/*, .pdf" required>
                                                <label for="primaryIdInput" class="file-upload-label">
                                                    <i class="bi bi-cloud-arrow-up fs-4"></i>
                                                    <span class="file-title">Upload Primary ID</span>
                                                    <span class="file-subtitle">JPG, PNG, or PDF</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-12 col-sm-6">
                                            <label class="form-label text-uppercase fs-xs fw-bold tracking-wider text-muted">Secondary ID</label>
                                            <div class="file-upload-card">
                                                <input type="file" name="secondary_id" id="secondaryIdInput" class="file-input-hidden" accept="image/*, .pdf">
                                                <label for="secondaryIdInput" class="file-upload-label">
                                                    <i class="bi bi-cloud-arrow-up fs-4"></i>
                                                    <span class="file-title">Upload Secondary ID</span>
                                                    <span class="file-subtitle">Optional</span>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label text-uppercase fs-xs fw-bold tracking-wider text-muted">Proof of Billing *</label>
                                            <div class="file-upload-card">
                                                <input type="file" name="proof_of_billing" id="proofBillingInput" class="file-input-hidden" accept="image/*, .pdf" required>
                                                <label for="proofBillingInput" class="file-upload-label">
                                                    <i class="bi bi-file-earmark-text fs-4"></i>
                                                    <span class="file-title">Upload Proof of Billing</span>
                                                    <span class="file-subtitle">Utility bill, statement, etc.</span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Duration Selector -->
                                        <div class="col-12">
                                            <label class="form-label text-uppercase fs-xs fw-bold tracking-wider text-muted">Quick Duration</label>
                                            <div class="duration-segmented-control">
                                                <button type="button" class="segment-btn" id="btn10h" onclick="setDuration(10, this)">10 Hours</button>
                                                <button type="button" class="segment-btn" id="btn24h" onclick="setDuration(24, this)">24 Hours</button>
                                                <button type="button" class="segment-btn active" id="btnCustom" onclick="setDuration('custom', this)">Custom</button>
                                            </div>
                                        </div>

                                        <!-- DateTime Preview -->
                                        <div class="col-12">
                                            <div class="preview-banner" id="dateTimePreview" style="display: none;">
                                                <div class="preview-item">
                                                    <i class="bi bi-calendar-minus-fill text-brand-yellow"></i>
                                                    <div>
                                                        <span class="label">Pickup</span>
                                                        <strong id="previewPickup">--</strong>
                                                    </div>
                                                </div>
                                                <div class="preview-divider"></div>
                                                <div class="preview-item">
                                                    <i class="bi bi-calendar-plus-fill text-brand-yellow"></i>
                                                    <div>
                                                        <span class="label">Return</span>
                                                        <strong id="previewReturn">--</strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Pick / Return Local Inputs -->
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Pickup Date & Time</label>
                                            <input type="datetime-local" id="pickupDatetime" class="form-control custom-input" required onchange="syncDateTimeValues()">
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Return Date & Time</label>
                                            <input type="datetime-local" id="returnDatetime" class="form-control custom-input" required onchange="syncDateTimeValues()">
                                        </div>

                                        <input type="hidden" name="start_date" id="startDate">
                                        <input type="hidden" name="pickup_time" id="pickupTime">
                                        <input type="hidden" name="end_date" id="endDate">
                                        <input type="hidden" name="return_time" id="returnTime">

                                        <!-- Searchable Car Filter Dropdown -->
                                        <div class="col-12">
                                            <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Vehicle Type & Selection</label>
                                            <div class="dropdown mb-2">
                                                <button class="form-select custom-select text-start d-flex justify-content-between align-items-center w-100" 
                                                        type="button" 
                                                        id="carTypeDropdownBtn" 
                                                        data-bs-toggle="dropdown" 
                                                        data-bs-auto-close="outside" 
                                                        aria-expanded="false">
                                                    <span id="selectedCarTypeLabel" class="text-muted">All Types / Categories</span>
                                                    <i class="bi bi-chevron-down opacity-50"></i>
                                                </button>
                                                
                                                <div class="dropdown-menu w-100 p-2 car-type-dropdown glass-dropdown shadow-lg rounded-4" aria-labelledby="carTypeDropdownBtn">
                                                    <div class="p-1 mb-2">
                                                        <div class="input-group-custom compact">
                                                            <span class="input-icon"><i class="bi bi-search"></i></span>
                                                            <input type="text" class="form-control custom-input py-1" id="carTypeSearchInput" placeholder="Search vehicle type...">
                                                        </div>
                                                    </div>
                                                    <div id="carTypeOptionsList" class="custom-scroll max-h-48 overflow-y-auto">
                                                        <a class="dropdown-item custom-dropdown-item" data-value="">All Types / Categories</a>
                                                        <a class="dropdown-item custom-dropdown-item" data-value="Sedan">Sedan</a>
                                                        <a class="dropdown-item custom-dropdown-item" data-value="SUV">SUV</a>
                                                        <a class="dropdown-item custom-dropdown-item" data-value="MPV / Van">MPV / Van</a>
                                                        <a class="dropdown-item custom-dropdown-item" data-value="Hatchback">Hatchback</a>
                                                        <a class="dropdown-item custom-dropdown-item" data-value="Pickup Truck">Pickup Truck</a>
                                                        <a class="dropdown-item custom-dropdown-item" data-value="Crossover">Crossover</a>
                                                        <a class="dropdown-item custom-dropdown-item" data-value="Coupe">Coupe</a>
                                                        <a class="dropdown-item custom-dropdown-item" data-value="Convertible">Convertible</a>
                                                        <a class="dropdown-item custom-dropdown-item" data-value="Luxury / Executive">Luxury / Executive</a>
                                                        <a class="dropdown-item custom-dropdown-item" data-value="Electric / Hybrid">Electric / Hybrid</a>
                                                        <div class="dropdown-divider border-separator"></div>
                                                        <a class="dropdown-item custom-dropdown-item text-brand-yellow fw-bold" data-value="Others">Others (Custom)</a>
                                                    </div>
                                                </div>
                                            </div>

                                            <input type="hidden" id="typeFilter" value="">
                                            
                                            <div class="input-group-custom">
                                                <span class="input-icon"><i class="bi bi-car-front-fill"></i></span>
                                                <select name="car_id" id="carSelect" class="form-select custom-select" onchange="calculateTieredTotal()" required>
                                                    <option value="">-- Select a Car --</option>
                                                    <?php if (!empty($available_cars)): ?>
                                                        <?php foreach ($available_cars as $car): ?>
                                                            <option value="<?= $car['id'] ?>" 
                                                                    data-type="<?= htmlspecialchars($car['type'] ?? '') ?>"
                                                                    data-busy="<?= htmlspecialchars($car['busy_slots'] ?? '') ?>"
                                                                    data-price-10="<?= $car['price_10_hours'] ?? 0 ?>"
                                                                    data-price-12="<?= $car['price_12_hours'] ?? 0 ?>"
                                                                    data-price-24="<?= $car['price_24_hours'] ?? 0 ?>"
                                                                    data-ext-1-6="<?= $car['ext_price_1_6'] ?? 0 ?>"
                                                                    data-ext-7-10="<?= $car['ext_price_7_10'] ?? 0 ?>"
                                                                    data-ext-11-12="<?= $car['ext_price_11_12'] ?? 0 ?>"
                                                                    data-ext-13-24="<?= $car['ext_price_13_24'] ?? 0 ?>">
                                                                <?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['plate_number'] . ')') ?> 
                                                                — ₱<?= number_format($car['price_24_hours'] ?? 0, 2) ?>/24h
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Pricing Adjustments -->
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Discount (₱)</label>
                                            <div class="input-group-custom">
                                                <span class="input-icon">₱</span>
                                                <input type="number" name="discount_price" id="discount_priceInput" class="form-control custom-input" value="0" min="0" oninput="calculateTieredTotal()">
                                            </div>
                                        </div>

                                        <div class="col-12 col-sm-6">
                                            <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Down Payment (₱)</label>
                                            <div class="input-group-custom">
                                                <span class="input-icon">₱</span>
                                                <input type="number" name="down_payment" id="downPaymentInput" class="form-control custom-input" value="0" min="0" oninput="calculateTieredTotal()">
                                            </div>
                                        </div>

                                        <!-- Real-time Cost Calculation Card -->
                                        <div class="col-12">
                                            <div class="pricing-card">
                                                <div class="pricing-row">
                                                    <span>Base Rental Rate</span>
                                                    <strong id="displayBasePrice">₱0.00</strong>
                                                </div>
                                                <div class="pricing-row text-danger">
                                                    <span>Discount Deduction</span>
                                                    <strong id="displayDiscount">-₱0.00</strong>
                                                </div>
                                                <div class="pricing-row text-success">
                                                    <span>Down Payment Paid</span>
                                                    <strong id="displayDownPayment">-₱0.00</strong>
                                                </div>
                                                <div class="pricing-divider"></div>
                                                <div class="pricing-total-row">
                                                    <div>
                                                        <span class="total-label">Remaining Balance</span>
                                                        <div class="duration-badge" id="durationDisplay">0 Hours</div>
                                                    </div>
                                                    <h3 class="total-amount mb-0" id="displayTotal">₱0.00</h3>
                                                </div>
                                                <input type="hidden" name="total_price" id="totalPriceInput" value="0">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="d-flex justify-content-end gap-3 mt-4 pt-2">
                                        <button type="button" class="btn btn-cancel-custom" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="add_manual_booking" class="btn btn-brand-submit" id="confirmBookingBtn">
                                            <span>Confirm Booking</span>
                                            <i class="bi bi-arrow-right-short fs-5"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                        </div>
                    </div>
                </div>

                <!-- Bookings Display Table Engine -->
                <div class="bookings-display-container">
                    <div class="d-block d-md-none mobile-cards-wrapper">
                        <?php if (empty($bookings)): ?>
                            <div class="card border-0 shadow-sm p-5 text-center text-muted" style="border-radius: var(--card-radius);">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No manual bookings found.
                            </div>
                        <?php else: ?>
                            <?php foreach ($bookings as $b): ?>
                                <div class="mobile-booking-card js-searchable-booking">
                                    <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                                        <div>
                                            <div class="fw-bold text-dark">
                                                <?php if (!empty($b['guest_name'])): ?>
                                                    <?= htmlspecialchars($b['guest_name']) ?>
                                                    <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size:9px">WALK-IN</span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($b['member_name'] ?? 'N/A') ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($b['gmail'] ?? 'No Email') ?></div>
                                        </div>
                                        <div>
                                            <span class="status-badge status-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-2">
                                        <div class="col-6 border-end">
                                            <span class="text-uppercase text-muted d-block" style="font-size: 0.65rem; font-weight:700;">Vehicle</span>
                                            <div class="fw-bold" style="font-size: 12px;"><?= htmlspecialchars($b['brand']) ?> <?= htmlspecialchars($b['model']) ?></div>
                                            <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($b['plate_number']) ?></div>
                                        </div>
                                        <div class="col-6 ps-2">
                                            <span class="text-uppercase text-muted d-block" style="font-size: 0.65rem; font-weight:700;">Pricing Data</span>
                                            <div class="fw-bold text-dark" style="font-size: 12px;">
                                                ₱<?= number_format($b['total_price'] + $b['discount_price'] + $b['down_payment'], 2) ?>
                                            </div>
                                            <div class="text-brand-yellow fw-bold" style="font-size: 13px; border-top: 1px solid #dee2e6; margin-top: 4px; padding-top: 2px;">
                                                Bal: ₱<?= number_format($b['total_price'], 2) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-light p-2 rounded-3 mb-2 border-start border-3 border-warning">
                                        <span class="text-uppercase text-muted d-block mb-1" style="font-size: 0.65rem; font-weight:700;">Schedule</span>
                                        <div style="font-size:11px;" class="text-dark fw-medium">
                                            <i class="bi bi-calendar-event me-1 text-muted"></i><?= date('M d, Y', strtotime($b['start_date'])) ?> to <?= date('M d, Y', strtotime($b['end_date'])) ?>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                        <div class="d-flex gap-1">
                                            <?php if (!empty($b['primary_id_path'])): ?>
                                                <a href="../../public/assets/images/ids/<?= $b['primary_id_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-2 py-1" style="font-size:10px;">ID 1</a>
                                            <?php endif; ?>
                                            <?php if (!empty($b['proof_billing_path'])): ?>
                                                <a href="../../public/assets/images/ids/<?= $b['proof_billing_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-2 py-1" style="font-size:10px;">PROOF</a>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <?php if ($b['status'] == 'Completed' || $b['status'] == 'Cancelled'): ?>
                                                <button class="btn btn-sm btn-light border text-muted" disabled style="font-size: 11px;"><i class="bi bi-lock-fill me-1"></i>Locked</button>
                                            <?php else: ?>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">Actions</button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                        <li><a class="dropdown-item text-primary" href="#" data-bs-toggle="modal" data-bs-target="#editBookingModal<?= $b['id'] ?>"><i class="bi bi-pencil-square me-2"></i>Edit</a></li>
                                                        <?php if ($b['status'] == 'Confirmed'): ?>
                                                            <li><a class="dropdown-item text-success" href="process/booking_actions.php?id=<?= $b['id'] ?>&status=Completed&source=manual&filter=<?= $filter ?>"><i class="bi bi-check-circle me-2"></i>Complete</a></li>
                                                        <?php endif; ?>
                                                        <li><a class="dropdown-item text-danger" href="process/booking_actions.php?id=<?= $b['id'] ?>&status=Cancelled&source=manual&filter=<?= $filter ?>"><i class="bi bi-x-circle me-2"></i>Cancel</a></li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-none d-md-block desktop-table-card">
                        <div class="card border-0 shadow-sm table-card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table id="bookingsTable" class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="border-0 px-3 py-3">Customer</th>
                                                <th class="border-0 py-3">Vehicle</th>
                                                <th class="border-0 py-3">Schedule</th>
                                                <th class="border-0 py-3">Verification Files</th>
                                                <th class="border-0 py-3">Pricing Data</th>
                                                <th class="border-0 py-3 text-center">Status</th>
                                                <th class="border-0 pe-3 py-3 text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small">
                                            <?php if (empty($bookings)): ?>
                                                <tr><td colspan="7" class="text-center py-5 text-muted">No manual bookings found.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($bookings as $b): ?>
                                                    <?php
                                                        $__displayName = !empty($b['guest_name']) ? $b['guest_name'] : ($b['member_name'] ?? 'N/A');
                                                        $__nameParts = preg_split('/\s+/', trim($__displayName));
                                                        $__initials = strtoupper(mb_substr($__nameParts[0] ?? '', 0, 1) . (isset($__nameParts[1]) ? mb_substr($__nameParts[1], 0, 1) : ''));
                                                        if ($__initials === '') $__initials = '?';
                                                    ?>
                                                    <tr class="js-searchable-booking">
                                                        <td class="px-3 py-3">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="customer-avatar"><?= htmlspecialchars($__initials) ?></span>
                                                                <div>
                                                                    <div class="fw-bold text-dark">
                                                                        <?php if (!empty($b['guest_name'])): ?>
                                                                            <?= htmlspecialchars($b['guest_name']) ?><span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size:9px">WALK-IN</span>
                                                                        <?php else: ?>
                                                                            <?= htmlspecialchars($b['member_name'] ?? 'N/A') ?>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="text-muted small"><?= htmlspecialchars($b['gmail'] ?? 'No Email') ?></div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="py-3">
                                                            <div class="fw-semibold"><?= htmlspecialchars($b['brand']) ?> <?= htmlspecialchars($b['model']) ?></div>
                                                            <div class="text-muted small"><?= htmlspecialchars($b['plate_number']) ?></div>
                                                        </td>
                                                        <td class="py-3">
                                                            <div><i class="bi bi-calendar-event me-1 text-muted"></i><?= date('M d', strtotime($b['start_date'])) ?> - <?= date('M d', strtotime($b['end_date'])) ?></div>
                                                            <div class="text-muted small"><i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($b['pickup_time'])) ?> - <?= date('h:i A', strtotime($b['return_time'])) ?></div>
                                                        </td>
                                                        <td class="py-3">
                                                            <div class="d-flex gap-1">
                                                                <?php if (!empty($b['primary_id_path'])): ?>
                                                                    <a href="../../public/assets/images/ids/<?= $b['primary_id_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-2 py-0" style="font-size:11px">ID 1</a>
                                                                <?php endif; ?>
                                                                <?php if (!empty($b['proof_billing_path'])): ?>
                                                                    <a href="../../public/assets/images/ids/<?= $b['proof_billing_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-2 py-0" style="font-size:11px">PROOF</a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td class="py-3">
                                                            <div class="fw-bold text-dark">
                                                                ₱<?= number_format($b['total_price'] + $b['discount_price'] + $b['down_payment'], 2) ?>
                                                            </div>
                                                            <div class="text-brand-yellow fw-bold" style="font-size:12px;">
                                                                Bal: ₱<?= number_format($b['total_price'], 2) ?>
                                                            </div>
                                                        </td>
                                                        <td class="py-3 text-center">
                                                            <span class="status-badge status-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span>
                                                        </td>
                                                        <td class="pe-3 py-3 text-end">
                                                            <?php if ($b['status'] == 'Completed' || $b['status'] == 'Cancelled'): ?>
                                                                <span class="text-muted small"><i class="bi bi-lock-fill"></i> Locked</span>
                                                            <?php else: ?>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">Actions</button>
                                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                                        <li><a class="dropdown-item text-primary" href="#" data-bs-toggle="modal" data-bs-target="#editBookingModal<?= $b['id'] ?>"><i class="bi bi-pencil-square me-2"></i>Edit</a></li>
                                                                        <?php if ($b['status'] == 'Confirmed'): ?>
                                                                            <li><a class="dropdown-item text-success" href="process/booking_actions.php?id=<?= $b['id'] ?>&status=Completed&source=manual&filter=<?= $filter ?>"><i class="bi bi-check-circle me-2"></i>Complete</a></li>
                                                                        <?php endif; ?>
                                                                        <li><a class="dropdown-item text-danger" href="process/booking_actions.php?id=<?= $b['id'] ?>&status=Cancelled&source=manual&filter=<?= $filter ?>"><i class="bi bi-x-circle me-2"></i>Cancel</a></li>
                                                                    </ul>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>

<?php foreach ($bookings as $b): ?>
    <div class="modal fade modal-animated" id="editBookingModal<?= $b['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content glass-card border-0 shadow-lg">

                <!-- Modal Header -->
                <div class="modal-header border-0 p-4 pb-2 align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div class="modal-icon-badge">
                            <i class="bi bi-pencil-square"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 modal-title-text">Edit Booking #BK-<?= $b['id'] ?></h5>
                            <p class="text-muted small mb-0">Update reservation details</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close-custom" data-bs-dismiss="modal" aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <form method="POST" action="process/booking_actions.php" enctype="multipart/form-data" id="editBookingForm_<?= $b['id'] ?>">
                    <div class="modal-body p-4 pt-3">
                        <input type="hidden" name="update_booking" value="1">
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <input type="hidden" name="source" value="manual">
                        <input type="hidden" name="filter" value="<?= $filter ?>">

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Customer Type</label>
                                <div class="input-group-custom">
                                    <span class="input-icon"><i class="bi bi-people"></i></span>
                                    <select id="userType_<?= $b['id'] ?>" name="customer_type" class="form-select custom-select" onchange="toggleEditCustomerType(<?= $b['id'] ?>)" required>
                                        <option value="registered" <?= (!empty($b['user_id'])) ? 'selected' : '' ?>>Registered Member</option>
                                        <option value="guest" <?= (empty($b['user_id'])) ? 'selected' : '' ?>>Guest / Walk-in</option>
                                    </select>
                                </div>
                            </div>

                            <div id="registeredInput_<?= $b['id'] ?>" class="col-12 fade-switch <?= (empty($b['user_id'])) ? 'd-none' : '' ?>">
                                <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Select Member</label>
                                <div class="input-group-custom">
                                    <span class="input-icon"><i class="bi bi-person-badge"></i></span>
                                    <select name="user_id" id="userIdSelect_<?= $b['id'] ?>" class="form-select custom-select" <?= (!empty($b['user_id'])) ? 'required' : '' ?>>
                                        <option value="">-- Select Member --</option>
                                        <?php foreach ($customers as $cus): ?>
                                            <option value="<?= $cus['id'] ?>" <?= ($cus['id'] == $b['user_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cus['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div id="guestInput_<?= $b['id'] ?>" class="col-12 fade-switch <?= (!empty($b['user_id'])) ? 'd-none' : '' ?>">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Guest Full Name</label>
                                        <div class="input-group-custom">
                                            <span class="input-icon"><i class="bi bi-person"></i></span>
                                            <input type="text" name="guest_name" id="guestNameInput_<?= $b['id'] ?>" class="form-control custom-input" value="<?= htmlspecialchars($b['guest_name'] ?? '') ?>" placeholder="Enter Full Name" <?= (empty($b['user_id'])) ? 'required' : '' ?>>
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Email</label>
                                        <div class="input-group-custom">
                                            <span class="input-icon"><i class="bi bi-envelope"></i></span>
                                            <input type="email" name="guest_email" id="guestEmailInput_<?= $b['id'] ?>" class="form-control custom-input" value="<?= htmlspecialchars($b['gmail'] ?? '') ?>" placeholder="email@gmail.com">
                                        </div>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Phone</label>
                                        <div class="input-group-custom">
                                            <span class="input-icon"><i class="bi bi-telephone"></i></span>
                                            <input type="text" name="guest_phone" id="guestPhoneInput_<?= $b['id'] ?>" class="form-control custom-input" value="<?= htmlspecialchars($b['phone_number'] ?? '') ?>" placeholder="09xxxxxxxxx">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label text-uppercase fs-xs fw-bold tracking-wider text-muted">Primary ID <span class="fw-normal text-muted text-lowercase">(leave blank to keep old)</span></label>
                                <div class="file-upload-card">
                                    <input type="file" name="primary_id" id="primaryIdInput_<?= $b['id'] ?>" class="file-input-hidden" accept="image/*, .pdf">
                                    <label for="primaryIdInput_<?= $b['id'] ?>" class="file-upload-label">
                                        <i class="bi bi-cloud-arrow-up fs-4"></i>
                                        <span class="file-title">Upload Primary ID</span>
                                        <span class="file-subtitle">JPG, PNG, or PDF</span>
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label text-uppercase fs-xs fw-bold tracking-wider text-muted">Secondary ID <span class="fw-normal text-muted text-lowercase">(leave blank to keep old)</span></label>
                                <div class="file-upload-card">
                                    <input type="file" name="secondary_id" id="secondaryIdInput_<?= $b['id'] ?>" class="file-input-hidden" accept="image/*, .pdf">
                                    <label for="secondaryIdInput_<?= $b['id'] ?>" class="file-upload-label">
                                        <i class="bi bi-cloud-arrow-up fs-4"></i>
                                        <span class="file-title">Upload Secondary ID</span>
                                        <span class="file-subtitle">Optional</span>
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-uppercase fs-xs fw-bold tracking-wider text-muted">Proof of Billing <span class="fw-normal text-muted text-lowercase">(leave blank to keep old)</span></label>
                                <div class="file-upload-card">
                                    <input type="file" name="proof_of_billing" id="proofBillingInput_<?= $b['id'] ?>" class="file-input-hidden" accept="image/*, .pdf">
                                    <label for="proofBillingInput_<?= $b['id'] ?>" class="file-upload-label">
                                        <i class="bi bi-file-earmark-text fs-4"></i>
                                        <span class="file-title">Upload Proof of Billing</span>
                                        <span class="file-subtitle">Utility bill, statement, etc.</span>
                                    </label>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Pickup Date & Time</label>
                                <input type="datetime-local" name="pickup_datetime" id="pickupDatetime_<?= $b['id'] ?>" class="form-control custom-input" value="<?= date('Y-m-d\TH:i', strtotime($b['start_date'] . ' ' . $b['pickup_time'])) ?>" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Return Date & Time</label>
                                <input type="datetime-local" name="return_datetime" id="returnDatetime_<?= $b['id'] ?>" class="form-control custom-input" value="<?= date('Y-m-d\TH:i', strtotime($b['end_date'] . ' ' . $b['return_time'])) ?>" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Select Car</label>
                                <div class="input-group-custom">
                                    <span class="input-icon"><i class="bi bi-car-front-fill"></i></span>
                                    <select name="car_id" id="carSelect_<?= $b['id'] ?>" class="form-select custom-select" required>
                                        <?php
                                        $cars_sql = "SELECT id, brand, model, plate_number FROM cars ORDER BY brand ASC";
                                        $cars_res = mysqli_query($conn, $cars_sql);
                                        while ($car_row = mysqli_fetch_assoc($cars_res)) {
                                            $selected = ($car_row['id'] == $b['car_id']) ? 'selected' : '';
                                            echo '<option value="' . $car_row['id'] . '" ' . $selected . '>' . htmlspecialchars($car_row['brand'] . ' ' . $car_row['model'] . ' - ' . $car_row['plate_number']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Discount (₱)</label>
                                <div class="input-group-custom">
                                    <span class="input-icon">₱</span>
                                    <input type="number" name="discount_price" class="form-control custom-input" value="<?= floatval($b['discount_price']) ?>" min="0">
                                </div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label text-uppercase fs-xs fw-bold tracking-wider">Down Payment (₱)</label>
                                <div class="input-group-custom">
                                    <span class="input-icon">₱</span>
                                    <input type="number" name="down_payment" class="form-control custom-input" value="<?= floatval($b['down_payment']) ?>" min="0">
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="preview-banner mb-0">
                                    <div class="preview-item">
                                        <i class="bi bi-info-circle text-brand-yellow"></i>
                                        <div>
                                            <span class="label">Current Saved Total</span>
                                            <strong>₱<?= number_format($b['total_price'], 2) ?></strong>
                                        </div>
                                    </div>
                                    <div class="preview-divider"></div>
                                    <div class="preview-item">
                                        <span class="text-muted small">Total recalculates automatically from the updated dates on submission.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-3 px-4">
                        <button type="button" class="btn btn-cancel-custom" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-brand-submit">
                            <span>Save Changes</span>
                            <i class="bi bi-check2 fs-5"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>



<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
let currentDuration = null;
let currentSelectedDurationMode = 'custom';

function initialize() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hour = String(now.getHours()).padStart(2, '0');
    const minute = String(now.getMinutes()).padStart(2, '0');

    const defaultPickup = `${year}-${month}-${day}T${hour}:${minute}`;
    document.getElementById('pickupDatetime').value = defaultPickup;

    let returnDate = new Date(now);
    returnDate.setHours(returnDate.getHours() + 10);

    let returnYear = returnDate.getFullYear();
    let returnMonth = String(returnDate.getMonth() + 1).padStart(2, '0');
    let returnDay = String(returnDate.getDate()).padStart(2, '0');
    let returnHour = String(returnDate.getHours()).padStart(2, '0');
    let returnMinute = String(returnDate.getMinutes()).padStart(2, '0');

    document.getElementById('returnDatetime').value = `${returnYear}-${returnMonth}-${returnDay}T${returnHour}:${returnMinute}`;

    document.getElementById('pickupDatetime').setAttribute('min', defaultPickup);
    document.getElementById('returnDatetime').setAttribute('min', defaultPickup);

    currentDuration = null; 
    updateAll();
}

function setDuration(hours, element) {
    document.querySelectorAll('.segment-btn, .duration-btn').forEach(btn => btn.classList.remove('active'));
    if (element) element.classList.add('active');
    
    currentSelectedDurationMode = hours;
    
    if (hours !== 'custom') {
        document.getElementById('returnDatetime').readOnly = true;
        applyQuickDurationCalculations();
    } else {
        document.getElementById('returnDatetime').readOnly = false;
        calculateTieredTotal();
    }
}

function updateAll() {
    updatePreview();
    updateHiddenFields();
    calculateDuration();
    filterCarSelectionOptions();
}

function updatePreview() {
    const pickupInput = document.getElementById('pickupDatetime');
    const returnInput = document.getElementById('returnDatetime');
    const previewDiv = document.getElementById('dateTimePreview');
    const previewPickup = document.getElementById('previewPickup');
    const previewReturn = document.getElementById('previewReturn');

    if (pickupInput.value && returnInput.value) {
        previewDiv.style.display = 'flex';
        const pickup = new Date(pickupInput.value);
        const returnDateObj = new Date(returnInput.value);

        previewPickup.innerHTML = pickup.toLocaleString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true
        });
        previewReturn.innerHTML = returnDateObj.toLocaleString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true
        });
    } else {
        previewDiv.style.display = 'none';
    }
}

function updateHiddenFields() {
    const pickupInput = document.getElementById('pickupDatetime');
    const returnInput = document.getElementById('returnDatetime');

    if (pickupInput.value) {
        const [pickupDate, pickupTime] = pickupInput.value.split('T');
        document.getElementById('startDate').value = pickupDate;
        document.getElementById('pickupTime').value = pickupTime;
    }
    if (returnInput.value) {
        const [returnDate, returnTime] = returnInput.value.split('T');
        document.getElementById('endDate').value = returnDate;
        document.getElementById('returnTime').value = returnTime;
    }
}

function calculateDuration() {
    const pickupInput = document.getElementById('pickupDatetime');
    const returnInput = document.getElementById('returnDatetime');
    const durationDisplay = document.getElementById('durationDisplay');
    const confirmBtn = document.getElementById('confirmBookingBtn');

    if (!pickupInput.value || !returnInput.value) {
        if (durationDisplay) durationDisplay.innerHTML = '0 Hours';
        if (confirmBtn) confirmBtn.disabled = true;
        return;
    }

    const pickup = new Date(pickupInput.value);
    const returnDateObj = new Date(returnInput.value);
    
    const now = new Date();
    now.setSeconds(0);
    now.setMilliseconds(0);

    if (isNaN(pickup.getTime()) || isNaN(returnDateObj.getTime())) {
        if (durationDisplay) durationDisplay.innerHTML = '<span class="text-danger">Invalid date/time</span>';
        if (confirmBtn) confirmBtn.disabled = true;
        return;
    }

    if (pickup < now) {
        if (durationDisplay) durationDisplay.innerHTML = '<span class="text-danger">⚠️ Pickup time cannot be in the past!</span>';
        if (confirmBtn) confirmBtn.disabled = true;
        return;
    }

    if (returnDateObj <= pickup) {
        if (durationDisplay) durationDisplay.innerHTML = '<span class="text-danger">⚠️ Return must be after pickup time!</span>';
        if (confirmBtn) confirmBtn.disabled = true;
        return;
    }

    const diffMs = returnDateObj - pickup;
    const diffHours = diffMs / (1000 * 60 * 60);

    if (diffHours < 9.99) {
        if (durationDisplay) durationDisplay.innerHTML = `<span class="text-danger">${diffHours.toFixed(1)} hrs (Min 10h)</span>`;
        if (confirmBtn) confirmBtn.disabled = true;
        return;
    }

    if (durationDisplay) durationDisplay.innerHTML = `${Math.ceil(diffHours)} Hours ✓`;
    if (confirmBtn) confirmBtn.disabled = false;
}

function syncDateTimeValues() {
    updateAll();
    if (currentSelectedDurationMode !== 'custom') {
        applyQuickDurationCalculations();
    } else {
        calculateTieredTotal();
    }
}

function applyQuickDurationCalculations() {
    const pickupVal = document.getElementById('pickupDatetime').value;
    if (!pickupVal) return;
    
    let pickupDate = new Date(pickupVal);
    let targetReturnDate = new Date(pickupDate.getTime() + (parseInt(currentSelectedDurationMode) * 60 * 60 * 1000));
    
    const tzOffset = targetReturnDate.getTimezoneOffset() * 60000;
    const localISOTime = (new Date(targetReturnDate.getTime() - tzOffset)).toISOString().slice(0, 16);
    
    document.getElementById('returnDatetime').value = localISOTime;
    updateAll();
    calculateTieredTotal();
}

function filterCarSelectionOptions() {
    const pickupVal = document.getElementById('pickupDatetime').value;
    const returnVal = document.getElementById('returnDatetime').value;
    const filterValue = document.getElementById('typeFilter').value.toLowerCase();
    const carSelect = document.getElementById('carSelect');
    const options = carSelect.options;
    
    let userStart = pickupVal ? new Date(pickupVal) : null;
    let userEnd = returnVal ? new Date(returnVal) : null;

    for (let i = 0; i < options.length; i++) {
        if (options[i].value === "") continue;
        
        const optType = (options[i].getAttribute('data-type') || '').toLowerCase();
        const busySlots = options[i].getAttribute('data-busy') || '';
        let isBookedConflict = false;
        let baseText = options[i].text.replace(' (NOT AVAILABLE)', '');
        
        if (userStart && userEnd && busySlots !== '') {
            const slots = busySlots.split(',');
            for (let slot of slots) {
                const times = slot.split('|'); 
                if (times.length < 2) continue;
                
                const bookStart = new Date(times[0].trim());
                const bookEnd = new Date(times[1].trim());
                
                if (userStart < bookEnd && userEnd > bookStart) {
                    isBookedConflict = true;
                    break;
                }
            }
        }
        
        const typeMatches = (filterValue === "" || optType === filterValue);
        
        if (typeMatches && !isBookedConflict) {
            options[i].style.display = "";
            options[i].disabled = false;
            options[i].text = baseText;
        } else if (isBookedConflict) {
            options[i].style.display = ""; 
            options[i].disabled = true; 
            options[i].text = baseText + ' (NOT AVAILABLE)';
            if (carSelect.value === options[i].value) {
                carSelect.value = "";
                resetPricingDisplay();
            }
        } else {
            options[i].style.display = "none";
        }
    }
}

function calculateTieredTotal() {
    const pickupVal = document.getElementById('pickupDatetime').value;
    const returnVal = document.getElementById('returnDatetime').value;
    const carSelect = document.getElementById('carSelect');
    const selectedOption = carSelect.options[carSelect.selectedIndex];
    
    if (!pickupVal || !returnVal || !selectedOption || selectedOption.value === "") {
        resetPricingDisplay();
        return;
    }
    
    const start = new Date(pickupVal);
    const end = new Date(returnVal);
    if (end <= start) { resetPricingDisplay(); return; }
    
    const diffMs = end - start;
    const totalHours = Math.ceil(diffMs / (1000 * 60 * 60));
    
    const p10 = parseFloat(selectedOption.getAttribute('data-price-10')) || 0;
    const p12 = parseFloat(selectedOption.getAttribute('data-price-12')) || 0;
    const p24 = parseFloat(selectedOption.getAttribute('data-price-24')) || 0;
    
    const ext1_6 = parseFloat(selectedOption.getAttribute('data-ext-1-6')) || 0;
    const ext7_10 = parseFloat(selectedOption.getAttribute('data-ext-7-10')) || 0;
    const ext11_12 = parseFloat(selectedOption.getAttribute('data-ext-11-12')) || 0;
    const ext13_24 = parseFloat(selectedOption.getAttribute('data-ext-13-24')) || 0;
    
    let basePrice = 0;
    
    if (totalHours <= 10 && p10 > 0) basePrice = p10;
    else if (totalHours <= 12 && p12 > 0) basePrice = p12;
    else if (totalHours <= 24 && p24 > 0) basePrice = p24;
    else {
        const days = Math.floor(totalHours / 24);
        const extraHours = totalHours % 24;
        basePrice = days * p24;
        
        if (extraHours > 0) {
            if (extraHours <= 6) basePrice += ext1_6;
            else if (extraHours <= 10) basePrice += ext7_10;
            else if (extraHours <= 12) basePrice += ext11_12;
            else basePrice += ext13_24;
        }
    }
    
    const discount = parseFloat(document.getElementById('discount_priceInput').value) || 0;
    const downPayment = parseFloat(document.getElementById('downPaymentInput').value) || 0;
    const remainingBalance = Math.max(0, basePrice - discount - downPayment);
    
    document.getElementById('displayBasePrice').innerText = '₱' + basePrice.toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('displayDiscount').innerText = '-₱' + discount.toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('displayDownPayment').innerText = '-₱' + downPayment.toLocaleString('en-US', { minimumFractionDigits: 2 });
    const totalEl = document.getElementById('displayTotal');
    totalEl.innerText = '₱' + remainingBalance.toLocaleString('en-US', { minimumFractionDigits: 2 });
    totalEl.classList.remove('pulse');
    void totalEl.offsetWidth; /* restart animation */
    totalEl.classList.add('pulse');

    document.getElementById('totalPriceInput').value = remainingBalance.toFixed(2);
}

function resetPricingDisplay() {
    document.getElementById('displayBasePrice').innerText = '₱0.00';
    document.getElementById('displayDiscount').innerText = '-₱0.00';
    document.getElementById('displayDownPayment').innerText = '-₱0.00';
    document.getElementById('displayTotal').innerText = '₱0.00';
    document.getElementById('totalPriceInput').value = "0";
}

function toggleCustomerType() {
    const type = document.getElementById('userType').value;
    const regDiv = document.getElementById('registeredInput');
    const guestDiv = document.getElementById('guestInput');
    
    if (type === 'guest') {
        if(regDiv) regDiv.classList.add('d-none');
        if(guestDiv) guestDiv.classList.remove('d-none');
        document.getElementById('userIdSelect').removeAttribute('required');
        document.getElementById('guestNameInput').setAttribute('required', 'required');
    } else {
        if(regDiv) regDiv.classList.remove('d-none');
        if(guestDiv) guestDiv.classList.add('d-none');
        document.getElementById('userIdSelect').setAttribute('required', 'required');
        document.getElementById('guestNameInput').removeAttribute('required');
    }
}

/* Same switch as toggleCustomerType(), but scoped to a single Edit Booking modal by id */
function toggleEditCustomerType(id) {
    const type = document.getElementById('userType_' + id).value;
    const regDiv = document.getElementById('registeredInput_' + id);
    const guestDiv = document.getElementById('guestInput_' + id);
    const userIdSelect = document.getElementById('userIdSelect_' + id);
    const guestNameInput = document.getElementById('guestNameInput_' + id);

    if (type === 'guest') {
        if (regDiv) regDiv.classList.add('d-none');
        if (guestDiv) guestDiv.classList.remove('d-none');
        if (userIdSelect) userIdSelect.removeAttribute('required');
        if (guestNameInput) guestNameInput.setAttribute('required', 'required');
    } else {
        if (regDiv) regDiv.classList.remove('d-none');
        if (guestDiv) guestDiv.classList.add('d-none');
        if (userIdSelect) userIdSelect.setAttribute('required', 'required');
        if (guestNameInput) guestNameInput.removeAttribute('required');
    }
}

/* Animated file-upload previews. Delegated on document so this also
   covers every dynamically-repeated Edit Booking modal, not just the
   fixed IDs in the Create Booking modal. */
function initFileUploadPreviews() {
    document.addEventListener('change', function (e) {
        const input = e.target.closest('.file-input-hidden');
        if (!input) return;

        const card = input.closest('.file-upload-card');
        if (!card) return;

        const titleEl = card.querySelector('.file-title');
        const subtitleEl = card.querySelector('.file-subtitle');

        // Remember the original label text the first time this card is touched
        if (card.dataset.defaultTitle === undefined) {
            card.dataset.defaultTitle = titleEl ? titleEl.textContent : '';
            card.dataset.defaultSubtitle = subtitleEl ? subtitleEl.textContent : '';
        }

        if (input.files && input.files.length > 0) {
            const file = input.files[0];
            const shortName = file.name.length > 26 ? file.name.slice(0, 23) + '…' : file.name;
            card.classList.add('has-file');
            if (titleEl) titleEl.textContent = shortName;
            if (subtitleEl) subtitleEl.textContent = (file.size / 1024).toFixed(0) + ' KB • Click to change';
        } else {
            card.classList.remove('has-file');
            if (titleEl) titleEl.textContent = card.dataset.defaultTitle;
            if (subtitleEl) subtitleEl.textContent = card.dataset.defaultSubtitle;
        }
    });
}

$(document).ready(function () {
    initialize();
    initFileUploadPreviews();

    $('#carSelect').on('change', calculateTieredTotal);
    $('#discount_priceInput, #downPaymentInput').on('input', calculateTieredTotal);
    $('#pickupDatetime, #returnDatetime').on('change', syncDateTimeValues);
    $('#typeFilter').on('change', filterCarSelectionOptions);

    function applyLimiterAndFilter() {
        const queryValue = $('#unifiedManualSearch').val() ? $('#unifiedManualSearch').val().toLowerCase().trim() : '';
        const limitValue = parseInt($('#manualEntryLimitSelect').val(), 10) || 10;

        let desktopMatchCount = 0;
        $('#bookingsTable tbody tr.js-searchable-booking').each(function () {
            if ($(this).text().toLowerCase().includes(queryValue)) {
                desktopMatchCount++;
                $(this).css('display', desktopMatchCount <= limitValue ? '' : 'none');
            } else {
                $(this).css('display', 'none');
            }
        });

        let mobileMatchCount = 0;
        $('.mobile-booking-card.js-searchable-booking').each(function () {
            if ($(this).text().toLowerCase().includes(queryValue)) {
                mobileMatchCount++;
                $(this).css('display', mobileMatchCount <= limitValue ? 'block' : 'none');
            } else {
                $(this).css('display', 'none');
            }
        });
    }

    if ($('#unifiedManualSearch').length) {
        $('#unifiedManualSearch').on('input', applyLimiterAndFilter);
        $('#manualEntryLimitSelect').on('change', applyLimiterAndFilter);
        applyLimiterAndFilter();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const dropdownBtn = document.getElementById('carTypeDropdownBtn');
    const searchInput = document.getElementById('carTypeSearchInput');
    const optionsContainer = document.getElementById('carTypeOptionsList');
    const selectedLabel = document.getElementById('selectedCarTypeLabel');
    const hiddenInput = document.getElementById('typeFilter');

    if (dropdownBtn) {
        dropdownBtn.addEventListener('shown.bs.dropdown', function () {
            if (searchInput) searchInput.focus();
        });
    }

    if (optionsContainer) {
        optionsContainer.addEventListener('click', function(e) {
            const target = e.target.closest('.dropdown-item');
            if (!target) return;

            const val = target.getAttribute('data-value') || '';
            const text = target.textContent.trim();

            if (hiddenInput) hiddenInput.value = val;
            if (selectedLabel) {
                selectedLabel.textContent = text;
                selectedLabel.classList.remove('text-muted');
            }

            const dropdownInstance = bootstrap.Dropdown.getInstance(dropdownBtn);
            if (dropdownInstance) {
                dropdownInstance.hide();
            }

            if (typeof filterCarSelectionOptions === 'function') {
                filterCarSelectionOptions();
            }
        });
    }

    if (searchInput && optionsContainer) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const options = optionsContainer.querySelectorAll('.dropdown-item');

            options.forEach(option => {
                const text = option.textContent.toLowerCase();
                option.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    /* ---- Searchable "Select Member" dropdown (Walk-in Booking) ---- */
    const memberBtn = document.getElementById('memberDropdownBtn');
    const memberSearch = document.getElementById('memberSearchInput');
    const memberList = document.getElementById('memberOptionsList');
    const memberLabel = document.getElementById('selectedMemberLabel');
    const memberHidden = document.getElementById('userIdSelect');
    const memberNoResults = document.getElementById('memberNoResults');

    function filterMembers(query) {
        if (!memberList) return;
        const items = memberList.querySelectorAll('.dropdown-item');
        let visibleCount = 0;
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            const match = text.includes(query);
            item.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });
        if (memberNoResults) memberNoResults.classList.toggle('d-none', visibleCount > 0);
    }

    if (memberBtn) {
        memberBtn.addEventListener('shown.bs.dropdown', function () {
            if (memberSearch) {
                memberSearch.value = '';
                filterMembers('');
                memberSearch.focus();
            }
            memberBtn.classList.remove('border-danger');
        });
    }

    if (memberList) {
        memberList.addEventListener('click', function(e) {
            const target = e.target.closest('.dropdown-item');
            if (!target) return;

            const val = target.getAttribute('data-value') || '';
            const text = target.textContent.trim();

            if (memberHidden) memberHidden.value = val;
            if (memberLabel) {
                memberLabel.textContent = text;
                memberLabel.classList.toggle('text-muted', val === '');
            }

            const dropdownInstance = bootstrap.Dropdown.getInstance(memberBtn);
            if (dropdownInstance) {
                dropdownInstance.hide();
            }
        });
    }

    if (memberSearch) {
        memberSearch.addEventListener('input', function() {
            filterMembers(this.value.toLowerCase().trim());
        });
    }

    // Native "required" is ignored on hidden inputs, so validate the
    // member selection manually before the Walk-in Booking form submits.
    const manualBookingForm = document.getElementById('manualBookingForm');
    if (manualBookingForm) {
        manualBookingForm.addEventListener('submit', function(e) {
            const customerType = document.getElementById('userType');
            if (customerType && customerType.value === 'registered' && memberHidden && !memberHidden.value) {
                e.preventDefault();
                if (memberBtn) {
                    memberBtn.classList.add('border-danger');
                    memberBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }
});
</script>