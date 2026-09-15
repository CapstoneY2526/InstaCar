<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/branch_helper.php';

// --- AUTHENTICATION CHECK - JS Redirect ---
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

$current_month = date('m');
$current_year = date('Y');
$current_date = date('Y-m-d');

// 1. Total Cars
$res_cars = mysqli_query($conn, "SELECT COUNT(id) as total FROM cars WHERE 1=1" . branchScopeSql());
$row_cars = mysqli_fetch_assoc($res_cars);
$total_cars = $row_cars['total'] ?? 0;

// 2. Available Cars
$res_available = mysqli_query($conn, "SELECT COUNT(id) as total FROM cars WHERE status = 'Available'" . branchScopeSql());
$row_available = mysqli_fetch_assoc($res_available);
$available_cars = $row_available['total'] ?? 0;

// 3. Active Bookings (Confirmed and not yet completed)
$res_active_bookings = mysqli_query($conn, "
    SELECT COUNT(id) as total 
    FROM bookings 
    WHERE status = 'Confirmed' 
    AND start_date <= '$current_date'
    AND end_date >= '$current_date'" . branchScopeSql());
$row_active_bookings = mysqli_fetch_assoc($res_active_bookings);
$active_bookings = $row_active_bookings['total'] ?? 0;

// 4. Total Bookings (All time)
$res_total_bookings = mysqli_query($conn, "SELECT COUNT(id) as total FROM bookings WHERE 1=1" . branchScopeSql());
$row_total_bookings = mysqli_fetch_assoc($res_total_bookings);
$total_bookings = $row_total_bookings['total'] ?? 0;

// 5. Completed Bookings (This Month)
$res_completed = mysqli_query($conn, "
    SELECT COUNT(id) as total 
    FROM bookings 
    WHERE status = 'Completed' 
    AND MONTH(created_at) = '$current_month' 
    AND YEAR(created_at) = '$current_year'" . branchScopeSql());
$row_completed = mysqli_fetch_assoc($res_completed);
$completed_bookings = $row_completed['total'] ?? 0;

// 6. Monthly NET Revenue (via booking_payments joined to bookings for branch filtering)
$res_revenue = mysqli_query($conn, "
    SELECT COALESCE(SUM(bp.total_net), 0) as total 
    FROM booking_payments bp
    INNER JOIN bookings b ON bp.booking_id = b.id
    WHERE MONTH(bp.created_at) = '$current_month' 
    AND YEAR(bp.created_at) = '$current_year'" . branchScopeSql('b.branch_id'));
$row_revenue = mysqli_fetch_assoc($res_revenue);
$monthly_revenue = $row_revenue['total'] ?? 0;

// 7. Total NET Revenue (All time)
$res_total_revenue = mysqli_query($conn, "
    SELECT COALESCE(SUM(bp.total_net), 0) as total 
    FROM booking_payments bp
    INNER JOIN bookings b ON bp.booking_id = b.id
    WHERE 1=1" . branchScopeSql('b.branch_id'));
$row_total_revenue = mysqli_fetch_assoc($res_total_revenue);
$total_revenue = $row_total_revenue['total'] ?? 0;

// 8. Total GROSS Revenue
$res_gross_revenue = mysqli_query($conn, "
    SELECT COALESCE(SUM(bp.total_gross), 0) as total 
    FROM booking_payments bp
    INNER JOIN bookings b ON bp.booking_id = b.id
    WHERE 1=1" . branchScopeSql('b.branch_id'));
$row_gross_revenue = mysqli_fetch_assoc($res_gross_revenue);
$total_gross_revenue = $row_gross_revenue['total'] ?? 0;

// 9. Pending Tasks (Pending Bookings)
$res_tasks = mysqli_query($conn, "SELECT COUNT(id) as total FROM bookings WHERE status = 'Pending'" . branchScopeSql());
$row_tasks = mysqli_fetch_assoc($res_tasks);
$pending_tasks = $row_tasks['total'] ?? 0;

// 10. Total Customers (Users with role 'user')
// Note: customers aren't branch-scoped in the schema. Count them company-wide, but you
// can scope to "customers with bookings in this branch" if you prefer.
$res_customers = mysqli_query($conn, "SELECT COUNT(id) as total FROM users WHERE role = 'user'");
$row_customers = mysqli_fetch_assoc($res_customers);
$total_customers = $row_customers['total'] ?? 0;

// 11. Total Operators (scoped by branch)
$res_operators = mysqli_query($conn, "SELECT COUNT(id) as total FROM users WHERE role = 'operator'" . branchScopeSql());
$row_operators = mysqli_fetch_assoc($res_operators);
$total_operators = $row_operators['total'] ?? 0;

// 12. Recent Bookings (Last 5)
$recent_bookings_res = mysqli_query($conn, "
    SELECT b.*, 
           COALESCE(u.name, b.guest_name) as customer_name, 
           c.brand, 
           c.model, 
           c.plate_number,
           bp.total_net,
           bp.total_gross
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    JOIN cars c ON b.car_id = c.id 
    LEFT JOIN booking_payments bp ON b.id = bp.booking_id
    WHERE 1=1" . branchScopeSql('b.branch_id') . "
    ORDER BY b.id DESC 
    LIMIT 5
");

$recent_bookings = [];
if ($recent_bookings_res) {
    while ($booking = mysqli_fetch_assoc($recent_bookings_res)) {
        $recent_bookings[] = $booking;
    }
}

// 13. Recent Cars List
$recent_cars_res = mysqli_query($conn, "
    SELECT brand, model, plate_number, status 
    FROM cars 
    WHERE 1=1" . branchScopeSql() . "
    ORDER BY id DESC 
    LIMIT 5
");
$recent_cars = [];
if ($recent_cars_res) {
    while ($car = mysqli_fetch_assoc($recent_cars_res)) {
        $recent_cars[] = $car;
    }
}

function formatNumberShort($num) {
    if ($num >= 1000000000) {
        return round($num / 1000000000, 1) . 'B';
    }
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    }
    if ($num >= 1000) {
        return round($num / 1000, 1) . 'k';
    }
    return number_format($num);
}

$pageTitle = 'Admin Dashboard';

// Check for unread messages
$unread_count = 0;
$unread_check = mysqli_query($conn, "SHOW COLUMNS FROM contact_messages LIKE 'is_read'");

if ($unread_check && mysqli_num_rows($unread_check) > 0) {
    $unread_query = mysqli_query($conn, "SELECT COUNT(*) as unread FROM contact_messages WHERE is_read = 0");
    if ($unread_query) {
        $unread_data = mysqli_fetch_assoc($unread_query);
        $unread_count = (int)($unread_data['unread'] ?? 0);
    }
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    /* ========================================================
       BASE LAYOUT & LIGHT MODE STYLES
       ======================================================== */
    body, 
    button, 
    input, 
    select, 
    textarea, 
    .form-control, 
    .btn, 
    .table,
    .modal-content { 
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; 
    }

    body { 
        background-color: var(--brand-bg, #f8fafc); 
        overflow-x: hidden; 
    }

    .main-content { 
        background-color: var(--brand-bg, #f8fafc);
        min-height: 100vh; 
        width: 100%;
        transition: background-color 0.25s ease, color 0.25s ease;
    }

    /* Welcome Card Accent Banner */
    .welcome-card {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #ffffff;
        border-radius: 1.5rem;
        position: relative;
        overflow: hidden;
    }

    .welcome-card .btn-book,
    .welcome-card .btn-book *,
    body.dark-mode .welcome-card .btn-book,
    body.dark-mode .welcome-card .btn-book * { 
        background-color: #ffcc00 !important;
        border: none !important;
        color: #000000 !important;
        border-radius: 12px; 
        font-weight: 700 !important; 
        padding: 10px 24px; 
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: auto !important;
        box-shadow: none !important;
        transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }

    .welcome-card .btn-book:hover,
    body.dark-mode .welcome-card .btn-book:hover {
        background-color: #ffd633 !important;
        color: #000000 !important;
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(255, 204, 0, 0.4) !important;
    }

    /* Branch badge on welcome card */
    .welcome-branch-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        background: rgba(255, 204, 0, 0.18);
        color: #ffcc00;
        border: 1px solid rgba(255, 204, 0, 0.35);
        margin-top: 10px;
    }

    /* Stat Cards Base & Yellow Hover Glow Effect */
    .stat-card { 
        border-radius: 1.25rem; 
        border: 1px solid #edf2f7; 
        background-color: #ffffff;
        padding: 1.25rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.25s ease; 
        overflow: hidden;
        height: 100%;
    }

    .stat-card:hover { 
        transform: translateY(-3px); 
        border-color: #ffcc00 !important;
        box-shadow: 0 0 15px rgba(255, 204, 0, 0.4) !important;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }

    .stat-value {
        font-size: 1.4rem;
        font-weight: 800;
        line-height: 1.2;
        color: #0f172a;
    }

    .stat-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        font-weight: 600;
        margin-top: 2px;
    }

    .stat-subtext {
        font-size: 0.75rem;
        display: block;
        margin-top: 2px;
    }

    .card {
        border-radius: 1.25rem !important;
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
    }

    /* Table & Card Hover Animations */
    .table thead th {
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: 0.72rem;
        background-color: #f8fafc;
        color: #64748b;
        border-bottom: 1px solid #e2e8f0;
    }

    .table tbody tr:hover {
        background-color: #fef9e3 !important;
        transition: background 0.2s;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        display: inline-block;
    }
    .status-pending { background: #fef3c7; color: #d97706; }
    .status-confirmed { background: #dbeafe; color: #2563eb; }
    .status-completed { background: #d1fae5; color: #059669; }
    .status-cancelled { background: #fee2e2; color: #dc2626; }

    .btn-white {
        background: #ffffff;
        color: #0f172a;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
    }
    .btn-white:hover {
        border-color: #ffcc00 !important;
        color: #000000 !important;
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.25);
    }

    .icon-shape {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: rgba(255, 204, 0, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    /* Mobile / Tablet Cards */
    .mobile-card-container { display: none; }
    .responsive-mobile-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.25rem;
        margin: 0.75rem 1rem;
        transition: box-shadow 0.2s ease, background-color 0.25s ease, border-color 0.25s ease;
    }
    .responsive-mobile-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    /* Offcanvas Sidebar Responsive Blueprint */
    @media (max-width: 991.98px) {
        .mobile-sidebar-container {
            position: fixed;
            top: 0;
            left: -280px !important;
            width: 280px;
            height: 100vh;
            z-index: 1060;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15);
            background: #fff;
            overflow-y: auto !important;
            display: block !important;
        }

        .mobile-sidebar-container.show {
            left: 0 !important;
        }

        .sidebar-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.5);
            z-index: 1050;
            display: none;
            opacity: 0;
            transition: opacity 0.25s linear;
        }
        
        .sidebar-backdrop.show {
            display: block;
            opacity: 1;
        }

        .desktop-table-card { display: none !important; }
        .mobile-card-container { display: block; }
        .welcome-card { padding: 2rem !important; }
    }

    @media (min-width: 992px) {
        .desktop-table-card { display: block !important; }
        .mobile-card-container { display: none !important; }
    }

    /* ========================================================
       DARK MODE COMPLETE OVERRIDES & CONTRAST FIXES
       ======================================================== */
    body.dark-mode,
    body.dark-mode .main-content {
        background-color: #0a0a0a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode header,
    body.dark-mode navbar,
    body.dark-mode .navbar,
    body.dark-mode footer,
    body.dark-mode .footer {
        background-color: #141414 !important;
        border-color: #27272a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode footer p {
        color: #a1a1aa !important;
    }

    body.dark-mode .mobile-sidebar-container {
        background-color: #141414 !important;
        border-right: 1px solid #27272a !important;
    }

    body.dark-mode .main-content .text-dark,
    body.dark-mode .main-content h2,
    body.dark-mode .main-content h3,
    body.dark-mode .main-content h4,
    body.dark-mode .main-content h5,
    body.dark-mode .main-content h6,
    body.dark-mode .main-content label,
    body.dark-mode .stat-value {
        color: #ffffff !important;
    }

    body.dark-mode .main-content .text-muted,
    body.dark-mode .main-content .text-secondary,
    body.dark-mode .stat-label {
        color: #cbd5e1 !important;
    }

    body.dark-mode .main-content .text-primary {
        color: #38bdf8 !important;
    }

    body.dark-mode .welcome-card {
        background: linear-gradient(135deg, #18181b 0%, #09090b 100%) !important;
        border: 1px solid #27272a !important;
    }

    body.dark-mode .card,
    body.dark-mode .responsive-mobile-card {
        background-color: #141414 !important;
        border: 1px solid #27272a !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4) !important;
    }

    body.dark-mode .stat-card {
        background-color: #141414 !important;
        border: 1px solid #27272a !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4) !important;
    }

    body.dark-mode .stat-card:hover {
        border-color: #ffcc00 !important;
        box-shadow: 0 0 18px rgba(255, 204, 0, 0.35) !important;
    }

    body.dark-mode .card-header {
        background-color: #141414 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .table,
    body.dark-mode .table tr,
    body.dark-mode .table td,
    body.dark-mode .table th {
        background-color: #141414 !important;
        color: #f1f5f9 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .table thead,
    body.dark-mode .table thead tr,
    body.dark-mode .table thead th {
        background-color: #1f1f23 !important;
        color: #94a3b8 !important;
    }

    body.dark-mode .table tbody tr:hover {
        background-color: #1a1a1e !important;
    }

    body.dark-mode .btn-white {
        background-color: #1f1f23 !important;
        border-color: #27272a !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .btn-white:hover {
        border-color: #ffcc00 !important;
        color: #ffcc00 !important;
    }

    body.dark-mode code {
        background-color: #1f1f23 !important;
        color: #ffcc00 !important;
    }

    body.dark-mode .bg-primary.bg-opacity-10 { background-color: rgba(56, 189, 248, 0.15) !important; }
    body.dark-mode .bg-success.bg-opacity-10 { background-color: rgba(34, 197, 94, 0.15) !important; }
    body.dark-mode .bg-warning.bg-opacity-10 { background-color: rgba(234, 179, 8, 0.15) !important; }
    body.dark-mode .bg-info.bg-opacity-10 { background-color: rgba(56, 189, 248, 0.15) !important; }
    body.dark-mode .bg-danger.bg-opacity-10 { background-color: rgba(239, 68, 68, 0.15) !important; }
    body.dark-mode .bg-secondary.bg-opacity-10 { background-color: rgba(148, 163, 184, 0.15) !important; }
    body.dark-mode .border-light { border-color: #27272a !important; }
</style>

<div id="sidebarBackdrop" class="sidebar-backdrop"></div>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-2 p-0 d-none d-lg-block mobile-sidebar-container" id="sidebarWrapper">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col-12 col-lg-10 p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">
                
                <div class="card welcome-card shadow-lg border-0 mb-4">
                    <div class="card-body p-4 p-md-5">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-8 text-center text-md-start">
                                <h2 class="fw-bold mb-2">Welcome back, <span style="color: #ffcc00;"><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></span>! 👋</h2>
                                <p class="lead opacity-75 fs-6 fs-md-5 mb-0">Platform summary and system control center • <?= date('F d, Y') ?></p>

                                <?php
                                    // ---- Show current branch view ----
                                    $viewBranch = $_SESSION['view_branch'] ?? 'all';
                                    if ($viewBranch === 'all') {
                                        echo '<div class="welcome-branch-badge"><i class="bi bi-globe2"></i> Viewing All Branches</div>';
                                    } else {
                                        $bName = null;
                                        $bQ = mysqli_query($conn, "SELECT name FROM branches WHERE id = " . (int)$viewBranch);
                                        if ($bQ && $bRow = mysqli_fetch_assoc($bQ)) { $bName = $bRow['name']; }
                                        echo '<div class="welcome-branch-badge"><i class="bi bi-shop"></i> Viewing: ' . htmlspecialchars($bName ?? 'Branch') . '</div>';
                                    }
                                ?>

                                <div class="mt-3">
                                    <a href="contact_messages.php" class="btn btn-book">
                                        <i class="bi bi-envelope-fill me-2"></i>View Messages
                                        <?php if ($unread_count > 0): ?>
                                            <span class="badge bg-danger rounded-circle ms-2"><?= $unread_count ?></span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-4 text-end d-none d-md-block">
                                <i class="bi bi-shield-lock" style="font-size: 8rem; opacity: 0.15;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-car-front-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= $total_cars ?></div>
                                    <div class="stat-label">Total Fleet</div>
                                    <small class="text-success stat-subtext"><?= $available_cars ?> Available</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <div>
                                    <div class="stat-value">₱<?= number_format($monthly_revenue, 0) ?></div>
                                    <div class="stat-label">Monthly Net</div>
                                    <small class="text-muted stat-subtext">This month</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-key-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= $active_bookings ?></div>
                                    <div class="stat-label">Active Trips</div>
                                    <small class="text-muted stat-subtext">Currently Rented</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                                <div>
                                    <div class="stat-value">₱<?= formatNumberShort($total_revenue) ?></div>
                                    <div class="stat-label">Total Revenue</div>
                                    <small class="text-muted stat-subtext">All time</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-calendar-check-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= $total_bookings ?></div>
                                    <div class="stat-label">Total Bookings</div>
                                    <small class="text-muted stat-subtext"><?= $completed_bookings ?> Completed</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= $total_customers ?></div>
                                    <div class="stat-label">Customers</div>
                                    <small class="text-muted stat-subtext">Registered users</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value <?= $pending_tasks > 0 ? 'text-danger' : '' ?>"><?= $pending_tasks ?></div>
                                    <div class="stat-label">Pending Tasks</div>
                                    <small class="text-muted stat-subtext">Awaiting check</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                                    <i class="bi bi-person-badge-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= $total_operators ?></div>
                                    <div class="stat-label">Operators</div>
                                    <small class="text-muted stat-subtext">Active partners</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-7 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Recent Bookings</h5>
                            <a href="bookings_online.php" class="small fw-semibold text-decoration-none text-primary">View All &rarr;</a>
                        </div>
                        
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden desktop-table-card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="border-0 px-3 py-3">Customer</th>
                                                <th class="border-0 py-3">Vehicle</th>
                                                <th class="border-0 py-3">Dates</th>
                                                <th class="border-0 py-3">Total</th>
                                                <th class="border-0 py-3 text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recent_bookings)): ?>
                                                <?php foreach ($recent_bookings as $booking): ?>
                                                    <tr>
                                                        <td class="px-3 py-3">
                                                            <div class="fw-bold text-dark"><?= htmlspecialchars($booking['customer_name'] ?? 'Guest') ?></div>
                                                            <div class="text-muted small">#BK-<?= $booking['id'] ?></div>
                                                        </td>
                                                        <td class="py-3">
                                                            <div class="fw-semibold"><?= htmlspecialchars($booking['brand']) ?> <?= htmlspecialchars($booking['model']) ?></div>
                                                            <div class="text-muted small"><?= htmlspecialchars($booking['plate_number']) ?></div>
                                                        </td>
                                                        <td class="py-3">
                                                            <div class="small"><?= date('M d', strtotime($booking['start_date'])) ?> - <?= date('M d', strtotime($booking['end_date'])) ?></div>
                                                            <div class="text-muted small"><?= date('h:i A', strtotime($booking['pickup_time'])) ?></div>
                                                        </td>
                                                        <td class="py-3 fw-bold">₱<?= number_format($booking['total_price'], 2) ?></td>
                                                        <td class="py-3 text-center">
                                                            <span class="status-badge status-<?= strtolower($booking['status']) ?>">
                                                                <?= $booking['status'] ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">No recent bookings found.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="mobile-card-container">
                            <?php if (!empty($recent_bookings)): ?>
                                <?php foreach ($recent_bookings as $booking): ?>
                                    <div class="responsive-mobile-card shadow-sm">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <div class="fw-bold text-dark mb-0" style="font-size: 0.95rem;"><?= htmlspecialchars($booking['customer_name'] ?? 'Guest') ?></div>
                                                <small class="text-muted">ID: #BK-<?= $booking['id'] ?></small>
                                            </div>
                                            <span class="status-badge status-<?= strtolower($booking['status']) ?>">
                                                <?= $booking['status'] ?>
                                            </span>
                                        </div>
                                        <div class="border-top border-bottom py-2 my-2">
                                            <div class="small fw-semibold"><i class="bi bi-car-front me-2 text-secondary"></i><?= htmlspecialchars($booking['brand']) ?> <?= htmlspecialchars($booking['model']) ?></div>
                                            <div class="text-muted small">Plate: <code><?= htmlspecialchars($booking['plate_number']) ?></code></div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center pt-1">
                                            <div class="text-muted small">
                                                <i class="bi bi-calendar3 me-1"></i> <?= date('M d', strtotime($booking['start_date'])) ?> - <?= date('M d', strtotime($booking['end_date'])) ?>
                                            </div>
                                            <div class="fw-bold text-primary" style="font-size: 1rem;">
                                                ₱<?= number_format($booking['total_price'], 2) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="responsive-mobile-card text-center text-muted py-4">No recent bookings found.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Recent Vehicles</h5>
                            <a href="../shared/cars.php" class="small fw-semibold text-decoration-none text-primary">View All &rarr;</a>
                        </div>

                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 desktop-table-card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="border-0 px-3 py-3">Vehicle</th>
                                                <th class="border-0 py-3">Plate</th>
                                                <th class="border-0 py-3 text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recent_cars)): ?>
                                                <?php foreach ($recent_cars as $car): ?>
                                                    <tr>
                                                        <td class="px-3 py-3">
                                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($car['brand']) ?> <?= htmlspecialchars($car['model']) ?></div>
                                                        </td>
                                                        <td class="py-3"><code><?= htmlspecialchars($car['plate_number']) ?></code></td>
                                                        <td class="py-3 text-center">
                                                            <?php 
                                                                $status = $car['status'];
                                                                $badgeClass = match($status) {
                                                                    'Available' => 'bg-success',
                                                                    'Active', 'Rented' => 'bg-warning text-dark',
                                                                    'Maintenance' => 'bg-danger',
                                                                    default => 'bg-secondary'
                                                                };
                                                            ?>
                                                            <span class="badge <?= $badgeClass ?> rounded-pill px-3 py-1.5"><?= $status ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center py-4 text-muted">No vehicles found.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="mobile-card-container mb-4">
                            <?php if (!empty($recent_cars)): ?>
                                <?php foreach ($recent_cars as $car): ?>
                                    <div class="responsive-mobile-card shadow-sm d-flex justify-content-between align-items-center py-3">
                                        <div>
                                            <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($car['brand']) ?> <?= htmlspecialchars($car['model']) ?></div>
                                            <span class="text-muted small">Plate: <code><?= htmlspecialchars($car['plate_number']) ?></code></span>
                                        </div>
                                        <?php 
                                            $status = $car['status'];
                                            $badgeClass = match($status) {
                                                'Available' => 'bg-success',
                                                'Active', 'Rented' => 'bg-warning text-dark',
                                                'Maintenance' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };
                                        ?>
                                        <span class="badge <?= $badgeClass ?> rounded-pill px-3 py-2" style="font-size: 0.75rem; min-width: 90px; text-align: center;">
                                            <?= $status ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="responsive-mobile-card text-center text-muted py-4">No vehicles found.</div>
                            <?php endif; ?>
                        </div>

                        <h5 class="fw-bold mb-3">System Priority</h5>
                        <div class="card border-0 shadow-sm rounded-4 p-4">
                            <div class="d-flex gap-3 mb-4">
                                <div class="icon-shape text-warning flex-shrink-0">
                                    <i class="bi bi-tools"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1 small text-dark">Pending Bookings</h6>
                                    <p class="text-muted mb-0" style="font-size: 0.75rem;"><?= $pending_tasks ?> booking(s) awaiting confirmation.</p>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-3 mb-4">
                                <div class="icon-shape text-info flex-shrink-0">
                                    <i class="bi bi-wallet2"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1 small text-dark">Revenue Overview</h6>
                                    <p class="text-muted mb-0" style="font-size: 0.75rem;">₱<?= number_format($monthly_revenue, 2) ?> earned this month.</p>
                                </div>
                            </div>

                            <div class="d-flex gap-3">
                                <div class="icon-shape text-success flex-shrink-0">
                                    <i class="bi bi-car-front"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1 small text-dark">Fleet Status</h6>
                                    <p class="text-muted mb-0" style="font-size: 0.75rem;"><?= $available_cars ?> cars available for rent.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="mt-auto">
                <?php require_once __DIR__ . '/../components/footer.php'; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const dynamicHeaderArea = document.querySelector('.main-content header, .main-content nav, .container-fluid');
    let toggleBtn = null;
    
    if (dynamicHeaderArea) {
        const componentButtons = dynamicHeaderArea.getElementsByTagName('button');
        for (let btn of componentButtons) {
            if (btn.querySelector('.bi-list') || btn.innerHTML.includes('<span') || btn.className.includes('navbar-toggler')) {
                toggleBtn = btn;
                break;
            }
        }
    }
    
    if (!toggleBtn) {
        toggleBtn = document.querySelector('header button, .navbar-toggler, .bg-warning button');
    }

    const sidebar = document.getElementById("sidebarWrapper");
    const backdrop = document.getElementById("sidebarBackdrop");

    if (toggleBtn && sidebar && backdrop) {
        function toggleSidebar() {
            sidebar.classList.toggle("show");
            backdrop.classList.toggle("show");
        }

        toggleBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });

        backdrop.addEventListener("click", toggleSidebar);
    }
});
</script>