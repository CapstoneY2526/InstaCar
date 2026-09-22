<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

// Admin-only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied.";
    header("Location: ../../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../operator_revenue.php");
    exit();
}

$actor_id = (int)$_SESSION['user_id'];

$car_id       = (int)($_POST['car_id'] ?? 0);
$amount       = floatval($_POST['amount'] ?? 0);
$payment_date = trim($_POST['payment_date'] ?? '');
$notes        = trim($_POST['notes'] ?? '');

// ── Basic validation ──
if ($car_id <= 0 || $amount <= 0) {
    $_SESSION['error'] = "Invalid payment data.";
    header("Location: ../operator_revenue.php");
    exit();
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) {
    $_SESSION['error'] = "Invalid payment date format.";
    header("Location: ../operator_revenue.php");
    exit();
}

// ── Verify the car belongs to an operator ──
$carStmt = $conn->prepare("
    SELECT c.id, c.user_id, u.role AS owner_role, u.name AS operator_name
    FROM cars c
    INNER JOIN users u ON c.user_id = u.id
    WHERE c.id = ?
    LIMIT 1
");
$carStmt->bind_param('i', $car_id);
$carStmt->execute();
$carRow = $carStmt->get_result()->fetch_assoc();
$carStmt->close();

if (!$carRow || $carRow['owner_role'] !== 'operator') {
    $_SESSION['error'] = "Car not found or not owned by an operator.";
    header("Location: ../operator_revenue.php");
    exit();
}

$operator_id = (int)$carRow['user_id'];

// ── Compute current balance ──
$owedStmt = $conn->prepare("
    SELECT COALESCE(SUM(bp.operator_share), 0) AS total_owed
    FROM booking_payments bp
    INNER JOIN bookings b ON bp.booking_id = b.id
    WHERE b.car_id = ? AND bp.operator_share > 0
");
$owedStmt->bind_param('i', $car_id);
$owedStmt->execute();
$total_owed = (float)$owedStmt->get_result()->fetch_assoc()['total_owed'];
$owedStmt->close();

$paidStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) AS total_paid
    FROM operator_remittances
    WHERE car_id = ?
");
$paidStmt->bind_param('i', $car_id);
$paidStmt->execute();
$total_paid = (float)$paidStmt->get_result()->fetch_assoc()['total_paid'];
$paidStmt->close();

$balance = $total_owed - $total_paid;

if ($balance <= 0) {
    $_SESSION['error'] = "This car has no outstanding balance.";
    header("Location: ../operator_revenue.php");
    exit();
}

if ($amount > $balance) {
    $_SESSION['error'] = "Payment amount exceeds the outstanding balance of ₱" . number_format($balance, 2) . ".";
    header("Location: ../operator_revenue.php");
    exit();
}

// ── Insert the remittance row first (so we get its ID) ──
$insertStmt = $conn->prepare("
    INSERT INTO operator_remittances
        (operator_id, car_id, amount, payment_date, notes, recorded_by)
    VALUES (?, ?, ?, ?, ?, ?)
");
$insertStmt->bind_param('iidssi',
    $operator_id,
    $car_id,
    $amount,
    $payment_date,
    $notes,
    $actor_id
);

if (!$insertStmt->execute()) {
    $_SESSION['error'] = "Failed to record payment: " . $insertStmt->error;
    $insertStmt->close();
    header("Location: ../operator_revenue.php");
    exit();
}
$remittance_id = $insertStmt->insert_id;
$insertStmt->close();

// ── Handle optional proof photo uploads (multiple files) ──
$uploaded_count = 0;
$skipped_count  = 0;

if (!empty($_FILES['proofs']['name'][0])) {
    $target_dir = __DIR__ . '/../../../public/assets/images/remittances/';

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0775, true);
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
    $allowed_mimes = [
        'image/jpeg'      => ['jpg', 'jpeg'],
        'image/png'       => ['png'],
        'application/pdf' => ['pdf'],
    ];

    $file_count = count($_FILES['proofs']['name']);

    for ($i = 0; $i < $file_count; $i++) {
        $orig_name  = $_FILES['proofs']['name'][$i];
        $tmp_name   = $_FILES['proofs']['tmp_name'][$i];
        $file_error = $_FILES['proofs']['error'][$i];
        $file_size  = $_FILES['proofs']['size'][$i];

        if ($file_error !== UPLOAD_ERR_OK) {
            $skipped_count++;
            continue;
        }

        // Size guard — 5 MB per file
        if ($file_size > 5 * 1024 * 1024) {
            $skipped_count++;
            continue;
        }

        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_ext, true)) {
            $skipped_count++;
            continue;
        }

        // MIME sniff
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected_mime = $finfo ? finfo_file($finfo, $tmp_name) : false;
        if ($finfo) finfo_close($finfo);

        if (!$detected_mime || !isset($allowed_mimes[$detected_mime])) {
            $skipped_count++;
            continue;
        }

        if (!in_array($ext, $allowed_mimes[$detected_mime], true)) {
            $skipped_count++;
            continue;
        }

        // Save the file with a unique name
        $new_file_name = "remit_" . $remittance_id . "_" . time() . "_" . uniqid() . "." . $ext;
        $target_file   = $target_dir . $new_file_name;

        if (move_uploaded_file($tmp_name, $target_file)) {
            // Insert the photo record
            $photoStmt = $conn->prepare("
                INSERT INTO operator_remittance_photos (remittance_id, file_name)
                VALUES (?, ?)
            ");
            $photoStmt->bind_param('is', $remittance_id, $new_file_name);
            if ($photoStmt->execute()) {
                $uploaded_count++;
            } else {
                // DB insert failed — remove the file to avoid orphans
                @unlink($target_file);
                $skipped_count++;
            }
            $photoStmt->close();
        } else {
            $skipped_count++;
        }
    }
}

// ── Build the flash message ──
$newBalance = $balance - $amount;
$successMsg = "✅ Payment of ₱" . number_format($amount, 2) . " recorded.";
if ($newBalance <= 0.005) {
    $successMsg .= " This car is now fully settled.";
} else {
    $successMsg .= " Remaining balance: ₱" . number_format($newBalance, 2) . ".";
}
if ($uploaded_count > 0) {
    $successMsg .= " " . $uploaded_count . " proof file" . ($uploaded_count === 1 ? '' : 's') . " attached.";
}
if ($skipped_count > 0) {
    $successMsg .= " (" . $skipped_count . " file" . ($skipped_count === 1 ? '' : 's') . " skipped — invalid format or too large.)";
}
$_SESSION['success'] = $successMsg;

header("Location: ../operator_revenue.php");
exit();
?>