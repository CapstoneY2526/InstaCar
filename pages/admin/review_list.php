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

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --brand-yellow: #ffcc00;
        --brand-yellow-soft: rgba(255, 204, 0, 0.12);
        --brand-black: #0a0a0a;
        --brand-ink: #1e293b;
        --brand-muted: #64748b;
        --brand-card-bg-dark: #141414;
        --brand-row-bg-dark: #141414;
        --brand-border-dark: #262626;
    }

    body { 
        background-color: #f8fafc; 
        font-family: 'Inter', sans-serif;
        overflow-x: hidden; 
        color: #0f172a;
        transition: background-color 0.25s ease, color 0.25s ease;
    }

    .main-content { 
        min-height: 100vh; 
    }

    .p-4 > .d-flex.justify-content-between.align-items-center.mb-4,
    .p-4 > .mb-4:first-child {
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 204, 0, 0.25);
    }

    /* Cards & Stats Horizontal Layout */
    .card {
        border-radius: 16px !important;
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
    }

    .table-card { 
        border: 1px solid #e2e8f0; 
        border-radius: 16px; 
        background: #ffffff; 
        box-shadow: 0 2px 6px rgba(0,0,0,0.03); 
        overflow: hidden;
    }
    
    .stat-card {
        background: #ffffff;
        border-radius: 16px;
        padding: 1.25rem;
        transition: all 0.25s ease;
        border: 1px solid #e2e8f0;
        height: 100%;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        border-color: var(--brand-yellow);
        box-shadow: 0 8px 20px rgba(255, 204, 0, 0.12);
    }

    .stat-icon-wrapper {
        width: 48px;
        height: 48px;
        min-width: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        background-color: rgba(255, 204, 0, 0.18);
        color: #b38f00;
    }

    .status-badge { 
        padding: 4px 12px; 
        border-radius: 20px; 
        font-size: 0.7rem; 
        font-weight: 700; 
        display: inline-block; 
    }
    .status-pending { background: #fef3c7; color: #d97706; }
    .status-replied { background: #d1fae5; color: #059669; }
    
    .reply-box { 
        background: #f8fafc; 
        border-left: 4px solid var(--brand-yellow); 
        padding: 12px; 
        margin-top: 10px; 
        border-radius: 8px; 
        font-size: 0.85rem; 
        color: #334155; 
    }

    .rating-stars { 
        color: #ffc107; 
        font-size: 0.875rem; 
        letter-spacing: 1px; 
    }

    /* Form Inputs & Custom Buttons */
    .form-control-custom {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        padding: 10px 14px;
        font-size: 0.875rem;
        border-radius: 10px;
        color: var(--brand-ink);
        transition: all 0.2s ease;
        font-family: 'Inter', sans-serif;
    }

    .form-control-custom:focus {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.25) !important;
        outline: none !important;
    }

    .btn-white {
        background: #fff;
        color: var(--brand-ink);
        border: 1px solid #e2e8f0;
        transition: 0.2s ease;
    }

    .btn-white:hover {
        border-color: var(--brand-yellow) !important;
        color: var(--brand-black) !important;
    }

    /* Primary & Yellow Buttons Standard Rules */
    .btn-primary,
    button.btn-primary,
    .reply-button {
        background-color: var(--brand-yellow) !important;
        border-color: var(--brand-yellow) !important;
        color: #000000 !important;
        font-weight: 700 !important;
    }

    .btn-primary *,
    button.btn-primary *,
    .reply-button *,
    .btn-primary span,
    .reply-button span,
    .btn-primary i,
    .reply-button i {
        color: #000000 !important;
    }

    .btn-primary:hover, 
    .btn-primary:focus,
    .reply-button:hover,
    .reply-button:focus {
        background-color: #e6c200 !important;
        border-color: #e6c200 !important;
        color: #000000 !important;
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.35) !important;
    }

    /* Modal Styling */
    .modal-content-custom {
        background-color: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        border-radius: 20px !important;
        box-shadow: 0 20px 40px rgba(0,0,0,0.12) !important;
        font-family: 'Inter', sans-serif;
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

    .modal-review-preview {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }

    /* Table Styles */
    .table thead th {
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: 0.75rem;
        background-color: #f8f5e9 !important;
        color: #7a6200 !important;
        border-color: #f0e9d2 !important;
    }

    /* Theme Toggle Button */
    .theme-toggle-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e2e8f0;
        background: #fff;
        transition: all 0.25s ease;
        cursor: pointer;
    }

    .theme-toggle-btn:hover {
        border-color: var(--brand-yellow);
        transform: translateY(-1px);
    }

    .theme-toggle-btn i { 
        font-size: 1.1rem; 
        color: #64748b; 
    }

    /* Mobile Responsive Cards */
    @media (max-width: 768px) {
        .table-card { background: transparent !important; box-shadow: none !important; border: none !important; }
        .table-responsive { overflow-x: visible !important; }
        .table-responsive table, .table-responsive thead, .table-responsive tbody, 
        .table-responsive th, .table-responsive td, .table-responsive tr { display: block; width: 100%; }
        .table-responsive thead { position: absolute; top: -9999px; left: -9999px; }
        .table-responsive tr {
            background: #fff;
            border: 1px solid #eef2f6;
            border-radius: 14px;
            margin-bottom: 12px;
            padding: 16px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }
        .table-responsive td {
            border: none !important;
            padding: 8px 0;
            width: 100%;
            text-align: left !important;
            white-space: normal;
        }
        .table-responsive td::before {
            content: attr(data-label);
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--brand-muted);
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }
        td[data-label="Feedback"] { min-width: 0 !important; overflow: hidden; }
        td[data-label="Feedback"] .reply-box { width: 100% !important; margin-top: 10px; box-sizing: border-box; word-break: break-word; }
        td[data-label="Status"], td[data-label="Action"] { width: 100% !important; display: block !important; text-align: left !important; }
        .reply-button { width: 100% !important; }
    }

    /* ========================================================
       CONSOLIDATED DARK MODE OVERRIDES
    ======================================================== */
    body.dark-mode {
        background-color: var(--brand-black) !important;
        color: #f1f1f1 !important;
    }

    body.dark-mode .main-content {
        background-color: var(--brand-black) !important;
    }

    body.dark-mode header,
    body.dark-mode .header,
    body.dark-mode nav,
    body.dark-mode .navbar,
    body.dark-mode .navbar-custom,
    body.dark-mode div[class*="header"],
    body.dark-mode div[class*="navbar"],
    body.dark-mode .bg-white.shadow-sm,
    body.dark-mode .bg-white {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f1f1 !important;
    }

    body.dark-mode header .navbar-brand,
    body.dark-mode header h1, body.dark-mode header h2, body.dark-mode header h3,
    body.dark-mode header h4, body.dark-mode header h5, body.dark-mode header h6,
    body.dark-mode header .fw-bold, body.dark-mode header span.text-dark,
    body.dark-mode header [class*="logo"] {
        color: #ffffff !important;
    }

    body.dark-mode header small,
    body.dark-mode header .text-muted {
        color: #9a9a9a !important;
    }

    body.dark-mode .card,
    body.dark-mode .table-card,
    body.dark-mode .stat-card,
    body.dark-mode .modal-content-custom,
    body.dark-mode .dropdown-menu {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f1f1 !important;
    }

    body.dark-mode .modal-review-preview {
        background-color: #1a1a1a !important;
        border-color: #333333 !important;
    }

    body.dark-mode #modal_review_title {
        color: #ffffff !important;
    }

    body.dark-mode #modal_review_text {
        color: #cbd5e1 !important;
    }

    body.dark-mode .stat-card:hover {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 8px 20px rgba(255, 204, 0, 0.08) !important;
    }

    body.dark-mode .stat-icon-wrapper {
        background-color: rgba(255, 204, 0, 0.12) !important;
        color: var(--brand-yellow) !important;
    }

    /* Force Yellow Buttons to Render Pure Black Text in Dark Mode */
    body.dark-mode .btn-primary,
    body.dark-mode button.btn-primary,
    body.dark-mode .reply-button {
        background-color: var(--brand-yellow) !important;
        border-color: var(--brand-yellow) !important;
        color: #000000 !important;
        font-weight: 700 !important;
    }

    body.dark-mode .btn-primary *,
    body.dark-mode button.btn-primary *,
    body.dark-mode .reply-button *,
    body.dark-mode .btn-primary span,
    body.dark-mode .reply-button span,
    body.dark-mode .btn-primary i,
    body.dark-mode .reply-button i {
        color: #000000 !important;
    }

    /* Form Fields & Placeholders Fix */
    body.dark-mode .form-control-custom,
    body.dark-mode textarea#reply_text,
    body.dark-mode .form-control,
    body.dark-mode .form-select {
        background-color: #242424 !important;
        border: 1px solid #3d3d3d !important;
        color: #ffffff !important;
    }

    body.dark-mode .form-control-custom::placeholder,
    body.dark-mode .form-control::placeholder,
    body.dark-mode textarea#reply_text::placeholder {
        color: #94a3b8 !important;
        opacity: 1 !important;
    }

    body.dark-mode .form-control-custom:focus,
    body.dark-mode textarea#reply_text:focus {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.25) !important;
    }

    /* Table & Headers Fix */
    body.dark-mode .table {
        color: #f1f1f1 !important;
        --bs-table-bg: transparent;
    }

    body.dark-mode table.table thead th {
        background-color: #1f1f1f !important;
        color: var(--brand-yellow) !important;
        border-bottom: 1px solid var(--brand-border-dark) !important;
        font-weight: 700 !important;
    }

    body.dark-mode table.table tbody tr {
        background-color: var(--brand-row-bg-dark) !important;
    }

    body.dark-mode table.table td {
        border-bottom-color: var(--brand-border-dark) !important;
        color: #e2e8f0 !important;
    }

    body.dark-mode table.table-hover > tbody > tr:hover > * {
        background-color: #1f1f1f !important;
        color: #ffffff !important;
    }

    /* Typography & Contrast */
    body.dark-mode .fw-bold,
    body.dark-mode .fw-semibold,
    body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, 
    body.dark-mode h4, body.dark-mode h5, body.dark-mode h6,
    body.dark-mode .text-dark {
        color: #ffffff !important;
    }

    body.dark-mode .text-muted,
    body.dark-mode .text-secondary,
    body.dark-mode label.text-muted {
        color: #94a3b8 !important;
    }

    body.dark-mode .btn-light {
        background-color: #242424 !important;
        border-color: #3d3d3d !important;
        color: #f1f1f1 !important;
    }

    body.dark-mode .btn-light:hover {
        border-color: var(--brand-yellow) !important;
        color: var(--brand-yellow) !important;
    }

    body.dark-mode .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    body.dark-mode .reply-box {
        background-color: #121212 !important;
        color: #e2e8f0 !important;
        border-left-color: var(--brand-yellow) !important;
    }

    body.dark-mode .theme-toggle-btn {
        background: var(--brand-card-bg-dark);
        border-color: var(--brand-border-dark);
    }

    body.dark-mode .theme-toggle-btn i { 
        color: var(--brand-yellow); 
    }

    body.dark-mode footer,
    body.dark-mode .footer,
    body.dark-mode div[class*="footer"] {
        background-color: var(--brand-black) !important;
        border-top: 1px solid var(--brand-border-dark) !important;
        color: #9a9a9a !important;
    }

    @media (max-width: 768px) {
        body.dark-mode .table-responsive tr {
            background-color: var(--brand-card-bg-dark) !important;
            border-color: var(--brand-border-dark) !important;
        }
        body.dark-mode .table-responsive td::before {
            color: #9a9a9a !important;
        }
    }
</style>

<script>
    (function () {
        const stored = localStorage.getItem('instacar-admin-theme');
        if (stored === 'dark') {
            document.body.classList.add('dark-mode');
        }
    })();
</script>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-auto p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-4">
                <div class="mb-4">
                    <h3 class="fw-bold mb-0">Review <span style="color: var(--brand-yellow);">Management</span></h3>
                    <p class="text-muted small mb-0">Monitor and respond to customer feedback</p>
                </div>

                <!-- Reference Card Layout -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon-wrapper">
                                <i class="bi bi-chat-square-text-fill"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold mb-0 text-dark" style="font-size: 1.6rem; line-height: 1.1;"><?= $total_reviews ?></h3>
                                <div class="text-muted fw-bold" style="font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase;">TOTAL REVIEWS</div>
                                <div class="text-muted" style="font-size: 12px;">All submitted</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon-wrapper">
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold mb-0" style="font-size: 1.6rem; line-height: 1.1; color: #ffc107;"><?= $avg_rating ?> <span class="fs-5">★</span></h3>
                                <div class="text-muted fw-bold" style="font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase;">AVERAGE RATING</div>
                                <div class="text-muted" style="font-size: 12px;">Overall score</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <div class="stat-card">
                            <div class="stat-icon-wrapper">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                            <div>
                                <h3 class="fw-bold mb-0 text-warning" style="font-size: 1.6rem; line-height: 1.1;"><?= $pending_reviews ?></h3>
                                <div class="text-muted fw-bold" style="font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase;">PENDING REPLIES</div>
                                <div class="text-muted" style="font-size: 12px;">Awaiting response</div>
                            </div>
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
                            <a href="?" class="btn btn-light w-100 py-2 fw-semibold" style="border-radius: 10px;">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3">Customer</th>
                                    <th class="py-3">Vehicle</th>
                                    <th class="py-3">Feedback</th>
                                    <th class="text-center py-3">Status</th>
                                    <th class="text-end px-4 py-3">Action</th>
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
                                                <div class="text-warning fw-bold small">
                                                    <?= number_format((float)$r['rating'], 1) ?> ★
                                                </div>
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

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>

<!-- Reusable Feedback & Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            
            <div class="modal-header modal-header-custom">
                <h5 class="modal-title fw-bold text-dark" id="replyModalLabel" style="font-size: 16px; letter-spacing: -0.3px;">
                    <i class="bi bi-chat-square-quote-fill text-warning me-2"></i>Review Details & Response
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body modal-body-custom">
                <input type="hidden" id="review_id">
                
                <div class="p-3 mb-3 modal-review-preview">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div id="modal_stars" class="text-warning fs-6"></div>
                        <span id="modal_reply_badge" class="badge bg-success" style="display: none;">Replied</span>
                    </div>
                    <div id="modal_review_title" class="fw-bold small mb-1"></div>
                    <p id="modal_review_text" class="small mb-0" style="line-height: 1.5; white-space: pre-line;"></p>
                </div>

                <label class="form-label small fw-bold text-muted mb-2" style="text-transform: uppercase; letter-spacing: 0.5px;">Your Reply</label>
                <textarea id="reply_text" class="form-control shadow-none" rows="4" 
                    placeholder="Type your reply to the customer here..." 
                    style="font-size: 14px; padding: 12px;"></textarea>
                
                <div id="modal_message" class="mt-3" style="display: none;"></div>
            </div>

            <div class="modal-footer modal-footer-custom">
                <button type="button" class="btn border-0 text-muted fw-semibold" data-bs-dismiss="modal" style="font-size: 14px;">Cancel</button>
                <button type="button" class="btn btn-primary px-4 py-2 fw-semibold" id="submit_reply" style="border-radius: 10px; font-size: 14px;">
                    Save Reply
                </button>
            </div>
            
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('replyModal');
    if (typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalElement);

    document.querySelectorAll('.reply-button').forEach(button => {
        button.addEventListener('click', function() {
            const reviewId = this.dataset.id;
            const rating = parseInt(this.dataset.rating) || 5;
            const title = this.dataset.title || '';
            const text = this.dataset.text || '';
            const reply = this.dataset.reply || '';

            document.getElementById('review_id').value = reviewId;
            document.getElementById('modal_review_title').innerText = title;
            document.getElementById('modal_review_text').innerText = text;
            document.getElementById('reply_text').value = reply;

            const starsContainer = document.getElementById('modal_stars');
            starsContainer.innerHTML = '★'.repeat(rating) + '☆'.repeat(5 - rating);

            const badge = document.getElementById('modal_reply_badge');
            badge.style.display = reply.trim() !== '' ? 'inline-block' : 'none';

            const msgDiv = document.getElementById('modal_message');
            msgDiv.style.display = 'none';
            msgDiv.innerHTML = '';
        });
    });

    document.getElementById('submit_reply').addEventListener('click', function () {
        const reviewId = document.getElementById('review_id').value;
        const replyText = document.getElementById('reply_text').value.trim();
        const messageDiv = document.getElementById('modal_message');
        const submitBtn = this;

        if (!replyText) {
            messageDiv.innerHTML = '<div class="alert alert-danger py-2 px-3" style="font-size: 13px;">Please enter a valid message entry.</div>';
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
                throw new Error(data.message || 'Failed to save reply');
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