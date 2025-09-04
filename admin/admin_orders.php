<?php
session_start();
@include '../config.php'; // must set $conn (PDO)
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Simple logging for debugging
ini_set('log_errors', 1);
ini_set('error_log', '../debug.log');

// ---------- POST handlers ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_order_status') {
        $order_id = $_POST['order_id'] ?? '';
        $new_status = $_POST['order_status'] ?? '';

        error_log("Order status update request: order_id=$order_id, new_status=$new_status");
        if (!empty($order_id) && trim($new_status) !== '') {
            try {
                $check = $conn->prepare("SELECT order_status FROM orders WHERE order_id = ?");
                $check->execute([$order_id]);
                $row = $check->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $old = $row['order_status'];
                    $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
                    $stmt->execute([$new_status, $order_id]);
                    if ($stmt->rowCount() > 0) {
                        $_SESSION['success'] = "Order #$order_id status updated from " . ucfirst($old) . " to " . ucfirst($new_status) . ".";
                    } else {
                        $_SESSION['error'] = "No change (maybe status already '$new_status').";
                    }
                } else {
                    $_SESSION['error'] = "Order not found: $order_id";
                }
            } catch (Exception $e) {
                error_log("Order status update error: " . $e->getMessage());
                $_SESSION['error'] = "Failed to update order status: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Missing order ID or status value.";
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'admin_orders.php'));
        exit;
    }

    if ($action === 'update_payment_status') {
        $order_id = $_POST['order_id'] ?? '';
        $payment_status = $_POST['payment_status'] ?? '';

        error_log("Payment status update request: order_id=$order_id, payment_status=$payment_status");
        if (!empty($order_id) && trim($payment_status) !== '') {
            try {
                $check = $conn->prepare("SELECT payment_method, payment_status FROM orders WHERE order_id = ?");
                $check->execute([$order_id]);
                $row = $check->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    if ($row['payment_method'] !== 'cod') {
                        $_SESSION['error'] = "Only COD orders can have payment status changed here.";
                    } else {
                        $old = $row['payment_status'];
                        $stmt = $conn->prepare("UPDATE orders SET payment_status = ? WHERE order_id = ?");
                        $stmt->execute([$payment_status, $order_id]);
                        if ($stmt->rowCount() > 0) {
                            $_SESSION['success'] = "Payment status for #$order_id updated from " . ucfirst($old) . " to " . ucfirst($payment_status) . ".";
                        } else {
                            $_SESSION['error'] = "No change to payment status (maybe already '$payment_status').";
                        }
                    }
                } else {
                    $_SESSION['error'] = "Order not found: $order_id";
                }
            } catch (Exception $e) {
                error_log("Payment status update error: " . $e->getMessage());
                $_SESSION['error'] = "Failed to update payment status: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Missing order ID or payment status value.";
        }

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'admin_orders.php'));
        exit;
    }
}

// ---------- Filters ----------
$user_filter = $_GET['user_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$payment_filter = $_GET['payment_status'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ---------- Fetch orders ----------
$query = "SELECT o.*, u.name AS customer_name, u.email
          FROM orders o
          JOIN users u ON o.user_id = u.id
          WHERE 1=1";
$params = [];

if ($user_filter !== '') { $query .= " AND o.user_id = ?"; $params[] = $user_filter; }
if ($status_filter !== '') { $query .= " AND o.order_status = ?"; $params[] = $status_filter; }
if ($payment_filter !== '') { $query .= " AND o.payment_status = ?"; $params[] = $payment_filter; }
if ($search !== '') {
    $query .= " AND (o.order_id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
}
if ($date_from !== '') { $query .= " AND DATE(o.order_date) >= ?"; $params[] = $date_from; }
if ($date_to !== '') { $query .= " AND DATE(o.order_date) <= ?"; $params[] = $date_to; }

$query .= " ORDER BY o.order_date DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- Stats ----------
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN order_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN order_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
        SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
        SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as total_revenue,
        SUM(CASE WHEN DATE(order_date) = CURDATE() THEN 1 ELSE 0 END) as today_orders
    FROM orders
")->fetch(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Manage Orders</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Layout fixes: use fixed columns so filters keep aligned and don't overflow */
.form-filters {
    display: grid;
    grid-template-columns: 1fr 220px 220px 140px 140px 110px; /* search, status, payment, date-from, date-to, button */
    gap: 0.75rem;
    align-items: center;
}

/* Make the orders table vertically scrollable but keep horizontal inner scroll for wide tables */
.table-wrapper { max-height: 60vh; }
.table-wrapper > .overflow-x-auto { max-height: 60vh; overflow: auto; }

/* Modal content: larger card to show full order details and scroll internally */
#orderModal .modal-box {
    width: 80%;
    max-width: 900px; /* slightly narrower */
    max-height: 95vh; /* taller */
    overflow-y: auto;
    padding: 1.25rem;
}

/* Consistent compact select/input heights */
.select-compact { padding-top: .45rem; padding-bottom: .45rem; height: 2.25rem; }
.form-filters input[type="date"], .form-filters select, .form-filters input[type="text"] { box-sizing: border-box; }
</style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php include 'admin_header.php'; ?>

<div class="p-6 max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Manage Orders</h1>
        <a href="admin_page.php" class="px-4 py-2 bg-gray-700 text-white rounded">Dashboard</a>
    </div>

    <!-- messages -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="mb-4 p-3 rounded bg-green-100 text-green-800"><?= htmlspecialchars($_SESSION['success']); ?></div>
        <?php unset($_SESSION['success']); endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="mb-4 p-3 rounded bg-red-100 text-red-800"><?= htmlspecialchars($_SESSION['error']); ?></div>
        <?php unset($_SESSION['error']); endif; ?>

    <!-- filters -->
    <form method="GET" class="bg-white p-4 rounded mb-6 form-filters">
        <input type="text" name="search" placeholder="Order ID, Customer, Email" value="<?= htmlspecialchars($search) ?>" class="px-3 py-2 border rounded select-compact filter-search">
        <select name="status" class="px-3 py-2 border rounded select-compact filter-status">
            <option value="">All Order Status</option>
            <option value="pending" <?= $status_filter==='pending'?'selected':'' ?>>Pending</option>
            <option value="confirmed" <?= $status_filter==='confirmed'?'selected':'' ?>>Confirmed</option>
            <option value="completed" <?= $status_filter==='completed'?'selected':'' ?>>Completed</option>
            <option value="cancelled" <?= $status_filter==='cancelled'?'selected':'' ?>>Cancelled</option>
        </select>
    <select name="payment_status" class="px-3 py-2 border rounded select-compact filter-payment">
            <option value="">All Payment Status</option>
            <option value="pending" <?= $payment_filter==='pending'?'selected':'' ?>>Pending</option>
            <option value="paid" <?= $payment_filter==='paid'?'selected':'' ?>>Paid</option>
            <option value="completed" <?= $payment_filter==='completed'?'selected':'' ?>>Completed</option>
        </select>
        <div class="filter-dates" style="display:flex;gap:.5rem;align-items:center;">
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="px-3 py-2 border rounded select-compact">
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="px-3 py-2 border rounded select-compact">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded filter-action">Filter</button>
        </div>
    </form>

    <!-- stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded shadow">Total: <strong><?= $stats['total_orders'] ?></strong></div>
        <div class="bg-white p-4 rounded shadow">Pending: <strong><?= $stats['pending_orders'] ?></strong></div>
        <div class="bg-white p-4 rounded shadow">Completed: <strong><?= $stats['completed_orders'] ?></strong></div>
        <div class="bg-white p-4 rounded shadow">Revenue: <strong>₹<?= number_format($stats['total_revenue']) ?></strong></div>
    </div>

    <!-- orders table -->
    <div class="bg-white rounded shadow table-wrapper">
        <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr class="text-left text-sm text-gray-600">
                    <th class="p-3">Order</th>
                    <th class="p-3">Customer</th>
                    <th class="p-3">Amount</th>
                    <th class="p-3">Payment</th>
                    <th class="p-3">Order Status</th>
                    <th class="p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="6" class="p-6 text-center text-gray-500">No orders found</td></tr>
                <?php else: foreach ($orders as $order): ?>
                    <tr class="border-t hover:bg-gray-50">
                        <td class="p-3">
                            <div class="font-medium">#<?= htmlspecialchars($order['order_id']) ?></div>
                            <div class="text-xs text-gray-500"><?= date('M j, Y g:i A', strtotime($order['order_date'])) ?></div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium"><?= htmlspecialchars($order['customer_name']) ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($order['email']) ?></div>
                        </td>
                        <td class="p-3">
                            ₹<?= number_format($order['total'], 2) ?>
                            <div class="text-xs text-gray-500">Subtotal ₹<?= number_format($order['subtotal'],2) ?> • Delivery ₹<?= number_format($order['delivery_fee'],2) ?></div>
                        </td>

                        <!-- Payment column: COD select or online badge -->
                        <td class="p-3">
                            <?php if ($order['payment_method'] === 'cod'): ?>
                                <form method="POST" class="inline-block">
                                    <input type="hidden" name="action" value="update_payment_status">
                                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                    <select
                                        name="payment_status"
                                        data-original-value="<?= htmlspecialchars($order['payment_status']) ?>"
                                            onchange="handlePaymentStatusChange(this, '<?= htmlspecialchars($order['order_id']) ?>')"
                                            class="text-xs rounded-full px-2 py-1 font-semibold border-0 <?= ($order['payment_status']==='paid')?'bg-green-100 text-green-800':'bg-yellow-100 text-yellow-800' ?>">
                                            <option value="pending" <?= $order['payment_status']==='pending'?'selected':'' ?>>Pending</option>
                                            <option value="paid" <?= $order['payment_status']==='paid'?'selected':'' ?>>Paid</option>
                                    </select>
                                </form>
                                <div class="text-xs text-gray-500 mt-1">COD</div>
                            <?php else: 
                                $badgeClass = 'bg-gray-100 text-gray-800';
                                switch ($order['payment_status']) {
                                    case 'pending': $badgeClass = 'bg-yellow-100 text-yellow-800'; break;
                                    case 'paid': $badgeClass = 'bg-green-100 text-green-800'; break;
                                    case 'completed': $badgeClass = 'bg-blue-100 text-blue-800'; break;
                                }
                            ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($order['payment_status'])) ?></span>
                                <div class="text-xs text-gray-500 mt-1">Online</div>
                            <?php endif; ?>
                        </td>

                        <!-- Order status select -->
                        <td class="p-3">
                            <form method="POST" class="inline-block">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                <select name="order_status"
                                    data-original-value="<?= htmlspecialchars($order['order_status']) ?>"
                                    onchange="handleOrderStatusChange(this)"
                                    class="text-xs rounded-full px-2 py-1 font-semibold border-0 <?php
                                        switch($order['order_status']) {
                                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'confirmed': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'completed': echo 'bg-green-100 text-green-800'; break;
                                            case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?>">
                                    <option value="pending" <?= $order['order_status']==='pending'?'selected':'' ?>>Pending</option>
                                    <option value="confirmed" <?= $order['order_status']==='confirmed'?'selected':'' ?>>Confirmed</option>
                                    <option value="completed" <?= $order['order_status']==='completed'?'selected':'' ?>>Completed</option>
                                    <option value="cancelled" <?= $order['order_status']==='cancelled'?'selected':'' ?>>Cancelled</option>
                                </select>
                            </form>
                        </td>

                        <td class="p-3 text-sm">
                            <button onclick="viewOrderDetails('<?= htmlspecialchars($order['order_id']) ?>')" class="text-blue-600 hover:underline mr-2"><i class="fas fa-eye"></i> View</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal container for order details -->
<div id="orderModal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-50 z-50">
    <div class="mx-auto my-4 bg-white max-w-2xl p-6 rounded shadow modal-box">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">Order Details</h3>
            <button onclick="closeModal()" class="text-gray-500"><i class="fas fa-times"></i></button>
        </div>
        <div id="orderContent"></div>
    </div>
</div>

<script>
function viewOrderDetails(orderId) {
    fetch('get_order_details.php?order_id=' + encodeURIComponent(orderId))
        .then(r => r.text())
        .then(html => {
            document.getElementById('orderContent').innerHTML = html;
            document.getElementById('orderModal').classList.remove('hidden');
        }).catch(() => alert('Failed to load details'));
}
function closeModal(){ document.getElementById('orderModal').classList.add('hidden'); }
// Close modal when clicking on backdrop
document.getElementById('orderModal').addEventListener('click', function(e){
    if (e.target === this) closeModal();
});

// Payment status handler (mirrors order status behavior)
function handlePaymentStatusChange(selectElement, orderId) {
    const newStatus = selectElement.value;
    const originalStatus = selectElement.dataset.originalValue ?? selectElement.value;

    if (!newStatus) {
        alert('Please select a valid payment status');
        selectElement.value = originalStatus;
        return;
    }
    if (newStatus === originalStatus) return;

    updatePaymentStatusColor(selectElement, newStatus);

    if (confirm(`Change payment status for order #${orderId} from "${originalStatus}" to "${newStatus}"?`)) {
        const form = selectElement.closest('form');

        // create hidden input with the value that will be sent server-side
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'payment_status';
        hidden.value = newStatus;
        form.appendChild(hidden);

        // disable select so only hidden input is sent
        selectElement.disabled = true;
        form.submit();
    } else {
        selectElement.value = originalStatus;
        updatePaymentStatusColor(selectElement, originalStatus);
    }
}

function updatePaymentStatusColor(selectElement, status) {
    // remove possible previous color classes
    selectElement.className = selectElement.className.replace(/bg-(yellow|green|blue|gray)-100 text-(yellow|green|blue|gray)-800/g, '');
    selectElement.classList.add('text-xs','rounded-full','px-2','py-1','font-semibold','border-0');
    switch (status) {
        case 'pending': selectElement.classList.add('bg-yellow-100','text-yellow-800'); break;
        case 'paid': selectElement.classList.add('bg-green-100','text-green-800'); break;
        case 'completed': selectElement.classList.add('bg-blue-100','text-blue-800'); break;
        default: selectElement.classList.add('bg-gray-100','text-gray-800');
    }
}

// Order status handler
function handleOrderStatusChange(selectElement) {
    const newStatus = selectElement.value;
    const originalStatus = selectElement.dataset.originalValue ?? selectElement.value;

    if (!newStatus) {
        alert('Please select a valid status');
        selectElement.value = originalStatus;
        return;
    }
    if (newStatus === originalStatus) return;

    updateOrderStatusColor(selectElement, newStatus);
    if (confirm(`Change order status from "${originalStatus}" to "${newStatus}"?`)) {
        const form = selectElement.closest('form');

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'order_status';
        hidden.value = newStatus;
        form.appendChild(hidden);

        selectElement.disabled = true;
        form.submit();
    } else {
        selectElement.value = originalStatus;
        updateOrderStatusColor(selectElement, originalStatus);
    }
}

function updateOrderStatusColor(selectElement, status) {
    selectElement.className = selectElement.className.replace(/bg-(yellow|blue|green|red|gray)-100 text-(yellow|blue|green|red|gray)-800/g, '');
    switch(status) {
        case 'pending': selectElement.classList.add('bg-yellow-100','text-yellow-800'); break;
        case 'confirmed': selectElement.classList.add('bg-blue-100','text-blue-800'); break;
        case 'completed': selectElement.classList.add('bg-green-100','text-green-800'); break;
        case 'cancelled': selectElement.classList.add('bg-red-100','text-red-800'); break;
        default: selectElement.classList.add('bg-gray-100','text-gray-800');
    }
}

// Ensure dataset.originalValue is set for selects (defensive)
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('select[name="payment_status"], select[name="order_status"]').forEach(s => {
        s.dataset.originalValue = s.dataset.originalValue || s.getAttribute('data-original-value') || s.value || '';
    });
});
</script>
</body>
</html>