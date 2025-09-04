<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php?redirect=orders.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = $_POST['order_id'] ?? null;

if (!$order_id) {
    header("Location: orders.php");
    exit;
}

// Fetch order items from DB
$stmt = $conn->prepare("
    SELECT oi.product_id, oi.quantity, p.name, p.price, p.image 
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Add items using product_id as array key (consistent with cart_handler.php)
foreach ($items as $item) {
    $product_id = $item['product_id'];

    if (isset($_SESSION['cart'][$product_id])) {
        // If already in cart, increase quantity
        $_SESSION['cart'][$product_id]['quantity'] += $item['quantity'];
    } else {
        // Add new product to cart
        $_SESSION['cart'][$product_id] = [
            'id' => $product_id,
            'name' => $item['name'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'image' => $item['image']
        ];
    }
}

header("Location: cart.php");
exit;
