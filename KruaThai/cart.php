<?php
/**
 * Krua Thai - Universal Shopping Cart
 * File: cart.php
 * Description: Shopping cart that works for both guest users and logged-in members
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Utility Functions
class CartManager {
    
    public static function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    public static function formatPrice($price) {
        return '$' . number_format($price, 2);
    }
    
    public static function getCartItems() {
        // Initialize cart if doesn't exist
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        return $_SESSION['cart'];
    }
    
    public static function calculateCartTotals($cart_items) {
        $subtotal = 0;
        $total_items = 0;
        
        foreach ($cart_items as $item) {
            $item_total = ($item['base_price'] ?? 0) * ($item['quantity'] ?? 1);
            
            // Add extra charges
            if (isset($item['customizations'])) {
                if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                    $item_total += 2.99 * $item['quantity'];
                }
                if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                    $item_total += 1.99 * $item['quantity'];
                }
            }
            
            $subtotal += $item_total;
            $total_items += $item['quantity'];
        }
        
        $delivery_fee = $subtotal >= 25 ? 0 : 3.99;
        $tax_rate = 0.0825; // 8.25%
        $tax_amount = $subtotal * $tax_rate;
        $total = $subtotal + $delivery_fee + $tax_amount;
        
        return [
            'subtotal' => $subtotal,
            'delivery_fee' => $delivery_fee,
            'tax_amount' => $tax_amount,
            'total' => $total,
            'total_items' => $total_items
        ];
    }
    
    public static function removeFromCart($item_index) {
        if (isset($_SESSION['cart'][$item_index])) {
            unset($_SESSION['cart'][$item_index]);
            $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
            return true;
        }
        return false;
    }
    
    public static function updateQuantity($item_index, $new_quantity) {
        if (isset($_SESSION['cart'][$item_index])) {
            $quantity = max(1, min(10, intval($new_quantity)));
            $_SESSION['cart'][$item_index]['quantity'] = $quantity;
            return true;
        }
        return false;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];
    
    switch ($action) {
        case 'remove_item':
            $item_index = intval($_POST['item_index'] ?? -1);
            if (CartManager::removeFromCart($item_index)) {
                $response['success'] = true;
                $response['message'] = 'Item removed from cart';
                $response['cart_totals'] = CartManager::calculateCartTotals(CartManager::getCartItems());
            } else {
                $response['message'] = 'Item not found';
            }
            break;
            
        case 'update_quantity':
            $item_index = intval($_POST['item_index'] ?? -1);
            $quantity = intval($_POST['quantity'] ?? 1);
            if (CartManager::updateQuantity($item_index, $quantity)) {
                $response['success'] = true;
                $response['message'] = 'Quantity updated';
                $response['cart_totals'] = CartManager::calculateCartTotals(CartManager::getCartItems());
            } else {
                $response['message'] = 'Failed to update quantity';
            }
            break;
            
        case 'clear_cart':
            $_SESSION['cart'] = [];
            $response['success'] = true;
            $response['message'] = 'Cart cleared';
            $response['cart_totals'] = CartManager::calculateCartTotals([]);
            break;


                case 'prepare_guest_checkout':
        // Ensure cart is properly stored in session
        if (!empty($_SESSION['cart'])) {
            $response['success'] = true;
            $response['message'] = 'Cart prepared for guest checkout';
            $response['cart_items'] = count($_SESSION['cart']);
        } else {
            $response['message'] = 'Cart is empty';
        }
        break;
    }
    
    echo json_encode($response);
    exit;
}

// Get cart data
$is_logged_in = isset($_SESSION['user_id']);
$cart_items = CartManager::getCartItems();
$cart_totals = CartManager::calculateCartTotals($cart_items);
$is_empty = empty($cart_items);

// Get user info if logged in
$user = null;
if ($is_logged_in) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Continue without user data
    }
}

// Handle success message
$success_message = '';
if (isset($_GET['added']) && $_GET['added'] == '1') {
    $success_message = 'Item added to cart successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Krua Thai</title>
    <meta name="description" content="Review your selected Thai meal kits and proceed to checkout">
    
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

        /* CSS Custom Properties */
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
            --success-color: #27ae60;
            --error-color: #e74c3c;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'BaticaSans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background-color: #f8f9fa;
            font-weight: 400;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'BaticaSans', sans-serif;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-soft);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
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
            font-weight: 800;
            color: var(--curry);
            font-family: 'BaticaSans', sans-serif;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-gray);
            font-weight: 500;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .nav-links a:hover {
            color: var(--curry);
        }

        /* Main Content */
        .main-container {
            min-height: 100vh;
            padding: 2rem 0;
        }

        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        .page-subtitle {
            font-size: 1.1rem;
            color: var(--text-gray);
            font-family: 'BaticaSans', sans-serif;
        }

        /* Success Message */
        .success-message {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 2rem;
            text-align: center;
            font-family: 'BaticaSans', sans-serif;
        }

        /* Cart Layout */
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 3rem;
        }

        .cart-items {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            height: fit-content;
        }

        .cart-summary {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            position: sticky;
            top: 100px;
            height: fit-content;
        }

        /* Cart Items */
        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 1.5rem;
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, var(--curry), var(--brown));
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 2rem;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        .item-type {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .item-customizations {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 1rem;
        }

        .item-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quantity-btn {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border-light);
            background: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
        }

        .quantity-btn:hover {
            border-color: var(--curry);
            color: var(--curry);
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            padding: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .remove-btn {
            background: none;
            border: none;
            color: var(--error-color);
            cursor: pointer;
            font-size: 0.9rem;
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .remove-btn:hover {
            opacity: 0.7;
        }

        .item-price {
            text-align: right;
        }

        .item-unit-price {
            font-size: 0.9rem;
            color: var(--text-gray);
        }

        .item-total-price {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--curry);
            font-family: 'BaticaSans', sans-serif;
        }

        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
        }

        .empty-cart-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }

        .empty-cart h3 {
            margin-bottom: 1rem;
            color: var(--text-gray);
        }

        /* Cart Summary */
        .summary-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--text-dark);
            font-family: 'BaticaSans', sans-serif;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .summary-row.total {
            border-top: 2px solid var(--border-light);
            padding-top: 1rem;
            margin-top: 1.5rem;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .free-delivery {
            color: var(--success-color);
            font-weight: 500;
        }

        /* User Status */
        .user-status {
            background: var(--cream);
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 2rem;
        }

        .user-status.guest {
            border-left: 4px solid var(--curry);
        }

        .user-status.member {
            border-left: 4px solid var(--success-color);
        }

        .status-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-family: 'BaticaSans', sans-serif;
        }

        .status-description {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 1rem;
        }

        /* Buttons */
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            margin-bottom: 1rem;
        }

        .btn-primary {
            background: var(--curry);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--brown);
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: var(--curry);
            border: 2px solid var(--curry);
        }

        .btn-secondary:hover {
            background: var(--curry);
            color: var(--white);
        }

        .btn-outline {
            background: var(--white);
            color: var(--text-dark);
            border: 2px solid var(--border-light);
        }

        .btn-outline:hover {
            border-color: var(--curry);
            color: var(--curry);
        }

        .btn-danger {
            background: var(--error-color);
            color: var(--white);
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        /* Checkout Actions */
        .checkout-actions {
            margin-top: 2rem;
        }

        .benefits-list {
            background: rgba(207, 114, 58, 0.05);
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
        }

        .benefits-list h4 {
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: var(--curry);
        }

        .benefits-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .benefits-list li {
            font-size: 0.9rem;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
            padding-left: 1rem;
            position: relative;
        }

        .benefits-list li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: var(--success-color);
            font-weight: 600;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .cart-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .cart-summary {
                position: static;
                order: -1;
            }

            .cart-item {
                grid-template-columns: 80px 1fr;
                gap: 1rem;
            }

            .item-price {
                grid-column: span 2;
                text-align: left;
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid var(--border-light);
            }

            .nav-container {
                padding: 0 1rem;
            }

            .cart-container {
                padding: 0 1rem;
            }

            .page-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .cart-items,
            .cart-summary {
                padding: 1.5rem;
            }

            .item-controls {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }

        /* Loading states */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-light);
            border-top: 2px solid var(--curry);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
            <div class="nav-links">
                <a href="meal-kit.php">Meal Kits</a>
                <a href="menus.php">Ready Meals</a>
                <?php if ($is_logged_in): ?>
                    <a href="dashboard.php">My Account</a>
                    <a href="logout.php">Sign Out</a>
                <?php else: ?>
                    <a href="login.php">Sign In</a>
                    <a href="register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <div class="cart-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">🛒 Shopping Cart</h1>
                <p class="page-subtitle">Review your selected items and proceed to checkout</p>
            </div>

            <!-- Success Message -->
            <?php if ($success_message): ?>
                <div class="success-message" id="successMessage">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($is_empty): ?>
                <!-- Empty Cart -->
                <div class="cart-items">
                    <div class="empty-cart">
                        <div class="empty-cart-icon">🛒</div>
                        <h3>Your cart is empty</h3>
                        <p>Looks like you haven't added any delicious Thai meal kits yet!</p>
                        <div style="margin-top: 2rem;">
                            <a href="meal-kit.php" class="btn btn-primary" style="width: auto; display: inline-flex;">
                                Browse Meal Kits
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Cart with Items -->
                <div class="cart-layout">
                    <!-- Cart Items -->
                    <div class="cart-items">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                            <h2 style="margin: 0;">Cart Items (<?php echo $cart_totals['total_items']; ?>)</h2>
                            <button type="button" class="btn btn-danger" style="width: auto; padding: 0.5rem 1rem; font-size: 0.9rem;" onclick="clearCart()">
                                Clear Cart
                            </button>
                        </div>

                        <?php foreach ($cart_items as $index => $item): ?>
                            <div class="cart-item" data-index="<?php echo $index; ?>">
                                <div class="item-image">
                                    🍛
                                </div>
                                
                                <div class="item-details">
                                    <h3 class="item-name"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <div class="item-type">
                                        <?php echo htmlspecialchars(ucfirst($item['type'] ?? 'meal_kit')); ?>
                                        <?php if (isset($item['category'])): ?>
                                            • <?php echo htmlspecialchars($item['category']); ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($item['customizations'])): ?>
                                        <div class="item-customizations">
                                            <?php
                                            $customs = [];
                                            if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                                                $customs[] = 'Extra Protein (+$2.99)';
                                            }
                                            if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                                                $customs[] = 'Extra Vegetables (+$1.99)';
                                            }
                                            if (!empty($customs)) {
                                                echo implode(', ', $customs);
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($item['special_requests'])): ?>
                                        <div class="item-customizations">
                                            Note: <?php echo htmlspecialchars($item['special_requests']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="item-controls">
                                        <div class="quantity-control">
                                            <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $index; ?>, <?php echo max(1, $item['quantity'] - 1); ?>)">-</button>
                                            <input type="number" class="quantity-input" value="<?php echo $item['quantity']; ?>" 
                                                   min="1" max="10" onchange="updateQuantity(<?php echo $index; ?>, this.value)">
                                            <button type="button" class="quantity-btn" onclick="updateQuantity(<?php echo $index; ?>, <?php echo min(10, $item['quantity'] + 1); ?>)">+</button>
                                        </div>
                                        <button type="button" class="remove-btn" onclick="removeItem(<?php echo $index; ?>)">
                                            🗑️ Remove
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="item-price">
                                    <div class="item-unit-price">
                                        <?php echo CartManager::formatPrice($item['base_price']); ?> each
                                    </div>
                                    <div class="item-total-price">
                                        <?php 
                                        $item_total = $item['base_price'] * $item['quantity'];
                                        if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                                            $item_total += 2.99 * $item['quantity'];
                                        }
                                        if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                                            $item_total += 1.99 * $item['quantity'];
                                        }
                                        echo CartManager::formatPrice($item_total);
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Cart Summary -->
                    <div class="cart-summary">
                        <!-- User Status -->
                        <div class="user-status <?php echo $is_logged_in ? 'member' : 'guest'; ?>">
                            <?php if ($is_logged_in): ?>
                                <div class="status-title">👤 Welcome back, <?php echo htmlspecialchars($user['first_name'] ?? 'Member'); ?>!</div>
                                <div class="status-description">You're signed in and ready for faster checkout with saved addresses and payment methods.</div>
                            <?php else: ?>
                                <div class="status-title">👋 Shopping as Guest</div>
                                <div class="status-description">Create an account for faster checkout, order tracking, and exclusive member benefits.</div>
                                <a href="register.php" class="btn btn-secondary" style="width: auto; margin: 0;">Create Account</a>
                            <?php endif; ?>
                        </div>

                        <h3 class="summary-title">Order Summary</h3>
                        
                        <div class="summary-row">
                            <span>Subtotal (<?php echo $cart_totals['total_items']; ?> items):</span>
                            <span id="subtotal"><?php echo CartManager::formatPrice($cart_totals['subtotal']); ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Delivery Fee:</span>
                            <span id="delivery-fee" class="<?php echo $cart_totals['delivery_fee'] == 0 ? 'free-delivery' : ''; ?>">
                                <?php 
                                if ($cart_totals['delivery_fee'] == 0) {
                                    echo 'FREE';
                                } else {
                                    echo CartManager::formatPrice($cart_totals['delivery_fee']);
                                }
                                ?>
                            </span>
                        </div>
                        
                        <?php if ($cart_totals['subtotal'] < 25 && $cart_totals['delivery_fee'] > 0): ?>
                            <div style="font-size: 0.9rem; color: var(--text-gray); margin-bottom: 1rem;">
                                💡 Add <?php echo CartManager::formatPrice(25 - $cart_totals['subtotal']); ?> more for free delivery!
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-row">
                            <span>Tax (8.25%):</span>
                            <span id="tax"><?php echo CartManager::formatPrice($cart_totals['tax_amount']); ?></span>
                        </div>
                        
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span id="total"><?php echo CartManager::formatPrice($cart_totals['total']); ?></span>
                        </div>

                        <?php if ($is_logged_in): ?>
                            <!-- Member Benefits -->
                            <div class="benefits-list">
                                <h4>🎉 Member Benefits:</h4>
                                <ul>
                                    <li>Saved payment methods</li>
                                    <li>Order history & tracking</li>
                                    <li>Exclusive member discounts</li>
                                    <li>Priority customer support</li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <!-- Guest Benefits -->
                            <div class="benefits-list">
                                <h4>✨ Why create an account?</h4>
                                <ul>
                                    <li>Faster future checkouts</li>
                                    <li>Order tracking & history</li>
                                    <li>Exclusive member-only deals</li>
                                    <li>Personalized meal recommendations</li>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Checkout Actions -->
                        <div class="checkout-actions">
                            <?php if ($is_logged_in): ?>
                                <!-- Logged-in user checkout -->
                                <a href="guest-checkout.php" class="btn btn-primary">
                                    🔒 Proceed to Checkout
                                </a>
                            <?php else: ?>
                                <!-- Guest checkout options -->
                                <a href="javascript:void(0)" onclick="proceedAsGuest()" class="btn btn-primary">
                                    🚀 Continue as Guest
                                </a>
                                <a href="login.php" class="btn btn-secondary">
                                    👤 Sign In for Faster Checkout
                                </a>
                            <?php endif; ?>
                            
                            <a href="meal-kit.php" class="btn btn-outline">
                                ← Continue Shopping
                            </a>
                        </div>

                        <!-- Security Badge -->
                        <div style="text-align: center; margin-top: 2rem; font-size: 0.9rem; color: var(--text-gray);">
                            🔒 Secure checkout • SSL encrypted<br>
                            💳 We accept all major credit cards
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 1rem; text-align: center;">
            <div class="spinner" style="display: inline-block; margin-bottom: 1rem;"></div>
            <div>Processing...</div>
        </div>
    </div>

    <script>
        // Remove item from cart
        function removeItem(itemIndex) {
            if (confirm('Remove this item from your cart?')) {
                showLoading(true);
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=remove_item&item_index=${itemIndex}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload page to update cart
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        showLoading(false);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while removing the item.');
                    showLoading(false);
                });
            }
        }

        // Update item quantity
        function updateQuantity(itemIndex, newQuantity) {
            newQuantity = Math.max(1, Math.min(10, parseInt(newQuantity)));
            
            showLoading(true);
            startLoadingTimeout(); // Safety net
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_quantity&item_index=${itemIndex}&quantity=${newQuantity}`
            })
            .then(response => response.json())
            .then(data => {
                hideLoading(); // Force hide loading
                if (data.success) {
                    // Update the UI without full reload
                    updateCartTotals(data.cart_totals);
                    // Update the quantity input
                    const quantityInput = document.querySelector(`[data-index="${itemIndex}"] .quantity-input`);
                    if (quantityInput) {
                        quantityInput.value = newQuantity;
                    }
                    // Update item total price display
                    window.location.reload(); // Temporary fix - reload to ensure consistency
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                hideLoading(); // Force hide loading
                console.error('Error:', error);
                alert('An error occurred while updating quantity.');
            });
        }

        // Clear entire cart
        function clearCart() {
            if (confirm('Are you sure you want to clear your entire cart?')) {
                showLoading(true);
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=clear_cart'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        showLoading(false);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while clearing the cart.');
                    showLoading(false);
                });
            }
        }

 // Proceed as guest
function proceedAsGuest() {
    // Show loading state
    showLoading(true);
    
    // Get current cart from PHP (already available)
    const cartData = <?php echo json_encode($cart_items); ?>;
    const cartTotals = <?php echo json_encode($cart_totals); ?>;
    
    // Validate cart is not empty
    if (!cartData || cartData.length === 0) {
        alert('Your cart is empty. Please add items first.');
        showLoading(false);
        return;
    }
    
    // Store cart data in PHP session via AJAX
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=prepare_guest_checkout'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect to guest checkout with cart source
            window.location.href = 'guest-checkout.php?source=cart';
        } else {
            alert('Error preparing checkout: ' + data.message);
            showLoading(false);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while preparing checkout.');
        showLoading(false);
    });
}

        // Update cart totals display
        function updateCartTotals(totals) {
            document.getElementById('subtotal').textContent = '$' + totals.subtotal.toFixed(2);
            document.getElementById('tax').textContent = '$' + totals.tax_amount.toFixed(2);
            document.getElementById('total').textContent = '$' + totals.total.toFixed(2);
            
            const deliveryFeeElement = document.getElementById('delivery-fee');
            if (totals.delivery_fee === 0) {
                deliveryFeeElement.textContent = 'FREE';
                deliveryFeeElement.className = 'free-delivery';
            } else {
                deliveryFeeElement.textContent = '$' + totals.delivery_fee.toFixed(2);
                deliveryFeeElement.className = '';
            }
        }

        // Show/hide loading state
        function showLoading(show) {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                if (show) {
                    overlay.style.display = 'flex';
                } else {
                    overlay.style.display = 'none';
                }
            }
        }

        // Debug function to force hide loading
        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }

        // Auto-hide loading after 5 seconds (safety net)
        function startLoadingTimeout() {
            setTimeout(() => {
                hideLoading();
                console.log('Loading timeout - force hide loading overlay');
            }, 5000);
        }

        // Auto-hide success message and check for loading issues
        document.addEventListener('DOMContentLoaded', function() {
            // Force hide loading overlay on page load
            hideLoading();
            
            const successMessage = document.getElementById('successMessage');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => {
                        successMessage.style.display = 'none';
                    }, 300);
                }, 3000);
            }

            // Handle quantity input changes
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', function() {
                    const itemIndex = this.closest('.cart-item').dataset.index;
                    updateQuantity(itemIndex, this.value);
                });

                // Prevent invalid input
                input.addEventListener('input', function() {
                    let value = parseInt(this.value);
                    if (isNaN(value) || value < 1) {
                        this.value = 1;
                    } else if (value > 10) {
                        this.value = 10;
                    }
                });
            });

            // Add click handler to hide loading if user clicks anywhere
            document.addEventListener('click', function(e) {
                if (e.target.id === 'loadingOverlay') {
                    hideLoading();
                }
            });

            // Add escape key to hide loading
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    hideLoading();
                }
            });
        });

        // Handle browser back button
        window.addEventListener('popstate', function(event) {
            // Refresh cart when user navigates back
            window.location.reload();
        });

        // Prevent double submissions
        let isSubmitting = false;
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (isSubmitting && this.href && this.href.includes('checkout')) {
                    e.preventDefault();
                    return false;
                }
                if (this.href && this.href.includes('checkout')) {
                    isSubmitting = true;
                }
            });
        });

        // Update cart counter in navigation (if exists)
        function updateCartCounter() {
            const cartCounter = document.querySelector('.cart-counter');
            if (cartCounter) {
                cartCounter.textContent = <?php echo $cart_totals['total_items']; ?>;
                if (<?php echo $cart_totals['total_items']; ?> > 0) {
                    cartCounter.style.display = 'inline-block';
                } else {
                    cartCounter.style.display = 'none';
                }
            }
        }

        // Initialize
        updateCartCounter();
    </script>
</body>
</html>