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

$pageTitle = 'Staff Manager';

require_once __DIR__ . '/../../config/branch_helper.php';

// ---- Branch scope for staff_manager ----
// Admin can be viewing "all" branches or a specific one via the header switcher.
// When a specific branch is selected, restrict roster + activity to that branch.
$sm_branch    = ($_SESSION['view_branch'] === 'all') ? null : (int)$_SESSION['view_branch'];
$sm_scope_u   = $sm_branch ? " AND branch_id = $sm_branch"   : "";  // users (unaliased)
$sm_scope_b   = $sm_branch ? " AND branch_id = $sm_branch"   : "";  // bookings (unaliased)
$sm_scope_b_b = $sm_branch ? " AND b.branch_id = $sm_branch" : "";  // bookings aliased as b

// ---- Date helpers ----
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end   = date('Y-m-d', strtotime('sunday this week'));
$month_start = date('Y-m-01');
$month_end   = date('Y-m-t');

// ---- FETCH STAFF MEMBERS WITH STATS ----
$staff_members = [];

$staffBase = [];
$baseRes = mysqli_query($conn, "SELECT id, name, email, phone, created_at FROM users WHERE role = 'staff' $sm_scope_u ORDER BY name ASC");
if ($baseRes) {
    while ($row = mysqli_fetch_assoc($baseRes)) {
        $staffBase[$row['id']] = $row;
    }
}

$createdCounts = [];
$r1 = mysqli_query($conn, "
    SELECT created_by,
           COUNT(*) AS total_created,
           SUM(CASE WHEN DATE(start_date) BETWEEN '$month_start' AND '$month_end' THEN 1 ELSE 0 END) AS month_created
    FROM bookings
    WHERE created_by IS NOT NULL
      $sm_scope_b
    GROUP BY created_by
");
if ($r1) {
    while ($row = mysqli_fetch_assoc($r1)) {
        $createdCounts[$row['created_by']] = $row;
    }
}

$actionCounts = [];
$r2 = mysqli_query($conn, "
    SELECT last_action_by,
           COUNT(*) AS total_actions,
           SUM(CASE WHEN DATE(last_action_at) BETWEEN '$month_start' AND '$month_end' THEN 1 ELSE 0 END) AS month_actions
    FROM bookings
    WHERE last_action_by IS NOT NULL
      $sm_scope_b
    GROUP BY last_action_by
");
if ($r2) {
    while ($row = mysqli_fetch_assoc($r2)) {
        $actionCounts[$row['last_action_by']] = $row;
    }
}

$lastActivityTs = [];
$r3 = mysqli_query($conn, "
    SELECT staff_id, MAX(ts) AS last_ts FROM (
        SELECT created_by AS staff_id, MAX(created_at) AS ts FROM bookings WHERE created_by IS NOT NULL $sm_scope_b GROUP BY created_by
        UNION ALL
        SELECT last_action_by AS staff_id, MAX(last_action_at) AS ts FROM bookings WHERE last_action_by IS NOT NULL $sm_scope_b GROUP BY last_action_by
    ) t
    GROUP BY staff_id
");
if ($r3) {
    while ($row = mysqli_fetch_assoc($r3)) {
        $lastActivityTs[$row['staff_id']] = $row['last_ts'];
    }
}

$lastActionType = [];
$r4 = mysqli_query($conn, "
    SELECT b.last_action_by, b.last_action_type, b.last_action_at
    FROM bookings b
    INNER JOIN (
        SELECT last_action_by, MAX(last_action_at) AS max_at
        FROM bookings
        WHERE last_action_by IS NOT NULL
          $sm_scope_b
        GROUP BY last_action_by
    ) latest ON latest.last_action_by = b.last_action_by AND latest.max_at = b.last_action_at
    WHERE 1=1 $sm_scope_b_b
");
if ($r4) {
    while ($row = mysqli_fetch_assoc($r4)) {
        $lastActionType[$row['last_action_by']] = $row['last_action_type'];
    }
}

foreach ($staffBase as $id => $s) {
    $staff_members[] = [
        'id'                => $s['id'],
        'name'              => $s['name'],
        'email'             => $s['email'],
        'phone'             => $s['phone'] ?? '',
        'created_at'        => $s['created_at'],
        'total_created'     => $createdCounts[$id]['total_created']  ?? 0,
        'month_created'     => $createdCounts[$id]['month_created']  ?? 0,
        'total_actions'     => $actionCounts[$id]['total_actions']   ?? 0,
        'month_actions'     => $actionCounts[$id]['month_actions']   ?? 0,
        'last_activity_at'  => $lastActivityTs[$id] ?? null,
        'last_action_type'  => $lastActionType[$id] ?? null,
    ];
}

// ---- FETCH RECENT ACTIVITY FEED ----
$activity_feed = [];
$activityQuery = "
    SELECT 
        b.id AS booking_id,
        b.status AS booking_status,
        b.last_action_type,
        b.last_action_at,
        u.name AS actor_name,
        u.role AS actor_role,
        c.brand,
        c.model,
        c.plate_number,
        COALESCE(cu.name, b.guest_name) AS customer_name
    FROM bookings b
    JOIN users u ON b.last_action_by = u.id
    JOIN cars c ON b.car_id = c.id
    LEFT JOIN users cu ON b.user_id = cu.id
    WHERE u.role = 'staff'
      AND b.last_action_at IS NOT NULL
      $sm_scope_b_b
    ORDER BY b.last_action_at DESC
    LIMIT 30
";
$activityRes = mysqli_query($conn, $activityQuery);
if ($activityRes) {
    while ($row = mysqli_fetch_assoc($activityRes)) {
        $activity_feed[] = $row;
    }
}

// ---- Summary stats ----
$total_staff = count($staff_members);
$total_created_all = 0;
$total_actions_all = 0;
$active_this_week = 0;

foreach ($staff_members as $s) {
    $total_created_all += (int)$s['total_created'];
    $total_actions_all += (int)$s['total_actions'];
    if (!empty($s['last_activity_at']) && strtotime($s['last_activity_at']) >= strtotime($week_start)) {
        $active_this_week++;
    }
}

// Helpers
if (!function_exists('formatRelativeTime')) {
    function formatRelativeTime($timestamp) {
        if (!$timestamp) return 'Never';
        $ts = strtotime($timestamp);
        $diff = time() - $ts;
        if ($diff < 60)          return 'just now';
        if ($diff < 3600)        return floor($diff / 60) . 'm ago';
        if ($diff < 86400)       return floor($diff / 3600) . 'h ago';
        if ($diff < 604800)      return floor($diff / 86400) . 'd ago';
        return date('M j, Y', $ts);
    }
}
if (!function_exists('actionIcon')) {
    function actionIcon($type) {
        return [
            'Confirmed' => 'bi-check-circle-fill',
            'Completed' => 'bi-flag-fill',
            'Cancelled' => 'bi-x-circle-fill',
            'Edited'    => 'bi-pencil-fill',
        ][$type] ?? 'bi-clock-history';
    }
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
        --brand-dark: #0f172a;
        --brand-ink: #1e293b;
        --brand-muted: #64748b;
        --brand-border: #e2e8f0;
        --brand-bg: #f8fafc;
        --brand-card-bg-dark: #141414;
        --brand-row-bg-dark: #1a1a1a;
        --brand-border-dark: #27272a;
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

    /* ---- Stat Cards ---- */
    .stat-card {
        background: #ffffff;
        border-radius: var(--card-radius);
        padding: 1.25rem;
        transition: all 0.25s ease;
        border: 1px solid var(--brand-border);
        height: 100%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
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
    }
    .stat-value { font-size: 1.5rem; font-weight: 800; line-height: 1.2; color: var(--brand-dark); }
    .stat-label {
        font-size: 0.725rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--brand-muted);
        font-weight: 700;
        margin-top: 2px;
    }

    /* ---- Filter Bar ---- */
    .filter-bar {
        background: #ffffff;
        border: 1px solid var(--brand-border);
        border-radius: var(--card-radius);
        padding: 0.85rem 1rem;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }
    .search-field {
        position: relative;
        flex: 1 1 260px;
        min-width: 200px;
    }
    .search-field i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--brand-muted);
        font-size: 14px;
        pointer-events: none;
    }
    .search-field input {
        width: 100%;
        border: 1px solid var(--brand-border);
        border-radius: 10px;
        padding: 8px 14px 8px 38px;
        font-size: 0.85rem;
        height: 40px;
        color: var(--brand-ink);
        background-color: #ffffff;
        transition: all 0.2s ease-in-out;
    }
    .search-field input:focus {
        border-color: var(--brand-yellow);
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.2);
    }

    .filter-pills {
        display: flex;
        gap: 4px;
        padding: 4px;
        background: #f8fafc;
        border-radius: 10px;
        border: 1px solid var(--brand-border);
    }
    .filter-pills .pill {
        border: none;
        background: transparent;
        color: var(--brand-muted);
        font-weight: 700;
        font-size: 0.75rem;
        padding: 6px 14px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .filter-pills .pill:hover { color: var(--brand-ink); }
    .filter-pills .pill.active {
        background: var(--brand-yellow);
        color: #000000;
        box-shadow: 0 3px 8px rgba(255, 204, 0, 0.3);
    }

    .sort-select {
        height: 40px;
        padding: 6px 32px 6px 12px;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--brand-ink);
        background-color: #ffffff;
        border: 1px solid var(--brand-border);
        border-radius: 10px;
        outline: none;
        appearance: none;
        -webkit-appearance: none;
        background: #ffffff url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") no-repeat right 12px center/12px 12px !important;
    }

    .roster-empty {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--brand-muted);
        font-size: 0.9rem;
        display: none;
    }

    /* ---- Staff Card ---- */
    .staff-card {
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
    .staff-card:hover {
        border-color: var(--brand-yellow);
        box-shadow: 0 12px 20px -8px rgba(255, 204, 0, 0.25);
    }

    .staff-avatar {
        width: 52px; height: 52px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--brand-yellow), #ffe066);
        color: #000000;
        font-weight: 800;
        font-size: 1.15rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.3);
    }

    .staff-name {
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--brand-ink);
        margin: 0;
        line-height: 1.2;
    }
    .staff-email {
        font-size: 0.78rem;
        color: var(--brand-muted);
        margin: 0;
        word-break: break-all;
    }

    .activity-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 0.68rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .activity-pill.is-active  { background: #dcfce7; color: #15803d; }
    .activity-pill.is-idle    { background: #f1f5f9; color: #64748b; }
    .activity-pill.is-inactive { background: #fee2e2; color: #b91c1c; }
    body.dark-mode .activity-pill.is-active   { background: rgba(34, 197, 94, 0.15); color: #86efac; }
    body.dark-mode .activity-pill.is-idle     { background: #1f1f23; color: #94a3b8; }
    body.dark-mode .activity-pill.is-inactive { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }

    /* ---- Metrics ---- */
    .metric-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        padding-top: 0.9rem;
        border-top: 1px solid var(--brand-border);
    }
    .metric-item { display: flex; flex-direction: column; gap: 2px; }
    .metric-label {
        font-size: 0.62rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 700;
        color: var(--brand-muted);
    }
    .metric-value {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--brand-ink);
        line-height: 1.1;
    }
    .metric-sub { font-size: 0.68rem; color: var(--brand-muted); font-weight: 600; }
    .metric-sub.has-activity { color: #16a34a; font-weight: 700; }

    /* ---- Activity Feed ---- */
    .feed-list { display: flex; flex-direction: column; gap: 0; }
    .feed-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--brand-border);
        transition: background 0.15s ease;
    }
    .feed-item:last-child { border-bottom: none; }
    .feed-item:hover { background: var(--brand-yellow-soft); }

    .feed-icon {
        width: 34px; height: 34px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem;
        flex-shrink: 0;
    }
    .feed-icon.is-action-confirmed { background: #e0f2fe; color: #0369a1; }
    .feed-icon.is-action-completed { background: #dcfce7; color: #15803d; }
    .feed-icon.is-action-cancelled { background: #fee2e2; color: #b91c1c; }
    .feed-icon.is-action-edited    { background: #fef3c7; color: #92400e; }
    body.dark-mode .feed-icon.is-action-confirmed { background: rgba(56, 189, 248, 0.15); color: #7dd3fc; }
    body.dark-mode .feed-icon.is-action-completed { background: rgba(34, 197, 94, 0.15);  color: #86efac; }
    body.dark-mode .feed-icon.is-action-cancelled { background: rgba(239, 68, 68, 0.15);  color: #fca5a5; }
    body.dark-mode .feed-icon.is-action-edited    { background: rgba(234, 179, 8, 0.15);  color: #fde047; }

    .feed-text {
        flex: 1;
        min-width: 0;
        font-size: 0.85rem;
        line-height: 1.4;
        color: var(--brand-ink);
    }
    .feed-text strong { font-weight: 700; }
    .feed-time {
        font-size: 0.72rem;
        color: var(--brand-muted);
        white-space: nowrap;
        flex-shrink: 0;
    }

    /* ---- Empty state ---- */
    .empty-state {
        text-align: center;
        padding: 4rem 1rem;
        color: var(--brand-muted);
    }
    .empty-state i { font-size: 3rem; opacity: 0.3; display: block; margin-bottom: 1rem; }

    /* ---- Dark mode ---- */
    body.dark-mode {
        background-color: var(--brand-black) !important;
        color: #f1f5f9 !important;
    }
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
    body.dark-mode .card,
    body.dark-mode .stat-card,
    body.dark-mode .staff-card,
    body.dark-mode .filter-bar {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .staff-card:hover,
    body.dark-mode .stat-card:hover {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 10px 24px rgba(255, 204, 0, 0.12) !important;
    }
    body.dark-mode .stat-value,
    body.dark-mode .staff-name,
    body.dark-mode .metric-value,
    body.dark-mode .fw-bold,
    body.dark-mode h1, body.dark-mode h2, body.dark-mode h3,
    body.dark-mode h4, body.dark-mode h5, body.dark-mode h6,
    body.dark-mode .text-dark,
    body.dark-mode .feed-text {
        color: #ffffff !important;
    }
    body.dark-mode .stat-label,
    body.dark-mode .staff-email,
    body.dark-mode .metric-label,
    body.dark-mode .metric-sub,
    body.dark-mode .text-muted,
    body.dark-mode .feed-time {
        color: #cbd5e1 !important;
    }
    body.dark-mode .metric-grid { border-color: var(--brand-border-dark); }
    body.dark-mode .feed-item { border-color: var(--brand-border-dark); }
    body.dark-mode .feed-item:hover { background: rgba(255, 204, 0, 0.05); }

    body.dark-mode .search-field input,
    body.dark-mode .sort-select {
        background-color: #0d0d0d !important;
        color: #ffffff !important;
        border-color: var(--brand-border-dark) !important;
    }
    body.dark-mode .search-field input::placeholder { color: #64748b; }
    body.dark-mode .sort-select {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23cbd5e1' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    }
    body.dark-mode .filter-pills {
        background: #0d0d0d;
        border-color: var(--brand-border-dark);
    }
    body.dark-mode .filter-pills .pill { color: #94a3b8; }
    body.dark-mode .filter-pills .pill:hover { color: #ffffff; }
    body.dark-mode .filter-pills .pill.active {
        background: var(--brand-yellow);
        color: #000000;
    }

    /* =========================================================
       RESPONSIVE — polish only, no layout overrides
       ========================================================= */

    /* Tablets — 576 to 991px */
    @media (min-width: 576px) and (max-width: 991.98px) {
        .filter-bar { padding: 0.9rem 1rem; gap: 10px; }
        .search-field { flex: 1 1 100%; }
        .filter-pills { flex: 1 1 auto; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .sort-select { flex: 1 1 200px; }

        .staff-card { padding: 1.1rem; gap: 0.85rem; }
        .staff-avatar { width: 46px; height: 46px; font-size: 1rem; }
        .staff-name { font-size: 1rem; }

        .stat-card { padding: 1.1rem; }
        .stat-value { font-size: 1.35rem; }
    }

    /* Phones — below 576px */
    @media (max-width: 575.98px) {
        .filter-bar { padding: 0.75rem; gap: 8px; }
        .search-field { flex: 1 1 100%; min-width: 0; }
        .search-field input { height: 38px; font-size: 0.8rem; }

        .filter-pills {
            flex: 1 1 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }
        .filter-pills::-webkit-scrollbar { display: none; }
        .filter-pills .pill { padding: 6px 12px; font-size: 0.72rem; }

        .sort-select { flex: 1 1 100%; height: 38px; font-size: 0.8rem; }

        .stat-card { padding: 0.9rem; }
        .stat-icon { width: 38px; height: 38px; font-size: 1rem; }
        .stat-value { font-size: 1.15rem; }
        .stat-label { font-size: 0.65rem; }

        .staff-card { padding: 1rem; gap: 0.85rem; }
        .staff-avatar { width: 42px; height: 42px; font-size: 0.95rem; border-radius: 12px; }
        .staff-name { font-size: 0.95rem; }
        .staff-email { font-size: 0.72rem; }

        .metric-value { font-size: 1.05rem; }
        .metric-label { font-size: 0.58rem; }
        .metric-sub   { font-size: 0.62rem; }

        .activity-pill { font-size: 0.6rem; padding: 2px 7px; }

        .feed-item { padding: 10px 12px; gap: 10px; }
        .feed-icon { width: 30px; height: 30px; font-size: 0.75rem; }
        .feed-text { font-size: 0.78rem; }
        .feed-time { font-size: 0.68rem; }
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-auto p-0" style="width: 0; overflow: visible;">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-4">

                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4 header-title-wrapper">
                    <div>
                        <h3 class="fw-bold mb-0">Staff <span class="text-brand-yellow">Manager</span></h3>
                        <p class="text-muted small mb-0">Monitor performance, bookings, and activity across your team.</p>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-brand-yellow text-dark">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($total_staff) ?></div>
                                    <div class="stat-label">Total Staff</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-activity"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($active_this_week) ?></div>
                                    <div class="stat-label">Active This Week</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-journal-plus"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($total_created_all) ?></div>
                                    <div class="stat-label">Bookings Created</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($total_actions_all) ?></div>
                                    <div class="stat-label">Actions Performed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Left: Staff roster -->
                    <div class="col-12 col-xl-7">
                        <div class="d-flex justify-content-between align-items-center mb-3 px-1">
                            <h5 class="fw-bold mb-0">
                                <i class="bi bi-person-badge-fill me-2 text-brand-yellow"></i>Team Roster
                            </h5>
                            <span class="badge rounded-pill" style="background: rgba(255,204,0,0.15); color: #b38a00;">
                                <?= count($staff_members) ?> member<?= count($staff_members) === 1 ? '' : 's' ?>
                            </span>
                        </div>

                        <!-- Filter Bar -->
                        <div class="filter-bar mb-3">
                            <div class="search-field">
                                <i class="bi bi-search"></i>
                                <input type="text" id="staffSearchInput" placeholder="Search name, email, or phone...">
                            </div>

                            <div class="filter-pills" id="staffStatusFilter">
                                <button type="button" class="pill active" data-status="all">All</button>
                                <button type="button" class="pill" data-status="active">Active</button>
                                <button type="button" class="pill" data-status="idle">Idle</button>
                                <button type="button" class="pill" data-status="inactive">Inactive</button>
                            </div>

                            <select id="staffSortSelect" class="sort-select">
                                <option value="name">Name A→Z</option>
                                <option value="bookings">Most Bookings</option>
                                <option value="actions">Most Actions</option>
                                <option value="recent">Recently Active</option>
                            </select>
                        </div>

                        <?php if (empty($staff_members)): ?>
                            <div class="card border-0 shadow-sm empty-state" style="border-radius: var(--card-radius);">
                                <i class="bi bi-person-x"></i>
                                <h5 class="fw-bold">No staff accounts yet</h5>
                                <p class="mb-0 small">Create staff accounts from the Users page.</p>
                            </div>
                        <?php else: ?>
                            <div class="row g-3" id="staffRosterGrid">
                                <?php foreach ($staff_members as $s): 
                                    $__nameParts = preg_split('/\s+/', trim($s['name']));
                                    $__initials = strtoupper(mb_substr($__nameParts[0] ?? '', 0, 1) . (isset($__nameParts[1]) ? mb_substr($__nameParts[1], 0, 1) : ''));
                                    if ($__initials === '') $__initials = '?';

                                    $lastTs = $s['last_activity_at'] ? strtotime($s['last_activity_at']) : 0;
                                    $daysSinceActive = $lastTs ? (time() - $lastTs) / 86400 : 999;
                                    if (!$lastTs) {
                                        $statusClass = 'is-idle';
                                        $statusLabel = 'No activity';
                                        $dataStatus  = 'idle';
                                    } elseif ($daysSinceActive <= 7) {
                                        $statusClass = 'is-active';
                                        $statusLabel = 'Active';
                                        $dataStatus  = 'active';
                                    } elseif ($daysSinceActive <= 30) {
                                        $statusClass = 'is-idle';
                                        $statusLabel = 'Idle';
                                        $dataStatus  = 'idle';
                                    } else {
                                        $statusClass = 'is-inactive';
                                        $statusLabel = 'Inactive';
                                        $dataStatus  = 'inactive';
                                    }
                                ?>
                                    <div class="col-12 col-md-6 js-staff-card"
                                         data-name="<?= htmlspecialchars(strtolower($s['name'])) ?>"
                                         data-email="<?= htmlspecialchars(strtolower($s['email'])) ?>"
                                         data-phone="<?= htmlspecialchars(strtolower($s['phone'] ?? '')) ?>"
                                         data-status="<?= $dataStatus ?>"
                                         data-bookings="<?= (int)$s['total_created'] ?>"
                                         data-actions="<?= (int)$s['total_actions'] ?>"
                                         data-last-activity="<?= $lastTs ?: 0 ?>">
                                        <div class="staff-card">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="staff-avatar"><?= htmlspecialchars($__initials) ?></div>
                                                <div class="flex-grow-1 min-w-0">
                                                    <h6 class="staff-name text-truncate"><?= htmlspecialchars($s['name']) ?></h6>
                                                    <p class="staff-email text-truncate mb-1"><?= htmlspecialchars($s['email']) ?></p>
                                                    <span class="activity-pill <?= $statusClass ?>">
                                                        <i class="bi bi-circle-fill" style="font-size: 6px;"></i>
                                                        <?= $statusLabel ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="metric-grid">
                                                <div class="metric-item">
                                                    <span class="metric-label">Bookings Created</span>
                                                    <span class="metric-value"><?= number_format($s['total_created']) ?></span>
                                                    <span class="metric-sub <?= (int)$s['month_created'] > 0 ? 'has-activity' : '' ?>">
                                                        <?= number_format($s['month_created']) ?> this month
                                                    </span>
                                                </div>
                                                <div class="metric-item">
                                                    <span class="metric-label">Actions Taken</span>
                                                    <span class="metric-value"><?= number_format($s['total_actions']) ?></span>
                                                    <span class="metric-sub <?= (int)$s['month_actions'] > 0 ? 'has-activity' : '' ?>">
                                                        <?= number_format($s['month_actions']) ?> this month
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="d-flex align-items-center justify-content-between pt-2 border-top" style="font-size: 0.75rem;">
                                                <span class="text-muted">
                                                    <i class="bi bi-clock-history me-1"></i>Last activity
                                                </span>
                                                <span class="fw-bold">
                                                    <?= formatRelativeTime($s['last_activity_at']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="roster-empty" id="staffRosterEmpty">
                                <i class="bi bi-search d-block mb-2" style="font-size: 2rem; opacity: 0.3;"></i>
                                No staff members match your filters.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right: Activity feed -->
                    <div class="col-12 col-xl-5">
                        <div class="d-flex justify-content-between align-items-center mb-3 px-1">
                            <h5 class="fw-bold mb-0">
                                <i class="bi bi-activity me-2 text-brand-yellow"></i>Recent Activity
                            </h5>
                            <span class="badge rounded-pill" style="background: rgba(255,204,0,0.15); color: #b38a00;">
                                Last <?= count($activity_feed) ?>
                            </span>
                        </div>

                        <div class="card border-0 shadow-sm" style="border-radius: var(--card-radius); overflow: hidden;">
                            <?php if (empty($activity_feed)): ?>
                                <div class="empty-state" style="padding: 3rem 1rem;">
                                    <i class="bi bi-inbox"></i>
                                    <p class="mb-0 small">No staff activity yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="feed-list">
                                    <?php foreach ($activity_feed as $act):
                                        $actionType = $act['last_action_type'] ?? 'Edited';
                                        $actionClass = 'is-action-' . strtolower($actionType);
                                        $icon = actionIcon($actionType);
                                        $customerName = $act['customer_name'] ?: 'Unknown';
                                        $carName = $act['brand'] . ' ' . $act['model'];
                                    ?>
                                        <div class="feed-item">
                                            <div class="feed-icon <?= $actionClass ?>">
                                                <i class="bi <?= $icon ?>"></i>
                                            </div>
                                            <div class="feed-text">
                                                <strong><?= htmlspecialchars($act['actor_name']) ?></strong>
                                                <?= htmlspecialchars(strtolower($actionType)) ?>
                                                <strong>#BK-<?= $act['booking_id'] ?></strong>
                                                <span class="text-muted">— <?= htmlspecialchars($customerName) ?></span>
                                                <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                                                    <?= htmlspecialchars($carName) ?> • <?= htmlspecialchars($act['plate_number']) ?>
                                                </div>
                                            </div>
                                            <div class="feed-time">
                                                <?= formatRelativeTime($act['last_action_at']) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput  = document.getElementById('staffSearchInput');
    const statusFilter = document.getElementById('staffStatusFilter');
    const sortSelect   = document.getElementById('staffSortSelect');
    const grid         = document.getElementById('staffRosterGrid');
    const emptyMsg     = document.getElementById('staffRosterEmpty');

    if (!searchInput || !grid) return;

    let currentStatus = 'all';

    function applyFilters() {
        const query = searchInput.value.toLowerCase().trim();
        const cards = Array.from(grid.querySelectorAll('.js-staff-card'));

        let visibleCount = 0;

        cards.forEach(card => {
            const name   = card.dataset.name   || '';
            const email  = card.dataset.email  || '';
            const phone  = card.dataset.phone  || '';
            const status = card.dataset.status || '';

            const matchesQuery  = !query || name.includes(query) || email.includes(query) || phone.includes(query);
            const matchesStatus = currentStatus === 'all' || status === currentStatus;

            if (matchesQuery && matchesStatus) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        const sortBy = sortSelect.value;
        const sorted = cards.slice().sort((a, b) => {
            if (sortBy === 'name') {
                return (a.dataset.name || '').localeCompare(b.dataset.name || '');
            } else if (sortBy === 'bookings') {
                return parseInt(b.dataset.bookings || 0) - parseInt(a.dataset.bookings || 0);
            } else if (sortBy === 'actions') {
                return parseInt(b.dataset.actions || 0) - parseInt(a.dataset.actions || 0);
            } else if (sortBy === 'recent') {
                return parseInt(b.dataset.lastActivity || 0) - parseInt(a.dataset.lastActivity || 0);
            }
            return 0;
        });

        sorted.forEach(card => grid.appendChild(card));

        if (emptyMsg) {
            emptyMsg.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    searchInput.addEventListener('input', applyFilters);

    statusFilter.querySelectorAll('.pill').forEach(pill => {
        pill.addEventListener('click', function () {
            statusFilter.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            currentStatus = this.dataset.status;
            applyFilters();
        });
    });

    sortSelect.addEventListener('change', applyFilters);
});
</script>