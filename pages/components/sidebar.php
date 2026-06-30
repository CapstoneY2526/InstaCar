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
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
    /* Mobile Toggle Button */
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
    
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 240px; /* ORIGINAL SIZE */
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
    
    /* Mobile close button inside sidebar */
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
    
    /* Custom Scrollbar */
    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-track { background: transparent; }
    .sidebar::-webkit-scrollbar-thumb { background: #FFD700; border-radius: 10px; }

    .sidebar-brand { 
        font-weight: 800; 
        font-size: 24px; 
        color: #FFFFFF; 
        letter-spacing: -0.5px; 
    }
    
    /* Yellow accent for the logo dot */
    .sidebar-brand::after { content: '.'; color: #FFD700; }

    .sidebar small { color: #888888; }

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

    .submenu { list-style: none; padding-left: 20px; margin-top: 5px; padding-bottom: 10px; }
    .submenu .nav-link { font-size: 13px; padding: 8px 12px; color: #BBBBBB !important; }
    .submenu .nav-link:hover { color: #FFD700 !important; background: transparent !important; }
    .submenu .nav-link.active { color: #FFD700 !important; background: transparent !important; font-weight: bold; box-shadow: none; }

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
    
    /* Main content adjustment - ORIGINAL SIZE */
    .main-content {
        margin-left: 260px;
        width: calc(100% - 260px);
        transition: margin-left 0.3s ease;
    }
    
    /* Mobile Responsive */
   @media (max-width: 768px) {

        .sidebar-toggle {
            display: none !important;
        }

        .sidebar-close {
            display: block;
        }

        .sidebar {
            transform: translateX(-100%);
            width: 280px;
            z-index: 1001;
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0;
            width: 100%;
        }

        body.sidebar-open {
            overflow: hidden;
        }
    }

    @media (max-width:768px){

        .main-content{
            padding-top:20px;
        }

    }
    
    /* Tablet - Slightly smaller but not too small */
    @media (min-width: 769px) and (max-width: 1200px) {

        .sidebar {
            width: 220px;
        }

        .main-content {
            margin-left: 220px;
            width: calc(100% - 220px);
        }

        .sidebar .nav-link {
            padding: 10px;
            font-size: 13px;
        }

        .sidebar-brand {
            font-size: 20px;
        }
    }
    
    /* Desktop - Original size restored */
    @media (min-width: 1201px) {
        .sidebar {
            width: 260px;
            transform: translateX(0) !important;
        }
        
        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
        }
        
        .sidebar-toggle {
            display: none;
        }
    }

    @media (max-width:768px){
        .main-content {
            padding-top: 55px;
        }
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
            <div class="sidebar-section">Main Control</div>
            <li><a href="../admin/dashboard.php" class="nav-link <?= isActive('dashboard.php') ?>"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></li>

            <div class="sidebar-section">Management</div>
            <li>
                <button class="nav-link" type="button" data-bs-toggle="collapse" data-bs-target="#userMenu">
                    <i class="bi bi-people-fill"></i><span>Users</span>
                    <i class="bi bi-chevron-right ms-auto"></i>
                </button>
                <div class="collapse" id="userMenu">    
                    <ul class="submenu">
                        <li><a href="../admin/users.php?role=admin" class="nav-link"><i class="bi bi-shield-lock me-2"></i>Admins</a></li>
                        <li><a href="../admin/users.php?role=operator" class="nav-link"><i class="bi bi-person-badge me-2"></i>Operators</a></li>
                        <li><a href="../admin/users.php?role=user" class="nav-link"><i class="bi bi-person me-2"></i>Customers</a></li>
                    </ul>
                </div>
            </li>

            <li><a href="../shared/cars.php" class="nav-link <?= isActive('cars.php') ?>"><i class="bi bi-car-front-fill"></i><span>Fleet Manager</span></a></li>

            <li><a href="../admin/review_list.php" class="nav-link <?= isActive('review_list.php') ?>"><i class="bi bi-person me-2"></i><span>Reviews</span></a></li>

            <li>
                <button class="nav-link" type="button" data-bs-toggle="collapse" data-bs-target="#bookingMenu">
                    <i class="bi bi-calendar-event-fill"></i><span>Bookings</span>
                    <i class="bi bi-chevron-right ms-auto"></i>
                </button>
                <div class="collapse" id="bookingMenu">
                    <ul class="submenu">
                        <li><a href="../shared/calendar.php" class="nav-link"><i class="bi bi-calendar3 me-2"></i>Calendar View</a></li>
                        <li><a href="../admin/bookings_online.php" class="nav-link"><i class="bi bi-globe me-2"></i>Online Bookings</a></li>
                        <li><a href="../admin/bookings_manual.php" class="nav-link"><i class="bi bi-pencil-square me-2"></i>Manual Bookings</a></li>
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
                        <li><a href="../admin/expenses.php" class="nav-link"><i class="bi bi-cart-dash me-2"></i>Expenses</a></li>
                        <li><a href="../admin/settlements.php" class="nav-link"><i class="bi bi-cash-coin me-2"></i>Payments</a></li>
                        <li><a href="../admin/remittance.php" class="nav-link"><i class="bi bi-send-check me-2"></i>Remittance</a></li>
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
                        <li><a href="../admin/admin_revenue.php" class="nav-link"><i class="bi bi-house-door me-2"></i>House Revenue</a></li>
                        <li><a href="../admin/operator_revenue.php" class="nav-link"><i class="bi bi-briefcase me-2"></i>MGT Revenue</a></li>
                        <li><a href="../admin/income.php" class="nav-link"><i class="bi bi-graph-up-arrow me-2"></i>Income Statement</a></li>
                    </ul>
                </div>
            </li>
        <?php endif; ?>

        <?php if ($role === 'operator'): ?>
            <div class="sidebar-section">Overview</div>
            <li><a href="../operator/dashboard.php" class="nav-link <?= isActive('dashboard.php') ?>"><i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span></a></li>
            
            <div class="sidebar-section">Inventory</div>
            <li><a href="../shared/calendar.php" class="nav-link"><i class="bi bi-calendar3 me-2"></i>Calendar View</a></li>
            <li><a href="../shared/cars.php" class="nav-link <?= isActive('cars.php') ?>"><i class="bi bi-car-front-fill"></i><span>My Fleet</span></a></li>
            <li><a href="../operator/bookings.php" class="nav-link <?= isActive('bookings.php') ?>"><i class="bi bi-journal-check"></i><span>Reservations</span></a></li>
            
            <div class="sidebar-section">Finances</div>
            <li><a href="../operator/revenue.php" class="nav-link <?= isActive('revenue.php') ?>"><i class="bi bi-cash-stack"></i><span>Earnings</span></a></li>
        <?php endif; ?>

        <?php if ($role === 'user'): ?>
            <div class="sidebar-section">Navigation</div>
            <li><a href="../user/dashboard.php" class="nav-link <?= isActive('dashboard.php') ?>"><i class="bi bi-house-heart-fill"></i><span>Home</span></a></li>
            
            <div class="sidebar-section">Rental Service</div>
            <li><a href="../user/cars.php" class="nav-link <?= isActive('cars.php') ?>"><i class="bi bi-search-heart"></i><span>Find a Car</span></a></li>
            <li><a href="../user/mybookings.php" class="nav-link <?= isActive('mybookings.php') ?>"><i class="bi bi-clock-history"></i><span>My Trips</span></a></li>
        <?php endif; ?>

    </ul>
</div>

<script>
// Sidebar Toggle for Mobile
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const body = document.body;
    
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
    
    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }
    
    // Close sidebar when clicking a link on mobile
    const navLinks = sidebar.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                if (!this.hasAttribute('data-bs-toggle')) {
                    setTimeout(closeSidebar, 150);
                }
            }
        });
    });
    
    // Handle window resize - reset sidebar state
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('open');
            sidebarOverlay.style.display = 'none';
            body.classList.remove('sidebar-open');
        }
    });
});
</script>

<style>
    /* Adjust main content margin - ORIGINAL SIZE */
    .main-content {
        margin-left: 260px;
        width: calc(100% - 260px);
        transition: margin-left 0.3s ease;
    }
    
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            width: 100%;
        }
    }
    
    @media (min-width: 769px) and (max-width: 1200px) {
        .main-content {
            margin-left: 240px;
            width: calc(100% - 240px);
        }
    }
    
    @media (min-width: 1201px) {
        .main-content {
            margin-left: 260px;
            width: calc(100% - 260px);
        }
    }
</style>