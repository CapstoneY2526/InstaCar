<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/branch_helper.php';

// Auth Check - JS Redirect
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied.";
    ?>
    <script>window.stop(); window.location.href = "../../index.php";</script>
    <?php
    exit();
}

$pageTitle = 'Fleet Revenue';
$current_year = date('Y');

// Branch scope (respects the admin branch switcher)
$view_branch = $_SESSION['view_branch'] ?? 'all';
$branch_filter_active = false;
$branch_filter_id = 0;

if ($view_branch !== 'all') {
    $branch_filter_id = (int)$view_branch;
    $branch_filter_active = $branch_filter_id > 0;
}

$months = [1=>"JAN", 2=>"FEB", 3=>"MAR", 4=>"APR", 5=>"MAY", 6=>"JUN", 7=>"JUL", 8=>"AUG", 9=>"SEP", 10=>"OCT", 11=>"NOV", 12=>"DEC"];

// ── Filter params ──
$period = $_GET['period'] ?? 'all';      // all | this_month | last_30d | this_quarter
if (!in_array($period, ['all', 'this_month', 'last_30d', 'this_quarter'], true)) {
    $period = 'all';
}

$filter_type = $_GET['type'] ?? 'all';   // all | online | manual
if (!in_array($filter_type, ['all', 'online', 'manual'], true)) {
    $filter_type = 'all';
}

$car_filter_id = (int)($_GET['car_id'] ?? 0);  // 0 = all cars

// ── Build period WHERE clause ──
$period_where = '';
if ($period === 'this_month') {
    $period_where = " AND MONTH(COALESCE(p.created_at, b.created_at)) = MONTH(CURDATE()) 
                       AND YEAR(COALESCE(p.created_at, b.created_at)) = YEAR(CURDATE())";
} elseif ($period === 'last_30d') {
    $period_where = " AND COALESCE(p.created_at, b.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($period === 'this_quarter') {
    $period_where = " AND QUARTER(COALESCE(p.created_at, b.created_at)) = QUARTER(CURDATE()) 
                       AND YEAR(COALESCE(p.created_at, b.created_at)) = YEAR(CURDATE())";
}

// ══════════════════════════════════════════════════════════════
// 0) CAR FILTER DROPDOWN OPTIONS
// ══════════════════════════════════════════════════════════════
$cars_for_filter = [];
$cars_sql = "
    SELECT DISTINCT c.id, c.brand, c.model, c.plate_number
    FROM cars c
    INNER JOIN bookings b ON b.car_id = c.id AND b.status = 'Completed'
    LEFT JOIN booking_payments p ON p.booking_id = b.id
    WHERE YEAR(COALESCE(p.created_at, b.created_at)) = ?
";
$cars_params = [$current_year];
$cars_types  = 'i';

if ($branch_filter_active) {
    $cars_sql .= " AND c.branch_id = ?";
    $cars_params[] = $branch_filter_id;
    $cars_types   .= 'i';
}
$cars_sql .= " ORDER BY c.brand ASC, c.model ASC";

$stmt = $conn->prepare($cars_sql);
$stmt->bind_param($cars_types, ...$cars_params);
$stmt->execute();
$cars_res = $stmt->get_result();
while ($row = $cars_res->fetch_assoc()) {
    $cars_for_filter[] = $row;
}
$stmt->close();

// ══════════════════════════════════════════════════════════════
// 1) MAIN PER-CAR MONTHLY BREAKDOWN
// ══════════════════════════════════════════════════════════════
$admin_cars = [];
$total_yearly_gross = 0;
$cars_with_revenue = 0;
$monthly_totals = array_fill(1, 12, 0);

$sql = "
    SELECT
        c.id AS car_id,
        c.brand, c.model, c.plate_number,
        MONTH(COALESCE(p.created_at, b.created_at)) AS month_num,
        SUM(COALESCE(p.total_gross, b.total_price)) AS monthly_gross
    FROM cars c
    INNER JOIN bookings b
        ON b.car_id = c.id
       AND b.status = 'Completed'
    LEFT JOIN booking_payments p
        ON p.booking_id = b.id
    WHERE YEAR(COALESCE(p.created_at, b.created_at)) = ?
    {$period_where}
";
$params = [$current_year];
$types  = 'i';

if ($branch_filter_active) {
    $sql .= " AND c.branch_id = ?";
    $params[] = $branch_filter_id;
    $types   .= 'i';
}

if ($filter_type !== 'all') {
    $sql .= " AND b.booking_type = ?";
    $params[] = $filter_type;
    $types   .= 's';
}

if ($car_filter_id > 0) {
    $sql .= " AND c.id = ?";
    $params[] = $car_filter_id;
    $types   .= 'i';
}

$sql .= "
    GROUP BY c.id, MONTH(COALESCE(p.created_at, b.created_at))
    ORDER BY c.brand ASC, c.model ASC, month_num ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $car_key = $row['brand'] . ' ' . $row['model'] . ' (' . $row['plate_number'] . ')';
    if (!isset($admin_cars[$car_key])) {
        $admin_cars[$car_key] = [];
    }
    $gross = (float)$row['monthly_gross'];
    $admin_cars[$car_key][(int)$row['month_num']] = $gross;
    $total_yearly_gross += $gross;
    $monthly_totals[(int)$row['month_num']] += $gross;
}
$stmt->close();

// Sort cars by Year Total DESC
$car_totals_temp = [];
foreach ($admin_cars as $car_name => $carMonths) {
    $car_totals_temp[$car_name] = array_sum($carMonths);
}
arsort($car_totals_temp);
$admin_cars_sorted = [];
foreach ($car_totals_temp as $car_name => $total) {
    $admin_cars_sorted[$car_name] = $admin_cars[$car_name];
}
$admin_cars = $admin_cars_sorted;

foreach ($admin_cars as $carMonths) {
    if (!empty($carMonths)) $cars_with_revenue++;
}

// Best month
$best_month_num = 0;
$best_month_value = 0;
foreach ($monthly_totals as $m => $val) {
    if ($val > $best_month_value) {
        $best_month_value = $val;
        $best_month_num = $m;
    }
}
$best_month_label = $best_month_num ? $months[$best_month_num] . ' ' . $current_year : '—';

$has_data = !empty($admin_cars);

// Branch label for the header
$branch_label = 'All Branches';
if ($branch_filter_active && isset($conn)) {
    $bStmt = $conn->prepare("SELECT name FROM branches WHERE id = ? LIMIT 1");
    $bStmt->bind_param('i', $branch_filter_id);
    $bStmt->execute();
    $bRow = $bStmt->get_result()->fetch_assoc();
    $bStmt->close();
    if ($bRow) $branch_label = $bRow['name'];
}

function shortCurrency($amount) {
    $amount = (float)$amount;
    if ($amount == 0) return '—';
    if ($amount >= 1000000) return '₱' . round($amount / 1000000, 1) . 'M';
    if ($amount >= 1000)    return '₱' . round($amount / 1000, 1) . 'k';
    return '₱' . number_format($amount);
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    body, button, input, select, textarea, .form-control, .btn, .table {
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    }

    .main-content {
        background-color: var(--brand-bg, #f8fafc);
        min-height: 100vh;
        transition: background-color 0.25s ease, color 0.25s ease;
    }

    /* ============================================================
       STAT CARDS
       ============================================================ */
    .stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        padding: 1.15rem 1.25rem;
        height: 100%;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 8px 24px -8px rgba(255, 215, 0, 0.55) !important;
    }
    .stat-label {
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #475569;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .stat-value {
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
    }
    .stat-sub {
        font-size: 0.72rem;
        color: #475569;
        font-weight: 500;
        margin-top: 2px;
    }

    /* ============================================================
       FILTER BAR
       ============================================================ */
    .filter-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem 1.15rem;
    }
    .filter-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        margin-bottom: 4px;
    }
    .filter-card .form-select,
    .filter-card .form-control {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        font-size: 0.85rem;
        color: #0f172a;
        height: 38px;
    }
    .filter-card .form-select:focus,
    .filter-card .form-control:focus {
        border-color: #ffd700;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.22);
        outline: none;
    }

    .search-wrap { position: relative; width: 100%; }
    .search-wrap i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        pointer-events: none;
        font-size: 0.9rem;
    }
    .search-wrap input {
        padding-left: 2.4rem;
    }

    /* ============================================================
       CAR DROPDOWN (custom with search-in-dropdown)
       ============================================================ */
    .car-dropdown-wrapper { position: relative; }
    .car-dropdown-menu {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        max-height: 320px;
        overflow-y: auto;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        z-index: 100;
        display: none;
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        padding: 6px;
    }
    .car-dropdown-wrapper.open .car-dropdown-menu { display: block; }
    .car-dropdown-wrapper.open .car-dropdown-toggle {
        border-color: #ffd700;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.22);
    }
    .car-dropdown-toggle {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 0.75rem;
        height: 38px;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        font-size: 0.85rem;
        color: #0f172a;
        cursor: pointer;
        text-align: left;
        gap: 8px;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .car-dropdown-toggle .selected-label {
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .car-dropdown-toggle .caret {
        color: #64748b;
        transition: transform 0.2s ease;
        flex-shrink: 0;
    }
    .car-dropdown-wrapper.open .car-dropdown-toggle .caret {
        transform: rotate(180deg);
    }
    .car-dropdown-search {
        width: 100%;
        padding: 0.4rem 0.6rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.8rem;
        margin-bottom: 6px;
        outline: none;
    }
    .car-dropdown-search:focus { border-color: #ffd700; }
    .car-dropdown-item {
        padding: 0.5rem 0.7rem;
        border-radius: 8px;
        font-size: 0.82rem;
        color: #0f172a;
        cursor: pointer;
        transition: background 0.15s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .car-dropdown-item:hover { background: #fffbe6; }
    .car-dropdown-item.selected {
        background: #ffd700;
        font-weight: 700;
        color: #000;
    }
    .car-dropdown-empty {
        padding: 12px;
        text-align: center;
        font-size: 0.8rem;
        color: #94a3b8;
    }
    .car-dropdown-item .plate {
        font-size: 0.72rem;
        color: #64748b;
        margin-left: auto;
    }
    .car-dropdown-item.selected .plate { color: #000; }

    /* ============================================================
       REVENUE CARDS (per-car)
       ============================================================ */
    .revenue-row {
        border: 1px solid #e2e8f0 !important;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
    }
    .revenue-row:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 6px 18px -6px rgba(255, 215, 0, 0.5) !important;
        transform: translateY(-2px);
    }
    .revenue-row .card-header {
        background: #ffffff !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }
    .revenue-row .card-header h6 { color: #0f172a !important; }

    /* ============================================================
       REVENUE TABLE
       ============================================================ */
    .table-revenue thead th {
        background: #f1f5f9;
        font-size: 0.65rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #334155;
        font-weight: 800;
        border-bottom: 1px solid #cbd5e1;
        padding: 0.75rem 0.5rem;
    }
    .table-revenue tbody td {
        padding: 0.85rem 0.5rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.78rem;
        color: #0f172a;
        font-weight: 600;
    }
    .table-revenue tbody tr:hover td { background-color: #fffbe6; }
    .revenue-cell { min-width: 90px; }

    .table-revenue .empty-cell {
        color: #94a3b8 !important;
        font-weight: 500;
    }

    .year-total-cell {
        background: #fffbe6 !important;
        min-width: 130px;
    }
    .year-total-value {
        color: #926c00 !important;
        font-weight: 800 !important;
        font-size: 0.85rem;
    }

    /* ============================================================
       EMPTY STATES
       ============================================================ */
    .empty-state { text-align: center; padding: 60px 20px; }
    .empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 20px; }
    .empty-state h5 { color: #475569; margin-bottom: 10px; }

    /* ============================================================
       DARK MODE
       ============================================================ */
    body.dark-mode,
    body.dark-mode .main-content {
        background-color: #0a0a0a !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .stat-card,
    body.dark-mode .filter-card,
    body.dark-mode .card {
        background-color: #141414 !important;
        border-color: #27272a !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.4) !important;
    }
    body.dark-mode .stat-card:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 0 22px rgba(255, 215, 0, 0.45) !important;
    }
    body.dark-mode .stat-value { color: #ffffff !important; }
    body.dark-mode .stat-label,
    body.dark-mode .stat-sub,
    body.dark-mode .text-muted { color: #cbd5e1 !important; }

    body.dark-mode .filter-card .form-select,
    body.dark-mode .filter-card .form-control {
        background-color: #0d0d0d !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    body.dark-mode .filter-card .form-select:focus,
    body.dark-mode .filter-card .form-control:focus {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.22) !important;
    }

    body.dark-mode .search-wrap input {
        background: #0d0d0d !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    body.dark-mode .search-wrap input::placeholder { color: #64748b; }
    body.dark-mode .search-wrap input:focus {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.25) !important;
    }

    body.dark-mode .revenue-row { border-color: #27272a !important; }
    body.dark-mode .revenue-row:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 0 22px rgba(255, 215, 0, 0.35) !important;
    }
    body.dark-mode .revenue-row .card-header {
        background: #141414 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .revenue-row .card-header h6 { color: #ffffff !important; }

    body.dark-mode .table-revenue { background-color: #141414 !important; }
    body.dark-mode .table-revenue thead th {
        background-color: #1f1f23 !important;
        color: #cbd5e1 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .table-revenue tbody td {
        background-color: #141414 !important;
        color: #e2e8f0 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .table-revenue tbody tr:hover td {
        background-color: #1a1a1e !important;
    }
    body.dark-mode .table-revenue .empty-cell { color: #64748b !important; }
    body.dark-mode .year-total-cell { background-color: #1a1600 !important; }
    body.dark-mode .year-total-value { color: #ffd700 !important; }

    body.dark-mode .empty-state i { color: #3f3f46; }
    body.dark-mode .empty-state h5 { color: #cbd5e1; }

    /* Car dropdown dark mode */
    body.dark-mode .car-dropdown-toggle {
        background: #0d0d0d !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    body.dark-mode .car-dropdown-toggle .caret { color: #94a3b8; }
    body.dark-mode .car-dropdown-menu {
        background: #141414 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .car-dropdown-search {
        background: #0d0d0d !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    body.dark-mode .car-dropdown-item { color: #e2e8f0 !important; }
    body.dark-mode .car-dropdown-item:hover { background: #1a1a1e !important; }
    body.dark-mode .car-dropdown-item.selected {
        background: #ffd700 !important;
        color: #000 !important;
    }
    body.dark-mode .car-dropdown-item .plate { color: #94a3b8 !important; }
    body.dark-mode .car-dropdown-item.selected .plate { color: #000 !important; }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 col-md-auto p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">

                <!-- Header -->
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-0">Fleet <span style="color: #ffd700;">Revenue</span></h3>
                        <p class="text-muted small mb-0">
                            <?= htmlspecialchars($branch_label) ?> &middot; Earnings for <?= $current_year ?>
                        </p>
                    </div>
                </div>

                <!-- Stats row -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Total Gross (<?= $current_year ?>)</div>
                            <div class="stat-value">₱<?= number_format($total_yearly_gross, 2) ?></div>
                            <div class="stat-sub">All completed trips</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Cars With Revenue</div>
                            <div class="stat-value"><?= $cars_with_revenue ?></div>
                            <div class="stat-sub">At least 1 completed trip</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Best Month</div>
                            <div class="stat-value"><?= htmlspecialchars($best_month_label) ?></div>
                            <div class="stat-sub"><?= $best_month_value > 0 ? '₱' . number_format($best_month_value, 2) : 'No data yet' ?></div>
                        </div>
                    </div>
                </div>

                <!-- Filter bar (all controls on one row) -->
                <form method="GET" id="filterForm" class="filter-card mb-4">
                    <div class="row g-3 align-items-end">

                        <!-- Period -->
                        <div class="col-12 col-sm-6 col-lg-2">
                            <div class="filter-label">Period</div>
                            <select name="period" class="form-select" onchange="this.form.submit()">
                                <option value="all"          <?= $period === 'all'          ? 'selected' : '' ?>>All Year</option>
                                <option value="this_month"   <?= $period === 'this_month'   ? 'selected' : '' ?>>This Month</option>
                                <option value="last_30d"     <?= $period === 'last_30d'     ? 'selected' : '' ?>>Last 30 Days</option>
                                <option value="this_quarter" <?= $period === 'this_quarter' ? 'selected' : '' ?>>This Quarter</option>
                            </select>
                        </div>

                        <!-- Type -->
                        <div class="col-12 col-sm-6 col-lg-2">
                            <div class="filter-label">Type</div>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="all"    <?= $filter_type === 'all'    ? 'selected' : '' ?>>All Types</option>
                                <option value="online" <?= $filter_type === 'online' ? 'selected' : '' ?>>Online</option>
                                <option value="manual" <?= $filter_type === 'manual' ? 'selected' : '' ?>>Manual</option>
                            </select>
                        </div>

                        <!-- Car (custom dropdown with search) -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="filter-label">Car</div>
                            <div class="car-dropdown-wrapper" id="carDropdown">
                                <input type="hidden" name="car_id" id="carIdInput" value="<?= $car_filter_id ?>">
                                <button type="button" class="car-dropdown-toggle" id="carDropdownToggle">
                                    <span class="selected-label" id="carSelectedLabel">All Cars</span>
                                    <i class="bi bi-chevron-down caret"></i>
                                </button>
                                <div class="car-dropdown-menu" id="carDropdownMenu">
                                    <input type="text" class="car-dropdown-search" id="carDropdownSearch" placeholder="Search car or plate...">
                                    <div id="carDropdownList">
                                        <div class="car-dropdown-item <?= $car_filter_id === 0 ? 'selected' : '' ?>" data-car-id="0" data-search="all cars">
                                            <span>All Cars</span>
                                        </div>
                                        <?php foreach ($cars_for_filter as $c): 
                                            $label = $c['brand'] . ' ' . $c['model'];
                                        ?>
                                            <div class="car-dropdown-item <?= $car_filter_id === (int)$c['id'] ? 'selected' : '' ?>"
                                                 data-car-id="<?= (int)$c['id'] ?>"
                                                 data-search="<?= htmlspecialchars(strtolower($label . ' ' . $c['plate_number'])) ?>">
                                                <span><?= htmlspecialchars($label) ?></span>
                                                <span class="plate"><?= htmlspecialchars($c['plate_number']) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="car-dropdown-empty d-none" id="carDropdownEmpty">No cars match your search</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Search (live filter over cards below) -->
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="filter-label">Search</div>
                            <div class="search-wrap">
                                <i class="bi bi-search"></i>
                                <input type="text" id="carSearch" class="form-control" placeholder="Search car or plate...">
                            </div>
                        </div>

                        <!-- Reset -->
                        <div class="col-12 col-lg-2">
                            <a href="admin_revenue.php" class="btn btn-outline-secondary w-100" style="border-radius:10px; height:38px; display:inline-flex; align-items:center; justify-content:center;">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>

                <?php if (!$has_data): ?>
                    <div class="card border-0 shadow-sm rounded-4 empty-state">
                        <i class="bi bi-graph-up-arrow"></i>
                        <h5 class="fw-bold">No Revenue Data Found</h5>
                        <p class="text-muted mb-0 small">No completed bookings found for <?= $current_year ?> in this view.</p>
                    </div>
                <?php else: ?>

                    <?php foreach ($admin_cars as $car_name => $monthly_data): ?>
                        <div class="card border-0 shadow-sm mb-4 rounded-4 overflow-hidden revenue-row"
                             data-car="<?= htmlspecialchars(strtolower($car_name)) ?>">
                            <div class="card-header bg-white py-3 border-0">
                                <h6 class="mb-0 fw-bold">
                                    <i class="bi bi-car-front-fill me-2" style="color: #b38a00;"></i>
                                    <?= htmlspecialchars($car_name) ?>
                                </h6>
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
                                            $car_total = 0;
                                            foreach ($months as $num => $m):
                                                $g = $monthly_data[$num] ?? 0;
                                                $car_total += $g;
                                            ?>
                                                <td class="revenue-cell">
                                                    <?php if ($g > 0): ?>
                                                        <?= shortCurrency($g) ?>
                                                    <?php else: ?>
                                                        <span class="empty-cell">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td class="year-total-cell">
                                                <div class="year-total-value">
                                                    ₱<?= number_format($car_total, 2) ?>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div id="noSearchResults" class="card border-0 shadow-sm rounded-4 empty-state d-none">
                        <i class="bi bi-search"></i>
                        <h5 class="fw-bold">No cars match your search</h5>
                        <p class="text-muted mb-0 small">Try a different car name or plate number.</p>
                    </div>

                <?php endif; ?>
            </div>

            <div class="mt-auto">
                <?php require_once __DIR__ . '/../components/footer.php'; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ══════════════════════════════════════════════════
    // CAR DROPDOWN with search-in-dropdown
    // ══════════════════════════════════════════════════
    const wrapper    = document.getElementById('carDropdown');
    const toggleBtn  = document.getElementById('carDropdownToggle');
    const searchInp  = document.getElementById('carDropdownSearch');
    const list       = document.getElementById('carDropdownList');
    const emptyMsg   = document.getElementById('carDropdownEmpty');
    const hiddenInp  = document.getElementById('carIdInput');
    const selLabel   = document.getElementById('carSelectedLabel');
    const form       = document.getElementById('filterForm');

    if (wrapper) {
        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            wrapper.classList.toggle('open');
            if (wrapper.classList.contains('open') && searchInp) {
                searchInp.value = '';
                filterCarItems('');
                setTimeout(() => searchInp.focus(), 50);
            }
        });

        // Click outside closes
        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) {
                wrapper.classList.remove('open');
            }
        });

        // Search filter inside dropdown
        function filterCarItems(q) {
            q = q.trim().toLowerCase();
            const items = list.querySelectorAll('.car-dropdown-item');
            let visible = 0;

            items.forEach(item => {
                const haystack = item.getAttribute('data-search') || '';
                const match = q === '' || haystack.includes(q);
                item.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            if (emptyMsg) emptyMsg.classList.toggle('d-none', visible > 0);
        }

        if (searchInp) {
            searchInp.addEventListener('input', function () {
                filterCarItems(this.value);
            });
            searchInp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const visibleItems = Array.from(list.querySelectorAll('.car-dropdown-item'))
                        .filter(el => el.style.display !== 'none');
                    if (visibleItems.length === 1) {
                        visibleItems[0].click();
                    }
                }
            });
        }

        // Select a car
        list.addEventListener('click', function (e) {
            const item = e.target.closest('.car-dropdown-item');
            if (!item) return;

            const carId = item.getAttribute('data-car-id');
            const label = item.querySelector('span').textContent.trim();

            hiddenInp.value = carId;
            selLabel.textContent = carId === '0' ? 'All Cars' : label;

            list.querySelectorAll('.car-dropdown-item').forEach(el => el.classList.remove('selected'));
            item.classList.add('selected');

            wrapper.classList.remove('open');
            form.submit();
        });
    }

    // ══════════════════════════════════════════════════
    // CAR SEARCH (live filter over the cards below)
    // ══════════════════════════════════════════════════
    const input = document.getElementById('carSearch');
    if (input) {
        const rows = document.querySelectorAll('.revenue-row');
        const empty = document.getElementById('noSearchResults');

        input.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            let visible = 0;

            rows.forEach(row => {
                const haystack = row.getAttribute('data-car') || '';
                const match = q === '' || haystack.includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });

            if (empty) empty.classList.toggle('d-none', visible !== 0 || q === '');
        });
    }

});
</script>