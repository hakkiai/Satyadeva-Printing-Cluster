<?php
include '../database/db_connect.php';

// Get parameters from URL
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'partially_paid';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Validate search parameter if used
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
}

// Fetch orders
$sql = "SELECT js.* 
        FROM job_sheets js 
        LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id 
        WHERE js.completed_delivery = 1";

if ($filter === 'partially_paid') {
    $sql .= " AND (js.payment_status IS NULL OR js.payment_status IN ('incomplete', 'partially_paid', 'uncredit'))";
} elseif ($filter === 'fully_paid') {
    $sql .= " AND js.payment_status = 'completed'";
}

if (!empty($search)) {
    $sql .= " AND (js.customer_name LIKE '%$search%' OR js.id LIKE '%$search%')";
}

if (!empty($from_date) && !empty($to_date)) {
    $sql .= " AND js.created_at BETWEEN '$from_date' AND '$to_date'";
} elseif (!empty($from_date)) {
    $sql .= " AND js.created_at >= '$from_date'";
} elseif (!empty($to_date)) {
    $sql .= " AND js.created_at <= '$to_date'";
}

$sql .= " GROUP BY js.id ORDER BY js.id DESC LIMIT $offset, $records_per_page";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Page - Accounts Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20mm;
            padding: 0;
            background-color: #fff;
        }
        .container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #000;
        }
        h1 {
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
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
            * {
                color: #000 !important;
                background: transparent !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Accounts Dashboard - Print Page</h1>
        <?php if ($result && $result->num_rows > 0): ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Customer Name</th>
                    <th>Job Name</th>
                    <th>Total Charges</th>
                    <th>Payment Status</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td><?= htmlspecialchars($row['job_name']) ?></td>
                        <td>₹<?= number_format($row['total_charges'], 2) ?></td>
                        <td style="color: <?= $row['payment_status'] === 'incomplete' || $row['payment_status'] === NULL ? 'red' : ($row['payment_status'] === 'partially_paid' ? 'orange' : ($row['payment_status'] === 'uncredit' ? '#ff4500' : 'green')); ?>; font-weight: bold;">
                            <?php
                            if ($row['payment_status'] === 'completed') {
                                echo 'Fully Paid';
                            } elseif ($row['payment_status'] === 'partially_paid') {
                                echo 'Partially Paid';
                            } elseif ($row['payment_status'] === 'uncredit') {
                                echo 'Partially Paid';
                            } else {
                                echo 'Pending';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p>No orders found for the selected filter or search criteria.</p>
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