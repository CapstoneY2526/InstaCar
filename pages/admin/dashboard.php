<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// --- AUTHENTICATION CHECK - JS Redirect ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access.";
    ?>
    <script>
        window.stop();
        window.location.href = "../../index.php";
    </script>
    <?php
    exit();
}

$current_month = date('m');
$current_year = date('Y');
$current_date = date('Y-m-d');

// 1. Total Cars
$res_cars = mysqli_query($conn, "SELECT COUNT(id) as total FROM cars");
$row_cars = mysqli_fetch_assoc($res_cars);
$total_cars = $row_cars['total'] ?? 0;

// 2. Available Cars
$res_available = mysqli_query($conn, "SELECT COUNT(id) as total FROM cars WHERE status = 'Available'");
$row_available = mysqli_fetch_assoc($res_available);
$available_cars = $row_available['total'] ?? 0;

// 3. Active Bookings (Confirmed and not yet completed)
$res_active_bookings = mysqli_query($conn, "
    SELECT COUNT(id) as total 
    FROM bookings 
    WHERE status = 'Confirmed' 
    AND start_date <= '$current_date'
    AND end_date >= '$current_date'
");
$row_active_bookings = mysqli_fetch_assoc($res_active_bookings);
$active_bookings = $row_active_bookings['total'] ?? 0;

// 4. Total Bookings (All time)
$res_total_bookings = mysqli_query($conn, "SELECT COUNT(id) as total FROM bookings");
$row_total_bookings = mysqli_fetch_assoc($res_total_bookings);
$total_bookings = $row_total_bookings['total'] ?? 0;

// 5. Completed Bookings (This Month)
$res_completed = mysqli_query($conn, "
    SELECT COUNT(id) as total 
    FROM bookings 
    WHERE status = 'Completed' 
    AND MONTH(created_at) = '$current_month' 
    AND YEAR(created_at) = '$current_year'
");
$row_completed = mysqli_fetch_assoc($res_completed);
$completed_bookings = $row_completed['total'] ?? 0;

// 6. Monthly NET Revenue (from booking_payments table - using total_net)
$res_revenue = mysqli_query($conn, "
    SELECT COALESCE(SUM(total_net), 0) as total 
    FROM booking_payments
    WHERE MONTH(created_at) = '$current_month' 
    AND YEAR(created_at) = '$current_year'
");
$row_revenue = mysqli_fetch_assoc($res_revenue);
$monthly_revenue = $row_revenue['total'] ?? 0;

// 7. Total NET Revenue (All time - using total_net)
$res_total_revenue = mysqli_query($conn, "SELECT COALESCE(SUM(total_net), 0) as total FROM booking_payments");
$row_total_revenue = mysqli_fetch_assoc($res_total_revenue);
$total_revenue = $row_total_revenue['total'] ?? 0;

// 8. Total GROSS Revenue (for reference - optional)
$res_gross_revenue = mysqli_query($conn, "SELECT COALESCE(SUM(total_gross), 0) as total FROM booking_payments");
$row_gross_revenue = mysqli_fetch_assoc($res_gross_revenue);
$total_gross_revenue = $row_gross_revenue['total'] ?? 0;

// 9. Pending Tasks (Pending Bookings)
$res_tasks = mysqli_query($conn, "SELECT COUNT(id) as total FROM bookings WHERE status = 'Pending'");
$row_tasks = mysqli_fetch_assoc($res_tasks);
$pending_tasks = $row_tasks['total'] ?? 0;

// 10. Total Customers (Users with role 'user')
$res_customers = mysqli_query($conn, "SELECT COUNT(id) as total FROM users WHERE role = 'user'");
$row_customers = mysqli_fetch_assoc($res_customers);
$total_customers = $row_customers['total'] ?? 0;

// 11. Total Operators
$res_operators = mysqli_query($conn, "SELECT COUNT(id) as total FROM users WHERE role = 'operator'");
$row_operators = mysqli_fetch_assoc($res_operators);
$total_operators = $row_operators['total'] ?? 0;

// 12. Recent Bookings (Last 5)
$recent_bookings_res = mysqli_query($conn, "
    SELECT b.*, 
           COALESCE(u.name, b.guest_name) as customer_name, 
           c.brand, 
           c.model, 
           c.plate_number,
           bp.total_net,
           bp.total_gross
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    JOIN cars c ON b.car_id = c.id 
    LEFT JOIN booking_payments bp ON b.id = bp.booking_id
    ORDER BY b.id DESC 
    LIMIT 5
");

$recent_bookings = [];
if ($recent_bookings_res) {
    while ($booking = mysqli_fetch_assoc($recent_bookings_res)) {
        $recent_bookings[] = $booking;
    }
}

// 13. Recent Cars List
$recent_cars_res = mysqli_query($conn, "SELECT brand, model, plate_number, status FROM cars ORDER BY id DESC LIMIT 5");
$recent_cars = [];
if ($recent_cars_res) {
    while ($car = mysqli_fetch_assoc($recent_cars_res)) {
        $recent_cars[] = $car;
    }
}

$pageTitle = 'Admin Dashboard';
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    body { 
        background-color: #f8fafc; 
        font-family: 'Poppins', sans-serif;
    }

    .main-content { min-height: 100vh; }
    
    /* ========================================================
       UNIFIED DASHBOARD STATS CARD ENGINE (MATCHED TO ONLINE)
    ======================================================== */
    .row.g-3.mb-4 { flex-wrap: wrap; }
    
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 1.25rem;
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
        color: #1e293b;
    }
    @media (min-width: 576px) {
        .stat-value {
            font-size: 1.65rem;
        }
    }
    .stat-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        font-weight: 600;
        margin-top: 2px;
    }
    .stat-subtext {
        font-size: 0.75rem;
        display: block;
        margin-top: 2px;
    }

    .table thead th {
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: 0.75rem;
        background-color: #f1f5f9;
    }
    .status-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-block;
    }
    .status-pending { background: #fef3c7; color: #d97706; }
    .status-confirmed { background: #dbeafe; color: #2563eb; }
    .status-completed { background: #d1fae5; color: #059669; }
    .status-cancelled { background: #fee2e2; color: #dc2626; }

    /* Custom Mobile Card View Elements */
    .mobile-card-container {
        display: none;
    }

    @media (max-width: 768px) {
        body { font-size: 0.85rem; }
        h3 { font-size: 1.25rem; }
        h4 { font-size: 1.1rem; }
        h2 { font-size: 1.3rem !important; }

        .p-4 { padding: 0.85rem !important; }
        .welcome-text { display: none; }
        .row.g-3, .row.g-md-4 { --bs-gutter-y: .75rem; }

        /* Transform table visibility into Responsive Cards */
        .desktop-table-card {
            display: none !important;
        }
        .mobile-card-container {
            display: block;
        }
        .responsive-mobile-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
            border: 1px solid #eef2f6;
        }
    }

    /* Tablet adjustments */
    @media (max-width: 991px) {
        .main-content { width: 100%; }
        .p-4 { padding: 1rem !important; }
        .card { margin-bottom: 1rem; }
        h3 { font-size: 1.4rem; }
        h4 { font-size: 1.15rem; }
        .table { font-size: 0.85rem; }
    }

    /* Extra Small Screen Optimization */
    @media (max-width: 576px) {
        .container-fluid { padding: 0; }
        .p-4 { padding: .75rem !important; }
        .d-flex.justify-content-between.align-items-center.mb-4 {
            flex-direction: column;
            align-items: flex-start !important;
            gap: .75rem;
        }

        body {
            padding-left: 12px;
            padding-right: 12px;
            padding-bottom: 20px; /* Gives nice breathing room at the bottom of mobile card views */
        }
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
                        <h3 class="fw-bold mb-0">Admin Dashboard</h3>
                        <p class="text-muted mb-0 welcome-text">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?>.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-white border shadow-sm rounded-3 px-3 fw-semibold small">
                            <i class="bi bi-calendar3 me-1 text-primary"></i> <?= date('F d, Y') ?>
                        </button>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    
                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-car-front-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= $total_cars ?></div>
                                    <div class="stat-label">Total Fleet</div>
                                    <small class="text-success stat-subtext"><?= $available_cars ?> Available</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <div>
                                    <div class="stat-value">₱<?= number_format($monthly_revenue, 0) ?></div>
                                    <div class="stat-label">Monthly Net</div>
                                    <small class="text-muted stat-subtext">This month</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-key-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= $active_bookings ?></div>
                                    <div class="stat-label">Active Trips</div>
                                    <small class="text-muted stat-subtext">Currently Rented</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-cash-stack"></i>
                                </div>
                                <div>
                                    <div class="stat-value">₱<?= number_format($total_revenue, 0) ?></div>
                                    <div class="stat-label">Total Revenue</div>
                                    <small class="text-muted stat-subtext">All time</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-calendar-check-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= $total_bookings ?></div>
                                    <div class="stat-label">Total Bookings</div>
                                    <small class="text-muted stat-subtext"><?= $completed_bookings ?> Completed</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= $total_customers ?></div>
                                    <div class="stat-label">Customers</div>
                                    <small class="text-muted stat-subtext">Registered users</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value <?= $pending_tasks > 0 ? 'text-danger' : '' ?>"><?= $pending_tasks ?></div>
                                    <div class="stat-label">Pending Tasks</div>
                                    <small class="text-muted stat-subtext">Awaiting check</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-sm-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                                    <i class="bi bi-person-badge-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= $total_operators ?></div>
                                    <div class="stat-label">Operators</div>
                                    <small class="text-muted stat-subtext">Active partners</small>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row">
                    <div class="col-lg-7 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="fw-bold mb-0">Recent Bookings</h4>
                            <a href="bookings_online.php" class="btn btn-link text-decoration-none fw-bold small text-primary">View All</a>
                        </div>
                        
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden desktop-table-card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th class="border-0 px-3 py-3">Customer</th>
                                                <th class="border-0 py-3">Vehicle</th>
                                                <th class="border-0 py-3">Dates</th>
                                                <th class="border-0 py-3">Total</th>
                                                <th class="border-0 py-3 text-center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recent_bookings)): ?>
                                                <?php foreach ($recent_bookings as $booking): ?>
                                                    <tr>
                                                        <td class="px-3 py-3 align-middle">
                                                            <div class="fw-bold text-dark"><?= htmlspecialchars($booking['customer_name'] ?? 'Guest') ?></div>
                                                            <div class="text-muted small">#BK-<?= $booking['id'] ?></div>
                                                        </td>
                                                        <td class="py-3 align-middle">
                                                            <div class="fw-semibold"><?= htmlspecialchars($booking['brand']) ?> <?= htmlspecialchars($booking['model']) ?></div>
                                                            <div class="text-muted small"><?= htmlspecialchars($booking['plate_number']) ?></div>
                                                        </td>
                                                        <td class="py-3 align-middle">
                                                            <div class="small"><?= date('M d', strtotime($booking['start_date'])) ?> - <?= date('M d', strtotime($booking['end_date'])) ?></div>
                                                            <div class="text-muted small"><?= date('h:i A', strtotime($booking['pickup_time'])) ?></div>
                                                         </td>
                                                        <td class="py-3 align-middle fw-bold">₱<?= number_format($booking['total_price'], 2) ?></td>
                                                        <td class="py-3 align-middle text-center">
                                                            <span class="status-badge status-<?= strtolower($booking['status']) ?>">
                                                                <?= $booking['status'] ?>
                                                            </span>
                                                         </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">No recent bookings found.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="mobile-card-container">
                            <?php if (!empty($recent_bookings)): ?>
                                <?php foreach ($recent_bookings as $booking): ?>
                                    <div class="responsive-mobile-card shadow-sm">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <div class="fw-bold text-dark mb-0" style="font-size: 0.95rem;"><?= htmlspecialchars($booking['customer_name'] ?? 'Guest') ?></div>
                                                <small class="text-muted">ID: #BK-<?= $booking['id'] ?></small>
                                            </div>
                                            <span class="status-badge status-<?= strtolower($booking['status']) ?>">
                                                <?= $booking['status'] ?>
                                            </span>
                                        </div>
                                        <div class="border-top border-bottom py-2 my-2" style="background: #fafbfc; margin-left: -16px; margin-right: -16px; padding-left: 16px; padding-right: 16px;">
                                            <div class="small fw-semibold"><i class="bi bi-car-front me-2 text-secondary"></i><?= htmlspecialchars($booking['brand']) ?> <?= htmlspecialchars($booking['model']) ?></div>
                                            <div class="text-muted extra-small" style="font-size: 0.75rem; padding-left: 22px;">Plate: <code><?= htmlspecialchars($booking['plate_number']) ?></code></div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center pt-1">
                                            <div class="text-muted small">
                                                <i class="bi bi-calendar3 me-1"></i> <?= date('M d', strtotime($booking['start_date'])) ?> - <?= date('M d', strtotime($booking['end_date'])) ?>
                                            </div>
                                            <div class="fw-bold text-primary" style="font-size: 1rem;">
                                                ₱<?= number_format($booking['total_price'], 2) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="responsive-mobile-card text-center text-muted py-4">No recent bookings found.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="fw-bold mb-0">Recent Vehicles</h4>
                            <a href="../shared/cars.php" class="btn btn-link text-decoration-none fw-bold small text-primary">View All</a>
                        </div>

                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 desktop-table-card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th class="border-0 px-3 py-3">Vehicle</th>
                                                <th class="border-0 py-3">Plate</th>
                                                <th class="border-0 py-3">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recent_cars)): ?>
                                                <?php foreach ($recent_cars as $car): ?>
                                                    <tr>
                                                        <td class="px-3 py-3 align-middle">
                                                            <div class="fw-semibold"><?= htmlspecialchars($car['brand']) ?> <?= htmlspecialchars($car['model']) ?></div>
                                                         </td>
                                                        <td class="py-3 align-middle"><code><?= htmlspecialchars($car['plate_number']) ?></code></td>
                                                        <td class="py-3 align-middle">
                                                            <?php 
                                                                $status = $car['status'];
                                                                $badgeClass = match($status) {
                                                                    'Available' => 'bg-success',
                                                                    'Active', 'Rented' => 'bg-warning',
                                                                    'Maintenance' => 'bg-danger',
                                                                    default => 'bg-secondary'
                                                                };
                                                            ?>
                                                            <span class="badge <?= $badgeClass ?> rounded-pill px-3"><?= $status ?></span>
                                                         </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center py-4 text-muted">No vehicles found.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="mobile-card-container mb-4">
                            <?php if (!empty($recent_cars)): ?>
                                <?php foreach ($recent_cars as $car): ?>
                                    <div class="responsive-mobile-card shadow-sm d-flex justify-content-between align-items-center py-3">
                                        <div>
                                            <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($car['brand']) ?> <?= htmlspecialchars($car['model']) ?></div>
                                            <span class="text-muted small">Plate: <code><?= htmlspecialchars($car['plate_number']) ?></code></span>
                                        </div>
                                        <?php 
                                            $status = $car['status'];
                                            $badgeClass = match($status) {
                                                'Available' => 'bg-success',
                                                'Active', 'Rented' => 'bg-warning',
                                                'Maintenance' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };
                                        ?>
                                        <span class="badge <?= $badgeClass ?> rounded-pill px-3 py-2" style="font-size: 0.75rem; min-width: 90px; text-align: center;">
                                            <?= $status ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="responsive-mobile-card text-center text-muted py-4">No vehicles found.</div>
                            <?php endif; ?>
                        </div>

                        <h4 class="fw-bold mb-3">System Priority</h4>
                        <div class="card border-0 shadow-sm rounded-4 p-4">
                            <div class="d-flex gap-3 mb-4">
                                <div class="icon-shape text-warning flex-shrink-0">
                                    <i class="bi bi-tools"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1 small">Pending Bookings</h6>
                                    <p class="text-muted mb-0" style="font-size: 0.75rem;"><?= $pending_tasks ?> booking(s) awaiting confirmation.</p>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-3 mb-4">
                                <div class="icon-shape text-info flex-shrink-0">
                                    <i class="bi bi-wallet2"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1 small">Revenue Overview</h6>
                                    <p class="text-muted mb-0" style="font-size: 0.75rem;">₱<?= number_format($monthly_revenue, 2) ?> earned this month.</p>
                                </div>
                            </div>

                            <div class="d-flex gap-3">
                                <div class="icon-shape text-success flex-shrink-0">
                                    <i class="bi bi-car-front"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1 small">Fleet Status</h6>
                                    <p class="text-muted mb-0" style="font-size: 0.75rem;"><?= $available_cars ?> cars available for rent.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>