<?php
/**
 * Somdul Table - Product Order Status Page
 * File: product-order-status.php
 * Description: Order status and tracking page for logged-in users
 * Access via: order_id parameter
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $order_id = $_GET['order'] ?? '';
    $redirect_url = 'product-order-status.php';
    if (!empty($order_id)) $redirect_url .= '?order=' . urlencode($order_id);
    header('Location: login.php?redirect=' . urlencode($redirect_url));
    exit;
}

$user_id = $_SESSION['user_id'];

// Utility Functions
class ProductOrderStatusUtils {
    
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
$user = null;
$errors = [];

// Get order ID from URL
$order_id = ProductOrderStatusUtils::sanitizeInput($_GET['order'] ?? '');

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

// Get user information
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found. Please log in again.");
    }
} catch (Exception $e) {
    $errors[] = "Authentication error: " . $e->getMessage();
}

// Validate order ID
if (empty($order_id)) {
    $errors[] = "Order ID is required to view order status.";
} else {
    // Fetch order details (ensure user owns this order)
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM product_orders 
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$order_id, $user_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            $errors[] = "Order not found or you don't have permission to view this order.";
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
    <title>Order Status<?= !empty($order['order_number']) ? ' - ' . htmlspecialchars($order['order_number']) : '' ?> | Somdul Table</title>
    <meta name="description" content="Track your order status from Somdul Table">
    
    <style>
        /* PAGE-SPECIFIC STYLES ONLY - header styles come from header.php */
        
        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--brown);
            text-decoration: none;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .status-header {
            text-align: center;
            margin-bottom: 3rem;
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

        .user-info {
            background: var(--cream);
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 1rem;
            text-align: center;
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

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .quick-action-card {
            background: var(--cream);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            text-align: center;
            transition: var(--transition);
        }

        .quick-action-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .quick-action-card h4 {
            color: var(--brown);
            margin-bottom: 0.5rem;
        }

        .quick-action-card p {
            color: var(--text-gray);
            margin-bottom: 1rem;
            font-size: 0.9rem;
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

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .action-buttons .btn {
                width: 100%;
                max-width: 300px;
            }

            .quick-actions {
                grid-template-columns: 1fr;
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
        
        <!-- Back Link -->
        <a href="dashboard.php" class="back-link">
            ← Back to Dashboard
        </a>
        
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

            <!-- Quick Actions for Error State -->
            <div class="quick-actions">
                <div class="quick-action-card">
                    <h4>🛍️ Browse Products</h4>
                    <p>Explore our authentic Thai meal kits and ingredients</p>
                    <a href="product.php" class="btn btn-primary">Shop Now</a>
                </div>
                
                <div class="quick-action-card">
                    <h4>📋 Order History</h4>
                    <p>View all your previous orders and their status</p>
                    <a href="dashboard.php" class="btn btn-secondary">View Orders</a>
                </div>
            </div>

        <?php else: ?>
            <!-- Success State - Order Found -->
            
            <!-- User Welcome -->
            <?php if ($user): ?>
                <div class="user-info">
                    👋 Welcome back, <strong><?= htmlspecialchars($user['first_name'] ?? 'Customer') ?></strong>!
                </div>
            <?php endif; ?>
            
            <div class="status-header">
                <h1><?= ProductOrderStatusUtils::getStatusIcon($order['status']) ?> Order Status</h1>
                <div class="order-number">Order #<?= htmlspecialchars($order['order_number']) ?></div>
                <div style="color: var(--text-gray);">Placed on <?= ProductOrderStatusUtils::formatDate($order['created_at']) ?></div>
            </div>

            <!-- Status Badge -->
            <div class="status-card" style="text-align: center;">
                <div class="status-badge" style="background-color: <?= ProductOrderStatusUtils::getStatusColor($order['status']) ?>;">
                    <?= ProductOrderStatusUtils::getStatusIcon($order['status']) ?>
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
                    $steps = ProductOrderStatusUtils::getTrackingSteps($order['status']);
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
                                    <p>Quantity: <?= intval($item['quantity']) ?> × <?= ProductOrderStatusUtils::formatPrice($item['unit_price']) ?></p>
                                </div>
                                <div class="item-price">
                                    <?= ProductOrderStatusUtils::formatPrice($item['total_price']) ?>
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
                        <span><?= ProductOrderStatusUtils::formatPrice($order['subtotal']) ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span>Shipping:</span>
                        <span><?= ProductOrderStatusUtils::formatPrice($order['shipping_cost']) ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span>Tax:</span>
                        <span><?= ProductOrderStatusUtils::formatPrice($order['tax_amount']) ?></span>
                    </div>
                    
                    <div class="detail-row total">
                        <span>Total:</span>
                        <span><?= ProductOrderStatusUtils::formatPrice($order['total_amount']) ?></span>
                    </div>
                    
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--cream); font-size: 0.9rem; color: var(--text-gray);">
                        Payment Method: <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $order['payment_method']))) ?>
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

            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="quick-action-card">
                    <h4>🛍️ Shop Again</h4>
                    <p>Reorder your favorite items or explore new products</p>
                    <a href="product.php" class="btn btn-primary">Browse Products</a>
                </div>
                
                <div class="quick-action-card">
                    <h4>📋 Order History</h4>
                    <p>View all your previous orders and track their status</p>
                    <a href="dashboard.php" class="btn btn-secondary">View All Orders</a>
                </div>
                
                <div class="quick-action-card">
                    <h4>📞 Need Help?</h4>
                    <p>Contact our support team for assistance with your order</p>
                    <a href="contact.php" class="btn btn-secondary">Contact Support</a>
                </div>
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
        // Auto-refresh page every 30 seconds if order is in progress
        document.addEventListener('DOMContentLoaded', function() {
            const statusBadge = document.querySelector('.status-badge');
            if (statusBadge) {
                const statusText = statusBadge.textContent.toLowerCase();
                if (statusText.includes('pending') || statusText.includes('processing') || statusText.includes('shipped')) {
                    // Auto-refresh every 30 seconds for in-progress orders
                    setTimeout(function() {
                        window.location.reload();
                    }, 30000);
                }
            }
            
            // Add smooth scrolling for any anchor links
            const anchorLinks = document.querySelectorAll('a[href^="#"]');
            anchorLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    const targetSection = document.querySelector(targetId);
                    
                    if (targetSection) {
                        targetSection.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>