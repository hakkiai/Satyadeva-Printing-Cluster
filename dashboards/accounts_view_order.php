<?php
include '../database/db_connect.php';
session_start();
date_default_timezone_set('Asia/Kolkata');

if (!isset($_GET['id'])) {
    echo "<script>alert('No job ID provided!'); window.location.href='accounts_dashboard.php';</script>";
    exit;
}

$job_id = $_GET['id'];

// Fetch job details
$sql = "SELECT * FROM job_sheets WHERE id = ? AND completed_delivery = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    echo "<script>alert('Job not found or not completed!'); window.location.href='accounts_dashboard.php';</script>";
    exit;
}

// Function to calculate totals and balance
function calculate_totals($conn, $job_id, $total_charges) {
    $total_cash_paid = 0;
    $total_credit_logged = 0;
    $sql = "SELECT cash, credit FROM payment_records WHERE job_sheet_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $total_cash_paid += $row['cash'];
        $total_credit_logged += $row['credit'];
    }
    $stmt->close();

    $current_balance = $total_charges - $total_cash_paid;
    if ($total_credit_logged > 0 && $total_cash_paid < $total_charges) {
        $current_balance = min($total_credit_logged, $total_charges - $total_cash_paid);
    }
    if ($current_balance < 0) $current_balance = 0;

    return [$total_cash_paid, $total_credit_logged, $current_balance];
}

// Initial calculation
$total_charges = $job['plating_charges'] + $job['paper_charges'] + $job['lamination_charges'] + 
                $job['pinning_charges'] + $job['binding_charges'] + $job['finishing_charges'] + 
                $job['other_charges'] - $job['discount'];
list($total_cash_paid, $total_credit_logged, $current_balance) = calculate_totals($conn, $job_id, $total_charges);

// Fetch subcategories for paper dropdown
$subcategories = [];
$sql = "SELECT id, subcategory_name FROM inventory_subcategories WHERE category_id = (SELECT id FROM inventory_categories WHERE category_name = 'Paper')";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $subcategories[] = $row;
}

// Fetch items for type dropdown
$items = [];
$sql = "SELECT id, item_name, subcategory_id FROM inventory_items";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $items[$row['subcategory_id']][] = $row;
}

// Check if job is fully paid
$sql = "SELECT COUNT(*) as completed FROM payment_records WHERE job_sheet_id = ? AND payment_status = 'completed'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$is_completed = $result->fetch_assoc()['completed'] > 0;
$stmt->close();

// Get last payment method from session or default to 'cash'
$last_payment_method = $SESSION['payment_method' . $job_id] ?? 'cash';

// Handle job sheet update (only if not fully paid)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_job']) && !$is_completed) {
    $customer_name = $_POST['customer_name'];
    $phone_number = $_POST['phone_number'];
    $job_name = $_POST['job_name'];
    $paper_subcategory = $_POST['paper_subcategory'];
    $type = $_POST['type'];
    $quantity = $_POST['quantity'];
    $striking = $_POST['striking'];
    $machine = $_POST['machine'];
    $ryobi_type = $_POST['ryobi_type'] ?? NULL;
    $web_type = $_POST['web_type'] ?? NULL;
    $web_size = $_POST['web_size'] ?? NULL;
    $ctp_plate = $_POST['ctp_plate'] ?? NULL;
    $ctp_quantity = $_POST['ctp_quantity'];
    $plating_charges = floatval($_POST['plating_charges']);
    $paper_charges = floatval($_POST['paper_charges']); // Now printing_charges is paper_charges
    $printing_charges = floatval($_POST['printing_charges']); // Now paper_charges is printing_charges
    $lamination_charges = floatval($_POST['lamination_charges']);
    $pinning_charges = floatval($_POST['pinning_charges']);
    $binding_charges = floatval($_POST['binding_charges']);
    $finishing_charges = floatval($_POST['finishing_charges']);
    $other_charges = floatval($_POST['other_charges']);
    $discount = floatval($_POST['discount']);
    $total_charges = $plating_charges + $printing_charges + $lamination_charges + $pinning_charges + $binding_charges + $finishing_charges + $other_charges - $discount;

    // Update job_sheets
    $sql = "UPDATE job_sheets SET customer_name = ?, phone_number = ?, job_name = ?, paper_subcategory = ?, type = ?, quantity = ?, striking = ?, machine = ?, ryobi_type = ?, web_type = ?, web_size = ?, ctp_plate = ?, ctp_quantity = ?, plating_charges = ?, paper_charges = ?, printing_charges = ?, lamination_charges = ?, pinning_charges = ?, binding_charges = ?, finishing_charges = ?, other_charges = ?, discount = ?, total_charges = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssisssssisddddddddddi", $customer_name, $phone_number, $job_name, $paper_subcategory, $type, $quantity, $striking, $machine, $ryobi_type, $web_type, $web_size, $ctp_plate, $ctp_quantity, $plating_charges, $paper_charges, $printing_charges, $lamination_charges, $pinning_charges, $binding_charges, $finishing_charges, $other_charges, $discount, $total_charges, $job_id);
    $update_success = $stmt->execute();

    if ($update_success) {
        // Recalculate totals after update
        list($total_cash_paid, $total_credit_logged, $current_balance) = calculate_totals($conn, $job_id, $total_charges);

        $sql = "UPDATE payment_records SET balance = ? WHERE job_sheet_id = ? ORDER BY date DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $current_balance, $job_id);
        $stmt->execute();

        $sql = "SELECT COUNT(*) as count FROM payment_records WHERE job_sheet_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $job_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment_exists = $result->fetch_assoc()['count'] > 0;
        $stmt->close();

        if (!$payment_exists) {
            $date = date('Y-m-d H:i:s');
            $sql = "INSERT INTO payment_records (job_sheet_id, job_sheet_name, date, cash, credit, balance, payment_status, payment_type) 
                    VALUES (?, ?, ?, 0, 0, ?, 'partially_paid', 'cash')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issd", $job_id, $job_name, $date, $current_balance);
            $stmt->execute();
        }
        echo "<script>alert('Job sheet updated successfully!'); window.location.href='accounts_view_order.php?id=$job_id';</script>";
    } else {
        echo "<script>alert('Error updating job sheet: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// Handle payment submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_payment'])) {
    $payment_method = $_POST['payment_method'] ?? $last_payment_method;
    $cash_amount = ($payment_method !== 'credit') ? floatval($_POST['cash_amount'] ?? 0) : 0;
    $credit_amount = ($payment_method === 'credit') ? floatval($current_balance) : 0;
    $job_sheet_name = $job['job_name'] ?? 'Unnamed Job';
    $date = date('Y-m-d H:i:s');
    $SESSION['payment_method' . $job_id] = $payment_method;

    // Validate cash_amount
    if ($payment_method !== 'credit' && ($cash_amount < 0 || $cash_amount > $current_balance)) {
        echo "<script>alert('Invalid cash amount! Must be between 0 and $current_balance.');</script>";
        exit;
    }

    // Calculate the new balance
    if ($payment_method === 'credit') {
        $balance_amount = $current_balance;
        $payment_status = 'uncredit';
    } else {
        $balance_amount = $current_balance - $cash_amount;
        $payment_status = ($balance_amount <= 0) ? 'completed' : 'partially_paid';
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert into payment_records
        $sql = "INSERT INTO payment_records (job_sheet_id, job_sheet_name, date, cash, credit, balance, payment_status, payment_type) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("isssddss", $job_id, $job_sheet_name, $date, $cash_amount, $credit_amount, $balance_amount, $payment_status, $payment_method);
        if (!$stmt->execute()) {
            throw new Exception("Insert failed: " . $stmt->error);
        }
        $stmt->close();

        // Update job_sheets payment_status
        $final_payment_status = ($payment_method === 'credit') ? 'uncredit' : $payment_status;
        $sql_update = "UPDATE job_sheets SET payment_status = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if (!$stmt_update) {
            throw new Exception("Prepare update failed: " . $conn->error);
        }
        $stmt_update->bind_param("si", $final_payment_status, $job_id);
        if (!$stmt_update->execute()) {
            throw new Exception("Update failed: " . $stmt_update->error);
        }
        $stmt_update->close();

        // Insert Accounts stage into jobsheet_progress_history
        $progress_sql = "INSERT INTO jobsheet_progress_history (job_sheet_id, stage, created_at) 
                         VALUES (?, 'Accounts', NOW()) 
                         ON DUPLICATE KEY UPDATE created_at = NOW()";
        $stmt_progress = $conn->prepare($progress_sql);
        if (!$stmt_progress) {
            throw new Exception("Prepare progress failed: " . $conn->error);
        }
        $stmt_progress->bind_param("i", $job_id);
        if (!$stmt_progress->execute()) {
            throw new Exception("Progress insert failed: " . $stmt_progress->error);
        }
        $stmt_progress->close();

        // Recalculate totals
        list($total_cash_paid, $total_credit_logged, $current_balance) = calculate_totals($conn, $job_id, $total_charges);

        // Send to dispatch_jobs
        $dispatch_sql = "INSERT INTO dispatch_jobs (id, customer_name, job_name, total_charges, description, payment_status, balance, dispatched_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW()) 
                         ON DUPLICATE KEY UPDATE 
                         customer_name = VALUES(customer_name), 
                         job_name = VALUES(job_name), 
                         total_charges = VALUES(total_charges), 
                         description = VALUES(description), 
                         payment_status = VALUES(payment_status), 
                         balance = VALUES(balance), 
                         dispatched_at = VALUES(dispatched_at)";
        $stmt_dispatch = $conn->prepare($dispatch_sql);
        if (!$stmt_dispatch) {
            throw new Exception("Prepare dispatch failed: " . $conn->error);
        }
        $dispatch_description = $job['description'] ?? 'N/A';
        $stmt_dispatch->bind_param("isssdss", $job_id, $job['customer_name'], $job_sheet_name, $total_charges, $dispatch_description, $final_payment_status, $balance_amount);
        if (!$stmt_dispatch->execute()) {
            throw new Exception("Dispatch failed: " . $stmt_dispatch->error);
        }
        $stmt_dispatch->close();

        // Commit transaction
        $conn->commit();

        echo "<script>alert('Payment recorded successfully! New Balance: ₹$current_balance'); window.location.href='accounts_view_order.php?id=$job_id';</script>";
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        error_log("Payment submission error: " . $e->getMessage());
        echo "<script>alert('Error processing payment: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Job Sheet - Accounts</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .container { 
            width: 80%; 
            margin: 20px auto; 
            padding: 20px; 
            background-color: white; 
            border-radius: 10px; 
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); 
        }
        .customer-container { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 15px; 
            margin-bottom: 20px; 
            padding: 15px; 
            background-color: #f9f9f9; 
            border-radius: 8px; 
        }
        .form-group { 
            display: flex; 
            flex-direction: column; 
        }
        label { 
            margin-bottom: 5px; 
            font-weight: bold; 
            color: #333; 
            font-size: 0.9em; 
        }
        input, select { 
            width: 100%; 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            box-sizing: border-box; 
            font-size: 14px; 
        }
        input[readonly] {
            background-color: #f0f0f0;
            cursor: not-allowed;
        }
        button { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: bold; 
            margin: 5px; 
            transition: background-color 0.3s; 
        }
        .back-btn { 
            background-color: #6c757d; 
            color: white; 
        }
        .submit-btn { 
            background-color: #28a745; 
            color: white; 
        }
        .change-method-btn { 
            background-color: #007bff; 
            color: white; 
        }
        .statement-btn { 
            background-color: #ffc107; 
            color: black; 
        }
        .disabled-btn { 
            background-color: #ccc; 
            cursor: not-allowed; 
        }
        button:hover { 
            opacity: 0.9; 
        }
        #payment-section { 
            margin-top: 20px; 
            padding: 15px; 
            background-color: #e9ecef; 
            border-radius: 8px; 
        }
        .dialog-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }
        .dialog-box {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
            text-align: center;
        }
        .dialog-box button {
            margin: 10px 5px;
        }
        .check-btn { 
            background-color: #17a2b8; 
            color: white; 
        }
        .online-btn { 
            background-color: #007bff; 
            color: white; 
        }
        .cash-btn { 
            background-color: #28a745; 
            color: white; 
        }
        .phonepe-btn { 
            background-color: #6f42c1; 
            color: white; 
        }
        .credit-btn { 
            background-color: #dc3545; 
            color: white; 
        }
        .hidden { 
            display: none; 
        }
        h2 {
            color: #007bff;
            margin-bottom: 15px;
            font-size: 1.5em;
            text-align: center;
        }
        .bill-details-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            padding: 15px;
            background-color: #e9ecef;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .bill-details-grid .form-group {
            margin-bottom: 0;
        }
        .bill-details-grid label {
            font-size: 0.9em;
            color: #555;
            text-transform: uppercase;
        }
        .bill-details-grid input {
            font-weight: bold;
            background-color: white;
            border: 1px solid #ccc;
        }
        .total-charges {
            background-color: #e7f3ff;
            border: 2px solid #007bff;
            padding: 5px;
            font-weight: bold;
            color: #007bff;
        }
        .payment-details-grid {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            padding: 10px;
            flex-wrap: wrap;
        }
        .payment-details-grid .form-group {
            flex: 1;
            min-width: 200px;
        }
        .payment-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 10px;
        }
    </style>
    <script>
    var itemsData = <?= json_encode($items) ?>;
    var lastPaymentMethod = '<?= $last_payment_method ?>';
    var currentBalance = <?= $current_balance ?>; // Updated after payment
    var totalCashPaid = <?= $total_cash_paid ?>;
    var totalCreditLogged = <?= $total_credit_logged ?>;
    var totalCharges = <?= $total_charges ?>;

    function updateTypeDropdown() {
        var subcategoryId = document.getElementById("paper_subcategory").value;
        var typeDropdown = document.getElementById("type");
        typeDropdown.innerHTML = '<option value="">Select Type</option>';

        if (subcategoryId && itemsData[subcategoryId]) {
            itemsData[subcategoryId].forEach(item => {
                var option = document.createElement("option");
                option.value = item.id;
                option.textContent = item.item_name;
                if (item.id == "<?= $job['type'] ?>") option.selected = true;
                typeDropdown.appendChild(option);
            });
        }
    }

    function calculateTotalCharges() {
        let platingCharges = parseFloat(document.getElementsByName("plating_charges")[0].value) || 0;
        let printingCharges = parseFloat(document.getElementsByName("paper_charges")[0].value) || 0;
        let laminationCharges = parseFloat(document.getElementsByName("lamination_charges")[0].value) || 0;
        let pinningCharges = parseFloat(document.getElementsByName("pinning_charges")[0].value) || 0;
        let bindingCharges = parseFloat(document.getElementsByName("binding_charges")[0].value) || 0;
        let finishingCharges = parseFloat(document.getElementsByName("finishing_charges")[0].value) || 0;
        let otherCharges = parseFloat(document.getElementsByName("other_charges")[0].value) || 0;
        let discount = parseFloat(document.getElementsByName("discount")[0].value) || 0;

        let total = platingCharges + printingCharges + laminationCharges + pinningCharges + 
                    bindingCharges + finishingCharges + otherCharges - discount;
        total = Math.max(total, 0);
        document.getElementsByName("total_charges")[0].value = total.toFixed(2);

        let newBalance = total - totalCashPaid;
        if (totalCreditLogged > 0 && totalCashPaid < total) {
            newBalance = min(totalCreditLogged, total - totalCashPaid);
        }
        document.getElementsByName("balance_amount")[0].value = newBalance.toFixed(2);

        if (lastPaymentMethod !== 'credit') {
            calculateBalanceAmount();
        }
    }

    function calculateBalanceAmount() {
        let cashAmount = parseFloat(document.getElementsByName("cash_amount")[0].value) || 0;
        let balanceAmount = currentBalance - cashAmount;
        document.getElementsByName("balance_amount")[0].value = balanceAmount.toFixed(2);
    }

    function showPaymentFields(paymentMethod) {
        let cashGroup = document.getElementById('cash-group');
        let creditLabel = document.getElementById('credit-label');
        document.getElementsByName("payment_method")[0].value = paymentMethod;
        lastPaymentMethod = paymentMethod;

        if (paymentMethod === 'credit') {
            cashGroup.classList.add('hidden');
            creditLabel.classList.remove('hidden');
            document.getElementsByName("balance_amount")[0].value = currentBalance.toFixed(2);
        } else {
            cashGroup.classList.remove('hidden');
            creditLabel.classList.add('hidden');
            document.getElementsByName("cash_amount")[0].value = 0;
            document.getElementsByName("balance_amount")[0].value = currentBalance.toFixed(2);
        }

        fetch('set_payment_method.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'job_id=<?= $job_id ?>&payment_method=' + encodeURIComponent(paymentMethod)
        }).then(response => response.text())
          .then(data => console.log(data))
          .catch(error => console.error('Error:', error));

        document.getElementById('paymentDialog').style.display = 'none';
    }

    function showPaymentDialog() {
        document.getElementById('paymentDialog').style.display = 'flex';
    }

    document.addEventListener("DOMContentLoaded", function () {
        updateTypeDropdown();
        let billFields = ["plating_charges", "paper_charges", "lamination_charges", 
                        "pinning_charges", "binding_charges", "finishing_charges", "other_charges", "discount"];
        billFields.forEach(fieldName => {
            document.getElementsByName(fieldName)[0].addEventListener("input", calculateTotalCharges);
        });

        calculateTotalCharges();
        showPaymentFields(lastPaymentMethod);

        let dialog = document.getElementById('paymentDialog');
        <?php if (!$is_completed && !isset($SESSION['payment_method' . $job_id])): ?>
            dialog.style.display = 'flex';
        <?php else: ?>
            dialog.style.display = 'none';
        <?php endif; ?>

        document.getElementById('checkBtn').onclick = function() { showPaymentFields('cheque'); };
        document.getElementById('onlineBtn').onclick = function() { showPaymentFields('online'); };
        document.getElementById('cashBtn').onclick = function() { showPaymentFields('cash'); };
        document.getElementById('phonepeBtn').onclick = function() { showPaymentFields('phonepe'); };
        document.getElementById('creditBtn').onclick = function() { showPaymentFields('credit'); };

        document.getElementById('changeMethodBtn').onclick = showPaymentDialog;
        document.getElementsByName("cash_amount")[0].addEventListener("input", calculateBalanceAmount);
    });
    </script>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">View Job Sheet - Accounts</h2>
        <div class="nav-buttons">
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="container">
        <h2>Job Sheet Details (ID: <?= $job['id'] ?>)</h2>
        <form method="POST">
            <div class="customer-container">
                <h2>Customer Details</h2>
                <div class="form-group">
                    <label>Customer Name</label>
                    <input type="text" name="customer_name" value="<?= htmlspecialchars($job['customer_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone_number" value="<?= htmlspecialchars($job['phone_number']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Job Name</label>
                    <input type="text" name="job_name" value="<?= htmlspecialchars($job['job_name']) ?>" required>
                </div>
            </div>

            <div class="customer-container">
                <h2>Paper Section</h2>
                <div class="form-group">
                    <label>Paper (Subcategory)</label>
                    <select name="paper_subcategory" id="paper_subcategory" onchange="updateTypeDropdown()" required>
                        <option value="">Select Paper</option>
                        <?php foreach ($subcategories as $subcategory): ?>
                            <option value="<?= $subcategory['id'] ?>" <?= $subcategory['id'] == $job['paper_subcategory'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subcategory['subcategory_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" id="type" required>
                        <option value="">Select Type</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" value="<?= $job['quantity'] ?>" min="1" required>
                </div>
                <div class="form-group">
                    <label>Striking</label>
                    <input type="text" name="striking" value="<?= htmlspecialchars($job['striking']) ?>" required>
                </div>
            </div>

            <div class="customer-container">
                <h2>Machine Selection</h2>
                <div class="form-group">
                    <label>Machine</label>
                    <select name="machine" required>
                        <option value="DD" <?= $job['machine'] == 'DD' ? 'selected' : '' ?>>D/D</option>
                        <option value="SDD" <?= $job['machine'] == 'SDD' ? 'selected' : '' ?>>S/D</option>
                        <option value="DC" <?= $job['machine'] == 'DC' ? 'selected' : '' ?>>D/C</option>
                        <option value="RYOBI" <?= $job['machine'] == 'RYOBI' ? 'selected' : '' ?>>RYOBI</option>
                        <option value="Web" <?= $job['machine'] == 'Web' ? 'selected' : '' ?>>WEB</option>
                        <option value="Digital" <?= $job['machine'] == 'Digital' ? 'selected' : '' ?>>Digital</option>
                        <option value="CTP" <?= $job['machine'] == 'CTP' ? 'selected' : '' ?>>CTP</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>RYOBI Type</label>
                    <select name="ryobi_type">
                        <option value="">None</option>
                        <option value="black" <?= $job['ryobi_type'] == 'black' ? 'selected' : '' ?>>Black</option>
                        <option value="color" <?= $job['ryobi_type'] == 'color' ? 'selected' : '' ?>>Color</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Web Type</label>
                    <select name="web_type">
                        <option value="">None</option>
                        <option value="black" <?= $job['web_type'] == 'black' ? 'selected' : '' ?>>Black</option>
                        <option value="color" <?= $job['web_type'] == 'color' ? 'selected' : '' ?>>Color</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Web Size</label>
                    <select name="web_size">
                        <option value="">None</option>
                        <option value="8" <?= $job['web_size'] == 8 ? 'selected' : '' ?>>8</option>
                        <option value="16" <?= $job['web_size'] == 16 ? 'selected' : '' ?>>16</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>CTP Plate</label>
                    <select name="ctp_plate">
                        <option value="">None</option>
                        <option value="700x945" <?= $job['ctp_plate'] == '700x945' ? 'selected' : '' ?>>700 × 945</option>
                        <option value="335x485" <?= $job['ctp_plate'] == '335x485' ? 'selected' : '' ?>>335 × 485</option>
                        <option value="560x670" <?= $job['ctp_plate'] == '560x670' ? 'selected' : '' ?>>560 × 670</option>
                        <option value="610x890" <?= $job['ctp_plate'] == '610x890' ? 'selected' : '' ?>>610 × 890</option>
                        <option value="605x60" <?= $job['ctp_plate'] == '605x60' ? 'selected' : '' ?>>605 × 60</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>CTP Quantity</label>
                    <input type="number" name="ctp_quantity" value="<?= $job['ctp_quantity'] ?>" min="0">
                </div>
            </div>

            <div class="customer-container bill-details-grid">
                <h2>Bill Details</h2>
                <div class="form-group">
                    <label>Plating Charges</label>
                    <input type="number" name="plating_charges" value="<?= $job['plating_charges'] ?>" step="0.01">
                </div>
                <div class="form-group">
                    <label>Paper Charges</label>
                    <input type="number" name="printing_charges" value="<?= $job['printing_charges'] ?>" step="0.01">
                </div>
                <div class="form-group">
                    <label>Printing Charges</label>
                    <input type="number" name="paper_charges" value="<?= $job['paper_charges'] ?>" step="0.01">
                </div>
                <div class="form-group">
                    <label>Lamination Charges</label>
                    <input type="number" name="lamination_charges" value="<?= $job['lamination_charges'] ?>" step="0.01">
                </div>
                <div class="form-group">
                    <label>Pinning Charges</label>
                    <input type="number" name="pinning_charges" value="<?= $job['pinning_charges'] ?>" step="0.01">
                </div>
                <div class="form-group">
                    <label>Binding Charges</label>
                    <input type="number" name="binding_charges" value="<?= $job['binding_charges'] ?>" step="0.01">
                </div>
                <div class="form-group">
                    <label>Finishing Charges</label>
                    <input type="number" name="finishing_charges" value="<?= $job['finishing_charges'] ?>" step="0.01">
                </div>
                <div class="form-group">
                    <label>Other Charges</label>
                    <input type="number" name="other_charges" value="<?= $job['other_charges'] ?>" step="0.01">
                </div>
                <div class="form-group">
                    <label>Discount</label>
                    <input type="number" name="discount" value="<?= $job['discount'] ?>" step="0.01">
                </div>
                <div class="form-group">
                    <label>Total Charges (Excl. Paper)</label>
                    <input type="number" name="total_charges" value="<?= $total_charges ?>" step="0.01" readonly class="total-charges">
                </div>
            </div>

            <div id="payment-section">
                <h2>Payment Details</h2>
                <p style="font-size: 0.9em; color: #666; text-align: center;">Note: Balance reflects amount to repay (excl. paper charges)</p>
                <div class="payment-details-grid">
                    <div class="form-group" id="cash-group">
                        <label>Amount</label>
                        <input type="number" name="cash_amount" value="0" step="0.01" min="0" max="<?= $current_balance ?>">
                    </div>
                    <div class="form-group">
                        <label>Balance Amount (To Repay)</label>
                        <input type="number" name="balance_amount" value="<?= $current_balance ?>" step="0.01" readonly>
                    </div>
                    <div class="form-group hidden" id="credit-label">
                        <label>Payment Method: Credit</label>
                    </div>
                    <input type="hidden" name="payment_method" value="<?= $last_payment_method ?>">
                </div>
                <div class="payment-buttons">
                    <button type="button" id="changeMethodBtn" class="change-method-btn">Change Payment Method</button>
                    <button type="submit" name="submit_payment" class="submit-btn" <?= $is_completed ? 'disabled' : '' ?>>Submit Payment</button>
                </div>
            </div>

            <div class="dialog-overlay" id="paymentDialog">
                <div class="dialog-box">
                    <h3>Select Payment Method</h3>
                    <button id="checkBtn" class="check-btn">Cheque</button>
                    <button id="onlineBtn" class="online-btn">Online</button>
                    <button id="cashBtn" class="cash-btn">Cash</button>
                    <button id="phonepeBtn" class="phonepe-btn">PhonePe</button>
                    <button id="creditBtn" class="credit-btn">Credit</button>
                </div>
            </div>

            <div style="text-align: center; margin-top: 20px;">
                <button type="submit" name="update_job" class="submit-btn" <?= $is_completed ? 'disabled' : '' ?>>Update Job Sheet</button>
                <button type="button" class="back-btn" onclick="location.href='accounts_dashboard.php'">Back to Dashboard</button>
                <button type="button" class="statement-btn" onclick="location.href='payment_statement.php?job_id=<?= $job_id ?>'">View Statement</button>
            </div>
        </form>
    </div>
</body>
</htm