<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

if (isset($_POST['confirm_booking'])) {
    $user_id     = mysqli_real_escape_string($conn, $_SESSION['user_id']);
    $car_id      = mysqli_real_escape_string($conn, $_POST['car_id']);
    $start_date  = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date    = mysqli_real_escape_string($conn, $_POST['end_date']);
    $pickup_time = mysqli_real_escape_string($conn, $_POST['pickup_time']);
    $return_time = mysqli_real_escape_string($conn, $_POST['return_time']);
    
    // --- CALCULATE DURATION IN HOURS ---
    $start_datetime = new DateTime($start_date . ' ' . $pickup_time);
    $end_datetime = new DateTime($end_date . ' ' . $return_time);
    
    // Calculate duration in hours
    $interval = $start_datetime->diff($end_datetime);
    $hours = $interval->days * 24 + $interval->h + ($interval->i / 60);
    
    // --- CHECK MINIMUM HOURS REQUIREMENT (10 HOURS MINIMUM) ---
    if ($hours < 10) {
        $_SESSION['error'] = "Minimum booking duration is 10 hours. Your booking is only " . round($hours, 1) . " hour(s). Please adjust your schedule.";
        header("Location: ../cars.php");
        exit();
    }
    
    // --- GET UPDATED CAR PRICING FROM SCHEMA ---
    $car_sql = "SELECT price_10_hours, price_12_hours, price_24_hours FROM cars WHERE id = '$car_id'";
    $car_result = mysqli_query($conn, $car_sql);
    $car = mysqli_fetch_assoc($car_result);
    
    $price_10_hours = floatval($car['price_10_hours']);
    $price_12_hours = floatval($car['price_12_hours']);
    $price_24_hours = floatval($car['price_24_hours']);
    
    // --- CALCULATE TOTAL USING EXACT SCHEMA TIERED PRICING ---
    $total_price = 0;
    
    if ($hours <= 10) {
        $total_price = $price_10_hours > 0 ? $price_10_hours : 1099;
    } 
    else if ($hours <= 12) {
        $total_price = $price_12_hours > 0 ? $price_12_hours : 1300;
    } 
    else {
        // Ceiling total calendar days matching backend standard fallback layout
        $days = ceil($hours / 24);
        $total_price = $days * ($price_24_hours > 0 ? $price_24_hours : 1500);
    }
    
    $total_price = round($total_price, 2);
    
    $discount_price = 0;
    $down_payment = 0;
    
    // --- CREATE ORGANIZED SUBFOLDER FOR USER using __DIR__ ---
    $base_upload_dir = __DIR__ . "/../../../public/assets/images/ids/";
    
    // Create base directory if not exists
    if (!is_dir($base_upload_dir)) {
        mkdir($base_upload_dir, 0755, true);
        file_put_contents($base_upload_dir . "index.html", "");
    }
    
    // Get user info for folder name
    $user_sql = "SELECT name, email FROM users WHERE id = '$user_id'";
    $user_result = mysqli_query($conn, $user_sql);
    $user_data = mysqli_fetch_assoc($user_result);
    
    // Create folder name based on user name + user ID
    $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $user_data['name'] ?? 'user_' . $user_id);
    $user_folder = $folder_name . '_' . $user_id;
    
    // Check if user subfolder already exists, if not create it
    $user_upload_dir = $base_upload_dir . $user_folder . '/';
    if (!is_dir($user_upload_dir)) {
        mkdir($user_upload_dir, 0755, true);
    }
    
    $primary_id_path = "";
    $secondary_id_path = "";
    $proof_billing_path = "";
    
    $booking_timestamp = time();
    
    // Upload Primary ID
    if (!empty($_FILES['primary_id']['name'])) {
        $ext = strtolower(pathinfo($_FILES['primary_id']['name'], PATHINFO_EXTENSION));
        $filename = "primary_" . $booking_timestamp . "." . $ext;
        $target_file = $user_upload_dir . $filename;
        if (move_uploaded_file($_FILES['primary_id']['tmp_name'], $target_file)) {
            $primary_id_path = $user_folder . "/" . $filename;
        }
    }
    
    if (empty($primary_id_path)) {
        $_SESSION['error'] = "Primary ID is required for booking.";
        header("Location: ../cars.php");
        exit();
    }
    
    // Upload Secondary ID (optional)
    if (!empty($_FILES['secondary_id']['name'])) {
        $ext = strtolower(pathinfo($_FILES['secondary_id']['name'], PATHINFO_EXTENSION));
        $filename = "secondary_" . $booking_timestamp . "." . $ext;
        $target_file = $user_upload_dir . $filename;
        if (move_uploaded_file($_FILES['secondary_id']['tmp_name'], $target_file)) {
            $secondary_id_path = $user_folder . "/" . $filename;
        }
    }
    
    // Upload Proof of Billing (optional)
    if (!empty($_FILES['proof_of_billing']['name'])) {
        $ext = strtolower(pathinfo($_FILES['proof_of_billing']['name'], PATHINFO_EXTENSION));
        $filename = "proof_" . $booking_timestamp . "." . $ext;
        $target_file = $user_upload_dir . $filename;
        if (move_uploaded_file($_FILES['proof_of_billing']['tmp_name'], $target_file)) {
            $proof_billing_path = $user_folder . "/" . $filename;
        }
    }
    
    $sql = "INSERT INTO bookings (
                user_id, car_id, start_date, end_date, 
                pickup_time, return_time, 
                total_price, discount_price, down_payment,
                status, booking_type,
                primary_id_path, secondary_id_path, proof_billing_path
            ) VALUES (
                '$user_id', '$car_id', '$start_date', '$end_date', 
                '$pickup_time', '$return_time', 
                '$total_price', '$discount_price', '$down_payment',
                'Pending', 'online',
                '$primary_id_path', '$secondary_id_path', '$proof_billing_path'
            )";
    
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Booking submitted successfully! Duration: " . round($hours, 1) . " hours. Total: ₱" . number_format($total_price, 2);
    } else {
        $_SESSION['error'] = "Database Error: " . mysqli_error($conn);
    }
    
    header("Location: ../cars.php");
    exit();
}
?>