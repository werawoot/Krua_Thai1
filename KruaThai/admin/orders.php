<?php
/**
 * Krua Thai - Orders Management (แก้ไขแล้ว)
 * File: admin/orders.php
 */
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Path ที่ถูกต้องสำหรับ admin folder
require_once '../config/database.php';
require_once '../includes/functions.php';

// ✅ ลบ generateUUID() ออกแล้ว - ใช้จาก includes/functions.php

// Debug: ตรวจสอบ database
if (!isset($pdo)) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        echo "<!-- ✅ Database connection successful -->";
    } catch (Exception $e) {
        die("❌ Database connection failed: " . $e->getMessage());
    }
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $orderCount = $stmt->fetchColumn();
    echo "<!-- DEBUG: Found $orderCount orders in database -->";
    
    if ($orderCount == 0) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'");
        $subsCount = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM subscription_menus");
        $menuCount = $stmt->fetchColumn();
        
        echo "<!-- DEBUG: $subsCount active subscriptions, $menuCount subscription menus -->";
        echo "<!-- DEBUG: Need to run generate_orders.php to create orders! -->";
    }
} catch (Exception $e) {
    echo "<!-- DEBUG ERROR: " . $e->getMessage() . " -->";
}

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
            case 'update_order_status':
                $result = updateOrderStatus($pdo, $_POST['order_id'], $_POST['status']);
                echo json_encode($result);
                exit;
                
            case 'assign_rider':
                $result = assignRider($pdo, $_POST['order_id'], $_POST['rider_id']);
                echo json_encode($result);
                exit;
                
            case 'get_order_details':
                $result = getOrderDetails($pdo, $_POST['order_id']);
                echo json_encode($result);
                exit;
                
            case 'bulk_update_status':
                $result = bulkUpdateStatus($pdo, $_POST['order_ids'], $_POST['status']);
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
            logActivity('order_status_updated', $_SESSION['user_id'], getRealIPAddress(), [
                'order_id' => $orderId,
                'new_status' => $status
            ]);
            return ['success' => true, 'message' => 'Order status updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Order not found or no changes made'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating order status: ' . $e->getMessage()];
    }
}

function assignRider($pdo, $orderId, $riderId) {
    try {
        $stmt = $pdo->prepare("UPDATE orders SET assigned_rider_id = ?, status = 'out_for_delivery', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$riderId, $orderId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Rider assigned successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to assign rider'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error assigning rider: ' . $e->getMessage()];
    }
}

function getOrderDetails($pdo, $orderId) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                   u.email as customer_email,
                   u.phone as customer_phone,
                   CONCAT(r.first_name, ' ', r.last_name) as rider_name,
                   r.phone as rider_phone
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            LEFT JOIN users r ON o.assigned_rider_id = r.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // Get order items
            $stmt = $pdo->prepare("
                SELECT oi.*, m.name as menu_name, m.name_thai
                FROM order_items oi
                JOIN menus m ON oi.menu_id = m.id
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$orderId]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'data' => $order];
        } else {
            return ['success' => false, 'message' => 'Order not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching order details: ' . $e->getMessage()];
    }
}

function bulkUpdateStatus($pdo, $orderIds, $status) {
    try {
        $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
        $params = array_merge($orderIds, [$status]);
        
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$status], $orderIds));
        
        return ['success' => true, 'message' => $stmt->rowCount() . ' orders updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating orders: ' . $e->getMessage()];
    }
}

// Get filter parameters และโค้ดส่วนอื่นๆ ต่อไปเหมือนเดิม...
// (ใส่โค้ดส่วนที่เหลือจากไฟล์เดิม โดยไม่ต้องมี generateUUID function)

$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = ['1=1'];
$params = [];

if ($status_filter) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $where_conditions[] = "DATE(o.created_at) = ?";
    $params[] = $date_filter;
}

if ($search) {
    $where_conditions[] = "(o.order_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get total count
    $count_sql = "
        SELECT COUNT(*) as total
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_orders = $stmt->fetchColumn();
    $total_pages = ceil($total_orders / $limit);

    // Get orders
    $orders_sql = "
        SELECT o.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email,
               u.phone as customer_phone,
               CONCAT(r.first_name, ' ', r.last_name) as rider_name,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        LEFT JOIN users r ON o.assigned_rider_id = r.id
        WHERE $where_clause
        ORDER BY o.created_at DESC 
        LIMIT ? OFFSET ?
    ";
    $stmt = $pdo->prepare($orders_sql);
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get order statistics
    $stats_sql = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
            SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) as preparing_orders,
            SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_orders,
            SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) as out_for_delivery_orders,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_orders
        FROM orders o
        WHERE $where_clause
    ";
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get available riders
    $riders_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE role = 'rider' AND status = 'active'";
    $stmt = $pdo->prepare($riders_sql);
    $stmt->execute();
    $riders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $orders = [];
    $stats = [
        'total_orders' => 0, 'pending_orders' => 0, 'confirmed_orders' => 0, 
        'preparing_orders' => 0, 'ready_orders' => 0, 'out_for_delivery_orders' => 0, 
        'delivered_orders' => 0, 'cancelled_orders' => 0, 'today_orders' => 0
    ];
    $riders = [];
    $total_orders = 0;
    $total_pages = 1;
    error_log("Orders page error: " . $e->getMessage());
}

// ตัวอย่างการแสดงผลเมื่อไม่มี Orders
if (empty($orders)) {
    echo "<!-- DEBUG: No orders found. Need to run generate_orders.php -->";
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Krua Thai Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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

        /* Sidebar - Same as dashboard */
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

        /* Filter Controls */
        .filter-controls {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-control {
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        /* Orders Table */
        .orders-container {
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
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .table-wrapper {
            overflow-x: auto;
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
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background: #fafafa;
        }

        .table tbody tr.selected {
            background: rgba(207, 114, 58, 0.1);
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

        .status-ready {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
        }

        .status-out_for_delivery {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .status-delivered {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-cancelled {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        /* Priority Badges */
        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.6rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .priority-normal {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .priority-high {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .priority-urgent {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 1.5rem;
            background: var(--cream);
            border-top: 1px solid var(--border-light);
        }

        .pagination-info {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
        }

        .page-btn {
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-light);
            background: var(--white);
            color: var(--text-dark);
            text-decoration: none;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .page-btn:hover,
        .page-btn.active {
            background: var(--curry);
            color: var(--white);
            border-color: var(--curry);
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
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

        /* Bulk Actions */
        .bulk-actions {
            background: var(--white);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: none;
            align-items: center;
            gap: 1rem;
        }

        .bulk-actions.show {
            display: flex;
        }

        .selected-count {
            font-weight: 600;
            color: var(--curry);
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

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
            .sidebar {
                width: 260px;
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

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
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-row {
                grid-template-columns: 1fr;
            }

            .table-wrapper {
                font-size: 0.85rem;
            }

            .table th,
            .table td {
                padding: 0.75rem 0.5rem;
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
                    <a href="orders.php" class="nav-item active">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                    <a href="menus.php" class="nav-item">
                        <i class="nav-icon fas fa-utensils"></i>
                        <span>Menus</span>
                    </a>
                    <a href="subscriptions.php" class="nav-item">
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
                        <h1 class="page-title">
                            <i class="fas fa-shopping-cart" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Orders Management
                        </h1>
                        <p class="page-subtitle">Manage and track all customer orders in real-time</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="refreshOrders()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                        <button class="btn btn-primary" onclick="exportOrders()">
                            <i class="fas fa-download"></i>
                            Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Order Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_orders']) ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['pending_orders']) ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['delivered_orders']) ?></div>
                    <div class="stat-label">Delivered Today</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['today_orders']) ?></div>
                    <div class="stat-label">Today's Orders</div>
                </div>
            </div>

            <!-- Filter Controls -->
            <div class="filter-controls">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="form-group">
                            <label class="form-label">Status Filter</label>
                            <select name="status" class="form-control">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="preparing" <?= $status_filter === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                                <option value="ready" <?= $status_filter === 'ready' ? 'selected' : '' ?>>Ready</option>
                                <option value="out_for_delivery" <?= $status_filter === 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                                <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Date Filter</label>
                            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Order #, Customer name, Email..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                                Filter
                            </button>
                        </div>
                        
                        <div class="form-group">
                            <a href="orders.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="orders-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="fas fa-list" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        Orders List (<?= number_format($total_orders) ?> total)
                    </div>
                    <div>
                        <button class="btn btn-sm btn-secondary" onclick="selectAllOrders()">
                            <i class="fas fa-check-square"></i>
                            Select All
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="showBulkActions()" id="bulkActionsBtn" style="display: none;">
                            <i class="fas fa-tasks"></i>
                            Bulk Actions
                        </button>
                    </div>
                </div>

                <!-- Bulk Actions Bar -->
                <div class="bulk-actions" id="bulkActions">
                    <div class="selected-count">
                        <span id="selectedCount">0</span> orders selected
                    </div>
                    <select id="bulkStatusSelect" class="form-control" style="width: auto;">
                        <option value="">Change Status To...</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="preparing">Preparing</option>
                        <option value="ready">Ready</option>
                        <option value="out_for_delivery">Out for Delivery</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button class="btn btn-sm btn-success" onclick="applyBulkUpdate()">
                        <i class="fas fa-check"></i>
                        Apply
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="clearSelection()">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                </div>

                <div class="table-wrapper">
                    <?php if (!empty($orders)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th>Delivery Date</th>
                                <th>Rider</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                            <tr data-order-id="<?= $order['id'] ?>">
                                <td>
                                    <input type="checkbox" class="order-checkbox" value="<?= $order['id'] ?>" onchange="updateBulkActions()">
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                    <?php if ($order['special_notes']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-gray);">
                                            <i class="fas fa-sticky-note"></i>
                                            Has notes
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($order['customer_name']) ?></strong>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-gray);">
                                        <?= htmlspecialchars($order['customer_email']) ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-gray);">
                                        <?= htmlspecialchars($order['customer_phone']) ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="priority-badge priority-normal">
                                        <?= $order['item_count'] ?> items
                                    </span>
                                </td>
                                <td>
                                    <select class="form-control status-select" data-order-id="<?= $order['id'] ?>" onchange="updateOrderStatus(this)">
                                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="preparing" <?= $order['status'] === 'preparing' ? 'selected' : '' ?>>Preparing</option>
                                        <option value="ready" <?= $order['status'] === 'ready' ? 'selected' : '' ?>>Ready</option>
                                        <option value="out_for_delivery" <?= $order['status'] === 'out_for_delivery' ? 'selected' : '' ?>>Out for Delivery</option>
                                        <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </td>
                                <td>
                                    <?php 
                                        $deliveryDate = new DateTime($order['delivery_date']);
                                        echo $deliveryDate->format('M d, Y');
                                    ?>
                                    <?php if ($order['delivery_time_slot']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-gray);">
                                            <?= htmlspecialchars($order['delivery_time_slot']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['rider_name']): ?>
                                        <div>
                                            <strong><?= htmlspecialchars($order['rider_name']) ?></strong>
                                        </div>
                                    <?php else: ?>
                                        <select class="form-control rider-select" data-order-id="<?= $order['id'] ?>" onchange="assignRider(this)">
                                            <option value="">Assign Rider</option>
                                            <?php foreach ($riders as $rider): ?>
                                                <option value="<?= $rider['id'] ?>"><?= htmlspecialchars($rider['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $createdDate = new DateTime($order['created_at']);
                                        echo $createdDate->format('M d, H:i');
                                    ?>
                                </td>
                        <td>
    <div style="display: flex; gap: 0.5rem;">
        <!-- ✅ Print Order -->
        <a href="print-order.php?id=<?= $order['id'] ?>" 
           class="btn btn-icon btn-success btn-sm" 
           title="Print Order"
           target="_blank">
            <i class="fas fa-print"></i>
        </a>
        
        <!-- ✅ Edit Order -->
        <a href="edit-order.php?id=<?= $order['id'] ?>" 
           class="btn btn-icon btn-warning btn-sm" 
           title="Edit Order">
            <i class="fas fa-edit"></i>
        </a>
    </div>
</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
               <?php else: ?>
<div style="text-align: center; padding: 4rem; color: var(--text-gray);">
    <i class="fas fa-shopping-cart" style="font-size: 4rem; margin-bottom: 2rem; opacity: 0.3;"></i>
    <h3>No orders found</h3>
    <p><strong>สาเหตุ:</strong> ยังไม่มี Orders ในระบบ</p>
    <p>ระบบ Krua Thai ต้องสร้าง Orders จาก Subscriptions ก่อน</p>
    <div style="margin-top: 1.5rem;">
        <p style="background: #fff3cd; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
            📋 <strong>วิธีแก้:</strong><br>
            1. รัน SQL Script เพื่อสร้าง Orders จาก Subscription Menus<br>
            2. หรือสร้างไฟล์ generate_orders.php
        </p>
    </div>
    <a href="subscriptions.php" class="btn btn-primary">
        <i class="fas fa-calendar-alt"></i>
        ดู Subscriptions
    </a>
</div>
<?php endif; ?>
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?= (($page - 1) * $limit) + 1 ?> to <?= min($page * $limit, $total_orders) ?> of <?= number_format($total_orders) ?> orders
                    </div>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn">
                                <i class="fas fa-chevron-left"></i>
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                               class="page-btn <?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn">
                                Next
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal" id="orderDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Order Details</h3>
                <button class="modal-close" onclick="closeModal('orderDetailsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <div class="loading-overlay">
                    <div class="spinner"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('orderDetailsModal')">Close</button>
                <button class="btn btn-primary" onclick="printOrderDetails()">
                    <i class="fas fa-print"></i>
                    Print
                </button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Global variables
        let selectedOrders = new Set();
        let currentOrderId = null;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Orders page initialized');
            updateBulkActions();
        });

        // Refresh orders
        function refreshOrders() {
            showToast('Refreshing orders...', 'info');
            window.location.reload();
        }

        // Export orders
        function exportOrders() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.open(`export.php?${params.toString()}`, '_blank');
            showToast('Export started...', 'info');
        }

        // Update order status
        function updateOrderStatus(selectElement) {
            const orderId = selectElement.dataset.orderId;
            const newStatus = selectElement.value;
            const originalStatus = selectElement.dataset.original || selectElement.value;
            
            // Store original value
            selectElement.dataset.original = originalStatus;
            
            // Disable select during update
            selectElement.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_order_status&order_id=${orderId}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                selectElement.disabled = false;
                if (data.success) {
                    showToast(data.message, 'success');
                    // Update the original value
                    selectElement.dataset.original = newStatus;
                } else {
                    showToast(data.message, 'error');
                    // Revert to original value
                    selectElement.value = originalStatus;
                }
            })
            .catch(error => {
                selectElement.disabled = false;
                console.error('Error updating status:', error);
                showToast('Error updating order status', 'error');
                // Revert to original value
                selectElement.value = originalStatus;
            });
        }

        // Assign rider
        function assignRider(selectElement) {
            const orderId = selectElement.dataset.orderId;
            const riderId = selectElement.value;
            
            if (!riderId) return;
            
            selectElement.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=assign_rider&order_id=${orderId}&rider_id=${riderId}`
            })
            .then(response => response.json())
            .then(data => {
                selectElement.disabled = false;
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => refreshOrders(), 1000);
                } else {
                    showToast(data.message, 'error');
                    selectElement.value = '';
                }
            })
            .catch(error => {
                selectElement.disabled = false;
                console.error('Error assigning rider:', error);
                showToast('Error assigning rider', 'error');
                selectElement.value = '';
            });
        }

        // View order details
        function viewOrderDetails(orderId) {
            currentOrderId = orderId;
            document.getElementById('orderDetailsModal').classList.add('show');
            
            // Show loading
            document.getElementById('orderDetailsContent').innerHTML = `
                <div class="loading-overlay">
                    <div class="spinner"></div>
                </div>
            `;
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_order_details&order_id=${orderId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayOrderDetails(data.data);
                } else {
                    document.getElementById('orderDetailsContent').innerHTML = `
                        <div class="text-center" style="padding: 2rem; color: var(--text-gray);">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                            <h3>Error Loading Order</h3>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error fetching order details:', error);
                document.getElementById('orderDetailsContent').innerHTML = `
                    <div class="text-center" style="padding: 2rem; color: var(--text-gray);">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                        <h3>Error Loading Order</h3>
                        <p>Failed to load order details. Please try again.</p>
                    </div>
                `;
            });
        }

        // Display order details
        function displayOrderDetails(order) {
            const deliveryDate = new Date(order.delivery_date).toLocaleDateString();
            const createdDate = new Date(order.created_at).toLocaleString();
            
            let itemsHtml = '';
            if (order.items && order.items.length > 0) {
                itemsHtml = order.items.map(item => `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid var(--border-light);">
                        <div>
                            <strong>${escapeHtml(item.menu_name)}</strong>
                            ${item.name_thai ? `<div style="font-size: 0.9rem; color: var(--text-gray);">${escapeHtml(item.name_thai)}</div>` : ''}
                        </div>
                        <div style="text-align: right;">
                            <div>Qty: ${item.quantity}</div>
                            <div style="font-weight: 600;">₿${parseFloat(item.menu_price).toFixed(2)}</div>
                        </div>
                    </div>
                `).join('');
            } else {
                itemsHtml = '<p style="color: var(--text-gray);">No items found</p>';
            }
            
            document.getElementById('orderDetailsContent').innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <h4 style="margin-bottom: 1rem; color: var(--curry);">Order Information</h4>
                        <div style="margin-bottom: 1rem;">
                            <strong>Name:</strong><br>
                            ${escapeHtml(order.customer_name)}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Email:</strong><br>
                            ${escapeHtml(order.customer_email)}
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <strong>Phone:</strong><br>
                            ${escapeHtml(order.customer_phone)}
                        </div>
                        ${order.rider_name ? `
                        <div style="margin-bottom: 1rem;">
                            <strong>Assigned Rider:</strong><br>
                            ${escapeHtml(order.rider_name)}
                            ${order.rider_phone ? `<br><small>${escapeHtml(order.rider_phone)}</small>` : ''}
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                <div style="margin-top: 2rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--curry);">Delivery Address</h4>
                    <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm);">
                        ${escapeHtml(order.delivery_address)}
                        ${order.delivery_instructions ? `<br><strong>Instructions:</strong> ${escapeHtml(order.delivery_instructions)}` : ''}
                    </div>
                </div>
                
                <div style="margin-top: 2rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--curry);">Order Items</h4>
                    <div style="border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                        ${itemsHtml}
                    </div>
                </div>
                
                ${order.special_notes ? `
                <div style="margin-top: 2rem;">
                    <h4 style="margin-bottom: 1rem; color: var(--curry);">Special Notes</h4>
                    <div style="background: #fff3cd; padding: 1rem; border-radius: var(--radius-sm); border-left: 4px solid #ffc107;">
                        ${escapeHtml(order.special_notes)}
                    </div>
                </div>
                ` : ''}
            `;
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Print order details
        function printOrderDetails() {
            if (currentOrderId) {
                window.open(`print-order.php?id=${currentOrderId}`, '_blank');
            }
        }

        // Edit order
        function editOrder(orderId) {
            window.location.href = `edit-order.php?id=${orderId}`;
        }

        // แทนที่ฟังก์ชันเดิม
function printOrder(orderId) {
    const printUrl = `print-order.php?id=${orderId}&auto_print=1`;
    const printWindow = window.open(printUrl, '_blank', 'width=800,height=600,scrollbars=yes');
    printWindow.focus();
}

        // Bulk selection functions
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.order-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
                if (selectAll.checked) {
                    selectedOrders.add(checkbox.value);
                } else {
                    selectedOrders.delete(checkbox.value);
                }
            });
            
            updateBulkActions();
        }

        function selectAllOrders() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            const selectAll = document.getElementById('selectAll');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
                selectedOrders.add(checkbox.value);
            });
            selectAll.checked = true;
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            const selectAll = document.getElementById('selectAll');
            const bulkActions = document.getElementById('bulkActions');
            const bulkActionsBtn = document.getElementById('bulkActionsBtn');
            const selectedCount = document.getElementById('selectedCount');
            
            // Update selected orders set
            selectedOrders.clear();
            checkboxes.forEach(checkbox => {
                selectedOrders.add(checkbox.value);
            });
            
            // Update UI
            selectedCount.textContent = selectedOrders.size;
            
            if (selectedOrders.size > 0) {
                bulkActions.classList.add('show');
                bulkActionsBtn.style.display = 'inline-flex';
            } else {
                bulkActions.classList.remove('show');
                bulkActionsBtn.style.display = 'none';
            }
            
            // Update select all checkbox
            const allCheckboxes = document.querySelectorAll('.order-checkbox');
            selectAll.checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
        }

        function showBulkActions() {
            const bulkActions = document.getElementById('bulkActions');
            bulkActions.classList.add('show');
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            const selectAll = document.getElementById('selectAll');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            selectAll.checked = false;
            selectedOrders.clear();
            
            updateBulkActions();
        }

        function applyBulkUpdate() {
            const newStatus = document.getElementById('bulkStatusSelect').value;
            
            if (!newStatus) {
                showToast('Please select a status to update to', 'error');
                return;
            }
            
            if (selectedOrders.size === 0) {
                showToast('No orders selected', 'error');
                return;
            }
            
            if (!confirm(`Update ${selectedOrders.size} orders to "${newStatus}" status?`)) {
                return;
            }
            
            const orderIds = Array.from(selectedOrders);
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=bulk_update_status&order_ids=${JSON.stringify(orderIds)}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => refreshOrders(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error updating orders:', error);
                showToast('Error updating orders', 'error');
            });
        }

        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                showToast('Logging out...', 'info');
                
                fetch('../auth/logout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=logout'
                })
                .then(response => {
                    window.location.href = '../login.php';
                })
                .catch(error => {
                    console.error('Logout error:', error);
                    window.location.href = '../login.php';
                });
            }
        }

        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Handle keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshOrders();
            }
            if (e.ctrlKey && e.key === 'a') {
                e.preventDefault();
                selectAllOrders();
            }
            if (e.key === 'Escape') {
                clearSelection();
                // Close any open modals
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        });

        // Auto-refresh orders every 30 seconds
        setInterval(() => {
            if (selectedOrders.size === 0) {
                console.log('Auto-refreshing orders...');
                // Only auto-refresh if no orders are selected to avoid disrupting bulk operations
                window.location.reload();
            }
        }, 30000);

        // Real-time status updates (simulated)
        function checkForOrderUpdates() {
            // In a real application, this would use WebSockets or polling
            console.log('Checking for order updates...');
        }

        // Check for updates every 10 seconds
        setInterval(checkForOrderUpdates, 10000);

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log(`Orders page loaded in ${Math.round(loadTime)}ms`);
        });

        // Initialize tooltips
        document.querySelectorAll('[title]').forEach(element => {
            element.setAttribute('data-toggle', 'tooltip');
        });

        console.log('Krua Thai Orders Management initialized successfully');
    </script>
</body>
</html>
                          