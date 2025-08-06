<?php
/**
 * Dashboard with Error Handling - Updated with Somdul Table Theme
 * File: dashboard.php
 *///

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// Initialize variables with defaults
$user = null;
$subscriptions = [];
$recent_orders = [];
$nutrition_data = [];
$notifications = [];
$upcoming_delivery = null;

// Initialize stats with defaults
$active_subscriptions = 0;
$total_orders = 0;
$avg_nutrition = 0;

// Error tracking
$db_errors = [];

// Check if we have connection
if (!isset($connection)) {
    $db_errors[] = "Database connection variable not found";
} else {
    // Test connection
    if (!mysqli_ping($connection)) {
        $db_errors[] = "Database connection lost";
    }
}

// Only proceed if connection is good
if (empty($db_errors)) {
    // 1. Get user information with error handling
    try {
        $user_query = "SELECT * FROM users WHERE id = ?";
        $stmt = mysqli_prepare($connection, $user_query);
        
        if ($stmt === false) {
            $db_errors[] = "Failed to prepare user query: " . mysqli_error($connection);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);
                
                if (!$user) {
                    $db_errors[] = "User not found in database";
                }
            } else {
                $db_errors[] = "Failed to execute user query: " . mysqli_stmt_error($stmt);
            }
            
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        $db_errors[] = "User query exception: " . $e->getMessage();
    }

    // 2. Get subscriptions count (simplified)
    if (empty($db_errors)) {
        try {
            $subs_query = "SELECT COUNT(*) as count FROM subscriptions WHERE user_id = ?";
            $stmt = mysqli_prepare($connection, $subs_query);
            
            if ($stmt !== false) {
                mysqli_stmt_bind_param($stmt, "s", $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    $sub_count = mysqli_fetch_assoc($result);
                    $active_subscriptions = (int)($sub_count['count'] ?? 0);
                }
                mysqli_stmt_close($stmt);
            } else {
                // Table might not exist
                $db_errors[] = "Subscriptions table may not exist";
            }
        } catch (Exception $e) {
            $db_errors[] = "Subscriptions query error: " . $e->getMessage();
        }
    }

    // 3. Get orders count (simplified)
    if (empty($db_errors)) {
        try {
            $orders_query = "SELECT COUNT(*) as count FROM orders WHERE user_id = ?";
            $stmt = mysqli_prepare($connection, $orders_query);
            
            if ($stmt !== false) {
                mysqli_stmt_bind_param($stmt, "s", $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    $order_count = mysqli_fetch_assoc($result);
                    $total_orders = (int)($order_count['count'] ?? 0);
                }
                mysqli_stmt_close($stmt);
            } else {
                $db_errors[] = "Orders table may not exist";
            }
        } catch (Exception $e) {
            $db_errors[] = "Orders query error: " . $e->getMessage();
        }
    }

    // 4. Get notifications count (simplified)
    $notification_count = 0;
    try {
        $notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ?";
        $stmt = mysqli_prepare($connection, $notif_query);
        
        if ($stmt !== false) {
            mysqli_stmt_bind_param($stmt, "s", $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                $notif_result = mysqli_fetch_assoc($result);
                $notification_count = (int)($notif_result['count'] ?? 0);
            }
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        // Notifications table may not exist, ignore error
    }

    // Update last login (if user found)
    if ($user) {
        try {
            $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $stmt = mysqli_prepare($connection, $update_query);
            if ($stmt !== false) {
                mysqli_stmt_bind_param($stmt, "s", $user_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            // Non-critical error, continue
        }
    }
}

// Fallback user data if database failed
if (!$user) {
    $user = [
        'id' => $user_id,
        'first_name' => $_SESSION['user_name'] ?? 'User',
        'last_name' => '',
        'email' => $_SESSION['user_email'] ?? 'Unknown'
    ];
}

$page_title = "Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Somdul Table</title>
    <meta name="description" content="Welcome to your Somdul Table dashboard - manage your Thai meal subscriptions and orders">
    
    <!-- BaticaSans Font Import -->
    <link rel="preconnect" href="https://ydpschool.com">
    <style>
        /* BaticaSans Font Family */
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

        /* CSS Custom Properties - Matching Somdul Table Design System */
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
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            min-height: 100vh;
            font-size: 16px;
            font-weight: 400;
        }

        /* Typography using BaticaSans */
        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
        }

        @keyframes glow {
            from {
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            to {
                box-shadow: 0 2px 20px rgba(207, 114, 58, 0.3);
            }
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-3px);
            }
            60% {
                transform: translateY(-2px);
            }
        }

        /* Promotional Banner Styles */
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
            animation: glow 2s ease-in-out infinite alternate;
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
            animation: bounce 1.5s ease-in-out infinite;
        }

        .promo-text {
            letter-spacing: 0.5px;
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

        @keyframes glow {
            from {
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            to {
                box-shadow: 0 2px 20px rgba(207, 114, 58, 0.3);
            }
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-3px);
            }
            60% {
                transform: translateY(-2px);
            }
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

        .btn-logout {
            background: var(--danger);
            color: white;
            border: none;
        }

        .btn-logout:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        /* Dashboard Layout */
        .dashboard {
            padding-top: 120px;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%);
            color: var(--white);
            padding: 2.5rem 2rem;
            margin-bottom: 3rem;
            border-radius: 16px;
            position: relative;
            overflow: hidden;
            text-align: left;
        }

        .dashboard-header::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -20%;
            width: 80%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 1px, transparent 1px);
            background-size: 20px 20px;
            opacity: 0.3;
            transform: rotate(15deg);
        }

        .dashboard-welcome {
            position: relative;
            z-index: 2;
            max-width: 600px;
        }

        .welcome-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .dashboard-welcome h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            font-family: 'BaticaSans', sans-serif;
            line-height: 1.2;
        }

        .dashboard-welcome p {
            font-size: 1rem;
            opacity: 0.9;
            font-family: 'BaticaSans', sans-serif;
            line-height: 1.5;
            font-weight: 400;
        }

        /* Stats Section */
        .stats-section {
            margin-bottom: 3rem;
            padding-top: 3rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--shadow-soft);
            text-align: center;
            transition: transform 0.3s ease;
            border: 1px solid rgba(189, 147, 121, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--curry);
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .stat-label {
            color: var(--text-gray);
            font-weight: 500;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Dashboard Content */
        .dashboard-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 3rem;
        }

        .dashboard-main {
            display: flex;
            flex-direction: column;
            gap: 3rem;
        }

        .dashboard-sidebar {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .sidebar-card, .content-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(189, 147, 121, 0.1);
        }

        .sidebar-card h3, .content-card h3 {
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(189, 147, 121, 0.1);
        }

        .empty-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            opacity: 0.8;
        }

        .empty-state h3 {
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .empty-state p {
            color: var(--text-gray);
            margin-bottom: 2rem;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .action-button {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--cream);
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-dark);
            transition: var(--transition);
            font-family: 'BaticaSans', sans-serif;
        }

        .action-button:hover {
            background: var(--brown);
            color: var(--white);
            transform: translateX(5px);
        }

        .action-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-gray);
            transition: var(--transition);
        }

        .action-button:hover .action-icon {
            color: var(--white);
        }

        .action-text {
            font-weight: 500;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            font-weight: 500;
            border: 2px solid;
            font-family: 'BaticaSans', sans-serif;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-color: var(--success);
            color: var(--success);
        }

        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }

        .alert ul {
            margin: 0;
            padding-left: 1.2rem;
        }

        .alert ul li {
            margin-bottom: 0.3rem;
        }

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            font-family: monospace;
            font-size: 0.85rem;
        }

        .error-list {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .error-list ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .logout-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--cream);
            text-align: center;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--cream);
        }

        .section-header h2 {
            color: var(--text-dark);
            font-size: 1.5rem;
            margin: 0;
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .dashboard-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .dashboard-sidebar {
                order: -1;
            }
        }

        @media (max-width: 768px) {
            .promo-banner {
                font-size: 12px;
                padding: 6px 15px;
            }
            
            .navbar {
                top: 32px;
            }
            
            .dashboard {
                padding-top: 100px;
            }
            
            .promo-banner-content {
                flex-direction: column;
                gap: 5px;
            }
            
            .promo-close {
                right: 10px;
            }

            .dashboard-welcome h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }

            .container {
                padding: 0 1rem;
            }

            nav {
                padding: 1rem;
            }

            nav > div {
                padding: 0 0.5rem !important;
            }

            .nav-actions {
                gap: 0.5rem;
            }

            .btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .stat-card, .sidebar-card, .content-card {
                border: 2px solid var(--text-dark);
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Facebook-Style Notification CSS */
        .notification-dropdown {
            position: relative;
            display: inline-block;
        }

        .notification-bell {
            position: relative;
            background: linear-gradient(135deg, var(--curry), #e67e22);
            color: var(--white);
            border: none;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-soft);
        }

        .notification-bell:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
            background: linear-gradient(135deg, #e67e22, var(--brown));
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            min-width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            border: 2px solid white;
        }

        .notification-dropdown-panel {
            position: absolute;
            top: 100%;
            right: 0;
            width: 380px;
            max-height: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            border: 1px solid #e1e8ed;
            z-index: 1000;
            display: none;
            overflow: hidden;
            margin-top: 8px;
        }

        .notification-dropdown-panel.show {
            display: block;
            animation: dropdownFadeIn 0.3s ease-out;
        }

        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

  
    </style>
    
</head>
<body>
    <!-- Promotional Banner -->
    <div class="promo-banner" id="promoBanner">
        <div class="promo-banner-content">
            <span class="promo-icon">🌶️</span>
            <span class="promo-text">Fresh Ingredients Daily • Authentic Thai Flavors • Free Delivery on Orders $25+</span>
            <span class="promo-icon">🥢</span>
        </div>
        <button class="promo-close" onclick="closePromoBanner()" title="Close">×</button>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 0 1rem;">
            <a href="home2.php" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Somdul Table" style="height: 45px; width: auto;">
                <span class="logo-text">Somdul Table</span>
            </a>
            
            <div class="nav-actions">
                
                <span style="color: var(--text-gray); font-family: 'BaticaSans', sans-serif; font-weight: 500;">
                    Welcome, <?php echo htmlspecialchars($user['first_name']); ?>
                </span>
                <a href="home2.php" class="btn btn-secondary">Home</a>
   <div class="notification-dropdown">
    <button class="notification-bell" onclick="toggleNotificationDropdown()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="color: white;">
            <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="notification-badge" id="notificationCount">3</span>
    </button>
    <div class="notification-dropdown-panel" id="notificationPanel">
        <!-- Dropdown content จะใส่ตรงนี้ -->
    </div>
</div>
                <a href="logout.php" class="btn btn-logout">Logout</a>
            </div>
        </div>
    </nav>

    <div class="dashboard">
        <div class="container">
            <!-- Show errors if any (for debugging) -->
            <?php if (!empty($db_errors)): ?>
            <div class="error-list">
                <h4>🚨 Database Issues Detected:</h4>
                <ul>
                    <?php foreach ($db_errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p><strong>Note:</strong> Some features may not work correctly. Please check your database setup.</p>
            </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="stats-section">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $active_subscriptions; ?></div>
                        <div class="stat-label">Active Plans</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_orders; ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo round($avg_nutrition); ?>%</div>
                        <div class="stat-label">Nutrition Goals</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $notification_count ?? 0; ?></div>
                        <div class="stat-label">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block; margin-right: 6px; color: var(--text-gray);">
                                <path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M13.73 21C13.5542 21.3031 13.3019 21.5547 12.9982 21.7295C12.6946 21.9044 12.3504 21.9965 12 21.9965C11.6496 21.9965 11.3054 21.9044 11.0018 21.7295C10.6982 21.5547 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Notifications
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-content">
                <!-- Main Content -->
                <div class="dashboard-main">
                    <!-- Welcome Message -->
                    <section>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="color: var(--curry);">
                                    <path d="M9 12L11 14L15 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                </svg>
                            </div>
                            <h3>Welcome to Somdul Table!</h3>
                            <p>You have successfully logged in. Start exploring our authentic Thai cuisine and healthy meal plans!</p>
                            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                                <a href="subscribe.php" class="btn btn-primary">Choose Plan</a>
                                <a href="menus.php" class="btn btn-secondary">View Menu</a>
                            </div>
                        </div>
                    </section>

                    <!-- Database Setup Help -->
                    <?php if (!empty($db_errors)): ?>
                    <section>
                        <div class="content-card">
                            <h3>Fix Database Issues</h3>
                            <ol style="color: var(--text-gray); line-height: 1.8; font-family: 'BaticaSans', sans-serif;">
                                <li>Check if MySQL is running in MAMP/XAMPP</li>
                                <li>Verify database 'somdul_table' exists</li>
                                <li>Import somdul_table.sql file</li>
                                <li>Check database credentials in config/database.php</li>
                                <li>Run <a href="test_connection.php" target="_blank">test_connection.php</a> to debug</li>
                            </ol>
                        </div>
                    </section>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="dashboard-sidebar">
                    <!-- Quick Actions -->
                    <div class="sidebar-card">
                        <h3>Quick Actions</h3>
                        <div class="quick-actions">
                            <a href="subscribe.php" class="action-button">
                                <span class="action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                        <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                        <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <span class="action-text">Choose New Plan</span>
                            </a>
                            <a href="edit_profile.php" class="action-button">
                                <span class="action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <span class="action-text">Edit Profile</span>
                            </a>
                            <a href="menus.php" class="action-button">
                                <span class="action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 12L5 10L12 17L22 7L24 9L12 21L3 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                </span>
                                <span class="action-text">View All Menus</span>
                            </a>
                            <a href="nutrition-tracking.php" class="action-button">
                                <span class="action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M18 20V10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M12 20V4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M6 20V14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                                <span class="action-text">Track Nutrition</span>
                            </a>
                            <a href="payment_status.php" class="action-button">
                                <span class="action-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="1" y="5" width="22" height="14" rx="2" ry="2" stroke="currentColor" stroke-width="2"/>
                                        <line x1="1" y1="10" x2="23" y2="10" stroke="currentColor" stroke-width="2"/>
                                    </svg>
                                </span>
                                <span class="action-text">Payment Status</span>
                            </a>
                           <a href="subscription-status.php" class="action-button">
    <span class="action-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M6 2L3 6V20C3 21.1 3.9 22 5 22H19C20.1 22 21 21.1 21 20V6L18 2H6Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <line x1="3" y1="6" x2="21" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M16 10C16 11.1046 15.1046 12 14 12C12.8954 12 12 11.1046 12 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M8 10C8 11.1046 8.89543 12 10 12C11.1046 12 12 11.1046 12 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </span>
    <span class="action-text">My Orders</span>
</a>
                      
                        </div>
                    </div>

                    <!-- User Info -->
                    <div class="sidebar-card">
                        <h3>User Information</h3>
                        <div style="line-height: 1.8; font-family: 'BaticaSans', sans-serif;">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                            <p><strong>Status:</strong> <span style="color: var(--success);">● Online</span></p>
                        </div>
                    </div>

                    <!-- Support -->
                    <div class="sidebar-card">
                        <h3>Need Help?</h3>
                        <p style="color: var(--text-gray); font-family: 'BaticaSans', sans-serif;">If you have any issues using the system, you can contact our support team or visit our help center.</p>
                        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <a href="support-center.php" class="btn btn-secondary">Help Center</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        console.log('Somdul Table Dashboard loaded successfully!');
        console.log('Database errors:', <?php echo json_encode($db_errors); ?>);
        
        // Promotional banner functionality
        function closePromoBanner() {
            const promoBanner = document.getElementById('promoBanner');
            const navbar = document.querySelector('.navbar');
            const dashboard = document.querySelector('.dashboard');
            
            promoBanner.style.transform = 'translateY(-100%)';
            promoBanner.style.opacity = '0';
            
            setTimeout(() => {
                promoBanner.style.display = 'none';
                navbar.style.top = '0';
                dashboard.style.paddingTop = '80px';
            }, 300);
        }
        
        // Add some interactivity to stat cards
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Auto-refresh page if there are database errors (for development)
        <?php if (!empty($db_errors) && count($db_errors) < 5): ?>
        setTimeout(function() {
            if (confirm('Found database issues. Reload page to retry connection?')) {
                location.reload();
            }
        }, 5000);
        <?php endif; ?>

        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.95)';
            }
        });
// Facebook-Style Notification System
let notificationDropdownOpen = false;
let unreadCount = 0;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateNotificationCount();
    startNotificationPolling();
});

// Toggle notification dropdown
function toggleNotificationDropdown() {
    if (notificationDropdownOpen) {
        closeNotificationDropdown();
    } else {
        openNotificationDropdown();
    }
}

// Open notification dropdown
async function openNotificationDropdown() {
    notificationDropdownOpen = true;
    const panel = document.getElementById('notificationPanel');
    panel.innerHTML = `
        <div class="dropdown-header" style="padding: 1rem; border-bottom: 1px solid #e1e8ed; background: #f8f9fa;">
            <h4 style="margin: 0; font-size: 1.1rem; color: var(--text-dark);">Notifications</h4>
            <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                <button class="btn btn-sm btn-secondary" onclick="openNotificationsPage()" style="font-size: 0.8rem; padding: 0.3rem 0.6rem;">View All</button>
                <button class="btn btn-sm btn-success" onclick="markAllDropdownRead()" style="font-size: 0.8rem; padding: 0.3rem 0.6rem;">Mark All Read</button>
            </div>
        </div>
        <div class="dropdown-content" style="max-height: 400px; overflow-y: auto;">
            <div style="padding: 1rem; text-align: center; color: var(--text-gray);">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">⏳</div>
                Loading notifications...
            </div>
        </div>
    `;
    panel.classList.add('show');
    
    // Load notifications
    await loadDropdownNotifications();
}

// Close notification dropdown
function closeNotificationDropdown() {
    notificationDropdownOpen = false;
    const panel = document.getElementById('notificationPanel');
    panel.classList.remove('show');
}

// Load notifications for dropdown
async function loadDropdownNotifications() {
    try {
        const response = await fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_notifications&limit=5&filter=all'
        });

        const data = await response.json();
        
        if (data.success) {
            displayDropdownNotifications(data.notifications || []);
        } else {
            document.querySelector('.dropdown-content').innerHTML = `
                <div style="padding: 2rem; text-align: center; color: var(--text-gray);">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">❌</div>
                    <p>Unable to load notifications</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
        document.querySelector('.dropdown-content').innerHTML = `
            <div style="padding: 2rem; text-align: center; color: var(--text-gray);">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔌</div>
                <p>Connection problem</p>
            </div>
        `;
    }
}

// Display notifications in dropdown
function displayDropdownNotifications(notifications) {
    const content = document.querySelector('.dropdown-content');
    
    if (!notifications || notifications.length === 0) {
        content.innerHTML = `
            <div style="padding: 2rem; text-align: center; color: var(--text-gray);">
                <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔔</div>
                <p>No new notifications</p>
                <small>You're all caught up!</small>
            </div>
        `;
        return;
    }

    const notificationsHTML = notifications.map(notification => {
        const isUnread = !notification.read_at;
        const icon = getNotificationIcon(notification.type);
        const timeAgo = formatTimeAgo(notification.created_at);
        
        return `
            <div class="dropdown-notification-item" style="
                padding: 0.75rem 1rem; 
                border-bottom: 1px solid #f0f0f0; 
                cursor: pointer;
                ${isUnread ? 'background: linear-gradient(90deg, rgba(207, 114, 58, 0.05) 0%, transparent 100%); border-left: 3px solid var(--curry);' : ''}
                transition: background 0.3s ease;
            " onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='${isUnread ? 'linear-gradient(90deg, rgba(207, 114, 58, 0.05) 0%, transparent 100%)' : 'transparent'}'" onclick="handleDropdownNotificationClick('${notification.id}', ${isUnread})">
                <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                    <div style="font-size: 1.2rem; margin-top: 0.25rem;">${icon}</div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: ${isUnread ? '600' : '500'}; font-size: 0.9rem; color: var(--text-dark); margin-bottom: 0.25rem; line-height: 1.3;">
                            ${notification.title}
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-gray); line-height: 1.3; margin-bottom: 0.5rem;">
                            ${notification.message.length > 80 ? notification.message.substring(0, 80) + '...' : notification.message}
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-gray);">
                            ${timeAgo} ${isUnread ? '• <span style="color: var(--curry); font-weight: 600;">NEW</span>' : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    content.innerHTML = notificationsHTML;
}

// Handle notification click in dropdown
function handleDropdownNotificationClick(notificationId, isUnread) {
    if (isUnread) {
        markNotificationAsRead(notificationId);
    }
    // Optionally close dropdown and navigate
    closeNotificationDropdown();
}

// Get notification icon
function getNotificationIcon(type) {
    const icons = {
        order_update: '📦',
        delivery: '🚚',
        payment: '💳',
        promotion: '🎉',
        system: '⚙️',
        review_reminder: '⭐'
    };
    return icons[type] || '📢';
}

// Format time ago
function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);

    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    return date.toLocaleDateString();
}

// Update notification count
async function updateNotificationCount() {
    try {
        const response = await fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_unread_count'
        });

        const data = await response.json();
        
        if (data.success) {
            unreadCount = data.count;
            const badge = document.getElementById('notificationCount');
            badge.textContent = unreadCount;
            badge.style.display = unreadCount > 0 ? 'flex' : 'none';
        }
    } catch (error) {
        console.error('Error updating notification count:', error);
    }
}

// Mark notification as read
async function markNotificationAsRead(notificationId) {
    try {
        const response = await fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_as_read&notification_id=${notificationId}`
        });

        const data = await response.json();
        
        if (data.success) {
            updateNotificationCount();
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

// Mark all notifications as read (dropdown)
async function markAllDropdownRead() {
    try {
        const response = await fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=mark_all_read'
        });

        const data = await response.json();
        
        if (data.success) {
            updateNotificationCount();
            loadDropdownNotifications(); // Refresh dropdown
        }
    } catch (error) {
        console.error('Error marking all as read:', error);
    }
}

// Open full notifications page
function openNotificationsPage() {
    window.location.href = 'notifications.php';
}

// Start polling for new notifications
function startNotificationPolling() {
    setInterval(updateNotificationCount, 30000); // Every 30 seconds
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.querySelector('.notification-dropdown');
    if (!dropdown.contains(event.target)) {
        closeNotificationDropdown();
    }
});
    </script>
</body>
</html>