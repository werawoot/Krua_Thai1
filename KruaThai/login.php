<?php
/**
 * Somdul Table - User Login Page with OAuth Support
 * File: login.php
 * Description: Secure login with Facebook/Google OAuth, brute-force protection and session management
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

// OAuth Configuration - Replace with your actual credentials

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

// Handle Google OAuth Login
if (isset($_POST['google_login']) && $_POST['google_login'] === '1') {
    $google_id_token = $_POST['google_id_token'] ?? '';
    
    error_log("Google login attempt - ID Token: " . substr($google_id_token, 0, 20) . "...");
    
    if ($google_id_token) {
        // Verify Google ID Token
        $verify_url = "https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=" . urlencode($google_id_token);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verify_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("Google Token verification - HTTP Code: " . $http_code . ", Response: " . $response);
        
        if ($http_code === 200) {
            $google_data = json_decode($response, true);
            
            if ($google_data && isset($google_data['sub']) && isset($google_data['aud']) && $google_data['aud'] === $google_client_id) {
                $google_user_id = $google_data['sub'];
                
                // Check if user exists by Google ID
                $stmt = $db->prepare("SELECT * FROM users WHERE google_id = :google_id");
                $stmt->bindParam(':google_id', $google_user_id);
                $stmt->execute();
                $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                error_log("Google user existence check - User ID: " . $google_user_id . ", Exists: " . ($existing_user ? 'YES' : 'NO'));
                
                if ($existing_user) {
                    // User exists, log them in
                    $_SESSION['user_id'] = $existing_user['id'];
                    $_SESSION['user_email'] = $existing_user['email'];
                    $_SESSION['user_name'] = $existing_user['first_name'] . ' ' . $existing_user['last_name'];
                    $_SESSION['user_role'] = $existing_user['role'];
                    $_SESSION['login_method'] = 'google';
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :user_id");
                    $stmt->bindParam(':user_id', $existing_user['id']);
                    $stmt->execute();
                    
                    // Log successful login
                    logActivity('login_success', $existing_user['id'], getRealIPAddress(), [
                        'method' => 'google',
                        'google_id' => $google_user_id,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);
                    
                    // Check if profile is complete
                    $is_profile_complete = !empty($existing_user['phone']) && 
                                         !empty($existing_user['delivery_address']) && 
                                         !empty($existing_user['zip_code']) &&
                                         !empty($existing_user['email']) &&
                                         !str_contains($existing_user['email'], '@temp.somdultable.com');
                    
                    if (!$is_profile_complete) {
                        header('Location: complete-profile.php?returning=1&google=1');
                        exit();
                    } else {
                        // Redirect based on role or intended destination
                        $redirect_url = 'dashboard.php';
                        if (isset($_GET['redirect'])) {
                            $redirect_url = sanitizeInput($_GET['redirect']);
                            if (!preg_match('/^[a-zA-Z0-9\/_\-\.]+\.php(\?.*)?$/', $redirect_url)) {
                                $redirect_url = 'dashboard.php';
                            }
                        } elseif ($existing_user['role'] === 'admin') {
                            $redirect_url = 'admin/dashboard.php';
                        }
                        
                        $_SESSION['flash_message'] = "Welcome back! Signed in with Google.";
                        $_SESSION['flash_type'] = 'success';
                        header("Location: $redirect_url");
                        exit();
                    }
                } else {
                    // User doesn't exist, redirect to register with pre-filled Google data
                    $_SESSION['google_signup_data'] = [
                        'google_id' => $google_user_id,
                        'first_name' => $google_data['given_name'] ?? '',
                        'last_name' => $google_data['family_name'] ?? '',
                        'email' => $google_data['email'] ?? ''
                    ];
                    $_SESSION['flash_message'] = "No account found. Please create an account with Google.";
                    $_SESSION['flash_type'] = 'info';
                    header('Location: register.php');
                    exit();
                }
            } else {
                $errors[] = "Google verification failed. Please try again.";
            }
        } else {
            $errors[] = "Unable to verify Google account. Please try again.";
        }
    } else {
        $errors[] = "Google authentication data is missing. Please try again.";
    }
}

// Handle Facebook OAuth Login
if (isset($_POST['facebook_login']) && $_POST['facebook_login'] === '1') {
    $facebook_access_token = $_POST['facebook_access_token'] ?? '';
    $facebook_user_id = $_POST['facebook_user_id'] ?? '';
    
    error_log("Facebook login attempt - Access Token: " . substr($facebook_access_token, 0, 20) . "..., User ID: " . $facebook_user_id);
    
    if ($facebook_access_token && $facebook_user_id) {
        // Verify Facebook access token
        $verify_url = "https://graph.facebook.com/me?access_token=" . urlencode($facebook_access_token) . "&fields=id,first_name,last_name,name";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verify_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("Facebook API response - HTTP Code: " . $http_code . ", Response: " . $response);
        
        if ($http_code === 200) {
            $facebook_data = json_decode($response, true);
            
            if ($facebook_data && isset($facebook_data['id']) && $facebook_data['id'] === $facebook_user_id) {
                // Check if user exists by Facebook ID
                $stmt = $db->prepare("SELECT * FROM users WHERE facebook_id = :facebook_id");
                $stmt->bindParam(':facebook_id', $facebook_user_id);
                $stmt->execute();
                $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                error_log("Facebook user existence check - User ID: " . $facebook_user_id . ", Exists: " . ($existing_user ? 'YES' : 'NO'));
                
                if ($existing_user) {
                    // User exists, log them in
                    $_SESSION['user_id'] = $existing_user['id'];
                    $_SESSION['user_email'] = $existing_user['email'];
                    $_SESSION['user_name'] = $existing_user['first_name'] . ' ' . $existing_user['last_name'];
                    $_SESSION['user_role'] = $existing_user['role'];
                    $_SESSION['login_method'] = 'facebook';
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :user_id");
                    $stmt->bindParam(':user_id', $existing_user['id']);
                    $stmt->execute();
                    
                    // Log successful login
                    logActivity('login_success', $existing_user['id'], getRealIPAddress(), [
                        'method' => 'facebook',
                        'facebook_id' => $facebook_user_id,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);
                    
                    // Check if profile is complete
                    $is_profile_complete = !empty($existing_user['phone']) && 
                                         !empty($existing_user['delivery_address']) && 
                                         !empty($existing_user['zip_code']) &&
                                         !empty($existing_user['email']) &&
                                         !str_contains($existing_user['email'], '@temp.somdultable.com');
                    
                    if (!$is_profile_complete) {
                        header('Location: complete-profile.php?returning=1&facebook=1');
                        exit();
                    } else {
                        // Redirect based on role or intended destination
                        $redirect_url = 'dashboard.php';
                        if (isset($_GET['redirect'])) {
                            $redirect_url = sanitizeInput($_GET['redirect']);
                            if (!preg_match('/^[a-zA-Z0-9\/_\-\.]+\.php(\?.*)?$/', $redirect_url)) {
                                $redirect_url = 'dashboard.php';
                            }
                        } elseif ($existing_user['role'] === 'admin') {
                            $redirect_url = 'admin/dashboard.php';
                        }
                        
                        $_SESSION['flash_message'] = "Welcome back! Signed in with Facebook.";
                        $_SESSION['flash_type'] = 'success';
                        header("Location: $redirect_url");
                        exit();
                    }
                } else {
                    // User doesn't exist, redirect to register
                    $_SESSION['facebook_signup_data'] = [
                        'facebook_id' => $facebook_user_id,
                        'first_name' => $facebook_data['first_name'] ?? '',
                        'last_name' => $facebook_data['last_name'] ?? ''
                    ];
                    $_SESSION['flash_message'] = "No account found. Please create an account with Facebook.";
                    $_SESSION['flash_type'] = 'info';
                    header('Location: register.php');
                    exit();
                }
            } else {
                $errors[] = "Facebook verification failed. Please try again.";
            }
        } else {
            $errors[] = "Unable to verify Facebook account. Please try again.";
        }
    } else {
        $errors[] = "Facebook authentication data is missing. Please try again.";
    }
}

// Handle regular form submission (EXISTING CODE - unchanged)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['facebook_login']) && !isset($_POST['google_login'])) {
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
            $_SESSION['login_method'] = 'email';
            $_SESSION['login_time'] = time();
            
            // Handle remember me functionality
            if ($remember_me) {
                $remember_token = generateToken(32);
                $expires = time() + (30 * 24 * 60 * 60); // 30 days
                
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
                'method' => 'email',
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
                $redirect_url = 'kitchen/kitchen_dashboard.php';
            } elseif ($user->role === 'rider') {
                $redirect_url = 'rider/rider-dashboard.php';
            }
            
            // Special handling for just verified users
            if (isset($_SESSION['just_verified'])) {
                unset($_SESSION['just_verified']);
                $_SESSION['flash_message'] = "Welcome to Somdul Table! Your account is now active.";
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
                'method' => 'email',
                'email' => $email,
                'reason' => $auth_result['message'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        }
    }
}

// Handle verification email resend (EXISTING CODE - unchanged)
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
    <title>Sign In - Somdul Table</title>
    <meta name="description" content="Sign in to your Somdul Table account to order healthy Thai meals and manage your subscriptions">
    <meta name="keywords" content="login, sign in, Somdul Table, Thai food delivery">
    
    <!-- Google Sign-In -->
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    
    <!-- Facebook SDK -->
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v18.0&appId=<?php echo $facebook_app_id; ?>&autoLogAppEvents=1"></script>
    
    <!-- BaticaSans Font Import -->
    <link rel="preconnect" href="https://ydpschool.com">
    <style>
        /* [All existing CSS remains exactly the same - just copying everything] */
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-weight: 400;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
        }

        .login-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-medium);
            border: 1px solid rgba(255, 255, 255, 0.8);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%);
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
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
            cursor: pointer;
        }

        .logo:hover {
            transform: translateY(-2px);
            filter: brightness(1.1);
        }

        .logo-text {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            font-family: 'BaticaSans', sans-serif;
        }

        .login-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 400;
            position: relative;
            z-index: 1;
            font-family: 'BaticaSans', sans-serif;
        }

        .login-form {
            padding: 2.5rem 2rem;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            text-align: center;
            font-family: 'BaticaSans', sans-serif;
        }

        .form-subtitle {
            color: var(--text-gray);
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
            font-family: 'BaticaSans', sans-serif;
        }

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

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .required {
            color: var(--danger);
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 1rem 1.2rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--white);
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        input:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 3px rgba(207, 114, 58, 0.1);
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

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin: 1.5rem 0;
            padding: 1rem;
            background: rgba(189, 147, 121, 0.05);
            border-radius: var(--radius-md);
            transition: var(--transition);
        }

        .remember-me:hover {
            background: rgba(189, 147, 121, 0.08);
        }

        .remember-me input[type="checkbox"] {
            width: auto;
            margin: 0;
            transform: scale(1.2);
            accent-color: var(--curry);
        }

        .remember-me label {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-gray);
            cursor: pointer;
            font-family: 'BaticaSans', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            padding: 1.2rem 2rem;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.1rem;
            width: 100%;
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
            margin-top: 1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--brown), var(--curry));
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-primary:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.7;
        }

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            padding: 0.8rem 1.5rem;
            border: 2px solid var(--curry);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: var(--transition);
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: var(--shadow-soft);
        }

        .forgot-password {
            text-align: center;
            margin: 1.5rem 0;
        }

        .forgot-password a {
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            border-bottom: 1px solid transparent;
            transition: border-bottom-color 0.3s;
            font-family: 'BaticaSans', sans-serif;
        }

        .forgot-password a:hover {
            border-bottom-color: var(--curry);
        }

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
            font-family: 'BaticaSans', sans-serif;
        }

        .verification-section p {
            color: #856404;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
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
            transition: var(--transition);
            font-family: 'BaticaSans', sans-serif;
        }

        .verification-form button:hover {
            background: linear-gradient(45deg, #e0a800, var(--warning));
            transform: translateY(-1px);
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-light);
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .login-footer a {
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px solid transparent;
            transition: border-bottom-color 0.3s;
        }

        .login-footer a:hover {
            border-bottom-color: var(--curry);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 2rem 0;
            color: var(--text-gray);
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-light);
        }

        .divider span {
            padding: 0 1rem;
        }

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

        .back-to-home {
            position: fixed;
            top: 2rem;
            left: 2rem;
            z-index: 1000;
        }

        .back-to-home a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            background: var(--white);
            padding: 0.8rem 1.2rem;
            border-radius: 50px;
            box-shadow: var(--shadow-soft);
            transition: var(--transition);
            border: 2px solid var(--curry);
        }

        .back-to-home a:hover {
            background: var(--curry);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        /* Social Login Styles */
        .social-login-section {
            margin: 2rem 0 1.5rem;
        }

        .social-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
        }

        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            width: 100%;
            padding: 1rem 1.5rem;
            border: 2px solid;
            border-radius: 12px;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            position: relative;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .social-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .social-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .social-icon {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
        }

        .facebook-btn {
            background: linear-gradient(135deg, #1877f2 0%, #4267B2 100%);
            border-color: #1877f2;
            color: white;
        }

        .google-btn {
            background: white;
            border-color: #dadce0;
            color: #3c4043;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,.30), 0 1px 3px 1px rgba(60,64,67,.15);
            font-weight: 500;
        }

        .google-btn:hover {
            background: #f9f9f9;
            border-color: #dadce0;
            color: #3c4043;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,.30), 0 2px 6px 2px rgba(60,64,67,.15);
            transform: translateY(-1px);
        }

        .google-btn .social-icon {
            background: none;
            border-radius: 0;
            padding: 0;
            color: transparent;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%234285F4' d='M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z'/%3E%3Cpath fill='%2334A853' d='M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z'/%3E%3Cpath fill='%23FBBC04' d='M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z'/%3E%3Cpath fill='%23EA4335' d='M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z'/%3E%3C/svg%3E");
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

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

        @media (max-width: 768px) {
            .back-to-home {
                top: 1rem;
                left: 1rem;
            }
            
            .back-to-home a {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
        }

        input:focus-visible,
        button:focus-visible,
        a:focus-visible {
            outline: 2px solid var(--curry);
            outline-offset: 2px;
        }

        @media (prefers-contrast: high) {
            .login-container {
                border: 2px solid var(--text-dark);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <!-- Back to Home Link -->
    <div class="back-to-home">
        <a href="home2.php">
            <span>←</span>
            <span>Back to Home</span>
        </a>
    </div>

    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <a href="home2.php" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Somdul Table" style="height: 50px; width: auto; border-radius: 50%;">
                <span class="logo-text">Somdul Table</span>
            </a>
            <p class="login-subtitle">Welcome back to authentic Thai cuisine</p>
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

            <!-- Social Login Section -->
            <div class="social-login-section">
                <div class="social-buttons">
                    <button type="button" class="social-btn facebook-btn" id="facebookLoginBtn" onclick="loginWithFacebook()">
                        <svg class="social-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                        <span>Continue with Facebook</span>
                    </button>

                    <!-- Google Sign-In Button -->
                    <div id="g_id_onload"
                         data-client_id="<?php echo $google_client_id; ?>"
                         data-callback="handleGoogleCredentialResponse"
                         data-auto_prompt="false">
                    </div>
                    
                    <button type="button" class="social-btn google-btn" id="googleLoginBtn" onclick="loginWithGoogle()">
                        <svg class="social-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        <span>Continue with Google</span>
                    </button>
                </div>
            </div>

            <div class="divider">
                <span>Or continue with email</span>
            </div>

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
                    <small id="email_help" style="color: var(--text-gray); font-size: 0.85rem; font-family: 'BaticaSans', sans-serif;">The email address you used to register</small>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           autocomplete="current-password"
                           aria-describedby="password_help">
                    <small id="password_help" style="color: var(--text-gray); font-size: 0.85rem; font-family: 'BaticaSans', sans-serif;">Your account password</small>
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
                <span>New to Somdul Table?</span>
            </div>

            <div style="text-align: center;">
                <a href="register.php" class="btn-secondary">Create New Account</a>
            </div>

            <div class="login-footer">
                <p>By signing in, you agree to our <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a></p>
                <p style="margin-top: 1rem;">
                    <a href="home2.php">← Back to Home</a> | 
                    <a href="help.php">Need Help?</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Hidden forms for OAuth login -->
    <form id="facebookLoginForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="facebook_login" value="1">
        <input type="hidden" name="facebook_access_token" id="facebook_access_token">
        <input type="hidden" name="facebook_user_id" id="facebook_user_id">
    </form>

    <form id="googleLoginForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="google_login" value="1">
        <input type="hidden" name="google_id_token" id="google_id_token">
    </form>

    <script>
        // Initialize Facebook SDK
        window.fbAsyncInit = function() {
            FB.init({
                appId: '<?php echo $facebook_app_id; ?>',
                cookie: true,
                xfbml: true,
                version: 'v18.0'
            });
            
            document.getElementById('facebookLoginBtn').disabled = false;
        };

        // Google Sign-In Callback
        function handleGoogleCredentialResponse(response) {
            console.log("Google ID Token received:", response.credential.substring(0, 20) + "...");
            
            // Fill hidden form and submit
            document.getElementById('google_id_token').value = response.credential;
            document.getElementById('googleLoginForm').submit();
        }

        // Google login function
        function loginWithGoogle() {
            const googleBtn = document.getElementById('googleLoginBtn');
            
            // Show loading state
            googleBtn.disabled = true;
            googleBtn.innerHTML = '<svg class="social-icon" style="animation: spin 1s linear infinite;" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"/><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg><span>Connecting to Google...</span>';
            
            // Trigger Google Sign-In
            google.accounts.id.prompt((notification) => {
                if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
                    google.accounts.id.prompt();
                }
                
                // Reset button if user cancels
                setTimeout(() => {
                    resetGoogleButton();
                }, 3000);
            });
        }

        function resetGoogleButton() {
            const googleBtn = document.getElementById('googleLoginBtn');
            googleBtn.disabled = false;
            googleBtn.innerHTML = '<svg class="social-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg><span>Continue with Google</span>';
        }

        // Facebook login function
        function loginWithFacebook() {
            const facebookBtn = document.getElementById('facebookLoginBtn');
            
            // Show loading state
            facebookBtn.disabled = true;
            facebookBtn.innerHTML = '<svg class="social-icon" style="animation: spin 1s linear infinite;" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"/><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg><span>Connecting to Facebook...</span>';
            
            FB.login(function(response) {
                if (response.authResponse) {
                    const accessToken = response.authResponse.accessToken;
                    const userID = response.authResponse.userID;
                    
                    FB.api('/me', {fields: 'id,first_name,last_name,name'}, function(userInfo) {
                        console.log('Facebook user info:', userInfo);
                        
                        if (userInfo.id) {
                            document.getElementById('facebook_access_token').value = accessToken;
                            document.getElementById('facebook_user_id').value = userID;
                            document.getElementById('facebookLoginForm').submit();
                        } else {
                            alert('Unable to get your Facebook information. Please try again.');
                            resetFacebookButton();
                        }
                    });
                } else {
                    console.log('Facebook login cancelled');
                    resetFacebookButton();
                }
            }, {scope: 'public_profile'});
        }

        function resetFacebookButton() {
            const facebookBtn = document.getElementById('facebookLoginBtn');
            facebookBtn.disabled = false;
            facebookBtn.innerHTML = '<svg class="social-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg><span>Continue with Facebook</span>';
        }

        // [All existing JavaScript code for form validation remains the same]
        
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

            loginBtn.disabled = true;
            loginBtn.classList.add('loading');
            loginText.textContent = 'Signing in...';
            
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
            if (e.key === 'Enter' && document.activeElement === emailInput) {
                e.preventDefault();
                passwordInput.focus();
            }
            
            if (e.key === 'Escape') {
                document.activeElement.blur();
            }
        });

        // Auto-focus management
        document.addEventListener('DOMContentLoaded', function() {
            // Disable social buttons until SDKs load
            document.getElementById('facebookLoginBtn').disabled = true;
            document.getElementById('googleLoginBtn').disabled = false;
            
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

        // Form auto-save (for email only)
        emailInput.addEventListener('input', function() {
            if (this.value && isValidEmail(this.value)) {
                localStorage.setItem('somdul_table_login_email', this.value);
            }
        });

        // Restore saved email on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedEmail = localStorage.getItem('somdul_table_login_email');
            if (savedEmail && !emailInput.value) {
                emailInput.value = savedEmail;
                emailInput.dispatchEvent(new Event('blur'));
                passwordInput.focus();
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
        updateConnectionStatus();

        // Prevent form double submission
        let formSubmitted = false;
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (formSubmitted) {
                e.preventDefault();
                return false;
            }
            formSubmitted = true;
        });
    </script>
</body>
</html>