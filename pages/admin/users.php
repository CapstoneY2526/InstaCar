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

    /* ========================================================
       DARK MODE COMPLETE OVERRIDES
       ======================================================== */
    body.dark-mode,
    body.dark-mode .main-content {
        background-color: #0a0a0a !important;
        color: #f1f5f9 !important;
    }

    /* Header & Footer Components */
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

    /* Keep Brand Button High Contrast in Dark Mode */
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

    /* Cards */
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

    /* Text & Typography */
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

    /* Modals & Inputs */
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

    body.dark-mode .form-control {
        background-color: #0a0a0a !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }

    body.dark-mode .form-control:focus {
        border-color: #ffcc00 !important;
        box-shadow: 0 0 0 0.25rem rgba(255, 204, 0, 0.15) !important;
    }

    /* Action Buttons */
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
                <div class="header-title-wrapper d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark"><?= ucfirst($role_filter) ?> Management</h3>
                        <p class="text-muted mb-0">Total: <?= count($users) ?> registered accounts.</p>
                    </div>
                    <div>
                        <button class="btn btn-brand d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-person-plus-fill"></i>
                            <span>Add New <?= ucfirst($role_filter) ?></span>
                        </button>
                    </div>
                </div>

                <?php if (empty($users)): ?>
                    <div class="card user-card p-5 text-center text-muted shadow-sm">
                        <i class="bi bi-people mb-2 fs-1 text-secondary"></i>
                        <p class="mb-0 fw-semibold">No <?= htmlspecialchars($role_filter) ?>s found in the database.</p>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
                        <?php foreach ($users as $user): ?>
                            <div class="col">
                                <div class="card user-card h-100 position-relative overflow-hidden">
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="user-avatar-icon me-3">
                                                    <i class="bi bi-person fs-5"></i>
                                                </div>
                                                <div class="overflow-hidden">
                                                    <h6 class="fw-bold text-dark text-truncate mb-0" title="<?= htmlspecialchars($user['name']) ?>">
                                                        <?= htmlspecialchars($user['name']) ?>
                                                    </h6>
                                                    <span class="status-badge bg-brand-yellow text-brand-yellow mt-1">
                                                        <?= htmlspecialchars($role_filter) ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <hr class="my-3">

                                            <div class="mb-2 d-flex align-items-center gap-2 text-secondary small">
                                                <i class="bi bi-envelope"></i>
                                                <span class="text-truncate" title="<?= htmlspecialchars($user['email']) ?>">
                                                    <?= htmlspecialchars($user['email']) ?>
                                                </span>
                                            </div>

                                            <div class="mb-2 d-flex align-items-center gap-2 text-secondary small">
                                                <i class="bi bi-telephone"></i>
                                                <span><?= htmlspecialchars($user['phone'] ?? 'No Phone') ?></span>
                                            </div>

                                            <div class="d-flex align-items-center gap-2 text-secondary small">
                                                <i class="bi bi-calendar-check"></i>
                                                <span>Joined <?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top">
                                            <button class="btn btn-sm btn-light border rounded-3 px-3" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $user['id'];?>">
                                                <i class="bi bi-pencil me-1"></i> Edit
                                            </button>
                                            <a href="process/user_actions.php?delete=<?= $user['id'] ?>&role=<?= $role_filter ?>" 
                                               class="btn btn-sm btn-outline-danger rounded-3 px-3" 
                                               onclick="return confirm('Are you sure you want to delete this user?')">
                                                <i class="bi bi-trash me-1"></i> Delete
                                            </a>
                                        </div>

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

<?php initDataTable('userTable'); ?>

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
                <input type="hidden" name="role" value="<?= $role_filter ?>">
                
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
                    <input type="text" name="phone" class="form-control rounded-3" value="<?= htmlspecialchars($user['phone']) ?>">
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
                <input type="hidden" name="role" value="<?= $role_filter ?>">
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
                    <input type="password" name="password" class="form-control rounded-3" placeholder="••••••••" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Phone Number</label>
                    <input type="text" name="phone" class="form-control rounded-3" placeholder="09123456789">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Close</button>
                <button type="submit" name="add_user" class="btn btn-brand rounded-3 px-4">Save Account</button>
            </div>
        </form>
    </div>
</div>