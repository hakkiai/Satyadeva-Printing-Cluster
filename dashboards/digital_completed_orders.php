<?php
include '../database/db_connect.php';

// Fetch completed Digital orders, newest first
$sql = "SELECT * FROM job_sheets WHERE digital = 1 AND completed_digital = 1 ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Completed Digital Orders</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        table { width: 90%; margin: 20px auto; border-collapse: collapse; background-color: white; border-radius: 10px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background-color: #007bff; color: white; font-size: 16px; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .download-link { color: #17a2b8; text-decoration: none; margin: 0 5px; }
        .download-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Completed Digital Orders</h2>
        <div class="nav-buttons">
            <button onclick="location.href='digital_dashboard.php'">Back to Dashboard</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="main-container">
        <div class="user-container">
            <h2>Completed Digital Orders</h2>
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
                                            <a href="digital_dashboard.php?download_file=<?= urlencode($file) ?>&job_id=<?= $row['id'] ?>" class="download-link">Download <?= $index + 1 ?></a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        No file
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['description'] ?? 'No description') ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No completed Digital orders found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>