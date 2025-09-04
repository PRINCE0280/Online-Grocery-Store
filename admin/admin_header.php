<?php
@include '../config.php';
//session_start();

$admin_id = $_SESSION['admin_id'] ?? null;

if (isset($message)) {
   foreach ($message as $msg) {
      echo '
      <div class="message">
         <span>' . $msg . '</span>
         <i class="fas fa-times" onclick="this.parentElement.remove();"></i>
      </div>';
   }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>Admin Panel</title>
   <script src="https://cdn.tailwindcss.com"></script>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header class="header">
   <div class="flex justify-between items-center px-8 py-3 bg-green-600 shadow-md text-white">
      <!-- FreshMart Admin Logo -->
 <a href="admin_page.php" class="flex items-center text-white text-xl font-semibold italic">
   <i class="fas fa-shopping-basket mr-2"></i>
   <span>FreshMart</span>
   <span class="ml-6 text-base not-italic font-normal text-white">Admin Panel</span>
</a>

      <!-- Navigation -->
      <nav class="navbar space-x-4">
         <a href="admin_page.php" class="hover:underline">Home</a>
         <a href="admin_products.php" class="hover:underline">Products</a>
         <a href="admin_orders.php" class="hover:underline">Orders</a>
         <a href="admin_users.php" class="hover:underline">Users</a>
         <a href="admin_contacts.php" class="hover:underline">Messages</a>
      </nav>

      <!-- Profile Section -->
      <div class="profile relative text-white">
         <?php if ($admin_id): ?>
            <?php
               $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
               $stmt->execute([$admin_id]);
               $fetch_profile = $stmt->fetch(PDO::FETCH_ASSOC);
               $admin_image = $fetch_profile['image'] ?? 'default.png';
            ?>
            <img src="../uploaded_img/<?= htmlspecialchars($admin_image); ?>" alt="Admin" class="w-10 h-10 rounded-full object-cover border mx-auto">
            <p class="text-sm mt-1 text-center"><?= htmlspecialchars($fetch_profile['name']); ?></p>
            <div class="mt-2 text-center">
               <a href="../auth/logout.php" class="delete-btn text-sm bg-red-500 text-white px-3 py-1 rounded ml-2">Logout</a>
            </div>
         <?php else: ?>
            <div class="flex space-x-2">
               <a href="../auth/login.php" class="option-btn bg-white text-green-700 px-3 py-1 rounded text-sm">Login</a>
               <a href="../auth/register.php" class="option-btn bg-gray-100 text-green-700 px-3 py-1 rounded text-sm">Register</a>
            </div>
         <?php endif; ?>
      </div>
   </div>
</header>



</body>
</html>
