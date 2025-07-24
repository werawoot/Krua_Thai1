<?php
/**
 * Krua Thai - Complete Notification Center (FINAL FIX)
 * File: notifications.php  
 * Status: ERROR-FREE VERSION ✅
 * Fixed: Column 'data' not found error
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
    $action = $_POST['action'];
    $response = ['success' => false, 'errors' => []];

    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        switch ($action) {
            case 'get_notifications':
                $page = intval($_POST['page'] ?? 1);
                $limit = intval($_POST['limit'] ?? 20);
                $filter = $_POST['filter'] ?? 'all';
                $type = $_POST['type'] ?? 'all';
                $offset = ($page - 1) * $limit;
                
                $where_conditions = ["user_id = ?"];
                $params = [$user_id];
                
                if ($filter === 'unread') {
                    $where_conditions[] = "read_at IS NULL";
                } elseif ($filter === 'read') {
                    $where_conditions[] = "read_at IS NOT NULL";
                }
                
                if ($type !== 'all') {
                    $where_conditions[] = "type = ?";
                    $params[] = $type;
                }
                
                $where_clause = implode(' AND ', $where_conditions);
                
                // 🔥 FIXED: Removed 'data' column completely
                $stmt = $pdo->prepare("
                    SELECT id, type, title, message, read_at, created_at 
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
                
            case 'get_stats':
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread,
                        SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) as read,
                        SUM(CASE WHEN type = 'order' THEN 1 ELSE 0 END) as orders,
                        SUM(CASE WHEN type = 'delivery' THEN 1 ELSE 0 END) as delivery,
                        SUM(CASE WHEN type = 'payment' THEN 1 ELSE 0 END) as payment,
                        SUM(CASE WHEN type = 'system' THEN 1 ELSE 0 END) as system
                    FROM notifications 
                    WHERE user_id = ?
                ");
                $stmt->execute([$user_id]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Ensure all values are integers
                foreach (['total', 'unread', 'read', 'orders', 'delivery', 'payment', 'system'] as $key) {
                    $stats[$key] = intval($stats[$key] ?? 0);
                }
                
                $response['success'] = true;
                $response['stats'] = $stats;
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
            SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread
        FROM notifications 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Ensure stats are integers
    $stats = [
        'total' => intval($stats['total'] ?? 0),
        'unread' => intval($stats['unread'] ?? 0)
    ];
    
} catch (Exception $e) {
    error_log("User data error: " . $e->getMessage());
    // Fallback data
    $current_user = [
        'first_name' => $_SESSION['first_name'] ?? 'User', 
        'last_name' => $_SESSION['last_name'] ?? '', 
        'email' => $_SESSION['email'] ?? 'user@example.com'
    ];
    $stats = ['total' => 0, 'unread' => 0];
}

$page_title = "Notifications";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Krua Thai</title>
    
    <!-- BaticaSans Font -->
    <link rel="preconnect" href="https://ydpschool.com">
    <style>
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Bold.woff2') format('woff2');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Medium.woff2') format('woff2');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }

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
            --success: #28a745;
            --danger: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--cream);
            font-weight: 400;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            box-shadow: var(--shadow-soft);
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

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-top: 80px;
            min-height: 100vh;
            padding: 2rem;
        }

        .notifications-header {
            background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%);
            color: var(--white);
            padding: 3rem 0;
            margin-bottom: 3rem;
            border-radius: var(--radius-lg);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .notifications-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--white);
        }

        .notifications-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .notifications-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            align-items: start;
        }

        /* Sidebar */
        .notifications-sidebar {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-soft);
            position: sticky;
            top: 100px;
        }

        .sidebar-section {
            margin-bottom: 2rem;
        }

        .sidebar-section:last-child {
            margin-bottom: 0;
        }

        .sidebar-section h3 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .filter-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            text-align: left;
            width: 100%;
            background: transparent;
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--text-gray);
        }

        .filter-btn:hover {
            background: var(--cream);
            color: var(--text-dark);
        }

        .filter-btn.active {
            background: var(--curry);
            color: var(--white);
        }

        .filter-count {
            font-size: 0.8rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--cream);
            border-radius: var(--radius-md);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--curry);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-gray);
        }

        /* Main Content */
        .notifications-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .content-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .content-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .content-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        /* Notifications List */
        .notifications-list {
            padding: 0;
        }

        .notification-item {
            padding: 1.5rem 2rem;
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
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .notification-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .notification-icon.order { 
            background: #e3f2fd; 
            color: #1976d2; 
        }
        .notification-icon.delivery { 
            background: #f3e5f5; 
            color: #7b1fa2; 
        }
        .notification-icon.payment { 
            background: #e8f5e8; 
            color: #388e3c; 
        }
        .notification-icon.system { 
            background: #fff3e0; 
            color: #f57c00; 
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .notification-message {
            color: var(--text-gray);
            line-height: 1.5;
            margin-bottom: 0.75rem;
        }

        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: var(--text-gray);
        }

        .notification-time {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .notification-actions button {
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 15px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        /* Pagination */
        .pagination {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }

        .pagination button {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-light);
            background: white;
            color: var(--text-gray);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
        }

        .pagination button:hover:not(:disabled) {
            background: var(--curry);
            color: white;
            border-color: var(--curry);
        }

        .pagination button.active {
            background: var(--curry);
            color: white;
            border-color: var(--curry);
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Loading */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 3rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-light);
            border-top: 4px solid var(--curry);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Toast Messages */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-medium);
            z-index: 2000;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .toast.error {
            background: var(--danger);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .notifications-layout {
                grid-template-columns: 1fr;
            }
            
            .notifications-sidebar {
                position: static;
                order: -1;
            }
            
            .filter-buttons {
                flex-direction: row;
                overflow-x: auto;
            }
            
            .filter-btn {
                min-width: 120px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .notifications-header {
                margin-bottom: 2rem;
                padding: 2rem 0;
            }
            
            .notifications-header h1 {
                font-size: 2rem;
            }
            
            .content-header {
                padding: 1rem;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .notification-item {
                padding: 1rem;
            }
            
            .notification-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            
            .nav-links {
                display: none;
            }
        }

        /* Error Message Styles */
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin: 1rem 0;
            border: 1px solid #f5c6cb;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin: 1rem 0;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <a href="index.php" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Krua Thai" style="height: 40px; width: auto;" onerror="this.style.display='none';">
                <span class="logo-text">Krua Thai</span>
            </a>
            
            <ul class="nav-links">
                <li><a href="./menus.php">Menu</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            
            <div class="nav-actions">
                <a href="logout.php" class="btn btn-secondary">Sign Out</a>
                <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <!-- Notifications Header -->
        <div class="notifications-header">
            <div class="container">
                <h1>🔔 การแจ้งเตือน</h1>
                <p>ติดตามความเคลื่อนไหวออเดอร์ การจัดส่ง และกิจกรรมบัญชีของคุณ</p>
            </div>
        </div>

        <div class="container">
            <div class="notifications-layout">
                <!-- Sidebar -->
                <aside class="notifications-sidebar">
                    <!-- Stats -->
                    <div class="sidebar-section">
                        <h3>ภาพรวม</h3>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-number" id="totalCount"><?= $stats['total'] ?></div>
                                <div class="stat-label">ทั้งหมด</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number" id="unreadCount"><?= $stats['unread'] ?></div>
                                <div class="stat-label">ยังไม่อ่าน</div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="sidebar-section">
                        <h3>กรองตามสถานะ</h3>
                        <div class="filter-buttons">
                            <button class="filter-btn active" data-filter="all">
                                <span>ทั้งหมด</span>
                                <span class="filter-count" id="allCount"><?= $stats['total'] ?></span>
                            </button>
                            <button class="filter-btn" data-filter="unread">
                                <span>ยังไม่อ่าน</span>
                                <span class="filter-count" id="unreadFilterCount"><?= $stats['unread'] ?></span>
                            </button>
                            <button class="filter-btn" data-filter="read">
                                <span>อ่านแล้ว</span>
                                <span class="filter-count" id="readCount"><?= $stats['total'] - $stats['unread'] ?></span>
                            </button>
                        </div>
                    </div>

                    <!-- Type Filters -->
                    <div class="sidebar-section">
                        <h3>กรองตามประเภท</h3>
                        <div class="filter-buttons">
                            <button class="filter-btn active" data-type="all">
                                <span>ทุกประเภท</span>
                            </button>
                            <button class="filter-btn" data-type="order">
                                <span>📦 คำสั่งซื้อ</span>
                            </button>
                            <button class="filter-btn" data-type="delivery">
                                <span>🚚 การจัดส่ง</span>
                            </button>
                            <button class="filter-btn" data-type="payment">
                                <span>💰 การชำระเงิน</span>
                            </button>
                            <button class="filter-btn" data-type="system">
                                <span>⚙️ ระบบ</span>
                            </button>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="sidebar-section">
                        <h3>การดำเนินการ</h3>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <button class="btn btn-primary btn-sm" onclick="markAllAsRead()">
                                อ่านทั้งหมด
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="refreshNotifications()">
                                รีเฟรช
                            </button>
                        </div>
                    </div>
                </aside>

                <!-- Main Content -->
                <main class="notifications-content">
                    <div class="content-header">
                        <div class="content-title" id="contentTitle">การแจ้งเตือนทั้งหมด</div>
                        <div class="content-actions">
                            <button class="btn btn-sm btn-secondary" onclick="refreshNotifications()">
                                🔄 รีเฟรช
                            </button>
                        </div>
                    </div>
                    
                    <div class="notifications-list" id="notificationsList">
                        <div class="loading">
                            <div class="spinner"></div>
                        </div>
                    </div>

                    <div class="pagination" id="pagination" style="display: none;">
                        <!-- Pagination will be generated by JavaScript -->
                    </div>
                </main>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <script>
        let currentPage = 1;
        let currentFilter = 'all';
        let currentType = 'all';
        let isLoading = false;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔔 Krua Thai - Notifications page initializing...');
            loadNotifications();
            updateStats();
            
            // Set up filter buttons
            setupFilterButtons();
            
            // Auto-refresh every 30 seconds
            setInterval(updateStats, 30000);
        });

        // Setup filter button event listeners
        function setupFilterButtons() {
            // Status filters
            document.querySelectorAll('[data-filter]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');
                    setActiveFilter(this, '[data-filter]');
                    currentFilter = filter;
                    currentPage = 1;
                    loadNotifications();
                    updateContentTitle();
                });
            });

            // Type filters
            document.querySelectorAll('[data-type]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    setActiveFilter(this, '[data-type]');
                    currentType = type;
                    currentPage = 1;
                    loadNotifications();
                    updateContentTitle();
                });
            });
        }

        // Set active filter button
        function setActiveFilter(activeBtn, selector) {
            document.querySelectorAll(selector).forEach(btn => {
                btn.classList.remove('active');
            });
            activeBtn.classList.add('active');
        }

        // Update content title based on filters
        function updateContentTitle() {
            const filterText = currentFilter === 'all' ? 'ทั้งหมด' : 
                              currentFilter === 'unread' ? 'ยังไม่อ่าน' : 'อ่านแล้ว';
            const typeText = currentType === 'all' ? 'การแจ้งเตือน' :
                            currentType === 'order' ? 'การแจ้งเตือนคำสั่งซื้อ' :
                            currentType === 'delivery' ? 'การแจ้งเตือนการจัดส่ง' :
                            currentType === 'payment' ? 'การแจ้งเตือนการชำระเงิน' :
                            'การแจ้งเตือนระบบ';
            
            document.getElementById('contentTitle').textContent = 
                currentType === 'all' ? `การแจ้งเตือน${filterText}` : typeText;
        }

        // Load notifications
        async function loadNotifications() {
            if (isLoading) return;
            isLoading = true;

            const listContainer = document.getElementById('notificationsList');
            listContainer.innerHTML = '<div class="loading"><div class="spinner"></div></div>';

            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'get_notifications',
                        page: currentPage,
                        limit: 20,
                        filter: currentFilter,
                        type: currentType
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                console.log('✅ Notifications response:', data);

                if (data.success) {
                    displayNotifications(data.notifications || []);
                    displayPagination(data.page || 1, data.total_pages || 1, data.total || 0);
                } else {
                    const errorMsg = data.errors ? data.errors.join(', ') : 'ไม่สามารถโหลดการแจ้งเตือนได้';
                    showError(errorMsg);
                    listContainer.innerHTML = `<div class="empty-state"><div class="empty-icon">❌</div><h3>เกิดข้อผิดพลาด</h3><p>${errorMsg}</p></div>`;
                }
            } catch (error) {
                console.error('❌ Network error:', error);
                showError('เกิดข้อผิดพลาดเครือข่าย: ' + error.message);
                listContainer.innerHTML = '<div class="empty-state"><div class="empty-icon">🔌</div><h3>ปัญหาการเชื่อมต่อ</h3><p>กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต</p></div>';
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
                        <h3>ไม่มีการแจ้งเตือน</h3>
                        <p>คุณอ่านครบทุกข้อความแล้ว! กลับมาดูใหม่ภายหลัง</p>
                    </div>
                `;
                return;
            }

            const notificationsHTML = notifications.map(notification => {
                // Safely handle null/undefined values
                const isUnread = !notification.read_at;
                const title = escapeHtml(notification.title || 'ไม่มีหัวข้อ');
                const message = escapeHtml(notification.message || 'ไม่มีข้อความ');
                const type = notification.type || 'system';
                const id = notification.id || '';
                const createdAt = notification.created_at || new Date().toISOString();

                return `
                    <div class="notification-item ${isUnread ? 'unread' : ''}" data-id="${id}">
                        <div class="notification-header">
                            <div class="notification-icon ${type}">
                                ${getNotificationIcon(type)}
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">${title}</div>
                                <div class="notification-message">${message}</div>
                            </div>
                        </div>
                        <div class="notification-meta">
                            <div class="notification-time">
                                🕒 ${formatTime(createdAt)}
                            </div>
                            <div class="notification-actions">
                                ${isUnread ? `
                                    <button class="btn btn-sm btn-success" onclick="markAsRead('${id}')">
                                        อ่านแล้ว
                                    </button>
                                ` : ''}
                                <button class="btn btn-sm btn-danger" onclick="deleteNotification('${id}')">
                                    ลบ
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            listContainer.innerHTML = notificationsHTML;
        }

        // Display pagination
        function displayPagination(currentPage, totalPages, totalItems) {
            const paginationContainer = document.getElementById('pagination');
            
            if (totalPages <= 1) {
                paginationContainer.style.display = 'none';
                return;
            }

            paginationContainer.style.display = 'flex';

            let paginationHTML = '';

            // Previous button
            paginationHTML += `
                <button ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">
                    ← ก่อนหน้า
                </button>
            `;

            // Page numbers
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);

            if (startPage > 1) {
                paginationHTML += `<button onclick="changePage(1)">1</button>`;
                if (startPage > 2) {
                    paginationHTML += `<span style="padding: 0.5rem;">...</span>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                paginationHTML += `
                    <button class="${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">
                        ${i}
                    </button>
                `;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationHTML += `<span style="padding: 0.5rem;">...</span>`;
                }
                paginationHTML += `<button onclick="changePage(${totalPages})">${totalPages}</button>`;
            }

            // Next button
            paginationHTML += `
                <button ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">
                    ถัดไป →
                </button>
            `;

            paginationContainer.innerHTML = paginationHTML;
        }

        // Change page
        function changePage(page) {
            currentPage = page;
            loadNotifications();
        }

        // Get notification icon
        function getNotificationIcon(type) {
            const icons = {
                order: '📦',
                delivery: '🚚',
                payment: '💰',
                system: '⚙️'
            };
            return icons[type] || '🔔';
        }

        // Format time in Thai
        function formatTime(timestamp) {
            try {
                const date = new Date(timestamp);
                const now = new Date();
                const diff = now - date;
                const minutes = Math.floor(diff / 60000);
                const hours = Math.floor(diff / 3600000);
                const days = Math.floor(diff / 86400000);

                if (minutes < 1) return 'เมื่อสักครู่';
                if (minutes < 60) return `${minutes} นาทีที่แล้ว`;
                if (hours < 24) return `${hours} ชั่วโมงที่แล้ว`;
                if (days < 7) return `${days} วันที่แล้ว`;
                
                return date.toLocaleDateString('th-TH');
            } catch (error) {
                return 'ไม่ระบุเวลา';
            }
        }

        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Mark notification as read
        async function markAsRead(notificationId) {
            if (!notificationId) {
                showError('ไม่พบ ID การแจ้งเตือน');
                return;
            }

            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'mark_as_read',
                        notification_id: notificationId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Update UI
                    const notificationElement = document.querySelector(`[data-id="${notificationId}"]`);
                    if (notificationElement) {
                        notificationElement.classList.remove('unread');
                        const actionsContainer = notificationElement.querySelector('.notification-actions');
                        const markReadBtn = actionsContainer.querySelector('.btn-success');
                        if (markReadBtn) {
                            markReadBtn.remove();
                        }
                    }
                    
                    updateStats();
                    showSuccess('ทำเครื่องหมายอ่านแล้ว');
                } else {
                    showError(data.errors ? data.errors.join(', ') : 'ไม่สามารถทำเครื่องหมายได้');
                }
            } catch (error) {
                showError('เกิดข้อผิดพลาดเครือข่าย');
            }
        }

        // Mark all as read
        async function markAllAsRead() {
            if (!confirm('ทำเครื่องหมายการแจ้งเตือนทั้งหมดว่าอ่านแล้ว?')) return;

            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'mark_all_read'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    loadNotifications();
                    updateStats();
                    showSuccess(data.message || 'ทำเครื่องหมายทั้งหมดแล้ว');
                } else {
                    showError(data.errors ? data.errors.join(', ') : 'ไม่สามารถดำเนินการได้');
                }
            } catch (error) {
                showError('เกิดข้อผิดพลาดเครือข่าย');
            }
        }

        // Delete notification
        async function deleteNotification(notificationId) {
            if (!notificationId) {
                showError('ไม่พบ ID การแจ้งเตือน');
                return;
            }

            if (!confirm('ลบการแจ้งเตือนนี้?')) return;

            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'delete_notification',
                        notification_id: notificationId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Remove from UI with animation
                    const notificationElement = document.querySelector(`[data-id="${notificationId}"]`);
                    if (notificationElement) {
                        notificationElement.style.opacity = '0';
                        notificationElement.style.transform = 'translateX(-100%)';
                        setTimeout(() => {
                            notificationElement.remove();
                            // Reload if no notifications left
                            const remainingNotifications = document.querySelectorAll('.notification-item');
                            if (remainingNotifications.length === 0) {
                                loadNotifications();
                            }
                        }, 300);
                    }
                    
                    updateStats();
                    showSuccess('ลบการแจ้งเตือนแล้ว');
                } else {
                    showError(data.errors ? data.errors.join(', ') : 'ไม่สามารถลบได้');
                }
            } catch (error) {
                showError('เกิดข้อผิดพลาดเครือข่าย');
            }
        }

        // Refresh notifications
        function refreshNotifications() {
            currentPage = 1;
            loadNotifications();
            updateStats();
            showSuccess('รีเฟรชข้อมูลแล้ว');
        }

        // Update statistics
        async function updateStats() {
            try {
                const response = await fetch('notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'get_stats'
                    })
                });

                const data = await response.json();

                if (data.success && data.stats) {
                    const stats = data.stats;
                    
                    // Update sidebar stats
                    document.getElementById('totalCount').textContent = stats.total || 0;
                    document.getElementById('unreadCount').textContent = stats.unread || 0;
                    
                    // Update filter counts
                    document.getElementById('allCount').textContent = stats.total || 0;
                    document.getElementById('unreadFilterCount').textContent = stats.unread || 0;
                    document.getElementById('readCount').textContent = stats.read || 0;
                }
            } catch (error) {
                console.error('Failed to update stats:', error);
            }
        }

        // Show success message
        function showSuccess(message) {
            showToast(message, 'success');
        }

        // Show error message
        function showError(message) {
            showToast(message, 'error');
            console.error('Error:', message);
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            
            document.getElementById('toastContainer').appendChild(toast);
            
            // Show toast
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            // Hide and remove toast
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 300);
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // R key to refresh
            if (e.key === 'r' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                refreshNotifications();
            }
            
            // A key to mark all as read
            if (e.key === 'a' && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                markAllAsRead();
            }
        });

        console.log('🔔 Krua Thai - Notifications page loaded successfully!');
    </script>

    <!-- Debug Information (ลบออกใน production) -->
    <?php if (isset($_GET['debug'])): ?>
    <div style="position: fixed; bottom: 10px; left: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 5px; font-size: 12px; z-index: 1000; max-width: 300px;">
        <strong>🔧 Debug Info:</strong><br>
        User ID: <?= htmlspecialchars($user_id) ?><br>
        Total: <?= $stats['total'] ?> | Unread: <?= $stats['unread'] ?><br>
        Database: krua_thai<br>
        Version: Fixed - No Data Column Error ✅
    </div>
    <?php endif; ?>
</body>
</html>