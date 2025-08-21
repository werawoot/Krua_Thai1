<?php
/**
 * Somdul Table - Guest Order Status Page
 * File: guest-order-status.php
 * Description: Order status and tracking page for guest users
 * Access via: order_number and email parameters
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Utility Functions
class GuestOrderStatusUtils {
    
    public static function sanitizeInput($input) {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
    
    public static function formatPrice($price) {
        return '$' . number_format($price, 2);
    }
    
    public static function formatDate($date) {
        return date('F j, Y \a\t g:i A', strtotime($date));
    }
    
    public static function getStatusColor($status) {
        $colors = [
            'pending' => '#ff9900',
            'paid' => '#24b75d',
            'processing' => '#cf723a',
            'shipped' => '#adb89d',
            'delivered' => '#24b75d',
            'cancelled' => '#dc3545',
            'refunded' => '#6c757d'
        ];
        
        return $colors[strtolower($status)] ?? '#6c757d';
    }
    
    public static function getStatusIcon($status) {
        $icons = [
            'pending' => '⏳',
            'paid' => '✅',
            'processing' => '📦',
            'shipped' => '🚚',
            'delivered' => '🎉',
            'cancelled' => '❌',
            'refunded' => '💰'
        ];
        
        return $icons[strtolower($status)] ?? '📋';
    }
    
    public static function getTrackingSteps($status) {
        $steps = [
            ['name' => 'Order Placed', 'key' => 'pending', 'completed' => false],
            ['name' => 'Payment Confirmed', 'key' => 'paid', 'completed' => false],
            ['name' => 'Processing', 'key' => 'processing', 'completed' => false],
            ['name' => 'Shipped', 'key' => 'shipped', 'completed' => false],
            ['name' => 'Delivered', 'key' => 'delivered', 'completed' => false]
        ];
        
        $statusOrder = ['pending', 'paid', 'processing', 'shipped', 'delivered'];
        $currentIndex = array_search(strtolower($status), $statusOrder);
        
        if ($currentIndex !== false) {
            for ($i = 0; $i <= $currentIndex; $i++) {
                $steps[$i]['completed'] = true;
            }
        }
        
        // Handle special cases
        if (strtolower($status) === 'cancelled' || strtolower($status) === 'refunded') {
            // Only mark first step as completed for cancelled/refunded orders
            $steps[0]['completed'] = true;
        }
        
        return $steps;
    }
}

// Initialize variables
$order = null;
$order_items = [];
$errors = [];
$order_number = '';
$email = '';

// Get parameters from URL
$order_number = GuestOrderStatusUtils::sanitizeInput($_GET['order_number'] ?? '');
$email = GuestOrderStatusUtils::sanitizeInput($_GET['email'] ?? '');

// Database connection with fallback
try {
    $database = new Database();
    $pdo = $database->getConnection();
} catch (Exception $e) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        $pdo = new PDO("mysql:host=localhost:8889;dbname=somdul_table;charset=utf8mb4", "root", "root");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
}

// Validate required parameters
if (empty($order_number) || empty($email)) {
    $errors[] = "Order number and email are required to view order status.";
} else {
    // Fetch order details
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM product_orders 
            WHERE order_number = ? AND customer_email = ?
            LIMIT 1
        ");
        $stmt->execute([$order_number, $email]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $errors[] = "Order not found. Please check your order number and email address.";
        } else {
            // Fetch order items
            try {
                $stmt = $pdo->prepare("
                    SELECT * FROM product_order_items 
                    WHERE order_id = ?
                    ORDER BY id ASC
                ");
                $stmt->execute([$order['id']]);
                $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // If order items table doesn't exist, create mock items
                $order_items = [
                    [
                        'product_name' => 'Product Order',
                        'quantity' => 1,
                        'unit_price' => $order['subtotal'] ?? 0,
                        'total_price' => $order['subtotal'] ?? 0
                    ]
                ];
            }
        }
        
    } catch (Exception $e) {
        $errors[] = "Unable to retrieve order information: " . $e->getMessage();
    }
}

// Include header for consistent styling
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status<?= !empty($order_number) ? ' - ' . htmlspecialchars($order_number) : '' ?> | Somdul Table</title>
    <meta name="description" content="Track your order status from Somdul Table">
    
    <style>
        /* PAGE-SPECIFIC STYLES ONLY - header styles come from header.php */
        
        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .status-header {
            text-align: center;
            margin-bottom: 3rem;
            margin-top:15rem;
        }

        .status-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--brown);
        }

        .order-number {
            font-size: 1.2rem;
            color: var(--text-gray);
            margin-bottom: 0.5rem;
        }

        .status-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 1rem;
        }

        .tracking-timeline {
            margin: 2rem 0;
        }

        .timeline-step {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .timeline-step:last-child {
            margin-bottom: 0;
        }

        .timeline-step::after {
            content: '';
            position: absolute;
            left: 20px;
            top: 45px;
            width: 2px;
            height: 30px;
            background: var(--cream);
        }

        .timeline-step:last-child::after {
            display: none;
        }

        .timeline-step.completed::after {
            background: var(--brown);
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 1rem;
            flex-shrink: 0;
            z-index: 1;
            position: relative;
        }

        .timeline-step.completed .timeline-icon {
            background: var(--brown);
            color: var(--white);
        }

        .timeline-step:not(.completed) .timeline-icon {
            background: var(--cream);
            color: var(--text-gray);
        }

        .timeline-content h4 {
            margin: 0 0 0.5rem 0;
            color: var(--brown);
        }

        .timeline-content p {
            margin: 0;
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .order-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .detail-section h3 {
            color: var(--brown);
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--cream);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-row.total {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--brown);
            border-top: 2px solid var(--brown);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .order-items {
            margin-bottom: 2rem;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--cream);
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-details h4 {
            margin: 0 0 0.5rem 0;
            color: var(--brown);
        }

        .item-details p {
            margin: 0;
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .item-price {
            font-weight: 600;
            color: var(--brown);
        }

        .error-message {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 2rem;
            border: 1px solid #e74c3c;
            text-align: center;
        }

        .tracking-form {
            background: var(--cream);
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--brown);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-light);
            border-radius: var(--radius-sm);
            font-family: 'BaticaSans', sans-serif;
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--brown);
            box-shadow: 0 0 0 3px rgba(189, 147, 121, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .help-section {
            text-align: center;
            padding: 2rem;
            background: var(--cream);
            border-radius: var(--radius-lg);
            margin-top: 2rem;
        }

        .help-section h3 {
            color: var(--brown);
            margin-bottom: 1rem;
        }

        .help-section p {
            color: var(--text-gray);
            margin-bottom: 1rem;
        }

        /* Mobile responsive */
        @media (max-width: 768px) {
            .order-details {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .action-buttons .btn {
                width: 100%;
                max-width: 300px;
            }

            .container {
                padding: 0 1rem;
            }

            .status-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>

<!-- IMPORTANT: Add has-header class for proper spacing -->
<body class="has-header">
    <div class="container">
        
        <?php if (!empty($errors)): ?>
            <!-- Error State -->
            <div class="status-header">
                <h1>❌ Order Not Found</h1>
            </div>
            
            <div class="error-message">
                <h3>We couldn't find your order</h3>
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>

            <!-- Order Tracking Form -->
            <div class="tracking-form">
                <h3 style="text-align: center; margin-bottom: 1.5rem; color: var(--brown);">Track Your Order</h3>
                <form method="GET" action="guest-order-status.php">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="order_number" class="form-label">Order Number *</label>
                            <input type="text" id="order_number" name="order_number" class="form-input" 
                                   value="<?= htmlspecialchars($order_number) ?>" 
                                   placeholder="GUEST-PRD-20250821-..." required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-input" 
                                   value="<?= htmlspecialchars($email) ?>" 
                                   placeholder="your@email.com" required>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">🔍 Track Order</button>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- Success State - Order Found -->
            <div class="status-header">
                <h1><?= GuestOrderStatusUtils::getStatusIcon($order['status']) ?> Order Status</h1>
                <div class="order-number">Order #<?= htmlspecialchars($order['order_number']) ?></div>
                <div style="color: var(--text-gray);">Placed on <?= GuestOrderStatusUtils::formatDate($order['created_at']) ?></div>
            </div>

            <!-- Status Badge -->
            <div class="status-card" style="text-align: center;">
                <div class="status-badge" style="background-color: <?= GuestOrderStatusUtils::getStatusColor($order['status']) ?>;">
                    <?= GuestOrderStatusUtils::getStatusIcon($order['status']) ?>
                    <?= ucfirst($order['status']) ?>
                </div>
                
                <?php if (!empty($order['tracking_number'])): ?>
                    <p style="margin-top: 1rem; color: var(--text-gray);">
                        📦 Tracking Number: <strong><?= htmlspecialchars($order['tracking_number']) ?></strong>
                        <?php if (!empty($order['shipping_carrier'])): ?>
                            via <?= htmlspecialchars($order['shipping_carrier']) ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                
                <?php if (!empty($order['estimated_delivery'])): ?>
                    <p style="margin-top: 0.5rem; color: var(--brown); font-weight: 600;">
                        🚚 Estimated Delivery: <?= date('F j, Y', strtotime($order['estimated_delivery'])) ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Tracking Timeline -->
            <div class="status-card">
                <h3 style="color: var(--brown); margin-bottom: 1.5rem;">📋 Order Timeline</h3>
                
                <div class="tracking-timeline">
                    <?php 
                    $steps = GuestOrderStatusUtils::getTrackingSteps($order['status']);
                    foreach ($steps as $step): 
                    ?>
                        <div class="timeline-step <?= $step['completed'] ? 'completed' : '' ?>">
                            <div class="timeline-icon">
                                <?= $step['completed'] ? '✓' : ($step['key'] === strtolower($order['status']) ? '⏳' : '○') ?>
                            </div>
                            <div class="timeline-content">
                                <h4><?= htmlspecialchars($step['name']) ?></h4>
                                <?php if ($step['completed']): ?>
                                    <p>Completed</p>
                                <?php elseif ($step['key'] === strtolower($order['status'])): ?>
                                    <p>In Progress</p>
                                <?php else: ?>
                                    <p>Pending</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Order Details -->
            <div class="order-details">
                <!-- Order Items -->
                <div class="status-card">
                    <h3>🛍️ Order Items</h3>
                    <div class="order-items">
                        <?php foreach ($order_items as $item): ?>
                            <div class="item-row">
                                <div class="item-details">
                                    <h4><?= htmlspecialchars($item['product_name']) ?></h4>
                                    <p>Quantity: <?= intval($item['quantity']) ?> × <?= GuestOrderStatusUtils::formatPrice($item['unit_price']) ?></p>
                                </div>
                                <div class="item-price">
                                    <?= GuestOrderStatusUtils::formatPrice($item['total_price']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="status-card">
                    <h3>💰 Order Summary</h3>
                    
                    <div class="detail-row">
                        <span>Subtotal:</span>
                        <span><?= GuestOrderStatusUtils::formatPrice($order['subtotal']) ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span>Shipping:</span>
                        <span><?= GuestOrderStatusUtils::formatPrice($order['shipping_cost']) ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span>Tax:</span>
                        <span><?= GuestOrderStatusUtils::formatPrice($order['tax_amount']) ?></span>
                    </div>
                    
                    <div class="detail-row total">
                        <span>Total:</span>
                        <span><?= GuestOrderStatusUtils::formatPrice($order['total_amount']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Shipping Information -->
            <div class="status-card">
                <h3 style="color: var(--brown); margin-bottom: 1.5rem;">🚚 Shipping Information</h3>
                
                <div class="order-details">
                    <div>
                        <h4 style="color: var(--brown); margin-bottom: 1rem;">Delivery Address</h4>
                        <p style="margin: 0; line-height: 1.6; color: var(--text-dark);">
                            <?= htmlspecialchars($order['customer_name']) ?><br>
                            <?= htmlspecialchars($order['shipping_address_line1']) ?><br>
                            <?php if (!empty($order['shipping_address_line2'])): ?>
                                <?= htmlspecialchars($order['shipping_address_line2']) ?><br>
                            <?php endif; ?>
                            <?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?> <?= htmlspecialchars($order['shipping_zip']) ?><br>
                            <?= htmlspecialchars($order['shipping_country']) ?>
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="color: var(--brown); margin-bottom: 1rem;">Contact Information</h4>
                        <p style="margin: 0; line-height: 1.6; color: var(--text-dark);">
                            📧 <?= htmlspecialchars($order['customer_email']) ?><br>
                            <?php if (!empty($order['customer_phone'])): ?>
                                📞 <?= htmlspecialchars($order['customer_phone']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="product.php" class="btn btn-secondary">🛍️ Shop More</a>
                <a href="contact.php" class="btn btn-primary">📞 Contact Support</a>
            </div>

        <?php endif; ?>

        <!-- Help Section -->
        <div class="help-section">
            <h3>Need Help?</h3>
            <p>If you have any questions about your order, please don't hesitate to contact our customer support team.</p>
            <div style="display: flex; gap: 2rem; justify-content: center; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span>📧</span>
                    <a href="mailto:support@somdultable.com" style="color: var(--brown);">support@somdultable.com</a>
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span>📞</span>
                    <a href="tel:+1-555-SOMDUL" style="color: var(--brown);">(555) SOMDUL-1</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus on first empty field in tracking form
        document.addEventListener('DOMContentLoaded', function() {
            const orderNumberInput = document.getElementById('order_number');
            const emailInput = document.getElementById('email');
            
            if (orderNumberInput && !orderNumberInput.value) {
                orderNumberInput.focus();
            } else if (emailInput && !emailInput.value) {
                emailInput.focus();
            }
            
            // Form validation
            const trackingForm = document.querySelector('form');
            if (trackingForm) {
                trackingForm.addEventListener('submit', function(e) {
                    const orderNumber = orderNumberInput?.value.trim();
                    const email = emailInput?.value.trim();
                    
                    if (!orderNumber || !email) {
                        e.preventDefault();
                        alert('Please enter both order number and email address.');
                        return false;
                    }
                    
                    if (!email.includes('@')) {
                        e.preventDefault();
                        alert('Please enter a valid email address.');
                        emailInput?.focus();
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>