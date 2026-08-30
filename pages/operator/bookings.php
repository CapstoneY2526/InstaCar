<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth Check - Only Admin and Operator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'operator') {
    $_SESSION['error'] = "Unauthorized access.";
    ?>
    <script>
        window.stop();
        window.location.href = "../../index.php";
    </script>
    <?php
    exit();
}

$pageTitle = 'Vehicle Reservations';
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get filter from URL
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'All';

// Helper function to format numbers dynamically for mobile screens
function abbreviate_number($num) {
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    }
    if ($num >= 10000) {
        return round($num / 1000, 1) . 'K';
    }
    return number_format($num);
}

// Get booking stats
$statsQuery = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN b.status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN b.status IN ('Confirmed', 'Approved') THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN b.status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN b.status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
    COALESCE(SUM(b.total_price), 0) as total_revenue
FROM bookings b 
JOIN cars c ON b.car_id = c.id 
WHERE b.booking_type = 'online'";

if ($user_role === 'operator') {
    $statsQuery .= " AND c.user_id = $current_user_id";
}

$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Single query that combines all conditions
$query = "SELECT b.*, c.brand, c.model, c.plate_number, u.name as customer_name, c.user_id as car_owner_id
          FROM bookings b 
          JOIN cars c ON b.car_id = c.id 
          LEFT JOIN users u ON b.user_id = u.id 
          WHERE b.booking_type = 'online' ";

// Role-based filtering
if ($user_role === 'operator') {
    $query .= " AND c.user_id = ? ";
}

// Status filtering
if ($filter !== 'All') {
    $query .= " AND b.status = ? ";
}

$query .= " ORDER BY b.created_at DESC";

// Execute query
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    // Bind parameters based on what filters are active
    if ($user_role === 'operator' && $filter !== 'All') {
        mysqli_stmt_bind_param($stmt, "is", $current_user_id, $filter);
    } elseif ($user_role === 'operator') {
        mysqli_stmt_bind_param($stmt, "i", $current_user_id);
    } elseif ($filter !== 'All') {
        mysqli_stmt_bind_param($stmt, "s", $filter);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $bookings = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $bookings[] = $row;
    }
} else {
    die("Preparation failed: " . mysqli_error($conn));
}

// Check if there are any online bookings at all (for empty state message)
$check_sql = "SELECT COUNT(*) as total FROM bookings WHERE booking_type = 'online'";
$check_result = mysqli_query($conn, $check_sql);
$check_row = mysqli_fetch_assoc($check_result);
$has_online_bookings = ($check_row['total'] > 0);
$no_online_bookings = !$has_online_bookings;
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

    /* Stat Cards Base & Yellow Hover Glow Effect */
    .stat-card { 
        border-radius: 1.25rem; 
        border: 1px solid #edf2f7; 
        background-color: #ffffff;
        padding: 1.25rem;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.25s ease; 
        overflow: hidden;
        height: 100%;
        min-width: 0;
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
        word-break: break-all;
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

    /* Control Filter Box */
    .custom-control-box {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem;
        transition: background-color 0.25s ease, border-color 0.25s ease;
    }

    .control-search-input {
        position: relative;
    }

    .control-search-input i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }

    .control-search-input input {
        padding-left: 36px;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
    }

    .limiter-select {
        max-width: 85px;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
    }

    .card {
        border-radius: 1.25rem !important;
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
    }

    /* Table & Hover Animations */
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

    /* Mobile-First Table View Adaptations */
    @media (max-width: 768px) {
        .responsive-table thead {
            display: none;
        }

        .responsive-table tr {
            display: block;
            margin-bottom: 1rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1rem;
        }

        .responsive-table td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0 !important;
            border: none !important;
            font-size: 0.9rem;
        }

        .responsive-table td::before {
            content: attr(data-label);
            font-weight: 700;
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
    }

    /* Empty State Styling */
    .empty-state-wrapper {
        padding: 3rem 1.5rem;
        min-height: 380px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .empty-state {
        text-align: center;
        max-width: 400px;
        margin: 0 auto;
    }

    .empty-state-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 1.25rem;
        background: rgba(255, 204, 0, 0.15);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .empty-state-icon i {
        font-size: 2.5rem;
        color: #d97706;
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
    }

    @media (min-width: 576px) {
        .stat-value { font-size: 1.45rem; word-break: normal; }
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
    body.dark-mode .stat-label,
    body.dark-mode .responsive-table td::before {
        color: #cbd5e1 !important;
    }

    body.dark-mode .main-content .text-primary {
        color: #cbd5e1 !important;
    }

    body.dark-mode .card,
    body.dark-mode .custom-control-box {
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

    body.dark-mode .form-control,
    body.dark-mode .form-select {
        background-color: #1f1f23 !important;
        border-color: #27272a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .table,
    body.dark-mode .table tr,
    body.dark-mode .table td,
    body.dark-mode .table th {
        background-color: #141414 !important;
        color: #f1f5f9 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .responsive-table tr {
        background-color: #141414 !important;
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

    body.dark-mode code {
        background-color: #1f1f23 !important;
        color: #ffcc00 !important;
    }

    body.dark-mode .bg-light {
        background-color: #1f1f23 !important;
        color: #f1f5f9 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .bg-primary.bg-opacity-10 { background-color: rgba(56, 189, 248, 0.15) !important; }
    body.dark-mode .bg-success.bg-opacity-10 { background-color: rgba(34, 197, 94, 0.15) !important; }
    body.dark-mode .bg-warning.bg-opacity-10 { background-color: rgba(234, 179, 8, 0.15) !important; }
    body.dark-mode .bg-info.bg-opacity-10 { background-color: rgba(56, 189, 248, 0.15) !important; }
    body.dark-mode .bg-danger.bg-opacity-10 { background-color: rgba(239, 68, 68, 0.15) !important; }
</style>

<div id="sidebarBackdrop" class="sidebar-backdrop"></div>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-lg-2 p-0 d-none d-lg-block mobile-sidebar-container" id="sidebarWrapper">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <!-- Main Content Area -->
        <div class="col-12 col-lg-10 p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">
                <div class="mb-4">
                    <h3 class="fw-bold mb-0">Vehicle <span style="color: #FFCC00;">Reservations</span></h3>
                    <p class="text-muted mb-0 small d-none d-sm-block">Manage and track your fleet's reservation activity</p>
                </div>

                <!-- Statistics Overview Grid -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <div class="text-truncate">
                                    <div class="stat-value text-truncate"><?= abbreviate_number($stats['total_bookings'] ?? 0) ?></div>
                                    <div class="stat-label text-truncate">Total Bookings</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div class="text-truncate">
                                    <div class="stat-value text-truncate"><?= abbreviate_number($stats['pending'] ?? 0) ?></div>
                                    <div class="stat-label text-truncate">Pending</div>
                                    <small class="text-muted stat-subtext d-none d-sm-block text-truncate">Awaiting confirmation</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="text-truncate">
                                    <div class="stat-value text-truncate"><?= abbreviate_number(($stats['confirmed'] ?? 0) + ($stats['completed'] ?? 0)) ?></div>
                                    <div class="stat-label text-truncate">Active / Done</div>
                                    <small class="text-muted stat-subtext d-none d-sm-block text-truncate"><?= number_format($stats['confirmed'] ?? 0) ?> confirmed</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="card stat-card shadow-sm">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                                <div class="text-truncate">
                                    <div class="stat-value text-success text-truncate">₱<?= abbreviate_number($stats['total_revenue'] ?? 0) ?></div>
                                    <div class="stat-label text-truncate">Total Revenue</div>
                                    <small class="text-muted stat-subtext d-none d-sm-block text-truncate">From online</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom Live Control Panel -->
                <div class="custom-control-box mb-4 shadow-sm">
                    <div class="d-flex flex-row align-items-center justify-content-between gap-3 flex-nowrap">
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <span class="text-muted small text-nowrap">Show entries:</span>
                            <select id="entryLimiter" class="form-select form-select-sm limiter-select mb-0">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                        
                        <div class="flex-grow-1">
                            <div class="control-search-input">
                                <i class="bi bi-search"></i>
                                <input type="text" id="customSearchInput" class="form-control form-control-sm" placeholder="Search customer, vehicle, ID or status...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reservations Table Card -->
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="table-responsive-md">
                            <?php if (empty($bookings)): ?>
                                <div class="empty-state-wrapper">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="bi bi-calendar-x"></i>
                                        </div>
                                        <h5 class="fw-bold mb-2">No Online Bookings Found</h5>
                                        <p class="text-muted small mb-4">
                                            <?php if (isset($no_online_bookings) && $no_online_bookings): ?>
                                                There are no online bookings in the system yet.
                                            <?php else: ?>
                                                No <span class="badge bg-secondary mx-1"><?= htmlspecialchars($filter) ?></span>
                                                bookings found for online reservations.
                                            <?php endif; ?>
                                        </p>
                                        <div>
                                            <a href="?filter=All" class="btn btn-warning fw-bold text-dark btn-sm px-4 rounded-3">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i> View All Bookings
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <table class="table table-hover align-middle mb-0 responsive-table">
                                    <thead>
                                        <tr>
                                            <th class="ps-4 py-3">Booking</th>
                                            <th class="py-3">Customer</th>
                                            <th class="py-3">Vehicle</th>
                                            <th class="py-3">Rental Period</th>
                                            <th class="py-3">Total</th>
                                            <th class="text-end pe-4 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reservationsTableBody">
                                        <?php foreach ($bookings as $b): ?>
                                            <tr class="reservation-row">
                                                <td class="ps-4 py-3" data-label="ID">
                                                    <span class="fw-bold text-dark search-target">#BK-<?= $b['id'] ?></span>
                                                </td>
                                                <td class="py-3" data-label="Customer">
                                                    <div class="fw-bold text-dark search-target">
                                                        <?= !empty($b['customer_name']) ? htmlspecialchars($b['customer_name']) : htmlspecialchars($b['guest_name']) ?>
                                                    </div>
                                                    <span class="badge bg-light text-muted border" style="font-size: 9px;"><?= strtoupper($b['booking_type']) ?></span>
                                                </td>
                                                <td class="py-3" data-label="Vehicle">
                                                    <div class="fw-semibold text-dark search-target"><?= htmlspecialchars($b['brand']) ?> <?= htmlspecialchars($b['model']) ?></div>
                                                    <div class="text-muted small search-target"><code><?= htmlspecialchars($b['plate_number']) ?></code></div>
                                                </td>
                                                <td class="py-3" data-label="Period">
                                                    <div class="small fw-medium"><?= date('M d, Y', strtotime($b['start_date'])) ?></div>
                                                    <div class="text-muted small">to <?= date('M d, Y', strtotime($b['end_date'])) ?></div>
                                                </td>
                                                <td class="py-3 fw-bold" data-label="Total">
                                                    ₱<?= number_format($b['total_price'], 2) ?>
                                                </td>
                                                <td class="text-end pe-4 py-3" data-label="Status">
                                                    <?php
                                                    $statusClass = match ($b['status']) {
                                                        'Pending' => 'bg-warning text-dark',
                                                        'Confirmed', 'Approved' => 'bg-primary text-white',
                                                        'Completed' => 'bg-success text-white',
                                                        'Cancelled' => 'bg-danger text-white',
                                                        default => 'bg-secondary text-white'
                                                    };
                                                    ?>
                                                    <span class="badge <?= $statusClass ?> rounded-pill px-3 py-2 search-target"><?= $b['status'] ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
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
document.addEventListener('DOMContentLoaded', function() {
    // --- Mobile Sidebar Toggle Implementation ---
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

    // --- Dynamic Table Search & Entry Limiter ---
    const searchInput = document.getElementById('customSearchInput');
    const limiterSelect = document.getElementById('entryLimiter');
    const tableBody = document.getElementById('reservationsTableBody');
    
    if (!tableBody) return;
    
    const rows = Array.from(tableBody.querySelectorAll('.reservation-row'));

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const limitValue = limiterSelect.value;
        
        let visibleCount = 0;

        rows.forEach(row => {
            const targetFields = row.querySelectorAll('.search-target');
            let matchFound = false;

            targetFields.forEach(field => {
                if (field.textContent.toLowerCase().includes(searchTerm)) {
                    matchFound = true;
                }
            });

            if (matchFound) {
                if (limitValue === 'all' || visibleCount < parseInt(limitValue)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            } else {
                row.style.display = 'none';
            }
        });
    }

    if (searchInput && limiterSelect) {
        searchInput.addEventListener('input', filterTable);
        limiterSelect.addEventListener('change', filterTable);
        filterTable();
    }
});
</script>