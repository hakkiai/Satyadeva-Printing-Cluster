<?php
include '../database/db_connect.php';

$success_message = "";
$error_message = "";

// Handle user addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = $_POST['username'];
    $password = $_POST['password']; 
    $role = $_POST['role'];
    $email = $_POST['email'];

    // Check if user already exists
    $check_sql = "SELECT * FROM users WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error_message = "User or email already exists!";
    } else {
        $sql = "INSERT INTO users (username, password, role, email, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $username, $password, $role, $email);
        if ($stmt->execute()) {
            $success_message = "User added successfully!";
        } else {
            $error_message = "Error adding user!";
        }
    }
    $stmt->close();
}

// Handle user deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $username = $_POST['username'];

    $sql = "DELETE FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    if ($stmt->execute()) {
        $success_message = "User deleted successfully!";
    } else {
        $error_message = "Error deleting user!";
    }
    $stmt->close();
}

// Fetch existing users
$sql = "SELECT username, role, email, created_at FROM users WHERE role!='super_admin'";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .user-table th, .user-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        .user-table th {
            background-color: #007BFF;
            color: white;
        }

        .user-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .user-table tr:hover {
            background-color: #ddd;
        }
    </style>
</head>
<body>

<div class="navbar">
    <h2 class="brand">Super Admin Dashboard</h2>
    <div class="nav-buttons">
        <button onclick="location.href='add_user.php'">Add User</button>
        <button onclick="location.href='super_inventory.php'">Inventory</button>
        <button onclick="location.href='../dashboards/stock_inventory.php'">Stock Inventory</button>
        <button onclick="location.href='../dashboards/paper_inventory.php'">Paper Inventory</button>
        <button onclick="location.href='../dashboards/set_balance_limits.php'">Limitation</button>
        <button onclick="location.href='../dashboards/reports.php'">Reports</button> <!-- New Button -->
        <button onclick="location.href='../auth/logout.php'">Logout</button>
    </div>
</div>

<!-- Success and Error Alerts -->
<?php if ($success_message): ?>
    <script>
        alert("<?php echo $success_message; ?>");
    </script>
<?php endif; ?>

<?php if ($error_message): ?>
    <script>
        alert("<?php echo $error_message; ?>");
    </script>
<?php endif; ?>

<div class="main-container">
    <!-- Add New User Section -->
    <div class="user-container">
        <h2>Add New User</h2>
        <form action="add_user.php" method="POST" class="form">
            <div class="form-group">
                <label for="username">Enter User Name:</label>
                <input type="text" name="username" id="username" placeholder="Enter User Name" required>
            </div>

            <div class="form-group">
                <label for="password">Enter Password:</label>
                <input type="text" name="password" id="password" placeholder="Enter Password" required>
            </div>

            <div class="form-group">
                <label for="email">Enter Email:</label>
                <input type="email" name="email" id="email" placeholder="Enter Email" required>
            </div>

            <div class="form-group">
                <label for="role">Role:</label>
                <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="reception">Receptionist</option>
                    <option value="ctp">Ctp</option>
                    <option value="accounts">Accountant</option>
                    <option value="multicolour">Multicolour</option>
                    <option value="dispatch">Dispatch</option>
                    <option value="digital">Digital</option>
                </select>
            </div>

            <button type="submit" name="add_user" class="btn">Add User</button>
        </form>
    </div>

    <!-- Existing Users Table -->
    <div class="user-container">
        <h2>Existing Users</h2>
        <table class="user-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['role']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo date("m/d/Y", strtotime($row['created_at'])); ?></td>
                        <td>
                            <form action="add_user.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                <input type="hidden" name="username" value="<?php echo $row['username']; ?>">
                                <button type="submit" name="delete_user" style="background-color:red;" class="delete-btn">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?> 
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
