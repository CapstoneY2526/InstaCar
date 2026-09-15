<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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

$pageTitle = 'Branches';

// ---- Fetch all branches with counts ----
$branches = [];
$q = "
    SELECT 
        b.id,
        b.name,
        b.address,
        b.phone,
        b.is_active,
        b.created_at,
        (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.id AND u.role IN ('staff','operator')) AS staff_count,
        (SELECT COUNT(*) FROM cars c WHERE c.branch_id = b.id) AS car_count,
        (SELECT COUNT(*) FROM bookings bk WHERE bk.branch_id = b.id) AS booking_count
    FROM branches b
    ORDER BY b.is_active DESC, b.name ASC
";
$r = mysqli_query($conn, $q);
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $branches[] = $row;
    }
}

$total_branches = count($branches);
$active_branches = 0;
foreach ($branches as $b) {
    if ((int)$b['is_active'] === 1) $active_branches++;
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --brand-yellow: #ffcc00;
        --brand-yellow-hover: #e6b800;
        --brand-yellow-soft: rgba(255, 204, 0, 0.12);
        --brand-black: #0a0a0a;
        --brand-ink: #1e293b;
        --brand-muted: #64748b;
        --brand-border: #e2e8f0;
        --brand-bg: #f8fafc;
        --brand-card-bg-dark: #141414;
        --brand-border-dark: #27272a;
        --brand-row-bg-dark: #1a1a1a;
        --card-radius: 16px;
    }
    * { box-sizing: border-box; }
    html, body { width: 100%; max-width: 100%; overflow-x: hidden; }
    body {
        background-color: var(--brand-bg);
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: var(--brand-ink);
        transition: background-color 0.25s ease, color 0.25s ease;
    }
    .main-content { min-width: 0; min-height: 100vh; width: 100%; overflow-x: hidden; }

    .header-title-wrapper {
        padding-bottom: 1.25rem;
        border-bottom: 2px solid var(--brand-yellow-soft);
    }
    .text-brand-yellow { color: #b38a00 !important; }
    body.dark-mode .text-brand-yellow { color: var(--brand-yellow) !important; }

    /* ---- Brand button ---- */
    .btn-brand {
        background-color: var(--brand-yellow) !important;
        color: #000000 !important;
        font-weight: 700 !important;
        border: none !important;
        border-radius: 12px;
        padding: 0.6rem 1.25rem;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(255, 204, 0, 0.2);
    }
    .btn-brand:hover {
        background-color: var(--brand-yellow-hover) !important;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(255, 204, 0, 0.4);
    }
    .btn-brand:active {
        transform: translateY(0);
        box-shadow: 0 2px 6px rgba(255, 204, 0, 0.3);
    }

    /* ---- Stat Cards ---- */
    .stat-card {
        background: #ffffff;
        border-radius: var(--card-radius);
        padding: 1.25rem;
        border: 1px solid var(--brand-border);
        height: 100%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        transition: all 0.25s ease;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        border-color: var(--brand-yellow);
        box-shadow: 0 12px 20px -8px rgba(255, 204, 0, 0.25);
    }
    .stat-icon {
        width: 44px; height: 44px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
        transition: all 0.2s ease;
    }
    .stat-icon.brand-icon {
        background: var(--brand-yellow-soft);
        color: #b38a00;
    }
    body.dark-mode .stat-icon.brand-icon {
        background: rgba(255, 204, 0, 0.15);
        color: var(--brand-yellow);
    }

    .stat-value { font-size: 1.5rem; font-weight: 800; color: var(--brand-ink); line-height: 1.2; }
    .stat-label {
        font-size: 0.72rem; text-transform: uppercase;
        letter-spacing: 0.6px; color: var(--brand-muted);
        font-weight: 700; margin-top: 2px;
    }

    /* ---- Branch Cards ---- */
    .branch-card {
        background: #ffffff;
        border-radius: var(--card-radius);
        border: 1px solid var(--brand-border);
        padding: 1.25rem;
        transition: all 0.25s ease;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        height: 100%;
    }
    .branch-card:hover {
        border-color: var(--brand-yellow);
        box-shadow: 0 12px 24px -10px rgba(255, 204, 0, 0.3);
        transform: translateY(-3px);
    }
    .branch-card.is-inactive {
        opacity: 0.7;
    }
    .branch-card.is-inactive:hover {
        opacity: 1;
    }

    .branch-icon {
        width: 48px; height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--brand-yellow), #ffe066);
        color: #000000;
        font-weight: 800;
        font-size: 1.15rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.3);
        transition: transform 0.25s ease;
    }
    .branch-card:hover .branch-icon {
        transform: scale(1.06) rotate(-3deg);
    }

    .branch-name { font-size: 1.05rem; font-weight: 800; margin: 0; line-height: 1.2; }
    .branch-meta { font-size: 0.78rem; color: var(--brand-muted); margin: 0; }

    .branch-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        padding-top: 0.9rem;
        border-top: 1px solid var(--brand-border);
    }
    .branch-stats .item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        padding: 4px 2px;
        border-radius: 8px;
        transition: background-color 0.2s ease;
    }
    .branch-stats .item:hover {
        background: var(--brand-yellow-soft);
    }
    .branch-stats .num { font-size: 1.1rem; font-weight: 800; color: var(--brand-ink); }
    .branch-stats .lbl {
        font-size: 0.62rem; text-transform: uppercase;
        letter-spacing: 0.05em; color: var(--brand-muted);
        font-weight: 700; text-align: center; line-height: 1.15;
    }

    /* ---- Status pill ---- */
    .pill {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 10px; border-radius: 20px;
        font-size: 0.68rem; font-weight: 700;
    }
    .pill.is-active { background: #dcfce7; color: #15803d; }
    .pill.is-inactive { background: #fee2e2; color: #b91c1c; }
    body.dark-mode .pill.is-active { background: rgba(34,197,94,.15); color: #86efac; }
    body.dark-mode .pill.is-inactive { background: rgba(239,68,68,.15); color: #fca5a5; }

    /* ---- Action buttons on branch cards ---- */
    .branch-actions {
        display: flex;
        gap: 8px;
        padding-top: 0.75rem;
        border-top: 1px solid var(--brand-border);
    }

    .branch-btn {
        flex: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 10px;
        font-size: 0.8rem;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1.5px solid transparent;
        white-space: nowrap;
    }

    /* Edit button — light theme */
    .branch-btn.edit-btn {
        background: #f1f5f9;
        color: #334155;
        border-color: #e2e8f0;
    }
    .branch-btn.edit-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
        border-color: #cbd5e1;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
    }
    body.dark-mode .branch-btn.edit-btn {
        background: #27272a;
        color: #e2e8f0;
        border-color: #3f3f46;
    }
    body.dark-mode .branch-btn.edit-btn:hover {
        background: #3f3f46;
        color: #ffffff;
        border-color: #52525b;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    }

    /* Deactivate button — warning/danger tone */
    .branch-btn.deactivate-btn {
        background: #fef3c7;
        color: #92400e;
        border-color: #fde68a;
    }
    .branch-btn.deactivate-btn:hover {
        background: #f59e0b;
        color: #ffffff;
        border-color: #f59e0b;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.35);
    }
    body.dark-mode .branch-btn.deactivate-btn {
        background: rgba(245, 158, 11, 0.15);
        color: #fbbf24;
        border-color: rgba(245, 158, 11, 0.35);
    }
    body.dark-mode .branch-btn.deactivate-btn:hover {
        background: #f59e0b;
        color: #000000;
        border-color: #f59e0b;
        box-shadow: 0 4px 14px rgba(245, 158, 11, 0.5);
    }

    /* Activate button — success tone */
    .branch-btn.activate-btn {
        background: #dcfce7;
        color: #15803d;
        border-color: #bbf7d0;
    }
    .branch-btn.activate-btn:hover {
        background: #22c55e;
        color: #ffffff;
        border-color: #22c55e;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(34, 197, 94, 0.35);
    }
    body.dark-mode .branch-btn.activate-btn {
        background: rgba(34, 197, 94, 0.15);
        color: #86efac;
        border-color: rgba(34, 197, 94, 0.35);
    }
    body.dark-mode .branch-btn.activate-btn:hover {
        background: #22c55e;
        color: #000000;
        border-color: #22c55e;
        box-shadow: 0 4px 14px rgba(34, 197, 94, 0.5);
    }

    /* ---- Empty state ---- */
    .empty-state {
        text-align: center; padding: 4rem 1rem; color: var(--brand-muted);
    }
    .empty-state i { font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 1rem; }

    /* ---- Modal polish ---- */
    .modal-content {
        border-radius: var(--card-radius) !important;
        border: 1px solid var(--brand-border);
        background: #ffffff;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
    }
    .modal-header .btn-close {
        transition: transform 0.2s ease;
    }
    .modal-header .btn-close:hover {
        transform: rotate(90deg);
    }

    /* ---- Dark mode ---- */
    body.dark-mode { background-color: var(--brand-black) !important; color: #f1f5f9 !important; }
    body.dark-mode .main-content { background-color: var(--brand-black) !important; }
    body.dark-mode header, body.dark-mode nav, body.dark-mode .navbar {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode footer, body.dark-mode .footer {
        background-color: var(--brand-black) !important;
        border-color: var(--brand-border-dark) !important;
        color: #cbd5e1 !important;
    }
    body.dark-mode .stat-card,
    body.dark-mode .branch-card,
    body.dark-mode .modal-content {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .stat-card:hover,
    body.dark-mode .branch-card:hover {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 10px 24px rgba(255, 204, 0, 0.15) !important;
    }
    body.dark-mode .stat-value,
    body.dark-mode .branch-name,
    body.dark-mode .branch-stats .num,
    body.dark-mode h1, body.dark-mode h2, body.dark-mode h3,
    body.dark-mode h4, body.dark-mode h5, body.dark-mode h6,
    body.dark-mode .text-dark { color: #ffffff !important; }
    body.dark-mode .stat-label,
    body.dark-mode .branch-meta,
    body.dark-mode .branch-stats .lbl,
    body.dark-mode .text-muted { color: #cbd5e1 !important; }
    body.dark-mode .branch-stats,
    body.dark-mode .branch-actions { border-color: var(--brand-border-dark); }
    body.dark-mode .branch-stats .item:hover { background: rgba(255, 204, 0, 0.08); }
    body.dark-mode .form-control, body.dark-mode .form-select {
        background-color: #1a1a1a !important;
        border-color: #3f3f46 !important;
        color: #ffffff !important;
    }
    body.dark-mode .form-control::placeholder,
    body.dark-mode textarea::placeholder {
        color: #94a3b8 !important;
        opacity: 1 !important;
    }
    body.dark-mode .form-control:focus, body.dark-mode .form-select:focus {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.15) !important;
    }
    body.dark-mode .form-label { color: #cbd5e1 !important; }
    body.dark-mode .modal-header, body.dark-mode .modal-footer, body.dark-mode .modal-body {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
    }
    body.dark-mode .modal-header .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }

    /* Small phone tweaks */
    @media (max-width: 575.98px) {
        .branch-actions {
            flex-direction: column;
            gap: 6px;
        }
        .branch-btn {
            padding: 9px 12px;
        }
    }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        <div class="col-auto p-0" style="width: 0; overflow: visible;">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">

                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4 header-title-wrapper">
                    <div>
                        <h3 class="fw-bold mb-0">Branch <span class="text-brand-yellow">Management</span></h3>
                        <p class="text-muted small mb-0">Manage physical locations and assign staff, cars, and bookings.</p>
                    </div>
                    <button type="button" class="btn btn-brand d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addBranchModal">
                        <i class="bi bi-plus-circle-fill me-2"></i>New Branch
                    </button>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon brand-icon">
                                    <i class="bi bi-shop"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($total_branches) ?></div>
                                    <div class="stat-label">Total Branches</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($active_branches) ?></div>
                                    <div class="stat-label">Active</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($branches)): ?>
                    <div class="card border-0 shadow-sm empty-state" style="border-radius: var(--card-radius);">
                        <i class="bi bi-shop"></i>
                        <h5 class="fw-bold">No branches yet</h5>
                        <p class="mb-0 small">Click "New Branch" to create your first location.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($branches as $b): ?>
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="branch-card <?= (int)$b['is_active'] === 1 ? '' : 'is-inactive' ?>">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="branch-icon">
                                            <i class="bi bi-shop"></i>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <h6 class="branch-name text-truncate"><?= htmlspecialchars($b['name']) ?></h6>
                                            <p class="branch-meta text-truncate mb-1">
                                                <?= htmlspecialchars($b['address'] ?: 'No address set') ?>
                                            </p>
                                            <span class="pill <?= (int)$b['is_active'] === 1 ? 'is-active' : 'is-inactive' ?>">
                                                <i class="bi bi-circle-fill" style="font-size: 6px;"></i>
                                                <?= (int)$b['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="branch-stats">
                                        <div class="item">
                                            <span class="num"><?= number_format($b['staff_count']) ?></span>
                                            <span class="lbl">Staff / Operators</span>
                                        </div>
                                        <div class="item">
                                            <span class="num"><?= number_format($b['car_count']) ?></span>
                                            <span class="lbl">Cars</span>
                                        </div>
                                        <div class="item">
                                            <span class="num"><?= number_format($b['booking_count']) ?></span>
                                            <span class="lbl">Bookings</span>
                                        </div>
                                    </div>

                                    <div class="branch-actions">
                                        <button type="button" class="branch-btn edit-btn"
                                                data-bs-toggle="modal" data-bs-target="#editBranchModal<?= $b['id'] ?>">
                                            <i class="bi bi-pencil-square"></i>Edit
                                        </button>
                                        <?php if ((int)$b['is_active'] === 1): ?>
                                            <a href="process/branch_actions.php?toggle=<?= $b['id'] ?>" 
                                               class="branch-btn deactivate-btn"
                                               onclick="return confirm('Deactivate this branch?');">
                                                <i class="bi bi-pause-circle"></i>Deactivate
                                            </a>
                                        <?php else: ?>
                                            <a href="process/branch_actions.php?toggle=<?= $b['id'] ?>" 
                                               class="branch-btn activate-btn"
                                               onclick="return confirm('Activate this branch?');">
                                                <i class="bi bi-play-circle"></i>Activate
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>

<!-- ADD BRANCH MODAL -->
<div class="modal fade" id="addBranchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process/branch_actions.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius: var(--card-radius);">
            <input type="hidden" name="action" value="add">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-plus-circle me-2 text-brand-yellow"></i>New Branch
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Branch Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Cebu City" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Address</label>
                    <input type="text" name="address" class="form-control" placeholder="Full address">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted">Phone</label>
                    <input type="text" name="phone" class="form-control" placeholder="Contact number">
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-brand rounded-3 px-4">Save Branch</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODALS -->
<?php foreach ($branches as $b): ?>
    <div class="modal fade" id="editBranchModal<?= $b['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="process/branch_actions.php" method="POST" class="modal-content border-0 shadow-lg" style="border-radius: var(--card-radius);">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-pencil me-2 text-brand-yellow"></i>Edit Branch
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Branch Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($b['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Address</label>
                        <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($b['address'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($b['phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand rounded-3 px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>