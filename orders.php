<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'image_handler.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: auth/login.php?redirect=orders.php");
    exit;
}

$pageTitle = 'My Orders';

// Fetch orders
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'header.php'; ?>

<style>
/* Adjust content for fixed header */
main.orders-page {
    padding-top: 6rem; /* 96px, adjust to match header height */
}
</style>
<main class="orders-page container mx-auto px-4 pt-5 pb-20 md:pt-8">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">My Orders</h1>


    <!-- Success / Error Messages -->
    <?php if (isset($_GET['message'])): ?>
        <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
            <?php
            $message = $_GET['message'];
            switch($message) {
                case 'order_cancelled_successfully':
                    echo 'Order cancelled successfully!';
                    break;
                case 'already_cancelled':
                    echo 'This order is already cancelled.';
                    break;
                default:
                    echo htmlspecialchars($message);
            }
            ?>
        </div>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4">
            <?php
            $error = $_GET['error'];
            switch($error) {
                case 'invalid_order':
                    echo 'Invalid order ID provided.';
                    break;
                case 'order_not_found':
                    echo 'Order not found or does not belong to you.';
                    break;
                case 'cannot_cancel_completed':
                    echo 'Cannot cancel a completed order.';
                    break;
                case 'cancel_failed':
                    echo 'Failed to cancel order. Please try again.';
                    break;
                default:
                    echo htmlspecialchars($error);
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <p class="text-gray-600">You haven’t placed any orders yet.</p>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($orders as $order): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <p class="text-sm text-gray-600">Order ID: 
                                <span class="font-medium"><?= $order['order_id']; ?></span>
                            </p>
                            <p class="text-sm text-gray-600">Placed on: 
                                <span class="font-medium"><?= date('d M Y, h:i A', strtotime($order['order_date'])); ?></span>
                            </p>
                            <?php 
                            // Get order status with proper fallback handling
                            $rawOrderStatus = $order['order_status'] ?? $order['status'] ?? 'pending';
                            $status = strtolower(trim($rawOrderStatus));
                            
                            // Ensure we have a valid status
                            if (empty($status) || !in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
                                $status = 'pending';
                            }
                            ?>
                            <p class="text-sm text-gray-600">Status:
                                <span class="inline-block px-2 py-1 rounded text-sm font-medium
                                    <?php
                                    switch($status) {
                                        case 'cancelled':
                                            echo 'bg-red-100 text-red-800';
                                            break;
                                        case 'completed':
                                            echo 'bg-green-100 text-green-800';
                                            break;
                                        case 'confirmed':
                                            echo 'bg-blue-100 text-blue-800';
                                            break;
                                        case 'pending':
                                        default:
                                            echo 'bg-yellow-100 text-yellow-800';
                                            break;
                                    }
                                    ?>">
                                    <?= ucfirst($status); ?>
                                </span>
                            </p>
                            <p class="text-sm text-gray-600">Payment Method:
                                <span class="inline-block px-2 py-1 rounded text-sm font-medium
                                    <?= ($order['payment_method'] ?? 'cod') === 'cod' ? 'bg-blue-100 text-blue-600' : 'bg-green-100 text-green-600' ?>">
                                    <?= ($order['payment_method'] ?? 'cod') === 'cod' ? 'COD' : 'Online'; ?>
                                </span>
                                <?php if (($order['payment_method'] ?? 'cod') === 'cod'): ?>
                                    <span class="text-xs ml-2 px-1.5 py-0.5 rounded
                                        <?= ($order['payment_status'] ?? 'pending') === 'paid' ? 'bg-green-100 text-green-600' : 'bg-yellow-100 text-yellow-600' ?>">
                                        <?= ucfirst($order['payment_status'] ?? 'pending'); ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-green-600"><?= formatCurrency($order['total']); ?></p>
                            <p class="text-sm text-gray-500">
                                <?= ucfirst($order['payment_method']); ?> • <?= ucfirst($order['delivery_option']); ?>
                            </p>
                        </div>
                    </div>

                    <?php
                    $stmtItems = $conn->prepare("
                        SELECT oi.*, p.image 
                        FROM order_items oi 
                        LEFT JOIN products p ON oi.product_id = p.id 
                        WHERE oi.order_id = ?
                    ");
                    $stmtItems->execute([$order['order_id']]);
                    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <div class="space-y-3 mb-4">
                        <?php foreach ($items as $item): ?>
                            <div class="flex items-center justify-between border-t pt-3">
                                <div class="flex items-center space-x-3">
                                    <?= generateProductImageHTML($item, 'w-14 h-14 rounded object-cover'); ?>
                                    <div>
                                        <p class="font-medium text-gray-800"><?= htmlspecialchars($item['name']); ?></p>
                                        <p class="text-sm text-gray-500">Qty: <?= $item['quantity']; ?></p>
                                    </div>
                                </div>
                                <p class="font-semibold"><?= formatCurrency($item['price'] * $item['quantity']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <!-- Download Invoice -->
                        <a href="invoice.php?order_id=<?= $order['order_id']; ?>" 
                           class="text-sm bg-white text-gray-700 border border-gray-300 px-3 py-1.5 rounded hover:bg-gray-100">
                            <i class="fas fa-file-invoice mr-1"></i> Download Invoice
                        </a>

                        <!-- Cancel Order -->
                        <?php 
                        // Use the same status processing as above for consistency
                        $rawOrderStatus = $order['order_status'] ?? $order['status'] ?? 'pending';
                        $currentStatus = strtolower(trim($rawOrderStatus));
                        
                        // Ensure we have a valid status
                        if (empty($currentStatus) || !in_array($currentStatus, ['pending', 'confirmed', 'completed', 'cancelled'])) {
                            $currentStatus = 'pending';
                        }
                        
                        // Show cancel button for pending and confirmed orders, hide for completed and cancelled
                        if ($currentStatus === 'pending' || $currentStatus === 'confirmed'): 
                        ?>
                            <a href="cancel_order.php?order_id=<?= $order['order_id']; ?>" 
                               class="text-sm bg-red-100 text-red-600 px-3 py-1.5 rounded hover:bg-red-200"
                               onclick="return confirm('Are you sure you want to cancel this order?');">
                                <i class="fas fa-times-circle mr-1"></i> Cancel Order
                            </a>
                        <?php endif; ?>

                        <!-- Reorder -->
                        <form method="post" action="reorder.php" class="inline">
                            <input type="hidden" name="order_id" value="<?= $order['order_id']; ?>">
                            <button type="submit" 
                                    class="text-sm bg-green-100 text-green-700 px-3 py-1.5 rounded hover:bg-green-200">
                                <i class="fas fa-redo mr-1"></i> Reorder
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
