<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

$filter_rating = $_GET['rating'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search_query = $_GET['search'] ?? '';

// Stats
$res_total = mysqli_query($conn, "SELECT COUNT(id) as total FROM reviews");
$total_reviews = mysqli_fetch_assoc($res_total)['total'] ?? 0;

$res_pending = mysqli_query($conn, "SELECT COUNT(id) as total FROM reviews WHERE admin_reply IS NULL OR admin_reply = ''");
$pending_reviews = mysqli_fetch_assoc($res_pending)['total'] ?? 0;

$res_avg = mysqli_query($conn, "SELECT AVG(rating) as average FROM reviews");
$avg_rating = number_format(mysqli_fetch_assoc($res_avg)['average'] ?? 0, 1);

// Query Logic
$where_clauses = [];
if ($filter_rating !== '') $where_clauses[] = "r.rating = " . intval($filter_rating);

if ($search_query !== '') {
    $s = mysqli_real_escape_string($conn, $search_query);
    $where_clauses[] = "(u.name LIKE '%$s%' OR c.brand LIKE '%$s%' OR c.model LIKE '%$s%' OR c.plate_number LIKE '%$s%')";
}

if ($filter_status !== '') {
    if ($filter_status == 'pending') $where_clauses[] = "(r.admin_reply IS NULL OR r.admin_reply = '')";
    elseif ($filter_status == 'Replied') $where_clauses[] = "(r.admin_reply IS NOT NULL AND r.admin_reply != '')";
}
$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$reviews_query = "
    SELECT r.*, u.name as customer_name, c.brand, c.model, c.plate_number
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN bookings b ON r.booking_id = b.id
    JOIN cars c ON b.car_id = c.id
    $where_sql
    ORDER BY r.created_at DESC
";
$reviews_res = mysqli_query($conn, $reviews_query);
$all_reviews = mysqli_fetch_all($reviews_res, MYSQLI_ASSOC);

$pageTitle = 'Review Management';
require_once __DIR__ . '/../components/head.php'; 
?>

<style>
    body { background-color: #f8fafc; overflow-x: hidden; }
    .main-wrapper { 
        margin-left: 260px;
        width: calc(100% - 260px);
        min-height: 100vh;
        transition: all 0.3s;
    }
    @media (max-width: 992px) {
        .main-wrapper { margin-left: 0; width: 100%; }
    }
    .table-card { border: none; border-radius: 15px; background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .status-badge { padding: 5px 14px; border-radius: 50px; font-size: 13px; font-weight: 700; display: inline-block; }
    .status-pending { background: rgba(255, 140, 0, 0.1); color: #ff8c00; }
    .status-replied { background: rgba(12, 166, 120, 0.1); color: #0ca678; }
    .reply-box { background: #f1f5f9; border-left: 4px solid #0d6efd; padding: 12px; margin-top: 10px; border-radius: 8px; font-size: 14px; color: #334155; }
    .rating-stars { color: #ffc107; font-size: 14px; letter-spacing: 1px; }

    /* Custom Input Styles to Match Elite Interface Layouts */
    .form-control-custom {
        background-color: #f1f5f9 !important;
        border: 1px solid transparent !important;
        padding: 10px 14px;
        font-size: 14px;
        border-radius: 10px;
        transition: all 0.2s ease;
    }
    .form-control-custom:focus {
        background-color: #fff !important;
        border-color: #0d6efd !important;
        box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.15) !important;
    }

    /* Modern Light Aesthetic Modal Fixes */
    .modal-content-custom {
        background-color: #ffffff !important;
        border: none !important;
        border-radius: 20px !important;
        box-shadow: 0 20px 40px rgba(0,0,0,0.12) !important;
    }
    .modal-header-custom {
        border-bottom: 1px solid #e2e8f0 !important;
        padding: 20px 24px !important;
    }
    .modal-body-custom {
        padding: 24px !important;
    }
    .modal-footer-custom {
        border-top: 1px solid #e2e8f0 !important;
        padding: 16px 24px !important;
    }

    /* ==========================================
       📱 CARD OVERRIDES FOR MOBILE VIEWS
    ========================================== */
    @media (max-width: 768px) {

    .table-card {
        background: transparent !important;
        box-shadow: none !important;
    }

    .table-responsive {
        overflow-x: visible !important;
    }

    .table-responsive table,
    .table-responsive thead,
    .table-responsive tbody,
    .table-responsive th,
    .table-responsive td,
    .table-responsive tr {
        display: block;
        width: 100%;
    }

    .table-responsive thead {
        position: absolute;
        top: -9999px;
        left: -9999px;
    }

    .table-responsive tr {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        margin-bottom: 16px;
        padding: 16px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.03);
    }

    .table-responsive td {
        border: none !important;
        padding: 8px 0;
        width: 100%;
        text-align: left !important;
        white-space: normal;
        position: relative;
    }

    .table-responsive td::before {
        content: attr(data-label);
        display: block;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #94a3b8;
        margin-bottom: 4px;
    }

    /* 🔧 FIX: Prevent reply overlap inside Feedback column */
    td[data-label="Feedback"] {
        min-width: 0 !important;
        overflow: hidden;
    }

    td[data-label="Feedback"] .reply-box {
        width: 100% !important;
        margin-top: 10px;
        box-sizing: border-box;
        word-break: break-word;
    }

    /* 🔧 FIX: stop Status + Action from fighting layout */
    td[data-label="Status"],
    td[data-label="Action"] {
        width: 100% !important;
        display: block !important;
        text-align: left !important;
    }

    .reply-button {
        width: 100% !important;
    }
}
</style>

<div class="dashboard-container">
    <div class="sidebar-wrapper">
        <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
    </div>

    <div class="main-wrapper">
        <?php require_once __DIR__ . '/../components/header.php'; ?>

        <div class="p-4">
            <div class="mb-4">
                <h3 class="fw-bold mb-0">Review Management</h3>
                <p class="text-muted small">Monitor and respond to customer feedback</p>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4">
                    <div class="card border-0 shadow-sm p-3 rounded-4 h-100">
                        <small class="text-muted fw-bold d-block mb-1" style="font-size: 11px; letter-spacing: 0.5px;">TOTAL REVIEWS</small>
                        <h3 class="fw-bold mb-0" style="color: #1e293b;"><?= $total_reviews ?></h3>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="card border-0 shadow-sm p-3 rounded-4 h-100">
                        <small class="text-muted fw-bold d-block mb-1" style="font-size: 11px; letter-spacing: 0.5px;">AVERAGE RATING</small>
                        <h3 class="fw-bold mb-0" style="color: #ffc107;"><?= $avg_rating ?> <span class="fs-4">★</span></h3>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card border-0 shadow-sm p-3 rounded-4 h-100">
                        <small class="text-muted fw-bold d-block mb-1" style="font-size: 11px; letter-spacing: 0.5px;">PENDING REPLIES</small>
                        <h3 class="fw-bold mb-0 text-warning"><?= $pending_reviews ?></h3>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm p-3 mb-4 rounded-4">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="small fw-bold text-muted mb-1">SEARCH FEEDBACK</label>
                        <input type="text" name="search" class="form-control form-control-custom"
                            placeholder="Customer, car, plate number..."
                            value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="small fw-bold text-muted mb-1">RATING</label>
                        <select name="rating" class="form-select form-control-custom">
                            <option value="">All Ratings</option>
                            <?php for($i=5; $i>=1; $i--): ?>
                                <option value="<?= $i ?>" <?= $filter_rating == $i ? 'selected' : '' ?>><?= $i ?> Stars</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="small fw-bold text-muted mb-1">STATUS</label>
                        <select name="status" class="form-select form-control-custom">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending Reply</option>
                            <option value="Replied" <?= $filter_status == 'Replied' ? 'selected' : '' ?>>Replied</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold" style="border-radius: 10px;">Filter</button>
                        <a href="?" class="btn btn-light border w-100 py-2 fw-semibold" style="border-radius: 10px; color: #64748b;">Reset</a>
                    </div>
                </form>
            </div>

            <div class="table-card bg-white">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-muted" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Customer</th>
                                <th class="py-3 text-muted" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Vehicle</th>
                                <th class="py-3 text-muted" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Feedback</th>
                                <th class="text-center py-3 text-muted" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Status</th>
                                <th class="text-end px-4 py-3 text-muted" style="font-size: 12px; font-weight: 700; text-transform: uppercase;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($all_reviews)): ?>
                                <?php foreach ($all_reviews as $r): ?>
                                <tr>
                                    <td class="px-4" data-label="Customer">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($r['customer_name']) ?></div>
                                        <div class="text-muted small">ID: #BK-<?= $r['booking_id'] ?></div>
                                    </td>
                                    <td data-label="Vehicle">
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($r['brand']) ?> <?= htmlspecialchars($r['model']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($r['plate_number']) ?></div>
                                    </td>
                                    <td data-label="Feedback">
                                        <div class="d-flex flex-column gap-1">
                                            <!-- Top: Rating -->
                                            <div class="text-warning fw-bold small">
                                                <?= number_format((float)$r['rating'], 1) ?> ★
                                            </div>
                                            <!-- Bottom: Title -->
                                            <span class="text-truncate text-secondary small" style="max-width: 250px;">
                                                <?= htmlspecialchars($r['review_title']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-md-center" data-label="Status">
                                        <?php $replied = !empty($r['admin_reply']); ?>
                                        <span class="status-badge <?= $replied ? 'status-replied' : 'status-pending' ?>">
                                            <?= $replied ? 'Replied' : 'Pending' ?>
                                        </span>
                                    </td>
                                    <td class="px-md-4 text-end" data-label="Action">
                                        <button type="button"
                                                class="btn btn-primary btn-sm reply-button px-3 py-2 fw-semibold"
                                                style="border-radius: 8px; font-size: 13px;"
                                                data-id="<?= $r['id'] ?>"
                                                data-rating="<?= (int)$r['rating'] ?>"
                                                data-title="<?= htmlspecialchars($r['review_title'], ENT_QUOTES) ?>"
                                                data-text="<?= htmlspecialchars($r['review_text'], ENT_QUOTES) ?>"
                                                data-reply="<?= htmlspecialchars($r['admin_reply'] ?? '', ENT_QUOTES) ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#replyModal">
                                            <i class="bi bi-chat-left-dots-fill me-1"></i>
                                            <?= !empty($r['admin_reply']) ? 'Show & Edit Reply' : 'Reply' ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted fw-medium">No system records matches found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Single Reusable Feedback & Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            
            <!-- Modal Header -->
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title fw-bold text-dark" id="replyModalLabel" style="font-size: 16px; letter-spacing: -0.3px;">
                    <i class="bi bi-chat-square-quote-fill text-warning me-2"></i>Review Details & Response
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body modal-body-custom">
                <!-- Hidden ID Field -->
                <input type="hidden" id="review_id">
                
                <!-- Dynamic Customer Rating & Review Details -->
                <div class="p-3 mb-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div id="modal_stars" class="text-warning fs-6"></div>
                        <span id="modal_reply_badge" class="badge bg-success" style="display: none;">Replied</span>
                    </div>
                    <div id="modal_review_title" class="fw-bold text-dark small mb-1"></div>
                    <p id="modal_review_text" class="text-secondary small mb-0" style="line-height: 1.5; white-space: pre-line;"></p>
                </div>

                <!-- Admin Reply Textarea -->
                <label class="form-label small fw-bold text-muted mb-2" style="text-transform: uppercase; letter-spacing: 0.5px;">Your Reply</label>
                <textarea id="reply_text" class="form-control shadow-none" rows="4" 
                    placeholder="Type your reply to the customer here..." 
                    style="background-color: #ffffff; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 14px; padding: 12px;"></textarea>
                
                <!-- Status/Alert Message -->
                <div id="modal_message" class="mt-3" style="display: none;"></div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn border-0 text-muted fw-semibold" data-bs-dismiss="modal" style="font-size: 14px;">Cancel</button>
                <button type="button" class="btn btn-primary px-4 py-2 fw-semibold" id="submit_reply" style="border-radius: 10px; font-size: 14px;">
                    Save Reply
                </button>
            </div>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('replyModal');
    if (typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalElement);

    // OPEN MODAL RULE
    document.querySelectorAll('.reply-button').forEach(button => {
    button.addEventListener('click', function() {
        // Extract data attributes
        const reviewId = this.dataset.id;
        const rating = parseInt(this.dataset.rating) || 5;
        const title = this.dataset.title || '';
        const text = this.dataset.text || '';
        const reply = this.dataset.reply || '';

        // Populate modal elements
        document.getElementById('review_id').value = reviewId;
        document.getElementById('modal_review_title').innerText = title;
        document.getElementById('modal_review_text').innerText = text;
        document.getElementById('reply_text').value = reply;

        // Render Star Rating Stars (★/☆)
        const starsContainer = document.getElementById('modal_stars');
        starsContainer.innerHTML = '★'.repeat(rating) + '☆'.repeat(5 - rating);

        // Show/Hide Replied Badge
        const badge = document.getElementById('modal_reply_badge');
        badge.style.display = reply.trim() !== '' ? 'inline-block' : 'none';

        // Clear previous alert messages
        const msgDiv = document.getElementById('modal_message');
        msgDiv.style.display = 'none';
        msgDiv.innerHTML = '';
    });
});

    // SAVE REPLY ASYNC ROUTINE
    document.getElementById('submit_reply').addEventListener('click', function () {
        const reviewId = document.getElementById('review_id').value;
        const replyText = document.getElementById('reply_text').value.trim();
        const messageDiv = document.getElementById('modal_message');
        const submitBtn = this;

        if (!replyText) {
            messageDiv.innerHTML = '<div class="alert alert-danger py-2 px-3 style="font-size: 13px;">Please enter a valid message entry.</div>';
            messageDiv.style.display = 'block';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Saving changes...';

        fetch('process/reply_review.php', { 
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `review_id=${encodeURIComponent(reviewId)}&admin_reply=${encodeURIComponent(replyText)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                messageDiv.innerHTML = '<div class="alert alert-success py-2 px-3" style="font-size: 13px;">Reply saved perfectly!</div>';
                messageDiv.style.display = 'block';
                setTimeout(() => location.reload(), 700);
            } else {
                throw new Error(data.message || 'Failed to safe keeping');
            }
        })
        .catch(err => {
            messageDiv.innerHTML = '<div class="alert alert-danger py-2 px-3" style="font-size: 13px;">' + err.message + '</div>';
            messageDiv.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Save Reply';
        });
    });
});
</script>