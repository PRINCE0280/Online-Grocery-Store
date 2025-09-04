<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'product_images.php';

// ✅ Redirect to login if user not logged in
if (!isset($_SESSION['user_id'])) {
    $redirectBack = urlencode($_SERVER['HTTP_REFERER'] ?? 'cart.php');
    header("Location: auth/login.php?redirect={$redirectBack}");
    exit;
}

// ✅ Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ✅ Handle cart actions only via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $productId = (int)($_POST['product_id'] ?? 0);
            $quantity = max(1, (int)($_POST['quantity'] ?? 1));

            if ($productId > 0) {
                // 🟢 Fetch from DB
                $stmt = $conn->prepare("SELECT id, name, price, image FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                // 🔁 If not found in DB, check $products array from config.php
                if (!$product && isset($products[$productId])) {
                    $product = [
                        'id'    => $productId,
                        'name'  => $products[$productId]['name'],
                        'price' => $products[$productId]['price'],
                        'image' => $products[$productId]['image'] ?? null
                    ];
                }

                if ($product) {
                    $found = false;
                    foreach ($_SESSION['cart'] as &$item) {
                        if ($item['id'] == $productId) {
                            $item['quantity'] += $quantity;
                            $found = true;
                            break;
                        }
                    }
                    unset($item); // break reference

                    if (!$found) {
                        $_SESSION['cart'][] = [
                            'id'       => $product['id'],
                            'name'     => $product['name'],
                            'price'    => $product['price'],
                            'image'    => $product['image'],
                            'quantity' => $quantity
                        ];
                    }
                }
            }
            break;

        case 'remove':
            $productId = (int)($_POST['product_id'] ?? 0);
            if ($productId > 0) {
                $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], function ($item) use ($productId) {
                    return $item['id'] != $productId;
                }));
            }
            break;

        case 'update':
            $productId = (int)($_POST['product_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 0);
            foreach ($_SESSION['cart'] as &$item) {
                if ($item['id'] == $productId) {
                    if ($quantity > 0) {
                        $item['quantity'] = $quantity;
                    } else {
                        $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], function ($item) use ($productId) {
                            return $item['id'] != $productId;
                        }));
                    }
                    break;
                }
            }
            unset($item);
            break;
    }
}

// ✅ Redirect back to previous page or cart page
$redirect = $_SERVER['HTTP_REFERER'] ?? 'cart.php';
header("Location: $redirect");
exit;
