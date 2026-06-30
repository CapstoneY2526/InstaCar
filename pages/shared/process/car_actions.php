<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once "../../../config/database.php";

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'operator'])) {
    exit("Unauthorized");
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$target_dir = "../../../public/assets/images/cars/";

// ADD CAR
if (isset($_POST['add_car'])) {
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $model = mysqli_real_escape_string($conn, $_POST['model']);
    $plate = mysqli_real_escape_string($conn, $_POST['plate_number']);
    $fuel = mysqli_real_escape_string($conn, $_POST['fuel_type']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $trans = mysqli_real_escape_string($conn, $_POST['transmission']);
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $cap = (int)$_POST['capacity'];

    // 1. DUPLICATE PLATE NUMBER CHECK
    $check_plate_query = "SELECT id FROM cars WHERE plate_number = '$plate' LIMIT 1";
    $check_plate_res = mysqli_query($conn, $check_plate_query);
    if (mysqli_num_rows($check_plate_res) > 0) {
        $_SESSION['error'] = "Registration failed! Plate number '$plate' is already registered in the system.";
        header("Location: ../cars.php");
        exit();
    }

    // 10 Hours Tier
    $price_10 = floatval($_POST['price_10_hours']);
    $op_rate_10 = floatval($_POST['operator_10_hours']); 

    // 12 Hours Tier
    $price_12 = floatval($_POST['price_12_hours']);
    $op_rate_12 = floatval($_POST['operator_12_hours']); 

    // 24 Hours Tier (Formerly Daily)
    $price_24 = floatval($_POST['price_24_hours']);
    $op_rate_24 = floatval($_POST['operator_24_hours']); 

    // Extension Rates Tiers
    $ext_1_6 = floatval($_POST['ext_price_1_6']);
    $ext_7_10 = floatval($_POST['ext_price_7_10']);
    $ext_11_12 = floatval($_POST['ext_price_11_12']);
    $ext_13_24 = floatval($_POST['ext_price_13_24']);

    // Multiple Upload Stash handling
    $uploaded_images = [];
    if (!empty($_FILES['car_images']['name'][0])) {
        foreach ($_FILES['car_images']['name'] as $key => $name) {
            $tmp_name = $_FILES['car_images']['tmp_name'][$key];
            $error = $_FILES['car_images']['error'][$key];
            
            if ($error === UPLOAD_ERR_OK) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $newName = "car_" . uniqid() . "_" . time() . "." . $ext;
                if (move_uploaded_file($tmp_name, $target_dir . $newName)) {
                    $uploaded_images[] = $newName;
                }
            }
        }
    }
    
    // Save images as comma-separated string paths
    $image_string = implode(',', $uploaded_images);

    $sql = "INSERT INTO cars (
                user_id, brand, model, plate_number, fuel_type, type, transmission, capacity, color, image_path, status,
                price_10_hours, operator_10_hours,
                price_12_hours, operator_12_hours,
                price_24_hours, operator_24_hours,
                ext_price_1_6, ext_price_7_10, ext_price_11_12, ext_price_13_24
            ) VALUES (
                $user_id, '$brand', '$model', '$plate', '$fuel', '$type', '$trans', $cap, '$color', '$image_string', 'Available',
                $price_10, $op_rate_10,
                $price_12, $op_rate_12,
                $price_24, $op_rate_24,
                $ext_1_6, $ext_7_10, $ext_11_12, $ext_13_24
            )";
    
    mysqli_query($conn, $sql) ? $_SESSION['success'] = "Added Successfully!" : $_SESSION['error'] = mysqli_error($conn);
    header("Location: ../cars.php"); exit();
}

// UPDATE CAR (Matches form intercept button OR hidden vehicleEditForm input contexts)
// UPDATE CAR (Matches form intercept button OR hidden vehicleEditForm input contexts)
if (isset($_POST['update_car']) || isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $model = mysqli_real_escape_string($conn, $_POST['model']);
    $plate = mysqli_real_escape_string($conn, $_POST['plate_number']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $fuel = mysqli_real_escape_string($conn, $_POST['fuel_type']);
    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $trans = mysqli_real_escape_string($conn, $_POST['transmission']);
    $color = mysqli_real_escape_string($conn, $_POST['color']);
    $cap = (int)$_POST['capacity'];
    
    // Ensure target directory is available within this scope if needed
    $target_dir = "../../../public/assets/images/cars/";

    // 2. DUPLICATE PLATE NUMBER CHECK ON UPDATE (Excluding itself)
    $check_plate_query = "SELECT id FROM cars WHERE plate_number = '$plate' AND id != $id LIMIT 1";
    $check_plate_res = mysqli_query($conn, $check_plate_query);
    if (mysqli_num_rows($check_plate_res) > 0) {
        $_SESSION['error'] = "Update failed! Plate number '$plate' is already assigned to another vehicle.";
        header("Location: ../cars.php");
        exit();
    }

    // 10 Hours Tier
    $price_10 = floatval($_POST['price_10_hours']);
    $op_rate_10 = floatval($_POST['operator_10_hours']); 

    // 12 Hours Tier
    $price_12 = floatval($_POST['price_12_hours']);
    $op_rate_12 = floatval($_POST['operator_12_hours']); 

    // 24 Hours Tier
    $price_24 = floatval($_POST['price_24_hours']);
    $op_rate_24 = floatval($_POST['operator_24_hours']); 

    // Extension Rates Tiers
    $ext_1_6 = floatval($_POST['ext_price_1_6']);
    $ext_7_10 = floatval($_POST['ext_price_7_10']);
    $ext_11_12 = floatval($_POST['ext_price_11_12']);
    $ext_13_24 = floatval($_POST['ext_price_13_24']);

    $auth = ($role === 'admin') ? "" : " AND user_id = $user_id";

    // Retrieve existing collection paths from database first
    $car_query = mysqli_query($conn, "SELECT image_path FROM cars WHERE id = $id $auth");
    $car_data = mysqli_fetch_assoc($car_query);
    if (!$car_data) {
        $_SESSION['error'] = "Car record not found or access denied.";
        header("Location: ../cars.php");
        exit();
    }
    
    // Parse existing images array
    $current_images = !empty($car_data['image_path']) ? array_map('trim', explode(',', $car_data['image_path'])) : [];

    // Process structural deletions captured from JavaScript stashes
    if (isset($_POST['delete_existing_images']) && is_array($_POST['delete_existing_images'])) {
        foreach ($_POST['delete_existing_images'] as $del_img) {
            $del_img = trim($del_img);
            if (($key = array_search($del_img, $current_images)) !== false) {
                // Remove from the tracking collection array
                unset($current_images[$key]);
                
                // Physically delete file from server directory storage
                $physical_file = $target_dir . $del_img;
                if (!empty($del_img) && $del_img !== 'default.png' && file_exists($physical_file)) {
                    @unlink($physical_file);
                }
            }
        }
    }

    // Process new files appended via dropzone/file input element
    if (!empty($_FILES['car_images']['name'][0])) {
        foreach ($_FILES['car_images']['name'] as $key => $name) {
            $tmp_name = $_FILES['car_images']['tmp_name'][$key];
            $error = $_FILES['car_images']['error'][$key];
            
            if ($error === UPLOAD_ERR_OK) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $newName = "car_" . uniqid() . "_" . time() . "." . $ext;
                if (move_uploaded_file($tmp_name, $target_dir . $newName)) {
                    $current_images[] = $newName;
                }
            }
        }
    }

    // Re-index collection array keys cleanly and save back as a comma-separated string
    $current_images = array_values($current_images);
    $final_image_string = implode(',', $current_images);
    
    $update = "UPDATE cars SET 
               brand='$brand', model='$model', plate_number='$plate', status='$status', fuel_type='$fuel', 
               type='$type', transmission='$trans', capacity=$cap, color='$color', image_path='$final_image_string',
               price_10_hours=$price_10, operator_10_hours=$op_rate_10,
               price_12_hours=$price_12, operator_12_hours=$op_rate_12,
               price_24_hours=$price_24, operator_24_hours=$op_rate_24,
               ext_price_1_6=$ext_1_6, ext_price_7_10=$ext_7_10, ext_price_11_12=$ext_11_12, ext_price_13_24=$ext_13_24
               WHERE id = $id $auth";

    if (mysqli_query($conn, $update)) {
        $_SESSION['success'] = "Updated Successfully";
    } else {
        $_SESSION['error'] = "Failed: " . mysqli_error($conn);
    }
    
    header("Location: ../cars.php"); 
    exit();
}

// DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $auth = ($role === 'admin') ? "" : " AND user_id = $user_id";
    
    // Optional: Fetch file names to unlink files when a vehicle is removed
    $car_query = mysqli_query($conn, "SELECT image_path FROM cars WHERE id = $id $auth");
    if ($car_data = mysqli_fetch_assoc($car_query)) {
        $images = explode(',', $car_data['image_path']);
        foreach ($images as $img) {
            $img = trim($img);
            if (file_exists($target_dir . $img) && $img != 'default.png') {
                @unlink($target_dir . $img);
            }
        }
    }

    mysqli_query($conn, "DELETE FROM cars WHERE id = $id $auth");
    $_SESSION['success'] = "Vehicle removed from fleet listing.";
    header("Location: ../cars.php"); exit();
}

// PERMANENTLY SET MAIN CAR IMAGE
if (isset($_POST['action']) && $_POST['action'] === 'set_main_image') {
    header('Content-Type: application/json');
    
    $car_id = (int)$_POST['car_id'];
    $chosen_img = mysqli_real_escape_string($conn, trim($_POST['image_name']));
    $auth = ($_SESSION['role'] === 'admin') ? "" : " AND user_id = " . (int)$_SESSION['user_id'];

    // 1. Fetch current image string
    $query = mysqli_query($conn, "SELECT image_path FROM cars WHERE id = $car_id $auth");
    if ($car = mysqli_fetch_assoc($query)) {
        $images = array_map('trim', explode(',', $car['image_path']));
        
        // 2. Move chosen image to the first slot if it exists in the collection
        if (($key = array_search($chosen_img, $images)) !== false) {
            unset($images[$key]); // Remove it from its old position
            array_unshift($images, $chosen_img); // Push it to the front of the array
            
            $new_image_path = implode(',', $images);
            
            if ($update) {
                echo json_encode(['success' => true, 'new_main' => $chosen_img]);
                exit();
            }
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Failed to change main photo']);
    exit();
}
?>