<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth Check - Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    ?>
    <script>window.location.href = "../../index.php";</script>
    <?php
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Fetch user data including phone number
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, email, phone, role FROM users WHERE id = $user_id LIMIT 1"));

$pageTitle = 'Account Settings';

// QR Code File Path Check
$qr_file_path = __DIR__ . '/../../public/assets/images/qr_code_payment.png';
$qr_image_exists = file_exists($qr_file_path);
$qr_image_url = "../../public/assets/images/qr_code_payment.png" . ($qr_image_exists ? '?v=' . time() : '');
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    :root {
        --brand-yellow: #ffcc00;
        --brand-black: #0a0a0a;
        --brand-ink: #1e293b;
        --brand-card-bg-dark: #141414;
        --brand-row-bg-dark: #1a1a1a;
        --brand-border-dark: #27272a;
    }

    body { 
        background-color: #f8fafc; 
        overflow-x: hidden; 
        color: #0f172a;
        transition: background-color 0.25s ease, color 0.25s ease;
    }
    
    .main-wrapper { 
        margin-left: 260px;
        width: calc(100% - 260px);
        min-height: 100vh;
        transition: all 0.3s;
    }
    @media (max-width: 992px) {
        .main-wrapper { margin-left: 0; width: 100%; }
    }

    /* Form Controls */
    .form-control-custom {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        padding: 10px 14px;
        font-size: 14px;
        border-radius: 10px;
        color: var(--brand-ink);
        transition: all 0.2s ease;
    }
    .form-control-custom:focus {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.25) !important;
        outline: none !important;
    }

    .btn-primary {
        background-color: var(--brand-yellow) !important;
        border-color: var(--brand-yellow) !important;
        color: #000000 !important;
    }
    .btn-primary:hover, .btn-primary:focus {
        background-color: #e6c200 !important;
        border-color: #e6c200 !important;
        color: #000000 !important;
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.35) !important;
    }

    /* Custom Drag and Drop Dropzone */
    .qr-dropzone {
        border: 2px dashed #cbd5e1;
        background-color: #f8fafc;
        transition: all 0.25s ease;
    }
    .qr-dropzone:hover {
        border-color: var(--brand-yellow) !important;
        background-color: #f1f5f9;
    }

    /* ========================================================
       CONSOLIDATED DARK MODE OVERRIDES
    ======================================================== */
    body.dark-mode {
        background-color: var(--brand-black) !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .main-wrapper,
    body.dark-mode .main-content {
        background-color: var(--brand-black) !important;
    }

    /* Header & Navbar */
    body.dark-mode header,
    body.dark-mode .header,
    body.dark-mode .topbar,
    body.dark-mode .navbar,
    body.dark-mode [class*="header"],
    body.dark-mode [class*="topbar"],
    body.dark-mode .main-wrapper > header,
    body.dark-mode .main-wrapper > div:first-child:has(.dropdown-toggle) {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #ffffff !important;
    }

    /* Header Controls & Buttons */
    body.dark-mode header .btn,
    body.dark-mode .topbar .btn,
    body.dark-mode .theme-toggle-btn,
    body.dark-mode .dropdown-toggle,
    body.dark-mode .user-pill,
    body.dark-mode [data-bs-toggle="dropdown"] {
        background-color: #0d0d0d !important;
        border-color: var(--brand-border-dark) !important;
        color: #ffffff !important;
    }

    body.dark-mode header .bg-white,
    body.dark-mode .topbar .bg-white {
        background-color: var(--brand-card-bg-dark) !important;
    }

    /* Cards, Headers, Footers & Dropdowns */
    body.dark-mode .card,
    body.dark-mode .card-header,
    body.dark-mode .dropdown-menu,
    body.dark-mode footer,
    body.dark-mode .footer {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .bg-light,
    body.dark-mode .bg-light-subtle {
        background-color: #0d0d0d !important;
        color: #cbd5e1 !important;
    }

    body.dark-mode .form-control,
    body.dark-mode .form-control-custom {
        background-color: #0d0d0d !important;
        border-color: var(--brand-border-dark) !important;
        color: #ffffff !important;
    }

    body.dark-mode .form-control:focus,
    body.dark-mode .form-control-custom:focus {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.2) !important;
    }

    body.dark-mode .form-control[readonly] {
        background-color: #1a1a1a !important;
        color: #94a3b8 !important;
    }

    /* Dropzone Dark Mode Styling */
    body.dark-mode .qr-dropzone {
        border-color: var(--brand-border-dark) !important;
        background-color: #0d0d0d !important;
    }
    body.dark-mode .qr-dropzone:hover {
        border-color: var(--brand-yellow) !important;
        background-color: #1a1a1a !important;
    }

    /* Text Color Tweaks */
    body.dark-mode .text-dark,
    body.dark-mode .dropdown-item {
        color: #ffffff !important;
    }

    body.dark-mode .dropdown-item:hover {
        background-color: #27272a !important;
    }

    body.dark-mode .text-muted,
    body.dark-mode .text-secondary,
    body.dark-mode .form-text {
        color: #cbd5e1 !important;
    }

    body.dark-mode .alert-info {
        background-color: rgba(13, 202, 240, 0.1) !important;
        color: #6edff6 !important;
        border: 1px solid rgba(13, 202, 240, 0.2) !important;
    }
</style>

<div class="dashboard-container">
    <div class="sidebar-wrapper">
        <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
    </div>

    <div class="main-wrapper main-content">
        <?php require_once __DIR__ . '/../components/header.php'; ?>

        <div class="p-4">
            <div class="mb-4">
                <h3 class="fw-bold mb-0">
                    <i class="bi bi-gear-wide-connected me-2 text-warning"></i>My Profile & Settings
                </h3>
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
                    <div class="card shadow-sm border-0 h-100 rounded-4">
                        <div class="card-header bg-white py-3 border-0">
                            <h5 class="fw-bold mb-0"><i class="bi bi-person-circle me-2 text-primary"></i>Personal Information</h5>
                        </div>
                        <div class="card-body p-4 pt-0">
                            <form action="process/profile_actions.php" method="POST">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">
                                        <i class="bi bi-person-fill text-warning me-1"></i> Full Name
                                    </label>
                                    <input type="text" name="name" class="form-control form-control-custom" value="<?= htmlspecialchars($user['name']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">
                                        <i class="bi bi-envelope-fill text-warning me-1"></i> Email Address
                                    </label>
                                    <input type="email" name="email" class="form-control form-control-custom" value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">
                                        <i class="bi bi-telephone-fill text-warning me-1"></i> Phone Number
                                    </label>
                                    <input type="tel" name="phone" class="form-control form-control-custom" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="09123456789" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">
                                        <i class="bi bi-shield-lock-fill text-warning me-1"></i> Account Role
                                    </label>
                                    <input type="text" class="form-control form-control-custom" value="<?= ucfirst($user['role']) ?>" readonly>
                                    <div class="form-text">Role cannot be changed by the user.</div>
                                </div>
                                <button type="submit" name="update_info" class="btn btn-primary fw-bold px-4" style="border-radius: 10px;">
                                    <i class="bi bi-check-circle-fill me-1"></i> Update Info
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- SECURITY -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0 h-100 rounded-4">
                        <div class="card-header bg-white py-3 border-0">
                            <h5 class="fw-bold mb-0"><i class="bi bi-shield-lock me-2 text-warning"></i>Security</h5>
                        </div>
                        <div class="card-body p-4 pt-0">
                            <form action="process/profile_actions.php" method="POST">
                                <div class="mb-3">
                                    <label class="form-label small fw-bold">
                                        <i class="bi bi-lock-fill text-warning me-1"></i> Current Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password" name="current_password" id="current_password" class="form-control form-control-custom" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                            <i class="bi bi-eye-slash"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">
                                        <i class="bi bi-key-fill text-warning me-1"></i> New Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password" name="new_password" id="new_password" class="form-control form-control-custom" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                            <i class="bi bi-eye-slash"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">
                                        <i class="bi bi-key-fill text-warning me-1"></i> Confirm New Password
                                    </label>
                                    <div class="input-group">
                                        <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-custom" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                            <i class="bi bi-eye-slash"></i>
                                        </button>
                                    </div>
                                </div>

                                <button type="submit" name="update_password" class="btn btn-primary fw-bold px-4" style="border-radius: 10px;">
                                    <i class="bi bi-shield-check me-1"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- PAYMENT QR CODE MANAGEMENT (ADMIN / OPERATOR ONLY) -->
                <?php if (in_array($user['role'], ['admin', 'operator'])): ?>
                <div class="col-12">
                    <div class="card shadow-sm border-0 rounded-4">
                        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0"><i class="bi bi-qr-code-scan me-2 text-success"></i>Payment QR Code Settings</h5>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1">
                                <i class="bi bi-check2-circle me-1"></i>Active Payment QR
                            </span>
                        </div>
                        <div class="card-body p-4 pt-0">
                            <div class="row align-items-center g-4">
                                <!-- QR Image Preview Container -->
                                <div class="col-md-4 text-center border-end">
                                    <label class="form-label small fw-bold d-block text-muted mb-2">Current Displayed QR Code</label>
                                    <div class="p-3 bg-light rounded-4 border d-inline-block shadow-sm">
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
                                            <label class="form-label small fw-bold mb-2">
                                                <i class="bi bi-upload text-warning me-1"></i> Upload New Payment QR Code
                                            </label>
                                            
                                            <!-- Custom Dropzone Container -->
                                            <div class="qr-dropzone text-center p-4 rounded-4 position-relative mb-2" style="cursor: pointer;">
                                                <input type="file" name="qr_code_image" id="qr_file_input" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" accept="image/png, image/jpeg, image/webp" required style="cursor: pointer;" onchange="updateFileName(this)">
                                                <div class="py-2">
                                                    <i class="bi bi-cloud-arrow-up-fill text-warning fs-1 mb-2 d-block"></i>
                                                    <span class="fw-semibold d-block mb-1" id="file_label_text">Drag & drop your QR image here or <span class="text-warning">Browse</span></span>
                                                    <small class="text-muted">Supports PNG, JPG, or WEBP</small>
                                                </div>
                                            </div>

                                            <div class="form-text">
                                                Upload your GCash, Maya, or Bank Transfer QR code image. This image will immediately update in customer reservation modals.
                                            </div>
                                        </div>

                                        <div class="alert alert-info py-2 px-3 small mb-3 border-0">
                                            <i class="bi bi-info-circle me-1"></i>
                                            <strong>Tip:</strong> Ensure the QR code image is clear and easily scannable by banking apps.
                                        </div>

                                        <button type="submit" name="upload_qr_code" class="btn btn-success fw-bold px-4 py-2" style="border-radius: 10px;">
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

<script>
function updateFileName(input) {
    const label = document.getElementById('file_label_text');
    if (input.files && input.files[0]) {
        label.innerHTML = `<i class="bi bi-file-earmark-image text-warning me-1"></i> Selected: <strong class="text-warning">${input.files[0].name}</strong>`;
    }
}


document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function () {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        }
    });
});
</script>