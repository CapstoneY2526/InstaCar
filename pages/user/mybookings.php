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
$active_query = mysqli_query($conn, "SELECT COUNT(id) as total FROM bookings WHERE user_id = '$user_id' AND status = 'Approved'");
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
    $query_sql .= " AND b.status = '$filter_status'";
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
    body { 
        background-color: #f8fafc; 
        font-family: 'Poppins', sans-serif;
    }

    .main-content { min-height: 100vh; }
    
    /* ========================================================
       UNIFIED DASHBOARD STATS CARD ENGINE (CLEAN & SPACIOUS)
    ======================================================== */
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 1.25rem 1.5rem;
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
        height: 100%;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
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
        letter-spacing: 0.6px;
        color: #64748b;
        font-weight: 600;
        margin-top: 3px;
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
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        display: inline-block;
    }
    .status-pending { background: #fef3c7; color: #d97706; }
    .status-approved { background: #e0f2fe; color: #0369a1; }
    .status-completed { background: #d1fae5; color: #059669; }
    .status-cancelled { background: #fee2e2; color: #dc2626; }

    /* Mobile view transformations */
    .mobile-card-container { display: none; }

    @media (max-width: 768px) {
        body { font-size: 0.85rem; }
        h3 { font-size: 1.25rem; }
        .p-4 { padding: 0.85rem !important; }
        .desktop-table-card { display: none !important; }
        .mobile-card-container { display: block; }
        .responsive-mobile-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            border: 1px solid #eef2f6;
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
                        <h3 class="fw-bold mb-0">My Bookings</h3>
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
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search vehicle brand, model..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-6 col-sm-3 col-md-3">
                            <select name="status" class="form-select">
                                <option value="All" <?= $filter_status == 'All' ? 'selected' : '' ?>>All Statuses</option>
                                <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Approved" <?= $filter_status == 'Approved' ? 'selected' : '' ?>>Approved</option>
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

                <div class="card border-0 shadow-sm rounded-4 overflow-hidden desktop-table-card mb-4">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="border-0 px-4 py-3">Booking info</th>
                                        <th class="border-0 py-3">Vehicle details</th>
                                        <th class="border-0 py-3">Rental window</th>
                                        <th class="border-0 py-3">Grand total</th>
                                        <th class="border-0 py-3 text-center">Status</th>
                                        <th class="border-0 py-3 text-center">Review</th>
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
                                                <td class="py-3 align-middle fw-bold text-primary">₱<?= number_format($b['total_price'], 2) ?></td>
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
                                            <td colspan="6" class="text-center py-5 text-muted">No reservations match your criteria.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mobile-card-container mb-4">
                    <?php if (!empty($bookings)): ?>
                        <?php foreach ($bookings as $b): ?>
                            <div class="responsive-mobile-card shadow-sm">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">#BK-<?= $b['id'] ?></div>
                                        <small class="text-muted">Placed: <?= date('M d, Y', strtotime($b['created_at'])) ?></small>
                                    </div>
                                    <span class="status-badge status-<?= strtolower($b['status']) ?>">
                                        <?= $b['status'] ?>
                                    </span>
                                </div>
                                <div class="border-top border-bottom py-2 my-2" style="background: #fafbfc; margin-left: -16px; margin-right: -16px; padding-left: 16px; padding-right: 16px;">
                                    <div class="small fw-semibold text-dark"><i class="bi bi-car-front me-2 text-secondary"></i><?= htmlspecialchars($b['brand'] . ' ' . $b['model']) ?></div>
                                    <div class="text-muted extra-small" style="font-size: 0.75rem; padding-left: 22px;">Plate: <code><?= htmlspecialchars($b['plate_number']) ?></code></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center pt-1">
                                    <div class="text-muted small">
                                        <i class="bi bi-calendar3 me-1"></i> <?= date('M d', strtotime($b['start_date'])) ?> - <?= date('M d', strtotime($b['end_date'])) ?>
                                    </div>
                                    <div class="fw-bold text-primary" style="font-size: 1rem;">
                                        ₱<?= number_format($b['total_price'], 2) ?>
                                    </div>
                                </div>
                                <?php if ($b['status'] === 'Completed'): ?>
                                    <div class="mt-3 pt-2 border-top text-end">
                                        <button class="btn btn-sm btn-outline-primary rounded-3 px-3 edit-review-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#reviewModal"
                                                data-booking-id="<?= $b['id'] ?>"
                                                data-rating="<?= htmlspecialchars($b['rating'] ?? '5') ?>"
                                                data-title="<?= htmlspecialchars($b['review_title'] ?? '') ?>"
                                                data-text="<?= htmlspecialchars($b['review_text'] ?? '') ?>"
                                                data-admin-reply="<?= htmlspecialchars($b['admin_reply'] ?? '') ?>">
                                            <i class="bi bi-star-fill me-1"></i> <?= !empty($b['rating']) ? 'Edit' : 'Rate' ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
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
                    <!-- NOTIFICATION ALERT BOX -->
                    <div id="modal_alert" class="alert alert-dismissible fade show d-none mb-3" role="alert"></div>
                    <input type="hidden" id="modal_booking_id" name="booking_id">
                    
                    <!-- Notification Container inside modal -->
                    <div id="modal_alert" class="alert alert-dismissible fade show d-none" role="alert"></div>

                    <!-- ADMIN REPLY CONTAINER -->
                    <div id="admin_reply_wrapper" class="mb-4 p-3 rounded-3 border" style="display: none; background-color: #f0f9ff; border-color: #bae6fd !important;">
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

    let activeBtn = null;

    // 1. Reset Alert Box when Modal opens
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.edit-review-btn');
        if (!btn) return;

        activeBtn = btn;

        // Hide alert message when opening modal for a new selection
        const alertBox = document.getElementById('modal_alert');
        if (alertBox) {
            alertBox.classList.add('d-none');
            alertBox.innerText = '';
        }

        // Fill modal values from dataset
        document.getElementById('modal_booking_id').value   = btn.dataset.bookingId || '';
        document.getElementById('modal_rating').value       = btn.dataset.rating || '5';
        document.getElementById('modal_review_title').value = btn.dataset.title || '';
        document.getElementById('modal_review_text').value  = btn.dataset.text || '';

        // Handle Admin Reply visibility
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

    // 2. Form Submission with Dynamic Alert Notification
    const form = document.getElementById('reviewForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submit_review_btn');
            const alertBox  = document.getElementById('modal_alert');

            // Disable button & set spinner
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
                    // SHOW SUCCESS NOTIFICATION
                    alertBox.className = 'alert alert-success alert-dismissible fade show mb-3';
                    alertBox.innerText = 'Review saved successfully! Refreshing...';
                    alertBox.classList.remove('d-none');

                    // Update action button state dynamically
                    if (activeBtn) {
                        activeBtn.dataset.rating = formData.get('rating');
                        activeBtn.dataset.title  = formData.get('review_title');
                        activeBtn.dataset.text   = formData.get('review_text');
                        activeBtn.innerHTML      = '<i class="bi bi-star-fill me-1"></i> Edit Review';
                    }

                    // Refresh page after a brief pause so user sees the notification
                    setTimeout(() => {
                        location.reload();
                    }, 1200);

                } else {
                    // SHOW ERROR NOTIFICATION
                    alertBox.className = 'alert alert-danger alert-dismissible fade show mb-3';
                    alertBox.innerText = data.message || 'Failed to save review.';
                    alertBox.classList.remove('d-none');

                    submitBtn.disabled = false;
                    submitBtn.innerText = 'Submit Review';
                }
            })
            .catch(err => {
                console.error('AJAX Error:', err);
                // SHOW CONNECTION ERROR NOTIFICATION
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