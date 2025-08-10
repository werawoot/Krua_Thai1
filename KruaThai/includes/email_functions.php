<?php
require_once __DIR__ . '/../config/email_config.php';

function sendRealEmail($to, $subject, $body) {
    if (file_exists(__DIR__ . '/phpmailer/src/PHPMailer.php')) {
        require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/phpmailer/src/Exception.php';
        
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = GMAIL_USERNAME;
            $mail->Password = GMAIL_PASSWORD;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            $mail->setFrom(GMAIL_USERNAME, GMAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $mail->send();
            return true;
            
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log('Email Error: ' . $mail->ErrorInfo);
            return false;
        }
    } else {
        error_log('PHPMailer not found');
        return false;
    }
}

function sendPasswordResetEmailReal($email, $firstName, $resetToken) {
    $resetLink = 'http://' . $_SERVER['HTTP_HOST'] . '/reset_password.php?token=' . urlencode($resetToken);
    $subject = 'Password Reset - Krua Thai Restaurant';
    
    $body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #ff6b35;">🍜 Krua Thai</h1>
            <p style="color: #666;">Authentic Thai Meals, Made Healthy</p>
        </div>
        
        <h2 style="color: #333;">Hello ' . htmlspecialchars($firstName) . '!</h2>
        <p>You requested a password reset for your Krua Thai account.</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . $resetLink . '" 
               style="background: #ff6b35; color: white; padding: 15px 30px; 
                      text-decoration: none; border-radius: 25px; font-weight: bold;">
                Reset My Password
            </a>
        </div>
        
        <p style="color: #666; font-size: 14px;">
            This link expires in 1 hour.<br>
            If you didn\'t request this, please ignore this email.
        </p>
        
        <hr style="margin: 30px 0;">
        <p style="text-align: center; color: #999; font-size: 12px;">
            © 2024 Krua Thai Restaurant
        </p>
    </div>
</body>
</html>';
    
    return sendRealEmail($email, $subject, $body);
}

function testEmail($email) {
    $subject = 'Test Email from Krua Thai - ' . date('Y-m-d H:i:s');
    $body = '<h2>✅ Email Test Successful!</h2>
             <p>Your email system is working correctly!</p>
             <p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>
             <p><strong>From:</strong> ' . GMAIL_USERNAME . '</p>';
    
    return sendRealEmail($email, $subject, $body);
}
?>