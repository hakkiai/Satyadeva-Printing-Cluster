<?php
include '../database/db_connect.php'; 

$customer_name = $phone_number = $job_name = $total_charges = $payment_status = "";
$paper_subcategory = $type = $quantity = $striking = $machine = $ryobi_type = $web_type = $web_size = "";
$ctp_plate = $ctp_quantity = $plating_charges = $paper_charges = $printing_charges = "";
$lamination_charges = $pinning_charges = $binding_charges = $finishing_charges = $other_charges = $discount = "";
$status = "Draft";
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'add';
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Load job sheet details if in view or edit mode
if ($job_id > 0 && ($mode === 'view' || $mode === 'edit')) {
    $sql = "SELECT * FROM job_sheets WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $customer_name = $row['customer_name'];
        $phone_number = $row['phone_number'];
        $job_name = $row['job_name'];
        $paper_subcategory = $row['paper_subcategory'];
        $type = $row['type'];
        $quantity = $row['quantity'];
        $striking = $row['striking'];
        $machine = $row['machine'];
        $ryobi_type = $row['ryobi_type'];
        $web_type = $row['web_type'];
        $web_size = $row['web_size'];
        $ctp_plate = $row['ctp_plate'];
        $ctp_quantity = $row['ctp_quantity'];
        $plating_charges = $row['plating_charges'];
        $paper_charges = $row['paper_charges'];
        $printing_charges = $row['printing_charges'];
        $lamination_charges = $row['lamination_charges'];
        $pinning_charges = $row['pinning_charges'];
        $binding_charges = $row['binding_charges'];
        $finishing_charges = $row['finishing_charges'];
        $other_charges = $row['other_charges'];
        $discount = $row['discount'];
        $total_charges = $row['total_charges'];
        $status = $row['status'];
    }
    $stmt->close();
}

$subcategories = [];
$items = [];
$sql = "SELECT id, subcategory_name FROM inventory_subcategories WHERE category_id=(SELECT id FROM inventory_categories WHERE category_name='Paper')";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $subcategories[] = $row;
}
$sql = "SELECT id, item_name, subcategory_id FROM inventory_items";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $items[$row['subcategory_id']][] = $row;
}

if (isset($_POST['get_customer_type']) && isset($_POST['customer_name'])) {
    $customer_name = $_POST['customer_name'];
    $query = "SELECT member_status FROM customers WHERE customer_name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $customer_name);
    $stmt->execute();
    $stmt->bind_result($member_status);
    $response = ["success" => false];
    if ($stmt->fetch()) {
        $response["success"] = true;
        $response["customer_type"] = $member_status ? "member" : "non_member";
    }
    $stmt->close();
    echo json_encode($response);
    exit;
}

if (isset($_POST['subcategory_id'], $_POST['item_id'])) {
    $subcategory_id = $_POST['subcategory_id'];
    $item_id = $_POST['item_id'];

    $sql = "SELECT selling_price FROM sales_prices WHERE item_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $response = ["success" => false];
    if ($row = $result->fetch_assoc()) {
        $response["success"] = true;
        $response["selling_price"] = $row["selling_price"];
    } else {
        error_log("No selling price found for item_id: $item_id");
    }
    echo json_encode($response);
    exit;
}

$customer_type = "non_member";
if (isset($_POST['customer_name']) && !isset($_POST['get_customer_type'])) {
    $customer_name = $_POST['customer_name'];
    $query = "SELECT member_status FROM customers WHERE customer_name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $customer_name);
    $stmt->execute();
    $stmt->bind_result($member_status);
    if ($stmt->fetch()) {
        $customer_type = $member_status ? "member" : "non_member";
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['subcategory_id']) && !isset($_POST['get_customer_type'])) {
    $job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
    $customer_name = $_POST['customer_name'];
    $phone_number = $_POST['phone_number'];
    $job_name = $_POST['job_name'];
    $paper_subcategory = $_POST['paper'];
    $type = $_POST['type'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $striking = $_POST['striking'];
    $machine = $_POST['machine'];
    $ryobi_type = $_POST['ryobi_type'] ?? NULL;
    $web_type = $_POST['web_type'] ?? NULL;
    $web_size = isset($_POST['web_size']) ? (int)$_POST['web_size'] : NULL;
    $ctp_plate = $_POST['ctp_plate'] ?? NULL;
    $ctp_quantity = isset($_POST['ctp_quantity']) ? (int)$_POST['ctp_quantity'] : 0;
    $plating_charges = $_POST['plating_charges'];
    $paper_charges = $_POST['paper_charges'];
    $printing_charges = $_POST['printing_charges'];
    $lamination_charges = $_POST['lamination_charges'];
    $pinning_charges = $_POST['pinning_charges'];
    $binding_charges = $_POST['binding_charges'];
    $finishing_charges = $_POST['finishing_charges'];
    $other_charges = $_POST['other_charges'];
    $discount = $_POST['discount'];
    $total_charges = $_POST['total_charges'];
    $status = $_POST['status'] ?? 'Draft';

    // Debug: Log the status values
    error_log("POST status: " . (isset($_POST['status']) ? $_POST['status'] : 'Not set'));
    error_log("Status variable: " . $status);

    if ($job_id > 0) {
        // Update existing job sheet
        $sql = "UPDATE job_sheets SET 
            customer_name=?, phone_number=?, job_name=?, paper_subcategory=?, type=?, quantity=?, striking=?, machine=?, 
            ryobi_type=?, web_type=?, web_size=?, ctp_plate=?, ctp_quantity=?, plating_charges=?, paper_charges=?, 
            printing_charges=?, lamination_charges=?, pinning_charges=?, binding_charges=?, finishing_charges=?, 
            other_charges=?, discount=?, total_charges=?, status=?
            WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisssssssisddddddddddsi", 
            $customer_name, $phone_number, $job_name, $paper_subcategory, $type, $quantity, 
            $striking, $machine, $ryobi_type, $web_type, $web_size, $ctp_plate, $ctp_quantity, 
            $plating_charges, $paper_charges, $printing_charges, $lamination_charges, 
            $pinning_charges, $binding_charges, $finishing_charges, $other_charges, 
            $discount, $total_charges, $status, $job_id
        );
    } else {
        // Insert new job sheet
        $sql = "INSERT INTO job_sheets (
            customer_name, phone_number, job_name, paper_subcategory, type, quantity, striking, machine, 
            ryobi_type, web_type, web_size, ctp_plate, ctp_quantity, plating_charges, paper_charges, 
            printing_charges, lamination_charges, pinning_charges, binding_charges, finishing_charges, 
            other_charges, discount, total_charges, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisssssssisdddddddddds", 
            $customer_name, $phone_number, $job_name, $paper_subcategory, $type, $quantity, 
            $striking, $machine, $ryobi_type, $web_type, $web_size, $ctp_plate, $ctp_quantity, 
            $plating_charges, $paper_charges, $printing_charges, $lamination_charges, 
            $pinning_charges, $binding_charges, $finishing_charges, $other_charges, 
            $discount, $total_charges, $status
        );
    }

    if ($stmt->execute()) {
        if ($job_id == 0) {
            $job_id = $conn->insert_id;
        }
        error_log("Database operation successful. Status: " . $status);
        if ($status === 'Finalized') {
            error_log("Redirecting to finalize_order.php?id=$job_id");
            header("Location: finalize_order.php?id=$job_id");
            exit;
        } else {
            error_log("Redirecting to view_order.php");
            header("Location: view_order.php");
            exit;
        }
    } else {
        $error = "Error: " . $stmt->error;
        error_log($error);
        echo $error;
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reception Dashboard - New Order</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Custom dialog styles */
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
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .yes-btn { background-color: #28a745; color: white; }
        .no-btn { background-color: #dc3545; color: white; }

        /* Job Sheet Button Styling */
        .job-sheet-btn {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        }
        .job-sheet-btn:hover {
            background: linear-gradient(135deg, #0056b3, #003d82);
            transform: translateY(-2px);
            box-shadow: 0px 6px 12px rgba(0, 0, 0, 0.2);
        }
    </style>
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
        <h2>Customer Management</h2>
        <h3 style="padding-left:20px;">Select Customer</h3>
        <form method="GET" id="searchForm">
            <input type="text" name="search_query" class="search-input" placeholder="Search Customer...">
            <button type="submit" class="search-btn">Search</button>
        </form>
    </div>

    <?php if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search_query'])): ?>
    <div class="container" id="searchResults">
        <?php
        $search = $conn->real_escape_string($_GET['search_query']);
        $sql = "SELECT customer_name, phone_number FROM customers WHERE customer_name LIKE ?";
        $stmt = $conn->prepare($sql);
        $search_param = "%$search%";
        $stmt->bind_param("s", $search_param);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $customerJson = json_encode([
                    'customer_name' => $row['customer_name'],
                    'phone_number' => $row['phone_number']
                ]);
                echo "<div class='vendor-card'>
                    <strong class='vendor-name'>" . htmlspecialchars($row['customer_name']) . "</strong>
                    <div class='vendor-actions'>
                        <button class='edit-btn' onclick='selectCustomer(JSON.parse(\"" . addslashes($customerJson) . "\"))'>Select</button>
                    </div>
                    <p>Phone: " . htmlspecialchars($row['phone_number']) . "</p>
                </div>";
            }
        } else {
            echo "<p>No customers found.</p>";
        }
        $stmt->close();
        ?>
    </div>
    <?php endif; ?>

    <form action="" method="POST" id="jobForm" class="job-sheet-form">
        <input type="hidden" id="status" name="status" value="Draft">
        <input type="hidden" name="job_id" value="<?= $job_id ?>">
        <div class="container" style="width:70%">
            <h2 id="customerDetailsHeader">Customer Details</h2>
            <div class="customer-container" style="display:flex;justify-content:space-evenly;">
                <div class="form-group">
                    <label>Customer Name:</label>
                    <input type="text" name="customer_name" id="customer_name" placeholder="Enter Customer Name" value="<?= htmlspecialchars($customer_name) ?>" required <?= $mode === 'view' ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Phone Number:</label>
                    <input type="text" name="phone_number" id="phone_number" placeholder="Enter Phone Number" value="<?= htmlspecialchars($phone_number) ?>" required <?= $mode === 'view' ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Job Name:</label>
                    <input type="text" name="job_name" placeholder="Enter Job Name" value="<?= htmlspecialchars($job_name) ?>" required <?= $mode === 'view' ? 'readonly' : '' ?>>
                </div>
            </div>
            
            <h2>Paper Section</h2>
            <div class="customer-container" style="display:flex;gap:20px;justify-content:space-between;">
                <div class="form-group">
                    <label>Paper (Subcategory):</label>
                    <select name="paper" id="paper" onchange="updateTypeDropdown()" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="">Select Paper</option>
                        <?php foreach ($subcategories as $subcategory): ?>
                            <option value="<?= $subcategory['id'] ?>" <?= $paper_subcategory == $subcategory['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($subcategory['subcategory_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="type-container" style="display:<?= $paper_subcategory ? 'block' : 'none' ?>;">
                    <label>Type:</label>
                    <select name="type" id="type" onchange="fetchSellingPrice()" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="">Select Type</option>
                        <?php if ($paper_subcategory && isset($items[$paper_subcategory])): ?>
                            <?php foreach ($items[$paper_subcategory] as $item): ?>
                                <option value="<?= $item['id'] ?>" <?= $type == $item['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($item['item_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity:</label>
                    <input type="number" id="quantity" name="quantity" min="1" value="<?= $quantity ?: 1 ?>" oninput="calculateStrikingAndUpdate()" required <?= $mode === 'view' ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Printing Type:</label>
                    <select id="printingType" name="striking" onchange="calculateStrikingAndUpdate()" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="select" <?= $striking == 'select' ? 'selected' : '' ?>>Select striking type</option>
                        <option value="Customer" <?= $striking == 'Customer' ? 'selected' : '' ?>>One Side</option>
                        <option value="Company" <?= $striking == 'Company' ? 'selected' : '' ?>>Back and Back</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Striking:</label>
                    <input id="striking" name="plates" value="<?= $striking ?>" readonly>
                </div>
                <input type="hidden" id="selling_price" value="">
            </div>

            <h2>Machine Selection</h2>
            <div class="customer-container" style="display:flex;gap:20px;justify-content:space-between;">
                <div class="machine-selection">
                    <label><input type="radio" name="machine" value="DD" onchange="updateMachineOptions()" <?= $machine == 'DD' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> D/D</label>
                    <label><input type="radio" name="machine" value="SDD" onchange="updateMachineOptions()" <?= $machine == 'SDD' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> S/D</label>
                    <label><input type="radio" name="machine" value="DC" onchange="updateMachineOptions()" <?= $machine == 'DC' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> D/C</label>
                    <label><input type="radio" name="machine" value="RYOBI" onchange="updateMachineOptions()" <?= $machine == 'RYOBI' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> RYOBI</label>
                    <label><input type="radio" name="machine" value="Web" onchange="updateMachineOptions()" <?= $machine == 'Web' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> WEB</label>
                    <label><input type="radio" name="machine" value="Digital" onchange="updateMachineOptions()" <?= $machine == 'Digital' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> Digital</label>
                    <label><input type="radio" name="machine" value="CTP" onchange="updateMachineOptions()" <?= $machine == 'CTP' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> CTP</label>
                    <label><input type="checkbox" name="machine" value="re-print" id="re-print" <?= $mode === 'view' ? 'disabled' : '' ?>>RePrint</label>
                </div>

                <div class="form-group" id="ryobi-options" style="display:<?= $machine == 'RYOBI' ? 'block' : 'none' ?>;">
                    <label>RYOBI Type:</label>
                    <select name="ryobi_type" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="">Select RYOBI Type</option>
                        <option value="black" <?= $ryobi_type == 'black' ? 'selected' : '' ?>>Black</option>
                        <option value="color" <?= $ryobi_type == 'color' ? 'selected' : '' ?>>Color</option>
                    </select>
                </div>

                <div class="form-group" id="web-options" style="display:<?= $machine == 'Web' ? 'block' : 'none' ?>;">
                    <label>Web Type:</label>
                    <select name="web_type" id="webType" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="">Select web color</option>
                        <option value="black" <?= $web_type == 'black' ? 'selected' : '' ?>>Black</option>
                        <option value="color" <?= $web_type == 'color' ? 'selected' : '' ?>>Color</option>
                    </select>
                </div>

                <div class="form-group" id="web-sub-options" style="display:<?= $machine == 'Web' ? 'block' : 'none' ?>;">
                    <label>No of Papers:</label>
                    <select name="web_size" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="">Select Pages</option>
                        <option value="8" <?= $web_size == 8 ? 'selected' : '' ?>>8</option>
                        <option value="16" <?= $web_size == 16 ? 'selected' : '' ?>>16</option>
                    </select>
                </div>

                <div class="ctp-section" style="display:<?= $machine == 'CTP' ? 'none' : 'block' ?>;" id="ctp-section">
                    <h3>CTP Plate Sizes</h3>
                    <div class="plate-sizes">
                        <label><input type="radio" name="ctpPlate" value="700x945" <?= $ctp_plate == '700x945' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> 700 × 945</label>
                        <label><input type="radio" name="ctpPlate" value="335x485" <?= $ctp_plate == '335x485' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> 335 × 485</label>
                        <label><input type="radio" name="ctpPlate" value="560x670" <?= $ctp_plate == '560x670' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> 560 × 670</label>
                        <label><input type="radio" name="ctpPlate" value="610x890" <?= $ctp_plate == '610x890' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> 610 × 890</label>
                        <label><input type="radio" name="ctpPlate" value="605x60" <?= $ctp_plate == '605x60' ? 'checked' : '' ?> <?= $mode === 'view' ? 'disabled' : '' ?>> 605 × 60</label>
                    </div>
                    <label>Enter the quantity:</label>
                    <input type="number" id="ctpQuantity" name="ctp_quantity" placeholder="Enter Quantity" value="<?= $ctp_quantity ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                </div>

                <div class="ctp-plate-selection" id="ctpPlateSection" style="display:<?= $machine == 'CTP' ? 'block' : 'none' ?>;">
                    <h3>Select Plate Size</h3>
                    <select id="plateSize" <?= $mode === 'view' ? 'disabled' : '' ?>>
                        <option value="Select">Select Plate Size</option>
                        <option value="700x945" <?= $ctp_plate == '700x945' ? 'selected' : '' ?>>700 × 945</option>
                        <option value="335x485" <?= $ctp_plate == '335x485' ? 'selected' : '' ?>>335 × 485</option>
                        <option value="560x670" <?= $ctp_plate == '560x670' ? 'selected' : '' ?>>560 × 670</option>
                        <option value="610x890" <?= $ctp_plate == '610x890' ? 'selected' : '' ?>>610 × 890</option>
                        <option value="605x60" <?= $ctp_plate == '605x60' ? 'selected' : '' ?>>605 × 60</option>
                    </select>
                    <input type="number" id="plateQuantity" placeholder="Quantity" value="<?= $ctp_quantity ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    <button type="button" onclick="addPlate()" class="edit-btn" style="margin-left:250px;" <?= $mode === 'view' ? 'disabled' : '' ?>>ADD</button>
                    <div class="container" id="plateList"></div>
                </div>
            </div>
            <div class="customer-container" style="display:flex;gap:20px;justify-content:space-between;">
                <div class="customer-selection">
                    <label><input type="radio" name="customerType" value="Customer" onchange="updateCharges()" <?= $mode === 'view' ? 'disabled' : '' ?> checked> Customer</label>
                    <label><input type="radio" name="customerType" value="Publication" onchange="updateCharges()" <?= $mode === 'view' ? 'disabled' : '' ?>> Publication</label>
                </div>
            </div>
            <h2>Bill Details</h2>
            <div class="customer-container" style="display:flex;gap:20px;justify-content:space-between;">
                <div class="container">
                    <div class="form-group">
                        <label>Paper Charges:</label>
                        <input type="number" name="paper_charges" id="paper_charges" placeholder="Enter Paper Charges" value="<?= $paper_charges ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Plating Charges:</label>
                        <input type="number" name="plating_charges" placeholder="Enter Plating Charges" value="<?= $plating_charges ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Lamination Charges:</label>
                        <input type="number" name="lamination_charges" placeholder="Enter Lamination Charges" value="<?= $lamination_charges ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label>Pinning Charges:</label>
                        <input type="number" name="pinning_charges" placeholder="Enter Pinning Charges" value="<?= $pinning_charges ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    </div>
                </div>
                <div class="container">
                    <div class="form-group">
                        <label>Binding Charges:</label>
                        <input type="number" name="binding_charges" placeholder="Enter Binding Charges" value="<?= $binding_charges ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label>Finishing Charges:</label>
                        <input type="number" name="finishing_charges" placeholder="Enter Finishing Charges" value="<?= $finishing_charges ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label>Other Charges:</label>
                        <input type="number" name="other_charges" placeholder="Enter Other Charges" value="<?= $other_charges ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label>Discount:</label>
                        <input type="number" name="discount" placeholder="Enter Discount" value="<?= $discount ?>" <?= $mode === 'view' ? 'readonly' : '' ?>>
                    </div>
                </div>
            </div>

            <div class="customer-container" style="display:flex;gap:20px;justify-content:space-evenly;">
                <div class="form-group">
                    <label>Printing Charges:</label>
                    <input type="number" name="printing_charges" id="printing_charges" placeholder="Enter Printing Charges" value="<?= $printing_charges ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Total Charges:</label>
                    <input type="number" name="total_charges" placeholder="Total Charges" value="<?= $total_charges ?>" readonly>
                </div>
            </div>

            <?php if ($mode !== 'view'): ?>
            <div class="customer-container" style="display:flex;justify-content:space-between;">
                <button type="submit" class="job-sheet-btn" onclick="setStatus('Draft')">Save</button>
                <button type="button" class="job-sheet-btn" onclick="confirmFinalize()">Finalize</button>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <!-- Custom dialog for finalize -->
    <div id="finalizeDialog" class="dialog-overlay">
        <div class="dialog-box">
            <p>Are you sure you want to finalize this job sheet?</p>
            <button class="yes-btn" id="finalizeYesBtn">Yes</button>
            <button class="no-btn" id="finalizeNoBtn">No</button>
        </div>
    </div>

    <script>
        var itemsData = <?= json_encode($items) ?>;
        var mode = '<?= $mode ?>';
        window.customerType = '<?= $customer_type ?>';

        function selectCustomer(customer) {
            if (mode !== 'view' && mode !== 'edit') {
                document.getElementById("customer_name").value = customer.customer_name;
                document.getElementById("phone_number").value = customer.phone_number;
                fetchCustomerType(customer.customer_name);
                document.querySelector('.job-sheet-form').scrollIntoView({ behavior: "smooth" });
                updateCustomerDetailsHeader();
            }
        }

        function fetchCustomerType(customerName) {
            let xhr = new XMLHttpRequest();
            xhr.open("POST", "", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    let response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        window.customerType = response.customer_type;
                        updateCharges();
                    }
                }
            };
            xhr.send("customer_name=" + encodeURIComponent(customerName) + "&get_customer_type=true");
        }

        function updateTypeDropdown() {
            if (mode === 'view') return;
            var subcategoryId = document.getElementById("paper").value;
            var typeDropdown = document.getElementById("type");
            var typeContainer = document.getElementById("type-container");

            console.log("Updating type dropdown. Subcategory ID:", subcategoryId);

            typeDropdown.innerHTML = '<option value="">Select Type</option>';
            if (subcategoryId && itemsData[subcategoryId]) {
                itemsData[subcategoryId].forEach(item => {
                    var option = document.createElement("option");
                    option.value = item.id;
                    option.textContent = item.item_name;
                    typeDropdown.appendChild(option);
                });
                typeContainer.style.display = "block";
            } else {
                typeContainer.style.display = "none";
            }
        }

        function fetchSellingPrice() {
            if (mode === 'view') return;
            let subcategoryId = document.getElementById("paper").value;
            let itemId = document.getElementById("type").value;

            console.log("Fetching selling price. Subcategory ID:", subcategoryId, "Item ID:", itemId);

            if (subcategoryId && itemId) {
                let xhr = new XMLHttpRequest();
                xhr.open("POST", "", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                let response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    console.log("Successfully fetched selling price:", response.selling_price);
                                    document.getElementById("selling_price").value = response.selling_price;
                                    calculatePrintingCharges();
                                } else {
                                    console.error("Selling price not found for item ID:", itemId);
                                    alert("Selling price not found! Please ensure the item has a price set.");
                                    document.getElementById("selling_price").value = 0;
                                    calculatePrintingCharges();
                                }
                            } catch (e) {
                                console.error("Failed to parse AJAX response:", xhr.responseText, "Error:", e);
                                alert("Failed to parse selling price response.");
                                document.getElementById("selling_price").value = 0;
                                calculatePrintingCharges();
                            }
                        } else {
                            console.error("Failed to fetch selling price. Status:", xhr.status, "Response:", xhr.responseText);
                            alert("Failed to fetch selling price. Please try again.");
                            document.getElementById("selling_price").value = 0;
                            calculatePrintingCharges();
                        }
                    }
                };
                xhr.onerror = function() {
                    console.error("AJAX error occurred while fetching selling price.");
                    alert("An error occurred while fetching the selling price.");
                    document.getElementById("selling_price").value = 0;
                    calculatePrintingCharges();
                };
                xhr.send("subcategory_id=" + subcategoryId + "&item_id=" + itemId);
            } else {
                console.log("Cannot fetch selling price: Subcategory or item not selected. Subcategory ID:", subcategoryId, "Item ID:", itemId);
                document.getElementById("selling_price").value = 0;
                calculatePrintingCharges();
            }
        }

        function calculatePrintingCharges() {
            let quantity = parseFloat(document.getElementById("quantity").value) || 0;
            let sellingPrice = parseFloat(document.getElementById("selling_price").value) || 0;
            let customerType = document.querySelector('input[name="customerType"]:checked')?.value || 'Customer';

            console.log("Calculating paper charges - Quantity:", quantity, "Selling Price:", sellingPrice, "Customer Type:", customerType);

            // Adjust selling price based on customer type
            let adjustedSellingPrice = sellingPrice;
            if (customerType === "Publication") {
                adjustedSellingPrice = sellingPrice * 0.9; // 10% discount for Publication
                console.log("Adjusted selling price for Publication:", adjustedSellingPrice);
            }

            let printingCharges = quantity * adjustedSellingPrice;
            let paperChargesField = document.getElementById("paper_charges");

            if (paperChargesField) {
                paperChargesField.value = printingCharges.toFixed(2);
                console.log("Updated paper charges field to:", printingCharges.toFixed(2));
                // Force UI update
                setTimeout(() => {
                    paperChargesField.value = printingCharges.toFixed(2);
                    paperChargesField.dispatchEvent(new Event('change'));
                }, 0);
            } else {
                console.error("Paper charges field not found in DOM!");
                alert("Error: Paper charges field not found. Please check the form.");
            }

            calculateTotalCharges();
        }

        function calculateStriking() {
            let quantity = parseFloat(document.getElementById("quantity").value) || 0;
            let printingType = document.getElementById("printingType").value;
            let strikingField = document.getElementById("striking");

            console.log("Calculating striking - Quantity:", quantity, "Printing Type:", printingType);

            if (quantity <= 0) {
                strikingField.value = 0;
                return;
            }

            if (printingType === "Company") {
                strikingField.value = quantity * 2;
            } else {
                strikingField.value = quantity;
            }
            calculatePrintingCharges();
        }

        function calculateStrikingAndUpdate() {
            if (mode === 'view') return;
            console.log("Calculating striking and updating charges...");
            calculateStriking();
            updateCharges();
        }

        function updateMachineOptions() {
            if (mode === 'view') return;
            console.log("Updating machine options...");
            hideAllMachineOptions();
            let selectedMachine = document.querySelector('input[name="machine"]:checked');
            if (selectedMachine) {
                if (selectedMachine.value === "RYOBI") {
                    document.getElementById("ryobi-options").style.display = "block";
                } else if (selectedMachine.value === "Web") {
                    document.getElementById("web-options").style.display = "block";
                    document.getElementById("web-sub-options").style.display = "block";
                } else if (selectedMachine.value === "CTP") {
                    document.querySelector(".ctp-section").style.display = "none";
                    document.getElementById("ctpPlateSection").style.display = "block";
                }
            }
            updateCharges();
        }

        function updateCharges() {
            if (mode === 'view') return;
            let customerType = document.querySelector('input[name="customerType"]:checked')?.value || 'Customer';
            console.log("Customer type changed to:", customerType);

            // Recalculate paper charges based on the current customer type
            let paperSelected = document.getElementById("paper").value;
            let typeSelected = document.getElementById("type").value;
            if (paperSelected && typeSelected) {
                console.log("Paper and type are selected. Fetching selling price before calculating paper charges...");
                fetchSellingPrice();
            } else {
                console.log("Paper or type not selected. Setting selling price to 0 and recalculating.");
                document.getElementById("selling_price").value = 0;
                calculatePrintingCharges();
            }

            // Update printing charges for Publication
            if (customerType === "Publication") {
                console.log("Customer type is Publication. Fetching printing charges...");
                fetchAndCalculate();
            } else {
                console.log("Customer type is not Publication. Setting printing charges to 0.");
                document.getElementById("printing_charges").value = 0;
                calculateTotalCharges();
            }
        }

        function fetchAndCalculate() {
            if (mode === 'view') return;
            calculateStriking();
            let isMember = window.customerType || "<?php echo $customer_type; ?>";
            let striking = parseFloat(document.getElementById("striking").value) || 0;
            let selectedMachine = document.querySelector('input[name="machine"]:checked');

            console.log("Fetching printing charges - Striking:", striking, "Is Member:", isMember);

            if (!selectedMachine) {
                console.log("No machine selected for printing charges calculation.");
                alert("Please select a machine type.");
                return;
            }

            if (!striking || striking <= 0) {
                console.log("Striking value invalid:", striking);
                alert("Please ensure the quantity and printing type are set to calculate striking.");
                return;
            }

            let machineType = selectedMachine.value;
            if (machineType === "RYOBI") {
                let ryobiType = document.querySelector('select[name="ryobi_type"]').value;
                if (ryobiType === "color") {
                    machineType = "RYOBI_COLOR";
                }
            }

            let xhr = new XMLHttpRequest();
            xhr.open("GET", "printing_charges.php?action=fetch_pricing", true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            let pricingData = JSON.parse(xhr.responseText);
                            let charges = 0;

                            if (pricingData[machineType]) {
                                let pricing = pricingData[machineType];
                                let rate = isMember === "member" ? pricing.member_rate : pricing.non_member_rate;
                                charges = striking * rate;
                                console.log("Calculated printing charges:", charges, "for machine:", machineType);
                            } else {
                                console.error("Pricing data not found for machine type:", machineType);
                                alert("Pricing data not found for the selected machine type.");
                            }

                            document.getElementById("printing_charges").value = charges.toFixed(2);
                            calculateTotalCharges();
                        } catch (e) {
                            console.error("Failed to parse printing charges response:", xhr.responseText, "Error:", e);
                            alert("Failed to parse printing charges response.");
                        }
                    } else {
                        console.error("Failed to fetch printing charges. Status:", xhr.status, "Response:", xhr.responseText);
                        alert("Failed to fetch pricing data. Please try again.");
                    }
                }
            };
            xhr.onerror = function() {
                console.error("AJAX error occurred while fetching printing charges.");
                alert("An error occurred while fetching pricing data.");
            };
            xhr.send();
        }

        function hideAllMachineOptions() {
            document.getElementById("ryobi-options").style.display = "none";
            document.getElementById("web-options").style.display = "none";
            document.getElementById("web-sub-options").style.display = "none";
            document.getElementById("ctpPlateSection").style.display = "none";
            document.querySelector(".ctp-section").style.display = "block";
        }

        function calculateTotalCharges() {
            let paperCharges = parseFloat(document.getElementById("paper_charges").value) || 0;
            let platingCharges = parseFloat(document.querySelector('input[name="plating_charges"]').value) || 0;
            let printingCharges = parseFloat(document.getElementById("printing_charges").value) || 0;
            let laminationCharges = parseFloat(document.querySelector('input[name="lamination_charges"]').value) || 0;
            let pinningCharges = parseFloat(document.querySelector('input[name="pinning_charges"]').value) || 0;
            let bindingCharges = parseFloat(document.querySelector('input[name="binding_charges"]').value) || 0;
            let finishingCharges = parseFloat(document.querySelector('input[name="finishing_charges"]').value) || 0;
            let otherCharges = parseFloat(document.querySelector('input[name="other_charges"]').value) || 0;
            let discount = parseFloat(document.querySelector('input[name="discount"]').value) || 0;

            let total = paperCharges + platingCharges + printingCharges + laminationCharges + 
                       pinningCharges + bindingCharges + finishingCharges + otherCharges - discount;

            document.querySelector('input[name="total_charges"]').value = total.toFixed(2);
            console.log("Total charges updated:", total.toFixed(2));
        }

        function addPlate() {
            if (mode === 'view') return;
            let plateSize = document.getElementById("plateSize").value;
            let quantity = document.getElementById("plateQuantity").value;
            if (plateSize !== "Select" && quantity > 0) {
                let plateList = document.getElementById("plateList");
                let plateDiv = document.createElement("div");
                plateDiv.innerHTML = `${plateSize} - Quantity: ${quantity} <button type="button" onclick="removePlate(this)">Remove</button>`;
                plateList.appendChild(plateDiv);
                
                document.getElementById("ctpQuantity").value = quantity;
                let radio = document.querySelector(`input[name="ctpPlate"][value="${plateSize}"]`);
                if (radio) radio.checked = true;
                
                calculatePlatingCharges();
            }
        }

        function removePlate(button) {
            if (mode === 'view') return;
            button.parentElement.remove();
            calculatePlatingCharges();
        }

        function calculatePlatingCharges() {
            let plateList = document.getElementById("plateList").children;
            let totalPlatingCharges = 0;
            const platePrices = {
                '700x945': 350,
                '335x485': 150,
                '560x670': 250,
                '610x890': 300,
                '605x60': 200
            };

            for (let plate of plateList) {
                let [plateSize, quantityText] = plate.textContent.split(" - Quantity: ");
                let quantity = parseInt(quantityText.split(" ")[0]);
                if (platePrices[plateSize]) {
                    totalPlatingCharges += platePrices[plateSize] * quantity;
                }
            }

            let ctpSection = document.querySelector(".ctp-section");
            if (ctpSection.style.display !== "none") {
                let ctpPlate = document.querySelector('input[name="ctpPlate"]:checked');
                let ctpQuantity = parseInt(document.getElementById("ctpQuantity").value) || 0;
                if (ctpPlate && ctpQuantity > 0) {
                    totalPlatingCharges += platePrices[ctpPlate.value] * ctpQuantity;
                }
            }

            document.querySelector('input[name="plating_charges"]').value = totalPlatingCharges.toFixed(2);
            calculateTotalCharges();
        }

        function setStatus(status) {
            if (mode === 'view') return;
            document.getElementById("status").value = status;
        }

        function confirmFinalize() {
            if (mode === 'view') return;
            document.getElementById("finalizeDialog").style.display = "flex";
        }

        function updateCustomerDetailsHeader() {
            let customerName = document.getElementById("customer_name").value || "N/A";
            let phoneNumber = document.getElementById("phone_number").value || "N/A";
            let jobName = document.getElementsByName("job_name")[0].value || "N/A";
            document.getElementById("customerDetailsHeader").textContent = 
                `Customer Details: ${customerName} - ${phoneNumber} - ${jobName}`;
        }

        document.addEventListener("DOMContentLoaded", function() {
            console.log("Page loaded. Mode:", mode);

            if (mode !== 'view') {
                document.getElementById("paper").addEventListener("change", function() {
                    console.log("Paper selection changed.");
                    updateTypeDropdown();
                });
                document.getElementById("type").addEventListener("change", function() {
                    console.log("Type selection changed.");
                    fetchSellingPrice();
                });
                document.getElementById("quantity").addEventListener("input", function() {
                    console.log("Quantity changed.");
                    calculateStrikingAndUpdate();
                });
                document.getElementById("printingType").addEventListener("change", function() {
                    console.log("Printing type changed.");
                    calculateStrikingAndUpdate();
                });
                document.querySelectorAll('input[name="machine"]').forEach(input => {
                    input.addEventListener("change", function() {
                        console.log("Machine selection changed.");
                        updateMachineOptions();
                    });
                });
                document.querySelectorAll('input[name="customerType"]').forEach(input => {
                    input.addEventListener("change", function() {
                        console.log("Customer type radio button changed.");
                        updateCharges();
                    });
                });
                document.querySelectorAll('input[name="lamination_charges"], input[name="pinning_charges"], input[name="binding_charges"], input[name="finishing_charges"], input[name="other_charges"], input[name="discount"]').forEach(input => {
                    input.addEventListener('input', function() {
                        console.log("Additional charges or discount changed.");
                        calculateTotalCharges();
                    });
                });

                // Add event listeners for dynamic customer details header
                document.getElementById("customer_name").addEventListener("input", updateCustomerDetailsHeader);
                document.getElementById("phone_number").addEventListener("input", updateCustomerDetailsHeader);
                document.getElementsByName("job_name")[0].addEventListener("input", updateCustomerDetailsHeader);
            }

            // Initial calculations
            console.log("Performing initial calculations...");
            updateMachineOptions();
            calculateStriking();

            // If a paper type is pre-selected, fetch the selling price
            let paperSelected = document.getElementById("paper").value;
            let typeSelected = document.getElementById("type").value;
            if (paperSelected && typeSelected) {
                console.log("Paper and type pre-selected on load. Fetching selling price...");
                fetchSellingPrice();
            } else {
                console.log("No paper or type selected on load. Paper:", paperSelected, "Type:", typeSelected);
                document.getElementById("selling_price").value = 0;
                calculatePrintingCharges();
                calculateTotalCharges();
            }

            // Initial update of the Customer Details header
            updateCustomerDetailsHeader();

            // Finalize dialog event listeners
            document.getElementById("finalizeYesBtn").addEventListener("click", function() {
                setStatus("Finalized");
                document.getElementById("jobForm").submit();
            });

            document.getElementById("finalizeNoBtn").addEventListener("click", function() {
                document.getElementById("finalizeDialog").style.display = "none";
            });

            document.getElementById("jobForm").addEventListener("submit", function(event) {
                let machine = document.querySelector('input[name="machine"]:checked');
                if (!machine) {
                    alert("Please select a machine type.");
                    event.preventDefault();
                }
            });
        });
    </script>
</body>
</html>