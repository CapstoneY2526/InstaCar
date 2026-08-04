<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth Check - Allow customers, admin, and operator
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to continue.";
    ?>
    <script>window.location.href = "../../index.php";</script>
    <?php
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

// Build query with filters - pulling all operational statuses so they remain available on the gallery
$query = "SELECT * FROM cars WHERE status IN ('Available', 'Active', 'Rented')";

// Type filter
if ($type_filter !== 'All') {
    $type_filter_safe = mysqli_real_escape_string($conn, $type_filter);
    $query .= " AND type = '$type_filter_safe'";
}

// Status filter
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

// Define relative path to the QR image file
$qr_file_path = __DIR__ . '/public/assets/images/qr_code_payment.png';

// Generate path with version parameter to prevent browser caching
if (file_exists($qr_file_path)) {
    $qr_image_src = "public/assets/images/qr_code_payment.png?v=" . filemtime($qr_file_path);
} else {
    // Fallback if image file doesn't exist yet
    $qr_image_src = "public/assets/images/qr_code_payment.png?v=" . time();
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    .hover-card {
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .hover-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 30px -10px rgba(0,0,0,0.15) !important;
    }
    
    .transition-all {
        transition: all 0.3s ease;
    }
    
    /* Filter Section Styling */
    .filter-section {
        background: white;
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
        color: #64748b;
        margin-bottom: 0.5rem;
    }
    
    .filter-select {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        background-color: white;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .filter-select:hover {
        border-color: #0d6efd;
    }
    
    .filter-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
        outline: none;
    }
    
    .reset-filter {
        border-radius: 12px;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
    }
    
    /* Active filter badges */
    .active-filters {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }
    
    .filter-badge {
        background: #eef2ff;
        color: #0d6efd;
        border-radius: 20px;
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .filter-badge a {
        color: #0d6efd;
        text-decoration: none;
        font-weight: 700;
    }
    
    .filter-badge a:hover {
        color: #dc2626;
    }
    
    /* Results count */
    .results-count {
        font-size: 0.875rem;
        color: #64748b;
        margin-top: 0.5rem;
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
        background: #0d6efd;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.25rem 0.75rem;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .calendar-nav button:hover {
        background: #0b5ed7;
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.25rem;
        text-align: center;
    }

    .calendar-header {
        font-weight: 600;
        color: #64748b;
        padding: 0.5rem;
        font-size: 0.75rem;
        text-transform: uppercase;
    }

    .calendar-day {
        padding: 0.5rem;
        border-radius: 8px;
        font-size: 0.875rem;
        transition: all 0.2s ease;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 38px;
    }

    .calendar-day.available {
        background: #198754;
        color: white;
        cursor: pointer;
    }

    .calendar-day.available:hover {
        background: #157347;
        color: white;
        transform: scale(1.05);
    }

    .calendar-day.booked {
        background: #dc3545;
        color: white;
        cursor: not-allowed;
        opacity: 0.7;
    }

    .calendar-day.selected {
        background: #0d6efd !important;
        color: white !important;
    }

    .calendar-day.today {
        border: 2px solid #0d6efd;
        font-weight: bold;
    }

    .calendar-day:empty, .calendar-day.empty-day {
        background: transparent !important;
        cursor: default !important;
        border: none !important;
    }

    /* Mobile Responsive UI Overrides for Terms/Agreement Modal */
    @media (max-width: 576px) {
        #termsModal .modal-body {
            padding: 1rem !important;
        }
        
        #termsModal .agreement-text {
            padding: 1rem !important;
            height: 300px !important; /* Shorter layout constraints for mobile viewport */
            font-size: 0.8rem !important;
        }
        
        #termsModal .modal-footer {
            display: flex;
            flex-direction: column-reverse; /* Put main action button at the top */
            gap: 0.5rem;
            padding: 1rem !important;
        }
        
        #termsModal .modal-footer button {
            width: 100% !important; /* Scale element block edge-to-edge */
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
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>
        
        <div class="col-md-10 p-0 d-flex flex-column main-content" style="background: #f8fafc; min-height: 100vh;">
            <?php require_once __DIR__ . '/../components/header.php'; ?>
            
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-0">Vehicle Gallery</h3>
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
                        <div class="col-sm-6 col-md-6 col-lg-4 col-xl-3 car-item">
                            <div class="card h-100 border-0 shadow-sm rounded-4 hover-card transition-all" data-car-id="<?= $car['id'] ?>" style="position: relative; overflow: visible; z-index: 1;">
                                
                                <div class="position-relative rounded-top-4" style="height: 200px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); overflow: hidden;">
                                    <?php 
                                        // Explode comma-separated image strings into an array and clean up spaces
                                        $car_images = !empty($car['image_path']) ? array_filter(array_map('trim', explode(',', $car['image_path']))) : [];
                                        
                                        if (!empty($car_images)): 
                                            $primary_image = reset($car_images); // Grab the first photo as the cover thumbnail
                                            $image_src = "../../public/assets/images/cars/" . $primary_image;
                                    ?>
                                        <img src="<?= htmlspecialchars($image_src) ?>" 
                                            class="w-100 h-100 position-relative" 
                                            style="object-fit: cover; cursor: zoom-in; z-index: 10;" 
                                            alt="Car" 
                                            title="Click to view full stash gallery"
                                            onclick="openStashGalleryModal(<?= htmlspecialchars(json_encode(array_values($car_images))) ?>)">
                                    <?php else: ?>
                                        <div class="h-100 d-flex flex-column align-items-center justify-content-center text-white">
                                            <i class="bi bi-car-front-fill" style="font-size: 3.5rem; opacity: 0.5;"></i>
                                            <span class="small fw-bold mt-2">NO IMAGE</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="position-absolute top-0 end-0 m-3" style="z-index: 11;">
                                        <div class="bg-white rounded-3 px-3 py-1 shadow-sm">
                                            <span class="fw-bold text-primary">₱<?= number_format($car['price_24_hours']) ?></span>
                                            <small class="text-muted">/24h</small>
                                        </div>
                                    </div>
                                    
                                    <div class="position-absolute bottom-0 start-0 m-3" style="z-index: 11;">
                                        <span class="badge bg-dark bg-opacity-75 px-3 py-2 rounded-pill">
                                            <i class="bi bi-tag me-1"></i>
                                            <?= htmlspecialchars($car['type']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="position-absolute top-0 start-0 m-3" style="z-index: 11;">
                                        <span class="badge bg-success px-3 py-2 rounded-pill shadow-sm">
                                            <i class="bi bi-check-circle me-1"></i> Available
                                        </span>
                                    </div>
                                </div>

                                <div class="card-body p-3 p-md-4 d-flex flex-column justify-content-between" style="position: relative; z-index: -1;">
                                    <div>
                                        <div class="mb-3">
                                            <h5 class="fw-bold mb-1 text-truncate"><?= htmlspecialchars($car['brand']) ?> <?= htmlspecialchars($car['model']) ?></h5>
                                            <div class="d-flex flex-wrap gap-1 mt-2">
                                                <span class="badge bg-light text-dark rounded-pill px-2 py-1 small">
                                                    <i class="bi bi-gear-fill me-1 text-primary"></i><?= htmlspecialchars($car['transmission']) ?>
                                                </span>
                                                <span class="badge bg-light text-dark rounded-pill px-2 py-1 small">
                                                    <i class="bi bi-people-fill me-1 text-primary"></i><?= htmlspecialchars($car['capacity']) ?> Seats
                                                </span>
                                                <?php if(!empty($car['color'])): ?>
                                                <span class="badge bg-light text-dark rounded-pill px-2 py-1 small">
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

                                        <div class="mb-4" style="position: relative; overflow: visible;">
                                            <div class="p-2 border rounded-3 d-flex justify-content-between align-items-center bg-white" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#ratesCollapse<?= $car['id'] ?>">
                                                <small class="fw-bold text-dark"><i class="bi bi-cash-coin me-1 text-success"></i> View Rate Options</small>
                                                <i class="bi bi-chevron-down small text-muted"></i>
                                            </div>
                                            
                                            <div class="collapse position-absolute w-100 bg-white border shadow-lg rounded-3 mt-1 rate-dropdown-overlay" id="ratesCollapse<?= $car['id'] ?>" data-parent-card="<?= $car['id'] ?>" style="top: 100%; left: 0; z-index: 99;">
                                                <div class="p-3" style="font-size: 12px; color: #334155;">
                                                    <div class="fw-bold text-secondary border-bottom pb-1 mb-2">Base Multi-Hour Pricing:</div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span>10-Hour Duration Rate:</span>
                                                        <span class="fw-bold text-dark">₱<?= number_format($car['price_10_hours']) ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span>12-Hour Duration Rate:</span>
                                                        <span class="fw-bold text-dark">₱<?= number_format($car['price_12_hours']) ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-3">
                                                        <span>24-Hour Base Rate:</span>
                                                        <span class="fw-bold text-primary">₱<?= number_format($car['price_24_hours']) ?></span>
                                                    </div>

                                                    <div class="fw-bold text-secondary border-bottom pb-1 mb-2">Hourly Extension Rates:</div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span>Hours 1 to 6 Excess:</span>
                                                        <span class="fw-bold text-dark">₱<?= number_format($car['ext_price_1_6']) ?>/hr</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span>Hours 7 to 10 Excess:</span>
                                                        <span class="fw-bold text-dark">₱<?= number_format($car['ext_price_7_10']) ?>/hr</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span>Hours 11 to 12 Excess:</span>
                                                        <span class="fw-bold text-dark">₱<?= number_format($car['ext_price_11_12']) ?>/hr</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span>Hours 13 to 24 Excess:</span>
                                                        <span class="fw-bold text-dark">₱<?= number_format($car['ext_price_13_24']) ?>/hr</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button class="btn btn-primary w-100 fw-bold py-2 rounded-3 shadow-sm mt-auto" 
                                            onclick="openBookingModal(<?= htmlspecialchars(json_encode($car)) ?>)">
                                        <i class="bi bi-calendar-check me-2"></i>Book Now
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="stashGalleryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border-0 shadow-lg text-white">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold text-white-50">
                    <i class="bi bi-images me-2"></i>Vehicle Stash Gallery
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-2 p-md-4">
                
                <div id="stashGalleryCarousel" class="carousel slide" data-bs-ride="false">
                    <div class="carousel-inner rounded-3" id="stashCarouselItemsContainer" style="max-height: 500px; background: #000;">
                        </div>
                    
                    <button class="carousel-control-prev" type="button" data-bs-target="#stashGalleryCarousel" data-bs-slide="prev" id="stashCarouselPrevBtn">
                        <span class="carousel-control-prev-icon shadow-sm rounded-circle p-3 bg-dark bg-opacity-50" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#stashGalleryCarousel" data-bs-slide="next" id="stashCarouselNextBtn">
                        <span class="carousel-control-next-icon shadow-sm rounded-circle p-3 bg-dark bg-opacity-50" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>

                <div class="d-flex gap-2 justify-content-start justify-content-md-center mt-3 overflow-x-auto py-1 w-100" id="stashThumbsContainer" style="-webkit-overflow-scrolling: touch; white-space: nowrap;">
                    </div>

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
                        <div class="table-responsive border rounded p-2 bg-light">
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
                    <div class="col-12 date-time-preview mb-3 p-3 bg-light rounded border-start border-4 border-primary" id="dateTimePreview" style="display: none;">
                        <div class="mb-1 small text-muted">
                            <i class="bi bi-calendar-plus me-1 text-primary"></i>
                            <strong>Pickup:</strong> <span id="previewPickup">--</span>
                        </div>
                        <div class="small text-muted">
                            <i class="bi bi-calendar-check me-1 text-success"></i>
                            <strong>Return:</strong> <span id="previewReturn">--</span>
                        </div>
                    </div>

                    <div id="availabilityWarning" class="alert alert-warning d-none">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <span id="warningMessage"></span>
                    </div>

                    <hr class="text-muted opacity-25">

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

                    <!--  PROOF OF PAYMENT SECTION  -->
                    <div class="col-12 mb-3">
                        <label for="proofOfPaymentInput" class="form-label small fw-bold text-muted mb-1">Proof of Payment</label>
                        
                        <!-- Flex wrapper keeps input & badge side-by-side with no gap -->
                        <div class="d-flex align-items-center gap-2">
                            
                            <!-- File Input -->
                            <div class="flex-grow-1">
                                <input type="file" name="proof_of_payment" id="proofOfPaymentInput" class="form-control form-control-sm" accept="image/*,.pdf" required>
                            </div>

                            <!-- QR Code Button -->
                            <div class="border rounded-3 p-1 pe-2 bg-white d-inline-flex align-items-center gap-2 shadow-sm flex-shrink-0" 
                                style="cursor: pointer; height: 38px; transition: all 0.2s ease-in-out;" 
                                data-bs-toggle="modal" 
                                data-bs-target="#qrCodeModal" 
                                title="Click to view & scan Payment QR Code">
                                
                                <div class="rounded overflow-hidden bg-light d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                    <img src="../../public/assets/images/qr_code_payment.png?v=<?= time(); ?>" 
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

                        <!-- Subtext sitting cleanly below both elements -->
                        <div class="form-text text-muted mt-1" style="font-size: 11.5px;">
                            Scan QR code to pay down payment / full amount, then attach receipt photo.
                        </div>
                    </div>

                    <!-- QR CODE ENLARGEMENT MODAL (Keep this directly underneath or at the bottom of your file) -->
                    <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true" style="z-index: 1060;">
                            <div class="modal-dialog modal-dialog-centered" style="max-width: 360px;">
                                <div class="modal-content border-0 shadow-lg text-center overflow-hidden">
                                    <div class="modal-header bg-primary text-white py-2 px-3 border-0">
                                        <h6 class="modal-title fw-bold mb-0" id="qrCodeModalLabel">
                                            <i class="bi bi-qr-code me-1"></i> Payment QR Code
                                        </h6>
                                    </div>
                                    <div class="modal-body p-3 p-sm-4 bg-light">
                                        <p class="small text-muted mb-3" style="font-size: 12px; line-height: 1.4;">
                                            Scan this QR Code using GCash / Maya / Banking app to send your payment.
                                        </p>
                                        
                                        <div class="bg-white p-2 p-sm-3 rounded shadow-sm d-inline-block border mb-3 mw-100">
                                            <img src="../../public/assets/images/qr_code_payment.png?v=<?= time(); ?>" id="modalEnlargedQr" alt="Enlarged Payment QR Code" class="img-fluid" style="max-height: 240px; width: auto; object-fit: contain;">
                                        </div>
                                        
                                        <div class="w-100">
                                            <div class="alert alert-success border-success-subtle py-2 px-2 m-0 text-wrap fw-bold" style="font-size: 11px; line-height: 1.3;">
                                                💡 Take a screenshot of payment receipt after scanning
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0 p-2 bg-white justify-content-center">
                                        <button type="button" 
                                                class="btn btn-sm btn-secondary fw-semibold px-4" 
                                                onclick="bootstrap.Modal.getInstance(this.closest('.modal')).hide();">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>  

                    <!-- PRICING BREAKDOWN DISPLAY WITH DURATION SUMMARY -->
                    <div class="p-3 bg-light rounded border">
                        <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
                            <span>Base Rental Rate:</span>
                            <span id="userDisplayBasePrice">₱0.00</span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
                            <span>Total Duration:</span>
                            <span class="fw-semibold text-dark" id="userDurationDisplay">0 Hours</span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-1 small text-danger d-none" id="userDiscountRow">
                            <span>Discount Deduction:</span>
                            <span id="userDisplayDiscount">-₱0.00</span>
                        </div>

                        <hr class="my-2">

                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small fw-bold text-dark">Total Due:</span>
                            <h4 class="fw-bold text-primary mb-0" id="userDisplayTotal">₱0.00</h4>
                        </div>
                    </div>

                    <!-- ADDITIONAL INFORMATION -->
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

                <div class="agreement-text p-3 p-md-4 border rounded bg-white shadow-sm" style="height: 400px; overflow-y: auto; font-size: 0.875rem; line-height: 1.8; color: #334155; -webkit-overflow-scrolling: touch;">
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

                    <div class="form-check mt-4 p-3 bg-light rounded border">
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

    if (end <= start) {
        if (userDisplayTotal) userDisplayTotal.innerHTML = '<span class="text-danger">Invalid schedule</span>';
        if (userDurationDisplay) userDurationDisplay.innerHTML = '<span class="text-danger">Invalid dates</span>';
        if (confirmBtn) confirmBtn.disabled = true;
        return;
    }

    const diffMs = end - start;
    const hours = Math.ceil(diffMs / (1000 * 60 * 60));
    const days = Math.floor(hours / 24);

    // Format human-readable duration label
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

    let total = 0;

    if (hours <= 10) {
        total = p10 > 0 ? p10 : 1099;
    } else if (hours <= 12) {
        total = p12 > 0 ? p12 : 1300;
    } else if (hours <= 24) {
        total = p24 > 0 ? p24 : 1500;
    } else {
        const days = Math.floor(hours / 24);
        const extraHours = hours % 24;
        
        total = days * (p24 > 0 ? p24 : 1500);

        if (extraHours > 0) {
            if (extraHours <= 6) total += ext1_6;
            else if (extraHours <= 10) total += ext7_10;
            else if (extraHours <= 12) total += ext11_12;
            else total += ext13_24;
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
});

document.addEventListener('DOMContentLoaded', function() {
    const dropdownElements = document.querySelectorAll('.rate-dropdown-overlay');
    
    dropdownElements.forEach(dropdown => {
        const carId = dropdown.getAttribute('data-parent-card');
        const parentCard = document.querySelector(`.card[data-car-id="${carId}"]`);
        
        if (parentCard) {
            dropdown.addEventListener('show.bs.collapse', function() {
                parentCard.style.zIndex = '9999';
                
                dropdownElements.forEach(otherDropdown => {
                    if (otherDropdown !== dropdown && otherDropdown.classList.contains('show')) {
                        const bsCollapse = bootstrap.Collapse.getInstance(otherDropdown);
                        if (bsCollapse) {
                            bsCollapse.hide();
                        }
                    }
                });
            });
            
            dropdown.addEventListener('hide.bs.collapse', function() {
                parentCard.style.zIndex = '1';
            });
        }
    });

    document.addEventListener('click', function(event) {
        dropdownElements.forEach(dropdown => {
            if (dropdown.classList.contains('show')) {
                const carId = dropdown.getAttribute('data-parent-card');
                const toggleBtn = document.querySelector(`[data-bs-target="#ratesCollapse${carId}"]`);
                
                if (toggleBtn && !dropdown.contains(event.target) && !toggleBtn.contains(event.target)) {
                    const bsCollapse = bootstrap.Collapse.getInstance(dropdown);
                    if (bsCollapse) {
                        bsCollapse.hide();
                    }
                }
            }
        });
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
</script>