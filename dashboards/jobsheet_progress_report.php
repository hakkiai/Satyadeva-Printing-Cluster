<?php
session_start();
include '../database/db_connect.php';

// Determine dashboard based on user role
$dashboard_url = 'accounts_dashboard.php';
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
    }
}

// Handle search and date filters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? $conn->real_escape_string($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? $conn->real_escape_string($_GET['to_date']) : '';

// Build query with filters
$sql = "
    SELECT 
        js.id,
        js.job_name,
        js.customer_name,
        js.status AS initial_status,
        js.ctp,
        js.completed_ctp,
        js.multicolour,
        js.completed_multicolour,
        js.digital,
        js.completed_digital,
        js.completed_delivery,
        js.completed,
        js.payment_status,
        COALESCE(
            (SELECT stage 
             FROM jobsheet_progress_history jph 
             WHERE jph.job_sheet_id = js.id 
             ORDER BY jph.created_at DESC 
             LIMIT 1),
            js.status
        ) AS current_stage,
        GROUP_CONCAT(
            CONCAT(jph.stage, '||', DATE_FORMAT(jph.created_at, '%Y-%m-%d %H:%i:%s'))
            ORDER BY jph.created_at
            SEPARATOR ';;;'
        ) AS progress_history,
        (js.ctp = 1 OR js.multicolour = 1 OR js.digital = 1 OR js.completed_delivery = 1) AS has_redirection,
        dj.dispatched_at
    FROM job_sheets js
    LEFT JOIN jobsheet_progress_history jph ON js.id = jph.job_sheet_id
    LEFT JOIN dispatch_jobs dj ON js.id = dj.id
    WHERE 1=1
";
if ($search) {
    $sql .= " AND (js.job_name LIKE '%$search%' OR js.customer_name LIKE '%$search%')";
}
if ($from_date) {
    $sql .= " AND js.created_at >= '$from_date 00:00:00'";
}
if ($to_date) {
    $sql .= " AND js.created_at <= '$to_date 23:59:59'";
}
$sql .= "
    GROUP BY js.id, js.job_name, js.customer_name, js.status, js.ctp, js.completed_ctp, 
             js.multicolour, js.completed_multicolour, js.digital, js.completed_digital, 
             js.completed_delivery, js.completed, dj.dispatched_at
    ORDER BY js.created_at DESC
";

$result = $conn->query($sql);

// Determine which columns are empty for print and download
$has_reception = false;
$has_ctp = false;
$has_multicolour = false;
$has_digital = false;
$has_delivery = false;
$has_dispatch = false;
$has_accounts = false;

if ($result->num_rows > 0) {
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        $reception = ($row['initial_status'] === 'Draft' || $row['initial_status'] === 'Approved' || $row['initial_status'] === 'Finalized');
        $ctp = ($row['ctp'] == 1 || $row['completed_ctp'] == 1);
        $multicolour = ($row['multicolour'] == 1 || $row['completed_multicolour'] == 1);
        $digital = ($row['digital'] == 1 || $row['completed_digital'] == 1);
        $delivery = ($row['completed_delivery'] == 1);
        $dispatch = !empty($row['dispatched_at']);
        $accounts = ($row['payment_status'] === 'completed' || strpos($row['progress_history'], 'Accounts') !== false);
        $has_reception |= $reception;
        $has_ctp |= $ctp;
        $has_multicolour |= $multicolour;
        $has_digital |= $digital;
        $has_delivery |= $delivery;
        $has_dispatch |= $dispatch;
        $has_accounts |= $accounts;
    }
    $result->data_seek(0);
}

// Handle CSV download
if (isset($_GET['download']) && $_GET['download'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="jobsheet_progress_' . date('Ymd') . '.csv"');
    $output = fopen('php://output', 'w');
    $csv_headers = ['ID', 'Job Name', 'Customer', 'Initial Status', 'Current Stage'];
    if ($has_reception) $csv_headers[] = 'Reception';
    if ($has_ctp) $csv_headers[] = 'CTP';
    if ($has_multicolour) $csv_headers[] = 'Multicolour';
    if ($has_digital) $csv_headers[] = 'Digital';
    if ($has_delivery) $csv_headers[] = 'Delivery';
    if ($has_accounts) $csv_headers[] = 'Accounts';
    if ($has_dispatch) $csv_headers[] = 'Dispatch';
    fputcsv($output, $csv_headers);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $reception = ($row['initial_status'] === 'Draft' || $row['initial_status'] === 'Approved' || $row['initial_status'] === 'Finalized') ? '✔' : '-';
            $ctp = ($row['ctp'] == 1 || $row['completed_ctp'] == 1) ? '✔' : '-';
            $multicolour = ($row['multicolour'] == 1 || $row['completed_multicolour'] == 1) ? '✔' : '-';
            $digital = ($row['digital'] == 1 || $row['completed_digital'] == 1) ? '✔' : '-';
            $delivery = ($row['completed_delivery'] == 1) ? '✔' : '-';
            $dispatch = !empty($row['dispatched_at']) ? '✔' : '-';
            $accounts = ($row['payment_status'] === 'completed' || strpos($row['progress_history'], 'Accounts') !== false) ? '✔' : '-';
            $csv_fields = [
                $row['id'],
                $row['job_name'],
                $row['customer_name'],
                $row['initial_status'],
                $row['current_stage']
            ];
            if ($has_reception) $csv_fields[] = $reception;
            if ($has_ctp) $csv_fields[] = $ctp;
            if ($has_multicolour) $csv_fields[] = $multicolour;
            if ($has_digital) $csv_fields[] = $digital;
            if ($has_delivery) $csv_fields[] = $delivery;
            if ($has_accounts) $csv_fields[] = $accounts;
            if ($has_dispatch) $csv_fields[] = $dispatch;
            fputcsv($output, $csv_fields);
        }
    }
    fclose($output);
    exit;
}

// Handle print
$print_mode = isset($_GET['print']);
if ($print_mode) {
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobsheet Working Progress Report</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .navbar {
            background-color: #4a90e2;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
        }
        .navbar .brand {
            font-size: 20px;
            font-weight: bold;
        }
        .nav-buttons a, .nav-buttons button {
            background-color: #6b48ff;
            color: #fff;
            border: none;
            padding: 8px 15px;
            margin-left: 10px;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            font-size: 12px;
        }
        .nav-buttons a:hover, .nav-buttons button:hover {
            background-color: #5a3ce9;
        }
        .controls {
            max-width: 1200px;
            margin: 15px auto;
            padding: 10px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .controls input[type="text"], .controls input[type="date"] {
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 12px;
        }
        .controls button {
            background-color: #4a90e2;
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        .controls button:hover {
            background-color: #357abd;
        }
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 15px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .report-container h1 {
            font-size: 24px;
            color: #4a90e2;
            text-align: center;
            margin-bottom: 15px;
        }
        .jobsheet-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .jobsheet-table th {
            background-color: #4a90e2;
            color: #fff;
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .jobsheet-table td {
            padding: 8px;
            text-align: center;
            border: 1px solid #ddd;
        }
        .jobsheet-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .jobsheet-table tr:hover {
            background-color: #e6f3ff;
        }
        .completed {
            color: green;
            font-weight: bold;
        }
        .not-reached {
            color: #888;
        }
        @media print {
            .navbar, .controls, .nav-buttons {
                display: none !important;
            }
            .report-container {
                max-width: 100%;
                padding: 0;
                border: none;
            }
            .jobsheet-table {
                width: 100%;
                page-break-inside: auto;
            }
            .jobsheet-table th, .jobsheet-table td {
                border: 1px solid #000;
                padding: 6px;
                font-size: 10px;
            }
            .jobsheet-table th {
                background-color: #4a90e2 !important;
            }
            <?php if (!$has_reception) echo 'th:nth-child(6), td:nth-child(6) { display: none; }'; ?>
            <?php if (!$has_ctp) echo 'th:nth-child(7), td:nth-child(7) { display: none; }'; ?>
            <?php if (!$has_multicolour) echo 'th:nth-child(8), td:nth-child(8) { display: none; }'; ?>
            <?php if (!$has_digital) echo 'th:nth-child(9), td:nth-child(9) { display: none; }'; ?>
            <?php if (!$has_delivery) echo 'th:nth-child(10), td:nth-child(10) { display: none; }'; ?>
            <?php if (!$has_accounts) echo 'th:nth-child(11), td:nth-child(11) { display: none; }'; ?>
            <?php if (!$has_dispatch) echo 'th:nth-child(12), td:nth-child(12) { display: none; }'; ?>
        }
        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                gap: 8px;
            }
            .controls input[type="text"], .controls input[type="date"] {
                width: 100%;
            }
            .report-container {
                margin: 10px;
                padding: 10px;
            }
            .jobsheet-table th, .jobsheet-table td {
                font-size: 10px;
                padding: 6px;
            }
            .navbar {
                flex-direction: column;
                gap: 8px;
            }
            .nav-buttons a, .nav-buttons button {
                margin: 5px 0;
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="brand">Jobsheet Working Progress</div>
        <div class="nav-buttons">
            <a href="reports.php">Back to Reports</a>
            <a href="<?php echo htmlspecialchars($dashboard_url); ?>">Back to Dashboard</a>
            <a href="../auth/logout.php">Logout</a>
        </div>
    </div>

    <div class="controls">
        <input type="text" name="search" id="search" placeholder="Search by Job Name or Customer" value="<?php echo htmlspecialchars($search); ?>">
        <input type="date" name="from_date" id="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
        <input type="date" name="to_date" id="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
        <button onclick="applyFilters()">Apply Filters</button>
        <a href="?download=csv&search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>" class="nav-buttons"><i class="fas fa-download"></i> Download CSV</a>
        <button onclick="window.location.href='?print=1&search=<?php echo urlencode($search); ?>&from_date=<?php echo urlencode($from_date); ?>&to_date=<?php echo urlencode($to_date); ?>'"><i class="fas fa-print"></i> Print</button>
    </div>

    <div class="report-container">
        <h1>Jobsheet Working Progress</h1>
        <table class="jobsheet-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Job Name</th>
                    <th>Customer</th>
                    <th>Initial Status</th>
                    <th>Current Stage</th>
                    <th>Reception</th>
                    <th>CTP</th>
                    <th>Multicolour</th>
                    <th>Digital</th>
                    <th>Delivery</th>
                    <th>Accounts</th>
                    <th>Dispatch</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['job_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['initial_status']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['current_stage']) . "</td>";
                        $reception = ($row['initial_status'] === 'Draft' || $row['initial_status'] === 'Approved' || $row['initial_status'] === 'Finalized') ? '<span class="completed">✔</span>' : '<span class="not-reached">-</span>';
                        $ctp = ($row['ctp'] == 1 || $row['completed_ctp'] == 1) ? '<span class="completed">✔</span>' : '<span class="not-reached">-</span>';
                        $multicolour = ($row['multicolour'] == 1 || $row['completed_multicolour'] == 1) ? '<span class="completed">✔</span>' : '<span class="not-reached">-</span>';
                        $digital = ($row['digital'] == 1 || $row['completed_digital'] == 1) ? '<span class="completed">✔</span>' : '<span class="not-reached">-</span>';
                        $delivery = ($row['completed_delivery'] == 1) ? '<span class="completed">✔</span>' : '<span class="not-reached">-</span>';
                        $dispatch = !empty($row['dispatched_at']) ? '<span class="completed">✔</span>' : '<span class="not-reached">-</span>';
                        $accounts = ($row['payment_status'] === 'completed' || strpos($row['progress_history'], 'Accounts') !== false) ? '<span class="completed">✔</span>' : '<span class="not-reached">-</span>';
                        echo "<td>$reception</td>";
                        echo "<td>$ctp</td>";
                        echo "<td>$multicolour</td>";
                        echo "<td>$digital</td>";
                        echo "<td>$delivery</td>";
                        echo "<td>$accounts</td>";
                        echo "<td>$dispatch</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='12' class='no-data'>No job sheets found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <?php if ($print_mode): ?>
        <script>window.print();</script>
    <?php endif; ?>

    <script>
        function applyFilters() {
            const search = document.getElementById('search').value;
            const fromDate = document.getElementById('from_date').value;
            const toDate = document.getElementById('to_date').value;
            let url = 'jobsheet_progress_report.php?';
            if (search) url += 'search=' + encodeURIComponent(search) + '&';
            if (fromDate) url += 'from_date=' + encodeURIComponent(fromDate) + '&';
            if (toDate) url += 'to_date=' + encodeURIComponent(toDate) + '&';
            url = url.slice(0, -1);
            window.location.href = url;
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>