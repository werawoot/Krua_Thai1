<?php
/**
 * Somdul Table - Product Checkout Page (BINARY COLUMN SAFE)
 * File: product-checkout.php
 * Description: Checkout page for individual product purchases (separate from meal subscriptions)
 * UPDATED: Now redirects to product-order-status.php after successful order
 * FIXED: Handles both collation mismatch and binary column errors
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Utility Functions
class ProductCheckoutUtils {
    
    public static function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    public static function generateOrderNumber() {
        return 'PRD-' . date('Ymd-His') . '-' . substr(self::generateUUID(), 0, 6);
    }
    
    public static function sanitizeInput($input) {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) === 10 || strlen($phone) === 11;
    }
    
    public static function formatPrice($price) {
        return '$' . number_format($price, 2);
    }
    
    public static function calculateShipping($subtotal, $state = 'CA') {
        // Simple shipping calculation
        if ($subtotal >= 50) {
            return 0.00; // Free shipping over $50
        }
        return 7.99; // Standard shipping
    }
    
    public static function calculateTax($subtotal, $state = 'CA') {
        // Simple tax calculation (CA sales tax)
        $tax_rates = [
            'CA' => 0.0875, // 8.75%
            'NY' => 0.08,   // 8%
            'TX' => 0.0625, // 6.25%
            'FL' => 0.06,   // 6%
        ];
        
        $rate = $tax_rates[$state] ?? 0.05; // Default 5%
        return $subtotal * $rate;
    }
}

// Database Connection with Conservative Collation Fix
function getDatabaseConnection() {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // CONSERVATIVE FIX: Only set connection charset, no forced collation
        $pdo->exec("SET NAMES utf8mb4");
        
        return $pdo;
        
    } catch (Exception $e) {
        // Fallback connections with minimal charset settings
        $configs = [
            ["mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root"],
            ["mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root"]
        ];
        
        foreach ($configs as $config) {
            try {
                $pdo = new PDO($config[0], $config[1], $config[2], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                
                // Only set charset, avoid forcing collations
                $pdo->exec("SET NAMES utf8mb4");
                
                return $pdo;
                
            } catch (PDOException $e) {
                continue;
            }
        }
        
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

// Initialize variables
$selected_product = null;
$cart_items = [];
$checkout_action = '';
$errors = [];
$success = false;
$order_id = null;
$user = null;
$is_cart_checkout = false;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $product_id = $_GET['product'] ?? '';
    $source = $_GET['source'] ?? '';
    
    if ($source === 'cart') {
        $redirect_url = 'product-checkout.php?source=cart';
    } else {
        $redirect_url = 'product-checkout.php?product=' . urlencode($product_id);
    }
    
    header('Location: login.php?redirect=' . urlencode($redirect_url));
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle different entry points - FIXED VERSION
$source = trim(strtolower($_GET['source'] ?? ''));
$product_id = trim($_GET['product'] ?? '');

// Check if this is a cart checkout
$is_cart_checkout = false;
$cart_items = [];

// More robust cart detection
if ($source === 'cart' || (isset($_SESSION['cart']) && !empty($_SESSION['cart']) && empty($product_id))) {
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Check if cart has items - redirect to cart page, not product page
    if (empty($_SESSION['cart'])) {
        $_SESSION['flash_message'] = 'Your cart is empty. Please add items before checkout.';
        $_SESSION['flash_type'] = 'error';
        header('Location: cart.php');
        exit;
    }
    
    $is_cart_checkout = true;
    $cart_items = $_SESSION['cart'];
    
} elseif (!empty($product_id)) {
    // Direct product purchase
    $is_cart_checkout = false;
    
} else {
    // Neither cart nor direct product - unclear intent
    // Check if there's anything in the cart first
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        // Has cart items, probably meant to checkout cart
        $_SESSION['flash_message'] = 'Redirected to cart checkout.';
        $_SESSION['flash_type'] = 'info';
        header('Location: product-checkout.php?source=cart');
        exit;
    } else {
        // No cart items and no product specified - go to products page
        $_SESSION['flash_message'] = 'Please select a product to checkout.';
        $_SESSION['flash_type'] = 'info';
        header('Location: product.php');
        exit;
    }
}

// Debug logging (remove in production)
error_log("Product Checkout Debug - Source: '$source', Product ID: '$product_id', Is Cart: " . ($is_cart_checkout ? 'Yes' : 'No'));

try {
    // Get database connection with conservative collation fix
    $pdo = getDatabaseConnection();
    
    // Get user information - NO COLLATE clause to avoid binary column issues
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found. Please log in again.");
    }
    
} catch (Exception $e) {
    $errors[] = "Database error: " . $e->getMessage();
    error_log("Product checkout database error: " . $e->getMessage());
}

// Handle single product checkout
if (!$is_cart_checkout && !empty($product_id)) {
    // Fetch selected product - NO COLLATE clause to avoid binary column issues
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
        $stmt->execute([$product_id]);
        $selected_product = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Product fetch error: " . $e->getMessage());
        
        // Fallback products if database table doesn't exist or has issues
        $fallback_products = [
            'pad-thai-kit-pro' => [
                'id' => 'pad-thai-kit-pro',
                'name' => 'Premium Pad Thai Kit',
                'description' => 'Complete authentic Pad Thai kit with premium ingredients.',
                'price' => 24.99,
                'category' => 'meal-kit',
                'stock_quantity' => 50
            ],
            'tom-yum-paste-authentic' => [
                'id' => 'tom-yum-paste-authentic',
                'name' => 'Authentic Tom Yum Paste',
                'description' => 'Traditional Tom Yum paste made with fresh ingredients.',
                'price' => 12.99,
                'category' => 'sauce',
                'stock_quantity' => 100
            ],
            'thai-curry-kit-trio' => [
                'id' => 'thai-curry-kit-trio',
                'name' => 'Thai Curry Kit Trio',
                'description' => 'Three authentic curry pastes: Red, Green, and Yellow.',
                'price' => 34.99,
                'category' => 'meal-kit',
                'stock_quantity' => 30
            ],
            'fish-sauce-premium' => [
                'id' => 'fish-sauce-premium',
                'name' => 'Premium Fish Sauce',
                'description' => 'Artisanal fish sauce aged for 2 years.',
                'price' => 18.99,
                'category' => 'sauce',
                'stock_quantity' => 75
            ]
        ];
        
        $selected_product = $fallback_products[$product_id] ?? null;
    }

    if (!$selected_product) {
        header('Location: product.php');
        exit;
    }
}

// Handle form submission BEFORE any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']) && empty($errors)) {
    
    // Validate form input
    $shipping_address = ProductCheckoutUtils::sanitizeInput($_POST['shipping_address'] ?? '');
    $shipping_city = ProductCheckoutUtils::sanitizeInput($_POST['shipping_city'] ?? '');
    $shipping_state = ProductCheckoutUtils::sanitizeInput($_POST['shipping_state'] ?? '');
    $shipping_zip = ProductCheckoutUtils::sanitizeInput($_POST['shipping_zip'] ?? '');
    $payment_method = ProductCheckoutUtils::sanitizeInput($_POST['payment_method'] ?? '');
    
    // Validation
    if (empty($shipping_address)) $errors[] = "Please enter your shipping address";
    if (empty($shipping_city)) $errors[] = "Please enter your city";
    if (empty($shipping_state)) $errors[] = "Please select your state";
    if (empty($shipping_zip)) $errors[] = "Please enter your ZIP code";
    if (empty($payment_method)) $errors[] = "Please select a payment method";
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Calculate order totals
            $subtotal = 0;
            
            if ($is_cart_checkout) {
                // Calculate cart totals
                foreach ($cart_items as $item) {
                    $quantity = intval($item['quantity']);
                    $price = floatval($item['base_price']);
                    $item_total = $price * $quantity;
                    
                    // Add customization costs
                    if (isset($item['customizations'])) {
                        if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                            $item_total += 2.99 * $quantity;
                        }
                        if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                            $item_total += 1.99 * $quantity;
                        }
                    }
                    
                    $subtotal += $item_total;
                }
            } else {
                // Single product
                $quantity = max(1, intval($_POST['quantity'] ?? 1));
                $subtotal = $selected_product['price'] * $quantity;
            }
            
            $shipping_cost = ProductCheckoutUtils::calculateShipping($subtotal, $shipping_state);
            $tax_amount = ProductCheckoutUtils::calculateTax($subtotal, $shipping_state);
            $total_amount = $subtotal + $shipping_cost + $tax_amount;
            
            // Generate order details
            $order_id = ProductCheckoutUtils::generateUUID();
            $order_number = ProductCheckoutUtils::generateOrderNumber();
            
            // Check if product_orders table exists, if not create a simple order log
            try {
                // Try to insert into product_orders table
                $stmt = $pdo->prepare("
                    INSERT INTO product_orders (
                        id, order_number, user_id, customer_email, customer_name, customer_phone,
                        shipping_address_line1, shipping_city, shipping_state, shipping_zip,
                        subtotal, shipping_cost, tax_amount, total_amount,
                        status, payment_status, payment_method,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'paid', ?, NOW())
                ");
                
                $full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                if (empty($full_name)) $full_name = $user['email'];
                
                $stmt->execute([
                    $order_id, $order_number, $user_id, $user['email'], $full_name, $user['phone'] ?? '',
                    $shipping_address, $shipping_city, $shipping_state, $shipping_zip,
                    $subtotal, $shipping_cost, $tax_amount, $total_amount, $payment_method
                ]);
                
                // Insert order items
                if ($is_cart_checkout) {
                    // Insert all cart items
                    foreach ($cart_items as $item) {
                        $item_id = ProductCheckoutUtils::generateUUID();
                        $quantity = intval($item['quantity']);
                        $price = floatval($item['base_price']);
                        $item_subtotal = $price * $quantity;
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO product_order_items (
                                id, order_id, product_id, product_name, quantity, unit_price, total_price
                            ) VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $item_id, $order_id, $item['id'] ?? 'unknown', $item['name'], 
                            $quantity, $price, $item_subtotal
                        ]);
                    }
                    
                    // Clear cart after successful order
                    $_SESSION['cart'] = [];
                } else {
                    // Insert single product
                    $item_id = ProductCheckoutUtils::generateUUID();
                    $quantity = max(1, intval($_POST['quantity'] ?? 1));
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO product_order_items (
                            id, order_id, product_id, product_name, quantity, unit_price, total_price
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $item_id, $order_id, $product_id, $selected_product['name'], 
                        $quantity, $selected_product['price'], $subtotal
                    ]);
                }
                
            } catch (Exception $e) {
                // If product_orders table doesn't exist or has issues, create a basic order log
                error_log("Product order table error, using fallback: " . $e->getMessage());
                
                // Create a simple order log in session
                if (!isset($_SESSION['order_history'])) {
                    $_SESSION['order_history'] = [];
                }
                
                $_SESSION['order_history'][] = [
                    'id' => $order_id,
                    'order_number' => $order_number,
                    'total' => $total_amount,
                    'items' => $is_cart_checkout ? count($cart_items) : 1,
                    'date' => date('Y-m-d H:i:s'),
                    'status' => 'completed'
                ];
                
                // Still clear cart if cart checkout
                if ($is_cart_checkout) {
                    $_SESSION['cart'] = [];
                }
            }
            
            $pdo->commit();
            
            $success = true;
            $_SESSION['flash_message'] = "Order placed successfully! Order #" . $order_number;
            $_SESSION['flash_type'] = 'success';
            
            // UPDATED: Redirect to product-order-status.php instead of showing success inline
            header("Location: product-order-status.php?order=" . urlencode($order_id));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "An error occurred while processing your order: " . $e->getMessage();
            error_log("Order processing error: " . $e->getMessage());
        }
    }
}

// Calculate default totals for display
if ($is_cart_checkout) {
    $default_subtotal = 0;
    foreach ($cart_items as $item) {
        $quantity = intval($item['quantity']);
        $price = floatval($item['base_price']);
        $item_total = $price * $quantity;
        
        // Add customization costs
        if (isset($item['customizations'])) {
            if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                $item_total += 2.99 * $quantity;
            }
            if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                $item_total += 1.99 * $quantity;
            }
        }
        
        $default_subtotal += $item_total;
    }
} else {
    $default_quantity = 1;
    $default_subtotal = isset($selected_product) ? $selected_product['price'] * $default_quantity : 0;
}

$default_shipping = ProductCheckoutUtils::calculateShipping($default_subtotal);
$default_tax = ProductCheckoutUtils::calculateTax($default_subtotal);
$default_total = $default_subtotal + $default_shipping + $default_tax;

// Include the header AFTER form processing
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout<?= $is_cart_checkout ? ' - Your Cart' : ($selected_product ? ' - ' . htmlspecialchars($selected_product['name']) : '') ?> | Somdul Table</title>
    <meta name="description" content="Complete your purchase from Somdul Table">
    
    <style>
        /* PAGE-SPECIFIC STYLES ONLY - header styles come from header.php */
        
        .checkout-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .checkout-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .checkout-header h1 {
            color: var(--brown);
            margin-bottom: 0.5rem;
        }
        
        .checkout-steps {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        
        .step {
            padding: 0.5rem 1rem;
            background: var(--cream);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            color: var(--brown);
        }
        
        .step.active {
            background: var(--brown);
            color: var(--white);
        }
        
        .checkout-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }
        
        .checkout-form {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section h3 {
            color: var(--brown);
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--brown);
            box-shadow: 0 0 0 3px rgba(189, 147, 121, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .order-summary {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            height: fit-content;
            position: sticky;
            top: 140px;
        }
        
        .product-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--cream);
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            background: var(--cream);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brown);
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .product-details {
            flex: 1;
        }
        
        .product-name {
            font-weight: 600;
            color: var(--brown);
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }
        
        .product-price {
            color: var(--text-gray);
            font-size: 0.9rem;
        }
        
        .product-quantity {
            color: var(--text-gray);
            font-size: 0.85rem;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            border: 1px solid var(--brown);
            background: var(--white);
            color: var(--brown);
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .quantity-btn:hover {
            background: var(--brown);
            color: var(--white);
        }
        
        .quantity-input {
            width: 50px;
            text-align: center;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            padding: 0.25rem;
        }
        
        .order-totals {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--cream);
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .total-row.final {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--brown);
            border-top: 1px solid var(--cream);
            padding-top: 0.5rem;
            margin-top: 1rem;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--brown);
            text-decoration: none;
            margin-bottom: 1rem;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .checkout-content {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .checkout-form,
            .order-summary {
                padding: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                position: static;
                order: -1;
            }
            
            .checkout-container {
                padding: 0 1rem;
            }
        }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing -->
<body class="has-header">
    <!-- The header (promo banner + navbar) is already included from header.php -->

    <!-- Main Content -->
    <main class="main-content">
        <div class="checkout-container">
            <!-- Back Link -->
            <a href="<?= $is_cart_checkout ? 'cart.php' : 'product.php' ?>" class="back-link">
                ← Back to <?= $is_cart_checkout ? 'Cart' : 'Products' ?>
            </a>

            <!-- Checkout Header -->
            <div class="checkout-header">
                <h1><?= $is_cart_checkout ? 'Checkout - Your Cart' : 'Checkout' ?></h1>
                <div class="checkout-steps">
                    <div class="step active">Review Items</div>
                    <div class="step active">Shipping Info</div>
                    <div class="step active">Payment</div>
                </div>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <strong>Please resolve the following issues:</strong>
                    <ul style="margin: 0.5rem 0 0 1.5rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Checkout Content -->
            <div class="checkout-content">
                <!-- Checkout Form -->
                <div class="checkout-form">
                    <form method="POST" id="checkoutForm">
                        <!-- Shipping Information -->
                        <div class="form-section">
                            <h3>📦 Shipping Information</h3>
                            
                            <div class="form-group">
                                <label for="shipping_address">Street Address *</label>
                                <input type="text" id="shipping_address" name="shipping_address" 
                                       value="<?= htmlspecialchars($user['delivery_address'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="shipping_city">City *</label>
                                    <input type="text" id="shipping_city" name="shipping_city" 
                                           value="<?= htmlspecialchars($user['city'] ?? '') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="shipping_state">State *</label>
                                    <select id="shipping_state" name="shipping_state" required>
                                        <option value="">Select State</option>
                                        <option value="CA" <?= ($user['state'] ?? '') === 'CA' ? 'selected' : '' ?>>California</option>
                                        <option value="NY" <?= ($user['state'] ?? '') === 'NY' ? 'selected' : '' ?>>New York</option>
                                        <option value="TX" <?= ($user['state'] ?? '') === 'TX' ? 'selected' : '' ?>>Texas</option>
                                        <option value="FL" <?= ($user['state'] ?? '') === 'FL' ? 'selected' : '' ?>>Florida</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="shipping_zip">ZIP Code *</label>
                                <input type="text" id="shipping_zip" name="shipping_zip" 
                                       value="<?= htmlspecialchars($user['zip_code'] ?? '') ?>" required>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="form-section">
                            <h3>💳 Payment Method</h3>
                            
                            <div class="form-group">
                                <label>
                                    <input type="radio" name="payment_method" value="credit_card" checked>
                                    Credit/Debit Card
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="radio" name="payment_method" value="paypal">
                                    PayPal
                                </label>
                            </div>
                        </div>

                        <button type="submit" name="place_order" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                            Place Order - <?= ProductCheckoutUtils::formatPrice($default_total) ?>
                        </button>
                    </form>
                </div>

                <!-- Order Summary -->
                <div class="order-summary">
                    <h3 style="color: var(--brown); margin-bottom: 1rem;">Order Summary</h3>
                    
                    <?php if ($is_cart_checkout): ?>
                        <!-- Display all cart items -->
                        <?php foreach ($cart_items as $item): ?>
                            <div class="product-item">
                                <div class="product-image">🍜</div>
                                <div class="product-details">
                                    <div class="product-name"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="product-price"><?= ProductCheckoutUtils::formatPrice($item['base_price']) ?> each</div>
                                    <div class="product-quantity">Qty: <?= intval($item['quantity']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif (isset($selected_product)): ?>
                        <!-- Single product -->
                        <div class="product-item">
                            <div class="product-image">🍜</div>
                            <div class="product-details">
                                <div class="product-name"><?= htmlspecialchars($selected_product['name']) ?></div>
                                <div class="product-price"><?= ProductCheckoutUtils::formatPrice($selected_product['price']) ?> each</div>
                                
                                <div class="quantity-selector">
                                    <button type="button" class="quantity-btn" onclick="updateQuantity(-1)">-</button>
                                    <input type="number" class="quantity-input" id="quantity" name="quantity" value="1" min="1" max="10">
                                    <button type="button" class="quantity-btn" onclick="updateQuantity(1)">+</button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Fallback for no product -->
                        <div class="product-item">
                            <div class="product-image">🍜</div>
                            <div class="product-details">
                                <div class="product-name">Product Checkout</div>
                                <div class="product-price">Processing...</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="order-totals">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span id="subtotal"><?= ProductCheckoutUtils::formatPrice($default_subtotal) ?></span>
                        </div>
                        <div class="total-row">
                            <span>Shipping:</span>
                            <span id="shipping"><?= ProductCheckoutUtils::formatPrice($default_shipping) ?></span>
                        </div>
                        <div class="total-row">
                            <span>Tax:</span>
                            <span id="tax"><?= ProductCheckoutUtils::formatPrice($default_tax) ?></span>
                        </div>
                        <div class="total-row final">
                            <span>Total:</span>
                            <span id="total"><?= ProductCheckoutUtils::formatPrice($default_total) ?></span>
                        </div>
                    </div>
                    
                    <div style="font-size: 0.9rem; color: var(--text-gray); margin-top: 1rem;">
                        🚚 Free shipping on orders over $50<br>
                        📞 Questions? <a href="contact.php" style="color: var(--brown);">Contact us</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const isCartCheckout = <?= $is_cart_checkout ? 'true' : 'false' ?>;
        const productPrice = <?= isset($selected_product) ? $selected_product['price'] : ($is_cart_checkout ? $default_subtotal : 0) ?>;
        
        // Update quantity and recalculate totals (only for single product)
        function updateQuantity(change) {
            if (isCartCheckout) return; // Don't allow quantity changes for cart checkout
            
            const quantityInput = document.getElementById('quantity');
            if (!quantityInput) return;
            
            let newQuantity = parseInt(quantityInput.value) + change;
            
            if (newQuantity < 1) newQuantity = 1;
            if (newQuantity > 10) newQuantity = 10;
            
            quantityInput.value = newQuantity;
            updateTotals();
        }
        
        // Recalculate order totals
        function updateTotals() {
            if (isCartCheckout) return; // Cart totals are fixed
            
            const quantityInput = document.getElementById('quantity');
            if (!quantityInput) return;
            
            const quantity = parseInt(quantityInput.value);
            const state = document.getElementById('shipping_state').value || 'CA';
            
            const subtotal = productPrice * quantity;
            const shipping = subtotal >= 50 ? 0 : 7.99;
            
            // Simple tax calculation
            const taxRates = { 'CA': 0.0875, 'NY': 0.08, 'TX': 0.0625, 'FL': 0.06 };
            const taxRate = taxRates[state] || 0.05;
            const tax = subtotal * taxRate;
            
            const total = subtotal + shipping + tax;
            
            // Update display
            document.getElementById('subtotal').textContent = formatPrice(subtotal);
            document.getElementById('shipping').textContent = formatPrice(shipping);
            document.getElementById('tax').textContent = formatPrice(tax);
            document.getElementById('total').textContent = formatPrice(total);
            
            // Update button text
            const submitBtn = document.querySelector('[name="place_order"]');
            if (submitBtn) {
                submitBtn.textContent = `Place Order - ${formatPrice(total)}`;
            }
        }
        
        // Format price helper
        function formatPrice(amount) {
            return '$' + amount.toFixed(2);
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            if (!isCartCheckout) {
                // Quantity input change
                const quantityInput = document.getElementById('quantity');
                if (quantityInput) {
                    quantityInput.addEventListener('change', updateTotals);
                }
            }
            
            // State change updates tax
            const stateSelect = document.getElementById('shipping_state');
            if (stateSelect) {
                stateSelect.addEventListener('change', function() {
                    if (!isCartCheckout) {
                        updateTotals();
                    }
                });
            }
            
            // Form validation
            const checkoutForm = document.getElementById('checkoutForm');
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function(e) {
                    const requiredFields = ['shipping_address', 'shipping_city', 'shipping_state', 'shipping_zip'];
                    let hasErrors = false;
                    
                    requiredFields.forEach(field => {
                        const input = document.getElementById(field);
                        if (input && !input.value.trim()) {
                            input.style.borderColor = '#dc3545';
                            hasErrors = true;
                        } else if (input) {
                            input.style.borderColor = '#d4c4b8';
                        }
                    });
                    
                    if (hasErrors) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            }
        });
    </script>
</body>
</html>