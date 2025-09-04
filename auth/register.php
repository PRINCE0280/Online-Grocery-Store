<?php
include_once '../config.php';
require_once '../notification_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['submit'])) {
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $pass = md5($_POST['pass']);
    $cpass = md5($_POST['cpass']);
    $user_type = 'user';

    // Check if email already exists
    $stmt = $conn->prepare("SELECT * FROM `users` WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        $message[] = 'Email already exists!';
    } elseif ($pass !== $cpass) {
        $message[] = 'Passwords do not match!';
    } else {
        // Insert new user
        $insert = $conn->prepare("INSERT INTO `users` (name, email, password, user_type) VALUES (?, ?, ?, ?)");
        $insert->execute([$name, $email, $pass, $user_type]);

        // Fetch and store user in session
        $user_id = $conn->lastInsertId();
        $user_stmt = $conn->prepare("SELECT * FROM `users` WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = $user;

        // Create welcome notification
        createNotification(
            $user_id,
            "🎉 Welcome to FreshMart!",
            "Thank you for registering! Start exploring fresh groceries and enjoy fast delivery.",
            'welcome',
            ['registration_date' => date('Y-m-d H:i:s')]
        );

        header("Location: ../index.php");
        exit;
    }
}
?>

<?php include '../header.php'; ?>

<!-- Page Content -->
<div class="pt-[70px] bg-gray-100 min-h-screen flex items-center justify-center">
   <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">
      <div class="text-center mb-6">
         <h2 class="text-2xl font-bold text-green-600">Create Account</h2>
         <p class="text-sm text-gray-600">Register to start shopping with FreshMart</p>
      </div>

      <?php if (isset($message)): ?>
         <?php foreach ($message as $msg): ?>
            <div class="mb-4 px-4 py-2 bg-red-100 border border-red-400 text-red-700 rounded relative">
               <span><?= htmlspecialchars($msg) ?></span>
               <button onclick="this.parentElement.remove();" class="absolute right-2 top-1 text-red-700 hover:text-red-900">
                  <i class="fas fa-times"></i>
               </button>
            </div>
         <?php endforeach; ?>
      <?php endif; ?>

      <form action="" method="POST" class="space-y-4">
         <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
            <input type="text" name="name" id="name" required class="w-full px-4 py-2 mt-1 border rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
         </div>
         <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
            <input type="email" name="email" id="email" required class="w-full px-4 py-2 mt-1 border rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
         </div>
         <div>
            <label for="pass" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" name="pass" id="pass" required class="w-full px-4 py-2 mt-1 border rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
         </div>
         <div>
            <label for="cpass" class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input type="password" name="cpass" id="cpass" required class="w-full px-4 py-2 mt-1 border rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
         </div>
         <div>
            <button type="submit" name="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 rounded-md transition duration-300">
               Register Now
            </button>
         </div>
      </form>

      <p class="text-sm text-center mt-4">Already have an account? 
         <a href="login.php" class="text-green-600 hover:underline">Login now</a>
      </p>
   </div>
</div>
<?php include '../footer.php'; ?>
