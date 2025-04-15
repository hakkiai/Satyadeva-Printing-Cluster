<?php
include '../database/db_connect.php';

$alert_message = "";

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add Category
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);
        if (!empty($category_name)) {
            $stmt = $conn->prepare("INSERT INTO inventory_categories (category_name) VALUES (?)");
            $stmt->bind_param("s", $category_name);
            if ($stmt->execute()) {
                $alert_message = "Category added successfully!";
            } else {
                $alert_message = "Failed to add category!";
            }
            $stmt->close();
        }
    } 
    // Edit Category
    elseif (isset($_POST['edit_category'])) {
        $category_id = $_POST['category_id'];
        $category_name = trim($_POST['category_name']);

        if (!empty($category_name) && !empty($category_id)) {
            $stmt = $conn->prepare("UPDATE inventory_categories SET category_name = ? WHERE id = ?");
            $stmt->bind_param("si", $category_name, $category_id);
            if ($stmt->execute()) {
                $alert_message = "Category updated successfully!";
            } else {
                $alert_message = "Failed to update category!";
            }
            $stmt->close();
        }
    } 
    // Delete Category (and related subcategories & items)
    elseif (isset($_POST['delete_category'])) {
        $category_id = $_POST['category_id'];
        if (!empty($category_id)) {
            $conn->query("DELETE FROM inventory_items WHERE subcategory_id IN (SELECT id FROM inventory_subcategories WHERE category_id = $category_id)");
            $conn->query("DELETE FROM inventory_subcategories WHERE category_id = $category_id");
            if ($conn->query("DELETE FROM inventory_categories WHERE id = $category_id")) {
                $alert_message = "Category and all related data deleted successfully!";
            } else {
                $alert_message = "Failed to delete category!";
            }
        }
    } 
    // Add Subcategory
    elseif (isset($_POST['add_subcategory'])) {
        $category_id = $_POST['category_id'];
        $subcategory_name = trim($_POST['subcategory_name']);

        if (!empty($category_id) && !empty($subcategory_name)) {
            $stmt = $conn->prepare("INSERT INTO inventory_subcategories (subcategory_name, category_id) VALUES (?, ?)");
            $stmt->bind_param("si", $subcategory_name, $category_id);
            if ($stmt->execute()) {
                $alert_message = "Subcategory added successfully!";
            } else {
                $alert_message = "Failed to add subcategory!";
            }
            $stmt->close();
        }
    } 
    // Add Item
    elseif (isset($_POST['add_item'])) {
        $subcategory_id = $_POST['subcategory_id'];
        $item_name = trim($_POST['item_name']);

        if (!empty($subcategory_id) && !empty($item_name)) {
            $stmt = $conn->prepare("INSERT INTO inventory_items (item_name, subcategory_id) VALUES (?, ?)");
            $stmt->bind_param("si", $item_name, $subcategory_id);
            if ($stmt->execute()) {
                $alert_message = "Item added successfully!";
            } else {
                $alert_message = "Failed to add item!";
            }
            $stmt->close();
        }
    }
    // Edit Subcategory
    elseif (isset($_POST['edit_subcategory'])) {
        $subcategory_id = $_POST['subcategory_id'];
        $subcategory_name = trim($_POST['subcategory_name']);

        if (!empty($subcategory_id) && !empty($subcategory_name)) {
            $stmt = $conn->prepare("UPDATE inventory_subcategories SET subcategory_name = ? WHERE id = ?");
            $stmt->bind_param("si", $subcategory_name, $subcategory_id);
            if ($stmt->execute()) {
                $alert_message = "Subcategory updated successfully!";
            } else {
                $alert_message = "Failed to update subcategory!";
            }
            $stmt->close();
        }
    }

    // Delete Subcategory
    elseif (isset($_POST['delete_subcategory'])) {
        $subcategory_id = $_POST['subcategory_id'];
        if (!empty($subcategory_id)) {
            $conn->query("DELETE FROM inventory_items WHERE subcategory_id = $subcategory_id");
            if ($conn->query("DELETE FROM inventory_subcategories WHERE id = $subcategory_id")) {
                $alert_message = "Subcategory and related items deleted successfully!";
            } else {
                $alert_message = "Failed to delete subcategory!";
            }
        }
    }
}

// Fetch categories
$categories = $conn->query("SELECT * FROM inventory_categories");

// Fetch subcategories dynamically via AJAX
if (isset($_GET['fetch_subcategories']) && isset($_GET['category_id'])) {
    $category_id = intval($_GET['category_id']);
    $subcategories = $conn->query("SELECT * FROM inventory_subcategories WHERE category_id = $category_id");
    $subcategory_list = [];

    while ($row = $subcategories->fetch_assoc()) {
        $subcategory_list[] = ['id' => $row['id'], 'subcategory_name' => $row['subcategory_name']];
    }

    echo json_encode($subcategory_list);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <script>
        function showAlert(message) {
            if (message) alert(message);
        }
        function editCategory(id, name) {
        document.getElementById('category_id').value = id;
        document.getElementById('category_name').value = name;
        document.getElementById('category_submit').name = 'edit_category';
        document.getElementById('category_submit').textContent = 'Save Category';
       }

       function editSubcategory(id, name) {
        document.getElementById('subcategory_id').value = id;
        document.getElementById('subcategory_name').value = name;
        document.getElementById('subcategory_submit').name = 'edit_subcategory';
        document.getElementById('subcategory_submit').textContent = 'Save Subcategory';
        }

        function fetchItemSubcategories() {
    let categoryDropdown = document.getElementById('categoryDropdown'); // Item Category
    let subcategoryDropdown = document.getElementById('subcategoryDropdown'); // Item Subcategory
    let categoryId = categoryDropdown.value;

    subcategoryDropdown.innerHTML = '<option value="">Select Subcategory</option>';

    if (categoryId) {
        fetch('super_inventory.php?fetch_subcategories=1&category_id=' + categoryId)
            .then(response => response.json())
            .then(data => {
                data.forEach(subcategory => {
                    let option = document.createElement('option');
                    option.value = subcategory.id;
                    option.textContent = subcategory.subcategory_name;
                    subcategoryDropdown.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching item subcategories:', error));
    }
}

function fetchSubcategorySubcategories() {
    let categoryDropdown = document.getElementById('subcategoryCategoryDropdown'); // Subcategory Category
    let subcategoryContainer = document.getElementById('subcategoryContainer');
    let categoryId = categoryDropdown.value;

    subcategoryContainer.innerHTML = '';

    if (categoryId) {
        fetch('super_inventory.php?fetch_subcategories=1&category_id=' + categoryId)
            .then(response => response.json())
            .then(data => {
                data.forEach(subcategory => {
                    let card = document.createElement('div');
                    card.classList.add('category-card');
                    card.innerHTML = `
                        <p>${subcategory.subcategory_name}</p>
                        <button class="edit-btn" style="width:70px;background-color:green" onclick="editSubcategory(${subcategory.id}, '${subcategory.subcategory_name}')">Edit</button>
                        <form action="super_inventory.php" method="POST" style="display:inline;">
                            <input type="hidden" name="subcategory_id" value="${subcategory.id}">
                            <button class="delete-btn" style="background-color:red" type="submit" name="delete_subcategory" onclick="return confirm('Are you sure?')">Delete</button>
                        </form>
                    `;
                    subcategoryContainer.appendChild(card);
                });
            })
            .catch(error => console.error('Error fetching subcategories:', error));
    }
}


    </script>
</head>
<body onload="showAlert('<?php echo $alert_message; ?>')">
    <div class="navbar">
        <h2 class="brand">Super Admin Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='../dashboards/add_user.php'">Add User</button>
            <button onclick="location.href='../dashboards/super_inventory.php'">Inventory</button>
            <button onclick="location.href='../dashboards/stock_inventory.php'">Stock Inventory</button>
            <button onclick="location.href='../dashboards/paper_inventory.php'">Paper Inventory</button>
            <button onclick="location.href='../dashboards/set_balance_limits.php'">Limitation</button>
            <button onclick="location.href='../dashboards/reports.php'">Reports</button> <!-- New Button -->
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="inventory-container">
        <!-- Category Management -->
        <div class="inventory-box">
            <h2>Category Management</h2>
            <form action="super_inventory.php" method="POST">
                <input type="hidden" id="category_id" name="category_id">
                <label>Category Name:</label>
                <input type="text" id="category_name" name="category_name" placeholder="Enter Category:" required>
                <button type="submit" id="category_submit" name="add_category">Add Category</button>
            </form>

            <div class="category-list">
                <?php while ($row = $categories->fetch_assoc()) { ?>
                    <div class="category-card">
                        <p><?php echo $row['category_name']; ?></p>
                        <button class="edit-btn" style="width:70px;height:40px;background-color:green;" onclick="editCategory(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['category_name']); ?>')">Edit</button>
                        <form action="super_inventory.php" method="POST" style="display:inline;">
                            <input type="hidden" name="category_id" value="<?php echo $row['id']; ?>">
                            <button class="delete-btn" style="height:40px;background-color:red;" type="submit" name="delete_category" onclick="return confirm('Are you sure?')">Delete</button>
                        </form>
                    </div>
                <?php } ?>
            </div>
        </div>


        <!-- Subcategory Management -->
        <!-- Subcategory Management -->
    <div class="inventory-box">
        <h2>Subcategory Management</h2>
        <form action="super_inventory.php" method="POST">
            <label>Category:</label>
            <select id="subcategoryCategoryDropdown" name="category_id" onchange="fetchSubcategorySubcategories()" required>
                <option value="">Select Category</option>
                <?php
                $categories->data_seek(0);
                while ($row = $categories->fetch_assoc()) { ?>
                    <option value="<?php echo $row['id']; ?>"><?php echo $row['category_name']; ?></option>
                <?php } ?>
            </select>
            <label>Sub Category:</label>
            <input type="hidden" id="subcategory_id" name="subcategory_id">
            <input type="text" id="subcategory_name" name="subcategory_name" required placeholder="Enter Subcategory">
            <button type="submit" id="subcategory_submit" name="add_subcategory">Add SubCategory</button>
        </form>

        <!-- Subcategories Display -->
        <div id="subcategoryContainer" class="category-list"></div>
    </div>

    <!-- Item Management -->
    <div class="inventory-box">
            <h2>Item Management</h2>
            <form action="super_inventory.php" method="POST">
                <label>Category:</label>
                <select id="categoryDropdown" name="category_id" onchange="fetchItemSubcategories()" required>
                    <option value="">Select Category</option>
                    <?php
                    $categories->data_seek(0);
                    while ($row = $categories->fetch_assoc()) { ?>
                        <option value="<?php echo $row['id']; ?>"><?php echo $row['category_name']; ?></option>
                    <?php } ?>
                </select>
                <label>Subcategory:</label>
                <select id="subcategoryDropdown" name="subcategory_id" placeholder="Select SubCategory" required><option value="">Select SubCategory</option></select>
                <label>Item Name:</label>
                <input type="text" name="item_name" placeholder="Enter Item" required>
                <button type="submit" name="add_item">Add Item</button>
            </form>
        </div>
    </div>

</body>
</html>
