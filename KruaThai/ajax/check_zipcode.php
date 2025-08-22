<?php
/**
 * Fixed ZIP code checking endpoint
 * File: ajax/check_zipcode.php
 */

// Set proper headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start output buffering to prevent any accidental output
ob_start();

try {
    // Get ZIP code from request
    $zip_code = '';
    if (isset($_GET['zip_code'])) {
        $zip_code = $_GET['zip_code'];
    } elseif (isset($_POST['zip_code'])) {
        $zip_code = $_POST['zip_code'];
    }
    
    // Validate input
    if (empty($zip_code)) {
        throw new Exception('ZIP code is required');
    }
    
    // Clean up ZIP code (remove non-digits)
    $zip_code = preg_replace('/\D/', '', $zip_code);
    
    // Validate length
    if (strlen($zip_code) !== 5) {
        throw new Exception('ZIP code must be exactly 5 digits');
    }
    
    // Valid ZIP codes for your service areas
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
        
        // Find area name
        $area_name = 'Service Area';
        foreach ($area_map as $area => $zips) {
            if (in_array($zip_code, $zips)) {
                $area_name = $area;
                break;
            }
        }
        
        // Success response
        $response = [
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
            'can_order' => true
        ];
        
    } else {
        // ZIP not in service area
        $suggestions = [
            ['zip_code' => '92801', 'area' => 'Anaheim'],
            ['zip_code' => '92626', 'area' => 'Costa Mesa'],
            ['zip_code' => '92602', 'area' => 'Irvine'],
            ['zip_code' => '92831', 'area' => 'Fullerton']
        ];
        
        $response = [
            'success' => false,
            'message' => "Sorry, we don't deliver to {$zip_code} yet. We're expanding soon!",
            'type' => 'warning',
            'zip_code' => $zip_code,
            'suggestions' => $suggestions
        ];
    }
    
} catch (Exception $e) {
    // Error response
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'type' => 'error'
    ];
}

// Clear any output buffer
ob_clean();

// Set HTTP status
http_response_code(200);

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT);

// End output buffering
ob_end_flush();
exit;
?>