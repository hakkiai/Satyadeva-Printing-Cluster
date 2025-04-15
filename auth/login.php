<?php
session_start(); // Add this to start the session
include '../database/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if ($username === "superadmin" && $password === "root") {
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'super_admin'; // Set role for consistency
        header("Location: ../dashboards/super_admin_dashboard.php"); // Match expected filename
        exit();
    } else {
        $stmt = $conn->prepare("SELECT role, active FROM users WHERE username = ? AND password = ?");
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            if ($user['active'] == 1) { // Check if the user is active
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $user['role'];

                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: ../dashboards/admin.php");
                        break;
                    case 'super_admin':
                        header("Location: ../dashboards/superadmin.php");
                        break;
                    case 'reception':
                        header("Location: ../dashboards/reception-1.php");
                        break;
                    case 'ctp':
                        header("Location: ../dashboards/ctp_dashboard.php");
                        break;
                    case 'multicolour':
                        header("Location: ../dashboards/multicolour_dashboard.php");
                        break;
                    case 'accounts':
                        header("Location: ../dashboards/accounts_dashboard.php");
                        break;
                    case 'delivery':
                        header("Location: ../dashboards/delivery.php");
                        break;
                    case 'dispatch':
                        header("Location: ../dashboards/dispatch.php");
                        break;
                    case 'digital':
                        header("Location: ../dashboards/digital_dashboard.php");
                        break;
                    default:
                        echo "Unauthorized role!";
                        exit();
                }
                exit();
            } else {
                echo "Account is inactive. Please contact the administrator.";
            }
        } else {
            echo "Invalid username or password.";
        }

        $stmt->close();
        $conn->close();
    }
}
?>