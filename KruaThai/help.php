<?php
/**
 * Somdul Table - Help Center Page
 * File: help.php
 * Description: Comprehensive help center with FAQ, guides, and support resources
 */

// Basic security check
if (!defined('ALLOW_DIRECT_ACCESS')) {
    // This can be included by other files or accessed directly
}

// Check if user is logged in (optional for help page)
session_start();
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $is_logged_in ? ($_SESSION['user_name'] ?? 'User') : null;

// Get current year for copyright
$current_year = date('Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - Somdul Table</title>
    <meta name="description" content="Get help with Somdul Table meal delivery service. Find answers to common questions, troubleshooting guides, and contact support.">
    <meta name="keywords" content="help, support, FAQ, Somdul Table, Thai food delivery, customer service, troubleshooting">
    <meta name="robots" content="index, follow">
    
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
            --text-light: #95a5a6;
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
            font-size: 16px;
            font-weight: 400;
            min-height: 100vh;
        }

        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.3;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }

        h1 {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
        }

        h2 {
            font-size: 1.8rem;
            margin-bottom: 1.2rem;
            color: var(--curry);
        }

        h3 {
            font-size: 1.4rem;
            margin-bottom: 1rem;
            color: var(--brown);
        }

        h4 {
            font-size: 1.2rem;
            margin-bottom: 0.8rem;
            color: var(--text-dark);
        }

        p {
            margin-bottom: 1.2rem;
            color: var(--text-dark);
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%);
            color: var(--white);
            padding: 2rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
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

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
        }

        .logo:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--white), var(--cream));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.8);
        }

        .logo-text {
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            font-family: 'BaticaSans', sans-serif;
        }

        .breadcrumb {
            background: var(--white);
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .breadcrumb-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        .breadcrumb a {
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .breadcrumb a:hover {
            color: var(--brown);
            text-decoration: underline;
        }

        .breadcrumb-separator {
            color: var(--text-light);
            margin: 0 0.5rem;
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        /* Search Bar */
        .search-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            padding: 2rem;
            margin-bottom: 3rem;
            text-align: center;
        }

        .search-box {
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 1rem 1.5rem 1rem 3rem;
            border: 2px solid var(--border-light);
            border-radius: 50px;
            font-size: 1.1rem;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
            background: var(--white);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-gray);
            font-size: 1.2rem;
        }

        /* Quick Links */
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .quick-link-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            text-align: center;
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
            border: 2px solid transparent;
            text-decoration: none;
            color: inherit;
        }

        .quick-link-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
            border-color: var(--curry);
            text-decoration: none;
            color: inherit;
        }

        .quick-link-icon {
            font-size: 3rem;
            color: var(--curry);
            margin-bottom: 1rem;
        }

        .quick-link-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .quick-link-desc {
            color: var(--text-gray);
            font-size: 0.95rem;
        }

        /* FAQ Section */
        .faq-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            overflow: hidden;
            margin-bottom: 3rem;
        }

        .faq-header {
            background: linear-gradient(135deg, var(--sage) 0%, rgba(173, 184, 157, 0.8) 100%);
            color: var(--white);
            padding: 2rem;
            text-align: center;
        }

        .faq-content {
            padding: 2rem;
        }

        .faq-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: center;
        }

        .faq-category-btn {
            background: transparent;
            color: var(--text-gray);
            border: 2px solid var(--border-light);
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .faq-category-btn.active,
        .faq-category-btn:hover {
            background: var(--curry);
            color: var(--white);
            border-color: var(--curry);
        }

        .faq-list {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-item {
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            overflow: hidden;
            transition: var(--transition);
        }

        .faq-item:hover {
            border-color: var(--curry);
        }

        .faq-question {
            width: 100%;
            background: var(--cream);
            border: none;
            padding: 1.5rem;
            text-align: left;
            cursor: pointer;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            font-size: 1rem;
            color: var(--text-dark);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .faq-question:hover {
            background: rgba(var(--curry), 0.1);
        }

        .faq-question.active {
            background: var(--curry);
            color: var(--white);
        }

        .faq-icon {
            transition: transform 0.3s ease;
            font-size: 0.9rem;
        }

        .faq-question.active .faq-icon {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 1.5rem;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .faq-answer.active {
            padding: 1.5rem;
            max-height: 500px;
        }

        .faq-answer ul,
        .faq-answer ol {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }

        .faq-answer li {
            margin-bottom: 0.5rem;
        }

        /* Contact Section */
        .contact-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            overflow: hidden;
            margin-bottom: 3rem;
        }

        .contact-header {
            background: linear-gradient(135deg, var(--brown) 0%, rgba(189, 147, 121, 0.8) 100%);
            color: var(--white);
            padding: 2rem;
            text-align: center;
        }

        .contact-content {
            padding: 2rem;
        }

        .contact-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .contact-method {
            text-align: center;
            padding: 2rem;
            background: rgba(var(--curry), 0.05);
            border-radius: var(--radius-md);
            border: 2px solid transparent;
            transition: var(--transition);
        }

        .contact-method:hover {
            border-color: var(--curry);
            transform: translateY(-3px);
        }

        .contact-method-icon {
            font-size: 2.5rem;
            color: var(--curry);
            margin-bottom: 1rem;
        }

        .contact-method-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .contact-method-info {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--curry);
            margin-bottom: 0.5rem;
        }

        .contact-method-desc {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* Business Hours */
        .business-hours {
            background: var(--cream);
            border-radius: var(--radius-md);
            padding: 2rem;
            margin-top: 2rem;
        }

        .hours-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .hours-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(var(--text-gray), 0.2);
        }

        .hours-item:last-child {
            border-bottom: none;
        }

        .hours-day {
            font-weight: 600;
            color: var(--text-dark);
        }

        .hours-time {
            color: var(--text-gray);
        }

        /* Guides Section */
        .guides-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            padding: 2rem;
            margin-bottom: 3rem;
        }

        .guides-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .guide-card {
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            transition: var(--transition);
        }

        .guide-card:hover {
            border-color: var(--sage);
            transform: translateY(-3px);
        }

        .guide-icon {
            font-size: 2rem;
            color: var(--sage);
            margin-bottom: 1rem;
        }

        .guide-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .guide-desc {
            color: var(--text-gray);
            margin-bottom: 1rem;
        }

        .guide-link {
            color: var(--sage);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .guide-link:hover {
            text-decoration: underline;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 2rem 1rem;
            }
            
            .header-content {
                padding: 0 1rem;
            }
            
            .breadcrumb-content {
                padding: 0 1rem;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            h2 {
                font-size: 1.5rem;
            }
            
            .logo-text {
                font-size: 1.8rem;
            }
            
            .search-section {
                padding: 1.5rem;
            }
            
            .faq-content,
            .contact-content {
                padding: 1.5rem;
            }
            
            .faq-categories {
                justify-content: flex-start;
                overflow-x: auto;
                padding-bottom: 0.5rem;
            }
            
            .faq-category-btn {
                white-space: nowrap;
            }
            
            .quick-links {
                grid-template-columns: 1fr;
            }
            
            .contact-methods {
                grid-template-columns: 1fr;
            }
            
            .hours-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Print styles */
        @media print {
            .header,
            .breadcrumb,
            .search-section {
                display: none;
            }
            
            .container {
                max-width: 100%;
                margin: 0;
                padding: 1rem;
            }
            
            .faq-question,
            .faq-answer {
                background: white !important;
                color: black !important;
            }
        }

        /* Accessibility */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Focus indicators */
        button:focus-visible,
        input:focus-visible,
        a:focus-visible {
            outline: 2px solid var(--curry);
            outline-offset: 2px;
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(var(--curry), 0.3);
            border-radius: 50%;
            border-top-color: var(--curry);
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <a href="home2.php" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Somdul Table" style="height: 60px; width: auto; border-radius: 50%;" class="logo-icon">
                <span class="logo-text">Somdul Table</span>
            </a>
            <div>
                <h1 style="color: white; margin: 0;">Help Center</h1>
                <p style="margin: 0; opacity: 0.9; font-size: 1.1rem;">
                    <?php if ($is_logged_in): ?>
                        Welcome back, <?php echo htmlspecialchars($user_name); ?>! How can we help you today?
                    <?php else: ?>
                        How can we help you today?
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <div class="breadcrumb-content">
            <a href="home2.php">Home</a>
            <span class="breadcrumb-separator">›</span>
            <span>Help Center</span>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <!-- Search Section -->
        <div class="search-section">
            <h2>🔍 Search for Help</h2>
            <p style="color: var(--text-gray); margin-bottom: 2rem;">Type your question or browse topics below</p>
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" class="search-input" id="helpSearch" placeholder="e.g., How to change my meal plan, delivery issues, payment problems..." aria-label="Search help topics">
            </div>
            <div id="searchResults" style="margin-top: 1rem; display: none;"></div>
        </div>

        <!-- Quick Links -->
        <div class="quick-links">
            <a href="#faq-ordering" class="quick-link-card">
                <div class="quick-link-icon">🛒</div>
                <div class="quick-link-title">Ordering & Subscriptions</div>
                <div class="quick-link-desc">Learn how to place orders, manage subscriptions, and customize your meal plans</div>
            </a>
            
            <a href="#faq-delivery" class="quick-link-card">
                <div class="quick-link-icon">🚛</div>
                <div class="quick-link-title">Delivery & Tracking</div>
                <div class="quick-link-desc">Track your orders, delivery schedules, and what to do if you miss a delivery</div>
            </a>
            
            <a href="#faq-account" class="quick-link-card">
                <div class="quick-link-icon">👤</div>
                <div class="quick-link-title">Account & Billing</div>
                <div class="quick-link-desc">Manage your account settings, payment methods, and billing information</div>
            </a>
            
            <a href="#faq-food" class="quick-link-card">
                <div class="quick-link-icon">🍽️</div>
                <div class="quick-link-title">Food & Nutrition</div>
                <div class="quick-link-desc">Information about ingredients, allergens, nutrition facts, and meal customization</div>
            </a>
            
            <?php if ($is_logged_in): ?>
            <a href="support-center.php" class="quick-link-card">
                <div class="quick-link-icon">🎧</div>
                <div class="quick-link-title">Submit Support Ticket</div>
                <div class="quick-link-desc">Get personalized help by submitting a support request</div>
            </a>
            <?php endif; ?>
            
            <a href="#contact" class="quick-link-card">
                <div class="quick-link-icon">📞</div>
                <div class="quick-link-title">Contact Us</div>
                <div class="quick-link-desc">Speak directly with our customer support team via phone, email, or chat</div>
            </a>
        </div>

        <!-- FAQ Section -->
        <div class="faq-section">
            <div class="faq-header">
                <h2 style="color: white; margin: 0;">❓ Frequently Asked Questions</h2>
                <p style="margin: 0; opacity: 0.9;">Find quick answers to common questions</p>
            </div>
            
            <div class="faq-content">
                <!-- FAQ Categories -->
                <div class="faq-categories">
                    <button class="faq-category-btn active" data-category="all">All Questions</button>
                    <button class="faq-category-btn" data-category="ordering">Ordering</button>
                    <button class="faq-category-btn" data-category="delivery">Delivery</button>
                    <button class="faq-category-btn" data-category="account">Account</button>
                    <button class="faq-category-btn" data-category="food">Food & Safety</button>
                    <button class="faq-category-btn" data-category="billing">Billing</button>
                </div>

                <!-- FAQ List -->
                <div class="faq-list">
                    <!-- Ordering FAQs -->
                    <div class="faq-item" data-category="ordering">
                        <button class="faq-question">
                            <span>How do I place my first order?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>Getting started with Somdul Table is easy:</p>
                            <ol>
                                <li><strong>Create an account:</strong> Sign up with your email and delivery address</li>
                                <li><strong>Choose a meal plan:</strong> Select weekly or monthly subscription that fits your needs</li>
                                <li><strong>Pick your meals:</strong> Browse our authentic Thai menu and select your favorites</li>
                                <li><strong>Set delivery schedule:</strong> Choose your preferred delivery days and time slots</li>
                                <li><strong>Complete payment:</strong> Use Apple Pay, Google Pay, PayPal, or credit card</li>
                            </ol>
                            <p>Your first delivery will arrive within 2-3 business days!</p>
                        </div>
                    </div>

                    <div class="faq-item" data-category="ordering">
                        <button class="faq-question">
                            <span>Can I customize my meal plan?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>Absolutely! Somdul Table offers flexible customization:</p>
                            <ul>
                                <li><strong>Meal selection:</strong> Choose different meals for each delivery day</li>
                                <li><strong>Spice levels:</strong> Adjust from mild to extra hot for each dish</li>
                                <li><strong>Dietary preferences:</strong> Filter by vegetarian, vegan, gluten-free options</li>
                                <li><strong>Portion sizes:</strong> Available in regular and large portions</li>
                                <li><strong>Skip or pause:</strong> Skip weeks or pause your subscription anytime</li>
                            </ul>
                            <p>You can modify your upcoming orders up to 48 hours before delivery.</p>
                        </div>
                    </div>

                    <div class="faq-item" data-category="ordering">
                        <button class="faq-question">
                            <span>What meal plans are available?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>We offer several meal plans to fit your lifestyle:</p>
                            <ul>
                                <li><strong>Mini Plan (4 meals/week):</strong> Perfect for trying our service - $49.99/week</li>
                                <li><strong>Lite Plan (8 meals/week):</strong> For light eaters - $89.99/week</li>
                                <li><strong>Family Plan (12 meals/week):</strong> Great for families - $149.99/week</li>
                                <li><strong>Premium Plan (15 meals/week):</strong> For healthy enthusiasts - $179.99/week</li>
                                <li><strong>Monthly Plans:</strong> Save 10% with monthly subscriptions</li>
                            </ul>
                            <p>All plans include free delivery for orders over $50 and nutrition tracking.</p>
                        </div>
                    </div>

                    <!-- Delivery FAQs -->
                    <div class="faq-item" data-category="delivery">
                        <button class="faq-question">
                            <span>What are your delivery areas and times?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>We currently deliver to select areas with the following schedule:</p>
                            <ul>
                                <li><strong>Delivery zones:</strong> Check your ZIP code during registration</li>
                                <li><strong>Time slots:</strong> 9:00 AM - 12:00 PM, 12:00 PM - 3:00 PM, 3:00 PM - 6:00 PM, 6:00 PM - 9:00 PM</li>
                                <li><strong>Delivery days:</strong> Monday through Sunday</li>
                                <li><strong>Cutoff times:</strong> Orders must be placed 48 hours before delivery</li>
                            </ul>
                            <p>We're expanding to new areas regularly. Join our waitlist if we don't deliver to your area yet!</p>
                        </div>
                    </div>

                    <div class="faq-item" data-category="delivery">
                        <button class="faq-question">
                            <span>How do I track my delivery?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>Stay updated on your delivery with our tracking system:</p>
                            <ol>
                                <li><strong>Order confirmation:</strong> Receive email confirmation when order is placed</li>
                                <li><strong>Preparation updates:</strong> Get notified when your meal is being prepared</li>
                                <li><strong>Out for delivery:</strong> Receive text with delivery driver details</li>
                                <li><strong>Real-time tracking:</strong> Track your driver's location (premium feature)</li>
                                <li><strong>Delivery confirmation:</strong> Photo confirmation when delivered</li>
                            </ol>
                            <p>You can also check your order status anytime in your account dashboard.</p>
                        </div>
                    </div>

                    <div class="faq-item" data-category="delivery">
                        <button class="faq-question">
                            <span>What if I'm not home during delivery?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>We offer several options for missed deliveries:</p>
                            <ul>
                                <li><strong>Safe location:</strong> Leave specific instructions for a secure drop-off spot</li>
                                <li><strong>Photo proof:</strong> Our drivers take photos of delivered meals</li>
                                <li><strong>Neighbor delivery:</strong> Authorize delivery to a trusted neighbor</li>
                                <li><strong>Redelivery:</strong> Schedule redelivery for same day (additional fee may apply)</li>
                                <li><strong>Office delivery:</strong> Have meals delivered to your workplace</li>
                            </ul>
                            <p>Contact support immediately if your delivery wasn't received as planned.</p>
                        </div>
                    </div>

                    <!-- Account FAQs -->
                    <div class="faq-item" data-category="account">
                        <button class="faq-question">
                            <span>How do I manage my account and subscription?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>Your account dashboard gives you full control:</p>
                            <ul>
                                <li><strong>Subscription management:</strong> Change plans, pause, or cancel anytime</li>
                                <li><strong>Payment methods:</strong> Update credit cards and billing information</li>
                                <li><strong>Delivery preferences:</strong> Change address, delivery instructions, or schedule</li>
                                <li><strong>Dietary settings:</strong> Update allergies, preferences, and restrictions</li>
                                <li><strong>Order history:</strong> View past orders and reorder favorites</li>
                                <li><strong>Nutrition tracking:</strong> Monitor your daily nutrition intake</li>
                            </ul>
                            <p>Access your dashboard by logging into your account on our website or mobile app.</p>
                        </div>
                    </div>

                    <div class="faq-item" data-category="account">
                        <button class="faq-question">
                            <span>How do I cancel or pause my subscription?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>You have flexible options for managing your subscription:</p>
                            <h4>To Pause (up to 4 weeks per year):</h4>
                            <ol>
                                <li>Log into your account dashboard</li>
                                <li>Go to "Subscription Settings"</li>
                                <li>Select "Pause Subscription" and choose dates</li>
                                <li>Confirm your pause period</li>
                            </ol>
                            <h4>To Cancel:</h4>
                            <ol>
                                <li>Contact customer support or use the dashboard</li>
                                <li>Cancel before your next billing cycle to avoid charges</li>
                                <li>Complete any remaining scheduled deliveries</li>
                                <li>Receive confirmation email</li>
                            </ol>
                            <p><strong>Note:</strong> Weekly plans require 48 hours notice, monthly plans require 7 days notice.</p>
                        </div>
                    </div>

                    <!-- Food & Safety FAQs -->
                    <div class="faq-item" data-category="food">
                        <button class="faq-question">
                            <span>How do you handle food allergies and dietary restrictions?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>Food safety is our top priority. Here's how we protect you:</p>
                            <ul>
                                <li><strong>Allergy profile:</strong> Set up detailed allergy information in your account</li>
                                <li><strong>Clear labeling:</strong> All meals clearly list ingredients and common allergens</li>
                                <li><strong>Kitchen protocols:</strong> Separate preparation areas for different dietary needs</li>
                                <li><strong>Cross-contamination prevention:</strong> Strict cleaning procedures between prep</li>
                                <li><strong>Dietary filters:</strong> Easily find vegan, vegetarian, gluten-free, and keto options</li>
                            </ul>
                            <p><strong>⚠️ Important:</strong> Our kitchen processes nuts, dairy, soy, and shellfish. Please inform us of any severe allergies.</p>
                        </div>
                    </div>

                    <div class="faq-item" data-category="food">
                        <button class="faq-question">
                            <span>How do you ensure food quality and freshness?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>We maintain the highest quality standards:</p>
                            <ul>
                                <li><strong>Fresh ingredients:</strong> Daily sourcing from trusted local suppliers</li>
                                <li><strong>Expert chefs:</strong> Authentic Thai recipes prepared by certified chefs</li>
                                <li><strong>HACCP compliance:</strong> Food safety protocols at every step</li>
                                <li><strong>Temperature control:</strong> Proper refrigeration during transport</li>
                                <li><strong>Quality checks:</strong> Each meal inspected before packaging</li>
                                <li><strong>Packaging:</strong> Eco-friendly, insulated containers maintain freshness</li>
                            </ul>
                            <p>If you're ever unsatisfied with food quality, we offer 100% replacement or refund guarantee.</p>
                        </div>
                    </div>

                    <div class="faq-item" data-category="food">
                        <button class="faq-question">
                            <span>What if I receive damaged or incorrect food?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>We'll make it right immediately! Here's what to do:</p>
                            <ol>
                                <li><strong>Take photos:</strong> Document any issues with the food or packaging</li>
                                <li><strong>Contact us:</strong> Call, email, or submit a complaint through your dashboard</li>
                                <li><strong>Provide details:</strong> Describe the problem clearly</li>
                                <li><strong>Get resolution:</strong> We'll provide replacement meals or full refund</li>
                            </ol>
                            <p><strong>Resolution timeframe:</strong> We respond within 2 hours and resolve issues within 24 hours.</p>
                        </div>
                    </div>

                    <!-- Billing FAQs -->
                    <div class="faq-item" data-category="billing">
                        <button class="faq-question">
                            <span>What payment methods do you accept?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>We accept all major payment methods for your convenience:</p>
                            <ul>
                                <li><strong>Mobile payments:</strong> Apple Pay, Google Pay</li>
                                <li><strong>Digital wallets:</strong> PayPal, Amazon Pay</li>
                                <li><strong>Credit cards:</strong> Visa, MasterCard, American Express, Discover</li>
                                <li><strong>Debit cards:</strong> Any card with Visa/MasterCard logo</li>
                                <li><strong>Bank transfers:</strong> ACH transfers for recurring subscriptions</li>
                            </ul>
                            <p>All payments are processed securely with bank-level encryption. We never store your complete payment information.</p>
                        </div>
                    </div>

                    <div class="faq-item" data-category="billing">
                        <button class="faq-question">
                            <span>When will I be charged for my subscription?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>Billing schedule depends on your subscription type:</p>
                            <ul>
                                <li><strong>Weekly plans:</strong> Charged every week on your selected day</li>
                                <li><strong>Monthly plans:</strong> Charged monthly on your subscription start date</li>
                                <li><strong>First order:</strong> Charged immediately upon subscription activation</li>
                                <li><strong>Changes:</strong> Prorated charges for mid-cycle plan changes</li>
                            </ul>
                            <p>You'll receive email confirmation and invoice for every charge. Check your billing history in your account dashboard.</p>
                        </div>
                    </div>

                    <div class="faq-item" data-category="billing">
                        <button class="faq-question">
                            <span>How do refunds work?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>We offer fair and flexible refund policies:</p>
                            <h4>Eligible for full refund:</h4>
                            <ul>
                                <li>Food quality issues or safety concerns</li>
                                <li>Missing or significantly delayed deliveries</li>
                                <li>Billing errors or unauthorized charges</li>
                                <li>Cancellation within allowed timeframe</li>
                            </ul>
                            <h4>Refund process:</h4>
                            <ol>
                                <li>Submit refund request through support</li>
                                <li>Provide reason and supporting evidence</li>
                                <li>Receive approval within 24 hours</li>
                                <li>Refund processed within 3-5 business days</li>
                            </ol>
                            <p>Refunds are credited to your original payment method or as account credit.</p>
                        </div>
                    </div>

                    <!-- General FAQs -->
                    <div class="faq-item" data-category="all">
                        <button class="faq-question">
                            <span>Is there a mobile app available?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>Yes! Our mobile app offers the full Somdul Table experience:</p>
                            <ul>
                                <li><strong>Easy ordering:</strong> Browse menus and place orders on the go</li>
                                <li><strong>Real-time tracking:</strong> Track your delivery in real-time</li>
                                <li><strong>Nutrition insights:</strong> Monitor your daily nutrition intake</li>
                                <li><strong>Push notifications:</strong> Get updates about orders and deliveries</li>
                                <li><strong>Quick reorders:</strong> Reorder your favorite meals with one tap</li>
                            </ul>
                            <p>Download from the App Store or Google Play Store. Search for "Somdul Table".</p>
                        </div>
                    </div>

                    <div class="faq-item" data-category="all">
                        <button class="faq-question">
                            <span>Do you offer corporate or group discounts?</span>
                            <span class="faq-icon">▼</span>
                        </button>
                        <div class="faq-answer">
                            <p>Yes! We offer special pricing for groups and businesses:</p>
                            <ul>
                                <li><strong>Corporate catering:</strong> Office lunch programs and team events</li>
                                <li><strong>Group subscriptions:</strong> Discounts for 5+ people</li>
                                <li><strong>Employee benefits:</strong> Partner with your company's wellness program</li>
                                <li><strong>Bulk orders:</strong> Special pricing for large orders</li>
                                <li><strong>Custom menus:</strong> Tailored meal options for your group</li>
                            </ul>
                            <p>Contact our business team at <a href="mailto:business@somdultable.com">business@somdultable.com</a> for pricing and details.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Guides Section -->
        <div class="guides-section">
            <h2>📚 Step-by-Step Guides</h2>
            <p style="color: var(--text-gray);">Detailed tutorials to help you make the most of Somdul Table</p>
            
            <div class="guides-grid">
                <div class="guide-card">
                    <div class="guide-icon">🚀</div>
                    <div class="guide-title">Getting Started Guide</div>
                    <div class="guide-desc">Complete walkthrough for new customers from registration to first delivery</div>
                    <a href="#" class="guide-link">Read full guide →</a>
                </div>
                
                <div class="guide-card">
                    <div class="guide-icon">🍽️</div>
                    <div class="guide-title">Meal Customization Guide</div>
                    <div class="guide-desc">Learn how to customize spice levels, dietary preferences, and special requests</div>
                    <a href="#" class="guide-link">Read full guide →</a>
                </div>
                
                <div class="guide-card">
                    <div class="guide-icon">📱</div>
                    <div class="guide-title">Mobile App Tutorial</div>
                    <div class="guide-desc">Maximize your experience with our mobile app features and shortcuts</div>
                    <a href="#" class="guide-link">Read full guide →</a>
                </div>
                
                <div class="guide-card">
                    <div class="guide-icon">📊</div>
                    <div class="guide-title">Nutrition Tracking Guide</div>
                    <div class="guide-desc">Use our nutrition tools to track calories, macros, and health goals</div>
                    <a href="#" class="guide-link">Read full guide →</a>
                </div>
                
                <div class="guide-card">
                    <div class="guide-icon">💳</div>
                    <div class="guide-title">Billing & Payments Guide</div>
                    <div class="guide-desc">Understand billing cycles, payment methods, and subscription management</div>
                    <a href="#" class="guide-link">Read full guide →</a>
                </div>
                
                <div class="guide-card">
                    <div class="guide-icon">🔧</div>
                    <div class="guide-title">Troubleshooting Guide</div>
                    <div class="guide-desc">Solutions for common issues and problems you might encounter</div>
                    <a href="#" class="guide-link">Read full guide →</a>
                </div>
            </div>
        </div>

        <!-- Contact Section -->
        <div class="contact-section" id="contact">
            <div class="contact-header">
                <h2 style="color: white; margin: 0;">📞 Contact Our Support Team</h2>
                <p style="margin: 0; opacity: 0.9;">We're here to help you 24/7</p>
            </div>
            
            <div class="contact-content">
                <div class="contact-methods">
                    <div class="contact-method">
                        <div class="contact-method-icon">📞</div>
                        <div class="contact-method-title">Phone Support</div>
                        <div class="contact-method-info">1-800-SOMDUL (1-800-766-3385)</div>
                        <div class="contact-method-desc">Available 24/7 for urgent issues<br>Average wait time: 2 minutes</div>
                    </div>
                    
                    <div class="contact-method">
                        <div class="contact-method-icon">✉️</div>
                        <div class="contact-method-title">Email Support</div>
                        <div class="contact-method-info">support@somdultable.com</div>
                        <div class="contact-method-desc">Response within 2 hours<br>24/7 monitoring</div>
                    </div>
                    
                    <div class="contact-method">
                        <div class="contact-method-icon">💬</div>
                        <div class="contact-method-title">Live Chat</div>
                        <div class="contact-method-info">Available on website & app</div>
                        <div class="contact-method-desc">Instant responses<br>Mon-Sun 6 AM - 12 AM EST</div>
                    </div>
                    
                    <div class="contact-method">
                        <div class="contact-method-icon">📱</div>
                        <div class="contact-method-title">Text Support</div>
                        <div class="contact-method-info">Text "HELP" to 555-SOMDUL</div>
                        <div class="contact-method-desc">Quick support via SMS<br>Perfect for delivery issues</div>
                    </div>
                </div>

                <!-- Business Hours -->
                <div class="business-hours">
                    <h4>🕐 Customer Support Hours</h4>
                    <div class="hours-grid">
                        <div>
                            <div class="hours-item">
                                <span class="hours-day">Monday - Friday</span>
                                <span class="hours-time">6:00 AM - 12:00 AM EST</span>
                            </div>
                            <div class="hours-item">
                                <span class="hours-day">Saturday - Sunday</span>
                                <span class="hours-time">8:00 AM - 10:00 PM EST</span>
                            </div>
                        </div>
                        <div>
                            <div class="hours-item">
                                <span class="hours-day">Emergency Support</span>
                                <span class="hours-time">24/7 Available</span>
                            </div>
                            <div class="hours-item">
                                <span class="hours-day">Average Response</span>
                                <span class="hours-time">Under 2 hours</span>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem; padding: 1rem; background: rgba(var(--curry), 0.1); border-radius: var(--radius-sm); text-align: center;">
                        <p style="margin: 0; color: var(--curry); font-weight: 600;">
                            🚨 For food safety emergencies or severe allergic reactions, call 911 first, then contact us immediately at 1-800-SOMDUL
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Resources -->
        <div style="background: var(--white); border-radius: var(--radius-lg); box-shadow: var(--shadow-medium); padding: 2rem; margin-bottom: 2rem;">
            <h2>🔗 Additional Resources</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
                <div style="text-align: center;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📋</div>
                    <h4>Community Forum</h4>
                    <p style="color: var(--text-gray); margin-bottom: 1rem;">Connect with other Somdul Table customers, share recipes, and get tips</p>
                    <a href="#" style="color: var(--curry); text-decoration: none; font-weight: 600;">Visit Forum →</a>
                </div>
                
                <div style="text-align: center;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📺</div>
                    <h4>Video Tutorials</h4>
                    <p style="color: var(--text-gray); margin-bottom: 1rem;">Watch step-by-step videos on using our service and preparing meals</p>
                    <a href="#" style="color: var(--curry); text-decoration: none; font-weight: 600;">Watch Videos →</a>
                </div>
                
                <div style="text-align: center;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📰</div>
                    <h4>Blog & Tips</h4>
                    <p style="color: var(--text-gray); margin-bottom: 1rem;">Read about Thai cuisine, nutrition tips, and healthy eating guides</p>
                    <a href="#" style="color: var(--curry); text-decoration: none; font-weight: 600;">Read Blog →</a>
                </div>
                
                <div style="text-align: center;">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📱</div>
                    <h4>System Status</h4>
                    <p style="color: var(--text-gray); margin-bottom: 1rem;">Check if we're experiencing any service disruptions or maintenance</p>
                    <a href="#" style="color: var(--curry); text-decoration: none; font-weight: 600;">Check Status →</a>
                </div>
            </div>
        </div>

        <!-- Quick Actions for Logged In Users -->
        <?php if ($is_logged_in): ?>
        <div style="background: linear-gradient(135deg, var(--sage) 0%, rgba(173, 184, 157, 0.8) 100%); border-radius: var(--radius-lg); color: white; padding: 2rem; text-align: center;">
            <h3 style="color: white; margin-bottom: 1rem;">⚡ Quick Actions for Your Account</h3>
            <div style="display: flex; flex-wrap: wrap; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                <a href="dashboard.php" style="background: rgba(255,255,255,0.2); color: white; padding: 0.8rem 1.5rem; border-radius: 25px; text-decoration: none; font-weight: 600; transition: var(--transition);">View Dashboard</a>
                <a href="support-center.php" style="background: rgba(255,255,255,0.2); color: white; padding: 0.8rem 1.5rem; border-radius: 25px; text-decoration: none; font-weight: 600; transition: var(--transition);">Submit Ticket</a>
                <a href="#" style="background: rgba(255,255,255,0.2); color: white; padding: 0.8rem 1.5rem; border-radius: 25px; text-decoration: none; font-weight: 600; transition: var(--transition);">Order History</a>
                <a href="#" style="background: rgba(255,255,255,0.2); color: white; padding: 0.8rem 1.5rem; border-radius: 25px; text-decoration: none; font-weight: 600; transition: var(--transition);">Account Settings</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Search functionality
        const helpSearch = document.getElementById('helpSearch');
        const searchResults = document.getElementById('searchResults');
        const faqItems = document.querySelectorAll('.faq-item');

        helpSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            if (searchTerm.length < 2) {
                searchResults.style.display = 'none';
                showAllFAQs();
                return;
            }

            const results = [];
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question span').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
                
                if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                    results.push({
                        element: item,
                        question: item.querySelector('.faq-question span').textContent,
                        relevance: question.includes(searchTerm) ? 2 : 1
                        });
                }
            });
        });

        // Quick link interactions
        document.querySelectorAll('.quick-link-card').forEach(card => {
            card.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href && href.startsWith('#faq-')) {
                    e.preventDefault();
                    const category = href.replace('#faq-', '');
                    
                    // Scroll to FAQ section
                    document.querySelector('.faq-section').scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    
                    // Activate the category after a short delay
                    setTimeout(() => {
                        const categoryBtn = document.querySelector(`[data-category="${category}"]`);
                        if (categoryBtn) {
                            categoryBtn.click();
                        }
                    }, 500);
                }
            });
        });

        // Contact method interactions
        document.querySelectorAll('.contact-method').forEach(method => {
            method.addEventListener('click', function() {
                const title = this.querySelector('.contact-method-title').textContent;
                const info = this.querySelector('.contact-method-info').textContent;
                
                if (title.includes('Phone')) {
                    window.location.href = `tel:${info.replace(/\D/g, '')}`;
                } else if (title.includes('Email')) {
                    window.location.href = `mailto:${info}`;
                } else if (title.includes('Text')) {
                    // Show instructions for text support
                    alert(`To get text support:\n1. Open your text messages\n2. Send "HELP" to 555-766-3385\n3. Describe your issue\n4. Get instant assistance!`);
                } else if (title.includes('Live Chat')) {
                    // This would typically open a chat widget
                    alert('Live chat feature coming soon! For now, please use phone or email support.');
                }
            });
        });

        // Enhanced search with suggestions
        const searchSuggestions = [
            'how to order meals',
            'change delivery address',
            'cancel subscription',
            'food allergies',
            'payment methods',
            'delivery times',
            'meal plans',
            'nutrition information',
            'refund policy',
            'contact support'
        ];

        helpSearch.addEventListener('focus', function() {
            if (this.value === '') {
                showSearchSuggestions();
            }
        });

        function showSearchSuggestions() {
            const suggestionsHTML = searchSuggestions.map(suggestion => 
                `<div class="search-suggestion" style="padding: 0.5rem 1rem; cursor: pointer; border-bottom: 1px solid var(--border-light);">${suggestion}</div>`
            ).join('');
            
            searchResults.innerHTML = `
                <div style="background: var(--white); border: 1px solid var(--border-light); border-radius: var(--radius-md); margin-top: 0.5rem; max-height: 200px; overflow-y: auto;">
                    <div style="padding: 0.8rem 1rem; background: var(--cream); font-weight: 600;">Popular searches:</div>
                    ${suggestionsHTML}
                </div>
            `;
            searchResults.style.display = 'block';

            // Add click handlers for suggestions
            document.querySelectorAll('.search-suggestion').forEach(suggestion => {
                suggestion.addEventListener('click', function() {
                    helpSearch.value = this.textContent;
                    helpSearch.dispatchEvent(new Event('input'));
                    searchResults.style.display = 'none';
                });
            });
        }

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!helpSearch.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });

        // Keyboard navigation for FAQ
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close all FAQs
                document.querySelectorAll('.faq-question.active').forEach(q => {
                    q.classList.remove('active');
                    q.nextElementSibling.classList.remove('active');
                });
                
                // Hide search results
                searchResults.style.display = 'none';
                helpSearch.blur();
            }
        });

        // Auto-expand FAQ based on URL hash
        window.addEventListener('load', function() {
            const hash = window.location.hash;
            if (hash) {
                const targetElement = document.querySelector(hash);
                if (targetElement && targetElement.classList.contains('faq-item')) {
                    targetElement.querySelector('.faq-question').click();
                    setTimeout(() => {
                        targetElement.scrollIntoView({ behavior: 'smooth' });
                    }, 300);
                }
            }
        });

        // Track FAQ interactions for analytics
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', function() {
                const questionText = this.querySelector('span').textContent;
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'faq_interaction', {
                        'faq_question': questionText,
                        'event_category': 'help_center'
                    });
                }
            });
        });

        // Track search queries
        helpSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value.trim();
                if (searchTerm && typeof gtag !== 'undefined') {
                    gtag('event', 'help_search', {
                        'search_term': searchTerm,
                        'event_category': 'help_center'
                    });
                }
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Set focus on search if no hash in URL
            if (!window.location.hash) {
                setTimeout(() => {
                    helpSearch.focus();
                }, 500);
            }

            // Add loading states for contact methods
            document.querySelectorAll('.contact-method').forEach(method => {
                method.addEventListener('click', function() {
                    this.style.opacity = '0.7';
                    setTimeout(() => {
                        this.style.opacity = '1';
                    }, 200);
                });
            });

            // Initialize tooltips for better UX
            const tooltips = [
                { selector: '.search-input', text: 'Try searching for specific topics like "delivery" or "billing"' },
                { selector: '.faq-category-btn', text: 'Filter questions by category' },
                { selector: '.contact-method', text: 'Click to contact us via this method' }
            ];

            tooltips.forEach(tooltip => {
                document.querySelectorAll(tooltip.selector).forEach(element => {
                    element.setAttribute('title', tooltip.text);
                });
            });

            console.log('Help Center loaded successfully');
            
            // Track page view
            if (typeof gtag !== 'undefined') {
                gtag('event', 'page_view', {
                    page_title: 'Help Center',
                    page_location: window.location.href
                });
            }
        });

        // Advanced search with highlights
        function highlightSearchTerm(text, searchTerm) {
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            return text.replace(regex, '<mark style="background-color: yellow; padding: 0 2px;">$1</mark>');
        }

        // Contact form submission (if needed)
        function submitContactForm(formData) {
            // This would handle contact form submissions
            console.log('Contact form submitted:', formData);
            
            // Show success message
            alert('Thank you for contacting us! We\'ll respond within 2 hours.');
        }

        // Emergency contact shortcut
        function emergencyContact() {
            if (confirm('This will call our emergency support line. Continue?')) {
                window.location.href = 'tel:18007663385';
            }
        }

        // Add emergency contact shortcut (Ctrl/Cmd + E)
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                emergencyContact();
            }
        });

        // Feedback collection
        function collectFeedback() {
            const rating = prompt('How helpful was this page? (1-5 stars)');
            if (rating && rating >= 1 && rating <= 5) {
                const feedback = prompt('Any additional feedback? (optional)');
                
                // Send feedback (would be AJAX call in real implementation)
                console.log('Feedback collected:', { rating, feedback });
                alert('Thank you for your feedback!');
                
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'feedback_submitted', {
                        'rating': rating,
                        'event_category': 'help_center'
                    });
                }
            }
        }

        // Add feedback button (floating)
        const feedbackBtn = document.createElement('button');
        feedbackBtn.innerHTML = '💬 Feedback';
        feedbackBtn.style.cssText = `
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--curry);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: var(--shadow-medium);
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
            z-index: 1000;
            transition: var(--transition);
        `;
        feedbackBtn.addEventListener('click', collectFeedback);
        feedbackBtn.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        feedbackBtn.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
        document.body.appendChild(feedbackBtn);

        // Print functionality
        function printHelpPage() {
            window.print();
        }

        // Add keyboard shortcut for printing (Ctrl/Cmd + P handled by browser)
        console.log('Help Center Keyboard Shortcuts:');
        console.log('- Escape: Close FAQs and search results');
        console.log('- Ctrl/Cmd + E: Emergency contact');
        console.log('- Ctrl/Cmd + P: Print page');
    </script>
</body>
</html>

           