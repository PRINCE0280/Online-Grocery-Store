<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'razorpay_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit;
}

// Get form data
$order_data = $_POST;
$payment_method = $order_data['payment_method'] ?? '';

if ($payment_method === 'online') {
    // Prepare data for Razorpay
    $total_amount = floatval($order_data['total']) * 100; // Convert to paise
    $order_id = generateOrderId();
    
    // Store order data in session for later processing
    $_SESSION['pending_order'] = $order_data;
    $_SESSION['pending_order']['order_id'] = $order_id;
    $_SESSION['pending_order']['total_amount'] = $total_amount;
    
    // Redirect to payment page
    header('Location: payment.php');
    exit;
} else {
    // Process COD order directly (existing logic)
    // Redirect back to checkout with COD processing
    header('Location: checkout.php');
    exit;
}
?>