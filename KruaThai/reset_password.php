<?php
/**
 * Krua Thai - Reset Password Page
 * File: reset_password.php
 * Description: Handle password reset from email link
 * Theme: Matching nutrition-tracking.php design system
 * Language: English (USA market)
 * Mobile-responsive and fully functional
 */

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
 
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$success_message = "";
$token = $_GET['token'] ?? '';
$user = null;

// Validate token
if (empty($token)) {
    $errors[] = "Invalid or missing password reset token.";
} else {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Check if token exists and is not expired
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email, password_reset_expires 
            FROM users 
            WHERE password_reset_token = ? 
            AND password_reset_expires > NOW()
            AND status IN ('active', 'pending_verification')
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $errors[] = "Invalid or expired password reset token. Please request a new one.";
        }
    } catch (Exception $e) {
        $errors[] = "System error. Please try again later.";
        error_log("Token validation error: " . $e->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $new_password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // Validate passwords
    if (empty($new_password)) {
        $errors[] = "Please enter a new password";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    } else {
        try {
            // Double-check token is still valid
            $check_stmt = $pdo->prepare("
                SELECT id FROM users 
                WHERE password_reset_token = ? 
                AND password_reset_expires > NOW()
                AND status IN ('active', 'pending_verification')
            ");
            $check_stmt->execute([$token]);
            
            if (!$check_stmt->fetch()) {
                $errors[] = "Token expired during processing. Please request a new reset link.";
            } else {
                // Update password and clear reset token
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update_stmt = $pdo->prepare("
                    UPDATE users 
                    SET password_hash = ?, 
                        password_reset_token = NULL, 
                        password_reset_expires = NULL,
                        status = 'active',
                        failed_login_attempts = 0,
                        locked_until = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                if ($update_stmt->execute([$password_hash, $user['id']])) {
                    $success_message = "Your password has been successfully reset! You can now log in with your new password.";
                    
                    // Log activity
                    if (function_exists('logActivity')) {
                        logActivity($user['id'], 'password_reset_completed', 'Password successfully reset via email token');
                    }
                    
                    // Send confirmation email
                    try {
                        $email_subject = "Password Changed Successfully - Krua Thai";
                        $email_body = "
                        <div style='font-family: \"BaticaSans\", Arial, sans-serif; max-width: 600px; margin: 0 auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(189, 147, 121, 0.15);'>
                            <div style='background: linear-gradient(135deg, #cf723a, #bd9379); padding: 3rem 2rem; text-align: center; color: white;'>
                                <div style='width: 80px; height: 80px; background: white; border-radius: 50%; margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; font-size: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2);'>🍜</div>
                                <h2 style='margin: 0; font-size: 2rem; font-weight: 700;'>Password Changed Successfully</h2>
                            </div>
                            <div style='padding: 3rem 2rem;'>
                                <p style='font-size: 1.1rem; margin-bottom: 1.5rem;'>Hi <strong>" . htmlspecialchars($user['first_name']) . "</strong> 👋</p>
                                <p>Your password has been successfully changed for your Krua Thai account.</p>
                                <div style='background: #ece8e1; padding: 1.5rem; border-radius: 12px; margin: 2rem 0;'>
                                    <p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "<br>
                                       <strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>
                                </div>
                                <p>If you did not make this change, please contact our support team immediately.</p>
                                <p style='margin-top: 2rem; color: #7f8c8d;'>Best regards,<br><strong>Krua Thai Team</strong></p>
                            </div>
                            <div style='background: #f8f6f0; padding: 2rem; text-align: center; color: #7f8c8d; font-size: 0.9rem; border-top: 1px solid #e9ecef;'>
                                <p>📞 <a href='tel:+1-555-KRUA-THAI' style='color: #cf723a;'>+1-555-KRUA-THAI</a> | 
                                ✉️ <a href='mailto:support@kruathai.com' style='color: #cf723a;'>support@kruathai.com</a></p>
                            </div>
                        </div>";
                        
                        if (function_exists('sendEmail')) {
                            sendEmail($user['email'], $email_subject, $email_body);
                        }
                    } catch (Exception $e) {
                        error_log("Failed to send password change confirmation email: " . $e->getMessage());
                    }
                    
                    // Clear user data for security
                    $user = null;
                } else {
                    $errors[] = "Failed to update password. Please try again.";
                }
            }
        } catch (Exception $e) {
            $errors[] = "System error. Please try again later.";
            error_log("Password update error: " . $e->getMessage());
        }
    }
}

$page_title = "Reset Password";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Krua Thai</title>
    <meta name="description" content="Complete your password reset for Krua Thai - Healthy Thai meal delivery">
    
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- BaticaSans Font Import -->
    <link rel="preconnect" href="https://ydpschool.com">
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

        /* Krua Thai Design System - Same as nutrition-tracking.php */
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
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
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
            background: linear-gradient(135deg, var(--cream) 0%, #f0ebe1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }

        /* Background Animation */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(189,147,121,0.1)"/><circle cx="80" cy="80" r="3" fill="rgba(173,184,157,0.1)"/><circle cx="40" cy="70" r="1" fill="rgba(207,114,58,0.1)"/></svg>');
            animation: float 20s linear infinite;
            pointer-events: none;
            z-index: -1;
        }

        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); }
            100% { transform: translateY(-100vh) rotate(360deg); }
        }

        /* Container */
        .auth-container {
            width: 100%;
            max-width: 500px;
            position: relative;
        }

        /* Main Card - Same style as nutrition-tracking.php */
        .main-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            overflow: hidden;
            position: relative;
            border: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        .main-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--curry), var(--brown), var(--sage));
        }

        /* Header */
        .card-header {
            padding: 3rem 2rem 2rem;
            border-bottom: 1px solid var(--border-light);
            background: linear-gradient(135deg, rgba(207, 114, 58, 0.05), rgba(189, 147, 121, 0.05));
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .card-header::after {
            content: "";
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(207,114,58,0.1) 0%, transparent 70%);
            animation: floating 6s ease-in-out infinite;
        }

        @keyframes floating {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        .auth-logo {
            width: 80px;
            height: 80px;
            background: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            font-size: 2rem;
            border: 3px solid rgba(207,114,58,0.3);
            position: relative;
            z-index: 1;
        }

        .card-title {
            font-size: 2rem;
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
            color: var(--brown);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .card-title i {
            color: var(--curry);
        }

        .card-subtitle {
            color: var(--text-gray);
            font-size: 1rem;
            font-family: 'BaticaSans', sans-serif;
            position: relative;
            z-index: 1;
        }

        /* Body */
        .card-body {
            padding: 2.5rem 2rem;
        }

        /* Alerts - Same style as nutrition-tracking.php */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
            font-weight: 500;
            border: 1px solid;
            position: relative;
            overflow: hidden;
            font-family: 'BaticaSans', sans-serif;
        }

        .alert::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: currentColor;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.1), rgba(39, 174, 96, 0.05));
            border-color: var(--success);
            color: var(--success);
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1), rgba(231, 76, 60, 0.05));
            border-color: var(--danger);
            color: var(--danger);
        }

        .alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .alert-content {
            flex: 1;
        }

        .error-list {
            margin: 0;
            padding-left: 1.5rem;
            list-style: none;
        }

        .error-list li {
            margin-bottom: 0.5rem;
            position: relative;
        }

        .error-list li::before {
            content: "•";
            color: var(--danger);
            position: absolute;
            left: -1rem;
        }

        /* User Info */
        .user-info {
            background: linear-gradient(135deg, rgba(173, 184, 157, 0.1), rgba(189, 147, 121, 0.1));
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
            text-align: center;
            border: 1px solid var(--sage);
            font-family: 'BaticaSans', sans-serif;
        }

        .user-info strong {
            color: var(--curry);
        }

        .user-info p {
            margin-bottom: 0.5rem;
        }

        .user-info p:last-child {
            margin-bottom: 0;
        }

        /* Form Styles */
        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            position: relative;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .required {
            color: var(--danger);
            font-size: 1.1rem;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            z-index: 2;
            font-size: 1.2rem;
            color: var(--text-gray);
            transition: var(--transition);
        }

        .form-input {
            width: 100%;
            padding: 1.25rem 1rem 1.25rem 3rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--white);
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 4px rgba(207, 114, 58, 0.1);
            transform: translateY(-1px);
        }

        .form-input:focus + .input-icon {
            color: var(--curry);
            transform: scale(1.1);
        }

        .form-input::placeholder {
            color: var(--text-gray);
        }

        /* Password Requirements */
        .password-requirements {
            background: var(--cream);
            padding: 1.25rem;
            border-radius: var(--radius-md);
            margin-top: 0.75rem;
            font-size: 0.9rem;
            border: 1px solid rgba(189, 147, 121, 0.2);
        }

        .password-requirements h4 {
            color: var(--brown);
            font-size: 1rem;
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .requirement:last-child {
            margin-bottom: 0;
        }

        .requirement.valid {
            color: var(--success);
        }

        .requirement.invalid {
            color: var(--danger);
        }

        .req-icon {
            font-size: 1rem;
            flex-shrink: 0;
            transition: var(--transition);
        }

        .requirement.valid .req-icon {
            animation: bounce 0.5s ease;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-5px); }
            60% { transform: translateY(-3px); }
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            padding: 1.25rem 2rem;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-soft);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-transform: none;
            font-family: 'BaticaSans', sans-serif;
            width: 100%;
            touch-action: manipulation;
            min-height: 48px;
        }

        .btn-primary::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--brown), var(--curry));
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.7;
        }

        /* Footer */
        .card-footer {
            padding: 1.5rem 2rem 2rem;
            background: #f8f6f0;
            border-top: 1px solid var(--border-light);
            text-align: center;
        }

        .auth-link {
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            border: 1px solid transparent;
            font-family: 'BaticaSans', sans-serif;
            touch-action: manipulation;
            min-height: 44px;
        }

        .auth-link:hover {
            background: var(--curry);
            color: var(--white);
            transform: translateY(-1px);
            border-color: var(--curry);
        }

        .link-icon {
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }

        .auth-link:hover .link-icon {
            transform: translateX(-2px);
        }

        .btn-link {
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            padding: 0.75rem 1rem;
            border: 1px solid var(--curry);
            border-radius: var(--radius-sm);
            transition: var(--transition);
            display: inline-block;
            font-family: 'BaticaSans', sans-serif;
            touch-action: manipulation;
            min-height: 44px;
        }

        .btn-link:hover {
            background: var(--curry);
            color: var(--white);
            transform: translateY(-1px);
        }

        /* Success State Animation */
        .success-state .alert-success {
            animation: slideInUp 0.5s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Mobile Responsive Design - Same as nutrition-tracking.php */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .auth-container {
                max-width: 100%;
            }

            .card-header {
                padding: 2rem 1.5rem 1.5rem;
            }

            .card-title {
                font-size: 1.5rem;
            }

            .card-body,
            .card-footer {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .card-header {
                padding: 1.5rem 1rem;
            }

            .card-body,
            .card-footer {
                padding: 1.5rem 1rem;
            }

            .auth-logo {
                width: 70px;
                height: 70px;
                font-size: 1.8rem;
            }

            .card-title {
                font-size: 1.3rem;
            }

            .card-subtitle {
                font-size: 0.9rem;
            }
        }

        /* Focus Management */
        .form-input:focus-visible {
            outline: 2px solid var(--curry);
            outline-offset: 2px;
        }

        /* Touch-friendly interactions */
        @media (hover: none) {
            .auth-link:hover,
            .btn-primary:hover {
                transform: none;
            }
        }

        /* Accessibility improvements */
        @media (prefers-reduced-motion: reduce) {
            .floating,
            .req-icon,
            .alert-icon {
                animation: none;
            }
            
            * {
                transition: none !important;
            }
        }

        /* High contrast mode */
        @media (prefers-contrast: high) {
            .main-card,
            .password-requirements {
                border: 2px solid var(--text-dark);
            }
        }

        /* Loading spinner */
        .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid var(--white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="<?php echo $success_message ? 'success-state' : ''; ?>">
    <div class="auth-container">
        <div class="main-card">
            <div class="card-header">
                <div class="auth-logo">🍜</div>
                <h1 class="card-title">
                    <i class="fas fa-lock"></i>
                    Reset Password
                </h1>
                <p class="card-subtitle">
                    Create your new secure password for Krua Thai
                </p>
            </div>

            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <div class="alert-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="alert-content">
                            <ul class="error-list">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if (!$user): ?>
                                <div style="margin-top: 1rem; text-align: center;">
                                    <a href="forgot_password.php" class="btn-link">Request New Reset Link</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <div class="alert-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="alert-content">
                            <p><?php echo htmlspecialchars($success_message); ?></p>
                            <div style="margin-top: 1rem;">
                                <a href="login.php" class="btn-link">
                                    <i class="fas fa-sign-in-alt"></i>
                                    Go to Login
                                </a>
                            </div>
                        </div>
                    </div>
                <?php elseif ($user): ?>
                    <div class="user-info">
                        <p>
                            <i class="fas fa-user" style="color: var(--curry); margin-right: 0.5rem;"></i>
                            Resetting password for: <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                        </p>
                        <p>
                            <i class="fas fa-envelope" style="color: var(--sage); margin-right: 0.5rem;"></i>
                            <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                        </p>
                    </div>

                    <form method="POST" class="auth-form" id="resetPasswordForm">
                        <div class="form-group">
                            <label for="password" class="form-label">
                                <i class="fas fa-key"></i>
                                <span>New Password</span>
                                <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="form-input"
                                    placeholder="Enter your new password"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                >
                                <span class="input-icon">
                                    <i class="fas fa-lock"></i>
                                </span>
                            </div>
                            
                            <div class="password-requirements">
                                <h4>
                                    <i class="fas fa-shield-alt" style="color: var(--curry); margin-right: 0.5rem;"></i>
                                    Password Requirements
                                </h4>
                                <div class="requirement" id="length-req">
                                    <span class="req-icon">❌</span>
                                    <span>At least 8 characters long</span>
                                </div>
                                <div class="requirement" id="uppercase-req">
                                    <span class="req-icon">❌</span>
                                    <span>Contains uppercase letter (A-Z)</span>
                                </div>
                                <div class="requirement" id="lowercase-req">
                                    <span class="req-icon">❌</span>
                                    <span>Contains lowercase letter (a-z)</span>
                                </div>
                                <div class="requirement" id="number-req">
                                    <span class="req-icon">❌</span>
                                    <span>Contains at least one number (0-9)</span>
                                </div>
                                <div class="requirement" id="match-req">
                                    <span class="req-icon">❌</span>
                                    <span>Passwords match</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
                                <i class="fas fa-lock"></i>
                                <span>Confirm New Password</span>
                                <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="form-input"
                                    placeholder="Confirm your new password"
                                    required
                                    autocomplete="new-password"
                                >
                                <span class="input-icon">
                                    <i class="fas fa-lock"></i>
                                </span>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary" id="submitBtn" disabled>
                            <span id="btnText">
                                <i class="fas fa-key"></i>
                                Reset Password
                            </span>
                            <span id="btnSpinner" style="display: none;">
                                <span class="spinner"></span>
                                Resetting Password...
                            </span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card-footer">
                <a href="login.php" class="auth-link">
                    <span class="link-icon">
                        <i class="fas fa-arrow-left"></i>
                    </span>
                    Back to Login
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🍜 Krua Thai - Reset Password page loaded successfully!');
            
            const passwordField = document.getElementById('password');
            const confirmField = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            
            // Requirements elements
            const lengthReq = document.getElementById('length-req');
            const uppercaseReq = document.getElementById('uppercase-req');
            const lowercaseReq = document.getElementById('lowercase-req');
            const numberReq = document.getElementById('number-req');
            const matchReq = document.getElementById('match-req');

            // Initialize page animations
            initializePageAnimations();

            function validatePassword() {
                const password = passwordField ? passwordField.value : '';
                const confirm = confirmField ? confirmField.value : '';
                
                // Check length (at least 8 characters)
                const hasLength = password.length >= 8;
                if (lengthReq) updateRequirement(lengthReq, hasLength);
                
                // Check uppercase
                const hasUppercase = /[A-Z]/.test(password);
                if (uppercaseReq) updateRequirement(uppercaseReq, hasUppercase);
                
                // Check lowercase
                const hasLowercase = /[a-z]/.test(password);
                if (lowercaseReq) updateRequirement(lowercaseReq, hasLowercase);
                
                // Check number
                const hasNumber = /[0-9]/.test(password);
                if (numberReq) updateRequirement(numberReq, hasNumber);
                
                // Check match
                const hasMatch = password && confirm && password === confirm;
                if (matchReq) updateRequirement(matchReq, hasMatch);
                
                // Enable/disable submit button
                const allValid = hasLength && hasUppercase && hasLowercase && hasNumber && hasMatch;
                if (submitBtn) {
                    submitBtn.disabled = !allValid;
                    
                    if (allValid) {
                        submitBtn.style.opacity = '1';
                        submitBtn.style.cursor = 'pointer';
                    } else {
                        submitBtn.style.opacity = '0.6';
                        submitBtn.style.cursor = 'not-allowed';
                    }
                }
                
                return allValid;
            }

            function updateRequirement(element, isValid) {
                const icon = element.querySelector('.req-icon');
                
                if (isValid) {
                    element.classList.add('valid');
                    element.classList.remove('invalid');
                    if (icon) icon.textContent = '✅';
                } else {
                    element.classList.add('invalid');
                    element.classList.remove('valid');
                    if (icon) icon.textContent = '❌';
                }
            }

            // Event listeners
            if (passwordField && confirmField) {
                passwordField.addEventListener('input', validatePassword);
                confirmField.addEventListener('input', validatePassword);
                
                // Real-time validation feedback
                passwordField.addEventListener('input', function() {
                    const password = this.value;
                    
                    // Visual feedback for password field
                    if (password.length > 0) {
                        const strength = calculatePasswordStrength(password);
                        updatePasswordFieldStyle(this, strength);
                    } else {
                        this.style.borderColor = 'var(--border-light)';
                        this.style.boxShadow = '';
                    }
                });
                
                confirmField.addEventListener('input', function() {
                    const password = passwordField.value;
                    const confirm = this.value;
                    
                    // Visual feedback for confirm field
                    if (confirm.length > 0) {
                        if (password === confirm) {
                            this.style.borderColor = 'var(--success)';
                            this.style.boxShadow = '0 0 0 4px rgba(39, 174, 96, 0.1)';
                        } else {
                            this.style.borderColor = 'var(--danger)';
                            this.style.boxShadow = '0 0 0 4px rgba(231, 76, 60, 0.1)';
                        }
                    } else {
                        this.style.borderColor = 'var(--border-light)';
                        this.style.boxShadow = '';
                    }
                });
            }

            function calculatePasswordStrength(password) {
                let score = 0;
                if (password.length >= 8) score++;
                if (/[A-Z]/.test(password)) score++;
                if (/[a-z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^A-Za-z0-9]/.test(password)) score++;
                return score;
            }

            function updatePasswordFieldStyle(field, strength) {
                if (strength <= 2) {
                    field.style.borderColor = 'var(--danger)';
                    field.style.boxShadow = '0 0 0 4px rgba(231, 76, 60, 0.1)';
                } else if (strength <= 3) {
                    field.style.borderColor = 'var(--warning)';
                    field.style.boxShadow = '0 0 0 4px rgba(243, 156, 18, 0.1)';
                } else {
                    field.style.borderColor = 'var(--success)';
                    field.style.boxShadow = '0 0 0 4px rgba(39, 174, 96, 0.1)';
                }
            }

            // Auto-focus password field
            if (passwordField) {
                setTimeout(() => passwordField.focus(), 100);
            }

            // Form submission handling
            const form = document.getElementById('resetPasswordForm');
            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    if (submitBtn.disabled || !validatePassword()) {
                        e.preventDefault();
                        showNotification('Please complete all password requirements before submitting.', 'warning');
                        return false;
                    }
                    
                    // Show loading state
                    submitBtn.disabled = true;
                    btnText.style.display = 'none';
                    btnSpinner.style.display = 'flex';
                    
                    // Restore button after 15 seconds (fallback)
                    setTimeout(() => {
                        if (btnText && btnSpinner) {
                            submitBtn.disabled = false;
                            btnText.style.display = 'flex';
                            btnSpinner.style.display = 'none';
                        }
                    }, 15000);
                });
            }

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.ctrlKey && form && !submitBtn.disabled) {
                    e.preventDefault();
                    form.submit();
                }
                
                if (e.key === 'Escape') {
                    window.location.href = 'login.php';
                }
            });

            // Initialize page animations
            function initializePageAnimations() {
                // Animate main card on load
                const mainCard = document.querySelector('.main-card');
                if (mainCard) {
                    mainCard.style.opacity = '0';
                    mainCard.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        mainCard.style.transition = 'all 0.6s ease';
                        mainCard.style.opacity = '1';
                        mainCard.style.transform = 'translateY(0)';
                    }, 100);
                }

                // Animate requirements on load
                const requirements = document.querySelectorAll('.requirement');
                requirements.forEach((req, index) => {
                    req.style.opacity = '0';
                    req.style.transform = 'translateX(-10px)';
                    
                    setTimeout(() => {
                        req.style.transition = 'all 0.3s ease';
                        req.style.opacity = '1';
                        req.style.transform = 'translateX(0)';
                    }, 300 + (index * 50));
                });
            }

            // Show notification function
            function showNotification(message, type = 'info') {
                const colors = {
                    success: 'var(--success)',
                    error: 'var(--danger)',
                    info: 'var(--info)',
                    warning: 'var(--warning)'
                };
                
                const notification = document.createElement('div');
                notification.style.cssText = `
                    position: fixed;
                    top: 2rem;
                    right: 2rem;
                    background: ${colors[type]};
                    color: white;
                    padding: 1rem 1.5rem;
                    border-radius: var(--radius-md);
                    font-family: 'BaticaSans', sans-serif;
                    font-weight: 600;
                    z-index: 10000;
                    box-shadow: var(--shadow-medium);
                    transform: translateX(100%);
                    transition: transform 0.3s ease;
                    max-width: 300px;
                `;
                notification.textContent = message;
                
                document.body.appendChild(notification);
                
                // Animate in
                setTimeout(() => {
                    notification.style.transform = 'translateX(0)';
                }, 100);
                
                // Remove after delay
                setTimeout(() => {
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 4000);
            }

            // Touch feedback for mobile
            const touchElements = document.querySelectorAll('.btn-primary, .auth-link, .btn-link');
            touchElements.forEach(element => {
                element.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                });
                
                element.addEventListener('touchend', function() {
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });

            // Password visibility toggle functionality
            function addPasswordToggle() {
                const passwordInputs = document.querySelectorAll('input[type="password"]');
                
                passwordInputs.forEach(input => {
                    const wrapper = input.closest('.input-wrapper');
                    if (wrapper) {
                        const toggleBtn = document.createElement('button');
                        toggleBtn.type = 'button';
                        toggleBtn.style.cssText = `
                            position: absolute;
                            right: 1rem;
                            z-index: 3;
                            background: none;
                            border: none;
                            color: var(--text-gray);
                            cursor: pointer;
                            font-size: 1rem;
                            padding: 0.25rem;
                            transition: var(--transition);
                        `;
                        toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
                        
                        toggleBtn.addEventListener('click', function() {
                            const isPassword = input.type === 'password';
                            input.type = isPassword ? 'text' : 'password';
                            this.innerHTML = isPassword ? 
                                '<i class="fas fa-eye-slash"></i>' : 
                                '<i class="fas fa-eye"></i>';
                            this.style.color = isPassword ? 'var(--curry)' : 'var(--text-gray)';
                        });
                        
                        wrapper.appendChild(toggleBtn);
                        
                        // Adjust padding to make room for toggle
                        input.style.paddingRight = '3.5rem';
                    }
                });
            }

            // Add password visibility toggles
            addPasswordToggle();

            console.log('🔐 All password reset functionality initialized for mobile-friendly experience');
        });

        // Global error handler
        window.addEventListener('error', function(e) {
            console.error('Page error:', e.error);
            
            // Show user-friendly error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error';
            errorDiv.innerHTML = `
                <div class="alert-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-content">
                    <p>An unexpected error occurred. Please refresh the page and try again.</p>
                </div>
            `;
            
            const cardBody = document.querySelector('.card-body');
            if (cardBody) {
                cardBody.insertBefore(errorDiv, cardBody.firstChild);
            }
        });

        // Service Worker for offline support (optional)
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('SW registered: ', registration);
                    })
                    .catch(function(registrationError) {
                        console.log('SW registration failed: ', registrationError);
                    });
            });
        }
    </script>
</body>
</html>