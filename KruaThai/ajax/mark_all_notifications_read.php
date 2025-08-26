<?php
/**
 * AJAX Endpoint: Mark All Notifications as Read
 * File: ajax/mark_all_notifications_read.php
 */

session_start();
require_once '../config/database.php';
require_once '../NotificationManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $notificationManager = new NotificationManager($pdo);
    $success = $notificationManager->markAllAsRead($_SESSION['user_id']);
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    error_log("Error marking all notifications as read: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}