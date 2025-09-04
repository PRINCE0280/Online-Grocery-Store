<?php
/**
 * Notification Helper Functions
 * Handles creating, reading, and managing notifications
 */

require_once 'config.php';

/**
 * Create a new notification
 */
function createNotification($userId, $title, $message, $type = 'general', $data = null) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, data, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $jsonData = $data ? json_encode($data) : null;
        
        $result = $stmt->execute([$userId, $title, $message, $type, $jsonData]);
        
        if ($result) {
            return $conn->lastInsertId();
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notifications for a user
 */
function getUserNotifications($userId, $limit = 20, $unreadOnly = false) {
    global $conn;
    
    try {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];
        
        if ($unreadOnly) {
            $sql .= " AND is_read = FALSE";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT " . intval($limit);
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Get unread notification count for a user
 */
function getUnreadNotificationCount($userId) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$userId]);
        
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error counting unread notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($notificationId, $userId = null) {
    global $conn;
    
    try {
        $sql = "UPDATE notifications SET is_read = TRUE, updated_at = NOW() WHERE id = ?";
        $params = [$notificationId];
        
        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 */
function markAllNotificationsAsRead($userId) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE, updated_at = NOW() WHERE user_id = ? AND is_read = FALSE");
        return $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete notification
 */
function deleteNotification($notificationId, $userId = null) {
    global $conn;
    
    try {
        $sql = "DELETE FROM notifications WHERE id = ?";
        $params = [$notificationId];
        
        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $conn->prepare($sql);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Error deleting notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create order notification for customer and admin
 */
function createOrderNotification($orderId, $orderData, $customerId) {
    // Customer notification
    $customerTitle = "Order Confirmed - " . $orderData['order_id'];
    $customerMessage = "Your order for ₹" . number_format($orderData['total'], 2) . " has been confirmed and will be delivered soon.";
    $customerData = [
        'order_id' => $orderData['order_id'],
        'total' => $orderData['total'],
        'payment_method' => $orderData['payment_method'],
        'delivery_option' => $orderData['delivery_option']
    ];
    
    createNotification($customerId, $customerTitle, $customerMessage, 'order', $customerData);
    
    // Admin notification - Get admin user ID
    global $conn;
    try {
        $stmt = $conn->prepare("SELECT id FROM users WHERE user_type = 'admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            $adminTitle = "New Order Received - " . $orderData['order_id'];
            $adminMessage = "New order from " . $orderData['name'] . " for ₹" . number_format($orderData['total'], 2) . " (" . ($orderData['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Online Payment') . ")";
            $adminData = [
                'order_id' => $orderData['order_id'],
                'customer_name' => $orderData['name'],
                'customer_mobile' => $orderData['mobile'],
                'total' => $orderData['total'],
                'payment_method' => $orderData['payment_method'],
                'is_admin_notification' => true
            ];
            
            createNotification($admin['id'], $adminTitle, $adminMessage, 'admin', $adminData);
        }
    } catch (Exception $e) {
        error_log("Error creating admin notification: " . $e->getMessage());
    }
}

/**
 * Get notification icon based on type
 */
function getNotificationIcon($type) {
    switch ($type) {
        case 'order':
            return 'fas fa-shopping-bag';
        case 'admin':
            return 'fas fa-user-shield';
        case 'welcome':
            return 'fas fa-heart';
        case 'login':
            return 'fas fa-sign-in-alt';
        case 'payment':
            return 'fas fa-credit-card';
        case 'delivery':
            return 'fas fa-truck';
        case 'general':
            return 'fas fa-info-circle';
        default:
            return 'fas fa-bell';
    }
}

/**
 * Get notification background color based on type
 */
function getNotificationBgColor($type) {
    switch($type) {
        case 'order':
            return 'bg-blue-50';
        case 'admin':
            return 'bg-purple-50';
        case 'welcome':
            return 'bg-green-50';
        case 'login':
            return 'bg-yellow-50';
        case 'payment':
            return 'bg-blue-50';
        case 'delivery':
            return 'bg-orange-50';
        case 'general':
            return 'bg-gray-50';
        default:
            return 'bg-gray-50';
    }
}

/**
 * Get notification icon color based on type
 */
function getNotificationIconColor($type) {
    switch($type) {
        case 'order':
            return 'text-blue-600';
        case 'admin':
            return 'text-purple-600';
        case 'welcome':
            return 'text-green-600';
        case 'login':
            return 'text-yellow-600';
        case 'payment':
            return 'text-blue-600';
        case 'delivery':
            return 'text-orange-600';
        case 'general':
            return 'text-gray-600';
        default:
            return 'text-gray-600';
    }
}

/**
 * Get notification color based on type (legacy function)
 */
function getNotificationColor($type) {
    switch ($type) {
        case 'order':
            return 'text-blue-600';
        case 'payment':
            return 'text-blue-600';
        case 'delivery':
            return 'text-orange-600';
        case 'admin':
            return 'text-purple-600';
        case 'welcome':
            return 'text-green-600';
        case 'login':
            return 'text-yellow-600';
        case 'general':
            return 'text-gray-600';
        default:
            return 'text-gray-600';
    }
}

/**
 * Time ago helper function
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm ago';
    if ($time < 86400) return floor($time/3600) . 'h ago';
    if ($time < 2592000) return floor($time/86400) . 'd ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>
