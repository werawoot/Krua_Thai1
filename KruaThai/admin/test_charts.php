<?php
/**
 * Krua Thai - Chart Debug & Test System
 * File: admin/test_charts.php
 * Purpose: Quick diagnosis and data insertion for charts
 */

session_start();
require_once '../config/database.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die('Access denied');
}

// Handle actions
$action = $_GET['action'] ?? 'check';
$result = '';

try {
    switch($action) {
        case 'check':
            $result = checkDataStatus($pdo);
            break;
        case 'insert':
            $result = insertSampleData($pdo);
            break;
        case 'test_api':
            $result = testChartAPI($pdo);
            break;
    }
} catch (Exception $e) {
    $result = "Error: " . $e->getMessage();
}

function checkDataStatus($pdo) {
    $output = "📊 Krua Thai - Data Status Check\n";
    $output .= "=" . str_repeat("=", 40) . "\n\n";

    // Check payments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM payments WHERE status = 'completed'");
    $payments = $stmt->fetch()['count'];
    $output .= "💳 Completed Payments: {$payments}\n";

    // Check orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
    $orders = $stmt->fetch()['count'];
    $output .= "🛒 Total Orders: {$orders}\n";

    // Check subscriptions
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM subscriptions");
    $subscriptions = $stmt->fetch()['count'];
    $output .= "📅 Total Subscriptions: {$subscriptions}\n";

    // Check users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
    $customers = $stmt->fetch()['count'];
    $output .= "👥 Total Customers: {$customers}\n";

    // Check menus
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM menus WHERE is_active = 1");
    $menus = $stmt->fetch()['count'];
    $output .= "🍜 Active Menus: {$menus}\n";

    $output .= "\n" . str_repeat("=", 40) . "\n";

    if ($payments == 0 || $orders == 0 || $subscriptions == 0) {
        $output .= "⚠️  WARNING: Low/No data detected!\n";
        $output .= "📋 Recommendation: Insert sample data\n";
        $output .= "🔗 Click 'Insert Sample Data' button below\n";
    } else {
        $output .= "✅ Data looks good for charts!\n";
        $output .= "🔗 Try 'Test Chart API' to verify\n";
    }

    return $output;
}

function insertSampleData($pdo) {
    $output = "🚀 Inserting Sample Data...\n\n";
    
    try {
        // Insert sample users (customers)
        $customerIds = [];
        for ($i = 1; $i <= 5; $i++) {
            $customerId = generateUUID();
            $customerIds[] = $customerId;
            
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO users (id, email, password_hash, first_name, last_name, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'customer', 'active', ?)
            ");
            $stmt->execute([
                $customerId,
                "customer{$i}@test.com",
                password_hash('password123', PASSWORD_DEFAULT),
                "Customer",
                "Test{$i}",
                date('Y-m-d H:i:s', strtotime("-{$i} days"))
            ]);
        }
        $output .= "✅ Inserted 5 test customers\n";

        // Insert sample subscription plans (if not exist)
        $planIds = [];
        $plans = [
            ['weekly_basic', 'Weekly Basic', 'แพ็กเกจ 5 มื้อ', 'weekly', 5, 1, 399.00],
            ['weekly_premium', 'Weekly Premium', 'แพ็กเกจ 7 มื้อ', 'weekly', 7, 1, 599.00],
            ['monthly_family', 'Monthly Family', 'แพ็กเกจครอบครัว', 'monthly', 14, 4, 1599.00]
        ];

        foreach ($plans as $plan) {
            $planId = generateUUID();
            $planIds[] = $planId;
            
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO subscription_plans 
                (id, name, name_thai, plan_type, meals_per_week, weeks_duration, base_price, final_price, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $planId, $plan[1], $plan[2], $plan[3], $plan[4], $plan[5], $plan[6], $plan[6]
            ]);
        }
        $output .= "✅ Inserted 3 subscription plans\n";

        // Insert sample subscriptions
        $subscriptionIds = [];
        foreach ($customerIds as $index => $customerId) {
            $subscriptionId = generateUUID();
            $subscriptionIds[] = $subscriptionId;
            $planId = $planIds[array_rand($planIds)];
            
            $stmt = $pdo->prepare("
                INSERT INTO subscriptions 
                (id, user_id, plan_id, status, start_date, end_date, created_at)
                VALUES (?, ?, ?, 'active', ?, ?, ?)
            ");
            $startDate = date('Y-m-d', strtotime("-{$index} days"));
            $endDate = date('Y-m-d', strtotime("+30 days"));
            $createdAt = date('Y-m-d H:i:s', strtotime("-{$index} days"));
            
            $stmt->execute([$subscriptionId, $customerId, $planId, $startDate, $endDate, $createdAt]);
        }
        $output .= "✅ Inserted 5 subscriptions\n";

        // Insert sample payments
        $paymentMethods = ['credit_card', 'promptpay', 'bank_transfer'];
        foreach ($subscriptionIds as $index => $subscriptionId) {
            $paymentId = generateUUID();
            $amount = [399.00, 599.00, 899.00, 1199.00, 1599.00][array_rand([399.00, 599.00, 899.00, 1199.00, 1599.00])];
            $method = $paymentMethods[array_rand($paymentMethods)];
            
            $stmt = $pdo->prepare("
                INSERT INTO payments 
                (id, subscription_id, payment_method, amount, currency, status, created_at, processed_at)
                VALUES (?, ?, ?, ?, 'THB', 'completed', ?, ?)
            ");
            $createdAt = date('Y-m-d H:i:s', strtotime("-{$index} days"));
            $stmt->execute([$paymentId, $subscriptionId, $method, $amount, $createdAt, $createdAt]);
        }
        $output .= "✅ Inserted 5 payments\n";

        // Insert sample menus
        $menuCategories = ['main_course', 'appetizer', 'dessert'];
        $menuNames = [
            ['Pad Thai Healthy', 'ผัดไทยเพื่อสุขภาพ'],
            ['Green Curry Light', 'แกงเขียวหวานไลท์'],
            ['Tom Yum Soup', 'ต้มยำกุ้ง'],
            ['Mango Sticky Rice', 'ข้าวเหนียวมะม่วง'],
            ['Thai Salad', 'ส้มตำไทย']
        ];

        $menuIds = [];
        foreach ($menuNames as $index => $menu) {
            $menuId = generateUUID();
            $menuIds[] = $menuId;
            $category = $menuCategories[array_rand($menuCategories)];
            
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO menus 
                (id, name, name_thai, category, price, calories, protein, carbs, fat, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $menuId, $menu[0], $menu[1], $category,
                rand(150, 350), rand(300, 600), rand(15, 35), rand(30, 60), rand(8, 25)
            ]);
        }
        $output .= "✅ Inserted 5 menus\n";

        // Insert sample orders
        foreach ($subscriptionIds as $index => $subscriptionId) {
            $orderId = generateUUID();
            $menuId = $menuIds[array_rand($menuIds)];
            $status = ['pending', 'confirmed', 'preparing', 'delivered'][array_rand(['pending', 'confirmed', 'preparing', 'delivered'])];
            
            $stmt = $pdo->prepare("
                INSERT INTO orders 
                (id, subscription_id, delivery_date, status, created_at)
                VALUES (?, ?, ?, ?, ?)
            ");
            $deliveryDate = date('Y-m-d', strtotime("+{$index} days"));
            $createdAt = date('Y-m-d H:i:s', strtotime("-{$index} hours"));
            $stmt->execute([$orderId, $subscriptionId, $deliveryDate, $status, $createdAt]);

            // Insert order menu items
            $stmt = $pdo->prepare("
                INSERT INTO order_menus (id, order_id, menu_id, quantity, created_at)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([generateUUID(), $orderId, $menuId, rand(1, 3), $createdAt]);
        }
        $output .= "✅ Inserted 5 orders with menu items\n";

        $output .= "\n🎉 Sample data insertion completed!\n";
        $output .= "📊 Charts should now display data\n";
        $output .= "🔄 Refresh charts page to see results\n";

        return $output;
        
    } catch (Exception $e) {
        return "❌ Error inserting data: " . $e->getMessage();
    }
}

function testChartAPI($pdo) {
    $output = "🧪 Testing Chart API...\n\n";
    
    // Test revenue chart data
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(p.created_at) as period,
                SUM(p.amount) as revenue,
                COUNT(p.id) as transaction_count
            FROM payments p
            WHERE p.status = 'completed' 
            AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(p.created_at)
            ORDER BY period ASC
            LIMIT 5
        ");
        $stmt->execute();
        $revenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $output .= "💰 Revenue Chart Data:\n";
        if (empty($revenueData)) {
            $output .= "   ⚠️  No revenue data found\n";
        } else {
            foreach ($revenueData as $row) {
                $output .= "   📅 {$row['period']}: ฿{$row['revenue']} ({$row['transaction_count']} transactions)\n";
            }
        }
        
        // Test order volume data
        $stmt = $pdo->prepare("
            SELECT 
                DATE(o.created_at) as period,
                COUNT(o.id) as total_orders,
                SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders
            FROM orders o
            WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(o.created_at)
            ORDER BY period ASC
            LIMIT 5
        ");
        $stmt->execute();
        $orderData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $output .= "\n🛒 Order Volume Data:\n";
        if (empty($orderData)) {
            $output .= "   ⚠️  No order data found\n";
        } else {
            foreach ($orderData as $row) {
                $output .= "   📅 {$row['period']}: {$row['total_orders']} orders ({$row['completed_orders']} completed)\n";
            }
        }
        
        $output .= "\n✅ API test completed\n";
        
        if (empty($revenueData) && empty($orderData)) {
            $output .= "❌ No data available - insert sample data first\n";
        } else {
            $output .= "🎯 API working - charts should display data\n";
        }
        
    } catch (Exception $e) {
        $output .= "❌ API Error: " . $e->getMessage() . "\n";
    }
    
    return $output;
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chart Debug & Test - Krua Thai</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #F8F6F0;
            color: #2c3e50;
            line-height: 1.6;
            padding: 2rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(139, 90, 60, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #8B5A3C, #A67C52);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            opacity: 0.9;
        }

        .content {
            padding: 2rem;
        }

        .actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .btn {
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-primary {
            background: #8B5A3C;
            color: white;
        }

        .btn-primary:hover {
            background: #A67C52;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #2ecc71;
            transform: translateY(-2px);
        }

        .btn-info {
            background: #3498db;
            color: white;
        }

        .btn-info:hover {
            background: #5dade2;
            transform: translateY(-2px);
        }

        .btn-back {
            background: #95a5a6;
            color: white;
        }

        .btn-back:hover {
            background: #7f8c8d;
        }

        .result {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
            font-family: 'Courier New', monospace;
            white-space: pre-line;
            font-size: 0.9rem;
            line-height: 1.4;
            color: #2c3e50;
        }

        .icon {
            margin-right: 0.5rem;
        }

        .warning {
            background: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }

        .success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Chart Debug & Test System</h1>
            <p>Diagnose and fix chart loading issues</p>
        </div>
        
        <div class="content">
            <div class="actions">
                <a href="?action=check" class="btn btn-primary">
                    <span class="icon">📊</span>
                    Check Data Status
                </a>
                <a href="?action=insert" class="btn btn-success">
                    <span class="icon">🚀</span>
                    Insert Sample Data
                </a>
                <a href="?action=test_api" class="btn btn-info">
                    <span class="icon">🧪</span>
                    Test Chart API
                </a>
                <a href="charts.php" class="btn btn-back">
                    <span class="icon">📈</span>
                    Back to Charts
                </a>
            </div>

            <?php if ($result): ?>
                <div class="result <?= strpos($result, 'Error') !== false ? 'error' : (strpos($result, 'WARNING') !== false ? 'warning' : 'success') ?>">
                    <?= htmlspecialchars($result) ?>
                </div>
            <?php endif; ?>

            <div class="result">
📋 Quick Diagnosis Guide:

1️⃣ START HERE: Click "Check Data Status"
   ✅ If data exists → Click "Test Chart API"
   ❌ If no data → Click "Insert Sample Data"

2️⃣ After inserting data → Go back to Charts page

3️⃣ Still not working? Check browser Console (F12)

4️⃣ Need help? Check PHP error logs
            </div>
        </div>
    </div>
</body>
</html>