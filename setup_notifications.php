<?php
// Notification Database Setup
require_once 'config.php';

try {
    echo "🔄 Setting up notifications database...\n\n";
    
    // Create notifications table
    $sql = "
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('order', 'payment', 'delivery', 'general', 'admin') DEFAULT 'general',
        is_read BOOLEAN DEFAULT FALSE,
        data JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $conn->exec($sql);
    echo "✅ Notifications table created successfully!\n";
    
    // Add indexes
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_notifications_is_read ON notifications(is_read)",
        "CREATE INDEX IF NOT EXISTS idx_notifications_type ON notifications(type)",
        "CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at DESC)"
    ];
    
    foreach ($indexes as $index) {
        $conn->exec($index);
    }
    echo "✅ Database indexes created successfully!\n";
    
    // Add a welcome notification for existing users (optional)
    $welcomeCheck = $conn->query("SELECT COUNT(*) FROM notifications WHERE type = 'general' AND title LIKE 'Welcome%'")->fetchColumn();
    
    if ($welcomeCheck == 0) {
        $users = $conn->query("SELECT id FROM users WHERE user_type = 'user'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $user) {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $user['id'],
                'Welcome to FreshMart Notifications!',
                'You will now receive notifications about your orders, deliveries, and special offers.',
                'general'
            ]);
        }
        echo "✅ Welcome notifications added for existing users!\n";
    }
    
    echo "\n🎉 Notification system setup complete!\n";
    echo "📱 Features available:\n";
    echo "   - Order confirmation notifications\n";
    echo "   - Admin order alerts\n";
    echo "   - Real-time notification badges\n";
    echo "   - Notification management page\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
