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

// 1. Fetch Booking with Car and User Details
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

$images = explode(',', $booking['image_path']);
$first_image = trim($images[0]);
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --brand-yellow: #ffcc00;
        --brand-black: #0a0a0a;
        --brand-ink: #1e293b;
        --brand-card-bg-dark: #141414;
        --brand-row-bg-dark: #1a1a1a;
        --brand-border-dark: #27272a;
    }

    body { 
        font-family: 'Plus Jakarta Sans', sans-serif;
        background-color: #f8fafc; 
        overflow-x: hidden; 
        color: #0f172a;
        transition: background-color 0.25s ease, color 0.25s ease;
    }

    .main-wrapper { 
        margin-left: 260px;
        width: calc(100% - 260px);
        min-height: 100vh;
        transition: all 0.3s;
    }
    @media (max-width: 992px) {
        .main-wrapper { margin-left: 0; width: 100%; }
    }

    /* Cards & Components */
    .card-custom {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease, background-color 0.25s ease;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        animation: fadeIn 0.4s ease-out forwards;
    }

    .card-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
    }

    .car-image-container {
        position: relative;
        overflow: hidden;
        border-radius: 0.75rem;
    }

    .car-image-container img {
        transition: transform 0.4s ease;
    }

    .car-image-container:hover img {
        transform: scale(1.06);
    }

    .info-tile {
        background-color: #f1f5f9;
        border-radius: 0.75rem;
        transition: background-color 0.25s ease;
    }

    .form-control-custom, .form-select-custom {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        padding: 10px 14px;
        font-size: 14px;
        border-radius: 10px;
        color: var(--brand-ink);
        transition: all 0.2s ease;
    }

    .form-control-custom:focus, .form-select-custom:focus {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.25) !important;
        outline: none !important;
    }

    /* Primary Accent Button */
    .btn-primary-yellow {
        background-color: var(--brand-yellow) !important;
        border-color: var(--brand-yellow) !important;
        color: #000000 !important;
        font-weight: 700;
        transition: all 0.2s ease;
    }
    .btn-primary-yellow:hover, .btn-primary-yellow:focus {
        background-color: #e6c200 !important;
        border-color: #e6c200 !important;
        color: #000000 !important;
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.35) !important;
        transform: translateY(-1px);
    }

    .btn-secondary-custom {
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
        color: #0f172a;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .btn-secondary-custom:hover {
        background-color: #f1f5f9;
        color: #0f172a;
    }

    /* Status Badges */
    .status-pill {
        padding: 6px 16px;
        border-radius: 50rem;
        font-weight: 700;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    /* Fade-in Animation */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ========================================================
       CONSOLIDATED DARK MODE OVERRIDES
    ======================================================== */
    body.dark-mode {
        background-color: var(--brand-black) !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .main-wrapper,
    body.dark-mode .main-content {
        background-color: var(--brand-black) !important;
    }

    /* Header & Navbar */
    body.dark-mode header,
    body.dark-mode .header,
    body.dark-mode .topbar,
    body.dark-mode .navbar,
    body.dark-mode [class*="header"],
    body.dark-mode [class*="topbar"],
    body.dark-mode .main-wrapper > header,
    body.dark-mode .main-wrapper > div:first-child:has(.dropdown-toggle) {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #ffffff !important;
    }

    /* Cards, Info Tiles & Controls */
    body.dark-mode .card-custom {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .info-tile {
        background-color: #1a1a1a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .btn-secondary-custom {
        background-color: #1a1a1a !important;
        border-color: var(--brand-border-dark) !important;
        color: #ffffff !important;
    }

    body.dark-mode .btn-secondary-custom:hover {
        background-color: #27272a !important;
        color: #ffffff !important;
    }

    body.dark-mode .form-select-custom {
        background-color: #0d0d0d !important;
        border-color: var(--brand-border-dark) !important;
        color: #ffffff !important;
    }

    body.dark-mode .form-select-custom:focus {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.2) !important;
    }

    body.dark-mode .text-muted {
        color: #cbd5e1 !important;
    }

    body.dark-mode .border-light-subtle {
        border-color: var(--brand-border-dark) !important;
    }

    /* Print View Customization */
    @media print {
        .main-wrapper { margin-left: 0 !important; width: 100% !important; }
        .btn, sidebar, header, form { display: none !important; }
        body, .main-content { background: white !important; color: black !important; }
        .card-custom { border: 1px solid #ccc !important; box-shadow: none !important; }
    }
</style>

<div class="dashboard-container">
    <div class="sidebar-wrapper">
        <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
    </div>

    <div class="main-wrapper main-content">
        <?php require_once __DIR__ . '/../components/header.php'; ?>

        <div class="p-4 p-md-5">
            <!-- TOP BAR ACTION AREA -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <a href="calendar.php" class="btn btn-secondary-custom rounded-3 py-2 px-3">
                    <i class="bi bi-arrow-left me-2"></i>Back to Calendar
                </a>

                <div>
                    <?php 
                        $badgeClasses = match($booking['status']) {
                            'Approved' => 'bg-success-subtle text-success border border-success-subtle',
                            'Pending' => 'bg-warning-subtle text-dark border border-warning-subtle',
                            'Completed' => 'bg-success-subtle text-success border border-success-subtle',
                            default => 'bg-danger-subtle text-danger border border-danger-subtle'
                        };
                    ?>
                    <span class="status-pill <?= $badgeClasses ?>">
                        <i class="bi bi-circle-fill fs-6"></i> Status: <?= htmlspecialchars($booking['status']) ?>
                    </span>
                </div>
            </div>

            <div class="row g-4">
                <!-- MAIN CONTENT AREA -->
                <div class="col-lg-8">
                    <!-- CAR & RESERVATION SUMMARY CARD -->
                    <div class="card-custom p-4 mb-4">
                        <div class="d-flex align-items-center mb-4 flex-wrap gap-3">
                            <div class="car-image-container me-md-2">
                                <img src="/car-rental/public/assets/images/cars/<?= htmlspecialchars($first_image) ?>" 
                                     style="width: 140px; height: 90px; object-fit: cover;" 
                                     alt="Car Image">
                            </div>
                            <div>
                                <h3 class="fw-bold mb-1" style="font-size: 1.4rem;">
                                    <?= htmlspecialchars($booking['brand']) ?> <?= htmlspecialchars($booking['model']) ?>
                                </h3>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-warning text-dark fw-bold px-2 py-1">
                                        <i class="bi bi-card-text me-1"></i><?= htmlspecialchars($booking['plate_number']) ?>
                                    </span>
                                    <span class="text-muted small">Booking ID: #<?= $booking['id'] ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- DATES & PRICING METRICS TILE -->
                        <div class="row text-center info-tile p-3 g-3 m-0">
                            <div class="col-6 col-md-3">
                                <small class="text-muted d-block mb-1">Pick-up Date</small>
                                <span class="fw-bold"><?= date('M d, Y', strtotime($booking['start_date'])) ?></span>
                            </div>
                            <div class="col-6 col-md-3">
                                <small class="text-muted d-block mb-1">Return Date</small>
                                <span class="fw-bold"><?= date('M d, Y', strtotime($booking['end_date'])) ?></span>
                            </div>
                            <div class="col-6 col-md-3">
                                <small class="text-muted d-block mb-1">Total Duration</small>
                                <span class="fw-bold">
                                    <?php 
                                        $start = new DateTime($booking['start_date']);
                                        $end = new DateTime($booking['end_date']);
                                        echo $start->diff($end)->days + 1; 
                                    ?> Days
                                </span>
                            </div>
                            <div class="col-6 col-md-3">
                                <small class="text-muted d-block mb-1">Daily Rate</small>
                                <span class="fw-bold text-warning">₱<?= number_format($booking['price_per_day'], 0) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- CUSTOMER INFORMATION CARD -->
                    <div class="card-custom p-4">
                        <h5 class="fw-bold mb-4 d-flex align-items-center">
                            <i class="bi bi-person-vcard text-warning fs-4 me-2"></i> Customer Information
                        </h5>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small text-muted mb-1">Full Name</label>
                                <p class="fw-semibold mb-0"><?= htmlspecialchars($displayName) ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="small text-muted mb-1">Phone Number</label>
                                <p class="fw-semibold mb-0"><?= htmlspecialchars($displayPhone) ?></p>
                            </div>
                            <div class="col-md-12">
                                <label class="small text-muted mb-1">Email Address</label>
                                <p class="fw-semibold mb-0"><?= htmlspecialchars($displayEmail) ?></p>
                            </div>
                            <?php if($user_role !== 'user'): ?>
                            <div class="col-12">
                                <hr class="my-2 border-light-subtle">
                                <label class="small text-muted mb-1">Vehicle Operator (Owner)</label>
                                <p class="fw-semibold mb-0 text-warning"><?= htmlspecialchars($booking['operator_name']) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- SIDEBAR ACTION PANEL -->
                <div class="col-lg-4">
                    <!-- TOTAL PRICE DISPLAY -->
                    <div class="card-custom p-4 text-center mb-4">
                        <small class="text-uppercase tracking-wider fw-bold text-warning d-block mb-1" style="letter-spacing: 0.5px; font-size: 0.8rem;">Total Amount Due</small>
                        <h3 class="fw-bold mb-0">₱<?= number_format($booking['total_price'], 2) ?></h3>
                    </div>

                    <!-- MANAGEMENT CONTROLS (OPERATOR / ADMIN ONLY) -->
                    <?php if($user_role !== 'user'): ?>
                    <div class="card-custom p-4 mb-3">
                        <h6 class="fw-bold mb-3 d-flex align-items-center">
                            <i class="bi bi-sliders text-warning me-2"></i> Update Booking Status
                        </h6>
                        <form action="process/update_booking.php" method="POST">
                            <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                            <div class="mb-3">
                                <select name="status" class="form-select form-select-custom">
                                    <option value="Pending" <?= $booking['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Approved" <?= $booking['status'] == 'Approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="Completed" <?= $booking['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="Cancelled" <?= $booking['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary-yellow w-100 py-2 rounded-3">
                                <i class="bi bi-check-circle-fill me-1"></i> Save Changes
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- PRINT INVOICE ACTION -->
                    <div>
                        <button onclick="window.print()" class="btn btn-secondary-custom w-100 py-2 rounded-3">
                            <i class="bi bi-printer me-2"></i> Print Invoice
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php require_once __DIR__ . '/../components/footer.php'; ?>
    </div>
</div>