<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Staff-only guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    $_SESSION['error'] = "Access denied.";
    ?>
    <script>window.stop(); window.location.href = "../../index.php";</script>
    <?php
    exit();
}

// --- Branch hard-scope for staff (Option B) ---
// Staff only ever see / mutate their own branch. If no branch is assigned,
// fall back to "show nothing" rather than leaking all branches.
$staff_branch_id = (int)($_SESSION['branch_id'] ?? 0);
$branch_scope    = $staff_branch_id > 0 ? " AND branch_id = $staff_branch_id"   : " AND 1=0";
$branch_scope_b  = $staff_branch_id > 0 ? " AND b.branch_id = $staff_branch_id" : " AND 1=0";

// --- 1. THE AUTO-CANCEL CLEANER (scoped to the staff's branch) ---
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');
$now = date('H:i:s');
$auto_cancel_sql = "UPDATE bookings 
                    SET status = 'Cancelled', 
                        total_price = 500 
                    WHERE (status = 'Pending') 
                    AND booking_type = 'online'
                    $branch_scope
                    AND (
                        end_date < '$today'
                        OR 
                        (end_date = '$today' AND return_time < '$now')
                        OR
                        start_date < '$today'
                    )";
mysqli_query($conn, $auto_cancel_sql);

// Get filter parameter
$filter = $_GET['filter'] ?? 'All';
$pageTitle = 'Online Bookings';

// Stats
$statsQuery = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
FROM bookings 
WHERE booking_type = 'online' $branch_scope";

$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Build the query (with Audit joins)
$query = "SELECT b.*, 
                 COALESCE(u.name, b.guest_name) AS customer_name, 
                 COALESCE(u.email, b.gmail)     AS gmail, 
                 c.brand, c.model, c.plate_number,
                 a.name as action_by_name,
                 a.role as action_by_role
          FROM bookings b 
          LEFT JOIN users u ON b.user_id = u.id 
          LEFT JOIN users a ON b.last_action_by = a.id
          JOIN cars c ON b.car_id = c.id 
          WHERE b.booking_type = 'online' $branch_scope_b ";

if ($filter !== 'All') {
    $safe_filter = mysqli_real_escape_string($conn, $filter);
    $query .= " AND b.status = '$safe_filter' ";
}

$query .= " ORDER BY b.id DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("Booking Query Failed: " . mysqli_error($conn));
}

$bookings = [];
while ($row = mysqli_fetch_assoc($result)) {
    $bookings[] = $row;
}

// Relative time helper
if (!function_exists('formatRelativeTime')) {
    function formatRelativeTime($timestamp) {
        if (!$timestamp) return '';
        $ts = strtotime($timestamp);
        $diff = time() - $ts;
        if ($diff < 60)          return 'just now';
        if ($diff < 3600)        return floor($diff / 60) . 'm ago';
        if ($diff < 86400)       return floor($diff / 3600) . 'h ago';
        if ($diff < 604800)      return floor($diff / 86400) . 'd ago';
        return date('M j, Y', $ts);
    }
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../public/assets/css/booking-modal.css">

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

    * { 
        box-sizing: border-box; 
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    body.dark-mode * { scrollbar-color: #3f3f46 transparent; }

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

    .entry-limiter-select {
        height: 40px;
        padding: 6px 32px 6px 12px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--brand-ink);
        background-color: #ffffff;
        border: 1px solid var(--brand-border);
        border-radius: 10px;
        outline: none;
        appearance: none;
        -webkit-appearance: none;
        background: #ffffff url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") no-repeat right 12px center/12px 12px !important;
    }

    .table-card {
        border-radius: var(--card-radius) !important;
        border: 1px solid var(--brand-border);
        overflow: hidden !important;
        background: #ffffff;
    }
    .table thead th:first-child { border-top-left-radius: var(--card-radius) !important; }
    .table thead th:last-child { border-top-right-radius: var(--card-radius) !important; }

    .table-responsive {
        overflow-x: hidden !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
    }

    .table {
        width: 100% !important;
        margin: 0 !important;
        vertical-align: middle;
        background-color: #ffffff;
    }
    .table thead th {
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: 0.725rem;
        background-color: #f1f5f9;
        color: var(--brand-muted);
        padding: 1rem;
        border: none;
    }
    .table tbody td {
        vertical-align: middle;
        white-space: normal !important;
        word-break: break-word;
        background-color: #ffffff;
        position: relative;
    }

    /* Dropdowns */
    .dropdown { position: relative !important; }
    .dropdown-menu,
    .table td .dropdown-menu,
    .mobile-booking-card .dropdown-menu {
        z-index: 1070 !important;
        right: 0 !important;
        left: auto !important;
        margin-top: 0.25rem !important;
        max-width: calc(100vw - 2rem) !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
    }
    .table td:last-child {
        position: relative;
        overflow: visible !important;
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.725rem;
        font-weight: 700;
        display: inline-block;
        white-space: nowrap;
    }
    .status-pending { background: #fef3c7; color: #d97706; }
    .status-confirmed { background: #e0f2fe; color: #0369a1; }
    .status-completed { background: #dcfce7; color: #15803d; }
    .status-cancelled { background: #fee2e2; color: #b91c1c; }

    /* Audit column */
    .audit-stack {
        display: flex;
        flex-direction: column;
        gap: 6px;
        align-items: flex-start;
    }
    .audit-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 700;
        white-space: nowrap;
        line-height: 1.4;
    }
    .audit-badge i { font-size: 0.7rem; }
    .audit-badge.is-created-by-staff { background: var(--brand-yellow); color: #000000; }
    .audit-badge.is-created-by-other { background: #e0e7ff; color: #3730a3; }
    .audit-badge.is-created-by-none {
        background: #f1f5f9;
        color: #64748b;
        font-style: italic;
        font-weight: 600;
    }
    .audit-badge.is-action-confirmed  { background: #e0f2fe; color: #0369a1; }
    .audit-badge.is-action-completed  { background: #dcfce7; color: #15803d; }
    .audit-badge.is-action-cancelled  { background: #fee2e2; color: #b91c1c; }
    .audit-badge.is-action-edited     { background: #fef3c7; color: #92400e; }
    .audit-badge.is-action-none       { background: #f1f5f9; color: #94a3b8; font-style: italic; }
    .audit-badge .time-hint {
        font-weight: 600;
        opacity: 0.75;
        font-size: 0.62rem;
    }
    body.dark-mode .audit-badge.is-created-by-other { background: rgba(99, 102, 241, 0.2); color: #a5b4fc; }
    body.dark-mode .audit-badge.is-created-by-none  { background: #1f1f23; color: #94a3b8; }
    body.dark-mode .audit-badge.is-action-confirmed { background: rgba(56, 189, 248, 0.15); color: #7dd3fc; }
    body.dark-mode .audit-badge.is-action-completed { background: rgba(34, 197, 94, 0.15);  color: #86efac; }
    body.dark-mode .audit-badge.is-action-cancelled { background: rgba(239, 68, 68, 0.15);  color: #fca5a5; }
    body.dark-mode .audit-badge.is-action-edited    { background: rgba(234, 179, 8, 0.15);  color: #fde047; }
    body.dark-mode .audit-badge.is-action-none      { background: #1f1f23; color: #94a3b8; }

    /* Mobile cards */
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
        position: relative;
        overflow: visible !important;
    }

    /* Modal */
    .modal { z-index: 1060 !important; }
    .modal-backdrop { z-index: 1055 !important; }
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
    .modal-body { background-color: #ffffff; }

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

    /* Responsive */
    @media (min-width: 992px) {
        .mobile-cards-wrapper { display: none !important; }
        .desktop-table-wrapper, .desktop-table-card { display: block !important; }
    }
    @media (min-width: 576px) and (max-width: 991.98px) {
        .mobile-cards-wrapper { display: flex !important; }
        .desktop-table-wrapper, .desktop-table-card { display: none !important; }
        .custom-control-bar .card-body { gap: 1rem; }
        .search-input-wrapper { max-width: 240px; }
    }
    @media (max-width: 575.98px) {
        .desktop-table-wrapper, .desktop-table-card { display: none !important; }
        .mobile-cards-wrapper { display: flex !important; width: 100% !important; }
        .main-content { 
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
            width: 100% !important;
            overflow-x: hidden !important;
        }
        .main-content > .p-4 { padding: 1rem 0.5rem !important; }

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
        .custom-control-bar .card-body > div:first-child { flex: 0 0 auto !important; }
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
        .nav-status-pills .nav-item { flex: 0 0 auto !important; text-align: center; }
        .nav-status-pills .nav-link {
            padding: 0.4rem 0.75rem !important;
            font-size: 0.75rem;
            text-align: center;
            white-space: nowrap !important;
        }
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
        .mobile-booking-card .status-badge { align-self: flex-start; }
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
    }

    /* Dark mode */
    body.dark-mode {
        background-color: var(--brand-black) !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .main-content { background-color: var(--brand-black) !important; }
    body.dark-mode header, body.dark-mode nav, body.dark-mode .navbar {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode footer, body.dark-mode .footer {
        background-color: var(--brand-black) !important;
        border-color: var(--brand-border-dark) !important;
        color: #cbd5e1 !important;
    }
    body.dark-mode .bg-light {
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
    body.dark-mode .table-card { border-color: var(--brand-border-dark) !important; overflow: hidden !important; }
    body.dark-mode .dropdown-item { color: #e2e8f0 !important; }
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
    body.dark-mode .modal-title { color: #ffffff !important; }
    body.dark-mode .stat-label,
    body.dark-mode .text-muted,
    body.dark-mode .form-label { color: #cbd5e1 !important; }
    body.dark-mode .nav-status-pills {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
    }
    body.dark-mode .nav-status-pills .nav-link { color: #cbd5e1; }
    body.dark-mode .nav-status-pills .nav-link.active {
        background-color: var(--brand-yellow) !important;
        color: #000000 !important;
    }
    body.dark-mode .custom-control-bar {
        background: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
    }
    body.dark-mode .entry-limiter-select {
        background-color: #0d0d0d !important;
        color: #f1f5f9 !important;
        border-color: var(--brand-border-dark) !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffcc00' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    }
    body.dark-mode .search-input-wrapper input {
        background-color: #0d0d0d !important;
        color: #ffffff !important;
        border-color: var(--brand-border-dark) !important;
    }
    body.dark-mode .search-input-wrapper input::placeholder { color: #64748b; }
    body.dark-mode .table { color: #f1f5f9 !important; background-color: var(--brand-card-bg-dark) !important; }
    body.dark-mode .table thead th {
        background-color: #1a1600 !important;
        color: var(--brand-yellow) !important;
        border-bottom: 2px solid var(--brand-border-dark) !important;
    }
    body.dark-mode .table tbody tr { background-color: var(--brand-row-bg-dark) !important; }
    body.dark-mode .table td {
        background-color: var(--brand-row-bg-dark) !important;
        border-bottom: 1px solid var(--brand-border-dark) !important;
        color: #e2e8f0 !important;
    }
    body.dark-mode .table-hover > tbody > tr:hover > * {
        background-color: #262626 !important;
        color: #ffffff !important;
    }
    body.dark-mode .btn-light,
    body.dark-mode .btn-white,
    body.dark-mode .btn-outline-secondary,
    body.dark-mode .dropdown-toggle {
        background-color: #1f1f1f !important;
        color: #f1f5f9 !important;
        border-color: var(--brand-border-dark) !important;
    }
    body.dark-mode .btn-light:hover,
    body.dark-mode .btn-outline-secondary:hover,
    body.dark-mode .dropdown-toggle:hover,
    body.dark-mode .dropdown-toggle:focus {
        background-color: #2a2a2a !important;
        color: #ffffff !important;
        border-color: var(--brand-yellow) !important;
    }
    body.dark-mode .modal-header, body.dark-mode .modal-footer, body.dark-mode .modal-body {
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
    body.dark-mode .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
    body.dark-mode .form-control, body.dark-mode .form-select {
        background-color: #171717 !important;
        border-color: var(--brand-border-dark) !important;
        color: #f8fafc !important;
    }
    body.dark-mode .form-control::placeholder { color: #64748b !important; }
    body.dark-mode .form-control:focus, body.dark-mode .form-select:focus {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.2) !important;
    }
    body.dark-mode .text-brand-yellow { color: var(--brand-yellow) !important; }
    body.dark-mode .bg-brand-yellow,
    body.dark-mode .stat-icon.bg-brand-yellow {
        background: rgba(255, 204, 0, 0.15) !important;
        color: var(--brand-yellow) !important;
    }

    /* Pricing colors */
    .pricing-total { color: var(--brand-ink); }
    .pricing-balance { color: #d97706; }
    body.dark-mode .pricing-total { color: #ffffff !important; }
    body.dark-mode .pricing-balance { color: var(--brand-yellow) !important; }
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
                        <h3 class="fw-bold mb-0">Online <span class="text-brand-yellow">Reservations</span></h3>
                        <p class="text-muted small mb-0">Manage your online booking pipeline efficiently.</p>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-brand-yellow text-dark">
                                    <i class="bi bi-globe2"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($stats['total_bookings'] ?? 0) ?></div>
                                    <div class="stat-label">Total Online</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-hourglass-split"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($stats['pending'] ?? 0) ?></div>
                                    <div class="stat-label">Pending</div>
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
                </div>

                <div class="d-flex mb-3">
                    <ul class="nav nav-status-pills shadow-sm">
                        <li class="nav-item"><a class="nav-link <?= $filter == 'All' ? 'active' : '' ?>" href="?filter=All">All Bookings</a></li>
                        <li class="nav-item"><a class="nav-link <?= $filter == 'Pending' ? 'active' : '' ?>" href="?filter=Pending">Pending</a></li>
                        <li class="nav-item"><a class="nav-link <?= $filter == 'Confirmed' ? 'active' : '' ?>" href="?filter=Confirmed">Confirmed</a></li>
                        <li class="nav-item"><a class="nav-link <?= $filter == 'Completed' ? 'active' : '' ?>" href="?filter=Completed">Completed</a></li>
                        <li class="nav-item"><a class="nav-link <?= $filter == 'Cancelled' ? 'active' : '' ?>" href="?filter=Cancelled">Cancelled</a></li>
                    </ul>
                </div>

                <div class="card custom-control-bar shadow-sm mb-4">
                    <div class="card-body p-3 d-flex flex-row justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-2 small text-muted font-weight-bold">
                            <span>Show</span>
                            <select id="onlineEntryLimitSelect" class="entry-limiter-select">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                            <span>entries</span>
                        </div>
                        <div class="search-input-wrapper">
                            <i class="bi bi-search"></i>
                            <input type="text" id="unifiedOnlineSearch" placeholder="Search reservations...">
                        </div>
                    </div>
                </div>

                <div class="bookings-display-container">
                    <!-- Mobile cards -->
                    <div class="d-block d-md-none mobile-cards-wrapper">
                        <?php if (empty($bookings)): ?>
                            <div class="card border-0 shadow-sm p-5 text-center text-muted" style="border-radius: var(--card-radius);">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No online bookings found.
                            </div>
                        <?php else: ?>
                            <?php foreach ($bookings as $b): ?>
                                <div class="mobile-booking-card js-searchable-booking">
                                    <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                                        <div>
                                            <div class="fw-bold text-dark">
                                                <?= htmlspecialchars($b['customer_name'] ?? 'Guest User') ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($b['gmail'] ?? 'No Email') ?></div>
                                            <div class="text-muted" style="font-size: 10px;">ID: #BK-<?= $b['id'] ?></div>

                                            <!-- AUDIT stack -->
                                            <div class="audit-stack mt-2">
                                                <span class="audit-badge is-created-by-none">
                                                    <i class="bi bi-person"></i>
                                                    Auto / Customer
                                                </span>
                                                <?php if (!empty($b['last_action_by']) && !empty($b['action_by_name'])):
                                                    $actionType = $b['last_action_type'] ?? 'Edited';
                                                    $actionClass = 'is-action-' . strtolower($actionType);
                                                    $actionIcon = [
                                                        'Confirmed' => 'bi-check-circle-fill',
                                                        'Completed' => 'bi-flag-fill',
                                                        'Cancelled' => 'bi-x-circle-fill',
                                                        'Edited'    => 'bi-pencil-fill',
                                                    ][$actionType] ?? 'bi-clock';
                                                ?>
                                                    <span class="audit-badge <?= $actionClass ?>">
                                                        <i class="bi <?= $actionIcon ?>"></i>
                                                        <?= htmlspecialchars($actionType) ?>
                                                        • <?= htmlspecialchars($b['action_by_name']) ?>
                                                        <span class="time-hint"><?= formatRelativeTime($b['last_action_at']) ?></span>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="audit-badge is-action-none">
                                                        <i class="bi bi-clock-history"></i>
                                                        No actions yet
                                                    </span>
                                                <?php endif; ?>
                                            </div>
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
                                                ₱<?= number_format($b['total_price'], 2) ?>
                                            </div>
                                            <?php if ($b['down_payment'] > 0): ?>
                                                <div class="text-success" style="font-size: 10px;">DP: ₱<?= number_format($b['down_payment'], 2) ?></div>
                                            <?php endif; ?>
                                            <div class="text-brand-yellow fw-bold" style="font-size: 12px; border-top: 1px solid #dee2e6; margin-top: 4px; padding-top: 2px;">
                                                Bal: ₱<?= number_format(max(0, $b['total_price'] - $b['down_payment']), 2) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-light p-2 rounded-3 mb-2 border-start border-3 border-warning">
                                        <span class="text-uppercase text-muted d-block mb-1" style="font-size: 0.65rem; font-weight:700;">Schedule</span>
                                        <div style="font-size:11px;" class="text-dark fw-medium">
                                            <i class="bi bi-calendar-event me-1 text-muted"></i><?= date('M d, Y', strtotime($b['start_date'])) ?> to <?= date('M d, Y', strtotime($b['end_date'])) ?>
                                        </div>
                                        <div style="font-size:10px;" class="text-muted mt-1">
                                            <i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($b['pickup_time'])) ?> - <?= date('h:i A', strtotime($b['return_time'])) ?>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php if (!empty($b['primary_id_path'])): ?>
                                                <a href="../../public/assets/images/ids/<?= $b['primary_id_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-2 py-1" style="font-size:10px;">ID 1</a>
                                            <?php endif; ?>
                                            <?php if (!empty($b['secondary_id_path'])): ?>
                                                <a href="../../public/assets/images/ids/<?= $b['secondary_id_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-2 py-1" style="font-size:10px;">ID 2</a>
                                            <?php endif; ?>
                                            <?php if (!empty($b['proof_billing_path'])): ?>
                                                <a href="../../public/assets/images/ids/<?= $b['proof_billing_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-2 py-1" style="font-size:10px;">PROOF</a>
                                            <?php endif; ?>
                                            <?php if (!empty($b['proof_payment_path'])): ?>
                                                <a href="../../public/assets/images/ids/<?= $b['proof_payment_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-2 py-1" style="font-size:10px;">PAYMENT</a>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <?php if ($b['status'] == 'Completed' || $b['status'] == 'Cancelled'): ?>
                                                <button class="btn btn-sm btn-light border text-muted" disabled style="font-size: 11px;"><i class="bi bi-lock-fill me-1"></i>Locked</button>
                                            <?php else: ?>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">Actions</button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                        <?php if ($b['status'] == 'Pending'): ?>
                                                            <li><a class="dropdown-item text-primary" href="#" data-bs-toggle="modal" data-bs-target="#editCredentialsModal" data-booking='<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>'><i class="bi bi-pencil-square me-2"></i>Edit</a></li>
                                                            <li><a class="dropdown-item text-success" href="../admin/process/booking_actions.php?id=<?= $b['id'] ?>&status=Confirmed&source=online&filter=<?= $filter ?>"><i class="bi bi-check-circle me-2"></i>Confirm</a></li>
                                                        <?php endif; ?>
                                                        <?php if ($b['status'] == 'Confirmed'): ?>
                                                            <li><a class="dropdown-item text-success" href="../admin/process/booking_actions.php?id=<?= $b['id'] ?>&status=Completed&source=online&filter=<?= $filter ?>"><i class="bi bi-flag me-2"></i>Complete</a></li>
                                                        <?php endif; ?>
                                                        <li><a class="dropdown-item text-danger" href="../admin/process/booking_actions.php?id=<?= $b['id'] ?>&status=Cancelled&source=online&filter=<?= $filter ?>"><i class="bi bi-x-circle me-2"></i>Cancel</a></li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Desktop table -->
                    <div class="d-none d-md-block desktop-table-card">
                        <div class="card border-0 shadow-sm table-card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table id="bookingsTable" class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="border-0 px-3 py-3 text-start">Customer</th>
                                                <th class="border-0 py-3 text-start">Audit</th>
                                                <th class="border-0 py-3 text-start">Vehicle</th>
                                                <th class="border-0 py-3 text-start">Schedule</th>
                                                <th class="border-0 py-3 text-start">Fulfillment</th>
                                                <th class="border-0 py-3 text-start">Verification Files</th>
                                                <th class="border-0 py-3 text-start">Pricing</th>
                                                <th class="border-0 py-3 text-start">Status</th>
                                                <th class="border-0 pe-3 py-3 text-start">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small align-middle">
                                            <?php if (empty($bookings)): ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-5 text-muted">
                                                        <i class="bi bi-inbox fs-3 d-block mb-2 text-secondary"></i>
                                                        No online bookings found.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($bookings as $b): ?>
                                                    <tr class="js-searchable-booking">
                                                        <td class="px-3 py-3 text-start">
                                                            <div class="fw-bold text-dark">
                                                                <?= htmlspecialchars($b['customer_name'] ?? 'Guest User') ?>
                                                            </div>
                                                            <div class="text-muted small"><?= htmlspecialchars($b['gmail'] ?? 'No Email') ?></div>
                                                            <span class="badge bg-light text-secondary border mt-1" style="font-size: 10px;">
                                                                #BK-<?= $b['id'] ?>
                                                            </span>
                                                        </td>

                                                        <!-- AUDIT -->
                                                        <td class="py-3 text-start">
                                                            <div class="audit-stack">
                                                                <span class="audit-badge is-created-by-none">
                                                                    <i class="bi bi-person"></i>
                                                                    Auto / Customer
                                                                </span>
                                                                <?php if (!empty($b['last_action_by']) && !empty($b['action_by_name'])):
                                                                    $actionType = $b['last_action_type'] ?? 'Edited';
                                                                    $actionClass = 'is-action-' . strtolower($actionType);
                                                                    $actionIcon = [
                                                                        'Confirmed' => 'bi-check-circle-fill',
                                                                        'Completed' => 'bi-flag-fill',
                                                                        'Cancelled' => 'bi-x-circle-fill',
                                                                        'Edited'    => 'bi-pencil-fill',
                                                                    ][$actionType] ?? 'bi-clock';
                                                                ?>
                                                                    <span class="audit-badge <?= $actionClass ?>">
                                                                        <i class="bi <?= $actionIcon ?>"></i>
                                                                        <?= htmlspecialchars($actionType) ?>
                                                                        • <?= htmlspecialchars($b['action_by_name']) ?>
                                                                        <span class="time-hint"><?= formatRelativeTime($b['last_action_at']) ?></span>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="audit-badge is-action-none">
                                                                        <i class="bi bi-clock-history"></i>
                                                                        No actions yet
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>

                                                        <td class="py-3 text-start">
                                                            <div class="fw-semibold text-dark">
                                                                <?= htmlspecialchars($b['brand']) ?> <?= htmlspecialchars($b['model']) ?>
                                                            </div>
                                                            <div class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 mt-1">
                                                                <?= htmlspecialchars($b['plate_number']) ?>
                                                            </div>
                                                        </td>

                                                        <td class="py-3 text-start">
                                                            <div class="fw-medium text-dark">
                                                                <i class="bi bi-calendar-event me-1 text-primary"></i>
                                                                <?= date('M d', strtotime($b['start_date'])) ?> &ndash; <?= date('M d', strtotime($b['end_date'])) ?>
                                                            </div>
                                                            <div class="text-muted small mt-1">
                                                                <i class="bi bi-clock me-1 text-secondary"></i>
                                                                <?= date('h:i A', strtotime($b['pickup_time'])) ?> &ndash; <?= date('h:i A', strtotime($b['return_time'])) ?>
                                                            </div>
                                                        </td>

                                                        <td class="py-3 text-start">
                                                            <?php 
                                                                $fulfillment = $b['fulfillment_type'] ?? 'Pickup';
                                                                $isDelivery = (strtolower($fulfillment) === 'delivery');
                                                                $fulfillmentBg = $isDelivery ? 'bg-info text-dark' : 'bg-secondary text-white';
                                                                $businessAddress = "Jibao-an, Pavia, Iloilo"; 
                                                                $addressToShow = $isDelivery ? ($b['delivery_address'] ?? 'No address provided') : $businessAddress;
                                                            ?>
                                                            <span class="badge <?= $fulfillmentBg ?> px-2 py-1" style="font-size: 10px;">
                                                                <i class="bi <?= $isDelivery ? 'bi-truck' : 'bi-shop' ?> me-1"></i>
                                                                <?= htmlspecialchars(ucfirst($fulfillment)) ?>
                                                            </span>
                                                            <div class="small text-muted mt-1" style="font-size: 11px;">
                                                                <i class="bi bi-geo-alt-fill me-1 text-danger"></i><?= htmlspecialchars($addressToShow) ?>
                                                            </div>
                                                        </td>

                                                        <td class="py-3 text-start">
                                                            <div class="d-flex flex-wrap gap-1">
                                                                <?php if (!empty($b['primary_id_path'])): ?>
                                                                    <a href="../../public/assets/images/ids/<?= $b['primary_id_path'] ?>" target="_blank" class="btn btn-xs btn-outline-secondary px-2 py-1" style="font-size: 10px;">ID 1</a>
                                                                <?php endif; ?>
                                                                <?php if (!empty($b['secondary_id_path'])): ?>
                                                                    <a href="../../public/assets/images/ids/<?= $b['secondary_id_path'] ?>" target="_blank" class="btn btn-xs btn-outline-secondary px-2 py-1" style="font-size: 10px;">ID 2</a>
                                                                <?php endif; ?>
                                                                <?php if (!empty($b['proof_billing_path'])): ?>
                                                                    <a href="../../public/assets/images/ids/<?= $b['proof_billing_path'] ?>" target="_blank" class="btn btn-xs btn-outline-secondary px-2 py-1" style="font-size: 10px;">BILLING</a>
                                                                <?php endif; ?>
                                                                <?php if (!empty($b['proof_payment_path'])): ?>
                                                                    <a href="../../public/assets/images/ids/<?= $b['proof_payment_path'] ?>" target="_blank" class="btn btn-xs btn-outline-secondary px-2 py-1" style="font-size: 10px;">PAYMENT</a>
                                                                <?php endif; ?>
                                                                <?php if (empty($b['primary_id_path']) && empty($b['secondary_id_path']) && empty($b['proof_billing_path']) && empty($b['proof_payment_path'])): ?>
                                                                    <span class="text-muted small">&mdash;</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>

                                                        <td class="py-3 text-start">
                                                            <div class="fw-bold pricing-total">
                                                                ₱<?= number_format($b['total_price'], 2) ?>
                                                            </div>
                                                            <?php if ($b['down_payment'] > 0): ?>
                                                                <div class="text-success small" style="font-size: 11px;">
                                                                    DP: ₱<?= number_format($b['down_payment'], 2) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="fw-bold small pricing-balance" style="font-size: 11px;">
                                                                Bal: ₱<?= number_format(max(0, $b['total_price'] - $b['down_payment']), 2) ?>
                                                            </div>
                                                        </td>

                                                        <td class="py-3 text-start">
                                                            <span class="status-badge status-<?= strtolower($b['status']) ?>">
                                                                <?= htmlspecialchars($b['status']) ?>
                                                            </span>
                                                        </td>

                                                        <td class="pe-3 py-3 text-start">
                                                            <?php if ($b['status'] == 'Completed' || $b['status'] == 'Cancelled'): ?>
                                                                <span class="text-muted small"><i class="bi bi-lock-fill me-1"></i>Locked</span>
                                                            <?php else: ?>
                                                                <div class="dropdown d-inline-block">
                                                                    <button class="btn btn-sm btn-light border dropdown-toggle shadow-sm px-2 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 12px;">
                                                                        Actions
                                                                    </button>
                                                                    <ul class="dropdown-menu dropdown-menu-start shadow border-0" style="font-size: 12px;">
                                                                        <?php if ($b['status'] == 'Pending'): ?>
                                                                            <li>
                                                                                <button class="dropdown-item text-primary edit-credentials-btn" type="button" data-booking='<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>'>
                                                                                    <i class="bi bi-pencil-square me-2"></i>Edit
                                                                                </button>
                                                                            </li>
                                                                            <li>
                                                                                <a class="dropdown-item text-success" href="../admin/process/booking_actions.php?id=<?= $b['id'] ?>&status=Confirmed&source=online&filter=<?= $filter ?>">
                                                                                    <i class="bi bi-check-circle me-2"></i>Confirm
                                                                                </a>
                                                                            </li>
                                                                            <li><hr class="dropdown-divider"></li>
                                                                        <?php endif; ?>
                                                                        <?php if ($b['status'] == 'Confirmed'): ?>
                                                                            <li>
                                                                                <a class="dropdown-item text-success" href="../admin/process/booking_actions.php?id=<?= $b['id'] ?>&status=Completed&source=online&filter=<?= $filter ?>">
                                                                                    <i class="bi bi-flag me-2"></i>Complete
                                                                                </a>
                                                                            </li>
                                                                        <?php endif; ?>
                                                                        <?php if ($b['status'] !== 'Cancelled' && $b['status'] !== 'Completed'): ?>
                                                                            <li>
                                                                                <a class="dropdown-item text-danger" href="../admin/process/booking_actions.php?id=<?= $b['id'] ?>&status=Cancelled&source=online&filter=<?= $filter ?>">
                                                                                    <i class="bi bi-x-circle me-2"></i>Cancel
                                                                                </a>
                                                                            </li>
                                                                        <?php endif; ?>
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

<!-- EDIT RESERVATION MODAL -->
<div class="modal fade" id="editCredentialsModal" tabindex="-1" aria-labelledby="editCredentialsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form action="../admin/process/booking_actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="booking_id" id="edit_booking_id">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <input type="hidden" name="source" value="online">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="editCredentialsModalLabel">
                        <i class="bi bi-pencil-square me-2 text-warning"></i>Edit Reservation Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Fulfillment Type</label>
                            <select name="fulfillment_type" id="edit_fulfillment_type" class="form-select">
                                <option value="pickup">Pickup</option>
                                <option value="delivery">Delivery</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Delivery Address</label>
                            <input type="text" name="delivery_address" id="edit_delivery_address" class="form-control" placeholder="Address if Delivery">
                        </div>

                        <div class="col-6 col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" id="edit_end_date" class="form-control" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Pickup Time</label>
                            <input type="time" name="pickup_time" id="edit_pickup_time" class="form-control" step="60" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Return Time</label>
                            <input type="time" name="return_time" id="edit_return_time" class="form-control" step="60" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Total Price (₱)</label>
                            <input type="number" step="0.01" name="total_price" id="edit_total_price" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Down Payment (₱)</label>
                            <input type="number" step="0.01" name="down_payment" id="edit_down_payment" class="form-control" required>
                        </div>

                        <div class="col-12"><hr class="my-2"></div>
                        <div class="col-12"><h6 class="fw-bold mb-1">Update Documents / Verification (Optional)</h6></div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Primary ID</label>
                            <input type="file" name="primary_id" class="form-control">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Secondary ID</label>
                            <input type="file" name="secondary_id" class="form-control">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Proof of Billing</label>
                            <input type="file" name="proof_of_billing" class="form-control">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Proof of Payment</label>
                            <input type="file" name="proof_of_payment" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function () {
    function applyLimiterAndFilter() {
        const queryValue = $('#unifiedOnlineSearch').val().toLowerCase().trim();
        const limitValue = parseInt($('#onlineEntryLimitSelect').val(), 10) || 10;

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

    $('#unifiedOnlineSearch').on('input', applyLimiterAndFilter);
    $('#onlineEntryLimitSelect').on('change', applyLimiterAndFilter);
    applyLimiterAndFilter();
});

$(document).ready(function () {
    function formatTimeInput(timeStr) {
        if (!timeStr) return '';
        return timeStr.toString().substring(0, 5);
    }

    function toggleDeliveryAddress() {
        const type = $('#edit_fulfillment_type').val();
        if (type === 'delivery') {
            $('#edit_delivery_address').prop('disabled', false).attr('required', true);
        } else {
            $('#edit_delivery_address').prop('disabled', true).removeAttr('required').val('');
        }
    }

    $('#edit_fulfillment_type').on('change', toggleDeliveryAddress);

    $(document).on('click', '.edit-credentials-btn', function() {
        const booking = $(this).data('booking');

        $('#edit_booking_id').val(booking.id);
        $('#edit_fulfillment_type').val(booking.fulfillment_type || 'pickup');
        $('#edit_delivery_address').val(booking.delivery_address || '');
        $('#edit_start_date').val(booking.start_date);
        $('#edit_end_date').val(booking.end_date);
        $('#edit_pickup_time').val(formatTimeInput(booking.pickup_time));
        $('#edit_return_time').val(formatTimeInput(booking.return_time));
        $('#edit_total_price').val(booking.total_price);
        $('#edit_down_payment').val(booking.down_payment);

        toggleDeliveryAddress();
        $('#editCredentialsModal').modal('show');
    });

    // Mobile cards: convert the button to trigger the same modal
    $(document).on('click', '.mobile-booking-card .edit-credentials-btn', function() {
        const booking = $(this).data('booking');
        $('#edit_booking_id').val(booking.id);
        $('#edit_fulfillment_type').val(booking.fulfillment_type || 'pickup');
        $('#edit_delivery_address').val(booking.delivery_address || '');
        $('#edit_start_date').val(booking.start_date);
        $('#edit_end_date').val(booking.end_date);
        $('#edit_pickup_time').val(formatTimeInput(booking.pickup_time));
        $('#edit_return_time').val(formatTimeInput(booking.return_time));
        $('#edit_total_price').val(booking.total_price);
        $('#edit_down_payment').val(booking.down_payment);
        toggleDeliveryAddress();
        $('#editCredentialsModal').modal('show');
    });
});
</script>