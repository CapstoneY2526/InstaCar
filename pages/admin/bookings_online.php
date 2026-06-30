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

// --- 1. THE AUTO-CANCEL CLEANER ---
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');
$now = date('H:i:s');
$auto_cancel_sql = "UPDATE bookings 
                    SET status = 'Cancelled', 
                        total_price = 500 
                    WHERE (status = 'Pending') 
                    AND booking_type = 'online'
                    AND (
                        -- If end date is in the past
                        end_date < '$today'
                        OR 
                        -- If end date is today AND return time has passed
                        (end_date = '$today' AND return_time < '$now')
                        OR
                        -- If start date is in the past (but not same day)
                        start_date < '$today'
                    )";
mysqli_query($conn, $auto_cancel_sql);

// Get filter parameter
$filter = $_GET['filter'] ?? 'All';
$pageTitle = 'Online Bookings';

// Get stats for online bookings
$statsQuery = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
FROM bookings 
WHERE booking_type = 'online'";

$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Build the query
$query = "SELECT b.*, u.name as customer_name, c.brand, c.model, c.plate_number 
          FROM bookings b 
          LEFT JOIN users u ON b.user_id = u.id 
          JOIN cars c ON b.car_id = c.id 
          WHERE b.booking_type = 'online' ";

// Dynamic Filtering
if ($filter !== 'All') {
    $safe_filter = mysqli_real_escape_string($conn, $filter);
    $query .= " AND b.status = '$safe_filter' ";
}

$query .= " ORDER BY b.id DESC";

// Execute Query
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Booking Query Failed: " . mysqli_error($conn));
}

$bookings = [];
while ($row = mysqli_fetch_assoc($result)) {
    $bookings[] = $row;
}

// Debug: Check if there are online bookings
if (empty($bookings) && $filter == 'All') {
    $check_sql = "SELECT COUNT(*) as total FROM bookings WHERE booking_type = 'online'";
    $check_result = mysqli_query($conn, $check_sql);
    $check_row = mysqli_fetch_assoc($check_result);

    $no_online_bookings = ($check_row['total'] == 0);
} else {
    $no_online_bookings = false;
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
/* =========================
   GLOBAL SAFETY LAYER
========================= */
* { box-sizing: border-box; }
body { overflow-x: hidden; }
.main-content { overflow-x: hidden; min-width: 0; }

/* =========================
   TABLE WRAPPER FIX
========================= */
.table-responsive {
    overflow-x: auto !important;
    overflow-y: visible !important;
    -webkit-overflow-scrolling: touch;
    width: 100%;
}
.table { width: 100%; }
.table th, .table td {
    white-space: nowrap;
    vertical-align: middle;
}

/* =========================
   STATUS TABS
========================= */
.status-tabs {
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    gap: 6px;
    padding: 6px;
    -webkit-overflow-scrolling: touch;
}
.status-tabs .nav-item { flex: 0 0 auto; }
.status-tabs .nav-link {
    white-space: nowrap;
    font-weight: 500;
    color: #64748b;
    border: none;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
}
.status-tabs .nav-link.active {
    color: #0d6efd;
    background: rgba(13, 110, 253, 0.08);
    border-bottom: 2px solid #0d6efd;
}

.btn-manage { background: #fff; border: 1px solid #dee2e6; font-weight: 500; }
.empty-state { text-align: center; padding: 60px 20px; }
.empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 20px; }
.empty-state h5 { color: #64748b; }
.empty-state p { color: #94a3b8; }

/* =========================
   STAT CARDS
========================= */
.row.g-3.mb-4 { flex-wrap: wrap; }

/* ========================================================
   UNIFIED DASHBOARD STATS CARD ENGINE (IMAGE_78F75E MATCHED)
======================================================== */
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

/* Premium Realtime Control Container Bar UI (WINDOWS-MATCHED) */
.custom-control-bar {
    padding:5px;
    border: 1px solid gray !important;
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

/* ========================================================
   💥 SMART RESPONSIVE DESIGN (UNCHANGED NATIVE MOBILE)
======================================================== */
@media (max-width: 768px) {
    nav, .main-header {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        z-index: 100 !important;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08) !important;
    }

    body {
        padding-top: 75px !important; 
    }

    .p-4 { padding: 12px !important; }

    .card.shadow-sm.border-0.rounded-3,
    .table-responsive {
        background: transparent !important;
        box-shadow: none !important;
        overflow-x: visible !important;
    }

    #bookingsTable, 
    #bookingsTable thead, 
    #bookingsTable tbody, 
    #bookingsTable th, 
    #bookingsTable td, 
    #bookingsTable tr { 
        display: block; 
    }

    #bookingsTable thead tr { 
        position: absolute;
        top: -9999px;
        left: -9999px;
    }

    #bookingsTable tr {
        background: #fff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 14px !important;
        margin-bottom: 16px !important;
        padding: 16px 20px !important;
    }

    #bookingsTable td { 
        border: none !important;
        padding: 0 !important;
        margin-bottom: 14px;
        position: relative;
        white-space: normal;
        text-align: left !important;
    }

    #bookingsTable td::before { 
        content: attr(data-label);
        display: block;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        color: #94a3b8;
        margin-bottom: 4px;
    }

    #bookingsTable td[data-label="Status"],
    #bookingsTable td[data-label="Actions"] {
        display: inline-block !important;
        width: 48% !important;
        margin-bottom: 0 !important;
    }
}

@media (max-width: 576px) {
    .btn-group { display: flex; flex-direction: column; gap: 4px; }
    .dropdown-menu { font-size: 12px; }



}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 col-lg-2 p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col-12 col-lg-10 p-0 d-flex flex-column main-content" style="background: #f8fafc; min-height: 100vh;">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold mb-0">Online Reservations</h3>
                        <p class="text-muted">Manage your online booking pipeline efficiently.</p>
                    </div>
                    <div class="text-muted d-none d-md-block">
                        <i class="bi bi-info-circle"></i> Total Online Bookings: <?= count($bookings) ?>
                    </div>
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
                                    <div class="stat-label">Bookings</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6 col-lg-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-2 gap-sm-3">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-hourglass-split fs-4"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($stats['pending'] ?? 0) ?></div>
                                    <div class="stat-label">Pending</div>
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
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-0">
                        <ul class="nav status-tabs">
                            <li class="nav-item"><a class="nav-link <?= $filter == 'All' ? 'active' : '' ?>" href="?filter=All">All Bookings</a></li>
                            <li class="nav-item"><a class="nav-link <?= $filter == 'Pending' ? 'active' : '' ?>" href="?filter=Pending">Pending</a></li>
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
                            <select id="onlineEntryLimitSelect" class="entry-limiter-select">
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
                            <input type="text" id="unifiedOnlineSearch" placeholder="Search reservations real-time...">
                        </div>
                        
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-body p-0">
                        <div class="table-responsive shadow-sm rounded">
                            <?php if (empty($bookings)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-calendar-x"></i>
                                    <h5>No Online Bookings Found</h5>
                                    <p>
                                        <?php if (isset($no_online_bookings) && $no_online_bookings): ?>
                                            There are no online bookings in the system yet.
                                        <?php else: ?>
                                            No <?= $filter ?> bookings found for online reservations.
                                        <?php endif; ?>
                                    </p>
                                    <a href="?filter=All" class="btn btn-primary btn-sm">View All Bookings</a>
                                </div>
                            <?php else: ?>
                                <table id="bookingsTable" class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4">Customer</th>
                                            <th>Vehicle</th>
                                            <th>Schedule</th>
                                            <th>Verification</th>
                                            <th>Total</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-end pe-4">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <?php foreach ($bookings as $b): ?>
                                            <tr class="js-searchable-booking">
                                                <td class="ps-4" data-label="Customer">
                                                    <div class="fw-bold">
                                                        <?php
                                                        if (!empty($b['customer_name'])) {
                                                            echo htmlspecialchars($b['customer_name']);
                                                        } else {
                                                            echo '<span class="text-muted">Guest User</span>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <div class="text-muted" style="font-size: 10px;">ID: #BK-<?= $b['id'] ?></div>
                                                    <?php if ($b['user_id']): ?>
                                                        <div class="text-muted" style="font-size: 9px;">User ID: <?= $b['user_id'] ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Vehicle">
                                                    <div class="fw-bold"><?= htmlspecialchars($b['brand'] . ' ' . $b['model']) ?></div>
                                                    <div class="text-muted" style="font-size: 10px;"><?= htmlspecialchars($b['plate_number']) ?></div>
                                                </td>
                                                <td data-label="Schedule">
                                                    <div class="fw-bold text-dark" style="font-size: 11px;">
                                                        <?= date('M d', strtotime($b['start_date'])) ?> - <?= date('M d', strtotime($b['end_date'])) ?>
                                                    </div>
                                                    <div class="text-primary" style="font-size: 10px;">
                                                        <i class="bi bi-clock me-1"></i><?= date('h:i A', strtotime($b['pickup_time'])) ?> - <?= date('h:i A', strtotime($b['return_time'])) ?>
                                                    </div>
                                                </td>
                                                <td data-label="Verification">
                                                    <?php if (!empty($b['primary_id_path'])): ?>
                                                        <a href="../../public/assets/images/ids/<?= $b['primary_id_path'] ?>" target="_blank" class="btn btn-xs btn-outline-info py-0 px-1" style="font-size: 10px;"><i class="bi bi-file-image"></i> View ID 1</a>
                                                    <?php else: ?>
                                                        <span class="text-muted" style="font-size: 10px;">No ID</span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($b['secondary_id_path'])): ?><a href="../../public/assets/images/ids/<?= $b['secondary_id_path'] ?>" target="_blank" class="btn btn-xs btn-outline-info py-0 px-1" style="font-size: 10px;"><i class="bi bi-file-image"></i> View ID 2</a><?php endif; ?>
                                                    <?php if (!empty($b['proof_billing_path'])): ?><a href="../../public/assets/images/ids/<?= $b['proof_billing_path'] ?>" target="_blank" class="btn btn-xs btn-outline-info py-0 px-1" style="font-size: 10px;"><i class="bi bi-file-image"></i> View PROOF</a><?php endif; ?>
                                                </td>
                                                <td class="fw-bold <?= $b['status'] == 'Cancelled' ? 'text-danger' : '' ?>" data-label="Total">
                                                    <?php if ($b['status'] == 'Cancelled'): ?>
                                                        <div class="fw-bold text-danger">₱<?= number_format($b['total_price'], 2) ?></div>
                                                        <div class="text-danger" style="font-size: 9px;">⚠️ Cancellation Fee</div>
                                                    <?php else: ?>
                                                        ₱<?= number_format($b['total_price'], 2) ?>
                                                        <?php if ($b['down_payment'] > 0): ?>
                                                            <div class="text-success" style="font-size: 9px;">DP: ₱<?= number_format($b['down_payment'], 2) ?></div>
                                                        <?php endif; ?>
                                                        <div class="text-success" style="font-size: 9px;">Remaining Balance:
                                                            <?php
                                                            $total_price = $b['total_price'];
                                                            $down_payment = $b['down_payment'];
                                                            $remaining_balance = $total_price - $down_payment;
                                                            ?>
                                                            ₱<?= number_format($remaining_balance, 2) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center" data-label="Status">
                                                    <?php
                                                    $statusClass = ['Pending' => 'bg-warning text-dark', 'Confirmed' => 'bg-info text-white', 'Completed' => 'bg-success text-white', 'Cancelled' => 'bg-danger text-white'];
                                                    $currentClass = $statusClass[$b['status']] ?? 'bg-secondary';
                                                    ?>
                                                    <span class="badge <?= $currentClass ?> rounded-pill px-3 py-2"><?= $b['status'] ?></span>
                                                </td>
                                                <td class="text-end pe-4" data-label="Actions">
                                                    <div class="dropdown">
                                                        <?php if ($b['status'] === 'Confirmed'): ?>
                                                            <button class="btn btn-sm btn-manage dropdown-toggle" data-bs-toggle="dropdown">Manage</button>
                                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                                                <li><a class="dropdown-item text-primary" href="#" data-bs-toggle="modal" data-bs-target="#editBookingModal<?= $b['id'] ?>"><i class="bi bi-pencil-square me-2"></i>Edit Booking</a></li>
                                                                <li><a class="dropdown-item text-success" href="process/booking_actions.php?id=<?= $b['id'] ?>&status=Completed&filter=<?= $filter ?>"><i class="bi bi-flag me-2"></i>Mark Completed</a></li>
                                                                <li><hr class="dropdown-divider"></li>
                                                                <li><a class="dropdown-item text-danger" href="process/booking_actions.php?id=<?= $b['id'] ?>&status=Cancelled&filter=<?= $filter ?>" onclick="return confirm('Cancel this booking?')"><i class="bi bi-x-circle me-2"></i>Cancel</a></li>
                                                            </ul>
                                                        <?php elseif ($b['status'] === 'Pending'): ?>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="process/booking_actions.php?id=<?= $b['id'] ?>&status=Confirmed&filter=<?= $filter ?>" class="btn btn-sm btn-success" onclick="return confirm('Confirm this booking?')"><i class="bi bi-check-lg"></i> Confirm</a>
                                                                <a href="process/booking_actions.php?id=<?= $b['id'] ?>&status=Cancelled&filter=<?= $filter ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this booking?')"><i class="bi bi-x-lg"></i> Cancel</a>
                                                            </div>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-manage disabled opacity-50" disabled>Manage</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>

<?php foreach ($bookings as $b): ?>
    <div class="modal fade" id="editBookingModal<?= $b['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white p-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Booking #BK-<?= $b['id'] ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="process/booking_actions.php" method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="update_booking" value="1">
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <input type="hidden" name="source" value="online">
                        <input type="hidden" name="filter" value="<?= $filter ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Customer</label>
                                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($b['customer_name'] ?? 'Guest') ?>" readonly disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Vehicle</label>
                                <select name="car_id" class="form-select" required>
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
                            <div class="col-md-6"><label class="form-label small fw-bold">Pickup Date</label><input type="date" name="start_date" class="form-control" value="<?= $b['start_date'] ?>" required></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">Pickup Time</label><input type="time" name="pickup_time" class="form-control" value="<?= $b['pickup_time'] ?>" required></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">Return Date</label><input type="date" name="end_date" class="form-control" value="<?= $b['end_date'] ?>" required></div>
                            <div class="col-md-6"><label class="form-label small fw-bold">Return Time</label><input type="time" name="return_time" class="form-control" value="<?= $b['return_time'] ?>" required></div>
                            <div class="col-12">
                                <div class="alert alert-info p-2 small">
                                    <i class="bi bi-info-circle"></i> Current Total: <strong>₱<?= number_format($b['total_price'], 2) ?></strong><br>
                                    <span class="text-muted">Price will be recalculated based on new dates/times.</span>
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
    // 🟢 LIGHTWEIGHT DECOUPLED FILTRATION AND LIMITATION RUNTIME ENGINE
    $(document).ready(function () {
        
        // Core dynamic UI function to balance row limiters against search text queries
        function applyLimiterAndFilter() {
            const queryValue = $('#unifiedOnlineSearch').val().toLowerCase().trim();
            const limitValue = parseInt($('#onlineEntryLimitSelect').val(), 10) || 10;

            let matchCount = 0;
            
            // Loop through all data rows (works perfectly on both desktop tables and mobile blocks)
            $('#bookingsTable tbody tr.js-searchable-booking').each(function () {
                const textContent = $(this).text().toLowerCase();
                const matchesSearch = textContent.includes(queryValue);

                if (matchesSearch) {
                    matchCount++;
                    // Only display rows within the entry limit boundary
                    if (matchCount <= limitValue) {
                        $(this).css('display', ''); // Restores standard CSS render rule visibility
                    } else {
                        $(this).css('display', 'none'); // Hides row because it exceeded selected limit
                    }
                } else {
                    $(this).css('display', 'none'); // Hides row because it didn't match search text
                }
            });
        }

        // Bind Action Core Event Targets
        $('#unifiedOnlineSearch').on('input', applyLimiterAndFilter);
        $('#onlineEntryLimitSelect').on('change', applyLimiterAndFilter);

        // Initial execution on load
        applyLimiterAndFilter();
    });
</script>