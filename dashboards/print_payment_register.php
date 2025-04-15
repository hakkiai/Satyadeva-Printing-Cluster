<?php
include '../database/db_connect.php';
date_default_timezone_set('Asia/Kolkata');

// Get query parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';

// Fetch payment records
$sql = "SELECT pr.job_sheet_id, pr.job_sheet_name, pr.date, pr.cash, pr.credit, pr.balance, pr.payment_type, pr.payment_status, 
               js.customer_name, js.total_charges 
        FROM payment_records pr 
        LEFT JOIN job_sheets js ON pr.job_sheet_id = js.id 
        WHERE 1=1";
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $sql .= " AND js.customer_name LIKE '%$search%'";
}
if (!empty($from_date) && !empty($to_date)) {
    $sql .= " AND pr.date BETWEEN '$from_date 00:00:00' AND '$to_date 23:59:59'";
} elseif (!empty($from_date)) {
    $sql .= " AND pr.date >= '$from_date 00:00:00'";
} elseif (!empty($to_date)) {
    $sql .= " AND pr.date <= '$to_date 23:59:59'";
}
$sql .= " ORDER BY pr.date DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Payment Register</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #fff;
            color: #333;
        }
        .container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }
        h2 {
            text-align: center;
            margin-bottom: 10px;
            font-size: 24px;
        }
        .filter-info {
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }
        .filter-info p {
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
            font-size: 12px;
        }
        th {
            background-color: #e0e0e0;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .no-records {
            text-align: center;
            font-size: 14px;
            margin: 20px 0;
        }
        .close-btn {
            display: block;
            margin: 0 auto;
            padding: 8px 16px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .close-btn:hover {
            background-color: #c82333;
        }
        @media print {
            .close-btn {
                display: none;
            }
            body {
                margin: 0;
            }
            .container {
                width: 100%;
                max-width: none;
            }
            h2 {
                font-size: 20px;
            }
            table {
                font-size: 10px;
            }
            th, td {
                padding: 6px;
            }
        }
    </style>
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</head>
<body>
    <div class="container">
        <h2>Payment Register</h2>
        <div class="filter-info">
            <?php if (!empty($search)): ?>
                <p><strong>Customer:</strong> <?php echo htmlspecialchars($search); ?></p>
            <?php endif; ?>
            <?php if (!empty($from_date) || !empty($to_date)): ?>
                <p><strong>Date Range:</strong> 
                    <?php echo !empty($from_date) ? htmlspecialchars($from_date) : 'Any'; ?> 
                    to 
                    <?php echo !empty($to_date) ? htmlspecialchars($to_date) : 'Any'; ?>
                </p>
            <?php endif; ?>
        </div>
        <?php if ($result && $result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>Customer Name</th>
                        <th>Job Name</th>
                        <th>Date & Time</th>
                        <th>Payment Method</th>
                        <th>Amount Paid</th>
                        <th>Total Charges</th>
                        <th>Balance</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['job_sheet_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['job_sheet_name'] ?? 'N/A'); ?></td>
                            <td><?php echo date('d-M-Y h:i:s A', strtotime($row['date'])); ?></td>
                            <td><?php echo ucfirst($row['payment_type']); ?></td>
                            <td>
                                ₹<?php 
                                $amount = ($row['payment_type'] === 'credit') ? $row['credit'] : $row['cash'];
                                echo number_format($amount, 2);
                                ?>
                            </td>
                            <td>₹<?php echo number_format($row['total_charges'] ?? 0, 2); ?></td>
                            <td>₹<?php echo number_format($row['balance'], 2); ?></td>
                            <td>
                                <?php
                                $status = ($row['payment_status'] === 'completed') ? 'Fully Paid' : (($row['payment_type'] === 'credit') ? 'Partially Paid' : 'Partially Paid');
                                echo $status;
                                ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-records">No payment records found for the selected criteria.</p>
        <?php endif; ?>
        <button class="close-btn" onclick="window.close()">Close</button>
    </div>
</body>
</html>