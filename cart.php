<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'image_handler.php';
$pageTitle = 'Shopping Cart';

// Clear cart if user is not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['cart'] = []; // Forcefully empty cart
    header("Location: auth/login.php?redirect=cart.php");
    exit;
}
?>

<?php include 'header.php'; ?>

<main class="container mx-auto px-4 pt-10 pb-20 md:pt-12">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">Shopping Cart</h1>
    
    <?php if (empty($_SESSION['cart'])): ?>
        <div class="text-center py-12">
            <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
            <h2 class="text-2xl font-semibold text-gray-600 mb-4">Your cart is empty</h2>
            <p class="text-gray-500 mb-6">Add some products to get started!</p>
            <a href="categories.php" 
               class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
                Continue Shopping
            </a>
        </div>
    <?php else: ?>
        <?php
            $subtotal = getCartTotal(); // Total of all items in the cart
            $delivery_fee = ($subtotal >= 499) ? 0 : 50; // Free delivery if subtotal ≥ ₹499
            $total = $subtotal + $delivery_fee; // Final total
        ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b">
                        <h2 class="text-xl font-semibold text-gray-800">Cart Items</h2>
                    </div>

                    <div class="divide-y">
                        <?php foreach ($_SESSION['cart'] as $product): ?>
                            <div class="p-6 flex items-center space-x-4">
                                <?php echo generateProductImageHTML($product, 'w-16 h-16 object-cover rounded-lg'); ?>

                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($product['name'] ?? 'Unknown Product'); ?></h3>
                                    <p class="text-green-600 font-bold">
                                        <?= formatCurrency($product['price'] ?? 0); ?>
                                    </p>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <form method="POST" action="cart_handler.php" class="inline">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="product_id" value="<?= $product['id'] ?? 0; ?>">
                                        <select name="quantity" onchange="this.form.submit()" 
                                                class="border border-gray-300 rounded px-2 py-1">
                                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                                <option value="<?= $i; ?>" <?= $i === ($product['quantity'] ?? 1) ? 'selected' : ''; ?>>
                                                    <?= $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </form>
                                </div>

                                <div class="text-right">
                                    <p class="font-bold text-gray-800">
                                        <?= formatCurrency(($product['price'] ?? 0) * ($product['quantity'] ?? 1)); ?>
                                    </p>
                                    <form method="POST" action="cart_handler.php" class="inline">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="product_id" value="<?= $product['id'] ?? 0; ?>">
                                        <button type="submit" 
                                                class="text-red-600 hover:text-red-700 text-sm mt-1">
                                            <i class="fas fa-trash mr-1"></i>Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 sticky top-4">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Summary</h2>

                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-semibold"><?= formatCurrency($subtotal); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Delivery Fee</span>
                            <span class="font-semibold"><?= $delivery_fee === 0 ? 'Free' : formatCurrency($delivery_fee); ?></span>
                        </div>
                        <div class="border-t pt-3">
                            <div class="flex justify-between text-lg font-bold">
                                <span>Total</span>
                                <span class="text-green-600">
                                    <?= formatCurrency($total); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <a href="checkout.php" 
                           class="block w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 transition text-center font-semibold">
                            Proceed to Checkout
                        </a>
                        <a href="categories.php" 
                           class="block w-full bg-gray-200 text-gray-700 py-3 px-4 rounded-lg hover:bg-gray-300 transition text-center">
                            Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
