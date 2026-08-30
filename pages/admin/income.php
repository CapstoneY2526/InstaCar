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

// Initialize the data array for all 12 months
$monthly_data = array_fill(1, 12, ['gross' => 0, 'net' => 0, 'expense' => 0]);

// 1. Fetch Monthly GROSS Income (total_gross)
$gross_query = "SELECT 
                    MONTH(created_at) as month_num, 
                    COALESCE(SUM(total_gross), 0) as monthly_gross 
                FROM booking_payments 
                WHERE YEAR(created_at) = ? 
                GROUP BY MONTH(created_at)";

$stmt_gross = mysqli_prepare($conn, $gross_query);
if ($stmt_gross) {
    mysqli_stmt_bind_param($stmt_gross, "i", $current_year);
    mysqli_stmt_execute($stmt_gross);
    $gross_res = mysqli_stmt_get_result($stmt_gross);
    
    while ($row = mysqli_fetch_assoc($gross_res)) {
        $monthly_data[$row['month_num']]['gross'] = $row['monthly_gross'];
    }
    mysqli_stmt_close($stmt_gross);
}

// 2. Fetch Monthly NET Income (total_net) - This is what the house actually earns
$net_query = "SELECT 
                MONTH(created_at) as month_num, 
                COALESCE(SUM(total_net), 0) as monthly_net 
              FROM booking_payments 
              WHERE YEAR(created_at) = ? 
              GROUP BY MONTH(created_at)";

$stmt_net = mysqli_prepare($conn, $net_query);
if ($stmt_net) {
    mysqli_stmt_bind_param($stmt_net, "i", $current_year);
    mysqli_stmt_execute($stmt_net);
    $net_res = mysqli_stmt_get_result($stmt_net);
    
    while ($row = mysqli_fetch_assoc($net_res)) {
        $monthly_data[$row['month_num']]['net'] = $row['monthly_net'];
    }
    mysqli_stmt_close($stmt_net);
}

// 3. Fetch Monthly Expenses
$exp_query = "SELECT 
                MONTH(expense_date) as month_num, 
                COALESCE(SUM(amount), 0) as monthly_expense 
              FROM expenses 
              WHERE YEAR(expense_date) = ? 
              GROUP BY MONTH(expense_date)";

$stmt_exp = mysqli_prepare($conn, $exp_query);
if ($stmt_exp) {
    mysqli_stmt_bind_param($stmt_exp, "i", $current_year);
    mysqli_stmt_execute($stmt_exp);
    $exp_res = mysqli_stmt_get_result($stmt_exp);

    while ($row = mysqli_fetch_assoc($exp_res)) {
        $monthly_data[$row['month_num']]['expense'] = $row['monthly_expense'];
    }
    mysqli_stmt_close($stmt_exp);
}

$months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
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

    .net-profit-cell { width: 150px; }
    .income-statement-table tr td { padding: 1rem 0.75rem; }

    /* Standard Primary Action Button Styling */
    .btn-warning-action, .btn-submit-action {
        background-color: #ffcc00 !important;
        border-color: #ffcc00 !important;
        color: #000000 !important;
        font-weight: 700 !important;
    }
    .btn-warning-action:hover, .btn-submit-action:hover {
        background-color: #e6b800 !important;
        border-color: #e6b800 !important;
        color: #000000 !important;
    }

    /* Professional Print Styles */
    /* Professional Clean Print / PDF Export Styles */
@media print {
    @page { 
        size: A4 portrait; 
        margin: 12mm 15mm; 
    }

    body { 
        background: #ffffff !important; 
        color: #000000 !important; 
        -webkit-print-color-adjust: exact !important; 
        print-color-adjust: exact !important; 
    }

    /* Hide non-printable navigation & controls */
    .col-md-2, 
    .btn, 
    footer, 
    .bi, 
    .sidebar-backdrop, 
    #sidebarWrapper,
    header, 
    .navbar,
    .alert { 
        display: none !important; 
    }

    /* Layout Reset */
    .container-fluid, .row, .col-12, .col-lg-10, .main-content {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
    }

    /* Keep KPI Summary Cards Horizontal (3 Columns) */
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

    .card { 
        border: 1px solid #e2e8f0 !important; 
        box-shadow: none !important; 
        border-radius: 8px !important;
        background-color: #ffffff !important;
        page-break-inside: avoid;
    }

    /* Target specific KPI backgrounds for print color fidelity */
    .card.bg-success {
        background-color: #15803d !important;
        color: #ffffff !important;
    }

    .card.bg-warning {
        background-color: #b45309 !important;
        color: #ffffff !important;
    }

    /* Table Print Cleanups */
    .table-responsive {
        overflow: visible !important;
    }

    .income-statement-table {
        width: 100% !important;
        border-collapse: collapse !important;
    }

    .income-statement-table th, 
    .income-statement-table td {
        border-bottom: 1px solid #e2e8f0 !important;
        padding: 8px 12px !important;
        font-size: 0.85rem !important;
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

    .income-statement-table tfoot .text-info { color: #38bdf8 !important; }
    .income-statement-table tfoot .text-warning { color: #fde047 !important; }
    .income-statement-table tfoot .text-success { color: #4ade80 !important; }
}

    /* Responsive Mobile Sidebar & Layout Architecture Extensions */
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

    body.dark-mode .text-muted,
    body.dark-mode .text-secondary,
    body.dark-mode span:not(.badge):not(.text-success):not(.text-primary):not(.text-warning):not(.text-info):not(.text-danger) {
        color: #cbd5e1 !important;
    }

    body.dark-mode .text-primary {
        color: #38bdf8 !important;
    }

    body.dark-mode .text-info {
        color: #38bdf8 !important;
    }

    body.dark-mode .text-success {
        color: #22c55e !important;
    }

    body.dark-mode .text-danger {
        color: #f87171 !important;
    }

    body.dark-mode .text-warning {
        color: #fde047 !important;
    }

    /* Cards, Alert & Container Surfaces in Dark Mode */
    body.dark-mode .card,
    body.dark-mode .alert-light {
        background-color: #141414 !important;
        border-color: #27272a !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.5) !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .card.bg-white {
        background-color: #141414 !important;
    }

    /* Dynamic Efficiency Margin Box Dark Adjustments */
    body.dark-mode .bg-success {
        background-color: #15803d !important;
    }

    body.dark-mode .bg-warning {
        background-color: #b45309 !important;
    }

    /* Table Component Dark Overrides */
    body.dark-mode .income-statement-table,
    body.dark-mode .income-statement-table tr,
    body.dark-mode .income-statement-table td,
    body.dark-mode .income-statement-table th {
        background-color: #141414 !important;
        color: #f1f5f9 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .income-statement-table thead,
    body.dark-mode .income-statement-table thead tr,
    body.dark-mode .income-statement-table thead th,
    body.dark-mode .income-statement-table .bg-light {
        background-color: #1f1f23 !important;
        color: #94a3b8 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .income-statement-table tbody tr:hover {
        background-color: #1a1a1e !important;
    }

    body.dark-mode .income-statement-table tfoot,
    body.dark-mode .income-statement-table tfoot tr,
    body.dark-mode .income-statement-table tfoot td,
    body.dark-mode .income-statement-table .bg-dark {
        background-color: #18181b !important;
        color: #ffffff !important;
        border-color: #27272a !important;
    }

    /* Buttons in Dark Mode */
    body.dark-mode .btn-dark {
        background-color: #27272a !important;
        border-color: #3f3f46 !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .btn-dark:hover {
        background-color: #3f3f46 !important;
        color: #ffffff !important;
    }

    body.dark-mode .btn-success {
        background-color: #166534 !important;
        border-color: #15803d !important;
        color: #ffffff !important;
    }

    body.dark-mode .btn-success:hover {
        background-color: #15803d !important;
        border-color: #16a34a !important;
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
                        <h3 class="fw-bold mb-0 text-dark">Financial Statement</h3>
                        <p class="text-muted mb-0 small">Annual performance report for <?= $current_year ?></p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-dark px-4 py-2 rounded-3 shadow-sm flex-grow-1 flex-md-grow-0" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Export PDF
                        </button>

                        <a href="process/export_excel.php" class="btn btn-success px-4 py-2 rounded-3 shadow-sm flex-grow-1 flex-md-grow-0">
                            <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                        </a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <?php 
                        $total_gross = 0; 
                        $total_net = 0; 
                        $total_exp = 0;
                        foreach ($monthly_data as $data) {
                            $total_gross += $data['gross'];
                            $total_net += $data['net'];
                            $total_exp += $data['expense'];
                        }
                        // Net profit after expenses (using total_net from booking_payments)
                        $net_profit = $total_net - $total_exp;
                        $efficiency = ($total_gross > 0) ? ($net_profit / $total_gross) * 100 : 0;
                    ?>
                    <div class="col-12 col-md-4">
                        <div class="card border-0 shadow-sm p-3 rounded-4 bg-white">
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Total Annual Gross</small>
                            <h4 class="fw-bold text-dark mb-0">₱<?= number_format($total_gross, 2) ?></h4>
                            <small class="text-muted">Total customer payments</small>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="card border-0 shadow-sm p-3 rounded-4 bg-white">
                            <small class="text-muted fw-bold text-uppercase" style="font-size: 0.65rem;">Total Net Revenue</small>
                            <h4 class="fw-bold text-primary mb-0">₱<?= number_format($total_net, 2) ?></h4>
                            <small class="text-muted">After logistics deductions</small>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="card border-0 shadow-sm p-3 rounded-4 <?= $efficiency > 30 ? 'bg-success' : 'bg-warning' ?> text-white">
                            <small class="text-uppercase fw-bold" style="font-size: 0.65rem; opacity: 0.85;">Net Profit Margin</small>
                            <h4 class="fw-bold mb-0"><?= round($efficiency, 1) ?>%</h4>
                            <small class="small" style="opacity: 0.85;">Net Profit / Gross Revenue</small>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
                    <div class="table-responsive">
                        <table class="table table-hover income-statement-table align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase small fw-bold text-muted">Month</th>
                                    <th class="py-3 text-uppercase small fw-bold text-muted text-end">Gross Revenue</th>
                                    <th class="py-3 text-uppercase small fw-bold text-muted text-end">Net Revenue</th>
                                    <th class="py-3 text-uppercase small fw-bold text-muted text-end">Expenses</th>
                                    <th class="pe-4 py-3 text-uppercase small fw-bold text-muted text-end net-profit-cell">Net Profit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_data as $m_num => $data): 
                                    $net = $data['net'] - $data['expense'];
                                    // Only show months that have passed or have data
                                    if ($m_num <= date('n') || $data['gross'] > 0):
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?= $months[$m_num-1] ?></td>
                                    <td class="text-end">₱<?= number_format($data['gross'], 2) ?></td>
                                    <td class="text-end text-primary">₱<?= number_format($data['net'], 2) ?></td>
                                    <td class="text-end text-danger">₱<?= number_format($data['expense'], 2) ?></td>
                                    <td class="pe-4 text-end">
                                        <span class="fw-bold <?= $net >= 0 ? 'text-success' : 'text-danger' ?>">
                                            ₱<?= number_format($net, 2) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                            <tfoot class="bg-dark text-white fw-bold">
                                <tr>
                                    <td class="ps-4 py-3">GRAND TOTAL</td>
                                    <td class="py-3 text-end">₱<?= number_format($total_gross, 2) ?></td>
                                    <td class="py-3 text-end text-info">₱<?= number_format($total_net, 2) ?></td>
                                    <td class="py-3 text-end text-warning">₱<?= number_format($total_exp, 2) ?></td>
                                    <td class="pe-4 py-3 text-end text-success" style="font-size: 1.1rem;">₱<?= number_format($net_profit, 2) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <div class="alert alert-light border-0 shadow-sm rounded-4 p-3 mb-0">
                    <div class="d-flex gap-3 align-items-center text-muted">
                        <i class="bi bi-info-circle fs-5 flex-shrink-0 text-primary"></i>
                        <small>
                            <strong class="text-dark">Financial Breakdown:</strong><br>
                            • <strong>Gross Revenue</strong> = Total collected from customers<br>
                            • <strong>Net Revenue</strong> = What the house receives after standard transactional logistics cuts<br>
                            • <strong>Expenses</strong> = Operational costs (carwash, fuel, damage, etc.)<br>
                            • <strong>Net Profit</strong> = Final profit after all deductions
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
// Mobile Sidebar Active Target Capture Control Script Engine
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