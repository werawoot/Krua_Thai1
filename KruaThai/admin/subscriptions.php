<?php
/**
 * Krua Thai - Subscriptions Management
 * File: admin/subscriptions.php
 * Description: Complete subscription management system with real-time tracking and customer insights
 */
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'update_subscription_status':
                $result = updateSubscriptionStatus($pdo, $_POST['subscription_id'], $_POST['status']);
                echo json_encode($result);
                exit;
                
            case 'pause_subscription':
                $result = pauseSubscription($pdo, $_POST['subscription_id'], $_POST['pause_until']);
                echo json_encode($result);
                exit;
                
            case 'cancel_subscription':
                $result = cancelSubscription($pdo, $_POST['subscription_id'], $_POST['reason']);
                echo json_encode($result);
                exit;
                
            case 'get_subscription_details':
                $result = getSubscriptionDetails($pdo, $_POST['subscription_id']);
                echo json_encode($result);
                exit;
                
            case 'update_delivery_preferences':
                $result = updateDeliveryPreferences($pdo, $_POST);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Database Functions
function updateSubscriptionStatus($pdo, $subscriptionId, $status) {
    try {
        $stmt = $pdo->prepare("UPDATE subscriptions SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $subscriptionId]);
        
        if ($stmt->rowCount() > 0) {
            logActivity('subscription_status_updated', $_SESSION['user_id'], getRealIPAddress(), [
                'subscription_id' => $subscriptionId,
                'new_status' => $status
            ]);
            return ['success' => true, 'message' => 'Subscription status updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Subscription not found or no changes made'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating subscription status: ' . $e->getMessage()];
    }
}

function pauseSubscription($pdo, $subscriptionId, $pauseUntil) {
    try {
        $stmt = $pdo->prepare("
            UPDATE subscriptions 
            SET status = 'paused', pause_start_date = CURDATE(), pause_end_date = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$pauseUntil, $subscriptionId]);
        
        if ($stmt->rowCount() > 0) {
            logActivity('subscription_paused', $_SESSION['user_id'], getRealIPAddress(), [
                'subscription_id' => $subscriptionId,
                'pause_until' => $pauseUntil
            ]);
            return ['success' => true, 'message' => 'Subscription paused successfully'];
        } else {
            return ['success' => false, 'message' => 'Subscription not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error pausing subscription: ' . $e->getMessage()];
    }
}

function cancelSubscription($pdo, $subscriptionId, $reason) {
    try {
        $stmt = $pdo->prepare("
            UPDATE subscriptions 
            SET status = 'cancelled', cancellation_reason = ?, cancelled_at = NOW(), cancelled_by = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$reason, $_SESSION['user_id'], $subscriptionId]);
        
        if ($stmt->rowCount() > 0) {
            logActivity('subscription_cancelled', $_SESSION['user_id'], getRealIPAddress(), [
                'subscription_id' => $subscriptionId,
                'reason' => $reason
            ]);
            return ['success' => true, 'message' => 'Subscription cancelled successfully'];
        } else {
            return ['success' => false, 'message' => 'Subscription not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error cancelling subscription: ' . $e->getMessage()];
    }
}

function getSubscriptionDetails($pdo, $subscriptionId) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                   u.email as customer_email,
                   u.phone as customer_phone,
                   u.delivery_address,
                   sp.name as plan_name,
                   sp.meals_per_week,
                   sp.plan_type,
                   (SELECT COUNT(*) FROM orders WHERE subscription_id = s.id) as total_orders,
                   (SELECT COUNT(*) FROM orders WHERE subscription_id = s.id AND status = 'delivered') as delivered_orders,
                   (SELECT SUM(amount) FROM payments WHERE subscription_id = s.id AND status = 'completed') as total_paid
            FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            JOIN subscription_plans sp ON s.plan_id = sp.id
            WHERE s.id = ?
        ");
        $stmt->execute([$subscriptionId]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subscription) {
            // Get recent orders
            $stmt = $pdo->prepare("
                SELECT o.*, COUNT(oi.id) as item_count
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.subscription_id = ?
                GROUP BY o.id
                ORDER BY o.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$subscriptionId]);
            $subscription['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'data' => $subscription];
        } else {
            return ['success' => false, 'message' => 'Subscription not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching subscription details: ' . $e->getMessage()];
    }
}

function updateDeliveryPreferences($pdo, $data) {
    try {
        $subscriptionId = $data['subscription_id'];
        $deliveryDays = json_encode($data['delivery_days'] ?? []);
        $preferredTime = $data['preferred_time'] ?? 'afternoon';
        $specialInstructions = sanitizeInput($data['special_instructions'] ?? '');
        
        $stmt = $pdo->prepare("
            UPDATE subscriptions 
            SET delivery_days = ?, preferred_delivery_time = ?, special_instructions = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$deliveryDays, $preferredTime, $specialInstructions, $subscriptionId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Delivery preferences updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Subscription not found or no changes made'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating delivery preferences: ' . $e->getMessage()];
    }
}

// Fetch Subscriptions Data
try {
    // Get filters from URL
    $statusFilter = $_GET['status'] ?? '';
    $planFilter = $_GET['plan'] ?? '';
    $searchQuery = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    
    if ($statusFilter) {
        $whereConditions[] = "s.status = ?";
        $params[] = $statusFilter;
    }
    
    if ($planFilter) {
        $whereConditions[] = "s.plan_id = ?";
        $params[] = $planFilter;
    }
    
    if ($searchQuery) {
        $whereConditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR s.id LIKE ?)";
        $searchTerm = "%{$searchQuery}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get subscriptions with pagination
    $subscriptions_sql = "
        SELECT s.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email,
               u.phone as customer_phone,
               sp.name as plan_name,
               sp.meals_per_week,
               sp.plan_type,
               (SELECT COUNT(*) FROM orders WHERE subscription_id = s.id) as total_orders,
               (SELECT COUNT(*) FROM orders WHERE subscription_id = s.id AND status = 'delivered') as delivered_orders,
               (SELECT SUM(amount) FROM payments WHERE subscription_id = s.id AND status = 'completed') as total_revenue
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        JOIN subscription_plans sp ON s.plan_id = sp.id
        {$whereClause}
        ORDER BY s.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ";
    
    $stmt = $pdo->prepare($subscriptions_sql);
    $stmt->execute($params);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $count_sql = "
        SELECT COUNT(*) as total
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        JOIN subscription_plans sp ON s.plan_id = sp.id
        {$whereClause}
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $totalSubscriptions = $stmt->fetchColumn();
    $totalPages = ceil($totalSubscriptions / $limit);
    
    // Get statistics
    $stats_sql = "
        SELECT 
            COUNT(*) as total_subscriptions,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_subscriptions,
            SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused_subscriptions,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_subscriptions,
            SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_subscriptions,
            AVG(total_amount) as avg_subscription_value,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_today
        FROM subscriptions
    ";
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get subscription plans for filter
    $stmt = $pdo->prepare("SELECT id, name FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order, name");
    $stmt->execute();
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get revenue data for chart
    $revenue_sql = "
        SELECT DATE(p.payment_date) as date,
               SUM(p.amount) as revenue,
               COUNT(DISTINCT s.id) as subscription_count
        FROM payments p
        JOIN subscriptions s ON p.subscription_id = s.id
        WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND p.status = 'completed'
        GROUP BY DATE(p.payment_date)
        ORDER BY date ASC
    ";
    $stmt = $pdo->prepare($revenue_sql);
    $stmt->execute();
    $revenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $subscriptions = [];
    $stats = [
        'total_subscriptions' => 0, 'active_subscriptions' => 0, 'paused_subscriptions' => 0,
        'cancelled_subscriptions' => 0, 'expired_subscriptions' => 0, 'avg_subscription_value' => 0,
        'new_today' => 0
    ];
    $plans = [];
    $revenueData = [];
    $totalSubscriptions = 0;
    $totalPages = 1;
    error_log("Subscriptions page error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscriptions - Krua Thai Admin</title>
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
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
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

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--brown) 0%, var(--curry) 100%);
            color: var(--white);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-medium);
        }

        .sidebar-header {
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
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

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .sidebar-subtitle {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-section {
            margin-bottom: 1.5rem;
        }

        .nav-section-title {
            padding: 0 1.5rem 0.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.7;
            font-weight: 600;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
            cursor: pointer;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: var(--white);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: var(--white);
            font-weight: 600;
        }

        .nav-icon {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
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
            align-items: center;
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

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* Filters */
        .filters-container {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--white);
            color: var(--text-dark);
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        /* Table */
        .table-container {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
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

        .status-active {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-paused {
            background: rgba(241, 196, 15, 0.1);
            color: #f39c12;
        }

        .status-cancelled {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .status-expired {
            background: rgba(149, 165, 166, 0.1);
            color: #95a5a6;
        }

        .status-pending_payment {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .pagination a {
            background: var(--white);
            color: var(--text-dark);
            border: 1px solid var(--border-light);
        }

        .pagination a:hover {
            background: var(--curry);
            color: var(--white);
            border-color: var(--curry);
        }

        .pagination .current {
            background: var(--curry);
            color: var(--white);
            border: 1px solid var(--curry);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            animation: fadeIn 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-medium);
            animation: slideIn 0.3s ease;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-gray);
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--text-dark);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-light);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        /* Customer Info */
        .customer-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: var(--cream);
            padding: 1rem;
            border-radius: var(--radius-sm);
            border-left: 4px solid var(--curry);
        }

        .info-label {
            font-size: 0.8rem;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--text-dark);
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

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-light);
            border-top: 4px solid var(--curry);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 2rem;
            right: 2rem;
            z-index: 3000;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .toast {
            background: var(--white);
            border-left: 4px solid var(--curry);
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-medium);
            padding: 1rem;
            margin-bottom: 0.5rem;
            max-width: 400px;
            animation: slideInRight 0.3s ease;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .toast.success {
            border-left-color: var(--success);
        }

        .toast.error {
            border-left-color: var(--danger);
        }

        .toast.warning {
            border-left-color: var(--warning);
        }

        .toast-icon {
            font-size: 1.2rem;
            margin-top: 0.2rem;
        }

        .toast.success .toast-icon {
            color: var(--success);
        }

        .toast.error .toast-icon {
            color: var(--danger);
        }

        .toast.warning .toast-icon {
            color: var(--warning);
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-dark);
        }

        .toast-message {
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        .toast-close {
            background: none;
            border: none;
            color: var(--text-gray);
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0;
            margin-left: auto;
        }

        .toast-close:hover {
            color: var(--text-dark);
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 1rem;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: scale(0.8) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 100%;
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .table-container {
                overflow-x: auto;
            }

            .customer-info {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                margin: 1rem;
            }

            .toast-container {
                top: 1rem;
                right: 1rem;
                left: 1rem;
            }

            .toast {
                max-width: none;
            }
        }

        @media (max-width: 480px) {
            .btn-sm {
                padding: 0.4rem 0.8rem;
                font-size: 0.75rem;
            }

            .table th,
            .table td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
           <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../assets/image/LOGO_White Trans.png" 
                         alt="Krua Thai Logo" 
                         class="logo-image"
                         loading="lazy">
                </div>
                <div class="sidebar-title">Krua Thai</div>
                <div class="sidebar-subtitle">Admin Panel</div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="orders.php" class="nav-item">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                    <a href="menus.php" class="nav-item ">
                        <i class="nav-icon fas fa-utensils"></i>
                        <span>Menus</span>
                    </a>
                    <a href="subscriptions.php" class="nav-item active">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <span>Subscriptions</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="users.php" class="nav-item">
                        <i class="nav-icon fas fa-users"></i>
                        <span>Users</span>
                    </a>
                    <a href="inventory.php" class="nav-item">
                        <i class="nav-icon fas fa-boxes"></i>
                        <span>Inventory</span>
                    </a>
                    <a href="delivery-zones.php" class="nav-item">
                        <i class="nav-icon fas fa-map-marked-alt"></i>
                        <span>Delivery Zones</span>
                    </a>
                    <a href="reviews.php" class="nav-item">
                        <i class="nav-icon fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                    <a href="complaints.php" class="nav-item">
                        <i class="nav-icon fas fa-exclamation-triangle"></i>
                        <span>Complaints</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Financial</div>
                    <a href="payments.php" class="nav-item">
                        <i class="nav-icon fas fa-credit-card"></i>
                        <span>Payments</span>
                    </a>
                    <a href="reports.php" class="nav-item">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="settings.php" class="nav-item">
                        <i class="nav-icon fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="logout.php" class="nav-item" onclick="logout()" style="color: rgba(255, 255, 255, 0.9);">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="page-title">Subscription Management</h1>
                        <p class="page-subtitle">Monitor and manage customer subscriptions with real-time insights</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="refreshSubscriptions()">
                            <i class="fas fa-refresh"></i>
                            Refresh
                        </button>
                        <button class="btn btn-info" onclick="exportSubscriptions()">
                            <i class="fas fa-download"></i>
                            Export
                        </button>
                        <button class="btn btn-primary" onclick="showRevenueChart()">
                            <i class="fas fa-chart-line"></i>
                            Analytics
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_subscriptions']) ?></div>
                    <div class="stat-label">Total Subscriptions</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['active_subscriptions']) ?></div>
                    <div class="stat-label">Active Subscriptions</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-pause"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['paused_subscriptions']) ?></div>
                    <div class="stat-label">Paused Subscriptions</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-value">₿<?= number_format($stats['avg_subscription_value'], 0) ?></div>
                    <div class="stat-label">Avg. Subscription Value</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['new_today']) ?></div>
                    <div class="stat-label">New Today</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-container">
                <form method="GET" action="" id="filtersForm">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label class="form-label" for="statusFilter">Status</label>
                            <select id="statusFilter" name="status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="paused" <?= $statusFilter === 'paused' ? 'selected' : '' ?>>Paused</option>
                                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
                                <option value="pending_payment" <?= $statusFilter === 'pending_payment' ? 'selected' : '' ?>>Pending Payment</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="planFilter">Plan</label>
                            <select id="planFilter" name="plan" class="form-control">
                                <option value="">All Plans</option>
                                <?php foreach ($plans as $plan): ?>
                                    <option value="<?= $plan['id'] ?>" <?= $planFilter === $plan['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($plan['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="searchInput">Search</label>
                            <input type="text" 
                                   id="searchInput" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Customer name, email, or ID..."
                                   value="<?= htmlspecialchars($searchQuery) ?>">
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                                Filter
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                                <i class="fas fa-times"></i>
                                Clear
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Subscriptions Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">
                        <i class="fas fa-calendar-alt" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        Subscriptions (<?= number_format($totalSubscriptions) ?> total)
                    </h3>
                </div>

                <?php if (!empty($subscriptions)): ?>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Start Date</th>
                                <th>Next Billing</th>
                                <th>Revenue</th>
                                <th>Orders</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscriptions as $subscription): ?>
                            <tr>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($subscription['customer_name']) ?></strong>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-gray);">
                                        <?= htmlspecialchars($subscription['customer_email']) ?>
                                    </div>
                                    <?php if ($subscription['customer_phone']): ?>
                                    <div style="font-size: 0.8rem; color: var(--text-gray);">
                                        <?= htmlspecialchars($subscription['customer_phone']) ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($subscription['plan_name']) ?></strong>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-gray);">
                                        <?= $subscription['meals_per_week'] ?> meals/week
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-gray);">
                                        <?= ucfirst($subscription['plan_type']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $subscription['status'] ?>">
                                        <i class="fas fa-circle"></i>
                                        <?= ucfirst(str_replace('_', ' ', $subscription['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= date('M d, Y', strtotime($subscription['start_date'])) ?>
                                </td>
                                <td>
                                    <?php if ($subscription['next_billing_date']): ?>
                                        <?= date('M d, Y', strtotime($subscription['next_billing_date'])) ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: var(--curry);">
                                        ₿<?= number_format($subscription['total_revenue'] ?? 0, 0) ?>
                                    </strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= $subscription['delivered_orders'] ?></strong>
                                        <span style="color: var(--text-gray);">/ <?= $subscription['total_orders'] ?></span>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-gray);">
                                        delivered
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <button class="btn btn-info btn-sm btn-icon" 
                                                onclick="viewSubscription('<?= $subscription['id'] ?>')"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if ($subscription['status'] === 'active'): ?>
                                        <button class="btn btn-warning btn-sm btn-icon" 
                                                onclick="pauseSubscription('<?= $subscription['id'] ?>')"
                                                title="Pause Subscription">
                                            <i class="fas fa-pause"></i>
                                        </button>
                                        <?php elseif ($subscription['status'] === 'paused'): ?>
                                        <button class="btn btn-success btn-sm btn-icon" 
                                                onclick="resumeSubscription('<?= $subscription['id'] ?>')"
                                                title="Resume Subscription">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($subscription['status'], ['active', 'paused'])): ?>
                                        <button class="btn btn-danger btn-sm btn-icon" 
                                                onclick="cancelSubscription('<?= $subscription['id'] ?>', '<?= htmlspecialchars($subscription['customer_name']) ?>')"
                                                title="Cancel Subscription">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&status=<?= $statusFilter ?>&plan=<?= $planFilter ?>&search=<?= urlencode($searchQuery) ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>&status=<?= $statusFilter ?>&plan=<?= $planFilter ?>&search=<?= urlencode($searchQuery) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&status=<?= $statusFilter ?>&plan=<?= $planFilter ?>&search=<?= urlencode($searchQuery) ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div style="text-align: center; padding: 4rem 2rem; color: var(--text-gray);">
                    <i class="fas fa-calendar-alt" style="font-size: 4rem; margin-bottom: 2rem; opacity: 0.3;"></i>
                    <h3>No Subscriptions Found</h3>
                    <p>No subscriptions match your current filters. Try adjusting your search criteria.</p>
                    <button class="btn btn-primary" onclick="clearFilters()">
                        <i class="fas fa-refresh"></i>
                        Clear Filters
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Subscription Details Modal -->
    <div class="modal" id="subscriptionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Subscription Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Pause Subscription Modal -->
    <div class="modal" id="pauseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Pause Subscription</h3>
                <button class="modal-close" onclick="closePauseModal()">&times;</button>
            </div>
            <form id="pauseForm">
                <div class="modal-body">
                    <input type="hidden" id="pauseSubscriptionId">
                    
                    <div class="form-group">
                        <label class="form-label" for="pauseUntil">
                            Pause Until <span style="color: var(--danger);">*</span>
                        </label>
                        <input type="date" 
                               id="pauseUntil" 
                               name="pause_until" 
                               class="form-control" 
                               required
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        <div style="font-size: 0.8rem; color: var(--text-gray); margin-top: 0.25rem;">
                            The subscription will automatically resume on this date
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closePauseModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-pause"></i>
                        Pause Subscription
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancel Subscription Modal -->
    <div class="modal" id="cancelModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Cancel Subscription</h3>
                <button class="modal-close" onclick="closeCancelModal()">&times;</button>
            </div>
            <form id="cancelForm">
                <div class="modal-body">
                    <input type="hidden" id="cancelSubscriptionId">
                    
                    <div style="background: rgba(231, 76, 60, 0.1); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; border-left: 4px solid var(--danger);">
                        <h4 style="color: var(--danger); margin-bottom: 0.5rem;">
                            <i class="fas fa-exclamation-triangle"></i>
                            Warning: This action cannot be undone
                        </h4>
                        <p style="margin: 0; color: var(--text-dark);">
                            Cancelling this subscription will stop all future deliveries and billing.
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="cancellationReason">
                            Cancellation Reason <span style="color: var(--danger);">*</span>
                        </label>
                        <textarea id="cancellationReason" 
                                  name="reason" 
                                  class="form-control" 
                                  placeholder="Please provide a reason for cancellation..."
                                  rows="3"
                                  required></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCancelModal()">
                        Keep Subscription
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i>
                        Cancel Subscription
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Revenue Chart Modal -->
    <div class="modal" id="chartModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Subscription Revenue Analytics</h3>
                <button class="modal-close" onclick="closeChartModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Global variables
        let revenueChart;
        const revenueData = <?= json_encode($revenueData) ?>;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeEventListeners();
        });

        // Initialize event listeners
        function initializeEventListeners() {
            // Form submissions
            document.getElementById('pauseForm').addEventListener('submit', handlePauseSubmit);
            document.getElementById('cancelForm').addEventListener('submit', handleCancelSubmit);
            
            // Filter form auto-submit
            const filterInputs = document.querySelectorAll('#filtersForm select, #filtersForm input');
            filterInputs.forEach(input => {
                if (input.type === 'text') {
                    let timeout;
                    input.addEventListener('input', function() {
                        clearTimeout(timeout);
                        timeout = setTimeout(() => {
                            document.getElementById('filtersForm').submit();
                        }, 500);
                    });
                } else {
                    input.addEventListener('change', function() {
                        document.getElementById('filtersForm').submit();
                    });
                }
            });
        }

        // View subscription details
        function viewSubscription(subscriptionId) {
            const formData = new FormData();
            formData.append('action', 'get_subscription_details');
            formData.append('subscription_id', subscriptionId);

            fetch('subscriptions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySubscriptionDetails(data.data);
                    document.getElementById('subscriptionModal').classList.add('show');
                } else {
                    showToast('error', 'Error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Error', 'An error occurred while fetching subscription details.');
            });
        }

        // Display subscription details
        function displaySubscriptionDetails(subscription) {
            const modalBody = document.getElementById('modalBody');
            
            modalBody.innerHTML = `
                <div class="customer-info">
                    <div class="info-card">
                        <div class="info-label">Customer</div>
                        <div class="info-value">${escapeHtml(subscription.customer_name)}</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Email</div>
                        <div class="info-value">${escapeHtml(subscription.customer_email)}</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Phone</div>
                        <div class="info-value">${escapeHtml(subscription.customer_phone || 'Not provided')}</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Plan</div>
                        <div class="info-value">${escapeHtml(subscription.plan_name)}</div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status-badge status-${subscription.status}">
                                ${subscription.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                            </span>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Total Revenue</div>
                        <div class="info-value" style="color: var(--curry);">₿${parseFloat(subscription.total_paid || 0).toLocaleString()}</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <div>
                        <h4 style="margin-bottom: 1rem; color: var(--curry);">
                            <i class="fas fa-calendar"></i>
                            Subscription Details
                        </h4>
                        <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm);">
                            <div style="margin-bottom: 0.5rem;">
                                <strong>Start Date:</strong> ${new Date(subscription.start_date).toLocaleDateString()}
                            </div>
                            <div style="margin-bottom: 0.5rem;">
                                <strong>Billing Cycle:</strong> ${subscription.billing_cycle}
                            </div>
                            <div style="margin-bottom: 0.5rem;">
                                <strong>Meals per Week:</strong> ${subscription.meals_per_week}
                            </div>
                            <div style="margin-bottom: 0.5rem;">
                                <strong>Total Amount:</strong> ₿${parseFloat(subscription.total_amount).toLocaleString()}
                            </div>
                            ${subscription.next_billing_date ? `
                                <div style="margin-bottom: 0.5rem;">
                                    <strong>Next Billing:</strong> ${new Date(subscription.next_billing_date).toLocaleDateString()}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div>
                        <h4 style="margin-bottom: 1rem; color: var(--curry);">
                            <i class="fas fa-truck"></i>
                            Delivery Information
                        </h4>
                        <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm);">
                            <div style="margin-bottom: 0.5rem;">
                                <strong>Address:</strong><br>
                                ${escapeHtml(subscription.delivery_address || 'Not provided')}
                            </div>
                            <div style="margin-bottom: 0.5rem;">
                                <strong>Preferred Time:</strong> ${subscription.preferred_delivery_time || 'Not specified'}
                            </div>
                            ${subscription.special_instructions ? `
                                <div style="margin-bottom: 0.5rem;">
                                    <strong>Special Instructions:</strong><br>
                                    ${escapeHtml(subscription.special_instructions)}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>

                <div>
                    <h4 style="margin-bottom: 1rem; color: var(--curry);">
                        <i class="fas fa-shopping-cart"></i>
                        Recent Orders
                    </h4>
                    ${subscription.recent_orders && subscription.recent_orders.length > 0 ? `
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Status</th>
                                        <th>Delivery Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${subscription.recent_orders.map(order => `
                                        <tr>
                                            <td><strong>${escapeHtml(order.order_number)}</strong></td>
                                            <td>${new Date(order.created_at).toLocaleDateString()}</td>
                                            <td>${order.item_count} items</td>
                                            <td>
                                                <span class="status-badge status-${order.status}">
                                                    ${order.status.replace('_', ' ')}
                                                </span>
                                            </td>
                                            <td>${new Date(order.delivery_date).toLocaleDateString()}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : `
                        <div style="text-align: center; padding: 2rem; color: var(--text-gray);">
                            <i class="fas fa-shopping-cart" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                            <p>No orders found for this subscription</p>
                        </div>
                    `}
                </div>
            `;
        }

        // Pause subscription
        function pauseSubscription(subscriptionId) {
            document.getElementById('pauseSubscriptionId').value = subscriptionId;
            document.getElementById('pauseModal').classList.add('show');
        }

        // Resume subscription
        function resumeSubscription(subscriptionId) {
            if (!confirm('Are you sure you want to resume this subscription?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_subscription_status');
            formData.append('subscription_id', subscriptionId);
            formData.append('status', 'active');

            fetch('subscriptions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'Success', 'Subscription resumed successfully.');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('error', 'Error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Error', 'An error occurred while resuming the subscription.');
            });
        }

        // Cancel subscription
        function cancelSubscription(subscriptionId, customerName) {
            document.getElementById('cancelSubscriptionId').value = subscriptionId;
            document.getElementById('cancelModal').classList.add('show');
        }

        // Handle pause form submission
        function handlePauseSubmit(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'pause_subscription');
            formData.append('subscription_id', document.getElementById('pauseSubscriptionId').value);
            formData.append('pause_until', document.getElementById('pauseUntil').value);

            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;

            fetch('subscriptions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'Success', 'Subscription paused successfully.');
                    closePauseModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('error', 'Error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Error', 'An error occurred while pausing the subscription.');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        // Handle cancel form submission
        function handleCancelSubmit(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'cancel_subscription');
            formData.append('subscription_id', document.getElementById('cancelSubscriptionId').value);
            formData.append('reason', document.getElementById('cancellationReason').value);

            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;

            fetch('subscriptions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', 'Success', 'Subscription cancelled successfully.');
                    closeCancelModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast('error', 'Error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'Error', 'An error occurred while cancelling the subscription.');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        // Modal functions
        function closeModal() {
            document.getElementById('subscriptionModal').classList.remove('show');
        }

        function closePauseModal() {
            document.getElementById('pauseModal').classList.remove('show');
            document.getElementById('pauseForm').reset();
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').classList.remove('show');
            document.getElementById('cancelForm').reset();
        }

        function closeChartModal() {
            document.getElementById('chartModal').classList.remove('show');
            if (revenueChart) {
                revenueChart.destroy();
                revenueChart = null;
            }
        }

        // Show revenue chart
        function showRevenueChart() {
            document.getElementById('chartModal').classList.add('show');
            
            setTimeout(() => {
                initializeRevenueChart();
            }, 100);
        }

        // Initialize revenue chart
        function initializeRevenueChart() {
            if (revenueChart) {
                revenueChart.destroy();
            }

            const ctx = document.getElementById('revenueChart').getContext('2d');
            
            const labels = revenueData.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });
            
            const revenues = revenueData.map(item => parseFloat(item.revenue));
            const subscriptionCounts = revenueData.map(item => parseInt(item.subscription_count));

            revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue (THB)',
                        data: revenues,
                        borderColor: '#cf723a',
                        backgroundColor: 'rgba(207, 114, 58, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#cf723a',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        yAxisID: 'y'
                    }, {
                        label: 'Active Subscriptions',
                        data: subscriptionCounts,
                        borderColor: '#adb89d',
                        backgroundColor: 'rgba(173, 184, 157, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        pointBackgroundColor: '#adb89d',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Subscription Revenue & Growth (Last 30 Days)'
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
                            type: 'linear',
                            display: true,
                            position: 'left',
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
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                color: '#7f8c8d'
                            }
                        }
                    }
                }
            });
        }

        // Utility functions
        function refreshSubscriptions() {
            showToast('info', 'Refreshing', 'Refreshing subscription data...');
            location.reload();
        }

        function clearFilters() {
            window.location.href = 'subscriptions.php';
        }

        function exportSubscriptions() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('export', 'csv');
            window.open(currentUrl.toString(), '_blank');
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Show toast notification
        function showToast(type, title, message) {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast-' + Date.now();
            
            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-times-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.id = toastId;
            toast.innerHTML = `
                <i class="toast-icon ${icons[type] || icons.info}"></i>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="removeToast('${toastId}')">&times;</button>
            `;

            toastContainer.appendChild(toast);

            // Auto remove after 5 seconds
            setTimeout(() => {
                removeToast(toastId);
            }, 5000);
        }

        // Remove toast
        function removeToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.style.animation = 'slideInRight 0.3s ease reverse';
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            const modals = ['subscriptionModal', 'pauseModal', 'cancelModal', 'chartModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (e.target === modal) {
                    modal.classList.remove('show');
                    if (modalId === 'chartModal' && revenueChart) {
                        revenueChart.destroy();
                        revenueChart = null;
                    }
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to close modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => {
                    modal.classList.remove('show');
                    if (modal.id === 'chartModal' && revenueChart) {
                        revenueChart.destroy();
                        revenueChart = null;
                    }
                });
            }
            
            // Ctrl+R to refresh
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshSubscriptions();
            }
        });

        console.log('Krua Thai Subscriptions Management initialized successfully');
    </script>
</body>
</html>