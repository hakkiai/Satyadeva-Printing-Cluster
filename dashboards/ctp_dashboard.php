<?php
include '../database/db_connect.php';
session_start();

// Handle multiple file uploads
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['ctp_files'])) {
    $job_id = $_POST['job_id'];
    $upload_dir = '../uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    if (!is_writable($upload_dir)) {
        echo "<script>alert('Uploads directory is not writable!');</script>";
        exit;
    }

    $files = $_FILES['ctp_files'];
    $uploaded_paths = [];
    $file_count = count($files['name']);

    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $file_name = $job_id . '_ctp_' . time() . '_' . basename($files['name'][$i]);
            $file_path = $upload_dir . $file_name;
            if (move_uploaded_file($files['tmp_name'][$i], $file_path)) {
                $uploaded_paths[] = $file_path;
            }
        }
    }
    if (!empty($uploaded_paths)) {
        $_SESSION['new_file_paths'][$job_id] = array_merge($_SESSION['new_file_paths'][$job_id] ?? [], $uploaded_paths);
        echo "<script>alert('Files uploaded successfully! " . count($uploaded_paths) . " file(s) added.'); window.location.href='ctp_dashboard.php';</script>";
    } else {
        echo "<script>alert('Failed to upload files!');</script>";
    }
    exit;
}

// Handle clear files action
if (isset($_GET['clear_id'])) {
    $job_id = $_GET['clear_id'];
    unset($_SESSION['new_file_paths'][$job_id]);
    echo "<script>alert('Uploaded files cleared!'); window.location.href='ctp_dashboard.php';</script>";
    exit;
}

// Handle completion action
if (isset($_GET['complete_id'])) {
    $job_id = $_GET['complete_id'];
    $new_file_paths = $_SESSION['new_file_paths'][$job_id] ?? [];
    if (!empty($new_file_paths)) {
        $file_paths_string = implode(',', $new_file_paths); // Only new files, no existing ones

        $sql = "SELECT multicolour FROM job_sheets WHERE id = ? AND ctp = 1 AND completed_ctp = 0";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $job_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $job = $result->fetch_assoc();

        if ($job) {
            $sql = "UPDATE job_sheets SET completed_ctp = 1, file_path = ? WHERE id = ? AND ctp = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $file_paths_string, $job_id);
            $stmt->execute();

            if ($job['multicolour'] == 1) {
                $sql = "UPDATE job_sheets SET completed_multicolour = 0 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $job_id);
                $stmt->execute();
                echo "<script>alert('Order completed in CTP and sent to Multicolour!'); window.location.href='ctp_dashboard.php';</script>";
            } else {
                $sql = "UPDATE job_sheets SET completed_delivery = 0 WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $job_id);
                $stmt->execute();
                echo "<script>alert('Order completed in CTP and sent to Delivery!'); window.location.href='ctp_dashboard.php';</script>";
            }
            unset($_SESSION['new_file_paths'][$job_id]);
        } else {
            echo "<script>alert('Invalid job or already completed!');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Please upload at least one file before completing!');</script>";
    }
}

// Handle ZIP download
// Handle ZIP download
if (isset($_GET['download_id'])) {
    $job_id = $_GET['download_id'];
    $sql = "SELECT file_path FROM job_sheets WHERE id = ? AND completed_ctp = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        ob_clean();
        ob_start();
        $file_paths = $row['file_path'] ? explode(',', $row['file_path']) : [];

        if (!empty($file_paths)) {
            $zip = new ZipArchive();
            $zip_name = "job_{$job_id}_files.zip";
            $zip_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zip_name;

            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                foreach ($file_paths as $file_path) {
                    $file_path = trim($file_path);
                    if (file_exists($file_path) && is_readable($file_path)) {
                        $file_name = basename($file_path);
                        $zip->addFile($file_path, $file_name);
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
            echo "<script>alert('No previously uploaded files found for this job!');</script>";
            exit;
        }
    } else {
        echo "<script>alert('No files found or job already completed!');</script>";
        exit;
    }
}

// Fetch active CTP orders, newest first
$sql = "SELECT * FROM job_sheets WHERE ctp = 1 AND completed_ctp = 0 ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CTP Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        table { width: 90%; margin: 20px auto; border-collapse: collapse; background-color: white; border-radius: 10px; box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background-color: #007bff; color: white; font-size: 16px; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        button, input[type="button"] { padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; margin: 0 5px; }
        .view-btn { background-color: #28a745; color: white; }
        .upload-btn { background-color: #17a2b8; color: white; }
        .complete-btn { background-color: #ffc107; color: black; }
        .download-btn { background-color: #17a2b8; color: white; }
        .clear-btn { background-color: #dc3545; color: white; }
        .complete-btn:disabled { background-color: #ccc; cursor: not-allowed; opacity: 0.6; }
        button:hover:not(:disabled), input[type="button"]:hover { opacity: 0.8; }
        input[type="file"] { display: none; }
        .file-list { margin-top: 5px; font-size: 12px; color: #555; }
    </style>
    <script>
        function triggerFileUpload(jobId) { document.getElementById('file_input_' + jobId).click(); }
    </script>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">CTP Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='ctp_dashboard.php'">Home</button>
            <button onclick="location.href='ctp_completed_orders.php'">Completed Orders</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="main-container">
        <div class="user-container">
            <h2>Welcome, CTP User!</h2>
            <h3>Active CTP Orders</h3>
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
                                    <?php 
                                    $uploaded_files = isset($_SESSION['new_file_paths'][$row['id']]) ? $_SESSION['new_file_paths'][$row['id']] : [];
                                    $db_files = $row['file_path'] ? explode(',', $row['file_path']) : [];
                                    if (!empty($db_files) || !empty($uploaded_files)): ?>
                                        <button class="download-btn" onclick="location.href='ctp_dashboard.php?download_id=<?= $row['id'] ?>'">Download All</button>
                                        <?php if (!empty($uploaded_files)): ?>
                                            <div class="file-list"><?= count($uploaded_files) ?> new file(s) uploaded</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        No file
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['description'] ?? 'No description') ?></td>
                                <td>
                                    <button class="view-btn" onclick="location.href='ctp_view_order.php?id=<?= $row['id'] ?>'">View</button>
                                    <form method="POST" enctype="multipart/form-data" style="display: inline;">
                                        <input type="hidden" name="job_id" value="<?= $row['id'] ?>">
                                        <input type="file" id="file_input_<?= $row['id'] ?>" name="ctp_files[]" multiple onchange="this.form.submit()" accept=".pdf,.jpg,.png">
                                        <input type="button" class="upload-btn" value="Upload" onclick="triggerFileUpload(<?= $row['id'] ?>)">
                                    </form>
                                    <button class="clear-btn" onclick="if(confirm('Clear all uploaded files for this job?')) location.href='ctp_dashboard.php?clear_id=<?= $row['id'] ?>'">Clear Files</button>
                                    <button class="complete-btn" onclick="if(confirm('Mark this order as completed?')) location.href='ctp_dashboard.php?complete_id=<?= $row['id'] ?>'" 
                                        <?php echo !empty($uploaded_files) ? '' : 'disabled'; ?>>Completed</button>
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