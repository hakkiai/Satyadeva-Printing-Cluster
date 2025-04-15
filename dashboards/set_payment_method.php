<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id']) && isset($_POST['payment_method'])) {
    $job_id = (int)$_POST['job_id'];
    $payment_method = $_POST['payment_method'];
    $_SESSION['payment_method_' . $job_id] = $payment_method;
    echo "Payment method set successfully";
} else {
    echo "Invalid request";
}
?>