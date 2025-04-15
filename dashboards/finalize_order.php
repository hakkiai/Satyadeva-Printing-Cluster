<?php
include '../database/db_connect.php';

if (!isset($_GET['id'])) {
    header("Location: new_order.php");
    exit;
}

$job_id = $_GET['id'];

// Fetch job details
$sql = "SELECT * FROM job_sheets WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();

if (!$job) {
    echo "<script>alert('Job not found!'); window.location.href='new_order.php';</script>";
    exit;
}

// Function to fetch customer's total balance and balance limit from customers table
function get_customer_balance_data($conn, $customer_name) {
    $sql = "SELECT total_balance, balance_limit FROM customers WHERE customer_name = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $customer_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return [
        'total_balance' => $row && $row['total_balance'] !== null ? floatval($row['total_balance']) : 0,
        'balance_limit' => $row && $row['balance_limit'] !== null ? floatval($row['balance_limit']) : 0
    ];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ctp = isset($_POST['ctp']) ? 1 : 0;
    $multicolour = isset($_POST['multicolour']) ? 1 : 0;
    $digital = isset($_POST['digital']) ? 1 : 0;
    $description = $_POST['description'];

    // Fetch customer balance data
    $customer_name = $job['customer_name'];
    $balance_data = get_customer_balance_data($conn, $customer_name);
    $total_balance = $balance_data['total_balance'];
    $balance_limit = $balance_data['balance_limit'];
    $new_charges = floatval($job['total_charges']);
    $total_potential_balance = $total_balance + $new_charges;

    // Debugging: Log values to verify
    error_log("Customer: $customer_name, Total Balance: $total_balance, Balance Limit: $balance_limit, New Charges: $new_charges, Total Potential Balance: $total_potential_balance");

    // Strictly enforce balance limit if set and exceeded
    if ($balance_limit > 0 && $total_balance >= $balance_limit) { // Changed to check current total_balance
        echo "<script>alert('Customer $customer_name has already reached or exceeded their balance limit (Current Balance: ₹" . number_format($total_balance, 2) . " >= Limit: ₹" . number_format($balance_limit, 2) . "). No new job sheets can be finalized until the balance is cleared.'); window.location.href='new_order.php?mode=edit&id=$job_id';</script>";
        $stmt->close();
        $conn->close();
        exit; // Stop execution immediately
    }

    // Proceed with file upload and finalization only if limit allows
    if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $files = $_FILES['files'];
        $file_paths = [];
        $file_count = count($files['name']);

        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] == UPLOAD_ERR_OK) {
                $file_name = $job_id . '_' . time() . '_' . basename($files['name'][$i]);
                $file_path = $upload_dir . $file_name;
                if (move_uploaded_file($files['tmp_name'][$i], $file_path)) {
                    $file_paths[] = $file_path;
                }
            }
        }

        if (!empty($file_paths)) {
            $file_paths_string = implode(',', $file_paths);
            $update_sql = "UPDATE job_sheets SET 
                ctp=?, multicolour=?, digital=?, description=?, file_path=?, status='Finalized',
                completed_ctp=0, completed_multicolour=0, completed_digital=0, completed_delivery=0
                WHERE id=?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("iiissi", $ctp, $multicolour, $digital, $description, $file_paths_string, $job_id);

            if ($stmt->execute()) {
                // Update total_balance in customers table
                $update_balance_sql = "UPDATE customers SET total_balance = total_balance + ? WHERE customer_name = ?";
                $balance_stmt = $conn->prepare($update_balance_sql);
                $balance_stmt->bind_param("ds", $new_charges, $customer_name);
                $balance_stmt->execute();
                $balance_stmt->close();

                echo "<script>alert('Order Finalized successfully with " . count($file_paths) . " file(s)!'); window.location.href='new_order.php';</script>";
            } else {
                echo "<script>alert('Error updating order details: " . $stmt->error . "');</script>";
            }
        } else {
            echo "<script>alert('File upload failed!');</script>";
        }
    } else {
        echo "<script>alert('Please upload at least one valid file!');</script>";
    }
    $stmt->close();
    $conn->close();
    exit;
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
        /* Same CSS as before, omitted for brevity */
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
    </script>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Finalize Order</h2>
        <div class="nav-buttons">
            <button onclick="location.href='new_order.php'">Back to New Orders</button>
        </div>
    </div>

    <div class="customer-container">
        <h2>Finalize Job #<?php echo $job_id; ?></h2>
        <form class="finalize-form" method="POST" enctype="multipart/form-data">
            <div class="form-group checkbox-group">
                <label><input type="checkbox" name="ctp" <?php echo $job['ctp'] ? 'checked' : ''; ?>> CTP Required</label>
            </div>
            <div class="form-group checkbox-group">
                <label><input type="checkbox" name="multicolour" <?php echo $job['multicolour'] ? 'checked' : ''; ?>> Multicolour Printing</label>
            </div>
            <div class="form-group checkbox-group">
                <label><input type="checkbox" name="digital" <?php echo $job['digital'] ? 'checked' : ''; ?>> Digital Printing</label>
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
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>