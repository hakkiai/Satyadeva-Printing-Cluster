<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../database/db_connect.php';

// Session check (commented out to match your setup; uncomment if needed)
// session_start();
// if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'reception') {
//     error_log("User not logged in or not reception, redirecting to login");
//     header("Location: ../auth/login.php");
//     exit;
// }

if (!isset($_GET['id'])) {
    error_log("No job ID provided, redirecting to view_order.php");
    header("Location: view_order.php");
    exit;
}

$job_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($job_id === false) {
    error_log("Invalid job ID: {$_GET['id']}");
    echo "<script>alert('Invalid job ID!'); window.location.href='view_order.php';</script>";
    exit;
}

// Check schema for required and optional columns
$required_columns = ['id', 'customer_name', 'job_name', 'total_charges', 'file_path', 'description', 'ctp', 'multicolour', 'digital', 'completed_ctp', 'completed_multicolour', 'completed_digital', 'completed_delivery', 'status'];
$optional_columns = ['is_reverted', 'new_file_path', 'reverted_from', 'revert_reason'];
$columns_to_fetch = $required_columns;
$existing_columns = [];
$result = $conn->query("SHOW COLUMNS FROM job_sheets");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
} else {
    error_log("Failed to check job_sheets columns: " . $conn->error);
    echo "<script>alert('Database schema error: " . addslashes($conn->error) . "'); window.location.href='view_order.php';</script>";
    exit;
}
$missing_required = array_diff($required_columns, $existing_columns);
if (!empty($missing_required)) {
    error_log("Missing required columns in job_sheets: " . implode(', ', $missing_required));
    echo "<script>alert('Database schema error: Missing columns " . implode(', ', $missing_required) . "'); window.location.href='view_order.php';</script>";
    exit;
}

// Fetch job details
$sql = "SELECT " . implode(', ', $columns_to_fetch) . " FROM job_sheets WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo "<script>alert('Database error: " . addslashes($conn->error) . "'); window.location.href='view_order.php';</script>";
    exit;
}
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    error_log("Job not found: $job_id");
    echo "<script>alert('Job not found!'); window.location.href='view_order.php';</script>";
    exit;
}

// Function to fetch customer's total balance and balance limit
function get_customer_balance_data($conn, $customer_name) {
    $sql = "SELECT total_balance, balance_limit FROM customers WHERE customer_name = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed for customer balance: " . $conn->error);
        return ['total_balance' => 0, 'balance_limit' => 0];
    }
    $stmt->bind_param("s", $customer_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    if (!$row) {
        error_log("Customer not found: $customer_name");
        return ['total_balance' => 0, 'balance_limit' => 0];
    }
    return [
        'total_balance' => $row['total_balance'] !== null ? floatval($row['total_balance']) : 0,
        'balance_limit' => $row['balance_limit'] !== null ? floatval($row['balance_limit']) : 0
    ];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ctp = isset($_POST['ctp']) ? 1 : 0;
    $multicolour = isset($_POST['multicolour']) ? 1 : 0;
    $digital = isset($_POST['digital']) ? 1 : 0;
    $description = trim($_POST['description']);

    // Validate at least one stage
    if (!$ctp && !$multicolour && !$digital) {
        error_log("No stages selected for job $job_id");
        echo "<script>alert('Please select at least one department (CTP, Multicolour, or Digital)!'); window.location.href='finalize_order.php?id=$job_id';</script>";
        exit;
    }

    // Fetch customer balance data
    $customer_name = $job['customer_name'];
    $balance_data = get_customer_balance_data($conn, $customer_name);
    $total_balance = $balance_data['total_balance'];
    $balance_limit = $balance_data['balance_limit'];
    $new_charges = floatval($job['total_charges']);
    $total_potential_balance = $total_balance + $new_charges;

    if ($balance_limit > 0 && $total_balance >= $balance_limit) {
        error_log("Customer $customer_name exceeded balance limit");
        echo "<script>alert('Customer $customer_name has already reached or exceeded their balance limit (Current Balance: ₹" . number_format($total_balance, 2) . " >= Limit: ₹" . number_format($balance_limit, 2) . "). No new job sheets can be finalized until the balance is cleared.'); window.location.href='new_order.php?mode=edit&id=$job_id';</script>";
        $conn->close();
        exit;
    }

    // Check if files are uploaded
    if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
        $upload_dir = '../Uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        if (!is_writable($upload_dir)) {
            error_log("Uploads directory not writable: $upload_dir");
            echo "<script>alert('Uploads directory is not writable!'); window.location.href='finalize_order.php?id=$job_id';</script>";
            exit;
        }

        $files = $_FILES['files'];
        $file_paths = [];
        $file_count = count($files['name']);
        $upload_success = false;

        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] == UPLOAD_ERR_OK) {
                $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                $timestamp = date('Y-m-d H:i:s');
                $file_name = "job_{$job_id}_" . time() . "_$i.$ext";
                $file_path = $upload_dir . $file_name;
                if (move_uploaded_file($files['tmp_name'][$i], $file_path)) {
                    $file_paths[] = "$file_path|$timestamp";
                    $upload_success = true;
                } else {
                    error_log("Failed to move file: $file_path");
                }
            }
        }

        if ($upload_success && !empty($file_paths)) {
            $file_paths_string = implode(',', $file_paths);

            try {
                if (in_array('is_reverted', $existing_columns) && in_array('new_file_path', $existing_columns) && isset($job['is_reverted']) && $job['is_reverted'] == 1) {
                    // Reverted job: store original files in file_path, new files in new_file_path
                    $existing_files = !empty($job['file_path']) ? $job['file_path'] : '';
                    $new_files = $file_paths_string;
                    $update_sql = "UPDATE job_sheets SET 
                        ctp = ?, multicolour = ?, digital = ?, description = ?, file_path = ?, new_file_path = ?,
                        status = 'Draft', completed_ctp = 0, completed_multicolour = 0, completed_digital = 0, 
                        completed_delivery = 0, is_reverted = 0
                        WHERE id = ?";
                    $stmt = $conn->prepare($update_sql);
                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param("iiisssi", $ctp, $multicolour, $digital, $description, $existing_files, $new_files, $job_id);
                } else {
                    // Non-reverted job: store in file_path
                    $update_sql = "UPDATE job_sheets SET 
                        ctp = ?, multicolour = ?, digital = ?, description = ?, file_path = ?,
                        status = 'Draft', completed_ctp = 0, completed_multicolour = 0, completed_digital = 0, 
                        completed_delivery = 0" . 
                        (in_array('is_reverted', $existing_columns) ? ", is_reverted = 0" : "") . 
                        (in_array('new_file_path', $existing_columns) ? ", new_file_path = NULL" : "") . 
                        " WHERE id = ?";
                    $stmt = $conn->prepare($update_sql);
                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param("iiissi", $ctp, $multicolour, $digital, $description, $file_paths_string, $job_id);
                }

                if ($stmt->execute()) {
                    $update_balance_sql = "UPDATE customers SET total_balance = total_balance + ? WHERE customer_name = ?";
                    $balance_stmt = $conn->prepare($update_balance_sql);
                    if (!$balance_stmt) {
                        throw new Exception("Prepare failed for balance update: " . $conn->error);
                    }
                    $balance_stmt->bind_param("ds", $new_charges, $customer_name);
                    $balance_stmt->execute();
                    $balance_stmt->close();

                    // Redirect to view_order.php
                    echo "<script>alert('Order finalized successfully with " . count($file_paths) . " file(s)!'); window.location.href='view_order.php';</script>";
                    exit; // Ensure no further execution
                } else {
                    throw new Exception("Error updating job $job_id: " . $stmt->error);
                }
            } catch (Exception $e) {
                error_log("Error finalizing job $job_id: " . $e->getMessage());
                echo "<script>alert('Error finalizing job: " . addslashes($e->getMessage()) . "'); window.location.href='finalize_order.php?id=$job_id';</script>";
                exit;
            }
            $stmt->close();
        } else {
            error_log("File upload failed for job $job_id");
            echo "<script>alert('File upload failed or no valid files uploaded!'); window.location.href='finalize_order.php?id=$job_id';</script>";
            exit;
        }
    } else {
        error_log("No files uploaded for job $job_id");
        echo "<script>alert('Please upload at least one valid file!'); window.location.href='finalize_order.php?id=$job_id';</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalize Order</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: #f5f7fa; font-family: 'Arial', sans-serif; margin: 0; padding: 0; }
        .navbar { background: #007bff; padding: 15px 20px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar .brand { color: white; font-size: 22px; font-weight: bold; margin: 0; }
        .nav-buttons button { background: white; color: #007bff; border: none; padding: 8px 16px; border-radius: 20px; font-weight: bold; cursor: pointer; transition: background 0.3s ease, color 0.3s ease; }
        .nav-buttons button:hover { background: #0056b3; color: white; }
        .customer-container { max-width: 700px; margin: 30px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1); }
        .customer-container h2 { text-align: center; color: #333; font-size: 24px; margin-bottom: 20px; font-weight: bold; }
        .finalize-form { display: flex; flex-direction: column; align-items: center; gap: 15px; }
        .form-group.checkbox-group { display: flex; align-items: center; }
        .form-group.checkbox-group label { display: flex; align-items: center; font-size: 16px; color: #333; padding: 8px 12px; background: #eef4ff; border-radius: 6px; cursor: pointer; transition: background 0.3s ease; }
        .form-group.checkbox-group label:hover { background: #d9e6ff; }
        .form-group.checkbox-group input[type="checkbox"] { appearance: none; width: 18px; height: 18px; border: 2px solid #007bff; border-radius: 4px; margin-right: 8px; position: relative; cursor: pointer; }
        .form-group.checkbox-group input[type="checkbox"]:checked { background: #007bff; }
        .form-group.checkbox-group input[type="checkbox"]:checked::after { content: '\2713'; color: white; font-size: 12px; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); }
        .form-group.file-group { width: 100%; max-width: 350px; display: flex; flex-direction: column; align-items: center; }
        .form-group.file-group label { font-weight: bold; color: #333; font-size: 16px; margin-bottom: 8px; }
        .form-group.file-group input[type="file"] { width: 100%; padding: 10px; font-size: 15px; border: 2px dashed #007bff; border-radius: 8px; background: #fafcff; cursor: pointer; transition: border-color 0.3s ease; }
        .form-group.file-group input[type="file"]:hover { border-color: #0056b3; }
        .form-group.file-group input[type="file"]::-webkit-file-upload-button { background: #007bff; color: white; padding: 6px 12px; border: none; border-radius: 20px; cursor: pointer; font-size: 14px; transition: background 0.3s ease; }
        .form-group.file-group input[type="file"]::-webkit-file-upload-button:hover { background: #0056b3; }
        .form-group.textarea-group { width: 100%; max-width: 500px; }
        .form-group.textarea-group label { font-weight: bold; color: #333; font-size: 16px; margin-bottom: 8px; display: block; }
        .form-group.textarea-group textarea { width: 100%; height: 90px; padding: 10px; font-size: 15px; border: 2px solid #ddd; border-radius: 8px; background: #fff; resize: vertical; transition: border-color 0.3s ease; }
        .form-group.textarea-group textarea:focus { border-color: #007bff; outline: none; }
        .finalize-form button[type="submit"] { width: 100%; max-width: 180px; padding: 12px; font-size: 16px; font-weight: bold; background: #28a745; color: white; border: none; border-radius: 20px; cursor: pointer; transition: background 0.3s ease; }
        .finalize-form button[type="submit"]:hover { background: #218838; }
        .add-more-btn { padding: 6px 12px; background: #007bff; color: white; border: none; border-radius: 20px; cursor: pointer; font-size: 14px; margin-top: 8px; transition: background 0.3s ease; }
        .add-more-btn:hover { background: #0056b3; }
        .file-input-container { margin: 8px 0; width: 100%; }
        .workflow-info { 
            max-width: 500px; margin: 20px auto; background-color: #f0f8ff; border: 1px solid #b8daff;
            border-radius: 8px; padding: 15px; font-size: 14px;
        }
        .workflow-item {
            margin-bottom: 8px; padding-left: 20px; position: relative;
        }
        .workflow-item::before {
            content: "•"; position: absolute; left: 5px; color: #007bff;
        }
    </style>
    <script>
        function addMoreFiles() {
            const container = document.getElementById('file-inputs');
            const newInputDiv = document.createElement('div');
            newInputDiv.className = 'file-input-container';
            const newInput = document.createElement('input');
            newInput.type = 'file';
            newInput.name = 'files[]';
            newInput.multiple = true;
            newInput.accept = '.pdf,.jpg,.png';
            newInput.className = 'file-input';
            newInput.style.cssText = 'width: 100%; padding: 10px; font-size: 15px; border: 2px dashed #007bff; border-radius: 8px; background: #fafcff;';
            newInputDiv.appendChild(newInput);
            container.appendChild(newInputDiv);
        }
        
        function updateWorkflowInfo() {
            const ctp = document.getElementById('ctp').checked;
            const multicolour = document.getElementById('multicolour').checked;
            const digital = document.getElementById('digital').checked;
            let workflowText = "";
            
            if (ctp && multicolour && digital) {
                workflowText = "Files will go to CTP first, then to Multicolour, and finally to Digital.";
            } else if (ctp && multicolour) {
                workflowText = "Files will go to CTP first, then to Multicolour.";
            } else if (ctp && digital) {
                workflowText = "Files will go to CTP first, then to Digital.";
            } else if (multicolour && digital) {
                workflowText = "Files will go to Multicolour first, then to Digital.";
            } else if (ctp) {
                workflowText = "Files will go to CTP only.";
            } else if (multicolour) {
                workflowText = "Files will go to Multicolour only.";
            } else if (digital) {
                workflowText = "Files will go to Digital only.";
            } else {
                workflowText = "Please select at least one department.";
            }
            
            document.getElementById('workflow-description').textContent = workflowText;
        }
    </script>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Finalize Order</h2>
        <div class="nav-buttons">
            <button onclick="location.href='view_order.php'">Back to View Orders</button>
        </div>
    </div>

    <div class="customer-container">
        <h2>Finalize Job #<?php echo htmlspecialchars($job_id); ?></h2>
        
        <div class="workflow-info">
            <h3 style="margin-top: 0; margin-bottom: 10px; color: #007bff;">Workflow Information</h3>
            <div class="workflow-item">If job is reverted, both original and new files will be preserved.</div>
            <div class="workflow-item">Files will be routed based on the selected departments below.</div>
            <div class="workflow-item" id="workflow-description">Please select departments below to see workflow.</div>
        </div>
        
        <form class="finalize-form" method="POST" enctype="multipart/form-data">
            <div class="form-group checkbox-group">
                <label><input type="checkbox" id="ctp" name="ctp" <?php echo $job['ctp'] ? 'checked' : ''; ?> onchange="updateWorkflowInfo()"> CTP Required</label>
            </div>
            <div class="form-group checkbox-group">
                <label><input type="checkbox" id="multicolour" name="multicolour" <?php echo $job['multicolour'] ? 'checked' : ''; ?> onchange="updateWorkflowInfo()"> Multicolour Printing</label>
            </div>
            <div class="form-group checkbox-group">
                <label><input type="checkbox" id="digital" name="digital" <?php echo $job['digital'] ? 'checked' : ''; ?> onchange="updateWorkflowInfo()"> Digital Printing</label>
            </div>
            <div class="form-group file-group">
                <label>File Upload:</label>
                <div id="file-inputs">
                    <div class="file-input-container">
                        <input type="file" name="files[]" id="upload" multiple accept=".pdf,.jpg,.png" required>
                    </div>
                </div>
                <button type="button" class="add-more-btn" onclick="addMoreFiles()">Add More</button>
            </div>
            <div class="form-group textarea-group">
                <label>Description:</label>
                <textarea name="description" placeholder="Enter any additional details"><?php echo htmlspecialchars($job['description'] ?? ''); ?></textarea>
            </div>
            <button type="submit">Submit Finalization</button>
        </form>
    </div>
    
    <script>
        // Initialize workflow info on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateWorkflowInfo();
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>