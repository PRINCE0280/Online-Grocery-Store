<?php
@include 'config.php';
//session_start();

// Check both user and admin login
$user_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;

if (!$user_id) {
    header('location:login.php');
    exit;
}

// Fetch user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$fetch_profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fetch_profile) {
    header('location:login.php');
    exit;
}

$user_type = $fetch_profile['user_type'] ?? 'user'; // Needed for Go Back link
$message = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $has_error = false;

   // Handle remove profile image action (triggered from avatar menu)
   if (isset($_POST['remove_profile_image'])) {
      $old_image = $_POST['old_image'] ?? '';
      if ($old_image && file_exists(__DIR__ . '/uploaded_img/' . $old_image)) {
         @unlink(__DIR__ . '/uploaded_img/' . $old_image);
      }
      $conn->prepare("UPDATE `users` SET image = ? WHERE id = ?")->execute(['', $user_id]);
      $_SESSION['success_msg'] = 'Profile image removed successfully.';
      header("Location: profile.php");
      exit;
   }

    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

    // Update name and email
    $conn->prepare("UPDATE `users` SET name = ?, email = ? WHERE id = ?")
        ->execute([$name, $email, $user_id]);

    // Image upload
    $old_image = $_POST['old_image'] ?? '';
    if (isset($_FILES['image']) && $_FILES['image']['name']) {
        $image = $_FILES['image']['name'];
        $image_tmp_name = $_FILES['image']['tmp_name'];
        $image_folder = 'uploaded_img/' . $image;

        if ($_FILES['image']['size'] > 2000000) {
            $message[] = 'Image size too large!';
            $has_error = true;
        } else {
            $conn->prepare("UPDATE `users` SET image = ? WHERE id = ?")
                ->execute([$image, $user_id]);
            move_uploaded_file($image_tmp_name, $image_folder);

            if ($old_image && $old_image !== $image && file_exists('uploaded_img/' . $old_image)) {
                @unlink('uploaded_img/' . $old_image);
            }
        }
    }

    // Password update
    $entered_current_pass = md5($_POST['update_pass'] ?? '');
    $new_pass = md5($_POST['new_pass'] ?? '');
    $confirm_pass = md5($_POST['confirm_pass'] ?? '');

    if (!empty($_POST['update_pass']) || !empty($_POST['new_pass']) || !empty($_POST['confirm_pass'])) {
        if ($entered_current_pass !== getUserPassword($conn, $user_id)) {
            $message[] = 'Old password not matched!';
            $has_error = true;
        } elseif ($new_pass !== $confirm_pass) {
            $message[] = 'Confirm password not matched!';
            $has_error = true;
        } else {
            $conn->prepare("UPDATE `users` SET password = ? WHERE id = ?")
                ->execute([$confirm_pass, $user_id]);
        }
    }

    if (!$has_error) {
        $_SESSION['success_msg'] = 'Profile updated successfully!';
        header("Location: profile.php");
        exit;
    }
}

// Helper to get latest password
function getUserPassword($conn, $id) {
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <title>Update Profile</title>
   <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center px-2">
   <div class="bg-white rounded-xl shadow-lg w-full max-w-xl p-5">
      <h2 class="text-xl font-bold text-green-600 mb-4 text-center">Update Your Profile</h2>

      <!-- Success Message -->
      <?php if (!empty($_SESSION['success_msg'])): ?>
         <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-2 mb-3 rounded">
            <?= htmlspecialchars($_SESSION['success_msg']) ?>
         </div>
         <?php unset($_SESSION['success_msg']); ?>
      <?php endif; ?>

      <!-- Error Messages -->
      <?php if (!empty($message)): ?>
         <?php foreach ($message as $msg): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-2 mb-3 rounded">
               <?= htmlspecialchars($msg) ?>
            </div>
         <?php endforeach; ?>
      <?php endif; ?>

      <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
         <?php
         // Decide whether an uploaded profile image exists. If not, show initial with deterministic colors.
         $raw_img = $fetch_profile['image'] ?? '';
         $profile_img = '';
         $image_exists = false;

         if (!empty($raw_img)) {
             $sanitized_img = htmlspecialchars($raw_img);
             $imgPath = __DIR__ . '/uploaded_img/' . $sanitized_img;
             if (file_exists($imgPath) && is_file($imgPath)) {
                 $profile_img = $sanitized_img;
                 $image_exists = true;
             }
         }

         $user_name_for_initial = $fetch_profile['name'] ?? '';
         $initial = strtoupper(substr($user_name_for_initial ?: 'T', 0, 1));

         // Palette and deterministic selection (keeps colors varied per user/name)
         $palette = [
             ['bg' => '#FEEBC8', 'fg' => '#7C3AED'],
             ['bg' => '#E6FFFA', 'fg' => '#065F46'],
             ['bg' => '#FEF3C7', 'fg' => '#92400E'],
             ['bg' => '#E0F2FE', 'fg' => '#0C4A6E'],
             ['bg' => '#FCE7F3', 'fg' => '#701A75'],
             ['bg' => '#F0FDF4', 'fg' => '#14532D'],
             ['bg' => '#FFF7ED', 'fg' => '#92400E'],
             ['bg' => '#F8FAFC', 'fg' => '#0F172A'],
             ['bg' => '#FFF1F2', 'fg' => '#9F1239'],
             ['bg' => '#F3F4F6', 'fg' => '#1F2937'],
         ];
         $seed = $user_name_for_initial ?: $initial;
         $hash = crc32($seed);
         $choice = $palette[$hash % count($palette)];
         $avatar_bg = $choice['bg'];
         $avatar_fg = $choice['fg'];
         ?>

         <div class="flex justify-center relative" id="avatarWrapper">
            <?php if ($image_exists): ?>
               <button type="button" id="avatarButton" class="focus:outline-none">
                  <img src="uploaded_img/<?= $profile_img ?>?t=<?= time() ?>" alt="Profile" class="w-24 h-24 object-cover rounded-full border border-gray-300">
               </button>

               <!-- Avatar menu -->
               <div id="avatarMenu" class="absolute mt-2 hidden w-48 right-0 text-sm bg-white border rounded shadow-md z-50">
                  <form method="POST" class="p-2">
                     <input type="hidden" name="old_image" value="<?= htmlspecialchars($fetch_profile['image'] ?? '') ?>">
                     <input type="hidden" name="update_profile" value="1">
                     <button type="submit" name="remove_profile_image" class="w-full text-left px-3 py-2 hover:bg-gray-100 text-red-600">Remove image</button>
                  </form>
               </div>

            <?php else: ?>
               <div class="w-24 h-24 rounded-full border border-gray-300 flex items-center justify-center" style="background: <?= $avatar_bg ?>; color: <?= $avatar_fg ?>; font-weight:600; font-size:1.25rem;">
                  <?= htmlspecialchars($initial) ?>
               </div>
            <?php endif; ?>
         </div>

         <script>
            document.addEventListener('DOMContentLoaded', function () {
               const btn = document.getElementById('avatarButton');
               const menu = document.getElementById('avatarMenu');
               if (btn && menu) {
                  btn.addEventListener('click', function (e) {
                     e.stopPropagation();
                     menu.classList.toggle('hidden');
                  });

                  document.addEventListener('click', function (e) {
                     if (!menu.classList.contains('hidden')) {
                        menu.classList.add('hidden');
                     }
                  });
               }
            });
         </script>

         <input type="hidden" name="old_image" value="<?= htmlspecialchars($fetch_profile['image'] ?? '') ?>">

         <div>
            <label class="block font-medium text-sm mb-1">Username</label>
            <input type="text" name="name" value="<?= htmlspecialchars($fetch_profile['name']) ?>" required class="w-full border border-gray-300 px-3 py-2 rounded-md text-sm">
         </div>

         <div>
            <label class="block font-medium text-sm mb-1">Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($fetch_profile['email']) ?>" required class="w-full border border-gray-300 px-3 py-2 rounded-md text-sm">
         </div>

         <div>
            <label class="block font-medium text-sm mb-1">Update Profile Picture</label>
            <input type="file" name="image" accept="image/*" class="w-full border border-gray-300 px-2 py-1 rounded-md text-sm">
         </div>

         <div>
            <label class="block font-medium text-sm mb-1">Current Password</label>
            <input type="password" name="update_pass" class="w-full border border-gray-300 px-3 py-2 rounded-md text-sm">
         </div>

         <div>
            <label class="block font-medium text-sm mb-1">New Password</label>
            <input type="password" name="new_pass" class="w-full border border-gray-300 px-3 py-2 rounded-md text-sm">
         </div>

         <div>
            <label class="block font-medium text-sm mb-1">Confirm New Password</label>
            <input type="password" name="confirm_pass" class="w-full border border-gray-300 px-3 py-2 rounded-md text-sm">
         </div>

         <div class="mt-4 flex justify-between items-center">
            <button type="submit" name="update_profile" class="bg-green-600 text-white px-5 py-2 rounded-md hover:bg-green-700 text-sm">Update Profile</button>
            <a href="<?= $user_type === 'admin' ? 'admin/admin_page.php' : 'index.php' ?>" class="text-blue-600 hover:underline text-sm">Go Back</a>
         </div>
      </form>
   </div>
</body>
</html>
