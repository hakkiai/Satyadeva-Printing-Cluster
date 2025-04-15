<?php
include '../database/db_connect.php';

if (!isset($_GET['id'])) {
    header("Location: delivered_orders.php");
    exit;
}

$job_id = $_GET['id'];
$sql = "SELECT * FROM job_sheets WHERE id = ? AND completed_delivery = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    header("Location: delivered_orders.php");
    exit;
}

if (isset($_GET['download'])) {
    if ($job['file_path']) {
        $file_paths = explode(',', $job['file_path']);
        $zip = new ZipArchive();
        $zip_name = "job_$job_id_files.zip";
        if ($zip->open($zip_name, ZipArchive::CREATE) === TRUE) {
            foreach ($file_paths as $file_path) {
                if (file_exists($file_path)) {
                    $file_name = basename($file_path);
                    $zip->addFile($file_path, $file_name);
                }
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_name . '"');
            header('Content-Length: ' . filesize($zip_name));
            readfile($zip_name);
            unlink($zip_name);
            exit;
        } else {
            echo "<script>alert('Failed to create ZIP file!');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Delivered Order</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .order-details { background: white; width: 70%; margin: 20px auto; padding: 20px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); text-align: left; }
        .order-details h2 { color: #0056b3; text-align: center; margin-bottom: 20px; }
        .order-details p { margin: 10px 0; font-size: 16px; }
        .order-details p strong { color: #333; font-weight: bold; }
        .order-details button { padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin: 0 5px; }
        .download-btn { background-color: #17a2b8; color: white; }
        .back-btn { display: block; width: 100%; max-width: 200px; margin: 20px auto 0; padding: 10px; background: #0056b3; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; text-align: center; }
        button:hover { opacity: 0.8; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">View Delivered Order</h2>
        <div class="nav-buttons">
            <button class="back-btn" onclick="location.href='delivered_orders.php'">Back to Delivered Orders</button>
        </div>
    </div>

    <div class="order-details">
        <h2>Order #<?= $job['id'] ?> Details</h2>
        <p><strong>ID:</strong> <?= $job['id'] ?></p>
        <p><strong>Customer Name:</strong> <?= htmlspecialchars($job['customer_name']) ?></p>
        <p><strong>Job Name:</strong> <?= htmlspecialchars($job['job_name']) ?></p>
        <p><strong>Total Charges:</strong> ₹<?= number_format($job['total_charges'], 2) ?></p>
        <p><strong>File:</strong> 
            <?php if ($job['file_path']): ?>
                <button class="download-btn" onclick="location.href='delivered_view_order.php?id=<?= $job['id'] ?>&download=true'">Download All</button>
            <?php else: ?>
                No file
            <?php endif; ?>
        </p>
        <p><strong>Description:</strong> <?= htmlspecialchars($job['description'] ?? 'No description') ?></p>
        <button class="back-btn" onclick="location.href='delivered_orders.php'">Back to Delivered Orders</button>
    </div>
</body>
</html>