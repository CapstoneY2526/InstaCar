<?php
session_start();
// Three levels up to escape /pages/admin/process/ into the root directory
require_once __DIR__ . '/../../../config/database.php';

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    exit("Access Denied");
}

$current_year = date('Y');

// Force file download as Excel (.xls format using plain HTML)
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=financial_statement_$current_year.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Initialize months data structure
$monthly_data = array_fill(1, 12, [
    'gross' => 0,
    'net' => 0,
    'expense' => 0
]);

// 1. Fetch Gross
$gross_query = mysqli_query($conn, "
    SELECT MONTH(created_at) as month_num,
           COALESCE(SUM(total_gross), 0) as monthly_gross
    FROM booking_payments
    WHERE YEAR(created_at) = $current_year
    GROUP BY MONTH(created_at)
");
while ($row = mysqli_fetch_assoc($gross_query)) {
    $monthly_data[$row['month_num']]['gross'] = $row['monthly_gross'];
}

// 2. Fetch Net
$net_query = mysqli_query($conn, "
    SELECT MONTH(created_at) as month_num,
           COALESCE(SUM(total_net), 0) as monthly_net
    FROM booking_payments
    WHERE YEAR(created_at) = $current_year
    GROUP BY MONTH(created_at)
");
while ($row = mysqli_fetch_assoc($net_query)) {
    $monthly_data[$row['month_num']]['net'] = $row['monthly_net'];
}

// 3. Fetch Expenses
$exp_query = mysqli_query($conn, "
    SELECT MONTH(expense_date) as month_num,
           COALESCE(SUM(amount), 0) as monthly_expense
    FROM expenses
    WHERE YEAR(expense_date) = $current_year
    GROUP BY MONTH(expense_date)
");
while ($row = mysqli_fetch_assoc($exp_query)) {
    $monthly_data[$row['month_num']]['expense'] = $row['monthly_expense'];
}

$months = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

// Track grand totals for the footer
$total_gross = 0;
$total_net = 0;
$total_exp = 0;
?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<div style="font-family: Calibri, sans-serif;">

    <h3>Financial Statement (<?= $current_year ?>)</h3>

    <table border="1" cellpadding="5" style="border-collapse: collapse; font-family: Calibri, sans-serif;">
        <thead>
            <tr style="background-color: #f2f2f2; font-weight: bold;">
                <th align="left">MONTH</th>
                <th align="right">GROSS REVENUE</th>
                <th align="right">JERRY FEES</th>
                <th align="right">NET REVENUE</th>
                <th align="right">EXPENSES</th>
                <th align="right">NET PROFIT</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            foreach ($monthly_data as $m_num => $data): 
                // Match dashboard visibility logic
                if ($m_num > (int)date('n') && $data['gross'] == 0) {
                    continue; 
                }

                $jerry_fees = $data['gross'] - $data['net'];
                $profit = $data['net'] - $data['expense'];

                // Sum grand totals
                $total_gross += $data['gross'];
                $total_net += $data['net'];
                $total_exp += $data['expense'];
            ?>
            <tr>
                <td align="left"><b><?= $months[$m_num - 1] ?></b></td>
                <td align="right">₱<?= number_format($data['gross'], 2) ?></td>
                <td align="right">₱<?= number_format($jerry_fees, 2) ?></td>
                <td align="right">₱<?= number_format($data['net'], 2) ?></td>
                <td align="right">₱<?= number_format($data['expense'], 2) ?></td>
                <td align="right"><b>₱<?= number_format($profit, 2) ?></b></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #dddddd; font-weight: bold;">
                <td align="left">GRAND TOTAL</td>
                <td align="right">₱<?= number_format($total_gross, 2) ?></td>
                <td align="right">₱<?= number_format($total_gross - $total_net, 2) ?></td>
                <td align="right">₱<?= number_format($total_net, 2) ?></td>
                <td align="right">₱<?= number_format($total_exp, 2) ?></td>
                <td align="right">₱<?= number_format($total_net - $total_exp, 2) ?></td>
            </tr>
        </tfoot>
    </table>

</div>