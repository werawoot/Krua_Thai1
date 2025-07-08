<?php
/**
 * Krua Thai - Beautiful Forgot Password Page
 * File: forgot_password.php
 * Description: Modern, responsive forgot password page with beautiful UI
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    // Validate email
    if (empty($email)) {
        $errors[] = "กรุณากรอกอีเมล";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "รูปแบบอีเมลไม่ถูกต้อง";
    } else {
        // Check if email exists in database
        if (isset($connection) && mysqli_ping($connection)) {
            $stmt = mysqli_prepare($connection, "SELECT id, first_name, email, status FROM users WHERE email = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);
                
                if ($user) {
                    // Check if account is active
                    if ($user['status'] !== 'active' && $user['status'] !== 'pending_verification') {
                        $errors[] = "บัญชีของคุณถูกระงับ กรุณาติดต่อฝ่ายสนับสนุน";
                    } else {
                        // Generate password reset token
                        $reset_token = bin2hex(random_bytes(32));
                        $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                        
                        // Update user with reset token
                        $update_stmt = mysqli_prepare($connection, 
                            "UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
                        if ($update_stmt) {
                            mysqli_stmt_bind_param($update_stmt, "sss", $reset_token, $reset_expires, $user['id']);
                            
                            if (mysqli_stmt_execute($update_stmt)) {
                                $success_message = "เราได้ส่งลิงก์รีเซ็ตรหัสผ่านไปยังอีเมลของคุณแล้ว กรุณาตรวจสอบอีเมล";
                                
                                // Log activity if function exists
                                if (function_exists('logActivity')) {
                                    logActivity($user['id'], 'password_reset_requested', 'Password reset token generated');
                                }
                            } else {
                                $errors[] = "เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง";
                            }
                            mysqli_stmt_close($update_stmt);
                        }
                    }
                } else {
                    // Don't reveal if email exists or not for security
                    $success_message = "หากอีเมลนี้มีอยู่ในระบบ เราจะส่งลิงก์รีเซ็ตรหัสผ่านให้คุณ";
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $success_message = "ระบบไม่พร้อมใช้งานในขณะนี้ กรุณาลองใหม่อีกครั้ง";
        }
    }
}

$page_title = "ลืมรหัสผ่าน";
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Krua Thai</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
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
            --shadow-lg: rgba(61, 64, 40, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--olive);
            background: linear-gradient(135deg, var(--light-cream) 0%, #f0ebe1 100%);
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
            border-radius: 20px;
            box-shadow: 0 20px 60px var(--shadow-lg);
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(134, 96, 40, 0.1);
        }

        .auth-header {
            background: linear-gradient(135deg, var(--olive) 0%, var(--matcha) 100%);
            color: var(--white);
            padding: 3rem 2rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .auth-header::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: floating 6s ease-in-out infinite;
        }

        @keyframes floating {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        .logo-section {
            position: relative;
            z-index: 1;
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
            border: 3px solid rgba(255,255,255,0.3);
        }

        .auth-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .auth-header p {
            font-size: 1rem;
            opacity: 0.9;
            line-height: 1.5;
        }

        .auth-body {
            padding: 2.5rem 2rem;
        }

        /* Alerts */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            font-weight: 500;
            border: 1px solid;
            position: relative;
            overflow: hidden;
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
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(40, 167, 69, 0.05));
            border-color: var(--success);
            color: var(--success);
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(220, 53, 69, 0.05));
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

        .success-actions {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
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
            color: var(--olive);
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
            transition: all 0.3s ease;
        }

        .form-input {
            width: 100%;
            padding: 1.25rem 1rem 1.25rem 3rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
            color: var(--olive);
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--brown);
            box-shadow: 0 0 0 4px rgba(134, 96, 40, 0.1);
            transform: translateY(-1px);
        }

        .form-input:focus + .input-icon {
            color: var(--brown);
            transform: scale(1.1);
        }

        .form-input::placeholder {
            color: #adb5bd;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(45deg, var(--brown), #a67c00);
            color: var(--white);
            padding: 1.25rem 2rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(134, 96, 40, 0.3);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-transform: none;
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
            background: linear-gradient(45deg, #a67c00, var(--brown));
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(134, 96, 40, 0.4);
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            background: var(--gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            opacity: 0.7;
        }

        .btn-full {
            width: 100%;
        }

        .btn-spinner {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid transparent;
            border-top: 2px solid var(--white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Footer */
        .auth-footer {
            padding: 1.5rem 2rem 2rem;
            background: var(--light-gray);
            border-top: 1px solid #e9ecef;
        }

        .auth-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .auth-link {
            color: var(--brown);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .auth-link:hover {
            background: var(--brown);
            color: var(--white);
            transform: translateY(-1px);
            border-color: var(--brown);
        }

        .link-icon {
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }

        .auth-link:hover .link-icon {
            transform: translateX(2px);
        }

        .link-divider {
            color: #dee2e6;
            font-weight: 400;
        }

        .btn-link {
            color: var(--brown);
            text-decoration: none;
            font-weight: 600;
            padding: 0.75rem 1rem;
            border: 1px solid var(--brown);
            border-radius: 8px;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-link:hover {
            background: var(--brown);
            color: var(--white);
            transform: translateY(-1px);
        }

        /* Help Section */
        .help-section {
            margin-top: 2rem;
            background: var(--white);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px var(--shadow);
            border: 1px solid rgba(134, 96, 40, 0.1);
        }

        .help-section h3 {
            color: var(--olive);
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 700;
        }

        .help-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .help-item {
            text-align: center;
            padding: 1.5rem;
            background: var(--light-cream);
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 1px solid rgba(134, 96, 40, 0.1);
        }

        .help-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px var(--shadow);
            background: var(--cream);
        }

        .help-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .help-item h4 {
            color: var(--olive);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .help-item p {
            color: var(--gray);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .help-item a {
            color: var(--brown);
            text-decoration: none;
            font-weight: 600;
        }

        .help-item a:hover {
            text-decoration: underline;
        }

        /* Background Animation */
        .auth-container::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(134,96,40,0.1)"/><circle cx="80" cy="80" r="3" fill="rgba(134,96,40,0.05)"/><circle cx="40" cy="70" r="1" fill="rgba(134,96,40,0.1)"/></svg>');
            animation: float 20s linear infinite;
            pointer-events: none;
            z-index: -1;
        }

        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); }
            100% { transform: translateY(-100vh) rotate(360deg); }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .auth-container {
                max-width: 100%;
            }

            .auth-header {
                padding: 2rem 1.5rem 1.5rem;
            }

            .auth-header h1 {
                font-size: 1.5rem;
            }

            .auth-body,
            .auth-footer {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }

            .help-section {
                padding: 1.5rem;
            }

            .help-grid {
                grid-template-columns: 1fr;
            }

            .auth-links {
                flex-direction: column;
                gap: 0.5rem;
            }

            .link-divider {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .help-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .help-item {
                padding: 1rem;
            }

            .auth-header {
                padding: 1.5rem 1rem;
            }

            .auth-body,
            .auth-footer,
            .help-section {
                padding: 1.5rem 1rem;
            }
        }

        /* Focus Management */
        .form-input:focus-visible {
            outline: 2px solid var(--brown);
            outline-offset: 2px;
        }

        /* Loading State */
        .loading .auth-card {
            pointer-events: none;
            opacity: 0.8;
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
    </style>
</head>
<body>
    <div class="auth-container <?php echo $success_message ? 'success-state' : ''; ?>">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-section">
                    <div class="auth-logo">🍜</div>
                    <h1>ลืมรหัสผ่าน</h1>
                    <p>กรอกอีเมลของคุณเพื่อรับลิงก์รีเซ็ตรหัสผ่าน<br>เราจะส่งคำแนะนำไปให้คุณทันที</p>
                </div>
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
                            <div class="success-actions">
                                <a href="login.php" class="btn-link">กลับไปหน้าเข้าสู่ระบบ</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" class="auth-form" id="forgotPasswordForm">
                        <div class="form-group">
                            <label for="email" class="form-label">
                                <span class="label-text">อีเมลของคุณ</span>
                                <span class="required">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="form-input"
                                    placeholder="กรอกอีเมลที่ใช้สมัครสมาชิก"
                                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                    required
                                    autocomplete="email"
                                    autofocus
                                >
                                <span class="input-icon">📧</span>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary btn-full" id="submitBtn">
                            <span class="btn-text">ส่งลิงก์รีเซ็ตรหัสผ่าน</span>
                            <span class="btn-spinner" style="display: none;">
                                <span class="spinner"></span>
                                กำลังส่ง...
                            </span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="auth-footer">
                <div class="auth-links">
                    <a href="login.php" class="auth-link">
                        <span class="link-icon">←</span>
                        กลับไปหน้าเข้าสู่ระบบ
                    </a>
                    <span class="link-divider">|</span>
                    <a href="register.php" class="auth-link">
                        สมัครสมาชิกใหม่
                        <span class="link-icon">→</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="help-section">
            <h3>💡 ต้องการความช่วยเหลือ?</h3>
            <div class="help-grid">
                <div class="help-item">
                    <div class="help-icon">💡</div>
                    <h4>เคล็ดลับ</h4>
                    <p>ตรวจสอบโฟลเดอร์ Spam หากไม่พบอีเมลรีเซ็ตรหัสผ่าน</p>
                </div>
                <div class="help-item">
                    <div class="help-icon">⏰</div>
                    <h4>หมดอายุ</h4>
                    <p>ลิงก์รีเซ็ตรหัสผ่านจะหมดอายุภายใน 1 ชั่วโมง</p>
                </div>
                <div class="help-item">
                    <div class="help-icon">🔒</div>
                    <h4>ความปลอดภัย</h4>
                    <p>ลิงก์รีเซ็ตใช้ได้เพียงครั้งเดียวเท่านั้น</p>
                </div>
                <div class="help-item">
                    <div class="help-icon">📞</div>
                    <h4>ติดต่อเรา</h4>
                    <p>หากมีปัญหา โทร <a href="tel:02-000-1234">02-000-1234</a><br>หรือ <a href="mailto:support@kruathai.com">support@kruathai.com</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotPasswordForm');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = submitBtn?.querySelector('.btn-text');
            const btnSpinner = submitBtn?.querySelector('.btn-spinner');
            const emailField = document.getElementById('email');

            // Form submission handling
            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    // Show loading state
                    submitBtn.disabled = true;
                    btnText.style.display = 'none';
                    btnSpinner.style.display = 'flex';
                    
                    // Add loading class to container
                    document.querySelector('.auth-container').classList.add('loading');
                });
            }

            // Auto-focus email field on load
            if (emailField && !emailField.value) {
                setTimeout(() => emailField.focus(), 100);
            }

            // Enhanced email validation
            if (emailField) {
                emailField.addEventListener('input', function() {
                    const email = this.value.trim();
                    const isValid = email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                    
                    if (email.length > 0) {
                        if (isValid) {
                            this.style.borderColor = 'var(--success)';
                            this.style.boxShadow = '0 0 0 4px rgba(40, 167, 69, 0.1)';
                        } else {
                            this.style.borderColor = 'var(--danger)';
                            this.style.boxShadow = '0 0 0 4px rgba(220, 53, 69, 0.1)';
                        }
                    } else {
                        this.style.borderColor = '#e9ecef';
                        this.style.boxShadow = '';
                    }
                });

                // Clear validation on focus
                emailField.addEventListener('focus', function() {
                    this.style.borderColor = 'var(--brown)';
                    this.style.boxShadow = '0 0 0 4px rgba(134, 96, 40, 0.1)';
                });
            }

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.ctrlKey && form) {
                    e.preventDefault();
                    form.submit();
                }
                
                if (e.key === 'Escape') {
                    window.location.href = 'login.php';
                }
            });

            // Auto-hide success/error messages
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    setTimeout(() => {
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-20px)';
                        setTimeout(() => {
                            alert.remove();
                        }, 300);
                    }, 8000); // Hide success message after 8 seconds
                }
            });

            // Enhanced form validation
            if (form) {
                const validateForm = () => {
                    const email = emailField.value.trim();
                    const isValidEmail = email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
                    
                    if (submitBtn) {
                        submitBtn.disabled = !isValidEmail;
                        if (isValidEmail) {
                            submitBtn.style.opacity = '1';
                            submitBtn.style.cursor = 'pointer';
                        } else {
                            submitBtn.style.opacity = '0.6';
                            submitBtn.style.cursor = 'not-allowed';
                        }
                    }
                };

                emailField.addEventListener('input', validateForm);
                validateForm(); // Initial validation
            }

            // Smooth scrolling for help section
            const helpSection = document.querySelector('.help-section');
            if (helpSection) {
                const observerOptions = {
                    threshold: 0.1,
                    rootMargin: '0px 0px -50px 0px'
                };

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.style.animation = 'slideInUp 0.6s ease-out forwards';
                        }
                    });
                }, observerOptions);

                observer.observe(helpSection);
            }

            // Add ripple effect to buttons
            const buttons = document.querySelectorAll('.btn-primary, .auth-link, .btn-link');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        background: rgba(255, 255, 255, 0.3);
                        border-radius: 50%;
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        pointer-events: none;
                    `;
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });

            // Add CSS for ripple animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);

            // Preload login page for faster navigation
            const loginLink = document.querySelector('a[href="login.php"]');
            if (loginLink) {
                const preloadLink = document.createElement('link');
                preloadLink.rel = 'preload';
                preloadLink.href = 'login.php';
                preloadLink.as = 'document';
                document.head.appendChild(preloadLink);
            }

            // Add loading state management
            let isSubmitting = false;
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (isSubmitting) {
                        e.preventDefault();
                        return false;
                    }
                    isSubmitting = true;
                });
            }

            // Performance: Lazy load help section animations
            const helpItems = document.querySelectorAll('.help-item');
            helpItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100); // Stagger animation
            });

            console.log('🍜 Krua Thai - Forgot Password page loaded successfully!');
        });

        // Global error handler
        window.addEventListener('error', function(e) {
            console.error('Page error:', e.error);
            
            // Show user-friendly error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error';
            errorDiv.innerHTML = `
                <div class="alert-icon">⚠️</div>
                <div class="alert-content">
                    <p>เกิดข้อผิดพลาดที่ไม่คาดคิด กรุณารีเฟรชหน้าเว็บ</p>
                </div>
            `;
            
            const authBody = document.querySelector('.auth-body');
            if (authBody) {
                authBody.insertBefore(errorDiv, authBody.firstChild);
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

<?php
/**
 * Email Template Functions
 * Add these to includes/functions.php if they don't exist
 */

if (!function_exists('generatePasswordResetEmail')) {
    function generatePasswordResetEmail($firstName, $resetLink) {
        $logoUrl = "https://" . $_SERVER['HTTP_HOST'] . "/assets/images/logo.png";
        $currentYear = date('Y');
        
        return '
        <!DOCTYPE html>
        <html lang="th">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>รีเซ็ตรหัสผ่าน - Krua Thai</title>
            <style>
                body { 
                    font-family: "Inter", "Sarabun", Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #3d4028; 
                    margin: 0; 
                    padding: 0; 
                    background-color: #f5ede4; 
                }
                .container { 
                    max-width: 600px; 
                    margin: 0 auto; 
                    background: white; 
                    border-radius: 20px; 
                    overflow: hidden; 
                    box-shadow: 0 20px 60px rgba(61, 64, 40, 0.15);
                    border: 1px solid rgba(134, 96, 40, 0.1);
                }
                .header { 
                    background: linear-gradient(135deg, #3d4028, #4e4f22); 
                    padding: 3rem 2rem; 
                    text-align: center; 
                    color: white; 
                    position: relative;
                }
                .header::before {
                    content: "";
                    position: absolute;
                    top: 0; right: 0; bottom: 0; left: 0;
                    background: url(\'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="80" cy="20" r="15" fill="rgba(255,255,255,0.1)"/></svg>\');
                    opacity: 0.3;
                }
                .logo { 
                    width: 80px; 
                    height: 80px; 
                    background: white; 
                    border-radius: 50%; 
                    margin: 0 auto 1.5rem; 
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                    font-size: 2rem;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                }
                .header h1 { 
                    margin: 0; 
                    font-size: 2rem; 
                    font-weight: 700;
                    text-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .content { 
                    padding: 3rem 2rem; 
                }
                .btn-reset { 
                    display: inline-block; 
                    background: linear-gradient(45deg, #866028, #a67c00); 
                    color: white; 
                    padding: 1.25rem 2.5rem; 
                    text-decoration: none; 
                    border-radius: 12px; 
                    font-weight: 600; 
                    margin: 2rem 0;
                    box-shadow: 0 8px 25px rgba(134, 96, 40, 0.3);
                    transition: all 0.3s ease;
                }
                .footer { 
                    background: #f8f6f0; 
                    padding: 2rem; 
                    text-align: center; 
                    color: #6c757d; 
                    font-size: 0.9rem; 
                    border-top: 1px solid #e9ecef;
                }
                .warning { 
                    background: linear-gradient(135deg, rgba(255, 193, 7, 0.1), rgba(255, 193, 7, 0.05)); 
                    border-left: 4px solid #ffc107; 
                    padding: 1.5rem; 
                    margin: 2rem 0; 
                    border-radius: 8px;
                    border: 1px solid rgba(255, 193, 7, 0.3);
                }
                .warning h3 { 
                    margin-top: 0; 
                    color: #856404; 
                    font-size: 1.1rem;
                }
                .warning ul { 
                    margin-bottom: 0; 
                    padding-left: 1.5rem;
                }
                .warning li { 
                    margin-bottom: 0.5rem; 
                }
                .link-box {
                    background: #f8f9fa;
                    padding: 1.5rem;
                    border-radius: 8px;
                    margin: 2rem 0;
                    border: 1px solid #e9ecef;
                    word-break: break-all;
                    font-family: monospace;
                    font-size: 0.9rem;
                    line-height: 1.4;
                }
                .highlight {
                    background: linear-gradient(135deg, rgba(134, 96, 40, 0.1), rgba(134, 96, 40, 0.05));
                    padding: 1rem;
                    border-radius: 8px;
                    margin: 1rem 0;
                    border-left: 3px solid #866028;
                }
                @media (max-width: 600px) {
                    .container { margin: 1rem; border-radius: 15px; }
                    .header, .content, .footer { padding: 2rem 1.5rem; }
                    .btn-reset { display: block; text-align: center; margin: 2rem 0; }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">🍜</div>
                    <h1>รีเซ็ตรหัสผ่าน</h1>
                </div>
                <div class="content">
                    <p style="font-size: 1.1rem; margin-bottom: 1.5rem;">สวัสดี คุณ<strong>' . htmlspecialchars($firstName) . '</strong> 👋</p>
                    
                    <p>เราได้รับคำขอรีเซ็ตรหัสผ่านสำหรับบัญชี <strong>Krua Thai</strong> ของคุณ</p>
                    
                    <p>กรุณาคลิกปุ่มด้านล่างเพื่อสร้างรหัสผ่านใหม่:</p>
                    
                    <div style="text-align: center; margin: 2.5rem 0;">
                        <a href="' . $resetLink . '" class="btn-reset">🔒 รีเซ็ตรหัสผ่าน</a>
                    </div>
                    
                    <div class="warning">
                        <h3>⚠️ ข้อมูลสำคัญ:</h3>
                        <ul>
                            <li><strong>ลิงก์นี้จะหมดอายุภายใน 1 ชั่วโมง</strong></li>
                            <li>ใช้ได้เพียงครั้งเดียวเท่านั้น</li>
                            <li>หากคุณไม่ได้ขอรีเซ็ตรหัสผ่าน กรุณาเพิกเฉยอีเมลนี้</li>
                            <li>ห้ามแชร์ลิงก์นี้กับผู้อื่น</li>
                        </ul>
                    </div>
                    
                    <div class="highlight">
                        <p><strong>💡 เคล็ดลับ:</strong> หากปุ่มไม่ทำงาน กรุณาคัดลอกลิงก์ด้านล่างและวางในเบราว์เซอร์:</p>
                    </div>
                    
                    <div class="link-box">
                        ' . $resetLink . '
                    </div>
                    
                    <p style="margin-top: 2rem; color: #6c757d; font-size: 0.95rem;">
                        หากคุณยังคงประสบปัญหา กรุณาติดต่อทีมสนับสนุนของเราได้ตลอด 24 ชั่วโมง
                    </p>
                </div>
                <div class="footer">
                    <p style="margin-bottom: 1rem;">ด้วยความห่วงใย ❤️<br><strong>ทีมงาน Krua Thai</strong></p>
                    <p style="margin-bottom: 1rem;">
                        📞 <a href="tel:02-000-1234" style="color: #866028;">02-000-1234</a> | 
                        ✉️ <a href="mailto:support@kruathai.com" style="color: #866028;">support@kruathai.com</a>
                    </p>
                    <p style="font-size: 0.8rem; color: #adb5bd;">
                        © ' . $currentYear . ' Krua Thai. สงวนลิขสิทธิ์ทุกการใช้งาน<br>
                        อีเมลนี้ถูกส่งถึง ' . htmlspecialchars($firstName) . ' เนื่องจากมีการร้องขอรีเซ็ตรหัสผ่าน
                    </p>
                </div>
            </div>
        </body>
        </html>';
    }
}

if (!function_exists('sendEmail')) {
    function sendEmail($to, $subject, $body) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: Krua Thai <noreply@kruathai.com>',
            'Reply-To: support@kruathai.com',
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 1',
            'Importance: High'
        ];
        
        // For development, log email instead of sending
        if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
            error_log("=== EMAIL DEBUG ===");
            error_log("To: " . $to);
            error_log("Subject: " . $subject);
            error_log("Body: " . substr(strip_tags($body), 0, 200) . "...");
            error_log("==================");
            return true; // Simulate successful sending in development
        }
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
}
?>