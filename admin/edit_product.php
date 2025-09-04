<?php
@include '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('location:../auth/login.php');
    exit;
}

$product_id = $_GET['id'] ?? null;
if (!$product_id || !is_numeric($product_id)) {
    die('Invalid product ID.');
}

// Category slug-to-name mapping
$categories = [
    'staples'     => 'Staples',
    'snacks'      => 'Snacks & Beverages',
    'packaged'    => 'Packaged Food',
    'personal'    => 'Personal & Baby Care',
    'household'   => 'Household Care',
    'dairy'       => 'Dairy & Eggs',
    'home'        => 'Home & Kitchen',
    'dry_fruits'  => 'Dry Fruits'
];

// Fetch category slug-to-ID mapping from DB
$categoryMap = [];
$reverseCategoryMap = [];
$stmt = $conn->query("SELECT id, slug FROM categories");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $cat) {
    $categoryMap[$cat['slug']] = $cat['id'];
    $reverseCategoryMap[$cat['id']] = $cat['slug'];
}

$success = '';
$error = '';
$productUpdated = false;

// Fetch product
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die('Product not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $category_slug = $_POST['category_slug'] ?? '';
    $quantity = intval($_POST['quantity']);
    $unit = trim($_POST['unit']);
    $description = trim($_POST['description']);
    $image_url = trim($_POST['image_url'] ?? '');
    $imageName = $product['image']; // keep old image by default

    // Basic validation
    if (empty($name)) {
        $error = "Product name is required.";
    } elseif ($price < 0) {
        $error = "Price cannot be negative.";
    } elseif ($quantity < 0) {
        $error = "Quantity cannot be negative.";
    } elseif (empty($unit)) {
        $error = "Unit is required.";
    } elseif (!array_key_exists($category_slug, $categories) || !isset($categoryMap[$category_slug])) {
        $error = "Invalid or missing category.";
    }

    // --- Image Handling ---
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = $_FILES['image']['type'];

        if (!in_array($fileType, $allowedTypes)) {
            $error = "Only JPG, PNG, and GIF images are allowed.";
        } else {
            $imageName = time() . '_' . basename($_FILES['image']['name']);
            $uploadPath = '../uploaded_img/' . $imageName;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                $error = "Failed to upload image.";
            }
        }
    } elseif (!empty($image_url)) {
        if (filter_var($image_url, FILTER_VALIDATE_URL)) {
            $imageName = $image_url;
        } else {
            $error = "Invalid image URL.";
        }
    }

    // --- Save updated data ---
    if (empty($error)) {
        try {
            $category_id = $categoryMap[$category_slug];
            $stmt = $conn->prepare("UPDATE products SET name=?, price=?, category_id=?, quantity=?, unit=?, image=?, description=? WHERE id=?");
            $stmt->execute([$name, $price, $category_id, $quantity, $unit, $imageName, $description, $product_id]);

            $success = "Product updated successfully.";
            $productUpdated = true;

            // Refresh product data
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<?php include 'admin_header.php'; ?>

<div class="w-full max-w-xl h-fit min-h-[300px] mx-auto mt-4 p-6 bg-white rounded shadow overflow-auto">
    <h1 class="text-2xl font-bold text-blue-700 mb-4">Edit Product</h1>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php elseif ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-4">
        <div>
            <label class="block mb-1 font-semibold">Product Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>"
                   class="w-full border border-gray-300 rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block mb-1 font-semibold">Price (₹)</label>
            <input type="number" name="price" min="0" step="0.01"
                   value="<?= htmlspecialchars($product['price']) ?>"
                   class="w-full border border-gray-300 rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block mb-1 font-semibold">Category</label>
            <select name="category_slug" class="w-full border border-gray-300 rounded px-3 py-2" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $slug => $label): ?>
                    <option value="<?= $slug ?>" <?= $reverseCategoryMap[$product['category_id']] === $slug ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block mb-1 font-semibold">Quantity</label>
                <input type="number" name="quantity" min="0"
                       value="<?= htmlspecialchars($product['quantity']) ?>"
                       class="w-full border border-gray-300 rounded px-3 py-2" required>
            </div>

            <div>
                <label class="block mb-1 font-semibold">Unit</label>
                <input type="text" name="unit" value="<?= htmlspecialchars($product['unit']) ?>"
                       class="w-full border border-gray-300 rounded px-3 py-2">
            </div>
        </div>

        <div>
            <label class="block mb-1 font-semibold">Upload New Image</label>
            <input type="file" name="image" accept="image/*"
                   class="w-full border border-gray-300 rounded px-3 py-2 mb-2">

            <label class="block mb-1 font-semibold">OR Enter Image URL</label>
            <input type="url" name="image_url" value="<?= filter_var($product['image'], FILTER_VALIDATE_URL) ? htmlspecialchars($product['image']) : '' ?>"
                   placeholder="https://example.com/image.jpg"
                   class="w-full border border-gray-300 rounded px-3 py-2">
            <img id="urlPreview" class="mt-2 rounded shadow max-h-40 <?= filter_var($product['image'], FILTER_VALIDATE_URL) ? '' : 'hidden' ?>"
                 src="<?= filter_var($product['image'], FILTER_VALIDATE_URL) ? htmlspecialchars($product['image']) : '' ?>" alt="Preview">
        </div>

        <div>
            <label class="block mb-1 font-semibold">Description</label>
            <textarea name="description" rows="3"
                      class="w-full border border-gray-300 rounded px-3 py-2"><?= htmlspecialchars($product['description']) ?></textarea>
        </div>

        <div class="flex justify-between mt-6">
            <button type="submit"
                    class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition">
                Update Product
            </button>

            <?php if ($productUpdated): ?>
                <a href="admin_products.php"
                   class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 transition text-center">
                    View Product
                </a>
            <?php else: ?>
                <a href="admin_products.php" class="text-blue-600 hover:underline px-6 py-2">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
    const imageUrlInput = document.querySelector('input[name="image_url"]');
    const preview = document.getElementById('urlPreview');

    imageUrlInput.addEventListener('input', () => {
        const url = imageUrlInput.value.trim();
        if (url.match(/\.(jpeg|jpg|gif|png)$/i)) {
            preview.src = url;
            preview.classList.remove('hidden');
        } else {
            preview.classList.add('hidden');
            preview.src = '';
        }
    });
</script>

</body>
</html>
