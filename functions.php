<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Currency Format ---
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

// --- Get Product by ID (DB only) ---
function getProductById($id) {
    global $conn;

    $stmt = $conn->prepare("SELECT p.*, c.slug AS category_slug FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id
                            WHERE p.id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $product['source'] = 'database';
    }

    return $product;
}

// --- Get Products by Category Slug (DB only) ---
function getProductsByCategory($slug) {
    global $conn;

    $stmt = $conn->prepare("SELECT p.*, c.slug AS category_slug FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            WHERE c.slug = ?");
    $stmt->execute([$slug]);
    $dynamic = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dynamic as &$d) {
        $d['source'] = 'database';
    }

    return $dynamic;
}

// --- Add to Cart ---
function addToCart($productId, $quantity = 1) {
    $product = getProductById($productId);
    if ($product) {
        if (!isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'image' => $product['image'] ?? '',
                'category' => $product['category_slug'] ?? ''
            ];
        } else {
            $_SESSION['cart'][$productId]['quantity'] += $quantity;
        }
        return true;
    }
    return false;
}

// --- Remove from Cart ---
function removeFromCart($productId) {
    unset($_SESSION['cart'][$productId]);
}

// --- Update Cart Quantity ---
function updateCartQuantity($productId, $quantity) {
    if ($quantity <= 0) {
        removeFromCart($productId);
    } else {
        $_SESSION['cart'][$productId]['quantity'] = $quantity;
    }
}

// --- Cart Total ---
function getCartTotal() {
    $total = 0;
    foreach ($_SESSION['cart'] ?? [] as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}

// --- Cart Item Count ---
function getCartItemCount() {
    $count = 0;
    foreach ($_SESSION['cart'] ?? [] as $item) {
        $count += $item['quantity'];
    }
    return $count;
}

// --- Generate Order ID ---
function generateOrderId() {
    return 'ORD' . date('Ymd') . rand(1000, 9999);
}

// --- All Products (DB only) ---
function getAllProductsCombined() {
    global $conn;

    $stmt = $conn->prepare("
        SELECT p.*, c.slug AS category_slug 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.id DESC
    ");
    $stmt->execute();
    return array_map(function($p) {
        $p['source'] = 'database';
        return $p;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}
