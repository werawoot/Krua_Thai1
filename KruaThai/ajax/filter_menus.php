<?php
/**
 * AJAX endpoint for filtering menus by category
 * File: ajax/filter_menus.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once '../config/database.php';

try {
    $category_id = $_GET['category'] ?? 'all';
    $limit = (int)($_GET['limit'] ?? 10);
    
    // Build query based on category
    if ($category_id === 'all') {
        $sql = "
            SELECT m.*, c.name AS category_name, c.name_thai AS category_name_thai
            FROM menus m 
            LEFT JOIN menu_categories c ON m.category_id = c.id 
            WHERE m.is_available = 1 
            ORDER BY m.is_featured DESC, m.updated_at DESC 
            LIMIT ?
        ";
        $params = [$limit];
    } else {
        $sql = "
            SELECT m.*, c.name AS category_name, c.name_thai AS category_name_thai
            FROM menus m 
            LEFT JOIN menu_categories c ON m.category_id = c.id 
            WHERE m.is_available = 1 AND m.category_id = ?
            ORDER BY m.is_featured DESC, m.updated_at DESC 
            LIMIT ?
        ";
        $params = [$category_id, $limit];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no menus found, return fallback data
    if (empty($menus)) {
        $fallback_menus = [
            [
                'id' => 'fallback-1',
                'name' => 'Thai Green Curry',
                'name_thai' => 'แกงเขียวหวาน',
                'description' => 'Aromatic green curry with Thai basil and coconut milk',
                'category_name' => 'Thai Curries',
                'category_name_thai' => 'แกงไทย',
                'base_price' => 12.99,
                'main_image_url' => null,
                'is_featured' => 1
            ],
            [
                'id' => 'fallback-2',
                'name' => 'Pad Thai Classic',
                'name_thai' => 'ผัดไทยคลาสสิค',
                'description' => 'Traditional stir-fried rice noodles with tamarind sauce',
                'category_name' => 'Noodle Dishes',
                'category_name_thai' => 'เมนูเส้น',
                'base_price' => 11.99,
                'main_image_url' => null,
                'is_featured' => 1
            ],
            [
                'id' => 'fallback-3',
                'name' => 'Thai Basil Fried Rice',
                'name_thai' => 'ข้าวผัดกะเพรา',
                'description' => 'Fragrant jasmine rice with Thai basil and your choice of protein',
                'category_name' => 'Rice Bowls',
                'category_name_thai' => 'ข้าวกล่อง',
                'base_price' => 10.99,
                'main_image_url' => null,
                'is_featured' => 1
            ]
        ];
        
        // Filter fallback menus by category if not 'all'
        if ($category_id !== 'all') {
            $category_names = [
                '550e8400-e29b-41d4-a716-446655440005' => 'Rice Bowls',
                '550e8400-e29b-41d4-a716-446655440006' => 'Thai Curries',
                'a598bb91-68eb-4b0f-9de3-174362f36f37' => 'Noodle Dishes'
            ];
            
            $target_category = $category_names[$category_id] ?? '';
            $fallback_menus = array_filter($fallback_menus, function($menu) use ($target_category) {
                return $menu['category_name'] === $target_category;
            });
        }
        
        $menus = array_slice(array_values($fallback_menus), 0, $limit);
    }
    
    // Process menus for response
    $response_menus = [];
    foreach ($menus as $menu) {
        $menu_name = $menu['name'] ?: $menu['name_thai'];
        $category_name = $menu['category_name'] ?: $menu['category_name_thai'];
        $description = $menu['description'] ?: 'Authentic Thai cuisine made with fresh ingredients';
        
        // Determine background style
        $background_style = '';
        if (!empty($menu['main_image_url'])) {
            // Since we're in ajax/ folder, we need to go up one level to check file existence
            $image_path = '../' . $menu['main_image_url'];
            
            // Check if the image file exists (accounting for being in ajax subfolder)
            if (file_exists($image_path)) {
                // Return the original path (not the ../ version) for the client-side URL
                $background_style = "background-image: url('" . htmlspecialchars($menu['main_image_url']) . "');";
            } else {
                // If file doesn't exist, fall back to gradient
                $gradients = [
                    'Rice Bowls' => 'linear-gradient(45deg, #cf723a, #bd9379)',
                    'Thai Curries' => 'linear-gradient(45deg, #bd9379, #adb89d)',
                    'Noodle Dishes' => 'linear-gradient(45deg, #adb89d, #ece8e1)',
                    'Stir Fry' => 'linear-gradient(45deg, #cf723a, #bd9379)',
                    'Rice Dishes' => 'linear-gradient(45deg, #bd9379, #adb89d)',
                    'Soups' => 'linear-gradient(45deg, #adb89d, #ece8e1)',
                    'Salads' => 'linear-gradient(45deg, #adb89d, #cf723a)',
                    'Desserts' => 'linear-gradient(45deg, #cf723a, #bd9379)',
                    'Beverages' => 'linear-gradient(45deg, #bd9379, #adb89d)'
                ];
                $gradient = $gradients[$category_name] ?? $gradients['Rice Bowls'];
                $background_style = "background: $gradient;";
            }
        } else {
            // No image URL provided, use gradient fallback
            $gradients = [
                'Rice Bowls' => 'linear-gradient(45deg, #cf723a, #bd9379)',
                'Thai Curries' => 'linear-gradient(45deg, #bd9379, #adb89d)',
                'Noodle Dishes' => 'linear-gradient(45deg, #adb89d, #ece8e1)',
                'Stir Fry' => 'linear-gradient(45deg, #cf723a, #bd9379)',
                'Rice Dishes' => 'linear-gradient(45deg, #bd9379, #adb89d)',
                'Soups' => 'linear-gradient(45deg, #adb89d, #ece8e1)',
                'Salads' => 'linear-gradient(45deg, #adb89d, #cf723a)',
                'Desserts' => 'linear-gradient(45deg, #cf723a, #bd9379)',
                'Beverages' => 'linear-gradient(45deg, #bd9379, #adb89d)'
            ];
            $gradient = $gradients[$category_name] ?? $gradients['Rice Bowls'];
            $background_style = "background: $gradient;";
        }
        
        $response_menus[] = [
            'id' => $menu['id'],
            'name' => htmlspecialchars($menu_name),
            'description' => htmlspecialchars($description),
            'category_name' => htmlspecialchars($category_name),
            'price' => $menu['base_price'] ? number_format($menu['base_price'], 2) : null,
            'background_style' => $background_style,
            'image_url' => $menu['main_image_url'], // Add this for debugging
            'has_image' => !empty($menu['main_image_url']) // Add this for debugging
        ];
    }
    
    echo json_encode([
        'success' => true,
        'menus' => $response_menus,
        'count' => count($response_menus),
        'debug_info' => [
            'category_id' => $category_id,
            'sql_executed' => $sql,
            'raw_menus_sample' => isset($menus[0]) ? [
                'id' => $menus[0]['id'] ?? 'N/A',
                'name' => $menus[0]['name'] ?? 'N/A', 
                'main_image_url' => $menus[0]['main_image_url'] ?? 'N/A',
                'file_exists_check' => !empty($menus[0]['main_image_url']) ? (file_exists('../' . $menus[0]['main_image_url']) ? 'EXISTS' : 'NOT_FOUND') : 'NO_URL'
            ] : 'NO_MENUS'
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Filter menus error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Unable to load menus',
        'message' => $e->getMessage()
    ]);
}
?>