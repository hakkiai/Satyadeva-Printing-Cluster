<?php
include '../database/db_connect.php';

// Handle completion action
if (isset($_GET['complete_id'])) {
    $job_id = $_GET['complete_id'];
    $sql = "UPDATE job_sheets SET completed = 1 WHERE id = ? AND ctp = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    if ($stmt->execute()) {
        echo "<script>alert('Order marked as completed!'); window.location.href='ctp_orders.php';</script>";
    } else {
        echo "<script>alert('Error marking order as completed.');</script>";
    }
    $stmt->close();
}

// Fetch active CTP orders (not completed)
$job_id = isset($_GET['id']) ? $_GET['id'] : null;
if ($job_id) {
    $sql = "SELECT * FROM job_sheets WHERE id = ? AND ctp = 1 AND completed = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM job_sheets WHERE ctp = 1 AND completed = 0";
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        table {
            width: 90%;
            margin: 20px auto;
            border-collapse: collapse;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
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
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        button {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 0 5px;
        }
        .view-btn { background-color: #28a745; color: white; }
        .complete-btn { background-color: #ffc107; color: black; } /* Yellow for Complete */
        button:hover { opacity: 0.8; }
    </style>
</head>
<body>

<div class="navbar">
    <h2 class="brand">My Orders</h2>
    <div class="nav-buttons">
        <button onclick="location.href='ctp_dashboard.php'">Back to Dashboard</button>
        <button onclick="location.href='../auth/logout.php'">Logout</button>
    </div>
</div>

<div class="main-container">
    <div class="user-container">
        <h2>CTP Order List</h2>
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
                                <?php if ($row['file_path']): ?>
                                    <a href="<?= $row['file_path'] ?>" target="_blank">Download</a>
                                <?php else: ?>
                                    No file
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['description'] ?? 'No description') ?></td>
                            <td>
                                <button class="view-btn" onclick="location.href='ctp_view_order.php?id=<?= $row['id'] ?>'">View</button>
                                <button class="complete-btn" onclick="if(confirm('Mark this order as completed?')) location.href='ctp_orders.php?complete_id=<?= $row['id'] ?>'">Completed</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No active CTP orders found.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>