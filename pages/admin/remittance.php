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

// Error Checking for both queries
if (!$summary_res || !$history_res) {
    die("Database Error: " . mysqli_error($conn));
}

// Fetch Summary Data into array FIRST
$summaries = [];
while ($row = mysqli_fetch_assoc($summary_res)) {
    $summaries[] = $row;
}

// Fetch History Data into array
$history = [];
while ($row = mysqli_fetch_assoc($history_res)) {
    $history[] = $row;
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    .remit-card { transition: transform 0.2s; border-radius: 1rem !important; }
    .remit-card:hover { transform: translateY(-3px); }
    .jerry-fee-box { background: #fffbeb; border-left: 4px solid #f59e0b; }
    .owner-balance-box { background: #f0f9ff; border: 1px solid #bae6fd; }
    
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
            overflow-y: auto;
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
</style>

<div id="sidebarBackdrop" class="sidebar-backdrop"></div>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-2 p-0 d-none d-lg-block mobile-sidebar-container" id="sidebarWrapper">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col-12 col-lg-10 p-0 d-flex flex-column main-content" style="background: #f8fafc; min-height: 100vh;">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4 main-content-padding">
                <div class="d-flex justify-content-between align-items-center mb-4 payout-header">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h3 class="fw-bold mb-0">Remittance</h3>
                            <i class="bi bi-cash-stack text-success fs-4"></i>
                        </div>
                        <p class="text-muted mb-0 small">Manage payouts and delivery fee settlements.</p>
                    </div>
                    
                    <div class="mt-2 mt-md-0">
                        <button class="btn btn-white shadow-sm btn-sm rounded-3 px-3 border" onclick="location.reload()">
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
                                                    <span class="text-warning-emphasis fw-bold extra-small text-uppercase">Jerry Delivery Fees</span>
                                                    <span class="fw-bold text-dark">₱<?= number_format($row['total_jerry_fees'], 2) ?></span>
                                                </div>
                                                <form action="process/clear_jerry_fees.php" method="POST">
                                                    <input type="hidden" name="car_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning w-100 fw-bold py-1 shadow-sm" style="font-size: 0.7rem;" onclick="return confirm('Clear Jerry\'s delivery fees? This will mark them as paid.')">
                                                        <i class="bi bi-person-check me-1"></i> CLEAR JERRY FEE
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

                                            <button class="btn btn-primary w-100 mt-3 py-2 rounded-3 shadow-sm fw-bold" 
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

<div class="modal fade" id="remitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process/save_remit_action.php" method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2 text-warning"></i>Process Owner Payout</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="car_id" id="modal_car_id">
                
                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">Vehicle</label>
                    <div class="bg-light rounded-3 p-2 px-3">
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
            <div class="modal-footer bg-light border-0 p-4 pt-2">
                <button type="button" class="btn btn-secondary px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success px-4 flex-grow-1 py-2 fw-bold shadow-sm rounded-3">
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
    // Looks for any button layout wrapping the yellow hamburger design in your header module
    const headerElement = document.querySelector('.main-content header, .main-content nav, .container-fluid');
    let toggleBtn = null;
    
    if (headerElement) {
        // Find the button containing the icon line markers or located near the title text workspace
        const buttons = headerElement.getElementsByTagName('button');
        for (let btn of buttons) {
            if (btn.querySelector('.bi-list') || btn.innerHTML.includes('<span') || btn.className.includes('navbar-toggler')) {
                toggleBtn = btn;
                break;
            }
        }
    }
    
    // Fallback tracker if header structure search has zero hits
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