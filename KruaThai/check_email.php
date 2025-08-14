<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Your Email | Krua Thai</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            text-align: center;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            padding: 40px 30px;
            color: white;
        }
        .logo { font-size: 48px; margin-bottom: 10px; }
        .content { padding: 40px 30px; }
        .email-display {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            font-weight: 600;
            color: #495057;
            margin: 25px 0;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            padding: 14px 28px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            margin: 10px;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">📧</div>
            <h1>Check Your Email</h1>
            <p>We've sent you a verification link</p>
        </div>
        
        <div class="content">
            <h2>Verify Your Account</h2>
            <p><strong>We've sent a verification email to:</strong></p>
            
            <div class="email-display">
                <?php echo htmlspecialchars($_GET['email'] ?? 'your-email@example.com'); ?>
            </div>
            
            <p>Please check your email and click the verification link to activate your account.</p>
            
            <div style="background: #e3f2fd; padding: 20px; border-radius: 12px; margin: 20px 0;">
                <h4 style="color: #1976d2; margin-bottom: 10px;">📬 Can't find the email?</h4>
                <ul style="text-align: left; color: #1565c0; font-size: 14px;">
                    <li>Check your spam/junk folder</li>
                    <li>Look for emails from Krua Thai</li>
                    <li>Check the test_emails folder (development)</li>
                </ul>
            </div>
            
            <div>
                <a href="login.php" class="btn btn-primary">Go to Login</a>
                <a href="register.php" class="btn btn-secondary">Register Again</a>
            </div>
        </div>
    </div>
</body>
</html>