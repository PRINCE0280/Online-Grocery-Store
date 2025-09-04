<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'image_handler.php';
require_once 'product_images.php';

// ✅ Load category slug => name map globally
$categories = [];
$stmt = $conn->query("SELECT slug, name FROM categories ORDER BY name ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $categories[$row['slug']] = $row['name'];
}

// ✅ Get product ID
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;

// ✅ Load product from database
$stmt = $conn->prepare("SELECT p.*, c.slug AS category_slug 
                        FROM products p 
                        JOIN categories c ON p.category_id = c.id 
                        WHERE p.id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ Redirect if not found
if (!$product) {
    header('Location: categories.php');
    exit;
}

// ✅ Ensure category_slug exists
if (!isset($product['category_slug']) && isset($product['category'])) {
    $product['category_slug'] = $product['category'];
}

// ✅ Load all products from DB
$stmt = $conn->query("SELECT p.*, c.slug AS category_slug 
                      FROM products p 
                      JOIN categories c ON p.category_id = c.id 
                      ORDER BY p.id DESC");
$allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Page title
$pageTitle = $product['name'];
?>

<?php include 'header.php'; ?>

<main class="container mx-auto px-4 py-8">
    <nav class="mb-6">
        <a href="categories.php" class="text-green-600 hover:text-green-700">
            <i class="fas fa-arrow-left mr-2"></i>Back to Categories
        </a>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Product Image -->
        <?php
        $isSmall = isset($product['size']) && $product['size'] === 'small';
        $imageAspect = $isSmall ? 'aspect-[1/1]' : 'aspect-[4/3]';
        ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="relative w-full <?= $imageAspect ?> overflow-hidden rounded-lg">
                <?= generateProductImageHTML($product, 'absolute inset-0 w-full h-full object-contain'); ?>
            </div>
        </div>

        <!-- Product Details -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="mb-4">
                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                    <?= $categories[$product['category_slug']] ?? 'Uncategorized'; ?>
                </span>
            </div>

            <h1 class="text-3xl font-bold text-gray-800 mb-4"><?= htmlspecialchars($product['name']); ?></h1>

            <p class="text-4xl font-bold text-green-600 mb-6">
                <?= formatCurrency($product['price']); ?>
            </p>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Product Description</h3>
                <p class="text-gray-600">
                    <?= !empty($product['description']) 
                        ? htmlspecialchars($product['description']) 
                        : 'High-quality ' . htmlspecialchars(strtolower($product['name'])) . ' available for immediate delivery. Fresh and authentic products sourced directly from trusted suppliers.'; ?>
                </p>
            </div>

            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Features</h3>
                <ul class="text-gray-600 space-y-1">
                    <li><i class="fas fa-check text-green-600 mr-2"></i>Fresh and high quality</li>
                    <li><i class="fas fa-check text-green-600 mr-2"></i>Fast home delivery</li>
                    <li><i class="fas fa-check text-green-600 mr-2"></i>Best price guarantee</li>
                    <li><i class="fas fa-check text-green-600 mr-2"></i>Easy returns</li>
                </ul>
            </div>

            <form method="POST" action="cart_handler.php" class="space-y-4">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= $product['id']; ?>">

                <div class="flex items-center space-x-4">
                    <label for="quantity" class="text-gray-700 font-medium">Quantity:</label>
                    <select name="quantity" id="quantity" class="border border-gray-300 rounded-lg px-3 py-2">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?= $i; ?>"><?= $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <button type="submit"
                        class="w-full bg-green-600 text-white py-3 px-6 rounded-lg hover:bg-green-700 transition text-lg font-semibold">
                    <i class="fas fa-shopping-cart mr-2"></i>Add to Cart
                </button>
            </form>
        </div>
    </div>

    <!-- ✅ Related Products -->
    <section class="mt-12">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Related Products</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php
            $relatedProducts = array_filter($allProducts, function ($p) use ($product) {
                $pCategorySlug = $p['category_slug'] ?? $p['category'] ?? null;
                $currentCategorySlug = $product['category_slug'] ?? $product['category'] ?? null;
                return $pCategorySlug === $currentCategorySlug && $p['id'] != $product['id'];
            });

            $relatedProducts = array_slice(array_values($relatedProducts), 0, 4);

            foreach ($relatedProducts as $relatedProduct):
                $isSmall = isset($relatedProduct['size']) && $relatedProduct['size'] === 'small';
                $cardClass = $isSmall ? 'min-h-[300px]' : 'min-h-[460px]';
                $imageAspect = $isSmall ? 'aspect-[1/1]' : 'aspect-[4/3]';
            ?>
            <div class="bg-white rounded-lg shadow p-4 flex flex-col h-full <?= $cardClass ?> hover:shadow-lg transition duration-300 group">
                <a href="product.php?id=<?= $relatedProduct['id'] ?>" class="block">
                    <div class="w-full <?= $imageAspect ?> mb-3 overflow-hidden rounded relative">
                        <?= function_exists('generateProductImageHTML')
                            ? generateProductImageHTML($relatedProduct, 'absolute inset-0 w-full h-full object-contain group-hover:scale-105 transition duration-300')
                            : '<img src="' . htmlspecialchars($relatedProduct['image'] ?? 'https://via.placeholder.com/300x300?text=No+Image') . '" class="absolute inset-0 w-full h-full object-contain group-hover:scale-105 transition duration-300" alt="Product Image">'; ?>
                    </div>

                    <h3 class="text-base font-semibold text-gray-800 mb-1"><?= htmlspecialchars($relatedProduct['name']); ?></h3>
                    <p class="text-green-600 font-bold mb-2 text-sm">₹<?= htmlspecialchars($relatedProduct['price']); ?></p>
                </a>

                <!-- Add to Cart -->
                <form method="post" action="cart_handler.php" class="mt-auto">
                    <input type="hidden" name="product_id" value="<?= $relatedProduct['id'] ?>">
                    <input type="hidden" name="action" value="add">
                    <button type="submit" class="w-full bg-green-600 text-white py-2 rounded hover:bg-green-700 transition">
                        <i class="fas fa-shopping-cart me-1"></i> Add to Cart
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
