<?php
/**
 * Somdul Table - Subscription Route Optimizer with Route Assignment
 * Integrates database subscriptions with Google Route Optimization API
 * Colors: #bd9379, #ece8e1, #adb89d, #cf723a and white
 * UPDATED: Combines multiple subscriptions from the same user into one delivery
 * ENHANCED: Added route assignment functionality
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
$AVAILABLE_RIDERS = [];

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

// NEW: Get available riders/drivers
function getAvailableRiders() {
    $connection = getDatabaseConnection();
    
    $sql = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.phone,
            u.status,
            COUNT(s.id) as current_assignments
        FROM users u
        LEFT JOIN subscriptions s ON u.id = s.assigned_rider_id AND s.status = 'active'
        WHERE u.role = 'rider' 
        AND u.status = 'active'
        GROUP BY u.id, u.first_name, u.last_name, u.phone, u.status
        ORDER BY current_assignments ASC, u.first_name ASC
    ";
    
    try {
        $result = mysqli_query($connection, $sql);
        $riders = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $riders[] = $row;
        }
        
        return $riders;
    } catch (Exception $e) {
        error_log("Error fetching riders: " . $e->getMessage());
        return [];
    }
}

// NEW: Assign entire route to a rider
function assignRouteToRider($route_customers, $rider_id) {
    $connection = getDatabaseConnection();
    
    try {
        // Begin transaction
        mysqli_begin_transaction($connection);
        
        $success_count = 0;
        $error_messages = [];
        
        foreach ($route_customers as $customer) {
            // Check if customer has subscription_ids array or single ID
            $subscription_ids = isset($customer['subscription_ids']) ? $customer['subscription_ids'] : [$customer['subscription_id']];
            
            foreach ($subscription_ids as $subscription_id) {
                $sql = "UPDATE subscriptions SET assigned_rider_id = ?, updated_at = NOW() WHERE id = ?";
                $stmt = mysqli_prepare($connection, $sql);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "ii", $rider_id, $subscription_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success_count++;
                    } else {
                        $error_messages[] = "Failed to assign subscription ID: $subscription_id";
                    }
                    
                    mysqli_stmt_close($stmt);
                } else {
                    $error_messages[] = "Failed to prepare statement for subscription ID: $subscription_id";
                }
            }
        }
        
        if (empty($error_messages)) {
            // Commit transaction
            mysqli_commit($connection);
            
            // Get rider name for response
            $rider_sql = "SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?";
            $rider_stmt = mysqli_prepare($connection, $rider_sql);
            mysqli_stmt_bind_param($rider_stmt, "i", $rider_id);
            mysqli_stmt_execute($rider_stmt);
            $rider_result = mysqli_stmt_get_result($rider_stmt);
            $rider_data = mysqli_fetch_assoc($rider_result);
            $rider_name = $rider_data['name'] ?? 'Unknown';
            mysqli_stmt_close($rider_stmt);
            
            return [
                'success' => true,
                'message' => "Route successfully assigned to {$rider_name}. {$success_count} subscriptions updated.",
                'assignments_made' => $success_count
            ];
        } else {
            // Rollback transaction
            mysqli_rollback($connection);
            
            return [
                'success' => false,
                'message' => 'Some assignments failed: ' . implode(', ', $error_messages)
            ];
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($connection);
        
        return [
            'success' => false,
            'message' => 'Error assigning route: ' . $e->getMessage()
        ];
    }
}

// Handle AJAX requests for route assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'assign_route_to_rider':
                // Decode route customers data
                $route_customers = json_decode($_POST['route_customers'], true);
                $rider_id = (int)$_POST['rider_id'];
                
                if (empty($route_customers) || $rider_id <= 0) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid route data or rider ID'
                    ]);
                    exit;
                }
                
                $result = assignRouteToRider($route_customers, $rider_id);
                echo json_encode($result);
                exit;
                
            default:
                echo json_encode([
                    'success' => false,
                    'message' => 'Unknown action: ' . $_POST['action']
                ]);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error processing request: ' . $e->getMessage()
        ]);
        exit;
    }
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
            u.phone,
            SUM(CASE WHEN s.total_amount IS NOT NULL AND s.total_amount > 0 
                THEN GREATEST(1, ROUND(s.total_amount / 15)) 
                ELSE 1 END) as total_items,
            COUNT(s.id) as subscription_count
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
    
    $sql .= " GROUP BY s.user_id, u.first_name, u.last_name, u.delivery_address, u.city, u.state, u.zip_code, u.phone";
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
            // Get all subscription IDs for this user
            $subscription_ids = [];
            $delivery_times = [];
            $assigned_riders = [];
            
            // Get detailed subscription info for this user
            $detail_sql = "
                SELECT 
                    s.id,
                    s.preferred_delivery_time,
                    s.assigned_rider_id,
                    s.total_amount,
                    s.delivery_days
                FROM subscriptions s
                WHERE s.user_id = ? AND s.status = 'active'
                AND (
                    s.delivery_days LIKE ? 
                    OR s.delivery_days LIKE ?
                    OR s.delivery_days IS NULL 
                    OR s.delivery_days = ''
                )
            ";
            
            if (!empty($time_slot)) {
                $detail_sql .= " AND s.preferred_delivery_time = ?";
            }
            
            $detail_stmt = mysqli_prepare($connection, $detail_sql);
            
            if (!empty($time_slot)) {
                $dayOfWeekParam = "%$dayOfWeek%";
                $dateParam = "%$date%";
                mysqli_stmt_bind_param($detail_stmt, "isss", $row['user_id'], $dayOfWeekParam, $dateParam, $time_slot);
            } else {
                $dayOfWeekParam = "%$dayOfWeek%";
                $dateParam = "%$date%";
                mysqli_stmt_bind_param($detail_stmt, "iss", $row['user_id'], $dayOfWeekParam, $dateParam);
            }
            
            mysqli_stmt_execute($detail_stmt);
            $detail_result = mysqli_stmt_get_result($detail_stmt);
            
            $total_calculated_items = 0;
            while ($detail_row = mysqli_fetch_assoc($detail_result)) {
                $subscription_ids[] = $detail_row['id'];
                if (!empty($detail_row['preferred_delivery_time'])) {
                    $delivery_times[] = $detail_row['preferred_delivery_time'];
                }
                if (!empty($detail_row['assigned_rider_id'])) {
                    $assigned_riders[] = $detail_row['assigned_rider_id'];
                }
                
                // Calculate items for this subscription
                $items_for_subscription = 1; // Default
                if ($detail_row['total_amount'] && $detail_row['total_amount'] > 0) {
                    $items_for_subscription = max(1, round($detail_row['total_amount'] / 15));
                }
                $total_calculated_items += $items_for_subscription;
            }
            
            mysqli_stmt_close($detail_stmt);
            
            // Determine the primary delivery time (most common or first non-empty)
            $primary_delivery_time = '';
            if (!empty($delivery_times)) {
                $time_counts = array_count_values($delivery_times);
                arsort($time_counts);
                $primary_delivery_time = array_key_first($time_counts);
            }
            
            // Determine assigned rider (if any - use most common or first)
            $primary_rider_id = null;
            if (!empty($assigned_riders)) {
                $rider_counts = array_count_values($assigned_riders);
                arsort($rider_counts);
                $primary_rider_id = array_key_first($rider_counts);
            }
            
            $deliveries[] = [
                'subscription_ids' => $subscription_ids, // Array of all subscription IDs for this user
                'user_id' => $row['user_id'],
                'customer_name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'delivery_address' => $row['delivery_address'] . ', ' . $row['city'] . ', ' . $row['state'] . ' ' . $row['zip_code'],
                'phone' => $row['phone'] ?? '',
                'zip_code' => $row['zip_code'],
                'total_items' => $total_calculated_items, // Combined total from all subscriptions
                'subscription_count' => count($subscription_ids), // Number of subscriptions combined
                'delivery_time' => $primary_delivery_time,
                'assigned_rider_id' => $primary_rider_id,
                'latitude' => null, // Will be populated by geocoding if needed
                'longitude' => null,
                'delivery_days' => $row['delivery_days'] ?? '' // For debugging (from first subscription)
            ];
        }
        
        mysqli_stmt_close($stmt);
        return $deliveries;
        
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        return [];
    }
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
    $AVAILABLE_RIDERS = getAvailableRiders(); // Fetch available riders
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
                            'delivery_count' => count($routeSequence),
                            'route_index' => $routeIndex // Add route index for assignment purposes
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
        
        .subscription-count {
            background: #adb89d;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            margin-left: 8px;
            font-weight: normal;
        }
        
        .delivery-details {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
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
        
        /* NEW: Route Assignment Styles */
        .route-assignment {
            background: #f0f8ff;
            border: 2px solid #bd9379;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .assignment-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .assignment-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .rider-select {
            min-width: 200px;
            background: white;
            border: 2px solid #adb89d;
        }
        
        .assign-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .assign-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        .assign-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .assignment-info {
            font-size: 0.9rem;
            color: #666;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .loading-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #cf723a;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
            
            .assignment-controls {
                flex-direction: column;
                gap: 10px;
                width: 100%;
            }
            
            .rider-select {
                min-width: 100%;
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
        </div>
        
        <!-- Deliveries List -->
        <?php if (!empty($selected_date)): ?>
            <div class="section">
                <h2>📦 Combined Deliveries for <?= date('l, F j, Y', strtotime($selected_date)) ?> (<?= count($DELIVERIES) ?> customers)</h2>
                
                <?php if (empty($DELIVERIES)): ?>
                    <div class="warning-box">
                        <strong>🔭 No deliveries found</strong><br>
                        No active subscriptions scheduled for delivery on the selected date and time.
                    </div>
                <?php else: ?>
                    <div class="deliveries-list">
                        <?php foreach ($DELIVERIES as $delivery): ?>
                            <div class="delivery-item">
                                <div class="customer-info">
                                    <div class="customer-name">
                                        <?= htmlspecialchars($delivery['customer_name']) ?>
                                        <?php if ($delivery['subscription_count'] > 1): ?>
                                            <span class="subscription-count"><?= $delivery['subscription_count'] ?> subscriptions</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="color: #666; font-size: 0.9rem; margin-top: 4px;">
                                        📍 <?= htmlspecialchars($delivery['delivery_address']) ?>
                                    </div>
                                    <?php if (!empty($delivery['phone'])): ?>
                                        <div style="color: #666; font-size: 0.9rem;">
                                            📞 <?= htmlspecialchars($delivery['phone']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <!-- Debug: Show subscription IDs -->
                                    <div style="color: #999; font-size: 0.8rem; font-style: italic;">
                                        Subscription IDs: <?= implode(', ', $delivery['subscription_ids']) ?>
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
                        Total Customers: <?= count($DELIVERIES) ?> • 
                        Total Items: <?= array_sum(array_column($DELIVERIES, 'total_items')) ?> • 
                        Total Subscriptions: <?= array_sum(array_column($DELIVERIES, 'subscription_count')) ?> •
                        Avg Items/Customer: <?= round(array_sum(array_column($DELIVERIES, 'total_items')) / count($DELIVERIES), 1) ?> •
                        Unique Zip Codes: <?= count(array_unique(array_column($DELIVERIES, 'zip_code'))) ?>
                    </div>
                    
                    <?php 
                    $multipleSubscriptions = array_filter($DELIVERIES, function($d) { return $d['subscription_count'] > 1; });
                    if (!empty($multipleSubscriptions)): 
                    ?>
                        <div class="success-box">
                            <strong>✅ Order Consolidation Active:</strong> 
                            <?= count($multipleSubscriptions) ?> customers have multiple subscriptions combined into single deliveries, 
                            reducing total stops from <?= array_sum(array_column($DELIVERIES, 'subscription_count')) ?> to <?= count($DELIVERIES) ?>.
                        </div>
                    <?php endif; ?>
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
                        🚀 Optimize Routes for <?= count($DELIVERIES) ?> Combined Deliveries
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
                        <div class="stat-label">Customers</div>
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
            
            <!-- Route Details with Assignment -->
            <div class="section">
                <h2>🗺️ Optimized Routes</h2>
                <?php foreach ($ROUTES as $routeIndex => $route): ?>
                    <div class="route" id="route-<?= $routeIndex ?>">
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
                        
                        <!-- NEW: Route Assignment Section -->
                        <div class="route-assignment">
                            <div class="assignment-header">
                                <h4><i class="fas fa-user-plus"></i> Assign This Route to Rider</h4>
                                <div class="assignment-info">
                                    Assign all <?= $route['delivery_count'] ?> customers in this route to a single rider
                                </div>
                            </div>
                            
                            <div class="assignment-controls">
                                <select class="rider-select" id="rider-select-<?= $routeIndex ?>">
                                    <option value="">Select Rider/Driver</option>
                                    <?php foreach ($AVAILABLE_RIDERS as $rider): ?>
                                        <option value="<?= $rider['id'] ?>">
                                            <?= htmlspecialchars($rider['first_name'] . ' ' . $rider['last_name']) ?> 
                                            (<?= $rider['current_assignments'] ?> current assignments)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <button class="assign-btn" onclick="assignRouteToRider(<?= $routeIndex ?>)" id="assign-btn-<?= $routeIndex ?>" disabled>
                                    <i class="fas fa-check"></i> Assign Route
                                </button>
                            </div>
                        </div>
                        
                        <?php if (!empty($route['sequence'])): ?>
                            <h5 style="margin-top: 1rem; margin-bottom: 0.5rem;">Route Sequence:</h5>
                            <?php foreach ($route['sequence'] as $index => $stop): ?>
                                <div class="sequence-item" data-customer-data='<?= json_encode($stop['customer']) ?>'>
                                    <span class="sequence-number"><?= ($index + 1) ?></span>
                                    <strong><?= htmlspecialchars($stop['customer']['customer_name']) ?></strong>
                                    <?php if ($stop['customer']['subscription_count'] > 1): ?>
                                        <span style="background: #adb89d; color: white; padding: 2px 6px; border-radius: 8px; font-size: 0.7rem; margin-left: 8px;">
                                            <?= $stop['customer']['subscription_count'] ?> subs
                                        </span>
                                    <?php endif; ?>
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
                                    <br>
                                    <small style="color: #999; font-style: italic;">
                                        Subscription IDs: <?= implode(', ', $stop['customer']['subscription_ids']) ?>
                                    </small>
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
                            <?php if ($customer['subscription_count'] > 1): ?>
                                <span style="color: #856404;"> [<?= $customer['subscription_count'] ?> subscriptions combined]</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <p>Processing assignment...</p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        
        // NEW: Route Assignment Functions
        
        // Enable/disable assign button based on rider selection
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to all rider selects
            <?php foreach ($ROUTES as $routeIndex => $route): ?>
                document.getElementById('rider-select-<?= $routeIndex ?>').addEventListener('change', function() {
                    const assignBtn = document.getElementById('assign-btn-<?= $routeIndex ?>');
                    assignBtn.disabled = this.value === '';
                });
            <?php endforeach; ?>
        });
        
        function assignRouteToRider(routeIndex) {
            const riderSelect = document.getElementById(`rider-select-${routeIndex}`);
            const riderId = riderSelect.value;
            
            if (!riderId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Rider Selected',
                    text: 'Please select a rider before assigning the route.'
                });
                return;
            }
            
            // Get rider name for confirmation
            const riderName = riderSelect.options[riderSelect.selectedIndex].text;
            
            // Collect all customers in this route
            const routeElement = document.getElementById(`route-${routeIndex}`);
            const customerElements = routeElement.querySelectorAll('.sequence-item[data-customer-data]');
            
            const routeCustomers = [];
            customerElements.forEach(element => {
                try {
                    const customerData = JSON.parse(element.getAttribute('data-customer-data'));
                    routeCustomers.push(customerData);
                } catch (e) {
                    console.error('Error parsing customer data:', e);
                }
            });
            
            if (routeCustomers.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'No Customers Found',
                    text: 'No customers found in this route to assign.'
                });
                return;
            }
            
            // Show confirmation dialog
            Swal.fire({
                title: 'Confirm Route Assignment',
                html: `
                    <p>Assign <strong>${routeCustomers.length} customers</strong> to <strong>${riderName}</strong>?</p>
                    <div style="text-align: left; margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                        <strong>Customers to assign:</strong><br>
                        ${routeCustomers.map(customer => `• ${customer.customer_name} (${customer.total_items} items)`).join('<br>')}
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Assign Route',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    performRouteAssignment(routeIndex, riderId, routeCustomers, riderName);
                }
            });
        }
        
        function performRouteAssignment(routeIndex, riderId, routeCustomers, riderName) {
            // Show loading overlay
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            // Prepare the data for the API call
            const formData = new FormData();
            formData.append('action', 'assign_route_to_rider');
            formData.append('rider_id', riderId);
            formData.append('route_customers', JSON.stringify(routeCustomers));
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Hide loading overlay
                document.getElementById('loadingOverlay').style.display = 'none';
                
                if (data.success) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Route Assigned Successfully!',
                        text: data.message,
                        timer: 3000,
                        showConfirmButton: false
                    }).then(() => {
                        // Disable the assignment controls for this route
                        const riderSelect = document.getElementById(`rider-select-${routeIndex}`);
                        const assignBtn = document.getElementById(`assign-btn-${routeIndex}`);
                        const assignmentDiv = riderSelect.closest('.route-assignment');
                        
                        // Update the assignment section to show completion
                        assignmentDiv.innerHTML = `
                            <div class="assignment-header">
                                <h4 style="color: #28a745;"><i class="fas fa-check-circle"></i> Route Assigned Successfully</h4>
                                <div class="assignment-info" style="color: #28a745;">
                                    All ${routeCustomers.length} customers assigned to ${riderName}
                                </div>
                            </div>
                            <div style="background: #d4edda; padding: 10px; border-radius: 6px; color: #155724;">
                                <strong>✅ Assignment Complete:</strong> ${data.assignments_made} subscriptions updated
                            </div>
                        `;
                    });
                } else {
                    // Show error message
                    Swal.fire({
                        icon: 'error',
                        title: 'Assignment Failed',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                // Hide loading overlay
                document.getElementById('loadingOverlay').style.display = 'none';
                
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Network Error',
                    text: 'Failed to assign route. Please check your connection and try again.'
                });
            });
        }
    </script>
</body>
</html>