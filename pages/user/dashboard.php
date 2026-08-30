<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// --- AUTHENTICATION CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    $_SESSION['error'] = "Unauthorized access.";
    ?>
    <script>window.location.href = "../../index.php";</script>
    <?php
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// --- FETCH USER-SPECIFIC DATA ---

// 1. Total Bookings (All time)
$total_query = mysqli_query($conn, "SELECT COUNT(id) as total FROM bookings WHERE user_id = $user_id");
$total_bookings = mysqli_fetch_assoc($total_query)['total'] ?? 0;

// 2. Active Rental (Currently Approved or Ongoing)
$active_query = mysqli_query($conn, "SELECT COUNT(id) as total FROM bookings WHERE user_id = $user_id AND status = 'Approved'");
$active_rentals = mysqli_fetch_assoc($active_query)['total'] ?? 0;

// 3. Total Spent
$spent_query = mysqli_query($conn, "SELECT SUM(total_price) as total FROM bookings WHERE user_id = $user_id AND status = 'Completed'");
$total_spent = mysqli_fetch_assoc($spent_query)['total'] ?? 0;

// 4. Recent Bookings List
$recent_query = "SELECT b.id, b.start_date, b.end_date, b.status, b.total_price, c.brand, c.model 
                 FROM bookings b 
                 JOIN cars c ON b.car_id = c.id 
                 WHERE b.user_id = $user_id 
                 ORDER BY b.created_at DESC LIMIT 5";
$recent_bookings = mysqli_query($conn, $recent_query);

// Convert mysql result object to array so it can be cleanly re-looped on both Desktop (Table) and Mobile/Tablet (Cards)
$bookings_list = [];
if ($recent_bookings && $recent_bookings->num_rows > 0) {
    while($row = $recent_bookings->fetch_assoc()) {
        $bookings_list[] = $row;
    }
}

$pageTitle = 'My Dashboard';
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

    .welcome-card {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #ffffff;
        border-radius: 1.5rem;
        position: relative;
        overflow: hidden;
    }

    /* "Find a Car" Button - Compact & Yellow Glow on Hover */
    .welcome-card .btn-book,
    .welcome-card .btn-book *,
    body.dark-mode .welcome-card .btn-book,
    body.dark-mode .welcome-card .btn-book * { 
        background-color: #ffcc00 !important;
        border: none !important;
        color: #000000 !important;
        border-radius: 12px; 
        font-weight: 700 !important; 
        padding: 10px 30px 10px 10px; 
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
    }

    .stat-card:hover { 
        transform: translateY(-3px); 
        border-color: #ffcc00 !important;
        box-shadow: 0 0 15px rgba(255, 204, 0, 0.4) !important;
    }

    /* Mobile/Tablet Booking Cards Look & Feel */
    .mobile-booking-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.25rem;
        margin: 0.75rem 1rem;
        transition: box-shadow 0.2s ease, background-color 0.25s ease, border-color 0.25s ease;
    }
    .mobile-booking-card:last-child {
        margin-bottom: 1.25rem;
    }
    .mobile-booking-card:hover {
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

    /* Header & Footer Layout Wrappers */
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

    /* Mobile Sidebar Drawer Dark Style */
    body.dark-mode .mobile-sidebar-container {
        background-color: #141414 !important;
        border-right: 1px solid #27272a !important;
    }

    /* Typography Scoped to Content Areas */
    body.dark-mode .main-content .text-dark,
    body.dark-mode .main-content h2,
    body.dark-mode .main-content h3,
    body.dark-mode .main-content h4,
    body.dark-mode .main-content h5,
    body.dark-mode .main-content h6,
    body.dark-mode .main-content label {
        color: #ffffff !important;
    }

    body.dark-mode .main-content .text-muted,
    body.dark-mode .main-content .text-secondary {
        color: #cbd5e1 !important;
    }

    body.dark-mode .main-content .text-primary {
        color: #38bdf8 !important;
    }

    /* Welcome Card Dark Accent */
    body.dark-mode .welcome-card {
        background: linear-gradient(135deg, #18181b 0%, #09090b 100%) !important;
        border: 1px solid #27272a !important;
    }

    /* Dark Mode Cards & Yellow Glow Hover */
    body.dark-mode .card,
    body.dark-mode .mobile-booking-card {
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

    /* Table Component Dark Overrides */
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
    body.dark-mode .table thead th,
    body.dark-mode .bg-light {
        background-color: #1f1f23 !important;
        color: #94a3b8 !important;
    }

    body.dark-mode .table tbody tr:hover {
        background-color: #1a1a1e !important;
    }

    /* Status Badge Dark Overrides */
    body.dark-mode .badge.bg-primary {
        background-color: #0284c7 !important;
        color: #ffffff !important;
    }
    body.dark-mode .badge.bg-success {
        background-color: #166534 !important;
        color: #ffffff !important;
    }
    body.dark-mode .badge.bg-danger {
        background-color: #991b1b !important;
        color: #ffffff !important;
    }
    body.dark-mode .badge.bg-warning {
        background-color: #ca8a04 !important;
        color: #ffffff !important;
    }
    body.dark-mode .badge.bg-secondary {
        background-color: #3f3f46 !important;
        color: #f1f5f9 !important;
    }

    /* Subtle Icon Container Accents in Dark Mode */
    body.dark-mode .bg-primary.bg-opacity-10 {
        background-color: rgba(56, 189, 248, 0.15) !important;
    }
    body.dark-mode .bg-success.bg-opacity-10 {
        background-color: rgba(34, 197, 94, 0.15) !important;
    }
    body.dark-mode .bg-warning.bg-opacity-10 {
        background-color: rgba(234, 179, 8, 0.15) !important;
    }
    body.dark-mode .border-light {
        border-color: #27272a !important;
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
                
                <div class="card welcome-card shadow-lg border-0 mb-4">
                    <div class="card-body p-4 p-md-5">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-8 text-center text-md-start">
                                <h2 class="fw-bold mb-2">Hello, <span style="color: #ffcc00;"><?= htmlspecialchars($_SESSION['name']) ?></span>! 👋</h2>
                                <p class="lead opacity-75 fs-6 fs-md-5 mb-0">Ready for your next adventure? Browse our latest fleet and hit the road.</p>
                                <a href="cars.php" class="btn btn-book mt-4">
                                    <i class="bi bi-search me-2"></i>Find a Car
                                </a>
                            </div>
                            <div class="col-md-4 text-end d-none d-md-block">
                                <i class="bi bi-car-front" style="font-size: 8rem; opacity: 0.15;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 g-md-4 mb-4 mb-md-5">
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                                    <i class="bi bi-calendar-check fs-3"></i>
                                </div>
                                <div>
                                    <small class="text-muted fw-bold">My Bookings</small>
                                    <h3 class="fw-bold mb-0 text-dark"><?= $total_bookings ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                                    <i class="bi bi-clock-history fs-3"></i>
                                </div>
                                <div>
                                    <small class="text-muted fw-bold">Active Trips</small>
                                    <h3 class="fw-bold mb-0 text-dark"><?= $active_rentals ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3">
                                    <i class="bi bi-credit-card fs-3"></i>
                                </div>
                                <div>
                                    <small class="text-muted fw-bold">Total Spent</small>
                                    <h3 class="fw-bold mb-0 text-dark">₱<?= number_format($total_spent, 2) ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white border-0 pt-4 px-3 px-md-4 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-dark">My Recent Trips</h5>
                        <a href="mybookings.php" class="small fw-semibold text-decoration-none text-primary">View All</a>
                    </div>
                    
                    <div class="card-body p-0 d-none d-lg-block">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="bg-light">
                                    <tr class="text-uppercase small fw-bold text-muted">
                                        <th class="border-0 px-4 py-3">Vehicle</th>
                                        <th class="border-0 py-3">Duration</th>
                                        <th class="border-0 py-3 text-center">Status</th>
                                        <th class="border-0 py-3 text-end px-4">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(!empty($bookings_list)): ?>
                                        <?php foreach($bookings_list as $row): ?>
                                            <tr>
                                                <td class="px-4 py-3 align-middle">
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($row['brand'] . ' ' . $row['model']) ?></span>
                                                </td>
                                                <td class="py-3 align-middle">
                                                    <small class="text-muted">
                                                        <?= date('M d, Y', strtotime($row['start_date'])) ?> - <?= date('M d, Y', strtotime($row['end_date'])) ?>
                                                    </small>
                                                </td>
                                                <td class="py-3 align-middle text-center">
                                                    <?php 
                                                        $status = $row['status'];
                                                        $badge = 'bg-secondary';
                                                        if($status == 'Approved') $badge = 'bg-primary';
                                                        if($status == 'Completed') $badge = 'bg-success';
                                                        if($status == 'Cancelled') $badge = 'bg-danger';
                                                        if($status == 'Pending') $badge = 'bg-warning text-dark';
                                                    ?>
                                                    <span class="badge <?= $badge ?> rounded-pill px-3 py-2"><?= $status ?></span>
                                                </td>
                                                <td class="py-3 align-middle text-end px-4 fw-bold text-dark">
                                                    ₱<?= number_format($row['total_price'], 2) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <p class="mb-0">You haven't booked any cars yet.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-block d-lg-none border-top">
                        <?php if(!empty($bookings_list)): ?>
                            <div class="row g-0">
                                <?php foreach($bookings_list as $row): 
                                    $status = $row['status'];
                                    $badge = 'bg-secondary';
                                    if($status == 'Approved') $badge = 'bg-primary';
                                    if($status == 'Completed') $badge = 'bg-success';
                                    if($status == 'Cancelled') $badge = 'bg-danger';
                                    if($status == 'Pending') $badge = 'bg-warning text-dark';
                                ?>
                                    <div class="col-12 col-md-6">
                                        <div class="mobile-booking-card shadow-sm">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['brand'] . ' ' . $row['model']) ?></h6>
                                                    <div class="text-muted small">
                                                        <i class="bi bi-calendar3 me-1"></i>
                                                        <?= date('M d, Y', strtotime($row['start_date'])) ?> &rarr; <?= date('M d, Y', strtotime($row['end_date'])) ?>
                                                    </div>
                                                </div>
                                                <span class="badge <?= $badge ?> rounded-pill px-2.5 py-1.5"><?= $status ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-light">
                                                <span class="small text-muted">Total Paid Amount:</span>
                                                <span class="fw-bold text-primary">₱<?= number_format($row['total_price'], 2) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-folder2-open fs-2 d-block mb-2 opacity-50"></i>
                                <p class="mb-0 small">You haven't booked any cars yet.</p>
                            </div>
                        <?php endif; ?>
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
// Mobile Sidebar Active Target Capture Control Script
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