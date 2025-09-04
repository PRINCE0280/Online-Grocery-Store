<?php
@include '../config.php';

// Redirect if not logged in
$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('Location: ../auth/login.php');
    exit;
}

// Handle order status updates
if ($_POST['action'] ?? '' === 'update_order_status') {
    $order_id = $_POST['order_id'] ?? '';
    $new_status = $_POST['order_status'] ?? '';  // FIXED: Changed from 'status' to 'order_status'
    
    // Enhanced debug information
    error_log("=== ORDER STATUS UPDATE DEBUG ===");
    error_log("Raw POST Data: " . json_encode($_POST));
    error_log("HTTP Referer: " . ($_SERVER['HTTP_REFERER'] ?? 'Not set'));
    error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("Content Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));
    error_log("Order ID: '$order_id' (length: " . strlen($order_id) . ")");
    error_log("New Status: '$new_status' (length: " . strlen($new_status) . ")");
    error_log("Status is empty: " . (empty($new_status) ? 'YES' : 'NO'));
    error_log("Status is just whitespace: " . (trim($new_status) === '' ? 'YES' : 'NO'));
    
    // Check if both values are present and not empty
    if (!empty($order_id) && !empty($new_status) && trim($new_status) !== '') {
        try {
            // First check if order exists
            $checkStmt = $conn->prepare("SELECT o.order_status, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.order_id = ?");
            $checkStmt->execute([$order_id]);
            $currentOrder = $checkStmt->fetch();
            
            if ($currentOrder) {
                $oldStatus = $currentOrder['order_status'];
                $customerName = $currentOrder['customer_name'];
                error_log("Found order: $order_id | Customer: $customerName | Current Status: '$oldStatus'");
                
                // Special handling for 'confirmed' status
                if ($new_status === 'confirmed') {
                    error_log("SPECIAL CASE: Updating to 'confirmed' status");
                }
                
                $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
                error_log("Executing UPDATE query with status='$new_status' and order_id='$order_id'");
                
                $result = $stmt->execute([$new_status, $order_id]);
                $rowsAffected = $stmt->rowCount();
                
                error_log("SQL Execute Result: " . ($result ? 'TRUE' : 'FALSE'));
                error_log("Rows Affected: $rowsAffected");
                
                if ($result && $rowsAffected > 0) {
                    // Double-check the update worked
                    $verifyStmt = $conn->prepare("SELECT order_status FROM orders WHERE order_id = ?");
                    $verifyStmt->execute([$order_id]);
                    $verifiedOrder = $verifyStmt->fetch();
                    $actualStatus = $verifiedOrder['order_status'];
                    
                    error_log("Verification check - Actual status in DB: '$actualStatus'");
                    
                    if ($actualStatus === $new_status) {
                        $_SESSION['success'] = "Order #$order_id status updated from " . ucfirst($oldStatus) . " to " . ucfirst($new_status) . " successfully!";
                        error_log("✅ Order status updated successfully - verified in database");
                    } else {
                        $_SESSION['error'] = "Status update failed verification. Expected: '$new_status', Got: '$actualStatus'";
                        error_log("❌ Verification failed - database shows different status");
                    }
                } else {
                    $_SESSION['error'] = "No changes made to order status. Order ID: $order_id, Status: $new_status (Result: " . ($result ? 'TRUE' : 'FALSE') . ", Rows: $rowsAffected)";
                    error_log("❌ No rows affected - possible ENUM constraint issue");
                    
                    // Check if it's an ENUM issue
                    $enumStmt = $conn->query("SHOW COLUMNS FROM orders WHERE Field = 'order_status'");
                    $enumInfo = $enumStmt->fetch();
                    error_log("Order Status Column Definition: " . $enumInfo['Type']);
                }
            } else {
                $_SESSION['error'] = "Order not found: $order_id";
                error_log("❌ Order not found in database");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to update order status: " . $e->getMessage();
            error_log("❌ Exception in order status update: " . $e->getMessage());
            error_log("Exception Code: " . $e->getCode());
        }
    } else {
        // Enhanced validation with specific error messages
        if (empty($order_id) || trim($order_id) === '') {
            $_SESSION['error'] = "Error: Missing order ID. Please try again.";
            error_log("❌ Missing order ID in form submission");
        } elseif (empty($new_status) || trim($new_status) === '') {
            $_SESSION['error'] = "Error: Missing order status value. Please select a valid status and try again.";
            error_log("❌ Empty status value for Order ID: '$order_id'. This indicates JavaScript form submission issue.");
        } else {
            $_SESSION['error'] = "Error: Invalid data received. Order ID: '$order_id', Status: '$new_status'";
            error_log("❌ Other validation error - Order ID: '$order_id', Status: '$new_status'");
        }
    }
    
    error_log("=== END ORDER STATUS UPDATE DEBUG ===");
    
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Handle payment status updates (for COD orders)
if ($_POST['action'] ?? '' === 'update_payment_status') {
    $order_id = $_POST['order_id'] ?? '';
    $payment_status = $_POST['payment_status'] ?? '';
    
    // Enhanced debug information
    error_log("=== PAYMENT STATUS UPDATE DEBUG ===");
    error_log("POST Data: " . print_r($_POST, true));
    error_log("Order ID: '$order_id' | New Payment Status: '$payment_status'");
    
    // Check if both values are present and not empty
    if (!empty($order_id) && !empty($payment_status) && trim($payment_status) !== '') {
        try {
            // First check if the order exists and is COD
            $checkStmt = $conn->prepare("SELECT o.payment_method, o.payment_status, u.name as customer_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.order_id = ?");
            $checkStmt->execute([$order_id]);
            $order = $checkStmt->fetch();
            
            if ($order) {
                $oldPaymentStatus = $order['payment_status'];
                $paymentMethod = $order['payment_method'];
                $customerName = $order['customer_name'];
                
                error_log("Found order: $order_id | Customer: $customerName | Method: '$paymentMethod' | Current Payment Status: '$oldPaymentStatus'");
                
                if ($paymentMethod === 'cod') {
                    error_log("COD Order - proceeding with payment status update");
                    
                    $stmt = $conn->prepare("UPDATE orders SET payment_status = ? WHERE order_id = ?");
                    error_log("Executing payment UPDATE query with payment_status='$payment_status' and order_id='$order_id'");
                    
                    $result = $stmt->execute([$payment_status, $order_id]);
                    $rowsAffected = $stmt->rowCount();
                    
                    error_log("Payment SQL Execute Result: " . ($result ? 'TRUE' : 'FALSE'));
                    error_log("Payment Rows Affected: $rowsAffected");
                    
                    if ($result && $rowsAffected > 0) {
                        // Verify the update
                        $verifyStmt = $conn->prepare("SELECT payment_status FROM orders WHERE order_id = ?");
                        $verifyStmt->execute([$order_id]);
                        $verifiedPayment = $verifyStmt->fetch();
                        $actualPaymentStatus = $verifiedPayment['payment_status'];
                        
                        error_log("Payment verification check - Actual payment status in DB: '$actualPaymentStatus'");
                        
                        if ($actualPaymentStatus === $payment_status) {
                            $_SESSION['success'] = "Payment status updated to " . ucfirst($payment_status) . " successfully!";
                            error_log("✅ Payment status updated successfully - verified in database");
                        } else {
                            $_SESSION['error'] = "Payment status update failed verification. Expected: '$payment_status', Got: '$actualPaymentStatus'";
                            error_log("❌ Payment verification failed - database shows different status");
                        }
                    } else {
                        $_SESSION['error'] = "No changes made to payment status. (Result: " . ($result ? 'TRUE' : 'FALSE') . ", Rows: $rowsAffected)";
                        error_log("❌ No rows affected in payment update");
                        
                        // Check ENUM constraint
                        $enumStmt = $conn->query("SHOW COLUMNS FROM orders WHERE Field = 'payment_status'");
                        $enumInfo = $enumStmt->fetch();
                        error_log("Payment Status Column Definition: " . $enumInfo['Type']);
                    }
                } else {
                    $_SESSION['error'] = "Invalid order or not a COD order. Payment method: '$paymentMethod'";
                    error_log("❌ Not a COD order - payment method is '$paymentMethod'");
                }
            } else {
                $_SESSION['error'] = "Order not found: $order_id";
                error_log("❌ Order not found for payment update");
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to update payment status: " . $e->getMessage();
            error_log("❌ Exception in payment status update: " . $e->getMessage());
            error_log("Payment Exception Code: " . $e->getCode());
        }
    } else {
        $_SESSION['error'] = "Missing order ID or payment status. Order ID: '$order_id', Payment Status: '$payment_status'";
        error_log("❌ Missing payment update data");
    }
    
    error_log("=== END PAYMENT STATUS UPDATE DEBUG ===");
    
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Get filter parameters
$user_filter = $_GET['user_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment_status'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query with filters
$query = "SELECT o.*, u.name as customer_name, u.email 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE 1=1";

$params = [];

if (!empty($user_filter)) {
    $query .= " AND o.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($status_filter)) {
    $query .= " AND o.order_status = ?";
    $params[] = $status_filter;
}

if (!empty($payment_filter)) {
    $query .= " AND o.payment_status = ?";
    $params[] = $payment_filter;
}

if (!empty($search)) {
    $query .= " AND (o.order_id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($date_from)) {
    $query .= " AND DATE(o.order_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(o.order_date) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY o.order_date DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order statistics
$stats_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
    SUM(CASE WHEN order_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
    SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
    SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
    SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as total_revenue,
    SUM(CASE WHEN DATE(order_date) = CURDATE() THEN 1 ELSE 0 END) as today_orders
    FROM orders";
$stats = $conn->query($stats_query)->fetch(PDO::FETCH_ASSOC);

// Get user name if filtering by specific user
$user_name = '';
if (!empty($user_filter)) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_filter]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $user_data['name'] ?? '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">

<?php include 'admin_header.php'; ?>

<div class="p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Manage Orders</h1>
            <?php if (!empty($user_name)): ?>
                <p class="text-gray-600 mt-1">Orders for: <?= htmlspecialchars($user_name) ?></p>
            <?php endif; ?>
        </div>
        <div class="flex space-x-3">
            <?php if (!empty($user_filter)): ?>
                <a href="admin_users.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-users mr-2"></i>Back to Users
                </a>
            <?php endif; ?>
            <a href="admin_page.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                <i class="fas fa-arrow-left mr-2"></i>Dashboard
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_SESSION['success']); ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_SESSION['error']); ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4 mb-8">
        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-500">
            <div class="text-center">
                <i class="fas fa-shopping-cart text-blue-500 text-xl mb-2"></i>
                <h3 class="text-sm font-semibold text-gray-800">Total Orders</h3>
                <p class="text-xl font-bold text-blue-600"><?= $stats['total_orders'] ?></p>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-yellow-500">
            <div class="text-center">
                <i class="fas fa-clock text-yellow-500 text-xl mb-2"></i>
                <h3 class="text-sm font-semibold text-gray-800">Pending</h3>
                <p class="text-xl font-bold text-yellow-600"><?= $stats['pending_orders'] ?></p>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-blue-400">
            <div class="text-center">
                <i class="fas fa-thumbs-up text-blue-400 text-xl mb-2"></i>
                <h3 class="text-sm font-semibold text-gray-800">Confirmed</h3>
                <p class="text-xl font-bold text-blue-500"><?= $stats['confirmed_orders'] ?></p>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-green-500">
            <div class="text-center">
                <i class="fas fa-check-circle text-green-500 text-xl mb-2"></i>
                <h3 class="text-sm font-semibold text-gray-800">Completed</h3>
                <p class="text-xl font-bold text-green-600"><?= $stats['completed_orders'] ?></p>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-red-500">
            <div class="text-center">
                <i class="fas fa-times-circle text-red-500 text-xl mb-2"></i>
                <h3 class="text-sm font-semibold text-gray-800">Cancelled</h3>
                <p class="text-xl font-bold text-red-600"><?= $stats['cancelled_orders'] ?></p>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-purple-500">
            <div class="text-center">
                <i class="fas fa-rupee-sign text-purple-500 text-xl mb-2"></i>
                <h3 class="text-sm font-semibold text-gray-800">Revenue</h3>
                <p class="text-lg font-bold text-purple-600">₹<?= number_format($stats['total_revenue']) ?></p>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border-l-4 border-indigo-500">
            <div class="text-center">
                <i class="fas fa-calendar-day text-indigo-500 text-xl mb-2"></i>
                <h3 class="text-sm font-semibold text-gray-800">Today</h3>
                <p class="text-xl font-bold text-indigo-600"><?= $stats['today_orders'] ?></p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
            <?php if (!empty($user_filter)): ?>
                <input type="hidden" name="user_id" value="<?= htmlspecialchars($user_filter) ?>">
            <?php endif; ?>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Order ID, Customer..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Order Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                <select name="payment_status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
                    <option value="">All Payments</option>
                    <option value="paid" <?= $payment_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="pending" <?= $payment_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm">
            </div>
            
            <div class="flex items-end space-x-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition text-sm">
                    <i class="fas fa-search mr-1"></i>Filter
                </button>
                <a href="<?= empty($user_filter) ? 'admin_orders.php' : 'admin_orders.php?user_id=' . $user_filter ?>" 
                   class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition text-sm">
                    <i class="fas fa-refresh mr-1"></i>Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order Info</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-shopping-cart text-4xl mb-4"></i>
                                <p class="text-lg">No orders found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        #<?= htmlspecialchars($order['order_id']) ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?= date('M j, Y g:i A', strtotime($order['order_date'])) ?>
                                    </div>
                                    <div class="text-xs text-gray-400">
                                        <?= ucfirst($order['delivery_option']) ?> delivery
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($order['customer_name']) ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?= htmlspecialchars($order['email']) ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?= htmlspecialchars($order['mobile']) ?>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        ₹<?= number_format($order['total'], 2) ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Subtotal: ₹<?= number_format($order['subtotal'], 2) ?><br>
                                        Delivery: ₹<?= number_format($order['delivery_fee'], 2) ?>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($order['payment_method'] === 'cod'): ?>
                                        <!-- COD Payment - Show Pending/Paid dropdown -->
                                        <form method="POST" class="inline-block payment-form" onsubmit="return confirmStatusChange()">
                                            <input type="hidden" name="action" value="update_payment_status">
                                            <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                            <select name="payment_status" 
                                                    onchange="handlePaymentStatusChange(this)" 
                                                    class="text-xs rounded-full px-2 py-1 font-semibold border-0
                                                    <?= $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                                <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                            </select>
                                        </form>
                                        <div class="text-xs text-gray-500 mt-1">COD</div>
                                    <?php else: ?>
                                        <!-- Online Payment - Show fixed status -->
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            <?= $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= ucfirst($order['payment_status']) ?>
                                        </span>
                                        <div class="text-xs text-gray-500 mt-1">Online</div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <!-- Standard Order Status for all orders -->
                                    <form method="POST" class="inline-block order-status-form">
                                        <input type="hidden" name="action" value="update_order_status">
                                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                        <select name="order_status" onchange="handleOrderStatusChange(this)" 
                                                class="text-xs rounded-full px-2 py-1 font-semibold border-0
                                                <?php
                                                switch($order['order_status']) {
                                                    case 'pending':
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'confirmed':
                                                        echo 'bg-blue-100 text-blue-800';
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
                                            <option value="pending" <?= $order['order_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="confirmed" <?= $order['order_status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                            <option value="completed" <?= $order['order_status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                            <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                    </form>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="viewOrderDetails('<?= $order['order_id'] ?>')" 
                                            class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div id="orderModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-900">Order Details</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="orderContent">
            <!-- Order details will be loaded here -->
        </div>
    </div>
</div>

<script>
function confirmStatusChange() {
    return confirm('Are you sure you want to change this order status?');
}

async function viewOrderDetails(orderId) {
    try {
        const response = await fetch('get_order_details.php?order_id=' + orderId);
        const data = await response.text();
        document.getElementById('orderContent').innerHTML = data;
        document.getElementById('orderModal').classList.remove('hidden');
    } catch (error) {
        alert('Error loading order details');
    }
}

function closeModal() {
    document.getElementById('orderModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('orderModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Handle dynamic color changes for payment status dropdowns
function updatePaymentStatusColor(selectElement) {
    const selectedValue = selectElement.value;
    
    // Remove existing classes
    selectElement.className = selectElement.className.replace(/bg-(green|yellow)-100 text-(green|yellow)-800/g, '');
    
    // Add appropriate classes based on selection
    if (selectedValue === 'paid') {
        selectElement.classList.add('bg-green-100', 'text-green-800');
    } else {
        selectElement.classList.add('bg-yellow-100', 'text-yellow-800');
    }
}

// Handle dynamic color changes for order status dropdowns
function updateOrderStatusColor(selectElement) {
    const selectedValue = selectElement.value;
    
    // Remove existing color classes
    selectElement.className = selectElement.className.replace(/bg-(yellow|blue|green|red|gray)-100 text-(yellow|blue|green|red|gray)-800/g, '');
    
    // Add appropriate classes based on selection
    switch(selectedValue) {
        case 'pending':
            selectElement.classList.add('bg-yellow-100', 'text-yellow-800');
            break;
        case 'confirmed':
            selectElement.classList.add('bg-blue-100', 'text-blue-800');
            break;
        case 'completed':
            selectElement.classList.add('bg-green-100', 'text-green-800');
            break;
        case 'cancelled':
            selectElement.classList.add('bg-red-100', 'text-red-800');
            break;
        default:
            selectElement.classList.add('bg-gray-100', 'text-gray-800');
    }
}

// Handle payment status change with confirmation and delay
function handlePaymentStatusChange(selectElement) {
    const oldValue = selectElement.dataset.originalValue || selectElement.value;
    const newValue = selectElement.value;
    
    console.log('Payment Status Change Debug:');
    console.log('Old Value:', oldValue);
    console.log('New Value:', newValue);
    console.log('Original Value in dataset:', selectElement.dataset.originalValue);
    
    // Prevent empty submissions
    if (!newValue || newValue === '') {
        console.error('Empty payment status value detected, preventing submission');
        return false;
    }
    
    // Update color immediately
    updatePaymentStatusColor(selectElement);
    
    // Show confirmation and submit after delay
    setTimeout(function() {
        if (confirm('Are you sure you want to change the payment status from "' + (oldValue || 'unknown') + '" to "' + newValue + '"?')) {
            console.log('User confirmed payment status change, submitting form');
            console.log('Payment form data before submit:');
            
            const form = selectElement.closest('form');
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                console.log(key + ':', value);
            }
            
            // Update the stored original value to new value
            selectElement.dataset.originalValue = newValue;
            
            // Submit the form
            form.submit();
        } else {
            console.log('User cancelled payment status change, rolling back');
            // Rollback to original value and color
            selectElement.value = oldValue || selectElement.dataset.originalValue;
            updatePaymentStatusColor(selectElement);
        }
    }, 300); // Small delay to show color change
}

// Handle order status change with confirmation and delay
function handleOrderStatusChange(selectElement) {
    // Store the current value IMMEDIATELY to prevent loss
    const selectedValue = selectElement.value;
    
    console.log('=== ORDER STATUS CHANGE DEBUG ===');
    console.log('Selected Value:', selectedValue);
    console.log('Value Length:', selectedValue ? selectedValue.length : 0);
    console.log('Is Empty:', selectedValue === '');
    console.log('Element:', selectElement);
    
    // IMMEDIATE validation - prevent any empty submissions
    if (!selectedValue || selectedValue === '' || selectedValue.length === 0) {
        console.error('❌ EMPTY VALUE DETECTED - PREVENTING ALL ACTIONS');
        alert('Please select a valid status from the dropdown');
        return false; // Stop everything immediately
    }
    
    // Store original value for rollback (if not already stored)
    if (!selectElement.dataset.originalValue) {
        selectElement.dataset.originalValue = selectedValue;
    }
    const originalValue = selectElement.dataset.originalValue;
    
    console.log('Original Value:', originalValue);
    
    // If same value, don't proceed
    if (selectedValue === originalValue) {
        console.log('Same value selected, no change needed');
        return false;
    }
    
    // Update color immediately
    updateOrderStatusColor(selectElement);
    
    // Confirmation with minimal delay
    setTimeout(function() {
        // Triple check - value must still be there
        const currentValue = selectElement.value;
        if (!currentValue || currentValue === '' || currentValue !== selectedValue) {
            console.error('❌ Value changed or disappeared during confirmation delay!');
            console.error('Expected:', selectedValue, 'Current:', currentValue);
            alert('Error: Status value was lost. Please try selecting again.');
            selectElement.value = originalValue; // Reset
            updateOrderStatusColor(selectElement);
            return;
        }
        
        const confirmMessage = `Are you sure you want to change the order status from "${originalValue}" to "${selectedValue}"?`;
        
        if (confirm(confirmMessage)) {
            console.log('✅ User confirmed, preparing form submission');
            
            // Final comprehensive validation
            const form = selectElement.closest('form');
            const orderIdInput = form.querySelector('input[name="order_id"]');
            const actionInput = form.querySelector('input[name="action"]');
            const statusInput = selectElement;
            
            console.log('=== FINAL FORM VALIDATION ===');
            console.log('Order ID Input:', orderIdInput ? orderIdInput.value : 'MISSING');
            console.log('Action Input:', actionInput ? actionInput.value : 'MISSING');
            console.log('Status Input:', statusInput ? statusInput.value : 'MISSING');
            
            // Ensure all required fields are present
            if (!orderIdInput || !orderIdInput.value || 
                !actionInput || !actionInput.value || 
                !statusInput || !statusInput.value) {
                
                console.error('❌ MISSING FORM DATA - ABORTING SUBMISSION');
                alert('Error: Missing form data. Please refresh the page and try again.');
                selectElement.value = originalValue;
                updateOrderStatusColor(selectElement);
                return;
            }
            
            console.log('✅ All validation passed, submitting form');
            
            // Update stored original value
            selectElement.dataset.originalValue = selectedValue;
            
            // Submit form
            form.submit();
        } else {
            console.log('❌ User cancelled, rolling back');
            selectElement.value = originalValue;
            updateOrderStatusColor(selectElement);
        }
    }, 50); // Minimal delay - just enough to ensure DOM stability
}

// Initialize original values for all status selects
document.addEventListener('DOMContentLoaded', function() {
    const paymentSelects = document.querySelectorAll('select[name="payment_status"]');
    paymentSelects.forEach(function(select) {
        select.dataset.originalValue = select.value;
    });
    
    const orderSelects = document.querySelectorAll('select[name="order_status"]');
    orderSelects.forEach(function(select) {
        select.dataset.originalValue = select.value;
    });
});
</script>

</body>
</html>
