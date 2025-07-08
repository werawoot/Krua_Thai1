<?php
/**
 * Krua Thai - User Login Page
 * File: login.php
 * Description: Secure login with brute-force protection and session management
 */
define('DEBUG', true); // Set to false in production

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $redirect_url = $_SESSION['user_role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php';
    header("Location: $redirect_url");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'classes/User.php';

$database = new Database();
$db = $database->getConnection();

$errors = [];
$success_message = '';
$email_value = '';
$show_verification_resend = false;
$verification_email = '';

// Check for flash messages from other pages
if (isset($_SESSION['flash_message'])) {
    $success_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    $email_value = $email; // Preserve email on error
    
    // Basic validation
    if (empty($email)) {
        $errors[] = "Email address is required";
    } elseif (!validateEmail($email)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    // Rate limiting check (simple IP-based)
    $ip_address = getRealIPAddress();
    $rate_limit_key = "login_attempts_" . md5($ip_address);
    
    // Check rate limiting (max 10 attempts per IP per 15 minutes)
    if (!isset($_SESSION[$rate_limit_key])) {
        $_SESSION[$rate_limit_key] = ['count' => 0, 'last_attempt' => time()];
    }
    
    $rate_limit = &$_SESSION[$rate_limit_key];
    
    // Reset counter if 15 minutes passed
    if (time() - $rate_limit['last_attempt'] > 900) {
        $rate_limit = ['count' => 0, 'last_attempt' => time()];
    }
    
    if ($rate_limit['count'] >= 10) {
        $errors[] = "Too many login attempts from your IP address. Please try again later.";
    }
    
    // Proceed with authentication if no errors
    if (empty($errors)) {
        $user = new User($db);
        $auth_result = $user->authenticate($email, $password);
        
        // Increment rate limit counter
        $rate_limit['count']++;
        $rate_limit['last_attempt'] = time();
        
        if ($auth_result['success']) {
            // Reset rate limit on successful login
            unset($_SESSION[$rate_limit_key]);
            
            // Set session variables
            $_SESSION['user_id'] = $auth_result['user_id'];
            $_SESSION['user_email'] = $user->email;
            $_SESSION['user_name'] = $user->getFullName();
            $_SESSION['user_role'] = $user->role;
            $_SESSION['login_time'] = time();
            
            // Handle remember me functionality
            if ($remember_me) {
                $remember_token = generateToken(32);
                $expires = time() + (30 * 24 * 60 * 60); // 30 days
                
                // Store remember token in database (you'd need to add this field)
                // For now, we'll use a secure cookie
                setcookie(
                    'remember_token', 
                    $remember_token, 
                    $expires, 
                    '/', 
                    '', 
                    true, // Secure
                    true  // HttpOnly
                );
            }
            
            // Log successful login
            logActivity('login_success', $user->id, $ip_address, [
                'email' => $email,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'remember_me' => $remember_me
            ]);
            
            // Redirect based on role or intended destination
            $redirect_url = 'dashboard.php';
            
            if (isset($_GET['redirect'])) {
                $redirect_url = sanitizeInput($_GET['redirect']);
                // Validate redirect URL to prevent open redirects
                if (!preg_match('/^[a-zA-Z0-9\/_\-\.]+\.php(\?.*)?$/', $redirect_url)) {
                    $redirect_url = 'dashboard.php';
                }
            } elseif ($user->role === 'admin') {
                $redirect_url = 'admin/dashboard.php';
            } elseif ($user->role === 'kitchen') {
                $redirect_url = 'kitchen/dashboard.php';
            } elseif ($user->role === 'rider') {
                $redirect_url = 'rider/dashboard.php';
            }
            
            // Special handling for just verified users
            if (isset($_SESSION['just_verified'])) {
                unset($_SESSION['just_verified']);
                $_SESSION['flash_message'] = "Welcome to Krua Thai! Your account is now active.";
                $_SESSION['flash_type'] = 'success';
            }
            
            header("Location: $redirect_url");
            exit();
            
        } else {
            // Handle different authentication failure reasons
            if ($auth_result['requires_verification']) {
                $show_verification_resend = true;
                $verification_email = $email;
                $errors[] = $auth_result['message'] . " Check your email or request a new verification link below.";
            } elseif ($auth_result['account_locked']) {
                $errors[] = $auth_result['message'];
                $errors[] = "Account will be automatically unlocked after 15 minutes.";
            } else {
                $errors[] = $auth_result['message'];
                
                // Add helpful hints for common issues
                if (strpos($auth_result['message'], 'Invalid email or password') !== false) {
                    $errors[] = "Tip: Check your email spelling and ensure Caps Lock is off.";
                }
            }
            
            // Log failed login attempt
            logActivity('login_failed', $user->id ?? null, $ip_address, [
                'email' => $email,
                'reason' => $auth_result['message'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }
    }
}

// Handle verification email resend
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resend_verification'])) {
    $resend_email = sanitizeInput($_POST['resend_email']);
    
    if (!empty($resend_email) && validateEmail($resend_email)) {
        $user = new User($db);
        if ($user->getByEmail($resend_email)) {
            if ($user->resendVerificationEmail()) {
                $success_message = "Verification email sent to " . htmlspecialchars($resend_email) . ". Please check your inbox.";
            } else {
                $errors[] = "Failed to send verification email. Please try again later.";
            }
        } else {
            $errors[] = "No account found with this email address.";
        }
    } else {
        $errors[] = "Please enter a valid email address.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Krua Thai</title>
    <meta name="description" content="Sign in to your Krua Thai account to order healthy Thai meals and manage your subscriptions">
    <meta name="keywords" content="login, sign in, Krua Thai, Thai food delivery">
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
            --light-gray: #f8f9fa;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --shadow: rgba(61, 64, 40, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", sans-serif;
            line-height: 1.6;
            color: var(--olive);
            background: linear-gradient(135deg, var(--light-cream) 0%, #f9f5ed 100%);
            min-height: 100vh;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 8px 30px var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.8);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, var(--olive) 0%, var(--matcha) 100%);
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            color: var(--white);
            position: relative;
        }

        .login-header::before {
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

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--white), var(--cream));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.8);
        }

        .rice-grain {
            width: 12px;
            height: 20px;
            background: linear-gradient(180deg, var(--olive), var(--matcha));
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            transform: rotate(-3deg);
            box-shadow: inset 1px 0 2px rgba(255, 255, 255, 0.3);
        }

        .logo-text {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }

        .login-form {
            padding: 2.5rem 2rem;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--olive);
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .form-subtitle {
            color: var(--gray);
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        /* Alerts */
        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border: 2px solid;
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

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--olive);
            font-size: 0.95rem;
        }

        .required {
            color: var(--danger);
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 1rem 1.2rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--olive);
            font-family: inherit;
        }

        input:focus {
            outline: none;
            border-color: var(--brown);
            box-shadow: 0 0 0 3px rgba(134, 96, 40, 0.1);
            transform: translateY(-1px);
        }

        input.error {
            border-color: var(--danger);
            background-color: #fff5f5;
        }

        input.success {
            border-color: var(--success);
            background-color: #f0fff4;
        }

        /* Remember Me Checkbox */
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin: 1.5rem 0;
            padding: 1rem;
            background: rgba(61, 64, 40, 0.05);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .remember-me:hover {
            background: rgba(61, 64, 40, 0.08);
        }

        .remember-me input[type="checkbox"] {
            width: auto;
            margin: 0;
            transform: scale(1.2);
            accent-color: var(--brown);
        }

        .remember-me label {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray);
            cursor: pointer;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(45deg, var(--brown), #a67c00);
            color: var(--white);
            padding: 1.2rem 2rem;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(134, 96, 40, 0.3);
            margin-top: 1rem;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(45deg, #a67c00, var(--brown));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(134, 96, 40, 0.4);
        }

        .btn-primary:disabled {
            background: var(--gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.7;
        }

        .btn-secondary {
            background: transparent;
            color: var(--brown);
            padding: 0.8rem 1.5rem;
            border: 2px solid var(--brown);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-secondary:hover {
            background: var(--brown);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(134, 96, 40, 0.3);
        }

        /* Forgot Password Link */
        .forgot-password {
            text-align: center;
            margin: 1.5rem 0;
        }

        .forgot-password a {
            color: var(--brown);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            border-bottom: 1px solid transparent;
            transition: border-bottom-color 0.3s;
        }

        .forgot-password a:hover {
            border-bottom-color: var(--brown);
        }

        /* Verification Resend Section */
        .verification-section {
            background: rgba(255, 193, 7, 0.1);
            border: 2px solid var(--warning);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .verification-section h4 {
            color: #856404;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .verification-section p {
            color: #856404;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .verification-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .verification-form input[type="email"] {
            font-size: 0.9rem;
            padding: 0.8rem 1rem;
        }

        .verification-form button {
            padding: 0.8rem 1.5rem;
            font-size: 0.9rem;
            background: linear-gradient(45deg, var(--warning), #e0a800);
            color: #856404;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .verification-form button:hover {
            background: linear-gradient(45deg, #e0a800, var(--warning));
            transform: translateY(-1px);
        }

        /* Footer Links */
        .login-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
            color: var(--gray);
        }

        .login-footer a {
            color: var(--brown);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px solid transparent;
            transition: border-bottom-color 0.3s;
        }

        .login-footer a:hover {
            border-bottom-color: var(--brown);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 2rem 0;
            color: var(--gray);
            font-size: 0.9rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e9ecef;
        }

        .divider span {
            padding: 0 1rem;
        }

        /* Loading State */
        .loading {
            position: relative;
            pointer-events: none;
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

        /* Mobile Responsive */
        @media (max-width: 600px) {
            body {
                padding: 15px;
            }
            
            .login-container {
                max-width: 100%;
            }
            
            .login-header {
                padding: 2rem 1.5rem 1.5rem;
            }
            
            .login-form {
                padding: 2rem 1.5rem;
            }
            
            .logo-text {
                font-size: 1.6rem;
            }
            
            .form-title {
                font-size: 1.3rem;
            }
            
            .verification-form {
                gap: 0.8rem;
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            :root {
                --shadow: rgba(0, 0, 0, 0.3);
            }
            
            .login-container {
                border: 2px solid var(--olive);
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
            outline: 2px solid var(--brown);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <div class="logo">
                <div class="logo-icon">
                    <div class="rice-grain"></div>
                </div>
                <span class="logo-text">Krua Thai</span>
            </div>
            <p class="login-subtitle">Welcome back to healthy Thai cuisine</p>
        </div>

        <!-- Form -->
        <div class="login-form">
            <h1 class="form-title">Sign In</h1>
            <p class="form-subtitle">Access your account to order delicious meals</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert" aria-live="polite">
                    <?php if (count($errors) == 1): ?>
                        <?php echo htmlspecialchars($errors[0]); ?>
                    <?php else: ?>
                        <strong>Please fix the following issues:</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert" aria-live="polite">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm" novalidate>
                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           required 
                           autocomplete="email"
                           value="<?php echo htmlspecialchars($email_value); ?>"
                           aria-describedby="email_help"
                           autofocus>
                    <small id="email_help" style="color: var(--gray); font-size: 0.85rem;">The email address you used to register</small>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           autocomplete="current-password"
                           aria-describedby="password_help">
                    <small id="password_help" style="color: var(--gray); font-size: 0.85rem;">Your account password</small>
                </div>

                <div class="remember-me">
                    <input type="checkbox" 
                           id="remember_me" 
                           name="remember_me" 
                           value="1">
                    <label for="remember_me">Remember me for 30 days</label>
                </div>

                <button type="submit" class="btn-primary" id="loginBtn">
                    <span id="login_text">Sign In</span>
                </button>
            </form>

            <?php if ($show_verification_resend): ?>
                <div class="verification-section">
                    <h4>📧 Account Not Verified</h4>
                    <p>Your account needs email verification. Didn't receive the email?</p>
                    
                    <form method="POST" class="verification-form" id="resendForm">
                        <input type="email" 
                               name="resend_email" 
                               placeholder="Enter your email address" 
                               required 
                               value="<?php echo htmlspecialchars($verification_email); ?>"
                               aria-label="Email for verification resend">
                        <button type="submit" name="resend_verification" id="resendBtn">
                            Send Verification Email
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="forgot-password">
                <a href="forgot_password.php">Forgot your password?</a>
            </div>

            <div class="divider">
                <span>New to Krua Thai?</span>
            </div>

            <div style="text-align: center;">
                <a href="register.php" class="btn-secondary">Create New Account</a>
            </div>

            <div class="login-footer">
                <p>By signing in, you agree to our <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a></p>
                <p style="margin-top: 1rem;">
                    <a href="index.php">← Back to Home</a> | 
                    <a href="help.php">Need Help?</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Form validation and UX enhancements
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const loginBtn = document.getElementById('loginBtn');
        const loginText = document.getElementById('login_text');

        // Real-time email validation
        emailInput.addEventListener('blur', function() {
            const email = this.value;
            if (email && !isValidEmail(email)) {
                this.classList.add('error');
                this.classList.remove('success');
            } else if (email) {
                this.classList.remove('error');
                this.classList.add('success');
            } else {
                this.classList.remove('error', 'success');
            }
        });

        emailInput.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                this.classList.remove('error');
            }
        });

        // Password field feedback
        passwordInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                this.classList.remove('error');
                this.classList.add('success');
            } else {
                this.classList.remove('error', 'success');
            }
        });

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        // Form submission handling
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = emailInput.value;
            const password = passwordInput.value;

            // Client-side validation
            let hasErrors = false;

            if (!email) {
                emailInput.classList.add('error');
                hasErrors = true;
            }

            if (!password) {
                passwordInput.classList.add('error');
                hasErrors = true;
            }

            if (!email || !isValidEmail(email)) {
                emailInput.classList.add('error');
                hasErrors = true;
            }

            if (hasErrors) {
                e.preventDefault();
                emailInput.focus();
                return;
            }

            // Show loading state
            loginBtn.disabled = true;
            loginBtn.classList.add('loading');
            loginText.textContent = 'Signing in...';
            
            // Re-enable after timeout (in case of server errors)
            setTimeout(() => {
                loginBtn.disabled = false;
                loginBtn.classList.remove('loading');
                loginText.textContent = 'Sign In';
            }, 10000);
        });

        // Verification email resend form
        document.getElementById('resendForm')?.addEventListener('submit', function(e) {
            const resendBtn = document.getElementById('resendBtn');
            if (resendBtn) {
                resendBtn.disabled = true;
                resendBtn.textContent = 'Sending...';
                
                setTimeout(() => {
                    resendBtn.disabled = false;
                    resendBtn.textContent = 'Send Verification Email';
                }, 8000);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Enter key on email field moves to password
            if (e.key === 'Enter' && document.activeElement === emailInput) {
                e.preventDefault();
                passwordInput.focus();
            }
            
            // Escape key clears focus
            if (e.key === 'Escape') {
                document.activeElement.blur();
            }
        });

        // Auto-focus management
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on email field if empty, otherwise password
            if (!emailInput.value) {
                emailInput.focus();
            } else if (!passwordInput.value) {
                passwordInput.focus();
            }
        });

        // Remember me tooltip
        const rememberCheckbox = document.getElementById('remember_me');
        const rememberLabel = document.querySelector('label[for="remember_me"]');
        
        rememberLabel.addEventListener('mouseenter', function() {
            rememberLabel.title = 'Your login will be remembered for 30 days on this device';
        });

        // Auto-complete support
        if (window.PasswordCredential) {
            navigator.credentials.get({
                password: true,
                mediation: 'optional'
            }).then(function(credential) {
                if (credential) {
                    emailInput.value = credential.id;
                    passwordInput.value = credential.password;
                    
                    // Trigger validation
                    emailInput.dispatchEvent(new Event('blur'));
                    passwordInput.dispatchEvent(new Event('input'));
                }
            }).catch(function(error) {
                console.log('Credential retrieval failed:', error);
            });
        }

        // Form analytics (if you have analytics)
        function trackFormInteraction(action, element) {
            if (typeof gtag !== 'undefined') {
                gtag('event', action, {
                    'event_category': 'login_form',
                    'event_label': element
                });
            }
        }

        // Track form interactions
        emailInput.addEventListener('focus', () => trackFormInteraction('focus', 'email'));
        passwordInput.addEventListener('focus', () => trackFormInteraction('focus', 'password'));
        rememberCheckbox.addEventListener('change', () => trackFormInteraction('toggle', 'remember_me'));

        // Prevent form double submission
        let formSubmitted = false;
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            formSubmitted = true;
        });

        // Security: Clear password on page unload (if not remembering)
        window.addEventListener('beforeunload', function() {
            if (!rememberCheckbox.checked) {
                passwordInput.value = '';
            }
        });

        // Check for browser password manager
        setTimeout(function() {
            if (emailInput.value && passwordInput.value) {
                emailInput.dispatchEvent(new Event('blur'));
                passwordInput.dispatchEvent(new Event('input'));
            }
        }, 100);

        // Accessibility improvements
        emailInput.addEventListener('invalid', function(e) {
            e.preventDefault();
            this.classList.add('error');
            this.focus();
        });

        passwordInput.addEventListener('invalid', function(e) {
            e.preventDefault();
            this.classList.add('error');
            this.focus();
        });

        // Dynamic help text based on errors
        <?php if (!empty($errors) && in_array('Invalid email or password', $errors)): ?>
            // Show additional help for failed login
            setTimeout(function() {
                const helpDiv = document.createElement('div');
                helpDiv.style.cssText = 'background: rgba(255,193,7,0.1); padding: 1rem; border-radius: 8px; margin-top: 1rem; font-size: 0.9rem; color: #856404;';
                helpDiv.innerHTML = '<strong>Login Help:</strong><br>• Make sure your email is spelled correctly<br>• Check if Caps Lock is on<br>• Try resetting your password if you\'re sure the email is correct<br>• Contact support if you continue having issues';
                
                const form = document.getElementById('loginForm');
                form.appendChild(helpDiv);
            }, 1000);
        <?php endif; ?>

        // Progressive enhancement for password visibility toggle
        function addPasswordToggle() {
            const passwordGroup = passwordInput.parentElement;
            const toggleButton = document.createElement('button');
            toggleButton.type = 'button';
            toggleButton.innerHTML = '👁️';
            toggleButton.style.cssText = 'position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.2rem; color: var(--gray);';
            toggleButton.setAttribute('aria-label', 'Toggle password visibility');
            
            passwordGroup.style.position = 'relative';
            passwordInput.style.paddingRight = '3rem';
            passwordGroup.appendChild(toggleButton);
            
            toggleButton.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleButton.innerHTML = '🙈';
                    toggleButton.setAttribute('aria-label', 'Hide password');
                } else {
                    passwordInput.type = 'password';
                    toggleButton.innerHTML = '👁️';
                    toggleButton.setAttribute('aria-label', 'Show password');
                }
            });
        }

        // Add password toggle after DOM load
        document.addEventListener('DOMContentLoaded', addPasswordToggle);

        // Service Worker registration (for offline functionality)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js').then(function(registration) {
                    console.log('SW registered: ', registration);
                }).catch(function(registrationError) {
                    console.log('SW registration failed: ', registrationError);
                });
            });
        }

        // Handle network connectivity
        window.addEventListener('online', function() {
            const offlineMsg = document.getElementById('offline-message');
            if (offlineMsg) {
                offlineMsg.remove();
            }
        });

        window.addEventListener('offline', function() {
            const offlineMsg = document.createElement('div');
            offlineMsg.id = 'offline-message';
            offlineMsg.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; background: #dc3545; color: white; text-align: center; padding: 0.5rem; z-index: 9999; font-size: 0.9rem;';
            offlineMsg.textContent = '📡 You appear to be offline. Login functionality may be limited.';
            document.body.prepend(offlineMsg);
        });

        // Form auto-save (for email only)
        emailInput.addEventListener('input', function() {
            if (this.value && isValidEmail(this.value)) {
                localStorage.setItem('krua_thai_login_email', this.value);
            }
        });

        // Restore saved email on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedEmail = localStorage.getItem('krua_thai_login_email');
            if (savedEmail && !emailInput.value) {
                emailInput.value = savedEmail;
                emailInput.dispatchEvent(new Event('blur'));
                passwordInput.focus();
            }
        });

        // Clear saved email on successful login (would be handled by redirect)
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            // Email will be cleared on successful redirect
            // localStorage.removeItem('krua_thai_login_email');
        });

        // Handle browser back/forward buttons
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Page was loaded from cache, reset form state
                loginBtn.disabled = false;
                loginBtn.classList.remove('loading');
                loginText.textContent = 'Sign In';
                formSubmitted = false;
            }
        });

        // Real-time connection status
        function updateConnectionStatus() {
            const isOnline = navigator.onLine;
            if (!isOnline) {
                loginBtn.disabled = true;
                loginBtn.textContent = 'Offline - Check Connection';
            } else if (loginBtn.textContent === 'Offline - Check Connection') {
                loginBtn.disabled = false;
                loginBtn.textContent = 'Sign In';
            }
        }

        window.addEventListener('online', updateConnectionStatus);
        window.addEventListener('offline', updateConnectionStatus);

        // Initial connection check
        updateConnectionStatus();

        // Prevent autocomplete="off" override (some browsers ignore it)
        setTimeout(function() {
            emailInput.setAttribute('autocomplete', 'email');
            passwordInput.setAttribute('autocomplete', 'current-password');
        }, 50);
    </script>
</body>
</html>