<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// ── Aggressive anti-cache headers ──
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

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

$pageTitle = 'Remittance Management';

// 1. Query to get Summary per car (Owner Balance vs Jerry Fees)
$summary_query = "SELECT 
            c.id, c.brand, c.model, c.plate_number,
            COALESCE(SUM(p.total_net), 0) as total_earned,
            COALESCE(SUM(p.jer_delivery_fee + p.jer_pickup_fee), 0) as total_jerry_fees,
            COALESCE(SUM(p.remitted_amount), 0) as total_paid,
            (COALESCE(SUM(p.total_net), 0) - COALESCE(SUM(p.remitted_amount), 0)) as balance_to_remit
          FROM cars c
          LEFT JOIN bookings b ON c.id = b.car_id
          LEFT JOIN booking_payments p ON b.id = p.booking_id
          GROUP BY c.id
          HAVING (balance_to_remit > 0 OR total_jerry_fees > 0)";

$summary_res = mysqli_query($conn, $summary_query);

// 2. Query to get recent remittance history logs
$history_query = "SELECT b.id as booking_id, c.brand, c.model, p.remitted_amount, p.remittance_date, p.remarks
                  FROM booking_payments p
                  JOIN bookings b ON p.booking_id = b.id
                  JOIN cars c ON b.car_id = c.id
                  WHERE p.remitted_amount > 0
                  ORDER BY p.remittance_date DESC LIMIT 10";

$history_res = mysqli_query($conn, $history_query);

// Error Checking
if (!$summary_res || !$history_res) {
    die("Database Error: " . mysqli_error($conn));
}

$summaries = [];
while ($row = mysqli_fetch_assoc($summary_res)) {
    $summaries[] = $row;
}

$history = [];
while ($row = mysqli_fetch_assoc($history_res)) {
    $history[] = $row;
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

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

    .remit-card { transition: transform 0.2s, background-color 0.25s ease; border-radius: 1rem !important; }
    .remit-card:hover { transform: translateY(-3px); }
    
    .jerry-fee-box { background: #fffbeb; border-left: 4px solid #f59e0b; }
    .owner-balance-box { background: #f0f9ff; border: 1px solid #bae6fd; }
    .extra-small { font-size: 0.7rem; }
    
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
            overflow-y: auto;
            display: block !important;
        }

        .mobile-sidebar-container.show { left: 0 !important; }

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
        
        .sidebar-backdrop.show { display: block; opacity: 1; }
    }

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

    /* DARK MODE */
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

    body.dark-mode footer p,
    body.dark-mode header span,
    body.dark-mode header p { color: #a1a1aa !important; }

    body.dark-mode .mobile-sidebar-container {
        background-color: #141414 !important;
        border-right: 1px solid #27272a !important;
    }

    body.dark-mode .text-dark,
    body.dark-mode h3,
    body.dark-mode h4,
    body.dark-mode h5,
    body.dark-mode h6,
    body.dark-mode label { color: #ffffff !important; }

    body.dark-mode .text-muted,
    body.dark-mode .text-secondary,
    body.dark-mode span:not(.badge):not(.text-success):not(.text-primary):not(.text-warning) {
        color: #cbd5e1 !important;
    }

    body.dark-mode code.text-primary,
    body.dark-mode .text-primary { color: #38bdf8 !important; }

    body.dark-mode .text-success { color: #22c55e !important; }

    body.dark-mode .bg-warning-subtle {
        background-color: #451a03 !important;
        border: 1px solid #78350f !important;
    }

    body.dark-mode .bg-warning-subtle.text-warning { color: #fde047 !important; }

    body.dark-mode .card,
    body.dark-mode .remit-card {
        background-color: #141414 !important;
        border: 1px solid #27272a !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.5) !important;
    }

    body.dark-mode .jerry-fee-box {
        background-color: #1f1a08 !important;
        border-left: 4px solid #f59e0b !important;
    }

    body.dark-mode .jerry-fee-box .text-warning-emphasis { color: #fcd34d !important; }

    body.dark-mode .owner-balance-box {
        background-color: #0c1a24 !important;
        border: 1px solid #0369a1 !important;
    }

    body.dark-mode .owner-balance-box h4,
    body.dark-mode .owner-balance-box small { color: #38bdf8 !important; }

    body.dark-mode .list-group-item {
        background-color: #141414 !important;
        border-color: #27272a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .list-group-item .text-dark { color: #ffffff !important; }
    body.dark-mode .list-group-item .border-bottom { border-color: #27272a !important; }

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

<div class="container-fluid p-0">
    <div class="row g-0">
        <div class="col-lg-2 p-0 d-none d-lg-block mobile-sidebar-container" id="sidebarWrapper">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col-12 col-lg-10 p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4 main-content-padding" style="flex: 1;">
                <div class="d-flex justify-content-between align-items-center mb-4 payout-header">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h3 class="fw-bold mb-0 text-dark">Remittance</h3>
                            <i class="bi bi-cash-stack text-success fs-4"></i>
                        </div>
                        <p class="text-muted mb-0 small">Manage payouts and staff fee settlements.</p>
                    </div>
                    
                    <div class="mt-2 mt-md-0">
                        <button class="btn btn-white shadow-sm btn-sm rounded-3 px-3 border" onclick="window.location.href='remittance.php?v=' + Date.now()">
                            <i class="bi bi-arrow-repeat me-1"></i> Refresh
                        </button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-xl-8">
                        <h6 class="fw-bold mb-3 text-secondary d-flex align-items-center">
                            <span class="bg-primary p-1 rounded me-2"></span> Pending Payouts
                        </h6>
                        <div class="row g-3">
                            <?php if (!empty($summaries)): ?>
                                <?php foreach($summaries as $row): ?>
                                <div class="col-12 col-md-6">
                                    <div class="card border-0 shadow-sm h-100 remit-card">
                                        <div class="card-body p-4">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($row['brand']) ?> <?= htmlspecialchars($row['model']) ?></h6>
                                                    <code class="text-primary small fw-bold"><?= htmlspecialchars($row['plate_number']) ?></code>
                                                </div>
                                                <span class="badge bg-warning-subtle text-warning rounded-pill px-2" style="font-size: 0.65rem;">
                                                    <i class="bi bi-clock-history me-1"></i>PENDING
                                                </span>
                                            </div>
                                            
                                            <?php if($row['total_jerry_fees'] > 0): ?>
                                            <div class="p-3 rounded-3 mb-3 jerry-fee-box">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="text-warning-emphasis fw-bold extra-small text-uppercase"> Staff Fees</span>
                                                    <span class="fw-bold text-dark">₱<?= number_format($row['total_jerry_fees'], 2) ?></span>
                                                </div>
                                                <form action="process/clear_jerry_fees.php" method="POST">
                                                    <input type="hidden" name="car_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning w-100 fw-bold py-1 shadow-sm" style="font-size: 0.7rem;" onclick="return confirm('Clear staff fees? This will mark them as paid.')">
                                                        <i class="bi bi-person-check me-1"></i> CLEAR FEE
                                                    </button>
                                                </form>
                                            </div>
                                            <?php endif; ?>

                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between small mb-1">
                                                    <span class="text-muted">Net Earned:</span>
                                                    <span class="fw-semibold text-success">₱<?= number_format($row['total_earned'], 2) ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between small">
                                                    <span class="text-muted">Previously Paid:</span>
                                                    <span class="fw-semibold">₱<?= number_format($row['total_paid'], 2) ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between small mt-1 pt-1 border-top">
                                                    <span class="text-muted fw-bold">Balance to Remit:</span>
                                                    <span class="fw-bold text-primary">₱<?= number_format($row['balance_to_remit'], 2) ?></span>
                                                </div>
                                            </div>

                                            <div class="p-3 rounded-3 text-center owner-balance-box">
                                                <small class="text-primary fw-bold text-uppercase d-block mb-1" style="font-size: 0.65rem; letter-spacing: 1px;">Amount to Pay</small>
                                                <h4 class="fw-bold text-primary mb-0">₱<?= number_format($row['balance_to_remit'], 2) ?></h4>
                                            </div>

                                            <button class="btn btn-warning-action btn-primary w-100 mt-3 py-2 rounded-3 shadow-sm fw-bold" 
                                                    onclick="openRemitForm(<?= $row['id'] ?>, '<?= addslashes($row['brand'] . ' ' . $row['model']) ?>', <?= $row['balance_to_remit'] ?>)">
                                                <i class="bi bi-wallet2 me-2"></i>Process Payout
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="card border-0 shadow-sm p-5 text-center rounded-4">
                                        <i class="bi bi-shield-check text-dark display-1 mb-3"></i>
                                        <h5 class="text-secondary">Accounts Settled</h5>
                                        <p class="text-muted small">All owners and fees have been paid in full.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-12 col-xl-4 history-column">
                        <h6 class="fw-bold mb-3 text-secondary d-flex align-items-center">
                            <span class="bg-dark p-1 rounded me-2"></span> Remittance History
                        </h6>
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                            <div class="list-group list-group-flush">
                                <?php if(!empty($history)): ?>
                                    <?php foreach($history as $h): ?>
                                        <div class="list-group-item p-3 border-0 border-bottom">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <span class="fw-bold text-dark small"><?= htmlspecialchars($h['brand']) ?> <?= htmlspecialchars($h['model']) ?></span>
                                                    <div class="text-muted" style="font-size: 0.6rem;">BK-<?= $h['booking_id'] ?></div>
                                                </div>
                                                <span class="badge bg-success text-white">+ ₱<?= number_format($h['remitted_amount'], 2) ?></span>
                                            </div>
                                            <p class="extra-small text-muted mb-2"><?= htmlspecialchars($h['remarks'] ?? 'Standard Remittance') ?></p>
                                            <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.65rem;">
                                                <span><i class="bi bi-calendar3 me-1"></i><?= date('M d, Y h:i A', strtotime($h['remittance_date'])) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="p-4 text-center text-muted small italic">
                                        <i class="bi bi-inbox fs-2 d-block mb-2 opacity-100"></i>
                                        No remittance history yet.
                                    </div>
                                <?php endif; ?>
                            </div>
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

<!-- Modal Form -->
<div class="modal fade" id="remitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process/save_remit_action.php" method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header p-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2 text-warning"></i>Process Owner Payout</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="car_id" id="modal_car_id">
                
                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">Vehicle</label>
                    <div class="modal-vehicle-box bg-light rounded-3 p-2 px-3">
                        <span id="modal_car_name" class="fw-bold"></span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">Payout Amount (₱)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-success text-white border-0">₱</span>
                        <input type="number" name="amount" id="modal_amount" class="form-control fw-bold text-success" step="0.01" required>
                    </div>
                    <small class="text-muted">Enter the amount to pay to the owner</small>
                </div>

                <div class="mb-0">
                    <label class="form-label small fw-bold text-muted text-uppercase">Payment Remarks</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="e.g. Paid via GCash / Bank Transfer / Cash"></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-2">
                <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-submit-action btn-warning px-4 flex-grow-1 py-2 fw-bold shadow-sm rounded-3">
                    <i class="bi bi-check-circle me-2"></i>Confirm Payout
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRemitForm(id, name, amount) {
    document.getElementById('modal_car_id').value = id;
    document.getElementById('modal_car_name').innerHTML = name;
    document.getElementById('modal_amount').value = amount;
    
    var myModal = new bootstrap.Modal(document.getElementById('remitModal'));
    myModal.show();
}

// Fixed Layout Offcanvas Tracking Engine
document.addEventListener("DOMContentLoaded", function () {
    const headerElement = document.querySelector('.main-content header, .main-content nav, .container-fluid');
    let toggleBtn = null;
    
    if (headerElement) {
        const buttons = headerElement.getElementsByTagName('button');
        for (let btn of buttons) {
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