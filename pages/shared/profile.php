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

// QR Code File Path Check
$qr_file_path = __DIR__ . '/../../public/assets/images/qr_code_payment.png';
$qr_image_exists = file_exists($qr_file_path);
$qr_image_url = "../../public/assets/images/qr_code_payment.png" . ($qr_image_exists ? '?v=' . time() : '');
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
                    <h3 class="fw-bold mb-0">My Profile & Settings</h3>
                    <p class="text-muted">Manage your account information, security, and payment settings.</p>
                </div>

                <!-- SESSION FLASH MESSAGES -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-1"></i> <?= htmlspecialchars($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- PERSONAL INFORMATION -->
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white py-3 border-0">
                                <h5 class="fw-bold mb-0"><i class="bi bi-person-circle me-2 text-primary"></i>Personal Information</h5>
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

                    <!-- SECURITY -->
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white py-3 border-0">
                                <h5 class="fw-bold mb-0"><i class="bi bi-shield-lock me-2 text-dark"></i>Security</h5>
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

                    <!-- PAYMENT QR CODE MANAGEMENT (ADMIN / OPERATOR ONLY) -->
                    <?php if (in_array($user['role'], ['admin', 'operator'])): ?>
                    <div class="col-12">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                                <h5 class="fw-bold mb-0"><i class="bi bi-qr-code-scan me-2 text-success"></i>Payment QR Code Settings</h5>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">Active Payment QR</span>
                            </div>
                            <div class="card-body p-4 pt-0">
                                <div class="row align-items-center g-4">
                                    <!-- QR Image Preview Container -->
                                    <div class="col-md-4 text-center border-end">
                                        <label class="form-label small fw-bold d-block text-muted mb-2">Current Displayed QR Code</label>
                                        <div class="p-3 bg-light rounded border d-inline-block shadow-sm">
                                            <img src="<?= $qr_image_url ?>" 
                                                 alt="Payment QR Code" 
                                                 class="img-fluid rounded" 
                                                 style="max-height: 220px; object-fit: contain;"
                                                 onerror="this.onerror=null; this.src='https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=SamplePaymentQR';">
                                        </div>
                                    </div>

                                    <!-- Upload Form -->
                                    <div class="col-md-8">
                                        <form action="process/profile_actions.php" method="POST" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold">Upload New Payment QR Code</label>
                                                <input type="file" name="qr_code_image" class="form-control" accept="image/png, image/jpeg, image/webp" required>
                                                <div class="form-text">
                                                    Upload your GCash, Maya, or Bank Transfer QR code image (PNG, JPG, WEBP). This image will immediately update in customer reservation modals.
                                                </div>
                                            </div>

                                            <div class="alert alert-info py-2 px-3 small mb-3 border-0">
                                                <i class="bi bi-info-circle me-1"></i>
                                                <strong>Tip:</strong> Ensure the QR code image is clear and easily scannable by banking apps.
                                            </div>

                                            <button type="submit" name="upload_qr_code" class="btn btn-success fw-bold px-4">
                                                <i class="bi bi-cloud-arrow-up me-1"></i> Update Payment QR Code
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>