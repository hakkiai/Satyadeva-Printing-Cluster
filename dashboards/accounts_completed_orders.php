<?php
include '../database/db_connect.php';

// Handle individual file download
if (isset($_GET['download_file']) && isset($_GET['job_id'])) {
    $job_id = $_GET['job_id'];
    $file_path = $_GET['download_file'];
    $sql = "SELECT file_path FROM job_sheets WHERE id = ? AND completed_delivery = 1 AND payment_status = 'completed'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row && in_array($file_path, explode(',', $row['file_path']))) {
        if (file_exists($file_path) && is_readable($file_path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            echo "<script>alert('File not found or inaccessible!'); window.location.href='accounts_completed_orders.php';</script>";
        }
    } else {
        echo "<script>alert('Invalid file or job!'); window.location.href='accounts_completed_orders.php';</script>";
    }
}

// Fetch completed payment orders, newest first
$sql = "SELECT * FROM job_sheets WHERE completed_delivery = 1 AND payment_status = 'completed' ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Accounts Completed Orders</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        table { width: 90%; margin: 20px auto; border-collapse: collapse; background-color: white; border-radius: 10px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background-color: #007bff; color: white; font-size: 16px; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        button { padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin: 0 5px; }
        .view-btn { background-color: #28a745; color: white; }
        .statement-btn { background-color: #ffc107; color: black; }
        .print-btn { background-color: #dc3545; color: white; }
        .download-link { color: #17a2b8; text-decoration: none; margin: 0 5px; }
        .download-link:hover { text-decoration: underline; }
        button:hover { opacity: 0.8; }
        .dialog-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }
        .dialog-box {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
            text-align: center;
        }
        .dialog-box button {
            margin: 10px 5px;
        }
        .yes-btn { background-color: #28a745; color: white; }
        .no-btn { background-color: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Accounts Completed Orders</h2>
        <div class="nav-buttons">
            <button onclick="location.href='accounts_dashboard.php'">Home</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="main-container">
        <div class="user-container">
            <h2>Completed Payment Orders</h2>
            <?php 
            if ($result) {
                echo "<p>Number of completed orders found: " . $result->num_rows . "</p>";
            } else {
                echo "<p>Query failed: " . $conn->error . "</p>";
            }
            ?>
            <?php if ($result && $result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer Name</th>
                            <th>Job Name</th>
                            <th>Total Charges</th>
                            <th>File</th>
                            <th>Description</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['job_name']) ?></td>
                                <td>₹<?= number_format($row['total_charges'], 2) ?></td>
                                <td>
                                    <?php 
                                    $db_files = $row['file_path'] ? explode(',', $row['file_path']) : [];
                                    if (!empty($db_files)): ?>
                                        <?php foreach ($db_files as $index => $file): ?>
                                            <a href="accounts_completed_orders.php?download_file=<?= urlencode($file) ?>&job_id=<?= $row['id'] ?>" class="download-link">Download <?= $index + 1 ?></a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        No file
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['description'] ?? 'No description') ?></td>
                                <td style="color: green; font-weight: bold;">Paid</td>
                                <td>
                                    <button class="view-btn" onclick="location.href='accounts_view_order.php?id=<?= $row['id'] ?>'">View</button>
                                    <button class="statement-btn" onclick="location.href='payment_statement.php?job_id=<?= $row['id'] ?>'">Statement</button>
                                    <button class="print-btn" onclick="printJobSheet(<?= $row['id'] ?>)">Print</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No completed payment orders found.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Custom dialog -->
    <div id="printDialog" class="dialog-overlay">
        <div class="dialog-box">
            <p>Do you want to include Paper Charges in the print?</p>
            <button class="yes-btn" id="yesBtn">Yes</button>
            <button class="no-btn" id="noBtn">No</button>
        </div>
    </div>

    <script>
        function printJobSheet(jobId) {
            let dialog = document.getElementById('printDialog');
            dialog.style.display = 'flex';

            document.getElementById('yesBtn').onclick = function() {
                dialog.style.display = 'none';
                let url = 'print_account_job_sheet.php?id=' + jobId + '&paper_charges=with';
                window.open(url, '_blank', 'width=800,height=600');
            };

            document.getElementById('noBtn').onclick = function() {
                dialog.style.display = 'none';
                let url = 'print_account_job_sheet.php?id=' + jobId + '&paper_charges=without';
                window.open(url, '_blank', 'width=800,height=600');
            };
        }
    </script>
</body>
</html>