<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth Check - Using your preferred JS redirect
if (!isset($_SESSION['user_id'])) {
    ?>
    <script>window.location.href = "../../index.php";</script>
    <?php
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, email, role FROM users WHERE id = $user_id LIMIT 1"));

$pageTitle = 'Account Settings';
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col-md-10 p-0 d-flex flex-column main-content" style="background: #f8fafc; min-height: 100vh;">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-4">
                <div class="mb-4">
                    <h3 class="fw-bold mb-0">My Profile</h3>
                    <p class="text-muted">Manage your account information and security.</p>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white py-3 border-0">
                                <h5 class="fw-bold mb-0">Personal Information</h5>
                            </div>
                            <div class="card-body p-4 pt-0">
                                <form action="process/profile_actions.php" method="POST">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Full Name</label>
                                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Email Address</label>
                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Account Role</label>
                                        <input type="text" class="form-control bg-light" value="<?= ucfirst($user['role']) ?>" readonly>
                                        <div class="form-text">Role cannot be changed by the user.</div>
                                    </div>
                                    <button type="submit" name="update_info" class="btn btn-primary fw-bold px-4">Update Info</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-3 border-0">
                                <h5 class="fw-bold mb-0">Security</h5>
                            </div>
                            <div class="card-body p-4 pt-0">
                                <form action="process/profile_actions.php" method="POST">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Current Password</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">New Password</label>
                                        <input type="password" name="new_password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                    <button type="submit" name="update_password" class="btn btn-dark fw-bold px-4">Change Password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>