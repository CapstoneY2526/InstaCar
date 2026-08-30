<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth Check - JS Redirect
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied.";
    ?>
    <script>
        window.stop();
        window.location.href = "../../index.php";
    </script>
    <?php
    exit();
}

$pageTitle = 'House Revenue';
$current_year = date('Y');
$selected_car = $_GET['car_id'] ?? 'all';

// Initialize arrays
$admin_cars = [];
$total_yearly_gross = 0;
$total_yearly_net = 0;

// 1. Fetch House Cars for the filter (Procedural)
$house_cars_sql = "SELECT c.id, c.brand, c.model, c.plate_number 
                   FROM cars c 
                   JOIN users u ON c.user_id = u.id 
                   WHERE u.role = 'admin' 
                   ORDER BY c.brand ASC";

$house_cars_res = mysqli_query($conn, $house_cars_sql);

// 2. Build Query
$where_clauses = [
    "YEAR(b.created_at) = $current_year",
    "b.status = 'Completed'",
    "u.role = 'admin'"
];

if ($selected_car !== 'all') {
    $where_clauses[] = "c.id = " . intval($selected_car);
}

$where_str = implode(" AND ", $where_clauses);

// Updated SQL to get both Gross and Net
$query = "SELECT 
            c.brand, c.model, c.plate_number, c.id as car_id,
            MONTH(b.created_at) as month_num,
            COALESCE(SUM(p.total_gross), 0) as monthly_gross,
            COALESCE(SUM(p.total_net), 0) as monthly_net
          FROM cars c
          INNER JOIN users u ON c.user_id = u.id
          LEFT JOIN bookings b ON c.id = b.car_id AND b.status = 'Completed' AND YEAR(b.created_at) = $current_year
          LEFT JOIN booking_payments p ON b.id = p.booking_id
          WHERE u.role = 'admin'
          GROUP BY c.id, MONTH(b.created_at)
          ORDER BY c.brand ASC, month_num ASC";

$res = mysqli_query($conn, $query);

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $car_key = $row['brand'] . ' ' . $row['model'] . ' (' . $row['plate_number'] . ')';
        
        // Structure the array by car then by month
        if (!isset($admin_cars[$car_key])) {
            $admin_cars[$car_key] = [];
        }
        $admin_cars[$car_key][$row['month_num']] = [
            'gross' => $row['monthly_gross'],
            'net' => $row['monthly_net']
        ];
        
        $total_yearly_gross += $row['monthly_gross'];
        $total_yearly_net += $row['monthly_net'];
    }
} else {
    die("House Revenue Query Failed: " . mysqli_error($conn));
}

$months = [1=>"JAN", 2=>"FEB", 3=>"MAR", 4=>"APR", 5=>"MAY", 6=>"JUN", 7=>"JUL", 8=>"AUG", 9=>"SEP", 10=>"OCT", 11=>"NOV", 12=>"DEC"];
$has_data = !empty($admin_cars);
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    /* ========================================================
       BASE LAYOUT & COMPONENT OVERRIDES
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

    .main-content { 
        background-color: var(--brand-bg, #f8fafc);
        min-height: 100vh; 
        transition: background-color 0.25s ease, color 0.25s ease;
    }

    .total-gradient { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
    .net-text { font-size: 0.75rem; color: #94a3b8; }
    .table-revenue thead th { background: #f8fafc; font-size: 0.65rem; letter-spacing: 0.5px; }
    .revenue-cell { min-width: 90px; }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    .empty-state i {
        font-size: 64px;
        color: #cbd5e1;
        margin-bottom: 20px;
    }
    .empty-state h5 {
        color: #64748b;
        margin-bottom: 10px;
    }
    .empty-state p {
        color: #94a3b8;
    }

    .remit-card { transition: transform 0.2s, background-color 0.25s ease; border-radius: 1rem !important; }
    .remit-card:hover { transform: translateY(-3px); }
    
    .jerry-fee-box { background: #fffbeb; border-left: 4px solid #f59e0b; }
    .owner-balance-box { background: #f0f9ff; border: 1px solid #bae6fd; }
    .extra-small { font-size: 0.7rem; }

    /* Responsive Offcanvas Architecture for Mobile Viewports */
    @media (max-width: 991.98px) {
        .main-content-padding { padding: 1.25rem !important; }
        .payout-header { flex-direction: column; align-items: flex-start !important; gap: 0.75rem; }
        .history-column { margin-top: 2rem; }

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

    /* Standard Primary Button Accent (Yellow Variant) */
    .btn-warning-action, .btn-primary, .btn-submit-action {
        background-color: #ffcc00 !important;
        border-color: #ffcc00 !important;
        color: #000000 !important;
        font-weight: 700 !important;
    }
    .btn-warning-action:hover, .btn-primary:hover, .btn-submit-action:hover {
        background-color: #e6b800 !important;
        border-color: #e6b800 !important;
        color: #000000 !important;
    }

    /* ========================================================
       DARK MODE COMPLETE OVERRIDES & CONTRAST FIXES
       ======================================================== */
    body.dark-mode,
    body.dark-mode .main-content {
        background-color: #0a0a0a !important;
        color: #f1f5f9 !important;
    }

    /* Header & Footer Components */
    body.dark-mode header,
    body.dark-mode navbar,
    body.dark-mode .navbar,
    body.dark-mode footer,
    body.dark-mode .footer {
        background-color: #141414 !important;
        border-color: #27272a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode footer p,
    body.dark-mode header span,
    body.dark-mode header p {
        color: #a1a1aa !important;
    }

    /* Mobile Sidebar Dark Overrides */
    body.dark-mode .mobile-sidebar-container {
        background-color: #141414 !important;
        border-right: 1px solid #27272a !important;
    }

    /* Typography & High-Contrast Overrides */
    body.dark-mode .text-dark,
    body.dark-mode h3,
    body.dark-mode h4,
    body.dark-mode h5,
    body.dark-mode h6,
    body.dark-mode label {
        color: #ffffff !important;
    }

    /* Stat Box Fix for Net Profit Card */
    body.dark-mode .net-profit-card {
        background-color: #141414 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .net-profit-card h4 {
        color: #ffffff !important;
    }

    /* High-contrast fix for muted and secondary text elements */
    body.dark-mode .text-muted,
    body.dark-mode .text-secondary,
    body.dark-mode span:not(.badge):not(.text-success):not(.text-primary):not(.text-warning) {
        color: #cbd5e1 !important;
    }

    body.dark-mode code.text-primary,
    body.dark-mode .text-primary {
        color: #38bdf8 !important;
    }

    body.dark-mode .text-success {
        color: #22c55e !important;
    }

    /* Dark Mode Pending Badge Fix */
    body.dark-mode .bg-warning-subtle {
        background-color: #451a03 !important;
        border: 1px solid #78350f !important;
    }

    body.dark-mode .bg-warning-subtle.text-warning {
        color: #fde047 !important;
    }

    /* Cards, Tables & Container Surfaces in Dark Mode */
    body.dark-mode .card,
    body.dark-mode .remit-card,
    body.dark-mode .card-header,
    body.dark-mode .empty-state {
        background-color: #141414 !important;
        border-color: #27272a !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.5) !important;
    }

    /* Fix table cell background color leaking through in dark mode */
    body.dark-mode .table-revenue,
    body.dark-mode .table-revenue tr,
    body.dark-mode .table-revenue td,
    body.dark-mode .table-revenue th {
        background-color: #141414 !important;
        color: #f1f5f9 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .table-revenue thead th {
        background-color: #1f1f23 !important;
        color: #94a3b8 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .table-revenue .bg-light {
        background-color: #1a1a1e !important;
    }

    body.dark-mode .jerry-fee-box {
        background-color: #1f1a08 !important;
        border-left: 4px solid #f59e0b !important;
    }

    body.dark-mode .jerry-fee-box .text-warning-emphasis {
        color: #fcd34d !important;
    }

    body.dark-mode .owner-balance-box {
        background-color: #0c1a24 !important;
        border: 1px solid #0369a1 !important;
    }

    body.dark-mode .owner-balance-box h4,
    body.dark-mode .owner-balance-box small {
        color: #38bdf8 !important;
    }

    /* List Group & Remittance History */
    body.dark-mode .list-group-item {
        background-color: #141414 !important;
        border-color: #27272a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .list-group-item .text-dark {
        color: #ffffff !important;
    }

    body.dark-mode .list-group-item .border-bottom {
        border-color: #27272a !important;
    }

    /* Modal Dark Theme Styles */
    body.dark-mode .modal-content {
        background-color: #141414 !important;
        border: 1px solid #27272a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .modal-header {
        background-color: #141414 !important;
        border-bottom: 1px solid #27272a !important;
    }

    body.dark-mode .modal-footer {
        background-color: #141414 !important;
        border-top: 1px solid #27272a !important;
    }

    body.dark-mode .modal-vehicle-box {
        background-color: #1f1f1f !important;
        color: #ffffff !important;
    }

    /* Input & Placeholder High-Contrast Fixes */
    body.dark-mode .form-control,
    body.dark-mode textarea {
        background-color: #1a1f26 !important;
        border: 1px solid #3b4252 !important;
        color: #ffffff !important;
    }

    body.dark-mode .form-control::placeholder,
    body.dark-mode textarea::placeholder {
        color: #94a3b8 !important;
        opacity: 0.8 !important;
    }

    body.dark-mode .form-control:focus,
    body.dark-mode textarea:focus {
        background-color: #1a1f26 !important;
        border-color: #ffcc00 !important;
        color: #ffffff !important;
        box-shadow: 0 0 0 0.25rem rgba(255, 204, 0, 0.2) !important;
    }

    /* Action Buttons in Dark Mode */
    body.dark-mode .btn-white {
        background-color: #141414 !important;
        color: #f1f5f9 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .btn-white:hover {
        background-color: #1f1f1f !important;
        color: #ffffff !important;
    }

    body.dark-mode .btn-secondary {
        background-color: #27272a !important;
        border-color: #3f3f46 !important;
        color: #f1f5f9 !important;
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
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">House Revenue</h3>
                        <p class="text-muted mb-0 small">Earnings Breakdown for <?= $current_year ?></p>
                    </div>

                    <div class="d-flex gap-2 gap-sm-3">
                        <div class="total-gradient text-white px-3 px-sm-4 py-3 rounded-4 shadow-sm">
                            <small class="text-uppercase fw-bold opacity-75" style="font-size: 0.6rem; letter-spacing: 0.5px;">Gross Revenue</small>
                            <h4 class="fw-bold mb-0" style="font-size: calc(1.1rem + 0.3vw);">₱<?= number_format($total_yearly_gross, 2) ?></h4>
                        </div>
                        <div class="net-profit-card bg-white border px-3 px-sm-4 py-3 rounded-4 shadow-sm">
                            <small class="text-uppercase fw-bold text-primary" style="font-size: 0.6rem; letter-spacing: 0.5px;">Net Profit</small>
                            <h4 class="fw-bold mb-0 text-dark" style="font-size: calc(1.1rem + 0.3vw);">₱<?= number_format($total_yearly_net, 2) ?></h4>
                        </div>
                    </div>
                </div>

                <?php if (!$has_data): ?>
                    <div class="empty-state bg-white rounded-4 shadow-sm border">
                        <i class="bi bi-graph-up-arrow d-block mb-2"></i>
                        <h5 class="fw-bold">No Revenue Data Found</h5>
                        <p class="mb-0 small">No completed bookings found for <?= $current_year ?>.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($admin_cars as $car_name => $monthly_data): ?>
                        <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
                            <div class="card-header bg-white py-3 border-0">
                                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-car-front-fill me-2 text-primary"></i><?= htmlspecialchars($car_name) ?></h6>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-revenue align-middle mb-0 text-center">
                                    <thead>
                                        <tr>
                                            <?php foreach ($months as $m): ?><th><?= $m ?></th><?php endforeach; ?>
                                            <th class="bg-dark text-white fw-bold">YEAR TOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <?php 
                                            $car_gross = 0; $car_net = 0;
                                            foreach ($months as $num => $m): 
                                                $g = $monthly_data[$num]['gross'] ?? 0;
                                                $n = $monthly_data[$num]['net'] ?? 0;
                                                $car_gross += $g; $car_net += $n;
                                            ?>
                                                <td class="py-3 revenue-cell">
                                                    <?php if($g > 0): ?>
                                                        <div class="fw-bold text-dark small">₱<?= number_format($g) ?></div>
                                                        <div class="text-success fw-bold" style="font-size: 0.65rem;">₱<?= number_format($n) ?> <small class="text-muted fw-normal">net</small></div>
                                                    <?php else: ?>
                                                        <span class="text-muted opacity-50">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td class="bg-light py-3" style="min-width: 120px;">
                                                <div class="fw-bold text-dark small">₱<?= number_format($car_gross, 2) ?></div>
                                                <div class="fw-bold text-primary" style="font-size: 0.7rem;">₱<?= number_format($car_net, 2) ?> <small class="fw-bold text-uppercase">Net</small></div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mt-auto">
                <?php require_once __DIR__ . '/../components/footer.php'; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Mobile Responsive Toggle Trigger Core Engine
document.addEventListener("DOMContentLoaded", function () {
    // Searches layout structure to isolate yellow button reference points dynamically
    const containerWorkspace = document.querySelector('.main-content header, .main-content nav, .container-fluid');
    let toggleBtn = null;
    
    if (containerWorkspace) {
        const structuralButtons = containerWorkspace.getElementsByTagName('button');
        for (let btn of structuralButtons) {
            if (btn.querySelector('.bi-list') || btn.innerHTML.includes('<span') || btn.className.includes('navbar-toggler')) {
                toggleBtn = btn;
                break;
            }
        }
    }
    
    // Universal programmatic safe fallback selector handle
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

        // Action tracker targeting the single menu button safely
        toggleBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });

        // Close sidebar safely if window focus exits drawer bounds
        backdrop.addEventListener("click", toggleSidebar);
    }
});
</script>