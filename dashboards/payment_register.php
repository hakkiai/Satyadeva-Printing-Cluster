<?php
session_start();
include '../database/db_connect.php';
date_default_timezone_set('Asia/Kolkata');

// Determine dashboard based on user role
$dashboard_url = 'accounts_dashboard.php'; // Default
if (isset($_SESSION['role'])) {
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
            error_log("Payment_register.php - Unrecognized role: " . $_SESSION['role']);
            $dashboard_url = 'accounts_dashboard.php';
    }
}

// Get query parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Handle CSV download
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    $sql_csv = "SELECT pr.job_sheet_id, pr.job_sheet_name, pr.date, pr.cash, pr.credit, pr.balance, pr.payment_type, pr.payment_status, 
                       js.customer_name, js.total_charges 
                FROM payment_records pr 
                LEFT JOIN job_sheets js ON pr.job_sheet_id = js.id 
                WHERE 1=1";
    if (!empty($search)) {
        $search = $conn->real_escape_string($search);
        $sql_csv .= " AND js.customer_name LIKE '%$search%'";
    }
    if (!empty($from_date) && !empty($to_date)) {
        $sql_csv .= " AND pr.date BETWEEN '$from_date 00:00:00' AND '$to_date 23:59:59'";
    } elseif (!empty($from_date)) {
        $sql_csv .= " AND pr.date >= '$from_date 00:00:00'";
    } elseif (!empty($to_date)) {
        $sql_csv .= " AND pr.date <= '$to_date 23:59:59'";
    }
    $sql_csv .= " ORDER BY pr.date DESC";
    $result_csv = $conn->query($sql_csv);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payment_register_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Job ID', 'Customer Name', 'Job Name', 'Date & Time', 'Payment Method', 'Amount Paid', 'Total Charges', 'Balance', 'Payment Status']);

    while ($row = $result_csv->fetch_assoc()) {
        $amount = ($row['payment_type'] === 'credit') ? $row['credit'] : $row['cash'];
        $status = ($row['payment_status'] === 'completed') ? 'Fully Paid' : (($row['payment_type'] === 'credit') ? 'Partially Paid' : 'Partially Paid');
        fputcsv($output, [
            $row['job_sheet_id'],
            $row['customer_name'] ?? 'N/A',
            $row['job_sheet_name'] ?? 'N/A',
            date('d-M-Y h:i:s A', strtotime($row['date'])),
            ucfirst($row['payment_type']),
            '₹' . number_format($amount, 2),
            '₹' . number_format($row['total_charges'] ?? 0, 2),
            '₹' . number_format($row['balance'], 2),
            $status
        ]);
    }
    fclose($output);
    exit;
}

// Build the count query for pagination
$sql_count = "SELECT COUNT(*) as total 
              FROM payment_records pr 
              LEFT JOIN job_sheets js ON pr.job_sheet_id = js.id 
              WHERE 1=1";
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $sql_count .= " AND js.customer_name LIKE '%$search%'";
}
if (!empty($from_date) && !empty($to_date)) {
    $sql_count .= " AND pr.date BETWEEN '$from_date 00:00:00' AND '$to_date 23:59:59'";
} elseif (!empty($from_date)) {
    $sql_count .= " AND pr.date >= '$from_date 00:00:00'";
} elseif (!empty($to_date)) {
    $sql_count .= " AND pr.date <= '$to_date 23:59:59'";
}
$count_result = $conn->query($sql_count);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch payment records
$sql = "SELECT pr.job_sheet_id, pr.job_sheet_name, pr.date, pr.cash, pr.credit, pr.balance, pr.payment_type, pr.payment_status, 
               js.customer_name, js.total_charges 
        FROM payment_records pr 
        LEFT JOIN job_sheets js ON pr.job_sheet_id = js.id 
        WHERE 1=1";
if (!empty($search)) {
    $sql .= " AND js.customer_name LIKE '%$search%'";
}
if (!empty($from_date) && !empty($to_date)) {
    $sql .= " AND pr.date BETWEEN '$from_date 00:00:00' AND '$to_date 23:59:59'";
} elseif (!empty($from_date)) {
    $sql .= " AND pr.date >= '$from_date 00:00:00'";
} elseif (!empty($to_date)) {
    $sql .= " AND pr.date <= '$to_date 23:59:59'";
}
$sql .= " ORDER BY pr.date DESC LIMIT $offset, $records_per_page";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Register</title>
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
        .reports-container {
            width: 95%;
            max-width: 1200px;
            margin: 30px auto;
            padding: 25px;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        .reports-container h3 {
            color: #333;
            font-size: 28px;
            margin-bottom: 20px;
            text-align: center;
        }
        table { 
            width: 100%; 
            margin: 20px 0; 
            border-collapse: collapse; 
            background-color: white; 
            border-radius: 10px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: center; 
        }
        th { 
            background-color: #007bff; 
            color: white; 
            font-size: 16px; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
        }
        tr:nth-child(even) { 
            background-color: #f9f9f9; 
        }
        tr:hover { 
            background-color: #f1f1f1; 
            transition: background-color 0.3s ease; 
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
            border: 2px solid #007bff; 
            font-size: 16px; 
            background-color: #fff; 
            color: #333; 
            outline: none; 
            transition: border-color 0.3s ease, box-shadow 0.3s ease, transform 0.1s ease; 
        }
        .search-container input[type="text"]:hover, 
        .search-container input[type="text"]:focus { 
            border-color: #0056b3; 
            box-shadow: 0 0 15px rgba(0, 123, 255, 0.3); 
            transform: scale(1.02); 
        }
        .search-container input[type="text"]::placeholder { 
            color: #999; 
            font-style: italic; 
        }
        .search-container .clear-btn { 
            position: absolute; 
            right: 10px; 
            background: none; 
            border: none; 
            color: #dc3545; 
            font-size: 18px; 
            cursor: pointer; 
            transition: color 0.3s ease; 
        }
        .search-container .clear-btn:hover { 
            color: #c82333; 
        }
        .date-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .date-container input[type="date"] {
            padding: 10px;
            border-radius: 25px;
            border: 2px solid #007bff;
            font-size: 16px;
            background-color: #fff;
            color: #333;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .date-container input[type="date"]:hover,
        .date-container input[type="date"]:focus {
            border-color: #0056b3;
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.3);
        }
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        .print-btn, .download-btn {
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }
        .download-btn {
            background-color: #17a2b8;
        }
        .print-btn:hover {
            background-color: #218838;
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.5);
        }
        .download-btn:hover {
            background-color: #138496;
            box-shadow: 0 0 10px rgba(23, 162, 184, 0.5);
        }
        .pagination {
            text-align: center;
            margin: 20px 0;
        }
        .pagination a {
            padding: 8px 16px;
            text-decoration: none;
            color: #007bff;
            border: 1px solid #007bff;
            border-radius: 5px;
            margin: 0 5px;
            transition: background-color 0.3s ease;
        }
        .pagination a:hover {
            background-color: #007bff;
            color: white;
        }
        .pagination a.active {
            background-color: #007bff;
            color: white;
        }
        @media (max-width: 768px) {
            .reports-container {
                width: 90%;
                padding: 15px;
            }
            table {
                font-size: 14px;
            }
            th, td {
                padding: 8px;
            }
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
    <script>
    function applyFilter() {
        const search = document.getElementById('searchInput').value;
        const fromDate = document.getElementById('fromDate').value;
        const toDate = document.getElementById('toDate').value;
        window.location.href = 'payment_register.php?search=' + encodeURIComponent(search) + 
                              '&from_date=' + fromDate + 
                              '&to_date=' + toDate + 
                              '&page=1';
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

        searchInput.addEventListener('input', function() {
            toggleClearButton();
            debounceSearch();
        });

        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            toggleClearButton();
            applyFilter();
        });

        searchInput.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                clearTimeout(debounceTimeout);
                applyFilter();
            }
        });

        fromDate.addEventListener('change', applyFilter);
        toDate.addEventListener('change', applyFilter);

        toggleClearButton();
    });
    </script>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Payment Register</h2>
        <div class="nav-buttons">
            <button onclick="location.href='reports.php'">Back to Reports</button>
            <button onclick="location.href='<?php echo $dashboard_url; ?>'">Back to Dashboard</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="reports-container">
        <h3>Payment Register</h3>

        <!-- Search and Filter Section -->
        <div class="filter-container">
            <div class="search-container">
                <input type="text" id="searchInput" name="search" placeholder="Search by Customer Name" value="<?php echo htmlspecialchars($search); ?>">
                <button id="clearBtn" class="clear-btn" style="display: none;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="date-container">
                <label for="fromDate">From: </label>
                <input type="date" id="fromDate" value="<?php echo htmlspecialchars($from_date); ?>">
                <label for="toDate">To: </label>
                <input type="date" id="toDate" value="<?php echo htmlspecialchars($to_date); ?>">
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="print-btn" onclick="window.open('print_payment_register.php?search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>', '_blank')">Print</button>
            <button class="download-btn" onclick="window.location.href='payment_register.php?search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&download=csv'">Download</button>
        </div>

        <!-- Payment Records Table -->
        <?php if ($result && $result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>Customer Name</th>
                        <th>Job Name</th>
                        <th>Date & Time</th>
                        <th>Payment Method</th>
                        <th>Amount Paid</th>
                        <th>Total Charges</th>
                        <th>Balance</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['job_sheet_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['job_sheet_name'] ?? 'N/A'); ?></td>
                            <td><?php echo date('d-M-Y h:i:s A', strtotime($row['date'])); ?></td>
                            <td><?php echo ucfirst($row['payment_type']); ?></td>
                            <td>
                                ₹<?php 
                                $amount = ($row['payment_type'] === 'credit') ? $row['credit'] : $row['cash'];
                                echo number_format($amount, 2);
                                ?>
                            </td>
                            <td>₹<?php echo number_format($row['total_charges'] ?? 0, 2); ?></td>
                            <td>₹<?php echo number_format($row['balance'], 2); ?></td>
                            <td style="color: <?php echo ($row['payment_status'] === 'completed') ? 'green' : 'orange'; ?>; font-weight: bold;">
                                <?php
                                $status = ($row['payment_status'] === 'completed') ? 'Fully Paid' : (($row['payment_type'] === 'credit') ? 'Partially Paid' : 'Partially Paid');
                                echo $status;
                                ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination Links -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="payment_register.php?search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&page=<?php echo $page - 1; ?>">« Previous</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="payment_register.php?search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&page=<?php echo $i; ?>" class="<?php echo $i === 'active' ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="payment_register.php?search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>&page=<?php echo $page + 1; ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>No payment records found for the selected criteria.</p>
        <?php endif; ?>
    </div>
</body>
</html>