<?php
/**
 * Krua Thai - Public Homepage
 * File: home.php
 * Description: Public landing page - no login required, all buttons redirect to login/register
 */

session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

// Create database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=krua_thai;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db = $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        $db = null;
    }
}

// Check if user is already logged in - if so, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get featured meals from database
$featured_meals = [];
if ($db) {
    try {
        $query = "SELECT m.*, mc.name as category_name, mc.name_thai as category_name_thai
                  FROM menus m 
                  LEFT JOIN menu_categories mc ON m.category_id = mc.id 
                  WHERE m.is_featured = 1 AND m.is_available = 1 
                  ORDER BY m.created_at DESC 
                  LIMIT 6";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $featured_meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Featured meals query failed: " . $e->getMessage());
    }
}

// Fallback data if no database content
if (empty($featured_meals)) {
    $featured_meals = [
        [
            'id' => 'sample-1',
            'name' => 'Brown Rice & Basil Chicken',
            'name_thai' => 'ข้าวกล้องผัดกะเพรา',
            'description' => 'Aromatic Thai basil stir-fried with tender chicken served over nutritious brown rice',
            'calories_per_serving' => 380,
            'protein_g' => 28.5,
            'base_price' => 189.00,
            'dietary_tags' => '["High Protein", "Low Sodium", "Gluten-Free"]',
            'category_name_thai' => 'ข้าวกล่อง'
        ],
        [
            'id' => 'sample-2',
            'name' => 'Riceberry & Grilled Salmon',
            'name_thai' => 'ไรซ์เบอร์รี่ปลาแซลมอนย่าง',
            'description' => 'Omega-rich salmon perfectly grilled and served with antioxidant-packed riceberry',
            'calories_per_serving' => 420,
            'protein_g' => 32.0,
            'base_price' => 249.00,
            'dietary_tags' => '["Heart Healthy", "Diabetic-Friendly", "High Omega-3"]',
            'category_name_thai' => 'ข้าวกล่อง'
        ],
        [
            'id' => 'sample-3',
            'name' => 'Jasmine Rice & Herbal Curry',
            'name_thai' => 'ข้าวหอมมะลิแกงสมุนไพร',
            'description' => 'Traditional Thai green curry with fresh herbs and vegetables over fragrant jasmine rice',
            'calories_per_serving' => 350,
            'protein_g' => 18.5,
            'base_price' => 169.00,
            'dietary_tags' => '["Vegetarian", "Anti-Inflammatory", "Low Fat"]',
            'category_name_thai' => 'แกงไทย'
        ]
    ];
}

// Get subscription plans (weekly only: 4, 8, 12, 15 meals)
$subscription_plans = [];
if ($db) {
    try {
        $query = "SELECT * FROM subscription_plans 
                  WHERE is_active = 1 AND plan_type = 'weekly' 
                  AND meals_per_week IN (4, 8, 12, 15)
                  ORDER BY sort_order ASC, meals_per_week ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $subscription_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Subscription plans query failed: " . $e->getMessage());
    }
}

// Fallback plans
if (empty($subscription_plans)) {
    $subscription_plans = [
        [
            'id' => 'plan-1',
            'name' => 'Starter',
            'name_thai' => 'แพ็คเกจเริ่มต้น',
            'meals_per_week' => 4,
            'base_price' => 599.00,
            'final_price' => 599.00,
            'is_popular' => 0,
            'features' => '["4 meals per week", "Choose from 20+ recipes", "Free delivery", "Skip or pause anytime"]'
        ],
        [
            'id' => 'plan-2',
            'name' => 'Popular Choice',
            'name_thai' => 'แพ็คเกจยอดนิยม',
            'meals_per_week' => 8,
            'base_price' => 1199.00,
            'final_price' => 1199.00,
            'is_popular' => 1,
            'features' => '["8 meals per week", "Access to all recipes", "Free delivery", "Priority support", "Monthly health check-in"]'
        ],
        [
            'id' => 'plan-3',
            'name' => 'Family',
            'name_thai' => 'แพ็คเกจครอบครัว',
            'meals_per_week' => 12,
            'base_price' => 1799.00,
            'final_price' => 1799.00,
            'is_popular' => 0,
            'features' => '["12 meals per week", "All recipes + family portions", "Free delivery", "Dedicated nutritionist"]'
        ],
        [
            'id' => 'plan-4',
            'name' => 'Premium',
            'name_thai' => 'แพ็คเกจพรีเมียม',
            'meals_per_week' => 15,
            'base_price' => 2249.00,
            'final_price' => 2249.00,
            'is_popular' => 0,
            'features' => '["15 meals per week", "Unlimited access", "Free delivery", "Personal chef consultation", "Custom meal plans"]'
        ]
    ];
}

// Get customer reviews
$reviews = [];
if ($db) {
    try {
        $query = "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) as customer_name
                  FROM reviews r
                  JOIN users u ON r.user_id = u.id
                  WHERE r.is_public = 1 AND r.moderation_status = 'approved'
                  ORDER BY r.created_at DESC
                  LIMIT 3";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Reviews query failed: " . $e->getMessage());
    }
}

// Fallback reviews
if (empty($reviews)) {
    $reviews = [
        [
            'customer_name' => 'คุณสมชาย ใจดี',
            'overall_rating' => 5,
            'title' => 'อร่อยมาก คุ้มค่า',
            'comment' => 'ข้าวกล้องผัดกะเพราอร่อยมาก เครื่องเทศแท้ๆ รสชาติจัดจ้าน ส่งเร็วมาก บริการดีเยี่ยม',
            'created_at' => '2025-07-01 12:00:00'
        ],
        [
            'customer_name' => 'คุณวิภา สุขใส',
            'overall_rating' => 5,
            'title' => 'ถูกใจสาวออฟฟิศ',
            'comment' => 'สะดวกมาก สั่งได้ทุกวัน อาหารสดใหม่ โภชนาการครบ ลดน้ำหนักได้ด้วย ประทับใจค่ะ',
            'created_at' => '2025-06-28 14:30:00'
        ],
        [
            'customer_name' => 'คุณธีรยุทธ ฟิตเนส',
            'overall_rating' => 4,
            'title' => 'เหมาะกับคนออกกำลังกาย',
            'comment' => 'โปรตีนเยอะ แคลอรี่พอดี วัตถุดิบสด รสชาติดี จัดส่งตรงเวลา ราคายุติธรรม',
            'created_at' => '2025-06-25 09:15:00'
        ]
    ];
}

// Flash message handling
$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Krua Thai - อาหารไทยเพื่อสุขภาพ ส่งถึงบ้าน | บริการจัดส่งอาหารไทยคุณภาพ</title>
    <meta name="description" content="สั่งอาหารไทยเพื่อสุขภาพส่งถึงบ้าน อาหารสดใหม่ ขนาดความเผ็ดปรับได้ โภชนาการครบครัน แพ็คเกจสมาชิกราคาย่อมเยา">
    <meta name="keywords" content="อาหารไทยส่งถึงบ้าน, อาหารเพื่อสุขภาพ, อาหารไทยต้นตำรับ, สมาชิกอาหาร, ข้าวกล่อง, แกงไทย, จัดส่งอาหารกรุงเทพ">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://kruathai.com/">
    <meta property="og:title" content="Krua Thai - อาหารไทยเพื่อสุขภาพ ส่งถึงบ้าน">
    <meta property="og:description" content="สั่งอาหารไทยเพื่อสุขภาพส่งถึงบ้าน อาหารสดใหม่ ขนาดความเผ็ดปรับได้ โภชนาการครบครัน">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    
    <!-- Fonts -->
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

        /* Header */
        header {
            background: var(--white);
            box-shadow: var(--shadow-soft);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: var(--transition);
        }

        header.scrolled {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-medium);
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

        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--curry), var(--brown));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--curry);
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

        /* Mobile menu */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-dark);
            cursor: pointer;
        }

        /* Flash Message */
        .flash-message {
            padding: 1rem 2rem;
            text-align: center;
            font-weight: 500;
            margin-top: 70px;
            display: none;
        }

        .flash-message.show {
            display: block;
        }

        .flash-message.success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-bottom: 2px solid #28a745;
        }

        .flash-message.error {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-bottom: 2px solid #dc3545;
        }

        .flash-message.info {
            background: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
            border-bottom: 2px solid #17a2b8;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%);
            padding: 8rem 2rem 6rem;
            min-height: 90vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            margin-top: 70px;
        }

        .hero::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 60%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="70" cy="30" r="15" fill="rgba(255,255,255,0.1)"/><circle cx="85" cy="60" r="8" fill="rgba(255,255,255,0.05)"/><circle cx="60" cy="75" r="12" fill="rgba(255,255,255,0.08)"/></svg>');
            background-size: 200px 200px;
            opacity: 0.3;
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .hero-text h1 {
            font-size: 3.5rem;
            color: var(--white);
            margin-bottom: 1.5rem;
            line-height: 1.1;
            font-weight: 800;
        }

        .hero-text .subtitle {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .hero-text p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .hero-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hero-actions .btn {
            font-size: 1.1rem;
            padding: 1rem 2rem;
        }

        .hero-actions .btn-secondary {
            background: transparent;
            color: var(--white);
            border-color: var(--white);
        }

        .hero-actions .btn-secondary:hover {
            background: var(--white);
            color: var(--curry);
        }

        .hero-image {
            position: relative;
            height: 450px;
            border-radius: 25px;
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 15px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .hero-image-item {
            background: linear-gradient(135deg, var(--cream), #e6d2a8);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: var(--text-dark);
            text-align: center;
            padding: 1rem;
            transition: var(--transition);
            font-weight: 600;
        }

        .hero-image-item:hover {
            transform: scale(1.05);
        }

        /* Features Section */
        .features-section {
            padding: 6rem 2rem;
            background: var(--cream);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-header h2 {
            font-size: 2.5rem;
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .section-header p {
            font-size: 1.1rem;
            color: var(--text-gray);
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            text-align: center;
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            display: block;
        }

        .feature-card h3 {
            font-size: 1.3rem;
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .feature-card p {
            color: var(--text-gray);
            line-height: 1.6;
        }

        /* Featured Meals Section */
        .meals-section {
            padding: 6rem 2rem;
            background: var(--white);
        }

        .meals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .meal-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
            border: 1px solid var(--border-light);
        }

        .meal-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }

        .meal-image {
            height: 200px;
            background: linear-gradient(135deg, var(--cream), #e6d2a8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }

        .meal-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }

        .meal-card:hover .meal-image::before {
            transform: translateX(100%);
        }

        .meal-info {
            padding: 1.5rem;
        }

        .meal-category {
            font-size: 0.8rem;
            color: var(--brown);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .meal-info h3 {
            color: var(--text-dark);
            margin-bottom: 0.8rem;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .meal-description {
            color: var(--text-gray);
            margin-bottom: 1rem;
            line-height: 1.5;
            font-size: 0.95rem;
        }

        .nutrition-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .tag {
            background: var(--cream);
            color: var(--brown);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .meal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .meal-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--curry);
        }

        .calories {
            color: var(--sage);
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Plans Section */
        .plans-section {
            padding: 6rem 2rem;
            background: var(--cream);
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .plan-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
            border: 2px solid transparent;
            position: relative;
        }

        .plan-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }

        .plan-card.featured {
            border-color: var(--curry);
            transform: scale(1.05);
        }

        .plan-card.featured::before {
            content: "ยอดนิยม";
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--curry);
            color: var(--white);
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .plan-name {
            font-size: 1.5rem;
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .plan-price {
            font-size: 2.5rem;
            color: var(--curry);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .plan-price span {
            font-size: 1rem;
            color: var(--text-gray);
            font-weight: 500;
        }

        .plan-features {
            list-style: none;
            margin: 2rem 0;
            text-align: left;
        }

        .plan-features li {
            padding: 0.5rem 0;
            color: var(--text-gray);
            position: relative;
            padding-left: 1.5rem;
        }

        .plan-features li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }

        /* How It Works Section */
        .how-it-works {
            padding: 6rem 2rem;
            background: var(--white);
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            margin-top: 3rem;
        }

        .step-card {
            text-align: center;
            position: relative;
        }

        .step-number {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            margin: 0 auto 1.5rem;
            box-shadow: var(--shadow-soft);
        }

        .step-card h3 {
            font-size: 1.3rem;
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .step-card p {
            color: var(--text-gray);
            line-height: 1.6;
        }

        /* Reviews Section */
        .reviews-section {
            padding: 6rem 2rem;
            background: var(--cream);
        }

        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .review-card {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
        }

        .review-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .review-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .review-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--sage), var(--brown));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 600;
            margin-right: 1rem;
        }

        .review-info h4 {
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 0.3rem;
        }

        .stars {
            color: #ffd700;
            margin-bottom: 0.5rem;
        }

        .review-title {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.8rem;
        }

        .review-text {
            color: var(--text-gray);
            line-height: 1.6;
            font-style: italic;
        }

        /* CTA Section */
        .cta-section {
            padding: 6rem 2rem;
            background: linear-gradient(135deg, var(--text-dark) 0%, var(--sage) 100%);
            text-align: center;
            color: var(--white);
        }

        .cta-section h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .cta-section p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cta-buttons .btn {
            font-size: 1.1rem;
            padding: 1rem 2rem;
        }

        /* Footer */
        footer {
            background: var(--text-dark);
            color: var(--white);
            padding: 4rem 2rem 2rem;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            margin-bottom: 2rem;
        }

        .footer-section h3 {
            margin-bottom: 1.5rem;
            color: var(--cream);
            font-size: 1.2rem;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.8rem;
        }

        .footer-section a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-section a:hover {
            color: var(--cream);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }

            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: var(--white);
                flex-direction: column;
                padding: 2rem;
                box-shadow: var(--shadow-soft);
            }

            .nav-links.active {
                display: flex;
            }

            .hero {
                padding: 6rem 1rem 4rem;
                text-align: center;
            }

            .hero-content {
                grid-template-columns: 1fr;
                gap: 3rem;
            }

            .hero-text h1 {
                font-size: 2.5rem;
            }

            .hero-image {
                height: 300px;
                grid-template-columns: 1fr;
                grid-template-rows: repeat(4, 1fr);
            }

            .section-header h2 {
                font-size: 2rem;
            }

            .features-grid,
            .meals-grid,
            .plans-grid,
            .steps-grid,
            .reviews-grid {
                grid-template-columns: 1fr;
            }

            .plan-card.featured {
                transform: none;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
        }

        /* Auth Required Tooltip */
        .auth-tooltip {
            position: relative;
            display: inline-block;
        }

        .auth-tooltip .tooltip-text {
            visibility: hidden;
            background-color: var(--text-dark);
            color: var(--white);
            text-align: center;
            border-radius: 6px;
            padding: 8px 12px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            font-size: 0.8rem;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .auth-tooltip .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: var(--text-dark) transparent transparent transparent;
        }

        .auth-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Loading animation */
        .loading {
            opacity: 0;
            animation: fadeInUp 0.6s ease forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header id="header">
        <nav>
            <a href="home.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <span class="logo-text">Krua Thai</span>
            </a>

            <ul class="nav-links" id="navLinks">
                <li><a href="#meals">เมนูแนะนำ</a></li>
                <li><a href="#plans">แพ็กเกจ</a></li>
                <li><a href="#features">จุดเด่น</a></li>
                <li><a href="#reviews">รีวิว</a></li>
                <li><a href="#how-it-works">วิธีการใช้งาน</a></li>
            </ul>

            <div class="nav-actions">
                <a href="login.php" class="btn-secondary">เข้าสู่ระบบ</a>
                <a href="register.php" class="btn-primary">สมัครสมาชิก</a>
            </div>

            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle mobile menu">
                ☰
            </button>
        </nav>
    </header>

    <!-- Flash Message -->
    <?php if ($flash_message): ?>
        <div class="flash-message <?php echo htmlspecialchars($flash_type); ?> show" id="flashMessage">
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero" id="hero">
        <div class="hero-content">
            <div class="hero-text">
                <div class="subtitle">อาหารไทยต้นตำรับ</div>
                <h1>เพื่อสุขภาพที่ดี<br>ส่งถึงบ้านคุณ</h1>
                <p>
                    ลิ้มรสอาหารไทยแท้รสเข้มข้น ปรุงสดใหม่ทุกวัน 
                    ส่วนผสมคุณภาพ โภชนาการครบครัน ปรับระดับความเผ็ดได้ตามใจ
                    พร้อมส่งถึงหน้าบ้านคุณ
                </p>
                <div class="hero-actions">
                    <a href="register.php" class="btn-primary">เริ่มต้นสมาชิก</a>
                    <a href="#meals" class="btn-secondary">ดูเมนู</a>
                </div>
            </div>
            <div class="hero-image">
                <div class="hero-image-item">ข้าวกล้อง<br>ผัดกะเพรา</div>
                <div class="hero-image-item">ไรซ์เบอร์รี่<br>แกงเขียวหวาน</div>
                <div class="hero-image-item">ข้าวหอมมะลิ<br>ต้มยำกุ้ง</div>
                <div class="hero-image-item">ข้าวเหนียว<br>มะม่วง</div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="section-header">
                <h2>ทำไมต้องเลือก Krua Thai?</h2>
                <p>ผสมผสานความอร่อยของอาหารไทยต้นตำรับ กับโภชนาการที่ดีต่อสุขภาพ</p>
            </div>
            <div class="features-grid">
                <div class="feature-card loading">
                    <span class="feature-icon">🌾</span>
                    <h3>ข้าวหลากหลายสายพันธุ์</h3>
                    <p>ใช้ข้าวคุณภาพพรีเมียม ข้าวกล้อง ไรซ์เบอร์รี่ ข้าวหอมมะลิ และข้าวม่วง แต่ละชนิดมีคุณค่าทางโภชนาการที่แตกต่างกัน</p>
                </div>
                <div class="feature-card loading">
                    <span class="feature-icon">👨‍🍳</span>
                    <h3>เชฟผู้เชี่ยวชาญ</h3>
                    <p>เชฟไทยมืออาชีพที่มีประสบการณ์กว่า 15 ปี ปรุงด้วยวิธีดั้งเดิม ใช้เครื่องเทศแท้ รสชาติต้นตำรับ</p>
                </div>
                <div class="feature-card loading">
                    <span class="feature-icon">🥗</span>
                    <h3>นักโภชนาการรับรอง</h3>
                    <p>ทุกเมนูได้รับการออกแบบโดยนักโภชนาการ ให้โปรตีน คาร์โบไฮเดรต และไขมันดีในสัดส่วนที่เหมาะสม</p>
                </div>
                <div class="feature-card loading">
                    <span class="feature-icon">🚚</span>
                    <h3>ส่งสดใหม่ทุกวัน</h3>
                    <p>ปรุงสดใหม่ในครัวที่ได้มาตรฐาน ส่งภายในชั่วโมงเดียว รักษาความสดและความปลอดภัยของอาหาร</p>
                </div>
                <div class="feature-card loading">
                    <span class="feature-icon">🌶️</span>
                    <h3>ปรับความเผ็ดได้</h3>
                    <p>เลือกระดับความเผ็ดจากไม่เผ็ดจนถึงเผ็ดจัด และปรับเมนูตามความต้องการ มังสวิรัติ วีแกน หรือไม่มีกลูเตน</p>
                </div>
                <div class="feature-card loading">
                    <span class="feature-icon">📱</span>
                    <h3>จัดการง่าย</h3>
                    <p>ข้ามมื้อ หยุดชั่วคราว หรือเปลี่ยนแผนได้ตลอดเวลา ผ่านแอปและเว็บไซต์ที่ใช้งานง่าย</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Meals Section -->
    <section class="meals-section" id="meals">
        <div class="container">
            <div class="section-header">
                <h2>เมนูแนะนำ อาหารไทยเพื่อสุขภาพ</h2>
                <p>เลือกสรรเมนูอาหารไทยต้นตำรับที่ปรับปรุงให้เหมาะกับสุขภาพ</p>
            </div>
            <div class="meals-grid">
                <?php foreach (array_slice($featured_meals, 0, 3) as $meal): ?>
                    <?php
                    $dietary_tags = json_decode($meal['dietary_tags'] ?? '[]', true);
                    if (!is_array($dietary_tags)) $dietary_tags = [];
                    ?>
                    <div class="meal-card loading">
                        <div class="meal-image">
                            <?php if (isset($meal['main_image_url']) && $meal['main_image_url']): ?>
                                <img src="<?php echo htmlspecialchars($meal['main_image_url']); ?>" alt="<?php echo htmlspecialchars($meal['name_thai'] ?? $meal['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div style="font-size: 1rem; text-align: center;">
                                    <i class="fas fa-utensils" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                    <br><?php echo htmlspecialchars($meal['name_thai'] ?? $meal['name']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="meal-info">
                            <?php if (!empty($meal['category_name_thai'])): ?>
                                <div class="meal-category"><?php echo htmlspecialchars($meal['category_name_thai']); ?></div>
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($meal['name_thai'] ?? $meal['name']); ?></h3>
                            <p class="meal-description">
                                <?php echo htmlspecialchars($meal['description']); ?>
                            </p>
                            <?php if (!empty($dietary_tags)): ?>
                                <div class="nutrition-tags">
                                    <?php foreach (array_slice($dietary_tags, 0, 3) as $tag): ?>
                                        <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="meal-footer">
                                <div>
                                    <span class="meal-price">฿<?php echo number_format($meal['base_price'], 0); ?></span>
                                    <?php if (!empty($meal['calories_per_serving'])): ?>
                                        <div class="calories"><?php echo $meal['calories_per_serving']; ?> แคลอรี่</div>
                                    <?php endif; ?>
                                </div>
                                <div class="auth-tooltip">
                                    <a href="register.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">สั่งเลย</a>
                                    <span class="tooltip-text">กรุณาเข้าสู่ระบบก่อน</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; margin-top: 2rem;">
                <div class="auth-tooltip">
                    <a href="register.php" class="btn btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">ดูเมนูทั้งหมด</a>
                    <span class="tooltip-text">กรุณาสมัครสมาชิกก่อน</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Subscription Plans Section -->
    <section class="plans-section" id="plans">
        <div class="container">
            <div class="section-header">
                <h2>เลือกแพ็กเกจที่เหมาะกับคุณ</h2>
                <p>แพ็กเกจสมาชิกรายสัปดาห์ ยืดหยุ่นตามไลฟ์สไตล์และเป้าหมายสุขภาพของคุณ</p>
            </div>
            <div class="plans-grid">
                <?php foreach ($subscription_plans as $plan): ?>
                    <?php
                    $features = json_decode($plan['features'] ?? '[]', true);
                    if (!is_array($features)) $features = [];
                    $is_popular = $plan['is_popular'] ?? 0;
                    ?>
                    <div class="plan-card <?php echo $is_popular ? 'featured' : ''; ?> loading">
                        <h3 class="plan-name"><?php echo htmlspecialchars($plan['name_thai'] ?? $plan['name']); ?></h3>
                        <div class="plan-price">
                            ฿<?php echo number_format($plan['final_price'], 0); ?> 
                            <span>/สัปดาห์</span>
                        </div>
                        <div style="margin: 1rem 0; color: var(--text-gray); font-weight: 500;">
                            <?php echo $plan['meals_per_week']; ?> มื้อต่อสัปดาห์
                        </div>
                        <?php if (!empty($features)): ?>
                            <ul class="plan-features">
                                <?php foreach ($features as $feature): ?>
                                    <li><?php echo htmlspecialchars($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <div class="auth-tooltip">
                            <a href="register.php?plan=<?php echo $plan['id']; ?>" class="btn btn-primary">เลือกแพ็กเกจนี้</a>
                            <span class="tooltip-text">กรุณาสมัครสมาชิกก่อน</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; margin-top: 3rem;">
                <p style="color: var(--text-gray); margin-bottom: 1rem;">
                    <strong>ส่งฟรีทุกแพ็กเกจ</strong> • ข้ามหรือหยุดได้ตลอดเวลา • ยกเลิกแจ้งล่วงหน้า 24 ชั่วโมง
                </p>
                <p style="color: var(--text-gray); font-size: 0.9rem;">
                    🔒 ชำระเงินปลอดภัยด้วย Apple Pay, Google Pay และ PayPal
                </p>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works" id="how-it-works">
        <div class="container">
            <div class="section-header">
                <h2>ง่ายๆ เพียง 4 ขั้นตอน</h2>
                <p>เริ่มต้นการกินอาหารเพื่อสุขภาพได้ง่ายๆ ใน 4 ขั้นตอนเท่านั้น</p>
            </div>
            <div class="steps-grid">
                <div class="step-card loading">
                    <div class="step-number">1</div>
                    <h3>สมัครสมาชิก</h3>
                    <p>เลือกแพ็กเกจที่เหมาะกับคุณ และกรอกข้อมูลส่วนตัว ใช้เวลาเพียง 2 นาที</p>
                </div>
                <div class="step-card loading">
                    <div class="step-number">2</div>
                    <h3>เลือกเมนู</h3>
                    <p>เลือกเมนูอาหารที่ชอบจากเมนูหลากหลาย ปรับระดับความเผ็ดและความต้องการพิเศษ</p>
                </div>
                <div class="step-card loading">
                    <div class="step-number">3</div>
                    <h3>ชำระเงิน</h3>
                    <p>ชำระเงินผ่านช่องทางที่ปลอดภัย Apple Pay, Google Pay หรือ PayPal</p>
                </div>
                <div class="step-card loading">
                    <div class="step-number">4</div>
                    <h3>รับอาหาร</h3>
                    <p>รับอาหารสดใหม่ส่งถึงหน้าบ้าน พร้อมรับประทานใน 2-3 นาที</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Reviews Section -->
    <section class="reviews-section" id="reviews">
        <div class="container">
            <div class="section-header">
                <h2>ความคิดเห็นจากลูกค้า</h2>
                <p>ฟังความคิดเห็นจากลูกค้าที่ใช้บริการ Krua Thai</p>
            </div>
            <div class="reviews-grid">
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card loading">
                        <div class="review-header">
                            <div class="review-avatar">
                                <?php echo mb_substr($review['customer_name'], 0, 1, 'UTF-8'); ?>
                            </div>
                            <div class="review-info">
                                <h4><?php echo htmlspecialchars($review['customer_name']); ?></h4>
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $review['overall_rating']): ?>
                                            ⭐
                                        <?php else: ?>
                                            ☆
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($review['title'])): ?>
                            <div class="review-title"><?php echo htmlspecialchars($review['title']); ?></div>
                        <?php endif; ?>
                        <div class="review-text">
                            "<?php echo htmlspecialchars($review['comment']); ?>"
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2>พร้อมเริ่มต้นการกินเพื่อสุขภาพแล้วหรือยัง?</h2>
            <p>
                เข้าร่วมกับลูกค้าหลายพันคนที่เลือก Krua Thai 
                เพื่อสุขภาพที่ดีและความอร่อยที่แท้จริง
            </p>
            <div class="cta-buttons">
                <a href="register.php" class="btn btn-primary">เริ่มต้นเลย</a>
                <a href="#plans" class="btn btn-secondary" style="color: var(--white); border-color: var(--white);">ดูแพ็กเกจ</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                        <div class="logo-icon" style="width: 30px; height: 30px; font-size: 1rem;">
                            <i class="fas fa-utensils"></i>
                        </div>
                        Krua Thai
                    </div>
                </h3>
                <p style="color: rgba(255, 255, 255, 0.8); line-height: 1.6;">
                    อาหารไทยต้นตำรับเพื่อสุขภาพ ส่งถึงบ้านคุณด้วยความใส่ใจ
                    รสชาติดั้งเดิม ผสานโภชนาการสมัยใหม่
                </p>
                <div style="margin-top: 1.5rem;">
                    <a href="#" style="margin-right: 1rem; font-size: 1.5rem;">📘</a>
                    <a href="#" style="margin-right: 1rem; font-size: 1.5rem;">📷</a>
                    <a href="#" style="margin-right: 1rem; font-size: 1.5rem;">🐦</a>
                    <a href="#" style="font-size: 1.5rem;">📺</a>
                </div>
            </div>
            
            <div class="footer-section">
                <h3>เมนู</h3>
                <ul>
                    <li><a href="#meals">เมนูแนะนำ</a></li>
                    <li><a href="#plans">แพ็กเกจสมาชิก</a></li>
                    <li><a href="register.php">เมนูทั้งหมด</a></li>
                    <li><a href="register.php">ข้อมูลโภชนาการ</a></li>
                    <li><a href="register.php">คำถามที่พบบ่อย</a></li>
                    <li><a href="register.php">บล็อกสุขภาพ</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>ช่วยเหลือ</h3>
                <ul>
                    <li>📧 <a href="mailto:hello@kruathai.com">hello@kruathai.com</a></li>
                    <li>📞 <a href="tel:021234567">02-123-4567</a></li>
                    <li>💬 <a href="register.php">แชทสด</a></li>
                    <li>🕐 จันทร์-เสาร์: 8:00-20:00</li>
                    <li>📍 กรุงเทพมหานคร</li>
                    <li><a href="register.php">พื้นที่ส่ง</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>เกี่ยวกับเรา</h3>
                <ul>
                    <li><a href="register.php">เรื่องราว Krua Thai</a></li>
                    <li><a href="register.php">ร่วมงานกับเรา</a></li>
                    <li><a href="register.php">สื่อมวลชน</a></li>
                    <li><a href="register.php">ความยั่งยืน</a></li>
                    <li><a href="register.php">เงื่อนไขการใช้งาน</a></li>
                    <li><a href="register.php">นโยบายความเป็นส่วนตัว</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>
                &copy; <?php echo date('Y'); ?> Krua Thai. สงวนลิขสิทธิ์. สร้างด้วย ❤️ เพื่อสุขภาพที่ดี
            </p>
            <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                อาหารไทยต้นตำรับ เพื่อสุขภาพ • ใบอนุญาตธุรกิจอาหาร: #FB-TH-2024-001
            </p>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const navLinks = document.getElementById('navLinks');

        mobileMenuToggle.addEventListener('click', function() {
            navLinks.classList.toggle('active');
            this.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
        });

        // Header scroll effect
        const header = document.getElementById('header');
        let lastScrollTop = 0;

        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
            
            lastScrollTop = scrollTop;
        });

        // Smooth scroll for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const offsetTop = target.offsetTop - 80;
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                    
                    // Close mobile menu if open
                    navLinks.classList.remove('active');
                    mobileMenuToggle.textContent = '☰';
                }
            });
        });

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all loading elements
        document.querySelectorAll('.loading').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Auto-hide flash message
        const flashMessage = document.getElementById('flashMessage');
        if (flashMessage) {
            setTimeout(() => {
                flashMessage.style.opacity = '0';
                flashMessage.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    flashMessage.style.display = 'none';
                }, 300);
            }, 5000);
        }

        // Auth required notification
        function showAuthRequired() {
            alert('กรุณาเข้าสู่ระบบหรือสมัครสมาชิกก่อนใช้งานฟีเจอร์นี้');
        }

        // Add auth required alerts to interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // All buttons that require auth (except those in tooltips)
            const authButtons = document.querySelectorAll('a[href*="register.php"], a[href*="login.php"]');
            
            authButtons.forEach(button => {
                if (!button.closest('.auth-tooltip')) {
                    button.addEventListener('click', function(e) {
                        if (this.getAttribute('href').includes('register.php') && this.textContent.includes('เลย')) {
                            e.preventDefault();
                            showAuthRequired();
                            setTimeout(() => {
                                window.location.href = 'register.php';
                            }, 1000);
                        }
                    });
                }
            });
        });

        // Plan card hover effects
        document.querySelectorAll('.plan-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                if (!this.classList.contains('featured')) {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                }
            });
            
            card.addEventListener('mouseleave', function() {
                if (!this.classList.contains('featured')) {
                    this.style.transform = 'translateY(0) scale(1)';
                }
            });
        });

        // Meal card hover effects
        document.querySelectorAll('.meal-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Hero image effects
        document.querySelectorAll('.hero-image-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05) rotate(1deg)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1) rotate(0deg)';
            });
        });

        // Keyboard navigation support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                navLinks.classList.remove('active');
                mobileMenuToggle.textContent = '☰';
            }
        });

        // Track page views (analytics placeholder)
        function trackEvent(action, category, label) {
            console.log(`Event: ${action}, Category: ${category}, Label: ${label}`);
            // Add Google Analytics or other tracking here
        }

        // Track CTA clicks
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const action = this.textContent.trim();
                trackEvent('cta_click', 'homepage', action);
            });
        });

        // Performance optimization
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }

        // Simple cookie consent
        if (!localStorage.getItem('cookieConsent')) {
            setTimeout(() => {
                const consent = confirm('เว็บไซต์นี้ใช้คุกกี้เพื่อปรับปรุงประสบการณ์การใช้งาน คุณยอมรับหรือไม่?');
                if (consent) {
                    localStorage.setItem('cookieConsent', 'accepted');
                }
            }, 3000);
        }

        // Scroll progress indicator
        function updateScrollProgress() {
            const scrollTop = document.documentElement.scrollTop;
            const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrollProgress = (scrollTop / scrollHeight) * 100;
            
            document.documentElement.style.setProperty('--scroll-progress', scrollProgress + '%');
        }

        window.addEventListener('scroll', updateScrollProgress);
        updateScrollProgress();

        // Service Worker registration (PWA)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js').then(function(registration) {
                    console.log('ServiceWorker registration successful');
                }, function(err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
</body>
</html>