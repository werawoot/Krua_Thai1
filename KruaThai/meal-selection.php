<?php
/**
 * Krua Thai - Meal Selection Page
 * File: meal-selection.php
 * Description: Allows users to select meals for their subscription plan
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
        $pdo = new PDO("mysql:host=localhost;dbname=krua_thai;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        try {
            $pdo = new PDO("mysql:host=localhost:8889;dbname=krua_thai;charset=utf8mb4", "root", "root");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
}

require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $plan = $_GET['plan'] ?? '';
    $redirect_url = 'meal-selection.php' . ($plan ? '?plan=' . urlencode($plan) : '');
    header('Location: login.php?redirect=' . urlencode($redirect_url));
    exit;
}

// Get plan information
$plan_id = $_GET['plan'] ?? '';
if (empty($plan_id)) {
    $_SESSION['error_message'] = 'กรุณาเลือกแพ็คเกจก่อนเลือกเมนู';
    header('Location: index.php#plans');
    exit;
}

try {
    // Get plan details from database
    $stmt = $pdo->prepare("
        SELECT * FROM subscription_plans 
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Handle case when plan not found in database - use default plans based on current DB
    if (!$plan) {
        // Map to actual plans in database
        $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY meals_per_week ASC");
        $stmt->execute();
        $available_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $default_mapping = [
            'basic' => 4,
            'popular' => 8, 
            'family' => 12,
            'premium' => 15
        ];
        
        if (isset($default_mapping[$plan_id])) {
            $target_meals = $default_mapping[$plan_id];
            foreach ($available_plans as $available_plan) {
                if ($available_plan['meals_per_week'] == $target_meals) {
                    $plan = $available_plan;
                    break;
                }
            }
        }
        
        if (!$plan && !empty($available_plans)) {
            $plan = $available_plans[0]; // Use first available plan
        }
        
        if (!$plan) {
            $_SESSION['error_message'] = 'ไม่พบแพ็คเกจที่เลือก';
            header('Location: index.php#plans');
            exit;
        }
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
    error_log("Meal Selection Error: " . $e->getMessage());
    $_SESSION['error_message'] = 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';
    header('Location: index.php');
    exit;
}

// Handle meal selection submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'proceed_to_checkout') {
    header('Content-Type: application/json');
    
    $selected_meals = json_decode($_POST['selected_meals'] ?? '[]', true);
    
    // Enhanced validation
    if (!is_array($selected_meals)) {
        echo json_encode([
            'success' => false,
            'message' => 'ข้อมูลเมนูไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง'
        ]);
        exit;
    }
    
    if (count($selected_meals) !== (int)$plan['meals_per_week']) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณาเลือกเมนูให้ครบ ' . $plan['meals_per_week'] . ' มื้อ (เลือกแล้ว ' . count($selected_meals) . ' มื้อ)'
        ]);
        exit;
    }
    
    // Verify meals exist in database
    $placeholders = implode(',', array_fill(0, count($selected_meals), '?'));
    try {
        $stmt = $pdo->prepare("SELECT id, name, name_thai, base_price FROM menus WHERE id IN ($placeholders) AND is_available = 1");
        $stmt->execute($selected_meals);
        $verified_meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($verified_meals) !== count($selected_meals)) {
            echo json_encode([
                'success' => false,
                'message' => 'บางเมนูที่เลือกไม่พร้อมให้บริการ กรุณาเลือกใหม่'
            ]);
            exit;
        }
        
        // Store meal details for checkout
        $meal_details = [];
        foreach ($verified_meals as $meal) {
            $meal_details[$meal['id']] = [
                'name' => $meal['name'],
                'name_thai' => $meal['name_thai'],
                'base_price' => $meal['base_price']
            ];
        }
        
    } catch (Exception $e) {
        error_log("Meal verification error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดในการตรวจสอบเมนู'
        ]);
        exit;
    }
    
    // Store comprehensive data in session for checkout
    $_SESSION['checkout_data'] = [
        'plan' => $plan,
        'selected_meals' => $selected_meals,
        'meal_details' => $meal_details,
        'total_meals' => count($selected_meals),
        'user_id' => $_SESSION['user_id'],
        'created_at' => time(),
        'source' => 'meal-selection'
    ];
    
    echo json_encode([
        'success' => true,
        'redirect' => 'checkout.php',
        'message' => 'กำลังนำคุณไปยังหน้าชำระเงิน...'
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกเมนูอาหาร - <?php echo htmlspecialchars($plan['name_thai'] ?? $plan['name']); ?> | Krua Thai</title>
    <meta name="description" content="เลือกเมนูอาหารไทยเพื่อสุขภาพสำหรับแพ็คเกจของคุณ">
    
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --cream: #ece8e1;
            --sage: #adb89d;
            --brown: #bd9379;
            --curry: #cf723a;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #e8e8e8;
            --shadow-soft: 0 4px 12px rgba(0,0,0,0.05);
            --shadow-medium: 0 8px 24px rgba(0,0,0,0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--white);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        header {
            background: var(--white);
            box-shadow: var(--shadow-soft);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--curry);
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
        }

        .logo-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--curry), var(--brown));
            border-radius: 50%;
            color: var(--white);
            font-size: 1.5rem;
        }

        .nav-links {
            display: flex;
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

        /* Main Content */
        .main-content {
            padding: 2rem 0;
            min-height: calc(100vh - 200px);
        }

        /* Progress Bar */
        .progress-bar {
            background: var(--cream);
            border-radius: 50px;
            padding: 1.5rem 2rem;
            margin-bottom: 3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }

        .progress-step {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            position: relative;
            z-index: 2;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--white);
            color: var(--text-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            border: 3px solid var(--border-light);
            transition: var(--transition);
        }

        .step-circle.active {
            background: var(--curry);
            color: var(--white);
            border-color: var(--curry);
        }

        .step-circle.completed {
            background: var(--success);
            color: var(--white);
            border-color: var(--success);
        }

        .step-text {
            font-weight: 600;
            color: var(--text-dark);
        }

        .progress-line {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--border-light);
            transform: translateY(-50%);
            z-index: 1;
        }

        /* Plan Summary */
        .plan-summary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 3rem;
            text-align: center;
        }

        .plan-summary h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 800;
        }

        .plan-summary .subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .plan-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .plan-info-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            backdrop-filter: blur(10px);
        }

        .plan-info-value {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .plan-info-label {
            font-size: 1rem;
            opacity: 0.9;
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
            top: 100px;
            z-index: 50;
        }

        .counter-text {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .counter-number {
            color: var(--curry);
            font-size: 1.5rem;
            font-weight: 800;
        }

        .counter-complete {
            color: var(--success);
        }

        /* Filters */
        .filters-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }

        .filters-header {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid var(--border-light);
            border-radius: 50px;
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 15px rgba(207, 114, 58, 0.2);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-gray);
            font-size: 1.1rem;
        }

        .filter-categories {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .category-filter {
            padding: 0.6rem 1.2rem;
            border: 2px solid var(--border-light);
            border-radius: 25px;
            background: var(--white);
            color: var(--text-gray);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .category-filter:hover,
        .category-filter.active {
            border-color: var(--curry);
            background: var(--curry);
            color: var(--white);
        }

        /* Menu Grid */
        .meals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .meal-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            border: 2px solid var(--border-light);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }

        .meal-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .meal-card.selected {
            border-color: var(--curry);
            box-shadow: 0 0 20px rgba(207, 114, 58, 0.3);
        }

        .meal-image {
            position: relative;
            height: 200px;
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
            top: 1rem;
            left: 1rem;
            background: rgba(255, 255, 255, 0.95);
            color: var(--curry);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .selection-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--curry);
            color: var(--white);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            opacity: 0;
            transform: scale(0.5);
            transition: var(--transition);
        }

        .meal-card.selected .selection-badge {
            opacity: 1;
            transform: scale(1);
        }

        .meal-content {
            padding: 1.5rem;
        }

        .meal-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.8rem;
            line-height: 1.3;
        }

        .meal-description {
            color: var(--text-gray);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1.2rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2; /* Standard property for modern browsers */
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .meal-nutrition {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .nutrition-item {
            text-align: center;
            flex: 1;
            min-width: 60px;
        }

        .nutrition-value {
            font-weight: 700;
            color: var(--curry);
            font-size: 0.9rem;
        }

        .nutrition-label {
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-top: 0.2rem;
        }

        .meal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border-light);
        }

        .meal-price {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--curry);
        }

        .add-meal-btn {
            background: var(--curry);
            color: var(--white);
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .add-meal-btn.selected {
            background: var(--success);
        }

        /* Continue Button */
        .continue-section {
            position: sticky;
            bottom: 0;
            background: var(--white);
            border-top: 2px solid var(--border-light);
            padding: 2rem 0;
            margin-top: 3rem;
        }

        .continue-btn {
            width: 100%;
            background: var(--curry);
            color: var(--white);
            border: none;
            padding: 1.5rem 2rem;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
        }

        .continue-btn:hover:not(:disabled) {
            background: var(--brown);
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }

        .continue-btn:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        /* Loading States */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
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

        /* Dietary tags */
        .dietary-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 1rem;
        }

        .dietary-tag {
            padding: 0.3rem 0.6rem;
            background: var(--cream);
            color: var(--brown);
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .dietary-tag.vegetarian {
            background: #d4edda;
            color: #155724;
        }

        .dietary-tag.vegan {
            background: #d1ecf1;
            color: #0c5460;
        }

        .dietary-tag.gluten-free {
            background: #fff3cd;
            color: #856404;
        }

        /* Animation for meal selection */
        @keyframes selectPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .meal-card.just-selected {
            animation: selectPulse 0.4s ease-out;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }

            .plan-summary h1 {
                font-size: 2rem;
            }

            .plan-info {
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }

            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: 100%;
            }

            .meals-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .meal-nutrition {
                gap: 0.5rem;
            }

            .nutrition-item {
                min-width: 50px;
            }

            .progress-bar {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .progress-line {
                display: none;
            }

            .selection-counter {
                position: static;
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .meal-content {
                padding: 1rem;
            }

            .meal-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .add-meal-btn {
                justify-content: center;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <nav class="container">
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <span>Krua Thai</span>
            </a>
            
            <div class="nav-links">
                <a href="index.php">หน้าหลัก</a>
                <a href="dashboard.php">บัญชีของฉัน</a>
                <a href="logout.php">ออกจากระบบ</a>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Progress Bar -->
            <div class="progress-bar">
                <div class="progress-line"></div>
                <div class="progress-step">
                    <div class="step-circle">4</div>
                    <div class="step-text">เสร็จสิ้น</div>
                </div>
            </div>

            <!-- Plan Summary -->
            <div class="plan-summary">
                <h1><?php echo htmlspecialchars($plan['name_thai'] ?? $plan['name']); ?></h1>
                <div class="subtitle"><?php echo htmlspecialchars($plan['description'] ?? 'เลือกเมนูอาหารที่คุณชื่นชอบ'); ?></div>
                
                <div class="plan-info">
                    <div class="plan-info-item">
                        <div class="plan-info-value"><?php echo $plan['meals_per_week']; ?></div>
                        <div class="plan-info-label">มื้อต่อสัปดาห์</div>
                    </div>
                    <div class="plan-info-item">
                        <div class="plan-info-value">฿<?php echo number_format($plan['final_price'], 0); ?></div>
                        <div class="plan-info-label">ราคารวม/สัปดาห์</div>
                    </div>
                    <div class="plan-info-item">
                        <div class="plan-info-value">฿<?php echo number_format($plan['final_price'] / $plan['meals_per_week'], 0); ?></div>
                        <div class="plan-info-label">ราคาต่อมื้อ</div>
                    </div>
                </div>
            </div>

            <!-- Selection Counter -->
            <div class="selection-counter">
                <div class="counter-text">
                    เลือกแล้ว: <span class="counter-number" id="selectedCount">0</span>/<span class="counter-number"><?php echo $plan['meals_per_week']; ?></span> มื้อ
                </div>
                <div id="selectionStatus" class="counter-text" style="color: var(--text-gray);">
                    กรุณาเลือกเมนูให้ครบตามจำนวน
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <div class="filters-header">
                    <i class="fas fa-filter"></i> ค้นหาและกรองเมนู
                </div>
                <div class="filters-row">
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" class="search-input" placeholder="ค้นหาเมนูที่ต้องการ...">
                    </div>
                    
                    <div class="filter-categories">
                        <button class="category-filter active" data-category="all">
                            ทั้งหมด
                        </button>
                        <?php foreach ($categories as $category): ?>
                            <button class="category-filter" data-category="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name_thai'] ?: $category['name']); ?>
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
                             data-category="<?php echo $menu['category_id']; ?>"
                             data-name="<?php echo strtolower($menu['name'] . ' ' . ($menu['name_thai'] ?? '')); ?>"
                             data-price="<?php echo $menu['base_price']; ?>">
                            
                            <div class="meal-image">
                                <?php if ($menu['main_image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($menu['main_image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($menu['name']); ?>" 
                                         loading="lazy">
                                <?php else: ?>
                                    <div style="text-align: center;">
                                        <i class="fas fa-utensils" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                        <br><?php echo htmlspecialchars($menu['name_thai'] ?: $menu['name']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($menu['category_name']): ?>
                                    <div class="meal-badge">
                                        <?php echo htmlspecialchars($menu['category_name_thai'] ?: $menu['category_name']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="selection-badge">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                            
                            <div class="meal-content">
                                <h3 class="meal-title">
                                    <?php echo htmlspecialchars($menu['name_thai'] ?: $menu['name']); ?>
                                </h3>
                                
                                <?php if ($menu['description']): ?>
                                    <p class="meal-description">
                                        <?php echo htmlspecialchars($menu['description']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <!-- Dietary Tags -->
                                <?php if ($menu['dietary_tags']): ?>
                                    <div class="dietary-tags">
                                        <?php 
                                        $tags = json_decode($menu['dietary_tags'], true);
                                        if (is_array($tags)):
                                            foreach (array_slice($tags, 0, 3) as $tag): 
                                        ?>
                                            <span class="dietary-tag <?php echo strtolower(str_replace(' ', '-', $tag)); ?>">
                                                <?php echo htmlspecialchars($tag); ?>
                                            </span>
                                        <?php 
                                            endforeach;
                                            if (count($tags) > 3):
                                        ?>
                                            <span class="dietary-tag">+<?php echo count($tags) - 3; ?></span>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Nutrition Info -->
                                <div class="meal-nutrition">
                                    <?php if ($menu['calories_per_serving']): ?>
                                        <div class="nutrition-item">
                                            <div class="nutrition-value"><?php echo $menu['calories_per_serving']; ?></div>
                                            <div class="nutrition-label">แคลอรี่</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($menu['protein_g']): ?>
                                        <div class="nutrition-item">
                                            <div class="nutrition-value"><?php echo $menu['protein_g']; ?>g</div>
                                            <div class="nutrition-label">โปรตีน</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($menu['carbs_g']): ?>
                                        <div class="nutrition-item">
                                            <div class="nutrition-value"><?php echo $menu['carbs_g']; ?>g</div>
                                            <div class="nutrition-label">คาร์บ</div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($menu['preparation_time']): ?>
                                        <div class="nutrition-item">
                                            <div class="nutrition-value"><?php echo $menu['preparation_time']; ?></div>
                                            <div class="nutrition-label">นาที</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="meal-footer">
                                    <div class="meal-price">
                                        ฿<?php echo number_format($menu['base_price'], 0); ?>
                                    </div>
                                    <button class="add-meal-btn" onclick="toggleMealSelection('<?php echo $menu['id']; ?>')">
                                        <i class="fas fa-plus"></i>
                                        <span>เพิ่ม</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <i class="fas fa-utensils"></i>
                        <h3>ไม่พบเมนูอาหาร</h3>
                        <p>ขออภัย ขณะนี้ยังไม่มีเมนูอาหารให้เลือก</p>
                        <a href="index.php" class="btn btn-primary" style="margin-top: 1rem;">กลับหน้าหลัก</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Continue Section -->
            <div class="continue-section">
                <div class="container">
                    <button class="continue-btn" id="continueBtn" onclick="proceedToCheckout()" disabled>
                        <i class="fas fa-shopping-cart"></i>
                        ดำเนินการต่อ - ชำระเงิน
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Global variables
        const maxMeals = <?php echo $plan['meals_per_week']; ?>;
        let selectedMeals = [];
        let allMeals = [];

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
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
        });

        function setupEventListeners() {
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', debounce(filterMeals, 300));

            // Category filters
            document.querySelectorAll('.category-filter').forEach(filter => {
                filter.addEventListener('click', function() {
                    // Update active filter
                    document.querySelectorAll('.category-filter').forEach(f => f.classList.remove('active'));
                    this.classList.add('active');
                    filterMeals();
                });
            });
        }

        function toggleMealSelection(mealId) {
            const mealCard = document.querySelector(`[data-menu-id="${mealId}"]`);
            const button = mealCard.querySelector('.add-meal-btn');
            
            if (selectedMeals.includes(mealId)) {
                // Remove meal
                selectedMeals = selectedMeals.filter(id => id !== mealId);
                mealCard.classList.remove('selected');
                button.innerHTML = '<i class="fas fa-plus"></i><span>เพิ่ม</span>';
                button.classList.remove('selected');
                
                // Enable all disabled buttons if we're under the limit
                if (selectedMeals.length < maxMeals) {
                    document.querySelectorAll('.add-meal-btn:disabled').forEach(btn => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-plus"></i><span>เพิ่ม</span>';
                    });
                }
            } else {
                // Check if we can add more meals
                if (selectedMeals.length >= maxMeals) {
                    showToast('warning', `คุณเลือกเมนูครบแล้ว (${maxMeals} มื้อ)`);
                    return;
                }
                
                // Add meal
                selectedMeals.push(mealId);
                mealCard.classList.add('selected', 'just-selected');
                button.innerHTML = '<i class="fas fa-check"></i><span>เลือกแล้ว</span>';
                button.classList.add('selected');
                
                // Remove animation class after animation completes
                setTimeout(() => {
                    mealCard.classList.remove('just-selected');
                }, 400);
                
                // Disable all other buttons if we've reached the limit
                if (selectedMeals.length >= maxMeals) {
                    document.querySelectorAll('.meal-card:not(.selected) .add-meal-btn').forEach(btn => {
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fas fa-lock"></i><span>เต็มแล้ว</span>';
                    });
                }
            }
            
            updateUI();
            saveSelectionState();
            trackEvent('Meal', selectedMeals.includes(mealId) ? 'Add' : 'Remove', mealId);
        }

        function updateUI() {
            const selectedCount = selectedMeals.length;
            const countElement = document.getElementById('selectedCount');
            const statusElement = document.getElementById('selectionStatus');
            const continueBtn = document.getElementById('continueBtn');
            
            // Update counter
            countElement.textContent = selectedCount;
            countElement.className = selectedCount === maxMeals ? 'counter-number counter-complete' : 'counter-number';
            
            // Update status message
            if (selectedCount === 0) {
                statusElement.textContent = 'กรุณาเลือกเมนูให้ครบตามจำนวน';
                statusElement.style.color = 'var(--text-gray)';
            } else if (selectedCount < maxMeals) {
                const remaining = maxMeals - selectedCount;
                statusElement.textContent = `เลือกอีก ${remaining} เมนู`;
                statusElement.style.color = 'var(--warning)';
            } else {
                statusElement.textContent = '✅ เลือกครบแล้ว! พร้อมดำเนินการต่อ';
                statusElement.style.color = 'var(--success)';
            }
            
            // Update continue button
            continueBtn.disabled = selectedCount !== maxMeals;
            
            // Update page title
            document.title = `เลือกเมนู (${selectedCount}/${maxMeals}) - ${<?php echo json_encode($plan['name_thai'] ?? $plan['name']); ?>} | Krua Thai`;
        }

        function filterMeals() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const activeCategory = document.querySelector('.category-filter.active').dataset.category;
            
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
            const existingEmptyState = document.querySelector('.empty-state');
            if (visibleCount === 0 && searchTerm && !existingEmptyState) {
                const mealsGrid = document.getElementById('mealsGrid');
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'empty-state temp-empty';
                emptyDiv.style.gridColumn = '1 / -1';
                emptyDiv.innerHTML = `
                    <i class="fas fa-search"></i>
                    <h3>ไม่พบเมนูที่ค้นหา</h3>
                    <p>ลองใช้คำค้นหาอื่น หรือเลือกหมวดหมู่ที่แตกต่างกัน</p>
                `;
                mealsGrid.appendChild(emptyDiv);
            } else if (visibleCount > 0) {
                const tempEmpty = document.querySelector('.temp-empty');
                if (tempEmpty) tempEmpty.remove();
            }
        }

        function proceedToCheckout() {
            if (selectedMeals.length !== maxMeals) {
                showToast('error', 'กรุณาเลือกเมนูให้ครบตามจำนวนที่กำหนด');
                return;
            }
            
            // Show loading state
            const continueBtn = document.getElementById('continueBtn');
            const originalText = continueBtn.innerHTML;
            continueBtn.innerHTML = '<div class="loading"></div> กำลังดำเนินการ...';
            continueBtn.disabled = true;
            
            // Send selected meals to server
            const formData = new FormData();
            formData.append('action', 'proceed_to_checkout');
            formData.append('selected_meals', JSON.stringify(selectedMeals));
            
            fetch('meal-selection.php?plan=<?php echo urlencode($plan_id); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    trackEvent('Checkout', 'Proceed', 'MealSelectionComplete');
                    localStorage.removeItem('mealSelection');
                    window.location.href = data.redirect;
                } else {
                    showToast('error', data.message);
                    continueBtn.innerHTML = originalText;
                    continueBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
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

        function trackEvent(category, action, label) {
            console.log(`Analytics: ${category} - ${action} - ${label}`);
        }

        // Save/Load selection state
        function saveSelectionState() {
            localStorage.setItem('mealSelection', JSON.stringify({
                planId: '<?php echo $plan_id; ?>',
                selectedMeals: selectedMeals,
                timestamp: Date.now()
            }));
        }

        function loadSelectionState() {
            const saved = localStorage.getItem('mealSelection');
            if (saved) {
                const data = JSON.parse(saved);
                // Only restore if it's the same plan and less than 1 hour old
                if (data.planId === '<?php echo $plan_id; ?>' && Date.now() - data.timestamp < 3600000) {
                    data.selectedMeals.forEach(mealId => {
                        if (document.querySelector(`[data-menu-id="${mealId}"]`)) {
                            toggleMealSelection(mealId);
                        }
                    });
                }
            }
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && selectedMeals.length === maxMeals) {
                proceedToCheckout();
            }
            
            if (e.key === 'Escape') {
                document.getElementById('searchInput').value = '';
                filterMeals();
            }
        });

        // Performance monitoring
        window.addEventListener('load', function() {
            const loadTime = performance.now();
            console.log(`Meal selection page loaded in ${loadTime.toFixed(2)}ms`);
            trackEvent('Performance', 'PageLoad', loadTime.toFixed(0));
        });
    </script>
</body>
</html>