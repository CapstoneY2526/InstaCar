<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Admin-only guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied.";
    ?>
    <script>window.stop(); window.location.href = "../../index.php";</script>
    <?php
    exit();
}

$pageTitle = 'Staff Fee Remittances';

// ── Branch scope from header switcher ──
$view_branch = $_SESSION['view_branch'] ?? 'all';
$branch_filter_active = false;
$branch_filter_id = 0;
if ($view_branch !== 'all') {
    $branch_filter_id = (int)$view_branch;
    $branch_filter_active = $branch_filter_id > 0;
}

// ── Filters from URL ──
$filter_status = $_GET['status'] ?? 'all';

// ── Load branches (for looking up the active branch's name) ──
$branches = [];
$brRes = mysqli_query($conn, "SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC");
while ($row = mysqli_fetch_assoc($brRes)) $branches[] = $row;

// ── Build per-branch owed / paid / balance query ──
$sql = "
    SELECT
        br.id   AS branch_id,
        br.name AS branch_name,

        COALESCE(owed.total_owed, 0) AS total_owed,
        COALESCE(paid.total_paid, 0) AS total_paid

    FROM branches br

    LEFT JOIN (
        SELECT
            b.branch_id,
            SUM(bp.jer_delivery_fee + bp.jer_pickup_fee) AS total_owed
        FROM booking_payments bp
        INNER JOIN bookings b ON bp.booking_id = b.id
        WHERE b.branch_id IS NOT NULL
        GROUP BY b.branch_id
    ) owed ON owed.branch_id = br.id

    LEFT JOIN (
        SELECT
            branch_id,
            SUM(amount) AS total_paid
        FROM staff_fee_remittances
        GROUP BY branch_id
    ) paid ON paid.branch_id = br.id

    WHERE br.is_active = 1
";

$params = [];
$types  = '';

// Branch scope from header switcher (no UI needed — the switcher itself does the job)
if ($branch_filter_active) {
    $sql .= " AND br.id = ?";
    $params[] = $branch_filter_id;
    $types   .= 'i';
}

$sql .= " ORDER BY br.name ASC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$branch_rows = [];
while ($row = $res->fetch_assoc()) {
    $owed = (float)$row['total_owed'];
    $paid = (float)$row['total_paid'];
    $balance = $owed - $paid;

    if ($owed <= 0) {
        $status = 'no-activity';
    } elseif ($paid <= 0) {
        $status = 'unpaid';
    } elseif ($paid >= $owed) {
        $status = 'paid';
    } else {
        $status = 'partial';
    }

    // Apply status filter AFTER computing (it's derived, not stored)
    if ($filter_status !== 'all') {
        $statusMap = ['unpaid','partial','paid','no-activity'];
        if (!in_array($filter_status, $statusMap) || $status !== $filter_status) {
            continue;
        }
    }

    $row['total_owed'] = $owed;
    $row['total_paid'] = $paid;
    $row['balance']    = $balance;
    $row['status']     = $status;
    $branch_rows[] = $row;
}
$stmt->close();

// ── Fetch all remittances for the branches we're about to render ──
$branch_ids = array_column($branch_rows, 'branch_id');
$remittances_by_branch = [];
if (!empty($branch_ids)) {
    $in = implode(',', array_map('intval', $branch_ids));
    $remRes = mysqli_query($conn, "
        SELECT r.*, u.name AS recorded_by_name
        FROM staff_fee_remittances r
        LEFT JOIN users u ON r.recorded_by = u.id
        WHERE r.branch_id IN ($in)
        ORDER BY r.payment_date DESC, r.id DESC
    ");

    $remit_ids = [];
    $rows = [];
    while ($row = mysqli_fetch_assoc($remRes)) {
        $rows[] = $row;
        $remit_ids[] = (int)$row['id'];
    }

    // Fetch photos grouped by remittance_id
    $photos_by_remit = [];
    if (!empty($remit_ids)) {
        $in2 = implode(',', array_map('intval', $remit_ids));
        $photoRes = mysqli_query($conn, "
            SELECT id, remittance_id, file_name
            FROM staff_fee_remittance_photos
            WHERE remittance_id IN ($in2)
            ORDER BY id ASC
        ");
        while ($p = mysqli_fetch_assoc($photoRes)) {
            $photos_by_remit[(int)$p['remittance_id']][] = [
                'id'        => (int)$p['id'],
                'file_name' => $p['file_name'],
            ];
        }
    }

    foreach ($rows as $row) {
        $row['photos'] = $photos_by_remit[(int)$row['id']] ?? [];
        $remittances_by_branch[(int)$row['branch_id']][] = $row;
    }
}

// ── Active branch name (for header subtitle) ──
$active_branch_name = null;
if ($branch_filter_active) {
    foreach ($branches as $b) {
        if ((int)$b['id'] === $branch_filter_id) { $active_branch_name = $b['name']; break; }
    }
}

// ── Stats ──
$total_owed = 0;
$total_paid = 0;
foreach ($branch_rows as $b) {
    $total_owed += $b['total_owed'];
    $total_paid += $b['total_paid'];
}
$total_balance = $total_owed - $total_paid;
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    body, button, input, select, textarea, .form-control, .form-select, .btn, .table {
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    }

    .main-content {
        background-color: var(--brand-bg, #f8fafc);
        min-height: 100vh;
        transition: background-color 0.25s ease, color 0.25s ease;
    }

    /* ── Stat cards ── */
    .stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        padding: 1.15rem 1.25rem;
        height: 100%;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-3px);
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 8px 24px -8px rgba(255, 215, 0, 0.55) !important;
    }
    .stat-label {
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #475569;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .stat-value {
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
    }
    .stat-sub {
        font-size: 0.72rem;
        color: #475569;
        font-weight: 500;
        margin-top: 2px;
    }

    /* ── Filter bar ── */
    .filter-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem 1.15rem;
    }
    .filter-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        margin-bottom: 4px;
    }
    .filter-card .form-select,
    .filter-card .form-control {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        font-size: 0.85rem;
        color: #0f172a;
    }
    .filter-card .form-select:focus,
    .filter-card .form-control:focus {
        border-color: #ffd700;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.22);
        outline: none;
    }

    .search-wrap { position: relative; width: 100%; }
    .search-wrap i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        pointer-events: none;
        font-size: 0.9rem;
    }
    .search-wrap input {
        padding-left: 2.4rem;
        height: 40px;
    }

    /* ── Branch card ── */
    .branch-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        overflow: hidden;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .branch-card:hover {
        transform: translateY(-2px);
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 6px 18px -6px rgba(255, 215, 0, 0.5) !important;
    }
    .branch-card .card-head {
        padding: 1rem 1.25rem;
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
    }
    .branch-card .branch-title {
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }
    .branch-card .branch-meta {
        font-size: 0.75rem;
        color: #475569;
        margin-top: 2px;
    }

    /* ── Status badges ── */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.4px;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .status-badge::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: currentColor;
    }
    .status-unpaid      { background: #fee2e2; color: #b91c1c; }
    .status-partial     { background: #fef3c7; color: #92400e; }
    .status-paid        { background: #dcfce7; color: #15803d; }
    .status-no-activity { background: #f1f5f9; color: #64748b; }

    /* ── Money grid ── */
    .money-grid {
        padding: 1rem 1.25rem;
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
    }
    @media (max-width: 575.98px) {
        .money-grid { grid-template-columns: 1fr; gap: 0.5rem; }
    }
    .money-box {
        text-align: center;
        padding: 0.65rem 0.5rem;
        border-radius: 0.75rem;
        background: #f8fafc;
    }
    .money-box .lbl {
        font-size: 0.6rem;
        text-transform: uppercase;
        font-weight: 800;
        letter-spacing: 0.5px;
        color: #475569;
        margin-bottom: 3px;
    }
    .money-box .val {
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
    }
    .money-box.owed    .val { color: #0f172a; }
    .money-box.paid    .val { color: #15803d; }
    .money-box.balance .val { color: #b91c1c; }
    .money-box.balance.is-clear .val { color: #15803d; }

    /* ── Actions ── */
    .branch-card .actions {
        padding: 0.85rem 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        background: #fffbe6;
    }
    .btn-record {
        background-color: #ffd700;
        border: 1px solid #ffd700;
        color: #000000;
        font-weight: 700;
        padding: 0.5rem 1rem;
        border-radius: 0.65rem;
        transition: all 0.2s ease;
    }
    .btn-record:hover {
        background-color: #e6c200;
        border-color: #e6c200;
        color: #000000;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 215, 0, 0.4);
    }
    .btn-record:disabled {
        opacity: 0.4;
        cursor: not-allowed;
        transform: none;
    }
    .btn-history {
        background: transparent;
        border: 1px solid #cbd5e1;
        color: #334155;
        font-weight: 600;
        padding: 0.5rem 1rem;
        border-radius: 0.65rem;
        font-size: 0.8rem;
        transition: all 0.2s ease;
    }
    .btn-history:hover {
        background: #f1f5f9;
        color: #0f172a;
    }

    /* ── History items + proof thumbnails ── */
    .history-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.65rem 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.85rem;
    }
    .history-item:last-child { border-bottom: none; }
    .history-item .amount {
        font-weight: 800;
        color: #15803d;
        white-space: nowrap;
    }
    .history-item .meta {
        color: #64748b;
        font-size: 0.72rem;
        margin-top: 2px;
    }

    .proof-thumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }
    .proof-thumb {
        width: 52px;
        height: 52px;
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid #cbd5e1;
        cursor: pointer;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
    }
    .proof-thumb:hover {
        border-color: #ffd700;
        box-shadow: 0 0 0 1px #ffd700;
        transform: translateY(-1px);
    }
    .proof-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .proof-thumb .pdf-icon {
        color: #dc2626;
        font-size: 1.4rem;
    }

    .proof-viewer-img {
        max-width: 100%;
        max-height: 75vh;
        border-radius: 8px;
        display: block;
        margin: 0 auto;
        background: #000;
    }

    /* ── Empty state ── */
    .empty-state { text-align: center; padding: 60px 20px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 1.25rem; }
    .empty-state i { font-size: 64px; color: #94a3b8; margin-bottom: 20px; display: block; }
    .empty-state h5 { color: #334155; margin-bottom: 10px; font-weight: 800; }
    .empty-state p { color: #64748b; }

    .modal-section-title {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        margin-bottom: 8px;
    }

    /* ============================================================
       DARK MODE
       ============================================================ */
    body.dark-mode,
    body.dark-mode .main-content {
        background-color: #0a0a0a !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .stat-card,
    body.dark-mode .filter-card,
    body.dark-mode .branch-card,
    body.dark-mode .empty-state {
        background-color: #141414 !important;
        border-color: #27272a !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.4) !important;
    }
    body.dark-mode .stat-card:hover,
    body.dark-mode .branch-card:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 0 22px rgba(255, 215, 0, 0.4) !important;
    }
    body.dark-mode .stat-value,
    body.dark-mode .branch-card .branch-title,
    body.dark-mode .money-box .val { color: #ffffff !important; }
    body.dark-mode .stat-label,
    body.dark-mode .stat-sub,
    body.dark-mode .branch-card .branch-meta,
    body.dark-mode .money-box .lbl { color: #cbd5e1 !important; }
    body.dark-mode .money-box { background: #1f1f23 !important; }
    body.dark-mode .branch-card .card-head { background: #141414 !important; border-color: #27272a !important; }
    body.dark-mode .branch-card .actions { background: #1a1600 !important; }
    body.dark-mode .history-item { border-color: #27272a !important; }
    body.dark-mode .history-item .meta { color: #94a3b8 !important; }
    body.dark-mode .btn-history {
        border-color: #3f3f46 !important;
        color: #e2e8f0 !important;
    }
    body.dark-mode .btn-history:hover {
        background: #27272a !important;
        color: #ffffff !important;
    }
    body.dark-mode .filter-card .form-select,
    body.dark-mode .filter-card .form-control {
        background-color: #0d0d0d !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    body.dark-mode .filter-card .form-select:focus,
    body.dark-mode .filter-card .form-control:focus {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.22) !important;
    }
    body.dark-mode .search-wrap input::placeholder { color: #64748b !important; }
    body.dark-mode .search-wrap i { color: #64748b; }
    body.dark-mode .status-no-activity { background: #1f1f23 !important; color: #94a3b8 !important; }
    body.dark-mode .empty-state i { color: #71717a !important; }
    body.dark-mode .empty-state h5 { color: #e2e8f0 !important; }
    body.dark-mode .empty-state p { color: #cbd5e1 !important; }
    body.dark-mode h3 { color: #ffffff !important; }
    body.dark-mode .text-muted { color: #cbd5e1 !important; }

    body.dark-mode .proof-thumb {
        background: #1f1f23 !important;
        border-color: #3f3f46 !important;
    }
    body.dark-mode .proof-thumb:hover {
        border-color: #ffd700 !important;
    }

    /* ── Modal dark ── */
    body.dark-mode .modal-content {
        background-color: #141414 !important;
        border-color: #27272a !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .modal-header,
    body.dark-mode .modal-footer { border-color: #27272a !important; }
    body.dark-mode .modal-body .form-label,
    body.dark-mode .modal-body .filter-label { color: #cbd5e1 !important; }
    body.dark-mode .modal-body .form-control {
        background-color: #0d0d0d !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    body.dark-mode .modal-body .form-control::placeholder { color: #64748b !important; }
    body.dark-mode .modal-body #recordBalance { color: #ffffff !important; }
    body.dark-mode .modal-body #recordBranchLabel,
    body.dark-mode .modal-body #recordBranchName { color: #94a3b8 !important; }
    body.dark-mode .modal-body .history-item { border-color: #27272a !important; }
    body.dark-mode .modal-body .history-item .meta { color: #94a3b8 !important; }
    body.dark-mode .modal-body .modal-section-title { color: #cbd5e1 !important; }
    body.dark-mode input[type="file"].form-control::file-selector-button {
        background-color: #27272a !important;
        color: #f1f5f9 !important;
        border-color: #3f3f46 !important;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 col-md-auto p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">

                <!-- Header -->
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-0">Staff <span style="color: #b38a00;">Remittances</span></h3>
                        <p class="text-muted small mb-0">
                            <?= $branch_filter_active
                                ? htmlspecialchars($active_branch_name ?? 'Selected Branch')
                                : 'All Branches' ?>
                            &middot; Track delivery and pickup fees owed to each branch's staff.
                        </p>
                    </div>
                </div>

                <!-- Stats row -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Total Staff Fees</div>
                            <div class="stat-value">₱<?= number_format($total_owed, 2) ?></div>
                            <div class="stat-sub">Sum of delivery + pickup fees</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Total Paid</div>
                            <div class="stat-value" style="color:#15803d;">₱<?= number_format($total_paid, 2) ?></div>
                            <div class="stat-sub">All recorded remittances</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Outstanding Balance</div>
                            <div class="stat-value" style="color: <?= $total_balance > 0 ? '#b91c1c' : '#15803d' ?>;">
                                ₱<?= number_format($total_balance, 2) ?>
                            </div>
                            <div class="stat-sub">Fees owed minus paid</div>
                        </div>
                    </div>
                </div>

                <!-- Filters (branch filter now lives in the header switcher) -->
                <form method="GET" class="filter-card mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="filter-label">Status</div>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all"          <?= $filter_status === 'all'         ? 'selected' : '' ?>>All Status</option>
                                <option value="unpaid"       <?= $filter_status === 'unpaid'      ? 'selected' : '' ?>>Unpaid</option>
                                <option value="partial"      <?= $filter_status === 'partial'     ? 'selected' : '' ?>>Partial</option>
                                <option value="paid"         <?= $filter_status === 'paid'        ? 'selected' : '' ?>>Paid</option>
                                <option value="no-activity"  <?= $filter_status === 'no-activity' ? 'selected' : '' ?>>No Activity</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-6">
                            <div class="filter-label">Search</div>
                            <div class="search-wrap">
                                <i class="bi bi-search"></i>
                                <input type="text" id="branchSearch" class="form-control" placeholder="Search branch name...">
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md-3">
                            <a href="remittance.php" class="btn btn-outline-secondary w-100" style="border-radius:10px; height:40px; display:inline-flex; align-items:center; justify-content:center;">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Branch cards -->
                <?php if (empty($branch_rows)): ?>
                    <div class="empty-state">
                        <i class="bi bi-people"></i>
                        <h5>No branches found</h5>
                        <p class="mb-0 small">Either no branches exist, or the filters you set returned nothing.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3" id="branchGrid">
                        <?php foreach ($branch_rows as $b):
                            $statusClassMap = [
                                'unpaid'      => 'status-unpaid',
                                'partial'     => 'status-partial',
                                'paid'        => 'status-paid',
                                'no-activity' => 'status-no-activity',
                            ];
                            $statusLabelMap = [
                                'unpaid'      => 'Unpaid',
                                'partial'     => 'Partial',
                                'paid'        => 'Paid',
                                'no-activity' => 'No Activity',
                            ];
                            $sc = $statusClassMap[$b['status']];
                            $sl = $statusLabelMap[$b['status']];
                            $branchRemits = $remittances_by_branch[$b['branch_id']] ?? [];
                            $isClear = ($b['balance'] <= 0 && $b['total_owed'] > 0);
                            $searchKey = strtolower($b['branch_name']);

                            // Prepare JSON for modals
                            $remitsJson = htmlspecialchars(json_encode($branchRemits), ENT_QUOTES, 'UTF-8');
                        ?>
                            <div class="col-12 col-xl-6 branch-col" data-search="<?= htmlspecialchars($searchKey, ENT_QUOTES) ?>">
                                <div class="branch-card h-100 d-flex flex-column">

                                    <div class="card-head d-flex justify-content-between align-items-start gap-2">
                                        <div class="min-w-0">
                                            <div class="branch-title text-truncate">
                                                <i class="bi bi-shop me-1" style="color:#b38a00;"></i>
                                                <?= htmlspecialchars($b['branch_name']) ?>
                                            </div>
                                            <div class="branch-meta">Staff fee tracking</div>
                                        </div>
                                        <span class="status-badge <?= $sc ?>"><?= $sl ?></span>
                                    </div>

                                    <div class="money-grid">
                                        <div class="money-box owed">
                                            <div class="lbl">Owed</div>
                                            <div class="val">₱<?= number_format($b['total_owed'], 2) ?></div>
                                        </div>
                                        <div class="money-box paid">
                                            <div class="lbl">Paid</div>
                                            <div class="val">₱<?= number_format($b['total_paid'], 2) ?></div>
                                        </div>
                                        <div class="money-box balance <?= $isClear ? 'is-clear' : '' ?>">
                                            <div class="lbl">Balance</div>
                                            <div class="val">₱<?= number_format($b['balance'], 2) ?></div>
                                        </div>
                                    </div>

                                    <div class="actions mt-auto">
                                        <button class="btn-record"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#recordModal"
                                                data-branch-id="<?= (int)$b['branch_id'] ?>"
                                                data-branch-name="<?= htmlspecialchars($b['branch_name'], ENT_QUOTES) ?>"
                                                data-balance="<?= number_format($b['balance'], 2, '.', '') ?>"
                                                <?= $b['balance'] <= 0 ? 'disabled' : '' ?>>
                                            <i class="bi bi-cash-coin me-1"></i>
                                            Record Payment
                                        </button>

                                        <?php if (!empty($branchRemits)): ?>
                                            <button class="btn-history"
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#historyModal"
                                                    data-branch-name="<?= htmlspecialchars($b['branch_name'], ENT_QUOTES) ?>"
                                                    data-remits='<?= $remitsJson ?>'>
                                                <i class="bi bi-clock-history me-1"></i>
                                                Payment History (<?= count($branchRemits) ?>)
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="col-12 d-none" id="noSearchResults">
                            <div class="empty-state">
                                <i class="bi bi-search"></i>
                                <h5>No branches match your search</h5>
                                <p class="mb-0 small">Try a different branch name.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <div class="mt-auto">
                <?php require_once __DIR__ . '/../components/footer.php'; ?>
            </div>
        </div>
    </div>
</div>

<!-- RECORD PAYMENT MODAL -->
<div class="modal fade" id="recordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process/staff_remittance_actions.php"
              method="POST"
              enctype="multipart/form-data"
              class="modal-content border-0 shadow-lg rounded-4">
            <input type="hidden" name="action" value="record">
            <input type="hidden" name="branch_id" id="recordBranchId">

            <div class="modal-header border-0 pb-2">
                <div>
                    <h5 class="fw-bold mb-0">Record Staff Payment</h5>
                    <small class="text-muted" id="recordBranchLabel">—</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-2">
                <div class="mb-3">
                    <label class="filter-label">Current Balance</label>
                    <div class="fw-bold" style="font-size:1.15rem;" id="recordBalance">₱0.00</div>
                    <small class="text-muted" id="recordBranchName">—</small>
                </div>

                <div class="mb-3">
                    <label class="filter-label" for="recordAmount">Amount to Pay (₱)</label>
                    <input type="number" name="amount" id="recordAmount" class="form-control" step="0.01" min="0.01" required>
                </div>

                <div class="mb-3">
                    <label class="filter-label" for="recordDate">Payment Date</label>
                    <input type="date" name="payment_date" id="recordDate" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="filter-label" for="recordProofs">
                        Proof of Payment <span class="text-muted fw-normal text-lowercase">(optional, up to 5 files)</span>
                    </label>
                    <input type="file" name="proofs[]" id="recordProofs" class="form-control" accept="image/*,.pdf" multiple>
                    <small class="text-muted d-block mt-1" style="font-size:0.72rem;">
                        JPG, PNG, or PDF — max 5MB each.
                    </small>
                </div>

                <div class="mb-1">
                    <label class="filter-label" for="recordNotes">Notes (optional)</label>
                    <textarea name="notes" id="recordNotes" class="form-control" rows="2" placeholder="e.g. GCash reference #, cash handed over..."></textarea>
                </div>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn-record">
                    <i class="bi bi-check-circle me-1"></i>Save Payment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- PAYMENT HISTORY MODAL -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-2">
                <div>
                    <h5 class="fw-bold mb-0">Payment History</h5>
                    <small class="text-muted" id="historyModalBranchLabel">—</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2" id="historyModalBody">
                <!-- Populated by JS -->
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- PROOF VIEWER MODAL -->
<div class="modal fade" id="proofModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-2">
                <div>
                    <h5 class="fw-bold mb-0">Proof of Payment</h5>
                    <small class="text-muted" id="proofModalLabel">—</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2 text-center" id="proofModalBody">
                <!-- Populated by JS -->
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-between">
                <a href="#" id="proofOpenNewTab" target="_blank" class="btn btn-outline-secondary" style="display:none;">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Open in new tab
                </a>
                <button type="button" class="btn btn-outline-secondary ms-auto" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- DELETE REMITTANCE CONFIRMATION MODAL -->
<div class="modal fade" id="deleteRemitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process/staff_remittance_actions.php" method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="remittance_id" id="deleteRemitId">

            <div class="modal-header border-0 pb-2">
                <h5 class="fw-bold mb-0 text-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Remittance?
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-2">
                <p class="mb-2">You are about to permanently delete this payment record:</p>
                <div class="p-3 rounded-3 mb-3" style="background:#fee2e2;">
                    <div class="fw-bold" style="color:#991b1b; font-size:1.15rem;" id="deleteRemitAmount">₱0.00</div>
                    <div style="color:#7f1d1d; font-size:0.8rem;">Paid on <span id="deleteRemitDate">—</span></div>
                </div>
                <p class="text-muted small mb-0">
                    <strong>This cannot be undone.</strong> All attached proof files will also be deleted from the server.
                    The branch's balance will increase back by this amount.
                </p>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash me-1"></i>Delete Remittance
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const PROOF_BASE = '../../public/assets/images/remittances/';

    // ── Formatters ──
    function peso(v) {
        const n = parseFloat(v) || 0;
        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function shortDate(s) {
        if (!s) return '';
        const d = new Date(s.replace(' ', 'T'));
        if (isNaN(d)) return s;
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }
    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ── Record Payment modal ──
    const recordModal = document.getElementById('recordModal');
    if (recordModal) {
        recordModal.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            const branchId   = trigger.getAttribute('data-branch-id');
            const branchName = trigger.getAttribute('data-branch-name');
            const balance    = parseFloat(trigger.getAttribute('data-balance')) || 0;

            document.getElementById('recordBranchId').value = branchId;
            document.getElementById('recordBranchLabel').textContent = branchName;
            document.getElementById('recordBalance').textContent = peso(balance);
            document.getElementById('recordBranchName').textContent = branchName;

            const amountInput = document.getElementById('recordAmount');
            amountInput.value = balance.toFixed(2);
            amountInput.max = balance.toFixed(2);

            document.getElementById('recordNotes').value = '';
            document.getElementById('recordDate').value = new Date().toISOString().slice(0, 10);
            document.getElementById('recordProofs').value = '';
        });

        const amountInput = document.getElementById('recordAmount');
        if (amountInput) {
            amountInput.addEventListener('input', function () {
                const max = parseFloat(this.max) || 0;
                const val = parseFloat(this.value) || 0;
                if (val > max) {
                    this.value = max.toFixed(2);
                }
            });
        }
    }

    // ── Payment History modal ──
    const historyModal = document.getElementById('historyModal');
    if (historyModal) {
        historyModal.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            document.getElementById('historyModalBranchLabel').textContent = trigger.getAttribute('data-branch-name') || '';

            let remits = [];
            try {
                remits = JSON.parse(trigger.getAttribute('data-remits') || '[]');
            } catch (e) { remits = []; }

            const body = document.getElementById('historyModalBody');
            if (!remits.length) {
                body.innerHTML = '<p class="text-muted small mb-0">No payments recorded yet.</p>';
                return;
            }

            let total = 0;
            let html = '<div class="modal-section-title">Recorded remittances</div>';

            remits.forEach(r => {
                const amt = parseFloat(r.amount) || 0;
                total += amt;

                const dateLabel = shortDate(r.payment_date);
                const recordedBy = r.recorded_by_name || 'Unknown';
                const notesHtml = r.notes ? `<div class="meta">${escapeHtml(r.notes).replace(/\n/g, '<br>')}</div>` : '';

                // Proof thumbnails
                let proofsHtml = '';
                if (Array.isArray(r.photos) && r.photos.length > 0) {
                    proofsHtml += '<div class="proof-thumbs">';
                    r.photos.forEach(p => {
                        const url = PROOF_BASE + encodeURIComponent(p.file_name);
                        const isPdf = /\.pdf$/i.test(p.file_name);
                        if (isPdf) {
                            proofsHtml += `
                                <div class="proof-thumb proof-open"
                                     data-url="${escapeHtml(url)}"
                                     data-label="${escapeHtml(p.file_name)}"
                                     title="PDF">
                                    <i class="bi bi-file-earmark-pdf-fill pdf-icon"></i>
                                </div>
                            `;
                        } else {
                            proofsHtml += `
                                <div class="proof-thumb proof-open"
                                     data-url="${escapeHtml(url)}"
                                     data-label="${escapeHtml(p.file_name)}"
                                     title="View proof">
                                    <img src="${escapeHtml(url)}" alt="Proof">
                                </div>
                            `;
                        }
                    });
                    proofsHtml += '</div>';
                }

                const remittanceId = parseInt(r.id) || 0;

                html += `
                    <div class="history-item">
                        <div style="flex:1;">
                            <div class="fw-bold">${peso(amt)}</div>
                            <div class="meta">${dateLabel} · Recorded by ${escapeHtml(recordedBy)}</div>
                            ${notesHtml}
                            ${proofsHtml}
                        </div>
                        <div class="d-flex flex-column align-items-end gap-2">
                            <div class="amount">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger remit-delete-btn"
                                    data-remittance-id="${remittanceId}"
                                    data-amount="${peso(amt)}"
                                    data-date="${escapeHtml(dateLabel)}"
                                    title="Delete this remittance">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });

            html += `
                <div class="history-item" style="border-top:2px solid #e2e8f0; margin-top:0.5rem; padding-top:0.85rem;">
                    <div><div class="fw-bold">Total paid</div></div>
                    <div class="amount" style="font-size:0.95rem;">${peso(total)}</div>
                </div>
            `;

            body.innerHTML = html;
        });
    }

    // ── Proof viewer modal (delegated click) ──
    const proofModalEl = document.getElementById('proofModal');
    let proofModal = null;
    if (proofModalEl && typeof bootstrap !== 'undefined') {
        proofModal = new bootstrap.Modal(proofModalEl);
    }

    document.addEventListener('click', function (e) {
        const thumb = e.target.closest('.proof-open');
        if (!thumb || !proofModal) return;

        const url = thumb.getAttribute('data-url');
        const label = thumb.getAttribute('data-label') || 'Proof';
        const isPdf = /\.pdf$/i.test(url);

        const body = document.getElementById('proofModalBody');
        const labelEl = document.getElementById('proofModalLabel');
        const openLink = document.getElementById('proofOpenNewTab');

        labelEl.textContent = label;

        if (isPdf) {
            body.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-file-earmark-pdf-fill" style="font-size:4rem;color:#dc2626;"></i>
                    <p class="text-muted small mt-3 mb-0">PDF file — click "Open in new tab" to view.</p>
                </div>
            `;
        } else {
            body.innerHTML = `<img src="${url}" alt="Proof of payment" class="proof-viewer-img">`;
        }

        openLink.href = url;
        openLink.style.display = 'inline-flex';

        proofModal.show();
    });

    // ── Delete remittance confirmation ──
    const deleteModalEl = document.getElementById('deleteRemitModal');
    let deleteModal = null;
    if (deleteModalEl && typeof bootstrap !== 'undefined') {
        deleteModal = new bootstrap.Modal(deleteModalEl);
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.remit-delete-btn');
        if (!btn || !deleteModal) return;

        document.getElementById('deleteRemitId').value = btn.getAttribute('data-remittance-id');
        document.getElementById('deleteRemitAmount').textContent = btn.getAttribute('data-amount');
        document.getElementById('deleteRemitDate').textContent = btn.getAttribute('data-date');

        deleteModal.show();
    });

    // ── Client-side search ──
    const searchInput = document.getElementById('branchSearch');
    const branchCols  = document.querySelectorAll('.branch-col');
    const emptyResult = document.getElementById('noSearchResults');
    if (searchInput && branchCols.length) {
        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            let visible = 0;
            branchCols.forEach(col => {
                const haystack = col.getAttribute('data-search') || '';
                const match = q === '' || haystack.includes(q);
                col.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            if (emptyResult) {
                emptyResult.classList.toggle('d-none', visible !== 0 || q === '');
            }
        });
    }
});
</script>