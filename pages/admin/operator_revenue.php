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

$pageTitle = 'Operator Revenue';
$current_year = date('Y');

$selected_owner = $_GET['owner_id'] ?? 'all';
$selected_car = $_GET['car_id'] ?? 'all';

// 1. Fetch Operators (Procedural)
$owners_res = mysqli_query($conn, "SELECT id, name FROM users WHERE role = 'operator' ORDER BY name ASC");

// 2. Fetch Cars (Procedural)
$car_dropdown_sql = "SELECT id, brand, model, plate_number FROM cars";
if ($selected_owner !== 'all') {
    $car_dropdown_sql .= " WHERE user_id = " . intval($selected_owner);
}
$cars_dropdown_res = mysqli_query($conn, $car_dropdown_sql);

// 3. Build Query Logic
$where_clauses = ["YEAR(b.created_at) = $current_year", "b.status = 'Completed'"];
if ($selected_owner !== 'all') $where_clauses[] = "c.user_id = " . intval($selected_owner);
if ($selected_car !== 'all') $where_clauses[] = "c.id = " . intval($selected_car);

$where_str = implode(" AND ", $where_clauses);

$query = "SELECT 
            c.brand, c.model, c.plate_number, c.id as car_id,
            u.name as owner_name,
            MONTH(b.created_at) as month_num,
            SUM(COALESCE(p.total_gross, b.total_price)) as gross,
            SUM(COALESCE(p.total_net, b.total_price * 0.8)) as owner_share,
            SUM(COALESCE(p.total_gross - p.total_net - p.driver_fee - p.agent_fee, b.total_price * 0.2)) as mgt_income
          FROM cars c
          INNER JOIN users u ON c.user_id = u.id
          INNER JOIN bookings b ON c.id = b.car_id
          LEFT JOIN booking_payments p ON b.id = p.booking_id
          WHERE $where_str AND u.role = 'operator'
          GROUP BY c.id, MONTH(b.created_at)
          ORDER BY u.name ASC, c.id ASC, month_num ASC";

$res = mysqli_query($conn, $query);
$car_data = [];
$grand_total_mgt = 0;

if ($res && mysqli_num_rows($res) > 0) {
    while ($row = mysqli_fetch_assoc($res)) {
        $car_key = $row['brand'] . ' ' . $row['model'] . ' [' . $row['plate_number'] . ']';
        $car_data[$car_key]['owner'] = $row['owner_name'];
        $car_data[$car_key]['months'][$row['month_num']] = $row;
        $grand_total_mgt += $row['mgt_income'];
    }
} elseif (!$res) {
    die("Revenue Query Failed: " . mysqli_error($conn));
}

$months = [1=>"JAN", 2=>"FEB", 3=>"MAR", 4=>"APR", 5=>"MAY", 6=>"JUN", 7=>"JUL", 8=>"AUG", 9=>"SEP", 10=>"OCT", 11=>"NOV", 12=>"DEC"];
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
    .form-select,
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

    .mgt-badge { 
        background: #e0f2fe; 
        color: #0369a1; 
        border-radius: 6px; 
        padding: 4px 10px; 
        font-weight: 700; 
        display: inline-block;
    }

    .car-header { 
        background: #ffffff; 
        border-bottom: 1px solid #edf2f7; 
        transition: background-color 0.25s ease, border-color 0.25s ease;
    }

    .income-row:hover { 
        background-color: #f8fafc; 
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

    /* Offcanvas Sidebar Architecture Responsive Adjustments */
    @media (max-width: 991.98px) {
        .stat-card { width: 100% !important; margin-bottom: 1rem; }
        .table-responsive { font-size: 0.85rem; }
        .hide-mobile { display: none !important; }

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

    /* Muted and secondary text contrast fixes */
    body.dark-mode .text-muted,
    body.dark-mode .text-secondary,
    body.dark-mode span:not(.badge):not(.mgt-badge):not(.text-success):not(.text-primary):not(.text-warning) {
        color: #cbd5e1 !important;
    }

    body.dark-mode .text-primary {
        color: #38bdf8 !important;
    }

    /* Badge & Component Dark Fixes */
    body.dark-mode .mgt-badge {
        background-color: #0c4a6e !important;
        color: #38bdf8 !important;
    }

    body.dark-mode .car-header {
        background-color: #141414 !important;
        border-bottom-color: #27272a !important;
    }

    body.dark-mode .badge.bg-light {
        background-color: #27272a !important;
        color: #f1f5f9 !important;
        border-color: #3f3f46 !important;
    }

    /* Cards & Containers in Dark Mode */
    body.dark-mode .card,
    body.dark-mode .card-body,
    body.dark-mode .card-header {
        background-color: #141414 !important;
        border-color: #27272a !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.5) !important;
    }

    /* Form Controls & Dropdowns in Dark Mode */
    body.dark-mode .form-select,
    body.dark-mode .form-control {
        background-color: #1a1f26 !important;
        border: 1px solid #3b4252 !important;
        color: #ffffff !important;
    }

    body.dark-mode .form-select:focus,
    body.dark-mode .form-control:focus {
        background-color: #1a1f26 !important;
        border-color: #ffcc00 !important;
        color: #ffffff !important;
        box-shadow: 0 0 0 0.25rem rgba(255, 204, 0, 0.2) !important;
    }

    body.dark-mode .form-select option {
        background-color: #141414 !important;
        color: #ffffff !important;
    }

    /* Tables in Dark Mode */
    body.dark-mode .table,
    body.dark-mode .table tr,
    body.dark-mode .table td,
    body.dark-mode .table th {
        background-color: #141414 !important;
        color: #f1f5f9 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .table thead tr,
    body.dark-mode .table thead th,
    body.dark-mode .bg-light-subtle {
        background-color: #1f1f23 !important;
        color: #94a3b8 !important;
    }

    body.dark-mode .income-row:hover {
        background-color: #1a1a1e !important;
    }

    body.dark-mode .table tfoot,
    body.dark-mode .table tfoot td,
    body.dark-mode .table tfoot tr,
    body.dark-mode .bg-light {
        background-color: #18181b !important;
        color: #ffffff !important;
    }

    /* Reset / Secondary Buttons in Dark Mode */
    body.dark-mode .btn-secondary {
        background-color: #27272a !important;
        border-color: #3f3f46 !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .btn-secondary:hover {
        background-color: #3f3f46 !important;
        color: #ffffff !important;
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
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">Operator Revenue</h3>
                        <p class="text-muted mb-0 small">Analysis of management fees and operator payouts.</p>
                    </div>

                    <div class="bg-primary text-white px-4 py-3 rounded-4 shadow-sm stat-card">
                        <small class="text-uppercase fw-bold d-block mb-1" style="font-size: 0.65rem; opacity: 0.85; letter-spacing: 1px;">Total MGT Income</small>
                        <h3 class="fw-bold mb-0">₱<?= number_format($grand_total_mgt, 2) ?></h3>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4 rounded-4">
                    <div class="card-body p-3 p-md-4">
                        <form method="GET" class="row g-3">
                            <div class="col-12 col-md-5">
                                <label class="form-label small fw-bold text-muted">Operator Account</label>
                                <select name="owner_id" class="form-select border rounded-3" onchange="this.form.submit()">
                                    <option value="all">-- All Operators --</option>
                                    <?php 
                                    $owners_res->data_seek(0);
                                    while($o = $owners_res->fetch_assoc()): ?>
                                        <option value="<?= $o['id'] ?>" <?= $selected_owner == $o['id'] ? 'selected' : '' ?>><?= htmlspecialchars($o['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="form-label small fw-bold text-muted">Vehicle</label>
                                <select name="car_id" class="form-select border rounded-3" onchange="this.form.submit()">
                                    <option value="all">-- All Vehicles --</option>
                                    <?php 
                                    $cars_dropdown_res->data_seek(0);
                                    while($c = $cars_dropdown_res->fetch_assoc()): ?>
                                        <option value="<?= $c['id'] ?>" <?= $selected_car == $c['id'] ? 'selected' : '' ?>><?= $c['brand'] ?> <?= $c['model'] ?> (<?= $c['plate_number'] ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-2 d-flex align-items-end">
                                <a href="operator_revenue.php" class="btn btn-secondary w-100 rounded-3">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (empty($car_data)): ?>
                    <div class="card border-0 shadow-sm p-5 text-center rounded-4">
                        <i class="bi bi-wallet2 display-1 text-muted mb-3"></i>
                        <h5 class="text-muted">No completed bookings found.</h5>
                    </div>
                <?php else: ?>
                    <?php foreach ($car_data as $car_name => $details): ?>
                        <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden">
                            <div class="car-header p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($car_name) ?></h6>
                                    <small class="text-muted">Owned by: <span class="text-primary fw-semibold"><?= htmlspecialchars($details['owner']) ?></span></small>
                                </div>
                                <div>
                                    <span class="badge bg-light text-dark border rounded-pill px-3 py-2">Year <?= $current_year ?></span>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table mb-0 align-middle">
                                    <thead class="bg-light-subtle small text-uppercase">
                                        <tr class="text-muted" style="font-size: 0.65rem; letter-spacing: 0.5px;">
                                            <th class="ps-4">Month</th>
                                            <th class="text-center">MGT Income</th>
                                            <th class="text-center hide-mobile">Operator Share</th>
                                            <th class="text-end pe-4">Gross Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $year_mgt = 0; $year_gross = 0;
                                        foreach ($months as $num => $name): 
                                            $m = $details['months'][$num] ?? null;
                                            if($m):
                                                $year_mgt += $m['mgt_income'];
                                                $year_gross += $m['gross'];
                                        ?>
                                        <tr class="income-row">
                                            <td class="ps-4 fw-bold text-secondary"><?= $name ?></td>
                                            <td class="text-center">
                                                <span class="mgt-badge">₱<?= number_format($m['mgt_income'], 2) ?></span>
                                            </td>
                                            <td class="text-center text-muted hide-mobile">
                                                ₱<?= number_format($m['owner_share'], 2) ?>
                                            </td>
                                            <td class="text-end pe-4 fw-bold text-dark">
                                                ₱<?= number_format($m['gross'], 2) ?>
                                            </td>
                                        </tr>
                                        <?php endif; endforeach; ?>
                                    </tbody>
                                    <tfoot class="bg-light">
                                        <tr class="fw-bold">
                                            <td class="ps-4">TOTAL</td>
                                            <td class="text-center text-primary">₱<?= number_format($year_mgt, 2) ?></td>
                                            <td class="text-center hide-mobile">--</td>
                                            <td class="text-end pe-4">₱<?= number_format($year_gross, 2) ?></td>
                                        </tr>
                                    </tfoot>
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
// Mobile Sidebar Canvas Engine Tracker Controls Blueprint
document.addEventListener("DOMContentLoaded", function () {
    // Intercept header template elements to pull the yellow button handler link node references
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
    
    // Fallback handler engine assignment path
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