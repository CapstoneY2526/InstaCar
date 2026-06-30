<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// --- AUTHENTICATION CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    $_SESSION['error'] = "Unauthorized access.";
    ?>
    <script>window.location.href = "../../index.php";</script>
    <?php
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// --- FETCH USER-SPECIFIC DATA ---

// 1. Total Bookings (All time)
$total_query = mysqli_query($conn, "SELECT COUNT(id) as total FROM bookings WHERE user_id = $user_id");
$total_bookings = mysqli_fetch_assoc($total_query)['total'] ?? 0;

// 2. Active Rental (Currently Approved or Ongoing)
$active_query = mysqli_query($conn, "SELECT COUNT(id) as total FROM bookings WHERE user_id = $user_id AND status = 'Approved'");
$active_rentals = mysqli_fetch_assoc($active_query)['total'] ?? 0;

// 3. Total Spent
$spent_query = mysqli_query($conn, "SELECT SUM(total_price) as total FROM bookings WHERE user_id = $user_id AND status = 'Completed'");
$total_spent = mysqli_fetch_assoc($spent_query)['total'] ?? 0;

// 4. Recent Bookings List
$recent_query = "SELECT b.id, b.start_date, b.end_date, b.status, b.total_price, c.brand, c.model 
                 FROM bookings b 
                 JOIN cars c ON b.car_id = c.id 
                 WHERE b.user_id = $user_id 
                 ORDER BY b.created_at DESC LIMIT 5";
$recent_bookings = mysqli_query($conn, $recent_query);

// Convert mysql result object to array so it can be cleanly re-looped on both Desktop (Table) and Mobile/Tablet (Cards)
$bookings_list = [];
if ($recent_bookings && $recent_bookings->num_rows > 0) {
    while($row = $recent_bookings->fetch_assoc()) {
        $bookings_list[] = $row;
    }
}

$pageTitle = 'My Dashboard';
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    body { background-color: #f4f7fe; font-family: 'Poppins', sans-serif; overflow-x: hidden; }
    .main-content { min-height: 100vh; width: 100%; }
    .welcome-card {
        background: linear-gradient(45deg, #4e73df 0%, #224abe 100%);
        color: white;
        border-radius: 1.5rem;
    }
    .stat-card { border-radius: 1.25rem; border: none; transition: transform 0.2s; }
    .stat-card:hover { transform: scale(1.02); }
    .btn-book { border-radius: 12px; font-weight: 600; padding: 12px 24px; }
    
    /* Responsive Styling Custom Adjustments */
    @media (max-width: 576px) {
        .welcome-card { padding: 2rem !important; }
    }

    /* Mobile/Tablet Booking Cards Look & Feel */
    .mobile-booking-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.25rem;
        margin: 0.75rem 1rem;
        transition: box-shadow 0.2s ease;
    }
    .mobile-booking-card:last-child {
        margin-bottom: 1.25rem;
    }
    .mobile-booking-card:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
</style>

<div class="container-fluid">
    <div class="row"> 
        <div class="col-md-2 p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col-md-10 p-0 d-flex flex-column main-content" style="background: #f8fafc; min-height: 100vh;">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">
                
                <div class="card welcome-card shadow-lg border-0 mb-4">
                    <div class="card-body p-4 p-md-5">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-8 text-center text-md-start">
                                <h2 class="fw-bold mb-2">Hello, <?= htmlspecialchars($_SESSION['name']) ?>! 👋</h2>
                                <p class="lead opacity-75 fs-6 fs-md-5">Ready for your next adventure? Browse our latest fleet and hit the road.</p>
                                <a href="cars.php" class="btn btn-light btn-book text-primary shadow-sm mt-3 w-100 w-md-auto">
                                    <i class="bi bi-search me-2"></i> Find a Car
                                </a>
                            </div>
                            <div class="col-md-4 text-end d-none d-md-block">
                                <i class="bi bi-car-front" style="font-size: 8rem; opacity: 0.2;"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 g-md-4 mb-4 mb-md-5">
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                                    <i class="bi bi-calendar-check fs-3"></i>
                                </div>
                                <div>
                                    <small class="text-muted fw-bold">My Bookings</small>
                                    <h3 class="fw-bold mb-0"><?= $total_bookings ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                                    <i class="bi bi-clock-history fs-3"></i>
                                </div>
                                <div>
                                    <small class="text-muted fw-bold">Active Trips</small>
                                    <h3 class="fw-bold mb-0"><?= $active_rentals ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="card stat-card shadow-sm p-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3">
                                    <i class="bi bi-credit-card fs-3"></i>
                                </div>
                                <div>
                                    <small class="text-muted fw-bold">Total Spent</small>
                                    <h3 class="fw-bold mb-0">₱<?= number_format($total_spent, 2) ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-4 px-3 px-md-4 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">My Recent Trips</h5>
                        <a href="mybookings.php" class="small text-decoration-none">View All</a>
                    </div>
                    
                    <div class="card-body p-0 d-none d-lg-block">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="border-0 px-4 py-3">Vehicle</th>
                                        <th class="border-0 py-3">Duration</th>
                                        <th class="border-0 py-3 text-center">Status</th>
                                        <th class="border-0 py-3 text-end px-4">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(!empty($bookings_list)): ?>
                                        <?php foreach($bookings_list as $row): ?>
                                            <tr>
                                                <td class="px-4 py-3 align-middle">
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($row['brand'] . ' ' . $row['model']) ?></span>
                                                </td>
                                                <td class="py-3 align-middle">
                                                    <small class="text-muted">
                                                        <?= date('M d, Y', strtotime($row['start_date'])) ?> - <?= date('M d, Y', strtotime($row['end_date'])) ?>
                                                    </small>
                                                </td>
                                                <td class="py-3 align-middle text-center">
                                                    <?php 
                                                        $status = $row['status'];
                                                        $badge = 'bg-secondary';
                                                        if($status == 'Approved') $badge = 'bg-primary';
                                                        if($status == 'Completed') $badge = 'bg-success';
                                                        if($status == 'Cancelled') $badge = 'bg-danger';
                                                        if($status == 'Pending') $badge = 'bg-warning text-dark';
                                                    ?>
                                                    <span class="badge <?= $badge ?> rounded-pill px-3 py-2"><?= $status ?></span>
                                                </td>
                                                <td class="py-3 align-middle text-end px-4 fw-bold text-dark">
                                                    ₱<?= number_format($row['total_price'], 2) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <p class="mb-0">You haven't booked any cars yet.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="d-block d-lg-none border-top">
                        <?php if(!empty($bookings_list)): ?>
                            <div class="row g-0">
                                <?php foreach($bookings_list as $row): 
                                    $status = $row['status'];
                                    $badge = 'bg-secondary';
                                    if($status == 'Approved') $badge = 'bg-primary';
                                    if($status == 'Completed') $badge = 'bg-success';
                                    if($status == 'Cancelled') $badge = 'bg-danger';
                                    if($status == 'Pending') $badge = 'bg-warning text-dark';
                                ?>
                                    <div class="col-12 col-md-6"> <div class="mobile-booking-card shadow-sm">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['brand'] . ' ' . $row['model']) ?></h6>
                                                    <div class="text-muted small">
                                                        <i class="bi bi-calendar3 me-1"></i>
                                                        <?= date('M d, Y', strtotime($row['start_date'])) ?> &rarr; <?= date('M d, Y', strtotime($row['end_date'])) ?>
                                                    </div>
                                                </div>
                                                <span class="badge <?= $badge ?> rounded-pill px-2.5 py-1.5"><?= $status ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-light">
                                                <span class="small text-muted">Total Paid Amount:</span>
                                                <span class="fw-bold text-primary">₱<?= number_format($row['total_price'], 2) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-folder2-open fs-2 d-block mb-2 text-opacity-50 text-dark"></i>
                                <p class="mb-0 small">You haven't booked any cars yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>