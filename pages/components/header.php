<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$name = $_SESSION['name'] ?? 'Guest';
$role = $_SESSION['role'] ?? 'guest';

$firstLetter = strtoupper(substr($name, 0, 1));
?>

<style>

@media (max-width: 768px) {

    .panel-title{
        font-size:.8rem !important;
    }

    .panel-subtitle{
        font-size:7px !important;
    }

    .user-info-text {
        display: none;
    }

    .header-sidebar-toggle {
        margin-right: 10px;
        flex-shrink: 0;
    }

    .instacar-avatar {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }

}

.instacar-topbar {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    box-shadow: 0 2px 15px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    position: sticky;
    top: 0;
    z-index: 999;
}

/* Sidebar toggle button for mobile */
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
}

.panel-title span {
    color: #FFD700;
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

.user-dropdown-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #000000;
    padding: 6px 12px;
    border-radius: 10px;
    background: #ffffff;
    color: #000000;
    transition: all 0.2s ease;
}

.user-dropdown-toggle:hover {
    background: #fffcf0;
    border-color: #FFD700;
}

/* User info text - hide on small screens */
.user-info-text {
    display: block;
}

.user-info-status {
    font-size: 10px;
    color: #ccac00;
    font-weight: 800;
}

/* Dropdown styles */
.dropdown-menu {
    border-radius: 10px;
    border: 1px solid #000000;
    box-shadow: 4px 4px 0px rgba(0,0,0,0.1);
}

.dropdown-item {
    font-size: 14px;
    font-weight: 600;
    color: #000000;
}

.dropdown-item:hover {
    background: #FFD700;
    color: #000000;
}

.dropdown-item i {
    color: #FFD700;
}

.dropdown-item:hover i {
    color: #000000;
}

/* Responsive Styles - ORIGINAL SIZE */
@media (max-width: 768px) {
    .instacar-topbar {
        padding: 12px 16px !important;
    }
    
    .header-sidebar-toggle {
        display: flex;
    }
    
    .panel-title {
        font-size: 0.9rem;
    }
    
    .panel-subtitle {
        font-size: 8px;
    }
    
    .user-info-text {
        display: none;
    }
    
    .user-dropdown-toggle {
        padding: 6px 10px;
        gap: 6px;
    }
    
    .instacar-avatar {
        width: 32px;
        height: 32px;
        font-size: 14px;
    }
}

/* Tablet - Slight adjustment */
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

/* Desktop - Original size restored */
@media (min-width: 1201px) {
    .instacar-topbar {
        padding: 16px 24px !important;
    }
}
</style>

<nav class="navbar instacar-topbar px-4 py-3">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        
        <div class="d-flex align-items-center">
            <!-- Sidebar Toggle Button for Mobile -->
            <button class="header-sidebar-toggle" id="headerSidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            
            <div>
                <div class="panel-title">
                    MANAGEMENT <span>PORTAL</span>
                </div>
                <div class="text-muted panel-subtitle">
                    <?= strtoupper($role) ?> ACCESS
                </div>
            </div>
        </div>

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
</nav>

<script>
// Sidebar toggle functionality
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
    
    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            if (sidebar) {
                sidebar.classList.remove('open');
                sidebarOverlay.style.display = 'none';
                document.body.classList.remove('sidebar-open');
            }
        });
    }
});
</script>

<style>
body.sidebar-open {
    overflow: hidden;
}

.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 998;
    display: none;
}

.sidebar {
    z-index: 1000;
}

.instacar-topbar {
    z-index: 999;
}
</style>