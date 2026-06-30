<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['car_id'])) {
    $car_id = intval($_POST['car_id']);
    $today = date('Y-m-d');
    
    // Simple update: just clear the Jerry fees and add note
    $query = "UPDATE booking_payments p
              JOIN bookings b ON p.booking_id = b.id
              SET 
                p.remarks = CONCAT(COALESCE(p.remarks, ''), '\n[$today] Jerry Delivery/Pickup fees cleared'),
                p.jer_delivery_fee = 0,
                p.jer_pickup_fee = 0
              WHERE b.car_id = $car_id AND (p.jer_delivery_fee > 0 OR p.jer_pickup_fee > 0)";

    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $_SESSION['success'] = "✅ Jerry delivery fees have been cleared successfully!";
    } else {
        $_SESSION['error'] = "Update Failed: " . mysqli_error($conn);
    }
    
    header("Location: ../remittance.php");
    exit();
} else {
    header("Location: ../remittance.php");
    exit();
}
?>