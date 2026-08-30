<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    $_SESSION['error'] = "Access denied.";
    ?>
    <script>window.location.href = "../../index.php";</script>
    <?php
    exit();
}

$user_id = $_SESSION['user_id'];
$pageTitle = 'My Bookings';

// --- FETCH BOOKING METRICS FOR THE STAT BOXES ---
// 1. Total Bookings (All time)
$total_query = mysqli_query($conn, "SELECT COUNT(id) as total FROM bookings WHERE user_id = '$user_id'");
$total_bookings = mysqli_fetch_assoc($total_query)['total'] ?? 0;

// 2. Active Rentals (Currently Approved or Ongoing)
$active_query = mysqli_query($conn, "SELECT COUNT(id) as total FROM bookings WHERE user_id = '$user_id' AND status IN ('Approved', 'Confirmed', 'Ongoing')");
$active_rentals = mysqli_fetch_assoc($active_query)['total'] ?? 0;

// 3. Total Spent
$spent_query = mysqli_query($conn, "SELECT SUM(total_price) as total FROM bookings WHERE user_id = '$user_id' AND status = 'Completed'");
$total_spent = mysqli_fetch_assoc($spent_query)['total'] ?? 0;

// --- UNIFIED SEARCH, FILTER, CAR & REVIEW FETCH LOGIC ---
$bookings = [];
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$filter_status = mysqli_real_escape_string($conn, $_GET['status'] ?? 'All');

$query_sql = "SELECT 
                b.*, 
                c.brand, 
                c.model, 
                c.plate_number,
                r.rating, 
                r.review_title, 
                r.review_text, 
                r.admin_reply
              FROM bookings b 
              JOIN cars c ON b.car_id = c.id 
              LEFT JOIN reviews r ON b.id = r.booking_id AND r.user_id = '$user_id'
              WHERE b.user_id = '$user_id'";

if (!empty($search)) {
    $query_sql .= " AND (c.brand LIKE '%$search%' OR c.model LIKE '%$search%' OR b.id LIKE '%$search%')";
}

if ($filter_status !== 'All') {
    if ($filter_status === 'Approved') {
        $query_sql .= " AND b.status IN ('Approved', 'Confirmed', 'Ongoing')";
    } else {
        $query_sql .= " AND b.status = '$filter_status'";
    }
}

$query_sql .= " ORDER BY b.id DESC";

$result = mysqli_query($conn, $query_sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $bookings[] = $row;
    }
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    /* CSS Variables for Dynamic Theme Support (Yellow Theme Accent) */
    :root {
        --bg-main: #f8fafc;
        --bg-card: #ffffff;
        --bg-alt: #f1f5f9;
        --text-primary: #0f172a;
        --text-secondary: #64748b;
        --border-color: #e2e8f0;
        --dropdown-shadow: rgba(0, 0, 0, 0.15);
        --modal-bg: #ffffff;
        --modal-text: #334155;

        /* Core Yellow Palette */
        --yellow-primary: #eab308;
        --yellow-hover: #ca8a04;
        --yellow-light: #facc15;
        --yellow-dim: rgba(234, 179, 8, 0.15);
        --text-on-yellow: #000000;
        --brand-ink: #0f172a;
        --brand-yellow: #facc15;

        /* Dark Mode Text Readability Enhancements */
        --subtext-contrast: #94a3b8;
    }

    /* Dark Mode Variable Overrides */
    [data-bs-theme="dark"], body.dark-mode {
        --bg-main: #0a0a0a;
        --bg-card: #141414;
        --bg-alt: #1f1f23;
        --text-primary: #f8fafc;
        --text-secondary: #cbd5e1;
        --subtext-contrast: #94a3b8;
        --border-color: #27272a;
        --dropdown-shadow: rgba(0, 0, 0, 0.5);
        --modal-bg: #141414;
        --modal-text: #cbd5e1;
    }

    body { 
        background-color: var(--bg-main); 
        color: var(--text-primary);
        font-family: 'Poppins', sans-serif;
        transition: background-color 0.25s ease, color 0.25s ease;
    }

    .main-content { 
        min-height: 100vh;
        background-color: var(--bg-main);
        transition: background-color 0.25s ease;
    }

    /* ========================================================
       HEADER & FOOTER DARK MODE FIXES
    ======================================================== */
    [data-bs-theme="dark"] header,
    body.dark-mode header,
    [data-bs-theme="dark"] navbar,
    body.dark-mode navbar,
    [data-bs-theme="dark"] .navbar,
    body.dark-mode .navbar,
    [data-bs-theme="dark"] footer,
    body.dark-mode footer {
        background-color: var(--bg-card) !important;
        border-color: var(--border-color) !important;
        color: var(--text-primary) !important;
    }

    [data-bs-theme="dark"] header *,
    body.dark-mode header *,
    [data-bs-theme="dark"] footer *,
    body.dark-mode footer * {
        border-color: var(--border-color);
    }

    [data-bs-theme="dark"] .bg-white,
    body.dark-mode .bg-white,
    [data-bs-theme="dark"] .bg-light,
    body.dark-mode .bg-light {
        background-color: var(--bg-card) !important;
        color: var(--text-primary) !important;
    }

    [data-bs-theme="dark"] .dropdown-menu,
    body.dark-mode .dropdown-menu {
        background-color: var(--bg-card) !important;
        border-color: var(--border-color) !important;
        color: var(--text-primary) !important;
        box-shadow: 0 10px 25px var(--dropdown-shadow) !important;
    }

    [data-bs-theme="dark"] .dropdown-item,
    body.dark-mode .dropdown-item {
        color: var(--text-primary) !important;
    }

    [data-bs-theme="dark"] .dropdown-item:hover,
    body.dark-mode .dropdown-item:hover {
        background-color: var(--bg-alt) !important;
        color: var(--yellow-light) !important;
    }
    
    /* ========================================================
       UNIFIED DASHBOARD STATS CARD ENGINE
    ======================================================== */
    .stat-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 1.25rem 1.5rem;
        transition: all 0.2s ease, background-color 0.25s ease, border-color 0.25s ease;
        border: 1px solid var(--border-color);
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        border-color: var(--yellow-primary);
        box-shadow: 0 8px 20px rgba(234, 179, 8, 0.12);
    }
    .stat-icon {
        width: 52px;
        height: 52px;
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
        color: var(--text-primary);
    }
    @media (min-width: 576px) {
        .stat-value {
            font-size: 1.65rem;
        }
    }
    .stat-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--text-secondary);
        font-weight: 600;
        margin-top: 3px;
    }
    .stat-subtext {
        font-size: 0.75rem;
        display: block;
        margin-top: 2px;
    }

    .card {
        background-color: var(--bg-card);
        border-color: var(--border-color) !important;
        transition: background-color 0.25s ease, border-color 0.25s ease;
    }

    .table {
        color: var(--text-primary);
    }
    .table thead th {
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: 0.75rem;
        background-color: var(--bg-alt);
        color: var(--text-secondary);
    }
    .table-hover > tbody > tr:hover > * {
        background-color: var(--bg-alt);
        color: var(--text-primary);
    }
    .table > :not(caption) > * > * {
        border-bottom-color: var(--border-color);
    }

    /* COMPLETE STATUS BADGE STYLING */
    .status-badge {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
        text-align: center;
    }
    .status-pending { background: #fef3c7; color: #d97706; }
    .status-approved, 
    .status-confirmed, 
    .status-ongoing { background: #e0f2fe; color: #0369a1; }
    .status-completed { background: #d1fae5; color: #059669; }
    .status-cancelled { background: #fee2e2; color: #dc2626; }

    /* Dark Mode Status Badges */
    body.dark-mode .status-pending { background: rgba(217, 119, 6, 0.2); color: #f59e0b; }
    body.dark-mode .status-approved,
    body.dark-mode .status-confirmed,
    body.dark-mode .status-ongoing { background: rgba(3, 105, 161, 0.25); color: #38bdf8; }
    body.dark-mode .status-completed { background: rgba(5, 150, 105, 0.25); color: #34d399; }
    body.dark-mode .status-cancelled { background: rgba(220, 38, 38, 0.25); color: #f87171; }

    /* Pricing Data Colors */
    .pricing-total {
        color: var(--brand-ink);
    }
    .pricing-balance {
        color: #d97706;
    }

    body.dark-mode .pricing-total {
        color: #ffffff !important;
    }
    body.dark-mode .pricing-balance {
        color: var(--brand-yellow) !important;
    }

    /* Fulfillment Timeline Engine & Contrast Enhancements */
    .fulfillment-timeline {
        position: relative;
        padding-left: 20px;
    }
    .fulfillment-timeline::before {
        content: '';
        position: absolute;
        left: 7px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background-color: var(--border-color);
    }
    .fulfillment-item {
        position: relative;
        margin-bottom: 16px;
    }
    .fulfillment-item:last-child {
        margin-bottom: 0;
    }
    .fulfillment-marker {
        position: absolute;
        left: -20px;
        top: 4px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: var(--border-color);
    }
    .fulfillment-item.completed .fulfillment-marker {
        background-color: #10b981;
    }
    .fulfillment-item.active .fulfillment-marker {
        background-color: var(--yellow-primary);
        box-shadow: 0 0 0 3px var(--yellow-dim);
    }
    /* Cancelled Stage Styling */
    .fulfillment-item.cancelled .fulfillment-marker {
        background-color: #ef4444;
        box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
    }
    .fulfillment-item.cancelled .fulfillment-title {
        color: #ef4444;
    }

    /* Timeline Text High-Contrast Styles for Dark Mode */
    .fulfillment-item .fulfillment-title {
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--text-primary);
    }
    .fulfillment-item .fulfillment-desc {
        font-size: 0.78rem;
        color: var(--text-secondary);
        margin-top: 1px;
    }

    body.dark-mode .fulfillment-item .fulfillment-desc {
        color: var(--subtext-contrast) !important;
    }

    body.dark-mode #modal_fulfillment_booking_id {
        color: #ffffff !important;
    }

    /* Yellow theme accents */
    .main-content .text-primary {
        color: var(--yellow-hover) !important;
    }
    body.dark-mode .main-content .text-primary {
        color: var(--yellow-light) !important;
    }

    .badge.bg-primary {
        background-color: var(--yellow-primary) !important;
        color: var(--text-on-yellow) !important;
    }

    .border-primary {
        border-color: var(--yellow-primary) !important;
    }

    .btn-primary {
        background-color: var(--yellow-primary) !important;
        border-color: var(--yellow-primary) !important;
        color: var(--text-on-yellow) !important;
        font-weight: 600;
    }
    .btn-primary:hover,
    .btn-primary:focus {
        background-color: var(--yellow-hover) !important;
        border-color: var(--yellow-hover) !important;
        color: var(--text-on-yellow) !important;
    }

    .btn-outline-primary {
        color: var(--yellow-hover) !important;
        border-color: var(--yellow-primary) !important;
        background-color: transparent !important;
        transition: all 0.2s ease;
    }
    .btn-outline-primary:hover,
    .btn-outline-primary:focus {
        background-color: var(--yellow-primary) !important;
        border-color: var(--yellow-primary) !important;
        color: var(--text-on-yellow) !important;
        transform: translateY(-1px);
    }

    .btn-light,
    .input-group-text {
        background-color: var(--bg-card) !important;
        border-color: var(--border-color) !important;
        color: var(--text-primary) !important;
        transition: background-color 0.25s ease, border-color 0.25s ease, color 0.25s ease;
    }
    .btn-light:hover {
        border-color: var(--yellow-primary) !important;
    }

    .form-control,
    .form-select {
        background-color: var(--bg-card) !important;
        border-color: var(--border-color) !important;
        color: var(--text-primary) !important;
        transition: background-color 0.25s ease, border-color 0.25s ease, color 0.25s ease;
    }
    .form-control:focus,
    .form-select:focus {
        border-color: var(--yellow-primary) !important;
        box-shadow: 0 0 0 3px var(--yellow-dim) !important;
    }
    .form-control::placeholder {
        color: var(--text-secondary);
        opacity: 0.8;
    }

    .main-content .text-dark { color: var(--text-primary) !important; }
    .main-content .text-muted, 
    .main-content .text-secondary { color: var(--text-secondary) !important; }

    code {
        background-color: var(--bg-alt);
        color: var(--yellow-hover);
        border-radius: 6px;
        padding: 2px 6px;
    }
    body.dark-mode code {
        color: var(--yellow-light);
    }

    /* Modals adapt to theme */
    .modal-content {
        background-color: var(--modal-bg) !important;
        color: var(--text-primary) !important;
        border: 1px solid var(--border-color);
    }
    .modal-header.bg-light,
    .modal-footer.bg-light {
        background-color: var(--bg-alt) !important;
        border-color: var(--border-color) !important;
    }

    body.dark-mode .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%) !important;
    }

    #admin_reply_wrapper {
        background-color: var(--yellow-dim) !important;
        border-color: var(--yellow-primary) !important;
    }

    /* Table Reset for Dark Mode */
    body.dark-mode .table {
        --bs-table-bg: transparent !important;
        color: var(--text-primary) !important;
    }
    body.dark-mode .table > :not(caption) > * > * {
        background-color: transparent !important;
        color: var(--text-primary) !important;
        border-bottom-color: var(--border-color) !important;
        box-shadow: none !important;
    }
    body.dark-mode .table thead th {
        background-color: var(--bg-alt) !important;
        color: var(--yellow-light) !important;
        border-bottom-color: var(--border-color) !important;
    }
    body.dark-mode .table-hover > tbody > tr:hover > * {
        background-color: var(--bg-alt) !important;
        color: #ffffff !important;
    }

    /* Mobile Responsive Elements */
    .mobile-card-container { display: none; }

    .responsive-mobile-card {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 16px;
        margin-bottom: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        border: 1px solid var(--border-color);
        transition: background-color 0.25s ease, border-color 0.25s ease;
        overflow: hidden;
    }

    .responsive-mobile-card .mobile-vehicle-row {
        background-color: var(--bg-alt);
        border-top: 1px solid var(--border-color);
        border-bottom: 1px solid var(--border-color);
        padding: 10px 16px;
        margin: 12px -16px;
    }

    @media (max-width: 768px) {
        body { font-size: 0.85rem; }
        h3 { font-size: 1.25rem; }
        .p-4 { padding: 0.85rem !important; }
        .desktop-table-card { display: none !important; }
        .mobile-card-container { display: block; }
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-auto p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-4">
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold mb-0">My <span style="color: #ffcc00 !important;">Bookings</span></h3>
                        <p class="text-muted mb-0">Manage and track your reservation history</p>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-calendar-check-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= $total_bookings ?></div>
                                    <div class="stat-label">Total Bookings</div>
                                    <small class="text-muted stat-subtext">Reservations placed</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-key-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= $active_rentals ?></div>
                                    <div class="stat-label">Active Rentals</div>
                                    <small class="text-success stat-subtext">Approved / Out on road</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-wallet2"></i>
                                </div>
                                <div>
                                    <div class="stat-value">₱<?= number_format($total_spent, 0) ?></div>
                                    <div class="stat-label">Total Spent</div>
                                    <small class="text-muted stat-subtext">Completed trips</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
                    <form method="GET" action="" class="row g-2">
                        <div class="col-12 col-sm-6 col-md-5">
                            <div class="input-group">
                                <span class="input-group-text border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search vehicle brand, model..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-6 col-sm-3 col-md-3">
                            <select name="status" class="form-select">
                                <option value="All" <?= $filter_status == 'All' ? 'selected' : '' ?>>All Statuses</option>
                                <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Approved" <?= $filter_status == 'Approved' ? 'selected' : '' ?>>Approved / Active</option>
                                <option value="Completed" <?= $filter_status == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="Cancelled" <?= $filter_status == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-6 col-sm-3 col-md-2">
                            <button type="submit" class="btn btn-primary w-100 fw-semibold">Filter</button>
                        </div>
                        <?php if(!empty($_GET['search']) || isset($_GET['status'])): ?>
                            <div class="col-12 col-md-2">
                                <a href="mybookings.php" class="btn btn-light border w-100 text-secondary">Clear</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- DESKTOP TABLE VIEW -->
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden desktop-table-card mb-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="border-0 px-4 py-3">Booking info</th>
                                        <th class="border-0 py-3">Vehicle details</th>
                                        <th class="border-0 py-3">Rental window</th>
                                        <th class="border-0 py-3">Financial Details</th>
                                        <th class="border-0 py-3 text-center">Fulfillment Tracking</th>
                                        <th class="border-0 py-3 text-center">Status</th>
                                        <th class="border-0 py-3 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($bookings)): ?>
                                        <?php foreach ($bookings as $b): ?>
                                            <tr>
                                                <td class="px-4 py-3 align-middle">
                                                    <span class="fw-bold text-dark">#BK-<?= $b['id'] ?></span>
                                                    <div class="text-muted extra-small" style="font-size:0.75rem;">Placed: <?= date('M d, Y', strtotime($b['created_at'])) ?></div>
                                                </td>
                                                <td class="py-3 align-middle">
                                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($b['brand'] . ' ' . $b['model']) ?></div>
                                                    <div class="text-muted small">Plate: <code><?= htmlspecialchars($b['plate_number']) ?></code></div>
                                                </td>
                                                <td class="py-3 align-middle">
                                                    <div class="small fw-medium text-secondary"><?= date('M d', strtotime($b['start_date'])) ?> - <?= date('M d', strtotime($b['end_date'])) ?></div>
                                                    <div class="text-muted extra-small" style="font-size:0.72rem;"><i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($b['pickup_time'])) ?></div>
                                                </td>
                                                
                                                <!-- Financial Details -->
                                                <td class="py-3 text-start align-middle">
                                                    <div class="fw-bold pricing-total">
                                                        ₱<?= number_format($b['total_price'], 2) ?>
                                                    </div>
                                                    <?php if (($b['down_payment'] ?? 0) > 0): ?>
                                                        <div class="text-success small" style="font-size: 11px;">
                                                            DP: ₱<?= number_format($b['down_payment'], 2) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="fw-bold small pricing-balance" style="font-size: 11px;">
                                                        Bal: ₱<?= number_format(max(0, $b['total_price'] - ($b['down_payment'] ?? 0)), 2) ?>
                                                    </div>
                                                </td>

                                                <!-- Fulfillment Tracking Button -->
                                                <td class="py-3 align-middle text-center">
                                                    <button type="button" 
                                                            class="btn btn-sm btn-light border rounded-3 px-2 py-1 fulfillment-btn"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#fulfillmentModal"
                                                            data-booking-id="<?= $b['id'] ?>"
                                                            data-fulfillment-status="<?= htmlspecialchars($b['fulfillment_status'] ?? 'pending', ENT_QUOTES) ?>"
                                                            data-status="<?= htmlspecialchars($b['status'], ENT_QUOTES) ?>">
                                                        <i class="bi bi-truck me-1"></i> Track Stage
                                                    </button>
                                                </td>

                                                <td class="py-3 align-middle text-center">
                                                    <span class="status-badge status-<?= strtolower($b['status']) ?>">
                                                        <?= $b['status'] ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 align-middle text-center">
                                                    <?php if ($b['status'] === 'Completed'): ?>
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-primary rounded-3 px-3 edit-review-btn"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#reviewModal"
                                                                data-booking-id="<?= $b['id'] ?>"
                                                                data-rating="<?= htmlspecialchars($b['rating'] ?? '5', ENT_QUOTES) ?>"
                                                                data-title="<?= htmlspecialchars($b['review_title'] ?? '', ENT_QUOTES) ?>"
                                                                data-text="<?= htmlspecialchars($b['review_text'] ?? '', ENT_QUOTES) ?>"
                                                                data-admin-reply="<?= htmlspecialchars($b['admin_reply'] ?? '', ENT_QUOTES) ?>">
                                                            <i class="bi bi-star-fill me-1"></i>
                                                            <?= !empty($b['rating']) ? 'Edit Review' : 'Rate Experience' ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted small">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">No reservations match your criteria.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- MOBILE CARD VIEW -->
                <div class="mobile-card-container mb-4">
                    <?php if (!empty($bookings)): ?>
                        <?php foreach ($bookings as $b): ?>
                            <div class="responsive-mobile-card shadow-sm">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">#BK-<?= $b['id'] ?></div>
                                        <small class="text-muted">Placed: <?= date('M d, Y', strtotime($b['created_at'])) ?></small>
                                    </div>
                                    <span class="status-badge status-<?= strtolower($b['status']) ?>">
                                        <?= $b['status'] ?>
                                    </span>
                                </div>
                                
                                <div class="mobile-vehicle-row">
                                    <div class="small fw-semibold text-dark"><i class="bi bi-car-front me-2 text-secondary"></i><?= htmlspecialchars($b['brand'] . ' ' . $b['model']) ?></div>
                                    <div class="text-muted extra-small" style="font-size: 0.75rem; padding-left: 22px;">Plate: <code><?= htmlspecialchars($b['plate_number']) ?></code></div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="text-muted small">
                                        <i class="bi bi-calendar3 me-1"></i> <?= date('M d', strtotime($b['start_date'])) ?> - <?= date('M d', strtotime($b['end_date'])) ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold pricing-total">₱<?= number_format($b['total_price'], 2) ?></div>
                                        <div class="fw-bold small pricing-balance" style="font-size: 10px;">
                                            Bal: ₱<?= number_format(max(0, $b['total_price'] - ($b['down_payment'] ?? 0)), 2) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                    <button type="button" 
                                            class="btn btn-sm btn-light border rounded-3 px-2 py-1 fulfillment-btn"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#fulfillmentModal"
                                            data-booking-id="<?= $b['id'] ?>"
                                            data-fulfillment-status="<?= htmlspecialchars($b['fulfillment_status'] ?? 'pending', ENT_QUOTES) ?>"
                                            data-status="<?= htmlspecialchars($b['status'], ENT_QUOTES) ?>">
                                        <i class="bi bi-truck me-1"></i> Track Stage
                                    </button>

                                    <?php if ($b['status'] === 'Completed'): ?>
                                        <button class="btn btn-sm btn-outline-primary rounded-3 px-3 edit-review-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#reviewModal"
                                                data-booking-id="<?= $b['id'] ?>"
                                                data-rating="<?= htmlspecialchars($b['rating'] ?? '5', ENT_QUOTES) ?>"
                                                data-title="<?= htmlspecialchars($b['review_title'] ?? '', ENT_QUOTES) ?>"
                                                data-text="<?= htmlspecialchars($b['review_text'] ?? '', ENT_QUOTES) ?>"
                                                data-admin-reply="<?= htmlspecialchars($b['admin_reply'] ?? '', ENT_QUOTES) ?>">
                                            <i class="bi bi-star-fill me-1"></i> <?= !empty($b['rating']) ? 'Edit Review' : 'Rate' ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="responsive-mobile-card text-center text-muted py-4">No reservations match your criteria.</div>
                    <?php endif; ?>
                </div>

            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>

<!-- FULFILLMENT STATUS MODAL -->
<div class="modal fade" id="fulfillmentModal" tabindex="-1" aria-labelledby="fulfillmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 bg-light rounded-top-4 py-3">
                <h5 class="modal-title fw-bold" id="fulfillmentModalLabel">
                    <i class="bi bi-truck text-primary me-2"></i>Fulfillment Progress
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-3">Booking ID: <strong id="modal_fulfillment_booking_id" class="text-dark"></strong></p>
                
                <!-- STAGES TIMELINE (INCLUDES CANCELLED) -->
                <div class="fulfillment-timeline">
                    <div class="fulfillment-item" id="stage_pending">
                        <div class="fulfillment-marker"></div>
                        <div class="fulfillment-title">Reservation Submitted</div>
                        <div class="fulfillment-desc">Booking details received and awaiting confirmation</div>
                    </div>
                    <div class="fulfillment-item" id="stage_active">
                        <div class="fulfillment-marker"></div>
                        <div class="fulfillment-title">Confirmed & Out on Road</div>
                        <div class="fulfillment-desc">Vehicle prepared, assigned, and currently active or dispatched</div>
                    </div>
                    <div class="fulfillment-item" id="stage_completed">
                        <div class="fulfillment-marker"></div>
                        <div class="fulfillment-title">Trip Completed</div>
                        <div class="fulfillment-desc">Vehicle returned safely and reservation closed</div>
                    </div>
                    <!-- CANCELLED STAGE ITEM -->
                    <div class="fulfillment-item d-none" id="stage_cancelled">
                        <div class="fulfillment-marker"></div>
                        <div class="fulfillment-title">Booking Cancelled</div>
                        <div class="fulfillment-desc">This reservation was cancelled and will not be processed further.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light rounded-bottom-4 py-2">
                <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- REVIEW MODAL -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            
            <div class="modal-header border-0 bg-light rounded-top-4 py-3">
                <h5 class="modal-title fw-bold" id="reviewModalLabel">
                    <i class="bi bi-star text-warning me-2"></i>Trip Experience Review
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="reviewForm">
                <div class="modal-body p-4">
                    <div id="modal_alert" class="alert alert-dismissible fade show d-none mb-3" role="alert"></div>
                    <input type="hidden" id="modal_booking_id" name="booking_id">

                    <div id="admin_reply_wrapper" class="mb-4 p-3 rounded-3 border" style="display: none;">
                        <div class="d-flex align-items-center mb-1 text-primary fw-bold small">
                            <i class="bi bi-reply-fill me-1 fs-6"></i> Response from Admin/Host
                        </div>
                        <div id="display_admin_reply" class="text-dark small" style="white-space: pre-line; line-height: 1.5;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="modal_rating" class="form-label small fw-bold text-secondary">Overall Experience Rating</label>
                        <select class="form-select" id="modal_rating" name="rating" required>
                            <option value="5">⭐⭐⭐⭐⭐ 5 - Exceptional Experience</option>
                            <option value="4">⭐⭐⭐⭐ 4 - Very Good</option>
                            <option value="3">⭐⭐⭐ 3 - Fair / Average</option>
                            <option value="2">⭐⭐ 2 - Poor Service</option>
                            <option value="1">⭐ 1 - Terrible / Disappointing</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="modal_review_title" class="form-label small fw-bold text-secondary">Review Summary Title</label>
                        <input type="text" class="form-control" id="modal_review_title" name="review_title" placeholder="e.g., Smooth ride, excellent car condition!" required>
                    </div>

                    <div class="mb-0">
                        <label for="modal_review_text" class="form-label small fw-bold text-secondary">Detailed Feedback Comment</label>
                        <textarea class="form-control" id="modal_review_text" name="review_text" rows="4" placeholder="Share specific details regarding vehicle hand-off, cleanliness, driving performance..." required></textarea>
                    </div>
                </div>

                <div class="modal-footer border-0 bg-light rounded-bottom-4 py-2">
                    <button type="button" class="btn btn-white border rounded-3 text-secondary small px-3 fw-semibold" data-bs-dismiss="modal">Discard</button>
                    <button type="submit" id="submit_review_btn" class="btn btn-primary rounded-3 px-4 fw-semibold small">Submit Review</button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // --- FULFILLMENT MODAL HANDLER ---
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.fulfillment-btn');
        if (!btn) return;

        const bookingId = btn.dataset.bookingId || '';
        const status = btn.dataset.status || 'Pending';
        const fulfillmentStatus = btn.dataset.fulfillmentStatus || 'pending';

        document.getElementById('modal_fulfillment_booking_id').textContent = '#BK-' + bookingId;

        const stageCancelled = document.getElementById('stage_cancelled');
        const normalStages = ['pending', 'active', 'completed'];

        // Reset all normal stages
        normalStages.forEach(stg => {
            const el = document.getElementById('stage_' + stg);
            if (el) el.classList.remove('completed', 'active');
        });

        // Check for Cancelled status
        if (status === 'Cancelled' || fulfillmentStatus === 'cancelled') {
            if (stageCancelled) {
                stageCancelled.classList.remove('d-none');
                stageCancelled.classList.add('cancelled');
            }
        } else {
            if (stageCancelled) {
                stageCancelled.classList.add('d-none');
                stageCancelled.classList.remove('cancelled');
            }

            let currentStageIdx = 0;
            if (['Approved', 'Confirmed', 'Ongoing'].includes(status)) {
                currentStageIdx = 1;
            } else if (status === 'Completed') {
                currentStageIdx = 2;
            }
            
            if (['confirmed', 'ongoing'].includes(fulfillmentStatus)) {
                currentStageIdx = Math.max(currentStageIdx, 1);
            } else if (fulfillmentStatus === 'completed') {
                currentStageIdx = 2;
            }

            normalStages.forEach((stg, idx) => {
                const el = document.getElementById('stage_' + stg);
                if (!el) return;
                if (idx < currentStageIdx) {
                    el.classList.add('completed');
                } else if (idx === currentStageIdx) {
                    el.classList.add('active');
                }
            });
        }
    });

    // --- REVIEW MODAL HANDLER ---
    let activeBtn = null;

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.edit-review-btn');
        if (!btn) return;

        activeBtn = btn;

        const alertBox = document.getElementById('modal_alert');
        if (alertBox) {
            alertBox.classList.add('d-none');
            alertBox.innerText = '';
        }

        document.getElementById('modal_booking_id').value   = btn.dataset.bookingId || '';
        document.getElementById('modal_rating').value       = btn.dataset.rating || '5';
        document.getElementById('modal_review_title').value = btn.dataset.title || '';
        document.getElementById('modal_review_text').value  = btn.dataset.text || '';

        const adminReply   = btn.dataset.adminReply || '';
        const replyWrapper = document.getElementById('admin_reply_wrapper');
        const replyDisplay = document.getElementById('display_admin_reply');

        if (replyWrapper && replyDisplay) {
            if (adminReply.trim() !== '') {
                replyDisplay.textContent = adminReply;
                replyWrapper.style.display = 'block';
            } else {
                replyDisplay.textContent = '';
                replyWrapper.style.display = 'none';
            }
        }
    });

    const form = document.getElementById('reviewForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submit_review_btn');
            const alertBox  = document.getElementById('modal_alert');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

            const formData = new FormData(this);

            fetch('process/submit_review.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alertBox.className = 'alert alert-success alert-dismissible fade show mb-3';
                    alertBox.innerText = 'Review saved successfully! Refreshing...';
                    alertBox.classList.remove('d-none');

                    if (activeBtn) {
                        activeBtn.dataset.rating = formData.get('rating');
                        activeBtn.dataset.title  = formData.get('review_title');
                        activeBtn.dataset.text   = formData.get('review_text');
                        activeBtn.innerHTML      = '<i class="bi bi-star-fill me-1"></i> Edit Review';
                    }

                    setTimeout(() => {
                        location.reload();
                    }, 1200);

                } else {
                    alertBox.className = 'alert alert-danger alert-dismissible fade show mb-3';
                    alertBox.innerText = data.message || 'Failed to save review.';
                    alertBox.classList.remove('d-none');

                    submitBtn.disabled = false;
                    submitBtn.innerText = 'Submit Review';
                }
            })
            .catch(err => {
                console.error('AJAX Error:', err);
                alertBox.className = 'alert alert-danger alert-dismissible fade show mb-3';
                alertBox.innerText = 'Server error or invalid JSON response.';
                alertBox.classList.remove('d-none');

                submitBtn.disabled = false;
                submitBtn.innerText = 'Submit Review';
            });
        });
    }
});
</script>