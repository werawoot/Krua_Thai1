<?php
/**
 * AJAX Endpoint: Get Unread Notification Count
 * File: ajax/get_notification_count.php
 */

session_start();
require_once '../config/database.php';
require_once '../NotificationManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $notificationManager = new NotificationManager($pdo);
    $count = $notificationManager->getUnreadCount($_SESSION['user_id']);
    echo json_encode(['success' => true, 'count' => $count]);
} catch (Exception $e) {
    error_log("Error getting notification count: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error', 'count' => 0]);
}