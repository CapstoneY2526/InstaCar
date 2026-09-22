<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/branch_helper.php';

// Auth Check — allow admin and staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    $_SESSION['error'] = "Access denied.";
    echo '<script>window.location.href = "../../index.php";</script>';
    exit();
}

$pageTitle = 'Gross Earnings';

// ── Staff locked to own branch ──
if ($_SESSION['role'] === 'staff') {
    $staff_branch_id = (int)($_SESSION['branch_id'] ?? 0);
    $_SESSION['view_branch'] = $staff_branch_id > 0 ? (string)$staff_branch_id : 'all';
}

// ── Branch label ──
$view_branch = $_SESSION['view_branch'] ?? 'all';
$branch_label = 'All Branches';
if ($view_branch !== 'all') {
    $bid = (int)$view_branch;
    if ($bid > 0) {
        $bStmt = $conn->prepare("SELECT name FROM branches WHERE id = ? LIMIT 1");
        $bStmt->bind_param('i', $bid);
        $bStmt->execute();
        $bRow = $bStmt->get_result()->fetch_assoc();
        $bStmt->close();
        if ($bRow) $branch_label = $bRow['name'];
    }
}

$current_year = (int)date('Y');

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

$limit = $_GET['limit'] ?? 'all';   // all | 5 | 10 | 20 | 50
if (!in_array($limit, ['all', '5', '10', '20', '50'], true)) {
    $limit = 'all';
}

// ── Build period WHERE clause ──
$period_where = '';
if ($period === 'this_month') {
    $period_where = " AND MONTH(p.settled_at) = MONTH(CURDATE()) AND YEAR(p.settled_at) = YEAR(CURDATE())";
} elseif ($period === 'last_30d') {
    $period_where = " AND p.settled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($period === 'this_quarter') {
    $period_where = " AND QUARTER(p.settled_at) = QUARTER(CURDATE()) AND YEAR(p.settled_at) = YEAR(CURDATE())";
}

// ══════════════════════════════════════════════════════════════
// 0) CAR FILTER DROPDOWN OPTIONS
//    Show all cars in current branch that have ANY settled revenue this year
// ══════════════════════════════════════════════════════════════
$cars_for_filter = [];
$cars_sql = "
    SELECT DISTINCT c.id, c.brand, c.model, c.plate_number
    FROM cars c
    INNER JOIN bookings b ON b.car_id = c.id
    INNER JOIN booking_payments p ON p.booking_id = b.id
    WHERE YEAR(p.settled_at) = ? AND p.settled_at IS NOT NULL
";
$cars_params = [$current_year];
$cars_types  = 'i';
$cars_sql .= branchScopeSql('b.branch_id');
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
// 1) TOTAL STATS (respects all filters)
// ══════════════════════════════════════════════════════════════
$stats_sql = "
    SELECT
        COALESCE(SUM(p.total_gross), 0) AS total_gross,
        COUNT(DISTINCT p.id)             AS bookings_count,
        COALESCE(AVG(p.total_gross), 0)  AS avg_per_booking
    FROM booking_payments p
    INNER JOIN bookings b ON b.id = p.booking_id
    WHERE YEAR(p.settled_at) = ? 
      AND p.settled_at IS NOT NULL
      {$period_where}
";
$stats_params = [$current_year];
$stats_types  = 'i';

if ($filter_type !== 'all') {
    $stats_sql .= " AND b.booking_type = ?";
    $stats_params[] = $filter_type;
    $stats_types   .= 's';
}
if ($car_filter_id > 0) {
    $stats_sql .= " AND b.car_id = ?";
    $stats_params[] = $car_filter_id;
    $stats_types   .= 'i';
}

$stats_sql .= branchScopeSql('b.branch_id');

$stmt = $conn->prepare($stats_sql);
$stmt->bind_param($stats_types, ...$stats_params);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_gross      = (float)($stats['total_gross'] ?? 0);
$bookings_count   = (int)($stats['bookings_count'] ?? 0);
$avg_per_booking  = (float)($stats['avg_per_booking'] ?? 0);

// ══════════════════════════════════════════════════════════════
// 2) BEST MONTH + BEST CAR
// ══════════════════════════════════════════════════════════════
$month_names = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];

$best_month_sql = "
    SELECT MONTH(p.settled_at) AS m, SUM(p.total_gross) AS total
    FROM booking_payments p
    INNER JOIN bookings b ON b.id = p.booking_id
    WHERE YEAR(p.settled_at) = ? AND p.settled_at IS NOT NULL
    {$period_where}
";
$best_month_params = [$current_year];
$best_month_types  = 'i';

if ($filter_type !== 'all') {
    $best_month_sql .= " AND b.booking_type = ?";
    $best_month_params[] = $filter_type;
    $best_month_types   .= 's';
}
if ($car_filter_id > 0) {
    $best_month_sql .= " AND b.car_id = ?";
    $best_month_params[] = $car_filter_id;
    $best_month_types   .= 'i';
}
$best_month_sql .= branchScopeSql('b.branch_id');
$best_month_sql .= " GROUP BY MONTH(p.settled_at) ORDER BY total DESC LIMIT 1";

$stmt = $conn->prepare($best_month_sql);
$stmt->bind_param($best_month_types, ...$best_month_params);
$stmt->execute();
$best_month_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$best_month_label = $best_month_row ? $month_names[(int)$best_month_row['m']] . ' ' . $current_year : '—';
$best_month_value = (float)($best_month_row['total'] ?? 0);

$best_car_sql = "
    SELECT c.id AS car_id, c.brand, c.model, c.plate_number,
           SUM(p.total_gross) AS total
    FROM booking_payments p
    INNER JOIN bookings b ON b.id = p.booking_id
    INNER JOIN cars c     ON c.id = b.car_id
    WHERE YEAR(p.settled_at) = ? AND p.settled_at IS NOT NULL
    {$period_where}
";
$best_car_params = [$current_year];
$best_car_types  = 'i';

if ($filter_type !== 'all') {
    $best_car_sql .= " AND b.booking_type = ?";
    $best_car_params[] = $filter_type;
    $best_car_types   .= 's';
}
if ($car_filter_id > 0) {
    $best_car_sql .= " AND b.car_id = ?";
    $best_car_params[] = $car_filter_id;
    $best_car_types   .= 'i';
}
$best_car_sql .= branchScopeSql('b.branch_id');
$best_car_sql .= " GROUP BY c.id ORDER BY total DESC LIMIT 1";

$stmt = $conn->prepare($best_car_sql);
$stmt->bind_param($best_car_types, ...$best_car_params);
$stmt->execute();
$best_car_row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$best_car_label = $best_car_row ? ($best_car_row['brand'] . ' ' . $best_car_row['model']) : '—';
$best_car_value = (float)($best_car_row['total'] ?? 0);

// ══════════════════════════════════════════════════════════════
// 3) PER-CAR MONTHLY BREAKDOWN
// ══════════════════════════════════════════════════════════════
$car_monthly = [];

$car_sql = "
    SELECT
        c.id AS car_id,
        c.brand, c.model, c.plate_number,
        MONTH(p.settled_at) AS month_num,
        SUM(p.total_gross) AS monthly_gross
    FROM booking_payments p
    INNER JOIN bookings b ON b.id = p.booking_id
    INNER JOIN cars c     ON c.id = b.car_id
    WHERE YEAR(p.settled_at) = ? AND p.settled_at IS NOT NULL
    {$period_where}
";
$car_params = [$current_year];
$car_types  = 'i';

if ($filter_type !== 'all') {
    $car_sql .= " AND b.booking_type = ?";
    $car_params[] = $filter_type;
    $car_types   .= 's';
}
if ($car_filter_id > 0) {
    $car_sql .= " AND b.car_id = ?";
    $car_params[] = $car_filter_id;
    $car_types   .= 'i';
}
$car_sql .= branchScopeSql('b.branch_id');
$car_sql .= " GROUP BY c.id, MONTH(p.settled_at)
             ORDER BY c.brand ASC, c.model ASC, month_num ASC";

$stmt = $conn->prepare($car_sql);
$stmt->bind_param($car_types, ...$car_params);
$stmt->execute();
$car_res = $stmt->get_result();

while ($row = $car_res->fetch_assoc()) {
    $car_id   = (int)$row['car_id'];
    $month    = (int)$row['month_num'];
    $gross    = (float)$row['monthly_gross'];

    if (!isset($car_monthly[$car_id])) {
        $car_monthly[$car_id] = [
            'brand'   => $row['brand'],
            'model'   => $row['model'],
            'plate'   => $row['plate_number'],
            'months'  => array_fill(1, 12, 0),
            'total'   => 0
        ];
    }

    $car_monthly[$car_id]['months'][$month] = $gross;
    $car_monthly[$car_id]['total'] += $gross;
}
$stmt->close();

// Sort cars by total DESC
uasort($car_monthly, function($a, $b) {
    return $b['total'] <=> $a['total'];
});

// Apply Top N limit
$cars_displayed = $car_monthly;
$limit_applied = false;
if ($limit !== 'all') {
    $limit_n = (int)$limit;
    $cars_displayed = array_slice($car_monthly, 0, $limit_n, true);
    $limit_applied = true;
}

// ══════════════════════════════════════════════════════════════
// 4) MONTHLY TOTALS
//    Totals reflect ALL cars (not just the displayed ones)
//    So the Monthly Totals row remains consistent regardless of Top N
// ══════════════════════════════════════════════════════════════
$monthly_totals = array_fill(1, 12, 0);
foreach ($car_monthly as $car) {
    foreach ($car['months'] as $m => $val) {
        $monthly_totals[$m] += $val;
    }
}

// ══════════════════════════════════════════════════════════════
// 5) RECENT SETTLEMENTS (last 10)
// ══════════════════════════════════════════════════════════════
$recent_sql = "
    SELECT
        p.id AS payment_id,
        p.booking_id,
        p.total_gross,
        p.settled_at,
        c.brand, c.model, c.plate_number,
        b.guest_name,
        u.name AS member_name,
        su.name AS settled_by_name,
        su.role AS settled_by_role
    FROM booking_payments p
    INNER JOIN bookings b ON b.id = p.booking_id
    INNER JOIN cars c     ON c.id = b.car_id
    LEFT  JOIN users u    ON u.id = b.user_id
    LEFT  JOIN users su   ON su.id = p.settled_by
    WHERE YEAR(p.settled_at) = ? AND p.settled_at IS NOT NULL
      {$period_where}
";
$recent_params = [$current_year];
$recent_types  = 'i';

if ($filter_type !== 'all') {
    $recent_sql .= " AND b.booking_type = ?";
    $recent_params[] = $filter_type;
    $recent_types   .= 's';
}
if ($car_filter_id > 0) {
    $recent_sql .= " AND b.car_id = ?";
    $recent_params[] = $car_filter_id;
    $recent_types   .= 'i';
}
$recent_sql .= branchScopeSql('b.branch_id');
$recent_sql .= " ORDER BY p.settled_at DESC LIMIT 10";

$stmt = $conn->prepare($recent_sql);
$stmt->bind_param($recent_types, ...$recent_params);
$stmt->execute();
$recent_res = $stmt->get_result();
$recent_settlements = [];
while ($row = $recent_res->fetch_assoc()) {
    $recent_settlements[] = $row;
}
$stmt->close();

// ── Helper: short currency ──
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

    body, button, input, select, textarea, .form-control, .form-select, .btn, .table {
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    }

    .main-content {
        background-color: var(--brand-bg, #f8fafc);
        min-height: 100vh;
        transition: background-color 0.25s ease, color 0.25s ease;
    }

    /* ── Stat cards ── */
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

    /* ── Mini info cards ── */
    .mini-card {
        background: linear-gradient(135deg, #fffbe6 0%, #fff7d1 100%);
        border: 1px solid #ffd700;
        border-radius: 1rem;
        padding: 1rem 1.15rem;
        height: 100%;
    }
    .mini-card .mini-label {
        font-size: 0.62rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #8a6a00;
        font-weight: 800;
        margin-bottom: 4px;
    }
    .mini-card .mini-value {
        font-size: 1.05rem;
        font-weight: 800;
        color: #000;
    }
    .mini-card .mini-sub {
        font-size: 0.75rem;
        color: #8a6a00;
        font-weight: 600;
    }

    /* ── Filter bar ── */
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
        height: 40px;
    }

    /* ── Car dropdown with search-in-dropdown ── */
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
    .car-dropdown-wrapper.open .car-dropdown-menu {
        display: block;
    }
    .car-dropdown-wrapper.open .car-dropdown-toggle {
        border-color: #ffd700;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.22);
    }
    .car-dropdown-toggle {
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.45rem 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        font-size: 0.85rem;
        color: #0f172a;
        cursor: pointer;
        text-align: left;
        gap: 8px;
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
    .car-dropdown-search:focus {
        border-color: #ffd700;
    }
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
    .car-dropdown-item:hover {
        background: #fffbe6;
    }
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
    .car-dropdown-item.selected .plate {
        color: #000;
    }

    /* ── Table ── */
    .revenue-table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        overflow: hidden;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .revenue-table-card:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 6px 18px -6px rgba(255, 215, 0, 0.4) !important;
    }
    .table thead th {
        background: #f1f5f9;
        font-size: 0.65rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #334155;
        font-weight: 800;
        border-bottom: 1px solid #cbd5e1;
        padding: 0.75rem 0.5rem;
    }
    .table tbody td {
        padding: 0.75rem 0.5rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.8rem;
        color: #0f172a;
        vertical-align: middle;
    }
    .table tbody tr:hover td {
        background-color: #fffbe6;
    }
    .year-total-cell {
        background: #fffbe6 !important;
        min-width: 100px;
    }
    .year-total-value {
        color: #8a6a00 !important;
        font-weight: 800 !important;
        font-size: 0.85rem;
    }
    .month-empty {
        color: #94a3b8 !important;
        font-weight: 500;
    }

    /* ── Monthly totals row ── */
    .monthly-totals-row {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding: 6px 4px 14px 4px;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }
    .monthly-totals-row::-webkit-scrollbar { height: 6px; }
    .monthly-totals-row::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    .monthly-totals-row::-webkit-scrollbar-thumb:hover { background: #ffd700; }

    .month-tile {
        min-width: 96px;
        flex: 0 0 auto;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.85rem;
        padding: 0.75rem 0.85rem;
        text-align: center;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .month-tile:hover {
        transform: translateY(-2px);
        border-color: #ffd700 !important;
        box-shadow: 0 6px 18px -6px rgba(255, 215, 0, 0.5) !important;
    }
    .month-tile .mt-name {
        font-size: 0.68rem;
        font-weight: 800;
        color: #b38a00;
        letter-spacing: 0.4px;
        text-transform: uppercase;
    }
    .month-tile .mt-value {
        font-size: 0.9rem;
        font-weight: 800;
        color: #0f172a;
        margin-top: 4px;
    }
    .month-tile.mt-empty .mt-value {
        color: #94a3b8;
        font-weight: 600;
    }

    /* ── Mobile cards ── */
    .car-revenue-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem;
        margin-bottom: 0.85rem;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .car-revenue-card:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 6px 18px -6px rgba(255, 215, 0, 0.4) !important;
    }
    .car-revenue-card .crc-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        margin-bottom: 0.75rem;
    }
    .car-revenue-card .crc-name {
        font-weight: 800;
        color: #0f172a;
        font-size: 0.9rem;
    }
    .car-revenue-card .crc-plate {
        font-size: 0.72rem;
        color: #64748b;
    }
    .car-revenue-card .crc-total {
        font-weight: 800;
        color: #8a6a00;
        font-size: 1rem;
    }
    .car-revenue-card .crc-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
    }
    .car-revenue-card .crc-month {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 8px;
        border-radius: 6px;
        background: #f8fafc;
        font-size: 0.72rem;
    }
    .car-revenue-card .crc-month .lbl { color: #64748b; font-weight: 700; }
    .car-revenue-card .crc-month .val { color: #0f172a; font-weight: 700; }
    .car-revenue-card .crc-month.empty .val { color: #94a3b8; font-weight: 500; }

    /* ── Recent settlements ── */
    .recent-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        overflow: hidden;
    }
    .recent-card .rhead {
        padding: 1rem 1.15rem;
        border-bottom: 1px solid #f1f5f9;
        font-weight: 800;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .recent-card .rhead i { color: #b38a00; }
    .recent-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1.15rem;
        border-bottom: 1px solid #f1f5f9;
        gap: 12px;
        transition: background-color 0.15s ease;
    }
    .recent-item:hover {
        background-color: #fffbe6;
    }
    .recent-item:last-child { border-bottom: none; }
    .recent-item .ri-car {
        font-weight: 700;
        color: #0f172a;
        font-size: 0.85rem;
    }
    .recent-item .ri-sub {
        font-size: 0.72rem;
        color: #64748b;
    }
    .recent-item .ri-gross {
        font-weight: 800;
        color: #15803d;
        font-size: 0.9rem;
        text-align: right;
    }

    /* ── Empty state ── */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
    }
    .empty-state i { font-size: 64px; color: #94a3b8; margin-bottom: 20px; display: block; }
    .empty-state h5 { color: #334155; margin-bottom: 10px; font-weight: 800; }
    .empty-state p { color: #64748b; }

    /* ── Limit indicator ── */
    .limit-notice {
        font-size: 0.72rem;
        color: #8a6a00;
        font-weight: 700;
        background: #fffbe6;
        padding: 4px 10px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: 1px solid #ffd700;
        margin-bottom: 0.75rem;
    }
    body.dark-mode .limit-notice {
        background: #2a1f00;
        color: #ffd700;
        border-color: #ffd700;
    }

    /* ── Responsive ── */
    @media (min-width: 1200px) {
        .mobile-cards-wrapper { display: none !important; }
    }
    @media (max-width: 1199.98px) {
        .desktop-table-wrapper { display: none !important; }
        .mobile-cards-wrapper { display: block !important; }
    }

    /* ── Dark mode ── */
    body.dark-mode,
    body.dark-mode .main-content {
        background-color: #0a0a0a !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .stat-card,
    body.dark-mode .filter-card,
    body.dark-mode .revenue-table-card,
    body.dark-mode .month-tile,
    body.dark-mode .car-revenue-card,
    body.dark-mode .recent-card,
    body.dark-mode .empty-state {
        background-color: #141414 !important;
        border-color: #27272a !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.4) !important;
    }
    body.dark-mode .stat-card:hover,
    body.dark-mode .revenue-table-card:hover,
    body.dark-mode .month-tile:hover,
    body.dark-mode .car-revenue-card:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 0 22px rgba(255, 215, 0, 0.4) !important;
    }
    body.dark-mode .stat-value,
    body.dark-mode .month-tile .mt-value,
    body.dark-mode .car-revenue-card .crc-name,
    body.dark-mode .car-revenue-card .crc-month .val,
    body.dark-mode .recent-card .rhead,
    body.dark-mode .recent-item .ri-car { color: #ffffff !important; }
    body.dark-mode .stat-label,
    body.dark-mode .stat-sub,
    body.dark-mode .text-muted { color: #cbd5e1 !important; }

    body.dark-mode .mini-card {
        background: linear-gradient(135deg, #2a1f00 0%, #1a1400 100%) !important;
        border-color: #ffd700 !important;
    }
    body.dark-mode .mini-card .mini-label { color: #ffd700 !important; }
    body.dark-mode .mini-card .mini-value { color: #ffffff !important; }
    body.dark-mode .mini-card .mini-sub { color: #ffd700 !important; }

    body.dark-mode .table { background-color: #141414 !important; color: #f1f5f9 !important; }
    body.dark-mode .table thead th {
        background-color: #1f1f23 !important;
        color: #cbd5e1 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .table tbody td {
        background-color: #141414 !important;
        color: #e2e8f0 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .table tbody tr:hover td {
        background-color: #1a1a1e !important;
    }
    body.dark-mode .year-total-cell { background: #1a1600 !important; }
    body.dark-mode .year-total-value { color: #ffd700 !important; }
    body.dark-mode .month-empty { color: #6b7280 !important; }

    body.dark-mode .month-tile .mt-name { color: #ffd700 !important; }
    body.dark-mode .month-tile.mt-empty .mt-value { color: #6b7280 !important; }

    body.dark-mode .car-revenue-card .crc-head { border-bottom-color: #27272a !important; }
    body.dark-mode .car-revenue-card .crc-plate { color: #94a3b8 !important; }
    body.dark-mode .car-revenue-card .crc-total { color: #ffd700 !important; }
    body.dark-mode .car-revenue-card .crc-month { background: #1f1f23; }
    body.dark-mode .car-revenue-card .crc-month .lbl { color: #94a3b8 !important; }
    body.dark-mode .car-revenue-card .crc-month.empty .val { color: #6b7280 !important; }

    body.dark-mode .recent-card .rhead { border-bottom-color: #27272a !important; }
    body.dark-mode .recent-item { border-bottom-color: #27272a !important; }
    body.dark-mode .recent-item:hover { background-color: #1a1a1e !important; }
    body.dark-mode .recent-item .ri-sub { color: #94a3b8 !important; }
    body.dark-mode .recent-item .ri-gross { color: #4ade80 !important; }

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
    body.dark-mode .search-wrap input::placeholder { color: #64748b !important; }
    body.dark-mode .search-wrap i { color: #64748b; }

    body.dark-mode .empty-state i { color: #71717a !important; }
    body.dark-mode .empty-state h5 { color: #e2e8f0 !important; }
    body.dark-mode .empty-state p { color: #cbd5e1 !important; }
    body.dark-mode h3 { color: #ffffff !important; }

    /* Car dropdown dark mode */
    body.dark-mode .car-dropdown-toggle {
        background: #0d0d0d !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    body.dark-mode .car-dropdown-menu {
        background: #141414 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .car-dropdown-search {
        background: #0d0d0d !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    body.dark-mode .car-dropdown-item {
        color: #e2e8f0 !important;
    }
    body.dark-mode .car-dropdown-item:hover {
        background: #1a1a1e !important;
    }
    body.dark-mode .car-dropdown-item.selected {
        background: #ffd700 !important;
        color: #000 !important;
    }
    body.dark-mode .car-dropdown-item .plate { color: #94a3b8 !important; }
    body.dark-mode .car-dropdown-item.selected .plate { color: #000 !important; }

    @media (max-width: 576px) {
        .car-revenue-card .crc-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
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
                        <h3 class="fw-bold mb-0">Gross <span style="color: #b38a00;">Earnings</span></h3>
                        <p class="text-muted small mb-0">
                            <?= htmlspecialchars($branch_label) ?> &middot; <?= $current_year ?> &middot; Settled bookings only
                        </p>
                    </div>
                </div>

                <!-- Stats row -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Total Gross (<?= $current_year ?>)</div>
                            <div class="stat-value">₱<?= number_format($total_gross, 2) ?></div>
                            <div class="stat-sub">All settled bookings</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Bookings Settled</div>
                            <div class="stat-value"><?= number_format($bookings_count) ?></div>
                            <div class="stat-sub">Total count</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Avg / Booking</div>
                            <div class="stat-value">₱<?= number_format($avg_per_booking, 2) ?></div>
                            <div class="stat-sub">Mean gross</div>
                        </div>
                    </div>
                </div>

                <!-- Filter bar -->
                <form method="GET" id="filterForm" class="filter-card mb-4">
                    <div class="row g-3 align-items-end">

                        <!-- Period -->
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="filter-label">Period</div>
                            <select name="period" class="form-select" onchange="this.form.submit()">
                                <option value="all"          <?= $period === 'all'          ? 'selected' : '' ?>>All Year</option>
                                <option value="this_month"   <?= $period === 'this_month'   ? 'selected' : '' ?>>This Month</option>
                                <option value="last_30d"     <?= $period === 'last_30d'     ? 'selected' : '' ?>>Last 30 Days</option>
                                <option value="this_quarter" <?= $period === 'this_quarter' ? 'selected' : '' ?>>This Quarter</option>
                            </select>
                        </div>

                        <!-- Type -->
                        <div class="col-12 col-sm-6 col-md-2">
                            <div class="filter-label">Type</div>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="all"    <?= $filter_type === 'all'    ? 'selected' : '' ?>>All</option>
                                <option value="online" <?= $filter_type === 'online' ? 'selected' : '' ?>>Online</option>
                                <option value="manual" <?= $filter_type === 'manual' ? 'selected' : '' ?>>Manual</option>
                            </select>
                        </div>

                        <!-- Car (custom dropdown with search) -->
                        <div class="col-12 col-sm-6 col-md-3">
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

                        <!-- Limit -->
                        <div class="col-12 col-sm-6 col-md-2">
                            <div class="filter-label">Show</div>
                            <select name="limit" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?= $limit === 'all' ? 'selected' : '' ?>>All Cars</option>
                                <option value="5"   <?= $limit === '5'   ? 'selected' : '' ?>>Top 5</option>
                                <option value="10"  <?= $limit === '10'  ? 'selected' : '' ?>>Top 10</option>
                                <option value="20"  <?= $limit === '20'  ? 'selected' : '' ?>>Top 20</option>
                                <option value="50"  <?= $limit === '50'  ? 'selected' : '' ?>>Top 50</option>
                            </select>
                        </div>

                        <!-- Reset -->
                        <div class="col-12 col-md-2">
                            <a href="staff_gross.php" class="btn btn-outline-secondary w-100" style="border-radius:10px; height:40px; display:inline-flex; align-items:center; justify-content:center;">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>

                <?php if (empty($car_monthly)): ?>
                    <div class="empty-state">
                        <i class="bi bi-graph-up-arrow"></i>
                        <h5>No Revenue Data Yet</h5>
                        <p class="mb-0 small">No settled bookings found for <?= $current_year ?> in this view.</p>
                    </div>
                <?php else: ?>

                    <!-- Best Month / Best Car -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <div class="mini-card">
                                <div class="mini-label">🏆 Best Month</div>
                                <div class="mini-value"><?= htmlspecialchars($best_month_label) ?></div>
                                <div class="mini-sub">₱<?= number_format($best_month_value, 2) ?></div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="mini-card">
                                <div class="mini-label">🚗 Best Car</div>
                                <div class="mini-value"><?= htmlspecialchars($best_car_label) ?></div>
                                <div class="mini-sub">₱<?= number_format($best_car_value, 2) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Per-Car Monthly Breakdown -->
                    <div class="mb-4">
                        <div class="filter-label mb-2" style="font-size: 0.72rem;">
                            <i class="bi bi-car-front-fill me-1" style="color: #b38a00;"></i>
                            Per-Car Monthly Breakdown
                        </div>

                        <?php if ($limit_applied): ?>
                            <div class="limit-notice">
                                <i class="bi bi-funnel-fill"></i>
                                Showing Top <?= (int)$limit ?> cars by gross in selected period
                            </div>
                        <?php endif; ?>

                        <!-- Desktop table -->
                        <div class="desktop-table-wrapper">
                            <div class="revenue-table-card">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0 text-center">
                                        <thead>
                                            <tr>
                                                <th class="text-start ps-3" style="min-width: 200px;">Car</th>
                                                <?php foreach ($month_names as $m): ?>
                                                    <th><?= $m ?></th>
                                                <?php endforeach; ?>
                                                <th class="year-total-cell">TOTAL</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cars_displayed as $car_id => $car): ?>
                                                <tr class="js-car-row">
                                                    <td class="text-start ps-3">
                                                        <div class="fw-bold"><?= htmlspecialchars($car['brand']) ?> <?= htmlspecialchars($car['model']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($car['plate']) ?></small>
                                                    </td>
                                                    <?php foreach ($car['months'] as $m => $val): ?>
                                                        <td class="<?= $val > 0 ? '' : 'month-empty' ?>">
                                                            <?= shortCurrency($val) ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    <td class="year-total-cell">
                                                        <span class="year-total-value">₱<?= number_format($car['total'], 2) ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile cards -->
                        <div class="mobile-cards-wrapper">
                            <?php foreach ($cars_displayed as $car_id => $car): ?>
                                <div class="car-revenue-card">
                                    <div class="crc-head">
                                        <div>
                                            <div class="crc-name"><?= htmlspecialchars($car['brand']) ?> <?= htmlspecialchars($car['model']) ?></div>
                                            <div class="crc-plate"><?= htmlspecialchars($car['plate']) ?></div>
                                        </div>
                                        <div class="crc-total">₱<?= number_format($car['total'], 2) ?></div>
                                    </div>
                                    <div class="crc-grid">
                                        <?php foreach ($car['months'] as $m => $val): ?>
                                            <div class="crc-month <?= $val > 0 ? '' : 'empty' ?>">
                                                <span class="lbl"><?= $month_names[$m] ?></span>
                                                <span class="val"><?= $val > 0 ? shortCurrency($val) : '—' ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Monthly Totals -->
                    <div class="mb-4">
                        <div class="filter-label mb-2" style="font-size: 0.72rem;">
                            <i class="bi bi-calendar3 me-1" style="color: #b38a00;"></i>
                            Monthly Totals
                        </div>
                        <div class="monthly-totals-row">
                            <?php foreach ($monthly_totals as $m => $val): ?>
                                <div class="month-tile <?= $val > 0 ? '' : 'mt-empty' ?>">
                                    <div class="mt-name"><?= $month_names[$m] ?></div>
                                    <div class="mt-value"><?= $val > 0 ? shortCurrency($val) : '—' ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Recent Settlements -->
                    <?php if (!empty($recent_settlements)): ?>
                        <div class="mb-4">
                            <div class="filter-label mb-2" style="font-size: 0.72rem;">
                                <i class="bi bi-clock-history me-1" style="color: #b38a00;"></i>
                                Recent Settlements (Last 10)
                            </div>
                            <div class="recent-card">
                                <div class="rhead">
                                    <i class="bi bi-receipt"></i>
                                    <span>Latest Settled Bookings</span>
                                </div>
                                <?php foreach ($recent_settlements as $r): ?>
                                    <div class="recent-item">
                                        <div>
                                            <div class="ri-car">
                                                <?= htmlspecialchars($r['brand']) ?> <?= htmlspecialchars($r['model']) ?>
                                            </div>
                                            <div class="ri-sub">
                                                <?= htmlspecialchars($r['guest_name'] ?: $r['member_name'] ?: 'N/A') ?>
                                                &middot;
                                                <?= date('M d, Y g:i A', strtotime($r['settled_at'])) ?>
                                                &middot;
                                                by <?= htmlspecialchars($r['settled_by_name'] ?? 'Unknown') ?>
                                            </div>
                                        </div>
                                        <div class="ri-gross">₱<?= number_format((float)$r['total_gross'], 2) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

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
    const menu       = document.getElementById('carDropdownMenu');
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
            // Prevent form submit when pressing Enter inside search
            searchInp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    // If exactly 1 item is visible, select it
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

            // Update selected highlight
            list.querySelectorAll('.car-dropdown-item').forEach(el => el.classList.remove('selected'));
            item.classList.add('selected');

            // Close and submit
            wrapper.classList.remove('open');
            form.submit();
        });
    }

});
</script>