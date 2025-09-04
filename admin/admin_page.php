<?php
@include '../config.php';
//session_start();

// Redirect if not logged in
$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('Location: ../auth/login.php');
    exit;
}

// Fetch admin info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>Admin Dashboard</title>
   <script src="https://cdn.tailwindcss.com"></script>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">

<?php include 'admin_header.php'; ?>

<div class="p-6">
   <h1 class="text-2xl font-bold text-gray-800 mb-4">Welcome, <?= htmlspecialchars($admin['name']) ?> </h1>

   <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <a href="admin_products.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition border-l-4 border-green-500">
         <h2 class="text-xl font-semibold mb-2">Manage Products</h2>
         <p class="text-gray-600">View, add, edit, or delete grocery items.</p>
      </a>

      <a href="admin_orders.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition border-l-4 border-blue-500">
         <h2 class="text-xl font-semibold mb-2">Manage Orders</h2>
         <p class="text-gray-600">Track, update, and manage customer orders.</p>
      </a>

      <a href="admin_users.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition border-l-4 border-yellow-500">
         <h2 class="text-xl font-semibold mb-2">Manage Users</h2>
         <p class="text-gray-600">View registered users and their activities.</p>
      </a>

      <a href="admin_contacts.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition border-l-4 border-red-500">
         <h2 class="text-xl font-semibold mb-2">Messages</h2>
         <p class="text-gray-600">Read contact form messages or inquiries.</p>
      </a>

      <a href="../profile.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition border-l-4 border-indigo-500">
         <h2 class="text-xl font-semibold mb-2">Update Profile</h2>
         <p class="text-gray-600">Edit your profile and password.</p>
      </a>

      <a href="../auth/logout.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition border-l-4 border-gray-500">
         <h2 class="text-xl font-semibold mb-2">Logout</h2>
         <p class="text-gray-600">End your session securely.</p>
      </a>
   </div>
</div>

</body>
</html>
