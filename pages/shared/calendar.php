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
$pageTitle = 'Booking Calendar';

require_once __DIR__ . '/../components/head.php'; 
?>

<!-- Google Font: Inter -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

    /* ========================================================
       BASE LIGHT/DARK LAYOUT & COMPONENT OVERRIDES
       ======================================================== */
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
        max-height: 120px;
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
    
    .fc-event {
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
       DARK MODE COMPLETE OVERRIDES & FULLCALENDAR CONTRAST FIXES
       ======================================================== */
    body.dark-mode,
    body.dark-mode .main-content {
        background-color: #0a0a0a !important;
        color: #f1f5f9 !important;
    }

    /* Header & Footer Components */
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

    /* Calendar Card Container */
    body.dark-mode .calendar-card {
        background-color: #141414 !important;
        border-color: #27272a !important;
        box-shadow: 0 4px 20px rgba(0,0,0,0.5) !important;
    }

    body.dark-mode .fc .fc-toolbar-title {
        color: #ffffff !important;
    }

    /* Grid & Borders */
    body.dark-mode .fc-theme-standard td,
    body.dark-mode .fc-theme-standard th,
    body.dark-mode .fc-theme-standard .fc-scrollgrid {
        border-color: #27272a !important;
    }

    /* Header Days Bar (Sun, Mon, Tue...) Dark Overrides */
    body.dark-mode .fc .fc-col-header,
    body.dark-mode .fc .fc-col-header-cell {
        background-color: #1f1f1f !important;
    }

    body.dark-mode .fc .fc-col-header-cell-cushion {
        color: #ffffff !important;
        font-weight: 600 !important;
    }

    /* Day Numbers */
    body.dark-mode .fc .fc-daygrid-day-number {
        color: #ffcc00 !important;
    }

    body.dark-mode .view-more-hint {
        color: #a1a1aa !important;
    }

    /* Active Today Highlight */
    body.dark-mode .fc-day-today {
        background: #1f1f1f !important;
    }

    body.dark-mode .fc-day-today .fc-daygrid-day-number {
        background: #ffcc00 !important;
        color: #000000 !important;
        font-weight: bold !important;
    }

    /* Toolbar Buttons */
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

    /* Event Text Visibility Fix (Month & List View) */
    body.dark-mode .fc-event-main,
    body.dark-mode .fc-event-main-frame,
    body.dark-mode .fc-event-title,
    body.dark-mode .fc-event-time {
        color: #ffffff !important;
        font-weight: 500 !important;
    }

    /* FullCalendar List View Dark Overrides */
    body.dark-mode .fc-list,
    body.dark-mode .fc-list-table {
        background-color: #141414 !important;
        border-color: #27272a !important;
    }

    body.dark-mode .fc-list-day-cushion,
    body.dark-mode .fc-cell-shaded {
        background-color: #1f1f1f !important;
    }

    body.dark-mode .fc-list-day-text,
    body.dark-mode .fc-list-day-side-text {
        color: #ffcc00 !important;
        font-weight: 700 !important;
    }

    body.dark-mode .fc-list-event:hover td {
        background-color: #2a2a2a !important;
    }

    body.dark-mode .fc-list-event-title,
    body.dark-mode .fc-list-event-title a,
    body.dark-mode .fc-list-event-time {
        color: #ffffff !important;
    }

    body.dark-mode .fc-list-empty {
        background-color: #141414 !important;
        color: #a1a1aa !important;
    }

    body.dark-mode .fc-daygrid-more-link {
        color: #ffcc00 !important;
    }

    /* Popover Styling */
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

    /* Dynamic Agenda & Modal Dark Overrides */
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

    /* Text & Typography */
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

    /* Modals */
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

    /* Buttons */
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
       STATUS-BASED EVENT COLORS (matches legend: Pending / Approved / Completed / Cancelled)
       ======================================================== */
    .fc-event.status-pending {
        background-color: #f59e0b !important;
        border-color: #f59e0b !important;
    }
    .fc-event.status-pending .fc-event-title,
    .fc-event.status-pending .fc-event-main-frame,
    .fc-event.status-pending .fc-event-time {
        color: #1f1300 !important;
    }

    .fc-event.status-approved {
        background-color: #10b981 !important;
        border-color: #10b981 !important;
    }
    .fc-event.status-approved .fc-event-title,
    .fc-event.status-approved .fc-event-main-frame,
    .fc-event.status-approved .fc-event-time {
        color: #ffffff !important;
    }

    .fc-event.status-completed {
        background-color: #3b82f6 !important;
        border-color: #3b82f6 !important;
    }
    .fc-event.status-completed .fc-event-title,
    .fc-event.status-completed .fc-event-main-frame,
    .fc-event.status-completed .fc-event-time {
        color: #ffffff !important;
    }

    .fc-event.status-cancelled {
        background-color: #ef4444 !important;
        border-color: #ef4444 !important;
    }
    .fc-event.status-cancelled .fc-event-title,
    .fc-event.status-cancelled .fc-event-main-frame,
    .fc-event.status-cancelled .fc-event-time {
        color: #ffffff !important;
    }

    /* Dim cancelled bookings slightly so active ones stand out, but keep readable */
    .fc-event.status-cancelled { opacity: 0.85; }

    /* List view dot bullet + time text should also carry the status color */
    .fc-list-event.status-pending .fc-list-event-dot { border-color: #f59e0b !important; }
    .fc-list-event.status-approved .fc-list-event-dot { border-color: #10b981 !important; }
    .fc-list-event.status-completed .fc-list-event-dot { border-color: #3b82f6 !important; }
    .fc-list-event.status-cancelled .fc-list-event-dot { border-color: #ef4444 !important; }

    .fc-list-event.status-pending .status-chip { background:#f59e0b; color:#1f1300; }
    .fc-list-event.status-approved .status-chip { background:#10b981; color:#fff; }
    .fc-list-event.status-completed .status-chip { background:#3b82f6; color:#fff; }
    .fc-list-event.status-cancelled .status-chip { background:#ef4444; color:#fff; }

    /* ========================================================
       LIST VIEW — polished, responsive styling (mobile / tablet / laptop)
       ======================================================== */
    .fc-list {
        border-radius: 0.9rem !important;
        overflow: hidden;
        border: 1px solid var(--brand-border, #e2e8f0) !important;
    }

    .fc-list-day-cushion {
        background: #f8fafc !important;
        padding: 10px 16px !important;
    }

    .fc-list-day-text,
    .fc-list-day-side-text {
        font-weight: 700 !important;
        color: #0f172a !important;
        text-decoration: none !important;
        font-size: 0.9rem;
    }

    .fc-list-table td {
        padding: 10px 14px !important;
        vertical-align: middle !important;
        border-color: var(--brand-border, #f1f5f9) !important;
    }

    .fc-list-event {
        cursor: pointer;
        transition: background-color 0.15s ease;
    }

    .fc-list-event:hover td {
        background-color: #f8fafc;
    }

    .fc-list-event-time {
        font-weight: 700 !important;
        font-size: 0.8rem !important;
        color: #475569 !important;
        white-space: nowrap;
    }

    .fc-list-event-dot {
        border-width: 5px !important;
    }

    .fc-list-event-title {
        font-weight: 500 !important;
        font-size: 0.85rem !important;
    }

    .fc-list-event-title .list-event-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
    }

    .fc-list-event-title .list-event-main {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }

    .fc-list-event-title .list-event-model {
        font-weight: 700;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .fc-list-event-title .list-event-id {
        font-size: 0.7rem;
        font-weight: 600;
        color: #94a3b8;
    }

    .fc-list-event-title .status-chip {
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 3px 9px;
        border-radius: 999px;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .fc-list-empty {
        padding: 3rem 1rem !important;
        text-align: center;
        font-weight: 600;
        color: #94a3b8 !important;
        background-color: #ffffff !important;
    }

    body.dark-mode .fc-list-event:hover td {
        background-color: #2a2a2a !important;
    }

    body.dark-mode .fc-list-event-title .list-event-model {
        color: #ffffff !important;
    }

    body.dark-mode .fc-list-event-title .list-event-id {
        color: #71717a !important;
    }

    body.dark-mode .fc-list-empty {
        background-color: #141414 !important;
    }

    /* Tablet */
    @media (max-width: 992px) {
        .fc-list-day-cushion { padding: 8px 12px !important; }
        .fc-list-day-text, .fc-list-day-side-text { font-size: 0.82rem; }
        .fc-list-event-time { font-size: 0.72rem !important; }
        .fc-list-event-title { font-size: 0.8rem !important; }
    }

    /* Mobile */
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
                            <?= ($user_role === 'admin') ? "Full fleet overview." : "Your assigned vehicle bookings." ?>
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
    var userId = '<?= $user_id ?>';

    function redirectToDetails(eventId) {
        const detailPage = (userRole === 'operator') ? 'my_booking_details.php' : 'booking_details.php';
        window.location.href = detailPage + "?id=" + eventId;
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

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next',
            center: 'title',
            right: 'dayGridMonth,listMonth'
        },
        dayMaxEvents: 4,
        moreLinkClick: "popover", 
        events: function(fetchInfo, successCallback, failureCallback) {
            // Build the URL with user_id filter for operators
            let url = 'process/fetch_bookings.php';
            if (userRole === 'operator') {
                url += '?user_id=' + userId;
            }
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    successCallback(data);
                })
                .catch(error => {
                    console.error('Error fetching bookings:', error);
                    failureCallback(error);
                });
        },
        height: 650,
        editable: false,
        selectable: true,
        eventOrder: '-duration,start',

        eventClassNames: function(arg) {
            const status = (arg.event.extendedProps.status || '').toLowerCase();
            return status ? ['status-' + status] : [];
        },

        eventContent: function(arg) {
            const props = arg.event.extendedProps || {};
            const model = props.model || 'Vehicle';
            const color = props.color || 'N/A';
            const status = props.status || '';

            const rawStart = props.raw_start ? new Date(props.raw_start) : arg.event.start;
            const rawEnd   = props.raw_end ? new Date(props.raw_end) : arg.event.end;

            const isStartSegment = arg.isStart;
            const isEndSegment   = arg.isEnd;

            const releaseTime = formatToMilitaryTime(rawStart);
            const returnTime  = formatToMilitaryTime(rawEnd);

            let timeStr = '';

            if (isStartSegment && isEndSegment) {
                timeStr = `${releaseTime} - ${returnTime}`;
            } else if (isStartSegment) {
                timeStr = `${releaseTime} - 00:00`;
            } else if (isEndSegment) {
                timeStr = `00:00 - ${returnTime}`;
            } else {
                timeStr = `00:00 - 00:00`;
            }

            let customEl = document.createElement('div');
            customEl.className = 'fc-event-main-frame';

            if (arg.view.type === 'listMonth') {
                customEl.innerHTML = `
                    <div class="list-event-row">
                        <div class="list-event-main">
                            <span class="list-event-model">${model} • ${color}</span>
                            <span class="list-event-id">ID #${arg.event.id}${timeStr ? ' • ' + timeStr : ''}</span>
                        </div>
                        ${status ? `<span class="status-chip">${status}</span>` : ''}
                    </div>`;
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
            const clickedDateStr = info.dateStr; 
            const formattedDateOptions = { month: 'short', day: 'numeric', year: 'numeric' };
            document.getElementById('agendaModalDateTitle').innerText = info.date.toLocaleDateString('en-US', formattedDateOptions);

            const targetStart = new Date(info.date);
            targetStart.setHours(0, 0, 0, 0);

            const targetEnd = new Date(info.date);
            targetEnd.setHours(23, 59, 59, 999);

            const allEvents = calendar.getEvents();
            
            const filteredEvents = allEvents.filter(evt => {
                let start = new Date(evt.start);
                let end = evt.end ? new Date(evt.end) : new Date(start);
                return (start <= targetEnd && end > targetStart);
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
                let start = props.raw_start ? new Date(props.raw_start) : new Date(evt.start);
                let end = props.raw_end ? new Date(props.raw_end) : (evt.end ? new Date(evt.end) : new Date(start));

                const targetIsoStr = clickedDateStr;
                const startIsoStr = toLocalIsoString(start);
                const endIsoStr = toLocalIsoString(end);

                let actionLabel = '';
                let actionBadgeClass = '';
                let timeRangeDisplay = '';

                const startTimeMil = formatToMilitaryTime(start) || '00:00';
                const endTimeMil = formatToMilitaryTime(end) || '00:00';

                if (startIsoStr === endIsoStr) {
                    actionLabel = 'Same-Day Rental';
                    actionBadgeClass = 'bg-dark text-white';
                    timeRangeDisplay = `${startTimeMil} - ${endTimeMil}`;
                } else if (targetIsoStr === startIsoStr) {
                    actionLabel = '🛬 Vehicle Pickup';
                    actionBadgeClass = 'bg-primary bg-opacity-10 text-primary';
                    timeRangeDisplay = `${startTimeMil} - 00:00`;
                } else if (targetIsoStr === endIsoStr) {
                    actionLabel = '🛫 Vehicle Return';
                    actionBadgeClass = 'bg-danger bg-opacity-10 text-danger';
                    timeRangeDisplay = `00:00 - ${endTimeMil}`;
                } else {
                    actionLabel = '🚗 Booked Out';
                    actionBadgeClass = 'bg-secondary bg-opacity-10 text-secondary';
                    timeRangeDisplay = `All Day`;
                }

                const model = props.model || 'Vehicle';
                const color = props.color || 'N/A';

                const colors = {
                    'Approved': '#10b981',
                    'Pending': '#f59e0b',
                    'Completed': '#3b82f6',
                    'Cancelled': '#ef4444'
                };
                const statusColor = colors[props.status] || '#64748b';

                const itemDiv = document.createElement('div');
                itemDiv.className = "card border-0 bg-light p-3 rounded-3 mb-2 shadow-sm text-start agenda-item-card";
                itemDiv.style.cursor = "pointer";
                itemDiv.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="timeline-indicator-badge ${actionBadgeClass} d-inline-block mb-1">${actionLabel}</span>
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.9rem;">${model} • ${color}</h6>
                            <small class="text-muted d-block">${evt.title}</small>
                            <div class="text-dark small mt-1 fw-bold"><i class="bi bi-clock me-1"></i> ${timeRangeDisplay}</div>
                        </div>
                        <span class="badge rounded-pill" style="background-color: ${statusColor}; font-size: 10px;">${props.status}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top border-light">
                        <small class="text-muted fw-semibold">ID: #${evt.id}</small>
                        <span class="text-primary fw-bold" style="font-size: 11px;">View details →</span>
                    </div>
                `;

                itemDiv.addEventListener('click', function() {
                    dailyAgendaModal.hide(); 
                    redirectToDetails(evt.id);
                });

                container.appendChild(itemDiv);
            });

            dailyAgendaModal.show();
        },

        eventClick: function(info) {
            info.jsEvent.preventDefault(); 
            redirectToDetails(info.event.id);
        }
    });

    calendar.render();
});
</script>