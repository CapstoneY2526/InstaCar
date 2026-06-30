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

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<style>
    body { background-color: #f8fafc; font-family: 'Poppins', sans-serif; }
    .main-content { min-height: 100vh; }
    
    .calendar-card {
        background: white;
        border-radius: 1.25rem;
        border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        padding: 1rem;
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

<div class="container-fluid">
    <div class="row">
        <div class="col-md-auto p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-0">Booking Schedule</h3>
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