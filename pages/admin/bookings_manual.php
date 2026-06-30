<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied.";
    ?>
    <script>window.stop(); window.location.href = "../../index.php";</script>
    <?php
    exit();
}

if (isset($_FILES['proof_of_billing'])) {
    $fileType = $_FILES['proof_of_billing']['type'];
    $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    if (!in_array($fileType, $allowed)) {
        die("Error: Only PDFs and Images are allowed.");
    }
}

$pageTitle = 'Manual Booking';
$filter = $_GET['filter'] ?? 'All';

// Fetch Registered Customers
$customers = [];
$userRes = mysqli_query($conn, "SELECT id, name FROM users WHERE role = 'user' ORDER BY name ASC");
if ($userRes) {
    while ($row = mysqli_fetch_assoc($userRes)) {
        $customers[] = $row;
    }
}

// Get stats for manual bookings
$statsQuery = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
FROM bookings 
WHERE booking_type = 'manual'";

$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Fetch Bookings with Filter
$bookings = [];
$query = "SELECT b.*, u.name as member_name, c.brand, c.model, c.plate_number 
          FROM bookings b 
          LEFT JOIN users u ON b.user_id = u.id 
          JOIN cars c ON b.car_id = c.id 
          WHERE b.booking_type = 'manual'";

if ($filter !== 'All') {
    $safe_filter = mysqli_real_escape_string($conn, $filter);
    $query .= " AND b.status = '$safe_filter'";
}
$query .= " ORDER BY b.id DESC";

$res = mysqli_query($conn, $query);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $bookings[] = $row;
    }
}

// Fetch distinct brands for type filter
$brandTypes = [];
$typeRes = mysqli_query($conn, "SELECT DISTINCT brand FROM cars WHERE brand IS NOT NULL ORDER BY brand ASC");
if ($typeRes) {
    while ($row = mysqli_fetch_assoc($typeRes)) {
        $brandTypes[] = $row['brand'];
    }
}

// Fetch Cars with active reservation timelines packed together
$available_cars = [];
$carQuery = "
    SELECT c.*, 
           GROUP_CONCAT(CONCAT(b.start_date, ' ', b.pickup_time, '|', b.end_date, ' ', b.return_time)) AS busy_slots
    FROM cars c
    LEFT JOIN bookings b ON c.id = b.car_id AND b.status NOT IN ('Cancelled', 'Completed')
    GROUP BY c.id 
    ORDER BY c.brand ASC, c.model ASC
";
$carRes = mysqli_query($conn, $carQuery);
if ($carRes) {
    while ($row = mysqli_fetch_assoc($carRes)) {
        $available_cars[] = $row;
    }
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    /* Fixed Table overflow behavior */
    .table-responsive {
        overflow-x: visible !important; /* Changed from auto to allow overflow breakout */
        overflow-y: visible !important;
        -webkit-overflow-scrolling: touch;
    }

    .status-tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .status-tabs .nav-link {
        color: #64748b;
        border-bottom: 2px solid transparent;
    }

    .status-tabs .nav-link.active {
        color: #0d6efd;
        border-bottom: 2px solid #0d6efd;
        background: none;
    }

    .form-label.small {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
    }

    .duration-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 15px;
    }

    .duration-btn {
        flex: 1;
        min-width: 70px;
        padding: 6px 12px;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 6px;
        border: 1px solid #dee2e6;
        background: white;
        transition: all 0.2s;
        cursor: pointer;
    }

    .duration-btn:hover {
        background: #e9ecef;
        transform: translateY(-1px);
    }

    .duration-btn.active {
        background: #0d6efd;
        color: white;
        border-color: #0d6efd;
    }

    .date-time-preview {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 12px;
        font-size: 0.85rem;
        border-left: 3px solid #0d6efd;
    }

    .date-time-preview i {
        width: 20px;
        color: #6c757d;
    }

    .warning-text {
        color: #dc3545;
        font-size: 0.8rem;
        margin-top: 5px;
    }

    .info-text {
        color: #0d6efd;
        font-size: 0.75rem;
        margin-top: 5px;
    }

    /* Stats Boxes */
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 1rem 1.25rem;
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
        height: 100%;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        line-height: 1.2;
    }
    
    @media (min-width: 576px) {
        .stat-value {
            font-size: 1.75rem;
        }
    }

    .stat-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        font-weight: 600;
    }

    /* Mobile Booking Cards View Style */
    .mobile-booking-card {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        margin-bottom: 1rem;
        padding: 1rem;
    }

    /* Premium Realtime Control Container Bar UI (WINDOWS-MATCHED) */
    .custom-control-bar {
        border: 1px solid gray !important;
        padding:5px;
        border-radius: 12px !important;
        background: #ffffff !important;
    }

    /* Clean Styled Icon Search Bar Engine UI */
    .search-input-wrapper {
        position: relative;
    }
    
    .search-input-wrapper i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 14px;
        pointer-events: none;
        z-index: 5;
    }

    .search-input-wrapper input {
        width: 100% !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        padding: 8px 14px 8px 38px !important;
        font-size: 13px !important;
        height: 38px !important;
        color: #1e293b !important;
        background-color: #ffffff !important;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04) !important;
        transition: all 0.2s ease-in-out !important;
    }

    .search-input-wrapper input:focus {
        border-color: #0d6efd !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15) !important;
    }

    /* Custom Entry Limiter Form Element Selection UI */
    .entry-limiter-wrapper {
        color: #64748b;
        font-size: 13px;
        font-weight: 500;
    }

    .entry-limiter-select {
        display: inline-block;
        width: auto;
        height: 38px;
        padding: 6px 36px 6px 12px;
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        background-color: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        cursor: pointer;
        outline: none;
        transition: all 0.2s;
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23475569' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 12px 12px;
    }

    .entry-limiter-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 col-md-3 col-lg-2 p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col-12 col-md-9 col-lg-10 p-0 d-flex flex-column main-content" style="background: #f8fafc; min-height: 100vh;">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-0">Manual Bookings</h3>
                        <p class="text-muted mb-0">Manage walk-in reservations.</p>
                    </div>
                    <button type="button" class="btn btn-primary fw-bold shadow-sm w-sm-auto" data-bs-toggle="modal"
                        data-bs-target="#manualBookingModal">
                        <i class="bi bi-plus-circle me-2"></i>New Walk-in Booking
                    </button>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-lg-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-2 gap-sm-3">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-calendar-check fs-4"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($stats['total_bookings'] ?? 0) ?></div>
                                    <div class="stat-label">Total</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-2 gap-sm-3">
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-check-circle-fill fs-4"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($stats['confirmed'] ?? 0) ?></div>
                                    <div class="stat-label">Confirmed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-2 gap-sm-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-flag-fill fs-4"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($stats['completed'] ?? 0) ?></div>
                                    <div class="stat-label">Completed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-2 gap-sm-3">
                                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                                    <i class="bi bi-x-circle-fill fs-4"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($stats['cancelled'] ?? 0) ?></div>
                                    <div class="stat-label">Cancelled</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
                    <div class="card-body p-2">
                        <ul class="nav status-tabs border-0 m-0">
                            <li class="nav-item"><a class="nav-link <?= $filter == 'All' ? 'active' : '' ?>" href="?filter=All">All</a></li>
                            <li class="nav-item"><a class="nav-link <?= $filter == 'Confirmed' ? 'active' : '' ?>" href="?filter=Confirmed">Confirmed</a></li>
                            <li class="nav-item"><a class="nav-link <?= $filter == 'Completed' ? 'active' : '' ?>" href="?filter=Completed">Completed</a></li>
                            <li class="nav-item"><a class="nav-link <?= $filter == 'Cancelled' ? 'active' : '' ?>" href="?filter=Cancelled">Cancelled</a></li>
                        </ul>
                    </div>
                </div>

                <div class="card custom-control-bar shadow-sm mb-4">
                    <div class="card-body p-2 d-flex flex-row justify-content-between align-items-center gap-3">
                        
                        <div class="entry-limiter-wrapper d-flex align-items-center gap-2">
                            <span>Show</span>
                            <select id="manualEntryLimitSelect" class="entry-limiter-select">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span>entries</span>
                        </div>
                        
                        <div class="search-input-wrapper">
                            <i class="bi bi-search"></i>
                            <input type="text" id="unifiedManualSearch" placeholder="Search reservations real-time...">
                        </div>
                        
                    </div>
                </div>

                <div class="modal fade" id="manualBookingModal" tabindex="-1" data-bs-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header border-0 p-4 pb-0">
                                <h5 class="fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Create Walk-in Booking</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-3 p-md-4 pt-2">
                                <form action="process/booking_actions.php" method="POST" enctype="multipart/form-data" id="manualBookingForm">
                                    <input type="hidden" name="add_manual_booking" value="1">

                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label small fw-bold">Customer Type</label>
                                            <select id="userType" name="customer_type" class="form-select" onchange="toggleCustomerType()" required>
                                                <option value="registered">Registered Member</option>
                                                <option value="guest">Guest / Walk-in</option>
                                            </select>
                                        </div>

                                        <div id="registeredInput" class="col-12">
                                            <label class="form-label small fw-bold">Select Member</label>
                                            <select name="user_id" id="userIdSelect" class="form-select" required>
                                                <option value="">-- Select Member --</option>
                                                <?php foreach ($customers as $cus): ?>
                                                    <option value="<?= $cus['id'] ?>"><?= htmlspecialchars($cus['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div id="guestInput" class="col-12 d-none">
                                            <div class="row g-2">
                                                <div class="col-12">
                                                    <label class="form-label small fw-bold">Guest Full Name</label>
                                                    <input type="text" name="guest_name" id="guestNameInput" class="form-control guest-name-input" placeholder="Enter Full Name">
                                                </div>
                                                <div class="col-12 col-sm-6">
                                                    <label class="form-label small fw-bold">Email</label>
                                                    <input type="email" name="guest_email" id="guestEmailInput" class="form-control" placeholder="email@gmail.com">
                                                </div>
                                                <div class="col-12 col-sm-6">
                                                    <label class="form-label small fw-bold">Phone</label>
                                                    <input type="text" name="guest_phone" id="guestPhoneInput" class="form-control" placeholder="09xxxxxxxxx">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small fw-bold text-danger">Primary ID</label>
                                            <input type="file" name="primary_id" class="form-control form-control-sm" accept="image/*, .pdf" required>
                                        </div>

                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small fw-bold text-muted">Secondary ID</label>
                                            <input type="file" name="secondary_id" class="form-control form-control-sm" accept="image/*, .pdf">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-muted">Proof of Billing</label>
                                            <input type="file" name="proof_of_billing" class="form-control form-control-sm" accept="image/*, .pdf" required>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-muted">Quick Duration</label>
                                            <div class="duration-buttons">
                                                <button type="button" class="duration-btn" id="btn10h" onclick="setDuration(10, this)">10 Hours</button>
                                                <button type="button" class="duration-btn" id="btn24h" onclick="setDuration(24, this)">24 Hours</button>
                                                <button type="button" class="duration-btn active" id="btnCustom" onclick="setDuration('custom', this)">Custom</button>
                                            </div>
                                        </div>

                                        <div class="col-12 date-time-preview" id="dateTimePreview" style="display: none;">
                                            <div class="mb-2small text-muted">
                                                <i class="bi bi-calendar-plus"></i>
                                                <strong>Pickup:</strong> <span id="previewPickup">--</span>
                                            </div>
                                            <div class="small text-muted">
                                                <i class="bi bi-calendar-check"></i>
                                                <strong>Return:</strong> <span id="previewReturn">--</span>
                                            </div>
                                        </div>

                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small fw-bold">Pickup Date & Time</label>
                                            <input type="datetime-local" id="pickupDatetime" class="form-control" required onchange="syncDateTimeValues()">
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small fw-bold">Return Date & Time</label>
                                            <input type="datetime-local" id="returnDatetime" class="form-control" required onchange="syncDateTimeValues()">
                                        </div>

                                        <input type="hidden" name="start_date" id="startDate">
                                        <input type="hidden" name="pickup_time" id="pickupTime">
                                        <input type="hidden" name="end_date" id="endDate">
                                        <input type="hidden" name="return_time" id="returnTime">

                                        <div class="col-12">
                                            <label class="form-label small fw-bold text-dark">Select Car</label>
                                            <select id="typeFilter" class="form-select form-select-sm mb-2" onchange="filterCarSelectionOptions()">
                                                <option value="">All Types / Categories</option>
                                                <?php foreach ($brandTypes as $brand): ?>
                                                    <option value="<?= htmlspecialchars($brand) ?>"><?= htmlspecialchars($brand) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <select name="car_id" id="carSelect" class="form-select" onchange="calculateTieredTotal()" required>
                                                <option value="">-- Select a Car --</option>
                                                <?php if (!empty($available_cars)): ?>
                                                    <?php foreach ($available_cars as $car): ?>
                                                        <option value="<?= $car['id'] ?>" 
                                                                data-type="<?= htmlspecialchars($car['type'] ?? '') ?>"
                                                                data-busy="<?= htmlspecialchars($car['busy_slots'] ?? '') ?>"
                                                                data-price-10="<?= $car['price_10_hours'] ?? 0 ?>"
                                                                data-price-12="<?= $car['price_12_hours'] ?? 0 ?>"
                                                                data-price-24="<?= $car['price_24_hours'] ?? 0 ?>"
                                                                data-ext-1-6="<?= $car['ext_price_1_6'] ?? 0 ?>"
                                                                data-ext-7-10="<?= $car['ext_price_7_10'] ?? 0 ?>"
                                                                data-ext-11-12="<?= $car['ext_price_11_12'] ?? 0 ?>"
                                                                data-ext-13-24="<?= $car['ext_price_13_24'] ?? 0 ?>">
                                                            <?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['plate_number'] . ')') ?> 
                                                            — ₱<?= number_format($car['price_24_hours'] ?? 0, 2) ?>/24h
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>

                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small fw-bold">Discount (₱)</label>
                                            <input type="number" name="discount_price" id="discount_priceInput" class="form-control" value="0" min="0" oninput="calculateTieredTotal()">
                                        </div>

                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small fw-bold">Down Payment (₱)</label>
                                            <input type="number" name="down_payment" id="downPaymentInput" class="form-control" value="0" min="0" oninput="calculateTieredTotal()">
                                        </div>

                                        <div class="col-12">
                                            <div class="p-3 bg-light rounded border">
                                                <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
                                                    <span>Base Rental Rate:</span>
                                                    <span id="displayBasePrice">₱0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-1 small text-danger">
                                                    <span>Discount Deduction:</span>
                                                    <span id="displayDiscount">-₱0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-2 small text-success">
                                                    <span>Down Payment Paid:</span>
                                                    <span id="displayDownPayment">-₱0.00</span>
                                                </div>
                                                
                                                <hr class="my-2">
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="small fw-bold text-dark">Remaining Balance Due:</span>
                                                    <h4 class="fw-bold text-primary mb-0" id="displayTotal">₱0.00</h4>
                                                </div>
                                                
                                                <input type="hidden" name="total_price" id="totalPriceInput" value="0">
                                                <div class="small text-muted mt-2" id="durationDisplay"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="add_manual_booking" class="btn btn-primary fw-bold px-4" id="confirmBookingBtn">Confirm Booking</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bookings-display-container">
                    <div class="d-block d-md-none mobile-cards-wrapper">
                        <?php if (empty($bookings)): ?>
                            <div class="card border-0 shadow-sm p-5 text-center text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>No manual bookings found.
                            </div>
                        <?php else: ?>
                            <?php foreach ($bookings as $b): ?>
                                <div class="mobile-booking-card js-searchable-booking bg-white p-3 mb-3 rounded-3 shadow-sm border">
                                    <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                                        <div>
                                            <span class="text-uppercase tracking-wider text-muted d-block" style="font-size: 0.65rem; font-weight:700;">Customer</span>
                                            <div class="fw-bold text-dark">
                                                <?php if (!empty($b['guest_name'])): ?>
                                                    <?= htmlspecialchars($b['guest_name']) ?>
                                                    <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size:9px">WALK-IN</span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($b['member_name'] ?? 'N/A') ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($b['gmail'] ?? 'No Email') ?></div>
                                        </div>
                                        <div>
                                            <?php
                                            $statusClass = ['Confirmed' => 'bg-primary', 'Completed' => 'bg-success', 'Cancelled' => 'bg-danger'];
                                            $cls = $statusClass[$b['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?= $cls ?>"><?= $b['status'] ?></span>
                                        </div>
                                    </div>

                                    <div class="row g-2 mb-2">
                                        <div class="col-6 border-end">
                                            <span class="text-uppercase tracking-wider text-muted d-block" style="font-size: 0.65rem; font-weight:700;">Vehicle</span>
                                            <div class="fw-bold" style="font-size: 12px;"><?= htmlspecialchars($b['brand']) ?> <?= htmlspecialchars($b['model']) ?></div>
                                            <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($b['plate_number']) ?></div>
                                        </div>
                                        <div class="col-6 ps-2 d-md-none">
                                            <span class="text-uppercase tracking-wider text-muted d-block" style="font-size: 0.65rem; font-weight:700;">Pricing Data</span>
                                            
                                            <div class="fw-bold <?= $b['status'] == 'Cancelled' ? 'text-danger' : 'text-dark' ?>" style="font-size: 12px;">
                                                ₱<?= number_format($b['total_price'] + $b['discount_price'] + $b['down_payment'], 2) ?>
                                            </div>
                                            
                                            <?php if (isset($b['discount_price']) && $b['discount_price'] > 0): ?>
                                                <div class="text-danger" style="font-size: 10px; font-weight:500;">
                                                    Disc: ₱<?= number_format($b['discount_price'], 2) ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($b['down_payment'] > 0): ?>
                                                <div class="text-success" style="font-size: 10px; font-weight:500;">
                                                    DP: ₱<?= number_format($b['down_payment'], 2) ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="text-primary" style="font-size: 13px; font-weight: 800; border-top: 1px solid #dee2e6; margin-top: 5px; padding-top: 3px; line-height: 1.4;">
                                                Bal: ₱<?= number_format($b['total_price'], 2) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-light p-2 rounded-2 mb-2 border-start border-3 border-primary">
                                        <span class="text-uppercase tracking-wider text-muted d-block mb-1" style="font-size: 0.65rem; font-weight:700;">Schedule Timeline</span>
                                        <div style="font-size:11px; font-weight:500;" class="text-dark mb-1">
                                            <i class="bi bi-calendar-event me-1 text-muted"></i><?= date('M d, Y', strtotime($b['start_date'])) ?> to <?= date('M d, Y', strtotime($b['end_date'])) ?>
                                        </div>
                                        <div class="text-primary" style="font-size:11px; font-weight:500;">
                                            <i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($b['pickup_time'])) ?> - <?= date('h:i A', strtotime($b['return_time'])) ?>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                        <div class="d-flex gap-1 flex-wrap">
                                            <?php if (!empty($b['primary_id_path'])): ?>
                                                <a href="../../public/assets/images/ids/<?= $b['primary_id_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-2 py-1" style="font-size:10px;"><i class="bi bi-card-image me-1"></i>ID 1</a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($b['secondary_id_path'])): ?>
                                                <a href="../../public/assets/images/ids/<?= $b['secondary_id_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-2 py-1" style="font-size:10px;"><i class="bi bi-card-image me-1"></i>ID 2</a>
                                            <?php endif; ?>

                                            <?php if (!empty($b['proof_billing_path'])): ?>
                                                <a href="../../public/assets/images/ids/<?= $b['proof_billing_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-2 py-1" style="font-size:10px;"><i class="bi bi-card-image me-1"></i>PROOF</a>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <?php if ($b['status'] == 'Completed' || $b['status'] == 'Cancelled'): ?>
                                                <button class="btn btn-sm btn-light border text-muted" disabled style="font-size: 11px;"><i class="bi bi-lock-fill me-1"></i>Locked</button>
                                            <?php else: ?>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-light border dropdown-toggle" 
                                                            type="button" 
                                                            data-bs-toggle="dropdown" 
                                                            data-bs-boundary="viewport" 
                                                            data-bs-popper="static">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                        <li><a class="dropdown-item text-primary" href="#" data-bs-toggle="modal" data-bs-target="#editBookingModal<?= $b['id'] ?>"><i class="bi bi-pencil-square me-2"></i>Edit</a></li>
                                                        <?php if ($b['status'] == 'Confirmed'): ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li><a class="dropdown-item text-success" href="process/booking_actions.php?id=<?= $b['id'] ?>&status=Completed&source=manual&filter=<?= $filter ?>" onclick="return confirm('Mark as completed?')"><i class="bi bi-check-circle me-2"></i>Complete</a></li>
                                                        <?php endif; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="process/booking_actions.php?id=<?= $b['id'] ?>&status=Cancelled&source=manual&filter=<?= $filter ?>" onclick="return confirm('Cancel this booking?')"><i class="bi bi-x-circle me-2"></i>Cancel</a></li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-none d-md-block desktop-table-wrapper">
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table id="bookingsTable" class="table table-hover align-middle mb-0">
                                        <thead class="bg-light text-uppercase small text-secondary">
                                            <tr>
                                                <th class="ps-4">Customer</th>
                                                <th>Vehicle</th>
                                                <th>Schedule</th>
                                                <th>Verification Files</th>
                                                <th>Pricing Data</th>
                                                <th>Status</th>
                                                <th class="pe-4 text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="small">
                                            <?php if (empty($bookings)): ?>
                                                <tr><td colspan="7" class="text-center py-5 text-muted">No manual bookings found.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($bookings as $b): ?>
                                                    <tr class="js-searchable-booking">
                                                        <td class="ps-4">
                                                            <div class="fw-bold text-dark">
                                                                <?php if (!empty($b['guest_name'])): ?>
                                                                    <?= htmlspecialchars($b['guest_name']) ?><span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size:9px">WALK-IN</span>
                                                                <?php else: ?>
                                                                    <?= htmlspecialchars($b['member_name'] ?? 'N/A') ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="text-muted small"><?= htmlspecialchars($b['gmail'] ?? 'No Email') ?></div>
                                                        </td>
                                                        <td>
                                                            <div class="fw-bold"><?= htmlspecialchars($b['brand']) ?> <?= htmlspecialchars($b['model']) ?></div>
                                                            <div class="text-muted small"><?= htmlspecialchars($b['plate_number']) ?></div>
                                                        </td>
                                                        <td>
                                                            <div><i class="bi bi-calendar-event me-1 text-muted"></i><?= date('M d', strtotime($b['start_date'])) ?> - <?= date('M d', strtotime($b['end_date'])) ?></div>
                                                            <div class="text-primary small"><i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($b['pickup_time'])) ?> - <?= date('h:i A', strtotime($b['return_time'])) ?></div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex gap-1">
                                                                <?php if (!empty($b['primary_id_path'])): ?>
                                                                    <a href="../../public/assets/images/ids/<?= $b['primary_id_path'] ?>" target="_blank" class="btn btn-sm btn-outline-info px-2 py-0" style="font-size:11px">ID 1</a>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($b['secondary_id_path'])): ?>
                                                                    <a href="../../public/assets/images/ids/<?= $b['secondary_id_path'] ?>" target="_blank" class="btn btn-sm btn-outline-info px-2 py-0" style="font-size:11px">ID 2</a>
                                                                <?php endif; ?>

                                                                <?php if (!empty($b['proof_billing_path'])): ?>
                                                                    <a href="../../public/assets/images/ids/<?= $b['proof_billing_path'] ?>" target="_blank" class="btn btn-sm btn-outline-info px-2 py-0" style="font-size:11px">PROOF</a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td class="align-middle text-start d-none d-md-table-cell">
                                                            <div class="fw-bold <?= $b['status'] == 'Cancelled' ? 'text-danger' : 'text-dark' ?>" style="font-size: 13px;">
                                                                ₱<?= number_format($b['total_price'] + $b['discount_price'] + $b['down_payment'], 2) ?>
                                                            </div>
                                                            
                                                            <?php if (isset($b['discount_price']) && $b['discount_price'] > 0): ?>
                                                                <div class="text-danger" style="font-size: 11px; font-weight:500; line-height: 1.2;">
                                                                    Disc: ₱<?= number_format($b['discount_price'], 2) ?>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if ($b['down_payment'] > 0): ?>
                                                                <div class="text-success" style="font-size: 11px; font-weight:500; line-height: 1.2;">
                                                                    DP: ₱<?= number_format($b['down_payment'], 2) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <div class="text-primary" style="font-size: 13px; font-weight: 800; border-top: 1px solid #dee2e6; margin-top: 5px; padding-top: 3px; line-height: 1.4;">
                                                                Bal: ₱<?= number_format($b['total_price'], 2) ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php $cls = $statusClass[$b['status']] ?? 'bg-secondary'; ?>
                                                            <span class="badge <?= $cls ?>"><?= $b['status'] ?></span>
                                                        </td>
                                                        <td class="pe-4 text-end">
                                                            <?php if ($b['status'] == 'Completed' || $b['status'] == 'Cancelled'): ?>
                                                                <span class="text-muted small"><i class="bi bi-lock-fill"></i> Locked</span>
                                                            <?php else: ?>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-container="body" data-bs-toggle="dropdown">Actions</button>
                                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                                        <li><a class="dropdown-item text-primary" href="#" data-bs-toggle="modal" data-bs-target="#editBookingModal<?= $b['id'] ?>"><i class="bi bi-pencil-square me-2"></i>Edit</a></li>
                                                                        <?php if ($b['status'] == 'Confirmed'): ?>
                                                                            <li><hr class="dropdown-divider"></li>
                                                                            <li><a class="dropdown-item text-success" href="process/booking_actions.php?id=<?= $b['id'] ?>&status=Completed&source=manual&filter=<?= $filter ?>" onclick="return confirm('Mark as completed?')"><i class="bi bi-check-circle me-2"></i>Complete</a></li>
                                                                        <?php endif; ?>
                                                                        <li><hr class="dropdown-divider"></li>
                                                                        <li><a class="dropdown-item text-danger" href="process/booking_actions.php?id=<?= $b['id'] ?>&status=Cancelled&source=manual&filter=<?= $filter ?>" onclick="return confirm('Cancel this booking?')"><i class="bi bi-x-circle me-2"></i>Cancel</a></li>
                                                                    </ul>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>

<?php foreach ($bookings as $b): ?>
    <div class="modal fade" id="editBookingModal<?= $b['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white p-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Booking #BK-<?= $b['id'] ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="process/booking_actions.php" enctype="multipart/form-data" id="editBookingForm_<?= $b['id'] ?>">
                    <div class="modal-body p-3 p-md-4">
                        <input type="hidden" name="update_booking" value="1">
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <input type="hidden" name="source" value="manual">
                        <input type="hidden" name="filter" value="<?= $filter ?>">

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-bold">Customer Type</label>
                                <select id="userType_<?= $b['id'] ?>" name="customer_type" class="form-select" onchange="toggleEditCustomerType(<?= $b['id'] ?>)" required>
                                    <option value="registered" <?= (!empty($b['user_id'])) ? 'selected' : '' ?>>Registered Member</option>
                                    <option value="guest" <?= (empty($b['user_id'])) ? 'selected' : '' ?>>Guest / Walk-in</option>
                                </select>
                            </div>

                            <div id="registeredInput_<?= $b['id'] ?>" class="col-12 <?= (empty($b['user_id'])) ? 'd-none' : '' ?>">
                                <label class="form-label small fw-bold">Select Member</label>
                                <select name="user_id" id="userIdSelect_<?= $b['id'] ?>" class="form-select" <?= (!empty($b['user_id'])) ? 'required' : '' ?>>
                                    <option value="">-- Select Member --</option>
                                    <?php foreach ($customers as $cus): ?>
                                        <option value="<?= $cus['id'] ?>" <?= ($cus['id'] == $b['user_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cus['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div id="guestInput_<?= $b['id'] ?>" class="col-12 <?= (!empty($b['user_id'])) ? 'd-none' : '' ?>">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label small fw-bold">Guest Full Name</label>
                                        <input type="text" name="guest_name" id="guestNameInput_<?= $b['id'] ?>" class="form-control guest-name-input" value="<?= htmlspecialchars($b['guest_name'] ?? '') ?>" placeholder="Enter Full Name" <?= (empty($b['user_id'])) ? 'required' : '' ?>>
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label small fw-bold">Email</label>
                                        <input type="email" name="guest_email" id="guestEmailInput_<?= $b['id'] ?>" class="form-control" value="<?= htmlspecialchars($b['gmail'] ?? '') ?>" placeholder="email@gmail.com">
                                    </div>
                                    <div class="col-12 col-sm-6">
                                        <label class="form-label small fw-bold">Phone</label>
                                        <input type="text" name="guest_phone" id="guestPhoneInput_<?= $b['id'] ?>" class="form-control" value="<?= htmlspecialchars($b['phone_number'] ?? '') ?>" placeholder="09xxxxxxxxx">
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label small fw-bold text-muted">Primary ID (Leave blank to keep old)</label>
                                <input type="file" name="primary_id" class="form-control form-control-sm" accept="image/*, .pdf">
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label small fw-bold text-muted">Secondary ID (Leave blank to keep old)</label>
                                <input type="file" name="secondary_id" class="form-control form-control-sm" accept="image/*, .pdf">
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold text-muted">Proof of Billing (Leave blank to keep old)</label>
                                <input type="file" name="proof_of_billing" class="form-control form-control-sm" accept="image/*, .pdf">
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label small fw-bold">Pickup Date & Time</label>
                                <input type="datetime-local" name="pickup_datetime" id="pickupDatetime_<?= $b['id'] ?>" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($b['start_date'] . ' ' . $b['pickup_time'])) ?>" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label small fw-bold">Return Date & Time</label>
                                <input type="datetime-local" name="return_datetime" id="returnDatetime_<?= $b['id'] ?>" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($b['end_date'] . ' ' . $b['return_time'])) ?>" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label small fw-bold">Select Car</label>
                                <select name="car_id" id="carSelect_<?= $b['id'] ?>" class="form-select" required>
                                    <?php
                                    $cars_sql = "SELECT id, brand, model, plate_number FROM cars ORDER BY brand ASC";
                                    $cars_res = mysqli_query($conn, $cars_sql);
                                    while ($car_row = mysqli_fetch_assoc($cars_res)) {
                                        $selected = ($car_row['id'] == $b['car_id']) ? 'selected' : '';
                                        echo '<option value="' . $car_row['id'] . '" ' . $selected . '>' . htmlspecialchars($car_row['brand'] . ' ' . $car_row['model'] . ' - ' . $car_row['plate_number']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label small fw-bold">Discount (₱)</label>
                                <input type="number" name="discount_price" class="form-control" value="<?= floatval($b['discount_price']) ?>" min="0">
                            </div>

                            <div class="col-12 col-sm-6">
                                <label class="form-label small fw-bold">Down Payment (₱)</label>
                                <input type="number" name="down_payment" class="form-control" value="<?= floatval($b['down_payment']) ?>" min="0">
                            </div>

                            <div class="col-12">
                                <div class="alert alert-info p-2 small mb-0">
                                    <i class="bi bi-info-circle me-1"></i>Current Saved Total: <strong>₱<?= number_format($b['total_price'], 2) ?></strong><br>
                                    <span class="text-muted">Total will recalculate automatically using your modified date profiles on submission.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 p-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Save Changes</button>
                    </div>
            </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<script>
let currentDuration = null;
let currentSelectedDurationMode = 'custom';

function initialize() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hour = String(now.getHours()).padStart(2, '0');
    const minute = String(now.getMinutes()).padStart(2, '0');

    const defaultPickup = `${year}-${month}-${day}T${hour}:${minute}`;
    document.getElementById('pickupDatetime').value = defaultPickup;

    let returnDate = new Date(now);
    returnDate.setHours(returnDate.getHours() + 10);

    let returnYear = returnDate.getFullYear();
    let returnMonth = String(returnDate.getMonth() + 1).padStart(2, '0');
    let returnDay = String(returnDate.getDate()).padStart(2, '0');
    let returnHour = String(returnDate.getHours()).padStart(2, '0');
    let returnMinute = String(returnDate.getMinutes()).padStart(2, '0');

    document.getElementById('returnDatetime').value = `${returnYear}-${returnMonth}-${returnDay}T${returnHour}:${returnMinute}`;

    document.getElementById('pickupDatetime').setAttribute('min', defaultPickup);
    document.getElementById('returnDatetime').setAttribute('min', defaultPickup);

    currentDuration = null; 
    updateAll();
}

function setDuration(hours, element) {
    document.querySelectorAll('.duration-btn').forEach(btn => btn.classList.remove('active'));
    if (element) element.classList.add('active');
    
    currentSelectedDurationMode = hours;
    
    if (hours !== 'custom') {
        document.getElementById('returnDatetime').readOnly = true;
        applyQuickDurationCalculations();
    } else {
        document.getElementById('returnDatetime').readOnly = false;
        calculateTieredTotal();
    }
}

function updateAll() {
    updatePreview();
    updateHiddenFields();
    calculateDuration();
    filterCarSelectionOptions();
}

function updatePreview() {
    const pickupInput = document.getElementById('pickupDatetime');
    const returnInput = document.getElementById('returnDatetime');
    const previewDiv = document.getElementById('dateTimePreview');
    const previewPickup = document.getElementById('previewPickup');
    const previewReturn = document.getElementById('previewReturn');

    if (pickupInput.value && returnInput.value) {
        previewDiv.style.display = 'block';
        const pickup = new Date(pickupInput.value);
        const returnDateObj = new Date(returnInput.value);

        previewPickup.innerHTML = pickup.toLocaleString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true
        });
        previewReturn.innerHTML = returnDateObj.toLocaleString('en-US', {
            month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true
        });
    } else {
        previewDiv.style.display = 'none';
    }
}

function updateHiddenFields() {
    const pickupInput = document.getElementById('pickupDatetime');
    const returnInput = document.getElementById('returnDatetime');

    if (pickupInput.value) {
        const [pickupDate, pickupTime] = pickupInput.value.split('T');
        document.getElementById('startDate').value = pickupDate;
        document.getElementById('pickupTime').value = pickupTime;
    }
    if (returnInput.value) {
        const [returnDate, returnTime] = returnInput.value.split('T');
        document.getElementById('endDate').value = returnDate;
        document.getElementById('returnTime').value = returnTime;
    }
}

function calculateDuration() {
    const pickupInput = document.getElementById('pickupDatetime');
    const returnInput = document.getElementById('returnDatetime');
    const durationDisplay = document.getElementById('durationDisplay');
    const confirmBtn = document.getElementById('confirmBookingBtn');

    if (!pickupInput.value || !returnInput.value) {
        durationDisplay.innerHTML = '';
        confirmBtn.disabled = true;
        return;
    }

    const pickup = new Date(pickupInput.value);
    const returnDateObj = new Date(returnInput.value);
    
    const now = new Date();
    now.setSeconds(0);
    now.setMilliseconds(0);

    if (isNaN(pickup.getTime()) || isNaN(returnDateObj.getTime())) {
        durationDisplay.innerHTML = '<span class="text-danger">Invalid date/time</span>';
        confirmBtn.disabled = true;
        return;
    }

    if (pickup < now) {
        durationDisplay.innerHTML = '<span class="text-danger">⚠️ Pickup time cannot be in the past!</span>';
        confirmBtn.disabled = true;
        return;
    }

    if (returnDateObj <= pickup) {
        durationDisplay.innerHTML = '<span class="text-danger">⚠️ Return must be after pickup time!</span>';
        confirmBtn.disabled = true;
        return;
    }

    const diffMs = returnDateObj - pickup;
    const diffHours = diffMs / (1000 * 60 * 60);

    if (diffHours < 9.99) {
        durationDisplay.innerHTML = `<span class="text-danger">Duration: ${diffHours.toFixed(1)} hours (Minimum is 10 hours)</span>`;
        confirmBtn.disabled = true;
        return;
    }

    durationDisplay.innerHTML = `<span class="text-success">Duration: ${Math.ceil(diffHours)} hours ✓</span>`;
    confirmBtn.disabled = false;
}

function syncDateTimeValues() {
    updateAll();
    if (currentSelectedDurationMode !== 'custom') {
        applyQuickDurationCalculations();
    } else {
        calculateTieredTotal();
    }
}

function applyQuickDurationCalculations() {
    const pickupVal = document.getElementById('pickupDatetime').value;
    if (!pickupVal) return;
    
    let pickupDate = new Date(pickupVal);
    let targetReturnDate = new Date(pickupDate.getTime() + (parseInt(currentSelectedDurationMode) * 60 * 60 * 1000));
    
    const tzOffset = targetReturnDate.getTimezoneOffset() * 60000;
    const localISOTime = (new Date(targetReturnDate.getTime() - tzOffset)).toISOString().slice(0, 16);
    
    document.getElementById('returnDatetime').value = localISOTime;
    updateAll();
    calculateTieredTotal();
}

function filterCarSelectionOptions() {
    const pickupVal = document.getElementById('pickupDatetime').value;
    const returnVal = document.getElementById('returnDatetime').value;
    const filterValue = document.getElementById('typeFilter').value.toLowerCase();
    const carSelect = document.getElementById('carSelect');
    const options = carSelect.options;
    
    let userStart = pickupVal ? new Date(pickupVal) : null;
    let userEnd = returnVal ? new Date(returnVal) : null;

    for (let i = 0; i < options.length; i++) {
        if (options[i].value === "") continue;
        
        const optType = (options[i].getAttribute('data-type') || '').toLowerCase();
        const busySlots = options[i].getAttribute('data-busy') || '';
        let isBookedConflict = false;
        
        // Strip out existing flag appendages to recover original metadata
        let baseText = options[i].text.replace(' (NOT AVAILABLE)', '');
        
        if (userStart && userEnd && busySlots !== '') {
            const slots = busySlots.split(',');
            for (let slot of slots) {
                const times = slot.split('|'); 
                if (times.length < 2) continue;
                
                const bookStart = new Date(times[0].trim());
                const bookEnd = new Date(times[1].trim());
                
                // standard intersection constraint boundary logic
                if (userStart < bookEnd && userEnd > bookStart) {
                    isBookedConflict = true;
                    break;
                }
            }
        }
        
        const typeMatches = (filterValue === "" || optType === filterValue);
        
        if (typeMatches && !isBookedConflict) {
            options[i].style.display = "block";
            options[i].disabled = false;
            options[i].text = baseText;
        } else if (isBookedConflict) {
            options[i].style.display = "block"; 
            options[i].disabled = true; 
            options[i].text = baseText + ' (NOT AVAILABLE)';
            
            // Revert value assignments to blank if user tries selecting a locked vehicle
            if (carSelect.value === options[i].value) {
                carSelect.value = "";
                if (typeof resetPricingDisplay === "function") {
                    resetPricingDisplay();
                }
            }
        } else {
            options[i].style.display = "none";
        }
    }
}

function calculateTieredTotal() {
    const pickupVal = document.getElementById('pickupDatetime').value;
    const returnVal = document.getElementById('returnDatetime').value;
    const carSelect = document.getElementById('carSelect');
    const selectedOption = carSelect.options[carSelect.selectedIndex];
    
    if (!pickupVal || !returnVal || !selectedOption || selectedOption.value === "") {
        resetPricingDisplay();
        return;
    }
    
    const start = new Date(pickupVal);
    const end = new Date(returnVal);
    
    if (end <= start) {
        resetPricingDisplay();
        return;
    }
    
    const diffMs = end - start;
    const totalHours = Math.ceil(diffMs / (1000 * 60 * 60));
    
    const p10 = parseFloat(selectedOption.getAttribute('data-price-10')) || 0;
    const p12 = parseFloat(selectedOption.getAttribute('data-price-12')) || 0;
    const p24 = parseFloat(selectedOption.getAttribute('data-price-24')) || 0;
    
    const ext1_6 = parseFloat(selectedOption.getAttribute('data-ext-1-6')) || 0;
    const ext7_10 = parseFloat(selectedOption.getAttribute('data-ext-7-10')) || 0;
    const ext11_12 = parseFloat(selectedOption.getAttribute('data-ext-11-12')) || 0;
    const ext13_24 = parseFloat(selectedOption.getAttribute('data-ext-13-24')) || 0;
    
    let basePrice = 0;
    
    if (totalHours <= 10) {
        basePrice = p10;
    } else if (totalHours <= 12) {
        basePrice = p12;
    } else if (totalHours <= 24) {
        basePrice = p24;
    } else {
        const days = Math.floor(totalHours / 24);
        const extraHours = totalHours % 24;
        basePrice = days * p24;
        
        if (extraHours > 0) {
            if (extraHours <= 6) basePrice += (extraHours * ext1_6);
            else if (extraHours <= 10) basePrice += (extraHours * ext7_10);
            else if (extraHours <= 12) basePrice += (extraHours * ext11_12);
            else basePrice += (extraHours * ext13_24);
        }
    }
    
    const discount = parseFloat(document.getElementById('discount_priceInput').value) || 0;
    const downPayment = parseFloat(document.getElementById('downPaymentInput').value) || 0;
    const remainingBalance = Math.max(0, basePrice - discount - downPayment);
    
    document.getElementById('displayBasePrice').innerText = '₱' + basePrice.toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('displayDiscount').innerText = '-₱' + discount.toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('displayDownPayment').innerText = '-₱' + downPayment.toLocaleString('en-US', { minimumFractionDigits: 2 });
    document.getElementById('displayTotal').innerText = '₱' + remainingBalance.toLocaleString('en-US', { minimumFractionDigits: 2 });
    
    document.getElementById('totalPriceInput').value = remainingBalance.toFixed(2);
}

function resetPricingDisplay() {
    document.getElementById('displayBasePrice').innerText = '₱0.00';
    document.getElementById('displayDiscount').innerText = '-₱0.00';
    document.getElementById('displayDownPayment').innerText = '-₱0.00';
    document.getElementById('displayTotal').innerText = '₱0.00';
    document.getElementById('totalPriceInput').value = "0";
}

function toggleCustomerType() {
    const type = document.getElementById('userType').value;
    const regDiv = document.getElementById('registeredInput');
    const guestDiv = document.getElementById('guestInput');
    
    if (type === 'guest') {
        if(regDiv) regDiv.classList.add('d-none');
        if(guestDiv) guestDiv.classList.remove('d-none');
        document.getElementById('userIdSelect').removeAttribute('required');
        document.getElementById('guestNameInput').setAttribute('required', 'required');
    } else {
        if(regDiv) regDiv.classList.remove('d-none');
        if(guestDiv) guestDiv.classList.add('d-none');
        document.getElementById('userIdSelect').setAttribute('required', 'required');
        document.getElementById('guestNameInput').removeAttribute('required');
    }
}

function toggleEditCustomerType(bookingId) {
    const type = document.getElementById('userType_' + bookingId).value;
    const regDiv = document.getElementById('registeredInput_' + bookingId);
    const guestDiv = document.getElementById('guestInput_' + bookingId);
    
    const memberSelect = document.getElementById('userIdSelect_' + bookingId);
    const guestName = document.getElementById('guestNameInput_' + bookingId);

    if (type === 'guest') {
        if (regDiv) regDiv.classList.add('d-none');
        if (guestDiv) guestDiv.classList.remove('d-none');
        if (memberSelect) { memberSelect.value = ''; memberSelect.removeAttribute('required'); }
        if (guestName) guestName.setAttribute('required', 'required');
    } else {
        if (regDiv) regDiv.classList.remove('d-none');
        if (guestDiv) guestDiv.classList.add('d-none');
        if (guestName) { guestName.value = ''; guestName.removeAttribute('required'); }
        if (memberSelect) memberSelect.setAttribute('required', 'required');
    }
}

$(document).ready(function () {
    initialize();

    $('#carSelect').on('change', calculateTieredTotal);
    $('#discount_priceInput, #downPaymentInput').on('input', calculateTieredTotal);
    $('#pickupDatetime, #returnDatetime').on('change', syncDateTimeValues);
    $('#typeFilter').on('change', filterCarSelectionOptions);

    function applyLimiterAndFilter() {
        const queryValue = $('#unifiedManualSearch').val().toLowerCase().trim();
        const limitValue = parseInt($('#manualEntryLimitSelect').val(), 10) || 10;

        let desktopMatchCount = 0;
        $('#bookingsTable tbody tr.js-searchable-booking').each(function () {
            if ($(this).text().toLowerCase().includes(queryValue)) {
                desktopMatchCount++;
                $(this).css('display', desktopMatchCount <= limitValue ? '' : 'none');
            } else {
                $(this).css('display', 'none');
            }
        });

        let mobileMatchCount = 0;
        $('.mobile-booking-card.js-searchable-booking').each(function () {
            if ($(this).text().toLowerCase().includes(queryValue)) {
                mobileMatchCount++;
                $(this).css('display', mobileMatchCount <= limitValue ? 'block' : 'none');
            } else {
                $(this).css('display', 'none');
            }
        });
    }

    $('#unifiedManualSearch').on('input', applyLimiterAndFilter);
    $('#manualEntryLimitSelect').on('change', applyLimiterAndFilter);
    applyLimiterAndFilter();
});

document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file format. Please upload only images or PDF files.');
            this.value = '';
        }
    });
});
</script>