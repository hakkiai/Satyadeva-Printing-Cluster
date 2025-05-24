<?php
include '../database/db_connect.php';
session_start();

// Handle completion action
if (isset($_GET['complete_id'])) {
    $job_id = $_GET['complete_id'];
    $sql = "SELECT file_path, new_file_path FROM job_sheets WHERE id = ? AND digital = 1 AND completed_digital = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $job = $result->fetch_assoc();

    if ($job && ($job['file_path'] || $job['new_file_path'])) {
        // Combine all files into file_path
        $existing_files = $job['file_path'] ? explode(',', $job['file_path']) : [];
        $new_files = $job['new_file_path'] ? explode(',', $job['new_file_path']) : [];
        $all_files = array_merge($existing_files, $new_files);
        $file_paths_string = implode(',', $all_files);

        $sql = "UPDATE job_sheets SET completed_digital = 1, file_path = ?, new_file_path = NULL, is_reverted = 0, reverted_from = NULL, revert_reason = NULL WHERE id = ? AND digital = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $file_paths_string, $job_id);
        if ($stmt->execute()) {
            $sql = "UPDATE job_sheets SET completed_delivery = 0 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $job_id);
            $stmt->execute();
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

// Handle revert action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['revert_job_id'])) {
    $job_id = $_POST['revert_job_id'];
    $revert_reason = trim($_POST['revert_reason']);
    $new_file_paths = [];

    if (!empty($_FILES['new_files']['name'][0])) {
        $upload_dir = '../Uploads/';
        foreach ($_FILES['new_files']['name'] as $key => $name) {
            if ($_FILES['new_files']['error'][$key] === UPLOAD_ERR_OK) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $timestamp = date('Y-m-d H:i:s');
                $filename = "job_{$job_id}_revert_digital_" . time() . "_$key.$ext";
                $destination = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['new_files']['tmp_name'][$key], $destination)) {
                    $new_file_paths[] = "$destination|$timestamp";
                }
            }
        }
    }

    if (empty($revert_reason)) {
        echo "<script>alert('Please provide a reason for reversion!'); window.location.href='digital_dashboard.php';</script>";
        exit;
    }

    $new_file_path = !empty($new_file_paths) ? implode(',', $new_file_paths) : null;
    
    // Get current job state to determine revert workflow
    $sql = "SELECT multicolour, completed_multicolour, ctp, completed_ctp, new_file_path FROM job_sheets WHERE id = ? AND digital = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_job = $result->fetch_assoc();
    $stmt->close();
    
    // Append to existing new_file_path if it exists
    if ($current_job && !empty($current_job['new_file_path']) && !empty($new_file_path)) {
        $new_file_path = $current_job['new_file_path'] . ',' . $new_file_path;
    } elseif ($current_job && !empty($current_job['new_file_path'])) {
        $new_file_path = $current_job['new_file_path'];
    }

    // Determine where to revert to based on workflow
    if ($current_job && $current_job['multicolour'] && $current_job['completed_multicolour']) {
        // Revert to multicolour
        $sql = "UPDATE job_sheets SET is_reverted = 1, reverted_from = 'Digital', revert_reason = ?, new_file_path = ?, digital = 0, completed_digital = 0, multicolour = 1, completed_multicolour = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $revert_reason, $new_file_path, $job_id);
        $revert_destination = "Multicolour";
    } elseif ($current_job && $current_job['ctp'] && $current_job['completed_ctp'] && (!$current_job['multicolour'] || !$current_job['completed_multicolour'])) {
        // Revert to CTP if it came from CTP directly
        $sql = "UPDATE job_sheets SET is_reverted = 1, reverted_from = 'Digital', revert_reason = ?, new_file_path = ?, digital = 0, completed_digital = 0, ctp = 1, completed_ctp = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $revert_reason, $new_file_path, $job_id);
        $revert_destination = "CTP";
    } else {
        // Revert to Reception
        $sql = "UPDATE job_sheets SET is_reverted = 1, reverted_from = 'Digital', revert_reason = ?, new_file_path = ?, digital = 0, completed_digital = 0, status = 'Draft' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $revert_reason, $new_file_path, $job_id);
        $revert_destination = "Reception";
    }
    
    if ($stmt->execute()) {
        echo "<script>alert('Job reverted to $revert_destination Dashboard!'); window.location.href='digital_dashboard.php';</script>";
    } else {
        echo "<script>alert('Failed to revert job or job already completed!'); window.location.href='digital_dashboard.php';</script>";
    }
    $stmt->close();
    exit;
}

// Handle individual file download
if (isset($_GET['download_file']) && isset($_GET['job_id'])) {
    $job_id = $_GET['job_id'];
    $file_path = $_GET['download_file'];
    
    // Extract just the file path part if it contains a timestamp
    $file_parts = explode('|', $file_path);
    $real_path = $file_parts[0];
    
    $sql = "SELECT file_path, new_file_path FROM job_sheets WHERE id = ? AND completed_digital = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $valid_files = [];
    if ($row['file_path']) {
        foreach (explode(',', $row['file_path']) as $file) {
            $parts = explode('|', $file);
            $valid_files[] = $parts[0];
        }
    }
    if ($row['new_file_path']) {
        foreach (explode(',', $row['new_file_path']) as $file) {
            $parts = explode('|', $file);
            $valid_files[] = $parts[0];
        }
    }

    if (in_array($real_path, $valid_files)) {
        if (file_exists($real_path) && is_readable($real_path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($real_path) . '"');
            header('Content-Length: ' . filesize($real_path));
            readfile($real_path);
            exit;
        } else {
            echo "<script>alert('File not found or inaccessible!'); window.location.href='digital_dashboard.php';</script>";
        }
    } else {
        echo "<script>alert('Invalid file or job!'); window.location.href='digital_dashboard.php';</script>";
    }
}

// Handle ZIP download
if (isset($_GET['download_id'])) {
    $job_id = $_GET['download_id'];
    $sql = "SELECT file_path, new_file_path FROM job_sheets WHERE id = ? AND completed_digital = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $file_objects = [];
    if ($row['file_path']) {
        foreach (explode(',', $row['file_path']) as $file) {
            $parts = explode('|', $file);
            $path = $parts[0];
            $timestamp = isset($parts[1]) ? $parts[1] : '';
            if (file_exists($path) && is_readable($path)) {
                $file_objects[] = [
                    'path' => $path,
                    'timestamp' => $timestamp,
                    'type' => 'original'
                ];
            }
        }
    }
    if ($row['new_file_path']) {
        foreach (explode(',', $row['new_file_path']) as $file) {
            $parts = explode('|', $file);
            $path = $parts[0];
            $timestamp = isset($parts[1]) ? $parts[1] : '';
            if (file_exists($path) && is_readable($path)) {
                $file_objects[] = [
                    'path' => $path,
                    'timestamp' => $timestamp,
                    'type' => 'new'
                ];
            }
        }
    }

    if (!empty($file_objects)) {
        $zip = new ZipArchive();
        $zip_name = "job_{$job_id}_files.zip";
        $zip_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zip_name;

        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($file_objects as $file) {
                $file_name = basename($file['path']);
                $folder = "job_{$job_id}/" . $file['type'] . "_files/";
                $zip->addFile($file['path'], $folder . $file_name);
            }
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_name . '"');
            header('Content-Length: ' . filesize($zip_path));
            readfile($zip_path);
            unlink($zip_path);
            exit;
        } else {
            echo "<script>alert('Failed to create ZIP file!');</script>";
        }
    } else {
        echo "<script>alert('No files available for this job!');</script>";
    }
    exit;
}

// Fetch active Digital orders, newest first
$sql = "SELECT * FROM job_sheets WHERE digital = 1 AND completed_digital = 0 AND (completed_ctp = 1 OR ctp = 0) AND (completed_multicolour = 1 OR multicolour = 0) ORDER BY id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Digital Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            margin: 0 3px; 
            display: inline-flex; 
            align-items: center; 
            transition: transform 0.1s ease, box-shadow 0.3s ease; 
        }
        .view-btn { 
            background-color: #28a745; 
            color: white; 
        }
        .complete-btn { 
            background-color: #ffc107; 
            color: black; 
        }
        .download-btn { 
            background-color: #17a2b8; 
            color: white; 
        }
        .download-link { 
            color: #17a2b8; 
            text-decoration: none; 
            margin: 0 5px; 
            display: inline-block;
        }
        .download-link:hover { 
            text-decoration: underline; 
        }
        .complete-btn:disabled { 
            background-color: #ccc; 
            cursor: not-allowed; 
            opacity: 0.6; 
        }
        button:hover:not(:disabled) { 
            transform: scale(1.05); 
            box-shadow: 0 2px 5px rgba(0,0,0,0.2); 
        }
        .origin-ctp { 
            background-color: #e6f7ff; 
        }
        .origin-multicolour {
            background-color: #fff0f0;
        }
        .dropdown { 
            position: relative; 
            display: inline-block; 
        }
        .dropdown-btn { 
            padding: 8px 12px; 
            border: none; 
            border-radius: 5px; 
            background-color: #6c757d; 
            color: white; 
            font-weight: bold; 
            cursor: pointer; 
            transition: background-color 0.3s ease, transform 0.1s ease, box-shadow 0.3s ease; 
            display: inline-flex; 
            align-items: center; 
        }
        .dropdown-btn:hover { 
            background-color: #5a6268; 
            transform: scale(1.05); 
            box-shadow: 0 2px 5px rgba(0,0,0,0.2); 
        }
        .dropdown-btn i { 
            margin-right: 5px; 
        }
        .dropdown-content { 
            display: none; 
            position: absolute; 
            background-color: #fff; 
            min-width: 160px; 
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2); 
            border-radius: 5px; 
            z-index: 1; 
            top: 100%; 
            right: 0; 
        }
        .dropdown-content a { 
            color: #333; 
            padding: 12px 16px; 
            text-decoration: none; 
            display: block; 
            font-size: 14px; 
            transition: background-color 0.3s ease; 
        }
        .dropdown-content a:hover { 
            background-color: #f1f1f1; 
        }
        .dropdown-content a i { 
            margin-right: 8px; 
        }
        .dropdown:hover .dropdown-content { 
            display: block; 
        }
        .dropdown-content a.revert { 
            color: #6c757d; 
        }
        button i { 
            margin-right: 5px; 
        }
        .revert-form { 
            display: none; 
            position: fixed; 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%); 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.3); 
            z-index: 1000; 
        }
        .revert-form textarea { 
            width: 300px; 
            height: 100px; 
            margin: 10px 0; 
        }
        .revert-form input[type="file"] { 
            margin: 10px 0; 
            display: block;
        }
        .overlay { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            z-index: 999; 
        }
        .file-label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }
        .file-info {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 6px 8px;
            margin: 3px 0;
            font-size: 12px;
            color: #666;
        }
        .file-info.original {
            border-left: 3px solid #28a745;
        }
        .file-info.new {
            border-left: 3px solid #dc3545;
        }
        .origin-badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 5px;
        }
        .origin-badge.ctp {
            background-color: #e6f7ff;
            color: #0056b3;
        }
        .origin-badge.multicolour {
            background-color: #fff0f0;
            color: #dc3545;
        }
    </style>
    <script>
        function showRevertForm(jobId) {
            document.getElementById('revert_form_' + jobId).style.display = 'block';
            document.getElementById('overlay').style.display = 'block';
        }
        function hideRevertForm(jobId) {
            document.getElementById('revert_form_' + jobId).style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        }
    </script>
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
            <div id="overlay" class="overlay"></div>
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
                        <?php while ($row = $result->fetch_assoc()): 
                            $row_class = '';
                            $origin_badge = '';
                            
                            if ($row['ctp'] == 1 && $row['completed_ctp'] == 1 && $row['multicolour'] == 0) {
                                $row_class = 'origin-ctp';
                                $origin_badge = '<span class="origin-badge ctp">From CTP</span>';
                            } else if ($row['multicolour'] == 1 && $row['completed_multicolour'] == 1) {
                                $row_class = 'origin-multicolour';
                                $origin_badge = '<span class="origin-badge multicolour">From Multicolour</span>';
                            }
                        ?>
                            <tr class="<?= $row_class ?>">
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['job_name']) ?></td>
                                <td>₹<?= number_format($row['total_charges'], 2) ?></td>
                                <td>
                                    <?php 
                                    $original_files = [];
                                    $new_files = [];
                                    
                                    // Process original files
                                    if ($row['file_path']) {
                                        foreach (explode(',', $row['file_path']) as $file) {
                                            $parts = explode('|', $file);
                                            $path = $parts[0];
                                            $timestamp = isset($parts[1]) ? $parts[1] : '';
                                            $original_files[] = [
                                                'path' => $path,
                                                'timestamp' => $timestamp
                                            ];
                                        }
                                    }
                                    
                                    // Process new files
                                    if ($row['new_file_path']) {
                                        foreach (explode(',', $row['new_file_path']) as $file) {
                                            $parts = explode('|', $file);
                                            $path = $parts[0];
                                            $timestamp = isset($parts[1]) ? $parts[1] : '';
                                            $new_files[] = [
                                                'path' => $path,
                                                'timestamp' => $timestamp
                                            ];
                                        }
                                    }
                                    
                                    if (!empty($original_files) || !empty($new_files)): 
                                    ?>
                                        <?php if (!empty($original_files)): ?>
                                            <div class="file-label">Original Files:</div>
                                            <?php foreach ($original_files as $index => $file): ?>
                                                <div class="file-info original">
                                                    <a href="digital_dashboard.php?download_file=<?= urlencode($file['path'] . '|' . $file['timestamp']) ?>&job_id=<?= $row['id'] ?>" class="download-link">
                                                        <?= basename($file['path']) ?>
                                                    </a>
                                                    <?php if (!empty($file['timestamp'])): ?>
                                                        <span class="timestamp">(<?= $file['timestamp'] ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($new_files)): ?>
                                            <div class="file-label">New Files:</div>
                                            <?php foreach ($new_files as $index => $file): ?>
                                                <div class="file-info new">
                                                    <a href="digital_dashboard.php?download_file=<?= urlencode($file['path'] . '|' . $file['timestamp']) ?>&job_id=<?= $row['id'] ?>" class="download-link">
                                                        <?= basename($file['path']) ?>
                                                    </a>
                                                    <?php if (!empty($file['timestamp'])): ?>
                                                        <span class="timestamp">(<?= $file['timestamp'] ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        
                                        <button class="download-btn" onclick="location.href='digital_dashboard.php?download_id=<?= $row['id'] ?>'">
                                            <i class="fas fa-download"></i> Download All
                                        </button>
                                    <?php else: ?>
                                        No file
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['description'] ?? 'No description') ?>
                                    <?= $origin_badge ?>
                                </td>
                                <td>
                                    <button class="view-btn" onclick="location.href='digital_view_order.php?id=<?= $row['id'] ?>'">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="complete-btn" onclick="if(confirm('Mark this order as completed?')) location.href='digital_dashboard.php?complete_id=<?= $row['id'] ?>'" 
                                        <?php echo ($row['file_path'] || $row['new_file_path']) ? '' : 'disabled'; ?>>
                                        <i class="fas fa-check"></i> Complete
                                    </button>
                                    <div class="dropdown">
                                        <button class="dropdown-btn"><i class="fas fa-ellipsis-v"></i> More</button>
                                        <div class="dropdown-content">
                                            <a href="#" class="revert" onclick="showRevertForm(<?= $row['id'] ?>)">
                                                <i class="fas fa-undo"></i> Revert
                                            </a>
                                        </div>
                                    </div>
                                    <div id="revert_form_<?= $row['id'] ?>" class="revert-form">
                                        <form method="POST" enctype="multipart/form-data">
                                            <h3>Revert Job #<?= $row['id'] ?></h3>
                                            <label for="revert_reason_<?= $row['id'] ?>">Reason for Reversion:</label>
                                            <textarea id="revert_reason_<?= $row['id'] ?>" name="revert_reason" placeholder="Enter reason for reversion" required></textarea>
                                            <label for="new_files_<?= $row['id'] ?>">Upload New Files (Optional):</label>
                                            <input type="file" id="new_files_<?= $row['id'] ?>" name="new_files[]" multiple accept=".pdf,.jpg,.png">
                                            <input type="hidden" name="revert_job_id" value="<?= $row['id'] ?>">
                                            <button type="submit">Submit Revert</button>
                                            <button type="button" onclick="hideRevertForm(<?= $row['id'] ?>)">Cancel</button>
                                        </form>
                                    </div>
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