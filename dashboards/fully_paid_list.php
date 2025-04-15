<?php
session_start();
include '../database/db_connect.php';

// Determine dashboard based on user role
$dashboard_url = 'accounts_dashboard.php'; // Default
if (isset($_SESSION['role'])) {
    error_log("Fully_paid_list.php - User role: " . $_SESSION['role']);
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
            error_log("Fully_paid_list.php - Unrecognized role: " . $_SESSION['role']);
            $dashboard_url = 'accounts_dashboard.php';
    }
} else {
    error_log("Fully_paid_list.php - Session role not set");
    header("Location: login.php");
    exit;
}

// Initialize variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$from_date = isset($_GET['from_date']) && $_GET['from_date'] ? $_GET['from_date'] : null;
$to_date = isset($_GET['to_date']) && $_GET['to_date'] ? $_GET['to_date'] : null;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Handle CSV download for all fully paid (summary data)
if (isset($_GET['download_all'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="fully_paid_summary.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Customer Name', 'Total Charges', 'Paid Amount', 'Balance']);

    $sql = "SELECT customer_name,
                   SUM(total_charges) as total_charges,
                   SUM(total_paid) as total_paid,
                   SUM(total_charges - total_paid) as total_balance
            FROM (
                SELECT js.customer_name,
                       js.total_charges,
                       COALESCE(SUM(pr.cash + pr.credit), 0) as total_paid
                FROM job_sheets js
                LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id
                WHERE js.payment_status = 'completed'
                GROUP BY js.id, js.customer_name, js.total_charges
                HAVING (js.total_charges - COALESCE(SUM(pr.cash + pr.credit), 0)) = 0
            ) as subquery";
    $params = [];
    $types = "";

    if (!empty($search)) {
        $sql .= " WHERE customer_name LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }
    if ($from_date) {
        $sql .= $search ? " AND" : " WHERE";
        $sql .= " EXISTS (
                    SELECT 1 FROM job_sheets js2
                    WHERE js2.customer_name = subquery.customer_name
                    AND js2.created_at >= ?
                    AND js2.payment_status = 'completed'
                )";
        $params[] = $from_date;
        $types .= "s";
    }
    if ($to_date) {
        $sql .= $search || $from_date ? " AND" : " WHERE";
        $sql .= " EXISTS (
                    SELECT 1 FROM job_sheets js2
                    WHERE js2.customer_name = subquery.customer_name
                    AND js2.created_at <= ?
                    AND js2.payment_status = 'completed'
                )";
        $params[] = $to_date . " 23:59:59";
        $types .= "s";
    }

    $sql .= " GROUP BY customer_name
              ORDER BY customer_name";
    $stmt = $conn->prepare($sql);
    if ($types && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['customer_name'],
            number_format($row['total_charges'], 2),
            number_format($row['total_paid'], 2),
            number_format($row['total_balance'], 2)
        ]);
    }
    fclose($output);
    $stmt->close();
    exit;
}

// Handle CSV download for a single customer (detailed data)
if (isset($_GET['download_customer'])) {
    $customer_name = $_GET['download_customer'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="fully_paid_' . preg_replace('/[^a-zA-Z0-9]/', '_', $customer_name) . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Customer Name', 'Job Sheet ID', 'Job Name', 'Total Charges', 'Paid Amount', 'Balance', 'Payment Status', 'Created At']);

    $sql = "SELECT js.customer_name, js.id, js.job_name, js.total_charges, 
                   COALESCE(SUM(pr.cash + pr.credit), 0) as total_paid,
                   (js.total_charges - COALESCE(SUM(pr.cash + pr.credit), 0)) as balance,
                   js.payment_status,
                   js.created_at
            FROM job_sheets js
            LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id
            WHERE js.customer_name = ? AND js.payment_status = 'completed'";
    $params = [$customer_name];
    $types = "s";

    if ($from_date) {
        $sql .= " AND js.created_at >= ?";
        $params[] = $from_date;
        $types .= "s";
    }
    if ($to_date) {
        $sql .= " AND js.created_at <= ?";
        $params[] = $to_date . " 23:59:59";
        $types .= "s";
    }

    $sql .= " GROUP BY js.id, js.customer_name, js.job_name, js.total_charges, js.payment_status, js.created_at
              HAVING balance = 0
              ORDER BY js.id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['customer_name'],
            $row['id'],
            $row['job_name'],
            number_format($row['total_charges'], 2),
            number_format($row['total_paid'], 2),
            number_format($row['balance'], 2),
            $row['payment_status'],
            $row['created_at']
        ]);
    }
    fclose($output);
    $stmt->close();
    exit;
}

// Fetch customers for pagination
$sql_count = "SELECT COUNT(DISTINCT customer_name) as total
              FROM (
                  SELECT js.customer_name
                  FROM job_sheets js
                  LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id
                  WHERE js.payment_status = 'completed'";
$params = [];
$types = "";

if (!empty($search)) {
    $sql_count .= " AND js.customer_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if ($from_date) {
    $sql_count .= " AND js.created_at >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if ($to_date) {
    $sql_count .= " AND js.created_at <= ?";
    $params[] = $to_date . " 23:59:59";
    $types .= "s";
}

$sql_count .= " GROUP BY js.id, js.customer_name, js.total_charges
                HAVING (js.total_charges - COALESCE(SUM(pr.cash + pr.credit), 0)) = 0
              ) as subquery";
$stmt = $conn->prepare($sql_count);
if ($types && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$total_records = $result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);
$stmt->close();

// Fetch customers with summary data
$sql = "SELECT customer_name,
               SUM(total_charges) as total_charges,
               SUM(total_paid) as total_paid,
               SUM(total_charges - total_paid) as total_balance
        FROM (
            SELECT js.customer_name,
                   js.total_charges,
                   COALESCE(SUM(pr.cash + pr.credit), 0) as total_paid
            FROM job_sheets js
            LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id
            WHERE js.payment_status = 'completed'";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND js.customer_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if ($from_date) {
    $sql .= " AND js.created_at >= ?";
    $params[] = $from_date;
    $types .= "s";
}
if ($to_date) {
    $sql .= " AND js.created_at <= ?";
    $params[] = $to_date . " 23:59:59";
    $types .= "s";
}

$sql .= " GROUP BY js.id, js.customer_name, js.total_charges
          HAVING (js.total_charges - COALESCE(SUM(pr.cash + pr.credit), 0)) = 0
        ) as subquery
        GROUP BY customer_name
        ORDER BY customer_name
        LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if ($types && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$customers_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fully Paid List</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .fully-paid-container {
            width: 90%;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #28a745;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .download-btn, .print-btn, .print-all-btn, .expand-btn {
            padding: 5px 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .download-btn:hover, .print-btn:hover, .print-all-btn:hover, .expand-btn:hover {
            background-color: #0056b3;
        }
        .filter-container {
            text-align: center;
            margin: 20px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .search-container {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            max-width: 500px;
            position: relative;
        }
        .search-container input[type="text"] {
            flex: 1;
            padding: 12px 40px 12px 20px;
            border-radius: 25px;
            border: 2px solid #28a745;
            font-size: 16px;
        }
        .search-container .clear-btn {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #dc3545;
            font-size: 18px;
            cursor: pointer;
        }
        .date-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .date-container input[type="date"] {
            padding: 10px;
            border-radius: 25px;
            border: 2px solid #28a745;
            font-size: 16px;
        }
        .date-container label {
            font-weight: bold;
        }
        .pagination {
            text-align: center;
            margin: 20px 0;
        }
        .pagination a {
            padding: 8px 16px;
            text-decoration: none;
            color: #28a745;
            border: 1px solid #28a745;
            border-radius: 5px;
            margin: 0 5px;
        }
        .pagination a:hover {
            background-color: #28a745;
            color: white;
        }
        .pagination a.active {
            background-color: #28a745;
            color: white;
        }
        .customer-section {
            margin-bottom: 20px;
            display: none;
        }
        .fully-paid-container p {
            color: #dc3545;
            font-weight: bold;
            text-align: center;
        }
        .expand-btn i {
            margin-right: 5px;
        }
        @media print {
            .summary-table th:nth-child(5),
            .summary-table td:nth-child(5) {
                display: none;
            }
        }
    </style>
    <script>
        function applyFilter() {
            const search = document.getElementById('searchInput').value;
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            if (fromDate && toDate && new Date(fromDate) > new Date(toDate)) {
                alert("From date cannot be later than To date.");
                return;
            }
            window.location.href = 'fully_paid_list.php?search=' + encodeURIComponent(search) +
                                  '&from_date=' + fromDate +
                                  '&to_date=' + toDate +
                                  '&page=1';
        }

        function toggleDetails(customerName) {
            const section = document.getElementById('details-' + customerName.replace(/[^a-zA-Z0-9]/g, '_'));
            const button = document.querySelector(`button[data-customer="${customerName}"]`);
            if (section.style.display === 'none' || section.style.display === '') {
                section.style.display = 'block';
                button.innerHTML = '<i class="fas fa-minus"></i> Collapse';
            } else {
                section.style.display = 'none';
                button.innerHTML = '<i class="fas fa-plus"></i> Expand';
            }
        }

        function printCustomer(customerName) {
            const allSections = document.querySelectorAll('.customer-section');
            const summaryTable = document.querySelector('.summary-table');
            const navbar = document.querySelector('.navbar');
            const filterContainer = document.querySelector('.filter-container');
            const pagination = document.querySelector('.pagination');
            const printAllBtn = document.querySelector('.print-all-btn');
            const downloadButtons = document.querySelectorAll('.download-btn');
            const printButtons = document.querySelectorAll('.print-btn');
            const expandButtons = document.querySelectorAll('.expand-btn');

            // Store original display states
            const originalStyles = {
                sections: [],
                summaryTable: summaryTable ? summaryTable.style.display : '',
                navbar: navbar ? navbar.style.display : '',
                filterContainer: filterContainer ? filterContainer.style.display : '',
                pagination: pagination ? pagination.style.display : '',
                printAllBtn: printAllBtn ? printAllBtn.style.display : '',
                downloadButtons: [],
                printButtons: [],
                expandButtons: []
            };

            allSections.forEach(section => {
                originalStyles.sections.push(section.style.display);
                section.style.display = section.id === 'details-' + customerName.replace(/[^a-zA-Z0-9]/g, '_') ? 'block' : 'none';
            });
            if (summaryTable) summaryTable.style.display = 'none';
            if (navbar) navbar.style.display = 'none';
            if (filterContainer) filterContainer.style.display = 'none';
            if (pagination) pagination.style.display = 'none';
            if (printAllBtn) printAllBtn.style.display = 'none';
            downloadButtons.forEach(btn => {
                originalStyles.downloadButtons.push(btn.style.display);
                btn.style.display = 'none';
            });
            printButtons.forEach(btn => {
                originalStyles.printButtons.push(btn.style.display);
                btn.style.display = 'none';
            });
            expandButtons.forEach(btn => {
                originalStyles.expandButtons.push(btn.style.display);
                btn.style.display = 'none';
            });

            window.print();

            // Restore original display states
            allSections.forEach((section, index) => {
                section.style.display = originalStyles.sections[index] || 'none';
            });
            if (summaryTable) summaryTable.style.display = originalStyles.summaryTable || 'table';
            if (navbar) navbar.style.display = originalStyles.navbar || 'block';
            if (filterContainer) filterContainer.style.display = originalStyles.filterContainer || 'flex';
            if (pagination) pagination.style.display = originalStyles.pagination || 'block';
            if (printAllBtn) printAllBtn.style.display = originalStyles.printAllBtn || 'inline-block';
            downloadButtons.forEach((btn, index) => {
                btn.style.display = originalStyles.downloadButtons[index] || 'inline-block';
            });
            printButtons.forEach((btn, index) => {
                btn.style.display = originalStyles.printButtons[index] || 'inline-block';
            });
            expandButtons.forEach((btn, index) => {
                btn.style.display = originalStyles.expandButtons[index] || 'inline-block';
            });
        }

        function printAllCustomers() {
            const navbar = document.querySelector('.navbar');
            const filterContainer = document.querySelector('.filter-container');
            const pagination = document.querySelector('.pagination');
            const printAllBtn = document.querySelector('.print-all-btn');
            const summaryTable = document.querySelector('.summary-table');
            const allSections = document.querySelectorAll('.customer-section');
            const downloadButtons = document.querySelectorAll('.download-btn');
            const printButtons = document.querySelectorAll('.print-btn');
            const expandButtons = document.querySelectorAll('.expand-btn');

            // Store original display states
            const originalStyles = {
                navbar: navbar ? navbar.style.display : '',
                filterContainer: filterContainer ? filterContainer.style.display : '',
                pagination: pagination ? pagination.style.display : '',
                printAllBtn: printAllBtn ? printAllBtn.style.display : '',
                summaryTable: summaryTable ? summaryTable.style.display : '',
                sections: [],
                downloadButtons: [],
                printButtons: [],
                expandButtons: []
            };

            allSections.forEach(section => {
                originalStyles.sections.push(section.style.display);
                section.style.display = 'none';
            });
            if (summaryTable) summaryTable.style.display = 'table';
            if (navbar) navbar.style.display = 'none';
            if (filterContainer) filterContainer.style.display = 'none';
            if (pagination) pagination.style.display = 'none';
            if (printAllBtn) printAllBtn.style.display = 'none';
            downloadButtons.forEach(btn => {
                originalStyles.downloadButtons.push(btn.style.display);
                btn.style.display = 'none';
            });
            printButtons.forEach(btn => {
                originalStyles.printButtons.push(btn.style.display);
                btn.style.display = 'none';
            });
            expandButtons.forEach(btn => {
                originalStyles.expandButtons.push(btn.style.display);
                btn.style.display = 'none';
            });

            window.print();

            // Restore original display states
            allSections.forEach((section, index) => {
                section.style.display = originalStyles.sections[index] || 'none';
            });
            if (summaryTable) summaryTable.style.display = originalStyles.summaryTable || 'table';
            if (navbar) navbar.style.display = originalStyles.navbar || 'block';
            if (filterContainer) filterContainer.style.display = originalStyles.filterContainer || 'flex';
            if (pagination) pagination.style.display = originalStyles.pagination || 'block';
            if (printAllBtn) printAllBtn.style.display = originalStyles.printAllBtn || 'inline-block';
            downloadButtons.forEach((btn, index) => {
                btn.style.display = originalStyles.downloadButtons[index] || 'inline-block';
            });
            printButtons.forEach((btn, index) => {
                btn.style.display = originalStyles.printButtons[index] || 'inline-block';
            });
            expandButtons.forEach((btn, index) => {
                btn.style.display = originalStyles.expandButtons[index] || 'inline-block';
            });
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
        <h2 class="brand">Fully Paid List</h2>
        <div class="nav-buttons">
            <button onclick="location.href='<?php echo $dashboard_url; ?>'">Back to Dashboard</button>
            <button onclick="location.href='reports.php'">Back to Reports</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>
    <div class="fully-paid-container">
        <h2>Fully Paid Customers</h2>
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
            <button class="download-btn" onclick="window.location.href='fully_paid_list.php?download_all=1&search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>'">Download All CSV</button>
        </div>
        <?php if ($customers_result && $customers_result->num_rows > 0): ?>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Total Charges</th>
                        <th>Paid Amount</th>
                        <th>Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($customer = $customers_result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                            <td>₹<?php echo number_format($customer['total_charges'], 2); ?></td>
                            <td>₹<?php echo number_format($customer['total_paid'], 2); ?></td>
                            <td>₹<?php echo number_format($customer['total_balance'], 2); ?></td>
                            <td>
                                <button class="expand-btn" data-customer="<?php echo htmlspecialchars($customer['customer_name']); ?>" onclick="toggleDetails('<?php echo htmlspecialchars($customer['customer_name']); ?>')"><i class="fas fa-plus"></i> Expand</button>
                                <button class="download-btn" onclick="window.location.href='fully_paid_list.php?download_customer=<?php echo urlencode($customer['customer_name']); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>'">Download CSV</button>
                                <button class="print-btn" onclick="printCustomer('<?php echo htmlspecialchars($customer['customer_name']); ?>')">Print</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php
            // Reset result pointer to fetch customers again for detailed sections
            $customers_result->data_seek(0);
            while ($customer = $customers_result->fetch_assoc()):
                $safe_customer_id = preg_replace('/[^a-zA-Z0-9]/', '_', $customer['customer_name']);
            ?>
                <div class="customer-section" id="details-<?php echo $safe_customer_id; ?>">
                    <h3><?php echo htmlspecialchars($customer['customer_name']); ?></h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Job Sheet ID</th>
                                <th>Job Name</th>
                                <th>Total Charges</th>
                                <th>Paid Amount</th>
                                <th>Balance</th>
                                <th>Payment Status</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql_jobs = "SELECT js.id, js.job_name, js.total_charges,
                                                COALESCE(SUM(pr.cash + pr.credit), 0) as total_paid,
                                                (js.total_charges - COALESCE(SUM(pr.cash + pr.credit), 0)) as balance,
                                                js.payment_status,
                                                js.created_at
                                         FROM job_sheets js
                                         LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id
                                         WHERE js.customer_name = ? AND js.payment_status = 'completed'";
                            $params = [$customer['customer_name']];
                            $types = "s";

                            if ($from_date) {
                                $sql_jobs .= " AND js.created_at >= ?";
                                $params[] = $from_date;
                                $types .= "s";
                            }
                            if ($to_date) {
                                $sql_jobs .= " AND js.created_at <= ?";
                                $params[] = $to_date . " 23:59:59";
                                $types .= "s";
                            }

                            $sql_jobs .= " GROUP BY js.id, js.job_name, js.total_charges, js.payment_status, js.created_at
                                           HAVING balance = 0
                                           ORDER BY js.id";
                            $stmt = $conn->prepare($sql_jobs);
                            $stmt->bind_param($types, ...$params);
                            $stmt->execute();
                            $jobs_result = $stmt->get_result();
                            $total_charges = 0;
                            $total_paid = 0;
                            $total_balance = 0;
                            while ($job = $jobs_result->fetch_assoc()):
                                $total_charges += $job['total_charges'];
                                $total_paid += $job['total_paid'];
                                $total_balance += $job['balance'];
                            ?>
                                <tr>
                                    <td><?php echo $job['id']; ?></td>
                                    <td><?php echo htmlspecialchars($job['job_name']); ?></td>
                                    <td>₹<?php echo number_format($job['total_charges'], 2); ?></td>
                                    <td>₹<?php echo number_format($job['total_paid'], 2); ?></td>
                                    <td>₹<?php echo number_format($job['balance'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($job['payment_status']); ?></td>
                                    <td><?php echo $job['created_at']; ?></td>
                                </tr>
                            <?php endwhile; $stmt->close(); ?>
                            <tr>
                                <td colspan="2"><strong>Total</strong></td>
                                <td><strong>₹<?php echo number_format($total_charges, 2); ?></strong></td>
                                <td><strong>₹<?php echo number_format($total_paid, 2); ?></strong></td>
                                <td><strong>₹<?php echo number_format($total_balance, 2); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endwhile; ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="fully_paid_list.php?search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&page=<?php echo $page - 1; ?>">« Previous</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="fully_paid_list.php?search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="fully_paid_list.php?search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&page=<?php echo $page + 1; ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>No fully paid customers found.</p>
        <?php endif; ?>
    </div>
</body>
</html>