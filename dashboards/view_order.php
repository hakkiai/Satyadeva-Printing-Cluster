<?php
include '../database/db_connect.php';

// Get selected status (default: 'Draft')
$status = isset($_GET['status']) ? $_GET['status'] : 'Draft';
$customer_name = isset($_GET['customer_name']) ? $_GET['customer_name'] : '';

// Build SQL query with ORDER BY id DESC to show newest first
$sql = "SELECT * FROM job_sheets WHERE status=?";
if (!empty($customer_name)) {
    $sql .= " AND customer_name LIKE ?";
}
$sql .= " ORDER BY id DESC";

$stmt = $conn->prepare($sql);
if (!empty($customer_name)) {
    $param = "%$customer_name%";
    $stmt->bind_param("ss", $status, $param);
} else {
    $stmt->bind_param("s", $status);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Sheets</title>
    <link rel="stylesheet" href="../css/style.css">
    <!-- Include Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        table {
            width: 90%;
            margin: 20px auto;
            border-collapse: collapse;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
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
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        button {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 0 5px;
        }
        .search-btn {
            margin-top: 10px;
            width: 100px;
            margin-left: 350px;
        }
        .view-btn { background-color: #28a745; color: white; }
        .edit-btn { background-color: #ffc107; color: black; }
        .finalize-btn { background-color: #007bff; color: white; }
        /* Dropdown Button and Menu Styling (copied from accounts_dashboard.php) */
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
    </style>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Reception Dashboard</h2>
        <div class="nav-buttons">
            <button onclick="location.href='New_Order.php'">New Order</button>
            <button onclick="location.href='view_order.php'">View Order</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>
    
    <div class="container">
        <h2>Job Sheets</h2>
        <div class="form-group">
            <label for="status">Filter by Status:</label>
            <select id="status" onchange="filterStatus()">
                <option value="Draft" <?= $status == 'Draft' ? 'selected' : '' ?>>Draft</option>
                <option value="Finalized" <?= $status == 'Finalized' ? 'selected' : '' ?>>Finalized</option>
            </select>
            <input type="text" id="customer_name" placeholder="Search by Customer" value="<?= htmlspecialchars($customer_name) ?>">
            <button onclick="filterStatus()" class="search-btn">Search</button>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer Name</th>
                <th>Phone Number</th>
                <th>Job Name</th>
                <th>Total Charges</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                <td><?= htmlspecialchars($row['phone_number']) ?></td>
                <td><?= htmlspecialchars($row['job_name']) ?></td>
                <td>₹<?= number_format($row['total_charges'], 2) ?></td>
                <td><?= $row['status'] ?></td>
                <td>
                    <button class="view-btn" onclick="window.location.href='New_Order.php?id=<?= $row['id'] ?>&mode=view'">View</button>
                    <button class="edit-btn" onclick="window.location.href='New_Order.php?id=<?= $row['id'] ?>&mode=edit'">Edit</button>
                    <button class="finalize-btn" onclick="window.location.href='finalize_order.php?id=<?= $row['id'] ?>'">Finalize</button>
                    <div class="dropdown">
                        <button class="dropdown-btn">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <div class="dropdown-content">
                            <a href="print_job_sheet.php?id=<?= $row['id'] ?>&paper_charges=without" target="_blank" class="print1">
                                <i class="fas fa-print"></i> Print 1
                            </a>
                            <a href="print_job_sheet.php?id=<?= $row['id'] ?>&paper_charges=with" target="_blank" class="print2">
                                <i class="fas fa-print"></i> Print 2
                            </a>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <script>
        function filterStatus() {
            let selectedStatus = document.getElementById("status").value;
            let customerName = document.getElementById("customer_name").value;
            window.location.href = "view_order.php?status=" + selectedStatus + "&customer_name=" + customerName;
        }
    </script>
</body>
</html>