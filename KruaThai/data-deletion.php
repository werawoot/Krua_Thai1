<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Data Deletion - Somdul Table</title>
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

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: var(--white);
            box-shadow: var(--shadow-soft);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(231, 76, 60, 0.3);
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

        .warning-box {
            background: linear-gradient(135deg, #fff3e0, #ffe0b2);
            border-left: 4px solid #ff9800;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: var(--radius-md);
        }

        .warning-box .warning-icon {
            color: #ff9800;
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }

        .danger-box {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            border-left: 4px solid #f44336;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: var(--radius-md);
        }

        .danger-box .danger-icon {
            color: #f44336;
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }

        .info-box {
            background: linear-gradient(135deg, var(--cream), #f8f6f3);
            border-left: 4px solid var(--sage);
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: var(--radius-md);
        }

        .deletion-form {
            background: var(--white);
            border: 2px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin: 2rem 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            font-family: 'BaticaSans', sans-serif;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            margin: 1.5rem 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-top: 0.2rem;
        }

        .step-indicator {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 2rem 0;
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--sage), #9bb087);
            color: var(--white);
            border-radius: var(--radius-md);
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: var(--white);
            color: var(--sage);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .contact-info {
            background: var(--curry);
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

            .step-indicator {
                flex-direction: column;
                text-align: center;
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
            <h1>User Data Deletion</h1>
            <p>Request deletion of your personal data from Somdul Table and connected Facebook services.</p>
        </div>

        <div class="content-section">
            <div class="info-box">
                <p><strong><i class="fas fa-shield-alt" style="color: var(--sage); margin-right: 0.5rem;"></i>Your Data Rights:</strong> You have the right to request deletion of your personal data at any time. This includes data collected through our website, mobile app, and Facebook integration.</p>
            </div>

            <h2>What Data Will Be Deleted?</h2>
            <p>When you request data deletion, we will remove the following information from our systems:</p>
            
            <h3>Account Information</h3>
            <ul>
                <li>Personal details (name, email, phone number, address)</li>
                <li>Account credentials and login information</li>
                <li>Profile settings and preferences</li>
                <li>Subscription history and meal selections</li>
            </ul>

            <h3>Facebook Integration Data</h3>
            <ul>
                <li>Facebook profile information (name, email, profile picture)</li>
                <li>Facebook ID and connection tokens</li>
                <li>Any data received through Facebook Login</li>
                <li>Social sharing activity and interactions</li>
            </ul>

            <h3>Transaction and Usage Data</h3>
            <ul>
                <li>Order history and payment information</li>
                <li>Delivery addresses and preferences</li>
                <li>Customer support tickets and communications</li>
                <li>Website usage analytics linked to your account</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>What Data We May Retain</h2>
            
            <div class="warning-box">
                <p><i class="fas fa-exclamation-triangle warning-icon"></i><strong>Legal Requirements:</strong> Some information may be retained for legal, regulatory, or security purposes as required by law.</p>
            </div>

            <p>We may retain certain information even after deletion request:</p>
            <ul>
                <li>Financial records for tax and accounting purposes (7 years)</li>
                <li>Anonymized analytics data that cannot identify you personally</li>
                <li>Information required for ongoing legal proceedings</li>
                <li>Security logs for fraud prevention (anonymized)</li>
            </ul>
        </div>

        <div class="content-section">
            <h2>How to Request Data Deletion</h2>
            
            <div class="step-indicator">
                <div class="step-number">1</div>
                <div>
                    <strong>Verify Your Identity</strong>
                    <p>Complete the form below with your account information to verify your identity.</p>
                </div>
            </div>

            <div class="step-indicator">
                <div class="step-number">2</div>
                <div>
                    <strong>Review Consequences</strong>
                    <p>Understand what data will be deleted and the irreversible nature of this action.</p>
                </div>
            </div>

            <div class="step-indicator">
                <div class="step-number">3</div>
                <div>
                    <strong>Confirmation</strong>
                    <p>We'll process your request within 30 days and send a confirmation email.</p>
                </div>
            </div>
        </div>

        <div class="content-section">
            <h2>Data Deletion Request Form</h2>
            
            <div class="danger-box">
                <p><i class="fas fa-exclamation-circle danger-icon"></i><strong>Warning:</strong> Data deletion is permanent and cannot be undone. Your account will be permanently closed and all associated data will be removed from our systems.</p>
            </div>

            <form class="deletion-form" action="process-deletion.php" method="POST">
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your registered email address">
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" required placeholder="Enter your full name as registered">
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" placeholder="Enter your phone number (optional)">
                </div>

                <div class="form-group">
                    <label for="user_id">User ID or Account Number</label>
                    <input type="text" id="user_id" name="user_id" placeholder="If known, enter your User ID">
                </div>

                <div class="form-group">
                    <label for="deletion_reason">Reason for Deletion (Optional)</label>
                    <select id="deletion_reason" name="deletion_reason">
                        <option value="">Select a reason</option>
                        <option value="no_longer_needed">No longer need the service</option>
                        <option value="privacy_concerns">Privacy concerns</option>
                        <option value="service_issues">Service quality issues</option>
                        <option value="moving">Moving to different area</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="additional_info">Additional Information</label>
                    <textarea id="additional_info" name="additional_info" rows="4" placeholder="Any additional information or specific requests..."></textarea>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="facebook_data" name="facebook_data" value="yes">
                    <label for="facebook_data">Also delete data received through Facebook Login integration</label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="confirm_deletion" name="confirm_deletion" value="yes" required>
                    <label for="confirm_deletion">I understand that this action is permanent and cannot be undone. I confirm that I want to delete all my personal data from Somdul Table. *</label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="identity_confirmation" name="identity_confirmation" value="yes" required>
                    <label for="identity_confirmation">I confirm that I am the owner of this account and authorized to request this data deletion. *</label>
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-danger" style="margin-right: 1rem;">
                        <i class="fas fa-trash-alt"></i>
                        Submit Deletion Request
                    </button>
                    <a href="home2.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <div class="content-section">
            <h2>Alternative Options</h2>
            
            <p>Before requesting complete data deletion, consider these alternatives:</p>
            
            <h3>Account Deactivation</h3>
            <p>Temporarily suspend your account while keeping your data for potential future reactivation.</p>
            
            <h3>Privacy Settings</h3>
            <p>Adjust your privacy settings to limit data collection and sharing without deleting your account.</p>
            
            <h3>Data Export</h3>
            <p>Download a copy of your personal data before deletion. <a href="data-export.php" style="color: var(--curry);">Request Data Export</a></p>
            
            <h3>Subscription Pause</h3>
            <p>Pause your meal subscriptions without deleting your account or historical data.</p>
        </div>

        <div class="content-section">
            <h2>Processing Timeline</h2>
            
            <p>Your data deletion request will be processed according to the following timeline:</p>
            
            <ul>
                <li><strong>Immediate:</strong> Account access will be suspended</li>
                <li><strong>Within 7 days:</strong> Active subscriptions will be cancelled</li>
                <li><strong>Within 30 days:</strong> Personal data will be deleted from active systems</li>
                <li><strong>Within 90 days:</strong> Data will be removed from backup systems</li>
            </ul>
            
            <div class="info-box">
                <p><strong>Note:</strong> You will receive email confirmations at each stage of the deletion process.</p>
            </div>
        </div>

        <div class="contact-info">
            <h4>Need Help with Data Deletion?</h4>
            <p>If you have questions about the data deletion process or need assistance, please contact our Privacy Team:</p>
            <p>
                <strong>Email:</strong> <a href="mailto:privacy@somdultable.com">privacy@somdultable.com</a><br>
                <strong>Phone:</strong> <a href="tel:+1-555-PRIVACY">+1 (555) PRIVACY</a><br>
                <strong>Support Hours:</strong> Monday - Friday, 9 AM - 6 PM PST
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

    <script>
        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.deletion-form');
            const submitBtn = document.querySelector('button[type="submit"]');
            
            form.addEventListener('submit', function(e) {
                const confirmDeletion = document.getElementById('confirm_deletion').checked;
                const identityConfirmation = document.getElementById('identity_confirmation').checked;
                
                if (!confirmDeletion || !identityConfirmation) {
                    e.preventDefault();
                    alert('Please confirm both checkboxes to proceed with data deletion.');
                    return;
                }
                
                // Double confirmation for deletion
                const finalConfirm = confirm(
                    'FINAL CONFIRMATION: This will permanently delete all your data from Somdul Table. ' +
                    'This action cannot be undone. Are you absolutely sure you want to proceed?'
                );
                
                if (!finalConfirm) {
                    e.preventDefault();
                }
            });
            
            // Real-time validation feedback
            const requiredFields = form.querySelectorAll('input[required], select[required]');
            requiredFields.forEach(field => {
                field.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.style.borderColor = '#f44336';
                    } else {
                        this.style.borderColor = '#4caf50';
                    }
                });
            });
        });
    </script>
</body>
</html>