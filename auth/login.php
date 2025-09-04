<?php
include_once '../config.php';
require_once '../functions.php';
require_once '../image_handler.php';
require_once '../notification_helper.php';

$pageTitle = 'Login';
$message = [];

if (!isset($_SESSION)) session_start();

if (isset($_POST['submit'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $pass = md5($_POST['pass']);
    $pass = filter_var($pass, FILTER_SANITIZE_STRING);

    $stmt = $conn->prepare("SELECT * FROM `users` WHERE email = ? AND password = ?");
    $stmt->execute([$email, $pass]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        if ($row['user_type'] === 'admin') {
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['user'] = $row;
            
            // Create admin login notification
            createNotification(
                $row['id'],
                "Admin Login Successful",
                "Welcome back! You've successfully logged into the admin panel.",
                'login',
                ['login_time' => date('Y-m-d H:i:s'), 'user_type' => 'admin']
            );
            
            header('Location: ../admin/admin_page.php');
            exit;
        } elseif ($row['user_type'] === 'user') {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user'] = $row;
            
            // Create user login notification
            createNotification(
                $row['id'],
                "🔐 Login Successful",
                "Welcome back to FreshMart! You've successfully logged into your account.",
                'login',
                ['login_time' => date('Y-m-d H:i:s'), 'user_type' => 'user']
            );
            
            // Handle redirect parameter
            $redirect = $_GET['redirect'] ?? 'index';
            if ($redirect === 'checkout') {
                header('Location: ../checkout.php');
            } else {
                header('Location: ../index.php');
            }
            exit;
        } else {
            $message[] = 'User type not recognized!';
        }
    } else {
        $message[] = 'Incorrect email or password!';
    }
}
?>

<?php include '../header.php'; ?>

<!-- Login Page Content -->
<div class="pt-[70px] bg-gray-100 min-h-screen flex items-center justify-center">
   <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">
      <div class="text-center mb-6">
         <h2 class="text-2xl font-bold text-green-600">Welcome Back!</h2>
         <p class="text-sm text-gray-600">Login to your FreshMart account</p>
      </div>

      <?php if (!empty($message)): ?>
         <?php foreach ($message as $msg): ?>
            <div class="mb-4 px-4 py-2 bg-red-100 border border-red-400 text-red-700 rounded relative">
               <span><?= htmlspecialchars($msg) ?></span>
               <button onclick="this.parentElement.remove();" class="absolute right-2 top-1 text-red-700 hover:text-red-900">
                  <i class="fas fa-times"></i>
               </button>
            </div>
         <?php endforeach; ?>
      <?php endif; ?>

      <!-- Login Form -->
      <form method="POST" action="" class="space-y-4">
         <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
            <input type="email" name="email" id="email" class="w-full px-4 py-2 mt-1 border rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" required>
         </div>
         <div>
            <label for="pass" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" name="pass" id="pass" class="w-full px-4 py-2 mt-1 border rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" required>
         </div>
         <div>
            <button type="submit" name="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 rounded-md transition duration-300">
               Login Now
            </button>
         </div>
      </form>

      <p class="text-sm text-center mt-4">Don't have an account?
         <a href="register.php" class="text-green-600 hover:underline">Register now</a>
      </p>
   </div>
</div>

<?php include '../footer.php'; ?>
