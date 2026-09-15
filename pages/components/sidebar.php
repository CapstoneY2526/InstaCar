<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';

if (!function_exists('isActive')) {
    function isActive($file) {
        return basename($_SERVER['PHP_SELF']) === $file ? 'active' : '';
    }
}

// Helper: check if we're on users.php with a specific role param
if (!function_exists('isUsersActive')) {
    function isUsersActive($roleParam) {
        return basename($_SERVER['PHP_SELF']) === 'users.php'
            && ($_GET['role'] ?? '') === $roleParam
            ? 'active' : '';
    }
}
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    /* =========================================================
       MOBILE TOGGLE + OVERLAY
       ========================================================= */
    .sidebar-toggle {
        position: fixed;
        top: 10px;
        left: 10px;
        z-index: 1100;
        background: #FFD700;
        border: none;
        border-radius: 8px;
        padding: 10px 12px;
        cursor: pointer;
        display: none;
        color: #000;
        font-size: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 999;
        display: none;
    }

    /* =========================================================
       SIDEBAR SHELL
       ========================================================= */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 240px;
        background: #000000;
        padding: 20px 14px;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        z-index: 1000;
        border-right: 1px solid #333;
        transition: transform 0.3s ease;
        transform: translateX(0);
    }

    .sidebar-close {
        display: none;
        position: absolute;
        top: 15px;
        right: 15px;
        background: none;
        border: none;
        color: #FFF;
        font-size: 24px;
        cursor: pointer;
    }

    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-track { background: transparent; }
    .sidebar::-webkit-scrollbar-thumb { background: #FFD700; border-radius: 10px; }

    .sidebar-brand {
        font-weight: 800;
        font-size: 24px;
        color: #FFFFFF;
        letter-spacing: -0.5px;
    }

    .sidebar-brand::after { content: '.'; color: #FFD700; }

    .sidebar small { color: #888888; }

    /* =========================================================
       NAV LINKS (parent + child)
       ========================================================= */
    .sidebar .nav-link {
        color: #EEEEEE;
        padding: 10px 12px;
        border-radius: 8px;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all .3s ease;
        border: none;
        background: transparent;
        width: 100%;
        text-align: left;
        text-decoration: none;
        margin-bottom: 4px;
    }

    .sidebar .nav-link:hover {
        background: #1A1A1A;
        color: #FFD700;
    }

    /* Top-level link that is the current page */
    .sidebar .nav-link.active {
        position: relative;
        background: #FFD700;
        color: #000000;
        font-weight: 700;
        box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
    }

    .sidebar .nav-link.active::before {
        content: "";
        position: absolute;
        left: -14px;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 70%;
        background: #FFD700;
        border-radius: 10px;
    }

    /* =========================================================
       PARENT GROUP ACTIVE STATE
       Applied by JS when a child link is .active.
       Uses subtle dark fill + yellow text + yellow left bar
       so it doesn't compete with the child's bright yellow.
       ========================================================= */
    .sidebar .nav-link.active-parent {
        position: relative;
        background: #1A1A1A;
        color: #FFD700;
        font-weight: 700;
    }

    .sidebar .nav-link.active-parent::before {
        content: "";
        position: absolute;
        left: -14px;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 70%;
        background: #FFD700;
        border-radius: 10px;
    }

    .sidebar .nav-link.active-parent .ms-auto {
        color: #FFD700;
    }

    /* =========================================================
       SUBMENU
       ========================================================= */
    .submenu {
        list-style: none;
        padding-left: 20px;
        margin-top: 5px;
        padding-bottom: 10px;
    }

    .submenu .nav-link {
        font-size: 13px;
        padding: 8px 12px;
        color: #BBBBBB !important;
    }

    .submenu .nav-link:hover {
        color: #FFD700 !important;
        background: transparent !important;
    }

    .submenu .nav-link.active {
        color: #FFD700 !important;
        background: transparent !important;
        font-weight: bold;
        box-shadow: none;
    }

    /* =========================================================
       SECTION HEADERS + MISC
       ========================================================= */
    .sidebar-section {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .2em;
        color: #666666;
        margin: 25px 10px 10px;
        font-weight: 800;
    }

    .sidebar-divider { border-top: 1px solid #222; margin: 16px 0; }

    .ms-auto { transition: transform 0.2s; font-size: 10px; }
    .nav-link[aria-expanded="true"] .ms-auto { transform: rotate(90deg); color: #000000; }
    .nav-link.active-parent[aria-expanded="true"] .ms-auto { color: #FFD700; }

    /* =========================================================
       MAIN CONTENT OFFSET — single source of truth
       ========================================================= */
    .main-content {
        margin-left: 260px;
        width: calc(100% - 260px);
        transition: margin-left 0.3s ease;
    }

    /* =========================================================
       RESPONSIVE
       ========================================================= */

    /* Mobile */
    @media (max-width: 768px) {
        .sidebar-toggle { display: none !important; }
        .sidebar-close  { display: block; }
        .sidebar {
            transform: translateX(-100%);
            width: 280px;
            z-index: 1001;
        }
        .sidebar.open { transform: translateX(0); }
        .main-content {
            margin-left: 0;
            width: 100%;
            padding-top: 55px;
        }
        body.sidebar-open { overflow: hidden; }
    }

    /* Tablet */
    @media (min-width: 769px) and (max-width: 1200px) {
        .sidebar { width: 220px; }
        .main-content { margin-left: 220px; width: calc(100% - 220px); }
        .sidebar .nav-link { padding: 10px; font-size: 13px; }
        .sidebar-brand { font-size: 20px; }
    }

    /* Desktop */
    @media (min-width: 1201px) {
        .sidebar {
            width: 260px;
            transform: translateX(0) !important;
        }
        .main-content { margin-left: 260px; width: calc(100% - 260px); }
        .sidebar-toggle { display: none; }
    }
</style>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">

    <!-- Mobile Close Button -->
    <button class="sidebar-close" id="sidebarClose">
        <i class="bi bi-x"></i>
    </button>

    <div class="mb-4 px-2">
        <div class="sidebar-brand">InstaCar</div>
        <small class="text-uppercase" style="font-size: 10px; font-weight: 800; color: #FFD700;">
            <?= ucfirst($role) ?> Portal
        </small>
    </div>

    <ul class="nav flex-column px-0">

        <?php if ($role === 'admin'): ?>
            <!-- ==================== ADMIN ==================== -->
            <div class="sidebar-section">Main Control</div>
            <li>
                <a href="../admin/dashboard.php" class="nav-link <?= isActive('dashboard.php') ?>">
                    <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
                </a>
            </li>

            <div class="sidebar-section">Management</div>

            <!-- Users group -->
            <li>
                <button class="nav-link" type="button" data-bs-toggle="collapse" data-bs-target="#userMenu">
                    <i class="bi bi-people-fill"></i><span>Users</span>
                    <i class="bi bi-chevron-right ms-auto"></i>
                </button>
                <div class="collapse" id="userMenu">
                    <ul class="submenu">
                        <li><a href="../admin/users.php?role=admin"    class="nav-link <?= isUsersActive('admin') ?>"><i class="bi bi-shield-lock me-2"></i>Admins</a></li>
                        <li><a href="../admin/users.php?role=operator" class="nav-link <?= isUsersActive('operator') ?>"><i class="bi bi-person-badge me-2"></i>Operators</a></li>
                        <li><a href="../admin/users.php?role=staff"    class="nav-link <?= isUsersActive('staff') ?>"><i class="bi bi-person-vcard me-2"></i>Staff</a></li>
                        <li><a href="../admin/users.php?role=user"     class="nav-link <?= isUsersActive('user') ?>"><i class="bi bi-person me-2"></i>Customers</a></li>
                    </ul>
                </div>
            </li>

            <!-- Branches -->
            <li>
                <a href="../admin/branches.php" class="nav-link <?= isActive('branches.php') ?>">
                    <i class="bi bi-shop"></i><span>Branches</span>
                </a>
            </li>

            <!-- Staff Management group -->
            <li>
                <button class="nav-link" type="button" data-bs-toggle="collapse" data-bs-target="#staffMgmtMenu">
                    <i class="bi bi-person-vcard-fill"></i><span>Staff Management</span>
                    <i class="bi bi-chevron-right ms-auto"></i>
                </button>
                <div class="collapse" id="staffMgmtMenu">
                    <ul class="submenu">
                        <li>
                            <a href="../admin/staff_manager.php" class="nav-link <?= isActive('staff_manager.php') ?>">
                                <i class="bi bi-grid-1x2-fill me-2"></i>Staff
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Fleet Management group -->
            <li>
                <button class="nav-link" type="button" data-bs-toggle="collapse" data-bs-target="#fleetMenu">
                    <i class="bi bi-car-front-fill"></i><span>Fleet Management</span>
                    <i class="bi bi-chevron-right ms-auto"></i>
                </button>
                <div class="collapse" id="fleetMenu">
                    <ul class="submenu">
                        <li><a href="../shared/cars.php"             class="nav-link <?= isActive('cars.php') ?>"><i class="bi bi-car-front me-2"></i>Fleet</a></li>
                        <li><a href="../shared/my_car_schedules.php" class="nav-link <?= isActive('my_car_schedules.php') ?>"><i class="bi bi-calendar2-week me-2"></i>Schedules</a></li>
                    </ul>
                </div>
            </li>

            <!-- Reviews -->
            <li>
                <a href="../admin/review_list.php" class="nav-link <?= isActive('review_list.php') ?>">
                    <i class="bi bi-star-fill"></i><span>Reviews</span>
                </a>
            </li>

            <div class="sidebar-section">Bookings</div>
            <li>
                <button class="nav-link" type="button" data-bs-toggle="collapse" data-bs-target="#bookingMenu">
                    <i class="bi bi-calendar-event-fill"></i><span>Bookings</span>
                    <i class="bi bi-chevron-right ms-auto"></i>
                </button>
                <div class="collapse" id="bookingMenu">
                    <ul class="submenu">
                        <li><a href="../shared/calendar.php"       class="nav-link <?= isActive('calendar.php') ?>"><i class="bi bi-calendar3 me-2"></i>Calendar View</a></li>
                        <li><a href="../admin/bookings_online.php" class="nav-link <?= isActive('bookings_online.php') ?>"><i class="bi bi-globe me-2"></i>Online Bookings</a></li>
                        <li><a href="../admin/bookings_manual.php" class="nav-link <?= isActive('bookings_manual.php') ?>"><i class="bi bi-pencil-square me-2"></i>Manual Bookings</a></li>
                    </ul>
                </div>
            </li>

            <div class="sidebar-section">Financials</div>
            <li>
                <button class="nav-link" type="button" data-bs-toggle="collapse" data-bs-target="#finMenu">
                    <i class="bi bi-wallet2"></i><span>Accounting</span>
                    <i class="bi bi-chevron-right ms-auto"></i>
                </button>
                <div class="collapse" id="finMenu">
                    <ul class="submenu">
                        <li><a href="../admin/expenses.php"    class="nav-link <?= isActive('expenses.php') ?>"><i class="bi bi-cart-dash me-2"></i>Expenses</a></li>
                        <li><a href="../admin/settlements.php" class="nav-link <?= isActive('settlements.php') ?>"><i class="bi bi-cash-coin me-2"></i>Payments</a></li>
                        <li><a href="../admin/remittance.php"  class="nav-link <?= isActive('remittance.php') ?>"><i class="bi bi-send-check me-2"></i>Remittance</a></li>
                    </ul>
                </div>
            </li>

            <div class="sidebar-section">Reports</div>
            <li>
                <button class="nav-link" type="button" data-bs-toggle="collapse" data-bs-target="#reportMenu">
                    <i class="bi bi-bar-chart-steps"></i><span>Analytics</span>
                    <i class="bi bi-chevron-right ms-auto"></i>
                </button>
                <div class="collapse" id="reportMenu">
                    <ul class="submenu">
                        <li><a href="../admin/admin_revenue.php"    class="nav-link <?= isActive('admin_revenue.php') ?>"><i class="bi bi-house-door me-2"></i>House Revenue</a></li>
                        <li><a href="../admin/operator_revenue.php" class="nav-link <?= isActive('operator_revenue.php') ?>"><i class="bi bi-briefcase me-2"></i>MGT Revenue</a></li>
                        <li><a href="../admin/income.php"           class="nav-link <?= isActive('income.php') ?>"><i class="bi bi-graph-up-arrow me-2"></i>Income Statement</a></li>
                    </ul>
                </div>
            </li>
        <?php endif; ?>

        <?php if ($role === 'operator'): ?>
            <!-- ==================== OPERATOR ==================== -->
            <div class="sidebar-section">Overview</div>
            <li>
                <a href="../operator/dashboard.php" class="nav-link <?= isActive('dashboard.php') ?>">
                    <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
                </a>
            </li>

            <div class="sidebar-section">Fleet</div>
            <li>
                <a href="../shared/calendar.php" class="nav-link <?= isActive('calendar.php') ?>">
                    <i class="bi bi-calendar3"></i><span>Calendar View</span>
                </a>
            </li>
            <li>
                <button class="nav-link" type="button" data-bs-toggle="collapse" data-bs-target="#myFleetMenu">
                    <i class="bi bi-car-front-fill"></i><span>My Fleet</span>
                    <i class="bi bi-chevron-right ms-auto"></i>
                </button>
                <div class="collapse" id="myFleetMenu">
                    <ul class="submenu">
                        <li><a href="../shared/cars.php"             class="nav-link <?= isActive('cars.php') ?>"><i class="bi bi-car-front me-2"></i>Fleet Manager</a></li>
                        <li><a href="../shared/my_car_schedules.php" class="nav-link <?= isActive('my_car_schedules.php') ?>"><i class="bi bi-calendar2-week me-2"></i>Schedules</a></li>
                    </ul>
                </div>
            </li>
            <li>
                <a href="../operator/bookings.php" class="nav-link <?= isActive('bookings.php') ?>">
                    <i class="bi bi-journal-check"></i><span>Reservations</span>
                </a>
            </li>

            <div class="sidebar-section">Finances</div>
            <li>
                <a href="../operator/revenue.php" class="nav-link <?= isActive('revenue.php') ?>">
                    <i class="bi bi-cash-stack"></i><span>Earnings</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if ($role === 'staff'): ?>
            <!-- ==================== STAFF ==================== -->
            <div class="sidebar-section">Overview</div>
            <li>
                <a href="../staff/dashboard.php" class="nav-link <?= isActive('dashboard.php') ?>">
                    <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
                </a>
            </li>

            <div class="sidebar-section">Operations</div>
            <li>
                <a href="../shared/calendar.php" class="nav-link <?= isActive('calendar.php') ?>">
                    <i class="bi bi-calendar3"></i><span>Calendar View</span>
                </a>
            </li>

            <div class="sidebar-section">Bookings</div>
            <li>
                <a href="../staff/bookings.php" class="nav-link <?= isActive('bookings.php') ?>">
                    <i class="bi bi-journal-check"></i><span>Manage Bookings</span>
                </a>
            </li>
            <li>
                <a href="../staff/bookings_manual.php" class="nav-link <?= isActive('bookings_manual.php') ?>">
                    <i class="bi bi-pencil-square"></i><span>Manual Booking</span>
                </a>
            </li>

            <div class="sidebar-section">Support</div>
            <li>
                <a href="../staff/customers.php" class="nav-link <?= isActive('customers.php') ?>">
                    <i class="bi bi-people-fill"></i><span>Customers</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if ($role === 'user'): ?>
            <!-- ==================== CUSTOMER ==================== -->
            <div class="sidebar-section">Navigation</div>
            <li>
                <a href="../user/dashboard.php" class="nav-link <?= isActive('dashboard.php') ?>">
                    <i class="bi bi-house-heart-fill"></i><span>Home</span>
                </a>
            </li>

            <div class="sidebar-section">Rental Service</div>
            <li>
                <a href="../user/cars.php" class="nav-link <?= isActive('cars.php') ?>">
                    <i class="bi bi-search-heart"></i><span>Find a Car</span>
                </a>
            </li>
            <li>
                <a href="../user/mybookings.php" class="nav-link <?= isActive('mybookings.php') ?>">
                    <i class="bi bi-clock-history"></i><span>My Trips</span>
                </a>
            </li>
        <?php endif; ?>

    </ul>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar        = document.getElementById('sidebar');
    const sidebarClose   = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const body           = document.body;
    const STORAGE_KEY    = 'sidebar-open-groups';

    // ---------------------------------------------------------
    // 1. MOBILE SIDEBAR OPEN / CLOSE
    // ---------------------------------------------------------
    function openSidebar() {
        sidebar.classList.add('open');
        sidebarOverlay.style.display = 'block';
        body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        sidebarOverlay.style.display = 'none';
        body.classList.remove('sidebar-open');
    }

    if (sidebarClose)   sidebarClose.addEventListener('click', closeSidebar);
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    // ---------------------------------------------------------
    // 2. PERSIST OPEN COLLAPSE GROUPS
    // ---------------------------------------------------------
    function getOpenGroups() {
        try {
            return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');
        } catch (e) {
            return [];
        }
    }

    function saveOpenGroups(list) {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(list));
        } catch (e) { /* ignore */ }
    }

    function rememberGroup(id, isOpen) {
        let open = getOpenGroups();
        if (isOpen && !open.includes(id)) {
            open.push(id);
        } else if (!isOpen) {
            open = open.filter(x => x !== id);
        }
        saveOpenGroups(open);
    }

    // Restore previously-open groups
    getOpenGroups().forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.classList.add('show');
    });

    // Persist state on toggle
    document.querySelectorAll('.collapse').forEach(function(collapse) {
        collapse.addEventListener('shown.bs.collapse', function() {
            rememberGroup(this.id, true);
        });
        collapse.addEventListener('hidden.bs.collapse', function() {
            rememberGroup(this.id, false);

            // Clear parent highlight when the group is manually closed
            const parentBtn = document.querySelector('[data-bs-target="#' + this.id + '"]');
            if (parentBtn) parentBtn.classList.remove('active-parent');
        });
    });

    // ---------------------------------------------------------
    // 3. AUTO-EXPAND + MARK PARENT OF THE ACTIVE CHILD
    // ---------------------------------------------------------
    const activeChild = sidebar.querySelector('.submenu .nav-link.active');
    if (activeChild) {
        const parentCollapse = activeChild.closest('.collapse');
        if (parentCollapse) {
            // Ensure it's open
            parentCollapse.classList.add('show');
            rememberGroup(parentCollapse.id, true);

            // Mark parent button with subtle active state
            const parentBtn = document.querySelector(
                '[data-bs-target="#' + parentCollapse.id + '"]'
            );
            if (parentBtn) {
                parentBtn.classList.add('active-parent');
                parentBtn.setAttribute('aria-expanded', 'true');
            }
        }
    }

    // ---------------------------------------------------------
    // 4. MOBILE AUTO-CLOSE ON LINK TAP
    //    (Skips parent toggles, which have data-bs-toggle)
    // ---------------------------------------------------------
    sidebar.querySelectorAll('.nav-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                if (!this.hasAttribute('data-bs-toggle')) {
                    setTimeout(closeSidebar, 150);
                }
            }
        });
    });

    // ---------------------------------------------------------
    // 5. RESET MOBILE STATE ON RESIZE
    // ---------------------------------------------------------
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('open');
            sidebarOverlay.style.display = 'none';
            body.classList.remove('sidebar-open');
        }
    });
});
</script>