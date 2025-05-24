<?php
// session_start();
// if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'reception') {
//     error_log("User not logged in or not reception, redirecting to login");
//     header("Location: ../auth/login.php");
//     exit;
// }

include '../database/db_connect.php';

// Check schema for optional columns
$optional_columns = ['is_reverted', 'reverted_from', 'revert_reason', 'new_file_path'];
$existing_columns = [];
$result = $conn->query("SHOW COLUMNS FROM job_sheets");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
} else {
    error_log("Failed to check job_sheets columns: " . $conn->error);
    echo "<script>alert('Database schema error: " . addslashes($conn->error) . "');</script>";
    exit;
}

// Get selected status (default: 'Draft')
$status = isset($_GET['status']) ? $_GET['status'] : 'Draft';
$customer_name = isset($_GET['customer_name']) ? $_GET['customer_name'] : '';

// Build SQL query for main table
$sql = "SELECT id, customer_name, phone_number, job_name, total_charges, status, is_reverted, file_path";
if (in_array('reverted_from', $existing_columns)) $sql .= ", reverted_from";
if (in_array('revert_reason', $existing_columns)) $sql .= ", revert_reason";
if (in_array('new_file_path', $existing_columns)) $sql .= ", new_file_path";
$sql .= " FROM job_sheets WHERE status=?";
if (!empty($customer_name)) {
    $sql .= " AND customer_name LIKE ?";
}
$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo "<script>alert('Database error: " . addslashes($conn->error) . "');</script>";
    exit;
}
if (!empty($customer_name)) {
    $param = "%$customer_name%";
    $stmt->bind_param("ss", $status, $param);
} else {
    $stmt->bind_param("s", $status);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch reverted jobs for modal
$reverted_sql = "SELECT id, customer_name, job_name, total_charges";
if (in_array('reverted_from', $existing_columns)) $reverted_sql .= ", reverted_from";
if (in_array('revert_reason', $existing_columns)) $reverted_sql .= ", revert_reason";
$reverted_sql .= " FROM job_sheets WHERE ";
$conditions = [];
if (in_array('is_reverted', $existing_columns)) $conditions[] = "is_reverted=1";
if (in_array('reverted_from', $existing_columns)) $conditions[] = "reverted_from IS NOT NULL";
if (in_array('revert_reason', $existing_columns)) $conditions[] = "revert_reason IS NOT NULL";
$reverted_sql .= !empty($conditions) ? implode(" OR ", $conditions) : "1=0";
$reverted_sql .= " ORDER BY id DESC";

$reverted_stmt = $conn->prepare($reverted_sql);
if (!$reverted_stmt) {
    error_log("Prepare failed for reverted jobs: " . $conn->error);
    echo "<script>alert('Database error: " . addslashes($conn->error) . "');</script>";
    exit;
}
$reverted_stmt->execute();
$reverted_result = $reverted_stmt->get_result();

// Handle single job download
if (isset($_POST['download_job']) && isset($_POST['job_id'])) {
    $job_id = filter_var($_POST['job_id'], FILTER_VALIDATE_INT);
    if ($job_id === false) {
        error_log("Invalid job ID: {$_POST['job_id']}");
        echo "<script>alert('Invalid job ID!');</script>";
    } else {
        $job_sql = "SELECT file_path";
        if (in_array('new_file_path', $existing_columns)) $job_sql .= ", new_file_path";
        $job_sql .= " FROM job_sheets WHERE id=?";
        $job_stmt = $conn->prepare($job_sql);
        if (!$job_stmt) {
            error_log("Prepare failed for job download: " . $conn->error);
            echo "<script>alert('Database error: " . addslashes($conn->error) . "');</script>";
        } else {
            $job_stmt->bind_param("i", $job_id);
            $job_stmt->execute();
            $job_result = $job_stmt->get_result();
            $job = $job_result->fetch_assoc();

            error_log("Job $job_id: file_path=" . ($job['file_path'] ?? 'null') . ", new_file_path=" . ($job['new_file_path'] ?? 'null'));

            if ($job) {
                if (!class_exists('ZipArchive')) {
                    error_log("ZipArchive not enabled");
                    echo "<script>alert('ZipArchive extension is not enabled on the server!');</script>";
                } else {
                    $zip = new ZipArchive();
                    $zip_name = "job_{$job_id}_files_" . time() . ".zip";
                    $temp_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zip_name;

                    if ($zip->open($temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                        $files_added = 0;
                        // Original files
                        if (!empty($job['file_path'])) {
                            $files = array_filter(explode(',', $job['file_path']));
                            foreach ($files as $file) {
                                list($path, $timestamp) = explode('|', $file, 2) + [null, null];
                                error_log("Checking original file: $path, exists: " . (file_exists($path) ? 'yes' : 'no'));
                                if ($path && file_exists($path)) {
                                    $basename = basename($path);
                                    $zip->addFile($path, "job_{$job_id}/original_files/{$basename}");
                                    $files_added++;
                                    error_log("Added original file: $path ($timestamp)");
                                }
                            }
                        }
                        // New files
                        if (!empty($job['new_file_path']) && in_array('new_file_path', $existing_columns)) {
                            $new_files = array_filter(explode(',', $job['new_file_path']));
                            foreach ($new_files as $file) {
                                list($path, $timestamp) = explode('|', $file, 2) + [null, null];
                                error_log("Checking new file: $path, exists: " . (file_exists($path) ? 'yes' : 'no'));
                                if ($path && file_exists($path)) {
                                    $basename = basename($path);
                                    $zip->addFile($path, "job_{$job_id}/new_files/{$basename}");
                                    $files_added++;
                                    error_log("Added new file: $path ($timestamp)");
                                }
                            }
                        }
                        $zip->close();

                        if ($files_added > 0) {
                            header('Content-Type: application/zip');
                            header('Content-Disposition: attachment; filename="' . $zip_name . '"');
                            header('Content-Length: ' . filesize($temp_file));
                            readfile($temp_file);
                            unlink($temp_file);
                            exit;
                        } else {
                            error_log("No files added to ZIP for job $job_id");
                            echo "<script>alert('No downloadable files found for Job #$job_id!');</script>";
                        }
                    } else {
                        error_log("Failed to create ZIP: $temp_file");
                        echo "<script>alert('Failed to create ZIP file!');</script>";
                    }
                }
            } else {
                error_log("Job $job_id not found");
                echo "<script>alert('Job not found!');</script>";
            }
            $job_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Sheets</title>
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
        button:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .search-btn {
            margin-top: 10px;
            width: 100px;
            margin-left: 350px;
        }
        .view-btn { background-color: #28a745; color: white; }
        .edit-btn { background-color: #ffc107; color: black; }
        .finalize-btn { background-color: #007bff; color: white; }
        .download-btn { background-color: #17a2b8; color: white; }
        .download-main-btn { background-color: #dc3545; color: white; margin: 10px auto; display: block; }
        button i { margin-right: 5px; }
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
        .dropdown-content a.print1 { 
            color: #dc3545; 
        }
        .dropdown-content a.print2 { 
            color: #17a2b8; 
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 800px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .modal-content h2 {
            margin-top: 0;
            color: #333;
        }
        .modal-content table {
            width: 100%;
            margin: 0;
        }
        .modal-content button {
            margin: 5px;
        }
        .close-btn {
            float: right;
            font-size: 24px;
            cursor: pointer;
            color: #333;
        }
        .file-info {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 6px 8px;
            margin: 3px 0;
            font-size: 12px;
            color: #666;
            border-left: 3px solid #007bff;
        }
        .file-info.original {
            border-left-color: #28a745;
        }
        .file-info.new {
            border-left-color: #dc3545;
        }
        .file-label {
            font-weight: bold;
            margin-top: 8px;
            margin-bottom: 4px;
            font-size: 14px;
        }
        .revert-badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            background-color: #ffecb5;
            color: #856404;
            margin-left: 5px;
        }
    </style>
    <script>
        function filterStatus() {
            const status = document.getElementById('status').value;
            const customer_name = document.getElementById('customer_name').value;
            window.location.href = `view_order.php?status=${encodeURIComponent(status)}&customer_name=${encodeURIComponent(customer_name)}`;
        }
        function openModal() {
            document.getElementById('revertedJobsModal').style.display = 'block';
        }
        function closeModal() {
            document.getElementById('revertedJobsModal').style.display = 'none';
        }
        window.onclick = function(event) {
            const modal = document.getElementById('revertedJobsModal');
            if (event.target === modal) {
                closeModal();
            }
        };
        document.onkeydown = function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        };
    </script>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Reception Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='New_Order.php'">New Order</button>
            <button onclick="location.href='view_order.php'">View Order</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>
    
    <div class="container">
        <h2>Job Sheets</h2>
        <div class="form-group">
            <label for="status">Filter by Status:</label>
            <select id="status" onchange="filterStatus()">
                <option value="Draft" <?= $status == 'Draft' ? 'selected' : '' ?>>Draft</option>
                <option value="Finalized" <?= $status == 'Finalized' ? 'selected' : '' ?>>Finalized</option>
            </select>
            <input type="text" id="customer_name" placeholder="Search by Customer" value="<?= htmlspecialchars($customer_name) ?>">
            <button onclick="filterStatus()" class="search-btn">Search</button>
        </div>
        <button type="button" class="download-main-btn" onclick="openModal()"><i class="fas fa-download"></i> View Reverted Jobs</button>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer Name</th>
                <th>Phone Number</th>
                <th>Job Name</th>
                <th>Total Charges</th>
                <th>Status</th>
                <th>Files</th>
                <th>Revert Details</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                    <td><?= htmlspecialchars($row['phone_number']) ?></td>
                    <td><?= htmlspecialchars($row['job_name']) ?></td>
                    <td>₹<?= number_format($row['total_charges'], 2) ?></td>
                    <td>
                        <?= htmlspecialchars($row['status']) ?>
                        <?php if (isset($row['is_reverted']) && $row['is_reverted'] == 1): ?>
                            <span class="revert-badge">Reverted</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($row['is_reverted']) && $row['is_reverted'] == 1 && (!empty($row['file_path']) || !empty($row['new_file_path']))): ?>
                            <?php
                            $original_files = [];
                            $new_files = [];
                            
                            // Process original files
                            if (!empty($row['file_path'])) {
                                foreach (explode(',', $row['file_path']) as $file) {
                                    $parts = explode('|', $file);
                                    $path = $parts[0];
                                    $timestamp = isset($parts[1]) ? $parts[1] : '';
                                    error_log("Checking table original file: $path, exists: " . (file_exists($path) ? 'yes' : 'no'));
                                    if (file_exists($path)) {
                                        $original_files[] = [
                                            'path' => $path,
                                            'timestamp' => $timestamp
                                        ];
                                    }
                                }
                            }
                            
                            // Process new files
                            if (!empty($row['new_file_path']) && in_array('new_file_path', $existing_columns)) {
                                foreach (explode(',', $row['new_file_path']) as $file) {
                                    $parts = explode('|', $file);
                                    $path = $parts[0];
                                    $timestamp = isset($parts[1]) ? $parts[1] : '';
                                    error_log("Checking table new file: $path, exists: " . (file_exists($path) ? 'yes' : 'no'));
                                    if (file_exists($path)) {
                                        $new_files[] = [
                                            'path' => $path,
                                            'timestamp' => $timestamp
                                        ];
                                    }
                                }
                            }
                            ?>
                            
                            <?php if (!empty($original_files)): ?>
                                <div class="file-label">Original Files:</div>
                                <?php foreach ($original_files as $file): ?>
                                    <div class="file-info original">
                                        <a href="<?= htmlspecialchars($file['path']) ?>" download>
                                            <?= htmlspecialchars(basename($file['path'])) ?>
                                        </a>
                                        <?php if (!empty($file['timestamp'])): ?>
                                            <span>(<?= htmlspecialchars($file['timestamp']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <?php if (!empty($new_files)): ?>
                                <div class="file-label">New Files:</div>
                                <?php foreach ($new_files as $file): ?>
                                    <div class="file-info new">
                                        <a href="<?= htmlspecialchars($file['path']) ?>" download>
                                            <?= htmlspecialchars(basename($file['path'])) ?>
                                        </a>
                                        <?php if (!empty($file['timestamp'])): ?>
                                            <span>(<?= htmlspecialchars($file['timestamp']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (isset($row['is_reverted']) && $row['is_reverted'] == 1): ?>
                            <strong>Reverted from:</strong> <?= htmlspecialchars($row['reverted_from'] ?? 'Unknown') ?><br>
                            <strong>Reason:</strong> <?= htmlspecialchars($row['revert_reason'] ?? 'No reason provided') ?>
                        <?php else: ?>
                            Not reverted
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="view-btn" onclick="location.href='view_job_details.php?id=<?= $row['id'] ?>'">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="edit-btn" onclick="location.href='New_Order.php?mode=edit&id=<?= $row['id'] ?>'">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="finalize-btn" onclick="location.href='finalize_order.php?id=<?= $row['id'] ?>'">
                            <i class="fas fa-check"></i> Finalize
                        </button>
                        <div class="dropdown">
                            <button class="dropdown-btn">
                                <i class="fas fa-print"></i> Print
                            </button>
                            <div class="dropdown-content">
                                <a href="print_job_sheet.php?id=<?= $row['id'] ?>&type=1" class="print1">
                                    <i class="fas fa-print"></i> Print 1
                                </a>
                                <a href="print_job_sheet.php?id=<?= $row['id'] ?>&type=2" class="print2">
                                    <i class="fas fa-print"></i> Print 2
                                </a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Modal for Reverted Jobs -->
    <div id="revertedJobsModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">×</span>
            <h2>Reverted Job Sheets</h2>
            <?php if ($reverted_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer Name</th>
                            <th>Job Name</th>
                            <th>Total Charges</th>
                            <th>Reverted From</th>
                            <th>Revert Reason</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($job = $reverted_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($job['id']) ?></td>
                                <td><?= htmlspecialchars($job['customer_name']) ?></td>
                                <td><?= htmlspecialchars($job['job_name']) ?></td>
                                <td>₹<?= number_format($job['total_charges'], 2) ?></td>
                                <td><?= htmlspecialchars($job['reverted_from'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($job['revert_reason'] ?? 'No reason provided') ?></td>
                                <td>
                                    <button class="view-btn" onclick="location.href='view_job_details.php?id=<?= $job['id'] ?>'">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="finalize-btn" onclick="location.href='finalize_order.php?id=<?= $job['id'] ?>'">
                                        <i class="fas fa-check"></i> Re-Finalize
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                        <button type="submit" name="download_job" class="download-btn">
                                            <i class="fas fa-download"></i> Download Files
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No reverted job sheets found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
$stmt->close();
$reverted_stmt->close();
$conn->close();
?>c