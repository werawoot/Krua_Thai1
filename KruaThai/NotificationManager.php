<?php
/**
 * Somdul Table - Improved Notification Manager
 * Handles both personal and system-wide notifications efficiently
 */

class NotificationManager {
    private $pdo;
    private $maxPersonalNotificationsPerUser;
    
    public function __construct($pdo, $maxPersonalNotificationsPerUser = 15) {
        $this->pdo = $pdo;
        $this->maxPersonalNotificationsPerUser = $maxPersonalNotificationsPerUser;
    }
    
    /**
     * Create an order notification (convenience method for order-related notifications)
     * 
     * @param string $userId UUID of the user
     * @param string $subscriptionId UUID of the subscription
     * @param string $status Order status (confirmed, preparing, delivered, etc.)
     * @param array $orderDetails Order details (plan_name, total_amount, delivery_date, transaction_id)
     * @return int|false Notification ID or false on failure
     */
    public function createOrderNotification($userId, $subscriptionId, $status, $orderDetails = []) {
        try {
            // Prepare notification content based on status
            $title = '';
            $message = '';
            $priority = 'medium';
            
            switch ($status) {
                case 'confirmed':
                    $title = '🎉 Order Confirmed!';
                    $message = 'Your Thai meal subscription has been confirmed. ';
                    if (isset($orderDetails['plan_name'])) {
                        $message .= "Plan: {$orderDetails['plan_name']}. ";
                    }
                    if (isset($orderDetails['delivery_date'])) {
                        $deliveryDate = date('l, F j, Y', strtotime($orderDetails['delivery_date']));
                        $message .= "First delivery: {$deliveryDate}. ";
                    }
                    if (isset($orderDetails['total_amount'])) {
                        $message .= "Total: \${$orderDetails['total_amount']}. ";
                    }
                    $message .= 'Thank you for choosing Somdul Table!';
                    $priority = 'high';
                    break;
                    
                case 'preparing':
                    $title = '👨‍🍳 Your Meals Are Being Prepared';
                    $message = 'Our chefs are crafting your authentic Thai meals with fresh ingredients. Your order will be ready for delivery soon.';
                    break;
                    
                case 'out_for_delivery':
                    $title = '🚚 Out for Delivery';
                    $message = 'Your Thai meals are on their way! Please be available to receive your delivery.';
                    $priority = 'high';
                    break;
                    
                case 'delivered':
                    $title = '✅ Delivered Successfully';
                    $message = 'Your Thai meals have been delivered. Enjoy your authentic flavors and don\'t forget to rate your experience!';
                    break;
                    
                case 'delayed':
                    $title = '⏰ Delivery Delayed';
                    $message = 'We apologize for the delay in your delivery. Our team is working to get your meals to you as soon as possible.';
                    $priority = 'high';
                    break;
                    
                case 'cancelled':
                    $title = '❌ Order Cancelled';
                    $message = 'Your order has been cancelled. If this was unexpected, please contact our support team.';
                    $priority = 'high';
                    break;
                    
                default:
                    $title = '📋 Order Update';
                    $message = "Your order status has been updated to: {$status}";
                    break;
            }
            
            // Prepare data for the notification
            $notificationData = [
                'subscription_id' => $subscriptionId,
                'order_status' => $status,
                'order_details' => $orderDetails
            ];
            
            // If we have a transaction ID, include it
            if (isset($orderDetails['transaction_id'])) {
                $notificationData['transaction_id'] = $orderDetails['transaction_id'];
            }
            
            // Create the personal notification
            return $this->createPersonalNotification(
                $userId,
                'order',  // notification type
                $title,
                $message,
                $notificationData,
                $priority
            );
            
        } catch (Exception $e) {
            error_log("Order notification creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a personal notification for a specific user
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
    public function createPersonalNotification($userId, $type, $title, $message, $data = null, $priority = 'medium', $expiresAt = null) {
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
                $this->cleanupOldPersonalNotifications($userId);
                return $notificationId;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Personal notification creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a system notification (broadcast to multiple users)
     * 
     * @param string $createdBy Admin user ID
     * @param string $type (system, promotion, announcement, maintenance, general)
     * @param string $title
     * @param string $message
     * @param string $targetAudience (all, customers, active_subscribers, custom)
     * @param array $data Additional data
     * @param string $priority (low, medium, high, urgent)
     * @param DateTime $expiresAt Optional expiration date
     * @param array $targetCriteria Custom targeting criteria for 'custom' audience
     * @return int|false System notification ID or false on failure
     */
    public function createSystemNotification($createdBy, $type, $title, $message, $targetAudience = 'all', $data = null, $priority = 'medium', $expiresAt = null, $targetCriteria = null) {
        try {
            $sql = "INSERT INTO system_notifications (created_by, type, title, message, target_audience, data, priority, expires_at, target_criteria) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                $createdBy,
                $type,
                $title,
                $message,
                $targetAudience,
                $data ? json_encode($data) : null,
                $priority,
                $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null,
                $targetCriteria ? json_encode($targetCriteria) : null
            ]);
            
            return $success ? $this->pdo->lastInsertId() : false;
            
        } catch (Exception $e) {
            error_log("System notification creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all notifications for a user (personal + system)
     * 
     * @param string $userId UUID of the user
     * @param bool $unreadOnly Get only unread notifications
     * @param int $limit Maximum number of notifications to return
     * @param string $type Filter by notification type
     * @return array
     */
    public function getUserNotifications($userId, $unreadOnly = false, $limit = 20, $type = null) {
        try {
            $sql = "SELECT * FROM user_notifications_view WHERE user_id = ?";
            $params = [$userId];
            
            if ($unreadOnly) {
                $sql .= " AND is_read = 0";
            }
            
            if ($type) {
                $sql .= " AND type = ?";
                $params[] = $type;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON data and format
            foreach ($notifications as &$notification) {
                if ($notification['data']) {
                    $notification['data'] = json_decode($notification['data'], true);
                }
                
                // Add source-specific ID handling
                if ($notification['notification_source'] === 'system') {
                    $notification['system_id'] = str_replace('sys_', '', $notification['id']);
                }
            }
            
            return $notifications;
            
        } catch (Exception $e) {
            error_log("Error fetching user notifications: " . $e->getMessage());
            return [];
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
            $sql = "SELECT COUNT(*) FROM user_notifications_view 
                    WHERE user_id = ? AND is_read = 0";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            
            return (int) $stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Mark personal notification as read
     * 
     * @param int $notificationId
     * @param string $userId UUID of the user for security
     * @return bool
     */
    public function markPersonalNotificationAsRead($notificationId, $userId) {
        try {
            $sql = "UPDATE notifications SET is_read = TRUE, read_at = NOW() 
                    WHERE id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$notificationId, $userId]);
            
        } catch (Exception $e) {
            error_log("Error marking personal notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark system notification as read for a user
     * 
     * @param int $systemNotificationId
     * @param string $userId UUID of the user
     * @return bool
     */
    public function markSystemNotificationAsRead($systemNotificationId, $userId) {
        try {
            $sql = "INSERT IGNORE INTO user_system_notification_reads (user_id, system_notification_id) 
                    VALUES (?, ?)";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$userId, $systemNotificationId]);
            
        } catch (Exception $e) {
            error_log("Error marking system notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark notification as read (auto-detects personal vs system)
     * 
     * @param string $notificationId Can be numeric (personal) or "sys_123" (system)
     * @param string $userId UUID of the user
     * @return bool
     */
    public function markAsRead($notificationId, $userId) {
        if (strpos($notificationId, 'sys_') === 0) {
            // System notification
            $systemId = str_replace('sys_', '', $notificationId);
            return $this->markSystemNotificationAsRead($systemId, $userId);
        } else {
            // Personal notification
            return $this->markPersonalNotificationAsRead($notificationId, $userId);
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
            $this->pdo->beginTransaction();
            
            // Mark all personal notifications as read
            $sql1 = "UPDATE notifications SET is_read = TRUE, read_at = NOW() 
                     WHERE user_id = ? AND is_read = FALSE";
            $stmt1 = $this->pdo->prepare($sql1);
            $stmt1->execute([$userId]);
            
            // Mark all unread system notifications as read
            $sql2 = "INSERT IGNORE INTO user_system_notification_reads (user_id, system_notification_id)
                     SELECT ?, sn.id 
                     FROM system_notifications sn
                     LEFT JOIN user_system_notification_reads usnr ON sn.id = usnr.system_notification_id AND usnr.user_id = ?
                     WHERE sn.is_active = TRUE 
                     AND (sn.expires_at IS NULL OR sn.expires_at > NOW())
                     AND usnr.id IS NULL";
            $stmt2 = $this->pdo->prepare($sql2);
            $stmt2->execute([$userId, $userId]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a personal notification
     * 
     * @param int $notificationId
     * @param string $userId UUID of the user for security
     * @return bool
     */
    public function deletePersonalNotification($notificationId, $userId) {
        try {
            $sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$notificationId, $userId]);
            
        } catch (Exception $e) {
            error_log("Error deleting personal notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get system notification statistics for admin
     * 
     * @return array
     */
    public function getSystemNotificationStats() {
        try {
            $stats = [];
            
            // Active system notifications
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM system_notifications WHERE is_active = TRUE");
            $stmt->execute();
            $stats['active_system_notifications'] = $stmt->fetchColumn();
            
            // Total users who can receive notifications
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role != 'admin' AND status = 'active'");
            $stmt->execute();
            $stats['target_users'] = $stmt->fetchColumn();
            
            // System notifications sent in last 30 days
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM system_notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $stats['recent_system_notifications'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting system notification stats: " . $e->getMessage());
            return [
                'active_system_notifications' => 0,
                'target_users' => 0,
                'recent_system_notifications' => 0
            ];
        }
    }
    
    /**
     * Clean up old personal notifications for a user
     * 
     * @param string $userId UUID of the user
     * @return void
     */
    private function cleanupOldPersonalNotifications($userId) {
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
            $stmt->execute([$userId, $userId, $this->maxPersonalNotificationsPerUser]);
            
        } catch (Exception $e) {
            error_log("Error cleaning up old personal notifications: " . $e->getMessage());
        }
    }
    
    /**
     * Clean up expired notifications (run this periodically)
     * 
     * @return int Number of expired notifications cleaned up
     */
    public function cleanupExpiredNotifications() {
        try {
            $deletedCount = 0;
            
            // Clean up expired personal notifications
            $sql1 = "DELETE FROM notifications WHERE expires_at IS NOT NULL AND expires_at <= NOW()";
            $stmt1 = $this->pdo->prepare($sql1);
            $stmt1->execute();
            $deletedCount += $stmt1->rowCount();
            
            // Deactivate expired system notifications (don't delete, keep for records)
            $sql2 = "UPDATE system_notifications SET is_active = FALSE WHERE expires_at IS NOT NULL AND expires_at <= NOW() AND is_active = TRUE";
            $stmt2 = $this->pdo->prepare($sql2);
            $stmt2->execute();
            
            return $deletedCount;
            
        } catch (Exception $e) {
            error_log("Error cleaning up expired notifications: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Estimate total reach of a system notification
     * 
     * @param string $targetAudience
     * @param array $targetCriteria
     * @return int Estimated number of users who will receive the notification
     */
    public function estimateSystemNotificationReach($targetAudience, $targetCriteria = null) {
        try {
            $sql = "SELECT COUNT(*) FROM users WHERE role != 'admin'";
            $params = [];
            
            switch ($targetAudience) {
                case 'customers':
                    $sql .= " AND role = 'customer'";
                    break;
                case 'active_subscribers':
                    $sql .= " AND status = 'active'";
                    break;
                case 'custom':
                    // Add custom criteria logic here if needed
                    break;
                // 'all' uses base query
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return (int) $stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Error estimating notification reach: " . $e->getMessage());
            return 0;
        }
    }
}
?>