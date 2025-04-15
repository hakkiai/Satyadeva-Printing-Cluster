<?php
session_start();
include '../database/db_connect.php';

// Determine dashboard based on user role
$dashboard_url = 'accounts_dashboard.php'; // Default
if (isset($_SESSION['role'])) {
    error_log("Reports.php - User role: " . $_SESSION['role']);
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
            error_log("Reports.php - Unrecognized role: " . $_SESSION['role']);
            $dashboard_url = 'accounts_dashboard.php';
    }
} else {
    error_log("Reports.php - Session role not set");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link rel="stylesheet" href="../css/style.css">
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
            text-align: center;
        }
        .reports-container h3 {
            color: #333;
            font-size: 28px;
            margin-bottom: 20px;
        }
        .report-btn {
            display: inline-block;
            padding: 12px 25px;
            margin: 10px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-size: 16px;
            transition: background-color 0.3s, transform 0.2s;
        }
        .report-btn:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .reports-container {
                width: 90%;
                padding: 15px;
            }
            .report-btn {
                display: block;
                margin: 15px auto;
                width: 80%;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2 class="brand">Reports</h2>
        <div class="nav-buttons">
            <button onclick="location.href='<?php echo $dashboard_url; ?>'">Back to Dashboard</button>
            <button onclick="location.href='../auth/logout.php'">Logout</button>
        </div>
    </div>

    <div class="reports-container">
    <h3>Select a Report</h3>
    <a href="debitor_list.php" class="report-btn">Debitor List</a>
    <a href="creditor_list.php" class="report-btn">Creditor List</a>
    <a href="partially_paid_list.php" class="report-btn">Partially Paid List</a>
    <a href="fully_paid_list.php" class="report-btn">Fully Paid List</a>
    <a href="stock_register.php" class="report-btn">Stock Register</a>
    <a href="jobsheet_progress_report.php" class="report-btn">Job sheet Progress</a>
    <a href="payment_register.php" class="report-btn">Payment Register</a>
</div>
</body>
</html>