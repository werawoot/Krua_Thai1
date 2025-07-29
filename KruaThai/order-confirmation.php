<?php
/**
 * Krua Thai - Order Confirmation Page
 * File: order-confirmation.php
 * Description: Professional order success page with order details
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Security: Check if user just placed an order
if (!isset($_SESSION['order_success']) || !isset($_GET['order'])) {
    header('Location: meal-kit.php');
    exit;
}

$order_number = $_SESSION['order_success']['order_number'] ?? '';
$order_total = $_SESSION['order_success']['total'] ?? 0;
$order_items = $_SESSION['order_success']['items'] ?? [];
$customer_email = $_SESSION['order_success']['email'] ?? '';
$delivery_date = $_SESSION['order_success']['delivery_date'] ?? '';
$checkout_source = $_SESSION['order_success']['source'] ?? 'single';

// Clear the success session to prevent refresh
unset($_SESSION['order_success']);

// Helper function to format price
function formatPrice($price) {
    return '$' . number_format($price, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed - Krua Thai</title>
    <meta name="description" content="Your Thai meal kit order has been confirmed and will be delivered soon!">
    
    <!-- BaticaSans Font -->
    <link rel="preconnect" href="https://ydpschool.com">
    <style>
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Regular.woff2') format('woff2');
            font-weight: 400;
            font-display: swap;
        }
        
        @font-face {
            font-family: 'BaticaSans';
            src: url('https://ydpschool.com/fonts/BaticaSans-Bold.woff2') format('woff2');
            font-weight: 700;
            font-display: swap;
        }

        :root {
            --curry: #cf723a;
            --brown: #bd9379;
            --cream: #ece8e1;
            --sage: #adb89d;
            --white: #ffffff;
            --text-dark: #2c3e50;
            --text-gray: #7f8c8d;
            --success: #27ae60;
            --shadow: 0 8px 32px rgba(189, 147, 121, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: linear-gradient(135deg, #f8f9fa 0%, var(--cream) 100%);
            min-height: 100vh;
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            box-shadow: var(--shadow);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
            color: var(--text-dark);
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--curry);
        }

        /* Main Content */
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .success-card {
            background: var(--white);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: var(--shadow);
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .success-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--curry), var(--sage));
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--success), #2ecc71);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 3rem;
            color: var(--white);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .success-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .success-subtitle {
            font-size: 1.2rem;
            color: var(--text-gray);
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        /* Order Details */
        .order-details {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .detail-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .detail-label {
            color: var(--text-gray);
            font-weight: 500;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-dark);
        }

        /* Order Items */
        .order-items {
            background: var(--white);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .items-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
        }

        .item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, var(--curry), var(--brown));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .item-meta {
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        .item-price {
            font-weight: 700;
            color: var(--curry);
            font-size: 1.1rem;
        }

        /* Next Steps */
        .next-steps {
            background: var(--cream);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .steps-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .step {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .step-number {
            width: 30px;
            height: 30px;
            background: var(--curry);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .step-content {
            flex: 1;
        }

        .step-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .step-description {
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        /* Action Buttons */
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 160px;
            justify-content: center;
        }

        .btn-primary {
            background: var(--curry);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--brown);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(207, 114, 58, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
            transform: translateY(-2px);
        }

        /* Support Info */
        .support-info {
            text-align: center;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 12px;
            margin-top: 2rem;
        }

        .support-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .support-text {
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .success-card {
                padding: 2rem 1.5rem;
            }

            .success-title {
                font-size: 2rem;
            }

            .actions {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 280px;
            }

            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
        }

        /* Print Styles */
        @media print {
            .navbar, .actions, .support-info {
                display: none;
            }
            
            body {
                background: white;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="home2.php" class="logo">
                <img src="./assets/image/LOGO_BG.png" alt="Krua Thai" style="height: 40px; width: auto;">
                <span class="logo-text">Krua Thai</span>
            </a>
        </div>
    </nav>

    <div class="container">
        <!-- Success Message -->
        <div class="success-card">
            <div class="success-icon">✓</div>
            <h1 class="success-title">Order Confirmed!</h1>
            <p class="success-subtitle">
                Thank you for choosing Krua Thai! Your delicious Thai meal kit<?php echo count($order_items) > 1 ? 's' : ''; ?> 
                <?php echo count($order_items) > 1 ? 'are' : 'is'; ?> being prepared and will be delivered fresh to your door.
            </p>
        </div>

        <!-- Order Details -->
        <div class="order-details">
            <div class="detail-row">
                <span class="detail-label">📋 Order Number</span>
                <span class="detail-value"><?php echo htmlspecialchars($order_number); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">📧 Email Confirmation</span>
                <span class="detail-value"><?php echo htmlspecialchars($customer_email); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">🚚 Delivery Date</span>
                <span class="detail-value"><?php echo $delivery_date ? date('l, F j, Y', strtotime($delivery_date)) : 'To be scheduled'; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">🍛 Items</span>
                <span class="detail-value"><?php echo count($order_items); ?> meal kit<?php echo count($order_items) > 1 ? 's' : ''; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">💰 Total Amount</span>
                <span class="detail-value" style="color: var(--curry); font-size: 1.2rem;"><?php echo formatPrice($order_total); ?></span>
            </div>
        </div>

        <!-- Order Items -->
        <?php if (!empty($order_items)): ?>
        <div class="order-items">
            <h2 class="items-title">Your Order</h2>
            <?php foreach ($order_items as $item): ?>
            <div class="item">
                <div class="item-image">🍛</div>
                <div class="item-details">
                    <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                    <div class="item-meta">
                        <?php if (isset($item['prep_time'])): ?>
                            ⏱️ <?php echo $item['prep_time']; ?> mins prep time •
                        <?php endif; ?>
                        <?php if (isset($item['serves'])): ?>
                            👥 Serves <?php echo htmlspecialchars($item['serves']); ?> •
                        <?php endif; ?>
                        <?php if (isset($item['spice_level'])): ?>
                            🌶️ <?php echo htmlspecialchars($item['spice_level']); ?> spice
                        <?php endif; ?>
                        <?php if (isset($item['quantity']) && $item['quantity'] > 1): ?>
                            <br>Quantity: <?php echo $item['quantity']; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="item-price">
                    <?php echo formatPrice($item['base_price'] * ($item['quantity'] ?? 1)); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Next Steps -->
        <div class="next-steps">
            <h2 class="steps-title">What Happens Next?</h2>
            
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <div class="step-title">📧 Email Confirmation</div>
                    <div class="step-description">You'll receive a detailed confirmation email with your order information and tracking details.</div>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <div class="step-title">👨‍🍳 Kitchen Preparation</div>
                    <div class="step-description">Our Thai chefs will prepare your fresh ingredients and authentic curry pastes with care.</div>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <div class="step-title">📦 Packaging & Quality Check</div>
                    <div class="step-description">Everything is carefully packaged with ice packs to maintain freshness during delivery.</div>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">4</div>
                <div class="step-content">
                    <div class="step-title">🚚 Delivery</div>
                    <div class="step-description">Your meal kit will be delivered on your chosen date. No signature required - we'll leave it safely at your door.</div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="actions">
            <a href="meal-kit.php" class="btn btn-secondary">
                🛒 Order More Kits
            </a>
            <a href="register.php" class="btn btn-primary">
                👤 Create Account
            </a>
        </div>

        <!-- Support Information -->
        <div class="support-info">
            <div class="support-title">Need Help?</div>
            <div class="support-text">
                Contact our friendly customer support team at <strong>support@kruathai.com</strong> or call <strong>(555) 123-THAI</strong>
                <br>We're here to help 7 days a week, 8 AM - 8 PM
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll to top
        window.scrollTo(0, 0);

        // Print order functionality
        function printOrder() {
            window.print();
        }

        // Prevent back button after successful order
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };

        // Track conversion for analytics (if needed)
        if (typeof gtag !== 'undefined') {
            gtag('event', 'purchase', {
                'transaction_id': '<?php echo htmlspecialchars($order_number); ?>',
                'value': <?php echo $order_total; ?>,
                'currency': 'USD',
                'items': <?php echo json_encode($order_items); ?>
            });
        }

        // Auto-redirect to dashboard after 30 seconds (optional)
        // setTimeout(() => {
        //     if (confirm('Would you like to create an account to track your orders?')) {
        //         window.location.href = 'register.php';
        //     }
        // }, 30000);
    </script>
</body>
</html>