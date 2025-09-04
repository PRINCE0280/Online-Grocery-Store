<?php
@include '../config.php';

// Check if admin is logged in
$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    die('Unauthorized access');
}

$order_id = $_GET['order_id'] ?? '';
if (empty($order_id)) {
    die('Order ID required');
}

// Get order details with customer info
$stmt = $conn->prepare("
    SELECT o.*, u.name as customer_name, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.order_id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('Order not found');
}

// Get order items
$stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-6">
    <!-- Order Info -->
    <div class="border rounded-lg p-4">
        <h4 class="font-semibold text-gray-800 mb-3">Order Information</h4>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-600">Order ID:</span>
                <span class="font-medium">#<?= htmlspecialchars($order['order_id']) ?></span>
            </div>
            <div>
                <span class="text-gray-600">Date:</span>
                <span class="font-medium"><?= date('M j, Y g:i A', strtotime($order['order_date'])) ?></span>
            </div>
            <div>
                <span class="text-gray-600">Payment Method:</span>
                <span class="font-medium"><?= ($order['payment_method'] === 'cod') ? 'COD' : ucfirst($order['payment_method']) ?></span>
            </div>
            <div>
                <span class="text-gray-600">Payment Status:</span>
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                    <?= $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                    <?= ucfirst($order['payment_status']) ?>
                </span>
            </div>
            <div>
                <span class="text-gray-600">Order Status:</span>
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                    <?php
                    switch($order['order_status']) {
                        case 'pending':
                            echo 'bg-yellow-100 text-yellow-800';
                            break;
                        case 'completed':
                            echo 'bg-green-100 text-green-800';
                            break;
                        case 'cancelled':
                            echo 'bg-red-100 text-red-800';
                            break;
                        default:
                            echo 'bg-gray-100 text-gray-800';
                    }
                    ?>">
                    <?= ucfirst($order['order_status']) ?>
                </span>
            </div>
            <div>
                <span class="text-gray-600">Delivery:</span>
                <span class="font-medium"><?= ucfirst($order['delivery_option']) ?></span>
            </div>
        </div>
    </div>

    <!-- Customer Info -->
    <div class="border rounded-lg p-4">
        <h4 class="font-semibold text-gray-800 mb-3">Customer Information</h4>
        <div class="text-sm space-y-2">
            <div>
                <span class="text-gray-600">Name:</span>
                <span class="font-medium"><?= htmlspecialchars($order['customer_name']) ?></span>
            </div>
            <div>
                <span class="text-gray-600">Email:</span>
                <span class="font-medium"><?= htmlspecialchars($order['email']) ?></span>
            </div>
            <div>
                <span class="text-gray-600">Mobile:</span>
                <span class="font-medium"><?= htmlspecialchars($order['mobile'] ?? 'N/A') ?></span>
            </div>
            <div>
                <span class="text-gray-600">Address:</span>
                <span class="font-medium"><?= htmlspecialchars($order['address']) ?></span>
            </div>
            <div>
                <span class="text-gray-600">Pincode:</span>
                <span class="font-medium"><?= htmlspecialchars($order['pincode']) ?></span>
            </div>
        </div>
    </div>

    <!-- Order Items -->
    <div class="border rounded-lg p-4">
        <h4 class="font-semibold text-gray-800 mb-3">Order Items</h4>
        <div class="space-y-3">
            <?php foreach ($items as $item): ?>
                <div class="flex justify-between items-center py-2 border-b border-gray-200">
                    <div>
                        <span class="font-medium"><?= htmlspecialchars($item['name']) ?></span>
                        <span class="text-sm text-gray-600 ml-2">× <?= $item['quantity'] ?></span>
                    </div>
                    <div class="text-right">
                        <div class="font-medium">₹<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                        <div class="text-sm text-gray-600">₹<?= number_format($item['price'], 2) ?> each</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Order Summary -->
    <div class="border rounded-lg p-4 bg-gray-50">
        <h4 class="font-semibold text-gray-800 mb-3">Order Summary</h4>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Subtotal:</span>
                <span class="font-medium">₹<?= number_format($order['subtotal'], 2) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Delivery Fee:</span>
                <span class="font-medium">₹<?= number_format($order['delivery_fee'], 2) ?></span>
            </div>
            <div class="flex justify-between font-bold text-lg border-t pt-2">
                <span>Total:</span>
                <span class="text-green-600">₹<?= number_format($order['total'], 2) ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($order['payment_id'])): ?>
    <!-- Payment Info -->
    <div class="border rounded-lg p-4">
        <h4 class="font-semibold text-gray-800 mb-3">Payment Information</h4>
        <div class="text-sm">
            <div>
                <span class="text-gray-600">Payment ID:</span>
                <span class="font-mono text-xs"><?= htmlspecialchars($order['payment_id']) ?></span>
            </div>
            <?php if (!empty($order['razorpay_order_id'])): ?>
            <div class="mt-1">
                <span class="text-gray-600">Razorpay Order ID:</span>
                <span class="font-mono text-xs"><?= htmlspecialchars($order['razorpay_order_id']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
