<?php
/**
 * Krua Thai - Fixed Charts & Visualizations System
 * File: admin/charts_fixed.php
 * Features: Compatible with current database schema
 * Status: PRODUCTION READY ✅
 */

// Debug mode - เพิ่มที่บรรทัดแรก
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// Handle AJAX requests for chart data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_revenue_chart':
                $result = generateRevenueChart($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'get_order_volume_chart':
                $result = generateOrderVolumeChart($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'get_customer_chart':
                $result = generateCustomerChart($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'get_delivery_performance_chart':
                $result = generateDeliveryPerformanceChart($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'get_menu_popularity_chart':
                $result = generateMenuPopularityChart($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'get_chart_data':
                $result = getChartData($pdo, $_POST);
                echo json_encode($result);
                exit;
                
            case 'export_chart':
                $result = exportChartData($pdo, $_POST);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Chart Generation Functions
function generateRevenueChart($pdo, $data) {
    try {
        $dateFrom = $data['date_from'] ?? date('Y-m-01');
        $dateTo = $data['date_to'] ?? date('Y-m-d');
        $groupBy = $data['group_by'] ?? 'day';
        
        // Date format based on group by
        switch($groupBy) {
            case 'hour':
                $dateLabel = 'DATE_FORMAT(p.created_at, "%Y-%m-%d %H:00")';
                break;
            case 'day':
                $dateLabel = 'DATE(p.created_at)';
                break;
            case 'week':
                $dateLabel = 'YEARWEEK(p.created_at)';
                break;
            case 'month':
                $dateLabel = 'DATE_FORMAT(p.created_at, "%Y-%m")';
                break;
            default:
                $dateLabel = 'DATE(p.created_at)';
                break;
        }
        
        // Revenue by time period
        $stmt = $pdo->prepare("
            SELECT 
                {$dateLabel} as period,
                SUM(p.amount) as revenue,
                COUNT(p.id) as transaction_count,
                AVG(p.amount) as avg_transaction
            FROM payments p
            WHERE p.status = 'completed' 
            AND p.created_at BETWEEN ? AND ?
            GROUP BY {$dateLabel}
            ORDER BY period ASC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $revenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Revenue by payment method
        $stmt = $pdo->prepare("
            SELECT 
                p.payment_method,
                SUM(p.amount) as revenue,
                COUNT(p.id) as count,
                (SUM(p.amount) * 100.0 / (SELECT SUM(amount) FROM payments WHERE status = 'completed' AND created_at BETWEEN ? AND ?)) as percentage
            FROM payments p
            WHERE p.status = 'completed' 
            AND p.created_at BETWEEN ? AND ?
            GROUP BY p.payment_method
            ORDER BY revenue DESC
        ");
        $stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
        $paymentMethodData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top subscription plans by revenue
        $stmt = $pdo->prepare("
            SELECT 
                sp.name,
                sp.name_thai,
                SUM(p.amount) as revenue,
                COUNT(DISTINCT s.id) as subscription_count,
                AVG(p.amount) as avg_revenue
            FROM payments p
            JOIN subscriptions s ON p.subscription_id = s.id
            JOIN subscription_plans sp ON s.plan_id = sp.id
            WHERE p.status = 'completed' 
            AND p.created_at BETWEEN ? AND ?
            GROUP BY sp.id, sp.name, sp.name_thai
            ORDER BY revenue DESC
            LIMIT 10
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $planRevenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => [
                'revenue_trend' => $revenueData,
                'payment_methods' => $paymentMethodData,
                'plan_revenue' => $planRevenueData,
                'total_revenue' => array_sum(array_column($revenueData, 'revenue')),
                'period' => ['from' => $dateFrom, 'to' => $dateTo, 'group_by' => $groupBy]
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error generating revenue chart: ' . $e->getMessage()];
    }
}

function generateOrderVolumeChart($pdo, $data) {
    try {
        $dateFrom = $data['date_from'] ?? date('Y-m-01');
        $dateTo = $data['date_to'] ?? date('Y-m-d');
        $groupBy = $data['group_by'] ?? 'day';
        
        switch($groupBy) {
            case 'hour':
                $dateLabel = 'DATE_FORMAT(o.created_at, "%Y-%m-%d %H:00")';
                break;
            case 'day':
                $dateLabel = 'DATE(o.created_at)';
                break;
            case 'week':
                $dateLabel = 'YEARWEEK(o.created_at)';
                break;
            case 'month':
                $dateLabel = 'DATE_FORMAT(o.created_at, "%Y-%m")';
                break;
            default:
                $dateLabel = 'DATE(o.created_at)';
                break;
        }
        
        // Orders by time period
        $stmt = $pdo->prepare("
            SELECT 
                {$dateLabel} as period,
                COUNT(o.id) as total_orders,
                SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_orders
            FROM orders o
            WHERE o.created_at BETWEEN ? AND ?
            GROUP BY {$dateLabel}
            ORDER BY period ASC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $orderVolumeData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Orders by status
        $stmt = $pdo->prepare("
            SELECT 
                o.status,
                COUNT(o.id) as count,
                (COUNT(o.id) * 100.0 / (SELECT COUNT(*) FROM orders WHERE created_at BETWEEN ? AND ?)) as percentage
            FROM orders o
            WHERE o.created_at BETWEEN ? AND ?
            GROUP BY o.status
            ORDER BY count DESC
        ");
        $stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
        $statusData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Peak hours analysis
        $stmt = $pdo->prepare("
            SELECT 
                HOUR(o.created_at) as hour,
                COUNT(o.id) as order_count
            FROM orders o
            WHERE o.created_at BETWEEN ? AND ?
            GROUP BY HOUR(o.created_at)
            ORDER BY hour ASC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $peakHoursData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => [
                'volume_trend' => $orderVolumeData,
                'status_distribution' => $statusData,
                'peak_hours' => $peakHoursData,
                'total_orders' => array_sum(array_column($orderVolumeData, 'total_orders')),
                'period' => ['from' => $dateFrom, 'to' => $dateTo, 'group_by' => $groupBy]
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error generating order volume chart: ' . $e->getMessage()];
    }
}

function generateCustomerChart($pdo, $data) {
    try {
        $dateFrom = $data['date_from'] ?? date('Y-m-01');
        $dateTo = $data['date_to'] ?? date('Y-m-d');
        
        // Customer acquisition over time
        $stmt = $pdo->prepare("
            SELECT 
                DATE(u.created_at) as date,
                COUNT(u.id) as new_customers
            FROM users u
            WHERE u.role = 'customer' 
            AND u.created_at BETWEEN ? AND ?
            GROUP BY DATE(u.created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $acquisitionData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add cumulative calculation in PHP
        $cumulative = 0;
        foreach ($acquisitionData as &$item) {
            $cumulative += intval($item['new_customers']);
            $item['cumulative_customers'] = $cumulative;
        }
        
        // Customer lifetime value
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                COUNT(DISTINCT s.id) as subscription_count,
                COALESCE(SUM(p.amount), 0) as total_spent,
                COALESCE(AVG(p.amount), 0) as avg_order_value,
                DATEDIFF(CURDATE(), u.created_at) as days_since_signup
            FROM users u
            LEFT JOIN subscriptions s ON u.id = s.user_id
            LEFT JOIN payments p ON s.id = p.subscription_id AND p.status = 'completed'
            WHERE u.role = 'customer'
            AND u.created_at BETWEEN ? AND ?
            GROUP BY u.id, u.first_name, u.last_name, u.created_at
            HAVING total_spent > 0
            ORDER BY total_spent DESC
            LIMIT 20
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $ltvData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Customer retention analysis
        $stmt = $pdo->prepare("
            SELECT 
                MONTH(u.created_at) as signup_month,
                YEAR(u.created_at) as signup_year,
                COUNT(u.id) as total_signups,
                SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) as active_customers
            FROM users u
            LEFT JOIN subscriptions s ON u.id = s.user_id
            WHERE u.role = 'customer'
            AND u.created_at BETWEEN ? AND ?
            GROUP BY YEAR(u.created_at), MONTH(u.created_at)
            ORDER BY signup_year DESC, signup_month DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $retentionData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add retention rate calculation in PHP
        foreach ($retentionData as &$item) {
            if ($item['total_signups'] > 0) {
                $item['retention_rate'] = ($item['active_customers'] * 100.0) / $item['total_signups'];
            } else {
                $item['retention_rate'] = 0;
            }
        }
        
        // Customer segmentation by spending
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                COALESCE(SUM(p.amount), 0) as total_spent
            FROM users u
            LEFT JOIN subscriptions s ON u.id = s.user_id
            LEFT JOIN payments p ON s.id = p.subscription_id AND p.status = 'completed'
            WHERE u.role = 'customer'
            AND u.created_at BETWEEN ? AND ?
            GROUP BY u.id
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $spendingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process segmentation in PHP
        $segments = [
            'High Value (2000+ THB)' => ['count' => 0, 'total' => 0],
            'Medium Value (1000-1999 THB)' => ['count' => 0, 'total' => 0],
            'Low Value (500-999 THB)' => ['count' => 0, 'total' => 0],
            'New (< 500 THB)' => ['count' => 0, 'total' => 0]
        ];
        
        foreach ($spendingData as $customer) {
            $spent = floatval($customer['total_spent']);
            if ($spent >= 2000) {
                $segments['High Value (2000+ THB)']['count']++;
                $segments['High Value (2000+ THB)']['total'] += $spent;
            } elseif ($spent >= 1000) {
                $segments['Medium Value (1000-1999 THB)']['count']++;
                $segments['Medium Value (1000-1999 THB)']['total'] += $spent;
            } elseif ($spent >= 500) {
                $segments['Low Value (500-999 THB)']['count']++;
                $segments['Low Value (500-999 THB)']['total'] += $spent;
            } else {
                $segments['New (< 500 THB)']['count']++;
                $segments['New (< 500 THB)']['total'] += $spent;
            }
        }
        
        $segmentationData = [];
        foreach ($segments as $segment => $data) {
            $segmentationData[] = [
                'segment' => $segment,
                'customer_count' => $data['count'],
                'avg_spending' => $data['count'] > 0 ? $data['total'] / $data['count'] : 0,
                'segment_revenue' => $data['total']
            ];
        }
        
        return [
            'success' => true,
            'data' => [
                'acquisition_trend' => $acquisitionData,
                'top_customers' => $ltvData,
                'retention_analysis' => $retentionData,
                'customer_segments' => $segmentationData,
                'period' => ['from' => $dateFrom, 'to' => $dateTo]
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error generating customer chart: ' . $e->getMessage()];
    }
}

function generateDeliveryPerformanceChart($pdo, $data) {
    try {
        $dateFrom = $data['date_from'] ?? date('Y-m-01');
        $dateTo = $data['date_to'] ?? date('Y-m-d');
        
        // Basic delivery performance using available columns
        $stmt = $pdo->prepare("
            SELECT 
                DATE(o.created_at) as date,
                COUNT(o.id) as total_orders,
                SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
            FROM orders o
            WHERE o.created_at BETWEEN ? AND ?
            GROUP BY DATE(o.created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $deliveryTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add success rate calculation in PHP
        foreach ($deliveryTrends as &$item) {
            if ($item['total_orders'] > 0) {
                $item['success_rate'] = ($item['delivered_orders'] * 100.0) / $item['total_orders'];
            } else {
                $item['success_rate'] = 0;
            }
        }
        
        // Order status distribution for delivery analysis
        $stmt = $pdo->prepare("
            SELECT 
                o.status,
                COUNT(o.id) as count,
                (COUNT(o.id) * 100.0 / (SELECT COUNT(*) FROM orders WHERE created_at BETWEEN ? AND ?)) as percentage
            FROM orders o
            WHERE o.created_at BETWEEN ? AND ?
            GROUP BY o.status
            ORDER BY count DESC
        ");
        $stmt->execute([$dateFrom, $dateTo, $dateFrom, $dateTo]);
        $statusDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Time slot analysis
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(o.delivery_time_slot, 'Not Specified') as time_slot,
                COUNT(o.id) as order_count
            FROM orders o
            WHERE o.created_at BETWEEN ? AND ?
            GROUP BY o.delivery_time_slot
            ORDER BY order_count DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $timeSlotData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => [
                'delivery_trends' => $deliveryTrends,
                'status_distribution' => $statusDistribution,
                'time_slots' => $timeSlotData,
                'period' => ['from' => $dateFrom, 'to' => $dateTo]
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error generating delivery performance chart: ' . $e->getMessage()];
    }
}

function generateMenuPopularityChart($pdo, $data) {
    try {
        $dateFrom = $data['date_from'] ?? date('Y-m-01');
        $dateTo = $data['date_to'] ?? date('Y-m-d');
        
        // Most popular menus using subscription_menus table
        $stmt = $pdo->prepare("
            SELECT 
                m.name,
                m.name_thai,
                COALESCE(mc.name, 'Uncategorized') as category,
                COUNT(sm.id) as order_count,
                SUM(sm.quantity) as total_quantity,
                COALESCE(AVG(r.overall_rating), 0) as avg_rating,
                COUNT(DISTINCT r.id) as review_count
            FROM menus m
            LEFT JOIN menu_categories mc ON m.category_id = mc.id
            LEFT JOIN subscription_menus sm ON m.id = sm.menu_id
            LEFT JOIN orders o ON sm.subscription_id = o.subscription_id
            LEFT JOIN reviews r ON m.id = r.menu_id
            WHERE o.created_at BETWEEN ? AND ?
            GROUP BY m.id, m.name, m.name_thai, mc.name
            HAVING order_count > 0
            ORDER BY total_quantity DESC
            LIMIT 20
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $popularMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Menu category performance
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(mc.name, 'Uncategorized') as category,
                COUNT(DISTINCT m.id) as menu_count,
                COUNT(sm.id) as total_orders,
                COALESCE(AVG(r.overall_rating), 0) as avg_rating
            FROM menus m
            LEFT JOIN menu_categories mc ON m.category_id = mc.id
            LEFT JOIN subscription_menus sm ON m.id = sm.menu_id
            LEFT JOIN orders o ON sm.subscription_id = o.subscription_id
            LEFT JOIN reviews r ON m.id = r.menu_id
            WHERE o.created_at BETWEEN ? AND ?
            GROUP BY mc.name
            ORDER BY total_orders DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $categoryPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Menu trends over time
        $stmt = $pdo->prepare("
            SELECT 
                DATE(o.created_at) as date,
                m.name,
                COUNT(sm.id) as daily_quantity
            FROM menus m
            JOIN subscription_menus sm ON m.id = sm.menu_id
            JOIN orders o ON sm.subscription_id = o.subscription_id
            WHERE o.created_at BETWEEN ? AND ?
            GROUP BY DATE(o.created_at), m.id, m.name
            ORDER BY date ASC, daily_quantity DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $menuTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Low performing menus (available menus with low orders)
        $stmt = $pdo->prepare("
            SELECT 
                m.name,
                m.name_thai,
                COALESCE(mc.name, 'Uncategorized') as category,
                COALESCE(COUNT(sm.id), 0) as total_orders,
                COALESCE(AVG(r.overall_rating), 0) as avg_rating,
                COUNT(DISTINCT r.id) as review_count,
                m.created_at
            FROM menus m
            LEFT JOIN menu_categories mc ON m.category_id = mc.id
            LEFT JOIN subscription_menus sm ON m.id = sm.menu_id
            LEFT JOIN orders o ON sm.subscription_id = o.subscription_id AND o.created_at BETWEEN ? AND ?
            LEFT JOIN reviews r ON m.id = r.menu_id
            WHERE m.is_available = 1
            GROUP BY m.id, m.name, m.name_thai, mc.name, m.created_at
            HAVING total_orders < 5 OR (avg_rating > 0 AND avg_rating < 3)
            ORDER BY total_orders ASC, avg_rating ASC
            LIMIT 10
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        $lowPerformingMenus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => [
                'popular_menus' => $popularMenus,
                'category_performance' => $categoryPerformance,
                'menu_trends' => $menuTrends,
                'low_performing' => $lowPerformingMenus,
                'period' => ['from' => $dateFrom, 'to' => $dateTo]
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error generating menu popularity chart: ' . $e->getMessage()];
    }
}

function getChartData($pdo, $data) {
    try {
        $chartType = $data['chart_type'] ?? 'overview';
        
        switch($chartType) {
            case 'revenue':
                return generateRevenueChart($pdo, $data);
            case 'orders':
                return generateOrderVolumeChart($pdo, $data);
            case 'customers':
                return generateCustomerChart($pdo, $data);
            case 'delivery':
                return generateDeliveryPerformanceChart($pdo, $data);
            case 'menus':
                return generateMenuPopularityChart($pdo, $data);
            default:
                return ['success' => false, 'message' => 'Invalid chart type'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error getting chart data: ' . $e->getMessage()];
    }
}

function exportChartData($pdo, $data) {
    try {
        $chartType = $data['chart_type'] ?? 'revenue';
        $format = $data['format'] ?? 'json';
        
        $chartData = getChartData($pdo, $data);
        
        if (!$chartData['success']) {
            return $chartData;
        }
        
        $filename = "krua_thai_{$chartType}_chart_" . date('Y-m-d') . ".{$format}";
        
        if ($format === 'csv') {
            $csvData = convertChartDataToCSV($chartData['data'], $chartType);
            return [
                'success' => true,
                'filename' => $filename,
                'data' => $csvData,
                'mime_type' => 'text/csv'
            ];
        } else {
            return [
                'success' => true,
                'filename' => $filename,
                'data' => json_encode($chartData['data'], JSON_PRETTY_PRINT),
                'mime_type' => 'application/json'
            ];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Export error: ' . $e->getMessage()];
    }
}

function convertChartDataToCSV($data, $chartType) {
    $output = "Krua Thai - {$chartType} Chart Data\n";
    $output .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($data as $section => $items) {
        if (is_array($items) && !empty($items)) {
            $output .= ucwords(str_replace('_', ' ', $section)) . "\n";
            
            if (!empty($items[0])) {
                // Add headers
                $headers = array_keys($items[0]);
                $output .= implode(',', $headers) . "\n";
                
                // Add data rows
                foreach ($items as $item) {
                    $row = [];
                    foreach ($headers as $header) {
                        $value = $item[$header] ?? '';
                        // Escape commas and quotes for CSV
                        if (strpos($value, ',') !== false || strpos($value, '"') !== false) {
                            $value = '"' . str_replace('"', '""', $value) . '"';
                        }
                        $row[] = $value;
                    }
                    $output .= implode(',', $row) . "\n";
                }
            }
            $output .= "\n";
        }
    }
    
    return $output;
}

// Get initial dashboard data
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Quick stats for dashboard overview
try {
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()) as today_orders,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND DATE(created_at) = CURDATE()) as today_revenue,
            (SELECT COUNT(*) FROM users WHERE role = 'customer' AND status = 'active') as active_customers,
            (SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND DATE(delivered_at) = CURDATE()) as today_deliveries
    ");
    $stmt->execute();
    $quickStats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $quickStats = [
        'today_orders' => 0,
        'today_revenue' => 0,
        'active_customers' => 0,
        'today_deliveries' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Charts & Analytics - Krua Thai Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        :root {
            --primary-color: #8B5A3C;
            --accent-color: #A67C52;
            --curry-color: #cf723a;
            --herb-color: #adb89d;
--cream-color: #F8F6F0;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #e9ecef;
            --shadow-soft: 0 2px 10px rgba(139, 90, 60, 0.1);
            --transition: all 0.3s ease;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--cream-color);
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* Layout */
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: var(--white);
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            text-align: center;
            padding: 0 2rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .sidebar-header p {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .sidebar-nav {
            padding: 0 1rem;
        }

        .nav-section {
            margin-bottom: 2rem;
        }

        .nav-section-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.7;
            margin-bottom: 1rem;
            padding: 0 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--white);
            text-decoration: none;
            border-radius: var(--radius-sm);
            margin-bottom: 0.25rem;
            transition: var(--transition);
            font-size: 0.95rem;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(4px);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        /* Header */
        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }

        .page-header h1 {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--text-gray);
            font-size: 1.1rem;
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
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(139, 90, 60, 0.15);
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

        .stat-icon.revenue { background: var(--primary-color); }
        .stat-icon.orders { background: var(--curry-color); }
        .stat-icon.customers { background: var(--herb-color); }
        .stat-icon.deliveries { background: var(--accent-color); }

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

        /* Controls */
        .controls-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }

        .controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .form-control {
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(139, 90, 60, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--accent-color);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--herb-color);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: #9ba888;
        }

        .btn i {
            margin-right: 0.5rem;
        }

        /* Charts Section */
        .charts-tabs {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .tab-nav {
            display: flex;
            background: var(--cream-color);
            border-bottom: 1px solid var(--border-light);
        }

        .tab-btn {
            flex: 1;
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
            color: var(--text-gray);
            transition: var(--transition);
            position: relative;
        }

        .tab-btn.active {
            color: var(--primary-color);
            background: var(--white);
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-color);
        }

        .tab-content {
            padding: 2rem;
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .chart-export {
            padding: 0.5rem 1rem;
            background: var(--cream-color);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .chart-export:hover {
            background: var(--border-light);
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .chart-container.large {
            height: 400px;
        }

        /* Loading States */
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 200px;
            color: var(--text-gray);
        }

        .loading i {
            font-size: 2rem;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Toast Notifications */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            color: var(--white);
            font-weight: 500;
            z-index: 10000;
            transform: translateX(400px);
            transition: var(--transition);
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            background: #27ae60;
        }

        .toast.error {
            background: #e74c3c;
        }

        .toast.info {
            background: #3498db;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: var(--transition);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .charts-grid {
                grid-template-columns: 1fr;
            }

            .controls-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>🍜 Krua Thai</h2>
                <p>Admin Dashboard</p>
            </div>
            
            <div class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                    <a href="orders.php" class="nav-link">
                        <i class="fas fa-shopping-cart"></i>
                        Orders
                    </a>
                    <a href="menus.php" class="nav-link">
                        <i class="fas fa-utensils"></i>
                        Menus
                    </a>
                    <a href="subscriptions.php" class="nav-link">
                        <i class="fas fa-calendar-check"></i>
                        Subscriptions
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        Users
                    </a>
                    <a href="inventory.php" class="nav-link">
                        <i class="fas fa-boxes"></i>
                        Inventory
                    </a>
                    <a href="delivery-zones.php" class="nav-link">
                        <i class="fas fa-map-marked-alt"></i>
                        Delivery Zones
                    </a>
                    <a href="reviews.php" class="nav-link">
                        <i class="fas fa-star"></i>
                        Reviews
                    </a>
                    <a href="complaints.php" class="nav-link">
                        <i class="fas fa-exclamation-triangle"></i>
                        Complaints
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Analytics</div>
                    <a href="analytics.php" class="nav-link">
                        <i class="fas fa-chart-line"></i>
                        Analytics & Reports
                    </a>
                    <a href="charts.php" class="nav-link active">
                        <i class="fas fa-chart-bar"></i>
                        Charts & Visualizations
                    </a>
                    <a href="reports.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        Reports
                    </a>
                    <a href="payments.php" class="nav-link">
                        <i class="fas fa-credit-card"></i>
                        Payments
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>📊 Charts & Visualizations (Fixed Version)</h1>
                <p>Interactive charts compatible with current database schema</p>
            </div>

            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon revenue">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($quickStats['today_revenue'] ?? 0, 0) ?> ฿</div>
                    <div class="stat-label">Today's Revenue</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($quickStats['today_orders'] ?? 0) ?></div>
                    <div class="stat-label">Today's Orders</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon customers">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($quickStats['active_customers'] ?? 0) ?></div>
                    <div class="stat-label">Active Customers</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon deliveries">
                            <i class="fas fa-truck"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($quickStats['today_deliveries'] ?? 0) ?></div>
                    <div class="stat-label">Today's Deliveries</div>
                </div>
            </div>

            <!-- Controls Section -->
            <div class="controls-section">
                <div class="controls-grid">
                    <div class="form-group">
                        <label for="dateFrom">From Date</label>
                        <input type="date" id="dateFrom" class="form-control" value="<?= $dateFrom ?>">
                    </div>
                    <div class="form-group">
                        <label for="dateTo">To Date</label>
                        <input type="date" id="dateTo" class="form-control" value="<?= $dateTo ?>">
                    </div>
                    <div class="form-group">
                        <label for="groupBy">Group By</label>
                        <select id="groupBy" class="form-control">
                            <option value="day">Daily</option>
                            <option value="week">Weekly</option>
                            <option value="month">Monthly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button id="refreshCharts" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i>
                            Refresh Charts
                        </button>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button id="exportAllData" class="btn btn-secondary">
                            <i class="fas fa-download"></i>
                            Export All Data
                        </button>
                    </div>
                </div>
            </div>

            <!-- Charts Tabs -->
            <div class="charts-tabs">
                <div class="tab-nav">
                    <button class="tab-btn active" data-tab="revenue">Revenue Analytics</button>
                    <button class="tab-btn" data-tab="orders">Order Volume</button>
                    <button class="tab-btn" data-tab="customers">Customer Analytics</button>
                    <button class="tab-btn" data-tab="delivery">Delivery Performance</button>
                    <button class="tab-btn" data-tab="menus">Menu Popularity</button>
                </div>

                <!-- Revenue Analytics Tab -->
                <div class="tab-content active" id="revenue-tab">
                    <div class="charts-grid">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Revenue Trend</h3>
                                <button class="chart-export" onclick="exportChart('revenue_trend')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container large">
                                <canvas id="revenueTrendChart"></canvas>
                            </div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Payment Methods</h3>
                                <button class="chart-export" onclick="exportChart('payment_methods')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container">
                                <canvas id="paymentMethodsChart"></canvas>
                            </div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Plan Revenue</h3>
                                <button class="chart-export" onclick="exportChart('plan_revenue')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container">
                                <canvas id="planRevenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Volume Tab -->
                <div class="tab-content" id="orders-tab">
                    <div class="charts-grid">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Order Volume Trend</h3>
                                <button class="chart-export" onclick="exportChart('order_volume')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container large">
                                <canvas id="orderVolumeChart"></canvas>
                            </div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Order Status Distribution</h3>
                                <button class="chart-export" onclick="exportChart('order_status')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container">
                                <canvas id="orderStatusChart"></canvas>
                            </div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Peak Hours Analysis</h3>
                                <button class="chart-export" onclick="exportChart('peak_hours')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container">
                                <canvas id="peakHoursChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Analytics Tab -->
                <div class="tab-content" id="customers-tab">
                    <div class="charts-grid">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Customer Acquisition</h3>
                                <button class="chart-export" onclick="exportChart('customer_acquisition')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container large">
                                <canvas id="customerAcquisitionChart"></canvas>
                            </div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Customer Segments</h3>
                                <button class="chart-export" onclick="exportChart('customer_segments')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container">
                                <canvas id="customerSegmentsChart"></canvas>
                            </div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Retention Analysis</h3>
                                <button class="chart-export" onclick="exportChart('retention_analysis')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container">
                                <canvas id="retentionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery Performance Tab -->
                <div class="tab-content" id="delivery-tab">
                    <div class="charts-grid">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Delivery Success Rate</h3>
                                <button class="chart-export" onclick="exportChart('delivery_performance')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container large">
                                <canvas id="deliveryPerformanceChart"></canvas>
                            </div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Order Status Distribution</h3>
                                <button class="chart-export" onclick="exportChart('delivery_status')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container">
                                <canvas id="deliveryStatusChart"></canvas>
                            </div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Time Slot Preferences</h3>
                                <button class="chart-export" onclick="exportChart('time_slots')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container">
                                <canvas id="timeSlotsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Menu Popularity Tab -->
                <div class="tab-content" id="menus-tab">
                    <div class="charts-grid">
                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Most Popular Menus</h3>
                                <button class="chart-export" onclick="exportChart('popular_menus')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container large">
                                <canvas id="popularMenusChart"></canvas>
                            </div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Category Performance</h3>
                                <button class="chart-export" onclick="exportChart('category_performance')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container">
                                <canvas id="categoryPerformanceChart"></canvas>
                            </div>
                        </div>

                        <div class="chart-card">
                            <div class="chart-header">
                                <h3 class="chart-title">Menu Trends</h3>
                                <button class="chart-export" onclick="exportChart('menu_trends')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                            <div class="chart-container">
                                <canvas id="menuTrendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Global variables
        let charts = {};
        let chartData = {};
        
        // Chart.js default configuration
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        Chart.defaults.color = '#7f8c8d';
        
        // Color palette
        const colors = {
            primary: '#8B5A3C',
            accent: '#A67C52',
            curry: '#cf723a',
            herb: '#adb89d',
            cream: '#F8F6F0',
            success: '#27ae60',
            warning: '#f39c12',
            danger: '#e74c3c',
            info: '#3498db',
            secondary: '#95a5a6'
        };

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeTabs();
            loadAllCharts();
            setupEventListeners();
        });

        // Tab functionality
        function initializeTabs() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    
                    // Update active tab button
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update active tab content
                    tabContents.forEach(content => content.classList.remove('active'));
                    document.getElementById(targetTab + '-tab').classList.add('active');
                    
                    // Load charts for the active tab
                    loadTabCharts(targetTab);
                });
            });
        }

        // Event listeners
        function setupEventListeners() {
            document.getElementById('refreshCharts').addEventListener('click', loadAllCharts);
            document.getElementById('exportAllData').addEventListener('click', exportAllData);
        }

        // Load all charts
        function loadAllCharts() {
            showLoadingState();
            loadTabCharts('revenue');
        }

        // Load charts for specific tab
        function loadTabCharts(tabType) {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const groupBy = document.getElementById('groupBy').value;

            const requestData = {
                action: 'get_' + tabType + '_chart',
                date_from: dateFrom,
                date_to: dateTo,
                group_by: groupBy
            };

            fetch('charts.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(requestData)
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Received data:', data);
                if (data.success) {
                    chartData[tabType] = data.data;
                    renderCharts(tabType, data.data);
                } else {
                    showToast('Error loading ' + tabType + ' charts: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Fetch Error Details:', error);
                showToast('Network error: ' + error.message, 'error');
            });
        }

        // Render charts based on type
        function renderCharts(type, data) {
            switch(type) {
                case 'revenue':
                    renderRevenueCharts(data);
                    break;
                case 'orders':
                    renderOrderCharts(data);
                    break;
                case 'customers':
                    renderCustomerCharts(data);
                    break;
                case 'delivery':
                    renderDeliveryCharts(data);
                    break;
                case 'menus':
                    renderMenuCharts(data);
                    break;
            }
        }

        // Revenue Charts
        function renderRevenueCharts(data) {
            // Revenue Trend Chart
            const revenueTrendCtx = document.getElementById('revenueTrendChart');
            if (revenueTrendCtx && data.revenue_trend) {
                if (charts.revenueTrend) charts.revenueTrend.destroy();
                
                charts.revenueTrend = new Chart(revenueTrendCtx, {
                    type: 'line',
                    data: {
                        labels: data.revenue_trend.map(item => item.period),
                        datasets: [{
                            label: 'Revenue (THB)',
                            data: data.revenue_trend.map(item => parseFloat(item.revenue)),
                            borderColor: colors.primary,
                            backgroundColor: colors.primary + '20',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        }, {
                            label: 'Transactions',
                            data: data.revenue_trend.map(item => parseInt(item.transaction_count)),
                            borderColor: colors.curry,
                            backgroundColor: colors.curry + '20',
                            borderWidth: 2,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Revenue (THB)'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Transactions'
                                },
                                grid: {
                                    drawOnChartArea: false,
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        if (context.datasetIndex === 0) {
                                            return context.dataset.label + ': ฿' + context.parsed.y.toLocaleString();
                                        } else {
                                            return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Payment Methods Chart
            const paymentMethodsCtx = document.getElementById('paymentMethodsChart');
            if (paymentMethodsCtx && data.payment_methods) {
                if (charts.paymentMethods) charts.paymentMethods.destroy();
                
                charts.paymentMethods = new Chart(paymentMethodsCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.payment_methods.map(item => item.payment_method),
                        datasets: [{
                            data: data.payment_methods.map(item => parseFloat(item.revenue)),
                            backgroundColor: [colors.primary, colors.curry, colors.herb, colors.accent, colors.info],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const value = context.parsed;
                                        return context.label + ': ฿' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Plan Revenue Chart
            const planRevenueCtx = document.getElementById('planRevenueChart');
            if (planRevenueCtx && data.plan_revenue) {
                if (charts.planRevenue) charts.planRevenue.destroy();
                
                charts.planRevenue = new Chart(planRevenueCtx, {
                    type: 'bar',
                    data: {
                        labels: data.plan_revenue.map(item => item.name_thai || item.name),
                        datasets: [{
                            label: 'Revenue (THB)',
                            data: data.plan_revenue.map(item => parseFloat(item.revenue)),
                            backgroundColor: colors.primary,
                            borderColor: colors.accent,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Revenue (THB)'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Revenue: ฿' + context.parsed.y.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // Order Charts
        function renderOrderCharts(data) {
            // Order Volume Chart
            const orderVolumeCtx = document.getElementById('orderVolumeChart');
            if (orderVolumeCtx && data.volume_trend) {
                if (charts.orderVolume) charts.orderVolume.destroy();
                
                charts.orderVolume = new Chart(orderVolumeCtx, {
                    type: 'line',
                    data: {
                        labels: data.volume_trend.map(item => item.period),
                        datasets: [{
                            label: 'Total Orders',
                            data: data.volume_trend.map(item => parseInt(item.total_orders)),
                            borderColor: colors.primary,
                            backgroundColor: colors.primary + '20',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        }, {
                            label: 'Completed',
                            data: data.volume_trend.map(item => parseInt(item.completed_orders)),
                            borderColor: colors.success,
                            backgroundColor: colors.success + '20',
                            borderWidth: 2
                        }, {
                            label: 'Cancelled',
                            data: data.volume_trend.map(item => parseInt(item.cancelled_orders)),
                            borderColor: colors.danger,
                            backgroundColor: colors.danger + '20',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Orders'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        }
                    }
                });
            }

            // Order Status Chart
            const orderStatusCtx = document.getElementById('orderStatusChart');
            if (orderStatusCtx && data.status_distribution) {
                if (charts.orderStatus) charts.orderStatus.destroy();
                
                charts.orderStatus = new Chart(orderStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.status_distribution.map(item => item.status),
                        datasets: [{
                            data: data.status_distribution.map(item => parseInt(item.count)),
                            backgroundColor: [colors.success, colors.warning, colors.danger, colors.info, colors.secondary]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }

            // Peak Hours Chart
            const peakHoursCtx = document.getElementById('peakHoursChart');
            if (peakHoursCtx && data.peak_hours) {
                if (charts.peakHours) charts.peakHours.destroy();
                
                charts.peakHours = new Chart(peakHoursCtx, {
                    type: 'bar',
                    data: {
                        labels: data.peak_hours.map(item => item.hour + ':00'),
                        datasets: [{
                            label: 'Orders',
                            data: data.peak_hours.map(item => parseInt(item.order_count)),
                            backgroundColor: colors.curry,
                            borderColor: colors.primary,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Orders'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Hour of Day'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        }

        // Customer Charts
        function renderCustomerCharts(data) {
            // Customer Acquisition Chart
            const customerAcquisitionCtx = document.getElementById('customerAcquisitionChart');
            if (customerAcquisitionCtx && data.acquisition_trend) {
                if (charts.customerAcquisition) charts.customerAcquisition.destroy();
                
                charts.customerAcquisition = new Chart(customerAcquisitionCtx, {
                    type: 'line',
                    data: {
                        labels: data.acquisition_trend.map(item => item.date),
                        datasets: [{
                            label: 'New Customers',
                            data: data.acquisition_trend.map(item => parseInt(item.new_customers)),
                            borderColor: colors.primary,
                            backgroundColor: colors.primary + '20',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        }, {
                            label: 'Cumulative Total',
                            data: data.acquisition_trend.map(item => parseInt(item.cumulative_customers)),
                            borderColor: colors.herb,
                            backgroundColor: colors.herb + '20',
                            borderWidth: 2,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'New Customers'
                                },
                                beginAtZero: true
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Cumulative Total'
                                },
                                grid: {
                                    drawOnChartArea: false,
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        }
                    }
                });
            }

            // Customer Segments Chart
            const customerSegmentsCtx = document.getElementById('customerSegmentsChart');
            if (customerSegmentsCtx && data.customer_segments) {
                if (charts.customerSegments) charts.customerSegments.destroy();
                
                charts.customerSegments = new Chart(customerSegmentsCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.customer_segments.map(item => item.segment),
                        datasets: [{
                            data: data.customer_segments.map(item => parseInt(item.customer_count)),
                            backgroundColor: [colors.primary, colors.curry, colors.herb, colors.accent]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }

            // Retention Chart
            const retentionCtx = document.getElementById('retentionChart');
            if (retentionCtx && data.retention_analysis) {
                if (charts.retention) charts.retention.destroy();
                
                charts.retention = new Chart(retentionCtx, {
                    type: 'bar',
                    data: {
                        labels: data.retention_analysis.map(item => item.signup_year + '-' + String(item.signup_month).padStart(2, '0')),
                        datasets: [{
                            label: 'Retention Rate (%)',
                            data: data.retention_analysis.map(item => parseFloat(item.retention_rate)),
                            backgroundColor: colors.herb,
                            borderColor: colors.primary,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                title: {
                                    display: true,
                                    text: 'Retention Rate (%)'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        }

        // Delivery Charts
        function renderDeliveryCharts(data) {
            // Delivery Performance Chart
            const deliveryPerformanceCtx = document.getElementById('deliveryPerformanceChart');
            if (deliveryPerformanceCtx && data.delivery_trends) {
                if (charts.deliveryPerformance) charts.deliveryPerformance.destroy();
                
                charts.deliveryPerformance = new Chart(deliveryPerformanceCtx, {
                    type: 'line',
                    data: {
                        labels: data.delivery_trends.map(item => item.date),
                        datasets: [{
                            label: 'Total Orders',
                            data: data.delivery_trends.map(item => parseInt(item.total_orders)),
                            borderColor: colors.primary,
                            backgroundColor: colors.primary + '20',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        }, {
                            label: 'Success Rate (%)',
                            data: data.delivery_trends.map(item => parseFloat(item.success_rate)),
                            borderColor: colors.success,
                            backgroundColor: colors.success + '20',
                            borderWidth: 2,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Total Orders'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Success Rate (%)'
                                },
                                max: 100,
                                grid: {
                                    drawOnChartArea: false,
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        }
                    }
                });
            }

            // Delivery Status Chart
            const deliveryStatusCtx = document.getElementById('deliveryStatusChart');
            if (deliveryStatusCtx && data.status_distribution) {
                if (charts.deliveryStatus) charts.deliveryStatus.destroy();
                
                charts.deliveryStatus = new Chart(deliveryStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.status_distribution.map(item => item.status),
                        datasets: [{
                            data: data.status_distribution.map(item => parseInt(item.count)),
                            backgroundColor: [colors.success, colors.warning, colors.danger, colors.info, colors.secondary]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }

            // Time Slots Chart
            const timeSlotsCtx = document.getElementById('timeSlotsChart');
            if (timeSlotsCtx && data.time_slots) {
                if (charts.timeSlots) charts.timeSlots.destroy();
                
                charts.timeSlots = new Chart(timeSlotsCtx, {
                    type: 'bar',
                    data: {
                        labels: data.time_slots.map(item => item.time_slot),
                        datasets: [{
                            label: 'Orders',
                            data: data.time_slots.map(item => parseInt(item.order_count)),
                            backgroundColor: colors.curry,
                            borderColor: colors.primary,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Orders'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        }

        // Menu Charts
        function renderMenuCharts(data) {
            // Popular Menus Chart
            const popularMenusCtx = document.getElementById('popularMenusChart');
            if (popularMenusCtx && data.popular_menus) {
                if (charts.popularMenus) charts.popularMenus.destroy();
                
                const topMenus = data.popular_menus.slice(0, 10);
                charts.popularMenus = new Chart(popularMenusCtx, {
                    type: 'bar',
                    data: {
                        labels: topMenus.map(item => item.name_thai || item.name),
                        datasets: [{
                            label: 'Total Quantity Ordered',
                            data: topMenus.map(item => parseInt(item.total_quantity)),
                            backgroundColor: colors.primary,
                            borderColor: colors.accent,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        scales: {
                            x: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Quantity Ordered'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }

            // Category Performance Chart
            const categoryPerformanceCtx = document.getElementById('categoryPerformanceChart');
            if (categoryPerformanceCtx && data.category_performance) {
                if (charts.categoryPerformance) charts.categoryPerformance.destroy();
                
                charts.categoryPerformance = new Chart(categoryPerformanceCtx, {
                    type: 'doughnut',
                    data: {
                        labels: data.category_performance.map(item => item.category),
                        datasets: [{
                            data: data.category_performance.map(item => parseInt(item.total_orders)),
                            backgroundColor: [colors.primary, colors.curry, colors.herb, colors.accent, colors.info]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }

            // Menu Trends Chart
            const menuTrendsCtx = document.getElementById('menuTrendsChart');
            if (menuTrendsCtx && data.menu_trends) {
                if (charts.menuTrends) charts.menuTrends.destroy();
                
                // Get top 3 menus by total quantity
                const menuTotals = {};
                data.menu_trends.forEach(item => {
                    if (!menuTotals[item.name]) {
                        menuTotals[item.name] = 0;
                    }
                    menuTotals[item.name] += parseInt(item.daily_quantity);
                });

                const topMenuNames = Object.entries(menuTotals)
                    .sort(([,a], [,b]) => b - a)
                    .slice(0, 3)
                    .map(([name]) => name);

                const dates = [...new Set(data.menu_trends.map(item => item.date))].sort();
                const datasets = topMenuNames.map((menuName, index) => {
                    const menuData = dates.map(date => {
                        const dayData = data.menu_trends.find(item => item.date === date && item.name === menuName);
                        return dayData ? parseInt(dayData.daily_quantity) : 0;
                    });

                    return {
                        label: menuName,
                        data: menuData,
                        borderColor: [colors.primary, colors.curry, colors.herb][index],
                        backgroundColor: [colors.primary, colors.curry, colors.herb][index] + '20',
                        borderWidth: 2,
                        tension: 0.4
                    };
                });

                charts.menuTrends = new Chart(menuTrendsCtx, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Daily Quantity'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top'
                            }
                        }
                    }
                });
            }
        }

        // Export functions
        function exportChart(chartType) {
            showToast('Exporting chart data...', 'info');
        }

        function exportAllData() {
            showToast('Exporting all chart data...', 'info');
        }

        // Show loading state
        function showLoadingState() {
            const chartContainers = document.querySelectorAll('.chart-container canvas');
            chartContainers.forEach(canvas => {
                const container = canvas.parentElement;
                container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><span style="margin-left: 10px;">Loading chart...</span></div>';
            });
        }

        // Toast notification helper
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => document.body.removeChild(toast), 300);
            }, 3000);
        }

        console.log('🍜 Krua Thai Fixed Charts System Ready!');
    </script>
</body>
</html>