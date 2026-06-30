<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth Check - JS Redirect as requested
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied.";
    echo '<script>window.location.href = "../../index.php";</script>';
    exit();
}

$pageTitle = 'Add Booking Payments';

// FETCH COMPLETED BOOKINGS AND INJECT NEWLY ADAPTED TIER STRUCTURES FROM CARS STASH
$query = "SELECT b.id, b.booking_type, b.start_date, b.end_date, b.total_price, b.extension_price, b.extension_hours,
                 c.brand, c.model, c.plate_number, 
                 c.price_10_hours, c.operator_10_hours,
                 c.price_12_hours, c.operator_12_hours,
                 c.price_24_hours, c.operator_24_hours,
                 u.name as member_name, b.guest_name
          FROM bookings b
          JOIN cars c ON b.car_id = c.id
          LEFT JOIN users u ON b.user_id = u.id
          LEFT JOIN booking_payments p ON b.id = p.booking_id
          WHERE b.status = 'Completed' AND p.id IS NULL
          ORDER BY b.end_date DESC";

$res = mysqli_query($conn, $query);

$pending = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) { 
        $pending[] = $row; 
    }
} else {
    die("Database Query Failed: " . mysqli_error($conn));
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    /* ==========================================================================
       1. GLOBAL & CONTAINER LAYOUT ADJUSTMENTS
       ========================================================================== */
    .main-content { 
        background: #f8fafc; 
        min-height: 100vh; 
    }

    .extra-small { 
        font-size: 0.72rem; 
    }

    .total-box {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-radius: 12px;
        padding: 16px 20px;
        color: white;
    }

    /* Base Styling for Custom Cards */
    .settlement-card {
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        padding:5px;
        border: 1px solid gray;
        overflow: hidden;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .card-row-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.88rem;
    }

    .card-row-item:last-of-type {
        border-bottom: none;
    }

    /* ==========================================================================
       2. WIDESCREEN & DESKTOP STYLES (Min-width: 1200px)
       ========================================================================== */
    @media (min-width: 1200px) {
        .mobile-settlement-cards { 
            display: none !important; 
        }
        
        #settlementTable th { 
            font-size: 0.75rem; 
            letter-spacing: 0.5px; 
        }
    }

    /* ==========================================================================
       3. TABLET & MOBILE OVERHAUL BREAKPOINT (Max-width: 1199.98px)
       ========================================================================== */
    @media (max-width: 1199.98px) {
        .desktop-table-container { 
            display: none !important; 
        }
        
        .mobile-settlement-cards {
            display: flex !important;
        }

        .dataTables_wrapper .row {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            margin-bottom: 1rem !important;
        }

        .dataTables_length, 
        .dataTables_filter {
            text-align: left !important;
            float: none !important;
            width: 100% !important;
            margin: 4px 0 !important;
        }

        .dataTables_filter input {
            width: 100% !important;
            margin-left: 0 !important;
            display: block !important;
            margin-top: 6px !important;
        }

        .dataTables_info, 
        .dataTables_paginate {
            text-align: center !important;
            float: none !important;
            width: 100% !important;
            margin-top: 12px !important;
        }
        
        .dataTables_paginate .pagination {
            justify-content: center !important;
            margin-top: 8px !important;
        }
    }

    /* ==========================================================================
       4. CONTEXTUAL LAYOUT COMPRESSION (Max-width: 576px)
       ========================================================================== */
    @media (max-width: 576px) {
        .modal-body { 
            padding: 1.25rem !important;
        }
        
        .form-control { 
            font-size: 1rem; 
            padding: 0.75rem; 
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

            <div class="p-3 p-md-4" style="flex: 1;">
                <div class="row align-items-center mb-4 g-3">
                    <div class="col-12 col-md-8">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h3 class="fw-bold mb-0">Pending Settlements</h3>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3">Action Required</span>
                        </div>
                        <p class="text-muted mb-0 small">Enter expenses for completed trips to update analytics.</p>
                    </div>
                </div>

                <?php if(empty($pending)): ?>
                    <div class="card border-0 shadow-sm p-5 text-center rounded-4 bg-white">
                        <div class="py-4">
                            <i class="bi bi-check2-circle text-success display-4 mb-2"></i>
                            <h5 class="text-dark fw-bold">All Settled!</h5>
                            <p class="text-muted mb-0">No pending bookings require financial entry.</p>
                        </div>
                    </div>
                <?php else: ?>

                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden desktop-table-container">
                        <div class="table-responsive">
                            <table id="settlementTable" class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4 py-3 small text-muted">TRIP DATE</th>
                                        <th class="py-3 small text-muted">CUSTOMER</th>
                                        <th class="py-3 small text-muted">VEHICLE</th>
                                        <th class="py-3 small text-muted text-center">TYPE</th>
                                        <th class="text-end pe-4 py-3 small text-muted">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($pending as $b): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold"><?= date('M d, Y', strtotime($b['end_date'])) ?></div>
                                            <small class="text-muted">Finished Trip</small>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= $b['guest_name'] ?: $b['member_name'] ?></div>
                                        </td>
                                        <td>
                                            <span class="text-dark fw-bold"><?= $b['brand'] ?> <?= $b['model'] ?></span>
                                            <div class="extra-small text-muted"><?= $b['plate_number'] ?></div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge rounded-pill bg-<?= $b['booking_type'] == 'manual' ? 'info' : 'primary' ?> px-3">
                                                <?= ucfirst($b['booking_type']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <button class="btn btn-dark btn-sm rounded-3 shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#payModal<?= $b['id'] ?>">
                                                <i class="bi bi-plus-circle me-1"></i> Enter Fees
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mobile-settlement-cards row row-cols-1 row-cols-sm-2 g-3">
                        <?php foreach($pending as $b): ?>
                            <div class="col">
                                <div class="settlement-card">
                                    <div class="bg-light px-3 py-2.5 d-flex justify-content-between align-items-center border-bottom">
                                        <div>
                                            <span class="extra-small text-muted text-uppercase fw-bold tracking-wider">Trip Ended</span>
                                            <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?= date('M d, Y', strtotime($b['end_date'])) ?></div>
                                        </div>
                                        <span class="badge rounded-pill bg-<?= $b['booking_type'] == 'manual' ? 'info' : 'primary' ?> px-3">
                                            <?= ucfirst($b['booking_type']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="card-row-item">
                                        <span class="text-muted small">Customer</span>
                                        <span class="fw-semibold text-dark"><?= $b['guest_name'] ?: $b['member_name'] ?></span>
                                    </div>
                                    
                                    <div class="card-row-item">
                                        <span class="text-muted small">Vehicle</span>
                                        <div class="text-end">
                                            <span class="fw-bold text-dark d-block"><?= $b['brand'] ?> <?= $b['model'] ?></span>
                                            <span class="extra-small text-muted bg-light px-2 py-0.5 rounded border"><?= $b['plate_number'] ?></span>
                                        </div>
                                    </div>

                                    <div class="p-3 bg-white border-top border-light">
                                        <button class="btn btn-dark w-100 py-2 rounded-3 shadow-sm font-semibold text-sm d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#payModal<?= $b['id'] ?>">
                                            <i class="bi bi-plus-circle"></i> Enter Settlement Fees
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>
            </div>

            <div class="mt-auto">
                <?php require_once __DIR__ . '/../components/footer.php'; ?>
            </div>
        </div>
    </div>
</div>

<?php foreach ($pending as $b): ?>
<div class="modal fade" id="payModal<?= $b['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="process/save_payment.php" method="POST" class="modal-content border-0 shadow-lg rounded-4" id="paymentForm<?= $b['id'] ?>">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fs-6">Financial Settlement: <?= $b['brand'] ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                
                <div class="alert alert-primary py-3 border-0 rounded-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small"><i class="bi bi-info-circle me-2"></i>Base Rental Amount:</span>
                        <span class="fw-bold">₱<?= number_format($b['total_price'], 2) ?></span>
                    </div>
                    <?php if($b['extension_hours'] > 0): ?>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="small"><i class="bi bi-clock-history me-2"></i>Extension (<?= $b['extension_hours'] ?> hrs):</span>
                        <span class="fw-bold text-warning">₱<?= number_format($b['extension_price'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                        <span class="small fw-bold"><i class="bi bi-calculator me-2"></i>Total Amount:</span>
                        <span class="fw-bold text-success">₱<?= number_format($b['total_price'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="row g-3">
                    <div class="col-12"><h6 class="fw-bold text-uppercase extra-small text-muted mb-0">Revenue Details</h6><hr class="my-1"></div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-bold">Daily Rent</label>
                        <input type="number" name="daily_rent" class="form-control calc-field" value="<?= $b['total_price'] - ($b['extension_price'] ?? 0) ?>" step="0.01">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-bold">Extension Fees</label>
                        <input type="number" name="extension_fee" class="form-control calc-field" value="<?= $b['extension_price'] ?? 0 ?>" step="0.01">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold text-info">Agent Fees</label>
                        <input type="number" name="agent_fee" class="form-control calc-field" value="0">
                    </div>

                    <div class="col-12 mt-4"><h6 class="fw-bold text-uppercase extra-small text-muted mb-0">Logistics & Fees</h6><hr class="my-1"></div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-bold text-primary">Delivery Fees</label>
                        <input type="number" name="delivery_fee" class="form-control calc-field" value="0">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-bold text-primary">Staff Delivery Fees</label>
                        <input type="number" name="jer_delivery_fee" class="form-control calc-field" value="0">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-bold text-danger">Pickup Fees</label>
                        <input type="number" name="pickup_fee" class="form-control calc-field" value="0">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-bold text-danger">Staff Pick up</label>
                        <input type="number" name="jer_pickup_fee" class="form-control calc-field" value="0">
                    </div>

                    <div class="col-12 mt-4"><h6 class="fw-bold text-uppercase extra-small text-muted mb-0">Expenses & Deductions</h6><hr class="my-1"></div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-bold">Carwash Fees</label>
                        <input type="number" name="carwash" class="form-control calc-field" value="0">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-bold text-warning">Fuel Fees</label>
                        <input type="number" name="fuel" class="form-control calc-field" value="0">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold text-danger">Damage Fees</label>
                        <input type="number" name="damage_fee" class="form-control calc-field" value="0">
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold">Driver Fees</label>
                        <input type="number" name="driver_fee" class="form-control calc-field" value="0">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold text-muted">Other Fees</label>
                        <input type="number" name="others" class="form-control calc-field" value="0">
                    </div>
                </div>

                <div class="total-box mt-4" id="totalBox<?= $b['id'] ?>">
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-white">Gross Revenue:</span>
                                <span class="fw-bold" id="grossTotal<?= $b['id'] ?>">₱0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-white">Less: Staff Delivery Fee:</span>
                                <span class="fw-bold text-danger" id="jerDeliveryDisplay<?= $b['id'] ?>">₱0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-white">Less: Staff Pickup Fee:</span>
                                <span class="fw-bold text-danger" id="jerPickupDisplay<?= $b['id'] ?>">₱0.00</span>
                            </div>
                            <hr class="my-2 opacity-25">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">NET TOTAL:</span>
                                <span class="fw-bold fs-5" id="netTotal<?= $b['id'] ?>">₱0.00</span>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="total_gross" id="totalGrossInput<?= $b['id'] ?>" value="0">
                    <input type="hidden" name="total_net" id="totalNetInput<?= $b['id'] ?>" value="0">
                </div>
            </div>
            <div class="modal-footer bg-light border-0 rounded-bottom-4">
                <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save_payment" class="btn btn-success px-4 fw-bold rounded-3">
                    Confirm & Save
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Isolated real-time calculations scoped down cleanly per structural node block
(function() {
    const bookingId = <?= $b['id'] ?>;
    const form = document.getElementById('paymentForm' + bookingId);
    if (!form) return;
    
    function calculateTotals() {
        const dailyRent = parseFloat(form.querySelector('[name="daily_rent"]')?.value) || 0;
        const extensionFee = parseFloat(form.querySelector('[name="extension_fee"]')?.value) || 0;
        const agentFee = parseFloat(form.querySelector('[name="agent_fee"]')?.value) || 0;
        const deliveryFee = parseFloat(form.querySelector('[name="delivery_fee"]')?.value) || 0;
        const jerDelivery = parseFloat(form.querySelector('[name="jer_delivery_fee"]')?.value) || 0;
        const pickupFee = parseFloat(form.querySelector('[name="pickup_fee"]')?.value) || 0;
        const jerPickup = parseFloat(form.querySelector('[name="jer_pickup_fee"]')?.value) || 0;
        const carwash = parseFloat(form.querySelector('[name="carwash"]')?.value) || 0;
        const fuel = parseFloat(form.querySelector('[name="fuel"]')?.value) || 0;
        const damageFee = parseFloat(form.querySelector('[name="damage_fee"]')?.value) || 0;
        const driverFee = parseFloat(form.querySelector('[name="driver_fee"]')?.value) || 0;
        const others = parseFloat(form.querySelector('[name="others"]')?.value) || 0;
        
        // Calculate Gross Total (all revenue streams)
        const grossTotal = dailyRent + extensionFee + agentFee + deliveryFee + pickupFee + carwash + fuel + damageFee + driverFee + others;
        
        // Calculate NET Total (Gross minus absolute asset logistics overrides)
        const netTotal = grossTotal - jerDelivery - jerPickup;
        
        // Update elements
        document.getElementById('grossTotal' + bookingId).innerHTML = '₱' + grossTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('jerDeliveryDisplay' + bookingId).innerHTML = '₱' + jerDelivery.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('jerPickupDisplay' + bookingId).innerHTML = '₱' + jerPickup.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('netTotal' + bookingId).innerHTML = '₱' + netTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        
        // Push raw structural strings to hidden inputs
        document.getElementById('totalGrossInput' + bookingId).value = grossTotal.toFixed(2);
        document.getElementById('totalNetInput' + bookingId).value = netTotal.toFixed(2);
    }
    
    const inputs = form.querySelectorAll('input.calc-field');
    inputs.forEach(input => {
        input.addEventListener('input', calculateTotals);
        input.addEventListener('change', calculateTotals);
    });
    
    // Fallback invocation loop setup
    calculateTotals();
})();
</script>
<?php endforeach; ?>

<?php if (!empty($pending)): ?>
    <?php initDataTable('settlementTable'); ?>
<?php endif; ?>