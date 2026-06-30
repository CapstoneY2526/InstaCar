<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

// Auth Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access");
}

if (isset($_POST['save_payment'])) {
    $bid = intval($_POST['booking_id']);
    
    // Sanitize and Get Inputs
    $rent   = floatval($_POST['daily_rent'] ?? 0);
    $wash   = floatval($_POST['carwash'] ?? 0);
    $ext    = floatval($_POST['extension_fee'] ?? 0);
    $del    = floatval($_POST['delivery_fee'] ?? 0);
    $j_del  = floatval($_POST['jer_delivery_fee'] ?? 0);
    $pick   = floatval($_POST['pickup_fee'] ?? 0);
    $j_pick = floatval($_POST['jer_pickup_fee'] ?? 0);
    $fuel   = floatval($_POST['fuel'] ?? 0);
    $driver = floatval($_POST['driver_fee'] ?? 0);
    $damage = floatval($_POST['damage_fee'] ?? 0);
    $agent  = floatval($_POST['agent_fee'] ?? 0);
    $others = floatval($_POST['others'] ?? 0);

    // Calculate totals
    $total_gross = $rent + $ext + $del + $j_del + $pick + $j_pick + $fuel + $driver + $damage + $others;
    $total_net = $total_gross - ($j_del + $j_pick + $agent + $wash);

    // First, DELETE any existing record for this booking_id
    $delete_sql = "DELETE FROM booking_payments WHERE booking_id = $bid";
    mysqli_query($conn, $delete_sql);
    
    // Then INSERT new record
    $insert_sql = "INSERT INTO booking_payments 
                   (booking_id, daily_rent, carwash, extension_fee, delivery_fee, 
                    jer_delivery_fee, pickup_fee, jer_pickup_fee, fuel, driver_fee, 
                    damage_fee, agent_fee, others, total_gross, total_net) 
                   VALUES ($bid, $rent, $wash, $ext, $del, $j_del, $pick, $j_pick, 
                           $fuel, $driver, $damage, $agent, $others, $total_gross, $total_net)";
    
    if (mysqli_query($conn, $insert_sql)) {
        $_SESSION['success'] = "✅ Payment recorded successfully! Gross: ₱" . number_format($total_gross, 2) . " | Net: ₱" . number_format($total_net, 2);
    } else {
        $_SESSION['error'] = "Insert failed: " . mysqli_error($conn);
    }

    header("Location: ../settlements.php");
    exit();
}

header("Location: ../settlements.php");
exit();
?>