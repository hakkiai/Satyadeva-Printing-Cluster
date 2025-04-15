<?php
session_start();
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web-Based Printing Cluster</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

    <!-- Navbar -->
    <div class="navbar">
        <div class="brand">Satya Deva Printing Cluster</div>
        <div class="nav-buttons">
            <button onclick="window.location.href='index.php'">Home</button>
            <button onclick="showLoginPopup()">Login</button>
        </div>
    </div>

    <!-- Page Content -->
    <div class="content">
        <h2>Welcome to Satya Deva Printing Cluster ERP</h2>
        <p>This is the home page of the ERP system.</p>
    </div>

    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="closeLoginPopup()"></div>

    <!-- Login Popup -->
    <div class="login-popup" id="loginPopup">
        <span class="close-btn" onclick="closeLoginPopup()">✖</span>
        <h2>Login</h2>
        <form action="../auth/login.php" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button onclick="window.location.href='../auth/login.php'">Login</button>
        </form>
    </div>

    <script>
        function showLoginPopup() {
            document.getElementById('loginPopup').style.display = 'block';
            document.getElementById('overlay').style.display = 'block';
        }

        function closeLoginPopup() {
            document.getElementById('loginPopup').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
        }
    </script>

</body>
</html>
