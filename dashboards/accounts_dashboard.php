<?php
include '../database/db_connect.php';

// Define QR library path and check if it exists
$qr_lib_path = '../lib/phpqrcode/qrlib.php'; // Adjust this path based on your directory structure
if (!file_exists($qr_lib_path)) {
    die("Error: Cannot find qrlib.php at '$qr_lib_path'. Please ensure the phpqrcode library is installed.");
}
require_once $qr_lib_path;

// Check if GD library is enabled
if (!function_exists('ImageCreate')) {
    die("Error: GD library is not enabled. Enable it in php.ini and restart your server.");
}

// Handle individual file download
if (isset($_GET['download_file']) && isset($_GET['job_id'])) {
    $job_id = $_GET['job_id'];
    $file_path = $_GET['download_file'];
    $sql = "SELECT file_path FROM job_sheets WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row && in_array($file_path, explode(',', $row['file_path']))) {
        if (file_exists($file_path) && is_readable($file_path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            echo "<script>alert('File not found or inaccessible!'); window.location.href='accounts_dashboard.php';</script>";
        }
    } else {
        echo "<script>alert('Invalid file or job!'); window.location.href='accounts_dashboard.php';</script>";
    }
}

// Get query parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'partially_paid';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
$to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$generate_qr = isset($_GET['generate_qr']) ? (int)$_GET['generate_qr'] : 0; // New parameter to trigger QR section
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Pagination count query
$sql_count = "SELECT COUNT(DISTINCT js.id) as total 
              FROM job_sheets js 
              LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id 
              WHERE js.completed_delivery = 1";
if ($filter === 'partially_paid') {
    $sql_count .= " AND js.payment_status IN ('partially_paid', 'uncredit')";
} elseif ($filter === 'fully_paid') {
    $sql_count .= " AND js.payment_status = 'completed'";
} elseif ($filter === 'not_paid') {
    $sql_count .= " AND (js.payment_status IS NULL OR js.payment_status = 'incomplete')";
}
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $sql_count .= " AND (js.customer_name LIKE '%$search%' OR js.id LIKE '%$search%')";
}
if (!empty($from_date) && !empty($to_date)) {
    $sql_count .= " AND js.created_at BETWEEN '$from_date' AND '$to_date'";
} elseif (!empty($from_date)) {
    $sql_count .= " AND js.created_at >= '$from_date'";
} elseif (!empty($to_date)) {
    $sql_count .= " AND js.created_at <= '$to_date'";
}
$count_result = $conn->query($sql_count);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch orders
$sql = "SELECT js.* 
        FROM job_sheets js 
        LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id 
        WHERE js.completed_delivery = 1";
if ($filter === 'partially_paid') {
    $sql .= " AND js.payment_status IN ('partially_paid', 'uncredit')";
} elseif ($filter === 'fully_paid') {
    $sql .= " AND js.payment_status = 'completed'";
} elseif ($filter === 'not_paid') {
    $sql .= " AND (js.payment_status IS NULL OR js.payment_status = 'incomplete')";
}
if (!empty($search)) {
    $sql .= " AND (js.customer_name LIKE '%$search%' OR js.id LIKE '%$search%')";
}
if (!empty($from_date) && !empty($to_date)) {
    $sql .= " AND js.created_at BETWEEN '$from_date' AND '$to_date'";
} elseif (!empty($from_date)) {
    $sql .= " AND js.created_at >= '$from_date'";
} elseif (!empty($to_date)) {
    $sql .= " AND js.created_at <= '$to_date'";
}
$sql .= " GROUP BY js.id ORDER BY js.id DESC LIMIT $offset, $records_per_page";
$result = $conn->query($sql);

// QR and Payment Summary Logic (only calculate if generate_qr=1)
$total_balance = 0;
$job_sheets = [];
$qr_path = '';
$upi_id = '';
$show_qr_section = false;

if ($generate_qr === 1 && !empty($search) && $filter === 'partially_paid' && !is_numeric($search)) {
    $sql_qr = "SELECT js.id, js.job_name, js.customer_name, js.description, js.total_charges, 
                      COALESCE(SUM(pr.cash + pr.credit), 0) as total_paid
               FROM job_sheets js
               LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id
               WHERE js.completed_delivery = 1
               AND js.payment_status IN ('partially_paid', 'uncredit')
               AND js.customer_name LIKE ?
               GROUP BY js.id, js.job_name, js.customer_name, js.description, js.total_charges";
    $stmt = $conn->prepare($sql_qr);
    $search_param = "%$search%";
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $qr_result = $stmt->get_result();

    while ($row = $qr_result->fetch_assoc()) {
        $total_paid = floatval($row['total_paid']);
        $total_charges = floatval($row['total_charges']);
        $balance = $total_charges - $total_paid;
        if ($balance > 0) {
            $total_balance += $balance;
            $job_sheets[] = [
                'id' => $row['id'],
                'job_name' => $row['job_name'],
                'customer_name' => $row['customer_name'],
                'description' => $row['description'] ?? 'No description',
                'total_charges' => $total_charges,
                'balance' => $balance
            ];
        }
    }

    if ($total_balance > 0) {
        $sql_upi = "SELECT upi_id, payee_name FROM upi_settings LIMIT 1";
        $upi_result = $conn->query($sql_upi);
        if ($upi_row = $upi_result->fetch_assoc()) {
            $upi_id = $upi_row['upi_id'];
            $payee_name = $upi_row['payee_name'];
        } else {
            die("Error: UPI settings not found in upi_settings table.");
        }

        $transaction_note = "Payment for $search (Multiple Jobs)";
        $upi_url = "upi://pay?pa=" . urlencode($upi_id) . "&pn=" . urlencode($payee_name) . "&am=" . number_format($total_balance, 2, '.', '') . "&cu=INR&tn=" . urlencode($transaction_note);

        $qr_dir = '../qrcodes/';
        if (!is_dir($qr_dir)) {
            if (!mkdir($qr_dir, 0755, true)) {
                die("Error: Failed to create directory '$qr_dir'. Check permissions.");
            }
        }
        $qr_file = $qr_dir . 'qr_' . md5($search . time()) . '.png';
        try {
            QRcode::png($upi_url, $qr_file, QR_ECLEVEL_L, 10);
            if (!file_exists($qr_file)) {
                die("Error: QR code file '$qr_file' was not created.");
            }
        } catch (Exception $e) {
            die("Error generating QR code: " . $e->getMessage());
        }

        $base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 2);
        $qr_path = "$base_url/qrcodes/" . basename($qr_file);
        $show_qr_section = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Accounts Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        table { 
            width: 90%; 
            margin: 20px auto; 
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
        .download-link { 
            color: #17a2b8; 
            text-decoration: none; 
            margin: 0 5px; 
            font-weight: bold; 
        }
        .download-link:hover { 
            text-decoration: underline; 
            color: #138496; 
        }
        .filter-container { 
            text-align: center; 
            margin: 20px 0; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            gap: 15px; 
        }
        .filter-container label { 
            font-size: 18px; 
            font-weight: bold; 
            color: #333; 
            margin-right: 10px; 
        }
        .filter-container select { 
            padding: 10px 20px; 
            border-radius: 25px; 
            border: 2px solid #007bff; 
            font-size: 16px; 
            background-color: #fff; 
            color: #333; 
            cursor: pointer; 
            transition: border-color 0.3s ease, box-shadow 0.3s ease; 
            outline: none; 
        }
        .filter-container select:hover { 
            border-color: #0056b3; 
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.3); 
        }
        .filter-container select:focus { 
            border-color: #0056b3; 
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.5); 
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
        .dropdown { 
            position: relative; 
            display: inline-block; 
        }
        .dropdown-btn { 
            padding: 8px 16px; 
            border: none; 
            border-radius: 5px; 
            background-color: #007bff; 
            color: white; 
            font-weight: bold; 
            cursor: pointer; 
            transition: background-color 0.3s ease, transform 0.1s ease, box-shadow 0.3s ease; 
        }
        .dropdown-btn:hover { 
            background-color: #0056b3; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); 
        }
        .dropdown-btn i { 
            margin-right: 5px; 
        }
        .dropdown-content { 
            display: none; 
            position: absolute; 
            background-color: #fff; 
            min-width: 160px; 
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2); 
            border-radius: 5px; 
            z-index: 1; 
            top: 100%; 
            left: 0; 
        }
        .dropdown-content a { 
            color: #333; 
            padding: 12px 16px; 
            text-decoration: none; 
            display: block; 
            font-size: 14px; 
            transition: background-color 0.3s ease; 
        }
        .dropdown-content a:hover { 
            background-color: #f1f1f1; 
        }
        .dropdown-content a i { 
            margin-right: 8px; 
        }
        .dropdown:hover .dropdown-content { 
            display: block; 
        }
        .dropdown-content a.view { 
            color: #28a745; 
        }
        .dropdown-content a.statement { 
            color: #ffc107; 
        }
        .dropdown-content a.print1 { 
            color: #dc3545; 
        }
        .dropdown-content a.print2 { 
            color: #17a2b8; 
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
        .print-page-btn, .generate-qr-btn {
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            margin: 0 10px;
        }
        .print-page-btn:hover, .generate-qr-btn:hover {
            background-color: #218838;
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.5);
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
        .qr-section {
            margin: 20px auto;
            width: 90%;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .qr-section table {
            width: 100%;
            margin: 0;
            box-shadow: none;
        }
        .qr-section .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        .qr-section .qr-code img {
            max-width: 250px;
            height: auto;
        }
        .payment-info {
            text-align: center;
            font-size: 16px;
            margin: 10px 0;
            font-weight: bold;
        }
    </style>
    <script>
    function applyFilter() {
        const filter = document.getElementById('paymentFilter').value;
        const search = document.getElementById('searchInput').value;
        const fromDate = document.getElementById('fromDate').value;
        const toDate = document.getElementById('toDate').value;
        window.location.href = 'accounts_dashboard.php?filter=' + filter + 
                              '&search=' + encodeURIComponent(search) + 
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
    <h2 class="brand">Accounts Dashboard</h2>
    <div class="nav-buttons">
        <button onclick="location.href='reports.php'">Reports</button>
        <button onclick="location.href='../auth/logout.php'">Logout</button>
    </div>
</div>

<div class="main-container">
    <div class="user-container">
        <h2>Orders</h2>

        <!-- Filter and Search Section -->
        <div class="filter-container">
            <div>
                <label for="paymentFilter">Filter by Payment Status: </label>
                <select id="paymentFilter" onchange="applyFilter()">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Orders</option>
                    <option value="partially_paid" <?= $filter === 'partially_paid' ? 'selected' : '' ?>>Partially Paid</option>
                    <option value="not_paid" <?= $filter === 'not_paid' ? 'selected' : '' ?>>Not Paid</option>
                    <option value="fully_paid" <?= $filter === 'fully_paid' ? 'selected' : '' ?>>Fully Paid</option>
                </select>
            </div>

            <div class="search-container">
                <input type="text" id="searchInput" name="search" placeholder="Search by Customer Name or Job Sheet ID" value="<?= htmlspecialchars($search) ?>">
                <button id="clearBtn" class="clear-btn" style="display: none;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="date-container">
                <label for="fromDate">From: </label>
                <input type="date" id="fromDate" value="<?= htmlspecialchars($from_date) ?>">
                <label for="toDate">To: </label>
                <input type="date" id="toDate" value="<?= htmlspecialchars($to_date) ?>">
            </div>

            <div>
                <button class="print-page-btn" onclick="window.open('print_page.php?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&page=<?= $page ?>', '_blank')">Print Page</button>
                <?php if (!empty($search) && $filter === 'partially_paid' && !is_numeric($search)): ?>
                    <button class="generate-qr-btn" onclick="window.location.href='accounts_dashboard.php?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&page=<?= $page ?>&generate_qr=1'">Generate QR</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer Name</th>
                        <th>Job Name</th>
                        <th>Total Charges (Excl. Paper)</th>
                        <th>File</th>
                        <th>Description</th>
                        <th>Payment Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['customer_name']) ?></td>
                            <td><?= htmlspecialchars($row['job_name']) ?></td>
                            <td>₹<?= number_format($row['total_charges'], 2) ?></td>
                            <td>
                                <?php 
                                $db_files = $row['file_path'] ? explode(',', $row['file_path']) : [];
                                if (!empty($db_files)): ?>
                                    <?php foreach ($db_files as $index => $file): ?>
                                        <a href="accounts_dashboard.php?download_file=<?= urlencode($file) ?>&job_id=<?= $row['id'] ?>" class="download-link">Download <?= $index + 1 ?></a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    No file
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['description'] ?? 'No description') ?></td>
                            <td style="color: <?= $row['payment_status'] === 'incomplete' || $row['payment_status'] === NULL ? 'red' : ($row['payment_status'] === 'partially_paid' ? 'orange' : ($row['payment_status'] === 'uncredit' ? '#ff4500' : 'green')); ?>; font-weight: bold;">
                                <?php
                                if ($row['payment_status'] === 'completed') {
                                    echo 'Fully Paid';
                                } elseif ($row['payment_status'] === 'partially_paid') {
                                    echo 'Partially Paid';
                                } elseif ($row['payment_status'] === 'uncredit') {
                                    echo 'Partially Paid';
                                } else {
                                    echo 'Pending';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="dropdown-btn">
                                        <i class="fas fa-ellipsis-v"></i> Actions
                                    </button>
                                    <div class="dropdown-content">
                                        <a href="accounts_view_order.php?id=<?= $row['id'] ?>" class="view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="payment_statement.php?job_id=<?= $row['id'] ?>" class="statement">
                                            <i class="fas fa-file-invoice"></i> Statement
                                        </a>
                                        <a href="print_account_job_sheet.php?id=<?= $row['id'] ?>&paper_charges=without" target="_blank" class="print1">
                                            <i class="fas fa-print"></i> Print 1
                                        </a>
                                        <a href="print_account_job_sheet.php?id=<?= $row['id'] ?>&paper_charges=with" target="_blank" class="print2">
                                            <i class="fas fa-print"></i> Print 2
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Pagination Links -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="accounts_dashboard.php?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&page=<?= $page - 1 ?>">« Previous</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="accounts_dashboard.php?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="accounts_dashboard.php?filter=<?= urlencode($filter) ?>&search=<?= urlencode($search) ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>&page=<?= $page + 1 ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>No orders found for the selected filter or search criteria.</p>
        <?php endif; ?>

        <!-- QR and Payment Summary Section (only shown after clicking Generate QR) -->
        <?php if ($show_qr_section): ?>
            <div class="qr-section" id="qrSection">
                <h2>Payment Summary for <?= htmlspecialchars($search) ?></h2>
                <table>
                    <tr>
                        <th>Job ID</th>
                        <th>Job Name</th>
                        <th>Customer Name</th>
                        <th>Description</th>
                        <th>Total Charges</th>
                        <th>Balance</th>
                    </tr>
                    <?php foreach ($job_sheets as $sheet): ?>
                        <tr>
                            <td><?= $sheet['id'] ?></td>
                            <td><?= htmlspecialchars($sheet['job_name']) ?></td>
                            <td><?= htmlspecialchars($sheet['customer_name']) ?></td>
                            <td><?= htmlspecialchars($sheet['description']) ?></td>
                            <td>₹<?= number_format($sheet['total_charges'], 2) ?></td>
                            <td>₹<?= number_format($sheet['balance'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="5"><strong>Total Balance</strong></td>
                        <td><strong>₹<?= number_format($total_balance, 2) ?></strong></td>
                    </tr>
                </table>
                <div class="payment-info">
                    <p>Pay Total Balance (₹<?= number_format($total_balance, 2) ?>) via UPI: <?= htmlspecialchars($upi_id) ?></p>
                </div>
                <div class="qr-code">
                    <img src="<?= htmlspecialchars($qr_path) ?>" alt="QR Code for Payment" onerror="this.style.display='none'; this.nextSibling.style.display='block';">
                    <p style="display: none; color: red;">Failed to load QR code image.</p>
                </div>
                <div style="text-align: center;">
                    <button class="print-page-btn" onclick="window.open('print_payment_summary.php?search=<?= urlencode($search) ?>&from_date=<?= urlencode($from_date) ?>&to_date=<?= urlencode($to_date) ?>', '_blank')">Print Payment Summary</button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>