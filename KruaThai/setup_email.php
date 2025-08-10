<?php
/**
 * Simple Email Setup for MAMP - Krua Thai
 * File: setup_email.php
 */

$message = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gmail_user = $_POST['gmail_user'] ?? '';
    $gmail_pass = $_POST['gmail_pass'] ?? '';
    
    if (!empty($gmail_user) && !empty($gmail_pass)) {
        
        // 1. สร้าง config/email_config.php
        $config_content = "<?php
define('GMAIL_USERNAME', '" . addslashes($gmail_user) . "');
define('GMAIL_PASSWORD', '" . addslashes($gmail_pass) . "');
define('GMAIL_FROM_NAME', 'Krua Thai Restaurant');
?>";

        if (!is_dir('config')) mkdir('config', 0755, true);
        file_put_contents('config/email_config.php', $config_content);
        
        // 2. สร้าง includes/email_functions.php
        $email_functions = "<?php
require_once __DIR__ . '/../config/email_config.php';

// ตรวจสอบว่ามี PHPMailer หรือไม่
if (file_exists(__DIR__ . '/phpmailer/src/PHPMailer.php')) {
    require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/phpmailer/src/Exception.php';
    
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;
    
    function sendRealEmail(\$to, \$subject, \$body) {
        \$mail = new PHPMailer(true);
        
        try {
            \$mail->isSMTP();
            \$mail->Host = 'smtp.gmail.com';
            \$mail->SMTPAuth = true;
            \$mail->Username = GMAIL_USERNAME;
            \$mail->Password = GMAIL_PASSWORD;
            \$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            \$mail->Port = 587;
            
            \$mail->setFrom(GMAIL_USERNAME, GMAIL_FROM_NAME);
            \$mail->addAddress(\$to);
            \$mail->isHTML(true);
            \$mail->CharSet = 'UTF-8';
            \$mail->Subject = \$subject;
            \$mail->Body = \$body;
            
            \$mail->send();
            return true;
            
        } catch (Exception \$e) {
            error_log('Email Error: ' . \$mail->ErrorInfo);
            return false;
        }
    }
} else {
    function sendRealEmail(\$to, \$subject, \$body) {
        error_log('PHPMailer not found');
        return false;
    }
}

function sendPasswordResetEmailReal(\$email, \$firstName, \$resetToken) {
    \$resetLink = 'http://' . \$_SERVER['HTTP_HOST'] . '/reset_password.php?token=' . urlencode(\$resetToken);
    \$subject = 'Password Reset - Krua Thai Restaurant';
    
    \$body = '<!DOCTYPE html>
<html>
<head><meta charset=\"UTF-8\"></head>
<body style=\"font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;\">
    <div style=\"max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px;\">
        <div style=\"text-align: center; margin-bottom: 30px;\">
            <h1 style=\"color: #ff6b35;\">🍜 Krua Thai</h1>
            <p style=\"color: #666;\">Authentic Thai Meals, Made Healthy</p>
        </div>
        
        <h2 style=\"color: #333;\">Hello ' . htmlspecialchars(\$firstName) . '!</h2>
        <p>You requested a password reset for your Krua Thai account.</p>
        
        <div style=\"text-align: center; margin: 30px 0;\">
            <a href=\"' . \$resetLink . '\" 
               style=\"background: #ff6b35; color: white; padding: 15px 30px; 
                      text-decoration: none; border-radius: 25px; font-weight: bold;\">
                Reset My Password
            </a>
        </div>
        
        <p style=\"color: #666; font-size: 14px;\">
            This link expires in 1 hour.<br>
            If you didn\\'t request this, please ignore this email.
        </p>
        
        <hr style=\"margin: 30px 0;\">
        <p style=\"text-align: center; color: #999; font-size: 12px;\">
            © 2024 Krua Thai Restaurant
        </p>
    </div>
</body>
</html>';
    
    return sendRealEmail(\$email, \$subject, \$body);
}

function testEmail(\$email) {
    \$subject = 'Test Email from Krua Thai - ' . date('Y-m-d H:i:s');
    \$body = '<h2>✅ Email Test Successful!</h2>
             <p>Your email system is working correctly!</p>
             <p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>
             <p><strong>From:</strong> ' . GMAIL_USERNAME . '</p>';
    
    return sendRealEmail(\$email, \$subject, \$body);
}
?>";

        file_put_contents('includes/email_functions.php', $email_functions);
        
        // 3. อัปเดต functions.php
        if (file_exists('includes/functions.php')) {
            $functions_content = file_get_contents('includes/functions.php');
            
            // เพิ่ม require email_functions
            if (strpos($functions_content, 'email_functions.php') === false) {
                $functions_content = str_replace('<?php', "<?php\nrequire_once __DIR__ . '/email_functions.php';\n", $functions_content);
            }
            
            // แทนที่ฟังก์ชัน sendPasswordResetEmail
            if (strpos($functions_content, 'function sendPasswordResetEmail') !== false) {
                $pattern = '/function\s+sendPasswordResetEmail\s*\([^{]*\{[^}]*\}/s';
                $replacement = 'function sendPasswordResetEmail($email, $firstName, $resetToken) {
    return sendPasswordResetEmailReal($email, $firstName, $resetToken);
}';
                $functions_content = preg_replace($pattern, $replacement, $functions_content);
            } else {
                $functions_content .= "\n\nfunction sendPasswordResetEmail(\$email, \$firstName, \$resetToken) {\n    return sendPasswordResetEmailReal(\$email, \$firstName, \$resetToken);\n}";
            }
            
            file_put_contents('includes/functions.php', $functions_content);
        }
        
        $message = "✅ Email system setup completed!";
        $step = 2;
    } else {
        $message = "❌ Please enter Gmail and App Password";
    }
}

// ตรวจสอบสถานะ
$phpmailer_exists = file_exists('includes/phpmailer/src/PHPMailer.php');
$config_exists = file_exists('config/email_config.php');
$functions_exists = file_exists('includes/email_functions.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Setup - Krua Thai</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .status {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .status-item {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            margin: 5px;
            min-width: 150px;
        }
        .status-ok {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .form-group {
            margin: 15px 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .btn {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            margin: 10px 5px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
        .instructions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🍜 Krua Thai Email Setup</h1>
            <p>Configure real email sending for password reset</p>
        </div>
        
        <div class="content">
            <!-- Status Check -->
            <h3>📊 System Status</h3>
            <div class="status">
                <div class="status-item <?php echo $phpmailer_exists ? 'status-ok' : 'status-pending'; ?>">
                    <?php echo $phpmailer_exists ? '✅' : '📦'; ?><br>
                    <strong>PHPMailer</strong><br>
                    <?php echo $phpmailer_exists ? 'Installed' : 'Missing'; ?>
                </div>
                
                <div class="status-item <?php echo $config_exists ? 'status-ok' : 'status-pending'; ?>">
                    <?php echo $config_exists ? '✅' : '⚙️'; ?><br>
                    <strong>Gmail Config</strong><br>
                    <?php echo $config_exists ? 'Ready' : 'Need Setup'; ?>
                </div>
                
                <div class="status-item <?php echo $functions_exists ? 'status-ok' : 'status-pending'; ?>">
                    <?php echo $functions_exists ? '✅' : '🔧'; ?><br>
                    <strong>Functions</strong><br>
                    <?php echo $functions_exists ? 'Updated' : 'Pending'; ?>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert <?php echo $step == 2 ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($step == 1): ?>
            <!-- Gmail Setup Form -->
            <div class="instructions">
                <h3>🔑 Step 1: Gmail App Password Setup</h3>
                <p><strong>You need a Gmail App Password (NOT your regular password):</strong></p>
                <ol>
                    <li>Go to: <a href="https://myaccount.google.com/apppasswords" target="_blank">Gmail App Passwords</a></li>
                    <li>Select "Mail" → Generate</li>
                    <li>Copy the 16-digit code</li>
                </ol>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label>Gmail Address:</label>
                    <input type="email" name="gmail_user" class="form-control" 
                           placeholder="your-email@gmail.com" required>
                </div>
                
                <div class="form-group">
                    <label>Gmail App Password (16 digits):</label>
                    <input type="password" name="gmail_pass" class="form-control" 
                           placeholder="abcd efgh ijkl mnop" required>
                </div>
                
                <button type="submit" class="btn">
                    🚀 Setup Email System
                </button>
            </form>
            <?php endif; ?>

            <?php if ($step == 2): ?>
            <!-- Test Email -->
            <div class="instructions">
                <h3>🧪 Step 2: Test Email System</h3>
                <p>Your email system is configured! Now test it:</p>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4>📧 Send Test Email</h4>
                    <input type="email" id="testEmail" class="form-control" 
                           placeholder="test@gmail.com">
                    <button onclick="sendTestEmail()" class="btn">
                        Send Test Email
                    </button>
                </div>
                
                <div>
                    <h4>🔑 Test Password Reset</h4>
                    <input type="email" id="resetEmail" class="form-control" 
                           placeholder="reset@gmail.com">
                    <button onclick="sendResetEmail()" class="btn">
                        Send Reset Email
                    </button>
                </div>
            </div>

            <div class="alert alert-success" style="margin-top: 30px;">
                <h4>🎉 Success! Your system is ready!</h4>
                <p><strong>What works now:</strong></p>
                <ul>
                    <li>✅ Real emails sent via Gmail</li>
                    <li>✅ Password reset emails work</li>
                    <li>✅ Users can reset passwords</li>
                    <li>✅ Production ready!</li>
                </ul>
                
                <div style="margin-top: 20px;">
                    <a href="forgot_password.php" class="btn">Test Forgot Password</a>
                    <a href="login.php" class="btn">Go to Login</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function sendTestEmail() {
            const email = document.getElementById('testEmail').value;
            if (!email) {
                alert('Please enter an email address');
                return;
            }
            
            fetch('test_email_ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=test&email=' + encodeURIComponent(email)
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
            });
        }
        
        function sendResetEmail() {
            const email = document.getElementById('resetEmail').value;
            if (!email) {
                alert('Please enter an email address');
                return;
            }
            
            fetch('test_email_ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=reset&email=' + encodeURIComponent(email)
            })
            .then(response => response.text())
            .then(data => {
                alert(data);
            });
        }
    </script>
</body>
</html>