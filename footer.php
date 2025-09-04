<footer class="bg-gray-800 text-white mt-12">
    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">

            <!-- Brand Info -->
            <div>
                <h3 class="text-xl font-bold mb-4">
                    <i class="fas fa-shopping-basket mr-2"></i>FreshMart
                </h3>
                <p class="text-gray-300">Your trusted online grocery store delivering fresh products to your doorstep.</p>
            </div>

            <!-- Quick Links -->
            <div>
                <h4 class="text-lg font-semibold mb-4">Quick Links</h4>
                <ul class="space-y-2">
                    <li><a href="/grocery-web-app/index.php" class="text-gray-300 hover:text-white transition"><i class="fas fa-angle-right mr-1"></i>Home</a></li>
                    <li><a href="/grocery-web-app/categories.php" class="text-gray-300 hover:text-white transition"><i class="fas fa-angle-right mr-1"></i>Categories</a></li>
                    <li><a href="/grocery-web-app/about.php" class="text-gray-300 hover:text-white transition"><i class="fas fa-angle-right mr-1"></i>About Us</a></li>
                    <li><a href="/grocery-web-app/contact.php" class="text-gray-300 hover:text-white transition"><i class="fas fa-angle-right mr-1"></i>Contact Us</a></li>
                </ul>
            </div>

            <!-- Extra Links (Conditional Login/Register) -->
            <div>
                <h4 class="text-lg font-semibold mb-4">Extra Links</h4>
                <ul class="space-y-2">
                    <li><a href="/grocery-web-app/wishlist.php" class="text-gray-300 hover:text-white transition"><i class="fas fa-angle-right mr-1"></i>Wishlist</a></li>
                    <li><a href="/grocery-web-app/orders.php" class="text-gray-300 hover:text-white transition"><i class="fas fa-angle-right mr-1"></i>Orders</a></li>
                    <li><a href="/grocery-web-app/cart.php" class="text-gray-300 hover:text-white transition"><i class="fas fa-angle-right mr-1"></i>Cart</a></li>


                    <?php
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])):
                    ?>
                    <li><a href="/grocery-web-app/auth/login.php" class="text-gray-300 hover:text-white transition"><i class="fas fa-angle-right mr-1"></i>Login</a></li>
                    <li><a href="/grocery-web-app/auth/register.php" class="text-gray-300 hover:text-white transition"><i class="fas fa-angle-right mr-1"></i>Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Contact Info & Social -->
            <div>
                <h4 class="text-lg font-semibold mb-4">Contact Info</h4>
                <ul class="space-y-2 text-gray-300">
                    <li><i class="fas fa-phone mr-2"></i>+91 93047 67700</li>
                    <li><i class="fas fa-phone mr-2"></i>+91 88040 34055</li>
                    <li><i class="fas fa-envelope mr-2"></i>princesingh68912@gmail.com</li>
                    <li><i class="fas fa-map-marker-alt mr-2"></i>Rajkot, Gujarat - 360003</li>
                </ul>
                <div class="flex space-x-4 mt-4">
                    <a href="#" class="text-gray-300 hover:text-white text-lg"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-gray-300 hover:text-white text-lg"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="text-gray-300 hover:text-white text-lg"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-gray-300 hover:text-white text-lg"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-300">
            <p>&copy; <?= date('Y'); ?> <span class="font-semibold">FreshMart Grocery</span>. All rights reserved.</p>
        </div>
    </div>
</footer>
