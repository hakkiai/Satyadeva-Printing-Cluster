<?php
include '../database/db_connect.php';

// Debug: Check if qrlib.php exists
$qr_lib_path = '../lib/phpqrcode/qrlib.php';
if (!file_exists($qr_lib_path)) {
    die("Error: Cannot find qrlib.php at '$qr_lib_path'. Please ensure the phpqrcode library is installed.");
}
require_once $qr_lib_path;

// Debug: Check if GD is enabled
if (!function_exists('ImageCreate')) {
    die("Error: GD library is not enabled. Enable it in php.ini and restart your server.");
}

// Get job sheet ID and paper_charges parameter from URL
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$paper_charges_option = isset($_GET['paper_charges']) ? $_GET['paper_charges'] : 'with';

if (!$job_id) {
    die("No job sheet ID provided.");
}

// Fetch job sheet details
$sql = "SELECT customer_name, job_name, machine, total_charges, printing_charges AS paper_charges 
        FROM job_sheets 
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $customer_name = $row['customer_name'];
    $job_name = $row['job_name'];
    $machine = $row['machine'];
    $paper_charges = $row['paper_charges'];
    $total_charges = $row['total_charges'];
} else {
    die("Job sheet not found.");
}
$stmt->close();

// Fetch payment records
$sql = "SELECT date, cash, credit, balance, payment_type FROM payment_records WHERE job_sheet_id = ? ORDER BY date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);

// Determine the balance amount
$balance_amount = $total_charges;
if (!empty($payments)) {
    $balance_amount = $payments[0]['balance'];
}
$stmt->close();

// Determine the amount to use for QR code and display
if ($paper_charges_option === 'with') {
    $amount = $paper_charges;
    $amount_label = "Paper Charges";
} else {
    $amount = $balance_amount;
    $amount_label = "Balance Amount";
}

// Fetch UPI details
$sql = "SELECT upi_id, paper_charges_upi_id, payee_name FROM upi_settings LIMIT 1";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) {
    $default_upi_id = $row['upi_id'];
    $paper_charges_upi_id = $row['paper_charges_upi_id'];
    $payee_name = $row['payee_name'];
    $selected_upi_id = ($paper_charges_option === 'with' && $paper_charges_upi_id) ? $paper_charges_upi_id : $default_upi_id;
} else {
    die("UPI settings not found in upi_settings table.");
}

// Generate UPI URL
$transaction_note = "Payment for Job Sheet #$job_id";
$upi_url = "upi://pay?pa=" . urlencode($selected_upi_id) . "&pn=" . urlencode($payee_name) . "&am=" . number_format($amount, 2, '.', '') . "&cu=INR&tn=" . urlencode($transaction_note);

// Generate QR code
$qr_dir = '../qrcodes/';
if (!is_dir($qr_dir)) {
    if (!mkdir($qr_dir, 0755, true)) {
        die("Error: Failed to create directory '$qr_dir'. Check permissions.");
    }
}
$qr_file = $qr_dir . "qr_job_$job_id" . ($paper_charges_option === 'with' ? '_with_paper' : '_balance') . ".png";

try {
    QRcode::png($upi_url, $qr_file, QR_ECLEVEL_L, 4);
    if (!file_exists($qr_file)) {
        die("Error: QR code file '$qr_file' was not created after generation.");
    }
} catch (Exception $e) {
    die("Error generating QR code: " . $e->getMessage());
}

// Use absolute URL for QR code
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2);
$qr_path = "$base_url/qrcodes/qr_job_$job_id" . ($paper_charges_option === 'with' ? '_with_paper' : '_balance') . ".png";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Job Sheet #<?= $job_id ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .container {
            width: 80%;
            margin: auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fff;
        }
        h1 {
            text-align: center;
            color: #007bff;
        }
        .section {
            margin-bottom: 20px;
        }
        .section h2 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .section p {
            margin: 5px 0;
        }
        .bill-details table, .payment-details table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .bill-details th, .bill-details td, .payment-details th, .payment-details td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .bill-details th, .payment-details th {
            background-color: #f2f2f2;
        }
        .qr-code {
            text-align: center;
            margin-top: 20px;
        }
        .qr-code img {
            max-width: 150px;
            height: auto;
        }
        .qr-error {
            color: red;
            font-style: italic;
        }
        .no-print {
            margin-top: 20px;
            text-align: center;
        }
        button {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .print-btn { background-color: #dc3545; color: white; }
        button:hover { opacity: 0.8; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Job Sheet #<?= $job_id ?> (<?= $paper_charges_option === 'with' ? 'Paper Charges Only' : 'Balance Amount with Total Charges' ?>)</h1>

        <div class="section">
            <h2>Job Details</h2>
            <p><strong>Job Sheet ID:</strong> <?= $job_id ?></p>
            <p><strong>Customer Name:</strong> <?= htmlspecialchars($customer_name) ?></p>
            <p><strong>Job Name:</strong> <?= htmlspecialchars($job_name) ?></p>
            <p><strong>Machine:</strong> <?= htmlspecialchars($machine ?: 'Not specified') ?></p>
        </div>

        <div class="section bill-details">
            <h2>Bill Summary</h2>
            <table>
                <tr>
                    <th>Description</th>
                    <th>Amount (₹)</th>
                </tr>
                <?php if ($paper_charges_option === 'with'): ?>
                    <tr>
                        <td>Paper Charges</td>
                        <td><?= number_format($paper_charges, 2) ?></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td>Total Charges</td>
                        <td><?= number_format($total_charges, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Balance Amount</td>
                        <td><?= number_format($balance_amount, 2) ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="section payment-details">
            <h2>Payment Statements</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Payment Type</th>
                        <th>Amount Paid (₹)</th>
                        <th>Balance (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payments)): ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= date('d-M-Y h:i:s A', strtotime($payment['date'])) ?></td>
                                <td><?= ucfirst($payment['payment_type']) ?></td>
                                <td><?= $payment['payment_type'] === 'credit' ? number_format($payment['credit'], 2) : number_format($payment['cash'], 2) ?></td>
                                <td><?= number_format($payment['balance'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">No payment records found for this job.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="section qr-code">
            <h3>Pay <?= $amount_label ?> (₹<?= number_format($amount, 2) ?>) via UPI: <?= htmlspecialchars($selected_upi_id) ?></h3>
            <?php if (file_exists($qr_file)): ?>
                <img src="<?= htmlspecialchars($qr_path) ?>" alt="QR Code for Payment" onerror="this.style.display='none'; this.nextSibling.style.display='block';">
                <p class="qr-error" style="display: none;">Failed to load QR code image. Check the file path or contact support.</p>
            <?php else: ?>
                <p class="qr-error">QR code file not found at '<?= $qr_file ?>'. Check permissions or generation process.</p>
            <?php endif; ?>
        </div>

        <div class="no-print">
            <button class="print-btn" onclick="window.print()">Print</button>
            <button onclick="window.close()">Close</button>
        </div>
    </div>

    <script>
        window.onload = function() {
            setTimeout(() => {
                console.log("QR Code Image URL: <?= $qr_path ?>");
            }, 500);
        };
    </script>
</body>
</html>