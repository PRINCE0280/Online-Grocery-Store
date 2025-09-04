<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'image_handler.php';

//session_start();

$order_id = $_GET['order_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$order_id || !$user_id) {
    header("Location: orders.php");
    exit;
}

// Fetch order
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "Order not found.";
    exit;
}

// Fetch items
$stmtItems = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - <?= $order_id ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
    </style>
</head>
<body class="bg-gray-100 py-10">
<div id="printable-area" class="max-w-3xl mx-auto bg-white shadow-md p-8 rounded-lg">
    <div class="text-center mb-6">
        <h1 class="text-3xl font-bold text-green-600">FreshMart Invoice</h1>
        <p class="text-gray-600">Order ID: <?= $order['order_id'] ?></p>
        <p class="text-gray-600">Date: <?= date('d M Y, h:i A', strtotime($order['order_date'])) ?></p>
    </div>

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">Customer Info</h2>
        <p><strong>Name:</strong> <?= htmlspecialchars($order['name']) ?></p>
        <p><strong>Mobile:</strong> <?= $order['mobile'] ?></p>
        <p><strong>Address:</strong> <?= htmlspecialchars($order['address']) ?> - <?= $order['pincode'] ?></p>
    </div>

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">Items</h2>
        <table class="w-full text-left text-sm">
            <thead class="border-b">
                <tr>
                    <th class="py-2">Product</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr class="border-b">
                    <td class="py-2"><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= formatCurrency($item['price']) ?></td>
                    <td class="text-right"><?= formatCurrency($item['price'] * $item['quantity']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="text-right space-y-1">
        <p><strong>Subtotal:</strong> <?= formatCurrency($order['subtotal']) ?></p>
        <p><strong>Delivery Fee:</strong> <?= $order['delivery_fee'] == 0 ? 'Free' : formatCurrency($order['delivery_fee']) ?></p>
        <p class="text-lg font-bold"><strong>Total:</strong> <?= formatCurrency($order['total']) ?></p>
    </div>
</div>

<!-- Buttons -->
<div class="flex justify-center gap-4 mt-8 no-print">
    <a href="orders.php" 
       class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
        Back to Orders
    </a>
    <button onclick="window.print()" 
            class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition">
        <i class="fas fa-print mr-2"></i>Print Invoice
    </button>
</div>
</body>
</html>
