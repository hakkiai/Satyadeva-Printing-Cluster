<?php
include '../database/db_connect.php';

// Handle ZIP download
if (isset($_GET['download_id'])) {
    $job_id = $_GET['download_id'];
    $sql = "SELECT file_path FROM job_sheets WHERE id = ? AND completed_ctp = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row && $row['file_path']) {
        ob_clean();
        ob_start();

        $file_paths = explode(',', $row['file_path']);
        $zip = new ZipArchive();
        $zip_name = "job_{$job_id}_files.zip";
        $zip_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zip_name;

        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($file_paths as $file_path) {
                $file_path = trim($file_path);
                if (file_exists($file_path) && is_readable($file_path)) {
                    $file_name = basename($file_path);
                    $zip->addFile($file_path, $file_name);
                } else {
                    error_log("File not found or unreadable: $file_path");
                }
            }
            if (!$zip->close()) {
                error_log("Failed to close ZIP file: $zip_path");
                exit("Failed to finalize ZIP file");
            }

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_name . '"');
            header('Content-Length: ' . filesize($zip_path));
            header('Cache-Control: no-cache');
            header('Pragma: no-cache');
            header('Expires: 0');

            ob_end_flush();
            readfile($zip_path);
            unlink($zip_path);
            exit;
        } else {
            ob_end_clean();
            error_log("Failed to create ZIP file: $zip_path");
            echo "<script>alert('Failed to create ZIP file!');</script>";
            exit;
        }
    } else {
        echo "<script>alert('No files found for this job!');</script>";
        exit;
    }
}

// Fetch completed CTP orders, newest first
$sql = "SELECT * FROM job_sheets WHERE completed_ctp = 1 ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CTP Completed Orders</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        table { width: 90%; margin: 20px auto; border-collapse: collapse; background-color: white; border-radius: 10px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background-color: #007bff; color: white; font-size: 16px; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        button { padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin: 0 5px; }
        .view-btn { background-color: #28a745; color: white; }
        .download-btn { background-color: #17a2b8; color: white; }
        button:hover { opacity: 0.8; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">CTP Completed Orders</h2>
        <div class="nav-buttons">
            <button onclick="location.href='ctp_dashboard.php'">Home</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="main-container">
        <div class="user-container">
            <h2>Completed CTP Orders</h2>
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
                                        <button class="download-btn" onclick="location.href='ctp_completed_orders.php?download_id=<?= $row['id'] ?>'">Download All</button>
                                    <?php else: ?>
                                        No file
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['description'] ?? 'No description') ?></td>
                                <td>
                                    <button class="view-btn" onclick="location.href='ctp_view_order.php?id=<?= $row['id'] ?>'">View</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No completed CTP orders found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>