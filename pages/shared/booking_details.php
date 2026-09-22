<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: ../../index.php");
    exit();
}

// Helper function for asset paths - FIXED FOR INFINITYFREE
function asset($path) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    
    $isInfinityFree = (strpos($host, 'rf.gd') !== false || 
                       strpos($host, 'infinityfreeapp.com') !== false || 
                       strpos($host, 'infinityfree.net') !== false ||
                       strpos($host, 'epizy.com') !== false);
    
    if ($isInfinityFree) {
        $baseUrl = $protocol . $host . '/';
    } else {
        $baseUrl = $protocol . $host . '/car-rental/';
    }
    
    $path = ltrim($path, '/');
    return $baseUrl . $path;
}

$booking_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];

// ── Fetch Booking Details (using prepared statement) ──
$sql = "SELECT b.*, 
               c.brand, c.model, c.plate_number, c.image_path AS car_image_path, 
               c.price_24_hours AS price_per_day, 
               c.operator_24_hours AS extension_price,
               u.name as registered_name, u.email as registered_email, u.phone as registered_phone,
               owner.name as operator_name
        FROM bookings b
        JOIN cars c ON b.car_id = c.id
        LEFT JOIN users u ON b.user_id = u.id 
        JOIN users owner ON c.user_id = owner.id
        WHERE b.id = ?";

$params = [$booking_id];
$types  = 'i';

if ($user_role === 'operator') {
    $sql .= " AND c.user_id = ?";
    $params[] = $user_id;
    $types   .= 'i';
} elseif ($user_role === 'staff') {
    $branch_id = (int)($_SESSION['branch_id'] ?? 0);
    if ($branch_id > 0) {
        $sql .= " AND b.branch_id = ?";
        $params[] = $branch_id;
        $types   .= 'i';
    } else {
        $sql .= " AND 1=0";
    }
} elseif ($user_role === 'user') {
    $sql .= " AND b.user_id = ?";
    $params[] = $user_id;
    $types   .= 'i';
}
// admin → no additional filter (see all bookings, matches calendar scope)

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Booking not found or access denied.</div></div>");
}

// ── Fix times: combine date + time columns ──
$pickupTimeVal = !empty($booking['pickup_time']) ? $booking['pickup_time'] : '00:00:00';
$returnTimeVal = !empty($booking['return_time']) ? $booking['return_time'] : '00:00:00';

$pickupFullStr = $booking['start_date'] . ' ' . $pickupTimeVal;
$returnFullStr = $booking['end_date']   . ' ' . $returnTimeVal;

$pickupTs = strtotime($pickupFullStr);
$returnTs = strtotime($returnFullStr);

if (!$returnTs || $returnTs <= $pickupTs) {
    // Fallback if times are malformed
    $returnTs = strtotime($booking['end_date'] . ' 23:59:59');
}

$total_seconds = $returnTs - $pickupTs;
$total_hours   = $total_seconds / 3600;
$days_count    = floor($total_hours / 24);
$extra_hours   = $total_hours - ($days_count * 24);

// ── Fetch Customer Photos ──
$photos_stmt = $conn->prepare("SELECT * FROM booking_photos WHERE booking_id = ? ORDER BY uploaded_at DESC");
$photos_stmt->bind_param('i', $booking_id);
$photos_stmt->execute();
$photos_result = $photos_stmt->get_result();
$customer_photos = $photos_result ? $photos_result->fetch_all(MYSQLI_ASSOC) : [];
$photos_stmt->close();

// ── Fetch Customer Remarks ──
$remarks_stmt = $conn->prepare("SELECT r.*, u.name as author_name FROM booking_remarks r LEFT JOIN users u ON r.created_by = u.id WHERE r.booking_id = ? ORDER BY r.created_at DESC");
$remarks_stmt->bind_param('i', $booking_id);
$remarks_stmt->execute();
$remarks_result = $remarks_stmt->get_result();
$customer_remarks = $remarks_result ? $remarks_result->fetch_all(MYSQLI_ASSOC) : [];
$remarks_stmt->close();

// ── Display name/phone/email priority ──
$displayName  = !empty($booking['registered_name']) ? $booking['registered_name'] : $booking['guest_name'];
$displayPhone = !empty($booking['registered_phone']) ? $booking['registered_phone'] : ($booking['phone_number'] ?? 'N/A');
$displayEmail = !empty($booking['registered_email']) ? $booking['registered_email'] : ($booking['gmail'] ?? 'N/A');

// ── Price breakdown ──
$basePrice      = (float)($booking['base_price'] ?? $booking['total_price'] ?? 0);
$deliveryFee    = (float)($booking['delivery_fee'] ?? 0);
$pickupFee      = (float)($booking['pickup_fee'] ?? 0);
$discountAmount = (float)($booking['discount_price'] ?? 0);
$downPayment    = (float)($booking['down_payment'] ?? 0);
$extensionFee   = (float)($booking['extension_price'] ?? 0);
$extensionHours = (int)($booking['extension_hours'] ?? 0);
$totalRental    = $basePrice + $deliveryFee + $pickupFee + $extensionFee - $discountAmount;
$remainingBal   = (float)($booking['total_price'] ?? 0);

$pageTitle = "Booking Details #" . $booking['id'];
require_once __DIR__ . '/../components/head.php';

$car_images = !empty($booking['car_image_path']) ? explode(',', $booking['car_image_path']) : [];
$first_car_image = !empty($car_images[0]) ? trim($car_images[0]) : 'default.png';
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

    .status-pill {
        padding: 6px 16px;
        border-radius: 50rem;
        font-weight: 700;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .upload-dropzone {
        border: 2px dashed #ffcc00;
        background-color: #121212;
        border-radius: 1rem;
        padding: 2rem 1.5rem;
        text-align: center;
        cursor: pointer;
        position: relative;
        transition: all 0.3s ease;
    }
    .upload-dropzone:hover {
        background-color: #1a1a1a;
        border-color: #ffe066;
    }
    .upload-dropzone input[type="file"] {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        opacity: 0;
        cursor: pointer;
    }
    .upload-dropzone .upload-icon-box {
        width: 48px;
        height: 48px;
        border: 2px solid #ffcc00;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.75rem;
        color: #ffcc00;
        font-size: 1.5rem;
    }
    .upload-dropzone .upload-title {
        color: #ffffff;
        font-weight: 700;
        font-size: 1.05rem;
        margin-bottom: 0.25rem;
    }
    .upload-dropzone .upload-subtitle {
        color: #6c757d;
        font-size: 0.85rem;
    }

    .remark-item {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 12px 16px;
        position: relative;
    }

    .cust-photo-card {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #333;
        height: 100px;
    }
    .cust-photo-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .btn-delete-badge {
        position: absolute;
        top: 6px;
        right: 6px;
        z-index: 10;
        width: 24px;
        height: 24px;
        padding: 0;
        border-radius: 50%;
        background-color: #dc3545;
        color: #fff;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }
    .btn-delete-badge:hover {
        background-color: #bb2d3b;
    }

    /* Price breakdown */
    .price-breakdown {
        background-color: #f8fafc;
        border-radius: 0.75rem;
        padding: 1rem 1.15rem;
    }
    .price-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 0;
        font-size: 0.88rem;
    }
    .price-row.divider {
        border-top: 1px dashed #cbd5e1;
        margin: 6px 0;
        padding: 0;
    }
    .price-row.total {
        font-weight: 800;
        font-size: 1.05rem;
        color: #b38a00;
        padding-top: 6px;
    }
    body.dark-mode .price-breakdown {
        background-color: #1a1a1a;
    }
    body.dark-mode .price-row.divider {
        border-top-color: #3f3f46;
    }
    body.dark-mode .price-row.total {
        color: #ffd700;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ── Dark mode ── */
    body.dark-mode {
        background-color: var(--brand-black) !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .main-wrapper,
    body.dark-mode .main-content {
        background-color: var(--brand-black) !important;
    }
    body.dark-mode header,
    body.dark-mode .header,
    body.dark-mode .topbar,
    body.dark-mode .navbar {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #ffffff !important;
    }
    body.dark-mode .card-custom {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .info-tile,
    body.dark-mode .remark-item {
        background-color: #1a1a1a !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .btn-secondary-custom {
        background-color: #1a1a1a !important;
        border-color: var(--brand-border-dark) !important;
        color: #ffffff !important;
    }
    body.dark-mode .form-select-custom,
    body.dark-mode .form-control-custom {
        background-color: #0d0d0d !important;
        border-color: var(--brand-border-dark) !important;
        color: #ffffff !important;
    }
    body.dark-mode .text-muted { color: #cbd5e1 !important; }
    body.dark-mode .border-light-subtle { border-color: var(--brand-border-dark) !important; }
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

                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <?php 
                        // Booking type badge
                        $bType = $booking['booking_type'] ?? 'online';
                    ?>
                    <span class="badge <?= $bType === 'manual' ? 'bg-secondary-subtle text-secondary' : 'bg-info-subtle text-info' ?> fw-bold px-3 py-2" style="font-size: 0.75rem;">
                        <i class="bi <?= $bType === 'manual' ? 'bi-pencil-square' : 'bi-globe' ?> me-1"></i>
                        <?= $bType === 'manual' ? 'Walk-in / Manual' : 'Online Booking' ?>
                    </span>

                    <?php 
                        $badgeClasses = match($booking['status']) {
                            'Confirmed' => 'bg-info-subtle text-info border border-info-subtle',
                            'Pending'   => 'bg-warning-subtle text-dark border border-warning-subtle',
                            'Completed' => 'bg-success-subtle text-success border border-success-subtle',
                            'Cancelled' => 'bg-danger-subtle text-danger border border-danger-subtle',
                            default     => 'bg-secondary-subtle text-secondary border border-secondary-subtle'
                        };
                        $statusLabel = $booking['status'] === 'Confirmed' ? 'Released' : $booking['status'];
                    ?>
                    <span class="status-pill <?= $badgeClasses ?>">
                        <i class="bi bi-circle-fill fs-6"></i> Status: <?= htmlspecialchars($statusLabel) ?>
                    </span>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <!-- CAR SUMMARY CARD -->
                    <div class="card-custom p-4 mb-4">
                        <div class="d-flex align-items-center mb-4 flex-wrap gap-3">
                            <div class="car-image-container me-md-2">
                                <?php 
                                $carImagePath = asset('public/assets/images/cars/' . $first_car_image);
                                ?>
                                <img src="<?= $carImagePath ?>" 
                                     style="width: 140px; height: 90px; object-fit: cover;" 
                                     alt="Car Image"
                                     onerror="this.src='<?= asset('public/assets/images/cars/default.png') ?>'">
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

                         <!-- Schedule (uses proper date + time columns) -->
                        <div class="row text-center info-tile p-3 g-3 m-0">
                            <div class="col-6 col-md-3">
                                <small class="text-muted d-block mb-1">Pick-up</small>
                                <span class="fw-bold d-block"><?= date('M d, Y', $pickupTs) ?></span>
                                <span class="fw-bold text-warning" style="font-size: 0.9rem;">
                                    <?= date('H:i', $pickupTs) ?> <span class="text-muted fw-normal">(<?= date('g:i A', $pickupTs) ?>)</span>
                                </span>
                            </div>
                            <div class="col-6 col-md-3">
                                <small class="text-muted d-block mb-1">Return</small>
                                <span class="fw-bold d-block"><?= date('M d, Y', $returnTs) ?></span>
                                <span class="fw-bold text-warning" style="font-size: 0.9rem;">
                                    <?= date('H:i', $returnTs) ?> <span class="text-muted fw-normal">(<?= date('g:i A', $returnTs) ?>)</span>
                                </span>
                            </div>
                            <div class="col-6 col-md-3">
                                <small class="text-muted d-block mb-1">Total Duration</small>
                                <span class="fw-bold">
                                    <?php 
                                        if ($days_count > 0) {
                                            echo $days_count . 'd ';
                                        }
                                        echo number_format($extra_hours, 1) . 'h';
                                    ?>
                                </span>
                            </div>
                            <div class="col-6 col-md-3">
                                <small class="text-muted d-block mb-1">Daily Rate</small>
                                <span class="fw-bold text-warning">₱<?= number_format($booking['price_per_day'], 0) ?></span>
                            </div>
                        </div>

                        <?php if ($extensionHours > 0): ?>
                        <div class="mt-3 p-3 rounded-3" style="background: #fff7d1; border: 1px solid #ffd700;">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-clock-history text-warning fs-5"></i>
                                <div>
                                    <div class="fw-bold small">Extension Applied</div>
                                    <div class="small text-muted"><?= $extensionHours ?> extra hour<?= $extensionHours > 1 ? 's' : '' ?> — ₱<?= number_format($extensionFee, 2) ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
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

                            <!-- CUSTOMER DOCUMENTS -->
                            <div class="col-12">
                                <hr class="my-2 border-light-subtle">
                                <label class="small text-muted mb-3 d-block fw-semibold">Customer Documents & Verification Photos</label>
                                
                                <?php if (!empty($customer_photos)): ?>
                                    <div class="row g-2 mb-3">
                                        <?php foreach ($customer_photos as $photo): 
                                            $filePath = asset('public/assets/images/customers/' . $photo['file_name']);
                                            $ext = strtolower(pathinfo($photo['file_name'], PATHINFO_EXTENSION));
                                            $isPdf = ($ext === 'pdf');
                                        ?>
                                            <div class="col-4 col-sm-3">
                                                <div class="cust-photo-card border rounded-3 overflow-hidden shadow-sm bg-dark text-center">
                                                    <?php if ($user_role !== 'user'): ?>
                                                    <form action="process/delete_customer_item.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this document?');">
                                                        <input type="hidden" name="type" value="photo">
                                                        <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                                        <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                        <input type="hidden" name="file_name" value="<?= htmlspecialchars($photo['file_name']) ?>">
                                                        <button type="submit" class="btn-delete-badge" title="Delete file">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>

                                                    <a href="<?= $filePath ?>" target="_blank" class="d-flex flex-column align-items-center justify-content-center h-100 text-decoration-none p-2">
                                                        <?php if ($isPdf): ?>
                                                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-1 mb-1"></i>
                                                            <span class="text-light small text-truncate w-100" style="font-size: 0.75rem;">
                                                                <?= htmlspecialchars($photo['file_name']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <img src="<?= $filePath ?>" alt="Customer Document" class="w-100 h-100 object-fit-cover" onerror="this.style.display='none'">
                                                        <?php endif; ?>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted small fst-italic mb-3">No documents uploaded yet.</p>
                                <?php endif; ?>
                            </div>

                            <!-- REMARKS -->
                            <div class="col-12">
                                <hr class="my-2 border-light-subtle">
                                <label class="small text-muted mb-2 d-block fw-semibold">Customer Remarks & Notes History</label>

                                <?php if (!empty($customer_remarks)): ?>
                                    <div class="d-flex flex-column gap-2 mb-3">
                                        <?php foreach ($customer_remarks as $remark): ?>
                                            <div class="remark-item">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <small class="fw-bold text-warning"><?= htmlspecialchars($remark['author_name'] ?? 'System / User') ?></small>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <small class="text-muted" style="font-size: 0.75rem;"><?= date('M d, Y h:i A', strtotime($remark['created_at'])) ?></small>
                                                        
                                                        <?php if ($user_role !== 'user'): ?>
                                                        <form action="process/delete_customer_item.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this note?');" class="d-inline">
                                                            <input type="hidden" name="type" value="remark">
                                                            <input type="hidden" name="remark_id" value="<?= $remark['id'] ?>">
                                                            <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                            <button type="submit" class="btn btn-link text-danger p-0 border-0 ms-1" style="font-size: 0.85rem;" title="Delete note">
                                                                <i class="bi bi-trash-fill"></i>
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <p class="mb-0 small"><?= nl2br(htmlspecialchars($remark['remark'])) ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted small fst-italic mb-3">No remarks recorded yet.</p>
                                <?php endif; ?>
                            </div>

                            <!-- UPLOAD FORM -->
                            <form action="process/update_customer_info.php" method="POST" enctype="multipart/form-data" class="col-12 g-3 row m-0 p-0">
                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">

                                <div class="col-12 p-0 mb-3">
                                    <div class="upload-dropzone">
                                        <input type="file" name="customer_photos[]" id="customer_photos" multiple accept="image/*,.pdf" onchange="updateFileLabel(this)">
                                        <div class="upload-icon-box mx-auto">
                                            <i class="bi bi-cloud-arrow-up-fill"></i>
                                        </div>
                                        <div class="upload-title" id="upload-title-text">Upload Primary ID / Photos</div>
                                        <div class="upload-subtitle" id="upload-subtitle-text">JPG, PNG, or PDF (Multiple allowed)</div>
                                    </div>
                                </div>

                                <div class="col-12 p-0">
                                    <label class="small text-muted mb-1 fw-semibold">Add New Remark / Note</label>
                                    <textarea name="new_remark" class="form-control form-control-custom" rows="3" placeholder="Type new remark or notes here..."></textarea>
                                </div>

                                <div class="col-12 text-end mt-3 p-0">
                                    <button type="submit" class="btn btn-primary-yellow px-4 py-2 rounded-3">
                                        <i class="bi bi-save me-1"></i> Save Customer Updates
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- SIDEBAR ACTION PANEL -->
                <div class="col-lg-4">
                    <!-- PRICE BREAKDOWN -->
                    <div class="card-custom p-4 mb-4">
                        <h6 class="fw-bold mb-3 d-flex align-items-center">
                            <i class="bi bi-cash-stack text-warning me-2"></i> Payment Breakdown
                        </h6>

                        <div class="price-breakdown">
                            <div class="price-row">
                                <span>Base Rental</span>
                                <span class="fw-semibold">₱<?= number_format($basePrice, 2) ?></span>
                            </div>

                            <?php if ($deliveryFee > 0): ?>
                            <div class="price-row">
                                <span>Delivery Fee</span>
                                <span class="fw-semibold">+ ₱<?= number_format($deliveryFee, 2) ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($pickupFee > 0): ?>
                            <div class="price-row">
                                <span>Pickup Fee</span>
                                <span class="fw-semibold">+ ₱<?= number_format($pickupFee, 2) ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($extensionFee > 0): ?>
                            <div class="price-row">
                                <span>Extension Fee</span>
                                <span class="fw-semibold">+ ₱<?= number_format($extensionFee, 2) ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ($discountAmount > 0): ?>
                            <div class="price-row" style="color: #dc3545;">
                                <span>Discount</span>
                                <span class="fw-semibold">- ₱<?= number_format($discountAmount, 2) ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="price-row divider"></div>
                            <div class="price-row">
                                <span class="fw-bold">Total Rental</span>
                                <span class="fw-bold">₱<?= number_format($totalRental, 2) ?></span>
                            </div>

                            <?php if ($downPayment > 0): ?>
                            <div class="price-row" style="color: #198754;">
                                <span>Down Payment</span>
                                <span class="fw-semibold">- ₱<?= number_format($downPayment, 2) ?></span>
                            </div>
                            <div class="price-row divider"></div>
                            <?php endif; ?>

                            <div class="price-row total">
                                <span>Remaining Balance</span>
                                <span>₱<?= number_format($remainingBal, 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- BOOKING REMARKS (from bookings table) -->
                    <?php if (!empty($booking['remarks'])): ?>
                    <div class="card-custom p-4 mb-3">
                        <h6 class="fw-bold mb-2 d-flex align-items-center">
                            <i class="bi bi-chat-left-text text-warning me-2"></i> Booking Remarks
                        </h6>
                        <p class="small mb-0"><?= nl2br(htmlspecialchars($booking['remarks'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- STATUS UPDATE -->
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
                                    <option value="Confirmed" <?= $booking['status'] == 'Confirmed' ? 'selected' : '' ?>>Released</option>
                                    <option value="Completed" <?= $booking['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="Cancelled" <?= $booking['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary-yellow w-100 py-2 rounded-3">
                                <i class="bi bi-check-circle-fill me-1"></i> Save Status
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

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

<script>
function updateFileLabel(input) {
    const titleText = document.getElementById('upload-title-text');
    const subtitleText = document.getElementById('upload-subtitle-text');
    if (input.files && input.files.length > 0) {
        titleText.innerText = `${input.files.length} File(s) Selected`;
        subtitleText.innerText = Array.from(input.files).map(f => f.name).join(', ');
    } else {
        titleText.innerText = 'Upload Primary ID / Photos';
        subtitleText.innerText = 'JPG, PNG, or PDF (Multiple allowed)';
    }
}
</script>