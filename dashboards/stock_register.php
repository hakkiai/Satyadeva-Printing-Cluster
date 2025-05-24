/*stock_register.php*/
<?php
// Database connection
include '../database/db_connect.php';

// Add this near the top of your file after the database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to validate and normalize dates
function normalizeDate($date) {
    if (empty($date)) return null;
    
    // Check if it's already a valid date
    if (strtotime($date) !== false) {
        return date('Y-m-d', strtotime($date));
    }
    
    // Handle cases where date might be just a year
    if (preg_match('/^\d{4}$/', $date)) {
        return $date . '-01-01'; // Default to Jan 1 of that year
    }
    
    return null;
}

// Part 1: Fetch categories
$category_query = "SELECT * FROM inventory_categories";
$category_result = $conn->query($category_query);

// Part 2: Handle filters for stock transitions
$filter_category = isset($_GET['filter_category']) ? (int)$_GET['filter_category'] : 0;
$filter_subcategory = isset($_GET['filter_subcategory']) ? (int)$_GET['filter_subcategory'] : 0;
$filter_item = isset($_GET['filter_item']) ? (int)$_GET['filter_item'] : 0;
$filter_date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';

// Fetch subcategories based on selected category
$subcategories = [];
if ($filter_category) {
    $subcat_query = $conn->prepare("SELECT id, subcategory_name FROM inventory_subcategories WHERE category_id = ?");
    $subcat_query->bind_param("i", $filter_category);
    $subcat_query->execute();
    $subcategories = $subcat_query->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch items based on selected subcategory
$items = [];
if ($filter_subcategory) {
    $item_query = $conn->prepare("SELECT id, item_name FROM inventory_items_copy WHERE subcategory_id = ? AND active_status = 1");
    $item_query->bind_param("i", $filter_subcategory);
    $item_query->execute();
    $items = $item_query->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch stock transitions
$transitions = [];
$total_utilized = 0;
$total_unit = '';

if ($filter_category) {
    // First, ensure all dates are valid in source tables
    $conn->query("UPDATE stock_utilization SET utilization_date = '2025-01-01 00:00:00' 
                 WHERE utilization_date IS NULL OR CAST(utilization_date AS CHAR) NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}'");
    
    $conn->query("UPDATE job_sheets SET created_at = '2025-01-01 00:00:00' 
                 WHERE created_at IS NULL OR CAST(created_at AS CHAR) NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}'");

    // Check if we need to populate stock_transitions
    $check_transitions = $conn->query("SELECT COUNT(*) as count FROM stock_transitions WHERE category_id = $filter_category")->fetch_assoc();
    
    if ($check_transitions['count'] == 0) {
        // Get all valid records from stock_utilization
        $util_records = [];
        $util_query = $conn->prepare("
            SELECT 
                su.item_id,
                su.item_name,
                su.quantity_used,
                su.unit,
                su.utilization_date,
                su.department_id,
                su.customer_id,
                ii.subcategory_id,
                isc.category_id
            FROM stock_utilization su
            JOIN inventory_items_copy ii ON su.item_id = ii.id
            JOIN inventory_subcategories isc ON ii.subcategory_id = isc.id
            WHERE isc.category_id = ?
        ");
        $util_query->bind_param("i", $filter_category);
        $util_query->execute();
        $util_result = $util_query->get_result();
        
        while ($util = $util_result->fetch_assoc()) {
            $date = normalizeDate($util['utilization_date']);
            if ($date) {
                $util['transition_date'] = $date;
                $util_records[] = $util;
            }
        }
        
        // Get all valid records from job_sheets (type)
        $job_type_records = [];
        $job_type_query = $conn->prepare("
            SELECT 
                ii.id as item_id,
                ii.item_name,
                js.quantity as quantity_used,
                ii.unit,
                js.created_at,
                ii.subcategory_id,
                isc.category_id
            FROM job_sheets js
            JOIN inventory_items_copy ii ON js.type = ii.id
            JOIN inventory_subcategories isc ON ii.subcategory_id = isc.id
            WHERE isc.category_id = ?
            AND js.type IS NOT NULL
        ");
        $job_type_query->bind_param("i", $filter_category);
        $job_type_query->execute();
        $job_type_result = $job_type_query->get_result();
        
        while ($job = $job_type_result->fetch_assoc()) {
            $date = normalizeDate($job['created_at']);
            if ($date) {
                $job['transition_date'] = $date;
                $job['department_id'] = null;
                $job['customer_id'] = null;
                $job_type_records[] = $job;
            }
        }
        
        // Get all valid records from job_sheets (ctp_plate)
        $job_ctp_records = [];
        $job_ctp_query = $conn->prepare("
            SELECT 
                ii.id as item_id,
                ii.item_name,
                js.ctp_quantity as quantity_used,
                ii.unit,
                js.created_at,
                ii.subcategory_id,
                isc.category_id
            FROM job_sheets js
            JOIN inventory_items_copy ii ON js.ctp_plate = ii.id
            JOIN inventory_subcategories isc ON ii.subcategory_id = isc.id
            WHERE isc.category_id = ?
            AND js.ctp_plate IS NOT NULL
        ");
        $job_ctp_query->bind_param("i", $filter_category);
        $job_ctp_query->execute();
        $job_ctp_result = $job_ctp_query->get_result();
        
        while ($job = $job_ctp_result->fetch_assoc()) {
            $date = normalizeDate($job['created_at']);
            if ($date) {
                $job['transition_date'] = $date;
                $job['department_id'] = null;
                $job['customer_id'] = null;
                $job_ctp_records[] = $job;
            }
        }
        
        // Combine all records
        $all_records = array_merge($util_records, $job_type_records, $job_ctp_records);
        
        // Process each record
        foreach ($all_records as $record) {
            $item_id = $record['item_id'];
            $quantity_used = $record['quantity_used'];
            $transition_date = $record['transition_date'];
            
            // Get total quantity for the item
            $total_qty_query = "SELECT COALESCE(SUM(quantity), 0) as total_quantity FROM inventory WHERE item_id = ?";
            $total_stmt = $conn->prepare($total_qty_query);
            $total_stmt->bind_param("i", $item_id);
            $total_stmt->execute();
            $total_qty = $total_stmt->get_result()->fetch_assoc()['total_quantity'];
            $total_stmt->close();

            // Calculate opening balance
            $prev_transition = $conn->query("
                SELECT closing_balance 
                FROM stock_transitions 
                WHERE item_id = $item_id AND transition_date < '$transition_date' 
                ORDER BY transition_date DESC 
                LIMIT 1
            ")->fetch_assoc();
            
            $opening_balance = $prev_transition ? $prev_transition['closing_balance'] : $total_qty;
            $closing_balance = max(0, $opening_balance - $quantity_used);

            // Insert transition
            $insert_trans = $conn->prepare("
                INSERT INTO stock_transitions 
                (item_id, item_name, category_id, subcategory_id, opening_balance, 
                 utilized_quantity, closing_balance, unit, transition_date, 
                 department_id, customer_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_trans->bind_param("isiiiddsiii", 
                $record['item_id'], 
                $record['item_name'], 
                $record['category_id'], 
                $record['subcategory_id'], 
                $opening_balance, 
                $quantity_used, 
                $closing_balance, 
                $record['unit'], 
                $transition_date, 
                $record['department_id'], 
                $record['customer_id']);
            $insert_trans->execute();
            $insert_trans->close();
        }
    }

    // Fetch transitions for display
    $transitions_query = "
        SELECT 
            st.id,
            st.item_name,
            st.opening_balance,
            st.utilized_quantity,
            st.closing_balance,
            st.unit,
            st.transition_date,
            ic.category_name,
            isc.subcategory_name,
            d.department_name,
            c.customer_name
        FROM stock_transitions st
        LEFT JOIN inventory_categories ic ON st.category_id = ic.id
        LEFT JOIN inventory_subcategories isc ON st.subcategory_id = isc.id
        LEFT JOIN departments d ON st.department_id = d.department_id
        LEFT JOIN customers c ON st.customer_id = c.id
        WHERE st.category_id = $filter_category
    ";

    if ($filter_subcategory) {
        $transitions_query .= " AND st.subcategory_id = $filter_subcategory";
    }
    if ($filter_item) {
        $transitions_query .= " AND st.item_id = $filter_item";
    }
    if ($filter_date_from) {
        $transitions_query .= " AND st.transition_date >= '$filter_date_from'";
    }
    if ($filter_date_to) {
        $transitions_query .= " AND st.transition_date <= '$filter_date_to'";
    }

    $transitions_query .= " ORDER BY st.transition_date DESC";
    $transitions_result = $conn->query($transitions_query);
    if ($transitions_result === false) {
        error_log("Error in transitions query: " . $conn->error);
        $transitions = [];
    } else {
        $transitions = $transitions_result->fetch_all(MYSQLI_ASSOC);
    }

    // Calculate total utilized if item is selected
    if ($filter_item) {
        $total_query = "SELECT SUM(utilized_quantity) as total, unit 
                       FROM stock_transitions 
                       WHERE item_id = " . (int)$filter_item;
        
        $total_result = $conn->query($total_query);
        if ($total_result === false) {
            error_log("Error in query: " . $conn->error);
            $total_utilized = 0;
            $total_unit = '';
        } else {
            $row = $total_result->fetch_assoc();
            $total_utilized = $row['total'] ?? 0;
            $total_unit = $row['unit'] ?? '';
        }
    }
}

// Add this to check the query
if ($filter_item) {
    error_log("Filter item: " . $filter_item);
    error_log("Date from: " . $filter_date_from);
    error_log("Date to: " . $filter_date_to);
}

// Add this after your existing database connection code
function getStockSummary($conn, $item_id, $date_from, $date_to) {
    try {
        // First check if the table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'stock_transitions'");
        if ($table_check->num_rows == 0) {
            throw new Exception("stock_transitions table does not exist");
        }

        $summary = [
            'opening_balance' => 0,
            'total_added' => 0,
            'total_used' => 0,
            'closing_balance' => 0,
            'department_breakdown' => []
        ];
        
        // Get opening balance
        $opening_query = "SELECT closing_balance 
                         FROM stock_transitions 
                         WHERE item_id = ? 
                         AND transition_date < ? 
                         ORDER BY transition_date DESC 
                         LIMIT 1";
        
        $stmt = $conn->prepare($opening_query);
        if ($stmt === false) {
            throw new Exception("Error preparing opening balance query: " . $conn->error);
        }
        
        $stmt->bind_param("is", $item_id, $date_from);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $summary['opening_balance'] = $row['closing_balance'];
        }
        $stmt->close();
        
        // Get period transactions
        $transactions_query = "SELECT 
                                st.*,
                                d.department_name,
                                c.customer_name
                              FROM stock_transitions st
                              LEFT JOIN departments d ON st.department_id = d.department_id
                              LEFT JOIN customers c ON st.customer_id = c.id
                              WHERE st.item_id = ? 
                              AND st.transition_date BETWEEN ? AND ?";
        
        $stmt = $conn->prepare($transactions_query);
        if ($stmt === false) {
            throw new Exception("Error preparing transactions query: " . $conn->error);
        }
        
        $stmt->bind_param("iss", $item_id, $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $summary['total_used'] += $row['utilized_quantity'];
            
            // Department breakdown
            $dept_name = $row['department_name'] ?? 'Others';
            if ($row['customer_name']) {
                $dept_name .= " - " . $row['customer_name'];
            }
            
            if (!isset($summary['department_breakdown'][$dept_name])) {
                $summary['department_breakdown'][$dept_name] = [
                    'used' => 0
                ];
            }
            
            $summary['department_breakdown'][$dept_name]['used'] += $row['utilized_quantity'];
        }
        $stmt->close();
        
        // Calculate closing balance
        $summary['closing_balance'] = $summary['opening_balance'] - $summary['total_used'];
        
        return $summary;
    } catch (Exception $e) {
        error_log("Error in getStockSummary: " . $e->getMessage());
        return [
            'opening_balance' => 0,
            'total_added' => 0,
            'total_used' => 0,
            'closing_balance' => 0,
            'department_breakdown' => []
        ];
    }
}

// Modify your existing code to use the new summary function
if ($filter_item) {
    $date_from = $filter_date_from ?: date('Y-m-d', strtotime('-30 days'));
    $date_to = $filter_date_to ?: date('Y-m-d');
    $stock_summary = getStockSummary($conn, $filter_item, $date_from, $date_to);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Register</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background-color: #ffffff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .inventory-table th, .inventory-table td {
            border: 1px solid #e0e0e0;
            padding: 12px 15px;
            text-align: left;
        }
        .inventory-table th {
            background-color: #2c3e50;
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        .inventory-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .inventory-table tr:hover {
            background-color: #e9ecef;
        }
        .no-items {
            color: #6c757d;
            font-style: italic;
            margin: 20px 0;
            text-align: center;
        }
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            align-items: end;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.15s ease-in-out;
        }
        .form-control:focus {
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .records-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #2c3e50;
            color: white;
            border-radius: 8px;
        }
        .total-utilized {
            font-weight: bold;
            color: #ffffff;
            background: #28a745;
            padding: 8px 15px;
            border-radius: 4px;
        }
        .container {
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        h2, h3 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 15px;
        }
        .stock-summary {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .summary-item {
            background: white;
            padding: 15px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-item label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #495057;
        }
        .summary-item span {
            font-size: 1.2em;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Super Admin Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='add_user.php'">Add User</button>
            <button onclick="location.href='super_inventory.php'">Inventory</button>
            <button onclick="location.href='stock_inventory.php'">Stock Inventory</button>
            <button onclick="location.href='stock_register.php'">Stock Register</button>
            <button onclick="location.href='set_quantity.php'">Set Quantity</button>
            <button onclick="location.href='paper_inventory.php'">Paper Inventory</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <!-- First Container: Filters -->
    <div class="container">
        <h2>Stock Register</h2>
        <h3>Stock Transitions</h3>
        <form method="GET" class="filter-form" id="filterForm">
            <div class="filter-group">
                <label>Category:</label>
                <select name="filter_category" class="form-control" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php 
                    $category_result->data_seek(0);
                    while ($cat = $category_result->fetch_assoc()): ?>
                        <option value="<?= $cat['id'] ?>" <?= $filter_category == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Subcategory:</label>
                <select name="filter_subcategory" class="form-control" onchange="this.form.submit()">
                    <option value="">All Subcategories</option>
                    <?php foreach ($subcategories as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= $filter_subcategory == $sub['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($sub['subcategory_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Item:</label>
                <select name="filter_item" class="form-control" onchange="this.form.submit()">
                    <option value="">All Items</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?= $item['id'] ?>" <?= $filter_item == $item['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($item['item_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>From Date:</label>
                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filter_date_from) ?>" onchange="this.form.submit()">
            </div>
            <div class="filter-group">
                <label>To Date:</label>
                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filter_date_to) ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>

    <!-- Second Container: Transition Records -->
    <div class="container">
        <div class="records-header">
            <h3>Transition Records</h3>
            <?php if ($filter_item): ?>
                <div class="total-utilized">Total Utilized: <?= number_format($total_utilized, 2) ?> <?= htmlspecialchars($total_unit) ?></div>
            <?php endif; ?>
        </div>

        <?php if (!empty($transitions)): ?>
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category</th>
                        <th>Subcategory</th>
                        <th>Item</th>
                        <th>Dept/Customer</th>
                        <th>Opening</th>
                        <th>Utilized</th>
                        <th>Closing</th>
                        <th>Unit</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transitions as $record): ?>
                        <tr>
                            <td><?= htmlspecialchars($record['id']) ?></td>
                            <td><?= htmlspecialchars($record['category_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($record['subcategory_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($record['item_name']) ?></td>
                            <td>
                                <?php 
                                if (($record['department_name'] ?? '') === 'Others' && !empty($record['customer_name'])) {
                                    echo htmlspecialchars($record['customer_name']);
                                } else {
                                    echo htmlspecialchars($record['department_name'] ?? 'N/A');
                                }
                                ?>
                            </td>
                            <td><?= number_format($record['opening_balance'], 2) ?></td>
                            <td><?= number_format($record['utilized_quantity'], 2) ?></td>
                            <td><?= number_format($record['closing_balance'], 2) ?></td>
                            <td><?= htmlspecialchars($record['unit']) ?></td>
                            <td><?= date('d M Y', strtotime($record['transition_date'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-items">No transition records found.</p>
        <?php endif; ?>
    </div>

    <!-- Add this after your existing table -->
    <?php if (isset($stock_summary)): ?>
    <div class="stock-summary">
        <h3>Stock Summary</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <label>Opening Balance:</label>
                <span><?= number_format($stock_summary['opening_balance'], 2) ?> <?= htmlspecialchars($total_unit) ?></span>
            </div>
            <div class="summary-item">
                <label>Total Added:</label>
                <span><?= number_format($stock_summary['total_added'], 2) ?> <?= htmlspecialchars($total_unit) ?></span>
            </div>
            <div class="summary-item">
                <label>Total Used:</label>
                <span><?= number_format($stock_summary['total_used'], 2) ?> <?= htmlspecialchars($total_unit) ?></span>
            </div>
            <div class="summary-item">
                <label>Closing Balance:</label>
                <span><?= number_format($stock_summary['closing_balance'], 2) ?> <?= htmlspecialchars($total_unit) ?></span>
            </div>
        </div>
        
        <h4>Department-wise Breakdown</h4>
        <table class="inventory-table">
            <thead>
                <tr>
                    <th>Department/Customer</th>
                    <th>Quantity Used</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stock_summary['department_breakdown'] as $dept => $data): ?>
                <tr>
                    <td><?= htmlspecialchars($dept) ?></td>
                    <td><?= number_format($data['used'], 2) ?> <?= htmlspecialchars($total_unit) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</body>
</html>




http://localhost/Satyadeva-Printing-Cluster/dashboards/stock_register.php?filter_category=3&filter_subcategory=6&filter_item=20&date_from=&date_to=