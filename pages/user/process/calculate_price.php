<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $car_id = mysqli_real_escape_string($conn, $_POST['car_id']);
    $hours = floatval($_POST['hours']);
    $days = intval($_POST['days']);
    $price_per_day = floatval($_POST['price_per_day']);
    
    // Get car pricing from database
    $sql = "SELECT 
                price_per_day, 
                operator_rate, 
                tie_up_rate, 
                extension_price 
            FROM cars 
            WHERE id = '$car_id'";
    
    $result = mysqli_query($conn, $sql);
    $car = mysqli_fetch_assoc($result);
    
    $operator_rate = floatval($car['operator_rate']);
    $tie_up_rate = floatval($car['tie_up_rate']);
    $extension_price = floatval($car['extension_price']);
    
    $total_price = 0;
    $rate_type = 'Standard Daily';
    $rate_applied = $price_per_day;
    $discount = 0;
    
    // Dynamic pricing based on duration
    if ($hours <= 6) {
        // Short term: hourly rate based on daily rate
        $hourly_rate = $price_per_day / 10;
        $total_price = $hourly_rate * $hours;
        $rate_type = 'Hourly Rate';
        $rate_applied = $hourly_rate;
    }
    elseif ($hours <= 10) {
        // 7-10 hours: use operator_rate if available
        if ($operator_rate > 0) {
            $total_price = $operator_rate;
            $rate_type = 'Operator Rate (7-10 hrs)';
            $rate_applied = $operator_rate;
        } else {
            $total_price = $price_per_day * 0.8;
            $rate_type = 'Discounted Rate (7-10 hrs)';
            $rate_applied = $price_per_day * 0.8;
        }
    }
    elseif ($hours <= 12) {
        // 11-12 hours: use tie_up_rate if available
        if ($tie_up_rate > 0) {
            $total_price = $tie_up_rate;
            $rate_type = 'Tie Up Rate (11-12 hrs)';
            $rate_applied = $tie_up_rate;
        } else {
            $total_price = $price_per_day * 0.9;
            $rate_type = 'Discounted Rate (11-12 hrs)';
            $rate_applied = $price_per_day * 0.9;
        }
    }
    else {
        // Long duration: multi-day pricing
        if ($days >= 7 && $tie_up_rate > 0) {
            $total_price = $tie_up_rate * $days;
            $rate_type = 'Tie Up Rate (7+ days)';
            $rate_applied = $tie_up_rate;
            $discount = ($price_per_day - $tie_up_rate) * $days;
        }
        elseif ($days >= 3 && $operator_rate > 0) {
            $total_price = $operator_rate * $days;
            $rate_type = 'Operator Rate (3+ days)';
            $rate_applied = $operator_rate;
            $discount = ($price_per_day - $operator_rate) * $days;
        }
        else {
            $total_price = $price_per_day * $days;
            $rate_type = 'Standard Daily Rate';
            $rate_applied = $price_per_day;
        }
    }
    
    echo json_encode([
        'success' => true,
        'total_price' => round($total_price, 2),
        'breakdown' => [
            'duration_hours' => round($hours, 2),
            'duration_days' => $days,
            'rate_type' => $rate_type,
            'rate_applied' => round($rate_applied, 2),
            'discount_applied' => round($discount, 2)
        ]
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>