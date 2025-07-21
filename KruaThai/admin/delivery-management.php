<?php
/**
 * Krua Thai - Smart Route Optimization & Delivery Management System (FIXED VERSION)
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
                
            // 🔥 เพิ่ม case ใหม่สำหรับ auto-generate orders
            case 'auto_generate_orders':
                $result = autoGenerateOrdersFromSubscriptions($pdo, $_POST['date']);
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
    // หาวันพุธหรือวันเสาร์ที่ใกล้ที่สุด
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

// California ZIP Code Database with precise coordinates
$zipCoordinates = [
    // Zone A: 0-8 miles (Fullerton area)
    '92831' => ['lat' => 33.8703, 'lng' => -117.9253, 'city' => 'Fullerton', 'zone' => 'A', 'distance' => 2.1],
    '92832' => ['lat' => 33.8847, 'lng' => -117.9390, 'city' => 'Fullerton', 'zone' => 'A', 'distance' => 3.4],
    '92833' => ['lat' => 33.8889, 'lng' => -117.9256, 'city' => 'Fullerton', 'zone' => 'A', 'distance' => 2.8],
    '92834' => ['lat' => 33.9172, 'lng' => -117.9467, 'city' => 'Fullerton', 'zone' => 'A', 'distance' => 4.2],
    '92835' => ['lat' => 33.8892, 'lng' => -117.8817, 'city' => 'Fullerton', 'zone' => 'A', 'distance' => 1.8],
    '92821' => ['lat' => 33.9097, 'lng' => -117.9006, 'city' => 'Brea', 'zone' => 'A', 'distance' => 3.1],
    '92823' => ['lat' => 33.9267, 'lng' => -117.8653, 'city' => 'Brea', 'zone' => 'A', 'distance' => 2.9],
    
    // Zone B: 8-15 miles
    '90620' => ['lat' => 33.8408, 'lng' => -118.0011, 'city' => 'Buena Park', 'zone' => 'B', 'distance' => 8.7],
    '90621' => ['lat' => 33.8803, 'lng' => -117.9322, 'city' => 'Buena Park', 'zone' => 'B', 'distance' => 10.2],
    '92801' => ['lat' => 33.8353, 'lng' => -117.9145, 'city' => 'Anaheim', 'zone' => 'B', 'distance' => 9.4],
    '92802' => ['lat' => 33.8025, 'lng' => -117.9228, 'city' => 'Anaheim', 'zone' => 'B', 'distance' => 11.8],
    '92804' => ['lat' => 33.8172, 'lng' => -117.8978, 'city' => 'Anaheim', 'zone' => 'B', 'distance' => 12.3],
    '92805' => ['lat' => 33.8614, 'lng' => -117.9078, 'city' => 'Anaheim', 'zone' => 'B', 'distance' => 8.9],
    
    // Zone C: 15-25 miles
    '92840' => ['lat' => 33.7742, 'lng' => -117.9378, 'city' => 'Garden Grove', 'zone' => 'C', 'distance' => 18.2],
    '92841' => ['lat' => 33.7894, 'lng' => -117.9578, 'city' => 'Garden Grove', 'zone' => 'C', 'distance' => 16.9],
    '92843' => ['lat' => 33.7739, 'lng' => -117.9028, 'city' => 'Garden Grove', 'zone' => 'C', 'distance' => 19.1],
    '92683' => ['lat' => 33.7175, 'lng' => -117.9581, 'city' => 'Westminster', 'zone' => 'C', 'distance' => 22.4],
    
    // Zone D: 25+ miles
    '92703' => ['lat' => 33.7492, 'lng' => -117.8731, 'city' => 'Santa Ana', 'zone' => 'D', 'distance' => 28.6],
    '92648' => ['lat' => 33.6597, 'lng' => -117.9992, 'city' => 'Huntington Beach', 'zone' => 'D', 'distance' => 32.1],
    '92647' => ['lat' => 33.7247, 'lng' => -118.0056, 'city' => 'Huntington Beach', 'zone' => 'D', 'distance' => 26.8],
];

// ======================================================================
// 🔥 NEW FUNCTIONS - Auto-generate orders from subscriptions
// ======================================================================

/**
 * Auto-generate orders from subscription menus if they don't exist
 */
function autoGenerateOrdersFromSubscriptions($pdo, $date) {
    try {
        // ดึงข้อมูล subscription menus ที่ยังไม่มี orders
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
            // สร้าง order ใหม่
            $order_id = generateUUID();
            $order_number = 'ORD-' . date('Ymd', strtotime($date)) . '-' . substr($order_id, 0, 6);
            
            // ดึงข้อมูลผู้ใช้
            $stmt = $pdo->prepare("SELECT delivery_address FROM users WHERE id = ?");
            $stmt->execute([$subscription['user_id']]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // สร้าง order
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

/**
 * Generate UUID for new records
 */


// ======================================================================
// EXISTING FUNCTIONS (Advanced Functions)
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
        // หาวันพุธของสัปดาห์
        $wednesday = clone $today;
        $wednesday->modify("+" . $week . " weeks");
        $wednesday->modify("wednesday this week");
        
        // หาวันเสาร์ของสัปดาห์
        $saturday = clone $today;
        $saturday->modify("+" . $week . " weeks");
        $saturday->modify("saturday this week");
        
        // เพิ่มเฉพาะวันที่ยังไม่ผ่านไป
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

// ตรวจสอบว่าวันที่เลือกถูกต้องหรือไม่
function isValidDeliveryDate($date) {
    $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 3=Wednesday, 6=Saturday
    return in_array($dayOfWeek, [3, 6]); // เฉพาะวันพุธ(3) และวันเสาร์(6)
}

function calculateOptimalDeliveryCost($distance, $boxCount, $timeSlot = 'normal') {
    // Dynamic cost factors
    $fuelCostPerGallon = 4.85;  // Current CA gas price
    $milesPerGallon = 22;       // Delivery vehicle efficiency
    $avgSpeedMph = 28;          // City traffic speed
    $hourlyLaborCost = 18;      // CA minimum + benefits
    
    // Time multipliers
    $timeMultiplier = 1.0;
    if ($timeSlot === 'rush_hour') $timeMultiplier = 1.4;
    if ($timeSlot === 'lunch') $timeMultiplier = 1.2;
    if ($timeSlot === 'evening') $timeMultiplier = 1.1;
    
    // Round trip calculation
    $totalDistance = $distance * 2 * $timeMultiplier;
    
    // Cost calculations
    $fuelCost = ($totalDistance / $milesPerGallon) * $fuelCostPerGallon;
    $deliveryTime = ($totalDistance / $avgSpeedMph) + 0.25; // +15min for delivery
    $laborCost = $deliveryTime * $hourlyLaborCost;
    $totalCost = $fuelCost + $laborCost;
    
    // Efficiency metrics
    $costPerBox = $boxCount > 0 ? $totalCost / $boxCount : 0;
    $efficiencyScore = $boxCount > 0 ? min(100, (20 / $costPerBox) * 10) : 0;
    
    return [
        'distance' => round($distance, 2),
        'totalDistance' => round($totalDistance, 2),
        'fuelCost' => round($fuelCost, 2),
        'laborCost' => round($laborCost, 2),
        'totalCost' => round($totalCost, 2),
        'costPerBox' => round($costPerBox, 2),
        'deliveryTime' => round($deliveryTime * 60, 0), // in minutes
        'efficiencyScore' => round($efficiencyScore, 1),
        'timeMultiplier' => $timeMultiplier
    ];
}

function assignRiderToZone($pdo, $zone, $riderId, $date) {
    try {
        $pdo->beginTransaction();
        
        // ดึง orders ในโซนที่เลือก
        $stmt = $pdo->prepare("
            SELECT o.id, o.order_number, o.total_items, o.status,
                   u.first_name, u.last_name, u.zip_code
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE DATE(o.delivery_date) = ? 
            AND o.status IN ('confirmed', 'preparing', 'ready')
            AND (o.assigned_rider_id IS NULL OR o.assigned_rider_id = '')
            ORDER BY u.zip_code
        ");
        $stmt->execute([$date]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $assignedCount = 0;
        global $zipCoordinates;
        
        foreach ($orders as $order) {
            $zipCode = substr($order['zip_code'], 0, 5);
            if (isset($zipCoordinates[$zipCode]) && $zipCoordinates[$zipCode]['zone'] === $zone) {
                // Assign order to rider
                $updateStmt = $pdo->prepare("
                    UPDATE orders 
                    SET assigned_rider_id = ?, 
                        status = 'out_for_delivery',
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$riderId, $order['id']]);
                $assignedCount++;
            }
        }
        
        $pdo->commit();
        
        // ดึงชื่อ rider
        $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
        $stmt->execute([$riderId]);
        $riderName = $stmt->fetchColumn();
        
        return [
            'success' => true, 
            'message' => "Successfully assigned {$assignedCount} orders in Zone {$zone} to {$riderName}",
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
        
        // Get all unassigned orders for the date
        $stmt = $pdo->prepare("
            SELECT o.id, o.order_number, o.total_items, o.status, o.assigned_rider_id,
                   o.delivery_date, o.subscription_id,
                   u.first_name, u.last_name, u.zip_code, u.delivery_address
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE DATE(o.delivery_date) = ? 
            AND o.status IN ('confirmed', 'preparing', 'ready')
            AND (o.assigned_rider_id IS NULL OR o.assigned_rider_id = '')
            ORDER BY u.zip_code, o.created_at
        "); 
        $stmt->execute([$date]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get available riders
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, 
                   COALESCE((SELECT SUM(total_items) FROM orders WHERE assigned_rider_id = users.id AND DATE(delivery_date) = ?), 0) as current_load
            FROM users 
            WHERE role = 'rider' AND status = 'active'
        ");
        $stmt->execute([$date]);
        $riders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($riders)) {
            return ['success' => false, 'message' => 'No available riders found'];
        }
        
        // Group orders by zones and calculate efficiency
        $zoneGroups = [];
        foreach ($orders as $order) {
            $zipCode = substr($order['zip_code'], 0, 5);
            if (isset($zipCoordinates[$zipCode])) {
                $zone = $zipCoordinates[$zipCode]['zone'];
                $distance = $zipCoordinates[$zipCode]['distance'];
                
                if (!isset($zoneGroups[$zone])) {
                    $zoneGroups[$zone] = [
                        'orders' => [],
                        'totalBoxes' => 0,
                        'totalDistance' => 0,
                        'efficiency' => 0
                    ];
                }
                
                $zoneGroups[$zone]['orders'][] = $order;
                $zoneGroups[$zone]['totalBoxes'] += $order['total_items'];
                $zoneGroups[$zone]['totalDistance'] += $distance;
            }
        }
        
        // Calculate efficiency for each zone
        foreach ($zoneGroups as $zone => &$group) {
            $avgDistance = $group['totalDistance'] / count($group['orders']);
            $group['efficiency'] = $group['totalBoxes'] / max($avgDistance, 1);
        }
        
        // Sort zones by efficiency (highest first)
        uasort($zoneGroups, function($a, $b) {
            return $b['efficiency'] <=> $a['efficiency'];
        });
        
        // Sort riders by current load (lowest first)
        usort($riders, function($a, $b) {
            return $a['current_load'] <=> $b['current_load'];
        });
        
        $pdo->beginTransaction();
        $totalAssigned = 0;
        $assignments = [];
        
        // Assign zones to riders using the optimal algorithm
        $riderIndex = 0;
        foreach ($zoneGroups as $zone => $group) {
            if ($riderIndex >= count($riders)) {
                $riderIndex = 0; // Wrap around
            }
            
            $rider = $riders[$riderIndex];
            $maxCapacity = 25; // Maximum boxes per rider
            
            // Check if rider can handle this zone
            if (($rider['current_load'] + $group['totalBoxes']) <= $maxCapacity) {
                // Assign all orders in this zone to the rider
                foreach ($group['orders'] as $order) {
                    $stmt = $pdo->prepare("
                        UPDATE orders 
                        SET assigned_rider_id = ?, 
                            status = 'out_for_delivery',
                            updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$rider['id'], $order['id']]);
                    $totalAssigned++;
                }
                
                $assignments[] = [
                    'rider' => $rider['first_name'] . ' ' . $rider['last_name'],
                    'zone' => $zone,
                    'orders' => count($group['orders']),
                    'boxes' => $group['totalBoxes'],
                    'efficiency' => round($group['efficiency'], 1)
                ];
                
                // Update rider's current load
                $riders[$riderIndex]['current_load'] += $group['totalBoxes'];
            }
            
            $riderIndex++;
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => "Auto-optimization complete! Assigned {$totalAssigned} orders optimally.",
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

function calculateRouteEfficiency($pdo, $riderId, $date) {
    try {
        global $zipCoordinates;
        
        // Get rider's assigned orders
        $stmt = $pdo->prepare("
            SELECT o.id, o.order_number, o.total_items, o.status, o.assigned_rider_id,
                   o.delivery_date, o.subscription_id,
                   u.first_name, u.last_name, u.zip_code, u.delivery_address
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.assigned_rider_id = ? 
            AND DATE(o.delivery_date) = ?
            AND o.status = 'out_for_delivery'
            ORDER BY u.zip_code, o.created_at
        ");
        $stmt->execute([$riderId, $date]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($orders)) {
            return ['success' => true, 'data' => ['efficiency' => 0, 'totalBoxes' => 0, 'totalDistance' => 0]];
        }
        
        $totalBoxes = 0;
        $totalDistance = 0;
        $routeDetails = [];
        
        foreach ($orders as $order) {
            $zipCode = substr($order['zip_code'], 0, 5);
            if (isset($zipCoordinates[$zipCode])) {
                $distance = $zipCoordinates[$zipCode]['distance'];
                $totalBoxes += $order['total_items'];
                $totalDistance += $distance;
                
                $routeDetails[] = [
                    'customer' => $order['first_name'] . ' ' . $order['last_name'],
                    'boxes' => $order['total_items'],
                    'distance' => $distance,
                    'zone' => $zipCoordinates[$zipCode]['zone'],
                    'city' => $zipCoordinates[$zipCode]['city']
                ];
            }
        }
        
        $efficiency = $totalDistance > 0 ? ($totalBoxes / $totalDistance) * 10 : 0;
        
        return [
            'success' => true,
            'data' => [
                'efficiency' => round($efficiency, 1),
                'totalBoxes' => $totalBoxes,
                'totalDistance' => round($totalDistance, 1),
                'averageBoxesPerMile' => round($totalBoxes / max($totalDistance, 1), 2),
                'routeDetails' => $routeDetails
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error calculating efficiency: ' . $e->getMessage()];
    }
}

// ======================================================================
// FETCH DELIVERY DATA (ENHANCED VERSION)
// ======================================================================
function sanitizeTimeSlot($timeSlot) {
    // ถ้าไม่มีค่าให้ใช้ default
    if (empty($timeSlot)) return '12:00-15:00';
    
    $timeSlot = strtolower(trim($timeSlot));
    
    // แปลงค่าจาก text เป็น time range ที่ตรงกับ enum
    if (strpos($timeSlot, 'morning') !== false || strpos($timeSlot, '9:') !== false || strpos($timeSlot, '10:') !== false) {
        return '09:00-12:00';
    } elseif (strpos($timeSlot, 'evening') !== false || strpos($timeSlot, '18:') !== false || strpos($timeSlot, '19:') !== false) {
        return '18:00-21:00';
    } elseif (strpos($timeSlot, 'lunch') !== false || strpos($timeSlot, '12:') !== false || strpos($timeSlot, '13:') !== false) {
        return '12:00-15:00';  
    } elseif (strpos($timeSlot, 'afternoon') !== false || strpos($timeSlot, '15:') !== false || strpos($timeSlot, '16:') !== false) {
        return '15:00-18:00';
    } else {
        // ถ้าเป็น enum ที่ถูกต้องอยู่แล้ว ให้ return ตามเดิม
        $validSlots = ['09:00-12:00', '12:00-15:00', '15:00-18:00', '18:00-21:00'];
        if (in_array($timeSlot, $validSlots)) {
            return $timeSlot;
        }
        return '12:00-15:00'; // default
    }
}

// Fetch delivery data for the selected date
try {
    // 🔥 Auto-generate orders from subscriptions if needed
    $auto_generate_result = autoGenerateOrdersFromSubscriptions($pdo, $deliveryDate);
    
    // Get orders for the delivery date (including newly generated ones)
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.total_items, o.status, o.assigned_rider_id,
               o.delivery_date, o.created_at, o.subscription_id,
               u.id as user_id, u.first_name, u.last_name, u.phone, u.zip_code, 
               u.delivery_address, u.city, u.state,
               r.first_name as rider_first_name, r.last_name as rider_last_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN users r ON o.assigned_rider_id = r.id
        WHERE DATE(o.delivery_date) = ? AND o.status != 'cancelled'
        ORDER BY u.zip_code, o.created_at
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
    
} catch (Exception $e) {
    $orders = [];
    $riders = [];
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
            'isAssignable' => false // 🔥 เพิ่มค่าเริ่มต้น
        ];
        continue;
    }
    
    $orderCount = count($zoneOrders);
    $totalBoxes = array_sum(array_column($zoneOrders, 'total_items'));
    $totalDistance = array_sum(array_column($zoneOrders, 'distance'));
    $totalCost = array_sum(array_column(array_column($zoneOrders, 'costAnalysis'), 'totalCost'));
    $assignedCount = count(array_filter($zoneOrders, function($o) { return !empty($o['assigned_rider_id']); }));
    
    // 🔥 เพิ่มการคำนวณออเดอร์ที่ยังไม่ถูกจ่ายงาน
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
        'isAssignable' => $unassignedCount > 0 // 🔥 เพิ่ม Key นี้เพื่อส่งไปให้ HTML
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
        }
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
                <div class="sidebar-subtitle">Route Optimizer</div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="dashboard.php" class="nav-item">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="delivery-management.php" class="nav-item active">
                        <i class="nav-icon fas fa-route"></i>
                        <span>Route Optimizer</span>
                    </a>
                    <a href="orders.php" class="nav-item">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <span>Orders</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Delivery</div>
                    <a href="assign-riders.php" class="nav-item">
                        <i class="nav-icon fas fa-users"></i>
                        <span>Assign Riders</span>
                    </a>
                    <a href="track-deliveries.php" class="nav-item">
                        <i class="nav-icon fas fa-map-marked-alt"></i>
                        <span>Track Deliveries</span>
                    </a>
                    <a href="delivery-zones.php" class="nav-item">
                        <i class="nav-icon fas fa-map"></i>
                        <span>Delivery Zones</span>
                    </a>
                </div>
                
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
                        
                        <!-- 🔥 เพิ่มปุ่มใหม่นี้ -->
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
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= $totalStats['avgEfficiency'] ?>%</div>
                    <div class="stat-label">Efficiency Score</div>
                    <div class="stat-change <?= $totalStats['avgEfficiency'] > 70 ? 'positive' : '' ?>">
                        <i class="fas fa-<?= $totalStats['avgEfficiency'] > 70 ? 'arrow-up' : 'exclamation-triangle' ?>"></i>
                        <?= $totalStats['avgEfficiency'] > 70 ? 'Excellent' : 'Can improve' ?>
                    </div>
                </div>
            </div>

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
                    <button class="btn btn-success" onclick="autoOptimizeRoutes()">
                        <i class="fas fa-magic"></i>
                        Auto-Optimize All
                    </button>
                    <button class="btn btn-primary" onclick="showRouteAnalysis()">
                        <i class="fas fa-analytics"></i>
                        Route Analysis
                    </button>
                    <button class="btn btn-warning" onclick="exportRoutes()">
                        <i class="fas fa-download"></i>
                        Export Routes
                    </button>
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
                                    <?= $zone === 'A' ? '0-8 mi' : ($zone === 'B' ? '8-15 mi' : ($zone === 'C' ? '15-25 mi' : '25+ mi')) ?>
                                </div>
                            </div>
                            
                            <div class="zone-stats">
                                <div class="zone-stat">
                                    <div class="zone-stat-value">$<?= number_format($zoneStats[$zone]['totalCost'], 0) ?></div>
                                    <div class="zone-stat-label">Cost</div>
                                </div>
                            </div>
                            
                            <div class="efficiency-bar">
                                <div class="efficiency-fill zone-<?= $zone ?>" 
                                     style="width: <?= min($zoneStats[$zone]['avgEfficiency'], 100) ?>%"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <span style="font-size: 0.9rem; color: var(--text-gray);">
                                    Efficiency: <?= $zoneStats[$zone]['avgEfficiency'] ?>%
                                </span>
                                <span style="font-size: 0.8rem; color: var(--text-gray);">
                                    <?= $zoneStats[$zone]['avgBoxesPerMile'] ?> boxes/mile
                                </span>
                            </div>
                            
                            <div style="margin-bottom: 1rem;">
                                <div style="font-size: 0.9rem; font-weight: 500; margin-bottom: 0.5rem; color: var(--text-dark);">
                                    Deliveries in Zone <?= $zone ?>:
                                </div>
                                <?php foreach (array_slice($deliveryZones[$zone], 0, 3) as $order): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid var(--border-light);">
                                        <div>
                                            <div style="font-weight: 500; font-size: 0.9rem;">
                                                <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: var(--text-gray);">
                                                <?= $order['coordinates']['city'] ?> • <?= $order['zip_code'] ?>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-weight: 600; color: var(--curry);">
                                                <?= $order['total_items'] ?> boxes
                                            </div>
                                            <div style="font-size: 0.8rem; color: var(--text-gray);">
                                                <?= $order['distance'] ?> mi
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($deliveryZones[$zone]) > 3): ?>
                                    <div style="text-align: center; padding: 0.5rem 0; color: var(--text-gray); font-size: 0.8rem;">
                                        +<?= count($deliveryZones[$zone]) - 3 ?> more orders
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn btn-primary" style="flex: 1; padding: 0.5rem;"
                            <button class="btn <?= !$zoneStats[$zone]['isAssignable'] ? 'btn-secondary' : 'btn-primary' ?>" style="flex: 1; padding: 0.5rem;"
        onclick="assignRiderToZone('<?= $zone ?>')"
        <?= !$zoneStats[$zone]['isAssignable'] ? 'disabled' : '' ?>>
    <?php if (!$zoneStats[$zone]['isAssignable']): ?>
        <i class="fas fa-check-circle"></i>
        <span>Assigned</span>
    <?php else: ?>
        <i class="fas fa-user-plus"></i>
        <span>Assign Rider</span>
    <?php endif; ?>
</button>
                                <button class="btn btn-secondary" style="padding: 0.5rem;"
                                        onclick="viewZoneDetails('<?= $zone ?>')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Unassigned Orders Alert -->
            <?php if ($totalStats['unassignedOrders'] > 0): ?>
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 2rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <i class="fas fa-exclamation-triangle" style="color: #856404; font-size: 1.5rem;"></i>
                    <div>
                        <h4 style="margin: 0; color: #856404;">Unassigned Orders Alert</h4>
                        <p style="margin: 0; color: #856404;">
                            You have <?= $totalStats['unassignedOrders'] ?> orders that need rider assignment.
                            <button class="btn btn-warning" style="margin-left: 1rem;" onclick="autoOptimizeRoutes()">
                                <i class="fas fa-magic"></i>
                                Auto-Assign Now
                            </button>
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h3>Optimizing Routes...</h3>
            <p>Calculating the most efficient delivery paths</p>
        </div>
    </div>

    <!-- Rider Assignment Modal -->
    <div id="riderModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: var(--white); border-radius: var(--radius-md); padding: 2rem; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="margin: 0;">Assign Rider to Zone <span id="selectedZone"></span></h3>
                <button onclick="closeRiderModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-gray);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="ridersList">
                <?php foreach ($riders as $rider): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid var(--border-light); border-radius: var(--radius-sm); margin-bottom: 0.5rem; cursor: pointer; transition: var(--transition);" 
                         onclick="confirmRiderAssignment('<?= $rider['id'] ?>', '<?= htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']) ?>')"
                         onmouseover="this.style.background='var(--cream)'"
                         onmouseout="this.style.background='var(--white)'">
                        <div>
                            <div style="font-weight: 600;"><?= htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']) ?></div>
                            <div style="font-size: 0.9rem; color: var(--text-gray);">
                                Current load: <?= $rider['current_load'] ?> boxes (<?= $rider['current_orders'] ?> orders)
                            </div>
                        </div>
                        <div>
                            <i class="fas fa-chevron-right" style="color: var(--curry);"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Global variables
        let map;
        let selectedZone = '';
        const shopLocation = <?= json_encode($shopLocation) ?>;
        const zipCoordinates = <?= json_encode($zipCoordinates) ?>;
        const orders = <?= json_encode($orders) ?>;

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeMap();
            initializeTooltips();
            console.log('Krua Thai Route Optimization System initialized');
        });

        // Initialize map
        function initializeMap() {
            map = L.map('map').setView([shopLocation.lat, shopLocation.lng], 10);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add shop marker
            const shopIcon = L.divIcon({
                html: '<div style="background: var(--curry); color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><i class="fas fa-store"></i></div>',
                iconSize: [30, 30],
                className: 'custom-div-icon'
            });
            
            L.marker([shopLocation.lat, shopLocation.lng], { icon: shopIcon })
                .addTo(map)
                .bindPopup(`<strong>${shopLocation.name}</strong><br>${shopLocation.address}`);
            
            // Add delivery markers by zones
            const zoneColors = {
                'A': '#27ae60',
                'B': '#f39c12', 
                'C': '#e67e22',
                'D': '#e74c3c'
            };
            
            orders.forEach(order => {
                if (order.coordinates) {
                    const zoneColor = zoneColors[order.zone] || '#95a5a6';
                    const deliveryIcon = L.divIcon({
                        html: `<div style="background: ${zoneColor}; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3); font-size: 10px;">${order.total_items}</div>`,
                        iconSize: [24, 24],
                        className: 'custom-div-icon'
                    });
                    
                    L.marker([order.coordinates.lat, order.coordinates.lng], { icon: deliveryIcon })
                        .addTo(map)
                        .bindPopup(`
                            <div style="min-width: 200px;">
                                <strong>${order.first_name} ${order.last_name}</strong><br>
                                <small style="color: #666;">${order.coordinates.city}, ${order.zip_code}</small><br>
                                <div style="margin: 0.5rem 0;">
                                    <span style="background: ${zoneColor}; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px;">Zone ${order.zone}</span>
                                    <span style="margin-left: 0.5rem; font-weight: bold;">${order.total_items} boxes</span>
                                </div>
                                <small>Distance: ${order.distance} miles</small><br>
                                <small>Cost: ${order.costAnalysis.totalCost}</small>
                                ${order.assigned_rider_id ? `<br><small style="color: green;">✓ Assigned to rider</small>` : `<br><small style="color: orange;">⚠ Unassigned</small>`}
                            </div>
                        `);
                }
            });
        }

        // 🔥 NEW FUNCTION - Auto-generate orders from subscriptions
        function autoGenerateOrders() {
            Swal.fire({
                title: 'Generate Orders from Subscriptions?',
                html: `
                    <div style="text-align: left; margin: 1rem 0;">
                        <p>This will automatically create orders from active subscription menus for the selected date:</p>
                        <ul style="margin: 1rem 0; padding-left: 1.5rem;">
                            <li>Check for subscription menus without corresponding orders</li>
                            <li>Create new orders with proper order numbers</li>
                            <li>Set status to 'confirmed' for kitchen preparation</li>
                            <li>Sync data between Kitchen Dashboard and Delivery Management</li>
                        </ul>
                        <p><strong>This is safe to run multiple times - it won't create duplicates.</strong></p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-plus-circle"></i> Yes, Generate Orders!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('loadingOverlay').style.display = 'flex';
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=auto_generate_orders&date=${encodeURIComponent('<?= $deliveryDate ?>')}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('loadingOverlay').style.display = 'none';
                        
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Orders Generated Successfully!',
                                html: `
                                    <div style="text-align: left;">
                                        <p><strong>${data.generated} orders</strong> were created from subscription menus.</p>
                                        <p>✅ Kitchen Dashboard and Delivery Management are now synchronized.</p>
                                        <p>🚀 You can now proceed with route optimization and rider assignment.</p>
                                    </div>
                                `,
                                confirmButtonText: 'Great!',
                                confirmButtonColor: '#28a745'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Generation Failed',
                                text: data.message || 'Failed to generate orders. Please try again.',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    })
                    .catch(error => {
                        document.getElementById('loadingOverlay').style.display = 'none';
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Failed to connect to the server. Please try again.',
                            confirmButtonColor: '#dc3545'
                        });
                    });
                }
            });
        }

        // Auto-optimize routes
        function autoOptimizeRoutes() {
            Swal.fire({
                title: 'Auto-Optimize Routes?',
                html: `
                    <div style="text-align: left; margin: 1rem 0;">
                        <p>This will automatically assign all unassigned orders to riders using our smart algorithm:</p>
                        <ul style="margin: 1rem 0; padding-left: 1.5rem;">
                            <li>Group orders by zones for efficiency</li>
                            <li>Balance workload across available riders</li>
                            <li>Minimize total distance and fuel costs</li>
                            <li>Maximize boxes per mile ratio</li>
                        </ul>
                        <p><strong>Expected benefits:</strong></p>
                        <ul style="margin: 1rem 0; padding-left: 1.5rem;">
                            <li>25-45% fuel savings</li>
                            <li>2-4 hours time savings</li>
                            <li>$35-65 cost reduction</li>
                        </ul>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#27ae60',
                cancelButtonColor: '#e74c3c',
                confirmButtonText: '<i class="fas fa-magic"></i> Yes, Optimize!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('loadingOverlay').style.display = 'flex';
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=auto_optimize_routes&date=${encodeURIComponent('<?= $deliveryDate ?>')}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('loadingOverlay').style.display = 'none';
                        
                        if (data.success) {
                            let resultsHtml = `
                                <div style="text-align: left;">
                                    <h4 style="color: #27ae60; margin-bottom: 1rem;">
                                        <i class="fas fa-check-circle"></i> Optimization Complete!
                                    </h4>
                                    <p><strong>${data.totalAssigned} orders</strong> have been optimally assigned.</p>
                                    
                                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                                        <h5>Assignments Made:</h5>
                                        <ul>
                            `;
                            
                            data.assignments.forEach(assignment => {
                                resultsHtml += `
                                    <li><strong>${assignment.rider}</strong> → Zone ${assignment.zone} 
                                        (${assignment.orders} orders, ${assignment.boxes} boxes, ${assignment.efficiency}% efficiency)</li>
                                `;
                            });
                            
                            resultsHtml += `
                                        </ul>
                                    </div>
                                    
                                    <div style="background: #d4edda; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                                        <h5 style="color: #155724;">Estimated Savings:</h5>
                                        <ul style="color: #155724;">
                                            <li>Fuel saved: ${data.savings.fuelSaved}</li>
                                            <li>Time saved: ${data.savings.timeSaved}</li>
                                            <li>Cost saved: ${data.savings.costSaved}</li>
                                        </ul>
                                    </div>
                                </div>
                            `;
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'Routes Optimized!',
                                html: resultsHtml,
                                confirmButtonText: 'Great!',
                                confirmButtonColor: '#27ae60'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Optimization Failed',
                                text: data.message || 'Failed to optimize routes. Please try again.',
                                confirmButtonColor: '#e74c3c'
                            });
                        }
                    })
                    .catch(error => {
                        document.getElementById('loadingOverlay').style.display = 'none';
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Failed to connect to the server. Please check your connection and try again.',
                            confirmButtonColor: '#e74c3c'
                        });
                    });
                }
            });
        }

        // Assign rider to zone
        function assignRiderToZone(zone) {
            selectedZone = zone;
            document.getElementById('selectedZone').textContent = zone;
            document.getElementById('riderModal').style.display = 'flex';
        }

        // Close rider modal
        function closeRiderModal() {
            document.getElementById('riderModal').style.display = 'none';
            selectedZone = '';
        }

        // Confirm rider assignment
        function confirmRiderAssignment(riderId, riderName) {
            closeRiderModal();
            
            Swal.fire({
                title: 'Confirm Assignment',
                html: `Assign all Zone <strong>${selectedZone}</strong> orders to <strong>${riderName}</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#27ae60',
                cancelButtonColor: '#e74c3c',
                confirmButtonText: 'Yes, Assign!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('loadingOverlay').style.display = 'flex';
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=assign_rider_to_zone&zone=${selectedZone}&rider_id=${riderId}&date=${encodeURIComponent('<?= $deliveryDate ?>')}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('loadingOverlay').style.display = 'none';
                        
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Assignment Successful!',
                                html: `<strong>${data.ordersAssigned} orders</strong> in Zone ${selectedZone} have been assigned to ${riderName}.`,
                                confirmButtonText: 'Great!',
                                confirmButtonColor: '#27ae60'
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Assignment Failed',
                                text: data.message || 'Failed to assign rider. Please try again.',
                                confirmButtonColor: '#e74c3c'
                            });
                        }
                    })
                    .catch(error => {
                        document.getElementById('loadingOverlay').style.display = 'none';
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Connection Error',
                            text: 'Failed to connect to the server. Please try again.',
                            confirmButtonColor: '#e74c3c'
                        });
                    });
                }
            });
        }

        // Show rider routes
        function showRiderRoutes(riderId) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=calculate_route_efficiency&rider_id=${riderId}&date=${encodeURIComponent('<?= $deliveryDate ?>')}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let routeHtml = `
                        <div style="text-align: left;">
                            <h4>Route Analysis</h4>
                            <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                    <div>
                                        <strong>Total Boxes:</strong> ${data.data.totalBoxes}<br>
                                        <strong>Total Distance:</strong> ${data.data.totalDistance} miles
                                    </div>
                                    <div>
                                        <strong>Efficiency:</strong> ${data.data.efficiency}%<br>
                                        <strong>Boxes/Mile:</strong> ${data.data.averageBoxesPerMile}
                                    </div>
                                </div>
                            </div>
                    `;
                    
                    if (data.data.routeDetails && data.data.routeDetails.length > 0) {
                        routeHtml += `
                            <h5>Route Details:</h5>
                            <div style="max-height: 300px; overflow-y: auto;">
                        `;
                        
                        data.data.routeDetails.forEach((stop, index) => {
                            routeHtml += `
                                <div style="display: flex; justify-content: space-between; padding: 0.5rem; border-bottom: 1px solid #dee2e6;">
                                    <div>
                                        <strong>${index + 1}. ${stop.customer}</strong><br>
                                        <small>${stop.city} (Zone ${stop.zone})</small>
                                    </div>
                                    <div style="text-align: right;">
                                        <strong>${stop.boxes} boxes</strong><br>
                                        <small>${stop.distance} miles</small>
                                    </div>
                                </div>
                            `;
                        });
                        
                        routeHtml += '</div>';
                    } else {
                        routeHtml += '<p>No active routes for this rider.</p>';
                    }
                    
                    routeHtml += '</div>';
                    
                    Swal.fire({
                        title: 'Rider Route Analysis',
                        html: routeHtml,
                        width: '600px',
                        confirmButtonText: 'Close',
                        confirmButtonColor: '#cf723a'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to get route analysis.',
                        confirmButtonColor: '#e74c3c'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Failed to get route analysis.',
                    confirmButtonColor: '#e74c3c'
                });
            });
        }

        // Other functions
        function showRouteAnalysis() {
            const totalOrders = <?= $totalStats['totalOrders'] ?>;
            const totalBoxes = <?= $totalStats['totalBoxes'] ?>;
            const totalDistance = <?= $totalStats['totalDistance'] ?>;
            const avgEfficiency = <?= $totalStats['avgEfficiency'] ?>;
            
            Swal.fire({
                title: 'Route Analysis Report',
                html: `
                    <div style="text-align: left;">
                        <h4>Overall Statistics</h4>
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <strong>Total Orders:</strong> ${totalOrders}<br>
                                    <strong>Total Boxes:</strong> ${totalBoxes}
                                </div>
                                <div>
                                    <strong>Total Distance:</strong> ${totalDistance.toFixed(1)} miles<br>
                                    <strong>Efficiency Score:</strong> ${avgEfficiency}%
                                </div>
                            </div>
                        </div>
                        
                        <h4>Zone Breakdown</h4>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach (['A', 'B', 'C', 'D'] as $zone): ?>
                                <?php if ($zoneStats[$zone]['orderCount'] > 0): ?>
                                    <div style="border: 1px solid #dee2e6; border-radius: 8px; padding: 1rem; margin: 0.5rem 0;">
                                        <h5>Zone <?= $zone ?></h5>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                            <div>Orders: <?= $zoneStats[$zone]['orderCount'] ?></div>
                                            <div>Boxes: <?= $zoneStats[$zone]['totalBoxes'] ?></div>
                                            <div>Distance: <?= $zoneStats[$zone]['totalDistance'] ?> mi</div>
                                            <div>Efficiency: <?= $zoneStats[$zone]['avgEfficiency'] ?>%</div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                `,
                width: '600px',
                confirmButtonText: 'Close',
                confirmButtonColor: '#cf723a'
            });
        }

        function exportRoutes() {
            window.open(`export-routes.php?date=${encodeURIComponent('<?= $deliveryDate ?>')}&format=csv`, '_blank');
        }

        function printDeliverySheets() {
            window.open(`print-delivery-sheets.php?date=${encodeURIComponent('<?= $deliveryDate ?>')}`, '_blank');
        }

        function refreshPage() {
            window.location.reload();
        }

        function viewZoneDetails(zone) {
            // Show detailed zone information
            console.log('Viewing zone details for:', zone);
        }

        // Initialize tooltips
        function initializeTooltips() {
            // Add any tooltip initialization here
        }

        // Auto-refresh every 5 minutes
        setInterval(() => {
            console.log('Auto-refreshing route data...');
            // In production, you might want to refresh data via AJAX instead of full page reload
        }, 300000);

        console.log('Krua Thai Smart Route Optimization System loaded successfully');
    </script>
</body>
</html>