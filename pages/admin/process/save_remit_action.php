<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access");
}

if (isset($_POST['car_id'])) {
    $car_id = intval($_POST['car_id']);
    $remit_amount = floatval($_POST['amount']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');
    $today = date('Y-m-d H:i:s');
    $date_only = date('Y-m-d');

    if ($remit_amount <= 0) {
        $_SESSION['error'] = "Invalid amount. Please enter a valid payout amount.";
        header("Location: ../remittance.php");
        exit();
    }

    // Find all payment records for this car with balance
    $query = "SELECT p.id, p.total_net, p.remitted_amount 
              FROM booking_payments p
              JOIN bookings b ON p.booking_id = b.id
              WHERE b.car_id = $car_id AND (p.remitted_amount < p.total_net OR p.remitted_amount IS NULL)
              ORDER BY b.end_date ASC";
    
    $result = mysqli_query($conn, $query);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        $_SESSION['error'] = "No pending balance found for this vehicle.";
        header("Location: ../remittance.php");
        exit();
    }

    $remaining = $remit_amount;
    $updated_count = 0;

    // Fix: Remove && $remaining > 0 from while condition, handle inside loop
    while ($payment = mysqli_fetch_assoc($result)) {
        if ($remaining <= 0) break;
        
        $payment_id = $payment['id'];
        $current_remitted = floatval($payment['remitted_amount'] ?? 0);
        $total_net = floatval($payment['total_net']);
        $balance = $total_net - $current_remitted;
        
        if ($balance <= 0) continue;

        if ($remaining >= $balance) {
            $pay_this = $balance;
            $remaining -= $balance;
        } else {
            $pay_this = $remaining;
            $remaining = 0;
        }

        $new_remitted = $current_remitted + $pay_this;
        
        // Fix: Proper string concatenation without extra quotes
        $remark_text = "\n[$date_only] Paid ₱" . number_format($pay_this, 2) . ": " . $remarks;
        
        $update_sql = "UPDATE booking_payments 
                       SET remitted_amount = $new_remitted,
                           remittance_date = '$today',
                           remarks = CONCAT(COALESCE(remarks, ''), '$remark_text')
                       WHERE id = $payment_id";
        
        if (mysqli_query($conn, $update_sql)) {
            $updated_count++;
        } else {
            error_log("Update failed: " . mysqli_error($conn));
        }
    }

    if ($updated_count > 0) {
        $_SESSION['success'] = "✅ Payout of ₱" . number_format($remit_amount, 2) . " processed successfully!";
    } else {
        $_SESSION['error'] = "Failed to process payout.";
    }
    
    header("Location: ../remittance.php");
    exit();
}

header("Location: ../remittance.php");
exit();
?>