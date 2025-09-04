<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'notification_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$pageTitle = 'Notifications';
$user_id = $_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        markNotificationAsRead($_POST['notification_id'], $user_id);
    } elseif (isset($_POST['mark_all_read'])) {
        markAllNotificationsAsRead($user_id);
    } elseif (isset($_POST['delete']) && isset($_POST['notification_id'])) {
        deleteNotification($_POST['notification_id'], $user_id);
    }
    
    // Redirect to prevent form resubmission
    header('Location: notifications.php');
    exit;
}

// Get notifications
$notifications = getUserNotifications($user_id, 50);
$unreadCount = getUnreadNotificationCount($user_id);
?>

<?php include 'header.php'; ?>

<main class="container mx-auto px-4 py-24 md:py-20">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">Notifications</h1>
                <p class="text-gray-600 mt-1">
                    <?php if ($unreadCount > 0): ?>
                        You have <?php echo $unreadCount; ?> unread notification<?php echo $unreadCount > 1 ? 's' : ''; ?>
                    <?php else: ?>
                        All caught up! No unread notifications.
                    <?php endif; ?>
                </p>
            </div>
            
            <?php if ($unreadCount > 0): ?>
                <form method="POST" class="inline">
                    <button type="submit" name="mark_all_read" 
                            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm">
                        <i class="fas fa-check-double mr-2"></i>Mark All Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
            <!-- Empty State -->
            <div class="text-center py-12">
                <div class="bg-gray-100 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-bell-slash text-4xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">No Notifications Yet</h3>
                <p class="text-gray-600">You'll see notifications about your orders and updates here.</p>
                <a href="index.php" class="inline-block mt-4 bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <!-- Notifications List -->
            <div class="space-y-4">
                <?php foreach ($notifications as $notification): ?>
                    <div class="bg-white rounded-lg shadow-md p-4 <?php echo !$notification['is_read'] ? 'border-l-4 border-green-500' : ''; ?>">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start space-x-4 flex-1">
                                <!-- Icon -->
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                                        <i class="<?php echo getNotificationIcon($notification['type']); ?> <?php echo getNotificationColor($notification['type']); ?>"></i>
                                    </div>
                                </div>
                                
                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-semibold text-gray-800 <?php echo !$notification['is_read'] ? 'font-bold' : ''; ?>">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="inline-block w-2 h-2 bg-green-500 rounded-full ml-2"></span>
                                        <?php endif; ?>
                                    </h3>
                                    
                                    <p class="text-gray-600 mt-1">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </p>
                                    
                                    <!-- Additional data -->
                                    <?php if ($notification['data']): ?>
                                        <?php $data = json_decode($notification['data'], true); ?>
                                        <?php if ($notification['type'] === 'order' && isset($data['order_id'])): ?>
                                            <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                                <span><i class="fas fa-receipt mr-1"></i>Order ID: <?php echo htmlspecialchars($data['order_id']); ?></span>
                                                <?php if (isset($data['total'])): ?>
                                                    <span><i class="fas fa-rupee-sign mr-1"></i>₹<?php echo number_format($data['total'], 2); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <div class="flex items-center justify-between mt-3">
                                        <span class="text-sm text-gray-500">
                                            <i class="fas fa-clock mr-1"></i><?php echo timeAgo($notification['created_at']); ?>
                                        </span>
                                        
                                        <div class="flex items-center space-x-2">
                                            <?php if (!$notification['is_read']): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" name="mark_read" 
                                                            class="text-green-600 hover:text-green-800 text-sm font-medium">
                                                        <i class="fas fa-check mr-1"></i>Mark Read
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" name="delete" 
                                                        onclick="return confirm('Delete this notification?')"
                                                        class="text-red-600 hover:text-red-800 text-sm font-medium">
                                                    <i class="fas fa-trash mr-1"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Load More (if needed) -->
            <?php if (count($notifications) >= 50): ?>
                <div class="text-center mt-8">
                    <button class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition">
                        Load More Notifications
                    </button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
// Auto-refresh notifications count every 30 seconds
setInterval(function() {
    fetch('get_notification_count.php')
        .then(response => response.json())
        .then(data => {
            if (data.count !== undefined) {
                // Update notification badge in header if it exists
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    badge.textContent = data.count;
                    badge.style.display = data.count > 0 ? 'inline' : 'none';
                }
            }
        })
        .catch(error => console.error('Error fetching notification count:', error));
}, 30000);
</script>
