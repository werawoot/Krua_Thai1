<?php
/**
 * Debug Nutrition Data
 * File: debug_nutrition.php
 * วางไฟล์นี้ในโฟลเดอร์หลัก แล้วเปิด localhost/debug_nutrition.php
 */

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    die('Please login first');
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$week_start = date('Y-m-d', strtotime('monday this week'));

echo "<h2>🔍 Nutrition Debug Report</h2>";
echo "<p><strong>User ID:</strong> $user_id</p>";
echo "<p><strong>Today:</strong> $today</p>";
echo "<p><strong>Week Start:</strong> $week_start</p>";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // ===== 1. Check User's Subscriptions =====
    echo "<h3>📦 User Subscriptions</h3>";
    $stmt = $pdo->prepare("
        SELECT s.id, s.status, s.start_date, sp.name 
        FROM subscriptions s 
        JOIN subscription_plans sp ON s.plan_id = sp.id 
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($subscriptions)) {
        echo "❌ <strong>No subscriptions found!</strong><br>";
        echo "👉 User needs to create a subscription first<br><br>";
    } else {
        foreach ($subscriptions as $sub) {
            echo "✅ Subscription: {$sub['name']} (Status: {$sub['status']}, Start: {$sub['start_date']})<br>";
        }
    }
    
    // ===== 2. Check Subscription Menus =====
    echo "<h3>🍽️ Subscription Menus (This Week)</h3>";
    $stmt = $pdo->prepare("
        SELECT sm.delivery_date, m.name, m.name_thai, m.calories_per_serving, 
               m.protein_g, sm.quantity, s.id as sub_id
        FROM subscription_menus sm
        JOIN menus m ON sm.menu_id = m.id
        JOIN subscriptions s ON sm.subscription_id = s.id
        WHERE s.user_id = ? 
        AND sm.delivery_date BETWEEN ? AND DATE_ADD(?, INTERVAL 6 DAY)
        ORDER BY sm.delivery_date, sm.created_at
    ");
    $stmt->execute([$user_id, $week_start, $week_start]);
    $weekly_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($weekly_menus)) {
        echo "❌ <strong>No menus found for this week!</strong><br>";
        echo "👉 Check if subscription has selected menus<br>";
        echo "👉 Check if delivery_date is in range: $week_start to " . date('Y-m-d', strtotime($week_start . ' +6 days')) . "<br><br>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>Date</th><th>Menu</th><th>Calories</th><th>Protein</th><th>Qty</th><th>Total Cal</th>";
        echo "</tr>";
        
        $total_calories = 0;
        $total_protein = 0;
        $total_meals = 0;
        
        foreach ($weekly_menus as $menu) {
            $cal_total = ($menu['calories_per_serving'] ?? 0) * ($menu['quantity'] ?? 1);
            $protein_total = ($menu['protein_g'] ?? 0) * ($menu['quantity'] ?? 1);
            
            $total_calories += $cal_total;
            $total_protein += $protein_total;
            $total_meals += ($menu['quantity'] ?? 1);
            
            echo "<tr>";
            echo "<td>{$menu['delivery_date']}</td>";
            echo "<td>{$menu['name']} ({$menu['name_thai']})</td>";
            echo "<td>{$menu['calories_per_serving']}</td>";
            echo "<td>{$menu['protein_g']}g</td>";
            echo "<td>{$menu['quantity']}</td>";
            echo "<td><strong>$cal_total</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p><strong>📊 Week Summary:</strong></p>";
        echo "• Total Calories: <strong>$total_calories</strong><br>";
        echo "• Total Protein: <strong>" . number_format($total_protein, 1) . "g</strong><br>";
        echo "• Total Meals: <strong>$total_meals</strong><br>";
        echo "• Avg Daily Calories: <strong>" . round($total_calories / 7) . "</strong><br>";
        echo "• Avg Daily Protein: <strong>" . round($total_protein / 7, 1) . "g</strong><br>";
        echo "• Avg Meals/Day: <strong>" . round($total_meals / 7, 1) . "</strong><br><br>";
    }
    
    // ===== 3. Check Today's Data =====
    echo "<h3>📅 Today's Data ($today)</h3>";
    $stmt = $pdo->prepare("
        SELECT m.name, m.name_thai, m.calories_per_serving, m.protein_g, 
               m.carbs_g, m.fat_g, sm.quantity
        FROM subscription_menus sm
        JOIN menus m ON sm.menu_id = m.id
        JOIN subscriptions s ON sm.subscription_id = s.id
        WHERE s.user_id = ? AND DATE(sm.delivery_date) = ?
    ");
    $stmt->execute([$user_id, $today]);
    $today_meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($today_meals)) {
        echo "❌ <strong>No meals for today!</strong><br>";
        echo "👉 Check if today's date ($today) has meals in subscription_menus<br><br>";
    } else {
        $today_cal = 0;
        $today_protein = 0;
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #e8f5e8;'>";
        echo "<th>Menu</th><th>Calories</th><th>Protein</th><th>Carbs</th><th>Fat</th><th>Qty</th><th>Total Cal</th>";
        echo "</tr>";
        
        foreach ($today_meals as $meal) {
            $qty = $meal['quantity'] ?? 1;
            $cal = ($meal['calories_per_serving'] ?? 0) * $qty;
            $protein = ($meal['protein_g'] ?? 0) * $qty;
            
            $today_cal += $cal;
            $today_protein += $protein;
            
            echo "<tr>";
            echo "<td>{$meal['name']}</td>";
            echo "<td>{$meal['calories_per_serving']}</td>";
            echo "<td>{$meal['protein_g']}g</td>";
            echo "<td>{$meal['carbs_g']}g</td>";
            echo "<td>{$meal['fat_g']}g</td>";
            echo "<td>$qty</td>";
            echo "<td><strong>$cal</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p><strong>🍽️ Today Summary:</strong></p>";
        echo "• Total Calories: <strong>$today_cal</strong><br>";
        echo "• Total Protein: <strong>" . number_format($today_protein, 1) . "g</strong><br>";
        echo "• Meals Count: <strong>" . count($today_meals) . "</strong><br><br>";
    }
    
    // ===== 4. Check Raw Query Results =====
    echo "<h3>🔍 Raw Query Test</h3>";
    
    // Test the exact query from nutrition-tracking.php
    $stmt = $pdo->prepare("
        SELECT m.name_thai, m.name, m.calories_per_serving, m.protein_g, m.carbs_g, m.fat_g, 
               m.fiber_g, m.sodium_mg, sm.quantity, m.main_image_url,
               sm.delivery_date, s.id as subscription_id
        FROM subscription_menus sm
        JOIN menus m ON sm.menu_id = m.id
        JOIN subscriptions s ON sm.subscription_id = s.id
        WHERE s.user_id = ? AND DATE(sm.delivery_date) = ?
    ");
    $stmt->execute([$user_id, $today]);
    $raw_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Query for today ($today):</strong></p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
    echo "SELECT m.name_thai, m.name, m.calories_per_serving, m.protein_g, m.carbs_g, m.fat_g, 
       m.fiber_g, m.sodium_mg, sm.quantity, m.main_image_url,
       sm.delivery_date, s.id as subscription_id
FROM subscription_menus sm
JOIN menus m ON sm.menu_id = m.id
JOIN subscriptions s ON sm.subscription_id = s.id
WHERE s.user_id = '$user_id' AND DATE(sm.delivery_date) = '$today'";
    echo "</pre>";
    
    echo "<p><strong>Results:</strong> " . count($raw_results) . " rows</p>";
    
    if (!empty($raw_results)) {
        echo "<pre style='background: #e8f5e8; padding: 10px; border-radius: 5px;'>";
        print_r($raw_results);
        echo "</pre>";
    }
    
    // ===== 5. Check All Delivery Dates =====
    echo "<h3>📆 All Delivery Dates for User</h3>";
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(sm.delivery_date) as delivery_date, COUNT(*) as meals_count
        FROM subscription_menus sm
        JOIN subscriptions s ON sm.subscription_id = s.id
        WHERE s.user_id = ?
        GROUP BY DATE(sm.delivery_date)
        ORDER BY delivery_date DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $all_dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($all_dates)) {
        echo "❌ <strong>No delivery dates found at all!</strong><br>";
    } else {
        echo "<ul>";
        foreach ($all_dates as $date) {
            $is_today = ($date['delivery_date'] === $today) ? " 👈 TODAY" : "";
            echo "<li>{$date['delivery_date']}: {$date['meals_count']} meals$is_today</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Database Error:</strong> " . $e->getMessage();
}

echo "<hr>";
echo "<h3>🛠️ Next Steps</h3>";
echo "<ol>";
echo "<li>If no subscriptions → Create subscription first</li>";
echo "<li>If no menus → Add menus to subscription</li>";
echo "<li>If delivery_date wrong → Check meal selection date</li>";
echo "<li>If calories = 0 → Check menus table nutrition data</li>";
echo "</ol>";

echo "<p><a href='nutrition-tracking.php'>← Back to Nutrition Tracking</a></p>";
?>