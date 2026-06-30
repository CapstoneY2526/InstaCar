<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'operator'])) {
    $_SESSION['error'] = "Access denied.";
    header("Location: ../../index.php");
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];
$pageTitle = ($user_role === 'admin') ? 'Fleet Management' : 'My Car Fleet';

// Get stats
$statsQuery = "SELECT 
    COUNT(*) as total_cars,
    SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN status IN ('Active', 'Rented') THEN 1 ELSE 0 END) as rented,
    SUM(CASE WHEN status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance
FROM cars";

if ($user_role !== 'admin') {
    $statsQuery .= " WHERE user_id = $current_user_id";
}

$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Get cars list
$cars = [];
if ($user_role === 'admin') {
    $query = "SELECT c.*, u.name as owner_name FROM cars c LEFT JOIN users u ON c.user_id = u.id ORDER BY c.id DESC";
} else {
    $query = "SELECT * FROM cars WHERE user_id = $current_user_id ORDER BY id DESC";
}

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) { $cars[] = $row; }
}
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    /* =========================================
       GLOBAL SAFETY LAYER & LAYOUT FIXED
    ========================================= */
    * { box-sizing: border-box; }
    body { overflow-x: hidden; }
    
    /* Crucial Fix: Standard workflow boundaries allow sticky headers to compute perfectly */
    .main-content { min-width: 0; }

    .car-img-container {
        width: 60px; height: 60px; border-radius: 8px; overflow: hidden;
        background: #f1f5f9; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0;
    }
    .car-img-container img { width: 100%; height: 100%; object-fit: cover; }
    .badge-active { background-color: #0d6efd; color: white; }
    
    /* ========================================================
       UNIFIED DASHBOARD STATS GRID ENGINE (MANUAL SPEC-MATCHED)
    ======================================================== */
    .row.g-3.mb-4 {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important; /* Forces a perfect 2x2 grid on mobile layout ports */
        gap: 12px !important;
    }

    @media (min-width: 768px) {
        .row.g-3.mb-4 {
            grid-template-columns: repeat(4, 1fr) !important; /* Fluidly scales out to 4 columns on desktop layouts */
            gap: 15px !important;
            padding-bottom:20px;
        }
    }

    .stat-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding:10px;
        display: flex;
        align-items: center;
        gap: 12px;
        height: 100%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    }

    @media (min-width: 768px) {
        .stat-card {
            border-radius: 16px;
            padding:10px;
            gap: 13px;
        }
    }

    .stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    @media (min-width: 768px) {
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            font-size: 1.5rem;
        }
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        line-height: 1.1;
        color: #0f172a;
    }

    @media (min-width: 768px) {
        .stat-value {
            font-size: 1.75rem;
        }
    }

    .stat-label {
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-top: 2px;
    }

    /* Table Layout Structure Alignment Fixes */
    .table-responsive {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
        width: 100%;
        position: relative;
    }

    #fleetTable {
        width: 100% !important;
        table-layout: auto !important;
        margin: 0 !important;
    }

    #fleetTable th, #fleetTable td {
        white-space: nowrap;
        vertical-align: middle;
    }

    .action-scroll {
        display: flex;
        justify-content: flex-end;
        overflow-x: auto;
        max-width: 100%;
        -webkit-overflow-scrolling: touch;
    }

    .action-scroll .btn-group {
        display: inline-flex;
        flex-wrap: nowrap;
        gap: 6px;
        min-width: max-content;
    }

    /* ========================================================
       PREMIUM WINDOWS-MATCHED CUSTOM CONTROL BAR UI
    ======================================================== */
    .custom-control-bar {
        padding:5px;
        border: 1px solid gray !important;
        border-radius: 12px !important;
        background: #ffffff !important;
    }

    /* Clean Styled Icon Search Bar Engine UI */
    .search-input-wrapper {
        position: relative;
    }

    .search-input-wrapper i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 14px;
        pointer-events: none;
        z-index: 5;
    }

    .search-input-wrapper input {
        width: 100% !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 8px !important;
        padding: 8px 14px 8px 38px !important;
        font-size: 13px !important;
        height: 38px !important;
        color: #1e293b !important;
        background-color: #ffffff !important;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04) !important;
        transition: all 0.2s ease-in-out !important;
    }

    .search-input-wrapper input:focus {
        border-color: #0d6efd !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15) !important;
    }

    /* Custom Entry Limiter Form Element Selection UI */
    .entry-limiter-wrapper {
        color: #64748b;
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
    }

    .entry-limiter-select {
        display: inline-block;
        width: auto;
        height: 38px;
        padding: 6px 36px 6px 12px;
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        background-color: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        cursor: pointer;
        outline: none;
        transition: all 0.2s;
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23475569' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 12px 12px;
    }

    .entry-limiter-select:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.15);
    }

    @media (min-width: 992px) {
        #fleetTable td, #fleetTable th {
            white-space: normal !important;
            word-wrap: break-word;
        }
    }

    /* Smart Card Viewport Conversion Engine - Targets both Smartphones and Tablets (< 992px) */
    @media (max-width: 991.98px) {
        .col-md-2 { width: 100%; }
        .col-md-10 { width: 100%; }
        .main-content { padding-left: 0 !important; }

        .custom-control-bar .card-body {
            flex-direction: row !important;
            align-items: stretch !important;
            gap: 12px !important;
        }

        /* Transform table to clean stacked cards */
        #fleetTable,
        #fleetTable tbody {
            display: block;
            width: 100%;
        }
        #fleetTable thead {
            display: none;
        }
        #fleetTable tr {
            display: block;
            background: #fff;
            border-radius: 16px;
            margin-bottom: 16px;
            padding: 16px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }
        #fleetTable td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border: none !important;
            font-size: 13px;
        }
        #fleetTable td::before {
            content: attr(data-title);
            font-weight: 600;
            color: #64748b;
            text-align: left;
            flex-shrink: 0;
            margin-right: 15px;
        }
        
        #fleetTable td:first-child {
            border-bottom: 1px solid #f1f5f9 !important;
            padding-bottom: 12px;
            margin-bottom: 8px;
            justify-content: flex-start;
        }
        #fleetTable td:first-child::before {
            display: none;
        }
        .car-img-container {
            width: 52px;
            height: 52px;
            flex-shrink: 0;
        }
        
        #fleetTable td { text-align: right !important; }
        #fleetTable td * { text-align: right !important; }
        #fleetTable td:first-child * { text-align: left !important; }

        .badge { margin-top: 0; }
        .btn-group {
            display: flex;
            gap: 6px;
            justify-content: flex-end;
            width: auto;
        }
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 p-0"><?php require_once __DIR__ . '/../components/sidebar.php'; ?></div>
        <div class="col-md-10 p-0 d-flex flex-column main-content" style="background: #f8fafc; min-height: 100vh;">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold mb-0"><?= $pageTitle ?></h3>
                        <p class="text-muted small mb-0">Manage specs, manual tie-up rates, and availability.</p>
                    </div>
                    <button class="btn btn-primary shadow-sm fw-semibold px-3 px-md-4 py-2 rounded-3 d-inline-flex align-items-center gap-2 w-md-auto" data-bs-toggle="modal" data-bs-target="#addCarModal">
                        <i class="bi bi-plus-lg me-2"></i>Add New Car
                    </button>
                </div>

                <div class="row g-3 mb-4">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-car-front-fill"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?= number_format($stats['total_cars'] ?? 0) ?></div>
                            <div class="stat-label">Total Vehicles</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?= number_format($stats['available'] ?? 0) ?></div>
                            <div class="stat-label">Available</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?= number_format($stats['rented'] ?? 0) ?></div>
                            <div class="stat-label">Rented/Active</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-tools"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?= number_format($stats['maintenance'] ?? 0) ?></div>
                            <div class="stat-label">Maintenance</div>
                        </div>
                    </div>
                </div>

                <div class="card custom-control-bar shadow-sm mb-4">
                    <div class="card-body p-2 d-flex flex-row justify-content-between align-items-center gap-3">
                        
                        <div class="entry-limiter-wrapper d-flex align-items-center gap-2">
                            <span>Show</span>
                            <select id="fleetEntryLimitSelect" class="entry-limiter-select">
                                <option value="5">5</option>
                                <option value="10" selected>10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span>entries</span>
                        </div>
                        
                        <div class="search-input-wrapper">
                            <i class="bi bi-search"></i>
                            <input type="text" id="unifiedFleetSearch" placeholder="Search vehicles real-time...">
                        </div>
                        
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                    <div class="card-body p-3 p-md-0">
                        <div class="table-responsive">
                            <table id="fleetTable" class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4 py-3">Vehicle</th>
                                        <?php if ($user_role === 'admin'): ?><th>Owner</th><?php endif; ?>
                                        <th>Specs</th>
                                        <th>Plate No.</th>
                                        <th>Rates & Splits (10h / 12h / 24h)</th>
                                        <th>Extension Rates</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cars as $car): ?>
                                    <tr class="js-searchable-car-row">
                                        <td class="ps-4" data-title="Vehicle">
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                    $image_src = 'default.png';
                                                    $image_array = [];
                                                    if (!empty($car['image_path'])) {
                                                        $image_array = array_map('trim', explode(',', $car['image_path']));
                                                        $image_src = $image_array[0];
                                                    }
                                                    $extra_count = count($image_array) - 1;
                                                    $comma_separated_images = implode(',', $image_array);
                                                ?>
                                                
                                                <button type="button" 
                                                        class="btn p-0 border-0 me-3 position-relative car-gallery-modal-trigger" 
                                                        style="width: 85px; height: 60px; border-radius: 8px; overflow: hidden; background: #f1f5f9; border: 1px solid #e2e8f0; outline: none; box-shadow: none;"
                                                        data-car-id="<?= $car['id'] ?>"
                                                        data-car-name="<?= htmlspecialchars($car['brand'] . ' ' . $car['model']) ?>"
                                                        data-images="<?= htmlspecialchars($comma_separated_images) ?>">
                                                    
                                                    <img id="master_car_img_<?= $car['id'] ?>" src="../../public/assets/images/cars/<?= htmlspecialchars($image_src) ?>" alt="Car Thumbnail" class="w-100 h-100" style="object-fit: contain; background-color: #f8fafc;">
                                                    
                                                    <?php if ($extra_count > 0): ?>
                                                        <span class="position-absolute bottom-0 end-0 bg-dark bg-opacity-75 text-white px-1 fw-bold" style="font-size: 10px; border-top-left-radius: 4px;">
                                                            +<?= $extra_count ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </button>

                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($car['brand']) ?> <?= htmlspecialchars($car['model']) ?></div>
                                                    <div class="small text-muted text-uppercase" style="font-size: 11px; font-weight: 600; letter-spacing: 0.5px;"><?= htmlspecialchars($car['type']) ?></div>
                                                </div>
                                            </div>
                                            
                                            <div class="modal fade" id="fleetGalleryModal" tabindex="-1" aria-labelledby="fleetGalleryModalLabel" aria-hidden="true">
                                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg">
                                                        <div class="modal-header border-0 bg-light py-3">
                                                            <h5 class="modal-title fw-bold text-dark" id="fleetGalleryModalLabel">Vehicle Photos</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body p-4 bg-white">
                                                            <div class="text-center rounded-3 mb-4 p-2 position-relative" style="background: #f8fafc; border: 1px solid #e2e8f0; height: 380px;">
                                                                <img id="modalSpotlightViewer" src="" class="w-100 h-100" style="object-fit: contain;" alt="Vehicle Spotlight View">
                                                            </div>
                                                            
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <h6 class="small fw-bold text-muted mb-0 text-uppercase" style="letter-spacing: 0.5px;">Photo Stash Gallery</h6>
                                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill small" style="font-size: 11px;">💡 Click a photo below to set it as Main</span>
                                                            </div>
                                                            <div id="modalThumbnailsStripe" class="d-flex gap-2 flex-wrap p-1">
                                                                </div>
                                                        </div>
                                                        <div class="modal-footer border-0 bg-light py-2">
                                                            <button type="button" class="btn btn-sm btn-secondary fw-semibold" data-bs-dismiss="modal">Close Gallery</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <?php if ($user_role === 'admin'): ?>
                                            <td data-title="Owner"><small><?= htmlspecialchars($car['owner_name'] ?? 'System') ?></small></td>
                                        <?php endif; ?>
                                        <td data-title="Specs"><small><?= htmlspecialchars($car['transmission']) ?> | <?= $car['capacity'] ?> Seats | <?= htmlspecialchars($car['color']) ?></small> </td>
                                        <td data-title="Plate No."><code><?= htmlspecialchars($car['plate_number']) ?></code></td>
                                        
                                        <td data-title="Rates & Splits">
                                            <div class="d-flex flex-column gap-1" style="max-width: 280px;">
                                                <div class="d-flex justify-content-between align-items-center border-bottom pb-1" style="font-size: 11px;">
                                                    <span class="fw-bold text-dark">10h: ₱<?= number_format($car['price_10_hours'], 0) ?></span>
                                                    <span class="text-muted">Op: ₱<?= number_format($car['operator_10_hours'], 0) ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center border-bottom pb-1" style="font-size: 11px;">
                                                    <span class="fw-bold text-dark">12h: ₱<?= number_format($car['price_12_hours'], 0) ?></span>
                                                    <span class="text-muted">Op: ₱<?= number_format($car['operator_12_hours'], 0) ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center" style="font-size: 11px;">
                                                    <span class="fw-bold text-primary">24h: ₱<?= number_format($car['price_24_hours'], 0) ?></span>
                                                    <span class="text-muted">Op: ₱<?= number_format($car['operator_24_hours'], 0) ?></span>
                                                </div>
                                            </div>
                                        </td>

                                        <td data-title="Extension Rates">
                                            <div class="d-flex flex-column gap-1" style="max-width: 280px; font-size: 11px;">
                                                <div class="d-flex justify-content-between align-items-center border-bottom pb-1">
                                                    <span class="text-muted">Hrs 1-6:</span>
                                                    <span class="fw-bold text-dark">₱<?= number_format($car['ext_price_1_6'], 0) ?>/hr</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center border-bottom pb-1">
                                                    <span class="text-muted">Hrs 7-10:</span>
                                                    <span class="fw-bold text-dark">₱<?= number_format($car['ext_price_7_10'], 0) ?>/hr</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center border-bottom pb-1">
                                                    <span class="text-muted">Hrs 11-12:</span>
                                                    <span class="fw-bold text-dark">₱<?= number_format($car['ext_price_11_12'], 0) ?>/hr</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="text-muted">Hrs 13-24:</span>
                                                    <span class="fw-bold text-dark">₱<?= number_format($car['ext_price_13_24'], 0) ?>/hr</span>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="text-center" data-title="Status">
                                            <?php 
                                                $status = ($car['status'] == 'Rented') ? 'Active' : $car['status'];
                                                $class = match($status) { 'Available'=>'bg-success', 'Active'=>'badge-active', 'Maintenance'=>'bg-danger', default=>'bg-secondary' };
                                            ?>
                                            <span class="badge rounded-pill <?= $class ?>" style="min-width: 80px;"><?= $status ?></span>
                                        </td>
                                        <td class="text-end pe-4" data-title="Actions">
                                            <div class="action-scroll">
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#editCarModal<?= $car['id'] ?>"><i class="bi bi-pencil"></i></button>
                                                    <a href="process/car_actions.php?delete=<?= $car['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to completely remove this vehicle listing?')"><i class="bi bi-trash"></i></a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($cars)): ?>
                                    <tr class="js-empty-state-row">
                                        <td colspan="<?= $user_role === 'admin' ? '8' : '7' ?>" class="text-center py-5 text-muted">
                                            <i class="bi bi-car-front fs-1 d-block mb-2 opacity-50"></i>
                                            No vehicles found. Click "Register New Vehicle" to fill the stash gallery!
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="addCarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="process/car_actions.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg" id="vehicleRegisterForm">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Register New Vehicle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label small fw-bold text-secondary mb-0">Vehicle Media Upload Stash</label>
                        <span class="badge bg-primary rounded-pill px-2 py-1" id="stashCountBadge" style="font-size: 11px;">0 Photos</span>
                    </div>
                    <div class="border border-dashed rounded-4 p-3 bg-light text-center position-relative transition-all" id="dropzoneContainer" style="border-width: 2px !important; border-color: #cbd5e1 !important;">
                        <input type="file" id="stashImageInput" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer; z-index: 5;" accept="image/*" multiple>
                        
                        <div id="dropzonePlaceholder" class="py-3">
                            <i class="bi bi-images text-primary mb-2" style="font-size: 2.2rem;"></i>
                            <p class="mb-1 fw-semibold small">Drag & drop multiple vehicle photos here, or click to browse</p>
                            <span class="text-muted" style="font-size: 11px;">Supports JPEG, PNG, WEBP formats</span>
                        </div>

                        <div id="stashPreviewRow" class="d-none row g-2 justify-content-start mt-2" style="max-height: 260px; overflow-y: auto; position: relative; z-index: 10;">
                            </div>
                    </div>
                    <div id="hiddenFileInputsContainer"></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label small fw-bold">Brand</label><input type="text" name="brand" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Model</label><input type="text" name="model" class="form-control" required></div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Transmission</label>
                        <select name="transmission" class="form-select" required>
                            <option value="Manual">Manual</option>
                            <option value="Automatic">Automatic</option>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Seats</label><input type="number" name="capacity" class="form-control" value="5" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Color</label><input type="text" name="color" class="form-control" placeholder="RED" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Car Type</label><input type="text" name="type" class="form-control" placeholder="SUV" required></div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Fuel Type</label>
                        <select name="fuel_type" class="form-select" required>
                            <option value="green">Regular</option>
                            <option value="red">Premium</option>
                            <option value="diesel">Diesel</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Plate Number</label>
                        <input type="text" name="plate_number" id="edit_plate_number_<?= $car['id'] ?>" class="form-control edit-plate-input text-uppercase" data-car-id="<?= $car['id'] ?>" value="<?= htmlspecialchars($car['plate_number']) ?>" required>
                        <div id="edit_plate_error_<?= $car['id'] ?>" class="text-danger small fw-semibold mt-1 d-none"></div>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-secondary mb-1">Base Tier Tariffs & Shares</h6>

                    <div class="<?= $_SESSION['role'] === 'operator' ? 'col-12' : 'col-md-6' ?>">
                        <label class="form-label small fw-bold">Price per 10 Hrs (₱)</label>
                        <input type="number" name="price_10_hours" class="form-control" placeholder="0.00" required>
                    </div>
                    <?php if ($_SESSION['role'] !== 'operator'): ?>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-success">Operator Share 10 Hrs (₱)</label>
                        <input type="number" name="operator_10_hours" class="form-control text-success fw-bold" placeholder="0.00" required>
                    </div>
                    <?php endif; ?>

                    <div class="<?= $_SESSION['role'] === 'operator' ? 'col-12' : 'col-md-6' ?>">
                        <label class="form-label small fw-bold">Price per 12 Hrs (₱)</label>
                        <input type="number" name="price_12_hours" class="form-control" placeholder="0.00" required>
                    </div>
                    <?php if ($_SESSION['role'] !== 'operator'): ?>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-success">Operator Share 12 Hrs (₱)</label>
                        <input type="number" name="operator_12_hours" class="form-control text-success fw-bold" placeholder="0.00" required>
                    </div>
                    <?php endif; ?>

                    <div class="<?= $_SESSION['role'] === 'operator' ? 'col-12' : 'col-md-6' ?>">
                        <label class="form-label small fw-bold">Price per 24 Hrs (₱)</label>
                        <input type="number" name="price_24_hours" class="form-control" placeholder="0.00" required>
                    </div>
                    <?php if ($_SESSION['role'] !== 'operator'): ?>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-success">Operator Share 24 Hrs (₱)</label>
                        <input type="number" name="operator_24_hours" class="form-control text-success fw-bold" placeholder="0.00" required>
                    </div>
                    <?php endif; ?>

                    <hr class="my-3">
                    <h6 class="fw-bold text-danger mb-1">Hourly Overtime Extensions</h6>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold">1-6 Hours (₱/Hr)</label>
                        <input type="number" name="ext_price_1_6" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">7-10 Hours (₱/Hr)</label>
                        <input type="number" name="ext_price_7_10" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">11-12 Hours (₱/Hr)</label>
                        <input type="number" name="ext_price_11_12" class="form-control" placeholder="0" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">13-24 Hours (₱/Hr)</label>
                        <input type="number" name="ext_price_13_24" class="form-control" placeholder="0.00" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_car" class="btn btn-primary px-4 fw-bold">Register Car</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($cars as $car): ?>
<div class="modal fade" id="editCarModal<?= $car['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="process/car_actions.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg" id="vehicleEditForm">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">Edit Vehicle Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <input type="hidden" name="id" value="<?= $car['id'] ?>">

                <!-- EDIT STASH LAYER START -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label small fw-bold text-secondary mb-0">Vehicle Stash Gallery Management</label>
                        <span class="badge bg-primary rounded-pill px-2 py-1" id="editStashCountBadge" style="font-size: 11px;">0 New Photos</span>
                    </div>
                    
                    <div class="border border-dashed rounded-4 p-3 bg-light text-center position-relative transition-all" id="editDropzoneContainer" style="border-width: 2px !important; border-color: #cbd5e1 !important;">
                        <input type="file" id="editStashImageInput" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer; z-index: 5;" accept="image/*" multiple>
                        
                        <div id="editStashPreviewRow" class="row g-2 justify-content-start align-items-stretch" style="max-height: 260px; overflow-y: auto; position: relative; z-index: 10;">
                            
                            <?php 
                            if (!empty($car['image_path'])): 
                                // Split the comma-separated filenames into a clean array
                                $existing_images = array_map('trim', explode(',', $car['image_path']));
                                foreach ($existing_images as $index => $img_name):
                                    if (empty($img_name)) continue;
                                    // Create a unique clean ID safe for DOM manipulation selectors
                                    $container_id = "existingPhoto_" . md5($img_name);
                            ?>
                                <div class="col-4 col-sm-3 position-relative existing-photo-item mb-2" id="<?= $container_id ?>">
                                    <div class="card h-100 border rounded-3 overflow-hidden shadow-sm bg-white" style="min-height: 115px;">
                                        <img src="../../public/assets/images/cars/<?= htmlspecialchars($img_name) ?>" class="w-100" style="height: 85px; object-fit: cover;" alt="Saved Vehicle Photo">
                                        <div class="p-1 text-center border-top <?= ($index === 0) ? 'bg-primary bg-opacity-10' : 'bg-light' ?>">
                                            <?php if ($index === 0): ?>
                                                <span class="d-block small text-primary fw-bold" style="font-size: 9px; letter-spacing: 0.5px;">⭐ MAIN PHOTO</span>
                                            <?php else: ?>
                                                <span class="d-block small text-muted fw-semibold" style="font-size: 9px; letter-spacing: 0.5px;">STASH PHOTO</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-danger rounded-circle position-absolute d-flex align-items-center justify-content-center shadow-sm" 
                                            onclick="markExistingForDeletion('<?= htmlspecialchars($img_name) ?>', '<?= $container_id ?>')" 
                                            style="top: -6px; right: 2px; width: 22px; height: 22px; padding: 0; z-index: 25; border: 1px solid #fff;" title="Delete Photo from Server">
                                        <i class="bi bi-trash" style="font-size: 11px;"></i>
                                    </button>
                                </div>
                            <?php 
                                endforeach;
                            endif; 
                            ?>

                            <div class="col-4 col-sm-3 mb-2" id="inlineUploadSlot" style="position: relative; z-index: 12; cursor: pointer;">
                                <div class="card h-100 border border-dashed rounded-3 d-flex flex-column align-items-center justify-content-center text-primary p-2 bg-white shadow-sm" style="min-height: 115px; border-width: 2px !important; border-color: #bdc8d7 !important;">
                                    <i class="bi bi-plus-circle-fill fs-3 mb-1"></i>
                                    <span class="fw-bold text-center" style="font-size: 11px; line-height: 1.2;">Add More<br>Photos</span>
                                </div>
                            </div>

                        </div>
                    </div>
                    
                    <div id="removedImagesContainer"></div>
                </div>
                <!-- EDIT STASH LAYER END -->

                
                
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label small fw-bold">Brand</label><input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($car['brand']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Model</label><input type="text" name="model" class="form-control" value="<?= htmlspecialchars($car['model']) ?>" required></div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Transmission</label>
                        <select name="transmission" class="form-select">
                            <option value="Manual" <?= $car['transmission'] == 'Manual' ? 'selected' : '' ?>>Manual</option>
                            <option value="Automatic" <?= $car['transmission'] == 'Automatic' ? 'selected' : '' ?>>Automatic</option>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Seats</label><input type="number" name="capacity" class="form-control" value="<?= $car['capacity'] ?>" required></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Color</label><input type="text" name="color" class="form-control" value="<?= htmlspecialchars($car['color']) ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Car Type</label><input type="text" name="type" class="form-control" value="<?= htmlspecialchars($car['type']) ?>" required></div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Fuel Type</label>
                        <select name="fuel_type" class="form-select">
                            <option value="green" <?= $car['fuel_type'] == 'green' ? 'selected' : '' ?>>Regular</option>
                            <option value="red" <?= $car['fuel_type'] == 'red' ? 'selected' : '' ?>>Premium</option>
                            <option value="diesel" <?= $car['fuel_type'] == 'diesel' ? 'selected' : '' ?>>Diesel</option>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label small fw-bold">Plate Number</label><input type="text" name="plate_number" class="form-control" value="<?= htmlspecialchars($car['plate_number']) ?>" required></div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Status</label>
                        <select name="status" class="form-select">
                            <option value="Available" <?= $car['status'] == 'Available' ? 'selected' : '' ?>>Available</option>
                            <option value="Active" <?= ($car['status'] == 'Active' || $car['status'] == 'Rented') ? 'selected' : '' ?>>Active</option>
                            <option value="Maintenance" <?= $car['status'] == 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="Not Available" <?= $car['status'] == 'Not Available' ? 'selected' : '' ?>>Not Available</option>
                        </select>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-secondary mb-1">Base Tier Tariffs & Shares</h6>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Price per 10 Hrs (₱)</label>
                        <input type="number" name="price_10_hours" class="form-control" value="<?= $car['price_10_hours'] ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-success">Operator Share 10 Hrs (₱)</label>
                        <input type="number" name="operator_10_hours" class="form-control text-success fw-bold" value="<?= $car['operator_10_hours'] ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Price per 12 Hrs (₱)</label>
                        <input type="number" name="price_12_hours" class="form-control" value="<?= $car['price_12_hours'] ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-success">Operator Share 12 Hrs (₱)</label>
                        <input type="number" name="operator_12_hours" class="form-control text-success fw-bold" value="<?= $car['operator_12_hours'] ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Price per 24 Hrs (₱)</label>
                        <input type="number" name="price_24_hours" class="form-control" value="<?= $car['price_24_hours'] ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-success">Operator Share 24 Hrs (₱)</label>
                        <input type="number" name="operator_24_hours" class="form-control text-success fw-bold" value="<?= $car['operator_24_hours'] ?>" required>
                    </div>

                    <hr class="my-3">
                    <h6 class="fw-bold text-danger mb-1">Hourly Overtime Extensions</h6>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold">1-6 Hours (₱/Hr)</label>
                        <input type="number" name="ext_price_1_6" class="form-control" value="<?= $car['ext_price_1_6'] ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">7-10 Hours (₱/Hr)</label>
                        <input type="number" name="ext_price_7_10" class="form-control" value="<?= $car['ext_price_7_10'] ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">11-12 Hours (₱/Hr)</label>
                        <input type="number" name="ext_price_11_12" class="form-control" value="<?= $car['ext_price_11_12'] ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">13-24 Hours (₱/Hr)</label>
                        <input type="number" name="ext_price_13_24" class="form-control" value="<?= $car['ext_price_13_24'] ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_car" class="btn btn-primary px-4 fw-bold">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

<script>
    // 🟢 LIGHTWEIGHT DECOUPLED FLEET ENGINE WITH REALTIME LIMITER & SEARCH
    $(document).ready(function () {
        function applyFleetLimiterAndFilter() {
            const queryValue = $('#unifiedFleetSearch').val().toLowerCase().trim();
            const limitValue = parseInt($('#fleetEntryLimitSelect').val(), 10) || 10;
            let matchCount = 0;
            
            // Evaluates both standard desktop rows and adaptive mobile cards uniformly
            $('#fleetTable tbody tr.js-searchable-car-row').each(function () {
                const textContent = $(this).text().toLowerCase();
                const matchesSearch = textContent.includes(queryValue);

                if (matchesSearch) {
                    matchCount++;
                    if (matchCount <= limitValue) {
                        $(this).css('display', '');
                    } else {
                        $(this).css('display', 'none');
                    }
                } else {
                    $(this).css('display', 'none');
                }
            });

            // Dynamically toggles standard empty messaging states if zero items qualify
            if (matchCount === 0 && $('#fleetTable tbody tr.js-searchable-car-row').length > 0) {
                if ($('.js-no-results-fallback').length === 0) {
                    $('#fleetTable tbody').append(`
                        <tr class="js-no-results-fallback">
                            <td colspan="<?= $user_role === 'admin' ? '7' : '6' ?>" class="text-center py-4 text-muted">
                                <i class="bi bi-search fs-3 d-block mb-2 opacity-50"></i>
                                No matching vehicles found for "${$('#unifiedFleetSearch').val()}".
                            </td>
                        </tr>
                    `);
                }
            } else {
                $('.js-no-results-fallback').remove();
            }
        }

        // Action Core Event Listeners
        $('#unifiedFleetSearch').on('input', applyFleetLimiterAndFilter);
        $('#fleetEntryLimitSelect').on('change', applyFleetLimiterAndFilter);

        // Run on Page Load
        applyFleetLimiterAndFilter();
    });

    document.querySelectorAll('.price-calc').forEach(input => {
        input.addEventListener('input', function() {
            ['10', '12', '24'].forEach(tier => {
                const priceInput = document.getElementById('p_' + tier);
                const opField = document.getElementById('o_' + tier);
                
                if (priceInput && opField) {
                    const price = parseFloat(priceInput.value) || 0;
                    if (price > 0) {
                        opField.value = "₱ " + price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    } else {
                        opField.value = "";
                    }
                }
            });
        });
    });

    document.querySelectorAll('.edit-price-calc-<?= $car['id'] ?? 0 ?>').forEach(input => {
        input.addEventListener('input', function() {
            ['10', '12', '24'].forEach(tier => {
                const priceInput = document.getElementById('edit_p_' + tier + '_<?= $car['id'] ?? 0 ?>');
                const opField = document.getElementById('edit_o_' + tier + '_<?= $car['id'] ?? 0 ?>');
                
                if (priceInput && opField) {
                    const price = parseFloat(priceInput.value) || 0;
                    if (price > 0) {
                        opField.value = "₱ " + price.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    } else {
                        opField.value = "";
                    }
                }
            });
        });
    });

    // Global array holding the current file list stash context
    let imageStashArray = [];

    document.getElementById('stashImageInput').addEventListener('change', function(e) {
        const files = Array.from(e.target.files);
        
        files.forEach(file => {
            // Prevent adding exact duplicate files by name and size validation
            if (!imageStashArray.some(stashedFile => stashedFile.name === file.name && stashedFile.size === file.size)) {
                imageStashArray.push(file);
            }
        });

        renderStashGallery();
        this.value = ''; 
    });

    function renderStashGallery() {
        const placeholder = document.getElementById('dropzonePlaceholder');
        const previewRow = document.getElementById('stashPreviewRow');
        const countBadge = document.getElementById('stashCountBadge');
        
        previewRow.innerHTML = '';
        countBadge.textContent = `${imageStashArray.length} Photo${imageStashArray.length === 1 ? '' : 's'}`;

        if (imageStashArray.length === 0) {
            placeholder.classList.remove('d-none');
            previewRow.classList.add('d-none');
            return;
        }

        placeholder.classList.add('d-none');
        previewRow.classList.remove('d-none');

        imageStashArray.forEach((file, index) => {
            const reader = new FileReader();
            
            reader.onload = function(event) {
                const col = document.createElement('div');
                col.className = 'col-4 col-sm-3 position-relative mb-2';
                col.innerHTML = `
                    <div class="card h-100 border rounded-3 overflow-hidden shadow-sm" style="background: #fff;">
                        <img src="${event.target.result}" class="w-100" style="height: 85px; object-fit: cover;" alt="Preview">
                        <div class="p-1 bg-light border-top text-center">
                            <span class="text-truncate d-block small text-muted px-1" style="font-size: 9px; max-width: 100%;">${file.name}</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-danger rounded-circle position-absolute d-flex align-items-center justify-content-center remove-stash-btn" data-index="${index}" style="top: -6px; right: 2px; width: 20px; height: 20px; padding: 0; z-index: 20;" title="Remove Photo">
                        <i class="bi bi-x" style="font-size: 14px; font-weight: bold;"></i>
                    </button>
                `;
                
                previewRow.appendChild(col);
                
                col.querySelector('.remove-stash-btn').addEventListener('click', function(clickEvent) {
                    clickEvent.stopPropagation();
                    clickEvent.preventDefault();
                    imageStashArray.splice(index, 1);
                    renderStashGallery();
                });
            };
            
            reader.readAsDataURL(file);
        });
    }

    // INTERCEPT FORM SUBMISSION TO BIND STASHED IMAGES TO THE FORMDATA INSTANCE
    document.getElementById('vehicleRegisterForm').addEventListener('submit', function(e) {
        if (imageStashArray.length === 0) {
            e.preventDefault();
            alert('Please add at least one vehicle photo to the stash gallery.');
            return;
        }

        const dataTransfer = new DataTransfer();
        imageStashArray.forEach(file => {
            dataTransfer.items.add(file);
        });
        
        const fileInput = document.getElementById('stashImageInput');
        fileInput.name = "car_images[]"; 
        fileInput.files = dataTransfer.files;
    });

    // Drag & drop highlight state styling variables
    const dropzone = document.getElementById('dropzoneContainer');
    if (dropzone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.style.backgroundColor = '#e2e8f0';
            }, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => {
                dropzone.style.backgroundColor = '#f8fafc';
            }, false);
        });
    }

    // Global trackers for Editing variables
    let editImageStashArray = [];
    let imagesDeletedList = [];

    document.getElementById('editStashImageInput').addEventListener('change', function(e) {
        const files = Array.from(e.target.files);
        
        files.forEach(file => {
            if (!editImageStashArray.some(stashed => stashed.name === file.name && stashed.size === file.size)) {
                editImageStashArray.push(file);
            }
        });

        renderEditStashGallery();
        this.value = ''; 
    });

    function renderEditStashGallery() {
        const previewRow = document.getElementById('editStashPreviewRow');
        const countBadge = document.getElementById('editStashCountBadge');
        const inlineUploadSlot = document.getElementById('inlineUploadSlot');
        
        const newPreviews = previewRow.querySelectorAll('.new-photo-preview');
        newPreviews.forEach(el => el.remove());
        
        countBadge.textContent = `${editImageStashArray.length} New Photo${editImageStashArray.length === 1 ? '' : 's'}`;

        editImageStashArray.forEach((file, index) => {
            const reader = new FileReader();
            
            reader.onload = function(event) {
                const col = document.createElement('div');
                col.className = 'col-4 col-sm-3 position-relative mb-2 new-photo-preview';
                col.innerHTML = `
                    <div class="card h-100 border rounded-3 overflow-hidden shadow-sm" style="background: #fff; min-height: 115px;">
                        <img src="${event.target.result}" class="w-100" style="height: 85px; object-fit: cover;" alt="Preview">
                        <div class="p-1 bg-light border-top text-center">
                            <span class="text-truncate d-block small text-muted px-1" style="font-size: 9px; max-width: 100%;">${file.name}</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-danger rounded-circle position-absolute d-flex align-items-center justify-content-center remove-edit-stash-btn shadow" data-index="${index}" style="top: -6px; right: 2px; width: 22px; height: 22px; padding: 0; z-index: 25;" title="Remove Photo">
                        <i class="bi bi-x" style="font-size: 14px; font-weight: bold;"></i>
                    </button>
                `;
                
                previewRow.insertBefore(col, inlineUploadSlot);
                
                col.querySelector('.remove-edit-stash-btn').addEventListener('click', function(clickEvent) {
                    clickEvent.stopPropagation();
                    clickEvent.preventDefault();
                    editImageStashArray.splice(index, 1);
                    renderEditStashGallery();
                });
            };
            
            reader.readAsDataURL(file);
        });
    }

    // Handle removal of already existing pictures on server
    // Handle removal of already existing pictures on server
    function markExistingForDeletion(imagePath, containerId) {
        if (confirm("Are you sure you want to remove this photo? Changes take effect upon saving.")) {
            const container = document.getElementById(containerId);
            if (container) {
                container.remove(); // Remove visually from UI immediately
            }
            
            // Track the deleted image path
            imagesDeletedList.push(imagePath);
            
            // Find or create the hidden container to store inputs inside the form
            let containerInputs = document.getElementById('removedImagesContainer');
            if (!containerInputs) {
                containerInputs = document.createElement('div');
                containerInputs.id = 'removedImagesContainer';
                document.getElementById('vehicleEditForm').appendChild(containerInputs);
            }
            
            // Rebuild hidden inputs with the correct name matching the PHP backend
            containerInputs.innerHTML = '';
            imagesDeletedList.forEach(name => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'delete_existing_images[]'; // 777 MATCHES PHP EXPECTED KEY
                hiddenInput.value = name;
                containerInputs.appendChild(hiddenInput);
            });
        }
    }

    // Clear trackers when a brand new modal window populates to avoid crossover leaks
    function resetEditModalTracker() {
        editImageStashArray = [];
        imagesDeletedList = [];
        const containerInputs = document.getElementById('removedImagesContainer');
        if (containerInputs) containerInputs.innerHTML = '';
        renderEditStashGallery();
    }

    // Attach hooks to clean up fields whenever edit vehicle activation hooks click open
    document.querySelectorAll('[data-bs-target="#vehicleEditModal"], .edit-car-btn-trigger').forEach(btn => {
        btn.addEventListener('click', resetEditModalTracker);
    });

    // Bind payload to form data context on submission
    document.getElementById('vehicleEditForm').addEventListener('submit', function(e) {
        const dataTransfer = new DataTransfer();
        editImageStashArray.forEach(file => {
            dataTransfer.items.add(file);
        });
        
        const fileInput = document.getElementById('editStashImageInput');
        fileInput.name = "car_images[]"; 
        fileInput.files = dataTransfer.files;
    });

    // Drag highlights
    const editDropzone = document.getElementById('editDropzoneContainer');
    if (editDropzone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            editDropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                editDropzone.style.backgroundColor = '#e2e8f0';
            }, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            editDropzone.addEventListener(eventName, () => {
                editDropzone.style.backgroundColor = '#f8fafc';
            }, false);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const inlineUploadSlot = document.getElementById('inlineUploadSlot');
        const fileInput = document.getElementById('editStashImageInput');

        if (inlineUploadSlot && fileInput) {
            inlineUploadSlot.addEventListener('click', function(e) {
                e.stopPropagation();
                fileInput.click();
            });
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        // 1. Initialize Bootstrap Popovers for all Car Stash Buttons
        const popoverTriggerList = [].slice.call(document.querySelectorAll('.car-gallery-popover-btn'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, {
                trigger: 'click',
                sanitize: false 
            });
        });

        // 2. Global Event Delegation to catch clicks on dynamically generated preview thumbnails inside popovers
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('car-stash-thumb')) {
                const carId = e.target.getAttribute('data-car-id');
                const targetFilename = e.target.getAttribute('data-full-img');
                
                const masterImg = document.getElementById(`master_car_img_${carId}`);
                if (masterImg) {
                    masterImg.src = `../../public/assets/images/cars/${targetFilename}`;
                }

                const popoverBody = e.target.closest('.popover-body');
                if (popoverBody) {
                    popoverBody.querySelectorAll('.car-stash-thumb').forEach(thumb => {
                        thumb.classList.remove('border-primary', 'border-2');
                        thumb.classList.add('border-light');
                    });
                }
                e.target.classList.remove('border-light');
                e.target.classList.add('border-primary', 'border-2');
            }
        });

        // 3. Auto-close a popover if the operator clicks anywhere outside it
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.car-gallery-popover-btn') && !e.target.closest('.popover')) {
                popoverTriggerList.forEach(function (popoverTriggerEl) {
                    const instance = bootstrap.Popover.getInstance(popoverTriggerEl);
                    if (instance) instance.hide();
                });
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const galleryModalEl = document.getElementById('fleetGalleryModal');
        if (!galleryModalEl) return;
        const bsGalleryModal = new bootstrap.Modal(galleryModalEl);
        
        const spotlightViewer = document.getElementById('modalSpotlightViewer');
        const modalTitle = document.getElementById('fleetGalleryModalLabel');
        const thumbnailsStripe = document.getElementById('modalThumbnailsStripe');

        let currentActiveCarId = null;

        document.addEventListener('click', function(e) {
            const triggerBtn = e.target.closest('.car-gallery-modal-trigger');
            if (!triggerBtn) return;

            currentActiveCarId = triggerBtn.getAttribute('data-car-id');
            const carName = triggerBtn.getAttribute('data-car-name');
            const rawImages = triggerBtn.getAttribute('data-images');

            const imagesArray = rawImages ? rawImages.split(',') : ['default.png'];
            
            modalTitle.textContent = `${carName} - Photo Gallery Stash`;
            spotlightViewer.src = `../../public/assets/images/cars/${imagesArray[0]}`;
            thumbnailsStripe.innerHTML = '';

            imagesArray.forEach((filename, idx) => {
                const isFirst = (idx === 0);
                
                const wrapper = document.createElement('div');
                wrapper.className = 'position-relative m-1 rounded overflow-hidden shadow-sm border';
                wrapper.style.width = '100px';
                wrapper.style.height = '75px';

                const thumbImg = document.createElement('img');
                thumbImg.src = `../../public/assets/images/cars/${filename}`;
                thumbImg.className = `w-100 h-100 modal-gallery-thumb ${isFirst ? 'border-primary border-2' : 'border-transparent'}`;
                thumbImg.style.objectFit = 'cover';
                thumbImg.style.cursor = 'pointer';
                thumbImg.setAttribute('data-filename', filename);

                const overlay = document.createElement('div');
                overlay.className = 'position-absolute bottom-0 start-0 w-100 bg-dark bg-opacity-75 text-white d-flex align-items-center justify-content-center d-none';
                overlay.style.cursor = 'pointer';
                overlay.style.height = '24px';
                overlay.style.zIndex = '5';
                overlay.style.fontSize = '10px';
                overlay.style.fontWeight = '600';
                overlay.innerHTML = '<span>Set as Main</span>';

                if (isFirst) {
                    overlay.classList.remove('d-none');
                    overlay.className = 'position-absolute bottom-0 start-0 w-100 bg-primary bg-opacity-90 text-white d-flex align-items-center justify-content-center';
                    overlay.innerHTML = '<span>⭐ Current Main</span>';
                } else {
                    wrapper.addEventListener('mouseenter', function() {
                        if (!thumbImg.classList.contains('border-primary') && !overlay.classList.contains('bg-info')) {
                            overlay.classList.remove('d-none');
                            overlay.className = 'position-absolute bottom-0 start-0 w-100 bg-dark bg-opacity-75 text-white d-flex align-items-center justify-content-center';
                            overlay.innerHTML = '<span>Set as Main</span>';
                        }
                    });
                    wrapper.addEventListener('mouseleave', function() {
                        if (!thumbImg.classList.contains('border-primary')) {
                            overlay.classList.add('d-none');
                            overlay.className = 'position-absolute bottom-0 start-0 w-100 bg-dark bg-opacity-75 text-white d-flex align-items-center justify-content-center';
                            overlay.innerHTML = '<span>Set as Main</span>';
                        }
                    });
                }

                const handleSelectionClick = function(e) {
                    e.stopPropagation();

                    if (thumbImg.classList.contains('border-primary')) return;

                    const chosenFilename = thumbImg.getAttribute('data-filename');
                    spotlightViewer.src = `../../public/assets/images/cars/${chosenFilename}`;

                    if (!overlay.classList.contains('bg-info')) {
                        thumbnailsStripe.querySelectorAll('.modal-gallery-thumb').forEach(t => {
                            if (!t.classList.contains('border-primary')) {
                                const otherOverlay = t.nextSibling;
                                if (otherOverlay) {
                                    otherOverlay.className = 'position-absolute bottom-0 start-0 w-100 bg-dark bg-opacity-75 text-white d-flex align-items-center justify-content-center d-none';
                                    otherOverlay.innerHTML = '<span>Set as Main</span>';
                                }
                            }
                        });

                        overlay.classList.remove('d-none');
                        overlay.className = 'position-absolute bottom-0 start-0 w-100 bg-info text-dark d-flex align-items-center justify-content-center fw-bold animate-pulse';
                        overlay.innerHTML = '<span>✔️ Confirm?</span>';
                        return;
                    }

                    const outerMasterImg = document.getElementById(`master_car_img_${currentActiveCarId}`);
                    if (outerMasterImg) {
                        outerMasterImg.src = `../../public/assets/images/cars/${chosenFilename}`;
                    }

                    thumbnailsStripe.querySelectorAll('.modal-gallery-thumb').forEach(t => {
                        t.classList.remove('border-primary', 'border-2');
                        t.classList.add('border-transparent');
                        
                        const otherOverlay = t.nextSibling;
                        if (otherOverlay) {
                            otherOverlay.className = 'position-absolute bottom-0 start-0 w-100 bg-dark bg-opacity-75 text-white d-flex align-items-center justify-content-center d-none';
                            otherOverlay.innerHTML = '<span>Set as Main</span>';
                        }
                    });

                    thumbImg.classList.remove('border-transparent');
                    thumbImg.classList.add('border-primary', 'border-2');
                    overlay.className = 'position-absolute bottom-0 start-0 w-100 bg-primary bg-opacity-90 text-white d-flex align-items-center justify-content-center';
                    overlay.innerHTML = '<span>⭐ Current Main</span>';
                    overlay.classList.remove('d-none');

                    const formData = new FormData();
                    formData.append('action', 'set_main_image');
                    formData.append('car_id', currentActiveCarId);
                    formData.append('image_name', chosenFilename);

                    fetch('process/car_actions.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            let currentString = triggerBtn.getAttribute('data-images');
                            let currentArr = currentString.split(',');
                            let itemIndex = currentArr.indexOf(chosenFilename);
                            if (itemIndex > -1) {
                                currentArr.splice(itemIndex, 1);
                                currentArr.unshift(chosenFilename);
                                triggerBtn.setAttribute('data-images', currentArr.join(','));
                            }
                        } else {
                            console.error("Database update error:", data.message);
                        }
                    })
                    .catch(err => console.error("AJAX Error:", err));
                };

                thumbImg.addEventListener('click', handleSelectionClick);
                overlay.addEventListener('click', handleSelectionClick);

                wrapper.appendChild(thumbImg);
                wrapper.appendChild(overlay);
                thumbnailsStripe.appendChild(wrapper);
            });

            bsGalleryModal.show();
        });
    });


    function markExistingForDeletion(imgName, containerId) {
    // 1. Double check with the user before wiping files
    if (confirm("Are you sure you want to permanently delete this photo?")) {
        
        // 2. Locate the hidden stash container
        const container = document.getElementById('removedImagesContainer');
        
        if (container) {
            // 3. Create a hidden input that PHP expects: delete_existing_images[]
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'delete_existing_images[]';
            hiddenInput.value = imgName;
            
            // 4. Inject it into the form so it posts to car_actions.php
            container.appendChild(hiddenInput);
            
            // 5. Visually eliminate the image card from the modal screen
            const visualCard = document.getElementById(containerId);
            if (visualCard) {
                visualCard.remove();
            }
            
            // 6. Update the counter badge to show something changed
            const badge = document.getElementById('editStashCountBadge');
            if (badge) {
                badge.classList.remove('bg-primary');
                badge.classList.add('bg-danger');
                badge.innerText = "Changes Pending Save";
            }
        } else {
            console.error("Critical Error: 'removedImagesContainer' div was not found in the DOM.");
        }
    }
}
</script>