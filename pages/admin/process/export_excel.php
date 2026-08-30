<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

$current_year = date('Y');
$monthly_data = array_fill(1, 12, ['gross' => 0, 'net' => 0, 'expense' => 0]);

// 1. Gross Revenue
$stmt_gross = mysqli_prepare($conn, "SELECT MONTH(created_at) as month_num, COALESCE(SUM(total_gross), 0) as monthly_gross FROM booking_payments WHERE YEAR(created_at) = ? GROUP BY MONTH(created_at)");
if ($stmt_gross) {
    mysqli_stmt_bind_param($stmt_gross, "i", $current_year);
    mysqli_stmt_execute($stmt_gross);
    $res = mysqli_stmt_get_result($stmt_gross);
    while ($row = mysqli_fetch_assoc($res)) {
        $monthly_data[$row['month_num']]['gross'] = $row['monthly_gross'];
    }
    mysqli_stmt_close($stmt_gross);
}

// 2. Net Revenue
$stmt_net = mysqli_prepare($conn, "SELECT MONTH(created_at) as month_num, COALESCE(SUM(total_net), 0) as monthly_net FROM booking_payments WHERE YEAR(created_at) = ? GROUP BY MONTH(created_at)");
if ($stmt_net) {
    mysqli_stmt_bind_param($stmt_net, "i", $current_year);
    mysqli_stmt_execute($stmt_net);
    $res = mysqli_stmt_get_result($stmt_net);
    while ($row = mysqli_fetch_assoc($res)) {
        $monthly_data[$row['month_num']]['net'] = $row['monthly_net'];
    }
    mysqli_stmt_close($stmt_net);
}

// 3. Expenses
$stmt_exp = mysqli_prepare($conn, "SELECT MONTH(expense_date) as month_num, COALESCE(SUM(amount), 0) as monthly_expense FROM expenses WHERE YEAR(expense_date) = ? GROUP BY MONTH(expense_date)");
if ($stmt_exp) {
    mysqli_stmt_bind_param($stmt_exp, "i", $current_year);
    mysqli_stmt_execute($stmt_exp);
    $res = mysqli_stmt_get_result($stmt_exp);
    while ($row = mysqli_fetch_assoc($res)) {
        $monthly_data[$row['month_num']]['expense'] = $row['monthly_expense'];
    }
    mysqli_stmt_close($stmt_exp);
}

$months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

$fileName = "Financial_Statement_" . $current_year . "_" . date('Ymd') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$fileName\"");
header("Pragma: no-cache");
header("Expires: 0");

$total_gross = 0;
$total_net = 0;
$total_exp = 0;
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Financial Statement</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; }
        .title { font-size: 16pt; font-weight: bold; color: #0f172a; }
        .subtitle { font-size: 10pt; color: #64748b; margin-bottom: 15px; }
        .header-th { 
            background-color: #1e293b; 
            color: #ffffff; 
            font-weight: bold; 
            text-align: center;
            border: 0.5pt solid #475569;
            padding: 8px;
        }
        .data-td { 
            border: 0.5pt solid #cbd5e1; 
            padding: 6px; 
            vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .total-row td { 
            background-color: #0f172a; 
            color: #ffffff; 
            font-weight: bold; 
            border: 0.5pt solid #020617; 
            padding: 8px;
        }
        .num-fmt { mso-number-format:"\₱\#\,\#\#0\.00"; }
    </style>
</head>
<body>

<table>
    <tr>
        <td colspan="5" class="title">FINANCIAL STATEMENT (<?= $current_year ?>)</td>
    </tr>
    <tr>
        <td colspan="5" class="subtitle">Generated on <?= date('F j, Y, g:i a') ?></td>
    </tr>
    <tr><td colspan="5"></td></tr>
    <thead>
        <tr>
            <th class="header-th" style="width: 140px;">MONTH</th>
            <th class="header-th" style="width: 160px;">GROSS REVENUE</th>
            <th class="header-th" style="width: 160px;">NET REVENUE</th>
            <th class="header-th" style="width: 160px;">EXPENSES</th>
            <th class="header-th" style="width: 160px;">NET PROFIT</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        foreach ($monthly_data as $m_num => $data): 
            $net_profit_month = $data['net'] - $data['expense'];
            $total_gross += $data['gross'];
            $total_net += $data['net'];
            $total_exp += $data['expense'];
        ?>
        <tr>
            <td class="data-td text-center" style="font-weight: 600;"><?= $months[$m_num-1] ?></td>
            <td class="data-td text-right num-fmt">₱<?= number_format($data['gross'], 2) ?></td>
            <td class="data-td text-right num-fmt" style="color: #0369a1;">₱<?= number_format($data['net'], 2) ?></td>
            <td class="data-td text-right num-fmt" style="color: #b91c1c;">₱<?= number_format($data['expense'], 2) ?></td>
            <td class="data-td text-right num-fmt" style="font-weight: bold; color: <?= $net_profit_month >= 0 ? '#15803d' : '#b91c1c' ?>;">
                ₱<?= number_format($net_profit_month, 2) ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <?php $grand_net_profit = $total_net - $total_exp; ?>
        <tr class="total-row">
            <td class="text-center">GRAND TOTAL</td>
            <td class="text-right num-fmt">₱<?= number_format($total_gross, 2) ?></td>
            <td class="text-right num-fmt" style="color: #38bdf8;">₱<?= number_format($total_net, 2) ?></td>
            <td class="text-right num-fmt" style="color: #fde047;">₱<?= number_format($total_exp, 2) ?></td>
            <td class="text-right num-fmt" style="color: #4ade80;">₱<?= number_format($grand_net_profit, 2) ?></td>
        </tr>
    </tfoot>
</table>

</body>
</html>