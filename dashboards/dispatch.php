<?php
include '../database/db_connect.php';

$sql = "SELECT * FROM dispatch_jobs ORDER BY dispatched_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* General Layout */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        .navbar {
            background-color: #007bff;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar .brand {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }

        .nav-buttons button {
            padding: 8px 16px;
            background-color: #dc3545;
            border: none;
            border-radius: 5px;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .nav-buttons button:hover {
            background-color: #c82333;
        }

        .content {
            width: 90%;
            margin: 30px auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        h3 {
            color: #007bff;
            font-size: 28px;
            margin-bottom: 20px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Table Styling */
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #007bff;
            color: white;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f1f1f1;
            transition: background-color 0.3s ease;
        }

        td {
            font-size: 14px;
            color: #333;
        }

        /* Payment Status Styling */
        .payment-status {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 15px;
            display: inline-block;
        }

        .payment-status.completed {
            background-color: #28a745;
            color: white;
        }

        .payment-status.partially_paid {
            background-color: #ffc107;
            color: black;
        }

        .payment-status.uncredit {
            background-color: #dc3545;
            color: white;
        }

        .payment-status.incomplete {
            background-color: #6c757d;
            color: white;
        }

        /* Print Button */
        .print-btn {
            padding: 8px 15px;
            background-color: #17a2b8;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
        }

        .print-btn:hover {
            background-color: #138496;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .print-btn i {
            margin-right: 5px;
        }

        /* No Jobs Message */
        .no-jobs {
            text-align: center;
            font-size: 18px;
            color: #666;
            padding: 20px;
            background-color: #f8d7da;
            border-radius: 8px;
            border: 1px solid #f5c6cb;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .content {
                width: 95%;
                padding: 15px;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 10px;
            }

            .print-btn {
                padding: 6px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>

    <div class="navbar">
        <h2 class="brand">Dispatch Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="content">
        <h3>Welcome, Dispatch User</h3>
        <?php if ($result && $result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer Name</th>
                        <th>Job Name</th>
                        <th>Total Charges</th>
                        <th>Description</th>
                        <th>Payment Status</th>
                        <th>Balance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['job_name']); ?></td>
                            <td>₹<?php echo number_format($row['total_charges'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['description'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="payment-status <?php echo $row['payment_status'] ?? 'incomplete'; ?>">
                                    <?php 
                                    switch ($row['payment_status']) {
                                        case 'completed': echo 'Fully Paid'; break;
                                        case 'partially_paid': echo 'Partially Paid'; break;
                                        case 'uncredit': echo 'Credit Pending'; break;
                                        default: echo 'Pending';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>₹<?php echo number_format($row['balance'], 2); ?></td>
                            <td>
                                <button class="print-btn" onclick="window.open('print_dispatch_job.php?id=<?php echo $row['id']; ?>', '_blank')">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-jobs">No jobs dispatched yet.</p>
        <?php endif; ?>
    </div>

</body>
</html>