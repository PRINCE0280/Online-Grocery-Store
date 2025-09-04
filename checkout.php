<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'image_handler.php';
require_once 'mail_helper.php';
require_once 'notification_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header('Location: auth/login.php?redirect=checkout');
    exit;
}

if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$pageTitle = 'Checkout';
$errors = [];

// Calculate subtotal
$subtotal = getCartTotal();
$delivery_fee = ($subtotal >= 499) ? 0 : 50;
$express_fee = 0;
$total = $subtotal + $delivery_fee;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $delivery_option = $_POST['delivery_option'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';

    // Validation
    if (empty($name)) $errors[] = 'Name is required';
    if (empty($mobile)) $errors[] = 'Mobile number is required';
    if (empty($address)) $errors[] = 'Address is required';
    if (empty($pincode)) $errors[] = 'Pin code is required';
    if (empty($delivery_option)) $errors[] = 'Delivery option is required';
    if (empty($payment_method)) $errors[] = 'Payment method is required';
    if (!preg_match('/^[0-9]{10}$/', $mobile)) $errors[] = 'Mobile number must be 10 digits';
    if (!preg_match('/^[0-9]{6}$/', $pincode)) $errors[] = 'Pin code must be 6 digits';

    // Add express fee if selected
    if ($delivery_option === 'express') {
        $express_fee = 50;
    }

    // Update total after delivery choice
    $total = $subtotal + $delivery_fee + $express_fee;

    if (empty($errors)) {
        if ($payment_method === 'online') {
            // Store form data and redirect to payment processing
            $_SESSION['pending_order'] = [
                'name' => $name,
                'mobile' => $mobile,
                'address' => $address,
                'pincode' => $pincode,
                'delivery_option' => $delivery_option,
                'payment_method' => $payment_method,
                'subtotal' => $subtotal,
                'delivery_fee' => $delivery_fee + $express_fee,
                'total' => $total,
                'order_id' => generateOrderId(),
                'total_amount' => $total * 100
            ];
            header('Location: payment.php');
            exit;
        } else {
            // Process COD order directly (existing logic)
            try {
                $conn->beginTransaction();
                $orderId = generateOrderId();
                $userId = $_SESSION['user_id'];

                $stmt = $conn->prepare("INSERT INTO orders 
                    (order_id, user_id, name, mobile, address, pincode, delivery_option, payment_method, subtotal, delivery_fee, total, order_date, payment_status, order_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $orderId,
                    $userId,
                    $name,
                    $mobile,
                    $address,
                    $pincode,
                    $delivery_option,
                    $payment_method,
                    $subtotal,
                    $delivery_fee + $express_fee,
                    $total,
                    date('Y-m-d H:i:s'),
                    'pending',
                    'pending'  // Set order status to pending for COD orders
                ]);

                foreach ($_SESSION['cart'] as $item) {
                    $stmt = $conn->prepare("INSERT INTO order_items 
                        (order_id, product_id, name, quantity, price)
                        VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $orderId,
                        $item['id'],
                        $item['name'],
                        $item['quantity'],
                        $item['price']
                    ]);
                }

                $conn->commit();

                // Get user email for notifications
                $userEmail = getUserEmail($_SESSION['user_id']);

                $_SESSION['order_data'] = [
                    'order_id' => $orderId,
                    'name' => $name,
                    'mobile' => $mobile,
                    'address' => $address,
                    'pincode' => $pincode,
                    'delivery_option' => $delivery_option,
                    'payment_method' => $payment_method,
                    'items' => $_SESSION['cart'],
                    'subtotal' => $subtotal,
                    'delivery_fee' => $delivery_fee + $express_fee,
                    'total' => $total,
                    'order_date' => date('Y-m-d H:i:s'),
                    'status' => 'pending'
                ];

                // Send email notifications
                if ($userEmail) {
                    // Send confirmation email to customer
                    $emailSent = sendOrderConfirmationEmail($_SESSION['order_data'], $userEmail);
                    
                    // Send notification email to admin
                    sendAdminOrderNotification($_SESSION['order_data'], $userEmail);
                    
                    if (!$emailSent) {
                        error_log("Failed to send order confirmation email for order: " . $orderId);
                    }
                } else {
                    error_log("Could not retrieve user email for order: " . $orderId);
                }

                // Create in-app notifications
                createOrderNotification($orderId, $_SESSION['order_data'], $_SESSION['user_id']);

                unset($_SESSION['cart']);
                header('Location: order_success.php');
                exit;

            } catch (Exception $e) {
                $conn->rollBack();
                $errors[] = "Failed to place order. Please try again.";
            }
        }
    }
}
?>

<?php include 'header.php'; ?>

<main class="container mx-auto px-4 py-24 md:py-20">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">Checkout</h1>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?php if ($_GET['error'] === 'payment_failed'): ?>
                Payment failed or was cancelled. Please try again.
            <?php elseif ($_GET['error'] === 'order_failed'): ?>
                Failed to process your order. Please try again.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Delivery Information</h2>
            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mobile Number *</label>
                    <input type="tel" name="mobile" required pattern="[0-9]{10}" value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-green-500 focus:border-green-500">
                </div>

                <!-- 📍 Location Button -->
                <div>
                    <button type="button" onclick="fetchCurrentLocation()" class="text-sm text-blue-600 hover:underline mb-1">
                        📍 Use My Current Location
                    </button>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Delivery Address *</label>
                    <textarea id="autoAddress" name="address" required rows="3"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-green-500 focus:border-green-500"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pin Code *</label>
                    <input id="autoPincode" type="text" name="pincode" required pattern="[0-9]{6}"
                        value="<?= htmlspecialchars($_POST['pincode'] ?? '') ?>"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-green-500 focus:border-green-500">
                    <p id="pincodeError" class="text-red-500 text-sm mt-1"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Delivery Option *</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" name="delivery_option" value="standard" required <?= ($_POST['delivery_option'] ?? '') === 'standard' ? 'checked' : '' ?> class="mr-2">
                            <span>Standard Delivery (2–3 days) - Free</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="delivery_option" value="express" required <?= ($_POST['delivery_option'] ?? '') === 'express' ? 'checked' : '' ?> class="mr-2">
                            <span>Express Delivery (Same day) - ₹50</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method *</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" name="payment_method" value="cod" required <?= ($_POST['payment_method'] ?? '') === 'cod' ? 'checked' : '' ?> class="mr-2">
                            <span>Cash on Delivery</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="payment_method" value="online" required <?= ($_POST['payment_method'] ?? '') === 'online' ? 'checked' : '' ?> class="mr-2">
                            <span>Online Payment <small class="text-gray-500">(Test Mode)</small></span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 transition font-semibold">
                    <span id="submit-text">Place Order</span>
                </button>
            </form>
        </div>
        
        <!-- Order Summary -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-6">Order Summary</h2>
            <div class="space-y-4 mb-6">
                <?php foreach ($_SESSION['cart'] as $item): ?>
                    <div class="flex items-center space-x-3">
                        <?= generateProductImageHTML($item, 'w-12 h-12 object-cover rounded'); ?>
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-800"><?= htmlspecialchars($item['name']); ?></h4>
                            <p class="text-sm text-gray-600">Qty: <?= $item['quantity']; ?></p>
                        </div>
                        <p class="font-semibold">₹<?= number_format($item['price'] * $item['quantity'], 2); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="border-t pt-4 space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-600">Subtotal</span>
                    <span id="subtotal" data-value="<?= $subtotal ?>" class="font-semibold">
                        ₹<?= number_format($subtotal, 2); ?>
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Delivery Fee</span>
                    <span id="delivery_fee"
                          data-normal="<?= $delivery_fee ?>"
                          data-express="50"
                          class="font-semibold">
                        <?= $delivery_fee === 0 ? 'Free' : '₹' . number_format($delivery_fee, 2); ?>
                    </span>
                </div>
                <div class="flex justify-between text-lg font-bold border-t pt-2">
                    <span>Total</span>
                    <span id="total" class="text-green-600">₹<?= number_format($total, 2); ?></span>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function fillAddressFields(address) {
    const addressInput = document.getElementById('autoAddress');
    const pincodeInput = document.getElementById('autoPincode');

    const road = address.road || '';
    const house = address.house_number || '';
    const suburb = address.suburb || '';
    const city = address.city || address.town || address.village || '';
    const state = address.state || '';
    const postcode = address.postcode || '';

    const fullAddress = [house, road, suburb, city, state].filter(Boolean).join(', ');

    if (addressInput) addressInput.value = fullAddress;
    if (pincodeInput) pincodeInput.value = postcode;
}

function fetchCurrentLocation() {
    const errorBox = document.getElementById('pincodeError');

    if (!navigator.geolocation) {
        if (errorBox) errorBox.textContent = "Geolocation is not supported by your browser.";
        return;
    }

    navigator.geolocation.getCurrentPosition(
        position => {
            const { latitude, longitude } = position.coords;
            const apiKey = 'pk.f69eec3be205a7124c4427f5e76c5a77'; 
            fetch(`https://us1.locationiq.com/v1/reverse.php?key=${apiKey}&lat=${latitude}&lon=${longitude}&format=json`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.address) {
                        fillAddressFields(data.address);
                        if (errorBox) errorBox.textContent = "";
                    } else {
                        if (errorBox) errorBox.textContent = "Failed to retrieve address data.";
                    }
                })
                .catch(err => {
                    console.warn("Address lookup failed", err);
                    if (errorBox) errorBox.textContent = "Error fetching address.";
                });
        },
        error => {
            if (errorBox) errorBox.textContent = "Location access denied.";
            console.warn("Geolocation error:", error.message);
        }
    );
}

document.addEventListener('DOMContentLoaded', function () {
    const deliveryOptions = document.querySelectorAll('input[name="delivery_option"]');
    const paymentOptions = document.querySelectorAll('input[name="payment_method"]');
    const submitButton = document.getElementById('submit-text');
    const subtotal = parseFloat(document.getElementById('subtotal').dataset.value);
    const deliveryFeeElem = document.getElementById('delivery_fee');
    const totalElem = document.getElementById('total');
    const normalFee = parseFloat(deliveryFeeElem.dataset.normal);
    const expressFee = parseFloat(deliveryFeeElem.dataset.express);

    // Update delivery fee calculation
    deliveryOptions.forEach(option => {
        option.addEventListener('change', function () {
            let fee = (this.value === 'express') ? normalFee + expressFee : normalFee;

            deliveryFeeElem.textContent = (fee === 0) ? 'Free' : '₹' + fee;
            totalElem.textContent = '₹' + (subtotal + fee);
        });
    });
    
    // Update submit button text based on payment method
    paymentOptions.forEach(option => {
        option.addEventListener('change', function () {
            if (this.value === 'online') {
                submitButton.textContent = 'Proceed to Payment';
            } else {
                submitButton.textContent = 'Place Order';
            }
        });
    });
});
</script>
<?php include 'footer.php'; ?>