<?php
/**
 * Somdul Table - Terms of Service Page
 * File: terms.php
 * Description: Comprehensive Terms of Service for the meal delivery platform
 */

// Basic security check
if (!defined('ALLOW_DIRECT_ACCESS')) {
    // This can be included by other files or accessed directly
}

// Get current year for copyright
$current_year = date('Y');
$last_updated = "January 15, 2025";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - Somdul Table</title>
    <meta name="description" content="Terms of Service for Somdul Table - Authentic Thai meal delivery service. Read our terms and conditions for using our platform.">
    <meta name="keywords" content="terms of service, legal, Somdul Table, Thai food delivery, subscription meals">
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
            line-height: 1.7;
            color: var(--text-dark);
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            font-size: 16px;
            font-weight: 400;
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
            margin-top: 2.5rem;
            margin-bottom: 1.2rem;
            color: var(--curry);
            border-bottom: 2px solid var(--curry);
            padding-bottom: 0.5rem;
        }

        h3 {
            font-size: 1.4rem;
            margin-top: 2rem;
            margin-bottom: 1rem;
            color: var(--brown);
        }

        h4 {
            font-size: 1.2rem;
            margin-top: 1.5rem;
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .terms-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            border: 1px solid rgba(255, 255, 255, 0.8);
            overflow: hidden;
        }

        .terms-header {
            background: linear-gradient(135deg, var(--cream) 0%, #f8f6f3 100%);
            padding: 2.5rem 3rem 2rem;
            text-align: center;
            border-bottom: 1px solid var(--border-light);
        }

        .terms-content {
            padding: 3rem;
        }

        /* Table of Contents */
        .toc {
            background: rgba(var(--curry), 0.05);
            border: 2px solid rgba(var(--curry), 0.2);
            border-radius: var(--radius-md);
            padding: 2rem;
            margin: 2rem 0;
        }

        .toc h3 {
            color: var(--curry);
            margin-bottom: 1rem;
            margin-top: 0;
        }

        .toc ol {
            list-style: none;
            counter-reset: toc-counter;
            margin-left: 0;
        }

        .toc li {
            counter-increment: toc-counter;
            margin-bottom: 0.5rem;
            position: relative;
            padding-left: 0;
        }

        .toc li::before {
            content: counter(toc-counter) ".";
            font-weight: 700;
            color: var(--curry);
            margin-right: 0.5rem;
        }

        .toc a {
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .toc a:hover {
            color: var(--curry);
            text-decoration: underline;
        }

        /* Lists */
        ul, ol {
            margin-left: 1.5rem;
            margin-bottom: 1.2rem;
        }

        ul {
            list-style-type: disc;
        }

        ol {
            list-style-type: decimal;
        }

        li {
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        li strong {
            color: var(--curry);
        }

        /* Important Notice Boxes */
        .notice {
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin: 1.5rem 0;
            border-left: 4px solid;
            font-weight: 500;
        }

        .notice-warning {
            background: rgba(255, 193, 7, 0.1);
            border-color: var(--warning);
            color: #856404;
        }

        .notice-info {
            background: rgba(23, 162, 184, 0.1);
            border-color: var(--info);
            color: #0c5460;
        }

        .notice-important {
            background: rgba(220, 53, 69, 0.1);
            border-color: var(--danger);
            color: #721c24;
        }

        /* Contact Information */
        .contact-info {
            background: linear-gradient(135deg, var(--sage) 0%, rgba(173, 184, 157, 0.8) 100%);
            border-radius: var(--radius-md);
            padding: 2rem;
            margin: 2rem 0;
            color: var(--white);
            text-align: center;
        }

        .contact-info h4 {
            color: var(--white);
            margin-bottom: 1rem;
        }

        .contact-info a {
            color: var(--white);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px solid transparent;
            transition: border-bottom-color 0.3s;
        }

        .contact-info a:hover {
            border-bottom-color: var(--white);
        }

        /* Footer */
        .terms-footer {
            background: var(--cream);
            padding: 2rem 3rem;
            text-align: center;
            border-top: 1px solid var(--border-light);
            color: var(--text-gray);
        }

        .terms-footer p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .terms-footer a {
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px solid transparent;
            transition: border-bottom-color 0.3s;
        }

        .terms-footer a:hover {
            border-bottom-color: var(--curry);
        }

        /* Navigation Buttons */
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2rem 0;
            gap: 1rem;
        }

        .btn {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
            font-family: 'BaticaSans', sans-serif;
        }

        .btn:hover {
            background: linear-gradient(135deg, var(--brown), var(--curry));
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-outline {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-outline:hover {
            background: var(--curry);
            color: var(--white);
        }

        /* Scroll to Top */
        .scroll-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--curry);
            color: var(--white);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
            opacity: 0;
            visibility: hidden;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .scroll-to-top.visible {
            opacity: 1;
            visibility: visible;
        }

        .scroll-to-top:hover {
            background: var(--brown);
            transform: translateY(-2px);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 2rem 1rem;
            }
            
            .terms-content {
                padding: 2rem 1.5rem;
            }
            
            .terms-header {
                padding: 2rem 1.5rem 1.5rem;
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
            
            .nav-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 250px;
            }
            
            .toc {
                padding: 1.5rem;
            }
            
            .notice {
                padding: 1rem;
            }
            
            .contact-info {
                padding: 1.5rem;
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .terms-container {
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

        /* Focus indicators for accessibility */
        input:focus-visible,
        button:focus-visible,
        a:focus-visible {
            outline: 2px solid var(--curry);
            outline-offset: 2px;
        }

        /* Print styles */
        @media print {
            .header,
            .breadcrumb,
            .nav-buttons,
            .scroll-to-top,
            .terms-footer {
                display: none;
            }
            
            .terms-container {
                box-shadow: none;
                border: 1px solid #ccc;
            }
            
            .toc a {
                color: inherit;
                text-decoration: none;
            }
            
            .notice {
                border: 1px solid #ccc;
                background: #f9f9f9;
            }
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
            <h1 style="color: white; margin: 0;">Terms of Service</h1>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <div class="breadcrumb-content">
            <a href="home2.php">Home</a>
            <span class="breadcrumb-separator">›</span>
            <span>Terms of Service</span>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <div class="terms-container">
            <!-- Terms Header -->
            <div class="terms-header">
                <h1>Terms of Service</h1>
                <p style="margin: 0; color: var(--text-gray); font-size: 1.1rem;">
                    Last updated: <?php echo $last_updated; ?>
                </p>
            </div>

            <!-- Terms Content -->
            <div class="terms-content">
                <!-- Table of Contents -->
                <div class="toc">
                    <h3>📋 Table of Contents</h3>
                    <ol>
                        <li><a href="#agreement">Agreement to Terms</a></li>
                        <li><a href="#description">Service Description</a></li>
                        <li><a href="#registration">User Registration & Account</a></li>
                        <li><a href="#subscriptions">Subscription Services</a></li>
                        <li><a href="#payments">Payment Terms</a></li>
                        <li><a href="#delivery">Delivery Policy</a></li>
                        <li><a href="#cancellation">Cancellation & Refunds</a></li>
                        <li><a href="#user-conduct">User Conduct</a></li>
                        <li><a href="#privacy">Privacy & Data Protection</a></li>
                        <li><a href="#intellectual-property">Intellectual Property</a></li>
                        <li><a href="#disclaimers">Disclaimers & Limitations</a></li>
                        <li><a href="#termination">Termination</a></li>
                        <li><a href="#governing-law">Governing Law</a></li>
                        <li><a href="#contact">Contact Information</a></li>
                    </ol>
                </div>

                <div class="notice notice-important">
                    <strong>⚠️ Important Notice:</strong> By using Somdul Table's services, you agree to be bound by these Terms of Service. Please read them carefully before using our platform.
                </div>

                <!-- Section 1: Agreement to Terms -->
                <h2 id="agreement">1. Agreement to Terms</h2>
                <p>Welcome to <strong>Somdul Table</strong> ("we," "our," or "us"), an authentic Thai meal delivery service operating in the United States. These Terms of Service ("Terms") constitute a legally binding agreement between you ("you," "your," or "User") and Somdul Table regarding your use of our website, mobile application, and meal delivery services (collectively, the "Service").</p>
                
                <p>By accessing or using our Service, you acknowledge that you have read, understood, and agree to be bound by these Terms and our <a href="privacy.php">Privacy Policy</a>. If you do not agree to these Terms, please do not use our Service.</p>

                <div class="notice notice-info">
                    <strong>📍 Service Area:</strong> Our services are currently available only in select areas within the United States. Please check our delivery zones during registration.
                </div>

                <!-- Section 2: Service Description -->
                <h2 id="description">2. Service Description</h2>
                <h3>2.1 What We Offer</h3>
                <p>Somdul Table provides:</p>
                <ul>
                    <li><strong>Subscription-based meal delivery:</strong> Weekly and monthly plans featuring authentic Thai cuisine</li>
                    <li><strong>Healthy meal options:</strong> Nutritionally balanced meals with detailed nutrition information</li>
                    <li><strong>Customizable preferences:</strong> Dietary accommodations and spice level adjustments</li>
                    <li><strong>Fresh ingredients:</strong> High-quality, locally-sourced ingredients when possible</li>
                    <li><strong>Convenient scheduling:</strong> Flexible delivery times and meal planning</li>
                </ul>

                <h3>2.2 Service Availability</h3>
                <p>Our services are subject to availability and may be limited by:</p>
                <ul>
                    <li>Geographic delivery zones</li>
                    <li>Kitchen capacity and preparation times</li>
                    <li>Seasonal ingredient availability</li>
                    <li>Weather conditions and force majeure events</li>
                    <li>Federal holidays and local restrictions</li>
                </ul>

                <!-- Section 3: Registration -->
                <h2 id="registration">3. User Registration & Account</h2>
                <h3>3.1 Account Creation</h3>
                <p>To use our Service, you must:</p>
                <ul>
                    <li>Be at least 18 years old or have parental consent</li>
                    <li>Provide accurate, current, and complete information</li>
                    <li>Maintain and update your account information</li>
                    <li>Be responsible for maintaining account security</li>
                    <li>Notify us immediately of any unauthorized use</li>
                </ul>

                <h3>3.2 Account Security</h3>
                <p>You are responsible for:</p>
                <ul>
                    <li>Maintaining the confidentiality of your login credentials</li>
                    <li>All activities that occur under your account</li>
                    <li>Logging out of your account at the end of each session</li>
                    <li>Immediately notifying us of any security breaches</li>
                </ul>

                <div class="notice notice-warning">
                    <strong>🔐 Security Tip:</strong> Use a strong, unique password and enable two-factor authentication when available.
                </div>

                <!-- Section 4: Subscriptions -->
                <h2 id="subscriptions">4. Subscription Services</h2>
                <h3>4.1 Subscription Plans</h3>
                <p>We offer various subscription plans including:</p>
                <ul>
                    <li><strong>Weekly Plans:</strong> 4-15 meals per week</li>
                    <li><strong>Monthly Plans:</strong> Full month coverage with discount benefits</li>
                    <li><strong>Custom Plans:</strong> Tailored solutions for specific dietary needs</li>
                </ul>

                <h3>4.2 Menu Selection</h3>
                <p>Subscribers can:</p>
                <ul>
                    <li>Pre-select meals from our weekly rotating menu</li>
                    <li>Modify meal choices up to the cutoff time (typically 48-72 hours before delivery)</li>
                    <li>Skip weeks or pause subscriptions with advance notice</li>
                    <li>Customize spice levels and dietary preferences</li>
                </ul>

                <h3>4.3 Subscription Management</h3>
                <p>You may:</p>
                <ul>
                    <li>Pause your subscription for up to 4 weeks per year</li>
                    <li>Skip individual delivery weeks</li>
                    <li>Modify your plan size with one billing cycle notice</li>
                    <li>Cancel your subscription at any time (see Section 7)</li>
                </ul>

                <!-- Section 5: Payments -->
                <h2 id="payments">5. Payment Terms</h2>
                <h3>5.1 Accepted Payment Methods</h3>
                <p>We accept:</p>
                <ul>
                    <li>Apple Pay</li>
                    <li>Google Pay</li>
                    <li>PayPal</li>
                    <li>Major credit cards (Visa, MasterCard, American Express)</li>
                    <li>ACH bank transfers (for recurring subscriptions)</li>
                </ul>

                <h3>5.2 Billing and Charges</h3>
                <ul>
                    <li><strong>Subscription charges:</strong> Billed automatically according to your plan cycle</li>
                    <li><strong>Delivery fees:</strong> May apply based on your location and order value</li>
                    <li><strong>Taxes:</strong> Applicable state and local taxes will be added</li>
                    <li><strong>Payment processing:</strong> Small processing fees may apply for certain payment methods</li>
                </ul>

                <h3>5.3 Failed Payments</h3>
                <p>If payment fails:</p>
                <ul>
                    <li>We will attempt to process payment up to 3 times</li>
                    <li>You will be notified via email and in-app notifications</li>
                    <li>Service may be suspended until payment is resolved</li>
                    <li>Account may be cancelled after 7 days of failed payment attempts</li>
                </ul>

                <div class="notice notice-info">
                    <strong>💳 Payment Security:</strong> All payment information is processed securely through PCI-compliant payment processors. We do not store your complete payment details on our servers.
                </div>

                <!-- Section 6: Delivery -->
                <h2 id="delivery">6. Delivery Policy</h2>
                <h3>6.1 Delivery Areas</h3>
                <p>We currently deliver to select ZIP codes. Delivery availability is determined by:</p>
                <ul>
                    <li>Distance from our kitchen facilities</li>
                    <li>Local regulations and permits</li>
                    <li>Demand and operational capacity</li>
                    <li>Weather and safety conditions</li>
                </ul>

                <h3>6.2 Delivery Schedule</h3>
                <ul>
                    <li><strong>Time slots:</strong> 9:00 AM - 9:00 PM in 3-hour windows</li>
                    <li><strong>Advance notice:</strong> Delivery schedules are confirmed 24 hours in advance</li>
                    <li><strong>Modifications:</strong> Delivery changes must be requested at least 12 hours prior</li>
                    <li><strong>Missed deliveries:</strong> See our redelivery policy below</li>
                </ul>

                <h3>6.3 Delivery Requirements</h3>
                <ul>
                    <li>Someone must be available to receive the delivery</li>
                    <li>Clear and accurate delivery address is required</li>
                    <li>Safe and accessible delivery location</li>
                    <li>Special instructions should be provided in advance</li>
                </ul>

                <h3>6.4 Missed Deliveries</h3>
                <p>If you miss a delivery:</p>
                <ul>
                    <li>We will attempt to contact you during the delivery window</li>
                    <li>Meals may be left in a safe location (per your instructions)</li>
                    <li>Redelivery fees may apply for same-day redelivery requests</li>
                    <li>Refunds are not available for customer-missed deliveries</li>
                </ul>

                <!-- Section 7: Cancellation & Refunds -->
                <h2 id="cancellation">7. Cancellation & Refunds</h2>
                <h3>7.1 Subscription Cancellation</h3>
                <p>You may cancel your subscription at any time by:</p>
                <ul>
                    <li>Logging into your account and selecting "Cancel Subscription"</li>
                    <li>Contacting our customer support team</li>
                    <li>Sending a written cancellation request to support@somdultable.com</li>
                </ul>

                <h3>7.2 Cancellation Policy</h3>
                <ul>
                    <li><strong>Weekly subscriptions:</strong> Cancel up to 48 hours before your next delivery</li>
                    <li><strong>Monthly subscriptions:</strong> Cancel up to 7 days before your next billing cycle</li>
                    <li><strong>Immediate cancellation:</strong> May result in forfeiture of current week's meals</li>
                </ul>

                <h3>7.3 Refund Policy</h3>
                <p>Refunds are available for:</p>
                <ul>
                    <li><strong>Service failures:</strong> Late deliveries, wrong orders, quality issues</li>
                    <li><strong>Cancelled orders:</strong> Orders cancelled within the allowed timeframe</li>
                    <li><strong>Defective meals:</strong> Meals that don't meet our quality standards</li>
                </ul>

                <p>Refunds are <strong>not</strong> available for:</p>
                <ul>
                    <li>Customer missed deliveries</li>
                    <li>Change of mind after delivery</li>
                    <li>Meals consumed despite quality concerns (without prior reporting)</li>
                    <li>Cancellations outside the allowed timeframe</li>
                </ul>

                <h3>7.4 Refund Processing</h3>
                <ul>
                    <li>Refunds are processed within 5-7 business days</li>
                    <li>Refunds are credited to the original payment method</li>
                    <li>Store credit may be offered as an alternative</li>
                    <li>Processing fees may be deducted from refund amounts</li>
                </ul>

                <!-- Section 8: User Conduct -->
                <h2 id="user-conduct">8. User Conduct</h2>
                <h3>8.1 Prohibited Activities</h3>
                <p>You agree not to:</p>
                <ul>
                    <li>Use the Service for any unlawful purpose or in violation of any laws</li>
                    <li>Impersonate any person or entity or misrepresent your affiliation</li>
                    <li>Attempt to gain unauthorized access to our systems or other users' accounts</li>
                    <li>Interfere with or disrupt the Service or servers connected to the Service</li>
                    <li>Use automated systems (bots, scrapers) to access the Service</li>
                    <li>Upload or transmit viruses or malicious code</li>
                    <li>Harass, abuse, or harm other users or our staff</li>
                    <li>Use the Service to commit fraud or engage in deceptive practices</li>
                </ul>

                <h3>8.2 Content Guidelines</h3>
                <p>When submitting reviews, feedback, or other content:</p>
                <ul>
                    <li>Content must be truthful and based on genuine experience</li>
                    <li>No offensive, defamatory, or inappropriate language</li>
                    <li>No promotional content or spam</li>
                    <li>Respect intellectual property rights</li>
                    <li>No personal information about others</li>
                </ul>

                <!-- Section 9: Privacy -->
                <h2 id="privacy">9. Privacy & Data Protection</h2>
                <p>Your privacy is important to us. Our collection, use, and protection of your personal information is governed by our <a href="privacy.php">Privacy Policy</a>, which is incorporated into these Terms by reference.</p>

                <h3>9.1 Data We Collect</h3>
                <ul>
                    <li>Personal information (name, email, phone, address)</li>
                    <li>Payment and billing information</li>
                    <li>Dietary preferences and restrictions</li>
                    <li>Order history and preferences</li>
                    <li>Usage data and analytics</li>
                </ul>

                <h3>9.2 How We Use Your Data</h3>
                <ul>
                    <li>Process and fulfill your orders</li>
                    <li>Improve our services and user experience</li>
                    <li>Send important service communications</li>
                    <li>Provide customer support</li>
                    <li>Comply with legal obligations</li>
                </ul>

                <div class="notice notice-info">
                    <strong>🛡️ Your Rights:</strong> You have the right to access, correct, delete, or export your personal data. Contact us to exercise these rights.
                </div>

                <!-- Section 10: Intellectual Property -->
                <h2 id="intellectual-property">10. Intellectual Property</h2>
                <h3>10.1 Our Intellectual Property</h3>
                <p>The Service and its original content, features, and functionality are owned by Somdul Table and are protected by United States and international copyright, trademark, patent, trade secret, and other intellectual property laws.</p>

                <h3>10.2 User-Generated Content</h3>
                <p>By submitting content to our Service (reviews, photos, feedback), you grant us a non-exclusive, worldwide, royalty-free license to use, display, reproduce, and distribute such content in connection with our Service.</p>

                <h3>10.3 Trademark Policy</h3>
                <p>"Somdul Table" and our logo are trademarks owned by us. You may not use our trademarks without our prior written consent.</p>

                <!-- Section 11: Disclaimers -->
                <h2 id="disclaimers">11. Disclaimers & Limitations</h2>
                <h3>11.1 Service Disclaimer</h3>
                <p>Our Service is provided "as is" and "as available" without any warranties of any kind, either express or implied, including but not limited to implied warranties of merchantability, fitness for a particular purpose, or non-infringement.</p>

                <h3>11.2 Food Safety & Allergies</h3>
                <ul>
                    <li>We take food safety seriously and follow all applicable health regulations</li>
                    <li>We cannot guarantee that our meals are free from all allergens</li>
                    <li>Cross-contamination may occur in our kitchen facilities</li>
                    <li>You are responsible for informing us of any food allergies or restrictions</li>
                    <li>Consult healthcare providers for specific dietary needs</li>
                </ul>

                <h3>11.3 Limitation of Liability</h3>
                <p>To the maximum extent permitted by law, Somdul Table shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to loss of profits, data, or other intangible losses resulting from your use of the Service.</p>

                <div class="notice notice-warning">
                    <strong>⚠️ Allergy Warning:</strong> Our kitchen processes common allergens including nuts, dairy, soy, and shellfish. Please inform us of any food allergies when creating your account.
                </div>

                <!-- Section 12: Termination -->
                <h2 id="termination">12. Termination</h2>
                <h3>12.1 Termination by You</h3>
                <p>You may terminate your account at any time by following our cancellation procedures or contacting customer support.</p>

                <h3>12.2 Termination by Us</h3>
                <p>We may terminate or suspend your account immediately, without prior notice, for:</p>
                <ul>
                    <li>Violation of these Terms</li>
                    <li>Fraudulent or illegal activities</li>
                    <li>Extended payment failures</li>
                    <li>Abuse of our Service or staff</li>
                    <li>Compromise of account security</li>
                </ul>

                <h3>12.3 Effect of Termination</h3>
                <p>Upon termination:</p>
                <ul>
                    <li>Your right to use the Service will cease immediately</li>
                    <li>You remain liable for all charges incurred prior to termination</li>
                    <li>We may delete your account and data per our data retention policy</li>
                    <li>Sections of these Terms that should survive will remain in effect</li>
                </ul>

                <!-- Section 13: Governing Law -->
                <h2 id="governing-law">13. Governing Law</h2>
                <p>These Terms shall be governed by and construed in accordance with the laws of the State of [Your State], without regard to its conflict of law provisions. Any legal action or proceeding arising under these Terms will be brought exclusively in the federal or state courts located in [Your City, State].</p>

                <h3>13.1 Dispute Resolution</h3>
                <p>We encourage resolving disputes through direct communication. If you have a concern:</p>
                <ol>
                    <li>Contact our customer support team first</li>
                    <li>We will work in good faith to resolve the issue</li>
                    <li>Escalation procedures are available if needed</li>
                    <li>Legal action should be a last resort</li>
                </ol>

                <h3>13.2 Class Action Waiver</h3>
                <p>You agree that any legal action or proceeding shall be brought in an individual capacity, and not as a class action, collective action, or representative action.</p>

                <!-- Section 14: Miscellaneous -->
                <h2 id="miscellaneous">14. Miscellaneous</h2>
                <h3>14.1 Changes to Terms</h3>
                <p>We reserve the right to modify these Terms at any time. When we make changes:</p>
                <ul>
                    <li>We will post the updated Terms on our website</li>
                    <li>We will notify users via email of material changes</li>
                    <li>Continued use of the Service constitutes acceptance of new Terms</li>
                    <li>You may cancel your account if you disagree with changes</li>
                </ul>

                <h3>14.2 Severability</h3>
                <p>If any provision of these Terms is held to be invalid or unenforceable, the remaining provisions will remain in full force and effect.</p>

                <h3>14.3 Entire Agreement</h3>
                <p>These Terms, together with our Privacy Policy, constitute the entire agreement between you and Somdul Table regarding the use of our Service.</p>

                <h3>14.4 Assignment</h3>
                <p>We may assign our rights and obligations under these Terms to any party at any time without notice to you. You may not assign your rights under these Terms without our prior written consent.</p>

                <!-- Contact Information -->
                <div class="contact-info">
                    <h4 id="contact">📞 Contact Information</h4>
                    <p>Questions about these Terms? Contact us:</p>
                    <p>
                        <strong>Somdul Table Legal Department</strong><br>
                        Email: <a href="mailto:legal@somdultable.com">legal@somdultable.com</a><br>
                        Phone: <a href="tel:+15551234567">+1 (555) 123-4567</a><br>
                        Address: [Your Business Address]<br>
                        Business Hours: Monday - Friday, 9:00 AM - 6:00 PM EST
                    </p>
                </div>

                <!-- Navigation Buttons -->
                <div class="nav-buttons">
                    <a href="privacy.php" class="btn btn-outline">
                        ← Privacy Policy
                    </a>
                    <a href="register.php" class="btn">
                        Accept & Sign Up →
                    </a>
                </div>
            </div>

            <!-- Footer -->
            <div class="terms-footer">
                <p><strong>Effective Date:</strong> <?php echo $last_updated; ?></p>
                <p>© <?php echo $current_year; ?> Somdul Table. All rights reserved.</p>
                <p>
                    <a href="home2.php">← Back to Home</a> | 
                    <a href="privacy.php">Privacy Policy</a> | 
                    <a href="contact.php">Contact Us</a> | 
                    <a href="help.php">Help Center</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Scroll to Top Button -->
    <button class="scroll-to-top" id="scrollToTop" aria-label="Scroll to top">
        ↑
    </button>

    <script>
        // Scroll to Top functionality
        const scrollToTopBtn = document.getElementById('scrollToTop');
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                scrollToTopBtn.classList.add('visible');
            } else {
                scrollToTopBtn.classList.remove('visible');
            }
        });
        
        scrollToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Smooth scrolling for anchor links
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

        // Reading progress indicator (optional)
        function updateReadingProgress() {
            const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
            const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrolled = (winScroll / height) * 100;
            
            // You can add a progress bar here if desired
            console.log('Reading progress:', scrolled + '%');
        }

        window.addEventListener('scroll', updateReadingProgress);

        // Highlight current section in table of contents
        const observerOptions = {
            rootMargin: '0px 0px -80% 0px',
            threshold: 0
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    // Remove active class from all TOC links
                    document.querySelectorAll('.toc a').forEach(link => {
                        link.style.fontWeight = 'normal';
                        link.style.color = 'var(--text-dark)';
                    });
                    
                    // Add active class to current section
                    const currentLink = document.querySelector(`.toc a[href="#${entry.target.id}"]`);
                    if (currentLink) {
                        currentLink.style.fontWeight = 'bold';
                        currentLink.style.color = 'var(--curry)';
                    }
                }
            });
        }, observerOptions);

        // Observe all sections
        document.querySelectorAll('h2[id]').forEach(section => {
            observer.observe(section);
        });

        // Print functionality
        function printTerms() {
            window.print();
        }

        // Add print button if desired
        document.addEventListener('DOMContentLoaded', function() {
            // You can add a print button here
            console.log('Terms of Service page loaded successfully');
            
            // Track analytics if needed
            if (typeof gtag !== 'undefined') {
                gtag('event', 'page_view', {
                    page_title: 'Terms of Service',
                    page_location: window.location.href
                });
            }
        });

        // Accessibility improvements
        document.addEventListener('keydown', function(e) {
            // Escape key to close any modal or return to top
            if (e.key === 'Escape') {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            
            // Alt + T to focus on table of contents
            if (e.altKey && e.key === 't') {
                e.preventDefault();
                document.querySelector('.toc').focus();
            }
        });

        // Mobile menu toggle for better navigation on small screens
        function toggleMobileMenu() {
            const toc = document.querySelector('.toc');
            if (window.innerWidth <= 768) {
                toc.style.display = toc.style.display === 'none' ? 'block' : 'none';
            }
        }

        // Responsive handling
        window.addEventListener('resize', function() {
            const toc = document.querySelector('.toc');
            if (window.innerWidth > 768) {
                toc.style.display = 'block';
            }
        });

        // Form validation for any embedded forms
        function validateEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Contact form handling (if needed in future)
        function handleContactForm(formData) {
            // This would handle contact form submissions
            console.log('Contact form submitted:', formData);
        }

        // Loading state management
        function showLoading(element) {
            element.style.opacity = '0.6';
            element.style.pointerEvents = 'none';
        }

        function hideLoading(element) {
            element.style.opacity = '1';
            element.style.pointerEvents = 'auto';
        }

        // Error handling for network requests
        function handleNetworkError(error) {
            console.error('Network error:', error);
            // Show user-friendly error message
            alert('Sorry, there was a connection error. Please check your internet connection and try again.');
        }

        // Initialize page
        function initializePage() {
            // Check if user is already logged in
            fetch('check_session.php', {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.logged_in) {
                    // User is logged in, could show different navigation
                    console.log('User is logged in as:', data.user_role);
                }
            })
            .catch(error => {
                console.log('Session check failed:', error);
            });
        }

        // Call initialization on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializePage);
        } else {
            initializePage();
        }
    </script>
</body>
</html>