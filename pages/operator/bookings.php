<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth Check - Only Admin and Operator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'operator') {
    $_SESSION['error'] = "Unauthorized access.";
    ?>
    <script>window.location.href = "../../index.php";</script>
    <?php
    exit();
}

$pageTitle = 'Vehicle Reservations';
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get filter from URL
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'All';

// Helper function to format numbers dynamically for mobile screens
function abbreviate_number($num) {
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    }
    if ($num >= 10000) {
        return round($num / 1000, 1) . 'K';
    }
    return number_format($num);
}

// Get booking stats
$statsQuery = "SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN b.status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN b.status IN ('Confirmed', 'Approved') THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN b.status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN b.status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
    COALESCE(SUM(b.total_price), 0) as total_revenue
FROM bookings b 
JOIN cars c ON b.car_id = c.id 
WHERE b.booking_type = 'online'";

if ($user_role === 'operator') {
    $statsQuery .= " AND c.user_id = $current_user_id";
}

$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Single query that combines all conditions
$query = "SELECT b.*, c.brand, c.model, c.plate_number, u.name as customer_name, c.user_id as car_owner_id
          FROM bookings b 
          JOIN cars c ON b.car_id = c.id 
          LEFT JOIN users u ON b.user_id = u.id 
          WHERE b.booking_type = 'online' ";

// Role-based filtering
if ($user_role === 'operator') {
    $query .= " AND c.user_id = ? ";
}

// Status filtering
if ($filter !== 'All') {
    $query .= " AND b.status = ? ";
}

$query .= " ORDER BY b.created_at DESC";

// Execute query
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    // Bind parameters based on what filters are active
    if ($user_role === 'operator' && $filter !== 'All') {
        mysqli_stmt_bind_param($stmt, "is", $current_user_id, $filter);
    } elseif ($user_role === 'operator') {
        mysqli_stmt_bind_param($stmt, "i", $current_user_id);
    } elseif ($filter !== 'All') {
        mysqli_stmt_bind_param($stmt, "s", $filter);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $bookings = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $bookings[] = $row;
    }
} else {
    die("Preparation failed: " . mysqli_error($conn));
}

// Check if there are any online bookings at all (for empty state message)
$check_sql = "SELECT COUNT(*) as total FROM bookings WHERE booking_type = 'online'";
$check_result = mysqli_query($conn, $check_sql);
$check_row = mysqli_fetch_assoc($check_result);
$has_online_bookings = ($check_row['total'] > 0);
$no_online_bookings = !$has_online_bookings;
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    body {
        background-color: #f8fafc;
        font-family: 'Poppins', sans-serif;
    }

    /* Stats Boxes Fixed Overflow Configurations */
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 1rem 1.25rem;
        transition: all 0.2s ease;
        border: 1px solid #e2e8f0;
        height: 100%;
        min-width: 0; /* Prevents overflow behavior inside flexboxes */
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    }
    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }
    .stat-value {
        font-size: 1.15rem;
        font-weight: 800;
        line-height: 1.2;
        word-break: break-all;
    }
    .stat-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        font-weight: 600;
    }
    .stat-sub {
        font-size: 0.7rem;
        color: #64748b;
    }

    /* Custom Live Filtering Control Panel Stylings */
    .custom-control-box {
        display:flex;
        background-color: #ffffff;
        border: 1px solid gray;
        border-radius: 14px;
        padding: 1rem;
    }
    .control-search-input {
        position: relative;
    }
    .control-search-input i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }
    .control-search-input input {
        padding-left: 36px;
        border-radius: 8px;
    }
    .limiter-select {
        max-width: 85px;
        border-radius: 8px;
    }

    /* Tablet and Desktop breakpoint adaptations */
    @media (min-width: 576px) {
        .stat-value { font-size: 1.45rem; word-break: normal; }
        .stat-icon { width: 48px; height: 48px; font-size: 1.5rem; }
        .stat-label { font-size: 0.7rem; }
        .stat-sub { font-size: 0.75rem; }
    }

    /* Mobile-First Table Styling */
    @media (max-width: 768px) {
        .responsive-table thead {
            display: none;
        }

        .responsive-table tr {
            display: block;
            margin-bottom: 1.5rem;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 1rem;
        }

        .responsive-table td {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0 !important;
            border: none !important;
            font-size: 0.9rem;
        }

        .responsive-table td::before {
            content: attr(data-label);
            font-weight: 700;
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .responsive-table td.full-width-mobile {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    /* Modern Tab Styling */
    .status-tabs .nav-link {
        color: #64748b;
        font-weight: 500;
        border: none;
        padding: 10px 20px;
        border-bottom: 2px solid transparent;
    }

    .status-tabs .nav-link.active {
        color: #0d6efd;
        background: none;
        border-bottom: 2px solid #0d6efd;
    }

    /* Empty State Styling */
    .empty-state-wrapper {
        padding: 3rem 1.5rem;
        min-height: 400px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .empty-state {
        text-align: center;
        max-width: 400px;
        margin: 0 auto;
        animation: fadeInUp 0.5s ease-out;
    }

    .empty-state-icon {
        width: 100px;
        height: 100px;
        margin: 0 auto 1.5rem;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .empty-state-icon i {
        font-size: 3.5rem;
        color: #94a3b8;
    }

    .empty-state-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.75rem;
    }

    .empty-state-message {
        color: #64748b;
        font-size: 0.9375rem;
        margin-bottom: 1.5rem;
        line-height: 1.5;
    }

    .empty-state-actions {
        display: flex;
        gap: 0.75rem;
        justify-content: center;
        flex-wrap: wrap;
    }

    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Revenue Card Special Styling */
    .revenue-card .stat-value {
        color: #059669;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <!-- Dashboard Sidebar Structure Alignment -->
        <div class="col-md-2 p-0 sidebar-container">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <!-- Dashboard Content Body Alignment -->
        <div class="col-md-10 p-0 d-flex flex-column main-content" style="background: #f8fafc; min-height: 100vh;">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">
                <div class="mb-4">
                    <h3 class="fw-bold mb-0">Vehicle Reservations</h3>
                    <p class="text-muted mb-0 small d-none d-sm-block">Manage and track your fleet's activity</p>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-2 gap-sm-3 min-w-0">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <div class="text-truncate">
                                    <div class="stat-value text-truncate"><?= abbreviate_number($stats['total_bookings'] ?? 0) ?></div>
                                    <div class="stat-label text-truncate">Total Bookings</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-2 gap-sm-3 min-w-0">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div class="text-truncate">
                                    <div class="stat-value text-truncate"><?= abbreviate_number($stats['pending'] ?? 0) ?></div>
                                    <div class="stat-label text-truncate">Pending</div>
                                    <div class="stat-sub d-none d-sm-block text-truncate">Awaiting confirmation</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center gap-2 gap-sm-3 min-w-0">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                                <div class="text-truncate">
                                    <div class="stat-value text-truncate"><?= abbreviate_number(($stats['confirmed'] ?? 0) + ($stats['completed'] ?? 0)) ?></div>
                                    <div class="stat-label text-truncate">Active/Done</div>
                                    <div class="stat-sub d-none d-sm-block text-truncate"><?= number_format($stats['confirmed'] ?? 0) ?> conf.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-xl-3">
                        <div class="stat-card revenue-card">
                            <div class="d-flex align-items-center gap-2 gap-sm-3 min-w-0">
                                <div class="stat-icon bg-success bg-opacity-10 text-success">
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                                <div class="text-truncate">
                                    <div class="stat-value text-truncate">₱<?= abbreviate_number($stats['total_revenue'] ?? 0) ?></div>
                                    <div class="stat-label text-truncate">Total Revenue</div>
                                    <div class="stat-sub d-none d-sm-block text-truncate">From online</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="custom-control-box mb-4 shadow-sm">
                    <div class="d-flex flex-row align-items-center justify-content-between gap-3 flex-nowrap">
                        <!-- Entries Limiter Box -->
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <span class="text-muted small text-nowrap">Show entries:</span>
                            <select id="entryLimiter" class="form-select form-select-sm limiter-select mb-0">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="all">All</option>
                            </select>
                        </div>
                        
                        <!-- Live Filter Search Box -->
                        <div class="flex-grow-1">
                            <div class="control-search-input">
                                <i class="bi bi-search"></i>
                                <input type="text" id="customSearchInput" class="form-select-sm form-control" placeholder="Search customer, vehicle, ID or status...">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="table-responsive-md">
                            <?php if (empty($bookings)): ?>
                                <div class="empty-state-wrapper">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="bi bi-calendar-x"></i>
                                        </div>
                                        <h5 class="empty-state-title">No Online Bookings Found</h5>
                                        <p class="empty-state-message">
                                            <?php if (isset($no_online_bookings) && $no_online_bookings): ?>
                                                There are no online bookings in the system yet.
                                            <?php else: ?>
                                                No <span class="badge bg-secondary mx-1"><?= htmlspecialchars($filter) ?></span>
                                                bookings found for online reservations.
                                            <?php endif; ?>
                                        </p>
                                        <div class="empty-state-actions">
                                            <a href="?filter=All" class="btn btn-primary btn-sm">
                                                <i class="bi bi-calendar-check"></i> View All Bookings
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <table class="table table-hover align-middle mb-0 responsive-table">
                                    <thead class="bg-light small text-uppercase fw-bold">
                                        <tr>
                                            <th class="ps-4">Booking</th>
                                            <th>Customer</th>
                                            <th>Vehicle</th>
                                            <th>Rental Period</th>
                                            <th>Total</th>
                                            <th class="text-end pe-4">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reservationsTableBody">
                                        <?php foreach ($bookings as $b): ?>
                                            <tr class="reservation-row">
                                                <td class="ps-4" data-label="ID">
                                                    <span class="fw-bold text-dark search-target">#BK-<?= $b['id'] ?></span>
                                                </td>
                                                <td data-label="Customer">
                                                    <div class="fw-bold search-target">
                                                        <?= !empty($b['customer_name']) ? htmlspecialchars($b['customer_name']) : htmlspecialchars($b['guest_name']) ?>
                                                    </div>
                                                    <span class="badge bg-light text-muted border"
                                                        style="font-size: 9px;"><?= strtoupper($b['booking_type']) ?></span>
                                                </td>
                                                <td data-label="Vehicle">
                                                    <div class="fw-bold text-primary search-target"><?= htmlspecialchars($b['brand']) ?> <?= htmlspecialchars($b['model']) ?></div>
                                                    <div class="text-muted small search-target"><?= htmlspecialchars($b['plate_number']) ?></div>
                                                </td>
                                                <td data-label="Period">
                                                    <div class="small fw-medium">
                                                        <?= date('M d, Y', strtotime($b['start_date'])) ?></div>
                                                    <div class="text-muted x-small">to
                                                        <?= date('M d, Y', strtotime($b['end_date'])) ?></div>
                                                </td>
                                                <td data-label="Total">
                                                    <span class="fw-bold">₱<?= number_format($b['total_price'], 2) ?></span>
                                                </td>
                                                <td class="text-end pe-4" data-label="Status">
                                                    <?php
                                                    $statusClass = match ($b['status']) {
                                                        'Pending' => 'bg-warning text-dark',
                                                        'Confirmed' => 'bg-primary',
                                                        'Approved' => 'bg-primary',
                                                        'Completed' => 'bg-success',
                                                        'Cancelled' => 'bg-danger',
                                                        default => 'bg-secondary'
                                                    };
                                                    ?>
                                                    <span class="badge <?= $statusClass ?> rounded-pill px-3 py-2 search-target"><?= $b['status'] ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-auto">
                <?php require_once __DIR__ . '/../components/footer.php'; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('customSearchInput');
    const limiterSelect = document.getElementById('entryLimiter');
    const tableBody = document.getElementById('reservationsTableBody');
    
    if (!tableBody) return;
    
    const rows = Array.from(tableBody.querySelectorAll('.reservation-row'));

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const limitValue = limiterSelect.value;
        
        let visibleCount = 0;

        rows.forEach(row => {
            const targetFields = row.querySelectorAll('.search-target');
            let matchFound = false;

            targetFields.forEach(field => {
                if (field.textContent.toLowerCase().includes(searchTerm)) {
                    matchFound = true;
                }
            });

            if (matchFound) {
                if (limitValue === 'all' || visibleCount < parseInt(limitValue)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            } else {
                row.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('input', filterTable);
    limiterSelect.addEventListener('change', filterTable);
    
    filterTable();
});
</script>