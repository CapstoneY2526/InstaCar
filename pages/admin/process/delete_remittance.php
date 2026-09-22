<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

// Admin-only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied.";
    header("Location: ../../../index.php");
    exit();
}

// Must be POST (never delete via GET)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../operator_revenue.php");
    exit();
}

$actor_id       = (int)$_SESSION['user_id'];
$remittance_id  = (int)($_POST['remittance_id'] ?? 0);

if ($remittance_id <= 0) {
    $_SESSION['error'] = "Invalid remittance.";
    header("Location: ../operator_revenue.php");
    exit();
}

// ── Verify the remittance exists ──
$fetchStmt = $conn->prepare("
    SELECT id, operator_id, car_id, amount, payment_date
    FROM operator_remittances
    WHERE id = ?
    LIMIT 1
");
$fetchStmt->bind_param('i', $remittance_id);
$fetchStmt->execute();
$remitRow = $fetchStmt->get_result()->fetch_assoc();
$fetchStmt->close();

if (!$remitRow) {
    $_SESSION['error'] = "Remittance not found.";
    header("Location: ../operator_revenue.php");
    exit();
}

// ── Fetch photos so we can delete the physical files ──
$photosStmt = $conn->prepare("
    SELECT id, file_name
    FROM operator_remittance_photos
    WHERE remittance_id = ?
");
$photosStmt->bind_param('i', $remittance_id);
$photosStmt->execute();
$photosRes = $photosStmt->get_result();

$photos = [];
while ($p = $photosRes->fetch_assoc()) {
    $photos[] = $p;
}
$photosStmt->close();

// ── Delete the physical files first ──
$upload_dir = __DIR__ . '/../../../public/assets/images/remittances/';
$deleted_files = 0;
$failed_files  = 0;

foreach ($photos as $p) {
    // Guard against path traversal — only allow the base filename
    $safe_name = basename($p['file_name']);
    $file_path = $upload_dir . $safe_name;

    if (file_exists($file_path)) {
        if (@unlink($file_path)) {
            $deleted_files++;
        } else {
            $failed_files++;
        }
    }
}

// ── Delete the DB rows (photos first, then the remittance) ──
$photoDeleteStmt = $conn->prepare("
    DELETE FROM operator_remittance_photos
    WHERE remittance_id = ?
");
$photoDeleteStmt->bind_param('i', $remittance_id);
$photoDeleteStmt->execute();
$photoDeleteStmt->close();

$remitDeleteStmt = $conn->prepare("
    DELETE FROM operator_remittances
    WHERE id = ?
");
$remitDeleteStmt->bind_param('i', $remittance_id);
$remitDeleteStmt->execute();
$remitDeleteStmt->close();

// ── Build flash message ──
$msg = "✅ Remittance of ₱" . number_format((float)$remitRow['amount'], 2)
     . " for booking date " . htmlspecialchars($remitRow['payment_date'])
     . " has been deleted.";

if ($deleted_files > 0) {
    $msg .= " " . $deleted_files . " proof file" . ($deleted_files === 1 ? '' : 's') . " removed.";
}
if ($failed_files > 0) {
    $msg .= " (" . $failed_files . " file" . ($failed_files === 1 ? '' : 's') . " could not be removed from disk.)";
}

$_SESSION['success'] = $msg;

header("Location: ../operator_revenue.php");
exit();
?>