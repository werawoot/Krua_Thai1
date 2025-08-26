<?php
/**
 * Somdul Table - Notification Manager
 * Handles all notification operations with automatic cleanup
 */

class NotificationManager {
    private $pdo;
    private $maxNotificationsPerUser;
    
    public function __construct($pdo, $maxNotificationsPerUser = 15) {
        $this->pdo = $pdo;
        $this->maxNotificationsPerUser = $maxNotificationsPerUser;
    }
    
    /**
     * Create a new notification
     * 
     * @param string $userId UUID of the user
     * @param string $type (order, system, promotion, delivery, payment, general)
     * @param string $title
     * @param string $message
     * @param array $data Additional data (order_id, subscription_id, etc.)
     * @param string $priority (low, medium, high, urgent)
     * @param DateTime $expiresAt Optional expiration date
     * @return int|false Notification ID or false on failure
     */
    public function create($userId, $type, $title, $message, $data = null, $priority = 'medium', $expiresAt = null) {
        try {
            $sql = "INSERT INTO notifications (user_id, type, title, message, data, priority, expires_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                $userId,
                $type,
                $title,
                $message,
                $data ? json_encode($data) : null,
                $priority,
                $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null
            ]);
            
            if ($success) {
                $notificationId = $this->pdo->lastInsertId();
                
                // Manual cleanup if trigger doesn't exist
                $this->cleanupOldNotifications($userId);
                
                return $notificationId;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Notification creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notifications for a user
     * 
     * @param string $userId UUID of the user
     * @param bool $unreadOnly Get only unread notifications
     * @param int $limit Maximum number of notifications to return
     * @param string $type Filter by notification type
     * @return array
     */
    public function getUserNotifications($userId, $unreadOnly = false, $limit = 20, $type = null) {
        try {
            $sql = "SELECT * FROM notifications WHERE user_id = ?";
            $params = [$userId];
            
            if ($unreadOnly) {
                $sql .= " AND is_read = FALSE";
            }
            
            if ($type) {
                $sql .= " AND type = ?";
                $params[] = $type;
            }
            
            // Exclude expired notifications
            $sql .= " AND (expires_at IS NULL OR expires_at > NOW())";
            
            $sql .= " ORDER BY priority DESC, created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON data
            foreach ($notifications as &$notification) {
                if ($notification['data']) {
                    $notification['data'] = json_decode($notification['data'], true);
                }
            }
            
            return $notifications;
            
        } catch (Exception $e) {
            error_log("Error fetching notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark notification(s) as read
     * 
     * @param int|array $notificationIds Single ID or array of IDs
     * @param string $userId UUID of the user for security
     * @return bool
     */
    public function markAsRead($notificationIds, $userId) {
        try {
            if (!is_array($notificationIds)) {
                $notificationIds = [$notificationIds];
            }
            
            $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
            $sql = "UPDATE notifications 
                    SET is_read = TRUE, read_at = NOW() 
                    WHERE id IN ($placeholders) AND user_id = ?";
            
            $params = array_merge($notificationIds, [$userId]);
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute($params);
            
        } catch (Exception $e) {
            error_log("Error marking notifications as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for a user
     * 
     * @param string $userId UUID of the user
     * @return bool
     */
    public function markAllAsRead($userId) {
        try {
            $sql = "UPDATE notifications 
                    SET is_read = TRUE, read_at = NOW() 
                    WHERE user_id = ? AND is_read = FALSE";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$userId]);
            
        } catch (Exception $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread notification count for a user
     * 
     * @param string $userId UUID of the user
     * @return int
     */
    public function getUnreadCount($userId) {
        try {
            $sql = "SELECT COUNT(*) FROM notifications 
                    WHERE user_id = ? AND is_read = FALSE 
                    AND (expires_at IS NULL OR expires_at > NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            
            return (int) $stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Delete a notification
     * 
     * @param int $notificationId
     * @param string $userId UUID of the user for security
     * @return bool
     */
    public function delete($notificationId, $userId) {
        try {
            $sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$notificationId, $userId]);
            
        } catch (Exception $e) {
            error_log("Error deleting notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up old notifications for a user (manual cleanup)
     * 
     * @param string $userId UUID of the user
     * @return void
     */
    private function cleanupOldNotifications($userId) {
        try {
            $sql = "DELETE FROM notifications 
                    WHERE user_id = ? 
                    AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM notifications 
                            WHERE user_id = ? 
                            ORDER BY created_at DESC 
                            LIMIT ?
                        ) AS keep_notifications
                    )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId, $userId, $this->maxNotificationsPerUser]);
            
        } catch (Exception $e) {
            error_log("Error cleaning up old notifications: " . $e->getMessage());
        }
    }
    
    /**
     * Clean up expired notifications (run this periodically)
     * 
     * @return int Number of expired notifications deleted
     */
    public function cleanupExpiredNotifications() {
        try {
            $sql = "DELETE FROM notifications WHERE expires_at IS NOT NULL AND expires_at <= NOW()";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("Error cleaning up expired notifications: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Create order-related notification
     * 
     * @param string $userId UUID of the user
     * @param int $subscriptionId
     * @param string $status (confirmed, preparing, out_for_delivery, delivered, etc.)
     * @param array $orderDetails
     * @return int|false
     */
    public function createOrderNotification($userId, $subscriptionId, $status, $orderDetails = []) {
        $messages = [
            'confirmed' => 'Your order has been confirmed.',
            'preparing' => 'Our chefs are preparing your delicious Thai meals.',
            'out_for_delivery' => 'Your order is out for delivery.',
            'delivered' => 'Your order has been delivered. Enjoy your meal!',
            'cancelled' => 'Your order has been cancelled. If you have questions, please contact support.',
        ];
        
        $titles = [
            'confirmed' => 'Order Confirmed 🍽️',
            'preparing' => 'Order Being Prepared 👨‍🍳',
            'out_for_delivery' => 'Out for Delivery 🚗',
            'delivered' => 'Order Delivered ✅',
            'cancelled' => 'Order Cancelled ❌',
        ];
        
        $priorities = [
            'confirmed' => 'medium',
            'preparing' => 'medium',
            'out_for_delivery' => 'high',
            'delivered' => 'high',
            'cancelled' => 'high',
        ];
        
        $data = array_merge([
            'subscription_id' => $subscriptionId,
            'status' => $status
        ], $orderDetails);
        
        return $this->create(
            $userId,
            'order',
            $titles[$status] ?? 'Order Update',
            $messages[$status] ?? 'Your order status has been updated.',
            $data,
            $priorities[$status] ?? 'medium'
        );
    }
    
    /**
     * Create system notification
     * 
     * @param string $userId UUID of the user
     * @param string $title
     * @param string $message
     * @param string $priority
     * @return int|false
     */
    public function createSystemNotification($userId, $title, $message, $priority = 'medium') {
        return $this->create($userId, 'system', $title, $message, null, $priority);
    }
    
    /**
     * Create promotion notification
     * 
     * @param string $userId UUID of the user
     * @param string $title
     * @param string $message
     * @param array $promotionData
     * @param DateTime $expiresAt
     * @return int|false
     */
    public function createPromotionNotification($userId, $title, $message, $promotionData = [], $expiresAt = null) {
        return $this->create($userId, 'promotion', $title, $message, $promotionData, 'medium', $expiresAt);
    }
}