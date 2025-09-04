<?php
@include '../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('location:../auth/login.php');
    exit;
}

// ✅ Dynamically fetch categories from DB
$categories = [];
$categoryMap = [];
$stmt = $conn->query("SELECT id, slug, name FROM categories ORDER BY name ASC");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $cat) {
    $categories[$cat['slug']] = $cat['name'];
    $categoryMap[$cat['slug']] = $cat['id'];
}

$success = '';
$error = '';
$productAdded = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $category_slug = $_POST['category_slug'] ?? '';
    $quantity = intval($_POST['quantity']);
    $unit = trim($_POST['unit']);
    $description = trim($_POST['description']);
    $image_url = trim($_POST['image_url'] ?? '');

    // ✅ Validate inputs
    if (empty($name)) {
        $error = "Product name is required.";
    } elseif ($price < 0) {
        $error = "Price cannot be negative.";
    } elseif ($quantity < 0) {
        $error = "Quantity cannot be negative.";
    } elseif (empty($unit)) {
        $error = "Unit is required.";
    } elseif (!isset($categoryMap[$category_slug])) {
        $error = "Selected category does not exist in the database.";
    }

    $imageName = '';

    // ✅ Handle file upload
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
    }

    // ✅ If no upload, use image URL
    if (empty($imageName) && !empty($image_url)) {
        if (filter_var($image_url, FILTER_VALIDATE_URL)) {
            $imageName = $image_url;
        } else {
            $error = "Invalid image URL.";
        }
    }

    // ✅ Save to DB
    if (empty($error)) {
        try {
            $category_id = $categoryMap[$category_slug];
            $stmt = $conn->prepare("INSERT INTO products (name, price, category_id, quantity, unit, image, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $price, $category_id, $quantity, $unit, $imageName, $description]);

            $success = "Product added successfully.";
            $productAdded = true;
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
    <title>Add Product</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<?php include 'admin_header.php'; ?>

<div class="max-w-xl mx-auto mt-12 p-6 bg-white rounded shadow">
    <h1 class="text-2xl font-bold text-green-700 mb-4">Add Product</h1>

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
            <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block mb-1 font-semibold">Price (₹)</label>
            <input type="number" name="price" min="0" step="0.01"
                   value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded px-3 py-2" required>
        </div>

        <div>
            <label class="block mb-1 font-semibold">Category</label>
            <select name="category_slug" class="w-full border border-gray-300 rounded px-3 py-2" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $slug => $label): ?>
                    <option value="<?= $slug ?>" <?= ($_POST['category_slug'] ?? '') === $slug ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block mb-1 font-semibold">Quantity</label>
                <input type="number" name="quantity" min="0"
                       value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded px-3 py-2" required>
            </div>

            <div>
                <label class="block mb-1 font-semibold">Unit</label>
                <input type="text" name="unit" value="<?= htmlspecialchars($_POST['unit'] ?? '') ?>"
                       class="w-full border border-gray-300 rounded px-3 py-2">
            </div>
        </div>

        <div>
            <label class="block mb-1 font-semibold">Upload Product Image</label>
            <input type="file" name="image" accept="image/*"
                   class="w-full border border-gray-300 rounded px-3 py-2 mb-2">

            <label class="block mb-1 font-semibold">OR Enter Image URL</label>
            <input type="url" name="image_url" value="<?= htmlspecialchars($_POST['image_url'] ?? '') ?>"
                   placeholder="https://example.com/image.jpg"
                   class="w-full border border-gray-300 rounded px-3 py-2">
            <img id="urlPreview" class="mt-2 rounded shadow max-h-40 <?= empty($_POST['image_url']) ? 'hidden' : '' ?>"
                 src="<?= htmlspecialchars($_POST['image_url'] ?? '') ?>" alt="Preview">
        </div>

        <div>
            <label class="block mb-1 font-semibold">Description</label>
            <textarea name="description" rows="3"
                      class="w-full border border-gray-300 rounded px-3 py-2"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="flex justify-between mt-6">
            <button type="submit"
                    class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition">
                Add Product
            </button>

            <?php if ($productAdded): ?>
                <a href="admin_products.php"
                   class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700 transition text-center">
                    View Product
                </a>
            <?php else: ?>
                <a href="admin_products.php"
                   class="text-blue-600 hover:underline px-6 py-2 transition">
                    Cancel
                </a>
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
