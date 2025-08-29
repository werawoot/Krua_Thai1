<?php
/**
 * Somdul Table - Mark Notification as Read AJAX (Fixed for System Notifications)
 * File: ajax/mark_notification_read.php
 */

session_start();
require_once '../config/database.php';
require_once '../NotificationManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$notificationId = $input['notification_id'] ?? null;
$notificationSource = $input['notification_source'] ?? 'personal'; // 'personal' or 'system'

if (!$notificationId) {
    echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
    exit();
}

try {
    $notificationManager = new NotificationManager($pdo);
    
    if ($notificationSource === 'system' || strpos($notificationId, 'sys_') === 0) {
        // Handle system notification
        $systemId = str_replace('sys_', '', $notificationId);
        $success = $notificationManager->markSystemNotificationAsRead($systemId, $_SESSION['user_id']);
    } else {
        // Handle personal notification
        $success = $notificationManager->markPersonalNotificationAsRead($notificationId, $_SESSION['user_id']);
    }
    
    echo json_encode(['success' => $success]);
    
} catch (Exception $e) {
    error_log("Mark notification read error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}