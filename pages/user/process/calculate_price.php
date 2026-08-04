<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $car_id = intval($_POST['car_id'] ?? 0);
    
    if ($car_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Car ID']);
        exit();
    }

    // 1. Fetch updated tier prices and extension rates from the database
    $stmt = $conn->prepare("SELECT 
                price_10_hours, 
                price_12_hours, 
                price_24_hours, 
                ext_price_1_6, 
                ext_price_7_10, 
                ext_price_11_12, 
                ext_price_13_24 
            FROM cars 
            WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $car_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $car = $result->fetch_assoc();

    if (!$car) {
        echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
        exit();
    }

    $p10 = floatval($car['price_10_hours'] ?? 0);
    $p12 = floatval($car['price_12_hours'] ?? 0);
    $p24 = floatval($car['price_24_hours'] ?? 0);

    $ext_1_6   = floatval($car['ext_price_1_6'] ?? 0);
    $ext_7_10  = floatval($car['ext_price_7_10'] ?? 0);
    $ext_11_12 = floatval($car['ext_price_11_12'] ?? 0);
    $ext_13_24 = floatval($car['ext_price_13_24'] ?? 0);

    // 2. Parse Date & Time or Total Hours
    $total_hours = 0;

    if (!empty($_POST['start_datetime']) && !empty($_POST['end_datetime'])) {
        $start = new DateTime($_POST['start_datetime']);
        $end = new DateTime($_POST['end_datetime']);
        $diff = $start->diff($end);
        
        // Calculate total hours rounded up to nearest whole hour
        $diff_in_seconds = $end->getTimestamp() - $start->getTimestamp();
        if ($diff_in_seconds <= 0) {
            echo json_encode(['success' => false, 'message' => 'Return time must be after pickup time']);
            exit();
        }
        $total_hours = ceil($diff_in_seconds / 3600);
    } elseif (isset($_POST['hours'])) {
        $total_hours = ceil(floatval($_POST['hours']));
    }

    if ($total_hours <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid rental duration']);
        exit();
    }

    // 3. Calculation logic matching manual booking rules
    $base_price = 0;
    $rate_type = '';

    if ($total_hours <= 10) {
        $base_price = $p10;
        $rate_type = '10-Hour Tier Tariff';
    } elseif ($total_hours <= 12) {
        $base_price = $p12;
        $rate_type = '12-Hour Tier Tariff';
    } elseif ($total_hours <= 24) {
        $base_price = $p24;
        $rate_type = '24-Hour (Full Day) Tier Tariff';
    } else {
        // Multi-day & Overtime Extension Calculation
        $days = floor($total_hours / 24);
        $extra_hours = $total_hours % 24;
        
        $base_price = $days * $p24;
        $rate_type = "$days Day(s) Rate (₱" . number_format($p24, 2) . "/day)";

        if ($extra_hours > 0) {
            $extension_fee = 0;
            if ($extra_hours <= 6) {
                $extension_fee = $ext_1_6;
            } elseif ($extra_hours <= 10) {
                $extension_fee = $ext_7_10;
            } elseif ($extra_hours <= 12) {
                $extension_fee = $ext_11_12;
            } else {
                $extension_fee = $ext_13_24;
            }
            
            $base_price += $extension_fee;
            $rate_type .= " + $extra_hours hr(s) extension (₱" . number_format($extension_fee, 2) . ")";
        }
    }

    // 4. Handle optional Discounts & Down Payments
    $discount = floatval($_POST['discount_price'] ?? 0);
    $down_payment = floatval($_POST['down_payment'] ?? 0);
    
    $remaining_balance = max(0, $base_price - $discount - $down_payment);

    // Return clean JSON response
    echo json_encode([
        'success' => true,
        'total_price' => round($base_price, 2),
        'remaining_balance' => round($remaining_balance, 2),
        'breakdown' => [
            'duration_hours' => $total_hours,
            'rate_type' => $rate_type,
            'base_rate' => round($base_price, 2),
            'discount' => round($discount, 2),
            'down_payment' => round($down_payment, 2),
            'balance_due' => round($remaining_balance, 2)
        ]
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>