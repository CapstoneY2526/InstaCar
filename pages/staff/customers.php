<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../../config/database.php';

// Staff-only guard
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    $_SESSION['error'] = "Access denied.";
    ?>
    <script>window.stop(); window.location.href = "../../index.php";</script>
    <?php
    exit();
}

$pageTitle = 'Customers';

// Fetch all customers with booking stats
$customers = [];
$query = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.phone,
        u.created_at,
        COUNT(b.id) AS total_bookings,
        SUM(CASE WHEN b.status = 'Completed' THEN 1 ELSE 0 END) AS completed_bookings,
        SUM(CASE WHEN b.status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_bookings,
        SUM(CASE WHEN b.status IN ('Confirmed', 'Active', 'Pending') THEN 1 ELSE 0 END) AS active_bookings,
        (SELECT br.name
           FROM bookings b2
           LEFT JOIN branches br ON b2.branch_id = br.id
           WHERE b2.user_id = u.id
           ORDER BY b2.id DESC
           LIMIT 1) AS last_branch_name
    FROM users u
    LEFT JOIN bookings b ON b.user_id = u.id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY u.created_at DESC
";

$res = mysqli_query($conn, $query);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $customers[] = $row;
    }
}

$total_customers = count($customers);
$active_customers = 0;
$returning_customers = 0;
foreach ($customers as $c) {
    if ($c['active_bookings'] > 0) $active_customers++;
    if ($c['total_bookings'] > 1)  $returning_customers++;
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    /* ========================================================
       CORE VARIABLES & GLOBAL RESET
       ======================================================== */
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

    html, body {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
    }

    body { 
        background-color: var(--brand-bg); 
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: var(--brand-ink);
        transition: background-color 0.25s ease, color 0.25s ease;
    }

    .main-content { 
        min-width: 0;
        min-height: 100vh; 
        width: 100%;
        overflow-x: hidden;
    }

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
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .stat-value { 
        font-size: 1.5rem;
        font-weight: 800;
        line-height: 1.2;
        color: var(--brand-dark);
    }

    .stat-label {
        font-size: 0.725rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--brand-muted);
        font-weight: 700;
        margin-top: 2px;
    }

    /* ---- Control Bar ---- */
    .custom-control-bar {
        border: 1px solid var(--brand-border) !important;
        border-radius: var(--card-radius) !important;
        background: #ffffff !important;
    }

    .search-input-wrapper { 
        position: relative; 
        width: 100%; 
        max-width: 320px; 
    }
    .search-input-wrapper i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--brand-muted);
        font-size: 14px;
        pointer-events: none;
    }
    .search-input-wrapper input {
        width: 100% !important;
        border: 1px solid var(--brand-border) !important;
        border-radius: 10px !important;
        padding: 8px 14px 8px 38px !important;
        font-size: 0.85rem !important;
        height: 40px !important;
        color: var(--brand-ink) !important;
        background-color: #ffffff;
        transition: all 0.2s ease-in-out;
    }
    .search-input-wrapper input:focus {
        border-color: var(--brand-yellow) !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.2) !important;
    }

    .entry-limiter-select {
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

    /* ---- Filter Pills ---- */
    .nav-status-pills {
        background: #ffffff;
        padding: 6px;
        border-radius: var(--card-radius);
        border: 1px solid var(--brand-border);
        gap: 4px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
    }
    .nav-status-pills .nav-link {
        color: var(--brand-muted);
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.5rem 1.25rem;
        border-radius: 10px;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .nav-status-pills .nav-link.active {
        color: #000000;
        background: var(--brand-yellow);
        font-weight: 700;
    }

    /* ---- Table ---- */
    .table-card {
        border-radius: var(--card-radius) !important;
        border: 1px solid var(--brand-border);
        overflow: hidden !important;
        background: #ffffff;
    }
    .table thead th:first-child { border-top-left-radius: var(--card-radius) !important; }
    .table thead th:last-child  { border-top-right-radius: var(--card-radius) !important; }

    .table-responsive {
        overflow-x: auto !important;
        overflow-y: hidden !important;
        -webkit-overflow-scrolling: touch;
    }

    .table {
        width: 100% !important;
        margin: 0 !important;
        vertical-align: middle;
        background-color: #ffffff;
    }
    .table thead th {
        font-weight: 700;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: 0.725rem;
        background-color: #f1f5f9;
        color: var(--brand-muted);
        padding: 1rem;
        border: none;
    }
    .table tbody td {
        vertical-align: middle;
        white-space: normal !important;
        word-break: break-word;
        background-color: #ffffff;
        padding: 0.9rem 1rem;
        border-bottom: 1px solid var(--brand-border);
        border-top: none;
    }
    .table tbody tr:last-child td { border-bottom: none; }
    .table tbody tr:hover td { background-color: var(--brand-yellow-soft); transition: background 0.15s; }

    /* ---- Customer Cell ---- */
    .customer-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .customer-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--brand-yellow-soft);
        color: #8a6a00;
        font-weight: 800;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        text-transform: uppercase;
    }
    body.dark-mode .customer-avatar {
        background: rgba(255, 204, 0, 0.15);
        color: var(--brand-yellow);
    }

    /* ---- Copy Buttons ---- */
    .copy-btn {
        background: transparent;
        border: 1px solid var(--brand-border);
        border-radius: 6px;
        padding: 2px 6px;
        font-size: 0.7rem;
        color: var(--brand-muted);
        cursor: pointer;
        transition: all 0.2s ease;
        margin-left: 6px;
    }
    .copy-btn:hover {
        border-color: var(--brand-yellow);
        color: #b38a00;
        background: var(--brand-yellow-soft);
    }
    .copy-btn.copied {
        background: #dcfce7;
        color: #15803d;
        border-color: #86efac;
    }

    /* ---- Stats Badges ---- */
    .count-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 9px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .count-badge.is-total      { background: #f1f5f9; color: #334155; }
    .count-badge.is-completed  { background: #dcfce7; color: #15803d; }
    .count-badge.is-active     { background: #e0f2fe; color: #0369a1; }
    .count-badge.is-cancelled  { background: #fee2e2; color: #b91c1c; }
    .count-badge.is-zero       { background: #f8fafc; color: #94a3b8; font-style: italic; font-weight: 600; }
    body.dark-mode .count-badge.is-total     { background: #1f1f23; color: #cbd5e1; }
    body.dark-mode .count-badge.is-completed { background: rgba(34, 197, 94, 0.15); color: #86efac; }
    body.dark-mode .count-badge.is-active    { background: rgba(56, 189, 248, 0.15); color: #7dd3fc; }
    body.dark-mode .count-badge.is-cancelled { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
    body.dark-mode .count-badge.is-zero      { background: #1f1f23; color: #94a3b8; }

    /* ---- Mobile Cards ---- */
    .mobile-cards-wrapper {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        width: 100%;
    }
    .mobile-customer-card {
        background: #ffffff;
        border-radius: var(--card-radius);
        border: 1px solid var(--brand-border);
        padding: 1.1rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }
    .mobile-customer-card .card-header-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--brand-border);
    }

    /* ---- Empty State ---- */
    .empty-state {
        text-align: center;
        padding: 4rem 1rem;
        color: var(--brand-muted);
    }
    .empty-state i {
        font-size: 3rem;
        opacity: 0.3;
        display: block;
        margin-bottom: 1rem;
    }

    /* ---- Responsive ---- */
    @media (min-width: 992px) {
        .mobile-cards-wrapper { display: none !important; }
        .desktop-table-card { display: block !important; }
    }
    @media (max-width: 991.98px) {
        .mobile-cards-wrapper { display: flex !important; }
        .desktop-table-card { display: none !important; }
        .search-input-wrapper { max-width: 240px; }
    }
    @media (max-width: 575.98px) {
        .main-content { 
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }
        .main-content > .p-4 { padding: 1rem 0.5rem !important; }
        .custom-control-bar .card-body {
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 12px !important;
            padding: 0.875rem !important;
        }
        .search-input-wrapper {
            flex: 1 1 auto !important;
            max-width: 100% !important;
            width: auto !important;
        }
    }

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
    body.dark-mode .table-card,
    body.dark-mode .mobile-customer-card {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .stat-card:hover {
        border-color: var(--brand-yellow) !important;
        box-shadow: 0 10px 24px rgba(255, 204, 0, 0.12) !important;
    }
    body.dark-mode .stat-value,
    body.dark-mode .fw-bold,
    body.dark-mode h1, body.dark-mode h2, body.dark-mode h3,
    body.dark-mode h4, body.dark-mode h5, body.dark-mode h6,
    body.dark-mode .text-dark {
        color: #ffffff !important;
    }
    body.dark-mode .stat-label,
    body.dark-mode .text-muted {
        color: #cbd5e1 !important;
    }
    body.dark-mode .nav-status-pills {
        background-color: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
    }
    body.dark-mode .nav-status-pills .nav-link { color: #cbd5e1; }
    body.dark-mode .nav-status-pills .nav-link.active {
        background-color: var(--brand-yellow) !important;
        color: #000000 !important;
    }
    body.dark-mode .custom-control-bar {
        background: var(--brand-card-bg-dark) !important;
        border-color: var(--brand-border-dark) !important;
    }
    body.dark-mode .entry-limiter-select {
        background-color: #0d0d0d !important;
        color: #f1f5f9 !important;
        border-color: var(--brand-border-dark) !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffcc00' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    }
    body.dark-mode .search-input-wrapper input {
        background-color: #0d0d0d !important;
        color: #ffffff !important;
        border-color: var(--brand-border-dark) !important;
    }
    body.dark-mode .search-input-wrapper input::placeholder { color: #64748b; }
    body.dark-mode .table { color: #f1f5f9 !important; background-color: var(--brand-card-bg-dark) !important; }
    body.dark-mode .table thead th {
        background-color: #1a1600 !important;
        color: var(--brand-yellow) !important;
        border-bottom: 2px solid var(--brand-border-dark) !important;
    }
    body.dark-mode .table tbody tr { background-color: var(--brand-row-bg-dark) !important; }
    body.dark-mode .table td {
        background-color: var(--brand-row-bg-dark) !important;
        border-bottom: 1px solid var(--brand-border-dark) !important;
        color: #e2e8f0 !important;
    }
    body.dark-mode .table tbody tr:hover td {
        background-color: #262626 !important;
        color: #ffffff !important;
    }
    body.dark-mode .copy-btn {
        border-color: var(--brand-border-dark);
        color: #94a3b8;
    }
    body.dark-mode .copy-btn:hover {
        border-color: var(--brand-yellow);
        color: var(--brand-yellow);
    }
    body.dark-mode .mobile-customer-card .card-header-row {
        border-color: var(--brand-border-dark);
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 col-md-auto p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-4">

                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4 header-title-wrapper">
                    <div>
                        <h3 class="fw-bold mb-0">Customer <span class="text-brand-yellow">Directory</span></h3>
                        <p class="text-muted small mb-0">Quick lookup for customer info and booking history.</p>
                    </div>
                </div>

                <!-- Stats Row -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-sm-6 col-xl-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-brand-yellow text-dark">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($total_customers) ?></div>
                                    <div class="stat-label">Total Customers</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-sm-6 col-xl-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-info bg-opacity-10 text-info">
                                    <i class="bi bi-calendar-check-fill"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($active_customers) ?></div>
                                    <div class="stat-label">With Active Booking</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-12 col-xl-4">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-arrow-repeat"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?= number_format($returning_customers) ?></div>
                                    <div class="stat-label">Returning Customers</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Control Bar -->
                <div class="card custom-control-bar shadow-sm mb-4">
                    <div class="card-body p-3 d-flex flex-row justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-2 small text-muted font-weight-bold">
                            <span>Show</span>
                            <select id="customerEntryLimitSelect" class="entry-limiter-select">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="999">All</option>
                            </select>
                            <span>entries</span>
                        </div>
                        <div class="search-input-wrapper">
                            <i class="bi bi-search"></i>
                            <input type="text" id="customerSearchInput" placeholder="Search name, email, phone...">
                        </div>
                    </div>
                </div>

                <!-- Customer List -->
                <?php if (empty($customers)): ?>
                    <div class="card border-0 shadow-sm empty-state" style="border-radius: var(--card-radius);">
                        <i class="bi bi-people"></i>
                        <h5 class="fw-bold">No customers yet</h5>
                        <p class="mb-0 small">Customer accounts will appear here once they register.</p>
                    </div>
                <?php else: ?>
                    <!-- Mobile cards -->
                    <div class="d-block d-lg-none mobile-cards-wrapper">
                        <?php foreach ($customers as $c): 
                            $__nameParts = preg_split('/\s+/', trim($c['name']));
                            $__initials = strtoupper(mb_substr($__nameParts[0] ?? '', 0, 1) . (isset($__nameParts[1]) ? mb_substr($__nameParts[1], 0, 1) : ''));
                            if ($__initials === '') $__initials = '?';
                        ?>
                            <div class="mobile-customer-card js-searchable-customer">
                                <div class="card-header-row">
                                    <span class="customer-avatar"><?= htmlspecialchars($__initials) ?></span>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-bold text-dark text-truncate"><?= htmlspecialchars($c['name']) ?></div>
                                        <div class="text-muted small text-truncate">
                                            Joined <?= date('M j, Y', strtotime($c['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex flex-column gap-2">
                                    <div class="d-flex align-items-center justify-content-between small">
                                        <span class="text-muted"><i class="bi bi-envelope me-1"></i>Email</span>
                                        <div class="text-end" style="max-width: 65%;">
                                            <span class="text-dark text-truncate d-block" style="font-size: 0.8rem;">
                                                <?= htmlspecialchars($c['email'] ?? '—') ?>
                                            </span>
                                        </div>
                                    </div>
                                     <div class="d-flex align-items-center justify-content-between small">
                                        <span class="text-muted"><i class="bi bi-telephone me-1"></i>Phone</span>
                                        <span class="text-dark" style="font-size: 0.8rem;"><?= htmlspecialchars($c['phone'] ?? '—') ?></span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between small">
                                        <span class="text-muted"><i class="bi bi-shop me-1"></i>Last Booked At</span>
                                        <span class="text-dark" style="font-size: 0.8rem;">
                                            <?= !empty($c['last_branch_name']) ? htmlspecialchars($c['last_branch_name']) : '—' ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap gap-1 mt-2 pt-2 border-top">
                                    <span class="count-badge is-total">
                                        <i class="bi bi-journal-text"></i><?= $c['total_bookings'] ?> total
                                    </span>
                                    <?php if ($c['active_bookings'] > 0): ?>
                                        <span class="count-badge is-active">
                                            <i class="bi bi-circle-fill" style="font-size: 6px;"></i><?= $c['active_bookings'] ?> active
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($c['completed_bookings'] > 0): ?>
                                        <span class="count-badge is-completed">
                                            <i class="bi bi-check-circle-fill"></i><?= $c['completed_bookings'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($c['cancelled_bookings'] > 0): ?>
                                        <span class="count-badge is-cancelled">
                                            <i class="bi bi-x-circle-fill"></i><?= $c['cancelled_bookings'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($c['total_bookings'] == 0): ?>
                                        <span class="count-badge is-zero">No bookings yet</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Desktop table -->
                    <div class="d-none d-lg-block desktop-table-card">
                        <div class="card border-0 shadow-sm table-card">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table id="customersTable" class="table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th class="px-3 py-3 text-start">Customer</th>
                                                <th class="py-3 text-start">Contact</th>
                                                <th class="py-3 text-start">Booking History</th>
                                                <th class="py-3 text-start">Last Booked At</th>
                                                <th class="pe-3 py-3 text-end">Joined</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($customers as $c): 
                                                $__nameParts = preg_split('/\s+/', trim($c['name']));
                                                $__initials = strtoupper(mb_substr($__nameParts[0] ?? '', 0, 1) . (isset($__nameParts[1]) ? mb_substr($__nameParts[1], 0, 1) : ''));
                                                if ($__initials === '') $__initials = '?';
                                            ?>
                                                <tr class="js-searchable-customer">
                                                    <td class="px-3 py-3">
                                                        <div class="customer-cell">
                                                            <span class="customer-avatar"><?= htmlspecialchars($__initials) ?></span>
                                                            <div class="min-w-0">
                                                                <div class="fw-bold text-dark"><?= htmlspecialchars($c['name']) ?></div>
                                                                <div class="text-muted small">Customer #<?= $c['id'] ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="py-3">
                                                        <div class="d-flex flex-column gap-1 small">
                                                            <div class="d-flex align-items-center">
                                                                <i class="bi bi-envelope me-2 text-muted"></i>
                                                                <span class="text-dark"><?= htmlspecialchars($c['email'] ?? '—') ?></span>
                                                                <?php if (!empty($c['email'])): ?>
                                                                    <button type="button" class="copy-btn" onclick="copyText('<?= htmlspecialchars($c['email'], ENT_QUOTES) ?>', this)" title="Copy email">
                                                                        <i class="bi bi-clipboard"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="d-flex align-items-center">
                                                                <i class="bi bi-telephone me-2 text-muted"></i>
                                                                <span class="text-dark"><?= htmlspecialchars($c['phone'] ?? '—') ?></span>
                                                                <?php if (!empty($c['phone'])): ?>
                                                                    <button type="button" class="copy-btn" onclick="copyText('<?= htmlspecialchars($c['phone'], ENT_QUOTES) ?>', this)" title="Copy phone">
                                                                        <i class="bi bi-clipboard"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="py-3">
                                                        <div class="d-flex flex-wrap gap-1">
                                                            <span class="count-badge is-total">
                                                                <i class="bi bi-journal-text"></i><?= $c['total_bookings'] ?> total
                                                            </span>
                                                            <?php if ($c['active_bookings'] > 0): ?>
                                                                <span class="count-badge is-active">
                                                                    <i class="bi bi-circle-fill" style="font-size: 6px;"></i><?= $c['active_bookings'] ?> active
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if ($c['completed_bookings'] > 0): ?>
                                                                <span class="count-badge is-completed">
                                                                    <i class="bi bi-check-circle-fill"></i><?= $c['completed_bookings'] ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if ($c['cancelled_bookings'] > 0): ?>
                                                                <span class="count-badge is-cancelled">
                                                                    <i class="bi bi-x-circle-fill"></i><?= $c['cancelled_bookings'] ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if ($c['total_bookings'] == 0): ?>
                                                                <span class="count-badge is-zero">No bookings yet</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td class="py-3">
                                                        <?php if (!empty($c['last_branch_name'])): ?>
                                                            <span class="count-badge is-total">
                                                                <i class="bi bi-shop"></i><?= htmlspecialchars($c['last_branch_name']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted small">&mdash;</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="pe-3 py-3 text-end">
                                                        <div class="text-muted small">
                                                            <?= date('M j, Y', strtotime($c['created_at'])) ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>

<script>
// ---- Copy to clipboard ----
function copyText(text, btnEl) {
    const finish = () => {
        const original = btnEl.innerHTML;
        btnEl.innerHTML = '<i class="bi bi-check-lg"></i>';
        btnEl.classList.add('copied');
        setTimeout(() => {
            btnEl.innerHTML = original;
            btnEl.classList.remove('copied');
        }, 1200);
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(finish).catch(() => fallbackCopy(text, finish));
    } else {
        fallbackCopy(text, finish);
    }
}

function fallbackCopy(text, cb) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); cb(); } catch(e) {}
    document.body.removeChild(ta);
}

// ---- Search + entry limit ----
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('customerSearchInput');
    const limitSelect = document.getElementById('customerEntryLimitSelect');
    if (!searchInput || !limitSelect) return;

    function applyFilter() {
        const q = searchInput.value.toLowerCase().trim();
        const limit = parseInt(limitSelect.value, 10) || 10;

        let desktopCount = 0;
        document.querySelectorAll('#customersTable tbody tr.js-searchable-customer').forEach(tr => {
            if (tr.textContent.toLowerCase().includes(q)) {
                desktopCount++;
                tr.style.display = desktopCount <= limit ? '' : 'none';
            } else {
                tr.style.display = 'none';
            }
        });

        let mobileCount = 0;
        document.querySelectorAll('.mobile-customer-card.js-searchable-customer').forEach(card => {
            if (card.textContent.toLowerCase().includes(q)) {
                mobileCount++;
                card.style.display = mobileCount <= limit ? 'flex' : 'none';
            } else {
                card.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('input', applyFilter);
    limitSelect.addEventListener('change', applyFilter);
    applyFilter();
});
</script>