<?php
// Database connection
include '../database/db_connect.php';

// Part 1: Fetch categories
$category_query = "SELECT * FROM inventory_categories";
$category_result = $conn->query($category_query);

// Part 2: Handle category selection and fetch subcategories/items
$category_data = '';
if (isset($_GET['category_id'])) {
    $category_id = $_GET['category_id'];
    
    // Fetch subcategories for selected category
    $subcategory_query = "SELECT * FROM inventory_subcategories WHERE category_id = ?";
    $stmt = $conn->prepare($subcategory_query);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $subcategory_result = $stmt->get_result();

    if ($subcategory_result->num_rows > 0) {
        while ($subcategory = $subcategory_result->fetch_assoc()) {
            $category_data .= "<h4 class='subcategory-title'>" . htmlspecialchars($subcategory['subcategory_name']) . "</h4>";
            
            // Determine if this is a paper or plate category (adjust based on your category IDs)
            $is_paper = ($category_id == 1); // Paper category ID
            $is_plate = ($category_id == 2); // Plates category ID

            // Fetch items with calculated total_quantity and utilised_quantity
            $items_query = "
                SELECT 
                    ii.id,
                    ii.item_name,
                    COALESCE(SUM(i.quantity), 0) AS total_quantity,
                    " . ($is_paper ? "
                        COALESCE((
                            SELECT SUM(js.quantity)
                            FROM job_sheets js
                            WHERE js.type = ii.id
                        ), 0)" : ($is_plate ? "
                        COALESCE((
                            SELECT SUM(js.ctp_quantity)
                            FROM job_sheets js
                            WHERE js.ctp_plate = ii.id
                        ), 0)" : "0")) . " AS utilised_quantity,
                    ii.active_status,
                    ii.unit
                FROM inventory_items_copy ii
                LEFT JOIN inventory i ON i.item_id = ii.id
                WHERE ii.subcategory_id = ?
                GROUP BY ii.id, ii.item_name, ii.active_status, ii.unit";
            
            $item_stmt = $conn->prepare($items_query);
            $item_stmt->bind_param("i", $subcategory['id']);
            $item_stmt->execute();
            $items_result = $item_stmt->get_result();
            
            if ($items_result->num_rows > 0) {
                $category_data .= "<table class='inventory-table'>";
                $category_data .= "<tr>
                                    <th>Item Name</th>
                                    <th>Total Quantity</th>
                                    <th>Utilized Quantity</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Unit</th>
                                  </tr>";
                
                while ($item = $items_result->fetch_assoc()) {
                    // Calculate balance
                    $balance = $item['total_quantity'] - $item['utilised_quantity'];

                    // Update active_status based on balance
                    $new_status = ($balance <= 0) ? 0 : 1; // 0 = inactive, 1 = active
                    if ($new_status != $item['active_status']) {
                        $update_query = "UPDATE inventory_items_copy SET active_status = ? WHERE id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bind_param("ii", $new_status, $item['id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }

                    // Use updated status for display
                    $status_text = ($balance <= 0) ? 'Inactive' : 'Active';
                    $status_color = ($balance <= 0) ? 'red' : 'green';
                    
                    $category_data .= "<tr>";
                    $category_data .= "<td>" . htmlspecialchars($item['item_name'] ?? '') . "</td>";
                    $category_data .= "<td>" . number_format($item['total_quantity'], 2) . "</td>";
                    $category_data .= "<td>" . number_format($item['utilised_quantity'], 2) . "</td>";
                    $category_data .= "<td>" . number_format($balance, 2) . "</td>";
                    $category_data .= "<td style='color: $status_color'>$status_text</td>";
                    $category_data .= "<td>" . htmlspecialchars($item['unit'] ?? '') . "</td>";
                    $category_data .= "</tr>";
                }
                
                $category_data .= "</table>";
            } else {
                $category_data .= "<p class='no-items'>No items found in this subcategory.</p>";
            }
            
            $item_stmt->close();
        }
    } else {
        $category_data .= "<p class='no-items'>No subcategories found for this category.</p>";
    }
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Inventory</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .category-dropdown {
            width: 90%;
            padding: 10px;
            font-size: 16px;
            border: 2px solid #007bff;
            border-radius: 5px;
            background-color: #ffffff;
            color: #007bff;
            cursor: pointer;
        }
        .category-dropdown:focus {
            outline: none;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        }
        .subcategory-title {
            color: #007bff;
            font-size: 22px;
            margin: 20px 0 10px;
        }
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background-color: #ffffff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        .inventory-table th, .inventory-table td {
            border: 1px solid #cce5ff;
            padding: 12px;
            text-align: left;
        }
        .inventory-table th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .inventory-table td {
            color: #333;
        }
        .inventory-table tr:nth-child(even) {
            background-color: #f8fbff;
        }
        .no-items {
            color: #0056b3;
            font-style: italic;
            margin: 10px 0;
        }
    </style>
</head>
<body>
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
    <div class="container">
        <h2>Stock Inventory</h2>
        <select class="category-dropdown" onchange="if (this.value) location.href='?category_id=' + this.value;">
            <option value="">Select Category</option>
            <?php 
            $category_result->data_seek(0); // Reset pointer to start
            while ($category = $category_result->fetch_assoc()): ?>
                <option value="<?php echo $category['id']; ?>" <?php echo (isset($_GET['category_id']) && $_GET['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category['category_name']); ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="container">
        <!-- Display subcategory tables with all item details -->
        <?php echo $category_data; ?>
    </div>
</body>
</html>