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

$pageTitle = 'Income Statement';
$current_year = date('Y');

// Initialize data array for 12 months
// gross       = total customer payments
// operator    = sum of operator shares (owed to operators)
// staff_agent = sum of (total_gross - total_net) = staff + agent payouts
// company_net = gross - operator - staff_agent
// expense     = company overhead from expenses table
// net_profit  = company_net - expense
$monthly_data = array_fill(1, 12, [
    'gross' => 0, 'operator' => 0, 'staff_agent' => 0,
    'company_net' => 0, 'expense' => 0, 'net_profit' => 0
]);

// 1. Gross + Operator + Staff/Agent (single query on booking_payments)
$q = "SELECT
          MONTH(created_at) AS month_num,
          COALESCE(SUM(total_gross), 0)                        AS m_gross,
          COALESCE(SUM(operator_share), 0)                     AS m_operator,
          COALESCE(SUM(total_gross - total_net), 0)            AS m_staff_agent
      FROM booking_payments
      WHERE YEAR(created_at) = ?
      GROUP BY MONTH(created_at)";

$stmt = mysqli_prepare($conn, $q);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $current_year);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $m = (int)$row['month_num'];
        $monthly_data[$m]['gross']       = (float)$row['m_gross'];
        $monthly_data[$m]['operator']    = (float)$row['m_operator'];
        $monthly_data[$m]['staff_agent'] = (float)$row['m_staff_agent'];
        $monthly_data[$m]['company_net'] = $monthly_data[$m]['gross']
                                         - $monthly_data[$m]['operator']
                                         - $monthly_data[$m]['staff_agent'];
    }
    mysqli_stmt_close($stmt);
}

// 2. Expenses
$q = "SELECT
          MONTH(expense_date) AS month_num,
          COALESCE(SUM(amount), 0) AS m_expense
      FROM expenses
      WHERE YEAR(expense_date) = ?
      GROUP BY MONTH(expense_date)";

$stmt = mysqli_prepare($conn, $q);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $current_year);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $m = (int)$row['month_num'];
        $monthly_data[$m]['expense'] = (float)$row['m_expense'];
    }
    mysqli_stmt_close($stmt);
}

// 3. Net profit per month
foreach ($monthly_data as $m => $d) {
    $monthly_data[$m]['net_profit'] = $d['company_net'] - $d['expense'];
}

$months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

// ── Year totals ──
$total_gross       = 0;
$total_operator    = 0;
$total_staff_agent = 0;
$total_company_net = 0;
$total_exp         = 0;
$total_profit      = 0;

foreach ($monthly_data as $d) {
    $total_gross       += $d['gross'];
    $total_operator    += $d['operator'];
    $total_staff_agent += $d['staff_agent'];
    $total_company_net += $d['company_net'];
    $total_exp         += $d['expense'];
    $total_profit      += $d['net_profit'];
}

$margin = ($total_gross > 0) ? ($total_profit / $total_gross) * 100 : 0;
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    body, button, input, select, textarea, .form-control, .btn, .table, .modal-content {
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

    /* ── Income table ── */
    .income-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        overflow: hidden;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .income-card:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 6px 18px -6px rgba(255, 215, 0, 0.4) !important;
    }
    .income-statement-table thead th {
        background: #f1f5f9;
        font-size: 0.65rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #334155;
        font-weight: 800;
        border-bottom: 1px solid #cbd5e1;
        padding: 0.85rem 0.75rem;
    }
    .income-statement-table tbody td {
        padding: 0.9rem 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.82rem;
        color: #0f172a;
        font-weight: 600;
    }
    .income-statement-table tbody tr:hover td {
        background-color: #fffbe6;
    }
    .income-statement-table tfoot td {
        padding: 1rem 0.75rem;
        font-weight: 800;
        font-size: 0.85rem;
        background: #0f172a;
        color: #ffffff;
        border-top: 2px solid #020617;
    }
    .net-profit-cell { width: 150px; }
    .col-num { text-align: right; white-space: nowrap; }

    /* ── Buttons ── */
    .btn-export {
        padding: 0.55rem 1.25rem;
        border-radius: 0.65rem;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.2s ease;
    }
    .btn-print {
        background: #0f172a;
        color: #ffffff;
        border: 1px solid #0f172a;
    }
    .btn-print:hover {
        background: #1e293b;
        border-color: #1e293b;
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.35);
    }
    .btn-excel {
        background: #15803d;
        color: #ffffff;
        border: 1px solid #15803d;
    }
    .btn-excel:hover {
        background: #16a34a;
        border-color: #16a34a;
        color: #ffffff;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.35);
    }

    /* ── Info panel ── */
    .info-panel {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem 1.25rem;
    }
    .info-panel strong { color: #0f172a; }
    .info-panel small { color: #475569; line-height: 1.7; }

    /* ── Print styles (unchanged behavior, updated columns) ── */
    @media print {
        @page { size: A4 landscape; margin: 10mm 12mm; }

        body {
            background: #ffffff !important;
            color: #000000 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .col-md-2,
        .btn-export,
        footer,
        .bi,
        .sidebar-backdrop,
        #sidebarWrapper,
        header,
        .navbar,
        .info-panel {
            display: none !important;
        }

        .container-fluid, .row, .col-12, .col-lg-10, .main-content {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
        }

        .row.g-3.mb-4 {
            display: flex !important;
            flex-direction: row !important;
            gap: 12px !important;
            margin-bottom: 20px !important;
        }
        .row.g-3.mb-4 > [class*="col-"] {
            flex: 1 1 0 !important;
            width: 33.333% !important;
            max-width: 33.333% !important;
        }

        .stat-card {
            border: 1px solid #e2e8f0 !important;
            box-shadow: none !important;
            border-radius: 8px !important;
            background-color: #ffffff !important;
            page-break-inside: avoid;
        }

        .income-statement-table {
            width: 100% !important;
            border-collapse: collapse !important;
        }
        .income-statement-table th,
        .income-statement-table td {
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 6px 8px !important;
            font-size: 0.75rem !important;
        }
        .income-statement-table thead th {
            background-color: #f1f5f9 !important;
            color: #334155 !important;
            font-weight: 700 !important;
        }
        .income-statement-table tfoot td {
            background-color: #0f172a !important;
            color: #ffffff !important;
            font-weight: 700 !important;
        }
    }

    /* ── Mobile sidebar ── */
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
        .mobile-sidebar-container.show { left: 0 !important; }

        .sidebar-backdrop {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background: rgba(15, 23, 42, 0.5);
            z-index: 1050;
            display: none;
            opacity: 0;
            transition: opacity 0.25s linear;
        }
        .sidebar-backdrop.show { display: block; opacity: 1; }
    }

    /* ========================================================
       DARK MODE
       ======================================================== */
    body.dark-mode,
    body.dark-mode .main-content {
        background-color: #0a0a0a !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode header,
    body.dark-mode .navbar,
    body.dark-mode footer,
    body.dark-mode .footer {
        background-color: #141414 !important;
        border-color: #27272a !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .mobile-sidebar-container {
        background-color: #141414 !important;
        border-right: 1px solid #27272a !important;
    }
    body.dark-mode .text-dark,
    body.dark-mode h3, body.dark-mode h4, body.dark-mode h5,
    body.dark-mode h6, body.dark-mode label,
    body.dark-mode .stat-value {
        color: #ffffff !important;
    }
    body.dark-mode .text-muted,
    body.dark-mode .text-secondary,
    body.dark-mode .stat-label,
    body.dark-mode .stat-sub {
        color: #cbd5e1 !important;
    }

    body.dark-mode .stat-card,
    body.dark-mode .income-card,
    body.dark-mode .info-panel {
        background-color: #141414 !important;
        border-color: #27272a !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.5) !important;
    }
    body.dark-mode .stat-card:hover,
    body.dark-mode .income-card:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 0 22px rgba(255, 215, 0, 0.4) !important;
    }

    body.dark-mode .income-statement-table,
    body.dark-mode .income-statement-table tr,
    body.dark-mode .income-statement-table td,
    body.dark-mode .income-statement-table th {
        background-color: #141414 !important;
        color: #f1f5f9 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .income-statement-table thead th {
        background-color: #1f1f23 !important;
        color: #cbd5e1 !important;
    }
    body.dark-mode .income-statement-table tbody tr:hover td {
        background-color: #1a1a1e !important;
    }
    body.dark-mode .income-statement-table tfoot td {
        background-color: #18181b !important;
        color: #ffffff !important;
    }

    body.dark-mode .btn-print {
        background-color: #27272a !important;
        border-color: #3f3f46 !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .btn-print:hover {
        background-color: #3f3f46 !important;
        border-color: #3f3f46 !important;
        color: #ffffff !important;
    }
    body.dark-mode .info-panel strong { color: #ffffff; }
    body.dark-mode .info-panel small  { color: #cbd5e1; }
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

                <!-- Header -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
                    <div>
                        <h3 class="fw-bold mb-0">Income <span style="color: #ffd700;">Statement</span></h3>
                        <p class="text-muted mb-0 small">Annual performance report for <?= $current_year ?></p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-export btn-print flex-grow-1 flex-md-grow-0" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Export PDF
                        </button>

                        <a href="process/export_excel.php" class="btn btn-export btn-excel flex-grow-1 flex-md-grow-0">
                            <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                        </a>
                    </div>
                </div>

                <!-- Stats row -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Total Gross Revenue</div>
                            <div class="stat-value">₱<?= number_format($total_gross, 2) ?></div>
                            <div class="stat-sub">All customer payments</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Company Net</div>
                            <div class="stat-value" style="color:#0369a1;">₱<?= number_format($total_company_net, 2) ?></div>
                            <div class="stat-sub">After operator + staff payouts</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="stat-card" style="<?= $margin >= 30 ? 'background:#f0fdf4;' : 'background:#fffbeb;' ?>">
                            <div class="stat-label">Net Profit Margin</div>
                            <div class="stat-value" style="color: <?= $margin >= 30 ? '#15803d' : '#b45309' ?>;">
                                <?= number_format($margin, 1) ?>%
                            </div>
                            <div class="stat-sub">Net Profit ÷ Gross</div>
                        </div>
                    </div>
                </div>

                <!-- Income table -->
                <div class="income-card mb-4">
                    <div class="table-responsive">
                        <table class="table income-statement-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4 text-start">Month</th>
                                    <th class="col-num">Gross Revenue</th>
                                    <th class="col-num">Operator Payouts</th>
                                    <th class="col-num">Staff / Agent</th>
                                    <th class="col-num">Company Net</th>
                                    <th class="col-num">Expenses</th>
                                    <th class="pe-4 col-num">Net Profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_data as $m_num => $d):
                                    if ($m_num <= date('n') || $d['gross'] > 0):
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?= $months[$m_num-1] ?></td>
                                    <td class="col-num">₱<?= number_format($d['gross'], 2) ?></td>
                                    <td class="col-num" style="color:#b45309;">
                                        <?= $d['operator'] > 0 ? '-₱' . number_format($d['operator'], 2) : '—' ?>
                                    </td>
                                    <td class="col-num" style="color:#7c3aed;">
                                        <?= $d['staff_agent'] > 0 ? '-₱' . number_format($d['staff_agent'], 2) : '—' ?>
                                    </td>
                                    <td class="col-num fw-bold" style="color:#0369a1;">
                                        ₱<?= number_format($d['company_net'], 2) ?>
                                    </td>
                                    <td class="col-num" style="color:#b91c1c;">
                                        <?= $d['expense'] > 0 ? '-₱' . number_format($d['expense'], 2) : '—' ?>
                                    </td>
                                    <td class="pe-4 col-num fw-bold" style="color: <?= $d['net_profit'] >= 0 ? '#15803d' : '#b91c1c' ?>;">
                                        ₱<?= number_format($d['net_profit'], 2) ?>
                                    </td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="ps-4">GRAND TOTAL</td>
                                    <td class="col-num">₱<?= number_format($total_gross, 2) ?></td>
                                    <td class="col-num" style="color:#fde047;">-₱<?= number_format($total_operator, 2) ?></td>
                                    <td class="col-num" style="color:#c4b5fd;">-₱<?= number_format($total_staff_agent, 2) ?></td>
                                    <td class="col-num" style="color:#7dd3fc;">₱<?= number_format($total_company_net, 2) ?></td>
                                    <td class="col-num" style="color:#fca5a5;">-₱<?= number_format($total_exp, 2) ?></td>
                                    <td class="pe-4 col-num" style="color:#4ade80; font-size: 1rem;">
                                        ₱<?= number_format($total_profit, 2) ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Legend / info panel -->
                <div class="info-panel">
                    <div class="d-flex gap-3 align-items-start">
                        <i class="bi bi-info-circle fs-5 flex-shrink-0" style="color:#0369a1;"></i>
                        <small>
                            <strong>Financial Breakdown</strong><br>
                            • <strong>Gross Revenue</strong> = everything customers paid<br>
                            • <strong>Operator Payouts</strong> = sum of operator shares (owed to operators)<br>
                            • <strong>Staff / Agent</strong> = staff delivery + staff pickup + agent fees<br>
                            • <strong>Company Net</strong> = Gross − Operator − Staff/Agent<br>
                            • <strong>Expenses</strong> = company overhead (rent, salaries, etc.)<br>
                            • <strong>Net Profit</strong> = Company Net − Expenses
                        </small>
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
// Mobile sidebar toggle
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