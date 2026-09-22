<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

$current_year = date('Y');

$monthly_data = array_fill(1, 12, [
    'gross' => 0, 'operator' => 0, 'staff_agent' => 0,
    'company_net' => 0, 'expense' => 0, 'net_profit' => 0
]);

// 1. Gross + Operator + Staff/Agent from booking_payments
$q = "SELECT
          MONTH(created_at) AS month_num,
          COALESCE(SUM(total_gross), 0)             AS m_gross,
          COALESCE(SUM(operator_share), 0)          AS m_operator,
          COALESCE(SUM(total_gross - total_net), 0) AS m_staff_agent
      FROM booking_payments
      WHERE YEAR(created_at) = ?
      GROUP BY MONTH(created_at)";

$stmt = mysqli_prepare($conn, $q);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $current_year);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $m = (int)$row['month_num'];
        $monthly_data[$m]['gross']       = (float)$row['m_gross'];
        $monthly_data[$m]['operator']    = (float)$row['m_operator'];
        $monthly_data[$m]['staff_agent'] = (float)$row['m_staff_agent'];
        $monthly_data[$m]['company_net'] = $monthly_data[$m]['gross']
                                         - $monthly_data[$m]['operator']
                                         - $monthly_data[$m]['staff_agent'];
    }
    mysqli_stmt_close($stmt);
}

// 2. Expenses
$q = "SELECT
          MONTH(expense_date) AS month_num,
          COALESCE(SUM(amount), 0) AS m_expense
      FROM expenses
      WHERE YEAR(expense_date) = ?
      GROUP BY MONTH(expense_date)";

$stmt = mysqli_prepare($conn, $q);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $current_year);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $m = (int)$row['month_num'];
        $monthly_data[$m]['expense'] = (float)$row['m_expense'];
    }
    mysqli_stmt_close($stmt);
}

// 3. Net profit
foreach ($monthly_data as $m => $d) {
    $monthly_data[$m]['net_profit'] = $d['company_net'] - $d['expense'];
}

$months = ["January","February","March","April","May","June","July","August","September","October","November","December"];

$fileName = "Financial_Statement_" . $current_year . "_" . date('Ymd') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$fileName\"");
header("Pragma: no-cache");
header("Expires: 0");

$total_gross       = 0;
$total_operator    = 0;
$total_staff_agent = 0;
$total_company_net = 0;
$total_exp         = 0;
$total_profit      = 0;
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
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
        .text-right  { text-align: right; }
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
        <td colspan="7" class="title">FINANCIAL STATEMENT (<?= $current_year ?>)</td>
    </tr>
    <tr>
        <td colspan="7" class="subtitle">Generated on <?= date('F j, Y, g:i a') ?></td>
    </tr>
    <tr><td colspan="7"></td></tr>
    <thead>
        <tr>
            <th class="header-th" style="width: 120px;">MONTH</th>
            <th class="header-th" style="width: 140px;">GROSS REVENUE</th>
            <th class="header-th" style="width: 140px;">OPERATOR PAYOUTS</th>
            <th class="header-th" style="width: 140px;">STAFF / AGENT</th>
            <th class="header-th" style="width: 140px;">COMPANY NET</th>
            <th class="header-th" style="width: 140px;">EXPENSES</th>
            <th class="header-th" style="width: 140px;">NET PROFIT</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($monthly_data as $m_num => $d):
            $total_gross       += $d['gross'];
            $total_operator    += $d['operator'];
            $total_staff_agent += $d['staff_agent'];
            $total_company_net += $d['company_net'];
            $total_exp         += $d['expense'];
            $total_profit      += $d['net_profit'];
        ?>
        <tr>
            <td class="data-td text-center" style="font-weight: 600;"><?= $months[$m_num-1] ?></td>
            <td class="data-td text-right num-fmt">₱<?= number_format($d['gross'], 2) ?></td>
            <td class="data-td text-right num-fmt" style="color:#b45309;">-₱<?= number_format($d['operator'], 2) ?></td>
            <td class="data-td text-right num-fmt" style="color:#7c3aed;">-₱<?= number_format($d['staff_agent'], 2) ?></td>
            <td class="data-td text-right num-fmt" style="color:#0369a1; font-weight: bold;">₱<?= number_format($d['company_net'], 2) ?></td>
            <td class="data-td text-right num-fmt" style="color:#b91c1c;">-₱<?= number_format($d['expense'], 2) ?></td>
            <td class="data-td text-right num-fmt" style="font-weight: bold; color: <?= $d['net_profit'] >= 0 ? '#15803d' : '#b91c1c' ?>;">
                ₱<?= number_format($d['net_profit'], 2) ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td class="text-center">GRAND TOTAL</td>
            <td class="text-right num-fmt">₱<?= number_format($total_gross, 2) ?></td>
            <td class="text-right num-fmt" style="color:#fde047;">-₱<?= number_format($total_operator, 2) ?></td>
            <td class="text-right num-fmt" style="color:#c4b5fd;">-₱<?= number_format($total_staff_agent, 2) ?></td>
            <td class="text-right num-fmt" style="color:#7dd3fc;">₱<?= number_format($total_company_net, 2) ?></td>
            <td class="text-right num-fmt" style="color:#fca5a5;">-₱<?= number_format($total_exp, 2) ?></td>
            <td class="text-right num-fmt" style="color:#4ade80;">₱<?= number_format($total_profit, 2) ?></td>
        </tr>
    </tfoot>
</table>

</body>
</html>