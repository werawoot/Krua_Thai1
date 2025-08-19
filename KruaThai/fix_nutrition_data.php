<?php
/**
 * Fix Nutrition Data
 * File: fix_nutrition_data.php
 * วางไฟล์นี้ในโฟลเดอร์หลัก แล้วเปิด localhost/fix_nutrition_data.php
 */

session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    die('Please login first');
}

echo "<h2>🔧 Fix Nutrition Data</h2>";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // ===== 1. Check current menus without nutrition data =====
    echo "<h3>📋 Menus Missing Nutrition Data</h3>";
    $stmt = $pdo->prepare("
        SELECT id, name, name_thai, calories_per_serving, protein_g, carbs_g, fat_g
        FROM menus 
        WHERE calories_per_serving IS NULL OR calories_per_serving = 0
        ORDER BY name
    ");
    $stmt->execute();
    $empty_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($empty_menus)) {
        echo "✅ All menus have nutrition data!<br><br>";
    } else {
        echo "<p>Found " . count($empty_menus) . " menus without nutrition data:</p>";
        echo "<ul>";
        foreach ($empty_menus as $menu) {
            echo "<li>{$menu['name']} ({$menu['name_thai']}) - ID: {$menu['id']}</li>";
        }
        echo "</ul>";
    }
    
    // ===== 2. Sample Thai food nutrition data =====
    $thai_nutrition = [
        // Curry dishes
        'Green Curry' => ['calories' => 320, 'protein' => 28, 'carbs' => 12, 'fat' => 18, 'fiber' => 3, 'sodium' => 850],
        'Red Curry' => ['calories' => 310, 'protein' => 26, 'carbs' => 14, 'fat' => 16, 'fiber' => 3, 'sodium' => 900],
        'Massaman Curry' => ['calories' => 380, 'protein' => 22, 'carbs' => 18, 'fat' => 24, 'fiber' => 4, 'sodium' => 750],
        'Panang Curry' => ['calories' => 340, 'protein' => 24, 'carbs' => 16, 'fat' => 20, 'fiber' => 3, 'sodium' => 800],
        
        // Stir-fry dishes
        'Pad Thai' => ['calories' => 450, 'protein' => 18, 'carbs' => 58, 'fat' => 16, 'fiber' => 3, 'sodium' => 1200],
        'Cashew Chicken' => ['calories' => 380, 'protein' => 32, 'carbs' => 28, 'fat' => 15, 'fiber' => 2, 'sodium' => 950],
        'Basil Stir-Fry' => ['calories' => 290, 'protein' => 25, 'carbs' => 12, 'fat' => 16, 'fiber' => 2, 'sodium' => 1100],
        'Sweet and Sour' => ['calories' => 320, 'protein' => 20, 'carbs' => 42, 'fat' => 8, 'fiber' => 2, 'sodium' => 800],
        
        // Grilled/BBQ
        'Beef Crying Tiger' => ['calories' => 420, 'protein' => 35, 'carbs' => 8, 'fat' => 26, 'fiber' => 1, 'sodium' => 650],
        'Grilled Chicken' => ['calories' => 280, 'protein' => 32, 'carbs' => 4, 'fat' => 14, 'fiber' => 1, 'sodium' => 580],
        'BBQ Pork' => ['calories' => 350, 'protein' => 28, 'carbs' => 12, 'fat' => 20, 'fiber' => 1, 'sodium' => 750],
        
        // Salads
        'Som Tam' => ['calories' => 150, 'protein' => 8, 'carbs' => 22, 'fat' => 4, 'fiber' => 6, 'sodium' => 1200],
        'Larb' => ['calories' => 240, 'protein' => 22, 'carbs' => 8, 'fat' => 12, 'fiber' => 2, 'sodium' => 900],
        'Vegan Larb' => ['calories' => 180, 'protein' => 15, 'carbs' => 18, 'fat' => 6, 'fiber' => 4, 'sodium' => 800],
        
        // Soups
        'Tom Yum' => ['calories' => 180, 'protein' => 20, 'carbs' => 8, 'fat' => 6, 'fiber' => 2, 'sodium' => 1100],
        'Tom Kha' => ['calories' => 220, 'protein' => 18, 'carbs' => 12, 'fat' => 12, 'fiber' => 2, 'sodium' => 800],
        
        // Rice dishes  
        'Fried Rice' => ['calories' => 380, 'protein' => 14, 'carbs' => 62, 'fat' => 8, 'fiber' => 2, 'sodium' => 950],
        'Pineapple Fried Rice' => ['calories' => 420, 'protein' => 16, 'carbs' => 68, 'fat' => 10, 'fiber' => 3, 'sodium' => 800],
        
        // Default for unknown dishes
        'DEFAULT' => ['calories' => 300, 'protein' => 20, 'carbs' => 25, 'fat' => 12, 'fiber' => 3, 'sodium' => 800]
    ];
    
    // ===== 3. Update nutrition data =====
    if (isset($_POST['fix_nutrition'])) {
        echo "<h3>🔄 Updating Nutrition Data...</h3>";
        $updated_count = 0;
        
        foreach ($empty_menus as $menu) {
            $menu_name = $menu['name'];
            $nutrition = null;
            
            // Try to match by menu name
            foreach ($thai_nutrition as $dish_name => $dish_nutrition) {
                if ($dish_name === 'DEFAULT') continue;
                
                if (stripos($menu_name, $dish_name) !== false || 
                    stripos($dish_name, $menu_name) !== false) {
                    $nutrition = $dish_nutrition;
                    break;
                }
            }
            
            // Use default if no match found
            if (!$nutrition) {
                $nutrition = $thai_nutrition['DEFAULT'];
            }
            
            // Update the menu
            $stmt = $pdo->prepare("
                UPDATE menus SET 
                    calories_per_serving = ?,
                    protein_g = ?,
                    carbs_g = ?,
                    fat_g = ?,
                    fiber_g = ?,
                    sodium_mg = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $nutrition['calories'],
                $nutrition['protein'],
                $nutrition['carbs'],
                $nutrition['fat'],
                $nutrition['fiber'],
                $nutrition['sodium'],
                $menu['id']
            ]);
            
            if ($result) {
                echo "✅ Updated: {$menu_name} → {$nutrition['calories']} cal, {$nutrition['protein']}g protein<br>";
                $updated_count++;
            } else {
                echo "❌ Failed: {$menu_name}<br>";
            }
        }
        
        echo "<p><strong>📊 Summary: Updated $updated_count menus</strong></p>";
        echo "<p><a href='nutrition-tracking.php'>🎯 Test Nutrition Tracking Now!</a></p>";
        
    } else {
        // Show form to fix nutrition
        if (!empty($empty_menus)) {
            echo "<h3>🚀 Ready to Fix?</h3>";
            echo "<p>This will add realistic Thai food nutrition data to all empty menus.</p>";
            echo "<form method='post'>";
            echo "<button type='submit' name='fix_nutrition' style='
                background: #27ae60; 
                color: white; 
                padding: 15px 30px; 
                border: none; 
                border-radius: 8px; 
                font-size: 16px; 
                font-weight: bold; 
                cursor: pointer;
            '>🔧 Fix All Nutrition Data</button>";
            echo "</form>";
        }
    }
    
    // ===== 4. Add today's meals (optional) =====
    echo "<hr>";
    echo "<h3>📅 Add Today's Meals (Optional)</h3>";
    
    if (isset($_POST['add_today_meals'])) {
        echo "<h4>🍽️ Adding Today's Meals...</h4>";
        
        // Get user's latest active subscription
        $stmt = $pdo->prepare("
            SELECT id FROM subscriptions 
            WHERE user_id = ? AND status = 'active' 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subscription) {
            // Get some menus with nutrition data
            $stmt = $pdo->prepare("
                SELECT id, name FROM menus 
                WHERE calories_per_serving > 0 
                ORDER BY RAND() LIMIT 3
            ");
            $stmt->execute();
            $sample_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $today = date('Y-m-d');
            $added_count = 0;
            
            foreach ($sample_menus as $menu) {
                $menu_id = bin2hex(random_bytes(16));
                $stmt = $pdo->prepare("
                    INSERT INTO subscription_menus (id, subscription_id, menu_id, delivery_date, quantity, status)
                    VALUES (?, ?, ?, ?, 1, 'scheduled')
                ");
                
                $result = $stmt->execute([
                    $menu_id,
                    $subscription['id'],
                    $menu['id'],
                    $today
                ]);
                
                if ($result) {
                    echo "✅ Added: {$menu['name']} for today<br>";
                    $added_count++;
                }
            }
            
            echo "<p><strong>📊 Added $added_count meals for today!</strong></p>";
            echo "<p><a href='nutrition-tracking.php'>🎯 Check Nutrition Tracking!</a></p>";
            
        } else {
            echo "❌ No active subscription found!<br>";
        }
        
    } else {
        echo "<p>Want to add some sample meals for today to test the system?</p>";
        echo "<form method='post'>";
        echo "<button type='submit' name='add_today_meals' style='
            background: #3498db; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer;
        '>🍽️ Add Today's Test Meals</button>";
        echo "</form>";
    }
    
} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
}

echo "<hr>";
echo "<p><a href='debug_nutrition.php'>🔍 Back to Debug</a> | <a href='nutrition-tracking.php'>🥗 Nutrition Tracking</a></p>";
?>