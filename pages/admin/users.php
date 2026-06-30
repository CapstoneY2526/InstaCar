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
    /* Mobile-responsive font scaling */
    @media (max-width: 768px) {
        .main-content h3 { font-size: 1.25rem; }
        .main-content p { font-size: 0.85rem; }
        .table thead th { font-size: 0.7rem !important; padding: 10px !important; }
        .table tbody td { font-size: 0.8rem !important; padding: 10px !important; }
        .btn-primary { font-size: 0.8rem; padding: 8px 12px; }
        .user-name { font-size: 0.85rem !important; }
        
        .col-joined, .col-phone { display: none; }

        .dataTables_length select {
            width: 70px !important;
            padding: 4px 8px !important;
            font-size: 0.8rem !important;
        }

        .dataTables_length label {
            font-size: 0.8rem !important;
        }

        .dataTables_filter input {
            width: 140px !important;
            font-size: 0.8rem !important;
        }

    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-auto p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col p-0 d-flex flex-column main-content" style="background: #f8fafc; min-height: 100vh;">
            
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-0"><?= ucfirst($role_filter) ?> Management</h3>
                        <p class="text-muted mb-0">Total: <?= count($users) ?> registered accounts.</p>
                    </div>
                    <button class="btn btn-primary shadow-sm rounded-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus-fill me-2"></i>Add New
                    </button>
                </div>

                <?php if (empty($users)): ?>
                    <div class="card shadow-sm border-0 rounded-4 p-5 text-center text-muted">
                        <i class="bi bi-people mb-2 fs-2 text-secondary"></i>
                        <p class="mb-0">No <?= $role_filter ?>s found.</p>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
                        <?php foreach ($users as $user): ?>
                            <div class="col">
                                <div class="card h-100 shadow-sm border-0 rounded-4 position-relative overflow-hidden">
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        
                                        <div>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 44px; height: 44px; flex-shrink: 0;">
                                                    <i class="bi bi-person text-secondary fs-5"></i>
                                                </div>
                                                <div class="overflow-hidden">
                                                    <h6 class="fw-bold text-dark text-truncate mb-0" title="<?= htmlspecialchars($user['name']) ?>">
                                                        <?= htmlspecialchars($user['name']) ?>
                                                    </h6>
                                                    <small class="text-muted text-uppercase tracking-wider" style="font-size: 0.75rem;">
                                                        <?= htmlspecialchars($role_filter) ?>
                                                    </small>
                                                </div>
                                            </div>
                                            
                                            <hr class="text-muted opacity-25 my-3">

                                            <div class="mb-2 d-flex align-items-center gap-2 text-secondary small">
                                                <i class="bi bi-envelope text-muted"></i>
                                                <span class="text-truncate" title="<?= htmlspecialchars($user['email']) ?>">
                                                    <?= htmlspecialchars($user['email']) ?>
                                                </span>
                                            </div>

                                            <div class="mb-2 d-flex align-items-center gap-2 text-secondary small">
                                                <i class="bi bi-telephone text-muted"></i>
                                                <span><?= htmlspecialchars($user['phone'] ?? 'No Phone') ?></span>
                                            </div>

                                            <div class="d-flex align-items-center gap-2 text-secondary small">
                                                <i class="bi bi-calendar-check text-muted"></i>
                                                <span>Joined <?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top border-light">
                                            <button class="btn btn-sm btn-light border rounded-3 px-3" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $user['id'];?>">
                                                <i class="bi bi-pencil me-1"></i> Edit
                                            </button>
                                            <a href="process/user_actions.php?delete=<?= $user['id'] ?>&role=<?= $role_filter ?>" 
                                               class="btn btn-sm btn-outline-danger rounded-3 px-3" 
                                               onclick="return confirm('Are you sure?')">
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

<?php foreach ($users as $user): ?>
<div class="modal fade" id="editUserModal<?= $user['id'];?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process/user_actions.php" method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Edit <?= ucfirst($role_filter) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_user" class="btn btn-primary rounded-3 px-4">Update Account</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process/user_actions.php" method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">New <?= ucfirst($role_filter) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Close</button>
                <button type="submit" name="add_user" class="btn btn-primary rounded-3 px-4">Save Account</button>
            </div>
        </form>
    </div>
</div>