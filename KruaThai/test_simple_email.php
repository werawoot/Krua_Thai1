<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once 'includes/email_functions.php';

echo "<h2>Testing Gmail with Full Debug...</h2>";

// แสดง Error ทั้งหมด
if (function_exists('error_get_last')) {
    $error = error_get_last();
    if ($error) {
        echo "<h3>Last Error:</h3>";
        echo "<pre>" . print_r($error, true) . "</pre>";
    }
}

echo "<h3>Configuration Check:</h3>";
if (defined('GMAIL_USERNAME')) {
    echo "✅ Gmail Username: " . GMAIL_USERNAME . "<br>";
    echo "✅ Gmail Password: " . (defined('GMAIL_PASSWORD') ? 'Set (' . strlen(GMAIL_PASSWORD) . ' chars)' : '❌ Not Set') . "<br>";
} else {
    echo "❌ Gmail config not loaded!<br>";
    echo "Looking for config file...<br>";
    if (file_exists('config/email_config.php')) {
        echo "✅ Config file exists<br>";
    } else {
        echo "❌ Config file missing!<br>";
    }
}

// Test PHPMailer
echo "<h3>PHPMailer Check:</h3>";
if (file_exists('includes/phpmailer/src/PHPMailer.php')) {
    echo "✅ PHPMailer file exists<br>";
    require_once 'includes/phpmailer/src/PHPMailer.php';
    require_once 'includes/phpmailer/src/SMTP.php';
    require_once 'includes/phpmailer/src/Exception.php';
    
    if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        echo "✅ PHPMailer class loaded<br>";
    } else {
        echo "❌ PHPMailer class not loaded<br>";
    }
} else {
    echo "❌ PHPMailer file not found<br>";
}

echo "<hr>";
echo "<h3>Sending Test Email:</h3>";

$testEmail = "topkung72@gmail.com";
echo "To: $testEmail<br>";

try {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = GMAIL_USERNAME;
    $mail->Password = GMAIL_PASSWORD;
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->SMTPDebug = 2;  // Enable debug output
    
    // Recipients
    $mail->setFrom(GMAIL_USERNAME, 'Krua Thai Test');
    $mail->addAddress($testEmail);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'MAMP Test Email - ' . date('H:i:s');
    $mail->Body = '<h1>✅ MAMP Email Test Successful!</h1><p>Time: ' . date('Y-m-d H:i:s') . '</p>';
    
    $mail->send();
    echo "✅ Email sent successfully!";
    
} catch (\PHPMailer\PHPMailer\Exception $e) {
    echo "❌ PHPMailer Error: " . $e->getMessage();
} catch (Exception $e) {
    echo "❌ General Error: " . $e->getMessage();
}
?>