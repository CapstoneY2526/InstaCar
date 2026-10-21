<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once "../../../config/database.php";
require_once __DIR__ . '/../../../config/branch_helper.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'operator'])) {
    exit("Unauthorized");
}

// CSRF Protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Invalid CSRF token.";
    header("Location: ../cars.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

// ---- Branch handling ----
$posted_branch = $_POST['branch_id'] ?? '';
if (($posted_branch === '' || $posted_branch === '0') && currentBranchId() !== null) {
    $posted_branch = currentBranchId();
}
$branch_id = ($posted_branch === '' || $posted_branch === '0') ? null : intval($posted_branch);

/**
 * Helper function to handle multi-file photo uploads (InfinityFree & Localhost compatible)
 *
 * @param array $fileInput Standard $_FILES array entry
 * @return array List of saved file names
 */
function handleCarImagesUpload($fileInput) {
    $uploadedNames = [];
    
    // 1. Check if running on Localhost with a subfolder named /car-rental/
    $localPath = $_SERVER['DOCUMENT_ROOT'] . '/car-rental/public/assets/images/cars/';
    
    // 2. Check standard InfinityFree / root production path
    $prodPath = $_SERVER['DOCUMENT_ROOT'] . '/public/assets/images/cars/';

    if (is_dir($localPath)) {
        $uploadDir = $localPath;
    } elseif (is_dir($prodPath)) {
        $uploadDir = $prodPath;
    } else {
        // Dynamic fallback: resolve relative to this car_actions.php file location
        $uploadDir = realpath(__DIR__ . '/../../public/assets/images/cars/') . '/';
    }

    // Auto-create directory with permissions if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (isset($fileInput['name']) && is_array($fileInput['name'])) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/jpg'];

        for ($i = 0; $i < count($fileInput['name']); $i++) {
            // Check for file upload errors (e.g. UPLOAD_ERR_INI_SIZE on InfinityFree limits)
            if ($fileInput['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName  = $fileInput['tmp_name'][$i];
                $mimeType = mime_content_type($tmpName);

                if (in_array($mimeType, $allowedTypes)) {
                    $ext = strtolower(pathinfo($fileInput['name'][$i], PATHINFO_EXTENSION));
                    $newFileName = 'car_' . time() . '_' . uniqid() . '.' . $ext;
                    $destination = $uploadDir . $newFileName;

                    if (move_uploaded_file($tmpName, $destination)) {
                        $uploadedNames[] = $newFileName;
                    }
                }
            }
        }
    }

    return $uploadedNames;
}

// ============================================================
// ADD CAR
// ============================================================
if (isset($_POST['add_car'])) {
    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $plate = strtoupper(trim($_POST['plate_number']));
    $fuel = $_POST['fuel_type'];
    $type = $_POST['type'];
    $trans = $_POST['transmission'];
    $color = $_POST['color'];
    $cap = (int)$_POST['capacity'];

    $assigned_user_id = $user_id;
    if ($role === 'admin' && isset($_POST['user_id']) && $_POST['user_id'] !== '') {
        $picked = (int)$_POST['user_id'];
        if ($picked > 0) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role IN ('operator', 'admin') LIMIT 1");
            $stmt->bind_param('i', $picked);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                $assigned_user_id = $picked;
            } else {
                $_SESSION['error'] = "Invalid operator selected.";
                header("Location: ../cars.php");
                exit();
            }
        }
    }

    // Check duplicate plate
    $stmt = $conn->prepare("SELECT id FROM cars WHERE plate_number = ? LIMIT 1");
    $stmt->bind_param('s', $plate);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $_SESSION['error'] = "Plate number '$plate' is already registered.";
        header("Location: ../cars.php");
        exit();
    }

    // Process uploaded file images
    $images = [];
    if (isset($_FILES['car_images'])) {
        $images = handleCarImagesUpload($_FILES['car_images']);
    }
    
    if (empty($images)) {
        $images = ['default.png'];
    }
    $image_string = implode(',', $images);

    $price_10 = floatval($_POST['price_10_hours'] ?? 0);
    $op_rate_10 = floatval($_POST['operator_10_hours'] ?? 0);
    $price_12 = floatval($_POST['price_12_hours'] ?? 0);
    $op_rate_12 = floatval($_POST['operator_12_hours'] ?? 0);
    $price_24 = floatval($_POST['price_24_hours'] ?? 0);
    $op_rate_24 = floatval($_POST['operator_24_hours'] ?? 0);
    $ext_1_6 = floatval($_POST['ext_price_1_6'] ?? 0);
    $ext_7_10 = floatval($_POST['ext_price_7_10'] ?? 0);
    $ext_11_12 = floatval($_POST['ext_price_11_12'] ?? 0);
    $ext_13_24 = floatval($_POST['ext_price_13_24'] ?? 0);

    $sql = "INSERT INTO cars (
        user_id, branch_id, brand, model, plate_number, fuel_type, type, transmission, capacity, color, image_path, status,
        price_10_hours, operator_10_hours, price_12_hours, operator_12_hours, price_24_hours, operator_24_hours,
        ext_price_1_6, ext_price_7_10, ext_price_11_12, ext_price_13_24
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    
    // Type definition string: 11 parameters before rates, then 10 doubles for price splits/extensions
    $add_types = "iisssssssis" . str_repeat("d", 10); 

    $stmt->bind_param($add_types, 
        $assigned_user_id, $branch_id, $brand, $model, $plate, $fuel, $type, $trans, $cap, $color, $image_string,
        $price_10, $op_rate_10, $price_12, $op_rate_12, $price_24, $op_rate_24, $ext_1_6, $ext_7_10, $ext_11_12, $ext_13_24
    );

    if ($stmt->execute()) {
        if ($assigned_user_id !== $user_id) {
            $opNameStmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $opNameStmt->bind_param('i', $assigned_user_id);
            $opNameStmt->execute();
            $opRow = $opNameStmt->get_result()->fetch_assoc();
            $ownerName = $opRow['name'] ?? 'operator';
            $_SESSION['success'] = "Car added successfully and assigned to $ownerName!";
        } else {
            $_SESSION['success'] = "Car added successfully!";
        }
    } else {
        $_SESSION['error'] = "Error: " . $conn->error;
    }

    header("Location: ../cars.php");
    exit();
}

// ============================================================
// UPDATE CAR
// ============================================================
if (isset($_POST['update_car']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    
    // Check authorization
    if ($role === 'admin') {
        $stmt = $conn->prepare("SELECT id, image_path FROM cars WHERE id = ?");
        $stmt->bind_param('i', $id);
    } else {
        $stmt = $conn->prepare("SELECT id, image_path FROM cars WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $user_id);
    }
    $stmt->execute();
    $car_res = $stmt->get_result()->fetch_assoc();
    
    if (!$car_res) {
        $_SESSION['error'] = "Car not found or access denied.";
        header("Location: ../cars.php");
        exit();
    }

    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $plate = strtoupper(trim($_POST['plate_number']));
    $fuel = $_POST['fuel_type'];
    $type = $_POST['type'];
    $trans = $_POST['transmission'];
    $color = $_POST['color'];
    $cap = (int)$_POST['capacity'];

    // Check duplicate plate (exclude current vehicle)
    $stmt = $conn->prepare("SELECT id FROM cars WHERE plate_number = ? AND id != ? LIMIT 1");
    $stmt->bind_param('si', $plate, $id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $_SESSION['error'] = "Plate number '$plate' is already assigned to another vehicle.";
        header("Location: ../cars.php");
        exit();
    }

    // IMAGE HANDLING FOR EXISTING STASH
    $current_images = [];
    if (!empty($car_res['image_path'])) {
        $current_images = array_filter(array_map('trim', explode(',', $car_res['image_path'])));
        $current_images = array_values($current_images);
    }
    if (empty($current_images)) $current_images = ['default.png'];
    
    // Handle requested photo deletions
    if (isset($_POST['delete_existing_images']) && is_array($_POST['delete_existing_images'])) {
        foreach ($_POST['delete_existing_images'] as $del_img) {
            $del_img = trim($del_img);
            if (empty($del_img)) continue;
            
            $key = array_search($del_img, $current_images);
            if ($key !== false) {
                unset($current_images[$key]);
            }
        }
        $current_images = array_values($current_images);
    }
    
    // Process newly uploaded file images
    $new_images = [];
    if (isset($_FILES['car_images'])) {
        $new_images = handleCarImagesUpload($_FILES['car_images']);
    }
    
    // Merge existing and newly uploaded images
    if (!empty($new_images)) {
        $def_key = array_search('default.png', $current_images);
        if ($def_key !== false) unset($current_images[$def_key]);
        
        foreach ($new_images as $new_img) {
            if (!in_array($new_img, $current_images)) {
                $current_images[] = $new_img;
            }
        }
        $current_images = array_values($current_images);
    }
    if (empty($current_images)) $current_images = ['default.png'];
    
    $current_images = array_values(array_unique($current_images));
    $image_string = implode(',', $current_images);

    // Rate calculations
    $price_10 = floatval($_POST['price_10_hours'] ?? 0);
    $op_rate_10 = floatval($_POST['operator_10_hours'] ?? 0);
    $price_12 = floatval($_POST['price_12_hours'] ?? 0);
    $op_rate_12 = floatval($_POST['operator_12_hours'] ?? 0);
    $price_24 = floatval($_POST['price_24_hours'] ?? 0);
    $op_rate_24 = floatval($_POST['operator_24_hours'] ?? 0);
    $ext_1_6 = floatval($_POST['ext_price_1_6'] ?? 0);
    $ext_7_10 = floatval($_POST['ext_price_7_10'] ?? 0);
    $ext_11_12 = floatval($_POST['ext_price_11_12'] ?? 0);
    $ext_13_24 = floatval($_POST['ext_price_13_24'] ?? 0);

    $sql = "UPDATE cars SET 
        branch_id = ?, brand = ?, model = ?, plate_number = ?, fuel_type = ?, type = ?, transmission = ?, 
        capacity = ?, color = ?, image_path = ?, price_10_hours = ?, operator_10_hours = ?, 
        price_12_hours = ?, operator_12_hours = ?, price_24_hours = ?, operator_24_hours = ?, 
        ext_price_1_6 = ?, ext_price_7_10 = ?, ext_price_11_12 = ?, ext_price_13_24 = ?
        WHERE id = ?";
    
    $types = "issssssis" . "s" . "dddddddddd" . "i";
    
    $params = [
        $branch_id, $brand, $model, $plate, $fuel, $type, $trans, $cap, $color, $image_string,
        $price_10, $op_rate_10, $price_12, $op_rate_12, $price_24, $op_rate_24, 
        $ext_1_6, $ext_7_10, $ext_11_12, $ext_13_24, $id
    ];

    if ($role !== 'admin') {
        $sql .= " AND user_id = ?";
        $types .= "i";
        $params[] = $user_id;
    }

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $_SESSION['error'] = "SQL Error: " . $conn->error;
        header("Location: ../cars.php");
        exit();
    }

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Vehicle updated successfully!";
    } else {
        $_SESSION['error'] = "Update failed: " . $stmt->error;
    }

    header("Location: ../cars.php");
    exit();
}

// ============================================================
// DELETE CAR
// ============================================================
if (isset($_POST['delete_car'])) {
    $id = (int)$_POST['delete_car'];
    
    if ($role === 'admin') {
        $stmt = $conn->prepare("SELECT image_path FROM cars WHERE id = ?");
        $stmt->bind_param('i', $id);
    } else {
        $stmt = $conn->prepare("SELECT image_path FROM cars WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $user_id);
    }
    $stmt->execute();
    $img_data = $stmt->get_result()->fetch_assoc();

    if ($img_data) {
        $stmt = $conn->prepare("DELETE FROM cars WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Vehicle deleted successfully.";
        } else {
            $_SESSION['error'] = "Delete failed: " . $conn->error;
        }
    } else {
        $_SESSION['error'] = "Car not found or access denied.";
    }

    header("Location: ../cars.php");
    exit();
}

// ============================================================
// SET MAIN IMAGE (AJAX)
// ============================================================
if (isset($_POST['action']) && $_POST['action'] === 'set_main_image') {
    header('Content-Type: application/json');
    
    $car_id = (int)$_POST['car_id'];
    $chosen_img = trim($_POST['image_name']);
    
    if ($role === 'admin') {
        $stmt = $conn->prepare("SELECT image_path FROM cars WHERE id = ?");
        $stmt->bind_param('i', $car_id);
    } else {
        $stmt = $conn->prepare("SELECT image_path FROM cars WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $car_id, $user_id);
    }
    $stmt->execute();
    $car = $stmt->get_result()->fetch_assoc();

    if ($car && !empty($car['image_path'])) {
        $images = array_filter(array_map('trim', explode(',', $car['image_path'])));
        $images = array_values($images);
        
        if (($key = array_search($chosen_img, $images)) !== false) {
            unset($images[$key]);
            array_unshift($images, $chosen_img);
            $new_path = implode(',', array_values($images));
            
            $update = $conn->prepare("UPDATE cars SET image_path = ? WHERE id = ?");
            $update->bind_param('si', $new_path, $car_id);
            
            if ($update->execute()) {
                echo json_encode(['success' => true, 'new_main' => $chosen_img]);
                exit();
            }
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Failed to change main photo']);
    exit();
}
?>