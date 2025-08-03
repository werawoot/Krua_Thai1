<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Somdul Table</title>
    <link href="https://ydpschool.com/fonts/" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
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
            src: url('https://ydpschool.com/fonts/BaticaSans-Medium.woff2') format('woff2'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.woff') format('woff'),
                 url('https://ydpschool.com/fonts/BaticaSans-Medium.ttf') format('truetype');
            font-weight: 500;
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

        /* Header */
        .header {
            background: var(--white);
            box-shadow: var(--shadow-soft);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
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

        .nav-links a:hover {
            color: var(--curry);
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

        /* Main Content */
        .main-content {
            max-width: 900px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 3rem 0;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f6f3 100%);
            border-radius: var(--radius-lg);
        }

        .page-header h1 {
            font-size: 2.5rem;
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .page-header p {
            font-size: 1.2rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .content-section {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
        }

        .content-section h2 {
            color: var(--curry);
            font-size: 1.5rem;
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .content-section h3 {
            color: var(--brown);
            font-size: 1.2rem;
            margin: 1.5rem 0 0.8rem 0;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .content-section p {
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
            line-height: 1.7;
        }

        .content-section ul {
            margin: 1rem 0;
            padding-left: 1.5rem;
        }

        .content-section li {
            margin-bottom: 0.5rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        .highlight-box {
            background: linear-gradient(135deg, var(--cream), #f8f6f3);
            border-left: 4px solid var(--curry);
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: var(--radius-md);
        }

        .contact-info {
            background: var(--sage);
            color: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin: 2rem 0;
            text-align: center;
        }

        .contact-info h4 {
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .contact-info a {
            color: var(--white);
            text-decoration: none;
            font-weight: 500;
        }

        .contact-info a:hover {
            text-decoration: underline;
        }

        /* Footer */
        .footer {
            background: var(--text-dark);
            color: var(--white);
            text-align: center;
            padding: 2rem;
            margin-top: 3rem;
        }

        .footer p {
            font-family: 'BaticaSans', sans-serif;
        }

        .footer a {
            color: var(--curry);
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .nav-links {
                flex-wrap: wrap;
                gap: 1rem;
                justify-content: center;
            }

            .main-content {
                padding: 2rem 1rem;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .content-section {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="home2.php" class="logo">
                <div class="logo-icon">S</div>
                <span class="logo-text">Somdul Table</span>
            </a>
            
            <nav class="nav-links">
                <a href="home2.php">Home</a>
                <a href="menus.php">Menu</a>
                <a href="home2.php#about">About</a>
                <a href="home2.php#contact">Contact</a>
                <a href="home2.php" class="btn btn-primary">Get Started</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1>Privacy Policy</h1>
            <p>Your privacy is important to us. Learn how we protect and handle your personal information.</p>
        </div>

        <div class="content-section">
            <div class="highlight-box">
                <p><strong>Last updated:</strong> January 2025</p>
                <p>This Privacy Policy describes how Somdul Table ("we," "our," or "us") collects, uses, and protects your personal information when you use our Thai restaurant management and meal delivery services.</p>
            </div>

            <h2>1. Information We Collect</h2>
            
            <h3>Personal Information</h3>
            <p>When you create an account or use our services, we may collect:</p>
            <ul>
                <li>Name, email address, and phone number</li>
                <li>Delivery address and location information</li>
                <li>Payment information (processed securely through third-party providers)</li>
                <li>Dietary preferences and food allergies</li>
                <li>Order history and subscription preferences</li>
            </ul>

            <h3>Usage Information</h3>
            <p>We automatically collect certain information when you use our services:</p>
            <ul>
                <li>Device information (IP address, browser type, device type)</li>
                <li>Website usage patterns and interactions</li>
                <li>Location data for delivery purposes</li>
                <li>Cookies and similar tracking technologies</li>
            </ul>

            <h3>Facebook Integration Data</h3>
            <p>If you choose to connect your Facebook account, we may receive:</p>
            <ul>
                <li>Your Facebook profile information (name, email, profile picture)</li>
                <li>Friends list (only if you grant permission)</li>
                <li>Facebook ID for account linking purposes</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>2. How We Use Your Information</h2>
            
            <p>We use your personal information to:</p>
            <ul>
                <li><strong>Provide Services:</strong> Process orders, manage subscriptions, and deliver meals</li>
                <li><strong>Customer Support:</strong> Respond to inquiries and resolve issues</li>
                <li><strong>Personalization:</strong> Customize meal recommendations based on preferences</li>
                <li><strong>Communication:</strong> Send order updates, promotional offers, and service announcements</li>
                <li><strong>Analytics:</strong> Improve our services and user experience</li>
                <li><strong>Legal Compliance:</strong> Meet regulatory requirements and prevent fraud</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>3. Information Sharing and Disclosure</h2>
            
            <p>We do not sell your personal information. We may share your information in the following circumstances:</p>
            
            <h3>Service Providers</h3>
            <ul>
                <li>Payment processors for secure transaction handling</li>
                <li>Delivery partners for meal delivery services</li>
                <li>Cloud hosting providers for data storage</li>
                <li>Analytics providers for service improvement</li>
            </ul>

            <h3>Legal Requirements</h3>
            <p>We may disclose your information when required by law, court order, or to protect our rights and safety.</p>

            <h3>Business Transfers</h3>
            <p>In the event of a merger, acquisition, or sale of assets, your information may be transferred as part of the transaction.</p>
        </div>

        <div class="content-section">
            <h2>4. Data Security</h2>
            
            <p>We implement appropriate technical and organizational measures to protect your personal information:</p>
            <ul>
                <li>Encryption of sensitive data in transit and at rest</li>
                <li>Regular security assessments and updates</li>
                <li>Access controls and employee training</li>
                <li>Secure payment processing (PCI DSS compliant)</li>
            </ul>

            <div class="highlight-box">
                <p><strong>Note:</strong> While we strive to protect your information, no method of transmission over the internet is 100% secure. We cannot guarantee absolute security but continuously work to improve our protection measures.</p>
            </div>
        </div>

        <div class="content-section">
            <h2>5. Your Rights and Choices</h2>
            
            <p>You have the following rights regarding your personal information:</p>
            
            <h3>Access and Control</h3>
            <ul>
                <li><strong>Access:</strong> Request a copy of your personal information</li>
                <li><strong>Correction:</strong> Update or correct inaccurate information</li>
                <li><strong>Deletion:</strong> Request deletion of your personal information</li>
                <li><strong>Portability:</strong> Receive your data in a machine-readable format</li>
            </ul>

            <h3>Communication Preferences</h3>
            <ul>
                <li>Opt-out of marketing emails via unsubscribe links</li>
                <li>Adjust notification settings in your account dashboard</li>
                <li>Contact us to modify your communication preferences</li>
            </ul>

            <h3>Facebook Data</h3>
            <p>You can disconnect your Facebook account at any time through your account settings. To delete Facebook-related data, please visit our <a href="data-deletion.php" style="color: var(--curry);">Data Deletion page</a>.</p>
        </div>

        <div class="content-section">
            <h2>6. Cookies and Tracking Technologies</h2>
            
            <p>We use cookies and similar technologies to:</p>
            <ul>
                <li>Remember your preferences and settings</li>
                <li>Analyze website performance and usage</li>
                <li>Provide personalized content and advertisements</li>
                <li>Enable social media features</li>
            </ul>

            <p>You can control cookies through your browser settings, but disabling them may affect website functionality.</p>
        </div>

        <div class="content-section">
            <h2>7. Data Retention</h2>
            
            <p>We retain your personal information for as long as necessary to:</p>
            <ul>
                <li>Provide our services and maintain your account</li>
                <li>Comply with legal obligations</li>
                <li>Resolve disputes and enforce agreements</li>
                <li>Improve our services and user experience</li>
            </ul>

            <p>When you delete your account, we will delete or anonymize your personal information within 30 days, except where retention is required by law.</p>
        </div>

        <div class="content-section">
            <h2>8. Children's Privacy</h2>
            
            <p>Our services are not directed to children under 13 years of age. We do not knowingly collect personal information from children under 13. If you believe we have collected information from a child under 13, please contact us immediately.</p>
        </div>

        <div class="content-section">
            <h2>9. International Data Transfers</h2>
            
            <p>Your information may be transferred to and processed in countries other than your country of residence. We ensure appropriate safeguards are in place to protect your personal information in accordance with applicable data protection laws.</p>
        </div>

        <div class="content-section">
            <h2>10. Changes to This Privacy Policy</h2>
            
            <p>We may update this Privacy Policy from time to time. We will notify you of any material changes by:</p>
            <ul>
                <li>Posting the updated policy on our website</li>
                <li>Sending an email notification to registered users</li>
                <li>Displaying a prominent notice on our platform</li>
            </ul>

            <p>Your continued use of our services after the effective date constitutes acceptance of the updated Privacy Policy.</p>
        </div>

        <div class="contact-info">
            <h4>Questions About This Privacy Policy?</h4>
            <p>If you have any questions or concerns about this Privacy Policy or our data practices, please contact us:</p>
            <p>
                <strong>Email:</strong> <a href="mailto:privacy@somdultable.com">privacy@somdultable.com</a><br>
                <strong>Phone:</strong> <a href="tel:+1-555-SOMDUL">+1 (555) SOMDUL</a><br>
                <strong>Address:</strong> 123 Thai Garden Street, Food District, CA 90210
            </p>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <div style="width: 35px; height: 35px; background: linear-gradient(135deg, var(--curry), var(--brown)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--white); font-size: 1.2rem; font-weight: 700;">S</div>
            <span style="font-size: 1.3rem; font-weight: 700;">Somdul Table</span>
        </div>
        <p style="margin-bottom: 0.5rem;">Healthy Thai food delivered to your door</p>
        <p style="font-size: 0.9rem; opacity: 0.8;">&copy; 2025 Somdul Table. All rights reserved. | <a href="privacy-policy.php">Privacy Policy</a> | <a href="data-deletion.php">Data Deletion</a></p>
    </footer>
</body>
</html>