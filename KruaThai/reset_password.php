<?php
/**
 * Krua Thai - Reset Password Page
 * File: reset_password.php
 * Description: Handle password reset from email link
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($new_password)) {
        $errors[] = "Please enter a new password";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    } else {
        try {
            // Update password and clear reset token
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update_stmt = $pdo->prepare("
                UPDATE users 
                SET password_hash = ?, 
                    password_reset_token = NULL, 
                    password_reset_expires = NULL,
                    status = 'active'
                WHERE id = ?
            ");
            
            if ($update_stmt->execute([$password_hash, $user['id']])) {
                $success_message = "Your password has been successfully reset! You can now log in with your new password.";
                
                // Log activity
                if (function_exists('logActivity')) {
                    logActivity($user['id'], 'password_reset_completed', 'Password successfully reset');
                }
                
                // Clear user data for security
                $user = null;
            } else {
                $errors[] = "Failed to update password. Please try again.";
            }
        } catch (Exception $e) {
            $errors[] = "System error. Please try again later.";
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
    
    <style>
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
            --danger: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: linear-gradient(135deg, var(--cream) 0%, #f0ebe1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .auth-container {
            width: 100%;
            max-width: 450px;
            position: relative;
        }

        .auth-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-medium);
            overflow: hidden;
            border: 1px solid rgba(189, 147, 121, 0.1);
        }

        .auth-header {
            background: linear-gradient(135deg, var(--curry) 0%, var(--brown) 100%);
            color: var(--white);
            padding: 3rem 2rem 2rem;
            text-align: center;
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
        }

        .auth-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .auth-header p {
            font-size: 1rem;
            opacity: 0.9;
            line-height: 1.5;
        }

        .auth-body {
            padding: 2.5rem 2rem;
        }

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.25rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
            font-weight: 500;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border-color: var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }

        .alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
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
            opacity: 0.7;
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
        }

        .form-input:focus {
            outline: none;
            border-color: var(--curry);
            box-shadow: 0 0 0 4px rgba(207, 114, 58, 0.1);
        }

        .password-requirements {
            background: var(--cream);
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
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
            width: 100%;
        }

        .btn-primary:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--brown), var(--curry));
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-primary:disabled {
            background: var(--text-gray);
            cursor: not-allowed;
            opacity: 0.7;
        }

        .auth-footer {
            padding: 1.5rem 2rem 2rem;
            background: #f8f6f0;
            border-top: 1px solid var(--border-light);
            text-align: center;
        }

        .auth-link {
            color: var(--curry);
            text-decoration: none;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            display: inline-block;
        }

        .auth-link:hover {
            background: var(--curry);
            color: var(--white);
        }

        .user-info {
            background: var(--cream);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
            text-align: center;
        }

        .user-info strong {
            color: var(--curry);
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .auth-header {
                padding: 2rem 1.5rem 1.5rem;
            }

            .auth-header h1 {
                font-size: 1.5rem;
            }

            .auth-body,
            .auth-footer {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">🍜</div>
                <h1>Reset Password</h1>
                <p>Create your new secure password</p>
            </div>

            <div class="auth-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <div class="alert-icon">⚠️</div>
                        <div class="alert-content">
                            <ul class="error-list">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <div class="alert-icon">✅</div>
                        <div class="alert-content">
                            <p><?php echo htmlspecialchars($success_message); ?></p>
                            <div style="margin-top: 1rem;">
                                <a href="login.php" class="auth-link">Go to Login</a>
                            </div>
                        </div>
                    </div>
                <?php elseif ($user): ?>
                    <div class="user-info">
                        <p>Resetting password for: <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></p>
                        <p><strong><?php echo htmlspecialchars($user['email']); ?></strong></p>
                    </div>

                    <form method="POST" class="auth-form" id="resetPasswordForm">
                        <div class="form-group">
                            <label for="password" class="form-label">
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
                                <span class="input-icon">🔒</span>
                            </div>
                            
                            <div class="password-requirements">
                                <div class="requirement" id="length-req">
                                    <span class="req-icon">❌</span>
                                    <span>At least 8 characters</span>
                                </div>
                                <div class="requirement" id="match-req">
                                    <span class="req-icon">❌</span>
                                    <span>Passwords match</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">
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
                                <span class="input-icon">🔒</span>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary" id="submitBtn" disabled>
                            Reset Password
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="auth-footer">
                <a href="login.php" class="auth-link">← Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const confirmField = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            const lengthReq = document.getElementById('length-req');
            const matchReq = document.getElementById('match-req');

            function validatePassword() {
                const password = passwordField.value;
                const confirm = confirmField.value;
                
                // Check length
                const hasLength = password.length >= 8;
                updateRequirement(lengthReq, hasLength);
                
                // Check match
                const hasMatch = password && confirm && password === confirm;
                updateRequirement(matchReq, hasMatch);
                
                // Enable/disable submit button
                submitBtn.disabled = !(hasLength && hasMatch);
            }

            function updateRequirement(element, isValid) {
                const icon = element.querySelector('.req-icon');
                
                if (isValid) {
                    element.classList.add('valid');
                    element.classList.remove('invalid');
                    icon.textContent = '✅';
                } else {
                    element.classList.add('invalid');
                    element.classList.remove('valid');
                    icon.textContent = '❌';
                }
            }

            if (passwordField && confirmField) {
                passwordField.addEventListener('input', validatePassword);
                confirmField.addEventListener('input', validatePassword);
            }

            // Auto-focus password field
            if (passwordField) {
                setTimeout(() => passwordField.focus(), 100);
            }
        });
    </script>
</body>
</html>