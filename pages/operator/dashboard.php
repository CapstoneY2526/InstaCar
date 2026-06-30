<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'operator') {
    $_SESSION['error'] = "Unauthorized access.";
    ?>
    <script>window.location.href = "../../index.php";</script>
    <?php
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$current_month = (int)date('m');
$current_year = (int)date('Y');

// --- FETCH OPERATOR-SPECIFIC DATA ---

// Total Cars Owned by this Operator
$total_my_cars = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM cars WHERE user_id = $user_id"))['total'] ?? 0;

// Available Cars
$available_cars = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM cars WHERE user_id = $user_id AND status = 'Available'"))['total'] ?? 0;

// Current Active Rentals (Rented or Active)
$active_rentals = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(id) as total FROM cars WHERE user_id = $user_id AND status IN ('Active', 'Rented')"))['total'] ?? 0;

// Monthly Earnings (Net)
$monthly_earnings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(bp.total_net), 0) as total 
                 FROM booking_payments bp
                 JOIN bookings b ON bp.booking_id = b.id
                 JOIN cars c ON b.car_id = c.id
                 WHERE c.user_id = $user_id AND MONTH(bp.created_at) = $current_month AND YEAR(bp.created_at) = $current_year"))['total'] ?? 0;

// Total Earnings (All time)
$total_earnings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(bp.total_net), 0) as total 
                 FROM booking_payments bp
                 JOIN bookings b ON bp.booking_id = b.id
                 JOIN cars c ON b.car_id = c.id
                 WHERE c.user_id = $user_id"))['total'] ?? 0;

// Total Pending Balance/Remittance
$pending_remittance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(bp.owner_balance), 0) as total 
                FROM booking_payments bp
                JOIN bookings b ON bp.booking_id = b.id
                JOIN cars c ON b.car_id = c.id
                WHERE c.user_id = $user_id"))['total'] ?? 0;

// Total Bookings
$total_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(b.id) as total 
                FROM bookings b
                JOIN cars c ON b.car_id = c.id
                WHERE c.user_id = $user_id"))['total'] ?? 0;

// Recent Cars List - UPDATED: Changed price_per_day to price_24_hours
$my_recent_cars = mysqli_query($conn, "SELECT id, brand, model, plate_number, status, price_24_hours FROM cars WHERE user_id = $user_id ORDER BY id DESC LIMIT 5");

// Convert result set into an array to cleanly handle loop recycling
$cars_list = [];
if ($my_recent_cars && mysqli_num_rows($my_recent_cars) > 0) {
    while($car = mysqli_fetch_assoc($my_recent_cars)) {
        $cars_list[] = $car;
    }
}

$pageTitle = 'Operator Dashboard';
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    /* Enhanced Stats Cards Look and Feel */
    .stat-card {
        border-radius: 20px;
        border: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        background: white;
        overflow: hidden;
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.05) !important;
    }
    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.2;
        color: #1e293b;
    }
    .stat-label {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 4px;
    }

    @media (min-width: 576px) {
        .stat-value { font-size: 1.4rem; }
        .stat-icon { width: 48px; height: 48px; }
    }
    
    /* Table Styles */
    .table-custom thead th {
        background: #f8fafc;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-custom tbody tr:hover {
        background-color: #fef9e3;
        transition: background 0.2s;
    }

    /* Mobile & Tablet Mode Car Cards UI Layout styling */
    .mobile-vehicle-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.25rem;
        margin: 0.75rem 1rem;
        transition: box-shadow 0.2s ease;
    }
    .mobile-vehicle-card:last-child {
        margin-bottom: 1.25rem;
    }
    .mobile-vehicle-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-block;
    }
    .status-available { background: #d1fae5; color: #059669; }
    .status-rented { background: #dbeafe; color: #2563eb; }
    .status-active { background: #fef3c7; color: #d97706; }
    .status-maintenance { background: #fee2e2; color: #dc2626; }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 p-0 sidebar-container">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col-md-10 p-0 d-flex flex-column main-content" style="background: #f8fafc; min-height: 100vh;">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">
                <div class="mb-4">
                    <h3 class="fw-bold mb-1">Operator Dashboard</h3>
                    <p class="text-muted small mb-0">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'Operator') ?> • <?= date('F Y') ?></p>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6 col-xl-3">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">My Fleet</div>
                                    <div class="stat-value"><?= $total_my_cars ?></div>
                                    <small class="text-muted d-none d-sm-inline-block"><?= $available_cars ?> available</small>
                                </div>
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-car-front-fill fs-5 fs-sm-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Active Trips</div>
                                    <div class="stat-value"><?= $active_rentals ?></div>
                                    <small class="text-muted d-none d-sm-inline-block">In progress</small>
                                </div>
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-key-fill fs-5 fs-sm-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Bookings</div>
                                    <div class="stat-value"><?= $total_bookings ?></div>
                                    <small class="text-muted d-none d-sm-inline-block">All time records</small>
                                </div>
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-calendar-check-fill fs-5 fs-sm-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-xl-3">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Pending Bal</div>
                                    <div class="stat-value text-warning text-truncate" style="max-width: 130px;">₱<?= number_format($pending_remittance, 0) ?></div>
                                    <small class="text-muted d-none d-sm-inline-block">To collect</small>
                                </div>
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-cash-stack fs-5 fs-sm-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-5">
                    <div class="col-6">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Monthly Net</div>
                                    <div class="stat-value text-primary text-truncate" style="max-width: 140px;">₱<?= number_format($monthly_earnings, 0) ?></div>
                                    <small class="text-muted d-none d-sm-inline-block"><?= date('F Y') ?></small>
                                </div>
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-graph-up fs-5 fs-sm-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="stat-label">Total Net</div>
                                    <div class="stat-value text-success text-truncate" style="max-width: 140px;">₱<?= number_format($total_earnings, 0) ?></div>
                                    <small class="text-muted d-none d-sm-inline-block">Gross overall</small>
                                </div>
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-cash-coin fs-5 fs-sm-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3 px-1">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-car-front-fill me-2 text-primary"></i>My Vehicles
                    </h5>
                    <a href="../shared/cars.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                        <i class="bi bi-plus-circle me-1"></i>Manage Fleet
                    </a>
                </div>
                
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                    
                    <div class="table-responsive d-none d-lg-block">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4 py-3">Vehicle</th>
                                    <th class="py-3">Plate Number</th>
                                    <th class="py-3 text-center">Status</th>
                                    <th class="py-3 text-end">Daily Rate</th>
                                    <th class="pe-4 py-3 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($cars_list)): ?>
                                    <?php foreach($cars_list as $car): ?>
                                        <tr>
                                            <td class="ps-4 py-3">
                                                <div class="fw-semibold text-dark"><?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?></div>
                                             </td>
                                            <td class="py-3">
                                                <code><?= htmlspecialchars($car['plate_number']) ?></code>
                                            </td>
                                            <td class="py-3 text-center">
                                                <?php 
                                                    $status = $car['status'];
                                                    $badgeClass = '';
                                                    if ($status == 'Available') $badgeClass = 'status-available';
                                                    elseif ($status == 'Rented') $badgeClass = 'status-rented';
                                                    elseif ($status == 'Active') $badgeClass = 'status-active';
                                                    else $badgeClass = 'status-maintenance';
                                                ?>
                                                <span class="status-badge <?= $badgeClass ?>">
                                                    <?= $status ?>
                                                </span>
                                            </td>
                                            <td class="py-3 text-end">
                                                <span class="fw-bold">₱<?= number_format($car['price_24_hours'], 2) ?></span>
                                            </td>
                                            <td class="pe-4 py-3 text-end">
                                                <a href="view_car.php?id=<?= $car['id'] ?>" class="btn btn-sm btn-light border rounded-pill px-3">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-car-front display-6 d-block mb-2 opacity-50"></i>
                                            No vehicles found. Add your first car to get started.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-block d-lg-none border-top">
                        <?php if(!empty($cars_list)): ?>
                            <div class="row g-0">
                                <?php foreach($cars_list as $car): 
                                    $status = $car['status'];
                                    $badgeClass = '';
                                    if ($status == 'Available') $badgeClass = 'status-available';
                                    elseif ($status == 'Rented') $badgeClass = 'status-rented';
                                    elseif ($status == 'Active') $badgeClass = 'status-active';
                                    else $badgeClass = 'status-maintenance';
                                ?>
                                    <div class="col-12 col-md-6">
                                        <div class="mobile-vehicle-card shadow-sm">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?></h6>
                                                    <div class="text-muted small">
                                                        <i class="bi bi-hash me-1"></i>Plate: <code><?= htmlspecialchars($car['plate_number']) ?></code>
                                                    </div>
                                                </div>
                                                <span class="status-badge <?= $badgeClass ?>"><?= $status ?></span>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-light">
                                                <div>
                                                    <small class="text-muted d-block" style="font-size: 11px;">Daily Rental Rate:</small>
                                                    <span class="fw-bold text-dark">₱<?= number_format($car['price_24_hours'], 2) ?></span>
                                                </div>
                                                <a href="view_car.php?id=<?= $car['id'] ?>" class="btn btn-sm btn-light border rounded-pill px-3">
                                                    <i class="bi bi-eye me-1"></i>Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-car-front display-6 d-block mb-2 opacity-50"></i>
                                <p class="mb-0 small">No vehicles found. Add your first car to get started.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <div class="mt-auto">
                <?php require_once __DIR__ . '/../components/footer.php'; ?>
            </div>
        </div>
    </div>
</div>