<?php
/**
 * AJAX Endpoint: Mark Single Notification as Read
 * File: ajax/mark_notification_read.php
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

$input = json_decode(file_get_contents('php://input'), true);
$notificationId = $input['notification_id'] ?? null;

if (!$notificationId) {
    echo json_encode(['success' => false, 'error' => 'Missing notification ID']);
    exit;
}

try {
    $notificationManager = new NotificationManager($pdo);
    $success = $notificationManager->markAsRead($notificationId, $_SESSION['user_id']);
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    error_log("Error marking notification as read: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}