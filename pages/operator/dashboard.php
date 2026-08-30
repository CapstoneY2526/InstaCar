<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'operator') {
    $_SESSION['error'] = "Unauthorized access.";
    ?>
    <script>window.location.href = "../../index.php";</script>
    <?php
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$current_month = (int)date('m');
$current_year = (int)date('Y');

// --- FETCH OPERATOR-SPECIFIC DATA ---

// Total Cars Owned by this Operator
$total_my_cars = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM cars WHERE user_id = $user_id"))['total'] ?? 0;

// Available Cars
$available_cars = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM cars WHERE user_id = $user_id AND status = 'Available'"))['total'] ?? 0;

// Current Active Rentals (Rented or Active)
$active_rentals = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM cars WHERE user_id = $user_id AND status IN ('Active', 'Rented')"))['total'] ?? 0;

// Monthly Earnings (Net)
$monthly_earnings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(bp.total_net), 0) as total 
                 FROM booking_payments bp
                 JOIN bookings b ON bp.booking_id = b.id
                 JOIN cars c ON b.car_id = c.id
                 WHERE c.user_id = $user_id AND MONTH(bp.created_at) = $current_month AND YEAR(bp.created_at) = $current_year"))['total'] ?? 0;

// Total Earnings (All time)
$total_earnings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(bp.total_net), 0) as total 
                 FROM booking_payments bp
                 JOIN bookings b ON bp.booking_id = b.id
                 JOIN cars c ON b.car_id = c.id
                 WHERE c.user_id = $user_id"))['total'] ?? 0;

// Total Pending Balance/Remittance
$pending_remittance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(bp.owner_balance), 0) as total 
                FROM booking_payments bp
                JOIN bookings b ON bp.booking_id = b.id
                JOIN cars c ON b.car_id = c.id
                WHERE c.user_id = $user_id"))['total'] ?? 0;

// Total Bookings
$total_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(b.id) as total 
                FROM bookings b
                JOIN cars c ON b.car_id = c.id
                WHERE c.user_id = $user_id"))['total'] ?? 0;

// Recent Cars List
$my_recent_cars = mysqli_query($conn, "SELECT id, brand, model, plate_number, status, price_24_hours FROM cars WHERE user_id = $user_id ORDER BY id DESC LIMIT 5");

// Convert result set into an array
$cars_list = [];
if ($my_recent_cars && mysqli_num_rows($my_recent_cars) > 0) {
    while($car = mysqli_fetch_assoc($my_recent_cars)) {
        $cars_list[] = $car;
    }
}

$pageTitle = 'Operator Dashboard';
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

    /* Mobile & Tablet Mode Car Cards Layout */
    .mobile-vehicle-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.25rem;
        margin: 0.75rem 1rem;
        transition: box-shadow 0.2s ease, background-color 0.25s ease, border-color 0.25s ease;
    }
    .mobile-vehicle-card:last-child {
        margin-bottom: 1.25rem;
    }
    .mobile-vehicle-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

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

    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-block;
    }
    .status-available { background: #d1fae5; color: #059669; }
    .status-rented { background: #dbeafe; color: #2563eb; }
    .status-active { background: #fef3c7; color: #d97706; }
    .status-maintenance { background: #fee2e2; color: #dc2626; }

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
    body.dark-mode .mobile-vehicle-card {
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
                
                <div class="card welcome-card shadow-lg border-0 mb-4">
                    <div class="card-body p-4 p-md-5">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-8 text-center text-md-start">
                                <h2 class="fw-bold mb-2">Welcome back, <span style="color: #ffcc00;"><?= htmlspecialchars($_SESSION['name'] ?? 'Operator') ?></span>! 👋</h2>
                                <p class="lead opacity-75 fs-6 fs-md-5 mb-0">Track performance, manage listings, and monitor fleet earnings for <?= date('F Y') ?>.</p>
                                <a href="../shared/cars.php" class="btn btn-book mt-4">
                                    <i class="bi bi-plus-circle me-2"></i>Manage Fleet
                                </a>
                            </div>
                            <div class="col-md-4 text-end d-none d-md-block">
                                <i class="bi bi-speedometer2" style="font-size: 8rem; opacity: 0.15;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6 col-xl-3">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">My Fleet</div>
                                    <div class="stat-value"><?= $total_my_cars ?></div>
                                    <small class="text-muted d-none d-sm-inline-block"><?= $available_cars ?> available</small>
                                </div>
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-car-front-fill fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Active Trips</div>
                                    <div class="stat-value"><?= $active_rentals ?></div>
                                    <small class="text-muted d-none d-sm-inline-block">In progress</small>
                                </div>
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-key-fill fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Bookings</div>
                                    <div class="stat-value"><?= $total_bookings ?></div>
                                    <small class="text-muted d-none d-sm-inline-block">All time records</small>
                                </div>
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-calendar-check-fill fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Pending Bal</div>
                                    <div class="stat-value text-warning text-truncate" style="max-width: 130px;">₱<?= number_format($pending_remittance, 0) ?></div>
                                    <small class="text-muted d-none d-sm-inline-block">To collect</small>
                                </div>
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-cash-stack fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-5">
                    <div class="col-6">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Monthly Net</div>
                                    <div class="stat-value text-primary text-truncate" style="max-width: 140px;">₱<?= number_format($monthly_earnings, 0) ?></div>
                                    <small class="text-muted d-none d-sm-inline-block"><?= date('F Y') ?></small>
                                </div>
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-graph-up fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Total Net</div>
                                    <div class="stat-value text-success text-truncate" style="max-width: 140px;">₱<?= number_format($total_earnings, 0) ?></div>
                                    <small class="text-muted d-none d-sm-inline-block">Gross overall</small>
                                </div>
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-cash-coin fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 px-1">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-car-front-fill me-2 text-primary"></i>My Vehicles
                    </h5>
                    <a href="../shared/cars.php" class="small fw-semibold text-decoration-none text-primary">
                        Manage Fleet &rarr;
                    </a>
                </div>
                
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-0 d-none d-lg-block">
                        <div class="table-responsive">
                            <table class="table table-custom align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4 py-3">Vehicle</th>
                                        <th class="py-3">Plate Number</th>
                                        <th class="py-3 text-center">Status</th>
                                        <th class="py-3 text-end">Daily Rate</th>
                                        <th class="pe-4 py-3 text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(!empty($cars_list)): ?>
                                        <?php foreach($cars_list as $car): ?>
                                            <tr>
                                                <td class="ps-4 py-3">
                                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?></div>
                                                 </td>
                                                <td class="py-3">
                                                    <code><?= htmlspecialchars($car['plate_number']) ?></code>
                                                </td>
                                                <td class="py-3 text-center">
                                                    <?php 
                                                        $status = $car['status'];
                                                        $badgeClass = '';
                                                        if ($status == 'Available') $badgeClass = 'status-available';
                                                        elseif ($status == 'Rented') $badgeClass = 'status-rented';
                                                        elseif ($status == 'Active') $badgeClass = 'status-active';
                                                        else $badgeClass = 'status-maintenance';
                                                    ?>
                                                    <span class="status-badge <?= $badgeClass ?>">
                                                        <?= $status ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 text-end">
                                                    <span class="fw-bold">₱<?= number_format($car['price_24_hours'], 2) ?></span>
                                                </td>
                                                <td class="pe-4 py-3 text-end">
                                                    <a href="view_car.php?id=<?= $car['id'] ?>" class="btn btn-sm btn-light border rounded-pill px-3">
                                                        <i class="bi bi-eye me-1"></i>View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="bi bi-car-front display-6 d-block mb-2 opacity-50"></i>
                                                No vehicles found. Add your first car to get started.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-block d-lg-none border-top">
                        <?php if(!empty($cars_list)): ?>
                            <div class="row g-0">
                                <?php foreach($cars_list as $car): 
                                    $status = $car['status'];
                                    $badgeClass = '';
                                    if ($status == 'Available') $badgeClass = 'status-available';
                                    elseif ($status == 'Rented') $badgeClass = 'status-rented';
                                    elseif ($status == 'Active') $badgeClass = 'status-active';
                                    else $badgeClass = 'status-maintenance';
                                ?>
                                    <div class="col-12 col-md-6">
                                        <div class="mobile-vehicle-card shadow-sm">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?></h6>
                                                    <div class="text-muted small">
                                                        <i class="bi bi-hash me-1"></i>Plate: <code><?= htmlspecialchars($car['plate_number']) ?></code>
                                                    </div>
                                                </div>
                                                <span class="status-badge <?= $badgeClass ?>"><?= $status ?></span>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-light">
                                                <div>
                                                    <small class="text-muted d-block" style="font-size: 11px;">Daily Rental Rate:</small>
                                                    <span class="fw-bold">₱<?= number_format($car['price_24_hours'], 2) ?></span>
                                                </div>
                                                <a href="view_car.php?id=<?= $car['id'] ?>" class="btn btn-sm btn-light border rounded-pill px-3">
                                                    <i class="bi bi-eye me-1"></i>Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-car-front display-6 d-block mb-2 opacity-50"></i>
                                <p class="mb-0 small">No vehicles found. Add your first car to get started.</p>
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