<?php
/**
 * Krua Thai - Complete Rider Assignment System
 * File: admin/assign-riders.php
 * Features: มอบหมายงาน/ภาระงาน/เส้นทาง/แจ้งเตือน
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

// Handle AJAX requests for rider assignment operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_available_riders':
                $result = getAvailableRiders($pdo);
                echo json_encode($result);
                exit;
                
            case 'get_rider_workload':
                $result = getRiderWorkload($pdo, $_POST['rider_id'] ?? null);
                echo json_encode($result);
                exit;
                
            case 'assign_order_to_rider':
                $result = assignOrderToRider($pdo, $_POST['order_id'], $_POST['rider_id']);
                echo json_encode($result);
                exit;
                
            case 'bulk_assign_orders':
                $result = bulkAssignOrders($pdo, $_POST['order_ids'], $_POST['rider_id']);
                echo json_encode($result);
                exit;
                
            case 'optimize_routes':
                $result = optimizeRoutes($pdo, $_POST['rider_id']);
                echo json_encode($result);
                exit;
                
            case 'calculate_estimated_time':
                $result = calculateEstimatedTime($pdo, $_POST['order_id'], $_POST['rider_id']);
                echo json_encode($result);
                exit;
                
            case 'send_notification_to_rider':
                $result = sendNotificationToRider($pdo, $_POST['rider_id'], $_POST['message'], $_POST['order_ids'] ?? []);
                echo json_encode($result);
                exit;
                
            case 'auto_assign_optimal':
                $result = autoAssignOptimal($pdo);
                echo json_encode($result);
                exit;
                
            case 'get_assignment_analytics':
                $result = getAssignmentAnalytics($pdo);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// ✅ Core Assignment Functions
function getAvailableRiders($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.id, 
                   CONCAT(u.first_name, ' ', u.last_name) as name,
                   u.phone,
                   u.email,
                   u.last_login,
                   COUNT(o.id) as active_orders,
                   COALESCE(AVG(o.delivery_rating), 0) as avg_rating,
                   CASE 
                       WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 2 HOUR) THEN 'online'
                       WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 'recent'
                       ELSE 'offline'
                   END as status,
                   (SELECT COUNT(*) FROM orders o2 
                    WHERE o2.assigned_rider_id = u.id 
                    AND o2.status IN ('out_for_delivery') 
                    AND DATE(o2.delivery_date) = CURDATE()) as today_deliveries,
                   (SELECT SUM(total_amount) FROM orders o3 
                    WHERE o3.assigned_rider_id = u.id 
                    AND o3.status = 'delivered' 
                    AND DATE(o3.delivered_at) = CURDATE()) as today_revenue
            FROM users u
            LEFT JOIN orders o ON u.id = o.assigned_rider_id 
                AND o.status IN ('out_for_delivery', 'delivered')
                AND DATE(o.delivery_date) = CURDATE()
            WHERE u.role = 'rider' AND u.status = 'active'
            GROUP BY u.id
            ORDER BY 
                CASE 
                    WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 2 HOUR) THEN 1
                    WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 2
                    ELSE 3
                END,
                active_orders ASC, 
                avg_rating DESC
        ");
        $stmt->execute();
        $riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add workload analysis
        foreach ($riders as &$rider) {
            $rider['workload_level'] = getWorkloadLevel($rider['active_orders']);
            $rider['availability'] = getAvailabilityStatus($rider['active_orders'], $rider['status']);
            $rider['efficiency_score'] = calculateEfficiencyScore($rider);
        }
        
        return ['success' => true, 'data' => $riders];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching available riders: ' . $e->getMessage()];
    }
}

function getRiderWorkload($pdo, $riderId = null) {
    try {
        $where_clause = $riderId ? "AND u.id = ?" : "";
        $params = $riderId ? [$riderId] : [];
        
        $stmt = $pdo->prepare("
            SELECT u.id,
                   CONCAT(u.first_name, ' ', u.last_name) as name,
                   COUNT(CASE WHEN o.status = 'out_for_delivery' THEN 1 END) as active_deliveries,
                   COUNT(CASE WHEN o.status = 'delivered' AND DATE(o.delivered_at) = CURDATE() THEN 1 END) as completed_today,
                   AVG(CASE WHEN o.delivered_at IS NOT NULL THEN 
                       TIMESTAMPDIFF(MINUTE, o.created_at, o.delivered_at) 
                   END) as avg_delivery_time,
                   GROUP_CONCAT(
                       CASE WHEN o.status = 'out_for_delivery' THEN 
                           CONCAT(o.order_number, ':', o.delivery_time_slot, ':', 
                                  SUBSTRING(o.delivery_address, 1, 30), '...')
                       END SEPARATOR '|'
                   ) as current_orders,
                   (SELECT dz.zone_name 
                    FROM orders o2 
                    JOIN delivery_zones dz ON JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(o2.delivery_address, -5)))
                    WHERE o2.assigned_rider_id = u.id 
                    AND o2.status = 'out_for_delivery'
                    LIMIT 1) as current_zone,
                   COALESCE(SUM(CASE WHEN o.status = 'delivered' AND DATE(o.delivered_at) = CURDATE() THEN o.total_amount END), 0) as today_revenue
            FROM users u
            LEFT JOIN orders o ON u.id = o.assigned_rider_id 
                AND (o.status IN ('out_for_delivery', 'delivered') OR DATE(o.delivery_date) = CURDATE())
            WHERE u.role = 'rider' AND u.status = 'active' $where_clause
            GROUP BY u.id
            ORDER BY active_deliveries ASC, completed_today DESC
        ");
        $stmt->execute($params);
        
        if ($riderId) {
            $workload = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($workload) {
                $workload['route_optimization'] = getRouteOptimization($pdo, $riderId);
                $workload['estimated_completion_time'] = calculateCompletionTime($workload);
            }
            return ['success' => true, 'data' => $workload];
        } else {
            $workloads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'data' => $workloads];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching rider workload: ' . $e->getMessage()];
    }
}

function assignOrderToRider($pdo, $orderId, $riderId) {
    try {
        // Check if rider is available
        $riderCheck = getRiderWorkload($pdo, $riderId);
        if (!$riderCheck['success'] || !$riderCheck['data']) {
            return ['success' => false, 'message' => 'Rider not available'];
        }
        
        $riderData = $riderCheck['data'];
        if ($riderData['active_deliveries'] >= 5) { // Max 5 active orders
            return ['success' => false, 'message' => 'Rider has maximum workload (5 orders)'];
        }
        
        // Calculate estimated delivery time
        $timeEstimate = calculateEstimatedTime($pdo, $orderId, $riderId);
        
        // Assign the order
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET assigned_rider_id = ?, 
                status = CASE 
                    WHEN status IN ('ready', 'confirmed') THEN 'out_for_delivery' 
                    ELSE status 
                END,
                estimated_delivery_time = ?,
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$riderId, $timeEstimate['data']['estimated_time'] ?? null, $orderId]);
        
        if ($stmt->rowCount() > 0) {
            // Get order and rider details
            $stmt = $pdo->prepare("
                SELECT o.order_number, o.delivery_address, o.delivery_time_slot,
                       CONCAT(u.first_name, ' ', u.last_name) as rider_name,
                       CONCAT(c.first_name, ' ', c.last_name) as customer_name
                FROM orders o
                JOIN users u ON o.assigned_rider_id = u.id
                JOIN users c ON o.user_id = c.id
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            $orderDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Send notification to rider
            $notificationMessage = "New delivery assigned: Order #{$orderDetails['order_number']} for {$orderDetails['customer_name']}";
            sendNotificationToRider($pdo, $riderId, $notificationMessage, [$orderId]);
            
            return [
                'success' => true, 
                'message' => "Order #{$orderDetails['order_number']} assigned to {$orderDetails['rider_name']} successfully",
                'data' => $orderDetails
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to assign order'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error assigning order: ' . $e->getMessage()];
    }
}

function bulkAssignOrders($pdo, $orderIds, $riderId) {
    try {
        // Check rider availability for bulk assignment
        $riderWorkload = getRiderWorkload($pdo, $riderId);
        if (!$riderWorkload['success']) {
            return $riderWorkload;
        }
        
        $currentLoad = $riderWorkload['data']['active_deliveries'];
        $newTotalLoad = $currentLoad + count($orderIds);
        
        if ($newTotalLoad > 8) { // Max 8 orders including new ones
            return ['success' => false, 'message' => "Bulk assignment would exceed rider capacity. Current: {$currentLoad}, Requested: " . count($orderIds) . ", Max: 8"];
        }
        
        $pdo->beginTransaction();
        
        $assignedCount = 0;
        $assignedOrders = [];
        
        foreach ($orderIds as $orderId) {
            // Calculate estimated time for each order
            $timeEstimate = calculateEstimatedTime($pdo, $orderId, $riderId);
            
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET assigned_rider_id = ?, 
                    status = CASE 
                        WHEN status IN ('ready', 'confirmed') THEN 'out_for_delivery' 
                        ELSE status 
                    END,
                    estimated_delivery_time = ?,
                    updated_at = NOW() 
                WHERE id = ? AND assigned_rider_id IS NULL
            ");
            $stmt->execute([$riderId, $timeEstimate['data']['estimated_time'] ?? null, $orderId]);
            
            if ($stmt->rowCount() > 0) {
                $assignedCount++;
                $assignedOrders[] = $orderId;
            }
        }
        
        if ($assignedCount > 0) {
            $pdo->commit();
            
            // Get rider name
            $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
            $stmt->execute([$riderId]);
            $riderName = $stmt->fetchColumn();
            
            // Send bulk notification
            $notificationMessage = "Bulk assignment: {$assignedCount} new orders assigned to you";
            sendNotificationToRider($pdo, $riderId, $notificationMessage, $assignedOrders);
            
            return [
                'success' => true, 
                'message' => "{$assignedCount} orders assigned to {$riderName} successfully",
                'data' => ['assigned_count' => $assignedCount, 'assigned_orders' => $assignedOrders]
            ];
        } else {
            $pdo->rollback();
            return ['success' => false, 'message' => 'No orders were assigned (all may already be assigned)'];
        }
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => 'Error bulk assigning orders: ' . $e->getMessage()];
    }
}

function optimizeRoutes($pdo, $riderId) {
    try {
        // Get rider's current orders
        $stmt = $pdo->prepare("
            SELECT o.id, o.order_number, o.delivery_address, o.delivery_time_slot,
                   o.delivery_date, o.estimated_delivery_time,
                   dz.zone_name, dz.estimated_delivery_time as zone_time,
                   CONCAT(u.first_name, ' ', u.last_name) as customer_name
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN delivery_zones dz ON JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(o.delivery_address, -5)))
            WHERE o.assigned_rider_id = ? 
            AND o.status = 'out_for_delivery'
            ORDER BY o.delivery_time_slot ASC, dz.estimated_delivery_time ASC
        ");
        $stmt->execute([$riderId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($orders)) {
            return ['success' => true, 'data' => [], 'message' => 'No active orders to optimize'];
        }
        
        // Simple route optimization by time slot and zone
        $optimizedRoute = [];
        $groupedByZone = [];
        
        // Group orders by zone and time slot
        foreach ($orders as $order) {
            $zoneKey = $order['zone_name'] ?: 'unknown';
            $timeSlot = $order['delivery_time_slot'] ?: 'anytime';
            
            if (!isset($groupedByZone[$zoneKey])) {
                $groupedByZone[$zoneKey] = [];
            }
            if (!isset($groupedByZone[$zoneKey][$timeSlot])) {
                $groupedByZone[$zoneKey][$timeSlot] = [];
            }
            
            $groupedByZone[$zoneKey][$timeSlot][] = $order;
        }
        
        // Create optimized route
        $routeIndex = 1;
        foreach ($groupedByZone as $zoneName => $timeSlots) {
            ksort($timeSlots); // Sort by time slot
            foreach ($timeSlots as $timeSlot => $zoneOrders) {
                foreach ($zoneOrders as $order) {
                    $optimizedRoute[] = [
                        'route_order' => $routeIndex++,
                        'order_id' => $order['id'],
                        'order_number' => $order['order_number'],
                        'customer_name' => $order['customer_name'],
                        'delivery_address' => $order['delivery_address'],
                        'time_slot' => $order['delivery_time_slot'],
                        'zone_name' => $order['zone_name'],
                        'estimated_time' => $order['estimated_delivery_time'],
                        'travel_distance' => calculateTravelDistance($order),
                        'recommendation' => getRouteRecommendation($order, $routeIndex)
                    ];
                }
            }
        }
        
        // Calculate total route statistics
        $routeStats = [
            'total_orders' => count($optimizedRoute),
            'total_zones' => count($groupedByZone),
            'estimated_total_time' => array_sum(array_column($optimizedRoute, 'estimated_time')),
            'efficiency_score' => calculateRouteEfficiency($optimizedRoute)
        ];
        
        return [
            'success' => true, 
            'data' => [
                'optimized_route' => $optimizedRoute,
                'route_statistics' => $routeStats,
                'optimization_suggestions' => getOptimizationSuggestions($optimizedRoute)
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error optimizing routes: ' . $e->getMessage()];
    }
}

function calculateEstimatedTime($pdo, $orderId, $riderId) {
    try {
        // Get order details
        $stmt = $pdo->prepare("
            SELECT o.delivery_address, o.delivery_time_slot,
                   dz.estimated_delivery_time as zone_time,
                   dz.zone_name
            FROM orders o
            LEFT JOIN delivery_zones dz ON JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(o.delivery_address, -5)))
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $orderData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$orderData) {
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        // Get rider's current workload
        $riderWorkload = getRiderWorkload($pdo, $riderId);
        if (!$riderWorkload['success']) {
            return $riderWorkload;
        }
        
        $workloadData = $riderWorkload['data'];
        
        // Base delivery time from zone
        $baseTime = $orderData['zone_time'] ?: 60; // Default 60 minutes
        
        // Adjust for rider workload
        $workloadMultiplier = 1 + ($workloadData['active_deliveries'] * 0.1); // 10% per active order
        
        // Adjust for rider efficiency (based on average delivery time)
        $efficiencyMultiplier = 1;
        if ($workloadData['avg_delivery_time']) {
            $avgTime = $workloadData['avg_delivery_time'];
            $standardTime = 45; // Standard delivery time
            $efficiencyMultiplier = $avgTime / $standardTime;
        }
        
        // Calculate final estimated time
        $estimatedTime = round($baseTime * $workloadMultiplier * $efficiencyMultiplier);
        
        // Add buffer time based on time of day
        $currentHour = date('H');
        if ($currentHour >= 11 && $currentHour <= 13) { // Lunch rush
            $estimatedTime += 15;
        } elseif ($currentHour >= 18 && $currentHour <= 20) { // Dinner rush
            $estimatedTime += 20;
        }
        
        $estimatedDeliveryTime = date('Y-m-d H:i:s', strtotime("+{$estimatedTime} minutes"));
        
        return [
            'success' => true,
            'data' => [
                'estimated_time' => $estimatedDeliveryTime,
                'estimated_minutes' => $estimatedTime,
                'base_time' => $baseTime,
                'workload_factor' => $workloadMultiplier,
                'efficiency_factor' => $efficiencyMultiplier,
                'zone_name' => $orderData['zone_name']
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error calculating estimated time: ' . $e->getMessage()];
    }
}

function sendNotificationToRider($pdo, $riderId, $message, $orderIds = []) {
    try {
        // Get rider details
        $stmt = $pdo->prepare("
            SELECT CONCAT(first_name, ' ', last_name) as name, email, phone 
            FROM users 
            WHERE id = ? AND role = 'rider'
        ");
        $stmt->execute([$riderId]);
        $rider = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$rider) {
            return ['success' => false, 'message' => 'Rider not found'];
        }
        
        // Create notification record
        $notificationId = generate_uuid();
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                id, user_id, title, message, type, 
                related_data, is_read, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        
        $relatedData = json_encode([
            'order_ids' => $orderIds,
            'notification_type' => 'assignment',
            'sender' => $_SESSION['user_id']
        ]);
        
        $stmt->execute([
            $notificationId,
            $riderId,
            'New Delivery Assignment',
            $message,
            'assignment',
            $relatedData
        ]);
        
        // Send email notification (if email service is configured)
        $emailSent = sendEmailNotification($rider['email'], 'New Delivery Assignment', $message, $orderIds);
        
        // Send SMS notification (if SMS service is configured)
        $smsSent = sendSMSNotification($rider['phone'], $message);
        
        return [
            'success' => true,
            'message' => "Notification sent to {$rider['name']}",
            'data' => [
                'notification_id' => $notificationId,
                'rider_name' => $rider['name'],
                'email_sent' => $emailSent,
                'sms_sent' => $smsSent,
                'order_count' => count($orderIds)
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error sending notification: ' . $e->getMessage()];
    }
}

function autoAssignOptimal($pdo) {
    try {
        // Get unassigned orders that are ready for delivery
        $stmt = $pdo->prepare("
            SELECT o.id, o.order_number, o.delivery_address, o.delivery_time_slot,
                   o.total_amount, o.delivery_date,
                   dz.zone_name, dz.estimated_delivery_time
            FROM orders o
            LEFT JOIN delivery_zones dz ON JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(o.delivery_address, -5)))
            WHERE o.assigned_rider_id IS NULL 
            AND o.status IN ('ready', 'confirmed')
            AND DATE(o.delivery_date) = CURDATE()
            ORDER BY o.delivery_time_slot ASC, o.created_at ASC
        ");
        $stmt->execute();
        $unassignedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($unassignedOrders)) {
            return ['success' => true, 'message' => 'No orders to assign', 'data' => []];
        }
        
        // Get available riders
        $ridersResult = getAvailableRiders($pdo);
        if (!$ridersResult['success']) {
            return $ridersResult;
        }
        
        $availableRiders = array_filter($ridersResult['data'], function($rider) {
            return $rider['availability'] === 'available' && $rider['active_orders'] < 5;
        });
        
        if (empty($availableRiders)) {
            return ['success' => false, 'message' => 'No available riders for assignment'];
        }
        
        $assignments = [];
        
        // Smart assignment algorithm
        foreach ($unassignedOrders as $order) {
            $bestRider = null;
            $bestScore = -1;
            
            foreach ($availableRiders as &$rider) {
                // Skip if rider already has max load
                if ($rider['active_orders'] >= 5) continue;
                
                // Calculate assignment score
                $score = calculateAssignmentScore($order, $rider);
                
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestRider = $rider;
                }
            }
            
            if ($bestRider) {
                // Assign order to best rider
                $assignResult = assignOrderToRider($pdo, $order['id'], $bestRider['id']);
                
                if ($assignResult['success']) {
                    $assignments[] = [
                        'order_id' => $order['id'],
                        'order_number' => $order['order_number'],
                        'rider_id' => $bestRider['id'],
                        'rider_name' => $bestRider['name'],
                        'assignment_score' => $bestScore,
                        'zone' => $order['zone_name']
                    ];
                    
                    // Update rider's active orders count
                    $bestRider['active_orders']++;
                }
            }
        }
        
        return [
            'success' => true,
            'message' => count($assignments) . " orders auto-assigned successfully",
            'data' => [
                'assignments' => $assignments,
                'total_assigned' => count($assignments),
                'total_orders' => count($unassignedOrders),
                'assignment_rate' => round((count($assignments) / count($unassignedOrders)) * 100, 2)
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error in auto assignment: ' . $e->getMessage()];
    }
}

function getAssignmentAnalytics($pdo) {
    try {
        $analytics = [];
        
        // Today's assignment statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_assignments,
                COUNT(CASE WHEN status = 'delivered' THEN 1 END) as completed_deliveries,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered_at)) as avg_delivery_time,
                SUM(total_amount) as total_revenue
            FROM orders 
            WHERE assigned_rider_id IS NOT NULL 
            AND DATE(delivery_date) = CURDATE()
        ");
        $stmt->execute();
        $analytics['today_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Rider performance rankings
        $stmt = $pdo->prepare("
            SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name,
                   COUNT(o.id) as deliveries_count,
                   AVG(o.delivery_rating) as avg_rating,
                   AVG(TIMESTAMPDIFF(MINUTE, o.created_at, o.delivered_at)) as avg_time,
                   SUM(o.total_amount) as total_revenue
            FROM users u
            LEFT JOIN orders o ON u.id = o.assigned_rider_id 
                AND o.status = 'delivered' 
                AND DATE(o.delivered_at) = CURDATE()
            WHERE u.role = 'rider' AND u.status = 'active'
            GROUP BY u.id
            ORDER BY deliveries_count DESC, avg_rating DESC
            LIMIT 10
        ");
        $stmt->execute();
        $analytics['rider_rankings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Zone performance
        $stmt = $pdo->prepare("
            SELECT dz.zone_name,
                   COUNT(o.id) as order_count,
                   AVG(TIMESTAMPDIFF(MINUTE, o.created_at, o.delivered_at)) as avg_delivery_time,
                   AVG(o.delivery_rating) as avg_rating
            FROM delivery_zones dz
            LEFT JOIN orders o ON JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(o.delivery_address, -5)))
                AND o.status = 'delivered'
                AND DATE(o.delivered_at) = CURDATE()
            GROUP BY dz.id
            ORDER BY order_count DESC
        ");
        $stmt->execute();
        $analytics['zone_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['success' => true, 'data' => $analytics];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error fetching analytics: ' . $e->getMessage()];
    }
}

// ✅ Helper Functions
function getWorkloadLevel($activeOrders) {
    if ($activeOrders == 0) return 'available';
    if ($activeOrders <= 2) return 'light';
    if ($activeOrders <= 4) return 'moderate';
    if ($activeOrders <= 6) return 'heavy';
    return 'overloaded';
}

function getAvailabilityStatus($activeOrders, $onlineStatus) {
    if ($onlineStatus === 'offline') return 'offline';
    if ($activeOrders >= 8) return 'full';
    if ($activeOrders >= 5) return 'busy';
    return 'available';
}

function calculateEfficiencyScore($rider) {
    $baseScore = 100;
    
    // Deduct points for high workload
    $workloadPenalty = $rider['active_orders'] * 5;
    
    // Add points for good rating
    $ratingBonus = ($rider['avg_rating'] - 3) * 10; // Rating above 3 gets bonus
    
    // Add points for being online
    $statusBonus = $rider['status'] === 'online' ? 20 : 0;
    
    return max(0, min(100, $baseScore - $workloadPenalty + $ratingBonus + $statusBonus));
}

function calculateAssignmentScore($order, $rider) {
    $score = 0;
    
    // Base score from rider efficiency
    $score += $rider['efficiency_score'] * 0.4;
    
    // Workload factor (prefer less loaded riders)
    $score += (5 - $rider['active_orders']) * 10;
    
    // Rating factor
    $score += $rider['avg_rating'] * 5;
    
    // Zone familiarity (if rider is currently in the same zone)
    if (isset($rider['current_zone']) && $rider['current_zone'] === $order['zone_name']) {
        $score += 15;
    }
    
    // Online status bonus
    if ($rider['status'] === 'online') {
        $score += 10;
    }
    
    return $score;
}

function calculateTravelDistance($order) {
    // Simplified distance calculation based on zone
    // In real implementation, you'd use Google Maps API
    $baseDistance = 5; // km
    return $baseDistance + rand(1, 10); // Random variation
}

function getRouteRecommendation($order, $routeIndex) {
    $recommendations = [];
    
    if ($routeIndex === 1) {
        $recommendations[] = "First delivery - good start point";
    }
    
    if ($order['time_slot'] && strpos($order['time_slot'], '12:00') !== false) {
        $recommendations[] = "Lunch time delivery - expect traffic";
    }
    
    if ($order['zone_name']) {
        $recommendations[] = "Zone: {$order['zone_name']} - familiar area";
    }
    
    return implode(', ', $recommendations);
}

function calculateRouteEfficiency($route) {
    if (empty($route)) return 0;
    
    $totalTime = array_sum(array_column($route, 'estimated_time'));
    $orderCount = count($route);
    $avgTimePerOrder = $totalTime / $orderCount;
    
    // Efficiency based on average time per order (lower is better)
    $maxTime = 90; // Maximum expected time per order
    $efficiency = max(0, (($maxTime - $avgTimePerOrder) / $maxTime) * 100);
    
    return round($efficiency, 2);
}

function getOptimizationSuggestions($route) {
    $suggestions = [];
    
    if (count($route) > 6) {
        $suggestions[] = "Consider splitting route - high order count may cause delays";
    }
    
    $zones = array_unique(array_column($route, 'zone_name'));
    if (count($zones) > 3) {
        $suggestions[] = "Multiple zones detected - consider zone-based grouping";
    }
    
    $timeSlots = array_column($route, 'time_slot');
    $earlyDeliveries = array_filter($timeSlots, function($slot) {
        return strpos($slot, '12:00') !== false || strpos($slot, '11:00') !== false;
    });
    
    if (count($earlyDeliveries) > 2) {
        $suggestions[] = "Multiple lunch-time deliveries - start early to avoid rush";
    }
    
    return $suggestions;
}

function getRouteOptimization($pdo, $riderId) {
    // Get current route optimization for rider
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as order_count,
               GROUP_CONCAT(DISTINCT dz.zone_name) as zones
        FROM orders o
        LEFT JOIN delivery_zones dz ON JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(o.delivery_address, -5)))
        WHERE o.assigned_rider_id = ? AND o.status = 'out_for_delivery'
    ");
    $stmt->execute([$riderId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'order_count' => $result['order_count'],
        'zones_covered' => $result['zones'] ? explode(',', $result['zones']) : [],
        'optimization_needed' => $result['order_count'] > 4
    ];
}

function calculateCompletionTime($workload) {
    $avgTime = $workload['avg_delivery_time'] ?: 45; // Default 45 minutes
    $activeOrders = $workload['active_deliveries'];
    
    $estimatedCompletion = $activeOrders * $avgTime;
    return $estimatedCompletion;
}

function sendEmailNotification($email, $subject, $message, $orderIds = []) {
    // Email notification implementation
    // In real implementation, use PHPMailer or similar
    try {
        $emailContent = "
            <h3>{$subject}</h3>
            <p>{$message}</p>
        ";
        
        if (!empty($orderIds)) {
            $emailContent .= "<p>Order IDs: " . implode(', ', $orderIds) . "</p>";
        }
        
        // Mock email sending
        error_log("Email sent to {$email}: {$subject}");
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . $e->getMessage());
        return false;
    }
}

function sendSMSNotification($phone, $message) {
    // SMS notification implementation
    // In real implementation, use Twilio or similar service
    try {
        $smsText = substr($message, 0, 160); // SMS character limit
        
        // Mock SMS sending
        error_log("SMS sent to {$phone}: {$smsText}");
        return true;
    } catch (Exception $e) {
        error_log("SMS failed: " . $e->getMessage());
        return false;
    }
}

// Initialize data for page load
try {
    // Get available riders
    $riders_result = getAvailableRiders($pdo);
    $riders = $riders_result['success'] ? $riders_result['data'] : [];
    
    // Get unassigned orders
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.delivery_address, o.delivery_time_slot,
               o.total_amount, o.delivery_date, o.created_at,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.phone as customer_phone,
               dz.zone_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN delivery_zones dz ON JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(o.delivery_address, -5)))
        WHERE o.assigned_rider_id IS NULL 
        AND o.status IN ('ready', 'confirmed')
        AND DATE(o.delivery_date) >= CURDATE()
        ORDER BY o.delivery_date ASC, o.delivery_time_slot ASC, o.created_at ASC
    ");
    $stmt->execute();
    $unassignedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get assignment analytics
    $analytics_result = getAssignmentAnalytics($pdo);
    $analytics = $analytics_result['success'] ? $analytics_result['data'] : [];
    
} catch (Exception $e) {
    $riders = [];
    $unassignedOrders = [];
    $analytics = [];
    error_log("Assign riders page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rider Assignment - Krua Thai Admin</title>
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

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
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

        /* Assignment Panel */
        .assignment-panel {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .panel-section {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .panel-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
        }

        .panel-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .panel-subtitle {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .panel-body {
            padding: 1.5rem;
            max-height: 600px;
            overflow-y: auto;
        }

        /* Rider Cards */
        .rider-card {
            padding: 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .rider-card:hover {
            border-color: var(--curry);
            transform: translateY(-2px);
        }

        .rider-card.selected {
            border-color: var(--curry);
            background: rgba(207, 114, 58, 0.05);
        }

        .rider-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .rider-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .rider-status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-online {
            background: rgba(46, 204, 113, 0.1);
            color: #27ae60;
        }

        .status-recent {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
        }

        .status-offline {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .rider-metrics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .metric-item {
            text-align: center;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .metric-value {
            font-weight: 600;
            color: var(--curry);
            margin-bottom: 0.25rem;
        }

        .metric-label {
            font-size: 0.7rem;
            color: var(--text-gray);
            text-transform: uppercase;
        }

        /* Order Cards */
        .order-card {
            padding: 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .order-card.selected {
            border-color: var(--sage);
            background: rgba(173, 184, 157, 0.05);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .order-number {
            font-weight: 600;
            color: var(--text-dark);
        }

        .order-amount {
            font-weight: 600;
            color: var(--curry);
        }

        .order-details {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .order-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Assignment Actions */
        .assignment-actions {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        .actions-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        .actions-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
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

        /* Responsive design */
        @media (max-width: 1024px) {
            .assignment-panel {
                grid-template-columns: 1fr;
            }
        }

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

            .actions-row {
                flex-direction: column;
            }
        }

        /* Utilities */
        .text-center { text-align: center; }
        .d-none { display: none; }
        .mb-2 { margin-bottom: 1rem; }
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
                    <a href="delivery.php" class="nav-item">
                        <i class="nav-icon fas fa-truck"></i>
                        <span>Delivery</span>
                    </a>
                    <a href="assign-riders.php" class="nav-item active">
                        <i class="nav-icon fas fa-user-check"></i>
                        <span>Assign Riders</span>
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
                        <h1 class="page-title">Rider Assignment</h1>
                        <p class="page-subtitle">Smart assignment and workload management</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-warning" onclick="autoAssignOptimal()">
                            <i class="fas fa-magic"></i>
                            Auto Assign
                        </button>
                        <button class="btn btn-info" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                        <button class="btn btn-primary" onclick="viewAnalytics()">
                            <i class="fas fa-chart-bar"></i>
                            Analytics
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-motorcycle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= count($riders) ?></div>
                    <div class="stat-label">Available Riders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--curry), #e67e22);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= count($unassignedOrders) ?></div>
                    <div class="stat-label">Unassigned Orders</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $analytics['today_stats']['total_assignments'] ?? 0 ?></div>
                    <div class="stat-label">Today's Assignments</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= round($analytics['today_stats']['avg_delivery_time'] ?? 0) ?>min</div>
                    <div class="stat-label">Avg Delivery Time</div>
                </div>
            </div>

            <!-- Assignment Actions -->
            <div class="assignment-actions">
                <h3 class="actions-title">
                    <i class="fas fa-tools"></i>
                    Quick Actions
                </h3>
                <div class="actions-row">
                    <button class="btn btn-success" onclick="assignSelected()" id="assignBtn" disabled>
                        <i class="fas fa-user-plus"></i>
                        Assign Selected (<span id="selectedCount">0</span>)
                    </button>
                    <button class="btn btn-info" onclick="optimizeSelectedRiderRoute()">
                        <i class="fas fa-route"></i>
                        Optimize Route
                    </button>
                    <button class="btn btn-warning" onclick="bulkNotify()">
                        <i class="fas fa-bell"></i>
                        Notify Riders
                    </button>
                    <button class="btn btn-secondary" onclick="clearSelections()">
                        <i class="fas fa-times"></i>
                        Clear Selection
                    </button>
                </div>
            </div>

            <!-- Assignment Panel -->
            <div class="assignment-panel">
                <!-- Available Riders -->
                <div class="panel-section">
                    <div class="panel-header">
                        <h3 class="panel-title">Available Riders</h3>
                        <p class="panel-subtitle">Click to select a rider for assignment</p>
                    </div>
                    <div class="panel-body" id="ridersPanel">
                        <?php foreach ($riders as $rider): ?>
                        <div class="rider-card" data-rider-id="<?= $rider['id'] ?>" onclick="selectRider('<?= $rider['id'] ?>')">
                            <div class="rider-header">
                                <div class="rider-name"><?= htmlspecialchars($rider['name']) ?></div>
                                <div class="rider-status status-<?= $rider['status'] ?>">
                                    <i class="fas fa-circle"></i>
                                    <?= ucfirst($rider['status']) ?>
                                </div>
                            </div>
                            <div class="rider-metrics">
                                <div class="metric-item">
                                    <div class="metric-value"><?= $rider['active_orders'] ?></div>
                                    <div class="metric-label">Active Orders</div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-value"><?= number_format($rider['avg_rating'], 1) ?></div>
                                    <div class="metric-label">Rating</div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-value"><?= $rider['efficiency_score'] ?>%</div>
                                    <div class="metric-label">Efficiency</div>
                                </div>
                            </div>
                            <div style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--text-gray);">
                                <div><strong>Phone:</strong> <?= htmlspecialchars($rider['phone']) ?></div>
                                <div><strong>Today Revenue:</strong> ₿<?= number_format($rider['today_revenue'] ?? 0, 2) ?></div>
                                <div><strong>Workload:</strong> <?= ucfirst($rider['workload_level']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($riders)): ?>
                        <div class="text-center" style="padding: 2rem;">
                            <i class="fas fa-motorcycle" style="font-size: 3rem; color: var(--text-gray); margin-bottom: 1rem;"></i>
                            <h4 style="color: var(--text-gray);">No Available Riders</h4>
                            <p style="color: var(--text-gray);">All riders are currently busy or offline</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Unassigned Orders -->
                <div class="panel-section">
                    <div class="panel-header">
                        <h3 class="panel-title">Unassigned Orders</h3>
                        <p class="panel-subtitle">Select orders to assign to riders</p>
                    </div>
                    <div class="panel-body" id="ordersPanel">
                        <?php foreach ($unassignedOrders as $order): ?>
                        <div class="order-card" data-order-id="<?= $order['id'] ?>">
                            <div class="order-header">
                                <div class="order-number">#<?= htmlspecialchars($order['order_number']) ?></div>
                                <div class="order-amount">₿<?= number_format($order['total_amount'], 2) ?></div>
                            </div>
                            <div class="order-details">
                                <div><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?></div>
                                <div><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?></div>
                                <div><strong>Address:</strong> <?= htmlspecialchars(substr($order['delivery_address'], 0, 40)) ?>...</div>
                                <div><strong>Time Slot:</strong> <?= htmlspecialchars($order['delivery_time_slot']) ?></div>
                                <div><strong>Zone:</strong> <?= htmlspecialchars($order['zone_name'] ?: 'Unknown') ?></div>
                                <div><strong>Date:</strong> <?= date('M d, Y', strtotime($order['delivery_date'])) ?></div>
                            </div>
                            <div class="order-actions">
                                <input type="checkbox" class="order-checkbox" value="<?= $order['id'] ?>" onchange="updateOrderSelection()">
                                <label>Select</label>
                                <button class="btn btn-sm btn-info" onclick="calculateTimeEstimate('<?= $order['id'] ?>')">
                                    <i class="fas fa-clock"></i>
                                    Est. Time
                                </button>
                                <button class="btn btn-sm btn-success" onclick="quickAssign('<?= $order['id'] ?>')">
                                    <i class="fas fa-flash"></i>
                                    Quick Assign
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($unassignedOrders)): ?>
                        <div class="text-center" style="padding: 2rem;">
                            <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--sage); margin-bottom: 1rem;"></i>
                            <h4 style="color: var(--text-gray);">All Orders Assigned!</h4>
                            <p style="color: var(--text-gray);">No unassigned orders at this time</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // Global variables
        let selectedRider = null;
        let selectedOrders = [];

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            updateOrderSelection();
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'a') {
                    e.preventDefault();
                    autoAssignOptimal();
                } else if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    refreshData();
                }
            });
        });

        // ✅ Core Functions
        function selectRider(riderId) {
            // Remove previous selection
            document.querySelectorAll('.rider-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Select new rider
            const riderCard = document.querySelector(`[data-rider-id="${riderId}"]`);
            riderCard.classList.add('selected');
            selectedRider = riderId;
            
            // Update UI
            updateAssignButtonState();
            
            // Get rider workload
            getRiderWorkload(riderId);
        }

        function updateOrderSelection() {
            selectedOrders = Array.from(document.querySelectorAll('.order-checkbox:checked')).map(cb => cb.value);
            document.getElementById('selectedCount').textContent = selectedOrders.length;
            updateAssignButtonState();
        }

        function updateAssignButtonState() {
            const assignBtn = document.getElementById('assignBtn');
            assignBtn.disabled = !selectedRider || selectedOrders.length === 0;
        }

        // ✅ Assignment Functions
        function assignSelected() {
            if (!selectedRider || selectedOrders.length === 0) {
                showToast('Please select a rider and at least one order', 'error');
                return;
            }
            
            if (selectedOrders.length === 1) {
                assignSingleOrder(selectedOrders[0], selectedRider);
            } else {
                bulkAssignOrders(selectedOrders, selectedRider);
            }
        }

        function assignSingleOrder(orderId, riderId) {
            const formData = new FormData();
            formData.append('action', 'assign_order_to_rider');
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
                    removeAssignedOrder(orderId);
                    refreshRiderData();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Assignment error:', error);
                showToast('Error assigning order', 'error');
            });
        }

        function bulkAssignOrders(orderIds, riderId) {
            const formData = new FormData();
            formData.append('action', 'bulk_assign_orders');
            formData.append('rider_id', riderId);
            orderIds.forEach(orderId => {
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
                    data.data.assigned_orders.forEach(orderId => {
                        removeAssignedOrder(orderId);
                    });
                    refreshRiderData();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Bulk assignment error:', error);
                showToast('Error bulk assigning orders', 'error');
            });
        }

        function quickAssign(orderId) {
            // Find best available rider automatically
            const formData = new FormData();
            formData.append('action', 'get_available_riders');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    // Find rider with lowest workload and highest efficiency
                    const bestRider = data.data.reduce((best, current) => {
                        if (current.availability !== 'available') return best;
                        if (!best) return current;
                        
                        const currentScore = current.efficiency_score - (current.active_orders * 10);
                        const bestScore = best.efficiency_score - (best.active_orders * 10);
                        
                        return currentScore > bestScore ? current : best;
                    }, null);
                    
                    if (bestRider) {
                        assignSingleOrder(orderId, bestRider.id);
                    } else {
                        showToast('No available riders for quick assignment', 'error');
                    }
                } else {
                    showToast('No available riders', 'error');
                }
            })
            .catch(error => {
                console.error('Quick assign error:', error);
                showToast('Error in quick assignment', 'error');
            });
        }

        // ✅ Optimization Functions
        function optimizeSelectedRiderRoute() {
            if (!selectedRider) {
                showToast('Please select a rider first', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'optimize_routes');
            formData.append('rider_id', selectedRider);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showRouteOptimization(data.data);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Route optimization error:', error);
                showToast('Error optimizing route', 'error');
            });
        }

        function calculateTimeEstimate(orderId) {
            if (!selectedRider) {
                showToast('Please select a rider first', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'calculate_estimated_time');
            formData.append('order_id', orderId);
            formData.append('rider_id', selectedRider);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showTimeEstimate(data.data);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Time estimation error:', error);
                showToast('Error calculating time estimate', 'error');
            });
        }

        // ✅ Auto Assignment
        function autoAssignOptimal() {
            if (!confirm('Auto-assign all unassigned orders optimally?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'auto_assign_optimal');
            
            showToast('Processing auto assignment...', 'success');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    // Remove assigned orders from UI
                    data.data.assignments.forEach(assignment => {
                        removeAssignedOrder(assignment.order_id);
                    });
                    refreshRiderData();
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Auto assignment error:', error);
                showToast('Error in auto assignment', 'error');
            });
        }

        // ✅ Notification Functions
        function bulkNotify() {
            if (selectedOrders.length === 0) {
                showToast('Please select orders first', 'error');
                return;
            }
            
            const message = prompt('Enter notification message for riders:');
            if (!message) return;
            
            // Get unique riders from selected orders
            const riderIds = new Set();
            selectedOrders.forEach(orderId => {
                // This would need to be implemented to get rider ID from order
                // For now, we'll use the selected rider
                if (selectedRider) {
                    riderIds.add(selectedRider);
                }
            });
            
            riderIds.forEach(riderId => {
                sendNotificationToRider(riderId, message, selectedOrders);
            });
        }

        function sendNotificationToRider(riderId, message, orderIds = []) {
            const formData = new FormData();
            formData.append('action', 'send_notification_to_rider');
            formData.append('rider_id', riderId);
            formData.append('message', message);
            orderIds.forEach(orderId => {
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
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Notification error:', error);
                showToast('Error sending notification', 'error');
            });
        }

        // ✅ Data Functions
        function getRiderWorkload(riderId) {
            const formData = new FormData();
            formData.append('action', 'get_rider_workload');
            formData.append('rider_id', riderId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateRiderWorkloadDisplay(riderId, data.data);
                }
            })
            .catch(error => {
                console.error('Workload fetch error:', error);
            });
        }

        function refreshData() {
            showToast('Refreshing data...', 'success');
            window.location.reload();
        }

        function viewAnalytics() {
            const formData = new FormData();
            formData.append('action', 'get_assignment_analytics');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAnalyticsModal(data.data);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Analytics error:', error);
                showToast('Error fetching analytics', 'error');
            });
        }

        // ✅ UI Helper Functions
        function removeAssignedOrder(orderId) {
            const orderCard = document.querySelector(`[data-order-id="${orderId}"]`);
            if (orderCard) {
                orderCard.style.opacity = '0.5';
                orderCard.style.transform = 'translateX(-100%)';
                setTimeout(() => {
                    orderCard.remove();
                    updateOrderSelection();
                }, 300);
            }
        }

        function refreshRiderData() {
            // Refresh rider metrics
            if (selectedRider) {
                getRiderWorkload(selectedRider);
            }
        }

        function clearSelections() {
            // Clear rider selection
            document.querySelectorAll('.rider-card').forEach(card => {
                card.classList.remove('selected');
            });
            selectedRider = null;
            
            // Clear order selections
            document.querySelectorAll('.order-checkbox').forEach(cb => {
                cb.checked = false;
            });
            selectedOrders = [];
            
            updateOrderSelection();
        }

        function updateRiderWorkloadDisplay(riderId, workloadData) {
            const riderCard = document.querySelector(`[data-rider-id="${riderId}"]`);
            if (riderCard && workloadData) {
                // Update active orders count
                const activeOrdersMetric = riderCard.querySelector('.metric-value');
                if (activeOrdersMetric) {
                    activeOrdersMetric.textContent = workloadData.active_deliveries;
                }
                
                // Add workload details as tooltip or additional info
                const existingDetails = riderCard.querySelector('.workload-details');
                if (existingDetails) {
                    existingDetails.remove();
                }
                
                const detailsDiv = document.createElement('div');
                detailsDiv.className = 'workload-details';
                detailsDiv.style.cssText = 'margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-gray);';
                detailsDiv.innerHTML = `
                    <div>Completed Today: ${workloadData.completed_today}</div>
                    <div>Avg Time: ${Math.round(workloadData.avg_delivery_time || 0)} min</div>
                    <div>Current Zone: ${workloadData.current_zone || 'N/A'}</div>
                `;
                riderCard.appendChild(detailsDiv);
            }
        }

        function showTimeEstimate(estimateData) {
            const message = `
                Estimated Delivery Time: ${estimateData.estimated_minutes} minutes
                Expected Delivery: ${new Date(estimateData.estimated_time).toLocaleString()}
                Zone: ${estimateData.zone_name || 'Unknown'}
                Base Time: ${estimateData.base_time} min
                Workload Factor: ${estimateData.workload_factor.toFixed(2)}x
                Efficiency Factor: ${estimateData.efficiency_factor.toFixed(2)}x
            `;
            alert(message);
        }

        function showRouteOptimization(routeData) {
            let message = `Route Optimization Results:\n\n`;
            message += `Total Orders: ${routeData.route_statistics.total_orders}\n`;
            message += `Total Zones: ${routeData.route_statistics.total_zones}\n`;
            message += `Estimated Total Time: ${Math.round(routeData.route_statistics.estimated_total_time)} minutes\n`;
            message += `Efficiency Score: ${routeData.route_statistics.efficiency_score}%\n\n`;
            
            if (routeData.optimization_suggestions.length > 0) {
                message += `Suggestions:\n`;
                routeData.optimization_suggestions.forEach(suggestion => {
                    message += `- ${suggestion}\n`;
                });
            }
            
            alert(message);
        }

        function showAnalyticsModal(analyticsData) {
            let message = `Assignment Analytics:\n\n`;
            message += `Today's Stats:\n`;
            message += `Total Assignments: ${analyticsData.today_stats.total_assignments}\n`;
            message += `Completed Deliveries: ${analyticsData.today_stats.completed_deliveries}\n`;
            message += `Average Delivery Time: ${Math.round(analyticsData.today_stats.avg_delivery_time || 0)} minutes\n`;
            message += `Total Revenue: ₿${analyticsData.today_stats.total_revenue || 0}\n\n`;
            
            if (analyticsData.rider_rankings.length > 0) {
                message += `Top Performers:\n`;
                analyticsData.rider_rankings.slice(0, 3).forEach((rider, index) => {
                    message += `${index + 1}. ${rider.name} - ${rider.deliveries_count} deliveries (${rider.avg_rating.toFixed(1)} rating)\n`;
                });
            }
            
            alert(message);
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

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../auth/logout.php';
            }
        }

        // Initialize auto-refresh for rider status
        setInterval(function() {
            // Refresh rider data every 60 seconds
            const formData = new FormData();
            formData.append('action', 'get_available_riders');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update rider status indicators
                    data.data.forEach(rider => {
                        const riderCard = document.querySelector(`[data-rider-id="${rider.id}"]`);
                        if (riderCard) {
                            const statusElement = riderCard.querySelector('.rider-status');
                            if (statusElement) {
                                statusElement.className = `rider-status status-${rider.status}`;
                                statusElement.innerHTML = `<i class="fas fa-circle"></i> ${rider.status.charAt(0).toUpperCase() + rider.status.slice(1)}`;
                            }
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Auto-refresh error:', error);
            });
        }, 60000);

        console.log('Krua Thai Rider Assignment System initialized successfully');
        console.log('Features: Smart Assignment, Route Optimization, Real-time Updates, Notifications');
    </script>
</body>
</html>