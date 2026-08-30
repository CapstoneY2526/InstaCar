<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'operator') {
    ?>
    <script>
        window.stop();
        window.location.href = "../../login.php";
    </script>
    <?php
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$current_year = date('Y');
$pageTitle = 'My Revenue';

$selected_car = isset($_GET['car_id']) ? (int)$_GET['car_id'] : 'all';

$my_cars_res = mysqli_query($conn, "SELECT id, brand, model, plate_number FROM cars WHERE user_id = $user_id");

$car_filter = ($selected_car !== 'all') ? "AND c.id = $selected_car" : "";

$query = "SELECT 
            c.brand, c.model, c.plate_number,
            MONTH(b.created_at) as month_num,
            SUM(COALESCE(p.total_gross, b.total_price)) as gross,
            SUM(COALESCE(p.total_net, b.total_price * 0.8)) as my_share
          FROM cars c
          INNER JOIN bookings b ON c.id = b.car_id
          LEFT JOIN booking_payments p ON b.id = p.booking_id
          WHERE c.user_id = $user_id 
          AND YEAR(b.created_at) = $current_year 
          AND b.status = 'Completed'
          $car_filter
          GROUP BY c.id, MONTH(b.created_at)
          ORDER BY month_num DESC";

$res = mysqli_query($conn, $query);

$revenue_data = [];
$total_payout = 0;

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $car_key = "$row[brand] $row[model] ($row[plate_number])";
        $revenue_data[$car_key][$row['month_num']] = $row;
        $total_payout += $row['my_share'];
    }
}

$months = [1=>"JAN", 2=>"FEB", 3=>"MAR", 4=>"APR", 5=>"MAY", 6=>"JUN", 7=>"JUL", 8=>"AUG", 9=>"SEP", 10=>"OCT", 11=>"NOV", 12=>"DEC"];
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

    /* Revenue Summary Gradient Card & Yellow Hover Glow Effect */
    .revenue-summary { 
        border-radius: 1.25rem; 
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); 
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        border: 1px solid transparent;
    }

    .revenue-summary:hover {
        transform: translateY(-2px);
        border-color: #ffcc00 !important;
        box-shadow: 0 0 15px rgba(255, 204, 0, 0.4) !important;
    }

    .card {
        border-radius: 1.25rem !important;
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        transition: background-color 0.25s ease, border-color 0.25s ease;
    }

    .card-header {
        border-bottom: 1px solid #e2e8f0;
    }

    /* Table & Hover Animations */
    .revenue-table thead th {
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: 0.72rem;
        background-color: #f8fafc;
        color: #64748b;
        border-bottom: 1px solid #e2e8f0;
    }

    .revenue-table tbody tr:hover {
        background-color: #fef9e3 !important;
        transition: background 0.2s;
    }

    /* Mobile Entries Layout Transformation Rules */
    @media (max-width: 767.98px) {
        .revenue-table-wrapper {
            background: transparent !important;
            padding: 0 !important;
        }

        .revenue-table {
            display: block;
        }

        .revenue-table thead { 
            display: none; 
        }

        .revenue-table tbody {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 12px;
        }

        .revenue-table tr { 
            display: block; 
            background-color: #ffffff;
            border: 1px solid #e2e8f0 !important; 
            border-radius: 12px;
            padding: 16px !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            margin-bottom: 0 !important;
        }

        .revenue-table td { 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            border: none !important;
            padding: 6px 0 !important;
            text-align: right;
        }

        .revenue-table td:not(:last-child) {
            border-bottom: 1px dashed #f1f5f9 !important;
            padding-bottom: 10px !important;
            margin-bottom: 4px;
        }

        .revenue-table td:last-child {
            padding-top: 10px !important;
        }

        .revenue-table td::before { 
            content: attr(data-label); 
            font-weight: 600; 
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
        }
        
        .revenue-table td.ps-4 {
            padding-left: 0 !important;
        }
        .revenue-table td.pe-4 {
            padding-right: 0 !important;
        }
    }

    /* Offcanvas Sidebar Responsive Rules */
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

    /* ========================================================
       DARK MODE OVERRIDES & CONTRAST FIXES
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
    body.dark-mode .main-content label {
        color: #ffffff !important;
    }

    body.dark-mode .main-content .text-muted,
    body.dark-mode .main-content .text-secondary,
    body.dark-mode .revenue-table td::before {
        color: #cbd5e1 !important;
    }

    body.dark-mode .main-content .text-primary {
        color: #38bdf8 !important;
    }

    body.dark-mode .card {
        background-color: #141414 !important;
        border: 1px solid #27272a !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4) !important;
    }

    body.dark-mode .card-header {
        background-color: #141414 !important;
        border-bottom: 1px solid #27272a !important;
    }

    body.dark-mode .revenue-summary {
        background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%) !important;
        border: 1px solid #27272a !important;
    }

    body.dark-mode .revenue-summary:hover {
        border-color: #ffcc00 !important;
        box-shadow: 0 0 18px rgba(255, 204, 0, 0.35) !important;
    }

    body.dark-mode .form-control,
    body.dark-mode .form-select,
    body.dark-mode .bg-light {
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

    body.dark-mode .revenue-table tr {
        background-color: #141414 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .revenue-table thead,
    body.dark-mode .revenue-table thead tr,
    body.dark-mode .revenue-table thead th {
        background-color: #1f1f23 !important;
        color: #94a3b8 !important;
    }

    body.dark-mode .revenue-table tbody tr:hover {
        background-color: #1a1a1e !important;
    }

    body.dark-mode .icon-box.bg-primary-subtle {
        background-color: rgba(56, 189, 248, 0.15) !important;
        color: #38bdf8 !important;
    }
</style>

<div id="sidebarBackdrop" class="sidebar-backdrop"></div>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-lg-2 p-0 d-none d-lg-block mobile-sidebar-container" id="sidebarWrapper">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <!-- Main Workspace Area Wrapper -->
        <div class="col-12 col-lg-10 p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">
                <div class="row g-3 mb-4 align-items-center">
                    <div class="col-12 col-md-6">
                        <h3 class="fw-bold mb-0">Earnings <span style="color: #FFCC00;">Report</span></h3>
                        <p class="text-muted small mb-0">Financial performance for <?= $current_year ?></p>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="revenue-summary text-white p-3 shadow-sm d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-uppercase fw-bold" style="font-size: 0.65rem; opacity: 0.9; letter-spacing: 0.5px;">Total Net Payout (80%)</small>
                                <h3 class="fw-bold mb-0">₱<?= number_format($total_payout, 2) ?></h3>
                            </div>
                            <i class="bi bi-wallet2 fs-1 opacity-25"></i>
                        </div>
                    </div>
                </div>

                <!-- Filter Controls Form Panel -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-3">
                        <form method="GET" class="row g-2">
                            <div class="col-8 col-md-10">
                                <select name="car_id" class="form-select border-0 bg-light py-2" onchange="this.form.submit()">
                                    <option value="all">All Vehicles</option>
                                    <?php 
                                    $my_cars_res->data_seek(0);
                                    while($car = $my_cars_res->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $car['id'] ?>" <?= $selected_car == $car['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($car['brand']) ?> <?= htmlspecialchars($car['model']) ?> (<?= htmlspecialchars($car['plate_number']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-4 col-md-2">
                                <a href="revenue.php" class="btn btn-outline-secondary w-100 border-0 bg-light py-2">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (empty($revenue_data)): ?>
                    <div class="text-center py-5 card border-0 rounded-4 shadow-sm my-4">
                        <i class="bi bi-cash-stack text-muted opacity-25" style="font-size: 3.5rem;"></i>
                        <h5 class="fw-bold mt-3 text-dark">No Earnings History</h5>
                        <p class="text-muted small">No completed bookings found for this vehicle selection matching <?= $current_year ?>.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($revenue_data as $car_name => $months_list): ?>
                        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                            <div class="card-header bg-white py-3 border-0">
                                <div class="d-flex align-items-center">
                                    <div class="icon-box bg-primary-subtle text-primary me-2 rounded-circle" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="bi bi-car-front-fill"></i>
                                    </div>
                                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($car_name) ?></h6>
                                </div>
                            </div>
                            <div class="table-responsive-md revenue-table-wrapper">
                                <table class="table table-hover mb-0 align-middle revenue-table">
                                    <thead>
                                        <tr>
                                            <th class="ps-4 py-3">Month</th>
                                            <th class="text-md-center py-3">Gross Sales</th>
                                            <th class="text-md-end pe-4 py-3">My Payout</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($months as $num => $name): 
                                            if (isset($months_list[$num])): 
                                                $m = $months_list[$num];
                                        ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-dark" data-label="Month"><?= $name ?></td>
                                                <td class="text-md-center text-muted" data-label="Gross">₱<?= number_format($m['gross'], 2) ?></td>
                                                <td class="text-md-end fw-bold text-primary pe-4" data-label="Payout">₱<?= number_format($m['my_share'], 2) ?></td>
                                            </tr>
                                        <?php endif; endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Dashboard Sticky Footer Area Alignment -->
            <div class="mt-auto">
                <?php require_once __DIR__ . '/../components/footer.php'; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile Sidebar Toggle Implementation
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