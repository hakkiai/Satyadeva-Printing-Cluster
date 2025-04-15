<?php
session_start();
include '../database/db_connect.php';

// Determine dashboard based on user role
$dashboard_url = 'accounts_dashboard.php'; // Default
if (isset($_SESSION['role'])) {
    error_log("Stock_register.php - User role: " . $_SESSION['role']);
    switch (strtolower($_SESSION['role'])) {
        case 'super_admin':
            $dashboard_url = 'superadmin.php';
            break;
        case 'admin':
            $dashboard_url = 'admin.php';
            break;
        case 'accounts':
            $dashboard_url = 'accounts_dashboard.php';
            break;
        case 'reception':
            $dashboard_url = 'reception-1.php';
            break;
        case 'ctp':
            $dashboard_url = 'ctp_dashboard.php';
            break;
        case 'multicolour':
            $dashboard_url = 'multicolour_dashboard.php';
            break;
        case 'delivery':
            $dashboard_url = 'delivery.php';
            break;
        case 'dispatch':
            $dashboard_url = 'dispatch.php';
            break;
        case 'digital':
            $dashboard_url = 'digital_dashboard.php';
            break;
        default:
            error_log("Stock_register.php - Unrecognized role: " . $_SESSION['role']);
            $dashboard_url = 'accounts_dashboard.php';
    }
} else {
    error_log("Stock_register.php - Session role not set");
    header("Location: login.php");
    exit;
}

// Initialize variables
$category_id = isset($_GET['category_id']) && is_numeric($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$subcategory_id = isset($_GET['subcategory_id']) && is_numeric($_GET['subcategory_id']) ? (int)$_GET['subcategory_id'] : null;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Fetch categories for filter
$category_query = "SELECT id, category_name FROM inventory_categories ORDER BY category_name";
$category_result = $conn->query($category_query);

// Fetch subcategories for filter
$subcategory_query = "SELECT id, subcategory_name FROM inventory_subcategories";
if ($category_id) {
    $subcategory_query .= " WHERE category_id = ?";
}
$subcategory_query .= " ORDER BY subcategory_name";
$stmt = $conn->prepare($subcategory_query);
if ($category_id) {
    $stmt->bind_param("i", $category_id);
}
$stmt->execute();
$subcategory_result = $stmt->get_result();
$stmt->close();

// Handle CSV download
if (isset($_GET['download_all'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stock_register.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['S.No', 'Item Category', 'Item Subcategory', 'Item Sub Sub Category', 'Total Quantity']);

    $sql = "SELECT ic.category_name, isc.subcategory_name, ii.item_name, COALESCE(SUM(i.quantity), 0) AS total_quantity
            FROM inventory_items_copy ii
            JOIN inventory_subcategories isc ON ii.subcategory_id = isc.id
            JOIN inventory_categories ic ON isc.category_id = ic.id
            LEFT JOIN inventory i ON i.item_id = ii.id";
    $params = [];
    $types = "";
    $where_conditions = [];

    if ($category_id) {
        $where_conditions[] = "ic.id = ?";
        $params[] = $category_id;
        $types .= "i";
    }
    if ($subcategory_id) {
        $where_conditions[] = "isc.id = ?";
        $params[] = $subcategory_id;
        $types .= "i";
    }

    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    $sql .= " GROUP BY ii.id, ic.category_name, isc.subcategory_name, ii.item_name
              ORDER BY ic.category_name, isc.subcategory_name, ii.item_name";

    $stmt = $conn->prepare($sql);
    if ($types && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $sno = 0;
    while ($row = $result->fetch_assoc()) {
        $sno++;
        fputcsv($output, [
            $sno,
            $row['category_name'],
            $row['subcategory_name'],
            $row['item_name'],
            number_format($row['total_quantity'], 2)
        ]);
    }
    fclose($output);
    $stmt->close();
    exit;
}

// Fetch total records for pagination
$sql_count = "SELECT COUNT(DISTINCT ii.id) as total
              FROM inventory_items_copy ii
              JOIN inventory_subcategories isc ON ii.subcategory_id = isc.id
              JOIN inventory_categories ic ON isc.category_id = ic.id";
$params = [];
$types = "";
$where_conditions = [];

if ($category_id) {
    $where_conditions[] = "ic.id = ?";
    $params[] = $category_id;
    $types .= "i";
}
if ($subcategory_id) {
    $where_conditions[] = "isc.id = ?";
    $params[] = $subcategory_id;
    $types .= "i";
}

if (!empty($where_conditions)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_conditions);
}

$stmt = $conn->prepare($sql_count);
if ($types && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$total_records = $result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$stmt->close();

// Fetch stock items
$sql = "SELECT ic.category_name, isc.subcategory_name, ii.item_name, COALESCE(SUM(i.quantity), 0) AS total_quantity
        FROM inventory_items_copy ii
        JOIN inventory_subcategories isc ON ii.subcategory_id = isc.id
        JOIN inventory_categories ic ON isc.category_id = ic.id
        LEFT JOIN inventory i ON i.item_id = ii.id";
$params = [];
$types = "";
$where_conditions = [];

if ($category_id) {
    $where_conditions[] = "ic.id = ?";
    $params[] = $category_id;
    $types .= "i";
}
if ($subcategory_id) {
    $where_conditions[] = "isc.id = ?";
    $params[] = $subcategory_id;
    $types .= "i";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}
$sql .= " GROUP BY ii.id, ic.category_name, isc.subcategory_name, ii.item_name
          ORDER BY ic.category_name, isc.subcategory_name, ii.item_name
          LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if ($types && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items_result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Register</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }
        .navbar {
            background-color: #ffffff;
            padding: 15px 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar .brand {
            color: #007bff;
            font-size: 24px;
            margin: 0;
        }
        .nav-buttons button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            margin-left: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .nav-buttons button:hover {
            background-color: #0056b3;
        }
        .stock-container {
            width: 95%;
            max-width: 1200px;
            margin: 30px auto;
            padding: 25px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        .stock-container h2 {
            color: #333;
            font-size: 28px;
            margin-bottom: 20px;
            text-align: center;
        }
        .filter-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        .filter-container select {
            width: 100%;
            max-width: 300px;
            padding: 12px 15px;
            border: 2px solid #007bff;
            border-radius: 30px;
            font-size: 16px;
            outline: none;
            background-color: #ffffff;
            transition: border-color 0.3s;
        }
        .filter-container select:focus {
            border-color: #0056b3;
        }
        .filter-container button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }
        .filter-container button:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 20px 0;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
        }
        th, td {
            padding: 15px;
            text-align: left;
            font-size: 16px;
        }
        th {
            background-color: #007bff;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            border-bottom: 1px solid #e9ecef;
            color: #333;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover td {
            background-color: #f1f3f5;
            transition: background-color 0.2s;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        .pagination a {
            display: inline-block;
            padding: 10px 15px;
            text-decoration: none;
            color: #007bff;
            border: 2px solid #007bff;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        .pagination a:hover {
            background-color: #007bff;
            color: white;
        }
        .pagination a.active {
            background-color: #007bff;
            color: white;
            font-weight: 600;
        }
        .stock-container p {
            color: #dc3545;
            font-size: 18px;
            font-weight: 500;
            text-align: center;
            margin: 20px 0;
        }
        @media (max-width: 768px) {
            .stock-container {
                width: 90%;
                padding: 15px;
            }
            .filter-container {
                flex-direction: column;
                gap: 15px;
            }
            .filter-container select {
                max-width: 100%;
            }
            table {
                font-size: 14px;
            }
            th, td {
                padding: 10px;
            }
        }
    </style>
    <script>
        function applyFilter() {
            const category = document.getElementById('categoryFilter').value;
            const subcategory = document.getElementById('subcategoryFilter').value;
            let url = 'stock_register.php?page=1';
            if (category) {
                url += '&category_id=' + encodeURIComponent(category);
            }
            if (subcategory) {
                url += '&subcategory_id=' + encodeURIComponent(subcategory);
            }
            window.location.href = url;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const categoryFilter = document.getElementById('categoryFilter');
            const subcategoryFilter = document.getElementById('subcategoryFilter');

            if (categoryFilter) {
                categoryFilter.addEventListener('change', applyFilter);
            }
            if (subcategoryFilter) {
                subcategoryFilter.addEventListener('change', applyFilter);
            }
        });
    </script>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Stock Register</h2>
        <div class="nav-buttons">
            <button onclick="location.href='<?php echo $dashboard_url; ?>'">Back to Dashboard</button>
            <button onclick="location.href='reports.php'">Back to Reports</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>
    <div class="stock-container">
        <h2>Stock Register</h2>
        <div class="filter-container">
            <select id="categoryFilter" name="category_id">
                <option value="">All Categories</option>
                <?php while ($category = $category_result->fetch_assoc()): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <select id="subcategoryFilter" name="subcategory_id">
                <option value="">All Subcategories</option>
                <?php while ($subcategory = $subcategory_result->fetch_assoc()): ?>
                    <option value="<?php echo $subcategory['id']; ?>" <?php echo $subcategory_id == $subcategory['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($subcategory['subcategory_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button onclick="window.location.href='stock_register.php?download_all=1<?php echo $category_id ? '&category_id=' . urlencode($category_id) : ''; ?><?php echo $subcategory_id ? '&subcategory_id=' . urlencode($subcategory_id) : ''; ?>'">Download CSV</button>
        </div>
        <?php if ($items_result && $items_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Item Category</th>
                        <th>Item Subcategory</th>
                        <th>Item Sub Sub Category</th>
                        <th>Total Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sno = $offset; ?>
                    <?php while ($item = $items_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo ++$sno; ?></td>
                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['subcategory_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td><?php echo number_format($item['total_quantity'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="stock_register.php?<?php echo $category_id ? 'category_id=' . urlencode($category_id) . '&' : ''; ?><?php echo $subcategory_id ? 'subcategory_id=' . urlencode($subcategory_id) . '&' : ''; ?>page=<?php echo $page - 1; ?>">« Previous</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="stock_register.php?<?php echo $category_id ? 'category_id=' . urlencode($category_id) . '&' : ''; ?><?php echo $subcategory_id ? 'subcategory_id=' . urlencode($subcategory_id) . '&' : ''; ?>page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="stock_register.php?<?php echo $category_id ? 'category_id=' . urlencode($category_id) . '&' : ''; ?><?php echo $subcategory_id ? 'subcategory_id=' . urlencode($subcategory_id) . '&' : ''; ?>page=<?php echo $page + 1; ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>No items found.</p>
        <?php endif; ?>
    </div>
</body>
</html>