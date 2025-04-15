<?php
include '../database/db_connect.php';
session_start();

// Handle submit action
if (isset($_GET['submit_id'])) {
    $job_id = $_GET['submit_id'];
    $sql = "UPDATE job_sheets SET completed_delivery = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    if ($stmt->execute()) {
        echo "<script>alert('Order submitted for delivery!'); window.location.href='delivery.php';</script>";
    } else {
        echo "<script>alert('Error submitting order.');</script>";
    }
    $stmt->close();
    exit;
}

// Handle individual file download
if (isset($_GET['download_file']) && isset($_GET['job_id'])) {
    $job_id = $_GET['job_id'];
    $file_path = $_GET['download_file'];
    $sql = "SELECT file_path FROM job_sheets WHERE id = ? AND completed_delivery = 0";
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
            echo "<script>alert('File not found or inaccessible!'); window.location.href='delivery.php';</script>";
        }
    } else {
        echo "<script>alert('Invalid file or job!'); window.location.href='delivery.php';</script>";
    }
}

// Fetch active delivery orders, newest first
$sql = "SELECT * FROM job_sheets WHERE completed_delivery = 0 AND (completed_ctp = 1 OR completed_multicolour = 1 OR completed_digital = 1) ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delivery Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        table { 
            width: 90%; 
            margin: 20px auto; 
            border-collapse: collapse; 
            background-color: white; 
            border-radius: 10px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: center; 
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
        .download-link { 
            color: #17a2b8; 
            text-decoration: none; 
            margin: 0 5px; 
            font-weight: bold; 
        }
        .download-link:hover { 
            text-decoration: underline; 
            color: #138496; 
        }
        button { 
            padding: 8px 12px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: bold; 
            margin: 0 5px; 
            transition: opacity 0.3s ease; 
        }
        .view-btn { 
            background-color: #28a745; 
            color: white; 
        }
        .submit-btn { 
            background-color: #dc3545; 
            color: white; 
        }
        button:hover { 
            opacity: 0.8; 
        }
        .from-ctp-multicolour { 
            background-color: #fff3e6; 
        }
        .from-digital { 
            background-color: #e6f3ff; /* Light blue for Digital orders */
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Delivery Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='delivered_orders.php'">Delivered Orders</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="main-container">
        <div class="user-container">
            <h2>Welcome, Delivery User!</h2>
            <h3>Active Delivery Orders</h3>
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
                            <th>Tracking</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr <?php 
                                if ($row['ctp'] == 1 && $row['multicolour'] == 1) {
                                    echo 'class="from-ctp-multicolour"';
                                } elseif ($row['digital'] == 1) {
                                    echo 'class="from-digital"';
                                }
                            ?>>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['job_name']) ?></td>
                                <td>₹<?= number_format($row['total_charges'], 2) ?></td>
                                <td>
                                    <?php 
                                    $db_files = $row['file_path'] ? explode(',', $row['file_path']) : [];
                                    if (!empty($db_files)): ?>
                                        <?php foreach ($db_files as $index => $file): ?>
                                            <a href="delivery.php?download_file=<?= urlencode($file) ?>&job_id=<?= $row['id'] ?>" class="download-link">Download <?= $index + 1 ?></a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        No file
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['description'] ?? 'No description') ?></td>
                                <td>
                                    <?php 
                                    if ($row['ctp'] == 1 && $row['multicolour'] == 1) {
                                        echo "CTP & Multicolour";
                                    } elseif ($row['ctp'] == 1) {
                                        echo "CTP Only";
                                    } elseif ($row['multicolour'] == 1) {
                                        echo "Multicolour Only";
                                    } elseif ($row['digital'] == 1) {
                                        echo "Digital";
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button class="view-btn" onclick="location.href='delivery_view_order.php?id=<?= $row['id'] ?>'">View</button>
                                    <button class="submit-btn" onclick="if(confirm('Submit this order for delivery?')) location.href='delivery.php?submit_id=<?= $row['id'] ?>'">Submit</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No active delivery orders found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
$conn->close();
?>