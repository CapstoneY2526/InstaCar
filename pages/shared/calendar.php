<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// --- AUTHENTICATION CHECK ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to access the calendar.";
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// ── Branch label ──
if ($user_role === 'staff') {
    $staff_branch_id = (int)($_SESSION['branch_id'] ?? 0);
    $_SESSION['view_branch'] = $staff_branch_id > 0 ? (string)$staff_branch_id : 'all';
}

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

$pageTitle = 'Booking Calendar';

require_once __DIR__ . '/../components/head.php'; 
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    body, 
    button, 
    input, 
    select, 
    textarea, 
    .form-control, 
    .btn, 
    .table,
    .fc { 
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; 
    }
    
    .main-content { 
        background-color: var(--brand-bg, #f8fafc);
        min-height: 100vh; 
        transition: background-color 0.25s ease, color 0.25s ease;
    }
    
    .calendar-card {
        background: white;
        border-radius: 1.25rem;
        border: 1px solid var(--brand-border, #e2e8f0);
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        padding: 1rem;
        transition: background-color 0.25s ease, border-color 0.25s ease;
        overflow: hidden;
    }

    :root {
        --fc-today-bg-color: #f1f5f9;
        --fc-button-bg-color: #0f172a;
        --fc-button-hover-bg-color: #1e293b;
        --fc-button-active-bg-color: #0f172a;
        --fc-border-color: #f1f5f9;
    }

    /* Prevent event overflow beyond calendar cells */
    .fc-daygrid-day-frame {
        overflow: hidden !important;
        max-height: 130px;
    }

    .fc-daygrid-day {
        cursor: pointer !important;
        position: relative;
    }

    .fc-daygrid-day-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 8px !important;
    }

    .fc-daygrid-day-number {
        font-weight: 700;
        text-decoration: underline !important;
        text-underline-offset: 3px;
    }

    /* "View schedule" indicator on dates */
    .view-more-hint {
        font-size: 0.65rem;
        color: #64748b;
        font-weight: 600;
        display: inline-block;
        opacity: 0.8;
    }

    .fc-daygrid-day:hover .view-more-hint {
        color: #ffcc00;
        opacity: 1;
    }

    .fc-daygrid-more-link {
        font-weight: 600 !important;
        color: #0f172a !important;
        font-size: 0.7rem !important;
        padding-left: 5px;
        text-decoration: none !important;
    }

    .fc-popover {
        border-radius: 12px !important;
        border: none !important;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;
        overflow: hidden;
        z-index: 1050 !important;
    }

    .fc-popover-header {
        background: #0f172a !important;
        color: white !important;
        padding: 8px 12px !important;
        font-weight: 600;
    }

    .fc-popover-body {
        padding: 10px !important;
        max-height: 400px;
        overflow-y: auto;
    }

    .fc .fc-toolbar-title { font-weight: 700; color: #1e293b; font-size: 1.25rem; }
    .fc .fc-button-primary { 
        background-color: #0f172a !important; 
        border: none !important; 
        font-size: 0.85rem !important;
        padding: 0.5rem 1rem !important;
        border-radius: 8px !important;
    }
    
    /* Grid events keep the compact pill look */
    .fc-daygrid-event,
    .fc-event:not(.fc-list-event) {
        border: none !important;
        padding: 2px 6px !important;
        font-size: 0.75rem !important;
        border-radius: 6px !important;
        cursor: pointer;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }

    .fc-day-today { background: #f8fafc !important; }
    .fc-day-today .fc-daygrid-day-number {
        background: #0f172a; color: white !important;
        border-radius: 6px; padding: 2px 6px !important;
    }

    /* Target Daily Timeline Indicators styling */
    .timeline-indicator-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 6px;
    }

    /* Brand Action Button Styling */
    .btn-brand {
        background-color: var(--brand-yellow, #ffcc00) !important;
        color: #000000 !important;
        border: none !important;
        font-weight: 600 !important;
        padding: 0.6rem 1.25rem !important;
        border-radius: 10px !important;
        transition: all 0.2s ease !important;
    }

    .btn-brand i, 
    .btn-brand span {
        color: #000000 !important;
    }

    .btn-brand:hover {
        background-color: #e6b800 !important;
        color: #000000 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.25) !important;
    }

    /* ========================================================
       DARK MODE
       ======================================================== */
    body.dark-mode,
    body.dark-mode .main-content {
        background-color: #0a0a0a !important;
        color: #f1f5f9 !important;
    }

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

    body.dark-mode .calendar-card {
        background-color: #141414 !important;
        border-color: #27272a !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.5) !important;
    }

    body.dark-mode .fc .fc-toolbar-title {
        color: #ffffff !important;
    }

    body.dark-mode .fc-theme-standard td,
    body.dark-mode .fc-theme-standard th,
    body.dark-mode .fc-theme-standard .fc-scrollgrid {
        border-color: #27272a !important;
    }

    body.dark-mode .fc .fc-col-header,
    body.dark-mode .fc .fc-col-header-cell {
        background-color: #1f1f1f !important;
    }

    body.dark-mode .fc .fc-col-header-cell-cushion {
        color: #ffffff !important;
        font-weight: 600 !important;
    }

    body.dark-mode .fc .fc-daygrid-day-number {
        color: #ffcc00 !important;
    }

    body.dark-mode .view-more-hint {
        color: #a1a1aa !important;
    }

    body.dark-mode .fc-day-today {
        background: #1f1f1f !important;
    }

    body.dark-mode .fc-day-today .fc-daygrid-day-number {
        background: #ffcc00 !important;
        color: #000000 !important;
        font-weight: bold !important;
    }

    body.dark-mode .fc .fc-button-primary {
        background-color: #1f1f1f !important;
        color: #f1f5f9 !important;
        border: 1px solid #27272a !important;
    }

    body.dark-mode .fc .fc-button-primary:hover,
    body.dark-mode .fc .fc-button-primary.fc-button-active {
        background-color: #2a2a2a !important;
        border-color: #ffcc00 !important;
        color: #ffcc00 !important;
    }

    body.dark-mode .fc-daygrid-event .fc-event-main,
    body.dark-mode .fc-daygrid-event .fc-event-main-frame,
    body.dark-mode .fc-daygrid-event .fc-event-title,
    body.dark-mode .fc-daygrid-event .fc-event-time {
        color: #ffffff !important;
        font-weight: 500 !important;
    }

    body.dark-mode .fc-daygrid-more-link {
        color: #ffcc00 !important;
    }

    body.dark-mode .fc-popover {
        background-color: #141414 !important;
        border: 1px solid #27272a !important;
    }

    body.dark-mode .fc-popover-header {
        background: #1f1f1f !important;
        color: #ffffff !important;
    }

    body.dark-mode .fc-popover-body {
        background-color: #141414 !important;
    }

    body.dark-mode .agenda-item-card {
        background-color: #1f1f1f !important;
        border: 1px solid #27272a !important;
    }

    body.dark-mode .agenda-item-card h6 {
        color: #ffffff !important;
    }

    body.dark-mode .agenda-item-card .border-top {
        border-color: #27272a !important;
    }

    body.dark-mode .text-dark,
    body.dark-mode h3,
    body.dark-mode h4,
    body.dark-mode h5,
    body.dark-mode h6,
    body.dark-mode label {
        color: #ffffff !important;
    }

    body.dark-mode .text-muted,
    body.dark-mode .text-secondary {
        color: #a1a1aa !important;
    }

    body.dark-mode .modal-content {
        background-color: #141414 !important;
        border: 1px solid #27272a !important;
        color: #f1f5f9 !important;
    }

    body.dark-mode .modal-header,
    body.dark-mode .modal-footer {
        border-color: #27272a !important;
    }

    body.dark-mode .modal-body .bg-light {
        background-color: #1f1f1f !important;
    }

    body.dark-mode .icon-shape.bg-primary-subtle {
        background-color: #1f1f1f !important;
        color: #ffcc00 !important;
    }

    body.dark-mode .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    body.dark-mode .btn-white {
        background-color: #141414 !important;
        color: #f1f5f9 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .btn-white i {
        color: #ffcc00 !important;
    }

    body.dark-mode .btn-dark {
        background-color: #ffcc00 !important;
        color: #000000 !important;
        border: none !important;
    }

    body.dark-mode .btn-dark:hover {
        background-color: #ffd633 !important;
    }

    body.dark-mode .btn-brand {
        background-color: #ffcc00 !important;
        color: #000000 !important;
    }

    body.dark-mode .btn-brand i,
    body.dark-mode .btn-brand span {
        color: #000000 !important;
    }

    @media (max-width: 768px) {
        .fc .fc-toolbar { flex-direction: column; gap: 10px; }
        .btn.btn-white{
            padding: .5rem .75rem !important;
            font-size: .75rem !important;
        }
        .calendar-card small{ font-size:.6rem !important; }
        .fc{ font-size:.85rem; }
        .fc-view-harness{ min-height:450px !important; }
    }

    /* Allow multi-day continuous event bars to stretch seamlessly */
    .fc-daygrid-block-event {
        margin-top: 2px !important;
        margin-bottom: 2px !important;
    }

    .fc-daygrid-event-harness {
        margin-bottom: 2px !important;
    }

    .fc-daygrid-day-frame {
        overflow: visible !important;
        max-height: none !important;
    }

    /* ========================================================
       STATUS-BASED EVENT COLORS — GRID ONLY
       ======================================================== */
    .fc-daygrid-event.status-pending {
        background-color: #f59e0b !important;
        border-color: #f59e0b !important;
    }
    .fc-daygrid-event.status-pending .fc-event-title,
    .fc-daygrid-event.status-pending .fc-event-main-frame,
    .fc-daygrid-event.status-pending .fc-event-time {
        color: #1f1300 !important;
    }

    .fc-daygrid-event.status-approved {
        background-color: #10b981 !important;
        border-color: #10b981 !important;
    }
    .fc-daygrid-event.status-approved .fc-event-title,
    .fc-daygrid-event.status-approved .fc-event-main-frame,
    .fc-daygrid-event.status-approved .fc-event-time {
        color: #ffffff !important;
    }

    .fc-daygrid-event.status-completed {
        background-color: #3b82f6 !important;
        border-color: #3b82f6 !important;
    }
    .fc-daygrid-event.status-completed .fc-event-title,
    .fc-daygrid-event.status-completed .fc-event-main-frame,
    .fc-daygrid-event.status-completed .fc-event-time {
        color: #ffffff !important;
    }

    .fc-daygrid-event.status-cancelled {
        background-color: #ef4444 !important;
        border-color: #ef4444 !important;
        opacity: 0.85;
    }
    .fc-daygrid-event.status-cancelled .fc-event-title,
    .fc-daygrid-event.status-cancelled .fc-event-main-frame,
    .fc-daygrid-event.status-cancelled .fc-event-time {
        color: #ffffff !important;
    }

    .fc-daygrid-event.status-schedule {
        background-color: #8b5cf6 !important;
        border-color: #8b5cf6 !important;
        opacity: 0.85;
    }
    .fc-daygrid-event.status-schedule .fc-event-title,
    .fc-daygrid-event.status-schedule .fc-event-main-frame,
    .fc-daygrid-event.status-schedule .fc-event-time {
        color: #ffffff !important;
    }

    /* ========================================================
       LIST VIEW — subtle rows, colored accent only
       ======================================================== */
    .fc-list {
        border-radius: 0.9rem !important;
        overflow: hidden;
        border: 1px solid var(--brand-border, #e2e8f0) !important;
    }

    /* Kill solid status backgrounds on list rows only — grid events keep theirs. */
    .fc-list-event td,
    .fc-list-event.status-pending td,
    .fc-list-event.status-approved td,
    .fc-list-event.status-completed td,
    .fc-list-event.status-cancelled td,
    .fc-list-event.status-schedule td {
        background-color: transparent !important;
    }

    .fc-list-event .fc-event-title,
    .fc-list-event .fc-event-main-frame,
    .fc-list-event .fc-event-time {
        color: inherit !important;
    }

    .fc-list-day-cushion {
        background: #f1f5f9 !important;
        padding: 10px 16px !important;
        border-top: 1px solid #e2e8f0;
    }

    .fc-list-day-text,
    .fc-list-day-side-text {
        font-weight: 700 !important;
        color: #0f172a !important;
        text-decoration: none !important;
        font-size: 0.88rem;
    }

    .fc-list-table td {
        padding: 10px 14px !important;
        vertical-align: middle !important;
        border-color: #f1f5f9 !important;
    }

    .fc-list-event {
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .fc-list-event:hover td {
        background-color: #f8fafc !important;
    }

    /* Recolor the native list-view dot per status */
    .fc-list-event.status-pending   .fc-list-event-dot { border-color: #f59e0b !important; }
    .fc-list-event.status-approved  .fc-list-event-dot { border-color: #10b981 !important; }
    .fc-list-event.status-completed .fc-list-event-dot { border-color: #3b82f6 !important; }
    .fc-list-event.status-cancelled .fc-list-event-dot { border-color: #ef4444 !important; }
    .fc-list-event.status-schedule  .fc-list-event-dot { border-color: #8b5cf6 !important; }

    .fc-list-event-time {
        font-weight: 700 !important;
        font-size: 0.78rem !important;
        color: #94a3b8 !important;
        white-space: nowrap;
    }

    .fc-list-event-dot {
        border-width: 5px !important;
    }

    .fc-list-event-title {
        font-weight: 500 !important;
        font-size: 0.85rem !important;
        color: #0f172a !important;
    }

    /* Row layout: accent bar + body + chip */
    .fc-list-event-title .list-event-row {
        display: flex;
        align-items: stretch;
        gap: 10px;
    }

    .list-event-accent {
        flex-shrink: 0;
        width: 4px;
        border-radius: 3px;
        background: var(--accent, #94a3b8);
        align-self: stretch;
        min-height: 32px;
    }

    .fc-list-event-title .list-event-main {
        flex: 1 1 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        min-width: 0;
    }

    .fc-list-event-title .list-event-body {
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 0;
    }

    .list-event-model {
        font-weight: 700;
        color: #0f172a;
        font-size: 0.85rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .list-event-id {
        font-size: 0.68rem;
        font-weight: 600;
        color: #94a3b8;
    }

    .list-event-time-badges {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 2px;
    }

    .time-badge {
        font-size: 0.66rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
    }

    .time-badge.release { background: #dbeafe; color: #1d4ed8; }
    .time-badge.return  { background: #fee2e2; color: #b91c1c; }
    .time-badge.sched   { background: #ede9fe; color: #6d28d9; }

    .fc-list-event-title .status-chip {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 4px 10px;
        border-radius: 999px;
        white-space: nowrap;
        flex-shrink: 0;
        background: #e2e8f0;
        color: #475569;
    }

    .fc-list-event.status-pending   .status-chip { background: #fef3c7; color: #92400e; }
    .fc-list-event.status-approved  .status-chip { background: #d1fae5; color: #065f46; }
    .fc-list-event.status-completed .status-chip { background: #dbeafe; color: #1e40af; }
    .fc-list-event.status-cancelled .status-chip { background: #fee2e2; color: #991b1b; }
    .fc-list-event.status-schedule  .status-chip { background: #ede9fe; color: #5b21b6; }

    /* Continuation rows for in-between days of multi-day bookings */
    tr.fc-list-row-continuation td {
        padding-top: 3px !important;
        padding-bottom: 3px !important;
        background: #fafbfc !important;
    }
    tr.fc-list-row-continuation .fc-list-event-graphic,
    tr.fc-list-row-continuation .fc-list-event-time { opacity: 0.3; }

    .list-event-row.continuation {
        min-height: 14px;
        align-items: center;
        cursor: help;
    }

    .list-event-row.continuation .list-event-accent {
        min-height: 14px;
        opacity: 0.45;
        background-image: repeating-linear-gradient(
            to bottom,
            var(--accent, #94a3b8) 0,
            var(--accent, #94a3b8) 4px,
            transparent 4px,
            transparent 7px
        );
        background-color: transparent;
    }

    /* "+N more" toggle row */
    tr.list-more-row td {
        padding: 8px 14px !important;
        text-align: center;
        background: #f8fafc !important;
        border-top: 1px dashed #e2e8f0 !important;
    }

    .list-more-toggle {
        border: none;
        background: #ffffff;
        color: #475569;
        font-weight: 700;
        font-size: 0.72rem;
        padding: 4px 12px;
        border-radius: 999px;
        cursor: pointer;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }

    .list-more-toggle:hover { background: #f1f5f9; color: #0f172a; }

    .fc-list-empty {
        padding: 3rem 1rem !important;
        text-align: center;
        font-weight: 600;
        color: #94a3b8 !important;
        background-color: #ffffff !important;
    }

    /* ---- Dark mode list overrides ---- */
    body.dark-mode .fc-list-day-cushion {
        background: #1f1f1f !important;
        border-top-color: #27272a !important;
    }
    body.dark-mode .fc-list-day-text,
    body.dark-mode .fc-list-day-side-text { color: #ffcc00 !important; }

    body.dark-mode .fc-list-table td { border-color: #27272a !important; }
    body.dark-mode .fc-list-event:hover td { background-color: #1f1f1f !important; }
    body.dark-mode .fc-list-event-title,
    body.dark-mode .fc-list-event-title a,
    body.dark-mode .fc-list-event-time { color: #e4e4e7 !important; }

    body.dark-mode .list-event-model { color: #ffffff !important; }
    body.dark-mode .list-event-id { color: #71717a !important; }

    body.dark-mode .time-badge.release { background: #1e3a8a; color: #bfdbfe; }
    body.dark-mode .time-badge.return  { background: #7f1d1d; color: #fecaca; }
    body.dark-mode .time-badge.sched   { background: #4c1d95; color: #ddd6fe; }

    body.dark-mode .fc-list-event.status-pending   .status-chip { background: #78350f; color: #fde68a; }
    body.dark-mode .fc-list-event.status-approved  .status-chip { background: #064e3b; color: #6ee7b7; }
    body.dark-mode .fc-list-event.status-completed .status-chip { background: #1e3a8a; color: #93c5fd; }
    body.dark-mode .fc-list-event.status-cancelled .status-chip { background: #7f1d1d; color: #fca5a5; }
    body.dark-mode .fc-list-event.status-schedule  .status-chip { background: #4c1d95; color: #c4b5fd; }

    body.dark-mode tr.fc-list-row-continuation td { background: #161616 !important; }
    body.dark-mode tr.list-more-row td {
        background: #1a1a1a !important;
        border-top-color: #27272a !important;
    }
    body.dark-mode .list-more-toggle {
        background: #27272a;
        color: #ffcc00;
        box-shadow: none;
    }
    body.dark-mode .list-more-toggle:hover { background: #2f2f2f; }

    body.dark-mode .fc-list-empty {
        background-color: #141414 !important;
        color: #71717a !important;
    }

    @media (max-width: 992px) {
        .fc-list-day-cushion { padding: 8px 12px !important; }
        .fc-list-day-text, .fc-list-day-side-text { font-size: 0.82rem; }
        .fc-list-event-time { font-size: 0.72rem !important; }
        .fc-list-event-title { font-size: 0.8rem !important; }
    }

    @media (max-width: 576px) {
        .fc-toolbar-chunk .fc-button {
            padding: 0.4rem 0.65rem !important;
            font-size: 0.72rem !important;
        }
        .fc-list-table td { padding: 8px 10px !important; }
        .fc-list-event-time { font-size: 0.66rem !important; }
        .fc-list-event-title { font-size: 0.75rem !important; }
        .fc-list-event-title .list-event-row { gap: 6px; }
        .fc-list-event-title .status-chip { font-size: 0.58rem; padding: 2px 7px; }
        .fc-list-day-cushion { padding: 7px 10px !important; }
        .time-badge { font-size: 0.6rem; padding: 2px 6px; }
        .list-event-model { font-size: 0.78rem; }
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
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-0 text-dark">Booking Schedule</h3>
                        <p class="text-muted mb-0 small">
                            <?php
                                if ($user_role === 'admin') {
                                    echo htmlspecialchars($branch_label) . " · Full fleet overview.";
                                } elseif ($user_role === 'staff') {
                                    echo htmlspecialchars($branch_label) . " · Bookings at your branch.";
                                } else {
                                    echo "Your assigned vehicle bookings.";
                                }
                            ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-white border shadow-sm rounded-3 px-3 fw-semibold small">
                            <i class="bi bi-calendar3 me-1 text-primary"></i> <?= date('M d, Y') ?>
                        </button>
                    </div>
                </div>

                <div class="calendar-card mb-5">
                    <div class="d-flex justify-content-center gap-2 mb-3 flex-wrap">
                        <small class="fw-bold text-uppercase" style="font-size: 0.65rem; color: #f59e0b;">● Pending</small>
                        <small class="fw-bold text-uppercase" style="font-size: 0.65rem; color: #10b981;">● Approved</small>
                        <small class="fw-bold text-uppercase" style="font-size: 0.65rem; color: #3b82f6;">● Completed</small>
                        <small class="fw-bold text-uppercase" style="font-size: 0.65rem; color: #ef4444;">● Cancelled</small>
                        <small class="fw-bold text-uppercase" style="font-size: 0.65rem; color: #8b5cf6;">● Schedule Block</small>
                    </div>

                    <div id="calendar"></div>
                </div>
            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="dailyAgendaModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-2">
                <div>
                    <h5 class="fw-bold mb-0">Schedule Details</h5>
                    <small class="text-muted fw-semibold" id="agendaModalDateTitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 pt-0" id="agendaModalContainer" style="max-height: 420px;">
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    var dailyAgendaModal = new bootstrap.Modal(document.getElementById('dailyAgendaModal'));
    var userRole = '<?= $user_role ?>';

    // Max bookings shown per day before "+N more" collapse (list view).
    // Grid view uses dayMaxEvents below.
    var DAY_LIMIT = 3;

    function redirectToDetails(eventId) {
        window.location.href = 'booking_details.php?id=' + eventId;
    }

    function toLocalIsoString(date) {
        if (!date || isNaN(date.getTime())) return '';
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function formatToMilitaryTime(date) {
        if (!date || isNaN(date.getTime())) return '00:00';
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${hours}:${minutes}`;
    }

    // ── Mobile lock ───────────────────────────────────────────
    // Phones/tablets get list view only; grid view breaks on narrow screens.
    var mobileMql = window.matchMedia('(max-width: 768px)');
    var isMobile = mobileMql.matches;

    function buildToolbar() {
        return {
            left: 'prev,next',
            center: 'title',
            right: isMobile ? 'listMonth' : 'dayGridMonth,listMonth'
        };
    }

    // ── Collapse each list day to DAY_LIMIT rows ──────────────
    var enforceTimer = null;

    function enforceListDayLimit() {
        var tbody = document.querySelector('#calendar .fc-list-table tbody');
        if (!tbody) return;

        // Clean up previous injections first (idempotent re-renders).
        tbody.querySelectorAll('tr.list-more-row').forEach(function(r) { r.remove(); });
        tbody.querySelectorAll('tr.fc-list-event[data-hidden-by-limit="1"]').forEach(function(r) {
            r.style.display = '';
            r.removeAttribute('data-hidden-by-limit');
        });

        // Group rows by their day header.
        var groups = [];
        var current = null;
        Array.prototype.forEach.call(tbody.children, function(row) {
            if (row.classList.contains('fc-list-day')) {
                current = { events: [] };
                groups.push(current);
            } else if (current && row.classList.contains('fc-list-event')) {
                current.events.push(row);
            }
        });

        groups.forEach(function(g) {
            if (g.events.length <= DAY_LIMIT) return;

            // Longest-running bookings first (multi-day rentals stay visible).
            var sorted = g.events.slice().sort(function(a, b) {
                var da = parseFloat(a.dataset.duration) || 0;
                var db = parseFloat(b.dataset.duration) || 0;
                return db - da;
            });

            var hidden = sorted.slice(DAY_LIMIT);
            hidden.forEach(function(r) {
                r.style.display = 'none';
                r.setAttribute('data-hidden-by-limit', '1');
            });

            // Anchor the "+N more" row after the last visible event row.
            var lastRow = g.events[g.events.length - 1];
            var moreRow = document.createElement('tr');
            moreRow.className = 'list-more-row';
            var label = '+ ' + hidden.length + ' more booking' + (hidden.length > 1 ? 's' : '');
            moreRow.innerHTML = '<td colspan="3">' +
                '<button type="button" class="list-more-toggle">' +
                '<i class="bi bi-chevron-down me-1"></i>' + label +
                '</button></td>';
            lastRow.parentNode.insertBefore(moreRow, lastRow.nextSibling);

            var expanded = false;
            moreRow.querySelector('.list-more-toggle').addEventListener('click', function() {
                expanded = !expanded;
                hidden.forEach(function(r) { r.style.display = expanded ? '' : 'none'; });
                this.innerHTML = expanded
                    ? '<i class="bi bi-chevron-up me-1"></i>Show less'
                    : '<i class="bi bi-chevron-down me-1"></i>' + label;
            });
        });
    }

    function scheduleEnforce() {
        clearTimeout(enforceTimer);
        enforceTimer = setTimeout(enforceListDayLimit, 0);
    }

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: isMobile ? 'listMonth' : 'dayGridMonth',
        headerToolbar: buildToolbar(),
        displayEventTime: false,
        dayMaxEvents: DAY_LIMIT,     // grid view: show max 3, rest go to "+N more"
        moreLinkClick: 'popover',
        height: 650,
        editable: false,
        selectable: false,           // list rows use eventClick, not dateClick
        eventOrder: 'start,-duration,allDay,title',

        events: function(fetchInfo, successCallback, failureCallback) {
            fetch('process/fetch_bookings.php')
                .then(function(r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(successCallback)
                .catch(function(err) {
                    console.error('Error fetching bookings:', err);
                    failureCallback(err);
                });
        },

        eventClassNames: function(arg) {
            const props = arg.event.extendedProps || {};
            if (props.type === 'schedule') return ['status-schedule'];
            const status = (props.status || '').toLowerCase();
            return status ? ['status-' + status] : [];
        },

        eventContent: function(arg) {
            const props = arg.event.extendedProps || {};
            const isSchedule = props.type === 'schedule';

            const model = props.model || 'Vehicle';
            const color = props.color || 'N/A';
            const status = props.status || '';

            const rawStart = props.raw_start ? new Date(props.raw_start) : arg.event.start;
            const rawEnd   = props.raw_end   ? new Date(props.raw_end)   : (arg.event.end || arg.event.start);

            const isStartSegment = arg.isStart;
            const isEndSegment   = arg.isEnd;
            const isSameDay      = isStartSegment && isEndSegment;

            const releaseTime = formatToMilitaryTime(rawStart);
            const returnTime  = formatToMilitaryTime(rawEnd);

            let timeStr = '';
            if (isSchedule) {
                timeStr = '00:00 - 00:00 (All-Day)';
            } else if (isSameDay) {
                timeStr = `${releaseTime} - ${returnTime}`;
            } else if (isStartSegment) {
                timeStr = `${releaseTime} - 00:00`;
            } else if (isEndSegment) {
                timeStr = `00:00 - ${returnTime}`;
            } else {
                timeStr = '00:00 - 00:00';
            }

            let customEl = document.createElement('div');
            customEl.className = 'fc-event-main-frame';

            if (arg.view.type === 'listMonth') {
                const statusColors = {
                    'Approved':  '#10b981',
                    'Pending':   '#f59e0b',
                    'Completed': '#3b82f6',
                    'Cancelled': '#ef4444'
                };
                const accent = isSchedule ? '#8b5cf6' : (statusColors[status] || '#64748b');
                const statusChip = status ? `<span class="status-chip">${status}</span>` : '';

                if (isSchedule) {
                    customEl.innerHTML = `
                        <div class="list-event-row" style="--accent:${accent}">
                            <div class="list-event-accent"></div>
                            <div class="list-event-main">
                                <div class="list-event-body">
                                    <span class="list-event-model">${model} • ${color}</span>
                                    <span class="list-event-time-badges"><span class="time-badge sched">🚧 All-day</span></span>
                                </div>
                                ${statusChip}
                            </div>
                        </div>`;
                } else if (isSameDay) {
                    customEl.innerHTML = `
                        <div class="list-event-row" style="--accent:${accent}">
                            <div class="list-event-accent"></div>
                            <div class="list-event-main">
                                <div class="list-event-body">
                                    <span class="list-event-model">${model} • ${color}</span>
                                    <span class="list-event-time-badges">
                                        <span class="time-badge release">🛬 ${releaseTime}</span>
                                        <span class="time-badge return">🛫 ${returnTime}</span>
                                    </span>
                                    <span class="list-event-id">ID #${arg.event.id}</span>
                                </div>
                                ${statusChip}
                            </div>
                        </div>`;
                } else if (isStartSegment) {
                    customEl.innerHTML = `
                        <div class="list-event-row" style="--accent:${accent}">
                            <div class="list-event-accent"></div>
                            <div class="list-event-main">
                                <div class="list-event-body">
                                    <span class="list-event-model">${model} • ${color}</span>
                                    <span class="list-event-time-badges"><span class="time-badge release">🛬 Release ${releaseTime}</span></span>
                                    <span class="list-event-id">ID #${arg.event.id}</span>
                                </div>
                                ${statusChip}
                            </div>
                        </div>`;
                } else if (isEndSegment) {
                    customEl.innerHTML = `
                        <div class="list-event-row" style="--accent:${accent}">
                            <div class="list-event-accent"></div>
                            <div class="list-event-main">
                                <div class="list-event-body">
                                    <span class="list-event-model">${model} • ${color}</span>
                                    <span class="list-event-time-badges"><span class="time-badge return">🛫 Return ${returnTime}</span></span>
                                    <span class="list-event-id">ID #${arg.event.id}</span>
                                </div>
                                ${statusChip}
                            </div>
                        </div>`;
                } else {
                    // In-between day: connected thread, no repeated text.
                    customEl.innerHTML = `
                        <div class="list-event-row continuation" style="--accent:${accent}"
                             title="${model} • ${color} • ID #${arg.event.id} (in progress)">
                            <div class="list-event-accent"></div>
                            <span class="continuation-dot"></span>
                        </div>`;
                }
            } else {
                customEl.innerHTML = `
                    <div class="fc-event-title-container">
                        <div class="fc-event-title fw-semibold text-truncate">
                            ${model} • ${color}${timeStr ? ' • ' + timeStr : ''}
                        </div>
                    </div>`;
            }
            return { domNodes: [customEl] };
        },

        eventDidMount: function(info) {
            if (info.view.type !== 'listMonth') return;

            const props = info.event.extendedProps || {};
            const s = props.raw_start ? new Date(props.raw_start) : info.event.start;
            const e = props.raw_end   ? new Date(props.raw_end)   : (info.event.end || s);
            info.el.dataset.duration = String(Math.max(0, e - s));

            if (!info.isStart && !info.isEnd) {
                info.el.classList.add('fc-list-row-continuation');
            }
        },

        eventsSet: function() {
            if (calendar.view.type === 'listMonth') scheduleEnforce();
        },

        dayCellDidMount: function(arg) {
            const topEl = arg.el.querySelector('.fc-daygrid-day-top');
            if (topEl) {
                const hint = document.createElement('span');
                hint.className = 'view-more-hint';
                hint.innerText = 'View schedule';
                topEl.insertBefore(hint, topEl.firstChild);
            }
        },

        dateClick: function(info) {
            if (info.view.type === 'listMonth') return;

            const clickedDateStr = info.dateStr;
            const formattedDateOptions = { month: 'short', day: 'numeric', year: 'numeric' };
            document.getElementById('agendaModalDateTitle').innerText =
                info.date.toLocaleDateString('en-US', formattedDateOptions);

            const targetStart = new Date(info.date);
            targetStart.setHours(0, 0, 0, 0);

            const targetEnd = new Date(info.date);
            targetEnd.setHours(23, 59, 59, 999);

            const allEvents = calendar.getEvents();

            const filteredEvents = allEvents.filter(evt => {
                const props = evt.extendedProps || {};
                const s = props.raw_start ? new Date(props.raw_start) : new Date(evt.start);
                const e = props.raw_end
                    ? new Date(props.raw_end)
                    : (evt.end ? new Date(evt.end.getTime() - 1000) : new Date(s));
                return (s <= targetEnd && e >= targetStart);
            });

            const container = document.getElementById('agendaModalContainer');
            container.innerHTML = '';

            if (filteredEvents.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-calendar-x opacity-50 display-6 d-block mb-2"></i>
                        <small class="fw-medium">No operational schedules recorded for this day</small>
                    </div>`;
                dailyAgendaModal.show();
                return;
            }

            filteredEvents.forEach(evt => {
                const props = evt.extendedProps || {};
                const start = props.raw_start ? new Date(props.raw_start) : new Date(evt.start);
                const end   = props.raw_end
                    ? new Date(props.raw_end)
                    : (evt.end ? new Date(evt.end.getTime() - 1000) : new Date(start));

                const startIsoStr = toLocalIsoString(start);
                const endIsoStr   = toLocalIsoString(end);

                let actionLabel = '';
                let actionBadgeClass = '';
                let timeRangeDisplay = '';

                const startTimeMil = formatToMilitaryTime(start);
                const endTimeMil   = formatToMilitaryTime(end);

                if (props.type === 'schedule') {
                    actionLabel = '🚧 ' + (props.reason || 'Blocked');
                    actionBadgeClass = 'bg-secondary bg-opacity-10 text-secondary';
                    timeRangeDisplay = '00:00 - 00:00 (All-Day)';
                } else if (startIsoStr === endIsoStr) {
                    actionLabel = 'Same-Day Rental';
                    actionBadgeClass = 'bg-dark text-white';
                    timeRangeDisplay = `${startTimeMil} - ${endTimeMil}`;
                } else if (clickedDateStr === startIsoStr) {
                    actionLabel = '🛬 Vehicle Pickup';
                    actionBadgeClass = 'bg-primary bg-opacity-10 text-primary';
                    timeRangeDisplay = `${startTimeMil} - 00:00`;
                } else if (clickedDateStr === endIsoStr) {
                    actionLabel = '🛫 Vehicle Return';
                    actionBadgeClass = 'bg-danger bg-opacity-10 text-danger';
                    timeRangeDisplay = `00:00 - ${endTimeMil}`;
                } else {
                    actionLabel = '🚗 Booked Out';
                    actionBadgeClass = 'bg-secondary bg-opacity-10 text-secondary';
                    timeRangeDisplay = 'All Day';
                }

                const model = props.model || 'Vehicle';
                const color = props.color || 'N/A';

                const colors = {
                    'Approved':  '#10b981',
                    'Pending':   '#f59e0b',
                    'Completed': '#3b82f6',
                    'Cancelled': '#ef4444'
                };
                const statusColor = colors[props.status] || '#64748b';

                const startDateLabel = start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                const endDateLabel   = end.toLocaleDateString('en-US',   { month: 'short', day: 'numeric' });
                const sameDay        = (startIsoStr === endIsoStr);
                const dateRangeLabel = sameDay ? startDateLabel : `${startDateLabel} → ${endDateLabel}`;

                const itemDiv = document.createElement('div');
                itemDiv.className = 'card border-0 bg-light p-3 rounded-3 mb-2 shadow-sm text-start agenda-item-card';
                itemDiv.style.cursor = 'pointer';
                itemDiv.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="timeline-indicator-badge ${actionBadgeClass} d-inline-block mb-1">${actionLabel}</span>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.9rem;">${model} • ${color}</h6>
                            <small class="text-muted d-block">${evt.title}</small>
                            <div class="text-muted small mt-1" style="font-size: 11px;">
                                <i class="bi bi-calendar3 me-1"></i> ${dateRangeLabel}
                            </div>
                            <div class="text-dark small mt-1 fw-bold"><i class="bi bi-clock me-1"></i> ${timeRangeDisplay}</div>
                        </div>
                        <span class="badge rounded-pill" style="background-color: ${statusColor}; font-size: 10px;">${props.status}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top border-light">
                        <small class="text-muted fw-semibold">ID: #${evt.id}</small>
                        ${props.type === 'schedule'
                            ? '<span class="text-muted fw-bold" style="font-size: 11px;">Blocked period</span>'
                            : '<span class="text-primary fw-bold" style="font-size: 11px;">View details →</span>'}
                    </div>
                `;

                itemDiv.addEventListener('click', function() {
                    if (props.type === 'schedule') return;
                    dailyAgendaModal.hide();
                    redirectToDetails(evt.id);
                });

                container.appendChild(itemDiv);
            });

            dailyAgendaModal.show();
        },

        eventClick: function(info) {
            info.jsEvent.preventDefault();

            const props = info.event.extendedProps || {};
            if (props.type === 'schedule') {
                const dayDate = new Date(info.event.start);
                const formattedDate = dayDate.toLocaleDateString('en-US',
                    { month: 'short', day: 'numeric', year: 'numeric' });
                document.getElementById('agendaModalDateTitle').innerText = formattedDate;

                const container = document.getElementById('agendaModalContainer');
                container.innerHTML = `
                    <div class="card border-0 bg-light p-3 rounded-3 mb-2 shadow-sm agenda-item-card">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="timeline-indicator-badge bg-secondary bg-opacity-10 text-secondary d-inline-block mb-1">🚧 Schedule Block</span>
                                <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.9rem;">
                                    ${props.brand || 'Vehicle'} • ${props.color || 'N/A'}
                                </h6>
                                <div class="text-dark small mt-1 fw-bold">
                                    <i class="bi bi-tag me-1"></i> ${props.reason || 'Unavailable'}
                                </div>
                                ${props.notes ? `<div class="text-muted small mt-1">${props.notes}</div>` : ''}
                                ${props.created_by ? `<div class="text-muted small mt-1"><i class="bi bi-person-badge me-1"></i>Created by ${props.created_by}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
                dailyAgendaModal.show();
                return;
            }

            redirectToDetails(info.event.id);
        },

        datesSet: function(arg) {
            if (arg.view.type === 'listMonth') scheduleEnforce();
        }
    });

    calendar.render();

    // Lock mobile to list view and update toolbar when crossing the breakpoint.
    mobileMql.addEventListener('change', function(e) {
        isMobile = e.matches;
        calendar.setOption('headerToolbar', buildToolbar());
        if (isMobile && calendar.view.type !== 'listMonth') {
            calendar.changeView('listMonth');
        }
    });
});
</script>