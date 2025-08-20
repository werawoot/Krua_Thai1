<?php
/**
 * Somdul Table - Product Checkout Page
 * File: product-checkout.php
 * Description: Checkout page for individual product purchases (separate from meal subscriptions)
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

// Initialize variables
$selected_product = null;
$checkout_action = '';
$errors = [];
$success = false;
$order_id = null;
$user = null;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $product_id = $_GET['product'] ?? '';
    $redirect_url = 'product-checkout.php?product=' . urlencode($product_id);
    header('Location: login.php?redirect=' . urlencode($redirect_url));
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Database connection with fallback
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get user information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found. Please log in again.");
    }
    
} catch (Exception $e) {
    // Fallback connection
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get user with fallback
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e2) {
        // Second fallback
        $pdo = new PDO("mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Get selected product
$product_id = $_GET['product'] ?? '';
$checkout_action = $_GET['action'] ?? 'add_to_cart';

if (empty($product_id)) {
    header('Location: products.php');
    exit;
}

// Fetch selected product
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    $selected_product = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Fallback products if database table doesn't exist
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
        ]
    ];
    
    $selected_product = $fallback_products[$product_id] ?? null;
}

if (!$selected_product) {
    header('Location: products.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    
    // Validate form input
    $quantity = max(1, intval($_POST['quantity'] ?? 1));
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
            // Calculate order totals
            $subtotal = $selected_product['price'] * $quantity;
            $shipping_cost = ProductCheckoutUtils::calculateShipping($subtotal, $shipping_state);
            $tax_amount = ProductCheckoutUtils::calculateTax($subtotal, $shipping_state);
            $total_amount = $subtotal + $shipping_cost + $tax_amount;
            
            // Generate order details
            $order_id = ProductCheckoutUtils::generateUUID();
            $order_number = ProductCheckoutUtils::generateOrderNumber();
            
            // Insert product order
            $stmt = $pdo->prepare("
                INSERT INTO product_orders (
                    id, order_number, user_id, customer_email, customer_name, customer_phone,
                    shipping_address_line1, shipping_city, shipping_state, shipping_zip,
                    subtotal, shipping_cost, tax_amount, total_amount,
                    status, payment_status, payment_method,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, NOW())
            ");
            
            $full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            if (empty($full_name)) $full_name = $user['email'];
            
            $stmt->execute([
                $order_id, $order_number, $user_id, $user['email'], $full_name, $user['phone'] ?? '',
                $shipping_address, $shipping_city, $shipping_state, $shipping_zip,
                $subtotal, $shipping_cost, $tax_amount, $total_amount, $payment_method
            ]);
            
            // Insert order item
            $item_id = ProductCheckoutUtils::generateUUID();
            $stmt = $pdo->prepare("
                INSERT INTO product_order_items (
                    id, order_id, product_id, product_name, quantity, unit_price, total_price
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $item_id, $order_id, $product_id, $selected_product['name'], 
                $quantity, $selected_product['price'], $subtotal
            ]);
            
            // Simulate payment processing (in real implementation, integrate with Stripe/PayPal)
            $payment_success = true; // Simulate successful payment
            
            if ($payment_success) {
                // Update order status
                $stmt = $pdo->prepare("
                    UPDATE product_orders 
                    SET status = 'paid', payment_status = 'paid' 
                    WHERE id = ?
                ");
                $stmt->execute([$order_id]);
                
                $success = true;
                $_SESSION['flash_message'] = "Order placed successfully! Order #" . $order_number;
                $_SESSION['flash_type'] = 'success';
                
                // Redirect to order confirmation
                header("Location: product-order-status.php?order=" . $order_id);
                exit;
            } else {
                $errors[] = "Payment processing failed. Please try again.";
            }
            
        } catch (Exception $e) {
            $errors[] = "An error occurred while processing your order: " . $e->getMessage();
        }
    }
}

// Calculate default totals for display
$default_quantity = 1;
$default_subtotal = $selected_product['price'] * $default_quantity;
$default_shipping = ProductCheckoutUtils::calculateShipping($default_subtotal);
$default_tax = ProductCheckoutUtils::calculateTax($default_subtotal);
$default_total = $default_subtotal + $default_shipping + $default_tax;

// Include the header
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?= htmlspecialchars($selected_product['name']) ?> | Somdul Table</title>
    <meta name="description" content="Complete your purchase of <?= htmlspecialchars($selected_product['name']) ?>">
    
    <style>
        body.has-header {
            margin-top: 110px;
        }
        
        .checkout-container {
            max-width: 800px;
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
            top: 2rem;
        }
        
        .product-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--cream);
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            background: var(--cream);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brown);
            font-size: 2rem;
        }
        
        .product-details {
            flex: 1;
        }
        
        .product-name {
            font-weight: 600;
            color: var(--brown);
            margin-bottom: 0.5rem;
        }
        
        .product-price {
            color: var(--text-gray);
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
        
        .btn-place-order {
            width: 100%;
            background: var(--brown);
            color: var(--white);
            border: none;
            padding: 1rem;
            border-radius: var(--radius-sm);
            font-size: 1.1rem;
            font-weight: 600;
            font-family: 'BaticaSans', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1rem;
        }
        
        .btn-place-order:hover {
            background: #a8855f;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
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
            }
        }
    </style>
</head>

<body class="has-header">
    <div class="checkout-container">
        <!-- Back Link -->
        <a href="products.php" class="back-link">
            ← Back to Products
        </a>
        
        <!-- Checkout Header -->
        <div class="checkout-header">
            <h1>Checkout</h1>
            <div class="checkout-steps">
                <div class="step active">Review Product</div>
                <div class="step active">Shipping Info</div>
                <div class="step active">Payment</div>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <ul style="margin: 0; padding-left: 1.5rem;">
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
                                    <!-- Add more states as needed -->
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

                    <button type="submit" name="place_order" class="btn-place-order">
                        Place Order - <?= ProductCheckoutUtils::formatPrice($default_total) ?>
                    </button>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <h3 style="color: var(--brown); margin-bottom: 1rem;">Order Summary</h3>
                
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

    <script>
        // Product price for calculations
        const productPrice = <?= $selected_product['price'] ?>;
        
        // Update quantity and recalculate totals
        function updateQuantity(change) {
            const quantityInput = document.getElementById('quantity');
            let newQuantity = parseInt(quantityInput.value) + change;
            
            if (newQuantity < 1) newQuantity = 1;
            if (newQuantity > 10) newQuantity = 10;
            
            quantityInput.value = newQuantity;
            updateTotals();
        }
        
        // Recalculate order totals
        function updateTotals() {
            const quantity = parseInt(document.getElementById('quantity').value);
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
            const submitBtn = document.querySelector('.btn-place-order');
            submitBtn.textContent = `Place Order - ${formatPrice(total)}`;
        }
        
        // Format price helper
        function formatPrice(amount) {
            return '$' + amount.toFixed(2);
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Quantity input change
            document.getElementById('quantity').addEventListener('change', updateTotals);
            
            // State change updates tax
            document.getElementById('shipping_state').addEventListener('change', updateTotals);
            
            // Form validation
            document.getElementById('checkoutForm').addEventListener('submit', function(e) {
                const requiredFields = ['shipping_address', 'shipping_city', 'shipping_state', 'shipping_zip'];
                let hasErrors = false;
                
                requiredFields.forEach(field => {
                    const input = document.getElementById(field);
                    if (!input.value.trim()) {
                        input.style.borderColor = '#dc3545';
                        hasErrors = true;
                    } else {
                        input.style.borderColor = '#d4c4b8';
                    }
                });
                
                if (hasErrors) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html>