<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "../../../config/database.php";
require_once __DIR__ . '/../../../config/branch_helper.php';

// ============================================================
// InfinityFree WAF-safe entry point shim
// ============================================================
$__ct = $_SERVER['CONTENT_TYPE'] ?? '';
$__isJsonShim = false;

// ---- (A) JSON body wrapped in a single 'd' field ----
if (isset($_POST['d'])) {
    $J = json_decode($_POST['d'], true);
    if (is_array($J) && isset($J['op'], $J['t'])) {
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $J['t'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }

        // Map short keys to standard $_POST keys
        $_POST = [
            'csrf_token'         => $J['t'],
            'brand'              => $J['b']   ?? '',
            'model'              => $J['m']   ?? '',
            'transmission'       => $J['tr']  ?? '',
            'capacity'           => (int)($J['cap'] ?? 0),
            'color'              => $J['col'] ?? '',
            'type'               => $J['ty']  ?? '',
            'fuel_type'          => $J['fu']  ?? '',
            'plate_number'       => $J['pl']  ?? '',
            'branch_id'          => $J['br']  ?? '',
            'user_id'            => $J['uid'] ?? '',
            'price_10_hours'     => str_replace(',', '', (string)($J['p10'] ?? 0)),
            'operator_10_hours'  => str_replace(',', '', (string)($J['o10'] ?? 0)),
            'price_12_hours'     => str_replace(',', '', (string)($J['p12'] ?? 0)),
            'operator_12_hours'  => str_replace(',', '', (string)($J['o12'] ?? 0)),
            'price_24_hours'     => str_replace(',', '', (string)($J['p24'] ?? 0)),
            'operator_24_hours'  => str_replace(',', '', (string)($J['o24'] ?? 0)),
            'ext_price_1_6'      => str_replace(',', '', (string)($J['e16'] ?? 0)),
            'ext_price_7_10'     => str_replace(',', '', (string)($J['e710'] ?? 0)),
            'ext_price_11_12'    => str_replace(',', '', (string)($J['e1112'] ?? 0)),
            'ext_price_13_24'    => str_replace(',', '', (string)($J['e1324'] ?? 0)),
        ];

        if ($J['op'] === 'ac') {
            $_POST['add_car'] = 1;
        } elseif ($J['op'] === 'ec') {
            $_POST['update_car'] = 1;
            $_POST['id'] = (int)($J['id'] ?? 0);
        }

        $savedNames = waf_save_base64_images($J['imgs'] ?? []);
        if ($savedNames) {
            $_POST['_waf_images_csv'] = implode(',', $savedNames);
        }

        if (!empty($J['del']) && is_array($J['del'])) {
            $_POST['delete_existing_images'] = array_values($J['del']);
        }

        $__isJsonShim = true;
    }
}

// ---- (B) URL-encoded body (delete car / set main image) ----
if (isset($_POST['op'], $_POST['t'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['t'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $op = $_POST['op'];
    if ($op === 'dc') {
        $_POST['csrf_token'] = $_POST['t'];
        $_POST['delete_car'] = (int)($_POST['id'] ?? 0);
    } elseif ($op === 'sm') {
        $_POST['action']     = 'set_main_image';
        $_POST['csrf_token'] = $_POST['t'];
        $_POST['car_id']     = (int)($_POST['cid'] ?? 0);
        $_POST['image_name'] = $_POST['img'] ?? '';
    }
}

/**
 * Save base64-encoded images (from JSON payload) to the cars directory.
 */
function waf_save_base64_images($imgs) {
    if (empty($imgs) || !is_array($imgs)) return [];

    $localPath = $_SERVER['DOCUMENT_ROOT'] . '/car-rental/public/assets/images/cars/';
    $prodPath  = $_SERVER['DOCUMENT_ROOT'] . '/public/assets/images/cars/';

    if (is_dir($localPath)) {
        $uploadDir = $localPath;
    } elseif (is_dir($prodPath)) {
        $uploadDir = $prodPath;
    } else {
        $uploadDir = realpath(__DIR__ . '/../../public/assets/images/cars/') . '/';
    }

    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $saved = [];
    foreach ($imgs as $img) {
        if (empty($img['d'])) continue;
        if (!preg_match('#^data:image/(\w+);base64,#i', $img['d'], $m)) continue;

        $bin = base64_decode(substr($img['d'], strlen($m[0])));
        if ($bin === false || strlen($bin) === 0) continue;

        $ext = strtolower($m[1]);
        if ($ext === 'jpeg') $ext = 'jpg';
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) continue;

        $safe = 'car_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (file_put_contents($uploadDir . $safe, $bin) !== false) {
            $saved[] = $safe;
        }
    }
    return $saved;
}

// ---- Auth guard ----
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'operator'])) {
    if ($__isJsonShim || (isset($_POST['op']) && !empty($_POST['t']))) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    } else {
        echo "Unauthorized";
    }
    exit;
}

// CSRF Protection (for legacy direct POSTs that bypass the shim)
if (!$__isJsonShim) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid CSRF token.";
        header("Location: ../cars.php");
        exit();
    }
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

$isAjaxOp = $__isJsonShim || (isset($_POST['op']) && !empty($_POST['t']));

function respond($ok, $message = '', $extra = []) {
    global $isAjaxOp;
    if ($isAjaxOp) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
        exit;
    }
    $_SESSION[$ok ? 'success' : 'error'] = $message;
    header("Location: ../cars.php");
    exit;
}

// ---- Branch handling ----
$posted_branch = $_POST['branch_id'] ?? '';
if (($posted_branch === '' || $posted_branch === '0') && function_exists('currentBranchId') && currentBranchId() !== null) {
    $posted_branch = currentBranchId();
}
$branch_id = ($posted_branch === '' || $posted_branch === '0') ? null : intval($posted_branch);

/**
 * Classic multipart file uploads (fallback)
 */
function handleCarImagesUpload($fileInput) {
    $uploadedNames = [];
    $localPath = $_SERVER['DOCUMENT_ROOT'] . '/car-rental/public/assets/images/cars/';
    $prodPath  = $_SERVER['DOCUMENT_ROOT'] . '/public/assets/images/cars/';
    if (is_dir($localPath))      $uploadDir = $localPath;
    elseif (is_dir($prodPath))   $uploadDir = $prodPath;
    else                         $uploadDir = realpath(__DIR__ . '/../../public/assets/images/cars/') . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    if (isset($fileInput['name']) && is_array($fileInput['name'])) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/jpg'];
        for ($i = 0; $i < count($fileInput['name']); $i++) {
            if ($fileInput['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName  = $fileInput['tmp_name'][$i];
                $mimeType = mime_content_type($tmpName);
                if (in_array($mimeType, $allowedTypes)) {
                    $ext = strtolower(pathinfo($fileInput['name'][$i], PATHINFO_EXTENSION));
                    $newFileName = 'car_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
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
    $fuel  = $_POST['fuel_type'];
    $type  = $_POST['type'];
    $trans = $_POST['transmission'];
    $color = $_POST['color'];
    $cap   = (int)$_POST['capacity'];

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
                respond(false, "Invalid operator selected.");
            }
        }
    }

    $stmt = $conn->prepare("SELECT id FROM cars WHERE plate_number = ? LIMIT 1");
    $stmt->bind_param('s', $plate);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        respond(false, "Plate number '$plate' is already registered.");
    }

    $images = [];
    if (isset($_FILES['car_images'])) $images = handleCarImagesUpload($_FILES['car_images']);
    if (!empty($_POST['_waf_images_csv'])) {
        foreach (array_map('trim', explode(',', $_POST['_waf_images_csv'])) as $n) {
            if ($n !== '' && !in_array($n, $images)) $images[] = $n;
        }
    }
    if (empty($images)) $images = ['default.png'];
    $image_string = implode(',', $images);

    $price_10   = floatval($_POST['price_10_hours'] ?? 0);
    $op_rate_10 = floatval($_POST['operator_10_hours'] ?? 0);
    $price_12   = floatval($_POST['price_12_hours'] ?? 0);
    $op_rate_12 = floatval($_POST['operator_12_hours'] ?? 0);
    $price_24   = floatval($_POST['price_24_hours'] ?? 0);
    $op_rate_24 = floatval($_POST['operator_24_hours'] ?? 0);
    $ext_1_6    = floatval($_POST['ext_price_1_6'] ?? 0);
    $ext_7_10   = floatval($_POST['ext_price_7_10'] ?? 0);
    $ext_11_12  = floatval($_POST['ext_price_11_12'] ?? 0);
    $ext_13_24  = floatval($_POST['ext_price_13_24'] ?? 0);

    $sql = "INSERT INTO cars (
        user_id, branch_id, brand, model, plate_number, fuel_type, type, transmission, capacity, color, image_path, status,
        price_10_hours, operator_10_hours, price_12_hours, operator_12_hours, price_24_hours, operator_24_hours,
        ext_price_1_6, ext_price_7_10, ext_price_11_12, ext_price_13_24
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) respond(false, "Prepare failed: " . $conn->error);

    $add_types = "iisssssssis" . str_repeat("d", 10);
    $stmt->bind_param($add_types,
        $assigned_user_id, $branch_id, $brand, $model, $plate, $fuel, $type, $trans, $cap, $color, $image_string,
        $price_10, $op_rate_10, $price_12, $op_rate_12, $price_24, $op_rate_24,
        $ext_1_6, $ext_7_10, $ext_11_12, $ext_13_24
    );

    if ($stmt->execute()) {
        $msg = "Car added successfully!";
        if ($assigned_user_id !== $user_id) {
            $opNameStmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $opNameStmt->bind_param('i', $assigned_user_id);
            $opNameStmt->execute();
            $opRow = $opNameStmt->get_result()->fetch_assoc();
            $msg = "Car added successfully and assigned to " . ($opRow['name'] ?? 'operator') . "!";
        }
        respond(true, $msg);
    } else {
        respond(false, "Insert failed: " . $stmt->error);
    }
}

// ============================================================
// UPDATE CAR
// ============================================================
if (isset($_POST['update_car']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];

    if ($role === 'admin') {
        $stmt = $conn->prepare("SELECT id, image_path FROM cars WHERE id = ?");
        $stmt->bind_param('i', $id);
    } else {
        $stmt = $conn->prepare("SELECT id, image_path FROM cars WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $id, $user_id);
    }
    $stmt->execute();
    $car_res = $stmt->get_result()->fetch_assoc();
    if (!$car_res) respond(false, "Car not found or access denied.");

    $brand = $_POST['brand'];
    $model = $_POST['model'];
    $plate = strtoupper(trim($_POST['plate_number']));
    $fuel  = $_POST['fuel_type'];
    $type  = $_POST['type'];
    $trans = $_POST['transmission'];
    $color = $_POST['color'];
    $cap   = (int)$_POST['capacity'];

    $stmt = $conn->prepare("SELECT id FROM cars WHERE plate_number = ? AND id != ? LIMIT 1");
    $stmt->bind_param('si', $plate, $id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) respond(false, "Plate number '$plate' is already assigned to another vehicle.");

    $current_images = [];
    if (!empty($car_res['image_path'])) {
        $current_images = array_values(array_filter(array_map('trim', explode(',', $car_res['image_path']))));
    }
    if (empty($current_images)) $current_images = ['default.png'];

    if (isset($_POST['delete_existing_images']) && is_array($_POST['delete_existing_images'])) {
        foreach ($_POST['delete_existing_images'] as $del_img) {
            $del_img = trim($del_img);
            if ($del_img === '') continue;
            if (($key = array_search($del_img, $current_images)) !== false) unset($current_images[$key]);
        }
        $current_images = array_values($current_images);
    }

    $new_images = [];
    if (isset($_FILES['car_images'])) $new_images = handleCarImagesUpload($_FILES['car_images']);
    if (!empty($_POST['_waf_images_csv'])) {
        foreach (array_map('trim', explode(',', $_POST['_waf_images_csv'])) as $n) {
            if ($n !== '' && !in_array($n, $new_images)) $new_images[] = $n;
        }
    }

    if (!empty($new_images)) {
        if (($dk = array_search('default.png', $current_images)) !== false) unset($current_images[$dk]);
        foreach ($new_images as $n) if (!in_array($n, $current_images)) $current_images[] = $n;
        $current_images = array_values($current_images);
    }
    if (empty($current_images)) $current_images = ['default.png'];
    $current_images = array_values(array_unique($current_images));
    $image_string = implode(',', $current_images);

    $price_10   = floatval($_POST['price_10_hours'] ?? 0);
    $op_rate_10 = floatval($_POST['operator_10_hours'] ?? 0);
    $price_12   = floatval($_POST['price_12_hours'] ?? 0);
    $op_rate_12 = floatval($_POST['operator_12_hours'] ?? 0);
    $price_24   = floatval($_POST['price_24_hours'] ?? 0);
    $op_rate_24 = floatval($_POST['operator_24_hours'] ?? 0);
    $ext_1_6    = floatval($_POST['ext_price_1_6'] ?? 0);
    $ext_7_10   = floatval($_POST['ext_price_7_10'] ?? 0);
    $ext_11_12  = floatval($_POST['ext_price_11_12'] ?? 0);
    $ext_13_24  = floatval($_POST['ext_price_13_24'] ?? 0);

    $sql = "UPDATE cars SET
        branch_id=?, brand=?, model=?, plate_number=?, fuel_type=?, type=?, transmission=?,
        capacity=?, color=?, image_path=?, price_10_hours=?, operator_10_hours=?,
        price_12_hours=?, operator_12_hours=?, price_24_hours=?, operator_24_hours=?,
        ext_price_1_6=?, ext_price_7_10=?, ext_price_11_12=?, ext_price_13_24=?
        WHERE id=?";

    $types  = "issssssis" . "s" . "dddddddddd" . "i";
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
    if (!$stmt) respond(false, "SQL Error: " . $conn->error);

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) respond(true, "Vehicle updated successfully!");
    else                  respond(false, "Update failed: " . $stmt->error);
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
        if ($stmt->execute()) respond(true, "Vehicle deleted successfully.");
        else                  respond(false, "Delete failed: " . $conn->error);
    } else {
        respond(false, "Car not found or access denied.");
    }
}

// ============================================================
// SET MAIN IMAGE (AJAX)
// ============================================================
if (isset($_POST['action']) && $_POST['action'] === 'set_main_image') {
    header('Content-Type: application/json');

    $car_id     = (int)$_POST['car_id'];
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
        $images = array_values(array_filter(array_map('trim', explode(',', $car['image_path']))));
        
        if (($key = array_search($chosen_img, $images)) !== false) {
            unset($images[$key]);
            array_unshift($images, $chosen_img);
            $new_path = implode(',', array_values($images));
            
            // THIS IS THE FIXED LINE:
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

if ($isAjaxOp) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No valid action provided']);
    exit;
}