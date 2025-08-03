<?php
/**
 * Somdul Table - Privacy Policy Page
 * File: privacy.php
 * Description: Comprehensive Privacy Policy for US-based meal delivery service
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
    <title>Privacy Policy - Somdul Table</title>
    <meta name="description" content="Privacy Policy for Somdul Table - Learn how we collect, use, and protect your personal information in our Thai meal delivery service.">
    <meta name="keywords" content="privacy policy, data protection, Somdul Table, Thai food delivery, personal information">
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

        .privacy-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            border: 1px solid rgba(255, 255, 255, 0.8);
            overflow: hidden;
        }

        .privacy-header {
            background: linear-gradient(135deg, var(--cream) 0%, #f8f6f3 100%);
            padding: 2.5rem 3rem 2rem;
            text-align: center;
            border-bottom: 1px solid var(--border-light);
        }

        .privacy-content {
            padding: 3rem;
        }

        /* Table of Contents */
        .toc {
            background: rgba(var(--sage), 0.1);
            border: 2px solid rgba(var(--sage), 0.3);
            border-radius: var(--radius-md);
            padding: 2rem;
            margin: 2rem 0;
        }

        .toc h3 {
            color: var(--sage);
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
            color: var(--sage);
            margin-right: 0.5rem;
        }

        .toc a {
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .toc a:hover {
            color: var(--sage);
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

        .notice-privacy {
            background: rgba(173, 184, 157, 0.1);
            border-color: var(--sage);
            color: #5a6b46;
        }

        .notice-rights {
            background: rgba(23, 162, 184, 0.1);
            border-color: var(--info);
            color: #0c5460;
        }

        .notice-security {
            background: rgba(189, 147, 121, 0.1);
            border-color: var(--brown);
            color: #8b6914;
        }

        .notice-important {
            background: rgba(220, 53, 69, 0.1);
            border-color: var(--danger);
            color: #721c24;
        }

        .notice-ccpa {
            background: rgba(255, 193, 7, 0.1);
            border-color: var(--warning);
            color: #856404;
        }

        /* Data Types Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            background: var(--white);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-soft);
        }

        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .data-table th {
            background: var(--cream);
            font-weight: 700;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        .data-table tbody tr:hover {
            background: rgba(var(--curry), 0.05);
        }

        /* Contact Information */
        .contact-info {
            background: linear-gradient(135deg, var(--brown) 0%, rgba(189, 147, 121, 0.8) 100%);
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
        .privacy-footer {
            background: var(--cream);
            padding: 2rem 3rem;
            text-align: center;
            border-top: 1px solid var(--border-light);
            color: var(--text-gray);
        }

        .privacy-footer p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .privacy-footer a {
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px solid transparent;
            transition: border-bottom-color 0.3s;
        }

        .privacy-footer a:hover {
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
            
            .privacy-content {
                padding: 2rem 1.5rem;
            }
            
            .privacy-header {
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

            .data-table {
                font-size: 0.9rem;
            }

            .data-table th,
            .data-table td {
                padding: 0.75rem 0.5rem;
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .privacy-container {
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
            .privacy-footer {
                display: none;
            }
            
            .privacy-container {
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
            <h1 style="color: white; margin: 0;">Privacy Policy</h1>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <div class="breadcrumb-content">
            <a href="home2.php">Home</a>
            <span class="breadcrumb-separator">›</span>
            <span>Privacy Policy</span>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <div class="privacy-container">
            <!-- Privacy Header -->
            <div class="privacy-header">
                <h1>Privacy Policy</h1>
                <p style="margin: 0; color: var(--text-gray); font-size: 1.1rem;">
                    Last updated: <?php echo $last_updated; ?>
                </p>
            </div>

            <!-- Privacy Content -->
            <div class="privacy-content">
                <!-- Table of Contents -->
                <div class="toc">
                    <h3>📋 Table of Contents</h3>
                    <ol>
                        <li><a href="#overview">Privacy Overview</a></li>
                        <li><a href="#information-collected">Information We Collect</a></li>
                        <li><a href="#how-we-use">How We Use Your Information</a></li>
                        <li><a href="#sharing">Information Sharing & Disclosure</a></li>
                        <li><a href="#data-security">Data Security</a></li>
                        <li><a href="#retention">Data Retention</a></li>
                        <li><a href="#your-rights">Your Privacy Rights</a></li>
                        <li><a href="#ccpa">California Privacy Rights (CCPA)</a></li>
                        <li><a href="#cookies">Cookies & Tracking</a></li>
                        <li><a href="#third-party">Third-Party Services</a></li>
                        <li><a href="#international">International Users</a></li>
                        <li><a href="#children">Children's Privacy</a></li>
                        <li><a href="#changes">Policy Changes</a></li>
                        <li><a href="#contact">Contact Information</a></li>
                    </ol>
                </div>

                <div class="notice notice-privacy">
                    <strong>🛡️ Your Privacy Matters:</strong> At Somdul Table, we are committed to protecting your personal information and being transparent about how we collect, use, and share your data.
                </div>

                <!-- Section 1: Privacy Overview -->
                <h2 id="overview">1. Privacy Overview</h2>
                <p>This Privacy Policy explains how <strong>Somdul Table</strong> ("we," "our," or "us") collects, uses, discloses, and protects your personal information when you use our website, mobile application, and meal delivery services (collectively, the "Service").</p>
                
                <p>We respect your privacy and are committed to protecting your personal data in accordance with applicable privacy laws, including the California Consumer Privacy Act (CCPA) and other state privacy regulations.</p>

                <h3>1.1 Key Principles</h3>
                <ul>
                    <li><strong>Transparency:</strong> We clearly explain what data we collect and why</li>
                    <li><strong>Control:</strong> You have choices about your personal information</li>
                    <li><strong>Security:</strong> We protect your data with industry-standard security measures</li>
                    <li><strong>Minimization:</strong> We only collect data that is necessary for our services</li>
                    <li><strong>Accuracy:</strong> We strive to keep your information accurate and up-to-date</li>
                </ul>

                <!-- Section 2: Information We Collect -->
                <h2 id="information-collected">2. Information We Collect</h2>
                <p>We collect several types of information to provide and improve our services:</p>

                <h3>2.1 Personal Information You Provide</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data Category</th>
                            <th>Examples</th>
                            <th>Purpose</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Contact Information</strong></td>
                            <td>Name, email, phone number</td>
                            <td>Account creation, order communication</td>
                        </tr>
                        <tr>
                            <td><strong>Delivery Information</strong></td>
                            <td>Address, delivery instructions, ZIP code</td>
                            <td>Meal delivery, service area determination</td>
                        </tr>
                        <tr>
                            <td><strong>Payment Information</strong></td>
                            <td>Credit card details, billing address</td>
                            <td>Processing payments, fraud prevention</td>
                        </tr>
                        <tr>
                            <td><strong>Dietary Information</strong></td>
                            <td>Food allergies, preferences, restrictions</td>
                            <td>Meal customization, safety recommendations</td>
                        </tr>
                        <tr>
                            <td><strong>Profile Information</strong></td>
                            <td>Date of birth, gender, preferences</td>
                            <td>Service personalization, age verification</td>
                        </tr>
                    </tbody>
                </table>

                <h3>2.2 Information Collected Automatically</h3>
                <ul>
                    <li><strong>Usage Data:</strong> Pages visited, features used, time spent on the service</li>
                    <li><strong>Device Information:</strong> IP address, browser type, device type, operating system</li>
                    <li><strong>Location Data:</strong> Approximate location based on IP address (for delivery zone verification)</li>
                    <li><strong>Cookies:</strong> Preferences, authentication tokens, analytics data</li>
                    <li><strong>Communication Records:</strong> Customer service interactions, chat logs</li>
                </ul>

                <h3>2.3 Information from Third Parties</h3>
                <ul>
                    <li><strong>Payment Processors:</strong> Transaction verification and fraud prevention data</li>
                    <li><strong>Social Media:</strong> Profile information if you sign up through social login</li>
                    <li><strong>Delivery Partners:</strong> Delivery status and location updates</li>
                    <li><strong>Analytics Providers:</strong> Website performance and user behavior insights</li>
                </ul>

                <div class="notice notice-security">
                    <strong>🔒 Payment Security:</strong> We do not store complete credit card numbers. Payment processing is handled by PCI-compliant third-party processors with industry-standard encryption.
                </div>

                <!-- Section 3: How We Use Your Information -->
                <h2 id="how-we-use">3. How We Use Your Information</h2>
                <h3>3.1 Primary Uses</h3>
                <ul>
                    <li><strong>Service Delivery:</strong> Process orders, manage subscriptions, coordinate deliveries</li>
                    <li><strong>Account Management:</strong> Create and maintain your account, authenticate users</li>
                    <li><strong>Customer Support:</strong> Respond to inquiries, resolve issues, provide assistance</li>
                    <li><strong>Payment Processing:</strong> Handle transactions, manage billing, prevent fraud</li>
                    <li><strong>Safety & Quality:</strong> Ensure food safety based on allergies and dietary restrictions</li>
                </ul>

                <h3>3.2 Service Improvement</h3>
                <ul>
                    <li><strong>Personalization:</strong> Recommend meals, customize experiences, improve relevance</li>
                    <li><strong>Analytics:</strong> Understand usage patterns, optimize performance, develop new features</li>
                    <li><strong>Quality Assurance:</strong> Monitor service quality, track delivery performance</li>
                    <li><strong>Research:</strong> Conduct surveys, gather feedback, analyze trends</li>
                </ul>

                <h3>3.3 Communication</h3>
                <ul>
                    <li><strong>Transactional:</strong> Order confirmations, delivery updates, account notifications</li>
                    <li><strong>Marketing:</strong> Promotional offers, new menu items, newsletters (with consent)</li>
                    <li><strong>Operational:</strong> Service announcements, policy updates, system maintenance</li>
                    <li><strong>Safety:</strong> Food recall notices, allergy alerts, health advisories</li>
                </ul>

                <h3>3.4 Legal and Safety</h3>
                <ul>
                    <li><strong>Compliance:</strong> Meet legal obligations, respond to lawful requests</li>
                    <li><strong>Protection:</strong> Protect rights, property, and safety of users and our business</li>
                    <li><strong>Fraud Prevention:</strong> Detect and prevent fraudulent activities</li>
                    <li><strong>Dispute Resolution:</strong> Resolve conflicts, investigate complaints</li>
                </ul>

                <!-- Section 4: Information Sharing & Disclosure -->
                <h2 id="sharing">4. Information Sharing & Disclosure</h2>
                <p>We do not sell your personal information to third parties. We share information only in limited circumstances:</p>

                <h3>4.1 Service Providers</h3>
                <ul>
                    <li><strong>Payment Processors:</strong> Stripe, PayPal, Apple Pay, Google Pay</li>
                    <li><strong>Delivery Partners:</strong> Third-party delivery services and drivers</li>
                    <li><strong>Cloud Services:</strong> AWS, Google Cloud for data hosting and processing</li>
                    <li><strong>Analytics:</strong> Google Analytics, marketing platforms for insights</li>
                    <li><strong>Communication:</strong> Email service providers, SMS services, customer support tools</li>
                </ul>

                <h3>4.2 Business Transfers</h3>
                <p>In the event of a merger, acquisition, or sale of assets, your personal information may be transferred to the new entity, subject to the same privacy protections.</p>

                <h3>4.3 Legal Requirements</h3>
                <ul>
                    <li><strong>Law Enforcement:</strong> When required by law, court order, or government request</li>
                    <li><strong>Safety:</strong> To protect the safety of users, employees, or the public</li>
                    <li><strong>Legal Rights:</strong> To enforce our terms, protect our property, or defend legal claims</li>
                    <li><strong>Fraud Prevention:</strong> To investigate and prevent fraudulent activities</li>
                </ul>

                <h3>4.4 Consent-Based Sharing</h3>
                <p>We may share information with your explicit consent for specific purposes, such as:</p>
                <ul>
                    <li>Social media integration</li>
                    <li>Partner promotions</li>
                    <li>Research participation</li>
                    <li>Public testimonials or reviews</li>
                </ul>

                <div class="notice notice-rights">
                    <strong>🛡️ Your Control:</strong> You can always withdraw consent for optional data sharing. Essential sharing for service delivery may be required to continue using our services.
                </div>

                <!-- Section 5: Data Security -->
                <h2 id="data-security">5. Data Security</h2>
                <h3>5.1 Security Measures</h3>
                <p>We implement comprehensive security measures to protect your personal information:</p>
                <ul>
                    <li><strong>Encryption:</strong> All data transmitted is encrypted using TLS 1.3</li>
                    <li><strong>Secure Storage:</strong> Data at rest is encrypted using AES-256 encryption</li>
                    <li><strong>Access Controls:</strong> Role-based access with multi-factor authentication</li>
                    <li><strong>Regular Audits:</strong> Security assessments and penetration testing</li>
                    <li><strong>Employee Training:</strong> Privacy and security training for all staff</li>
                    <li><strong>Incident Response:</strong> Procedures for detecting and responding to breaches</li>
                </ul>

                <h3>5.2 Payment Security</h3>
                <ul>
                    <li><strong>PCI Compliance:</strong> Our payment processors are PCI DSS Level 1 certified</li>
                    <li><strong>Tokenization:</strong> Credit card data is tokenized and not stored on our servers</li>
                    <li><strong>Fraud Detection:</strong> Real-time monitoring for suspicious transactions</li>
                    <li><strong>Secure Processing:</strong> End-to-end encryption for all payment data</li>
                </ul>

                <h3>5.3 Data Breach Response</h3>
                <p>In the unlikely event of a data breach:</p>
                <ul>
                    <li>We will investigate and contain the incident immediately</li>
                    <li>Affected users will be notified within 72 hours</li>
                    <li>Relevant authorities will be notified as required by law</li>
                    <li>We will provide guidance on protective measures</li>
                    <li>A full incident report will be prepared and shared</li>
                </ul>

                <!-- Section 6: Data Retention -->
                <h2 id="retention">6. Data Retention</h2>
                <h3>6.1 Retention Periods</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data Type</th>
                            <th>Retention Period</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Account Information</strong></td>
                            <td>Until account deletion + 30 days</td>
                            <td>Service provision, support</td>
                        </tr>
                        <tr>
                            <td><strong>Order History</strong></td>
                            <td>7 years</td>
                            <td>Tax compliance, warranty claims</td>
                        </tr>
                        <tr>
                            <td><strong>Payment Records</strong></td>
                            <td>7 years</td>
                            <td>Financial compliance, dispute resolution</td>
                        </tr>
                        <tr>
                            <td><strong>Communication Logs</strong></td>
                            <td>3 years</td>
                            <td>Customer service, quality improvement</td>
                        </tr>
                        <tr>
                            <td><strong>Analytics Data</strong></td>
                            <td>2 years (aggregated)</td>
                            <td>Service optimization, trend analysis</td>
                        </tr>
                        <tr>
                            <td><strong>Marketing Preferences</strong></td>
                            <td>Until opt-out + 30 days</td>
                            <td>Compliance with marketing laws</td>
                        </tr>
                    </tbody>
                </table>

                <h3>6.2 Secure Deletion</h3>
                <p>When data reaches the end of its retention period, we:</p>
                <ul>
                    <li>Permanently delete personal information from our active systems</li>
                    <li>Remove data from backup systems within 90 days</li>
                    <li>Use secure deletion methods that prevent data recovery</li>
                    <li>Maintain logs of deletion activities for compliance</li>
                </ul>

                <!-- Section 7: Your Privacy Rights -->
                <h2 id="your-rights">7. Your Privacy Rights</h2>
                <h3>7.1 Access Rights</h3>
                <p>You have the right to:</p>
                <ul>
                    <li><strong>Access:</strong> Request a copy of your personal information</li>
                    <li><strong>Portability:</strong> Receive your data in a machine-readable format</li>
                    <li><strong>Correction:</strong> Update or correct inaccurate information</li>
                    <li><strong>Deletion:</strong> Request deletion of your personal information</li>
                    <li><strong>Restriction:</strong> Limit how we process your data</li>
                </ul>

                <h3>7.2 How to Exercise Your Rights</h3>
                <p>To exercise your privacy rights:</p>
                <ol>
                    <li><strong>Account Settings:</strong> Most data can be updated directly in your account</li>
                    <li><strong>Email Request:</strong> Contact us at privacy@somdultable.com</li>
                    <li><strong>Phone:</strong> Call our privacy team at +1 (555) 123-4567</li>
                    <li><strong>Mail:</strong> Send written requests to our address below</li>
                </ol>

                <h3>7.3 Response Times</h3>
                <ul>
                    <li><strong>Account Updates:</strong> Immediate for most changes</li>
                    <li><strong>Data Requests:</strong> Within 30 days of verification</li>
                    <li><strong>Deletion Requests:</strong> Within 30 days (may require identity verification)</li>
                    <li><strong>Complex Requests:</strong> Up to 60 days with notification</li>
                </ul>

                <div class="notice notice-rights">
                    <strong>📧 Easy Access:</strong> You can download your data directly from your account dashboard under "Privacy Settings" > "Download My Data".
                </div>

                <!-- Section 8: California Privacy Rights (CCPA) -->
                <h2 id="ccpa">8. California Privacy Rights (CCPA)</h2>
                <h3>8.1 CCPA Rights Summary</h3>
                <p>California residents have additional rights under the CCPA:</p>
                <ul>
                    <li><strong>Right to Know:</strong> Categories and specific pieces of personal information collected</li>
                    <li><strong>Right to Delete:</strong> Request deletion of personal information</li>
                    <li><strong>Right to Opt-Out:</strong> Opt-out of the sale of personal information</li>
                    <li><strong>Right to Non-Discrimination:</strong> Equal service regardless of privacy choices</li>
                </ul>

                <h3>8.2 Information We Collect (CCPA Categories)</h3>
                <ul>
                    <li><strong>Identifiers:</strong> Name, email, phone, address, IP address</li>
                    <li><strong>Commercial Information:</strong> Purchase history, preferences</li>
                    <li><strong>Internet Activity:</strong> Website usage, interactions</li>
                    <li><strong>Geolocation Data:</strong> Approximate location for delivery</li>
                    <li><strong>Sensory Data:</strong> Customer service call recordings</li>
                    <li><strong>Inferences:</strong> Preferences, characteristics, behavior patterns</li>
                </ul>

                <h3>8.3 Sale of Personal Information</h3>
                <div class="notice notice-ccpa">
                    <strong>🚫 We Do Not Sell Personal Information:</strong> Somdul Table does not sell personal information to third parties for monetary consideration.
                </div>

                <h3>8.4 CCPA Requests</h3>
                <p>California residents can make CCPA requests:</p>
                <ul>
                    <li><strong>Online Form:</strong> <a href="privacy-request.php">Submit a privacy request</a></li>
                    <li><strong>Email:</strong> ccpa@somdultable.com</li>
                    <li><strong>Phone:</strong> +1 (555) 123-4567 (toll-free for CA residents)</li>
                </ul>

                <!-- Section 9: Cookies & Tracking -->
                <h2 id="cookies">9. Cookies & Tracking</h2>
                <h3>9.1 Types of Cookies We Use</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Cookie Type</th>
                            <th>Purpose</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Essential</strong></td>
                            <td>Login, security, shopping cart</td>
                            <td>Session/1 year</td>
                        </tr>
                        <tr>
                            <td><strong>Analytics</strong></td>
                            <td>Usage statistics, performance</td>
                            <td>2 years</td>
                        </tr>
                        <tr>
                            <td><strong>Personalization</strong></td>
                            <td>Preferences, recommendations</td>
                            <td>1 year</td>
                        </tr>
                        <tr>
                            <td><strong>Marketing</strong></td>
                            <td>Advertising, retargeting</td>
                            <td>30-90 days</td>
                        </tr>
                    </tbody>
                </table>

                <h3>9.2 Managing Cookies</h3>
                <p>You can control cookies through:</p>
                <ul>
                    <li><strong>Browser Settings:</strong> Block or delete cookies in your browser</li>
                    <li><strong>Cookie Preferences:</strong> Use our cookie consent tool</li>
                    <li><strong>Opt-Out Tools:</strong> Industry opt-out pages (NAI, DAA)</li>
                    <li><strong>Do Not Track:</strong> We respect Do Not Track browser signals</li>
                </ul>

                <h3>9.3 Third-Party Tracking</h3>
                <p>We use third-party services that may track users:</p>
                <ul>
                    <li><strong>Google Analytics:</strong> Website performance and user behavior</li>
                    <li><strong>Facebook Pixel:</strong> Social media advertising and conversion tracking</li>
                    <li><strong>Hotjar:</strong> User experience and heatmap analysis</li>
                    <li><strong>Intercom:</strong> Customer support and communication</li>
                </ul>

                <!-- Section 10: Third-Party Services -->
                <h2 id="third-party">10. Third-Party Services</h2>
                <h3>10.1 Payment Providers</h3>
                <ul>
                    <li><strong>Stripe:</strong> <a href="https://stripe.com/privacy" target="_blank">Stripe Privacy Policy</a></li>
                    <li><strong>PayPal:</strong> <a href="https://www.paypal.com/privacy" target="_blank">PayPal Privacy Statement</a></li>
                    <li><strong>Apple Pay:</strong> <a href="https://support.apple.com/privacy" target="_blank">Apple Privacy Policy</a></li>
                    <li><strong>Google Pay:</strong> <a href="https://payments.google.com/privacy" target="_blank">Google Pay Privacy Notice</a></li>
                </ul>

                <h3>10.2 Social Media Integration</h3>
                <p>When you connect social media accounts:</p>
                <ul>
                    <li>We may access basic profile information</li>
                    <li>Social platforms may receive information about your interactions</li>
                    <li>You can revoke permissions in your social media settings</li>
                    <li>Each platform has its own privacy policy</li>
                </ul>

                <h3>10.3 Analytics and Marketing</h3>
                <ul>
                    <li><strong>Google Analytics:</strong> Anonymized usage statistics</li>
                    <li><strong>Mailchimp:</strong> Email marketing campaigns</li>
                    <li><strong>Twilio:</strong> SMS notifications and communication</li>
                    <li><strong>Zendesk:</strong> Customer support ticket management</li>
                </ul>

                <!-- Section 11: International Users -->
                <h2 id="international">11. International Users</h2>
                <h3>11.1 Data Transfers</h3>
                <p>If you access our service from outside the United States:</p>
                <ul>
                    <li>Your data may be transferred to and processed in the United States</li>
                    <li>We comply with applicable international transfer requirements</li>
                    <li>Data is protected by U.S. privacy laws and our security measures</li>
                    <li>You consent to cross-border data transfers by using our service</li>
                </ul>

                <h3>11.2 International Privacy Rights</h3>
                <p>We respect international privacy rights where applicable:</p>
                <ul>
                    <li><strong>GDPR (EU):</strong> European users have rights under GDPR</li>
                    <li><strong>PIPEDA (Canada):</strong> Canadian privacy protections</li>
                    <li><strong>Local Laws:</strong> Compliance with applicable local privacy laws</li>
                </ul>

                <!-- Section 12: Children's Privacy -->
                <h2 id="children">12. Children's Privacy</h2>
                <h3>12.1 Age Restrictions</h3>
                <ul>
                    <li>Our service is not intended for children under 13 years old</li>
                    <li>We do not knowingly collect personal information from children under 13</li>
                    <li>Users must be 18+ to create accounts or have parental consent</li>
                    <li>Parents can contact us to review or delete their child's information</li>
                </ul>

                <h3>12.2 Parental Rights</h3>
                <p>Parents and guardians have the right to:</p>
                <ul>
                    <li>Review their child's personal information</li>
                    <li>Request deletion of their child's data</li>
                    <li>Refuse further collection of their child's information</li>
                    <li>Contact us with privacy concerns about their child</li>
                </ul>

                <div class="notice notice-important">
                    <strong>⚠️ Important:</strong> If we discover we have collected information from a child under 13, we will delete it immediately. Please contact us if you believe we have collected such information.
                </div>

                <!-- Section 13: Policy Changes -->
                <h2 id="changes">13. Policy Changes</h2>
                <h3>13.1 How We Update This Policy</h3>
                <p>We may update this Privacy Policy to reflect:</p>
                <ul>
                    <li>Changes in our data practices</li>
                    <li>New legal requirements</li>
                    <li>Service improvements or new features</li>
                    <li>Enhanced security measures</li>
                </ul>

                <h3>13.2 Notification of Changes</h3>
                <ul>
                    <li><strong>Material Changes:</strong> 30-day advance notice via email</li>
                    <li><strong>Minor Updates:</strong> Posted on website with updated date</li>
                    <li><strong>Legal Changes:</strong> Immediate posting with notification</li>
                    <li><strong>User Choice:</strong> Option to cancel service if you disagree</li>
                </ul>

                <h3>13.3 Version History</h3>
                <p>Previous versions of this policy are available upon request for transparency and compliance purposes.</p>

                <!-- Contact Information -->
                <div class="contact-info">
                    <h4 id="contact">📞 Privacy Contact Information</h4>
                    <p>Questions about this Privacy Policy? Contact us:</p>
                    <p>
                        <strong>Privacy Team</strong><br>
                        Email: <a href="mailto:privacy@somdultable.com">privacy@somdultable.com</a><br>
                        Phone: <a href="tel:+15551234567">+1 (555) 123-4567</a><br>
                        Mail: Somdul Table Privacy Team<br>
                        [Your Business Address]<br>
                        [City, State ZIP]
                    </p>
                    <p style="margin-top: 1rem; font-size: 0.9rem;">
                        <strong>Response Time:</strong> We respond to privacy inquiries within 2 business days
                    </p>
                </div>

                <!-- Navigation Buttons -->
                <div class="nav-buttons">
                    <a href="terms.php" class="btn btn-outline">
                        ← Terms of Service
                    </a>
                    <a href="register.php" class="btn">
                        Accept & Sign Up →
                    </a>
                </div>

                <!-- Additional Resources -->
                <h2>Additional Privacy Resources</h2>
                <h3>14.1 Educational Resources</h3>
                <ul>
                    <li><a href="https://www.ftc.gov/privacy" target="_blank">FTC Privacy Guide</a> - Federal Trade Commission privacy information</li>
                    <li><a href="https://oag.ca.gov/privacy/ccpa" target="_blank">CCPA Information</a> - California Attorney General CCPA resources</li>
                    <li><a href="https://www.consumer.ftc.gov/articles/0272-how-keep-your-personal-information-secure" target="_blank">Personal Information Security</a> - FTC security tips</li>
                </ul>

                <h3>14.2 Privacy Tools</h3>
                <ul>
                    <li><strong>Account Dashboard:</strong> Manage your privacy settings directly</li>
                    <li><strong>Data Export:</strong> Download your personal information</li>
                    <li><strong>Cookie Manager:</strong> Control tracking preferences</li>
                    <li><strong>Communication Preferences:</strong> Manage email and SMS settings</li>
                </ul>

                <h3>14.3 Industry Compliance</h3>
                <p>Somdul Table maintains compliance with:</p>
                <ul>
                    <li><strong>CCPA</strong> - California Consumer Privacy Act</li>
                    <li><strong>CAN-SPAM</strong> - Email marketing regulations</li>
                    <li><strong>TCPA</strong> - Telephone Consumer Protection Act</li>
                    <li><strong>COPPA</strong> - Children's Online Privacy Protection Act</li>
                    <li><strong>SOX</strong> - Sarbanes-Oxley financial data protection</li>
                </ul>
            </div>

            <!-- Footer -->
            <div class="privacy-footer">
                <p><strong>Effective Date:</strong> <?php echo $last_updated; ?></p>
                <p>© <?php echo $current_year; ?> Somdul Table. All rights reserved.</p>
                <p>
                    <a href="home2.php">← Back to Home</a> | 
                    <a href="terms.php">Terms of Service</a> | 
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
                        currentLink.style.color = 'var(--sage)';
                    }
                }
            });
        }, observerOptions);

        // Observe all sections
        document.querySelectorAll('h2[id]').forEach(section => {
            observer.observe(section);
        });

        // Cookie consent banner (simplified)
        function showCookieConsent() {
            if (!localStorage.getItem('cookie_consent')) {
                const banner = document.createElement('div');
                banner.style.cssText = `
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    background: var(--text-dark);
                    color: white;
                    padding: 1rem 2rem;
                    text-align: center;
                    z-index: 10000;
                    font-family: 'BaticaSans', sans-serif;
                `;
                banner.innerHTML = `
                    <p style="margin: 0 0 1rem 0;">We use cookies to enhance your experience and analyze our traffic. By continuing to use our site, you consent to our use of cookies.</p>
                    <button onclick="acceptCookies()" style="background: var(--curry); color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; margin-right: 1rem; cursor: pointer;">Accept</button>
                    <a href="#cookies" style="color: var(--curry);">Learn More</a>
                `;
                document.body.appendChild(banner);
            }
        }

        function acceptCookies() {
            localStorage.setItem('cookie_consent', 'true');
            const banner = document.querySelector('div[style*="position: fixed"]');
            if (banner) {
                banner.remove();
            }
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            showCookieConsent();
            
            // Track page view for analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', 'page_view', {
                    page_title: 'Privacy Policy',
                    page_location: window.location.href
                });
            }
        });

        // Privacy request form handler (if form exists)
        function handlePrivacyRequest(formData) {
            // This would handle privacy request submissions
            console.log('Privacy request submitted:', formData);
        }

        // Data download functionality
        function downloadUserData() {
            // This would trigger user data download
            alert('Your data download will be prepared and sent to your email address within 24 hours.');
        }

        // Account deletion request
        function requestAccountDeletion() {
            if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                // This would handle account deletion request
                alert('Account deletion request submitted. You will receive a confirmation email shortly.');
            }
        }

        // Accessibility improvements
        document.addEventListener('keydown', function(e) {
            // Escape key to close any modal or return to top
            if (e.key === 'Escape') {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            
            // Alt + P to focus on privacy contact info
            if (e.altKey && e.key === 'p') {
                e.preventDefault();
                document.querySelector('#contact').scrollIntoView({ behavior: 'smooth' });
            }
        });

        // Print functionality
        function printPolicy() {
            window.print();
        }

        // Add keyboard shortcuts info
        console.log('Privacy Policy Keyboard Shortcuts:');
        console.log('- Escape: Scroll to top');
        console.log('- Alt + P: Jump to contact information');
    </script>
</body>
</html>