<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .backup-section {
            margin: 20px;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        .backup-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .restore-form {
            margin-top: 20px;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 30px 40px;
            border: 1px solid #888;
            width: 400px;
            border-radius: 10px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            position: relative;
            text-align: center;
        }
        .close {
            color: #aaa;
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover { color: #000; }
        .main-action {
            background: #28a745;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .main-action:hover { background: #218838; }
        .restore-form input[type="file"] {
            margin: 10px 0;
        }
        .restore-form button {
            background: #007bff;
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 5px;
            font-size: 15px;
            cursor: pointer;
        }
        .restore-form button:hover { background: #0056b3; }
    </style>
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
        <button onclick="location.href='../dashboards/reports.php'">Reports</button>
        <button id="backupBtn" style="background:#28a745;color:#fff;">Backup</button>
        <button onclick="location.href='../auth/logout.php'">Logout</button>
    </div>
</div>

    <div class="content">
        <h3>Welcome, Super Admin</h3>
    </div>

    <!-- Backup Modal -->
    <div id="backupModal" class="modal">
      <div class="modal-content">
        <span class="close" id="closeModal">&times;</span>
        <h2>Database Backup & Restore</h2>
        <?php
        if (isset($_SESSION['message'])) {
            echo '<div class="message success">' . $_SESSION['message'] . '</div>';
            unset($_SESSION['message']);
        }
        if (isset($_SESSION['error'])) {
            echo '<div class="message error">' . $_SESSION['error'] . '</div>';
            unset($_SESSION['error']);
        }
        ?>
        <div class="backup-buttons">
            <button class="main-action" onclick="location.href='db_backup.php?action=backup'">Download Backup</button>
        </div>
        <div class="restore-form">
            <h4>Restore Database</h4>
            <form action="db_backup.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="sql_file" accept=".sql" required>
                <button type="submit" name="restore">Restore Database</button>
            </form>
        </div>
      </div>
    </div>

    <script>
        // Modal logic
        var modal = document.getElementById('backupModal');
        var btn = document.getElementById('backupBtn');
        var span = document.getElementById('closeModal');
        btn.onclick = function() {
            modal.style.display = 'block';
        }
        span.onclick = function() {
            modal.style.display = 'none';
        }
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>