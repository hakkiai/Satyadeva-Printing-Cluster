<?php
include '../database/db_connect.php';

// Handle balance limit update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['customer_id']) && isset($_POST['balance_limit'])) {
    $customer_id = (int)$_POST['customer_id'];
    $balance_limit = floatval($_POST['balance_limit']);
    
    $sql = "UPDATE customers SET balance_limit = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $balance_limit, $customer_id);
    if ($stmt->execute()) {
        echo "<script>alert('Balance limit updated successfully!'); window.location.href='set_balance_limits.php';</script>";
    } else {
        echo "<script>alert('Error updating balance limit: " . $stmt->error . "');</script>";
    }
    $stmt->close();
}

// Function to calculate customer's total balance and job sheet count
function get_customer_balance_and_jobs($conn, $customer_name) {
    $sql = "SELECT js.id, js.total_charges, COALESCE(SUM(pr.cash + pr.credit), 0) as total_paid
            FROM job_sheets js
            LEFT JOIN payment_records pr ON js.id = pr.job_sheet_id
            WHERE TRIM(LOWER(js.customer_name)) = TRIM(LOWER(?))
            AND js.status = 'Finalized'
            GROUP BY js.id, js.total_charges";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $customer_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total_balance = 0;
    $job_count = 0;
    
    error_log("Calculating balance for customer: $customer_name");
    while ($row = $result->fetch_assoc()) {
        $job_count++;
        $total_charges = floatval($row['total_charges']);
        $total_paid = floatval($row['total_paid']);
        $balance = $total_charges - $total_paid;
        error_log("Job ID: {$row['id']}, Total Charges: $total_charges, Total Paid: $total_paid, Balance: $balance");
        if ($balance > 0) {
            $total_balance += $balance;
        }
    }
    
    error_log("Customer: $customer_name, Total Balance: $total_balance, Job Count: $job_count");
    $stmt->close();
    return ['job_count' => $job_count, 'total_balance' => $total_balance];
}

// Handle search
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
$params = [];
if (!empty($search_query)) {
    $where_clause = "WHERE id LIKE ? OR customer_name LIKE ?";
    $search_term = "%$search_query%";
    $params = [$search_term, $search_term];
}

// Fetch all customers, ordered by ID
$sql = "SELECT id, customer_name, phone_number, balance_limit FROM customers $where_clause ORDER BY id ASC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param("ss", $params[0], $params[1]);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Balance Limits</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
        }
        .navbar {
            background: #007bff;
            padding: 15px 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar .brand {
            color: white;
            font-size: 22px;
            font-weight: bold;
            margin: 0;
        }
        .nav-buttons button {
            background: white;
            color: #007bff;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .nav-buttons button:hover {
            background: #0056b3;
            color: white;
        }
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .search-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .search-bar input[type="text"] {
            padding: 8px 12px;
            width: 300px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .search-bar button {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .search-bar button:hover {
            background: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .form-group {
            display: inline-block;
        }
        input[type="number"] {
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 100px;
        }
        button {
            padding: 6px 12px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #218838;
        }
        .no-results {
            text-align: center;
            color: #666;
            font-size: 16px;
            padding: 20px;
        }
        .limit-reached {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
    <script>
        // Fallback to handle navigation issues
        function goToDashboard() {
            window.location.href = 'superadmin.php';
        }
    </script>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Set Balance Limits</h2>
        <div class="nav-buttons">
            <button onclick="goToDashboard()">Back to Dashboard</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="container">
        <h2>Customer Balance Limits</h2>
        
        <!-- Search Bar -->
        <div class="search-bar">
            <form method="GET" action="set_balance_limits.php">
                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search by ID or Name">
                <button type="submit">Search</button>
            </form>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer Name</th>
                        <th>Phone Number</th>
                        <th>Job Sheets</th>
                        <th>Total Balance (₹)</th>
                        <th>Balance Limit (₹)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php 
                        $balance_data = get_customer_balance_and_jobs($conn, $row['customer_name']);
                        $job_count = $balance_data['job_count'];
                        $total_balance = $balance_data['total_balance'];
                        $balance_limit = $row['balance_limit'] !== null ? floatval($row['balance_limit']) : 0;
                        $limit_reached = $balance_limit > 0 && $total_balance >= $balance_limit;
                        ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['customer_name']) ?></td>
                            <td><?= htmlspecialchars($row['phone_number']) ?></td>
                            <td><?= $job_count ?></td>
                            <td class="<?= $limit_reached ? 'limit-reached' : '' ?>">
                                <?= number_format($total_balance, 2) ?: '0.00' ?>
                                <?= $limit_reached ? '<br><span>Balance limit reached, clear the balance</span>' : '' ?>
                            </td>
                            <td>
                                <form method="POST">
                                    <div class="form-group">
                                        <input type="number" name="balance_limit" value="<?= $balance_limit > 0 ? number_format($balance_limit, 2, '.', '') : '' ?>" min="0" step="0.01" placeholder="Set limit" required>
                                        <input type="hidden" name="customer_id" value="<?= $row['id'] ?>">
                                    </div>
                            </td>
                            <td>
                                    <button type="submit">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-results">No customers found<?php echo !empty($search_query) ? " matching '$search_query'" : "."; ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>