<?php
include '../database/db_connect.php';

$job_id = $_GET['id'];
$sql = "SELECT * FROM dispatch_jobs WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    die("Job not found.");
}

// Fallback for dispatched_at if it's not in a valid format
$dispatched_at = $job['dispatched_at'] ? date('d/m/Y', strtotime($job['dispatched_at'])) : date('d/m/Y');
$dispatched_time = $job['dispatched_at'] ? date('H:i', strtotime($job['dispatched_at'])) : date('H:i');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Dispatch Job #<?php echo $job_id; ?></title>
    <style>
        @page {
            margin: 0;
            size: A5;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f0f0f0;
        }
        .gate-pass {
            width: 400px;
            padding: 15px;
            border: 2px solid #000;
            background-color: #fff;
            position: relative;
            margin: 20px;
            min-height: 280px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .header h2 {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .header hr {
            border: 0;
            height: 1px;
            background: #000;
            margin: 8px 0;
        }
        .field-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 13px;
            line-height: 1.4;
        }
        .field-row label {
            font-weight: bold;
            width: 40%;
            text-align: right;
            margin-right: 8px;
            padding-top: 4px;
        }
        .field-row span {
            width: 60%;
            border-bottom: 1px solid #000;
            padding: 2px 4px;
            word-wrap: break-word;
            height: auto;
        }
        .description-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 13px;
            line-height: 1.4;
        }
        .description-row label {
            font-weight: bold;
            width: 40%;
            text-align: right;
            margin-right: 8px;
            padding-top: 4px;
        }
        .description-row span {
            width: 60%;
            padding: 2px 4px;
            word-wrap: break-word;
            height: auto;
        }
        .signature-row {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            font-size: 13px;
            line-height: 1.4;
        }
        .signature-row label {
            font-weight: bold;
            text-align: center;
            width: 100%;
            margin: 0;
            padding: 0;
        }
        .signature-row span {
            width: 50%;
            padding: 2px 4px;
            text-align: center;
            height: 20px;
            display: block;
            margin: 0 auto;
        }
        .job-sheet-no {
            position: absolute;
            top: 10px;
            left: 10px;
            font-size: 12px;
            font-weight: bold;
        }
        .date-time {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 12px;
            text-align: right;
        }
        @media print {
            body {
                background-color: #fff;
                margin: -60mm;
                padding: 0;
            }
            .no-print {
                display: none;
            }
            .gate-pass {
                box-shadow: none;
                border: none;
                width: 100%;
                margin: 0;
                padding: 15mm 10mm;
                min-height: auto;
                position: absolute;
                top: 0;
                left: 0;
            }
            @page {
                margin: 0;
            }
            footer, .footer {
                display: none !important;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="gate-pass">
        <div class="job-sheet-no">Job Sheet No: <?php echo $job['id']; ?></div>
        <div class="date-time">
            Date: <?php echo $dispatched_at; ?><br>
            Time: <?php echo $dispatched_time; ?>
        </div>
        <div class="header">
            <h2>SRI SATYADEVA PRINTING CLUSTER ASSOCIATION</h2>
            <h2>KAKINADA</h2>
            <hr>
            <h3>GATE PASS</h3>
        </div>
        <div class="field-row">
            <label>Customer Name:</label>
            <span><?php echo htmlspecialchars($job['customer_name']); ?></span>
        </div>
        <div class="field-row">
            <label>Transport Type:</label>
            <span><?php echo htmlspecialchars($job['transport_type'] ?? ''); ?></span>
        </div>
        <div class="description-row">
            <label>Description of Material Dispatched:</label>
            <span><?php echo htmlspecialchars(trim($job['description']) ? $job['description'] : ''); ?></span>
        </div>
        <div class="signature-row">
            <label>Authorized Signature:</label>
            <span></span> <!-- Removed underline, left as blank space -->
        </div>
        <button class="no-print" onclick="window.close()">Close</button>
    </div>
</body>
</html>