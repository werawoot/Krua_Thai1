<?php
/**
 * Somdul Table - Admin Notifications Management (Improved System)
 * File: admin/notifications.php
 * Description: Admin interface for creating both personal and system notifications
 */

require_once '../config/database.php';
require_once '../NotificationManager.php';

$notificationManager = new NotificationManager($pdo);
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    try {
        $notification_scope = $_POST['notification_scope']; // 'personal' or 'system'
        $type = trim($_POST['type']);
        $title = trim($_POST['title']);
        $message = trim($_POST['message']);
        $priority = $_POST['priority'];
        $expires_at = !empty($_POST['expires_at']) ? new DateTime($_POST['expires_at']) : null;
        
        // Additional data for promotions
        $data = null;
        if ($type === 'promotion' && !empty($_POST['promo_code'])) {
            $data = [
                'promo_code' => $_POST['promo_code'],
                'discount' => $_POST['discount'] ?? null,
                'promo_url' => $_POST['promo_url'] ?? null
            ];
        }
        
        if ($notification_scope === 'system') {
            // System notification (efficient broadcast)
            $target_audience = $_POST['target_audience'];
            
            // Get admin user ID (assuming admin is logged in)
            $admin_id = $_SESSION['user_id'] ?? null;
            if (!$admin_id) {
                // Fallback: get first admin user
                $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
                $stmt->execute();
                $admin_id = $stmt->fetchColumn();
            }
            
            $result = $notificationManager->createSystemNotification(
                $admin_id,
                $type,
                $title,
                $message,
                $target_audience,
                $data,
                $priority,
                $expires_at
            );
            
            if ($result) {
                $estimated_reach = $notificationManager->estimateSystemNotificationReach($target_audience);
                $success_message = "System notification created successfully! Estimated reach: {$estimated_reach} users.";
            } else {
                $error_message = "Failed to create system notification.";
            }
            
        } else {
            // Personal notifications (individual targeting)
            $selected_users = isset($_POST['selected_users']) ? $_POST['selected_users'] : [];
            
            if (empty($selected_users)) {
                $error_message = "Please select at least one user for personal notifications.";
            } else {
                $sent_count = 0;
                foreach ($selected_users as $user_id) {
                    $result = $notificationManager->createPersonalNotification(
                        $user_id,
                        $type,
                        $title,
                        $message,
                        $data,
                        $priority,
                        $expires_at
                    );
                    
                    if ($result) $sent_count++;
                }
                
                $success_message = "Successfully sent personal notifications to {$sent_count} users!";
            }
        }
        
    } catch (Exception $e) {
        $error_message = "Error sending notification: " . $e->getMessage();
        error_log("Notification send error: " . $e->getMessage());
    }
}

// Get recent notifications for display (both personal and system)
try {
    // Recent personal notifications
    $stmt = $pdo->prepare("
        SELECT n.*, u.name as user_name, u.email as user_email,
               'personal' as notification_scope
        FROM notifications n 
        LEFT JOIN users u ON n.user_id = u.id 
        ORDER BY n.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_personal = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent system notifications
    $stmt = $pdo->prepare("
        SELECT sn.*, u.name as created_by_name,
               'system' as notification_scope,
               (SELECT COUNT(*) FROM users WHERE role != 'admin' AND status = 'active') as potential_reach
        FROM system_notifications sn
        LEFT JOIN users u ON sn.created_by = u.id
        ORDER BY sn.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_system = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine and sort by creation date
    $recent_notifications = array_merge($recent_personal, $recent_system);
    usort($recent_notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $recent_notifications = array_slice($recent_notifications, 0, 10);
    
} catch (Exception $e) {
    $recent_notifications = [];
    error_log("Error fetching recent notifications: " . $e->getMessage());
}

// Get statistics
try {
    $system_stats = $notificationManager->getSystemNotificationStats();
    $total_users = $system_stats['target_users'];
    $active_system_notifications = $system_stats['active_system_notifications'];
    $recent_system_notifications = $system_stats['recent_system_notifications'];
    
    // Personal notification stats
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_personal FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $recent_personal_notifications = $stmt->fetchColumn();
} catch (Exception $e) {
    $total_users = 0;
    $active_system_notifications = 0;
    $recent_system_notifications = 0;
    $recent_personal_notifications = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Somdul Table Admin</title>
    <link href="https://ydpschool.com/fonts/" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom Admin Styles -->
    <style>
        @import url('https://ydpschool.com/fonts/');
        /* BaticaSans Font Import */
        @font-face {
            font-family: 'BaticaSans';
            src: url('../Font/BaticaSans-Regular.woff2') format('woff2'),
                url('../Font/BaticaSans-Regular.woff') format('woff'),
                url('../Font/BaticaSans-Regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        /* CSS Variables */
        :root {
            --brown: #bd9379;
            --white: #ffffff;
            --cream: #ece8e1;
            --sage: #adb89d;
            --curry: #cf723a;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #d4c4b8;
            --shadow-soft: 0 4px 12px rgba(189, 147, 121, 0.15);
            --shadow-medium: 0 8px 24px rgba(189, 147, 121, 0.25);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--brown) 0%, var(--curry) 100%);
            color: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-medium);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border-left: 4px solid var(--brown);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--brown);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .main-form {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--cream);
            padding-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--brown);
            box-shadow: 0 0 0 3px rgba(189, 147, 121, 0.1);
        }

        .form-control.textarea {
            min-height: 120px;
            resize: vertical;
        }

        .radio-group {
            display: flex;
            gap: 2rem;
            margin-bottom: 1rem;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--brown);
        }

        .user-search {
            display: none;
            margin-top: 1rem;
        }

        .user-search.show {
            display: block;
        }

        .search-input {
            position: relative;
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--white);
            border: 2px solid var(--border-light);
            border-top: none;
            border-radius: 0 0 var(--radius-sm) var(--radius-sm);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .search-results.show {
            display: block;
        }

        .search-result-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--cream);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-result-item:hover {
            background: var(--cream);
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .selected-users {
            margin-top: 1rem;
        }

        .selected-user-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--sage);
            color: var(--white);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            margin: 0.2rem 0.5rem 0.2rem 0;
        }

        .selected-user-tag .remove {
            cursor: pointer;
            font-weight: bold;
        }

        .promotion-fields {
            display: none;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .promotion-fields.show {
            display: grid;
        }

        .btn {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--brown);
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            background: #a8855f;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: var(--sage);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: #9ba788;
        }

        .sidebar-panel {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .sidebar-panel-header {
            background: var(--cream);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
        }

        .sidebar-panel-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .sidebar-panel-content {
            padding: 1.5rem;
            max-height: 600px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 1rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            background: var(--white);
        }

        .notification-item:last-child {
            margin-bottom: 0;
        }

        .notification-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .notification-type {
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .notification-type.order {
            background: #e3f2fd;
            color: #1976d2;
        }

        .notification-type.system {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .notification-type.promotion {
            background: #fff3e0;
            color: #f57c00;
        }

        .notification-type.general {
            background: #e8f5e8;
            color: #388e3c;
        }

        .notification-title {
            font-weight: 600;
            color: var(--text-dark);
            flex: 1;
        }

        .notification-time {
            font-size: 0.8rem;
            color: var(--text-gray);
        }

        .notification-message {
            color: var(--text-gray);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .notification-scope-badge {
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 0.5rem;
        }

        .notification-scope-badge.system {
            background: #e8f5e8;
            color: #388e3c;
        }

        .notification-scope-badge.personal {
            background: #e3f2fd;
            color: #1976d2;
        }

        .notification-reach {
            font-size: 0.8rem;
            color: var(--sage);
            font-weight: 500;
        }

        .reach-estimate {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: rgba(173, 184, 157, 0.1);
            border-radius: var(--radius-sm);
            border-left: 3px solid var(--sage);
        }

        .system-targeting {
            display: block;
        }

        .personal-targeting {
            display: none;
        }

        .personal-targeting.show {
            display: block;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1.5rem;
            border: 2px solid;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .sidebar-panel {
                order: -1;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .main-form {
                padding: 1.5rem;
            }

            .radio-group {
                flex-direction: column;
                gap: 1rem;
            }

            .promotion-fields {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">📢 Notifications</h1>
            <p class="page-subtitle">Send notifications to your customers</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($total_users) ?></div>
                <div class="stat-label">Target Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($active_system_notifications) ?></div>
                <div class="stat-label">Active System Notifications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($recent_system_notifications) ?></div>
                <div class="stat-label">System Notifications (30 days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($recent_personal_notifications) ?></div>
                <div class="stat-label">Personal Notifications (30 days)</div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- Main Form -->
            <div class="main-form">
                <form method="POST" action="" id="notificationForm">
                    <!-- Notification Scope -->
                    <div class="form-section">
                        <h3 class="section-title">Notification Scope</h3>
                        
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="notification_scope" value="system" checked>
                                <span>System Notification (Broadcast)</span>
                                <small style="display: block; color: var(--text-gray); margin-top: 0.25rem;">
                                    Efficient broadcast to groups of users. No duplicate storage.
                                </small>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="notification_scope" value="personal">
                                <span>Personal Notifications (Individual)</span>
                                <small style="display: block; color: var(--text-gray); margin-top: 0.25rem;">
                                    Individual notifications to specific users.
                                </small>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Notification Details -->
                    <div class="form-section">
                        <h3 class="section-title">Notification Details</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Notification Type</label>
                            <select name="type" class="form-control" id="notificationType" required>
                                <option value="general">General</option>
                                <option value="promotion">Promotion</option>
                                <option value="system">System</option>
                                <option value="delivery">Delivery</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" placeholder="Enter notification title" required maxlength="255">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control textarea" placeholder="Enter your message here..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>

                        <!-- Promotion-specific fields -->
                        <div class="promotion-fields" id="promotionFields">
                            <div class="form-group">
                                <label class="form-label">Promo Code (optional)</label>
                                <input type="text" name="promo_code" class="form-control" placeholder="SAVE20">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Discount (optional)</label>
                                <input type="text" name="discount" class="form-control" placeholder="20% off">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Promotion URL (optional)</label>
                                <input type="url" name="promo_url" class="form-control" placeholder="https://...">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Expires At (optional)</label>
                                <input type="datetime-local" name="expires_at" class="form-control">
                            </div>
                        </div>
                    </div>

                    <!-- Recipients Section -->
                    <div class="form-section">
                        <h3 class="section-title">Recipients</h3>
                        
                        <!-- System Notification Targeting -->
                        <div class="system-targeting" id="systemTargeting">
                            <div class="form-group">
                                <label class="form-label">Target Audience</label>
                                <select name="target_audience" class="form-control" id="targetAudience">
                                    <option value="all">All Users (<?= number_format($total_users) ?> users)</option>
                                    <option value="customers">Customers Only</option>
                                    <option value="active_subscribers">Active Subscribers Only</option>
                                </select>
                            </div>
                            <div class="reach-estimate">
                                <small style="color: var(--sage); font-weight: 600;">
                                    📊 Estimated reach: <span id="reachEstimate"><?= number_format($total_users) ?></span> users
                                </small>
                            </div>
                        </div>
                        
                        <!-- Personal Notification Targeting -->
                        <div class="personal-targeting" id="personalTargeting" style="display: none;">
                            <div class="search-input">
                                <input type="text" id="userSearchInput" class="form-control" placeholder="Search users by name or email...">
                                <div class="search-results" id="searchResults"></div>
                            </div>
                            <div class="selected-users" id="selectedUsers"></div>
                        </div>
                    </div>

                    <button type="submit" name="send_notification" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Send Notification
                    </button>
                </form>
            </div>

            <!-- Recent Notifications Sidebar -->
            <div class="sidebar-panel">
                <div class="sidebar-panel-header">
                    <h3 class="sidebar-panel-title">Recent Notifications</h3>
                </div>
                <div class="sidebar-panel-content">
                    <?php if (empty($recent_notifications)): ?>
                        <p style="text-align: center; color: var(--text-gray); font-style: italic;">No recent notifications</p>
                    <?php else: ?>
                        <?php foreach ($recent_notifications as $notification): ?>
                                <div class="notification-item">
                                    <div class="notification-header">
                                        <span class="notification-type <?= htmlspecialchars($notification['type']) ?>">
                                            <?= htmlspecialchars($notification['type']) ?>
                                        </span>
                                        <span class="notification-scope-badge <?= $notification['notification_scope'] ?>">
                                            <?= $notification['notification_scope'] === 'system' ? 'SYSTEM' : 'PERSONAL' ?>
                                        </span>
                                        <span class="notification-title"><?= htmlspecialchars($notification['title']) ?></span>
                                        <span class="notification-time">
                                            <?= date('M j, g:i A', strtotime($notification['created_at'])) ?>
                                        </span>
                                    </div>
                                    <div class="notification-message">
                                        <?= htmlspecialchars(substr($notification['message'], 0, 100)) ?><?= strlen($notification['message']) > 100 ? '...' : '' ?>
                                    </div>
                                    <?php if ($notification['notification_scope'] === 'personal' && isset($notification['user_name'])): ?>
                                        <div class="notification-user">
                                            To: <?= htmlspecialchars($notification['user_name']) ?> (<?= htmlspecialchars($notification['user_email']) ?>)
                                        </div>
                                    <?php elseif ($notification['notification_scope'] === 'system'): ?>
                                        <div class="notification-reach">
                                            Target: <?= htmlspecialchars($notification['target_audience'] ?? 'all') ?>
                                            <?php if (isset($notification['potential_reach'])): ?>
                                                | Reach: <?= number_format($notification['potential_reach']) ?> users
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedUserIds = new Set();
        let searchTimeout;

        document.addEventListener('DOMContentLoaded', function() {
            const notificationScopeRadios = document.querySelectorAll('input[name="notification_scope"]');
            const systemTargeting = document.getElementById('systemTargeting');
            const personalTargeting = document.getElementById('personalTargeting');
            const targetAudienceSelect = document.getElementById('targetAudience');
            const reachEstimate = document.getElementById('reachEstimate');
            const userSearchInput = document.getElementById('userSearchInput');
            const searchResults = document.getElementById('searchResults');
            const selectedUsers = document.getElementById('selectedUsers');
            const notificationType = document.getElementById('notificationType');
            const promotionFields = document.getElementById('promotionFields');

            // Handle notification scope change
            notificationScopeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'system') {
                        systemTargeting.style.display = 'block';
                        personalTargeting.classList.remove('show');
                        clearSelectedUsers();
                    } else {
                        systemTargeting.style.display = 'none';
                        personalTargeting.classList.add('show');
                    }
                });
            });

            // Handle target audience change (for system notifications)
            if (targetAudienceSelect && reachEstimate) {
                targetAudienceSelect.addEventListener('change', function() {
                    updateReachEstimate(this.value);
                });
            }

            // Handle notification type change
            if (notificationType && promotionFields) {
                notificationType.addEventListener('change', function() {
                    if (this.value === 'promotion') {
                        promotionFields.classList.add('show');
                    } else {
                        promotionFields.classList.remove('show');
                    }
                });
            }

            // User search functionality (for personal notifications)
            if (userSearchInput) {
                let searchTimeout;
                userSearchInput.addEventListener('input', function() {
                    const query = this.value.trim();
                    
                    clearTimeout(searchTimeout);
                    
                    if (query.length < 2) {
                        if (searchResults) searchResults.classList.remove('show');
                        return;
                    }
                    
                    searchTimeout = setTimeout(() => {
                        searchUsers(query);
                    }, 300);
                });
            }

            // Close search results when clicking outside
            document.addEventListener('click', function(e) {
                if (searchResults && !e.target.closest('.search-input')) {
                    searchResults.classList.remove('show');
                }
            });

            // Form validation
            document.getElementById('notificationForm').addEventListener('submit', function(e) {
                const notificationScope = document.querySelector('input[name="notification_scope"]:checked').value;
                
                if (notificationScope === 'personal' && selectedUserIds.size === 0) {
                    e.preventDefault();
                    alert('Please select at least one user for personal notifications.');
                    return;
                }

                // Confirm before sending
                let confirmMessage;
                if (notificationScope === 'system') {
                    const targetAudience = targetAudienceSelect.value;
                    const estimatedReach = reachEstimate.textContent;
                    confirmMessage = `Are you sure you want to send this system notification?\n\nTarget: ${targetAudience}\nEstimated reach: ${estimatedReach} users`;
                } else {
                    const userCount = selectedUserIds.size;
                    confirmMessage = `Are you sure you want to send personal notifications to ${userCount} users?`;
                }
                
                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                }
            });

            function updateReachEstimate(targetAudience) {
                // You could make an AJAX call here to get exact counts
                // For now, using the static total_users count
                const totalUsers = <?= $total_users ?>;
                let estimate = totalUsers;
                
                switch (targetAudience) {
                    case 'customers':
                        estimate = Math.floor(totalUsers * 0.8); // Rough estimate
                        break;
                    case 'active_subscribers':
                        estimate = Math.floor(totalUsers * 0.6); // Rough estimate
                        break;
                }
                
                if (reachEstimate) {
                    reachEstimate.textContent = estimate.toLocaleString();
                }
            }

            function searchUsers(query) {
                fetch(`ajax/search_users.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displaySearchResults(data.users);
                        } else {
                            console.error('Search failed:', data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                    });
            }

            function displaySearchResults(users) {
                if (!searchResults) return;
                
                if (users.length === 0) {
                    searchResults.innerHTML = '<div class="search-result-item">No users found</div>';
                } else {
                    searchResults.innerHTML = users.map(user => `
                        <div class="search-result-item" data-user-id="${user.id}" data-user-name="${user.name}" data-user-email="${user.email}">
                            <i class="fas fa-user"></i>
                            <span>${user.name} (${user.email})</span>
                        </div>
                    `).join('');

                    // Add click handlers
                    searchResults.querySelectorAll('.search-result-item[data-user-id]').forEach(item => {
                        item.addEventListener('click', function() {
                            const userId = this.dataset.userId;
                            const userName = this.dataset.userName;
                            const userEmail = this.dataset.userEmail;
                            
                            if (!selectedUserIds.has(userId)) {
                                addSelectedUser(userId, userName, userEmail);
                            }
                            
                            userSearchInput.value = '';
                            searchResults.classList.remove('show');
                        });
                    });
                }
                
                searchResults.classList.add('show');
            }

            function addSelectedUser(userId, userName, userEmail) {
                selectedUserIds.add(userId);
                
                if (selectedUsers) {
                    const tag = document.createElement('span');
                    tag.className = 'selected-user-tag';
                    tag.innerHTML = `
                        ${userName} 
                        <span class="remove" onclick="removeSelectedUser('${userId}')">&times;</span>
                        <input type="hidden" name="selected_users[]" value="${userId}">
                    `;
                    
                    selectedUsers.appendChild(tag);
                }
            }

            window.removeSelectedUser = function(userId) {
                selectedUserIds.delete(userId);
                
                if (selectedUsers) {
                    const tags = selectedUsers.querySelectorAll('.selected-user-tag');
                    tags.forEach(tag => {
                        const input = tag.querySelector('input[type="hidden"]');
                        if (input && input.value === userId) {
                            tag.remove();
                        }
                    });
                }
            };

            function clearSelectedUsers() {
                selectedUserIds.clear();
                if (selectedUsers) {
                    selectedUsers.innerHTML = '';
                }
            }
        });
    </script>
</body>
</html>