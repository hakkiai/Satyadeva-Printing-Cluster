<?php
include '../database/db_connect.php';

// Define QR library path and check if it exists
$qr_lib_path = '../lib/phpqrcode/qrlib.php'; // Adjust this path as needed
if (!file_exists($qr_lib_path)) {
    die("Error: Cannot find qrlib.php at '$qr_lib_path'. Please ensure the phpqrcode library is installed.");
}
require_once $qr_lib_path;

// Check if GD library is enabled
if (!function_exists('ImageCreate')) {
    die("Error: GD library is not enabled. Enable it in php.ini and restart your server.");
}

// Get parameters from URL
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// Validate search parameter
if (empty($search)) {
    die("Error: No customer name provided for printing.");
}

// QR Code Generation Logic
$sql_qr = "SELECT js.id, js.job_name, js.total_charges, js.payment_status,
                  COALESCE(SUM(pr.cash + pr.credit), 0) as total_paid
           FROM job_sheets js
           LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id
           WHERE js.completed_delivery = 1
           AND (js.payment_status IS NULL OR js.payment_status IN ('incomplete', 'partially_paid', 'uncredit'))
           AND js.customer_name LIKE ?
           GROUP BY js.id, js.job_name, js.total_charges, js.payment_status";
$stmt = $conn->prepare($sql_qr);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$search_param = "%$search%";
$stmt->bind_param("s", $search_param);
$stmt->execute();
$qr_result = $stmt->get_result();

$total_balance = 0;
$job_sheets = [];

while ($row = $qr_result->fetch_assoc()) {
    $total_paid = floatval($row['total_paid']);
    $total_charges = floatval($row['total_charges']);
    $balance = $total_charges - $total_paid;
    if ($balance > 0) {
        $total_balance += $balance;
        $job_sheets[] = [
            'id' => $row['id'],
            'job_name' => $row['job_name'],
            'total_charges' => $total_charges,
            'balance' => $balance
        ];
    }
}
$stmt->close();

// Fetch UPI details
$sql_upi = "SELECT upi_id, payee_name FROM upi_settings LIMIT 1";
$upi_result = $conn->query($sql_upi);
$upi_id = '';
$payee_name = '';
if ($upi_row = $upi_result->fetch_assoc()) {
    $upi_id = $upi_row['upi_id'];
    $payee_name = $upi_row['payee_name'];
} else {
    die("Error: UPI settings not found in upi_settings table.");
}

// Generate UPI URL and QR code only if there’s a balance
$qr_path = '';
if ($total_balance > 0) {
    $transaction_note = "Payment for $search (Multiple Jobs)";
    $upi_url = "upi://pay?pa=" . urlencode($upi_id) . "&pn=" . urlencode($payee_name) . "&am=" . number_format($total_balance, 2, '.', '') . "&cu=INR&tn=" . urlencode($transaction_note);

    $qr_dir = '../qrcodes/';
    if (!is_dir($qr_dir)) {
        if (!mkdir($qr_dir, 0755, true)) {
            die("Error: Failed to create directory '$qr_dir'. Check permissions.");
        }
    }
    $qr_file = $qr_dir . 'qr_' . md5($search . time()) . '.png';
    try {
        QRcode::png($upi_url, $qr_file, QR_ECLEVEL_L, 10);
        if (!file_exists($qr_file)) {
            die("Error: QR code file '$qr_file' was not created.");
        }
    } catch (Exception $e) {
        die("Error generating QR code: " . $e->getMessage());
    }

    $base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2);
    $qr_path = "$base_url/qrcodes/" . basename($qr_file);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Payment Details for <?= htmlspecialchars($search) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20mm;
            padding: 0;
            background-color: #fff;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #000;
            box-shadow: none;
        }
        h1 {
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
            color: #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            page-break-inside: auto;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
            background-color: #fff;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .payment-info {
            text-align: center;
            font-size: 16px;
            margin: 10px 0;
            font-weight: bold;
        }
        .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        .qr-code img {
            max-width: 250px;
            height: auto;
        }
        @media print {
            body {
                margin: 0;
                padding: 20mm;
            }
            .container {
                border: none;
                padding: 0;
            }
            h1 {
                font-size: 26px;
            }
            .qr-code img {
                max-width: 300px;
            }
            * {
                color: #000 !important;
                background: transparent !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Payment Details for <?= htmlspecialchars($search) ?></h1>
        <?php if (!empty($job_sheets)): ?>
            <table>
                <tr>
                    <th>Job ID</th>
                    <th>Job Name</th>
                    <th>Total Charges</th>
                    <th>Balance</th>
                </tr>
                <?php foreach ($job_sheets as $sheet): ?>
                    <tr>
                        <td><?= $sheet['id'] ?></td>
                        <td><?= htmlspecialchars($sheet['job_name']) ?></td>
                        <td>₹<?= number_format($sheet['total_charges'], 2) ?></td>
                        <td>₹<?= number_format($sheet['balance'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="3"><strong>Total Balance</strong></td>
                    <td><strong>₹<?= number_format($total_balance, 2) ?></strong></td>
                </tr>
            </table>
            <?php if ($total_balance > 0 && !empty($qr_path)): ?>
                <div class="payment-info">
                    <p>Pay Total Balance (₹<?= number_format($total_balance, 2) ?>) via UPI: <?= htmlspecialchars($upi_id) ?></p>
                </div>
                <div class="qr-code">
                    <img src="<?= htmlspecialchars($qr_path) ?>" alt="QR Code for Payment" onerror="this.style.display='none'; this.nextSibling.style.display='block';">
                    <p style="display: none; color: red;">Failed to load QR code image.</p>
                </div>
            <?php else: ?>
                <p>No payment due. All balances are settled.</p>
            <?php endif; ?>
        <?php else: ?>
            <p>No job sheets found for this customer with partial payments.</p>
        <?php endif; ?>
    </div>
    <script>
        window.print();
        window.onafterprint = function() {
            window.close(); // Close the window after printing
        };
    </script>
</body>
</html>