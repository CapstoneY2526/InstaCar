<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// --- AUTH CHECK ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'operator'])) {
    $_SESSION['error'] = "Access denied.";
    header("Location: ../../index.php");
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];
$pageTitle = 'My Car Schedules';

// Load user's cars (operator: own cars only; admin: all cars)
if ($user_role === 'admin') {
    $carsQuery = "SELECT id, brand, model, plate_number FROM cars ORDER BY brand, model";
    $carsResult = mysqli_query($conn, $carsQuery);
} else {
    $carsQuery = "SELECT id, brand, model, plate_number FROM cars WHERE user_id = ? ORDER BY brand, model";
    $stmt = mysqli_prepare($conn, $carsQuery);
    mysqli_stmt_bind_param($stmt, 'i', $current_user_id);
    mysqli_stmt_execute($stmt);
    $carsResult = mysqli_stmt_get_result($stmt);
}

$user_cars = [];
while ($row = mysqli_fetch_assoc($carsResult)) {
    $user_cars[] = $row;
}

// Helper: pick colors/icons per reason
function scheduleReasonClass($reason) {
    return match($reason) {
        'Maintenance'  => ['bg' => 'bg-warning-subtle',   'text' => 'text-warning',   'icon' => 'bi-tools'],
        'Personal Use' => ['bg' => 'bg-info-subtle',      'text' => 'text-info',      'icon' => 'bi-person-fill'],
        'Unavailable'  => ['bg' => 'bg-danger-subtle',    'text' => 'text-danger',    'icon' => 'bi-slash-circle'],
        default        => ['bg' => 'bg-secondary-subtle', 'text' => 'text-secondary', 'icon' => 'bi-question-circle'],
    };
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    /* ============================================================
       THEME VARIABLES
       ============================================================ */
    :root {
        --page-bg: #f8fafc;
        --card-bg: #ffffff;
        --card-border: #e2e8f0;
        --text-primary: #0f172a;
        --text-muted: #64748b;
        --brand-yellow: #ffcc00;
        --brand-yellow-dark: #b38a00;
        --row-bg: #f8fafc;
        --row-border: #e2e8f0;
        --row-bg-hover: #f1f5f9;
        --row-border-hover: #cbd5e1;
    }

    body.dark-mode {
        --page-bg: #0a0a0a;
        --card-bg: #141414;
        --card-border: #27272a;
        --text-primary: #ffffff;
        --text-muted: #a1a1aa;
        --row-bg: #1a1a1a;
        --row-border: #27272a;
        --row-bg-hover: #222222;
        --row-border-hover: #3f3f46;
    }

    html, body {
        background-color: var(--page-bg) !important;
        transition: background-color 0.25s ease;
    }

    .main-content {
        min-height: 100vh;
        background-color: var(--page-bg) !important;
    }

    /* ============================================================
       CARDS
       ============================================================ */
    .card {
        background-color: var(--card-bg) !important;
        border-color: var(--card-border) !important;
        color: var(--text-primary) !important;
        transition: background-color 0.25s ease, border-color 0.25s ease;
    }

    .text-dark { color: var(--text-primary) !important; }
    .text-muted { color: var(--text-muted) !important; }

    /* ============================================================
       BRAND YELLOW BUTTON
       ============================================================ */
    .btn-primary {
        background-color: var(--brand-yellow) !important;
        border-color: var(--brand-yellow) !important;
        color: #000000 !important;
        font-weight: 600 !important;
        box-shadow: 0 2px 4px rgba(255, 204, 0, 0.2) !important;
        transition: all 0.2s ease !important;
    }
    .btn-primary:hover,
    .btn-primary:focus {
        background-color: #e6b800 !important;
        border-color: #e6b800 !important;
        color: #000000 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.35) !important;
    }
    .btn-primary:disabled {
        background-color: #d4d4d8 !important;
        border-color: #d4d4d8 !important;
        color: #71717a !important;
        cursor: not-allowed;
        transform: none;
        box-shadow: none !important;
    }

    /* ============================================================
       YELLOW ACCENT TEXT (theme-aware)
       ============================================================ */
    .brand-accent { color: var(--brand-yellow-dark) !important; }
    body.dark-mode .brand-accent { color: var(--brand-yellow) !important; }

    /* ============================================================
       ALERTS
       ============================================================ */
    .alert-warning {
        background-color: #fff8e1 !important;
        border-color: #f0c14b !important;
        color: #78350f !important;
    }
    body.dark-mode .alert-warning {
        background-color: rgba(255, 204, 0, 0.1) !important;
        border-color: rgba(255, 204, 0, 0.35) !important;
        color: #fde68a !important;
    }

    /* ============================================================
       SCHEDULE BLOCK ROWS
       ============================================================ */
    .schedule-block-row {
        background-color: var(--row-bg);
        border: 1px solid var(--row-border);
        transition: all 0.2s ease;
    }
    .schedule-block-row:hover {
        background-color: var(--row-bg-hover);
        border-color: var(--row-border-hover);
    }

    .past-block-row {
        background-color: transparent;
    }

    .min-w-0 { min-width: 0; }

    /* ============================================================
       COLLAPSE TOGGLE
       ============================================================ */
    [data-bs-toggle="collapse"] .bi-chevron-down {
        transition: transform 0.2s ease;
    }
    [data-bs-toggle="collapse"][aria-expanded="true"] .bi-chevron-down {
        transform: rotate(180deg);
    }

    /* ============================================================
       MODAL THEME FIXES
       ============================================================ */
    .modal-content {
        background-color: var(--card-bg) !important;
        color: var(--text-primary);
        border-color: var(--card-border) !important;
    }
    .modal-header,
    .modal-footer {
        border-color: var(--card-border) !important;
    }
    body.dark-mode .modal-body .form-label {
        color: #a1a1aa !important;
    }
    body.dark-mode .form-control,
    body.dark-mode .form-select {
        background-color: #1a1a1a !important;
        border-color: #3f3f46 !important;
        color: #ffffff !important;
    }
    body.dark-mode .form-control::placeholder {
        color: #71717a !important;
    }
    body.dark-mode .form-control:focus,
    body.dark-mode .form-select:focus {
        background-color: #141414 !important;
        border-color: #ffcc00 !important;
        color: #ffffff !important;
        box-shadow: 0 0 0 2px rgba(255, 204, 0, 0.25) !important;
    }
    body.dark-mode .btn-light {
        background-color: #27272a !important;
        border-color: #3f3f46 !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .btn-light:hover {
        background-color: #3f3f46 !important;
        color: #ffffff !important;
    }
    body.dark-mode .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }
    body.dark-mode .alert-warning.rounded-3 {
        background-color: rgba(255, 204, 0, 0.1) !important;
        color: #fde68a !important;
        border-color: rgba(255, 204, 0, 0.35) !important;
    }

    /* Placeholder / empty states */
    .placeholder-icon { color: var(--text-muted); opacity: 0.25; }

    .past-block-row {
        background-color: transparent;
        transition: background-color 0.2s ease;
    }
    .past-block-row:hover {
        background-color: var(--row-bg-hover);
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
                <!-- Page Header -->
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">
                            My Car <span class="brand-accent">Schedules</span>
                        </h3>
                        <p class="text-muted small mb-0">
                            Manage when your cars are unavailable for booking.
                        </p>
                    </div>
                    <button class="btn btn-primary fw-semibold px-3 px-md-4 py-2 rounded-3 d-inline-flex align-items-center gap-2"
                            data-bs-toggle="modal"
                            data-bs-target="#addScheduleModal"
                            <?= empty($user_cars) ? 'disabled' : '' ?>>
                        <i class="bi bi-plus-lg me-1"></i> Add Schedule Block
                    </button>
                </div>

                <!-- No Cars Warning -->
                <?php if (empty($user_cars)): ?>
                    <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex align-items-center gap-3 p-4">
                        <i class="bi bi-exclamation-triangle-fill fs-3"></i>
                        <div>
                            <h6 class="fw-bold mb-1">No cars assigned to you yet</h6>
                            <p class="mb-0 small">
                                You need at least one car before you can create a schedule block.
                                <?= $user_role === 'operator' ? 'Contact the admin to assign cars to your fleet.' : 'Add a car from the Fleet Management page first.' ?>
                            </p>
                        </div>
                    </div>
                <?php else: ?>

                    <!-- Flash Messages -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <?php
                    // Load upcoming schedules
                    if ($user_role === 'admin') {
                        $upQuery = "SELECT cs.*, c.brand, c.model, c.plate_number, u.name AS created_by_name
                                    FROM car_schedules cs
                                    JOIN cars c ON cs.car_id = c.id
                                    LEFT JOIN users u ON cs.created_by = u.id
                                    WHERE cs.end_date >= CURDATE()
                                    ORDER BY cs.start_date ASC";
                        $upResult = mysqli_query($conn, $upQuery);
                    } else {
                        $upQuery = "SELECT cs.*, c.brand, c.model, c.plate_number, u.name AS created_by_name
                                    FROM car_schedules cs
                                    JOIN cars c ON cs.car_id = c.id
                                    LEFT JOIN users u ON cs.created_by = u.id
                                    WHERE cs.end_date >= CURDATE() AND c.user_id = ?
                                    ORDER BY cs.start_date ASC";
                        $stmt = mysqli_prepare($conn, $upQuery);
                        mysqli_stmt_bind_param($stmt, 'i', $current_user_id);
                        mysqli_stmt_execute($stmt);
                        $upResult = mysqli_stmt_get_result($stmt);
                    }

                    $upcoming = [];
                    while ($row = mysqli_fetch_assoc($upResult)) { $upcoming[] = $row; }

                    // Load past schedules
                    if ($user_role === 'admin') {
                        $pastQuery = "SELECT cs.*, c.brand, c.model, c.plate_number, u.name AS created_by_name
                                      FROM car_schedules cs
                                      JOIN cars c ON cs.car_id = c.id
                                      LEFT JOIN users u ON cs.created_by = u.id
                                      WHERE cs.end_date < CURDATE()
                                      ORDER BY cs.end_date DESC
                                      LIMIT 30";
                        $pastResult = mysqli_query($conn, $pastQuery);
                    } else {
                        $pastQuery = "SELECT cs.*, c.brand, c.model, c.plate_number, u.name AS created_by_name
                                      FROM car_schedules cs
                                      JOIN cars c ON cs.car_id = c.id
                                      LEFT JOIN users u ON cs.created_by = u.id
                                      WHERE cs.end_date < CURDATE() AND c.user_id = ?
                                      ORDER BY cs.end_date DESC
                                      LIMIT 30";
                        $stmt = mysqli_prepare($conn, $pastQuery);
                        mysqli_stmt_bind_param($stmt, 'i', $current_user_id);
                        mysqli_stmt_execute($stmt);
                        $pastResult = mysqli_stmt_get_result($stmt);
                    }

                    $past = [];
                    while ($row = mysqli_fetch_assoc($pastResult)) { $past[] = $row; }
                    ?>

                    <!-- UPCOMING SCHEDULES -->
                    <div class="card border-0 shadow-sm rounded-4 mb-4">
                        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-calendar2-check-fill me-2 brand-accent"></i>Upcoming Blocks
                            </h6>
                            <span class="badge rounded-pill" style="background: rgba(255,204,0,0.15); color: #b38a00;">
                                <?= count($upcoming) ?> block<?= count($upcoming) === 1 ? '' : 's' ?>
                            </span>
                        </div>
                        <div class="card-body p-3">
                            <?php if (empty($upcoming)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-calendar-x d-block mb-2 placeholder-icon" style="font-size: 2.5rem;"></i>
                                    <p class="small mb-0">No upcoming schedule blocks. Your cars are bookable.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($upcoming as $block): 
                                    $cls = scheduleReasonClass($block['reason']);
                                    $startTs = strtotime($block['start_date']);
                                    $endTs   = strtotime($block['end_date']);
                                    $days    = round(($endTs - $startTs) / 86400) + 1;
                                    $todayTs = strtotime(date('Y-m-d'));
                                    $isActive = ($startTs <= $todayTs && $endTs >= $todayTs);
                                ?>
                                    <div class="schedule-block-row d-flex flex-wrap align-items-center gap-3 p-3 mb-2 rounded-3">
                                        <div class="schedule-reason-icon <?= $cls['bg'] ?> <?= $cls['text'] ?> rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                             style="width: 48px; height: 48px;">
                                            <i class="bi <?= $cls['icon'] ?> fs-5"></i>
                                        </div>

                                        <div class="flex-grow-1 min-w-0">
                                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                                <span class="fw-bold text-dark">
                                                    <?= htmlspecialchars($block['brand'] . ' ' . $block['model']) ?>
                                                </span>
                                                <span class="text-muted small">• <?= htmlspecialchars($block['plate_number']) ?></span>
                                                <?php if ($isActive): ?>
                                                    <span class="badge bg-danger rounded-pill" style="font-size: 10px;">
                                                        <i class="bi bi-record-circle me-1"></i>Active Now
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted">
                                                <i class="bi bi-calendar-event me-1"></i>
                                                <?= date('M j, Y', $startTs) ?> &rarr; <?= date('M j, Y', $endTs) ?>
                                                <span class="text-muted">(<?= $days ?> day<?= $days === 1 ? '' : 's' ?>)</span>
                                            </div>
                                            <div class="small mt-1">
                                                <span class="fw-semibold <?= $cls['text'] ?>"><?= htmlspecialchars($block['reason']) ?></span>
                                                <?php if (!empty($block['notes'])): ?>
                                                    <span class="text-muted">— <?= htmlspecialchars($block['notes']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($user_role === 'admin' && !empty($block['created_by_name'])): ?>
                                                <div class="text-muted extra-small mt-1" style="font-size: 11px;">
                                                    <i class="bi bi-person-badge me-1"></i>Created by <?= htmlspecialchars($block['created_by_name']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger rounded-3"
                                                onclick="confirmDeleteSchedule(<?= $block['id'] ?>, '<?= htmlspecialchars($block['brand'] . ' ' . $block['model'], ENT_QUOTES) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- PAST SCHEDULES -->
                    <?php if (!empty($past)): ?>
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-transparent border-0 px-4 pt-4 pb-3">
                            <button class="btn btn-link text-decoration-none p-0 fw-bold text-dark d-flex align-items-center gap-2 w-100"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#pastSchedulesCollapse"
                                    aria-expanded="false"
                                    aria-controls="pastSchedulesCollapse">
                                <i class="bi bi-clock-history brand-accent fs-5"></i>
                                <span>Past Blocks</span>
                                <span class="badge rounded-pill ms-1"
                                    style="background: rgba(255,204,0,0.15); color: #b38a00;">
                                    <?= count($past) ?>
                                </span>
                                <i class="bi bi-chevron-down ms-auto"></i>
                            </button>
                        </div>
                        <div class="collapse" id="pastSchedulesCollapse">
                            <div class="card-body px-4 pb-4 pt-1">
                                <hr class="mt-0 mb-3" style="border-color: var(--card-border); opacity: 1;">
                                <?php foreach ($past as $block):
                                    $cls = scheduleReasonClass($block['reason']);
                                    $startTs = strtotime($block['start_date']);
                                    $endTs   = strtotime($block['end_date']);
                                    $days    = round(($endTs - $startTs) / 86400) + 1;
                                ?>
                                    <div class="d-flex flex-wrap align-items-center gap-3 py-2 px-3 mb-1 rounded-3 past-block-row">
                                        <div class="schedule-reason-icon <?= $cls['bg'] ?> <?= $cls['text'] ?> rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                                            style="width: 42px; height: 42px;">
                                            <i class="bi <?= $cls['icon'] ?> fs-6"></i>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <div class="fw-semibold text-dark small">
                                                <?= htmlspecialchars($block['brand'] . ' ' . $block['model']) ?>
                                                <span class="text-muted">• <?= htmlspecialchars($block['plate_number']) ?></span>
                                            </div>
                                            <div class="text-muted" style="font-size: 12px;">
                                                <?= date('M j', $startTs) ?> → <?= date('M j, Y', $endTs) ?>
                                                <span>• <?= $days ?>d • <?= htmlspecialchars($block['reason']) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>

<?php if (!empty($user_cars)): ?>
<!-- ADD SCHEDULE MODAL -->
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <!-- [CHANGED] Point to combined handler + action hidden field -->
        <form action="process/schedule_actions.php" method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <input type="hidden" name="action" value="save_schedule">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-calendar-plus me-2 brand-accent"></i>Add Schedule Block
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Car</label>
                    <select name="car_id" class="form-select" required>
                        <option value="">— Select a car —</option>
                        <?php foreach ($user_cars as $car): ?>
                            <option value="<?= $car['id'] ?>">
                                <?= htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' (' . $car['plate_number'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted">Start Date</label>
                        <input type="date" name="start_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold text-muted">End Date</label>
                        <input type="date" name="end_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">Reason</label>
                    <select name="reason" class="form-select" required>
                        <option value="Maintenance">🔧 Maintenance</option>
                        <option value="Personal Use">👤 Personal Use</option>
                        <option value="Unavailable">🚫 Unavailable</option>
                        <option value="Other">❓ Other</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted">Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="e.g. Oil change, tune up..."></textarea>
                </div>

                <div class="alert alert-warning rounded-3 border-0 small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    While this block is active, the car cannot be booked by customers, and the fleet status will show as <strong>Unavailable</strong>.
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-3 px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold rounded-3 px-4">Save Block</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal fade" id="deleteScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <!-- [CHANGED] Point to combined handler + action hidden field -->
        <form action="process/schedule_actions.php" method="POST" class="modal-content border-0 shadow-lg rounded-4">
            <input type="hidden" name="action" value="delete_schedule">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Delete Block?
                </h6>
            </div>
            <div class="modal-body p-4 pt-2">
                <p class="small text-muted mb-0">
                    Remove the schedule block for <strong id="deleteScheduleCarName"></strong>? The car will become bookable again on those dates.
                </p>
                <input type="hidden" name="schedule_id" id="deleteScheduleId">
            </div>
            <div class="modal-footer border-0 p-4 pt-0 gap-2">
                <button type="button" class="btn btn-light rounded-3 flex-fill" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger fw-bold rounded-3 flex-fill">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmDeleteSchedule(id, carName) {
    document.getElementById('deleteScheduleId').value = id;
    document.getElementById('deleteScheduleCarName').textContent = carName;
    new bootstrap.Modal(document.getElementById('deleteScheduleModal')).show();
}
</script>
<?php endif; ?>