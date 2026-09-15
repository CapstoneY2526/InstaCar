<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

// Anti-cache headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Auth Check
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
    
    // Clear staff delivery/pickup fees and log it in remarks
    $query = "UPDATE booking_payments p
              JOIN bookings b ON p.booking_id = b.id
              SET 
                p.remarks = CONCAT(COALESCE(p.remarks, ''), '\n[$today] Staff delivery/pickup fees cleared'),
                p.jer_delivery_fee = 0,
                p.jer_pickup_fee = 0
              WHERE b.car_id = $car_id 
                AND (p.jer_delivery_fee > 0 OR p.jer_pickup_fee > 0)";

    $result = mysqli_query($conn, $query);
    
    if ($result) {
        $affected = mysqli_affected_rows($conn);
        
        if ($affected > 0) {
            $_SESSION['success'] = "✅ Staff fees cleared successfully! ({$affected} payment record" . ($affected > 1 ? 's' : '') . " updated)";
        } else {
            $_SESSION['error'] = "No staff fees were pending for this vehicle.";
        }
    } else {
        $_SESSION['error'] = "Update Failed: " . mysqli_error($conn);
    }
    
    // 303 forces browser to GET (not reuse cached POST result) + timestamp busts cache
    header("Location: ../remittance.php?v=" . time(), true, 303);
    exit();
} else {
    header("Location: ../remittance.php", true, 303);
    exit();
}
?>