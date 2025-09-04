<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'image_handler.php';
$pageTitle = 'Home';
?>

<?php include 'header.php'; ?>

<!-- Hover Style -->
<style>
  .hover-zoom {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }

  .hover-zoom:hover {
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 0.75rem 1.25rem rgba(0, 0, 0, 0.1);
  }

  .object-fit-cover {
    object-fit: cover;
  }

  .card:hover .object-fit-cover {
    transform: scale(1.05);
    transition: transform 0.3s ease;
  }
</style>

<div class="pt-[28px]">

  <!-- Carousel -->
  <div id="carouselExampleAutoplaying" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-inner">
      <!-- Slide 1 -->
      <div class="carousel-item active">
        <img src="images/home-bg.jpg" class="d-block w-100" alt="Slide 1">
        <div class="carousel-caption d-none d-md-block text-start" style="left: 5%; bottom: 20%; max-width: 600px;">
          <span class="text-warning fs-4 fw-semibold">Your One-Stop Grocery Destination</span>
          <h2 class="fw-bold display-5 text-dark">SHOP EVERYDAY ESSENTIALS<br>WITH EASE</h2>
          <p class="text-dark fs-5">
            From fresh dairy to daily staples, snacks, and home care – get everything you need, delivered to your doorstep with quality you can trust.
          </p>
          <a href="categories.php" class="btn btn-success px-4 py-2">Buy Now</a>
        </div>
      </div>

      <!-- Slide 2 -->
        <div class="carousel-item">
  <img src="images/Discount.jpg" class="d-block w-100 object-cover md:h-[500px] h-[300px]" alt="Slide 2">
        <div class="carousel-caption d-none d-md-block" style="bottom: 20%;">
          <a href="categories.php" class="btn btn-success px-4 py-2">Buy Now</a>
        </div>
      </div>

      <!-- Slide 3 -->
        <div class="carousel-item">
  <img src="images/Discount2.jpg" class="d-block w-100 object-cover md:h-[500px] h-[300px]" alt="Slide 3">
        <div class="carousel-caption d-none d-md-block" style="bottom: 20%;">
          <a href="categories.php" class="btn btn-success px-4 py-2">Buy Now</a>
        </div>
      </div>
    </div>

    <!-- Carousel Controls -->
    <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Next</span>
    </button>
  </div>

  <!-- Main Content -->
  <main class="container py-5">
    <!-- Shop by Category -->
    <section class="mb-5">
      <h2 class="text-center fs-2 fw-bold mb-4">Shop by Category</h2>
      <div class="row g-4">
        <?php foreach ($categories as $key => $name): ?>
          <div class="col-6 col-md-3">
            <a href="categories.php?category=<?= $key; ?>" class="text-decoration-none">
              <div class="card text-center border-0 shadow-sm h-100 hover-zoom">
                <div class="card-body">
                  <?php
                    $icons = [
                      'staples' => 'fas fa-seedling',
                      'snacks' => 'fas fa-cookie-bite',
                      'packaged' => 'fas fa-box',
                      'personal' => 'fas fa-user-check',
                      'household' => 'fas fa-home',
                      'dairy' => 'fas fa-glass-whiskey',
                      'home' => 'fas fa-utensils',
                      'dry_fruits' => 'fas fa-apple-alt'
                    ];
                  ?>
                  <div class="text-success fs-1 mb-3">
                    <i class="<?= $icons[$key] ?? 'fas fa-box-open'; ?>"></i>
                  </div>
                  <h5 class="card-title text-dark"><?= htmlspecialchars($name); ?></h5>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Featured Products -->
 <section class="container mx-auto px-4 py-12">
  <h2 class="text-2xl font-bold text-center mb-8">Featured Products</h2>

  <?php
    // Load categories (slug => name)
    $categories = [];
    $stmt = $conn->query("SELECT slug, name FROM categories ORDER BY name ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[$row['slug']] = $row['name'];
    }

    // Fetch DB featured products with category_slug
    $featuredStmt = $conn->prepare("SELECT p.*, c.slug AS category_slug FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC LIMIT 4");
    $featuredStmt->execute();
    $dbFeatured = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fallback to static products if needed
    $totalNeeded = 4;
    $staticFallback = [];

    if (count($dbFeatured) < $totalNeeded && isset($products) && is_array($products)) {
        $remaining = array_filter($products, function ($p) use ($dbFeatured) {
            foreach ($dbFeatured as $db) {
                if ($p['id'] == $db['id']) return false;
            }
            return true;
        });

        // Set category_slug manually for static products
        foreach ($remaining as &$p) {
            $p['category_slug'] = $p['category'] ?? null;
        }

        $staticFallback = array_slice($remaining, 0, $totalNeeded - count($dbFeatured));
    }

    // Final product list
    $featuredProducts = array_merge($dbFeatured, $staticFallback);
  ?>

  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
    <?php foreach ($featuredProducts as $product): ?>
      <?php
        $isSmall = isset($product['size']) && $product['size'] === 'small';
$cardClass = $isSmall ? 'min-h-[220px]' : 'min-h-[300px]';
        $imageAspect = $isSmall ? 'aspect-[1/1]' : 'aspect-[4/3]';
      ?>
      <div class="bg-white rounded-lg shadow p-4 flex flex-col h-full <?= $cardClass ?> hover:shadow-lg transition duration-300 group">
        <a href="product.php?id=<?= $product['id'] ?>" class="block">
          <div class="w-full <?= $imageAspect ?> mb-3 overflow-hidden rounded relative">
            <?= function_exists('generateProductImageHTML')
              ? generateProductImageHTML($product, 'absolute inset-0 w-full h-full object-contain group-hover:scale-105 transition duration-300')
              : '<img src="' . htmlspecialchars($product['image'] ?? 'https://via.placeholder.com/300x300?text=No+Image') . '" class="absolute inset-0 w-full h-full object-contain group-hover:scale-105 transition duration-300" alt="Product Image">'; ?>
          </div>

          <h3 class="text-base font-semibold mb-1 text-gray-900"><?= htmlspecialchars($product['name']) ?></h3>

          <!-- ✅ Category name (if exists) -->
          <div class="text-gray-500 text-sm mb-1">
            <?= isset($product['category_slug'], $categories[$product['category_slug']])
                ? htmlspecialchars($categories[$product['category_slug']])
                : 'Uncategorized'; ?>
          </div>

          <div class="text-green-700 font-bold text-sm mb-2">₹<?= htmlspecialchars($product['price']) ?></div>

          <?php if (!empty($product['description'])): ?>
            <div class="text-xs text-gray-500 mb-2 line-clamp-2">
              <?= htmlspecialchars($product['description']) ?>
            </div>
          <?php endif; ?>
        </a>

        <form method="POST" action="cart_handler.php" class="mt-auto">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="product_id" value="<?= $product['id']; ?>">
          <button type="submit" class="w-full bg-green-600 text-white py-2 rounded hover:bg-green-700 transition">
            Add to Cart
          </button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="text-center mt-8">
    <a href="categories.php" class="inline-block bg-gray-100 text-green-700 border border-green-600 px-6 py-2 rounded hover:bg-green-600 hover:text-white transition font-medium">
      View More...
    </a>
  </div>
</section>

  </main>

</div>

<?php include 'footer.php'; ?>
