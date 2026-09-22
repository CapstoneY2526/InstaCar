<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Staff-only guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    $_SESSION['error'] = "Access denied.";
    ?>
    <script>window.stop(); window.location.href = "../../index.php";</script>
    <?php
    exit();
}

$pageTitle = 'Branch Revenue';
$current_year = date('Y');
$staff_branch_id = (int)($_SESSION['branch_id'] ?? 0);

// Months labels
$months = [1=>"JAN", 2=>"FEB", 3=>"MAR", 4=>"APR", 5=>"MAY", 6=>"JUN", 7=>"JUL", 8=>"AUG", 9=>"SEP", 10=>"OCT", 11=>"NOV", 12=>"DEC"];

$branch_name = 'Your Branch';
$branch_cars = [];
$total_yearly_gross = 0;
$cars_with_revenue = 0;
$monthly_totals = array_fill(1, 12, 0);

if ($staff_branch_id > 0) {

    // Fetch branch name
    $bStmt = $conn->prepare("SELECT name FROM branches WHERE id = ? LIMIT 1");
    $bStmt->bind_param('i', $staff_branch_id);
    $bStmt->execute();
    $bRow = $bStmt->get_result()->fetch_assoc();
    $bStmt->close();
    if ($bRow) {
        $branch_name = $bRow['name'];
    }

    // Fetch revenue per car per month
    $sql = "
        SELECT
            c.id AS car_id,
            c.brand,
            c.model,
            c.plate_number,
            MONTH(COALESCE(p.created_at, b.created_at)) AS month_num,
            SUM(COALESCE(p.total_gross, b.total_price)) AS monthly_gross
        FROM cars c
        INNER JOIN bookings b
            ON b.car_id = c.id
           AND b.status = 'Completed'
        LEFT JOIN booking_payments p
            ON p.booking_id = b.id
        WHERE c.branch_id = ?
          AND YEAR(COALESCE(p.created_at, b.created_at)) = ?
        GROUP BY c.id, MONTH(COALESCE(p.created_at, b.created_at))
        ORDER BY c.brand ASC, c.model ASC, month_num ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $staff_branch_id, $current_year);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $car_key = $row['brand'] . ' ' . $row['model'] . ' (' . $row['plate_number'] . ')';
        if (!isset($branch_cars[$car_key])) {
            $branch_cars[$car_key] = [];
        }
        $gross = (float)$row['monthly_gross'];
        $branch_cars[$car_key][(int)$row['month_num']] = $gross;
        $total_yearly_gross += $gross;
        $monthly_totals[(int)$row['month_num']] += $gross;
    }
    $stmt->close();

    foreach ($branch_cars as $carMonths) {
        if (!empty($carMonths)) {
            $cars_with_revenue++;
        }
    }
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

$has_data = !empty($branch_cars);

// Shorthand formatter
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
       SEARCH BAR
       ============================================================ */
    .search-wrap {
        position: relative;
        max-width: 340px;
    }
    .search-wrap i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        pointer-events: none;
    }
    .search-wrap input {
        padding-left: 2.5rem;
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        height: 42px;
        font-size: 0.875rem;
        color: #0f172a;
        width: 100%;
        transition: all 0.2s ease;
    }
    .search-wrap input::placeholder { color: #94a3b8; }
    .search-wrap input:focus {
        border-color: #ffd700;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.22);
        outline: none;
    }

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
    .revenue-row .card-header h6 {
        color: #0f172a !important;
    }

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
    .table-revenue tbody tr:hover td {
        background-color: #fffbe6;
    }
    .revenue-cell { min-width: 90px; }

    /* Month cells: nudge the dash to look muted but visible */
    .table-revenue .empty-cell {
        color: #94a3b8 !important;
        font-weight: 500;
    }

    /* Year total column */
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

    body.dark-mode .revenue-row {
        border-color: #27272a !important;
    }
    body.dark-mode .revenue-row:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 0 22px rgba(255, 215, 0, 0.35) !important;
    }
    body.dark-mode .revenue-row .card-header {
        background: #141414 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .revenue-row .card-header h6 {
        color: #ffffff !important;
    }

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
    body.dark-mode .table-revenue .empty-cell {
        color: #64748b !important;
    }
    body.dark-mode .year-total-cell {
        background-color: #1a1600 !important;
    }
    body.dark-mode .year-total-value {
        color: #ffd700 !important;
    }

    body.dark-mode .empty-state i { color: #3f3f46; }
    body.dark-mode .empty-state h5 { color: #cbd5e1; }

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
                        <h3 class="fw-bold mb-0">Branch <span style="color: #b38a00;">Revenue</span></h3>
                        <p class="text-muted small mb-0">
                            <?= htmlspecialchars($branch_name) ?> &middot; Earnings for <?= $current_year ?>
                        </p>
                    </div>
                </div>

                <?php if ($staff_branch_id <= 0): ?>
                    <div class="card border-0 shadow-sm rounded-4 empty-state">
                        <i class="bi bi-shop"></i>
                        <h5 class="fw-bold">No branch assigned</h5>
                        <p class="text-muted mb-0 small">Contact your administrator to assign your account to a branch.</p>
                    </div>
                <?php else: ?>

                    <!-- Stats row -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-4">
                            <div class="stat-card">
                                <div class="stat-label">Total Gross (<?= $current_year ?>)</div>
                                <div class="stat-value">₱<?= number_format($total_yearly_gross, 2) ?></div>
                                <div class="stat-sub">Full customer-paid revenue</div>
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

                    <?php if (!$has_data): ?>
                        <div class="card border-0 shadow-sm rounded-4 empty-state">
                            <i class="bi bi-graph-up-arrow"></i>
                            <h5 class="fw-bold">No Revenue Data Found</h5>
                            <p class="text-muted mb-0 small">No completed bookings for <?= $current_year ?> at this branch.</p>
                        </div>
                    <?php else: ?>

                        <!-- Search bar -->
                        <div class="d-flex justify-content-end mb-3">
                            <div class="search-wrap">
                                <i class="bi bi-search"></i>
                                <input type="text" id="carSearch" placeholder="Search car or plate...">
                            </div>
                        </div>

                        <!-- Per-car monthly grid -->
                        <?php foreach ($branch_cars as $car_name => $monthly_data): ?>
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
    const input = document.getElementById('carSearch');
    if (!input) return;

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
});
</script>