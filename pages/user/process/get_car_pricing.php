<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get car_id from request
$car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;

if ($car_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid car ID']);
    exit();
}

// Fetch complete car specs and tiered pricing rules from database
$query = "SELECT 
            id, brand, model, type, transmission, capacity, color, plate_number, status, fuel_type,
            price_10_hours, operator_10_hours,
            price_12_hours, operator_12_hours,
            price_24_hours, operator_24_hours,
            ext_price_1_6, ext_price_7_10, ext_price_11_12, ext_price_13_24
          FROM cars WHERE id = ? LIMIT 1";

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $car_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $car = mysqli_fetch_assoc($result);

    if ($car) {
        // Return full updated tariff and specs structure
        echo json_encode([
            'success' => true,
            'car' => [
                'id' => intval($car['id']),
                'brand' => $car['brand'],
                'model' => $car['model'],
                'type' => $car['type'],
                'transmission' => $car['transmission'],
                'capacity' => intval($car['capacity']),
                'color' => $car['color'],
                'plate_number' => $car['plate_number'],
                'status' => $car['status'],
                'fuel_type' => $car['fuel_type']
            ],
            'pricing' => [
                'price_10_hours'    => floatval($car['price_10_hours']),
                'operator_10_hours' => floatval($car['operator_10_hours']),
                'price_12_hours'    => floatval($car['price_12_hours']),
                'operator_12_hours' => floatval($car['operator_12_hours']),
                'price_24_hours'    => floatval($car['price_24_hours']),
                'operator_24_hours' => floatval($car['operator_24_hours']),
                'ext_price_1_6'     => floatval($car['ext_price_1_6']),
                'ext_price_7_10'    => floatval($car['ext_price_7_10']),
                'ext_price_11_12'   => floatval($car['ext_price_11_12']),
                'ext_price_13_24'   => floatval($car['ext_price_13_24'])
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Car record not found']);
    }

    mysqli_stmt_close($stmt);
} else {
    echo json_encode(['success' => false, 'message' => 'Database query failure']);
}

mysqli_close($conn);
?>