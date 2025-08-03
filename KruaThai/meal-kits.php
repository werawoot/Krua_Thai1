<?php
/**
 * Krua Thai - Meal Kits Page
 * File: meal-kit.php
 * Description: Browse and order Thai meal kits for home cooking
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'includes/cart_functions.php';
require_once 'config/database.php';

try {
    // Fetch meal kit categories
    $categories = [];
    $stmt = $pdo->prepare("
        SELECT id, name, name_thai 
        FROM menu_categories 
        WHERE is_active = 1 
        ORDER BY sort_order ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch meal kits (look for Meal Kits category)
    $stmt = $pdo->prepare("
        SELECT m.*, c.name AS category_name, c.name_thai AS category_name_thai
        FROM menus m 
        LEFT JOIN menu_categories c ON m.category_id = c.id 
        WHERE m.is_available = 1 
        AND (c.name = 'Meal Kits' OR c.name_thai = 'มีล คิท')
        ORDER BY m.is_featured DESC, m.updated_at DESC
    ");
    $stmt->execute();
    $meal_kits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no meal kits found, create sample data
    if (empty($meal_kits)) {
        $meal_kits = [
            [
                'id' => 'green-curry-kit',
                'name' => 'Green Curry Kit',
                'name_thai' => 'ชุดแกงเขียวหวาน',
                'description' => 'Everything you need to make authentic Thai Green Curry at home. Includes homemade curry paste, fresh chicken, coconut milk, and jasmine rice.',
                'base_price' => 18.99,
                'prep_time' => 25,
                'serves' => '2-3 people',
                'spice_level' => 'Medium',
                'difficulty' => 'Easy',
                'main_image_url' => 'assets/image/green-curry-kit.jpg'
            ],
            [
                'id' => 'panang-kit',
                'name' => 'Panang Curry Kit',
                'name_thai' => 'ชุดแพนง',
                'description' => 'Rich and creamy Panang curry with tender beef, traditional curry paste, and thick coconut cream. Perfect for a restaurant-quality meal at home.',
                'base_price' => 21.99,
                'prep_time' => 30,
                'serves' => '2-3 people',
                'spice_level' => 'Mild',
                'difficulty' => 'Easy',
                'main_image_url' => 'assets/image/panang-kit.jpg'
            ],
            [
                'id' => 'pad-thai-kit',
                'name' => 'Pad Thai Kit',
                'name_thai' => 'ชุดผัดไทย',
                'description' => 'The iconic Thai noodle dish made simple. Includes rice noodles, fresh shrimp, our signature Pad Thai sauce, and traditional garnishes.',
                'base_price' => 16.99,
                'prep_time' => 20,
                'serves' => '2 people',
                'spice_level' => 'Mild',
                'difficulty' => 'Beginner',
                'main_image_url' => 'assets/image/pad-thai-kit.jpg'
            ],
            [
                'id' => 'tom-yum-kit',
                'name' => 'Tom Yum Soup Kit',
                'name_thai' => 'ชุดต้มยำ',
                'description' => 'Hot and sour soup with fresh shrimp, mushrooms, and aromatic herbs. Includes pre-made tom yum paste and fresh ingredients.',
                'base_price' => 17.99,
                'prep_time' => 15,
                'serves' => '2-3 people',
                'spice_level' => 'Hot',
                'difficulty' => 'Beginner',
                'main_image_url' => 'assets/image/tom-yum-kit.jpg'
            ]
        ];
    }

} catch (Exception $e) {
    error_log("Meal kits page database error: " . $e->getMessage());
    $categories = [];
    $meal_kits = [];
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thai Meal Kits - Cook Authentic Thai at Home | Krua Thai</title>
    <meta name="description" content="Discover authentic Thai meal kits delivered to your door. Everything you need to cook restaurant-quality Thai food at home.">
    
    <!-- BaticaSans Font Import -->
    <link rel="preconnect" href="https://ydpschool.com">
    <style>
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Regular.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Bold.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Bold.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Bold.ttf') format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Medium.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.ttf') format('truetype');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }

        /* CSS Custom Properties for Krua Thai Design System */
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: var(--white);
            font-weight: 400;
        }

        /* Typography using BaticaSans */
        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
        }

        /* Promotional Banner */
        .promo-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, var(--curry) 0%, #e67e22 100%);
            color: var(--white);
            text-align: center;
            padding: 8px 20px;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            font-size: 14px;
            z-index: 1001;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .promo-banner-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .promo-icon {
            font-size: 16px;
        }

        .promo-close {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--white);
            font-size: 18px;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .promo-close:hover {
            opacity: 1;
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 38px;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
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
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .nav-links a:hover,
        .nav-links a.active {
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

        /* Hero Section */
        .hero-section {
            padding-top: 120px;
            min-height: 60vh;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            text-align: center;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.1;
            font-family: 'BaticaSans', sans-serif;
        }

        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: var(--text-gray);
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            font-family: 'BaticaSans', sans-serif;
        }

        .hero-cta {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Meal Kits Grid */
        .meal-kits-section {
            padding: 5rem 2rem;
            background: var(--white);
        }

        .section-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .section-subtitle {
            font-size: 1.1rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .meal-kits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .meal-kit-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
            cursor: pointer;
        }

        .meal-kit-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }

        .kit-image {
            width: 100%;
            height: 250px;
            background: linear-gradient(45deg, var(--curry), var(--brown));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 3rem;
            position: relative;
            overflow: hidden;
        }

        .kit-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .kit-content {
            padding: 2rem;
        }

        .kit-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .kit-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            margin-bottom: 0.5rem;
        }

        .kit-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--curry);
            font-family: 'BaticaSans', sans-serif;
        }

        .kit-description {
            color: var(--text-gray);
            margin-bottom: 1.5rem;
            line-height: 1.6;
            font-family: 'BaticaSans', sans-serif;
        }

        .kit-details {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .detail-icon {
            width: 16px;
            height: 16px;
            fill: var(--curry);
        }

        .spice-level {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.8rem;
            background: var(--cream);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        .kit-actions {
            display: flex;
            gap: 1rem;
        }

        .btn-outline {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
            flex: 1;
        }

        .btn-outline:hover {
            background: var(--curry);
            color: var(--white);
        }

        /* How It Works Section */
        .how-it-works {
            padding: 5rem 2rem;
            background: var(--cream);
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .step-card {
            text-align: center;
            padding: 2rem;
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }

        .step-number {
            width: 80px;
            height: 80px;
            background: var(--curry);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
            margin: 0 auto 1.5rem;
        }

        .step-title {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .step-description {
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        /* Features Section */
        .features-section {
            padding: 5rem 2rem;
            background: var(--white);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .feature-card {
            text-align: center;
            padding: 2rem;
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: var(--cream);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .feature-title {
            font-size: 1.2rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .feature-description {
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .promo-banner {
                font-size: 12px;
                padding: 6px 15px;
            }
            
            .navbar {
                top: 32px;
            }
            
            .hero-section {
                padding-top: 100px;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }

            .hero-content p {
                font-size: 1rem;
            }

            .hero-cta {
                flex-direction: column;
                align-items: center;
            }

            .nav-links {
                display: none;
            }

            .meal-kits-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .kit-actions {
                flex-direction: column;
            }

            .steps-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .kit-content {
                padding: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Promotional Banner -->
    <div class="promo-banner" id="promoBanner">
        <div class="promo-banner-content">
            <span class="promo-icon">🍳</span>
            <span class="promo-text">Free Shipping on All Meal Kits + Bonus Recipe Book</span>
            <span class="promo-icon">📚</span>
        </div>
        <button class="promo-close" onclick="closePromoBanner()" title="Close">×</button>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; max-width: 1200px; margin: 0 auto; width: 100%;">
            <a href="home2.php" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Krua Thai" style="height: 50px; width: auto;">
                <span class="logo-text">Krua Thai</span>
            </a>
            
            <ul class="nav-links">
                <li><a href="./menus.php">Ready Meals</a></li>
                <li><a href="./meal-kit.php" class="active">Meal Kits</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
            
            <div class="nav-actions">
                <?php if ($is_logged_in): ?>
                    <a href="cart.php" class="btn btn-secondary">
                        🛒 Cart 
                        <?php
// คำนวณ cart count แบบเดียวกับ cart.php
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += isset($item['quantity']) ? intval($item['quantity']) : 1;
    }
}
?>
<span class="cart-counter" id="cartCounter" style="background: var(--curry); color: var(--white); border-radius: 50%; padding: 2px 6px; font-size: 0.8rem; margin-left: 5px; <?php echo $cart_count > 0 ? 'display: inline-block;' : 'display: none;'; ?>">
    <?php echo $cart_count; ?>
</span>
                    </a>
                    <a href="dashboard.php" class="btn btn-primary">My Account</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary">Sign In</a>
                    <a href="register.php" class="btn btn-primary">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="hero-container">
            <div class="hero-content">
                <h1>Thai Cooking Made Simple</h1>
                <p>Bring the authentic flavors of Thailand to your kitchen. Our meal kits include everything you need - pre-made sauces, fresh ingredients, and easy-to-follow recipes.</p>
                
                <div class="hero-cta">
                    <a href="#meal-kits" class="btn btn-primary">Shop Meal Kits</a>
                    <a href="#how-it-works" class="btn btn-secondary">How It Works</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Meal Kits Section -->
    <section class="meal-kits-section" id="meal-kits">
        <div class="section-container">
            <div class="section-header">
                <h2 class="section-title">Featured Meal Kits</h2>
                <p class="section-subtitle">Restaurant-quality Thai dishes, ready to cook at home in under 30 minutes</p>
            </div>

            <div class="meal-kits-grid">
                <?php foreach ($meal_kits as $kit): ?>
                    <div class="meal-kit-card" onclick="viewKitDetails('<?php echo htmlspecialchars($kit['id']); ?>')">
                        <div class="kit-image">
                            <?php if (isset($kit['main_image_url']) && file_exists($kit['main_image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($kit['main_image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($kit['name']); ?>">
                            <?php else: ?>
                                🍛
                            <?php endif; ?>
                        </div>
                        
                        <div class="kit-content">
                            <div class="kit-header">
                                <div>
                                    <h3 class="kit-title"><?php echo htmlspecialchars($kit['name']); ?></h3>
                                    <?php if (isset($kit['spice_level'])): ?>
                                        <span class="spice-level">
                                            🌶️ <?php echo htmlspecialchars($kit['spice_level']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="kit-price">$<?php echo number_format($kit['base_price'], 2); ?></div>
                            </div>
                            
                            <p class="kit-description">
                                <?php echo htmlspecialchars($kit['description']); ?>
                            </p>
                            
                            <div class="kit-details">
                                <?php if (isset($kit['prep_time'])): ?>
                                    <div class="detail-item">
                                        <svg class="detail-icon" viewBox="0 0 24 24">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm1-13h-2v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                                        </svg>
                                        <span><?php echo $kit['prep_time']; ?> mins</span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($kit['serves'])): ?>
                                    <div class="detail-item">
                                        <svg class="detail-icon" viewBox="0 0 24 24">
                                            <path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2c0 1.11-.89 2-2 2s-2-.89-2-2zM9 2C7.89 2 7 2.89 7 4s.89 2 2 2 2-.89 2-2-.89-2-2-2zM6 6c-1.3 0-2.26.84-2.82 2.06C2.76 8.83 2.76 9.96 3.24 10.76L5 13h2.5l1.5-2.5L10.5 13H13l1.74-2.24c.48-.8.48-1.93.06-2.7C14.26 6.84 13.3 6 12 6z"/>
                                        </svg>
                                        <span><?php echo htmlspecialchars($kit['serves']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($kit['difficulty'])): ?>
                                    <div class="detail-item">
                                        <svg class="detail-icon" viewBox="0 0 24 24">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                        <span><?php echo htmlspecialchars($kit['difficulty']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="kit-actions">
                                <?php if ($is_logged_in): ?>
                                    <button class="btn btn-outline" onclick="addToCart('<?php echo htmlspecialchars($kit['id']); ?>')">
                                        Add to Cart
                                    </button>
                                    <button class="btn btn-primary" onclick="quickOrder('<?php echo htmlspecialchars($kit['id']); ?>')">
                                        Order Now
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-outline" onclick="addToCart('<?php echo htmlspecialchars($kit['id']); ?>')">
                                        Select Kit
                                    </button>
                                    <button class="btn btn-primary" onclick="quickOrder('<?php echo htmlspecialchars($kit['id']); ?>')">
                                        Order Now
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works" id="how-it-works">
        <div class="section-container">
            <div class="section-header">
                <h2 class="section-title">How Our Meal Kits Work</h2>
                <p class="section-subtitle">From our kitchen to yours in 4 simple steps</p>
            </div>

            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3 class="step-title">Choose Your Kit</h3>
                    <p class="step-description">Browse our selection of authentic Thai meal kits, each designed to serve 2-3 people and ready in under 30 minutes.</p>
                </div>

                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3 class="step-title">We Prep Everything</h3>
                    <p class="step-description">Our chefs prepare authentic sauces, portion fresh ingredients, and include step-by-step recipe cards with photos.</p>
                </div>

                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3 class="step-title">Fast Delivery</h3>
                    <p class="step-description">Receive your meal kit within 24-48 hours, packed with ice to ensure freshness and quality.</p>
                </div>

                <div class="step-card">
                    <div class="step-number">4</div>
                    <h3 class="step-title">Cook & Enjoy</h3>
                    <p class="step-description">Follow our easy instructions to create restaurant-quality Thai food in your own kitchen. No experience needed!</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="section-container">
            <div class="section-header">
                <h2 class="section-title">Why Choose Our Meal Kits?</h2>
                <p class="section-subtitle">The authentic Thai experience, simplified for home cooking</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🌿</div>
                    <h3 class="feature-title">Fresh Ingredients</h3>
                    <p class="feature-description">We source authentic Thai ingredients and fresh produce daily, ensuring maximum flavor and quality.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">👨‍🍳</div>
                    <h3 class="feature-title">Chef-Made Sauces</h3>
                    <p class="feature-description">Our Thai chefs prepare traditional curry pastes and sauces using family recipes passed down through generations.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">📖</div>
                    <h3 class="feature-title">Easy Instructions</h3>
                    <p class="feature-description">Step-by-step photo guides make cooking Thai food simple, even for beginners. No culinary experience required.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">🚚</div>
                    <h3 class="feature-title">Fast Delivery</h3>
                    <p class="feature-description">Order today, cook tomorrow. All meal kits arrive fresh with next-day delivery in most areas.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">💰</div>
                    <h3 class="feature-title">Great Value</h3>
                    <p class="feature-description">Each kit serves 2-3 people for less than the cost of dining out, with restaurant-quality results.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">♻️</div>
                    <h3 class="feature-title">Eco-Friendly</h3>
                    <p class="feature-description">All packaging is recyclable or compostable, and we minimize food waste with perfectly portioned ingredients.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="hero-section" style="min-height: 40vh; background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%); color: var(--white);">
        <div class="hero-container">
            <div class="hero-content">
                <h2 style="color: var(--white); margin-bottom: 1rem;">Ready to Start Cooking?</h2>
                <p style="color: rgba(255,255,255,0.9); margin-bottom: 2rem;">Join thousands of home cooks who've discovered the joy of authentic Thai cooking with our meal kits.</p>
                
                <div class="hero-cta">
                    <a href="#meal-kits" class="btn" style="background: var(--white); color: var(--curry);">Shop Now</a>
                    <a href="tel:+1-555-THAI-KIT" class="btn btn-secondary" style="border-color: var(--white); color: var(--white);">Call Us</a>
                </div>
            </div>
        </div>
    </section>


<script>

// =================================================================
// NOTIFICATION SYSTEM
// =================================================================

function showNotification(message, type = 'success') {
    // Remove existing notification
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Add styles if not already added
    if (!document.getElementById('notification-styles')) {
        const styles = document.createElement('style');
        styles.id = 'notification-styles';
        styles.textContent = `
            .notification {
                position: fixed;
                top: 100px;
                right: 20px;
                z-index: 9999;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                min-width: 300px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                transform: translateX(100%);
                transition: transform 0.3s ease;
                font-family: 'BaticaSans', sans-serif;
            }
            .notification-success {
                background: linear-gradient(135deg, #28a745, #20c997);
            }
            .notification-error {
                background: linear-gradient(135deg, #dc3545, #fd7e14);
            }
            .notification-content {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .notification-icon {
                font-size: 18px;
            }
            .notification.show {
                transform: translateX(0);
            }
        `;
        document.head.appendChild(styles);
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-icon">${type === 'success' ? '✅' : '❌'}</span>
            <span class="notification-message">${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => notification.classList.add('show'), 100);
    
    // Auto hide after 4 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}
    
// =================================================================
// CART FUNCTIONS - EXISTING (แก้ไขให้ใช้ syncCartFromServer)
// =================================================================
function updateCartCounter(count) {
    const cartCounter = document.querySelector('.cart-counter');
    if (cartCounter) {
        cartCounter.textContent = count;
        cartCounter.style.display = count > 0 ? 'inline-block' : 'none';
        
        // Animation
        cartCounter.style.transform = 'scale(1.3)';
        cartCounter.style.background = '#28a745';
        
        setTimeout(() => {
            cartCounter.style.transform = 'scale(1)';
            cartCounter.style.background = 'var(--curry)';
        }, 300);
    }
    
    // Sync with server after update
    setTimeout(() => {
        syncCartFromServer();
    }, 1000);
}

// =================================================================
// 🛒 CART AUTO-SYNC SYSTEM - เพิ่มใหม่
// =================================================================

/**
 * Sync cart counter from server
 */
function syncCartFromServer() {
    fetch('ajax/sync_cart.php', {
        method: 'GET',
        headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const cartCounter = document.querySelector('.cart-counter');
            if (cartCounter) {
                // Update counter display
                cartCounter.textContent = data.count;
                
                if (data.count > 0) {
                    cartCounter.style.display = 'inline-block';
                    cartCounter.classList.add('has-items');
                } else {
                    cartCounter.style.display = 'none';
                    cartCounter.classList.remove('has-items');
                }
            }
            
            // Update any other cart displays on the page
            const cartTotalElements = document.querySelectorAll('.cart-total');
            cartTotalElements.forEach(element => {
                element.textContent = `$${data.formatted_total}`;
            });
            
            // Update cart icon badge if exists
            const cartBadge = document.querySelector('.cart-badge');
            if (cartBadge) {
                cartBadge.textContent = data.count;
                cartBadge.style.display = data.count > 0 ? 'inline-block' : 'none';
            }
            
            console.log('✅ Cart synced:', data);
            return data;
        } else {
            console.warn('⚠️ Cart sync failed:', data);
            return null;
        }
    })
    .catch(error => {
        console.error('❌ Error syncing cart:', error);
        // Don't show error to user for background sync
        return null;
    });
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.toast-notification');
    existingToasts.forEach(toast => toast.remove());
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 9999;
        transform: translateX(100%);
        transition: transform 0.3s ease-in-out;
        max-width: 300px;
        font-family: 'BaticaSans', sans-serif;
    `;
    
    const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
    toast.innerHTML = `${icon} ${message}`;
    
    document.body.appendChild(toast);
    
    // Slide in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Slide out and remove
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Initialize cart sync system
 */
function initCartSync() {
    // Load existing cart count on page load
    <?php if ($is_logged_in && isset($_SESSION['cart_summary']['count'])): ?>
        updateCartCounter(<?php echo $_SESSION['cart_summary']['count']; ?>);
    <?php endif; ?>
    
    // Initial sync
    syncCartFromServer();
    
    // Periodic sync every 30 seconds
    setInterval(syncCartFromServer, 30000);
    
    // Sync when page becomes visible (user switches back to tab)
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            syncCartFromServer();
        }
    });
    
    // Sync when user clicks on cart-related elements
    document.addEventListener('click', (e) => {
        if (e.target.closest('.cart-counter') || 
            e.target.closest('a[href="cart.php"]') ||
            e.target.closest('.cart-link')) {
            syncCartFromServer();
        }
    });
    
    // Handle return from login with add_kit parameter
    const urlParams = new URLSearchParams(window.location.search);
    const addKit = urlParams.get('add_kit');
    if (addKit) {
        setTimeout(() => {
            addToCart(addKit);
            // Clean URL
            window.history.replaceState({}, '', window.location.pathname);
        }, 500);
    }
    
    console.log('🛒 Cart Auto-Sync System Initialized');
}

// =================================================================
// ADD TO CART FUNCTION - ปรับปรุงให้ใช้ Auto-Sync
// =================================================================

function addToCart(kitId, event) {
    // Prevent event bubbling
    if (event) {
        event.stopPropagation();
    }
    
    const button = event ? event.target : null;
    const originalText = button ? button.textContent : '';
    
    // Show loading state
    if (button) {
        button.textContent = 'Adding...';
        button.disabled = true;
        button.style.opacity = '0.7';
    }
    
    <?php if ($is_logged_in): ?>
        // Logged in user - AJAX call
        fetch('ajax/add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                kit_id: kitId,
                quantity: 1,
                type: 'meal_kit'
            })
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text(); // Get as text first
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed data:', data);
                
                if (data.success) {
                    // Success handling
                    if (button) {
                        button.textContent = '✅ Added!';
                        button.style.background = 'var(--sage)';
                        button.style.color = 'var(--white)';
                        button.style.opacity = '1';
                    }
                    
                    showNotification(`${data.item_added.name} added to cart!`, 'success');
                    updateCartCounter(data.cart_count);
                    
                    // Reset button after 2 seconds
                    setTimeout(() => {
                        if (button) {
                            button.textContent = originalText;
                            button.style.background = '';
                            button.style.color = '';
                            button.disabled = false;
                            button.style.opacity = '1';
                        }
                    }, 2000);
                    
                } else {
                    throw new Error(data.message || 'Unknown error occurred');
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Raw response was:', text);
                throw new Error('Invalid response from server');
            }
        })
        .catch(error => {
            console.error('Add to cart error:', error);
            showNotification('Error: ' + error.message, 'error');
            
            // Reset button on error
            if (button) {
                button.textContent = originalText;
                button.disabled = false;
                button.style.opacity = '1';
            }
        });
    <?php else: ?>
        // Guest user - redirect to login
        showNotification('Please log in to add items to cart', 'error');
        
        setTimeout(() => {
            const returnUrl = encodeURIComponent(window.location.pathname + '?add_kit=' + kitId);
            window.location.href = 'login.php?return=' + returnUrl;
        }, 1500);
        
        if (button) {
            button.textContent = originalText;
            button.disabled = false;
            button.style.opacity = '1';
        }
    <?php endif; ?>
}

// =================================================================
// KIT DETAILS MODAL - EXISTING (ไม่ต้องแก้ไข)
// =================================================================

function viewKitDetails(kitId) {
    console.log('Opening details for kit:', kitId);
    
    // Sample kit details data
    const kitDetailsData = {
        'green-curry-kit': {
            name: 'Green Curry Kit',
            price: '$18.99',
            description: 'Authentic Thai Green Curry with fresh ingredients',
            details: ['Serves 2-3 people', '25 minutes prep time', 'Medium spice level']
        },
        'panang-kit': {
            name: 'Panang Curry Kit', 
            price: '$21.99',
            description: 'Rich and creamy Panang curry with tender beef',
            details: ['Serves 2-3 people', '30 minutes prep time', 'Mild spice level']
        },
        'pad-thai-kit': {
            name: 'Pad Thai Kit',
            price: '$16.99', 
            description: 'Classic Thai noodle dish made simple',
            details: ['Serves 2 people', '20 minutes prep time', 'Mild spice level']
        },
        'tom-yum-kit': {
            name: 'Tom Yum Soup Kit',
            price: '$17.99',
            description: 'Hot and sour soup with fresh shrimp',
            details: ['Serves 2-3 people', '15 minutes prep time', 'Hot spice level']
        }
    };
    
    const kit = kitDetailsData[kitId];
    if (!kit) {
        showNotification('Kit details not available', 'error');
        return;
    }
    
    // Create modal content
    const modalHtml = `
        <div style="max-width: 450px; background: white; color: var(--text-dark); border-radius: 12px; overflow: hidden;">
            <div style="padding: 20px; border-bottom: 1px solid #eee;">
                <h3 style="margin: 0 0 10px 0; color: var(--curry); font-size: 1.5rem;">${kit.name}</h3>
                <p style="margin: 0 0 10px 0; font-size: 1.3rem; font-weight: bold; color: var(--curry);">${kit.price}</p>
                <p style="margin: 0; line-height: 1.5; color: var(--text-gray);">${kit.description}</p>
            </div>
            <div style="padding: 20px;">
                <h4 style="margin: 0 0 10px 0; color: var(--text-dark);">What's Included:</h4>
                <ul style="margin: 0 0 20px 0; padding-left: 20px;">
                    ${kit.details.map(detail => `<li style="margin-bottom: 5px;">${detail}</li>`).join('')}
                </ul>
                <div style="display: flex; gap: 10px;">
                    <button onclick="addToCart('${kitId}'); closeModal();" style="
                        background: var(--curry); 
                        color: white; 
                        border: none; 
                        padding: 12px 20px; 
                        border-radius: 25px; 
                        cursor: pointer;
                        font-family: 'BaticaSans', sans-serif;
                        font-weight: 600;
                        flex: 1;
                    ">Add to Cart</button>
                    <button onclick="closeModal()" style="
                        background: transparent; 
                        color: var(--curry); 
                        border: 2px solid var(--curry); 
                        padding: 12px 20px; 
                        border-radius: 25px; 
                        cursor: pointer;
                        font-family: 'BaticaSans', sans-serif;
                        font-weight: 600;
                    ">Close</button>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal
    closeModal();
    
    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.id = 'kitModal';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    
    overlay.innerHTML = modalHtml;
    
    // Close on overlay click
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closeModal();
        }
    });
    
    document.body.appendChild(overlay);
    
    // Show modal
    setTimeout(() => {
        overlay.style.opacity = '1';
    }, 10);
}

function closeModal() {
    const modal = document.getElementById('kitModal');
    if (modal) {
        modal.style.opacity = '0';
        setTimeout(() => modal.remove(), 300);
    }
}

// =================================================================
// QUICK ORDER - EXISTING (ไม่ต้องแก้ไข)
// =================================================================

function quickOrder(kitId) {
    <?php if ($is_logged_in): ?>
        if (confirm('Proceed to checkout for this meal kit?')) {
            window.location.href = 'checkout.php?kit=' + kitId + '&action=quick_order';
        }
    <?php else: ?>
        if (confirm('You will need to provide delivery details. Continue?')) {
            sessionStorage.setItem('selected_kit', kitId);
            sessionStorage.setItem('checkout_action', 'quick_order');
            window.location.href = 'guest-checkout.php?kit=' + kitId + '&action=quick_order';
        }
    <?php endif; ?>
}

// =================================================================
// UTILITY FUNCTIONS - EXISTING (ไม่ต้องแก้ไข)
// =================================================================

function closePromoBanner() {
    const promoBanner = document.getElementById('promoBanner');
    const navbar = document.querySelector('.navbar');
    
    promoBanner.style.transform = 'translateY(-100%)';
    promoBanner.style.opacity = '0';
    
    setTimeout(() => {
        promoBanner.style.display = 'none';
        navbar.style.top = '0';
    }, 300);
}

// =================================================================
// EVENT LISTENERS - ปรับปรุงให้รวม Auto-Sync
// =================================================================

// Smooth scrolling for navigation links
document.addEventListener('DOMContentLoaded', function() {
    // 🛒 Initialize cart sync system
    initCartSync();
    
    // Smooth scrolling
    const navLinks = document.querySelectorAll('a[href^="#"]');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetSection = document.querySelector(targetId);
            
            if (targetSection) {
                targetSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Card animation on scroll
    const kitCards = document.querySelectorAll('.meal-kit-card');
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

    kitCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });
});

// Navbar background on scroll
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 100) {
        navbar.style.background = 'rgba(255, 255, 255, 0.98)';
    } else {
        navbar.style.background = 'rgba(255, 255, 255, 0.95)';
    }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// =================================================================
// CSS Styles for enhanced cart counter
// =================================================================
const style = document.createElement('style');
style.textContent = `
    .cart-counter {
        animation: pulse 2s infinite;
    }
    
    .cart-counter.has-items {
        background: var(--curry) !important;
        color: white;
        font-weight: bold;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .toast-notification {
        font-size: 14px;
        line-height: 1.4;
    }
`;
document.head.appendChild(style);

</script>
    
</body>
</html>