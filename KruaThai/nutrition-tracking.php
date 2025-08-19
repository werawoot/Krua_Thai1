<?php
/**
 * Krua Thai - Nutrition Tracking Page
 * File: nutrition-tracking.php
 * Description: Simple nutrition tracking based on subscription meals
 * Theme: Same UI/colors as subscription-status.php
 * Language: English (USA market)
 * Mobile-responsive and fully functional
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');


// ✅ เพิ่มตรงนี้
function getThaiNutritionData() {
    return [
        'Green Curry' => ['calories' => 320, 'protein' => 28, 'carbs' => 12, 'fat' => 18, 'fiber' => 3, 'sodium' => 850],
        'Pad Thai' => ['calories' => 450, 'protein' => 18, 'carbs' => 58, 'fat' => 16, 'fiber' => 3, 'sodium' => 1200],
        'Cashew Chicken' => ['calories' => 380, 'protein' => 32, 'carbs' => 28, 'fat' => 15, 'fiber' => 2, 'sodium' => 950],
        'Tom Yum' => ['calories' => 180, 'protein' => 20, 'carbs' => 8, 'fat' => 6, 'fiber' => 2, 'sodium' => 1100],
        'Tom Kha' => ['calories' => 220, 'protein' => 18, 'carbs' => 12, 'fat' => 12, 'fiber' => 2, 'sodium' => 800],
        'Chicken Satay' => ['calories' => 300, 'protein' => 20, 'carbs' => 25, 'fat' => 12, 'fiber' => 3, 'sodium' => 800],
        'Beef Crying Tiger' => ['calories' => 420, 'protein' => 35, 'carbs' => 8, 'fat' => 26, 'fiber' => 1, 'sodium' => 650],
        'Panang Curry' => ['calories' => 340, 'protein' => 24, 'carbs' => 16, 'fat' => 20, 'fiber' => 3, 'sodium' => 800],
        'Larb' => ['calories' => 240, 'protein' => 22, 'carbs' => 8, 'fat' => 12, 'fiber' => 2, 'sodium' => 900],
        'DEFAULT' => ['calories' => 300, 'protein' => 20, 'carbs' => 25, 'fat' => 12, 'fiber' => 3, 'sodium' => 800]
    ];
}

function autoFixMenuNutrition($pdo, $menu_id, $menu_name) {
    $thai_nutrition = getThaiNutritionData();
    $nutrition = null;
    
    foreach ($thai_nutrition as $dish_name => $dish_nutrition) {
        if ($dish_name === 'DEFAULT') continue;
        if (stripos($menu_name, $dish_name) !== false || stripos($dish_name, $menu_name) !== false) {
            $nutrition = $dish_nutrition;
            break;
        }
    }
    
    if (!$nutrition) {
        $nutrition = $thai_nutrition['DEFAULT'];
    }
    
    $stmt = $pdo->prepare("
        UPDATE menus SET calories_per_serving = ?, protein_g = ?, carbs_g = ?, 
                        fat_g = ?, fiber_g = ?, sodium_mg = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$nutrition['calories'], $nutrition['protein'], $nutrition['carbs'], 
                   $nutrition['fat'], $nutrition['fiber'], $nutrition['sodium'], $menu_id]);
    
    return $nutrition;
}
function syncLatestOrdersToNutrition($pdo, $user_id) {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO subscription_menus (id, subscription_id, menu_id, delivery_date, quantity, status, created_at)
        SELECT 
            CONCAT('auto-', UUID()) as id,
            COALESCE(o.subscription_id, 'manual-order') as subscription_id,
            oi.menu_id,
            o.delivery_date,
            oi.quantity,
            'delivered' as status,
            NOW() as created_at
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ? 
          AND o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          AND o.status IN ('confirmed', 'preparing', 'ready', 'out_for_delivery', 'delivered')
          AND NOT EXISTS (
              SELECT 1 FROM subscription_menus sm2 
              WHERE sm2.menu_id = oi.menu_id 
                AND DATE(sm2.delivery_date) = DATE(o.delivery_date)
                AND sm2.subscription_id LIKE '%manual%'
          )
    ");
    
    $result = $stmt->execute([$user_id]);
    $affected_rows = $stmt->rowCount();
    
    return [
        'success' => $result,
        'synced_meals' => $affected_rows,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}


// ===== NUTRITION CALCULATION FUNCTIONS =====

// ✅ แทนที่ function เดิม
function calculateDailyNutritionFromSubscription($pdo, $user_id, $date) {
    $stmt = $pdo->prepare("
        SELECT m.id, m.name_thai, m.name, m.calories_per_serving, m.protein_g, m.carbs_g, m.fat_g, 
               m.fiber_g, m.sodium_mg, sm.quantity, m.main_image_url
        FROM subscription_menus sm
        JOIN menus m ON sm.menu_id = m.id
        JOIN subscriptions s ON sm.subscription_id = s.id
        WHERE s.user_id = ? AND DATE(sm.delivery_date) = ?
    ");
    $stmt->execute([$user_id, $date]);
    $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totals = [
        'calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'fiber' => 0, 'sodium' => 0,
        'meals_count' => count($meals), 'meals' => []
    ];
    
    foreach ($meals as $meal) {
        // ✅ AUTO-FIX: ตรวจสอบและแก้ไขข้อมูลโภชนาการ
        if (empty($meal['calories_per_serving']) || $meal['calories_per_serving'] == 0) {
            $nutrition = autoFixMenuNutrition($pdo, $meal['id'], $meal['name']);
            $meal['calories_per_serving'] = $nutrition['calories'];
            $meal['protein_g'] = $nutrition['protein'];
            $meal['carbs_g'] = $nutrition['carbs'];
            $meal['fat_g'] = $nutrition['fat'];
            $meal['fiber_g'] = $nutrition['fiber'];
            $meal['sodium_mg'] = $nutrition['sodium'];
        }
        
        $qty = intval($meal['quantity']) ?: 1;
        $totals['calories'] += ($meal['calories_per_serving'] ?? 0) * $qty;
        $totals['protein'] += ($meal['protein_g'] ?? 0) * $qty;
        $totals['carbs'] += ($meal['carbs_g'] ?? 0) * $qty;
        $totals['fat'] += ($meal['fat_g'] ?? 0) * $qty;
        $totals['fiber'] += ($meal['fiber_g'] ?? 0) * $qty;
        $totals['sodium'] += ($meal['sodium_mg'] ?? 0) * $qty;
        $totals['meals'][] = $meal;
    }
    
    $totals['calories_percent'] = round(($totals['calories'] / 2000) * 100);
    $totals['protein_percent'] = round(($totals['protein'] / 150) * 100);
    $totals['carbs_percent'] = round(($totals['carbs'] / 250) * 100);
    $totals['fat_percent'] = round(($totals['fat'] / 65) * 100);
    
    $totals['message'] = getNutritionMessage($totals);
    $totals['status_color'] = getNutritionColor($totals['calories_percent']);
    
    return $totals;
}

function getNutritionMessage($totals) {
    $calories = $totals['calories'];
    $protein = $totals['protein'];
    $meals = $totals['meals_count'];
    
    if ($meals == 0) {
        return "No meals today yet. Order a meal plan! 🍽️";
    }
    
    if ($calories < 1200) {
        return "Calories are a bit low. Consider adding more food 😊";
    } elseif ($calories > 2500) {
        return "High calorie intake today. Watch portions 😅";
    } elseif ($protein < 100) {
        return "Low protein. Try adding chicken or fish 🍗";
    } elseif ($calories >= 1800 && $calories <= 2200 && $protein >= 120) {
        return "Perfect balance! Keep it up 🌟";
    } else {
        return "Great job! Thai food is nutritious 🇹🇭";
    }
}

function getNutritionColor($percent) {
    if ($percent < 50) return '#e74c3c'; // Red - low
    if ($percent < 80) return '#f39c12'; // Orange - medium
    if ($percent <= 110) return '#27ae60'; // Green - good
    return '#e67e22'; // Orange - high
}

function getWeeklyNutritionSummary($pdo, $user_id, $week_start_date) {
    $weekly_data = [];
    $weekly_totals = ['calories' => 0, 'protein' => 0, 'meals' => 0];
    
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime($week_start_date . " +$i days"));
        $daily = calculateDailyNutritionFromSubscription($pdo, $user_id, $date);
        
        $weekly_data[$date] = $daily;
        $weekly_totals['calories'] += $daily['calories'];
        $weekly_totals['protein'] += $daily['protein'];
        $weekly_totals['meals'] += $daily['meals_count'];
    }
    
    return [
        'daily_breakdown' => $weekly_data,
        'weekly_averages' => [
            'calories' => round($weekly_totals['calories'] / 7),
            'protein' => round($weekly_totals['protein'] / 7, 1),
            'meals_per_day' => round($weekly_totals['meals'] / 7, 1)
        ],
        'total_meals' => $weekly_totals['meals']
    ];
}

// ===== AJAX HANDLERS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        switch ($_POST['action']) {
            case 'get_daily_nutrition':
                $date = $_POST['date'] ?? $today;
                $nutrition = calculateDailyNutritionFromSubscription($pdo, $user_id, $date);
                echo json_encode(['success' => true, 'data' => $nutrition]);
                break;
                
            case 'get_weekly_nutrition':
                $week_start = $_POST['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
                $weekly = getWeeklyNutritionSummary($pdo, $user_id, $week_start);
                echo json_encode(['success' => true, 'data' => $weekly]);
                break;
                
            case 'set_nutrition_goal':
                $goal_type = $_POST['goal_type'] ?? 'maintenance';
                $_SESSION['nutrition_goal'] = $goal_type;
                echo json_encode(['success' => true, 'message' => 'Goal saved successfully']);
                break;
                
               case 'get_meal_analysis':
    $date = $_POST['date'] ?? date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT sm.menu_id, sm.quantity, sm.delivery_date,
               m.name as menu_name, m.name_thai, m.calories_per_serving, m.protein_g, 
               m.carbs_g, m.fat_g, m.fiber_g, m.sodium_mg, m.main_image_url
        FROM subscription_menus sm
        JOIN menus m ON sm.menu_id = m.id
        JOIN subscriptions s ON sm.subscription_id = s.id
        WHERE s.user_id = ? AND DATE(sm.delivery_date) = ? AND s.status = 'active'
        ORDER BY sm.created_at DESC
    ");
    $stmt->execute([$user_id, $date]);
    $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'meals' => $meals, 'date' => $date]);
    break;

case 'sync_latest_orders':
    $sync_result = syncLatestOrdersToNutrition($pdo, $user_id);
    $has_updates = $sync_result['synced_meals'] > 0;
    
    echo json_encode([
        'success' => true,
        'updated' => $has_updates,
        'synced_meals' => $sync_result['synced_meals'],
        'message' => $has_updates ? "Synced {$sync_result['synced_meals']} new meals" : "No new orders to sync",
        'timestamp' => $sync_result['timestamp']
    ]);
    break;

case 'force_refresh_nutrition':
    syncLatestOrdersToNutrition($pdo, $user_id);
    $updated_nutrition = calculateDailyNutritionFromSubscription($pdo, $user_id, $today);
    
    echo json_encode([
        'success' => true,
        'nutrition_data' => $updated_nutrition,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    break;
   

        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// ===== FETCH DATA FOR PAGE =====
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get today's nutrition
    $today_nutrition = calculateDailyNutritionFromSubscription($pdo, $user_id, $today);
    
    // Get this week's nutrition
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $weekly_nutrition = getWeeklyNutritionSummary($pdo, $user_id, $week_start);
    
    // Get user info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Auto-sync latest orders when page loads
if (!isset($_POST['action'])) {
    $sync_result = syncLatestOrdersToNutrition($pdo, $user_id);
    
    if ($sync_result['synced_meals'] > 0) {
        error_log("Nutrition Auto-sync: Added {$sync_result['synced_meals']} meals for user {$user_id}");
    }
}

} catch (Exception $e) {
    $error_message = "Unable to load data: " . $e->getMessage();
}

// Include the header (same as subscription-status.php)
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nutrition Tracking - Krua Thai</title>
    <meta name="description" content="Track your nutrition with healthy Thai meals">
    
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
    /* USING SAME STYLES AS subscription-status.php - Mobile Optimized */
    
    /* Container */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .main-content {
        padding-top: 2rem;
        min-height: calc(100vh - 200px);
    }

    /* Page Title */
    .page-title {
        font-size: 2.5rem;
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        text-align: center;
        margin-bottom: 2rem;
        color: var(--brown);
    }

    .page-title i {
        color: var(--curry);
        margin-right: 0.5rem;
    }

    /* Main Content Card */
    .main-card {
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-medium);
        overflow: hidden;
        position: relative;
        border: 1px solid var(--border-light);
        margin-bottom: 2rem;
    }

    .main-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--curry), var(--brown), var(--sage));
    }

    .card-header {
        padding: 2rem;
        border-bottom: 1px solid var(--border-light);
        background: linear-gradient(135deg, rgba(207, 114, 58, 0.05), rgba(189, 147, 121, 0.05));
    }

    .card-title {
        font-size: 1.5rem;
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        color: var(--brown);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .card-title i {
        color: var(--curry);
    }

    .card-subtitle {
        color: var(--text-gray);
        font-size: 1rem;
        font-family: 'BaticaSans', sans-serif;
    }

    /* Nutrition Summary Cards */
    .nutrition-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        padding: 2rem;
    }

    .nutrition-card {
        background: var(--white);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-soft);
        transition: var(--transition);
    }

    .nutrition-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-medium);
    }

    .nutrition-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
    }

    .nutrition-card-title {
        font-size: 1.2rem;
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        color: var(--brown);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .nutrition-card-title i {
        color: var(--curry);
    }

    /* Progress Bars */
    .nutrition-item {
        margin-bottom: 1.2rem;
    }

    .nutrition-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .nutrition-name {
        font-weight: 600;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .nutrition-value {
        font-weight: 700;
        color: var(--curry);
    }

    .progress-bar {
        width: 100%;
        height: 12px;
        background: var(--cream);
        border-radius: 6px;
        overflow: hidden;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--sage), var(--curry));
        border-radius: 6px;
        transition: width 0.6s ease;
        position: relative;
    }

    .progress-fill.low {
        background: linear-gradient(90deg, #e74c3c, #c0392b);
    }

    .progress-fill.medium {
        background: linear-gradient(90deg, #f39c12, #e67e22);
    }

    .progress-fill.good {
        background: linear-gradient(90deg, #27ae60, #229954);
    }

    .progress-fill.high {
        background: linear-gradient(90deg, #e67e22, #d35400);
    }

    /* Message Box */
    .nutrition-message {
        background: linear-gradient(135deg, rgba(173, 184, 157, 0.1), rgba(189, 147, 121, 0.1));
        padding: 1.5rem;
        border-radius: var(--radius-lg);
        margin: 1.5rem 2rem;
        border: 1px solid var(--sage);
        text-align: center;
        font-family: 'BaticaSans', sans-serif;
    }

    .nutrition-message-text {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--brown);
        margin-bottom: 0.5rem;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .stat-item {
        text-align: center;
        padding: 1rem;
        background: var(--cream);
        border-radius: var(--radius-md);
        border: 1px solid var(--border-light);
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        color: var(--curry);
        margin-bottom: 0.3rem;
    }

    .stat-label {
        font-size: 0.9rem;
        color: var(--text-gray);
        font-family: 'BaticaSans', sans-serif;
        font-weight: 600;
    }

    /* Meal Cards */
    .meals-section {
        padding: 2rem;
        border-top: 1px solid var(--border-light);
    }

    .meals-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .meal-card {
        background: var(--white);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-soft);
        border: 1px solid var(--border-light);
        transition: var(--transition);
    }

    .meal-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-medium);
    }

    .meal-image {
        width: 100%;
        height: 120px;
        object-fit: cover;
        background: linear-gradient(135deg, var(--cream), var(--sage));
    }

    .meal-content {
        padding: 1rem;
    }

    .meal-name {
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }

    .meal-nutrition {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.3rem;
        font-size: 0.8rem;
        color: var(--text-gray);
        font-family: 'BaticaSans', sans-serif;
    }

    .meal-nutrition span {
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    /* Weekly Chart */
    .weekly-section {
        padding: 2rem;
        border-top: 1px solid var(--border-light);
    }

    .chart-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .chart-day {
        text-align: center;
        padding: 1rem 0.5rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-light);
        background: var(--cream);
        transition: var(--transition);
        cursor: pointer;
    }

    .chart-day:hover {
        background: rgba(207, 114, 58, 0.1);
        transform: translateY(-2px);
    }

    .chart-day-name {
        font-size: 0.8rem;
        color: var(--text-gray);
        font-weight: 600;
        margin-bottom: 0.3rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .chart-day-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--curry);
        font-family: 'BaticaSans', sans-serif;
    }

    .chart-day-bar {
        width: 100%;
        height: 4px;
        background: var(--border-light);
        border-radius: 2px;
        margin-top: 0.5rem;
        overflow: hidden;
    }

    .chart-day-fill {
        height: 100%;
        background: var(--curry);
        border-radius: 2px;
        transition: width 0.6s ease;
    }

    /* Goal Setting */
    .goal-section {
        padding: 2rem;
        border-top: 1px solid var(--border-light);
        background: linear-gradient(135deg, rgba(189, 147, 121, 0.02), rgba(173, 184, 157, 0.02));
    }

    .goal-buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .goal-btn {
        padding: 1rem 1.5rem;
        border: 2px solid var(--border-light);
        border-radius: var(--radius-lg);
        background: var(--white);
        cursor: pointer;
        transition: var(--transition);
        text-align: center;
        font-family: 'BaticaSans', sans-serif;
        font-weight: 600;
        color: var(--text-dark);
        touch-action: manipulation;
    }

    .goal-btn:hover {
        border-color: var(--curry);
        background: rgba(207, 114, 58, 0.05);
        transform: translateY(-2px);
    }

    .goal-btn.active {
        border-color: var(--curry);
        background: var(--curry);
        color: var(--white);
    }

    .goal-btn-icon {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        display: block;
    }

    .goal-btn-title {
        font-size: 1rem;
        margin-bottom: 0.3rem;
    }

    .goal-btn-desc {
        font-size: 0.8rem;
        opacity: 0.8;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-gray);
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.3;
        color: var(--sage);
    }

    .empty-state h3 {
        font-size: 1.3rem;
        margin-bottom: 0.5rem;
        color: var(--brown);
        font-family: 'BaticaSans', sans-serif;
        font-weight: 700;
    }

    .empty-state p {
        font-size: 1rem;
        margin-bottom: 1.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .empty-state .btn {
        background: var(--curry);
        color: var(--white);
        border: none;
        padding: 1rem 2rem;
        border-radius: var(--radius-lg);
        text-decoration: none;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: var(--transition);
        touch-action: manipulation;
    }

    .empty-state .btn:hover {
        background: var(--brown);
        transform: translateY(-1px);
        box-shadow: var(--shadow-soft);
    }

    /* Bottom Navigation */
    .bottom-nav {
        padding: 2rem;
        border-top: 1px solid var(--border-light);
        background: var(--cream);
    }

    .nav-links {
        display: flex;
        justify-content: center;
        gap: 2rem;
        flex-wrap: wrap;
    }

    .nav-link {
        color: var(--brown);
        text-decoration: none;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: var(--transition);
        padding: 0.5rem 1rem;
        border-radius: var(--radius-md);
        touch-action: manipulation;
        min-height: 44px;
    }

    .nav-link:hover {
        color: var(--curry);
        background: rgba(207, 114, 58, 0.1);
        transform: translateY(-1px);
    }

    /* Loading Animation */
    .loading {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        color: var(--text-gray);
        font-family: 'BaticaSans', sans-serif;
    }

    .spinner {
        width: 30px;
        height: 30px;
        border: 3px solid var(--border-light);
        border-top: 3px solid var(--curry);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 1rem;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Mobile Responsive Design */
    @media (max-width: 768px) {
        .page-title {
            font-size: 2rem;
            margin-bottom: 1.5rem;
        }

        .nutrition-grid {
            grid-template-columns: 1fr;
            padding: 1.5rem;
            gap: 1rem;
        }

        .nutrition-card {
            padding: 1rem;
        }

        .card-header {
            padding: 1.5rem;
        }

        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .chart-days {
            grid-template-columns: repeat(4, 1fr);
            gap: 0.3rem;
        }

        .chart-day {
            padding: 0.8rem 0.3rem;
        }

        .chart-day-name {
            font-size: 0.7rem;
        }

        .chart-day-value {
            font-size: 1rem;
        }

        .goal-buttons {
            grid-template-columns: 1fr;
            gap: 0.8rem;
        }

        .goal-btn {
            padding: 1rem;
        }

        .nav-links {
            flex-direction: column;
            gap: 1rem;
            align-items: center;
        }

        .meals-grid {
            grid-template-columns: 1fr;
        }

        .nutrition-message {
            margin: 1rem;
            padding: 1rem;
        }

        .meals-section, .weekly-section, .goal-section {
            padding: 1.5rem;
        }
    }

    @media (max-width: 480px) {
        .container {
            padding: 0 15px;
        }

        .page-title {
            font-size: 1.8rem;
        }

        .card-title {
            font-size: 1.3rem;
        }

        .nutrition-card {
            padding: 1rem;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .chart-days {
            grid-template-columns: repeat(2, 1fr);
        }

        .stat-value {
            font-size: 1.5rem;
        }

        .nutrition-card-title {
            font-size: 1.1rem;
        }

        .progress-bar {
            height: 10px;
        }

        .goal-btn-icon {
            font-size: 1.3rem;
        }

        .goal-btn-title {
            font-size: 0.9rem;
        }
    }

    /* Touch-friendly interactions */
    @media (hover: none) {
        .nutrition-card:hover,
        .meal-card:hover,
        .chart-day:hover {
            transform: none;
        }
        
        .goal-btn:hover,
        .nav-link:hover {
            transform: none;
        }
    }

    /* Accessibility improvements */
    @media (prefers-reduced-motion: reduce) {
        .progress-fill,
        .chart-day-fill,
        .spinner {
            animation: none;
            transition: none;
        }
    }

    /* High contrast mode */
    @media (prefers-contrast: high) {
        .progress-bar {
            border: 2px solid var(--text-dark);
        }
        
        .nutrition-card,
        .meal-card {
            border: 2px solid var(--text-dark);
        }
    }
    </style>
</head>

<body class="has-header">
    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Title -->
            <h1 class="page-title">
                <i class="fas fa-chart-pie"></i>
                Nutrition Tracking
            </h1>

            <!-- Today's Nutrition Summary -->
            <div class="main-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-calendar-day"></i>
                        Today's Nutrition
                    </h2>
                    <p class="card-subtitle">
                        Hello <?= htmlspecialchars($user_info['first_name'] ?? 'there') ?>! Here's your nutrition summary for today
                    </p>
                </div>

                <?php if ($today_nutrition['meals_count'] > 0): ?>
                    <div class="nutrition-grid">
                        <!-- Calories Card -->
                        <div class="nutrition-card">
                            <div class="nutrition-card-header">
                                <div class="nutrition-card-title">
                                    <i class="fas fa-fire"></i>
                                    Calories
                                </div>
                            </div>
                            
                            <div class="nutrition-item">
                                <div class="nutrition-label">
                                    <div class="nutrition-name">
                                        <i class="fas fa-drumstick-bite" style="color: #8e44ad;"></i>
                                        Protein
                                    </div>
                                    <div class="nutrition-value"><?= number_format($today_nutrition['protein'], 1) ?>g</div>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill <?= $today_nutrition['protein_percent'] < 50 ? 'low' : ($today_nutrition['protein_percent'] < 80 ? 'medium' : 'good') ?>" 
                                         style="width: <?= min($today_nutrition['protein_percent'], 100) ?>%"></div>
                                </div>
                            </div>

                            <div class="nutrition-item">
                                <div class="nutrition-label">
                                    <div class="nutrition-name">
                                        <i class="fas fa-bread-slice" style="color: #f39c12;"></i>
                                        Carbs
                                    </div>
                                    <div class="nutrition-value"><?= number_format($today_nutrition['carbs'], 1) ?>g</div>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill <?= $today_nutrition['carbs_percent'] < 50 ? 'low' : ($today_nutrition['carbs_percent'] < 80 ? 'medium' : 'good') ?>" 
                                         style="width: <?= min($today_nutrition['carbs_percent'], 100) ?>%"></div>
                                </div>
                            </div>

                            <div class="nutrition-item">
                                <div class="nutrition-label">
                                    <div class="nutrition-name">
                                        <i class="fas fa-tint" style="color: #27ae60;"></i>
                                        Fat
                                    </div>
                                    <div class="nutrition-value"><?= number_format($today_nutrition['fat'], 1) ?>g</div>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill <?= $today_nutrition['fat_percent'] < 50 ? 'low' : ($today_nutrition['fat_percent'] < 80 ? 'medium' : 'good') ?>" 
                                         style="width: <?= min($today_nutrition['fat_percent'], 100) ?>%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Today's Stats -->
                        <div class="nutrition-card">
                            <div class="nutrition-card-header">
                                <div class="nutrition-card-title">
                                    <i class="fas fa-chart-bar"></i>
                                    Today's Stats
                                </div>
                            </div>
                            
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-value"><?= $today_nutrition['meals_count'] ?></div>
                                    <div class="stat-label">Meals</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?= number_format($today_nutrition['fiber'], 1) ?></div>
                                    <div class="stat-label">Fiber (g)</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?= number_format($today_nutrition['sodium'] / 1000, 1) ?></div>
                                    <div class="stat-label">Sodium (g)</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Nutrition Message -->
                    <div class="nutrition-message">
                        <div class="nutrition-message-text">
                            <?= htmlspecialchars($today_nutrition['message']) ?>
                        </div>
                    </div>

                    <!-- Today's Meals -->
                    <div class="meals-section">
                        <h3 class="card-title">
                            <i class="fas fa-utensils"></i>
                            Today's Meals
                        </h3>
                        
                        <div class="meals-grid">
                            <?php foreach ($today_nutrition['meals'] as $meal): ?>
                                <div class="meal-card">
                                    <?php if ($meal['main_image_url']): ?>
                                        <img src="<?= htmlspecialchars($meal['main_image_url']) ?>" 
                                             alt="<?= htmlspecialchars($meal['name'] ?? $meal['name_thai']) ?>" 
                                             class="meal-image" 
                                             onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <div class="meal-image" style="display: flex; align-items: center; justify-content: center; color: var(--text-gray);">
                                            <i class="fas fa-utensils" style="font-size: 2rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="meal-content">
                                        <div class="meal-name">
                                            <?= htmlspecialchars($meal['name'] ?? $meal['name_thai']) ?>
                                            <?php if ($meal['quantity'] > 1): ?>
                                                <span style="color: var(--curry); font-weight: 700;">(×<?= $meal['quantity'] ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="meal-nutrition">
                                            <span><i class="fas fa-fire" style="color: #e74c3c;"></i> <?= $meal['calories_per_serving'] * $meal['quantity'] ?> cal</span>
                                            <span><i class="fas fa-drumstick-bite" style="color: #8e44ad;"></i> <?= number_format($meal['protein_g'] * $meal['quantity'], 1) ?>g</span>
                                            <span><i class="fas fa-bread-slice" style="color: #f39c12;"></i> <?= number_format($meal['carbs_g'] * $meal['quantity'], 1) ?>g</span>
                                            <span><i class="fas fa-tint" style="color: #27ae60;"></i> <?= number_format($meal['fat_g'] * $meal['quantity'], 1) ?>g</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-utensils"></i>
                        <h3>No Meals Today</h3>
                        <p>You haven't ordered any meals for today yet</p>
                        <a href="subscribe.php" class="btn">
                            <i class="fas fa-plus"></i>
                            Order Meal Plan
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Weekly Overview -->
            <div class="main-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-calendar-week"></i>
                        This Week's Overview
                    </h2>
                    <p class="card-subtitle">
                        Track your weekly nutrition patterns
                    </p>
                </div>

                <div class="weekly-section">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($weekly_nutrition['weekly_averages']['calories']) ?></div>
                            <div class="stat-label">Avg Daily Calories</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $weekly_nutrition['weekly_averages']['protein'] ?></div>
                            <div class="stat-label">Avg Daily Protein (g)</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $weekly_nutrition['total_meals'] ?></div>
                            <div class="stat-label">Total Meals</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $weekly_nutrition['weekly_averages']['meals_per_day'] ?></div>
                            <div class="stat-label">Avg Meals/Day</div>
                        </div>
                    </div>

                    <div class="chart-days">
                        <?php 
                        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                        $day_index = 0;
                        foreach ($weekly_nutrition['daily_breakdown'] as $date => $day_data): 
                            $cal_percent = $day_data['calories'] > 0 ? min(($day_data['calories'] / 2000) * 100, 100) : 0;
                        ?>
                            <div class="chart-day" onclick="loadDayDetails('<?= $date ?>')">
                                <div class="chart-day-name"><?= $days[$day_index] ?></div>
                                <div class="chart-day-value"><?= number_format($day_data['calories']) ?></div>
                                <div class="chart-day-bar">
                                    <div class="chart-day-fill" style="width: <?= $cal_percent ?>%"></div>
                                </div>
                            </div>
                        <?php 
                            $day_index++;
                        endforeach; 
                        ?>
                    </div>
                </div>
            </div>

            <!-- Nutrition Goals -->
            <div class="main-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-bullseye"></i>
                        Nutrition Goals
                    </h2>
                    <p class="card-subtitle">
                        Set your nutrition targets to track progress
                    </p>
                </div>

                <div class="goal-section">
                    <div class="goal-buttons">
                        <div class="goal-btn <?= ($_SESSION['nutrition_goal'] ?? '') === 'weight_loss' ? 'active' : '' ?>" 
                             onclick="setNutritionGoal('weight_loss', this)">
                            <div class="goal-btn-icon">🏃‍♀️</div>
                            <div class="goal-btn-title">Weight Loss</div>
                            <div class="goal-btn-desc">1,600 calories, High protein</div>
                        </div>
                        
                        <div class="goal-btn <?= ($_SESSION['nutrition_goal'] ?? 'maintenance') === 'maintenance' ? 'active' : '' ?>" 
                             onclick="setNutritionGoal('maintenance', this)">
                            <div class="goal-btn-icon">⚖️</div>
                            <div class="goal-btn-title">Maintenance</div>
                            <div class="goal-btn-desc">2,000 calories, Balanced</div>
                        </div>
                        
                        <div class="goal-btn <?= ($_SESSION['nutrition_goal'] ?? '') === 'muscle_gain' ? 'active' : '' ?>" 
                             onclick="setNutritionGoal('muscle_gain', this)">
                            <div class="goal-btn-icon">💪</div>
                            <div class="goal-btn-title">Muscle Gain</div>
                            <div class="goal-btn-desc">2,400 calories, High protein</div>
                        </div>
                        
                        <div class="goal-btn <?= ($_SESSION['nutrition_goal'] ?? '') === 'healthy_thai' ? 'active' : '' ?>" 
                             onclick="setNutritionGoal('healthy_thai', this)">
                            <div class="goal-btn-icon">🇹🇭</div>
                            <div class="goal-btn-title">Healthy Thai</div>
                            <div class="goal-btn-desc">1,800 calories, Thai-focused</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Navigation -->
            <div class="main-card">
                <div class="bottom-nav">
                    <div class="nav-links">
                        <a href="dashboard.php" class="nav-link">
                            <i class="fas fa-home"></i>
                            Dashboard
                        </a>
                        <a href="subscription-status.php" class="nav-link">
                            <i class="fas fa-clipboard-list"></i>
                            Order Status
                        </a>
                        <a href="subscribe.php" class="nav-link">
                            <i class="fas fa-plus"></i>
                            Order Meals
                        </a>
                        <a href="help.php" class="nav-link">
                            <i class="fas fa-question-circle"></i>
                            Help
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Loading Modal (for AJAX requests) -->
    <div id="loadingModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 1rem; text-align: center;">
            <div class="spinner"></div>
            <div style="margin-top: 1rem; font-family: 'BaticaSans', sans-serif; color: var(--brown);">Loading nutrition data...</div>
        </div>
    </div>

    <script>
        // JavaScript for nutrition tracking functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🥗 Nutrition Tracking page loaded');
            
            // Initialize page
            initializePage();
            
            // Add touch-friendly interactions for mobile
            addMobileInteractions();
        });

        function initializePage() {
            // Animate cards on load
            const cards = document.querySelectorAll('.nutrition-card, .main-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Animate progress bars
            setTimeout(() => {
                const progressBars = document.querySelectorAll('.progress-fill, .chart-day-fill');
                progressBars.forEach(bar => {
                    const width = bar.style.width;
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = width;
                    }, 200);
                });
            }, 500);
        }

        function addMobileInteractions() {
            // Add touch feedback for interactive elements
            const interactiveElements = document.querySelectorAll('.goal-btn, .chart-day, .nav-link');
            
            interactiveElements.forEach(element => {
                element.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.95)';
                });
                
                element.addEventListener('touchend', function() {
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        }

        // Set nutrition goal
        function setNutritionGoal(goalType, clickedElement) {
            showLoading();
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=set_nutrition_goal&goal_type=${goalType}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    // Update UI - remove active from all buttons
                    document.querySelectorAll('.goal-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    
                    // Add active to clicked button
                    const targetButton = clickedElement || document.querySelector(`[onclick*="${goalType}"]`);
                    if (targetButton) {
                        targetButton.classList.add('active');
                    }
                    
                    // Show success message
                    showNotification('Goal updated successfully! 🎯', 'success');
                    
                    // Refresh page after short delay to show updated calculations
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('Failed to update goal. Please try again.', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showNotification('Network error. Please check your connection.', 'error');
            });
        }

        // Load day details (for weekly chart clicks)
        function loadDayDetails(date) {
            showLoading();
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_daily_nutrition&date=${date}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showDayModal(date, data.data);
                } else {
                    showNotification('Failed to load day details.', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showNotification('Network error. Please try again.', 'error');
            });
        }

        // Show day details modal
        function showDayModal(date, dayData) {
            const formattedDate = new Date(date).toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const modalHTML = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1rem;" onclick="this.remove()">
                    <div style="background: white; border-radius: 1rem; padding: 2rem; max-width: 500px; width: 100%; max-height: 80vh; overflow-y: auto;" onclick="event.stopPropagation()">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <h3 style="color: var(--brown); margin: 0; font-family: 'BaticaSans', sans-serif;">
                                <i class="fas fa-calendar-day" style="color: var(--curry);"></i>
                                ${formattedDate}
                            </h3>
                            <button onclick="this.closest('div[style*=\"position: fixed\"]').remove()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-gray);">&times;</button>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                            <div style="text-align: center; padding: 1rem; background: var(--cream); border-radius: 0.5rem;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--curry);">${dayData.calories}</div>
                                <div style="font-size: 0.9rem; color: var(--text-gray);">Calories</div>
                            </div>
                            <div style="text-align: center; padding: 1rem; background: var(--cream); border-radius: 0.5rem;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--curry);">${dayData.protein.toFixed(1)}g</div>
                                <div style="font-size: 0.9rem; color: var(--text-gray);">Protein</div>
                            </div>
                        </div>
                        
                        <div style="background: rgba(173, 184, 157, 0.1); padding: 1rem; border-radius: 0.5rem; text-align: center; color: var(--brown); font-weight: 600;">
                            ${dayData.message}
                        </div>
                        
                        ${dayData.meals_count > 0 ? `
                            <div style="margin-top: 1.5rem;">
                                <h4 style="color: var(--brown); margin-bottom: 1rem;">Meals (${dayData.meals_count})</h4>
                                ${dayData.meals.map(meal => `
                                    <div style="padding: 0.8rem; background: var(--cream); border-radius: 0.5rem; margin-bottom: 0.5rem;">
                                        <div style="font-weight: 600; margin-bottom: 0.3rem;">${meal.name || meal.name_thai}${meal.quantity > 1 ? ` (×${meal.quantity})` : ''}</div>
                                        <div style="font-size: 0.85rem; color: var(--text-gray);">
                                            ${meal.calories_per_serving * meal.quantity} cal • ${(meal.protein_g * meal.quantity).toFixed(1)}g protein
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }

        // Utility functions
        function showLoading() {
            const modal = document.getElementById('loadingModal');
            modal.style.display = 'flex';
        }

        function hideLoading() {
            const modal = document.getElementById('loadingModal');
            modal.style.display = 'none';
        }

        function showNotification(message, type = 'info') {
            const colors = {
                success: '#27ae60',
                error: '#e74c3c',
                info: '#3498db',
                warning: '#f39c12'
            };
            
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 2rem;
                right: 2rem;
                background: ${colors[type]};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 0.5rem;
                font-family: 'BaticaSans', sans-serif;
                font-weight: 600;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                transform: translateX(100%);
                transition: transform 0.3s ease;
                max-width: 300px;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Animate in
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Remove after delay
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close any open modals
                const modals = document.querySelectorAll('[style*="position: fixed"]');
                modals.forEach(modal => {
                    if (modal.id !== 'loadingModal') {
                        modal.remove();
                    }
                });
            }
        });

        // Swipe gesture for mobile (simple implementation)
        let touchStartX = 0;
        let touchEndX = 0;

        document.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        });

        document.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });

        function handleSwipe() {
            const swipeThreshold = 100;
            const swipeDistance = touchEndX - touchStartX;
            
            if (Math.abs(swipeDistance) > swipeThreshold) {
                if (swipeDistance > 0) {
                    // Swipe right - could navigate back
                    console.log('Swipe right detected');
                } else {
                    // Swipe left - could navigate forward
                    console.log('Swipe left detected');
                }
            }
        }

        // Auto-refresh data periodically (every 5 minutes)
        setInterval(function() {
            if (!document.hidden) {
                // Only refresh if page is visible
                console.log('Auto-refreshing nutrition data...');
                // Could implement silent data refresh here
            }
        }, 5 * 60 * 1000);

        // Service worker registration for offline support (basic)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                // Register service worker if available
                // navigator.serviceWorker.register('/sw.js');
            });
        }

        // Real-time auto-sync every 30 seconds
setInterval(async function() {
    if (!document.hidden) {
        try {
            const response = await fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=sync_latest_orders'
            });
            const data = await response.json();
            
            if (data.updated && data.synced_meals > 0) {
                console.log('🍽️ New meals detected:', data.synced_meals);
                
                // Show notification
                showNotification(`Added ${data.synced_meals} new meals to nutrition tracking! 🎉`, 'success');
                
                // Auto-refresh page after 2 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
        } catch (error) {
            console.log('Auto-sync check failed:', error);
        }
    }
}, 30000); // Check every 30 seconds

// Also check when page becomes visible again
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        // Check for updates when user returns to tab
        setTimeout(async () => {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=sync_latest_orders'
                });
                const data = await response.json();
                
                if (data.updated) {
                    window.location.reload();
                }
            } catch (error) {
                console.log('Visibility sync failed:', error);
            }
        }, 1000);
    }
});
    </script>
</body>
</html>