<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'image_handler.php';

// Fetch all categories
$categories = [];
$stmt = $conn->query("SELECT slug, name FROM categories ORDER BY name ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $categories[$row['slug']] = $row['name'];
}

// Get URL parameters
$selectedCategory = $_GET['category'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');

// Build SQL query with optional filters
$sql = "SELECT DISTINCT p.id, p.name, p.price, p.category_id, p.quantity, p.unit, p.image, p.description,
        c.slug AS category_slug
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id";

$conditions = [];
$params = [];

if (!empty($searchQuery)) {
    $conditions[] = "p.name LIKE :search";
    $params[':search'] = '%' . $searchQuery . '%';
}

if (!empty($selectedCategory)) {
    $conditions[] = "c.slug = :category";
    $params[':category'] = $selectedCategory;
}

if ($conditions) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$displayProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page title
$pageTitle = 'All Products';
if ($selectedCategory && isset($categories[$selectedCategory])) {
    $pageTitle = $categories[$selectedCategory];
}
?>

<?php include 'header.php'; ?>

<main class="container mx-auto px-4 py-24 md:py-20">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">
            <?= htmlspecialchars($pageTitle); ?>
        </h1>

        <!-- Category Filter -->
        <div class="flex flex-wrap gap-2 mb-4">
            <a href="categories.php" class="px-4 py-2 rounded-lg <?= !$selectedCategory ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                All Categories
            </a>
            <?php foreach ($categories as $slug => $name): ?>
                <a href="categories.php?category=<?= urlencode($slug); ?>" class="px-4 py-2 rounded-lg <?= $selectedCategory === $slug ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                    <?= htmlspecialchars($name); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Search Info -->
        <?php if (!empty($searchQuery)): ?>
            <div class="inline-flex items-center bg-gray-200 rounded-full px-3 py-1.5 mb-6 text-xs md:text-sm w-[35%] shadow-sm border border-gray-300">
                <span class="text-gray-700 whitespace-nowrap text-xs md:text-sm">
                  Showing results for
                  <span class="text-gray-900 font-semibold">"<?= htmlspecialchars($searchQuery) ?>"</span>
                </span>
                <a href="categories.php" class="ml-auto text-gray-500 hover:text-gray-700 text-sm md:text-base" title="Clear search">
                  <i class="fas fa-times text-xs md:text-sm"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Products Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
        <?php if (count($displayProducts) > 0): ?>
            <?php foreach ($displayProducts as $product): ?>
                <div class="bg-white rounded-lg shadow p-4 flex flex-col h-full min-h-[320px] hover:shadow-lg transition duration-300 group">
                    <a href="product.php?id=<?= $product['id'] ?>" class="block">
                        <div class="w-full h-48 mb-3 overflow-hidden rounded relative bg-white-50 flex items-center justify-center">
                            <?= function_exists('generateProductImageHTML') 
                                ? generateProductImageHTML($product, 'max-w-full max-h-full object-contain group-hover:scale-105 transition duration-300')
                                : '<img src="' . htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/300x300?text=No+Image') . '" class="max-w-full max-h-full object-contain group-hover:scale-105 transition duration-300" alt="Product Image">'; ?>
                        </div>

                        <h2 class="text-lg font-semibold mb-1 text-gray-900"><?= htmlspecialchars($product['name']) ?></h2>
                        <div class="text-gray-600 text-sm mb-2">
                            <?= isset($product['category_slug'], $categories[$product['category_slug']])
                                ? htmlspecialchars($categories[$product['category_slug']])
                                : 'Uncategorized'; ?>
                        </div>
                        <div class="font-bold text-green-700 mb-2">₹<?= htmlspecialchars($product['price']) ?></div>

                        <?php if (!empty($product['description'])): ?>
                            <div class="text-xs text-gray-500 mb-2 line-clamp-2">
                                <?= htmlspecialchars($product['description']) ?>
                            </div>
                        <?php endif; ?>
                    </a>

                    <!-- Add to Cart -->
                    <form method="post" action="cart_handler.php" class="mt-auto">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <input type="hidden" name="action" value="add">
                        <button type="submit" class="w-full bg-green-600 text-white py-2 rounded hover:bg-green-700 transition">
                            Add to Cart
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full text-center text-gray-500">No products found.</div>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>
