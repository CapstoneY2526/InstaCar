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

// Fetch car pricing rules from database
$query = "SELECT * FROM cars WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $car_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$car = mysqli_fetch_assoc($stmt);

if ($car) {
    // Return pricing information
    echo json_encode([
        'success' => true,
        'pricing' => [
            'id' => $car['id'],
            'price_per_day' => floatval($car['price_per_day']),
            'brand' => $car['brand'],
            'model' => $car['model'],
            'type' => $car['type']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Car not found']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>