<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$name = $_SESSION['name'] ?? 'Guest';
$role = $_SESSION['role'] ?? 'guest';

$firstLetter = strtoupper(substr($name, 0, 1));
?>

<style>

/* Lock horizontal scroll on the root elements only */
html, body {
    max-width: 100%;
    overflow-x: hidden;
}

/* Fix mobile overflow safely without breaking desktop layout */
@media (max-width: 575.98px) {
    /* 1. Stack stat cards into a clean 1-column layout */
    .stat-card,
    [class*="col-"] > .stat-card {
        width: 100% !important;
    }
    
    /* Force grid columns to take full width on mobile */
    .row > [class*="col-6"],
    .row > [class*="col-sm-6"] {
        flex: 0 0 100% !important;
        max-width: 100% !important;
        margin-bottom: 0.75rem;
    }

    /* 2. Fix inner card layout so icon and text sit side-by-side cleanly */
    .stat-card .d-flex {
        gap: 12px !important;
    }

    /* 3. Fix header action row from overflowing */
    .instacar-topbar .d-flex {
        gap: 6px !important;
    }
    
    .panel-title {
        font-size: 0.75rem !important;
    }
}

/* Base Toggle Button */
.user-dropdown-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 12px;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

/* --- LIGHT MODE --- */
body:not(.dark-mode) .user-dropdown-toggle {
    background: #ffffff !important;
    border: 1.5px solid #000000 !important;
    color: #000000 !important;
}

body:not(.dark-mode) .user-dropdown-toggle:hover {
    background: #f8fafc !important;
    border-color: #1e293b !important;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
    transform: translateY(-1px);
}

body:not(.dark-mode) .dropdown-menu {
    background-color: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1) !important;
}

body:not(.dark-mode) .dropdown-item {
    color: #000000 !important;
}

body:not(.dark-mode) .dropdown-item:hover {
    background-color: #FFD700 !important;
    color: #000000 !important;
}

/* --- DARK MODE --- */
body.dark-mode .user-dropdown-toggle {
    background: #18181b !important; 
    border: 1.5px solid #facc15 !important;
    color: #ffffff !important;
}

body.dark-mode .user-dropdown-toggle:hover {
    background: #27272a !important;
    border-color: #ffffff !important;
    box-shadow: 0 0 15px rgba(255, 255, 255, 0.35) !important;
    transform: translateY(-1px);
}

body.dark-mode .dropdown-menu {
    background-color: #18181b !important;
    border: 1px solid #27272a !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.6) !important;
}

body.dark-mode .dropdown-item {
    color: #f4f4f5 !important;
}

body.dark-mode .dropdown-item:hover {
    background-color: #facc15 !important;
    color: #000000 !important;
}

body.dark-mode .dropdown-item i {
    color: #a1a1aa;
}

body.dark-mode .dropdown-item:hover i {
    color: #000000 !important;
}

/* Dynamic Caret Arrow Rotation */
.user-dropdown-toggle::after {
    transition: transform 0.25s ease;
}

.user-dropdown-toggle.show::after {
    transform: rotate(180deg);
}

/* Animated Smooth Dropdown Animation */
.dropdown-menu {
    display: block !important;
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px) scale(0.97);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
    border-radius: 12px !important;
    padding: 6px !important;
    margin-top: 8px !important;
}

.dropdown-menu.show {
    opacity: 1 !important;
    visibility: visible !important;
    transform: translateY(0) scale(1) !important;
}

.dropdown-item {
    font-size: 14px;
    font-weight: 600;
    border-radius: 8px;
    padding: 8px 12px !important;
    transition: all 0.15s ease !important;
}

.dropdown-item:hover {
    transform: translateX(4px);
}

/* Theme Toggle Button Base */
.theme-toggle-btn {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1.5px solid #000000;
    background: #ffffff;
    transition: all 0.25s ease;
    cursor: pointer;
    flex-shrink: 0;
}

body:not(.dark-mode) .theme-toggle-btn {
    background: #ffffff !important;
    border-color: #000000 !important;
}

body:not(.dark-mode) .theme-toggle-btn i {
    color: #000000 !important;
}

body:not(.dark-mode) .theme-toggle-btn:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

body.dark-mode .theme-toggle-btn {
    background: #18181b !important;
    border-color: #facc15 !important;
}

body.dark-mode .theme-toggle-btn i {
    color: #facc15 !important;
}

body.dark-mode .theme-toggle-btn:hover {
    border-color: #ffffff !important;
    box-shadow: 0 0 12px rgba(255, 255, 255, 0.35);
}

.instacar-topbar {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    box-shadow: 0 2px 15px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    position: sticky;
    top: 0;
    z-index: 1050;
}

.header-sidebar-toggle {
    display: none;
    background: #FFD700;
    border: none;
    border-radius: 8px;
    padding: 8px 10px;
    cursor: pointer;
    color: #000;
    font-size: 18px;
    margin-right: 12px;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.header-sidebar-toggle:hover {
    background: #e6c200;
}

.panel-title {
    font-weight: 900;
    font-size: 1.1rem;
    letter-spacing: 0.5px;
    color: #000000;
    text-transform: uppercase;
    line-height: 1.2;
}

.panel-title span {
    color: #FFD700;
}

body.dark-mode .panel-title {
    color: #ffffff !important;
}

.panel-subtitle {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1px;
}

.instacar-avatar {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: #FFD700;
    color: #000000;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    flex-shrink: 0;
}

.user-info-text {
    display: block;
}

.user-info-status {
    font-size: 10px;
    color: #ccac00;
    font-weight: 800;
}

/* Responsive Overrides */
@media (max-width: 768px) {
    .instacar-topbar {
        padding: 10px 14px !important;
    }
    
    .instacar-topbar .container-fluid {
        flex-wrap: nowrap !important;
        gap: 8px !important;
    }

    .header-sidebar-toggle {
        display: flex;
        margin-right: 6px;
    }
    
    .panel-title {
        font-size: 0.85rem;
    }
    
    .panel-subtitle {
        font-size: 8px;
    }
    
    .user-info-text {
        display: none;
    }
    
    .user-dropdown-toggle {
        padding: 4px 8px;
        gap: 4px;
    }
    
    .instacar-avatar {
        width: 32px;
        height: 32px;
        font-size: 14px;
    }

    .theme-toggle-btn {
        width: 34px;
        height: 34px;
    }
}

@media (min-width: 769px) and (max-width: 1200px) {
    .instacar-topbar {
        padding: 14px 20px !important;
    }
    
    .panel-title {
        font-size: 1rem;
    }
    
    .user-info-text .fw-bold {
        font-size: 12px;
    }
    
    .user-info-status {
        font-size: 9px;
    }
}

@media (min-width: 1201px) {
    .instacar-topbar {
        padding: 16px 24px !important;
    }
}

/* --- MOBILE OFF-CANVAS SIDEBAR OVERRIDES --- */
@media (max-width: 768px) {
    /* Ensure topbar sits below open overlay/sidebar */
    .instacar-topbar {
        z-index: 1000 !important;
    }

    /* Force mobile sidebar off-canvas drawer */
    #sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        bottom: 0 !important;
        width: 280px !important;
        max-width: 85vw !important;
        height: 100vh !important;
        z-index: 1090 !important; /* Higher than header (1000) */
        transform: translateX(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        box-shadow: 10px 0 30px rgba(0, 0, 0, 0.5) !important;
        overflow-y: auto !important;
    }

    /* Slide-in state */
    #sidebar.open {
        transform: translateX(0) !important;
    }

    /* Background overlay backdrop */
    #sidebarOverlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: rgba(0, 0, 0, 0.6) !important;
        backdrop-filter: blur(2px);
        z-index: 1080 !important; /* Sitting right below sidebar */
    }
}
</style>

<nav class="navbar instacar-topbar">
    <div class="container-fluid d-flex justify-content-between align-items-center flex-nowrap">
        
        <div class="d-flex align-items-center min-w-0 me-2">
            <!-- Sidebar Toggle Button for Mobile -->
            <button class="header-sidebar-toggle" id="headerSidebarToggle" type="button" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            
            <div class="text-truncate">
                <div class="panel-title text-truncate">
                    MANAGEMENT <span>PORTAL</span>
                </div>
                <div class="text-muted panel-subtitle text-truncate">
                    <?= htmlspecialchars(strtoupper($role)) ?> ACCESS
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 gap-sm-3 flex-shrink-0">
            <!-- Header Dark Mode Toggle Button -->
            <button class="theme-toggle-btn" type="button" id="headerThemeToggleBtn" title="Toggle dark mode" aria-label="Toggle dark mode">
                <i class="bi bi-moon-stars-fill" id="headerThemeToggleIcon"></i>
            </button>

            <div class="dropdown">
                <button
                    class="btn user-dropdown-toggle dropdown-toggle d-flex align-items-center"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    <div class="instacar-avatar">
                        <?= htmlspecialchars($firstLetter) ?>
                    </div>

                    <div class="text-start user-info-text">
                        <div class="fw-bold small" style="line-height: 1;">
                            <?= htmlspecialchars($name) ?>
                        </div>
                        <div class="user-info-status">
                            ACTIVE
                        </div>
                    </div>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li>
                        <a class="dropdown-item py-2" href="../shared/profile.php">
                            <i class="bi bi-person-fill me-2"></i>
                            My Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item py-2 text-danger" href="../../process/auth/logout.php">
                            <i class="bi bi-box-arrow-right-fill me-2"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const headerToggle = document.getElementById('headerSidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (headerToggle) {
        headerToggle.addEventListener('click', function() {
            if (sidebar) {
                sidebar.classList.add('open');
                if (sidebarOverlay) {
                    sidebarOverlay.style.display = 'block';
                }
                document.body.classList.add('sidebar-open');
            }
        });
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            if (sidebar) {
                sidebar.classList.remove('open');
                sidebarOverlay.style.display = 'none';
                document.body.classList.remove('sidebar-open');
            }
        });
    }

    const toggleBtn = document.getElementById('headerThemeToggleBtn');
    const toggleIcon = document.getElementById('headerThemeToggleIcon');
    const STORAGE_KEY = 'instacar-admin-theme';

    function applyTheme(isDark) {
        document.body.classList.toggle('dark-mode', isDark);
        if (toggleIcon) {
            toggleIcon.className = isDark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
        }
    }

    applyTheme(localStorage.getItem(STORAGE_KEY) === 'dark');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const isDark = !document.body.classList.contains('dark-mode');
            applyTheme(isDark);
            localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light');
        });
    }
});
</script>