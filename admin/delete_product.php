<?php
@include '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('location:../auth/login.php');
    exit;
}

// Redirect if no valid product ID provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid product ID.');
}

$product_id = (int)$_GET['id'];

// Fetch product with image and category info
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die('Product not found.');
}

$imagePath = $product['image'];

// ✅ Delete local image only if it's not a shared image or URL
if ($imagePath && !filter_var($imagePath, FILTER_VALIDATE_URL)) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE image = ? AND id != ?");
    $stmt->execute([$imagePath, $product_id]);
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        $localPath = '../uploaded_img/' . basename($imagePath);
        if (file_exists($localPath)) {
            unlink($localPath);
        }
    }
}

// ✅ Delete product from database
$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt->execute([$product_id]);

// Redirect to products page
header('Location: admin_products.php');
exit;
?>
