<?php
/**
 * Somdul Table - Shopping Cart Page
 * File: cart.php
 * Description: View and manage cart items before checkout
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions BEFORE any output (including header.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_quantity':
            $item_index = intval($_POST['item_index'] ?? -1);
            $new_quantity = max(1, min(10, intval($_POST['quantity'] ?? 1)));
            
            if ($item_index >= 0 && isset($_SESSION['cart'][$item_index])) {
                $_SESSION['cart'][$item_index]['quantity'] = $new_quantity;
                $_SESSION['flash_message'] = 'Cart updated successfully';
                $_SESSION['flash_type'] = 'success';
            }
            break;
            
        case 'remove_item':
            $item_index = intval($_POST['item_index'] ?? -1);
            
            if ($item_index >= 0 && isset($_SESSION['cart'][$item_index])) {
                $item_name = $_SESSION['cart'][$item_index]['name'];
                unset($_SESSION['cart'][$item_index]);
                $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
                $_SESSION['flash_message'] = $item_name . ' removed from cart';
                $_SESSION['flash_type'] = 'success';
            }
            break;
            
        case 'clear_cart':
            $_SESSION['cart'] = [];
            $_SESSION['flash_message'] = 'Cart cleared successfully';
            $_SESSION['flash_type'] = 'success';
            break;
    }
    
    // Redirect to prevent form resubmission
    header('Location: cart.php');
    exit;
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

// Calculate cart totals
$subtotal = 0;
$total_items = 0;

foreach ($_SESSION['cart'] as $item) {
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
    $total_items += $quantity;
}

$delivery_fee = $subtotal >= 25 ? 0 : 3.99;
$tax_rate = 0.0825; // 8.25%
$tax_amount = $subtotal * $tax_rate;
$total = $subtotal + $delivery_fee + $tax_amount;

// Get flash message
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Include the header (contains navbar, promo banner, fonts, and base styles)
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart | Somdul Table</title>
    <meta name="description" content="Review your cart and proceed to checkout for authentic Thai meal kits and products.">
    
    <style>
        /* PAGE-SPECIFIC STYLES ONLY - header styles come from header.php */
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .main-content {
            padding-top: 2rem;
            min-height: calc(100vh - 200px);
        }
        
        .cart-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .cart-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .cart-header h1 {
            color: var(--brown);
            margin-bottom: 0.5rem;
        }
        
        .cart-breadcrumb {
            color: var(--text-gray);
            margin-bottom: 2rem;
        }
        
        .cart-breadcrumb a {
            color: var(--curry);
            text-decoration: none;
        }
        
        .cart-breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .cart-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 3rem;
        }
        
        .cart-items {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
        }
        
        .cart-summary {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            height: fit-content;
            position: sticky;
            top: 140px;
        }
        
        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--brown);
            font-weight: 700;
        }
        
        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 1rem;
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--cream);
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 100px;
            height: 100px;
            background: var(--cream);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--brown);
            font-size: 2rem;
        }
        
        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--radius-sm);
        }
        
        .item-details {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .item-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--brown);
            margin-bottom: 0.5rem;
        }
        
        .item-category {
            font-size: 0.9rem;
            color: var(--curry);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        .item-price {
            font-size: 1rem;
            color: var(--text-gray);
            margin-bottom: 1rem;
        }
        
        .item-customizations {
            font-size: 0.9rem;
            color: var(--sage);
            margin-bottom: 0.5rem;
        }
        
        .item-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid var(--border-light);
            border-radius: var(--radius-sm);
            padding: 0.25rem;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            border: none;
            background: var(--white);
            color: var(--brown);
            border-radius: 50%;
            cursor: pointer;
            font-weight: bold;
            transition: var(--transition);
        }
        
        .quantity-btn:hover {
            background: var(--brown);
            color: var(--white);
        }
        
        .quantity-input {
            width: 50px;
            text-align: center;
            border: none;
            background: transparent;
            font-weight: 600;
        }
        
        .item-controls {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            height: 100px;
        }
        
        .item-total {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--brown);
        }
        
        .btn-remove {
            background: none;
            border: none;
            color: var(--text-gray);
            cursor: pointer;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .btn-remove:hover {
            color: var(--error-color, #e74c3c);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }
        
        .summary-row.total {
            border-top: 2px solid var(--cream);
            padding-top: 1rem;
            margin-top: 1rem;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--brown);
        }
        
        .free-shipping-note {
            background: var(--cream);
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin: 1rem 0;
            text-align: center;
            font-size: 0.9rem;
            color: var(--text-gray);
        }
        
        .free-shipping-note.achieved {
            background: rgba(173, 184, 157, 0.2);
            color: var(--sage);
            font-weight: 600;
        }
        
        /* Remove conflicting button styles - let header.php handle all button styling */
        
        .checkout-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .btn-danger {
            background: var(--error-color, #e74c3c);
            color: var(--white);
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-gray);
        }
        
        .empty-cart h3 {
            color: var(--brown);
            margin-bottom: 1rem;
        }
        
        .empty-cart-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .flash-message {
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 2rem;
            font-weight: 600;
        }
        
        .flash-message.success {
            background: rgba(173, 184, 157, 0.2);
            color: var(--sage);
            border: 1px solid var(--sage);
        }
        
        .flash-message.error {
            background: rgba(231, 76, 60, 0.1);
            color: var(--error-color, #e74c3c);
            border: 1px solid var(--error-color, #e74c3c);
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .cart-content {
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
            
            .item-controls {
                grid-column: 1 / -1;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                margin-top: 1rem;
                height: auto;
            }
            
            .cart-container {
                padding: 0 1rem;
            }
        }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing (like product.php) -->
<body class="has-header">
    <!-- The header (promo banner + navbar) is already included from header.php -->

    <!-- Main Content -->
    <main class="main-content">
        <div class="cart-container">
            <!-- Breadcrumb -->
            <div class="cart-breadcrumb">
                <a href="index.php">Home</a> → <a href="product.php">Products</a> → Shopping Cart
            </div>
            
            <!-- Cart Header -->
            <div class="cart-header">
                <h1>Shopping Cart</h1>
                <?php if (!empty($_SESSION['cart'])): ?>
                    <p>Review your items and proceed to checkout</p>
                <?php endif; ?>
            </div>

            <!-- Flash Message -->
            <?php if ($flash_message): ?>
                <div class="flash-message <?= $flash_type ?>">
                    <?= htmlspecialchars($flash_message) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($_SESSION['cart'])): ?>
                <!-- Empty Cart State -->
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Your cart is empty</h3>
                    <p>Looks like you haven't added any items to your cart yet.</p>
                    <a href="product.php" class="btn btn-primary" style="margin-top: 2rem; max-width: 300px;">
                        Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <!-- Cart Content -->
                <div class="cart-content">
                    <!-- Cart Items -->
                    <div class="cart-items">
                        <h2 class="section-title">Your Items (<?= count($_SESSION['cart']) ?>)</h2>
                        
                        <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                            <?php
                            $quantity = intval($item['quantity']);
                            $price = floatval($item['base_price']);
                            $item_subtotal = $price * $quantity;
                            
                            // Calculate extras
                            $extras_cost = 0;
                            $extras_text = '';
                            if (isset($item['customizations'])) {
                                if (isset($item['customizations']['extra_protein']) && $item['customizations']['extra_protein']) {
                                    $extras_cost += 2.99 * $quantity;
                                    $extras_text .= 'Extra Protein (+$2.99) ';
                                }
                                if (isset($item['customizations']['extra_vegetables']) && $item['customizations']['extra_vegetables']) {
                                    $extras_cost += 1.99 * $quantity;
                                    $extras_text .= 'Extra Vegetables (+$1.99) ';
                                }
                            }
                            
                            $item_total = $item_subtotal + $extras_cost;
                            ?>
                            
                            <div class="cart-item">
                                <div class="item-image">
                                    <?php if (!empty($item['image_url']) && file_exists($item['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                    <?php else: ?>
                                        🍜
                                    <?php endif; ?>
                                </div>
                                
                                <div class="item-details">
                                    <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="item-category"><?= htmlspecialchars($item['category'] ?? 'Product') ?></div>
                                    <div class="item-price">$<?= number_format($price, 2) ?> each</div>
                                    
                                    <?php if ($extras_text): ?>
                                        <div class="item-customizations">
                                            <?= htmlspecialchars(trim($extras_text)) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="item-actions">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_quantity">
                                            <input type="hidden" name="item_index" value="<?= $index ?>">
                                            <div class="quantity-controls">
                                                <button type="button" class="quantity-btn" onclick="changeQuantity(this, -1)">-</button>
                                                <input type="number" name="quantity" value="<?= $quantity ?>" min="1" max="10" class="quantity-input" onchange="this.form.submit()">
                                                <button type="button" class="quantity-btn" onclick="changeQuantity(this, 1)">+</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="item-controls">
                                    <div class="item-total">$<?= number_format($item_total, 2) ?></div>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="remove_item">
                                        <input type="hidden" name="item_index" value="<?= $index ?>">
                                        <button type="submit" class="btn-remove" onclick="return confirm('Remove this item from cart?')">
                                            🗑️ Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Clear Cart Button -->
                        <div style="text-align: right; margin-top: 2rem;">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="clear_cart">
                                <button type="submit" class="btn-clear-cart" onclick="return confirm('Clear all items from cart?')">
                                    Clear Cart
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Cart Summary -->
                    <div class="cart-summary">
                        <h3 class="section-title">Order Summary</h3>
                        
                        <div class="summary-row">
                            <span>Subtotal (<?= $total_items ?> items):</span>
                            <span>$<?= number_format($subtotal, 2) ?></span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Delivery Fee:</span>
                            <span style="<?= $delivery_fee == 0 ? 'color: var(--sage); font-weight: 600;' : '' ?>">
                                <?= $delivery_fee == 0 ? 'FREE' : '$' . number_format($delivery_fee, 2) ?>
                            </span>
                        </div>
                        
                        <div class="summary-row">
                            <span>Tax (8.25%):</span>
                            <span>$<?= number_format($tax_amount, 2) ?></span>
                        </div>
                        
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span>$<?= number_format($total, 2) ?></span>
                        </div>
                        
                        <!-- Free Shipping Notice -->
                        <?php if ($delivery_fee > 0): ?>
                            <div class="free-shipping-note">
                                💡 Add $<?= number_format(25 - $subtotal, 2) ?> more for free delivery!
                            </div>
                        <?php else: ?>
                            <div class="free-shipping-note achieved">
                                ✅ You've earned free delivery!
                            </div>
                        <?php endif; ?>
                        
                        <!-- Checkout Buttons -->
                        <div class="checkout-buttons">
                            <a href="<?= $is_logged_in ? 'product-checkout.php?source=cart' : 'guest-product-checkout.php?source=cart' ?>" class="btn btn-primary">
                                🛒 Proceed to Checkout
                            </a>
                            
                            <a href="product.php" class="btn btn-secondary">
                                ← Continue Shopping
                            </a>
                        </div>
                        
                        <!-- Additional Info -->
                        <div style="font-size: 0.9rem; color: var(--text-gray); margin-top: 1.5rem; text-align: center; line-height: 1.4;">
                            <p>🔒 Secure checkout</p>
                            <p>📞 Need help? <a href="contact.php" style="color: var(--curry);">Contact us</a></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Quantity controls
        function changeQuantity(button, change) {
            const quantityInput = button.parentElement.querySelector('.quantity-input');
            let newQuantity = parseInt(quantityInput.value) + change;
            
            if (newQuantity < 1) newQuantity = 1;
            if (newQuantity > 10) newQuantity = 10;
            
            quantityInput.value = newQuantity;
            quantityInput.form.submit();
        }
        
        // Auto-hide flash messages
        document.addEventListener('DOMContentLoaded', function() {
            const flashMessage = document.querySelector('.flash-message');
            if (flashMessage) {
                setTimeout(() => {
                    flashMessage.style.opacity = '0';
                    flashMessage.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        flashMessage.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        });
        
        // Smooth remove animation
        document.querySelectorAll('.btn-remove').forEach(button => {
            button.addEventListener('click', function(e) {
                if (confirm('Remove this item from cart?')) {
                    const cartItem = this.closest('.cart-item');
                    cartItem.style.opacity = '0.5';
                    cartItem.style.transform = 'translateX(-20px)';
                    cartItem.style.transition = 'all 0.3s ease';
                }
            });
        });
        
        // Prevent double submission
        let isSubmitting = false;
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
                isSubmitting = true;
                
                setTimeout(() => {
                    isSubmitting = false;
                }, 1000);
            });
        });
    </script>
</body>
</html>