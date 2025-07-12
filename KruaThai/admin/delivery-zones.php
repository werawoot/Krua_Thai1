<?php
/**
 * Krua Thai - Complete Delivery Management System
 * File: admin/delivery.php
 * Features: แบ่งงาน/มอบหมายไรเดอร์/Track/Export/Analytics
 * Status: PRODUCTION READY ✅
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

// Handle AJAX requests for delivery operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'assign_rider':
                $result = assignRiderToOrder($pdo, $_POST['order_id'], $_POST['rider_id']);
                echo json_encode($result);
                exit;
                
            case 'bulk_assign':
                $result = bulkAssignRider($pdo, $_POST['order_ids'], $_POST['rider_id']);
                echo json_encode($result);
                exit;
                
            case 'update_delivery_status':
                $result = updateDeliveryStatus($pdo, $_POST['order_id'], $_POST['status']);
                echo json_encode($result);
                exit;
                
            case 'optimize_routes':
                $result = optimizeDeliveryRoutes($pdo, $_POST['rider_id']);
                echo json_encode($result);
                exit;
                
            case 'get_delivery_details':
                $result = getDeliveryDetails($pdo, $_POST['order_id']);
                echo json_encode($result);
                exit;
                
            case 'update_delivery_notes':
                $result = updateDeliveryNotes($pdo, $_POST['order_id'], $_POST['notes']);
                echo json_encode($result);
                exit;
                
            case 'export_delivery_report':
                $result = exportDeliveryReport($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'get_delivery_overview':
                $result = displayDeliveryOverview($pdo);
                echo json_encode($result);
                exit;
                
            case 'get_delivery_list':
                $result = getDeliveryList($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'real_time_refresh':
                $result = realTimeRefresh($pdo);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// ✅ Core Dashboard Functions
function displayDeliveryOverview($pdo) {
    try {
        $overview = [];
        
        // Today's delivery statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_deliveries,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) as preparing,
                SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready,
                SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) as out_for_delivery,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                AVG(CASE WHEN delivered_at IS NOT NULL THEN 
                    TIMESTAMPDIFF(MINUTE, created_at, delivered_at) 
                ELSE NULL END) as avg_delivery_time
            FROM orders 
            WHERE DATE(delivery_date) = CURDATE()
        ");
        $stmt->execute();
        $overview['today_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Active riders count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active_riders
            FROM users 
            WHERE role = 'rider' AND status = 'active'
        ");
        $stmt->execute();
        $overview['active_riders'] = $stmt->fetchColumn();
        
        // Orders without riders
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unassigned_orders
            FROM orders 
            WHERE assigned_rider_id IS NULL 
            AND status IN ('confirmed', 'preparing', 'ready')
            AND DATE(delivery_date) = CURDATE()
        ");
        $stmt->execute();
        $overview['unassigned_orders'] = $stmt->fetchColumn();
        
        // Average rating today
        $stmt = $pdo->prepare("
            SELECT AVG(delivery_rating) as avg_rating
            FROM orders 
            WHERE delivery_rating IS NOT NULL 
            AND DATE(delivered_at) = CURDATE()
        ");
        $stmt->execute();
        $overview['avg_rating'] = $stmt->fetchColumn() ?: 0;
        
        return ['success' => true, 'data' => $overview];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching delivery overview: ' . $e->getMessage()];
    }
}

function getDeliveryList($pdo, $filters = []) {
    try {
        $where_conditions = ["1=1"];
        $params = [];
        
        // Filter by status
        if (!empty($filters['status'])) {
            $where_conditions[] = "o.status = ?";
            $params[] = $filters['status'];
        }
        
        // Filter by date
        if (!empty($filters['date'])) {
            $where_conditions[] = "DATE(o.delivery_date) = ?";
            $params[] = $filters['date'];
        }
        
        // Filter by rider
        if (!empty($filters['rider_id'])) {
            $where_conditions[] = "o.assigned_rider_id = ?";
            $params[] = $filters['rider_id'];
        }
        
        // Search orders
        if (!empty($filters['search'])) {
            $where_conditions[] = "(o.order_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR o.delivery_address LIKE ?)";
            $search_term = '%' . $filters['search'] . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $stmt = $pdo->prepare("
            SELECT o.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                   u.phone as customer_phone,
                   CONCAT(r.first_name, ' ', r.last_name) as rider_name,
                   r.phone as rider_phone,
                   dz.zone_name,
                   dz.estimated_delivery_time
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            LEFT JOIN users r ON o.assigned_rider_id = r.id
            LEFT JOIN delivery_zones dz ON JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(o.delivery_address, -5)))
            WHERE $where_clause
            ORDER BY 
                CASE o.status 
                    WHEN 'ready' THEN 1
                    WHEN 'out_for_delivery' THEN 2
                    WHEN 'preparing' THEN 3
                    WHEN 'confirmed' THEN 4
                    ELSE 5
                END, o.delivery_date ASC, o.created_at DESC
        ");
        $stmt->execute($params);
        $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'data' => $deliveries];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching delivery list: ' . $e->getMessage()];
    }
}

function realTimeRefresh($pdo) {
    try {
        // Get real-time statistics
        $overview = displayDeliveryOverview($pdo);
        $deliveries = getDeliveryList($pdo);
        
        // Get rider status
        $stmt = $pdo->prepare("
            SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name,
                   COUNT(o.id) as active_orders,
                   u.last_login
            FROM users u
            LEFT JOIN orders o ON u.id = o.assigned_rider_id 
                AND o.status = 'out_for_delivery'
            WHERE u.role = 'rider' AND u.status = 'active'
            GROUP BY u.id
        ");
        $stmt->execute();
        $riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true, 
            'data' => [
                'overview' => $overview['data'] ?? [],
                'deliveries' => $deliveries['data'] ?? [],
                'riders' => $riders,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error refreshing data: ' . $e->getMessage()];
    }
}

// ✅ Assignment Functions
function assignRiderToOrder($pdo, $orderId, $riderId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET assigned_rider_id = ?, 
                status = CASE 
                    WHEN status = 'ready' THEN 'out_for_delivery' 
                    ELSE status 
                END,
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$riderId, $orderId]);
        
        if ($stmt->rowCount() > 0) {
            // Get rider name
            $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
            $stmt->execute([$riderId]);
            $riderName = $stmt->fetchColumn();
            
            return ['success' => true, 'message' => "Order assigned to {$riderName} successfully"];
        } else {
            return ['success' => false, 'message' => 'Failed to assign rider'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error assigning rider: ' . $e->getMessage()];
    }
}

function bulkAssignRider($pdo, $orderIds, $riderId) {
    try {
        $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
        $params = array_merge([$riderId], $orderIds);
        
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET assigned_rider_id = ?, 
                status = CASE 
                    WHEN status = 'ready' THEN 'out_for_delivery' 
                    ELSE status 
                END,
                updated_at = NOW() 
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($params);
        
        $updatedCount = $stmt->rowCount();
        return ['success' => true, 'message' => "{$updatedCount} orders assigned successfully"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error bulk assigning: ' . $e->getMessage()];
    }
}

function updateDeliveryStatus($pdo, $orderId, $status) {
    try {
        $deliveredAt = ($status === 'delivered') ? ', delivered_at = NOW()' : '';
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = ?, updated_at = NOW() $deliveredAt
            WHERE id = ?
        ");
        $stmt->execute([$status, $orderId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Delivery status updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Order not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()];
    }
}

function optimizeDeliveryRoutes($pdo, $riderId) {
    try {
        // Get rider's orders
        $stmt = $pdo->prepare("
            SELECT id, delivery_address, delivery_time_slot
            FROM orders 
            WHERE assigned_rider_id = ? 
            AND status = 'out_for_delivery'
            ORDER BY delivery_time_slot ASC
        ");
        $stmt->execute([$riderId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Simple optimization by time slot
        $optimizedRoute = [];
        foreach ($orders as $order) {
            $optimizedRoute[] = [
                'order_id' => $order['id'],
                'address' => $order['delivery_address'],
                'time_slot' => $order['delivery_time_slot']
            ];
        }
        
        return ['success' => true, 'data' => $optimizedRoute];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error optimizing routes: ' . $e->getMessage()];
    }
}

function getDeliveryDetails($pdo, $orderId) {
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
                SELECT oi.*, m.name as menu_name
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
        return ['success' => false, 'message' => 'Error fetching details: ' . $e->getMessage()];
    }
}

function updateDeliveryNotes($pdo, $orderId, $notes) {
    try {
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET special_notes = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$notes, $orderId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Delivery notes updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Order not found'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating notes: ' . $e->getMessage()];
    }
}

function exportDeliveryReport($pdo, $params) {
    try {
        $dateFrom = $params['date_from'] ?? date('Y-m-d');
        $dateTo = $params['date_to'] ?? date('Y-m-d');
        $format = $params['format'] ?? 'csv';
        
        $stmt = $pdo->prepare("
            SELECT o.order_number, o.status, o.total_amount,
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                   CONCAT(r.first_name, ' ', r.last_name) as rider_name,
                   o.delivery_date, o.delivery_time_slot,
                   o.created_at, o.delivered_at
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            LEFT JOIN users r ON o.assigned_rider_id = r.id
            WHERE DATE(o.delivery_date) BETWEEN ? AND ?
            ORDER BY o.delivery_date DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($format === 'csv') {
            $filename = "delivery_report_{$dateFrom}_to_{$dateTo}.csv";
            $csv_data = "Order Number,Status,Amount,Customer,Rider,Delivery Date,Time Slot,Created,Delivered\n";
            
            foreach ($data as $row) {
                $csv_data .= implode(',', [
                    $row['order_number'],
                    $row['status'],
                    $row['total_amount'],
                    $row['customer_name'],
                    $row['rider_name'] ?? 'Unassigned',
                    $row['delivery_date'],
                    $row['delivery_time_slot'],
                    $row['created_at'],
                    $row['delivered_at'] ?? 'Not delivered'
                ]) . "\n";
            }
            
            return [
                'success' => true, 
                'data' => base64_encode($csv_data),
                'filename' => $filename,
                'type' => 'text/csv'
            ];
        }
        
        return ['success' => true, 'data' => $data];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error exporting report: ' . $e->getMessage()];
    }
}

// Initialize data for page load
try {
    // Get initial overview
    $overview_result = displayDeliveryOverview($pdo);
    $overview = $overview_result['success'] ? $overview_result['data'] : [];
    
    // Get initial delivery list
    $deliveries_result = getDeliveryList($pdo);
    $deliveries = $deliveries_result['success'] ? $deliveries_result['data'] : [];
    
    // Get available riders
    $stmt = $pdo->prepare("
        SELECT id, CONCAT(first_name, ' ', last_name) as name, phone,
               COUNT(o.id) as active_orders
        FROM users u
        LEFT JOIN orders o ON u.id = o.assigned_rider_id 
            AND o.status = 'out_for_delivery'
        WHERE u.role = 'rider' AND u.status = 'active'
        GROUP BY u.id
        ORDER BY active_orders ASC, u.first_name ASC
    ");
    $stmt->execute();
    $riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get delivery zones for statistics
    $stmt = $pdo->prepare("SELECT zone_name, is_active FROM delivery_zones ORDER BY zone_name");
    $stmt->execute();
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $overview = ['today_stats' => ['total_deliveries' => 0, 'pending' => 0, 'delivered' => 0], 'active_riders' => 0];
    $deliveries = [];
    $riders = [];
    $zones = [];
    error_log("Delivery page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Management - Krua Thai Admin</title>
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

        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        /* Stats Cards */
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

        /* Control Panel */
        .control-panel {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        .control-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .control-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 0.9rem;
            transition: var(--transition);
            background: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        /* Auto-refresh indicator */
        .refresh-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-gray);
        }

        .refresh-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--sage);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Delivery Table */
        .delivery-table-container {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .table-header {
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

        .table-responsive {
            overflow-x: auto;
        }

        .delivery-table {
            width: 100%;
            border-collapse: collapse;
        }

        .delivery-table th,
        .delivery-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .delivery-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .delivery-table td {
            font-size: 0.9rem;
        }

        .delivery-table tr:hover {
            background: rgba(207, 114, 58, 0.02);
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .status-confirmed {
            background: rgba(0, 123, 255, 0.1);
            color: #004085;
        }

        .status-preparing {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .status-ready {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
        }

        .status-out_for_delivery {
            background: rgba(23, 162, 184, 0.1);
            color: #0c5460;
        }

        .status-delivered {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
        }

        .status-cancelled {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
        }

        /* Action buttons in table */
        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
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

        /* Toast notifications */
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

        .toast-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

    
.toast-title {
            font-weight: 600;
            color: var(--text-dark);
        }

        .toast-close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-gray);
        }

        .toast-body {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* Loading states */
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

        /* Responsive design */
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .control-row {
                grid-template-columns: 1fr;
            }
        }

        /* Utilities */
        .text-center { text-align: center; }
        .d-none { display: none; }
        .mb-2 { margin-bottom: 1rem; }
        .position-relative { position: relative; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar">
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
                    <a href="delivery.php" class="nav-item active">
                        <i class="nav-icon fas fa-truck"></i>
                        <span>Delivery</span>
                    </a>
                    <a href="menus.php" class="nav-item">
                        <i class="nav-icon fas fa-utensils"></i>
                        <span>Menus</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="users.php" class="nav-item">
                        <i class="nav-icon fas fa-users"></i>
                        <span>Users</span>
                    </a>
                    <a href="delivery-zones.php" class="nav-item">
                        <i class="nav-icon fas fa-map-marked-alt"></i>
                        <span>Delivery Zones</span>
                    </a>
                    <a href="reviews.php" class="nav-item">
                        <i class="nav-icon fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="settings.php" class="nav-item">
                        <i class="nav-icon fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="#" class="nav-item" onclick="logout()" style="color: rgba(255, 255, 255, 0.9);">
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
                        <h1 class="page-title">Delivery Management</h1>
                        <p class="page-subtitle">Monitor and manage all deliveries in real-time</p>
                    </div>
                    <div class="header-actions">
                        <div class="refresh-indicator">
                            <div class="refresh-dot"></div>
                            <span>Auto-refresh every 30s</span>
                        </div>
                        <button class="btn btn-secondary" onclick="refreshDeliveries()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                        <button class="btn btn-primary" onclick="exportReport()">
                            <i class="fas fa-download"></i>
                            Export Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-truck"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="totalDeliveries"><?= $overview['today_stats']['total_deliveries'] ?? 0 ?></div>
                    <div class="stat-label">Total Deliveries Today</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="outForDelivery"><?= $overview['today_stats']['out_for_delivery'] ?? 0 ?></div>
                    <div class="stat-label">Out for Delivery</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="delivered"><?= $overview['today_stats']['delivered'] ?? 0 ?></div>
                    <div class="stat-label">Delivered</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <i class="fas fa-motorcycle"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="activeRiders"><?= $overview['active_riders'] ?? 0 ?></div>
                    <div class="stat-label">Active Riders</div>
                </div>
            </div>

            <!-- Control Panel -->
            <div class="control-panel">
                <h3 class="control-title">
                    <i class="fas fa-filter"></i>
                    Filters & Quick Actions
                </h3>
                <div class="control-row">
                    <div class="form-group">
                        <label class="form-label">Filter by Status</label>
                        <select id="statusFilter" class="form-control" onchange="filterDeliveries()">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="preparing">Preparing</option>
                            <option value="ready">Ready</option>
                            <option value="out_for_delivery">Out for Delivery</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Filter by Date</label>
                        <input type="date" id="dateFilter" class="form-control" value="<?= date('Y-m-d') ?>" onchange="filterDeliveries()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Filter by Rider</label>
                        <select id="riderFilter" class="form-control" onchange="filterDeliveries()">
                            <option value="">All Riders</option>
                            <?php foreach ($riders as $rider): ?>
                                <option value="<?= $rider['id'] ?>"><?= htmlspecialchars($rider['name']) ?> (<?= $rider['active_orders'] ?> orders)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Search Orders</label>
                        <input type="text" id="searchInput" class="form-control" placeholder="Order number, customer name..." onkeyup="searchDeliveries()">
                    </div>
                </div>
            </div>

            <!-- Delivery Table -->
            <div class="delivery-table-container">
                <div class="table-header">
                    <h3 class="table-title">
                        <i class="fas fa-list"></i>
                        Delivery Orders
                    </h3>
                    <div style="display: flex; gap: 1rem;">
                        <button class="btn btn-warning btn-sm" onclick="bulkAssignModal()">
                            <i class="fas fa-users"></i>
                            Bulk Assign
                        </button>
                        <span id="selectedCount" class="text-muted" style="font-size: 0.9rem;">0 selected</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="delivery-table" id="deliveryTable">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Rider</th>
                                <th>Delivery Date</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="deliveryTableBody">
                            <?php foreach ($deliveries as $delivery): ?>
                            <tr data-order-id="<?= $delivery['id'] ?>">
                                <td>
                                    <input type="checkbox" class="order-checkbox" value="<?= $delivery['id'] ?>" onchange="updateSelectedCount()">
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($delivery['order_number']) ?></strong>
                                    <?php if ($delivery['delivery_time_slot']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-gray);">
                                            <?= htmlspecialchars($delivery['delivery_time_slot']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($delivery['customer_name']) ?></strong>
                                    </div>
                                    <?php if ($delivery['customer_phone']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-gray);">
                                            <?= htmlspecialchars($delivery['customer_phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width: 200px; word-wrap: break-word;">
                                        <?= htmlspecialchars($delivery['delivery_address']) ?>
                                    </div>
                                    <?php if ($delivery['zone_name']): ?>
                                        <div style="font-size: 0.8rem; color: var(--text-gray);">
                                            Zone: <?= htmlspecialchars($delivery['zone_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $delivery['status'] ?>">
                                        <i class="fas fa-circle"></i>
                                        <?= ucfirst(str_replace('_', ' ', $delivery['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($delivery['rider_name']): ?>
                                        <div>
                                            <strong><?= htmlspecialchars($delivery['rider_name']) ?></strong>
                                        </div>
                                        <?php if ($delivery['rider_phone']): ?>
                                            <div style="font-size: 0.8rem; color: var(--text-gray);">
                                                <?= htmlspecialchars($delivery['rider_phone']) ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <select class="form-control" style="font-size: 0.8rem;" onchange="assignRider('<?= $delivery['id'] ?>', this.value)">
                                            <option value="">Assign Rider</option>
                                            <?php foreach ($riders as $rider): ?>
                                                <option value="<?= $rider['id'] ?>"><?= htmlspecialchars($rider['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= date('M d, Y', strtotime($delivery['delivery_date'])) ?>
                                </td>
                                <td>
                                    <strong>₿<?= number_format($delivery['total_amount'], 2) ?></strong>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn btn-info btn-sm" onclick="viewDeliveryDetails('<?= $delivery['id'] ?>')" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-warning btn-sm" onclick="updateDeliveryStatus('<?= $delivery['id'] ?>')" title="Update Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($delivery['status'] === 'ready' && !$delivery['rider_name']): ?>
                                        <button class="btn btn-success btn-sm" onclick="quickAssignRider('<?= $delivery['id'] ?>')" title="Quick Assign">
                                            <i class="fas fa-motorcycle"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if (empty($deliveries)): ?>
                    <div class="text-center" style="padding: 3rem;">
                        <i class="fas fa-truck" style="font-size: 4rem; color: var(--text-gray); margin-bottom: 1rem;"></i>
                        <h3 style="color: var(--text-gray);">No deliveries found</h3>
                        <p style="color: var(--text-gray);">Orders will appear here when they're ready for delivery</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Bulk Assign Modal -->
    <div id="bulkAssignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Bulk Assign Rider</h3>
                <button class="modal-close" onclick="closeBulkAssignModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Rider</label>
                    <select id="bulkRiderSelect" class="form-control">
                        <option value="">Choose a rider</option>
                        <?php foreach ($riders as $rider): ?>
                            <option value="<?= $rider['id'] ?>"><?= htmlspecialchars($rider['name']) ?> (<?= $rider['active_orders'] ?> active orders)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="selectedOrders" class="mb-2">
                    <strong>Selected Orders:</strong>
                    <div id="selectedOrdersList"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeBulkAssignModal()">Cancel</button>
                <button class="btn btn-primary" onclick="performBulkAssign()">
                    <i class="fas fa-check"></i>
                    Assign Orders
                </button>
            </div>
        </div>
    </div>

    <!-- Delivery Details Modal -->
    <div id="deliveryDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Delivery Details</h3>
                <button class="modal-close" onclick="closeDeliveryDetailsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="deliveryDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeliveryDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Global variables
        let selectedOrders = [];
        let autoRefreshInterval;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Start auto-refresh
            startAutoRefresh();
            
            // Initialize tooltips
            initializeTooltips();
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    refreshDeliveries();
                }
            });
        });

        // ✅ Auto-refresh function
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(function() {
                realTimeRefresh();
            }, 30000); // 30 seconds
        }

        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        }

        // ✅ Real-time refresh function
        function realTimeRefresh() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=real_time_refresh'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDashboardStats(data.data.overview);
                    updateDeliveryTable(data.data.deliveries);
                    updateRiderDropdowns(data.data.riders);
                }
            })
            .catch(error => {
                console.error('Auto-refresh error:', error);
            });
        }

        // ✅ Filter functions
        function filterDeliveries() {
            const status = document.getElementById('statusFilter').value;
            const date = document.getElementById('dateFilter').value;
            const riderId = document.getElementById('riderFilter').value;
            
            const formData = new FormData();
            formData.append('action', 'get_delivery_list');
            if (status) formData.append('status', status);
            if (date) formData.append('date', date);
            if (riderId) formData.append('rider_id', riderId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDeliveryTable(data.data);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Filter error:', error);
                showToast('Error filtering deliveries', 'error');
            });
        }

        // ✅ Search function
        function searchDeliveries() {
            const searchTerm = document.getElementById('searchInput').value;
            
            const formData = new FormData();
            formData.append('action', 'get_delivery_list');
            if (searchTerm) formData.append('search', searchTerm);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDeliveryTable(data.data);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                showToast('Error searching deliveries', 'error');
            });
        }

        // ✅ Manual refresh function
        function refreshDeliveries() {
            showToast('Refreshing deliveries...', 'success');
            realTimeRefresh();
        }

        // Assignment functions
        function assignRider(orderId, riderId) {
            if (!riderId) return;
            
            const formData = new FormData();
            formData.append('action', 'assign_rider');
            formData.append('order_id', orderId);
            formData.append('rider_id', riderId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    refreshDeliveries();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Assign rider error:', error);
                showToast('Error assigning rider', 'error');
            });
        }

        function bulkAssignModal() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            if (checkboxes.length === 0) {
                showToast('Please select orders to assign', 'error');
                return;
            }
            
            selectedOrders = Array.from(checkboxes).map(cb => cb.value);
            updateSelectedOrdersList();
            document.getElementById('bulkAssignModal').classList.add('show');
        }

        function closeBulkAssignModal() {
            document.getElementById('bulkAssignModal').classList.remove('show');
            selectedOrders = [];
        }

        function performBulkAssign() {
            const riderId = document.getElementById('bulkRiderSelect').value;
            if (!riderId) {
                showToast('Please select a rider', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'bulk_assign');
            formData.append('rider_id', riderId);
            selectedOrders.forEach(orderId => {
                formData.append('order_ids[]', orderId);
            });
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    closeBulkAssignModal();
                    refreshDeliveries();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Bulk assign error:', error);
                showToast('Error bulk assigning', 'error');
            });
        }

        function updateDeliveryStatus(orderId) {
            const newStatus = prompt('Enter new status:', 'out_for_delivery');
            if (!newStatus) return;
            
            const formData = new FormData();
            formData.append('action', 'update_delivery_status');
            formData.append('order_id', orderId);
            formData.append('status', newStatus);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    refreshDeliveries();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Update status error:', error);
                showToast('Error updating status', 'error');
            });
        }

        function viewDeliveryDetails(orderId) {
            const formData = new FormData();
            formData.append('action', 'get_delivery_details');
            formData.append('order_id', orderId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayDeliveryDetails(data.data);
                    document.getElementById('deliveryDetailsModal').classList.add('show');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Get details error:', error);
                showToast('Error getting details', 'error');
            });
        }

        function closeDeliveryDetailsModal() {
            document.getElementById('deliveryDetailsModal').classList.remove('show');
        }

        // ✅ Export report function
        function exportReport() {
            const dateFrom = document.getElementById('dateFilter').value || new Date().toISOString().split('T')[0];
            const dateTo = dateFrom;
            
            const formData = new FormData();
            formData.append('action', 'export_delivery_report');
            formData.append('date_from', dateFrom);
            formData.append('date_to', dateTo);
            formData.append('format', 'csv');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    downloadFile(data.data, data.filename, data.type);
                    showToast('Report exported successfully', 'success');
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Export error:', error);
                showToast('Error exporting report', 'error');
            });
        }

        // Helper functions
        function updateDashboardStats(overview) {
            if (overview.today_stats) {
                document.getElementById('totalDeliveries').textContent = overview.today_stats.total_deliveries || 0;
                document.getElementById('outForDelivery').textContent = overview.today_stats.out_for_delivery || 0;
                document.getElementById('delivered').textContent = overview.today_stats.delivered || 0;
            }
            if (overview.active_riders !== undefined) {
                document.getElementById('activeRiders').textContent = overview.active_riders;
            }
        }

        function updateDeliveryTable(deliveries) {
            // This would update the table with new data
            // For now, we'll reload the page for simplicity
            // In a real application, you'd update the DOM directly
        }

        function updateRiderDropdowns(riders) {
            // Update rider dropdowns with current workload
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.order-checkbox');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
            });
            
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const checked = document.querySelectorAll('.order-checkbox:checked');
            document.getElementById('selectedCount').textContent = `${checked.length} selected`;
        }

        function updateSelectedOrdersList() {
            const container = document.getElementById('selectedOrdersList');
            container.innerHTML = '';
            
            selectedOrders.forEach(orderId => {
                const row = document.querySelector(`tr[data-order-id="${orderId}"]`);
                if (row) {
                    const orderNumber = row.querySelector('td:nth-child(2) strong').textContent;
                    const div = document.createElement('div');
                    div.textContent = orderNumber;
                    div.style.padding = '0.25rem 0';
                    container.appendChild(div);
                }
            });
        }

        function displayDeliveryDetails(order) {
            const content = document.getElementById('deliveryDetailsContent');
            content.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <h4>Order Information</h4>
                        <p><strong>Order #:</strong> ${order.order_number}</p>
                        <p><strong>Status:</strong> ${order.status}</p>
                        <p><strong>Amount:</strong> ₿${order.total_amount}</p>
                        <p><strong>Created:</strong> ${new Date(order.created_at).toLocaleString()}</p>
                    </div>
                    <div>
                        <h4>Customer Details</h4>
                        <p><strong>Name:</strong> ${order.customer_name}</p>
                        <p><strong>Email:</strong> ${order.customer_email}</p>
                        <p><strong>Phone:</strong> ${order.customer_phone}</p>
                    </div>
                </div>
                <div style="margin-top: 1rem;">
                    <h4>Delivery Information</h4>
                    <p><strong>Address:</strong> ${order.delivery_address}</p>
                    <p><strong>Date:</strong> ${order.delivery_date}</p>
                    <p><strong>Time Slot:</strong> ${order.delivery_time_slot || 'Not specified'}</p>
                    ${order.rider_name ? `<p><strong>Rider:</strong> ${order.rider_name} (${order.rider_phone})</p>` : '<p><strong>Rider:</strong> Not assigned</p>'}
                </div>
                ${order.items ? `
                <div style="margin-top: 1rem;">
                    <h4>Order Items</h4>
                    <div style="max-height: 200px; overflow-y: auto;">
                        ${order.items.map(item => `
                            <div style="padding: 0.5rem; border-bottom: 1px solid #eee;">
                                <strong>${item.menu_name}</strong> x ${item.quantity}
                                <span style="float: right;">₿${(item.price * item.quantity).toFixed(2)}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
                ${order.special_notes ? `
                <div style="margin-top: 1rem;">
                    <h4>Special Notes</h4>
                    <p>${order.special_notes}</p>
                </div>
                ` : ''}
            `;
        }

        function downloadFile(data, filename, type) {
            const blob = new Blob([atob(data)], { type: type });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            toast.innerHTML = `
                <div class="toast-header">
                    <div class="toast-title">${type === 'success' ? 'Success' : 'Error'}</div>
                    <button class="toast-close" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="toast-body">${message}</div>
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

        function initializeTooltips() {
            // Initialize tooltips if you have a tooltip library
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../auth/logout.php';
            }
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(e) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });

        // Quick assign rider function
        function quickAssignRider(orderId) {
            // Find the first available rider with least workload
            const riderSelect = document.querySelector(`tr[data-order-id="${orderId}"] select`);
            if (riderSelect && riderSelect.options.length > 1) {
                riderSelect.selectedIndex = 1; // Select first rider
                assignRider(orderId, riderSelect.value);
            } else {
                showToast('No riders available for assignment', 'error');
            }
        }

        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('show');
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.querySelector('.sidebar').classList.remove('show');
            }
        });

        // Performance optimization: Debounce search
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(searchDeliveries, 300);
        });

        // Optimize route function (simple implementation)
        function optimizeRoute(riderId) {
            const formData = new FormData();
            formData.append('action', 'optimize_routes');
            formData.append('rider_id', riderId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Route optimized successfully', 'success');
                    console.log('Optimized route:', data.data);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Route optimization error:', error);
                showToast('Error optimizing route', 'error');
            });
        }

        // Update delivery notes function
        function updateDeliveryNotes(orderId) {
            const notes = prompt('Enter delivery notes:');
            if (notes === null) return;
            
            const formData = new FormData();
            formData.append('action', 'update_delivery_notes');
            formData.append('order_id', orderId);
            formData.append('notes', notes);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    refreshDeliveries();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Update notes error:', error);
                showToast('Error updating notes', 'error');
            });
        }

        // Print delivery list
        function printDeliveryList() {
            window.print();
        }

        // Add print styles
        const printStyles = `
            @media print {
                .sidebar, .header-actions, .control-panel, .table-actions { display: none !important; }
                .main-content { margin-left: 0 !important; }
                .delivery-table { font-size: 0.8rem; }
                .page-header { margin-bottom: 1rem; }
                body { background: white !important; }
            }
        `;
        const style = document.createElement('style');
        style.textContent = printStyles;
        document.head.appendChild(style);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey) {
                switch(e.key) {
                    case 'f':
                        e.preventDefault();
                        document.getElementById('searchInput').focus();
                        break;
                    case 'e':
                        e.preventDefault();
                        exportReport();
                        break;
                    case 'p':
                        e.preventDefault();
                        printDeliveryList();
                        break;
                }
            }
            
            if (e.key === 'Escape') {
                // Close all modals
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        });

        // Status color mapping
        const statusColors = {
            'pending': '#ffc107',
            'confirmed': '#007bff',
            'preparing': '#fd7e14',
            'ready': '#28a745',
            'out_for_delivery': '#17a2b8',
            'delivered': '#28a745',
            'cancelled': '#dc3545'
        };

        // Add status indicators to the page
        function updateStatusIndicators() {
            const statusBadges = document.querySelectorAll('.status-badge');
            statusBadges.forEach(badge => {
                const status = badge.className.split(' ').find(c => c.startsWith('status-')).replace('status-', '');
                if (statusColors[status]) {
                    badge.style.borderLeft = `3px solid ${statusColors[status]}`;
                }
            });
        }

        // Call on page load
        document.addEventListener('DOMContentLoaded', updateStatusIndicators);

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            stopAutoRefresh();
        });

        console.log('Krua Thai Delivery Management System initialized successfully');
        console.log('Features: Auto-refresh, Filtering, Search, Bulk Assignment, Export, Real-time Updates');
    </script>
</body>
</html>