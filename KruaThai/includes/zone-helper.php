<?php
/**
 * Krua Thai - Complete Zone Helper Functions
 * File: includes/zone-helper.php
 * Features: Helper functions สำหรับจัดการโซนการส่งของ
 * Status: PRODUCTION READY ✅
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

/**
 * Get zone information by zip code
 * @param PDO $pdo Database connection
 * @param string $zip_code 5-digit zip code
 * @return array Zone information or null if not found
 */
function getZoneByZipCode($pdo, $zip_code) {
    try {
        $stmt = $pdo->prepare("
            SELECT dz.*, 
                   COUNT(DISTINCT o.id) as current_orders_today,
                   COUNT(DISTINCT CASE WHEN o.status IN ('pending', 'confirmed', 'preparing', 'ready', 'out_for_delivery') 
                                      THEN o.id END) as active_orders_today
            FROM delivery_zones dz
            LEFT JOIN orders o ON JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(?)) 
                                AND o.delivery_date = CURDATE()
            WHERE JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(?)) 
            AND dz.is_active = 1
            GROUP BY dz.id
            LIMIT 1
        ");
        
        $stmt->execute([$zip_code, $zip_code]);
        $zone = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($zone) {
            // Decode JSON fields
            $zone['zip_codes_array'] = json_decode($zone['zip_codes'], true);
            $zone['delivery_time_slots_array'] = json_decode($zone['delivery_time_slots'], true);
            
            // Calculate availability percentage
            $zone['capacity_percentage'] = ($zone['max_orders_per_day'] > 0) 
                ? round(($zone['current_orders_today'] / $zone['max_orders_per_day']) * 100, 1)
                : 0;
                
            // Check if zone is at capacity
            $zone['is_at_capacity'] = $zone['current_orders_today'] >= $zone['max_orders_per_day'];
            
            return $zone;
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error in getZoneByZipCode: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if delivery is available for a specific zip code and date
 * @param PDO $pdo Database connection
 * @param string $zip_code 5-digit zip code
 * @param string $delivery_date Date in Y-m-d format (optional, defaults to today)
 * @param string $time_slot Preferred time slot (optional)
 * @return array Availability status and details
 */
function checkDeliveryAvailability($pdo, $zip_code, $delivery_date = null, $time_slot = null) {
    try {
        if (!$delivery_date) {
            $delivery_date = date('Y-m-d');
        }
        
        // Get zone information
        $zone = getZoneByZipCode($pdo, $zip_code);
        
        if (!$zone) {
            return [
                'available' => false,
                'reason' => 'no_coverage',
                'message' => 'No delivery service available for this area',
                'zone' => null
            ];
        }
        
        // Check if zone is active
        if (!$zone['is_active']) {
            return [
                'available' => false,
                'reason' => 'zone_inactive',
                'message' => 'Delivery service temporarily unavailable in this area',
                'zone' => $zone
            ];
        }
        
        // Check zone capacity for the date
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as orders_count 
            FROM orders o
            WHERE JSON_CONTAINS(
                (SELECT zip_codes FROM delivery_zones WHERE id = ?), 
                JSON_QUOTE(?)
            )
            AND o.delivery_date = ?
            AND o.status NOT IN ('cancelled')
        ");
        
        $stmt->execute([$zone['id'], $zip_code, $delivery_date]);
        $orders_on_date = $stmt->fetchColumn();
        
        if ($orders_on_date >= $zone['max_orders_per_day']) {
            return [
                'available' => false,
                'reason' => 'capacity_full',
                'message' => 'Delivery slots full for this date',
                'zone' => $zone,
                'current_orders' => $orders_on_date,
                'max_orders' => $zone['max_orders_per_day']
            ];
        }
        
        // Check specific time slot if provided
        if ($time_slot) {
            $available_slots = getDeliveryTimeSlots($pdo, $zip_code, $delivery_date);
            if (!in_array($time_slot, $available_slots)) {
                return [
                    'available' => false,
                    'reason' => 'timeslot_unavailable',
                    'message' => 'Selected time slot not available',
                    'zone' => $zone,
                    'available_slots' => $available_slots
                ];
            }
        }
        
        // Check if delivery date is not in the past
        if (strtotime($delivery_date) < strtotime(date('Y-m-d'))) {
            return [
                'available' => false,
                'reason' => 'past_date',
                'message' => 'Cannot schedule delivery for past dates',
                'zone' => $zone
            ];
        }
        
        // Check if delivery date is not too far in future (e.g., max 30 days)
        $max_days_ahead = 30;
        if (strtotime($delivery_date) > strtotime("+{$max_days_ahead} days")) {
            return [
                'available' => false,
                'reason' => 'too_far_future',
                'message' => "Cannot schedule delivery more than {$max_days_ahead} days in advance",
                'zone' => $zone
            ];
        }
        
        return [
            'available' => true,
            'reason' => 'available',
            'message' => 'Delivery available',
            'zone' => $zone,
            'current_orders' => $orders_on_date,
            'max_orders' => $zone['max_orders_per_day'],
            'remaining_slots' => $zone['max_orders_per_day'] - $orders_on_date
        ];
        
    } catch (Exception $e) {
        error_log("Error in checkDeliveryAvailability: " . $e->getMessage());
        return [
            'available' => false,
            'reason' => 'system_error',
            'message' => 'System error checking availability',
            'zone' => null
        ];
    }
}

/**
 * Get available delivery time slots for a zone and date
 * @param PDO $pdo Database connection
 * @param string $zip_code 5-digit zip code
 * @param string $delivery_date Date in Y-m-d format (optional, defaults to today)
 * @return array Available time slots
 */
function getDeliveryTimeSlots($pdo, $zip_code, $delivery_date = null) {
    try {
        if (!$delivery_date) {
            $delivery_date = date('Y-m-d');
        }
        
        // Get zone information
        $zone = getZoneByZipCode($pdo, $zip_code);
        
        if (!$zone || !$zone['is_active']) {
            return [];
        }
        
        $available_slots = $zone['delivery_time_slots_array'] ?: [];
        
        // If delivery date is today, filter out past time slots
        if ($delivery_date === date('Y-m-d')) {
            $current_time = date('H:i');
            $filtered_slots = [];
            
            foreach ($available_slots as $slot) {
                // Extract start time from slot (e.g., "09:00-12:00" -> "09:00")
                $slot_start = explode('-', $slot)[0];
                
                // Add buffer time (e.g., 2 hours before slot start)
                $buffer_minutes = 120;
                $slot_start_with_buffer = date('H:i', strtotime($slot_start) - ($buffer_minutes * 60));
                
                if ($current_time <= $slot_start_with_buffer) {
                    $filtered_slots[] = $slot;
                }
            }
            
            $available_slots = $filtered_slots;
        }
        
        // Check capacity for each time slot (if you want to limit orders per slot)
        $max_orders_per_slot = ceil($zone['max_orders_per_day'] / count($available_slots ?: [1]));
        $final_slots = [];
        
        foreach ($available_slots as $slot) {
            // Count current orders for this slot
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM orders o
                WHERE JSON_CONTAINS(
                    (SELECT zip_codes FROM delivery_zones WHERE id = ?), 
                    JSON_QUOTE(?)
                )
                AND o.delivery_date = ?
                AND o.delivery_time_slot = ?
                AND o.status NOT IN ('cancelled')
            ");
            
            $stmt->execute([$zone['id'], $zip_code, $delivery_date, $slot]);
            $slot_orders = $stmt->fetchColumn();
            
            if ($slot_orders < $max_orders_per_slot) {
                $final_slots[] = [
                    'slot' => $slot,
                    'current_orders' => $slot_orders,
                    'max_orders' => $max_orders_per_slot,
                    'remaining' => $max_orders_per_slot - $slot_orders
                ];
            }
        }
        
        return $final_slots;
        
    } catch (Exception $e) {
        error_log("Error in getDeliveryTimeSlots: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate shipping cost for an order
 * @param PDO $pdo Database connection
 * @param string $zip_code 5-digit zip code
 * @param float $order_amount Total order amount
 * @param array $options Additional options (express, insurance, etc.)
 * @return array Shipping cost breakdown
 */
function calculateShippingCost($pdo, $zip_code, $order_amount = 0, $options = []) {
    try {
        // Get zone information
        $zone = getZoneByZipCode($pdo, $zip_code);
        
        if (!$zone) {
            return [
                'success' => false,
                'message' => 'No delivery service available for this area',
                'costs' => null
            ];
        }
        
        if (!$zone['is_active']) {
            return [
                'success' => false,
                'message' => 'Delivery service temporarily unavailable',
                'costs' => null
            ];
        }
        
        $base_fee = (float) $zone['delivery_fee'];
        $free_delivery_minimum = (float) $zone['free_delivery_minimum'];
        
        // Calculate base delivery fee
        $delivery_fee = ($order_amount >= $free_delivery_minimum) ? 0 : $base_fee;
        
        // Additional fees based on options
        $express_fee = 0;
        $insurance_fee = 0;
        $fragile_fee = 0;
        $weekend_fee = 0;
        
        // Express delivery (same day or priority)
        if (isset($options['express']) && $options['express']) {
            $express_fee = $base_fee * 0.5; // 50% surcharge
        }
        
        // Insurance fee (percentage of order value)
        if (isset($options['insurance']) && $options['insurance'] && $order_amount > 0) {
            $insurance_rate = 0.02; // 2% of order value
            $insurance_fee = $order_amount * $insurance_rate;
            $insurance_fee = max($insurance_fee, 20); // Minimum 20 THB
            $insurance_fee = min($insurance_fee, 200); // Maximum 200 THB
        }
        
        // Fragile item handling
        if (isset($options['fragile']) && $options['fragile']) {
            $fragile_fee = 30; // Fixed 30 THB for fragile handling
        }
        
        // Weekend delivery surcharge
        $delivery_date = $options['delivery_date'] ?? date('Y-m-d');
        $day_of_week = date('w', strtotime($delivery_date));
        if ($day_of_week == 0 || $day_of_week == 6) { // Sunday or Saturday
            $weekend_fee = 25; // 25 THB weekend surcharge
        }
        
        // Calculate total
        $subtotal = $delivery_fee + $express_fee + $insurance_fee + $fragile_fee + $weekend_fee;
        
        // Apply any discounts
        $discount = 0;
        if (isset($options['discount_percentage']) && $options['discount_percentage'] > 0) {
            $discount = $subtotal * ($options['discount_percentage'] / 100);
        }
        
        // Tax calculation (7% VAT in Thailand)
        $tax_rate = 0.07;
        $tax_amount = ($subtotal - $discount) * $tax_rate;
        
        $total = $subtotal - $discount + $tax_amount;
        
        // Round to 2 decimal places
        $total = round($total, 2);
        
        return [
            'success' => true,
            'message' => 'Shipping cost calculated successfully',
            'costs' => [
                'base_delivery_fee' => $delivery_fee,
                'express_fee' => $express_fee,
                'insurance_fee' => $insurance_fee,
                'fragile_fee' => $fragile_fee,
                'weekend_fee' => $weekend_fee,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax_amount' => $tax_amount,
                'total' => $total,
                'is_free_delivery' => $delivery_fee == 0,
                'free_delivery_minimum' => $free_delivery_minimum,
                'zone_name' => $zone['zone_name']
            ],
            'breakdown' => [
                'order_amount' => $order_amount,
                'delivery_eligible_for_free' => $order_amount >= $free_delivery_minimum,
                'amount_needed_for_free' => max(0, $free_delivery_minimum - $order_amount),
                'estimated_delivery_time' => $zone['estimated_delivery_time'] . ' minutes'
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Error in calculateShippingCost: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error calculating shipping cost',
            'costs' => null
        ];
    }
}

/**
 * Get zone capacity information
 * @param PDO $pdo Database connection
 * @param string $zone_id Zone ID or zip code
 * @param string $date Date to check capacity for (optional, defaults to today)
 * @return array Zone capacity details
 */
function getZoneCapacity($pdo, $zone_identifier, $date = null) {
    try {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        // Determine if identifier is zone ID or zip code
        $is_zone_id = preg_match('/^[a-f0-9-]{36}$/i', $zone_identifier);
        
        if ($is_zone_id) {
            // Get zone by ID
            $stmt = $pdo->prepare("SELECT * FROM delivery_zones WHERE id = ? AND is_active = 1");
            $stmt->execute([$zone_identifier]);
            $zone = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Get zone by zip code
            $zone = getZoneByZipCode($pdo, $zone_identifier);
        }
        
        if (!$zone) {
            return [
                'success' => false,
                'message' => 'Zone not found or inactive',
                'capacity' => null
            ];
        }
        
        // Get current orders for the date
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_orders,
                COUNT(CASE WHEN status = 'preparing' THEN 1 END) as preparing_orders,
                COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready_orders,
                COUNT(CASE WHEN status = 'out_for_delivery' THEN 1 END) as out_for_delivery_orders,
                COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
                COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders
            FROM orders o
            WHERE o.delivery_date = ?
            AND EXISTS (
                SELECT 1 FROM delivery_zones dz 
                WHERE dz.id = ? 
                AND JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(o.delivery_address, -5)))
            )
        ");
        
        $stmt->execute([$date, $zone['id']]);
        $order_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate capacity metrics
        $max_capacity = (int) $zone['max_orders_per_day'];
        $current_orders = (int) $order_stats['total_orders'];
        $active_orders = $current_orders - (int) $order_stats['cancelled_orders'];
        
        $utilization_percentage = ($max_capacity > 0) ? round(($active_orders / $max_capacity) * 100, 1) : 0;
        $remaining_capacity = max(0, $max_capacity - $active_orders);
        
        // Capacity status
        $capacity_status = 'available';
        if ($utilization_percentage >= 100) {
            $capacity_status = 'full';
        } elseif ($utilization_percentage >= 80) {
            $capacity_status = 'high';
        } elseif ($utilization_percentage >= 50) {
            $capacity_status = 'medium';
        }
        
        // Get time slot distribution
        $stmt = $pdo->prepare("
            SELECT 
                delivery_time_slot,
                COUNT(*) as slot_orders
            FROM orders o
            WHERE o.delivery_date = ?
            AND EXISTS (
                SELECT 1 FROM delivery_zones dz 
                WHERE dz.id = ? 
                AND JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(o.delivery_address, -5)))
            )
            AND o.status NOT IN ('cancelled')
            GROUP BY delivery_time_slot
        ");
        
        $stmt->execute([$date, $zone['id']]);
        $time_slot_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate peak hours
        $peak_slot = null;
        $max_slot_orders = 0;
        foreach ($time_slot_distribution as $slot_data) {
            if ($slot_data['slot_orders'] > $max_slot_orders) {
                $max_slot_orders = $slot_data['slot_orders'];
                $peak_slot = $slot_data['delivery_time_slot'];
            }
        }
        
        return [
            'success' => true,
            'message' => 'Zone capacity retrieved successfully',
            'capacity' => [
                'zone_id' => $zone['id'],
                'zone_name' => $zone['zone_name'],
                'date' => $date,
                'max_capacity' => $max_capacity,
                'current_orders' => $current_orders,
                'active_orders' => $active_orders,
                'remaining_capacity' => $remaining_capacity,
                'utilization_percentage' => $utilization_percentage,
                'capacity_status' => $capacity_status,
                'is_at_capacity' => $remaining_capacity <= 0,
                'order_breakdown' => [
                    'pending' => (int) $order_stats['pending_orders'],
                    'confirmed' => (int) $order_stats['confirmed_orders'],
                    'preparing' => (int) $order_stats['preparing_orders'],
                    'ready' => (int) $order_stats['ready_orders'],
                    'out_for_delivery' => (int) $order_stats['out_for_delivery_orders'],
                    'delivered' => (int) $order_stats['delivered_orders'],
                    'cancelled' => (int) $order_stats['cancelled_orders']
                ],
                'time_slot_distribution' => $time_slot_distribution,
                'peak_time_slot' => $peak_slot,
                'estimated_delivery_time' => $zone['estimated_delivery_time'] . ' minutes'
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Error in getZoneCapacity: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error retrieving zone capacity',
            'capacity' => null
        ];
    }
}

/**
 * Get delivery estimation for a zip code
 * @param PDO $pdo Database connection
 * @param string $zip_code 5-digit zip code
 * @param string $delivery_date Preferred delivery date (optional)
 * @return array Delivery estimation details
 */
function getDeliveryEstimation($pdo, $zip_code, $delivery_date = null) {
    try {
        if (!$delivery_date) {
            $delivery_date = date('Y-m-d');
        }
        
        $zone = getZoneByZipCode($pdo, $zip_code);
        
        if (!$zone) {
            return [
                'success' => false,
                'message' => 'No delivery service available',
                'estimation' => null
            ];
        }
        
        $availability = checkDeliveryAvailability($pdo, $zip_code, $delivery_date);
        $time_slots = getDeliveryTimeSlots($pdo, $zip_code, $delivery_date);
        
        // Calculate earliest delivery date if current date is not available
        $earliest_date = $delivery_date;
        $check_date = $delivery_date;
        $days_checked = 0;
        $max_days_to_check = 14;
        
        while (!$availability['available'] && $days_checked < $max_days_to_check) {
            $days_checked++;
            $check_date = date('Y-m-d', strtotime($delivery_date . " +{$days_checked} days"));
            $availability = checkDeliveryAvailability($pdo, $zip_code, $check_date);
            
            if ($availability['available']) {
                $earliest_date = $check_date;
                break;
            }
        }
        
        return [
            'success' => true,
            'message' => 'Delivery estimation calculated',
            'estimation' => [
                'zone_name' => $zone['zone_name'],
                'requested_date' => $delivery_date,
                'earliest_available_date' => $earliest_date,
                'days_from_now' => max(0, (strtotime($earliest_date) - strtotime(date('Y-m-d'))) / 86400),
                'estimated_delivery_time' => $zone['estimated_delivery_time'],
                'is_same_day_available' => $earliest_date === date('Y-m-d'),
                'is_next_day_available' => $earliest_date === date('Y-m-d', strtotime('+1 day')),
                'available_time_slots' => $time_slots,
                'delivery_fee' => $zone['delivery_fee'],
                'free_delivery_minimum' => $zone['free_delivery_minimum'],
                'zone_capacity' => [
                    'utilization' => $zone['capacity_percentage'],
                    'is_at_capacity' => $zone['is_at_capacity'],
                    'remaining_slots' => $zone['max_orders_per_day'] - $zone['current_orders_today']
                ]
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Error in getDeliveryEstimation: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error calculating delivery estimation',
            'estimation' => null
        ];
    }
}

/**
 * Validate and format zip code
 * @param string $zip_code Input zip code
 * @return array Validation result
 */
function validateAndFormatZipCode($zip_code) {
    // Remove any non-numeric characters
    $cleaned = preg_replace('/\D/', '', $zip_code);
    
    // Check if it's exactly 5 digits
    if (strlen($cleaned) !== 5) {
        return [
            'valid' => false,
            'formatted' => null,
            'message' => 'Zip code must be exactly 5 digits'
        ];
    }
    
    // Thai zip codes start with specific digits
    $first_digit = substr($cleaned, 0, 1);
    if (!in_array($first_digit, ['1', '2', '3', '4', '5', '6', '7', '8', '9'])) {
        return [
            'valid' => false,
            'formatted' => null,
            'message' => 'Invalid Thai zip code format'
        ];
    }
    
    return [
        'valid' => true,
        'formatted' => $cleaned,
        'message' => 'Valid zip code'
    ];
}

/**
 * Get zone statistics for admin dashboard
 * @param PDO $pdo Database connection
 * @param string $zone_id Specific zone ID (optional)
 * @param int $days Number of days to analyze (optional, defaults to 30)
 * @return array Zone statistics
 */
function getZoneStatistics($pdo, $zone_id = null, $days = 30) {
    try {
        $where_clause = $zone_id ? "WHERE dz.id = ?" : "";
        $params = $zone_id ? [$zone_id, $days] : [$days];
        
        $stmt = $pdo->prepare("
            SELECT 
                dz.id,
                dz.zone_name,
                dz.delivery_fee,
                dz.free_delivery_minimum,
                dz.max_orders_per_day,
                dz.estimated_delivery_time,
                dz.is_active,
                COUNT(DISTINCT o.id) as total_orders,
                COUNT(DISTINCT CASE WHEN o.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) 
                                   THEN o.id END) as recent_orders,
                AVG(o.total_amount) as avg_order_value,
                COUNT(DISTINCT CASE WHEN o.status = 'delivered' THEN o.id END) as delivered_orders,
                COUNT(DISTINCT CASE WHEN o.status = 'cancelled' THEN o.id END) as cancelled_orders,
                AVG(CASE WHEN o.status = 'delivered' AND o.delivered_at IS NOT NULL 
                         THEN TIMESTAMPDIFF(MINUTE, o.created_at, o.delivered_at) END) as avg_delivery_time
            FROM delivery_zones dz
            LEFT JOIN orders o ON JSON_CONTAINS(dz.zip_codes, JSON_QUOTE(SUBSTRING(o.delivery_address, -5)))
            {$where_clause}
            GROUP BY dz.id
            ORDER BY dz.zone_name
        ");
        
        $stmt->execute($params);
        $statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate additional metrics
        foreach ($statistics as &$stat) {
            $stat['delivery_success_rate'] = $stat['total_orders'] > 0 
                ? round(($stat['delivered_orders'] / $stat['total_orders']) * 100, 1) 
                : 0;
                
            $stat['cancellation_rate'] = $stat['total_orders'] > 0 
                ? round(($stat['cancelled_orders'] / $stat['total_orders']) * 100, 1) 
                : 0;
                
            $stat['avg_daily_orders'] = round($stat['recent_orders'] / $days, 1);
            
            $stat['capacity_utilization'] = $stat['max_orders_per_day'] > 0 
                ? round(($stat['avg_daily_orders'] / $stat['max_orders_per_day']) * 100, 1) 
                : 0;
        }
        
        return [
            'success' => true,
            'statistics' => $zone_id ? ($statistics[0] ?? null) : $statistics,
            'period_days' => $days
        ];
        
    } catch (Exception $e) {
        error_log("Error in getZoneStatistics: " . $e->getMessage());
        return [
            'success' => false,
            'statistics' => null,
            'message' => 'Error retrieving zone statistics'
        ];
    }
}

// Example usage and testing functions (for development)
// Uncomment the line below to enable testing mode
// define('ZONE_HELPER_TEST', true);

if (defined('ZONE_HELPER_TEST')) {
    
    /**
     * Test all zone helper functions
     * @param PDO $pdo Database connection
     */
    function testZoneHelperFunctions($pdo) {
        echo "<h2>Testing Zone Helper Functions</h2>";
        
        $test_zip = "10110"; // Bangkok Central
        
        // Test 1: Get zone by zip code
        echo "<h3>1. Testing getZoneByZipCode()</h3>";
        $zone = getZoneByZipCode($pdo, $test_zip);
        echo "<pre>" . print_r($zone, true) . "</pre>";
        
        // Test 2: Check delivery availability
        echo "<h3>2. Testing checkDeliveryAvailability()</h3>";
        $availability = checkDeliveryAvailability($pdo, $test_zip);
        echo "<pre>" . print_r($availability, true) . "</pre>";
        
        // Test 3: Get delivery time slots
        echo "<h3>3. Testing getDeliveryTimeSlots()</h3>";
        $slots = getDeliveryTimeSlots($pdo, $test_zip);
        echo "<pre>" . print_r($slots, true) . "</pre>";
        
        // Test 4: Calculate shipping cost
        echo "<h3>4. Testing calculateShippingCost()</h3>";
        $cost = calculateShippingCost($pdo, $test_zip, 500, ['express' => true, 'insurance' => true]);
        echo "<pre>" . print_r($cost, true) . "</pre>";
        
        // Test 5: Get zone capacity
        echo "<h3>5. Testing getZoneCapacity()</h3>";
        $capacity = getZoneCapacity($pdo, $test_zip);
        echo "<pre>" . print_r($capacity, true) . "</pre>";
        
        // Test 6: Get delivery estimation
        echo "<h3>6. Testing getDeliveryEstimation()</h3>";
        $estimation = getDeliveryEstimation($pdo, $test_zip);
        echo "<pre>" . print_r($estimation, true) . "</pre>";
        
        // Test 7: Validate zip code
        echo "<h3>7. Testing validateAndFormatZipCode()</h3>";
        $validation = validateAndFormatZipCode("10110");
        echo "<pre>" . print_r($validation, true) . "</pre>";
        
        // Test 8: Get zone statistics
        echo "<h3>8. Testing getZoneStatistics()</h3>";
        $statistics = getZoneStatistics($pdo);
        echo "<pre>" . print_r($statistics, true) . "</pre>";
    }
}

/**
 * Helper function to get formatted zone information for display
 * @param array $zone Zone data from database
 * @return array Formatted zone information
 */
function formatZoneForDisplay($zone) {
    if (!$zone) {
        return null;
    }
    
    return [
        'id' => $zone['id'],
        'name' => $zone['zone_name'],
        'delivery_fee' => number_format($zone['delivery_fee'], 2),
        'free_minimum' => number_format($zone['free_delivery_minimum'], 2),
        'delivery_time' => $zone['estimated_delivery_time'] . ' minutes',
        'capacity' => $zone['current_orders_today'] . '/' . $zone['max_orders_per_day'],
        'utilization' => $zone['capacity_percentage'] . '%',
        'status' => $zone['is_active'] ? 'Active' : 'Inactive',
        'zip_codes' => implode(', ', $zone['zip_codes_array'] ?: []),
        'time_slots' => implode(', ', $zone['delivery_time_slots_array'] ?: [])
    ];
}

/**
 * Helper function to get zone color based on capacity
 * @param float $utilization_percentage Capacity utilization percentage
 * @return string CSS color class
 */
function getZoneCapacityColor($utilization_percentage) {
    if ($utilization_percentage >= 90) {
        return 'danger'; // Red - Very high
    } elseif ($utilization_percentage >= 70) {
        return 'warning'; // Orange - High
    } elseif ($utilization_percentage >= 40) {
        return 'info'; // Blue - Medium
    } else {
        return 'success'; // Green - Low
    }
}

/**
 * Get next available delivery date for a zone
 * @param PDO $pdo Database connection
 * @param string $zip_code 5-digit zip code
 * @param int $max_days_to_check Maximum days to look ahead
 * @return array Next available date information
 */
function getNextAvailableDeliveryDate($pdo, $zip_code, $max_days_to_check = 14) {
    try {
        $current_date = date('Y-m-d');
        
        for ($i = 0; $i < $max_days_to_check; $i++) {
            $check_date = date('Y-m-d', strtotime($current_date . " +{$i} days"));
            $availability = checkDeliveryAvailability($pdo, $zip_code, $check_date);
            
            if ($availability['available']) {
                $day_name = date('l', strtotime($check_date));
                $formatted_date = date('M d, Y', strtotime($check_date));
                
                return [
                    'success' => true,
                    'date' => $check_date,
                    'formatted_date' => $formatted_date,
                    'day_name' => $day_name,
                    'days_from_now' => $i,
                    'is_today' => $i === 0,
                    'is_tomorrow' => $i === 1,
                    'zone' => $availability['zone'],
                    'remaining_slots' => $availability['remaining_slots'] ?? 0
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'No delivery slots available in the next ' . $max_days_to_check . ' days',
            'date' => null
        ];
        
    } catch (Exception $e) {
        error_log("Error in getNextAvailableDeliveryDate: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error finding next available date',
            'date' => null
        ];
    }
}

/**
 * Bulk check delivery availability for multiple zip codes
 * @param PDO $pdo Database connection
 * @param array $zip_codes Array of zip codes to check
 * @param string $delivery_date Date to check (optional)
 * @return array Availability results for all zip codes
 */
function bulkCheckDeliveryAvailability($pdo, $zip_codes, $delivery_date = null) {
    try {
        $results = [];
        
        foreach ($zip_codes as $zip_code) {
            $availability = checkDeliveryAvailability($pdo, $zip_code, $delivery_date);
            $results[$zip_code] = $availability;
        }
        
        // Summary statistics
        $total_checked = count($zip_codes);
        $available_count = count(array_filter($results, function($result) {
            return $result['available'];
        }));
        
        return [
            'success' => true,
            'results' => $results,
            'summary' => [
                'total_checked' => $total_checked,
                'available' => $available_count,
                'unavailable' => $total_checked - $available_count,
                'coverage_percentage' => $total_checked > 0 ? round(($available_count / $total_checked) * 100, 1) : 0
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Error in bulkCheckDeliveryAvailability: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error in bulk availability check',
            'results' => []
        ];
    }
}

/**
 * Get delivery recommendations based on order details
 * @param PDO $pdo Database connection
 * @param string $zip_code 5-digit zip code
 * @param float $order_amount Order total amount
 * @param array $preferences Customer preferences (time, date, etc.)
 * @return array Delivery recommendations
 */
function getDeliveryRecommendations($pdo, $zip_code, $order_amount = 0, $preferences = []) {
    try {
        $zone = getZoneByZipCode($pdo, $zip_code);
        
        if (!$zone) {
            return [
                'success' => false,
                'message' => 'No delivery service available',
                'recommendations' => []
            ];
        }
        
        $recommendations = [];
        
        // Cost optimization recommendation
        $shipping_cost = calculateShippingCost($pdo, $zip_code, $order_amount);
        if ($shipping_cost['success'] && !$shipping_cost['costs']['is_free_delivery']) {
            $amount_needed = $shipping_cost['costs']['free_delivery_minimum'] - $order_amount;
            if ($amount_needed > 0 && $amount_needed <= 200) { // Reasonable amount to add
                $recommendations[] = [
                    'type' => 'cost_optimization',
                    'priority' => 'high',
                    'title' => 'Save on Delivery',
                    'message' => "Add ฿{$amount_needed} more to get FREE delivery!",
                    'action' => 'add_items',
                    'savings' => $shipping_cost['costs']['base_delivery_fee']
                ];
            }
        }
        
        // Time slot recommendation
        $today_slots = getDeliveryTimeSlots($pdo, $zip_code, date('Y-m-d'));
        $tomorrow_slots = getDeliveryTimeSlots($pdo, $zip_code, date('Y-m-d', strtotime('+1 day')));
        
        if (empty($today_slots) && !empty($tomorrow_slots)) {
            $recommendations[] = [
                'type' => 'timing',
                'priority' => 'medium',
                'title' => 'Next Day Delivery',
                'message' => 'Today is fully booked. Tomorrow has ' . count($tomorrow_slots) . ' available slots.',
                'action' => 'select_tomorrow',
                'available_slots' => count($tomorrow_slots)
            ];
        }
        
        // Express delivery recommendation for high-value orders
        if ($order_amount > 1000) {
            $express_cost = calculateShippingCost($pdo, $zip_code, $order_amount, ['express' => true]);
            if ($express_cost['success']) {
                $additional_cost = $express_cost['costs']['express_fee'];
                $recommendations[] = [
                    'type' => 'service_upgrade',
                    'priority' => 'low',
                    'title' => 'Express Delivery',
                    'message' => "Get priority delivery for just ฿{$additional_cost} more",
                    'action' => 'upgrade_express',
                    'additional_cost' => $additional_cost
                ];
            }
        }
        
        // Capacity warning
        if ($zone['capacity_percentage'] > 80) {
            $recommendations[] = [
                'type' => 'capacity_warning',
                'priority' => 'high',
                'title' => 'High Demand Area',
                'message' => 'This area is {$zone[\'capacity_percentage\']}% booked. Order soon to secure your slot!',
                'action' => 'order_now',
                'urgency' => 'high'
            ];
        }
        
        // Weekend surcharge warning
        $preferred_date = $preferences['delivery_date'] ?? date('Y-m-d');
        $day_of_week = date('w', strtotime($preferred_date));
        if ($day_of_week == 0 || $day_of_week == 6) {
            $recommendations[] = [
                'type' => 'pricing_info',
                'priority' => 'low',
                'title' => 'Weekend Delivery',
                'message' => 'Weekend deliveries include a ฿25 surcharge',
                'action' => 'info_only',
                'additional_cost' => 25
            ];
        }
        
        // Sort recommendations by priority
        usort($recommendations, function($a, $b) {
            $priority_order = ['high' => 1, 'medium' => 2, 'low' => 3];
            return $priority_order[$a['priority']] <=> $priority_order[$b['priority']];
        });
        
        return [
            'success' => true,
            'recommendations' => $recommendations,
            'zone_info' => formatZoneForDisplay($zone),
            'total_recommendations' => count($recommendations)
        ];
        
    } catch (Exception $e) {
        error_log("Error in getDeliveryRecommendations: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error generating recommendations',
            'recommendations' => []
        ];
    }
}

/**
 * Log zone activity for analytics
 * @param PDO $pdo Database connection
 * @param string $zone_id Zone ID
 * @param string $activity_type Type of activity
 * @param array $data Additional data
 * @return bool Success status
 */
function logZoneActivity($pdo, $zone_id, $activity_type, $data = []) {
    try {
        // Create zone_activity table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS zone_activity (
                id INT PRIMARY KEY AUTO_INCREMENT,
                zone_id CHAR(36) NOT NULL,
                activity_type VARCHAR(50) NOT NULL,
                activity_data JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_zone_activity (zone_id, activity_type, created_at)
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO zone_activity (zone_id, activity_type, activity_data, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt->execute([
            $zone_id,
            $activity_type,
            json_encode($data),
            $ip_address,
            $user_agent
        ]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error in logZoneActivity: " . $e->getMessage());
        return false;
    }
}

// Auto-include guard to prevent errors if included multiple times
if (!function_exists('zone_helper_loaded')) {
    function zone_helper_loaded() {
        return true;
    }
}

/*
 * Usage Examples:
 * 
 * // Check if delivery is available
 * $availability = checkDeliveryAvailability($pdo, "10110", "2025-07-15");
 * if ($availability['available']) {
 *     echo "Delivery is available!";
 * }
 * 
 * // Calculate shipping cost
 * $cost = calculateShippingCost($pdo, "10110", 750.00, ['express' => true]);
 * echo "Total shipping: ฿" . $cost['costs']['total'];
 * 
 * // Get zone capacity
 * $capacity = getZoneCapacity($pdo, "10110");
 * echo "Zone is " . $capacity['capacity']['utilization_percentage'] . "% full";
 * 
 * // Get available time slots
 * $slots = getDeliveryTimeSlots($pdo, "10110", "2025-07-15");
 * foreach ($slots as $slot) {
 *     echo $slot['slot'] . " - " . $slot['remaining'] . " slots left\n";
 * }
 * 
 * // Get delivery recommendations
 * $recommendations = getDeliveryRecommendations($pdo, "10110", 450.00);
 * foreach ($recommendations['recommendations'] as $rec) {
 *     echo $rec['title'] . ": " . $rec['message'] . "\n";
 * }
 */

?>