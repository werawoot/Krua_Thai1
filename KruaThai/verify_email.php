<?php
/**
 * Krua Thai - Email Verification Handler
 * File: verify_email.php
 * Description: Handle email verification tokens and activate user accounts
 */

session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'classes/User.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$message_type = '';
$success = false;
$user_data = null;
$show_resend = false;

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $message = 'No verification token provided. Please check your email for the correct verification link.';
    $message_type = 'error';
} else {
    $token = sanitizeInput($_GET['token']);
    
    // Validate token format (should be 64 characters hex)
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
        $message = 'Invalid verification token format. Please check your email for the correct verification link.';
        $message_type = 'error';
    } else {
        // Check if token exists and get user info
        $query = "SELECT id, email, first_name, last_name, status, email_verified, created_at 
                  FROM users 
                  WHERE email_verification_token = :token 
                  LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user_data = $stmt->fetch();
            
            // Check if already verified
            if ($user_data['email_verified'] == 1) {
                $success = true;
                $message = 'Your email has already been verified! Your account is active and ready to use.';
                $message_type = 'info';
            } else {
                // Check token age (expire after 24 hours)
                $created_time = strtotime($user_data['created_at']);
                $current_time = time();
                $token_age_hours = ($current_time - $created_time) / 3600;
                
                if ($token_age_hours > 24) {
                    $message = 'This verification link has expired (valid for 24 hours). Please request a new verification email.';
                    $message_type = 'warning';
                    $show_resend = true;
                } else {
                    // Verify the email
                    $user = new User($db);
                    if ($user->verifyEmail($token)) {
                        $success = true;
                        $message = 'Email verified successfully! Your account is now active and you can start ordering delicious Thai meals.';
                        $message_type = 'success';
                        
                        // Log successful verification
                        logActivity('email_verified_success', $user_data['id'], getRealIPAddress(), [
                            'email' => $user_data['email'],
                            'token_age_hours' => round($token_age_hours, 2)
                        ]);
                        
                        // Auto-login the user
                        $_SESSION['user_id'] = $user_data['id'];
                        $_SESSION['user_email'] = $user_data['email'];
                        $_SESSION['user_name'] = $user_data['first_name'] . ' ' . $user_data['last_name'];
                        $_SESSION['user_role'] = 'customer';
                        $_SESSION['just_verified'] = true;
                        
                    } else {
                        $message = 'Email verification failed due to a technical error. Please try again or contact support.';
                        $message_type = 'error';
                        
                        logActivity('email_verification_failed', $user_data['id'], getRealIPAddress(), [
                            'email' => $user_data['email'],
                            'reason' => 'database_update_failed'
                        ]);
                    }
                }
            }
        } else {
            $message = 'Invalid or expired verification token. The link may have been used already or may have expired.';
            $message_type = 'error';
            $show_resend = true;
            
            logActivity('email_verification_failed', null, getRealIPAddress(), [
                'reason' => 'token_not_found',
                'token_prefix' => substr($token, 0, 10) . '...'
            ]);
        }
    }
}

// Handle resend verification email
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resend_email'])) {
    $email = sanitizeInput($_POST['email']);
    
    if (empty($email) || !validateEmail($email)) {
        $message = 'Please enter a valid email address.';
        $message_type = 'error';
    } else {
        $user = new User($db);
        if ($user->getByEmail($email)) {
            if ($user->email_verified == 1) {
                $message = 'This email is already verified. You can sign in to your account.';
                $message_type = 'info';
            } else {
                if ($user->resendVerificationEmail()) {
                    $message = 'Verification email sent successfully! Please check your inbox (and spam folder).';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to send verification email. Please try again later or contact support.';
                    $message_type = 'error';
                }
            }
        } else {
            $message = 'No account found with this email address. Please check the email or register for a new account.';
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Krua Thai</title>
    <meta name="description" content="Verify your email address to activate your Krua Thai account">
    <meta name="robots" content="noindex, nofollow">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --olive: #3d4028;
            --matcha: #4e4f22;
            --brown: #866028;
            --cream: #d1b990;
            --light-cream: #f5ede4;
            --white: #ffffff;
            --gray: #6c757d;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --shadow: rgba(61, 64, 40, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", sans-serif;
            background: linear-gradient(135deg, var(--light-cream) 0%, #f9f5ed 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            color: var(--olive);
        }

        .verification-container {
            background: var(--white);
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 8px 30px var(--shadow);
            max-width: 600px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .logo-section {
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--white), var(--cream));
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px var(--shadow);
            margin-bottom: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.8);
        }

        .rice-grain {
            width: 14px;
            height: 24px;
            background: linear-gradient(180deg, var(--olive), var(--matcha));
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            transform: rotate(-3deg);
            box-shadow: inset 1px 0 2px rgba(255, 255, 255, 0.3);
        }

        .logo-text {
            color: var(--olive);
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .status-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            line-height: 1;
        }

        .status-icon.success {
            color: var(--success);
        }

        .status-icon.error {
            color: var(--danger);
        }

        .status-icon.warning {
            color: var(--warning);
        }

        .status-icon.info {
            color: var(--info);
        }

        h1 {
            color: var(--olive);
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .message {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
            color: var(--gray);
        }

        .message.success {
            color: var(--success);
        }

        .message.error {
            color: var(--danger);
        }

        .message.warning {
            color: #856404;
        }

        .message.info {
            color: var(--info);
        }

        .user-info {
            background: rgba(61, 64, 40, 0.05);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 2px solid rgba(61, 64, 40, 0.1);
        }

        .user-info h3 {
            color: var(--olive);
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .user-info p {
            color: var(--gray);
            margin: 0.3rem 0;
        }

        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(45deg, var(--brown), #a67c00);
            color: var(--white);
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 0 0.5rem 0.5rem 0;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(134, 96, 40, 0.3);
        }

        .btn:hover {
            background: linear-gradient(45deg, #a67c00, var(--brown));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(134, 96, 40, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: var(--brown);
            border: 2px solid var(--brown);
            box-shadow: none;
        }

        .btn-secondary:hover {
            background: var(--brown);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(134, 96, 40, 0.3);
        }

        .btn-warning {
            background: linear-gradient(45deg, var(--warning), #e0a800);
            color: #856404;
        }

        .btn-warning:hover {
            background: linear-gradient(45deg, #e0a800, var(--warning));
        }

        .resend-section {
            background: rgba(255, 193, 7, 0.1);
            border: 2px solid var(--warning);
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }

        .resend-section h3 {
            color: #856404;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }

        .resend-section p {
            color: #856404;
            margin-bottom: 1.5rem;
        }

        .resend-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 400px;
            margin: 0 auto;
        }

        .resend-form input[type="email"] {
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            color: var(--olive);
            background: var(--white);
            transition: all 0.3s ease;
        }

        .resend-form input[type="email"]:focus {
            outline: none;
            border-color: var(--warning);
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.1);
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .next-steps {
            background: rgba(40, 167, 69, 0.1);
            border: 2px solid var(--success);
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: left;
        }

        .next-steps h3 {
            color: var(--success);
            margin-bottom: 1rem;
            text-align: center;
            font-size: 1.3rem;
        }

        .next-steps ol {
            color: var(--olive);
            padding-left: 1.5rem;
            line-height: 1.8;
        }

        .next-steps li {
            margin-bottom: 0.5rem;
        }

        .footer-info {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
            color: var(--gray);
            font-size: 0.9rem;
        }

        .footer-info p {
            margin: 0.3rem 0;
        }

        .footer-info a {
            color: var(--brown);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-info a:hover {
            text-decoration: underline;
        }

        /* Mobile responsive */
        @media (max-width: 600px) {
            body {
                padding: 15px;
            }
            
            .verification-container {
                padding: 2rem 1.5rem;
            }
            
            .logo-text {
                font-size: 1.6rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .message {
                font-size: 1rem;
            }
            
            .btn {
                padding: 0.9rem 1.5rem;
                font-size: 0.95rem;
                margin: 0.3rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
        }

        /* Loading animation */
        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top: 2px solid var(--white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Accessibility improvements */
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
        .btn:focus-visible,
        input:focus-visible {
            outline: 2px solid var(--brown);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo-icon">
                <div class="rice-grain"></div>
            </div>
            <h2 class="logo-text">Krua Thai</h2>
        </div>

        <!-- Status Display -->
        <?php if ($success): ?>
            <div class="status-icon success" aria-label="Success">✅</div>
            <h1>Email Verified Successfully!</h1>
            
            <?php if ($user_data): ?>
                <div class="user-info">
                    <h3>Welcome to Krua Thai, <?php echo htmlspecialchars($user_data['first_name']); ?>!</h3>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?></p>
                    <p><strong>Account Status:</strong> <span style="color: var(--success); font-weight: 600;">Active</span></p>
                </div>
            <?php endif; ?>
            
            <p class="message success"><?php echo htmlspecialchars($message); ?></p>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="next-steps">
                    <h3>🎉 You're all set! Here's what you can do now:</h3>
                    <ol>
                        <li><strong>Browse our healthy Thai meals</strong> and discover authentic flavors</li>
                        <li><strong>Choose a meal plan</strong> that fits your lifestyle</li>
                        <li><strong>Customize your preferences</strong> for spice level and dietary needs</li>
                        <li><strong>Place your first order</strong> and get fresh meals delivered</li>
                    </ol>
                </div>
                
                <div class="action-buttons">
                    <a href="dashboard.php" class="btn">Go to My Dashboard</a>
                    <a href="menu.php" class="btn btn-secondary">Browse Menu</a>
                </div>
            <?php else: ?>
                <div class="action-buttons">
                    <a href="login.php" class="btn">Sign In to Your Account</a>
                    <a href="menu.php" class="btn btn-secondary">Browse Menu</a>
                </div>
            <?php endif; ?>

        <?php elseif ($message_type === 'warning'): ?>
            <div class="status-icon warning" aria-label="Warning">⚠️</div>
            <h1>Verification Link Expired</h1>
            <p class="message warning"><?php echo htmlspecialchars($message); ?></p>
            
            <?php if ($show_resend): ?>
                <div class="resend-section">
                    <h3>📧 Get a New Verification Link</h3>
                    <p>Enter your email address and we'll send you a fresh verification link:</p>
                    
                    <form method="POST" class="resend-form" id="resendForm">
                        <input type="email" 
                               name="email" 
                               placeholder="Enter your email address" 
                               required 
                               autocomplete="email"
                               value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>"
                               aria-label="Email address">
                        <button type="submit" name="resend_email" class="btn btn-warning" id="resendBtn">
                            Send New Verification Email
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="register.php" class="btn btn-secondary">Register New Account</a>
                <a href="login.php" class="btn btn-secondary">Back to Sign In</a>
            </div>

        <?php elseif ($message_type === 'info'): ?>
            <div class="status-icon info" aria-label="Information">ℹ️</div>
            <h1>Account Already Verified</h1>
            
            <?php if ($user_data): ?>
                <div class="user-info">
                    <h3>Account Information</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?></p>
                    <p><strong>Status:</strong> <span style="color: var(--success); font-weight: 600;">Verified & Active</span></p>
                </div>
            <?php endif; ?>
            
            <p class="message info"><?php echo htmlspecialchars($message); ?></p>
            
            <div class="action-buttons">
                <a href="login.php" class="btn">Sign In to Your Account</a>
                <a href="menu.php" class="btn btn-secondary">Browse Menu</a>
            </div>

        <?php else: ?>
            <div class="status-icon error" aria-label="Error">❌</div>
            <h1>Verification Failed</h1>
            <p class="message error"><?php echo htmlspecialchars($message); ?></p>
            
            <?php if ($show_resend): ?>
                <div class="resend-section">
                    <h3>📧 Need a New Verification Link?</h3>
                    <p>If you have an account with us, enter your email to receive a new verification link:</p>
                    
                    <form method="POST" class="resend-form" id="resendForm">
                        <input type="email" 
                               name="email" 
                               placeholder="Enter your email address" 
                               required 
                               autocomplete="email"
                               aria-label="Email address">
                        <button type="submit" name="resend_email" class="btn btn-warning" id="resendBtn">
                            Send Verification Email
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="register.php" class="btn">Create New Account</a>
                <a href="contact.php" class="btn btn-secondary">Contact Support</a>
                <a href="index.php" class="btn btn-secondary">Back to Home</a>
            </div>
        <?php endif; ?>

        <!-- Footer Information -->
        <div class="footer-info">
            <p><strong>Need Help?</strong></p>
            <p>📧 Email: <a href="mailto:support@kruathai.com">support@kruathai.com</a></p>
            <p>📞 Phone: <a href="tel:021234567">02-123-4567</a></p>
            <p>🕐 Support Hours: Mon-Sat 8AM-8PM</p>
            <p style="margin-top: 1rem; font-size: 0.85rem;">
                <a href="privacy.php">Privacy Policy</a> | 
                <a href="terms.php">Terms of Service</a> | 
                <a href="help.php">Help Center</a>
            </p>
        </div>
    </div>

    <script>
        // Handle resend form submission
        document.getElementById('resendForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('resendBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
                submitBtn.textContent = 'Sending...';
                
                // Re-enable after timeout in case of errors
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                    submitBtn.textContent = 'Send Verification Email';
                }, 10000);
            }
        });

        // Auto-redirect for successful verification
        <?php if ($success && isset($_SESSION['user_id'])): ?>
            setTimeout(() => {
                if (confirm('Would you like to go to your dashboard now?')) {
                    window.location.href = 'dashboard.php';
                }
            }, 5000);
        <?php endif; ?>

        // Email validation for resend form
        document.querySelector('input[type="email"]')?.addEventListener('input', function() {
            const email = this.value;
            const submitBtn = document.getElementById('resendBtn');
            
            if (email && !isValidEmail(email)) {
                this.style.borderColor = '#dc3545';
                if (submitBtn) submitBtn.disabled = true;
            } else if (email) {
                this.style.borderColor = '#28a745';
                if (submitBtn) submitBtn.disabled = false;
            } else {
                this.style.borderColor = '#e9ecef';
                if (submitBtn) submitBtn.disabled = false;
            }
        });

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        // Analytics tracking (if you have analytics)
        <?php if ($success): ?>
            // Track successful email verification
            if (typeof gtag !== 'undefined') {
                gtag('event', 'email_verified', {
                    'event_category': 'user_engagement',
                    'event_label': 'email_verification_success'
                });
            }
        <?php endif; ?>

        // Accessibility: Focus management
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on first interactive element
            const firstButton = document.querySelector('.btn');
            const emailInput = document.querySelector('input[type="email"]');
            
            if (emailInput) {
                emailInput.focus();
            } else if (firstButton) {
                firstButton.focus();
            }
        });

        // Keyboard navigation improvements
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Clear focus on escape
                document.activeElement.blur();
            }
        });
    </script>
</body>
</html>