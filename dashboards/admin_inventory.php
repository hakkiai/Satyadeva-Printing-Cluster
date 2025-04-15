<?php
session_start(); // Start the session

include '../database/db_connect.php'; // Database connection

// Initialize the invoice number in the session if it doesn't exist
if (!isset($_SESSION['current_invoice_number'])) {
    $stmt = $conn->prepare("SELECT MAX(invoice_number) AS max_invoice FROM invoice");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $_SESSION['current_invoice_number'] = $row['max_invoice'] ? $row['max_invoice'] + 1 : 1;
}

// Fetch the current invoice number for display
$current_invoice_number = $_SESSION['current_invoice_number'];

// Handle AJAX request for inventory data
if (isset($_GET['inventory_id'])) {
    $inventory_id = intval($_GET['inventory_id']);
    $stmt = $conn->prepare("SELECT inv.*, v.vendor_name, i.subcategory_id, s.category_id 
        FROM inventory inv 
        JOIN vendors v ON inv.vendor_id = v.id 
        JOIN inventory_items i ON inv.item_id = i.id 
        JOIN inventory_subcategories s ON i.subcategory_id = s.id 
        WHERE inv.id = ?");
    $stmt->bind_param("i", $inventory_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode([
            'vendor_name' => $row['vendor_name'],
            'invoice_number' => $row['invoice_number'],
            'category_id' => $row['category_id'],
            'subcategory_id' => $row['subcategory_id'],
            'item_id' => $row['item_id'],
            'quantity' => $row['quantity'],
            'price_per_unit' => $row['price_per_unit']
        ]);
    } else {
        echo json_encode(['error' => 'Inventory not found']);
    }
    exit;
}

// Fetch subcategories dynamically
if (isset($_GET['category_id'])) {
    $category_id = intval($_GET['category_id']);
    $stmt = $conn->prepare("SELECT id, subcategory_name FROM inventory_subcategories WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<option value=''>Select Subcategory</option>";
    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row['id']}'>{$row['subcategory_name']}</option>";
    }
    exit;
}

// Fetch items dynamically
if (isset($_GET['subcategory_id'])) {
    $subcategory_id = intval($_GET['subcategory_id']);
    $stmt = $conn->prepare("SELECT id, item_name FROM inventory_items WHERE subcategory_id = ?");
    $stmt->bind_param("i", $subcategory_id);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<option value=''>Select Item</option>";
    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row['id']}'>{$row['item_name']}</option>";
    }
    exit;
}

// Handle inventory update form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $vendor_name = $_POST['vendor_name'];
    $invoice_number = $_POST['invoice_number'];
    $inventory_id = isset($_POST['inventory_id']) && !empty($_POST['inventory_id']) ? intval($_POST['inventory_id']) : null;

    if (empty($vendor_name) || empty($invoice_number)) {
        die("<script>alert('Vendor name and invoice number are required!'); window.history.back();</script>");
    }

    // Get vendor_id
    $stmt = $conn->prepare("SELECT id FROM vendors WHERE vendor_name = ?");
    $stmt->bind_param("s", $vendor_name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $vendor_id = $row['id'];
    } else {
        die("<script>alert('Vendor not found.'); window.history.back();</script>");
    }
    $stmt->close();

    // Check if the invoice_number exists in the invoice table
    $stmt = $conn->prepare("SELECT invoice_number FROM invoice WHERE invoice_number = ?");
    $stmt->bind_param("i", $invoice_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO invoice (invoice_number) VALUES (?)");
        $stmt->bind_param("i", $invoice_number);
        if (!$stmt->execute()) {
            die("<script>alert('Error creating invoice: " . $stmt->error . "');</script>");
        }
    }
    $stmt->close();

    // Process items
    if (isset($_POST['item']) && is_array($_POST['item'])) {
        foreach ($_POST['item'] as $index => $item_id) {
            $category_id = $_POST['category'][$index];
            $subcategory_id = $_POST['subcategory'][$index];
            $quantity = $_POST['quantity'][$index];
            $price = $_POST['price'][$index];

            if (empty($item_id) || empty($quantity) || empty($price)) {
                die("<script>alert('All fields are required for each item!'); window.history.back();</script>");
            }

            if ($inventory_id) {
                $stmt = $conn->prepare("UPDATE inventory SET vendor_id = ?, item_id = ?, quantity = ?, price_per_unit = ?, invoice_number = ? WHERE id = ?");
                $stmt->bind_param("iiidii", $vendor_id, $item_id, $quantity, $price, $invoice_number, $inventory_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO inventory (vendor_id, item_id, quantity, price_per_unit, invoice_number) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiidi", $vendor_id, $item_id, $quantity, $price, $invoice_number);
            }

            if (!$stmt->execute()) {
                die("<script>alert('Error saving inventory: " . $stmt->error . "');</script>");
            }
            $stmt->close();
        }
    }

    echo "<script>
            alert('Inventory saved successfully!');
            window.location.href = 'admin_inventory.php?invoice_number=$invoice_number';
          </script>";
    exit;
}

// Handle inventory deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        echo "<script>alert('Inventory deleted successfully!'); window.location.href='admin_inventory.php';</script>";
    } else {
        echo "<script>alert('Error deleting inventory: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Inventory</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .invoice-number-container {
            display: flex;
            align-items: center;
        }
        #invoice_number {
            margin-right: 10px;
        }
        .item-row {
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #ddd;
        }
        .edit-btn, .delete-btn {
            padding: 5px 10px;
            font-size: 14px;
            cursor: pointer;
            border: none;
            border-radius: 4px;
        }
        .edit-btn {
            background-color: #28a745;
            color: white;
        }
        .delete-btn {
            background-color: #dc3545;
            color: white;
        }
        .edit-btn:hover, .delete-btn:hover {
            opacity: 0.8;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Admin Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='add_vendor.php'">Add Vendor</button>
            <button onclick="location.href='add_edit_customers.php'">Add/Edit Customers</button>
            <button onclick="location.href='admin_inventory.php'">Inventory</button>
            <button onclick="location.href='sales.php'">Sales</button>
            <button onclick="location.href='printing_charges.php'">Printing Charges</button>
            <button onclick="location.href='reports.php'">Reports</button> <!-- New Button -->
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="container">
        <h2>Inventory Management</h2>
        <h3 style="padding-left:20px;">Select Vendor</h3>
        <form method="GET">
            <input type="text" name="search_query" class="search-input" placeholder="Search Vendor...">
            <button type="submit" class="search-btn">Search</button>
        </form>
    </div>

    <?php if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search_query'])): ?>
    <div class="container">
        <?php
        $search = $conn->real_escape_string($_GET['search_query']);
        $sql = "SELECT * FROM vendors WHERE vendor_name LIKE ?";
        $stmt = $conn->prepare($sql);
        $search_param = "%$search%";
        $stmt->bind_param("s", $search_param);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $vendorName = htmlspecialchars($row['vendor_name']);
                echo "<div class='vendor-card' id='vendor-" . $row['id'] . "'>
                    <strong class='vendor-name'>$vendorName</strong>
                    <div class='vendor-actions'>
                        <button class='search-btn' onclick='openInventoryForm(\"$vendorName\")'>Select</button>
                    </div>
                    <p>Phone: <span>" . htmlspecialchars($row['phone_number']) . "</span></p>
                    <p>GST: <span>" . htmlspecialchars($row['gst_number']) . "</span></p>
                </div>";
            }
        } else {
            echo "<p>No vendors found.</p>";
        }
        $stmt->close();
        ?>
    </div>
    <?php endif; ?>

    <div id="inventoryFormContainer" class="container" style="display: none;">
        <h2>Add Items for <span id="vendorName"></span></h2>
        <form method="POST" class="form" id="inventoryForm">
            <input type="hidden" id="vendor_name" name="vendor_name">
            <input type="hidden" id="inventory_id" name="inventory_id">
            <div class="invoice-details">
                <label>Invoice Number:</label>
                <div class="invoice-number-container">
                    <input type="text" id="invoice_number" name="invoice_number" value="<?php echo $current_invoice_number; ?>" required>
                </div>
            </div>
            <div id="itemContainer">
                <!-- Item rows will be dynamically added here -->
            </div>
            <button type="button" id="addItemButton">Add Another Item</button>
            <button type="submit" id="inventorySubmitButton">Save Inventory</button>
        </form>
    </div>

    <div class="container" style="width:70%;">
        <h2>Current Inventory</h2>
        <table>
            <tr>
                <th>Invoice Number</th>
                <th>Vendor</th>
                <th>Category</th>
                <th>Subcategory</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Date</th>
                <th>Action</th>
            </tr>
            <?php
            $sql = "SELECT v.vendor_name, c.category_name, s.subcategory_name, i.item_name, inv.quantity, inv.price_per_unit, inv.invoice_number, inv.created_at, inv.id
                    FROM inventory inv
                    JOIN vendors v ON inv.vendor_id = v.id
                    JOIN inventory_items i ON inv.item_id = i.id
                    JOIN inventory_subcategories s ON i.subcategory_id = s.id
                    JOIN inventory_categories c ON s.category_id = c.id
                    ORDER BY inv.created_at DESC";
            $result = $conn->query($sql);
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                            <td>{$row['invoice_number']}</td>
                            <td>{$row['vendor_name']}</td>
                            <td>{$row['category_name']}</td>
                            <td>{$row['subcategory_name']}</td>
                            <td>{$row['item_name']}</td>
                            <td>{$row['quantity']}</td>
                            <td>₹{$row['price_per_unit']}</td>
                            <td>{$row['created_at']}</td>
                            <td>
                                <button class='edit-btn' onclick='editInventory({$row['id']})'>Edit</button>
                                <button class='delete-btn' onclick='deleteInventory({$row['id']})'>Delete</button>
                            </td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='9'>No inventory records found.</td></tr>";
            }
            ?>
        </table>
    </div>

    <script>
        let itemCounter = 0;

        function loadSubcategories(categoryId, itemIndex) {
            $.ajax({
                url: 'admin_inventory.php?category_id=' + categoryId,
                method: 'GET',
                success: function(response) {
                    $('#subcategoryDropdown' + itemIndex).html(response);
                }
            });
        }

        function loadItems(subcategoryId, itemIndex) {
            $.ajax({
                url: 'admin_inventory.php?subcategory_id=' + subcategoryId,
                method: 'GET',
                success: function(response) {
                    $('#itemDropdown' + itemIndex).html(response);
                }
            });
        }

        function addItemRow(categoryId = '', subcategoryId = '', itemId = '', quantity = '', price = '') {
            const itemIndex = itemCounter++;
            const itemRow = `
                <div class="item-row" id="itemRow${itemIndex}">
                    <div class="form-group">
                        <label>Category:</label>
                        <select id="categoryDropdown${itemIndex}" name="category[]" onchange="loadSubcategories(this.value, ${itemIndex})" required>
                            <option value="">Select Category</option>
                            <?php
                            $result = $conn->query("SELECT id, category_name FROM inventory_categories");
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value='{$row['id']}'>{$row['category_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subcategory:</label>
                        <select id="subcategoryDropdown${itemIndex}" name="subcategory[]" onchange="loadItems(this.value, ${itemIndex})" required>
                            <option value="">Select Subcategory</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Item:</label>
                        <select id="itemDropdown${itemIndex}" name="item[]" required>
                            <option value="">Select Item</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantity:</label>
                        <input type="number" name="quantity[]" min="1" value="${quantity}" required>
                    </div>
                    <div class="form-group">
                        <label>Price per unit:</label>
                        <input type="number" name="price[]" min="0" step="0.01" value="${price}" required>
                    </div>
                    <button type="button" onclick="removeItemRow(${itemIndex})">Remove Item</button>
                </div>
            `;
            $('#itemContainer').append(itemRow);
            
            if (categoryId) {
                $('#categoryDropdown' + itemIndex).val(categoryId).trigger('change');
                setTimeout(() => {
                    if (subcategoryId) {
                        $('#subcategoryDropdown' + itemIndex).val(subcategoryId).trigger('change');
                        setTimeout(() => {
                            if (itemId) {
                                $('#itemDropdown' + itemIndex).val(itemId);
                            }
                        }, 100);
                    }
                }, 100);
            }
        }

        function removeItemRow(itemIndex) {
            $('#itemRow' + itemIndex).remove();
        }

        function openInventoryForm(vendorName) {
            $("#inventoryFormContainer").show();
            $("#vendorName").text(vendorName);
            $("#vendor_name").val(vendorName);
            $('#inventorySubmitButton').text('Save Inventory');
            $('#inventory_id').val('');
            $('#itemContainer').empty();
            $('#invoice_number').val('<?php echo $current_invoice_number; ?>');
            addItemRow();
        }

        function editInventory(inventoryId) {
            $.ajax({
                url: 'admin_inventory.php',
                method: 'GET',
                data: { inventory_id: inventoryId },
                dataType: 'json',
                success: function(data) {
                    if (data.error) {
                        alert(data.error);
                    } else {
                        $("#inventoryFormContainer").show();
                        $("#vendorName").text(data.vendor_name);
                        $("#vendor_name").val(data.vendor_name);
                        $('#invoice_number').val(data.invoice_number);
                        $('#inventory_id').val(inventoryId);
                        $('#itemContainer').empty();
                        
                        addItemRow(
                            data.category_id,
                            data.subcategory_id,
                            data.item_id,
                            data.quantity,
                            data.price_per_unit
                        );
                        
                        $('#inventorySubmitButton').text('Update Inventory');
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error fetching inventory data: ' + error);
                }
            });
        }

        function deleteInventory(inventoryId) {
            if (confirm('Are you sure you want to delete this inventory record?')) {
                window.location.href = 'admin_inventory.php?delete_id=' + inventoryId;
            }
        }

        $(document).ready(function() {
            $('#addItemButton').click(function() {
                addItemRow();
            });

            const urlParams = new URLSearchParams(window.location.search);
            const invoiceNumber = urlParams.get('invoice_number');
            if (invoiceNumber) {
                $('#invoice_number').val(invoiceNumber);
            }
        });
    </script>
</body>
</html>