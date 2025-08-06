<?php
/**
 * Somdul Table - Complete Notification System
 * File: notifications.php
 * Description: Facebook-style notifications with Somdul Table theme
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php?redirect=notifications.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = "";

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    ob_clean(); // ป้องกัน PHP error ปน JSON
    
    $action = $_POST['action'];
    $response = ['success' => false, 'errors' => []];

    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        switch ($action) {
            case 'get_notifications':
                $page = intval($_POST['page'] ?? 1);
                $limit = intval($_POST['limit'] ?? 10);
                $filter = $_POST['filter'] ?? 'all';
                $offset = ($page - 1) * $limit;
                
                $where_conditions = ["user_id = ?"];
                $params = [$user_id];
                
                if ($filter === 'unread') {
                    $where_conditions[] = "read_at IS NULL";
                } elseif ($filter === 'read') {
                    $where_conditions[] = "read_at IS NOT NULL";
                }
                
                $where_clause = implode(' AND ', $where_conditions);
                
                // Get notifications
                $stmt = $pdo->prepare("
                    SELECT id, type, title, message, read_at, created_at, priority, avatar_url, action_url
                    FROM notifications 
                    WHERE $where_clause
                    ORDER BY created_at DESC 
                    LIMIT ? OFFSET ?
                ");
                $params[] = $limit;
                $params[] = $offset;
                $stmt->execute($params);
                $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get total count
                $count_stmt = $pdo->prepare("
                    SELECT COUNT(*) as total 
                    FROM notifications 
                    WHERE $where_clause
                ");
                $count_params = array_slice($params, 0, -2);
                $count_stmt->execute($count_params);
                $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                $response['success'] = true;
                $response['notifications'] = $notifications;
                $response['total'] = intval($total);
                $response['page'] = $page;
                $response['total_pages'] = ceil($total / $limit);
                break;
                
            case 'get_unread_count':
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM notifications 
                    WHERE user_id = ? AND read_at IS NULL
                ");
                $stmt->execute([$user_id]);
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                $response['success'] = true;
                $response['count'] = intval($count);
                break;
                
            case 'mark_as_read':
                $notification_id = $_POST['notification_id'] ?? null;
                if ($notification_id) {
                    $stmt = $pdo->prepare("
                        UPDATE notifications 
                        SET read_at = NOW() 
                        WHERE id = ? AND user_id = ? AND read_at IS NULL
                    ");
                    $stmt->execute([$notification_id, $user_id]);
                    $response['success'] = true;
                    $response['message'] = "Notification marked as read";
                    $response['affected_rows'] = $stmt->rowCount();
                } else {
                    $response['errors'][] = "Invalid notification ID";
                }
                break;
                
            case 'mark_all_read':
                $stmt = $pdo->prepare("
                    UPDATE notifications 
                    SET read_at = NOW() 
                    WHERE user_id = ? AND read_at IS NULL
                ");
                $stmt->execute([$user_id]);
                $affected = $stmt->rowCount();
                $response['success'] = true;
                $response['message'] = "Marked $affected notifications as read";
                $response['affected_rows'] = $affected;
                break;
                
            case 'delete_notification':
                $notification_id = $_POST['notification_id'] ?? null;
                if ($notification_id) {
                    $stmt = $pdo->prepare("
                        DELETE FROM notifications 
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([$notification_id, $user_id]);
                    $response['success'] = true;
                    $response['message'] = "Notification deleted";
                    $response['affected_rows'] = $stmt->rowCount();
                } else {
                    $response['errors'][] = "Invalid notification ID";
                }
                break;
                
            case 'create_test_notification':
                // สร้าง notification ทดสอบ
                $notification_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
                
                $test_notifications = [
                    [
                        'type' => 'order_update',
                        'title' => '🍜 Order Confirmed!',
                        'message' => 'Your Pad Thai order has been confirmed and is being prepared by our kitchen.',
                        'priority' => 'high'
                    ],
                    [
                        'type' => 'delivery',
                        'title' => '🚚 Out for Delivery',
                        'message' => 'Your Thai meal is on the way! Expected delivery in 15-20 minutes.',
                        'priority' => 'high'
                    ],
                    [
                        'type' => 'promotion',
                        'title' => '🎉 Limited Time Offer',
                        'message' => 'Get 20% off your next healthy Thai meal! Use code HEALTHY20 at checkout.',
                        'priority' => 'medium'
                    ],
                    [
                        'type' => 'payment',
                        'title' => '💳 Payment Successful',
                        'message' => 'Your payment of $24.99 has been processed successfully via Apple Pay.',
                        'priority' => 'medium'
                    ],
                    [
                        'type' => 'system',
                        'title' => '📱 App Update Available',
                        'message' => 'A new version of Somdul Table app is available with improved features.',
                        'priority' => 'low'
                    ]
                ];
                
                $random_notification = $test_notifications[array_rand($test_notifications)];
                
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (id, user_id, type, title, message, priority, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $notification_id,
                    $user_id,
                    $random_notification['type'],
                    $random_notification['title'],
                    $random_notification['message'],
                    $random_notification['priority']
                ]);
                
                $response['success'] = true;
                $response['message'] = 'Test notification created!';
                break;
                
            default:
                $response['errors'][] = "Unknown action: $action";
        }
        
    } catch (Exception $e) {
        $response['errors'][] = "Database error: " . $e->getMessage();
        error_log("Notifications error: " . $e->getMessage());
    }
    
    echo json_encode($response);
    exit();
}

// Get user data for page display
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get user info
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get basic stats for page load
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread,
            SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) as read
        FROM notifications 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure stats are integers
    $stats = [
        'total' => intval($stats['total'] ?? 0),
        'unread' => intval($stats['unread'] ?? 0),
        'read' => intval($stats['read'] ?? 0)
    ];
    
} catch (Exception $e) {
    error_log("User data error: " . $e->getMessage());
    // Fallback data
    $current_user = [
        'first_name' => $_SESSION['first_name'] ?? 'User', 
        'last_name' => $_SESSION['last_name'] ?? '', 
        'email' => $_SESSION['email'] ?? 'user@example.com'
    ];
    $stats = ['total' => 0, 'unread' => 0, 'read' => 0];
}

$page_title = "Notifications";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Somdul Table</title>
    <meta name="description" content="Stay updated with your Somdul Table orders and account activity">
    
    <!-- BaticaSans Font Import -->
    <link rel="preconnect" href="https://ydpschool.com">
    <style>
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Regular.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Bold.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Bold.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Bold.ttf') format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Medium.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.ttf') format('truetype');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }
    </style>
    
    <style>
        /* Somdul Table Design System Variables */
        :root {
            --brown: #bd9379;
            --cream: #ece8e1;
            --sage: #adb89d;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(189, 147, 121, 0.15);
            --shadow-medium: 0 8px 24px rgba(189, 147, 121, 0.25);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success: #27ae60;
            --danger: #e74c3c;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: var(--white);
            min-height: 100vh;
            font-weight: 400;
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: var(--text-dark);
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--curry);
            font-family: 'BaticaSans', sans-serif;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-gray);
            font-weight: 500;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .nav-links a:hover {
            color: var(--curry);
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Button Styles */
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--sage), #27ae60);
            color: var(--white);
        }

        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        /* Main Content */
        .main-content {
            margin-top: 80px;
            min-height: 100vh;
            padding: 2rem;
            background-color: #fafafa;

        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header Section */
        .notifications-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 3rem 0;
        }

        .notifications-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .notifications-header p {
            font-size: 1.2rem;
            color: var(--text-gray);
            max-width: 600px;
            margin: 0 auto;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Back Button */
        .back-section {
            margin-bottom: 2rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--curry);
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .stat-label {
            color: var(--text-gray);
            font-weight: 500;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Actions Bar */
        .actions-bar {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .filter-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Notifications Container */
        .notifications-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            border: 1px solid #f5f5f5;

        }

        .notifications-list {
            padding: 0;
        }

        .notification-item {
            padding: 2rem;
            border-bottom: 1px solid var(--border-light);
            transition: var(--transition);
            position: relative;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background: var(--cream);
        }

        .notification-item.unread {
            background: linear-gradient(90deg, rgba(207, 114, 58, 0.05) 0%, transparent 100%);
            border-left: 4px solid var(--curry);
        }

        .notification-header {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        .notification-icon.order_update { background: #e3f2fd; color: #1976d2; }
        .notification-icon.delivery { background: #f3e5f5; color: #7b1fa2; }
        .notification-icon.payment { background: #e8f5e8; color: #388e3c; }
        .notification-icon.promotion { background: #fff3e0; color: #f57c00; }
        .notification-icon.system { background: #fafafa; color: #616161; }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .notification-message {
            color: var(--text-gray);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: var(--text-gray);
            margin-bottom: 1rem;
        }

        .notification-time {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 500;
        }

        .notification-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Loading & Empty States */
        .loading {
            text-align: center;
            padding: 4rem;
            color: var(--text-gray);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border-light);
            border-top: 4px solid var(--curry);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.6;
        }

        .empty-state h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .empty-state p {
            font-family: 'BaticaSans', sans-serif;
        }

        /* Toast Notifications */
        .toast {
            position: fixed;
            top: 100px;
            right: 20px;
            background: var(--white);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-medium);
            z-index: 2000;
            transform: translateX(100%);
            transition: var(--transition);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 500;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-left: 4px solid #27ae60;
        }

        .toast.error {
            border-left: 4px solid #e74c3c;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-content {
                padding: 1.5rem;
            }

            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-buttons,
            .action-buttons {
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
                margin-top: 70px;
            }

            .notifications-header {
                padding: 2rem 0;
                margin-bottom: 2rem;
            }

            .notifications-header h1 {
                font-size: 2rem;
            }

            .notifications-header p {
                font-size: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem 1rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .actions-bar {
                padding: 1.5rem;
            }

            .filter-buttons {
                width: 100%;
                justify-content: space-between;
            }

            .filter-buttons .btn {
                flex: 1;
                max-width: 80px;
                padding: 0.6rem 0.8rem;
                font-size: 0.8rem;
            }

            .action-buttons {
                width: 100%;
                justify-content: center;
            }

            .notification-item {
                padding: 1.5rem;
            }

            .notification-header {
                gap: 1rem;
            }

            .notification-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }

            .notification-title {
                font-size: 1rem;
            }

            .notification-message {
                font-size: 0.9rem;
            }

            .notification-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }

            .nav-links {
                display: none;
            }

            .nav-actions {
                gap: 0.5rem;
            }

            .nav-actions .btn {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .stat-card:nth-child(3) {
                grid-column: span 2;
                max-width: 300px;
                margin: 0 auto;
            }

            .notification-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .toast {
                top: 80px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }

        /* Hover effects for touch devices */
        @media (hover: none) and (pointer: coarse) {
            .btn {
                min-height: 44px;
                touch-action: manipulation;
            }

            .notification-item {
                cursor: pointer;
            }
        }

       @media (prefers-color-scheme: dark) {
    :root {
        --white: #ffffff;
        --text-dark: #1a1a1a;
        --text-gray: #666666;
        --cream: #ffffff;
        --border-light: #f0f0f0;
    }

    .notifications-container {
        background: #ffffff;
    }

    .notification-item:hover {
        background: #fafafa;
    }

    .toast {
        background: #ffffff;
        color: #1a1a1a;
    }
}
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <a href="index.php" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Somdul Table" style="height: 50px; width: auto;">
            </a>
            <a href="index.php" class="logo">
                <span class="logo-text">Somdul Table</span>
            </a>

            <ul class="nav-links">
                <li><a href="./menus.php">Menu</a></li>
                <li><a href="./dashboard.php">Dashboard</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>

            <div class="nav-actions">
                <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
                <a href="logout.php" class="btn btn-primary">Sign Out</a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container">
            <!-- Back Button -->
            <div class="back-section">
                <a href="dashboard.php" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M19 12H5M12 19L5 12L12 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>

            <!-- Page Header -->
            <div class="notifications-header">
                <h1>🔔 Notifications</h1>
                <p>Stay updated with your orders and account activity from Somdul Table</p>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Notifications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['unread'] ?></div>
                    <div class="stat-label">Unread</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['read'] ?></div>
                    <div class="stat-label">Read</div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div class="actions-bar">
                <div class="filter-buttons">
                    <button class="btn btn-secondary" onclick="loadNotifications('all')" id="filterAll">All</button>
                    <button class="btn btn-secondary" onclick="loadNotifications('unread')" id="filterUnread">Unread</button>
                    <button class="btn btn-secondary" onclick="loadNotifications('read')" id="filterRead">Read</button>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="createTestNotification()">
                        <span class="desktop-text">Create Test</span>
                        <span class="mobile-text">Test</span>
                    </button>
                    <button class="btn btn-success" onclick="markAllAsRead()">
                        <span class="desktop-text">Mark All Read</span>
                        <span class="mobile-text">Read All</span>
                    </button>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="notifications-container">
                <div class="notifications-list" id="notificationsList">
                    <div class="loading">
                        <div class="loading-spinner"></div>
                        <p>Loading notifications...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentFilter = 'all';
        let isLoading = false;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadNotifications('all');
            setActiveFilter('all');
        });

        // Load notifications
        async function loadNotifications(filter = 'all') {
            if (isLoading) return;
            
            isLoading = true;
            currentFilter = filter;
            setActiveFilter(filter);
            
            const listContainer = document.getElementById('notificationsList');
            listContainer.innerHTML = `
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <p>Loading notifications...</p>
                </div>
            `;

            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=get_notifications&filter=${filter}&limit=20`
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.success) {
                    displayNotifications(data.notifications || []);
                } else {
                    const errorMsg = data.errors ? data.errors.join(', ') : 'Unable to load notifications';
                    showError(errorMsg);
                    listContainer.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon">❌</div>
                            <h3>Error Occurred</h3>
                            <p>${errorMsg}</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Network error:', error);
                showError('Network error: ' + error.message);
                listContainer.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">🔌</div>
                        <h3>Connection Problem</h3>
                        <p>Please check your internet connection and try again</p>
                    </div>
                `;
            } finally {
                isLoading = false;
            }
        }

        // Display notifications
        function displayNotifications(notifications) {
            const listContainer = document.getElementById('notificationsList');

            if (!notifications || notifications.length === 0) {
                listContainer.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">🔔</div>
                        <h3>No Notifications</h3>
                        <p>You're all caught up! Check back later for updates from Somdul Table.</p>
                    </div>
                `;
                return;
            }

            const notificationsHTML = notifications.map(notification => {
                const isUnread = !notification.read_at;
                const title = escapeHtml(notification.title || 'No Subject');
                const message = escapeHtml(notification.message || 'No message');
                const type = notification.type || 'system';
                const id = notification.id || '';
                const createdAt = notification.created_at || new Date().toISOString();
                const priority = notification.priority || 'medium';

                return `
                    <div class="notification-item ${isUnread ? 'unread' : ''}" data-id="${id}">
                        <div class="notification-header">
                            <div class="notification-icon ${type}">
                                ${getTypeIcon(type)}
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">${title}</div>
                                <div class="notification-message">${message}</div>
                                <div class="notification-meta">
                                    <span class="notification-time">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="margin-right: 0.25rem;">
                                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                            <polyline points="12,6 12,12 16,14" stroke="currentColor" stroke-width="2"/>
                                        </svg>
                                        ${formatTimeAgo(createdAt)}
                                    </span>
                                    <span class="priority-badge priority-${priority}">${priority.toUpperCase()}</span>
                                </div>
                                <div class="notification-actions">
                                    ${isUnread ? `<button class="btn btn-sm btn-success" onclick="markAsRead('${id}')">Mark Read</button>` : ''}
                                    <button class="btn btn-sm btn-danger" onclick="deleteNotification('${id}')">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            listContainer.innerHTML = notificationsHTML;
        }

        // Get icon for notification type
        function getTypeIcon(type) {
            const icons = {
                order_update: '📦',
                delivery: '🚚',
                payment: '💳',
                promotion: '🎉',
                system: '⚙️',
                review_reminder: '⭐'
            };
            return icons[type] || '📢';
        }

        // Format time ago
        function formatTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);

            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
            if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
            
            return date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric',
                year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
            });
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Set active filter button
        function setActiveFilter(filter) {
            document.querySelectorAll('.filter-buttons .btn').forEach(btn => {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-secondary');
            });
            
            const activeBtn = document.getElementById(`filter${filter.charAt(0).toUpperCase() + filter.slice(1)}`);
            if (activeBtn) {
                activeBtn.classList.remove('btn-secondary');
                activeBtn.classList.add('btn-primary');
            }
        }

        // Mark notification as read
        async function markAsRead(notificationId) {
            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=mark_as_read&notification_id=${notificationId}`
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess('Notification marked as read');
                    loadNotifications(currentFilter);
                    updatePageStats();
                } else {
                    showError(data.errors ? data.errors.join(', ') : 'Failed to mark as read');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        // Mark all notifications as read
        async function markAllAsRead() {
            if (!confirm('Mark all notifications as read?')) return;

            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=mark_all_read'
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess(data.message || 'All notifications marked as read');
                    loadNotifications(currentFilter);
                    // Update stats by reloading page
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showError(data.errors ? data.errors.join(', ') : 'Failed to mark all as read');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        // Delete notification
        async function deleteNotification(notificationId) {
            if (!confirm('Delete this notification?')) return;

            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_notification&notification_id=${notificationId}`
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess('Notification deleted');
                    loadNotifications(currentFilter);
                    updatePageStats();
                } else {
                    showError(data.errors ? data.errors.join(', ') : 'Failed to delete notification');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        // Create test notification
        async function createTestNotification() {
            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=create_test_notification'
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess('Test notification created!');
                    loadNotifications(currentFilter);
                    updatePageStats();
                } else {
                    showError(data.errors ? data.errors.join(', ') : 'Failed to create test notification');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        // Update page statistics
        async function updatePageStats() {
            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_unread_count'
                });

                const data = await response.json();

                if (data.success) {
                    // Update unread count in stats
                    const unreadStat = document.querySelector('.stats-grid .stat-card:nth-child(2) .stat-number');
                    if (unreadStat) {
                        unreadStat.textContent = data.count;
                    }
                }
            } catch (error) {
                console.error('Failed to update stats:', error);
            }
        }

        // Show success toast
        function showSuccess(message) {
            showToast(message, 'success');
        }

        // Show error toast
        function showError(message) {
            showToast(message, 'error');
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span>${type === 'success' ? '✅' : '❌'}</span>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 300);
            }, 4000);
        }

        // Auto refresh every 2 minutes
        setInterval(() => {
            if (!document.hidden) {
                loadNotifications(currentFilter);
            }
        }, 120000);

        // Page visibility API - refresh when user returns to tab
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                loadNotifications(currentFilter);
            }
        });

        // Pull to refresh for mobile
        let startY = 0;
        let currentY = 0;
        let pullDistance = 0;
        const pullThreshold = 80;

        document.addEventListener('touchstart', (e) => {
            if (window.scrollY === 0) {
                startY = e.touches[0].clientY;
            }
        });

        document.addEventListener('touchmove', (e) => {
            if (window.scrollY === 0 && startY > 0) {
                currentY = e.touches[0].clientY;
                pullDistance = currentY - startY;
                
                if (pullDistance > 0) {
                    e.preventDefault();
                    const container = document.querySelector('.container');
                    container.style.transform = `translateY(${Math.min(pullDistance * 0.5, pullThreshold)}px)`;
                    
                    if (pullDistance > pullThreshold) {
                        container.style.opacity = '0.8';
                    }
                }
            }
        });

        document.addEventListener('touchend', () => {
            const container = document.querySelector('.container');
            container.style.transform = '';
            container.style.opacity = '';
            
            if (pullDistance > pullThreshold) {
                showSuccess('Refreshing notifications...');
                loadNotifications(currentFilter);
            }
            
            startY = 0;
            pullDistance = 0;
        });

        // Smooth scrolling for navigation
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('a[href^="#"]');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    const targetSection = document.querySelector(targetId);
                    
                    if (targetSection) {
                        targetSection.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });

        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.boxShadow = '0 4px 20px rgba(189, 147, 121, 0.2)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
                navbar.style.boxShadow = '0 4px 12px rgba(189, 147, 121, 0.15)';
            }
        });

        // Mobile menu toggle (if needed)
        function toggleMobileMenu() {
            const navLinks = document.querySelector('.nav-links');
            navLinks.style.display = navLinks.style.display === 'flex' ? 'none' : 'flex';
        }

        // Add mobile-specific text for responsive design
        const mobileTexts = document.querySelectorAll('.mobile-text');
        const desktopTexts = document.querySelectorAll('.desktop-text');
        
        function updateTextForScreenSize() {
            const isMobile = window.innerWidth <= 480;
            
            mobileTexts.forEach(text => {
                text.style.display = isMobile ? 'inline' : 'none';
            });
            
            desktopTexts.forEach(text => {
                text.style.display = isMobile ? 'none' : 'inline';
            });
        }

        // Initialize responsive text
        updateTextForScreenSize();
        window.addEventListener('resize', updateTextForScreenSize);

        console.log('🍜 Somdul Table Notifications - Ready to serve! 🇺🇸');
    </script>

    <!-- Hidden elements for mobile responsiveness -->
    <style>
        .mobile-text {
            display: none;
        }

        .desktop-text {
            display: inline;
        }

        @media (max-width: 480px) {
            .mobile-text {
                display: inline;
            }

            .desktop-text {
                display: none;
            }
        }

        /* Priority badges styling */
        .priority-badge {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .priority-high {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .priority-medium {
            background: rgba(243, 156, 18, 0.1);
            color: #f39c12;
        }

        .priority-low {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        /* Animation for notification items */
        .notification-item {
            animation: fadeInUp 0.3s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Enhanced loading state */
        .loading p {
            margin-top: 1rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 500;
            color: var(--text-gray);
        }

        /* Better button focus states */
        .btn:focus {
            outline: 2px solid var(--curry);
            outline-offset: 2px;
        }

        .btn:focus:not(:focus-visible) {
            outline: none;
        }
    </style>
</body>
</html>