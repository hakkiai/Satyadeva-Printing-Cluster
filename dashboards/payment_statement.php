<?php
include '../database/db_connect.php';
date_default_timezone_set('Asia/Kolkata');

// Check if job_id is provided
if (!isset($_GET['job_id']) || !is_numeric($_GET['job_id'])) {
    echo "<script>alert('Invalid or missing job ID!'); window.location.href='accounts_dashboard.php';</script>";
    exit;
}

$job_id = (int)$_GET['job_id'];

// Fetch job details
$sql = "SELECT job_name, total_charges, plating_charges, paper_charges, printing_charges, lamination_charges, pinning_charges, binding_charges, finishing_charges, other_charges, discount 
        FROM job_sheets 
        WHERE id = ? AND completed_delivery = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    echo "<script>alert('Job not found or not completed!'); window.location.href='accounts_dashboard.php';</script>";
    exit;
}

// Recalculate total charges to ensure correctness (excluding paper charges, which is printing_charges)
$total_charges = $job['plating_charges'] + $job['paper_charges'] + $job['lamination_charges'] + 
                $job['pinning_charges'] + $job['binding_charges'] + $job['finishing_charges'] + 
                $job['other_charges'] - $job['discount'];

// Fetch payment records
$sql = "SELECT date, cash, credit, balance, payment_status, payment_type 
        FROM payment_records 
        WHERE job_sheet_id = ? 
        ORDER BY date DESC"; // Newest first for display
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate total paid and running balance (oldest to newest logic)
$total_cash_paid = 0;
$total_credit_logged = 0;
$effective_credit = 0;
$running_balance = $total_charges; // Start with total charges
$adjusted_payments = [];

// Process payments in chronological order (oldest to newest) for correct balance
$chronological_payments = array_reverse($payments); // Reverse DESC to ASC internally
foreach ($chronological_payments as $index => $payment) {
    if ($payment['payment_type'] === 'credit') {
        $total_credit_logged += $payment['credit'];
        $running_balance = $total_charges - $total_cash_paid; // Credit sets debt, not reduced yet
    } else {
        $total_cash_paid += $payment['cash'];
        $running_balance = $total_charges - $total_cash_paid; // Cash reduces balance
    }
    if ($running_balance < 0) $running_balance = 0;

    // Store adjusted payment with correct balance
    $adjusted_payments[$index] = $payment;
    $adjusted_payments[$index]['balance'] = $running_balance;
}

// Reverse adjusted payments back to newest-first for display
$adjusted_payments = array_reverse($adjusted_payments);

// Final calculations
$current_balance = $total_charges - $total_cash_paid;
$effective_credit = $total_credit_logged - $total_cash_paid;
if ($effective_credit < 0) $effective_credit = 0;
if ($total_credit_logged > 0 && $total_cash_paid < $total_charges) {
    $current_balance = min($effective_credit, $total_charges - $total_cash_paid);
}
if ($current_balance < 0) $current_balance = 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Statement - Job #<?= $job_id ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .container { 
            width: 70%; 
            margin: 20px auto; 
            padding: 20px; 
            background-color: white; 
            border-radius: 10px; 
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: center; 
        }
        th { 
            background-color: #007bff; 
            color: white; 
            text-transform: uppercase; 
            font-size: 14px; 
        }
        tr:nth-child(even) { 
            background-color: #f2f2f2; 
        }
        tr:hover { 
            background-color: #e9ecef; 
        }
        button { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: bold; 
            margin: 5px; 
            transition: background-color 0.3s; 
        }
        .back-btn { 
            background-color: #6c757d; 
            color: white; 
        }
        .print-btn { 
            background-color: #28a745; 
            color: white; 
        }
        button:hover { 
            opacity: 0.8; 
        }
        h2 { 
            color: #007bff; 
            margin-bottom: 15px; 
        }
        .summary { 
            margin-top: 20px; 
            padding: 15px; 
            background-color: #e9ecef; 
            border-radius: 5px; 
        }
        .summary p { 
            margin: 5px 0; 
            font-size: 16px; 
        }
        .summary .highlight { 
            font-weight: bold; 
            color: #007bff; 
        }
        @media print {
            .navbar, .back-btn, .print-btn {
                display: none;
            }
            .container {
                width: 100%;
                margin: 0;
                box-shadow: none;
            }
        }
    </style>
    <script>
        function printStatement() {
            window.print();
        }
    </script>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Payment Statement - Job #<?= $job_id ?></h2>
        <div class="nav-buttons">
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="container">
        <h2>Job Details</h2>
        <p><strong>Job Name:</strong> <?= htmlspecialchars($job['job_name']) ?></p>
        <p><strong>Total Charges (Excl. Paper):</strong> ₹<?= number_format($total_charges, 2) ?></p>

        <h2>Payment History</h2>
        <?php if (!empty($adjusted_payments)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Payment Type</th>
                        <th>Amount Paid (₹)</th>
                        <th>Balance (₹)</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adjusted_payments as $payment): ?>
                        <tr>
                            <td><?= date('d-M-Y h:i:s A', strtotime($payment['date'])) ?></td>
                            <td><?= ucfirst($payment['payment_type']) ?></td>
                            <td>
                                <?php
                                if ($payment['payment_type'] === 'credit') {
                                    echo number_format($payment['credit'], 2);
                                } else {
                                    echo number_format($payment['cash'], 2);
                                }
                                ?>
                            </td>
                            <td><?= number_format($payment['balance'], 2) ?></td>
                            <td>
                                <?php
                                if ($payment['payment_status'] === 'completed') {
                                    echo 'Fully Paid';
                                } elseif ($payment['payment_type'] === 'credit') {
                                    echo 'Partially Paid';
                                } else {
                                    echo 'Partially Paid';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="summary">
                <p><strong>Total Cash Paid:</strong> ₹<?= number_format($total_cash_paid, 2) ?></p>
                <p><strong>Remaining Credit Debt:</strong> ₹<?= number_format($effective_credit, 2) ?></p>
                <p><strong>Current Balance (Excl. Paper):</strong> <span class="highlight">₹<?= number_format($current_balance, 2) ?></span></p>
            </div>
        <?php else: ?>
            <p>No payment records found for this job.</p>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 20px;">
            <button class="back-btn" onclick="location.href='accounts_dashboard.php'">Back to Dashboard</button>
            <button class="print-btn" onclick="printStatement()">Print Statement</button>
        </div>
    </div>
</body>
</html>