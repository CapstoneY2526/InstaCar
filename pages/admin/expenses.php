<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Auth Check - JS Redirect
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied.";
    ?>
    <script>
        window.stop();
        window.location.href = "../../index.php";
    </script>
    <?php
    exit();
}

$pageTitle = 'Car Expense Tracker';
$selected_car = $_GET['car_id'] ?? null;
$current_year = date('Y');

// 1. Fetch Monthly Data using Prepared Statement (Procedural)
$monthly_data = [];
if ($selected_car) {
    $query = "SELECT 
                MONTH(b.start_date) as month_num,
                COALESCE(SUM(p.daily_rent), 0) as daily_rent,
                COALESCE(SUM(p.carwash), 0) as carwash,
                COALESCE(SUM(p.extension_fee), 0) as extension_fee,
                COALESCE(SUM(p.delivery_fee), 0) as delivery_fee,
                COALESCE(SUM(p.jer_delivery_fee), 0) as jer_delivery_fee,
                COALESCE(SUM(p.pickup_fee), 0) as pickup_fee,
                COALESCE(SUM(p.jer_pickup_fee), 0) as jer_pickup_fee,
                COALESCE(SUM(p.fuel), 0) as fuel,
                COALESCE(SUM(p.driver_fee), 0) as driver_fee,
                COALESCE(SUM(p.damage_fee), 0) as damage_fee,
                COALESCE(SUM(p.agent_fee), 0) as agent_fee,
                COALESCE(SUM(p.others), 0) as others,
                COALESCE(SUM(p.total_gross), 0) as total_gross,
                COALESCE(SUM(p.total_net), 0) as total_net
              FROM bookings b
              JOIN booking_payments p ON b.id = p.booking_id
              WHERE b.car_id = ? AND YEAR(b.start_date) = ?
              GROUP BY MONTH(b.start_date)";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ii", $selected_car, $current_year);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        
        while ($row = mysqli_fetch_assoc($res)) {
            $monthly_data[$row['month_num']] = $row;
        }
        mysqli_stmt_close($stmt);
    } else {
        die("Expense Query Failed: " . mysqli_error($conn));
    }
}

// 2. Fetch Cars for the dropdown (Procedural)
$cars_res = mysqli_query($conn, "SELECT id, brand, model, plate_number FROM cars ORDER BY brand ASC");
$cars = [];
if ($cars_res) {
    while ($car_row = mysqli_fetch_assoc($cars_res)) {
        $cars[] = $car_row;
    }
}

$months = [
    1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR', 5 => 'MAY', 6 => 'JUN', 
    7 => 'JUL', 8 => 'AUG', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DEC'
];

$categories = [
    'daily_rent' => 'Daily Rent',
    'carwash' => 'Carwash',
    'extension_fee' => 'Extension Fee',
    'delivery_fee' => 'Delivery Fee',
    'jer_delivery_fee' => 'Jer. Delivery Fee',
    'pickup_fee' => 'Pickup Fee',
    'jer_pickup_fee' => 'Jer. Pick up Fee',
    'fuel' => 'Fuel',
    'driver_fee' => 'Driver Fee',
    'damage_fee' => 'Damage Fee',
    'agent_fee' => 'Agent Fee',
    'others' => 'Others'
];
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    /* Desktop-first Sticky Table Layout adjustments */
    @media (min-width: 992px) {
        .mobile-financial-card { display: none !important; }
    }

    /* Mobile/Tablet Overhaul: Hide the table, show clean vertical data cards */
    @media (max-width: 991.98px) {
        .responsive-table-card-container { display: none !important; }
        
        .mobile-financial-card {
            display: block;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            border: 1px solid #e2e8f0;
            margin-bottom: 16px;
            overflow: hidden;
        }

        .mobile-card-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #edf2f7;
            padding: 12px 16px;
        }

        .mobile-data-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            border-bottom: 1px solid #f7fafc;
            font-size: 0.85rem;
        }

        .mobile-data-row:last-child {
            border-bottom: none;
        }
    }

    /* Horizontal Scrollable Grid for top Monthly Summaries */
    .monthly-summary-grid {
        display: flex;
        overflow-x: auto;
        gap: 12px;
        padding-bottom: 12px;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }
    .summary-card {
        min-width: 115px;
        flex: 0 0 auto;
    }

    .extra-small { font-size: 0.72rem; }
    .table th { font-size: 0.75rem; letter-spacing: 0.5px; }
    .main-content { background: #f8fafc; min-height: 100vh; }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12 col-md-auto p-0">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <div class="col p-0 d-flex flex-column main-content">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4" style="flex: 1;">
                <div class="row align-items-center mb-4 g-3">
                    <div class="col-12 col-sm-6">
                        <h3 class="fw-bold mb-0">Financial Analytics</h3>
                        <p class="text-muted mb-0 small">Performance Review for Fiscal Year <?= $current_year ?></p>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="input-group ms-auto" style="max-width: 400px;">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-car-front text-primary"></i></span>
                            <select class="form-select border-start-0 shadow-sm" onchange="location.href='?car_id='+this.value">
                                <option value="">Select a Vehicle</option>
                                <?php foreach ($cars as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $selected_car == $c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['brand'] . ' ' . $c['model'] . ' (' . $c['plate_number'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <?php if (!$selected_car): ?>
                    <div class="card border-0 shadow-sm p-5 text-center rounded-4">
                        <div class="py-5">
                            <i class="bi bi-graph-up-arrow text-light display-1 mb-3"></i>
                            <h4 class="text-secondary">No Vehicle Selected</h4>
                            <p class="text-muted">Select a car from the dropdown to view monthly breakdown.</p>
                        </div>
                    </div>
                <?php else: ?>
                    
                    <div class="monthly-summary-grid mb-4">
                        <?php foreach ($months as $num => $name): 
                            $m_gross = $monthly_data[$num]['total_gross'] ?? 0;
                            $m_net = $monthly_data[$num]['total_net'] ?? 0;
                        ?>
                            <div class="card border-0 shadow-sm text-center p-2 rounded-3 summary-card">
                                <span class="fw-bold text-primary border-bottom mb-2 pb-1 extra-small"><?= $name ?></span>
                                <div class="mb-1">
                                    <small class="text-muted d-block extra-small">Gross</small>
                                    <span class="fw-bold small">₱<?= number_format($m_gross) ?></span>
                                </div>
                                <div>
                                    <small class="text-muted d-block extra-small">Net</small>
                                    <span class="fw-bold small <?= $m_net >= 0 ? 'text-success' : 'text-danger' ?>">
                                        ₱<?= number_format($m_net) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3 border-0">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-list-check me-2 text-primary"></i>Expense & Fee Distribution</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0 responsive-financial-table">
                                <thead class="bg-light text-uppercase">
                                    <tr>
                                        <th class="ps-4 py-3" style="min-width: 170px;">Category</th>
                                        <?php foreach($months as $name) echo "<th class='text-center'>$name</th>"; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $key => $label): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-secondary small"><?= $label ?></td>
                                            <?php foreach ($months as $num => $name): 
                                                $val = $monthly_data[$num][$key] ?? 0;
                                            ?>
                                                <td class="text-center <?= $val == 0 ? 'text-muted opacity-25' : 'fw-semibold' ?>" style="font-size: 0.85rem; min-width: 95px;">
                                                    <?= $val > 0 ? '₱'.number_format($val) : '-' ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-light fw-bold">
                                    <tr>
                                        <td class="ps-4">TOTAL NET REVENUE</td>
                                        <?php foreach ($months as $num => $name): 
                                            $net = $monthly_data[$num]['total_net'] ?? 0;
                                        ?>
                                            <td class="text-center <?= $net >= 0 ? 'text-success' : 'text-danger' ?>">
                                                ₱<?= number_format($net) ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php require_once __DIR__ . '/../components/footer.php'; ?>
        </div>
    </div>
</div>