<?php
session_start();
require_once '../database/db_connect.php';

// Debug: Output session info if not set
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit();
}
if ($_SESSION['role'] !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit();
}

// Handle backup request FIRST - before any HTML output
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    // Clear any previous output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    global $conn;
    
    try {
        // Get all tables
        $tables = array();
        $result = $conn->query("SHOW TABLES");
        
        if (!$result) {
            throw new Exception("Error getting tables: " . $conn->error);
        }
        
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        // Start building backup content
        $backup = "-- MySQL Database Backup\n";
        $backup .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $backup .= "-- Host: " . $_SERVER['HTTP_HOST'] . "\n";
        $backup .= "-- ------------------------------------------------------\n\n";
        
        $backup .= "SET NAMES utf8mb4;\n";
        $backup .= "SET time_zone = '+00:00';\n";
        $backup .= "SET foreign_key_checks = 0;\n";
        $backup .= "SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';\n\n";
        
        // Process each table
        foreach ($tables as $table) {
            $backup .= "-- ------------------------------------------------------\n";
            $backup .= "-- Table structure for table `{$table}`\n";
            $backup .= "-- ------------------------------------------------------\n\n";
            
            // Drop table if exists
            $backup .= "DROP TABLE IF EXISTS `{$table}`;\n";
            
            // Get CREATE TABLE statement
            $create_result = $conn->query("SHOW CREATE TABLE `{$table}`");
            if ($create_result) {
                $create_row = $create_result->fetch_row();
                $backup .= $create_row[1] . ";\n\n";
            }
            
            // Get table data
            $data_result = $conn->query("SELECT * FROM `{$table}`");
            if ($data_result && $data_result->num_rows > 0) {
                $backup .= "-- Dumping data for table `{$table}`\n\n";
                $backup .= "LOCK TABLES `{$table}` WRITE;\n";
                
                // Get column information
                $columns_result = $conn->query("SHOW COLUMNS FROM `{$table}`");
                $columns = array();
                while ($col = $columns_result->fetch_assoc()) {
                    $columns[] = $col['Field'];
                }
                
                // Insert data in batches for better performance
                $insert_queries = array();
                while ($row = $data_result->fetch_assoc()) {
                    $values = array();
                    foreach ($columns as $column) {
                        if ($row[$column] === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $conn->real_escape_string($row[$column]) . "'";
                        }
                    }
                    $insert_queries[] = "(" . implode(',', $values) . ")";
                }
                
                // Write INSERT statements in chunks of 100 rows
                $chunks = array_chunk($insert_queries, 100);
                foreach ($chunks as $chunk) {
                    $backup .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES\n";
                    $backup .= implode(",\n", $chunk) . ";\n";
                }
                
                $backup .= "UNLOCK TABLES;\n\n";
            } else {
                $backup .= "-- No data found for table `{$table}`\n\n";
            }
        }
        
        $backup .= "SET foreign_key_checks = 1;\n";
        $backup .= "-- Backup completed\n";
        
        // Generate filename
        $filename = 'database_backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Force download
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream');
        header('Content-Type: application/download');
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . strlen($backup));
        
        // Output the backup
        echo $backup;
        exit();
        
    } catch (Exception $e) {
        die("Backup failed: " . $e->getMessage());
    }
}

// Function to restore database
function restoreDatabase($sql_file) {
    global $conn;
    
    try {
        // Read the SQL file
        $sql_content = file_get_contents($sql_file);
        if ($sql_content === false) {
            return false;
        }
        
        // Set MySQL settings for import
        $conn->query("SET foreign_key_checks = 0");
        $conn->query("SET NAMES utf8mb4");
        
        // Split into individual statements
        $statements = preg_split('/;\s*$/m', $sql_content);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && $statement !== '--') {
                $result = $conn->query($statement);
                if (!$result) {
                    error_log("SQL Error: " . $conn->error . " in statement: " . substr($statement, 0, 100));
                    $conn->query("SET foreign_key_checks = 1");
                    return false;
                }
            }
        }
        
        $conn->query("SET foreign_key_checks = 1");
        return true;
        
    } catch (Exception $e) {
        error_log("Restore error: " . $e->getMessage());
        return false;
    }
}

// Handle restore request
if (isset($_POST['restore']) && isset($_FILES['sql_file'])) {
    $file = $_FILES['sql_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_extension === 'sql') {
            if (restoreDatabase($file['tmp_name'])) {
                $_SESSION['message'] = "Database restored successfully!";
            } else {
                $_SESSION['error'] = "Error restoring database. Check error logs for details.";
            }
        } else {
            $_SESSION['error'] = "Invalid file format! Please upload a .sql file.";
        }
    } else {
        $_SESSION['error'] = "File upload failed. Error code: " . $file['error'];
    }
    
    header('Location: superadmin.php');
    exit();
}

// HTML form for backup/restore (only shown if not downloading)
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Backup & Restore</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .container { max-width: 600px; margin: 0 auto; }
        .button { 
            background: #007cba; 
            color: white; 
            padding: 10px 20px; 
            text-decoration: none; 
            border-radius: 5px; 
            display: inline-block; 
            margin: 10px 5px; 
            border: none;
            cursor: pointer;
        }
        .button:hover { background: #005a87; }
        .form-group { margin: 20px 0; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Backup & Restore</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="form-group">
            <h2>Backup Database</h2>
            <p>Click the button below to download a complete backup of your database.</p>
            <a href="?action=backup" class="button">Download Backup</a>
        </div>
        
        <div class="form-group">
            <h2>Restore Database</h2>
            <p>Upload a SQL backup file to restore your database.</p>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="sql_file" accept=".sql" required>
                <br><br>
                <input type="submit" name="restore" value="Restore Database" class="button" 
                       onclick="return confirm('Are you sure? This will overwrite your current database!');">
            </form>
        </div>
        
        <div class="form-group">
            <a href="../admin/dashboard.php" class="button">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>