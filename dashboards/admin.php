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
    <h2 class="brand">Admin Dashboard</h2>
    <div class="nav-buttons">
        <button onclick="location.href='add_vendor.php'">Add Vendor</button>
        <button onclick="location.href='add_edit_customers.php'">Add/Edit Customers</button>
        <button onclick="location.href='admin_inventory.php'">Inventory</button>
        <button onclick="location.href='sales.php'">Sales</button>
        <button onclick="location.href='printing_charges.php'">Printing Charges</button>
        <button onclick="location.href='reports.php'">Reports</button> <!-- New Button -->
        <button onclick="location.href='../auth/logout.php'">Logout</button>
    </div>
</div>

    <div class="content">
        <h3>Welcome, admin</h3>
        <p>Quick overview will appear here...</p>
    </div>

</body>
</html>
