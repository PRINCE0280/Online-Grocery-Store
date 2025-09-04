<?php
function getProductImageUrl($product) {
    global $productImages;

    $productId = is_array($product) ? ($product['id'] ?? 0) : intval($product);

    // ✅ 1. External image URL from DB
    if (is_array($product) && !empty($product['image']) && filter_var($product['image'], FILTER_VALIDATE_URL)) {
        return $product['image'];
    }

    // ✅ 2. Uploaded image from DB
    if (is_array($product) && !empty($product['image'])) {
        $localPath = __DIR__ . "/uploaded_img/{$product['image']}";
        if (file_exists($localPath)) {
            return "uploaded_img/{$product['image']}";
        }
    }
    // ✅ 4. Fallback placeholder
    $colors = ['4ade80', 'dc2626', 'f59e0b', '3b82f6', '8b5cf6', '06b6d4', '64748b', 'd97706'];
    $color = $colors[$productId % count($colors)];
    return "https://via.placeholder.com/200x200/{$color}/ffffff?text=Product+{$productId}";
}
?>
    