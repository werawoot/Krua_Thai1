<?php
/**
 * Somdul Table - Corrected Notification Manager
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
     * Create a personal notification for a specific user
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
     * Get all notifications for a user (personal + system) - CORRECTED
     */
    public function getUserNotifications($userId, $unreadOnly = false, $limit = 20, $type = null) {
        try {
            $notifications = [];
            
            // Get personal notifications
            $personalSql = "SELECT 
                            id, 
                            type, 
                            title, 
                            message, 
                            created_at, 
                            is_read, 
                            data,
                            'personal' as notification_source
                        FROM notifications 
                        WHERE user_id = ?";
            
            $personalParams = [$userId];
            
            if ($unreadOnly) {
                $personalSql .= " AND is_read = 0";
            }
            
            if ($type) {
                $personalSql .= " AND type = ?";
                $personalParams[] = $type;
            }
            
            $personalSql .= " ORDER BY created_at DESC";
            
            $stmt = $this->pdo->prepare($personalSql);
            $stmt->execute($personalParams);
            $personalNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get system notifications with read status
            $systemSql = "SELECT 
                            CONCAT('sys_', sn.id) as id,
                            sn.id as system_id,
                            sn.type, 
                            sn.title, 
                            sn.message, 
                            sn.created_at,
                            CASE WHEN usnr.read_at IS NOT NULL THEN 1 ELSE 0 END as is_read,
                            sn.data,
                            'system' as notification_source,
                            sn.target_audience
                        FROM system_notifications sn
                        LEFT JOIN user_system_notification_reads usnr 
                            ON sn.id = usnr.system_notification_id AND usnr.user_id = ?
                        WHERE sn.is_active = 1 
                            AND (sn.expires_at IS NULL OR sn.expires_at > NOW())";
            
            $systemParams = [$userId];
            
            if ($unreadOnly) {
                $systemSql .= " AND usnr.read_at IS NULL";
            }
            
            if ($type) {
                $systemSql .= " AND sn.type = ?";
                $systemParams[] = $type;
            }
            
            $systemSql .= " ORDER BY sn.created_at DESC";
            
            $stmt = $this->pdo->prepare($systemSql);
            $stmt->execute($systemParams);
            $systemNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filter system notifications by target audience
            $systemNotifications = array_filter($systemNotifications, function($notification) use ($userId) {
                return $this->isNotificationTargetedToUser($notification, $userId);
            });
            
            // Merge notifications
            $notifications = array_merge($personalNotifications, $systemNotifications);
            
            // Sort by created_at descending
            usort($notifications, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            // Limit results
            $notifications = array_slice($notifications, 0, $limit);
            
            // Process data field
            foreach ($notifications as &$notification) {
                if ($notification['data']) {
                    $notification['data'] = json_decode($notification['data'], true);
                }
            }
            
            return $notifications;
            
        } catch (Exception $e) {
            error_log("Error fetching user notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if notification is targeted to user
     */
    private function isNotificationTargetedToUser($notification, $userId) {
        $targetAudience = $notification['target_audience'];
        
        if ($targetAudience === 'all') {
            return true;
        }
        
        if ($targetAudience === 'customers') {
            // Check if user has any subscriptions
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetchColumn() > 0;
        }
        
        if ($targetAudience === 'active_subscribers') {
            // Check if user has active subscriptions
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE user_id = ? AND status = 'active'");
            $stmt->execute([$userId]);
            return $stmt->fetchColumn() > 0;
        }
        
        return false;
    }
    
    /**
     * Get unread notification count for a user - CORRECTED
     */
    public function getUnreadCount($userId) {
        try {
            $totalCount = 0;
            
            // Count unread personal notifications
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$userId]);
            $totalCount += (int) $stmt->fetchColumn();
            
            // Count unread system notifications
            $systemSql = "SELECT COUNT(*) FROM system_notifications sn
                         LEFT JOIN user_system_notification_reads usnr 
                             ON sn.id = usnr.system_notification_id AND usnr.user_id = ?
                         WHERE sn.is_active = 1 
                             AND (sn.expires_at IS NULL OR sn.expires_at > NOW())
                             AND usnr.read_at IS NULL";
            
            $stmt = $this->pdo->prepare($systemSql);
            $stmt->execute([$userId]);
            $systemCount = (int) $stmt->fetchColumn();
            
            // Filter system notifications by target audience (this is simplified)
            // In a production system, you might want to cache this or optimize further
            if ($systemCount > 0) {
                $systemNotifications = $this->getSystemNotifications($userId, true);
                $totalCount += count($systemNotifications);
            }
            
            return $totalCount;
            
        } catch (Exception $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get system notifications for a user
     */
    private function getSystemNotifications($userId, $unreadOnly = false) {
        $systemSql = "SELECT 
                        sn.id,
                        sn.target_audience,
                        CASE WHEN usnr.read_at IS NOT NULL THEN 1 ELSE 0 END as is_read
                    FROM system_notifications sn
                    LEFT JOIN user_system_notification_reads usnr 
                        ON sn.id = usnr.system_notification_id AND usnr.user_id = ?
                    WHERE sn.is_active = 1 
                        AND (sn.expires_at IS NULL OR sn.expires_at > NOW())";
        
        if ($unreadOnly) {
            $systemSql .= " AND usnr.read_at IS NULL";
        }
        
        $stmt = $this->pdo->prepare($systemSql);
        $stmt->execute([$userId]);
        $systemNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Filter by target audience
        return array_filter($systemNotifications, function($notification) use ($userId) {
            return $this->isNotificationTargetedToUser($notification, $userId);
        });
    }
    
    /**
     * Mark personal notification as read
     */
    public function markPersonalNotificationAsRead($notificationId, $userId) {
        try {
            $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() 
                    WHERE id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$notificationId, $userId]);
            
        } catch (Exception $e) {
            error_log("Error marking personal notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark system notification as read for a user - CORRECTED
     */
    public function markSystemNotificationAsRead($systemNotificationId, $userId) {
        try {
            // Remove 'sys_' prefix if it exists
            $systemId = str_replace('sys_', '', $systemNotificationId);
            
            $sql = "INSERT INTO user_system_notification_reads (user_id, system_notification_id, read_at) 
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE read_at = NOW()";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$userId, $systemId]);
            
        } catch (Exception $e) {
            error_log("Error marking system notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark notification as read (auto-detects personal vs system) - CORRECTED
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
     */
    public function markAllAsRead($userId) {
        try {
            $this->pdo->beginTransaction();
            
            // Mark all personal notifications as read
            $sql1 = "UPDATE notifications SET is_read = 1, read_at = NOW() 
                     WHERE user_id = ? AND is_read = 0";
            $stmt1 = $this->pdo->prepare($sql1);
            $stmt1->execute([$userId]);
            
            // Mark all unread system notifications as read
            $sql2 = "INSERT INTO user_system_notification_reads (user_id, system_notification_id, read_at)
                     SELECT ?, sn.id, NOW()
                     FROM system_notifications sn
                     LEFT JOIN user_system_notification_reads usnr ON sn.id = usnr.system_notification_id AND usnr.user_id = ?
                     WHERE sn.is_active = 1 
                     AND (sn.expires_at IS NULL OR sn.expires_at > NOW())
                     AND usnr.id IS NULL
                     ON DUPLICATE KEY UPDATE read_at = NOW()";
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
     * Clean up old personal notifications for a user
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
     * Clean up expired notifications
     */
    public function cleanupExpiredNotifications() {
        try {
            $deletedCount = 0;
            
            // Clean up expired personal notifications
            $sql1 = "DELETE FROM notifications WHERE expires_at IS NOT NULL AND expires_at <= NOW()";
            $stmt1 = $this->pdo->prepare($sql1);
            $stmt1->execute();
            $deletedCount += $stmt1->rowCount();
            
            // Deactivate expired system notifications
            $sql2 = "UPDATE system_notifications SET is_active = 0 WHERE expires_at IS NOT NULL AND expires_at <= NOW() AND is_active = 1";
            $stmt2 = $this->pdo->prepare($sql2);
            $stmt2->execute();
            
            return $deletedCount;
            
        } catch (Exception $e) {
            error_log("Error cleaning up expired notifications: " . $e->getMessage());
            return 0;
        }
    }
}