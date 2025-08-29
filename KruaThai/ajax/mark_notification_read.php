<?php
/**
 * Somdul Table - Mark Notification as Read AJAX (CORRECTED)
 * File: ajax/mark_notification_read.php
 */

session_start();
require_once '../config/database.php';
require_once '../NotificationManager.php';

header('Content-Type: application/json');

// Debug logging
error_log("Mark notification read request received");

if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$notificationId = $input['notification_id'] ?? null;
$notificationSource = $input['notification_source'] ?? 'personal';

error_log("Notification ID: " . $notificationId);
error_log("Notification Source: " . $notificationSource);
error_log("User ID: " . $_SESSION['user_id']);

if (!$notificationId) {
    error_log("No notification ID provided");
    echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
    exit();
}

try {
    $notificationManager = new NotificationManager($pdo);
    $userId = $_SESSION['user_id'];
    
    // Determine if this is a system notification
    $isSystemNotification = ($notificationSource === 'system') || (strpos($notificationId, 'sys_') === 0);
    
    if ($isSystemNotification) {
        // Handle system notification
        $systemId = str_replace('sys_', '', $notificationId);
        error_log("Marking system notification as read. System ID: " . $systemId);
        $success = $notificationManager->markSystemNotificationAsRead($systemId, $userId);
    } else {
        // Handle personal notification
        error_log("Marking personal notification as read. Notification ID: " . $notificationId);
        $success = $notificationManager->markPersonalNotificationAsRead($notificationId, $userId);
    }
    
    error_log("Mark as read result: " . ($success ? 'success' : 'failed'));
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read']);
    }
    
} catch (Exception $e) {
    error_log("Mark notification read error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}