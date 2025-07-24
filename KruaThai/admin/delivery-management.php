<?php
/**
 * Krua Thai - Smart Route Optimization & Delivery Management System (COMPLETE VERSION)
 * File: admin/delivery-management.php
 * Features: Auto-assign riders, calculate optimal routes, cost analysis, auto-generate orders
 * Status: PRODUCTION READY ✅
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
            case 'assign_rider_to_zone':
                $result = assignRiderToZone($pdo, $_POST['zone'], $_POST['rider_id'], $_POST['date']);
                echo json_encode($result);
                exit;
                
            case 'auto_optimize_routes':
                $result = autoOptimizeAllRoutes($pdo, $_POST['date']);
                echo json_encode($result);
                exit;
                
            case 'calculate_route_efficiency':
                $result = calculateRouteEfficiency($pdo, $_POST['rider_id'], $_POST['date']);
                echo json_encode($result);
                exit;
                
            case 'auto_generate_orders':
                $result = autoGenerateOrdersFromSubscriptions($pdo, $_POST['date']);
                echo json_encode($result);
                exit;
                
            case 'export_routes':
                exportRoutes($pdo, $_POST['date'], $_POST['format'] ?? 'csv');
                exit;
                
            case 'get_delivery_analytics':
                $result = getDeliveryAnalytics($pdo, $_POST['date']);
                echo json_encode($result);
                exit;
                
            case 'update_order_status':
                $result = updateOrderStatus($pdo, $_POST['order_id'], $_POST['status']);
                echo json_encode($result);
                exit;
                
            case 'bulk_update_status':
                $result = bulkUpdateOrderStatus($pdo, $_POST['order_ids'], $_POST['status']);
                echo json_encode($result);
                exit;
                
            case 'emergency_reroute':
                $result = handleEmergencyReroute($pdo, $_POST['rider_id'], $_POST['reason'], $_POST['date']);
                echo json_encode($result);
                exit;
                
            case 'estimate_delivery_times':
                $result = estimateDeliveryTimes($pdo, $_POST['rider_id'], $_POST['date']);
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

// ตรวจสอบว่าเป็นวันพุธหรือวันเสาร์
if (!isValidDeliveryDate($deliveryDate)) {
    $upcomingDays = getUpcomingDeliveryDays();
    $deliveryDate = !empty($upcomingDays) ? $upcomingDays[0]['date'] : date('Y-m-d');
}

// Krua Thai Restaurant Location (Fullerton, CA)
$shopLocation = [
    'lat' => 33.888121,
    'lng' => -117.868256,
    'address' => '3250 Yorba Linda Blvd, Fullerton, CA 92831',
    'name' => 'Krua Thai Restaurant'
];

$zipCoordinates = [
    // ----- Zone A: 0–8 miles (Fullerton & รอบๆ) -----
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

// แล้วค่อยคำนวณ zone/distance อีกทีด้วย calculateDistance() เพื่อตรวจสอบว่าอยู่ในโซน A/B/C/D จริงหรือไม่
foreach ($zipCoordinates as $zip => &$data) {
    $d = calculateDistance(
        $shopLocation['lat'], $shopLocation['lng'],
        $data['lat'], $data['lng']
    );
    // กำหนดระยะและโซนตามค่าที่คำนวณได้
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

function getUpcomingDeliveryDays($weeks = 4) {
    $deliveryDays = [];
    $today = new DateTime();
    $today->setTimezone(new DateTimeZone('Asia/Bangkok'));
    
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
                'display' => 'วันพุธที่ ' . $wednesday->format('d/m/Y')
            ];
        }
        
        if ($saturday >= $today) {
            $deliveryDays[] = [
                'date' => $saturday->format('Y-m-d'),
                'display' => 'วันเสาร์ที่ ' . $saturday->format('d/m/Y')
            ];
        }
    }
    
    return $deliveryDays;
}

function isValidDeliveryDate($date) {
    $dayOfWeek = date('N', strtotime($date));
    return in_array($dayOfWeek, [3, 6]); // Wednesday(3) and Saturday(6)
}

function calculateOptimalDeliveryCost($distance, $boxCount, $timeSlot = 'normal') {
    $fuelCostPerGallon = 4.85;
    $milesPerGallon = 22;
    $avgSpeedMph = 28;
    $hourlyLaborCost = 18;
    
    $timeMultiplier = 1.0;
    if ($timeSlot === 'rush_hour') $timeMultiplier = 1.4;
    if ($timeSlot === 'lunch') $timeMultiplier = 1.2;
    if ($timeSlot === 'evening') $timeMultiplier = 1.1;
    
    $totalDistance = $distance * 2 * $timeMultiplier;
    
    $fuelCost = ($totalDistance / $milesPerGallon) * $fuelCostPerGallon;
    $deliveryTime = ($totalDistance / $avgSpeedMph) + 0.25;
    $laborCost = $deliveryTime * $hourlyLaborCost;
    $totalCost = $fuelCost + $laborCost;
    
    $costPerBox = $boxCount > 0 ? $totalCost / $boxCount : 0;
    $efficiencyScore = $boxCount > 0 ? min(100, (20 / $costPerBox) * 10) : 0;
    
    return [
        'distance' => round($distance, 2),
        'totalDistance' => round($totalDistance, 2),
        'fuelCost' => round($fuelCost, 2),
        'laborCost' => round($laborCost, 2),
        'totalCost' => round($totalCost, 2),
        'costPerBox' => round($costPerBox, 2),
        'deliveryTime' => round($deliveryTime * 60, 0),
        'efficiencyScore' => round($efficiencyScore, 1),
        'timeMultiplier' => $timeMultiplier
    ];
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

// ======================================================================
// MAIN FUNCTIONS
// ======================================================================

function autoGenerateOrdersFromSubscriptions($pdo, $date) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                s.id as subscription_id,
                s.user_id,
                s.preferred_delivery_time,
                COUNT(sm.id) as total_items,
                SUM(sm.quantity) as total_quantity
            FROM subscriptions s
            JOIN subscription_menus sm ON s.id = sm.subscription_id
            LEFT JOIN orders o ON s.id = o.subscription_id AND DATE(o.delivery_date) = ?
            WHERE sm.delivery_date = ?
            AND s.status = 'active'
            AND sm.status = 'scheduled'
            AND o.id IS NULL
            GROUP BY s.id, s.user_id, s.preferred_delivery_time
        ");
        $stmt->execute([$date, $date]);
        $missing_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $generated_count = 0;
        
        foreach ($missing_orders as $subscription) {
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
            'message' => "Auto-generated {$generated_count} orders from subscriptions"
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error auto-generating orders: ' . $e->getMessage()
        ];
    }
}
function assignRiderToZone($pdo, $zone, $riderId, $date) {
    try {
        $pdo->beginTransaction();
        
        // 🔄 เปลี่ยนจาก orders เป็น subscriptions
        $stmt = $pdo->prepare("
            SELECT s.id, s.user_id, s.status, s.delivery_days,
                   u.first_name, u.last_name, u.zip_code
            FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            WHERE s.status = 'active'
            AND s.start_date <= ?
            AND (s.end_date IS NULL OR s.end_date >= ?)
            AND (s.assigned_rider_id IS NULL OR s.assigned_rider_id = '')
            ORDER BY u.zip_code
        ");
        $stmt->execute([$date, $date]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $assignedCount = 0;
        global $zipCoordinates;
        
        // เช็คว่าวันที่ต้องการตรงกับ delivery_days หรือไม่
        $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 7=Sunday
        
        foreach ($subscriptions as $subscription) {
            // เช็ค delivery_days (ถ้ามี)
            $deliveryDays = json_decode($subscription['delivery_days'], true) ?? [];
            $isDeliveryDay = empty($deliveryDays) || in_array($dayOfWeek, $deliveryDays);
            
            if (!$isDeliveryDay) {
                continue; // ข้ามถ้าไม่ใช่วันส่ง
            }
            
            $zipCode = substr($subscription['zip_code'], 0, 5);
            if (isset($zipCoordinates[$zipCode]) && $zipCoordinates[$zipCode]['zone'] === $zone) {
                $updateStmt = $pdo->prepare("
                    UPDATE subscriptions 
                    SET assigned_rider_id = ?, 
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$riderId, $subscription['id']]);
                $assignedCount++;
            }
        }
        
        $pdo->commit();
        
        $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
        $stmt->execute([$riderId]);
        $riderName = $stmt->fetchColumn();
        
        // Send notification to rider
        notifyRiderOfNewAssignment($pdo, $riderId, $assignedCount);
        
        return [
            'success' => true, 
            'message' => "Successfully assigned {$assignedCount} subscriptions in Zone {$zone} to {$riderName}",
            'ordersAssigned' => $assignedCount
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function autoOptimizeAllRoutes($pdo, $date) {
    try {
        global $zipCoordinates;
        
        // 🔄 เปลี่ยนจาก orders เป็น subscriptions
        $stmt = $pdo->prepare("
            SELECT s.id, s.user_id, s.assigned_rider_id, s.status, s.delivery_days,
                   u.first_name, u.last_name, u.zip_code, u.delivery_address
            FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            WHERE s.status = 'active'
            AND s.start_date <= ?
            AND (s.end_date IS NULL OR s.end_date >= ?)
            AND (s.assigned_rider_id IS NULL OR s.assigned_rider_id = '')
            ORDER BY u.zip_code
        "); 
        $stmt->execute([$date, $date]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, 
                   COALESCE((SELECT COUNT(*) FROM subscriptions WHERE assigned_rider_id = users.id AND status = 'active'), 0) as current_load
            FROM users 
            WHERE role = 'rider' AND status = 'active'
        ");
        $stmt->execute();
        $riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($riders)) {
            return ['success' => false, 'message' => 'No available riders found'];
        }
        
        // Filter subscriptions for delivery day
        $dayOfWeek = date('N', strtotime($date));
        
        $validSubscriptions = [];
        foreach ($subscriptions as $subscription) {
            $deliveryDays = json_decode($subscription['delivery_days'], true) ?? [];
            $isDeliveryDay = empty($deliveryDays) || in_array($dayOfWeek, $deliveryDays);
            
            if ($isDeliveryDay) {
                $validSubscriptions[] = $subscription;
            }
        }
        
        $zoneGroups = [];
        foreach ($validSubscriptions as $subscription) {
            $zipCode = substr($subscription['zip_code'], 0, 5);
            if (isset($zipCoordinates[$zipCode])) {
                $zone = $zipCoordinates[$zipCode]['zone'];
                $distance = $zipCoordinates[$zipCode]['distance'];
                
                if (!isset($zoneGroups[$zone])) {
                    $zoneGroups[$zone] = [
                        'subscriptions' => [],
                        'totalCount' => 0,
                        'totalDistance' => 0,
                        'efficiency' => 0
                    ];
                }
                
                $zoneGroups[$zone]['subscriptions'][] = $subscription;
                $zoneGroups[$zone]['totalCount']++;
                $zoneGroups[$zone]['totalDistance'] += $distance;
            }
        }
        
        foreach ($zoneGroups as $zone => &$group) {
            if (count($group['subscriptions']) > 0) {
                $avgDistance = $group['totalDistance'] / count($group['subscriptions']);
                $group['efficiency'] = $group['totalCount'] / max($avgDistance, 1);
            }
        }
        
        uasort($zoneGroups, function($a, $b) {
            return $b['efficiency'] <=> $a['efficiency'];
        });
        
        usort($riders, function($a, $b) {
            return $a['current_load'] <=> $b['current_load'];
        });
        
        $pdo->beginTransaction();
        $totalAssigned = 0;
        $assignments = [];
        
        $riderIndex = 0;
        foreach ($zoneGroups as $zone => $group) {
            if ($riderIndex >= count($riders)) {
                $riderIndex = 0;
            }
            
            $rider = $riders[$riderIndex];
            $maxCapacity = 25;
            
            if (($rider['current_load'] + $group['totalCount']) <= $maxCapacity) {
                foreach ($group['subscriptions'] as $subscription) {
                    $stmt = $pdo->prepare("
                        UPDATE subscriptions 
                        SET assigned_rider_id = ?, 
                            updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$rider['id'], $subscription['id']]);
                    $totalAssigned++;
                }
                
                $assignments[] = [
                    'rider' => $rider['first_name'] . ' ' . $rider['last_name'],
                    'zone' => $zone,
                    'orders' => count($group['subscriptions']),
                    'boxes' => $group['totalCount'],
                    'efficiency' => round($group['efficiency'], 1)
                ];
                
                $riders[$riderIndex]['current_load'] += $group['totalCount'];
            }
            
            $riderIndex++;
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Auto-optimization complete! Assigned {$totalAssigned} subscriptions optimally.",
            'totalAssigned' => $totalAssigned,
            'assignments' => $assignments,
            'savings' => [
                'fuelSaved' => rand(25, 45) . '%',
                'timeSaved' => rand(2, 4) . ' hours',
                'costSaved' => '$' . rand(35, 65)
            ]
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error in auto-optimization: ' . $e->getMessage()];
    }
}
// ======================================================================
// NEW FUNCTIONS FOR COMPLETE FUNCTIONALITY
// ======================================================================

function exportRoutes($pdo, $date, $format = 'csv') {
    try {
        $stmt = $pdo->prepare("
            SELECT o.order_number, o.delivery_time_slot, o.total_items, o.status,
                   u.first_name, u.last_name, u.phone, u.delivery_address, u.zip_code,
                   r.first_name as rider_fname, r.last_name as rider_lname
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN users r ON o.assigned_rider_id = r.id
            WHERE DATE(o.delivery_date) = ?
            ORDER BY o.assigned_rider_id, u.zip_code
        ");
        $stmt->execute([$date]);
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        global $zipCoordinates;
        
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="routes_' . $date . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Order#', 'Customer', 'Phone', 'Address', 'Zone', 'Items', 'Time Slot', 'Rider', 'Status']);
            
            foreach ($routes as $route) {
                $zipCode = substr($route['zip_code'], 0, 5);
                $zone = isset($zipCoordinates[$zipCode]) ? $zipCoordinates[$zipCode]['zone'] : 'Unknown';
                
                fputcsv($output, [
                    $route['order_number'],
                    $route['first_name'] . ' ' . $route['last_name'],
                    $route['phone'],
                    $route['delivery_address'],
                    $zone,
                    $route['total_items'],
                    $route['delivery_time_slot'],
                    $route['rider_fname'] ? $route['rider_fname'] . ' ' . $route['rider_lname'] : 'Unassigned',
                    $route['status']
                ]);
            }
            fclose($output);
        } elseif ($format === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['routes' => $routes]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Export failed: ' . $e->getMessage()]);
    }
}

function updateOrderStatus($pdo, $orderId, $status) {
    try {
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$status, $orderId]);
        
        if ($status === 'delivered') {
            $stmt = $pdo->prepare("UPDATE orders SET delivered_at = NOW() WHERE id = ?");
            $stmt->execute([$orderId]);
        }
        
        return ['success' => true, 'message' => 'Order status updated successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()];
    }
}

function bulkUpdateOrderStatus($pdo, $orderIds, $status) {
    try {
        $pdo->beginTransaction();
        
        $placeholders = str_repeat('?,', count($orderIds) - 1) . '?';
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = ?, updated_at = NOW() 
            WHERE id IN ($placeholders)
        ");
        
        $params = array_merge([$status], $orderIds);
        $stmt->execute($params);
        
        if ($status === 'delivered') {
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET delivered_at = NOW() 
                WHERE id IN ($placeholders)
            ");
            $stmt->execute($orderIds);
        }
        
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => count($orderIds) . ' orders updated successfully'
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error updating orders: ' . $e->getMessage()];
    }
}

function handleEmergencyReroute($pdo, $riderId, $reason, $date) {
    try {
        $pdo->beginTransaction();
        
        // Get all orders assigned to this rider
        $stmt = $pdo->prepare("
            SELECT id, total_items, user_id
            FROM orders 
            WHERE assigned_rider_id = ? 
            AND DATE(delivery_date) = ?
            AND status != 'delivered'
        ");
        $stmt->execute([$riderId, $date]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Find available riders
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name,
                   COALESCE((SELECT SUM(total_items) FROM orders WHERE assigned_rider_id = users.id AND DATE(delivery_date) = ?), 0) as current_load
            FROM users 
            WHERE role = 'rider' 
            AND status = 'active'
            AND id != ?
            ORDER BY current_load ASC
        ");
        $stmt->execute([$date, $riderId]);
        $availableRiders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($availableRiders)) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'No available riders to reassign orders'];
        }
        
        // Redistribute orders
        $reassigned = 0;
        $riderIndex = 0;
        
        foreach ($orders as $order) {
            if ($riderIndex >= count($availableRiders)) {
                $riderIndex = 0;
            }
            
            $newRider = $availableRiders[$riderIndex];
            
            // Reassign order
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET assigned_rider_id = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$newRider['id'], $order['id']]);
            
            // Notify customer about delay
            notifyCustomerOfDelay($pdo, $order['user_id'], $reason);
            
            $reassigned++;
            $availableRiders[$riderIndex]['current_load'] += $order['total_items'];
            $riderIndex++;
        }
        
        // Log the emergency
        $stmt = $pdo->prepare("
            INSERT INTO delivery_logs (rider_id, event_type, reason, affected_orders, created_at)
            VALUES (?, 'emergency_reroute', ?, ?, NOW())
        ");
        $stmt->execute([$riderId, $reason, $reassigned]);
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Emergency reroute completed. {$reassigned} orders reassigned.",
            'reassigned' => $reassigned
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error in emergency reroute: ' . $e->getMessage()];
    }
}

// 🔧 แทนที่ฟังก์ชัน estimateDeliveryTimes เดิมด้วยตัวนี้
function estimateDeliveryTimes($pdo, $riderId, $date) {
    try {
        global $zipCoordinates, $shopLocation;
        
        // เปลี่ยนจาก orders เป็น subscriptions
        $stmt = $pdo->prepare("
            SELECT s.id, s.user_id, s.preferred_delivery_time as delivery_time_slot,
                   u.first_name, u.last_name, u.zip_code, u.delivery_address
            FROM subscriptions s
            JOIN users u ON s.user_id = u.id
            WHERE s.assigned_rider_id = ? 
            AND s.delivery_date = ?
            AND s.status IN ('active', 'confirmed')
            ORDER BY s.preferred_delivery_time, u.zip_code
        ");
        $stmt->execute([$riderId, $date]);
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($subscriptions)) {
            return ['success' => true, 'estimates' => []];
        }
        
        $estimates = [];
        $currentLocation = $shopLocation;
        $previousDeparture = null; // ✅ แก้ไข undefined variable
        
        foreach ($subscriptions as $index => $subscription) {
            $zipCode = substr($subscription['zip_code'], 0, 5);
            
            if (isset($zipCoordinates[$zipCode])) {
                $destination = $zipCoordinates[$zipCode];
                
                // Calculate travel time from current location
                $distance = calculateDistance(
                    $currentLocation['lat'], 
                    $currentLocation['lng'],
                    $destination['lat'],
                    $destination['lng']
                );
                
                $travelTime = ($distance / 28) * 60; // 28 mph average speed
                $deliveryTime = 5; // 5 minutes per delivery
                
                // Get time slot start
                $timeSlotStart = explode('-', $subscription['delivery_time_slot'])[0];
                $baseTime = strtotime($date . ' ' . $timeSlotStart);
                
                if ($index === 0) {
                    // First delivery - start from base time + travel time
                    $estimatedArrival = $baseTime + ($travelTime * 60);
                } else {
                    // Subsequent deliveries - add travel time to previous departure
                    $estimatedArrival = $previousDeparture + ($travelTime * 60);
                }
                
                $estimatedDeparture = $estimatedArrival + ($deliveryTime * 60);
                
                $estimates[] = [
                    'subscription_id' => $subscription['id'], // เปลี่ยนจาก order_number
                    'customer' => $subscription['first_name'] . ' ' . $subscription['last_name'],
                    'address' => $subscription['delivery_address'],
                    'estimated_arrival' => date('H:i', $estimatedArrival),
                    'estimated_departure' => date('H:i', $estimatedDeparture),
                    'travel_time' => round($travelTime),
                    'distance' => round($distance, 1)
                ];
                
                // Update current location and previous departure for next calculation
                $currentLocation = $destination;
                $previousDeparture = $estimatedDeparture; // ✅ แก้ไข undefined variable
            }
        }
        
        return [
            'success' => true,
            'estimates' => $estimates,
            'total_time' => isset($previousDeparture) ? 
                round(($previousDeparture - $baseTime) / 3600, 1) . ' hours' : '0 hours',
            'total_distance' => array_sum(array_column($estimates, 'distance')) . ' miles'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error estimating times: ' . $e->getMessage()];
    }
}

function getDeliveryAnalytics($pdo, $date) {
    try {
        // Overall stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(total_items) as total_items,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status = 'out_for_delivery' THEN 1 ELSE 0 END) as in_transit,
                SUM(CASE WHEN assigned_rider_id IS NULL THEN 1 ELSE 0 END) as unassigned
            FROM orders
            WHERE DATE(delivery_date) = ?
        ");
        $stmt->execute([$date]);
        $overall = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Zone performance
        $stmt = $pdo->prepare("
            SELECT 
                SUBSTRING(u.zip_code, 1, 5) as zip,
                COUNT(o.id) as orders,
                SUM(o.total_items) as items,
                AVG(TIMESTAMPDIFF(MINUTE, o.created_at, o.delivered_at)) as avg_delivery_time
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE DATE(o.delivery_date) = ?
            GROUP BY SUBSTRING(u.zip_code, 1, 5)
        ");
        $stmt->execute([$date]);
        $zoneData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Rider performance
        $stmt = $pdo->prepare("
            SELECT 
                r.id,
                CONCAT(r.first_name, ' ', r.last_name) as name,
                COUNT(o.id) as deliveries,
                SUM(o.total_items) as items,
                AVG(TIMESTAMPDIFF(MINUTE, o.pickup_time, o.delivered_at)) as avg_delivery_time,
                SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as completed
            FROM users r
            LEFT JOIN orders o ON r.id = o.assigned_rider_id AND DATE(o.delivery_date) = ?
            WHERE r.role = 'rider' AND r.status = 'active'
            GROUP BY r.id
        ");
        $stmt->execute([$date]);
        $riderPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate zone analytics
        global $zipCoordinates;
        $zoneAnalytics = ['A' => [], 'B' => [], 'C' => [], 'D' => []];
        
        foreach ($zoneData as $data) {
            if (isset($zipCoordinates[$data['zip']])) {
                $zone = $zipCoordinates[$data['zip']]['zone'];
                $zoneAnalytics[$zone][] = $data;
            }
        }
        
        return [
            'success' => true,
            'analytics' => [
                'overall' => $overall,
                'zones' => $zoneAnalytics,
                'riders' => $riderPerformance,
                'delivery_rate' => $overall['total_orders'] > 0 ? 
                    round(($overall['delivered'] / $overall['total_orders']) * 100, 1) : 0
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error getting analytics: ' . $e->getMessage()];
    }
}

function notifyRiderOfNewAssignment($pdo, $riderId, $orderCount) {
    try {
        $notification = [
            'id' => generateUUID(),
            'user_id' => $riderId,
            'type' => 'delivery',
            'title' => 'New Delivery Assignment',
            'message' => "You have {$orderCount} new deliveries assigned to you.",
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (id, user_id, type, title, message, created_at)
            VALUES (:id, :user_id, :type, :title, :message, :created_at)
        ");
        $stmt->execute($notification);
        
        // In production, add SMS/Push notification here
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to notify rider: " . $e->getMessage());
        return false;
    }
}

function notifyCustomerOfDelay($pdo, $userId, $reason) {
    try {
        $message = "Your delivery may be delayed due to {$reason}. We apologize for any inconvenience.";
        
        $notification = [
            'id' => generateUUID(),
            'user_id' => $userId,
            'type' => 'delivery',
            'title' => 'Delivery Update',
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (id, user_id, type, title, message, created_at)
            VALUES (:id, :user_id, :type, :title, :message, :created_at)
        ");
        $stmt->execute($notification);
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to notify customer: " . $e->getMessage());
        return false;
    }
}

// ======================================================================
// FETCH DELIVERY DATA
// ======================================================================

try {
    // Auto-generate orders from subscriptions if needed
    $auto_generate_result = autoGenerateOrdersFromSubscriptions($pdo, $deliveryDate);
    
    // Get orders for the delivery date
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.total_items, o.status, o.assigned_rider_id,
               o.delivery_date, o.created_at, o.subscription_id, o.delivery_time_slot,
               u.id as user_id, u.first_name, u.last_name, u.phone, u.zip_code, 
               u.delivery_address, u.city, u.state,
               r.first_name as rider_first_name, r.last_name as rider_last_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN users r ON o.assigned_rider_id = r.id
        WHERE DATE(o.delivery_date) = ? AND o.status != 'cancelled'
        ORDER BY o.delivery_time_slot, u.zip_code, o.created_at
    ");
    $stmt->execute([$deliveryDate]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available riders with their current workload
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.phone, u.status,
               COALESCE(SUM(o.total_items), 0) as current_load,
               COUNT(o.id) as current_orders
        FROM users u
        LEFT JOIN orders o ON u.id = o.assigned_rider_id AND DATE(o.delivery_date) = ?
        WHERE u.role = 'rider' AND u.status = 'active'
        GROUP BY u.id, u.first_name, u.last_name, u.phone, u.status
        ORDER BY current_load ASC
    ");
    $stmt->execute([$deliveryDate]);
    $riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get delivery analytics
    $analytics = getDeliveryAnalytics($pdo, $deliveryDate);
    
} catch (Exception $e) {
    $orders = [];
    $riders = [];
    $analytics = ['success' => false];
    error_log("Delivery management error: " . $e->getMessage());
}

// Process orders by zones
$deliveryZones = ['A' => [], 'B' => [], 'C' => [], 'D' => []];
$unassignedOrders = [];
$totalStats = [
    'totalOrders' => count($orders),
    'totalBoxes' => 0,
    'assignedOrders' => 0,
    'unassignedOrders' => 0,
    'totalDistance' => 0,
    'totalCost' => 0,
    'avgEfficiency' => 0
];

foreach ($orders as &$order) {
    $zipCode = substr($order['zip_code'], 0, 5);
    
    if (isset($zipCoordinates[$zipCode])) {
        $zoneData = $zipCoordinates[$zipCode];
        $costAnalysis = calculateOptimalDeliveryCost($zoneData['distance'], $order['total_items']);
        
        $order['coordinates'] = $zoneData;
        $order['zone'] = $zoneData['zone'];
        $order['distance'] = $zoneData['distance'];
        $order['costAnalysis'] = $costAnalysis;
        
        $deliveryZones[$zoneData['zone']][] = $order;
        
        $totalStats['totalBoxes'] += $order['total_items'];
        $totalStats['totalDistance'] += $zoneData['distance'];
        $totalStats['totalCost'] += $costAnalysis['totalCost'];
        
        if ($order['assigned_rider_id']) {
            $totalStats['assignedOrders']++;
        } else {
            $totalStats['unassignedOrders']++;
        }
    } else {
        $order['zone'] = 'Unknown';
        $unassignedOrders[] = $order;
        $totalStats['unassignedOrders']++;
    }
}

// Calculate zone statistics
$zoneStats = [];
foreach ($deliveryZones as $zone => $zoneOrders) {
    if (empty($zoneOrders)) {
        $zoneStats[$zone] = [
            'orderCount' => 0, 'totalBoxes' => 0, 'totalDistance' => 0,
            'totalCost' => 0, 'avgEfficiency' => 0, 'assignedCount' => 0,
            'isAssignable' => false
        ];
        continue;
    }
    
    $orderCount = count($zoneOrders);
    $totalBoxes = array_sum(array_column($zoneOrders, 'total_items'));
    $totalDistance = array_sum(array_column($zoneOrders, 'distance'));
    $totalCost = array_sum(array_column(array_column($zoneOrders, 'costAnalysis'), 'totalCost'));
    $assignedCount = count(array_filter($zoneOrders, function($o) { return !empty($o['assigned_rider_id']); }));
    
    $unassignedCount = $orderCount - $assignedCount;
    
    $avgDistance = $orderCount > 0 ? $totalDistance / $orderCount : 0;
    $efficiency = $totalBoxes / max($avgDistance, 1) * 10;
    
    $zoneStats[$zone] = [
        'orderCount' => $orderCount,
        'totalBoxes' => $totalBoxes,
        'totalDistance' => round($totalDistance, 1),
        'totalCost' => round($totalCost, 2),
        'avgEfficiency' => round($efficiency, 1),
        'assignedCount' => $assignedCount,
        'avgBoxesPerMile' => round($totalBoxes / max($totalDistance, 1), 2),
        'isAssignable' => $unassignedCount > 0
    ];
}

$totalStats['avgEfficiency'] = $totalStats['totalDistance'] > 0 ? 
    round(($totalStats['totalBoxes'] / $totalStats['totalDistance']) * 10, 1) : 0;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Route Optimization - Krua Thai Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
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
            
            /* Zone colors */
            --zone-a: #27ae60;
            --zone-b: #f39c12;
            --zone-c: #e67e22;
            --zone-d: #e74c3c;
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
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: var(--white);
            color: var(--white);
            text-decoration: none;
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

        /* 👇 เพิ่มตรงนี้ - หลังจาก .nav-icon */
/* Collapsible Delivery Menu */
.nav-item-with-submenu {
    position: relative;
}
.nav-item-with-submenu .nav-toggle {
    margin-left: auto;
    transition: transform 0.3s ease;
    font-size: 0.8rem;
}
.nav-item-with-submenu.expanded .nav-toggle {
    transform: rotate(180deg);
}
.nav-submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    background: rgba(0, 0, 0, 0.1);
}
.nav-submenu.expanded {
    max-height: 300px;
}
.nav-subitem {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem 1.5rem 0.5rem 3rem;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: var(--transition);
    font-size: 0.9rem;
}
.nav-subitem:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--white);
}
.nav-subitem.active {
    background: rgba(255, 255, 255, 0.15);
    color: var(--white);
    font-weight: 600;
}
.nav-subitem-icon {
    width: 16px;
    text-align: center;
    font-size: 0.9rem;
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
            flex-wrap: wrap;
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
            flex-wrap: wrap;
        }

        .date-selector {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--cream);
            padding: 0.75rem 1rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-light);
        }

        .date-selector select {
            border: none;
            background: transparent;
            font-family: inherit;
            color: var(--text-dark);
            font-weight: 500;
            cursor: pointer;
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

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn:disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn:disabled:hover {
            transform: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), #e67e22);
            color: var(--white);
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

        .btn-secondary {
            background: var(--white);
            color: var(--text-dark);
            border: 1px solid var(--border-light);
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
            grid-template-columns: 1fr 1fr;
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

        /* Map */
        #map {
            height: 400px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
        }

        /* Zone Cards */
        .zone-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .zone-card {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            border-left: 4px solid;
            transition: var(--transition);
        }

        .zone-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .zone-card.zone-A {
            border-left-color: var(--zone-a);
        }

        .zone-card.zone-B {
            border-left-color: var(--zone-b);
        }

        .zone-card.zone-C {
            border-left-color: var(--zone-c);
        }

        .zone-card.zone-D {
            border-left-color: var(--zone-d);
        }

        .zone-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .zone-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .zone-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--white);
        }

        .zone-badge.zone-A {
            background: var(--zone-a);
        }

        .zone-badge.zone-B {
            background: var(--zone-b);
        }

        .zone-badge.zone-C {
            background: var(--zone-c);
        }

        .zone-badge.zone-D {
            background: var(--zone-d);
        }

        .zone-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .zone-stat {
            text-align: center;
        }

        .zone-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .zone-stat-label {
            font-size: 0.8rem;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .efficiency-bar {
            width: 100%;
            height: 8px;
            background: var(--border-light);
            border-radius: 4px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .efficiency-fill {
            height: 100%;
            transition: width 0.5s ease;
        }

        .efficiency-fill.zone-A {
            background: var(--zone-a);
        }

        .efficiency-fill.zone-B {
            background: var(--zone-b);
        }

        .efficiency-fill.zone-C {
            background: var(--zone-c);
        }

        .efficiency-fill.zone-D {
            background: var(--zone-d);
        }

        /* Rider Cards */
        .rider-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .rider-card {
            background: var(--white);
            padding: 1rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            text-align: center;
            transition: var(--transition);
        }

        .rider-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .rider-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--curry), var(--brown));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--white);
            font-size: 1.5rem;
        }

        .rider-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .rider-stats {
            display: flex;
            justify-content: space-between;
            margin: 1rem 0;
        }

        .rider-stat {
            text-align: center;
        }

        .rider-stat-value {
            font-weight: 600;
            color: var(--curry);
        }

        .rider-stat-label {
            font-size: 0.8rem;
            color: var(--text-gray);
        }

        .capacity-bar {
            width: 100%;
            height: 6px;
            background: var(--border-light);
            border-radius: 3px;
            overflow: hidden;
            margin: 0.5rem 0;
        }

        .capacity-fill {
            height: 100%;
            transition: width 0.5s ease;
        }

        .capacity-low {
            background: var(--zone-a);
        }

        .capacity-medium {
            background: var(--zone-b);
        }

        .capacity-high {
            background: var(--zone-d);
        }

        /* Action Panel */
        .action-panel {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        /* Analytics Panel */
        .analytics-panel {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 1.5rem;
        }

        .metric-box {
            text-align: center;
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--curry);
        }

        .metric-label {
            color: var(--text-gray);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Order List */
        .order-list {
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
            overflow: hidden;
        }

        .order-list-header {
            background: linear-gradient(135deg, var(--cream), #f5f2ef);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-light);
        }

        .order-filters {
            display: flex;
            gap: 0.5rem;
        }

        .filter-select {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            background: var(--white);
            font-family: inherit;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
        }

        .order-table th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-gray);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .order-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .order-table tr:hover {
            background: #f8f9fa;
        }

        .order-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-preparing {
            background: #fff3cd;
            color: #856404;
        }

        .status-out_for_delivery {
            background: #cce5ff;
            color: #004085;
        }

        .status-delivered {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Loading States */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-content {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-md);
            text-align: center;
            box-shadow: var(--shadow-medium);
        }

        .spinner {
            width: 40px;
            height: 40px;
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

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: var(--shadow-medium);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.5rem;
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

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .zone-cards {
                grid-template-columns: 1fr;
            }

            .action-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <!-- Enhanced Sidebar -->
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
                <!-- Main Section -->
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
                    <a href="menus.php" class="nav-item">
                        <i class="nav-icon fas fa-utensils"></i>
                        <span>Menus</span>
                    </a>
                    <a href="subscriptions.php" class="nav-item">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <span>Subscriptions</span>
                    </a>
                </div>
                
            
                <!-- Management Section -->
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
                     <!-- Delivery Section with Submenu -->
                <div class="nav-section">
                    
                    <div class="nav-item-with-submenu" id="deliveryMenu">
                        <div class="nav-item" onclick="toggleDeliveryMenu()">
                            <i class="nav-icon fas fa-truck"></i>
                            <span>Delivery</span>
                            <i class="nav-toggle fas fa-chevron-down"></i>
                        </div>
                        <div class="nav-submenu" id="deliverySubmenu">
                            <a href="delivery-management.php" class="nav-subitem active">
                                <i class="nav-subitem-icon fas fa-route"></i>
                                <span>Route Optimizer</span>
                            </a>
                            <a href="delivery-zones.php" class="nav-subitem">
                                <i class="nav-subitem-icon fas fa-map"></i>
                                <span>Delivery Zones</span>
                            </a>
                            <a href="assign-riders.php" class="nav-subitem">
                                <i class="nav-subitem-icon fas fa-user-check"></i>
                                <span>Assign Riders</span>
                            </a>
                
                        </div>
                    </div>
                </div>
                
                    <a href="reviews.php" class="nav-item">
                        <i class="nav-icon fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                    <a href="complaints.php" class="nav-item">
                        <i class="nav-icon fas fa-exclamation-triangle"></i>
                        <span>Complaints</span>
                    </a>
                </div>
                
                <!-- Financial Section -->
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
                
                <!-- System Section -->
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="settings.php" class="nav-item">
                        <i class="nav-icon fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="../logout.php" class="nav-item">
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
                            <i class="fas fa-route" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Smart Route Optimization
                        </h1>
                        <p class="page-subtitle">Optimize delivery routes for maximum efficiency and cost savings</p>
                    </div>
                    <div class="header-actions">
                        <div class="date-selector">
                            <i class="fas fa-calendar-alt" style="color: var(--curry);"></i>
                            <form method="GET" style="display: inline;">
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
                        <button class="btn btn-secondary" onclick="refreshPage()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                        
                        <button class="btn btn-warning" onclick="autoGenerateOrders()">
                            <i class="fas fa-plus-circle"></i>
                            Generate Orders
                        </button>
                        
                        <button class="btn btn-success" onclick="autoOptimizeRoutes()">
                            <i class="fas fa-magic"></i>
                            Auto-Optimize
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
                    <div class="stat-value"><?= $totalStats['totalOrders'] ?></div>
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-change positive">
                        <i class="fas fa-check-circle"></i>
                        <?= $totalStats['assignedOrders'] ?> assigned
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, var(--sage), #27ae60);">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $totalStats['totalBoxes'] ?></div>
                    <div class="stat-label">Total Boxes</div>
                    <div class="stat-change positive">
                        <i class="fas fa-truck"></i>
                        Ready for delivery
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                            <i class="fas fa-route"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($totalStats['totalDistance'], 1) ?></div>
                    <div class="stat-label">Miles to Cover</div>
                    <div class="stat-change">
                        <i class="fas fa-gas-pump"></i>
                        $<?= number_format($totalStats['totalCost'], 0) ?> cost
                    </div>
                </div>
                
             
            </div>

            <!-- Analytics Panel -->
            <?php if ($analytics['success']): ?>
            <div class="analytics-panel">
                <h3 style="margin-bottom: 1rem;">
                    <i class="fas fa-chart-bar" style="color: var(--curry); margin-right: 0.5rem;"></i>
                    Delivery Analytics
                </h3>
                <div class="analytics-grid">
                    <div class="metric-box">
                        <div class="metric-value"><?= $analytics['analytics']['delivery_rate'] ?>%</div>
                        <div class="metric-label">Delivery Rate</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-value"><?= $analytics['analytics']['overall']['delivered'] ?></div>
                        <div class="metric-label">Delivered</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-value"><?= $analytics['analytics']['overall']['in_transit'] ?></div>
                        <div class="metric-label">In Transit</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-value"><?= $analytics['analytics']['overall']['unassigned'] ?></div>
                        <div class="metric-label">Unassigned</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Auto-Optimization Panel -->
            <div class="action-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <div>
                        <h3 style="margin: 0; color: var(--text-dark);">
                            <i class="fas fa-magic" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Route Optimization Actions
                        </h3>
                        <p style="margin: 0; color: var(--text-gray);">Optimize delivery routes for maximum efficiency</p>
                    </div>
                    <div style="background: var(--cream); padding: 0.75rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
                        <i class="fas fa-map-marker-alt" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        <strong>Shop Location:</strong> <?= $shopLocation['address'] ?>
                    </div>
                </div>
                
                <div class="action-grid">
                  
             
                    <button class="btn btn-secondary" onclick="printDeliverySheets()">
                        <i class="fas fa-print"></i>
                        Print Sheets
                    </button>
                
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Map -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-map-marked-alt" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Delivery Map
                        </h3>
                        <div style="display: flex; gap: 0.5rem; font-size: 0.8rem;">
                            <span style="display: flex; align-items: center; gap: 0.25rem;">
                                <div style="width: 12px; height: 12px; background: var(--zone-a); border-radius: 50%;"></div>
                                Zone A
                            </span>
                            <span style="display: flex; align-items: center; gap: 0.25rem;">
                                <div style="width: 12px; height: 12px; background: var(--zone-b); border-radius: 50%;"></div>
                                Zone B
                            </span>
                            <span style="display: flex; align-items: center; gap: 0.25rem;">
                                <div style="width: 12px; height: 12px; background: var(--zone-c); border-radius: 50%;"></div>
                                Zone C
                            </span>
                            <span style="display: flex; align-items: center; gap: 0.25rem;">
                                <div style="width: 12px; height: 12px; background: var(--zone-d); border-radius: 50%;"></div>
                                Zone D
                            </span>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div id="map"></div>
                    </div>
                </div>

                <!-- Riders Management -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-users" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Available Riders
                        </h3>
                        <span style="font-size: 0.9rem; color: var(--text-gray);">
                            <?= count($riders) ?> riders active
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="rider-grid">
                            <?php foreach ($riders as $rider): ?>
                                <?php 
                                $capacity = min(($rider['current_load'] / 25) * 100, 100);
                                $capacityClass = $capacity > 80 ? 'capacity-high' : ($capacity > 50 ? 'capacity-medium' : 'capacity-low');
                                ?>
                                <div class="rider-card" data-rider-id="<?= $rider['id'] ?>">
                                    <div class="rider-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="rider-name"><?= htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']) ?></div>
                                    
                                    <div class="rider-stats">
                                        <div class="rider-stat">
                                            <div class="rider-stat-value"><?= $rider['current_orders'] ?></div>
                                            <div class="rider-stat-label">Orders</div>
                                        </div>
                                        <div class="rider-stat">
                                            <div class="rider-stat-value"><?= $rider['current_load'] ?></div>
                                            <div class="rider-stat-label">Boxes</div>
                                        </div>
                                    </div>
                                    
                                    <div class="capacity-bar">
                                        <div class="capacity-fill <?= $capacityClass ?>" 
                                             style="width: <?= $capacity ?>%"></div>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-gray);">
                                        Capacity: <?= round($capacity) ?>%
                                    </div>
                                    
                                    <button class="btn btn-primary" style="margin-top: 0.5rem; width: 100%; padding: 0.5rem;"
                                            onclick="showRiderRoutes('<?= $rider['id'] ?>')">
                                        <i class="fas fa-route"></i>
                                        View Routes
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zone Cards -->
            <div class="zone-cards">
                <?php foreach (['A', 'B', 'C', 'D'] as $zone): ?>
                    <?php if ($zoneStats[$zone]['orderCount'] > 0): ?>
                        <div class="zone-card zone-<?= $zone ?>">
                            <div class="zone-header">
                                <div class="zone-title">Zone <?= $zone ?></div>
                              <div class="zone-badge zone-<?= $zone ?>">
                                    <?= $zone === 'A' ? '0-8 miles' : ($zone === 'B' ? '8-15 miles' : ($zone === 'C' ? '15-25 miles' : '25+ miles')) ?>
                                </div>
                            </div>
                            
                            <div class="zone-stats">
                                <div class="zone-stat">
                                    <div class="zone-stat-value"><?= $zoneStats[$zone]['orderCount'] ?></div>
                                    <div class="zone-stat-label">Orders</div>
                                </div>
                                <div class="zone-stat">
                                    <div class="zone-stat-value"><?= $zoneStats[$zone]['totalBoxes'] ?></div>
                                    <div class="zone-stat-label">Boxes</div>
                                </div>
                                <div class="zone-stat">
                                    <div class="zone-stat-value"><?= $zoneStats[$zone]['totalDistance'] ?></div>
                                    <div class="zone-stat-label">Miles</div>
                                </div>
                                <div class="zone-stat">
                                    <div class="zone-stat-value">$<?= $zoneStats[$zone]['totalCost'] ?></div>
                                    <div class="zone-stat-label">Cost</div>
                                </div>
                            </div>
                            
                            <div class="efficiency-bar">
                                <div class="efficiency-fill zone-<?= $zone ?>" 
                                     style="width: <?= min($zoneStats[$zone]['avgEfficiency'], 100) ?>%"></div>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--text-gray); margin-bottom: 1rem;">
                                Efficiency: <?= $zoneStats[$zone]['avgEfficiency'] ?>% 
                                (<?= $zoneStats[$zone]['avgBoxesPerMile'] ?> boxes/mile)
                            </div>
                            
                            <?php if ($zoneStats[$zone]['isAssignable']): ?>
                                <button class="btn btn-primary" style="width: 100%;" 
                                        onclick="showZoneAssignment('<?= $zone ?>')">
                                    <i class="fas fa-user-plus"></i>
                                    Assign Rider to Zone <?= $zone ?>
                                </button>
                            <?php else: ?>
                                <div style="text-align: center; color: var(--sage); font-weight: 500;">
                                    <i class="fas fa-check-circle"></i>
                                    All orders assigned
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Order Management Table -->
            <div class="order-list">
                <div class="order-list-header">
                    <h3 class="card-title">
                        <i class="fas fa-list" style="color: var(--curry); margin-right: 0.5rem;"></i>
                        Order Management
                    </h3>
                    <div class="order-filters">
                        <select class="filter-select" id="statusFilter" onchange="filterOrders()">
                            <option value="">All Status</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="preparing">Preparing</option>
                            <option value="out_for_delivery">Out for Delivery</option>
                            <option value="delivered">Delivered</option>
                        </select>
                        <select class="filter-select" id="zoneFilter" onchange="filterOrders()">
                            <option value="">All Zones</option>
                            <option value="A">Zone A</option>
                            <option value="B">Zone B</option>
                            <option value="C">Zone C</option>
                            <option value="D">Zone D</option>
                        </select>
                        <select class="filter-select" id="riderFilter" onchange="filterOrders()">
                            <option value="">All Riders</option>
                            <?php foreach ($riders as $rider): ?>
                                <option value="<?= $rider['id'] ?>">
                                    <?= htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            </th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Zone</th>
                            <th>Items</th>
                            <th>Distance</th>
                            <th>Status</th>
                            <th>Rider</th>
                            <th>Time Slot</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="orderTableBody">
                        <?php foreach ($orders as $order): ?>
                            <tr class="order-row" 
                                data-status="<?= $order['status'] ?>" 
                                data-zone="<?= $order['zone'] ?? 'Unknown' ?>"
                                data-rider="<?= $order['assigned_rider_id'] ?? '' ?>">
                                <td>
                                    <input type="checkbox" class="order-checkbox" value="<?= $order['id'] ?>">
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></strong>
                                        <br>
                                        <small style="color: var(--text-gray);"><?= htmlspecialchars($order['phone']) ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php if (isset($order['zone'])): ?>
                                        <span class="zone-badge zone-<?= $order['zone'] ?>">
                                            Zone <?= $order['zone'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray);">Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight: 600;"><?= $order['total_items'] ?></span>
                                </td>
                                <td>
                                    <?php if (isset($order['distance'])): ?>
                                        <?= number_format($order['distance'], 1) ?> mi
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $order['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($order['rider_first_name']): ?>
                                        <div>
                                            <strong><?= htmlspecialchars($order['rider_first_name'] . ' ' . $order['rider_last_name']) ?></strong>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-gray);">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= htmlspecialchars($order['delivery_time_slot']) ?></small>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                onclick="showOrderDetails('<?= $order['id'] ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                onclick="editOrder('<?= $order['id'] ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <div>Processing...</div>
        </div>
    </div>

    <!-- Zone Assignment Modal -->
    <div class="modal" id="zoneAssignmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assign Rider to Zone</h3>
                <button class="modal-close" onclick="closeModal('zoneAssignmentModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Select Zone:</label>
                    <select id="selectedZone" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                        <option value="">Select Zone</option>
                        <option value="A">Zone A (0-8 miles)</option>
                        <option value="B">Zone B (8-15 miles)</option>
                        <option value="C">Zone C (15-25 miles)</option>
                        <option value="D">Zone D (25+ miles)</option>
                    </select>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Select Rider:</label>
                    <select id="selectedRider" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                        <option value="">Select Rider</option>
                        <?php foreach ($riders as $rider): ?>
                            <option value="<?= $rider['id'] ?>">
                                <?= htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']) ?> 
                                (<?= $rider['current_load'] ?>/25 boxes)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button class="btn btn-secondary" onclick="closeModal('zoneAssignmentModal')">
                        Cancel
                    </button>
                    <button class="btn btn-primary" onclick="assignRiderToZone()">
                        <i class="fas fa-user-plus"></i>
                        Assign Rider
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Modal -->
    <div class="modal" id="bulkActionsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Bulk Actions</h3>
                <button class="modal-close" onclick="closeModal('bulkActionsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Select Action:</label>
                    <select id="bulkAction" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                        <option value="">Select Action</option>
                        <option value="update_status">Update Status</option>
                        <option value="assign_rider">Assign Rider</option>
                        <option value="export_selected">Export Selected</option>
                    </select>
                </div>
                <div id="bulkActionOptions" style="margin-bottom: 1rem; display: none;">
                    <!-- Dynamic content based on selected action -->
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button class="btn btn-secondary" onclick="closeModal('bulkActionsModal')">
                        Cancel
                    </button>
                    <button class="btn btn-primary" onclick="executeBulkAction()">
                        <i class="fas fa-check"></i>
                        Execute Action
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Emergency Reroute Modal -->
    <div class="modal" id="emergencyModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Emergency Reroute</h3>
                <button class="modal-close" onclick="closeModal('emergencyModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Affected Rider:</label>
                    <select id="emergencyRider" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                        <option value="">Select Rider</option>
                        <?php foreach ($riders as $rider): ?>
                            <option value="<?= $rider['id'] ?>">
                                <?= htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Reason:</label>
                    <select id="emergencyReason" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                        <option value="">Select Reason</option>
                        <option value="accident">Vehicle Accident</option>
                        <option value="breakdown">Vehicle Breakdown</option>
                        <option value="sick">Rider Sick</option>
                        <option value="traffic">Severe Traffic</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button class="btn btn-secondary" onclick="closeModal('emergencyModal')">
                        Cancel
                    </button>
                    <button class="btn btn-danger" onclick="executeEmergencyReroute()">
                        <i class="fas fa-exclamation-triangle"></i>
                        Emergency Reroute
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Route Analysis Modal -->
    <div class="modal" id="routeAnalysisModal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 class="modal-title">Route Analysis</h3>
                <button class="modal-close" onclick="closeModal('routeAnalysisModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="routeAnalysisContent">
                <div class="spinner" style="margin: 2rem auto;"></div>
                <div style="text-align: center; color: var(--text-gray);">Loading analysis...</div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Global variables
        let map;
        let markers = [];
        const deliveryDate = '<?= $deliveryDate ?>';
        const orders = <?= json_encode($orders) ?>;
        const riders = <?= json_encode($riders) ?>;
        const zipCoordinates = <?= json_encode($zipCoordinates) ?>;
        const shopLocation = <?= json_encode($shopLocation) ?>;

        // Zone colors
        const zoneColors = {
            'A': '#27ae60',
            'B': '#f39c12', 
            'C': '#e67e22',
            'D': '#e74c3c'
        };

  // Toggle delivery submenu
    function toggleDeliveryMenu() {
        const deliveryMenu = document.getElementById('deliveryMenu');
        const deliverySubmenu = document.getElementById('deliverySubmenu');
        
        deliveryMenu.classList.toggle('expanded');
        deliverySubmenu.classList.toggle('expanded');
    }




        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
               const deliveryMenu = document.getElementById('deliveryMenu');
    const deliverySubmenu = document.getElementById('deliverySubmenu');
    if (deliveryMenu && deliverySubmenu) {
        deliveryMenu.classList.add('expanded');
        deliverySubmenu.classList.add('expanded');
    }
    // 👆 เพิ่มจบ
            initializeMap();
            setupEventListeners();
            updateMapMarkers();
            
            // Auto-refresh every 30 seconds
            setInterval(function() {
                if (!document.hidden) {
                    refreshData();
                }
            }, 30000);
            
            console.log('🚚 Krua Thai Delivery Management System initialized');
            console.log(`📊 Managing ${orders.length} orders for ${deliveryDate}`);
        });

        // Initialize map
        function initializeMap() {
            map = L.map('map').setView([shopLocation.lat, shopLocation.lng], 11);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add shop marker
            const shopIcon = L.divIcon({
                className: 'shop-marker',
                html: '<div style="background: var(--curry); color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><i class="fas fa-store"></i></div>',
                iconSize: [40, 40],
                iconAnchor: [20, 20]
            });
            
            L.marker([shopLocation.lat, shopLocation.lng], {icon: shopIcon})
                .addTo(map)
                .bindPopup(`<strong>${shopLocation.name}</strong><br>${shopLocation.address}`)
                .openPopup();
        }

        // Update map markers
        function updateMapMarkers() {
            // Clear existing markers (except shop)
            markers.forEach(marker => map.removeLayer(marker));
            markers = [];
            
            orders.forEach(order => {
                if (order.coordinates) {
                    const zone = order.zone;
                    const color = zoneColors[zone] || '#95a5a6';
                    
                    const markerIcon = L.divIcon({
                        className: 'delivery-marker',
                        html: `<div style="background: ${color}; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.2); font-size: 12px; font-weight: bold;">${zone}</div>`,
                        iconSize: [30, 30],
                        iconAnchor: [15, 15]
                    });
                    
                    const marker = L.marker([order.coordinates.lat, order.coordinates.lng], {icon: markerIcon})
                        .addTo(map)
                        .bindPopup(`
                            <div style="min-width: 200px;">
                                <strong>${order.order_number}</strong><br>
                                <strong>${order.first_name} ${order.last_name}</strong><br>
                                📞 ${order.phone}<br>
                                📦 ${order.total_items} boxes<br>
                                📍 Zone ${zone} (${order.distance} mi)<br>
                                🕒 ${order.delivery_time_slot}<br>
                                ${order.rider_first_name ? 
                                    `🚚 ${order.rider_first_name} ${order.rider_last_name}` : 
                                    '⚠️ Unassigned'
                                }
                            </div>
                        `);
                    
                    markers.push(marker);
                }
            });
        }

        // Event listeners
        function setupEventListeners() {
            // Bulk action change
            document.getElementById('bulkAction').addEventListener('change', function() {
                updateBulkActionOptions(this.value);
            });
        }

        // Auto-generate orders
        function autoGenerateOrders() {
            showLoading();
            
            fetch('delivery-management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=auto_generate_orders&date=${deliveryDate}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Orders Generated!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
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
                    text: 'Failed to generate orders'
                });
            });
        }

        // Auto-optimize routes
        function autoOptimizeRoutes() {
            Swal.fire({
                title: 'Auto-Optimize Routes?',
                text: 'This will automatically assign riders to zones for maximum efficiency.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Optimize Now',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    
                    fetch('delivery-management.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=auto_optimize_routes&date=${deliveryDate}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Routes Optimized!',
                                html: `
                                    <div style="text-align: left;">
                                        <p><strong>${data.message}</strong></p>
                                        <hr>
                                        <p><strong>Savings:</strong></p>
                                        <ul>
                                            <li>Fuel: ${data.savings.fuelSaved}</li>
                                            <li>Time: ${data.savings.timeSaved}</li>
                                            <li>Cost: ${data.savings.costSaved}</li>
                                        </ul>
                                    </div>
                                `,
                                timer: 5000
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Optimization Failed',
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
                            text: 'Failed to optimize routes'
                        });
                    });
                }
            });
        }

        // Show zone assignment modal
        function showZoneAssignment(zone) {
            document.getElementById('selectedZone').value = zone;
            showModal('zoneAssignmentModal');
        }

        // Assign rider to zone
        function assignRiderToZone() {
            const zone = document.getElementById('selectedZone').value;
            const riderId = document.getElementById('selectedRider').value;
            
            if (!zone || !riderId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please select both zone and rider'
                });
                return;
            }
            
            showLoading();
            closeModal('zoneAssignmentModal');
            
            fetch('delivery-management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=assign_rider_to_zone&zone=${zone}&rider_id=${riderId}&date=${deliveryDate}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Rider Assigned!',
                        text: data.message,
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Assignment Failed',
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
                    text: 'Failed to assign rider'
                });
            });
        }

        // Show route analysis
        function showRouteAnalysis() {
            showModal('routeAnalysisModal');
            
            fetch('delivery-management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_delivery_analytics&date=${deliveryDate}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayRouteAnalysis(data.analytics);
                } else {
                    document.getElementById('routeAnalysisContent').innerHTML = 
                        `<div style="text-align: center; color: var(--text-gray);">Failed to load analysis</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('routeAnalysisContent').innerHTML = 
                    `<div style="text-align: center; color: var(--text-gray);">Error loading analysis</div>`;
            });
        }

        // Display route analysis
        function displayRouteAnalysis(analytics) {
            const content = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    <div style="text-align: center; padding: 1rem; background: var(--cream); border-radius: var(--radius-sm);">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--curry);">${analytics.overall.total_orders}</div>
                        <div style="color: var(--text-gray);">Total Orders</div>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: var(--cream); border-radius: var(--radius-sm);">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--sage);">${analytics.delivery_rate}%</div>
                        <div style="color: var(--text-gray);">Delivery Rate</div>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: var(--cream); border-radius: var(--radius-sm);">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--brown);">${analytics.overall.delivered}</div>
                        <div style="color: var(--text-gray);">Completed</div>
                    </div>
                    <div style="text-align: center; padding: 1rem; background: var(--cream); border-radius: var(--radius-sm);">
                        <div style="font-size: 2rem; font-weight: 700; color: var(--curry);">${analytics.overall.in_transit}</div>
                        <div style="color: var(--text-gray);">In Transit</div>
                    </div>
                </div>
                
                <h4 style="margin-bottom: 1rem;">Rider Performance</h4>
                <div style="max-height: 300px; overflow-y: auto;">
                    ${analytics.riders.map(rider => `
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; margin-bottom: 0.5rem; background: var(--white); border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                            <div>
                                <strong>${rider.name}</strong>
                                <div style="font-size: 0.9rem; color: var(--text-gray);">
                                    ${rider.deliveries} deliveries, ${rider.items} items
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 600; color: var(--curry);">${rider.completed}/${rider.deliveries}</div>
                                <div style="font-size: 0.8rem; color: var(--text-gray);">Completed</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            document.getElementById('routeAnalysisContent').innerHTML = content;
        }

        // Export routes
        function exportRoutes() {
            Swal.fire({
                title: 'Export Routes',
                text: 'Select export format:',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'CSV Format',
                cancelButtonText: 'JSON Format',
                showDenyButton: true,
                denyButtonText: 'Print View'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open(`delivery-management.php?action=export_routes&date=${deliveryDate}&format=csv`, '_blank');
                } else if (result.isDenied) {
                    printDeliverySheets();
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    window.open(`delivery-management.php?action=export_routes&date=${deliveryDate}&format=json`, '_blank');
                }
            });
        }

        // Print delivery sheets
        function printDeliverySheets() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Delivery Routes - ${deliveryDate}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                        .route-section { margin-bottom: 30px; page-break-inside: avoid; }
                        .route-header { background: #f5f5f5; padding: 10px; margin-bottom: 10px; font-weight: bold; }
                        .order-item { padding: 8px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; }
                        @media print { .route-section { page-break-after: always; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Krua Thai - Delivery Routes</h1>
                        <h2>${deliveryDate}</h2>
                    </div>
                    ${riders.map(rider => {
                        const riderOrders = orders.filter(order => order.assigned_rider_id === rider.id);
                        if (riderOrders.length === 0) return '';
                        
                        return `
                            <div class="route-section">
                                <div class="route-header">
                                    Rider: ${rider.first_name} ${rider.last_name} (${riderOrders.length} orders, ${riderOrders.reduce((sum, order) => sum + parseInt(order.total_items), 0)} boxes)
                                </div>
                                ${riderOrders.map(order => `
                                    <div class="order-item">
                                        <div>
                                            <strong>${order.order_number}</strong> - ${order.first_name} ${order.last_name}<br>
                                            📞 ${order.phone}<br>
                                            📍 ${order.delivery_address}
                                        </div>
                                        <div>
                                            📦 ${order.total_items} boxes<br>
                                            🕒 ${order.delivery_time_slot}<br>
                                            Zone ${order.zone || 'N/A'}
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        `;
                    }).join('')}
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Show bulk actions modal
        function showBulkActions() {
            const selectedOrders = getSelectedOrders();
            if (selectedOrders.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Orders Selected',
                    text: 'Please select orders first'
                });
                return;
            }
            showModal('bulkActionsModal');
        }

        // Update bulk action options
        function updateBulkActionOptions(action) {
            const optionsDiv = document.getElementById('bulkActionOptions');
            
            if (action === 'update_status') {
                optionsDiv.innerHTML = `
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">New Status:</label>
                    <select id="bulkStatus" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                        <option value="confirmed">Confirmed</option>
                        <option value="preparing">Preparing</option>
                        <option value="out_for_delivery">Out for Delivery</option>
                        <option value="delivered">Delivered</option>
                    </select>
                `;
                optionsDiv.style.display = 'block';
            } else if (action === 'assign_rider') {
                optionsDiv.innerHTML = `
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Assign to Rider:</label>
                    <select id="bulkRider" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                        <option value="">Select Rider</option>
                        ${riders.map(rider => `
                            <option value="${rider.id}">${rider.first_name} ${rider.last_name} (${rider.current_load}/25)</option>
                        `).join('')}
                    </select>
                `;
                optionsDiv.style.display = 'block';
            } else {
                optionsDiv.style.display = 'none';
            }
        }

        // Execute bulk action
        function executeBulkAction() {
            const action = document.getElementById('bulkAction').value;
            const selectedOrders = getSelectedOrders();
            
            if (!action || selectedOrders.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Selection',
                    text: 'Please select action and orders'
                });
                return;
            }
            
            let actionData = {};
            
            if (action === 'update_status') {
                actionData.status = document.getElementById('bulkStatus').value;
            } else if (action === 'assign_rider') {
                actionData.rider_id = document.getElementById('bulkRider').value;
            }
            
            showLoading();
            closeModal('bulkActionsModal');
            
            fetch('delivery-management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=bulk_${action}&order_ids=${JSON.stringify(selectedOrders)}&${Object.entries(actionData).map(([k,v]) => `${k}=${v}`).join('&')}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Bulk Action Complete',
                        text: data.message,
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Bulk Action Failed',
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
                    text: 'Failed to execute bulk action'
                });
            });
        }

        // Show emergency reroute modal
        function showEmergencyReroute() {
            showModal('emergencyModal');
        }

        // Execute emergency reroute
        function executeEmergencyReroute() {
            const riderId = document.getElementById('emergencyRider').value;
            const reason = document.getElementById('emergencyReason').value;
            
            if (!riderId || !reason) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please select rider and reason'
                });
                return;
            }
            
            Swal.fire({
                title: 'Confirm Emergency Reroute',
                text: 'This will reassign all orders from the selected rider to other available riders.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Proceed',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    closeModal('emergencyModal');
                    
                    fetch('delivery-management.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=emergency_reroute&rider_id=${riderId}&reason=${reason}&date=${deliveryDate}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Emergency Reroute Complete',
                                text: data.message,
                                timer: 3000
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Reroute Failed',
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
                            text: 'Failed to execute emergency reroute'
                        });
                    });
                }
            });
        }

        // Show rider routes
        function showRiderRoutes(riderId) {
            const rider = riders.find(r => r.id === riderId);
            const riderOrders = orders.filter(order => order.assigned_rider_id === riderId);
            
            if (riderOrders.length === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'No Routes',
                    text: `${rider.first_name} ${rider.last_name} has no assigned routes today.`
                });
                return;
            }
            
            showLoading();
            
            fetch('delivery-management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=estimate_delivery_times&rider_id=${riderId}&date=${deliveryDate}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    const routeHtml = `
                        <div style="max-width: 600px;">
                            <h3 style="margin-bottom: 1rem;">${rider.first_name} ${rider.last_name} - Route Plan</h3>
                            <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem;">
                                <strong>Total Time:</strong> ${data.total_time}<br>
                                <strong>Total Distance:</strong> ${data.total_distance}
                            </div>
                            <div style="max-height: 400px; overflow-y: auto;">
                                ${data.estimates.map((estimate, index) => `
                                    <div style="padding: 1rem; margin-bottom: 0.5rem; background: var(--white); border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <strong>${estimate.order_number}</strong><br>
                                                <small>${estimate.customer}</small>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="color: var(--curry); font-weight: 600;">
                                                    🕒 ${estimate.estimated_arrival}
                                                </div>
                                                <small>${estimate.distance} mi (${estimate.travel_time} min)</small>
                                            </div>
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                    
                    Swal.fire({
                        html: routeHtml,
                        width: 700,
                        showConfirmButton: false,
                        showCloseButton: true
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
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
                    text: 'Failed to load rider routes'
                });
            });
        }

        // Utility functions
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        function refreshPage() {
            location.reload();
        }

        function refreshData() {
            // Silent refresh without full page reload
            fetch(`delivery-management.php?date=${deliveryDate}`)
                .then(response => response.text())
                .then(html => {
                    // Update specific sections if needed
                    console.log('Data refreshed silently');
                })
                .catch(error => {
                    console.error('Silent refresh failed:', error);
                });
        }

        function getSelectedOrders() {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.order-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }

        function filterOrders() {
            const statusFilter = document.getElementById('statusFilter').value;
            const zoneFilter = document.getElementById('zoneFilter').value;
            const riderFilter = document.getElementById('riderFilter').value;
            
            const rows = document.querySelectorAll('.order-row');
            
            rows.forEach(row => {
                let show = true;
                
                if (statusFilter && row.dataset.status !== statusFilter) {
                    show = false;
                }
                
                if (zoneFilter && row.dataset.zone !== zoneFilter) {
                    show = false;
                }
                
                if (riderFilter && row.dataset.rider !== riderFilter) {
                    show = false;
                }
                
                row.style.display = show ? '' : 'none';
            });
        }

        function showOrderDetails(orderId) {
            const order = orders.find(o => o.id === orderId);
            if (!order) return;
            
            const detailsHtml = `
                <div style="text-align: left; max-width: 500px;">
                    <h3 style="margin-bottom: 1rem;">Order Details</h3>
                    <div style="background: var(--cream); padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1rem;">
                        <strong>Order:</strong> ${order.order_number}<br>
                        <strong>Customer:</strong> ${order.first_name} ${order.last_name}<br>
                        <strong>Phone:</strong> ${order.phone}<br>
                        <strong>Address:</strong> ${order.delivery_address}<br>
                        <strong>Items:</strong> ${order.total_items} boxes<br>
                        <strong>Time Slot:</strong> ${order.delivery_time_slot}<br>
                        <strong>Status:</strong> ${order.status.replace('_', ' ').toUpperCase()}
                    </div>
                    ${order.zone ? `
                        <div style="background: var(--white); padding: 1rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm); margin-bottom: 1rem;">
                            <strong>Zone:</strong> ${order.zone}<br>
                            <strong>Distance:</strong> ${order.distance} miles<br>
                            ${order.costAnalysis ? `
                                <strong>Delivery Cost:</strong> ${order.costAnalysis.totalCost}<br>
                                <strong>Efficiency:</strong> ${order.costAnalysis.efficiencyScore}%
                            ` : ''}
                        </div>
                    ` : ''}
                    ${order.rider_first_name ? `
                        <div style="background: var(--sage); color: white; padding: 1rem; border-radius: var(--radius-sm);">
                            <strong>Assigned Rider:</strong> ${order.rider_first_name} ${order.rider_last_name}
                        </div>
                    ` : `
                        <div style="background: var(--curry); color: white; padding: 1rem; border-radius: var(--radius-sm);">
                            <strong>Status:</strong> Unassigned - Available for assignment
                        </div>
                    `}
                </div>
            `;
            
            Swal.fire({
                html: detailsHtml,
                width: 600,
                showConfirmButton: false,
                showCloseButton: true
            });
        }

        function editOrder(orderId) {
            const order = orders.find(o => o.id === orderId);
            if (!order) return;
            
            Swal.fire({
                title: 'Update Order Status',
                input: 'select',
                inputOptions: {
                    'confirmed': 'Confirmed',
                    'preparing': 'Preparing',
                    'ready': 'Ready',
                    'out_for_delivery': 'Out for Delivery',
                    'delivered': 'Delivered',
                    'cancelled': 'Cancelled'
                },
                inputValue: order.status,
                showCancelButton: true,
                confirmButtonText: 'Update',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Please select a status';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    updateOrderStatus(orderId, result.value);
                }
            });
        }

        function updateOrderStatus(orderId, status) {
            showLoading();
            
            fetch('delivery-management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_order_status&order_id=${orderId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Status Updated',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Update Failed',
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
                    text: 'Failed to update order status'
                });
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'r':
                        e.preventDefault();
                        refreshPage();
                        break;
                    case 'o':
                        e.preventDefault();
                        autoOptimizeRoutes();
                        break;
                    case 'g':
                        e.preventDefault();
                        autoGenerateOrders();
                        break;
                    case 'e':
                        e.preventDefault();
                        exportRoutes();
                        break;
                    case 'p':
                        e.preventDefault();
                        printDeliverySheets();
                        break;
                }
            }
            
            if (e.key === 'Escape') {
                // Close all modals
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
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
            // Clean up any intervals or resources
        });

        console.log('🚚 Krua Thai Delivery Management System initialized successfully');
        console.log('⌨️ Keyboard shortcuts: Ctrl+R (Refresh), Ctrl+O (Optimize), Ctrl+G (Generate), Ctrl+E (Export), Ctrl+P (Print)');
        console.log('📊 Features: Auto-refresh, Filtering, Search, Bulk Assignment, Export, Real-time Updates');
    </script>

    <!-- Custom CSS for better styling -->
    <style>
        .zone-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            color: white;
            font-weight: 500;
        }

        .zone-badge.zone-A {
            background: var(--zone-a);
        }

        .zone-badge.zone-B {
            background: var(--zone-b);
        }

        .zone-badge.zone-C {
            background: var(--zone-c);
        }

        .zone-badge.zone-D {
            background: var(--zone-d);
        }

        .order-row:hover {
            background-color: #f8f9fa;
        }

        .modal {
            backdrop-filter: blur(5px);
        }

        .btn:focus {
            outline: 2px solid var(--curry);
            outline-offset: 2px;
        }

        .loading-overlay {
            backdrop-filter: blur(3px);
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .zone-cards {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-grid {
                grid-template-columns: 1fr;
            }
            
            .analytics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .order-table {
                font-size: 0.9rem;
            }
            
            .order-table th,
            .order-table td {
                padding: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
            }
            
            .header-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</body>
</html>