<?php
/**
 * Somdul Table - About Us Page
 * File: about.php
 * Description: Learn about Somdul Table's mission, chefs, and authentic Thai cuisine journey
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Our Story & Mission | Somdul Table</title>
    <meta name="description" content="Discover the story behind Somdul Table - authentic Thai cuisine crafted by expert chefs and delivered fresh to your door. Learn about our mission to bring Thailand's flavors to America.">
    
    <style>
        /* BaticaSans Font Import - Local Files */
        @font-face {
            font-family: 'BaticaSans';
            src: url('./Font/BaticaSans-Regular.woff2') format('woff2'),
                url('./Font/BaticaSans-Regular.woff') format('woff'),
                url('./Font/BaticaSans-Regular.ttf') format('truetype');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'BaticaSans';
            src: url('./Font/BaticaSans-Italic.woff2') format('woff2'),
                url('./Font/BaticaSans-Italic.woff') format('woff'),
                url('./Font/BaticaSans-Italic.ttf') format('truetype');
            font-weight: 400;
            font-style: italic;
            font-display: swap;
        }

        /* Fallback for bold/medium weights - browser will simulate them */
        @font-face {
            font-family: 'BaticaSans';
            src: url('./Font/BaticaSans-Regular.woff2') format('woff2'),
                url('./Font/BaticaSans-Regular.woff') format('woff'),
                url('./Font/BaticaSans-Regular.ttf') format('truetype');
            font-weight: 500;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'BaticaSans';
            src: url('./Font/BaticaSans-Regular.woff2') format('woff2'),
                url('./Font/BaticaSans-Regular.woff') format('woff'),
                url('./Font/BaticaSans-Regular.ttf') format('truetype');
            font-weight: 700;
            font-style: normal;
            font-display: swap;
        }

        /* IMPROVED CSS Custom Properties for Somdul Table Design System - COLOR HIERARCHY ONLY */
        :root {
            /* LEVEL 1 (MOST IMPORTANT): BROWN #bd9379 + WHITE */
            --brown: #bd9379;
            --white: #ffffff;
            
            /* LEVEL 2 (SECONDARY): CREAM #ece8e1 */
            --cream: #ece8e1;
            
            /* LEVEL 3 (SUPPORTING): SAGE #adb89d */
            --sage: #adb89d;
            
            /* LEVEL 4 (ACCENT/CONTRAST - LEAST USED): CURRY #cf723a */
            --curry: #cf723a;
            
            /* Text colors using brown hierarchy */
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --border-light: #d4c4b8; /* Brown-tinted border */
            
            /* Shadows using brown as base (Level 1) */
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

        /* Typography using BaticaSans - LEVEL 1: Brown for headings */
        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--brown); /* LEVEL 1: Brown instead of text-dark */
        }

        /* Promotional Banner Styles - LEVEL 4: Curry for special promos */
        .promo-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #cf723a; /* LEVEL 4: Curry for promotional banner */
            color: var(--white); /* LEVEL 1: White */
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
            color: var(--white); /* LEVEL 1: White */
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
            background: #ece8e1;
            backdrop-filter: blur(10px);
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
        }

        .navbar, .navbar * {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: var(--brown); /* LEVEL 1: Brown */
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: var(--brown); /* LEVEL 1: Solid brown instead of gradient */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white); /* LEVEL 1: White text */
            font-size: 1.5rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--brown); /* LEVEL 1: Brown */
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
            color: var(--brown); /* LEVEL 1: Brown hover */
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
            background: var(--brown); /* LEVEL 1: Brown primary */
            color: var(--white); /* LEVEL 1: White text */
            box-shadow: var(--shadow-soft);
        }

        .btn-primary:hover {
            background: #a8855f; /* Darker brown on hover */
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: var(--brown); /* LEVEL 1: Brown */
            border: 2px solid var(--brown); /* LEVEL 1: Brown border */
        }

        .btn-secondary:hover {
            background: var(--brown); /* LEVEL 1: Brown */
            color: var(--white); /* LEVEL 1: White */
        }

        /* Profile Icon Styles */
        .profile-link {
            text-decoration: none;
            transition: var(--transition);
        }

        .profile-icon {
            width: 45px;
            height: 45px;
            background: var(--brown); /* LEVEL 1: Brown */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white); /* LEVEL 1: White */
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
        }

        .profile-icon:hover {
            background: #a8855f; /* Darker brown on hover */
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .profile-icon svg {
            width: 24px;
            height: 24px;
        }

        /* Hero Section */
        .hero-section {
            padding-top: 120px;
            min-height: 80vh;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%); /* LEVEL 2: Cream background */
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            font-weight: 800;
            color: var(--brown); /* LEVEL 1: Brown title */
            line-height: 1.1;
            font-family: 'BaticaSans', sans-serif;
        }

        .hero-content p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: var(--text-gray);
            line-height: 1.6;
            font-family: 'BaticaSans', sans-serif;
        }

        .hero-image {
            position: relative;
            height: 500px;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-medium);
        }

        .hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Story Section */
        .story-section {
            padding: 6rem 2rem;
            background: var(--white); /* LEVEL 1: White */
        }

        .story-container {
            max-width: 1000px;
            margin: 0 auto;
            text-align: center;
        }

        .story-title {
            font-size: 2.8rem;
            margin-bottom: 2rem;
            color: var(--brown); /* LEVEL 1: Brown */
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .story-subtitle {
            font-size: 1.4rem;
            margin-bottom: 3rem;
            color: var(--curry); /* LEVEL 4: Curry accent */
            font-family: 'BaticaSans', sans-serif;
            font-weight: 500;
            font-style: italic;
        }

        .story-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .story-content p {
            margin-bottom: 1.5rem;
        }

        /* Values Section */
        .values-section {
            padding: 6rem 2rem;
            background: var(--cream); /* LEVEL 2: Cream background */
        }

        .values-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .values-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .values-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--brown); /* LEVEL 1: Brown */
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .values-subtitle {
            font-size: 1.2rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
        }

        .value-card {
            background: var(--white); /* LEVEL 1: White */
            padding: 3rem 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            text-align: center;
            transition: var(--transition);
        }

        .value-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }

        .value-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            display: block;
        }

        .value-title {
            font-size: 1.4rem;
            margin-bottom: 1rem;
            color: var(--brown); /* LEVEL 1: Brown */
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .value-description {
            color: var(--text-gray);
            line-height: 1.6;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Chefs Section */
        .chefs-section {
            padding: 6rem 2rem;
            background: var(--white); /* LEVEL 1: White */
        }

        .chefs-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .chefs-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .chefs-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--brown); /* LEVEL 1: Brown */
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .chefs-subtitle {
            font-size: 1.2rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .chefs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 3rem;
        }

        .chef-card {
            background: var(--white); /* LEVEL 1: White */
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
        }

        .chef-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-medium);
        }

        .chef-image {
            width: 100%;
            height: 300px;
            background: linear-gradient(45deg, var(--brown), var(--sage)); /* LEVEL 1 & 3: Brown and sage */
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white); /* LEVEL 1: White */
            font-size: 4rem;
            position: relative;
        }

        .chef-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .chef-content {
            padding: 2rem;
        }

        .chef-name {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--brown); /* LEVEL 1: Brown */
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .chef-role {
            font-size: 1rem;
            color: var(--curry); /* LEVEL 4: Curry */
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .chef-description {
            color: var(--text-gray);
            line-height: 1.6;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Mission Section */
        .mission-section {
            padding: 6rem 2rem;
            background: linear-gradient(135deg, var(--brown) 0%, var(--sage) 100%); /* LEVEL 1 & 3: Brown to sage */
            color: var(--white); /* LEVEL 1: White */
        }

        .mission-container {
            max-width: 1000px;
            margin: 0 auto;
            text-align: center;
        }

        .mission-title {
            font-size: 2.8rem;
            margin-bottom: 2rem;
            color: var(--white) !important; /* LEVEL 1: White override */
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .mission-content {
            font-size: 1.3rem;
            line-height: 1.8;
            margin-bottom: 3rem;
            font-family: 'BaticaSans', sans-serif;
            color: rgba(255, 255, 255, 0.9);
        }

        .mission-cta {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-white {
            background: var(--white); /* LEVEL 1: White */
            color: var(--brown); /* LEVEL 1: Brown */
            border: 2px solid var(--white); /* LEVEL 1: White */
        }

        .btn-white:hover {
            background: transparent;
            color: var(--white); /* LEVEL 1: White */
        }

        .btn-outline-white {
            background: transparent;
            color: var(--white); /* LEVEL 1: White */
            border: 2px solid var(--white); /* LEVEL 1: White */
        }

        .btn-outline-white:hover {
            background: var(--white); /* LEVEL 1: White */
            color: var(--brown); /* LEVEL 1: Brown */
        }

        /* Testimonials Section */
        .testimonials-section {
            padding: 6rem 2rem;
            background: var(--cream); /* LEVEL 2: Cream */
        }

        .testimonials-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .testimonials-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .testimonials-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--brown); /* LEVEL 1: Brown */
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .testimonials-subtitle {
            font-size: 1.2rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .testimonial-card {
            background: var(--white); /* LEVEL 1: White */
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .testimonial-quote {
            font-size: 1.1rem;
            line-height: 1.7;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            font-family: 'BaticaSans', sans-serif;
            font-style: italic;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            background: var(--brown); /* LEVEL 1: Brown */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white); /* LEVEL 1: White */
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
        }

        .author-info h4 {
            color: var(--brown); /* LEVEL 1: Brown */
            margin: 0;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .author-info p {
            color: var(--text-gray);
            margin: 0;
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .stars {
            display: flex;
            gap: 0.2rem;
            margin-bottom: 1rem;
        }

        .star {
            color: #ffc107;
            font-size: 1.2rem;
        }

        /* CTA Section */
        .cta-section {
            padding: 6rem 2rem;
            background: var(--white); /* LEVEL 1: White */
        }

        .cta-container {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .cta-title {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: var(--brown); /* LEVEL 1: Brown */
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .cta-subtitle {
            font-size: 1.2rem;
            margin-bottom: 3rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .promo-banner {
                font-size: 12px;
                padding: 6px 15px;
            }
            
            .promo-banner-content {
                flex-direction: column;
                gap: 5px;
            }
            
            .promo-close {
                right: 10px;
            }
            
            .hero-section {
                padding-top: 100px;
            }

            .hero-container {
                grid-template-columns: 1fr;
                gap: 2rem;
                text-align: center;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }

            .hero-content p {
                font-size: 1rem;
            }

            .nav-links {
                display: none;
            }

            .values-grid,
            .chefs-grid,
            .testimonials-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .story-title,
            .values-title,
            .chefs-title,
            .testimonials-title,
            .mission-title,
            .cta-title {
                font-size: 2rem;
            }

            .mission-content {
                font-size: 1.1rem;
            }

            .mission-cta,
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 480px) {
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .story-title,
            .values-title,
            .chefs-title,
            .testimonials-title,
            .mission-title,
            .cta-title {
                font-size: 1.8rem;
            }

            .value-card,
            .chef-content,
            .testimonial-card {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Promotional Banner -->
    <div class="promo-banner" id="promoBanner">
        <div class="promo-banner-content">
            <span class="promo-icon">🇹🇭</span>
            <span class="promo-text">Authentic Thai Flavors from Bangkok to Your Table</span>
            <span class="promo-icon">✨</span>
        </div>
        <button class="promo-close" onclick="closePromoBanner()" title="Close">×</button>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; max-width: 1200px; margin: 0 auto; width: 100%;">
            <a href="home2.php" class="logo">
                <img src="./assets/image/LOGO_BG2.png" alt="Somdul Table" style="height: 80px; width: auto;">
            </a>
            
            <ul class="nav-links">
                <li><a href="./menus.php">Menu</a></li>
                <li><a href="./meal-kits.php">Meal-Kits</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="./about.php" class="active">About</a></li>
                <li><a href="./contact.php">Contact</a></li>
            </ul>
            
            <div class="nav-actions">
                <?php if ($is_logged_in): ?>
                    <!-- User is logged in - show profile icon -->
                    <a href="dashboard.php" class="profile-link" title="Go to Dashboard">
                        <div class="profile-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                    </a>
                <?php else: ?>
                    <!-- User is not logged in - show sign in button -->
                    <a href="login.php" class="btn btn-secondary">Sign In</a>
                <?php endif; ?>
                <a href="subscribe.php" class="btn btn-primary">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" id="home">
        <div class="hero-container">
            <div class="hero-content">
                <h1>Bringing Thailand's Heart to Your Table</h1>
                <p>We're more than a meal delivery service. We're a bridge between the bustling street markets of Bangkok and your dining room, crafted with passion by authentic Thai chefs who carry generations of culinary wisdom.</p>
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <a href="#our-story" class="btn btn-primary">Our Story</a>
                    <a href="#our-chefs" class="btn btn-secondary">Meet Our Chefs</a>
                </div>
            </div>
            <div class="hero-image">
                <img src="assets/image/thai-chef-cooking.jpg" alt="Thai chef preparing authentic dishes" 
                     onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:4rem;color:var(--white)\'>👨‍🍳</div>'">
            </div>
        </div>
    </section>

    <!-- Story Section -->
    <section class="story-section" id="our-story">
        <div class="story-container">
            <h2 class="story-title">Our Story</h2>
            <p class="story-subtitle">"Somdul" means "balance" in Thai – the perfect harmony of flavors that defines our cuisine</p>
            
            <div class="story-content">
                <p>It all started with a simple longing for home. When our founder, Chef Siriporn, moved to America, she missed the vibrant flavors of her grandmother's kitchen in Bangkok. The aromatic curry pastes ground fresh each morning, the perfect balance of sweet, sour, salty, and spicy that danced on every plate.</p>

                <p>We realized that authentic Thai food isn't just about recipes – it's about the soul, the technique, and the stories passed down through generations. That's why we partnered with master chefs directly from Thailand, each bringing their unique regional expertise and family traditions to create something unprecedented in America.</p>

                <p>Today, Somdul Table connects food lovers across the United States with the true essence of Thai cuisine. We're not just delivering meals; we're sharing culture, preserving tradition, and creating new memories around the dinner table.</p>

                <p>Every dish tells a story. Every meal is a journey. Welcome to our table.</p>
            </div>
        </div>
    </section>

    <!-- Values Section -->
    <section class="values-section" id="our-values">
        <div class="values-container">
            <div class="values-header">
                <h2 class="values-title">What Drives Us</h2>
                <p class="values-subtitle">The principles that guide every dish we create and every relationship we build</p>
            </div>

            <div class="values-grid">
                <div class="value-card">
                    <span class="value-icon">🌿</span>
                    <h3 class="value-title">Authentic Ingredients</h3>
                    <p class="value-description">We source traditional Thai ingredients directly from trusted suppliers, ensuring every dish maintains its authentic flavor profile and cultural integrity.</p>
                </div>

                <div class="value-card">
                    <span class="value-icon">👨‍🍳</span>
                    <h3 class="value-title">Master Craftsmanship</h3>
                    <p class="value-description">Our chefs are trained in traditional Thai cooking methods, bringing decades of experience and family recipes to create restaurant-quality meals.</p>
                </div>

                <div class="value-card">
                    <span class="value-icon">🌱</span>
                    <h3 class="value-title">Sustainable Practices</h3>
                    <p class="value-description">From eco-friendly packaging to supporting local farms, we're committed to practices that respect both our planet and our communities.</p>
                </div>

                <div class="value-card">
                    <span class="value-icon">❤️</span>
                    <h3 class="value-title">Cultural Bridge</h3>
                    <p class="value-description">We believe food is the universal language that connects cultures, bringing the warmth of Thai hospitality to American homes.</p>
                </div>

                <div class="value-card">
                    <span class="value-icon">🍃</span>
                    <h3 class="value-title">Health & Wellness</h3>
                    <p class="value-description">Thai cuisine naturally emphasizes fresh vegetables, lean proteins, and balanced nutrition – supporting both taste and wellness.</p>
                </div>

                <div class="value-card">
                    <span class="value-icon">🤝</span>
                    <h3 class="value-title">Community First</h3>
                    <p class="value-description">We're not just feeding customers; we're building a community of food lovers who appreciate authentic flavors and cultural exchange.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Chefs Section -->
    <section class="chefs-section" id="our-chefs">
        <div class="chefs-container">
            <div class="chefs-header">
                <h2 class="chefs-title">Meet Our Master Chefs</h2>
                <p class="chefs-subtitle">The culinary artists who bring authentic Thai flavors to your table</p>
            </div>

            <div class="chefs-grid">
                <div class="chef-card">
                    <div class="chef-image">
                        <img src="assets/image/chef-siriporn.jpg" alt="Chef Siriporn Pattanakul" 
                             onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:4rem;color:var(--white)\'>👩‍🍳</div>'">
                    </div>
                    <div class="chef-content">
                        <h3 class="chef-name">Chef Siriporn Pattanakul</h3>
                        <p class="chef-role">Head Chef & Co-Founder</p>
                        <p class="chef-description">Born in Bangkok, Chef Siriporn learned the art of Thai cooking from her grandmother. With over 15 years of experience in Bangkok's finest restaurants, she specializes in royal Thai cuisine and traditional curry preparations. Her passion lies in preserving authentic recipes while making them accessible to modern lifestyles.</p>
                    </div>
                </div>

                <div class="chef-card">
                    <div class="chef-image">
                        <img src="assets/image/chef-narong.jpg" alt="Chef Narong Thanakit" 
                             onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:4rem;color:var(--white)\'>👨‍🍳</div>'">
                    </div>
                    <div class="chef-content">
                        <h3 class="chef-name">Chef Narong Thanakit</h3>
                        <p class="chef-role">Regional Cuisine Specialist</p>
                        <p class="chef-description">Hailing from Chiang Mai in Northern Thailand, Chef Narong brings the bold, rustic flavors of his homeland to Somdul Table. His expertise in regional Thai cuisines ensures our menu represents the diverse culinary landscape of Thailand, from spicy som tam to rich khao soi.</p>
                    </div>
                </div>

                <div class="chef-card">
                    <div class="chef-image">
                        <img src="assets/image/chef-pranee.jpg" alt="Chef Pranee Suksomboon" 
                             onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;font-size:4rem;color:var(--white)\'>👩‍🍳</div>'">
                    </div>
                    <div class="chef-content">
                        <h3 class="chef-name">Chef Pranee Suksomboon</h3>
                        <p class="chef-role">Dessert & Street Food Expert</p>
                        <p class="chef-description">From the bustling streets of Bangkok, Chef Pranee mastered the art of Thai street food and traditional desserts. Her creative approach to classic recipes brings the vibrant energy of Thai markets to every dish, specializing in pad thai, mango sticky rice, and innovative fusion creations.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission Section -->
    <section class="mission-section" id="our-mission">
        <div class="mission-container">
            <h2 class="mission-title">Our Mission</h2>
            <p class="mission-content">
                We envision a world where authentic Thai cuisine is accessible to everyone, where busy families can experience the joy of restaurant-quality meals without compromise, and where the rich culinary traditions of Thailand continue to thrive in hearts and homes across America.
            </p>
            <p class="mission-content">
                Through Somdul Table, we're not just delivering food – we're preserving culture, supporting authentic culinary artistry, and creating meaningful connections between Thai heritage and American tables.
            </p>
            <div class="mission-cta">
                <a href="menus.php" class="btn btn-white">Explore Our Menu</a>
                <a href="subscribe.php" class="btn btn-outline-white">Start Your Journey</a>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section" id="testimonials">
        <div class="testimonials-container">
            <div class="testimonials-header">
                <h2 class="testimonials-title">What Our Community Says</h2>
                <p class="testimonials-subtitle">Real stories from families who've made Somdul Table part of their lives</p>
            </div>

            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="testimonial-quote">"As someone who lived in Bangkok for three years, I can honestly say Somdul Table captures the authentic flavors I fell in love with. The green curry tastes exactly like what I had at my favorite local restaurant in Sukhumvit."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">MJ</div>
                        <div class="author-info">
                            <h4>Michael Johnson</h4>
                            <p>Food Enthusiast • Seattle, WA</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="testimonial-quote">"Somdul Table has become our family's weekly tradition. My kids love the pad thai, and I love that we're getting authentic, healthy meals without the stress of cooking. It's brought our family closer around the dinner table."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">LC</div>
                        <div class="author-info">
                            <h4>Lisa Chen</h4>
                            <p>Working Mother • San Francisco, CA</p>
                        </div>
                    </div>
                </div>

                <div class="testimonial-card">
                    <div class="stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="testimonial-quote">"The quality and attention to detail is incredible. You can taste the difference when chefs who truly understand Thai cuisine prepare your meals. Every dish tells a story, and I love learning about the origins through their descriptions."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">RM</div>
                        <div class="author-info">
                            <h4>Robert Martinez</h4>
                            <p>Food Blogger • Austin, TX</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section" id="get-started">
        <div class="cta-container">
            <h2 class="cta-title">Ready to Experience Authentic Thai?</h2>
            <p class="cta-subtitle">Join thousands of families who've made Somdul Table their go-to for authentic Thai cuisine. Your culinary journey to Thailand starts here.</p>
            
            <div class="cta-buttons">
                <a href="subscribe.php" class="btn btn-primary">Start Your Subscription</a>
                <a href="menus.php" class="btn btn-secondary">Browse Our Menu</a>
            </div>
        </div>
    </section>

    <script>
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

        // Smooth scrolling for navigation links
        document.addEventListener('DOMContentLoaded', function() {
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

            // Animation on scroll
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

            // Animate cards on scroll
            const animatedElements = document.querySelectorAll('.value-card, .chef-card, .testimonial-card');
            animatedElements.forEach(element => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(30px)';
                element.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
                observer.observe(element);
            });
        });

        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(236, 232, 225, 0.98)';
            } else {
                navbar.style.background = '#ece8e1';
            }
        });
    </script>
</body>
</html>