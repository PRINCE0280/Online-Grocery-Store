<?php
require_once 'config.php';
require_once 'functions.php';
// Add the image handler include at the top after other includes
require_once 'image_handler.php';

// Redirect if no order data
if (!isset($_SESSION['order_data'])) {
    header('Location: index.php');
    exit;
}

$order = $_SESSION['order_data'];
$pageTitle = 'Order Confirmation';
?>

<?php include 'header.php'; ?>

<main class="container mx-auto px-4 py-24 md:py-20">

    <div class="max-w-2xl mx-auto">
        <!-- Success Message -->
        <div class="text-center mb-8">
            <div class="bg-green-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-3xl text-green-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Order Placed Successfully!</h1>
            <p class="text-gray-600">Thank you for your order. We'll deliver it to you soon.</p>
        </div>

        <!-- Order Details -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Details</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <p class="text-sm text-gray-600">Order ID</p>
                    <p class="font-semibold text-lg"><?php echo $order['order_id']; ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Order Date</p>
                    <p class="font-semibold"><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Payment Method</p>
                    <p class="font-semibold capitalize">
                        <?php echo $order['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Online Payment'; ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Delivery Option</p>
                    <p class="font-semibold capitalize">
                        <?php echo $order['delivery_option'] === 'standard' ? 'Standard Delivery' : 'Express Delivery'; ?>
                    </p>
                </div>
            </div>
            
            <div class="border-t pt-4">
                <h3 class="font-semibold text-gray-800 mb-3">Delivery Address</h3>
                <div class="text-gray-600">
                    <p class="font-medium"><?php echo htmlspecialchars($order['name']); ?></p>
                    <p><?php echo htmlspecialchars($order['address']); ?></p>
                    <p>Pin Code: <?php echo htmlspecialchars($order['pincode']); ?></p>
                    <p>Mobile: <?php echo htmlspecialchars($order['mobile']); ?></p>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Items</h2>
            
            <div class="space-y-4">
                <?php foreach ($order['items'] as $item): ?>
                    <div class="flex items-center space-x-4 py-3 border-b last:border-b-0">
                        <?php echo generateProductImageHTML($item, 'w-16 h-16 object-cover rounded-lg'); ?>
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-800"><?php echo $item['name']; ?></h4>
                            <p class="text-sm text-gray-600">
                                <?php echo formatCurrency($item['price']); ?> × <?php echo $item['quantity']; ?>
                            </p>
                        </div>
                        <p class="font-semibold">
                            <?php echo formatCurrency($item['price'] * $item['quantity']); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="border-t pt-4 mt-4 space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-600">Subtotal</span>
                    <span class="font-semibold"><?php echo formatCurrency($order['subtotal']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Delivery Fee</span>
                    <span class="font-semibold"><?php echo formatCurrency($order['delivery_fee']); ?></span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t pt-2">
                    <span>Total Paid</span>
                    <span class="text-green-600"><?php echo formatCurrency($order['total']); ?></span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="text-center space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    You will receive email updates about your order status at <?php echo htmlspecialchars($order['email'] ?? $_SESSION['user_email'] ?? 'your registered email'); ?>
                </p>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="index.php" 
                   class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
                    Continue Shopping
                </a>
                <button onclick="window.print()" 
                        class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-print mr-2"></i>Print Order
                </button>
            </div>
        </div>
    </div>
</main>

<?php 
// Clear order data after displaying
unset($_SESSION['order_data']);
include 'footer.php'; 
?>
