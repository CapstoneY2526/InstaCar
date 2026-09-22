<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/branch_helper.php';

// Auth Check
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

$pageTitle = 'Car Expense Tracker';
$selected_car = $_GET['car_id'] ?? null;
$current_year = date('Y');

// ── Active branch label ──
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

// ── Monthly data — scoped by branch + selected car ──
$monthly_data = [];

if ($selected_car) {
    // Verify the selected car belongs to the current branch view
    $verifySql = "SELECT c.id FROM cars c WHERE c.id = ?" . branchScopeSql('c.branch_id') . " LIMIT 1";
    $vStmt = $conn->prepare($verifySql);
    $vStmt->bind_param('i', $selected_car);
    $vStmt->execute();
    $selected_car_valid = (bool)$vStmt->get_result()->fetch_assoc();
    $vStmt->close();

    if ($selected_car_valid) {
        $query = "SELECT 
                    MONTH(b.start_date) as month_num,
                    COALESCE(SUM(p.daily_rent), 0) as daily_rent,
                    COALESCE(SUM(p.carwash), 0) as carwash,
                    COALESCE(SUM(p.extension_fee), 0) as extension_fee,
                    COALESCE(SUM(p.delivery_fee), 0) as delivery_fee,
                    COALESCE(SUM(p.pickup_fee), 0) as pickup_fee,
                    COALESCE(SUM(p.fuel), 0) as fuel,
                    COALESCE(SUM(p.driver_fee), 0) as driver_fee,
                    COALESCE(SUM(p.damage_fee), 0) as damage_fee,
                    COALESCE(SUM(p.agent_fee), 0) as agent_fee,
                    COALESCE(SUM(p.others), 0) as others,
                    COALESCE(SUM(p.total_gross), 0) as total_gross,
                    COALESCE(SUM(p.total_net), 0) as total_net
                  FROM bookings b
                  JOIN booking_payments p ON b.id = p.booking_id
                  WHERE b.car_id = ? AND YEAR(b.start_date) = ?
                  GROUP BY MONTH(b.start_date)";
        
        $stmt = mysqli_prepare($conn, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $selected_car, $current_year);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($res)) {
                $monthly_data[$row['month_num']] = $row;
            }
            mysqli_stmt_close($stmt);
        } else {
            die("Expense Query Failed: " . mysqli_error($conn));
        }
    } else {
        $selected_car = null;
    }
}

// ── Cars for the dropdown — scoped by branch ──
$cars = [];
$carsSql = "SELECT id, brand, model, plate_number FROM cars WHERE 1=1" . branchScopeSql() . " ORDER BY brand ASC";
$cars_res = mysqli_query($conn, $carsSql);
if ($cars_res) {
    while ($car_row = mysqli_fetch_assoc($cars_res)) {
        $cars[] = $car_row;
    }
}

$months = [
    1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR', 5 => 'MAY', 6 => 'JUN', 
    7 => 'JUL', 8 => 'AUG', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DEC'
];

$categories = [
    'daily_rent' => 'Daily Rent',
    'carwash' => 'Carwash',
    'extension_fee' => 'Extension Fee',
    'delivery_fee' => 'Delivery Fee',
    'pickup_fee' => 'Pickup Fee',
    'fuel' => 'Fuel',
    'driver_fee' => 'Driver Fee',
    'damage_fee' => 'Damage Fee',
    'agent_fee' => 'Agent Fee',
    'others' => 'Others'
];

// Year totals
$year_gross = 0;
$year_net = 0;
foreach ($monthly_data as $d) {
    $year_gross += (float)$d['total_gross'];
    $year_net   += (float)$d['total_net'];
}

// Selected car label
$selected_car_label = '';
foreach ($cars as $c) {
    if ($c['id'] == $selected_car) {
        $selected_car_label = $c['brand'] . ' ' . $c['model'] . ' (' . $c['plate_number'] . ')';
        break;
    }
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

    /* ── Vehicle selector (compact, inline with header) ── */
    .vehicle-selector {
        display: flex;
        flex-direction: column;
        min-width: 260px;
        max-width: 300px;
        width: 100%;
    }
    .vehicle-selector .filter-label {
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #475569;
        margin-bottom: 4px;
    }
    .vehicle-selector .form-select {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        font-size: 0.85rem;
        font-weight: 600;
        color: #0f172a;
        background-color: #ffffff;
        padding: 0.55rem 2.2rem 0.55rem 0.9rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .vehicle-selector .form-select:focus {
        border-color: #ffd700;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.22);
        outline: none;
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
    .stat-value.vehicle-value { font-size: 1rem; font-weight: 700; }
    .stat-sub {
        font-size: 0.72rem;
        color: #475569;
        font-weight: 500;
        margin-top: 2px;
    }

    /* ── Section headings ── */
    .section-heading {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #334155;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .section-heading i { color: #b38a00; font-size: 0.95rem; }

    /* ── Monthly summary grid ── */
    .monthly-summary-grid {
        display: flex;
        overflow-x: auto;
        overflow-y: visible;
        gap: 12px;
        padding: 6px 4px 14px 4px;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }
    .monthly-summary-grid::-webkit-scrollbar { height: 6px; }
    .monthly-summary-grid::-webkit-scrollbar-track { background: transparent; }
    .monthly-summary-grid::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
    .monthly-summary-grid::-webkit-scrollbar-thumb:hover { background: #ffd700; }

    .summary-card {
        min-width: 150px;
        flex: 0 0 auto;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 0.85rem 0.95rem;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .summary-card:hover {
        transform: translateY(-2px);
        border-color: #ffd700 !important;
        box-shadow: 0 6px 18px -6px rgba(255, 215, 0, 0.5) !important;
    }
    .summary-card.is-empty {
        background: #fafbfc;
        opacity: 0.7;
    }
    .summary-card.is-empty:hover {
        opacity: 1;
    }

    .summary-card .m-name {
        font-weight: 800;
        color: #b38a00;
        font-size: 0.72rem;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 6px;
        margin-bottom: 8px;
        display: block;
        text-align: center;
    }
    .summary-card.is-empty .m-name {
        color: #94a3b8;
    }

    .summary-card .m-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 3px 0;
        font-size: 0.78rem;
    }
    .summary-card .m-row .m-lbl {
        font-size: 0.62rem;
        text-transform: uppercase;
        font-weight: 700;
        color: #64748b;
        letter-spacing: 0.4px;
    }
    .summary-card .m-row .m-val {
        font-weight: 800;
        color: #0f172a;
        font-size: 0.82rem;
    }
    .summary-card .m-row .m-val.net-pos { color: #15803d; }
    .summary-card .m-row .m-val.net-neg { color: #b91c1c; }
    .summary-card .m-row .m-val.is-muted { color: #94a3b8; font-weight: 600; }

    /* ── Table card ── */
    .expense-table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        overflow: hidden;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .expense-table-card:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 6px 18px -6px rgba(255, 215, 0, 0.4) !important;
    }
    .expense-table-card .card-header {
        padding: 1rem 1.25rem;
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
    }
    .expense-table-card .card-header h6 {
        font-weight: 800;
        margin: 0;
        color: #0f172a;
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
        font-size: 0.82rem;
        color: #0f172a;
        vertical-align: middle;
    }
    .table tbody tr:hover td {
        background-color: #fffbe6;
    }
    .table tbody td.cat-cell {
        padding-left: 1rem;
        font-weight: 700;
        color: #334155;
    }
    .table tbody td.empty-cell {
        color: #94a3b8 !important;
        font-weight: 500;
    }
    .table tbody td.has-value {
        font-weight: 700;
        color: #0f172a;
    }

    .table tfoot td {
        padding: 0.9rem 0.5rem;
        font-weight: 800;
        background: #0f172a;
        color: #ffffff;
        border-top: 2px solid #020617;
    }
    .table tfoot td:first-child { padding-left: 1rem; }
    .table tfoot td.net-pos { color: #4ade80; }
    .table tfoot td.net-neg { color: #fca5a5; }

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

    /* ── Responsive ── */
    @media (min-width: 992px) {
        .mobile-financial-card { display: none !important; }
    }
    @media (max-width: 991.98px) {
        .responsive-table-card-container { display: none !important; }

        .mobile-financial-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            overflow: hidden;
            margin-bottom: 1rem;
            transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .mobile-financial-card:hover {
            border-color: #ffd700 !important;
            box-shadow: 0 0 0 1px #ffd700, 0 6px 18px -6px rgba(255, 215, 0, 0.4) !important;
        }
        .mobile-financial-card .mfc-head {
            padding: 0.85rem 1.1rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 800;
            color: #b38a00;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .mobile-financial-card .mfc-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.65rem 1.1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }
        .mobile-financial-card .mfc-row:last-child { border-bottom: none; }
        .mobile-financial-card .mfc-row .lbl { color: #475569; font-weight: 600; }
        .mobile-financial-card .mfc-row .val { font-weight: 700; color: #0f172a; }
        .mobile-financial-card .mfc-row .val.net-pos { color: #15803d; }
        .mobile-financial-card .mfc-row .val.net-neg { color: #b91c1c; }
        .mobile-financial-card .mfc-row .val.is-muted { color: #94a3b8; font-weight: 600; }
        .mobile-financial-card .mfc-total-row {background: #f8fafc; border-top: 1px solid #e2e8f0;}
    }

    /* ============================================================
       DARK MODE
       ============================================================ */
    body.dark-mode,
    body.dark-mode .main-content {
        background-color: #0a0a0a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .stat-card,
    body.dark-mode .summary-card,
    body.dark-mode .expense-table-card,
    body.dark-mode .mobile-financial-card,
    body.dark-mode .empty-state {
        background-color: #141414 !important;
        border-color: #27272a !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.4) !important;
    }
    body.dark-mode .summary-card.is-empty {
        background-color: #0f0f0f !important;
        opacity: 0.65;
    }
    body.dark-mode .stat-card:hover,
    body.dark-mode .summary-card:hover,
    body.dark-mode .expense-table-card:hover,
    body.dark-mode .mobile-financial-card:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 22px rgba(255, 215, 0, 0.4) !important;
    }

    body.dark-mode .stat-value,
    body.dark-mode .summary-card .m-row .m-val,
    body.dark-mode .expense-table-card .card-header h6 { color: #ffffff !important; }
    body.dark-mode .stat-label,
    body.dark-mode .stat-sub,
    body.dark-mode .summary-card .m-row .m-lbl,
    body.dark-mode .text-muted { color: #cbd5e1 !important; }

    body.dark-mode .section-heading { color: #e2e8f0 !important; }

    /* Vehicle selector dark mode — this was the white-in-dark-mode bug */
    body.dark-mode .vehicle-selector .filter-label { color: #cbd5e1 !important; }
    body.dark-mode .vehicle-selector .form-select {
        background-color: #0d0d0d !important;
        border-color: #27272a !important;
        color: #ffffff !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffd700' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    }
    body.dark-mode .vehicle-selector .form-select:focus {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.25) !important;
    }
    body.dark-mode .vehicle-selector .form-select option {
        background-color: #141414 !important;
        color: #ffffff !important;
    }

    /* Summary card dark mode refinements */
    body.dark-mode .summary-card .m-name { color: #ffd700 !important; border-bottom-color: #27272a !important; }
    body.dark-mode .summary-card.is-empty .m-name { color: #6b7280 !important; }
    body.dark-mode .summary-card .m-row .m-val.net-pos { color: #4ade80 !important; }
    body.dark-mode .summary-card .m-row .m-val.net-neg { color: #fca5a5 !important; }
    body.dark-mode .summary-card .m-row .m-val.is-muted { color: #6b7280 !important; }

    body.dark-mode .expense-table-card .card-header {
        background: #141414 !important;
        border-color: #27272a !important;
    }

    /* Table dark mode */
    body.dark-mode .table {
        background-color: #141414 !important;
        color: #f1f5f9 !important;
    }
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
    body.dark-mode .table tbody td.cat-cell { color: #cbd5e1 !important; }
    body.dark-mode .table tbody td.empty-cell { color: #64748b !important; }
    body.dark-mode .table tbody td.has-value { color: #ffffff !important; }

    body.dark-mode .table tfoot td {
        background-color: #18181b !important;
        color: #ffffff !important;
        border-top-color: #27272a !important;
    }
    body.dark-mode .table tfoot td.net-pos { color: #4ade80 !important; }
    body.dark-mode .table tfoot td.net-neg { color: #fca5a5 !important; }

    body.dark-mode .mobile-financial-card .mfc-head {
        background: #1f1f23 !important;
        border-color: #27272a !important;
        color: #ffd700 !important;
    }
    body.dark-mode .mobile-financial-card .mfc-row { border-color: #27272a !important; }
    body.dark-mode .mobile-financial-card .mfc-row .lbl { color: #cbd5e1 !important; }
    body.dark-mode .mobile-financial-card .mfc-total-row {background: #1f1f23 !important; border-top-color: #27272a !important;}
    body.dark-mode .mobile-financial-card .mfc-row .val { color: #ffffff !important; }
    body.dark-mode .mobile-financial-card .mfc-row .val.net-pos { color: #4ade80 !important; }
    body.dark-mode .mobile-financial-card .mfc-row .val.net-neg { color: #fca5a5 !important; }
    body.dark-mode .mobile-financial-card .mfc-row .val.is-muted { color: #6b7280 !important; }

    body.dark-mode .empty-state i { color: #71717a !important; }
    body.dark-mode .empty-state h5 { color: #e2e8f0 !important; }
    body.dark-mode .empty-state p { color: #cbd5e1 !important; }
    body.dark-mode h3 { color: #ffffff !important; }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        <div class="col-12 col-md-auto p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4" style="flex: 1;">

                <!-- Header with inline vehicle selector -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 gap-2">
                    <div>
                        <h3 class="fw-bold mb-0">Fleet <span style="color: #ffd700;">Breakdown</span></h3>
                        <p class="text-muted mb-0 small">
                            <?= htmlspecialchars($branch_label) ?> &middot; Performance Review for Fiscal Year <?= $current_year ?>
                        </p>
                    </div>

                    <form method="GET" class="vehicle-selector">
                        <label class="filter-label">
                            <i class="bi bi-car-front me-1" style="color:#b38a00;"></i>Vehicle
                        </label>
                        <select name="car_id" class="form-select" onchange="this.form.submit()">
                            <option value="">— Select a Vehicle —</option>
                            <?php foreach ($cars as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $selected_car == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['brand'] . ' ' . $c['model'] . ' (' . $c['plate_number'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <?php if (!$selected_car): ?>
                    <div class="empty-state">
                        <i class="bi bi-graph-up-arrow"></i>
                        <h5>No Vehicle Selected</h5>
                        <p class="mb-0 small">Choose a vehicle from the dropdown to view its monthly breakdown.</p>
                    </div>
                <?php else: ?>

                    <!-- Stats row -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-4">
                            <div class="stat-card">
                                <div class="stat-label">Year Gross</div>
                                <div class="stat-value">₱<?= number_format($year_gross, 2) ?></div>
                                <div class="stat-sub">All fee categories combined</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="stat-card">
                                <div class="stat-label">Year Net</div>
                                <div class="stat-value" style="color: <?= $year_net >= 0 ? '#15803d' : '#b91c1c' ?>;">
                                    ₱<?= number_format($year_net, 2) ?>
                                </div>
                                <div class="stat-sub">Gross minus staff/agent payouts</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <div class="stat-card">
                                <div class="stat-label">Currently Viewing</div>
                                <div class="stat-value vehicle-value"><?= htmlspecialchars($selected_car_label) ?></div>
                                <div class="stat-sub">Vehicle breakdown</div>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly summary -->
                    <div class="section-heading">
                        <i class="bi bi-calendar3"></i>Monthly Summary
                    </div>
                    <div class="monthly-summary-grid mb-4">
                        <?php foreach ($months as $num => $name):
                            $m_gross = (float)($monthly_data[$num]['total_gross'] ?? 0);
                            $m_net   = (float)($monthly_data[$num]['total_net'] ?? 0);
                            $isEmpty = ($m_gross <= 0 && $m_net <= 0);
                            $netCls  = $isEmpty ? 'is-muted' : ($m_net >= 0 ? 'net-pos' : 'net-neg');
                            $cardCls = $isEmpty ? 'summary-card is-empty' : 'summary-card';
                        ?>
                            <div class="<?= $cardCls ?>">
                                <span class="m-name"><?= $name ?></span>
                                <div class="m-row">
                                    <span class="m-lbl">Gross</span>
                                    <span class="m-val <?= $isEmpty ? 'is-muted' : '' ?>">
                                        <?= $isEmpty ? '—' : '₱' . number_format($m_gross) ?>
                                    </span>
                                </div>
                                <div class="m-row">
                                    <span class="m-lbl">Net</span>
                                    <span class="m-val <?= $netCls ?>">
                                        <?= $isEmpty ? '—' : '₱' . number_format($m_net) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Desktop table -->
                    <div class="section-heading">
                        <i class="bi bi-list-check"></i>Expense &amp; Fee Distribution
                    </div>
                    <div class="responsive-table-card-container">
                        <div class="expense-table-card">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="ps-4" style="min-width: 170px;">Category</th>
                                            <?php foreach($months as $name) echo "<th class='text-center'>$name</th>"; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $key => $label): ?>
                                            <tr>
                                                <td class="cat-cell"><?= $label ?></td>
                                                <?php foreach ($months as $num => $name):
                                                    $val = (float)($monthly_data[$num][$key] ?? 0);
                                                    $cls = $val > 0 ? 'has-value' : 'empty-cell';
                                                ?>
                                                    <td class="text-center <?= $cls ?>" style="min-width: 95px;">
                                                        <?= $val > 0 ? '₱' . number_format($val) : '—' ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td>TOTAL NET REVENUE</td>
                                            <?php foreach ($months as $num => $name):
                                                $net = (float)($monthly_data[$num]['total_net'] ?? 0);
                                                $cls = $net >= 0 ? 'net-pos' : 'net-neg';
                                            ?>
                                                <td class="text-center <?= $cls ?>">
                                                    ₱<?= number_format($net) ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile cards -->
                    <div class="mobile-cards-wrapper">
                        <?php foreach ($monthly_data as $num => $d): ?>
                            <div class="mobile-financial-card">
                                <div class="mfc-head"><?= $months[$num] ?></div>
                                <?php foreach ($categories as $key => $label):
                                    $val = (float)($d[$key] ?? 0);
                                ?>
                                    <div class="mfc-row">
                                        <span class="lbl"><?= $label ?></span>
                                        <span class="val <?= $val > 0 ? '' : 'is-muted' ?>">
                                            <?= $val > 0 ? '₱' . number_format($val) : '—' ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <div class="mfc-row mfc-total-row">
                                    <span class="lbl fw-bold">Net Total</span>
                                    <span class="val <?= ($d['total_net'] ?? 0) >= 0 ? 'net-pos' : 'net-neg' ?>">
                                        ₱<?= number_format($d['total_net'] ?? 0) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>
            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>