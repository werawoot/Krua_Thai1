<?php
/**
 * Krua Thai - Nutrition Tracking System
 * File: nutrition-tracking.php
 * Features: Track daily nutrition, set goals, view progress, meal analysis
 * Status: PRODUCTION READY ✅
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php?redirect=nutrition-tracking.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = "";

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false, 'errors' => []];

    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        switch ($action) {
            case 'update_goals':
                $target_calories = intval($_POST['target_calories'] ?? 2000);
                $target_protein = floatval($_POST['target_protein'] ?? 150);
                $target_carbs = floatval($_POST['target_carbs'] ?? 200);
                $target_fat = floatval($_POST['target_fat'] ?? 65);
                $target_fiber = floatval($_POST['target_fiber'] ?? 25);
                $target_sodium = floatval($_POST['target_sodium'] ?? 2300);
                
                // Check if user has existing goal
                $stmt = $pdo->prepare("SELECT id FROM nutrition_goals WHERE user_id = ? AND is_active = 1");
                $stmt->execute([$user_id]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Update existing goal
                    $stmt = $pdo->prepare("
                        UPDATE nutrition_goals SET 
                        target_calories = ?, target_protein_g = ?, target_carbs_g = ?, 
                        target_fat_g = ?, target_fiber_g = ?, target_sodium_mg = ?, 
                        updated_at = NOW()
                        WHERE user_id = ? AND is_active = 1
                    ");
                    $stmt->execute([$target_calories, $target_protein, $target_carbs, $target_fat, $target_fiber, $target_sodium, $user_id]);
                } else {
                    // Create new goal
                    $stmt = $pdo->prepare("
                        INSERT INTO nutrition_goals (
                            id, user_id, target_calories, target_protein_g, target_carbs_g, 
                            target_fat_g, target_fiber_g, target_sodium_mg, is_active, created_at, updated_at
                        ) VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmt->execute([$user_id, $target_calories, $target_protein, $target_carbs, $target_fat, $target_fiber, $target_sodium]);
                }
                
                $response['success'] = true;
                $response['message'] = 'เป้าหมายโภชนาการได้รับการอัพเดทแล้ว';
                break;
                
            case 'get_nutrition_data':
                $days = intval($_POST['days'] ?? 7);
                
                // Get nutrition tracking data
                $stmt = $pdo->prepare("
                    SELECT * FROM daily_nutrition_tracking 
                    WHERE user_id = ? 
                    ORDER BY tracking_date DESC 
                    LIMIT ?
                ");
                $stmt->execute([$user_id, $days]);
                $tracking_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get current goals
                $stmt = $pdo->prepare("
                    SELECT * FROM nutrition_goals 
                    WHERE user_id = ? AND is_active = 1 
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute([$user_id]);
                $goals = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['tracking_data'] = $tracking_data;
                $response['goals'] = $goals;
                break;
                
            case 'get_meal_analysis':
                $date = $_POST['date'] ?? date('Y-m-d');
                
                // Get meals from orders for specific date
                $stmt = $pdo->prepare("
                    SELECT o.*, oi.menu_id, oi.quantity, m.name as menu_name, m.name_thai,
                           m.calories_per_serving, m.protein_g, m.carbs_g, m.fat_g, 
                           m.fiber_g, m.sodium_mg, m.sugar_g
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    JOIN menus m ON oi.menu_id = m.id
                    WHERE o.user_id = ? AND DATE(o.delivery_date) = ?
                    ORDER BY o.created_at DESC
                ");
                $stmt->execute([$user_id, $date]);
                $meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['meals'] = $meals;
                $response['date'] = $date;
                break;
        }
    } catch (Exception $e) {
        $response['errors'][] = "Error: " . $e->getMessage();
        error_log("Nutrition tracking error: " . $e->getMessage());
    }
    
    echo json_encode($response);
    exit();
}

// Get user data and initial nutrition data
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get user info
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get current nutrition goals
    $stmt = $pdo->prepare("
        SELECT * FROM nutrition_goals 
        WHERE user_id = ? AND is_active = 1 
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $nutrition_goals = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent tracking data (last 7 days)
    $stmt = $pdo->prepare("
        SELECT * FROM daily_nutrition_tracking 
        WHERE user_id = ? 
        ORDER BY tracking_date DESC 
        LIMIT 7
    ");
    $stmt->execute([$user_id]);
    $tracking_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get today's data specifically
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT * FROM daily_nutrition_tracking 
        WHERE user_id = ? AND tracking_date = ?
    ");
    $stmt->execute([$user_id, $today]);
    $today_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no data for today, create default
    if (!$today_data) {
        $today_data = [
            'total_calories' => 0,
            'total_protein_g' => 0,
            'total_carbs_g' => 0,
            'total_fat_g' => 0,
            'total_fiber_g' => 0,
            'total_sodium_mg' => 0,
            'total_sugar_g' => 0,
            'goal_achievement_percentage' => 0
        ];
    }
    
    // Default goals if none set
    if (!$nutrition_goals) {
        $nutrition_goals = [
            'target_calories' => 2000,
            'target_protein_g' => 150,
            'target_carbs_g' => 200,
            'target_fat_g' => 65,
            'target_fiber_g' => 25,
            'target_sodium_mg' => 2300
        ];
    }
    
} catch (Exception $e) {
    error_log("Nutrition data error: " . $e->getMessage());
    $current_user = [
        'first_name' => $_SESSION['first_name'] ?? 'User', 
        'last_name' => $_SESSION['last_name'] ?? ''
    ];
    $nutrition_goals = [
        'target_calories' => 2000,
        'target_protein_g' => 150,
        'target_carbs_g' => 200,
        'target_fat_g' => 65,
        'target_fiber_g' => 25,
        'target_sodium_mg' => 2300
    ];
    $tracking_data = [];
    $today_data = [
        'total_calories' => 0,
        'total_protein_g' => 0,
        'total_carbs_g' => 0,
        'total_fat_g' => 0,
        'total_fiber_g' => 0,
        'total_sodium_mg' => 0,
        'total_sugar_g' => 0,
        'goal_achievement_percentage' => 0
    ];
}

$page_title = "Nutrition Tracking";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Krua Thai</title>
    
    <!-- BaticaSans Font -->
    <link rel="preconnect" href="https://ydpschool.com">
    <style>
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Bold.woff2') format('woff2');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Medium.woff2') format('woff2');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }

        :root {
            --brown: #bd9379;
            --cream: #ece8e1;
            --sage: #adb89d;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(189, 147, 121, 0.15);
            --shadow-medium: 0 8px 24px rgba(189, 147, 121, 0.25);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--cream);
            font-weight: 400;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            box-shadow: var(--shadow-soft);
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: var(--text-dark);
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--curry);
            font-family: 'BaticaSans', sans-serif;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-gray);
            font-weight: 500;
            transition: var(--transition);
        }

        .nav-links a:hover {
            color: var(--curry);
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-top: 80px;
            min-height: 100vh;
            padding: 2rem;
        }

        .nutrition-header {
            background: linear-gradient(135deg, var(--sage) 0%, var(--curry) 100%);
            color: var(--white);
            padding: 3rem 0;
            margin-bottom: 3rem;
            border-radius: var(--radius-lg);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .nutrition-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--white);
        }

        .nutrition-header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        /* Grid Layout */
        .nutrition-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .nutrition-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .card-subtitle {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-top: 0.25rem;
        }

        /* Progress Bars */
        .progress-container {
            margin-bottom: 1.5rem;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .progress-label {
            font-weight: 500;
            color: var(--text-dark);
        }

        .progress-value {
            font-weight: 600;
            color: var(--curry);
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--cream);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .progress-calories { background: #ff6b6b; }
        .progress-protein { background: #4ecdc4; }
        .progress-carbs { background: #45b7d1; }
        .progress-fat { background: #f9ca24; }
        .progress-fiber { background: #6c5ce7; }
        .progress-sodium { background: #fd79a8; }

        /* Today's Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: var(--white);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-soft);
            border-top: 4px solid var(--curry);
        }

        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--curry);
            margin-bottom: 0.5rem;
        }

        .summary-label {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 0.25rem;
        }

        .summary-progress {
            font-size: 0.8rem;
            color: var(--text-gray);
        }

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
        }

        .data-table th {
            background: var(--curry);
            color: var(--white);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .data-table tr:hover {
            background: var(--cream);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        /* Goals Form */
        .goals-form {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-input {
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-family: inherit;
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        /* Chart Container */
        .chart-container {
            height: 300px;
            margin: 2rem 0;
            padding: 1rem;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }

        /* Loading */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 3rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid var(--border-light);
            border-top: 4px solid var(--curry);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Toast Messages */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-medium);
            z-index: 2000;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .toast.error {
            background: var(--danger);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .nutrition-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .nutrition-header {
                margin-bottom: 2rem;
                padding: 2rem 0;
            }
            
            .nutrition-header h1 {
                font-size: 2rem;
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-links {
                display: none;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-gray);
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <a href="index.php" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Krua Thai" style="height: 40px; width: auto;" onerror="this.style.display='none';">
                <span class="logo-text">Krua Thai</span>
            </a>
            
            <ul class="nav-links">
                <li><a href="./menus.php">Menu</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="notifications.php">Notifications</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            
            <div class="nav-actions">
                <a href="logout.php" class="btn btn-secondary">Sign Out</a>
                <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <!-- Nutrition Header -->
        <div class="nutrition-header">
            <div class="container">
                <h1>🍽️ ติดตามโภชนาการ</h1>
                <p>ติดตามและวิเคราะห์โภชนาการจากอาหารไทยเพื่อสุขภาพของคุณ</p>
            </div>
        </div>

        <div class="container">
            <!-- Today's Summary -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-value" id="todayCalories"><?= number_format($today_data['total_calories']) ?></div>
                    <div class="summary-label">แคลอรี่วันนี้</div>
                    <div class="summary-progress">
                        <?= $nutrition_goals['target_calories'] ? round(($today_data['total_calories'] / $nutrition_goals['target_calories']) * 100) : 0 ?>% จากเป้าหมาย
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-value" id="todayProtein"><?= number_format($today_data['total_protein_g'], 1) ?>g</div>
                    <div class="summary-label">โปรตีน</div>
                    <div class="summary-progress">
                        <?= $nutrition_goals['target_protein_g'] ? round(($today_data['total_protein_g'] / $nutrition_goals['target_protein_g']) * 100) : 0 ?>% จากเป้าหมาย
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-value" id="todayCarbs"><?= number_format($today_data['total_carbs_g'], 1) ?>g</div>
                    <div class="summary-label">คาร์โบไฮเดรต</div>
                    <div class="summary-progress">
                        <?= $nutrition_goals['target_carbs_g'] ? round(($today_data['total_carbs_g'] / $nutrition_goals['target_carbs_g']) * 100) : 0 ?>% จากเป้าหมาย
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-value" id="todayFat"><?= number_format($today_data['total_fat_g'], 1) ?>g</div>
                    <div class="summary-label">ไขมัน</div>
                    <div class="summary-progress">
                        <?= $nutrition_goals['target_fat_g'] ? round(($today_data['total_fat_g'] / $nutrition_goals['target_fat_g']) * 100) : 0 ?>% จากเป้าหมาย
                    </div>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="nutrition-grid">
                <!-- Today's Progress -->
                <div class="nutrition-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">ความคืบหน้าวันนี้</h3>
                            <p class="card-subtitle"><?= date('d/m/Y') ?></p>
                        </div>
                        <button class="btn btn-sm btn-secondary" onclick="refreshTodayData()">🔄 รีเฟรช</button>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-header">
                            <span class="progress-label">แคลอรี่</span>
                            <span class="progress-value">
                                <?= number_format($today_data['total_calories']) ?> / <?= number_format($nutrition_goals['target_calories']) ?> kcal
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill progress-calories" 
                                 style="width: <?= min(100, ($today_data['total_calories'] / $nutrition_goals['target_calories']) * 100) ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-header">
                            <span class="progress-label">โปรตีน</span>
                            <span class="progress-value">
                                <?= number_format($today_data['total_protein_g'], 1) ?> / <?= number_format($nutrition_goals['target_protein_g'], 1) ?> g
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill progress-protein" 
                                 style="width: <?= min(100, ($today_data['total_protein_g'] / $nutrition_goals['target_protein_g']) * 100) ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-header">
                            <span class="progress-label">คาร์โบไฮเดรต</span>
                            <span class="progress-value">
                                <?= number_format($today_data['total_carbs_g'], 1) ?> / <?= number_format($nutrition_goals['target_carbs_g'], 1) ?> g
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill progress-carbs" 
                                 style="width: <?= min(100, ($today_data['total_carbs_g'] / $nutrition_goals['target_carbs_g']) * 100) ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-header">
                            <span class="progress-label">ไขมัน</span>
                            <span class="progress-value">
                                <?= number_format($today_data['total_fat_g'], 1) ?> / <?= number_format($nutrition_goals['target_fat_g'], 1) ?> g
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill progress-fat" 
                                 style="width: <?= min(100, ($today_data['total_fat_g'] / $nutrition_goals['target_fat_g']) * 100) ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-header">
                            <span class="progress-label">ไฟเบอร์</span>
                            <span class="progress-value">
                                <?= number_format($today_data['total_fiber_g'], 1) ?> / <?= number_format($nutrition_goals['target_fiber_g'], 1) ?> g
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill progress-fiber" 
                                 style="width: <?= min(100, ($today_data['total_fiber_g'] / $nutrition_goals['target_fiber_g']) * 100) ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="progress-container">
                        <div class="progress-header">
                            <span class="progress-label">โซเดียม</span>
                            <span class="progress-value">
                                <?= number_format($today_data['total_sodium_mg']) ?> / <?= number_format($nutrition_goals['target_sodium_mg']) ?> mg
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill progress-sodium" 
                                 style="width: <?= min(100, ($today_data['total_sodium_mg'] / $nutrition_goals['target_sodium_mg']) * 100) ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- Goals Setting -->
                <div class="nutrition-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title">เป้าหมายโภชนาการ</h3>
                            <p class="card-subtitle">ตั้งค่าเป้าหมายรายวันของคุณ</p>
                        </div>
                    </div>
                    
                    <form id="goalsForm" class="goals-form" style="padding: 0;">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">แคลอรี่ (kcal)</label>
                                <input type="number" id="targetCalories" class="form-input" 
                                       value="<?= $nutrition_goals['target_calories'] ?>" min="1200" max="5000">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">โปรตีน (g)</label>
                                <input type="number" id="targetProtein" class="form-input" 
                                       value="<?= $nutrition_goals['target_protein_g'] ?>" min="50" max="300" step="0.1">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">คาร์โบไฮเดรต (g)</label>
                                <input type="number" id="targetCarbs" class="form-input" 
                                       value="<?= $nutrition_goals['target_carbs_g'] ?>" min="100" max="500" step="0.1">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">ไขมัน (g)</label>
                                <input type="number" id="targetFat" class="form-input" 
                                       value="<?= $nutrition_goals['target_fat_g'] ?>" min="30" max="200" step="0.1">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">ไฟเบอร์ (g)</label>
                                <input type="number" id="targetFiber" class="form-input" 
                                       value="<?= $nutrition_goals['target_fiber_g'] ?>" min="15" max="50" step="0.1">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">โซเดียม (mg)</label>
                                <input type="number" id="targetSodium" class="form-input" 
                                       value="<?= $nutrition_goals['target_sodium_mg'] ?>" min="1000" max="4000">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">💾 บันทึกเป้าหมาย</button>
                    </form>
                </div>
            </div>

            <!-- Weekly History -->
            <div class="nutrition-card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">ประวัติ 7 วันที่ผ่านมา</h3>
                        <p class="card-subtitle">ติดตามความคืบหน้าของคุณ</p>
                    </div>
                    <button class="btn btn-sm btn-secondary" onclick="loadNutritionHistory()">📊 รีเฟรช</button>
                </div>
                
                <div id="historyTable">
                    <?php if (!empty($tracking_data)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>วันที่</th>
                                <th>แคลอรี่</th>
                                <th>โปรตีน (g)</th>
                                <th>คาร์บ (g)</th>
                                <th>ไขมัน (g)</th>
                                <th>ไฟเบอร์ (g)</th>
                                <th>โซเดียม (mg)</th>
                                <th>% เป้าหมาย</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tracking_data as $day): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($day['tracking_date'])) ?></td>
                                <td><?= number_format($day['total_calories']) ?></td>
                                <td><?= number_format($day['total_protein_g'], 1) ?></td>
                                <td><?= number_format($day['total_carbs_g'], 1) ?></td>
                                <td><?= number_format($day['total_fat_g'], 1) ?></td>
                                <td><?= number_format($day['total_fiber_g'], 1) ?></td>
                                <td><?= number_format($day['total_sodium_mg']) ?></td>
                                <td>
                                    <span style="color: <?= $day['goal_achievement_percentage'] >= 80 ? 'var(--success)' : ($day['goal_achievement_percentage'] >= 50 ? 'var(--warning)' : 'var(--danger)') ?>">
                                        <?= number_format($day['goal_achievement_percentage']) ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <h3>ยังไม่มีข้อมูลประวัติ</h3>
                        <p>เริ่มสั่งอาหารและติดตามโภชนาการของคุณวันนี้!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Meal Analysis -->
            <div class="nutrition-card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">วิเคราะห์มื้ออาหารวันนี้</h3>
                        <p class="card-subtitle">ดูรายละเอียดโภชนาการแต่ละมื้อ</p>
                    </div>
                    <button class="btn btn-sm btn-secondary" onclick="loadMealAnalysis()">🔍 วิเคราะห์</button>
                </div>
                
                <div id="mealAnalysis">
                    <div class="loading">
                        <div class="spinner"></div>
                    </div>
                </div>
            </div>

            <!-- Quick Tips -->
            <div class="nutrition-card">
                <div class="card-header">
                    <h3 class="card-title">💡 เคล็ดลับโภชนาการ</h3>
                </div>
                
                <div style="display: grid; gap: 1rem;">
                    <div style="padding: 1rem; background: var(--cream); border-radius: var(--radius-sm); border-left: 4px solid var(--curry);">
                        <strong>เป้าหมายแคลอรี่:</strong> สำหรับคนทั่วไป ผู้หญิง 1,800-2,000 kcal/วัน ผู้ชาย 2,200-2,500 kcal/วัน
                    </div>
                    <div style="padding: 1rem; background: var(--cream); border-radius: var(--radius-sm); border-left: 4px solid var(--sage);">
                        <strong>โปรตีน:</strong> ควรได้รับ 0.8-1.2 กรัมต่อน้ำหนักตัว 1 กิโลกรัม
                    </div>
                    <div style="padding: 1rem; background: var(--cream); border-radius: var(--radius-sm); border-left: 4px solid var(--brown);">
                        <strong>ไฟเบอร์:</strong> ควรได้รับอย่างน้อย 25-35 กรัมต่อวัน จากผักและผลไม้
                    </div>
                    <div style="padding: 1rem; background: var(--cream); border-radius: var(--radius-sm); border-left: 4px solid var(--curry);">
                        <strong>โซเดียม:</strong> ไม่ควรเกิน 2,300 มิลลิกรัมต่อวัน เพื่อป้องกันความดันโลหิตสูง
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <script>
        let isLoading = false;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🍽️ Krua Thai - Nutrition Tracking page initializing...');
            
            // Set up form submission
            setupGoalsForm();
            
            // Load initial meal analysis
            loadMealAnalysis();
            
            // Auto-refresh every 5 minutes
            setInterval(refreshTodayData, 300000);
        });

        // Setup goals form submission
        function setupGoalsForm() {
            const form = document.getElementById('goalsForm');
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                if (isLoading) return;
                isLoading = true;
                
                const formData = new FormData();
                formData.append('action', 'update_goals');
                formData.append('target_calories', document.getElementById('targetCalories').value);
                formData.append('target_protein', document.getElementById('targetProtein').value);
                formData.append('target_carbs', document.getElementById('targetCarbs').value);
                formData.append('target_fat', document.getElementById('targetFat').value);
                formData.append('target_fiber', document.getElementById('targetFiber').value);
                formData.append('target_sodium', document.getElementById('targetSodium').value);
                
                try {
                    const response = await fetch('nutrition-tracking.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showSuccess(data.message || 'เป้าหมายได้รับการอัพเดทแล้ว');
                        // Refresh the page to show updated progress
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showError(data.errors ? data.errors.join(', ') : 'ไม่สามารถอัพเดทเป้าหมายได้');
                    }
                } catch (error) {
                    showError('เกิดข้อผิดพลาดเครือข่าย');
                } finally {
                    isLoading = false;
                }
            });
        }

        // Refresh today's data
        async function refreshTodayData() {
            if (isLoading) return;
            isLoading = true;
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_nutrition_data');
                formData.append('days', '1');
                
                const response = await fetch('nutrition-tracking.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success && data.tracking_data.length > 0) {
                    const today = data.tracking_data[0];
                    const goals = data.goals;
                    
                    // Update summary cards
                    document.getElementById('todayCalories').textContent = Number(today.total_calories).toLocaleString();
                    document.getElementById('todayProtein').textContent = Number(today.total_protein_g).toFixed(1) + 'g';
                    document.getElementById('todayCarbs').textContent = Number(today.total_carbs_g).toFixed(1) + 'g';
                    document.getElementById('todayFat').textContent = Number(today.total_fat_g).toFixed(1) + 'g';
                    
                    // Update progress bars
                    updateProgressBars(today, goals);
                    
                    showSuccess('ข้อมูลได้รับการรีเฟรชแล้ว');
                } else {
                    showError('ไม่สามารถรีเฟรชข้อมูลได้');
                }
            } catch (error) {
                showError('เกิดข้อผิดพลาดเครือข่าย');
            } finally {
                isLoading = false;
            }
        }

        // Update progress bars
        function updateProgressBars(todayData, goals) {
            if (!goals) return;
            
            const progressBars = document.querySelectorAll('.progress-fill');
            const progressValues = document.querySelectorAll('.progress-value');
            
            // Calculate percentages
            const percentages = [
                Math.min(100, (todayData.total_calories / goals.target_calories) * 100),
                Math.min(100, (todayData.total_protein_g / goals.target_protein_g) * 100),
                Math.min(100, (todayData.total_carbs_g / goals.target_carbs_g) * 100),
                Math.min(100, (todayData.total_fat_g / goals.target_fat_g) * 100),
                Math.min(100, (todayData.total_fiber_g / goals.target_fiber_g) * 100),
                Math.min(100, (todayData.total_sodium_mg / goals.target_sodium_mg) * 100)
            ];
            
            // Update progress bars
            progressBars.forEach((bar, index) => {
                bar.style.width = percentages[index] + '%';
            });
        }

        // Load nutrition history
        async function loadNutritionHistory() {
            const historyContainer = document.getElementById('historyTable');
            historyContainer.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_nutrition_data');
                formData.append('days', '7');
                
                const response = await fetch('nutrition-tracking.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    displayHistoryTable(data.tracking_data);
                    showSuccess('ประวัติได้รับการรีเฟรชแล้ว');
                } else {
                    historyContainer.innerHTML = '<div class="empty-state"><div class="empty-icon">❌</div><h3>ไม่สามารถโหลดประวัติได้</h3></div>';
                }
            } catch (error) {
                historyContainer.innerHTML = '<div class="empty-state"><div class="empty-icon">🔌</div><h3>เกิดข้อผิดพลาดเครือข่าย</h3></div>';
            }
        }

        // Display history table
        function displayHistoryTable(trackingData) {
            const historyContainer = document.getElementById('historyTable');
            
            if (!trackingData || trackingData.length === 0) {
                historyContainer.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <h3>ยังไม่มีข้อมูลประวัติ</h3>
                        <p>เริ่มสั่งอาหารและติดตามโภชนาการของคุณวันนี้!</p>
                    </div>
                `;
                return;
            }
            
            let tableHTML = `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>แคลอรี่</th>
                            <th>โปรตีน (g)</th>
                            <th>คาร์บ (g)</th>
                            <th>ไขมัน (g)</th>
                            <th>ไฟเบอร์ (g)</th>
                            <th>โซเดียม (mg)</th>
                            <th>% เป้าหมาย</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            trackingData.forEach(day => {
                const date = new Date(day.tracking_date);
                const achievement = Number(day.goal_achievement_percentage || 0);
                const achievementColor = achievement >= 80 ? 'var(--success)' : 
                                       achievement >= 50 ? 'var(--warning)' : 'var(--danger)';
                
                tableHTML += `
                    <tr>
                        <td>${date.toLocaleDateString('th-TH')}</td>
                        <td>${Number(day.total_calories).toLocaleString()}</td>
                        <td>${Number(day.total_protein_g).toFixed(1)}</td>
                        <td>${Number(day.total_carbs_g).toFixed(1)}</td>
                        <td>${Number(day.total_fat_g).toFixed(1)}</td>
                        <td>${Number(day.total_fiber_g).toFixed(1)}</td>
                        <td>${Number(day.total_sodium_mg).toLocaleString()}</td>
                        <td>
                            <span style="color: ${achievementColor}">
                                ${achievement.toFixed(0)}%
                            </span>
                        </td>
                    </tr>
                `;
            });
            
            tableHTML += '</tbody></table>';
            historyContainer.innerHTML = tableHTML;
        }

        // Load meal analysis
        async function loadMealAnalysis() {
            const analysisContainer = document.getElementById('mealAnalysis');
            analysisContainer.innerHTML = '<div class="loading"><div class="spinner"></div></div>';
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_meal_analysis');
                formData.append('date', new Date().toISOString().split('T')[0]);
                
                const response = await fetch('nutrition-tracking.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    displayMealAnalysis(data.meals);
                } else {
                    analysisContainer.innerHTML = '<div class="empty-state"><div class="empty-icon">🍽️</div><h3>ยังไม่มีมื้ออาหารวันนี้</h3><p>สั่งอาหารเพื่อดูการวิเคราะห์โภชนาการ</p></div>';
                }
            } catch (error) {
                analysisContainer.innerHTML = '<div class="empty-state"><div class="empty-icon">❌</div><h3>ไม่สามารถโหลดข้อมูลได้</h3></div>';
            }
        }

        // Display meal analysis
        function displayMealAnalysis(meals) {
            const analysisContainer = document.getElementById('mealAnalysis');
            
            if (!meals || meals.length === 0) {
                analysisContainer.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">🍽️</div>
                        <h3>ยังไม่มีมื้ออาหารวันนี้</h3>
                        <p>สั่งอาหารเพื่อดูการวิเคราะห์โภชนาการ</p>
                        <a href="menus.php" class="btn btn-primary" style="margin-top: 1rem;">🍜 ดูเมนูอาหาร</a>
                    </div>
                `;
                return;
            }
            
            let analysisHTML = '<div style="display: grid; gap: 1rem;">';
            
            meals.forEach(meal => {
                const calories = Number(meal.calories_per_serving) * Number(meal.quantity);
                const protein = Number(meal.protein_g) * Number(meal.quantity);
                const carbs = Number(meal.carbs_g) * Number(meal.quantity);
                const fat = Number(meal.fat_g) * Number(meal.quantity);
                
                analysisHTML += `
                    <div style="padding: 1rem; background: var(--cream); border-radius: var(--radius-md); border-left: 4px solid var(--curry);">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                            <div>
                                <h4 style="color: var(--curry); margin-bottom: 0.25rem;">${escapeHtml(meal.name_thai || meal.menu_name)}</h4>
                                <p style="color: var(--text-gray); font-size: 0.9rem;">จำนวน: ${meal.quantity} หน่วย</p>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 600; color: var(--curry);">${calories.toFixed(0)} kcal</div>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; font-size: 0.85rem;">
                            <div style="text-align: center;">
                                <div style="font-weight: 600;">${protein.toFixed(1)}g</div>
                                <div style="color: var(--text-gray);">โปรตีน</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-weight: 600;">${carbs.toFixed(1)}g</div>
                                <div style="color: var(--text-gray);">คาร์บ</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-weight: 600;">${fat.toFixed(1)}g</div>
                                <div style="color: var(--text-gray);">ไขมัน</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            analysisHTML += '</div>';
            analysisContainer.innerHTML = analysisHTML;
        }

        // Utility functions
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Show success message
        function showSuccess(message) {
            showToast(message, 'success');
        }

        // Show error message  
        function showError(message) {
            showToast(message, 'error');
            console.error('Error:', message);
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            
            document.getElementById('toastContainer').appendChild(toast);
            
            // Show toast
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            // Hide and remove toast
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.remove();
                    }
                }, 300);
            }, 4000);
        }

        console.log('🍽️ Krua Thai - Nutrition Tracking page loaded successfully!');
    </script>

    <!-- Debug Information (ลบออกใน production) -->
    <?php if (isset($_GET['debug'])): ?>
    <div style="position: fixed; bottom: 10px; left: 10px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 5px; font-size: 12px; z-index: 1000; max-width: 300px;">
        <strong>🔧 Debug Info:</strong><br>
        User ID: <?= htmlspecialchars($user_id) ?><br>
        Today Calories: <?= $today_data['total_calories'] ?><br>
        Goal Calories: <?= $nutrition_goals['target_calories'] ?><br>
        Tracking Records: <?= count($tracking_data) ?><br>
        Version: Nutrition Tracking v1.0 ✅
    </div>
    <?php endif; ?>
</body>
</html>