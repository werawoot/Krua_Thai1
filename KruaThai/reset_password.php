<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$success_message = "";
$token_valid = false;
$user_data = null;

// Check for token in URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $errors[] = "ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้อง";
} else {
    // Validate token
    $stmt = mysqli_prepare($connection, 
        "SELECT id, first_name, email, password_reset_token, password_reset_expires 
         FROM users 
         WHERE password_reset_token = ? AND password_reset_expires > NOW()");
    
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user_data = mysqli_fetch_assoc($result);
    
    if ($user_data) {
        $token_valid = true;
    } else {
        // Check if token exists but expired
        $expired_stmt = mysqli_prepare($connection, 
            "SELECT id FROM users WHERE password_reset_token = ?");
        mysqli_stmt_bind_param($expired_stmt, "s", $token);
        mysqli_stmt_execute($expired_stmt);
        $expired_result = mysqli_stmt_get_result($expired_stmt);
        
        if (mysqli_fetch_assoc($expired_result)) {
            $errors[] = "ลิงก์รีเซ็ตรหัสผ่านหมดอายุแล้ว กรุณาขอลิงก์ใหม่";
        } else {
            $errors[] = "ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้องหรือถูกใช้งานแล้ว";
        }
        mysqli_stmt_close($expired_stmt);
    }
    mysqli_stmt_close($stmt);
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($new_password)) {
        $errors[] = "กรุณากรอกรหัสผ่านใหม่";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
        $errors[] = "รหัสผ่านต้องประกอบด้วย ตัวพิมพ์เล็ก ตัวพิมพ์ใหญ่ และตัวเลข";
    }
    
    if (empty($confirm_password)) {
        $errors[] = "กรุณายืนยันรหัสผ่าน";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "รหัสผ่านไม่ตรงกัน";
    }
    
    // If no errors, update password
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $update_stmt = mysqli_prepare($connection, 
            "UPDATE users 
             SET password_hash = ?, 
                 password_reset_token = NULL, 
                 password_reset_expires = NULL,
                 failed_login_attempts = 0,
                 locked_until = NULL
             WHERE id = ?");
        
        mysqli_stmt_bind_param($update_stmt, "ss", $hashed_password, $user_data['id']);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success_message = "รีเซ็ตรหัสผ่านสำเร็จ! คุณสามารถเข้าสู่ระบบด้วยรหัสผ่านใหม่ได้แล้ว";
            
            // Log successful password reset
            logActivity($user_data['id'], 'password_reset_completed', 'Password successfully reset via email token');
            
            // Send confirmation email
            $email_subject = "รหัสผ่านถูกเปลี่ยนแล้ว - Krua Thai";
            $email_body = generatePasswordChangeConfirmationEmail($user_data['first_name']);
            sendEmail($user_data['email'], $email_subject, $email_body);
            
            $token_valid = false; // Prevent form from showing again
        } else {
            $errors[] = "เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง";
        }
        mysqli_stmt_close($update_stmt);
    }
}

$page_title = "รีเซ็ตรหัสผ่าน";
include 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/auth.css">

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <div class="logo-section">
                <img src="assets/images/logo.png" alt="Krua Thai" class="auth-logo">
                <h1>รีเซ็ตรหัสผ่าน</h1>
                <?php if ($token_valid): ?>
                    <p>สร้างรหัสผ่านใหม่สำหรับ <strong><?php echo htmlspecialchars($user_data['email']); ?></strong></p>
                <?php else: ?>
                    <p>เปลี่ยนรหัสผ่านของคุณ</p>
                <?php endif; ?>
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
                        <?php if (!$token_valid): ?>
                            <div class="error-actions">
                                <a href="forgot_password.php" class="btn-link">ขอลิงก์รีเซ็ตใหม่</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <div class="alert-icon">✅</div>
                    <div class="alert-content">
                        <h3>รีเซ็ตรหัสผ่านสำเร็จ!</h3>
                        <p><?php echo htmlspecialchars($success_message); ?></p>
                        <div class="success-actions">
                            <a href="login.php" class="btn-primary">เข้าสู่ระบบ</a>
                            <a href="index.php" class="btn-link">กลับหน้าหลัก</a>
                        </div>
                    </div>
                </div>
            <?php elseif ($token_valid): ?>
                <form method="POST" class="auth-form" id="resetPasswordForm">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="new_password" class="form-label">
                            <span class="label-text">รหัสผ่านใหม่</span>
                            <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password" 
                                class="form-input"
                                placeholder="อย่างน้อย 8 ตัวอักษร"
                                required
                                autocomplete="new-password"
                                minlength="8"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                👁️
                            </button>
                        </div>
                        <div class="password-requirements">
                            <small>รหัสผ่านต้องประกอบด้วย:</small>
                            <ul class="requirements-list">
                                <li id="req-length">อย่างน้อย 8 ตัวอักษร</li>
                                <li id="req-lowercase">ตัวพิมพ์เล็ก (a-z)</li>
                                <li id="req-uppercase">ตัวพิมพ์ใหญ่ (A-Z)</li>
                                <li id="req-number">ตัวเลข (0-9)</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">
                            <span class="label-text">ยืนยันรหัสผ่าน</span>
                            <span class="required">*</span>
                        </label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="form-input"
                                placeholder="กรอกรหัสผ่านอีกครั้ง"
                                required
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                👁️
                            </button>
                        </div>
                        <div id="password-match-indicator" class="password-match"></div>
                    </div>

                    <button type="submit" class="btn-primary btn-full" id="submitBtn">
                        <span class="btn-text">บันทึกรหัสผ่านใหม่</span>
                        <span class="btn-spinner" style="display: none;">
                            <span class="spinner"></span>
                            กำลังบันทึก...
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

    <?php if ($token_valid): ?>
    <!-- Security Notice -->
    <div class="security-notice">
        <div class="notice-header">
            <span class="notice-icon">🔐</span>
            <h3>เพื่อความปลอดภัย</h3>
        </div>
        <div class="notice-content">
            <ul>
                <li>ลิงก์นี้จะหมดอายุหลังจากใช้งาน</li>
                <li>รหัสผ่านใหม่จะมีผลทันทีหลังบันทึก</li>
                <li>เราจะส่งอีเมลยืนยันการเปลี่ยนรหัสผ่าน</li>
                <li>หากคุณไม่ได้เปลี่ยนรหัสผ่าน กรุณาติดต่อเราทันที</li>
            </ul>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.password-requirements {
    margin-top: 0.5rem;
}

.requirements-list {
    list-style: none;
    padding: 0;
    margin: 0.5rem 0;
}

.requirements-list li {
    padding: 0.25rem 0;
    color: #666;
    font-size: 0.85rem;
    position: relative;
    padding-left: 20px;
}

.requirements-list li::before {
    content: "✗";
    position: absolute;
    left: 0;
    color: #dc3545;
    font-weight: bold;
}

.requirements-list li.valid::before {
    content: "✓";
    color: #28a745;
}

.password-match {
    margin-top: 0.5rem;
    font-size: 0.85rem;
    font-weight: 500;
}

.password-match.match {
    color: #28a745;
}

.password-match.no-match {
    color: #dc3545;
}

.security-notice {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    margin-top: 2rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    border-left: 4px solid var(--brown);
}

.notice-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.notice-icon {
    font-size: 1.5rem;
}

.notice-header h3 {
    color: var(--brown);
    margin: 0;
    font-size: 1.2rem;
}

.notice-content ul {
    color: #666;
    line-height: 1.6;
}

.notice-content li {
    margin-bottom: 0.5rem;
}

.password-toggle {
    background: none;
    border: none;
    cursor: pointer;
    padding: 0.5rem;
    opacity: 0.6;
    transition: opacity 0.3s;
}

.password-toggle:hover {
    opacity: 1;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resetPasswordForm');
    const newPasswordField = document.getElementById('new_password');
    const confirmPasswordField = document.getElementById('confirm_password');
    const submitBtn = document.getElementById('submitBtn');
    
    if (form) {
        // Password strength validation
        if (newPasswordField) {
            newPasswordField.addEventListener('input', function() {
                validatePasswordStrength(this.value);
            });
        }
        
        // Password match validation
        if (confirmPasswordField) {
            confirmPasswordField.addEventListener('input', function() {
                validatePasswordMatch();
            });
        }
        
        // Form submission
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.querySelector('.btn-text').style.display = 'none';
            submitBtn.querySelector('.btn-spinner').style.display = 'flex';
        });
    }
    
    // Auto-focus first password field
    if (newPasswordField) {
        newPasswordField.focus();
    }
});

function validatePasswordStrength(password) {
    const requirements = {
        'req-length': password.length >= 8,
        'req-lowercase': /[a-z]/.test(password),
        'req-uppercase': /[A-Z]/.test(password),
        'req-number': /\d/.test(password)
    };
    
    Object.keys(requirements).forEach(reqId => {
        const element = document.getElementById(reqId);
        if (element) {
            element.classList.toggle('valid', requirements[reqId]);
        }
    });
    
    validatePasswordMatch();
}

function validatePasswordMatch() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const indicator = document.getElementById('password-match-indicator');
    
    if (!confirmPassword) {
        indicator.textContent = '';
        indicator.className = 'password-match';
        return;
    }
    
    if (newPassword === confirmPassword) {
        indicator.textContent = '✓ รหัสผ่านตรงกัน';
        indicator.className = 'password-match match';
    } else {
        indicator.textContent = '✗ รหัสผ่านไม่ตรงกัน';
        indicator.className = 'password-match no-match';
    }
}

function validateForm() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    // Check password strength
    if (newPassword.length < 8) return false;
    if (!/[a-z]/.test(newPassword)) return false;
    if (!/[A-Z]/.test(newPassword)) return false;
    if (!/\d/.test(newPassword)) return false;
    
    // Check password match
    if (newPassword !== confirmPassword) return false;
    
    return true;
}

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    
    if (field.type === 'password') {
        field.type = 'text';
        button.textContent = '🙈';
    } else {
        field.type = 'password';
        button.textContent = '👁️';
    }
}
</script>

<?php include 'includes/footer.php'; ?>

<?php
// Password change confirmation email template
function generatePasswordChangeConfirmationEmail($firstName) {
    $logoUrl = "https://" . $_SERVER['HTTP_HOST'] . "/assets/images/logo.png";
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>รหัสผ่านถูกเปลี่ยนแล้ว - Krua Thai</title>
        <style>
            body { font-family: "Sarabun", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f8f6f0; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #28a745, #20c997); padding: 2rem; text-align: center; color: white; }
            .logo { max-width: 120px; margin-bottom: 1rem; }
            .content { padding: 2rem; }
            .success-icon { font-size: 3rem; margin-bottom: 1rem; }
            .footer { background: #f8f6f0; padding: 1.5rem; text-align: center; color: #666; font-size: 0.9rem; }
            .security-notice { background: #e7f3ff; border-left: 4px solid #0066cc; padding: 1rem; margin: 1rem 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="' . $logoUrl . '" alt="Krua Thai" class="logo">
                <div class="success-icon">✅</div>
                <h1>รหัสผ่านถูกเปลี่ยนแล้ว</h1>
            </div>
            <div class="content">
                <p>สวัสดี คุณ' . htmlspecialchars($firstName) . ',</p>
                <p>เราขอแจ้งให้ทราบว่ารหัสผ่านของบัญชี Krua Thai ของคุณได้ถูกเปลี่ยนเรียบร้อยแล้ว</p>
                
                <div class="security-notice">
                    <strong>ข้อมูลการเปลี่ยนรหัสผ่าน:</strong>
                    <ul>
                        <li><strong>วันที่:</strong> ' . date('d/m/Y H:i:s') . '</li>
                        <li><strong>IP Address:</strong> ' . ($_SERVER['REMOTE_ADDR'] ?? 'ไม่ทราบ') . '</li>
                        <li><strong>วิธีการ:</strong> ผ่านลิงก์รีเซ็ตรหัสผ่าน</li>
                    </ul>
                </div>
                
                <p><strong>หากคุณไม่ได้เป็นผู้เปลี่ยนรหัสผ่าน:</strong></p>
                <ul>
                    <li>กรุณาติดต่อฝ่ายสนับสนุนทันทีที่ <a href="mailto:support@kruathai.com">support@kruathai.com</a></li>
                    <li>หรือโทร 02-xxx-xxxx</li>
                </ul>
                
                <p>เพื่อความปลอดภัย เราแนะนำให้:</p>
                <ul>
                    <li>ใช้รหัสผ่านที่แข็งแกร่งและไม่เหมือนกับเว็บไซต์อื่น</li>
                    <li>ไม่แชร์รหัสผ่านกับผู้อื่น</li>
                    <li>เปลี่ยนรหัสผ่านเป็นประจำ</li>
                </ul>
            </div>
            <div class="footer">
                <p>ด้วยความห่วงใย<br><strong>ทีมงาน Krua Thai</strong></p>
                <p>หากมีปัญหา กรุณาติดต่อ: <a href="mailto:support@kruathai.com">support@kruathai.com</a></p>
            </div>
        </div>
    </body>
    </html>';
}
?>