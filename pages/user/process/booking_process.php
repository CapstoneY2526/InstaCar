<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    $user_id     = (int)($_SESSION['user_id'] ?? 0);
    $car_id      = (int)($_POST['car_id'] ?? 0);
    $start_date  = mysqli_real_escape_string($conn, $_POST['start_date'] ?? '');
    $end_date    = mysqli_real_escape_string($conn, $_POST['end_date'] ?? '');
    $pickup_time = mysqli_real_escape_string($conn, $_POST['pickup_time'] ?? '');
    $return_time = mysqli_real_escape_string($conn, $_POST['return_time'] ?? '');
    
    if ($user_id <= 0 || $car_id <= 0) {
        $_SESSION['error'] = "Invalid request.";
        header("Location: ../cars.php");
        exit();
    }

    // --- CALCULATE DURATION IN HOURS ---
    $start_datetime = new DateTime($start_date . ' ' . $pickup_time);
    $end_datetime   = new DateTime($end_date . ' ' . $return_time);
    
    $interval = $start_datetime->diff($end_datetime);
    $hours    = ($interval->days * 24) + $interval->h + ($interval->i / 60);
    
    if ($hours < 10) {
        $_SESSION['error'] = "Minimum booking duration is 10 hours. Your booking is only " . round($hours, 1) . " hour(s).";
        header("Location: ../cars.php");
        exit();
    }
    
    // --- GET CAR PRICING FROM SCHEMA ---
    $car_sql    = "SELECT price_10_hours, price_12_hours, price_24_hours, ext_price_1_6, ext_price_7_10, ext_price_11_12, ext_price_13_24, branch_id FROM cars WHERE id = $car_id";
    $car_result = mysqli_query($conn, $car_sql);
    $car        = mysqli_fetch_assoc($car_result);
    
    if (!$car) {
        $_SESSION['error'] = "Vehicle not found.";
        header("Location: ../cars.php");
        exit();
    }

    // --- SECURE SERVER-SIDE PRICE CALCULATION (Prevents frontend manipulation) ---
    function calculateServerPrice($hours, $p10, $p12, $p24, $ext1_6, $ext7_10, $ext11_12, $ext13_24) {
        $hours = round($hours, 2);
        if ($hours <= 10) return floatval($p10);
        if ($hours <= 12) return floatval($p12);
        if ($hours <= 24) return floatval($p24);
        
        $days       = floor($hours / 24);
        $extraHours = $hours - ($days * 24);
        $basePrice  = $days * floatval($p24);
        
        if ($extraHours > 0) {
            if ($extraHours <= 6) $basePrice += floatval($ext1_6);
            elseif ($extraHours <= 10) $basePrice += floatval($ext7_10);
            elseif ($extraHours <= 12) $basePrice += floatval($ext11_12);
            else $basePrice += floatval($ext13_24);
        }
        return round($basePrice, 2);
    }

    $total_price    = calculateServerPrice($hours, $car['price_10_hours'], $car['price_12_hours'], $car['price_24_hours'], $car['ext_price_1_6'], $car['ext_price_7_10'], $car['ext_price_11_12'], $car['ext_price_13_24']);
    $discount_price = 0;
    $down_payment   = 0;
    
    $branch_id = !empty($car['branch_id']) ? intval($car['branch_id']) : null;
    
    // --- FULFILLMENT & ADDRESSES ---
    $fulfillment_type = mysqli_real_escape_string($conn, trim($_POST['fulfillment_type'] ?? 'pickup'));
    $delivery_address = ($fulfillment_type === 'delivery' && !empty($_POST['delivery_address'])) 
        ? mysqli_real_escape_string($conn, trim($_POST['delivery_address'])) 
        : null;
        
    // NEW: Return Address (Always required as requested)
    $return_delivery_address = !empty($_POST['return_delivery_address']) 
        ? mysqli_real_escape_string($conn, trim($_POST['return_delivery_address'])) 
        : null;

    // --- SECURE FILE UPLOAD HANDLER ---
    function handleSecureUpload($file, $target_dir, $prefix) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > 5 * 1024 * 1024) return null; // 5MB limit
        
        // Validate MIME type securely
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowed_mimes)) return null;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Ensure extension matches allowed list
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) return null;

        $new_name = $prefix . "_" . uniqid() . "_" . time() . "." . $ext;
        
        if (move_uploaded_file($file['tmp_name'], $target_dir . $new_name)) {
            return $new_name;
        }
        return null;
    }

    // --- CREATE ORGANIZED SUBFOLDER FOR USER ---
    $base_upload_dir = __DIR__ . "/../../../public/assets/images/ids/";
    
    if (!is_dir($base_upload_dir)) {
        mkdir($base_upload_dir, 0755, true);
        file_put_contents($base_upload_dir . "index.html", "");
    }
    
    // Securely fetch user data
    $user_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data   = $user_result->fetch_assoc();
    $user_stmt->close();
    
    $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $user_data['name'] ?? 'user_' . $user_id);
    $user_folder = $folder_name . '_' . $user_id;
    
    $user_upload_dir = $base_upload_dir . $user_folder . '/';
    if (!is_dir($user_upload_dir)) {
        mkdir($user_upload_dir, 0755, true);
    }
    
    $primary_id_path    = "";
    $secondary_id_path  = "";
    $proof_billing_path = "";
    $proof_payment_path = "";
    
    // Process Uploads
    if (!empty($_FILES['primary_id']['name'])) {
        $uploaded = handleSecureUpload($_FILES['primary_id'], $user_upload_dir, 'primary');
        if ($uploaded) $primary_id_path = $user_folder . "/" . $uploaded;
    }
    
    if (empty($primary_id_path)) {
        $_SESSION['error'] = "Primary ID is required (JPG, PNG, or PDF only, max 5MB).";
        header("Location: ../cars.php");
        exit();
    }
    
    if (!empty($_FILES['secondary_id']['name'])) {
        $uploaded = handleSecureUpload($_FILES['secondary_id'], $user_upload_dir, 'secondary');
        if ($uploaded) $secondary_id_path = $user_folder . "/" . $uploaded;
    }
    
    if (!empty($_FILES['proof_of_billing']['name'])) {
        $uploaded = handleSecureUpload($_FILES['proof_of_billing'], $user_upload_dir, 'billing');
        if ($uploaded) $proof_billing_path = $user_folder . "/" . $uploaded;
    }

    if (!empty($_FILES['proof_of_payment']['name'])) {
        $uploaded = handleSecureUpload($_FILES['proof_of_payment'], $user_upload_dir, 'payment');
        if ($uploaded) $proof_payment_path = $user_folder . "/" . $uploaded;
    }
    
    // --- INSERT INTO DATABASE ---
    $stmt = $conn->prepare("INSERT INTO bookings (
                user_id, car_id, branch_id, start_date, end_date, 
                pickup_time, return_time, 
                total_price, discount_price, down_payment,
                status, booking_type, fulfillment_type, delivery_address, return_delivery_address,
                primary_id_path, secondary_id_path, proof_billing_path, proof_payment_path
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'online', ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "iiissssdddsssssss",
        $user_id, $car_id, $branch_id, $start_date, $end_date,
        $pickup_time, $return_time,
        $total_price, $discount_price, $down_payment,
        $fulfillment_type, $delivery_address, $return_delivery_address,
        $primary_id_path, $secondary_id_path, $proof_billing_path, $proof_payment_path
    );
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Booking submitted successfully! Duration: " . round($hours, 1) . " hours. Total: ₱" . number_format($total_price, 2);
    } else {
        $_SESSION['error'] = "Database Error: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: ../cars.php");
    exit();
}
?>