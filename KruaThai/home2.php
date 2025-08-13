<?php
/**
 * Somdul Table - Home Page with Database Integration
 * File: home2.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config/database.php';

try {
    // Fetch categories for navigation
    $categories = [];
    $stmt = $pdo->prepare("
        SELECT id, name, name_thai 
        FROM menu_categories 
        WHERE is_active = 1 
        ORDER BY sort_order ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch featured menus for display (limit to 6 for carousel)
    $stmt = $pdo->prepare("
        SELECT m.*, c.name AS category_name, c.name_thai AS category_name_thai
        FROM menus m 
        LEFT JOIN menu_categories c ON m.category_id = c.id 
        WHERE m.is_available = 1 
        ORDER BY m.is_featured DESC, m.updated_at DESC 
        LIMIT 6
    ");
    $stmt->execute();
    $featured_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Home page database error: " . $e->getMessage());
    $categories = [];
    $featured_menus = [];
}

// Category icons mapping
$category_icons = [
    'Rice Bowls' => '<path d="M12 2c-1.1 0-2 .9-2 2v2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-2V4c0-1.1-.9-2-2-2zm0 2v2h-2V4h2zm-4 4h8v2h-8V8zm0 4h8v6H8v-6z"/>',
    'Thai Curries' => '<path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
    'Noodle Dishes' => '<path d="M22 2v20H2V2h20zm-2 2H4v16h16V4zM6 8h12v2H6V8zm0 4h12v2H6v-2zm0 4h8v2H6v-2z"/>',
    'Stir Fry' => '<path d="M12.5 3.5c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5S10.17 2 11 2s1.5.67 1.5 1.5zM20 8H4c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2zm0 10H4v-8h16v8zm-8-6c1.38 0 2.5 1.12 2.5 2.5S13.38 17 12 17s-2.5-1.12-2.5-2.5S10.62 12 12 12z"/>',
    'Rice Dishes' => '<path d="M18 3H6c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H6V5h12v14zM8 7h8v2H8V7zm0 4h8v2H8v-2zm0 4h6v2H8v-2z"/>',
    'Soups' => '<path d="M4 18h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2zm0-10h16v8H4V8zm8-4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"/>',
    'Salads' => '<path d="M7 10c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zm8 0c0-1.1.9-2 2-2s2 .9 2 2-.9 2-2 2-2-.9-2-2zM12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8 0-1.12.23-2.18.65-3.15C6.53 8.51 8 8 9.64 8c.93 0 1.83.22 2.64.61.81-.39 1.71-.61 2.64-.61 1.64 0 3.11.51 4.35.85.42.97.65 2.03.65 3.15 0 4.41-3.59 8-8 8z"/>',
    'Desserts' => '<path d="M12 3L8 6.5h8L12 3zm0 18c4.97 0 9-4.03 9-9H3c0 4.97 4.03 9 9 9zm0-16L8.5 8h7L12 5z"/>',
    'Beverages' => '<path d="M5 4v3h5.5v12h3V7H19V4H5z"/>'
];

// Default icon for categories not in mapping
$default_icon = '<path d="M12 2c-1.1 0-2 .9-2 2v2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-2V4c0-1.1-.9-2-2-2zm0 2v2h-2V4h2zm-4 4h8v2h-8V8zm0 4h8v6H8v-6z"/>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Somdul Table - Authentic Thai Restaurant Management</title>
    <meta name="description" content="Experience authentic Thai cuisine with Somdul Table - Your premier Thai restaurant management system in the US">
    
    <style>
    /* How It Works Section - Updated Styles */
    .hiw-items {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        margin-top: 3rem;
    }

    .hiw-step {
        position: relative; /* Add relative positioning */
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 0; /* Remove padding since image will fill */
        background: var(--white);
        border-radius: 15px;
        box-shadow: var(--shadow-soft);
        transition: var(--transition);
        overflow: hidden; /* Ensure image doesn't overflow rounded corners */
        height: 400px; /* Set fixed height for consistency */
    }

    .hiw-step--with-border {
        border: 2px solid var(--cream);
    }

    .hiw-step:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-medium);
    }

    .hiw-step-image {
        position: absolute; /* Position image absolutely to fill entire card */
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 15px; /* Match parent border radius */
        z-index: 1; /* Behind text content */
    }

    .hiw-step-text {
        position: absolute; /* Position text absolutely over image */
        bottom: 0;
        left: 0;
        right: 0;
        text-align: center;
        padding: 1.2rem; /* Reduced padding for smaller text area */
        background: rgba(255, 255, 255, 0.9); /* Semi-transparent white background */
        backdrop-filter: blur(10px); /* Add blur effect for better readability */
        border-radius: 0 0 15px 15px; /* Rounded bottom corners */
        z-index: 2; /* Above image */
        min-height: 120px; /* Fixed minimum height for consistency */
        display: flex;
        flex-direction: column;
        justify-content: center; /* Center content vertically */
    }

    .hiw-step-text .font-bold {
        font-size: 1.1rem; /* Reduced from 1.3rem */
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.6rem; /* Reduced margin */
        font-family: 'BaticaSans', sans-serif;
    }

    .step-text {
        color: var(--text-dark); /* Changed from gray to dark for better contrast */
        line-height: 1.4; /* Reduced line height */
        font-family: 'BaticaSans', sans-serif;
        font-weight: 500; /* Slightly bolder for better readability */
        font-size: 0.9rem; /* Smaller text size */
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .hiw-items {
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .hiw-step {
            height: 300px; /* Smaller height for mobile */
        }
        
        .hiw-step-text {
            padding: 1rem; /* Reduced padding */
            min-height: 100px; /* Consistent height for mobile */
        }
        
        .hiw-step-text .font-bold {
            font-size: 1rem; /* Smaller title */
            margin-bottom: 0.5rem;
        }
        
        .step-text {
            font-size: 0.8rem; /* Smaller text */
            line-height: 1.3;
        }
    }

    @media (max-width: 480px) {
        .hiw-items {
            grid-template-columns: 1fr;
        }
        
        .hiw-step {
            height: 350px; /* Adjust height for single column */
        }
        
        .hiw-step-text {
            padding: 1rem; /* Consistent padding */
            min-height: 110px; /* Consistent height for single column */
        }
        
        .hiw-step-text .font-bold {
            font-size: 1rem;
        }
        
        .step-text {
            font-size: 0.85rem;
        }
    }

    /* Meet the Chefs Section */
    .chefs-section {
        padding: 5rem 2rem;
        background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
    }

    .chefs-container {
        max-width: 1200px;
        margin: 0 auto;
        text-align: center;
    }

    .chefs-title {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        color: var(--text-dark);
        font-family: 'BaticaSans', sans-serif;
        font-weight: 700;
    }

    .chefs-subtitle {
        font-size: 1.2rem;
        color: var(--text-gray);
        margin-bottom: 3rem;
        font-family: 'BaticaSans', sans-serif;
        font-weight: 400;
    }

    .chefs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2.5rem;
        margin-top: 3rem;
    }

    .chef-card {
        background: var(--white);
        border-radius: 20px;
        padding: 2rem;
        box-shadow: var(--shadow-soft);
        transition: var(--transition);
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .chef-card:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-medium);
    }

    .chef-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--curry), var(--brown));
    }

    .chef-image {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        margin: 0 auto 1.5rem;
        border: 4px solid var(--cream);
        transition: var(--transition);
    }

    .chef-card:hover .chef-image {
        border-color: var(--curry);
        transform: scale(1.05);
    }

    .chef-name {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .chef-title {
        font-size: 1rem;
        color: var(--curry);
        margin-bottom: 1rem;
        font-family: 'BaticaSans', sans-serif;
        font-weight: 600;
    }

    .chef-description {
        color: var(--text-gray);
        line-height: 1.6;
        margin-bottom: 1.5rem;
        font-family: 'BaticaSans', sans-serif;
        font-size: 0.95rem;
    }

    .chef-credentials {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: center;
    }

    .credential-badge {
        background: linear-gradient(135deg, var(--sage), var(--brown));
        color: var(--white);
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
    }

    /* Reviews Section */
    .reviews-section {
        padding: 5rem 2rem;
        background: var(--white);
    }

    .reviews-container {
        max-width: 1200px;
        margin: 0 auto;
        text-align: center;
    }

    .reviews-title {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        color: var(--text-dark);
        font-family: 'BaticaSans', sans-serif;
        font-weight: 700;
    }

    .reviews-subtitle {
        font-size: 1.2rem;
        color: var(--text-gray);
        margin-bottom: 3rem;
        font-family: 'BaticaSans', sans-serif;
        font-weight: 400;
    }

    .reviews-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 2rem;
        margin-top: 3rem;
    }

    .review-card {
        background: var(--white);
        border-radius: 15px;
        padding: 2rem;
        box-shadow: var(--shadow-soft);
        transition: var(--transition);
        text-align: left;
        border: 2px solid var(--cream);
        position: relative;
    }

    .review-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-medium);
        border-color: var(--curry);
    }

    .review-stars {
        display: flex;
        gap: 0.2rem;
        margin-bottom: 1rem;
    }

    .star {
        color: #ffc107;
        font-size: 1.2rem;
    }

    .review-text {
        color: var(--text-dark);
        line-height: 1.6;
        margin-bottom: 1.5rem;
        font-family: 'BaticaSans', sans-serif;
        font-size: 1rem;
        font-style: italic;
        position: relative;
    }

    .review-text::before {
        content: '"';
        font-size: 2rem;
        color: var(--curry);
        position: absolute;
        top: -0.5rem;
        left: -0.5rem;
        font-weight: 700;
    }

    .review-text::after {
        content: '"';
        font-size: 2rem;
        color: var(--curry);
        font-weight: 700;
    }

    .reviewer-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .reviewer-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--curry), var(--brown));
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
        font-weight: 700;
        font-family: 'BaticaSans', sans-serif;
        font-size: 1.2rem;
    }

    .reviewer-details h4 {
        color: var(--text-dark);
        margin: 0;
        font-family: 'BaticaSans', sans-serif;
        font-weight: 600;
        font-size: 1rem;
    }

    .reviewer-details p {
        color: var(--text-gray);
        margin: 0;
        font-family: 'BaticaSans', sans-serif;
        font-size: 0.9rem;
    }

    .review-platform {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: var(--cream);
        color: var(--text-gray);
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        font-family: 'BaticaSans', sans-serif;
    }

    /* Responsive Design for New Sections */
    @media (max-width: 768px) {
        .chefs-title, .reviews-title {
            font-size: 2rem;
        }

        .chefs-subtitle, .reviews-subtitle {
            font-size: 1rem;
        }

        .chefs-grid, .reviews-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .chef-card, .review-card {
            padding: 1.5rem;
        }

        .chef-image {
            width: 100px;
            height: 100px;
        }
    }

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
        }
    </style>
    
    <style>
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

        .nav-links a:hover {
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 80px 2rem 2rem;
            background: url('./assets/image/padthai2.png') center/cover no-repeat, linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%); /* LEVEL 2: Cream background */
            position: relative;
            overflow: hidden;
            margin-top: 0;
        }

        .hero-container {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .hero-container .background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--white) 0%, var(--cream) 100%); /* LEVEL 1 & 2: White to cream */
            z-index: -1;
        }

        .hero-content {
            flex: 1;
            max-width: 600px;
            padding: 3rem 2rem;
            z-index: 10;
        }

        .hero-content h1 {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            font-weight: 800;
            color: var(--brown); /* LEVEL 1: Brown title */
            line-height: 1.1;
            font-family: 'BaticaSans', sans-serif;
        }

        .hero-content p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            color: var(--text-gray);
            line-height: 1.6;
            font-family: 'BaticaSans', sans-serif;
        }

        .hero-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .zip-input-container {
            position: relative;
            width: 100%;
            max-width: 400px;
        }

        .zip-input {
            width: 100%;
            padding: 1rem 1.5rem;
            border: 2px solid var(--border-light); /* Brown-tinted border */
            border-radius: 50px;
            font-size: 1.1rem;
            font-family: 'BaticaSans', sans-serif;
            background: var(--white); /* LEVEL 1: White */
            transition: all 0.3s;
            outline: none;
        }

        .zip-input:focus {
            border-color: var(--brown); /* LEVEL 1: Brown focus */
            box-shadow: 0 0 0 3px rgba(189, 147, 121, 0.1);
        }

        .zip-input::placeholder {
            color: #999;
            font-family: 'BaticaSans', sans-serif;
        }

        .order-now-button {
            background: #cf723a ; /* LEVEL 1: Brown */
            color: var(--white); /* LEVEL 1: White */
            padding: 1rem 2.5rem;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.2rem;
            font-family: 'BaticaSans', sans-serif;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            max-width: 200px;
        }

        .order-now-button:hover {
            background: #cf723a ; /* Darker brown */
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Hero Videos - Vertical Sliding Animation */
        .hero-videos {
            flex: 1;
            height: 80vh;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            justify-content: center;
        }

        .image-column {
            flex: 1;
            height: 100%;
            max-width: 300px;
            position: relative;
            overflow: hidden;
        }

        .image-slider, .image-slider-reverse {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            animation-duration: 40s;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
        }

        .image-slider {
            animation-name: slideDown;
        }

        .image-slider-reverse {
            animation-name: slideUp;
        }

        @keyframes slideDown {
            0% { transform: translateY(-50%); }
            100% { transform: translateY(0%); }
        }

        @keyframes slideUp {
            0% { transform: translateY(0%); }
            100% { transform: translateY(-50%); }
        }

        .video-container {
            position: relative;
            width: 100%;
            height: 200px; /* Fixed height instead of min-height */
            max-width: 200px; /* Adjust based on your layout needs */
            overflow: hidden;
            border-radius: 0px;
            background: linear-gradient(45deg, var(--brown), var(--sage)); /* LEVEL 1 & 3: Brown and sage */
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white); /* LEVEL 1: White */
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            font-size: 1.2rem;
            clip-path: inset(0);
        }

        .video-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            border-radius: 12px;
        }

        .video-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            border-radius: 12px;
        }

        .meal-cards-container {
    position: relative;
    overflow: hidden;
    margin-bottom: 2rem;
}

    .meal-cards-track {
        display: flex;
        gap: 1.5rem;
        transition: transform 0.3s ease;
        padding: 0 2rem;
    }

    .meal-card {
        flex: 0 0 320px;
        height: 400px;
        border-radius: 15px;
        position: relative;
        overflow: hidden;
        cursor: pointer;
        transition: transform 0.3s ease;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    }

    .meal-card:hover {
        transform: translateY(-5px);
    }

    .meal-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(
            180deg,
            transparent 0%,
            transparent 40%,
            rgba(189, 147, 121, 0.3) 70%, /* LEVEL 1: Brown overlay instead of black */
            rgba(189, 147, 121, 0.8) 100%
        );
    }

    .meal-card-content {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 1.5rem;
        color: var(--white); /* LEVEL 1: White */
        z-index: 2;
    }

    .meal-card-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        font-family: 'BaticaSans', sans-serif;
        color: var(--white) !important; /* LEVEL 1: White */
    }

    .meal-card-description {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-bottom: 1rem;
        font-family: 'BaticaSans', sans-serif;
    }

    .meal-card-chef {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .chef-image {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
    }

    .chef-info {
        font-size: 0.8rem;
        opacity: 0.8;
    }

    .chef-info span {
        font-weight: 400;
    }

    /* Scroll Controls */
    .scroll-controls {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-top: 2rem;
    }

    .scroll-btn {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: 2px solid var(--brown); /* LEVEL 1: Brown border */
        background: var(--white); /* LEVEL 1: White */
        color: var(--brown); /* LEVEL 1: Brown */
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: var(--transition);
    }

    .scroll-btn:hover {
        background: var(--brown); /* LEVEL 1: Brown */
        color: var(--white); /* LEVEL 1: White */
    }

    .scroll-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .meal-card {
            flex: 0 0 280px;
            height: 360px;
        }

        .meal-cards-track {
            padding: 0 1rem;
        }
    }

    @media (max-width: 480px) {
        .meal-card {
            flex: 0 0 250px;
            height: 320px;
        }
    }

        /* Steps Section */
        .steps-section {
            padding: 5rem 2rem;
            background: var(--cream); /* LEVEL 2: Cream background */
        }

        .steps-container {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .steps-title {
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: var(--brown); /* LEVEL 1: Brown */
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .step {
            text-align: center;
            padding: 2rem;
        }

        .step-number {
            width: 80px;
            height: 80px;
            background: var(--brown); /* LEVEL 1: Brown */
            color: var(--white); /* LEVEL 1: White */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
            margin: 0 auto 1rem;
        }

        .step h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: var(--brown); /* LEVEL 1: Brown */
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .step p {
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
        .promo-banner {
            font-size: 12px;
            padding: 6px 15px;
        }
        
        .hero-section {
            padding-top: 100px; /* UPDATE THIS */
        }
        
        .promo-banner-content {
            flex-direction: column;
            gap: 5px;
        }
        
        .promo-close {
            right: 10px;
        }
            .hero-container {
                flex-direction: column;
                text-align: center;
            }

            .hero-content {
                order: 1;
                max-width: 100%;
                padding: 2rem 1rem;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .hero-content p {
                font-size: 1rem;
            }

            .hero-videos {
                order: 2;
                height: 300px;
                flex-direction: row;
                gap: 1rem;
            }

            .image-column {
                height: 100%;
            }

            .video-container {
                min-height: 120px;
                font-size: 0.9rem;
            }

            .zip-input-container {
                max-width: 100%;
            }

            .order-now-button {
                max-width: 100%;
            }

            .nav-links {
                display: none;
            }

            .steps-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .steps-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-content h1 {
                font-size: 2rem;
            }
            
            .hero-videos {
                height: 250px;
            }
            
            .video-container {
                min-height: 100px;
                font-size: 0.8rem;
            }
        }
.menu-nav-container {
    margin-bottom: 32px;
    /* Remove max-width and centering margin */
    width: 100%; /* Full width */
    padding: 20px 0;
    background: var(--cream); /* LEVEL 2: Cream background */
    border-radius: 0; /* Remove border radius for full width */
    box-shadow: none; /* Remove shadow for cleaner full-width look */
    /* Add a subtle border instead */
    border-top: 1px solid rgba(189, 147, 121, 0.1);
    border-bottom: 1px solid rgba(189, 147, 121, 0.1);
}


.menu-nav-wrapper {
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: none;
    -ms-overflow-style: none;
    padding: 0 1rem;
    /* Center the content */
    max-width: 1200px;
    margin: 0 auto;
}

.menu-nav-wrapper::-webkit-scrollbar {
    display: none;
}

.menu-nav-list {
    display: flex;
    gap: 0;
    min-width: max-content;
    align-items: center;
    justify-content: center; /* Center the navigation items */
}
/* Also prevent text selection on menu navigation */
.menu-nav-container,
.menu-nav-container * {
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}
.menu-nav-item {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 54px;
    padding: 0 16px;
    border: none;
    border-bottom: 2px solid transparent;
    background: transparent;
    cursor: pointer;
    font-family: 'BaticaSans', Arial, sans-serif;
    font-size: 14px;
    font-weight: 600;
    color: #707070;
    transition: all 0.3s ease;
    white-space: nowrap;
    text-decoration: none;
    border-radius: var(--radius-sm);
    /* Remove any focus outline */
    outline: none !important;
    -webkit-tap-highlight-color: transparent;
}

.menu-nav-item:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(189, 147, 121, 0.3);
}

.menu-nav-item:hover {
    color: var(--brown); /* LEVEL 1: Brown hover */
    background: rgba(189, 147, 121, 0.1); /* Light brown background */
    border-bottom-color: var(--brown); /* LEVEL 1: Brown */
}

.menu-nav-item.active {
    color: var(--brown); /* LEVEL 1: Brown active */
    background: var(--white); /* LEVEL 1: White background */
    border-bottom-color: var(--brown); /* LEVEL 1: Brown */
    box-shadow: var(--shadow-soft);
}

.menu-nav-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.menu-nav-icon svg {
    width: 100%;
    height: 100%;
    fill: #707070;
    transition: fill 0.3s ease;
}

.menu-nav-item:hover .menu-nav-icon svg {
    fill: var(--brown); /* LEVEL 1: Brown */
}

.menu-nav-item.active .menu-nav-icon svg {
    fill: var(--brown); /* LEVEL 1: Brown */
}

.menu-nav-text {
    font-size: 14px;
    font-weight: 600;
}

/* Responsive design */
@media (max-width: 768px) {
    .menu-nav-container {
        margin-bottom: 24px;
    }
    
    .menu-nav-item {
        padding: 0 12px;
        font-size: 13px;
    }
    
    .menu-nav-icon {
        width: 20px;
        height: 20px;
    }
}
/* Promotional Banner Styles - LEVEL 4: Curry for special promos */
.promo-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: #cf723a ; /* LEVEL 4: Curry for promotional banner */
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
    </style>
</head>

<body>
    <div class="promo-banner" id="promoBanner">
        <div class="promo-banner-content">
            <span class="promo-icon">🍪</span>
            <span class="promo-text">50% OFF First Week + Free Cookies for Life</span>
            <span class="promo-icon">🎉</span>
        </div>
        <button class="promo-close" onclick="closePromoBanner()" title="Close">×</button>
    </div>

    <!-- Navigation -->
    <nav class="navbar">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; max-width: 1200px; margin: 0 auto; width: 100%;">
            <a href="index.php" class="logo">
                <img src="./assets/image/LOGO_BG2.png" alt="Somdul Table" style="height: 80px; width: auto;">
            </a>

            
            <ul class="nav-links">
                <li><a href="./menus.php">Menu</a></li>
                <li><a href="./meal-kits.php">Meal-Kits</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="./blogs.php">About</a></li>
                <li><a href="./contact.php">Contact</a></li>
            </ul>
            
            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
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

    <!-- Hero Vertical Slider -->
    <section class="hero-section" id="home" data-testid="hero-vertical-slider">
        <div class="hero-container" data-testid="hero-vertical-slider-image-columns-container">
            <div class="background"></div>
            
            <div class="hero-content">
                <h1>Fresh Thai Meals Delivered Daily</h1>
                <p>Experience authentic Thai flavors crafted by expert chefs and delivered fresh to your door. Healthy, delicious, and perfectly spiced to your preference.</p>
                
                <div class="hero-form">
                    <div class="zip-input-container">
                        <input type="text" class="zip-input" placeholder="Enter your ZIP code">
                    </div>
                    <a href="./menus.php" class="order-now-button">View Menu</a>
                </div>
            </div>
            
            <div class="hero-videos">
                <!-- Left Column - Sliding Up -->
                <div class="image-column" data-testid="image-column">
                    <div class="image-slider-reverse">
                        <div class="video-container">
                            <div class="video-container">
                                <video autoplay muted loop>
                                    <source src="assets/videos/video1.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image1.jpg" alt="Pad Thai">
                        </div>
                        <div class="video-container">
                            <div class="video-container">
                                <video autoplay muted loop>
                                    <source src="assets/videos/video7.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image2.jpg" alt="Pad Thai">
                        </div>
                        <div class="video-container">
                            <div class="video-container">
                                <video autoplay muted loop>
                                    <source src="assets/videos/video2.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image3.jpg" alt="Pad Thai">
                        </div>
                    </div>
                </div>

                <!-- Middle Column - Sliding Down -->
                <div class="image-column" data-testid="image-column">
                    <div class="image-slider">
                        <div class="video-container">
                            <div class="video-container">
                                <video autoplay muted loop>
                                    <source src="assets/videos/video8.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image4.jpg" alt="Pad Thai">
                        </div>
                        <div class="video-container">
                            <div class="video-container">
                                <video autoplay muted loop>
                                    <source src="assets/videos/video3.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image5.jpg" alt="Pad Thai">
                        </div>
                        <div class="video-container">
                            <div class="video-container">
                                <video autoplay muted loop>
                                    <source src="assets/videos/video6.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image6.jpg" alt="Pad Thai">
                        </div>
                    </div>
                </div>

                <!-- Right Column - Sliding Up -->
                <div class="image-column" data-testid="image-column">
                    <div class="image-slider-reverse">
                        <div class="video-container">
                            <div class="video-container">
                                <video autoplay muted loop>
                                    <source src="assets/videos/video9.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image7.jpg" alt="Pad Thai">
                        </div>
                        <div class="video-container">
                            <div class="video-container">
                                <video autoplay muted loop>
                                    <source src="assets/videos/video5.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image8.jpg" alt="Pad Thai">
                        </div>
                        <div class="video-container">
                            <div class="video-container">
                                <video autoplay muted loop>
                                    <source src="assets/videos/video4.mp4" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                        </div>
                        <div class="video-container">
                            <img src="assets/image/image9.jpg" alt="Pad Thai">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Menu Section -->
    <section class="menu-section" id="menu">
        <div class="menu-container">            
            <div class="menu-nav-container">
                <div class="menu-nav-wrapper">
                    <div class="menu-nav-list">
                        <?php if (empty($categories)): ?>
                            <!-- Fallback categories if database is empty -->
                            <button class="menu-nav-item active" data-category="all">
                                <span class="menu-nav-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <?php echo $default_icon; ?>
                                    </svg>
                                </span>
                                <span class="menu-nav-text">All Items</span>
                            </button>
                        <?php else: ?>
                            <!-- All items button -->
                            <button class="menu-nav-item active" data-category="all">
                                <span class="menu-nav-icon">
                                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                                    </svg>
                                </span>
                                <span class="menu-nav-text">All Items</span>
                            </button>
                            
                            <!-- Dynamic categories from database -->
                            <?php foreach ($categories as $index => $category): ?>
                                <?php 
                                $category_name = $category['name'] ?: $category['name_thai'];
                                $icon_path = $category_icons[$category_name] ?? $default_icon;
                                ?>
                                <button class="menu-nav-item" data-category="<?php echo htmlspecialchars($category['id']); ?>">
                                    <span class="menu-nav-icon">
                                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <?php echo $icon_path; ?>
                                        </svg>
                                    </span>
                                    <span class="menu-nav-text">
                                        <?php echo htmlspecialchars($category_name); ?>
                                    </span>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="meal-cards-container">
                <div class="meal-cards-track" id="mealCardsTrack">
                    <?php if (empty($featured_menus)): ?>
                        <!-- Fallback content if no menus in database -->
                        <div class="meal-card" style="background: linear-gradient(45deg, var(--brown), var(--sage));" data-menu-id="fallback-1" onclick="openMenuDetails('fallback-1')">
                            <div class="meal-card-content">
                                <h3 class="meal-card-title">Thai Green Curry</h3>
                                <p class="meal-card-description">Aromatic green curry with Thai basil and coconut milk</p>
                                <div class="meal-card-chef">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--white); display: flex; align-items: center; justify-content: center; color: var(--curry); font-weight: bold;">👨‍🍳</div>
                                    <div class="chef-info">
                                        <span>by</span> Chef Siriporn
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="meal-card" style="background: linear-gradient(45deg, var(--sage), var(--cream));" data-menu-id="fallback-2" onclick="openMenuDetails('fallback-2')">
                            <div class="meal-card-content">
                                <h3 class="meal-card-title">Pad Thai Classic</h3>
                                <p class="meal-card-description">Traditional stir-fried rice noodles with tamarind sauce</p>
                                <div class="meal-card-chef">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--white); display: flex; align-items: center; justify-content: center; color: var(--curry); font-weight: bold;">👨‍🍳</div>
                                    <div class="chef-info">
                                        <span>by</span> Chef Narong
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Dynamic menus from database -->
                        <?php foreach ($featured_menus as $menu): ?>
                            <?php
                            $menu_name = $menu['name'] ?: $menu['name_thai'];
                            $category_name = $menu['category_name'] ?: $menu['category_name_thai'];
                            $description = $menu['description'] ?: 'Authentic Thai cuisine made healthy';
                            
                            // Determine background image or fallback
                            $background_style = '';
                            if ($menu['main_image_url'] && file_exists($menu['main_image_url'])) {
                                $background_style = "background-image: url('" . htmlspecialchars($menu['main_image_url']) . "');";
                            } else {
                                // Fallback gradient based on category - using new color hierarchy
                                $gradients = [
                                    'Rice Bowls' => 'linear-gradient(45deg, var(--brown), var(--sage))',
                                    'Thai Curries' => 'linear-gradient(45deg, var(--sage), var(--cream))',
                                    'Noodle Dishes' => 'linear-gradient(45deg, var(--brown), var(--cream))',
                                    'default' => 'linear-gradient(45deg, var(--brown), var(--sage))'
                                ];
                                $gradient = $gradients[$category_name] ?? $gradients['default'];
                                $background_style = "background: $gradient;";
                            }
                            ?>
                            <div class="meal-card" 
                                 style="<?php echo $background_style; ?>" 
                                 data-menu-id="<?php echo htmlspecialchars($menu['id']); ?>" 
                                 onclick="openMenuDetails('<?php echo htmlspecialchars($menu['id']); ?>')">
                                <div class="meal-card-content">
                                    <h3 class="meal-card-title">
                                        <?php echo htmlspecialchars($menu_name); ?>
                                    </h3>
                                    <p class="meal-card-description">
                                        <?php echo htmlspecialchars($description); ?>
                                    </p>
                                    <div class="meal-card-chef">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Scroll Controls -->
            <div class="scroll-controls">
                <button class="scroll-btn" id="scrollLeft">←</button>
                <button class="scroll-btn" id="scrollRight">→</button>
            </div>
        </div>
    </section>

       <section class="steps-section" id="how-it-works">
        <div class="steps-container">
            <h2 class="steps-title">How It Works</h2>
            <div data-testid="hiw-items" class="hiw-items">
                <div data-testid="hiw-step" class="hiw-step hiw-step--with-border">
                    <img src="assets/image/weeklyplan.jpg" alt="Pick your weekly plan" class="hiw-step-image">
                    <div data-testid="hiw-step-text" class="hiw-step-text">
                        <p class="font-bold">Pick your weekly plan</p>
                        <p data-testid="step-text" class="step-text">Choose from 4 to 16 meals per week – you can pause, skip, or cancel deliveries at any time.</p>
                    </div>
                </div>
                <div data-testid="hiw-step" class="hiw-step hiw-step--with-border">
                    <img src="assets/image/selectingmeal.jpeg" alt="Pick your weekly plan" class="hiw-step-image">
                    <div data-testid="hiw-step-text" class="hiw-step-text">
                        <p class="font-bold">Select your meals</p>
                        <p data-testid="step-text" class="step-text">Browse our menu and select your meals – new offerings added weekly.</p>
                    </div>
                </div>
                <div data-testid="hiw-step" class="hiw-step hiw-step--with-border">
                    <img src="assets/image/chefcooking.jpg" alt="Pick your weekly plan" class="hiw-step-image">
                    <div data-testid="hiw-step-text" class="hiw-step-text">
                        <p class="font-bold">Let chefs work their magic</p>
                        <p data-testid="step-text" class="step-text">Every meal is made to order in small batches with the craft and artistry offered only by top-tier chefs.</p>
                    </div>
                </div>
                <div data-testid="hiw-step" class="hiw-step hiw-step--with-border">
                    <img src="assets/image/streetfood.jpg" alt="Pick your weekly plan" class="hiw-step-image">
                    <div data-testid="hiw-step-text" class="hiw-step-text">
                        <p class="font-bold">Sit back and enjoy</p>
                        <p data-testid="step-text" class="step-text">Delivered fresh every week, enjoy chef-crafted meals in minutes with no prep or cleanup.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Customer Reviews Section -->
    <section class="reviews-section" id="reviews">
        <div class="reviews-container">
            <h2 class="reviews-title">What Our Customers Say</h2>
            <p class="reviews-subtitle">Join thousands of satisfied customers who trust Somdul Table for authentic Thai cuisine</p>
            
            <div class="reviews-grid">
                <div class="review-card">
                    <div class="review-platform">Google</div>
                    <div class="review-stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="review-text">
                        The Pad Thai is absolutely incredible! It tastes exactly like what I had in Bangkok. The delivery is always on time and the packaging keeps everything fresh. I've been ordering for 6 months now and never disappointed.
                    </p>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">SM</div>
                        <div class="reviewer-details">
                            <h4>Sarah Mitchell</h4>
                            <p>Regular Customer • Los Angeles, CA</p>
                        </div>
                    </div>
                </div>

                <div class="review-card">
                    <div class="review-platform">Yelp</div>
                    <div class="review-stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="review-text">
                        As someone who lived in Thailand for 3 years, I can say this is the most authentic Thai food I've found in the US. The green curry is phenomenal and the spice levels are perfect. Highly recommend!
                    </p>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">DJ</div>
                        <div class="reviewer-details">
                            <h4>David Johnson</h4>
                            <p>Food Enthusiast • New York, NY</p>
                        </div>
                    </div>
                </div>

                <div class="review-card">
                    <div class="review-platform">TrustPilot</div>
                    <div class="review-stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="review-text">
                        Amazing quality and portion sizes! The mango sticky rice dessert is to die for. Customer service is excellent too - they accommodated my dietary restrictions perfectly. Will definitely order again!
                    </p>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">ER</div>
                        <div class="reviewer-details">
                            <h4>Emily Rodriguez</h4>
                            <p>Verified Customer • Chicago, IL</p>
                        </div>
                    </div>
                </div>

                <div class="review-card">
                    <div class="review-platform">Google</div>
                    <div class="review-stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="review-text">
                        The convenience of having restaurant-quality Thai food delivered weekly is unmatched. The flavors are complex and authentic, and I love trying new dishes each week. The packaging is eco-friendly too!
                    </p>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">MT</div>
                        <div class="reviewer-details">
                            <h4>Michael Thompson</h4>
                            <p>Busy Professional • Miami, FL</p>
                        </div>
                    </div>
                </div>

                <div class="review-card">
                    <div class="review-platform">Facebook</div>
                    <div class="review-stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="review-text">
                        I ordered for a dinner party and everyone was blown away! The Tom Yum soup was perfectly balanced and the presentation was beautiful. My Thai friends were impressed with the authenticity. 5 stars!
                    </p>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">LC</div>
                        <div class="reviewer-details">
                            <h4>Lisa Chen</h4>
                            <p>Event Host • San Francisco, CA</p>
                        </div>
                    </div>
                </div>

                <div class="review-card">
                    <div class="review-platform">Google</div>
                    <div class="review-stars">
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                        <span class="star">★</span>
                    </div>
                    <p class="review-text">
                        Been ordering from Somdul Table for over a year. The consistency in quality is remarkable. Each dish is packed with flavor and arrives hot. The subscription model makes meal planning so easy!
                    </p>
                    <div class="reviewer-info">
                        <div class="reviewer-avatar">RK</div>
                        <div class="reviewer-details">
                            <h4>Robert Kim</h4>
                            <p>Loyal Customer • Seattle, WA</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<script>
    // Function to handle meal card clicks - redirects to menus.php with menu ID
    function openMenuDetails(menuId) {
        // Redirect to menus.php with the menu ID as a parameter
        window.location.href = `menus.php?show_menu=${encodeURIComponent(menuId)}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const menuItems = document.querySelectorAll('.menu-nav-item');
        const mealCardsTrack = document.getElementById('mealCardsTrack');
        const scrollLeftBtn = document.getElementById('scrollLeft');
        const scrollRightBtn = document.getElementById('scrollRight');
        
        // Initialize scroll tracking
        let currentScroll = 0;
        const cardWidth = 320 + 24; // card width + gap
        
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                // Remove active class from all items
                menuItems.forEach(i => i.classList.remove('active'));
                
                // Add active class to clicked item
                this.classList.add('active');
                
                // Get category for filtering
                const category = this.getAttribute('data-category');
                console.log('Selected category:', category);
                
                // Show loading state
                showLoadingState();
                
                // Filter menus by category
                filterMenusByCategory(category);
            });
        });
        
        function showLoadingState() {
            mealCardsTrack.innerHTML = `
                <div class="loading-container" style="display: flex; justify-content: center; align-items: center; width: 100%; height: 400px;">
                    <div class="loading-spinner" style="
                        border: 4px solid #ece8e1;
                        border-top: 4px solid #bd9379;
                        border-radius: 50%;
                        width: 50px;
                        height: 50px;
                        animation: spin 1s linear infinite;
                    "></div>
                </div>
            `;
            
            // Add spin animation if not already defined
            if (!document.querySelector('#spin-animation')) {
                const style = document.createElement('style');
                style.id = 'spin-animation';
                style.textContent = `
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                `;
                document.head.appendChild(style);
            }
        }
        
        function filterMenusByCategory(categoryId) {
            // Make AJAX request to filter menus
            const url = `ajax/filter_menus.php?category=${encodeURIComponent(categoryId)}&limit=10`;
            
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        updateMenuCards(data.menus);
                    } else {
                        console.error('Filter error:', data.error);
                        showErrorState(data.error || 'Failed to load menus');
                    }
                })
                .catch(error => {
                    console.error('Network error:', error);
                    showErrorState('Network error occurred. Please try again.');
                });
        }
        
        function updateMenuCards(menus) {
            if (!menus || menus.length === 0) {
                showEmptyState();
                return;
            }
            
            // Generate HTML for menu cards
            const cardsHTML = menus.map(menu => {
                // Log debug info to console
                console.log('Menu:', menu.name, 'Image URL:', menu.image_url, 'Has Image:', menu.has_image);
                
                // Determine the complete background style
                let backgroundStyle = '';
                if (menu.has_image && menu.image_url) {
                    // Use background-image with proper sizing and positioning
                    backgroundStyle = `background-image: url('${menu.image_url}'); background-size: cover; background-position: center; background-repeat: no-repeat;`;
                } else {
                    // Use the gradient from the server response
                    backgroundStyle = menu.background_style;
                }
                
                return `
                    <div class="meal-card" 
                         style="${backgroundStyle}" 
                         data-menu-id="${menu.id}" 
                         onclick="openMenuDetails('${menu.id}')">
                        <div class="meal-card-content">
                            <h3 class="meal-card-title">${menu.name}</h3>
                            <p class="meal-card-description">${menu.description}</p>
                            <div class="meal-card-chef">
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            // Update the track with new cards
            mealCardsTrack.innerHTML = cardsHTML;
            
            // Reset scroll position
            currentScroll = 0;
            mealCardsTrack.style.transform = `translateX(${currentScroll}px)`;
            
            // Update scroll buttons
            updateScrollButtons();
            
            // Re-enable touch/swipe functionality for new cards
            setupTouchEvents();
        }
        
        function showEmptyState() {
            mealCardsTrack.innerHTML = `
                <div class="empty-state" style="
                    display: flex; 
                    flex-direction: column; 
                    justify-content: center; 
                    align-items: center; 
                    width: 100%; 
                    height: 400px; 
                    text-align: center;
                    color: var(--text-gray);
                ">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🍽️</div>
                    <h3 style="color: var(--text-dark); margin-bottom: 0.5rem;">No meals found</h3>
                    <p>Try selecting a different category or check back later for new additions.</p>
                </div>
            `;
            updateScrollButtons();
        }
        
        function showErrorState(message) {
            mealCardsTrack.innerHTML = `
                <div class="error-state" style="
                    display: flex; 
                    flex-direction: column; 
                    justify-content: center; 
                    align-items: center; 
                    width: 100%; 
                    height: 400px; 
                    text-align: center;
                    color: var(--text-gray);
                ">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">⚠️</div>
                    <h3 style="color: var(--text-dark); margin-bottom: 0.5rem;">Oops! Something went wrong</h3>
                    <p>${message}</p>
                    <button onclick="location.reload()" style="
                        margin-top: 1rem;
                        padding: 0.8rem 1.5rem;
                        background: var(--brown);
                        color: white;
                        border: none;
                        border-radius: 50px;
                        cursor: pointer;
                        font-family: 'BaticaSans', sans-serif;
                        font-weight: 600;
                    ">Retry</button>
                </div>
            `;
            updateScrollButtons();
        }
        
        function updateScrollButtons() {
            const visibleCards = Math.floor(window.innerWidth / cardWidth);
            const maxScroll = -(mealCardsTrack.children.length - visibleCards) * cardWidth;
            
            scrollLeftBtn.disabled = currentScroll >= 0;
            scrollRightBtn.disabled = currentScroll <= maxScroll || mealCardsTrack.children.length <= visibleCards;
        }
        
        function setupTouchEvents() {
            let startX, currentX, isDragging = false;
            
            // Remove existing listeners to avoid duplicates
            mealCardsTrack.removeEventListener('touchstart', handleTouchStart);
            mealCardsTrack.removeEventListener('touchmove', handleTouchMove);
            mealCardsTrack.removeEventListener('touchend', handleTouchEnd);
            
            // Add new listeners
            mealCardsTrack.addEventListener('touchstart', handleTouchStart);
            mealCardsTrack.addEventListener('touchmove', handleTouchMove);
            mealCardsTrack.addEventListener('touchend', handleTouchEnd);
            
            function handleTouchStart(e) {
                startX = e.touches[0].clientX;
                isDragging = true;
            }
            
            function handleTouchMove(e) {
                if (!isDragging) return;
                currentX = e.touches[0].clientX;
                const diffX = currentX - startX;
                const newScroll = currentScroll + diffX;
                
                const visibleCards = Math.floor(window.innerWidth / cardWidth);
                const maxScroll = -(mealCardsTrack.children.length - visibleCards) * cardWidth;
                
                if (newScroll <= 0 && newScroll >= maxScroll) {
                    mealCardsTrack.style.transform = `translateX(${newScroll}px)`;
                }
            }
            
            function handleTouchEnd(e) {
                if (!isDragging) return;
                isDragging = false;
                
                const diffX = currentX - startX;
                const visibleCards = Math.floor(window.innerWidth / cardWidth);
                const maxScroll = -(mealCardsTrack.children.length - visibleCards) * cardWidth;
                
                if (Math.abs(diffX) > 50) {
                    if (diffX > 0) {
                        // Swipe right (scroll left)
                        currentScroll = Math.min(currentScroll + cardWidth, 0);
                    } else {
                        // Swipe left (scroll right)
                        currentScroll = Math.max(currentScroll - cardWidth, maxScroll);
                    }
                    mealCardsTrack.style.transform = `translateX(${currentScroll}px)`;
                    updateScrollButtons();
                }
            }
        }
        
        // Scroll button functionality
        scrollLeftBtn.addEventListener('click', () => {
            currentScroll = Math.min(currentScroll + cardWidth * 2, 0);
            mealCardsTrack.style.transform = `translateX(${currentScroll}px)`;
            updateScrollButtons();
        });
        
        scrollRightBtn.addEventListener('click', () => {
            const visibleCards = Math.floor(window.innerWidth / cardWidth);
            const maxScroll = -(mealCardsTrack.children.length - visibleCards) * cardWidth;
            currentScroll = Math.max(currentScroll - cardWidth * 2, maxScroll);
            mealCardsTrack.style.transform = `translateX(${currentScroll}px)`;
            updateScrollButtons();
        });
        
        // Initialize scroll buttons
        updateScrollButtons();
        
        // Setup initial touch events
        setupTouchEvents();
        
        // Update on window resize
        window.addEventListener('resize', () => {
            const visibleCards = Math.floor(window.innerWidth / cardWidth);
            const maxScroll = -(mealCardsTrack.children.length - visibleCards) * cardWidth;
            if (currentScroll < maxScroll) {
                currentScroll = maxScroll;
                mealCardsTrack.style.transform = `translateX(${currentScroll}px)`;
            }
            updateScrollButtons();
        });
    });
    
    function closePromoBanner() {
        const promoBanner = document.getElementById('promoBanner');
        const navbar = document.querySelector('.navbar');
        const heroSection = document.querySelector('.hero-section');
        
        promoBanner.style.transform = 'translateY(-100%)';
        promoBanner.style.opacity = '0';
        
        setTimeout(() => {
            promoBanner.style.display = 'none';
            navbar.style.top = '0';
            heroSection.style.paddingTop = '80px';
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
    });

    // Navbar background on scroll
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 100) {
            navbar.style.background = '#ece8e1';
        } else {
            navbar.style.background = '#ece8e1';
        }
    });
    </script>
</body>
</html>