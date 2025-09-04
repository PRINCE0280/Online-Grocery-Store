<?php
// Load static product image mappings
require_once 'product_images.php';

/**
 * Generates an HTML image element for a product
 * 
 * @param array $product The product array (must include 'id' and 'name')
 * @param string $classes Optional Tailwind or custom classes for styling
 * @return string HTML <img> tag with fallback and error handling
 */
function generateProductImageHTML($product, $classes = 'w-full h-48 object-cover') {
    $imageSrc = getProductImageUrl($product);
    $altText = htmlspecialchars($product['name']);

    // Inline fallback (onerror) if image fails to load
    $fallbackSvg = base64_encode('
        <svg width="200" height="200" xmlns="http://www.w3.org/2000/svg">
            <rect width="200" height="200" fill="#e5e7eb"/>
            <text x="100" y="90" font-family="Arial, sans-serif" font-size="12" fill="#6b7280" text-anchor="middle">
                No Image
            </text>
            <text x="100" y="110" font-family="Arial, sans-serif" font-size="10" fill="#9ca3af" text-anchor="middle">
                Available
            </text>
        </svg>');

    return '<img src="' . htmlspecialchars($imageSrc) . '" 
                 alt="' . $altText . '" 
                 class="' . htmlspecialchars($classes) . '" 
                 onerror="this.onerror=null;this.src=\'data:image/svg+xml;base64,' . $fallbackSvg . '\'">';
}
?>
