<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. Auth Check — staff only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    $_SESSION['error'] = "Unauthorized access.";
    ?>
    <script>window.location.href = "../../index.php";</script>
    <?php
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

// Branch hard-scope for staff (Option B):
// staff only ever see their own branch. If somehow no branch is assigned, show nothing
// rather than leaking all branches.
$branch_id      = (int)($_SESSION['branch_id'] ?? 0);
$branch_scope   = $branch_id > 0 ? " AND branch_id = $branch_id"   : " AND 1=0";
$branch_scope_b = $branch_id > 0 ? " AND b.branch_id = $branch_id" : " AND 1=0";

// --- FETCH STAFF-FOCUSED DATA ---

// Today's Pickups (bookings starting today, not cancelled)
$pickups_today = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(id) AS total
    FROM bookings
    WHERE DATE(start_date) = '$today'
      AND status NOT IN ('Cancelled', 'Completed')
      $branch_scope
"))['total'] ?? 0;

// Today's Returns (bookings ending today)
$returns_today = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(id) AS total
    FROM bookings
    WHERE DATE(end_date) = '$today'
      AND status NOT IN ('Cancelled', 'Completed')
      $branch_scope
"))['total'] ?? 0;

// Pending Bookings needing action
$pending_bookings = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(id) AS total
    FROM bookings
    WHERE status = 'Pending'
      $branch_scope
"))['total'] ?? 0;

// Active Rentals (currently out on the road)
$active_rentals = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(id) AS total
    FROM bookings
    WHERE status = 'Active'
      $branch_scope
"))['total'] ?? 0;

// --- TODAY'S SCHEDULE (pickups + returns merged) ---
$schedule_q = mysqli_query($conn, "
    SELECT b.id, b.start_date, b.end_date, b.status,
           c.brand, c.model, c.plate_number,
           COALESCE(u.name, b.guest_name)    AS customer_name,
           COALESCE(u.phone, b.phone_number) AS customer_phone
    FROM bookings b
    JOIN cars  c ON b.car_id = c.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE (DATE(b.start_date) = '$today' OR DATE(b.end_date) = '$today')
      AND b.status NOT IN ('Cancelled')
      $branch_scope_b
    ORDER BY b.start_date ASC
    LIMIT 10
");

$today_schedule = [];
if ($schedule_q && mysqli_num_rows($schedule_q) > 0) {
    while ($row = mysqli_fetch_assoc($schedule_q)) {
        $today_schedule[] = $row;
    }
}

// --- RECENT BOOKINGS ---
$recent_q = mysqli_query($conn, "
    SELECT b.id, b.start_date, b.end_date, b.status, b.total_price,
           c.brand, c.model, c.plate_number,
           COALESCE(u.name, b.guest_name) AS customer_name
    FROM bookings b
    JOIN cars  c ON b.car_id = c.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE 1=1 $branch_scope_b
    ORDER BY b.id DESC
    LIMIT 5
");

$recent_bookings = [];
if ($recent_q && mysqli_num_rows($recent_q) > 0) {
    while ($row = mysqli_fetch_assoc($recent_q)) {
        $recent_bookings[] = $row;
    }
}

$pageTitle = 'Staff Dashboard';
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

    /* Stat Cards Base & Yellow Hover Glow Effect */
    .stat-card { 
        border-radius: 1.25rem; 
        border: 1px solid #edf2f7; 
        background-color: #ffffff;
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
        flex-shrink: 0;
    }

    .stat-value {
        font-size: 1.4rem;
        font-weight: 700;
        line-height: 1.2;
        color: #0f172a;
    }

    .stat-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 4px;
    }

    /* Schedule item rows */
    .schedule-item {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 14px 16px;
        transition: box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.25s ease;
    }

    .schedule-item:hover {
        border-color: #ffcc00;
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.15);
    }

    .time-chip {
        background: #fef3c7;
        color: #92400e;
        font-weight: 700;
        font-size: 11px;
        padding: 4px 10px;
        border-radius: 20px;
        white-space: nowrap;
    }

    .time-chip.return {
        background: #dbeafe;
        color: #1e40af;
    }

    /* Status badges */
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-block;
    }
    .status-pending   { background: #fef3c7; color: #d97706; }
    .status-confirmed { background: #d1fae5; color: #059669; }
    .status-active    { background: #dbeafe; color: #2563eb; }
    .status-completed { background: #e5e7eb; color: #374151; }
    .status-cancelled { background: #fee2e2; color: #dc2626; }

    /* Table */
    .table-custom thead th {
        background: #f8fafc;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        border-bottom: 1px solid #e2e8f0;
    }

    .table-custom tbody tr:hover {
        background-color: #fef9e3;
        transition: background 0.2s;
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

        .welcome-card { padding: 2rem !important; }
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
    body.dark-mode .schedule-item {
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

    body.dark-mode .table-custom,
    body.dark-mode .table-custom tr,
    body.dark-mode .table-custom td,
    body.dark-mode .table-custom th {
        background-color: #141414 !important;
        color: #f1f5f9 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .table-custom thead,
    body.dark-mode .table-custom thead tr,
    body.dark-mode .table-custom thead th {
        background-color: #1f1f23 !important;
        color: #94a3b8 !important;
    }

    body.dark-mode .table-custom tbody tr:hover {
        background-color: #1a1a1e !important;
    }

    body.dark-mode .bg-primary.bg-opacity-10 {
        background-color: rgba(56, 189, 248, 0.15) !important;
    }
    body.dark-mode .bg-success.bg-opacity-10 {
        background-color: rgba(34, 197, 94, 0.15) !important;
    }
    body.dark-mode .bg-warning.bg-opacity-10 {
        background-color: rgba(234, 179, 8, 0.15) !important;
    }
    body.dark-mode .bg-info.bg-opacity-10 {
        background-color: rgba(56, 189, 248, 0.15) !important;
    }
    body.dark-mode .bg-danger.bg-opacity-10 {
        background-color: rgba(239, 68, 68, 0.15) !important;
    }
    body.dark-mode .border-light {
        border-color: #27272a !important;
    }

    body.dark-mode .btn-light {
        background-color: #1f1f23 !important;
        border-color: #27272a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode code {
        background-color: #1f1f23;
        color: #ffcc00;
    }
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

                <!-- Welcome banner -->
                <div class="card welcome-card shadow-lg border-0 mb-4">
                    <div class="card-body p-4 p-md-5">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-8 text-center text-md-start">
                                <h2 class="fw-bold mb-2">
                                    Good day, <span style="color: #ffcc00;"><?= htmlspecialchars($_SESSION['name'] ?? 'Staff') ?></span>! 👋
                                </h2>
                                <p class="lead opacity-75 fs-6 fs-md-5 mb-0">
                                    You have <strong><?= $pickups_today ?></strong> pickup<?= $pickups_today === 1 ? '' : 's' ?>
                                    and <strong><?= $returns_today ?></strong> return<?= $returns_today === 1 ? '' : 's' ?>
                                    scheduled for today — <?= date('F j, Y') ?>.
                                </p>
                                <a href="../staff/bookings.php" class="btn btn-book mt-4">
                                    <i class="bi bi-journal-check me-2"></i>View All Bookings
                                </a>
                            </div>
                            <div class="col-md-4 text-end d-none d-md-block">
                                <i class="bi bi-clipboard-check" style="font-size: 8rem; opacity: 0.15;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stat cards -->
                <div class="row g-3 mb-3">
                    <div class="col-6 col-xl-3">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Today's Pickups</div>
                                    <div class="stat-value"><?= $pickups_today ?></div>
                                    <small class="text-muted d-none d-sm-inline-block">Cars going out</small>
                                </div>
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-box-arrow-up-right fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Today's Returns</div>
                                    <div class="stat-value"><?= $returns_today ?></div>
                                    <small class="text-muted d-none d-sm-inline-block">Cars coming in</small>
                                </div>
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-box-arrow-in-down-left fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Pending</div>
                                    <div class="stat-value"><?= $pending_bookings ?></div>
                                    <small class="text-muted d-none d-sm-inline-block">Awaiting action</small>
                                </div>
                                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                                    <i class="bi bi-hourglass-split fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Active Rentals</div>
                                    <div class="stat-value"><?= $active_rentals ?></div>
                                    <small class="text-muted d-none d-sm-inline-block">On the road</small>
                                </div>
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-key-fill fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Schedule -->
                <div class="d-flex justify-content-between align-items-center mb-3 mt-4 px-1">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-clock-history me-2 text-warning"></i>Today's Schedule
                    </h5>
                    <span class="badge rounded-pill" style="background: rgba(255,204,0,0.15); color: #b38a00;">
                        <?= count($today_schedule) ?> item<?= count($today_schedule) === 1 ? '' : 's' ?>
                    </span>
                </div>

                <div class="card border-0 shadow-sm rounded-4 mb-5">
                    <div class="card-body p-3">
                        <?php if (empty($today_schedule)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-calendar2-check d-block mb-2" style="font-size: 2.5rem; opacity: 0.3;"></i>
                                <p class="small mb-0">No pickups or returns scheduled for today.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($today_schedule as $item):
                                $isPickup = date('Y-m-d', strtotime($item['start_date'])) === $today;
                                $isReturn = date('Y-m-d', strtotime($item['end_date']))   === $today;

                                $statusClass = 'status-pending';
                                if ($item['status'] === 'Confirmed') $statusClass = 'status-confirmed';
                                elseif ($item['status'] === 'Active')    $statusClass = 'status-active';
                                elseif ($item['status'] === 'Completed') $statusClass = 'status-completed';
                                elseif ($item['status'] === 'Cancelled') $statusClass = 'status-cancelled';
                            ?>
                                <div class="schedule-item d-flex flex-wrap align-items-center gap-3 mb-2">
                                    <div class="flex-shrink-0">
                                        <span class="time-chip <?= $isReturn && !$isPickup ? 'return' : '' ?>">
                                            <?= $isPickup ? 'PICKUP' : ($isReturn ? 'RETURN' : 'TODAY') ?>
                                        </span>
                                    </div>

                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-bold text-dark">
                                            <?= htmlspecialchars($item['brand'] . ' ' . $item['model']) ?>
                                            <span class="text-muted small">• <?= htmlspecialchars($item['plate_number']) ?></span>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($item['customer_name'] ?? 'Guest') ?>
                                            <?php if (!empty($item['customer_phone'])): ?>
                                                <span class="ms-2"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($item['customer_phone']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="flex-shrink-0">
                                        <span class="status-badge <?= $statusClass ?>"><?= $item['status'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="d-flex justify-content-between align-items-center mb-3 px-1">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-journal-text me-2 text-primary"></i>Recent Bookings
                    </h5>
                    <a href="../staff/bookings.php" class="small fw-semibold text-decoration-none text-primary">
                        View All &rarr;
                    </a>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-custom align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4 py-3">Vehicle</th>
                                        <th class="py-3">Customer</th>
                                        <th class="py-3">Dates</th>
                                        <th class="py-3 text-center">Status</th>
                                        <th class="pe-4 py-3 text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_bookings)): ?>
                                        <?php foreach ($recent_bookings as $b):
                                            $statusClass = 'status-pending';
                                            if ($b['status'] === 'Confirmed') $statusClass = 'status-confirmed';
                                            elseif ($b['status'] === 'Active')    $statusClass = 'status-active';
                                            elseif ($b['status'] === 'Completed') $statusClass = 'status-completed';
                                            elseif ($b['status'] === 'Cancelled') $statusClass = 'status-cancelled';
                                        ?>
                                            <tr>
                                                <td class="ps-4 py-3">
                                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($b['brand'] . ' ' . $b['model']) ?></div>
                                                    <code class="small"><?= htmlspecialchars($b['plate_number']) ?></code>
                                                </td>
                                                <td class="py-3"><?= htmlspecialchars($b['customer_name'] ?? 'Guest') ?></td>
                                                <td class="py-3">
                                                    <div class="small">
                                                        <?= date('M j', strtotime($b['start_date'])) ?> →
                                                        <?= date('M j', strtotime($b['end_date'])) ?>
                                                    </div>
                                                </td>
                                                <td class="py-3 text-center">
                                                    <span class="status-badge <?= $statusClass ?>"><?= $b['status'] ?></span>
                                                </td>
                                                <td class="pe-4 py-3 text-end">
                                                    <span class="fw-bold">₱<?= number_format($b['total_price'], 2) ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="bi bi-journal display-6 d-block mb-2 opacity-50"></i>
                                                No bookings yet.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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