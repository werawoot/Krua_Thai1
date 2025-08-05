<?php
/**
 * Krua Thai - Admin Dashboard
 * File: admin/dashboard.php
 * Description: Main admin dashboard with real-time statistics and management overview
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

// Handle AJAX requests for dashboard operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'update_order_status':
                $result = updateOrderStatus($pdo, $_POST['order_id'], $_POST['status']);
                echo json_encode($result);
                exit;
                
            case 'quick_stats_refresh':
                $result = getQuickStats($pdo);
                echo json_encode($result);
                exit;
                
            case 'get_recent_activities':
                $result = getRecentActivities($pdo);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Database Functions
function updateOrderStatus($pdo, $orderId, $status) {
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $orderId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Order status updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Order not found or no changes made'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating order status: ' . $e->getMessage()];
    }
}

function getQuickStats($pdo) {
    try {
        $stats = [];
        
        // Total revenue today
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as today_revenue 
            FROM payments 
            WHERE DATE(payment_date) = CURDATE() AND status = 'completed'
        ");
        $stmt->execute();
        $stats['today_revenue'] = $stmt->fetchColumn();
        
        // Total orders today
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as today_orders 
            FROM orders 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stmt->execute();
        $stats['today_orders'] = $stmt->fetchColumn();
        
        // Active subscriptions
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active_subscriptions 
            FROM subscriptions 
            WHERE status = 'active'
        ");
        $stmt->execute();
        $stats['active_subscriptions'] = $stmt->fetchColumn();
        
        // Pending orders
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as pending_orders 
            FROM orders 
            WHERE status IN ('pending', 'confirmed')
        ");
        $stmt->execute();
        $stats['pending_orders'] = $stmt->fetchColumn();
        
        return ['success' => true, 'data' => $stats];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching stats: ' . $e->getMessage()];
    }
}

function getRecentActivities($pdo) {
    try {
        $stmt = $pdo->prepare("
            (SELECT 'order' as type, o.id, o.order_number as reference, 
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    o.status, o.created_at as activity_time
             FROM orders o 
             JOIN users u ON o.user_id = u.id 
             ORDER BY o.created_at DESC LIMIT 5)
            UNION ALL
            (SELECT 'payment' as type, p.id, p.transaction_id as reference,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    p.status, p.created_at as activity_time
             FROM payments p 
             JOIN users u ON p.user_id = u.id 
             ORDER BY p.created_at DESC LIMIT 5)
            UNION ALL
            (SELECT 'review' as type, r.id, r.title as reference,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    r.moderation_status as status, r.created_at as activity_time
             FROM reviews r 
             JOIN users u ON r.user_id = u.id 
             ORDER BY r.created_at DESC LIMIT 5)
            ORDER BY activity_time DESC LIMIT 10
        ");
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'data' => $activities];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching activities: ' . $e->getMessage()];
    }
}

// Fetch Dashboard Statistics
try {
    // Overview Statistics
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()) as today_orders,
            (SELECT COUNT(*) FROM orders WHERE status = 'pending') as pending_orders,
            (SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND DATE(delivered_at) = CURDATE()) as delivered_today,
            (SELECT COUNT(*) FROM users WHERE status = 'active') as total_customers,
            (SELECT COUNT(*) FROM subscriptions WHERE status = 'active') as active_subscriptions,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(payment_date) = CURDATE() AND status = 'completed') as today_revenue,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) AND status = 'completed') as month_revenue,
            (SELECT COALESCE(AVG(overall_rating), 0) FROM reviews WHERE is_public = 1 AND moderation_status = 'approved') as avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE moderation_status = 'pending') as pending_reviews,
            (SELECT COUNT(*) FROM complaints WHERE status = 'open') as open_complaints
    ");
    $stmt->execute();
    $overview = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent Orders
    $stmt = $pdo->prepare("
        SELECT o.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.phone as customer_phone
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT 8
    ");
    $stmt->execute();
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top Selling Menus
    $stmt = $pdo->prepare("
        SELECT m.name, m.name_thai, 
               COUNT(oi.id) as order_count,
               SUM(oi.quantity) as total_quantity,
               SUM(oi.menu_price * oi.quantity) as total_revenue
        FROM order_items oi
        JOIN menus m ON oi.menu_id = m.id
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY m.id, m.name, m.name_thai
        ORDER BY total_quantity DESC
        LIMIT 5
    ");
    $stmt->execute();
    $topMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Revenue Trend (Last 7 days)
    $stmt = $pdo->prepare("
        SELECT DATE(payment_date) as date,
               COALESCE(SUM(amount), 0) as revenue
        FROM payments 
        WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              AND status = 'completed'
        GROUP BY DATE(payment_date)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $revenueTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Low Stock Items
    $stmt = $pdo->prepare("
        SELECT ingredient_name, ingredient_name_thai, 
               current_stock, minimum_stock, unit_of_measure
        FROM inventory 
        WHERE current_stock <= minimum_stock AND is_active = 1
        ORDER BY (current_stock / minimum_stock) ASC
        LIMIT 5
    ");
    $stmt->execute();
    $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent Customer Registrations
    $stmt = $pdo->prepare("
        SELECT first_name, last_name, email, created_at
        FROM users 
        WHERE role = 'customer' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recentCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Initialize empty arrays if database fails
    $overview = [
        'today_orders' => 0, 'pending_orders' => 0, 'delivered_today' => 0,
        'total_customers' => 0, 'active_subscriptions' => 0, 'today_revenue' => 0,
        'month_revenue' => 0, 'avg_rating' => 0, 'pending_reviews' => 0, 'open_complaints' => 0
    ];
    $recentOrders = [];
    $topMenus = [];
    $revenueTrend = [];
    $lowStockItems = [];
    $recentCustomers = [];
    error_log("Dashboard error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Krua Thai Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --cream: #ece8e1;
            --sage: #adb89d;
            --brown: #bd9379;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(0,0,0,0.05);
            --shadow-medium: 0 8px 24px rgba(0,0,0,0.1);
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
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f6f3 100%);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
        }


        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .logo-image {
            max-width: 80px;
            max-height: 80px;
            width: auto;
            height: auto;
            object-fit: contain;
            filter: brightness(1.1) contrast(1.2);
            transition: transform 0.3s ease;
        }

        .logo-image:hover {
            transform: scale(1.05);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 2rem;
            transition: var(--transition);
        }

        /* Header */
        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-gray);
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .welcome-section {
            background: linear-gradient(135deg, var(--curry), #e67e22);
            color: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 0.5rem;
        }

        .welcome-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .welcome-time {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), #e67e22);
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--text-dark);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-soft);
        }

        .btn-secondary:hover {
            background: var(--cream);
            transform: translateY(-1px);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--sage), #27ae60);
            color: var(--white);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: var(--white);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: var(--white);
        }

        .btn-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: var(--white);
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        /* Stats Grid */
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
            border: 1px solid var(--border-light);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--curry), var(--brown));
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--white);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .stat-change {
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .stat-change.positive {
            color: #27ae60;
        }

        .stat-change.negative {
            color: #e74c3c;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .dashboard-card {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Tables */
        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }

        .table th {
            background: var(--cream);
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background: #fafafa;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-confirmed {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .status-preparing {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-delivered {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-cancelled {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        /* Charts */
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 1rem;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .quick-action:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .quick-action-icon {
            font-size: 2rem;
            color: var(--curry);
            margin-bottom: 1rem;
        }

        .quick-action-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .quick-action-desc {
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        /* Alert boxes */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            border-left: 4px solid;
        }

        .alert-warning {
            background: rgba(241, 196, 15, 0.1);
            border-left-color: #f39c12;
            color: #d68910;
        }

        .alert-info {
            background: rgba(52, 152, 219, 0.1);
            border-left-color: #3498db;
            color: #2980b9;
        }

        /* Loading States */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
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

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 3000;
        }

        .toast {
            background: var(--white);
            border-left: 4px solid var(--curry);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-medium);
            padding: 1rem;
            margin-bottom: 0.5rem;
            min-width: 300px;
            transform: translateX(100%);
            transition: var(--transition);
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-left-color: #27ae60;
        }

        .toast.error {
            border-left-color: #e74c3c;
        }

        /* Responsive Design */
        @media (max-width: 768px) {

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .logo-image {
                max-width: 60px;
                max-height: 60px;
            }
        }

        /* Utilities */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .d-none { display: none; }
        .d-block { display: block; }
        .mb-0 { margin-bottom: 0; }
        .mb-1 { margin-bottom: 0.5rem; }
        .mb-2 { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <div class="welcome-section">
                            <div class="welcome-title">
                                <i class="fas fa-tachometer-alt" style="margin-right: 0.5rem;"></i>
                                Welcome Back, Admin!
                            </div>
                            <div class="welcome-time" id="currentDateTime">
                                <?= date('l, F j, Y - g:i A') ?>
                            </div>
                        </div>
                        <h1 class="page-title">Dashboard Overview</h1>
                        <p class="page-subtitle">Monitor your restaurant operations in real-time</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="refreshDashboard()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                        <button class="btn btn-primary" onclick="window.location.href='orders.php'">
                            <i class="fas fa-plus"></i>
                            New Order
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($overview['today_orders']) ?></div>
                    <div class="stat-label">Today's Orders</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        +12% from yesterday
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($overview['pending_orders']) ?></div>
                    <div class="stat-label">Pending Orders</div>
                    <?php if ($overview['pending_orders'] > 10): ?>
                        <div class="stat-change negative">
                            <i class="fas fa-exclamation-triangle"></i>
                            Needs attention
                        </div>
                    <?php else: ?>
                        <div class="stat-change positive">
                            <i class="fas fa-check"></i>
                            Under control
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-value">₿<?= number_format($overview['today_revenue'], 0) ?></div>
                    <div class="stat-label">Today's Revenue</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        +8% from yesterday
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($overview['active_subscriptions']) ?></div>
                    <div class="stat-label">Active Subscriptions</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        +5% this month
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($overview['avg_rating'], 1) ?></div>
                    <div class="stat-label">Average Rating</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        Excellent service
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="orders.php?status=pending" class="quick-action">
                    <div class="quick-action-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="quick-action-title">Manage Orders</div>
                    <div class="quick-action-desc">View and process pending orders</div>
                </a>
                
                <a href="menus.php" class="quick-action">
                    <div class="quick-action-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="quick-action-title">Menu Management</div>
                    <div class="quick-action-desc">Add or update menu items</div>
                </a>
                
                <a href="inventory.php" class="quick-action">
                    <div class="quick-action-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="quick-action-title">Inventory Check</div>
                    <div class="quick-action-desc">Monitor stock levels</div>
                </a>
                
                <a href="reports.php" class="quick-action">
                    <div class="quick-action-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="quick-action-title">Sales Reports</div>
                    <div class="quick-action-desc">View detailed analytics</div>
                </a>
            </div>

            <!-- Alerts -->
            <?php if ($overview['pending_reviews'] > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Review Alert:</strong> You have <?= $overview['pending_reviews'] ?> reviews pending moderation.
                <a href="reviews.php?status=pending" style="margin-left: 1rem;">Review Now</a>
            </div>
            <?php endif; ?>
            
            <?php if ($overview['open_complaints'] > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Complaints Alert:</strong> You have <?= $overview['open_complaints'] ?> open complaints that need attention.
                <a href="complaints.php?status=open" style="margin-left: 1rem;">Handle Now</a>
            </div>
            <?php endif; ?>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Recent Orders -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-shopping-cart" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Recent Orders
                        </h3>
                        <a href="orders.php" class="btn btn-secondary btn-sm">View All</a>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (!empty($recentOrders)): ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($recentOrders, 0, 5) as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($order['customer_name']) ?></div>
                                            <small style="color: var(--text-gray);"><?= htmlspecialchars($order['customer_phone']) ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $order['status'] ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                $orderDate = new DateTime($order['created_at']);
                                                echo $orderDate->format('M d, H:i');
                                            ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-icon btn-info btn-sm" onclick="viewOrder('<?= $order['id'] ?>')" title="View Order">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-gray);">
                            <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                            <h3>No recent orders</h3>
                            <p>Orders will appear here when customers place them</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Sidebar -->
                <div style="display: flex; flex-direction: column; gap: 2rem;">
                    <!-- Revenue Chart -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-line" style="color: var(--curry); margin-right: 0.5rem;"></i>
                                Revenue Trend
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Top Selling Items -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-fire" style="color: var(--curry); margin-right: 0.5rem;"></i>
                                Top Selling Items
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($topMenus)): ?>
                                <?php foreach ($topMenus as $index => $menu): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--border-light);">
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-dark);">
                                            #<?= $index + 1 ?> <?= htmlspecialchars($menu['name']) ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-gray);">
                                            <?= htmlspecialchars($menu['name_thai']) ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-weight: 600; color: var(--curry);">
                                            <?= $menu['total_quantity'] ?> sold
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-gray);">
                                            ₿<?= number_format($menu['total_revenue'], 0) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: var(--text-gray);">
                                <i class="fas fa-utensils" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                                <p>No sales data available</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Low Stock Alert -->
                    <?php if (!empty($lowStockItems)): ?>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-exclamation-triangle" style="color: #f39c12; margin-right: 0.5rem;"></i>
                                Low Stock Alert
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php foreach ($lowStockItems as $item): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--border-light);">
                                <div>
                                    <div style="font-weight: 500; color: var(--text-dark);">
                                        <?= htmlspecialchars($item['ingredient_name']) ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-gray);">
                                        <?= htmlspecialchars($item['ingredient_name_thai']) ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 600; color: #e74c3c;">
                                        <?= $item['current_stock'] ?> <?= $item['unit_of_measure'] ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-gray);">
                                        Min: <?= $item['minimum_stock'] ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <div style="margin-top: 1rem;">
                                <a href="inventory.php?filter=low_stock" class="btn btn-warning btn-sm">
                                    <i class="fas fa-boxes"></i>
                                    Manage Inventory
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Customers -->
            <?php if (!empty($recentCustomers)): ?>
            <div class="dashboard-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user-plus" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        Recent Customer Registrations
                    </h3>
                    <a href="users.php?role=customer" class="btn btn-secondary btn-sm">View All Customers</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th>Registered</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentCustomers as $customer): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($customer['email']) ?></td>
                                    <td>
                                        <?php 
                                            $regDate = new DateTime($customer['created_at']);
                                            echo $regDate->format('M d, Y');
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-delivered">New</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Global variables
        let revenueChart;

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            updateDateTime();
            setInterval(updateDateTime, 60000); // Update every minute
            setInterval(refreshStats, 300000); // Refresh stats every 5 minutes
        });

        // Update current date/time
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            };
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
        }

        // Initialize charts
        function initializeCharts() {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            
            // Prepare revenue trend data
            const revenueTrendData = <?= json_encode($revenueTrend) ?>;
            const labels = revenueTrendData.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });
            const data = revenueTrendData.map(item => parseFloat(item.revenue));

            revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue (THB)',
                        data: data,
                        borderColor: '#cf723a',
                        backgroundColor: 'rgba(207, 114, 58, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#cf723a',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#7f8c8d'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            },
                            ticks: {
                                color: '#7f8c8d',
                                callback: function(value) {
                                    return '₿' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    elements: {
                        point: {
                            hoverBackgroundColor: '#cf723a'
                        }
                    }
                }
            });
        }

        // Refresh dashboard statistics
        function refreshDashboard() {
            showToast('Refreshing dashboard...', 'info');
            window.location.reload();
        }

        // Refresh stats via AJAX
        function refreshStats() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=quick_stats_refresh'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStatsDisplay(data.data);
                }
            })
            .catch(error => {
                console.error('Error refreshing stats:', error);
            });
        }

        // Update stats display
        function updateStatsDisplay(stats) {
            // Update stat values with animation
            const statCards = document.querySelectorAll('.stat-value');
            statCards.forEach((card, index) => {
                const newValue = Object.values(stats)[index];
                if (newValue !== undefined) {
                    animateValue(card, parseInt(card.textContent.replace(/[^\d]/g, '')), newValue, 1000);
                }
            });
        }

        // Animate number values
        function animateValue(element, start, end, duration) {
            const range = end - start;
            const increment = range / (duration / 16);
            let current = start;
            
            const timer = setInterval(() => {
                current += increment;
                if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                    current = end;
                    clearInterval(timer);
                }
                
                if (element.textContent.includes('₿')) {
                    element.textContent = '₿' + Math.floor(current).toLocaleString();
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 16);
        }

        // View order details
        function viewOrder(orderId) {
            window.location.href = `orders.php?view=${orderId}`;
        }

        // Update order status
        function updateOrderStatus(orderId, status) {
            const formData = new FormData();
            formData.append('action', 'update_order_status');
            formData.append('order_id', orderId);
            formData.append('status', status);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    refreshDashboard();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error updating order status:', error);
                showToast('Error updating order status', 'error');
            });
        }

        // Toast notification system
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            toast.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                        <span>${message}</span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: var(--text-gray);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(toast);
            
            // Show toast
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }


        // Handle keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshDashboard();
            }
        });

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log(`Dashboard loaded in ${Math.round(loadTime)}ms`);
        });

        // Real-time notifications (simulated)
        function checkForNotifications() {
            // In a real application, this would check for new orders, reviews, etc.
            // For now, we'll just log that we're checking
            console.log('Checking for new notifications...');
        }

        // Check for notifications every 30 seconds
        setInterval(checkForNotifications, 30000);

        // Initialize tooltips and other interactive elements
        document.querySelectorAll('[title]').forEach(element => {
            element.setAttribute('data-toggle', 'tooltip');
        });

        // Add loading states to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function() {
                if (this.onclick && this.onclick.toString().includes('window.location')) {
                    this.innerHTML = '<i class="spinner" style="width: 16px; height: 16px; border-width: 2px; margin-right: 0.5rem;"></i>' + this.textContent;
                    this.disabled = true;
                }
            });
        });

        console.log('Krua Thai Dashboard initialized successfully');
    </script>
</body>
</html>