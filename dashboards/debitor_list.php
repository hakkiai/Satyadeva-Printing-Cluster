<?php
session_start();
include '../database/db_connect.php';

// Determine dashboard based on user role
$dashboard_url = 'accounts_dashboard.php'; // Default
if (isset($_SESSION['role'])) {
    error_log("Debitor_list.php - User role: " . $_SESSION['role']);
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
            error_log("Debitor_list.php - Unrecognized role: " . $_SESSION['role']);
            $dashboard_url = 'accounts_dashboard.php';
    }
} else {
    error_log("Debitor_list.php - Session role not set");
    header("Location: ../auth/login.php");
    exit;
}

// Initialize variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$from_date = isset($_GET['from_date']) && $_GET['from_date'] ? $_GET['from_date'] : null;
$to_date = isset($_GET['to_date']) && $_GET['to_date'] ? $_GET['to_date'] : null;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Debug logging
error_log("Debitor_list.php - Search: '$search', From Date: '$from_date', To Date: '$to_date', Page: $page");

// Debug: Log job_sheets schema
$result = $conn->query("SHOW COLUMNS FROM job_sheets");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}
error_log("job_sheets columns: " . implode(", ", $columns));

// Handle CSV download for all debitors
if (isset($_GET['download_all'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="debitor_list.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Customer Name', 'Total Debit Amount']);

    $sql = "SELECT js.customer_name, 
                   (COALESCE(SUM(js.total_charges), 0) - COALESCE(SUM(pr.cash + pr.credit), 0)) as total_debit
            FROM job_sheets js
            LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id";
    $params = [];
    $types = "";
    $where_conditions = [];

    if (!empty($search)) {
        $where_conditions[] = "js.customer_name LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }
    if ($from_date) {
        $where_conditions[] = "js.created_at >= ?";
        $params[] = $from_date;
        $types .= "s";
    }
    if ($to_date) {
        $where_conditions[] = "js.created_at <= ?";
        $params[] = $to_date . " 23:59:59";
        $types .= "s";
    }

    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }

    $sql .= " GROUP BY js.customer_name HAVING total_debit > 0 ORDER BY js.customer_name";
    
    try {
        $stmt = $conn->prepare($sql);
        if ($types && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            throw new Exception("CSV SQL Error: " . $stmt->error);
        }
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['customer_name'],
                number_format($row['total_debit'], 2)
            ]);
        }
        fclose($output);
        $stmt->close();
        exit;
    } catch (Exception $e) {
        error_log("Debitor_list.php - CSV Error: " . $e->getMessage());
        exit("Unable to generate CSV. Please check the database configuration.");
    }
}

// Fetch customers for pagination
$sql_count = "SELECT COUNT(DISTINCT js.customer_name) as total
              FROM job_sheets js
              LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id";
$params = [];
$types = "";
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "js.customer_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if ($from_date) {
    $where_conditions[] = "js.created_at >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if ($to_date) {
    $where_conditions[] = "js.created_at <= ?";
    $params[] = $to_date . " 23:59:59";
    $types .= "s";
}

if (!empty($where_conditions)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql_count .= " GROUP BY js.customer_name HAVING (COALESCE(SUM(js.total_charges), 0) - COALESCE(SUM(pr.cash + pr.credit), 0)) > 0";

try {
    $stmt = $conn->prepare($sql_count);
    if ($types && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        throw new Exception("Count SQL Error: " . $stmt->error);
    }
    $result = $stmt->get_result();
    $total_records = $result->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $records_per_page);
    $stmt->close();
} catch (Exception $e) {
    error_log("Debitor_list.php - Count Error: " . $e->getMessage());
    $error_message = "Unable to count debitors. Please check the database configuration.";
    $total_records = 0;
    $total_pages = 1;
}

// Fetch customers
$sql = "SELECT js.customer_name, 
               (COALESCE(SUM(js.total_charges), 0) - COALESCE(SUM(pr.cash + pr.credit), 0)) as total_debit
        FROM job_sheets js
        LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id";
$params = [];
$types = "";
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "js.customer_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if ($from_date) {
    $where_conditions[] = "js.created_at >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if ($to_date) {
    $where_conditions[] = "js.created_at <= ?";
    $params[] = $to_date . " 23:59:59";
    $types .= "s";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " GROUP BY js.customer_name HAVING total_debit > 0 ORDER BY js.customer_name LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

try {
    $stmt = $conn->prepare($sql);
    if ($types && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        throw new Exception("Fetch SQL Error: " . $stmt->error);
    }
    $customers_result = $stmt->get_result();
    $stmt->close();
} catch (Exception $e) {
    error_log("Debitor_list.php - Fetch Error: " . $e->getMessage());
    $error_message = "Unable to fetch debitors. Please check the database configuration.";
    $customers_result = null;
}

// Calculate total debit for display
$total_debit_all = 0;
if ($customers_result && $customers_result->num_rows > 0) {
    $customers_result->data_seek(0);
    while ($row = $customers_result->fetch_assoc()) {
        $total_debit_all += $row['total_debit'];
    }
    $customers_result->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debitor List</title>
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
            color: #ffc107;
            font-size: 24px;
            margin: 0;
        }
        .nav-buttons button {
            background-color: #ffc107;
            color: #333;
            border: none;
            padding: 10px 20px;
            margin-left: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .nav-buttons button:hover {
            background-color: #e0a800;
        }
        .debitor-container {
            width: 95%;
            max-width: 1200px;
            margin: 30px auto;
            padding: 25px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        .debitor-container h2 {
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
        .search-container {
            position: relative;
            width: 100%;
            max-width: 400px;
        }
        .search-container input[type="text"] {
            width: 100%;
            padding: 12px 50px 12px 20px;
            border: 2px solid #ffc107;
            border-radius: 30px;
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s;
        }
        .search-container input[type="text"]:focus {
            border-color: #e0a800;
        }
        .search-container .clear-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #dc3545;
            font-size: 18px;
            cursor: pointer;
        }
        .date-container {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .date-container label {
            color: #555;
            font-weight: 500;
        }
        .date-container input[type="date"] {
            padding: 10px 15px;
            border: 2px solid #ffc107;
            border-radius: 30px;
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s;
        }
        .date-container input[type="date"]:focus {
            border-color: #e0a800;
        }
        .filter-container button {
            background-color: #ffc107;
            color: #333;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }
        .filter-container button:hover {
            background-color: #e0a800;
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
            background-color: #ffc107;
            color: #333;
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
        .total-row td {
            font-weight: 600;
            background-color: #e9ecef;
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
            color: #ffc107;
            border: 2px solid #ffc107;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        .pagination a:hover {
            background-color: #ffc107;
            color: #333;
        }
        .pagination a.active {
            background-color: #ffc107;
            color: #333;
            font-weight: 600;
        }
        .debitor-container p.error-message {
            color: #dc3545;
            font-size: 18px;
            font-weight: 500;
            text-align: center;
            margin: 20px 0;
        }
        @media (max-width: 768px) {
            .debitor-container {
                width: 90%;
                padding: 15px;
            }
            .filter-container {
                flex-direction: column;
                gap: 15px;
            }
            .date-container {
                flex-direction: column;
                align-items: flex-start;
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
            const search = document.getElementById('searchInput').value;
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            console.log("Search:", search, "From:", fromDate, "To:", toDate);
            if (fromDate && toDate && new Date(fromDate) > new Date(toDate)) {
                alert("From date cannot be later than To date.");
                return;
            }
            window.location.href = 'debitor_list.php?search=' + encodeURIComponent(search) +
                                  '&from_date=' + fromDate +
                                  '&to_date=' + toDate +
                                  '&page=1';
        }

        function printAllCustomers() {
            const navbar = document.querySelector('.navbar');
            const filterContainer = document.querySelector('.filter-container');
            const pagination = document.querySelector('.pagination');

            if (navbar) navbar.style.display = 'none';
            if (filterContainer) filterContainer.style.display = 'none';
            if (pagination) pagination.style.display = 'none';

            window.print();

            if (navbar) navbar.style.display = 'flex';
            if (filterContainer) filterContainer.style.display = 'flex';
            if (pagination) pagination.style.display = 'flex';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const clearBtn = document.getElementById('clearBtn');
            const fromDate = document.getElementById('fromDate');
            const toDate = document.getElementById('toDate');
            let debounceTimeout;

            function toggleClearButton() {
                clearBtn.style.display = searchInput.value.length > 0 ? 'block' : 'none';
            }

            function debounceSearch() {
                clearTimeout(debounceTimeout);
                debounceTimeout = setTimeout(() => {
                    applyFilter();
                }, 500);
            }

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    toggleClearButton();
                    debounceSearch();
                });

                searchInput.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        clearTimeout(debounceTimeout);
                        applyFilter();
                    }
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    console.log("Clearing search input");
                    if (searchInput) searchInput.value = '';
                    toggleClearButton();
                    applyFilter();
                });
            }

            if (fromDate) fromDate.addEventListener('change', applyFilter);
            if (toDate) toDate.addEventListener('change', applyFilter);

            toggleClearButton();
        });
    </script>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Debitor List</h2>
        <div class="nav-buttons">
            <button onclick="location.href='<?php echo $dashboard_url; ?>'">Back to Dashboard</button>
            <button onclick="location.href='reports.php'">Back to Reports</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>
    <div class="debitor-container">
        <h2>Debitor Customers</h2>
        <div class="filter-container">
            <div class="search-container">
                <input type="text" id="searchInput" name="search" placeholder="Search by Customer Name" value="<?php echo htmlspecialchars($search); ?>">
                <button id="clearBtn" class="clear-btn" style="display: none;"><i class="fas fa-times"></i></button>
            </div>
            <div class="date-container">
                <label for="fromDate">From:</label>
                <input type="date" id="fromDate" value="<?php echo htmlspecialchars($from_date); ?>">
                <label for="toDate">To:</label>
                <input type="date" id="toDate" value="<?php echo htmlspecialchars($to_date); ?>">
            </div>
            <button class="print-all-btn" onclick="printAllCustomers()">Print All</button>
            <button class="download-btn" onclick="window.location.href='debitor_list.php?download_all=1&search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>'">Download All CSV</button>
        </div>
        <?php if (isset($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php elseif ($customers_result && $customers_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Total Debit Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($customer = $customers_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                            <td>₹<?php echo number_format($customer['total_debit'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <tr class="total-row">
                        <td><strong>Total</strong></td>
                        <td><strong>₹<?php echo number_format($total_debit_all, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="debitor_list.php?search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&page=<?php echo $page - 1; ?>">« Previous</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="debitor_list.php?search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="debitor_list.php?search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&page=<?php echo $page + 1; ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="error-message">No debitor customers found.</p>
        <?php endif; ?>
    </div>
</body>
</html>