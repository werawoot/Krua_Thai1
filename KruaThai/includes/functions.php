<?php
require_once __DIR__ . '/email_functions.php';

/**
 * Complete Helper Functions for Krua Thai
 * File: includes/functions.php
 */

/**
 * Generate UUID v4 (Primary function)
 */
function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Generate UUID v4 (Alias for backward compatibility)
 * ✅ เพิ่มฟังก์ชันนี้เพื่อแก้ปัญหา
 */
function generate_uuid() {
    return generateUUID();
}

/**
 * Generate secure random token
 */
function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Sanitize user input
 */
function sanitizeInput($data) {
    if ($data === null) {
        return null;
    }
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Sanitize input (Alias)
 */
function sanitize_input($data) {
    return sanitizeInput($data);
}

/**
 * Validate email address
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate email (Alias)
 */
function validate_email($email) {
    return validateEmail($email);
}

/**
 * Get user avatar initials
 */
function get_user_avatar($firstName, $lastName) {
    return mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1);
}

/**
 * Format Thai phone number
 */
function format_phone_number($phone) {
    if (empty($phone)) return 'N/A';
    
    // Format Thai phone number (0812345678 -> 081-234-5678)
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
        return substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6);
    }
    
    return $phone;
}

/**
 * Calculate age from birth date
 */
function calculate_age($birthDate) {
    if (empty($birthDate)) return null;
    
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    $age = $birth->diff($today);
    
    return $age->y;
}

/**
 * Validate Thai phone number
 */
function validate_thai_phone($phone) {
    // Thai phone format: 08xxxxxxxx or 09xxxxxxxx
    return preg_match('/^0[89]\d{8}$/', $phone);
}

/**
 * Get user's real IP address
 */
function getRealIPAddress() {
    $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Log activity for security monitoring
 */
function logActivity($action, $user_id = null, $ip_address = null, $details = []) {
    $log_file = __DIR__ . '/../logs/activity.log';
    
    // Create logs directory if it doesn't exist
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $ip_address ?: getRealIPAddress();
    
    $log_entry = [
        'timestamp' => $timestamp,
        'action' => $action,
        'user_id' => $user_id,
        'ip_address' => $ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    $log_line = json_encode($log_entry) . "\n";
    file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
}

/**
 * Send verification email
 */
function sendVerificationEmail($email, $first_name, $token) {
    $subject = "Verify Your Krua Thai Account";
    $verification_link = "http://localhost/kruathai/verify_email.php?token=" . urlencode($token);
    
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; color: #3d4028; background-color: #f5ede4; margin: 0; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 20px; padding: 40px;'>
            <h2 style='color: #866028; text-align: center;'>Welcome to Krua Thai, {$first_name}!</h2>
            <p>Please click the button below to verify your email address:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$verification_link}' 
                   style='background: #866028; color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; display: inline-block;'>
                   Verify Email Address
                </a>
            </div>
            <p>If the button doesn't work, copy and paste this link:</p>
            <p><a href='{$verification_link}'>{$verification_link}</a></p>
            <p>Best regards,<br>The Krua Thai Team</p>
        </div>
    </body>
    </html>
    ";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Krua Thai <noreply@kruathai.com>',
        'Reply-To: support@kruathai.com'
    ];
    
    return mail($email, $subject, $message, implode("\r\n", $headers));
}

/**
 * Redirect with message
 */
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

/**
 * Display flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        $alert_class = [
            'success' => 'alert-success',
            'error' => 'alert-error', 
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ][$type] ?? 'alert-info';
        
        return "<div class='alert {$alert_class}'>" . htmlspecialchars($message) . "</div>";
    }
    
    return '';
}

/**
 * Check if user is logged in
 */
function requireLogin() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Format Thai date
 */
function formatThaiDate($date) {
    $thai_months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม',
        4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
        10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = $thai_months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp) + 543;
    
    return "$day $month $year";
}

/**
 * Format Thai date (Alias)
 */
function format_thai_date($date) {
    return formatThaiDate($date);
}

/**
 * Generate order number
 */
function generateOrderNumber() {
    $prefix = 'KT';
    $date = date('ymd');
    $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . $date . $random;
}

/**
 * Debug function - remove in production
 */
function debug($data, $label = 'DEBUG') {
    // เช็คว่าเป็น development environment
    $is_dev = (isset($_SERVER['SERVER_NAME']) && 
               ($_SERVER['SERVER_NAME'] === 'localhost' || 
                strpos($_SERVER['SERVER_NAME'], '.local') !== false ||
                $_SERVER['SERVER_ADDR'] === '127.0.0.1'));
    
    if ($is_dev) {
        echo "<pre style='background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; margin: 10px 0;'>";
        echo "<strong>{$label}:</strong>\n";
        print_r($data);
        echo "</pre>";
    }
}


/**
 * Check if email domain is allowed (ฟังก์ชันที่ขาดหายไป)
 */
function isEmailDomainAllowed($email) {
    // Extract domain from email
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    
    // List of blocked domains (disposable email services)
    $blocked_domains = [
        'tempmail.org',
        '10minutemail.com', 
        'guerrillamail.com',
        'mailinator.com',
        'throwaway.email'
    ];
    
    // Check if domain is not in blocked list
    return !in_array($domain, $blocked_domains);
}

/**
 * Validate US phone number
 * Accepts formats like: (555) 123-4567, 555-123-4567, 555.123.4567, 5551234567, +15551234567
 */
function validatePhone($phone) {
    if (empty($phone)) {
        return false;
    }
    
    // Remove all non-digits
    $clean_phone = preg_replace('/\D/', '', $phone);
    
    // Check for US phone number formats
    // 10 digits: 5551234567 (without country code)
    // 11 digits: 15551234567 (with country code +1)
    if (strlen($clean_phone) === 10) {
        // 10-digit US number, first digit should be 2-9 (area code cannot start with 0 or 1)
        return preg_match('/^[2-9]\d{9}$/', $clean_phone);
    } elseif (strlen($clean_phone) === 11) {
        // 11-digit with country code, should start with 1 and area code 2-9
        return preg_match('/^1[2-9]\d{9}$/', $clean_phone);
    }
    
    return false;
}

/**
 * Clean phone number for storage (US format)
 * Stores as 10-digit format: 5551234567
 */
function cleanPhoneNumber($phone) {
    if (empty($phone)) {
        return '';
    }
    
    // Remove all non-digits
    $clean_phone = preg_replace('/\D/', '', $phone);
    
    // If it's 11 digits starting with 1, remove the 1 (US country code)
    if (strlen($clean_phone) === 11 && substr($clean_phone, 0, 1) === '1') {
        $clean_phone = substr($clean_phone, 1);
    }
    
    return $clean_phone;
}

/**
 * Format phone number for display (US format)
 * Converts 5551234567 to (555) 123-4567
 */
function formatPhoneNumber($phone) {
    if (empty($phone)) {
        return 'N/A';
    }
    
    // Clean the phone number first
    $clean_phone = cleanPhoneNumber($phone);
    
    // Format as (555) 123-4567
    if (strlen($clean_phone) === 10) {
        return sprintf('(%s) %s-%s', 
            substr($clean_phone, 0, 3),
            substr($clean_phone, 3, 3), 
            substr($clean_phone, 6, 4)
        );
    }
    
    return $phone; // Return original if can't format
}

/**
 * Alternative phone validation function with more detailed feedback
 * Returns array with 'valid' boolean and 'message' string
 */
function validatePhoneDetailed($phone) {
    if (empty($phone)) {
        return ['valid' => false, 'message' => 'Phone number is required'];
    }
    
    // Remove all non-digits
    $clean_phone = preg_replace('/\D/', '', $phone);
    
    if (strlen($clean_phone) < 10) {
        return ['valid' => false, 'message' => 'Phone number is too short'];
    } elseif (strlen($clean_phone) > 11) {
        return ['valid' => false, 'message' => 'Phone number is too long'];
    } elseif (strlen($clean_phone) === 10) {
        // 10-digit format
        if (!preg_match('/^[2-9]\d{9}$/', $clean_phone)) {
            return ['valid' => false, 'message' => 'Invalid area code (must start with 2-9)'];
        }
    } elseif (strlen($clean_phone) === 11) {
        // 11-digit format
        if (!preg_match('/^1[2-9]\d{9}$/', $clean_phone)) {
            return ['valid' => false, 'message' => 'Invalid format (must be 1 + valid US number)'];
        }
    }
    
    return ['valid' => true, 'message' => 'Valid phone number'];
}

/**
 * Validate password strength (ฟังก์ชันใหม่)
 */
function validatePassword($password) {
    // At least 8 characters
    if (strlen($password) < 8) {
        return false;
    }
    
    // Contains at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    
    // Contains at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    
    // Contains at least one number
    if (!preg_match('/\d/', $password)) {
        return false;
    }
    
    return true;
}

/**
 * Validate ZIP code (ฟังก์ชันใหม่)
 */
function validateZipCode($zip) {
    // Thai postal code is 5 digits
    return preg_match('/^\d{5}$/', $zip);
}


/**
 * ตรวจสอบว่าเป็น localhost หรือไม่
 */
function isLocalhost() {
    return in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) || 
           strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0;
}

/**
 * ส่งอีเมลรีเซ็ตรหัสผ่าน (สำหรับ forgot_password.php)
 */
function sendPasswordResetEmail($email, $firstName, $resetToken) {
    return sendPasswordResetEmailReal($email, $firstName, $resetToken);
}

/**
 * บันทึกอีเมลเป็นไฟล์ (สำหรับทดสอบ)
 */
function sendEmailForTesting($to, $subject, $body) {
    
    // สร้างโฟลเดอร์ test_emails
    $email_dir = __DIR__ . '/../test_emails';
    if (!is_dir($email_dir)) {
        mkdir($email_dir, 0755, true);
    }
    
    // สร้างชื่อไฟล์
    $filename = 'email_' . date('Y-m-d_H-i-s') . '_' . md5($to . time()) . '.html';
    $filepath = $email_dir . '/' . $filename;
    
    // เนื้อหาอีเมล
    $emailContent = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Test Email - {$subject}</title>
</head>
<body style='margin: 20px; background: #f5f5f5; font-family: Arial, sans-serif;'>
    <div style='background: #e8f5e8; padding: 20px; margin-bottom: 20px; border-left: 5px solid #28a745; border-radius: 5px;'>
        <h2>📧 Email Test Preview - Krua Thai</h2>
        <p><strong>To:</strong> {$to}</p>
        <p><strong>Subject:</strong> {$subject}</p>
        <p><strong>Generated:</strong> " . date('Y-m-d H:i:s', time() + 7*3600) . " (Thailand Time)</p> 
        <p><strong>Status:</strong> ✅ Email would be sent in production</p>
    </div>
    
    <div style='border: 1px solid #ddd; border-radius: 8px; overflow: hidden;'>
        {$body}
    </div>
</body>
</html>";
    
    // บันทึกไฟล์
    $saved = file_put_contents($filepath, $emailContent);
    
    if ($saved) {
        error_log("=== EMAIL SENT (TEST MODE) ===");
        error_log("To: " . $to);
        error_log("Subject: " . $subject);
error_log("View at: http://localhost/Krua_Thai1/KruaThai/test_emails/" . $filename);
        error_log("===============================");
        return true;
    }
    
    return false;
}

/**
 * Template อีเมลรีเซ็ตรหัสผ่าน
 */
function generatePasswordResetEmailTemplate($firstName, $resetLink) {
    return "
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Password Reset - Krua Thai</title>
</head>
<body style='margin: 0; padding: 0; background-color: #ece8e1; font-family: Arial, sans-serif;'>
    <div style='max-width: 600px; margin: 40px auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1);'>
        
        <!-- Header -->
        <div style='background: linear-gradient(135deg, #cf723a, #bd9379); padding: 40px 30px; text-align: center; color: white;'>
            <div style='width: 80px; height: 80px; background: white; border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center; font-size: 30px;'>
                🍜
            </div>
            <h1 style='margin: 0; font-size: 28px; font-weight: bold;'>Password Reset</h1>
            <p style='margin: 10px 0 0 0; opacity: 0.9;'>Krua Thai - Authentic Thai Meals</p>
        </div>
        
        <!-- Content -->
        <div style='padding: 40px 30px;'>
            <h2 style='color: #cf723a; margin-bottom: 20px;'>Hello {$firstName}! 👋</h2>
            
            <p style='color: #333; line-height: 1.6; margin-bottom: 20px;'>
                We received a request to reset your password for your Krua Thai account.
            </p>
            
            <p style='color: #333; line-height: 1.6; margin-bottom: 30px;'>
                Click the button below to create a new password:
            </p>
            
            <!-- Reset Button -->
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$resetLink}' 
                   style='background: linear-gradient(135deg, #cf723a, #bd9379); 
                          color: white; 
                          padding: 15px 30px; 
                          text-decoration: none; 
                          border-radius: 25px; 
                          display: inline-block; 
                          font-weight: bold;
                          box-shadow: 0 5px 15px rgba(207, 114, 58, 0.3);'>
                    🔒 Reset Password
                </a>
            </div>
            
            <!-- Warning -->
            <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 25px 0; border-radius: 5px;'>
                <h3 style='color: #856404; margin: 0 0 10px 0; font-size: 16px;'>⚠️ Important:</h3>
                <ul style='color: #856404; margin: 0; padding-left: 20px;'>
                    <li>This link expires in 1 hour</li>
                    <li>Can only be used once</li>
                    <li>If you didn't request this, ignore this email</li>
                </ul>
            </div>
            
            <p style='color: #666; font-size: 14px; margin-top: 25px;'>
                If the button doesn't work, copy this link: <br>
                <code style='background: #f8f9fa; padding: 5px; border-radius: 3px; word-break: break-all;'>{$resetLink}</code>
            </p>
        </div>
        
        <!-- Footer -->
        <div style='background: #f8f6f0; padding: 30px; text-align: center; color: #666; font-size: 14px;'>
            <p style='margin: 0 0 10px 0;'>With care ❤️<br><strong>Krua Thai Team</strong></p>
            <p style='margin: 0;'>© " . date('Y') . " Krua Thai. All rights reserved.</p>
        </div>
    </div>
</body>
</html>";
}

/**
 * Check if user is logged in (เพิ่มฟังก์ชันนี้ถ้ายังไม่มี)
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * sendEmail function (alias สำหรับ compatibility)
 */
function sendEmail($to, $subject, $body) {
    if (isLocalhost()) {
        return sendEmailForTesting($to, $subject, $body);
    } else {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: Krua Thai <noreply@kruathai.com>',
            'Reply-To: support@kruathai.com'
        ];
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
}

/**
 * generatePasswordResetEmail function (alias สำหรับ compatibility)
 */
function generatePasswordResetEmail($firstName, $resetLink) {
    return generatePasswordResetEmailTemplate($firstName, $resetLink);
}


?>