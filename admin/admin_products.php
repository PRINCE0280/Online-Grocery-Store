<?php
@include '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_id'])) {
    header('location:../auth/login.php');
    exit;
}

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../product_images.php';

// Handle delete for DB products
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];

    $product = getProductById($delete_id);
    if ($product) {
        $stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
        $stmt->execute([$delete_id]);
        $img = $stmt->fetchColumn();

        if ($img && !filter_var($img, FILTER_VALIDATE_URL)) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE image = ? AND id != ?");
            $stmt->execute([$img, $delete_id]);
            $count = $stmt->fetchColumn();

            if ($count == 0) {
                $filePath = __DIR__ . '/../uploaded_img/' . basename($img);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }

        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$delete_id]);
    }

    header('location:admin_products.php');
    exit;
}

// Fetch all products from DB (newest first)
$stmt = $conn->query("SELECT * FROM products ORDER BY id DESC");
$allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Products - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<?php include 'admin_header.php'; ?>

<div class="container mx-auto py-8 px-4">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-green-700">Manage Products</h1>
        <a href="add_product.php" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 text-sm">+ Add Product</a>
    </div>

    <?php if (count($allProducts) > 0): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($allProducts as $product): ?>
                <?php
                    $imgUrl = getProductImageUrl($product);
                    $finalImgSrc = filter_var($imgUrl, FILTER_VALIDATE_URL) ? $imgUrl : "../" . ltrim($imgUrl, '/');

                    $isSmall = isset($product['size']) && $product['size'] === 'small';
                    $cardHeight = $isSmall ? 'min-h-[300px]' : 'min-h-[460px]';
                    $imageAspect = $isSmall ? 'aspect-[1/1]' : 'aspect-[4/3]';
                ?>
                <div class="bg-white rounded-lg shadow p-4 flex flex-col h-full <?= $cardHeight ?>">
                    <!-- Image -->
                    <div class="w-full <?= $imageAspect ?> mb-3 overflow-hidden rounded relative">
                        <img src="<?= htmlspecialchars($finalImgSrc) ?>"
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             class="absolute inset-0 w-full h-full object-contain" />
                    </div>

                    <!-- Product Info -->
                    <h2 class="text-base font-semibold text-gray-800 mb-1"><?= htmlspecialchars($product['name']) ?></h2>
                    <p class="text-green-600 font-bold mb-1 text-sm">₹<?= htmlspecialchars($product['price']) ?></p>
                    <?php if (isset($product['category_slug'])): ?>
                        <p class="text-xs text-gray-500 mb-2">
                            Category: <span class="text-gray-700 font-medium"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $product['category_slug']))) ?></span>
                        </p>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="flex gap-2 mt-auto text-sm">
                        <a href="edit_product.php?id=<?= $product['id'] ?>"
                           class="flex-1 text-center bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">
                           Edit
                        </a>
                        <a href="admin_products.php?delete=<?= $product['id'] ?>"
                           onclick="return confirm('Delete this product?');"
                           class="flex-1 text-center bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600">
                           Delete
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-center text-gray-600 mt-20">No products available.</p>
    <?php endif; ?>
</div>

</body>
</html>
