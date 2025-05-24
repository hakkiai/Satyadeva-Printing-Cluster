<!-- /*stock_utilization.php*/ -->
<?php
session_start();
include '../database/db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch utilization records
$query = "SELECT 
            su.id, 
            su.item_name, 
            su.quantity_used, 
            su.unit, 
            su.utilization_date,
            su.user,
            d.department_name,
            c.customer_name
          FROM stock_utilization su
          LEFT JOIN departments d ON su.department_id = d.department_id
          LEFT JOIN customers c ON su.customer_id = c.id
          ORDER BY su.utilization_date DESC";
$utilizations = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Fetch initial data
$departments   = $conn->query("SELECT department_id, department_name FROM departments")->fetch_all(MYSQLI_ASSOC);
$categories    = $conn->query("SELECT id AS category_id, category_name FROM inventory_categories")->fetch_all(MYSQLI_ASSOC);
$subcategories = [];
$items         = [];
$customer_results = [];
$selected_customer = null;
$success_message   = '';
$error_message     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_utilization'])) {
        try {
            $department_id = (int)$_POST['department'];
            $item_id       = (int)$_POST['item'];
            $quantity      = (float)$_POST['quantity'];
            $customer_id   = isset($_POST['customer_id']) && $_POST['customer_id'] !== '' ? (int)$_POST['customer_id'] : null;
            $user          = $_SESSION['user']['username'] ?? 'admin';

            if (!$department_id) throw new Exception("Select a department");
            if (!$item_id) throw new Exception("Select an item");
            if ($quantity <= 0) throw new Exception("Enter valid quantity");

            $dept_check = $conn->prepare("SELECT department_name FROM departments WHERE department_id = ?");
            $dept_check->bind_param("i", $department_id);
            $dept_check->execute();
            $dept_row = $dept_check->get_result()->fetch_assoc();
            $dept_name = $dept_row['department_name'] ?? '';

            if ($dept_name === 'Others' && !$customer_id) {
                throw new Exception("Select a customer for Others");
            }

            $item_check = $conn->prepare("SELECT item_name, balance, unit FROM inventory_items_copy WHERE id = ? AND active_status = 1 FOR UPDATE");
            $item_check->bind_param("i", $item_id);
            $item_check->execute();
            $item = $item_check->get_result()->fetch_assoc();
            
            if (!$item) throw new Exception("Item not found");
            if ($item['balance'] < $quantity) throw new Exception("Insufficient stock: " . $item['balance'] . " " . $item['unit']);

            $conn->begin_transaction();

            $update_stmt = $conn->prepare("UPDATE inventory_items_copy SET utilised_quantity = utilised_quantity + ? WHERE id = ?");
            $update_stmt->bind_param("di", $quantity, $item_id);
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update inventory: " . $conn->error);
            }

            $insert_stmt = $conn->prepare("INSERT INTO stock_utilization 
                (user, item_id, item_name, quantity_used, unit, department_id, customer_id, utilization_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $insert_stmt->bind_param("sisdsii", $user, $item_id, $item['item_name'], $quantity, $item['unit'], $department_id, $customer_id);
            if (!$insert_stmt->execute()) {
                throw new Exception("Failed to insert utilization: " . $conn->error);
            }

            $conn->commit();
            $success_message = "Utilization saved successfully";
            $_POST = [];
            $subcategories = [];
            $items = [];
            $selected_customer = null;

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
            error_log("Error: " . $e->getMessage());
        }
    } elseif (isset($_POST['add_customer'])) {
        $data = array_map('trim', $_POST);
        $is_member = isset($data['is_member']) && $data['is_member'] === 'member' ? 'member' : 'non-member';
        
        $stmt = $conn->prepare("INSERT INTO customers (customer_name, firm_name, firm_location, gst_number, email, phone_number, address, is_member) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", 
            $data['name'], $data['firm'], $data['location'], $data['gst'], 
            $data['email'], $data['phone'], $data['address'], $is_member);
        
        if ($stmt->execute()) {
            $success_message = "Customer added successfully!";
            $selected_customer = [
                'id' => $conn->insert_id,
                'customer_name' => $data['name']
            ];
            $_POST['customer_id'] = $selected_customer['id'];
        } else {
            $error_message = "Error adding customer: " . $stmt->error;
        }
    }

    // Load dependent dropdowns only if not adding customer
    if (!empty($_POST['category']) && !isset($_POST['add_customer'])) {
        $stmt = $conn->prepare("SELECT id AS subcategory_id, subcategory_name FROM inventory_subcategories WHERE category_id = ?");
        $stmt->bind_param("i", $_POST['category']);
        $stmt->execute();
        $subcategories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    if (!empty($_POST['subcategory']) && !isset($_POST['add_customer'])) {
        $stmt = $conn->prepare("SELECT id, item_name, balance, unit FROM inventory_items_copy WHERE subcategory_id = ? AND active_status = 1");
        $stmt->bind_param("i", $_POST['subcategory']);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    if (!empty($_POST['customer_search']) && !isset($_POST['add_customer'])) {
        $term = "%" . $conn->real_escape_string($_POST['customer_search']) . "%";
        $stmt = $conn->prepare("SELECT id, customer_name FROM customers WHERE customer_name LIKE ? LIMIT 10");
        $stmt->bind_param("s", $term);
        $stmt->execute();
        $customer_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Utilization</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .form-group { margin: 15px 0; }
        .form-control { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .success { color: green; font-weight: bold; margin: 10px 0; }
        .error { color: red; font-weight: bold; margin: 10px 0; }
        .hidden { display: none; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px 15px; border: 1px solid #ddd; text-align: left; }
        th { background: #f2f2f2; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
        .btn { padding: 8px 15px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #45a049; }
        #customer_results { max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; margin-top: 5px; }
        .customer-result { padding: 8px; border-bottom: 1px solid #eee; }
        .records-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .search-box { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .container { padding: 20px; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Admin Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='add_vendor.php'">Add Vendor</button>
            <button onclick="location.href='add_edit_customers.php'">Add/Edit Customers</button>
            <button onclick="location.href='admin_inventory.php'">Inventory</button>
            <button onclick="location.href='stock_utilization.php'">Stock Utilization</button>
            <button onclick="location.href='sales.php'">Sales</button>
            <button onclick="location.href='printing_charges.php'">Printing Charges</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>
    <h2>Stock Utilization</h2>
    <div class="container">
        <form method="POST" id="utilizationForm">
            <div class="form-group">
                <label>Department:</label>
                <select name="department" class="form-control" onchange="this.form.submit()" required>
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['department_id'] ?>"
                            <?= isset($_POST['department']) && $_POST['department'] == $dept['department_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['department_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php 
            $selected_dept = isset($_POST['department']) ? array_filter($departments, fn($d) => $d['department_id'] == $_POST['department']) : [];
            $selected_dept = !empty($selected_dept) ? reset($selected_dept) : null;
            if ($selected_dept && $selected_dept['department_name'] === 'Others'): 
            ?>
                <div class="form-group">
                    <label>Customer:</label>
                    <input type="text" name="customer_search" class="form-control"
                        value="<?= htmlspecialchars($_POST['customer_search'] ?? '') ?>"
                        onchange="this.form.submit()" placeholder="Search customers">
                    
                    <?php if (!empty($customer_results)): ?>
                        <div id="customer_results">
                            <?php foreach ($customer_results as $c): ?>
                                <div class="customer-result">
                                    <input type="radio" name="customer_radio" value="<?= $c['id'] ?>"
                                        <?= isset($_POST['customer_id']) && $_POST['customer_id'] == $c['id'] ? 'checked' : '' ?>
                                        onclick="document.getElementById('customer_id').value = this.value;">
                                    <?= htmlspecialchars($c['customer_name']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (!empty($_POST['customer_search'])): ?>
                        <div id="customer_results">
                            <div class="customer-result">
                                <em>No customer found.</em>
                                <button type="button" class="btn" onclick="document.getElementById('addCustomerForm').classList.remove('hidden')">Add Customer</button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="customer_id" id="customer_id"
                        value="<?= htmlspecialchars($_POST['customer_id'] ?? '') ?>">
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Category:</label>
                <select name="category" class="form-control" onchange="this.form.submit()" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['category_id'] ?>"
                            <?= isset($_POST['category']) && $_POST['category'] == $cat['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Subcategory:</label>
                <select name="subcategory" class="form-control" onchange="this.form.submit()" required>
                    <option value="">Select Subcategory</option>
                    <?php foreach ($subcategories as $sub): ?>
                        <option value="<?= $sub['subcategory_id'] ?>"
                            <?= isset($_POST['subcategory']) && $_POST['subcategory'] == $sub['subcategory_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sub['subcategory_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Item:</label>
                <select name="item" class="form-control" required>
                    <option value="">Select Item</option>
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $item): ?>
                            <option value="<?= $item['id'] ?>"
                                <?= isset($_POST['item']) && $_POST['item'] == $item['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($item['item_name']) ?> (Balance: <?= $item['balance'] ?> <?= $item['unit'] ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Quantity:</label>
                <input type="number" name="quantity" class="form-control" step="0.01" min="0.01"
                    value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>" required>
            </div>

            <button type="submit" name="save_utilization" class="btn" style="margin-left:290px;">Save</button>
        </form>
        <div class="container">
        <!-- Separate form for adding customers -->
        <?php if ($selected_dept && $selected_dept['department_name'] === 'Others'): ?>
            <form method="POST" id="addCustomerForm" class="hidden" action="">
                <div id="add_customer_form">
                    <h4>Add New Customer</h4>
                    <div class="form-group">
                        <label>Name:</label>
                        <input type="text" name="name" class="form-control" required>
                        <label>Firm Name:</label>
                        <input type="text" name="firm" class="form-control" required>
                        <label>Location:</label>
                        <input type="text" name="location" class="form-control" required>
                        <label>GST Number:</label>
                        <input type="text" name="gst" class="form-control" required>
                        <label>Email:</label>
                        <input type="email" name="email" class="form-control">
                        <label>Phone:</label>
                        <input type="text" name="phone" class="form-control" required>
                        <label>Address:</label>
                        <textarea name="address" class="form-control"></textarea>
                        <label>Member Status:</label>
                        <select name="is_member" class="form-control">
                            <option value="non-member">Non-Member</option>
                            <option value="member">Member</option>
                        </select>
                    </div>
                    <!-- Preserve main form state -->
                    <input type="hidden" name="department" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">
                    <input type="hidden" name="subcategory" value="<?= htmlspecialchars($_POST['subcategory'] ?? '') ?>">
                    <input type="hidden" name="item" value="<?= htmlspecialchars($_POST['item'] ?? '') ?>">
                    <input type="hidden" name="quantity" value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>">
                    <button type="submit" name="add_customer" class="btn">Save Customer</button>
                    <button type="button" class="btn" style="background: #f44336;" onclick="document.getElementById('addCustomerForm').classList.add('hidden')">Cancel</button>
                </div>
            </form>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        </div>
    </div>
    <div class="container">
        <div class="records-header">
            <h3>Records</h3>
            <input type="text" id="searchInput" class="search-box" placeholder="Search records..." onkeyup="searchTable()">
        </div>
        <?php if (!empty($utilizations)): ?>
            <table id="utilizationTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Item</th>
                        <th>Dept/Customer</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($utilizations as $record): ?>
                        <tr>
                            <td><?= htmlspecialchars($record['id']) ?></td>
                            <td><?= htmlspecialchars($record['item_name']) ?></td>
                            <td>
                                <?php 
                                if ($record['department_name'] === 'Others' && $record['customer_name']) {
                                    echo htmlspecialchars($record['customer_name']);
                                } else {
                                    echo htmlspecialchars($record['department_name']);
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($record['quantity_used']) ?></td>
                            <td><?= htmlspecialchars($record['unit']) ?></td>
                            <td><?= date('d M Y H:i', strtotime($record['utilization_date'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No records</p>
        <?php endif; ?>
    </div>
    <script>
        document.getElementById('utilizationForm').addEventListener('submit', function(e) {
            if (e.submitter && e.submitter.name === 'save_utilization') {
                const dept = document.querySelector('[name="department"]').value;
                const cat  = document.querySelector('[name="category"]').value;
                const subcat = document.querySelector('[name="subcategory"]').value;
                const item = document.querySelector('[name="item"]').value;
                const qty  = document.querySelector('[name="quantity"]').value;
                const customer = document.getElementById('customer_id') ? document.getElementById('customer_id').value : '';

                console.log('Validation - customer:', customer);

                if (!dept) {
                    alert('Select department');
                    e.preventDefault();
                    return;
                }
                
                const deptSelect = document.querySelector('[name="department"]');
                const selectedOption = deptSelect.options[deptSelect.selectedIndex];
                if (selectedOption.text === 'Others' && (!customer || customer === '')) {
                    alert('Select customer for Others department');
                    e.preventDefault();
                    return;
                }
                
                if (!cat) {
                    alert('Select category');
                    e.preventDefault();
                    return;
                }
                if (!subcat) {
                    alert('Select subcategory');
                    e.preventDefault();
                    return;
                }
                if (!item) {
                    alert('Select item');
                    e.preventDefault();
                    return;
                }
                if (!qty || qty <= 0) {
                    alert('Enter valid quantity');
                    e.preventDefault();
                    return;
                }
            }
        });

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('utilizationTable');
            const tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) { // Start at 1 to skip header row
                let found = false;
                const td = tr[i].getElementsByTagName('td');
                for (let j = 0; j < td.length; j++) {
                    const cell = td[j];
                    if (cell) {
                        const txtValue = cell.textContent || cell.innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? '' : 'none';
            }
        }
    </script>
</body>
</html>