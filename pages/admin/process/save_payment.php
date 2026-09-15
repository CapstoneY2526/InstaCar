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
    $j_del  = floatval($_POST['jer_delivery_fee'] ?? 0);   // Staff delivery fee
    $pick   = floatval($_POST['pickup_fee'] ?? 0);
    $j_pick = floatval($_POST['jer_pickup_fee'] ?? 0);     // Staff pickup fee
    $fuel   = floatval($_POST['fuel'] ?? 0);
    $driver = floatval($_POST['driver_fee'] ?? 0);
    $damage = floatval($_POST['damage_fee'] ?? 0);
    $agent  = floatval($_POST['agent_fee'] ?? 0);
    $others = floatval($_POST['others'] ?? 0);
    
    // Capture remarks
    $remarks = $_POST['remarks'] ?? '';

    // ── GROSS REVENUE (everything the customer pays) ──
    // Includes: rent, extension, delivery, pickup, damage, other, carwash, fuel, driver
    $total_gross = $rent + $ext + $del + $pick + $damage + $others + $wash + $fuel + $driver;
    
    // ── NET TOTAL (Gross minus staff/agent payouts) ──
    // Deduct only what's paid out: staff delivery, staff pickup, agent fees
    $total_net = $total_gross - $j_del - $j_pick - $agent;

    // First, DELETE any existing record for this booking_id (in case of re-settlement)
    $delete_sql = "DELETE FROM booking_payments WHERE booking_id = ?";
    $stmtDel = $conn->prepare($delete_sql);
    if ($stmtDel) {
        $stmtDel->bind_param("i", $bid);
        $stmtDel->execute();
        $stmtDel->close();
    }
    
    // Then INSERT new record using a prepared statement
    $insert_sql = "INSERT INTO booking_payments 
                   (booking_id, daily_rent, carwash, extension_fee, delivery_fee, 
                    jer_delivery_fee, pickup_fee, jer_pickup_fee, fuel, driver_fee, 
                    damage_fee, agent_fee, others, total_gross, total_net, remarks) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_sql);
    if ($stmt) {
        // 1 int + 14 decimals + 1 string = 16 chars
        $type_string = "i" . str_repeat("d", 14) . "s";
        
        $stmt->bind_param($type_string, 
            $bid,          // i
            $rent,         // d 1
            $wash,         // d 2
            $ext,          // d 3
            $del,          // d 4
            $j_del,        // d 5
            $pick,         // d 6
            $j_pick,       // d 7
            $fuel,         // d 8
            $driver,       // d 9
            $damage,       // d 10
            $agent,        // d 11
            $others,       // d 12
            $total_gross,  // d 13
            $total_net,    // d 14
            $remarks       // s
        );
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "✅ Payment recorded successfully! Gross: ₱" . number_format($total_gross, 2) . " | Net: ₱" . number_format($total_net, 2);
        } else {
            $_SESSION['error'] = "Insert failed: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Failed to prepare statement: " . $conn->error;
    }

    header("Location: ../settlements.php");
    exit();
}

header("Location: ../settlements.php");
exit();
?>