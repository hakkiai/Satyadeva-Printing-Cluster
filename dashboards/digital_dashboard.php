<?php
include '../database/db_connect.php';
session_start();

// Handle completion action
if (isset($_GET['complete_id'])) {
    $job_id = $_GET['complete_id'];
    $new_file_paths = $_SESSION['new_file_paths'][$job_id] ?? [];
    $sql = "SELECT file_path FROM job_sheets WHERE id = ? AND digital = 1 AND completed_digital = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $job = $result->fetch_assoc();

    if ($job && ($job['file_path'] || !empty($new_file_paths))) {
        $file_paths_string = $job['file_path'] ?: implode(',', $new_file_paths); // Use existing files or new ones if present

        $sql = "UPDATE job_sheets SET completed_digital = 1, file_path = ? WHERE id = ? AND digital = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $file_paths_string, $job_id);
        if ($stmt->execute()) {
            $sql = "UPDATE job_sheets SET completed_delivery = 0 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
            unset($_SESSION['new_file_paths'][$job_id]);
            echo "<script>alert('Order completed in Digital and sent to Delivery!'); window.location.href='digital_dashboard.php';</script>";
        } else {
            echo "<script>alert('Error marking order as completed: " . $stmt->error . "');</script>";
        }
    } else {
        echo "<script>alert('Please ensure at least one file is associated with this job before completing!');</script>";
    }
    $stmt->close();
    exit;
}

// Handle individual file download
if (isset($_GET['download_file']) && isset($_GET['job_id'])) {
    $job_id = $_GET['job_id'];
    $file_path = $_GET['download_file'];
    $sql = "SELECT file_path FROM job_sheets WHERE id = ? AND completed_digital = 0";
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
            echo "<script>alert('File not found or inaccessible!'); window.location.href='digital_dashboard.php';</script>";
        }
    } else {
        echo "<script>alert('Invalid file or job!'); window.location.href='digital_dashboard.php';</script>";
    }
}

// Fetch active Digital orders, newest first
$sql = "SELECT * FROM job_sheets WHERE digital = 1 AND completed_digital = 0 AND (completed_ctp = 1 OR ctp = 0) ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Digital Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        table { width: 90%; margin: 20px auto; border-collapse: collapse; background-color: white; border-radius: 10px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background-color: #007bff; color: white; font-size: 16px; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        button { padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin: 0 5px; }
        .view-btn { background-color: #28a745; color: white; }
        .complete-btn { background-color: #ffc107; color: black; }
        .download-link { color: #17a2b8; text-decoration: none; margin: 0 5px; }
        .download-link:hover { text-decoration: underline; }
        .complete-btn:disabled { background-color: #ccc; cursor: not-allowed; opacity: 0.6; }
        button:hover:not(:disabled) { opacity: 0.8; }
        .from-ctp { background-color: #ffe6e6; }
        .file-list { margin-top: 5px; font-size: 12px; color: #555; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Digital Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='digital_dashboard.php'">Home</button>
            <button onclick="location.href='digital_completed_orders.php'">Completed Orders</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="main-container">
        <div class="user-container">
            <h2>Welcome, Digital User!</h2>
            <h3>Active Digital Orders</h3>
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
                            <tr <?php echo ($row['ctp'] == 1 && $row['digital'] == 1) ? 'class="from-ctp"' : ''; ?>>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['job_name']) ?></td>
                                <td>₹<?= number_format($row['total_charges'], 2) ?></td>
                                <td>
                                    <?php 
                                    $uploaded_files = isset($_SESSION['new_file_paths'][$row['id']]) ? $_SESSION['new_file_paths'][$row['id']] : [];
                                    $db_files = $row['file_path'] ? explode(',', $row['file_path']) : [];
                                    if (!empty($db_files)): ?>
                                        <?php foreach ($db_files as $index => $file): ?>
                                            <a href="digital_dashboard.php?download_file=<?= urlencode($file) ?>&job_id=<?= $row['id'] ?>" class="download-link">Download <?= $index + 1 ?></a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($uploaded_files)): ?>
                                        <div class="file-list"><?= count($uploaded_files) ?> new file(s) uploaded (complete to download)</div>
                                    <?php elseif (empty($db_files)): ?>
                                        No file
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['description'] ?? 'No description') ?>
                                    <?php if ($row['ctp'] == 1 && $row['digital'] == 1): ?>
                                        <br><span style="color: red; font-weight: bold;">(From CTP)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="view-btn" onclick="location.href='digital_view_order.php?id=<?= $row['id'] ?>'">View</button>
                                    <button class="complete-btn" onclick="if(confirm('Mark this order as completed?')) location.href='digital_dashboard.php?complete_id=<?= $row['id'] ?>'" 
                                        <?php echo ($row['file_path'] || !empty($uploaded_files)) ? '' : 'disabled'; ?>>Completed</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No active Digital orders found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>