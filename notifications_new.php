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

// Helper functions for notification styling
function getNotificationIcon($type) {
    switch($type) {
        case 'order': return 'fas fa-shopping-bag';
        case 'admin': return 'fas fa-user-shield';
        case 'welcome': return 'fas fa-heart';
        case 'login': return 'fas fa-sign-in-alt';
        case 'general': return 'fas fa-info-circle';
        default: return 'fas fa-bell';
    }
}

function getNotificationBgColor($type) {
    switch($type) {
        case 'order': return 'bg-blue-50';
        case 'admin': return 'bg-purple-50';
        case 'welcome': return 'bg-green-50';
        case 'login': return 'bg-yellow-50';
        case 'general': return 'bg-gray-50';
        default: return 'bg-gray-50';
    }
}

function getNotificationIconColor($type) {
    switch($type) {
        case 'order': return 'text-blue-600';
        case 'admin': return 'text-purple-600';
        case 'welcome': return 'text-green-600';
        case 'login': return 'text-yellow-600';
        case 'general': return 'text-gray-600';
        default: return 'text-gray-600';
    }
}
?>

<?php include 'header.php'; ?>

<main class="container mx-auto px-4 py-24 md:py-20">
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-bell text-green-600 mr-3"></i>
                    Notifications
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    <?php if ($unreadCount > 0): ?>
                        You have <?php echo $unreadCount; ?> unread notification<?php echo $unreadCount > 1 ? 's' : ''; ?>
                    <?php else: ?>
                        All caught up! No unread notifications.
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Mark All Read Button -->
            <?php if ($unreadCount > 0): ?>
                <form method="POST">
                    <button type="submit" name="mark_all_read" 
                            class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm rounded-xl hover:bg-green-700 transition-colors duration-200 shadow-sm hover:shadow-md">
                        <i class="fas fa-check-double mr-2 text-xs"></i>Mark All Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
            <!-- Empty State -->
            <div class="text-center py-16 bg-white rounded-2xl shadow-sm">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-bell-slash text-2xl text-gray-400"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800 mb-2">No Notifications Yet</h3>
                <p class="text-gray-500 mb-6">You'll see notifications about your orders and updates here.</p>
                <a href="index.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 text-white rounded-xl hover:from-green-700 hover:to-green-800 transition-all duration-200 shadow-lg hover:shadow-xl">
                    <i class="fas fa-shopping-cart mr-2"></i>
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <!-- Notifications List -->
            <div class="space-y-3">
                <?php foreach ($notifications as $notification): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow duration-200 <?php echo !$notification['is_read'] ? 'ring-2 ring-green-100' : ''; ?>">
                        <div class="p-4">
                            <div class="flex items-start space-x-3">
                                <!-- Icon -->
                                <div class="flex-shrink-0 mt-1">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center <?php echo getNotificationBgColor($notification['type']); ?>">
                                        <i class="<?php echo getNotificationIcon($notification['type']); ?> text-sm <?php echo getNotificationIconColor($notification['type']); ?>"></i>
                                    </div>
                                </div>
                                
                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between">
                                        <h3 class="text-sm font-semibold text-gray-900 leading-relaxed <?php echo !$notification['is_read'] ? 'font-bold' : ''; ?>">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                            <?php if (!$notification['is_read']): ?>
                                                <span class="inline-block w-1.5 h-1.5 bg-green-500 rounded-full ml-1"></span>
                                            <?php endif; ?>
                                        </h3>
                                        
                                        <!-- Actions -->
                                        <div class="flex items-center space-x-1 ml-2">
                                            <?php if (!$notification['is_read']): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" name="mark_read" 
                                                            class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-md transition-colors duration-200"
                                                            title="Mark as read">
                                                        <i class="fas fa-check text-xs"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" name="delete" 
                                                        class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-md transition-colors duration-200"
                                                        title="Delete notification"
                                                        onclick="return confirm('Delete this notification?')">
                                                    <i class="fas fa-times text-xs"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <p class="text-sm text-gray-600 mt-1 leading-relaxed">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </p>
                                    
                                    <!-- Additional data (show order details only once, no duplicate Order ID) -->
                                    <?php if ($notification['data']): ?>
                                        <?php $data = json_decode($notification['data'], true); ?>
                                        <?php if ($notification['type'] === 'order' && isset($data['total'])): ?>
                                            <div class="mt-2 flex items-center space-x-4 text-xs text-gray-500">
                                                <span class="inline-flex items-center bg-gray-50 px-2 py-1 rounded-md">
                                                    <i class="fas fa-rupee-sign mr-1"></i>₹<?php echo number_format($data['total'], 2); ?>
                                                </span>
                                                <?php if (isset($data['payment_method'])): ?>
                                                    <span class="inline-flex items-center bg-blue-50 text-blue-700 px-2 py-1 rounded-md">
                                                        <i class="fas fa-credit-card mr-1"></i><?php echo ucfirst($data['payment_method']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (isset($data['delivery_option'])): ?>
                                                    <span class="inline-flex items-center bg-green-50 text-green-700 px-2 py-1 rounded-md">
                                                        <i class="fas fa-truck mr-1"></i><?php echo ucfirst($data['delivery_option']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($notification['type'] === 'welcome' || $notification['type'] === 'login'): ?>
                                            <?php if (isset($data['login_time']) || isset($data['registration_date'])): ?>
                                                <div class="mt-2">
                                                    <span class="inline-flex items-center bg-gray-50 px-2 py-1 rounded-md text-xs text-gray-500">
                                                        <i class="fas fa-calendar mr-1"></i>
                                                        <?php echo isset($data['login_time']) ? date('M j, Y g:i A', strtotime($data['login_time'])) : date('M j, Y g:i A', strtotime($data['registration_date'])); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <div class="flex items-center justify-between mt-3">
                                        <span class="text-xs text-gray-400">
                                            <i class="fas fa-clock mr-1"></i><?php echo timeAgo($notification['created_at']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Load More Button -->
            <?php if (count($notifications) >= 50): ?>
                <div class="text-center mt-8">
                    <button class="inline-flex items-center px-6 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-colors duration-200 text-sm font-medium">
                        <i class="fas fa-chevron-down mr-2"></i>
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

// Add smooth animations for actions
document.addEventListener('DOMContentLoaded', function() {
    // Add loading state to buttons
    const actionButtons = document.querySelectorAll('button[name="mark_read"], button[name="delete"]');
    actionButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.style.opacity = '0.5';
            this.style.pointerEvents = 'none';
        });
    });
});
</script>
