<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth Check - Redirect using header instead of inline script
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to continue.";
    header("Location: ../../index.php");
    exit();
}

$pageTitle = 'Vehicle Gallery';
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get filter from URL
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'All';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';

// Get distinct car types for filter dropdown
$typeQuery = "SELECT DISTINCT type FROM cars WHERE status IN ('Available', 'Active', 'Rented') ORDER BY type";
$typeResult = mysqli_query($conn, $typeQuery);
$car_types = [];
while ($row = mysqli_fetch_assoc($typeResult)) {
    $car_types[] = $row['type'];
}

// Build query with filters
$query = "SELECT * FROM cars WHERE status IN ('Available', 'Active', 'Rented')";

if ($type_filter !== 'All') {
    $type_filter_safe = mysqli_real_escape_string($conn, $type_filter);
    $query .= " AND type = '$type_filter_safe'";
}

if ($status_filter !== 'All') {
    $status_filter_safe = mysqli_real_escape_string($conn, $status_filter);
    $query .= " AND status = '$status_filter_safe'";
}

$query .= " ORDER BY created_at DESC";

$cars_result = mysqli_query($conn, $query);
$cars = [];
while ($row = mysqli_fetch_assoc($cars_result)) {
    $cars[] = $row;
}

// Ensure path resolution matches assets directory
$qr_file_path = __DIR__ . '/../../public/assets/images/qr_code_payment.png';

// Cache-busting version query parameter
if (file_exists($qr_file_path)) {
    $qr_image_src = "../../public/assets/images/qr_code_payment.png?v=" . filemtime($qr_file_path);
} else {
    $qr_image_src = "../../public/assets/images/qr_code_payment.png?v=" . time();
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    /* ========================================================
       BASE LAYOUT & LIGHT MODE STYLES
       ======================================================== */
    body, 
    button, 
    input, 
    select, 
    textarea, 
    .form-control, 
    .btn, 
    .table,
    .modal-content { 
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; 
    }

    body { 
        background-color: var(--brand-bg, #f8fafc); 
        overflow-x: hidden; 
    }

    .main-content { 
        background-color: var(--brand-bg, #f8fafc);
        min-height: 100vh; 
        width: 100%;
        transition: background-color 0.25s ease, color 0.25s ease;
    }

    /* CSS Variables for Dynamic Theme Support (Yellow Theme Accent) */
    :root {
        --bg-main: #f8fafc;
        --bg-card: #ffffff;
        --bg-alt: #f1f5f9;
        --text-primary: #0f172a;
        --text-secondary: #64748b;
        --border-color: #e2e8f0;
        --dropdown-shadow: rgba(0, 0, 0, 0.15);
        --modal-bg: #ffffff;
        --modal-text: #334155;

        /* Core Yellow Palette */
        --yellow-primary: #eab308;
        --yellow-hover: #ca8a04;
        --yellow-light: #facc15;
        --yellow-dim: rgba(234, 179, 8, 0.15);
        --text-on-yellow: #000000;
    }

    /* Dark Mode Variable Overrides */
    [data-bs-theme="dark"], body.dark-mode {
        --bg-main: #0a0a0a;
        --bg-card: #141414;
        --bg-alt: #1f1f23;
        --text-primary: #f1f5f9;
        --text-secondary: #cbd5e1;
        --border-color: #27272a;
        --dropdown-shadow: rgba(0, 0, 0, 0.5);
        --modal-bg: #141414;
        --modal-text: #cbd5e1;
    }

    .hover-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.25s ease;
        cursor: pointer;
        background-color: var(--bg-card) !important;
        color: var(--text-primary) !important;
        border: 1px solid var(--border-color) !important;
    }
    
    .hover-card:hover,
    .hover-card.selected {
        transform: translateY(-3px);
        border-color: var(--yellow-primary) !important;
        box-shadow: 0 0 15px rgba(234, 179, 8, 0.4) !important;
    }
    
    .transition-all {
        transition: all 0.3s ease;
    }
    
    /* Filter Section Styling */
    .filter-section {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .filter-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
        margin-bottom: 0.5rem;
    }
    
    .filter-select {
        border-radius: 12px;
        border: 1px solid var(--border-color);
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        background-color: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .filter-select:hover,
    .filter-select:focus {
        border-color: var(--yellow-primary);
        box-shadow: 0 0 0 3px var(--yellow-dim);
        outline: none;
    }
    
    .reset-filter {
        border-radius: 12px;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
        border-color: var(--border-color);
    }
    
    .reset-filter:hover {
        background-color: var(--bg-alt);
        color: var(--text-primary);
    }
    
    /* Active Filter Badges */
    .active-filters {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }
    
    .filter-badge {
        background: var(--yellow-dim);
        color: #ca8a04;
        border-radius: 20px;
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid var(--yellow-primary);
    }
    
    .filter-badge a {
        color: #ca8a04;
        text-decoration: none;
        font-weight: 700;
    }
    
    .filter-badge a:hover {
        color: #dc2626;
    }
    
    /* Results Count */
    .results-count {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-top: 0.5rem;
    }

    /* Dynamic Badges & Buttons */
    .theme-badge {
        background-color: var(--bg-alt) !important;
        color: var(--text-primary) !important;
    }

    .theme-dropdown-btn {
        background-color: var(--bg-card) !important;
        border-color: var(--border-color) !important;
        color: var(--text-primary) !important;
    }

    .theme-bg-alt {
        background-color: var(--bg-alt) !important;
        color: var(--text-primary) !important;
    }

    /* Primary Accent Buttons & Yellow Button Contrast Override */
    .btn-primary,
    .button-primary,
    .book-now-btn,
    .filter-btn.active {
        background-color: var(--yellow-primary) !important;
        border-color: var(--yellow-primary) !important;
        color: var(--text-on-yellow) !important;
        font-weight: 600;
    }

    /* Force all child elements (text, icons, spans, anchors) inside primary buttons to stay black */
    .btn-primary *,
    .button-primary *,
    .book-now-btn *,
    .filter-btn.active * {
        color: var(--text-on-yellow) !important;
    }

    .btn-primary:hover,
    .button-primary:hover,
    .book-now-btn:hover,
    .filter-btn.active:hover {
        background-color: var(--yellow-hover) !important;
        border-color: var(--yellow-hover) !important;
        color: var(--text-on-yellow) !important;
    }

    .btn-primary:active,
    .btn-primary:focus {
        box-shadow: 0 0 0 3px var(--yellow-dim) !important;
    }

    /* ---- Replace Bootstrap's default blue accents with the yellow brand accent ---- */
    .text-primary {
        color: var(--yellow-hover) !important;
    }

    .badge.bg-primary {
        background-color: var(--yellow-primary) !important;
        color: var(--text-on-yellow) !important;
    }

    .border-primary {
        border-color: var(--yellow-primary) !important;
    }

    .btn-outline-primary {
        color: var(--yellow-hover) !important;
        border-color: var(--yellow-primary) !important;
        background-color: transparent !important;
        transition: all 0.2s ease;
    }

    .btn-outline-primary:hover,
    .btn-outline-primary:focus {
        background-color: var(--yellow-primary) !important;
        border-color: var(--yellow-primary) !important;
        color: var(--text-on-yellow) !important;
        transform: translateY(-1px);
    }

    .btn-outline-primary.active,
    .btn-check:checked + .btn-outline-primary {
        background-color: var(--yellow-primary) !important;
        border-color: var(--yellow-primary) !important;
        color: var(--text-on-yellow) !important;
        box-shadow: 0 2px 8px rgba(234, 179, 8, 0.35);
    }

    .modal-header.bg-primary {
        background-color: var(--yellow-primary) !important;
        border-color: var(--yellow-primary) !important;
    }

    .modal-header.bg-primary,
    .modal-header.bg-primary .modal-title,
    .modal-header.bg-primary * {
        color: var(--text-on-yellow) !important;
    }

    /* Calendar Styles */
    .calendar-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding: 0 0.5rem;
    }

    .calendar-nav button {
        background: var(--yellow-primary);
        color: var(--text-on-yellow);
        border: none;
        border-radius: 8px;
        padding: 0.25rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .calendar-nav button:hover {
        background: var(--yellow-hover);
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.25rem;
        text-align: center;
    }

    .calendar-header {
        font-weight: 600;
        color: var(--text-secondary);
        padding: 0.5rem;
        font-size: 0.75rem;
        text-transform: uppercase;
    }

    .calendar-day {
        padding: 0.5rem;
        border-radius: 8px;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: var(--bg-card);
        color: var(--text-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
    }

    .calendar-day.available {
        background: var(--yellow-dim);
        color: var(--yellow-hover);
        border: 1px solid var(--yellow-primary);
        cursor: pointer;
        font-weight: 600;
    }

    .calendar-day.available:hover {
        background: var(--yellow-primary);
        color: var(--text-on-yellow);
        transform: scale(1.05);
    }

    .calendar-day.booked {
        background: #dc3545;
        color: white;
        cursor: not-allowed;
        opacity: 0.7;
    }

    .calendar-day.selected {
        background: var(--yellow-primary) !important;
        color: var(--text-on-yellow) !important;
        font-weight: bold;
    }

    .calendar-day.today {
        border: 2px solid var(--yellow-primary);
        font-weight: bold;
    }

    .calendar-day:empty, .calendar-day.empty-day {
        background: transparent !important;
        cursor: default !important;
        border: none !important;
    }

    /* Modals Dynamic Styling */
    .modal-content {
        background-color: var(--modal-bg) !important;
        color: var(--text-primary) !important;
        border: 1px solid var(--border-color);
    }

    .agreement-text-box {
        height: 400px; 
        overflow-y: auto; 
        font-size: 0.875rem; 
        line-height: 1.8; 
        color: var(--modal-text);
        background-color: var(--bg-card) !important;
        border-color: var(--border-color) !important;
        -webkit-overflow-scrolling: touch;
    }

    /* ========================================================
       BOOKING FORM POLISH — subtle motion + brand-consistent hovers
       ======================================================== */
    @keyframes modalPopIn {
        from { opacity: 0; transform: translateY(8px) scale(0.985); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    #bookingModal.show .modal-content,
    #termsModal.show .modal-content {
        animation: modalPopIn 0.25s ease-out;
    }

    /* Availability calendar legend badges */
    #bookingModal .badge {
        transition: transform 0.15s ease;
    }
    #bookingModal .badge:hover {
        transform: translateY(-1px);
    }

    /* File upload rows */
    #bookingModal input[type="file"].form-control {
        cursor: pointer;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.15s ease;
    }
    #bookingModal input[type="file"].form-control:hover {
        border-color: var(--yellow-primary) !important;
    }
    #bookingModal input[type="file"].form-control:focus {
        box-shadow: 0 0 0 3px var(--yellow-dim) !important;
        border-color: var(--yellow-primary) !important;
    }

    /* Scan QR chip */
    #bookingModal [data-bs-target="#qrCodeModal"] {
        transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.15s ease;
    }
    #bookingModal [data-bs-target="#qrCodeModal"]:hover {
        border-color: var(--yellow-primary) !important;
        box-shadow: 0 4px 12px var(--yellow-dim);
        transform: translateY(-1px);
    }

    /* Pricing breakdown card */
    #bookingModal .theme-bg-alt {
        transition: box-shadow 0.2s ease;
    }

    /* Confirm Reservation button */
    #confirmBtn {
        position: relative;
        overflow: hidden;
        transition: transform 0.15s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }
    #confirmBtn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(234, 179, 8, 0.4) !important;
    }
    #confirmBtn:active:not(:disabled) {
        transform: translateY(0);
    }
    #confirmBtn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Calendar days get a touch more life */
    .calendar-day.available {
        transition: background-color 0.18s ease, color 0.18s ease, transform 0.15s ease, box-shadow 0.15s ease;
    }
    .calendar-day.selected {
        box-shadow: 0 2px 10px rgba(234, 179, 8, 0.45);
    }

    /* Duration quick-select buttons */
    .duration-btn {
        transition: all 0.2s ease;
    }
    .duration-btn:hover {
        transform: translateY(-1px);
    }

    /* Date/time preview slide-in */
    @keyframes previewSlideIn {
        from { opacity: 0; transform: translateY(-4px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .date-time-preview[style*="display: block"],
    .date-time-preview.d-block {
        animation: previewSlideIn 0.2s ease-out;
    }

    /* Cards Positioning */
    .car-item {
        position: relative;
        z-index: 1;
    }

    .car-item.dropdown-active {
        z-index: 1050 !important;
    }

    .rate-dropdown-overlay {
        bottom: 100% !important;
        top: auto !important;
        left: 0;
        margin-bottom: 8px !important;
        transform: none !important;
        z-index: 1050 !important;
        box-shadow: 0 10px 25px var(--dropdown-shadow) !important;
        background-color: var(--bg-card) !important;
        border: 1px solid var(--border-color) !important;
    }

    .rate-dropdown-overlay .rate-highlight-price,
    .rate-option-toggle {
        color: var(--yellow-light) !important;
    }

    /* Offcanvas Sidebar Responsive Blueprint */
    @media (max-width: 991.98px) {
        .mobile-sidebar-container {
            position: fixed;
            top: 0;
            left: -280px !important;
            width: 280px;
            height: 100vh;
            z-index: 1060;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.35);
            /* Sidebar keeps its own dark brand look regardless of light/dark theme */
            background: #0a0a0a;
            overflow-y: auto !important;
            display: block !important;
        }

        .mobile-sidebar-container.show {
            left: 0 !important;
        }

        .sidebar-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.5);
            z-index: 1050;
            display: none;
            opacity: 0;
            transition: opacity 0.25s linear;
        }
        
        .sidebar-backdrop.show {
            display: block;
            opacity: 1;
        }
    }

    /* Mobile Responsive UI Overrides for Terms/Agreement Modal */
    @media (max-width: 576px) {
        #termsModal .modal-body {
            padding: 1rem !important;
        }
        
        #termsModal .agreement-text-box {
            padding: 1rem !important;
            height: 300px !important;
            font-size: 0.8rem !important;
        }
        
        #termsModal .modal-footer {
            display: flex;
            flex-direction: column-reverse;
            gap: 0.5rem;
            padding: 1rem !important;
        }
        
        #termsModal .modal-footer button {
            width: 100% !important;
            margin: 0 !important;
            padding: 0.75rem 1rem !important;
        }
        
        #termsModal h3 {
            font-size: 1.1rem !important;
        }
        
        #termsModal .form-check {
            padding: 0.75rem !important;
            padding-left: 2rem !important;
        }
    }

    /* ========================================================
       DARK MODE COMPLETE OVERRIDES, FORM INPUTS & CONTRAST FIXES
       ======================================================== */
    body.dark-mode,
    body.dark-mode .main-content {
        background-color: #0a0a0a !important;
        color: #f1f5f9 !important;
    }

    /* Form Inputs & File Uploads in Dark Mode (Removes White Inputs) */
    body.dark-mode input[type="text"],
    body.dark-mode input[type="datetime-local"],
    body.dark-mode input[type="date"],
    body.dark-mode input[type="file"],
    body.dark-mode select,
    body.dark-mode .form-control {
        background-color: #1e1e1e !important;
        color: #f1f5f9 !important;
        border: 1px solid #27272a !important;
    }

    body.dark-mode input[type="text"]:focus,
    body.dark-mode input[type="datetime-local"]:focus,
    body.dark-mode input[type="date"]:focus,
    body.dark-mode select:focus,
    body.dark-mode .form-control:focus {
        border-color: var(--yellow-primary) !important;
        box-shadow: 0 0 0 2px var(--yellow-dim) !important;
    }

    body.dark-mode input[type="file"]::file-selector-button {
        background-color: #2a2a2a !important;
        color: #f1f5f9 !important;
        border: 1px solid #3f3f46 !important;
        padding: 4px 10px;
        margin-right: 10px;
        cursor: pointer;
    }

    body.dark-mode input[type="file"]::file-selector-button:hover {
        background-color: var(--yellow-primary) !important;
        color: #000000 !important;
    }

    /* Header & Footer Layout Wrappers */
    body.dark-mode header,
    body.dark-mode navbar,
    body.dark-mode .navbar,
    body.dark-mode footer,
    body.dark-mode .footer {
        background-color: #141414 !important;
        border-color: #27272a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode footer p,
    body.dark-mode header span,
    body.dark-mode header p {
        color: #a1a1aa !important;
    }

    /* Mobile Sidebar Drawer stays dark in both themes (matches sidebar's fixed brand look) */
    .mobile-sidebar-container,
    body.dark-mode .mobile-sidebar-container {
        background-color: #0a0a0a !important;
        border-right: 1px solid #27272a !important;
    }

    /* Typography & High Contrast Fixes */
    body.dark-mode .text-dark,
    body.dark-mode h2,
    body.dark-mode h3,
    body.dark-mode h4,
    body.dark-mode h5,
    body.dark-mode h6,
    body.dark-mode label {
        color: #ffffff !important;
    }

    body.dark-mode .text-muted:not(.sidebar *):not(header *):not(.navbar *),
    body.dark-mode .text-secondary:not(.sidebar *):not(header *):not(.navbar *),
    body.dark-mode span:not(.sidebar *):not(header *):not(.navbar *):not(.badge):not(.text-success):not(.text-primary):not(.text-warning) {
        color: #cbd5e1 !important;
    }

    body.dark-mode .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%) !important;
    }

    input[type="file"]::file-selector-button {
        padding: 5px !important;
        margin: 1px 5px !important;
        border: 1px solid #333333 !important;
        border-radius: 5px !important;
        background-color: #333333 !important;
        color: #ffffff !important;
        font-weight: 300 !important;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    input[type="file"]::file-selector-button:hover {
        background-color: #333333 !important;
        border-color: #eab308 !important;
    }

    body.dark-mode header small,
    body.dark-mode .navbar small,
    body.dark-mode .text-muted {
        color: #cbd5e1 !important; /* Bright silver/gray */
        opacity: 1 !important;
    }

    body.dark-mode .text-primary {
        color: var(--yellow-light) !important;
    }

    body.dark-mode .card {
        background-color: #141414 !important;
        border: 1px solid #27272a !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4) !important;
    }

    body.dark-mode .hover-card:hover,
    body.dark-mode .hover-card.selected {
        border-color: var(--yellow-primary) !important;
        box-shadow: 0 0 18px rgba(234, 179, 8, 0.35) !important;
    }

    /* Status Badge Overrides */
    body.dark-mode .badge.bg-primary {
        background-color: var(--yellow-dim) !important;
        color: var(--yellow-light) !important;
        border: 1px solid var(--yellow-primary) !important;
    }

    body.dark-mode .badge.bg-success {
        background-color: #141414 !important;
        color: #4ade80 !important;
        border: 1px solid #22c55e !important;
    }
    
    body.dark-mode .badge.bg-danger {
        background-color: #991b1b !important;
        color: #ffffff !important;
    }
    body.dark-mode .badge.bg-warning {
        background-color: var(--yellow-hover) !important;
        color: #ffffff !important;
    }
    body.dark-mode .badge.bg-secondary {
        background-color: #3f3f46 !important;
        color: #f1f5f9 !important;
    }

    /* Custom styling for the availability warning alert */
    #availabilityWarning {
        background-color: #fff3cd !important;
        border-color: #ffe69c !important;
        color: #664d03 !important;
    }

    #availabilityWarning * {
        color: #664d03 !important;
    }

    /* Dark Mode Override for Availability Warning Alert */
    body.dark-mode #availabilityWarning {
        background-color: rgba(234, 179, 8, 0.15) !important;
        border: 1px solid #eab308 !important;
        color: #fef08a !important;
    }

    body.dark-mode #availabilityWarning * {
        color: #fef08a !important;
    }

    /* Helper Utilities */
    .extra-small {
        font-size: 11px;
    }

    .cursor-pointer {
        cursor: pointer;
    }

    /* Delivery Address Placeholder Styling */
    #deliveryAddress::placeholder {
        color: #94a3b8 !important;
        opacity: 1 !important;
    }

    body.dark-mode #deliveryAddress::placeholder,
    [data-bs-theme="dark"] #deliveryAddress::placeholder {
        color: #a1a1aa !important;
        opacity: 1 !important;
    }

    /* Base Card Styling - Light Mode */
    .fulfillment-card {
        background-color: #ffffff;
        border-color: #dee2e6 !important;
        transition: all 0.2s ease-in-out;
    }

    .fulfillment-card .card-title-text {
        color: #212529 !important;
    }

    .fulfillment-card .card-subtitle-text {
        color: #6c757d !important;
    }

    .fulfillment-card .icon-box {
        width: 34px;
        height: 34px;
        background-color: #fef08a;
        color: #854d0e;
    }

    .fulfillment-card:hover {
        border-color: #eab308 !important;
    }

    /* Selected State - Light Mode (Yellow Focus) */
    .btn-check:checked + .fulfillment-card {
        background-color: #fefce8;
        border-color: #eab308 !important;
        box-shadow: 0 0 0 1px #eab308;
    }

    .btn-check:checked + .fulfillment-card .icon-box {
        background-color: #eab308;
        color: #000000;
    }

    /* Dark Mode Overrides (Explicit High Contrast) */
    body.dark-mode .fulfillment-card,
    [data-bs-theme="dark"] .fulfillment-card {
        background-color: #1e1e1e;
        border-color: #333333 !important;
    }

    body.dark-mode .fulfillment-card .card-title-text,
    [data-bs-theme="dark"] .fulfillment-card .card-title-text {
        color: #ffffff !important;
    }

    body.dark-mode .fulfillment-card .card-subtitle-text,
    [data-bs-theme="dark"] .fulfillment-card .card-subtitle-text {
        color: #a1a1aa !important;
    }

    body.dark-mode .fulfillment-card .icon-box,
    [data-bs-theme="dark"] .fulfillment-card .icon-box {
        background-color: rgba(234, 179, 8, 0.2);
        color: #fef08a;
    }

    /* Selected State - Dark Mode */
    body.dark-mode .btn-check:checked + .fulfillment-card,
    [data-bs-theme="dark"] .btn-check:checked + .fulfillment-card {
        background-color: rgba(234, 179, 8, 0.12);
        border-color: #eab308 !important;
        box-shadow: 0 0 0 1px #eab308;
    }

    body.dark-mode .btn-check:checked + .fulfillment-card .icon-box,
    [data-bs-theme="dark"] .btn-check:checked + .fulfillment-card .icon-box {
        background-color: #eab308;
        color: #000000;
    }

     .pickup-address-card {
        background: #f8fafc;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
    }
    
    .pickup-address-card:hover {
        background: #f1f5f9;
        border-color: #ffcc00;
    }
    
    .address-icon {
        background: #fef9e7 !important;
    }
    
    .address-icon i {
        color: #b38a00 !important;
    }
    
    .pickup-address-card a:hover {
        color: #b38a00 !important;
        text-decoration: underline !important;
    }
    
    /* Dark mode */
    body.dark-mode .pickup-address-card {
        background: #1a1a1a;
        border-color: #27272a;
    }
    
    body.dark-mode .pickup-address-card:hover {
        background: #222222;
        border-color: #ffcc00;
    }
    
    body.dark-mode .pickup-address-card .text-dark,
    body.dark-mode .pickup-address-card .fw-bold {
        color: #f1f5f9 !important;
    }
    
    body.dark-mode .address-icon {
        background: rgba(255, 204, 0, 0.15) !important;
    }
    
    body.dark-mode .address-icon i {
        color: #ffcc00 !important;
    }

</style>

<div id="sidebarBackdrop" class="sidebar-backdrop"></div>

<div class="container-fluid p-0">
    <div class="row g-0">
        <div class="col-lg-2 p-0 d-none d-lg-block mobile-sidebar-container" id="sidebarWrapper">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>
        
        <div class="col-12 col-lg-10 p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>
            
            <div class="p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-0">Vehicle <span style="color: #ffcc00 !important;">Gallery</span></h3>
                        <p class="text-muted mb-0">Browse our fleet and check availability.</p>
                    </div>
                </div>
                
                <div class="filter-section">
                    <div class="row g-3 align-items-end">
                        <div class="col-sm-6 col-md-4">
                            <div class="filter-label">
                                <i class="bi bi-car-front"></i> Filter by Type
                            </div>
                            <select class="filter-select w-100" id="typeFilter" onchange="applyFilters()">
                                <option value="All" <?= $type_filter == 'All' ? 'selected' : '' ?>>All Types</option>
                                <?php foreach ($car_types as $type): ?>
                                    <option value="<?= htmlspecialchars($type) ?>" <?= $type_filter == $type ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-sm-6 col-md-4">
                            <div class="filter-label">
                                <i class="bi bi-tag"></i> Filter by Status
                            </div>
                            <select class="filter-select w-100" id="statusFilter" onchange="applyFilters()">
                                <option value="All" <?= $status_filter == 'All' ? 'selected' : '' ?>>All Status</option>
                                <option value="Available" <?= $status_filter == 'Available' ? 'selected' : '' ?>>Available</option>
                                <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Rented/Active</option>
                            </select>
                        </div>
                        
                        <div class="col-12 col-md-4">
                            <button class="btn btn-outline-secondary reset-filter w-100" onclick="resetFilters()">
                                <i class="bi bi-x-circle"></i> Reset All Filters
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($type_filter !== 'All' || $status_filter !== 'All'): ?>
                        <div class="active-filters">
                            <span class="small text-muted me-2">Active filters:</span>
                            <?php if ($type_filter !== 'All'): ?>
                                <span class="filter-badge">
                                    Type: <?= htmlspecialchars($type_filter) ?>
                                    <a href="?type=All&status=<?= $status_filter ?>">×</a>
                                </span>
                            <?php endif; ?>
                            <?php if ($status_filter !== 'All'): ?>
                                <span class="filter-badge">
                                    Status: <?= $status_filter == 'Available' ? 'Available' : 'Rented/Active' ?>
                                    <a href="?type=<?= $type_filter ?>&status=All">×</a>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="results-count">
                        <i class="bi bi-car-front"></i> Showing <?= count($cars) ?> vehicle<?= count($cars) != 1 ? 's' : '' ?>
                        <?php if ($type_filter !== 'All' || $status_filter !== 'All'): ?>
                            <?php if ($type_filter !== 'All' && $status_filter !== 'All'): ?>
                                of type "<?= htmlspecialchars($type_filter) ?>" with status "<?= $status_filter == 'Available' ? 'Available' : 'Rented/Active' ?>"
                            <?php elseif ($type_filter !== 'All'): ?>
                                of type "<?= htmlspecialchars($type_filter) ?>"
                            <?php elseif ($status_filter !== 'All'): ?>
                                with status "<?= $status_filter == 'Available' ? 'Available' : 'Rented/Active' ?>"
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row g-3 g-md-4" id="carContainer">
                    <?php if (empty($cars)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="bi bi-car-front text-muted" style="font-size: 4rem;"></i>
                            <h5 class="mt-3 text-muted">No vehicles found</h5>
                            <p class="text-muted small">
                                <?php if ($type_filter !== 'All' || $status_filter !== 'All'): ?>
                                    No vehicles match your filters. Try adjusting your criteria.
                                <?php else: ?>
                                    Please check back later for available cars.
                                <?php endif; ?>
                            </p>
                            <?php if ($type_filter !== 'All' || $status_filter !== 'All'): ?>
                                <button onclick="resetFilters()" class="btn btn-primary mt-2">
                                    <i class="bi bi-arrow-repeat"></i> Reset Filters
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($cars as $car): ?>
                            <div class="col-sm-6 col-md-6 col-lg-4 col-xl-3 car-item position-relative" style="z-index: 1;">
                                <div class="card h-100 border-0 shadow-sm rounded-4 hover-card transition-all" data-car-id="<?= $car['id'] ?>">
                                    
                                    <!-- Image Header Section -->
                                    <div class="position-relative rounded-top-4 overflow-hidden" style="height: 200px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                        <?php 
                                            $car_images = !empty($car['image_path']) ? array_filter(array_map('trim', explode(',', $car['image_path']))) : [];
                                            if (!empty($car_images)): 
                                                $primary_image = reset($car_images);
                                                $image_src = "../../public/assets/images/cars/" . $primary_image;
                                        ?>
                                            <img src="<?= htmlspecialchars($image_src) ?>" 
                                                class="w-100 h-100 position-relative" 
                                                style="object-fit: cover; cursor: zoom-in;" 
                                                alt="Car" 
                                                title="Click to view full stash gallery"
                                                onclick="openStashGalleryModal(<?= htmlspecialchars(json_encode(array_values($car_images)), ENT_QUOTES, 'UTF-8') ?>)">
                                        <?php else: ?>
                                            <div class="h-100 d-flex flex-column align-items-center justify-content-center text-white">
                                                <i class="bi bi-car-front-fill" style="font-size: 3.5rem; opacity: 0.5;"></i>
                                                <span class="small fw-bold mt-2">NO IMAGE</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="position-absolute top-0 end-0 m-3">
                                            <div class="theme-badge rounded-3 px-3 py-1 shadow-sm">
                                                <span class="fw-bold text-primary">₱<?= number_format($car['price_24_hours']) ?></span>
                                                <small class="text-muted">/24h</small>
                                            </div>
                                        </div>
                                        
                                        <div class="position-absolute bottom-0 start-0 m-3">
                                            <span class="badge bg-dark bg-opacity-75 px-3 py-2 rounded-pill">
                                                <i class="bi bi-tag me-1"></i>
                                                <?= htmlspecialchars($car['type']) ?>
                                            </span>
                                        </div>
                                        
                                    </div>

                                    <!-- Card Content Body -->
                                    <div class="card-body p-3 p-md-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="mb-3">
                                                <h5 class="fw-bold mb-1 text-truncate"><?= htmlspecialchars($car['brand']) ?> <?= htmlspecialchars($car['model']) ?></h5>
                                                <div class="d-flex flex-wrap gap-1 mt-2">
                                                    <span class="badge theme-badge rounded-pill px-2 py-1 small">
                                                        <i class="bi bi-gear-fill me-1 text-primary"></i><?= htmlspecialchars($car['transmission']) ?>
                                                    </span>
                                                    <span class="badge theme-badge rounded-pill px-2 py-1 small">
                                                        <i class="bi bi-people-fill me-1 text-primary"></i><?= htmlspecialchars($car['capacity']) ?> Seats
                                                    </span>
                                                    <?php if(!empty($car['color'])): ?>
                                                    <span class="badge theme-badge rounded-pill px-2 py-1 small">
                                                        <i class="bi bi-palette-fill me-1 text-primary"></i><?= htmlspecialchars($car['color']) ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between small text-muted">
                                                    <span><i class="bi bi-fuel-pump"></i> <?= ucfirst($car['fuel_type'] ?? 'Gasoline') ?></span>
                                                    <span><i class="bi bi-file-text"></i> <?= htmlspecialchars($car['plate_number']) ?></span>
                                                </div>
                                            </div>

                                            <!-- Rates Dropdown -->
                                            <div class="dropdown mb-3">
                                                <button class="btn theme-dropdown-btn border rounded-3 w-100 d-flex justify-content-between align-items-center p-2" 
                                                        type="button" 
                                                        id="rateDropdown<?= $car['id'] ?>" 
                                                        data-bs-toggle="dropdown" 
                                                        data-bs-auto-close="true"
                                                        aria-expanded="false">
                                                    <small class="fw-bold"><i class="bi bi-cash-coin me-1 text-success"></i> View Rate Options</small>
                                                    <i class="bi bi-chevron-down small text-muted"></i>
                                                </button>
                                                
                                                <div class="dropdown-menu w-100 shadow-lg border-0 rounded-3 p-3 rate-dropdown-overlay" 
                                                     aria-labelledby="rateDropdown<?= $car['id'] ?>" 
                                                     data-parent-card="<?= $car['id'] ?>" 
                                                     style="min-width: 100%;">
                                                    <div style="font-size: 12px;">
                                                        <div class="fw-bold text-secondary border-bottom pb-1 mb-2">Base Multi-Hour Pricing:</div>
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span>10-Hour Duration Rate:</span>
                                                            <span class="fw-bold">₱<?= number_format($car['price_10_hours']) ?></span>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span>12-Hour Duration Rate:</span>
                                                            <span class="fw-bold">₱<?= number_format($car['price_12_hours']) ?></span>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-3">
                                                            <span>24-Hour Base Rate:</span>
                                                            <span class="fw-bold text-primary">₱<?= number_format($car['price_24_hours']) ?></span>
                                                        </div>

                                                        <div class="fw-bold text-secondary border-bottom pb-1 mb-2">Hourly Extension Rates:</div>
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span>Hours 1 to 6 Excess:</span>
                                                            <span class="fw-bold">₱<?= number_format($car['ext_price_1_6']) ?>/hr</span>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span>Hours 7 to 10 Excess:</span>
                                                            <span class="fw-bold">₱<?= number_format($car['ext_price_7_10']) ?>/hr</span>
                                                        </div>
                                                        <div class="d-flex justify-content-between mb-1">
                                                            <span>Hours 11 to 12 Excess:</span>
                                                            <span class="fw-bold">₱<?= number_format($car['ext_price_11_12']) ?>/hr</span>
                                                        </div>
                                                        <div class="d-flex justify-content-between">
                                                            <span>Hours 13 to 24 Excess:</span>
                                                            <span class="fw-bold">₱<?= number_format($car['ext_price_13_24']) ?>/hr</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action Button -->
                                        <button class="btn btn-primary w-100 fw-bold py-2 rounded-3 shadow-sm mt-auto" 
                                                onclick="openBookingModal(<?= htmlspecialchars(json_encode($car), ENT_QUOTES, 'UTF-8') ?>)">
                                            <i class="bi bi-calendar-check me-2"></i>Book Now
                                        </button>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-auto">
                <?php require_once __DIR__ . '/../components/footer.php'; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="stashGalleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border-0 shadow-lg text-white">
            <div class="modal-header border-0 pb-0 bg-black">
                <h6 class="modal-title fw-bold text-white">
                    <i class="bi bi-images me-2 text-warning"></i>Vehicle Stash Gallery
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-2 p-md-4 bg-black">
                <div id="stashGalleryCarousel" class="carousel slide" data-bs-ride="false">
                    <div class="carousel-inner rounded-3" id="stashCarouselItemsContainer" style="max-height: 500px; background: #000;"></div>
                    
                    <button class="carousel-control-prev" type="button" data-bs-target="#stashGalleryCarousel" data-bs-slide="prev" id="stashCarouselPrevBtn">
                        <span class="carousel-control-prev-icon shadow-sm rounded-circle p-3 bg-dark bg-opacity-50" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#stashGalleryCarousel" data-bs-slide="next" id="stashCarouselNextBtn">
                        <span class="carousel-control-next-icon shadow-sm rounded-circle p-3 bg-dark bg-opacity-50" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>

                <div class="d-flex gap-2 justify-content-start justify-content-md-center mt-3 overflow-x-auto py-1 w-100" id="stashThumbsContainer" style="-webkit-overflow-scrolling: touch; white-space: nowrap;"></div>
            </div>
        </div>
    </div>
</div>

<script>
function applyFilters() {
    var type = document.getElementById('typeFilter').value;
    var status = document.getElementById('statusFilter').value;
    window.location.href = '?type=' + encodeURIComponent(type) + '&status=' + encodeURIComponent(status);
}

function resetFilters() {
    window.location.href = '?type=All&status=All';
}
</script>

<div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="fw-bold">Book <span id="modalCarName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process/booking_process.php" method="POST" enctype="multipart/form-data" id="bookingForm">
                <div class="modal-body p-3 p-md-4">
                    <!-- Hidden Car Data Fields -->
                    <input type="hidden" name="car_id" id="modalCarId">
                    <input type="hidden" name="price_10_hours" id="modalCarPrice10" value="0">
                    <input type="hidden" name="price_12_hours" id="modalCarPrice12" value="0">
                    <input type="hidden" name="price_24_hours" id="modalCarPrice24" value="0">
                    <input type="hidden" name="ext_price_1_6" id="modalExtPrice1_6" value="0">
                    <input type="hidden" name="ext_price_7_10" id="modalExtPrice7_10" value="0">
                    <input type="hidden" name="ext_price_11_12" id="modalExtPrice11_12" value="0">
                    <input type="hidden" name="ext_price_13_24" id="modalExtPrice13_24" value="0">

                    <!-- Form Formats Expected by Backend -->
                    <input type="hidden" name="start_date" id="startDate">
                    <input type="hidden" name="pickup_time" id="pickupTime">
                    <input type="hidden" name="end_date" id="endDate">
                    <input type="hidden" name="return_time" id="returnTime">
                    <input type="hidden" name="total_price" id="totalPriceInput" value="0">

                    <!-- AVAILABILITY CALENDAR SECTION -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label fw-bold mb-0">
                                <i class="bi bi-calendar-check me-1 text-primary"></i> Availability Calendar
                            </label>
                            <div>
                                <span class="badge bg-success me-1">Available</span>
                                <span class="badge bg-danger me-1">Booked</span>
                                <span class="badge bg-primary">Selected</span>
                            </div>
                        </div>
                        <div class="table-responsive border rounded p-2 theme-bg-alt">
                            <div id="availabilityCalendar" style="min-width: 280px;">
                                <div class="text-center text-muted py-3">
                                    <div class="spinner-border spinner-border-sm" role="status"></div>
                                    Loading calendar...
                                </div>
                            </div>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            <i class="bi bi-info-circle"></i> Grayed/Red dates are already booked. Please select available dates.
                        </small>
                    </div>

                    <hr class="text-muted opacity-25">

                    <!-- QUICK DURATION PRESETS -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Quick Duration</label>
                        <div class="duration-buttons d-flex gap-2 flex-wrap">
                            <button type="button" class="duration-btn btn btn-outline-primary btn-sm flex-fill" id="btn10h" onclick="setPublicDuration(10, this)">10 Hours</button>
                            <button type="button" class="duration-btn btn btn-outline-primary btn-sm flex-fill" id="btn24h" onclick="setPublicDuration(24, this)">24 Hours</button>
                            <button type="button" class="duration-btn btn btn-outline-primary btn-sm flex-fill active" id="btnCustom" onclick="setPublicDuration('custom', this)">Custom</button>
                        </div>
                    </div>

                    <!-- SCHEDULE DATETIME PICKERS -->
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Pickup Date & Time</label>
                            <input type="datetime-local" id="pickupDatetime" class="form-control" required onchange="syncPublicDateTimeValues()">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-bold">Return Date & Time</label>
                            <input type="datetime-local" id="returnDatetime" class="form-control" required onchange="syncPublicDateTimeValues()">
                        </div>
                    </div>

                    <!-- DYNAMIC SCHEDULE SUMMARY & PREVIEW -->
                    <div class="col-12 date-time-preview mb-3 p-3 theme-bg-alt rounded border-start border-4 border-primary" id="dateTimePreview" style="display: none;">
                        <div class="mb-1 small text-muted">
                            <i class="bi bi-calendar-plus me-1 text-primary"></i>
                            <strong>Pickup:</strong> <span id="previewPickup">--</span>
                        </div>
                        <div class="small text-muted">
                            <i class="bi bi-calendar-check me-1 text-success"></i>
                            <strong>Return:</strong> <span id="previewReturn">--</span>
                        </div>
                    </div>

                    <div id="availabilityWarning" class="alert alert-warning d-none d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2 flex-shrink-0"></i>
                        <span id="warningMessage" class="fw-medium"></span>
                    </div>

                    <hr class="text-muted opacity-25">

                    <!-- PICKUP OR DELIVERY OPTIONS -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold mb-2">Fulfillment Type</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="fulfillment_type" id="fulfillmentPickup" value="pickup" checked onchange="toggleFulfillmentDetails()">
                                <label class="fulfillment-card h-100 w-100 p-2.5 rounded-3 border d-flex align-items-center gap-2 cursor-pointer" for="fulfillmentPickup">
                                    <div class="icon-box rounded-2 d-flex align-items-center justify-content-center flex-shrink-0">
                                        <i class="bi bi-building fs-6"></i>
                                    </div>
                                    <div class="lh-sm">
                                        <div class="card-title-text fw-bold small">Self Pickup</div>
                                        <div class="card-subtitle-text extra-small">Pickup at office</div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="fulfillment_type" id="fulfillmentDelivery" value="delivery" onchange="toggleFulfillmentDetails()">
                                <label class="fulfillment-card h-100 w-100 p-2.5 rounded-3 border d-flex align-items-center gap-2 cursor-pointer" for="fulfillmentDelivery">
                                    <div class="icon-box rounded-2 d-flex align-items-center justify-content-center flex-shrink-0">
                                        <i class="bi bi-truck fs-6"></i>
                                    </div>
                                    <div class="lh-sm">
                                        <div class="card-title-text fw-bold small">Car Delivery</div>
                                        <div class="card-subtitle-text extra-small">Deliver to location</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Self Pickup Info Box -->
                        <div id="pickupAddressWrapper" class="mt-3 p-3 rounded-3 border theme-bg-alt border-warning-subtle d-flex align-items-start gap-3">
                            <i class="bi bi-geo-alt-fill text-warning fs-5 flex-shrink-0 mt-1"></i>
                            <div>
                                <div class="fw-bold extra-small text-uppercase tracking-wider opacity-75 mb-1">Pickup Address</div>
                                <div class="pickup-address-card p-2 p-sm-3 mb-3">
                                    <div class="d-flex align-items-center gap-2 gap-sm-3">
                                        <div class="address-icon bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                                            <i class="bi bi-geo-alt-fill text-primary" style="font-size: 1.2rem;"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="text-muted text-uppercase fw-semibold" style="font-size: 0.6rem; letter-spacing: 0.8px;">
                                                <i class="bi bi-pin-map me-1"></i> PICKUP LOCATION
                                            </div>
                                            <div class="d-flex align-items-center flex-wrap gap-1 gap-sm-2">
                                                <span class="fw-bold" style="font-size: 0.9rem;">Pandac, Pavia, 5001 Iloilo</span>
                                                <a href="https://www.google.com/maps/place/Instacar+Car+Rental+Services/@10.7495626,122.5198576,17z/data=!3m1!4b1!4m6!3m5!1s0x33aefb4f8be44f49:0x930b22a3c6978b93!8m2!3d10.7495626!4d122.5198576!16s%2Fg%2F11vq394xy6?entry=ttu&g_ep=EgoyMDI2MDkwMi4wIKXMDSoASAFQAw%3D%3D" 
                                                target="_blank" 
                                                rel="noopener noreferrer"
                                                class="text-decoration-none text-primary fw-semibold d-inline-flex align-items-center gap-1" 
                                                style="font-size: 0.75rem;">
                                                    <i class="bi bi-box-arrow-up-right"></i> Map
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden Delivery Address Field -->
                        <div id="deliveryAddressWrapper" class="mt-3" style="display: none !important;">
                            <label for="deliveryAddress" class="form-label small fw-bold text-muted mb-1">Delivery Address</label>
                            <textarea name="delivery_address" id="deliveryAddress" class="form-control form-control-sm p-2.5 custom-placeholder" rows="2" placeholder="Enter complete delivery location / landmark..."></textarea>
                        </div>
                    </div>

                    <!-- ID & PROOF OF BILLING UPLOADS -->
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-danger">
                                <i class="bi bi-card-heading me-1"></i>Primary ID (e.g. Driver's License)
                            </label>
                            <input type="file" name="primary_id" class="form-control form-control-sm" accept="image/*,.pdf" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted">Secondary ID (Optional)</label>
                            <input type="file" name="secondary_id" class="form-control form-control-sm" accept="image/*,.pdf">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted">Proof of Billing</label>
                            <input type="file" name="proof_of_billing" class="form-control form-control-sm" accept="image/*,.pdf">
                        </div>
                    </div>

                    <!-- PROOF OF PAYMENT SECTION -->
                    <div class="col-12 mb-3">
                        <label for="proofOfPaymentInput" class="form-label small fw-bold text-muted mb-1">Proof of Payment</label>
                        
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1">
                                <input type="file" name="proof_of_payment" id="proofOfPaymentInput" class="form-control form-control-sm" accept="image/*,.pdf" required>
                            </div>

                            <div class="border rounded-3 p-1 pe-2 theme-bg-alt d-inline-flex align-items-center gap-2 shadow-sm flex-shrink-0" 
                                style="cursor: pointer; height: 38px; transition: all 0.2s ease-in-out;" 
                                data-bs-toggle="modal" 
                                data-bs-target="#qrCodeModal" 
                                title="Click to view & scan Payment QR Code">
                                
                                <div class="rounded overflow-hidden bg-light d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                    <img src="<?= $qr_image_src ?>" 
                                        id="formQrCodeThumbnail" 
                                        alt="Payment QR Code" 
                                        class="w-100 h-100" 
                                        style="object-fit: cover;">
                                </div>

                                <div class="text-start pe-1">
                                    <div class="text-primary fw-bold lh-1" style="font-size: 11px;">
                                        <i class="bi bi-qr-code-scan me-1"></i>Scan QR
                                    </div>
                                    <span class="text-muted d-block" style="font-size: 9px; line-height: 1;">Tap to open</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-text text-muted mt-1" style="font-size: 11.5px;">
                            Scan QR code to pay down payment / full amount, then attach receipt photo.
                        </div>
                    </div>

                    <!-- QR CODE ENLARGEMENT MODAL -->
                    <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true" style="z-index: 1060;">
                        <div class="modal-dialog modal-dialog-centered" style="max-width: 360px;">
                            <div class="modal-content border-0 shadow-lg text-center overflow-hidden">
                                <div class="modal-header bg-primary text-white py-2 px-3 border-0">
                                    <h6 class="modal-title fw-bold mb-0" id="qrCodeModalLabel">
                                        <i class="bi bi-qr-code me-1"></i> Payment QR Code
                                    </h6>
                                </div>
                                <div class="modal-body p-3 p-sm-4 theme-bg-alt">
                                    <p class="small text-muted mb-3" style="font-size: 12px; line-height: 1.4;">
                                        Scan this QR Code using GCash / Maya / Banking app to send your payment.
                                    </p>
                                    
                                    <div class="bg-white p-2 p-sm-3 rounded shadow-sm d-inline-block border mb-3 mw-100">
                                        <img src="<?= $qr_image_src ?>" id="modalEnlargedQr" alt="Enlarged Payment QR Code" class="img-fluid" style="max-height: 240px; width: auto; object-fit: contain;">
                                    </div>
                                    
                                    <div class="w-100">
                                        <div class="alert alert-success border-success-subtle py-2 px-2 m-0 text-wrap fw-bold" style="font-size: 11px; line-height: 1.3;">
                                            💡 Take a screenshot of payment receipt after scanning
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer border-0 p-2 bg-transparent justify-content-center">
                                    <button type="button" 
                                            class="btn btn-sm btn-secondary fw-semibold px-4" 
                                            onclick="bootstrap.Modal.getInstance(this.closest('.modal')).hide();">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PRICING BREAKDOWN DISPLAY -->
                    <div class="p-3 theme-bg-alt rounded border">
                        <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
                            <span>Base Rental Rate:</span>
                            <span id="userDisplayBasePrice">₱0.00</span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
                            <span>Total Duration:</span>
                            <span class="fw-semibold" id="userDurationDisplay">0 Hours</span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-1 small text-danger d-none" id="userDiscountRow">
                            <span>Discount Deduction:</span>
                            <span id="userDisplayDiscount">-₱0.00</span>
                        </div>

                        <hr class="my-2">

                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small fw-bold">Total Due:</span>
                            <h4 class="fw-bold text-primary mb-0" id="userDisplayTotal">₱0.00</h4>
                        </div>
                    </div>

                    <div class="mt-3 small text-muted">
                        <div><i class="bi bi-info-circle me-1"></i> Additional fees apply for delivery and pickup outside our office.</div>
                        <div><i class="bi bi-brush me-1"></i> Carwash fee may apply depending on vehicle condition upon return.</div>
                    </div>
                </div>

                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="confirm_booking" class="btn btn-primary w-100 py-2 fw-bold shadow-sm" id="confirmBtn">Confirm Reservation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="termsModal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="termsLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold" id="termsLabel"><i class="bi bi-shield-check me-2"></i>CAR RENTAL AGREEMENT</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-5">
                <div class="text-center mb-4">
                    <h3 class="fw-bold mb-0 fs-4 fs-md-3">INSTACAR CAR RENTAL SERVICES</h3>
                    <p class="text-muted small">Jibao-an, Pavia, Iloilo | +639175540394 / +639461901465</p>
                    <div class="d-flex justify-content-center">
                        <hr class="w-25 border-primary border-2 mt-1">
                    </div>
                </div>

                <div class="agreement-text-box p-3 p-md-4 border rounded shadow-sm">
                    <p class="text-center fw-bold text-uppercase mb-4">Car Rental Agreement</p>
                    
                    <p>This Car Rental Agreement (the "Agreement") is entered into between:<br>
                    <strong>RENTER:</strong> Hereinafter referred to as RENTER;<br>
                    -and- <strong>OWNER:</strong> Instacar Car Rental Services, hereinafter referred to as INSTACAR;<br>
                    Collectively referred to as the "Parties."</p>

                    <h6 class="fw-bold mt-4 text-primary">I. Vehicle Rental</h6>
                    <p>INSTACAR agrees to rent to RENTER a vehicle identified under the details provided in Annex A.</p>

                    <h6 class="fw-bold mt-3 text-primary">II. Term of Agreement</h6>
                    <p>The term of this Car Rental Agreement runs from the date and hour of vehicle pickup as indicated in Annex A until the return of the vehicle to INSTACAR and completion of all terms of this Agreement. The Parties may shorten or extend the estimated rental term by mutual consent.</p>

                    <h6 class="fw-bold mt-3 text-primary">III. Compliance with Terms and Conditions</h6>
                    <p>RENTER complies and agrees with the terms and conditions as stated below.</p>

                    <h6 class="fw-bold mt-3 text-primary">IV. Licensure and Legal Compliance</h6>
                    <p>RENTER will comply with all applicable laws relating to holding of licensure to operate the vehicle, and pertaining to operation of motor vehicles including but not limited to LTO and other relevant traffic regulations.</p>

                    <h6 class="fw-bold mt-3 text-danger">V. Restrictions on Use</h6>
                    <p>RENTER should not operate the vehicle in the following cases: In motor sports events, in illegal transactions or activities, carrying persons or anything for hire, parking the vehicle in unsecured places, towing or pushing anything, transporting or getting onboard any kind of pet or animal, smoking inside the vehicle, carrying anything of weight in excess of the vehicle’s maximum capacity, passing on roads that are not passable or not safe for the vehicle, unlawful, improper, or offensive use of vehicle equipment/tools/parts, the RENTER shall not assign nor transfer his right to use the vehicle to any third person without prior written consent from INSTACAR, nor mortgage or sell the said vehicle to any third person, otherwise, INSTACAR shall file appropriate action against the lessee.</p>

                    <h6 class="fw-bold mt-3 text-danger">VI. Coverage Area</h6>
                    <p>RENTER shall only use the vehicle within Panay Island unless written consent is provided by INSTACAR. If the vehicle is taken outside Panay Island or transported by any watercraft without consent, a fine of <strong>PHP 100,000</strong> shall be imposed. Additionally, INSTACAR reserves the right to report the violation to the appropriate authorities, including the Highway Patrol Group (HPG), for the immediate apprehension of the unit.</p>

                    <h6 class="fw-bold mt-3 text-primary">VII. Authorized Operators</h6>
                    <p>RENTER will not allow any other person to operate the Rented Vehicle unless identified in Annex A.</p>

                    <h6 class="fw-bold mt-3 text-primary">VIII. Traffic Violations and Penalties</h6>
                    <p>RENTER shall be responsible for all fines, penalties, and liabilities resulting from any traffic or road violations incurred during the rental period. If RENTER receives a traffic violation or is issued a ticket that imposes a penalty on the rental unit, they must report it to INSTACAR within 24 hours of issuance. Failure to report within the given timeframe will result in the RENTER being charged double the total penalty amount, including any additional fees due to delayed payment caused by the RENTER’S failure to report on time. RENTER must settle the total amount immediately upon notification.</p>

                    <h6 class="fw-bold mt-3 text-primary">IX. Cleaning Fee</h6>
                    <p>The vehicle will be handed over to the RENTER washed and clean, and should be returned also clean with the same cleanliness during handover. Otherwise, RENTER will be charged a PHP 200 washing fee.<br>
                    <strong>Smoking, Carrying of Fresh Fish, Dried Fish, Animals, Foods, Spillage, Vomiting, or Any Items That Cause Odor and Stains:</strong> A cleaning fee of <strong>PHP 2,000</strong> will be charged to RENTER for detailed cleaning services required to address the odor and dirt caused by smoking, carrying fresh fish, dried fish, any animals, food items, or any items that may cause odor and stains on the vehicle, as well as incidents involving spillage or vomiting.</p>

                    <h6 class="fw-bold mt-3 text-primary">X. Key Replacement Charges</h6>
                    <p>If the vehicle key is locked inside the vehicle and RENTER requests a duplicate key, the following charges will apply:<br>
                    - Provincial: P5,000 plus gasoline charges for round-trip delivery.<br>
                    - Iloilo City: P1,000 plus gasoline charges for round-trip delivery.<br>
                    In the event the key is lost, a charge will be applied based on the price of replacing the key from the authorized service center (casa).</p>

                    <h6 class="fw-bold mt-3 text-primary">XI. Fuel</h6>
                    <p>Fuel charges shall be on the sole account of RENTER; RENTER is responsible for returning the vehicle with the same amount of fuel in the tank as when received. Instacar will not refund the extra fuel if the rented vehicle is returned with more than the amount when it was received by the RENTER. The RENTER must use the correct fuel type specified for the vehicle. Any damage resulting from the use of incorrect fuel will be fully charged to the renter.</p>

                    <h6 class="fw-bold mt-3 text-primary">XII. Vehicle Condition</h6>
                    <p>RENTER shall return the vehicle in the same condition it was delivered except for the normal wear and tear.</p>

                    <h6 class="fw-bold mt-3 text-primary">XIII. Retrieval of Vehicle</h6>
                    <p>Instacar reserves the right to retrieve the rented vehicle at any time and from any location if the RENTER fails to fulfill their payment obligations.</p>

                    <h6 class="fw-bold mt-3 text-primary">XV. Failure to Return Vehicle</h6>
                    <p>If RENTER fails to return the vehicle on the due date and time without a permitted extension from Instacar, RENTER will be deemed to be in unlawful possession of the vehicle and to have authorized the issuance of a warrant for the arrest of the RENTER.</p>

                    <h6 class="fw-bold mt-3 text-primary">XVI. Extension of Rental Period</h6>
                    <p>If the RENTER fails to return the vehicle within the stipulated time, the RENTER agrees to pay <strong>PHP 200 per hour (PHP 250 for vans)</strong> for the first 6 hours. In excess of six hours, the RENTER must pay the daily rate for the rented vehicle or van, depending on the type.</p>

                    <h6 class="fw-bold mt-3 text-primary">XVIII. Responsibility for Damages and Repairs</h6>
                    <p>The RENTER will shoulder all expenses for any damage, replacement of missing parts and accessories, and the full daily rental rate of the damaged vehicle until it has been fixed or restored to its original condition.</p>

                    <h6 class="fw-bold mt-3 text-primary">XX. Insurance Coverage</h6>
                    <p>If the Rental Vehicle is damaged or destroyed while in the possession of the Renter, the Renter may choose to use the vehicle’s comprehensive insurance. If the insurance company denies coverage, the Renter will be responsible for covering the full cost of the damage.</p>

                    <h6 class="fw-bold mt-3 text-primary">XXI. Service Fee for Assistance</h6>
                    <p>Within City Limits: PHP 1,000 / Outside City Limits: PHP 5,000 / Fuel Cost: The RENTER shall also cover the fuel expenses for the round-trip travel of the assistance vehicle.</p>

                    <h6 class="fw-bold mt-3 text-primary">XXIII. Payment Terms and Conditions</h6>
                    <p><strong>Rental Payment Terms:</strong><br>
                    1. The renter must pay a non-refundable reservation fee of 5% of the total rental rate to confirm the booking.<br>
                    2. The remaining balance for the entire rental period must be fully paid upon handover of the unit.<br>
                    <strong>Damage Payment Settlement Terms:</strong><br>
                    - Minor Damages (Below PHP 10,000) – Payment due within the day.<br>
                    - Moderate Damages (PHP 10,000 - PHP 50,000) – Payment due within 7 days.<br>
                    - Major Damages (Above PHP 50,000 / Total Wreck) – 50% upfront within 7 days, balance payable within 30 days.</p>

                    <h6 class="fw-bold mt-3 text-primary">XXIV. Cancellation and Refund Policy</h6>
                    <p>Once the reservation is confirmed and the reservation fee is received, there will be no refund in case of cancellation or change of schedule.</p>
                    
                    <br>
                    <p class="small text-muted">Owner/Manager: Grayson Mark S. Del Socorro</p>

                    <div class="form-check mt-4 p-3 theme-bg-alt rounded border">
                        <input class="form-check-input" type="checkbox" id="agreeCheckbox" onchange="toggleProceedBtn()">
                        <label class="form-check-label fw-bold" for="agreeCheckbox">
                            I hereby confirm that I have read, understood, and agreed to ALL the terms and conditions stated above.
                        </label>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Decline</button>
                <button type="button" id="proceedToBookingBtn" class="btn btn-primary px-5 fw-bold" onclick="showBookingForm()" disabled>
                    Accept and Proceed
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let tempCarData = null;
let bookedDates = [];
let currentCalendarDate = new Date();
let selectedStartDate = null;
let selectedEndDate = null;

function openBookingModal(car) {
    tempCarData = car;
    document.getElementById('agreeCheckbox').checked = false;
    document.getElementById('proceedToBookingBtn').disabled = true;
    
    const termsModal = new bootstrap.Modal(document.getElementById('termsModal'));
    termsModal.show();
}

function toggleProceedBtn() {
    const isChecked = document.getElementById('agreeCheckbox').checked;
    document.getElementById('proceedToBookingBtn').disabled = !isChecked;
}

async function showBookingForm() {
    const termsEl = document.getElementById('termsModal');
    const modalInstance = bootstrap.Modal.getInstance(termsEl);
    if (modalInstance) {
        modalInstance.hide();
    }

    // Populate car data
    document.getElementById('modalCarName').innerText = tempCarData.brand + ' ' + tempCarData.model;
    document.getElementById('modalCarId').value = tempCarData.id;
    document.getElementById('modalCarPrice24').value = tempCarData.price_24_hours || 0;
    document.getElementById('modalCarPrice12').value = tempCarData.price_12_hours || 0;
    document.getElementById('modalCarPrice10').value = tempCarData.price_10_hours || 0;

    // Populate extension rate inputs for JS calculations
    if (document.getElementById('modalExtPrice1_6'))   document.getElementById('modalExtPrice1_6').value   = tempCarData.ext_price_1_6 || 0;
    if (document.getElementById('modalExtPrice7_10'))  document.getElementById('modalExtPrice7_10').value  = tempCarData.ext_price_7_10 || 0;
    if (document.getElementById('modalExtPrice11_12')) document.getElementById('modalExtPrice11_12').value = tempCarData.ext_price_11_12 || 0;
    if (document.getElementById('modalExtPrice13_24')) document.getElementById('modalExtPrice13_24').value = tempCarData.ext_price_13_24 || 0;

    // Reset dates
    const pickupDt = document.getElementById('pickupDatetime');
    const returnDt = document.getElementById('returnDatetime');
    if (pickupDt) pickupDt.value = '';
    if (returnDt) returnDt.value = '';
    
    if (document.getElementById('startDate')) document.getElementById('startDate').value = '';
    if (document.getElementById('endDate')) document.getElementById('endDate').value = '';
    
    selectedStartDate = null;
    selectedEndDate = null;
    
    if (document.getElementById('userDisplayTotal')) document.getElementById('userDisplayTotal').innerText = '₱0.00';
    if (document.getElementById('userDisplayBasePrice')) document.getElementById('userDisplayBasePrice').innerText = '₱0.00';
    if (document.getElementById('userDurationDisplay')) document.getElementById('userDurationDisplay').innerHTML = '0 Hours';
    if (document.getElementById('availabilityWarning')) document.getElementById('availabilityWarning').classList.add('d-none');
    
    // Load availability calendar
    await loadAvailabilityCalendar(tempCarData.id);
    
    setTimeout(() => {
        const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
        bookingModal.show();
    }, 400);
}

async function loadAvailabilityCalendar(carId) {
    try {
        const response = await fetch(`process/get_car_bookings.php?car_id=${carId}`);
        const data = await response.json();
        
        if (data.success) {
            bookedDates = data.booked_dates;
            renderCalendar(currentCalendarDate);
        } else {
            document.getElementById('availabilityCalendar').innerHTML = 
                '<div class="alert alert-danger">Failed to load calendar</div>';
        }
    } catch (error) {
        console.error('Error loading calendar:', error);
        document.getElementById('availabilityCalendar').innerHTML = 
            '<div class="alert alert-danger">Error loading availability</div>';
    }
}

function renderCalendar(date) {
    const year = date.getFullYear();
    const month = date.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startWeekday = firstDay.getDay(); 
    const daysInMonth = lastDay.getDate();
    
    let calendarHtml = `
        <div class="calendar-nav">
            <button type="button" onclick="changeMonth(-1)">&laquo; Prev</button>
            <h6 class="m-0" style="font-size: 0.9rem;">${firstDay.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}</h6>
            <button type="button" onclick="changeMonth(1)">Next &raquo;</button>
        </div>
        <div class="calendar-grid">
    `;
    
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    weekdays.forEach(day => {
        calendarHtml += `<div class="calendar-header">${day}</div>`;
    });
    
    for (let i = 0; i < startWeekday; i++) {
        calendarHtml += `<div class="calendar-day empty-day"></div>`;
    }
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    let dStart = null;
    let dEnd = null;
    if (selectedStartDate) {
        const startParts = selectedStartDate.split('-');
        dStart = new Date(startParts[0], startParts[1] - 1, startParts[2]);
        dStart.setHours(0, 0, 0, 0);
    }
    if (selectedEndDate) {
        const endParts = selectedEndDate.split('-');
        dEnd = new Date(endParts[0], endParts[1] - 1, endParts[2]);
        dEnd.setHours(0, 0, 0, 0);
    }
    
    for (let day = 1; day <= daysInMonth; day++) {
        const currentDate = new Date(year, month, day);
        currentDate.setHours(0, 0, 0, 0);
        
        const formatDay = String(day).padStart(2, '0');
        const formatMonth = String(month + 1).padStart(2, '0');
        const dateString = `${year}-${formatMonth}-${formatDay}`;
        
        let isBooked = bookedDates.includes(dateString);
        let isToday = currentDate.getTime() === today.getTime();
        let isPast = currentDate < today;
        
        let cssClass = '';
        if (isBooked || isPast) {
            cssClass = 'booked';
        } else {
            cssClass = 'available';
            
            if (dStart) {
                if (dEnd) {
                    if (currentDate >= dStart && currentDate <= dEnd) {
                        cssClass += ' selected';
                    }
                } else if (currentDate.getTime() === dStart.getTime()) {
                    cssClass += ' selected';
                }
            }
        }
        
        if (isToday) cssClass += ' today';
        
        let onclickAttr = '';
        if (!isBooked && !isPast) {
            onclickAttr = `onclick="selectDateFromCalendar('${dateString}')"`;
        }
        
        calendarHtml += `
            <div class="calendar-day ${cssClass}" ${onclickAttr}>
                ${day}
            </div>
        `;
    }
    
    calendarHtml += `</div>`;
    document.getElementById('availabilityCalendar').innerHTML = calendarHtml;
}

function changeMonth(delta) {
    currentCalendarDate.setMonth(currentCalendarDate.getMonth() + delta);
    renderCalendar(currentCalendarDate);
}

function selectDateFromCalendar(dateString) {
    const pickupDt = document.getElementById('pickupDatetime');
    const returnDt = document.getElementById('returnDatetime');
    
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    const pickupTimeInput = document.getElementById('pickupTime');
    const returnTimeInput = document.getElementById('returnTime');
    
    const timeStart = (pickupTimeInput && pickupTimeInput.value) ? pickupTimeInput.value : '09:00';
    const timeReturn = (returnTimeInput && returnTimeInput.value) ? returnTimeInput.value : '17:00';

    if (!selectedStartDate || (selectedStartDate && selectedEndDate)) {
        selectedStartDate = dateString;
        selectedEndDate = null;
        
        if (startDateInput) startDateInput.value = dateString;
        if (endDateInput) endDateInput.value = '';
        
        if (pickupDt) pickupDt.value = `${dateString}T${timeStart}`;
        if (returnDt) returnDt.value = '';
        
        highlightSelectedDates(dateString, null);
    } else {
        if (dateString < selectedStartDate) {
            selectedStartDate = dateString;
            selectedEndDate = null;
            
            if (startDateInput) startDateInput.value = dateString;
            if (endDateInput) endDateInput.value = '';
            
            if (pickupDt) pickupDt.value = `${dateString}T${timeStart}`;
            if (returnDt) returnDt.value = '';
            
            highlightSelectedDates(dateString, null);
        } else {
            selectedEndDate = dateString;
            
            if (endDateInput) endDateInput.value = dateString;
            if (returnDt) returnDt.value = `${dateString}T${timeReturn}`;
            
            highlightSelectedDates(selectedStartDate, dateString);
            validateAndCalculate();
        }
    }
}

function highlightSelectedDates(start, end) {
    renderCalendar(currentCalendarDate);
}

function syncPublicDateTimeValues() {
    validateAndCalculate();
}

async function validateAndCalculate() {
    let carId = document.getElementById('modalCarId')?.value;
    
    let startDate = '', startTime = '', endDate = '', endTime = '';
    
    const pickupDt = document.getElementById('pickupDatetime');
    const returnDt = document.getElementById('returnDatetime');
    
    if (pickupDt && pickupDt.value) {
        const parts = pickupDt.value.split('T');
        startDate = parts[0];
        startTime = parts[1] || '09:00';
    } else {
        startDate = document.getElementById('startDate')?.value || '';
        startTime = document.getElementById('pickupTime')?.value || '09:00';
    }

    if (returnDt && returnDt.value) {
        const parts = returnDt.value.split('T');
        endDate = parts[0];
        endTime = parts[1] || '17:00';
    } else {
        endDate = document.getElementById('endDate')?.value || '';
        endTime = document.getElementById('returnTime')?.value || '17:00';
    }

    if (document.getElementById('startDate')) document.getElementById('startDate').value = startDate;
    if (document.getElementById('pickupTime')) document.getElementById('pickupTime').value = startTime;
    if (document.getElementById('endDate')) document.getElementById('endDate').value = endDate;
    if (document.getElementById('returnTime')) document.getElementById('returnTime').value = endTime;

    const warningDiv = document.getElementById('availabilityWarning');
    const warningMsg = document.getElementById('warningMessage');
    const confirmBtn = document.getElementById('confirmBtn');
    
    if (!startDate || !endDate || !startTime || !endTime) {
        calculateUserTotal();
        return;
    }
    
    const start = new Date(`${startDate}T${startTime}`);
    const end = new Date(`${endDate}T${endTime}`);
    
    // Safety check for invalid dates
    if (isNaN(start.getTime()) || isNaN(end.getTime())) {
        if (confirmBtn) confirmBtn.disabled = true;
        return false;
    }

    const hours = (end - start) / (1000 * 60 * 60);
    
    if (hours < 10) {
        if (warningDiv && warningMsg) {
            warningDiv.classList.remove('d-none');
            warningMsg.innerHTML = `⚠️ Minimum booking is 10 hours. Current duration: ${hours.toFixed(1)} hours. Please adjust your schedule.`;
        }
        if (confirmBtn) confirmBtn.disabled = true;
        if (document.getElementById('userDisplayTotal')) document.getElementById('userDisplayTotal').innerHTML = '<span class="text-danger">Minimum 10 hours required!</span>';
        if (document.getElementById('userDurationDisplay')) document.getElementById('userDurationDisplay').innerHTML = `<span class="text-danger">${hours.toFixed(1)} Hours (Min 10h)</span>`;
        return false;
    }
    
    const startParts = startDate.split('-');
    const endParts = endDate.split('-');
    const startDateObj = new Date(startParts[0], startParts[1] - 1, startParts[2]);
    const endDateObj = new Date(endParts[0], endParts[1] - 1, endParts[2]);
    
    let hasConflict = false;
    let conflictMessage = '';
    
    for (let d = new Date(startDateObj); d <= endDateObj; d.setDate(d.getDate() + 1)) {
        const formatDay = String(d.getDate()).padStart(2, '0');
        const formatMonth = String(d.getMonth() + 1).padStart(2, '0');
        const dateString = `${d.getFullYear()}-${formatMonth}-${formatDay}`;
        
        if (bookedDates.includes(dateString)) {
            hasConflict = true;
            conflictMessage = `The date ${dateString} is already booked. Please select different dates.`;
            break;
        }
    }
    
    if (!hasConflict && carId) {
        try {
            const checkResponse = await fetch(`process/check_date_range.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    car_id: carId,
                    start_date: startDate,
                    end_date: endDate,
                    start_time: startTime,
                    end_time: endTime
                })
            });
            
            const checkData = await checkResponse.json();
            if (!checkData.available) {
                hasConflict = true;
                conflictMessage = checkData.message || 'Selected dates conflict with existing booking';
            }
        } catch(e) {}
    }
    
    if (hasConflict) {
        if (warningDiv && warningMsg) {
            warningDiv.classList.remove('d-none');
            warningMsg.innerText = conflictMessage;
        }
        if (confirmBtn) confirmBtn.disabled = true;
        if (document.getElementById('userDisplayTotal')) document.getElementById('userDisplayTotal').innerText = '₱0.00';
        return false;
    } else {
        if (warningDiv) warningDiv.classList.add('d-none');
        if (confirmBtn) confirmBtn.disabled = false;
        calculateUserTotal();
        return true;
    }
}

let currentSelectedDurationMode = 'custom';

function setPublicDuration(hours, element) {
    const durationContainer = element ? element.closest('.duration-buttons') : document;
    if (durationContainer) {
        durationContainer.querySelectorAll('.duration-btn').forEach(btn => btn.classList.remove('active'));
    }
    if (element) {
        element.classList.add('active');
    }

    currentSelectedDurationMode = hours;
    const returnDt = document.getElementById('returnDatetime');

    if (hours !== 'custom') {
        if (returnDt) returnDt.readOnly = true;
        applyQuickDurationPreset(hours);
    } else {
        if (returnDt) returnDt.readOnly = false;
        validateAndCalculate();
    }
}

function applyQuickDurationPreset(hours) {
    const pickupDt = document.getElementById('pickupDatetime');
    const returnDt = document.getElementById('returnDatetime');

    let startDatetime = null;

    if (pickupDt && pickupDt.value) {
        startDatetime = new Date(pickupDt.value);
    } else if (selectedStartDate) {
        startDatetime = new Date(`${selectedStartDate}T09:00`);
    }

    if (!startDatetime || isNaN(startDatetime.getTime())) {
        startDatetime = new Date();
    }

    const endDatetime = new Date(startDatetime.getTime() + (parseInt(hours) * 60 * 60 * 1000));

    const tzOffsetStart = startDatetime.getTimezoneOffset() * 60000;
    const tzOffsetEnd = endDatetime.getTimezoneOffset() * 60000;

    const formattedStartISO = new Date(startDatetime.getTime() - tzOffsetStart).toISOString().slice(0, 16);
    const formattedEndISO = new Date(endDatetime.getTime() - tzOffsetEnd).toISOString().slice(0, 16);

    if (pickupDt) pickupDt.value = formattedStartISO;
    if (returnDt) returnDt.value = formattedEndISO;

    selectedStartDate = formattedStartISO.split('T')[0];
    selectedEndDate = formattedEndISO.split('T')[0];

    highlightSelectedDates(selectedStartDate, selectedEndDate);
    validateAndCalculate();
}

async function calculateUserTotal() {
    const carId = document.getElementById('modalCarId')?.value;
    
    let startDate = '', startTime = '', endDate = '', endTime = '';
    
    const pickupDt = document.getElementById('pickupDatetime');
    const returnDt = document.getElementById('returnDatetime');

    if (pickupDt && pickupDt.value) {
        const parts = pickupDt.value.split('T');
        startDate = parts[0];
        startTime = parts[1] || '09:00';
    } else {
        startDate = document.getElementById('startDate')?.value;
        startTime = document.getElementById('pickupTime')?.value;
    }

    if (returnDt && returnDt.value) {
        const parts = returnDt.value.split('T');
        endDate = parts[0];
        endTime = parts[1] || '17:00';
    } else {
        endDate = document.getElementById('endDate')?.value;
        endTime = document.getElementById('returnTime')?.value;
    }

    const confirmBtn = document.getElementById('confirmBtn');
    const userDisplayTotal = document.getElementById('userDisplayTotal');
    const userDisplayBasePrice = document.getElementById('userDisplayBasePrice');
    const userDurationDisplay = document.getElementById('userDurationDisplay');

    if (!startDate || !startTime || !endDate || !endTime) {
        if (userDisplayTotal) userDisplayTotal.innerText = '₱0.00';
        if (userDurationDisplay) userDurationDisplay.innerText = '0 Hours';
        return;
    }

    const start = new Date(`${startDate}T${startTime}`);
    const end = new Date(`${endDate}T${endTime}`);

    if (isNaN(start.getTime()) || isNaN(end.getTime()) || end <= start) {
        if (userDisplayTotal) userDisplayTotal.innerHTML = '<span class="text-danger">Invalid schedule</span>';
        if (userDurationDisplay) userDurationDisplay.innerHTML = '<span class="text-danger">Invalid dates</span>';
        if (confirmBtn) confirmBtn.disabled = true;
        return;
    }

    const diffMs = end - start;
    const hours = Math.ceil(diffMs / (1000 * 60 * 60));
    const days = Math.floor(hours / 24);

    let durationText = `${hours} Hours`;
    if (hours >= 24) {
        const remHours = hours % 24;
        durationText = remHours > 0 ? `${days} Day(s), ${remHours} Hr(s)` : `${days} Day(s) (${hours} Hours)`;
    }

    if (hours < 10) {
        if (userDisplayTotal) userDisplayTotal.innerHTML = '<span class="text-danger">⚠️ Min 10h</span>';
        if (userDurationDisplay) userDurationDisplay.innerHTML = `<span class="text-danger">${hours} Hours (Min 10h)</span>`;
        if (confirmBtn) confirmBtn.disabled = true;
        return;
    } else {
        if (userDurationDisplay) userDurationDisplay.innerHTML = `<span class="text-success fw-bold">${durationText} ✓</span>`;
        if (confirmBtn) confirmBtn.disabled = false;
    }

    try {
        const formData = new FormData();
        formData.append('car_id', carId);
        formData.append('start_datetime', `${startDate} ${startTime}`);
        formData.append('end_datetime', `${endDate} ${endTime}`);
        formData.append('hours', hours);
        formData.append('days', days);

        const response = await fetch('process/calculate_price.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            const formattedPrice = '₱' + result.total_price.toLocaleString('en-PH', { 
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2 
            });
            
            if (userDisplayTotal) userDisplayTotal.innerText = formattedPrice;
            if (userDisplayBasePrice) userDisplayBasePrice.innerText = formattedPrice;
            if (document.getElementById('totalPriceInput')) document.getElementById('totalPriceInput').value = result.total_price;
        } else {
            fallbackCalculateTotal(hours);
        }
    } catch (error) {
        fallbackCalculateTotal(hours);
    }
}

function fallbackCalculateTotal(hours) {
    const confirmBtn = document.getElementById('confirmBtn');
    
    const p10 = parseFloat(document.getElementById('modalCarPrice10')?.value) || 0;
    const p12 = parseFloat(document.getElementById('modalCarPrice12')?.value) || 0;
    const p24 = parseFloat(document.getElementById('modalCarPrice24')?.value) || 0;

    const ext1_6   = parseFloat(document.getElementById('modalExtPrice1_6')?.value) || 0;
    const ext7_10  = parseFloat(document.getElementById('modalExtPrice7_10')?.value) || 0;
    const ext11_12 = parseFloat(document.getElementById('modalExtPrice11_12')?.value) || 0;
    const ext13_24 = parseFloat(document.getElementById('modalExtPrice13_24')?.value) || 0;

    const baseDayRate = p24 > 0 ? p24 : 1500;
    let total = 0;

    if (hours <= 10) {
        total = p10 > 0 ? p10 : 1099;
    } else if (hours <= 12) {
        total = p12 > 0 ? p12 : 1300;
    } else {
        // Evaluate full 24-hour day blocks and calculate remaining excess hours
        const days = Math.floor(hours / 24);
        const extraHours = hours % 24;
        
        total = days * baseDayRate;

        if (extraHours > 0) {
            let extensionFee = 0;

            for (let h = 1; h <= extraHours; h++) {
                if (h <= 6)       extensionFee += ext1_6;
                else if (h <= 10) extensionFee += ext7_10;
                else if (h <= 12) extensionFee += ext11_12;
                else              extensionFee += ext13_24;
            }

            // Cap excess charges if they exceed a full 24h day rate
            if (extensionFee > baseDayRate) {
                extensionFee = baseDayRate;
            }

            total += extensionFee;
        }
    }

    total = Math.round(total * 100) / 100;
    const formattedTotal = '₱' + total.toLocaleString('en-PH', { 
        minimumFractionDigits: 2, 
        maximumFractionDigits: 2 
    });

    if (document.getElementById('userDisplayTotal')) document.getElementById('userDisplayTotal').innerText = formattedTotal;
    if (document.getElementById('userDisplayBasePrice')) document.getElementById('userDisplayBasePrice').innerText = formattedTotal;
    if (document.getElementById('totalPriceInput')) document.getElementById('totalPriceInput').value = total;

    if (confirmBtn) confirmBtn.disabled = false;
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Offcanvas Mobile Sidebar Toggle Script
    const dynamicHeaderArea = document.querySelector('.main-content header, .main-content nav, .container-fluid');
    let toggleBtn = null;
    
    if (dynamicHeaderArea) {
        const componentButtons = dynamicHeaderArea.getElementsByTagName('button');
        for (let btn of componentButtons) {
            if (btn.querySelector('.bi-list') || btn.innerHTML.includes('<span') || btn.className.includes('navbar-toggler')) {
                toggleBtn = btn;
                break;
            }
        }
    }
    
    if (!toggleBtn) {
        toggleBtn = document.querySelector('header button, .navbar-toggler, .bg-warning button');
    }

    const sidebar = document.getElementById("sidebarWrapper");
    const backdrop = document.getElementById("sidebarBackdrop");

    if (toggleBtn && sidebar && backdrop) {
        function toggleSidebar() {
            sidebar.classList.toggle("show");
            backdrop.classList.toggle("show");
        }

        toggleBtn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });

        backdrop.addEventListener("click", toggleSidebar);
    }

    const pickupDt = document.getElementById('pickupDatetime');
    const returnDt = document.getElementById('returnDatetime');

    if (pickupDt) pickupDt.addEventListener('change', validateAndCalculate);
    if (returnDt) returnDt.addEventListener('change', validateAndCalculate);

    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    const pickupTimeInput = document.getElementById('pickupTime');
    const returnTimeInput = document.getElementById('returnTime');

    if (startDateInput) startDateInput.addEventListener('change', validateAndCalculate);
    if (endDateInput) endDateInput.addEventListener('change', validateAndCalculate);
    if (pickupTimeInput) pickupTimeInput.addEventListener('change', validateAndCalculate);
    if (returnTimeInput) returnTimeInput.addEventListener('change', validateAndCalculate);

    // Bootstrap dropdown z-index handling for card overlays
    const dropdownElements = document.querySelectorAll('.rate-dropdown-overlay');
    dropdownElements.forEach(dropdown => {
        const carId = dropdown.getAttribute('data-parent-card');
        const parentCardItem = dropdown.closest('.car-item');
        
        if (parentCardItem) {
            dropdown.addEventListener('show.bs.dropdown', function() {
                parentCardItem.classList.add('dropdown-active');
            });
            dropdown.addEventListener('hide.bs.dropdown', function() {
                parentCardItem.classList.remove('dropdown-active');
            });
        }
    });
});

function openStashGalleryModal(imagesArray) {
    const modalEl = document.getElementById('stashGalleryModal');
    const carouselEl = document.getElementById('stashGalleryCarousel');
    const container = document.getElementById('stashCarouselItemsContainer');
    const thumbsContainer = document.getElementById('stashThumbsContainer');
    const prevBtn = document.getElementById('stashCarouselPrevBtn');
    const nextBtn = document.getElementById('stashCarouselNextBtn');
    
    container.innerHTML = '';
    thumbsContainer.innerHTML = '';
    
    let existingCarousel = bootstrap.Carousel.getInstance(carouselEl);
    if (existingCarousel) {
        existingCarousel.dispose();
    }

    if (!imagesArray || imagesArray.length === 0) return;

    if (imagesArray.length <= 1) {
        prevBtn.classList.add('d-none');
        nextBtn.classList.add('d-none');
    } else {
        prevBtn.classList.remove('d-none');
        nextBtn.classList.remove('d-none');
    }

    imagesArray.forEach((imgName, idx) => {
        const fullPath = `../../public/assets/images/cars/${imgName}`;
        
        const itemDiv = document.createElement('div');
        itemDiv.className = `carousel-item ${idx === 0 ? 'active' : ''}`;
        itemDiv.innerHTML = `
            <img src="${fullPath}" class="d-block w-100" style="height: 450px; object-fit: contain; background: #000;" alt="Vehicle Gallery Photo">
        `;
        container.appendChild(itemDiv);
        
        if (imagesArray.length > 1) {
            const thumbImg = document.createElement('img');
            thumbImg.src = fullPath;
            thumbImg.className = `img-thumbnail bg-dark border-secondary ${idx === 0 ? 'border-primary opacity-100' : 'opacity-50'}`;
            thumbImg.style = "width: 65px; height: 45px; object-fit: cover; cursor: pointer; transition: all 0.2s;";
            
            thumbImg.addEventListener('click', () => {
                const bsCarousel = bootstrap.Carousel.getOrCreateInstance(carouselEl);
                bsCarousel.to(idx);
            });
            thumbsContainer.appendChild(thumbImg);
        }
    });

    let bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    
    carouselEl.addEventListener('slide.bs.carousel', event => {
        const thumbs = thumbsContainer.querySelectorAll('img');
        thumbs.forEach((t, i) => {
            if (i === event.to) {
                t.classList.add('border-primary', 'opacity-100');
                t.classList.remove('opacity-50');
                t.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            } else {
                t.classList.remove('border-primary', 'opacity-100');
                t.classList.add('opacity-50');
            }
        });
    });

    bsModal.show();
}


function toggleFulfillmentDetails() {
    const isPickup = document.getElementById('fulfillmentPickup').checked;
    const isDelivery = document.getElementById('fulfillmentDelivery').checked;
    
    const pickupWrapper = document.getElementById('pickupAddressWrapper');
    const deliveryWrapper = document.getElementById('deliveryAddressWrapper');
    const deliveryInput = document.getElementById('deliveryAddress');

    if (isPickup) {
        pickupWrapper.classList.remove('d-none');
        pickupWrapper.style.setProperty('display', 'flex', 'important');
        
        deliveryWrapper.style.setProperty('display', 'none', 'important');
        deliveryInput.removeAttribute('required');
        deliveryInput.value = '';
    } else if (isDelivery) {
        pickupWrapper.classList.add('d-none');
        pickupWrapper.style.setProperty('display', 'none', 'important');
        
        deliveryWrapper.style.setProperty('display', 'block', 'important');
        deliveryInput.setAttribute('required', 'required');
    }
}
</script>