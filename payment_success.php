<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'razorpay_config.php';
require_once 'mail_helper.php';
require_once 'notification_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['pending_order'])) {
    header('Location: checkout.php');
    exit;
}

$razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
$razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
$razorpay_signature = $_POST['razorpay_signature'] ?? '';

if (empty($razorpay_payment_id)) {
    header('Location: checkout.php?error=payment_failed');
    exit;
}

try {
    $conn->beginTransaction();
    
    $order_data = $_SESSION['pending_order'];
    $userId = $_SESSION['user_id'];
    
    // Calculate fees
    $subtotal = getCartTotal();
    $delivery_fee = ($subtotal >= 499) ? 0 : 50;
    $express_fee = ($order_data['delivery_option'] === 'express') ? 50 : 0;
    $total_delivery_fee = $delivery_fee + $express_fee;
    $total = $subtotal + $total_delivery_fee;
    
    // Insert order into database
    $stmt = $conn->prepare("INSERT INTO orders 
        (order_id, user_id, name, mobile, address, pincode, delivery_option, payment_method, 
         subtotal, delivery_fee, total, order_date, payment_id, payment_status, order_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $order_data['order_id'],
        $userId,
        $order_data['name'],
        $order_data['mobile'],
        $order_data['address'],
        $order_data['pincode'],
        $order_data['delivery_option'],
        'online',
        $subtotal,
        $total_delivery_fee,
        $total,
        date('Y-m-d H:i:s'),
        $razorpay_payment_id,
        'paid',
        'confirmed'  // Automatically set order status to confirmed for paid online orders
    ]);
    
    // Insert order items
    foreach ($_SESSION['cart'] as $item) {
        $stmt = $conn->prepare("INSERT INTO order_items 
            (order_id, product_id, name, quantity, price)
            VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $order_data['order_id'],
            $item['id'],
            $item['name'],
            $item['quantity'],
            $item['price']
        ]);
    }
    
    $conn->commit();
    
    // Get user email for notifications
    $userEmail = getUserEmail($_SESSION['user_id']);
    
    // Prepare order data for success page
    $_SESSION['order_data'] = [
        'order_id' => $order_data['order_id'],
        'name' => $order_data['name'],
        'mobile' => $order_data['mobile'],
        'address' => $order_data['address'],
        'pincode' => $order_data['pincode'],
        'delivery_option' => $order_data['delivery_option'],
        'payment_method' => 'online',
        'payment_id' => $razorpay_payment_id,
        'items' => $_SESSION['cart'],
        'subtotal' => $subtotal,
        'delivery_fee' => $total_delivery_fee,
        'total' => $total,
        'order_date' => date('Y-m-d H:i:s'),
        'status' => 'confirmed'  // Updated to show confirmed status for online payments
    ];

    // Send email notifications
    if ($userEmail) {
        // Send confirmation email to customer
        $emailSent = sendOrderConfirmationEmail($_SESSION['order_data'], $userEmail);
        
        // Send notification email to admin
        sendAdminOrderNotification($_SESSION['order_data'], $userEmail);
        
        if (!$emailSent) {
            error_log("Failed to send order confirmation email for order: " . $order_data['order_id']);
        }
    } else {
        error_log("Could not retrieve user email for order: " . $order_data['order_id']);
    }
    
    // Create notifications for both customer and admin
    createOrderNotification($order_data['order_id'], $_SESSION['order_data'], $_SESSION['user_id']);
    
    // Clear cart and pending order
    unset($_SESSION['cart']);
    unset($_SESSION['pending_order']);
    
    header('Location: order_success.php');
    exit;
    
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Payment processing error: " . $e->getMessage());
    header('Location: checkout.php?error=order_failed');
    exit;
}
?>