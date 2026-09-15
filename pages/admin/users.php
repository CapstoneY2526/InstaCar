<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth Check - Strictly Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied.";
    header("Location: ../../index.php");
    exit();
}

$role_filter = isset($_GET['role']) ? $_GET['role'] : 'user';
$pageTitle = 'Manage ' . ucfirst($role_filter) . 's';

$users = [];

// Fetch all active branches for dropdowns
$branches_list = [];
$brRes = mysqli_query($conn, "SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC");
if ($brRes) {
    while ($bRow = mysqli_fetch_assoc($brRes)) {
        $branches_list[] = $bRow;
    }
}

// Helper: find a branch name by id
if (!function_exists('branchNameById')) {
    function branchNameById($id, $list) {
        foreach ($list as $b) {
            if ((int)$b['id'] === (int)$id) return $b['name'];
        }
        return null;
    }
}

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE role = ? ORDER BY id DESC");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $role_filter);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $users[] = $row;
        }
    } else {
        die("Result fetching failed: " . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
} else {
    die("Statement preparation failed: " . mysqli_error($conn));
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    /* ========================================================
       BASE LIGHT/DARK LAYOUT & COMPONENT OVERRIDES
       ======================================================== */
    .main-content {
        background-color: var(--brand-bg, #f8fafc);
        min-height: 100vh;
        transition: background-color 0.25s ease, color 0.25s ease;
    }

    /* Brand Action Button Styling */
    .btn-brand {
        background-color: var(--brand-yellow, #ffcc00) !important;
        color: #000000 !important;
        border: none !important;
        font-weight: 600 !important;
        padding: 0.6rem 1.25rem !important;
        border-radius: 10px !important;
        transition: all 0.2s ease !important;
    }

    .btn-brand i, 
    .btn-brand span {
        color: #000000 !important;
    }

    .btn-brand:hover {
        background-color: #e6b800 !important;
        color: #000000 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.25) !important;
    }

    /* Card */
    .user-card {
        background-color: #ffffff;
        border: 1px solid var(--brand-border, #e2e8f0);
        border-radius: var(--card-radius, 16px);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.25s ease;
    }

    .user-card:hover {
        transform: translateY(-2px);
        border-color: var(--brand-yellow, #ffcc00);
        box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.05);
    }

    .user-avatar-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background-color: #f1f5f9;
        color: var(--brand-muted, #64748b);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    /* ---------- NEW: search bar & header polish ---------- */
    .users-toolbar {
        background: #ffffff;
        border: 1px solid var(--brand-border, #e2e8f0);
        border-radius: 14px;
        padding: 0.85rem 1rem;
        transition: background-color 0.25s ease, border-color 0.25s ease;
    }

    .users-toolbar .search-wrap {
        position: relative;
    }

    .users-toolbar .search-wrap i {
        position: absolute;
        top: 50%;
        left: 0.9rem;
        transform: translateY(-50%);
        color: #94a3b8;
        pointer-events: none;
    }

    .users-toolbar .search-wrap input {
        padding-left: 2.4rem;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        transition: all 0.2s ease;
    }

    .users-toolbar .search-wrap input:focus {
        background: #ffffff;
        border-color: #ffcc00;
        box-shadow: 0 0 0 0.2rem rgba(255, 204, 0, 0.15);
    }

    .role-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.28rem 0.65rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        background: rgba(255, 204, 0, 0.15);
        color: #926c00;
        text-transform: capitalize;
    }

    .meta-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        color: #64748b;
        padding: 0.15rem 0;
    }

    .meta-row i {
        width: 16px;
        text-align: center;
        color: #94a3b8;
    }

    .empty-state {
        background: #ffffff;
        border: 1px dashed #cbd5e1;
        border-radius: 16px;
    }

    /* ========================================================
       DARK MODE COMPLETE OVERRIDES
       ======================================================== */
    body.dark-mode,
    body.dark-mode .main-content {
        background-color: #0a0a0a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode header,
    body.dark-mode navbar,
    body.dark-mode .navbar,
    body.dark-mode footer,
    body.dark-mode .footer {
        background-color: #141414 !important;
        border-color: #27272a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode footer p,
    body.dark-mode header span,
    body.dark-mode header p {
        color: #a1a1aa !important;
    }

    body.dark-mode .btn-brand {
        background-color: #ffcc00 !important;
        color: #000000 !important;
    }
    body.dark-mode .btn-brand i,
    body.dark-mode .btn-brand span {
        color: #000000 !important;
    }
    body.dark-mode .btn-brand:hover {
        background-color: #ffd633 !important;
        box-shadow: 0 4px 15px rgba(255, 204, 0, 0.35) !important;
    }

    body.dark-mode .user-card {
        background-color: #141414 !important;
        border-color: #27272a !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .user-card:hover {
        border-color: #ffcc00 !important;
        box-shadow: 0 10px 20px -5px rgba(255, 204, 0, 0.1) !important;
    }

    body.dark-mode .user-avatar-icon {
        background-color: #1f1f1f !important;
        color: #ffcc00 !important;
    }

    body.dark-mode .text-dark,
    body.dark-mode h3,
    body.dark-mode h5,
    body.dark-mode h6,
    body.dark-mode label {
        color: #ffffff !important;
    }

    body.dark-mode .text-muted,
    body.dark-mode .text-secondary {
        color: #a1a1aa !important;
    }

    body.dark-mode .border-light,
    body.dark-mode hr {
        border-color: #27272a !important;
        opacity: 1 !important;
    }

    body.dark-mode .modal-content {
        background-color: #141414 !important;
        border: 1px solid #27272a !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .modal-header,
    body.dark-mode .modal-footer {
        border-color: #27272a !important;
    }
    body.dark-mode .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    body.dark-mode .form-control,
    body.dark-mode .form-select {
        background-color: #0a0a0a !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    body.dark-mode .form-control::placeholder {
        color: #6b7280 !important;
    }
    body.dark-mode .form-control:focus,
    body.dark-mode .form-select:focus {
        border-color: #ffcc00 !important;
        box-shadow: 0 0 0 0.25rem rgba(255, 204, 0, 0.15) !important;
    }

    body.dark-mode .btn-light {
        background-color: #1f1f1f !important;
        color: #f1f5f9 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .btn-light:hover {
        background-color: #2a2a2a !important;
        border-color: #ffcc00 !important;
    }

    body.dark-mode .btn-outline-danger {
        color: #ef4444 !important;
        border-color: #7f1d1d !important;
    }
    body.dark-mode .btn-outline-danger:hover {
        background-color: #dc2626 !important;
        color: #ffffff !important;
    }

    /* New toolbar dark mode */
    body.dark-mode .users-toolbar {
        background: #141414 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .users-toolbar .search-wrap input {
        background: #0a0a0a !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    body.dark-mode .users-toolbar .search-wrap input::placeholder {
        color: #6b7280 !important;
    }
    body.dark-mode .users-toolbar .search-wrap input:focus {
        background: #0a0a0a !important;
        border-color: #ffcc00 !important;
    }
    body.dark-mode .users-toolbar .search-wrap i {
        color: #6b7280;
    }
    body.dark-mode .role-pill {
        background: rgba(255, 204, 0, 0.12);
        color: #ffcc00;
    }
    body.dark-mode .meta-row {
        color: #cbd5e1;
    }
    body.dark-mode .meta-row i {
        color: #6b7280;
    }
    body.dark-mode .empty-state {
        background: #141414;
        border-color: #27272a;
    }

    @media (max-width: 768px) {
        .main-content h3 { font-size: 1.25rem; }
        .main-content p { font-size: 0.85rem; }
        .user-card { padding: 1rem !important; }
    }
</style>

<div class="container-fluid p-0">
    <div class="row g-0">
        <div class="col-md-auto p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col p-0 d-flex flex-column main-content">
            
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">

                <!-- Header -->
                <div class="header-title-wrapper d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-1 text-dark"><?= ucfirst($role_filter) ?> Management</h3>
                        <p class="text-muted mb-0 small">
                            <i class="bi bi-people-fill me-1"></i>
                            Total: <strong><?= count($users) ?></strong> registered account<?= count($users) === 1 ? '' : 's' ?>.
                        </p>
                    </div>
                    <div>
                        <button class="btn btn-brand d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-person-plus-fill"></i>
                            <span>Add New <?= ucfirst($role_filter) ?></span>
                        </button>
                    </div>
                </div>

                <!-- Toolbar: search + count -->
                <div class="users-toolbar d-flex flex-column flex-sm-row gap-2 align-items-sm-center justify-content-between mb-4">
                    <div class="search-wrap flex-grow-1" style="max-width: 420px;">
                        <i class="bi bi-search"></i>
                        <input type="text" id="userSearch" class="form-control form-control-sm" placeholder="Search by name, email, or phone…">
                    </div>
                    <div class="text-muted small">
                        Showing <span id="visibleCount"><?= count($users) ?></span> of <?= count($users) ?>
                    </div>
                </div>

                <?php if (empty($users)): ?>
                    <div class="empty-state p-5 text-center text-muted">
                        <i class="bi bi-people mb-2 fs-1 text-secondary d-block"></i>
                        <p class="mb-1 fw-semibold">No <?= htmlspecialchars($role_filter) ?>s found.</p>
                        <p class="mb-0 small">Click <em>Add New <?= ucfirst($role_filter) ?></em> to create one.</p>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3" id="userGrid">
                        <?php foreach ($users as $user):
                            $bName = branchNameById($user['branch_id'] ?? null, $branches_list);
                            $searchable = strtolower($user['name'] . ' ' . $user['email'] . ' ' . ($user['phone'] ?? ''));
                        ?>
                            <div class="col user-col" data-search="<?= htmlspecialchars($searchable) ?>">
                                <div class="card user-card h-100 position-relative overflow-hidden">
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="user-avatar-icon me-3">
                                                    <i class="bi bi-person fs-5"></i>
                                                </div>
                                                <div class="overflow-hidden flex-grow-1">
                                                    <h6 class="fw-bold text-dark text-truncate mb-1" title="<?= htmlspecialchars($user['name']) ?>">
                                                        <?= htmlspecialchars($user['name']) ?>
                                                    </h6>
                                                    <span class="role-pill">
                                                        <i class="bi bi-shield-check"></i>
                                                        <?= htmlspecialchars($role_filter) ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <hr class="my-3">

                                            <div class="meta-row">
                                                <i class="bi bi-envelope"></i>
                                                <span class="text-truncate" title="<?= htmlspecialchars($user['email']) ?>">
                                                    <?= htmlspecialchars($user['email']) ?>
                                                </span>
                                            </div>

                                            <div class="meta-row">
                                                <i class="bi bi-telephone"></i>
                                                <span><?= htmlspecialchars($user['phone'] ?? 'No phone') ?></span>
                                            </div>

                                            <div class="meta-row">
                                                <i class="bi bi-shop"></i>
                                                <span>
                                                    <?= $bName ? htmlspecialchars($bName) : '<em class="text-muted">No branch</em>' ?>
                                                </span>
                                            </div>

                                            <div class="meta-row">
                                                <i class="bi bi-calendar-check"></i>
                                                <span>Joined <?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                                            </div>

                                            <?php if (isset($user['is_verified']) && (int)$user['is_verified'] === 0): ?>
                                                <div class="mt-3">
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="bi bi-exclamation-triangle me-1"></i>Unverified
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top">
                                            <button class="btn btn-sm btn-light border rounded-3 px-3" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $user['id'];?>">
                                                <i class="bi bi-pencil me-1"></i> Edit
                                            </button>
                                            <a href="process/user_actions.php?delete=<?= $user['id'] ?>&role=<?= htmlspecialchars($role_filter) ?>" 
                                               class="btn btn-sm btn-outline-danger rounded-3 px-3" 
                                               onclick="return confirm('Are you sure you want to delete this user?')">
                                                <i class="bi bi-trash me-1"></i> Delete
                                            </a>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="col-12 d-none" id="noResults">
                            <div class="empty-state p-5 text-center text-muted">
                                <i class="bi bi-search mb-2 fs-3 d-block"></i>
                                <p class="mb-0 small">No accounts match your search.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>

<!-- Edit User Modals -->
<?php foreach ($users as $user): ?>
<div class="modal fade" id="editUserModal<?= $user['id'];?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process/user_actions.php" method="POST" class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit <?= ucfirst($role_filter) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" value="<?= $user['id'];?>">
                <input type="hidden" name="role" value="<?= htmlspecialchars($role_filter) ?>">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold">Full Name</label>
                    <input type="text" name="name" class="form-control rounded-3" value="<?= htmlspecialchars($user['name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Email Address</label>
                    <input type="email" name="email" class="form-control rounded-3" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Phone Number</label>
                    <input type="text" name="phone" class="form-control rounded-3" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold d-flex justify-content-between align-items-center">
                        <span>Password</span>
                        <span class="text-muted fw-normal" style="font-size: 0.72rem;">Leave blank to keep current</span>
                    </label>
                    <div class="input-group">
                        <input type="password" name="password" class="form-control rounded-start-3" placeholder="New password (optional)" autocomplete="new-password">
                        <button type="button" class="btn btn-light border toggle-pw" tabindex="-1">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Branch</label>
                    <select name="branch_id" class="form-select rounded-3">
                        <option value="">— No Branch —</option>
                        <?php foreach ($branches_list as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= ((int)($user['branch_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_user" class="btn btn-brand rounded-3 px-4">Update Account</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process/user_actions.php" method="POST" class="modal-content shadow-lg border-0 rounded-4">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">New <?= ucfirst($role_filter) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="role" value="<?= htmlspecialchars($role_filter) ?>">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Full Name</label>
                    <input type="text" name="name" class="form-control rounded-3" placeholder="John Doe" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Email Address</label>
                    <input type="email" name="email" class="form-control rounded-3" placeholder="name@example.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Password</label>
                    <div class="input-group">
                        <input type="password" name="password" class="form-control rounded-start-3" placeholder="••••••••" required autocomplete="new-password">
                        <button type="button" class="btn btn-light border toggle-pw" tabindex="-1">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Phone Number</label>
                    <input type="text" name="phone" class="form-control rounded-3" placeholder="09123456789">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Branch</label>
                    <select name="branch_id" class="form-select rounded-3">
                        <option value="">— No Branch —</option>
                        <?php foreach ($branches_list as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Close</button>
                <button type="submit" name="add_user" class="btn btn-brand rounded-3 px-4">Save Account</button>
            </div>
        </form>
    </div>
</div>

<script>
// ---- Live search filter ----
(function () {
    const input = document.getElementById('userSearch');
    const grid  = document.getElementById('userGrid');
    const noRes = document.getElementById('noResults');
    const visibleCount = document.getElementById('visibleCount');
    if (!input || !grid) return;

    const cols = grid.querySelectorAll('.user-col');
    const total = cols.length;

    input.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        let shown = 0;

        cols.forEach(col => {
            const hay = col.getAttribute('data-search') || '';
            const match = q === '' || hay.includes(q);
            col.classList.toggle('d-none', !match);
            if (match) shown++;
        });

        visibleCount.textContent = shown;
        if (noRes) noRes.classList.toggle('d-none', shown !== 0);
    });
})();

// ---- Password visibility toggles ----
document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', function () {
        const wrap = this.closest('.input-group');
        const inp  = wrap ? wrap.querySelector('input') : null;
        if (!inp) return;
        const show = inp.type === 'password';
        inp.type = show ? 'text' : 'password';
        const icon = this.querySelector('i');
        if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
});
</script>