<?php
include '../database/db_connect.php';

// Get job sheet ID and paper_charges parameter from URL
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$paper_charges_option = isset($_GET['paper_charges']) ? $_GET['paper_charges'] : 'with';

if (!$job_id) {
    die("No job sheet ID provided.");
}

// Fetch job sheet details with paper subcategory and type names
$sql = "SELECT js.*, 
        isc.subcategory_name AS paper_subcategory_name, 
        ii.item_name AS type_name 
        FROM job_sheets js 
        LEFT JOIN inventory_subcategories isc ON js.paper_subcategory = isc.id 
        LEFT JOIN inventory_items ii ON js.type = ii.id 
        WHERE js.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $customer_name = $row['customer_name'];
    $phone_number = $row['phone_number'];
    $job_name = $row['job_name'];
    $paper_subcategory = $row['paper_subcategory_name'];
    $type = $row['type_name'];
    $quantity = $row['quantity'];
    $striking = $row['striking'];
    $machine = $row['machine'];
    $ryobi_type = $row['ryobi_type'];
    $web_type = $row['web_type'];
    $web_size = $row['web_size'];
    $ctp_plate = $row['ctp_plate'];
    $ctp_quantity = $row['ctp_quantity'];
    $paper_charges = $row['paper_charges']; // Printing Charges in DB
    $plating_charges = $row['plating_charges'];
    $printing_charges = $row['printing_charges']; // Paper Charges in DB
    $lamination_charges = $row['lamination_charges'];
    $pinning_charges = $row['pinning_charges'];
    $binding_charges = $row['binding_charges'];
    $finishing_charges = $row['finishing_charges'];
    $other_charges = $row['other_charges'];
    $discount = $row['discount'];
    $status = $row['status'];

    // Calculate total charges based on the paper_charges option
    if ($paper_charges_option === 'with') {
        // Only Paper Charges (printing_charges in DB)
        $total_charges = $printing_charges;
    } else {
        // Exclude Paper Charges (printing_charges in DB)
        $total_charges = $paper_charges + $plating_charges + $lamination_charges + 
                         $pinning_charges + $binding_charges + $finishing_charges + 
                         $other_charges - $discount;
        $total_charges = max($total_charges, 0); // Ensure total is not negative
    }
} else {
    die("Job sheet not found.");
}

$stmt->close();
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
        .bill-details table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .bill-details th, .bill-details td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .bill-details th {
            background-color: #f2f2f2;
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
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Job Sheet #<?= $job_id ?> (<?= $paper_charges_option === 'with' ? 'Paper Charges Only' : 'Without Paper Charges' ?>)</h1>

        <div class="section">
            <h2>Customer Details</h2>
            <p><strong>Customer Name:</strong> <?= htmlspecialchars($customer_name) ?></p>
            <p><strong>Phone Number:</strong> <?= htmlspecialchars($phone_number) ?></p>
            <p><strong>Job Name:</strong> <?= htmlspecialchars($job_name) ?></p>
        </div>

        <div class="section">
            <h2>Paper Section</h2>
            <p><strong>Paper Subcategory:</strong> <?= htmlspecialchars($paper_subcategory ?: 'Not specified') ?></p>
            <p><strong>Type:</strong> <?= htmlspecialchars($type ?: 'Not specified') ?></p>
            <p><strong>Quantity:</strong> <?= $quantity ?></p>
            <p><strong>Striking:</strong> <?= $striking ?></p>
        </div>

        <div class="section">
            <h2>Machine Selection</h2>
            <p><strong>Machine:</strong> <?= $machine ?: 'Not specified' ?></p>
            <?php if ($machine == 'RYOBI'): ?>
                <p><strong>RYOBI Type:</strong> <?= $ryobi_type ?: 'Not specified' ?></p>
            <?php endif; ?>
            <?php if ($machine == 'Web'): ?>
                <p><strong>Web Type:</strong> <?= $web_type ?: 'Not specified' ?></p>
                <p><strong>Web Size:</strong> <?= $web_size ?: 'Not specified' ?></p>
            <?php endif; ?>
            <?php if ($ctp_plate): ?>
                <p><strong>CTP Plate:</strong> <?= $ctp_plate ?></p>
                <p><strong>CTP Quantity:</strong> <?= $ctp_quantity ?></p>
            <?php endif; ?>
        </div>

        <div class="section bill-details">
            <h2>Bill Details</h2>
            <table>
                <tr>
                    <th>Description</th>
                    <th>Amount (₹)</th>
                </tr>
                <?php if ($paper_charges_option !== 'with'): ?>
                    <!-- Show all charges except Paper Charges when paper_charges=without -->
                    <tr>
                        <td>Printing Charges</td>
                        <td><?= number_format($paper_charges, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Plating Charges</td>
                        <td><?= number_format($plating_charges, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Lamination Charges</td>
                        <td><?= number_format($lamination_charges, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Pinning Charges</td>
                        <td><?= number_format($pinning_charges, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Binding Charges</td>
                        <td><?= number_format($binding_charges, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Finishing Charges</td>
                        <td><?= number_format($finishing_charges, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Other Charges</td>
                        <td><?= number_format($other_charges, 2) ?></td>
                    </tr>
                    <tr>
                        <td>Discount</td>
                        <td>-<?= number_format($discount, 2) ?></td>
                    </tr>
                <?php else: ?>
                    <!-- Show only Paper Charges when paper_charges=with -->
                    <tr>
                        <td>Paper Charges</td>
                        <td><?= number_format($printing_charges, 2) ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th>Total Charges</th>
                    <th><?= number_format($total_charges, 2) ?></th>
                </tr>
            </table>
        </div>

        <div class="section">
            <p><strong>Status:</strong> <?= $status ?></p>
        </div>

        <div class="no-print">
            <button class="print-btn" onclick="window.print()">Print</button>
            <button onclick="window.close()">Close</button>
        </div>
    </div>
</body>
</html>