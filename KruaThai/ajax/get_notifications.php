<?php
/**
 * AJAX Endpoint: Get User Notifications
 * File: ajax/get_notifications.php
 */

session_start();
require_once '../config/database.php';
require_once '../NotificationManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$limit = $_GET['limit'] ?? 10;
$unreadOnly = isset($_GET['unread_only']) ? filter_var($_GET['unread_only'], FILTER_VALIDATE_BOOLEAN) : false;
$type = $_GET['type'] ?? null;

try {
    $notificationManager = new NotificationManager($pdo);
    $notifications = $notificationManager->getUserNotifications(
        $_SESSION['user_id'], 
        $unreadOnly, 
        $limit, 
        $type
    );
    echo json_encode(['success' => true, 'notifications' => $notifications]);
} catch (Exception $e) {
    error_log("Error getting notifications: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error', 'notifications' => []]);
}