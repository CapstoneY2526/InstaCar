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

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

// ---- Branch handling ----
// If the form didn't send a branch, fall back to the current view scope:
//   - Admin viewing "Cebu"     → tag new car as Cebu
//   - Admin viewing "All"      → stays NULL (they can pick manually in the form)
//   - Staff/operator           → their own branch
$posted_branch = $_POST['branch_id'] ?? '';
if (($posted_branch === '' || $posted_branch === '0') && currentBranchId() !== null) {
    $posted_branch = currentBranchId();
}
$branch_id = ($posted_branch === '' || $posted_branch === '0') ? 'NULL' : intval($posted_branch);

// CORRECT PATH: From /pages/shared/process/ to /public/assets/images/cars/
$target_dir = "../../../public/assets/images/cars/";

// Ensure upload directory exists
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// ============================================================
// ADD CAR
// ============================================================
if (isset($_POST['add_car'])) {
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $model = mysqli_real_escape_string($conn, $_POST['model']);
    $plate = strtoupper(trim(mysqli_real_escape_string($conn, $_POST['plate_number'])));
    $fuel = mysqli_real_escape_string($conn, $_POST['fuel_type']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $trans = mysqli_real_escape_string($conn, $_POST['transmission']);
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $cap = (int)$_POST['capacity'];

    // ============================================================
    // OPERATOR ASSIGNMENT (admin only)
    // ============================================================
    $assigned_user_id = $user_id;

    if ($role === 'admin' && isset($_POST['user_id']) && $_POST['user_id'] !== '') {
        $picked = (int)$_POST['user_id'];

        if ($picked > 0) {
            $validate = mysqli_query($conn, "SELECT id FROM users WHERE id = $picked AND role IN ('operator', 'admin') LIMIT 1");
            if ($validate && mysqli_num_rows($validate) > 0) {
                $assigned_user_id = $picked;
            } else {
                $_SESSION['error'] = "Invalid operator selected.";
                header("Location: ../cars.php");
                exit();
            }
        }
    }

    // Check duplicate plate
    $check = mysqli_query($conn, "SELECT id FROM cars WHERE plate_number = '$plate' LIMIT 1");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['error'] = "Plate number '$plate' is already registered.";
        header("Location: ../cars.php");
        exit();
    }

    // Upload images
    $images = [];
    if (isset($_FILES['car_images']) && !empty($_FILES['car_images']['name'][0])) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $files = $_FILES['car_images'];
        $count = count($files['name']);
        
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;
            
            $new_name = "car_" . uniqid() . "_" . time() . "_" . $i . "." . $ext;
            $dest = $target_dir . $new_name;
            
            if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                $images[] = $new_name;
            }
        }
    }
    
    if (empty($images)) {
        $images = ['default.png'];
    }
    $image_string = implode(',', $images);

    // Insert with branch_id
    $sql = "INSERT INTO cars (
        user_id, branch_id, brand, model, plate_number, fuel_type, type, transmission, capacity, color, image_path, status,
        price_10_hours, operator_10_hours,
        price_12_hours, operator_12_hours,
        price_24_hours, operator_24_hours,
        ext_price_1_6, ext_price_7_10, ext_price_11_12, ext_price_13_24
    ) VALUES (
        $assigned_user_id, $branch_id, '$brand', '$model', '$plate', '$fuel', '$type', '$trans', $cap, '$color', '$image_string', 'Available',
        " . floatval($_POST['price_10_hours'] ?? 0) . ", " . floatval($_POST['operator_10_hours'] ?? 0) . ",
        " . floatval($_POST['price_12_hours'] ?? 0) . ", " . floatval($_POST['operator_12_hours'] ?? 0) . ",
        " . floatval($_POST['price_24_hours'] ?? 0) . ", " . floatval($_POST['operator_24_hours'] ?? 0) . ",
        " . floatval($_POST['ext_price_1_6'] ?? 0) . ", " . floatval($_POST['ext_price_7_10'] ?? 0) . ",
        " . floatval($_POST['ext_price_11_12'] ?? 0) . ", " . floatval($_POST['ext_price_13_24'] ?? 0) . "
    )";

    if (mysqli_query($conn, $sql)) {
        if ($assigned_user_id !== $user_id) {
            $opName = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM users WHERE id = $assigned_user_id"));
            $ownerName = $opName['name'] ?? 'operator';
            $_SESSION['success'] = "Car added successfully and assigned to $ownerName!";
        } else {
            $_SESSION['success'] = "Car added successfully!";
        }
    } else {
        $_SESSION['error'] = "Error: " . mysqli_error($conn);
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
    $auth = ($role === 'admin') ? "" : " AND user_id = $user_id";
    $check = mysqli_query($conn, "SELECT id FROM cars WHERE id = $id $auth");
    if (mysqli_num_rows($check) === 0) {
        $_SESSION['error'] = "Car not found or access denied.";
        header("Location: ../cars.php");
        exit();
    }

    // Get form data
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $model = mysqli_real_escape_string($conn, $_POST['model']);
    $plate = strtoupper(trim(mysqli_real_escape_string($conn, $_POST['plate_number'])));
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $fuel = mysqli_real_escape_string($conn, $_POST['fuel_type']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $trans = mysqli_real_escape_string($conn, $_POST['transmission']);
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $cap = (int)$_POST['capacity'];

    // Check duplicate plate (exclude current)
    $check_plate = mysqli_query($conn, "SELECT id FROM cars WHERE plate_number = '$plate' AND id != $id LIMIT 1");
    if (mysqli_num_rows($check_plate) > 0) {
        $_SESSION['error'] = "Plate number '$plate' is already assigned to another vehicle.";
        header("Location: ../cars.php");
        exit();
    }

    // ============================================================
    // IMAGE HANDLING
    // ============================================================
    $current_images = [];
    $img_query = mysqli_query($conn, "SELECT image_path FROM cars WHERE id = $id");
    if ($img_data = mysqli_fetch_assoc($img_query)) {
        if (!empty($img_data['image_path'])) {
            $current_images = array_filter(array_map('trim', explode(',', $img_data['image_path'])));
            $current_images = array_values($current_images);
        }
    }
    
    if (empty($current_images)) {
        $current_images = ['default.png'];
    }
    
    // Handle deletions
    if (isset($_POST['delete_existing_images']) && is_array($_POST['delete_existing_images'])) {
        foreach ($_POST['delete_existing_images'] as $del_img) {
            $del_img = trim($del_img);
            if (empty($del_img)) continue;
            
            $key = array_search($del_img, $current_images);
            if ($key !== false) {
                unset($current_images[$key]);
                if ($del_img !== 'default.png') {
                    $file = $target_dir . $del_img;
                    if (file_exists($file)) {
                        @unlink($file);
                    }
                }
            }
        }
        $current_images = array_values($current_images);
    }
    
    // Upload new images
    $new_images = [];
    if (isset($_FILES['car_images']) && !empty($_FILES['car_images']['name'][0])) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $files = $_FILES['car_images'];
        $count = count($files['name']);
        
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;
            
            $new_name = "car_" . uniqid() . "_" . time() . "_" . $i . "." . $ext;
            $dest = $target_dir . $new_name;
            
            if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                $new_images[] = $new_name;
            }
        }
    }
    
    // Merge images
    if (!empty($new_images)) {
        $def_key = array_search('default.png', $current_images);
        if ($def_key !== false) {
            unset($current_images[$def_key]);
        }
        
        foreach ($new_images as $new_img) {
            if (!in_array($new_img, $current_images)) {
                $current_images[] = $new_img;
            }
        }
        $current_images = array_values($current_images);
    }
    
    if (empty($current_images)) {
        $current_images = ['default.png'];
    }
    
    $current_images = array_values(array_unique($current_images));
    $image_string = implode(',', $current_images);

    // Rates
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

    // Update database (with branch_id)
    $sql = "UPDATE cars SET 
        branch_id = $branch_id,
        brand = '$brand',
        model = '$model',
        plate_number = '$plate',
        status = '$status',
        fuel_type = '$fuel',
        type = '$type',
        transmission = '$trans',
        capacity = $cap,
        color = '$color',
        image_path = '$image_string',
        price_10_hours = $price_10,
        operator_10_hours = $op_rate_10,
        price_12_hours = $price_12,
        operator_12_hours = $op_rate_12,
        price_24_hours = $price_24,
        operator_24_hours = $op_rate_24,
        ext_price_1_6 = $ext_1_6,
        ext_price_7_10 = $ext_7_10,
        ext_price_11_12 = $ext_11_12,
        ext_price_13_24 = $ext_13_24
    WHERE id = $id $auth";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Vehicle updated successfully!";
    } else {
        $_SESSION['error'] = "Update failed: " . mysqli_error($conn);
    }

    header("Location: ../cars.php");
    exit();
}

// ============================================================
// DELETE CAR
// ============================================================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $auth = ($role === 'admin') ? "" : " AND user_id = $user_id";

    $img_query = mysqli_query($conn, "SELECT image_path FROM cars WHERE id = $id $auth");
    if ($img_data = mysqli_fetch_assoc($img_query)) {
        if (!empty($img_data['image_path'])) {
            $images = array_filter(array_map('trim', explode(',', $img_data['image_path'])));
            foreach ($images as $img) {
                if ($img !== 'default.png') {
                    $file = $target_dir . $img;
                    if (file_exists($file)) @unlink($file);
                }
            }
        }
    }

    $sql = "DELETE FROM cars WHERE id = $id $auth";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Vehicle deleted successfully.";
    } else {
        $_SESSION['error'] = "Delete failed: " . mysqli_error($conn);
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
    $auth = ($_SESSION['role'] === 'admin') ? "" : " AND user_id = " . (int)$_SESSION['user_id'];

    $query = mysqli_query($conn, "SELECT image_path FROM cars WHERE id = $car_id $auth");
    if ($car = mysqli_fetch_assoc($query)) {
        if (!empty($car['image_path'])) {
            $images = array_filter(array_map('trim', explode(',', $car['image_path'])));
            $images = array_values($images);
            
            if (($key = array_search($chosen_img, $images)) !== false) {
                unset($images[$key]);
                array_unshift($images, $chosen_img);
                $new_path = implode(',', array_values($images));
                
                $update = "UPDATE cars SET image_path = '$new_path' WHERE id = $car_id $auth";
                if (mysqli_query($conn, $update)) {
                    echo json_encode(['success' => true, 'new_main' => $chosen_img]);
                    exit();
                }
            }
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Failed to change main photo']);
    exit();
}
?>