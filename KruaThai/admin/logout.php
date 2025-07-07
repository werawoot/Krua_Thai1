<?php
/**
 * Simple Logout Handler
 * File: logout.php
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Store user info for display
$user_name = $_SESSION['user_name'] ?? 'User';
$user_email = $_SESSION['user_email'] ?? '';

// Log activity if functions exist
if (function_exists('logActivity')) {
    require_once 'includes/functions.php';
    logActivity('user_logout', $_SESSION['user_id'], $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', [
        'email' => $user_email,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Clear session
$_SESSION = array();

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ออกจากระบบ - Krua Thai</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5ede4 0%, #f9f5ed 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #3d4028;
        }

        .logout-container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 8px 30px rgba(61, 64, 40, 0.1);
            max-width: 500px;
            width: 100%;
            animation: fadeIn 0.6s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-section {
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #ffffff, #d1b990);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(61, 64, 40, 0.1);
            margin-bottom: 1rem;
        }

        .rice-grain {
            width: 12px;
            height: 20px;
            background: linear-gradient(180deg, #3d4028, #4e4f22);
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            transform: rotate(-3deg);
        }

        .logo-text {
            color: #3d4028;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }

        .logout-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            animation: wave 2s ease-in-out infinite;
        }

        @keyframes wave {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(10deg); }
            75% { transform: rotate(-10deg); }
        }

        h1 {
            color: #3d4028;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .message {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
            color: #6c757d;
        }

        .user-info {
            background: rgba(40, 167, 69, 0.1);
            border: 2px solid #28a745;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .user-info h3 {
            color: #28a745;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .user-info p {
            color: #3d4028;
            margin: 0.3rem 0;
        }

        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(45deg, #866028, #a67c00);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 0 0.5rem 0.5rem 0;
            box-shadow: 0 4px 15px rgba(134, 96, 40, 0.3);
        }

        .btn:hover {
            background: linear-gradient(45deg, #a67c00, #866028);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(134, 96, 40, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: #866028;
            border: 2px solid #866028;
            box-shadow: none;
        }

        .btn-secondary:hover {
            background: #866028;
            color: white;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .countdown {
            background: #cce7ff;
            color: #004085;
            padding: 1rem;
            border-radius: 10px;
            margin: 1.5rem 0;
            font-weight: 600;
        }

        .footer-info {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .footer-info a {
            color: #866028;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-info a:hover {
            text-decoration: underline;
        }

        @media (max-width: 600px) {
            .logout-container {
                padding: 2rem 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo-icon">
                <div class="rice-grain"></div>
            </div>
            <h2 class="logo-text">Krua Thai</h2>
        </div>

        <!-- Success Message -->
        <div class="logout-icon">👋</div>
        <h1>ออกจากระบบสำเร็จ</h1>
        
        <div class="user-info">
            <h3>ขอบคุณ, <?php echo htmlspecialchars($user_name); ?>!</h3>
            <p>คุณได้ออกจากระบบเรียบร้อยแล้ว</p>
            <p>ข้อมูลของคุณปลอดภัยและบัญชีของคุณได้รับการรักษาความปลอดภัย</p>
        </div>
        
        <p class="message">
            ขอบคุณที่ใช้บริการ Krua Thai! เราหวังว่าคุณจะชอบอาหารไทยเพื่อสุขภาพของเรา 
            กลับมาใช้บริการอีกเร็วๆ นี้สำหรับอาหารที่อร่อยและมีประโยชน์มากขึ้น
        </p>

        <!-- Countdown -->
        <div class="countdown" id="countdown">
            🏠 กำลังเปลี่ยนไปหน้าแรกใน <span id="timer">5</span> วินาที...
        </div>
        
        <div class="action-buttons">
            <a href="index.php" class="btn">กลับหน้าแรก</a>
            <a href="login.php" class="btn-secondary">เข้าสู่ระบบอีกครั้ง</a>
        </div>

        <!-- Footer Information -->
        <div class="footer-info">
            <p><strong>มีคำถามเกี่ยวกับบัญชีของคุณ?</strong></p>
            <p>📧 <a href="mailto:support@kruathai.com">support@kruathai.com</a></p>
            <p>📞 <a href="tel:021234567">02-123-4567</a></p>
            <p style="margin-top: 1rem; font-size: 0.85rem;">
                <a href="privacy.php">นโยบายความเป็นส่วนตัว</a> | 
                <a href="terms.php">ข้อตกลงการใช้งาน</a> | 
                <a href="help.php">ศูนย์ช่วยเหลือ</a>
            </p>
        </div>
    </div>

    <script>
        // Auto redirect with countdown
        let countdown = 5;
        const timerElement = document.getElementById('timer');
        const countdownElement = document.getElementById('countdown');
        
        const countdownInterval = setInterval(() => {
            countdown--;
            timerElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                countdownElement.innerHTML = '🏠 กำลังเปลี่ยนหน้า...';
                window.location.href = 'index.php';
            }
        }, 1000);

        // Allow user to cancel auto-redirect by interacting with page
        let userInteracted = false;
        
        document.addEventListener('click', () => {
            if (!userInteracted) {
                userInteracted = true;
                clearInterval(countdownInterval);
                countdownElement.style.display = 'none';
            }
        });

        document.addEventListener('keydown', () => {
            if (!userInteracted) {
                userInteracted = true;
                clearInterval(countdownInterval);
                countdownElement.style.display = 'none';
            }
        });

        // Prevent back button
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };

        // Clear any remaining data
        if (typeof Storage !== "undefined") {
            localStorage.removeItem('krua_thai_login_email');
            sessionStorage.clear();
        }

        console.log('Logout successful - session cleared');
    </script>
</body>
</html>