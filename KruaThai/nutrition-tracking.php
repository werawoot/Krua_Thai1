<?php
/**
 * Krua Thai - Nutrition Tracking Page (Production Version)
 * File: nutrition-tracking.php
 * Description: Two-level nutrition display with historical view
 * Language: English (USA market)
 * Mobile-optimized and production-ready
 */

session_start();
error_reporting(0); // Disable in production
ini_set('display_errors', 0); // Disable in production

require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// ===== NUTRITION FUNCTIONS =====
function getNutritionTargets($goal_type = 'maintenance') {
    $targets = [
        'weight_loss' => ['calories' => 1600, 'protein' => 120, 'carbs' => 150, 'fat' => 55],
        'maintenance' => ['calories' => 2000, 'protein' => 150, 'carbs' => 250, 'fat' => 65],
        'muscle_gain' => ['calories' => 2400, 'protein' => 180, 'carbs' => 300, 'fat' => 80],
        'healthy_thai' => ['calories' => 1800, 'protein' => 135, 'carbs' => 225, 'fat' => 60]
    ];
    
    return $targets[$goal_type] ?? $targets['maintenance'];
}

function syncLatestOrdersToNutrition($pdo, $user_id) {
    try {
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
    } catch (Exception $e) {
        error_log("Sync error: " . $e->getMessage());
        return ['success' => false, 'synced_meals' => 0, 'timestamp' => date('Y-m-d H:i:s')];
    }
}

// ===== DAILY NUTRITION CALCULATION =====
function calculateDailyNutritionFromSubscription($pdo, $user_id, $date) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.id, m.name_thai, m.name, m.calories_per_serving, m.protein_g, m.carbs_g, m.fat_g, 
                   m.fiber_g, m.sodium_mg, sm.quantity, m.main_image_url
            FROM subscription_menus sm
            JOIN menus m ON sm.menu_id = m.id
            JOIN subscriptions s ON sm.subscription_id = s.id
            WHERE s.user_id = ? AND DATE(sm.delivery_date) = ?
            AND s.status IN ('active', 'paused')
        ");
        $stmt->execute([$user_id, $date]);
        $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totals = [
            'calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'fiber' => 0, 'sodium' => 0,
            'meals_count' => count($meals), 'meals' => []
        ];
        
        foreach ($meals as $meal) {
            // Use existing nutrition data from database
            $qty = intval($meal['quantity']) ?: 1;
            $totals['calories'] += (floatval($meal['calories_per_serving']) ?: 0) * $qty;
            $totals['protein'] += (floatval($meal['protein_g']) ?: 0) * $qty;
            $totals['carbs'] += (floatval($meal['carbs_g']) ?: 0) * $qty;
            $totals['fat'] += (floatval($meal['fat_g']) ?: 0) * $qty;
            $totals['fiber'] += (floatval($meal['fiber_g']) ?: 0) * $qty;
            $totals['sodium'] += (floatval($meal['sodium_mg']) ?: 0) * $qty;
            $totals['meals'][] = $meal;
        }
        
        // Calculate percentages
        $goal_type = $_SESSION['nutrition_goal'] ?? 'maintenance';
        $targets = getNutritionTargets($goal_type);

        $totals['calories_percent'] = $targets['calories'] > 0 ? round(($totals['calories'] / $targets['calories']) * 100) : 0;
        $totals['protein_percent'] = $targets['protein'] > 0 ? round(($totals['protein'] / $targets['protein']) * 100) : 0;
        $totals['carbs_percent'] = $targets['carbs'] > 0 ? round(($totals['carbs'] / $targets['carbs']) * 100) : 0;
        $totals['fat_percent'] = $targets['fat'] > 0 ? round(($totals['fat'] / $targets['fat']) * 100) : 0;

        $totals['targets'] = $targets;
        $totals['goal_type'] = $goal_type;
        $totals['message'] = getNutritionMessage($totals);
        $totals['status_color'] = getNutritionColor($totals['calories_percent']);
        
        return $totals;
    } catch (Exception $e) {
        error_log("Calculate nutrition error: " . $e->getMessage());
        return [
            'calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'fiber' => 0, 'sodium' => 0,
            'meals_count' => 0, 'meals' => [], 'message' => 'Unable to load nutrition data',
            'status_color' => '#e74c3c', 'targets' => getNutritionTargets(), 'goal_type' => 'maintenance'
        ];
    }
}

function getNutritionMessage($totals) {
    $calories = $totals['calories'];
    $protein = $totals['protein'];
    $meals = $totals['meals_count'];
    
    if ($meals == 0) {
        return "No meals scheduled for this day.";
    }
    
    if ($calories < 1200) {
        return "Calories are below recommended minimum. Consider adding more meals.";
    } elseif ($calories > 2500) {
        return "High calorie intake for the day. Monitor portion sizes.";
    } elseif ($protein < 100) {
        return "Protein intake is low. Consider adding protein-rich meals.";
    } elseif ($calories >= 1800 && $calories <= 2200 && $protein >= 120) {
        return "Excellent nutritional balance for the day!";
    } else {
        return "Good nutrition from your Thai meal selection.";
    }
}

function getNutritionColor($percent) {
    if ($percent < 50) return '#e74c3c'; // Red - low
    if ($percent < 80) return '#f39c12'; // Orange - medium
    if ($percent <= 110) return '#27ae60'; // Green - good
    return '#e67e22'; // Orange - high
}

// ===== TWO-LEVEL DISPLAY FUNCTIONS =====
function getNutritionHistory($pdo, $user_id, $days_back = 7) {
    $history = [];
    for ($i = 0; $i < $days_back; $i++) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $daily = calculateDailyNutritionFromSubscription($pdo, $user_id, $date);
        $history[$date] = $daily;
    }
    return array_reverse($history, true);
}

function getWeeklyPackageOverview($pdo, $user_id, $week_start) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_meals,
                SUM(m.calories_per_serving * sm.quantity) as total_calories,
                SUM(m.protein_g * sm.quantity) as total_protein,
                SUM(m.carbs_g * sm.quantity) as total_carbs,
                SUM(m.fat_g * sm.quantity) as total_fat,
                sp.name as plan_name,
                sp.meals_per_week
            FROM subscription_menus sm
            JOIN menus m ON sm.menu_id = m.id
            JOIN subscriptions s ON sm.subscription_id = s.id
            JOIN subscription_plans sp ON s.plan_id = sp.id
            WHERE s.user_id = ? 
            AND sm.delivery_date BETWEEN ? AND DATE_ADD(?, INTERVAL 6 DAY)
            AND s.status IN ('active', 'paused')
            GROUP BY sp.name, sp.meals_per_week
        ");
        $stmt->execute([$user_id, $week_start, $week_start]);
        $package = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$package) {
            return null;
        }
        
        return [
            'plan_name' => $package['plan_name'] ?? 'Your Plan',
            'meals_per_week' => intval($package['meals_per_week']) ?: 0,
            'total_meals' => intval($package['total_meals']) ?: 0,
            'total_calories' => floatval($package['total_calories']) ?: 0,
            'total_protein' => floatval($package['total_protein']) ?: 0,
            'total_carbs' => floatval($package['total_carbs']) ?: 0,
            'total_fat' => floatval($package['total_fat']) ?: 0,
            'avg_calories_per_day' => round((floatval($package['total_calories']) ?: 0) / 7),
            'avg_protein_per_day' => round((floatval($package['total_protein']) ?: 0) / 7, 1),
            'week_start' => $week_start,
            'week_end' => date('Y-m-d', strtotime($week_start . ' +6 days'))
        ];
    } catch (Exception $e) {
        error_log("Weekly package error: " . $e->getMessage());
        return null;
    }
}

function getTodayGoalBreakdown($daily_nutrition, $goal_type = 'maintenance') {
    $targets = getNutritionTargets($goal_type);
    
    return [
        'target_calories' => $targets['calories'],
        'target_protein' => $targets['protein'],
        'target_carbs' => $targets['carbs'],
        'target_fat' => $targets['fat'],
        'available_calories' => $daily_nutrition['calories'],
        'available_protein' => $daily_nutrition['protein'],
        'coverage_percent' => $targets['calories'] > 0 ? round(($daily_nutrition['calories'] / $targets['calories']) * 100) : 0,
        'remaining_calories' => max(0, $targets['calories'] - $daily_nutrition['calories']),
        'remaining_protein' => max(0, $targets['protein'] - $daily_nutrition['protein']),
        'goal_type' => $goal_type
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
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid date format']);
                    break;
                }
                $nutrition = calculateDailyNutritionFromSubscription($pdo, $user_id, $date);
                echo json_encode(['success' => true, 'data' => $nutrition]);
                break;
                
            case 'get_nutrition_history':
                $days_back = min(14, max(1, intval($_POST['days_back'] ?? 7)));
                $history = getNutritionHistory($pdo, $user_id, $days_back);
                echo json_encode(['success' => true, 'data' => $history]);
                break;

            case 'get_date_nutrition':
                $selected_date = $_POST['selected_date'] ?? $today;
                
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid date format']);
                    break;
                }
                
                $daily = calculateDailyNutritionFromSubscription($pdo, $user_id, $selected_date);
                $week_start = date('Y-m-d', strtotime('monday this week', strtotime($selected_date)));
                $package = getWeeklyPackageOverview($pdo, $user_id, $week_start);
                $goal_breakdown = getTodayGoalBreakdown($daily, $_SESSION['nutrition_goal'] ?? 'maintenance');
                
                echo json_encode([
                    'success' => true,
                    'date' => $selected_date,
                    'daily_nutrition' => $daily,
                    'package_overview' => $package,
                    'goal_breakdown' => $goal_breakdown
                ]);
                break;
                
            case 'set_nutrition_goal':
                $goal_type = $_POST['goal_type'] ?? 'maintenance';
                $allowed_goals = ['weight_loss', 'maintenance', 'muscle_gain', 'healthy_thai'];
                
                if (!in_array($goal_type, $allowed_goals)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid goal type']);
                    break;
                }
                
                $_SESSION['nutrition_goal'] = $goal_type;
                echo json_encode(['success' => true, 'message' => 'Goal updated successfully']);
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
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        error_log("AJAX error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Server error occurred']);
    }
    exit();
}

// ===== FETCH DATA FOR PAGE =====
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get and validate selected date
    $selected_date = $_GET['date'] ?? $today;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
        $selected_date = $today;
    }
    
    // Ensure date is not in future and not too far back
    if ($selected_date > $today) {
        $selected_date = $today;
    }
    
    $min_date = date('Y-m-d', strtotime('-14 days'));
    if ($selected_date < $min_date) {
        $selected_date = $min_date;
    }
    
    // Get data
    $selected_nutrition = calculateDailyNutritionFromSubscription($pdo, $user_id, $selected_date);
    $selected_week_start = date('Y-m-d', strtotime('monday this week', strtotime($selected_date)));
    $package_overview = getWeeklyPackageOverview($pdo, $user_id, $selected_week_start);
    $goal_breakdown = getTodayGoalBreakdown($selected_nutrition, $_SESSION['nutrition_goal'] ?? 'maintenance');
    $nutrition_history = getNutritionHistory($pdo, $user_id, 7);
    
    // Get user info
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Auto-sync (reduced frequency for production)
    if (!isset($_POST['action']) && rand(1, 10) === 1) { // Only 10% of page loads
        syncLatestOrdersToNutrition($pdo, $user_id);
    }
} catch (Exception $e) {
    error_log("Page load error: " . $e->getMessage());
    $error_message = "Unable to load nutrition data. Please try again later.";
    
    // Fallback data
    $selected_nutrition = ['calories' => 0, 'protein' => 0, 'meals_count' => 0, 'meals' => []];
    $package_overview = null;
    $goal_breakdown = ['coverage_percent' => 0, 'available_calories' => 0, 'target_calories' => 2000];
    $nutrition_history = [];
    $user_info = ['first_name' => 'User'];
}

include 'header.php';
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nutrition Tracking - Krua Thai</title>
    <meta name="description" content="Track your nutrition with healthy Thai meals delivered fresh">
    <meta name="robots" content="noindex, nofollow">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
    /* Production CSS - Minified base styles */
    .container{max-width:1200px;margin:0 auto;padding:0 20px}
    .main-content{padding-top:2rem;min-height:calc(100vh - 200px)}
    .page-title{font-size:2.5rem;font-weight:700;text-align:center;margin-bottom:2rem;color:var(--brown,#8B4513)}
    .page-title i{color:var(--curry,#CF723A);margin-right:0.5rem}
    
    .main-card{background:var(--white,#fff);border-radius:var(--radius-lg,12px);box-shadow:0 4px 12px rgba(0,0,0,0.1);overflow:hidden;position:relative;border:1px solid var(--border-light,#e1e5e9);margin-bottom:2rem}
    .main-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--curry,#CF723A),var(--brown,#8B4513),var(--sage,#ADB89D))}
    .card-header{padding:2rem;border-bottom:1px solid var(--border-light,#e1e5e9);background:linear-gradient(135deg,rgba(207,114,58,0.05),rgba(189,147,121,0.05))}
    .card-title{font-size:1.5rem;font-weight:700;color:var(--brown,#8B4513);display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem}
    .card-title i{color:var(--curry,#CF723A)}
    .card-subtitle{color:var(--text-gray,#6c757d);font-size:1rem}
    
    /* Date Navigation */
    .date-navigation{display:flex;align-items:center;justify-content:center;gap:1rem;margin:2rem 0;padding:1rem;background:var(--cream,#F5F1EB);border-radius:12px}
    .nav-btn{background:var(--curry,#CF723A);color:white;border:none;padding:0.75rem 1rem;border-radius:8px;cursor:pointer;font-weight:600;transition:all 0.2s}
    .nav-btn:hover:not(:disabled){background:var(--brown,#8B4513);transform:translateY(-1px)}
    .nav-btn:disabled{background:#999;cursor:not-allowed}
    .date-picker{text-align:center}
    .date-picker input{padding:0.5rem;border:1px solid #ddd;border-radius:8px}
    .date-picker label{display:block;font-weight:600;color:var(--brown,#8B4513);margin-top:0.5rem}
    
    /* Package Overview */
    .package-overview{background:linear-gradient(135deg,rgba(207,114,58,0.05),rgba(189,147,121,0.05))}
    .package-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;padding:2rem}
    .package-stat{text-align:center;padding:1.5rem;background:white;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1)}
    .package-stat .stat-value{font-size:2rem;font-weight:700;color:var(--curry,#CF723A);margin-bottom:0.5rem}
    .package-stat .stat-label{font-size:1rem;font-weight:600;color:var(--brown,#8B4513);margin-bottom:0.25rem}
    .package-stat .stat-sub{font-size:0.85rem;color:#6c757d}
    
    /* Goal Breakdown */
    .goal-comparison{padding:2rem}
    .goal-circle{width:120px;height:120px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;position:relative;background:conic-gradient(var(--curry,#CF723A) 0%,#f0f0f0 0%)}
    .goal-circle::before{content:'';width:80px;height:80px;background:white;border-radius:50%;position:absolute}
    .goal-circle span{font-size:1.5rem;font-weight:700;color:var(--curry,#CF723A);z-index:1}
    .goal-details{text-align:center}
    .available{font-size:1.2rem;font-weight:700;color:var(--brown,#8B4513);margin-bottom:0.5rem}
    .target,.remaining{color:#6c757d;margin-bottom:0.25rem}
    
    /* Meals Display */
    .meals-section{padding:2rem;border-top:1px solid #e1e5e9}
    .meals-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem;margin-top:1rem}
    .meal-card{background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);border:1px solid #e1e5e9;transition:all 0.2s;position:relative}
    .meal-card:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,0.15)}
    .meal-number{position:absolute;top:0.75rem;left:0.75rem;background:var(--curry,#CF723A);color:white;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.85rem;z-index:2}
    .meal-image-container{position:relative;width:100%;height:180px;overflow:hidden;background:linear-gradient(135deg,#F5F1EB,#ADB89D)}
    .meal-image{width:100%;height:100%;object-fit:cover}
    .meal-image-fallback{width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#6c757d;font-size:2.5rem;opacity:0.5}
    .quantity-badge{position:absolute;top:0.75rem;right:0.75rem;background:rgba(0,0,0,0.8);color:white;padding:0.3rem 0.6rem;border-radius:4px;font-weight:600;font-size:0.8rem;z-index:2}
    .meal-content{padding:1.25rem}
    .meal-name{font-weight:700;color:#333;margin-bottom:0.3rem;font-size:1.05rem;line-height:1.3}
    .meal-name-thai{color:#6c757d;font-size:0.9rem;font-style:italic;margin-bottom:0.75rem}
    .meal-calories{display:flex;align-items:center;gap:0.5rem;font-size:1.1rem;font-weight:700;color:var(--curry,#CF723A);margin-bottom:1rem;padding:0.5rem;background:rgba(207,114,58,0.1);border-radius:8px}
    .meal-calories i{color:#e74c3c}
    .meal-nutrition-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:0.75rem}
    .meal-nutrition-grid .nutrition-item{display:flex;align-items:center;gap:0.4rem;font-size:0.85rem;padding:0.4rem;background:#F5F1EB;border-radius:4px}
    .meal-nutrition-grid .nutrition-item span{font-weight:600;color:#333}
    .meal-nutrition-grid .nutrition-item small{color:#6c757d;font-size:0.75rem;margin-left:auto}
    
    /* History Chart */
    .history-chart{display:grid;grid-template-columns:repeat(7,1fr);gap:1rem;padding:2rem}
    .history-day{text-align:center;padding:1rem 0.5rem;border-radius:12px;cursor:pointer;transition:all 0.2s;background:#F5F1EB}
    .history-day:hover{background:rgba(207,114,58,0.1);transform:translateY(-2px)}
    .history-day.selected{background:var(--curry,#CF723A);color:white}
    .history-bar{width:100%;height:8px;background:#ddd;border-radius:4px;margin:0.5rem 0;overflow:hidden}
    .history-fill{height:100%;background:var(--curry,#CF723A);border-radius:4px;transition:width 0.6s ease}
    .history-date{font-size:0.9rem;font-weight:600;margin-bottom:0.3rem}
    .history-value{font-size:1.1rem;font-weight:700;color:var(--curry,#CF723A)}
    .history-label{font-size:0.8rem;color:#6c757d}
    
    /* Empty State */
    .empty-state{text-align:center;padding:3rem 2rem;color:#6c757d}
    .empty-state i{font-size:3rem;margin-bottom:1rem;opacity:0.3}
    .empty-state h3{font-size:1.3rem;margin-bottom:0.5rem;color:var(--brown,#8B4513);font-weight:700}
    .empty-state p{font-size:1rem;margin-bottom:1.5rem}
    .empty-state .btn{background:var(--curry,#CF723A);color:white;border:none;padding:1rem 2rem;border-radius:12px;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:0.5rem;transition:all 0.2s}
    .empty-state .btn:hover{background:var(--brown,#8B4513);transform:translateY(-1px)}
    
    /* Loading */
    .spinner{width:30px;height:30px;border:3px solid #f3f3f3;border-top:3px solid var(--curry,#CF723A);border-radius:50%;animation:spin 1s linear infinite;margin-right:1rem}
    @keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
    
    /* Mobile Responsive */
    @media (max-width: 768px){
        .page-title{font-size:2rem;margin-bottom:1.5rem}
        .date-navigation{flex-direction:column;gap:0.5rem}
        .package-stat-grid{grid-template-columns:repeat(2,1fr);gap:1rem;padding:1rem}
        .history-chart{grid-template-columns:repeat(4,1fr)}
        .meals-grid{grid-template-columns:1fr}
        .meal-nutrition-grid{grid-template-columns:1fr;gap:0.5rem}
        .card-header{padding:1.5rem}
        .goal-comparison{padding:1.5rem}
        .meals-section{padding:1.5rem}
        .meal-content{padding:1rem}
        .meal-image-container{height:150px}
        .meal-number{width:24px;height:24px;font-size:0.8rem;top:0.5rem;left:0.5rem}
        .quantity-badge{top:0.5rem;right:0.5rem;padding:0.2rem 0.5rem;font-size:0.75rem}
    }
    @media (max-width: 480px){
        .container{padding:0 15px}
        .page-title{font-size:1.8rem}
        .package-stat-grid{grid-template-columns:1fr;padding:1rem}
        .history-chart{grid-template-columns:repeat(3,1fr);gap:0.5rem}
        .nav-btn{padding:0.5rem 0.75rem;font-size:0.9rem}
    }
    /* Touch-friendly interactions */
    @media (hover: none){
        .meal-card:hover,.history-day:hover{transform:none}
        .nav-btn:hover:not(:disabled){transform:none}
    }
    </style>
</head>

<body class="has-header">
    <main class="main-content">
        <div class="container">
            <h1 class="page-title">
                <i class="fas fa-chart-pie"></i>
                Nutrition Tracking
            </h1>

            <?php if (isset($error_message)): ?>
                <div style="background:#f8d7da;color:#721c24;padding:1rem;border-radius:8px;margin-bottom:2rem;text-align:center">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Date Navigation -->
            <div class="date-navigation">
                <button onclick="navigateDate(-1)" class="nav-btn">
                    <i class="fas fa-chevron-left"></i> Previous Day
                </button>
                <div class="date-picker">
                    <input type="date" id="selectedDate" value="<?= htmlspecialchars($selected_date) ?>" 
                           max="<?= htmlspecialchars($today) ?>" 
                           min="<?= date('Y-m-d', strtotime('-14 days')) ?>"
                           onchange="loadDateNutrition(this.value)">
                    <label for="selectedDate">
                        <?= date('l, M j, Y', strtotime($selected_date)) ?>
                    </label>
                </div>
                <button onclick="navigateDate(1)" class="nav-btn" <?= $selected_date >= $today ? 'disabled' : '' ?>>
                    Next Day <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <!-- Level 1: Weekly Package Overview -->
            <?php if ($package_overview): ?>
            <div class="main-card package-overview">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-box"></i>
                        Weekly Package Overview
                    </h2>
                    <p class="card-subtitle">
                        <?= htmlspecialchars($package_overview['plan_name']) ?> 
                        (<?= date('M j', strtotime($package_overview['week_start'])) ?> - 
                         <?= date('M j', strtotime($package_overview['week_end'])) ?>)
                    </p>
                </div>
                <div class="package-stat-grid">
                    <div class="package-stat">
                        <div class="stat-value"><?= number_format($package_overview['total_calories']) ?></div>
                        <div class="stat-label">Total Calories</div>
                        <div class="stat-sub">Available this week</div>
                    </div>
                    <div class="package-stat">
                        <div class="stat-value"><?= $package_overview['total_meals'] ?></div>
                        <div class="stat-label">Total Meals</div>
                        <div class="stat-sub"><?= $package_overview['meals_per_week'] ?> planned</div>
                    </div>
                    <div class="package-stat">
                        <div class="stat-value"><?= number_format($package_overview['avg_calories_per_day']) ?></div>
                        <div class="stat-label">Avg per Day</div>
                        <div class="stat-sub"><?= $package_overview['avg_protein_per_day'] ?>g protein</div>
                    </div>
                    <div class="package-stat">
                        <div class="stat-value"><?= number_format($package_overview['total_protein']) ?>g</div>
                        <div class="stat-label">Total Protein</div>
                        <div class="stat-sub">For the week</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Level 2: Daily Goal Breakdown -->
            <div class="main-card goal-breakdown">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-bullseye"></i>
                        Daily Goal Breakdown
                    </h2>
                    <p class="card-subtitle">
                        <?= $selected_date === $today ? 'Today' : date('l, M j', strtotime($selected_date)) ?> vs 
                        <?= ucfirst($goal_breakdown['goal_type']) ?> Goal
                    </p>
                </div>
                <div class="goal-comparison">
                    <div class="goal-circle" style="background: conic-gradient(var(--curry,#CF723A) <?= $goal_breakdown['coverage_percent'] ?>%, #f0f0f0 0%)">
                        <span><?= $goal_breakdown['coverage_percent'] ?>%</span>
                    </div>
                    <div class="goal-details">
                        <div class="available"><?= number_format($goal_breakdown['available_calories']) ?> cal available</div>
                        <div class="target">Target: <?= number_format($goal_breakdown['target_calories']) ?> cal</div>
                        <?php if ($goal_breakdown['remaining_calories'] > 0): ?>
                            <div class="remaining">Still need: <?= number_format($goal_breakdown['remaining_calories']) ?> cal</div>
                        <?php else: ?>
                            <div class="target" style="color: #27ae60; font-weight: 600;">Goal achieved!</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Selected Day Meals -->
            <?php if ($selected_nutrition['meals_count'] > 0): ?>
            <div class="main-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-utensils"></i>
                        <?= $selected_date === $today ? "Today's Meals" : "Meals for " . date('M j', strtotime($selected_date)) ?>
                    </h2>
                    <p class="card-subtitle">
                        <?= $selected_nutrition['meals_count'] ?> meals providing <?= number_format($selected_nutrition['calories']) ?> calories
                    </p>
                </div>
                <div class="meals-section">
                    <div class="meals-grid">
                        <?php foreach ($selected_nutrition['meals'] as $index => $meal): ?>
                            <div class="meal-card">
                                <div class="meal-number"><?= $index + 1 ?></div>
                                
                                <div class="meal-image-container">
                                    <?php if (!empty($meal['main_image_url'])): ?>
                                        <img src="<?= htmlspecialchars($meal['main_image_url']) ?>" 
                                             alt="<?= htmlspecialchars($meal['name'] ?? $meal['name_thai']) ?>" 
                                             class="meal-image" 
                                             loading="lazy"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="meal-image-fallback" style="display: none;">
                                            <i class="fas fa-utensils"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="meal-image-fallback">
                                            <i class="fas fa-utensils"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (intval($meal['quantity']) > 1): ?>
                                        <div class="quantity-badge">×<?= intval($meal['quantity']) ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="meal-content">
                                    <div class="meal-name">
                                        <?= htmlspecialchars($meal['name'] ?? $meal['name_thai']) ?>
                                    </div>
                                    
                                    <?php if (!empty($meal['name_thai']) && $meal['name'] !== $meal['name_thai']): ?>
                                        <div class="meal-name-thai">
                                            <?= htmlspecialchars($meal['name_thai']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="meal-calories">
                                        <i class="fas fa-fire"></i>
                                        <?= number_format(floatval($meal['calories_per_serving']) * intval($meal['quantity'] ?: 1)) ?> cal
                                    </div>
                                    
                                    <div class="meal-nutrition-grid">
                                        <div class="nutrition-item">
                                            <i class="fas fa-drumstick-bite" style="color: #8e44ad;"></i>
                                            <span><?= number_format(floatval($meal['protein_g']) * intval($meal['quantity'] ?: 1), 1) ?>g</span>
                                            <small>Protein</small>
                                        </div>
                                        <div class="nutrition-item">
                                            <i class="fas fa-bread-slice" style="color: #f39c12;"></i>
                                            <span><?= number_format(floatval($meal['carbs_g']) * intval($meal['quantity'] ?: 1), 1) ?>g</span>
                                            <small>Carbs</small>
                                        </div>
                                        <div class="nutrition-item">
                                            <i class="fas fa-tint" style="color: #27ae60;"></i>
                                            <span><?= number_format(floatval($meal['fat_g']) * intval($meal['quantity'] ?: 1), 1) ?>g</span>
                                            <small>Fat</small>
                                        </div>
                                        <?php if (floatval($meal['fiber_g']) > 0): ?>
                                        <div class="nutrition-item">
                                            <i class="fas fa-leaf" style="color: #27ae60;"></i>
                                            <span><?= number_format(floatval($meal['fiber_g']) * intval($meal['quantity'] ?: 1), 1) ?>g</span>
                                            <small>Fiber</small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="main-card">
                <div class="empty-state">
                    <i class="fas fa-utensils"></i>
                    <h3>No Meals Scheduled</h3>
                    <p>No meals are scheduled for <?= $selected_date === $today ? 'today' : 'this day' ?>.</p>
                    <a href="subscribe.php" class="btn">
                        <i class="fas fa-plus"></i>
                        Order Meal Plan
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Historical View -->
            <?php if (!empty($nutrition_history)): ?>
            <div class="main-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-history"></i>
                        Nutrition History (7 Days)
                    </h2>
                    <p class="card-subtitle">
                        Click any day to view detailed nutrition information
                    </p>
                </div>
                <div class="history-chart">
                    <?php foreach ($nutrition_history as $date => $day_data): ?>
                        <div class="history-day <?= $date === $selected_date ? 'selected' : '' ?>" 
                             onclick="loadDateNutrition('<?= $date ?>')"
                             role="button"
                             tabindex="0"
                             onkeydown="if(event.key==='Enter')loadDateNutrition('<?= $date ?>')">
                            <div class="history-date"><?= date('M j', strtotime($date)) ?></div>
                            <div class="history-bar">
                                <div class="history-fill" style="width: <?= min((intval($day_data['calories']) / 2000) * 100, 100) ?>%"></div>
                            </div>
                            <div class="history-value"><?= number_format($day_data['calories']) ?></div>
                            <div class="history-label">cal</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Goal Setting -->
            <div class="main-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-target"></i>
                        Nutrition Goals
                    </h2>
                    <p class="card-subtitle">
                        Set your nutrition targets to track progress effectively
                    </p>
                </div>
                <div style="padding: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="goal-btn <?= ($_SESSION['nutrition_goal'] ?? '') === 'weight_loss' ? 'selected' : '' ?>" 
                         onclick="setNutritionGoal('weight_loss', this)"
                         style="padding: 1rem; border: 2px solid #ddd; border-radius: 12px; background: white; cursor: pointer; text-align: center; transition: all 0.2s; <?= ($_SESSION['nutrition_goal'] ?? '') === 'weight_loss' ? 'border-color: #CF723A; background: #CF723A; color: white;' : '' ?>">
                        <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🏃‍♀️</div>
                        <div style="font-size: 1rem; margin-bottom: 0.3rem; font-weight: 600;">Weight Loss</div>
                        <div style="font-size: 0.8rem; opacity: 0.8;">1,600 calories, High protein</div>
                    </div>
                    
                    <div class="goal-btn <?= ($_SESSION['nutrition_goal'] ?? 'maintenance') === 'maintenance' ? 'selected' : '' ?>" 
                         onclick="setNutritionGoal('maintenance', this)"
                         style="padding: 1rem; border: 2px solid #ddd; border-radius: 12px; background: white; cursor: pointer; text-align: center; transition: all 0.2s; <?= ($_SESSION['nutrition_goal'] ?? 'maintenance') === 'maintenance' ? 'border-color: #CF723A; background: #CF723A; color: white;' : '' ?>">
                        <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">⚖️</div>
                        <div style="font-size: 1rem; margin-bottom: 0.3rem; font-weight: 600;">Maintenance</div>
                        <div style="font-size: 0.8rem; opacity: 0.8;">2,000 calories, Balanced</div>
                    </div>
                    
                    <div class="goal-btn <?= ($_SESSION['nutrition_goal'] ?? '') === 'muscle_gain' ? 'selected' : '' ?>" 
                         onclick="setNutritionGoal('muscle_gain', this)"
                         style="padding: 1rem; border: 2px solid #ddd; border-radius: 12px; background: white; cursor: pointer; text-align: center; transition: all 0.2s; <?= ($_SESSION['nutrition_goal'] ?? '') === 'muscle_gain' ? 'border-color: #CF723A; background: #CF723A; color: white;' : '' ?>">
                        <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">💪</div>
                        <div style="font-size: 1rem; margin-bottom: 0.3rem; font-weight: 600;">Muscle Gain</div>
                        <div style="font-size: 0.8rem; opacity: 0.8;">2,400 calories, High protein</div>
                    </div>
                    
                    <div class="goal-btn <?= ($_SESSION['nutrition_goal'] ?? '') === 'healthy_thai' ? 'selected' : '' ?>" 
                         onclick="setNutritionGoal('healthy_thai', this)"
                         style="padding: 1rem; border: 2px solid #ddd; border-radius: 12px; background: white; cursor: pointer; text-align: center; transition: all 0.2s; <?= ($_SESSION['nutrition_goal'] ?? '') === 'healthy_thai' ? 'border-color: #CF723A; background: #CF723A; color: white;' : '' ?>">
                        <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🇹🇭</div>
                        <div style="font-size: 1rem; margin-bottom: 0.3rem; font-weight: 600;">Healthy Thai</div>
                        <div style="font-size: 0.8rem; opacity: 0.8;">1,800 calories, Thai-focused</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Loading Modal -->
    <div id="loadingModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 1rem; text-align: center;">
            <div class="spinner"></div>
            <div style="margin-top: 1rem; color: #333;">Loading nutrition data...</div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Nutrition Tracking loaded');
            initializePage();
        });

        function initializePage() {
            const cards = document.querySelectorAll('.main-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add hover effects for goal buttons
            document.querySelectorAll('.goal-btn').forEach(btn => {
                if (!btn.classList.contains('selected')) {
                    btn.addEventListener('mouseenter', function() {
                        this.style.borderColor = '#CF723A';
                        this.style.background = 'rgba(207, 114, 58, 0.05)';
                        this.style.transform = 'translateY(-2px)';
                    });
                    btn.addEventListener('mouseleave', function() {
                        this.style.borderColor = '#ddd';
                        this.style.background = 'white';
                        this.style.transform = 'translateY(0)';
                    });
                }
            });
        }

        function navigateDate(direction) {
            const currentDate = document.getElementById('selectedDate').value;
            const newDate = new Date(currentDate);
            newDate.setDate(newDate.getDate() + direction);
            
            const today = new Date();
            const minDate = new Date();
            minDate.setDate(today.getDate() - 14);
            
            if (newDate <= today && newDate >= minDate) {
                loadDateNutrition(newDate.toISOString().split('T')[0]);
            }
        }

        function loadDateNutrition(date) {
            showLoading();
            
            const url = new URL(window.location);
            url.searchParams.set('date', date);
            window.history.pushState({}, '', url);
            
            document.getElementById('selectedDate').value = date;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_date_nutrition&selected_date=' + encodeURIComponent(date)
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    window.location.reload();
                } else {
                    showNotification('Failed to load nutrition data', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showNotification('Network error', 'error');
            });
        }

        function setNutritionGoal(goalType, clickedElement) {
            showLoading();
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=set_nutrition_goal&goal_type=' + encodeURIComponent(goalType)
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    document.querySelectorAll('.goal-btn').forEach(btn => {
                        btn.classList.remove('selected');
                        btn.style.borderColor = '#ddd';
                        btn.style.background = 'white';
                        btn.style.color = '#333';
                    });
                    
                    clickedElement.classList.add('selected');
                    clickedElement.style.borderColor = '#CF723A';
                    clickedElement.style.background = '#CF723A';
                    clickedElement.style.color = 'white';
                    
                    showNotification('Goal updated successfully!', 'success');
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('Failed to update goal', 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showNotification('Network error', 'error');
            });
        }

        function showLoading() {
            document.getElementById('loadingModal').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingModal').style.display = 'none';
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
                border-radius: 8px;
                font-weight: 600;
                z-index: 10000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                transform: translateX(100%);
                transition: transform 0.3s ease;
                max-width: 300px;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
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
                const modals = document.querySelectorAll('[style*="position: fixed"]');
                modals.forEach(modal => {
                    if (modal.id !== 'loadingModal') {
                        modal.remove();
                    }
                });
            }
        });

        // Auto-sync check (reduced frequency for production)
        setInterval(async function() {
            if (!document.hidden && Math.random() < 0.1) { // 10% chance every 5 minutes
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=sync_latest_orders'
                    });
                    const data = await response.json();
                    
                    if (data.success && data.updated && data.synced_meals > 0) {
                        showNotification(`Added ${data.synced_meals} new meals to tracking`, 'success');
                        setTimeout(() => window.location.reload(), 2000);
                    }
                } catch (error) {
                    console.log('Auto-sync failed:', error);
                }
            }
        }, 5 * 60 * 1000); // Check every 5 minutes
    </script>
</body>
</html>