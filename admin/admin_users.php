<?php
@include '../config.php';

// Redirect if not logged in
$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) {
    header('Location: ../auth/login.php');
    exit;
}

// Handle status updates for user accounts
if ($_POST['action'] ?? '' === 'update_user_status') {
    $user_id = $_POST['user_id'] ?? 0;
    $new_status = $_POST['status'] ?? 'active';
    
    try {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND user_type = 'user'");
        $stmt->execute([$new_status, $user_id]);
        $_SESSION['success'] = "User status updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update user status.";
    }
    
    header('Location: admin_users.php');
    exit;
}

// Get search parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query with filters
$query = "SELECT u.*, 
          COUNT(o.id) as total_orders,
          SUM(CASE WHEN o.payment_status = 'paid' THEN o.total ELSE 0 END) as total_spent,
          MAX(o.order_date) as last_order_date
          FROM users u 
          LEFT JOIN orders o ON u.id = o.user_id 
          WHERE u.user_type = 'user'";

$params = [];

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($status_filter)) {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_users,
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_registrations
    FROM users WHERE user_type = 'user'";
$stats = $conn->query($stats_query)->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">

<?php include 'admin_header.php'; ?>

<div class="p-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Manage Users</h1>
        <a href="admin_page.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_SESSION['success']); ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_SESSION['error']); ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
            <div class="flex items-center">
                <i class="fas fa-users text-blue-500 text-2xl mr-4"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Total Users</h3>
                    <p class="text-2xl font-bold text-blue-600"><?= $stats['total_users'] ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
            <div class="flex items-center">
                <i class="fas fa-user-check text-green-500 text-2xl mr-4"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Active Users</h3>
                    <p class="text-2xl font-bold text-green-600"><?= $stats['active_users'] ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-red-500">
            <div class="flex items-center">
                <i class="fas fa-user-times text-red-500 text-2xl mr-4"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Inactive Users</h3>
                    <p class="text-2xl font-bold text-red-600"><?= $stats['inactive_users'] ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow border-l-4 border-yellow-500">
            <div class="flex items-center">
                <i class="fas fa-user-plus text-yellow-500 text-2xl mr-4"></i>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Today's Signups</h3>
                    <p class="text-2xl font-bold text-yellow-600"><?= $stats['today_registrations'] ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-64">
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Users</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search by name or email..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="min-w-48">
                <label class="block text-sm font-medium text-gray-700 mb-2">Status Filter</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Status</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            
            <a href="admin_users.php" class="px-6 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
                <i class="fas fa-refresh mr-2"></i>Clear
            </a>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User Info</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Spent</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-users text-4xl mb-4"></i>
                                <p class="text-lg">No users found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                <i class="fas fa-user text-gray-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($user['name']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                Joined: <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($user['email']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($user['mobile'] ?? 'N/A') ?></div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= $user['total_orders'] ?> orders</div>
                                    <div class="text-sm text-gray-500">
                                        <?php if ($user['last_order_date']): ?>
                                            Last: <?= date('M j, Y', strtotime($user['last_order_date'])) ?>
                                        <?php else: ?>
                                            No orders yet
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-green-600">
                                        ₹<?= number_format($user['total_spent'], 2) ?>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <form method="POST" class="inline-block">
                                        <input type="hidden" name="action" value="update_user_status">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <select name="status" onchange="this.form.submit()" 
                                                class="text-sm rounded-full px-3 py-1 font-semibold 
                                                       <?= $user['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                    </form>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="admin_orders.php?user_id=<?= $user['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-900 mr-4">
                                        <i class="fas fa-shopping-cart mr-1"></i>View Orders
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Auto-submit form when status changes
document.addEventListener('DOMContentLoaded', function() {
    const statusSelects = document.querySelectorAll('select[name="status"]');
    statusSelects.forEach(select => {
        select.addEventListener('change', function() {
            if (confirm('Are you sure you want to change this user\'s status?')) {
                this.form.submit();
            } else {
                // Revert to original value
                this.selectedIndex = this.getAttribute('data-original-index');
            }
        });
        
        // Store original index
        select.setAttribute('data-original-index', select.selectedIndex);
    });
});
</script>

</body>
</html>
