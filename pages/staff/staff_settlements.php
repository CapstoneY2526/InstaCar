<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/branch_helper.php';

// Auth Check — allow admin and staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    $_SESSION['error'] = "Access denied.";
    echo '<script>window.location.href = "../../index.php";</script>';
    exit();
}

$pageTitle = 'Pending Settlements';

// ── Staff are locked to their own branch ──
if ($_SESSION['role'] === 'staff') {
    $staff_branch_id = (int)($_SESSION['branch_id'] ?? 0);
    $_SESSION['view_branch'] = $staff_branch_id > 0 ? (string)$staff_branch_id : 'all';
}

// ── Active branch label (for subtitle) ──
$view_branch = $_SESSION['view_branch'] ?? 'all';
$branch_label = 'All Branches';
if ($view_branch !== 'all') {
    $bid = (int)$view_branch;
    if ($bid > 0) {
        $bStmt = $conn->prepare("SELECT name FROM branches WHERE id = ? LIMIT 1");
        $bStmt->bind_param('i', $bid);
        $bStmt->execute();
        $bRow = $bStmt->get_result()->fetch_assoc();
        $bStmt->close();
        if ($bRow) $branch_label = $bRow['name'];
    }
}

// ── Type filter ──
$filter_type = $_GET['type'] ?? 'all';

// ── Fetch pending settlements (scoped by view_branch) ──
$query = "SELECT b.id, b.booking_type, b.branch_id, b.start_date, b.end_date, b.pickup_time, b.return_time, 
                 b.total_price, b.extension_price, b.extension_hours,
                 b.delivery_fee, b.pickup_fee, b.remarks,
                 c.brand, c.model, c.plate_number, 
                 c.price_10_hours, c.price_12_hours, c.price_24_hours,
                 c.ext_price_1_6, c.ext_price_7_10, c.ext_price_11_12, c.ext_price_13_24,
                 u.name as member_name, b.guest_name
          FROM bookings b
          JOIN cars c ON b.car_id = c.id
          LEFT JOIN users u ON b.user_id = u.id
          LEFT JOIN booking_payments p ON b.id = p.booking_id
          WHERE b.status = 'Completed' AND p.id IS NULL";

if ($filter_type !== 'all' && in_array($filter_type, ['online', 'manual'], true)) {
    $query .= " AND b.booking_type = '" . mysqli_real_escape_string($conn, $filter_type) . "'";
}

$query .= branchScopeSql('b.branch_id');
$query .= " ORDER BY b.end_date DESC";

$res = mysqli_query($conn, $query);

$pending = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) { 
        $pending[] = $row; 
    }
} else {
    die("Database Query Failed: " . mysqli_error($conn));
}

// ── Stats ──
$pending_count = count($pending);
$pending_value = 0;
$oldest_date   = null;
foreach ($pending as $p) {
    $pending_value += (float)$p['total_price'];
    $endTs = strtotime($p['end_date']);
    if ($oldest_date === null || $endTs < $oldest_date) {
        $oldest_date = $endTs;
    }
}

$oldest_label = '—';
if ($oldest_date !== null) {
    $days = (int)floor((time() - $oldest_date) / 86400);
    if ($days <= 0) $oldest_label = 'Today';
    elseif ($days === 1) $oldest_label = '1 day ago';
    else $oldest_label = $days . ' days ago';
}

// ── Helper: relative time for History tab ──
if (!function_exists('formatRelativeTime')) {
    function formatRelativeTime($timestamp) {
        if (!$timestamp) return '';
        $ts = strtotime($timestamp);
        $diff = time() - $ts;
        if ($diff < 60)     return 'just now';
        if ($diff < 3600)   return floor($diff / 60) . 'm ago';
        if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
        if ($diff < 604800) return floor($diff / 86400) . 'd ago';
        return date('M j, Y', $ts);
    }
}

// ── History scope (branch default, or "mine" filter) ──
$history_scope = $_GET['history_scope'] ?? 'branch';
if (!in_array($history_scope, ['branch', 'mine'], true)) {
    $history_scope = 'branch';
}

// ── History filters ──
$h_period  = $_GET['h_period']  ?? 'all';   // all | today | 7d | 30d
if (!in_array($h_period, ['all', 'today', '7d', '30d'], true)) {
    $h_period = 'all';
}

$h_settler = (int)($_GET['h_settler'] ?? 0);   // 0 = Anyone
$h_type    = $_GET['h_type']    ?? 'all';       // all | online | manual
if (!in_array($h_type, ['all', 'online', 'manual'], true)) {
    $h_type = 'all';
}

// ── Fetch recent settlements for the History tab ──
$history = [];
$history_sql = "
    SELECT
        p.id AS payment_id,
        p.booking_id,
        p.total_gross,
        p.total_net,
        p.settled_at,
        p.settled_by,
        b.booking_type,
        b.start_date,
        b.end_date,
        c.brand, c.model, c.plate_number,
        u.name  AS member_name,
        b.guest_name,
        su.name AS settled_by_name,
        su.role AS settled_by_role
    FROM booking_payments p
    INNER JOIN bookings b ON b.id = p.booking_id
    INNER JOIN cars     c ON c.id = b.car_id
    LEFT  JOIN users    u ON u.id = b.user_id
    LEFT  JOIN users   su ON su.id = p.settled_by
    WHERE 1=1
";

// Branch scope
$history_sql .= branchScopeSql('b.branch_id');

// Collect bind values dynamically
$history_params = [];
$history_types  = '';

// Scope: "My Settlements Only"
if ($history_scope === 'mine') {
    $history_sql .= " AND p.settled_by = ?";
    $history_params[] = $_SESSION['user_id'];
    $history_types   .= 'i';
}

// Filter: Settled By specific user
if ($h_settler > 0) {
    $history_sql .= " AND p.settled_by = ?";
    $history_params[] = $h_settler;
    $history_types   .= 'i';
}

// Filter: Type
if ($h_type !== 'all') {
    $history_sql .= " AND b.booking_type = ?";
    $history_params[] = $h_type;
    $history_types   .= 's';
}

// Filter: Period
if ($h_period === 'today') {
    $history_sql .= " AND DATE(p.settled_at) = CURDATE()";
} elseif ($h_period === '7d') {
    $history_sql .= " AND p.settled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($h_period === '30d') {
    $history_sql .= " AND p.settled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$history_sql .= " ORDER BY p.settled_at DESC, p.id DESC LIMIT 100";

$hStmt = $conn->prepare($history_sql);
if ($hStmt) {
    if (!empty($history_types)) {
        $hStmt->bind_param($history_types, ...$history_params);
    }
    $hStmt->execute();
    $hRes = $hStmt->get_result();
    while ($row = $hRes->fetch_assoc()) {
        $history[] = $row;
    }
    $hStmt->close();
}

// ── Fetch list of users who have settled at least one booking ──
// Scoped by branch so staff only see their branch's settlers
$settlers = [];
$settlers_sql = "
    SELECT DISTINCT u.id, u.name, u.role
    FROM booking_payments p
    INNER JOIN users u ON u.id = p.settled_by
    INNER JOIN bookings b ON b.id = p.booking_id
    WHERE p.settled_by IS NOT NULL
";
$settlers_sql .= branchScopeSql('b.branch_id');
$settlers_sql .= " ORDER BY u.name ASC";

$sRes = $conn->query($settlers_sql);
if ($sRes) {
    while ($sRow = $sRes->fetch_assoc()) {
        $settlers[] = $sRow;
    }
}
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

    .settlement-table-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        overflow: hidden;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .settlement-table-card:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 6px 18px -6px rgba(255, 215, 0, 0.4) !important;
    }
    .table thead th {
        background: #f1f5f9;
        font-size: 0.65rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        color: #334155;
        font-weight: 800;
        border-bottom: 1px solid #cbd5e1;
        padding: 0.85rem 0.75rem;
    }
    .table tbody td {
        padding: 0.9rem 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.85rem;
        color: #0f172a;
        vertical-align: middle;
    }
    .table tbody tr:hover td {
        background-color: #fffbe6;
    }
    .table tbody tr:last-child td { border-bottom: none; }

    .type-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }
    .type-online { background: #e0f2fe; color: #0369a1; }
    .type-manual { background: #f3e8ff; color: #6d28d9; }

    .btn-enter-fees {
        background-color: #ffd700;
        border: 1px solid #ffd700;
        color: #000000;
        font-weight: 700;
        padding: 0.45rem 0.9rem;
        border-radius: 0.6rem;
        font-size: 0.78rem;
        transition: all 0.2s ease;
    }
    .btn-enter-fees:hover {
        background-color: #e6c200;
        border-color: #e6c200;
        color: #000000;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 215, 0, 0.4);
    }

    .pending-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        overflow: hidden;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .pending-card:hover {
        transform: translateY(-2px);
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 6px 18px -6px rgba(255, 215, 0, 0.5) !important;
    }
    .pending-card .card-head {
        padding: 0.9rem 1.1rem;
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
    }
    .pending-card .card-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.7rem 1.1rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.85rem;
    }
    .pending-card .card-row:last-child { border-bottom: none; }
    .pending-card .card-actions {
        padding: 0.85rem 1.1rem;
        background: #fffbe6;
    }

    .pending-card .card-row-net {
        background: #fffbe6;
        border-top: 2px solid #ffd700;
    }
    .pending-card .card-net-value {
        color: #15803d;
        font-size: 1rem;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
    }
    .empty-state i { font-size: 64px; color: #94a3b8; margin-bottom: 20px; display: block; }
    .empty-state h5 { color: #334155; margin-bottom: 10px; font-weight: 800; }
    .empty-state p { color: #64748b; }

    .modal-content {
        border: 1px solid #e2e8f0 !important;
        border-radius: 1.25rem !important;
    }
    .modal-header.bg-dark {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
        border-bottom: 1px solid #27272a !important;
    }

    .base-rental-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.85rem;
        padding: 0.9rem 1.1rem;
    }
    .base-rental-card .label {
        font-size: 0.72rem;
        color: #475569;
        font-weight: 600;
    }
    .base-rental-card .value {
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
    }

    body.dark-mode,
    body.dark-mode .main-content {
        background-color: #0a0a0a !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .stat-card,
    body.dark-mode .filter-card,
    body.dark-mode .settlement-table-card,
    body.dark-mode .pending-card,
    body.dark-mode .empty-state {
        background-color: #141414 !important;
        border-color: #27272a !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.4) !important;
    }
    body.dark-mode .stat-card:hover,
    body.dark-mode .settlement-table-card:hover,
    body.dark-mode .pending-card:hover {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 1px #ffd700, 0 0 22px rgba(255, 215, 0, 0.4) !important;
    }
    body.dark-mode .stat-value,
    body.dark-mode .pending-card .card-head .fw-bold { color: #ffffff !important; }
    body.dark-mode .stat-label,
    body.dark-mode .stat-sub,
    body.dark-mode .text-muted { color: #cbd5e1 !important; }

    body.dark-mode .table {
        background-color: #141414 !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .table thead th {
        background-color: #1f1f23 !important;
        color: #cbd5e1 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .table tbody td {
        background-color: #141414 !important;
        color: #e2e8f0 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .table tbody tr:hover td {
        background-color: #1a1a1e !important;
    }

    body.dark-mode .pending-card .card-head { background: #141414 !important; border-color: #27272a !important; }
    body.dark-mode .pending-card .card-row { border-color: #27272a !important; }
    body.dark-mode .pending-card .card-actions { background: #1a1600 !important; }
    body.dark-mode .pending-card .card-row-net {background: #1a1600 !important; border-top-color: #ffd700 !important;}
    body.dark-mode .pending-card .card-net-value {color: #4ade80 !important; }

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

    body.dark-mode .type-online { background: rgba(56, 189, 248, 0.15) !important; color: #7dd3fc !important; }
    body.dark-mode .type-manual { background: rgba(139, 92, 246, 0.15) !important; color: #c4b5fd !important; }

    body.dark-mode .empty-state i { color: #71717a !important; }
    body.dark-mode .empty-state h5 { color: #e2e8f0 !important; }
    body.dark-mode .empty-state p { color: #cbd5e1 !important; }
    body.dark-mode h3 { color: #ffffff !important; }

    body.dark-mode .modal-content {
        background-color: #141414 !important;
        border-color: #27272a !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .modal-header.bg-dark {
        background: linear-gradient(135deg, #18181b 0%, #09090b 100%) !important;
    }
    body.dark-mode .modal-body .form-label { color: #cbd5e1 !important; }
    body.dark-mode .modal-body .form-control {
        background-color: #0d0d0d !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    body.dark-mode .modal-body .form-control:focus {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.22) !important;
    }
    body.dark-mode .base-rental-card {
        background: #1f1f23 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .base-rental-card .label { color: #cbd5e1 !important; }
    body.dark-mode .base-rental-card .value { color: #ffffff !important; }

    body.dark-mode .modal-footer {
        background-color: #141414 !important;
        border-top-color: #27272a !important;
    }
    body.dark-mode hr { border-color: #27272a !important; opacity: 1 !important; }

    @media (min-width: 1200px) {
        .mobile-cards-wrapper { display: none !important; }
    }
    @media (max-width: 1199.98px) {
        .desktop-table-wrapper { display: none !important; }
        .mobile-cards-wrapper { display: flex !important; }
    }

    @media (max-width: 576px) {
        .modal-body { padding: 1.25rem !important; }
    }

        /* ── Tabs ── */
    .settlement-tabs {
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 1.25rem;
    }
    .settlement-tabs .nav-link {
        border: none;
        color: #64748b;
        font-weight: 600;
        padding: 0.75rem 1.25rem;
        background: transparent;
        border-bottom: 3px solid transparent;
        transition: all 0.2s ease;
    }
    .settlement-tabs .nav-link:hover {
        color: #0f172a;
        border-bottom-color: #e2e8f0;
    }
    .settlement-tabs .nav-link.active {
        color: #0f172a;
        background: transparent;
        border-bottom-color: #ffd700;
        font-weight: 700;
    }
    .settlement-tabs .nav-link .tab-badge {
        display: inline-block;
        background: #e2e8f0;
        color: #334155;
        font-size: 0.65rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        margin-left: 6px;
        vertical-align: middle;
    }
    .settlement-tabs .nav-link.active .tab-badge {
        background: #ffd700;
        color: #000;
    }

    /* ── Audit badges ── */
    .audit-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        white-space: nowrap;
        line-height: 1.3;
    }
    .audit-badge i { font-size: 0.72rem; }
    .audit-badge.is-admin    { background: #ffd700; color: #000; }
    .audit-badge.is-staff    { background: #e0e7ff; color: #3730a3; }
    .audit-badge.is-operator { background: #fce7f3; color: #9d174d; }
    .audit-badge.is-unknown  { background: #f1f5f9; color: #64748b; font-style: italic; font-weight: 600; }
    .audit-badge .time-hint {
        font-weight: 600;
        opacity: 0.8;
        font-size: 0.62rem;
    }

    /* ── History scope filter ── */
    .history-scope-bar {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    .history-scope-bar .scope-btn {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #475569;
        font-size: 0.78rem;
        font-weight: 600;
        padding: 0.4rem 0.9rem;
        border-radius: 999px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .history-scope-bar .scope-btn:hover {
        border-color: #ffd700;
        color: #0f172a;
    }
    .history-scope-bar .scope-btn.active {
        background: #ffd700;
        border-color: #ffd700;
        color: #000;
        font-weight: 700;
    }

    /* ── Dark mode: tabs & badges ── */
    body.dark-mode .settlement-tabs { border-bottom-color: #27272a; }
    body.dark-mode .settlement-tabs .nav-link { color: #94a3b8; }
    body.dark-mode .settlement-tabs .nav-link:hover { color: #ffffff; border-bottom-color: #3f3f46; }
    body.dark-mode .settlement-tabs .nav-link.active { color: #ffffff; border-bottom-color: #ffd700; }
    body.dark-mode .settlement-tabs .nav-link .tab-badge { background: #27272a; color: #cbd5e1; }
    body.dark-mode .settlement-tabs .nav-link.active .tab-badge { background: #ffd700; color: #000; }

    body.dark-mode .audit-badge.is-staff    { background: rgba(99, 102, 241, 0.2); color: #a5b4fc; }
    body.dark-mode .audit-badge.is-operator { background: rgba(236, 72, 153, 0.2); color: #f9a8d4; }
    body.dark-mode .audit-badge.is-unknown  { background: #1f1f23; color: #94a3b8; }

    body.dark-mode .history-scope-bar .scope-btn {
        background: #141414;
        border-color: #27272a;
        color: #cbd5e1;
    }
    body.dark-mode .history-scope-bar .scope-btn:hover { border-color: #ffd700; color: #ffffff; }
    body.dark-mode .history-scope-bar .scope-btn.active { background: #ffd700; border-color: #ffd700; color: #000; }

        /* ── Resettle button ── */
    .btn-reset-settlement {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: transparent;
        border: 1px solid #cbd5e1;
        color: #475569;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.35rem 0.75rem;
        border-radius: 0.5rem;
        text-decoration: none;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .btn-reset-settlement:hover {
        background: #fff7d1;
        border-color: #ffd700;
        color: #8a6a00;
    }
    .btn-reset-settlement i {
        font-size: 0.8rem;
    }

    /* Dark mode */
    body.dark-mode .btn-reset-settlement {
        border-color: #3f3f46;
        color: #cbd5e1;
    }
    body.dark-mode .btn-reset-settlement:hover {
        background: rgba(255, 215, 0, 0.12);
        border-color: #ffd700;
        color: #ffd700;
    }

        /* ── History filter bar ── */
    .history-filter-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem 1.15rem;
        margin-bottom: 1.15rem;
    }

    .hf-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem 1.25rem;
        align-items: flex-end;
    }

    .hf-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
        flex: 1 1 auto;
    }

    .hf-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        margin: 0;
    }

    /* Period button group */
    .hf-period-group {
        display: inline-flex;
        border: 1px solid #cbd5e1;
        border-radius: 0.6rem;
        overflow: hidden;
        background: #ffffff;
    }
    .hf-period-btn {
        background: transparent;
        border: none;
        padding: 0.45rem 0.85rem;
        font-size: 0.78rem;
        font-weight: 600;
        color: #475569;
        cursor: pointer;
        transition: all 0.15s ease;
        text-decoration: none;
        white-space: nowrap;
        border-right: 1px solid #cbd5e1;
    }
    .hf-period-btn:last-child {
        border-right: none;
    }
    .hf-period-btn:hover {
        background: #fffbe6;
        color: #0f172a;
    }
    .hf-period-btn.active {
        background: #ffd700;
        color: #000;
        font-weight: 700;
    }

    /* Dropdowns */
    .hf-select {
        border-radius: 0.6rem;
        border: 1px solid #cbd5e1;
        font-size: 0.82rem;
        font-weight: 600;
        color: #0f172a;
        padding: 0.45rem 2.2rem 0.45rem 0.75rem;
        width: 100%;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-color: #ffffff;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23475569' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 12px 12px;
    }

    .hf-select:focus {
        border-color: #ffd700;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.22);
        outline: none;
    }

    /* Search input */
    .hf-search {
        position: relative;
        width: 100%;
    }
    .hf-search i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.9rem;
        pointer-events: none;
    }
    .hf-search input {
        width: 100%;
        border-radius: 0.6rem;
        border: 1px solid #cbd5e1;
        padding: 0.45rem 0.75rem 0.45rem 2.3rem;
        font-size: 0.82rem;
        font-weight: 500;
        color: #0f172a;
        background: #ffffff;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .hf-search input:focus {
        border-color: #ffd700;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.22);
        outline: none;
    }

    /* Clear button */
    .hf-clear {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: transparent;
        border: 1px solid #cbd5e1;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.45rem 0.85rem;
        border-radius: 0.6rem;
        text-decoration: none;
        transition: all 0.15s ease;
        white-space: nowrap;
    }
    .hf-clear:hover {
        background: #fff7d1;
        border-color: #ffd700;
        color: #8a6a00;
    }

    /* Mobile stack */
    @media (max-width: 767.98px) {
        .hf-row { gap: 0.75rem; }
        .hf-group { flex: 1 1 100%; }
        .hf-period-group { width: 100%; display: flex; }
        .hf-period-btn { flex: 1; text-align: center; padding: 0.5rem 0.25rem; font-size: 0.72rem; }
    }

    /* Dark mode */
    body.dark-mode .history-filter-card {
        background-color: #141414 !important;
        border-color: #27272a !important;
    }
    body.dark-mode .hf-label { color: #cbd5e1 !important; }
    body.dark-mode .hf-period-group {
        background-color: #0d0d0d !important;
        border-color: #27272a !important;
    }
    body.dark-mode .hf-period-btn {
        color: #cbd5e1;
        border-right-color: #27272a;
    }
    body.dark-mode .hf-period-btn:hover {
        background: rgba(255, 215, 0, 0.08);
        color: #ffffff;
    }
    body.dark-mode .hf-period-btn.active {
        background: #ffd700;
        color: #000;
    }
    body.dark-mode .hf-select {
        background-color: #0d0d0d !important;
        border-color: #27272a !important;
        color: #ffffff !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffd700' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.75rem center !important;
        background-size: 12px 12px !important;
    }
    body.dark-mode .hf-select:focus {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.22) !important;
    }
    body.dark-mode .hf-select option {
        background-color: #141414;
        color: #ffffff;
    }
    body.dark-mode .hf-search input {
        background-color: #0d0d0d !important;
        border-color: #27272a !important;
        color: #ffffff !important;
    }
    body.dark-mode .hf-search input::placeholder { color: #64748b !important; }
    body.dark-mode .hf-search input:focus {
        border-color: #ffd700 !important;
        box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.22) !important;
    }
    body.dark-mode .hf-search i { color: #64748b; }
    body.dark-mode .hf-clear {
        border-color: #27272a;
        color: #cbd5e1;
    }
    body.dark-mode .hf-clear:hover {
        background: rgba(255, 215, 0, 0.12);
        border-color: #ffd700;
        color: #ffd700;
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
                        <h3 class="fw-bold mb-0">Pending <span style="color: #b38a00;">Settlements</span></h3>
                        <p class="text-muted small mb-0">
                            <?= htmlspecialchars($branch_label) ?> &middot; Completed bookings awaiting fee entry.
                        </p>
                    </div>
                </div>

                <!-- Stats row -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Pending Count</div>
                            <div class="stat-value"><?= number_format($pending_count) ?></div>
                            <div class="stat-sub">Bookings awaiting settlement</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Total Pending Value</div>
                            <div class="stat-value">₱<?= number_format($pending_value, 2) ?></div>
                            <div class="stat-sub">Estimated revenue</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="stat-card">
                            <div class="stat-label">Oldest Pending</div>
                            <div class="stat-value"><?= htmlspecialchars($oldest_label) ?></div>
                            <div class="stat-sub">Age of oldest unsettled trip</div>
                        </div>
                    </div>
                </div>

                <!-- ── Tabs ── -->
                <?php
                    // Server-side tab detection:
                    // If the URL has history-related params, start on the History tab.
                    $on_history = isset($_GET['history_scope'])
                            || isset($_GET['h_period'])
                            || isset($_GET['h_settler'])
                            || isset($_GET['h_type']);

                    $pending_tab_class = $on_history ? 'nav-link'         : 'nav-link active';
                    $history_tab_class = $on_history ? 'nav-link active'  : 'nav-link';
                    $pending_pane_class = $on_history ? 'tab-pane fade'         : 'tab-pane fade show active';
                    $history_pane_class = $on_history ? 'tab-pane fade show active' : 'tab-pane fade';
                ?>

                <ul class="nav settlement-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="<?= $pending_tab_class ?>" id="pending-tab"
                                data-bs-toggle="tab" data-bs-target="#pending-pane"
                                type="button" role="tab">
                            <i class="bi bi-hourglass-split me-1"></i>Pending
                            <?php if ($pending_count > 0): ?>
                                <span class="tab-badge"><?= $pending_count ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="<?= $history_tab_class ?>" id="history-tab"
                                data-bs-toggle="tab" data-bs-target="#history-pane"
                                type="button" role="tab">
                            <i class="bi bi-clock-history me-1"></i>History
                            <?php if (count($history) > 0): ?>
                                <span class="tab-badge"><?= count($history) ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>

                <div class="tab-content">

                    <!-- ═══════════════ PENDING PANE ═══════════════ -->
                    <div class="<?= $pending_pane_class ?>" id="pending-pane" role="tabpanel">

                        <!-- Filters -->
                        <form method="GET" class="filter-card mb-4">
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-sm-6 col-md-3">
                                    <div class="filter-label">Type</div>
                                    <select name="type" class="form-select" onchange="this.form.submit()">
                                        <option value="all"    <?= $filter_type === 'all'    ? 'selected' : '' ?>>All Types</option>
                                        <option value="online" <?= $filter_type === 'online' ? 'selected' : '' ?>>Online</option>
                                        <option value="manual" <?= $filter_type === 'manual' ? 'selected' : '' ?>>Manual</option>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6 col-md-6">
                                    <div class="filter-label">Search</div>
                                    <div class="search-wrap">
                                        <i class="bi bi-search"></i>
                                        <input type="text" id="settlementSearch" class="form-control" placeholder="Search customer, vehicle, or plate...">
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <a href="staff_settlements.php" class="btn btn-outline-secondary w-100" style="border-radius:10px; height:40px; display:inline-flex; align-items:center; justify-content:center;">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                                    </a>
                                </div>
                            </div>
                        </form>

                        <?php if (empty($pending)): ?>
                            <div class="empty-state">
                                <i class="bi bi-check2-circle"></i>
                                <h5>All Settled!</h5>
                                <p class="mb-0 small">No pending bookings require financial entry.</p>
                            </div>
                        <?php else: ?>

                            <!-- Desktop table -->
                            <div class="desktop-table-wrapper">
                                <div class="settlement-table-card">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="ps-4">Trip Ended</th>
                                                    <th>Customer</th>
                                                    <th>Vehicle</th>
                                                    <th class="text-center">Type</th>
                                                    <th class="text-end pe-4">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($pending as $b): ?>
                                                <tr class="js-searchable-row"
                                                    data-search="<?= htmlspecialchars(strtolower(($b['guest_name'] ?: $b['member_name']) . ' ' . $b['brand'] . ' ' . $b['model'] . ' ' . $b['plate_number']), ENT_QUOTES) ?>">
                                                    <td class="ps-4">
                                                        <div class="fw-bold"><?= date('M d, Y', strtotime($b['end_date'])) ?></div>
                                                        <small class="text-muted">Finished Trip</small>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars($b['guest_name'] ?: $b['member_name']) ?></div>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold"><?= htmlspecialchars($b['brand']) ?> <?= htmlspecialchars($b['model']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($b['plate_number']) ?></small>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="type-badge <?= $b['booking_type'] === 'manual' ? 'type-manual' : 'type-online' ?>">
                                                            <?= htmlspecialchars(ucfirst($b['booking_type'])) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end pe-4">
                                                        <button class="btn-enter-fees" data-bs-toggle="modal" data-bs-target="#payModal<?= $b['id'] ?>">
                                                            <i class="bi bi-plus-circle me-1"></i>Enter Fees
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div id="noSearchResults" class="empty-state mt-3 d-none">
                                    <i class="bi bi-search"></i>
                                    <h5>No pending settlements match your search</h5>
                                    <p class="mb-0 small">Try a different customer, vehicle, or plate.</p>
                                </div>
                            </div>

                            <!-- Mobile cards -->
                            <div class="mobile-cards-wrapper flex-column gap-3">
                                <?php foreach($pending as $b): ?>
                                    <div class="pending-card js-searchable-card"
                                         data-search="<?= htmlspecialchars(strtolower(($b['guest_name'] ?: $b['member_name']) . ' ' . $b['brand'] . ' ' . $b['model'] . ' ' . $b['plate_number']), ENT_QUOTES) ?>">
                                        <div class="card-head">
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($b['brand']) ?> <?= htmlspecialchars($b['model']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($b['plate_number']) ?></small>
                                            </div>
                                            <span class="type-badge <?= $b['booking_type'] === 'manual' ? 'type-manual' : 'type-online' ?>">
                                                <?= htmlspecialchars(ucfirst($b['booking_type'])) ?>
                                            </span>
                                        </div>

                                        <div class="card-row">
                                            <span class="text-muted small">Customer</span>
                                            <span class="fw-semibold"><?= htmlspecialchars($b['guest_name'] ?: $b['member_name']) ?></span>
                                        </div>

                                        <div class="card-row">
                                            <span class="text-muted small">Trip Ended</span>
                                            <span class="fw-semibold"><?= date('M d, Y', strtotime($b['end_date'])) ?></span>
                                        </div>

                                        <div class="card-actions">
                                            <button class="btn-enter-fees w-100" data-bs-toggle="modal" data-bs-target="#payModal<?= $b['id'] ?>">
                                                <i class="bi bi-plus-circle me-1"></i>Enter Settlement Fees
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <div id="noSearchResultsMobile" class="empty-state d-none">
                                    <i class="bi bi-search"></i>
                                    <h5>No pending settlements match your search</h5>
                                    <p class="mb-0 small">Try a different customer, vehicle, or plate.</p>
                                </div>
                            </div>

                        <?php endif; ?>
                    </div> <!-- /#pending-pane -->

                    <!-- ═══════════════ HISTORY PANE ═══════════════ -->
                    <div class="<?= $history_pane_class ?>" id="history-pane" role="tabpanel">

                        <!-- History scope filter -->
                        <div class="history-scope-bar">
                            <span class="text-muted small me-1">Show:</span>
                            <a href="?history_scope=branch<?= $filter_type !== 'all' ? '&type=' . urlencode($filter_type) : '' ?>#history-pane"
                               class="scope-btn <?= $history_scope === 'branch' ? 'active' : '' ?>"
                               onclick="sessionStorage.setItem('activeSettlementTab','history');">
                                <i class="bi bi-shop me-1"></i><?= htmlspecialchars($branch_label) ?>
                            </a>
                            <a href="?history_scope=mine<?= $filter_type !== 'all' ? '&type=' . urlencode($filter_type) : '' ?>#history-pane"
                               class="scope-btn <?= $history_scope === 'mine' ? 'active' : '' ?>"
                               onclick="sessionStorage.setItem('activeSettlementTab','history');">
                                <i class="bi bi-person-check me-1"></i>My Settlements Only
                            </a>
                        </div>

                                                <!-- History filter bar -->
                        <form method="GET" id="historyFilterForm" class="history-filter-card">
                            <!-- preserve scope -->
                            <input type="hidden" name="history_scope" value="<?= htmlspecialchars($history_scope) ?>">
                            <?php if ($filter_type !== 'all'): ?>
                                <input type="hidden" name="type" value="<?= htmlspecialchars($filter_type) ?>">
                            <?php endif; ?>

                            <div class="hf-row">

                                <!-- Period -->
                                <div class="hf-group" style="flex: 0 0 auto; min-width: 220px;">
                                    <label class="hf-label">Period</label>
                                    <div class="hf-period-group">
                                        <?php
                                        $periods = [
                                            'all'   => 'All',
                                            'today' => 'Today',
                                            '7d'    => '7 days',
                                            '30d'   => '30 days',
                                        ];
                                        foreach ($periods as $key => $label):
                                            // Build link preserving other filters
                                            $q = [
                                                'history_scope' => $history_scope,
                                                'h_period'      => $key,
                                            ];
                                            if ($h_settler > 0) $q['h_settler'] = $h_settler;
                                            if ($h_type !== 'all') $q['h_type'] = $h_type;
                                            if ($filter_type !== 'all') $q['type'] = $filter_type;
                                        ?>
                                            <a href="?<?= http_build_query($q) ?>#history-pane"
                                               class="hf-period-btn <?= $h_period === $key ? 'active' : '' ?>"
                                               onclick="sessionStorage.setItem('activeSettlementTab','history');">
                                                <?= $label ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Settled By -->
                                <div class="hf-group" style="flex: 1 1 200px; max-width: 240px;">
                                    <label class="hf-label" for="hSettlerSelect">Settled By</label>
                                    <select name="h_settler" id="hSettlerSelect" class="hf-select">
                                        <option value="0" <?= $h_settler === 0 ? 'selected' : '' ?>>Anyone</option>
                                        <?php foreach ($settlers as $s): ?>
                                            <option value="<?= (int)$s['id'] ?>" <?= $h_settler === (int)$s['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($s['name']) ?>
                                                (<?= htmlspecialchars(ucfirst($s['role'])) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Type -->
                                <div class="hf-group" style="flex: 1 1 160px; max-width: 200px;">
                                    <label class="hf-label" for="hTypeSelect">Type</label>
                                    <select name="h_type" id="hTypeSelect" class="hf-select">
                                        <option value="all"    <?= $h_type === 'all'    ? 'selected' : '' ?>>All Types</option>
                                        <option value="online" <?= $h_type === 'online' ? 'selected' : '' ?>>Online</option>
                                        <option value="manual" <?= $h_type === 'manual' ? 'selected' : '' ?>>Manual</option>
                                    </select>
                                </div>

                                <!-- Search (client-side) -->
                                <div class="hf-group" style="flex: 2 1 260px;">
                                    <label class="hf-label" for="historySearch">Search</label>
                                    <div class="hf-search">
                                        <i class="bi bi-search"></i>
                                        <input type="text" id="historySearch" placeholder="Customer, vehicle, or plate...">
                                    </div>
                                </div>

                                <!-- Clear button -->
                                <div class="hf-group" style="flex: 0 0 auto;">
                                    <label class="hf-label" style="visibility: hidden;">&nbsp;</label>
                                    <?php
                                    $clear_q = ['history_scope' => $history_scope];
                                    if ($filter_type !== 'all') $clear_q['type'] = $filter_type;
                                    ?>
                                    <a href="?<?= http_build_query($clear_q) ?>#history-pane"
                                       class="hf-clear"
                                       onclick="sessionStorage.setItem('activeSettlementTab','history');">
                                        <i class="bi bi-x-circle"></i>
                                        <span>Clear</span>
                                    </a>
                                </div>

                            </div>
                        </form>

                        <?php if (empty($history)): ?>
                            <div class="empty-state">
                                <i class="bi bi-clock-history"></i>
                                <h5>No settlement history yet</h5>
                                <p class="mb-0 small">
                                    <?= $history_scope === 'mine' ? "You haven't settled any bookings yet." : 'No settled bookings found for this branch.' ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <!-- Desktop history table -->
                            <div class="desktop-table-wrapper">
                                <div class="settlement-table-card">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th class="ps-4">Settled</th>
                                                    <th>Vehicle</th>
                                                    <th>Customer</th>
                                                    <th>Settled By</th>
                                                    <th class="text-end pe-4">Gross</th>
                                                    <th class="text-end pe-4">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($history as $h):
                                                    $settlerName = $h['settled_by_name'] ?? 'Unknown User';
                                                    $settlerRole = strtolower($h['settled_by_role'] ?? '');
                                                    $roleClass   = in_array($settlerRole, ['admin','staff','operator'], true)
                                                                 ? 'is-' . $settlerRole
                                                                 : 'is-unknown';
                                                    $roleIcon    = [
                                                        'admin'    => 'bi-shield-lock-fill',
                                                        'staff'    => 'bi-person-vcard-fill',
                                                        'operator' => 'bi-briefcase-fill',
                                                    ][$settlerRole] ?? 'bi-question-circle';
                                                ?>
                                                    <tr>
                                                        <td class="ps-4">
                                                            <div class="fw-semibold small">
                                                                <?= $h['settled_at'] ? date('M d, Y', strtotime($h['settled_at'])) : '—' ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                <?= $h['settled_at'] ? date('g:i A', strtotime($h['settled_at'])) : '' ?>
                                                                <?= $h['settled_at'] ? '· ' . formatRelativeTime($h['settled_at']) : '' ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <div class="fw-bold"><?= htmlspecialchars($h['brand']) ?> <?= htmlspecialchars($h['model']) ?></div>
                                                            <small class="text-muted"><?= htmlspecialchars($h['plate_number']) ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="fw-semibold small">
                                                                <?= htmlspecialchars($h['guest_name'] ?: $h['member_name'] ?: 'N/A') ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                <?= htmlspecialchars(ucfirst($h['booking_type'])) ?>
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <span class="audit-badge <?= $roleClass ?>">
                                                                <i class="bi <?= $roleIcon ?>"></i>
                                                                <?= htmlspecialchars($settlerName) ?>
                                                                <?php if ($settlerRole): ?>
                                                                    <span class="time-hint">· <?= htmlspecialchars(ucfirst($settlerRole)) ?></span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end fw-semibold">₱<?= number_format((float)$h['total_gross'], 2) ?></td>
                                                        <td class="text-end pe-4">
                                                            <a href="../admin/process/reset_settlement.php?booking_id=<?= (int)$h['booking_id'] ?>"
                                                            class="btn-reset-settlement"
                                                            onclick="return confirm('Reset this settlement?\n\nThe existing record will be deleted and the booking will return to Pending so you can re-enter the fees.');">
                                                                <i class="bi bi-arrow-counterclockwise"></i>
                                                                <span>Resettle</span>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Mobile history cards -->
                            <div class="mobile-cards-wrapper flex-column gap-3">
                                <?php foreach ($history as $h):
                                    $settlerName = $h['settled_by_name'] ?? 'Unknown User';
                                    $settlerRole = strtolower($h['settled_by_role'] ?? '');
                                    $roleClass   = in_array($settlerRole, ['admin','staff','operator'], true)
                                                 ? 'is-' . $settlerRole
                                                 : 'is-unknown';
                                    $roleIcon    = [
                                        'admin'    => 'bi-shield-lock-fill',
                                        'staff'    => 'bi-person-vcard-fill',
                                        'operator' => 'bi-briefcase-fill',
                                    ][$settlerRole] ?? 'bi-question-circle';
                                ?>
                                    <div class="pending-card">
                                        <div class="card-head">
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($h['brand']) ?> <?= htmlspecialchars($h['model']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($h['plate_number']) ?></small>
                                            </div>
                                            <span class="audit-badge <?= $roleClass ?>">
                                                <i class="bi <?= $roleIcon ?>"></i>
                                                <?= htmlspecialchars($settlerName) ?>
                                            </span>
                                        </div>

                                        <div class="card-row">
                                            <span class="text-muted small">Customer</span>
                                            <span class="fw-semibold">
                                                <?= htmlspecialchars($h['guest_name'] ?: $h['member_name'] ?: 'N/A') ?>
                                            </span>
                                        </div>

                                        <div class="card-row">
                                            <span class="text-muted small">Settled</span>
                                            <span class="fw-semibold">
                                                <?php if ($h['settled_at']): ?>
                                                    <?= date('M d, Y g:i A', strtotime($h['settled_at'])) ?>
                                                    <small class="text-muted d-block" style="font-weight: 500;">
                                                        <?= formatRelativeTime($h['settled_at']) ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-style: italic;">—</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>

                                        <div class="card-row">
                                            <span class="text-muted small">Type</span>
                                            <span class="type-badge <?= $h['booking_type'] === 'manual' ? 'type-manual' : 'type-online' ?>">
                                                <?= htmlspecialchars(ucfirst($h['booking_type'])) ?>
                                            </span>
                                        </div>

                                        <div class="card-row card-row-net">
                                            <span class="fw-bold">Gross</span>
                                            <span class="fw-bold card-net-value">
                                                ₱<?= number_format((float)$h['total_gross'], 2) ?>
                                            </span>
                                        </div>

                                        <div class="card-actions">
                                            <a href="../admin/process/reset_settlement.php?booking_id=<?= (int)$h['booking_id'] ?>"
                                            class="btn-reset-settlement w-100"
                                            onclick="return confirm('Reset this settlement?\n\nThe existing record will be deleted and the booking will return to Pending so you can re-enter the fees.');">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i>Resettle
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div> <!-- /#history-pane -->

                </div> <!-- /tab-content -->

            </div> <!-- /p-3 p-md-4 -->

            <div class="mt-auto">
                <?php require_once __DIR__ . '/../components/footer.php'; ?>
            </div>
        </div>
    </div>
</div>

<?php foreach ($pending as $b): ?>
<div class="modal fade" id="payModal<?= $b['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="../admin/process/save_payment.php" method="POST" 
              class="modal-content border-0 shadow-lg rounded-4 payment-form-node" 
              id="paymentForm<?= $b['id'] ?>"
              data-end-date="<?= $b['end_date'] ?>"
              data-return-time="<?= $b['return_time'] ?? '00:00:00' ?>"
              data-ext-1-6="<?= $b['ext_price_1_6'] ?? 0 ?>"
              data-ext-7-10="<?= $b['ext_price_7_10'] ?? 0 ?>"
              data-ext-11-12="<?= $b['ext_price_11_12'] ?? 0 ?>"
              data-ext-13-24="<?= $b['ext_price_13_24'] ?? 0 ?>">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fs-6">
                    <i class="bi bi-receipt me-2"></i>Financial Settlement: <?= htmlspecialchars($b['brand']) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                
                <div class="base-rental-card mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="label"><i class="bi bi-info-circle me-1"></i>Base Rental Amount:</span>
                        <span class="value">₱<?= number_format($b['total_price'], 2) ?></span>
                    </div>
    
                    <div id="overtimeNotice<?= $b['id'] ?>" class="d-none mt-2 pt-2 border-top">
                        <div class="d-flex justify-content-between align-items-center small">
                            <span class="text-muted"><i class="bi bi-alarm text-danger me-1"></i>Scheduled Return:</span>
                            <span class="fw-semibold"><?= date('M d, Y', strtotime($b['end_date'])) ?> <?= date('h:i A', strtotime($b['return_time'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center small text-danger fw-bold mt-1">
                            <span><i class="bi bi-clock-history me-1"></i>Overtime Detected:</span>
                            <span id="lateHoursText<?= $b['id'] ?>">0 Hours Late</span>
                        </div>
                    </div>

                    <?php if($b['extension_hours'] > 0): ?>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                        <span class="small"><i class="bi bi-clock-history me-2"></i>Extension (<?= $b['extension_hours'] ?> hrs):</span>
                        <span class="fw-bold text-warning">₱<?= number_format($b['extension_price'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                        <span class="small fw-bold"><i class="bi bi-calculator me-2"></i>Total Amount:</span>
                        <span class="fw-bold text-success">₱<?= number_format($b['total_price'], 2) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="row g-3">
                    <div class="col-12"><h6 class="fw-bold text-uppercase extra-small text-muted mb-0">Revenue Details</h6><hr class="my-1"></div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-bold">Daily Rent</label>
                        <input type="number" name="daily_rent" class="form-control calc-field" value="<?= $b['total_price'] - ($b['extension_price'] ?? 0) ?>" step="0.01">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-bold">Extension Fees</label>
                        <input type="number" name="extension_fee" class="form-control calc-field" value="<?= $b['extension_price'] ?? 0 ?>" step="0.01">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold text-info">Agent Fees</label>
                        <input type="number" name="agent_fee" class="form-control calc-field" value="0">
                    </div>

                    <div class="col-12 mt-4"><h6 class="fw-bold text-uppercase extra-small text-muted mb-0">Logistics & Fees</h6><hr class="my-1"></div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-bold text-primary">Delivery Fees</label>
                        <input type="number" name="delivery_fee" class="form-control calc-field" value="<?= floatval($b['delivery_fee'] ?? 0) ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-bold text-primary">Staff Delivery Fees</label>
                        <input type="number" name="jer_delivery_fee" class="form-control calc-field" value="0">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-bold text-danger">Pickup Fees</label>
                        <input type="number" name="pickup_fee" class="form-control calc-field" value="<?= floatval($b['pickup_fee'] ?? 0) ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small fw-bold text-danger">Staff Pick up</label>
                        <input type="number" name="jer_pickup_fee" class="form-control calc-field" value="0">
                    </div>

                    <div class="col-12 mt-4"><h6 class="fw-bold text-uppercase extra-small text-muted mb-0">Expenses & Deductions</h6><hr class="my-1"></div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-bold">Carwash Fees</label>
                        <input type="number" name="carwash" class="form-control calc-field" value="0">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small fw-bold text-warning">Fuel Fees</label>
                        <input type="number" name="fuel" class="form-control calc-field" value="0">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-bold text-danger">Damage Fees</label>
                        <input type="number" name="damage_fee" class="form-control calc-field" value="0">
                    </div>
                    
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold">Driver Fees</label>
                        <input type="number" name="driver_fee" class="form-control calc-field" value="0">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label small fw-bold text-muted">Other Fees</label>
                        <input type="number" name="others" class="form-control calc-field" value="0">
                    </div>

                    <div class="col-12 mt-4">
                        <label class="form-label small fw-bold text-muted">Notes / Remarks</label>
                        <textarea name="remarks" id="remarksInput<?= $b['id'] ?>" class="form-control" rows="2" placeholder="Enter any additional notes or remarks for this settlement..."><?= htmlspecialchars($b['remarks'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="total-box mt-4" id="totalBox<?= $b['id'] ?>">
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-white">Gross Revenue:</span>
                                <span class="fw-bold" id="grossTotal<?= $b['id'] ?>">₱0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-white">Less: Staff Delivery Fee:</span>
                                <span class="fw-bold text-danger" id="jerDeliveryDisplay<?= $b['id'] ?>">₱0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-white">Less: Staff Pickup Fee:</span>
                                <span class="fw-bold text-danger" id="jerPickupDisplay<?= $b['id'] ?>">₱0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-white">Less: Agent Fee:</span>
                                <span class="fw-bold text-danger" id="agentFeeDisplay<?= $b['id'] ?>">₱0.00</span>
                            </div>
                            <hr class="my-2 opacity-25">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">NET TOTAL:</span>
                                <span class="fw-bold fs-5" id="netTotal<?= $b['id'] ?>">₱0.00</span>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="total_gross" id="totalGrossInput<?= $b['id'] ?>" value="0">
                    <input type="hidden" name="total_net" id="totalNetInput<?= $b['id'] ?>" value="0">
                </div>
            </div>
            <div class="modal-footer border-0 rounded-bottom-4">
                <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="save_payment" class="btn-enter-fees px-4">
                    <i class="bi bi-check-circle me-1"></i>Confirm & Save
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const bookingId = <?= $b['id'] ?>;
    const form = document.getElementById('paymentForm' + bookingId);
    if (!form) return;

    function autoCalculateOvertime() {
        const endDate    = form.getAttribute('data-end-date');
        const returnTime = form.getAttribute('data-return-time');

        if (!endDate || !returnTime) return;

        const scheduledReturn = new Date(`${endDate}T${returnTime}`);
        const actualReturn    = new Date();

        if (isNaN(scheduledReturn.getTime()) || actualReturn <= scheduledReturn) return;

        const diffMs    = actualReturn - scheduledReturn;
        const lateHours = Math.ceil(diffMs / (1000 * 60 * 60));

        if (lateHours <= 0) return;

        const ext1_6   = parseFloat(form.getAttribute('data-ext-1-6'))   || 0;
        const ext7_10  = parseFloat(form.getAttribute('data-ext-7-10'))  || 0;
        const ext11_12 = parseFloat(form.getAttribute('data-ext-11-12')) || 0;
        const ext13_24 = parseFloat(form.getAttribute('data-ext-13-24')) || 0;

        let autoExtensionFee = 0;
        if (lateHours <= 6)       autoExtensionFee = ext1_6;
        else if (lateHours <= 10) autoExtensionFee = ext7_10;
        else if (lateHours <= 12) autoExtensionFee = ext11_12;
        else                      autoExtensionFee = ext13_24;

        const extInput = form.querySelector('[name="extension_fee"]');
        if (extInput && (!extInput.dataset.userEdited || extInput.dataset.userEdited === "false")) {
            extInput.value = autoExtensionFee.toFixed(2);
        }

        const noticeBox = document.getElementById('overtimeNotice' + bookingId);
        const lateText  = document.getElementById('lateHoursText' + bookingId);
        if (noticeBox && lateText) {
            noticeBox.classList.remove('d-none');
            lateText.innerHTML = `${lateHours} Hour${lateHours > 1 ? 's' : ''} Late (+₱${autoExtensionFee.toLocaleString('en-PH', { minimumFractionDigits: 2 })})`;
        }
    }

    const extInput = form.querySelector('[name="extension_fee"]');
    if (extInput) {
        extInput.addEventListener('input', () => extInput.dataset.userEdited = "true");
    }

    function calculateTotals() {
        const dailyRent    = parseFloat(form.querySelector('[name="daily_rent"]')?.value) || 0;
        const extensionFee = parseFloat(form.querySelector('[name="extension_fee"]')?.value) || 0;
        const deliveryFee  = parseFloat(form.querySelector('[name="delivery_fee"]')?.value) || 0;
        const pickupFee    = parseFloat(form.querySelector('[name="pickup_fee"]')?.value) || 0;
        const damageFee    = parseFloat(form.querySelector('[name="damage_fee"]')?.value) || 0;
        const others       = parseFloat(form.querySelector('[name="others"]')?.value) || 0;
        const carwash      = parseFloat(form.querySelector('[name="carwash"]')?.value) || 0;
        const fuel         = parseFloat(form.querySelector('[name="fuel"]')?.value) || 0;
        const driverFee    = parseFloat(form.querySelector('[name="driver_fee"]')?.value) || 0;

        const agentFee     = parseFloat(form.querySelector('[name="agent_fee"]')?.value) || 0;
        const jerDelivery  = parseFloat(form.querySelector('[name="jer_delivery_fee"]')?.value) || 0;
        const jerPickup    = parseFloat(form.querySelector('[name="jer_pickup_fee"]')?.value) || 0;

        const grossTotal = dailyRent + extensionFee + deliveryFee + pickupFee
                         + damageFee + others + carwash + fuel + driverFee;

        const netTotal = grossTotal - jerDelivery - jerPickup - agentFee;

        document.getElementById('grossTotal' + bookingId).innerHTML = '₱' + grossTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('jerDeliveryDisplay' + bookingId).innerHTML = '₱' + jerDelivery.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('jerPickupDisplay' + bookingId).innerHTML = '₱' + jerPickup.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('agentFeeDisplay' + bookingId).innerHTML = '₱' + agentFee.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('netTotal' + bookingId).innerHTML = '₱' + netTotal.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        document.getElementById('totalGrossInput' + bookingId).value = grossTotal.toFixed(2);
        document.getElementById('totalNetInput' + bookingId).value = netTotal.toFixed(2);
    }

    autoCalculateOvertime();
    calculateTotals();

    form.querySelectorAll('input.calc-field').forEach(input => {
        input.addEventListener('input', calculateTotals);
        input.addEventListener('change', calculateTotals);
    });
})();
</script>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('settlementSearch');
    if (!input) return;

    const desktopRows = document.querySelectorAll('.js-searchable-row');
    const mobileCards = document.querySelectorAll('.js-searchable-card');
    const noDesktop   = document.getElementById('noSearchResults');
    const noMobile    = document.getElementById('noSearchResultsMobile');

    input.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();

        let visDesktop = 0;
        desktopRows.forEach(row => {
            const haystack = row.getAttribute('data-search') || '';
            const match = q === '' || haystack.includes(q);
            row.style.display = match ? '' : 'none';
            if (match) visDesktop++;
        });
        if (noDesktop) noDesktop.classList.toggle('d-none', visDesktop !== 0 || q === '');

        let visMobile = 0;
        mobileCards.forEach(card => {
            const haystack = card.getAttribute('data-search') || '';
            const match = q === '' || haystack.includes(q);
            card.style.display = match ? '' : 'none';
            if (match) visMobile++;
        });
        if (noMobile) noMobile.classList.toggle('d-none', visMobile !== 0 || q === '');
    });
});

// ── Tab persistence: remember which tab was open across reloads ──
document.addEventListener('DOMContentLoaded', function () {
    const historyTab = document.getElementById('history-tab');
    const pendingTab = document.getElementById('pending-tab');

    if (!historyTab || !pendingTab) return;

    // Restore active tab from sessionStorage
    const saved = sessionStorage.getItem('activeSettlementTab');

    // Check URL hash too — if someone clicked a scope button with #history-pane
    const hash = window.location.hash;

    if (hash === '#history-pane' || saved === 'history') {
        const bsTab = new bootstrap.Tab(historyTab);
        bsTab.show();
    }

    // Save when user switches
    historyTab.addEventListener('shown.bs.tab', function () {
        sessionStorage.setItem('activeSettlementTab', 'history');
    });
    pendingTab.addEventListener('shown.bs.tab', function () {
        sessionStorage.setItem('activeSettlementTab', 'pending');
    });
});

// ── History filter bar: auto-submit dropdowns + client-side search ──
document.addEventListener('DOMContentLoaded', function () {

    const filterForm   = document.getElementById('historyFilterForm');
    const settlerSel   = document.getElementById('hSettlerSelect');
    const typeSel      = document.getElementById('hTypeSelect');
    const searchInput  = document.getElementById('historySearch');

    // Auto-submit on dropdown change
    [settlerSel, typeSel].forEach(function (sel) {
        if (!sel) return;
        sel.addEventListener('change', function () {
            // Remember we're on the History tab
            sessionStorage.setItem('activeSettlementTab', 'history');
            if (filterForm) filterForm.submit();
        });
    });

    // Client-side search over the History rows (desktop + mobile)
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();

            // Desktop rows — build haystack from visible text
            const desktopRows = document.querySelectorAll('#history-pane .desktop-table-wrapper tbody tr');
            desktopRows.forEach(function (row) {
                const haystack = row.textContent.toLowerCase();
                row.style.display = (q === '' || haystack.includes(q)) ? '' : 'none';
            });

            // Mobile cards
            const mobileCards = document.querySelectorAll('#history-pane .mobile-cards-wrapper .pending-card');
            mobileCards.forEach(function (card) {
                const haystack = card.textContent.toLowerCase();
                card.style.display = (q === '' || haystack.includes(q)) ? '' : 'none';
            });
        });

        // Preserve search value if we navigated back with it
        const savedSearch = sessionStorage.getItem('historySearchValue');
        if (savedSearch) {
            searchInput.value = savedSearch;
            searchInput.dispatchEvent(new Event('input'));
        }

        // Save search value when typing
        searchInput.addEventListener('input', function () {
            sessionStorage.setItem('historySearchValue', this.value);
        });
    }

    // Clear saved search when the Clear button is clicked
    const clearBtn = document.querySelector('.hf-clear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            sessionStorage.removeItem('historySearchValue');
        });
    }
});
</script>