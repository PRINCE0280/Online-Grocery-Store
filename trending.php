<?php
// Basic trending products functionality
header('Content-Type: application/json');

require_once 'config.php';

try {
    // Get trending/popular products (example query)
    $stmt = $conn->query("SELECT id, name, price, image FROM products ORDER BY id DESC LIMIT 6");
    $trending_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'products' => $trending_products
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load trending products'
    ]);
}
?>