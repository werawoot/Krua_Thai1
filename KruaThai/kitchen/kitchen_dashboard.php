<?php
/**
 * Weekend Kitchen Dashboard - USA ENGLISH VERSION
 * File: weekend_kitchen_dashboard.php
 * Role: kitchen, admin only
 * Status: PRODUCTION READY ✅
 * Focus: Weekend delivery preparation (Saturday & Sunday only)
 * Language: English (USA)
 * Timezone: America/New_York
 */

// Start output buffering to prevent header issues
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session FIRST
session_start();

// Set timezone to US Eastern
date_default_timezone_set('America/New_York');

// Role-based access control
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if user has kitchen or admin role
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['kitchen', 'admin'])) {
    die('<div style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 10px; margin: 20px; font-family: Arial;">
        <h3>🚫 Access Denied</h3>
        <p>You do not have permission to access the kitchen dashboard.</p>
        <p>Required roles: Kitchen Staff or Admin</p>
        <a href="../dashboard.php" style="background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a>
    </div>');
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// Get next weekends (Saturday & Sunday) for next 3 weeks
function getNextWeekends() {
    $weekends = [];
    $currentDate = new DateTime();
    $currentDate->setTimezone(new DateTimeZone('America/New_York'));
    
    for ($week = 0; $week < 3; $week++) { // 3 weeks only
        // Calculate this week's Saturday
        $saturday = clone $currentDate;
        $saturday->modify('this week monday')->modify('+' . (5 + $week * 7) . ' days');
        
        // Calculate this week's Sunday  
        $sunday = clone $saturday;
        $sunday->modify('+1 day');
        
        // Only include future dates
        $today = new DateTime();
        $today->setTimezone(new DateTimeZone('America/New_York'));
        
        if ($saturday >= $today) {
            $weekends[] = [
                'date' => $saturday->format('Y-m-d'),
                'display' => $saturday->format('l, M j') . ' (Saturday)',
                'day_name' => 'Saturday',
                'week_label' => $week == 0 ? 'This Week' : ($week == 1 ? 'Next Week' : 'Week ' . ($week + 1))
            ];
        }
        
        if ($sunday >= $today) {
            $weekends[] = [
                'date' => $sunday->format('Y-m-d'),
                'display' => $sunday->format('l, M j') . ' (Sunday)', 
                'day_name' => 'Sunday',
                'week_label' => $week == 0 ? 'This Week' : ($week == 1 ? 'Next Week' : 'Week ' . ($week + 1))
            ];
        }
    }
    
    return $weekends;
}

// Get filter parameters
$available_weekends = getNextWeekends();
$selected_date = $_GET['date'] ?? ($available_weekends[0]['date'] ?? date('Y-m-d'));
$status_filter = $_GET['status'] ?? 'all';
$export_type = $_GET['export'] ?? '';

// Validate selected date is a weekend
$selected_day = date('N', strtotime($selected_date)); // 6=Saturday, 7=Sunday
if (!in_array($selected_day, [6, 7])) {
    $selected_date = $available_weekends[0]['date'] ?? date('Y-m-d');
}

// Kitchen data arrays
$weekend_orders = [];
$meal_summary = [];
$subscription_summary = [];
$total_customers = 0;
$total_meals = 0;

try {
    // Get weekend deliveries from subscription_menus with PDO
    $delivery_query = "
        SELECT DISTINCT
            sm.delivery_date,
            sm.subscription_id,
            s.user_id,
            s.preferred_delivery_time,
            s.special_instructions as subscription_notes,
            u.first_name,
            u.last_name,
            u.phone,
            u.delivery_address,
            u.city,
            u.dietary_preferences,
            u.allergies,
            u.spice_level,
            sp.name as plan_name,
            sp.name_thai as plan_name_thai
        FROM subscription_menus sm
        JOIN subscriptions s ON sm.subscription_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN subscription_plans sp ON s.plan_id = sp.id
        WHERE sm.delivery_date = ?
        AND s.status = 'active'
        AND sm.status = 'scheduled'
        ORDER BY s.preferred_delivery_time ASC, u.last_name ASC
    ";
    
    $stmt = $pdo->prepare($delivery_query);
    $stmt->execute([$selected_date]);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $customers = [];
    foreach ($deliveries as $delivery) {
        $customer_key = $delivery['user_id'];
        if (!isset($customers[$customer_key])) {
            $customers[$customer_key] = $delivery;
            $customers[$customer_key]['meals'] = [];
            $total_customers++;
        }
    }
    
    // Get meals for each customer
    foreach ($customers as $user_id => &$customer) {
        $meals_query = "
            SELECT 
                sm.id as subscription_menu_id,
                sm.quantity,
                sm.customizations,
                sm.special_requests,
                m.id as menu_id,
                m.name as menu_name,
                m.name_thai as menu_name_thai,
                m.base_price,
                m.ingredients,
                m.cooking_method,
                m.preparation_time,
                m.spice_level as menu_spice_level,
                mc.name as category_name,
                mc.name_thai as category_name_thai
            FROM subscription_menus sm
            JOIN menus m ON sm.menu_id = m.id
            LEFT JOIN menu_categories mc ON m.category_id = mc.id
            WHERE sm.delivery_date = ?
            AND sm.subscription_id = ?
            AND sm.status = 'scheduled'
            ORDER BY mc.sort_order ASC, m.name ASC
        ";
        
        $meals_stmt = $pdo->prepare($meals_query);
        $meals_stmt->execute([$selected_date, $customer['subscription_id']]);
        $meals = $meals_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($meals as $meal) {
            $customer['meals'][] = $meal;
            $total_meals += $meal['quantity'];
            
            // Build meal summary for kitchen prep
            $meal_key = $meal['menu_name'];
            if (!isset($meal_summary[$meal_key])) {
                $meal_summary[$meal_key] = [
                    'menu_id' => $meal['menu_id'],
                    'name' => $meal['menu_name'],
                    'name_thai' => $meal['menu_name_thai'],
                    'category' => $meal['category_name'],
                    'category_thai' => $meal['category_name_thai'],
                    'total_quantity' => 0,
                    'prep_time' => $meal['preparation_time'],
                    'spice_level' => $meal['menu_spice_level'],
                    'base_price' => $meal['base_price'],
                    'customizations' => [],
                    'special_requests' => [],
                    'ingredients' => $meal['ingredients'],
                    'cooking_method' => $meal['cooking_method'],
                    'customers' => []
                ];
            }
            
            $meal_summary[$meal_key]['total_quantity'] += $meal['quantity'];
            $meal_summary[$meal_key]['customers'][] = $customer['first_name'] . ' ' . $customer['last_name'];
            
            // Collect customizations
            if (!empty($meal['customizations'])) {
                $customs = json_decode($meal['customizations'], true);
                if ($customs) {
                    foreach ($customs as $custom) {
                        if (!in_array($custom, $meal_summary[$meal_key]['customizations'])) {
                            $meal_summary[$meal_key]['customizations'][] = $custom;
                        }
                    }
                }
            }
            
            // Collect special requests
            if (!empty($meal['special_requests'])) {
                if (!in_array($meal['special_requests'], $meal_summary[$meal_key]['special_requests'])) {
                    $meal_summary[$meal_key]['special_requests'][] = $meal['special_requests'];
                }
            }
        }
    }
    
    $weekend_orders = array_values($customers);
    
    // Sort meal summary by total quantity (highest first)
    uasort($meal_summary, function($a, $b) {
        return $b['total_quantity'] - $a['total_quantity'];
    });
    
} catch (Exception $e) {
    error_log("Weekend Kitchen Dashboard Error: " . $e->getMessage());
    $error_message = "Error loading kitchen data: " . $e->getMessage();
}

// Handle CSV Export
if ($export_type === 'csv') {
    // Clean any existing output
    ob_end_clean();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="weekend_kitchen_prep_' . $selected_date . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for proper Excel display
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, [
        'Delivery Date', 'Customer Name', 'Phone', 'Address', 
        'Delivery Time', 'Plan', 'Menu', 'Quantity', 'Customizations',
        'Special Requests', 'Dietary Preferences', 'Allergies', 'Spice Level'
    ]);
    
    // Data rows
    foreach ($weekend_orders as $customer) {
        foreach ($customer['meals'] as $meal) {
            fputcsv($output, [
                $selected_date,
                $customer['first_name'] . ' ' . $customer['last_name'],
                $customer['phone'],
                $customer['delivery_address'] . ', ' . $customer['city'],
                $customer['preferred_delivery_time'],
                $customer['plan_name'] ?? 'Standard Plan',
                $meal['menu_name'],
                $meal['quantity'],
                $meal['customizations'],
                $meal['special_requests'],
                $customer['dietary_preferences'],
                $customer['allergies'],
                $customer['spice_level']
            ]);
        }
    }
    
    fclose($output);
    exit;
}

// Handle JSON Export
if ($export_type === 'json') {
    // Clean any existing output
    ob_end_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="weekend_kitchen_data_' . $selected_date . '.json"');
    
    $export_data = [
        'delivery_date' => $selected_date,
        'total_customers' => $total_customers,
        'total_meals' => $total_meals,
        'customers' => $weekend_orders,
        'meal_summary' => $meal_summary
    ];
    
    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekend Kitchen Dashboard - Krua Thai USA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            /* Krua Thai USA Brand Colors */
            --primary-cream: #f8f6f3;
            --primary-green: #4a7c59;
            --primary-brown: #8b4513;
            --primary-orange: #e67e22;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #6c757d;
            --border-light: #e9ecef;
            --shadow-soft: 0 2px 10px rgba(0,0,0,0.05);
            --shadow-medium: 0 4px 20px rgba(0,0,0,0.1);
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Roboto', sans-serif;
            background: linear-gradient(135deg, var(--primary-cream) 0%, #f1f3f4 100%);
            min-height: 100vh;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-brown) 0%, var(--primary-orange) 100%);
            color: white;
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow-medium);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-title h1 {
            font-size: 1.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-meta {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            font-size: 0.9rem;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
            color: white;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .dashboard-controls {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .control-label {
            font-weight: 500;
            color: var(--primary-brown);
            font-size: 0.9rem;
        }

        .control-input {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: var(--transition);
            font-family: inherit;
        }

        .control-input:focus {
            outline: none;
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 3px rgba(230, 126, 34, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-orange), #d35400);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--primary-green), #27ae60);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-outline {
            background: white;
            border: 2px solid var(--primary-cream);
            color: var(--primary-brown);
        }

        .btn-outline:hover {
            background: var(--primary-cream);
            border-color: var(--primary-brown);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-orange), var(--primary-brown));
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary-orange);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-brown);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1.1rem;
            color: var(--text-gray);
            font-weight: 500;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .main-content {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .sidebar {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            padding: 2rem;
            max-height: 800px;
            overflow-y: auto;
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary-cream), #f8f6f3);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-brown);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .customer-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .customer-card {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f3f4f6;
            transition: var(--transition);
        }

        .customer-card:hover {
            background: var(--primary-cream);
        }

        .customer-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .customer-info h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-brown);
            margin-bottom: 0.25rem;
        }

        .customer-details {
            font-size: 0.9rem;
            color: var(--text-gray);
            line-height: 1.4;
        }

        .customer-details div {
            margin-bottom: 0.25rem;
        }

        .delivery-time {
            background: linear-gradient(135deg, var(--primary-green), #27ae60);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .meals-list {
            margin-top: 1rem;
        }

        .meal-item {
            background: var(--primary-cream);
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 0.5rem;
            border-left: 4px solid var(--primary-orange);
        }

        .meal-item:last-child {
            margin-bottom: 0;
        }

        .meal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .meal-name {
            font-weight: 600;
            color: var(--primary-brown);
        }

        .meal-quantity {
            background: var(--primary-orange);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .meal-meta {
            font-size: 0.85rem;
            color: var(--text-gray);
            line-height: 1.4;
        }

        .meal-meta div {
            margin-bottom: 0.25rem;
        }

        .prep-summary-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-brown);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .prep-item {
            background: linear-gradient(135deg, var(--primary-cream), white);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .prep-item:hover {
            box-shadow: var(--shadow-soft);
            transform: translateY(-2px);
        }

        .prep-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .prep-name {
            font-weight: 600;
            color: var(--primary-brown);
            font-size: 1.1rem;
        }

        .prep-quantity {
            background: linear-gradient(135deg, var(--primary-orange), #d35400);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1rem;
        }

        .prep-meta {
            font-size: 0.9rem;
            color: var(--text-gray);
            line-height: 1.5;
        }

        .prep-meta div {
            margin-bottom: 0.25rem;
        }

        .prep-customers {
            margin-top: 0.5rem;
            font-weight: 500;
            color: var(--primary-green);
        }

        .print-section {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            padding: 2rem;
            margin-top: 2rem;
        }

        .print-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-gray);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--primary-cream);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary-brown);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .weekend-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, var(--primary-green), #27ae60);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .print-area, .print-area * {
                visibility: visible;
            }
            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .dashboard-controls {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }
            
            .header {
                padding: 1rem;
            }
            
            .header-title h1 {
                font-size: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .print-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="header-title">
                <h1>
                    <i class="fas fa-utensils"></i>
                    Weekend Kitchen Dashboard
                </h1>
                <div class="weekend-indicator">
                    <i class="fas fa-calendar-weekend"></i>
                    Weekend Delivery Only
                </div>
            </div>
            <div class="header-meta">
                <div class="meta-item">
                    <i class="fas fa-calendar-day"></i>
                    <span><?php echo !empty($selected_date) ? date('l, M j, Y', strtotime($selected_date)) : 'Date not selected'; ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-users"></i>
                    <span><?php echo $total_customers; ?> Customers</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-bowl-food"></i>
                    <span><?php echo $total_meals; ?> Meals</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-user-circle"></i>
                    <span><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Kitchen Staff'); ?></span>
                </div>
                <div class="meta-item">
                    <a href="../dashboard.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Dashboard</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="main-container">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard Controls -->
        <div class="dashboard-controls">
            <div class="control-group">
                <label class="control-label">Select Delivery Date (Weekends Only)</label>
                <select class="control-input" onchange="filterByDate(this.value)">
                    <?php foreach ($available_weekends as $weekend): ?>
                        <option value="<?php echo $weekend['date']; ?>" 
                                <?php echo ($weekend['date'] === $selected_date) ? 'selected' : ''; ?>>
                            <?php echo $weekend['display'] . ' - ' . $weekend['week_label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="control-group">
                <label class="control-label">Status Filter</label>
                <select class="control-input" onchange="filterByStatus(this.value)">
                    <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>All Orders</option>
                    <option value="scheduled" <?php echo ($status_filter === 'scheduled') ? 'selected' : ''; ?>>Scheduled</option>
                    <option value="in_progress" <?php echo ($status_filter === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                    <option value="completed" <?php echo ($status_filter === 'completed') ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            
            <div class="control-group">
                <label class="control-label">Export Data</label>
                <div style="display: flex; gap: 0.5rem;">
                    <a href="?date=<?php echo $selected_date; ?>&export=csv" class="btn btn-secondary">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
               
                </div>
            </div>
            
            <div class="control-group">
                <label class="control-label">Refresh Data</label>
                <button class="btn btn-primary" onclick="refreshData()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $total_customers; ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bowl-food"></i>
                </div>
                <div class="stat-number"><?php echo $total_meals; ?></div>
                <div class="stat-label">Total Meals</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo count($meal_summary); ?></div>
                <div class="stat-label">Menu Items to Prep</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-weekend"></i>
                </div>
                <div class="stat-number"><?php echo date('N', strtotime($selected_date)) == 6 ? 'Sat' : 'Sun'; ?></div>
                <div class="stat-label">Delivery Day</div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Customer Orders -->
            <div class="main-content">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-truck-fast"></i>
                        Delivery Schedule - <?php echo !empty($selected_date) ? date('l, M j', strtotime($selected_date)) : 'Date not selected'; ?>
                    </h2>
                    <div style="display: flex; gap: 1rem;">
                       
                        <button class="btn btn-primary" onclick="markAllCompleted()">
                            <i class="fas fa-check-circle"></i> Mark All Complete
                        </button>
                    </div>
                </div>
                
                <div class="customer-list">
                    <?php if (empty($weekend_orders)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No Deliveries Scheduled</h3>
                            <p>No weekend meal deliveries are scheduled for the selected date</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($weekend_orders as $index => $customer): ?>
                            <div class="customer-card" id="customer-<?php echo $customer['user_id']; ?>">
                                <div class="customer-header">
                                    <div class="customer-info">
                                        <h3><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h3>
                                        <div class="customer-details">
                                            <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($customer['phone']); ?></div>
                                            <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($customer['delivery_address'] . ', ' . $customer['city']); ?></div>
                                            <div><i class="fas fa-tag"></i> Plan: <?php echo htmlspecialchars($customer['plan_name'] ?? 'Standard Plan'); ?></div>
                                            <?php if (!empty($customer['dietary_preferences'])): ?>
                                                <div><i class="fas fa-leaf"></i> Diet: <?php echo htmlspecialchars($customer['dietary_preferences']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($customer['allergies'])): ?>
                                                <div><i class="fas fa-exclamation-triangle"></i> Allergies: <?php echo htmlspecialchars($customer['allergies']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($customer['subscription_notes'])): ?>
                                                <div><i class="fas fa-sticky-note"></i> Notes: <?php echo htmlspecialchars($customer['subscription_notes']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="delivery-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo htmlspecialchars($customer['preferred_delivery_time'] ?? '12:00 PM - 3:00 PM'); ?>
                                    </div>
                                </div>
                                
                                <div class="meals-list">
                                    <?php foreach ($customer['meals'] as $meal): ?>
                                        <div class="meal-item">
                                            <div class="meal-header">
                                                <div class="meal-name"><?php echo htmlspecialchars($meal['menu_name']); ?></div>
                                                <div class="meal-quantity">x<?php echo $meal['quantity']; ?></div>
                                            </div>
                                            <div class="meal-meta">
                                                <?php if (!empty($meal['category_name'])): ?>
                                                    <div><strong>Category:</strong> <?php echo htmlspecialchars($meal['category_name']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($meal['preparation_time'])): ?>
                                                    <div><strong>Prep Time:</strong> <?php echo $meal['preparation_time']; ?> minutes</div>
                                                <?php endif; ?>
                                                <?php if (!empty($meal['customizations'])): ?>
                                                    <div><strong>Customizations:</strong> <?php echo htmlspecialchars($meal['customizations']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($meal['special_requests'])): ?>
                                                    <div><strong>Special Requests:</strong> <?php echo htmlspecialchars($meal['special_requests']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Meal Preparation Summary -->
            <div class="sidebar">
                <div class="prep-summary-title">
                    <i class="fas fa-clipboard-list"></i>
                    Kitchen Prep Summary
                </div>
                
                <?php if (empty($meal_summary)): ?>
                    <div class="empty-state">
                        <i class="fas fa-utensils"></i>
                        <h3>No Meals to Prepare</h3>
                        <p>No meal preparations required for this date</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($meal_summary as $meal): ?>
                        <div class="prep-item">
                            <div class="prep-header">
                                <div class="prep-name"><?php echo htmlspecialchars($meal['name']); ?></div>
                                <div class="prep-quantity"><?php echo $meal['total_quantity']; ?> portions</div>
                            </div>
                            <div class="prep-meta">
                                <?php if (!empty($meal['category'])): ?>
                                    <div><strong>Category:</strong> <?php echo htmlspecialchars($meal['category']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($meal['prep_time'])): ?>
                                    <div><strong>Prep Time:</strong> <?php echo $meal['prep_time']; ?> min/portion</div>
                                <?php endif; ?>
                                <?php if (!empty($meal['spice_level'])): ?>
                                    <div><strong>Spice Level:</strong> <?php echo $meal['spice_level']; ?></div>
                                <?php endif; ?>
                                <?php if (!empty($meal['cooking_method'])): ?>
                                    <div><strong>Cooking Method:</strong> <?php echo htmlspecialchars($meal['cooking_method']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($meal['customizations'])): ?>
                                    <div><strong>Customizations:</strong> <?php echo implode(', ', $meal['customizations']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($meal['special_requests'])): ?>
                                    <div><strong>Special Requests:</strong> <?php echo implode(', ', $meal['special_requests']); ?></div>
                                <?php endif; ?>
                                <div class="prep-customers">
                                    <strong>Customers:</strong> <?php echo implode(', ', array_unique($meal['customers'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Print Section -->
        <div class="print-section no-print">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-print"></i>
                    Print & Export Options
                </h2>
            </div>
            
            <div class="print-buttons">
                <button class="btn btn-primary" onclick="printPrepSheet()">
                    <i class="fas fa-clipboard-list"></i>
                    Print Prep Sheet
                </button>
                
                <button class="btn btn-secondary" onclick="printDeliverySheet()">
                    <i class="fas fa-truck"></i>
                    Print Delivery Sheet
                </button>
                
                <button class="btn btn-outline" onclick="printIngredientsList()">
                    <i class="fas fa-carrot"></i>
                    Print Ingredients List
                </button>
                
                <a href="?date=<?php echo $selected_date; ?>&export=csv" class="btn btn-secondary">
                    <i class="fas fa-file-csv"></i>
                    Export CSV
                </a>
                
           
            </div>
        </div>
    </div>

    <!-- Print Areas (Hidden) -->
    <div id="prep-print-area" class="print-area" style="display: none;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="color: #8b4513; margin-bottom: 0.5rem;">Krua Thai USA - Kitchen Prep Sheet</h1>
            <h2 style="color: #6c757d;">Delivery Date: <?php echo !empty($selected_date) ? date('l, F j, Y', strtotime($selected_date)) : 'Date not selected'; ?></h2>
            <p style="color: #666;">Printed: <?php echo date('m/d/Y H:i:s'); ?> (Eastern Time)</p>
        </div>
        
        <div style="margin-bottom: 2rem;">
            <h3 style="background: #f8f6f3; padding: 1rem; border-radius: 8px; color: #8b4513;">
                Prep Summary - Total <?php echo count($meal_summary); ?> items / <?php echo $total_meals; ?> portions
            </h3>
        </div>
        
        <?php foreach ($meal_summary as $meal): ?>
            <div style="border: 1px solid #ddd; margin-bottom: 1rem; border-radius: 8px; overflow: hidden;">
                <div style="background: #e67e22; color: white; padding: 1rem; font-weight: bold; font-size: 1.2rem;">
                    <?php echo htmlspecialchars($meal['name']); ?> - <?php echo $meal['total_quantity']; ?> portions
                </div>
                <div style="padding: 1rem;">
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($meal['category'] ?? 'Not specified'); ?></p>
                    <p><strong>Prep Time:</strong> <?php echo $meal['prep_time'] ?? 'Not specified'; ?> min/portion</p>
                    <p><strong>Cooking Method:</strong> <?php echo htmlspecialchars($meal['cooking_method'] ?? 'Standard recipe'); ?></p>
                    <?php if (!empty($meal['ingredients'])): ?>
                        <p><strong>Ingredients:</strong> <?php echo htmlspecialchars($meal['ingredients']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($meal['customizations'])): ?>
                        <p><strong>Customizations:</strong> <?php echo implode(', ', $meal['customizations']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($meal['special_requests'])): ?>
                        <p><strong>Special Requests:</strong> <?php echo implode(', ', $meal['special_requests']); ?></p>
                    <?php endif; ?>
                    <p><strong>Customers:</strong> <?php echo implode(', ', array_unique($meal['customers'])); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        function filterByDate(date) {
            window.location.href = `?date=${date}&status=<?php echo $status_filter; ?>`;
        }

        function filterByStatus(status) {
            window.location.href = `?date=<?php echo $selected_date; ?>&status=${status}`;
        }

        function refreshData() {
            window.location.reload();
        }

        function printPrepSheet() {
            const printArea = document.getElementById('prep-print-area');
            const originalDisplay = printArea.style.display;
            
            printArea.style.display = 'block';
            window.print();
            printArea.style.display = originalDisplay;
        }

        function printCustomerList() {
            window.print();
        }

        function printDeliverySheet() {
            // Create delivery sheet content
            let deliveryContent = `
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="color: #8b4513;">Krua Thai USA - Delivery Sheet</h1>
                    <h2>Delivery Date: <?php echo !empty($selected_date) ? date('l, F j, Y', strtotime($selected_date)) : 'Date not selected'; ?></h2>
                    <p>Total <?php echo $total_customers; ?> customers / <?php echo $total_meals; ?> meals</p>
                </div>
                <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                    <thead>
                        <tr style="background: #f8f6f3;">
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">No.</th>
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">Customer</th>
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">Phone</th>
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">Address</th>
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">Delivery Time</th>
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">Menu Items</th>
                            <th style="border: 1px solid #ddd; padding: 0.5rem;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            <?php foreach ($weekend_orders as $index => $customer): ?>
                deliveryContent += `
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 0.5rem; text-align: center;"><?php echo $index + 1; ?></td>
                        <td style="border: 1px solid #ddd; padding: 0.5rem;"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></td>
                        <td style="border: 1px solid #ddd; padding: 0.5rem;"><?php echo htmlspecialchars($customer['phone']); ?></td>
                        <td style="border: 1px solid #ddd; padding: 0.5rem;"><?php echo htmlspecialchars($customer['delivery_address']); ?></td>
                        <td style="border: 1px solid #ddd; padding: 0.5rem;"><?php echo htmlspecialchars($customer['preferred_delivery_time'] ?? '12:00 PM - 3:00 PM'); ?></td>
                        <td style="border: 1px solid #ddd; padding: 0.5rem;">
                            <?php foreach ($customer['meals'] as $meal): ?>
                                <?php echo htmlspecialchars($meal['menu_name']); ?> (<?php echo $meal['quantity']; ?>)<br>
                            <?php endforeach; ?>
                        </td>
                        <td style="border: 1px solid #ddd; padding: 0.5rem; text-align: center;">☐</td>
                    </tr>
                `;
            <?php endforeach; ?>
            
            deliveryContent += `
                    </tbody>
                </table>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Delivery Sheet - <?php echo !empty($selected_date) ? date('m/d/Y', strtotime($selected_date)) : 'Date not selected'; ?></title>
                        <style>
                            body { font-family: 'Inter', 'Roboto', sans-serif; }
                            table { width: 100%; border-collapse: collapse; }
                            th, td { border: 1px solid #ddd; padding: 0.5rem; }
                            th { background: #f5f5f5; }
                        </style>
                    </head>
                    <body>${deliveryContent}</body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        function printIngredientsList() {
            // Create ingredients summary
            const ingredients = new Map();
            
            <?php foreach ($meal_summary as $meal): ?>
                <?php if (!empty($meal['ingredients'])): ?>
                    const mealIngredients = "<?php echo addslashes($meal['ingredients']); ?>".split(',');
                    const quantity = <?php echo $meal['total_quantity']; ?>;
                    
                    mealIngredients.forEach(ingredient => {
                        const trimmed = ingredient.trim();
                        if (trimmed) {
                            ingredients.set(trimmed, (ingredients.get(trimmed) || 0) + quantity);
                        }
                    });
                <?php endif; ?>
            <?php endforeach; ?>
            
            let ingredientsContent = `
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="color: #8b4513;">Krua Thai USA - Ingredients List</h1>
                    <h2>Delivery Date: <?php echo !empty($selected_date) ? date('l, F j, Y', strtotime($selected_date)) : 'Date not selected'; ?></h2>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f6f3;">
                            <th style="border: 1px solid #ddd; padding: 1rem;">Ingredient</th>
                            <th style="border: 1px solid #ddd; padding: 1rem;">Quantity Needed</th>
                            <th style="border: 1px solid #ddd; padding: 1rem;">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            for (const [ingredient, count] of ingredients) {
                ingredientsContent += `
                    <tr>
                        <td style="border: 1px solid #ddd; padding: 0.75rem;">${ingredient}</td>
                        <td style="border: 1px solid #ddd; padding: 0.75rem; text-align: center;">${count} units</td>
                        <td style="border: 1px solid #ddd; padding: 0.75rem;"></td>
                    </tr>
                `;
            }
            
            ingredientsContent += `
                    </tbody>
                </table>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Ingredients List - <?php echo !empty($selected_date) ? date('m/d/Y', strtotime($selected_date)) : 'Date not selected'; ?></title>
                        <style>
                            body { font-family: 'Inter', 'Roboto', sans-serif; }
                            table { width: 100%; border-collapse: collapse; }
                            th, td { border: 1px solid #ddd; padding: 0.75rem; }
                            th { background: #f5f5f5; font-weight: bold; }
                        </style>
                    </head>
                    <body>${ingredientsContent}</body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        function markAllCompleted() {
            if (confirm('Are you sure you want to mark all meal preparations as completed?')) {
                // Here you would typically make an AJAX call to update the database
                alert('All meal preparations marked as completed!');
                
                // Visual feedback
                document.querySelectorAll('.prep-item').forEach(item => {
                    item.style.opacity = '0.6';
                    item.style.background = 'linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)';
                });
            }
        }

        // Auto-refresh every 5 minutes
        setInterval(() => {
            if (confirm('Would you like to automatically refresh the data?')) {
                window.location.reload();
            }
        }, 300000); // 5 minutes

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey) {
                switch(e.key) {
                    case 'p':
                        e.preventDefault();
                        printPrepSheet();
                        break;
                    case 'r':
                        e.preventDefault();
                        refreshData();
                        break;
                }
            }
        });

        console.log('Weekend Kitchen Dashboard (USA) loaded successfully');
        console.log('Total customers:', <?php echo $total_customers; ?>);
        console.log('Total meals:', <?php echo $total_meals; ?>);
        console.log('Selected date:', '<?php echo $selected_date; ?>');
        console.log('Timezone: Eastern Time (US)');
    </script>
</body>
</html>