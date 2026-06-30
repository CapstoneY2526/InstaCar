<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// 1. Auth Check - Redirects if not an operator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'operator') {
    ?>
    <script>window.location.href = "../../index.php";</script>
    <?php 
    exit();
}

// 2. Security: Clean the inputs
$car_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];

// 3. Short Style: Fetch the car details in one line
// We check for both car ID and user ID to ensure the Operator owns this car
$car = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM cars WHERE id = $car_id AND user_id = $user_id"));

// 4. Handle case where car doesn't exist or isn't owned by this operator
if (!$car) {
    echo "<script>alert('Vehicle not found or access denied.'); window.location.href = 'dashboard.php';</script>";
    exit();
}

$pageTitle = $car['brand'] . ' Details';
require_once __DIR__ . '/../components/head.php';
?>

<div class="container py-5">
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0"><?= $car['brand'] . ' ' . $car['model'] ?></h2>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill">Back</a>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-6">
                <p class="mb-1 text-muted small fw-bold">PLATE NUMBER</p>
                <p class="fs-5"><?= $car['plate_number'] ?></p>
            </div>
            <div class="col-md-6">
                <p class="mb-1 text-muted small fw-bold">AVAILABILITY STATUS</p>
                <p class="fs-5">
                    <span class="badge rounded-pill <?= $car['status'] == 'Available' ? 'bg-success' : 'bg-warning' ?>">
                        <?= $car['status'] ?>
                    </span>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../components/footer.php'; ?>