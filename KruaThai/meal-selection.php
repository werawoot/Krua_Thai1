<?php
/**
 * Somdul Table - Meal Selection Page with Quantity Support
 * File: meal-selection.php
 * Description: Select meals with quantities according to package amount (Step 2)
 * Updated to support multiple quantities of the same meal
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Database connection with fallback
try {
    require_once 'config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    try {
        // Fallback connection for MAMP/XAMPP
        $pdo = new PDO("mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        try {
            $pdo = new PDO("mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $plan = $_GET['plan'] ?? '';
    $redirect_url = 'meal-selection.php' . ($plan ? '?plan=' . urlencode($plan) : '');
    header('Location: login.php?redirect=' . urlencode($redirect_url));
    exit;
}

// Get plan information
$plan_id = $_GET['plan'] ?? '';
$highlight_menu_id = $_GET['menu'] ?? '';

if (empty($plan_id)) {
    header('Location: subscribe.php');
    exit;
}

// Helper function to get plan name with fallback
function getPlanName($plan) {
    if (isset($plan['name_english']) && !empty($plan['name_english'])) {
        return $plan['name_english'];
    } elseif (isset($plan['name_thai']) && !empty($plan['name_thai'])) {
        return $plan['name_thai'];
    } else {
        return 'Package ' . ($plan['meals_per_week'] ?? 'Unknown');
    }
}

// Helper function to get menu name with fallback
function getMenuName($menu) {
    if (isset($menu['name']) && !empty($menu['name'])) {
        return $menu['name'];
    } elseif (isset($menu['name_thai']) && !empty($menu['name_thai'])) {
        return $menu['name_thai'];
    } else {
        return 'Menu Item';
    }
}

// Helper function to get category name with fallback
function getCategoryName($category) {
    if (isset($category['name']) && !empty($category['name'])) {
        return $category['name'];
    } elseif (isset($category['name_thai']) && !empty($category['name_thai'])) {
        return $category['name_thai'];
    } else {
        return 'Category';
    }
}

try {
    // Get plan details from database
    $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan) {
        // Fallback plans matching database structure
        $plans = [
            '74c0ed20-5afc-11f0-8b7f-3f129bd34f14' => ['id' => '74c0ed20-5afc-11f0-8b7f-3f129bd34f14', 'name_thai' => 'แพ็คเกจ 4 มื้อ', 'name_english' => '4 Meal Package', 'meals_per_week' => 4, 'final_price' => 2500],
            'd6e98c56-5afb-11f0-8b7f-3f129bd34f14' => ['id' => 'd6e98c56-5afb-11f0-8b7f-3f129bd34f14', 'name_thai' => 'แพ็คเกจ 8 มื้อ', 'name_english' => '8 Meal Package', 'meals_per_week' => 8, 'final_price' => 4500],
            'd6e98d96-5afb-11f0-8b7f-3f129bd34f14' => ['id' => 'd6e98d96-5afb-11f0-8b7f-3f129bd34f14', 'name_thai' => 'แพ็คเกจ 12 มื้อ', 'name_english' => '12 Meal Package', 'meals_per_week' => 12, 'final_price' => 6200],
            'd6e98e7c-5afb-11f0-8b7f-3f129bd34f14' => ['id' => 'd6e98e7c-5afb-11f0-8b7f-3f129bd34f14', 'name_thai' => 'แพ็คเกจ 15 มื้อ', 'name_english' => '15 Meal Package', 'meals_per_week' => 15, 'final_price' => 7500],
            // Support old IDs
            '4' => ['id' => '4', 'name_thai' => 'แพ็คเกจ 4 มื้อ', 'name_english' => '4 Meal Package', 'meals_per_week' => 4, 'final_price' => 2500],
            '8' => ['id' => '8', 'name_thai' => 'แพ็คเกจ 8 มื้อ', 'name_english' => '8 Meal Package', 'meals_per_week' => 8, 'final_price' => 4500],
            '12' => ['id' => '12', 'name_thai' => 'แพ็คเกจ 12 มื้อ', 'name_english' => '12 Meal Package', 'meals_per_week' => 12, 'final_price' => 6200],
            '15' => ['id' => '15', 'name_thai' => 'แพ็คเกจ 15 มื้อ', 'name_english' => '15 Meal Package', 'meals_per_week' => 15, 'final_price' => 7500]
        ];
        $plan = $plans[$plan_id] ?? $plans['8']; // Default to 8 meals
    }
    
    // Get available menus
    $stmt = $pdo->prepare("
        SELECT m.*, mc.name as category_name, mc.name_thai as category_name_thai
        FROM menus m 
        LEFT JOIN menu_categories mc ON m.category_id = mc.id 
        WHERE m.is_available = 1 
        ORDER BY mc.sort_order ASC, m.name ASC
    ");
    $stmt->execute();
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get menu categories for filters
    $stmt = $pdo->prepare("
        SELECT DISTINCT mc.* 
        FROM menu_categories mc
        INNER JOIN menus m ON mc.id = m.category_id
        WHERE mc.is_active = 1 AND m.is_available = 1
        ORDER BY mc.sort_order ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Fallback data if database fails
    $plan = ['id' => $plan_id, 'name_thai' => 'Selected Package', 'name_english' => 'Selected Package', 'meals_per_week' => 8, 'final_price' => 4500];
    $menus = [
        ['id' => '74184fca-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Pad Thai (Shrimp)', 'name_thai' => 'ผัดไทยกุ้ง', 'base_price' => 320, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Classic Pad Thai with shrimp, tofu, and peanuts.'],
        ['id' => '741955aa-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Pad Thai (Vegan)', 'name_thai' => 'ผัดไทยเจ', 'base_price' => 290, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Vegan Pad Thai with tofu, vegetables, and peanuts.'],
        ['id' => '74195a82-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Thai Basil (Chicken) + Rice', 'name_thai' => 'ผัดกะเพราไก่', 'base_price' => 305, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Spicy chicken stir-fried with basil, served with rice.'],
        ['id' => '74195c1c-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Green Curry (Chicken) + Rice', 'name_thai' => 'แกงเขียวหวานไก่', 'base_price' => 330, 'category_name' => 'Thai Curries', 'category_name_thai' => 'แกงไทย', 'description' => 'Mildly spicy green curry with chicken and rice.'],
        ['id' => '74195d7a-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Vegan Larb (Tofu)', 'name_thai' => 'ลาบเต้าหู้', 'base_price' => 265, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Northeastern style spicy tofu salad.'],
        ['id' => '74195ed8-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Cashew Chicken + Rice', 'name_thai' => 'ไก่ผัดเม็ดมะม่วง', 'base_price' => 320, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Chicken stir-fried with cashews and vegetables, served with rice.'],
        ['id' => '74196068-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Tom Kha (Chicken) + Rice', 'name_thai' => 'ต้มข่าไก่', 'base_price' => 305, 'category_name' => 'Thai Curries', 'category_name_thai' => 'แกงไทย', 'description' => 'Coconut milk soup with chicken and herbs, served with rice.'],
        ['id' => '74196cac-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Beef Crying Tiger + Sticky Rice', 'name_thai' => 'เนื้อเสือร้องไห้', 'base_price' => 385, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Grilled beef with spicy dipping sauce, served with sticky rice.'],
        ['id' => '7419d55c-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Tom Yum (Shrimp) soup', 'name_thai' => 'ต้มยำกุ้ง', 'base_price' => 345, 'category_name' => 'Thai Curries', 'category_name_thai' => 'แกงไทย', 'description' => 'Spicy and sour shrimp soup with Thai herbs.'],
        ['id' => '7419d89a-57ba-11f0-a23d-e6c5297ffe7c', 'name' => 'Chicken Satay', 'name_thai' => 'สะเต๊ะไก่', 'base_price' => 280, 'category_name' => 'Rice Bowls', 'category_name_thai' => 'ข้าวกล่อง', 'description' => 'Grilled chicken skewers served with peanut sauce.']
    ];
    $categories = [
        ['id' => '550e8400-e29b-41d4-a716-446655440005', 'name' => 'Rice Bowls', 'name_thai' => 'ข้าวกล่อง'],
        ['id' => '550e8400-e29b-41d4-a716-446655440006', 'name' => 'Thai Curries', 'name_thai' => 'แกงไทย']
    ];
}

// Handle meal selection submission - UPDATED FOR QUANTITIES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'proceed_to_checkout') {
    header('Content-Type: application/json');
    
    $selected_meals_quantities = json_decode($_POST['selected_meals_quantities'] ?? '{}', true);
    
    if (!is_array($selected_meals_quantities)) {
        echo json_encode(['success' => false, 'message' => 'Invalid menu data. Please try again.']);
        exit;
    }
    
    // Calculate total meals from quantities
    $total_selected = array_sum($selected_meals_quantities);
    
    if ($total_selected !== (int)$plan['meals_per_week']) {
        echo json_encode(['success' => false, 'message' => 'Please select exactly ' . $plan['meals_per_week'] . ' meals (currently selected: ' . $total_selected . ' meals)']);
        exit;
    }
    
    // Convert quantities to traditional format for checkout compatibility
    $selected_meals = [];
    foreach ($selected_meals_quantities as $meal_id => $quantity) {
        for ($i = 0; $i < $quantity; $i++) {
            $selected_meals[] = $meal_id;
        }
    }
    
    // Fetch meal details from database to store in session
    $meal_details = [];
    if (!empty($selected_meals_quantities)) {
        try {
            $meal_ids = array_keys($selected_meals_quantities);
            $placeholders = str_repeat('?,', count($meal_ids) - 1) . '?';
            
            $stmt = $pdo->prepare("
                SELECT m.id, m.name, m.name_thai, m.base_price, m.description, m.main_image_url,
                       mc.name as category_name, mc.name_thai as category_name_thai
                FROM menus m 
                LEFT JOIN menu_categories mc ON m.category_id = mc.id 
                WHERE m.id IN ($placeholders) AND m.is_available = 1
            ");
            
            $stmt->execute($meal_ids);
            $meal_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Create associative array with meal ID as key
            foreach ($meal_results as $meal) {
                $meal_details[$meal['id']] = $meal;
            }
            
            error_log("Fetched meal details for checkout: " . json_encode(array_keys($meal_details)));
            
        } catch (Exception $e) {
            error_log("Error fetching meal details: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error retrieving meal information. Please try again.']);
            exit;
        }
    }
    
    // Verify all selected meals were found
    if (count($meal_details) !== count($selected_meals_quantities)) {
        echo json_encode(['success' => false, 'message' => 'Some selected meals are no longer available. Please refresh and try again.']);
        exit;
    }
    
    // Store data in session for checkout (UPDATED with quantities and meal_details)
    $_SESSION['checkout_data'] = [
        'plan' => $plan,
        'selected_meals' => $selected_meals, // Traditional format for checkout compatibility
        'selected_meals_quantities' => $selected_meals_quantities, // NEW: Quantity data
        'meal_details' => $meal_details,
        'total_meals' => $total_selected,
        'user_id' => $_SESSION['user_id'],
        'created_at' => time(),
        'source' => 'meal-selection'
    ];
    
    error_log("Stored checkout data with " . count($meal_details) . " meal details and quantities: " . json_encode($selected_meals_quantities));
    
    echo json_encode(['success' => true, 'redirect' => 'checkout.php', 'message' => 'Redirecting to checkout...']);
    exit;
}

// Category icons mapping (same as home2.php)
$category_icons = [
    'Rice Bowls' => '<path d="M12 2c-1.1 0-2 .9-2 2v2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-2V4c0-1.1-.9-2-2-2zm0 2v2h-2V4h2zm-4 4h8v2h-8V8zm0 4h8v6H8v-6z"/>',
    'Thai Curries' => '<path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
    'Noodle Dishes' => '<path d="M22 2v20H2V2h20zm-2 2H4v16h16V4zM6 8h12v2H6V8zm0 4h12v2H6v-2zm0 4h8v2H6v-2z"/>',
    'Stir Fry' => '<path d="M12.5 3.5c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5S10.17 2 11 2s1.5.67 1.5 1.5zM20 8H4c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2zm0 10H4v-8h16v8zm-8-6c1.38 0 2.5 1.12 2.5 2.5S13.38 17 12 17s-2.5-1.12-2.5-2.5S10.62 12 12 12z"/>',
    'Rice Dishes' => '<path d="M18 3H6c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H6V5h12v14zM8 7h8v2H8V7zm0 4h8v2H8v-2zm0 4h6v2H8v-2z"/>',
    'Soups' => '<path d="M4 18h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2zm0-10h16v8H4V8zm8-4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>',
    'Salads' => '<path d="M7 10c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm8 0c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8 0-1.12.23-2.18.65-3.15C6.53 8.51 8 8 9.64 8c.93 0 1.83.22 2.64.61.81-.39 1.71-.61 2.64-.61 1.64 0 3.11.51 4.35.85.42.97.65 2.03.65 3.15 0 4.41-3.59 8-8 8z"/>',
    'Desserts' => '<path d="M12 3L8 6.5h8L12 3zm0 18c4.97 0 9-4.03 9-9H3c0 4.97 4.03 9 9 9zm0-16L8.5 8h7L12 5z"/>',
    'Beverages' => '<path d="M5 4v3h5.5v12h3V7H19V4H5z"/>'
];

// Default icon for categories not in mapping
$default_icon = '<path d="M12 2c-1.1 0-2 .9-2 2v2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-2V4c0-1.1-.9-2-2-2zm0 2v2h-2V4h2zm-4 4h8v2h-8V8zm0 4h8v6H8v-6z"/>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Your Meals | Somdul Table</title>
    <meta name="description" content="Select your perfect meals from authentic Thai cuisine - Somdul Table delivers fresh, healthy meals to your door">
    
    <style>
        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 120px 2rem 4rem;
        }

        /* Progress Bar */
        .progress-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 3rem;
            box-shadow: var(--shadow-soft);
        }

        .progress-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .progress-step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.95rem;
            font-family: 'BaticaSans', sans-serif;
            background: var(--white);
            color: var(--text-gray);
            border: 2px solid var(--cream);
            transition: var(--transition);
            white-space: nowrap;
        }

        .progress-step.active {
            background: var(--brown);
            color: var(--white);
            border-color: var(--brown);
            box-shadow: 0 4px 12px rgba(189, 147, 121, 0.3);
        }

        .progress-step.completed {
            background: var(--sage);
            color: var(--white);
            border-color: var(--sage);
        }

        .progress-arrow {
            color: var(--sage);
            font-size: 1.2rem;
            font-weight: 600;
        }

        /* Selection Counter */
        .selection-counter {
            background: var(--white);
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 120px;
            z-index: 50;
            box-shadow: var(--shadow-soft);
        }

        .counter-text {
            font-size: 1.1rem;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
        }

        .counter-number {
            color: var(--curry);
            font-size: 1.3rem;
            font-weight: 800;
        }

        .counter-complete {
            color: var(--success);
        }

        /* UPDATED: Filters Section to match menu-nav-container style */
        .menu-nav-container {
            margin-bottom: 32px;
            width: 100%;
            padding: 20px 0;
            background: var(--cream); /* LEVEL 2: Cream background */
            border-radius: 0;
            box-shadow: none;
            border-top: 1px solid rgba(189, 147, 121, 0.1);
            border-bottom: 1px solid rgba(189, 147, 121, 0.1);
        }

        .menu-nav-container,
        .menu-nav-container * {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        .menu-nav-wrapper {
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding: 0 1rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .menu-nav-wrapper::-webkit-scrollbar {
            display: none;
        }

        .menu-nav-list {
            display: flex;
            gap: 0;
            min-width: max-content;
            align-items: center;
            justify-content: flex-start;
            margin-bottom: 1rem;
        }

        .menu-nav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            height: 54px;
            padding: 0 16px;
            border: none;
            border-bottom: 2px solid transparent;
            background: transparent;
            cursor: pointer;
            font-family: 'BaticaSans', Arial, sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: #707070;
            transition: all 0.3s ease;
            white-space: nowrap;
            text-decoration: none;
            border-radius: var(--radius-sm);
            outline: none !important;
            -webkit-tap-highlight-color: transparent;
        }

        .menu-nav-item:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(189, 147, 121, 0.3);
        }

        .menu-nav-item:hover {
            color: var(--brown); /* LEVEL 1: Brown hover */
            background: rgba(189, 147, 121, 0.1); /* Light brown background */
            border-bottom-color: var(--brown); /* LEVEL 1: Brown */
        }

        .menu-nav-item.active {
            color: var(--brown); /* LEVEL 1: Brown active */
            background: var(--white); /* LEVEL 1: White background */
            border-bottom-color: var(--brown); /* LEVEL 1: Brown */
            box-shadow: var(--shadow-soft);
        }

        .menu-nav-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .menu-nav-icon svg {
            width: 100%;
            height: 100%;
            fill: #707070;
            transition: fill 0.3s ease;
        }

        .menu-nav-item:hover .menu-nav-icon svg {
            fill: var(--brown); /* LEVEL 1: Brown */
        }

        .menu-nav-item.active .menu-nav-icon svg {
            fill: var(--brown); /* LEVEL 1: Brown */
        }

        .menu-nav-text {
            font-size: 14px;
            font-weight: 600;
        }

        /* Search box within the nav container */
        .search-box {
            width: 100%;
            max-width: 400px;
            position: relative;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 2.8rem;
            border: 2px solid var(--border-light);
            border-radius: 50px;
            font-size: 1rem;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
            background: var(--white);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--brown);
            box-shadow: 0 0 15px rgba(189, 147, 121, 0.2);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-gray);
            font-size: 1rem;
        }

        /* Menu Grid - 5 columns on desktop */
        .meals-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .meal-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-light);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
            cursor: pointer;
        }

        .meal-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
            border-color: var(--sage);
        }

        .meal-card.selected {
            border-color: var(--curry);
            box-shadow: 0 8px 32px rgba(207, 114, 58, 0.25);
            transform: translateY(-2px);
        }

        .meal-image {
            position: relative;
            height: 160px;
            overflow: hidden;
            background: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--text-gray);
        }

        .meal-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .meal-card:hover .meal-image img {
            transform: scale(1.05);
        }

        .meal-badge {
            position: absolute;
            top: 0.8rem;
            left: 0.8rem;
            background: rgba(255, 255, 255, 0.95);
            color: var(--curry);
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            font-family: 'BaticaSans', sans-serif;
        }

        /* NEW: Quantity Badge */
        .quantity-badge {
            position: absolute;
            top: 0.8rem;
            right: 0.8rem;
            background: var(--curry);
            color: var(--white);
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 700;
            opacity: 0;
            transform: scale(0.5);
            transition: var(--transition);
            font-family: 'BaticaSans', sans-serif;
        }

        .meal-card.selected .quantity-badge {
            opacity: 1;
            transform: scale(1);
        }

        .meal-content {
            padding: 1.2rem;
        }

        .meal-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--brown); /* LEVEL 1: Brown title */
            margin-bottom: 0.6rem;
            line-height: 1.3;
            font-family: 'BaticaSans', sans-serif;
        }

        .meal-description {
            color: var(--text-gray);
            font-size: 0.8rem;
            line-height: 1.4;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-family: 'BaticaSans', sans-serif;
        }

        .meal-footer {
            display: flex;
            justify-content: center;
            align-items: center;
            padding-top: 0.8rem;
            border-top: 1px solid var(--border-light);
        }

        /* NEW: Quantity Controls */
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            justify-content: center;
        }

        .quantity-btn {
            background: var(--curry);
            color: var(--white);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'BaticaSans', sans-serif;
        }

        .quantity-btn:hover {
            background: var(--brown);
            transform: scale(1.1);
        }

        .quantity-btn:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            transform: none;
            opacity: 0.5;
        }

        .quantity-display {
            background: var(--cream);
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 0.4rem 0.8rem;
            font-weight: 700;
            color: var(--brown);
            min-width: 40px;
            text-align: center;
            font-family: 'BaticaSans', sans-serif;
            font-size: 0.9rem;
        }

        .add-meal-btn {
            background: var(--curry);
            color: var(--white);
            border: none;
            padding: 0.5rem 0.8rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-family: 'BaticaSans', sans-serif;
            width: 100%;
            justify-content: center;
        }

        .add-meal-btn:hover {
            background: var(--brown);
            transform: translateY(-2px);
        }

        .add-meal-btn:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            transform: none;
        }

        /* Continue Button */
        .continue-section {
            position: sticky;
            bottom: 0;
            background: var(--white);
            border-top: 2px solid var(--border-light);
            padding: 2rem 0;
            margin-top: 3rem;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.05);
        }

        .continue-btn {
            width: 100%;
            background: var(--brown); /* LEVEL 1: Brown primary */
            color: var(--white); /* LEVEL 1: White */
            border: none;
            padding: 1.2rem 2rem;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-family: 'BaticaSans', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            box-shadow: var(--shadow-soft);
        }

        .continue-btn:hover:not(:disabled) {
            background: #a8855f; /* Darker brown */
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .continue-btn:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
            box-shadow: none;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            color: var(--brown); /* LEVEL 1: Brown heading */
            font-family: 'BaticaSans', sans-serif;
        }

        /* Loading States */
        .loading {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Toast Notifications */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--white);
            border-left: 4px solid;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-medium);
            padding: 1rem 1.5rem;
            min-width: 300px;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            z-index: 2000;
            font-family: 'BaticaSans', sans-serif;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            border-left-color: var(--success);
        }

        .toast.error {
            border-left-color: var(--danger);
        }

        .toast.warning {
            border-left-color: var(--warning);
        }

        /* Animation for meal selection */
        @keyframes selectPulse {
            0% { transform: scale(1) translateY(-2px); }
            50% { transform: scale(1.02) translateY(-4px); }
            100% { transform: scale(1) translateY(-2px); }
        }

        .meal-card.just-selected {
            animation: selectPulse 0.4s ease-out;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .meals-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 968px) {
            .meals-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 100px 1rem 3rem;
            }

            .menu-nav-list {
                justify-content: flex-start;
            }

            .menu-nav-item {
                padding: 0 12px;
                font-size: 13px;
            }
            
            .menu-nav-icon {
                width: 20px;
                height: 20px;
            }

            .meals-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }

            .progress-bar {
                gap: 0.5rem;
            }

            .progress-step {
                font-size: 0.8rem;
                padding: 0.6rem 1rem;
            }

            .progress-arrow {
                font-size: 1rem;
            }

            .selection-counter {
                position: static;
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }

            /* Mobile quantity controls */
            .quantity-btn {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }

            .quantity-display {
                min-width: 44px;
                padding: 0.5rem 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .progress-step {
                font-size: 0.7rem;
                padding: 0.5rem 0.8rem;
            }

            .meals-grid {
                grid-template-columns: 1fr;
            }

            .meal-content {
                padding: 1rem;
            }

            .quantity-controls {
                gap: 0.8rem;
            }

            .add-meal-btn {
                justify-content: center;
                padding: 0.8rem;
            }
        }
    </style>
</head>
<body class="has-header">
    <!-- Include Header - This handles all navigation and fonts -->
    <?php include 'header.php'; ?>

    <div class="container">
        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-step completed">
                    <i class="fas fa-check-circle"></i>
                    <span>Choose Package</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step active">
                    <i class="fas fa-utensils"></i>
                    <span>Select Menu</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step">
                    <i class="fas fa-credit-card"></i>
                    <span>Payment</span>
                </div>
                <span class="progress-arrow">→</span>
                <div class="progress-step">
                    <i class="fas fa-check-double"></i>
                    <span>Complete</span>
                </div>
            </div>
        </div>

        <!-- Selection Counter -->
        <div class="selection-counter">
            <div class="counter-text">
                Selected: <span class="counter-number" id="selectedCount">0</span>/<span class="counter-number"><?php echo $plan['meals_per_week']; ?></span> meals
            </div>
            <div id="selectionStatus" class="counter-text" style="color: var(--text-gray);">
                Please select the required number of meals
            </div>
        </div>

        <!-- Updated Filters Section to match menu-nav-container style -->
        <div class="menu-nav-container">
            <div class="menu-nav-wrapper">
                <!-- Search Box -->
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Search for meals you want...">
                </div>
                
                <!-- Category Navigation -->
                <div class="menu-nav-list">
                    <!-- All items button -->
                    <button class="menu-nav-item active" data-category="all">
                        <span class="menu-nav-icon">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                            </svg>
                        </span>
                        <span class="menu-nav-text">All Items</span>
                    </button>
                    
                    <!-- Dynamic categories from database -->
                    <?php foreach ($categories as $category): ?>
                        <?php 
                        $category_name = getCategoryName($category);
                        $icon_path = $category_icons[$category_name] ?? $default_icon;
                        ?>
                        <button class="menu-nav-item" data-category="<?php echo htmlspecialchars($category['id']); ?>">
                            <span class="menu-nav-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <?php echo $icon_path; ?>
                                </svg>
                            </span>
                            <span class="menu-nav-text">
                                <?php echo htmlspecialchars($category_name); ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Meals Grid -->
        <div class="meals-grid" id="mealsGrid">
            <?php if (!empty($menus)): ?>
                <?php foreach ($menus as $menu): ?>
                    <div class="meal-card" 
                         data-menu-id="<?php echo $menu['id']; ?>"
                         data-category="<?php echo $menu['category_id'] ?? ''; ?>"
                         data-name="<?php echo strtolower(getMenuName($menu) . ' ' . ($menu['name_thai'] ?? '')); ?>"
                         data-price="<?php echo $menu['base_price']; ?>">
                        
                        <div class="meal-image">
                            <?php if (!empty($menu['main_image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($menu['main_image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars(getMenuName($menu)); ?>" 
                                     loading="lazy">
                            <?php else: ?>
                                <div style="text-align: center;">
                                    <i class="fas fa-utensils" style="font-size: 1.5rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                    <br><span style="font-size: 0.8rem;"><?php echo htmlspecialchars(getMenuName($menu)); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($menu['category_name']) || !empty($menu['category_name_thai'])): ?>
                                <div class="meal-badge">
                                    <?php echo htmlspecialchars($menu['category_name'] ?: $menu['category_name_thai']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- NEW: Quantity Badge -->
                            <div class="quantity-badge" id="badge-<?php echo $menu['id']; ?>">
                                1
                            </div>
                        </div>
                        
                        <div class="meal-content">
                            <h3 class="meal-title">
                                <?php echo htmlspecialchars(getMenuName($menu)); ?>
                            </h3>
                            
                            <?php if (!empty($menu['description'])): ?>
                                <p class="meal-description">
                                    <?php echo htmlspecialchars($menu['description']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="meal-footer">
                                <!-- NEW: Quantity Controls (hidden by default) -->
                                <div class="quantity-controls" id="controls-<?php echo $menu['id']; ?>" style="display: none;">
                                    <button class="quantity-btn" onclick="event.stopPropagation(); updateMealQuantity('<?php echo $menu['id']; ?>', -1)">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <div class="quantity-display" id="quantity-<?php echo $menu['id']; ?>">1</div>
                                    <button class="quantity-btn" onclick="event.stopPropagation(); updateMealQuantity('<?php echo $menu['id']; ?>', 1)">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                
                                <!-- Add Button (shown by default) -->
                                <button class="add-meal-btn" id="add-btn-<?php echo $menu['id']; ?>" onclick="event.stopPropagation(); addFirstMeal('<?php echo $menu['id']; ?>')">
                                    <i class="fas fa-plus"></i>
                                    <span>Add</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-utensils"></i>
                    <h3>No Meals Available</h3>
                    <p>Sorry, there are currently no meals available to select</p>
                    <a href="subscribe.php" style="margin-top: 1rem; display: inline-block; padding: 0.8rem 1.5rem; background: var(--curry); color: var(--white); text-decoration: none; border-radius: var(--radius-md);">Back to Packages</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Continue Section -->
        <div class="continue-section">
            <button class="continue-btn" id="continueBtn" onclick="proceedToCheckout()" disabled>
                <i class="fas fa-shopping-cart"></i>
                Continue - Proceed to Payment
            </button>
        </div>
    </div>

    <!-- Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>

    <script>
        // HAMBURGER MENU FIX - Use this code block
        function fixHamburgerMenu() {
            const hamburger = document.getElementById('mobileMenuToggle');
            if (!hamburger) return;
            
            hamburger.style.cssText = `display: block !important; position: relative !important; z-index: 1105 !important; pointer-events: auto !important; cursor: pointer !important;`;
            
            const newHamburger = hamburger.cloneNode(true);
            hamburger.parentNode.replaceChild(newHamburger, hamburger);
            
            newHamburger.addEventListener('click', function(e) {
                e.preventDefault(); e.stopPropagation();
                const mobileMenu = document.getElementById('mobileNavMenu');
                const hamburgerIcon = newHamburger.querySelector('.hamburger');
                if (mobileMenu && hamburgerIcon) {
                    mobileMenu.classList.toggle('active');
                    hamburgerIcon.classList.toggle('open');
                    document.body.style.overflow = mobileMenu.classList.contains('active') ? 'hidden' : 'auto';
                }
            }, { capture: true });
        }

        // Global variables - UPDATED FOR QUANTITIES
        const maxMeals = <?php echo $plan['meals_per_week']; ?>;
        let selectedMealsQuantities = {}; // NEW: Object to track {meal_id: quantity}
        let allMeals = [];

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Updated meal selection page loaded with QUANTITY SUPPORT');
            
            // Apply hamburger fix after 1 second (let header load)
            setTimeout(fixHamburgerMenu, 1000);
            
            // Store all meal data
            document.querySelectorAll('.meal-card').forEach(card => {
                allMeals.push({
                    id: card.dataset.menuId,
                    category: card.dataset.category,
                    name: card.dataset.name,
                    price: parseFloat(card.dataset.price),
                    element: card
                });
            });

            // Set up event listeners
            setupEventListeners();
            updateUI();
            loadSelectionState();
            
            // Highlight menu if specified
            <?php if ($highlight_menu_id): ?>
            const highlightCard = document.querySelector('[data-menu-id="<?php echo $highlight_menu_id; ?>"]');
            if (highlightCard) {
                highlightCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                highlightCard.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    highlightCard.style.transform = '';
                }, 1000);
            }
            <?php endif; ?>
        });

        function setupEventListeners() {
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', debounce(filterMeals, 300));

            // Category filters - Updated for new nav structure
            document.querySelectorAll('.menu-nav-item').forEach(filter => {
                filter.addEventListener('click', function() {
                    // Update active filter
                    document.querySelectorAll('.menu-nav-item').forEach(f => f.classList.remove('active'));
                    this.classList.add('active');
                    filterMeals();
                });
            });
        }

        // NEW: Add first meal function
        function addFirstMeal(mealId) {
            console.log('🍽️ Adding first meal:', mealId);
            
            // Check if we can add more meals
            const totalSelected = getTotalSelectedMeals();
            if (totalSelected >= maxMeals) {
                showToast('warning', `You have already selected the maximum number of meals (${maxMeals} meals)`);
                return;
            }
            
            // Add meal with quantity 1
            selectedMealsQuantities[mealId] = 1;
            
            // Update UI for this meal
            const mealCard = document.querySelector(`[data-menu-id="${mealId}"]`);
            const addBtn = document.getElementById(`add-btn-${mealId}`);
            const controls = document.getElementById(`controls-${mealId}`);
            const quantityDisplay = document.getElementById(`quantity-${mealId}`);
            const badge = document.getElementById(`badge-${mealId}`);
            
            // Hide add button, show controls
            addBtn.style.display = 'none';
            controls.style.display = 'flex';
            
            // Update displays
            quantityDisplay.textContent = '1';
            badge.textContent = '1';
            
            // Update card styling
            mealCard.classList.add('selected', 'just-selected');
            
            // Remove animation class after animation completes
            setTimeout(() => {
                mealCard.classList.remove('just-selected');
            }, 400);
            
            updateUI();
            saveSelectionState();
            checkMealLimits();
        }

        // NEW: Update meal quantity function
        function updateMealQuantity(mealId, change) {
            console.log('🔄 Updating meal quantity:', mealId, 'change:', change);
            
            const currentQuantity = selectedMealsQuantities[mealId] || 0;
            const newQuantity = currentQuantity + change;
            
            // Validate new quantity
            if (newQuantity < 0) return;
            
            // Check total meal limit when increasing
            if (change > 0) {
                const totalSelected = getTotalSelectedMeals();
                if (totalSelected >= maxMeals) {
                    showToast('warning', `You have already selected the maximum number of meals (${maxMeals} meals)`);
                    return;
                }
            }
            
            const mealCard = document.querySelector(`[data-menu-id="${mealId}"]`);
            const addBtn = document.getElementById(`add-btn-${mealId}`);
            const controls = document.getElementById(`controls-${mealId}`);
            const quantityDisplay = document.getElementById(`quantity-${mealId}`);
            const badge = document.getElementById(`badge-${mealId}`);
            
            if (newQuantity === 0) {
                // Remove meal completely
                delete selectedMealsQuantities[mealId];
                
                // Show add button, hide controls
                addBtn.style.display = 'flex';
                controls.style.display = 'none';
                
                // Update card styling
                mealCard.classList.remove('selected');
                
            } else {
                // Update quantity
                selectedMealsQuantities[mealId] = newQuantity;
                
                // Update displays
                quantityDisplay.textContent = newQuantity.toString();
                badge.textContent = newQuantity.toString();
                
                // Ensure card is marked as selected
                mealCard.classList.add('selected');
                
                // Show controls if not visible
                if (controls.style.display === 'none') {
                    addBtn.style.display = 'none';
                    controls.style.display = 'flex';
                }
            }
            
            updateUI();
            saveSelectionState();
            checkMealLimits();
        }

        // NEW: Get total selected meals across all quantities
        function getTotalSelectedMeals() {
            return Object.values(selectedMealsQuantities).reduce((sum, quantity) => sum + quantity, 0);
        }

        // NEW: Check meal limits and disable/enable buttons
        function checkMealLimits() {
            const totalSelected = getTotalSelectedMeals();
            const isAtLimit = totalSelected >= maxMeals;
            
            // Disable/enable add buttons for meals not yet selected
            document.querySelectorAll('.add-meal-btn').forEach(btn => {
                const mealId = btn.id.replace('add-btn-', '');
                const isSelected = selectedMealsQuantities.hasOwnProperty(mealId);
                
                if (!isSelected && isAtLimit) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-lock"></i><span>Limit Reached</span>';
                } else if (!isSelected) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-plus"></i><span>Add</span>';
                }
            });
            
            // Disable/enable plus buttons for selected meals
            document.querySelectorAll('.quantity-controls').forEach(controls => {
                const mealId = controls.id.replace('controls-', '');
                const plusBtn = controls.querySelector('.quantity-btn:last-child');
                
                if (plusBtn) {
                    plusBtn.disabled = isAtLimit;
                }
            });
        }

        function updateUI() {
            const totalSelected = getTotalSelectedMeals();
            const countElement = document.getElementById('selectedCount');
            const statusElement = document.getElementById('selectionStatus');
            const continueBtn = document.getElementById('continueBtn');
            
            // Update counter
            countElement.textContent = totalSelected;
            countElement.className = totalSelected === maxMeals ? 'counter-number counter-complete' : 'counter-number';
            
            // Update status message
            if (totalSelected === 0) {
                statusElement.textContent = 'Please select the required number of meals';
                statusElement.style.color = 'var(--text-gray)';
            } else if (totalSelected < maxMeals) {
                const remaining = maxMeals - totalSelected;
                statusElement.textContent = `Select ${remaining} more meal${remaining === 1 ? '' : 's'}`;
                statusElement.style.color = 'var(--warning)';
            } else {
                statusElement.textContent = '✅ Selection complete! Ready to proceed';
                statusElement.style.color = 'var(--success)';
            }
            
            // Update continue button
            continueBtn.disabled = totalSelected !== maxMeals;
            
            // Update page title
            document.title = `Select Meals (${totalSelected}/${maxMeals}) - ${<?php echo json_encode(getPlanName($plan)); ?>} | Somdul Table`;
        }

        function filterMeals() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const activeCategory = document.querySelector('.menu-nav-item.active').dataset.category;
            
            let visibleCount = 0;
            
            allMeals.forEach(meal => {
                let visible = true;
                
                // Category filter
                if (activeCategory !== 'all' && meal.category !== activeCategory) {
                    visible = false;
                }
                
                // Search filter
                if (searchTerm && !meal.name.includes(searchTerm)) {
                    visible = false;
                }
                
                // Apply visibility
                meal.element.style.display = visible ? 'block' : 'none';
                if (visible) visibleCount++;
            });
            
            // Show empty state if no results
            const existingEmptyState = document.querySelector('.temp-empty');
            if (existingEmptyState) existingEmptyState.remove();
            
            if (visibleCount === 0 && searchTerm) {
                const mealsGrid = document.getElementById('mealsGrid');
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'empty-state temp-empty';
                emptyDiv.innerHTML = `
                    <i class="fas fa-search"></i>
                    <h3>No meals found</h3>
                    <p>Try using different search terms or select a different category</p>
                `;
                mealsGrid.appendChild(emptyDiv);
            }
        }

        function proceedToCheckout() {
            const totalSelected = getTotalSelectedMeals();
            if (totalSelected !== maxMeals) {
                showToast('error', 'Please select the exact number of meals required');
                return;
            }
            
            console.log('🚀 Proceeding to checkout with meal quantities:', selectedMealsQuantities);
            
            // Show loading state
            const continueBtn = document.getElementById('continueBtn');
            const originalText = continueBtn.innerHTML;
            continueBtn.innerHTML = '<div class="loading"></div> Processing...';
            continueBtn.disabled = true;
            
            // Send selected meals with quantities to server
            const formData = new FormData();
            formData.append('action', 'proceed_to_checkout');
            formData.append('selected_meals_quantities', JSON.stringify(selectedMealsQuantities));
            
            fetch('meal-selection.php?plan=<?php echo urlencode($plan_id); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('✅ Server response:', data);
                if (data.success) {
                    localStorage.removeItem('mealSelection');
                    showToast('success', 'Meal selection saved! Redirecting to checkout...');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    showToast('error', data.message);
                    continueBtn.innerHTML = originalText;
                    continueBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('❌ Error:', error);
                showToast('error', 'An error occurred. Please try again.');
                continueBtn.innerHTML = originalText;
                continueBtn.disabled = false;
            });
        }

        // Utility functions
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function showToast(type, message) {
            const toast = document.createElement('div');
            toast.className = `toast ${type} show`;
            toast.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.8rem;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle'}" style="font-size: 1.2rem;"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 300);
            }, 4000);
        }

        // Save/Load selection state - UPDATED FOR QUANTITIES
        function saveSelectionState() {
            localStorage.setItem('mealSelection', JSON.stringify({
                planId: '<?php echo $plan_id; ?>',
                selectedMealsQuantities: selectedMealsQuantities,
                timestamp: Date.now()
            }));
        }

        function loadSelectionState() {
            const saved = localStorage.getItem('mealSelection');
            if (saved) {
                const data = JSON.parse(saved);
                // Only restore if it's the same plan and less than 1 hour old
                if (data.planId === '<?php echo $plan_id; ?>' && Date.now() - data.timestamp < 3600000) {
                    // Restore each meal with its quantity
                    Object.entries(data.selectedMealsQuantities || {}).forEach(([mealId, quantity]) => {
                        if (document.querySelector(`[data-menu-id="${mealId}"]`)) {
                            // First add the meal
                            addFirstMeal(mealId);
                            // Then set the correct quantity if > 1
                            if (quantity > 1) {
                                for (let i = 1; i < quantity; i++) {
                                    updateMealQuantity(mealId, 1);
                                }
                            }
                        }
                    });
                }
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && getTotalSelectedMeals() === maxMeals) {
                proceedToCheckout();
            }
            
            if (e.key === 'Escape') {
                document.getElementById('searchInput').value = '';
                filterMeals();
            }
        });
    </script>
</body>
</html>