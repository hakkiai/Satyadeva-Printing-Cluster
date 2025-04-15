<?php
include '../database/db_connect.php'; // Database connection

// Fetch Categories
$categoryQuery = "SELECT id, category_name FROM inventory_categories";
$categoryResult = mysqli_query($conn, $categoryQuery);

// Fetch Subcategories Based on Category Selection
$subcategories = [];
if (isset($_POST['category'])) {
    $category_id = $_POST['category'];
    $stmt = $conn->prepare("SELECT id, subcategory_name FROM inventory_subcategories WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $subcategories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fetch Items Based on Subcategory Selection
$items = [];
if (isset($_POST['sub_category'])) {
    $subcategory_id = $_POST['sub_category'];
    $stmt = $conn->prepare("SELECT id, item_name FROM inventory_items WHERE subcategory_id = ?");
    $stmt->bind_param("i", $subcategory_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Handle Form Submission to Insert into sales_prices Table
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save'])) {
    $item_id = $_POST['item'];
    $selling_price = $_POST['price'];
    $quantity_per_unit = $_POST['quantity'];
    $unit_type = $_POST['unit'];

    // Insert Data into sales_prices Table
    $stmt = $conn->prepare("INSERT INTO sales_prices (item_id, selling_price, quantity_per_unit, unit_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("idis", $item_id, $selling_price, $quantity_per_unit, $unit_type);
    
    if ($stmt->execute()) {
        echo "<script>alert('Sales price added successfully!');</script>";
    } else {
        echo "<script>alert('Error occurred during adding!');</script>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Charges Management</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="navbar">
        <div class="brand">Admin Dashboard</div>
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
        <h2>Sales Charges Management</h2>
        <form method="POST" action="sales.php" class="form">
            <div class="form-group">
                <label>Category:</label>
                <select name="category" onchange="this.form.submit()">
                    <option value="">Select Category</option>
                    <?php while ($row = mysqli_fetch_assoc($categoryResult)) { ?>
                        <option value="<?= $row['id'] ?>" <?= (isset($_POST['category']) && $_POST['category'] == $row['id']) ? 'selected' : '' ?>>
                            <?= $row['category_name'] ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Sub Category:</label>
                <select name="sub_category" onchange="this.form.submit()">
                    <option value="">Select Sub Category</option>
                    <?php foreach ($subcategories as $row) { ?>
                        <option value="<?= $row['id'] ?>" <?= (isset($_POST['sub_category']) && $_POST['sub_category'] == $row['id']) ? 'selected' : '' ?>>
                            <?= $row['subcategory_name'] ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Select Item:</label>
                <select name="item">
                    <option value="">Select Item</option>
                    <?php foreach ($items as $row) { ?>
                        <option value="<?= $row['id'] ?>"><?= $row['item_name'] ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group">
                <label>Selling Price:</label>
                <input type="number" id="price" name="price" value="0" min="0">
            </div>
            
            <div class="form-group">
                <label>Quantity per Unit:</label>
                <input type="number" id="quantity" name="quantity" value="1" min="1">
            </div>
            
            <div class="form-group">
                <label>Select Unit Type:</label>
                <select name="unit">
                    <option>Kilogram(KG)</option>
                    <option>Meters(M)</option>
                    <option>Litres(L)</option>
                </select>
            </div>
            
            <button type="submit" name="save">Save</button>
        </form>
    </div>
</body>
</html>
