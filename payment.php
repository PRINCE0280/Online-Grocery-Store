<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'razorpay_config.php';

if (empty($_SESSION['pending_order']) || empty($_SESSION['cart'])) {
    header('Location: checkout.php');
    exit;
}

$order_data = $_SESSION['pending_order'];
$pageTitle = 'Payment';
?>

<?php include 'header.php'; ?>

<main class="container mx-auto px-4 py-24 md:py-20">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6 text-center">Complete Payment</h1>
        
        <div class="space-y-4 mb-6">
            <div class="flex justify-between">
                <span class="text-gray-600">Order ID:</span>
                <span class="font-semibold"><?= htmlspecialchars($order_data['order_id']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Amount:</span>
                <span class="font-semibold text-green-600">₹<?= number_format($order_data['total'], 2) ?></span>
            </div>
        </div>

        <button id="pay-now" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition font-semibold">
            Pay ₹<?= number_format($order_data['total'], 2) ?>
        </button>
        
        <button id="test-payment" class="w-full bg-green-600 text-white py-2 px-4 rounded-lg mt-2 hover:bg-green-700 transition">
            Test Payment (Skip Gateway)
        </button>
        
        <div class="mt-4 text-center">
            <a href="checkout.php" class="text-gray-600 hover:text-gray-800">← Back to Checkout</a>
        </div>
        
        <div class="mt-6 p-4 bg-yellow-50 rounded-lg">
            <p class="text-sm text-yellow-800">
                <strong>Test Cards:</strong>
            </p>
            <div class="mt-2 text-xs text-yellow-700">
                <strong>Visa:</strong> 4111 1111 1111 1111<br>
                <strong>Mastercard:</strong> 5555 5555 5555 4444<br>
                <strong>Expiry:</strong> 12/25 | <strong>CVV:</strong> 123
            </div>
        </div>
    </div>
</main>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function() {
    'use strict';
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPayment);
    } else {
        initPayment();
    }
    
    function initPayment() {
        // Real payment button
        var payButton = document.getElementById('pay-now');
        if (payButton) {
            payButton.addEventListener('click', function(e) {
                e.preventDefault();
                startRazorpayPayment();
            });
        }
        
        // Test payment button
        var testButton = document.getElementById('test-payment');
        if (testButton) {
            testButton.addEventListener('click', function(e) {
                e.preventDefault();
                processTestPayment();
            });
        }
    }
    
    function startRazorpayPayment() {
        var options = {
            key: "<?= RAZORPAY_KEY_ID ?>",
            amount: "<?= isset($order_data['total_amount']) ? $order_data['total_amount'] : ($order_data['total'] * 100) ?>",
            currency: "INR",
            name: "Grocery Store",
            description: "Order Payment",
            handler: function(response) {
                submitPaymentForm(response.razorpay_payment_id);
            },
            prefill: {
                name: "<?= htmlspecialchars($order_data['name'] ?? '') ?>",
                contact: "<?= htmlspecialchars($order_data['mobile'] ?? '') ?>"
            },
            theme: {
                color: "#16a34a"
            }
        };
        
        try {
            var rzp = new Razorpay(options);
            rzp.open();
        } catch (error) {
            alert('Payment system error. Please try the test payment.');
            console.error(error);
        }
    }
    
    function processTestPayment() {
        var testPaymentId = 'pay_test_' + Date.now();
        submitPaymentForm(testPaymentId);
    }
    
    function submitPaymentForm(paymentId) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'payment_success.php';
        
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'razorpay_payment_id';
        input.value = paymentId;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
})();
</script>

<?php include 'footer.php'; ?>