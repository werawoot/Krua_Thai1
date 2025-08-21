<?php
/**
 * Simple test version for ZIP code checking
 * File: ajax/check_zipcode.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Log the request for debugging
error_log("Zipcode check request: " . print_r($_REQUEST, true));

try {
    $zip_code = $_GET['zip_code'] ?? $_POST['zip_code'] ?? '';
    
    if (empty($zip_code)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a ZIP code',
            'type' => 'error'
        ]);
        exit;
    }
    
    // Try to use database functions if available, otherwise use simple validation
    if (function_exists('validateAndFormatZipCode') && isset($pdo)) {
        // Use database-powered validation
        $validation = validateAndFormatZipCode($zip_code);
        
        if (!$validation['valid']) {
            echo json_encode([
                'success' => false,
                'message' => $validation['message'],
                'type' => 'error',
                'debug' => 'Database validation failed'
            ]);
            exit;
        }
        
        $zip_code = $validation['formatted'];
        
        // Check delivery availability using database
        $availability = checkDeliveryAvailability($pdo, $zip_code);
        
        if (!$availability['available']) {
            echo json_encode([
                'success' => false,
                'message' => $availability['message'],
                'type' => 'warning',
                'reason' => $availability['reason'],
                'debug' => 'Database availability check failed'
            ]);
            exit;
        }
        
        // Get detailed zone information
        $zone = getZoneByZipCode($pdo, $zip_code);
        $shipping = calculateShippingCost($pdo, $zip_code, 0);
        
        echo json_encode([
            'success' => true,
            'message' => "Great! We deliver to {$zip_code}",
            'type' => 'success',
            'zip_code' => $zip_code,
            'zone' => [
                'name' => $zone['zone_name'],
                'delivery_fee' => number_format($zone['delivery_fee'], 2),
                'free_minimum' => number_format($zone['free_delivery_minimum'], 2),
                'estimated_time' => $zone['estimated_delivery_time'] . ' minutes'
            ],
            'can_order' => true,
            'debug' => 'Database validation passed'
        ]);
        
    } else {
        // Fallback to simple validation without database
    } else {
        // Fallback to simple validation without database
        $zip_code = preg_replace('/\D/', '', $zip_code);
        
        if (strlen($zip_code) !== 5) {
            echo json_encode([
                'success' => false,
                'message' => 'ZIP code must be exactly 5 digits',
                'type' => 'error',
                'debug' => 'Simple validation - Length check failed: ' . strlen($zip_code)
            ]);
            exit;
        }
        
        // Use the simple ZIP code validation that was already there
        $valid_zips = [
            // Anaheim
            '92801', '92802', '92803', '92804', '92805', '92806', '92807', '92808', '92809',
            '92812', '92814', '92815', '92816', '92817', '92825', '92850', '92899',
            // Brea  
            '92821', '92822', '92823',
            // Buena Park
            '90620', '90621', '90622', '90624',
            // Costa Mesa
            '92626', '92627', '92628',
            // Fountain Valley
            '92708', '92728',
            // Fullerton
            '92831', '92832', '92833', '92834', '92835', '92836', '92837', '92838',
            // Garden Grove
            '92840', '92841', '92842', '92843', '92844', '92845', '92846',
            // Irvine
            '92602', '92603', '92604', '92606', '92612', '92614', '92616', '92617',
            '92618', '92619', '92620', '92623', '92650', '92697',
            // Orange
            '92856', '92857', '92859', '92862', '92863', '92864', '92865', '92866',
            '92867', '92868', '92869',
            // Placentia
            '92811', '92870', '92871',
            // Tustin
            '92780', '92781', '92782',
            // Villa Park
            '92861',
            // Westminster
            '92683', '92684', '92685',
            // Newport Beach
            '92625', '92657', '92658', '92659', '92660', '92661', '92662', '92663'
        ];
        
        if (in_array($zip_code, $valid_zips)) {
            // Find which area this ZIP belongs to
            $area_map = [
                'Anaheim' => ['92801', '92802', '92803', '92804', '92805', '92806', '92807', '92808', '92809', '92812', '92814', '92815', '92816', '92817', '92825', '92850', '92899'],
                'Brea' => ['92821', '92822', '92823'],
                'Buena Park' => ['90620', '90621', '90622', '90624'],
                'Costa Mesa' => ['92626', '92627', '92628'],
                'Fountain Valley' => ['92708', '92728'],
                'Fullerton' => ['92831', '92832', '92833', '92834', '92835', '92836', '92837', '92838'],
                'Garden Grove' => ['92840', '92841', '92842', '92843', '92844', '92845', '92846'],
                'Irvine' => ['92602', '92603', '92604', '92606', '92612', '92614', '92616', '92617', '92618', '92619', '92620', '92623', '92650', '92697'],
                'Orange' => ['92856', '92857', '92859', '92862', '92863', '92864', '92865', '92866', '92867', '92868', '92869'],
                'Placentia' => ['92811', '92870', '92871'],
                'Tustin' => ['92780', '92781', '92782'],
                'Villa Park' => ['92861'],
                'Westminster' => ['92683', '92684', '92685'],
                'Newport Beach' => ['92625', '92657', '92658', '92659', '92660', '92661', '92662', '92663']
            ];
            
            $area_name = 'Unknown Area';
            foreach ($area_map as $area => $zips) {
                if (in_array($zip_code, $zips)) {
                    $area_name = $area;
                    break;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Great! We deliver to {$zip_code}",
                'type' => 'success',
                'zip_code' => $zip_code,
                'zone' => [
                    'name' => $area_name,
                    'delivery_fee' => '3.99',
                    'free_minimum' => '35.00',
                    'estimated_time' => '30-45 minutes'
                ],
                'can_order' => true,
                'debug' => 'Simple validation passed'
            ]);
            
        } else {
            // Get a few sample valid ZIP codes for suggestions
            $suggestions = [
                ['zip_code' => '92801', 'area' => 'Anaheim'],
                ['zip_code' => '92626', 'area' => 'Costa Mesa'],
                ['zip_code' => '92602', 'area' => 'Irvine'],
                ['zip_code' => '92831', 'area' => 'Fullerton']
            ];
            
            echo json_encode([
                'success' => false,
                'message' => "Sorry, we don't deliver to {$zip_code} yet. We're expanding soon!",
                'type' => 'warning',
                'zip_code' => $zip_code,
                'suggestions' => $suggestions,
                'debug' => 'Simple validation - ZIP not in valid list'
            ]);
        }
    }
    
} catch (Exception $e) {
    error_log("Zipcode check error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Sorry, there was an error checking your ZIP code. Please try again.',
        'type' => 'error',
        'debug' => $e->getMessage()
    ]);
}
?>