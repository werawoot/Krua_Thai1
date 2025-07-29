<?php
/**
 * Somdul Table - Simplified Delivery Management System
 * File: admin/delivery-management.php
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../login.php"); 
    exit();
}

// Database connection
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'optimize_delivery_order':
                $result = optimizeDeliveryOrder($pdo, $_POST['date']);
                echo json_encode($result);
                exit;
                
            case 'assign_rider_to_customer':
                $result = assignRiderToCustomer($pdo, $_POST['subscription_id'], $_POST['rider_id']);
                echo json_encode($result);
                exit;
                
            case 'remove_rider_from_customer':
                $result = removeRiderFromCustomer($pdo, $_POST['subscription_id']);
                echo json_encode($result);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Get current date from query parameter or use today
$deliveryDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Check if valid delivery date (Wednesday or Saturday)
if (!isValidDeliveryDate($deliveryDate)) {
    $upcomingDays = getUpcomingDeliveryDays();
    $deliveryDate = !empty($upcomingDays) ? $upcomingDays[0]['date'] : date('Y-m-d');
}

// Restaurant Location (Somdul Table)
$shopLocation = [
    'lat' => 33.888121,
    'lng' => -117.868256,
    'address' => '3250 Yorba Linda Blvd, Fullerton, CA 92831',
    'name' => 'Somdul Table'
];

// Zip Code Coordinates (from old system)
$zipCoordinates = [
    // ----- Zone A: 0–8 miles (Fullerton & surrounding) -----
    // Fullerton
    '92831' => ['lat'=>33.8703, 'lng'=>-117.9253],
    '92832' => ['lat'=>33.8847, 'lng'=>-117.9390],
    '92833' => ['lat'=>33.8889, 'lng'=>-117.9256],
    '92834' => ['lat'=>33.9172, 'lng'=>-117.9467],
    '92835' => ['lat'=>33.8892, 'lng'=>-117.8817],
    // Yorba Linda
    '92885' => ['lat'=>33.8881, 'lng'=>-117.8132],
    '92886' => ['lat'=>33.8950, 'lng'=>-117.7890],
    '92887' => ['lat'=>33.9020, 'lng'=>-117.8200],
    // Placentia
    '92870' => ['lat'=>33.8722, 'lng'=>-117.8554],
    // Brea
    '92821' => ['lat'=>33.9097, 'lng'=>-117.9006],
    '92823' => ['lat'=>33.9267, 'lng'=>-117.8653],
    // La Habra
    '90631' => ['lat'=>33.9312, 'lng'=>-117.9462],
    '90632' => ['lat'=>33.9148, 'lng'=>-117.9370],
    
    // ----- Zone B: 8–15 miles -----
    '90620' => ['lat'=>33.8408, 'lng'=>-118.0011], // Buena Park
    '90621' => ['lat'=>33.8803, 'lng'=>-117.9322], // Buena Park
    '92801' => ['lat'=>33.8353, 'lng'=>-117.9145], // Anaheim
    '92802' => ['lat'=>33.8025, 'lng'=>-117.9228], // Anaheim
    '92804' => ['lat'=>33.8172, 'lng'=>-117.8978], // Anaheim
    '92805' => ['lat'=>33.8614, 'lng'=>-117.9078], // Anaheim
    '92806' => ['lat'=>33.8260, 'lng'=>-117.9243], // Anaheim
    '92807' => ['lat'=>33.8455, 'lng'=>-117.7583], // Anaheim Hills
    '92808' => ['lat'=>33.8115, 'lng'=>-117.8311], // Anaheim Hills

    // ----- Zone C: 15–25 miles -----
    '92840' => ['lat'=>33.7742, 'lng'=>-117.9378], // Garden Grove
    '92841' => ['lat'=>33.7894, 'lng'=>-117.9578], // Garden Grove
    '92843' => ['lat'=>33.7739, 'lng'=>-117.9028], // Garden Grove
    '92683' => ['lat'=>33.7175, 'lng'=>-117.9581], // Westminster

    // ----- Zone D: 25+ miles -----
    '92703' => ['lat'=>33.7492, 'lng'=>-117.8731], // Santa Ana
    '92647' => ['lat'=>33.7247, 'lng'=>-118.0056], // Huntington Beach
    '92648' => ['lat'=>33.6597, 'lng'=>-117.9992], // Huntington Beach
];

// Calculate distances and assign zones
foreach ($zipCoordinates as $zip => &$data) {
    $d = calculateDistance(
        $shopLocation['lat'], $shopLocation['lng'],
        $data['lat'], $data['lng']
    );
    $data['distance'] = round($d, 1);
    if      ($d <= 8)  $data['zone'] = 'A';
    elseif  ($d <= 15) $data['zone'] = 'B';
    elseif  ($d <= 25) $data['zone'] = 'C';
    else               $data['zone'] = 'D';
}
unset($data);

// ======================================================================
// UTILITY FUNCTIONS
// ======================================================================

function safeHtmlSpecialChars($string, $flags = ENT_QUOTES, $encoding = 'UTF-8') {
    return htmlspecialchars($string ?? '', $flags, $encoding);
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 3959; // Miles
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;
    
    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

function calculateRiderRouteDistance($riderOrders, $shopLocation) {
    if (empty($riderOrders)) {
        return ['totalDistance' => 0, 'route' => []];
    }
    
    // Start from shop
    $currentLat = $shopLocation['lat'];
    $currentLng = $shopLocation['lng'];
    $totalDistance = 0;
    $route = [];
    
    // Add shop as starting point
    $route[] = [
        'name' => 'Somdul Table (Start)',
        'address' => $shopLocation['address'],
        'lat' => $currentLat,
        'lng' => $currentLng,
        'distance_from_previous' => 0
    ];
    
    foreach ($riderOrders as $order) {
        if ($order['latitude'] && $order['longitude']) {
            $distance = calculateDistance(
                $currentLat, $currentLng,
                $order['latitude'], $order['longitude']
            );
            
            $totalDistance += $distance;
            
            $route[] = [
                'name' => $order['first_name'] . ' ' . $order['last_name'],
                'address' => $order['delivery_address'],
                'lat' => $order['latitude'],
                'lng' => $order['longitude'],
                'distance_from_previous' => round($distance, 2),
                'total_items' => $order['total_items']
            ];
            
            // Update current position for next calculation
            $currentLat = $order['latitude'];
            $currentLng = $order['longitude'];
        }
    }
    
    return [
        'totalDistance' => round($totalDistance, 2),
        'route' => $route,
        'stops' => count($riderOrders),
        'estimatedTime' => calculateEstimatedTime($totalDistance, count($riderOrders))
    ];
}

function calculateEstimatedTime($distance, $stops) {
    // Rough calculation: 
    // - Average 25 mph driving speed in city
    // - 10 minutes per stop for delivery
    $drivingTime = ($distance / 25) * 60; // Convert to minutes
    $deliveryTime = $stops * 10; // 10 minutes per stop
    
    return round($drivingTime + $deliveryTime);
}

function getUpcomingDeliveryDays($weeks = 4) {
    $deliveryDays = [];
    $today = new DateTime();
    
    for ($week = 0; $week < $weeks; $week++) {
        $wednesday = clone $today;
        $wednesday->modify("+" . $week . " weeks");
        $wednesday->modify("wednesday this week");
        
        $saturday = clone $today;
        $saturday->modify("+" . $week . " weeks");
        $saturday->modify("saturday this week");
        
        if ($wednesday >= $today) {
            $deliveryDays[] = [
                'date' => $wednesday->format('Y-m-d'),
                'display' => 'Wednesday ' . $wednesday->format('M d, Y')
            ];
        }
        
        if ($saturday >= $today) {
            $deliveryDays[] = [
                'date' => $saturday->format('Y-m-d'),
                'display' => 'Saturday ' . $saturday->format('M d, Y')
            ];
        }
    }
    
    return $deliveryDays;
}

function sanitizeTimeSlot($timeSlot) {
    if (empty($timeSlot)) return '12:00-15:00';
    
    $timeSlot = strtolower(trim($timeSlot));
    
    if (strpos($timeSlot, 'morning') !== false || strpos($timeSlot, '9:') !== false) {
        return '09:00-12:00';
    } elseif (strpos($timeSlot, 'evening') !== false || strpos($timeSlot, '18:') !== false) {
        return '18:00-21:00';
    } elseif (strpos($timeSlot, 'lunch') !== false || strpos($timeSlot, '12:') !== false) {
        return '12:00-15:00';  
    } elseif (strpos($timeSlot, 'afternoon') !== false || strpos($timeSlot, '15:') !== false) {
        return '15:00-18:00';
    } else {
        $validSlots = ['09:00-12:00', '12:00-15:00', '15:00-18:00', '18:00-21:00'];
        if (in_array($timeSlot, $validSlots)) {
            return $timeSlot;
        }
        return '12:00-15:00';
    }
}

function isValidDeliveryDate($date) {
    $dayOfWeek = date('N', strtotime($date));
    return in_array($dayOfWeek, [3, 6]); // Wednesday(3) and Saturday(6)
}

function autoGenerateOrdersFromSubscriptions($pdo, $date) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                s.id as subscription_id,
                s.user_id,
                s.preferred_delivery_time,
                s.delivery_days,
                COUNT(sm.id) as total_items,
                SUM(sm.quantity) as total_quantity
            FROM subscriptions s
            JOIN subscription_menus sm ON s.id = sm.subscription_id
            LEFT JOIN orders o ON s.id = o.subscription_id AND DATE(o.delivery_date) = ?
            WHERE sm.delivery_date = ?
            AND s.status = 'active'
            AND sm.status = 'scheduled'
            AND o.id IS NULL
            GROUP BY s.id, s.user_id, s.preferred_delivery_time, s.delivery_days
        ");
        $stmt->execute([$date, $date]);
        $missing_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $generated_count = 0;
        
        foreach ($missing_orders as $subscription) {
            // Check if this date is in the delivery_days array
            $deliveryDays = json_decode($subscription['delivery_days'], true) ?? [];
            $isValidDeliveryDay = empty($deliveryDays) || in_array($date, $deliveryDays);
            
            if (!$isValidDeliveryDay) {
                continue; // Skip if this date is not in delivery_days
            }
            
            $order_id = generateUUID();
            $order_number = 'ORD-' . date('Ymd', strtotime($date)) . '-' . substr($order_id, 0, 6);
            
            $stmt = $pdo->prepare("SELECT delivery_address FROM users WHERE id = ?");
            $stmt->execute([$subscription['user_id']]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    id, subscription_id, user_id, order_number,
                    delivery_date, delivery_time_slot, delivery_address,
                    total_items, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', NOW(), NOW())
            ");
            
            $stmt->execute([
                $order_id,
                $subscription['subscription_id'],
                $subscription['user_id'],
                $order_number,
                $date,
                sanitizeTimeSlot($subscription['preferred_delivery_time']),
                $user_data['delivery_address'] ?? '',
                $subscription['total_quantity']
            ]);
            
            $generated_count++;
        }
        
        return [
            'success' => true,
            'generated' => $generated_count,
            'message' => "Auto-generated {$generated_count} orders from subscriptions for {$date}"
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error auto-generating orders: ' . $e->getMessage()
        ];
    }
}

function optimizeDeliveryOrder($pdo, $date) {
    try {
        global $shopLocation, $zipCoordinates;
        
        // Get all subscriptions for the date (instead of orders)
        $stmt = $pdo->prepare("
            SELECT s.id, s.user_id, s.delivery_days, s.preferred_delivery_time,
                   u.first_name, u.last_name, u.delivery_address, u.zip_code,
                   SUM(sm.quantity) as total_items
            FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            JOIN subscription_menus sm ON s.id = sm.subscription_id
            WHERE sm.delivery_date = ? 
            AND s.status = 'active'
            AND sm.status = 'scheduled'
            AND s.start_date <= ?
            AND (s.end_date IS NULL OR s.end_date >= ?)
            GROUP BY s.id, s.user_id, s.delivery_days, s.preferred_delivery_time,
                     u.first_name, u.last_name, u.delivery_address, u.zip_code
            ORDER BY s.created_at
        ");
        $stmt->execute([$date, $date, $date]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($subscriptions)) {
            return ['success' => false, 'message' => 'No subscriptions to optimize'];
        }
        
        // Filter by delivery_days and add coordinates
        $validSubscriptions = [];
        foreach ($subscriptions as $subscription) {
            $deliveryDays = json_decode($subscription['delivery_days'], true) ?? [];
            $isValidDeliveryDay = empty($deliveryDays) || in_array($date, $deliveryDays);
            
            if ($isValidDeliveryDay) {
                $zipCode = substr($subscription['zip_code'], 0, 5);
                if (isset($zipCoordinates[$zipCode])) {
                    $subscription['latitude'] = $zipCoordinates[$zipCode]['lat'];
                    $subscription['longitude'] = $zipCoordinates[$zipCode]['lng'];
                    $subscription['distance'] = $zipCoordinates[$zipCode]['distance'];
                } else {
                    $subscription['latitude'] = null;
                    $subscription['longitude'] = null;
                    $subscription['distance'] = 0;
                }
                $validSubscriptions[] = $subscription;
            }
        }
        
        if (empty($validSubscriptions)) {
            return ['success' => false, 'message' => 'No valid subscriptions for this date'];
        }
        
        // Simple optimization algorithm: Nearest neighbor from shop
        $optimized_subscriptions = [];
        $remaining_subscriptions = $validSubscriptions;
        $current_location = $shopLocation;
        
        while (!empty($remaining_subscriptions)) {
            $nearest_index = 0;
            $shortest_distance = PHP_FLOAT_MAX;
            
            foreach ($remaining_subscriptions as $index => $subscription) {
                $lat = $subscription['latitude'];
                $lng = $subscription['longitude'];
                
                if ($lat === null || $lng === null) {
                    continue;
                }
                
                $distance = calculateDistance(
                    $current_location['lat'], 
                    $current_location['lng'],
                    $lat, 
                    $lng
                );
                
                if ($distance < $shortest_distance) {
                    $shortest_distance = $distance;
                    $nearest_index = $index;
                }
            }
            
            // Add the nearest subscription to optimized list
            $nearest_subscription = $remaining_subscriptions[$nearest_index];
            $nearest_subscription['distance'] = round($shortest_distance, 2);
            $nearest_subscription['order_sequence'] = count($optimized_subscriptions) + 1;
            
            $optimized_subscriptions[] = $nearest_subscription;
            
            // Update current location
            if ($nearest_subscription['latitude'] && $nearest_subscription['longitude']) {
                $current_location = [
                    'lat' => $nearest_subscription['latitude'],
                    'lng' => $nearest_subscription['longitude']
                ];
            }
            
            // Remove from remaining subscriptions
            array_splice($remaining_subscriptions, $nearest_index, 1);
        }
        
        // Note: No database update for sequence since we're working with subscriptions
        // The sequence is returned for display purposes only
        
        return [
            'success' => true,
            'message' => 'Delivery order optimized successfully',
            'optimized_orders' => $optimized_subscriptions,
            'total_distance' => array_sum(array_column($optimized_subscriptions, 'distance'))
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error optimizing delivery order: ' . $e->getMessage()
        ];
    }
}

function assignRiderToCustomer($pdo, $subscriptionId, $riderId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE subscriptions 
            SET assigned_rider_id = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$riderId, $subscriptionId]);
        
        // Get rider name for response
        $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
        $stmt->execute([$riderId]);
        $riderName = $stmt->fetchColumn();
        
        return [
            'success' => true,
            'message' => "Rider {$riderName} assigned successfully"
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error assigning rider: ' . $e->getMessage()
        ];
    }
}

function removeRiderFromCustomer($pdo, $subscriptionId) {
    try {
        // Get rider name before removing (for response message)
        $stmt = $pdo->prepare("
            SELECT CONCAT(u.first_name, ' ', u.last_name) as rider_name 
            FROM subscriptions s 
            JOIN users u ON s.assigned_rider_id = u.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$subscriptionId]);
        $riderName = $stmt->fetchColumn();
        
        // Remove rider assignment
        $stmt = $pdo->prepare("
            UPDATE subscriptions 
            SET assigned_rider_id = NULL, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$subscriptionId]);
        
        return [
            'success' => true,
            'message' => $riderName ? "Rider {$riderName} removed successfully" : "Rider assignment removed successfully"
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error removing rider: ' . $e->getMessage()
        ];
    }
}

// ======================================================================
// FETCH DATA
// ======================================================================

try {
    // Get subscriptions for the delivery date (not orders)
    $stmt = $pdo->prepare("
        SELECT s.id, s.user_id, s.status, s.assigned_rider_id, s.delivery_days,
               s.preferred_delivery_time, s.start_date, s.end_date,
               u.id as user_id, u.first_name, u.last_name, u.phone, 
               u.delivery_address, u.city, u.state, u.zip_code,
               r.first_name as rider_first_name, r.last_name as rider_last_name,
               COUNT(sm.id) as total_items,
               SUM(sm.quantity) as total_quantity
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        JOIN subscription_menus sm ON s.id = sm.subscription_id
        LEFT JOIN users r ON s.assigned_rider_id = r.id
        WHERE sm.delivery_date = ? 
        AND s.status = 'active'
        AND sm.status = 'scheduled'
        AND s.start_date <= ?
        AND (s.end_date IS NULL OR s.end_date >= ?)
        GROUP BY s.id, s.user_id, s.status, s.assigned_rider_id, s.delivery_days,
                 s.preferred_delivery_time, s.start_date, s.end_date,
                 u.id, u.first_name, u.last_name, u.phone, 
                 u.delivery_address, u.city, u.state, u.zip_code,
                 r.first_name, r.last_name
        ORDER BY s.created_at
    ");
    $stmt->execute([$deliveryDate, $deliveryDate, $deliveryDate]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter subscriptions by delivery_days (specific dates)
    $orders = []; // We'll call them orders for consistency with the UI
    foreach ($subscriptions as $subscription) {
        $deliveryDays = json_decode($subscription['delivery_days'], true) ?? [];
        $isValidDeliveryDay = empty($deliveryDays) || in_array($deliveryDate, $deliveryDays);
        
        if ($isValidDeliveryDay) {
            // Convert subscription to order format for UI consistency
            $order = [
                'id' => $subscription['id'],
                'subscription_id' => $subscription['id'],
                'order_number' => 'SUB-' . $subscription['id'],
                'user_id' => $subscription['user_id'],
                'first_name' => $subscription['first_name'],
                'last_name' => $subscription['last_name'],
                'phone' => $subscription['phone'],
                'delivery_address' => $subscription['delivery_address'],
                'city' => $subscription['city'],
                'state' => $subscription['state'],
                'zip_code' => $subscription['zip_code'],
                'total_items' => $subscription['total_quantity'],
                'status' => 'confirmed', // Subscription status
                'assigned_rider_id' => $subscription['assigned_rider_id'],
                'rider_first_name' => $subscription['rider_first_name'],
                'rider_last_name' => $subscription['rider_last_name'],
                'delivery_date' => $deliveryDate,
                'delivery_time_slot' => $subscription['preferred_delivery_time'],
                'created_at' => $subscription['start_date']
            ];
            $orders[] = $order;
        }
    }
    
    // DEBUG: Test basic database connection first
    $testStmt = $pdo->query("SELECT 1 as test");
    $testResult = $testStmt->fetch();
    $connectionWorking = ($testResult && $testResult['test'] == 1);
    
    // DEBUG: Test simple count queries
    $subscriptionCountStmt = $pdo->query("SELECT COUNT(*) as count FROM subscriptions");
    $totalSubscriptionCount = $subscriptionCountStmt->fetch()['count'] ?? 0;
    
    $userCountStmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $totalUserCount = $userCountStmt->fetch()['count'] ?? 0;
    
    // DEBUG: Test the exact date query manually
    $dateTestStmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM subscriptions s
        JOIN subscription_menus sm ON s.id = sm.subscription_id
        WHERE sm.delivery_date = ? 
        AND s.status = 'active'
        AND sm.status = 'scheduled'
    ");
    $dateTestStmt->execute([$deliveryDate]);
    $dateTestCount = $dateTestStmt->fetch()['count'] ?? 0;
    
    // Get available riders
    $riderStmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.phone, u.status,
               COUNT(s.id) as current_orders
        FROM users u
        LEFT JOIN subscriptions s ON u.id = s.assigned_rider_id AND s.status = 'active'
        WHERE u.role = 'rider' AND u.status = 'active'
        GROUP BY u.id, u.first_name, u.last_name, u.phone, u.status
        ORDER BY current_orders ASC
    ");
    $riderStmt->execute();
    $riders = $riderStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Store debug info for display
    $debugInfo = [
        'connectionWorking' => $connectionWorking,
        'totalSubscriptionCount' => $totalSubscriptionCount,
        'totalUserCount' => $totalUserCount,
        'dateTestCount' => $dateTestCount,
        'subscriptionsFound' => count($subscriptions),
        'ordersFound' => count($orders),
        'ridersFound' => count($riders),
        'deliveryDate' => $deliveryDate
    ];
    
} catch (Exception $e) {
    $orders = [];
    $riders = [];
    $debugInfo = [
        'error' => $e->getMessage(),
        'connectionWorking' => false,
        'totalSubscriptionCount' => 0,
        'totalUserCount' => 0,
        'dateTestCount' => 0,
        'subscriptionsFound' => 0,
        'ordersFound' => 0,
        'ridersFound' => 0,
        'deliveryDate' => $deliveryDate
    ];
    error_log("Delivery management error: " . $e->getMessage());
}

// Calculate statistics and add coordinates from zip codes
$totalStats = [
    'totalOrders' => count($orders),
    'totalBoxes' => array_sum(array_column($orders, 'total_items')),
    'assignedOrders' => count(array_filter($orders, function($o) { return !empty($o['assigned_rider_id']); })),
    'unassignedOrders' => count(array_filter($orders, function($o) { return empty($o['assigned_rider_id']); })),
    'totalDistance' => 0
];

// Add coordinates and calculate distances for each order
foreach ($orders as &$order) {
    $zipCode = substr($order['zip_code'], 0, 5);
    
    if (isset($zipCoordinates[$zipCode])) {
        $order['latitude'] = $zipCoordinates[$zipCode]['lat'];
        $order['longitude'] = $zipCoordinates[$zipCode]['lng'];
        $order['distance'] = $zipCoordinates[$zipCode]['distance'];
        $order['zone'] = $zipCoordinates[$zipCode]['zone'];
        $totalStats['totalDistance'] += $order['distance'];
    } else {
        $order['latitude'] = null;
        $order['longitude'] = null;
        $order['distance'] = 0;
        $order['zone'] = 'Unknown';
    }
}

// Calculate rider routes and distances
$riderRoutes = [];
foreach ($riders as $rider) {
    $riderOrders = array_filter($orders, function($order) use ($rider) {
        return $order['assigned_rider_id'] == $rider['id'];
    });
    
    if (!empty($riderOrders)) {
        $routeInfo = calculateRiderRouteDistance($riderOrders, $shopLocation);
        $riderRoutes[$rider['id']] = [
            'rider' => $rider,
            'orders' => array_values($riderOrders),
            'route_info' => $routeInfo
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Management - Somdul Table Admin</title>
    <link href="https://ydpschool.com/fonts/" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="css/delivery-management.css">
    <style>
        .rider-routes-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .rider-route-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
        }
        
        .rider-route-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #cf723a;
        }
        
        .rider-info h4 {
            margin: 0;
            color: #cf723a;
            font-size: 1.2rem;
        }
        
        .route-stats {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        
        .route-stat {
            text-align: center;
        }
        
        .route-stat .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #bd9379;
        }
        
        .route-stat .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .route-details {
            margin-top: 1rem;
        }
        
        .route-stop {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #dee2e6;
        }
        
        .route-stop:last-child {
            border-bottom: none;
        }
        
        .stop-number {
            background: #cf723a;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
            font-size: 0.9rem;
        }
        
        .stop-info {
            flex: 1;
        }
        
        .stop-distance {
            color: #6c757d;
            font-size: 0.9rem;
            margin-left: auto;
        }
        
        .no-riders-assigned {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .remove-rider-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            margin-left: 0.5rem;
        }
        
        .remove-rider-btn:hover {
            background: #c82333;
        }
        
        .assigned-rider {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .rider-name {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Somdul Table</h2>
                <p>Admin Panel</p>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="orders.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
                <a href="delivery-management.php" class="nav-item active">
                    <i class="fas fa-truck"></i>
                    <span>Delivery Management</span>
                </a>
                <a href="users.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <a href="../logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <h1>Delivery Management</h1>
                        <p>Manage and optimize daily deliveries</p>
                    </div>
                    <div class="header-actions">
                        <div class="date-selector">
                            <i class="fas fa-calendar-alt"></i>
                            <form method="GET">
                                <select name="date" onchange="this.form.submit()">
                                    <?php 
                                    $deliveryDays = getUpcomingDeliveryDays();
                                    foreach ($deliveryDays as $day): 
                                    ?>
                                        <option value="<?= $day['date'] ?>" <?= $day['date'] == $deliveryDate ? 'selected' : '' ?>>
                                            <?= $day['display'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <button class="btn btn-primary" onclick="generateOrders()">
                            <i class="fas fa-plus"></i>
                            Generate Orders
                        </button>
                    </div>
                </div>
            </div>

            <!-- Debug Information (remove in production) -->
            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                <div style="background: #f8f9fa; padding: 1rem; margin-bottom: 1rem; border-radius: 8px; border-left: 4px solid #007bff;">
                    <h4>🔍 Advanced Debug Information (Using Subscriptions Table)</h4>
                    
                    <div style="background: white; padding: 1rem; margin: 1rem 0; border-radius: 4px;">
                        <h5>Database Connection Test:</h5>
                        <p><strong>Connection Working:</strong> <?= ($debugInfo['connectionWorking'] ?? false) ? '✅ YES' : '❌ NO' ?></p>
                        <p><strong>Total Subscriptions in DB:</strong> <?= $debugInfo['totalSubscriptionCount'] ?? 'Unknown' ?></p>
                        <p><strong>Total Users in DB:</strong> <?= $debugInfo['totalUserCount'] ?? 'Unknown' ?></p>
                    </div>
                    
                    <div style="background: white; padding: 1rem; margin: 1rem 0; border-radius: 4px;">
                        <h5>Date Query Test:</h5>
                        <p><strong>Date:</strong> <?= $debugInfo['deliveryDate'] ?? $deliveryDate ?> (<?= date('l', strtotime($deliveryDate)) ?>)</p>
                        <p><strong>Subscriptions for this date (count only):</strong> <?= $debugInfo['dateTestCount'] ?? 'Unknown' ?></p>
                        <p><strong>Raw subscriptions found:</strong> <?= $debugInfo['subscriptionsFound'] ?? 'Unknown' ?></p>
                        <p><strong>Filtered orders (after delivery_days check):</strong> <?= $debugInfo['ordersFound'] ?? 'Unknown' ?></p>
                        <p><strong>Riders found:</strong> <?= $debugInfo['ridersFound'] ?? 'Unknown' ?></p>
                    </div>
                    
                    <?php if (isset($debugInfo['error'])): ?>
                        <div style="background: #f8d7da; color: #721c24; padding: 1rem; margin: 1rem 0; border-radius: 4px;">
                            <h5>❌ Error Detected:</h5>
                            <p><?= htmlspecialchars($debugInfo['error']) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div style="background: white; padding: 1rem; margin: 1rem 0; border-radius: 4px;">
                        <h5>Manual Test Queries (for Subscriptions):</h5>
                        <p><strong>Test Connection:</strong><br>
                        <code>SELECT 1 as test;</code></p>
                        
                        <p><strong>Count Subscriptions for Date:</strong><br>
                        <code>SELECT COUNT(*) FROM subscriptions s JOIN subscription_menus sm ON s.id = sm.subscription_id WHERE sm.delivery_date = '<?= $deliveryDate ?>' AND s.status = 'active' AND sm.status = 'scheduled';</code></p>
                        
                        <p><strong>Count Active Riders:</strong><br>
                        <code>SELECT COUNT(*) FROM users WHERE role = 'rider' AND status = 'active';</code></p>
                        
                        <p><strong>Show Tables:</strong><br>
                        <code>SHOW TABLES;</code></p>
                    </div>
                    
                    <?php if (!empty($orders)): ?>
                        <div style="background: #d4edda; color: #155724; padding: 1rem; margin: 1rem 0; border-radius: 4px;">
                            <h5>✅ Sample Order Found:</h5>
                            <pre><?= htmlspecialchars(json_encode($orders[0], JSON_PRETTY_PRINT)) ?></pre>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $totalStats['totalOrders'] ?></h3>
                        <p>Total Orders</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $totalStats['totalBoxes'] ?></h3>
                        <p>Total Meals</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-route"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $totalStats['totalDistance'] > 0 ? number_format($totalStats['totalDistance'], 1) : '0.0' ?></h3>
                        <p>Miles to Cover</p>
                        <?php if ($totalStats['totalDistance'] == 0 && $totalStats['totalOrders'] > 0): ?>
                            <small style="color: #e74c3c;">⚠️ Location data missing</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $totalStats['assignedOrders'] ?>/<?= $totalStats['totalOrders'] ?></h3>
                        <p>Assigned Orders</p>
                    </div>
                </div>
            </div>
            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Map -->
                <div class="map-container">
                    <h3>Delivery Map</h3>
                    <div id="map"></div>
                </div>

                <!-- Customer List -->
                <div class="customer-list-container">
                    <div class="list-header">
                        <h3>Delivery List</h3>
                        <button class="btn btn-success" onclick="optimizeDeliveryOrder()">
                            <i class="fas fa-magic"></i>
                            Optimize Order
                        </button>
                    </div>
                    
                    <div class="customer-list" id="customerList">
                        <?php if (empty($orders)): ?>
                            <div class="customer-item" style="text-align: center; background: #f8f9fa; border: 2px dashed #dee2e6;">
                                <div style="padding: 2rem;">
                                    <i class="fas fa-calendar-times" style="font-size: 3rem; color: #6c757d; margin-bottom: 1rem;"></i>
                                    <h4 style="color: #6c757d;">No Orders Found</h4>
                                    <p style="color: #6c757d; margin: 0;">
                                        No orders scheduled for <?= date('l, F j, Y', strtotime($deliveryDate)) ?>.<br>
                                        Try generating orders from subscriptions or check a different date.
                                    </p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($orders as $index => $order): ?>
                                <div class="customer-item" data-subscription-id="<?= $order['id'] ?>">
                                    <div class="customer-number">
                                        <?= $index + 1 ?>
                                    </div>
                                    <div class="customer-info">
                                        <h4><?= safeHtmlSpecialChars($order['first_name'] . ' ' . $order['last_name']) ?></h4>
                                        <p><i class="fas fa-map-marker-alt"></i> <?= safeHtmlSpecialChars($order['delivery_address']) ?></p>
                                        <p><i class="fas fa-phone"></i> <?= safeHtmlSpecialChars($order['phone']) ?></p>
                                        <p><i class="fas fa-box"></i> <?= $order['total_items'] ?> meals</p>
                                        <?php if ($order['distance'] > 0): ?>
                                            <p><i class="fas fa-route"></i> <?= $order['distance'] ?> miles
                                            <?php if (isset($order['zone'])): ?>
                                                - <span style="color: #cf723a; font-weight: 600;">Zone <?= $order['zone'] ?></span>
                                            <?php endif; ?>
                                            </p>
                                        <?php else: ?>
                                            <p><i class="fas fa-question-circle"></i> <span style="color: #6c757d;">Location data unavailable</span></p>
                                        <?php endif; ?>
                                        <?php if (!empty($order['delivery_time_slot'])): ?>
                                            <p><i class="fas fa-clock"></i> <?= safeHtmlSpecialChars($order['delivery_time_slot']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="customer-actions">
                                        <?php if ($order['rider_first_name']): ?>
                                            <div class="assigned-rider">
                                                <div class="rider-name">
                                                    <i class="fas fa-user-check"></i>
                                                    <?= safeHtmlSpecialChars($order['rider_first_name'] . ' ' . $order['rider_last_name']) ?>
                                                </div>
                                                <button class="remove-rider-btn" onclick="removeRider('<?= $order['id'] ?>')" title="Remove rider assignment">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <select class="rider-select" onchange="assignRider(this, '<?= $order['id'] ?>')">
                                                <option value="">Assign Rider</option>
                                                <?php foreach ($riders as $rider): ?>
                                                    <option value="<?= $rider['id'] ?>">
                                                        <?= safeHtmlSpecialChars($rider['first_name'] . ' ' . $rider['last_name']) ?> 
                                                        (<?= $rider['current_orders'] ?> orders)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <!-- Rider Routes Section -->
            <?php if (!empty($riderRoutes)): ?>
                <div class="rider-routes-section">
                    <h3><i class="fas fa-route"></i> Rider Route Summary</h3>
                    
                    <?php foreach ($riderRoutes as $riderId => $routeData): ?>
                        <div class="rider-route-card">
                            <div class="rider-route-header">
                                <div class="rider-info">
                                    <h4><?= safeHtmlSpecialChars($routeData['rider']['first_name'] . ' ' . $routeData['rider']['last_name']) ?></h4>
                                    <p><i class="fas fa-phone"></i> <?= safeHtmlSpecialChars($routeData['rider']['phone'] ?? 'No phone') ?></p>
                                </div>
                                <div class="route-stats">
                                    <div class="route-stat">
                                        <div class="stat-value"><?= $routeData['route_info']['stops'] ?></div>
                                        <div class="stat-label">Stops</div>
                                    </div>
                                    <div class="route-stat">
                                        <div class="stat-value"><?= $routeData['route_info']['totalDistance'] ?></div>
                                        <div class="stat-label">Miles</div>
                                    </div>
                                    <div class="route-stat">
                                        <div class="stat-value"><?= $routeData['route_info']['estimatedTime'] ?></div>
                                        <div class="stat-label">Minutes</div>
                                    </div>
                                    <div class="route-stat">
                                        <div class="stat-value"><?= array_sum(array_column($routeData['orders'], 'total_items')) ?></div>
                                        <div class="stat-label">Meals</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="route-details">
                                <h5><i class="fas fa-map-marked-alt"></i> Route Details:</h5>
                                <?php foreach ($routeData['route_info']['route'] as $index => $stop): ?>
                                    <div class="route-stop">
                                        <div class="stop-number"><?= $index + 1 ?></div>
                                        <div class="stop-info">
                                            <strong><?= safeHtmlSpecialChars($stop['name']) ?></strong>
                                            <br>
                                            <small><?= safeHtmlSpecialChars($stop['address']) ?></small>
                                            <?php if (isset($stop['total_items'])): ?>
                                                <br>
                                                <small><i class="fas fa-box"></i> <?= $stop['total_items'] ?> meals</small>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($stop['distance_from_previous'] > 0): ?>
                                            <div class="stop-distance">
                                                <i class="fas fa-arrow-right"></i> <?= $stop['distance_from_previous'] ?> mi
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="rider-routes-section">
                    <div class="no-riders-assigned">
                        <i class="fas fa-user-times" style="font-size: 3rem; margin-bottom: 1rem; color: #6c757d;"></i>
                        <h4>No Riders Assigned Yet</h4>
                        <p>Assign riders to customers to see their route summaries here.</p>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <p>Processing...</p>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Pass PHP data to JavaScript
        window.deliveryData = {
            date: '<?= $deliveryDate ?>',
            orders: <?= json_encode($orders) ?>,
            riders: <?= json_encode($riders) ?>,
            shopLocation: <?= json_encode($shopLocation) ?>,
            riderRoutes: <?= json_encode($riderRoutes) ?>
        };
        
        // Add function to remove rider
        function removeRider(subscriptionId) {
            Swal.fire({
                title: 'Remove Rider Assignment?',
                text: 'This will unassign the rider from this customer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Remove',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    
                    fetch('delivery-management.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            'action': 'remove_rider_from_customer',
                            'subscription_id': subscriptionId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        
                        if (data.success) {
                            // Update the UI to show assignment dropdown again
                            const customerItem = document.querySelector(`[data-subscription-id="${subscriptionId}"]`);
                            const actionsDiv = customerItem.querySelector('.customer-actions');
                            
                            const riderOptions = window.deliveryData.riders.map(rider => 
                                `<option value="${rider.id}">${rider.first_name} ${rider.last_name} (${rider.current_orders} orders)</option>`
                            ).join('');
                            
                            actionsDiv.innerHTML = `
                                <select class="rider-select" onchange="assignRider(this, '${subscriptionId}')">
                                    <option value="">Assign Rider</option>
                                    ${riderOptions}
                                </select>
                            `;
                            
                            // Show success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Rider Removed',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                // Refresh the page to update rider routes
                                location.reload();
                            });
                            
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Removal Failed',
                                text: data.message
                            });
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to remove rider assignment'
                        });
                    });
                }
            });
        }
        
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
    </script>
    <script src="js/delivery-management.js"></script>
</body>
</html>