<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
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

    <div class="content">
        <h3>Welcome, Super Admin</h3>
        <p>Quick overview will appear here...</p>
    </div>
</body>
</html>