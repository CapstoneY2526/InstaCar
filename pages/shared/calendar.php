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
    }

    :root {
        --fc-today-bg-color: #f1f5f9;
        --fc-button-bg-color: #0f172a;
        --fc-button-hover-bg-color: #1e293b;
        --fc-button-active-bg-color: #0f172a;
        --fc-border-color: #f1f5f9;
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
        padding: 3px 8px !important;
        font-size: 0.75rem !important;
        border-radius: 6px !important;
        cursor: pointer;
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
        color: #f1f5f9 !important;
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
                            <?= ($user_role === 'admin') ? "Full fleet overview." : "Your personal booking schedule." ?>
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

<div class="modal fade" id="eventModal" tabindex="-1" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-0 text-center">
                <div class="icon-shape bg-primary-subtle text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; border-radius: 20px;">
                    <i class="bi bi-calendar-event fs-2"></i>
                </div>
                <h4 class="fw-bold mb-1" id="modalCar">Vehicle</h4>
                <p class="text-muted mb-4" id="modalCustomer">Client Name</p>

                <div class="d-flex justify-content-between bg-light p-3 rounded-3 mb-4 text-start">
                    <div>
                        <small class="text-muted d-block uppercase fw-bold" style="font-size: 0.6rem;">STATUS</small>
                        <span id="modalStatus" class="fw-bold"></span>
                    </div>
                    <div class="text-end">
                        <small class="text-muted d-block uppercase fw-bold" style="font-size: 0.6rem;">BOOKING ID</small>
                        <span id="modalId" class="fw-bold"></span>
                    </div>
                </div>

                <div class="d-grid">
                    <a id="modalLink" href="#" class="btn btn-dark py-2 fw-bold rounded-3">View Details</a>
                </div>
            </div>
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
    var eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
    var dailyAgendaModal = new bootstrap.Modal(document.getElementById('dailyAgendaModal'));

    var isMobile = window.innerWidth < 768;

    // Helper to format local Date objects to 'YYYY-MM-DD' cleanly without timezone shifting
    function toLocalIsoString(date) {
        const yyyy = date.getFullYear();
        const mm = String(date.getMonth() + 1).padStart(2, '0');
        const dd = String(date.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    // Helper function to format human-readable times (e.g., "02:30 PM")
    function formatToLocalTime(date) {
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }

    // Helper function to populate and show the detailed event modal
    function showEventDetails(eventId, title, extendedProps) {
        document.getElementById('modalCar').innerText = extendedProps.car || 'N/A';
        document.getElementById('modalCustomer').innerText = title;
        document.getElementById('modalId').innerText = "#" + eventId;
        
        const status = extendedProps.status;
        const statusEl = document.getElementById('modalStatus');
        statusEl.innerText = status;
        
        const colors = {
            'Approved': '#10b981',
            'Pending': '#f59e0b',
            'Completed': '#3b82f6',
            'Cancelled': '#ef4444'
        };
        statusEl.style.color = colors[status] || '#64748b';

        const role = '<?= $user_role ?>';
        const detailPage = (role === 'user') ? 'my_booking_details.php' : 'booking_details.php';
        document.getElementById('modalLink').href = detailPage + "?id=" + eventId;
        
        eventModal.show();
    }

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next',
            center: 'title',
            right: isMobile ? '' : 'dayGridMonth,listMonth'
        },
        
        dayMaxEvents: isMobile ? 2 : 5, 
        moreLinkClick: "popover", 
        events: 'process/fetch_bookings.php',
        height: 'auto',
        editable: false,
        selectable: true,
        
        moreLinkContent: function(args) {
            return '+ ' + args.num + ' more';
        },

        dateClick: function(info) {
            const clickedDateStr = info.dateStr; // Standard 'YYYY-MM-DD' from click context
            const formattedDateOptions = { month: 'short', day: 'numeric', year: 'numeric' };
            document.getElementById('agendaModalDateTitle').innerText = info.date.toLocaleDateString('en-US', formattedDateOptions);

            // Establish strict local timeline points for accurate matching bounds
            const targetStart = new Date(info.date);
            targetStart.setHours(0, 0, 0, 0);

            const targetEnd = new Date(info.date);
            targetEnd.setHours(23, 59, 59, 999);

            const allEvents = calendar.getEvents();
            
            // Match overlapping events utilizing FullCalendar's exclusive end-date schema
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
                const props = evt.extendedProps;
                
                let start = new Date(evt.start);
                let end = evt.end ? new Date(evt.end) : new Date(start);

                // Build timezone-safe local strings for comparative tracking labels
                const targetIsoStr = clickedDateStr;
                const startIsoStr = toLocalIsoString(start);
                
                // For exclusive display matching, find the active visual last day
                let lastDisplayDay = new Date(end);
                if (evt.end) { 
                    lastDisplayDay.setDate(lastDisplayDay.getDate() - 1); 
                }
                const endIsoStr = toLocalIsoString(lastDisplayDay);

                let actionLabel = '';
                let actionBadgeClass = '';
                let timestampDisplay = '';

                // Extract exact localized strings for pickup and return actions
                const startTimeStr = formatToLocalTime(start);
                const endTimeStr = formatToLocalTime(end);

                if (startIsoStr === endIsoStr) {
                    actionLabel = 'Same-Day Rental';
                    actionBadgeClass = 'bg-dark text-white';
                    timestampDisplay = `<div class="text-muted small mt-1"><i class="bi bi-clock me-1"></i> ${startTimeStr} - ${endTimeStr}</div>`;
                } else if (targetIsoStr === startIsoStr) {
                    actionLabel = '🛬 Vehicle Pickup';
                    actionBadgeClass = 'bg-primary bg-opacity-10 text-primary';
                    timestampDisplay = `<div class="text-primary small mt-1 fw-semibold"><i class="bi bi-clock me-1"></i> Pickup Time: ${startTimeStr}</div>`;
                } else if (targetIsoStr === endIsoStr) {
                    actionLabel = '🛫 Vehicle Return';
                    actionBadgeClass = 'bg-danger bg-opacity-10 text-danger';
                    timestampDisplay = `<div class="text-danger small mt-1 fw-semibold"><i class="bi bi-clock me-1"></i> Return Time: ${endTimeStr}</div>`;
                } else {
                    actionLabel = '🚗 Booked (All Time Out)';
                    actionBadgeClass = 'bg-secondary bg-opacity-10 text-secondary';
                    timestampDisplay = `<div class="text-muted small mt-1"><i class="bi bi-calendar-range me-1"></i> Mid-rent cycle (Out all day)</div>`;
                }

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
                            <h6 class="fw-bold mb-0 text-dark" style="font-size: 0.9rem;">${props.car || 'Vehicle'}</h6>
                            <small class="text-muted d-block">${evt.title}</small>
                            ${timestampDisplay}
                        </div>
                        <span class="badge rounded-pill" style="background-color: ${statusColor}; font-size: 10px;">${props.status}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top border-light">
                        <small class="text-muted fw-semibold">ID: #${evt.id}</small>
                        <span class="text-primary fw-bold" style="font-size: 11px;">Tap to view →</span>
                    </div>
                `;

                itemDiv.addEventListener('click', function() {
                    dailyAgendaModal.hide(); 
                    showEventDetails(evt.id, evt.title, props);
                });

                container.appendChild(itemDiv);
            });

            dailyAgendaModal.show();
        },

        eventClick: function(info) {
            info.jsEvent.preventDefault(); 
            showEventDetails(info.event.id, info.event.title, info.event.extendedProps);
        }
    });

    calendar.render();
});
</script>