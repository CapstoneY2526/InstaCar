<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'operator') {
    ?>
    <script>window.location.href = "../../login.php";</script>
    <?php
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$current_year = date('Y');
$pageTitle = 'My Revenue';

$selected_car = isset($_GET['car_id']) ? (int)$_GET['car_id'] : 'all';

$my_cars_res = mysqli_query($conn, "SELECT id, brand, model, plate_number FROM cars WHERE user_id = $user_id");

$car_filter = ($selected_car !== 'all') ? "AND c.id = $selected_car" : "";

$query = "SELECT 
            c.brand, c.model, c.plate_number,
            MONTH(b.created_at) as month_num,
            SUM(COALESCE(p.total_gross, b.total_price)) as gross,
            SUM(COALESCE(p.total_net, b.total_price * 0.8)) as my_share
          FROM cars c
          INNER JOIN bookings b ON c.id = b.car_id
          LEFT JOIN booking_payments p ON b.id = p.booking_id
          WHERE c.user_id = $user_id 
          AND YEAR(b.created_at) = $current_year 
          AND b.status = 'Completed'
          $car_filter
          GROUP BY c.id, MONTH(b.created_at)
          ORDER BY month_num DESC";

$res = mysqli_query($conn, $query);

$revenue_data = [];
$total_payout = 0;

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $car_key = "$row[brand] $row[model] ($row[plate_number])";
        $revenue_data[$car_key][$row['month_num']] = $row;
        $total_payout += $row['my_share'];
    }
}

$months = [1=>"JAN", 2=>"FEB", 3=>"MAR", 4=>"APR", 5=>"MAY", 6=>"JUN", 7=>"JUL", 8=>"AUG", 9=>"SEP", 10=>"OCT", 11=>"NOV", 12=>"DEC"];
?>

<?php require_once __DIR__ . '/../components/head.php'; ?>

<style>
    body { 
        background-color: #f8fafc; 
        font-family: 'Poppins', sans-serif; 
    }
    
    .revenue-summary { 
        border-radius: 1rem; 
        background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); 
    }

    /* Mobile Entries Layout Transformation Rules */
    @media (max-width: 767.98px) {
        .revenue-table-wrapper {
            background: transparent !important;
            padding: 0 !important;
        }

        .revenue-table {
            display: block;
        }

        .revenue-table thead { 
            display: none; 
        }

        .revenue-table tbody {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 12px;
        }

        .revenue-table tr { 
            display: block; 
            background-color: #ffffff;
            border: 1px solid #e2e8f0 !important; 
            border-radius: 12px;
            padding: 16px !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
            margin-bottom: 0 !important;
        }

        .revenue-table td { 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            border: none !important;
            padding: 6px 0 !important;
            text-align: right;
        }

        .revenue-table td:not(:last-child) {
            border-bottom: 1px dashed #f1f5f9 !important;
            padding-bottom: 10px !important;
            margin-bottom: 4px;
        }

        .revenue-table td:last-child {
            padding-top: 10px !important;
        }

        .revenue-table td::before { 
            content: attr(data-label); 
            font-weight: 600; 
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
        }
        
        .revenue-table td.ps-4 {
            padding-left: 0 !important;
        }
        .revenue-table td.pe-4 {
            padding-right: 0 !important;
        }
    }
</style>

<div class="container-fluid">
    <div class="row">
        <!-- Dashboard Sidebar Container Alignment -->
        <div class="col-md-2 p-0 sidebar-container">
            <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
        </div>

        <!-- Main Workspace Area Wrapper -->
        <div class="col-md-10 p-0 d-flex flex-column main-content" style="background: #f8fafc; min-height: 100vh;">
            <?php require_once __DIR__ . '/../components/header.php'; ?>

            <div class="p-3 p-md-4">
                <div class="row g-3 mb-4 align-items-center">
                    <div class="col-12 col-md-6">
                        <h3 class="fw-bold mb-0">Earnings Report</h3>
                        <p class="text-muted small mb-0">Financial performance for <?= $current_year ?></p>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="revenue-summary text-white p-3 shadow-sm d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-uppercase fw-bold" style="font-size: 0.65rem; opacity: 0.9; letter-spacing: 0.5px;">Total Net Payout (80%)</small>
                                <h3 class="fw-bold mb-0">₱<?= number_format($total_payout, 2) ?></h3>
                            </div>
                            <i class="bi bi-wallet2 fs-1 opacity-25"></i>
                        </div>
                    </div>
                </div>

                <!-- Filter Controls Form Panel -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-3">
                        <form method="GET" class="row g-2">
                            <div class="col-8 col-md-10">
                                <select name="car_id" class="form-select border-0 bg-light py-2" onchange="this.form.submit()">
                                    <option value="all">All Vehicles</option>
                                    <?php 
                                    $my_cars_res->data_seek(0);
                                    while($car = $my_cars_res->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $car['id'] ?>" <?= $selected_car == $car['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($car['brand']) ?> <?= htmlspecialchars($car['model']) ?> (<?= htmlspecialchars($car['plate_number']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-4 col-md-2">
                                <a href="revenue.php" class="btn btn-outline-secondary w-100 border-0 bg-light py-2">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (empty($revenue_data)): ?>
                    <div class="text-center py-5 bg-white rounded-4 shadow-sm border-0 my-4">
                        <i class="bi bi-cash-stack text-muted opacity-25" style="font-size: 3.5rem;"></i>
                        <h5 class="fw-bold mt-3 text-dark">No Earnings History</h5>
                        <p class="text-muted small">No completed bookings found for this vehicle selection matching <?= $current_year ?>.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($revenue_data as $car_name => $months_list): ?>
                        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                            <div class="card-header bg-white py-3 border-0">
                                <div class="d-flex align-items-center">
                                    <div class="icon-box bg-primary-subtle text-primary me-2 rounded-circle" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="bi bi-car-front-fill"></i>
                                    </div>
                                    <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($car_name) ?></h6>
                                </div>
                            </div>
                            <div class="table-responsive-md revenue-table-wrapper">
                                <table class="table table-hover mb-0 align-middle revenue-table">
                                    <thead class="bg-light small text-uppercase fw-bold text-muted">
                                        <tr>
                                            <th class="ps-4 py-3">Month</th>
                                            <th class="text-md-center py-3">Gross Sales</th>
                                            <th class="text-md-end pe-4 py-3">My Payout</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($months as $num => $name): 
                                            if (isset($months_list[$num])): 
                                                $m = $months_list[$num];
                                        ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-dark" data-label="Month"><?= $name ?></td>
                                                <td class="text-md-center text-muted" data-label="Gross">₱<?= number_format($m['gross'], 2) ?></td>
                                                <td class="text-md-end fw-bold text-primary pe-4" data-label="Payout">₱<?= number_format($m['my_share'], 2) ?></td>
                                            </tr>
                                        <?php endif; endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Dashboard Sticky Footer Area Alignment -->
            <div class="mt-auto">
                <?php require_once __DIR__ . '/../components/footer.php'; ?>
            </div>
        </div>
    </div>
</div>