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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - Somdul Table</title>
    <meta name="robots" content="noindex, nofollow">
    
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

        /* CSS Custom Properties for Somdul Table Design System */
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #f8f9fa 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--text-dark);
            font-weight: 400;
        }

        .logout-container {
            background: var(--white);
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            box-shadow: var(--shadow-medium);
            max-width: 500px;
            width: 100%;
            animation: fadeIn 0.6s ease;
            border: 1px solid var(--border-light);
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
            background: linear-gradient(135deg, var(--curry), var(--brown));
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-soft);
            margin-bottom: 1rem;
            color: var(--white);
            font-size: 1.8rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
        }

        .logo-text {
            color: var(--curry);
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0;
            font-family: 'BaticaSans', sans-serif;
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
            color: var(--text-dark);
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 700;
            font-family: 'BaticaSans', sans-serif;
        }

        .message {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        .user-info {
            background: rgba(173, 184, 157, 0.1);
            border: 2px solid var(--sage);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .user-info h3 {
            color: var(--sage);
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
            font-family: 'BaticaSans', sans-serif;
            font-weight: 600;
        }

        .user-info p {
            color: var(--text-dark);
            margin: 0.3rem 0;
            font-family: 'BaticaSans', sans-serif;
        }

        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--curry), var(--brown));
            color: var(--white);
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
            margin: 0 0.5rem 0.5rem 0;
            box-shadow: var(--shadow-soft);
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
            box-shadow: none;
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .countdown {
            background: rgba(207, 114, 58, 0.1);
            color: var(--curry);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin: 1.5rem 0;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            border: 1px solid var(--curry);
        }

        .footer-info {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-light);
            color: var(--text-gray);
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .footer-info a {
            color: var(--curry);
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
            <div class="logo-icon">S</div>
            <h2 class="logo-text">Somdul Table</h2>
        </div>

        <!-- Success Message -->
        <div class="logout-icon">👋</div>
        <h1>Successfully Logged Out</h1>
        
        <div class="user-info">
            <h3>Thank you, <?php echo htmlspecialchars($user_name); ?>!</h3>
            <p>You have been successfully logged out</p>
            <p>Your data is secure and your account has been protected</p>
        </div>
        
        <p class="message">
            Thank you for using Somdul Table! We hope you enjoyed our authentic Thai restaurant management system. 
            Come back soon for more delicious and healthy Thai cuisine experiences.
        </p>

        <!-- Countdown -->
        <div class="countdown" id="countdown">
            🏠 Redirecting to home page in <span id="timer">5</span> seconds...
        </div>
        
        <div class="action-buttons">
            <a href="home2.php" class="btn">Back to Home</a>
            <a href="login.php" class="btn btn-secondary">Sign In Again</a>
        </div>

        <!-- Footer Information -->
        <div class="footer-info">
            <p><strong>Questions about your account?</strong></p>
            <p>📧 <a href="mailto:support@somdultable.com">support@somdultable.com</a></p>
            <p>📞 <a href="tel:+1234567890">+1 (234) 567-890</a></p>
            <p style="margin-top: 1rem; font-size: 0.85rem;">
                <a href="privacy.php">Privacy Policy</a> | 
                <a href="terms.php">Terms of Service</a> | 
                <a href="help.php">Help Center</a>
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
                countdownElement.innerHTML = '🏠 Redirecting...';
                window.location.href = 'home2.php';
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
            localStorage.removeItem('somdul_table_login_email');
            sessionStorage.clear();
        }

        console.log('Logout successful - session cleared');
    </script>
</body>
</html>