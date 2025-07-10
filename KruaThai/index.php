<?php
/**
 * Krua Thai - Dashboard (Main Page After Login)
 * File: index.php
 * Description: Main dashboard for logged-in users - meal ordering, subscriptions, profile management
 */

session_start();

// Redirect to public homepage if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}

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

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'User';
$user_role = $_SESSION['user_role'] ?? 'customer';

// Get user information
$user_info = null;
if ($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to fetch user info: " . $e->getMessage());
    }
}

// Get active subscription
$active_subscription = null;
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT s.*, sp.name as plan_name, sp.name_thai as plan_name_thai, sp.meals_per_week
            FROM subscriptions s 
            JOIN subscription_plans sp ON s.plan_id = sp.id
            WHERE s.user_id = ? AND s.status IN ('active', 'paused') 
            ORDER BY s.created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $active_subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to fetch subscription: " . $e->getMessage());
    }
}

// Get recent orders
$recent_orders = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT o.*, COUNT(oi.id) as item_count
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to fetch orders: " . $e->getMessage());
    }
}

// Get featured meals for ordering
$featured_meals = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT m.*, mc.name as category_name, mc.name_thai as category_name_thai
            FROM menus m 
            LEFT JOIN menu_categories mc ON m.category_id = mc.id 
            WHERE m.is_featured = 1 AND m.is_available = 1 
            ORDER BY m.created_at DESC 
            LIMIT 4
        ");
        $stmt->execute();
        $featured_meals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to fetch featured meals: " . $e->getMessage());
    }
}

// Get available subscription plans
$subscription_plans = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM subscription_plans 
            WHERE is_active = 1 AND plan_type = 'weekly' 
            AND meals_per_week IN (4, 8, 12, 15)
            ORDER BY sort_order ASC, meals_per_week ASC
        ");
        $stmt->execute();
        $subscription_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to fetch subscription plans: " . $e->getMessage());
    }
}

// Get notifications
$notifications = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = 0
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to fetch notifications: " . $e->getMessage());
    }
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Krua Thai | Authentic Thai Food for Health</title>
    <meta name="description" content="Manage your subscription, order meals, and track your delivery status">
    
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
            font-family: 'Sarabun', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: #f8f9fa;
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

        .user-menu {
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .notifications {
            position: relative;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: var(--transition);
        }

        .notifications:hover {
            background: var(--cream);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--danger);
            color: var(--white);
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            transition: var(--transition);
        }

        .user-profile:hover {
            background: var(--cream);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--sage), var(--brown));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 600;
        }

        .user-info h4 {
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 0.2rem;
        }

        .user-info span {
            color: var(--text-gray);
            font-size: 0.8rem;
        }

        .dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-medium);
            min-width: 200px;
            padding: 1rem 0;
            display: none;
            z-index: 1001;
        }

        .dropdown.show {
            display: block;
        }

        .dropdown a {
            display: block;
            padding: 0.8rem 1.5rem;
            color: var(--text-dark);
            text-decoration: none;
            transition: var(--transition);
        }

        .dropdown a:hover {
            background: var(--cream);
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
            margin-top: 80px;
            display: none;
        }

        .flash-message.show {
            display: block;
        }

        .flash-message.success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
            border-bottom: 2px solid var(--success);
        }

        .flash-message.error {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border-bottom: 2px solid var(--danger);
        }

        .flash-message.info {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
            border-bottom: 2px solid var(--info);
        }

        /* Main Content */
        .main-content {
            padding: 6rem 2rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .dashboard-header {
            margin-bottom: 3rem;
        }

        .welcome-section {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 3rem;
        }

        .welcome-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 2rem;
        }

        .welcome-text h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .welcome-text p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .quick-stats {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .dashboard-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            border: 1px solid var(--border-light);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .card-action {
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .card-action:hover {
            color: var(--brown);
        }

        /* Subscription Status */
        .subscription-status {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-paused {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .subscription-info {
            margin-bottom: 1rem;
        }

        .subscription-info h4 {
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .subscription-info p {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* Order History */
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-info h5 {
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 0.3rem;
        }

        .order-info p {
            color: var(--text-gray);
            font-size: 0.8rem;
        }

        .order-status {
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status-delivered {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status-preparing {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .status-pending {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }

        /* Featured Meals Grid */
        .featured-meals {
            grid-column: 1 / -1;
        }

        .meals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
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
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .meal-image {
            height: 150px;
            background: linear-gradient(135deg, var(--cream), #e6d2a8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .meal-info {
            padding: 1rem;
        }

        .meal-category {
            font-size: 0.7rem;
            color: var(--brown);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.3rem;
        }

        .meal-info h4 {
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 1rem;
            font-weight: 700;
        }

        .meal-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--curry);
            margin-bottom: 0.8rem;
        }

        /* Buttons */
        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-align: center;
            justify-content: center;
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
            font-size: 0.8rem;
        }

        /* Subscription Plans Grid */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .plan-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow-soft);
            border: 2px solid transparent;
            transition: var(--transition);
            position: relative;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .plan-card.featured {
            border-color: var(--curry);
        }

        .plan-card.featured::before {
            content: "Popular";
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--curry);
            color: var(--white);
            padding: 0.3rem 1rem;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .plan-name {
            font-size: 1.1rem;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .plan-price {
            font-size: 1.8rem;
            color: var(--curry);
            margin-bottom: 0.3rem;
            font-weight: 700;
        }

        .plan-price span {
            font-size: 0.8rem;
            color: var(--text-gray);
            font-weight: 500;
        }

        .plan-meals {
            color: var(--text-gray);
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        /* Notifications */
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1rem;
            flex-shrink: 0;
        }

        .notification-delivery {
            background: var(--info);
        }

        .notification-payment {
            background: var(--success);
        }

        .notification-system {
            background: var(--warning);
        }

        .notification-content h5 {
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
        }

        .notification-content p {
            color: var(--text-gray);
            font-size: 0.8rem;
            line-height: 1.4;
        }

        .notification-time {
            font-size: 0.7rem;
            color: var(--text-gray);
            margin-top: 0.3rem;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h4 {
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .empty-state p {
            margin-bottom: 1rem;
            font-size: 0.9rem;
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

            .main-content {
                padding: 5rem 1rem 2rem;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .welcome-content {
                flex-direction: column;
                text-align: center;
            }

            .quick-stats {
                justify-content: center;
            }

            .meals-grid,
            .plans-grid {
                grid-template-columns: 1fr;
            }

            .user-info {
                display: none;
            }
        }

        /* Loading Animation */
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
    <header>
        <nav>
            <a href="index.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-utensils"></i>
                </div>
                <span class="logo-text">Krua Thai</span>
            </a>

            <ul class="nav-links" id="navLinks">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="menus.php">All Menus</a></li>
                <li><a href="meal-selection.php">Order Food</a></li>
                <?php if ($active_subscription): ?>
                    <li><a href="meal-planner.php">Meal Planner</a></li>
                <?php endif; ?>
                <li><a href="order-history.php">Order History</a></li>
            </ul>

            <div class="user-menu">
                <!-- Notifications -->
                <div class="notifications" onclick="toggleNotifications()">
                    <i class="fas fa-bell" style="font-size: 1.2rem; color: var(--text-gray);"></i>
                    <?php if (count($notifications) > 0): ?>
                        <span class="notification-badge"><?php echo count($notifications); ?></span>
                    <?php endif; ?>
                </div>

                <!-- User Profile -->
                <div class="user-profile" onclick="toggleUserMenu()">
                    <div class="user-avatar">
                        <?php echo mb_substr($user_name, 0, 1, 'UTF-8'); ?>
                    </div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($user_name); ?></h4>
                        <span><?php echo $user_role === 'admin' ? 'Administrator' : 'Member'; ?></span>
                    </div>
                    <i class="fas fa-chevron-down" style="font-size: 0.8rem; color: var(--text-gray);"></i>
                </div>

                <!-- User Dropdown -->
                <div class="dropdown" id="userDropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="subscription-status.php"><i class="fas fa-calendar-alt"></i> Subscription</a>
                    <a href="order-history.php"><i class="fas fa-history"></i> Order History</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <?php if ($user_role === 'admin'): ?>
                        <a href="admin/dashboard.php"><i class="fas fa-shield-alt"></i> Admin Panel</a>
                    <?php endif; ?>
                    <hr style="margin: 0.5rem 0; border: none; border-top: 1px solid var(--border-light);">
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <button class="mobile-menu-toggle" id="mobileMenuToggle">
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

    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Section -->
        <section class="welcome-section loading">
            <div class="welcome-content">
                <div class="welcome-text">
                    <h1>Hello, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
                    <p>Welcome to Krua Thai - Authentic Thai food for your health, delivered to your door</p>
                </div>
                <div class="quick-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($recent_orders); ?></div>
                        <div class="stat-label">Recent Orders</div>
                    </div>
                    <?php if ($active_subscription): ?>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $active_subscription['meals_per_week']; ?></div>
                            <div class="stat-label">Meals/Week</div>
                        </div>
                    <?php endif; ?>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($notifications); ?></div>
                        <div class="stat-label">Notifications</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Current Subscription -->
            <div class="dashboard-card loading">
                <div class="card-header">
                    <h3 class="card-title">Current Subscription</h3>
                    <?php if ($active_subscription): ?>
                        <a href="subscription-status.php" class="card-action">Manage</a>
                    <?php endif; ?>
                </div>
                
                <?php if ($active_subscription): ?>
                    <div class="subscription-status">
                        <span class="status-badge status-<?php echo $active_subscription['status']; ?>">
                            <?php echo $active_subscription['status'] === 'active' ? 'Active' : 'Paused'; ?>
                        </span>
                    </div>
                    <div class="subscription-info">
                        <h4><?php echo htmlspecialchars($active_subscription['plan_name'] ?? $active_subscription['plan_name_thai']); ?></h4>
                        <p><?php echo $active_subscription['meals_per_week']; ?> meals per week</p>
                        <?php if ($active_subscription['next_billing_date']): ?>
                            <p>Next billing: <?php echo date('m/d/Y', strtotime($active_subscription['next_billing_date'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                        <a href="meal-planner.php" class="btn btn-primary btn-sm">Plan Meals</a>
                        <a href="subscription-status.php" class="btn btn-secondary btn-sm">Manage</a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-plus"></i>
                        <h4>No Active Subscription</h4>
                        <p>Start your healthy eating journey today</p>
                        <a href="subscribe.php" class="btn btn-primary btn-sm">Choose Plan</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Orders -->
            <div class="dashboard-card loading">
                <div class="card-header">
                    <h3 class="card-title">Recent Orders</h3>
                    <a href="order-history.php" class="card-action">View All</a>
                </div>
                
                <?php if (!empty($recent_orders)): ?>
                    <?php foreach (array_slice($recent_orders, 0, 3) as $order): ?>
                        <div class="order-item">
                            <div class="order-info">
                                <h5>Order #<?php echo htmlspecialchars($order['order_number']); ?></h5>
                                <p><?php echo $order['item_count']; ?> items • <?php echo date('m/d/Y', strtotime($order['created_at'])); ?></p>
                            </div>
                            <span class="order-status status-<?php echo $order['status']; ?>">
                                <?php 
                                $status_text = [
                                    'pending' => 'Pending',
                                    'confirmed' => 'Confirmed',
                                    'preparing' => 'Preparing',
                                    'ready' => 'Ready',
                                    'out_for_delivery' => 'Out for Delivery',
                                    'delivered' => 'Delivered',
                                    'cancelled' => 'Cancelled'
                                ];
                                echo $status_text[$order['status']] ?? ucfirst($order['status']);
                                ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h4>No Orders Yet</h4>
                        <p>Start ordering delicious meals today</p>
                        <a href="menus.php" class="btn btn-primary btn-sm">View Menu</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Order - Featured Meals -->
            <div class="dashboard-card featured-meals loading">
                <div class="card-header">
                    <h3 class="card-title">Featured Meals - Quick Order</h3>
                    <a href="menus.php" class="card-action">View All</a>
                </div>
                
                <?php if (!empty($featured_meals)): ?>
                    <div class="meals-grid">
                        <?php foreach ($featured_meals as $meal): ?>
                            <div class="meal-card">
                                <div class="meal-image">
                                    <?php if (isset($meal['main_image_url']) && $meal['main_image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($meal['main_image_url']); ?>" alt="<?php echo htmlspecialchars($meal['name'] ?? $meal['name_thai']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="text-align: center;">
                                            <i class="fas fa-utensils" style="font-size: 1.5rem; margin-bottom: 0.3rem; opacity: 0.5;"></i>
                                            <br><?php echo htmlspecialchars($meal['name'] ?? $meal['name_thai']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="meal-info">
                                    <?php if (!empty($meal['category_name'])): ?>
                                        <div class="meal-category"><?php echo htmlspecialchars($meal['category_name']); ?></div>
                                    <?php endif; ?>
                                    <h4><?php echo htmlspecialchars($meal['name'] ?? $meal['name_thai']); ?></h4>
                                    <div class="meal-price">$<?php echo number_format($meal['base_price'], 2); ?></div>
                                    <a href="meal-selection.php?single=<?php echo $meal['id']; ?>" class="btn btn-primary btn-sm" style="width: 100%;">Order Now</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-utensils"></i>
                        <h4>No Featured Meals</h4>
                        <p>Menu items are being prepared</p>
                        <a href="menus.php" class="btn btn-primary btn-sm">View All Menus</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Subscription Plans (if no active subscription) -->
        <?php if (!$active_subscription && !empty($subscription_plans)): ?>
            <section id="subscription-plans" class="dashboard-card loading">
                <div class="card-header">
                    <h3 class="card-title">Choose Your Subscription Plan</h3>
                    <span class="card-action" style="color: var(--text-gray);">Starting from $29.99/week</span>
                </div>
                
                <div class="plans-grid">
                    <?php foreach ($subscription_plans as $plan): ?>
                        <div class="plan-card <?php echo $plan['is_popular'] ? 'featured' : ''; ?>">
                            <h4 class="plan-name"><?php echo htmlspecialchars($plan['name'] ?? $plan['name_thai']); ?></h4>
                            <div class="plan-price">
                                $<?php echo number_format($plan['final_price'], 2); ?>
                                <span>/week</span>
                            </div>
                            <div class="plan-meals"><?php echo $plan['meals_per_week']; ?> meals per week</div>
                            <a href="meal-selection.php?plan=<?php echo $plan['id']; ?>" class="btn btn-primary btn-sm">Choose This Plan</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Notifications Panel -->
        <?php if (!empty($notifications)): ?>
            <section class="dashboard-card loading">
                <div class="card-header">
                    <h3 class="card-title">Notifications</h3>
                    <a href="notifications.php" class="card-action">View All</a>
                </div>
                
                <?php foreach (array_slice($notifications, 0, 3) as $notification): ?>
                    <div class="notification-item">
                        <div class="notification-icon notification-<?php echo $notification['type']; ?>">
                            <?php
                            $icons = [
                                'order_update' => 'fas fa-shopping-cart',
                                'delivery' => 'fas fa-truck',
                                'payment' => 'fas fa-credit-card',
                                'promotion' => 'fas fa-tag',
                                'system' => 'fas fa-info-circle',
                                'review_reminder' => 'fas fa-star'
                            ];
                            $icon = $icons[$notification['type']] ?? 'fas fa-bell';
                            ?>
                            <i class="<?php echo $icon; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <h5><?php echo htmlspecialchars($notification['title']); ?></h5>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="notification-time">
                                <?php echo timeAgo($notification['created_at']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <script>
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const navLinks = document.getElementById('navLinks');

        mobileMenuToggle.addEventListener('click', function() {
            navLinks.classList.toggle('active');
            this.textContent = navLinks.classList.contains('active') ? '✕' : '☰';
        });

        // User menu toggle
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        // Notifications toggle
        function toggleNotifications() {
            // Simple alert for now - you can implement a proper notifications panel
            <?php if (!empty($notifications)): ?>
                alert('You have <?php echo count($notifications); ?> notifications');
            <?php else: ?>
                alert('No new notifications');
            <?php endif; ?>
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-menu')) {
                document.getElementById('userDropdown').classList.remove('show');
            }
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
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

        // Card hover effects
        document.querySelectorAll('.meal-card, .plan-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Dashboard analytics
        function trackDashboardAction(action, element) {
            console.log(`Dashboard action: ${action} on ${element}`);
            // Add analytics tracking here
        }

        // Track clicks on dashboard elements
        document.querySelectorAll('.btn, .card-action').forEach(element => {
            element.addEventListener('click', function() {
                trackDashboardAction('click', this.textContent.trim());
            });
        });

        // Check for updates periodically
        function checkForUpdates() {
            // Implement real-time updates for orders, notifications, etc.
            console.log('Checking for updates...');
        }

        // Check for updates every 30 seconds
        setInterval(checkForUpdates, 30000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'm':
                        e.preventDefault();
                        window.location.href = 'menus.php';
                        break;
                    case 'o':
                        e.preventDefault();
                        window.location.href = 'order-history.php';
                        break;
                    case 'p':
                        e.preventDefault();
                        window.location.href = 'profile.php';
                        break;
                }
            }
            
            if (e.key === 'Escape') {
                document.getElementById('userDropdown').classList.remove('show');
                navLinks.classList.remove('active');
                mobileMenuToggle.textContent = '☰';
            }
        });
    </script>
</body>
</html>

<?php
// Helper function for time ago
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('m/d/Y', strtotime($datetime));
}
?>