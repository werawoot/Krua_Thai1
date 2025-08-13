<?php
/**
 * Somdul Table - Subscription Route Optimizer
 * Integrates database subscriptions with Google Route Optimization API
 * Colors: #bd9379, #ece8e1, #adb89d, #cf723a and white
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Google API Configuration
$PROJECT_ID = 'somdultable';
$SERVICE_ACCOUNT_KEY_FILE = '../secrets/somdultable-4feccc0213da.json';

// Restaurant location
$RESTAURANT = [
    'lat' => 33.888121,
    'lng' => -117.868256,
    'address' => '3250 Yorba Linda Blvd, Fullerton, CA 92831'
];

// Get form inputs
$selected_date = isset($_POST['delivery_date']) ? $_POST['delivery_date'] : date('Y-m-d');
$selected_time = isset($_POST['delivery_time']) ? $_POST['delivery_time'] : '';

// Route optimization settings
$NUM_DRIVERS = isset($_POST['num_drivers']) ? (int)$_POST['num_drivers'] : 2;
$NUM_DRIVERS = max(1, min(10, $NUM_DRIVERS));

$CAPACITY_BUFFER = isset($_POST['capacity_buffer']) ? (int)$_POST['capacity_buffer'] : 2;
$CAPACITY_BUFFER = max(0, min(10, $CAPACITY_BUFFER));

$COST_PER_KM = isset($_POST['cost_per_km']) ? (float)$_POST['cost_per_km'] : 1.0;
$COST_PER_KM = max(0.1, min(10.0, $COST_PER_KM));

$COST_PER_HOUR = isset($_POST['cost_per_hour']) ? (float)$_POST['cost_per_hour'] : 30.0;
$COST_PER_HOUR = max(10.0, min(100.0, $COST_PER_HOUR));

$FIXED_COST_FIRST = isset($_POST['fixed_cost_first']) ? (float)$_POST['fixed_cost_first'] : 100.0;
$FIXED_COST_FIRST = max(0.0, min(500.0, $FIXED_COST_FIRST));

$FIXED_COST_OTHERS = isset($_POST['fixed_cost_others']) ? (float)$_POST['fixed_cost_others'] : 50.0;
$FIXED_COST_OTHERS = max(0.0, min(500.0, $FIXED_COST_OTHERS));

$FORCE_DISTRIBUTION = isset($_POST['force_distribution']) ? true : false;

// Initialize variables
$DELIVERIES = [];
$ROUTES = [];
$UNASSIGNED = [];
$API_SUCCESS = false;
$API_ERROR = null;
$API_DATA = null;

// Database functions
function getDatabaseConnection() {
    global $connection; // Use your existing database connection
    return $connection;
}

function getDeliveryTimeSlots() {
    return [
        '09:00-12:00' => '9:00 AM - 12:00 PM',
        '12:00-15:00' => '12:00 PM - 3:00 PM', 
        '15:00-18:00' => '3:00 PM - 6:00 PM',
        '18:00-21:00' => '6:00 PM - 9:00 PM'
    ];
}

function fetchDeliveriesForDate($date, $time_slot = '') {
    $connection = getDatabaseConnection();
    
    // Convert date to day of week for delivery_days check
    $dayOfWeek = strtolower(date('l', strtotime($date))); // monday, tuesday, etc.
    
    $sql = "
        SELECT 
            s.id as subscription_id,
            s.user_id,
            s.assigned_rider_id,
            s.total_amount,
            s.delivery_days,
            s.preferred_delivery_time,
            s.status as subscription_status,
            u.first_name,
            u.last_name,
            u.delivery_address,
            u.city,
            u.state,
            u.zip_code,
            u.phone
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        WHERE s.status = 'active'
        AND (
            s.delivery_days LIKE ? 
            OR s.delivery_days LIKE ?
            OR s.delivery_days IS NULL 
            OR s.delivery_days = ''
        )
    ";
    
    // Add time slot condition if provided
    if (!empty($time_slot)) {
        $sql .= " AND s.preferred_delivery_time = ?";
    }
    
    $sql .= " ORDER BY u.zip_code, u.last_name, u.first_name";
    
    try {
        $stmt = mysqli_prepare($connection, $sql);
        
        if (!empty($time_slot)) {
            // Bind parameters for delivery_days (both day name and date), and time_slot
            $dayOfWeekParam = "%$dayOfWeek%";
            $dateParam = "%$date%";
            mysqli_stmt_bind_param($stmt, "sss", $dayOfWeekParam, $dateParam, $time_slot);
        } else {
            // Bind parameters for delivery_days (both day name and date)
            $dayOfWeekParam = "%$dayOfWeek%";
            $dateParam = "%$date%";
            mysqli_stmt_bind_param($stmt, "ss", $dayOfWeekParam, $dateParam);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $deliveries = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Calculate total items (you might want to adjust this based on your business logic)
            $total_items = 1; // Default to 1 meal per subscription
            if ($row['total_amount']) {
                // Estimate items based on amount (assuming $15 per meal)
                $total_items = max(1, round($row['total_amount'] / 15));
            }
            
            $deliveries[] = [
                'subscription_id' => $row['subscription_id'],
                'customer_name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'delivery_address' => $row['delivery_address'] . ', ' . $row['city'] . ', ' . $row['state'] . ' ' . $row['zip_code'],
                'phone' => $row['phone'] ?? '',
                'zip_code' => $row['zip_code'],
                'total_items' => $total_items,
                'delivery_time' => $row['preferred_delivery_time'] ?? '',
                'assigned_rider_id' => $row['assigned_rider_id'],
                'latitude' => null, // Will be populated by geocoding if needed
                'longitude' => null,
                'delivery_days' => $row['delivery_days'] // For debugging
            ];
        }
        
        mysqli_stmt_close($stmt);
        return $deliveries;
        
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return [];
    }
}

// Add debugging function to see what's in delivery_days
function debugDeliveryDays($date) {
    $connection = getDatabaseConnection();
    
    $sql = "
        SELECT 
            s.id,
            s.delivery_days,
            s.preferred_delivery_time,
            s.status,
            CONCAT(u.first_name, ' ', u.last_name) as customer_name
        FROM subscriptions s
        JOIN users u ON s.user_id = u.id
        WHERE s.status = 'active'
        ORDER BY s.id
        LIMIT 10
    ";
    
    $result = mysqli_query($connection, $sql);
    $debug_data = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $debug_data[] = $row;
    }
    
    return $debug_data;
}

// Simple geocoding function (you might want to use Google Geocoding API for better results)
function addCoordinatesToDeliveries($deliveries) {
    global $RESTAURANT; // Access global restaurant location
    
    // For now, we'll use approximate coordinates based on zip codes in Fullerton area
    $zipCoordinates = [
        '92831' => ['lat' => 33.8703, 'lng' => -117.9253],
        '92832' => ['lat' => 33.8536, 'lng' => -117.9270],
        '92833' => ['lat' => 33.8478, 'lng' => -117.9348],
        '92834' => ['lat' => 33.8792, 'lng' => -117.9681],
        '92835' => ['lat' => 33.8823, 'lng' => -117.9109],
        '92821' => ['lat' => 33.9263, 'lng' => -117.8998], // Brea
        '92870' => ['lat' => 33.8722, 'lng' => -117.8511], // Placentia
        '92806' => ['lat' => 33.8359, 'lng' => -117.9132]  // Anaheim
    ];
    
    foreach ($deliveries as &$delivery) {
        $zip = substr($delivery['zip_code'], 0, 5);
        if (isset($zipCoordinates[$zip])) {
            $delivery['latitude'] = $zipCoordinates[$zip]['lat'] + (rand(-50, 50) / 10000); // Add small random offset
            $delivery['longitude'] = $zipCoordinates[$zip]['lng'] + (rand(-50, 50) / 10000);
        } else {
            // Default to restaurant location if zip not found
            $delivery['latitude'] = $RESTAURANT['lat'] + (rand(-20, 20) / 10000);
            $delivery['longitude'] = $RESTAURANT['lng'] + (rand(-20, 20) / 10000);
        }
    }
    
    return $deliveries;
}

// Fetch deliveries when date is selected
if (!empty($selected_date)) {
    $DELIVERIES = fetchDeliveriesForDate($selected_date, $selected_time);
    $DELIVERIES = addCoordinatesToDeliveries($DELIVERIES);
}

// Helper functions from original code
function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earthRadius = 3959; // miles
    $lat1 = deg2rad($lat1);
    $lng1 = deg2rad($lng1);
    $lat2 = deg2rad($lat2);
    $lng2 = deg2rad($lng2);
    
    $dlat = $lat2 - $lat1;
    $dlng = $lng2 - $lng1;
    
    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlng/2) * sin($dlng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return round($earthRadius * $c, 1);
}

function formatTime($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    }
    return $minutes . 'm';
}

function getAccessToken($serviceAccountFile) {
    if (!file_exists($serviceAccountFile)) {
        return null;
    }
    
    try {
        $serviceAccount = json_decode(file_get_contents($serviceAccountFile), true);
        if (!$serviceAccount) {
            return null;
        }
        
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $now = time();
        $payload = [
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ];
        
        $jwt = base64url_encode(json_encode($header)) . '.' . base64url_encode(json_encode($payload));
        
        $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
        if (!$privateKey) {
            return null;
        }
        
        openssl_sign($jwt, $signature, $privateKey, 'SHA256');
        $jwt .= '.' . base64url_encode($signature);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://oauth2.googleapis.com/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $tokenData = json_decode($response, true);
            return $tokenData['access_token'] ?? null;
        }
        
        return null;
    } catch (Exception $e) {
        return null;
    }
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Route optimization logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['optimize']) && !empty($DELIVERIES)) {
    $accessToken = getAccessToken($SERVICE_ACCOUNT_KEY_FILE);
    
    if (!$accessToken) {
        $API_ERROR = 'Failed to authenticate with Google API';
    } else {
        // Calculate capacity constraints
        $totalItems = array_sum(array_column($DELIVERIES, 'total_items'));
        $baseCapacityPerDriver = ceil($totalItems / $NUM_DRIVERS);
        $maxItemsPerDriver = $baseCapacityPerDriver + $CAPACITY_BUFFER;
        
        // Create shipments with load demands
        $currentDate = date('Y-m-d');
        $shipments = [];
        
        foreach ($DELIVERIES as $delivery) {
            $shipments[] = [
                'pickups' => [[
                    'arrivalLocation' => [
                        'latitude' => $RESTAURANT['lat'],
                        'longitude' => $RESTAURANT['lng']
                    ],
                    'duration' => '300s'
                ]],
                'deliveries' => [[
                    'arrivalLocation' => [
                        'latitude' => (float)$delivery['latitude'],
                        'longitude' => (float)$delivery['longitude']
                    ],
                    'duration' => '480s'
                ]],
                'label' => $delivery['customer_name'],
                'loadDemands' => [
                    'weight' => [
                        'amount' => (int)$delivery['total_items']
                    ]
                ]
            ];
        }
        
        // Create vehicles with constraints
        $vehicles = [];
        for ($i = 0; $i < $NUM_DRIVERS; $i++) {
            $vehicleConfig = [
                'startLocation' => [
                    'latitude' => $RESTAURANT['lat'],
                    'longitude' => $RESTAURANT['lng']
                ],
                'endLocation' => [
                    'latitude' => $RESTAURANT['lat'],
                    'longitude' => $RESTAURANT['lng']
                ],
                'label' => 'Driver ' . ($i + 1)
            ];
            
            if ($FORCE_DISTRIBUTION && $NUM_DRIVERS > 1) {
                $vehicleConfig['loadLimits'] = [
                    'weight' => [
                        'maxLoad' => $maxItemsPerDriver
                    ]
                ];
            }
            
            $vehicleConfig['costPerKilometer'] = $COST_PER_KM;
            $vehicleConfig['costPerTraveledHour'] = $COST_PER_HOUR;
            $vehicleConfig['fixedCost'] = $i === 0 ? $FIXED_COST_FIRST : $FIXED_COST_OTHERS;
            
            $vehicles[] = $vehicleConfig;
        }
        
        $requestData = [
            'model' => [
                'shipments' => $shipments,
                'vehicles' => $vehicles,
                'globalStartTime' => $currentDate . 'T16:00:00Z',
                'globalEndTime' => $currentDate . 'T22:00:00Z'
            ],
            'searchMode' => 'CONSUME_ALL_AVAILABLE_TIME',
            'timeout' => '30s'
        ];
        
        // Call Google API
        $endpoint = "https://routeoptimization.googleapis.com/v1/projects/{$PROJECT_ID}:optimizeTours";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ],
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $API_SUCCESS = true;
            $API_DATA = json_decode($response, true);
            
            // Process routes
            if (isset($API_DATA['routes'])) {
                $assignedCustomers = [];
                
                foreach ($API_DATA['routes'] as $routeIndex => $route) {
                    if (!isset($route['visits']) || empty($route['visits'])) {
                        continue;
                    }
                    
                    $routeSequence = [];
                    $prevLat = $RESTAURANT['lat'];
                    $prevLng = $RESTAURANT['lng'];
                    
                    $shipmentLabelMap = [];
                    foreach ($DELIVERIES as $index => $delivery) {
                        $shipmentLabelMap[$delivery['customer_name']] = $index;
                    }
                    
                    foreach ($route['visits'] as $visit) {
                        if (isset($visit['isPickup']) && $visit['isPickup']) {
                            continue;
                        }
                        
                        $customerIndex = null;
                        $customerName = $visit['shipmentLabel'] ?? '';
                        
                        if (isset($visit['shipmentIndex'])) {
                            $customerIndex = $visit['shipmentIndex'];
                        } elseif ($customerName && isset($shipmentLabelMap[$customerName])) {
                            $customerIndex = $shipmentLabelMap[$customerName];
                        }
                        
                        if ($customerIndex !== null && isset($DELIVERIES[$customerIndex])) {
                            $customer = $DELIVERIES[$customerIndex];
                            
                            $distance = calculateDistance(
                                $prevLat, $prevLng, 
                                $customer['latitude'], $customer['longitude']
                            );
                            
                            $routeSequence[] = [
                                'customer' => $customer,
                                'distance_from_prev' => $distance,
                                'start_time' => $visit['startTime'] ?? '',
                                'customer_index' => $customerIndex
                            ];
                            
                            $assignedCustomers[] = $customerIndex;
                            $prevLat = $customer['latitude'];
                            $prevLng = $customer['longitude'];
                        }
                    }
                    
                    if (!empty($routeSequence)) {
                        $ROUTES[] = [
                            'driver' => $route['vehicleLabel'] ?? 'Driver ' . ($routeIndex + 1),
                            'sequence' => $routeSequence,
                            'total_distance' => isset($route['metrics']['travelDistanceMeters']) ? 
                                round($route['metrics']['travelDistanceMeters'] * 0.000621371, 1) : 0,
                            'total_duration' => isset($route['metrics']['totalDuration']) ? 
                                $route['metrics']['totalDuration'] : '0s',
                            'travel_duration' => isset($route['metrics']['travelDuration']) ? 
                                $route['metrics']['travelDuration'] : '0s',
                            'visit_duration' => isset($route['metrics']['visitDuration']) ? 
                                $route['metrics']['visitDuration'] : '0s',
                            'start_time' => $route['vehicleStartTime'] ?? '',
                            'end_time' => $route['vehicleEndTime'] ?? '',
                            'delivery_count' => count($routeSequence)
                        ];
                    }
                }
                
                // Find unassigned customers
                for ($i = 0; $i < count($DELIVERIES); $i++) {
                    if (!in_array($i, $assignedCustomers)) {
                        $UNASSIGNED[] = $DELIVERIES[$i];
                    }
                }
            }
        } else {
            $errorData = json_decode($response, true);
            $API_ERROR = $errorData['error']['message'] ?? 'API request failed with HTTP ' . $httpCode;
        }
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Somdul Table - Delivery Route Optimizer</title>
    <link href="https://ydpschool.com/fonts/" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2');
            font-weight: normal;
            font-style: normal;
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: 'BaticaSans', Arial, sans-serif; 
            background: #ece8e1; 
            padding: 20px;
            line-height: 1.6;
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
        }
        
        .header {
            background: linear-gradient(135deg, #bd9379, #cf723a);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .section { 
            background: white; 
            margin-bottom: 20px; 
            padding: 20px; 
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h2 { 
            color: #bd9379; 
            margin-bottom: 15px;
            font-size: 1.5rem;
            border-bottom: 2px solid #cf723a;
            padding-bottom: 0.5rem;
        }
        
        .date-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .control-group {
            display: flex;
            flex-direction: column;
        }
        
        .control-group label {
            font-weight: bold;
            color: #bd9379;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .control-group small {
            color: #666;
            font-size: 0.8rem;
            margin-top: 2px;
        }
        
        select, input, button { 
            padding: 10px 12px; 
            border: 2px solid #adb89d; 
            border-radius: 6px;
            font-family: 'BaticaSans', Arial, sans-serif;
            font-size: 0.9rem;
        }
        
        button { 
            background: linear-gradient(135deg, #cf723a, #bd9379);
            color: white; 
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        button:hover { 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(207, 114, 58, 0.3);
        }
        
        .btn-load {
            background: linear-gradient(135deg, #adb89d, #9aa888);
            grid-column: 1 / -1;
            justify-self: center;
            min-width: 200px;
        }
        
        .deliveries-list { 
            max-height: 400px; 
            overflow-y: auto; 
            border: 1px solid #adb89d; 
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .delivery-item { 
            padding: 15px; 
            border-bottom: 1px solid #ece8e1; 
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .delivery-item:last-child { 
            border-bottom: none; 
        }
        
        .delivery-item:nth-child(even) {
            background: #f8f8f8;
        }
        
        .customer-info {
            flex: 1;
        }
        
        .customer-name {
            font-weight: bold;
            color: #cf723a;
            font-size: 1.1rem;
        }
        
        .delivery-details {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .item-count {
            background: #bd9379;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        .delivery-time {
            background: #adb89d;
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        
        .info-box {
            background: #f0f8ff;
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
            border-left: 4px solid #bd9379;
        }
        
        .warning-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #856404;
        }
        
        .success-box {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #28a745;
        }
        
        .status-success { 
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724; 
            padding: 15px; 
            border-radius: 8px;
            border-left: 4px solid #28a745;
        }
        
        .status-error { 
            background: linear-gradient(135deg, #f8d7da, #f1b0b7);
            color: #721c24; 
            padding: 15px; 
            border-radius: 8px;
            border-left: 4px solid #dc3545;
        }
        
        .optimize-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
            transform: scale(1.2);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-top: 5px;
        }
        
        .admin-section {
            background: #f8f9fa;
            border: 2px dashed #adb89d;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
        }
        
        .admin-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            cursor: pointer;
        }
        
        .admin-controls {
            display: none;
        }
        
        .admin-controls.show {
            display: block;
        }
        
        /* Route display styles */
        .route { 
            margin-bottom: 25px; 
            padding: 20px; 
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 12px;
            border-left: 6px solid #bd9379;
        }
        
        .route h3 { 
            color: #cf723a; 
            margin-bottom: 15px;
            font-size: 1.3rem;
        }
        
        .route-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .route-stat {
            background: white;
            padding: 10px 15px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #adb89d;
        }
        
        .route-stat .stat-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #bd9379;
        }
        
        .route-stat .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
        }
        
        .sequence-item { 
            padding: 12px 0; 
            border-left: 4px solid #bd9379; 
            padding-left: 15px; 
            margin-bottom: 10px;
            background: white;
            border-radius: 0 8px 8px 0;
            margin-left: 10px;
        }
        
        .sequence-number {
            display: inline-block;
            width: 25px;
            height: 25px;
            background: #cf723a;
            color: white;
            text-align: center;
            border-radius: 50%;
            font-weight: bold;
            line-height: 25px;
            margin-right: 10px;
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .summary-stat {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-top: 4px solid #bd9379;
        }
        
        .summary-stat .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #cf723a;
        }
        
        .summary-stat .stat-label {
            color: #6c757d;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .date-controls, .optimize-controls {
                grid-template-columns: 1fr;
            }
            
            .delivery-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .delivery-details {
                align-self: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚚 Somdul Table</h1>
            <p>Subscription Delivery Route Optimizer</p>
        </div>
        
        <!-- Date Selection -->
        <div class="section">
            <h2>📅 Select Delivery Date & Time</h2>
            <form method="POST">
                <div class="date-controls">
                    <div class="control-group">
                        <label for="delivery_date">Delivery Date</label>
                        <input type="date" name="delivery_date" id="delivery_date" 
                               value="<?= htmlspecialchars($selected_date) ?>" required>
                        <small>Select the date for delivery optimization</small>
                    </div>
                    
                    <div class="control-group">
                        <label for="delivery_time">Time Slot (Optional)</label>
                        <select name="delivery_time" id="delivery_time">
                            <option value="">All Time Slots</option>
                            <?php foreach (getDeliveryTimeSlots() as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $selected_time === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Filter by preferred delivery time</small>
                    </div>
                </div>
                
                <button type="submit" name="load_deliveries" class="btn-load">
                    📋 Load Deliveries for Selected Date
                </button>
            </form>
            
            <!-- Debug Section -->
            <?php if (!empty($selected_date)): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #17a2b8;">
                    <strong>🔍 Debug Info for <?= htmlspecialchars($selected_date) ?>:</strong><br>
                    <small>Day of week: <strong><?= strtolower(date('l', strtotime($selected_date))) ?></strong></small><br>
                    <small>Looking for delivery_days containing: "<strong>%<?= strtolower(date('l', strtotime($selected_date))) ?>%</strong>" OR "<strong>%<?= htmlspecialchars($selected_date) ?>%</strong>"</small><br>
                    <br>
                    <strong>Sample subscription data:</strong><br>
                    <div style="max-height: 150px; overflow-y: auto; background: white; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 0.8rem;">
                        <?php 
                        $debug_data = debugDeliveryDays($selected_date);
                        foreach ($debug_data as $debug_row): 
                        ?>
                            ID: <?= $debug_row['id'] ?> | Customer: <?= htmlspecialchars($debug_row['customer_name']) ?> | 
                            Delivery Days: <strong>"<?= htmlspecialchars($debug_row['delivery_days'] ?? 'NULL') ?>"</strong> | 
                            Status: <?= $debug_row['status'] ?><br>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Deliveries List -->
        <?php if (!empty($selected_date)): ?>
            <div class="section">
                <h2>📦 Deliveries for <?= date('l, F j, Y', strtotime($selected_date)) ?> (<?= count($DELIVERIES) ?>)</h2>
                
                <?php if (empty($DELIVERIES)): ?>
                    <div class="warning-box">
                        <strong>📭 No deliveries found</strong><br>
                        No active subscriptions scheduled for delivery on the selected date and time.
                    </div>
                <?php else: ?>
                    <div class="deliveries-list">
                        <?php foreach ($DELIVERIES as $delivery): ?>
                            <div class="delivery-item">
                                <div class="customer-info">
                                    <div class="customer-name"><?= htmlspecialchars($delivery['customer_name']) ?></div>
                                    <div style="color: #666; font-size: 0.9rem; margin-top: 4px;">
                                        📍 <?= htmlspecialchars($delivery['delivery_address']) ?>
                                    </div>
                                    <?php if (!empty($delivery['phone'])): ?>
                                        <div style="color: #666; font-size: 0.9rem;">
                                            📞 <?= htmlspecialchars($delivery['phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <!-- Debug: Show delivery_days -->
                                    <div style="color: #999; font-size: 0.8rem; font-style: italic;">
                                        Delivery Days: "<?= htmlspecialchars($delivery['delivery_days'] ?? 'NULL') ?>"
                                    </div>
                                </div>
                                <div class="delivery-details">
                                    <?php if (!empty($delivery['delivery_time'])): ?>
                                        <span class="delivery-time"><?= htmlspecialchars($delivery['delivery_time']) ?></span>
                                    <?php endif; ?>
                                    <span class="item-count"><?= $delivery['total_items'] ?> items</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="info-box">
                        <strong>📊 Delivery Summary:</strong>
                        Total Items: <?= array_sum(array_column($DELIVERIES, 'total_items')) ?> • 
                        Avg Items/Customer: <?= round(array_sum(array_column($DELIVERIES, 'total_items')) / count($DELIVERIES), 1) ?> •
                        Unique Zip Codes: <?= count(array_unique(array_column($DELIVERIES, 'zip_code'))) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Route Optimization -->
        <?php if (!empty($DELIVERIES)): ?>
            <div class="section">
                <h2>🎯 Route Optimization Settings</h2>
                <form method="POST">
                    <!-- Hidden fields to preserve date selection -->
                    <input type="hidden" name="delivery_date" value="<?= htmlspecialchars($selected_date) ?>">
                    <input type="hidden" name="delivery_time" value="<?= htmlspecialchars($selected_time) ?>">
                    
                    <div class="optimize-controls">
                        <div class="control-group">
                            <label for="num_drivers">Number of Drivers</label>
                            <select name="num_drivers" id="num_drivers">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i == $NUM_DRIVERS ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <small>Available drivers for delivery</small>
                        </div>
                        
                        <div class="control-group">
                            <label>Share Work Equally</label>
                            <div class="checkbox-group">
                                <input type="checkbox" name="force_distribution" id="force_distribution" <?= $FORCE_DISTRIBUTION ? 'checked' : '' ?>>
                                <label for="force_distribution">Force equal distribution</label>
                            </div>
                            <small>Ensures all drivers get deliveries</small>
                        </div>
                    </div>
                    
                    <!-- Advanced Settings -->
                    <div class="admin-section">
                        <div class="admin-header" onclick="toggleAdmin()">
                            <span style="font-size: 1.2rem; margin-right: 10px;">⚙️</span>
                            <strong>Advanced Settings</strong>
                            <span style="margin-left: auto; font-size: 1.2rem;" id="adminToggle">▼</span>
                        </div>
                        
                        <div class="admin-controls" id="adminControls">
                            <div class="optimize-controls">
                                <div class="control-group">
                                    <label for="capacity_buffer">Extra Items Per Driver</label>
                                    <input type="number" name="capacity_buffer" id="capacity_buffer" 
                                           value="<?= $CAPACITY_BUFFER ?>" min="0" max="10" step="1">
                                    <small>Extra capacity beyond equal split</small>
                                </div>
                                
                                <div class="control-group">
                                    <label for="cost_per_km">Distance Priority</label>
                                    <input type="number" name="cost_per_km" id="cost_per_km" 
                                           value="<?= $COST_PER_KM ?>" min="0.1" max="10" step="0.1">
                                    <small>Higher = prioritize shorter routes</small>
                                </div>
                                
                                <div class="control-group">
                                    <label for="cost_per_hour">Speed Priority</label>
                                    <input type="number" name="cost_per_hour" id="cost_per_hour" 
                                           value="<?= $COST_PER_HOUR ?>" min="10" max="100" step="5">
                                    <small>Higher = prioritize faster completion</small>
                                </div>
                                
                                <div class="control-group">
                                    <label for="fixed_cost_first">Driver 1 Penalty</label>
                                    <input type="number" name="fixed_cost_first" id="fixed_cost_first" 
                                           value="<?= $FIXED_COST_FIRST ?>" min="0" max="500" step="10">
                                    <small>Penalty for using only driver 1</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="optimize" style="grid-column: 1 / -1; justify-self: center; min-width: 250px;">
                        🚀 Optimize Routes for <?= count($DELIVERIES) ?> Deliveries
                    </button>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- API Status -->
        <?php if ($API_SUCCESS || $API_ERROR): ?>
            <div class="section">
                <?php if ($API_SUCCESS): ?>
                    <div class="status-success">✅ Google Route Optimization API connected successfully!</div>
                <?php else: ?>
                    <div class="status-error">❌ Error: <?= htmlspecialchars($API_ERROR) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Optimization Results -->
        <?php if (!empty($ROUTES) && isset($API_DATA['metrics'])): ?>
            <div class="section">
                <h2>📊 Optimization Results</h2>
                <div class="summary-stats">
                    <div class="summary-stat">
                        <div class="stat-value"><?= count($ROUTES) ?></div>
                        <div class="stat-label">Active Drivers</div>
                    </div>
                    <div class="summary-stat">
                        <div class="stat-value"><?= round($API_DATA['metrics']['aggregatedRouteMetrics']['travelDistanceMeters'] * 0.000621371, 1) ?></div>
                        <div class="stat-label">Total Miles</div>
                    </div>
                    <div class="summary-stat">
                        <div class="stat-value"><?= formatTime(intval(str_replace('s', '', $API_DATA['metrics']['aggregatedRouteMetrics']['totalDuration']))) ?></div>
                        <div class="stat-label">Total Time</div>
                    </div>
                    <div class="summary-stat">
                        <div class="stat-value"><?= $API_DATA['metrics']['aggregatedRouteMetrics']['performedShipmentCount'] ?></div>
                        <div class="stat-label">Deliveries</div>
                    </div>
                </div>
                
                <?php if (count($ROUTES) > 1): ?>
                    <div class="success-box">
                        <strong>✅ Multiple Drivers Working:</strong> 
                        <?php 
                        $itemCounts = [];
                        foreach ($ROUTES as $route) {
                            $totalItems = 0;
                            foreach ($route['sequence'] as $stop) {
                                $totalItems += $stop['customer']['total_items'];
                            }
                            $itemCounts[] = $totalItems;
                        }
                        echo "Workload: " . implode(', ', array_map(function($count, $index) {
                            return "Driver " . ($index + 1) . " (" . $count . " items)";
                        }, $itemCounts, array_keys($itemCounts)));
                        ?>
                    </div>
                <?php elseif (count($ROUTES) === 1 && $NUM_DRIVERS > 1): ?>
                    <div class="warning-box">
                        <strong>⚠️ Single Driver Used:</strong> 
                        Only one driver was assigned. Enable "Share Work Equally" to force distribution.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Route Details -->
            <div class="section">
                <h2>🗺️ Optimized Routes</h2>
                <?php foreach ($ROUTES as $route): ?>
                    <div class="route">
                        <h3><?= htmlspecialchars($route['driver']) ?></h3>
                        
                        <div class="route-stats">
                            <div class="route-stat">
                                <div class="stat-value"><?= $route['total_distance'] ?></div>
                                <div class="stat-label">Miles</div>
                            </div>
                            <div class="route-stat">
                                <div class="stat-value"><?= $route['delivery_count'] ?></div>
                                <div class="stat-label">Stops</div>
                            </div>
                            <div class="route-stat">
                                <div class="stat-value">
                                    <?php
                                    $totalItems = 0;
                                    foreach ($route['sequence'] as $stop) {
                                        $totalItems += $stop['customer']['total_items'];
                                    }
                                    echo $totalItems;
                                    ?>
                                </div>
                                <div class="stat-label">Items</div>
                            </div>
                            <div class="route-stat">
                                <div class="stat-value"><?= formatTime(intval(str_replace('s', '', $route['total_duration']))) ?></div>
                                <div class="stat-label">Total Time</div>
                            </div>
                            <?php if ($route['start_time']): ?>
                            <div class="route-stat">
                                <div class="stat-value"><?= date('g:i A', strtotime($route['start_time'])) ?></div>
                                <div class="stat-label">Start</div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($route['sequence'])): ?>
                            <?php foreach ($route['sequence'] as $index => $stop): ?>
                                <div class="sequence-item">
                                    <span class="sequence-number"><?= ($index + 1) ?></span>
                                    <strong><?= htmlspecialchars($stop['customer']['customer_name']) ?></strong>
                                    <small style="color: #bd9379; font-weight: bold;">(<?= $stop['distance_from_prev'] ?> mi from previous)</small>
                                    <?php if ($stop['start_time']): ?>
                                        <small style="color: #6c757d;"> - ETA: <?= date('g:i A', strtotime($stop['start_time'])) ?></small>
                                    <?php endif; ?>
                                    <br>
                                    <small style="color: #666;">📍 <?= htmlspecialchars($stop['customer']['delivery_address']) ?></small>
                                    <small style="color: #cf723a; font-weight: bold;"> • <?= $stop['customer']['total_items'] ?> items</small>
                                    <?php if (!empty($stop['customer']['phone'])): ?>
                                        <small style="color: #999;"> • 📞 <?= htmlspecialchars($stop['customer']['phone']) ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Unassigned Deliveries -->
        <?php if (!empty($UNASSIGNED)): ?>
            <div class="section">
                <div class="warning-box">
                    <h2 style="color: #856404; border: none; margin-bottom: 1rem;">⚠️ Unassigned Deliveries</h2>
                    <?php foreach ($UNASSIGNED as $customer): ?>
                        <div style="margin-bottom: 0.5rem;">
                            <strong><?= htmlspecialchars($customer['customer_name']) ?></strong> - 
                            <?= htmlspecialchars($customer['delivery_address']) ?>
                            <span style="color: #856404;"> (<?= $customer['total_items'] ?> items)</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleAdmin() {
            const controls = document.getElementById('adminControls');
            const toggle = document.getElementById('adminToggle');
            
            if (controls.classList.contains('show')) {
                controls.classList.remove('show');
                toggle.textContent = '▼';
            } else {
                controls.classList.add('show');
                toggle.textContent = '▲';
            }
        }
        
        // Auto-submit form when date changes
        document.getElementById('delivery_date').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('delivery_time').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>