<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../../index.php");
    exit();
}

$booking_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];

// 1. Fetch Booking with Car and User Details (Using LEFT JOIN for guests)
$query = "SELECT b.*, 
          c.brand, c.model, c.plate_number, c.image_path, 
          c.price_24_hours AS price_per_day, 
          c.operator_24_hours AS extension_price,
          u.name as registered_name, u.email as registered_email, u.phone as registered_phone,
          owner.name as operator_name
          FROM bookings b
          JOIN cars c ON b.car_id = c.id
          LEFT JOIN users u ON b.user_id = u.id 
          JOIN users owner ON c.user_id = owner.id
          WHERE b.id = $booking_id";

// 2. Security Check
if ($user_role === 'operator') {
    $query .= " AND c.user_id = $user_id";
} elseif ($user_role === 'user') {
    $query .= " AND b.user_id = $user_id";
}

$result = mysqli_query($conn, $query);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Booking not found or access denied.</div></div>");
}

// 3. PRIORITY LOGIC: Use registered info if user_id exists, otherwise use guest_name
$displayName  = !empty($booking['registered_name']) ? $booking['registered_name'] : $booking['guest_name'];
$displayPhone = !empty($booking['registered_phone']) ? $booking['registered_phone'] : $booking['phone_number'];
$displayEmail = !empty($booking['registered_email']) ? $booking['registered_email'] : ($booking['gmail'] ?? 'N/A');

$pageTitle = "Booking Details #" . $booking['id'];
require_once __DIR__ . '/../components/head.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col-md-10 p-0 d-flex flex-column main-content" style="background: #f8fafc; min-height: 100vh;">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <a href="calendar.php" class="btn btn-light border btn-sm rounded-3">
                        <i class="bi bi-arrow-left"></i> Back to Calendar
                    </a>
                    <div class="badge rounded-pill <?php 
                        echo match($booking['status']) {
                            'Approved' => 'bg-success',
                            'Pending' => 'bg-warning text-dark',
                            'Completed' => 'bg-primary',
                            default => 'bg-danger'
                        };
                    ?> px-3 py-2">Status: <?= $booking['status'] ?></div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm rounded-4 mb-4">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-4">
                                    <img src="../../public/assets/images/cars/<?= $booking['image_path'] ?>" 
                                         class="rounded-3 me-3" style="width: 120px; height: 80px; object-fit: cover;">
                                    <div>
                                        <h4 class="fw-bold mb-0"><?= $booking['brand'] ?> <?= $booking['model'] ?></h4>
                                        <p class="text-muted mb-0">Plate: <span class="badge bg-light text-dark border"><?= $booking['plate_number'] ?></span></p>
                                    </div>
                                </div>

                                <div class="row text-center bg-light rounded-4 p-3 g-2">
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted d-block">Pick-up</small>
                                        <span class="fw-bold"><?= date('M d, Y', strtotime($booking['start_date'])) ?></span>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted d-block">Return</small>
                                        <span class="fw-bold"><?= date('M d, Y', strtotime($booking['end_date'])) ?></span>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted d-block">Duration</small>
                                        <span class="fw-bold">
                                            <?php 
                                                $start = new DateTime($booking['start_date']);
                                                $end = new DateTime($booking['end_date']);
                                                echo $start->diff($end)->days + 1; 
                                            ?> Days
                                        </span>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <small class="text-muted d-block">Daily Rate</small>
                                        <span class="fw-bold text-primary">₱<?= number_format($booking['price_per_day'], 0) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-3">Customer Information</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="small text-muted">Full Name</label>
                                        <p class="fw-bold mb-0"><?= htmlspecialchars($displayName) ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="small text-muted">Phone Number</label>
                                        <p class="fw-bold mb-0"><?= htmlspecialchars($displayPhone) ?></p>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label class="small text-muted">Email Address</label>
                                        <p class="fw-bold mb-0"><?= htmlspecialchars($displayEmail) ?></p>
                                    </div>
                                    <?php if($user_role !== 'user'): ?>
                                    <div class="col-12">
                                        <hr class="my-3 opacity-50">
                                        <label class="small text-muted">Operator (Owner)</label>
                                        <p class="fw-bold mb-0"><?= $booking['operator_name'] ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm rounded-4 bg-dark text-white mb-4">
                            <div class="card-body p-4 text-center">
                                <small class="text-uppercase opacity-75">Total Payment</small>
                                <h2 class="fw-bold mb-0 mt-1">₱<?= number_format($booking['total_price'], 2) ?></h2>
                            </div>
                        </div>

                        <?php if($user_role !== 'user'): ?>
                        <div class="card border-0 shadow-sm rounded-4">
                            <div class="card-body p-4">
                                <h6 class="fw-bold mb-3">Manage Status</h6>
                                <form action="process/update_booking.php" method="POST">
                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                    <select name="status" class="form-select mb-3 rounded-3">
                                        <option value="Pending" <?= $booking['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="Approved" <?= $booking['status'] == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                        <option value="Completed" <?= $booking['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="Cancelled" <?= $booking['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3">Save Update</button>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <button onclick="window.print()" class="btn btn-outline-secondary w-100 rounded-3">
                                <i class="bi bi-printer me-2"></i> Print Invoice
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>