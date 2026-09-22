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
    header("Location: ../remittance.php");
    exit();
}

$actor_id = (int)$_SESSION['user_id'];
$action   = $_POST['action'] ?? '';

// ════════════════════════════════════════════════════════════
// ACTION: Record a staff remittance
// ════════════════════════════════════════════════════════════
if ($action === 'record') {

    $branch_id    = (int)($_POST['branch_id'] ?? 0);
    $amount       = floatval($_POST['amount'] ?? 0);
    $payment_date = trim($_POST['payment_date'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');

    // Basic validation
    if ($branch_id <= 0 || $amount <= 0) {
        $_SESSION['error'] = "Invalid payment data.";
        header("Location: ../remittance.php");
        exit();
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) {
        $_SESSION['error'] = "Invalid payment date format.";
        header("Location: ../remittance.php");
        exit();
    }

    // Verify branch exists
    $brStmt = $conn->prepare("SELECT id, name FROM branches WHERE id = ? LIMIT 1");
    $brStmt->bind_param('i', $branch_id);
    $brStmt->execute();
    $branch = $brStmt->get_result()->fetch_assoc();
    $brStmt->close();

    if (!$branch) {
        $_SESSION['error'] = "Branch not found.";
        header("Location: ../remittance.php");
        exit();
    }

    // Compute current balance for this branch
    // Owed = sum of all staff fees across settled bookings at this branch
    $owedStmt = $conn->prepare("
        SELECT COALESCE(SUM(bp.jer_delivery_fee + bp.jer_pickup_fee), 0) AS total_owed
        FROM booking_payments bp
        INNER JOIN bookings b ON bp.booking_id = b.id
        WHERE b.branch_id = ?
    ");
    $owedStmt->bind_param('i', $branch_id);
    $owedStmt->execute();
    $total_owed = (float)$owedStmt->get_result()->fetch_assoc()['total_owed'];
    $owedStmt->close();

    // Paid = sum of all recorded remittances for this branch
    $paidStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total_paid
        FROM staff_fee_remittances
        WHERE branch_id = ?
    ");
    $paidStmt->bind_param('i', $branch_id);
    $paidStmt->execute();
    $total_paid = (float)$paidStmt->get_result()->fetch_assoc()['total_paid'];
    $paidStmt->close();

    $balance = $total_owed - $total_paid;

    if ($balance <= 0) {
        $_SESSION['error'] = "This branch has no outstanding staff fees.";
        header("Location: ../remittance.php");
        exit();
    }

    if ($amount > $balance) {
        $_SESSION['error'] = "Payment amount exceeds the outstanding balance of ₱" . number_format($balance, 2) . ".";
        header("Location: ../remittance.php");
        exit();
    }

    // Insert the remittance
    $insertStmt = $conn->prepare("
        INSERT INTO staff_fee_remittances (branch_id, amount, payment_date, notes, recorded_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insertStmt->bind_param('idssi', $branch_id, $amount, $payment_date, $notes, $actor_id);

    if (!$insertStmt->execute()) {
        $_SESSION['error'] = "Failed to record payment: " . $insertStmt->error;
        $insertStmt->close();
        header("Location: ../remittance.php");
        exit();
    }
    $remittance_id = $insertStmt->insert_id;
    $insertStmt->close();

    // ── Handle optional proof uploads (multi-file, same as operator remittance) ──
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

            if ($file_error !== UPLOAD_ERR_OK) { $skipped_count++; continue; }
            if ($file_size > 5 * 1024 * 1024) { $skipped_count++; continue; }

            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext, true)) { $skipped_count++; continue; }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_mime = $finfo ? finfo_file($finfo, $tmp_name) : false;
            if ($finfo) finfo_close($finfo);

            if (!$detected_mime || !isset($allowed_mimes[$detected_mime])) { $skipped_count++; continue; }
            if (!in_array($ext, $allowed_mimes[$detected_mime], true)) { $skipped_count++; continue; }

            $new_file_name = "staffremit_" . $remittance_id . "_" . time() . "_" . uniqid() . "." . $ext;
            $target_file   = $target_dir . $new_file_name;

            if (move_uploaded_file($tmp_name, $target_file)) {
                $photoStmt = $conn->prepare("
                    INSERT INTO staff_fee_remittance_photos (remittance_id, file_name)
                    VALUES (?, ?)
                ");
                $photoStmt->bind_param('is', $remittance_id, $new_file_name);
                if ($photoStmt->execute()) {
                    $uploaded_count++;
                } else {
                    @unlink($target_file);
                    $skipped_count++;
                }
                $photoStmt->close();
            } else {
                $skipped_count++;
            }
        }
    }

    // Build flash message
    $newBalance = $balance - $amount;
    $msg = "✅ Payment of ₱" . number_format($amount, 2) . " recorded for " . htmlspecialchars($branch['name']) . ".";
    if ($newBalance <= 0.005) {
        $msg .= " This branch is now fully settled.";
    } else {
        $msg .= " Remaining balance: ₱" . number_format($newBalance, 2) . ".";
    }
    if ($uploaded_count > 0) {
        $msg .= " " . $uploaded_count . " proof file" . ($uploaded_count === 1 ? '' : 's') . " attached.";
    }
    if ($skipped_count > 0) {
        $msg .= " (" . $skipped_count . " file" . ($skipped_count === 1 ? '' : 's') . " skipped.)";
    }
    $_SESSION['success'] = $msg;

    header("Location: ../remittance.php");
    exit();
}

// ════════════════════════════════════════════════════════════
// ACTION: Delete a staff remittance
// ════════════════════════════════════════════════════════════
if ($action === 'delete') {

    $remittance_id = (int)($_POST['remittance_id'] ?? 0);

    if ($remittance_id <= 0) {
        $_SESSION['error'] = "Invalid remittance.";
        header("Location: ../remittance.php");
        exit();
    }

    // Fetch the row
    $fetchStmt = $conn->prepare("
        SELECT r.id, r.branch_id, r.amount, r.payment_date, b.name AS branch_name
        FROM staff_fee_remittances r
        LEFT JOIN branches b ON r.branch_id = b.id
        WHERE r.id = ?
        LIMIT 1
    ");
    $fetchStmt->bind_param('i', $remittance_id);
    $fetchStmt->execute();
    $remit = $fetchStmt->get_result()->fetch_assoc();
    $fetchStmt->close();

    if (!$remit) {
        $_SESSION['error'] = "Remittance not found.";
        header("Location: ../remittance.php");
        exit();
    }

    // Fetch photos so we can delete physical files
    $photosStmt = $conn->prepare("
        SELECT file_name FROM staff_fee_remittance_photos WHERE remittance_id = ?
    ");
    $photosStmt->bind_param('i', $remittance_id);
    $photosStmt->execute();
    $photosRes = $photosStmt->get_result();

    $upload_dir = __DIR__ . '/../../../public/assets/images/remittances/';
    $deleted_files = 0;
    $failed_files  = 0;

    while ($p = $photosRes->fetch_assoc()) {
        $safe_name = basename($p['file_name']);
        $file_path = $upload_dir . $safe_name;
        if (file_exists($file_path)) {
            if (@unlink($file_path)) $deleted_files++;
            else $failed_files++;
        }
    }
    $photosStmt->close();

    // Delete DB rows
    $delPhotos = $conn->prepare("DELETE FROM staff_fee_remittance_photos WHERE remittance_id = ?");
    $delPhotos->bind_param('i', $remittance_id);
    $delPhotos->execute();
    $delPhotos->close();

    $delRemit = $conn->prepare("DELETE FROM staff_fee_remittances WHERE id = ?");
    $delRemit->bind_param('i', $remittance_id);
    $delRemit->execute();
    $delRemit->close();

    $msg = "✅ Remittance of ₱" . number_format((float)$remit['amount'], 2)
         . " for " . htmlspecialchars($remit['branch_name'] ?? 'branch')
         . " (" . htmlspecialchars($remit['payment_date']) . ") has been deleted.";
    if ($deleted_files > 0) $msg .= " " . $deleted_files . " file" . ($deleted_files === 1 ? '' : 's') . " removed.";
    if ($failed_files > 0)  $msg .= " (" . $failed_files . " file" . ($failed_files === 1 ? '' : 's') . " could not be removed.)";

    $_SESSION['success'] = $msg;

    header("Location: ../remittance.php");
    exit();
}

// Unknown action
$_SESSION['error'] = "Unknown action.";
header("Location: ../remittance.php");
exit();
?>