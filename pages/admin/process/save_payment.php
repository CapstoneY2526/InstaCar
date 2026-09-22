<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

// Auth Check — allow admin and staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'staff'], true)) {
    die("Unauthorized access");
}

// Who is settling this payment?
$settled_by = (int)$_SESSION['user_id'];

// ── Where to send the user after a successful save (role-aware) ──
// Relative paths from this file's location (pages/admin/process/):
//   admin  → ../settlements.php
//   staff  → ../../staff/staff_settlements.php
// These work identically on localhost (/car-rental/pages/...) and production (/pages/...)
$return_page = ($_SESSION['role'] === 'staff')
    ? '../../staff/staff_settlements.php'
    : '../settlements.php';

if (isset($_POST['save_payment'])) {
    $bid = intval($_POST['booking_id']);
    
    // Sanitize and Get Inputs
    $rent   = floatval($_POST['daily_rent'] ?? 0);
    $wash   = floatval($_POST['carwash'] ?? 0);
    $ext    = floatval($_POST['extension_fee'] ?? 0);
    $del    = floatval($_POST['delivery_fee'] ?? 0);
    $j_del  = floatval($_POST['jer_delivery_fee'] ?? 0);   // Staff delivery fee
    $pick   = floatval($_POST['pickup_fee'] ?? 0);
    $j_pick = floatval($_POST['jer_pickup_fee'] ?? 0);     // Staff pickup fee
    $fuel   = floatval($_POST['fuel'] ?? 0);
    $driver = floatval($_POST['driver_fee'] ?? 0);
    $damage = floatval($_POST['damage_fee'] ?? 0);
    $agent  = floatval($_POST['agent_fee'] ?? 0);
    $others = floatval($_POST['others'] ?? 0);
    
    // Capture remarks
    $remarks = $_POST['remarks'] ?? '';

    // ── GROSS REVENUE (everything the customer pays) ──
    $total_gross = $rent + $ext + $del + $pick + $damage + $others + $wash + $fuel + $driver;
    
    // ── NET TOTAL (Gross minus staff/agent payouts) ──
    $total_net = $total_gross - $j_del - $j_pick - $agent;

    // ── OPERATOR SHARE ──
    $operator_share = 0.0;

    $opSql = "
        SELECT
            c.user_id       AS car_owner_id,
            u.role          AS owner_role,
            c.operator_10_hours,
            c.operator_12_hours,
            c.operator_24_hours,
            b.start_date,
            b.pickup_time,
            b.end_date,
            b.return_time
        FROM bookings b
        INNER JOIN cars c  ON b.car_id = c.id
        INNER JOIN users u ON c.user_id = u.id
        WHERE b.id = ?
        LIMIT 1
    ";

    $opStmt = $conn->prepare($opSql);
    if ($opStmt) {
        $opStmt->bind_param('i', $bid);
        $opStmt->execute();
        $opRow = $opStmt->get_result()->fetch_assoc();
        $opStmt->close();

        if ($opRow && $opRow['owner_role'] === 'operator') {
            $startTs = strtotime($opRow['start_date'] . ' ' . ($opRow['pickup_time'] ?? '00:00:00'));
            $endTs   = strtotime($opRow['end_date']   . ' ' . ($opRow['return_time'] ?? '00:00:00'));

            if ($startTs && $endTs && $endTs > $startTs) {
                $hours = ($endTs - $startTs) / 3600;
                $hours = ceil($hours);

                $op10 = floatval($opRow['operator_10_hours'] ?? 0);
                $op12 = floatval($opRow['operator_12_hours'] ?? 0);
                $op24 = floatval($opRow['operator_24_hours'] ?? 0);

                if ($hours <= 10 && $op10 > 0) {
                    $operator_share = $op10;
                } elseif ($hours <= 12 && $op12 > 0) {
                    $operator_share = $op12;
                } elseif ($hours <= 24 && $op24 > 0) {
                    $operator_share = $op24;
                } elseif ($op24 > 0) {
                    $days = floor($hours / 24);
                    $operator_share = $days * $op24;
                }
            }
        }
    }

    $operator_share = round($operator_share, 2);

    // First, DELETE any existing record for this booking_id (re-settlement)
    $delete_sql = "DELETE FROM booking_payments WHERE booking_id = ?";
    $stmtDel = $conn->prepare($delete_sql);
    if ($stmtDel) {
        $stmtDel->bind_param("i", $bid);
        $stmtDel->execute();
        $stmtDel->close();
    }
    
    // Then INSERT new record
    $insert_sql = "INSERT INTO booking_payments 
               (booking_id, daily_rent, carwash, extension_fee, delivery_fee, 
                jer_delivery_fee, pickup_fee, jer_pickup_fee, fuel, driver_fee, 
                damage_fee, agent_fee, others, total_gross, total_net, operator_share, remarks,
                settled_by, settled_at) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
               
    $stmt = $conn->prepare($insert_sql);
    if ($stmt) {
        // 2 ints + 15 decimals + 1 string = 18 chars
        $type_string = "i" . str_repeat("d", 15) . "s" . "i";
        
        $stmt->bind_param($type_string, 
            $bid,             // i
            $rent,            // d 1
            $wash,            // d 2
            $ext,             // d 3
            $del,             // d 4
            $j_del,           // d 5
            $pick,            // d 6
            $j_pick,          // d 7
            $fuel,            // d 8
            $driver,          // d 9
            $damage,          // d 10
            $agent,           // d 11
            $others,          // d 12
            $total_gross,     // d 13
            $total_net,       // d 14
            $operator_share,  // d 15
            $remarks,         // s
            $settled_by       // i
        );
        
        if ($stmt->execute()) {
            $successMsg = "✅ Payment recorded successfully! Gross: ₱" . number_format($total_gross, 2)
                        . " | Net: ₱" . number_format($total_net, 2);
            if ($operator_share > 0) {
                $successMsg .= " | Operator owed: ₱" . number_format($operator_share, 2);
            }
            $_SESSION['success'] = $successMsg;
        } else {
            $_SESSION['error'] = "Insert failed: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Failed to prepare statement: " . $conn->error;
    }

    header("Location: " . $return_page);
    exit();
}

header("Location: " . $return_page);
exit();
?>